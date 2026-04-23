<?php
/**
 * Skill: Priority Rules Engine.
 *
 * Runs before confidence-based classification. If a hard rule matches, the
 * caller should trust it and short-circuit downstream guesswork.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Converter\Skills;

use StackBlueprint\Converter\Generated\SimulationKnowledge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class PriorityRulesEngine {

	/**
	 * Evaluate hard rules for a section/root node.
	 *
	 * @param \DOMElement $node Section/root node.
	 * @param \DOMXPath   $xp   XPath helper.
	 * @return array<string,mixed>|null
	 */
	public function evaluate_section( \DOMElement $node, \DOMXPath $xp ): ?array {
		$compiled = $this->evaluate_compiled_rules( $node, $xp );
		if ( null !== $compiled ) {
			return $compiled;
		}

		// RULE-011 (decorative overlay) before fixed-position rule.
		if ( $this->is_decorative_overlay( $node ) ) {
			return [
				'rule'   => 'RULE-011',
				'action' => 'native',
				'reason' => 'Decorative fixed/absolute overlay can stay structural with companion CSS.',
			];
		}

		// RULE-001: position:fixed => HTML.
		if ( $this->has_fixed_position_signal( $node, $xp ) ) {
			return [
				'rule'   => 'RULE-001',
				'action' => 'html',
				'reason' => 'Fixed-position behavior is unsafe in native container hierarchy.',
			];
		}

		// RULE-002: canvas => HTML/global handling.
		if ( $xp->query( './/canvas | self::canvas', $node )->length > 0 ) {
			return [
				'rule'   => 'RULE-002',
				'action' => 'html',
				'reason' => 'Canvas behavior requires preserved/global handling.',
			];
		}

		// RULE-003: script mutates text/HTML.
		if ( $this->has_script_mutation_signal( $node, $xp ) ) {
			return [
				'rule'   => 'RULE-003',
				'action' => 'html',
				'reason' => 'Source script mutates content at runtime; preserve behavior island.',
			];
		}

		// RULE-004: table => HTML.
		if ( $xp->query( './/table | self::table', $node )->length > 0 ) {
			return [
				'rule'   => 'RULE-004',
				'action' => 'html',
				'reason' => 'Table structures exceed free-native coverage for robust carryover.',
			];
		}

		// RULE-005: css columns => HTML.
		if ( $this->has_css_columns_signal( $node, $xp ) ) {
			return [
				'rule'   => 'RULE-005',
				'action' => 'html',
				'reason' => 'CSS Columns/masonry layout has no stable native equivalent.',
			];
		}

		return null;
	}

	private function evaluate_compiled_rules( \DOMElement $node, \DOMXPath $xp ): ?array {
		foreach ( SimulationKnowledge::hard_rules() as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}
			$type = (string) ( $rule['type'] ?? '' );
			$action = (string) ( $rule['action'] ?? '' );
			$rule_id = (string) ( $rule['id'] ?? 'RULE-COMPILED' );
			$reason = (string) ( $rule['reason'] ?? 'Compiled simulation rule matched.' );

			if ( 'tag' === $type ) {
				$tag = strtolower( (string) ( $rule['tag'] ?? '' ) );
				if ( '' !== $tag && $xp->query( './/' . $tag . ' | self::' . $tag, $node )->length > 0 ) {
					return [ 'rule' => $rule_id, 'action' => $action, 'reason' => $reason ];
				}
				continue;
			}

			if ( 'style_contains' === $type ) {
				$needle = strtolower( (string) ( $rule['needle'] ?? '' ) );
				if ( '' === $needle ) {
					continue;
				}
				// Surgical narrowing: RULE-005 must be section-local columns contract,
				// not a broad text hit across unrelated style/script content.
				if ( 'RULE-005' === $rule_id ) {
					if ( $this->has_css_columns_signal( $node, $xp ) ) {
						return [ 'rule' => $rule_id, 'action' => $action, 'reason' => $reason ];
					}
					continue;
				}
				$html = strtolower( (string) $node->ownerDocument->saveHTML( $node ) );
				if ( str_contains( $html, $needle ) ) {
					return [ 'rule' => $rule_id, 'action' => $action, 'reason' => $reason ];
				}
				continue;
			}

			if ( 'script_mutation' === $type && $this->has_script_mutation_signal( $node, $xp ) ) {
				return [ 'rule' => $rule_id, 'action' => $action, 'reason' => $reason ];
			}
		}
		return null;
	}

	private function has_fixed_position_signal( \DOMElement $node, \DOMXPath $xp ): bool {
		$scope_html = strtolower( $node->ownerDocument->saveHTML( $node ) );
		if ( str_contains( $scope_html, 'position:fixed' ) || str_contains( $scope_html, 'position: fixed' ) ) {
			return true;
		}
		$fixed_nodes = $xp->query(
			'.//*[contains(@style,"position:fixed") or contains(@style,"position: fixed") or contains(@class,"fixed")]',
			$node
		);
		return (bool) ( $fixed_nodes && $fixed_nodes->length > 0 );
	}

	private function has_script_mutation_signal( \DOMElement $node, \DOMXPath $xp ): bool {
		$scripts = $xp->query( './/script', $node );
		if ( ! $scripts || 0 === $scripts->length ) {
			return false;
		}
		foreach ( $scripts as $script ) {
			$code = strtolower( (string) $script->textContent );
			if (
				( str_contains( $code, 'queryselector' ) || str_contains( $code, 'getelementbyid' ) ) &&
				( str_contains( $code, '.textcontent') || str_contains( $code, '.innerhtml' ) || str_contains( $code, '.innertext' ) )
			) {
				return true;
			}
		}
		return false;
	}

	private function has_css_columns_signal( \DOMElement $node, \DOMXPath $xp ): bool {
		$inline_nodes = $xp->query(
			'.//*[contains(@style,"column-count") or contains(@style,"columns:")] | self::*[contains(@style,"column-count") or contains(@style,"columns:")]',
			$node
		);
		if ( $inline_nodes && $inline_nodes->length > 0 ) {
			return true;
		}

		$tokens = $this->collect_section_hook_tokens( $node, $xp );
		if ( empty( $tokens ) ) {
			return false;
		}

		$styles = $xp->query( '//style' );
		if ( ! $styles || 0 === $styles->length ) {
			return false;
		}

		foreach ( $styles as $style ) {
			$css = (string) $style->textContent;
			if ( '' === trim( $css ) ) {
				continue;
			}
			if ( ! preg_match_all( '/([^{}]+)\{([^{}]+)\}/', $css, $rules, PREG_SET_ORDER ) ) {
				continue;
			}
			foreach ( $rules as $rule ) {
				$selector = strtolower( trim( (string) ( $rule[1] ?? '' ) ) );
				$body = strtolower( trim( (string) ( $rule[2] ?? '' ) ) );
				if ( '' === $selector || '' === $body ) {
					continue;
				}
				if ( ! str_contains( $body, 'column-count' ) && ! str_contains( $body, 'columns:' ) ) {
					continue;
				}
				foreach ( $tokens as $token ) {
					if ( '' !== $token && str_contains( $selector, $token ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Collect CSS hook tokens that belong to this section subtree.
	 *
	 * @return array<int,string>
	 */
	private function collect_section_hook_tokens( \DOMElement $node, \DOMXPath $xp ): array {
		$tokens = [];
		$nodes = $xp->query( 'self::*[@id or @class]', $node );
		if ( ! $nodes ) {
			return [];
		}
		foreach ( $nodes as $el ) {
			if ( ! $el instanceof \DOMElement ) {
				continue;
			}
			$id = strtolower( trim( (string) $el->getAttribute( 'id' ) ) );
			if ( '' !== $id ) {
				$tokens[] = '#' . $id;
			}
			$classes = preg_split( '/\s+/', strtolower( trim( (string) $el->getAttribute( 'class' ) ) ) );
			foreach ( (array) $classes as $class ) {
				$class = trim( (string) $class );
				if ( '' !== $class ) {
					$tokens[] = '.' . $class;
				}
			}
		}
		return array_values( array_unique( $tokens ) );
	}

	private function is_decorative_overlay( \DOMElement $node ): bool {
		$style = strtolower( preg_replace( '/\s+/', '', (string) $node->getAttribute( 'style' ) ) );
		if ( '' === $style ) {
			return false;
		}
		$has_position = str_contains( $style, 'position:absolute' ) || str_contains( $style, 'position:fixed' );
		$has_no_events = str_contains( $style, 'pointer-events:none' );
		$has_overlay_shape = str_contains( $style, 'inset:0' ) || ( str_contains( $style, 'width:100%' ) && str_contains( $style, 'height:100%' ) );
		$no_text = '' === trim( (string) $node->textContent );
		return $has_position && $has_no_events && $has_overlay_shape && $no_text;
	}
}


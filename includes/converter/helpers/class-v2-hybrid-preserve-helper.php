<?php
/**
 * V2 Hybrid Preserve Helper.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Converter\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class V2HybridPreserveHelper {

	/**
	 * Detect real CSS columns/masonry declarations without matching
	 * grid-template-columns.
	 */
	public static function has_columns_or_masonry_declaration( string $css ): bool {
		$css = strtolower( $css );
		if ( '' === trim( $css ) ) {
			return false;
		}

		if ( preg_match( '/(^|[;\s{])(?:-webkit-|-moz-)?(?:column-count|column-width|column-gap|column-fill|column-rule(?:-[\w-]+)?|columns)\s*:/i', $css ) ) {
			return true;
		}

		return (bool) preg_match( '/(^|[;\s{])grid-template-rows\s*:\s*masonry\b|(^|[;\s{])grid-template-columns\s*:\s*masonry\b/i', $css );
	}

	public static function node_has_inline_columns_contract( \DOMElement $node, \DOMXPath $xp ): bool {
		$nodes = $xp->query( 'self::*[@style] | .//*[@style]', $node );
		if ( ! $nodes ) {
			return false;
		}

		foreach ( $nodes as $el ) {
			if ( $el instanceof \DOMElement && self::has_columns_or_masonry_declaration( (string) $el->getAttribute( 'style' ) ) ) {
				return true;
			}
		}

		return false;
	}

	public static function node_has_columns_contract( \DOMElement $node, \DOMXPath $xp, string $raw_css = '' ): bool {
		if ( self::node_has_inline_columns_contract( $node, $xp ) ) {
			return true;
		}

		$tokens = self::collect_hook_tokens( $node, $xp, false );
		if ( empty( $tokens ) ) {
			return false;
		}

		foreach ( self::style_blocks( $node, $xp, $raw_css ) as $css ) {
			foreach ( self::column_contract_rules( $css ) as $rule ) {
				foreach ( $tokens as $token ) {
					if ( self::selector_matches_token( (string) $rule['selector'], $token ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Find local subtree roots that carry columns/masonry behavior.
	 *
	 * @return array<int,\DOMElement>
	 */
	public static function find_columns_contract_nodes( \DOMElement $node, \DOMXPath $xp, string $raw_css = '' ): array {
		$matches = [];
		$inline_nodes = $xp->query( 'self::*[@style] | .//*[@style]', $node );
		if ( $inline_nodes ) {
			foreach ( $inline_nodes as $el ) {
				if ( $el instanceof \DOMElement && self::has_columns_or_masonry_declaration( (string) $el->getAttribute( 'style' ) ) ) {
					$matches[ spl_object_id( $el ) ] = $el;
				}
			}
		}

		$token_map = self::collect_hook_token_map( $node, $xp );
		if ( ! empty( $token_map ) ) {
			foreach ( self::style_blocks( $node, $xp, $raw_css ) as $css ) {
				foreach ( self::column_contract_rules( $css ) as $rule ) {
					$selector = (string) $rule['selector'];
					foreach ( $token_map as $token => $elements ) {
						if ( ! self::selector_matches_token( $selector, $token ) ) {
							continue;
						}
						foreach ( $elements as $el ) {
							if ( $el instanceof \DOMElement ) {
								$matches[ spl_object_id( $el ) ] = $el;
							}
						}
					}
				}
			}
		}

		return array_values( self::prefer_local_roots( array_values( $matches ) ) );
	}

	/**
	 * Collect root or subtree CSS hook tokens.
	 *
	 * @return array<int,string>
	 */
	public static function collect_hook_tokens( \DOMElement $node, \DOMXPath $xp, bool $include_descendants = true ): array {
		return array_keys( self::collect_hook_token_map( $node, $xp, $include_descendants ) );
	}

	/**
	 * @return array<string,array<int,\DOMElement>>
	 */
	private static function collect_hook_token_map( \DOMElement $node, \DOMXPath $xp, bool $include_descendants = true ): array {
		$map = [];
		$query = $include_descendants ? 'self::*[@id or @class] | .//*[@id or @class]' : 'self::*[@id or @class]';
		$nodes = $xp->query( $query, $node );
		if ( ! $nodes ) {
			return [];
		}

		foreach ( $nodes as $el ) {
			if ( ! $el instanceof \DOMElement ) {
				continue;
			}
			$id = strtolower( trim( (string) $el->getAttribute( 'id' ) ) );
			if ( '' !== $id ) {
				$map[ '#' . $id ][] = $el;
			}
			$classes = preg_split( '/\s+/', strtolower( trim( (string) $el->getAttribute( 'class' ) ) ) );
			foreach ( (array) $classes as $class ) {
				$class = trim( (string) $class );
				if ( '' !== $class ) {
					$map[ '.' . $class ][] = $el;
				}
			}
		}

		return $map;
	}

	/**
	 * @return array<int,array{selector:string,body:string}>
	 */
	private static function column_contract_rules( string $css ): array {
		$out = [];
		if ( ! preg_match_all( '/([^{}]+)\{([^{}]+)\}/', $css, $rules, PREG_SET_ORDER ) ) {
			return [];
		}

		foreach ( $rules as $rule ) {
			$selector = strtolower( trim( (string) ( $rule[1] ?? '' ) ) );
			$body = strtolower( trim( (string) ( $rule[2] ?? '' ) ) );
			if ( '' === $selector || '' === $body || ! self::has_columns_or_masonry_declaration( $body ) ) {
				continue;
			}
			$out[] = [
				'selector' => $selector,
				'body'     => $body,
			];
		}

		return $out;
	}

	/**
	 * @return array<int,string>
	 */
	private static function style_blocks( \DOMElement $node, \DOMXPath $xp, string $raw_css ): array {
		$blocks = [];
		if ( '' !== trim( $raw_css ) ) {
			$blocks[] = $raw_css;
		}

		$styles = $xp->query( '//style' );
		if ( $styles ) {
			foreach ( $styles as $style ) {
				$css = trim( (string) $style->textContent );
				if ( '' !== $css ) {
					$blocks[] = $css;
				}
			}
		}

		return array_values( array_unique( $blocks ) );
	}

	private static function selector_matches_token( string $selector, string $token ): bool {
		$token = strtolower( trim( $token ) );
		if ( '' === $token ) {
			return false;
		}

		$escaped = str_replace( ':', '\\\\:', $token );
		return str_contains( $selector, $token ) || str_contains( $selector, $escaped );
	}

	/**
	 * Keep the narrowest roots so a section-level match does not swallow a
	 * smaller behavior island when that smaller island also has a contract.
	 *
	 * @param array<int,\DOMElement> $nodes Matching nodes.
	 * @return array<int,\DOMElement>
	 */
	private static function prefer_local_roots( array $nodes ): array {
		$out = [];
		foreach ( $nodes as $candidate ) {
			$has_matching_descendant = false;
			foreach ( $nodes as $other ) {
				if ( $candidate === $other ) {
					continue;
				}
				if ( self::is_ancestor_of( $candidate, $other ) ) {
					$has_matching_descendant = true;
					break;
				}
			}
			if ( ! $has_matching_descendant ) {
				$out[] = $candidate;
			}
		}

		return $out;
	}

	private static function is_ancestor_of( \DOMElement $ancestor, \DOMElement $node ): bool {
		$parent = $node->parentNode;
		while ( $parent instanceof \DOMElement ) {
			if ( $parent === $ancestor ) {
				return true;
			}
			$parent = $parent->parentNode;
		}
		return false;
	}
}

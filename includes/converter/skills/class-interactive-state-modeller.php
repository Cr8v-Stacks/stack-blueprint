<?php
/**
 * Skill: Interactive State Modeller.
 *
 * Extracts :hover, :focus, and :active CSS rules from the source CSS and
 * routes them to the correct output destination:
 *
 *  V1 — all states stay inlined in HTML widget <style> blocks (already there)
 *  V2 — states → companion CSS targeting [prefix]-[section]-[element] classes
 *
 * Also detects parent:hover-affects-child relationships which cannot be
 * expressed in Elementor's per-widget hover settings.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Converter\Skills;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class InteractiveStateModeller
 */
class InteractiveStateModeller {

	// ── State Routing Constants ──────────────────────────────

	public const ROUTE_COMPANION_CSS  = 'companion_css';
	public const ROUTE_HTML_WIDGET    = 'html_widget';
	public const ROUTE_ELEMENTOR_PRO  = 'elementor_pro_note';

	// ── Public API ───────────────────────────────────────────

	/**
	 * Extract and route all interactive state rules from a CSS block.
	 *
	 * @param  string $css      Full CSS string (from CssResolver or raw CSS).
	 * @param  string $prefix   CSS class prefix.
	 * @param  string $strategy 'v1' | 'v2'.
	 * @return array<string, mixed> {
	 *   companion_css: string,  — CSS rules to add to companion CSS
	 *   html_widget:   string,  — CSS rules to inline in HTML widget <style>
	 *   notes:         string[], — Elementor Pro notes for the conversion report
	 * }
	 */
	public function extract( string $css, string $prefix, string $strategy ): array {
		$hover_rules = $this->parseHoverRules( $css );

		$companion_css = '';
		$html_widget   = '';
		$notes         = [];

		foreach ( $hover_rules as $rule ) {
			$route = $this->routeRule( $rule, $strategy );

			switch ( $route ) {
				case self::ROUTE_COMPANION_CSS:
					$companion_css .= $this->adaptSelector( $rule, $prefix ) . "\n";
					break;

				case self::ROUTE_HTML_WIDGET:
					$html_widget .= $rule['raw'] . "\n";
					break;

				case self::ROUTE_ELEMENTOR_PRO:
					$notes[] = 'Motion effect (transform/opacity) detected on ' . $rule['selector'] .
					           ' — consider using Elementor Pro Motion Effects for this animation.';
					// Still add to companion CSS as fallback.
					$companion_css .= $this->adaptSelector( $rule, $prefix ) . "\n";
					break;
			}
		}

		// Extract transition declarations (always go to companion CSS).
		$transitions = $this->parseTransitions( $css );
		$companion_css .= $transitions;

		// Extract ::before / ::after pseudo-elements.
		$pseudos = $this->parsePseudoElements( $css, $prefix, $strategy );
		$companion_css .= $pseudos;

		return [
			'companion_css' => $companion_css,
			'html_widget'   => $html_widget,
			'notes'         => $notes,
		];
	}

	/**
	 * Detect parent:hover → child effects.
	 *
	 * @param  string $css Full CSS string.
	 * @return array<int, array<string, string>> Each entry: { parent, child, properties }
	 */
	public function detectParentChildHover( string $css ): array {
		$relationships = [];

		// Match: .parent:hover .child { ... }
		preg_match_all(
			'/([.#][^\s{]+):hover\s+([.#][^\s{]+)\s*\{([^}]+)\}/m',
			$css,
			$matches,
			PREG_SET_ORDER
		);

		foreach ( $matches as $m ) {
			$relationships[] = [
				'parent'     => trim( $m[1] ),
				'child'      => trim( $m[2] ),
				'properties' => trim( $m[3] ),
			];
		}

		return $relationships;
	}

	// ── CSS Parsing ──────────────────────────────────────────

	/**
	 * Parse all hover/focus/active rules from a CSS string.
	 *
	 * @param  string $css CSS string.
	 * @return array<int, array<string, mixed>>
	 */
	private function parseHoverRules( string $css ): array {
		$rules = [];

		preg_match_all(
			'/([^{}]+):(?:hover|focus|focus-visible|active|focus-within)\s*\{([^}]+)\}/m',
			$css,
			$matches,
			PREG_SET_ORDER
		);

		foreach ( $matches as $m ) {
			$selector   = trim( $m[1] );
			$properties = trim( $m[2] );
			$rules[]    = [
				'selector'   => $selector,
				'properties' => $properties,
				'raw'        => $m[0],
				'type'       => $this->classifyProperties( $properties ),
			];
		}

		return $rules;
	}

	/**
	 * Classify what type of interactive state a rule represents.
	 *
	 * @param  string $properties CSS properties block.
	 * @return string 'transform' | 'color' | 'opacity' | 'mixed'
	 */
	private function classifyProperties( string $properties ): string {
		$has_transform = str_contains( $properties, 'transform' );
		$has_opacity   = str_contains( $properties, 'opacity' ) && ! str_contains( $properties, 'color' );
		$has_color     = str_contains( $properties, 'color' ) || str_contains( $properties, 'background' ) || str_contains( $properties, 'border' );

		if ( $has_transform && ! $has_color ) {
			return 'transform';
		}
		if ( $has_opacity && ! $has_color ) {
			return 'opacity';
		}
		return 'color';
	}

	/**
	 * Determine where a rule should be routed.
	 *
	 * @param  array<string, mixed> $rule     Parsed rule.
	 * @param  string               $strategy 'v1' | 'v2'.
	 * @return string Route constant.
	 */
	private function routeRule( array $rule, string $strategy ): string {
		// V1: all states stay in HTML widgets (already inlined in source).
		if ( 'v1' === $strategy ) {
			return self::ROUTE_HTML_WIDGET;
		}

		// Transform-only hover → could use Elementor Pro Motion Effects.
		if ( 'transform' === $rule['type'] ) {
			return self::ROUTE_ELEMENTOR_PRO;
		}

		// Color/background/border changes → companion CSS.
		return self::ROUTE_COMPANION_CSS;
	}

	/**
	 * Adapt a selector to use the Elementor-specific class targeting.
	 *
	 * @param  array<string, mixed> $rule   Parsed rule.
	 * @param  string               $prefix CSS prefix.
	 * @return string Adapted CSS rule string.
	 */
	private function adaptSelector( array $rule, string $prefix ): string {
		$selector   = $rule['selector'];
		$properties = $rule['properties'];
		$pseudo     = '';

		// Detect which pseudo-class was used.
		if ( str_contains( $rule['raw'], ':hover' ) ) {
			$pseudo = ':hover';
		} elseif ( str_contains( $rule['raw'], ':focus' ) ) {
			$pseudo = ':focus';
		}

		// Original selector already has prefix → use directly.
		if ( str_contains( $selector, $prefix ) ) {
			return "{$selector}{$pseudo} {\n  {$properties}\n}";
		}

		// Add !important to color/background rules to beat theme defaults.
		$props_lines = explode( ';', $properties );
		$adapted     = [];
		foreach ( $props_lines as $line ) {
			$line = trim( $line );
			if ( $line && ! str_contains( $line, '!important' ) ) {
				$adapted[] = $line . ' !important';
			} elseif ( $line ) {
				$adapted[] = $line;
			}
		}

		return "{$selector}{$pseudo} {\n  " . implode( ";\n  ", $adapted ) . ";\n}";
	}

	/**
	 * Parse transition declarations from CSS.
	 *
	 * @param  string $css CSS string.
	 * @return string Transition rules for companion CSS.
	 */
	private function parseTransitions( string $css ): string {
		$output = '';
		preg_match_all(
			'/([^{}]+)\s*\{[^}]*transition\s*:[^}]+\}/m',
			$css,
			$matches
		);
		foreach ( ( $matches[0] ?? [] ) as $rule ) {
			// Only include if the selector has a class (not bare element selectors).
			if ( preg_match( '/\.[a-z]/', $rule ) ) {
				$output .= $rule . "\n";
			}
		}
		return $output;
	}

	/**
	 * Parse ::before and ::after pseudo-elements.
	 *
	 * @param  string $css      CSS string.
	 * @param  string $prefix   CSS prefix.
	 * @param  string $strategy 'v1' | 'v2'.
	 * @return string Pseudo-element rules for companion CSS.
	 */
	private function parsePseudoElements( string $css, string $prefix, string $strategy ): string {
		if ( 'v1' === $strategy ) {
			return ''; // V1: pseudo-elements stay in HTML widget <style> blocks.
		}

		$output = '';
		preg_match_all(
			'/([^{}]+)::(?:before|after)\s*\{([^}]+)\}/m',
			$css,
			$matches,
			PREG_SET_ORDER
		);

		foreach ( $matches as $m ) {
			$selector = trim( $m[1] );
			$props    = trim( $m[2] );

			// Skip bare element selectors (body, html, *).
			if ( ! preg_match( '/\.[a-z]/', $selector ) ) {
				continue;
			}

			$output .= "{$selector}::" . ( str_contains( $m[0], '::before' ) ? 'before' : 'after' ) .
			           " {\n  {$props}\n}\n";
		}

		return $output;
	}
}

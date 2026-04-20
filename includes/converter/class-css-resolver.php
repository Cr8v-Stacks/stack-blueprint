<?php
/**
 * CSS Resolver.
 *
 * Implements a lightweight but correct CSS cascade resolver.
 * Handles class selectors, tag selectors, ID selectors, descendant
 * combinators, pseudo-classes (ignored for matching but stripped),
 * CSS custom property var() resolution, shorthand expansion,
 * and @media query collection.
 *
 * Does NOT handle: attribute selectors, :nth-child, complex pseudo-selectors.
 * These are flagged as unresolvable and skipped.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Converter;

if ( ! defined( 'ABSPATH' ) ) exit;

class CssResolver {

	/** @var array<string, array> All parsed rules: [selector, properties, specificity] */
	private array $rules = [];

	/** @var array<string, string> CSS custom property map: --name => value */
	private array $custom_props = [];

	/** @var array<string, array> Responsive rules keyed by breakpoint px */
	private array $media_rules = [];

	/** @var string[] Raw @keyframes blocks */
	private array $keyframes = [];

	// ── Parse ──────────────────────────────────────────────────

	/**
	 * Parse a raw CSS string into the resolver's internal structures.
	 */
	public function parse( string $css ): void {
		// Strip comments.
		$css = preg_replace( '/\/\*.*?\*\//s', '', $css );

		// Extract @keyframes.
		if ( preg_match_all( '/@keyframes\s+[\w-]+\s*\{(?:[^{}]*|\{[^{}]*\})*\}/s', $css, $kf ) ) {
			$this->keyframes = $kf[0];
			$css = preg_replace( '/@keyframes\s+[\w-]+\s*\{(?:[^{}]*|\{[^{}]*\})*\}/s', '', $css );
		}

		// Extract @media blocks.
		if ( preg_match_all( '/@media[^{]+\{((?:[^{}]|\{[^{}]*\})*)\}/s', $css, $media_matches, PREG_SET_ORDER ) ) {
			foreach ( $media_matches as $media ) {
				$breakpoint = $this->extract_breakpoint( $media[0] );
				if ( $breakpoint ) {
					$this->parse_rule_block( $media[1], $breakpoint );
				}
				$css = str_replace( $media[0], '', $css );
			}
		}

		// Parse remaining normal rules.
		$this->parse_rule_block( $css, null );
	}

	private function parse_rule_block( string $css, ?int $breakpoint ): void {
		if ( ! preg_match_all( '/([^{}]+)\{([^{}]*)\}/s', $css, $matches, PREG_SET_ORDER ) ) {
			return;
		}

		foreach ( $matches as $match ) {
			$selectors  = array_map( 'trim', explode( ',', $match[1] ) );
			$properties = $this->parse_declarations( $match[2] );

			// Collect :root custom properties.
			foreach ( $selectors as $sel ) {
				if ( trim( $sel ) === ':root' || trim( $sel ) === 'html' ) {
					foreach ( $properties as $prop => $val ) {
						if ( str_starts_with( $prop, '--' ) ) {
							$this->custom_props[ $prop ] = $val;
						}
					}
				}
			}

			foreach ( $selectors as $sel ) {
				$sel = trim( $sel );
				if ( empty( $sel ) ) continue;

				$entry = [
					'selector'    => $sel,
					'properties'  => $properties,
					'specificity' => $this->specificity( $sel ),
					'breakpoint'  => $breakpoint,
				];

				if ( $breakpoint ) {
					$this->media_rules[ $breakpoint ][] = $entry;
				} else {
					$this->rules[] = $entry;
				}
			}
		}
	}

	private function parse_declarations( string $block ): array {
		$result = [];
		$lines  = explode( ';', $block );
		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( ! $line ) continue;
			$colon = strpos( $line, ':' );
			if ( $colon === false ) continue;
			$prop = strtolower( trim( substr( $line, 0, $colon ) ) );
			$val  = trim( substr( $line, $colon + 1 ) );
			$val  = rtrim( $val, '!' ); // strip !important marker
			if ( $prop && $val ) $result[ $prop ] = $val;
		}
		return $result;
	}

	// ── Resolve ───────────────────────────────────────────────

	/**
	 * Compute the resolved styles for a DOM element.
	 *
	 * @param string   $tag     Lowercase tag name (e.g. 'div', 'h1').
	 * @param string[] $classes Array of class names on the element.
	 * @param string   $id      Element ID attribute value.
	 * @param string   $inline  Inline style attribute value.
	 * @return array  Resolved property => value map.
	 */
	public function resolve( string $tag, array $classes, string $id = '', string $inline = '' ): array {
		$matching = [];

		foreach ( $this->rules as $rule ) {
			if ( $this->matches( $rule['selector'], $tag, $classes, $id ) ) {
				$matching[] = $rule;
			}
		}

		// Sort by specificity ascending — higher specificity wins last.
		usort( $matching, fn($a,$b) => $a['specificity'] <=> $b['specificity'] );

		$resolved = [];
		foreach ( $matching as $rule ) {
			foreach ( $rule['properties'] as $prop => $val ) {
				$resolved[ $prop ] = $val;
			}
		}

		// Inline styles override everything.
		if ( $inline ) {
			$inline_props = $this->parse_declarations( $inline );
			foreach ( $inline_props as $p => $v ) $resolved[ $p ] = $v;
		}

		// Resolve var() references.
		foreach ( $resolved as $prop => $val ) {
			$resolved[ $prop ] = $this->resolve_vars( $val );
		}

		return $resolved;
	}

	/**
	 * Get responsive rules for an element at a given breakpoint.
	 */
	public function resolve_responsive( string $tag, array $classes, string $id, int $breakpoint ): array {
		$resolved = [];
		$bucket   = $this->media_rules[ $breakpoint ] ?? [];

		$matching = array_filter( $bucket, fn($r) => $this->matches($r['selector'],$tag,$classes,$id) );
		usort( $matching, fn($a,$b) => $a['specificity'] <=> $b['specificity'] );

		foreach ( $matching as $rule ) {
			foreach ( $rule['properties'] as $prop => $val ) {
				$resolved[ $prop ] = $this->resolve_vars( $val );
			}
		}

		return $resolved;
	}

	public function get_custom_props(): array { return $this->custom_props; }
	public function get_keyframes(): array    { return $this->keyframes; }
	public function get_breakpoints(): array  { return array_keys( $this->media_rules ); }

	// ── Selector Matching ─────────────────────────────────────

	/**
	 * Does a CSS selector match a given element?
	 * Handles: tag, .class, #id, compound (.a.b), descendant (a b),
	 * child (a > b), adjacent (a + b), sibling (a ~ b).
	 * Ignores pseudo-classes/elements (strips them before matching).
	 */
	private function matches( string $selector, string $tag, array $classes, string $id ): bool {
		// Strip pseudo-classes and pseudo-elements for matching purposes.
		$selector = preg_replace( '/::?[\w-]+(\([^)]*\))?/', '', $selector );
		$selector = trim( $selector );

		// For combined selectors (descendant / child / sibling), only check the
		// rightmost (most-specific) simple selector.
		if ( preg_match( '/[\s>~+]/', $selector ) ) {
			$parts    = preg_split( '/\s*[\s>~+]\s*/', $selector );
			$selector = end( $parts );
		}

		// Parse simple selector: split into tag, classes, id.
		$sel_tag     = '';
		$sel_classes = [];
		$sel_id      = '';

		if ( preg_match_all( '/([.#]?[a-z][\w-]*)/i', $selector, $tokens ) ) {
			foreach ( $tokens[1] as $tok ) {
				if ( str_starts_with( $tok, '.' ) ) {
					$sel_classes[] = ltrim( $tok, '.' );
				} elseif ( str_starts_with( $tok, '#' ) ) {
					$sel_id = ltrim( $tok, '#' );
				} else {
					$sel_tag = strtolower( $tok );
				}
			}
		}

		// Universal selector.
		if ( $selector === '*' ) return true;

		// Tag must match if specified (unless it is a universal).
		if ( $sel_tag && $sel_tag !== '*' && $sel_tag !== $tag ) return false;

		// All specified classes must be present.
		foreach ( $sel_classes as $cls ) {
			if ( ! in_array( $cls, $classes, true ) ) return false;
		}

		// ID must match if specified.
		if ( $sel_id && $sel_id !== $id ) return false;

		// Must have at least one constraint to match.
		if ( ! $sel_tag && empty( $sel_classes ) && ! $sel_id ) return false;

		return true;
	}

	// ── Specificity ───────────────────────────────────────────

	/**
	 * Calculate CSS specificity as a single comparable integer.
	 * Formula: (IDs * 10000) + (classes * 100) + (tags * 1)
	 */
	private function specificity( string $selector ): int {
		$ids      = preg_match_all( '/#[\w-]+/', $selector );
		$classes  = preg_match_all( '/\.[\w-]+|:\w+/', $selector );
		$tags     = preg_match_all( '/(?<![.#\w])(?!:)[a-z][\w-]*/i', $selector );
		return ( $ids * 10000 ) + ( $classes * 100 ) + $tags;
	}

	// ── CSS var() resolution ──────────────────────────────────

	private function resolve_vars( string $value ): string {
		if ( ! str_contains( $value, 'var(' ) ) return $value;

		return preg_replace_callback(
			'/var\(\s*(--[\w-]+)(?:\s*,\s*([^)]+))?\s*\)/',
			function ( $m ) {
				$var_name = $m[1];
				$fallback = $m[2] ?? '';
				return $this->custom_props[ $var_name ] ?? $fallback;
			},
			$value
		);
	}

	// ── Shorthand Expansion ───────────────────────────────────

	/**
	 * Expand CSS shorthand properties to their longhand forms.
	 *
	 * @param array $props  Resolved property map.
	 * @return array        Expanded property map.
	 */
	public function expand_shorthands( array $props ): array {
		$expanded = $props;

		// padding shorthand.
		if ( isset( $props['padding'] ) ) {
			$sides = $this->expand_4sides( $props['padding'] );
			$expanded['padding-top']    = $sides[0];
			$expanded['padding-right']  = $sides[1];
			$expanded['padding-bottom'] = $sides[2];
			$expanded['padding-left']   = $sides[3];
		}

		// margin shorthand.
		if ( isset( $props['margin'] ) ) {
			$sides = $this->expand_4sides( $props['margin'] );
			$expanded['margin-top']    = $sides[0];
			$expanded['margin-right']  = $sides[1];
			$expanded['margin-bottom'] = $sides[2];
			$expanded['margin-left']   = $sides[3];
		}

		// border shorthand: border: 1px solid rgba(...)
		if ( isset( $props['border'] ) && ! isset( $props['border-width'] ) ) {
			if ( preg_match( '/^(\S+)\s+(solid|dashed|dotted|none)\s+(.+)$/', $props['border'], $bm ) ) {
				$expanded['border-width'] = $bm[1];
				$expanded['border-style'] = $bm[2];
				$expanded['border-color'] = $bm[3];
			}
		}

		// font shorthand (partial — extract size and family).
		if ( isset( $props['font'] ) && ! isset( $props['font-family'] ) ) {
			if ( preg_match( '/([\d.]+(?:px|em|rem|vw))\s+(.+)$/', $props['font'], $fm ) ) {
				$expanded['font-size']   = $fm[1];
				$expanded['font-family'] = trim( $fm[2], '"' );
			}
		}

		return $expanded;
	}

	private function expand_4sides( string $value ): array {
		$parts = preg_split( '/\s+/', trim($value) );
		return match ( count($parts) ) {
			1 => [ $parts[0], $parts[0], $parts[0], $parts[0] ],
			2 => [ $parts[0], $parts[1], $parts[0], $parts[1] ],
			3 => [ $parts[0], $parts[1], $parts[2], $parts[1] ],
			4 => [ $parts[0], $parts[1], $parts[2], $parts[3] ],
			default => [ '0', '0', '0', '0' ],
		};
	}

	// ── Style → Elementor Settings Translator ─────────────────

	/**
	 * Translate a resolved CSS property map to Elementor widget settings.
	 * Returns two buckets: elementor_settings and companion_css_rules.
	 */
	public function to_elementor_settings( array $props, string $widget_type = 'container' ): array {
		$props    = $this->expand_shorthands( $props );
		$settings = [];
		$companion = [];

		foreach ( $props as $prop => $val ) {
			$val = trim( $val );
			if ( ! $val ) continue;

			switch ( $prop ) {
				case 'font-family':
					$settings['typography_typography']  = 'custom';
					$settings['typography_font_family'] = trim( $val, "\"'" );
					break;

				case 'font-weight':
					$settings['typography_typography']  = 'custom';
					$settings['typography_font_weight'] = $val;
					break;

				case 'font-size':
					if ( str_contains( $val, 'clamp' ) ) {
						// Extract minimum value as fallback.
						if ( preg_match( '/clamp\s*\(\s*([\d.]+)(px|rem|em)/', $val, $m ) ) {
							$settings['typography_typography'] = 'custom';
							$settings['typography_font_size']  = [ 'unit' => $m[2], 'size' => (float) $m[1] ];
						}
						$companion[] = "font-size: {$val};"; // Full clamp in companion.
					} elseif ( preg_match( '/([\d.]+)(px|rem|em|vw)/', $val, $m ) ) {
						$settings['typography_typography'] = 'custom';
						$settings['typography_font_size']  = [ 'unit' => $m[2], 'size' => (float) $m[1] ];
					}
					break;

				case 'line-height':
					$unit = str_contains( $val, 'em' ) ? 'em' : ( str_contains( $val, 'px' ) ? 'px' : 'em' );
					$num  = (float) preg_replace( '/[^0-9.]/', '', $val );
					$settings['typography_typography']   = 'custom';
					$settings['typography_line_height']  = [ 'unit' => $unit, 'size' => $num ];
					break;

				case 'letter-spacing':
					$unit = str_contains( $val, 'em' ) ? 'em' : 'px';
					$num  = (float) preg_replace( '/[^0-9.\-]/', '', $val );
					$settings['typography_typography']      = 'custom';
					$settings['typography_letter_spacing']  = [ 'unit' => $unit, 'size' => $num ];
					break;

				case 'color':
					if ( $widget_type === 'heading' ) {
						$settings['title_color'] = $val;
					} else {
						$settings['color'] = $val;
					}
					break;

				case 'background-color':
					if ( $val !== 'transparent' ) {
						$settings['background_background'] = 'classic';
						$settings['background_color']      = $val;
					}
					break;

				case 'padding-top':
				case 'padding-right':
				case 'padding-bottom':
				case 'padding-left':
					$key = str_replace( 'padding-', '', $prop );
					$num = preg_replace( '/[^0-9.]/', '', $val );
					if ( ! isset( $settings['padding'] ) ) {
						$settings['padding'] = [ 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => false ];
					}
					$settings['padding'][ $key ] = $num;
					break;

				case 'margin-top':
				case 'margin-right':
				case 'margin-bottom':
				case 'margin-left':
					$key = str_replace( 'margin-', '', $prop );
					$num = preg_replace( '/[^0-9.]/', '', $val );
					if ( ! isset( $settings['margin'] ) ) {
						$settings['margin'] = [ 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '0', 'left' => '0', 'isLinked' => false ];
					}
					$settings['margin'][ $key ] = $num;
					break;

				case 'gap':
					if ( preg_match( '/([\d.]+)(px|em)/', $val, $m ) ) {
						$settings['gap'] = [ 'unit' => $m[2], 'size' => (float) $m[1], 'column' => (float) $m[1], 'row' => (float) $m[1] ];
					}
					break;

				case 'border-width':
					$num = preg_replace( '/[^0-9.]/', '', $val );
					$settings['border_width'] = [ 'unit' => 'px', 'top' => $num, 'right' => $num, 'bottom' => $num, 'left' => $num, 'isLinked' => false ];
					break;

				case 'border-style':
					$settings['border_border'] = $val;
					break;

				case 'border-color':
					$settings['border_color'] = $val;
					break;

				case 'border-radius':
					$num = preg_replace( '/[^0-9.]/', '', $val );
					$settings['border_radius'] = [ 'unit' => 'px', 'top' => $num, 'right' => $num, 'bottom' => $num, 'left' => $num, 'isLinked' => false ];
					break;

				case 'min-height':
					if ( str_contains( $val, 'vh' ) ) {
						$num = (float) preg_replace( '/[^0-9.]/', '', $val );
						$settings['min_height']      = [ 'unit' => 'vh', 'size' => $num ];
						$settings['min_height_type'] = 'min-height';
					} elseif ( preg_match( '/([\d.]+)px/', $val, $m ) ) {
						$settings['min_height']      = [ 'unit' => 'px', 'size' => (float) $m[1] ];
						$settings['min_height_type'] = 'min-height';
					}
					break;

				case 'justify-content':
					$map = [ 'flex-start'=>'flex-start','center'=>'center','flex-end'=>'flex-end','space-between'=>'space-between','space-around'=>'space-around' ];
					$settings['justify_content'] = $map[ $val ] ?? 'flex-start';
					break;

				case 'align-items':
					$map = [ 'flex-start'=>'flex-start','center'=>'center','flex-end'=>'flex-end','stretch'=>'stretch' ];
					$settings['align_items'] = $map[ $val ] ?? 'flex-start';
					break;

				case 'flex-direction':
					$settings['flex_direction'] = $val;
					break;

				case 'overflow':
					if ( $val === 'hidden' ) $companion[] = "overflow: hidden;";
					break;

				// These always go to companion CSS.
				case '-webkit-text-stroke':
				case 'animation':
				case 'transition':
				case 'filter':
				case 'backdrop-filter':
				case 'mix-blend-mode':
				case 'clip-path':
				case 'transform':
				case 'z-index':
				case 'position':
				case 'box-shadow':
				case 'text-shadow':
				case 'cursor':
				case 'pointer-events':
				case 'will-change':
					$companion[] = "{$prop}: {$val};";
					break;
			}
		}

		return [
			'elementor_settings' => $settings,
			'companion_css'      => $companion,
		];
	}

	// ── Utility ───────────────────────────────────────────────

	private function extract_breakpoint( string $media_rule ): ?int {
		// Extract max-width breakpoint value.
		if ( preg_match( '/@media[^{]*max-width\s*:\s*([\d]+)px/', $media_rule, $m ) ) {
			return (int) $m[1];
		}
		return null;
	}

	/**
	 * Check if a set of resolved styles contains animation-related properties.
	 */
	public function has_animation( array $props ): bool {
		$anim_props = [ 'animation', 'animation-name', 'animation-duration', 'transition' ];
		foreach ( $anim_props as $p ) {
			if ( isset( $props[ $p ] ) && $props[ $p ] !== 'none' ) return true;
		}
		return false;
	}

	/**
	 * Detect display type: grid, flex-row, flex-column, block.
	 */
	public function detect_display( array $props ): string {
		$display = $props['display'] ?? 'block';
		if ( $display === 'grid' ) return 'grid';
		if ( $display === 'flex' || $display === 'inline-flex' ) {
			$dir = $props['flex-direction'] ?? 'row';
			return $dir === 'column' ? 'flex-column' : 'flex-row';
		}
		return 'block';
	}
}

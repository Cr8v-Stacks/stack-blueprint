<?php
/**
 * Pipeline Pass 1 — Document Intelligence.
 *
 * Reads the entire HTML document and builds a high-level
 * understanding without making any mapping decisions:
 * - Design tokens (CSS custom properties)
 * - Font list with weights
 * - Color palette
 * - Animation inventory (JS + CSS)
 * - Section boundary detection
 * - Per-section complexity scores
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Converter\Passes;

if ( ! defined( 'ABSPATH' ) ) exit;

class PassDocumentIntelligence {

	/** @var array Design token name => value */
	public array $tokens = [];

	/** @var array font-family => [weights] */
	public array $fonts = [];

	/** @var string[] Unique color values */
	public array $colors = [];

	/** @var array element_xpath => [animation types] */
	public array $animated_elements = [];

	/** @var array[] Section boundary nodes */
	public array $section_boundaries = [];

	/** @var array section_key => complexity_score */
	public array $section_complexity = [];

	/** @var bool Does the page have a canvas animation? */
	public bool $has_canvas = false;

	/** @var bool Does the page have a custom cursor? */
	public bool $has_cursor = false;

	/** @var string Raw concatenated CSS from all <style> blocks */
	public string $raw_css = '';

	/** @var string Raw concatenated JS from all <script> blocks */
	public string $raw_js = '';

	// ── Run ───────────────────────────────────────────────────

	public function run( \DOMDocument $dom, string $html ): void {
		$this->extract_raw_css( $dom );
		$this->extract_raw_js( $dom );
		$this->extract_tokens();
		$this->extract_fonts( $html );
		$this->extract_colors();
		$this->detect_animations( $dom );
		$this->detect_canvas_cursor( $dom );
		$this->detect_section_boundaries( $dom );
		$this->score_section_complexity( $dom );
	}

	// ── Extraction ────────────────────────────────────────────

	private function extract_raw_css( \DOMDocument $dom ): void {
		$xp = new \DOMXPath( $dom );
		$style_blocks = $xp->query( '//style' );
		if ( $style_blocks ) {
			foreach ( $style_blocks as $block ) {
				$this->raw_css .= "\n" . $block->textContent;
			}
		}
	}

	private function extract_raw_js( \DOMDocument $dom ): void {
		$xp = new \DOMXPath( $dom );
		$scripts = $xp->query( '//script[not(@src)]' );
		if ( $scripts ) {
			foreach ( $scripts as $s ) {
				$this->raw_js .= "\n" . $s->textContent;
			}
		}
	}

	private function extract_tokens(): void {
		// Extract from :root { ... } blocks.
		if ( preg_match_all( '/:root\s*\{([^}]+)\}/s', $this->raw_css, $roots ) ) {
			foreach ( $roots[1] as $root_block ) {
				if ( preg_match_all( '/--([\w-]+)\s*:\s*([^;]+);/', $root_block, $m, PREG_SET_ORDER ) ) {
					foreach ( $m as $match ) {
						$this->tokens[ '--' . trim( $match[1] ) ] = trim( $match[2] );
					}
				}
			}
		}
		// Also extract from other selectors (element-scoped tokens).
		if ( preg_match_all( '/--([\w-]+)\s*:\s*([^;}{]+);/', $this->raw_css, $m, PREG_SET_ORDER ) ) {
			foreach ( $m as $match ) {
				$key = '--' . trim( $match[1] );
				if ( ! isset( $this->tokens[ $key ] ) ) {
					$this->tokens[ $key ] = trim( $match[2] );
				}
			}
		}
	}

	private function extract_fonts( string $html ): void {
		// From Google Fonts URL.
		if ( preg_match_all( '/family=([^&"\')\s]+)/', $html, $m ) ) {
			foreach ( $m[1] as $raw ) {
				$fam     = urldecode( explode( ':', $raw )[0] );
				$fam     = str_replace( '+', ' ', trim( $fam, '"\',.()' ) );
				$weights = [];
				if ( preg_match( '/wght@([^&"\')\s]+)/', $raw, $wm ) ) {
					preg_match_all( '/\b([1-9]\d{2})\b/', $wm[1], $weight_matches );
					$weights = array_map( 'intval', $weight_matches[1] ?? [] );
				}
				if ( $fam && strlen( $fam ) > 1 ) {
					$this->fonts[ $fam ] = array_unique( array_merge( $this->fonts[ $fam ] ?? [], $weights ) );
				}
			}
		}
		// From font-family declarations in CSS.
		if ( preg_match_all( '/font-family\s*:\s*([^;}{]+);/', $this->raw_css, $fm ) ) {
			foreach ( $fm[1] as $family_raw ) {
				$family_raw = $this->resolve_font_token( trim( $family_raw ) );
				$families   = array_map( fn($f) => trim( $f, " \"'\t" ), explode( ',', $family_raw ) );
				foreach ( $families as $fam ) {
					if ( $fam && ! in_array( strtolower($fam), ['sans-serif','serif','monospace','inherit','initial','unset'], true ) ) {
						$this->fonts[ $fam ] = $this->fonts[ $fam ] ?? [];
					}
				}
			}
		}
	}

	/**
	 * Resolve font-family var() tokens using extracted custom properties.
	 *
	 * @param string $family_raw Raw font-family declaration value.
	 * @return string
	 */
	private function resolve_font_token( string $family_raw ): string {
		if ( ! preg_match( '/^var\(\s*(--[\w-]+)\s*(?:,\s*([^)]+))?\)$/', $family_raw, $match ) ) {
			return $family_raw;
		}

		$var_name = $match[1];
		$fallback = trim( $match[2] ?? '' );

		return $this->tokens[ $var_name ] ?? $fallback ?: $family_raw;
	}

	private function extract_colors(): void {
		$seen = [];
		// Hex.
		if ( preg_match_all( '/#([0-9a-fA-F]{3,8})\b/', $this->raw_css, $m ) ) {
			foreach ( $m[0] as $c ) {
				$lower = strtolower( $c );
				if ( ! isset( $seen[$lower] ) ) { $this->colors[] = $lower; $seen[$lower] = true; }
			}
		}
		// rgba / rgb.
		if ( preg_match_all( '/rgba?\([^)]+\)/', $this->raw_css, $m ) ) {
			foreach ( $m[0] as $c ) {
				if ( ! isset( $seen[$c] ) ) { $this->colors[] = $c; $seen[$c] = true; }
			}
		}
	}

	private function detect_animations( \DOMDocument $dom ): void {
		$xp = new \DOMXPath( $dom );

		// Elements with inline animation.
		$all = $xp->query( '//*[@style]' );
		if ( $all ) {
			foreach ( $all as $el ) {
				$style = $el->getAttribute( 'style' );
				if ( str_contains( $style, 'animation' ) || str_contains( $style, 'transition' ) ) {
					$this->flag_animated( $el, 'css_animation' );
				}
			}
		}

		// JS patterns in raw JS.
		$anim_patterns = [
			'requestAnimationFrame'  => 'raf',
			'setInterval'            => 'interval',
			'IntersectionObserver'   => 'intersection_observer',
			'canvas.getContext'      => 'canvas',
			'document.createElement(\'canvas\')' => 'canvas',
			'gsap.'                  => 'gsap',
			'TweenLite'              => 'gsap',
			'TimelineMax'            => 'gsap',
			'ScrollMagic'            => 'scroll_magic',
			'anime('                 => 'animejs',
			'Swiper'                 => 'swiper',
			'splide'                 => 'splide',
			'glide'                  => 'glide',
		];

		foreach ( $anim_patterns as $pattern => $type ) {
			if ( str_contains( $this->raw_js, $pattern ) ) {
				// Mark at document level.
				$this->animated_elements['document'][] = $type;
			}
		}

		// @keyframes in CSS.
		if ( preg_match_all( '/@keyframes\s+([\w-]+)/', $this->raw_css, $m ) ) {
			foreach ( $m[1] as $name ) {
				// Find elements that use this keyframe.
				$els = $xp->query( "//*[contains(@class,'{$name}')]" );
				if ( $els ) {
					foreach ( $els as $el ) $this->flag_animated( $el, 'keyframe:' . $name );
				}
			}
		}

		// Elements with class containing animation keywords.
		$anim_class_keywords = [ 'animate', 'animated', 'anim', 'reveal', 'fade', 'slide', 'counter', 'count', 'orbit', 'pulse', 'blink', 'marquee', 'scroll', 'parallax' ];
		foreach ( $anim_class_keywords as $kw ) {
			$els = $xp->query( "//*[contains(@class,'{$kw}')]" );
			if ( $els ) foreach ( $els as $el ) $this->flag_animated( $el, 'class_hint:' . $kw );
		}
	}

	private function detect_canvas_cursor( \DOMDocument $dom ): void {
		$xp = new \DOMXPath( $dom );

		// Canvas elements.
		$canvases = $xp->query( '//canvas' );
		if ( $canvases && $canvases->length > 0 ) $this->has_canvas = true;
		if ( str_contains( $this->raw_js, 'getContext' ) ) $this->has_canvas = true;
		if ( str_contains( $this->raw_js, "createElement('canvas')" ) ) $this->has_canvas = true;

		// Cursor elements.
		$cursors = $xp->query( '//*[contains(@id,"cursor") or contains(@class,"cursor")]' );
		if ( $cursors && $cursors->length > 0 ) $this->has_cursor = true;
		if ( str_contains( $this->raw_js, 'cursor-dot' ) || str_contains( $this->raw_css, 'cursor:none' ) ) {
			$this->has_cursor = true;
		}
	}

	private function detect_section_boundaries( \DOMDocument $dom ): void {
		$xp = new \DOMXPath( $dom );

		// Tier 1: semantic elements.
		$semantic = $xp->query( '//body/header | //body/section | //body/footer | //body/article | //body/main' );
		if ( $semantic && $semantic->length >= 2 ) {
			foreach ( $semantic as $n ) {
				$this->section_boundaries[] = $this->node_descriptor( $n );
			}
			return;
		}

		// Tier 2: main children.
		$main = $xp->query( '//main/section | //main/div[@id or @class]' );
		if ( $main && $main->length >= 2 ) {
			foreach ( $main as $n ) $this->section_boundaries[] = $this->node_descriptor( $n );
			return;
		}

		// Tier 3: body divs with attributes.
		$divs = $xp->query( '//body/div[@id or @class]' );
		if ( $divs ) foreach ( $divs as $n ) $this->section_boundaries[] = $this->node_descriptor( $n );
	}

	private function score_section_complexity( \DOMDocument $dom ): void {
		$xp = new \DOMXPath( $dom );
		foreach ( $this->section_boundaries as &$sec ) {
			$id  = $sec['id'] ?? '';
			$cls = $sec['class'] ?? '';

			// Find the node.
			$query = $id
				? "//*[@id='{$id}']"
				: "//*[contains(@class, '" . preg_replace('/\s+.*/', '', $cls) . "')]";

			$nodes = $xp->query( $query );
			if ( ! $nodes || $nodes->length === 0 ) continue;
			$node = $nodes->item(0);

			$score = 0;
			$inner_html = $node->ownerDocument->saveHTML( $node );

			// More CSS rules = more complex.
			$score += substr_count( $inner_html, 'class=' ) * 0.5;
			// Animations.
			if ( str_contains( $inner_html, 'animation' ) ) $score += 20;
			if ( str_contains( $inner_html, '@keyframes' ) ) $score += 15;
			// Canvas.
			if ( str_contains( $inner_html, 'canvas' ) ) $score += 30;
			// Grid spans.
			if ( str_contains( $inner_html, 'grid-row' ) || str_contains( $inner_html, 'grid-column' ) ) $score += 15;
			// Script tags.
			$scripts = $xp->query( './/script', $node );
			if ( $scripts ) $score += $scripts->length * 10;
			// DOM depth.
			$score += $this->node_depth( $node ) * 2;

			$sec['complexity'] = (int) $score;
		}
		unset( $sec );
	}

	// ── Helpers ───────────────────────────────────────────────

	private function flag_animated( \DOMElement $el, string $type ): void {
		$key = $el->getAttribute('id') ?: ('class:' . $el->getAttribute('class'));
		$this->animated_elements[ $key ][] = $type;
	}

	private function node_descriptor( \DOMElement $n ): array {
		return [
			'tag'        => strtolower( $n->nodeName ),
			'id'         => $n->getAttribute('id'),
			'class'      => $n->getAttribute('class'),
			'complexity' => 0,
		];
	}

	private function node_depth( \DOMElement $node ): int {
		$depth = 0;
		$parent = $node->parentNode;
		while ( $parent && $parent->nodeType === XML_ELEMENT_NODE ) {
			$depth++;
			$parent = $parent->parentNode;
		}
		return $depth;
	}

	/**
	 * Is this element animated according to Pass 1's inventory?
	 */
	public function element_is_animated( \DOMElement $el ): bool {
		$id_key  = $el->getAttribute('id');
		$cls_key = 'class:' . $el->getAttribute('class');

		if ( $id_key && isset( $this->animated_elements[ $id_key ] ) ) return true;
		if ( isset( $this->animated_elements[ $cls_key ] ) ) return true;

		// Check class names against animation keyword hints.
		$cls = strtolower( $el->getAttribute('class') );
		foreach ( [ 'animate','reveal','fade','count','orbit','pulse','blink','marquee','parallax','ticker','carousel','slider' ] as $kw ) {
			if ( str_contains( $cls, $kw ) ) return true;
		}

		// Check inline style.
		$style = $el->getAttribute('style');
		if ( str_contains( $style, 'animation' ) || str_contains( $style, '@keyframes' ) ) return true;

		// Check for canvas or script children.
		$xp = new \DOMXPath( $el->ownerDocument );
		$canvas = $xp->query( './/canvas', $el );
		$script = $xp->query( './/script', $el );
		if ( ( $canvas && $canvas->length > 0 ) || ( $script && $script->length > 0 ) ) return true;

		return false;
	}

	/**
	 * Is element a cursor overlay (should be SKIPped and recreated in Global Setup)?
	 */
	public function is_cursor_element( \DOMElement $el ): bool {
		$id  = strtolower( $el->getAttribute('id') );
		$cls = strtolower( $el->getAttribute('class') );
		return str_contains( $id, 'cursor' ) || str_contains( $cls, 'cursor' );
	}

	/**
	 * Is element a fixed-position background (canvas, bg-canvas, etc.)?
	 */
	public function is_background_element( \DOMElement $el ): bool {
		$id  = strtolower( $el->getAttribute('id') );
		$cls = strtolower( $el->getAttribute('class') );
		return str_contains( $id, 'canvas' ) || str_contains( $cls, 'bg-canvas' )
			|| $el->nodeName === 'canvas';
	}
}

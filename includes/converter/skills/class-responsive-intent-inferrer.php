<?php
/**
 * Skill: Responsive Intent Inferrer.
 *
 * Infers responsive breakpoint rules even when no @media queries exist in the
 * source CSS. Generates ready-to-use CSS @media blocks for the companion CSS.
 *
 * Rules differ per strategy:
 *  V1 — generates fewer rules (many styles live in HTML widget <style> blocks)
 *  V2 — generates comprehensive per-section responsive blocks
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Converter\Skills;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ResponsiveIntentInferrer
 */
class ResponsiveIntentInferrer {

	// ── Public API ───────────────────────────────────────────

	/**
	 * Infer responsive CSS rules for a set of sections.
	 *
	 * @param  array<int, array<string, mixed>> $sections  Section inventory from ConversionContext.
	 * @param  array<string, mixed>             $design    Design system (colors, fonts, spacing).
	 * @param  string                           $prefix    CSS class prefix.
	 * @param  string                           $strategy  'v1' | 'v2'.
	 * @return array<string, string> { tablet: CSS, mobile: CSS }
	 */
	public function inferBreakpoints(
		array $sections,
		array $design,
		string $prefix,
		string $strategy
	): array {
		$p = $prefix; // Shorthand.

		// Common rules that apply to all strategies.
		$tablet_rules = $this->baseTabletRules( $p );
		$mobile_rules = $this->baseMobileRules( $p );

		// V2 adds comprehensive per-section rules.
		if ( 'v2' === $strategy ) {
			$tablet_rules .= $this->v2TabletRules( $p, $sections );
			$mobile_rules .= $this->v2MobileRules( $p, $sections );
		}

		return [
			'tablet' => "@media (max-width: 1024px) {\n{$tablet_rules}\n}",
			'mobile' => "@media (max-width: 768px) {\n{$mobile_rules}\n}",
		];
	}

	// ── Tablet Rules (1024px) ────────────────────────────────

	/**
	 * Base tablet rules that apply regardless of strategy.
	 *
	 * @param  string $p CSS prefix.
	 * @return string CSS rules (no wrapping media query — caller wraps).
	 */
	private function baseTabletRules( string $p ): string {
		return <<<CSS
  /* Tablet: Hero headline scale */
  .{$p}-hero-headline .elementor-heading-title {
    font-size: clamp(48px, 8vw, 100px) !important;
    letter-spacing: -2px !important;
  }

  /* Tablet: Section descriptions centered → left */
  .{$p}-section-desc .elementor-widget-text-editor p {
    text-align: left;
  }

  /* Tablet: Bento grid collapse to 2 columns */
  .{$p}-bento-grid {
    grid-template-columns: 1fr 1fr !important;
  }

CSS;
	}

	/**
	 * V2-specific tablet rules (comprehensive per-section).
	 *
	 * @param  string                           $p        CSS prefix.
	 * @param  array<int, array<string, mixed>> $sections Section inventory.
	 * @return string
	 */
	private function v2TabletRules( string $p, array $sections ): string {
		return <<<CSS
  /* Tablet: Process grid stacks */
  .{$p}-process-grid {
    flex-direction: column !important;
  }

  /* Tablet: Hero bottom row stacks */
  .{$p}-hero-bottom {
    flex-direction: column !important;
  }

  /* Tablet: Testimonial grid stacks */
  .{$p}-testi-grid {
    flex-direction: column !important;
  }

CSS;
	}

	// ── Mobile Rules (768px) ─────────────────────────────────

	/**
	 * Base mobile rules that apply regardless of strategy.
	 *
	 * @param  string $p CSS prefix.
	 * @return string
	 */
	private function baseMobileRules( string $p ): string {
		return <<<CSS
  /* Mobile: Global horizontal padding floor */
  .{$p}-hero,
  .{$p}-features,
  .{$p}-process,
  .{$p}-testimonials,
  .{$p}-pricing,
  .{$p}-cta,
  .{$p}-footer {
    padding-left: 24px !important;
    padding-right: 24px !important;
  }

  /* Mobile: Bento → single column */
  .{$p}-bento-grid {
    grid-template-columns: 1fr !important;
  }
  .{$p}-bento-grid > * {
    grid-column: 1 / -1 !important;
    grid-row: auto !important;
  }

  /* Mobile: Stats → 2 columns */
  .{$p}-stats-grid {
    grid-template-columns: repeat(2, 1fr) !important;
  }

  /* Mobile: CTA padding reduction */
  .{$p}-cta {
    padding: 60px 32px !important;
    margin-left: 16px !important;
    margin-right: 16px !important;
  }
  .{$p}-cta::before {
    font-size: 100px !important;
  }

CSS;
	}

	/**
	 * V2-specific mobile rules (comprehensive).
	 *
	 * @param  string                           $p        CSS prefix.
	 * @param  array<int, array<string, mixed>> $sections Section inventory.
	 * @return string
	 */
	private function v2MobileRules( string $p, array $sections ): string {
		return <<<CSS
  /* Mobile: Testimonials → stack */
  .{$p}-testi-grid {
    flex-direction: column !important;
  }

  /* Mobile: Pricing → stack */
  .{$p}-pricing-grid {
    flex-direction: column !important;
  }

  /* Mobile: Footer → 2 columns */
  .{$p}-footer-grid {
    grid-template-columns: 1fr 1fr !important;
  }

  /* Mobile: Footer bottom → stack */
  .{$p}-footer-bottom-inner {
    flex-direction: column !important;
    gap: 12px !important;
    text-align: center;
  }

  /* Mobile: Hero headline */
  .{$p}-hero-headline .elementor-heading-title {
    font-size: clamp(40px, 10vw, 72px) !important;
    letter-spacing: -1px !important;
  }

  /* Mobile: Hero bottom → stack */
  .{$p}-hero-bottom {
    flex-direction: column !important;
  }

  /* Mobile: Process grid → stack */
  .{$p}-process-grid {
    flex-direction: column !important;
  }

CSS;
	}

	// ── Column-Count Intent ──────────────────────────────────

	/**
	 * Infer responsive column counts for a detected grid.
	 *
	 * @param  int    $desktop_columns Column count at desktop.
	 * @param  string $pattern_type    Section pattern type hint.
	 * @return array<string, int> { tablet: int, mobile: int }
	 */
	public function inferGridBreakpoints( int $desktop_columns, string $pattern_type ): array {
		// Special cases.
		if ( 'stats_row' === $pattern_type ) {
			return [ 'tablet' => max( 2, intval( $desktop_columns / 2 ) ), 'mobile' => 2 ];
		}
		if ( 'footer' === $pattern_type ) {
			return [ 'tablet' => 2, 'mobile' => 2 ];
		}

		// General rules.
		$tablet_cols = max( 1, (int) ceil( $desktop_columns / 2 ) );
		$tablet_cols = min( $tablet_cols, 2 ); // Max 2 on tablet.
		$mobile_cols = 1;

		return [ 'tablet' => $tablet_cols, 'mobile' => $mobile_cols ];
	}

	/**
	 * Determine if a flex-row container should stack to column on mobile.
	 *
	 * @param  \DOMElement $container The container element.
	 * @param  string      $pattern   Detected section pattern.
	 * @return bool True if it should stack.
	 */
	public function shouldStackOnMobile( \DOMElement $container, string $pattern ): bool {
		$cls = $container->getAttribute( 'class' );

		// Never stack: button groups, tag rows.
		if ( str_contains( $cls, 'action' ) || str_contains( $cls, 'btn-group' ) ) {
			return false;
		}

		// Always stack: major content rows.
		if ( str_contains( $cls, 'hero-bottom' ) || str_contains( $cls, 'process-grid' ) ) {
			return true;
		}

		// Default: stack side-by-side content.
		return in_array( $pattern, [ 'hero', 'process_steps', 'cta', 'footer' ], true );
	}
}

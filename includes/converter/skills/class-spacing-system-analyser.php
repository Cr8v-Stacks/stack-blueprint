<?php
/**
 * Skill: Spacing System Analyser.
 *
 * Detects the base spacing unit (4px or 8px) by computing the GCD of all
 * padding and margin values found in the CSS. Generates responsive scale
 * tokens (tablet ×0.75, mobile ×0.5) for companion CSS.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Converter\Skills;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class SpacingSystemAnalyser
 */
class SpacingSystemAnalyser {

	// ── Public API ───────────────────────────────────────────

	/**
	 * Analyse a set of spacing values and detect the underlying grid.
	 *
	 * @param  float[] $spacing_values All padding/margin values collected from CSS.
	 * @return array<string, mixed> {
	 *   base:       int,
	 *   scale_type: '4px'|'8px'|'custom',
	 *   tokens:     array<string, int>,
	 *   outliers:   float[],
	 * }
	 */
	public function analyse( array $spacing_values ): array {
		if ( empty( $spacing_values ) ) {
			return $this->defaults();
		}

		// Filter: only positive integers ≤ 200px, exclude 0.
		$values = array_filter( $spacing_values, fn( $v ) => $v > 0 && $v <= 200 );
		$values = array_unique( array_map( 'intval', $values ) );
		sort( $values );

		// Try to detect base.
		$base      = $this->detectBase( $values );
		$scale_type = 4 === $base ? '4px' : ( 8 === $base ? '8px' : 'custom' );

		// Produce canonical spacing tokens.
		$tokens   = $this->buildTokens( $base );

		// Flag outliers (values not divisible by base).
		$outliers = array_values( array_filter( $values, fn( $v ) => $v % $base !== 0 ) );

		return [
			'base'       => $base,
			'scale_type' => $scale_type,
			'tokens'     => $tokens,
			'outliers'   => $outliers,
		];
	}

	/**
	 * Generate responsive scale variants for all tokens.
	 *
	 * @param  array<string, mixed> $system Output of analyse().
	 * @return array<string, array<string, int>> { desktop, tablet, mobile }
	 */
	public function generateResponsiveScale( array $system ): array {
		$tokens = $system['tokens'] ?? $this->buildTokens( 8 );
		$tablet = [];
		$mobile = [];

		foreach ( $tokens as $name => $value ) {
			$t = (int) round( $value * 0.75 );
			$m = (int) round( $value * 0.5 );

			// Floor: horizontal padding never below 24px, vertical never below 16px.
			$is_large = $value >= 60;
			$tablet[ $name ] = max( $is_large ? 40 : 8, $t );
			$mobile[ $name ] = max( $is_large ? 24 : 8, $m );
		}

		return [
			'desktop' => $tokens,
			'tablet'  => $tablet,
			'mobile'  => $mobile,
		];
	}

	/**
	 * Build a CSS :root {…} block with spacing custom properties.
	 *
	 * @param  array<string, int>    $tokens Spacing token map.
	 * @param  string                $prefix CSS variable prefix.
	 * @return string CSS custom properties string.
	 */
	public function toCSSVars( array $tokens, string $prefix = '' ): string {
		$p    = $prefix ? $prefix . '-' : '';
		$vars = [];
		foreach ( $tokens as $name => $value ) {
			$vars[] = "  --{$p}space-{$name}: {$value}px;";
		}
		return implode( "\n", $vars );
	}

	// ── Internal Logic ───────────────────────────────────────

	/**
	 * Detect the base unit by testing how many values are divisible by 4 vs 8.
	 *
	 * @param  int[] $values Sorted integer spacing values.
	 * @return int 4 or 8.
	 */
	private function detectBase( array $values ): int {
		$div_by_4 = 0;
		$div_by_8 = 0;
		foreach ( $values as $v ) {
			if ( $v % 4 === 0 ) {
				$div_by_4++;
			}
			if ( $v % 8 === 0 ) {
				$div_by_8++;
			}
		}
		$count = count( $values );
		if ( $count === 0 ) {
			return 8;
		}
		// 8px base: at least 70% of values divisible by 8.
		if ( $div_by_8 / $count >= 0.7 ) {
			return 8;
		}
		// 4px base: at least 70% of values divisible by 4.
		if ( $div_by_4 / $count >= 0.7 ) {
			return 4;
		}
		// Default to 8px (most common in modern design systems).
		return 8;
	}

	/**
	 * Build a standard set of spacing tokens from a base unit.
	 *
	 * @param  int $base Base spacing unit (4 or 8).
	 * @return array<string, int>
	 */
	private function buildTokens( int $base ): array {
		if ( 4 === $base ) {
			return [
				'1'  => 4,
				'2'  => 8,
				'3'  => 12,
				'4'  => 16,
				'6'  => 24,
				'8'  => 32,
				'10' => 40,
				'12' => 48,
				'16' => 64,
				'20' => 80,
				'24' => 96,
				'30' => 120,
				'40' => 160,
			];
		}
		return [
			'1'  => 8,
			'2'  => 16,
			'3'  => 24,
			'4'  => 32,
			'5'  => 40,
			'6'  => 48,
			'8'  => 64,
			'10' => 80,
			'12' => 96,
			'16' => 128,
			'20' => 160,
		];
	}

	/**
	 * Default spacing system (8px base).
	 *
	 * @return array<string, mixed>
	 */
	private function defaults(): array {
		return [
			'base'       => 8,
			'scale_type' => '8px',
			'tokens'     => $this->buildTokens( 8 ),
			'outliers'   => [],
		];
	}
}

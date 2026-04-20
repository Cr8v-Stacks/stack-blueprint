<?php
/**
 * Skill: Color Harmony Classifier.
 *
 * Classifies all colors found in the source CSS into semantic design roles
 * using HSL luminance clustering and saturation analysis.
 *
 * Roles returned: background, surface, accent, text_primary, text_secondary,
 *                 text_muted, border, accent_secondary.
 *
 * Also detects whether the color scheme is dark or light, which affects
 * how hover states are generated (lighten vs darken).
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Converter\Skills;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ColorHarmonyClassifier
 */
class ColorHarmonyClassifier {

	// ── Public API ───────────────────────────────────────────

	/**
	 * Classify a set of color values into design roles.
	 *
	 * @param  string[] $colors Hex, rgb, or rgba color strings.
	 * @return array<string, mixed> {
	 *   background:       string,
	 *   surface:          string,
	 *   accent:           string,
	 *   text_primary:     string,
	 *   text_secondary:   string,
	 *   text_muted:       string,
	 *   border:           string,
	 *   accent_secondary: string,
	 *   scheme:           'dark'|'light',
	 * }
	 */
	public function classify( array $colors ): array {
		$hsl_colors = [];
		foreach ( $colors as $color ) {
			$hsl = $this->toHSL( $color );
			if ( null !== $hsl ) {
				$hsl_colors[] = [ 'original' => $color, 'hsl' => $hsl ];
			}
		}

		if ( empty( $hsl_colors ) ) {
			return $this->defaults();
		}

		// Sort by luminance ascending (darkest to lightest).
		usort( $hsl_colors, fn( $a, $b ) => $a['hsl']['l'] <=> $b['hsl']['l'] );

		$count = count( $hsl_colors );

		// Background = darkest color (or lightest for light modes).
		$background = $hsl_colors[0]['original'];
		$scheme     = $hsl_colors[0]['hsl']['l'] < 30 ? 'dark' : 'light';

		// Text primary = lightest (or darkest for light modes).
		$text_primary = $hsl_colors[ $count - 1 ]['original'];

		// Accent = most saturated color.
		$most_saturated = $hsl_colors[0];
		foreach ( $hsl_colors as $c ) {
			if ( $c['hsl']['s'] > $most_saturated['hsl']['s'] ) {
				$most_saturated = $c;
			}
		}
		$accent = $most_saturated['original'];

		// Surface = slightly offset from background (luminance +5 to +20 from bg).
		$surface          = $background;
		$bg_l             = $hsl_colors[0]['hsl']['l'];
		foreach ( $hsl_colors as $c ) {
			$diff = $c['hsl']['l'] - $bg_l;
			if ( $diff > 2 && $diff < 25 ) {
				$surface = $c['original'];
				break;
			}
		}

		// Text secondary = second lightest.
		$text_secondary = $count >= 2 ? $hsl_colors[ $count - 2 ]['original'] : $text_primary;

		// Text muted = typically a semi-transparent value (detected by rgba alpha).
		$text_muted = $this->findMutedText( $colors );
		if ( null === $text_muted ) {
			$text_muted = $count >= 3 ? $hsl_colors[ (int) round( $count * 0.6 ) ]['original'] : $text_secondary;
		}

		// Border = low-opacity color (typical rgba with 0.08–0.20 alpha).
		$border = $this->findBorderColor( $colors );
		if ( null === $border ) {
			$border = 'rgba(255,255,255,0.08)';
		}

		// Accent secondary = second most saturated, different hue from accent.
		$accent_secondary = $this->findSecondAccent( $hsl_colors, $most_saturated );

		return [
			'background'       => $background,
			'surface'          => $surface,
			'accent'           => $accent,
			'text_primary'     => $text_primary,
			'text_secondary'   => $text_secondary,
			'text_muted'       => $text_muted,
			'border'           => $border,
			'accent_secondary' => $accent_secondary,
			'scheme'           => $scheme,
		];
	}

	/**
	 * Detect if the primary scheme is dark or light based on background luminance.
	 *
	 * @param  array<string, mixed> $color_system Output of classify().
	 * @return string 'dark' | 'light'
	 */
	public function detectScheme( array $color_system ): string {
		return $color_system['scheme'] ?? 'dark';
	}

	// ── Color Conversion ─────────────────────────────────────

	/**
	 * Convert any CSS color to HSL.
	 *
	 * @param  string $color Hex, rgb(), or rgba() string.
	 * @return array<string, float>|null { h: float, s: float, l: float, a: float } or null on failure.
	 */
	public function toHSL( string $color ): ?array {
		$color = trim( $color );

		// Hex color.
		if ( preg_match( '/^#([0-9a-f]{3,8})$/i', $color, $m ) ) {
			$hex = $m[1];
			if ( strlen( $hex ) === 3 ) {
				$hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
			}
			$r = hexdec( substr( $hex, 0, 2 ) ) / 255;
			$g = hexdec( substr( $hex, 2, 2 ) ) / 255;
			$b = hexdec( substr( $hex, 4, 2 ) ) / 255;
			$a = strlen( $hex ) === 8 ? hexdec( substr( $hex, 6, 2 ) ) / 255 : 1.0;
			return $this->rgbToHSL( $r, $g, $b, $a );
		}

		// rgb() / rgba().
		if ( preg_match( '/rgba?\(\s*(\d+),\s*(\d+),\s*(\d+)(?:,\s*([\d.]+))?\s*\)/i', $color, $m ) ) {
			$r = (float) $m[1] / 255;
			$g = (float) $m[2] / 255;
			$b = (float) $m[3] / 255;
			$a = isset( $m[4] ) ? (float) $m[4] : 1.0;
			return $this->rgbToHSL( $r, $g, $b, $a );
		}

		return null;
	}

	/**
	 * Convert RGB (0–1 range) to HSL.
	 *
	 * @param  float $r Red 0–1.
	 * @param  float $g Green 0–1.
	 * @param  float $b Blue 0–1.
	 * @param  float $a Alpha 0–1.
	 * @return array<string, float>
	 */
	private function rgbToHSL( float $r, float $g, float $b, float $a = 1.0 ): array {
		$max  = max( $r, $g, $b );
		$min  = min( $r, $g, $b );
		$l    = ( $max + $min ) / 2;
		$s    = 0.0;
		$h    = 0.0;
		$diff = $max - $min;

		if ( $diff > 0.0 ) {
			$s = $l < 0.5 ? $diff / ( $max + $min ) : $diff / ( 2 - $max - $min );
			switch ( $max ) {
				case $r:
					$h = fmod( ( $g - $b ) / $diff + 6, 6 );
					break;
				case $g:
					$h = ( $b - $r ) / $diff + 2;
					break;
				case $b:
					$h = ( $r - $g ) / $diff + 4;
					break;
			}
			$h /= 6;
		}

		return [
			'h' => round( $h * 360, 1 ),
			's' => round( $s * 100, 1 ),
			'l' => round( $l * 100, 1 ),
			'a' => $a,
		];
	}

	// ── Specific Finders ─────────────────────────────────────

	/**
	 * Find a muted text color — typically rgba with alpha 0.4–0.65.
	 *
	 * @param  string[] $colors Raw color strings.
	 * @return string|null
	 */
	private function findMutedText( array $colors ): ?string {
		foreach ( $colors as $c ) {
			if ( preg_match( '/rgba\(\s*\d+,\s*\d+,\s*\d+,\s*(0\.[4-6]\d*)\s*\)/i', $c ) ) {
				return $c;
			}
		}
		return null;
	}

	/**
	 * Find a border color — typically rgba with alpha 0.06–0.20.
	 *
	 * @param  string[] $colors Raw color strings.
	 * @return string|null
	 */
	private function findBorderColor( array $colors ): ?string {
		foreach ( $colors as $c ) {
			if ( preg_match( '/rgba\(\s*\d+,\s*\d+,\s*\d+,\s*(0\.0[6-9]|0\.1\d*|0\.20?)\s*\)/i', $c ) ) {
				return $c;
			}
		}
		return null;
	}

	/**
	 * Find a second accent color with a meaningfully different hue.
	 *
	 * @param  array<int, array<string, mixed>> $hsl_colors Sorted color list with HSL data.
	 * @param  array<string, mixed>             $primary    Primary accent entry.
	 * @return string Color string.
	 */
	private function findSecondAccent( array $hsl_colors, array $primary ): string {
		$primary_hue = $primary['hsl']['h'];
		foreach ( $hsl_colors as $c ) {
			$hue_diff = abs( $c['hsl']['h'] - $primary_hue );
			if ( $hue_diff > 30 && $c['hsl']['s'] > 40 && $c['original'] !== $primary['original'] ) {
				return $c['original'];
			}
		}
		return $primary['original'];
	}

	/**
	 * Return safe default color roles when no colors can be parsed.
	 *
	 * @return array<string, string>
	 */
	private function defaults(): array {
		return [
			'background'       => '#0a0a0a',
			'surface'          => '#111111',
			'accent'           => '#7c3aed',
			'text_primary'     => '#ffffff',
			'text_secondary'   => 'rgba(255,255,255,0.7)',
			'text_muted'       => 'rgba(255,255,255,0.4)',
			'border'           => 'rgba(255,255,255,0.08)',
			'accent_secondary' => '#06b6d4',
			'scheme'           => 'dark',
		];
	}
}

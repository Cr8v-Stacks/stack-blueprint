<?php
/**
 * Skill: Typography Scale Detector.
 *
 * Collects all font-size values from the source CSS, detects the underlying
 * modular scale (if any), and assigns semantic hierarchy roles to each size.
 *
 * Hierarchy roles: display, heading_l, heading_m, heading_s, body_l, body_m,
 *                  body_s, label, mono.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Converter\Skills;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TypographyScaleDetector
 */
class TypographyScaleDetector {

	/**
	 * Known modular type scales to test against.
	 *
	 * @var array<string, float>
	 */
	private array $known_scales = [
		'minor-second'     => 1.067,
		'major-second'     => 1.125,
		'minor-third'      => 1.200,
		'major-third'      => 1.250,
		'perfect-fourth'   => 1.333,
		'augmented-fourth' => 1.414,
		'perfect-fifth'    => 1.500,
		'golden-ratio'     => 1.618,
	];

	// ── Public API ───────────────────────────────────────────

	/**
	 * Detect the typographic scale from a list of font sizes.
	 *
	 * @param  array<int, array<string, mixed>> $font_sizes List of { px: float, weight: int, context: string } entries.
	 * @return array<string, mixed> {
	 *   scale_name: string,
	 *   scale_ratio: float,
	 *   roles: array<float, string>,  // px => role name
	 *   base_px: float,
	 * }
	 */
	public function detect( array $font_sizes ): array {
		if ( empty( $font_sizes ) ) {
			return $this->defaults();
		}

		// Extract just the px values, deduplicate, sort ascending.
		$px_values = array_unique( array_column( $font_sizes, 'px' ) );
		sort( $px_values );

		// Try to find a consistent modular scale.
		$best_scale      = null;
		$best_scale_name = 'custom';
		$best_coverage   = 0;

		foreach ( $this->known_scales as $name => $ratio ) {
			$coverage = $this->testScale( $px_values, $ratio );
			if ( $coverage > $best_coverage && $coverage >= 0.6 ) {
				$best_coverage   = $coverage;
				$best_scale      = $ratio;
				$best_scale_name = $name;
			}
		}

		// Assign roles based on detected scale or heuristic size+weight.
		$roles   = [];
		$base_px = 16.0;

		// Try to find base size (closest to 16px in the middle of the scale).
		foreach ( $px_values as $px ) {
			if ( $px >= 14 && $px <= 18 ) {
				$base_px = $px;
				break;
			}
		}

		// Assign a role to each font size.
		$count = count( $px_values );
		foreach ( $px_values as $idx => $px ) {
			$role = $this->getRoleForSize( (float) $px, $font_sizes, $base_px, $idx, $count );
			$roles[ (float) $px ] = $role;
		}

		return [
			'scale_name'  => $best_scale_name,
			'scale_ratio' => $best_scale ?? 1.333,
			'roles'       => $roles,
			'base_px'     => $base_px,
		];
	}

	/**
	 * Get the semantic role for a given font size.
	 *
	 * @param  float                             $px         The font size in px.
	 * @param  array<int, array<string, mixed>>  $font_sizes Full font size list with weights.
	 * @param  float                             $base_px    Detected base size.
	 * @param  int                               $idx        Position in sorted list.
	 * @param  int                               $count      Total count.
	 * @return string Role name.
	 */
	public function getRoleForSize( float $px, array $font_sizes, float $base_px, int $idx, int $count ): string {
		// Find corresponding weight for this px value.
		$weight = 400;
		foreach ( $font_sizes as $fs ) {
			if ( abs( $fs['px'] - $px ) < 0.5 ) {
				$weight = (int) ( $fs['weight'] ?? 400 );
				break;
			}
		}

		// Display: very large + heavy weight; or largest in the set.
		if ( $px >= 64 && $weight >= 700 ) {
			return 'display';
		}
		if ( $idx === $count - 1 && $px >= 48 ) {
			return 'display';
		}

		// Headings.
		if ( $px >= 40 ) {
			return 'heading_l';
		}
		if ( $px >= 28 ) {
			return 'heading_m';
		}
		if ( $px >= 22 ) {
			return 'heading_s';
		}

		// Large body.
		if ( $px >= 18 ) {
			return 'body_l';
		}

		// Standard body.
		if ( $px >= 15 ) {
			return 'body_m';
		}

		// Small — could be label, mono, caption.
		if ( $px >= 12 ) {
			// If it's a monospace context or uppercase label.
			$context = '';
			foreach ( $font_sizes as $fs ) {
				if ( abs( $fs['px'] - $px ) < 0.5 ) {
					$context = $fs['context'] ?? '';
					break;
				}
			}
			if ( str_contains( $context, 'mono' ) || str_contains( $context, 'code' ) ) {
				return 'mono';
			}
			if ( str_contains( $context, 'uppercase' ) || str_contains( $context, 'label' ) || str_contains( $context, 'tag' ) ) {
				return 'label';
			}
			return 'body_s';
		}

		return 'label';
	}

	/**
	 * Get the display heading pixel size from the role map.
	 *
	 * @param  array<string, mixed> $scale_result Output of detect().
	 * @return float Pixel size, or 120.0 as default.
	 */
	public function displaySize( array $scale_result ): float {
		foreach ( $scale_result['roles'] as $px => $role ) {
			if ( 'display' === $role ) {
				return (float) $px;
			}
		}
		return 120.0;
	}

	// ── Scale Testing ────────────────────────────────────────

	/**
	 * Test how well a set of px values fits a given modular scale ratio.
	 *
	 * @param  float[] $px_values Sorted font sizes.
	 * @param  float   $ratio     Scale ratio to test.
	 * @return float Fraction of values that fit the scale (0–1).
	 */
	private function testScale( array $px_values, float $ratio ): float {
		$count   = count( $px_values );
		$matches = 0;

		for ( $i = 1; $i < $count; $i++ ) {
			$observed_ratio = $px_values[ $i ] / $px_values[ $i - 1 ];
			// Allow ±0.05 tolerance.
			if ( abs( $observed_ratio - $ratio ) <= 0.05 ) {
				$matches++;
			}
		}

		return $count > 1 ? $matches / ( $count - 1 ) : 0.0;
	}

	/**
	 * Default scale when no font sizes are available.
	 *
	 * @return array<string, mixed>
	 */
	private function defaults(): array {
		return [
			'scale_name'  => 'perfect-fourth',
			'scale_ratio' => 1.333,
			'roles'       => [
				12.0  => 'label',
				14.0  => 'body_s',
				16.0  => 'body_m',
				18.0  => 'body_l',
				24.0  => 'heading_s',
				32.0  => 'heading_m',
				48.0  => 'heading_l',
				80.0  => 'display',
			],
			'base_px'     => 16.0,
		];
	}
}

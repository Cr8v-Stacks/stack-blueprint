<?php
/**
 * Skill: Visual Fingerprint Engine.
 *
 * Identifies section-level design patterns using weighted signal evaluation.
 * Returns the best-matching pattern and its confidence score.
 *
 * Patterns: hero, bento_grid, stats_row, process_steps, testimonials,
 *           pricing, cta, footer, marquee, fixed_nav.
 *
 * Confidence threshold: 0.75 (global pipeline constant).
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Converter\Skills;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class VisualFingerprintEngine
 */
class VisualFingerprintEngine {

	private const MIN_CONFIDENCE = 0.75;

	// ── Pattern Signal Maps ──────────────────────────────────

	/**
	 * Pattern definitions. Each pattern has:
	 *  - required: [ signal => weight ] — heavy penalty if missing
	 *  - optional: [ signal => weight ] — bonus confidence if present
	 *  - exclusions: [ signal ] — immediate disqualification if present
	 *
	 * @var array<string, array<string, mixed>>
	 */
	private array $patterns = [

		'hero' => [
			'required' => [
				'has_heading'           => 0.85,
				'heading_is_h1_or_h2'   => 0.80,
				'is_first_section'      => 0.90,
			],
			'optional' => [
				'has_cta_button'        => 0.75,
				'has_subtitle'          => 0.70,
				'min_height_large'      => 0.80,
				'has_eyebrow_element'   => 0.70,
				'has_large_font'        => 0.80,
				'flex_column_layout'    => 0.50,
			],
			'exclusions' => [
				'has_price_amount',
				'has_grid_layout',
				'has_3plus_similar_cards',
			],
		],

		'bento_grid' => [
			'required' => [
				'has_grid_layout'       => 0.95,
				'has_4plus_children'    => 0.80,
			],
			'optional' => [
				'children_have_border'  => 0.70,
				'children_have_bg'      => 0.70,
				'children_mixed_sizes'  => 0.90,
				'children_have_heading' => 0.75,
				'children_have_body'    => 0.70,
			],
			'exclusions' => [
				'has_price_amount',
				'has_quote_marks',
			],
		],

		'stats_row' => [
			'required' => [
				'has_large_numbers'     => 0.90,
				'has_3plus_children'    => 0.80,
			],
			'optional' => [
				'numbers_have_labels'   => 0.80,
				'flex_row_layout'       => 0.70,
				'has_count_animation'   => 0.85,
			],
			'exclusions' => [
				'has_price_amount',
				'has_cta_button',
			],
		],

		'process_steps' => [
			'required' => [
				'has_numbered_items'    => 0.85,
				'has_step_titles'       => 0.80,
			],
			'optional' => [
				'has_step_descriptions' => 0.75,
				'has_visual_aside'      => 0.70,
				'step_count_3_to_5'     => 0.65,
				'has_section_heading'   => 0.60,
			],
			'exclusions' => [
				'has_price_amount',
				'has_quote_marks',
			],
		],

		'testimonials' => [
			'required' => [
				'has_quote_marks'       => 0.90,
				'has_person_name'       => 0.80,
			],
			'optional' => [
				'has_job_title'         => 0.75,
				'has_company_name'      => 0.70,
				'has_3plus_children'    => 0.80,
				'children_have_border'  => 0.60,
				'has_avatar_circle'     => 0.65,
			],
			'exclusions' => [
				'has_price_amount',
				'has_numbered_items',
			],
		],

		'pricing' => [
			'required' => [
				'has_price_amount'      => 0.95,
				'has_cta_button'        => 0.70,
			],
			'optional' => [
				'has_price_period'      => 0.85,
				'has_feature_list'      => 0.80,
				'has_2to4_children'     => 0.80,
				'one_child_distinct_bg' => 0.75,
				'has_plan_name'         => 0.75,
			],
			'exclusions' => [
				'has_quote_marks',
				'is_first_section',
			],
		],

		'cta' => [
			'required' => [
				'has_heading'           => 0.80,
				'has_cta_button'        => 0.85,
			],
			'optional' => [
				'has_subtitle'          => 0.70,
				'has_distinct_bg'       => 0.75,
				'is_near_page_end'      => 0.70,
				'has_2_buttons'         => 0.65,
			],
			'exclusions' => [
				'has_price_amount',
				'has_quote_marks',
				'is_first_section',
			],
		],

		'footer' => [
			'required' => [
				'is_last_section'       => 0.90,
				'has_multi_columns'     => 0.80,
			],
			'optional' => [
				'has_footer_links'      => 0.85,
				'has_copyright'         => 0.90,
				'has_logo'              => 0.70,
				'has_nav_link_lists'    => 0.75,
			],
			'exclusions' => [
				'has_price_amount',
				'has_quote_marks',
				'is_first_section',
			],
		],

		'marquee' => [
			'required' => [
				'has_marquee_animation' => 0.95,
				'has_repeating_items'   => 0.80,
			],
			'optional' => [
				'is_single_row'         => 0.70,
				'items_have_separators' => 0.60,
			],
			'exclusions' => [],
		],

		'fixed_nav' => [
			'required' => [
				'is_position_fixed'     => 0.95,
				'has_nav_links'         => 0.85,
			],
			'optional' => [
				'has_logo'              => 0.70,
				'has_cta_button'        => 0.65,
				'is_first_element'      => 0.80,
			],
			'exclusions' => [],
		],
	];

	// ── Public API ───────────────────────────────────────────

	/**
	 * Identify the design pattern of a section.
	 *
	 * @param  \DOMElement          $section The section DOM node.
	 * @param  \DOMXPath            $xp      XPath for traversal.
	 * @param  array<string, mixed> $context Context hints: is_first, is_last, section_index.
	 * @return array<string, mixed> { pattern, confidence, alternatives[] }
	 */
	public function identify( \DOMElement $section, \DOMXPath $xp, array $context = [] ): array {
		$signals = $this->extractSignals( $section, $xp, $context );
		$scores  = [];

		foreach ( $this->patterns as $name => $pattern ) {
			// Check exclusions first — immediate disqualification.
			$excluded = false;
			foreach ( $pattern['exclusions'] as $excl ) {
				if ( ! empty( $signals[ $excl ] ) ) {
					$excluded = true;
					break;
				}
			}
			if ( $excluded ) {
				continue;
			}

			// Calculate score from required + optional signals.
			$score     = 0.0;
			$max_score = 0.0;

			foreach ( $pattern['required'] as $signal => $weight ) {
				$max_score += $weight;
				if ( ! empty( $signals[ $signal ] ) ) {
					$score += $weight;
				} else {
					// Missing required signal — heavy penalty.
					$score -= $weight * 0.5;
				}
			}

			foreach ( $pattern['optional'] as $signal => $weight ) {
				$effective_weight = $weight * 0.5;
				$max_score       += $effective_weight;
				if ( ! empty( $signals[ $signal ] ) ) {
					$score += $effective_weight;
				}
			}

			$normalised = $max_score > 0 ? $score / $max_score : 0.0;

			if ( $normalised >= self::MIN_CONFIDENCE ) {
				$scores[ $name ] = round( $normalised, 3 );
			}
		}

		arsort( $scores );

		if ( empty( $scores ) ) {
			return [
				'pattern'      => 'unknown',
				'confidence'   => 0.0,
				'alternatives' => [],
			];
		}

		$top_pattern = (string) array_key_first( $scores );
		$alternatives = array_slice( $scores, 1, 3, true );

		return [
			'pattern'      => $top_pattern,
			'confidence'   => $scores[ $top_pattern ],
			'alternatives' => $alternatives,
		];
	}

	// ── Signal Extraction ────────────────────────────────────

	/**
	 * Extract boolean signals from a DOM section.
	 *
	 * @param  \DOMElement          $section Section node.
	 * @param  \DOMXPath            $xp      XPath instance.
	 * @param  array<string, mixed> $context Pipeline context hints.
	 * @return array<string, bool>
	 */
	private function extractSignals( \DOMElement $section, \DOMXPath $xp, array $context ): array {
		$s   = []; // signals
		$txt = $section->textContent;

		// Structural.
		$children     = $xp->query( './div | ./section | ./article', $section );
		$child_count  = $children ? $children->length : 0;
		$s['has_3plus_children']    = $child_count >= 3;
		$s['has_4plus_children']    = $child_count >= 4;
		$s['has_2to4_children']     = $child_count >= 2 && $child_count <= 4;
		$s['has_multi_columns']     = $child_count >= 2;

		// Position in page.
		$s['is_first_section']  = ! empty( $context['is_first'] );
		$s['is_last_section']   = ! empty( $context['is_last'] );
		$s['is_first_element']  = ! empty( $context['is_first'] );
		$s['is_near_page_end']  = ( $context['section_index'] ?? 0 ) >= ( $context['total_sections'] ?? 10 ) - 2;

		// Layout.
		$cls = $section->getAttribute( 'class' );
		$s['has_grid_layout']   = str_contains( $cls, 'grid' ) || null !== $xp->query( './/*[@class[contains(.,"grid")]]', $section )->item( 0 );
		$s['flex_row_layout']   = str_contains( $cls, 'row' ) || str_contains( $cls, 'flex' );
		$s['flex_column_layout']= str_contains( $cls, 'col' );

		// Position fixed (for nav).
		$s['is_position_fixed'] = $this->hasInlineOrClassStyle( $section, $xp, 'position:fixed' ) ||
		                          str_contains( $cls, 'fixed' ) || str_contains( $cls, 'nav' ) || 'header' === $section->nodeName;

		// Headings.
		$h1  = $xp->query( './/h1', $section );
		$h2  = $xp->query( './/h2', $section );
		$h3  = $xp->query( './/h3', $section );
		$s['has_heading']        = ( $h1->length + $h2->length + $h3->length ) > 0;
		$s['heading_is_h1_or_h2']= ( $h1->length + $h2->length ) > 0;

		// Detect large font (display heading signal).
		$s['has_large_font'] = (bool) $xp->query( './/*[contains(@class,"display") or contains(@class,"headline") or contains(@class,"hero-title")]', $section )->item( 0 );

		// Content.
		$s['has_subtitle']   = $xp->query( './/p', $section )->length > 0;
		$s['has_cta_button'] = $xp->query( './/a[contains(@class,"btn") or contains(@class,"cta") or contains(@class,"button")] | .//button', $section )->length > 0;
		$s['has_2_buttons']  = $xp->query( './/a[contains(@class,"btn") or contains(@class,"button")] | .//button', $section )->length >= 2;
		$s['has_nav_links']  = $xp->query( './/nav | .//*[contains(@class,"nav")]', $section )->length > 0;
		$s['has_logo']       = (bool) $xp->query( './/*[contains(@class,"logo")]', $section )->item( 0 );

		// Eyebrow / tag.
		$s['has_eyebrow_element'] = (bool) $xp->query(
			'.//*[contains(@class,"eyebrow") or contains(@class,"tag") or contains(@class,"badge") or contains(@class,"label") or contains(@class,"overline")]',
			$section
		)->item( 0 );

		// Numbers / stats.
		$s['has_large_numbers'] = (bool) preg_match( '/\d{1,3}[kK%+]?(?:\+)?/', $txt );
		$s['numbers_have_labels']= $s['has_large_numbers'] && $xp->query( './/p | .//span', $section )->length > 2;
		$s['has_count_animation']= str_contains( $txt, 'counter' ) || null !== $xp->query( './/*[contains(@class,"counter") or contains(@class,"stat")]', $section )->item( 0 );

		// Numbered items / process.
		$ordered_list = $xp->query( './/ol | .//*[contains(@class,"step") or contains(@class,"process")]', $section );
		$s['has_numbered_items']  = $ordered_list->length > 0;
		$s['has_step_titles']     = (bool) $xp->query( './/h3 | .//h4', $section )->item( 0 );
		$s['has_step_descriptions']= $xp->query( './/p', $section )->length >= 2;
		$s['has_visual_aside']    = (bool) $xp->query( './/*[contains(@class,"visual") or contains(@class,"orb") or contains(@class,"diagram")]', $section )->item( 0 );
		$s['step_count_3_to_5']   = $ordered_list->length >= 3 && $ordered_list->length <= 5;
		$s['has_section_heading'] = $s['has_heading'];

		// Quote / testimonials.
		$s['has_quote_marks']   = str_contains( $txt, '"' ) || str_contains( $txt, '"' ) || str_contains( $txt, '&ldquo;' );
		$s['has_person_name']   = (bool) $xp->query( './/*[contains(@class,"name") or contains(@class,"author")]', $section )->item( 0 );
		$s['has_job_title']     = (bool) $xp->query( './/*[contains(@class,"role") or contains(@class,"title") or contains(@class,"position")]', $section )->item( 0 );
		$s['has_company_name']  = (bool) $xp->query( './/*[contains(@class,"company")]', $section )->item( 0 );
		$s['has_avatar_circle'] = (bool) $xp->query( './/*[contains(@class,"avatar") or contains(@class,"initials")]', $section )->item( 0 );

		// Pricing.
		$s['has_price_amount']  = (bool) preg_match( '/[\$€£]\s*\d+|\d+\s*\/\s*(mo|month|yr|year)/i', $txt );
		$s['has_price_period']  = (bool) preg_match( '/per\s+month|per\s+year|\/mo|\/yr/i', $txt );
		$s['has_feature_list']  = $xp->query( './/ul | .//li', $section )->length > 3;
		$s['has_plan_name']     = (bool) preg_match( '/starter|pro|enterprise|basic|growth|scale|free/i', $txt );
		$s['one_child_distinct_bg'] = false; // Complex — check child background variation.

		// Footer.
		$s['has_footer_links']  = $xp->query( './/a', $section )->length > 4;
		$s['has_copyright']     = str_contains( $txt, '©' ) || str_contains( $txt, 'copyright' ) || str_contains( $txt, 'rights reserved' );
		$s['has_nav_link_lists']= $xp->query( './/ul[.//a] | .//nav', $section )->length > 1;

		// Marquee.
		$s['has_marquee_animation'] = (bool) $xp->query( './/*[contains(@class,"marquee") or contains(@class,"ticker") or contains(@class,"scroll-x")]', $section )->item( 0 ) ||
		                               str_contains( $cls, 'marquee' );
		$s['has_repeating_items']   = $child_count >= 4;
		$s['is_single_row']         = ! $s['has_grid_layout'] && $s['flex_row_layout'];
		$s['items_have_separators'] = (bool) $xp->query( './/*[contains(@class,"sep") or contains(@class,"dot") or contains(@class,"divider")]', $section )->item( 0 );

		// Dimensional / visual.
		$s['min_height_large']      = str_contains( $section->getAttribute( 'style' ), 'min-height' );
		$s['has_distinct_bg']       = str_contains( $cls, 'bg') || str_contains( $section->getAttribute( 'style' ), 'background' );

		// Children structural analysis.
		$child_borders = 0;
		$child_bgs     = 0;
		if ( $children && $children->length > 0 ) {
			foreach ( $children as $child ) {
				/** @var \DOMElement $child */
				$cc = $child->getAttribute( 'class' );
				if ( str_contains( $cc, 'card' ) || str_contains( $cc, 'border' ) ) {
					$child_borders++;
				}
				if ( str_contains( $cc, 'bg' ) || str_contains( $cc, 'surface' ) || str_contains( $cc, 'panel' ) ) {
					$child_bgs++;
				}
			}
		}
		$s['children_have_border']  = $child_borders >= 2;
		$s['children_have_bg']      = $child_bgs >= 2;
		$s['children_have_heading'] = $xp->query( './/h2 | .//h3 | .//h4', $section )->length >= 2;
		$s['children_have_body']    = $xp->query( './/p', $section )->length >= 2;
		$s['children_mixed_sizes']  = false; // Set by grid analysis if grid spans vary.

		// Card-level similarity check (for pricing/testimonials).
		$s['has_3plus_similar_cards'] = $this->hasSimilarChildren( $section, $xp, 3 );

		return $s;
	}

	/**
	 * Check if children of a section are structurally similar (same tag depth and class patterns).
	 *
	 * @param  \DOMElement $section Section node.
	 * @param  \DOMXPath   $xp      XPath.
	 * @param  int         $min     Minimum count of similar children required.
	 * @return bool
	 */
	private function hasSimilarChildren( \DOMElement $section, \DOMXPath $xp, int $min ): bool {
		$children = $xp->query( './div | ./article | ./li', $section );
		if ( ! $children || $children->length < $min ) {
			return false;
		}
		// Check that at least $min children share the same primary class.
		$class_groups = [];
		foreach ( $children as $child ) {
			$primary_class = explode( ' ', trim( $child->getAttribute( 'class' ) ) )[0] ?? '';
			if ( $primary_class ) {
				$class_groups[ $primary_class ] = ( $class_groups[ $primary_class ] ?? 0 ) + 1;
			}
		}
		foreach ( $class_groups as $count ) {
			if ( $count >= $min ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Check if an element has a CSS property in its inline style or a class-based style.
	 *
	 * @param  \DOMElement $el   Element.
	 * @param  \DOMXPath   $xp   XPath.
	 * @param  string      $prop Property string to search for, e.g. 'position:fixed'.
	 * @return bool
	 */
	private function hasInlineOrClassStyle( \DOMElement $el, \DOMXPath $xp, string $prop ): bool {
		$style = $el->getAttribute( 'style' );
		return str_contains( str_replace( ' ', '', $style ), str_replace( ' ', '', $prop ) );
	}
}

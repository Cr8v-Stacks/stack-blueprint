<?php
/**
 * Skill: Component Recogniser.
 *
 * Sub-section pattern recognition. Identifies micro-level structure within
 * sections: eyebrow+heading+desc combos, card structures, process steps,
 * stat cells, testimonial authors, pricing feature lists, and nav link lists.
 *
 * Used by Pass 3 to correctly map component structure to Elementor containers.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Converter\Skills;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ComponentRecogniser
 */
class ComponentRecogniser {

	// ── Component Type Constants ─────────────────────────────

	public const COMP_SECTION_HEADER   = 'section_header';   // eyebrow + title + desc
	public const COMP_CARD             = 'card';              // bordered container with content
	public const COMP_PROCESS_STEP     = 'process_step';      // number + title + desc
	public const COMP_STAT_CELL        = 'stat_cell';         // large number + small label
	public const COMP_TESTI_CARD       = 'testimonial_card';  // quote + author block
	public const COMP_AUTHOR_BLOCK     = 'author_block';      // avatar + name + role
	public const COMP_PRICING_CARD     = 'pricing_card';      // plan name + amount + feats + CTA
	public const COMP_PRICING_FEAT     = 'pricing_feature';   // icon + text feature item
	public const COMP_NAV_LINKS        = 'nav_links';         // horizontal or vertical link list
	public const COMP_BUTTON_GROUP     = 'button_group';      // 2+ adjacent CTAs
	public const COMP_FOOTER_COL       = 'footer_column';     // footer column with title + links
	public const COMP_UNKNOWN          = 'unknown';

	// ── Public API ───────────────────────────────────────────

	/**
	 * Recognise the component type of a DOM element.
	 *
	 * @param  \DOMElement $el Element to classify.
	 * @param  \DOMXPath   $xp XPath for traversal.
	 * @return array<string, mixed> { type: string, confidence: float, signals: string[] }
	 */
	public function recognise( \DOMElement $el, \DOMXPath $xp ): array {
		// Run checks in order of specificity (most specific first).
		$checks = [
			[ self::COMP_PRICING_CARD,   [ $this, 'isPricingCard' ] ],
			[ self::COMP_TESTI_CARD,     [ $this, 'isTestimonialCard' ] ],
			[ self::COMP_AUTHOR_BLOCK,   [ $this, 'isAuthorBlock' ] ],
			[ self::COMP_STAT_CELL,      [ $this, 'isStatCell' ] ],
			[ self::COMP_PRICING_FEAT,   [ $this, 'isPricingFeature' ] ],
			[ self::COMP_PROCESS_STEP,   [ $this, 'isProcessStep' ] ],
			[ self::COMP_SECTION_HEADER, [ $this, 'isSectionHeader' ] ],
			[ self::COMP_FOOTER_COL,     [ $this, 'isFooterColumn' ] ],
			[ self::COMP_NAV_LINKS,      [ $this, 'isNavLinks' ] ],
			[ self::COMP_BUTTON_GROUP,   [ $this, 'isButtonGroup' ] ],
			[ self::COMP_CARD,           [ $this, 'isCard' ] ],
		];

		foreach ( $checks as [ $type, $checker ] ) {
			$result = $checker( $el, $xp );
			if ( $result['match'] ) {
				return [
					'type'       => $type,
					'confidence' => $result['confidence'],
					'signals'    => $result['signals'],
				];
			}
		}

		return [
			'type'       => self::COMP_UNKNOWN,
			'confidence' => 0.0,
			'signals'    => [],
		];
	}

	// ── Component Detectors ──────────────────────────────────

	private function isSectionHeader( \DOMElement $el, \DOMXPath $xp ): array {
		$signals = [];
		$cls     = $el->getAttribute( 'class' );

		if ( str_contains( $cls, 'header' ) || str_contains( $cls, 'section-header' ) ) {
			$signals[] = 'class contains section-header';
		}
		$has_heading  = $xp->query( './/h2 | .//h3', $el )->length > 0;
		$has_para     = $xp->query( './/p', $el )->length > 0;
		$has_eyebrow  = (bool) $xp->query( './/*[contains(@class,"tag") or contains(@class,"eyebrow") or contains(@class,"label")]', $el )->item( 0 );

		if ( $has_heading ) {
			$signals[] = 'has heading';
		}
		if ( $has_para ) {
			$signals[] = 'has paragraph';
		}
		if ( $has_eyebrow ) {
			$signals[] = 'has eyebrow';
		}

		$score = 0;
		if ( $has_heading ) {
			$score += 0.4;
		}
		if ( $has_para ) {
			$score += 0.3;
		}
		if ( $has_eyebrow ) {
			$score += 0.3;
		}

		return [ 'match' => $score >= 0.7, 'confidence' => $score, 'signals' => $signals ];
	}

	private function isCard( \DOMElement $el, \DOMXPath $xp ): array {
		$signals = [];
		$cls     = $el->getAttribute( 'class' );

		$is_card_class = str_contains( $cls, 'card' ) || str_contains( $cls, 'panel' ) || str_contains( $cls, 'item' );
		$has_border    = str_contains( $el->getAttribute( 'style' ), 'border' ) || str_contains( $cls, 'border' );
		$has_heading   = $xp->query( './/h3 | .//h4 | .//h2', $el )->length > 0;
		$has_body      = $xp->query( './/p', $el )->length > 0;

		if ( $is_card_class ) {
			$signals[] = 'card class';
		}
		if ( $has_border ) {
			$signals[] = 'has border styling';
		}
		if ( $has_heading ) {
			$signals[] = 'has heading';
		}
		if ( $has_body ) {
			$signals[] = 'has body text';
		}

		$score = 0;
		if ( $is_card_class ) {
			$score += 0.4;
		}
		if ( $has_border ) {
			$score += 0.2;
		}
		if ( $has_heading ) {
			$score += 0.2;
		}
		if ( $has_body ) {
			$score += 0.2;
		}

		return [ 'match' => $score >= 0.6, 'confidence' => $score, 'signals' => $signals ];
	}

	private function isProcessStep( \DOMElement $el, \DOMXPath $xp ): array {
		$signals = [];
		$cls     = $el->getAttribute( 'class' );

		$is_step    = str_contains( $cls, 'step' ) || str_contains( $cls, 'process' );
		$has_number = (bool) preg_match( '/^\s*\d+\s*$/', $el->textContent ) ||
		              $xp->query( './/*[contains(@class,"num") or contains(@class,"step-num") or contains(@class,"number")]', $el )->length > 0;
		$has_title  = $xp->query( './/h3 | .//h4 | .//strong', $el )->length > 0;
		$has_desc   = $xp->query( './/p', $el )->length > 0;

		if ( $is_step ) {
			$signals[] = 'step/process class';
		}
		if ( $has_number ) {
			$signals[] = 'has step number';
		}
		if ( $has_title ) {
			$signals[] = 'has step title';
		}
		if ( $has_desc ) {
			$signals[] = 'has step description';
		}

		$score = 0;
		if ( $is_step ) {
			$score += 0.35;
		}
		if ( $has_number ) {
			$score += 0.35;
		}
		if ( $has_title ) {
			$score += 0.2;
		}
		if ( $has_desc ) {
			$score += 0.1;
		}

		return [ 'match' => $score >= 0.7, 'confidence' => $score, 'signals' => $signals ];
	}

	private function isStatCell( \DOMElement $el, \DOMXPath $xp ): array {
		$signals = [];
		$cls     = $el->getAttribute( 'class' );
		$txt     = $el->textContent;

		$is_stat_class = str_contains( $cls, 'stat' ) || str_contains( $cls, 'counter' );
		$has_number    = (bool) preg_match( '/\d{1,3}[kK%+]?/', $txt );
		$has_label     = $xp->query( './/p | .//span | .//small', $el )->length > 0;
		$child_count   = $xp->query( './*', $el )->length;

		if ( $is_stat_class ) {
			$signals[] = 'stat class';
		}
		if ( $has_number ) {
			$signals[] = 'has numeric value';
		}
		if ( $has_label ) {
			$signals[] = 'has label';
		}

		$score = 0;
		if ( $is_stat_class ) {
			$score += 0.5;
		}
		if ( $has_number ) {
			$score += 0.3;
		}
		if ( $has_label && $child_count <= 3 ) {
			$score += 0.2;
		}

		return [ 'match' => $score >= 0.75, 'confidence' => $score, 'signals' => $signals ];
	}

	private function isTestimonialCard( \DOMElement $el, \DOMXPath $xp ): array {
		$signals = [];
		$cls     = $el->getAttribute( 'class' );
		$txt     = $el->textContent;

		$is_testi   = str_contains( $cls, 'testi' ) || str_contains( $cls, 'review' ) || str_contains( $cls, 'quote' );
		$has_quote  = str_contains( $txt, '"' ) || str_contains( $txt, '"' );
		$has_author = (bool) $xp->query( './/*[contains(@class,"name") or contains(@class,"author")]', $el )->item( 0 );

		if ( $is_testi ) {
			$signals[] = 'testimonial class';
		}
		if ( $has_quote ) {
			$signals[] = 'has quote marks';
		}
		if ( $has_author ) {
			$signals[] = 'has author element';
		}

		$score = 0;
		if ( $is_testi ) {
			$score += 0.45;
		}
		if ( $has_quote ) {
			$score += 0.35;
		}
		if ( $has_author ) {
			$score += 0.2;
		}

		return [ 'match' => $score >= 0.75, 'confidence' => $score, 'signals' => $signals ];
	}

	private function isAuthorBlock( \DOMElement $el, \DOMXPath $xp ): array {
		$signals = [];
		$cls     = $el->getAttribute( 'class' );

		$is_author  = str_contains( $cls, 'author' ) || str_contains( $cls, 'meta' );
		$has_avatar = (bool) $xp->query( './/*[contains(@class,"avatar") or contains(@class,"initials")]', $el )->item( 0 );
		$has_name   = (bool) $xp->query( './/*[contains(@class,"name")]', $el )->item( 0 );
		$has_role   = (bool) $xp->query( './/*[contains(@class,"role") or contains(@class,"title")]', $el )->item( 0 );

		if ( $is_author ) {
			$signals[] = 'author class';
		}
		if ( $has_avatar ) {
			$signals[] = 'has avatar';
		}
		if ( $has_name ) {
			$signals[] = 'has name';
		}
		if ( $has_role ) {
			$signals[] = 'has role';
		}

		$score = 0;
		if ( $is_author ) {
			$score += 0.3;
		}
		if ( $has_avatar ) {
			$score += 0.3;
		}
		if ( $has_name ) {
			$score += 0.2;
		}
		if ( $has_role ) {
			$score += 0.2;
		}

		return [ 'match' => $score >= 0.75, 'confidence' => $score, 'signals' => $signals ];
	}

	private function isPricingCard( \DOMElement $el, \DOMXPath $xp ): array {
		$signals = [];
		$cls     = $el->getAttribute( 'class' );
		$txt     = $el->textContent;

		$is_plan    = str_contains( $cls, 'plan' ) || str_contains( $cls, 'price' ) || str_contains( $cls, 'tier' );
		$has_price  = (bool) preg_match( '/[\$€£]\s*\d+/', $txt );
		$has_feats  = $xp->query( './/ul | .//li', $el )->length > 2;
		$has_cta    = $xp->query( './/a[contains(@class,"btn")] | .//button', $el )->length > 0;

		if ( $is_plan ) {
			$signals[] = 'pricing plan class';
		}
		if ( $has_price ) {
			$signals[] = 'has currency amount';
		}
		if ( $has_feats ) {
			$signals[] = 'has feature list';
		}
		if ( $has_cta ) {
			$signals[] = 'has CTA button';
		}

		$score = 0;
		if ( $is_plan ) {
			$score += 0.25;
		}
		if ( $has_price ) {
			$score += 0.50;
		}
		if ( $has_feats ) {
			$score += 0.15;
		}
		if ( $has_cta ) {
			$score += 0.10;
		}

		return [ 'match' => $score >= 0.75, 'confidence' => $score, 'signals' => $signals ];
	}

	private function isPricingFeature( \DOMElement $el, \DOMXPath $xp ): array {
		$tag    = strtolower( $el->nodeName );
		$parent = $el->parentNode;
		$p_tag  = $parent instanceof \DOMElement ? strtolower( $parent->nodeName ) : '';
		$match  = 'li' === $tag && 'ul' === $p_tag;
		return [
			'match'      => $match,
			'confidence' => $match ? 0.85 : 0.0,
			'signals'    => $match ? [ 'li inside ul (pricing feature)' ] : [],
		];
	}

	private function isNavLinks( \DOMElement $el, \DOMXPath $xp ): array {
		$tag        = strtolower( $el->nodeName );
		$cls        = $el->getAttribute( 'class' );
		$link_count = $xp->query( './/a', $el )->length;
		$is_nav     = 'nav' === $tag || str_contains( $cls, 'nav' ) || str_contains( $cls, 'links' );
		$match      = $is_nav && $link_count >= 2;
		return [
			'match'      => $match,
			'confidence' => $match ? 0.85 : 0.0,
			'signals'    => $match ? [ 'nav element', "{$link_count} links" ] : [],
		];
	}

	private function isButtonGroup( \DOMElement $el, \DOMXPath $xp ): array {
		$cls        = $el->getAttribute( 'class' );
		$btn_count  = $xp->query( './/a[contains(@class,"btn")] | .//button', $el )->length;
		$is_actions = str_contains( $cls, 'action' ) || str_contains( $cls, 'btn' );
		$match      = $btn_count >= 2 || ( $is_actions && $btn_count >= 1 );
		return [
			'match'      => $match,
			'confidence' => $match ? 0.80 : 0.0,
			'signals'    => $match ? [ "{$btn_count} buttons" ] : [],
		];
	}

	private function isFooterColumn( \DOMElement $el, \DOMXPath $xp ): array {
		$cls       = $el->getAttribute( 'class' );
		$has_links = $xp->query( './/a', $el )->length >= 2;
		$has_title = $xp->query( './/h4 | .//h5 | .//strong | .//*[contains(@class,"title")]', $el )->length > 0;
		$match     = ( str_contains( $cls, 'col' ) || str_contains( $cls, 'footer' ) ) && $has_links;
		return [
			'match'      => $match,
			'confidence' => $match ? 0.80 : 0.0,
			'signals'    => array_filter( [ $has_title ? 'has column title' : null, $has_links ? 'has links' : null ] ),
		];
	}
}

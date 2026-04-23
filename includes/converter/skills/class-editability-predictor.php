<?php
/**
 * Skill: Editability Predictor.
 *
 * Scores every DOM element 0–10 for how likely a non-developer client is
 * to want to edit it after the site launches. The score maps to a widget type:
 *
 *  ≥7.0  → heading / text-editor / button    (native widget)
 *  4.0–6.9 → either  (V1: html widget preference; V2: native preference)
 *  ≤3.9  → html widget always
 *
 * V1 strategy applies a -2.0 modifier to all scores, biasing toward HTML widgets.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Converter\Skills;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class EditabilityPredictor
 */
class EditabilityPredictor {

	// ── Widget Type Constants ────────────────────────────────

	public const WIDGET_HEADING     = 'heading';
	public const WIDGET_TEXT_EDITOR = 'text-editor';
	public const WIDGET_BUTTON      = 'button';
	public const WIDGET_HTML        = 'html';

	/** Score above which native is preferred. */
	private const NATIVE_THRESHOLD = 7.0;

	/** Score below which HTML widget is always used. */
	private const HTML_THRESHOLD   = 4.0;

	/** V1 score penalty — biases toward HTML widgets. */
	private const V1_PENALTY = 2.0;

	// ── Public API ───────────────────────────────────────────

	/**
	 * Predict the best widget type for an element.
	 *
	 * @param  \DOMElement          $el       The element to classify.
	 * @param  \DOMXPath            $xp       XPath for child traversal.
	 * @param  array<string, mixed> $context  Pipeline context hints.
	 * @param  string               $strategy 'v1' | 'v2'.
	 * @return array<string, mixed> {
	 *   widget_type: string,
	 *   editability: float,
	 *   confidence:  float,
	 *   reasoning:   string[],
	 * }
	 */
	public function predict(
		\DOMElement $el,
		\DOMXPath $xp,
		array $context = [],
		string $strategy = 'v2'
	): array {
		$score    = 5.0; // Start neutral.
		$reasoning = [];

		// ── Strong UPWARD signals ──────────────────────────────

		$tag = strtolower( $el->nodeName );

		if ( in_array( $tag, [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ], true ) ) {
			$score     += 3.5;
			$reasoning[]= 'Heading tag (' . $tag . ') — high editability';
		}
		if ( 'p' === $tag && mb_strlen( trim( $el->textContent ) ) > 10 ) {
			$score     += 3.0;
			$reasoning[]= 'Paragraph — client body content';
		}
		if ( 'a' === $tag || 'button' === $tag ) {
			// Check if it's a Call-to-Action.
			$cls = $el->getAttribute( 'class' );
			if ( str_contains( $cls, 'btn' ) || str_contains( $cls, 'cta' ) || str_contains( $cls, 'button' ) ) {
				$score     += 3.5;
				$reasoning[]= 'CTA/Button — clients change button text often';
			} else {
				$score     += 1.5;
				$reasoning[]= 'Link — mild editability';
			}
		}
		if ( $this->isTestimonialQuote( $el ) ) {
			$score     += 3.5;
			$reasoning[]= 'Testimonial quote — clients update testimonials';
		}
		if ( $this->isPricingFeatureItem( $el ) ) {
			$score     += 3.0;
			$reasoning[]= 'Pricing feature item — clients adjust feature lists';
		}
		if ( $this->isPersonName( $el ) ) {
			$score     += 3.0;
			$reasoning[]= 'Person name — clients update team info';
		}
		if ( $this->isEyebrowTag( $el ) ) {
			$score     += 2.0;
			$reasoning[]= 'Eyebrow/tag text — section label, editable';
		}

		// ── Strong DOWNWARD signals ────────────────────────────

		if ( $this->hasAnimation( $el, $xp ) ) {
			$score     -= 4.0;
			$reasoning[]= 'Element has CSS/JS animation — HTML widget required';
		}
		if ( $this->isDecorativeSymbol( $el ) ) {
			$score     -= 4.0;
			$reasoning[]= 'Decorative symbol/icon — not client-editable';
		}
		if ( $this->isInitialsAvatar( $el ) ) {
			$score     -= 3.0;
			$reasoning[]= 'Initials avatar circle — developer-level element';
		}
		if ( 'canvas' === $tag ) {
			$score     = 0.0;
			$reasoning[]= 'Canvas element — always HTML widget';
		}
		if ( 'svg' === $tag ) {
			$score     -= 4.5;
			$reasoning[]= 'SVG illustration — not client-editable';
		}
		if ( $this->isTerminalOrCode( $el ) ) {
			$score     -= 3.0;
			$reasoning[]= 'Terminal/code block — developer element';
		}
		if ( $this->isOrbitalOrRing( $el ) ) {
			$score     -= 5.0;
			$reasoning[]= 'Orbital/ring animation — HTML widget only';
		}
		if ( $this->isMarqueeItem( $el ) ) {
			$score     -= 3.5;
			$reasoning[]= 'Marquee item — animation context, HTML widget';
		}
		if ( $this->isAbsolutelyPositioned( $el ) ) {
			$score     -= 2.0;
			$reasoning[]= 'Absolutely positioned element — companion CSS required';
		}
		if ( mb_strlen( trim( $el->textContent ) ) < 5 && 'p' !== $tag ) {
			$score     -= 2.0;
			$reasoning[]= 'Very short text (<5 chars) — likely icon or symbol';
		}

		// ── Context signals ────────────────────────────────────

		if ( ! empty( $context['parent_is_animated'] ) ) {
			$score     -= 2.0;
			$reasoning[]= 'Parent element is animated — reduce native preference';
		}
		if ( ! empty( $context['inside_card_grid'] ) && $this->isCardTitle( $el ) ) {
			$score     += 2.0;
			$reasoning[]= 'Card title in grid — high editability context';
		}
		if ( ( $context['sibling_repetitions'] ?? 0 ) > 3 ) {
			$score     += 1.5;
			$reasoning[]= 'Repeated sibling structure — pattern suggests editable list';
		}

		// ── V1 strategy penalty ────────────────────────────────
		if ( 'v1' === $strategy ) {
			$score     -= self::V1_PENALTY;
			$reasoning[]= 'V1 strategy: -2.0 bias toward HTML widget fidelity';
		}

		// Clamp to range.
		$score = max( 0.0, min( 10.0, $score ) );

		$widget_type = $this->scoreToWidgetType( $score, $el, $strategy );
		$confidence  = $this->calculateConfidence( $score, $strategy );

		return [
			'widget_type' => $widget_type,
			'editability' => round( $score, 2 ),
			'confidence'  => round( $confidence, 3 ),
			'reasoning'   => $reasoning,
		];
	}

	// ── Score → Widget Type ─────────────────────────────────

	/**
	 * Convert an editability score to a concrete widget type.
	 *
	 * @param  float       $score    Editability score 0–10.
	 * @param  \DOMElement $el       The element.
	 * @param  string      $strategy 'v1' | 'v2'.
	 * @return string Widget type constant.
	 */
	private function scoreToWidgetType( float $score, \DOMElement $el, string $strategy ): string {
		$tag = strtolower( $el->nodeName );

		// Canvas is always HTML widget regardless of score.
		if ( 'canvas' === $tag || 'svg' === $tag ) {
			return self::WIDGET_HTML;
		}

		if ( $score >= self::NATIVE_THRESHOLD ) {
			// Determine which native type.
			if ( in_array( $tag, [ 'h1', 'h2', 'h3', 'h4', 'h5', 'h6' ], true ) ) {
				return self::WIDGET_HEADING;
			}
			if ( in_array( $tag, [ 'a', 'button' ], true ) ) {
				return self::WIDGET_BUTTON;
			}
			return self::WIDGET_TEXT_EDITOR;
		}

		if ( $score < self::HTML_THRESHOLD ) {
			return self::WIDGET_HTML;
		}

		// Ambiguous range 4.0–6.9:
		return 'v1' === $strategy ? self::WIDGET_HTML : self::WIDGET_TEXT_EDITOR;
	}

	/**
	 * Calculate confidence based on how decisively the score exceeds thresholds.
	 *
	 * @param  float  $score    Editability score.
	 * @param  string $strategy 'v1' | 'v2'.
	 * @return float Confidence 0–1.
	 */
	private function calculateConfidence( float $score, string $strategy ): float {
		// High confidence on clear decisions (score far from ambiguous range).
		$distance_from_native = abs( $score - self::NATIVE_THRESHOLD );
		$distance_from_html   = abs( $score - self::HTML_THRESHOLD );
		$min_distance         = min( $distance_from_native, $distance_from_html );

		// Confidence scales from 0.5 (right at threshold) to 1.0 (10 points away).
		return min( 1.0, 0.5 + ( $min_distance / 10.0 ) );
	}

	// ── Signal Detectors ────────────────────────────────────

	private function hasAnimation( \DOMElement $el, \DOMXPath $xp ): bool {
		$style = $el->getAttribute( 'style' );
		$cls   = $el->getAttribute( 'class' );
		if ( str_contains( $style, 'animation' ) || str_contains( $style, '@keyframes' ) ) {
			return true;
		}
		if ( str_contains( $cls, 'animate' ) || str_contains( $cls, 'spin' ) || str_contains( $cls, 'pulse' ) ) {
			return true;
		}
		// Check for canvas children.
		$canvas_nodes = $xp->query( './/canvas | .//*[contains(@class,"canvas")]', $el );
		return (bool) ( $canvas_nodes && $canvas_nodes->length > 0 );
	}

	private function isDecorativeSymbol( \DOMElement $el ): bool {
		$txt  = trim( $el->textContent );
		$cls  = $el->getAttribute( 'class' );
		$tag  = strtolower( $el->nodeName );
		return ( 'i' === $tag || str_contains( $cls, 'icon' ) ) ||
		       ( mb_strlen( $txt ) === 1 && ! ctype_alnum( $txt ) );
	}

	private function isInitialsAvatar( \DOMElement $el ): bool {
		$cls = $el->getAttribute( 'class' );
		return str_contains( $cls, 'avatar' ) || str_contains( $cls, 'initials' );
	}

	private function isTerminalOrCode( \DOMElement $el ): bool {
		$cls = $el->getAttribute( 'class' );
		$tag = strtolower( $el->nodeName );
		return str_contains( $cls, 'terminal' ) || str_contains( $cls, 'code' ) ||
		       'code' === $tag || 'pre' === $tag;
	}

	private function isOrbitalOrRing( \DOMElement $el ): bool {
		$cls = $el->getAttribute( 'class' );
		return str_contains( $cls, 'orb' ) || str_contains( $cls, 'ring' ) ||
		       str_contains( $cls, 'orbit' ) || str_contains( $cls, 'pulse' );
	}

	private function isMarqueeItem( \DOMElement $el ): bool {
		$cls = $el->getAttribute( 'class' );
		$parent_cls = '';
		if ( $el->parentNode instanceof \DOMElement ) {
			$parent_cls = $el->parentNode->getAttribute( 'class' );
		}
		return str_contains( $cls, 'marquee' ) || str_contains( $parent_cls, 'marquee' );
	}

	private function isAbsolutelyPositioned( \DOMElement $el ): bool {
		$style = $el->getAttribute( 'style' );
		return str_contains( str_replace( ' ', '', $style ), 'position:absolute' );
	}

	private function isTestimonialQuote( \DOMElement $el ): bool {
		$cls = $el->getAttribute( 'class' );
		$txt = $el->textContent;
		return str_contains( $cls, 'quote' ) ||
		       ( 'blockquote' === strtolower( $el->nodeName ) ) ||
		       ( str_contains( $txt, '"' ) && mb_strlen( $txt ) > 20 );
	}

	private function isPricingFeatureItem( \DOMElement $el ): bool {
		$tag    = strtolower( $el->nodeName );
		$parent = $el->parentNode;
		$p_tag  = $parent instanceof \DOMElement ? strtolower( $parent->nodeName ) : '';
		return 'li' === $tag && 'ul' === $p_tag;
	}

	private function isPersonName( \DOMElement $el ): bool {
		$cls = $el->getAttribute( 'class' );
		return str_contains( $cls, 'name' ) || str_contains( $cls, 'author' );
	}

	private function isEyebrowTag( \DOMElement $el ): bool {
		$cls = $el->getAttribute( 'class' );
		return str_contains( $cls, 'tag' ) || str_contains( $cls, 'eyebrow' ) ||
		       str_contains( $cls, 'overline' ) || str_contains( $cls, 'label' );
	}

	private function isCardTitle( \DOMElement $el ): bool {
		$tag = strtolower( $el->nodeName );
		$cls = $el->getAttribute( 'class' );
		return in_array( $tag, [ 'h3', 'h4' ], true ) || str_contains( $cls, 'card-title' );
	}
}

<?php
/**
 * V2 Decision Helper.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Converter\Helpers;

use StackBlueprint\Converter\HtmlParser;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class V2DecisionHelper {

	/**
	 * Build primitive affinity signals for V2 native-first decisions.
	 *
	 * @return array{score:int,signals:array<string,int>}
	 */
	public static function collect_affinity_signals( HtmlParser $parser, \DOMElement $node, \DOMXPath $xp ): array {
		$html = $node->ownerDocument ? (string) $node->ownerDocument->saveHTML( $node ) : '';
		$affinity = $parser->primitive_native_affinity( $html );
		$signals = (array) ( $affinity['signals'] ?? [] );
		$signals['interactive'] = (int) $xp->query( './/*[self::details or self::summary or @onclick or @onchange]', $node )->length;
		$signals['containers'] = (int) $xp->query( './/*[self::div or self::section or self::article]', $node )->length;
		$score = (int) ( $affinity['score'] ?? 0 );

		if ( $signals['containers'] >= 2 && ( (int) ( $signals['headings'] ?? 0 ) + (int) ( $signals['text'] ?? 0 ) ) >= 2 ) {
			$score = min( 12, $score + 2 );
		}

		return [ 'score' => $score, 'signals' => $signals ];
	}

	/**
	 * Decide whether V2 should force native rendering.
	 */
	public static function should_prefer_native( string $type, array $affinity, array $complexity ): bool {
		$score = (int) ( $affinity['score'] ?? 0 );
		$signals = (array) ( $affinity['signals'] ?? [] );
		$has_behavior_lock = ( (int) ( $signals['scripts'] ?? 0 ) > 0 ) || ( (int) ( $signals['forms'] ?? 0 ) > 0 );
		$complexity_score = (int) ( $complexity['score'] ?? 0 );
		$native_favored_types = [ 'hero', 'features', 'pricing', 'footer', 'cta', 'generic', 'process', 'testimonials' ];

		if ( in_array( $type, $native_favored_types, true ) && $score >= 3 ) {
			return true;
		}
		if ( $complexity_score <= 8 && $score >= 4 && ! $has_behavior_lock ) {
			return true;
		}

		return false;
	}
}


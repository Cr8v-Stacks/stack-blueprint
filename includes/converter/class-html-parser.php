<?php
/**
 * HTML Parser Utility.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Converter;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class HtmlParser
 *
 * Future: pre-analyse HTML prototype before sending to AI.
 * Will detect sections, extract design tokens, identify animation patterns,
 * and produce a structured analysis to improve conversion accuracy.
 */
class HtmlParser {

	/**
	 * Extract all CSS custom property declarations from HTML.
	 *
	 * @param string $html HTML content.
	 * @return array Associative array of variable name => value.
	 */
	public function extract_css_variables( string $html ): array {
		$vars = [];
		if ( preg_match_all( '/--([\w-]+)\s*:\s*([^;]+);/', $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$vars[ '--' . trim( $match[1] ) ] = trim( $match[2] );
			}
		}
		return $vars;
	}

	/**
	 * Detect Google Fonts used in the prototype.
	 *
	 * @param string $html HTML content.
	 * @return array List of font family names.
	 */
	public function detect_fonts( string $html ): array {
		$fonts = [];
		if ( preg_match_all( '/fonts\.googleapis\.com\/css2\?family=([^"\'&]+)/', $html, $matches ) ) {
			foreach ( $matches[1] as $raw ) {
				$family  = urldecode( explode( ':', $raw )[0] );
				$fonts[] = str_replace( '+', ' ', $family );
			}
		}
		return array_unique( $fonts );
	}

	/**
	 * Detect whether the prototype has a particle canvas animation.
	 *
	 * @param string $html HTML content.
	 * @return bool
	 */
	public function has_particle_canvas( string $html ): bool {
		return str_contains( $html, 'canvas' ) && str_contains( $html, 'particle' );
	}

	/**
	 * Primitive-native score for V2 "container + native widgets first".
	 *
	 * @param string $html HTML fragment.
	 * @return array{score:int,signals:array<string,int>}
	 */
	public function primitive_native_affinity( string $html ): array {
		$signals = [
			'headings' => preg_match_all( '/<h[1-6][\s>]/i', $html ) ?: 0,
			'text'     => preg_match_all( '/<p[\s>]/i', $html ) ?: 0,
			'buttons'  => preg_match_all( '/<(button|a)[\s>]/i', $html ) ?: 0,
			'images'   => preg_match_all( '/<(img|svg)[\s>]/i', $html ) ?: 0,
			'lists'    => preg_match_all( '/<(ul|ol|li)[\s>]/i', $html ) ?: 0,
			'tables'   => preg_match_all( '/<(table|tr|td|th)[\s>]/i', $html ) ?: 0,
			'forms'    => preg_match_all( '/<(form|input|textarea|select)[\s>]/i', $html ) ?: 0,
			'scripts'  => preg_match_all( '/<script[\s>]/i', $html ) ?: 0,
		];
		$score = min( 12, $signals['headings'] + $signals['text'] + $signals['buttons'] + $signals['images'] + $signals['lists'] + ( 2 * $signals['tables'] ) );
		return [ 'score' => $score, 'signals' => $signals ];
	}
}

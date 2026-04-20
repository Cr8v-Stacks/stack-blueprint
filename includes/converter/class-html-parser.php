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
}

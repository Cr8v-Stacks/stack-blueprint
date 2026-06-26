<?php
/**
 * HTML Analyzer and Pre-processor.
 *
 * Scans HTML for prefix detection, token optimization, and extraction before sending to AI.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Utilities;

class HtmlAnalyzer {

	/**
	 * Extracts the most statistically significant CSS class prefix from the document.
	 *
	 * @param string $html The raw HTML.
	 * @return string The detected prefix (e.g., 'nx-', 'card-') or 'sb-' as fallback.
	 */
	public static function detect_prefix( $html ) {
		// Extract all class="" attributes
		preg_match_all( '/class=["\']([^"\']+)["\']/i', $html, $matches );
		if ( empty( $matches[1] ) ) {
			return 'sb-'; // Fallback
		}

		$all_classes = [];
		foreach ( $matches[1] as $class_string ) {
			$classes = explode( ' ', $class_string );
			foreach ( $classes as $class ) {
				$class = trim( $class );
				if ( ! empty( $class ) ) {
					$all_classes[] = $class;
				}
			}
		}

		$prefixes = [];
		foreach ( $all_classes as $class ) {
			// Find prefix ending with hyphen (e.g. "nx-button" -> "nx-")
			if ( strpos( $class, '-' ) !== false ) {
				$parts = explode( '-', $class );
				$prefix = $parts[0] . '-';
				
				// Ignore common non-semantic prefixes
				if ( in_array( $prefix, [ 'text-', 'bg-', 'p-', 'm-', 'flex-', 'grid-', 'col-', 'row-', 'w-', 'h-' ] ) ) {
					continue;
				}

				if ( ! isset( $prefixes[ $prefix ] ) ) {
					$prefixes[ $prefix ] = 0;
				}
				$prefixes[ $prefix ]++;
			}
		}

		if ( empty( $prefixes ) ) {
			return 'sb-';
		}

		// Sort by frequency
		arsort( $prefixes );
		$top_prefix = array_key_first( $prefixes );
		
		// If the most common prefix appears at least 3 times, trust it.
		if ( $prefixes[ $top_prefix ] >= 3 ) {
			return $top_prefix;
		}

		return 'sb-';
	}

	private static array $svg_map = [];

	/**
	 * Minifies HTML to save API tokens.
	 * Removes comments, replaces huge SVGs, strips base64 images.
	 *
	 * @param string $html
	 * @return string
	 */
	public static function minify_for_ai( $html ) {
		self::$svg_map = [];
		
		// Remove HTML comments
		$html = preg_replace( '/<!--(.*?)-->/is', '', $html );

		// Replace large SVG contents with a placeholder token to save massive amounts of context window
		$html = preg_replace_callback( '/<svg[^>]*>.*?<\/svg>/is', function($matches) {
			$token = 'SVG_TOKEN_' . md5($matches[0]);
			self::$svg_map[$token] = $matches[0];
			return '<svg data-token="' . $token . '"></svg>';
		}, $html );

		// Strip massive base64 images
		$html = preg_replace( '/src=["\']data:image\/[^;]+;base64,[^"\']+["\']/is', 'src="data:image-placeholder"', $html );

		// Remove scripts that are definitely analytics/tracking, keep only custom UI scripts
		$html = preg_replace( '/<script[^>]*src=["\'].*(google-analytics|gtm|facebook|hotjar).*["\'][^>]*>.*?<\/script>/is', '', $html );

		// Collapse multiple spaces/newlines
		$html = preg_replace( '/\s+/', ' ', $html );
		$html = str_replace( '> <', '><', $html );

		return trim( $html );
	}

	/**
	 * Pre-process the HTML file into a clean payload for the LLM.
	 *
	 * @param string $html The raw HTML string.
	 * @return array {
	 *    @type string $html   The minified HTML.
	 *    @type string $prefix The detected prefix.
	 *    @type array  $svgs   Map of SVG tokens to original SVG code.
	 * }
	 */
	public static function process( $html ) {
		$prefix   = self::detect_prefix( $html );
		$minified = self::minify_for_ai( $html );

		return [
			'html'   => $minified,
			'prefix' => $prefix,
			'svgs'   => self::$svg_map
		];
	}
}

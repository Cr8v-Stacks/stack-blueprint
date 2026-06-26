<?php
/**
 * Conversion Manager.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Converter;

use WP_Error;
use StackBlueprint\Utilities\ApiManager;
use StackBlueprint\Utilities\FileHandler;
use StackBlueprint\Utilities\HtmlAnalyzer;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ConversionManager
 *
 * Orchestrates the full conversion workflow: file validation,
 * content merging, AI API call, result parsing, and DB storage.
 */
class ConversionManager {

	/**
	 * @var FileHandler
	 */
	private FileHandler $file_handler;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->file_handler = new FileHandler();
	}

	/**
	 * Start a new conversion job.
	 *
	 * @param array      $html_file     $_FILES entry for the HTML file.
	 * @param array|null $css_file      Optional $_FILES entry for CSS file.
	 * @param array|null $js_file       Optional $_FILES entry for JS file.
	 * @param array      $params        Conversion parameters.
	 * @return array|WP_Error
	 */
	public function start_conversion(
		array $html_file,
		?array $css_file,
		?array $js_file,
		array $params
	): array|WP_Error {
		// Validate and read files.
		$html_content = $this->file_handler->read_uploaded_file( $html_file, 'html' );
		if ( is_wp_error( $html_content ) ) {
			return $html_content;
		}

		// Merge companion CSS and JS if provided.
		$merged = $this->merge_prototype( $html_content, $css_file, $js_file );

		// Run HtmlAnalyzer to detect prefix, minify, and map SVGs
		$processed = HtmlAnalyzer::process( $merged );
		$merged = $processed['html'];
		$svgs   = $processed['svgs'] ?? [];

		// If user didn't explicitly set a prefix, use the detected one
		if ( empty( $params['prefix'] ) || $params['prefix'] === 'sb' ) {
			$params['prefix'] = rtrim($processed['prefix'], '-'); // ApiManager adds the hyphen
		}

		// Log conversion start.
		$conversion_id = $this->log_conversion( $params, $merged );
		if ( ! $conversion_id ) {
			return new WP_Error( 'db_error', __( 'Failed to create conversion record.', 'stack-blueprint' ) );
		}

		$result = $this->run_conversion( $merged, $params, $conversion_id, $svgs );

		if ( is_wp_error( $result ) ) {
			$this->update_conversion_status( $conversion_id, 'failed', $result->get_error_message() );
			return $result;
		}

		return [
			'conversion_id' => $conversion_id,
			'status'        => 'complete',
			'project_name'  => $params['project_name'],
			'strategy'      => $params['strategy'],
			'prefix'        => $params['prefix'],
			'provider'      => $params['provider'] ?? 'anthropic',
			'class_map'     => $result['class_map'] ?? [],
			'warnings'      => $result['warnings'] ?? [],
			'diagnostics'   => $result['diagnostics'] ?? [],
		];
	}

	/**
	 * Merge HTML, CSS, and JS into a single prototype string.
	 */
	private function merge_prototype( string $html, ?array $css_file, ?array $js_file ): string {
		$extra_css = '';
		$extra_js  = '';

		if ( $css_file && ! empty( $css_file['tmp_name'] ) ) {
			$css_content = $this->file_handler->read_uploaded_file( $css_file, 'css' );
			if ( ! is_wp_error( $css_content ) ) {
				$extra_css = "\n<style>\n" . $css_content . "\n</style>\n";
			}
		}

		if ( $js_file && ! empty( $js_file['tmp_name'] ) ) {
			$js_content = $this->file_handler->read_uploaded_file( $js_file, 'js' );
			if ( ! is_wp_error( $js_content ) ) {
				$extra_js = "\n<script>\n" . $js_content . "\n</script>\n";
			}
		}

		if ( str_contains( $html, '</body>' ) ) {
			$html = str_replace( '</body>', $extra_css . $extra_js . '</body>', $html );
		} else {
			$html .= $extra_css . $extra_js;
		}

		return $html;
	}

	/**
	 * Execute the conversion via the AI API Manager.
	 */
	private function run_conversion( string $content, array $params, int $conversion_id, array $svgs = [] ): array|WP_Error {
		try {
			$provider_name = sanitize_key( $params['provider'] ?? 'anthropic' );
			$strategy      = sanitize_key( $params['strategy'] ?? 'v2' );

			$api_manager = new ApiManager( $provider_name );
			$parsed      = $api_manager->convert( $content, $params, $strategy );

			if ( is_wp_error( $parsed ) ) {
				return $parsed;
			}

			// Restore SVGs if any tokens were used
			if ( ! empty( $svgs ) && ! empty( $parsed['json_template'] ) ) {
				$json_string = wp_json_encode( $parsed['json_template'] );
				
				// Build a replacement array: '<svg data-token="SVG_TOKEN_XXX"></svg>' => '<svg>...</svg>'
				$replacements = [];
				foreach ( $svgs as $token => $svg_code ) {
					// The AI might output the placeholder exact or unescaped depending on JSON encoding
					$placeholder = '<svg data-token="' . $token . '"></svg>';
					$replacements[ $placeholder ] = $svg_code;
					
					// Also replace just the token in case the AI messed up the tags
					$replacements[ $token ] = $svg_code;
				}
				
				$restored_string = strtr( $json_string, $replacements );
				$restored_json   = json_decode( $restored_string, true );
				
				if ( json_last_error() === JSON_ERROR_NONE && is_array( $restored_json ) ) {
					$parsed['json_template'] = $restored_json;
				}
			}

			$this->store_conversion_result( $conversion_id, $parsed );
			return $parsed;
		} catch ( \Throwable $e ) {
			return new WP_Error(
				'conversion_runtime_error',
				sprintf(
					/* translators: %s: runtime error message */
					__( 'Conversion runtime failure: %s', 'stack-blueprint' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Log a new conversion to the database.
	 */
	private function log_conversion( array $params, string $content ): int {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . 'sb_conversions',
			[
				'project_name' => sanitize_text_field( $params['project_name'] ?? 'untitled' ),
				'strategy'     => sanitize_key( $params['strategy'] ?? 'v2' ),
				'prefix'       => sanitize_key( $params['prefix'] ?? 'sb' ),
				'status'       => 'processing',
				'input_hash'   => md5( $content ),
				'file_size'    => strlen( $content ),
				'created_at'   => current_time( 'mysql' ),
			],
			[ '%s', '%s', '%s', '%s', '%s', '%d', '%s' ]
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * Store the result of a successful conversion.
	 */
	private function store_conversion_result( int $id, array $result ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'sb_conversions',
			[
				'status'       => 'complete',
				'completed_at' => current_time( 'mysql' ),
			],
			[ 'id' => $id ],
			[ '%s', '%s' ],
			[ '%d' ]
		);

		update_option( 'sb_result_json_' . $id, $result['json_output'] ?? '', false );
		update_option( 'sb_result_css_' . $id, $result['css_output'] ?? '', false );
		update_option( 'sb_result_meta_' . $id, [
			'class_map'   => $result['class_map'] ?? [],
			'warnings'    => $result['warnings'] ?? [],
			'diagnostics' => $result['diagnostics'] ?? [],
		], false );
	}

	/**
	 * Update conversion status (e.g., on failure).
	 */
	private function update_conversion_status( int $id, string $status, string $error = '' ): void {
		global $wpdb;

		$wpdb->update(
			$wpdb->prefix . 'sb_conversions',
			[
				'status'        => $status,
				'completed_at'  => current_time( 'mysql' ),
				'error_message' => $error,
			],
			[ 'id' => $id ],
			[ '%s', '%s', '%s' ],
			[ '%d' ]
		);
	}

	/**
	 * Get a single conversion record with its outputs.
	 */
	public function get_conversion( int $id ): array|WP_Error {
		global $wpdb;

		$record = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}sb_conversions WHERE id = %d",
			$id
		), ARRAY_A );

		if ( ! $record ) {
			return new WP_Error( 'not_found', __( 'Conversion not found.', 'stack-blueprint' ) );
		}

		$record['json_output'] = get_option( 'sb_result_json_' . $id, '' );
		$record['css_output']  = get_option( 'sb_result_css_' . $id, '' );
		$meta                  = get_option( 'sb_result_meta_' . $id, [] );

		$record['class_map']   = $meta['class_map'] ?? [];
		$record['warnings']    = $meta['warnings'] ?? [];
		$record['diagnostics'] = $meta['diagnostics'] ?? [];

		return $record;
	}
}

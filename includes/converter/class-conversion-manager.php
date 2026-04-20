<?php
/**
 * Conversion Manager.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Converter;

use WP_Error;
use StackBlueprint\Utilities\ApiClient;
use StackBlueprint\Utilities\FileHandler;

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
	 * @var ApiClient
	 */
	private ApiClient $api_client;

	/**
	 * @var FileHandler
	 */
	private FileHandler $file_handler;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->api_client   = new ApiClient();
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

		// Log conversion start.
		$conversion_id = $this->log_conversion( $params, $merged );
		if ( ! $conversion_id ) {
			return new WP_Error( 'db_error', __( 'Failed to create conversion record.', 'stack-blueprint' ) );
		}

		// Run conversion synchronously (WP cron can be used for large files in future).
		// Pre-validate HTML before running (AI engine only — native handles its own).
		$pre_warnings = [];
		if ( ($params['converter'] ?? 'ai') === 'ai' ) {
			$validation   = $this->api_client->pre_validate( $merged );
			$pre_warnings = $validation['warnings'] ?? [];
		}

		$result = $this->run_conversion( $merged, $params, $conversion_id );

		if ( is_wp_error( $result ) ) {
			$this->update_conversion_status( $conversion_id, 'failed', $result->get_error_message() );
			return $result;
		}

		// Merge pre-validation warnings into result.
		$result['warnings'] = array_merge( $pre_warnings, $result['warnings'] ?? [] );

		return [
			'conversion_id' => $conversion_id,
			'status'        => 'complete',
			'project_name'  => $params['project_name'],
			'strategy'      => $params['strategy'],
			'prefix'        => $params['prefix'],
			'converter'     => $params['converter'] ?? 'ai',
			'class_map'     => $result['class_map'] ?? [],
			'warnings'      => $result['warnings'] ?? [],
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

		// Inject before </body> if possible.
		if ( str_contains( $html, '</body>' ) ) {
			$html = str_replace( '</body>', $extra_css . $extra_js . '</body>', $html );
		} else {
			$html .= $extra_css . $extra_js;
		}

		return $html;
	}

	/**
	 * Execute the conversion — either via AI API or the native offline converter.
	 */
	private function run_conversion( string $content, array $params, int $conversion_id ): array|WP_Error {
		$converter_mode = $params['converter'] ?? 'ai'; // 'ai' | 'native'

		if ( 'native' === $converter_mode ) {
			// Fully offline — no API call.
			$native = new NativeConverter();
			$result = $native->convert( $content, $params );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$this->store_conversion_result( $conversion_id, $result );
			return $result;
		}

		// AI path.
		$strategy = $params['strategy'] ?? 'v2';

		if ( 'v1' === $strategy ) {
			$response = $this->api_client->convert_v1( $content, $params );
		} else {
			$response = $this->api_client->convert_v2( $content, $params );
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$parsed = $this->parse_ai_response( $response['text'] );

		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		$this->store_conversion_result( $conversion_id, $parsed );
		return $parsed;
	}

	/**
	 * Parse and validate the JSON response from Claude.
	 */
	private function parse_ai_response( string $raw_text ): array|WP_Error {
		// Strip any accidental markdown fences.
		$clean = trim( preg_replace( '/^```(json)?\s*/m', '', preg_replace( '/```\s*$/m', '', $raw_text ) ) );

		$data = json_decode( $clean, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			// Try to extract JSON from the response.
			if ( preg_match( '/\{.*\}/s', $clean, $matches ) ) {
				$data = json_decode( $matches[0], true );
			}

			if ( JSON_ERROR_NONE !== json_last_error() ) {
				return new WP_Error(
					'parse_error',
					__( 'AI returned invalid JSON. Try again or simplify your prototype.', 'stack-blueprint' )
				);
			}
		}

		if ( empty( $data['json_template'] ) ) {
			return new WP_Error(
				'missing_template',
				__( 'AI response did not contain a valid template. Try again.', 'stack-blueprint' )
			);
		}

		return [
			'json_template' => $data['json_template'],
			'json_output'   => wp_json_encode( $data['json_template'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ),
			'css_output'    => $data['companion_css'] ?? '',
			'class_map'     => $data['class_map'] ?? [],
			'warnings'      => $data['warnings'] ?? [],
		];
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

		// Store large output in postmeta-style options to avoid column size limits.
		update_option( 'sb_result_json_' . $id, $result['json_output'] ?? '', false );
		update_option( 'sb_result_css_' . $id, $result['css_output'] ?? '', false );
		update_option( 'sb_result_meta_' . $id, [
			'class_map' => $result['class_map'] ?? [],
			'warnings'  => $result['warnings'] ?? [],
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
	public function get_conversion( int $id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}sb_conversions WHERE id = %d",
				$id
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$row['json_output'] = get_option( 'sb_result_json_' . $id, '' );
		$row['css_output']  = get_option( 'sb_result_css_' . $id, '' );
		$meta               = get_option( 'sb_result_meta_' . $id, [] );
		$row['class_map']   = $meta['class_map'] ?? [];
		$row['warnings']    = $meta['warnings'] ?? [];

		return $row;
	}

	/**
	 * Get recent conversion history.
	 */
	public function get_history( int $limit = 20 ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, project_name, strategy, prefix, status, file_size, created_at, completed_at, error_message
				FROM {$wpdb->prefix}sb_conversions
				ORDER BY created_at DESC
				LIMIT %d",
				$limit
			),
			ARRAY_A
		);

		return $rows ?: [];
	}
}

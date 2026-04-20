<?php
/**
 * REST API Controller.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;
use StackBlueprint\Converter\ConversionManager;
use StackBlueprint\Utilities\FileHandler;
use StackBlueprint\Utilities\Helpers;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class RestApi
 */
class RestApi extends WP_REST_Controller {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'stack-blueprint/v1';

	/**
	 * Register REST routes.
	 */
	public function register_routes(): void {

		// Upload and start conversion.
		register_rest_route( $this->namespace, '/convert', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_convert' ],
				'permission_callback' => [ $this, 'admin_permission' ],
				'args'                => $this->get_convert_args(),
			],
		] );

		// Get conversion status / result.
		register_rest_route( $this->namespace, '/convert/(?P<id>\d+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_conversion' ],
				'permission_callback' => [ $this, 'admin_permission' ],
				'args'                => [
					'id' => [
						'required'          => true,
						'type'              => 'integer',
						'sanitize_callback' => 'absint',
					],
				],
			],
		] );

		// Get conversion progress.
		register_rest_route( $this->namespace, '/convert-progress', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_convert_progress' ],
				'permission_callback' => '__return_true',
			],
		] );

		// Get conversion history.
		register_rest_route( $this->namespace, '/history', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_history' ],
				'permission_callback' => [ $this, 'admin_permission' ],
			],
		] );

		// Save settings.
		register_rest_route( $this->namespace, '/settings', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_settings' ],
				'permission_callback' => [ $this, 'admin_permission' ],
			],
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'save_settings' ],
				'permission_callback' => [ $this, 'admin_permission' ],
			],
		] );

		// Test API key.
		register_rest_route( $this->namespace, '/test-api', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'test_api_key' ],
				'permission_callback' => [ $this, 'admin_permission' ],
			],
		] );

		// Save template to Elementor.
		register_rest_route( $this->namespace, '/save-template', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'save_template' ],
				'permission_callback' => [ $this, 'admin_permission' ],
			],
		] );

		// Push design tokens to Elementor Global Colors & Fonts.
		register_rest_route( $this->namespace, '/push-globals', [
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'push_globals' ],
				'permission_callback' => [ $this, 'admin_permission' ],
			],
		] );

		// Download result files.
		register_rest_route( $this->namespace, '/download/(?P<id>\d+)/(?P<type>[a-z]+)', [
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'download_file' ],
				// Permission handled inside the callback via _wpnonce query param
				// so direct link clicks work without an Authorization header.
				'permission_callback' => '__return_true',
				'args'                => [
					'id'   => [ 'required' => true, 'type' => 'integer', 'sanitize_callback' => 'absint' ],
					'type' => [ 'required' => true, 'type' => 'string', 'enum' => [ 'json', 'css' ] ],
				],
			],
		] );
	}

	/**
	 * Permission: must be admin.
	 */
	public function admin_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Handle a new conversion request.
	 */
	public function handle_convert( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$files = $request->get_file_params();

		if ( empty( $files['html_file'] ) ) {
			return new WP_Error( 'missing_file', __( 'No HTML file provided.', 'stack-blueprint' ), [ 'status' => 400 ] );
		}

		$project_name = sanitize_text_field( $request->get_param( 'project_name' ) ?? 'my-project' );
		$raw_prefix   = (string) ( $request->get_param( 'prefix' ) ?? '' );
		$params = [
			'project_name' => $project_name,
			'prefix'       => $raw_prefix,
			'strategy'     => sanitize_key( $request->get_param( 'strategy' ) ?? get_option( 'sb_default_strategy', 'v2' ) ),
			'converter'    => sanitize_key( $request->get_param( 'converter' ) ?? 'ai' ),
			'tx_id'        => sanitize_key( $request->get_param( 'tx_id' ) ?? '' ),
		];

		// Optionally handle companion CSS/JS files.
		$companion_css = $files['css_file'] ?? null;
		$companion_js  = $files['js_file']  ?? null;

		$manager = new ConversionManager();
		$result  = $manager->start_conversion( $files['html_file'], $companion_css, $companion_js, $params );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( $result, 202 );
	}

	/**
	 * Get a single conversion record.
	 */
	public function get_conversion( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$id     = $request->get_param( 'id' );
		$manager = new ConversionManager();
		$record  = $manager->get_conversion( $id );

		if ( ! $record ) {
			return new WP_Error( 'not_found', __( 'Conversion not found.', 'stack-blueprint' ), [ 'status' => 404 ] );
		}

		return new WP_REST_Response( $record, 200 );
	}

	/**
	 * Get real-time conversion progress.
	 */
	public function get_convert_progress( WP_REST_Request $request ): WP_REST_Response {
		$tx_id = $request->get_param( 'tx_id' );
		if ( empty( $tx_id ) ) {
			return new WP_REST_Response( [ 'pass' => 0 ], 200 );
		}
		
		$pass = get_transient( 'sb_tx_' . sanitize_key( $tx_id ) );
		return new WP_REST_Response( [ 'pass' => intval( $pass ) ], 200 );
	}

	/**
	 * Get conversion history.
	 */
	public function get_history( WP_REST_Request $request ): WP_REST_Response {
		$manager = new ConversionManager();
		$history = $manager->get_history( 20 );
		return new WP_REST_Response( $history, 200 );
	}

	/**
	 * Get all plugin settings.
	 */
	public function get_settings( WP_REST_Request $request ): WP_REST_Response {
		$settings = [
			'api_key'          => ! empty( get_option( 'sb_api_key' ) ) ? str_repeat( '•', 32 ) : '',
			'api_key_set'      => ! empty( get_option( 'sb_api_key' ) ),
			'api_model'        => get_option( 'sb_api_model', 'claude-sonnet-4-20250514' ),
			'api_mode'         => get_option( 'sb_api_mode', 'own' ),
			'default_strategy' => get_option( 'sb_default_strategy', 'v2' ),
			'max_file_size'    => (int) get_option( 'sb_max_file_size', 5 ),
		];
		return new WP_REST_Response( $settings, 200 );
	}

	/**
	 * Save plugin settings.
	 */
	public function save_settings( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$body = $request->get_json_params();

		if ( isset( $body['api_key'] ) && ! str_contains( (string) $body['api_key'], '•' ) ) {
			update_option( 'sb_api_key', sanitize_text_field( $body['api_key'] ) );
		}
		if ( isset( $body['api_model'] ) ) {
			update_option( 'sb_api_model', sanitize_text_field( $body['api_model'] ) );
		}
		if ( isset( $body['api_mode'] ) ) {
			update_option( 'sb_api_mode', in_array( $body['api_mode'], [ 'own', 'builtin' ], true ) ? $body['api_mode'] : 'own' );
		}
		if ( isset( $body['default_strategy'] ) ) {
			update_option( 'sb_default_strategy', sanitize_key( $body['default_strategy'] ) );
		}
		if ( isset( $body['max_file_size'] ) ) {
			update_option( 'sb_max_file_size', absint( $body['max_file_size'] ) );
		}

		return new WP_REST_Response( [ 'success' => true ], 200 );
	}

	/**
	 * Push design tokens to Elementor Global Colors and Fonts.
	 *
	 * @param WP_REST_Request $request Request with colors and fonts arrays.
	 */
	public function push_globals( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return new WP_Error(
				'elementor_not_active',
				__( 'Elementor must be active to push global tokens.', 'stack-blueprint' ),
				[ 'status' => 400 ]
			);
		}

		$body   = $request->get_json_params();
		$colors = $body['colors'] ?? [];
		$fonts  = $body['fonts']  ?? [];

		// Get the active Elementor kit.
		$kit_id = \Elementor\Plugin::$instance->kits_manager->get_active_id();
		if ( ! $kit_id ) {
			return new WP_Error( 'no_kit', __( 'Could not find the active Elementor kit.', 'stack-blueprint' ), [ 'status' => 500 ] );
		}

		$kit = \Elementor\Plugin::$instance->kits_manager->get_kit( $kit_id );
		if ( ! $kit ) {
			return new WP_Error( 'kit_error', __( 'Could not load the active Elementor kit.', 'stack-blueprint' ), [ 'status' => 500 ] );
		}

		// Read existing system colors and custom colors.
		$existing_colors = $kit->get_meta( '_elementor_page_settings' )['system_colors'] ?? [];
		$custom_colors   = $kit->get_meta( '_elementor_page_settings' )['custom_colors'] ?? [];

		// Build new custom color entries — avoid duplicating by title.
		$existing_titles = array_column( $custom_colors, 'title' );
		$pushed_colors   = 0;

		foreach ( $colors as $var => $data ) {
			$title = sanitize_text_field( $data['name'] ?? $var );
			$value = sanitize_text_field( $data['value'] ?? '' );

			if ( ! $value ) continue;

			// If title already exists, update it.
			$found = array_search( $title, $existing_titles, true );
			if ( false !== $found ) {
				$custom_colors[ $found ]['color'] = $value;
			} else {
				$custom_colors[] = [
					'_id'   => substr( md5( $title ), 0, 7 ),
					'title' => $title,
					'color' => $value,
				];
			}
			$pushed_colors++;
		}

		// Read existing custom typography.
		$custom_typography = $kit->get_meta( '_elementor_page_settings' )['custom_typography'] ?? [];
		$existing_tnames   = array_column( $custom_typography, 'title' );
		$pushed_fonts      = 0;

		foreach ( $fonts as $family => $role_name ) {
			$title  = sanitize_text_field( $role_name );
			$family = sanitize_text_field( $family );

			if ( ! $title || ! $family ) continue;

			$found = array_search( $title, $existing_tnames, true );
			$entry = [
				'_id'   => substr( md5( $title ), 0, 7 ),
				'title' => $title,
				'typography_typography'   => 'custom',
				'typography_font_family'  => $family,
				'typography_font_weight'  => '400',
			];

			if ( false !== $found ) {
				$custom_typography[ $found ] = $entry;
			} else {
				$custom_typography[] = $entry;
			}
			$pushed_fonts++;
		}

		// Merge and save back to the kit.
		$page_settings = $kit->get_meta( '_elementor_page_settings' ) ?: [];
		$page_settings['custom_colors']     = $custom_colors;
		$page_settings['custom_typography'] = $custom_typography;

		update_post_meta( $kit_id, '_elementor_page_settings', $page_settings );

		// Clear Elementor CSS cache so changes take effect.
		\Elementor\Plugin::$instance->files_manager->clear_cache();

		return new WP_REST_Response( [
			'success'       => true,
			'pushed_colors' => $pushed_colors,
			'pushed_fonts'  => $pushed_fonts,
		], 200 );
	}

	/**
	 * Test the configured API key.
	 */
	public function test_api_key( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$body    = $request->get_json_params();
		$api_key = sanitize_text_field( $body['api_key'] ?? get_option( 'sb_api_key', '' ) );

		if ( empty( $api_key ) || str_contains( $api_key, '•' ) ) {
			$api_key = get_option( 'sb_api_key', '' );
		}

		if ( empty( $api_key ) ) {
			return new WP_Error( 'no_key', __( 'No API key configured.', 'stack-blueprint' ), [ 'status' => 400 ] );
		}

		$client = new \StackBlueprint\Utilities\ApiClient( $api_key );
		$result = $client->test_connection();

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( [ 'success' => true, 'model' => $result ], 200 );
	}

	/**
	 * Save conversion result as an Elementor template.
	 */
	public function save_template( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$body = $request->get_json_params();
		$id   = absint( $body['conversion_id'] ?? 0 );

		if ( ! $id ) {
			return new WP_Error( 'missing_id', __( 'Conversion ID required.', 'stack-blueprint' ), [ 'status' => 400 ] );
		}

		$manager  = new ConversionManager();
		$record   = $manager->get_conversion( $id );

		if ( ! $record || empty( $record['json_output'] ) ) {
			return new WP_Error( 'not_ready', __( 'Conversion result not available.', 'stack-blueprint' ), [ 'status' => 404 ] );
		}

		$template = new \StackBlueprint\Elementor\TemplateBuilder();
		$result   = $template->save_to_elementor( $record );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return new WP_REST_Response( [ 'success' => true, 'template_id' => $result ], 200 );
	}

	/**
	 * Download a conversion output file.
	 * Accepts nonce via query param since this is triggered by direct link click.
	 */
	public function download_file( WP_REST_Request $request ): void {
		// Verify nonce passed as query param (download links can't send headers).
		$nonce = $request->get_param( '_wpnonce' );
		if ( ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
			status_header( 403 );
			wp_die( esc_html__( 'Session expired. Please refresh and try again.', 'stack-blueprint' ) );
		}

		$id   = absint( $request->get_param( 'id' ) );
		$type = sanitize_key( $request->get_param( 'type' ) );

		if ( ! in_array( $type, [ 'json', 'css' ], true ) ) {
			status_header( 400 );
			wp_die( esc_html__( 'Invalid file type.', 'stack-blueprint' ) );
		}

		$manager = new ConversionManager();
		$record  = $manager->get_conversion( $id );

		if ( ! $record ) {
			status_header( 404 );
			wp_die( esc_html__( 'Conversion not found.', 'stack-blueprint' ) );
		}

		$field   = 'json' === $type ? 'json_output' : 'css_output';
		$content = $record[ $field ] ?? '';

		if ( empty( $content ) ) {
			status_header( 404 );
			wp_die( esc_html__( 'File not ready. The conversion may have failed.', 'stack-blueprint' ) );
		}

		$slug     = sanitize_file_name( $record['project_name'] ?: 'template' );
		$filename = $slug . '-elementor.' . $type;
		$mime     = 'json' === $type ? 'application/json' : 'text/css';

		// Kill WP output buffering.
		if ( ob_get_level() ) ob_end_clean();

		nocache_headers();
		header( 'Content-Type: ' . $mime . '; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . strlen( $content ) );
		header( 'X-Content-Type-Options: nosniff' );

		// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo $content;
		exit;
	}

	/**
	 * Define convert endpoint args.
	 */
	private function get_convert_args(): array {
		return [
			'project_name' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => 'my-project',
			],
			'prefix' => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'default'           => '',
			],
			'strategy' => [
				'type'    => 'string',
				'enum'    => [ 'v1', 'v2' ],
				'default' => 'v2',
			],
			'converter' => [
				'type'    => 'string',
				'enum'    => [ 'ai', 'native' ],
				'default' => 'ai',
			],
		];
	}
}

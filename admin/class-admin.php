<?php
/**
 * Admin Bootstrap.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Admin;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Admin
 *
 * Registers admin menu pages and enqueues admin assets.
 */
class Admin {

	/**
	 * Admin menu slug.
	 */
	const MENU_SLUG = 'stack-blueprint';

	/**
	 * Initialise admin hooks.
	 */
	public function init(): void {
		add_action( 'admin_menu', [ $this, 'register_menus' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'admin_body_class', [ $this, 'add_body_class' ] );
	}

	/**
	 * Register top-level and sub-menu pages.
	 */
	public function register_menus(): void {
		add_menu_page(
			__( 'Stack Blueprint', 'stack-blueprint' ),
			__( 'Stack Blueprint', 'stack-blueprint' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_converter' ],
			$this->get_menu_icon(),
			30
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Converter', 'stack-blueprint' ),
			__( 'Converter', 'stack-blueprint' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'render_converter' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'History', 'stack-blueprint' ),
			__( 'History', 'stack-blueprint' ),
			'manage_options',
			self::MENU_SLUG . '-history',
			[ $this, 'render_history' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Design Tokens', 'stack-blueprint' ),
			__( 'Design Tokens', 'stack-blueprint' ),
			'manage_options',
			self::MENU_SLUG . '-tokens',
			[ $this, 'render_tokens' ]
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'stack-blueprint' ),
			__( 'Settings', 'stack-blueprint' ),
			'manage_options',
			self::MENU_SLUG . '-settings',
			[ $this, 'render_settings' ]
		);
	}

	/**
	 * Enqueue admin CSS and JS on plugin pages.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_assets( string $hook ): void {
		$plugin_pages = [
			'toplevel_page_stack-blueprint',
			'stack-blueprint_page_stack-blueprint-history',
			'stack-blueprint_page_stack-blueprint-tokens',
			'stack-blueprint_page_stack-blueprint-settings',
		];

		if ( ! in_array( $hook, $plugin_pages, true ) ) {
			return;
		}

		// Admin stylesheet.
		wp_enqueue_style(
			'stack-blueprint-admin',
			SB_ADMIN_URL . 'css/admin.css',
			[],
			SB_VERSION
		);

		// Admin script.
		wp_enqueue_script(
			'stack-blueprint-admin',
			SB_ADMIN_URL . 'js/admin.js',
			[],
			SB_VERSION,
			true
		);

		// Pass data to JS.
		wp_localize_script( 'stack-blueprint-admin', 'SB', [
			'apiBase'  => esc_url_raw( rest_url( 'stack-blueprint/v1' ) ),
			'nonce'    => wp_create_nonce( 'wp_rest' ),
			'version'  => SB_VERSION,
			'adminUrl' => esc_url_raw( admin_url( 'admin.php' ) ),
			'apiMode'  => get_option( 'sb_api_mode', 'own' ),
			'i18n'     => [
				'noFile' => __( 'No file', 'stack-blueprint' ),
			],
		] );
	}

	/**
	 * Add body class for full-screen override of WP admin UI.
	 *
	 * @param string $classes Existing body classes.
	 * @return string Modified body classes.
	 */
	public function add_body_class( string $classes ): string {
		$screen = get_current_screen();
		if ( $screen && str_contains( $screen->id, 'stack-blueprint' ) ) {
			$classes .= ' sb-admin-page';
		}
		return $classes;
	}

	/**
	 * Render the Converter page.
	 */
	public function render_converter(): void {
		require_once SB_ADMIN_PATH . 'views/page-converter.php';
	}

	/**
	 * Render the History page.
	 */
	public function render_history(): void {
		require_once SB_ADMIN_PATH . 'views/page-history.php';
	}

	/**
	 * Render the Design Tokens page.
	 */
	public function render_tokens(): void {
		require_once SB_ADMIN_PATH . 'views/page-tokens.php';
	}

	/**
	 * Render the Settings page.
	 */
	public function render_settings(): void {
		require_once SB_ADMIN_PATH . 'views/page-settings.php';
	}

	/**
	 * SVG icon for the admin menu (base64 encoded).
	 */
	private function get_menu_icon(): string {
		$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="none"><rect x="1" y="1" width="8" height="8" rx="1" fill="currentColor" opacity="1"/><rect x="11" y="1" width="8" height="5" rx="1" fill="currentColor" opacity="0.7"/><rect x="11" y="8" width="8" height="5" rx="1" fill="currentColor" opacity="0.4"/><rect x="1" y="11" width="8" height="8" rx="1" fill="currentColor" opacity="0.6"/><rect x="11" y="15" width="8" height="4" rx="1" fill="currentColor" opacity="0.9"/></svg>';
		return 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}
}

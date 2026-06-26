<?php
/**
 * Main Plugin class.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Plugin
 *
 * Singleton that bootstraps all plugin components.
 */
final class Plugin {

	/**
	 * Single instance.
	 *
	 * @var Plugin|null
	 */
	private static ?Plugin $instance = null;

	/**
	 * Private constructor — use get_instance().
	 */
	private function __construct() {}

	/**
	 * Get singleton instance.
	 */
	public static function get_instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Boot all plugin components.
	 */
	public function boot(): void {
		$this->load_dependencies();
		$this->init_hooks();
	}

	/**
	 * Load all required files.
	 */
	private function load_dependencies(): void {
		// Core utilities.
		require_once SB_INCLUDES_PATH . 'utilities/class-helpers.php';
		require_once SB_INCLUDES_PATH . 'utilities/class-file-handler.php';
		require_once SB_INCLUDES_PATH . 'utilities/class-html-analyzer.php';
		require_once SB_INCLUDES_PATH . 'utilities/providers/class-provider-base.php';
		require_once SB_INCLUDES_PATH . 'utilities/providers/class-provider-anthropic.php';
		require_once SB_INCLUDES_PATH . 'utilities/providers/class-provider-openai.php';
		require_once SB_INCLUDES_PATH . 'utilities/providers/class-provider-gemini.php';
		require_once SB_INCLUDES_PATH . 'utilities/class-api-manager.php';

		// Elementor integration.
		require_once SB_INCLUDES_PATH . 'elementor/class-template-builder.php';
		require_once SB_INCLUDES_PATH . 'elementor/class-widget-registry.php';

		// Converter engines.
		require_once SB_INCLUDES_PATH . 'converter/class-conversion-manager.php';

		// Admin.
		if ( is_admin() ) {
			require_once SB_ADMIN_PATH . 'class-admin.php';
		}

		// REST API routes.
		require_once SB_INCLUDES_PATH . 'class-rest-api.php';
	}

	/**
	 * Register all action/filter hooks.
	 */
	private function init_hooks(): void {
		// Admin.
		if ( is_admin() ) {
			$admin = new \StackBlueprint\Admin\Admin();
			$admin->init();
		}

		// REST routes (always needed for admin AJAX-style calls).
		$rest = new RestApi();
		add_action( 'rest_api_init', [ $rest, 'register_routes' ] );
	}
}

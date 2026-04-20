<?php
/**
 * Plugin Name: Stack Blueprint
 * Plugin URI:  https://cr8vstacks.com/stack-blueprint
 * Description: Convert custom HTML/CSS/JS prototypes into importable Elementor page templates using AI. Design freely, convert precisely.
 * Version:     1.0.0
 * Requires at least: 6.3
 * Requires PHP: 8.1
 * Author:      Cr8v Stacks
 * Author URI:  https://cr8vstacks.com
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: stack-blueprint
 * Domain Path: /languages
 *
 * @package StackBlueprint
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin constants.
define( 'SB_VERSION',      '1.0.0' );
define( 'SB_FILE',         __FILE__ );
define( 'SB_PATH',         plugin_dir_path( __FILE__ ) );
define( 'SB_URL',          plugin_dir_url( __FILE__ ) );
define( 'SB_ADMIN_PATH',   SB_PATH . 'admin/' );
define( 'SB_ADMIN_URL',    SB_URL  . 'admin/' );
define( 'SB_INCLUDES_PATH', SB_PATH . 'includes/' );
define( 'SB_ASSETS_URL',   SB_URL  . 'assets/' );
define( 'SB_MIN_PHP',      '8.1' );
define( 'SB_MIN_WP',       '6.3' );

/**
 * Check environment before loading.
 */
function sb_check_environment(): bool {
	if ( version_compare( PHP_VERSION, SB_MIN_PHP, '<' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p>' .
				sprintf(
					/* translators: %s: required PHP version */
					esc_html__( 'Stack Blueprint requires PHP %s or higher.', 'stack-blueprint' ),
					SB_MIN_PHP
				) .
				'</p></div>';
		} );
		return false;
	}
	return true;
}

/**
 * Autoloader for plugin classes.
 */
spl_autoload_register( function ( string $class ): void {
	$prefix   = 'StackBlueprint\\';
	$base_dir = SB_PATH . 'includes/';

	if ( strncmp( $prefix, $class, strlen( $prefix ) ) !== 0 ) {
		return;
	}

	$relative_class = substr( $class, strlen( $prefix ) );
	$parts          = explode( '\\', $relative_class );
	$class_name     = array_pop( $parts );

	// Convert namespace parts to directory path.
	$sub_dir = '';
	foreach ( $parts as $part ) {
		$sub_dir .= strtolower( preg_replace( '/([A-Z])/', '-$1', lcfirst( $part ) ) ) . '/';
	}

	$file = $base_dir . $sub_dir . 'class-' . strtolower( str_replace( '_', '-', preg_replace( '/([A-Z])/', '-$1', lcfirst( $class_name ) ) ) ) . '.php';

	if ( file_exists( $file ) ) {
		require_once $file;
	}
} );

/**
 * Load plugin text domain.
 */
function sb_load_textdomain(): void {
	load_plugin_textdomain(
		'stack-blueprint',
		false,
		dirname( plugin_basename( SB_FILE ) ) . '/languages'
	);
}
add_action( 'plugins_loaded', 'sb_load_textdomain' );

/**
 * Activation hook.
 */
function sb_activate(): void {
	require_once SB_INCLUDES_PATH . 'class-activator.php';
	StackBlueprint\Activator::activate();
}
register_activation_hook( SB_FILE, 'sb_activate' );

/**
 * Deactivation hook.
 */
function sb_deactivate(): void {
	require_once SB_INCLUDES_PATH . 'class-deactivator.php';
	StackBlueprint\Deactivator::deactivate();
}
register_deactivation_hook( SB_FILE, 'sb_deactivate' );

/**
 * Boot the plugin.
 */
function sb_init(): void {
	if ( ! sb_check_environment() ) {
		return;
	}
	require_once SB_INCLUDES_PATH . 'class-plugin.php';
	StackBlueprint\Plugin::get_instance()->boot();
}
add_action( 'plugins_loaded', 'sb_init' );

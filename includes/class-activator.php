<?php
/**
 * Plugin Activator.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Activator
 *
 * Runs on plugin activation: sets default options, creates DB tables.
 */
class Activator {

	/**
	 * Run activation routines.
	 */
	public static function activate(): void {
		self::set_defaults();
		self::create_tables();

		// Store activation timestamp.
		update_option( 'sb_activated_at', time() );
		update_option( 'sb_version', SB_VERSION );

		// Flush rewrite rules after activation.
		flush_rewrite_rules();
	}

	/**
	 * Set default plugin options.
	 */
	private static function set_defaults(): void {
		$defaults = [
			'sb_api_key'            => '',
			'sb_api_model'          => 'claude-sonnet-4-20250514',
			'sb_api_mode'           => 'own',
			'sb_default_strategy'   => 'v2',
			'sb_max_file_size'      => 5,
			'sb_save_history'       => true,
			'sb_conversion_timeout' => 120,
		];

		foreach ( $defaults as $key => $value ) {
			if ( false === get_option( $key ) ) {
				update_option( $key, $value );
			}
		}
	}

	/**
	 * Create custom database tables.
	 */
	private static function create_tables(): void {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Conversion history table.
		$table_conversions = $wpdb->prefix . 'sb_conversions';

		$sql = "CREATE TABLE IF NOT EXISTS {$table_conversions} (
			id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			project_name  VARCHAR(200)        NOT NULL DEFAULT '',
			strategy      VARCHAR(10)         NOT NULL DEFAULT 'v2',
			prefix        VARCHAR(20)         NOT NULL DEFAULT 'sb',
			status        VARCHAR(20)         NOT NULL DEFAULT 'pending',
			input_hash    VARCHAR(64)         NOT NULL DEFAULT '',
			file_size     INT(11)             NOT NULL DEFAULT 0,
			created_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			completed_at  DATETIME                     DEFAULT NULL,
			error_message TEXT                         DEFAULT NULL,
			PRIMARY KEY (id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
}

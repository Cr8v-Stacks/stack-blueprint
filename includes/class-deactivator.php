<?php
/**
 * Plugin Deactivator.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Deactivator
 */
class Deactivator {

	/**
	 * Run deactivation routines.
	 */
	public static function deactivate(): void {
		// Clear any scheduled cron jobs.
		wp_clear_scheduled_hook( 'sb_cleanup_temp_files' );

		// Flush rewrite rules.
		flush_rewrite_rules();
	}
}

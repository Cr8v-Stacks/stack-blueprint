<?php
/**
 * Elementor Template Builder.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Elementor;

use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class TemplateBuilder
 *
 * Saves converted JSON output as an Elementor page template post.
 */
class TemplateBuilder {

	/**
	 * Save a conversion result as an Elementor template.
	 *
	 * @param array $record Conversion record with json_output and css_output.
	 * @return int|WP_Error Template post ID or error.
	 */
	public function save_to_elementor( array $record ): int|WP_Error {
		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			return new WP_Error(
				'elementor_not_active',
				__( 'Elementor must be active to save templates.', 'stack-blueprint' )
			);
		}

		$title    = sanitize_text_field( $record['project_name'] ?? 'Stack Blueprint Template' );
		$json_raw = $record['json_output'] ?? '';

		if ( empty( $json_raw ) ) {
			return new WP_Error( 'empty_template', __( 'No template content to save.', 'stack-blueprint' ) );
		}

		$template_data = json_decode( $json_raw, true );

		if ( JSON_ERROR_NONE !== json_last_error() || empty( $template_data ) ) {
			return new WP_Error( 'invalid_json', __( 'Template JSON is invalid.', 'stack-blueprint' ) );
		}

		// Create the template post.
		$post_id = wp_insert_post( [
			'post_title'   => $title . ' — Stack Blueprint',
			'post_status'  => 'publish',
			'post_type'    => 'elementor_library',
			'meta_input'   => [
				'_elementor_template_type' => 'page',
			],
		] );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Store the Elementor data.
		$content = $template_data['content'] ?? [];
		update_post_meta( $post_id, '_elementor_data', wp_slash( wp_json_encode( $content ) ) );
		update_post_meta( $post_id, '_elementor_version', '3.0.0' );
		update_post_meta( $post_id, '_elementor_edit_mode', 'builder' );

		// If there's companion CSS, store it in post meta for reference.
		if ( ! empty( $record['css_output'] ) ) {
			update_post_meta( $post_id, '_sb_companion_css', $record['css_output'] );
		}

		// Set taxonomy for template type.
		if ( taxonomy_exists( 'elementor_library_type' ) ) {
			wp_set_object_terms( $post_id, 'page', 'elementor_library_type', false );
		}

		return $post_id;
	}
}

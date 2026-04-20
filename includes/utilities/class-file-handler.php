<?php
/**
 * File Handler Utility.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Utilities;

use WP_Error;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class FileHandler
 */
class FileHandler {

	/**
	 * Allowed MIME types per extension.
	 */
	private const ALLOWED_TYPES = [
		'html' => [ 'text/html', 'application/xhtml+xml', 'text/plain' ],
		'css'  => [ 'text/css', 'text/plain' ],
		'js'   => [ 'application/javascript', 'text/javascript', 'text/plain', 'application/x-javascript' ],
	];

	/**
	 * Read and validate an uploaded file.
	 *
	 * @param array  $file_entry $_FILES entry.
	 * @param string $type       Expected type: 'html', 'css', 'js'.
	 * @return string|WP_Error File contents or WP_Error.
	 */
	public function read_uploaded_file( array $file_entry, string $type ): string|WP_Error {
		if ( ! isset( $file_entry['tmp_name'] ) || empty( $file_entry['tmp_name'] ) ) {
			return new WP_Error( 'no_file', __( 'No file was uploaded.', 'stack-blueprint' ) );
		}

		// Check for upload errors.
		if ( UPLOAD_ERR_OK !== ( $file_entry['error'] ?? UPLOAD_ERR_NO_FILE ) ) {
			return new WP_Error( 'upload_error', $this->upload_error_message( $file_entry['error'] ) );
		}

		// Validate size (max 5MB by default).
		$max_bytes = (int) get_option( 'sb_max_file_size', 5 ) * 1024 * 1024;
		if ( $file_entry['size'] > $max_bytes ) {
			return new WP_Error(
				'file_too_large',
				sprintf(
					/* translators: %s: max file size in MB */
					__( 'File exceeds the maximum allowed size of %sMB.', 'stack-blueprint' ),
					get_option( 'sb_max_file_size', 5 )
				)
			);
		}

		// Validate the file is an actual uploaded file.
		if ( ! is_uploaded_file( $file_entry['tmp_name'] ) ) {
			return new WP_Error( 'invalid_upload', __( 'Invalid file upload.', 'stack-blueprint' ) );
		}

		// Read content.
		$content = file_get_contents( $file_entry['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

		if ( false === $content ) {
			return new WP_Error( 'read_error', __( 'Could not read the uploaded file.', 'stack-blueprint' ) );
		}

		// Basic content sanity check for HTML files.
		if ( 'html' === $type && ! $this->looks_like_html( $content ) ) {
			return new WP_Error( 'invalid_html', __( 'The uploaded file does not appear to be a valid HTML document.', 'stack-blueprint' ) );
		}

		return $content;
	}

	/**
	 * Check if content looks like HTML.
	 */
	private function looks_like_html( string $content ): bool {
		$lower = strtolower( trim( $content ) );
		return str_contains( $lower, '<html' )
			|| str_contains( $lower, '<!doctype' )
			|| str_contains( $lower, '<body' )
			|| str_contains( $lower, '<div' )
			|| str_contains( $lower, '<section' );
	}

	/**
	 * Map PHP upload error codes to readable messages.
	 */
	private function upload_error_message( int $code ): string {
		$messages = [
			UPLOAD_ERR_INI_SIZE   => __( 'The file exceeds the server upload limit.', 'stack-blueprint' ),
			UPLOAD_ERR_FORM_SIZE  => __( 'The file exceeds the form upload limit.', 'stack-blueprint' ),
			UPLOAD_ERR_PARTIAL    => __( 'The file was only partially uploaded.', 'stack-blueprint' ),
			UPLOAD_ERR_NO_FILE    => __( 'No file was uploaded.', 'stack-blueprint' ),
			UPLOAD_ERR_NO_TMP_DIR => __( 'No temporary folder available for upload.', 'stack-blueprint' ),
			UPLOAD_ERR_CANT_WRITE => __( 'Could not write the uploaded file to disk.', 'stack-blueprint' ),
			UPLOAD_ERR_EXTENSION  => __( 'A PHP extension blocked the file upload.', 'stack-blueprint' ),
		];

		return $messages[ $code ] ?? __( 'An unknown upload error occurred.', 'stack-blueprint' );
	}
}

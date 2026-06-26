<?php
/**
 * Base AI Provider Abstract Class.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Utilities\Providers;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) exit;

abstract class Provider_Base {

	protected string $api_key;
	protected string $model;

	public function __construct( string $api_key, string $model ) {
		$this->api_key = $api_key;
		$this->model   = $model;
	}

	/**
	 * Test the connection to the provider.
	 *
	 * @return string|WP_Error Model name on success, error on failure.
	 */
	abstract public function test_connection(): string|WP_Error;

	/**
	 * Run a conversion.
	 *
	 * @param string $system_prompt
	 * @param string $user_prompt
	 * @return array|WP_Error
	 */
	abstract public function call_api( string $system_prompt, string $user_prompt ): array|WP_Error;

	/**
	 * Parses JSON response safely.
	 *
	 * @param string $text
	 * @return array|WP_Error
	 */
	protected function parse_json( string $text ): array|WP_Error {
		// Clean markdown fences if model ignored instructions
		$text = preg_replace('/^```json\s*/i', '', trim($text));
		$text = preg_replace('/```$/i', '', trim($text));

		$data = json_decode( $text, true );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'json_error', 'Invalid JSON returned by AI: ' . json_last_error_msg(), [ 'raw' => $text ] );
		}

		if ( empty( $data['json_template'] ) ) {
			return new WP_Error( 'json_structure', 'Missing json_template object.' );
		}

		return $data;
	}
}

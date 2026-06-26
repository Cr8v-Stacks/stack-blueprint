<?php
/**
 * Anthropic API Provider.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Utilities\Providers;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) exit;

class Provider_Anthropic extends Provider_Base {

	private const API_URL = 'https://api.anthropic.com/v1/messages';
	private const VERSION = '2023-06-01';

	public function test_connection(): string|WP_Error {
		$r = $this->call_api( 'You are a test bot.', 'Respond with exactly: {"status":"ok"}' );
		return is_wp_error($r) ? $r : $this->model;
	}

	public function call_api( string $system_prompt, string $user_prompt ): array|WP_Error {
		$payload = [
			'model'      => $this->model,
			'max_tokens' => 8192,
			'system'     => $system_prompt,
			'messages'   => [
				[ 'role' => 'user', 'content' => $user_prompt ]
			]
		];

		$response = wp_remote_post( self::API_URL, [
			'headers' => [
				'x-api-key'         => $this->api_key,
				'anthropic-version' => self::VERSION,
				'content-type'      => 'application/json',
			],
			'body'    => json_encode( $payload ),
			'timeout' => 120,
		] );

		if ( is_wp_error( $response ) ) return $response;

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['content'][0]['text'] ) ) {
			return new WP_Error( 'api_error', 'Invalid Anthropic response: ' . wp_remote_retrieve_body( $response ) );
		}

		return $this->parse_json( $body['content'][0]['text'] );
	}
}

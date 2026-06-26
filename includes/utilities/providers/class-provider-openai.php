<?php
/**
 * OpenAI API Provider.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Utilities\Providers;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) exit;

class Provider_OpenAI extends Provider_Base {

	private const API_URL = 'https://api.openai.com/v1/chat/completions';

	public function test_connection(): string|WP_Error {
		$r = $this->call_api( 'You are a test bot.', 'Respond with exactly: {"status":"ok"}' );
		return is_wp_error($r) ? $r : $this->model;
	}

	public function call_api( string $system_prompt, string $user_prompt ): array|WP_Error {
		$payload = [
			'model'      => $this->model,
			'messages'   => [
				[ 'role' => 'system', 'content' => $system_prompt ],
				[ 'role' => 'user', 'content' => $user_prompt ]
			],
			'response_format' => [ 'type' => 'json_object' ]
		];

		// O1 models don't support max_tokens in the same way, but 4o and 5 do.
		if ( str_contains( $this->model, 'gpt-4o' ) || str_contains( $this->model, 'gpt-5' ) ) {
			$payload['max_tokens'] = 16384;
		}

		$response = wp_remote_post( self::API_URL, [
			'headers' => [
				'Authorization' => 'Bearer ' . $this->api_key,
				'Content-Type'  => 'application/json',
			],
			'body'    => json_encode( $payload ),
			'timeout' => 120,
		] );

		if ( is_wp_error( $response ) ) return $response;

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['choices'][0]['message']['content'] ) ) {
			return new WP_Error( 'api_error', 'Invalid OpenAI response: ' . wp_remote_retrieve_body( $response ) );
		}

		return $this->parse_json( $body['choices'][0]['message']['content'] );
	}
}

<?php
/**
 * Gemini API Provider.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Utilities\Providers;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) exit;

class Provider_Gemini extends Provider_Base {

	public function test_connection(): string|WP_Error {
		$r = $this->call_api( 'You are a test bot.', 'Respond with exactly: {"status":"ok"}' );
		return is_wp_error($r) ? $r : $this->model;
	}

	public function call_api( string $system_prompt, string $user_prompt ): array|WP_Error {
		$url = "https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->api_key}";

		$payload = [
			'system_instruction' => [
				'parts' => [
					[ 'text' => $system_prompt ]
				]
			],
			'contents' => [
				[
					'parts' => [
						[ 'text' => $user_prompt ]
					]
				]
			],
			'generationConfig' => [
				'responseMimeType' => 'application/json',
			]
		];

		$response = wp_remote_post( $url, [
			'headers' => [
				'Content-Type' => 'application/json',
			],
			'body'    => json_encode( $payload ),
			'timeout' => 180, // Gemini can be slow on 1.5 Pro
		] );

		if ( is_wp_error( $response ) ) return $response;

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( empty( $body['candidates'][0]['content']['parts'][0]['text'] ) ) {
			return new WP_Error( 'api_error', 'Invalid Gemini response: ' . wp_remote_retrieve_body( $response ) );
		}

		return $this->parse_json( $body['candidates'][0]['content']['parts'][0]['text'] );
	}
}

<?php
/**
 * Script Bridge Helper.
 *
 * Centralizes source-JS runtime hardening so bridge behavior can evolve
 * without bloating the main NativeConverter class.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Converter\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ScriptBridgeHelper {

	/**
	 * Apply runtime safety transforms to bridged JS.
	 *
	 * @param string $js Source JS.
	 * @param array  $handler_names Inline handler names discovered from HTML.
	 * @return string
	 */
	public static function apply_runtime_safety( string $js, array $handler_names = [] ): string {
		$safe = $js;

		// Prevent one missing node from killing all downstream behavior.
		$safe = preg_replace(
			'/document\.getElementById\(([^)]+)\)\.([A-Za-z_\$][A-Za-z0-9_\$]*)/',
			'document.getElementById($1)?.$2',
			(string) $safe
		);

		if ( ! empty( $handler_names ) ) {
			$export_lines = [];
			foreach ( $handler_names as $fn ) {
				$fn = trim( (string) $fn );
				if ( '' === $fn ) {
					continue;
				}
				$quoted = preg_quote( $fn, '/' );
				if ( preg_match( '/\bfunction\s+' . $quoted . '\s*\(/', $safe )
					|| preg_match( '/\b(?:const|let|var)\s+' . $quoted . '\s*=\s*(?:async\s*)?(?:function|\([^)]*\)\s*=>)/', $safe ) ) {
					$export_lines[] = "if (typeof {$fn} === 'function') { window.{$fn} = {$fn}; }";
				}
			}
			if ( ! empty( $export_lines ) ) {
				$safe .= "\n" . implode( "\n", array_unique( $export_lines ) ) . "\n";
			}
		}

		return (string) $safe;
	}

	/**
	 * Discover inline handler function names from HTML attributes.
	 *
	 * @param string $html Raw source HTML.
	 * @return array<int,string>
	 */
	public static function extract_inline_handler_names( string $html ): array {
		$names = [];
		$html = trim( $html );
		if ( '' === $html ) {
			return [];
		}
		if ( preg_match_all( '/\bon[a-z]+\s*=\s*["\']\s*([A-Za-z_\$][A-Za-z0-9_\$]*)\s*\(/i', $html, $matches ) ) {
			foreach ( (array) ( $matches[1] ?? [] ) as $name ) {
				$names[ (string) $name ] = true;
			}
		}
		return array_keys( $names );
	}
}


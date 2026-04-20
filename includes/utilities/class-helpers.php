<?php
/**
 * General Helpers.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Utilities;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Helpers
 */
class Helpers {

	/**
	 * Prefixes that are too generic or likely to collide in user projects.
	 *
	 * @var string[]
	 */
	private const RESERVED_PREFIXES = [
		'wp',
		'wc',
		'el',
		'et',
		'vc',
		'be',
		'cs',
		'gp',
		'cad',
		'nl',
		'id',
		'js',
		'css',
		'php',
		'div',
		'row',
		'col',
		'btn',
		'card',
		'grid',
		'hero',
		'main',
		'site',
		'page',
		'body',
		'html',
		'app',
		'web',
		'api',
		'cms',
		'seo',
		'dev',
		'new',
		'old',
		'my',
		'the',
		'and',
		'for',
		'test',
		'project',
		'proj',
	];

	/**
	 * Format file size for display.
	 *
	 * @param int $bytes File size in bytes.
	 * @return string Human-readable size.
	 */
	public static function format_file_size( int $bytes ): string {
		if ( $bytes < 1024 ) {
			return $bytes . ' B';
		}
		if ( $bytes < 1048576 ) {
			return round( $bytes / 1024, 1 ) . ' KB';
		}
		return round( $bytes / 1048576, 2 ) . ' MB';
	}

	/**
	 * Generate a random 8-character hex ID for Elementor elements.
	 *
	 * @return string
	 */
	public static function generate_element_id(): string {
		return bin2hex( random_bytes( 4 ) );
	}

	/**
	 * Sanitize a CSS prefix.
	 *
	 * @param string $prefix Raw prefix.
	 * @return string Clean prefix.
	 */
	public static function sanitize_prefix( string $prefix ): string {
		return preg_replace( '/[^a-z0-9-]/', '', strtolower( trim( $prefix ) ) );
	}

	/**
	 * Check whether a prefix is safe for output use.
	 *
	 * @param string $prefix Prefix candidate.
	 * @return bool
	 */
	public static function is_safe_prefix( string $prefix ): bool {
		$prefix = self::sanitize_prefix( $prefix );

		if ( strlen( $prefix ) < 2 || strlen( $prefix ) > 12 ) {
			return false;
		}

		if ( in_array( $prefix, self::RESERVED_PREFIXES, true ) ) {
			return false;
		}

		return (bool) preg_match( '/^[a-z][a-z0-9-]*$/', $prefix );
	}

	/**
	 * Generate a deterministic, collision-aware prefix.
	 *
	 * @param string      $project_name Project name from the request.
	 * @param string      $filename Uploaded HTML filename.
	 * @param string|null $requested_prefix User/browser-provided prefix.
	 * @return string
	 */
	public static function generate_prefix( string $project_name, string $filename = '', ?string $requested_prefix = null ): string {
		$candidates = [];

		if ( null !== $requested_prefix && '' !== trim( $requested_prefix ) ) {
			$candidates[] = $requested_prefix;
		}

		$project_slug = sanitize_title( $project_name );
		$file_slug    = sanitize_title( pathinfo( $filename, PATHINFO_FILENAME ) );

		$project_parts = array_values( array_filter( array_map(
			fn( $part ) => self::sanitize_prefix( $part ),
			preg_split( '/[-_\s]+/', $project_slug )
		) ) );
		$meaningful_project_parts = array_values( array_filter(
			$project_parts,
			fn( $part ) => ! in_array( $part, self::RESERVED_PREFIXES, true )
		) );

		if ( $project_slug ) {
			$candidates[] = $project_slug;
		}
		if ( ! empty( $meaningful_project_parts ) ) {
			$candidates[] = implode( '-', array_slice( $meaningful_project_parts, 0, 2 ) );
			$candidates[] = $meaningful_project_parts[0];
		}
		if ( count( $meaningful_project_parts ) >= 2 ) {
			$initials = '';
			foreach ( $meaningful_project_parts as $part ) {
				$initials .= substr( $part, 0, 1 );
			}
			$candidates[] = $initials;
		}

		foreach ( [ $project_slug, $file_slug ] as $slug ) {
			if ( ! $slug ) {
				continue;
			}

			foreach ( preg_split( '/[-_\s]+/', $slug ) as $part ) {
				$part = self::sanitize_prefix( $part );
				if ( $part ) {
					$candidates[] = $part;
				}
			}

			$collapsed = self::sanitize_prefix( str_replace( '-', '', $slug ) );
			if ( $collapsed ) {
				$candidates[] = $collapsed;
			}
		}

		foreach ( $candidates as $candidate ) {
			$prefix = self::normalize_prefix_candidate( $candidate );
			if ( $prefix ) {
				return $prefix;
			}
		}

		$hash_source = strtolower( trim( $project_name . '|' . $filename ) );
		$hash        = substr( md5( $hash_source ), 0, 4 );

		return 'sb' . $hash;
	}

	/**
	 * Normalize a raw prefix candidate into a safe short prefix.
	 *
	 * @param string $candidate Raw candidate.
	 * @return string
	 */
	private static function normalize_prefix_candidate( string $candidate ): string {
		$candidate = self::sanitize_prefix( $candidate );
		if ( ! $candidate ) {
			return '';
		}

		$variants = [];

		if ( strlen( $candidate ) >= 2 ) {
			$max_len = min( 12, strlen( $candidate ) );
			$variants[] = substr( $candidate, 0, $max_len );
			if ( str_contains( $candidate, '-' ) && strlen( $candidate ) > 12 ) {
				$window = substr( $candidate, 0, 12 );
				$cut    = strrpos( $window, '-' );
				if ( false !== $cut && $cut >= 2 ) {
					$variants[] = substr( $window, 0, $cut );
				}
			}
		}

		$collapsed = preg_replace( '/[^a-z0-9]/', '', $candidate );
		if ( strlen( $collapsed ) >= 2 ) {
			$variants[] = substr( $collapsed, 0, min( 12, strlen( $collapsed ) ) );
		}

		$no_vowels = preg_replace( '/[aeiou-]/', '', $candidate );
		if ( strlen( $no_vowels ) >= 2 ) {
			$variants[] = substr( $no_vowels, 0, min( 12, strlen( $no_vowels ) ) );
		}

		foreach ( $variants as $variant ) {
			if ( self::is_safe_prefix( $variant ) ) {
				return $variant;
			}
		}

		return '';
	}
}

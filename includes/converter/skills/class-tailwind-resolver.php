<?php
/**
 * Skill: Tailwind Resolver.
 *
 * Lightweight runtime resolver backed by generated simulation knowledge.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Converter\Skills;

use StackBlueprint\Converter\Generated\SimulationKnowledge;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class TailwindResolver {

	/**
	 * Detect whether HTML strongly resembles utility-class/Tailwind markup.
	 */
	public function is_tailwind_html( string $html ): bool {
		$hits = 0;
		$tokens = [];
		if ( preg_match_all( '/\bclass\s*=\s*"([^"]+)"/i', $html, $m ) ) {
			foreach ( $m[1] as $class_attr ) {
				foreach ( preg_split( '/\s+/', trim( (string) $class_attr ) ) as $tok ) {
					$tok = trim( (string) $tok );
					if ( '' !== $tok ) {
						$tokens[ $tok ] = true;
					}
				}
			}
		}
		$tokens = array_keys( $tokens );

		foreach ( $tokens as $tok ) {
			// Ground-truth check: only count tokens that can actually resolve.
			if ( null !== $this->resolve_class( (string) $tok ) ) {
				$hits++;
				if ( $hits >= 3 ) {
					return true;
				}
			}
		}

		return $hits >= 3;
	}

	/**
	 * Resolve class token to CSS declaration (best effort).
	 */
	public function resolve_class( string $class ): ?string {
		$class = trim( $class );
		if ( '' === $class ) {
			return null;
		}

		// Important modifier: !text-white, !flex
		$class = ltrim( $class, '!' );

		// Handle Tailwind variants like: md:flex, hover:bg-black, dark:text-white.
		// For pre-resolution we resolve the *base utility* so the pipeline has
		// some usable CSS even if responsive/state scoping is not yet modeled.
		// Example: "md:flex" -> "flex".
		if ( str_contains( $class, ':' ) ) {
			$parts = explode( ':', $class );
			$class = (string) end( $parts );
		}

		// Opacity suffix forms: text-white/80, bg-black/20.
		// For baseline mapping we resolve the color token and ignore opacity.
		if ( str_contains( $class, '/' ) && ! str_contains( $class, '[' ) ) {
			$class = (string) explode( '/', $class, 2 )[0];
		}

		// Handle Tailwind negative utilities e.g. "-mt-4" by stripping leading '-'
		// for baseline mapping attempts. (A richer resolver can re-apply sign.)
		$negative = false;
		if ( str_starts_with( $class, '-' ) ) {
			$negative = true;
			$class = ltrim( $class, '-' );
		}

		$map = SimulationKnowledge::tailwind_map();
		if ( isset( $map[ $class ] ) ) {
			$decl = (string) $map[ $class ];
			if ( $negative && preg_match( '/^(?:margin|top|right|bottom|left|translate|inset|gap)-/i', $class ) ) {
				// Best-effort: if we ever add signed spacing translations to map,
				// this hook can reapply negatives. For now we just return mapping.
				return $decl;
			}
			return $decl;
		}

		// Arbitrary syntax: text-[120px], w-[72rem], bg-[#c8ff00]
		if ( preg_match( '/^([a-z-]+)-\[(.+)\]$/', $class, $m ) ) {
			$prefix = $m[1];
			$value  = $m[2];
			$prefix_map = [
				'text' => 'font-size:%s',
				'w'    => 'width:%s',
				'h'    => 'height:%s',
				'bg'   => 'background-color:%s',
				'px'   => 'padding-left:%1$s;padding-right:%1$s',
				'py'   => 'padding-top:%1$s;padding-bottom:%1$s',
				'gap'  => 'gap:%s',
			];
			if ( isset( $prefix_map[ $prefix ] ) ) {
				$decl = sprintf( $prefix_map[ $prefix ], $value );
				if ( $negative && preg_match( '/^(?:margin|top|right|bottom|left)/i', $decl ) ) {
					// Best-effort only; do not attempt to negate arbitrary values.
					return $decl;
				}
				return $decl;
			}
		}

		return null;
	}
}


<?php
/**
 * Converter V2 — Native Components Strategy.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Converter;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ConverterV2
 *
 * V2 strategy: maximum editability via native Elementor widgets.
 * Every editable section becomes native Heading/Text/Button widgets.
 * HTML widgets only for non-editable visual/animated elements.
 * Intended for client-maintained sites.
 */
class ConverterV2 {
	/**
	 * Section types that are generally behavior-heavy and usually better preserved.
	 *
	 * @var string[]
	 */
	private array $force_html_types = [ 'video', 'slider', 'logos', 'newsletter' ];

	/**
	 * In V2, only preserve unresolved payloads for genuinely behavior-locked types.
	 *
	 * @var string[]
	 */
	private array $preservation_allowed_types = [ 'marquee', 'stats' ];

	public function should_force_html_type( string $type ): bool {
		return in_array( $type, $this->force_html_types, true );
	}

	public function allows_payload_preservation( string $type ): bool {
		return in_array( $type, $this->preservation_allowed_types, true );
	}
}

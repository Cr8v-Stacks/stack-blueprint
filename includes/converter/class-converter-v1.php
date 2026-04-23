<?php
/**
 * Converter V1 — HTML Widget Strategy.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Converter;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class ConverterV1
 *
 * V1 strategy: maximum visual fidelity via HTML widgets.
 * Complex/animated sections are preserved as self-contained HTML widgets.
 * Intended for developer-maintained sites.
 */
class ConverterV1 {
	/**
	 * V1 is fidelity-first and keeps more full HTML sections.
	 *
	 * @var string[]
	 */
	private array $force_html_types = [ 'video', 'slider', 'logos', 'gallery', 'contact', 'newsletter' ];

	public function should_force_html_type( string $type ): bool {
		return in_array( $type, $this->force_html_types, true );
	}

	public function allows_payload_preservation( string $type ): bool {
		return true;
	}
}

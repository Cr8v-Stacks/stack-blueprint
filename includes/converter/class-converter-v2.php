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
	// Logic is handled via ApiClient system prompts.
	// Future: structured HTML analysis, section-by-section conversion pipeline.
}

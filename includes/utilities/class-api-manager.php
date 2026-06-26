<?php
/**
 * API Manager.
 * Routes conversions to the correct AI provider and handles prompt engineering and JSON repair.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Utilities;

use StackBlueprint\Utilities\Providers\Provider_Anthropic;
use StackBlueprint\Utilities\Providers\Provider_OpenAI;
use StackBlueprint\Utilities\Providers\Provider_Gemini;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) exit;

class ApiManager {

	private $provider;

	public function __construct( string $provider_name = 'anthropic' ) {
		switch ( $provider_name ) {
			case 'openai':
				$key   = get_option( 'sb_openai_key', '' );
				$model = get_option( 'sb_openai_model', 'gpt-4o' );
				$this->provider = new Provider_OpenAI( $key, $model );
				break;
			case 'gemini':
				$key   = get_option( 'sb_gemini_key', '' );
				$model = get_option( 'sb_gemini_model', 'gemini-1.5-pro' );
				$this->provider = new Provider_Gemini( $key, $model );
				break;
			case 'anthropic':
			default:
				$key   = get_option( 'sb_api_key', '' );
				$model = get_option( 'sb_api_model', 'claude-3-5-sonnet-20241022' );
				$this->provider = new Provider_Anthropic( $key, $model );
				break;
		}
	}

	public function test_connection(): string|WP_Error {
		return $this->provider->test_connection();
	}

	public function convert( string $html, array $params, string $strategy ): array|WP_Error {
		$system = $strategy === 'v1' ? $this->system_v1( $params ) : $this->system_v2( $params );
		$prompt = $this->build_prompt( $html, $params, $strategy );

		$parsed = $this->provider->call_api( $system, $prompt );
		
		// If initial parse fails (but we got a response from the provider, maybe bad JSON), retry once.
		if ( is_wp_error( $parsed ) && $parsed->get_error_code() === 'json_error' ) {
			$raw_failed = $parsed->get_error_data('raw');
			$retry_prompt = $this->build_repair_prompt( $raw_failed, $parsed->get_error_message(), $params );
			$retry = $this->provider->call_api( $system, $retry_prompt );
			if ( ! is_wp_error( $retry ) ) {
				$parsed = $retry;
			}
		}

		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}

		// PHP Auto-repair and Structure Enforcement
		$parsed['json_template'] = $this->repair_template( $parsed['json_template'] );
		$parsed['json_output']   = wp_json_encode( $parsed['json_template'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
		$parsed['css_output']    = $parsed['companion_css'] ?? '';
		
		return $parsed;
	}

	private function build_prompt( string $html, array $params, string $strategy ): string {
		$name   = sanitize_text_field( $params['project_name'] ?? 'my-project' );
		$prefix = sanitize_key( $params['prefix'] ?? 'sb' );
		
		$strat_note = $strategy === 'v1'
			? 'V1 (HTML Widget): preserve animated/complex sections as self-contained HTML widgets for maximum visual fidelity.'
			: 'V2 (Native Components): every editable element MUST become a native Elementor widget.';

		return <<<PROMPT
Convert the following HTML prototype to an Elementor template.

Project name: {$name}
CSS class prefix to use everywhere: {$prefix}-
Strategy: {$strat_note}

HTML PROTOTYPE:
```html
{$html}
```

CRITICAL INSTRUCTIONS:
1. Extract the EXACT literal text from the HTML. DO NOT use placeholders like {{heading}} or {{text}}.
2. Respond ONLY with valid JSON matching EXACTLY this structure. No preamble, no markdown formatting.
{
  "json_template": {
    "version": "0.4",
    "title": "{$name}",
    "type": "page",
    "content": [ /* array of Elementor container objects */ ],
    "page_settings": { "background_background": "classic", "hide_title": "yes" }
  },
  "companion_css": "/* complete CSS string */",
  "class_map": [ { "class": "...", "element": "...", "location": "..." } ],
  "warnings": [ "...conversion notes..." ]
}
PROMPT;
	}

	private function build_repair_prompt( string $failed_json, string $error, array $params ): string {
		return <<<PROMPT
The previous response contained invalid JSON. Error: {$error}

Here is the failed response:
{$failed_json}

Fix ONLY the JSON syntax errors and return the corrected JSON. The structure must match:
{
  "json_template": { ... },
  "companion_css": "...",
  "class_map": [...],
  "warnings": [...]
}
Do not add markdown code fences.
PROMPT;
	}

	private function system_v1( array $params ): string {
		$prefix = sanitize_key( $params['prefix'] ?? 'sb' );
		return <<<SYSTEM
You are an expert Elementor page builder engineer converting HTML/CSS/JS prototypes to valid Elementor 3.x+ JSON templates.

═══ ABSOLUTE RULES ═══
- Extract EXACT content from the HTML. NO placeholders.
- Use ONLY modern Flexbox Container system (elType: "container"). NO legacy Section/Column.
- All element IDs: unique 8-character alphanumeric strings.
- Every container and widget MUST have _css_classes set with "{$prefix}-" prefix.
- isInner MUST be false for top-level content array elements, and true for ALL nested containers.

═══ V1 STRATEGY: HTML WIDGET APPROACH ═══
Goal: maximum visual fidelity.
- Complex or animated elements (canvas, marquee, particles, scripts) MUST become HTML widgets (widgetType: "html").
- Simple static layout + text sections can use native widgets.
- The first element MUST be a Global Setup HTML widget that uses JavaScript to inject Google Fonts, CSS :root variables, and any body-level effects (like canvas particles).

═══ COMPANION CSS MUST INCLUDE ═══
- All extracted CSS styles, fully prefixed with "{$prefix}-".
SYSTEM;
	}

	private function system_v2( array $params ): string {
		$prefix = sanitize_key( $params['prefix'] ?? 'sb' );
		return <<<SYSTEM
You are an expert Elementor page builder engineer converting HTML/CSS/JS prototypes to valid Elementor 3.x+ JSON templates.

═══ ABSOLUTE RULES ═══
- Extract EXACT content from the HTML. NO placeholders.
- Use ONLY modern Flexbox Container system (elType: "container").
- All element IDs: unique 8-character alphanumeric strings.
- Every container and widget MUST have _css_classes set with "{$prefix}-" prefix.
- isInner MUST be false for top-level content array elements, and true for ALL nested containers.

═══ V2 STRATEGY: NATIVE COMPONENTS APPROACH ═══
Goal: maximum editability.
- EVERY piece of editable content MUST become a native Elementor widget (heading, text-editor, button, image).
- HTML widgets are strictly ONLY for non-editable animated effects (canvas, scripts).
- If you see a grid, use container_type: "grid" and map grid_columns_fr.

═══ COMPANION CSS MUST INCLUDE ═══
- All extracted CSS styles, fully prefixed with "{$prefix}-".
SYSTEM;
	}

	private function repair_template( array $template ): array {
		$allowed_widgets = ['heading','text-editor','button','html','image','icon-list','divider','spacer','posts','video','image-gallery'];
		$seen_ids        = [];

		$template['content'] = $this->repair_elements( $template['content'] ?? [], $allowed_widgets, $seen_ids, false );

		if ( empty($template['page_settings']) ) {
			$template['page_settings'] = [ 'hide_title' => 'yes' ];
		}

		return $template;
	}

	private function repair_elements( array $elements, array $allowed, array &$seen, bool $is_inner = false ): array {
		$repaired = [];
		foreach ( $elements as $el ) {
			if ( ! is_array($el) ) continue;

			// Unique ID.
			if ( empty($el['id']) || isset($seen[$el['id']]) ) {
				$el['id'] = bin2hex( random_bytes(4) );
			}
			$seen[$el['id']] = true;

			// Required keys.
			if ( ! isset($el['elType']) )   $el['elType']  = 'widget';
			
			// FIX isInner REGRESSION: Top level is false, ALL others are true.
			$el['isInner'] = $is_inner;
			
			if ( ! isset($el['settings']) ) $el['settings'] = [];
			if ( ! isset($el['elements']) ) $el['elements'] = [];

			if ( $el['elType'] === 'widget' ) {
				if ( empty($el['widgetType']) ) $el['widgetType'] = 'html';
				if ( ! in_array($el['widgetType'], $allowed, true) ) {
					$el['widgetType'] = 'html';
					if ( empty($el['settings']['html']) ) $el['settings']['html'] = '';
				}
			}

			// Dimension array fix
			$dim_props = ['border_width', 'border_radius', 'padding', 'margin'];
			foreach ($dim_props as $dp) {
				if (isset($el['settings'][$dp]) && is_array($el['settings'][$dp])) {
					if (!isset($el['settings'][$dp]['unit'])) $el['settings'][$dp]['unit'] = 'px';
					if (!isset($el['settings'][$dp]['isLinked'])) $el['settings'][$dp]['isLinked'] = false;
				}
			}

			// Recursively repair children. All children are inner.
			$el['elements'] = $this->repair_elements( $el['elements'], $allowed, $seen, true );

			$repaired[] = $el;
		}
		return $repaired;
	}
}

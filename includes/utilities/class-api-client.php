<?php
/**
 * Anthropic API Client.
 *
 * Improvements in this version:
 * - Pre-validation of HTML before API call
 * - Structured output wrapper: { template, companion_css, class_map, warnings }
 * - Token-aware size check (warn if > 60k chars, split recommendation)
 * - Much richer system prompts based on architecture documentation
 * - Auto-retry with targeted error correction on JSON parse failure
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Utilities;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) exit;

class ApiClient {

	private const ANTHROPIC_API_URL = 'https://api.anthropic.com/v1/messages';
	private const ANTHROPIC_VERSION = '2023-06-01';

	/** Approximate character threshold before chunking is recommended. */
	private const CHUNK_THRESHOLD = 80000;

	private string $api_key;
	private string $model;

	public function __construct( string $api_key = '', string $model = '' ) {
		$this->api_key = $api_key ?: (string) get_option( 'sb_api_key', '' );
		$this->model   = $model   ?: (string) get_option( 'sb_api_model', 'claude-sonnet-4-20250514' );
	}

	// ── Public Conversion Methods ─────────────────────────────

	public function test_connection(): string|WP_Error {
		$r = $this->call( 'Respond with exactly: {"status":"ok"}', 50 );
		return is_wp_error($r) ? $r : $this->model;
	}

	public function convert_v1( string $html, array $params ): array|WP_Error {
		return $this->run_conversion( $html, $params, 'v1' );
	}

	public function convert_v2( string $html, array $params ): array|WP_Error {
		return $this->run_conversion( $html, $params, 'v2' );
	}

	// ── Pre-Validation ────────────────────────────────────────

	/**
	 * Run pre-conversion checks and return a report.
	 *
	 * @return array { valid: bool, warnings: string[], info: array }
	 */
	public function pre_validate( string $html ): array {
		$warnings = [];
		$info     = [];

		$len = strlen( $html );
		$info['size_chars'] = $len;
		$info['size_kb']    = round( $len / 1024, 1 );

		// Size warning.
		if ( $len > self::CHUNK_THRESHOLD ) {
			$warnings[] = "HTML is large ({$info['size_kb']} KB). For best results, split into sections or use the Native Converter for very large files.";
		}

		// Framework detection.
		if ( str_contains($html,'tailwind') || str_contains($html,'tw-') ) {
			$warnings[] = 'Tailwind CSS detected. Class names carry no semantic meaning — conversion uses structural analysis instead.';
			$info['framework'] = 'tailwind';
		} elseif ( preg_match('/bootstrap|container-fluid|col-md-|col-lg-/', $html) ) {
			$warnings[] = 'Bootstrap CSS detected. Grid class names will be ignored; layout is inferred from structure.';
			$info['framework'] = 'bootstrap';
		} elseif ( preg_match('/sc-[a-z0-9]{6,}|css-[a-z0-9]{6,}/', $html) ) {
			$warnings[] = 'CSS-in-JS obfuscated class names detected (Styled Components or Emotion). Conversion relies entirely on DOM structure.';
			$info['framework'] = 'css-in-js';
		}

		// Already an Elementor page.
		if ( str_contains($html,'elementor-section') || str_contains($html,'e-con') || str_contains($html,'data-element_type') ) {
			$warnings[] = 'This HTML appears to already be an Elementor page. Consider using Elementor\'s export instead.';
		}

		// External CSS references (will be unresolvable).
		preg_match_all('/<link[^>]+\.css/', $html, $ext_css);
		if ( ! empty($ext_css[0]) ) {
			$warnings[] = 'External CSS files detected (' . count($ext_css[0]) . '). These cannot be fetched by the converter. If critical styles are missing, inline your CSS into the HTML file.';
		}

		// GSAP.
		if ( str_contains($html,'gsap.') || str_contains($html,'TweenMax') || str_contains($html,'ScrollMagic') ) {
			$warnings[] = 'GSAP / ScrollMagic animation library detected. JS animations will be preserved as HTML widgets but require GSAP to be loaded globally on the WordPress site to function.';
		}

		// No semantic sections at all.
		if ( ! preg_match('/<(section|header|footer|main|article)/i', $html) ) {
			$warnings[] = 'No semantic sectioning tags (section, header, footer, main) detected. Section detection will rely on div structure — results may be less accurate.';
		}

		return [
			'valid'    => true,
			'warnings' => $warnings,
			'info'     => $info,
		];
	}

	// ── Core Conversion ───────────────────────────────────────

	private function run_conversion( string $html, array $params, string $strategy ): array|WP_Error {
		$system = $strategy === 'v1'
			? $this->system_v1( $params )
			: $this->system_v2( $params );

		$prompt = $this->build_prompt( $html, $params, $strategy );

		$response = $this->call_with_system( $system, $prompt, 16000 );
		if ( is_wp_error($response) ) return $response;

		$parsed = $this->parse_response( $response['text'] );

		// Auto-retry with targeted error correction.
		if ( is_wp_error($parsed) ) {
			$retry_prompt = $this->build_repair_prompt( $response['text'], $parsed->get_error_message(), $params );
			$retry        = $this->call_with_system( $system, $retry_prompt, 16000 );
			if ( ! is_wp_error($retry) ) {
				$parsed = $this->parse_response( $retry['text'] );
			}
		}

		return $parsed;
	}

	private function build_prompt( string $html, array $params, string $strategy ): string {
		$name    = sanitize_text_field( $params['project_name'] ?? 'my-project' );
		$prefix  = sanitize_key( $params['prefix'] ?? 'sb' );
		$strat_note = $strategy === 'v1'
			? 'V1 (HTML Widget): preserve animated/complex sections as self-contained HTML widgets for maximum visual fidelity.'
			: 'V2 (Native Components): every editable element → native Elementor widget. HTML widgets only for non-editable animated effects.';

		return <<<PROMPT
Convert the following HTML prototype to an Elementor template.

Project name: {$name}
CSS class prefix: {$prefix}-
Strategy: {$strat_note}

HTML PROTOTYPE:
```html
{$html}
```

Respond ONLY with valid JSON in EXACTLY this structure — no markdown, no preamble, no explanation:
{
  "json_template": {
    "version": "0.4",
    "title": "{$name}",
    "type": "page",
    "content": [ ...array of Elementor container and widget objects... ],
    "page_settings": { "background_background": "classic", "background_color": "...", "hide_title": "yes" }
  },
  "companion_css": "...complete CSS string...",
  "class_map": [ { "class": "...", "element": "...", "location": "..." } ],
  "warnings": [ "...conversion notes..." ]
}

The response must be parseable by JSON.parse() with zero preprocessing. Do not wrap in markdown code fences.
PROMPT;
	}

	private function build_repair_prompt( string $failed_json, string $error, array $params ): string {
		$prefix = sanitize_key( $params['prefix'] ?? 'sb' );
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

All class names must use prefix "{$prefix}-". Do not change the content — only fix JSON syntax.
PROMPT;
	}

	// ── System Prompts ────────────────────────────────────────

	private function system_v1( array $params ): string {
		$prefix  = sanitize_key( $params['prefix'] ?? 'sb' );
		$name    = sanitize_text_field( $params['project_name'] ?? 'my-project' );

		return <<<SYSTEM
You are an expert Elementor page builder engineer converting HTML/CSS/JS prototypes to valid Elementor 3.x+ JSON templates.

═══ ABSOLUTE RULES ═══
- Use ONLY modern Flexbox Container system (elType: "container"). NO legacy Section/Column.
- All element IDs: unique 8-character alphanumeric strings (e.g. "a1b2c3d4").
- Every container and widget: _css_classes set with "{$prefix}-" prefix.
- Major sections: _element_id for anchor navigation.
- isInner: false for top-level sections. isInner: true for ALL nested containers.
- JSON must be valid — no trailing commas, no comments, no markdown.

═══ V1 STRATEGY: HTML WIDGET APPROACH ═══
Goal: maximum visual fidelity. Preserve the original design as precisely as possible.

ALWAYS HTML widget:
- Animated elements (CSS animation, @keyframes, requestAnimationFrame)
- Particle canvas backgrounds (inject via document.createElement('canvas') + body.appendChild)
- Custom cursor overlays
- Marquee/ticker strips
- Count-up statistics (requires JS IntersectionObserver)
- Orbital / pulsing ring visuals
- Bento grids with grid-row: span (Elementor editor doesn't reliably support explicit grid placement)
- Terminal / pipeline bar / code demo components
- Any section containing <script> tags

NATIVE widgets ONLY for sections that are purely static layout + text:
- Simple text + heading + button sections with no animation
- Standard pricing cards (no animated effects)
- Footer (if no JS)

═══ GLOBAL SETUP HTML WIDGET (first element, widgetType: "html") ═══
Must use JavaScript + document.body.appendChild() to inject:
1. Google Fonts <link> tag for ALL fonts in the prototype
2. CSS :root { } with ALL design tokens from the source
3. Particle canvas (createElement + body.appendChild) — NOT inline in widget DOM
4. Custom cursor elements (body.appendChild dot + ring)
5. body::after noise overlay via CSS
6. IIFE containing: cursor animation, particle system (130 particles + connection lines), 
   scroll reveal IntersectionObserver (threshold 0.12, class "{$prefix}-reveal" → "{$prefix}-visible"),
   nav scroll listener (scrollY > 60 → "{$prefix}-scrolled" class on nav)

═══ NAV HTML WIDGET (second element) ═══
- position: fixed, top/left/right: 0, z-index: 1000
- Default: transparent background
- On scroll: frosted glass (backdrop-filter + rgba background)
- Extract actual links, logo text, CTA from source HTML
- Fully self-contained styles and JS

═══ COMPANION CSS MUST INCLUDE ═══
1. Full class map as comment header: /* .{$prefix}-hero-headline → Hero H1 → Advanced tab → CSS Classes */
2. All CSS tokens as :root custom properties
3. Hero headline: font-size: clamp(64px, 9vw, 140px), letter-spacing, line-height
4. Italic outline text (-webkit-text-stroke on em tags)
5. All hover states and transitions
6. All ::before/::after pseudo-elements
7. Button variants: primary (acid), dark (ink), ghost (transparent), price variants
8. Scroll reveal utility classes
9. Responsive at 1024px and 768px

═══ REQUIRED SETTINGS ON ALL ELEMENTS ═══
Containers must include: background_background, background_color, border_border, border_color, border_width ({unit:"px",top:"1",right:"1",bottom:"1",left:"1",isLinked:false}), padding ({unit:"px",top:"1",right:"1",bottom:"1",left:"1",isLinked:false}), gap, flex_direction, align_items, justify_content as applicable.
Heading widgets must include: typography_typography: "custom", typography_font_family, typography_font_weight, typography_font_size (unit+size object), typography_letter_spacing, typography_line_height, title_color.
Button widgets must include: background_color, button_text_color, border_radius ({unit:"px",top:"0",right:"0",bottom:"0",left:"0",isLinked:false}), padding ({unit:"px",top:"18",right:"36",bottom:"18",left:"36",isLinked:false}), typography settings.
SYSTEM;
	}

	private function system_v2( array $params ): string {
		$prefix = sanitize_key( $params['prefix'] ?? 'sb' );
		$name   = sanitize_text_field( $params['project_name'] ?? 'my-project' );

		return <<<SYSTEM
You are an expert Elementor page builder engineer converting HTML/CSS/JS prototypes to Elementor 3.x+ JSON templates optimised for maximum client editability.

═══ ABSOLUTE RULES ═══
- Use ONLY modern Flexbox Container system (elType: "container"). NO legacy Section/Column.
- All element IDs: unique 8-character alphanumeric strings.
- Every element: _css_classes with "{$prefix}-" prefix.
- Major sections: _element_id set.
- isInner: false for top-level content array elements. isInner: true for ALL nested containers.
- JSON must be strictly valid — parseable by JSON.parse() with no preprocessing.

═══ V2 STRATEGY: NATIVE COMPONENTS APPROACH ═══
Goal: maximum editability. Every piece of client-editable content uses native Elementor widgets.

NATIVE WIDGETS for all editable content:
- widgetType: "heading" for all h1–h6 content
- widgetType: "text-editor" for all paragraph/body text
- widgetType: "button" for all CTAs
- Native inner containers for card grids (testimonials, pricing, process steps, team)
- Elementor Grid container (container_type: "grid") for bento-style grids

HTML WIDGETS only for non-editable visual/animated effects:
- Global Setup: canvas, cursor, fonts, CSS vars, scroll reveal JS (always first)
- Fixed nav bar (requires JS scroll listener)
- Marquee/ticker strip
- Count-up statistics (requires JS IntersectionObserver)
- Bento grid HTML (when grid-row: span is needed — Elementor editor can't reliably set these)
- Orbital / pulsing ring / animated visual diagrams
- Terminal / pipeline bar components
- Any element requiring body-level DOM manipulation

═══ GRID CONTAINERS ═══
For any CSS grid layout:
- Add container_type: "grid" to the container settings
- Use grid_columns_fr: e.g. "5fr 4fr 3fr" for asymmetric columns, "1fr 1fr 1fr" for equal
- For bento cards with explicit placement: add grid_column_start, grid_column_end, grid_row_start, grid_row_end to each card container settings
- gap: { "unit": "px", "size": 16, "column": 16, "row": 16 }

═══ CSS CLASS NAMING (all with prefix "{$prefix}-") ═══
Sections:        {$prefix}-hero, {$prefix}-features, {$prefix}-process, {$prefix}-testimonials, {$prefix}-pricing, {$prefix}-cta, {$prefix}-footer
Section header:  {$prefix}-section-header, {$prefix}-section-header-left
Section tag:     {$prefix}-section-tag (+ HTML widget: <div class="{$prefix}-section-tag">— LABEL</div>)
Section title:   {$prefix}-section-title
Section desc:    {$prefix}-section-desc
Hero:            {$prefix}-hero-headline, {$prefix}-hero-sub, {$prefix}-hero-bottom, {$prefix}-hero-actions, {$prefix}-eyebrow-widget
Buttons:         {$prefix}-btn-primary, {$prefix}-btn-dark, {$prefix}-btn-outline-dark, {$prefix}-btn-ghost, {$prefix}-btn-price, {$prefix}-btn-price-dark
Cards:           {$prefix}-bento-card, {$prefix}-testi-card, {$prefix}-price-card, {$prefix}-price-featured
Card content:    {$prefix}-card-tag, {$prefix}-card-title, {$prefix}-card-body, {$prefix}-bento-title
Process:         {$prefix}-process-step, {$prefix}-step-num-widget, {$prefix}-step-content, {$prefix}-step-title, {$prefix}-step-desc
Testimonials:    {$prefix}-testi-quote, {$prefix}-testi-author-widget, {$prefix}-testi-author, {$prefix}-testi-avatar, {$prefix}-testi-name, {$prefix}-testi-role
Pricing:         {$prefix}-price-badge-plan, {$prefix}-price-amount-widget, {$prefix}-price-period-widget, {$prefix}-price-feats-widget, {$prefix}-price-badge
CTA:             {$prefix}-cta-title, {$prefix}-cta-sub, {$prefix}-cta-actions
Footer:          {$prefix}-footer-grid, {$prefix}-footer-brand-col, {$prefix}-footer-nav-col, {$prefix}-footer-logo-widget, {$prefix}-footer-col-title-widget, {$prefix}-footer-links-widget, {$prefix}-footer-bottom-widget
Reveal:          {$prefix}-reveal (add to all major containers + cards), {$prefix}-d1, {$prefix}-d2, {$prefix}-d3 (stagger)

═══ GLOBAL SETUP HTML WIDGET (first element) ═══
Must inject ALL of the following into document.body via JavaScript IIFE:
1. Google Fonts <link> tag — all fonts in the prototype with all required weights
2. :root { } CSS block with ALL design tokens (CSS custom properties)
3. Particle canvas via document.createElement('canvas') + body.appendChild() [NOT inline markup]
4. Custom cursor dot + ring elements via body.appendChild()
5. body::after noise overlay (CSS in the <style> block)
6. IntersectionObserver scroll reveal (threshold 0.12) → adds "{$prefix}-visible" to "{$prefix}-reveal" elements
7. Nav scroll listener: window.scrollY > 60 → adds "{$prefix}-scrolled" to nav element

═══ REQUIRED SETTINGS ON ALL ELEMENTS ═══
Containers: background_background ("classic"), background_color, border_border, border_color, border_width ({unit:"px",top:"1",right:"1",bottom:"1",left:"1",isLinked:false}), padding ({unit:"px",top:"0",right:"0",bottom:"0",left:"0",isLinked:false}), gap (unit+size+column+row), flex_direction, align_items, justify_content.
Heading widgets: typography_typography ("custom"), typography_font_family, typography_font_weight, typography_font_size ({unit:"px",size:N}), typography_letter_spacing ({unit:"px" or "em",size:N}), typography_line_height ({unit:"em",size:N}), title_color (hex/rgba).
Button widgets: background_color, button_text_color, border_radius ({unit:"px",top:"0",right:"0",bottom:"0",left:"0",isLinked:false}), padding ({unit:"px",top:"18",right:"36",bottom:"18",left:"36",isLinked:false}), typography_typography, typography_font_family, typography_font_weight, typography_font_size.

═══ COMPANION CSS MUST INCLUDE ═══
1. Full class map comment header
2. :root { } with all CSS custom properties + {$prefix}-prefixed vars
3. body background + z-index stack overrides
4. Scroll reveal + stagger utilities
5. Per-section styles IN SECTION ORDER matching the JSON output
6. Hero: clamp() font size, -webkit-text-stroke on em, accent colour on .acid-word / span[class]
7. All button variants (primary, dark, outline-dark, ghost, price, price-dark) — hover states
8. Card hover effects (translateY, border glow)
9. Process step hover (step-num colour, step-title colour)
10. Testimonial author block (flex, avatar, name, role)
11. Pricing: badge (absolute), amount (large display font), feats list, featured card overrides
12. CTA: ::before watermark, child z-index override
13. Footer: logo, col-title, links, bottom bar, status dot animation
14. Responsive: 1024px and 768px breakpoints
SYSTEM;
	}

	// ── Response Parsing ──────────────────────────────────────

	private function parse_response( string $raw ): array|WP_Error {
		// Strip markdown fences.
		$clean = trim( preg_replace('/^```(json)?\s*/m','', preg_replace('/```\s*$/m','',$raw)) );

		// Try direct parse.
		$data = json_decode( $clean, true );

		// Try to extract JSON object if surrounded by other text.
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			if ( preg_match( '/\{.*\}/s', $clean, $m ) ) {
				$data = json_decode( $m[0], true );
			}
		}

		if ( json_last_error() !== JSON_ERROR_NONE || ! is_array($data) ) {
			return new WP_Error( 'parse_error',
				__( 'AI returned invalid JSON. Error: ', 'stack-blueprint' ) . json_last_error_msg()
			);
		}

		// Validate required structure.
		if ( empty($data['json_template']) || ! is_array($data['json_template']) ) {
			return new WP_Error( 'missing_template',
				__( 'AI response missing json_template. Try again or simplify your prototype.', 'stack-blueprint' )
			);
		}

		if ( empty($data['json_template']['content']) ) {
			return new WP_Error( 'empty_template',
				__( 'AI generated an empty template (no sections). Try again with a different prototype.', 'stack-blueprint' )
			);
		}

		// Validate and repair JSON (Pass 9).
		$template    = $this->repair_template( $data['json_template'] );
		$json_output = wp_json_encode( $template, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );

		return [
			'json_template' => $template,
			'json_output'   => $json_output,
			'css_output'    => is_string($data['companion_css'] ?? '') ? $data['companion_css'] : '',
			'class_map'     => is_array($data['class_map'] ?? null) ? $data['class_map'] : [],
			'warnings'      => is_array($data['warnings'] ?? null) ? $data['warnings'] : [],
		];
	}

	/**
	 * Repair common issues in AI-generated Elementor JSON.
	 */
	private function repair_template( array $template ): array {
		$allowed_widgets = ['heading','text-editor','button','html','image','icon-list','divider','spacer','posts','video','image-gallery'];
		$seen_ids        = [];

		$template['content'] = $this->repair_elements( $template['content'] ?? [], $allowed_widgets, $seen_ids );

		// Ensure page_settings exists.
		if ( empty($template['page_settings']) ) {
			$template['page_settings'] = [ 'hide_title' => 'yes' ];
		}

		return $template;
	}

	private function repair_elements( array $elements, array $allowed, array &$seen ): array {
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
			if ( ! isset($el['isInner']) )  $el['isInner'] = false;
			if ( ! isset($el['settings']) ) $el['settings'] = [];
			if ( ! isset($el['elements']) ) $el['elements'] = [];

			// Whitelist widget types.
			if ( $el['elType'] === 'widget' ) {
				if ( empty($el['widgetType']) ) $el['widgetType'] = 'html';
				if ( ! in_array($el['widgetType'], $allowed, true) ) {
					$el['widgetType'] = 'html';
					if ( empty($el['settings']['html']) ) $el['settings']['html'] = '';
				}
			}

			// Ensure _css_classes is a string.
			if ( isset($el['settings']['_css_classes']) && ! is_string($el['settings']['_css_classes']) ) {
				$el['settings']['_css_classes'] = '';
			}

			// Deduplicate _element_id.
			if ( ! empty($el['settings']['_element_id']) ) {
				$eid = $el['settings']['_element_id'];
				if ( isset($seen['eid_' . $eid]) ) {
					// Append random string to deduplicate
					$el['settings']['_element_id'] = $eid . '-' . bin2hex(random_bytes(2));
				}
				$seen['eid_' . $el['settings']['_element_id']] = true;
			}

			// Dimension array fix: if AI forgot 'unit' or 'isLinked' on dimensions, inject them
			$dim_props = ['border_width', 'border_radius', 'padding', 'margin'];
			foreach ($dim_props as $dp) {
				if (isset($el['settings'][$dp]) && is_array($el['settings'][$dp])) {
					if (!isset($el['settings'][$dp]['unit'])) $el['settings'][$dp]['unit'] = 'px';
					if (!isset($el['settings'][$dp]['isLinked'])) $el['settings'][$dp]['isLinked'] = false;
				}
			}

			// Recursively repair children.
			$el['elements'] = $this->repair_elements( $el['elements'], $allowed, $seen );

			$repaired[] = $el;
		}
		return $repaired;
	}

	// ── Raw API Calls ─────────────────────────────────────────

	public function call( string $prompt, int $max_tokens = 4000 ): array|WP_Error {
		return $this->call_with_system( '', $prompt, $max_tokens );
	}

	public function call_with_system( string $system, string $prompt, int $max_tokens = 4000 ): array|WP_Error {
		if ( empty($this->api_key) ) {
			return new WP_Error( 'no_api_key', __( 'Anthropic API key is not configured. Go to Settings to add your key.', 'stack-blueprint' ) );
		}

		$body = [
			'model'      => $this->model,
			'max_tokens' => $max_tokens,
			'messages'   => [['role'=>'user','content'=>$prompt]],
		];
		if ( ! empty($system) ) $body['system'] = $system;

		$response = wp_remote_post( self::ANTHROPIC_API_URL, [
			'timeout' => (int) get_option( 'sb_conversion_timeout', 120 ),
			'headers' => [
				'Content-Type'      => 'application/json',
				'x-api-key'         => $this->api_key,
				'anthropic-version' => self::ANTHROPIC_VERSION,
			],
			'body' => wp_json_encode( $body ),
		]);

		if ( is_wp_error($response) ) {
			return new WP_Error( 'api_request_failed', $response->get_error_message() );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( $code !== 200 ) {
			$msg = $data['error']['message'] ?? __( 'Unknown API error.', 'stack-blueprint' );
			return new WP_Error( 'api_error_' . $code, $msg );
		}

		$text = '';
		foreach ( ($data['content'] ?? []) as $block ) {
			if ( 'text' === ($block['type'] ?? '') ) $text .= $block['text'];
		}

		return [
			'text'          => $text,
			'input_tokens'  => $data['usage']['input_tokens'] ?? 0,
			'output_tokens' => $data['usage']['output_tokens'] ?? 0,
			'model'         => $data['model'] ?? $this->model,
		];
	}
}

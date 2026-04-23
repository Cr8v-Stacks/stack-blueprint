<?php
/**
 * Native Offline Converter — Full 9-Pass Pipeline
 *
 * Implements the complete agentic conversion pipeline described in the
 * Stack Blueprint architecture documentation:
 *
 * Pass 1: Document Intelligence (PassDocumentIntelligence)
 * Pass 2: Layout Analysis (per-section display type detection via CssResolver)
 * Pass 3: Content Classification (widget decision tree with confidence + editability)
 * Pass 4: Style Resolution (CSS cascade via CssResolver, companion CSS collection)
 * Pass 5: Class & ID Generation (prefix-namespaced, deterministic)
 * Pass 6: Global Setup Synthesis (fonts, vars, cursor, canvas, scroll reveal)
 * Pass 7: JSON Assembly (TemplateLibrary patterns → fallback general converters)
 * Pass 8: Companion CSS Generation (design system + per-element rules + responsive)
 * Pass 9: Validation & Repair (JSON parse check, ID uniqueness, required-key audit)
 *
 * KEY PRINCIPLE: Use one root namespace for isolation, but preserve and
 * retarget source selectors wherever practical. We should not invent a
 * second noisy descendant selector universe when the source already has
 * a usable styling contract.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Converter;

require_once __DIR__ . '/helpers/class-script-bridge-helper.php';
require_once __DIR__ . '/helpers/class-v2-decision-helper.php';
require_once __DIR__ . '/helpers/class-v2-hybrid-preserve-helper.php';
require_once __DIR__ . '/helpers/class-v2-primitive-assembler-helper.php';

use StackBlueprint\Converter\ConverterV1;
use StackBlueprint\Converter\ConverterV2;
use StackBlueprint\Converter\Passes\PassDocumentIntelligence;
use StackBlueprint\Converter\Generated\SimulationKnowledge;
use StackBlueprint\Converter\Helpers\ScriptBridgeHelper;
use StackBlueprint\Converter\Helpers\V2DecisionHelper;
use StackBlueprint\Converter\Helpers\V2HybridPreserveHelper;
use StackBlueprint\Converter\Helpers\V2PrimitiveAssemblerHelper;
use StackBlueprint\Converter\Skills\PriorityRulesEngine;
use StackBlueprint\Converter\Skills\TailwindResolver;
use StackBlueprint\Utilities\Helpers;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) exit;

class NativeConverter {

	// ── Config ────────────────────────────────────────────────
	private string $prefix       = 'sb';
	private string $project_name = 'my-project';
	private string $strategy     = 'v2';

	// ── Pipeline dependencies ─────────────────────────────────
	private PassDocumentIntelligence $intel;
	private CssResolver              $css;
	private TemplateLibrary          $lib;
	private PriorityRulesEngine      $priority_rules;
	private TailwindResolver         $tailwind_resolver;
	private HtmlParser               $html_parser;

	// ── Resolved design tokens ────────────────────────────────
	private string $c_bg      = '#0a0a12';
	private string $c_accent  = '#c8ff00';
	private string $c_text    = '#f5f3ee';
	private string $c_surface = 'rgba(255,255,255,0.03)';
	private string $c_border  = 'rgba(255,255,255,0.1)';
	private string $f_display = 'Syne';
	private string $f_body    = 'DM Sans';
	private string $f_mono    = 'Space Mono';

	// ── Output accumulators ───────────────────────────────────
	private array  $elements      = [];
	private array  $warnings      = [];
	private array  $class_map     = [];
	private array  $companion_rules = []; // Per-class CSS rule strings.
	private array  $seen_ids      = [];   // For uniqueness validation (Pass 9).
	private array  $diagnostics   = [];
	private array  $detected_section_types = [];
	private array  $built_section_types    = [];
	private array  $section_payloads       = [];
	private array  $emitted_hook_inventory = [ 'classes' => [], 'ids' => [] ];
	private array  $global_setup_asset_inventory = [];
	private array  $companion_css_coverage = [];
	private array  $source_selector_bridge_coverage = [];
	private array  $source_script_bridge_coverage = [];
	private array  $native_widget_semantic_coverage = [];
	private array  $tailwind_coverage = [];
	private int    $hidden_skip_log_count = 0;
	private bool   $strategy_policy_emitted = false;
	private ConverterV1|ConverterV2|null $strategy_profile = null;

	// ═══════════════════════════════════════════════════════════
	// PUBLIC ENTRY POINT
	// ═══════════════════════════════════════════════════════════

	private function update_pass( array $params, int $pass ) {
		if ( ! empty( $params['tx_id'] ) ) {
			set_transient( 'sb_tx_' . sanitize_key( $params['tx_id'] ), $pass, 60 );
		}
	}

	private function default_widget_semantic_coverage(): array {
		return [
			'icon-list' => [ 'source' => false, 'output' => false, 'pseudo_source' => false, 'pseudo_output' => false ],
			'text'      => [ 'source' => false, 'output' => false, 'pseudo_source' => false, 'pseudo_output' => false ],
			'heading'   => [ 'source' => false, 'output' => false, 'pseudo_source' => false, 'pseudo_output' => false ],
			'button'    => [ 'source' => false, 'output' => false, 'pseudo_source' => false, 'pseudo_output' => false ],
			'image'     => [ 'source' => false, 'output' => false, 'pseudo_source' => false, 'pseudo_output' => false ],
			'media'     => [ 'source' => false, 'output' => false, 'pseudo_source' => false, 'pseudo_output' => false ],
		];
	}

	public function convert( string $html, array $params = [] ): array|WP_Error {
		$this->project_name = sanitize_text_field( $params['project_name'] ?? 'my-project' );
		$requested_prefix   = isset( $params['prefix'] ) ? (string) $params['prefix'] : null;
		$source_filename    = (string) ( $params['filename'] ?? '' );
		$this->prefix       = Helpers::generate_prefix( $this->project_name, $source_filename, $requested_prefix );
		$this->strategy     = in_array( $params['strategy'] ?? 'v2', ['v1','v2'], true )
			? $params['strategy'] : 'v2';
		$this->strategy_profile = ( 'v1' === $this->strategy ) ? new ConverterV1() : new ConverterV2();
		$this->elements              = [];
		$this->warnings              = [];
		$this->class_map             = [];
		$this->companion_rules       = [];
		$this->seen_ids              = [];
		$this->diagnostics           = [];
		$this->detected_section_types = [];
		$this->built_section_types    = [];
		$this->section_payloads       = [];
		$this->emitted_hook_inventory = [ 'classes' => [], 'ids' => [] ];
		$this->global_setup_asset_inventory = [];
		$this->companion_css_coverage = [];
		$this->source_selector_bridge_coverage = [];
		$this->source_script_bridge_coverage = [];
		$this->native_widget_semantic_coverage = $this->default_widget_semantic_coverage();
		$this->tailwind_coverage = [];
		$this->strategy_policy_emitted = false;
		$this->priority_rules = new PriorityRulesEngine();
		$this->tailwind_resolver = new TailwindResolver();
		$this->html_parser = new HtmlParser();

		if ( ! Helpers::is_safe_prefix( $this->prefix ) ) {
			return $this->fail_conversion(
				'invalid_prefix',
				sprintf( 'Unsafe CSS prefix "%s" generated for this conversion.', $this->prefix ),
				[
					'pass'         => 0,
					'prefix'       => $this->prefix,
					'project_name' => $this->project_name,
				]
			);
		}

		// ── Pass 1: Document Intelligence ──────────────────────
		$this->update_pass( $params, 1 );
		$this->emit_strategy_policy_diagnostic();
		$dom = $this->parse_html( $html );
		if ( is_wp_error( $dom ) ) return $dom;

		$this->intel = new PassDocumentIntelligence();
		$this->intel->run( $dom, $html );
		$is_tailwind = $this->tailwind_resolver->is_tailwind_html( $html );
		$this->diagnostics[] = [
			'code'    => 'tailwind_detection',
			'message' => $is_tailwind
				? 'Tailwind-like utility markup detected.'
				: 'Tailwind-like utility markup not detected.',
			'context' => [
				'pass' => 1,
			],
		];
		if ( $is_tailwind ) {
			$this->apply_tailwind_pre_resolution( $dom );
		}
		$knowledge_error = $this->assert_simulation_knowledge_integrity();
		if ( is_wp_error( $knowledge_error ) ) {
			return $knowledge_error;
		}

		$prototype_error = $this->assert_supported_input_shape( $dom, $html );
		if ( is_wp_error( $prototype_error ) ) {
			return $prototype_error;
		}

		// ── Pass 2: CSS Cascade Resolution ─────────────────────
		$this->update_pass( $params, 2 );
		$this->css = new CssResolver();
		$this->css->parse( $this->intel->raw_css );
		$tailwind_error = $this->assert_tailwind_resolution_integrity();
		if ( is_wp_error( $tailwind_error ) ) {
			return $tailwind_error;
		}

		// Resolve palette and fonts from Pass 1 tokens + CSS.
		$this->resolve_design_system();

		// ── Pass 3–5 happen inside build_elements() per section ─
		$this->update_pass( $params, 3 ); // Marks boundary for 3-5

		// Initialise Template Library with resolved design system.
		$this->lib = new TemplateLibrary([
			'prefix'    => $this->prefix,
			'bg'        => $this->c_bg,
			'accent'    => $this->c_accent,
			'text'      => $this->c_text,
			'surface'   => $this->c_surface,
			'border'    => $this->c_border,
			'f_display' => $this->f_display,
			'f_body'    => $this->f_body,
			'f_mono'    => $this->f_mono,
		]);

		// ── Pass 6: Global Setup Synthesis ─────────────────────
		$this->update_pass( $params, 6 );
		$this->build_global_setup();
		$global_setup_error = $this->assert_global_setup_integrity();
		if ( is_wp_error( $global_setup_error ) ) {
			return $global_setup_error;
		}
		$this->build_nav( $dom );

		// ── Pass 7: JSON Assembly ───────────────────────────────
		$this->update_pass( $params, 7 );
		$this->build_sections( $dom );
		$this->append_source_script_bridge_to_global_setup();

		if ( empty( $this->elements ) ) {
			return new WP_Error( 'no_content', __( 'No sections found. Ensure the HTML has section, header, or footer tags with meaningful content.', 'stack-blueprint' ) );
		}

		// ── Pass 8: Companion CSS Generation ───────────────────
		$this->update_pass( $params, 8 );
		$companion_css = $this->build_companion_css();

		// ── Pass 9: Validation & Repair ─────────────────────────
		$this->update_pass( $params, 9 );
		$repaired = $this->validate_and_repair( $this->elements );
		$integrity_error = $this->assert_output_integrity( $repaired );
		if ( is_wp_error( $integrity_error ) ) {
			return $integrity_error;
		}

		$this->record_conversion_run_report( $repaired, (string) $companion_css );

		$template = [
			'version'       => '0.4',
			'title'         => $this->project_name,
			'type'          => 'page',
			'content'       => $repaired,
			'page_settings' => [
				'background_background' => 'classic',
				'background_color'      => $this->c_bg,
				'hide_title'            => 'yes',
			],
		];

		return [
			'json_template' => $template,
			'json_output'   => wp_json_encode( $template, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ),
			'css_output'    => $companion_css,
			'class_map'     => $this->class_map,
			'warnings'      => $this->warnings,
			'diagnostics'   => $this->diagnostics,
		];
	}

	/**
	 * Pass-ownership conversion run report (single summary artifact per run).
	 *
	 * @param array  $elements Repaired top-level output tree.
	 * @param string $companion_css Emitted companion CSS.
	 */
	private function record_conversion_run_report( array $elements, string $companion_css ): void {
		$render_modes = [];
		$hybrid_added = 0;
		foreach ( (array) $this->section_payloads as $type => $payload ) {
			$mode = (string) ( $payload['render_mode'] ?? '' );
			if ( '' !== $mode ) {
				$render_modes[ $mode ] = ( $render_modes[ $mode ] ?? 0 ) + 1;
			}
			if ( ! empty( $payload['hybrid_fragment_added'] ) ) {
				$hybrid_added++;
			}
		}

		$hybrid_events = [
			'attached_total'      => 0,
			'attached_to_subtree' => 0,
			'fragments_detected'  => 0,
		];
		foreach ( (array) $this->diagnostics as $diag ) {
			if ( ! is_array( $diag ) || ( $diag['code'] ?? '' ) !== 'hybrid_fragment_attached' ) {
				continue;
			}
			$hybrid_events['attached_total']++;
			$ctx = (array) ( $diag['context'] ?? [] );
			if ( ! empty( $ctx['attached_to_subtree'] ) ) {
				$hybrid_events['attached_to_subtree']++;
			}
			$hybrid_events['fragments_detected'] += (int) ( $ctx['fragments_detected'] ?? 0 );
		}

		$this->diagnostics[] = [
			'code'    => 'conversion_run_report',
			'message' => 'Conversion run report recorded (pass ownership summary).',
			'context' => [
				'pass'  => 9,
				'meta'  => [
					'prefix'       => $this->prefix,
					'strategy'     => $this->strategy,
					'project_name' => $this->project_name,
				],
				'sections' => [
					'detected_types' => array_values( array_unique( (array) $this->detected_section_types ) ),
					'built_types'    => array_values( array_unique( (array) $this->built_section_types ) ),
					'render_modes'   => $render_modes,
				],
				'hybrid' => [
					'sections_marked_hybrid' => $hybrid_added,
					'events'                 => $hybrid_events,
				],
				'bridges' => [
					'selector' => (array) $this->source_selector_bridge_coverage,
					'script'   => (array) $this->source_script_bridge_coverage,
				],
				'assets' => [
					'global_setup'  => (array) $this->global_setup_asset_inventory,
					'companion_css' => [
						'bytes' => strlen( $companion_css ),
						'has_pseudo' => (bool) preg_match( '/::(before|after)\s*\{/i', $companion_css ),
						'has_hover'  => (bool) preg_match( '/:hover\b/i', $companion_css ),
					],
				],
				'output' => [
					'top_level_elements' => count( $elements ),
				],
			],
		];
	}

	// ═══════════════════════════════════════════════════════════
	// DESIGN SYSTEM RESOLUTION (Passes 1–2 output)
	// ═══════════════════════════════════════════════════════════

	private function resolve_design_system(): void {
		$tokens = array_merge( $this->css->get_custom_props(), $this->intel->tokens );

		// Resolve semantic colour roles via multiple alias keys.
		$alias = [
			'c_bg'      => ['--void','--bg','--background','--color-bg','--page-bg','--dark'],
			'c_accent'  => ['--acid','--accent','--primary','--brand','--color-accent','--highlight'],
			'c_text'    => ['--paper','--text','--foreground','--color-text','--light','--body-color'],
			'c_surface' => ['--mist','--surface','--card-bg','--panel','--color-surface'],
			'c_border'  => ['--stroke','--border','--color-border'],
		];

		foreach ( $alias as $prop => $keys ) {
			foreach ( $keys as $k ) {
				if ( ! empty( $tokens[$k] ) ) {
					$this->$prop = trim( $tokens[$k] );
					break;
				}
			}
		}

		// Fonts: prefer explicit token roles before falling back to inferred families.
		$font_token_map = [
			'--font-display' => 'f_display',
			'--font-body'    => 'f_body',
			'--font-mono'    => 'f_mono',
		];

		foreach ( $font_token_map as $token_key => $target_prop ) {
			if ( empty( $tokens[ $token_key ] ) ) {
				continue;
			}

			$resolved_family = $this->extract_primary_font_family( $tokens[ $token_key ] );
			if ( $resolved_family ) {
				$this->$target_prop = $resolved_family;
			}
		}

		// Fonts: classify by role using the intel's font map.
		$display_hints = ['syne','playfair','cormorant','fraunces','clash','cabinet','editorial','display'];
		$mono_hints    = ['mono','code','fira','space mono','courier','ibm plex mono','jetbrains','dm mono'];

		$display_set = false; $mono_set = false; $body_set = false;

		foreach ( $this->intel->fonts as $fam => $weights ) {
			$lower = strtolower( $fam );
			if ( ! $display_set && array_reduce( $display_hints, fn($c,$h) => $c || str_contains($lower,$h), false ) ) {
				$this->f_display = $fam; $display_set = true;
			} elseif ( ! $mono_set && array_reduce( $mono_hints, fn($c,$h) => $c || str_contains($lower,$h), false ) ) {
				$this->f_mono = $fam; $mono_set = true;
			} elseif ( ! $body_set ) {
				$this->f_body = $fam; $body_set = true;
			}
		}
	}

	/**
	 * Extract the first real font family from a font-family declaration.
	 *
	 * @param string $value Raw CSS font-family value.
	 * @return string
	 */
	private function extract_primary_font_family( string $value ): string {
		$families = array_map( fn( $family ) => trim( $family, " \"'\t" ), explode( ',', $value ) );

		foreach ( $families as $family ) {
			$lower = strtolower( $family );
			if ( $family && ! in_array( $lower, [ 'sans-serif', 'serif', 'monospace', 'inherit', 'initial', 'unset' ], true ) ) {
				return $family;
			}
		}

		return '';
	}

	// ═══════════════════════════════════════════════════════════
	// HTML PARSING
	// ═══════════════════════════════════════════════════════════

	private function parse_html( string $html ): \DOMDocument|WP_Error {
		$dom = new \DOMDocument( '1.0', 'UTF-8' );
		$dom->preserveWhiteSpace = false;
		libxml_use_internal_errors( true );
		$dom->loadHTML( '<?xml encoding="UTF-8">' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		if ( ! $dom->documentElement ) $dom->loadHTML( $html );
		libxml_clear_errors();
		return $dom;
	}

	// ═══════════════════════════════════════════════════════════
	// PASS 6 — GLOBAL SETUP SYNTHESIS
	// ═══════════════════════════════════════════════════════════

	private function build_global_setup(): void {
		$p   = $this->prefix;
		$bg  = $this->c_bg;
		$ac  = $this->c_accent;
		$tx  = $this->c_text;
		$has_cursor = ! empty( $this->intel->has_cursor );
		$has_canvas = ! empty( $this->intel->has_canvas );

		// Google Fonts link — all detected families with all weights.
		$font_link = '';
		if ( ! empty( $this->intel->fonts ) ) {
			$fams = [];
			foreach ( $this->intel->fonts as $fam => $weights ) {
				$w_str  = implode( ';', array_filter( array_unique( array_merge( [400, 700], $weights ) ) ) );
				$fams[] = urlencode( $fam ) . ':wght@' . $w_str;
			}
			$font_link = '<link rel="preconnect" href="https://fonts.googleapis.com">'
				. '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
				. '<link href="https://fonts.googleapis.com/css2?family=' . implode( '&family=', $fams ) . '&display=swap" rel="stylesheet">';
		}

		// CSS custom properties — all tokens.
		$root_vars = '';
		foreach ( array_merge( $this->css->get_custom_props(), $this->intel->tokens ) as $k => $v ) {
			$root_vars .= "{$k}:{$v};";
		}
		// Always include our prefixed vars.
		$root_vars .= "--{$p}-bg:{$bg};--{$p}-accent:{$ac};--{$p}-text:{$tx};";
		$root_vars .= "--{$p}-surface:{$this->c_surface};--{$p}-border:{$this->c_border};";
		$root_vars .= "--{$p}-fd:'{$this->f_display}',sans-serif;";
		$root_vars .= "--{$p}-fb:'{$this->f_body}',sans-serif;";
		$root_vars .= "--{$p}-fm:'{$this->f_mono}',monospace;";

		// Include any @keyframes from the source that drive animations.
		$keyframes_css = implode( "\n", $this->css->get_keyframes() );

		$cursor_css = $has_cursor ? "body,a,button,.elementor-button{cursor:none!important;}" : '';
		$canvas_overlay_css = $has_canvas ? <<<CSS
body::after{content:'';position:fixed;inset:0;z-index:1;pointer-events:none;
background-image:url("data:image/svg+xml,%3Csvg viewBox='0 0 256 256' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.04'/%3E%3C/svg%3E");opacity:.35;}
CSS : '';
		$cursor_js = $has_cursor ? <<<JS
/* ── Cursor ── */
var dot=document.createElement('div');dot.id='{$p}-cursor-dot';
var ring=document.createElement('div');ring.id='{$p}-cursor-ring';
var dSt='width:8px;height:8px;background:{$ac};border-radius:50%;position:fixed;top:0;left:0;transform:translate(-50%,-50%);pointer-events:none;z-index:99999;';
var rSt='width:36px;height:36px;border:1px solid rgba(200,255,0,.5);border-radius:50%;position:fixed;top:0;left:0;transform:translate(-50%,-50%);pointer-events:none;z-index:99998;transition:all .15s ease;';
dot.style.cssText=dSt;ring.style.cssText=rSt;
JS : '';
		$canvas_js = $has_canvas ? <<<JS
function initParticles(cv){var ctx=cv.getContext('2d'),W,H,pts=[];
function rsz(){W=cv.width=innerWidth;H=cv.height=innerHeight;}rsz();
window.addEventListener('resize',rsz);
function Pt(){this.x=Math.random()*W;this.y=Math.random()*H;this.vx=(Math.random()-.5)*.3;this.vy=(Math.random()-.5)*.3;this.r=Math.random()*1.5+.5;this.a=Math.random()*.4+.1;this.c=Math.random()>.7?'{$ac}':'#ffffff';}
Pt.prototype.tick=function(){this.x+=this.vx;this.y+=this.vy;if(this.x<0||this.x>W||this.y<0||this.y>H){this.x=Math.random()*W;this.y=Math.random()*H;}};
for(var i=0;i<130;i++)pts.push(new Pt());
(function loop(){
ctx.clearRect(0,0,W,H);
for(var i=0;i<pts.length;i++){pts[i].tick();ctx.beginPath();ctx.arc(pts[i].x,pts[i].y,pts[i].r,0,Math.PI*2);ctx.fillStyle=pts[i].c;ctx.globalAlpha=pts[i].a;ctx.fill();}
for(var i=0;i<pts.length;i++){for(var j=i+1;j<pts.length;j++){var dx=pts[i].x-pts[j].x,dy=pts[i].y-pts[j].y,d=Math.sqrt(dx*dx+dy*dy);if(d<100){ctx.beginPath();ctx.moveTo(pts[i].x,pts[i].y);ctx.lineTo(pts[j].x,pts[j].y);ctx.strokeStyle='{$ac}';ctx.globalAlpha=(1-d/100)*.08;ctx.lineWidth=.5;ctx.stroke();}}}
ctx.globalAlpha=1;requestAnimationFrame(loop);
})();
JS : '';
		$asset_injector_js = ( $has_cursor || $has_canvas ) ? <<<JS
function injectAssets(){
  if(document.getElementById('{$p}-cursor-dot') || document.getElementById('{$p}-bg-canvas')) return;
JS : '';
		if ( $has_cursor ) {
			$asset_injector_js .= "\n  document.body.appendChild(dot);document.body.appendChild(ring);";
		}
		if ( $has_canvas ) {
			$asset_injector_js .= "\n  var cv=document.createElement('canvas');cv.id='{$p}-bg-canvas';";
			$asset_injector_js .= "\n  cv.style.cssText='position:fixed;top:0;left:0;width:100%;height:100%;z-index:0;pointer-events:none;opacity:.4;';";
			$asset_injector_js .= "\n  document.body.insertBefore(cv, document.body.firstChild);";
			$asset_injector_js .= "\n  initParticles(cv);";
		}
		if ( $has_cursor || $has_canvas ) {
			$asset_injector_js .= "\n}\nif(document.readyState==='complete'){injectAssets();}else{window.addEventListener('load',injectAssets);}";
		}
		$cursor_motion_js = $has_cursor ? <<<JS
var mx=0,my=0,rx=0,ry=0;
document.addEventListener('mousemove',function(e){mx=e.clientX;my=e.clientY;});
document.addEventListener('mouseover',function(e){if(e.target.closest('a,button')){ring.style.width='52px';ring.style.height='52px';}else{ring.style.width='36px';ring.style.height='36px';}});
(function aC(){dot.style.left=mx+'px';dot.style.top=my+'px';rx+=(mx-rx)*.12;ry+=(my-ry)*.12;ring.style.left=rx+'px';ring.style.top=ry+'px';requestAnimationFrame(aC);})();
JS : '';

		$html = <<<HTML
{$font_link}
<style>
:root{{$root_vars}}
body,.elementor-page,#page,.site{background:{$bg}!important;color:{$tx};font-family:'{$this->f_body}',sans-serif;overflow-x:hidden;}
{$cursor_css}
.elementor-section,.e-con,.elementor-container{position:relative;z-index:2;}
{$canvas_overlay_css}
.{$p}-reveal{opacity:1;transform:none;transition:opacity .7s cubic-bezier(.16,1,.3,1),transform .7s cubic-bezier(.16,1,.3,1);}
html.{$p}-motion-ready .{$p}-reveal{opacity:0;transform:translateY(40px);}
html.{$p}-motion-ready .{$p}-reveal.{$p}-visible{opacity:1;transform:translateY(0);}
.{$p}-d1{transition-delay:.1s;}.{$p}-d2{transition-delay:.2s;}.{$p}-d3{transition-delay:.3s;}
::-webkit-scrollbar{width:4px;}::-webkit-scrollbar-track{background:{$bg};}
::-webkit-scrollbar-thumb{background:rgba(200,255,0,.3);}::-webkit-scrollbar-thumb:hover{background:{$ac};}
.elementor-editor-active .{$p}-reveal, .elementor-editor-active [class*='reveal'], .elementor-editor-active [class*='hidden'] { opacity: 1 !important; visibility: visible !important; transform: none !important; transition: none !important; }
{$keyframes_css}
</style>
<script>
(function(){
'use strict';
document.documentElement.classList.add('{$p}-motion-ready');
if(document.body){document.body.classList.add('{$p}-page');}
else{document.addEventListener('DOMContentLoaded',function(){if(document.body){document.body.classList.add('{$p}-page');}});}
{$cursor_js}
{$canvas_js}
{$asset_injector_js}
{$cursor_motion_js}

/* ── Scroll Reveal ── */
var sObs=new IntersectionObserver(function(entries){
  entries.forEach(function(e){
    if(e.isIntersecting || window.elementor) e.target.classList.add('{$p}-visible');
  });
},{threshold:.1});

function initR(){
  var revs=document.querySelectorAll('.{$p}-reveal');
  revs.forEach(function(el){
    // Safe reveal: if element is already in viewport or we are in Elementor editor
    var rect=el.getBoundingClientRect();
    if((rect.top<window.innerHeight&&rect.bottom>0)||window.elementor){
      el.classList.add('{$p}-visible');
    }
    sObs.observe(el);
  });
  // Fail-safe: show everything if JS fails or taking too long
  setTimeout(function(){document.querySelectorAll('.{$p}-reveal:not(.{$p}-visible)').forEach(function(f){f.classList.add('{$p}-visible');});},2500);
}
if(document.readyState==='loading'){document.addEventListener('DOMContentLoaded',initR);}else{initR();}

function initStatCounters(){
  var statCards=document.querySelectorAll('.{$p}-stat-cell');
  if(!statCards.length || !('IntersectionObserver' in window)){return;}
  var seen=new WeakSet();
  var parseValue=function(text){
    var raw=(text||'').trim();
    var match=raw.match(/^([\d.,]+)(.*)$/);
    if(!match){return null;}
    var num=parseFloat(match[1].replace(/,/g,''));
    if(isNaN(num)){return null;}
    var decimals=(match[1].split('.')[1]||'').length;
    return { value:num, decimals:decimals, suffix:(match[2]||'').trim() };
  };
  var obs=new IntersectionObserver(function(entries){
    entries.forEach(function(entry){
      if(!entry.isIntersecting || seen.has(entry.target)){return;}
      seen.add(entry.target);
      entry.target.classList.add('{$p}-counted');
      var heading=entry.target.querySelector('.{$p}-stat-num .elementor-heading-title');
      if(!heading){return;}
      var parsed=parseValue(heading.textContent);
      if(!parsed){return;}
      var cur=0, steps=60, inc=parsed.value/steps;
      var iv=setInterval(function(){
        cur+=inc;
        if(cur>=parsed.value){cur=parsed.value;clearInterval(iv);}
        heading.textContent=cur.toFixed(parsed.decimals)+parsed.suffix;
      },30);
    });
  },{threshold:.3});
  statCards.forEach(function(card){obs.observe(card);});
}
initStatCounters();

/* ── Nav scroll class ── */
window.addEventListener('scroll',function(){
var nav=document.getElementById('{$p}-fixed-nav');
if(nav)nav.classList.toggle('{$p}-scrolled',window.scrollY>60);
},{passive:true});

}());
</script>
HTML;

		$this->elements[] = $this->w( 'html', [
			'_css_classes' => "{$p}-global-setup",
			'_element_id'  => "{$p}-global-setup",
			'html'         => $html,
		]);
		$this->cmap( "{$p}-global-setup", 'Global Setup — fonts, CSS vars, cursor, particles, scroll reveal', 'First element — do not delete or move' );
	}

	// ═══════════════════════════════════════════════════════════
	// NAV — Always HTML widget (fixed position, JS scroll class)
	// ═══════════════════════════════════════════════════════════

	private function build_nav( \DOMDocument $dom ): void {
		$p   = $this->prefix;
		$ac  = $this->c_accent;
		$xp  = new \DOMXPath( $dom );

		// Find nav element in source.
		$nav_el = null;
		foreach ( ['//nav','//header[.//nav]','//*[@id="navbar"]','//*[@id="nav"]','//*[contains(@class,"nav")]'] as $q ) {
			$r = $xp->query($q);
			if ( $r && $r->length > 0 ) { $nav_el = $r->item(0); break; }
		}

		// Extract logo text.
		$logo = '';
		if ( $nav_el ) {
			$logo_el = $xp->query('.//*[contains(@class,"logo")] | .//a[contains(@class,"brand")] | .//a[contains(@class,"logo")] | .//h1 | .//h2', $nav_el)->item(0);
			if ( $logo_el ) {
				$t = $this->extract_inline_markup_text( $logo_el );
				if ( $t ) $logo = $t;
			}
		}

		// Extract links.
		$links_html = '';
		if ( $nav_el ) {
			$links = $xp->query('.//a[not(contains(@class,"btn")) and not(contains(@class,"cta")) and not(contains(@class,"logo"))]', $nav_el);
			if ( $links ) {
				$n = 0;
				foreach ( $links as $lnk ) {
					if ( ++$n > 6 ) break;
					$txt  = $this->extract_inline_markup_text( $lnk );
					$href = $lnk->getAttribute('href') ?: '#';
					if ( $txt && strlen( wp_strip_all_tags( $txt ) ) < 40 ) {
						$links_html .= "<li><a href=\"" . esc_attr($href) . "\" class=\"{$p}-nav-link\">" . $txt . "</a></li>\n";
					}
				}
			}
		}
		// CTA text.
		$cta = '';
		if ( $nav_el ) {
			$cta_el = $xp->query('.//*[contains(@class,"cta")] | .//button', $nav_el)->item(0);
			if ( $cta_el ) {
				$t = $this->extract_inline_markup_text( $cta_el );
				if ( $t ) {
					$cta = $this->normalize_cta_text( wp_strip_all_tags( $t ) );
				}
			}
		}

		$bc = $this->c_border;
		$bg = $this->c_bg;

		$html = <<<HTML
<style>
.{$p}-nav{position:fixed;top:0;left:0;right:0;z-index:1000;padding:24px 60px;display:flex;align-items:center;justify-content:space-between;border-bottom:1px solid transparent;transition:all .4s;}
.{$p}-nav.{$p}-scrolled{background:rgba(10,10,18,.88);backdrop-filter:blur(20px);-webkit-backdrop-filter:blur(20px);border-color:{$bc};padding:16px 60px;}
.{$p}-nav-logo{font-family:'{$this->f_display}',sans-serif;font-weight:800;font-size:22px;letter-spacing:-.5px;color:{$this->c_text};text-decoration:none;}
.{$p}-nav-logo span,.{$p}-nav-link span{color:{$ac};}
.{$p}-nav-links{display:flex;gap:40px;list-style:none;margin:0;padding:0;}
.{$p}-nav-link{font-size:13px;font-family:'{$this->f_mono}',monospace;color:rgba(245,243,238,.5);text-decoration:none;letter-spacing:.05em;transition:color .2s;}
.{$p}-nav-link:hover{color:{$this->c_text};}
.{$p}-nav-cta{font-family:'{$this->f_mono}',monospace;font-size:12px;letter-spacing:.08em;padding:10px 22px;background:{$ac};color:{$bg};border:none;font-weight:700;transition:all .2s;}
.{$p}-nav-cta:hover{opacity:.85;transform:translateY(-1px);}
@media(max-width:768px){.{$p}-nav-links{display:none;}.{$p}-nav{padding:16px 24px;}}
</style>
<nav class="{$p}-nav" id="{$p}-fixed-nav">
  <a href="#" class="{$p}-nav-logo">{$logo}</a>
  <ul class="{$p}-nav-links">
{$links_html}  </ul>
  {$this->render_nav_cta_html( $cta )}
</nav>
HTML;

		$this->elements[] = $this->w( 'html', [
			'_css_classes' => "{$p}-nav-widget",
			'_element_id'  => "{$p}-nav",
			'html'         => $html,
		]);
	}

	// ═══════════════════════════════════════════════════════════
	// PASS 3–7: Section processing pipeline
	// ═══════════════════════════════════════════════════════════

	private function build_sections( \DOMDocument $dom ): void {
		$xp = new \DOMXPath( $dom );

		$candidates = [];
		$seen_oids  = [];

		$add_nodes = function( $node_list ) use ( &$candidates, &$seen_oids ) {
			if ( ! $node_list ) return;
			foreach ( $node_list as $n ) {
				$oid = spl_object_id( $n );
				if ( ! isset( $seen_oids[ $oid ] ) ) {
					$candidates[]          = $n;
					$seen_oids[ $oid ]     = true;
				}
			}
		};

		$add_nodes( $xp->query( '//body/*' ) );

		// ── F-01a: Dive into single-child wrappers.
		// If body only has one or two children (e.g. #app wrapper), dive deeper
		// to find the actual sections.
		$loops = 0;
		while ( count( $candidates ) <= 2 && $loops < 3 ) {
			$new_candidates = [];
			$found_inner    = false;
			foreach ( $candidates as $c ) {
				$tag = strtolower($c->nodeName);
				// Skip if the candidate itself looks like a real section.
				$sig = strtolower($c->getAttribute('class') . ' ' . $c->getAttribute('id'));
				if ( in_array($tag, ['section','header','footer','main','nav'], true) || 
					 preg_match('/\b(hero|stats|pricing|features|process|cta)\b/', $sig) ) {
					$new_candidates[] = $c;
					continue;
				}
				
				$children = $xp->query( './*', $c );
				if ( $children && $children->length > 0 ) {
					foreach ( $children as $child ) {
						$new_candidates[] = $child;
						$found_inner = true;
					}
				} else {
					$new_candidates[] = $c;
				}
			}
			if ( ! $found_inner ) break;
			$candidates = $new_candidates;
			$loops++;
		}

		$final_sections = [];
		$type_groups    = [];

		foreach ( $candidates as $node ) {
			if ( ! ( $node instanceof \DOMElement ) ) continue;
			$tag = strtolower( $node->nodeName );
			if ( in_array( $tag, ['script','style','noscript','link','meta','head'], true ) ) continue;
			if ( strlen( trim( $node->textContent ) ) < 5 ) continue;
			if ( $this->is_static_hidden_element( $node, $xp ) ) continue;
			if ( $this->intel->is_cursor_element( $node ) ) continue;
			if ( $this->intel->is_background_element( $node ) ) continue;

			$type = $this->classify_section( $node, $xp );
			if ( $type === 'nav' ) continue;

			$type_groups[$type][] = $node;
		}

		$this->detected_section_types = array_values( array_unique( array_keys( $type_groups ) ) );

		// Architecture article §14: "Deduplication must be based on structural fidelity".
		foreach ( $type_groups as $type => $nodes ) {
			if ( $type === 'generic' ) {
				foreach ( $nodes as $n ) $final_sections[] = ['type' => $type, 'node' => $n];
				continue;
			}
			// Pick highest fidelity node for this type.
			$best_node  = null;
			$best_score = -1;
			foreach ( $nodes as $n ) {
				$score = $this->calculate_fidelity_score( $n, $xp );
				if ( $score > $best_score ) {
					$best_score = $score;
					$best_node  = $n;
				}
			}
			if ( $best_node ) {
				$best_node = $this->promote_structural_section_root( $best_node, (string) $type, $xp );
			}
			if ( $best_node ) $final_sections[] = ['type' => $type, 'node' => $best_node];
		}

		$section_type_counts = [];
		foreach ( $final_sections as $sec ) {
			$node = $sec['node'];
			$type = $sec['type'];
			$section_type_counts[ $type ] = ( $section_type_counts[ $type ] ?? 0 ) + 1;
			$section_ordinal = (int) $section_type_counts[ $type ];

			// Pass 3: Editability / complexity score for native vs HTML widget.
			$decision = $this->decide_strategy( $node, $type, $xp );
			$complexity = $this->score_subtree_complexity( $node, $xp );

			// Pass 7: Try template library first (highest quality output).
			// ── F-01b: marquee/stats/cta route through library even if decision is 'html'.
			$el = null;
			$render_mode = 'native_rebuilt';
			$content = $this->extract_section_content( $node, $type, $xp );
			$this->section_payloads[ $type ] = $content;
			$hybrid_fragment_html = null;
			$hybrid_fragments = [];
			if ( 'native' === $decision ) {
				$hybrid_fragment_html = $this->extract_hybrid_fragment_html( $node, $xp, $complexity );
				$hybrid_fragments = $this->extract_repeated_hybrid_fragments( $node, $xp, $complexity );
			}

			// Broad-spectrum safety: if a native/template path is selected but the
			// required payload is missing, preserve the source as HTML instead of
			// emitting a broken native template with empty content.
			if ( 'native' === $decision && $this->section_payload_requires_preservation( $type, $content ) ) {
				$decision = 'html';
				$this->diagnostics[] = [
					'code'    => 'payload_unresolved_preserve',
					'message' => sprintf( 'Section "%s" payload unresolved; preserving as HTML widget to avoid partial native output.', $type ),
					'context' => [ 'pass' => 7, 'type' => $type, 'required' => $this->required_payload_keys_for_type( $type ) ],
				];
			}

			$force_template_types = [ 'marquee', 'stats', 'cta' ];
			$force_template_allowed = in_array( $type, $force_template_types, true ) && ! $this->section_payload_requires_preservation( $type, $content );
			if ( 'cta' === $type && 'html' === $decision ) {
				$force_template_allowed = false;
			}
			if ( ! $el && ( $decision === 'native' || ( $this->lib->has_template( $type ) && $force_template_allowed ) ) ) {
				$el = $this->lib->get( $type, $content );
				if ( $el ) {
					$render_mode = 'native_rebuilt';
				}
			}

			// Fall back when library has no match.
			if ( ! $el ) {
				if ( $decision === 'native' ) {
					// Generic native builder.
					$el = $this->build_generic_native( $node, $type, $xp );
					$render_mode = 'native_rebuilt';
				} else {
					// HTML widget: preserve original with extracted CSS.
					$el = $this->build_html_widget_section( $node, $type, $xp );
					$render_mode = 'fully_preserved_source';
				}
			}

			if ( $el ) {
				if ( 'native' === $decision && ! empty( $hybrid_fragment_html ) ) {
					$existing_visual_widgets = $this->count_subtree_class_pattern(
						$el,
						'/\b' . preg_quote( $this->prefix, '/' ) . '-card-visual-widget\b/'
					);
					if ( $existing_visual_widgets <= 0 ) {
						$this->append_hybrid_fragment_widget( $el, $type, (string) $hybrid_fragment_html, $node, $hybrid_fragments );
					}
					$render_mode = 'native_hybrid_fragment';
					$this->section_payloads[ $type ]['hybrid_fragment_added'] = true;
				}
				$this->assign_top_level_section_anchor( $el, $type, $section_ordinal );
				$this->section_payloads[ $type ]['output_element_id'] = (string) ( $el['settings']['_element_id'] ?? '' );
				$this->elements[] = $el;
				$this->built_section_types[] = $type;
				$this->section_payloads[ $type ]['render_mode'] = $render_mode;
				$this->record_section_diagnostic( $type, $render_mode, $decision, $node, $content, $el, $xp );
				$this->cmap( "{$this->prefix}-{$type}", ucfirst($type) . ' section', 'Advanced tab → CSS ID' );
			}
		}
	}

	// ═══════════════════════════════════════════════════════════
	// PASS 3: CLASSIFIER — Multi-signal scoring
	// ═══════════════════════════════════════════════════════════

	private function assign_top_level_section_anchor( array &$el, string $type, int $ordinal ): void {
		if ( ! isset( $el['settings'] ) || ! is_array( $el['settings'] ) ) {
			$el['settings'] = [];
		}

		$base_id = "{$this->prefix}-{$type}";
		$el['settings']['_element_id'] = $ordinal > 1 ? "{$base_id}-{$ordinal}" : $base_id;

		// Broad-spectrum hook rule: whenever we mint a stable anchor ID, also
		// ensure a matching class hook exists so CSS bridges/pseudo hosts can
		// target either `#id` or `.class` safely.
		$existing = (string) ( $el['settings']['_css_classes'] ?? '' );
		$tokens = array_values( array_filter( preg_split( '/\s+/', trim( $existing ) ) ?: [] ) );
		$need = "{$this->prefix}-{$type}";
		if ( ! in_array( $need, $tokens, true ) ) {
			$tokens[] = $need;
			$el['settings']['_css_classes'] = trim( implode( ' ', $tokens ) );
		}
	}

	private function classify_section( \DOMElement $node, \DOMXPath $xp ): string {
		$tag  = strtolower( $node->nodeName );
		// Strip prefix-dash segments before scoring so "nx-hero" → "hero".
		$sig  = preg_replace( '/\b[a-z]{1,5}-/', '', strtolower(
			$node->getAttribute('class') . ' ' . $node->getAttribute('id')
		));
		$text = strtolower( substr( $node->textContent, 0, 800 ) );

		if ( $tag === 'footer' || str_contains($sig,'footer') ) return 'footer';
		if ( $tag === 'nav' )                                    return 'nav';

		$s = array_fill_keys([
			'hero','marquee','stats','features','bento','process','timeline',
			'testimonials','pricing','comparison','faq','team','gallery',
			'video','blog','contact','newsletter','cta','about','logos',
			'slider','footer','generic',
		], 0);

		if ( $tag === 'header' ) $s['hero'] += 8;

		$kw = [
			'hero'         => ['hero','banner','splash','masthead','intro','landing'],
			'marquee'      => ['marquee','ticker','scroller','logo-strip','brand-strip','partner-strip','infinite'],
			'stats'        => ['stats','statistics','numbers','metrics','counter','count-up','achievements'],
			'features'     => ['features','capabilities','services','solutions','offerings','benefits'],
			'bento'        => ['bento','grid-features','feature-grid','mosaic'],
			'process'      => ['process','how-it-works','steps','workflow','method','approach'],
			'timeline'     => ['timeline','history','journey','roadmap','milestones'],
			'testimonials' => ['testimonials','reviews','feedback','quotes','social-proof'],
			'pricing'      => ['pricing','plans','packages','tiers','price','billed','annually','per month'],
			'comparison'   => ['comparison','compare','versus','vs'],
			'faq'          => ['faq','faqs','accordion','questions','answers'],
			'team'         => ['team','people','staff','founders','members'],
			'gallery'      => ['gallery','portfolio','work','projects','showcase'],
			'video'        => ['video','media','demo','watch','reel'],
			'blog'         => ['blog','posts','articles','news','insights'],
			'contact'      => ['contact','get-in-touch','reach-us'],
			'newsletter'   => ['newsletter','subscribe','signup'],
			'cta'          => ['cta','call-to-action','get-started','final-cta','ready to','extraordinary','ship faster','start building'],
			'about'        => ['about','mission','vision','story','company'],
			'logos'        => ['logos','clients','partners','brands','trusted'],
			'slider'       => ['slider','carousel','slideshow','swiper'],
		];

		foreach ( $kw as $type => $words ) {
			foreach ( $words as $w ) {
				if ( str_contains($sig,$w) )  $s[$type] += 12;
				if ( str_contains($text,$w) ) $s[$type] +=  3;
			}
		}

		// ── Hardening: High-priority structural markers.
		if ( str_contains($sig, 'marquee') || str_contains($sig, 'ticker') ) $s['marquee'] += 25;
		if ( str_contains($sig, 'stats') || str_contains($sig, 'metrics') )   $s['stats']   += 25;
		if ( str_contains($sig, 'bento') )                                   $s['bento']   += 25;

		// ── Hardening: Content fingerprints.
		if ( preg_match('/\$[\d,]+|€\d+|£\d+/', $text) ) {
			// Only count as pricing if it doesn't look like a testimonial quote.
			if ( ! preg_match('/"[^"]+"|\bwrote\b|\bsaid\b|\bclient\b/i', $text) ) {
				$s['pricing'] += 14;
			} else {
				$s['testimonials'] += 10;
				$s['pricing'] -= 5;
			}
		}

		if ( substr_count($text,'?') >= 3 )               $s['faq']     += 10;

		// Structural.
		if ( $xp->query('.//h1', $node)->length === 1 ) $s['hero'] += 12;
		if ( $xp->query('.//video | .//iframe[contains(@src,"youtube") or contains(@src,"vimeo")]', $node)->length > 0 ) $s['video'] += 18;
		if ( $xp->query('.//form | .//input[@type="email"] | .//textarea', $node)->length > 0 ) $s['contact'] += 14;
		if ( $xp->query('.//details', $node)->length > 0 ) $s['faq'] += 14;

		arsort($s);
		$winner = (string) array_key_first($s);
		return ( reset($s) < 4 ) ? 'generic' : $winner;
	}

	// ═══════════════════════════════════════════════════════════
	// PASS 3: DECISION ENGINE
	// Determines native vs HTML widget per strategy + complexity
	// ═══════════════════════════════════════════════════════════

	private function decide_strategy( \DOMElement $node, string $type, \DOMXPath $xp ): string {
		$hard_rule = $this->priority_rules->evaluate_section( $node, $xp );
		if ( is_array( $hard_rule ) ) {
			$rule_id = (string) ( $hard_rule['rule'] ?? '' );
			if ( 'v2' === $this->strategy && 'RULE-005' === $rule_id && $this->supports_rule_005_fragment_hybrid( $type ) ) {
				$this->diagnostics[] = [
					'code'    => 'priority_rule_fragment_hybrid',
					'message' => 'RULE-005 routed to V2 native rebuild with in-place hybrid preservation for columns/masonry fragments.',
					'context' => [
						'pass' => 3,
						'type' => $type,
						'rule' => $rule_id,
					],
				];
				return 'native';
			}
			if ( 'v2' === $this->strategy && 'RULE-005' === $rule_id && $this->can_relax_css_columns_hard_rule( $node, $xp, $type ) ) {
				$this->diagnostics[] = [
					'code'    => 'priority_rule_relaxed',
					'message' => 'RULE-005 relaxed for V2 primitive-first path because no true css-columns contract was found.',
					'context' => [
						'pass' => 3,
						'type' => $type,
						'rule' => $rule_id,
					],
				];
			} else {
			$this->diagnostics[] = [
				'code'    => 'priority_rule_match',
				'message' => sprintf( 'Priority rule %s forced %s strategy.', (string) ( $hard_rule['rule'] ?? 'unknown' ), (string) ( $hard_rule['action'] ?? 'native' ) ),
				'context' => [
					'pass'   => 3,
					'type'   => $type,
					'rule'   => (string) ( $hard_rule['rule'] ?? '' ),
					'reason' => (string) ( $hard_rule['reason'] ?? '' ),
				],
			];
			return ( 'html' === ( $hard_rule['action'] ?? '' ) ) ? 'html' : 'native';
			}
		}

		// ── F-01b: Types that have template library entries should go through
		// the library even if they produce HTML widgets. This ensures the
		// library's well-crafted HTML (with proper CSS/JS) is used rather
		// than the raw source HTML widget preservation fallback.
		// marquee, stats, cta → template library handles them (outputs HTML widgets)
		// slider, logos, video, gallery, contact, newsletter → raw HTML widget preserve
		if ( $this->strategy_profile && $this->strategy_profile->should_force_html_type( $type ) ) {
			return 'html';
		}

		$policy = $this->get_strategy_policy();
		$complexity = $this->score_subtree_complexity( $node, $xp );
		$this->diagnostics[] = [
			'code'    => 'strategy_complexity_score',
			'message' => 'Generic subtree complexity scored for native-vs-html strategy.',
			'context' => [
				'pass'           => 3,
				'type'           => $type,
				'score'          => (int) ( $complexity['score'] ?? 0 ),
				'threshold_html' => (int) ( $policy['html_threshold'] ?? 7 ),
				'signals'        => (array) ( $complexity['signals'] ?? [] ),
				'policy'         => $policy['id'] ?? $this->strategy,
			],
		];

		$v2_affinity = [];
		if ( 'v2' === $this->strategy ) {
			$v2_affinity = $this->collect_v2_native_affinity_signals( $node, $xp );
			$this->diagnostics[] = [
				'code'    => 'v2_native_affinity',
				'message' => 'V2 primitive-first affinity calculated before preserve decision.',
				'context' => [
					'pass'   => 3,
					'type'   => $type,
					'score'  => (int) ( $v2_affinity['score'] ?? 0 ),
					'signals'=> (array) ( $v2_affinity['signals'] ?? [] ),
				],
			];
			if ( $this->should_prefer_native_in_v2( $type, $v2_affinity, $complexity ) ) {
				return 'native';
			}
		}

		// Preserve richer interactive sections unless we have explicit native
		// builders for them. This prevents partial breakage for accordions, tabs,
		// toggles, and carousel-like structures that are common in real designs.
		if ( $this->contains_interactive_structure( $node, $xp ) || $this->has_complex_behavior_contract( $node, $xp ) ) {
			if ( 'v2' === $this->strategy ) {
				$this->diagnostics[] = [
					'code'    => 'strategy_interactive_hybrid_preferred',
					'message' => 'V2 prefers native + in-place hybrid fragments for interactive/behavioral structures.',
					'context' => [ 'pass' => 3, 'type' => $type ],
				];
				return 'native';
			}
			return 'html';
		}

		// Broad-spectrum policy: decide from structural/behavioral complexity,
		// not only known section labels.
		if ( (int) ( $complexity['score'] ?? 0 ) >= (int) ( $policy['html_threshold'] ?? 7 ) ) {
			return 'html';
		}

		// Strategy-specific guardrails (kept explicit and policy-driven).
		if ( ! in_array( $type, (array) ( $policy['never_force_html_types'] ?? [] ), true ) ) {
			if ( ! empty( $policy['preserve_on_animation'] ) && $this->intel->element_is_animated( $node ) ) {
				return 'html';
			}
			if ( ! empty( $policy['preserve_on_inline_script'] ) && $xp->query( './/script', $node )->length > 0 ) {
				return 'html';
			}
			$signals = (array) ( $complexity['signals'] ?? [] );
			if ( ! empty( $policy['preserve_on_grid_span'] ) && ! empty( $signals['grid_span_complexity'] ) ) {
				return 'html';
			}
			if ( ! empty( $policy['preserve_on_absolute_layering'] ) && ! empty( $signals['absolute_layering'] ) ) {
				return 'html';
			}
		}

		// Otherwise native.
		return 'native';
	}

	private function can_relax_css_columns_hard_rule( \DOMElement $node, \DOMXPath $xp, string $type ): bool {
		if ( ! in_array( $type, [ 'hero', 'features', 'pricing', 'footer', 'cta', 'generic', 'process', 'testimonials' ], true ) ) {
			return false;
		}
		return ! V2HybridPreserveHelper::node_has_columns_contract( $node, $xp, (string) ( $this->intel->raw_css ?? '' ) );
	}

	private function supports_rule_005_fragment_hybrid( string $type ): bool {
		return in_array( $type, [ 'hero', 'features', 'pricing', 'footer', 'cta', 'generic', 'process', 'testimonials' ], true );
	}

	/**
	 * Build optional in-place hybrid fragment from complexity signals.
	 */
	private function extract_hybrid_fragment_html( \DOMElement $node, \DOMXPath $xp, array $complexity ): ?string {
		$signals = (array) ( $complexity['signals'] ?? [] );
		$should_attempt = ! empty( $signals['behavior_coupling'] )
			|| ! empty( $signals['interactive_structure'] )
			|| ! empty( $signals['grid_span_complexity'] )
			|| ! empty( $signals['pseudo_dependency'] )
			|| ! empty( $signals['css_columns_masonry'] );
		if ( ! $should_attempt ) {
			return null;
		}

		if ( ! empty( $signals['css_columns_masonry'] ) ) {
			$columns_fragment = $this->extract_columns_hybrid_fragment_html( $node, $xp );
			if ( ! empty( $columns_fragment ) ) {
				return $columns_fragment;
			}
		}

		$fragment = $this->extract_complex_visual( $node );
		if ( empty( $fragment ) || strlen( (string) $fragment ) < 24 ) {
			return null;
		}
		return (string) $fragment;
	}

	private function extract_columns_hybrid_fragment_html( \DOMElement $node, \DOMXPath $xp ): ?string {
		$candidates = V2HybridPreserveHelper::find_columns_contract_nodes( $node, $xp, (string) ( $this->intel->raw_css ?? '' ) );
		if ( empty( $candidates ) ) {
			$this->diagnostics[] = [
				'code'    => 'rule_005_fragment_unavailable',
				'message' => 'RULE-005 columns/masonry contract was detected, but no local fragment root could be isolated.',
				'context' => [ 'pass' => 7 ],
			];
			return null;
		}

		foreach ( $candidates as $candidate ) {
			if ( ! $candidate instanceof \DOMElement ) {
				continue;
			}
			$html = (string) $candidate->ownerDocument->saveHTML( $candidate );
			if ( strlen( trim( wp_strip_all_tags( $html ) ) ) < 4 && ! preg_match( '/<(?:img|svg|canvas|video|iframe)\b/i', $html ) ) {
				continue;
			}

			$this->diagnostics[] = [
				'code'    => 'rule_005_fragment_isolated',
				'message' => 'RULE-005 columns/masonry behavior isolated to an in-place hybrid fragment.',
				'context' => [
					'pass'  => 7,
					'tag'   => strtolower( $candidate->tagName ),
					'id'    => (string) $candidate->getAttribute( 'id' ),
					'class' => trim( (string) $candidate->getAttribute( 'class' ) ),
					'is_section_root' => $candidate === $node,
				],
			];
			return $this->build_preserved_fragment_html( $candidate );
		}

		return null;
	}

	/**
	 * Inject hybrid HTML fragment as an in-place child widget.
	 */
	private function append_hybrid_fragment_widget( array &$element, string $type, string $html, \DOMElement $source_node, array $fragments = [] ): void {
		if ( '' === trim( $html ) ) {
			return;
		}
		if ( ! isset( $element['elements'] ) || ! is_array( $element['elements'] ) ) {
			$element['elements'] = [];
		}
		$source_hooks = $this->source_hook_classes_for_node( $source_node );
		$attached_to_subtree = false;
		if ( ! empty( $fragments ) ) {
			$fragment_index = 0;
			foreach ( $element['elements'] as &$child ) {
				if ( ! is_array( $child ) || 'container' !== ( $child['elType'] ?? '' ) ) {
					continue;
				}
				if ( $fragment_index >= count( $fragments ) ) {
					break;
				}
				if ( ! isset( $child['elements'] ) || ! is_array( $child['elements'] ) ) {
					$child['elements'] = [];
				}
				$child['elements'][] = $this->w( 'html', [
					'_css_classes' => trim( "{$this->prefix}-{$type}-hybrid-fragment {$this->prefix}-hybrid-fragment {$source_hooks}" ),
					'html'         => (string) $fragments[ $fragment_index ],
				] );
				$fragment_index++;
				$attached_to_subtree = true;
			}
			unset( $child );
		}

		if ( ! $attached_to_subtree ) {
			$element['elements'][] = $this->w( 'html', [
				'_css_classes' => trim( "{$this->prefix}-{$type}-hybrid-fragment {$this->prefix}-hybrid-fragment {$source_hooks}" ),
				'html'         => $html,
			] );
		}
		$this->diagnostics[] = [
			'code'    => 'hybrid_fragment_attached',
			'message' => sprintf( 'Attached in-place hybrid fragment for section "%s".', $type ),
			'context' => [
				'pass'               => 7,
				'type'               => $type,
				'fragments_detected' => count( $fragments ),
				'attached_to_subtree'=> $attached_to_subtree,
			],
		];
	}

	/**
	 * Prefer repeated-source child fragments for in-place hybrid attachment.
	 *
	 * @return array<int,string>
	 */
	private function extract_repeated_hybrid_fragments( \DOMElement $node, \DOMXPath $xp, array $complexity ): array {
		$signals = (array) ( $complexity['signals'] ?? [] );
		if (
			empty( $signals['behavior_coupling'] ) &&
			empty( $signals['interactive_structure'] ) &&
			empty( $signals['grid_span_complexity'] ) &&
			empty( $signals['pseudo_dependency'] ) &&
			empty( $signals['css_columns_masonry'] )
		) {
			return [];
		}

		$fragments = [];
		foreach ( $node->childNodes as $child ) {
			if ( ! $child instanceof \DOMElement ) {
				continue;
			}
			if ( ! in_array( strtolower( $child->tagName ), [ 'div', 'section', 'article', 'li' ], true ) ) {
				continue;
			}
			$fragment = $this->extract_complex_visual( $child );
			if ( ! empty( $fragment ) && strlen( (string) $fragment ) >= 24 ) {
				$fragments[] = (string) $fragment;
			}
			if ( count( $fragments ) >= 6 ) {
				break;
			}
		}

		return $fragments;
	}

	/**
	 * Generic complexity scoring for any section subtree.
	 *
	 * @return array{score:int,signals:array<string,mixed>}
	 */
	private function score_subtree_complexity( \DOMElement $node, \DOMXPath $xp ): array {
		$score   = 0;
		$signals = [];

		$containers = $xp->query( './/*[self::div or self::section or self::article or self::main or self::aside]', $node );
		$container_count = $containers ? (int) $containers->length : 0;
		if ( $container_count >= 12 ) {
			$score += 2;
			$signals['container_density'] = $container_count;
		}

		$max_child_containers = $this->max_direct_container_children( $node, $xp );
		if ( $max_child_containers >= 5 ) {
			$score += 2;
			$signals['repeated_container_children'] = $max_child_containers;
		}

		$max_depth = $this->estimate_container_depth( $node, $xp );
		if ( $max_depth >= 4 ) {
			$score += 1;
			$signals['container_depth'] = $max_depth;
		}

		$node_html = strtolower( $node->ownerDocument->saveHTML( $node ) );
		if ( str_contains( $node_html, 'grid-row' ) || str_contains( $node_html, 'grid-column' ) || str_contains( $node_html, 'grid-template' ) ) {
			$score += 2;
			$signals['grid_span_complexity'] = true;
		}
		if ( V2HybridPreserveHelper::node_has_columns_contract( $node, $xp, (string) ( $this->intel->raw_css ?? '' ) ) ) {
			$score += 2;
			$signals['css_columns_masonry'] = true;
		}
		if ( str_contains( $node_html, 'position:absolute' ) || str_contains( $node_html, 'position: absolute' ) ) {
			$score += 1;
			$signals['absolute_layering'] = true;
		}

		if ( $this->node_has_pseudo_dependency( $node ) ) {
			$score += 2;
			$signals['pseudo_dependency'] = true;
		}

		if ( $this->has_complex_behavior_contract( $node, $xp ) ) {
			$score += 3;
			$signals['behavior_coupling'] = true;
		}

		if ( $this->contains_interactive_structure( $node, $xp ) ) {
			$score += 2;
			$signals['interactive_structure'] = true;
		}

		return [
			'score'          => $score,
			'signals'        => $signals,
		];
	}

	/**
	 * Explicit strategy policy: thresholds + guardrails.
	 *
	 * @return array<string,mixed>
	 */
	private function get_strategy_policy(): array {
		if ( 'v1' === $this->strategy ) {
			return [
				'id'                         => 'v1_fidelity_first',
				'html_threshold'             => 6,
				'preserve_on_animation'      => true,
				'preserve_on_inline_script'  => true,
				'preserve_on_grid_span'      => true,
				'preserve_on_absolute_layering' => true,
				// Types that are routed elsewhere (template library), so do not force by this policy.
				'never_force_html_types'     => [ 'marquee', 'stats', 'cta' ],
			];
		}

		// v2
		return [
			'id'                         => 'v2_editable_native_first',
			'html_threshold'             => 7,
			'preserve_on_animation'      => false,
			'preserve_on_inline_script'  => false,
			'preserve_on_grid_span'      => false,
			'preserve_on_absolute_layering' => false,
			'never_force_html_types'     => [ 'marquee', 'stats', 'cta' ],
		];
	}

	private function required_payload_keys_for_type( string $type ): array {
		return match ( $type ) {
			'features', 'bento', 'testimonials', 'pricing' => [ 'cards' ],
			'footer' => [ 'cols' ],
			'process' => [ 'steps' ],
			'stats' => [ 'stats' ],
			'marquee' => [ 'items' ],
			'cta' => [ 'title' ],
			default => [],
		};
	}

	private function section_payload_requires_preservation( string $type, array $payload ): bool {
		if ( 'v2' === $this->strategy && $this->strategy_profile && ! $this->strategy_profile->allows_payload_preservation( $type ) ) {
			return false;
		}
		$required = $this->required_payload_keys_for_type( $type );
		if ( empty( $required ) ) {
			return false;
		}
		// If already preserved, do not force again.
		if ( 'fully_preserved_source' === (string) ( $payload['render_mode'] ?? '' ) ) {
			return false;
		}
		foreach ( $required as $key ) {
			if ( empty( $payload[ $key ] ) ) {
				return true;
			}
		}
		return false;
	}

	private function collect_v2_native_affinity_signals( \DOMElement $node, \DOMXPath $xp ): array {
		return V2DecisionHelper::collect_affinity_signals( $this->html_parser, $node, $xp );
	}

	private function should_prefer_native_in_v2( string $type, array $affinity, array $complexity ): bool {
		return V2DecisionHelper::should_prefer_native( $type, $affinity, $complexity );
	}

	private function emit_strategy_policy_diagnostic(): void {
		if ( $this->strategy_policy_emitted ) {
			return;
		}
		$this->strategy_policy_emitted = true;
		$this->diagnostics[] = [
			'code'    => 'strategy_policy',
			'message' => 'Strategy policy locked for this run.',
			'context' => [
				'pass'     => 1,
				'strategy' => $this->strategy,
				'policy'   => $this->get_strategy_policy(),
			],
		];
	}

	private function max_direct_container_children( \DOMElement $root, \DOMXPath $xp ): int {
		$max = 0;
		$all = $xp->query( './/*[self::div or self::section or self::article or self::main or self::aside]', $root );
		if ( ! $all ) {
			return 0;
		}
		foreach ( $all as $node ) {
			if ( ! $node instanceof \DOMElement ) {
				continue;
			}
			$count = 0;
			foreach ( $node->childNodes as $child ) {
				if ( $child instanceof \DOMElement && in_array( strtolower( $child->tagName ), [ 'div', 'section', 'article', 'main', 'aside' ], true ) ) {
					$count++;
				}
			}
			if ( $count > $max ) {
				$max = $count;
			}
		}
		return $max;
	}

	private function estimate_container_depth( \DOMElement $root, \DOMXPath $xp ): int {
		$max_depth = 0;
		$walk = function( \DOMElement $node, int $depth ) use ( &$walk, &$max_depth ): void {
			if ( $depth > $max_depth ) {
				$max_depth = $depth;
			}
			foreach ( $node->childNodes as $child ) {
				if ( ! $child instanceof \DOMElement ) {
					continue;
				}
				if ( in_array( strtolower( $child->tagName ), [ 'div', 'section', 'article', 'main', 'aside' ], true ) ) {
					$walk( $child, $depth + 1 );
				}
			}
		};
		$walk( $root, 1 );
		return $max_depth;
	}

	private function node_has_pseudo_dependency( \DOMElement $node ): bool {
		$doc_html = strtolower( (string) $node->ownerDocument->saveHTML() );
		$id = trim( strtolower( (string) $node->getAttribute( 'id' ) ) );
		if ( '' !== $id && ( str_contains( $doc_html, '#' . $id . '::before' ) || str_contains( $doc_html, '#' . $id . '::after' ) ) ) {
			return true;
		}
		$classes = preg_split( '/\s+/', strtolower( trim( (string) $node->getAttribute( 'class' ) ) ) );
		foreach ( (array) $classes as $class_name ) {
			$class_name = trim( (string) $class_name );
			if ( '' === $class_name ) {
				continue;
			}
			if ( str_contains( $doc_html, '.' . $class_name . '::before' ) || str_contains( $doc_html, '.' . $class_name . '::after' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Detect richer interactive structures that should stay as HTML widgets.
	 *
	 * @param \DOMElement $node Section node.
	 * @param \DOMXPath   $xp DOM XPath helper.
	 * @return bool
	 */
	private function contains_interactive_structure( \DOMElement $node, \DOMXPath $xp ): bool {
		$section_html = strtolower( $node->ownerDocument->saveHTML( $node ) );

		$interactive_markers = [
			'accordion',
			'collapse',
			'toggle',
			'tablist',
			'tab-content',
			'carousel',
			'swiper',
			'slick-track',
			'glide__track',
			'splide',
			'data-bs-toggle',
			'data-toggle',
			'aria-expanded',
			'aria-controls',
			'role="tab"',
			'role="tabpanel"',
			'role="tablist"',
		];

		foreach ( $interactive_markers as $marker ) {
			if ( str_contains( $section_html, $marker ) ) {
				return true;
			}
		}

		$interactive_nodes = $xp->query(
			'.//*[
				contains(@class,"accordion")
				or contains(@class,"toggle")
				or contains(@class,"tabs")
				or contains(@class,"tab-content")
				or contains(@class,"carousel")
				or contains(@class,"swiper")
				or contains(@class,"slider")
				or @aria-expanded
				or @aria-controls
				or @data-bs-toggle
				or @data-toggle
				or @role="tab"
				or @role="tabpanel"
				or @role="tablist"
			]',
			$node
		);

		return (bool) ( $interactive_nodes && $interactive_nodes->length > 0 );
	}

	/**
	 * Detect behavior-heavy structures that should be preserved as HTML.
	 */
	private function has_complex_behavior_contract( \DOMElement $node, \DOMXPath $xp ): bool {
		if ( $this->intel->element_is_animated( $node ) ) {
			return true;
		}

		if ( $xp->query( './/script', $node )->length > 0 ) {
			return true;
		}

		$html = strtolower( $node->ownerDocument->saveHTML( $node ) );
		$markers = [
			'requestanimationframe', 'intersectionobserver', 'setinterval', 'settimeout',
			'counter', 'countup', 'marquee', 'ticker', 'lottie', 'gsap',
			'canvas', 'webgl', 'particles', 'orb', 'parallax',
			'data-aos', 'data-animate', 'data-animation', 'data-counter',
			'onmouseover=', 'onmouseenter=', 'onmouseleave=', 'onclick=',
		];
		foreach ( $markers as $marker ) {
			if ( str_contains( $html, $marker ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Static hidden elements can be skipped at section candidate level.
	 * Toggle/tab/accordion linked hidden blocks are not skipped.
	 */
	private function is_static_hidden_element( \DOMElement $node, \DOMXPath $xp ): bool {
		$style = strtolower( preg_replace( '/\s+/', '', (string) $node->getAttribute( 'style' ) ) );
		$hidden_by_style = ( '' !== $style ) && ( str_contains( $style, 'display:none' ) || str_contains( $style, 'visibility:hidden' ) );
		$hidden_by_attr  = $node->hasAttribute( 'hidden' ) || 'true' === strtolower( (string) $node->getAttribute( 'aria-hidden' ) );
		$hidden_contract = $this->node_has_hidden_class_contract( $node );
		$hidden_by_class = ! empty( $hidden_contract['hidden'] );

		$is_hidden = $hidden_by_style || $hidden_by_attr || $hidden_by_class;
		if ( ! $is_hidden ) {
			return false;
		}

		if (
			$node->hasAttribute( 'data-tab' ) ||
			$node->hasAttribute( 'data-panel' ) ||
			$node->hasAttribute( 'data-filter' ) ||
			$node->hasAttribute( 'data-category' ) ||
			$node->hasAttribute( 'data-target' ) ||
			$node->hasAttribute( 'data-bs-target' ) ||
			$node->hasAttribute( 'aria-controls' ) ||
			$node->hasAttribute( 'aria-expanded' ) ||
			'tabpanel' === strtolower( (string) $node->getAttribute( 'role' ) )
		) {
			return false;
		}

		$scope_html = strtolower( $node->ownerDocument->saveHTML( $node->ownerDocument->documentElement ) );
		$id = trim( (string) $node->getAttribute( 'id' ) );
		if ( '' !== $id && ( str_contains( $scope_html, "getelementbyid('{$id}')" ) || str_contains( $scope_html, "getelementbyid(\"{$id}\")" ) ) ) {
			return false;
		}

		$class_attr = trim( (string) $node->getAttribute( 'class' ) );
		if ( '' !== $class_attr ) {
			foreach ( preg_split( '/\s+/', $class_attr ) as $class_name ) {
				$class_name = trim( (string) $class_name );
				if ( '' !== $class_name && ( str_contains( $scope_html, '.' . strtolower( $class_name ) ) || str_contains( $scope_html, 'classlist' ) ) ) {
					return false;
				}
			}
		}

		$button_toggle = $xp->query(
			'.//button[@aria-controls or @data-bs-toggle or @data-toggle] | .//*[@role="tab" or @data-tab]',
			$node->parentNode instanceof \DOMNode ? $node->parentNode : $node
		);
		if ( $button_toggle && $button_toggle->length > 0 ) {
			return false;
		}

		// Record limited diagnostics for skipped hidden elements to avoid silent drops.
		if ( $this->hidden_skip_log_count < 30 ) {
			$this->hidden_skip_log_count++;
			$this->diagnostics[] = [
				'code'    => 'static_hidden_skipped',
				'message' => 'Static hidden element skipped during candidate selection.',
				'context' => [
					'pass'           => 3,
					'tag'            => strtolower( (string) $node->nodeName ),
					'id'             => (string) $node->getAttribute( 'id' ),
					'class'          => (string) $node->getAttribute( 'class' ),
					'hidden_by'      => [
						'style' => $hidden_by_style,
						'attr'  => $hidden_by_attr,
						'class' => $hidden_by_class,
					],
					'class_contract' => $hidden_contract,
				],
			];
		}

		return true;
	}

	/**
	 * Best-effort detection of class-driven "hidden" contracts.
	 *
	 * @return array{hidden:bool,reason:string,matched_class:string}
	 */
	private function node_has_hidden_class_contract( \DOMElement $node ): array {
		$class_attr = strtolower( trim( (string) $node->getAttribute( 'class' ) ) );
		if ( '' === $class_attr ) {
			return [ 'hidden' => false, 'reason' => '', 'matched_class' => '' ];
		}

		$tokens = array_values( array_filter( preg_split( '/\s+/', $class_attr ) ?: [] ) );
		$common_hidden = [
			'hidden', 'sr-only', 'visually-hidden', 'is-hidden', 'u-hidden',
			'd-none', 'd-hidden', 'hidden-md', 'hidden-lg', 'hide', 'is-invisible',
		];
		foreach ( $tokens as $tok ) {
			$tok = trim( (string) $tok );
			if ( '' === $tok ) {
				continue;
			}
			if ( in_array( $tok, $common_hidden, true ) ) {
				return [ 'hidden' => true, 'reason' => 'common_hidden_class', 'matched_class' => $tok ];
			}
			// Tailwind variant shorthand like md:hidden / lg:hidden.
			if ( preg_match( '/(^|:)hidden$/', $tok ) ) {
				return [ 'hidden' => true, 'reason' => 'tailwind_hidden_variant', 'matched_class' => $tok ];
			}
		}

		// Source CSS contract check for display:none / visibility:hidden.
		$raw_css = (string) ( $this->intel->raw_css ?? '' );
		if ( '' === $raw_css ) {
			return [ 'hidden' => false, 'reason' => '', 'matched_class' => '' ];
		}

		foreach ( $tokens as $tok ) {
			$tok = trim( (string) $tok );
			if ( '' === $tok ) {
				continue;
			}
			// Match both normal and Tailwind-escaped class selectors.
			$tok_escaped = str_replace( ':', '\\\\:', $tok );
			$re = '/\.(?:' . preg_quote( $tok, '/' ) . '|' . preg_quote( $tok_escaped, '/' ) . ')\s*\{[^}]*?(display\s*:\s*none|visibility\s*:\s*hidden)/i';
			if ( preg_match( $re, $raw_css ) ) {
				return [ 'hidden' => true, 'reason' => 'source_css_contract', 'matched_class' => $tok ];
			}
		}

		return [ 'hidden' => false, 'reason' => '', 'matched_class' => '' ];
	}

	// ═══════════════════════════════════════════════════════════
	// PASS 7: CONTENT EXTRACTION (feeds Template Library)
	// Extract meaningful content from DOM to populate templates.
	// ═══════════════════════════════════════════════════════════

	private function extract_section_content( \DOMElement $node, string $type, \DOMXPath $xp ): array {
		$content = [
			'project_name' => $this->project_name,
			'source_class' => trim( (string) $node->getAttribute( 'class' ) ),
			'source_id'    => trim( (string) $node->getAttribute( 'id' ) ),
		];

		// Section-level header elements.
		$content['tag']   = $this->get_text( $xp, ['.//*[contains(@class,"tag")]','.//*[contains(@class,"eyebrow")]','.//*[contains(@class,"label")]'], $node, 80 );
		$content['title'] = $this->get_text( $xp, ['.//h2','.//h3'], $node, 300 );
		$content['desc']  = $this->get_para( $node, $xp );

		switch ( $type ) {
			case 'marquee':
				$items = [];
				$item_nodes = $xp->query('.//*[contains(@class,"item") or contains(@class,"logo") or contains(@class,"brand") or contains(@class,"marquee-item")]', $node);
				if ( $item_nodes ) {
					foreach ( $item_nodes as $in ) {
						$txt = trim(strip_tags($in->textContent));
						// Remove symbol bullets (like ◆) at the start
						$txt = preg_replace('/^[^a-zA-Z0-9]+/', '', $txt);
						if ( $txt && strlen($txt) > 2 && strlen($txt) < 80 ) $items[] = trim($txt);
					}
				}
				$content['items'] = array_unique($items) ?: null;
				break;

			case 'hero':
				$content['eyebrow']       = $this->get_text( $xp, ['.//*[contains(@class,"eyebrow")]','.//*[contains(@class,"hero-eyebrow")]'], $node, 80 );
				$content['headline']      = $this->get_heading( $node, $xp, ['h1','h2'] );
				$content['sub']           = $this->get_para( $node, $xp );
				$content['cta_primary']   = $this->normalize_cta_text( $this->get_btn( $node, $xp, 0 ) );
				$content['cta_secondary'] = $this->get_btn( $node, $xp, 1 );
				break;

			case 'stats':
				$stats = [];
				$stat_nodes = $this->find_stat_nodes( $node, $xp );
				if ( $stat_nodes ) {
					$seen_stat_nodes = [];
					foreach ( $stat_nodes as $stat_node ) {
						$oid = spl_object_id( $stat_node );
						if ( isset( $seen_stat_nodes[ $oid ] ) ) {
							continue;
						}
						$seen_stat_nodes[ $oid ] = true;

						$num_el = $this->find_stat_number_element( $stat_node, $xp );
						if ( ! $num_el ) {
							continue;
						}

						$target = trim( $num_el->getAttribute('data-target') );
						$raw_num_text = trim( preg_replace( '/\s+/', ' ', $num_el->textContent ) );
						$num = $target ?: preg_replace('/[^\d.]/','', $raw_num_text);
						if ( ! $num ) {
							continue;
						}

						$unit_el = $xp->query('.//*[contains(@class,"unit")]', $stat_node)->item(0);
						$label_el = $xp->query('.//*[contains(@class,"label") or contains(@class,"caption") or contains(@class,"desc") or contains(@class,"copy") or contains(@class,"eyebrow")]', $stat_node)->item(0);

						$unit = $unit_el ? trim( preg_replace( '/\s+/', ' ', $unit_el->textContent ) ) : '';
						if ( ! $unit && preg_match( '/[^\d.\s]+$/u', $raw_num_text, $unit_match ) ) {
							$unit = trim( $unit_match[0] );
						}

						$label = $label_el ? trim( preg_replace( '/\s+/', ' ', $label_el->textContent ) ) : '';
						if ( ! $label ) {
							$label = $this->infer_stat_label( $stat_node, $xp, $raw_num_text, $unit );
						}
						$dec = str_contains($num,'.') ? strlen(substr($num,strpos($num,'.')+1)) : 0;
						$stats[] = [
							'num'   => $num,
							'unit'  => mb_substr( $unit, 0, 12 ),
							'label' => mb_substr( $label, 0, 40 ),
							'dec'   => $dec,
							'source_class' => trim( (string) $stat_node->getAttribute( 'class' ) ),
							'source_id'    => trim( (string) $stat_node->getAttribute( 'id' ) ),
						];
					}
				}
				if ( empty( $stats ) ) {
					$stats = $this->extract_simple_stats_from_html( $node );
				}
				$content['stats'] = $stats ?: null;
				break;

			case 'features':
			case 'bento':
				$content['cards']       = $this->extract_cards( $node, $xp );
				if ( 'features' === $type && empty( $content['cards'] ) ) {
					$content['cards'] = V2PrimitiveAssemblerHelper::extract_feature_cards_from_scripts( $node, $xp );
					if ( ! empty( $content['cards'] ) ) {
						$this->diagnostics[] = [
							'code'    => 'v2_script_data_cards_extracted',
							'message' => 'Feature cards extracted from source JavaScript data for native V2 widgets.',
							'context' => [
								'pass'  => 7,
								'type'  => $type,
								'count' => count( $content['cards'] ),
							],
						];
					}
				}
				$content['bento_spans'] = $this->get_bento_spans_strict( $content['cards'], $node, $xp );
				break;

			case 'process':
				$steps = $this->extract_process_steps_generic( $node, $xp );
				$content['steps'] = $steps ?: null;
				if ( ! empty( $steps[0]['desc'] ) && isset( $content['desc'] ) && trim( $content['desc'] ) === trim( $steps[0]['desc'] ) ) {
					$content['desc'] = '';
				}
				break;


			case 'testimonials':
				// ── F-03b: Use dedicated testimonial extractor that gets name + role.
				$content['cards'] = $this->extract_testimonial_cards( $node, $xp );
				if ( ! empty( $content['desc'] ) && $this->looks_like_quote( $content['desc'] ) ) {
					$content['desc'] = '';
				}
				break;

			case 'pricing':
				// ── F-03a: Per-card extraction (not whole-section regex).
				$content['cards'] = $this->extract_pricing_cards( $node, $xp );
				break;

			case 'cta':
				$content['title']         = $this->get_heading( $node, $xp, ['h2','h1','h3'] );
				$content['sub']           = $this->get_para( $node, $xp );
				$content['cta_primary']   = $this->normalize_cta_text( $this->get_btn( $node, $xp, 0 ) );
				$content['cta_secondary'] = $this->normalize_cta_text( $this->get_btn( $node, $xp, 1 ) );
				break;

			case 'footer':
				$brand_identity = $this->extract_footer_brand_identity( $node, $xp );
				$brand_name_key = strtolower( preg_replace( '/\s+/', ' ', $brand_identity['brand_name'] ?? '' ) );
				$cols = $this->extract_footer_columns_generic( $node, $xp, $brand_name_key );
				$content['cols'] = $cols ?: null;

				// ── New: Identity Extraction (Training)
				$brand_el = $xp->query('.//*[contains(@class,"brand") or contains(@class,"logo") or contains(@class,"bio")]', $node)->item(0);
				if ( $brand_el ) {
					$logo_el = $xp->query('.//a[contains(@class,"logo")] | .//img', $brand_el)->item(0);
					if ( $logo_el ) {
						// Extract real logo HTML, removing any 'hidden' or 'mobile' classes
						$logo_html = $node->ownerDocument->saveHTML($logo_el);
						$content['brand_logo'] = preg_replace('/\s+(hidden|mobile|lite|small|md:hidden)\s+/', ' ', $logo_html);
					}
					
					$bio_el = $xp->query('.//p', $brand_el)->item(0);
					if ( $bio_el ) $content['brand_desc'] = trim($bio_el->textContent);
				}
				$content = array_merge( $content, $brand_identity );
				break;
		}

		return $content;
	}

	// ═══════════════════════════════════════════════════════════
	// PASS 7: HTML WIDGET PRESERVATION
	// Preserve section with original code + extracted CSS
	// ═══════════════════════════════════════════════════════════

	private function build_html_widget_section( \DOMElement $node, string $type, \DOMXPath $xp ): array {
		$p = $this->prefix;
		$source_hook_classes = $this->source_hook_classes_for_node( $node );

		// Collect class names in this section for CSS extraction.
		$class_names = [];
		foreach ( explode( ' ', (string) $node->getAttribute( 'class' ) ) as $cls ) {
			$cls = trim( $cls );
			if ( $cls ) {
				$class_names[ $cls ] = true;
			}
		}
		$els = $xp->query('.//*[@class]', $node);
		if ($els) {
			foreach ($els as $el) {
				foreach (explode(' ', $el->getAttribute('class')) as $cls) {
					$cls = trim($cls);
					if ($cls) $class_names[$cls] = true;
				}
			}
		}

		// Extract relevant CSS rules from source stylesheet.
		$section_css = '';
		if ( $class_names && $this->intel->raw_css ) {
			$pattern = implode('|', array_map('preg_quote', array_keys($class_names)));
			// Relaxed regex matches class followed by pseudo-element, space, or child selector.
			if ( preg_match_all('/([^{}]+\{[^{}]+\})/s', $this->intel->raw_css, $rules) ) {
				foreach ($rules[1] as $rule) {
					if (preg_match('/\.(' . $pattern . ')([:\s\.\[>#]|$)/', $rule)) {
						$section_css .= trim($rule) . "\n";
					}
				}
			}
			// Include keyframes referenced in extracted rules.
			foreach ($this->css->get_keyframes() as $kf) {
				preg_match('/@keyframes\s+([\w-]+)/', $kf, $nm);
				if ($nm && str_contains($section_css, $nm[1])) $section_css .= $kf . "\n";
			}
		}

		// Get inner HTML of the section.
		$inner = $node->ownerDocument->saveHTML( $node );
		foreach ( [] as $child ) {
			$inner .= $node->ownerDocument->saveHTML($child);
		}

		$html_content = ($section_css ? "<style>\n/* {$type} — extracted from source */\n{$section_css}\n</style>\n" : '') . $inner;

		$this->warnings[] = "Section '{$type}': complex structure — original HTML preserved as widget. Review in Elementor editor.";

		$widget = $this->w('html', [
			'_css_classes' => trim( "{$p}-{$type}-widget {$source_hook_classes}" ),
			'html'         => $html_content,
		]);
		$widget['settings']['_element_id'] = "{$p}-{$type}";
		return $widget;
	}

	// ═══════════════════════════════════════════════════════════
	// GENERIC NATIVE BUILDER (fallback when library has no match)
	// ═══════════════════════════════════════════════════════════

	private function build_generic_native( \DOMElement $node, string $type, \DOMXPath $xp ): array {
		$p   = $this->prefix;
		$els = [];
		$section_source_hooks = $this->source_hook_classes_for_node( $node );

		// Section header.
		$tag   = $this->get_text($xp,['.//*[contains(@class,"tag")]','.//*[contains(@class,"eyebrow")]'],$node,80);
		$title = $this->get_heading($node,$xp,['h2','h3','h1']);
		$desc  = $this->get_para($node,$xp);

		if ($tag) $els[] = $this->w('html',['_css_classes'=>"{$p}-section-tag-widget",'html'=>"<div class=\"{$p}-section-tag\">{$tag}</div>"]);
		if ($title) {
			$els[] = $this->heading_w($title,'h2',"{$p}-section-title",[
				'typography_font_family'    => $this->f_display,
				'typography_font_weight'    => '800',
				'typography_font_size'      => ['unit'=>'px','size'=>52],
				'typography_letter_spacing' => ['unit'=>'px','size'=>-2],
				'title_color'               => $this->c_text,
			]);
		}
		if ($desc) $els[] = $this->text_w("<p>{$desc}</p>","{$p}-section-desc");

		// Cards if any.
		$cards = $this->extract_cards($node,$xp);
		if (!empty($cards)) {
			$card_widgets = [];
			foreach ($cards as $i => $card) {
				$inner = [];
				if ($card['title']) $inner[] = $this->heading_w($card['title'],'h3',"{$p}-card-title {$p}-d".(($i%3)+1),[
					'typography_font_family' => $this->f_display,'typography_font_weight'=>'700',
					'typography_font_size'=>['unit'=>'px','size'=>20],'title_color'=>$this->c_text,
				]);
				if ($card['body'])  $inner[] = $this->text_w("<p>{$card['body']}</p>","{$p}-card-body");
				if ( ! empty( $card['visual_html'] ) ) $inner[] = $this->w('html',['_css_classes'=>"{$p}-card-visual-widget {$p}-card-visual",'html'=>$card['visual_html']]);
				$card_source_hooks = trim( $this->source_hook_classes_from_parts( (string) ( $card['source_class'] ?? '' ), (string) ( $card['source_id'] ?? '' ) ) );
				$card_widgets[] = $this->con('column',trim("{$p}-{$type}-card {$p}-reveal {$card_source_hooks}"),'',$inner,[
					'background_background'=>'classic','background_color'=>$this->c_surface,
					'border_border'=>'solid','border_color'=>$this->c_border,
					'border_width'=>$this->bw(1,1,1,1),
					'padding'=>$this->pad(28,28,28,28),
				]);
			}
			$cols = count($cards) <= 2 ? '1fr 1fr' : '1fr 1fr 1fr';
			$els[] = $this->grid_con($card_widgets,$cols,"{$p}-{$type}-grid");
		}

		$blocks = [];
		if ( empty( $cards ) ) {
			$blocks = $this->extract_generic_blocks( $node, $xp );
			if ( ! empty( $blocks ) ) {
				$block_widgets = [];
				foreach ( $blocks as $i => $block ) {
					$inner = [];
					if ( ! empty( $block['title'] ) ) $inner[] = $this->heading_w($block['title'],'h3',"{$p}-card-title {$p}-d".(($i%3)+1),[
						'typography_font_family' => $this->f_display,'typography_font_weight'=>'700',
						'typography_font_size'=>['unit'=>'px','size'=>20],'title_color'=>$this->c_text,
					]);
					if ( ! empty( $block['body'] ) ) $inner[] = $this->text_w("<p>{$block['body']}</p>","{$p}-card-body");
					if ( ! empty( $block['items'] ) ) {
						$list = $this->icon_list_w( $block['items'], "{$p}-generic-list", (string) ( $block['list_icon'] ?? '' ) );
						if ( $list ) {
							$inner[] = $list;
						}
					}
					if ( ! empty( $block['visual_html'] ) ) $inner[] = $this->w('html',['_css_classes'=>"{$p}-card-visual-widget {$p}-card-visual",'html'=>$block['visual_html']]);
					if ( ! empty( $block['cta'] ) ) $inner[] = $this->btn_w($block['cta'],'#',"{$p}-btn-primary",$this->c_accent,$this->c_bg);
					if ( ! empty( $inner ) ) {
						$block_source_hooks = trim( $this->source_hook_classes_from_parts( (string) ( $block['source_class'] ?? '' ), (string) ( $block['source_id'] ?? '' ) ) );
						$block_widgets[] = $this->con('column',trim("{$p}-{$type}-card {$p}-reveal {$block_source_hooks}"),'',$inner,[
							'background_background'=>'classic','background_color'=>$this->c_surface,
							'border_border'=>'solid','border_color'=>$this->c_border,
							'border_width'=>$this->bw(1,1,1,1),
							'padding'=>$this->pad(28,28,28,28),
						]);
					}
				}
				if ( ! empty( $block_widgets ) ) {
					$cols = count($block_widgets) <= 2 ? '1fr 1fr' : '1fr 1fr 1fr';
					$els[] = $this->grid_con($block_widgets,$cols,"{$p}-{$type}-grid");
				}
			}
		}

		if ( empty( $cards ) && empty( $blocks ) ) {
			$flow_blocks = $this->extract_generic_flow_blocks( $node, $xp );
			foreach ( $flow_blocks as $i => $block ) {
				$inner = [];
				if ( ! empty( $block['title'] ) ) {
					$inner[] = $this->heading_w($block['title'],'h3',"{$p}-card-title {$p}-d".(($i%3)+1),[
						'typography_font_family' => $this->f_display,'typography_font_weight'=>'700',
						'typography_font_size'=>['unit'=>'px','size'=>20],'title_color'=>$this->c_text,
					]);
				}
				if ( ! empty( $block['body'] ) ) {
					$inner[] = $this->text_w("<p>{$block['body']}</p>","{$p}-card-body");
				}
				if ( ! empty( $block['items'] ) ) {
					$list = $this->icon_list_w( $block['items'], "{$p}-generic-list", (string) ( $block['list_icon'] ?? '' ) );
					if ( $list ) {
						$inner[] = $list;
					}
				}
				if ( ! empty( $block['visual_html'] ) ) {
					$inner[] = $this->w('html',['_css_classes'=>"{$p}-card-visual-widget {$p}-card-visual",'html'=>$block['visual_html']]);
				}
				if ( ! empty( $block['cta'] ) ) {
					$inner[] = $this->btn_w($block['cta'],'#',"{$p}-btn-primary",$this->c_accent,$this->c_bg);
				}
				if ( ! empty( $inner ) ) {
					$flow_source_hooks = trim( $this->source_hook_classes_from_parts( (string) ( $block['source_class'] ?? '' ), (string) ( $block['source_id'] ?? '' ) ) );
					$els[] = $this->con('column',trim("{$p}-{$type}-flow {$p}-reveal {$flow_source_hooks}"),'',$inner,[
						'background_background'=>'classic',
						'background_color'=>'transparent',
						'padding'=>$this->pad(0,0,0,0),
					]);
				}
			}
		}

		// Button fallback.
		$btn = $this->get_btn($node,$xp,0);
		if ($btn && empty($cards) && empty($blocks)) {
			$els[] = $this->btn_w($btn,'#',"{$p}-btn-primary",$this->c_accent,$this->c_bg);
		}

		// V2 primitive-native fallback: keep sections editable before full preserve.
		if ( 'v2' === $this->strategy && empty( $els ) ) {
			$this->append_v2_primitive_fallback_elements( $els, $node, $xp, $type );
		}

		// If nothing extracted, fall back to HTML widget.
		if (empty($els)) {
			return $this->build_html_widget_section($node,$type,$xp);
		}

		return $this->con('column',trim("{$p}-{$type} {$p}-section {$p}-reveal {$section_source_hooks}"),"{$p}-{$type}",$els,[
			'background_background'=>'classic','background_color'=>$this->c_bg,
			'border_border'=>'solid','border_color'=>$this->c_border,
			'border_width'=>$this->bw(1,0,0,0),
			'padding'=>$this->pad(120,60,120,60),
		]);
	}

	private function append_v2_primitive_fallback_elements( array &$els, \DOMElement $node, \DOMXPath $xp, string $type ): void {
		$p = $this->prefix;
		$primitives = V2PrimitiveAssemblerHelper::extract_primitives( $node, $xp );
		$added = 0;

		foreach ( (array) ( $primitives['headings'] ?? [] ) as $heading ) {
			$text = trim( (string) ( $heading['text'] ?? '' ) );
			$tag = trim( (string) ( $heading['tag'] ?? 'h2' ) );
			if ( '' === $text ) {
				continue;
			}
			$els[] = $this->heading_w( $text, in_array( $tag, [ 'h1', 'h2', 'h3', 'h4' ], true ) ? $tag : 'h2', "{$p}-{$type}-primitive-heading" );
			$added++;
		}

		foreach ( (array) ( $primitives['paragraphs'] ?? [] ) as $paragraph ) {
			$text = trim( (string) $paragraph );
			if ( '' === $text ) {
				continue;
			}
			$els[] = $this->text_w( '<p>' . esc_html( $text ) . '</p>', "{$p}-{$type}-primitive-text" );
			$added++;
		}

		foreach ( (array) ( $primitives['buttons'] ?? [] ) as $button ) {
			$text = trim( (string) ( $button['text'] ?? '' ) );
			$url = trim( (string) ( $button['url'] ?? '#' ) );
			if ( '' === $text ) {
				continue;
			}
			$els[] = $this->btn_w( $text, '' !== $url ? $url : '#', "{$p}-{$type}-primitive-btn", $this->c_accent, $this->c_bg );
			$added++;
		}

		foreach ( (array) ( $primitives['lists'] ?? [] ) as $list_items ) {
			$list = $this->icon_list_w( (array) $list_items, "{$p}-{$type}-primitive-list", 'fas fa-check' );
			if ( $list ) {
				$els[] = $list;
				$added++;
			}
		}

		foreach ( (array) ( $primitives['images'] ?? [] ) as $image ) {
			$src = trim( (string) ( $image['src'] ?? '' ) );
			if ( '' === $src ) {
				continue;
			}
			$els[] = $this->w( 'image', [
				'_css_classes' => "{$p}-{$type}-primitive-image",
				'image' => [ 'url' => $src ],
			] );
			$added++;
		}

		foreach ( (array) ( $primitives['tables'] ?? [] ) as $table_rows ) {
			$html = '<table class="' . esc_attr( "{$p}-{$type}-primitive-table" ) . '"><tbody>';
			foreach ( (array) $table_rows as $row ) {
				$html .= '<tr>';
				foreach ( (array) $row as $cell ) {
					$html .= '<td>' . esc_html( (string) $cell ) . '</td>';
				}
				$html .= '</tr>';
			}
			$html .= '</tbody></table>';
			$els[] = $this->w( 'html', [
				'_css_classes' => "{$p}-{$type}-primitive-table-widget",
				'html' => $html,
			] );
			$added++;
		}

		if ( $added > 0 ) {
			$this->diagnostics[] = [
				'code' => 'v2_primitive_fallback_applied',
				'message' => 'V2 primitive-native fallback emitted editable widgets before HTML preservation.',
				'context' => [
					'pass' => 7,
					'type' => $type,
					'widgets_added' => $added,
					'primitive_counts' => [
						'headings' => count( (array) ( $primitives['headings'] ?? [] ) ),
						'paragraphs' => count( (array) ( $primitives['paragraphs'] ?? [] ) ),
						'buttons' => count( (array) ( $primitives['buttons'] ?? [] ) ),
						'lists' => count( (array) ( $primitives['lists'] ?? [] ) ),
						'images' => count( (array) ( $primitives['images'] ?? [] ) ),
						'tables' => count( (array) ( $primitives['tables'] ?? [] ) ),
					],
				],
			];
		}
	}

	private function extract_generic_blocks( \DOMElement $node, \DOMXPath $xp ): array {
		$groups = [];
		$candidate_groups = [];

		$direct = [];
		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof \DOMElement && in_array( strtolower( $child->nodeName ), [ 'div', 'section', 'article', 'li' ], true ) ) {
				$direct[] = $child;
			}
		}
		if ( count( $direct ) >= 2 ) {
			$candidate_groups[] = $direct;
		}

		$descendants = $xp->query( './/div | .//section | .//article | .//li', $node );
		if ( $descendants && $descendants->length > 0 ) {
			$by_parent = [];
			foreach ( $descendants as $descendant ) {
				if ( ! $descendant instanceof \DOMElement ) {
					continue;
				}
				$parent = $descendant->parentNode;
				if ( ! $parent instanceof \DOMElement || $parent === $node ) {
					continue;
				}
				$key = spl_object_id( $parent );
				$by_parent[ $key ][] = $descendant;
			}
			foreach ( $by_parent as $group ) {
				if ( count( $group ) >= 2 ) {
					$candidate_groups[] = $group;
				}
			}
		}

		foreach ( $candidate_groups as $candidate_group ) {
			$parsed = [];
			foreach ( $candidate_group as $candidate ) {
				$block = $this->build_generic_block_payload( $candidate, $xp );
				if ( $this->is_generic_block_payload_useful( $block ) ) {
					$parsed[] = $block;
				}
			}
			if ( count( $parsed ) >= 2 ) {
				$groups = $parsed;
				break;
			}
		}

		return $groups;
	}

	private function extract_generic_flow_blocks( \DOMElement $node, \DOMXPath $xp ): array {
		$blocks = [];

		foreach ( $node->childNodes as $child ) {
			if ( ! $child instanceof \DOMElement ) {
				continue;
			}

			$tag = strtolower( $child->nodeName );
			if ( in_array( $tag, [ 'script', 'style', 'noscript' ], true ) ) {
				continue;
			}

			$block = $this->build_generic_block_payload( $child, $xp );
			if ( $this->is_generic_block_payload_useful( $block ) ) {
				$blocks[] = $block;
			}
		}

		if ( empty( $blocks ) ) {
			$block = $this->build_generic_block_payload( $node, $xp );
			if ( $this->is_generic_block_payload_useful( $block ) ) {
				$blocks[] = $block;
			}
		}

		return $blocks;
	}

	private function build_generic_block_payload( \DOMElement $node, \DOMXPath $xp ): array {
		$list_items = [];
		$list_nodes = $xp->query( './/ul/li | .//ol/li', $node );
		if ( $list_nodes ) {
			foreach ( $list_nodes as $li ) {
				$text = trim( preg_replace( '/\s+/', ' ', (string) $li->textContent ) );
				if ( '' !== $text && strlen( $text ) <= 160 ) {
					$list_items[] = $text;
				}
			}
		}
		$list_icon = $this->infer_list_icon_for_node( $node );

		return [
			'title'       => $this->get_heading( $node, $xp, [ 'h3', 'h4', 'h2', 'h5', 'strong' ] ),
			'body'        => $this->get_para( $node, $xp ),
			'items'       => array_values( array_unique( $list_items ) ),
			'list_icon'   => $list_icon['icon'] ?? '',
			'cta'         => $this->get_btn( $node, $xp, 0 ),
			'visual_html' => $this->extract_complex_visual( $node ),
			'source_class'=> trim( (string) $node->getAttribute( 'class' ) ),
			'source_id'   => trim( (string) $node->getAttribute( 'id' ) ),
		];
	}

	private function is_generic_block_payload_useful( array $block ): bool {
		return '' !== trim( (string) ( $block['title'] ?? '' ) )
			|| '' !== trim( (string) ( $block['body'] ?? '' ) )
			|| ! empty( $block['items'] )
			|| '' !== trim( (string) ( $block['cta'] ?? '' ) )
			|| ! empty( $block['visual_html'] );
	}

	// ═══════════════════════════════════════════════════════════
	// PASS 9: VALIDATION & REPAIR
	// ═══════════════════════════════════════════════════════════

	private function validate_and_repair( array $elements ): array {
		$repaired    = [];
		$seen_ids    = []; // Elementor element 'id' (random hex)
		$seen_el_ids = []; // settings '_element_id' (CSS anchor IDs)

		foreach ( $elements as $el ) {
			$el = $this->repair_element( $el, $seen_ids, $seen_el_ids );
			if ( $el ) $repaired[] = $el;
		}

		return $this->enforce_top_level_container_contract( $repaired );
	}

	/**
	 * Elementor import compatibility guard:
	 * top-level page content must be containers/sections, not loose widgets.
	 */
	private function enforce_top_level_container_contract( array $elements ): array {
		$wrapped = [];
		foreach ( $elements as $idx => $element ) {
			if ( ! is_array( $element ) ) {
				continue;
			}
			$el_type = (string) ( $element['elType'] ?? '' );
			if ( 'widget' !== $el_type ) {
				$wrapped[] = $element;
				continue;
			}
			$source_classes = trim( (string) ( $element['settings']['_css_classes'] ?? '' ) );
			$section_classes = trim( $source_classes . ' sb-top-level-widget-wrap' );
			$section = $this->con( 'column', $section_classes, '', [ $element ], [], false );
			$section['settings']['_element_id'] = (string) ( $element['settings']['_element_id'] ?? ( $this->prefix . '-wrap-' . ( $idx + 1 ) ) );
			$wrapped[] = $section;
			$this->warnings[] = 'Repair: wrapped top-level widget in container for Elementor import compatibility.';
		}
		return $wrapped;
	}

	/**
	 * Create a hard failure with pass diagnostics.
	 *
	 * @param string $code Error code.
	 * @param string $message Error message.
	 * @param array  $context Debug context.
	 * @return WP_Error
	 */
	private function fail_conversion( string $code, string $message, array $context = [] ): WP_Error {
		$this->diagnostics[] = [
			'code'    => $code,
			'message' => $message,
			'context' => $context,
		];

		return new WP_Error(
			$code,
			$message,
			[
				'status'      => 422,
				'diagnostics' => $this->diagnostics,
			]
		);
	}

	/**
	 * Pre-resolve Tailwind utility classes into synthetic CSS rules so downstream
	 * classification and style mapping can work with computed styles.
	 */
	private function apply_tailwind_pre_resolution( \DOMDocument $dom ): void {
		$tokens = $this->collect_class_tokens( $dom );
		$resolved = 0;
		$rules = [];
		$unresolved_samples = [];
		$resolved_samples = [];
		foreach ( $tokens as $token ) {
			$decl = $this->tailwind_resolver->resolve_class( $token );
			if ( ! is_string( $decl ) || '' === trim( $decl ) ) {
				if ( count( $unresolved_samples ) < 24 ) {
					$unresolved_samples[] = $token;
				}
				continue;
			}
			$resolved++;
			if ( count( $resolved_samples ) < 12 ) {
				$resolved_samples[] = $token;
			}
			$safe = sanitize_html_class( $token );
			if ( '' === $safe ) {
				continue;
			}
			$rules[] = '.' . $safe . '{' . $decl . ';}';
		}
		$rules = array_values( array_unique( $rules ) );
		if ( ! empty( $rules ) ) {
			$this->intel->raw_css .= "\n/* Tailwind Pre-Resolution (compiled) */\n" . implode( "\n", $rules ) . "\n";
		}

		$this->tailwind_coverage = [
			'detected'      => true,
			'scanned'       => count( $tokens ),
			'resolved'      => $resolved,
			'generated_css' => count( $rules ),
		];
		$this->diagnostics[] = [
			'code'    => 'tailwind_pre_resolution',
			'message' => sprintf( 'Tailwind pre-resolution generated %d synthetic rules from %d class tokens.', count( $rules ), count( $tokens ) ),
			'context' => [
				'pass'          => 2,
				'resolved'      => $resolved,
				'generated_css' => count( $rules ),
				'scanned'       => count( $tokens ),
				'samples'       => [
					'resolved'   => $resolved_samples,
					'unresolved' => $unresolved_samples,
				],
			],
		];
	}

	/**
	 * Collect unique class tokens from the DOM.
	 *
	 * @return string[]
	 */
	private function collect_class_tokens( \DOMDocument $dom ): array {
		$xp = new \DOMXPath( $dom );
		$nodes = $xp->query( '//*[@class]' );
		$tokens = [];
		if ( $nodes ) {
			foreach ( $nodes as $node ) {
				foreach ( preg_split( '/\s+/', trim( (string) $node->getAttribute( 'class' ) ) ) as $cls ) {
					$cls = trim( (string) $cls );
					if ( '' !== $cls ) {
						$tokens[ $cls ] = true;
					}
				}
			}
		}
		return array_values( array_keys( $tokens ) );
	}

	/**
	 * Hard-fail when Tailwind was detected but produced no usable pre-resolution coverage.
	 */
	private function assert_tailwind_resolution_integrity(): ?WP_Error {
		if ( empty( $this->tailwind_coverage['detected'] ) ) {
			return null;
		}
		$resolved = (int) ( $this->tailwind_coverage['resolved'] ?? 0 );
		$generated = (int) ( $this->tailwind_coverage['generated_css'] ?? 0 );
		$scanned = (int) ( $this->tailwind_coverage['scanned'] ?? 0 );
		if ( $scanned > 0 && ( 0 === $resolved || 0 === $generated ) ) {
			return $this->fail_conversion(
				'tailwind_resolution_failed',
				'Tailwind-like markup was detected, but no utility class mappings were resolved into CSS.',
				[
					'pass'     => 2,
					'coverage' => $this->tailwind_coverage,
				]
			);
		}
		return null;
	}

	/**
	 * Hard-fail if compiled simulation knowledge is missing critical hard-rule coverage.
	 */
	private function assert_simulation_knowledge_integrity(): ?WP_Error {
		$rules = SimulationKnowledge::hard_rules();
		$count = is_array( $rules ) ? count( $rules ) : 0;
		$ids = [];
		foreach ( (array) $rules as $rule ) {
			if ( is_array( $rule ) && ! empty( $rule['id'] ) ) {
				$ids[] = (string) $rule['id'];
			}
		}
		$ids = array_values( array_unique( $ids ) );
		$required = [ 'RULE-001', 'RULE-002', 'RULE-003', 'RULE-004', 'RULE-005' ];
		$missing = array_values( array_diff( $required, $ids ) );

		$this->diagnostics[] = [
			'code'    => 'simulation_knowledge_coverage',
			'message' => sprintf( 'Compiled hard-rule coverage: %d rules, %d unique IDs.', $count, count( $ids ) ),
			'context' => [
				'pass'        => 1,
				'rule_count'  => $count,
				'rule_ids'    => $ids,
				'missing_ids' => $missing,
			],
		];

		if ( $count < 3 || ! empty( $missing ) ) {
			return $this->fail_conversion(
				'simulation_knowledge_coverage_failed',
				'Compiled simulation hard-rule coverage is below required minimum.',
				[
					'pass'        => 1,
					'rule_count'  => $count,
					'rule_ids'    => $ids,
					'missing_ids' => $missing,
				]
			);
		}
		return null;
	}

	/**
	 * Record how a section was rendered so preserved/hybrid/native modes are explicit.
	 *
	 * @param string      $type Section type.
	 * @param string      $render_mode Final render mode.
	 * @param string      $decision Strategy decision.
	 * @param \DOMElement $node Source node.
	 * @param array       $content Extracted payload.
	 * @return void
	 */
	private function record_section_diagnostic( string $type, string $render_mode, string $decision, \DOMElement $node, array $content, array $output_element, \DOMXPath $xp ): void {
		$widget_counts = $this->count_widgets_in_output_tree( $output_element );
		$matrix_checks = $this->evaluate_widget_matrix_contracts( $node, $render_mode, $widget_counts, $xp );
		$this->diagnostics[] = [
			'code'    => 'section_render_mode',
			'message' => sprintf( 'Section "%s" rendered as %s.', $type, $render_mode ),
			'context' => [
				'type'            => $type,
				'render_mode'     => $render_mode,
				'decision'        => $decision,
				'tag'             => strtolower( $node->nodeName ),
				'has_children'    => $node->childNodes->length > 0,
				'payload_keys'    => array_values( array_keys( $content ) ),
				'preserved_source'=> ! empty( $content['preserved_source'] ),
				'widget_counts'   => $widget_counts,
				'matrix_checks'   => $matrix_checks,
			],
		];

		foreach ( $matrix_checks as $check ) {
			if ( empty( $check['ok'] ) ) {
				$this->diagnostics[] = [
					'code'    => 'widget_matrix_violation',
					'message' => (string) ( $check['message'] ?? 'Widget matrix contract violation.' ),
					'context' => [
						'type'         => $type,
						'render_mode'  => $render_mode,
						'rule'         => (string) ( $check['rule'] ?? '' ),
						'widget_counts'=> $widget_counts,
					],
				];
			}
		}
	}

	/**
	 * Count widget families in emitted section tree.
	 *
	 * @param array<string,mixed> $node
	 * @return array<string,int>
	 */
	private function count_widgets_in_output_tree( array $node ): array {
		$counts = [
			'heading' => 0,
			'text-editor' => 0,
			'button' => 0,
			'icon-list' => 0,
			'image' => 0,
			'video' => 0,
			'html' => 0,
			'container' => 0,
		];
		$walk = function( array $n ) use ( &$walk, &$counts ): void {
			$el_type = (string) ( $n['elType'] ?? '' );
			if ( 'container' === $el_type ) {
				$counts['container']++;
			}
			if ( 'widget' === $el_type ) {
				$w = (string) ( $n['widgetType'] ?? '' );
				if ( isset( $counts[ $w ] ) ) {
					$counts[ $w ]++;
				} elseif ( in_array( $w, [ 'image-gallery' ], true ) ) {
					$counts['image']++;
				}
			}
			foreach ( (array) ( $n['elements'] ?? [] ) as $child ) {
				if ( is_array( $child ) ) {
					$walk( $child );
				}
			}
		};
		$walk( $node );
		return $counts;
	}

	/**
	 * Enforce key contracts from ELEMENTOR_FREE_WIDGET_MATRIX with diagnostics.
	 *
	 * @return array<int,array<string,mixed>>
	 */
	private function evaluate_widget_matrix_contracts( \DOMElement $source_node, string $render_mode, array $widget_counts, \DOMXPath $xp ): array {
		$checks = [];
		$list_nodes = $xp->query( './/ul/li | .//ol/li', $source_node );
		$has_real_list = (bool) ( $list_nodes && $list_nodes->length >= 2 );
		$has_html = (int) ( $widget_counts['html'] ?? 0 ) > 0;
		$has_icon_list = (int) ( $widget_counts['icon-list'] ?? 0 ) > 0;
		$checks[] = [
			'rule' => 'matrix_icon_list_for_real_lists',
			'ok' => ( ! $has_real_list ) || $has_icon_list || $has_html,
			'message' => 'Source has real list structure but output has neither icon-list nor preserved HTML list fragment.',
		];

		$cta_nodes = $xp->query( './/a | .//button', $source_node );
		$has_cta = (bool) ( $cta_nodes && $cta_nodes->length > 0 );
		$has_button = (int) ( $widget_counts['button'] ?? 0 ) > 0;
		$checks[] = [
			'rule' => 'matrix_button_for_cta',
			'ok' => ( ! $has_cta ) || $has_button || $has_html,
			'message' => 'Source has CTA/link/button signals but output lacks button widget and preserved HTML fallback.',
		];

		$heading_nodes = $xp->query( './/h1 | .//h2 | .//h3 | .//h4 | .//h5 | .//h6', $source_node );
		$has_headings = (bool) ( $heading_nodes && $heading_nodes->length > 0 );
		$has_heading_widget = (int) ( $widget_counts['heading'] ?? 0 ) > 0;
		$checks[] = [
			'rule' => 'matrix_heading_for_heading_tags',
			'ok' => ( ! $has_headings ) || $has_heading_widget || $has_html,
			'message' => 'Source has heading tags but output lacks heading widget and preserved HTML fallback.',
		];

		$inline_markup_nodes = $xp->query( './/h1//span | .//h1//em | .//h2//span | .//h2//em | .//a//span', $source_node );
		$has_inline_markup = (bool) ( $inline_markup_nodes && $inline_markup_nodes->length > 0 );
		$checks[] = [
			'rule' => 'matrix_inline_markup_survival',
			'ok' => ( ! $has_inline_markup ) || $has_html || $has_heading_widget || (int) ( $widget_counts['text-editor'] ?? 0 ) > 0,
			'message' => 'Source has inline markup emphasis but output has no obvious native/preserved carrier for inline markup.',
		];

		// If fully preserved source mode, matrix checks should never hard-fail.
		if ( 'fully_preserved_source' === $render_mode ) {
			foreach ( $checks as &$check ) {
				$check['ok'] = true;
			}
			unset( $check );
		}

		return $checks;
	}

	/**
	 * Ensure global setup HTML does not contain known-bad unresolved tokens.
	 *
	 * @return WP_Error|null
	 */
	private function assert_global_setup_integrity(): ?WP_Error {
		$global = $this->elements[0] ?? null;
		$html   = $global['settings']['html'] ?? '';

		$has_required_vars = is_string( $html )
			&& str_contains( $html, ':root{' )
			&& str_contains( $html, "--{$this->prefix}-bg" )
			&& str_contains( $html, "--{$this->prefix}-accent" )
			&& str_contains( $html, "--{$this->prefix}-text" );

		if ( ! is_string( $html ) || ! $has_required_vars ) {
			return $this->fail_conversion(
				'global_setup_missing',
				'Global setup HTML widget was not assembled correctly.',
				[
					'pass'    => 6,
					'element' => 'global_setup',
				]
			);
		}

		if ( preg_match( '/var\(--font[-\w]*\)|var%28--font[-\w]*%29/i', $html ) ) {
			return $this->fail_conversion(
				'unresolved_font_tokens',
				'Global setup contains unresolved font CSS variables in the Google Fonts payload.',
				[
					'pass'    => 6,
					'element' => 'global_setup',
					'prefix'  => $this->prefix,
				]
			);
		}

		return null;
	}

	/**
	 * Reject prototype sheets and demo switchers that are not single-page site inputs.
	 *
	 * @param \DOMDocument $dom Parsed DOM.
	 * @param string       $html Original HTML.
	 * @return WP_Error|null
	 */
	private function assert_supported_input_shape( \DOMDocument $dom, string $html ): ?WP_Error {
		$xp            = new \DOMXPath( $dom );
		$demo_sections = $xp->query( '//*[contains(@class,"demo-section")]' );
		$demo_tabs     = $xp->query( '//*[contains(@class,"demo-tab")]' );
		$drawer_nodes  = $xp->query( '//*[contains(@class,"m-drawer")]' );
		$header_nodes  = $xp->query( '//header[contains(@class,"site-header")]' );
		$tables        = $xp->query( '//table' );
		$forms         = $xp->query( '//form | //input | //select | //textarea' );
		$asides        = $xp->query( '//aside | //*[(contains(@class,"sidebar") or contains(@id,"sidebar"))]' );
		$footers       = $xp->query( '//footer | //*[(contains(@class,"footer") or contains(@id,"footer"))]' );
		$headings      = $xp->query( '//h1 | //h2 | //h3' );
		$panels        = $xp->query( '//*[
			contains(@class,"panel")
			or contains(@class,"chart")
			or contains(@class,"graph")
			or contains(@class,"datatable")
			or contains(@class,"table")
			or contains(@class,"kanban")
			or contains(@class,"analytics")
			or contains(@class,"admin")
		]' );
		$nav_items      = $xp->query( '//nav//*[self::a or self::button or self::span] | //aside//*[self::a or self::button or self::span]' );

		$is_demo_sheet = ( $demo_sections && $demo_sections->length >= 2 )
			|| ( $demo_tabs && $demo_tabs->length >= 2 )
			|| (
				$header_nodes
				&& $header_nodes->length >= 1
				&& $drawer_nodes
				&& $drawer_nodes->length >= 2
					&& preg_match( '/SECTION\s+[1-9]|Prototype|Main Header|Blog Header/i', $html )
			);

		if ( $is_demo_sheet ) {
			return $this->fail_conversion(
				'unsupported_prototype_sheet',
				'This input is a multi-state prototype sheet or component showcase, not a single page conversion target.',
				[
					'pass'          => 1,
					'demo_sections' => $demo_sections ? $demo_sections->length : 0,
					'demo_tabs'     => $demo_tabs ? $demo_tabs->length : 0,
					'drawers'       => $drawer_nodes ? $drawer_nodes->length : 0,
				]
			);
		}

		$dashboard_score = 0;
		$lower_html      = strtolower( $html );
		$matched_keywords = [];
		$strong_keywords = [ 'dashboard', 'analytics', 'admin', 'crm', 'kanban', 'datatable' ];

		$keyword_weights = [
			'dashboard'    => 4,
			'analytics'    => 3,
			'admin'        => 3,
			'crm'          => 3,
			'sidebar'      => 3,
			'topbar'       => 2,
			'toolbar'      => 2,
			'datatable'    => 3,
			'kanban'       => 3,
			'chart'        => 2,
			'revenue'      => 2,
			'customers'    => 2,
			'settings'     => 2,
			'notifications'=> 2,
			'inbox'        => 2,
		];

		foreach ( $keyword_weights as $keyword => $weight ) {
			if ( str_contains( $lower_html, $keyword ) ) {
				$dashboard_score += $weight;
				$matched_keywords[] = $keyword;
			}
		}
		$matched_keywords = array_values( array_unique( $matched_keywords ) );
		$strong_keyword_hits = array_values( array_intersect( $matched_keywords, $strong_keywords ) );

		if ( $tables && $tables->length >= 1 ) {
			$dashboard_score += 3;
		}
		if ( $forms && $forms->length >= 8 ) {
			$dashboard_score += 2;
		}
		if ( $asides && $asides->length >= 1 ) {
			$dashboard_score += 3;
		}
		if ( $panels && $panels->length >= 6 ) {
			$dashboard_score += 3;
		}
		if ( $nav_items && $nav_items->length >= 14 ) {
			$dashboard_score += 2;
		}

		$structural_signals = [
			'tables'    => $tables ? $tables->length : 0,
			'forms'     => $forms ? $forms->length : 0,
			'asides'    => $asides ? $asides->length : 0,
			'panels'    => $panels ? $panels->length : 0,
			'nav_items' => $nav_items ? $nav_items->length : 0,
			'footers'   => $footers ? $footers->length : 0,
			'headings'  => $headings ? $headings->length : 0,
		];

		$dashboard_structure_count = 0;
		if ( $structural_signals['tables'] >= 1 ) {
			$dashboard_structure_count++;
		}
		if ( $structural_signals['forms'] >= 8 ) {
			$dashboard_structure_count++;
		}
		if ( $structural_signals['asides'] >= 1 ) {
			$dashboard_structure_count++;
		}
		if ( $structural_signals['panels'] >= 6 ) {
			$dashboard_structure_count++;
		}
		if ( $structural_signals['nav_items'] >= 14 ) {
			$dashboard_structure_count++;
		}

		$app_shell = (
			$asides
			&& $asides->length >= 1
			&& $panels
			&& $panels->length >= 6
			&& $nav_items
			&& $nav_items->length >= 8
			&& ( ( $tables && $tables->length >= 1 ) || ( $forms && $forms->length >= 6 ) )
		);

		$has_explicit_dashboard_keywords = preg_match(
			'/\b(dashboard|analytics|admin|crm|kanban|datatable)\b/i',
			$html
		);
		$marketing_page_cues = (
			( $footers && $footers->length >= 1 && $headings && $headings->length >= 3 )
			|| preg_match( '/\b(pricing|testimonial|features|faq|contact|subscribe|book demo|start free|learn more|hero|cta|footer)\b/i', $html )
		);

		$this->diagnostics[] = [
			'code'    => 'supported_input_shape_audit',
			'message' => 'Supported-input-shape audit recorded.',
			'context' => [
				'pass'                       => 1,
				'dashboard_score'            => $dashboard_score,
				'matched_keywords'           => $matched_keywords,
				'strong_keyword_hits'        => $strong_keyword_hits,
				'dashboard_structure_count'  => $dashboard_structure_count,
				'app_shell'                  => $app_shell,
				'marketing_page_cues'        => (bool) $marketing_page_cues,
				'structural_signals'         => $structural_signals,
			],
		];

		$hard_dashboard_match = (
			(
				$dashboard_score >= 10
				&& $has_explicit_dashboard_keywords
				&& count( $strong_keyword_hits ) >= 1
				&& $dashboard_structure_count >= 2
			)
			|| (
				$app_shell
				&& count( $strong_keyword_hits ) >= 1
			)
			|| (
				$app_shell
				&& $dashboard_structure_count >= 4
				&& ! $marketing_page_cues
			)
		);

		if ( $hard_dashboard_match && ! $marketing_page_cues ) {
			return $this->fail_conversion(
				'unsupported_dashboard_ui',
				'This input looks like a dashboard, app UI, or admin interface rather than a supported page conversion target.',
				[
					'pass'            => 1,
					'dashboard_score' => $dashboard_score,
					'matched_keywords'=> $matched_keywords,
					'strong_keyword_hits' => $strong_keyword_hits,
					'dashboard_structure_count' => $dashboard_structure_count,
					'app_shell'       => $app_shell,
					'marketing_page_cues' => (bool) $marketing_page_cues,
					'structural_signals'  => $structural_signals,
				]
			);
		}

		if (
			( $dashboard_score >= 12 && count( $strong_keyword_hits ) >= 1 && ! $marketing_page_cues )
			|| ( $app_shell && ! $marketing_page_cues )
		) {
			$this->warnings[] = sprintf(
				'Input-shape audit: dashboard/app-shell heuristics were triggered (score %d) but not hard-blocked.',
				$dashboard_score
			);
		}

		return null;
	}

	/**
	 * Fail loudly on known-bad output states.
	 *
	 * @param array $elements Repaired top-level elements.
	 * @return WP_Error|null
	 */
	private function assert_output_integrity( array $elements ): ?WP_Error {
		$this->record_emitted_hook_diagnostics( $elements );
		$this->record_global_setup_asset_diagnostics();
		$this->record_architecture_compliance_diagnostics( $elements );

		$missing_sections = array_values(
			array_diff(
				array_filter( $this->detected_section_types, fn( $type ) => 'generic' !== $type ),
				array_unique( $this->built_section_types )
			)
		);

		if ( ! empty( $missing_sections ) ) {
			return $this->fail_conversion(
				'missing_sections',
				'One or more detected sections were not assembled into Elementor output.',
				[
					'pass'             => 7,
					'detected_sections' => $this->detected_section_types,
					'missing_sections'  => $missing_sections,
				]
			);
		}

		foreach ( $elements as $element ) {
			$element_id = $element['settings']['_element_id'] ?? '';
			if (
				'container' === ( $element['elType'] ?? '' ) &&
				is_string( $element_id ) &&
				str_starts_with( $element_id, $this->prefix . '-' ) &&
				empty( $element['elements'] )
			) {
				return $this->fail_conversion(
					'empty_section_shell',
					sprintf( 'Generated section "%s" is an empty container shell.', $element_id ),
					[
						'pass'       => 7,
						'element_id' => $element_id,
					]
				);
			}
		}

		$placeholder = $this->find_placeholder_leak( $elements );
		if ( $placeholder ) {
			return $this->fail_conversion(
				'placeholder_leakage',
				sprintf( 'Template placeholder "%s" leaked into the generated output.', $placeholder['match'] ),
				[
					'pass'    => 7,
					'path'    => $placeholder['path'],
					'element' => $placeholder['element_id'],
				]
			);
		}

		$preservation_issue = $this->find_preservation_integrity_issue( $elements );
		if ( $preservation_issue ) {
			return $this->fail_conversion(
				$preservation_issue['code'],
				$preservation_issue['message'],
				$preservation_issue['context']
			);
		}

		$coverage_issue = $this->find_coverage_integrity_issue();
		if ( $coverage_issue ) {
			return $this->fail_conversion(
				$coverage_issue['code'],
				$coverage_issue['message'],
				$coverage_issue['context']
			);
		}

		$footer_issue = $this->find_duplicate_footer_titles( $elements );
		if ( $footer_issue ) {
			return $this->fail_conversion(
				'duplicate_footer_columns',
				sprintf( 'Footer contains duplicate column title "%s".', $footer_issue['title'] ),
				[
					'pass'       => 7,
					'element_id' => $footer_issue['element_id'],
					'title'      => $footer_issue['title'],
				]
			);
		}

		$extraction_issue = $this->find_required_extraction_gaps();
		if ( $extraction_issue ) {
			return $this->fail_conversion(
				$extraction_issue['code'],
				$extraction_issue['message'],
				$extraction_issue['context']
			);
		}

		$structural_issue = $this->find_structural_fidelity_issue( $elements );
		if ( $structural_issue ) {
			return $this->fail_conversion(
				$structural_issue['code'],
				$structural_issue['message'],
				$structural_issue['context']
			);
		}

		return null;
	}

	private function record_emitted_hook_diagnostics( array $elements ): void {
		$inventory = $this->collect_emitted_hook_inventory( $elements );
		$this->emitted_hook_inventory = $inventory;
		$missing   = [];

		foreach ( $this->class_map as $entry ) {
			$hook = trim( (string) ( $entry['class'] ?? '' ) );
			if ( '' === $hook ) {
				continue;
			}

			if ( isset( $inventory['ids'][ $hook ] ) || isset( $inventory['classes'][ $hook ] ) ) {
				continue;
			}

			$missing[] = [
				'hook'     => $hook,
				'element'  => (string) ( $entry['element'] ?? '' ),
				'location' => (string) ( $entry['location'] ?? '' ),
			];
		}

		$this->diagnostics[] = [
			'code'    => 'emitted_hook_inventory',
			'message' => 'Emitted hook inventory collected for output validation.',
			'context' => [
				'class_count'   => count( $inventory['classes'] ),
				'id_count'      => count( $inventory['ids'] ),
				'missing_hooks' => $missing,
			],
		];

		if ( ! empty( $missing ) ) {
			$this->warnings[] = sprintf( 'Hook inventory found %d class map entries that do not exist in emitted output.', count( $missing ) );
		}
	}

	private function record_global_setup_asset_diagnostics(): void {
		$global_html = (string) ( $this->elements[0]['settings']['html'] ?? '' );
		if ( '' === $global_html ) {
			return;
		}

		$has_canvas_output = (bool) preg_match(
			'/\bid\s*=\s*(["\'])' . preg_quote( $this->prefix . '-bg-canvas', '/' ) . '\\1/i',
			$global_html
		) || str_contains( $global_html, "cv.id='{$this->prefix}-bg-canvas'" ) || str_contains( $global_html, "cv.id=\"{$this->prefix}-bg-canvas\"" );

		$context = [
			'has_canvas_source'  => $this->intel->has_canvas,
			'has_cursor_source'  => $this->intel->has_cursor,
			'has_canvas_output'  => $has_canvas_output,
			'has_cursor_output'  => str_contains( $global_html, $this->prefix . '-cursor-dot' ) && str_contains( $global_html, $this->prefix . '-cursor-ring' ),
			'has_source_js'      => '' !== trim( $this->intel->raw_js ),
			'has_script_bridge'  => str_contains( $global_html, 'Source Script Bridge' ),
		];
		$this->global_setup_asset_inventory = $context;

		$this->diagnostics[] = [
			'code'    => 'global_setup_asset_inventory',
			'message' => 'Global setup asset diagnostics recorded.',
			'context' => $context,
		];

		if ( $context['has_canvas_source'] && ! $context['has_canvas_output'] ) {
			$this->warnings[] = 'Global setup diagnostics: source indicates canvas behavior, but prefixed canvas output was not found.';
		}
		if ( $context['has_cursor_source'] && ! $context['has_cursor_output'] ) {
			$this->warnings[] = 'Global setup diagnostics: source indicates cursor behavior, but prefixed cursor output was not found.';
		}
	}

	private function collect_emitted_hook_inventory( array $elements ): array {
		$inventory = [
			'classes'           => [],
			'ids'               => [],
			'icon_list_classes' => [],
			'icon_list_ids'     => [],
			'text_classes'      => [],
			'text_ids'          => [],
			'heading_classes'   => [],
			'heading_ids'       => [],
			'button_classes'    => [],
			'button_ids'        => [],
			'image_classes'     => [],
			'image_ids'         => [],
			'media_classes'     => [],
			'media_ids'         => [],
		];

		$walk = function( array $node ) use ( &$walk, &$inventory ): void {
			$settings = $node['settings'] ?? [];
			$classes  = trim( (string) ( $settings['_css_classes'] ?? '' ) );
			$element_id = trim( (string) ( $settings['_element_id'] ?? '' ) );

			if ( '' !== $classes ) {
				foreach ( preg_split( '/\s+/', $classes ) as $class_name ) {
					$class_name = trim( (string) $class_name );
					if ( '' !== $class_name ) {
						$inventory['classes'][ $class_name ] = true;
					}
				}
			}

			if ( '' !== $element_id ) {
				$inventory['ids'][ $element_id ] = true;
			}

			if ( 'icon-list' === ( $node['widgetType'] ?? '' ) ) {
				if ( '' !== $classes ) {
					foreach ( preg_split( '/\s+/', $classes ) as $class_name ) {
						$class_name = trim( (string) $class_name );
						if ( '' !== $class_name ) {
							$inventory['icon_list_classes'][ $class_name ] = true;
						}
					}
				}
				if ( '' !== $element_id ) {
					$inventory['icon_list_ids'][ $element_id ] = true;
				}
			}

			if ( 'text-editor' === ( $node['widgetType'] ?? '' ) ) {
				if ( '' !== $classes ) {
					foreach ( preg_split( '/\s+/', $classes ) as $class_name ) {
						$class_name = trim( (string) $class_name );
						if ( '' !== $class_name ) {
							$inventory['text_classes'][ $class_name ] = true;
						}
					}
				}
				if ( '' !== $element_id ) {
					$inventory['text_ids'][ $element_id ] = true;
				}
			}

			if ( 'heading' === ( $node['widgetType'] ?? '' ) ) {
				if ( '' !== $classes ) {
					foreach ( preg_split( '/\s+/', $classes ) as $class_name ) {
						$class_name = trim( (string) $class_name );
						if ( '' !== $class_name ) {
							$inventory['heading_classes'][ $class_name ] = true;
						}
					}
				}
				if ( '' !== $element_id ) {
					$inventory['heading_ids'][ $element_id ] = true;
				}
			}

			if ( 'button' === ( $node['widgetType'] ?? '' ) ) {
				if ( '' !== $classes ) {
					foreach ( preg_split( '/\s+/', $classes ) as $class_name ) {
						$class_name = trim( (string) $class_name );
						if ( '' !== $class_name ) {
							$inventory['button_classes'][ $class_name ] = true;
						}
					}
				}
				if ( '' !== $element_id ) {
					$inventory['button_ids'][ $element_id ] = true;
				}
			}

			if ( in_array( (string) ( $node['widgetType'] ?? '' ), [ 'image', 'image-gallery' ], true ) ) {
				if ( '' !== $classes ) {
					foreach ( preg_split( '/\s+/', $classes ) as $class_name ) {
						$class_name = trim( (string) $class_name );
						if ( '' !== $class_name ) {
							$inventory['image_classes'][ $class_name ] = true;
						}
					}
				}
				if ( '' !== $element_id ) {
					$inventory['image_ids'][ $element_id ] = true;
				}
			}

			if ( 'video' === ( $node['widgetType'] ?? '' ) ) {
				if ( '' !== $classes ) {
					foreach ( preg_split( '/\s+/', $classes ) as $class_name ) {
						$class_name = trim( (string) $class_name );
						if ( '' !== $class_name ) {
							$inventory['media_classes'][ $class_name ] = true;
						}
					}
				}
				if ( '' !== $element_id ) {
					$inventory['media_ids'][ $element_id ] = true;
				}
			}

			if ( 'html' === ( $node['widgetType'] ?? '' ) ) {
				$html = (string) ( $settings['html'] ?? '' );
				if ( '' !== $html ) {
					if ( preg_match_all( '/class=(["\'])([^"\']+)\1/', $html, $class_matches ) ) {
						foreach ( $class_matches[2] as $class_group ) {
							foreach ( preg_split( '/\s+/', (string) $class_group ) as $class_name ) {
								$class_name = trim( (string) $class_name );
								if ( '' !== $class_name ) {
									$inventory['classes'][ $class_name ] = true;
								}
							}
						}
					}
					if ( preg_match_all( '/id=(["\'])([^"\']+)\1/', $html, $id_matches ) ) {
						foreach ( $id_matches[2] as $id_name ) {
							$id_name = trim( (string) $id_name );
							if ( '' !== $id_name ) {
								$inventory['ids'][ $id_name ] = true;
							}
						}
					}
				}
			}

			foreach ( (array) ( $node['elements'] ?? [] ) as $child ) {
				if ( is_array( $child ) ) {
					$walk( $child );
				}
			}
		};

		foreach ( $elements as $element ) {
			if ( is_array( $element ) ) {
				$walk( $element );
			}
		}

		return $inventory;
	}

	/**
	 * Fail when key section content is unresolved instead of allowing synthetic defaults.
	 *
	 * @return array|null
	 */
	private function find_required_extraction_gaps(): ?array {
		$checks = [
			'hero' => function( array $payload ): ?array {
				if ( empty( trim( (string) ( $payload['headline'] ?? '' ) ) ) ) {
					return [
						'code' => 'hero_headline_unresolved',
						'message' => 'Hero headline was not extracted, and no synthetic fallback is allowed.',
						'context' => [ 'pass' => 7, 'type' => 'hero' ],
					];
				}
				return null;
			},
			'marquee' => function( array $payload ): ?array {
				if ( empty( $payload['items'] ) ) {
					return [
						'code' => 'marquee_items_unresolved',
						'message' => 'Marquee items were not extracted, and no synthetic fallback is allowed.',
						'context' => [ 'pass' => 7, 'type' => 'marquee' ],
					];
				}
				return null;
			},
			'stats' => function( array $payload ): ?array {
				if ( ! empty( $payload['preserved_source'] ) ) {
					return null;
				}

				$stats = $payload['stats'] ?? [];
				if ( empty( $stats ) ) {
					return [
						'code' => 'stats_unresolved',
						'message' => 'Stats structure could not be extracted or preserved, and no synthetic fallback is allowed.',
						'context' => [ 'pass' => 7, 'type' => 'stats' ],
					];
				}
				return null;
			},
			'features' => function( array $payload ): ?array {
				if ( 'fully_preserved_source' === (string) ( $payload['render_mode'] ?? '' ) ) {
					return null;
				}
				if ( empty( $payload['cards'] ) ) {
					return [
						'code' => 'feature_cards_unresolved',
						'message' => 'Feature cards were not extracted, and no synthetic fallback is allowed.',
						'context' => [ 'pass' => 7, 'type' => 'features' ],
					];
				}
				return null;
			},
			'bento' => function( array $payload ): ?array {
				if ( empty( $payload['cards'] ) ) {
					return [
						'code' => 'bento_cards_unresolved',
						'message' => 'Bento cards were not extracted, and no synthetic fallback is allowed.',
						'context' => [ 'pass' => 7, 'type' => 'bento' ],
					];
				}
				return null;
			},
			'process' => function( array $payload ): ?array {
				if ( empty( $payload['steps'] ) ) {
					return [
						'code' => 'process_steps_unresolved',
						'message' => 'Process steps were not extracted, and no synthetic fallback is allowed.',
						'context' => [ 'pass' => 7, 'type' => 'process' ],
					];
				}
				return null;
			},
			'testimonials' => function( array $payload ): ?array {
				if ( empty( $payload['cards'] ) ) {
					return [
						'code' => 'testimonial_cards_unresolved',
						'message' => 'Testimonial cards were not extracted, and no synthetic fallback is allowed.',
						'context' => [ 'pass' => 7, 'type' => 'testimonials' ],
					];
				}
				return null;
			},
			'pricing' => function( array $payload ): ?array {
				if ( 'fully_preserved_source' === (string) ( $payload['render_mode'] ?? '' ) ) {
					return null;
				}
				if ( empty( $payload['cards'] ) ) {
					return [
						'code' => 'pricing_cards_unresolved',
						'message' => 'Pricing cards were not extracted, and no synthetic fallback is allowed.',
						'context' => [ 'pass' => 7, 'type' => 'pricing' ],
					];
				}
				foreach ( (array) ( $payload['cards'] ?? [] ) as $index => $card ) {
					if ( ! empty( $card['has_list_pseudo'] ) && empty( $card['features_icon'] ) ) {
						return [
							'code'    => 'pricing_list_icon_unresolved',
							'message' => 'Pricing list pseudo-content was detected in source CSS, but no native list icon mapping was produced.',
							'context' => [ 'pass' => 7, 'type' => 'pricing', 'card_index' => $index ],
						];
					}
				}
				return null;
			},
			'cta' => function( array $payload ): ?array {
				if ( 'fully_preserved_source' === (string) ( $payload['render_mode'] ?? '' ) ) {
					return null;
				}
				if ( empty( trim( (string) ( $payload['title'] ?? '' ) ) ) ) {
					return [
						'code' => 'cta_title_unresolved',
						'message' => 'CTA title was not extracted, and no synthetic fallback is allowed.',
						'context' => [ 'pass' => 7, 'type' => 'cta' ],
					];
				}
				return null;
			},
			'footer' => function( array $payload ): ?array {
				if ( 'fully_preserved_source' === (string) ( $payload['render_mode'] ?? '' ) ) {
					return null;
				}
				if ( empty( $payload['cols'] ) ) {
					return [
						'code' => 'footer_columns_unresolved',
						'message' => 'Footer columns were not extracted, and no synthetic fallback is allowed.',
						'context' => [ 'pass' => 7, 'type' => 'footer' ],
					];
				}
				foreach ( (array) ( $payload['cols'] ?? [] ) as $index => $col ) {
					if ( ! empty( $col['has_list_pseudo'] ) && empty( $col['list_icon'] ) ) {
						return [
							'code'    => 'footer_list_icon_unresolved',
							'message' => 'Footer list pseudo-content was detected in source CSS, but no native list icon mapping was produced.',
							'context' => [ 'pass' => 7, 'type' => 'footer', 'column_index' => $index ],
						];
					}
				}
				return null;
			},
		];

		foreach ( $checks as $type => $check ) {
			if ( ! in_array( $type, $this->built_section_types, true ) ) {
				continue;
			}

			$payload = $this->section_payloads[ $type ] ?? [];
			$issue = $check( $payload );
			if ( $issue ) {
				return $issue;
			}
		}

		return null;
	}

	private function find_coverage_integrity_issue(): ?array {
		$global = $this->global_setup_asset_inventory;
		if ( ! empty( $global['has_canvas_source'] ) && empty( $global['has_canvas_output'] ) ) {
			return [
				'code' => 'global_canvas_missing',
				'message' => 'Pass 1 detected canvas behavior, but Global Setup output is missing the prefixed canvas asset.',
				'context' => [ 'pass' => 6, 'global_setup' => $global ],
			];
		}

		if ( empty( $global['has_canvas_source'] ) && ! empty( $global['has_canvas_output'] ) ) {
			return [
				'code' => 'unexpected_global_canvas',
				'message' => 'Global Setup emitted a canvas asset even though Pass 1 did not detect source canvas behavior.',
				'context' => [ 'pass' => 6, 'global_setup' => $global ],
			];
		}

		if ( ! empty( $global['has_cursor_source'] ) && empty( $global['has_cursor_output'] ) ) {
			return [
				'code' => 'global_cursor_missing',
				'message' => 'Pass 1 detected custom cursor behavior, but Global Setup output is missing the prefixed cursor assets.',
				'context' => [ 'pass' => 6, 'global_setup' => $global ],
			];
		}

		if ( empty( $global['has_cursor_source'] ) && ! empty( $global['has_cursor_output'] ) ) {
			return [
				'code' => 'unexpected_global_cursor',
				'message' => 'Global Setup emitted cursor assets even though Pass 1 did not detect source cursor behavior.',
				'context' => [ 'pass' => 6, 'global_setup' => $global ],
			];
		}

		$script_cov = (array) $this->source_script_bridge_coverage;
		$requires_bridge = ! empty( $global['has_source_js' ] ) && ( ! empty( $script_cov['has_source_hook_candidates'] ) || ! empty( $script_cov['has_source_selector_hits'] ) );
		if ( $requires_bridge && empty( $global['has_script_bridge'] ) ) {
			return [
				'code' => 'global_script_bridge_missing',
				'message' => 'Source JavaScript was detected with mappable hooks, but Global Setup did not include the source script bridge block.',
				'context' => [ 'pass' => 6, 'global_setup' => $global, 'coverage' => $script_cov ],
			];
		}

		$coverage = $this->companion_css_coverage;
		$prefixed_pseudo_hosts = array_values( array_filter(
			(array) ( $coverage['unresolved_pseudo_hosts'] ?? [] ),
			fn( $host ) => str_contains( (string) $host, '.' . $this->prefix . '-' ) || str_contains( (string) $host, '#' . $this->prefix . '-' )
		) );
		if ( ! empty( $prefixed_pseudo_hosts ) ) {
			return [
				'code' => 'orphaned_prefixed_pseudo_hosts',
				'message' => 'Companion CSS contains prefixed pseudo-element hosts that do not exist in emitted output.',
				'context' => [
					'pass'  => 8,
					'hosts' => array_slice( $prefixed_pseudo_hosts, 0, 20 ),
				],
			];
		}

		$prefixed_missing_keyframes = array_values( array_filter(
			(array) ( $coverage['missing_keyframes'] ?? [] ),
			fn( $name ) => str_starts_with( (string) $name, $this->prefix . '-' )
		) );
		if ( ! empty( $prefixed_missing_keyframes ) ) {
			return [
				'code' => 'missing_prefixed_keyframes',
				'message' => 'Companion CSS references prefixed animation names without matching @keyframes definitions.',
				'context' => [
					'pass'      => 8,
					'keyframes' => array_slice( $prefixed_missing_keyframes, 0, 20 ),
				],
			];
		}

		$selector_bridge = $this->source_selector_bridge_coverage;
		if (
			! empty( $selector_bridge['has_source_css'] ) &&
			! empty( $selector_bridge['has_source_hook_candidates'] ) &&
			empty( $selector_bridge['has_bridge_targets'] )
		) {
			return [
				'code' => 'source_css_hooks_orphaned',
				'message' => 'Source CSS exposed bridgeable hook candidates, but none of them mapped to emitted output hooks.',
				'context' => [
					'pass' => 8,
					'coverage' => $selector_bridge,
				],
			];
		}

		if (
			! empty( $selector_bridge['has_source_css'] ) &&
			! empty( $selector_bridge['has_bridge_targets'] ) &&
			! empty( $selector_bridge['has_source_selector_hits'] ) &&
			empty( $selector_bridge['has_output_css'] )
		) {
			return [
				'code' => 'source_css_bridge_unresolved',
				'message' => 'Source CSS had mappable source hooks, but the generic selector bridge produced no retargeted CSS.',
				'context' => [
					'pass' => 8,
					'coverage' => $selector_bridge,
				],
			];
		}

		if (
			! empty( $selector_bridge['has_source_css'] ) &&
			! empty( $selector_bridge['has_bridge_targets'] ) &&
			! empty( $selector_bridge['has_source_selector_hits'] ) &&
			! empty( $selector_bridge['source_has_hover'] ) &&
			empty( $selector_bridge['output_has_hover'] )
		) {
			return [
				'code' => 'source_hover_bridge_missing',
				'message' => 'Source CSS contained hover-state rules for mappable hooks, but the retargeted bridge output did not carry any :hover selectors.',
				'context' => [
					'pass' => 8,
					'coverage' => $selector_bridge,
				],
			];
		}

		if (
			! empty( $selector_bridge['has_source_css'] ) &&
			! empty( $selector_bridge['has_bridge_targets'] ) &&
			! empty( $selector_bridge['has_source_selector_hits'] ) &&
			! empty( $selector_bridge['source_has_pseudo'] ) &&
			empty( $selector_bridge['output_has_pseudo'] )
		) {
			return [
				'code' => 'source_pseudo_bridge_missing',
				'message' => 'Source CSS contained pseudo-element rules for mappable hooks, but the retargeted bridge output did not carry any pseudo-element selectors.',
				'context' => [
					'pass' => 8,
					'coverage' => $selector_bridge,
				],
			];
		}

		if (
			! empty( $selector_bridge['has_source_css'] ) &&
			! empty( $selector_bridge['has_bridge_targets'] ) &&
			! empty( $selector_bridge['has_source_selector_hits'] ) &&
			! empty( $selector_bridge['source_has_media'] ) &&
			empty( $selector_bridge['output_has_media'] )
		) {
			return [
				'code' => 'source_media_bridge_missing',
				'message' => 'Source CSS contained media-query rules for mappable hooks, but the retargeted bridge output did not carry any @media blocks.',
				'context' => [
					'pass' => 8,
					'coverage' => $selector_bridge,
				],
			];
		}

		if (
			! empty( $selector_bridge['has_source_css'] ) &&
			! empty( $selector_bridge['has_bridge_targets'] ) &&
			! empty( $selector_bridge['has_source_selector_hits'] ) &&
			! empty( $selector_bridge['source_has_supports'] ) &&
			empty( $selector_bridge['output_has_supports'] )
		) {
			return [
				'code' => 'source_supports_bridge_missing',
				'message' => 'Source CSS contained @supports rules for mappable hooks, but the retargeted bridge output did not carry any @supports blocks.',
				'context' => [
					'pass' => 8,
					'coverage' => $selector_bridge,
				],
			];
		}

		$semantic_gaps = [];
		foreach ( (array) ( $selector_bridge['widget_semantics'] ?? [] ) as $family => $coverage ) {
			if ( ! empty( $coverage['source'] ) && empty( $coverage['output'] ) ) {
				$semantic_gaps[] = (string) $family;
			}
		}
		if ( ! empty( $semantic_gaps ) ) {
			return [
				'code' => 'source_widget_semantic_bridge_missing',
				'message' => 'Source CSS used native-widget tag semantics for mappable hooks, but the selector bridge did not carry those semantics into Elementor-rendered output.',
				'context' => [
					'pass'     => 8,
					'families' => $semantic_gaps,
					'coverage' => $selector_bridge['widget_semantics'] ?? [],
				],
			];
		}

		$pseudo_semantic_gaps = [];
		foreach ( (array) ( $selector_bridge['widget_semantics'] ?? [] ) as $family => $coverage ) {
			if ( ! empty( $coverage['pseudo_source'] ) && empty( $coverage['pseudo_output'] ) ) {
				$pseudo_semantic_gaps[] = (string) $family;
			}
		}
		if ( ! empty( $pseudo_semantic_gaps ) ) {
			return [
				'code' => 'source_widget_pseudo_semantic_bridge_missing',
				'message' => 'Source CSS used native-widget pseudo-element hosts for mappable hooks, but the selector bridge did not carry those pseudo hosts into Elementor-rendered output.',
				'context' => [
					'pass'     => 8,
					'families' => $pseudo_semantic_gaps,
					'coverage' => $selector_bridge['widget_semantics'] ?? [],
				],
			];
		}

		$script_bridge = $this->source_script_bridge_coverage;
		if (
			! empty( $script_bridge['has_source_js'] ) &&
			! empty( $script_bridge['has_source_hook_candidates'] ) &&
			empty( $script_bridge['has_bridge_targets'] )
		) {
			return [
				'code' => 'source_js_hooks_orphaned',
				'message' => 'Source JS exposed bridgeable hook candidates, but none of them mapped to emitted output hooks.',
				'context' => [
					'pass' => 8,
					'coverage' => $script_bridge,
				],
			];
		}

		if (
			! empty( $script_bridge['has_source_js'] ) &&
			! empty( $script_bridge['has_bridge_targets'] ) &&
			! empty( $script_bridge['has_source_selector_hits'] ) &&
			empty( $script_bridge['has_rewrite'] )
		) {
			return [
				'code' => 'source_js_bridge_unresolved',
				'message' => 'Source JS had mappable source hooks, but the generic script bridge could not retarget any selectors or state hooks.',
				'context' => [
					'pass' => 8,
					'coverage' => $script_bridge,
				],
			];
		}

		if (
			! empty( $script_bridge['has_source_js'] ) &&
			! empty( $script_bridge['has_bridge_targets'] ) &&
			! empty( $script_bridge['has_source_selector_hits'] ) &&
			( ! empty( $script_bridge['source_has_animation_api'] ) || ! empty( $script_bridge['source_has_selector_api'] ) ) &&
			empty( $script_bridge['has_rewrite'] )
		) {
			return [
				'code' => 'source_behavior_bridge_missing',
				'message' => 'Source JS contained behavior or selector APIs for mappable hooks, but the retargeted script bridge produced no rewritten output.',
				'context' => [
					'pass' => 8,
					'coverage' => $script_bridge,
				],
			];
		}

		$script_semantic_gaps = [];
		foreach ( (array) ( $script_bridge['widget_semantics'] ?? [] ) as $family => $coverage ) {
			if ( ! empty( $coverage['source'] ) && empty( $coverage['output'] ) ) {
				$script_semantic_gaps[] = (string) $family;
			}
		}
		if ( ! empty( $script_semantic_gaps ) ) {
			return [
				'code' => 'source_js_widget_semantic_bridge_missing',
				'message' => 'Source JS used native-widget tag semantics for mappable hooks, but the script bridge did not carry those semantics into Elementor-rendered output.',
				'context' => [
					'pass'     => 8,
					'families' => $script_semantic_gaps,
					'coverage' => $script_bridge['widget_semantics'] ?? [],
				],
			];
		}

		return null;
	}

	/**
	 * Structural fidelity checks: repeated counts, bento spans, and section contracts.
	 *
	 * @param array $elements Repaired top-level output tree.
	 * @return array<string,mixed>|null
	 */
	private function find_structural_fidelity_issue( array $elements ): ?array {
		$section_map = $this->map_top_level_sections_by_type( $elements );

		$checks = [
			'stats' => function( array $payload, ?array $output ) {
				if ( ! empty( $payload['preserved_source'] ) || null === $output ) {
					return null;
				}
				$expected = count( (array) ( $payload['stats'] ?? [] ) );
				if ( $expected <= 0 ) {
					return null;
				}
				$actual = $this->count_subtree_class_pattern( $output, '/\b' . preg_quote( $this->prefix, '/' ) . '-stat(?:-card|-cell)\b/' );
				if ( $actual <= 0 || $actual < max( 1, $expected - 1 ) ) {
					return [
						'code' => 'stats_structure_degraded',
						'message' => 'Stats repeated-card structure degraded between source payload and emitted output.',
						'context' => [ 'pass' => 7, 'type' => 'stats', 'expected' => $expected, 'actual' => $actual ],
					];
				}
				return null;
			},
			'bento' => function( array $payload, ?array $output ) {
				if ( ! empty( $payload['preserved_source'] ) || null === $output ) {
					return null;
				}
				$cards = count( (array) ( $payload['cards'] ?? [] ) );
				if ( $cards <= 0 ) {
					return null;
				}
				$actual_cards = $this->count_subtree_class_pattern( $output, '/\b' . preg_quote( $this->prefix, '/' ) . '-bento-card\b/' );
				if ( $actual_cards <= 0 ) {
					$actual_cards = $this->count_subtree_class_pattern( $output, '/\b' . preg_quote( $this->prefix, '/' ) . '-bc-[a-z]\b/' );
				}
				if ( $actual_cards <= 0 || $actual_cards < max( 1, $cards - 1 ) ) {
					return [
						'code' => 'bento_card_count_degraded',
						'message' => 'Bento card count in emitted output is lower than source payload expectations.',
						'context' => [ 'pass' => 7, 'type' => 'bento', 'expected' => $cards, 'actual' => $actual_cards ],
					];
				}
				$spans = (array) ( $payload['bento_spans'] ?? [] );
				if ( ! empty( $spans ) ) {
					$non_default_span_found = false;
					foreach ( $spans as $span ) {
						$col = (int) ( $span['col'] ?? 1 );
						$row = (int) ( $span['row'] ?? 1 );
						if ( $col > 1 || $row > 1 ) {
							$non_default_span_found = true;
							break;
						}
					}
					if ( $non_default_span_found && ! $this->companion_css_has_bento_span_contract() ) {
						return [
							'code' => 'bento_span_contract_missing',
							'message' => 'Source bento spans were detected but emitted companion CSS lacks explicit span contract rules.',
							'context' => [ 'pass' => 8, 'type' => 'bento', 'span_count' => count( $spans ) ],
						];
					}
				}
				return null;
			},
			'process' => function( array $payload, ?array $output ) {
				if ( ! empty( $payload['preserved_source'] ) || null === $output ) {
					return null;
				}
				$expected = count( (array) ( $payload['steps'] ?? [] ) );
				if ( $expected <= 0 ) {
					return null;
				}
				$actual = $this->count_subtree_class_pattern( $output, '/\b' . preg_quote( $this->prefix, '/' ) . '-process-step\b/' );
				if ( $actual <= 0 || $actual < max( 1, $expected - 1 ) ) {
					return [
						'code' => 'process_step_count_degraded',
						'message' => 'Process step count in emitted output is lower than source payload expectations.',
						'context' => [ 'pass' => 7, 'type' => 'process', 'expected' => $expected, 'actual' => $actual ],
					];
				}
				return null;
			},
			'pricing' => function( array $payload, ?array $output ) {
				if ( ! empty( $payload['preserved_source'] ) || null === $output ) {
					return null;
				}
				$expected = count( (array) ( $payload['cards'] ?? [] ) );
				if ( $expected <= 0 ) {
					return null;
				}
				$actual = $this->count_subtree_class_pattern( $output, '/\b' . preg_quote( $this->prefix, '/' ) . '-price-card\b/' );
				if ( $actual <= 0 || $actual < max( 1, $expected - 1 ) ) {
					return [
						'code' => 'pricing_card_count_degraded',
						'message' => 'Pricing card count in emitted output is lower than source payload expectations.',
						'context' => [ 'pass' => 7, 'type' => 'pricing', 'expected' => $expected, 'actual' => $actual ],
					];
				}
				return null;
			},
		];

		foreach ( $checks as $type => $check ) {
			if ( ! in_array( $type, $this->built_section_types, true ) ) {
				continue;
			}
			$payload = (array) ( $this->section_payloads[ $type ] ?? [] );
			$issue = $check( $payload, $section_map[ $type ] ?? null );
			if ( $issue ) {
				return $issue;
			}
		}

		// Generic repeated-structure fidelity guard for unknown/non-template sections.
		foreach ( (array) $this->built_section_types as $type ) {
			$payload = (array) ( $this->section_payloads[ $type ] ?? [] );
			$output  = $section_map[ $type ] ?? null;
			if ( null === $output || ! empty( $payload['preserved_source'] ) ) {
				continue;
			}

			$expected_repeated = $this->estimate_payload_repeated_units( $payload );
			if ( $expected_repeated < 3 ) {
				continue;
			}

			$actual_repeated = $this->estimate_output_repeated_units( $output, $type );
			if ( $actual_repeated < max( 1, $expected_repeated - 1 ) ) {
				return [
					'code'    => 'generic_repeated_structure_degraded',
					'message' => 'Repeated structure fidelity degraded for emitted section output.',
					'context' => [
						'pass'      => 7,
						'type'      => $type,
						'expected'  => $expected_repeated,
						'actual'    => $actual_repeated,
					],
				];
			}
		}

		return null;
	}

	/**
	 * Estimate repeated unit count from generic payload shapes.
	 */
	private function estimate_payload_repeated_units( array $payload ): int {
		$candidate_keys = [ 'cards', 'stats', 'steps', 'items', 'cols', 'rows', 'features' ];
		$max = 0;
		foreach ( $candidate_keys as $key ) {
			$count = count( (array) ( $payload[ $key ] ?? [] ) );
			if ( $count > $max ) {
				$max = $count;
			}
		}
		return $max;
	}

	/**
	 * Estimate repeated unit count from emitted section subtree.
	 */
	private function estimate_output_repeated_units( array $section_node, string $type ): int {
		if ( 'features' === $type ) {
			$feature_cards = $this->count_subtree_class_pattern( $section_node, '/\b' . preg_quote( $this->prefix, '/' ) . '-bento-card\b/' );
			if ( $feature_cards <= 0 ) {
				$feature_cards = $this->count_subtree_class_pattern( $section_node, '/\b' . preg_quote( $this->prefix, '/' ) . '-bc-[a-z]\b/' );
			}
			if ( $feature_cards > 0 ) {
				return $feature_cards;
			}
		}

		if ( 'footer' === $type ) {
			$footer_cols = $this->count_subtree_class_pattern( $section_node, '/\b' . preg_quote( $this->prefix, '/' ) . '-footer-nav-col\b/' );
			if ( $footer_cols > 0 ) {
				return $footer_cols;
			}
		}

		$typed_pattern = '/\b' . preg_quote( $this->prefix, '/' ) . '-' . preg_quote( $type, '/' ) . '-(?:card|item|step|col|cell|row)\b/';
		$typed_count = $this->count_subtree_class_pattern( $section_node, $typed_pattern );
		if ( $typed_count > 0 ) {
			return $typed_count;
		}

		$children = (array) ( $section_node['elements'] ?? [] );
		$container_children = 0;
		foreach ( $children as $child ) {
			if ( is_array( $child ) && 'container' === ( $child['elType'] ?? '' ) ) {
				$container_children++;
			}
		}
		return $container_children;
	}

	/**
	 * @param array $elements
	 * @return array<string,array>
	 */
	private function map_top_level_sections_by_type( array $elements ): array {
		$map = [];
		foreach ( $elements as $element ) {
			if ( ! is_array( $element ) || 'container' !== ( $element['elType'] ?? '' ) ) {
				continue;
			}
			$id = trim( (string) ( $element['settings']['_element_id'] ?? '' ) );
			if ( '' === $id ) {
				continue;
			}
			foreach ( (array) $this->built_section_types as $type ) {
				$prefix = $this->prefix . '-' . $type;
				if ( str_starts_with( $id, $prefix ) && ! isset( $map[ $type ] ) ) {
					$map[ $type ] = $element;
				}
			}
		}
		return $map;
	}

	/**
	 * @param array  $node
	 * @param string $pattern
	 * @return int
	 */
	private function count_subtree_class_pattern( array $node, string $pattern ): int {
		$count = 0;
		$walk = function( array $n ) use ( &$walk, &$count, $pattern ): void {
			$classes = (string) ( $n['settings']['_css_classes'] ?? '' );
			if ( '' !== $classes && preg_match( $pattern, $classes ) ) {
				$count++;
			}
			foreach ( (array) ( $n['elements'] ?? [] ) as $child ) {
				if ( is_array( $child ) ) {
					$walk( $child );
				}
			}
		};
		$walk( $node );
		return $count;
	}

	private function companion_css_has_bento_span_contract(): bool {
		$coverage = (array) $this->source_selector_bridge_coverage;
		// Fallback to strict class-map and known generated span aliases.
		$has_span_hooks = false;
		foreach ( (array) $this->class_map as $entry ) {
			$class = (string) ( $entry['class'] ?? '' );
			if ( preg_match( '/\b' . preg_quote( $this->prefix, '/' ) . '-(?:bc-[a-z]|bento-card(?:-[a-z])?)\b/', $class ) ) {
				$has_span_hooks = true;
				break;
			}
		}
		$has_bridge_targets = ! empty( $coverage['has_bridge_targets'] );
		return $has_span_hooks || $has_bridge_targets;
	}

	/**
	 * Runtime checklist aligned to architecture article sections.
	 *
	 * Emits done / partial / not_done states as diagnostics context for each run.
	 */
	private function record_architecture_compliance_diagnostics( array $elements ): void {
		$selector_bridge = (array) $this->source_selector_bridge_coverage;
		$script_bridge   = (array) $this->source_script_bridge_coverage;
		$global          = (array) $this->global_setup_asset_inventory;

		$checks = [
			'problem_layers' => [
				'done' => ! empty( $this->detected_section_types ) && ! empty( $this->built_section_types ),
				'partial' => empty( $this->detected_section_types ) || empty( $this->built_section_types ),
			],
			'pass_architecture_integrity' => [
				'done' => ! empty( $this->diagnostics ),
				'partial' => true,
			],
			'hybrid_boundary_correctness' => [
				'done' => $this->has_any_render_mode( 'fully_preserved_source' ) || $this->has_any_render_mode( 'native_rebuilt' ),
				'partial' => true,
			],
			'global_setup_handling' => [
				'done' => ( empty( $global['has_canvas_source'] ) || ! empty( $global['has_canvas_output'] ) )
					&& ( empty( $global['has_cursor_source'] ) || ! empty( $global['has_cursor_output'] ) ),
				'partial' => ! empty( $global ),
			],
			'css_js_carryover_fidelity' => [
				'done' => ! empty( $selector_bridge['has_output_css'] ) || empty( $selector_bridge['has_source_css'] ),
				'partial' => ! empty( $selector_bridge['has_source_css'] ) || ! empty( $script_bridge['has_source_js'] ),
			],
			'validation_truthfulness' => [
				'done' => ! empty( $this->diagnostics ),
				'partial' => true,
			],
			'edge_case_posture' => [
				'done' => ! empty( $selector_bridge['source_has_pseudo'] ) ? ! empty( $selector_bridge['output_has_pseudo'] ) : true,
				'partial' => true,
			],
		];

		$statuses = [];
		foreach ( $checks as $key => $state ) {
			if ( ! empty( $state['done'] ) ) {
				$statuses[ $key ] = 'done';
			} elseif ( ! empty( $state['partial'] ) ) {
				$statuses[ $key ] = 'partial';
			} else {
				$statuses[ $key ] = 'not_done';
			}
		}

		$this->diagnostics[] = [
			'code'    => 'architecture_article_compliance',
			'message' => 'Runtime architecture-article compliance checklist generated.',
			'context' => [
				'pass'     => 9,
				'statuses' => $statuses,
			],
		];
	}

	private function has_any_render_mode( string $mode ): bool {
		foreach ( (array) $this->section_payloads as $payload ) {
			if ( (string) ( $payload['render_mode'] ?? '' ) === $mode ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Detect preserved sections that lost their wrapper/signature during preservation.
	 *
	 * @param array $elements Repaired top-level elements.
	 * @return array|null
	 */
	private function find_preservation_integrity_issue( array $elements ): ?array {
		foreach ( $this->built_section_types as $type ) {
			$payload = $this->section_payloads[ $type ] ?? [];
			$mode = (string) ( $payload['render_mode'] ?? '' );
			if ( 'fully_preserved_source' !== $mode ) {
				continue;
			}

			$expected_id = (string) ( $payload['output_element_id'] ?? '' );
			if ( '' === $expected_id ) {
				$expected_id = "{$this->prefix}-{$type}";
			}
			$element = null;
			foreach ( $elements as $candidate ) {
				if ( (string) ( $candidate['settings']['_element_id'] ?? '' ) === $expected_id ) {
					$element = $candidate;
					break;
				}
			}

			if ( ! $element ) {
				return [
					'code' => 'preserved_section_missing',
					'message' => sprintf( 'Preserved section "%s" is missing from the assembled output.', $type ),
					'context' => [ 'pass' => 7, 'type' => $type, 'mode' => $mode ],
				];
			}

			$html = $this->extract_html_payload_from_element( $element );
			if ( '' === trim( $html ) ) {
				return [
					'code' => 'preserved_section_empty',
					'message' => sprintf( 'Preserved section "%s" has empty HTML output.', $type ),
					'context' => [ 'pass' => 7, 'type' => $type, 'mode' => $mode ],
				];
			}

			$has_structure = (bool) preg_match( '/<(?:div|section|header|footer|main|article|nav)\b/i', $html );
			if ( ! $has_structure ) {
				return [
					'code' => 'preserved_section_wrapper_missing',
					'message' => sprintf( 'Preserved section "%s" appears to have lost its wrapper markup.', $type ),
					'context' => [ 'pass' => 7, 'type' => $type, 'mode' => $mode ],
				];
			}

			if ( 'stats' === $type ) {
				if ( ! $this->has_stats_preservation_signature( $html ) ) {
					return [
						'code' => 'stats_preservation_incomplete',
						'message' => 'Stats was marked preserved, but the preserved HTML is missing the expected grid wrapper.',
						'context' => [ 'pass' => 7, 'type' => 'stats', 'mode' => $mode ],
					];
				}
				if ( false === stripos( $html, '<script' ) ) {
					$this->diagnostics[] = [
						'code'    => 'stats_behavior_missing_preserved',
						'message' => 'Stats preserved without source behavior script; structure kept and companion JS/CSS may still style it.',
						'context' => [ 'pass' => 7, 'type' => 'stats', 'mode' => $mode ],
					];
				}
			}
		}

		return null;
	}

	private function extract_html_payload_from_element( array $element ): string {
		$direct = (string) ( $element['settings']['html'] ?? '' );
		if ( '' !== trim( $direct ) ) {
			return $direct;
		}
		foreach ( (array) ( $element['elements'] ?? [] ) as $child ) {
			if ( ! is_array( $child ) ) {
				continue;
			}
			$child_html = $this->extract_html_payload_from_element( $child );
			if ( '' !== trim( $child_html ) ) {
				return $child_html;
			}
		}
		return '';
	}

	/**
	 * Broad-spectrum integrity check for preserved stats blocks.
	 * Accept wrapper variants that still clearly represent a repeated stats layout.
	 *
	 * @param string $html Preserved section HTML.
	 * @return bool
	 */
	private function has_stats_preservation_signature( string $html ): bool {
		$lower_html = strtolower( $html );

		$wrapper_markers = [
			'stats-grid',
			'tst-stats-grid',
			'stat-grid',
			'metrics-grid',
			'metrics-row',
			'stats-row',
			'kpi-grid',
			'counter-grid',
		];

		foreach ( $wrapper_markers as $marker ) {
			if ( str_contains( $lower_html, $marker ) ) {
				return true;
			}
		}

		$card_pattern = '/class=(["\'])(?:(?!\1).)*(stat-cell|stat-card|metric-card|metric-item|kpi-card|counter-card|stats-card)(?:(?!\1).)*\1/i';
		if ( preg_match_all( $card_pattern, $html, $matches ) && count( $matches[0] ) >= 2 ) {
			return true;
		}

		$counter_pattern = '/class=(["\'])(?:(?!\1).)*(tst-count|count|counter|stat-num|metric-value)(?:(?!\1).)*\1/i';
		if ( preg_match_all( $counter_pattern, $html, $matches ) && count( $matches[0] ) >= 2 ) {
			return true;
		}

		return false;
	}

	/**
	 * Search the generated element tree for template placeholders.
	 *
	 * @param array  $elements Element tree.
	 * @param string $path Current traversal path.
	 * @return array|null
	 */
	private function find_placeholder_leak( array $elements, string $path = 'content' ): ?array {
		foreach ( $elements as $index => $element ) {
			$current_path = $path . '.' . $index;
			$element_id   = $element['settings']['_element_id'] ?? $element['id'] ?? '';

			foreach ( $element['settings'] ?? [] as $key => $value ) {
				if ( ! is_string( $value ) ) {
					continue;
				}

				if ( preg_match( '/\{\{\s*([a-z0-9_-]+)\s*\}\}/i', $value, $match ) ) {
					return [
						'match'      => $match[0],
						'path'       => $current_path . '.settings.' . $key,
						'element_id' => $element_id,
					];
				}
			}

			$child_hit = $this->find_placeholder_leak( $element['elements'] ?? [], $current_path . '.elements' );
			if ( $child_hit ) {
				return $child_hit;
			}
		}

		return null;
	}

	/**
	 * Detect duplicate footer column headings in generated output.
	 *
	 * @param array $elements Element tree.
	 * @return array|null
	 */
	private function find_duplicate_footer_titles( array $elements ): ?array {
		foreach ( $elements as $element ) {
			$element_id = $element['settings']['_element_id'] ?? '';
			if ( 'container' === ( $element['elType'] ?? '' ) && is_string( $element_id ) && str_ends_with( $element_id, '-footer' ) ) {
				$titles = [];
				foreach ( $this->collect_footer_titles( $element ) as $title ) {
					$key = strtolower( preg_replace( '/\s+/', ' ', trim( wp_strip_all_tags( $title ) ) ) );
					if ( ! $key ) {
						continue;
					}
					if ( isset( $titles[ $key ] ) ) {
						return [
							'element_id' => $element_id,
							'title'      => trim( wp_strip_all_tags( $title ) ),
						];
					}
					$titles[ $key ] = true;
				}
			}

			$child_issue = $this->find_duplicate_footer_titles( $element['elements'] ?? [] );
			if ( $child_issue ) {
				return $child_issue;
			}
		}

		return null;
	}

	/**
	 * Collect footer column heading HTML/text from nested widgets.
	 *
	 * @param array $element Elementor element tree node.
	 * @return array
	 */
	private function collect_footer_titles( array $element ): array {
		$titles = [];
		$classes = (string) ( $element['settings']['_css_classes'] ?? '' );
		$html    = (string) ( $element['settings']['html'] ?? '' );
		$title   = (string) ( $element['settings']['title'] ?? '' );

		if ( str_contains( $classes, 'footer-col-title-widget' ) && preg_match( '/<h5[^>]*>(.*?)<\/h5>/is', $html, $match ) ) {
			$titles[] = $match[1];
		}
		if ( str_contains( $classes, 'footer-col-title' ) && '' !== trim( wp_strip_all_tags( $title ) ) ) {
			$titles[] = $title;
		}

		foreach ( $element['elements'] ?? [] as $child ) {
			$titles = array_merge( $titles, $this->collect_footer_titles( $child ) );
		}

		return $titles;
	}

	private function repair_element( array $el, array &$seen_ids, array &$seen_el_ids = [] ): ?array {
		// Ensure required keys exist.
		if ( empty($el['id']) ) $el['id'] = $this->genid();
		if ( empty($el['elType']) ) $el['elType'] = 'widget';
		if ( ! isset($el['isInner']) ) $el['isInner'] = false;
		if ( ! isset($el['settings']) ) $el['settings'] = [];
		if ( ! isset($el['elements']) ) $el['elements'] = [];

		// Deduplicate random element IDs.
		if ( isset($seen_ids[$el['id']]) ) {
			$el['id'] = $this->genid();
		}
		$seen_ids[$el['id']] = true;

		// ── F-08: Deduplicate _element_id (CSS anchor / JS target IDs).
		// BUG-13: Two containers sharing the same _element_id breaks anchor links
		// and causes CSS targeting to hit the wrong element.
		if ( ! empty( $el['settings']['_element_id'] ) ) {
			$eid = $el['settings']['_element_id'];
			if ( isset( $seen_el_ids[ $eid ] ) ) {
				// Suffix with counter to make unique.
				$counter = 2;
				while ( isset( $seen_el_ids[ "{$eid}-{$counter}" ] ) ) $counter++;
				$el['settings']['_element_id'] = "{$eid}-{$counter}";
				$this->warnings[] = "Repair: duplicate _element_id '{$eid}' renamed to '{$eid}-{$counter}'.";
			}
			$seen_el_ids[ $el['settings']['_element_id'] ] = true;
		}

		// Widgets must have widgetType.
		if ( $el['elType'] === 'widget' && empty($el['widgetType']) ) {
			$el['widgetType'] = 'html';
			$this->warnings[] = 'Repair: widget missing widgetType — defaulted to html widget.';
		}

		// Whitelist widget types (block Elementor Pro-only widgets in free tier).
		$allowed = ['heading','text-editor','button','html','image','icon-list','divider','spacer','posts','video','image-gallery'];
		if ( $el['elType'] === 'widget' && ! in_array($el['widgetType'], $allowed, true) ) {
			$this->warnings[] = "Repair: unknown widget type '{$el['widgetType']}' replaced with html widget.";
			$el['widgetType'] = 'html';
			if ( empty($el['settings']['html']) ) $el['settings']['html'] = '';
		}

		// Ensure _css_classes is a string.
		if ( isset($el['settings']['_css_classes']) && ! is_string($el['settings']['_css_classes']) ) {
			$el['settings']['_css_classes'] = '';
		}

		// Recursively repair children (pass same seen-id sets down).
		$repaired_children = [];
		foreach ( ($el['elements'] ?? []) as $child ) {
			$r = $this->repair_element( $child, $seen_ids, $seen_el_ids );
			if ($r) $repaired_children[] = $r;
		}
		$el['elements'] = $repaired_children;

		return $el;
	}

	// ═══════════════════════════════════════════════════════════
	// PASS 8: COMPANION CSS
	// ═══════════════════════════════════════════════════════════

	private function build_companion_css(): string {
		$p  = $this->prefix;
		$ac = $this->c_accent;
		$bg = $this->c_bg;
		$tx = $this->c_text;
		$sf = $this->c_surface;
		$bd = $this->c_border;
		$fd = $this->f_display;
		$fb = $this->f_body;
		$fm = $this->f_mono;
		$brand_mark = addslashes( $this->resolve_brand_label() );
		$bento_span_css = $this->build_bento_span_css();
		$inline_markup_css = $this->build_inline_markup_css();
		$inventory = $this->get_current_emitted_hook_inventory();
		$section_tag_pseudo_css = isset( $inventory['classes'][ "{$p}-section-tag" ] ) ? ".{$p}-section-tag::before { content: '—'; }\n" : '';
		$eyebrow_pseudo_css     = isset( $inventory['classes'][ "{$p}-eyebrow" ] ) ? ".{$p}-eyebrow::before { content: ''; display: block; width: 40px; height: 1px; background: {$ac}; }\n" : '';
		$stat_pseudo_css        = isset( $inventory['classes'][ "{$p}-stat-cell" ] ) ? ".{$p}-stat-cell::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, {$ac} 0%, transparent 100%); transform: scaleX(0); transform-origin: left; transition: transform .8s ease; }\n.{$p}-stat-cell.{$p}-counted::before { transform: scaleX(1); }\n" : '';
		$card_tag_pseudo_css    = isset( $inventory['classes'][ "{$p}-card-tag" ] ) ? ".{$p}-card-tag .elementor-heading-title::before { content: ''; display: block; width: 20px; height: 1px; background: {$ac}; }\n" : '';
		$cta_selector = '';
		if ( isset( $inventory['classes'][ "{$p}-cta" ] ) ) {
			$cta_selector = ".{$p}-cta";
		} elseif ( isset( $inventory['ids'][ "{$p}-cta" ] ) ) {
			$cta_selector = "#{$p}-cta";
		}
		$cta_pseudo_css = '' !== $cta_selector
			? "{$cta_selector}::before { content: '{$brand_mark}'; position: absolute; right: -20px; top: 50%; transform: translateY(-50%); font-family: var(--{$p}-fd); font-weight: 800; font-size: 200px; color: rgba(0,0,0,.06); letter-spacing: -8px; pointer-events: none; line-height: 1; white-space: nowrap; z-index: 0; }\n"
			: '';
		$cta_pseudo_responsive_css = '' !== $cta_selector
			? "  {$cta_selector}::before { font-size: 100px !important; }\n"
			: '';

		// ── F-10: Class map header — list ALL output sections for user reference.
		// Previously missing: testimonials, marquee, stats, cta.
		$all_sections = [
			"{$p}-global-setup"  => [ 'Global Setup', 'HTML widget — do not delete or move', '' ],
			"{$p}-nav"           => [ 'Navigation',   'HTML widget — fixed position + JS', '' ],
			"{$p}-hero"          => [ 'Hero section', 'Container — CSS ID: Advanced tab', '' ],
			"{$p}-marquee"       => [ 'Marquee strip', 'HTML widget — scrolling ticker', '' ],
			"{$p}-stats"         => [ 'Stats section','Container — CSS ID: Advanced tab', '' ],
			"{$p}-features"      => [ 'Features / Bento', 'Container — CSS ID', '' ],
			"{$p}-process"       => [ 'Process / How it Works', 'Container — CSS ID', '' ],
			"{$p}-testimonials"  => [ 'Testimonials', 'Container — CSS ID', '' ],
			"{$p}-pricing"       => [ 'Pricing',      'Container — CSS ID', '' ],
			"{$p}-cta"           => [ 'CTA section',  'Container — CSS ID', '' ],
			"{$p}-footer"        => [ 'Footer',        'Container — CSS ID', '' ],
		];

		// Merge with dynamically-collected cmap (preserves custom entries).
		$cmap_lines = $all_sections;
		foreach ( $this->class_map as $e ) {
			if ( ! isset( $cmap_lines[ $e['class'] ] ) ) {
				$cmap_lines[ $e['class'] ] = [ $e['element'], $e['location'], '' ];
			}
		}

		$map = "/*\n * ══════════════════════════════════════════════════════\n";
		$map .= " *  Stack Blueprint — Companion CSS\n";
		$map .= " *  Engine: Native Converter | Strategy: {$this->strategy}\n";
		$map .= " *  Project: {$this->project_name} | Prefix: {$p}-\n *\n";
		$map .= " *  Add to: Elementor → Site Settings → Custom CSS\n *\n";
		$map .= " *  CLASS MAP  (CSS ID → Elementor location)\n";
		$map .= " *  ─────────────────────────────────────────────────\n";
		foreach ( $cmap_lines as $cls => $info ) {
			$map .= " *  .{$cls} → {$info[0]} → {$info[1]}\n";
		}
		$map .= " * ══════════════════════════════════════════════════════\n */\n\n";

		$html_render_modes = $this->count_render_modes();
		$total_rendered_sections = max( 1, array_sum( $html_render_modes ) );
		$preserved_html_sections = (int) ( $html_render_modes['fully_preserved_source'] ?? 0 );
		$html_dominant_output = ( $preserved_html_sections / $total_rendered_sections ) >= 0.60;

		$css = <<<CSS
/* ── TOKENS */
:root {
  --{$p}-bg:      {$bg};
  --{$p}-accent:  {$ac};
  --{$p}-text:    {$tx};
  --{$p}-surface: {$sf};
  --{$p}-border:  {$bd};
  --{$p}-fd: '{$fd}', sans-serif;
  --{$p}-fb: '{$fb}', sans-serif;
  --{$p}-fm: '{$fm}', monospace;
}

/* ── PAGE */
body, .elementor-page, #page, .site { background: {$bg} !important; }
.elementor-section-wrap, .e-con-inner { max-width: 100%; }

/* ── SCROLL REVEAL */
.{$p}-reveal { opacity: 1; transform: none; transition: opacity .7s cubic-bezier(.16,1,.3,1), transform .7s cubic-bezier(.16,1,.3,1); }
html.{$p}-motion-ready .{$p}-reveal { opacity: 0; transform: translateY(40px); }
html.{$p}-motion-ready .{$p}-reveal.{$p}-visible { opacity: 1; transform: translateY(0); }
.{$p}-d1 { transition-delay: .1s; } .{$p}-d2 { transition-delay: .2s; } .{$p}-d3 { transition-delay: .3s; }

/* ── SECTIONS */
.{$p}-section { position: relative; z-index: 2; }
.{$p}-section-tag { font-family: var(--{$p}-fm); font-size: 11px; letter-spacing: .2em; color: {$ac}; margin-bottom: 16px; display: flex; align-items: center; gap: 12px; }
.{$p}-section-tag::before { content: '—'; }
.{$p}-section-title .elementor-heading-title { font-family: var(--{$p}-fd) !important; font-weight: 800; color: {$tx}; letter-spacing: -2px; line-height: 1.05; }
.{$p}-section-desc .elementor-widget-text-editor p { font-size: 15px; color: rgba(245,243,238,.45); text-align: right; font-weight: 300; line-height: 1.8; max-width: 300px; }

/* ── HERO */
.{$p}-hero { position: relative; z-index: 2; }
.{$p}-eyebrow { font-family: var(--{$p}-fm); font-size: 11px; letter-spacing: .2em; color: {$ac}; display: flex; align-items: center; gap: 12px; margin-bottom: 28px; }
.{$p}-eyebrow::before { content: ''; display: block; width: 40px; height: 1px; background: {$ac}; }
.{$p}-hero-headline .elementor-heading-title { font-family: var(--{$p}-fd) !important; font-weight: 800 !important; font-size: clamp(64px, 9vw, 140px) !important; line-height: .92 !important; letter-spacing: -3px !important; color: {$tx}; }
.{$p}-hero-headline em { font-style: italic; color: transparent; -webkit-text-stroke: 1px rgba(245,243,238,.4); font-weight: 400; }
.{$p}-hero-headline .acid-word, .{$p}-hero-headline span[class] { color: {$ac}; }
.{$p}-hero-sub .elementor-widget-text-editor p { font-size: 16px; color: rgba(245,243,238,.55); max-width: 360px; line-height: 1.7; font-weight: 300; }
.{$p}-btn-ghost, .{$p}-hero-ghost-wrap a { font-family: var(--{$p}-fm); font-size: 11px; letter-spacing: .1em; color: rgba(245,243,238,.4); text-decoration: none; border-bottom: 1px solid rgba(245,243,238,.15); padding-bottom: 2px; transition: all .2s; }
.{$p}-btn-ghost:hover, .{$p}-hero-ghost-wrap a:hover { color: {$tx}; border-color: {$tx}; }

/* ── BUTTONS */
.{$p}-btn-primary .elementor-button, .{$p}-btn-hero-primary .elementor-button { background: {$ac} !important; color: {$bg} !important; font-family: var(--{$p}-fm) !important; font-weight: 700 !important; font-size: 12px !important; letter-spacing: .1em !important; border-radius: 0 !important; transition: all .2s !important; }
.{$p}-btn-primary .elementor-button:hover, .{$p}-btn-hero-primary .elementor-button:hover { background: #d9ff33 !important; transform: translateY(-2px); }
.{$p}-btn-dark .elementor-button, .{$p}-cta-btn-primary .elementor-button { background: {$bg} !important; color: {$ac} !important; font-family: var(--{$p}-fm) !important; font-weight: 700 !important; border-radius: 0 !important; transition: all .2s !important; }
.{$p}-btn-dark .elementor-button:hover { transform: translateY(-2px); }
.{$p}-btn-outline-dark .elementor-button { background: transparent !important; color: {$bg} !important; border: 1px solid rgba(5,5,10,.3) !important; font-family: var(--{$p}-fm) !important; font-size: 12px !important; border-radius: 0 !important; transition: all .2s !important; }
.{$p}-btn-outline-dark .elementor-button:hover { background: rgba(5,5,10,.08) !important; }
.{$p}-btn-price .elementor-button { display: block; width: 100%; padding: 16px !important; background: transparent !important; border: 1px solid rgba(245,243,238,.2) !important; color: {$tx} !important; font-family: var(--{$p}-fm) !important; font-size: 12px !important; border-radius: 0 !important; transition: all .2s !important; }
.{$p}-btn-price .elementor-button:hover { background: {$ac} !important; border-color: {$ac} !important; color: {$bg} !important; }
.{$p}-btn-price-dark .elementor-button { display: block; width: 100%; padding: 16px !important; background: {$bg} !important; border-color: {$bg} !important; color: {$ac} !important; font-family: var(--{$p}-fm) !important; font-size: 12px !important; border-radius: 0 !important; transition: all .2s !important; }

/* ── CARDS (shared) */
.{$p}-stats-grid { border-bottom: 1px solid {$bd}; background: rgba(255,255,255,.1); }
.{$p}-stat-cell { position: relative; }
.{$p}-stat-cell::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 2px; background: linear-gradient(90deg, {$ac} 0%, transparent 100%); transform: scaleX(0); transform-origin: left; transition: transform .8s ease; }
.{$p}-stat-cell.{$p}-counted::before { transform: scaleX(1); }
.{$p}-stat-num .elementor-heading-title { margin: 0 0 12px; font-family: var(--{$p}-fd) !important; font-weight: 800; font-size: clamp(48px,5vw,72px); color: {$tx} !important; letter-spacing: -2px; line-height: 1; }
.{$p}-stat-label .elementor-widget-text-editor p { margin: 0; font-size: 13px; color: rgba(245,243,238,.4); font-family: var(--{$p}-fm); letter-spacing: .08em; }
.{$p}-bento-grid { gap: 16px !important; }
.{$p}-bento-grid, .{$p}-footer-grid { display: grid; }
.{$p}-card-visual-widget { width: 100%; }
.{$p}-card-visual { margin-top: 12px; }
.{$p}-card-visual > * { width: 100%; }
{$bento_span_css}
.{$p}-bento-card, .{$p}-testi-card, .{$p}-price-card, .{$p}-team-card { transition: border-color .3s, background .3s, transform .3s; }
.{$p}-bento-card:hover, .{$p}-testi-card:hover { border-color: rgba(200,255,0,.25) !important; background: rgba(255,255,255,.05) !important; transform: translateY(-3px); }
.{$p}-price-card:not(.{$p}-price-featured):hover { border-color: rgba(200,255,0,.4) !important; transform: translateY(-4px); }
.{$p}-card-tag .elementor-heading-title { font-family: var(--{$p}-fm) !important; font-size: 10px; letter-spacing: .15em; color: {$ac}; margin-bottom: 20px; display: flex; align-items: center; gap: 8px; }
.{$p}-card-tag .elementor-heading-title::before { content: ''; display: block; width: 20px; height: 1px; background: {$ac}; }
.{$p}-card-title .elementor-heading-title { font-family: var(--{$p}-fd) !important; font-weight: 700; font-size: clamp(20px,2.2vw,30px); letter-spacing: -.5px; line-height: 1.1; }
.{$p}-card-body .elementor-widget-text-editor p { font-size: 14px; color: rgba(245,243,238,.45); line-height: 1.75; font-weight: 300; }

/* ── PROCESS */
.{$p}-process-step { cursor: default; transition: all .3s; }
.{$p}-step-num { min-width: 40px; padding-top: 4px; }
.{$p}-step-num .elementor-heading-title { font-family: var(--{$p}-fm) !important; font-size: 11px; color: rgba(245,243,238,.2); letter-spacing: .1em; transition: color .3s; }
.{$p}-process-step:hover .{$p}-step-num .elementor-heading-title { color: {$ac} !important; }
.{$p}-step-title .elementor-heading-title { font-family: var(--{$p}-fd) !important; font-weight: 700; font-size: 20px; letter-spacing: -.5px; transition: color .3s; }
.{$p}-process-step:hover .{$p}-step-title .elementor-heading-title { color: {$ac}; }
.{$p}-step-desc .elementor-widget-text-editor p { font-size: 14px; color: rgba(245,243,238,.4); line-height: 1.75; font-weight: 300; }

/* ── TESTIMONIALS */
.{$p}-testi-quote .elementor-widget-text-editor p { font-size: 17px; line-height: 1.7; color: rgba(245,243,238,.75); font-weight: 300; }
.{$p}-testi-quote strong { color: {$tx}; font-weight: 500; }
.{$p}-testi-author { display: flex; align-items: center; gap: 14px; border-top: 1px solid {$bd}; padding-top: 20px; }
.{$p}-testi-avatar-wrap { flex-shrink: 0; }
.{$p}-testi-avatar .elementor-heading-title { margin: 0; font-family: var(--{$p}-fd) !important; font-weight: 800; font-size: 14px; color: {$bg} !important; }
.{$p}-testi-name .elementor-heading-title { margin: 0; font-size: 14px; font-weight: 500; color: {$tx} !important; }
.{$p}-testi-role .elementor-widget-text-editor p { margin: 0; font-size: 12px; color: rgba(245,243,238,.35); font-family: var(--{$p}-fm); letter-spacing: .05em; }

/* ── PRICING */
.{$p}-price-card { position: relative; }
.{$p}-price-badge { position: absolute; top: -14px; left: 50%; transform: translateX(-50%); background: {$bg}; border: 1px solid {$ac}; padding: 6px 16px; white-space: nowrap; }
.{$p}-price-badge .elementor-heading-title { margin: 0; color: {$ac} !important; font-family: var(--{$p}-fm) !important; font-size: 10px; letter-spacing: .15em; }
.{$p}-price-plan .elementor-heading-title { margin: 0 0 24px; font-family: var(--{$p}-fm) !important; font-size: 11px; letter-spacing: .2em; color: rgba(245,243,238,.4) !important; }
.{$p}-price-amount .elementor-heading-title { margin: 0 0 8px; font-family: var(--{$p}-fd) !important; font-weight: 800; font-size: 60px; letter-spacing: -3px; line-height: 1; }
.{$p}-price-period .elementor-widget-text-editor p { margin: 0 0 40px; font-size: 13px; color: rgba(245,243,238,.35); }
.{$p}-price-feats-list { margin-bottom: 40px; }
.{$p}-price-feats-list .elementor-icon-list-items { display: flex; flex-direction: column; gap: 14px; }
.{$p}-price-feats-list .elementor-icon-list-item { align-items: flex-start; }
.{$p}-price-feats-list .elementor-icon-list-icon i { color: {$ac}; font-size: 12px; margin-top: 2px; }
.{$p}-price-feats-list .elementor-icon-list-text { font-size: 14px; color: rgba(245,243,238,.6); }
.{$p}-price-featured .{$p}-price-plan .elementor-heading-title, .{$p}-price-featured .{$p}-price-amount .elementor-heading-title, .{$p}-price-featured .{$p}-price-period .elementor-widget-text-editor p { color: {$bg} !important; }
.{$p}-price-featured .{$p}-price-feats-list .elementor-icon-list-text { color: {$bg} !important; }
.{$p}-price-featured .{$p}-price-feats-list .elementor-icon-list-icon i { color: {$bg} !important; }

/* ── CTA */
.{$p}-cta { position: relative; overflow: hidden; }
.{$p}-cta::before { content: '{$brand_mark}'; position: absolute; right: -20px; top: 50%; transform: translateY(-50%); font-family: var(--{$p}-fd); font-weight: 800; font-size: 200px; color: rgba(0,0,0,.06); letter-spacing: -8px; pointer-events: none; line-height: 1; white-space: nowrap; z-index: 0; }
.{$p}-cta > .e-con, .{$p}-cta > .elementor-widget { position: relative; z-index: 1; }
.{$p}-cta-title .elementor-heading-title { font-family: var(--{$p}-fd) !important; font-weight: 800 !important; font-size: clamp(40px, 5vw, 72px) !important; color: {$bg} !important; letter-spacing: -2px !important; line-height: 1 !important; max-width: 600px; }
.{$p}-cta-sub .elementor-widget-text-editor p { font-size: 16px; color: rgba(5,5,10,.5); font-weight: 300; max-width: 400px; margin-bottom: 32px; }

/* ── FOOTER */
.{$p}-footer-logo, .{$p}-footer-logo-widget .nav-logo { font-family: var(--{$p}-fd); font-weight: 800; font-size: 22px; letter-spacing: -.5px; color: {$tx}; text-decoration: none; display: inline-block; }
.{$p}-footer-logo span, .{$p}-footer-logo-widget .nav-logo span { color: {$ac}; }
.{$p}-footer-brand-desc .elementor-widget-text-editor p { font-size: 14px; color: rgba(245,243,238,.35); line-height: 1.75; font-weight: 300; max-width: 280px; }
.{$p}-footer-col-title .elementor-heading-title { font-family: var(--{$p}-fm) !important; font-size: 10px; letter-spacing: .2em; color: rgba(245,243,238,.25) !important; margin: 0 0 20px; }
.{$p}-footer-links-list { margin: 0; }
.{$p}-footer-links-list .elementor-icon-list-items { display: flex; flex-direction: column; gap: 12px; }
.{$p}-footer-links-list .elementor-icon-list-item { align-items: flex-start; }
.{$p}-footer-links-list .elementor-icon-list-icon { display: none; }
.{$p}-footer-links-list .elementor-icon-list-text { font-size: 14px; color: rgba(245,243,238,.45); }
.{$p}-footer-link { font-size: 14px; color: rgba(245,243,238,.45); text-decoration: none; transition: color .2s; }
.{$p}-footer-link:hover, .{$p}-footer-links-list .elementor-icon-list-item:hover .elementor-icon-list-text { color: {$tx}; }
.{$p}-footer-bottom { display: flex; justify-content: space-between; align-items: center; padding-top: 32px; border-top: 1px solid {$bd}; }
.{$p}-footer-copy { font-family: var(--{$p}-fm); font-size: 11px; color: rgba(245,243,238,.2); letter-spacing: .05em; }
.{$p}-footer-status { display: flex; align-items: center; gap: 8px; font-family: var(--{$p}-fm); font-size: 11px; color: rgba(245,243,238,.3); }
.{$p}-status-dot { width: 6px; height: 6px; border-radius: 50%; background: {$ac}; animation: {$p}-status-pulse 2s ease-in-out infinite; }
@keyframes {$p}-status-pulse { 50% { opacity: .4; transform: scale(.8); } }

/* ── RESPONSIVE */
@media (max-width: 1024px) {
  .{$p}-hero-headline .elementor-heading-title { font-size: clamp(48px, 8vw, 100px) !important; }
  .{$p}-hero-bottom .e-con { flex-direction: column !important; align-items: flex-start !important; }
  .{$p}-section-desc .elementor-widget-text-editor p { text-align: left; }
  .{$p}-process-grid > .e-con { flex-direction: column !important; }
  .{$p}-bento-grid { grid-template-columns: 1fr 1fr !important; }
  .{$p}-bento-card { grid-column: span 1 !important; grid-row: span 1 !important; }
}

@media (max-width: 768px) {
  .{$p}-hero, .{$p}-features, .{$p}-process, .{$p}-testimonials, .{$p}-pricing, .{$p}-footer { padding-left: 24px !important; padding-right: 24px !important; }
  .{$p}-cta { margin-left: 16px !important; margin-right: 16px !important; padding: 60px 32px !important; }
  .{$p}-cta::before { font-size: 100px !important; }
  .{$p}-testi-grid > .e-con, .{$p}-pricing-grid > .e-con { flex-direction: column !important; }
  .{$p}-footer-grid { grid-template-columns: 1fr 1fr !important; }
  .{$p}-footer-brand-col { grid-column: 1 / -1 !important; }
  .{$p}-bento-grid { grid-template-columns: 1fr !important; }
}
CSS;
		if ( $html_dominant_output ) {
			$css = <<<CSS
/* ── TOKENS */
:root {
  --{$p}-bg:      {$bg};
  --{$p}-accent:  {$ac};
  --{$p}-text:    {$tx};
  --{$p}-surface: {$sf};
  --{$p}-border:  {$bd};
  --{$p}-fd: '{$fd}', sans-serif;
  --{$p}-fb: '{$fb}', sans-serif;
  --{$p}-fm: '{$fm}', monospace;
}

/* ── PAGE */
body, .elementor-page, #page, .site { background: {$bg} !important; }
.elementor-section-wrap, .e-con-inner { max-width: 100%; }

/* ── SCROLL REVEAL */
.{$p}-reveal { opacity: 1; transform: none; transition: opacity .7s cubic-bezier(.16,1,.3,1), transform .7s cubic-bezier(.16,1,.3,1); }
html.{$p}-motion-ready .{$p}-reveal { opacity: 0; transform: translateY(40px); }
html.{$p}-motion-ready .{$p}-reveal.{$p}-visible { opacity: 1; transform: translateY(0); }
.{$p}-d1 { transition-delay: .1s; } .{$p}-d2 { transition-delay: .2s; } .{$p}-d3 { transition-delay: .3s; }
CSS;
			$this->diagnostics[] = [
				'code'    => 'companion_css_mode_html_dominant',
				'message' => 'Companion CSS switched to html-dominant mode (source contract first).',
				'context' => [
					'pass' => 8,
					'preserved_sections' => $preserved_html_sections,
					'total_sections' => $total_rendered_sections,
					'ratio' => $preserved_html_sections / $total_rendered_sections,
				],
			];
		}
		$css = $this->prune_optional_companion_pseudo_rules( $css );
		$css .= "\n" . $inline_markup_css;
		$css = $this->normalize_companion_css( $css );
		$this->record_companion_css_diagnostics( $css );
		return $map . $css;
	}

	private function count_render_modes(): array {
		$modes = [];
		foreach ( (array) $this->section_payloads as $payload ) {
			$mode = (string) ( $payload['render_mode'] ?? '' );
			if ( '' === $mode ) {
				continue;
			}
			$modes[ $mode ] = ( $modes[ $mode ] ?? 0 ) + 1;
		}
		return $modes;
	}

	private function prune_optional_companion_pseudo_rules( string $css ): string {
		$inventory = $this->get_current_emitted_hook_inventory();
		$p = preg_quote( $this->prefix, '/' );

		$optional_rules = [
			'section-tag' => [
				'present'  => isset( $inventory['classes'][ "{$this->prefix}-section-tag" ] ),
				'patterns' => [
					'/^\.' . $p . '-section-tag::before\s*\{[^}]*\}\s*$/mi',
				],
			],
			'eyebrow' => [
				'present'  => isset( $inventory['classes'][ "{$this->prefix}-eyebrow" ] ),
				'patterns' => [
					'/^\.' . $p . '-eyebrow::before\s*\{[^}]*\}\s*$/mi',
				],
			],
			'stat-cell' => [
				'present'  => isset( $inventory['classes'][ "{$this->prefix}-stat-cell" ] ),
				'patterns' => [
					'/^\.' . $p . '-stat-cell::before\s*\{[^}]*\}\s*$/mi',
					'/^\.' . $p . '-stat-cell\.' . $p . '-counted::before\s*\{[^}]*\}\s*$/mi',
				],
			],
			'card-tag' => [
				'present'  => isset( $inventory['classes'][ "{$this->prefix}-card-tag" ] ),
				'patterns' => [
					'/^\.' . $p . '-card-tag\s+\.elementor-heading-title::before\s*\{[^}]*\}\s*$/mi',
				],
			],
			'cta' => [
				'present'  => isset( $inventory['classes'][ "{$this->prefix}-cta" ] ) || isset( $inventory['ids'][ "{$this->prefix}-cta" ] ),
				'patterns' => [
					'/^\.' . $p . '-cta::before\s*\{[^}]*\}\s*$/mi',
					'/^\s*\.' . $p . '-cta::before\s*\{[^}]*\}\s*$/mi',
				],
			],
		];

		foreach ( $optional_rules as $rule ) {
			if ( ! empty( $rule['present'] ) ) {
				continue;
			}
			foreach ( $rule['patterns'] as $pattern ) {
				$css = preg_replace( $pattern, '', $css );
			}
		}

		return $css;
	}

	private function normalize_companion_css( string $css ): string {
		$p = preg_quote( $this->prefix, '/' );

		$css = preg_replace(
			'/(\.' . $p . '-[a-z0-9-]+)\s+\.elementor-widget-text-editor\s+p/',
			'$1 p',
			$css
		);

		$css .= "\n" . $this->build_structural_selector_aliases();
		$css .= "\n" . $this->build_source_selector_bridge_css();

		return $css;
	}

	private function record_companion_css_diagnostics( string $css ): void {
		$inventory = $this->get_current_emitted_hook_inventory();

		$unresolved_pseudo_hosts = [];
		if ( preg_match_all( '/([^{}]+)::(before|after)\s*\{/i', $css, $pseudo_matches, PREG_SET_ORDER ) ) {
			foreach ( $pseudo_matches as $match ) {
				$selector_block = trim( (string) $match[1] );
				foreach ( preg_split( '/\s*,\s*/', $selector_block ) as $selector ) {
					$selector = trim( (string) $selector );
					if ( '' === $selector ) {
						continue;
					}
					if ( ! $this->selector_targets_emitted_hook( $selector, $inventory ) ) {
						$unresolved_pseudo_hosts[ $selector . '::' . strtolower( (string) $match[2] ) ] = true;
					}
				}
			}
		}

		$defined_keyframes = [];
		if ( preg_match_all( '/@keyframes\s+([A-Za-z0-9_-]+)/', $css, $keyframe_matches ) ) {
			foreach ( $keyframe_matches[1] as $name ) {
				$defined_keyframes[ trim( (string) $name ) ] = true;
			}
		}

		$used_keyframes = [];
		if ( preg_match_all( '/animation(?:-name)?\s*:\s*([^;}{]+)/i', $css, $animation_matches ) ) {
			foreach ( $animation_matches[1] as $value ) {
				foreach ( preg_split( '/\s*,\s*/', (string) $value ) as $animation_value ) {
					$name = $this->extract_animation_name_from_value( $animation_value );
					if ( $name ) {
						$used_keyframes[ $name ] = true;
					}
				}
			}
		}

		$missing_keyframes = array_values( array_diff( array_keys( $used_keyframes ), array_keys( $defined_keyframes ) ) );
		$this->companion_css_coverage = [
			'unresolved_pseudo_hosts' => array_values( array_keys( $unresolved_pseudo_hosts ) ),
			'missing_keyframes'       => $missing_keyframes,
		];

		$this->diagnostics[] = [
			'code'    => 'companion_css_coverage',
			'message' => 'Companion CSS diagnostics recorded.',
			'context' => [
				'unresolved_pseudo_hosts' => array_values( array_keys( $unresolved_pseudo_hosts ) ),
				'missing_keyframes'       => $missing_keyframes,
			],
		];

		if ( ! empty( $unresolved_pseudo_hosts ) ) {
			$this->warnings[] = sprintf( 'Companion CSS diagnostics: %d pseudo-element hosts did not map to emitted hooks.', count( $unresolved_pseudo_hosts ) );
		}
		if ( ! empty( $missing_keyframes ) ) {
			$this->warnings[] = sprintf( 'Companion CSS diagnostics: %d animation names are used without matching @keyframes in companion CSS.', count( $missing_keyframes ) );
		}
	}

	private function selector_targets_emitted_hook( string $selector, array $inventory ): bool {
		$selector = trim( $selector );
		if ( '' === $selector ) {
			return false;
		}

		if ( str_contains( $selector, 'body.' . $this->prefix . '-page' ) ) {
			return true;
		}

		if ( preg_match_all( '/#([A-Za-z0-9_-]+)/', $selector, $id_matches ) ) {
			foreach ( $id_matches[1] as $id_name ) {
				if ( isset( $inventory['ids'][ $id_name ] ) ) {
					return true;
				}
			}
		}

		if ( preg_match_all( '/\.([A-Za-z0-9_-]+)/', $selector, $class_matches ) ) {
			foreach ( $class_matches[1] as $class_name ) {
				if ( isset( $inventory['classes'][ $class_name ] ) ) {
					return true;
				}
			}
		}

		return false;
	}

	private function extract_animation_name_from_value( string $value ): string {
		$value = trim( $value );
		if ( '' === $value ) {
			return '';
		}

		$tokens = preg_split( '/\s+/', $value );
		$keywords = [
			'infinite', 'linear', 'ease', 'ease-in', 'ease-out', 'ease-in-out',
			'forwards', 'backwards', 'both', 'none', 'normal', 'reverse',
			'alternate', 'alternate-reverse', 'running', 'paused', 'initial',
			'inherit', 'unset',
		];

		foreach ( $tokens as $token ) {
			$token = trim( (string) $token );
			if ( '' === $token ) {
				continue;
			}
			if ( preg_match( '/^\d+(?:\.\d+)?m?s$/i', $token ) ) {
				continue;
			}
			if ( in_array( strtolower( $token ), $keywords, true ) ) {
				continue;
			}
			return $token;
		}

		return '';
	}

	private function build_inline_markup_css(): string {
		if ( empty( $this->intel->raw_css ) ) {
			return '';
		}

		$markup = wp_json_encode( $this->section_payloads );
		if ( ! is_string( $markup ) || '' === $markup ) {
			return '';
		}

		preg_match_all( '/class=\\\\"([^\\\\"]+)\\\\"/', $markup, $matches );
		$classes = [];
		foreach ( $matches[1] ?? [] as $class_group ) {
			foreach ( preg_split( '/\s+/', html_entity_decode( (string) $class_group, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) as $class_name ) {
				$class_name = trim( $class_name );
				if ( $class_name ) {
					$classes[ $class_name ] = true;
				}
			}
		}

		$class_map = [];
		foreach ( array_keys( $classes ) as $class_name ) {
			$class_map[ $class_name ] = [ '.' . $class_name ];
		}

		preg_match_all( '/id=\\\\"([^\\\\"]+)\\\\"/', $markup, $id_matches );
		$id_map = [];
		foreach ( $id_matches[1] ?? [] as $id_name ) {
			$id_name = trim( html_entity_decode( (string) $id_name, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
			if ( $id_name ) {
				$id_map[ $id_name ] = [ '#' . $id_name ];
			}
		}

		if ( empty( $class_map ) && empty( $id_map ) ) {
			return '';
		}

		$source_hit_analysis = $this->analyze_source_css_bridge_hits( $this->intel->raw_css, $class_map, $id_map );
		$css = $this->rewrite_source_css_with_maps( $this->intel->raw_css, $class_map, $id_map, true );
		$inline_markup_bridge_coverage = [
			'has_source_css'     => true,
			'has_bridge_targets' => true,
			'has_source_selector_hits' => ! empty( $source_hit_analysis['has_selector_hits'] ),
			'matched_rule_count' => (int) ( $source_hit_analysis['matched_rule_count'] ?? 0 ),
			'has_output_css'     => '' !== trim( $css ),
			'bridged_classes'    => array_keys( $class_map ),
			'bridged_ids'        => array_keys( $id_map ),
		];
		$this->diagnostics[] = [
			'code'    => 'inline_markup_bridge',
			'message' => 'Inline markup selector bridge analysis recorded.',
			'context' => $inline_markup_bridge_coverage,
		];

		foreach ( $this->css->get_keyframes() as $kf ) {
			if ( preg_match( '/@keyframes\s+([\w-]+)/', $kf, $nm ) && str_contains( $css, $nm[1] ) ) {
				$css .= $kf . "\n";
			}
		}

		if ( '' === trim( $css ) && ! empty( $source_hit_analysis['has_selector_hits'] ) ) {
			$this->diagnostics[] = [
				'code'    => 'inline_markup_bridge_empty',
				'message' => 'Inline markup bridge found source selector hits but emitted no CSS.',
				'context' => $inline_markup_bridge_coverage,
			];
		}

		return $css ? "/* â”€â”€ INLINE MARKUP PRESERVATION */\n" . $css : '';
	}

	private function build_source_selector_bridge_css(): string {
		if ( empty( $this->intel->raw_css ) ) {
			$this->source_selector_bridge_coverage = [
				'has_source_css'      => false,
				'has_source_hook_candidates' => false,
				'has_bridge_targets'  => false,
				'has_source_selector_hits' => false,
				'matched_rule_count'  => 0,
				'has_output_css'      => false,
				'source_has_pseudo'   => false,
				'source_has_media'    => false,
				'source_has_supports' => false,
				'source_has_hover'    => false,
				'output_has_pseudo'   => false,
				'output_has_media'    => false,
				'output_has_supports' => false,
				'output_has_hover'    => false,
				'candidate_classes'   => [],
				'candidate_ids'       => [],
				'bridged_classes'     => [],
				'bridged_ids'         => [],
				'widget_semantics'    => $this->default_widget_semantic_coverage(),
			];
			return '';
		}

		$selector_maps = $this->collect_source_selector_bridge_map();
		$candidate_classes = array_keys( $selector_maps['classes'] ?? [] );
		$candidate_ids     = array_keys( $selector_maps['ids'] ?? [] );
		$has_candidates    = ! empty( $candidate_classes ) || ! empty( $candidate_ids );
		$class_map     = $this->filter_bridge_class_targets_to_emitted_hooks( $selector_maps['classes'] ?? [] );
		$id_map        = $this->filter_bridge_id_targets_to_emitted_hooks( $selector_maps['ids'] ?? [] );
		if ( empty( $class_map ) && empty( $id_map ) ) {
			$this->source_selector_bridge_coverage = [
				'has_source_css'      => true,
				'has_source_hook_candidates' => $has_candidates,
				'has_bridge_targets'  => false,
				'has_source_selector_hits' => false,
				'matched_rule_count'  => 0,
				'has_output_css'      => false,
				'source_has_pseudo'   => (bool) preg_match( '/::(before|after)\b/i', $this->intel->raw_css ),
				'source_has_media'    => (bool) preg_match( '/@media\b/i', $this->intel->raw_css ),
				'source_has_supports' => (bool) preg_match( '/@supports\b/i', $this->intel->raw_css ),
				'source_has_hover'    => (bool) preg_match( '/:hover\b/i', $this->intel->raw_css ),
				'output_has_pseudo'   => false,
				'output_has_media'    => false,
				'output_has_supports' => false,
				'output_has_hover'    => false,
				'candidate_classes'   => $candidate_classes,
				'candidate_ids'       => $candidate_ids,
				'bridged_classes'     => [],
				'bridged_ids'         => [],
				'widget_semantics'    => $this->default_widget_semantic_coverage(),
			];
			return '';
		}

		$source_hit_analysis = $this->analyze_source_css_bridge_hits( $this->intel->raw_css, $class_map, $id_map );
		$css = $this->rewrite_source_css_with_maps( $this->intel->raw_css, $class_map, $id_map, true );
		$this->source_selector_bridge_coverage = [
			'has_source_css'      => true,
			'has_source_hook_candidates' => $has_candidates,
			'has_bridge_targets'  => true,
			'has_source_selector_hits' => ! empty( $source_hit_analysis['has_selector_hits'] ),
			'matched_rule_count'  => (int) ( $source_hit_analysis['matched_rule_count'] ?? 0 ),
			'has_output_css'      => '' !== trim( $css ),
			'source_has_pseudo'   => ! empty( $source_hit_analysis['has_pseudo'] ),
			'source_has_media'    => ! empty( $source_hit_analysis['has_media'] ),
			'source_has_supports' => ! empty( $source_hit_analysis['has_supports'] ),
			'source_has_hover'    => ! empty( $source_hit_analysis['has_hover'] ),
			'output_has_pseudo'   => (bool) preg_match( '/::(before|after)\b/i', $css ),
			'output_has_media'    => (bool) preg_match( '/@media\b/i', $css ),
			'output_has_supports' => (bool) preg_match( '/@supports\b/i', $css ),
			'output_has_hover'    => (bool) preg_match( '/:hover\b/i', $css ),
			'candidate_classes'   => $candidate_classes,
			'candidate_ids'       => $candidate_ids,
			'bridged_classes'     => array_keys( $class_map ),
			'bridged_ids'         => array_keys( $id_map ),
			'widget_semantics'    => $this->native_widget_semantic_coverage,
		];
		$this->diagnostics[] = [
			'code'    => 'source_selector_bridge',
			'message' => 'Source selector bridge analysis recorded.',
			'context' => $this->source_selector_bridge_coverage,
		];

		foreach ( $this->css->get_keyframes() as $kf ) {
			if ( preg_match( '/@keyframes\s+([\w-]+)/', $kf, $nm ) && str_contains( $css, $nm[1] ) ) {
				$css .= $kf . "\n";
			}
		}

		if ( '' === trim( $css ) && ! empty( $source_hit_analysis['has_selector_hits'] ) ) {
			$this->warnings[] = 'Source selector bridge found source CSS hooks but could not emit any retargeted CSS.';
		}

		return $css ? "/* â”€â”€ SOURCE SELECTOR BRIDGE */\n" . $css : '';
	}

	private function analyze_source_css_bridge_hits( string $css, array $class_map, array $id_map, bool $inside_media = false, bool $inside_supports = false ): array {
		$css = trim( $css );
		if ( '' === $css ) {
			return [
				'has_selector_hits' => false,
				'has_pseudo'        => false,
				'has_media'         => false,
				'has_supports'      => false,
				'has_hover'         => false,
				'matched_rule_count'=> 0,
			];
		}

		$coverage = [
			'has_selector_hits' => false,
			'has_pseudo'        => false,
			'has_media'         => false,
			'has_supports'      => false,
			'has_hover'         => false,
			'matched_rule_count'=> 0,
		];
		$length = strlen( $css );
		$offset = 0;

		while ( $offset < $length ) {
			if ( preg_match( '/\G\s+/A', $css, $space_match, 0, $offset ) ) {
				$offset += strlen( $space_match[0] );
				continue;
			}

			if ( preg_match( '/\G@(media|supports)\b([^{]*)\{/Ai', $css, $at_match, 0, $offset ) ) {
				$type = strtolower( (string) $at_match[1] );
				$open_brace = $offset + strlen( $at_match[0] ) - 1;
				$close_brace = $this->find_matching_brace_position( $css, $open_brace );
				if ( null === $close_brace ) {
					break;
				}

				$inner_css = substr( $css, $open_brace + 1, $close_brace - $open_brace - 1 );
				$inner_coverage = $this->analyze_source_css_bridge_hits(
					$inner_css,
					$class_map,
					$id_map,
					$inside_media || 'media' === $type,
					$inside_supports || 'supports' === $type
				);
				$coverage['has_selector_hits'] = $coverage['has_selector_hits'] || ! empty( $inner_coverage['has_selector_hits'] );
				$coverage['has_pseudo']        = $coverage['has_pseudo'] || ! empty( $inner_coverage['has_pseudo'] );
				$coverage['has_media']         = $coverage['has_media'] || ! empty( $inner_coverage['has_media'] );
				$coverage['has_supports']      = $coverage['has_supports'] || ! empty( $inner_coverage['has_supports'] );
				$coverage['has_hover']         = $coverage['has_hover'] || ! empty( $inner_coverage['has_hover'] );
				$coverage['matched_rule_count'] += (int) ( $inner_coverage['matched_rule_count'] ?? 0 );
				$offset = $close_brace + 1;
				continue;
			}

			if ( preg_match( '/\G@[^;{]+;/A', $css, $statement_match, 0, $offset ) ) {
				$offset += strlen( $statement_match[0] );
				continue;
			}

			$next_open = strpos( $css, '{', $offset );
			if ( false === $next_open ) {
				break;
			}

			$selector_block = trim( substr( $css, $offset, $next_open - $offset ) );
			$close_brace = $this->find_matching_brace_position( $css, $next_open );
			if ( null === $close_brace ) {
				break;
			}

			$offset = $close_brace + 1;
			if ( '' === $selector_block || str_starts_with( $selector_block, '@' ) ) {
				continue;
			}

			$selectors = $this->rewrite_source_rule_selectors( $selector_block, $class_map, $id_map );
			if ( empty( $selectors ) ) {
				continue;
			}

			$coverage['has_selector_hits'] = true;
			$coverage['matched_rule_count']++;
			if ( preg_match( '/::(before|after)\b/i', $selector_block ) ) {
				$coverage['has_pseudo'] = true;
			}
			if ( preg_match( '/:hover\b/i', $selector_block ) ) {
				$coverage['has_hover'] = true;
			}
			if ( $inside_media ) {
				$coverage['has_media'] = true;
			}
			if ( $inside_supports ) {
				$coverage['has_supports'] = true;
			}
		}

		return $coverage;
	}

	private function collect_source_selector_bridge_map(): array {
		$maps = [
			'classes' => [],
			'ids'     => [],
		];
		$p   = $this->prefix;

		foreach ( $this->section_payloads as $type => $payload ) {
			$this->add_source_selector_bridge_targets(
				$maps['classes'],
				(string) ( $payload['source_class'] ?? '' ),
				[ '#' . $p . '-' . $type ]
			);
			$this->add_source_id_bridge_targets(
				$maps['ids'],
				(string) ( $payload['source_id'] ?? '' ),
				[ '#' . $p . '-' . $type ]
			);
			$this->collect_generic_payload_source_hooks(
				$payload,
				$maps['classes'],
				$maps['ids'],
				[ '#' . $p . '-' . $type ]
			);
		}

		foreach ( (array) ( $this->section_payloads['stats']['stats'] ?? [] ) as $index => $stat ) {
			$letter = chr( 97 + $index );
			$this->add_source_selector_bridge_targets(
				$maps['classes'],
				(string) ( $stat['source_class'] ?? '' ),
				[ '.'.$p.'-stat-cell', '.'.$p."-stat-card-{$letter}", '#'.$p."-stat-{$letter}" ]
			);
			$this->add_source_id_bridge_targets(
				$maps['ids'],
				(string) ( $stat['source_id'] ?? '' ),
				[ '#'.$p."-stat-{$letter}" ]
			);
		}

		foreach ( [ 'features', 'bento' ] as $type ) {
			foreach ( (array) ( $this->section_payloads[ $type ]['cards'] ?? [] ) as $index => $card ) {
				$letter = chr( 97 + $index );
				$this->add_source_selector_bridge_targets(
					$maps['classes'],
					(string) ( $card['source_class'] ?? '' ),
					[ '.'.$p.'-bento-card', '.'.$p."-bento-card-{$letter}", '.'.$p."-bc-{$letter}", '#'.$p."-bento-card-{$letter}" ]
				);
				$this->add_source_id_bridge_targets(
					$maps['ids'],
					(string) ( $card['source_id'] ?? '' ),
					[ '#'.$p."-bento-card-{$letter}" ]
				);
			}
		}

		foreach ( (array) ( $this->section_payloads['process']['steps'] ?? [] ) as $index => $step ) {
			$letter = chr( 97 + $index );
			$this->add_source_selector_bridge_targets(
				$maps['classes'],
				(string) ( $step['source_class'] ?? '' ),
				[ '.'.$p.'-process-step', '.'.$p."-process-step-{$letter}", '#'.$p."-process-step-{$letter}" ]
			);
			$this->add_source_id_bridge_targets(
				$maps['ids'],
				(string) ( $step['source_id'] ?? '' ),
				[ '#'.$p."-process-step-{$letter}" ]
			);
		}

		foreach ( (array) ( $this->section_payloads['testimonials']['cards'] ?? [] ) as $index => $card ) {
			$letter = chr( 97 + $index );
			$this->add_source_selector_bridge_targets(
				$maps['classes'],
				(string) ( $card['source_class'] ?? '' ),
				[ '.'.$p.'-testi-card', '.'.$p."-testi-card-{$letter}", '#'.$p."-testi-card-{$letter}" ]
			);
			$this->add_source_id_bridge_targets(
				$maps['ids'],
				(string) ( $card['source_id'] ?? '' ),
				[ '#'.$p."-testi-card-{$letter}" ]
			);
		}

		foreach ( (array) ( $this->section_payloads['pricing']['cards'] ?? [] ) as $index => $card ) {
			$letter = chr( 97 + $index );
			$this->add_source_selector_bridge_targets(
				$maps['classes'],
				(string) ( $card['source_class'] ?? '' ),
				[ '.'.$p.'-price-card', '.'.$p."-price-card-{$letter}", '#'.$p."-price-card-{$letter}" ]
			);
			$this->add_source_id_bridge_targets(
				$maps['ids'],
				(string) ( $card['source_id'] ?? '' ),
				[ '#'.$p."-price-card-{$letter}" ]
			);
		}

		return $maps;
	}

	private function rewrite_source_css_with_maps( string $css, array $class_map, array $id_map, bool $scope_root = true ): string {
		$css = trim( $css );
		if ( '' === $css ) {
			return '';
		}

		$result = '';
		$length = strlen( $css );
		$offset = 0;

		while ( $offset < $length ) {
			if ( preg_match( '/\G\s+/A', $css, $space_match, 0, $offset ) ) {
				$offset += strlen( $space_match[0] );
				continue;
			}

			if ( preg_match( '/\G@(media|supports)\b([^{]*)\{/Ai', $css, $at_match, 0, $offset ) ) {
				$header = '@' . $at_match[1] . $at_match[2];
				$open_brace = $offset + strlen( $at_match[0] ) - 1;
				$close_brace = $this->find_matching_brace_position( $css, $open_brace );
				if ( null === $close_brace ) {
					break;
				}

				$inner_css = substr( $css, $open_brace + 1, $close_brace - $open_brace - 1 );
				$rewritten_inner = $this->rewrite_source_css_with_maps( $inner_css, $class_map, $id_map, $scope_root );
				if ( '' !== trim( $rewritten_inner ) ) {
					$result .= trim( $header ) . " {\n" . trim( $rewritten_inner ) . "\n}\n";
				}
				$offset = $close_brace + 1;
				continue;
			}

			if ( preg_match( '/\G@[^;{]+;/A', $css, $statement_match, 0, $offset ) ) {
				$offset += strlen( $statement_match[0] );
				continue;
			}

			$next_open = strpos( $css, '{', $offset );
			if ( false === $next_open ) {
				break;
			}

			$selector_block = trim( substr( $css, $offset, $next_open - $offset ) );
			$close_brace = $this->find_matching_brace_position( $css, $next_open );
			if ( null === $close_brace ) {
				break;
			}

			$body = trim( substr( $css, $next_open + 1, $close_brace - $next_open - 1 ) );
			$offset = $close_brace + 1;

			if ( '' === $selector_block || '' === $body || str_starts_with( $selector_block, '@' ) ) {
				continue;
			}

			$selectors = $this->rewrite_source_rule_selectors( $selector_block, $class_map, $id_map );
			if ( empty( $selectors ) ) {
				continue;
			}

			if ( $scope_root ) {
				$selectors = $this->scope_selectors( $selectors );
			}

			$result .= implode( ', ', $selectors ) . " {\n" . $body . "\n}\n";
		}

		return $result;
	}

	private function find_matching_brace_position( string $css, int $open_brace ): ?int {
		$length = strlen( $css );
		$depth  = 0;

		for ( $index = $open_brace; $index < $length; $index++ ) {
			$char = $css[ $index ];
			if ( '{' === $char ) {
				$depth++;
				continue;
			}
			if ( '}' === $char ) {
				$depth--;
				if ( 0 === $depth ) {
					return $index;
				}
			}
		}

		return null;
	}

	private function collect_generic_payload_source_hooks( mixed $payload, array &$class_map, array &$id_map, array $targets ): void {
		if ( is_array( $payload ) ) {
			if ( isset( $payload['source_class'] ) && is_string( $payload['source_class'] ) ) {
				$this->add_source_selector_bridge_targets( $class_map, $payload['source_class'], $targets );
			}
			if ( isset( $payload['source_id'] ) && is_string( $payload['source_id'] ) ) {
				$this->add_source_id_bridge_targets( $id_map, $payload['source_id'], $targets );
			}

			foreach ( $payload as $value ) {
				if ( is_array( $value ) ) {
					$this->collect_generic_payload_source_hooks( $value, $class_map, $id_map, $targets );
				} elseif ( is_string( $value ) && str_contains( $value, '<' ) ) {
					$this->add_markup_hook_targets( $class_map, $id_map, $value, $targets );
				}
			}
		} elseif ( is_string( $payload ) && str_contains( $payload, '<' ) ) {
			$this->add_markup_hook_targets( $class_map, $id_map, $payload, $targets );
		}
	}

	private function add_markup_hook_targets( array &$class_map, array &$id_map, string $markup, array $targets ): void {
		if ( '' === trim( $markup ) ) {
			return;
		}

		if ( preg_match_all( '/class=(["\'])([^"\']+)\1/', $markup, $class_matches ) ) {
			foreach ( $class_matches[2] as $class_group ) {
				$this->add_source_selector_bridge_targets( $class_map, (string) $class_group, $targets );
			}
		}

		if ( preg_match_all( '/id=(["\'])([^"\']+)\1/', $markup, $id_matches ) ) {
			foreach ( $id_matches[2] as $id_name ) {
				$this->add_source_id_bridge_targets( $id_map, (string) $id_name, $targets );
			}
		}
	}

	private function add_source_selector_bridge_targets( array &$map, string $source_class_group, array $targets ): void {
		foreach ( preg_split( '/\s+/', trim( $source_class_group ) ) as $class_name ) {
			$class_name = trim( (string) $class_name );
			if ( ! $this->should_bridge_source_class( $class_name ) ) {
				continue;
			}
			if ( ! isset( $map[ $class_name ] ) ) {
				$map[ $class_name ] = [];
			}
			$map[ $class_name ] = array_values( array_unique( array_merge( $map[ $class_name ], $targets ) ) );
		}
	}

	private function add_source_id_bridge_targets( array &$map, string $source_id, array $targets ): void {
		$source_id = trim( $source_id );
		if ( '' === $source_id || str_starts_with( $source_id, $this->prefix . '-' ) ) {
			return;
		}
		if ( ! isset( $map[ $source_id ] ) ) {
			$map[ $source_id ] = [];
		}
		$map[ $source_id ] = array_values( array_unique( array_merge( $map[ $source_id ], $targets ) ) );
	}

	private function should_bridge_source_class( string $class_name ): bool {
		if ( '' === $class_name ) {
			return false;
		}
		if ( str_starts_with( $class_name, $this->prefix . '-' ) ) {
			return false;
		}
		if ( preg_match( '/^(?:reveal(?:-delay-\d+)?|visible|hidden|active|current|selected|open|closed|container|grid|row|col|section|widget)$/', $class_name ) ) {
			return false;
		}
		if ( str_starts_with( $class_name, 'elementor-' ) || str_starts_with( $class_name, 'e-' ) ) {
			return false;
		}
		return true;
	}

	private function source_hook_classes_for_node( \DOMElement $node ): string {
		return $this->source_hook_classes_from_parts(
			(string) $node->getAttribute( 'class' ),
			(string) $node->getAttribute( 'id' )
		);
	}

	private function source_hook_classes_from_parts( string $source_classes, string $source_id ): string {
		$hooks = [];
		foreach ( preg_split( '/\s+/', trim( $source_classes ) ) as $class_name ) {
			$class_name = trim( (string) $class_name );
			if ( $this->should_bridge_source_class( $class_name ) ) {
				$hooks[ $class_name ] = true;
			}
		}

		$source_id = trim( $source_id );
		if ( '' !== $source_id && ! str_starts_with( $source_id, $this->prefix . '-' ) && ! str_starts_with( $source_id, 'elementor-' ) ) {
			$hooks[ 'src-id-' . sanitize_html_class( $source_id ) ] = true;
		}

		return implode( ' ', array_keys( $hooks ) );
	}

	private function rewrite_source_rule_selectors( string $selector_block, array $class_map, array $id_map = [] ): array {
		$selectors = preg_split( '/\s*,\s*/', trim( $selector_block ) );
		$rewritten = [];

		foreach ( $selectors as $selector ) {
			$selector = trim( (string) $selector );
			if ( '' === $selector ) {
				continue;
			}

			$candidates = [ $selector ];
			$matched    = false;

			foreach ( $class_map as $source_class => $target_selectors ) {
				$pattern = '/\.' . preg_quote( $source_class, '/' ) . '([:\.\[\s>#\+~]|$)/';
				$next    = [];
				foreach ( $candidates as $candidate ) {
					if ( preg_match( $pattern, $candidate ) ) {
						$matched = true;
						foreach ( $target_selectors as $target_selector ) {
							$next[] = preg_replace( $pattern, $target_selector . '$1', $candidate );
						}
					} else {
						$next[] = $candidate;
					}
				}
				$candidates = array_values( array_unique( array_filter( $next ) ) );
			}

			foreach ( $id_map as $source_id => $target_selectors ) {
				$pattern = '/#' . preg_quote( $source_id, '/' ) . '([:\.\[\s>#\+~]|$)/';
				$next    = [];
				foreach ( $candidates as $candidate ) {
					if ( preg_match( $pattern, $candidate ) ) {
						$matched = true;
						foreach ( $target_selectors as $target_selector ) {
							$next[] = preg_replace( $pattern, $target_selector . '$1', $candidate );
						}
					} else {
						$next[] = $candidate;
					}
				}
				$candidates = array_values( array_unique( array_filter( $next ) ) );
			}

			if ( $matched ) {
				foreach ( $candidates as $candidate ) {
					$rewritten[ $this->rewrite_native_widget_semantics( $candidate ) ] = true;
				}
			}
		}

		return array_keys( $rewritten );
	}

	private function rewrite_native_widget_semantics( string $selector ): string {
		$inventory = $this->get_current_emitted_hook_inventory();
		$original_selector = $selector;
		$has_pseudo = (bool) preg_match( '/::(before|after)\b/i', $selector );
		$widget_wrappers = [
			'icon-list' => array_merge(
				array_map( fn( $class_name ) => '.' . $class_name, array_keys( (array) ( $inventory['icon_list_classes'] ?? [] ) ) ),
				array_map( fn( $id_name ) => '#' . $id_name, array_keys( (array) ( $inventory['icon_list_ids'] ?? [] ) ) )
			),
			'text' => array_merge(
				array_map( fn( $class_name ) => '.' . $class_name, array_keys( (array) ( $inventory['text_classes'] ?? [] ) ) ),
				array_map( fn( $id_name ) => '#' . $id_name, array_keys( (array) ( $inventory['text_ids'] ?? [] ) ) )
			),
			'heading' => array_merge(
				array_map( fn( $class_name ) => '.' . $class_name, array_keys( (array) ( $inventory['heading_classes'] ?? [] ) ) ),
				array_map( fn( $id_name ) => '#' . $id_name, array_keys( (array) ( $inventory['heading_ids'] ?? [] ) ) )
			),
			'button' => array_merge(
				array_map( fn( $class_name ) => '.' . $class_name, array_keys( (array) ( $inventory['button_classes'] ?? [] ) ) ),
				array_map( fn( $id_name ) => '#' . $id_name, array_keys( (array) ( $inventory['button_ids'] ?? [] ) ) )
			),
			'image' => array_merge(
				array_map( fn( $class_name ) => '.' . $class_name, array_keys( (array) ( $inventory['image_classes'] ?? [] ) ) ),
				array_map( fn( $id_name ) => '#' . $id_name, array_keys( (array) ( $inventory['image_ids'] ?? [] ) ) )
			),
			'media' => array_merge(
				array_map( fn( $class_name ) => '.' . $class_name, array_keys( (array) ( $inventory['media_classes'] ?? [] ) ) ),
				array_map( fn( $id_name ) => '#' . $id_name, array_keys( (array) ( $inventory['media_ids'] ?? [] ) ) )
			),
		];

		foreach ( $widget_wrappers['icon-list'] as $wrapper ) {
			if ( ! str_contains( $selector, $wrapper ) ) {
				continue;
			}

			$escaped = preg_quote( $wrapper, '/' );
			if ( preg_match( '/' . $escaped . '((?:[^,{]*)?)\s+(?:ul|ol|li|a)(?=[:\.\[#\s>+~]|$)/i', $selector ) ) {
				$this->native_widget_semantic_coverage['icon-list']['source'] = true;
				if ( $has_pseudo ) {
					$this->native_widget_semantic_coverage['icon-list']['pseudo_source'] = true;
				}
			}

			$selector = preg_replace(
				'/' . $escaped . '((?:[^,{]*)?)\s+(?:ul|ol)(?=[:\.\[#\s>+~]|$)/i',
				$wrapper . '$1 .elementor-icon-list-items',
				$selector
			);
			$selector = preg_replace(
				'/' . $escaped . '((?:[^,{]*)?)\s+li(?=::(?:before|after))/i',
				$wrapper . '$1 .elementor-icon-list-icon i',
				$selector
			);
			$selector = preg_replace(
				'/' . $escaped . '((?:[^,{]*)?)\s+a(?=::(?:before|after))/i',
				$wrapper . '$1 .elementor-icon-list-icon i',
				$selector
			);
			$selector = preg_replace(
				'/' . $escaped . '((?:[^,{]*)?)\s+li(?=[:\.\[#\s>+~]|$)/i',
				$wrapper . '$1 .elementor-icon-list-item',
				$selector
			);
			$selector = preg_replace(
				'/' . $escaped . '((?:[^,{]*)?)\s+a(?=[:\.\[#\s>+~]|$)/i',
				$wrapper . '$1 .elementor-icon-list-text',
				$selector
			);
			$selector = str_replace( '.elementor-icon-list-icon i::before', '.elementor-icon-list-icon i', $selector );
			$selector = str_replace( '.elementor-icon-list-icon i::after', '.elementor-icon-list-icon i', $selector );
			if ( $selector !== $original_selector && str_contains( $selector, '.elementor-icon-list-' ) ) {
				$this->native_widget_semantic_coverage['icon-list']['output'] = true;
				if ( $has_pseudo ) {
					$this->native_widget_semantic_coverage['icon-list']['pseudo_output'] = true;
				}
			}
		}

		foreach ( $widget_wrappers['text'] as $wrapper ) {
			if ( ! str_contains( $selector, $wrapper ) ) {
				continue;
			}

			$escaped = preg_quote( $wrapper, '/' );
			if ( preg_match( '/' . $escaped . '\s*>\s*(?:p|a|ul|ol|blockquote|span)(?=[:\.\[#\s>+~]|$)/i', $selector ) ) {
				$this->native_widget_semantic_coverage['text']['source'] = true;
				if ( $has_pseudo ) {
					$this->native_widget_semantic_coverage['text']['pseudo_source'] = true;
				}
			}
			$selector = preg_replace(
				'/' . $escaped . '\s*>\s*(p|a|ul|ol|blockquote)(?=[:\.\[#\s>+~]|$)/i',
				$wrapper . ' .elementor-widget-container > $1',
				$selector
			);
			$selector = preg_replace(
				'/' . $escaped . '\s*>\s*span(?=[:\.\[#\s>+~]|$)/i',
				$wrapper . ' .elementor-widget-container span',
				$selector
			);
			if ( $selector !== $original_selector && str_contains( $selector, '.elementor-widget-container' ) ) {
				$this->native_widget_semantic_coverage['text']['output'] = true;
				if ( $has_pseudo ) {
					$this->native_widget_semantic_coverage['text']['pseudo_output'] = true;
				}
			}
		}

		foreach ( $widget_wrappers['heading'] as $wrapper ) {
			if ( ! str_contains( $selector, $wrapper ) ) {
				continue;
			}

			$escaped = preg_quote( $wrapper, '/' );
			if ( preg_match( '/' . $escaped . '((?:[^,{]*)?)\s+h[1-6](?=[:\.\[#\s>+~]|$)/i', $selector ) ) {
				$this->native_widget_semantic_coverage['heading']['source'] = true;
				if ( $has_pseudo ) {
					$this->native_widget_semantic_coverage['heading']['pseudo_source'] = true;
				}
			}
			$selector = preg_replace(
				'/' . $escaped . '((?:[^,{]*)?)\s+h[1-6](?=[:\.\[#\s>+~]|$)/i',
				$wrapper . '$1 .elementor-heading-title',
				$selector
			);
			if ( $selector !== $original_selector && str_contains( $selector, '.elementor-heading-title' ) ) {
				$this->native_widget_semantic_coverage['heading']['output'] = true;
				if ( $has_pseudo ) {
					$this->native_widget_semantic_coverage['heading']['pseudo_output'] = true;
				}
			}
		}

		foreach ( $widget_wrappers['button'] as $wrapper ) {
			if ( ! str_contains( $selector, $wrapper ) ) {
				continue;
			}

			$escaped = preg_quote( $wrapper, '/' );
			if ( preg_match( '/' . $escaped . '((?:[^,{]*)?)\s+(?:a|button)(?:\s+span)?(?=[:\.\[#\s>+~]|$)/i', $selector ) ) {
				$this->native_widget_semantic_coverage['button']['source'] = true;
				if ( $has_pseudo ) {
					$this->native_widget_semantic_coverage['button']['pseudo_source'] = true;
				}
			}
			$selector = preg_replace(
				'/' . $escaped . '((?:[^,{]*)?)\s+(?:a|button)(?=[:\.\[#\s>+~]|$)/i',
				$wrapper . '$1 .elementor-button',
				$selector
			);
			$selector = preg_replace(
				'/' . $escaped . '((?:[^,{]*)?)\s+(?:a|button)\s+span(?=[:\.\[#\s>+~]|$)/i',
				$wrapper . '$1 .elementor-button-text',
				$selector
			);
			if ( $selector !== $original_selector && ( str_contains( $selector, '.elementor-button' ) || str_contains( $selector, '.elementor-button-text' ) ) ) {
				$this->native_widget_semantic_coverage['button']['output'] = true;
				if ( $has_pseudo ) {
					$this->native_widget_semantic_coverage['button']['pseudo_output'] = true;
				}
			}
		}

		foreach ( $widget_wrappers['image'] as $wrapper ) {
			if ( ! str_contains( $selector, $wrapper ) ) {
				continue;
			}

			$escaped = preg_quote( $wrapper, '/' );
			if ( preg_match( '/' . $escaped . '((?:[^,{]*)?)\s+(?:figure|picture|img)(?=[:\.\[#\s>+~]|$)/i', $selector ) ) {
				$this->native_widget_semantic_coverage['image']['source'] = true;
				if ( $has_pseudo ) {
					$this->native_widget_semantic_coverage['image']['pseudo_source'] = true;
				}
			}
			$selector = preg_replace(
				'/' . $escaped . '((?:[^,{]*)?)\s+(?:figure|picture|img)(?=::(?:before|after))/i',
				$wrapper . '$1 .elementor-image',
				$selector
			);
			$selector = preg_replace(
				'/' . $escaped . '((?:[^,{]*)?)\s+figure(?=[:\.\[#\s>+~]|$)/i',
				$wrapper . '$1 .elementor-image',
				$selector
			);
			$selector = preg_replace(
				'/' . $escaped . '((?:[^,{]*)?)\s+(?:picture|img)(?=[:\.\[#\s>+~]|$)/i',
				$wrapper . '$1 .elementor-image img',
				$selector
			);
			if ( $selector !== $original_selector && str_contains( $selector, '.elementor-image' ) ) {
				$this->native_widget_semantic_coverage['image']['output'] = true;
				if ( $has_pseudo ) {
					$this->native_widget_semantic_coverage['image']['pseudo_output'] = true;
				}
			}
		}

		foreach ( $widget_wrappers['media'] as $wrapper ) {
			if ( ! str_contains( $selector, $wrapper ) ) {
				continue;
			}

			$escaped = preg_quote( $wrapper, '/' );
			if ( preg_match( '/' . $escaped . '((?:[^,{]*)?)\s+(?:video|iframe)(?=[:\.\[#\s>+~]|$)/i', $selector ) ) {
				$this->native_widget_semantic_coverage['media']['source'] = true;
				if ( $has_pseudo ) {
					$this->native_widget_semantic_coverage['media']['pseudo_source'] = true;
				}
			}
			$selector = preg_replace(
				'/' . $escaped . '((?:[^,{]*)?)\s+(?:video|iframe)(?=::(?:before|after))/i',
				$wrapper . '$1 .elementor-wrapper',
				$selector
			);
			$selector = preg_replace(
				'/' . $escaped . '((?:[^,{]*)?)\s+\.(?:video|embed|player)(?=::(?:before|after))/i',
				$wrapper . '$1 .elementor-wrapper',
				$selector
			);
			$selector = preg_replace(
				'/' . $escaped . '((?:[^,{]*)?)\s+(?:video|iframe)(?=[:\.\[#\s>+~]|$)/i',
				$wrapper . '$1 .elementor-wrapper iframe',
				$selector
			);
			$selector = preg_replace(
				'/' . $escaped . '((?:[^,{]*)?)\s+\.(?:video|embed|player)(?=[:\.\[#\s>+~]|$)/i',
				$wrapper . '$1 .elementor-wrapper',
				$selector
			);
			if ( $selector !== $original_selector && ( str_contains( $selector, '.elementor-wrapper' ) || str_contains( $selector, '.elementor-custom-embed-image-overlay' ) ) ) {
				$this->native_widget_semantic_coverage['media']['output'] = true;
				if ( $has_pseudo ) {
					$this->native_widget_semantic_coverage['media']['pseudo_output'] = true;
				}
			}
		}

		return preg_replace( '/\s+/', ' ', trim( $selector ) ) ?? $selector;
	}

	private function scope_selectors( array $selectors ): array {
		$scope  = 'body.' . $this->prefix . '-page';
		$result = [];

		foreach ( $selectors as $selector ) {
			$selector = trim( (string) $selector );
			if ( '' === $selector ) {
				continue;
			}

			if ( str_starts_with( $selector, $scope ) ) {
				$result[ $selector ] = true;
				continue;
			}

			if ( preg_match( '/^body([:\.\[#\s>+~]|$)/', $selector ) ) {
				$selector = preg_replace( '/^body/', $scope, $selector, 1 );
				$result[ $selector ] = true;
				continue;
			}

			$result[ $scope . ' ' . $selector ] = true;
		}

		return array_keys( $result );
	}

	private function build_source_script_bridge_js(): string {
		if ( empty( $this->intel->raw_js ) ) {
			$this->source_script_bridge_coverage = [
				'has_source_js'             => false,
				'has_source_hook_candidates'=> false,
				'has_bridge_targets'        => false,
				'has_source_selector_hits'  => false,
				'has_rewrite'               => false,
				'rewrite_count'             => 0,
				'source_has_animation_api'  => false,
				'source_has_selector_api'   => false,
				'candidate_classes'         => [],
				'candidate_ids'             => [],
				'bridged_classes'           => [],
				'bridged_ids'               => [],
				'widget_semantics'          => $this->default_widget_semantic_coverage(),
			];
			return '';
		}

		$selector_maps = $this->collect_source_selector_bridge_map();
		$candidate_classes = array_keys( $selector_maps['classes'] ?? [] );
		$candidate_ids     = array_keys( $selector_maps['ids'] ?? [] );
		$has_candidates    = ! empty( $candidate_classes ) || ! empty( $candidate_ids );
		$class_map     = $this->filter_bridge_class_targets_to_emitted_hooks( $selector_maps['classes'] ?? [] );
		$id_map        = $this->filter_bridge_id_targets_to_emitted_hooks( $selector_maps['ids'] ?? [] );
		if ( empty( $class_map ) && empty( $id_map ) ) {
			$this->source_script_bridge_coverage = [
				'has_source_js'             => true,
				'has_source_hook_candidates'=> $has_candidates,
				'has_bridge_targets'        => false,
				'has_source_selector_hits'  => false,
				'has_rewrite'               => false,
				'rewrite_count'             => 0,
				'source_has_animation_api'  => (bool) preg_match( '/requestAnimationFrame|setInterval|setTimeout|IntersectionObserver/i', $this->intel->raw_js ),
				'source_has_selector_api'   => (bool) preg_match( '/querySelector|querySelectorAll|getElementById|getElementsByClassName|classList\.|jQuery\s*\(|\$\s*\(|\.(?:on|one|delegate|undelegate|is|find|filter|not|closest|parents|children|siblings|has|next|prev|nextAll|prevAll|parentsUntil|nextUntil|prevUntil)\s*\(|\.(?:hasClass|addClass|removeClass|toggleClass)\s*\(/i', $this->intel->raw_js ),
				'candidate_classes'         => $candidate_classes,
				'candidate_ids'             => $candidate_ids,
				'bridged_classes'           => [],
				'bridged_ids'               => [],
				'widget_semantics'          => $this->default_widget_semantic_coverage(),
			];
			return '';
		}

		$source_js_hit_analysis = $this->analyze_source_js_bridge_hits( $this->intel->raw_js, $class_map, $id_map );
		$rewrite = $this->rewrite_source_js_with_maps( $this->intel->raw_js, $class_map, $id_map, true );
		$js = $rewrite['js'] ?? '';
		$js = $this->apply_js_runtime_safety( $js );
		$has_rewrite = ! empty( $rewrite['has_rewrite'] );
		$rewrite_count = (int) ( $rewrite['rewrite_count'] ?? 0 );
		$this->source_script_bridge_coverage = [
			'has_source_js'             => '' !== trim( (string) $this->intel->raw_js ),
			'has_source_hook_candidates'=> $has_candidates,
			'has_bridge_targets'        => true,
			'has_source_selector_hits'  => ! empty( $source_js_hit_analysis['has_selector_hits'] ),
			'has_rewrite'               => $has_rewrite,
			'rewrite_count'             => $rewrite_count,
			'source_has_animation_api'  => (bool) preg_match( '/requestAnimationFrame|setInterval|setTimeout|IntersectionObserver/i', $this->intel->raw_js ),
			'source_has_selector_api'   => (bool) preg_match( '/querySelector|querySelectorAll|getElementById|getElementsByClassName|classList\.|jQuery\s*\(|\$\s*\(|\.(?:on|one|delegate|undelegate|is|find|filter|not|closest|parents|children|siblings|has|next|prev|nextAll|prevAll|parentsUntil|nextUntil|prevUntil)\s*\(|\.(?:hasClass|addClass|removeClass|toggleClass)\s*\(/i', $this->intel->raw_js ),
			'candidate_classes'         => $candidate_classes,
			'candidate_ids'             => $candidate_ids,
			'bridged_classes'           => array_keys( $class_map ),
			'bridged_ids'               => array_keys( $id_map ),
			'widget_semantics'          => $rewrite['widget_semantics'] ?? $this->default_widget_semantic_coverage(),
		];

		$this->diagnostics[] = [
			'code'    => 'source_script_bridge',
			'message' => 'Source script bridge analysis recorded.',
			'context' => $this->source_script_bridge_coverage,
		];

		if ( ! $has_rewrite && ! empty( $source_js_hit_analysis['has_selector_hits'] ) ) {
			$this->warnings[] = 'Source script bridge found source JS but could not retarget any selectors or state hooks to emitted output.';
			return '';
		}

		return "/* ── Source Script Bridge ── */\ntry{\n{$js}\n}catch(e){console.warn('{$this->prefix} source script bridge failed',e);}";
	}

	private function apply_js_runtime_safety( string $js ): string {
		return ScriptBridgeHelper::apply_runtime_safety(
			$js,
			$this->extract_inline_handler_function_names()
		);
	}

	private function extract_inline_handler_function_names(): array {
		return ScriptBridgeHelper::extract_inline_handler_names(
			(string) ( $this->intel->raw_html ?? '' )
		);
	}

	private function rewrite_source_js_with_maps( string $js, array $class_map, array $id_map, bool $scope_root = true ): array {
		$rewritten = $js;
		$rewrite_count = 0;
		$widget_semantics = $this->default_widget_semantic_coverage();

		$rewrite_selector_literal = function( string $selector ) use ( $class_map, $id_map, $scope_root, &$rewrite_count, &$widget_semantics ): string {
			$selector = trim( $selector );
			if ( '' === $selector ) {
				return $selector;
			}

			$rewritten_selectors = $this->rewrite_source_rule_selectors( $selector, $class_map, $id_map );
			if ( empty( $rewritten_selectors ) ) {
				return $selector;
			}

			$final_selectors = $scope_root ? $this->scope_selectors( $rewritten_selectors ) : $rewritten_selectors;
			$final_selector  = implode( ', ', $final_selectors );
			if ( $final_selector !== $selector ) {
				$rewrite_count++;
			}
			$this->record_js_widget_semantic_coverage( $selector, $final_selector, $widget_semantics );

			return $final_selector;
		};

		$rewrite_class_name = function( string $class_name ) use ( $class_map, &$rewrite_count ): string {
			$class_name = trim( $class_name );
			if ( '' === $class_name || empty( $class_map[ $class_name ] ) ) {
				return $class_name;
			}

			foreach ( (array) $class_map[ $class_name ] as $target ) {
				if ( str_starts_with( $target, '.' ) ) {
					$mapped = substr( $target, 1 );
					if ( '' !== $mapped && $mapped !== $class_name ) {
						$rewrite_count++;
						return $mapped;
					}
				}
			}

			return $class_name;
		};

		$rewrite_id_name = function( string $id_name ) use ( $id_map, &$rewrite_count ): string {
			$id_name = trim( $id_name );
			if ( '' === $id_name || empty( $id_map[ $id_name ] ) ) {
				return $id_name;
			}

			foreach ( (array) $id_map[ $id_name ] as $target ) {
				if ( str_starts_with( $target, '#' ) ) {
					$mapped = substr( $target, 1 );
					if ( '' !== $mapped && $mapped !== $id_name ) {
						$rewrite_count++;
						return $mapped;
					}
				}
			}

			return $id_name;
		};

		$selector_function_pattern = '/(\b(?:querySelector|querySelectorAll|closest|matches)\s*\(\s*)([\'"])(.*?)\2/s';
		$rewritten = preg_replace_callback(
			$selector_function_pattern,
			function( array $match ) use ( $rewrite_selector_literal ) {
				$updated = $rewrite_selector_literal( (string) $match[3] );
				return $match[1] . $match[2] . $updated . $match[2];
			},
			$rewritten
		);

		$jquery_pattern = '/(\b(?:jQuery|\$)\s*\(\s*)([\'"])([.#][^\'"]*)\2/s';
		$rewritten = preg_replace_callback(
			$jquery_pattern,
			function( array $match ) use ( $rewrite_selector_literal ) {
				$updated = $rewrite_selector_literal( (string) $match[3] );
				return $match[1] . $match[2] . $updated . $match[2];
			},
			$rewritten
		);

		$jquery_selector_methods = '/(\.(?:is|find|filter|not|closest|parents|children|siblings|has|next|prev|nextAll|prevAll|parentsUntil|nextUntil|prevUntil)\s*\(\s*)([\'"])([^\'"]+)\2/s';
		$rewritten = preg_replace_callback(
			$jquery_selector_methods,
			function( array $match ) use ( $rewrite_selector_literal ) {
				$updated = $rewrite_selector_literal( (string) $match[3] );
				return $match[1] . $match[2] . $updated . $match[2];
			},
			$rewritten
		);

		$jquery_delegated_methods = '/(\.(?:on|one|delegate|undelegate)\s*\(\s*[\'"][^\'"]+[\'"]\s*,\s*)([\'"])([^\'"]+)\2/s';
		$rewritten = preg_replace_callback(
			$jquery_delegated_methods,
			function( array $match ) use ( $rewrite_selector_literal ) {
				$updated = $rewrite_selector_literal( (string) $match[3] );
				return $match[1] . $match[2] . $updated . $match[2];
			},
			$rewritten
		);

		$get_by_id_pattern = '/(\bgetElementById\s*\(\s*)([\'"])([^\'"]+)\2/s';
		$rewritten = preg_replace_callback(
			$get_by_id_pattern,
			function( array $match ) use ( $rewrite_id_name ) {
				$updated = $rewrite_id_name( (string) $match[3] );
				return $match[1] . $match[2] . $updated . $match[2];
			},
			$rewritten
		);

		$jquery_class_methods = '/(\.(?:hasClass|addClass|removeClass|toggleClass)\s*\(\s*)([\'"])([^\'"]+)\2/s';
		$rewritten = preg_replace_callback(
			$jquery_class_methods,
			function( array $match ) use ( $rewrite_class_name ) {
				$updated = $rewrite_class_name( (string) $match[3] );
				return $match[1] . $match[2] . $updated . $match[2];
			},
			$rewritten
		);

		$get_by_class_pattern = '/(\bgetElementsByClassName\s*\(\s*)([\'"])([^\'"]+)\2/s';
		$rewritten = preg_replace_callback(
			$get_by_class_pattern,
			function( array $match ) use ( $rewrite_class_name ) {
				$updated = $rewrite_class_name( (string) $match[3] );
				return $match[1] . $match[2] . $updated . $match[2];
			},
			$rewritten
		);

		$class_list_methods = '/(classList\.(?:contains|add|remove|toggle)\s*\(\s*)([\'"])([^\'"]+)\2/s';
		$rewritten = preg_replace_callback(
			$class_list_methods,
			function( array $match ) use ( $rewrite_class_name ) {
				$updated = $rewrite_class_name( (string) $match[3] );
				return $match[1] . $match[2] . $updated . $match[2];
			},
			$rewritten
		);

		$class_list_replace = '/(classList\.replace\s*\(\s*)([\'"])([^\'"]+)\2(\s*,\s*)([\'"])([^\'"]+)\5/s';
		$rewritten = preg_replace_callback(
			$class_list_replace,
			function( array $match ) use ( $rewrite_class_name ) {
				$from = $rewrite_class_name( (string) $match[3] );
				$to   = $rewrite_class_name( (string) $match[6] );
				return $match[1] . $match[2] . $from . $match[2] . $match[4] . $match[5] . $to . $match[5];
			},
			$rewritten
		);

		return [
			'js'            => $rewritten,
			'has_rewrite'   => $rewrite_count > 0 && $rewritten !== $js,
			'rewrite_count' => $rewrite_count,
			'widget_semantics' => $widget_semantics,
		];
	}

	private function analyze_source_js_bridge_hits( string $js, array $class_map, array $id_map ): array {
		$js = trim( $js );
		if ( '' === $js ) {
			return [
				'has_selector_hits' => false,
			];
		}

		foreach ( array_keys( $class_map ) as $class_name ) {
			$quoted = preg_quote( $class_name, '/' );
			$selector_pattern = '/[\'"][^\'"]*\.' . $quoted . '(?=[:\.\[#\s>+~]|$)[^\'"]*[\'"]/i';
			$class_api_pattern = '/(?:getElementsByClassName|classList\.(?:contains|add|remove|toggle|replace)|\.(?:hasClass|addClass|removeClass|toggleClass))\s*\(\s*[\'"]' . $quoted . '[\'"]/i';
			if ( preg_match( $selector_pattern, $js ) || preg_match( $class_api_pattern, $js ) ) {
				return [ 'has_selector_hits' => true ];
			}
		}

		foreach ( array_keys( $id_map ) as $id_name ) {
			$quoted = preg_quote( $id_name, '/' );
			$selector_pattern = '/[\'"][^\'"]*#' . $quoted . '(?=[:\.\[#\s>+~]|$)[^\'"]*[\'"]/i';
			$id_api_pattern = '/getElementById\s*\(\s*[\'"]' . $quoted . '[\'"]/i';
			if ( preg_match( $selector_pattern, $js ) || preg_match( $id_api_pattern, $js ) ) {
				return [ 'has_selector_hits' => true ];
			}
		}

		return [
			'has_selector_hits' => false,
		];
	}

	private function record_js_widget_semantic_coverage( string $source_selector, string $output_selector, array &$coverage ): void {
		$checks = [
			'icon-list' => [
				'source' => '/(?:ul|ol|li|a)(?=[:\.\[#\s>+~]|$)/i',
				'output' => '/\.elementor-icon-list-(?:items|item|text|icon)/i',
			],
			'text' => [
				'source' => '/>\s*(?:p|a|ul|ol|blockquote|span)(?=[:\.\[#\s>+~]|$)/i',
				'output' => '/\.elementor-widget-container/i',
			],
			'heading' => [
				'source' => '/h[1-6](?=[:\.\[#\s>+~]|$)/i',
				'output' => '/\.elementor-heading-title/i',
			],
			'button' => [
				'source' => '/(?:a|button)(?:\s+span)?(?=[:\.\[#\s>+~]|$)/i',
				'output' => '/\.elementor-button(?:-text)?/i',
			],
			'image' => [
				'source' => '/(?:figure|picture|img)(?=[:\.\[#\s>+~]|$)/i',
				'output' => '/\.elementor-image(?:\s+img)?/i',
			],
			'media' => [
				'source' => '/(?:video|iframe)(?=[:\.\[#\s>+~]|$)/i',
				'output' => '/\.elementor-wrapper|\.elementor-custom-embed-image-overlay/i',
			],
		];

		foreach ( $checks as $family => $patterns ) {
			if ( preg_match( $patterns['source'], $source_selector ) ) {
				$coverage[ $family ]['source'] = true;
				if ( preg_match( $patterns['output'], $output_selector ) ) {
					$coverage[ $family ]['output'] = true;
				}
			}
		}
	}

	private function filter_bridge_class_targets_to_emitted_hooks( array $class_map ): array {
		$inventory = $this->get_current_emitted_hook_inventory();
		$filtered  = [];

		foreach ( $class_map as $source_class => $target_selectors ) {
			$valid = [];
			foreach ( $target_selectors as $target_selector ) {
				$target_selector = trim( (string) $target_selector );
				if ( str_starts_with( $target_selector, '.' ) ) {
					$class_name = substr( $target_selector, 1 );
					if ( isset( $inventory['classes'][ $class_name ] ) ) {
						$valid[] = $target_selector;
					}
				} elseif ( str_starts_with( $target_selector, '#' ) ) {
					$id_name = substr( $target_selector, 1 );
					if ( isset( $inventory['ids'][ $id_name ] ) ) {
						$valid[] = $target_selector;
					}
				}
			}
			if ( ! empty( $valid ) ) {
				$filtered[ $source_class ] = array_values( array_unique( $valid ) );
			}
		}

		return $filtered;
	}

	private function filter_bridge_id_targets_to_emitted_hooks( array $id_map ): array {
		$inventory = $this->get_current_emitted_hook_inventory();
		$filtered  = [];

		foreach ( $id_map as $source_id => $target_selectors ) {
			$valid = [];
			foreach ( $target_selectors as $target_selector ) {
				$target_selector = trim( (string) $target_selector );
				if ( str_starts_with( $target_selector, '#' ) ) {
					$id_name = substr( $target_selector, 1 );
					if ( isset( $inventory['ids'][ $id_name ] ) ) {
						$valid[] = $target_selector;
					}
				} elseif ( str_starts_with( $target_selector, '.' ) ) {
					$class_name = substr( $target_selector, 1 );
					if ( isset( $inventory['classes'][ $class_name ] ) ) {
						$valid[] = $target_selector;
					}
				}
			}
			if ( ! empty( $valid ) ) {
				$filtered[ $source_id ] = array_values( array_unique( $valid ) );
			}
		}

		return $filtered;
	}

	private function get_current_emitted_hook_inventory(): array {
		if ( empty( $this->emitted_hook_inventory['classes'] ) && empty( $this->emitted_hook_inventory['ids'] ) ) {
			$this->emitted_hook_inventory = $this->collect_emitted_hook_inventory( $this->elements );
		}

		return $this->emitted_hook_inventory;
	}

	private function append_source_script_bridge_to_global_setup(): void {
		$bridge_js = $this->build_source_script_bridge_js();
		if ( '' === $bridge_js ) {
			return;
		}
		$script_block = "\n<script>\n(function(){\n{$bridge_js}\n}());\n</script>";
		foreach ( $this->elements as &$top ) {
			if ( ! is_array( $top ) ) {
				continue;
			}
			$top_id = (string) ( $top['settings']['_element_id'] ?? '' );
			$is_global_root = ( "{$this->prefix}-global-setup" === $top_id );
			if ( ! $is_global_root && ! str_contains( (string) ( $top['settings']['_css_classes'] ?? '' ), "{$this->prefix}-global-setup" ) ) {
				continue;
			}
			if ( ! isset( $top['elements'] ) || ! is_array( $top['elements'] ) ) {
				continue;
			}
			foreach ( $top['elements'] as &$child ) {
				if ( ! is_array( $child ) || 'widget' !== ( $child['elType'] ?? '' ) || 'html' !== ( $child['widgetType'] ?? '' ) ) {
					continue;
				}
				$html = (string) ( $child['settings']['html'] ?? '' );
				if ( '' === trim( $html ) ) {
					continue;
				}
				$child['settings']['html'] = $html . $script_block;
				return;
			}
			unset( $child );
		}
		unset( $top );

		// Backward compatibility: global setup may still be a top-level html widget.
		foreach ( $this->elements as &$top ) {
			if ( ! is_array( $top ) || 'widget' !== ( $top['elType'] ?? '' ) || 'html' !== ( $top['widgetType'] ?? '' ) ) {
				continue;
			}
			$classes = (string) ( $top['settings']['_css_classes'] ?? '' );
			$eid = (string) ( $top['settings']['_element_id'] ?? '' );
			if ( ! str_contains( $classes, "{$this->prefix}-global-setup" ) && "{$this->prefix}-global-setup" !== $eid ) {
				continue;
			}
			$html = (string) ( $top['settings']['html'] ?? '' );
			if ( '' === trim( $html ) ) {
				continue;
			}
			$top['settings']['html'] = $html . $script_block;
			return;
		}
		unset( $top );
	}

	private function build_structural_selector_aliases(): string {
		$p = $this->prefix;

		return <<<CSS

/* â”€â”€ STRUCTURAL ALIASES */
[id^="{$p}-stat-"], .{$p}-stat-cell { position: relative; }
[id^="{$p}-bento-card-"], .{$p}-bento-card { transition: border-color .3s, background .3s, transform .3s; }
[id^="{$p}-bento-card-"]:hover, .{$p}-bento-card:hover { border-color: rgba(200,255,0,.25) !important; background: rgba(255,255,255,.05) !important; transform: translateY(-3px); }
[id^="{$p}-price-card-"], .{$p}-price-card { position: relative; }
[id^="{$p}-price-card-"]:not(.{$p}-price-featured):hover, .{$p}-price-card:not(.{$p}-price-featured):hover { border-color: rgba(200,255,0,.4) !important; transform: translateY(-4px); }
[id^="{$p}-process-step-"], .{$p}-process-step { transition: all .3s; }
[id^="{$p}-testi-card-"], .{$p}-testi-card { transition: border-color .3s, background .3s, transform .3s; }
[id^="{$p}-testi-card-"]:hover, .{$p}-testi-card:hover { border-color: rgba(200,255,0,.25) !important; background: rgba(255,255,255,.05) !important; transform: translateY(-3px); }
.{$p}-bento-grid { grid-template-columns: repeat(12, minmax(0, 1fr)) !important; grid-auto-rows: 80px !important; }
.{$p}-step-visual-widget, .{$p}-testi-visual-widget, .{$p}-price-visual-widget, .{$p}-card-visual-widget { width: 100%; }
CSS;
	}

	// ═══════════════════════════════════════════════════════════
	// CARD EXTRACTION HELPERS — prefix-agnostic
	// ═══════════════════════════════════════════════════════════

	private function extract_cards( \DOMElement $node, \DOMXPath $xp ): array {
		$suffixes  = ['card','item','tile','box','step','tier','plan','testi','testimonial','post','member','person','feature'];
		$conds     = array_map( fn($s) => "contains(normalize-space(@class),'{$s}')", $suffixes );
		$q         = './/*[(' . implode(' or ',$conds) . ') and (./h2 or ./h3 or ./h4 or ./p)]';

		try { $nodes = $xp->query($q,$node); } catch (\Exception $e) { $nodes = null; }

		if (!$nodes || $nodes->length < 2) {
			try { $nodes = $xp->query('./div[.//h3 or .//h4] | ./article | ./li[.//h3]', $node); }
			catch (\Exception $e) { $nodes = null; }
		}

		if (!$nodes || $nodes->length < 2) return [];

		$cards = []; $seen = [];
		foreach ($nodes as $cn) {
			$oid = spl_object_id($cn);
			if (isset($seen[$oid])) continue;
			$seen[$oid] = true;

			// ── F-02b: Prevent double-walking (skip wrappers).
			$is_wrapper = false;
			foreach ($nodes as $other) {
				if ($other !== $cn) {
					$p = $other->parentNode;
					while ($p) {
						if ($p === $cn) { $is_wrapper = true; break; }
						$p = $p->parentNode;
					}
					if ($is_wrapper) break;
				}
			}
			if ($is_wrapper) continue;

			$title  = $this->get_heading($cn,$xp,['h3','h4','h2','strong']);
			$body   = $this->get_para($cn,$xp);
			$tag    = $this->get_text($xp,['.//*[contains(@class,"tag")]','.//*[contains(@class,"badge")]','.//*[contains(@class,"label")]'],$cn,80);
			$name_el = $xp->query('.//*[contains(@class,"name") or contains(@class,"author")]',$cn)->item(0);

			if ($title || strlen($body) > 15) {
				$visual_html = $this->extract_complex_visual($cn);

				$cards[] = [
					'title'       => $title,
					'body'        => $body,
					'tag'         => $tag,
					'author'      => $name_el ? trim($name_el->textContent) : '',
					'quote'       => $body,
					'visual_html' => $visual_html,
					'source_class'=> trim( (string) $cn->getAttribute( 'class' ) ),
					'source_id'   => trim( (string) $cn->getAttribute( 'id' ) ),
				];
			}
		}

		return $cards;
	}

	private function extract_process_steps_generic( \DOMElement $node, \DOMXPath $xp ): array {
		$candidate_wrappers = [ $node ];
		$wrapper_queries = [
			'.//*[contains(@class,"process-steps") or contains(@class,"steps-list") or contains(@class,"steps-wrap")]',
			'.//*[contains(@class,"process-grid") or contains(@class,"timeline") or contains(@class,"roadmap") or contains(@class,"workflow") or contains(@class,"journey") or contains(@class,"milestones") or contains(@class,"phases")]',
			'.//*[contains(@class,"steps") or contains(@class,"timeline") or contains(@class,"workflow")]',
		];

		foreach ( $wrapper_queries as $query ) {
			try {
				$matches = $xp->query( $query, $node );
			} catch ( \Exception $e ) {
				$matches = null;
			}
			if ( ! $matches ) {
				continue;
			}
			foreach ( $matches as $match ) {
				if ( $match instanceof \DOMElement ) {
					$candidate_wrappers[] = $match;
				}
			}
		}

		$best_steps = [];
		$best_score = 0;
		$seen_wrappers = [];

		foreach ( $candidate_wrappers as $wrapper ) {
			if ( ! $wrapper instanceof \DOMElement ) {
				continue;
			}
			$wrapper_id = spl_object_id( $wrapper );
			if ( isset( $seen_wrappers[ $wrapper_id ] ) ) {
				continue;
			}
			$seen_wrappers[ $wrapper_id ] = true;

			$steps = $this->extract_process_steps_from_wrapper( $wrapper, $xp );
			$score = count( $steps );
			if ( $score >= 2 && $score > $best_score ) {
				$best_steps = $steps;
				$best_score = $score;
			}
		}

		$grouped_steps = $this->extract_process_steps_from_repeated_descendants( $node, $xp );
		if ( count( $grouped_steps ) > $best_score ) {
			$best_steps = $grouped_steps;
			$best_score = count( $grouped_steps );
		}

		if ( ! empty( $best_steps ) ) {
			return $best_steps;
		}

		$cards = $this->extract_cards( $node, $xp );
		$steps = [];
		foreach ( $cards as $card ) {
			$title = trim( (string) ( $card['title'] ?? '' ) );
			$desc  = trim( (string) ( $card['body'] ?? '' ) );
			$visual_html = $card['visual_html'] ?? null;
			if ( '' === $title && '' === $desc && empty( $visual_html ) ) {
				continue;
			}
			$steps[] = [
				'title'       => $title,
				'desc'        => $desc,
				'visual_html' => $visual_html,
				'source_class'=> trim( (string) ( $card['source_class'] ?? '' ) ),
				'source_id'   => trim( (string) ( $card['source_id'] ?? '' ) ),
			];
		}

		return count( $steps ) >= 2 ? $steps : [];
	}

	private function extract_process_steps_from_repeated_descendants( \DOMElement $node, \DOMXPath $xp ): array {
		try {
			$descendants = $xp->query( './/div | .//article | .//li | .//section', $node );
		} catch ( \Exception $e ) {
			$descendants = null;
		}

		if ( ! $descendants ) {
			return [];
		}

		$groups = [];
		foreach ( $descendants as $descendant ) {
			if ( ! $descendant instanceof \DOMElement ) {
				continue;
			}
			if ( ! $this->is_process_step_candidate( $descendant, $xp ) ) {
				continue;
			}
			$parent = $descendant->parentNode;
			if ( ! $parent instanceof \DOMElement ) {
				continue;
			}

			$parent_id = spl_object_id( $parent );
			if ( ! isset( $groups[ $parent_id ] ) ) {
				$groups[ $parent_id ] = [];
			}
			$groups[ $parent_id ][] = $descendant;
		}

		$best_steps = [];
		foreach ( $groups as $group ) {
			if ( count( $group ) < 2 ) {
				continue;
			}

			$steps = [];
			foreach ( $group as $candidate ) {
				$steps[] = $this->build_process_step_payload( $candidate, $xp );
			}

			$steps = array_values( array_filter(
				$steps,
				fn( array $step ) => '' !== trim( (string) ( $step['title'] ?? '' ) ) || '' !== trim( (string) ( $step['desc'] ?? '' ) ) || ! empty( $step['visual_html'] )
			) );

			if ( count( $steps ) >= 2 && count( $steps ) > count( $best_steps ) ) {
				$best_steps = $steps;
			}
		}

		return $best_steps;
	}

	private function extract_process_steps_from_wrapper( \DOMElement $wrapper, \DOMXPath $xp ): array {
		$candidates = [];
		foreach ( $wrapper->childNodes as $child ) {
			if ( ! $child instanceof \DOMElement ) {
				continue;
			}
			$tag = strtolower( $child->tagName );
			if ( ! in_array( $tag, [ 'div', 'article', 'li', 'section' ], true ) ) {
				continue;
			}
			if ( $this->is_process_step_candidate( $child, $xp ) ) {
				$candidates[] = $child;
			}
		}

		if ( count( $candidates ) < 2 ) {
			return [];
		}

		$steps = [];
		foreach ( $candidates as $candidate ) {
			$steps[] = $this->build_process_step_payload( $candidate, $xp );
		}

		return array_values( array_filter(
			$steps,
			fn( array $step ) => '' !== trim( (string) ( $step['title'] ?? '' ) ) || '' !== trim( (string) ( $step['desc'] ?? '' ) ) || ! empty( $step['visual_html'] )
		) );
	}

	private function is_process_step_candidate( \DOMElement $node, \DOMXPath $xp ): bool {
		$title = $this->get_heading( $node, $xp, [ 'h3', 'h4', 'h2', 'h5', 'strong' ] );
		$desc  = $this->get_para( $node, $xp );
		$visual_html = $this->extract_complex_visual( $node );
		$text = trim( preg_replace( '/\s+/', ' ', (string) $node->textContent ) );
		$class = strtolower( trim( (string) $node->getAttribute( 'class' ) ) );

		if ( '' !== $title || '' !== $desc || ! empty( $visual_html ) ) {
			return true;
		}

		if ( preg_match( '/(?:step|phase|stage|milestone|timeline|roadmap|journey|workflow)/i', $class ) && strlen( $text ) >= 8 ) {
			return true;
		}

		if ( preg_match( '/^\s*(?:0?\d+|[ivxlcdm]+)[\.\):\-]?\s+/i', $text ) && strlen( $text ) <= 280 ) {
			return true;
		}

		return false;
	}

	private function build_process_step_payload( \DOMElement $node, \DOMXPath $xp ): array {
		return [
			'title'       => $this->get_heading( $node, $xp, [ 'h3', 'h4', 'h2', 'h5', 'strong' ] ),
			'desc'        => $this->get_para( $node, $xp ),
			'visual_html' => $this->extract_complex_visual( $node ),
			'source_class'=> trim( (string) $node->getAttribute( 'class' ) ),
			'source_id'   => trim( (string) $node->getAttribute( 'id' ) ),
		];
	}

	/**
	 * Detect if a node contains complex visual elements (SVG, Canvas, Video)
	 * that should be preserved in a hybrid container.
	 */
	private function extract_complex_visual(\DOMElement $node): ?string {
		$xp = new \DOMXPath($node->ownerDocument);
		// Broad detection: look for interactive/visual nodes generically.
		$q = './/svg | .//canvas | .//video | .//iframe | .//script | .//*[@data-animate or @data-animation or @data-counter or @data-aos or @onclick or @onmouseover or @onmouseenter or @onmouseleave] | .//*[contains(@class,"visual") or contains(@class,"animation") or contains(@class,"orb") or contains(@class,"terminal") or contains(@class,"pipeline") or contains(@class,"graph") or contains(@class,"code") or contains(@class,"counter") or contains(@class,"ticker") or contains(@class,"marquee") or contains(@class,"particle") or contains(@class,"lottie")]';
		$nodes = $xp->query($q, $node);

		if ($nodes && $nodes->length > 0) {
			// Find the largest visual container or the specific complex nodes.
			// For Bento cards, we often want the specific "visual" div.
			$visual_wrap = $xp->query('.//*[contains(@class,"visual") or contains(@class,"graphic") or contains(@class,"image-wrap")]', $node)->item(0);
			$target = $visual_wrap ?: $nodes->item(0);

			if ( $target instanceof \DOMElement ) {
				$cursor = $target;
				while ( $cursor->parentNode instanceof \DOMElement && $cursor->parentNode !== $node ) {
					$parent = $cursor->parentNode;
					$parent_class = strtolower( (string) $parent->getAttribute( 'class' ) );
					$has_visual_hint = (bool) preg_match( '/visual|graphic|animation|orb|terminal|pipeline|graph|code|mock|media/', $parent_class );
					$child_count = 0;
					foreach ( $parent->childNodes as $child ) {
						if ( $child instanceof \DOMElement ) {
							$child_count++;
						}
					}
					if ( ! $has_visual_hint && $child_count > 6 ) {
						break;
					}
					$cursor = $parent;
					if ( $has_visual_hint ) {
						$target = $cursor;
						break;
					}
				}
			}

			// Ensure we don't just grab a tiny icon. Only grab if it has significant structure.
			if ($target instanceof \DOMElement) {
				return $this->build_preserved_fragment_html( $target );
			}
		}

		return null;
	}

	/**
	 * Preserve a complex inner fragment with its relevant source CSS and keyframes.
	 *
	 * @param \DOMElement $target Fragment root.
	 * @return string
	 */
	private function build_preserved_fragment_html( \DOMElement $target ): string {
		$class_names = [];
		$id_names    = [];
		foreach ( explode( ' ', (string) $target->getAttribute( 'class' ) ) as $cls ) {
			$cls = trim( $cls );
			if ( $cls ) {
				$class_names[ $cls ] = true;
			}
		}
		$target_id = trim( (string) $target->getAttribute( 'id' ) );
		if ( $target_id ) {
			$id_names[ $target_id ] = true;
		}

		$xp = new \DOMXPath( $target->ownerDocument );
		$els = $xp->query( './/*[@class]', $target );
		if ( $els ) {
			foreach ( $els as $el ) {
				foreach ( explode( ' ', (string) $el->getAttribute( 'class' ) ) as $cls ) {
					$cls = trim( $cls );
					if ( $cls ) {
						$class_names[ $cls ] = true;
					}
				}
			}
		}
		$id_els = $xp->query( './/*[@id]', $target );
		if ( $id_els ) {
			foreach ( $id_els as $el ) {
				$id = trim( (string) $el->getAttribute( 'id' ) );
				if ( $id ) {
					$id_names[ $id ] = true;
				}
			}
		}

		$fragment_css = '';
		if ( ( ! empty( $class_names ) || ! empty( $id_names ) ) && $this->intel->raw_css ) {
			$class_pattern = implode( '|', array_map( 'preg_quote', array_keys( $class_names ) ) );
			$id_pattern    = ! empty( $id_names ) ? implode( '|', array_map( 'preg_quote', array_keys( $id_names ) ) ) : '';
			if ( preg_match_all( '/([^{}]+\{[^{}]+\})/s', $this->intel->raw_css, $rules ) ) {
				foreach ( $rules[1] as $rule ) {
					$matches_class = preg_match( '/\.(' . $class_pattern . ')([:\s\.\[>#]|$)/', $rule );
					$matches_id    = $id_pattern ? preg_match( '/#(' . $id_pattern . ')([:\s\.\[>#]|$)/', $rule ) : false;
					if ( $matches_class || $matches_id ) {
						$fragment_css .= trim( $rule ) . "\n";
					}
				}
			}
			foreach ( $this->css->get_keyframes() as $kf ) {
				if ( preg_match( '/@keyframes\s+([\w-]+)/', $kf, $nm ) && str_contains( $fragment_css, $nm[1] ) ) {
					$fragment_css .= $kf . "\n";
				}
			}
		}

		$html = $target->ownerDocument->saveHTML( $target );
		$scripts = '';
		$script_nodes = $xp->query( './/script', $target );
		if ( $script_nodes ) {
			foreach ( $script_nodes as $script_node ) {
				$scripts .= $target->ownerDocument->saveHTML( $script_node ) . "\n";
			}
		}
		if ( '' !== trim( $fragment_css ) ) {
			return "<style>\n{$fragment_css}</style>\n" . $html . ( $scripts ? "\n" . $scripts : '' );
		}

		return $html . ( $scripts ? "\n" . $scripts : '' );
	}

	private function extract_pricing_cards( \DOMElement $node, \DOMXPath $xp ): array {
		// ── F-03a: Per-card extraction.
		// Bug: old code ran preg_match on $node->textContent (the WHOLE section),
		// so every card got the same price (first match in section = $40 default).
		// Fix: find individual card containers, extract fields per-card.

		// Locate individual price card nodes.
		$card_queries = [
			'.//*[contains(@class,"price-card") or contains(@class,"pricing-card") or contains(@class,"plan-card")]',
			'.//*[contains(@class,"plan") and (.//button or .//a)]',
			'./div[.//button or .//a]',
		];
		$card_nodes = null;
		foreach ( $card_queries as $q ) {
			try { $r = $xp->query( $q, $node ); } catch ( \Exception $e ) { continue; }
			if ( $r && $r->length >= 2 ) { $card_nodes = $r; break; }
		}

		if ( ! $card_nodes ) {
			return [];
		}

		$cards = []; $seen = [];
		foreach ( $card_nodes as $cn ) {
			$oid = spl_object_id($cn);
			if ( isset($seen[$oid]) ) continue;
			$seen[$oid] = true;

			// Skip if this node contains another card node (it's a wrapper, not a card).
			$is_wrapper = false;
			foreach ( $card_nodes as $other ) {
				if ( $other !== $cn ) {
					$p = $other->parentNode;
					while ( $p ) {
						if ( $p === $cn ) { $is_wrapper = true; break; }
						$p = $p->parentNode;
					}
					if ( $is_wrapper ) break;
				}
			}
			if ( $is_wrapper ) continue;

			// Plan name.
			$plan_el = $xp->query('.//*[contains(@class,"plan") or contains(@class,"tier") or contains(@class,"price-plan")]', $cn)->item(0);
			$plan    = $plan_el ? trim(strip_tags($plan_el->textContent)) : '';

			// Price amount — look for a specific amount element first.
			$amt_el = $xp->query('.//*[contains(@class,"amount") or contains(@class,"price-num") or contains(@class,"price-value") or contains(@class,"price-amount")]', $cn)->item(0);
			$price  = '';
			if ( $amt_el ) {
				$price = trim(strip_tags($amt_el->textContent));
			} else {
				// Scan just this card's text (not the whole section).
				preg_match('/\$[\d,]+(?:\.\d{2})?|FREE|CUSTOM/i', $cn->textContent, $pm);
				$price = $pm[0] ?? '';
			}

			// Period / billing.
			$period_el = $xp->query('.//*[contains(@class,"period") or contains(@class,"billing") or contains(@class,"price-period")]', $cn)->item(0);
			$period    = '';
			if ( $period_el ) {
				$period = trim(strip_tags($period_el->textContent));
			} else {
				preg_match('/per\s+month|\/\s*month|per\s+year|annually|forever|billed\s+annually/i', $cn->textContent, $pp);
				$period = $pp[0] ?? '';
			}

			// Featured flag.
			$cls      = strtolower($cn->getAttribute('class'));
			$featured = str_contains($cls,'featured') || str_contains($cls,'popular');

			// Badge text.
			$badge_el = $xp->query('.//*[contains(@class,"badge") or contains(@class,"popular") or contains(@class,"recommended") or contains(@class,"most-popular")]', $cn)->item(0);
			$badge    = $badge_el ? trim(strip_tags($badge_el->textContent)) : '';

			// Features list items.
			$feats = [];
			$feat_list = $xp->query('.//ul/li | .//ol/li', $cn);
			if ( $feat_list ) {
				foreach ( $feat_list as $li ) {
					$t = trim($li->textContent);
					if ( $t && strlen($t) < 150 ) $feats[] = $t;
				}
			}
			$list_icon = $this->infer_list_icon_for_node( $cn );

			// CTA button text.
			$btn = $xp->query('.//button | .//a[contains(@class,"btn") or contains(@class,"cta")]', $cn)->item(0);
			$cta = $btn ? trim(strip_tags($btn->textContent)) : '';

			// Title/heading.
			$title = $this->get_heading( $cn, $xp, ['h3','h4','h2','h5'] );

			if ( $plan || $price || $title ) {
				$cards[] = [
					'title'       => $title ?: $plan,
					'plan'        => $plan  ?: $title,
					'price'       => $price,
					'period'      => $period,
					'featured'       => $featured,
					'badge'          => $badge,
					'features'       => $feats,
					'features_icon'  => $list_icon['icon'],
					'icon_source'    => $list_icon['source'],
					'has_list_pseudo'=> $list_icon['has_pseudo'],
					'cta'         => $cta,
					'body'        => $this->get_para( $cn, $xp ),
					'visual_html' => $this->extract_complex_visual($cn),
					'source_class'=> trim( (string) $cn->getAttribute( 'class' ) ),
					'source_id'   => trim( (string) $cn->getAttribute( 'id' ) ),
				];
			}
		}

		return $cards;
	}

	/**
	 * Extract testimonial cards with proper name, role, and quote fields.
	 * ── F-03b: The generic extract_cards() returns 'author' not 'name', and
	 * never extracts 'role' — causing template library testimonials() to use
	 * empty defaults for author names and roles.
	 */
	private function extract_testimonial_cards( \DOMElement $node, \DOMXPath $xp ): array {
		$card_queries = [
			'.//*[contains(@class,"testi-card") or contains(@class,"testimonial-card") or contains(@class,"review-card")]',
			'.//*[contains(@class,"testi") and not(contains(@class,"testi-grid")) and not(contains(@class,"testi-quote")) and (.//p or .//blockquote)]',
			'.//*[contains(@class,"review") and (.//p or .//blockquote)]',
		];

		$card_nodes = null;
		foreach ( $card_queries as $q ) {
			try { $r = $xp->query( $q, $node ); } catch ( \Exception $e ) { continue; }
			if ( $r && $r->length >= 1 ) { $card_nodes = $r; break; }
		}

		// No specific testimonial elements found. Fail loudly upstream instead of
		// silently reclassifying generic cards into testimonials.
		if ( ! $card_nodes ) {
			return [];
		}

		$cards = []; $seen = [];
		foreach ( $card_nodes as $cn ) {
			$oid = spl_object_id($cn);
			if ( isset($seen[$oid]) ) continue;
			$seen[$oid] = true;

			// Skip wrapper nodes that contain other card nodes.
			$is_wrapper = false;
			foreach ( $card_nodes as $other ) {
				if ( $other !== $cn ) {
					$p = $other->parentNode;
					while ( $p ) {
						if ( $p === $cn ) { $is_wrapper = true; break; }
						$p = $p->parentNode;
					}
					if ( $is_wrapper ) break;
				}
			}
			if ( $is_wrapper ) continue;

			// Quote text — prefer explicit quote element, then any paragraph.
			$q_el  = $xp->query('.//*[contains(@class,"quote")] | .//blockquote', $cn)->item(0)
				?? $xp->query('.//p', $cn)->item(0);
			$quote = $q_el ? $this->normalize_quote_text( trim( strip_tags( $q_el->textContent ) ) ) : '';

			// Author name.
			$name_el = $xp->query('.//*[contains(@class,"name") or contains(@class,"author")]', $cn)->item(0);
			$name = $name_el ? trim( preg_replace( '/\s+/', ' ', strip_tags( $name_el->textContent ) ) ) : '';

			// Role / title.
			$role_el = $xp->query('.//*[contains(@class,"role") or contains(@class,"title") or contains(@class,"position") or contains(@class,"company") or contains(@class,"testi-role")]', $cn)->item(0);
			$role = $role_el ? trim( preg_replace( '/\s+/', ' ', strip_tags( $role_el->textContent ) ) ) : '';

			if ( $name && $role && str_contains( $name, $role ) ) {
				$name = trim( str_replace( $role, '', $name ) );
			}

			if ( preg_match( '/^[A-Z]{1,3}\s+(.+)$/', $name, $match ) ) {
				$name = trim( $match[1] );
			}

			if ( $quote || $name ) {
				$cards[] = [
					'quote'       => $quote,
					'name'        => $name,
					'role'        => $role,
					'visual_html' => $this->extract_complex_visual($cn),
					'source_class'=> trim( (string) $cn->getAttribute( 'class' ) ),
					'source_id'   => trim( (string) $cn->getAttribute( 'id' ) ),
					// compat keys for generic consumers
					'title'  => $name,
					'body'   => $quote,
					'author' => $name,
				];
			}
		}

		return $cards;
	}

	/**
	 * Find leaf stat card nodes rather than every element whose class contains "stat".
	 *
	 * @param \DOMElement $node Stats section node.
	 * @param \DOMXPath   $xp DOM XPath helper.
	 * @return \DOMNodeList|array
	 */
	private function find_stat_nodes( \DOMElement $node, \DOMXPath $xp ) {
		$queries = [
			'.//*[contains(@class,"stat-cell") or contains(@class,"stat-card") or contains(@class,"metric-card") or contains(@class,"kpi-card")]',
			'.//*[contains(@class,"stat-item") or contains(@class,"metric-item") or contains(@class,"counter-item")]',
			'./div[.//*[contains(@class,"count") or contains(@class,"num") or contains(@class,"number") or @data-target]]',
			'.//li[.//*[contains(@class,"count") or contains(@class,"num") or contains(@class,"number") or @data-target]]',
		];

		foreach ( $queries as $query ) {
			try {
				$candidates = $xp->query( $query, $node );
			} catch ( \Exception $e ) {
				continue;
			}

			if ( ! $candidates || $candidates->length < 1 ) {
				continue;
			}

			$filtered = [];
			foreach ( $candidates as $candidate ) {
				if ( ! $candidate instanceof \DOMElement ) {
					continue;
				}

				$has_number = $this->node_contains_stat_number( $candidate, $xp );
				if ( ! $has_number ) {
					continue;
				}

				// Skip wrapper nodes that contain another candidate from the same result set.
				$is_wrapper = false;
				foreach ( $candidates as $other ) {
					if ( $other === $candidate || ! $other instanceof \DOMElement ) {
						continue;
					}

					$parent = $other->parentNode;
					while ( $parent ) {
						if ( $parent === $candidate ) {
							$is_wrapper = true;
							break 2;
						}
						$parent = $parent->parentNode;
					}
				}

				$filtered[] = $candidate;
			}

			if ( ! empty( $filtered ) ) {
				return $filtered;
			}
		}

		// Number-first fallback: find counter/number elements, then walk up to the
		// nearest repeated parent that likely represents an individual stat card.
		try {
			$number_nodes = $xp->query(
				'.//*[contains(@class,"count") or contains(@class,"num") or contains(@class,"number") or contains(@class,"value") or @data-target]',
				$node
			);
		} catch ( \Exception $e ) {
			$number_nodes = null;
		}

		if ( $number_nodes && $number_nodes->length > 0 ) {
			$resolved = [];
			$seen = [];

			foreach ( $number_nodes as $number_node ) {
				if ( ! $number_node instanceof \DOMElement ) {
					continue;
				}

				$candidate = $number_node;
				while ( $candidate instanceof \DOMElement && $candidate !== $node ) {
					$parent = $candidate->parentNode;
					if ( ! $parent instanceof \DOMElement ) {
						break;
					}

					$repeated_siblings = 0;
					foreach ( $parent->childNodes as $sibling ) {
						if ( ! $sibling instanceof \DOMElement ) {
							continue;
						}

						$has_number = $this->node_contains_stat_number( $sibling, $xp );
						if ( $has_number ) {
							$repeated_siblings++;
						}
					}

					if ( $repeated_siblings >= 2 ) {
						$key = spl_object_id( $candidate );
						if ( ! isset( $seen[ $key ] ) ) {
							$resolved[] = $candidate;
							$seen[ $key ] = true;
						}
						break;
					}

					$candidate = $parent;
				}
			}

			if ( ! empty( $resolved ) ) {
				return $resolved;
			}
		}

		// Final heuristic: repeated direct children whose text looks like a stat card.
		$direct_children = [];
		foreach ( $node->childNodes as $child ) {
			if ( ! $child instanceof \DOMElement ) {
				continue;
			}

			if ( $this->is_likely_stat_card( $child, $xp ) ) {
				$direct_children[] = $child;
			}
		}

		if ( count( $direct_children ) >= 2 ) {
			return $direct_children;
		}

		return [];
	}

	/**
	 * Determine whether a node contains a numeric stat value.
	 *
	 * @param \DOMElement $node Node to inspect.
	 * @param \DOMXPath   $xp DOM XPath helper.
	 * @return bool
	 */
	private function node_contains_stat_number( \DOMElement $node, \DOMXPath $xp ): bool {
		if ( $this->find_stat_number_element( $node, $xp ) instanceof \DOMElement ) {
			return true;
		}

		$text = trim( preg_replace( '/\s+/', ' ', strip_tags( $node->textContent ) ) );
		return (bool) preg_match( '/\d+(?:[.,]\d+)?\s*(?:[kmbtKMBT]|%|ms|s|\+)?/', $text );
	}

	/**
	 * Find the element that most likely represents the stat number cluster.
	 *
	 * @param \DOMElement $node Stat node.
	 * @param \DOMXPath   $xp DOM XPath helper.
	 * @return \DOMElement|null
	 */
	private function find_stat_number_element( \DOMElement $node, \DOMXPath $xp ): ?\DOMElement {
		$queries = [
			'.//*[contains(@class,"num") or contains(@class,"number") or contains(@class,"count") or contains(@class,"value") or @data-target]',
			'.//*[contains(@class,"amount") or contains(@class,"metric-num") or contains(@class,"kpi-num")]',
		];

		foreach ( $queries as $query ) {
			try {
				$match = $xp->query( $query, $node )->item( 0 );
			} catch ( \Exception $e ) {
				$match = null;
			}

			if ( $match instanceof \DOMElement ) {
				return $match;
			}
		}

		try {
			$descendants = $xp->query( './/*', $node );
		} catch ( \Exception $e ) {
			$descendants = null;
		}

		if ( $descendants ) {
			foreach ( $descendants as $descendant ) {
				if ( ! $descendant instanceof \DOMElement ) {
					continue;
				}

				$text = trim( preg_replace( '/\s+/', ' ', strip_tags( $descendant->textContent ) ) );
				if ( '' !== $text && strlen( $text ) <= 32 && preg_match( '/\d+(?:[.,]\d+)?\s*(?:[kmbtKMBT]|%|ms|s|\+)?/u', $text ) ) {
					return $descendant;
				}
			}
		}

		$self_text = trim( preg_replace( '/\s+/', ' ', strip_tags( $node->textContent ) ) );
		if ( '' !== $self_text && strlen( $self_text ) <= 32 && preg_match( '/\d+(?:[.,]\d+)?\s*(?:[kmbtKMBT]|%|ms|s|\+)?/u', $self_text ) ) {
			return $node;
		}

		return null;
	}

	/**
	 * Heuristic stat-card detector for custom wrappers.
	 *
	 * @param \DOMElement $node Candidate node.
	 * @param \DOMXPath   $xp DOM XPath helper.
	 * @return bool
	 */
	private function is_likely_stat_card( \DOMElement $node, \DOMXPath $xp ): bool {
		$text = trim( preg_replace( '/\s+/', ' ', strip_tags( $node->textContent ) ) );
		if ( '' === $text || strlen( $text ) > 180 ) {
			return false;
		}

		if ( ! preg_match( '/\d+(?:[.,]\d+)?\s*(?:[kmbtKMBT]|%|ms|s|\+)?/u', $text ) ) {
			return false;
		}

		$has_labelish_child = null !== $xp->query(
			'.//*[contains(@class,"label") or contains(@class,"caption") or contains(@class,"desc") or contains(@class,"copy") or contains(@class,"eyebrow")]',
			$node
		)->item( 0 );

		$child_count = 0;
		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof \DOMElement ) {
				$child_count++;
			}
		}

		return $has_labelish_child || $child_count <= 4;
	}

	/**
	 * Infer a stat label from the non-numeric text inside a stat block.
	 *
	 * @param \DOMElement $stat_node Stat container.
	 * @param \DOMXPath   $xp DOM XPath helper.
	 * @param string      $raw_num_text Extracted raw number text.
	 * @param string      $unit Extracted unit.
	 * @return string
	 */
	private function infer_stat_label( \DOMElement $stat_node, \DOMXPath $xp, string $raw_num_text, string $unit ): string {
		$candidates = [];
		$attribute_candidates = [
			$stat_node->getAttribute( 'data-label' ),
			$stat_node->getAttribute( 'aria-label' ),
			$stat_node->getAttribute( 'title' ),
		];
		foreach ( $attribute_candidates as $attribute_candidate ) {
			$attribute_candidate = trim( preg_replace( '/\s+/', ' ', (string) $attribute_candidate ) );
			if ( '' !== $attribute_candidate && ! preg_match( '/^[\d\W]+$/u', $attribute_candidate ) ) {
				$candidates[] = $attribute_candidate;
			}
		}

		$number_el = $xp->query( './/*[contains(@class,"count") or contains(@class,"num") or contains(@class,"number") or @data-target]', $stat_node )->item( 0 );
		if ( $number_el instanceof \DOMElement ) {
			foreach ( [ 'data-label', 'aria-label', 'title' ] as $attribute_name ) {
				$attribute_candidate = trim( preg_replace( '/\s+/', ' ', (string) $number_el->getAttribute( $attribute_name ) ) );
				if ( '' !== $attribute_candidate && ! preg_match( '/^[\d\W]+$/u', $attribute_candidate ) ) {
					$candidates[] = $attribute_candidate;
				}
			}

			$number_parent = $number_el->parentNode;
			if ( $number_parent instanceof \DOMElement ) {
				$sibling = $number_parent->nextSibling;
				while ( $sibling ) {
					if ( $sibling instanceof \DOMElement ) {
						$text = trim( preg_replace( '/\s+/', ' ', strip_tags( $sibling->textContent ) ) );
						if ( '' !== $text && ! preg_match( '/^[\d\W]+$/u', $text ) ) {
							$candidates[] = $text;
						}
					}
					$sibling = $sibling->nextSibling;
				}
			}
		}

		$child_nodes = $xp->query( './*', $stat_node );

		if ( $child_nodes ) {
			foreach ( $child_nodes as $child ) {
				$text = trim( preg_replace( '/\s+/', ' ', strip_tags( $child->textContent ) ) );
				if ( '' === $text ) {
					continue;
				}

				$normalized = trim( str_replace( [ $raw_num_text, $unit ], '', $text ) );
				$normalized = trim( preg_replace( '/\s+/', ' ', $normalized ) );

				if ( '' === $normalized || preg_match( '/^[\d\W]+$/u', $normalized ) ) {
					continue;
				}

				$candidates[] = $normalized;
			}
		}

		if ( empty( $candidates ) ) {
			$text = trim( preg_replace( '/\s+/', ' ', strip_tags( $stat_node->textContent ) ) );
			$text = trim( str_replace( [ $raw_num_text, $unit ], '', $text ) );
			if ( $text && ! preg_match( '/^[\d\W]+$/u', $text ) ) {
				$candidates[] = $text;
			}
		}

		$candidates = array_values( array_unique( array_filter( array_map(
			static function( $candidate ) use ( $raw_num_text, $unit ) {
				$candidate = trim( str_replace( [ $raw_num_text, $unit ], '', (string) $candidate ) );
				$candidate = trim( preg_replace( '/\s+/', ' ', $candidate ) );
				if ( '' === $candidate || preg_match( '/^[\d\W]+$/u', $candidate ) ) {
					return '';
				}
				return $candidate;
			},
			$candidates
		) ) ) );

		usort( $candidates, fn( $a, $b ) => strlen( $a ) <=> strlen( $b ) );

		return $candidates[0] ?? '';
	}

	/**
	 * Extract footer brand identity while allowing different footer shapes.
	 *
	 * @param \DOMElement $node Footer node.
	 * @param \DOMXPath   $xp DOM XPath helper.
	 * @return array
	 */
	private function extract_footer_brand_identity( \DOMElement $node, \DOMXPath $xp ): array {
		$identity = [];
		$candidates = $xp->query(
			'.//*[
				contains(@class,"footer-brand")
				or contains(@class,"brand")
				or contains(@class,"logo")
				or contains(@class,"bio")
			]',
			$node
		);

		$brand_el = null;
		if ( $candidates && $candidates->length > 0 ) {
			foreach ( $candidates as $candidate ) {
				$has_copy = $xp->query( './/p', $candidate )->length > 0;
				$has_mark = $xp->query( './/a | .//img | .//h1 | .//h2 | .//h3 | .//h4', $candidate )->length > 0;
				if ( $has_copy && $has_mark ) {
					$brand_el = $candidate;
					break;
				}
			}

			if ( ! $brand_el ) {
				$brand_el = $candidates->item( 0 );
			}
		}

		if ( ! $brand_el ) {
			return $identity;
		}

		$logo_el = $xp->query(
			'.//a[contains(@class,"logo") or contains(@class,"brand")] | .//a[1] | .//img[1] | .//h1[1] | .//h2[1] | .//h3[1]',
			$brand_el
		)->item( 0 );
		if ( $logo_el ) {
			$logo_html = $node->ownerDocument->saveHTML( $logo_el );
			$identity['brand_logo'] = preg_replace( '/\s+(hidden|mobile|lite|small|md:hidden)\s+/', ' ', $logo_html );

			if ( 'img' === strtolower( $logo_el->nodeName ) ) {
				$brand_name = trim( (string) $logo_el->getAttribute( 'alt' ) );
			} else {
				$brand_name = trim( preg_replace( '/\s+/', ' ', strip_tags( $logo_el->textContent ) ) );
			}

			if ( $brand_name ) {
				$identity['brand_name'] = $brand_name;
			}
		}

		$bio_el = $xp->query( './/p[1]', $brand_el )->item( 0 );
		if ( $bio_el ) {
			$identity['brand_desc'] = trim( preg_replace( '/\s+/', ' ', $bio_el->textContent ) );
		}

		return $identity;
	}

	private function extract_footer_columns_generic( \DOMElement $node, \DOMXPath $xp, string $brand_name_key = '' ): array {
		$candidate_columns = [];

		try {
			$explicit = $xp->query(
				'.//*[contains(@class,"footer-col")] | .//*[contains(@class,"footer-nav")] | .//*[contains(@class,"menu-col")] | .//*[contains(@class,"link-col")] | .//*[contains(@class,"nav-col")]',
				$node
			);
		} catch ( \Exception $e ) {
			$explicit = null;
		}
		if ( $explicit ) {
			foreach ( $explicit as $col ) {
				if ( $col instanceof \DOMElement ) {
					$candidate_columns[] = $col;
				}
			}
		}

		foreach ( $node->childNodes as $child ) {
			if ( $child instanceof \DOMElement && $this->is_footer_column_candidate( $child, $xp ) ) {
				$candidate_columns[] = $child;
			}
		}

		$grouped_columns = $this->extract_footer_columns_from_repeated_descendants( $node, $xp );
		$candidate_columns = array_merge( $candidate_columns, $grouped_columns );

		$seen_nodes = [];
		$seen_titles = [];
		$cols = [];

		foreach ( $candidate_columns as $col ) {
			if ( ! $col instanceof \DOMElement ) {
				continue;
			}
			$oid = spl_object_id( $col );
			if ( isset( $seen_nodes[ $oid ] ) ) {
				continue;
			}
			$seen_nodes[ $oid ] = true;

			$payload = $this->build_footer_column_payload( $col, $xp, $brand_name_key );
			if ( null === $payload ) {
				continue;
			}

			$title_key = strtolower( preg_replace( '/\s+/', ' ', (string) ( $payload['title'] ?? '' ) ) );
			if ( '' !== $title_key && isset( $seen_titles[ $title_key ] ) ) {
				continue;
			}
			if ( '' !== $title_key ) {
				$seen_titles[ $title_key ] = true;
			}
			$cols[] = $payload;
		}

		if ( count( $cols ) < 2 ) {
			$list_cols = $this->extract_footer_columns_from_list_groups( $node, $xp, $brand_name_key );
			foreach ( $list_cols as $payload ) {
				$title_key = strtolower( preg_replace( '/\s+/', ' ', (string) ( $payload['title'] ?? '' ) ) );
				if ( '' !== $title_key && isset( $seen_titles[ $title_key ] ) ) {
					continue;
				}
				if ( '' !== $title_key ) {
					$seen_titles[ $title_key ] = true;
				}
				$cols[] = $payload;
			}
		}

		if ( count( $cols ) < 2 ) {
			$anchor_cols = $this->extract_footer_columns_from_anchor_clusters( $node, $xp, $brand_name_key );
			foreach ( $anchor_cols as $payload ) {
				$title_key = strtolower( preg_replace( '/\s+/', ' ', (string) ( $payload['title'] ?? '' ) ) );
				if ( '' !== $title_key && isset( $seen_titles[ $title_key ] ) ) {
					continue;
				}
				if ( '' !== $title_key ) {
					$seen_titles[ $title_key ] = true;
				}
				$cols[] = $payload;
			}
		}

		if ( empty( $cols ) ) {
			$aggregate = $this->extract_footer_columns_from_all_links( $node, $xp, $brand_name_key );
			if ( ! empty( $aggregate ) ) {
				$cols = $aggregate;
			}
		}

		return $cols;
	}

	private function extract_footer_columns_from_repeated_descendants( \DOMElement $node, \DOMXPath $xp ): array {
		try {
			$descendants = $xp->query( './/div | .//section | .//article | .//aside | .//li', $node );
		} catch ( \Exception $e ) {
			$descendants = null;
		}

		if ( ! $descendants ) {
			return [];
		}

		$groups = [];
		foreach ( $descendants as $descendant ) {
			if ( ! $descendant instanceof \DOMElement ) {
				continue;
			}
			if ( ! $this->is_footer_column_candidate( $descendant, $xp ) ) {
				continue;
			}
			$parent = $descendant->parentNode;
			if ( ! $parent instanceof \DOMElement ) {
				continue;
			}
			$groups[ spl_object_id( $parent ) ][] = $descendant;
		}

		$best = [];
		foreach ( $groups as $group ) {
			if ( count( $group ) >= 2 && count( $group ) > count( $best ) ) {
				$best = $group;
			}
		}

		return $best;
	}

	private function is_footer_column_candidate( \DOMElement $node, \DOMXPath $xp ): bool {
		$title = $xp->query( './/h4|.//h5|.//h6|.//strong', $node )->item( 0 );
		$links = $xp->query( './/a', $node );
		$logoish = $xp->query( './/a[contains(@class,"logo")] | .//img', $node );
		$text = trim( preg_replace( '/\s+/', ' ', (string) $node->textContent ) );
		$class = strtolower( trim( (string) $node->getAttribute( 'class' ) ) );

		if ( $title && $links && $links->length >= 2 ) {
			return true;
		}

		if ( preg_match( '/(?:footer|menu|nav|links|column|col)/i', $class ) && $links && $links->length >= 2 ) {
			return true;
		}

		if ( $logoish && $links && $links->length <= 1 ) {
			return false;
		}

		return false !== strpos( $text, "\n" ) && $links && $links->length >= 3;
	}

	private function build_footer_column_payload( \DOMElement $col, \DOMXPath $xp, string $brand_name_key = '' ): ?array {
		$h     = $xp->query( './/h4|.//h5|.//h6|.//strong', $col )->item( 0 );
		$title = $h ? trim( preg_replace( '/\s+/', ' ', (string) $h->textContent ) ) : '';

		$links = $xp->query( './/a', $col );
		$link_list = [];
		if ( $links ) {
			foreach ( $links as $lnk ) {
				$t = trim( preg_replace( '/\s+/', ' ', (string) $lnk->textContent ) );
				if ( '' !== $t && strlen( $t ) < 50 ) {
					$link_list[] = $t;
				}
			}
		}

		if ( '' !== $brand_name_key ) {
			$link_list = array_values( array_filter(
				$link_list,
				fn( $link_text ) => strtolower( preg_replace( '/\s+/', ' ', (string) $link_text ) ) !== $brand_name_key
			) );
		}

		$link_list = array_values( array_unique( array_filter( $link_list ) ) );
		if ( '' === $title && count( $link_list ) <= 1 ) {
			return null;
		}
		if ( empty( $link_list ) && '' === $title ) {
			return null;
		}

		$link_list = array_slice( $link_list, 0, 6 );
		$list_icon = $this->infer_list_icon_for_node( $col );

		return [
			'title'           => $title,
			'links'           => $link_list,
			'list_icon'       => $list_icon['icon'],
			'icon_source'     => $list_icon['source'],
			'has_list_pseudo' => $list_icon['has_pseudo'],
		];
	}

	private function extract_footer_columns_from_list_groups( \DOMElement $node, \DOMXPath $xp, string $brand_name_key = '' ): array {
		try {
			$lists = $xp->query( './/ul | .//ol', $node );
		} catch ( \Exception $e ) {
			$lists = null;
		}

		if ( ! $lists ) {
			return [];
		}

		$cols = [];
		$seen = [];
		foreach ( $lists as $list ) {
			if ( ! $list instanceof \DOMElement ) {
				continue;
			}

			$link_list = [];
			$links = $xp->query( './/a', $list );
			if ( $links && $links->length > 0 ) {
				foreach ( $links as $lnk ) {
					$text = trim( preg_replace( '/\s+/', ' ', (string) $lnk->textContent ) );
					if ( '' !== $text && strlen( $text ) < 50 ) {
						$link_list[] = $text;
					}
				}
			} else {
				$items = $xp->query( './li', $list );
				if ( $items ) {
					foreach ( $items as $item ) {
						$text = trim( preg_replace( '/\s+/', ' ', (string) $item->textContent ) );
						if ( '' !== $text && strlen( $text ) < 50 ) {
							$link_list[] = $text;
						}
					}
				}
			}

			$link_list = array_values( array_unique( array_filter( $link_list ) ) );
			if ( '' !== $brand_name_key ) {
				$link_list = array_values( array_filter(
					$link_list,
					fn( $link_text ) => strtolower( preg_replace( '/\s+/', ' ', (string) $link_text ) ) !== $brand_name_key
				) );
			}
			if ( count( $link_list ) < 2 ) {
				continue;
			}

			$title = $this->infer_footer_list_title( $list, $node, $xp );
			$key = strtolower( ( '' !== $title ? $title . '|' : '' ) . implode( '|', $link_list ) );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;

			$list_icon = $this->infer_list_icon_for_node( $list );
			$cols[] = [
				'title'           => $title,
				'links'           => array_slice( $link_list, 0, 6 ),
				'list_icon'       => $list_icon['icon'],
				'icon_source'     => $list_icon['source'],
				'has_list_pseudo' => $list_icon['has_pseudo'],
			];
		}

		return $cols;
	}

	private function infer_footer_list_title( \DOMElement $list, \DOMElement $section_root, \DOMXPath $xp ): string {
		$current = $list;
		$depth = 0;
		while ( $current instanceof \DOMElement && $current !== $section_root && $depth < 4 ) {
			$heading = $xp->query( './h3 | ./h4 | ./h5 | ./h6 | ./strong', $current )->item( 0 );
			if ( $heading ) {
				return trim( preg_replace( '/\s+/', ' ', (string) $heading->textContent ) );
			}

			$sibling = $current->previousSibling;
			while ( $sibling ) {
				if ( $sibling instanceof \DOMElement && in_array( strtolower( $sibling->tagName ), [ 'h3', 'h4', 'h5', 'h6', 'strong', 'p' ], true ) ) {
					$text = trim( preg_replace( '/\s+/', ' ', (string) $sibling->textContent ) );
					if ( '' !== $text && strlen( $text ) <= 60 ) {
						return $text;
					}
				}
				$sibling = $sibling->previousSibling;
			}

			$parent = $current->parentNode;
			$current = $parent instanceof \DOMElement ? $parent : null;
			$depth++;
		}

		return '';
	}

	private function extract_footer_columns_from_anchor_clusters( \DOMElement $node, \DOMXPath $xp, string $brand_name_key = '' ): array {
		try {
			$anchors = $xp->query( './/a', $node );
		} catch ( \Exception $e ) {
			$anchors = null;
		}

		if ( ! $anchors || $anchors->length < 4 ) {
			return [];
		}

		$groups = [];
		foreach ( $anchors as $anchor ) {
			if ( ! $anchor instanceof \DOMElement ) {
				continue;
			}

			$text = trim( preg_replace( '/\s+/', ' ', (string) $anchor->textContent ) );
			if ( '' === $text || strlen( $text ) >= 50 ) {
				continue;
			}
			if ( '' !== $brand_name_key && strtolower( $text ) === $brand_name_key ) {
				continue;
			}

			$cluster_root = $this->find_footer_anchor_cluster_root( $anchor, $node, $xp );
			if ( ! $cluster_root ) {
				continue;
			}

			$groups[ spl_object_id( $cluster_root ) ] = $cluster_root;
		}

		$cols = [];
		foreach ( $groups as $cluster_root ) {
			$payload = $this->build_footer_column_payload( $cluster_root, $xp, $brand_name_key );
			if ( null === $payload ) {
				continue;
			}
			if ( count( (array) ( $payload['links'] ?? [] ) ) < 2 ) {
				continue;
			}
			$cols[] = $payload;
		}

		return $cols;
	}

	private function find_footer_anchor_cluster_root( \DOMElement $anchor, \DOMElement $section_root, \DOMXPath $xp ): ?\DOMElement {
		$current = $anchor->parentNode;
		$depth = 0;

		while ( $current instanceof \DOMElement && $current !== $section_root && $depth < 5 ) {
			$links = $xp->query( './/a', $current );
			$link_count = $links ? (int) $links->length : 0;
			$logoish = $xp->query( './/a[contains(@class,"logo")] | .//img', $current );
			$has_heading = (bool) $xp->query( './/h3|.//h4|.//h5|.//h6|.//strong', $current )->item( 0 );

			if ( $link_count >= 2 && ! ( $logoish && $logoish->length > 0 && $link_count <= 1 ) ) {
				if ( $has_heading || $link_count >= 3 ) {
					return $current;
				}
			}

			$parent = $current->parentNode;
			$current = $parent instanceof \DOMElement ? $parent : null;
			$depth++;
		}

		return null;
	}

	private function extract_footer_columns_from_all_links( \DOMElement $node, \DOMXPath $xp, string $brand_name_key = '' ): array {
		try {
			$links = $xp->query( './/a', $node );
		} catch ( \Exception $e ) {
			$links = null;
		}

		if ( ! $links || $links->length < 2 ) {
			return [];
		}

		$link_list = [];
		foreach ( $links as $link ) {
			if ( ! $link instanceof \DOMElement ) {
				continue;
			}

			$text = trim( preg_replace( '/\s+/', ' ', (string) $link->textContent ) );
			if ( '' === $text || strlen( $text ) >= 50 ) {
				continue;
			}
			if ( '' !== $brand_name_key && strtolower( $text ) === $brand_name_key ) {
				continue;
			}

			$class = strtolower( trim( (string) $link->getAttribute( 'class' ) ) );
			if ( preg_match( '/logo|brand/', $class ) ) {
				continue;
			}

			$link_list[] = $text;
		}

		$link_list = array_values( array_unique( array_filter( $link_list ) ) );
		if ( count( $link_list ) < 2 ) {
			return [];
		}

		$list_icon = $this->infer_list_icon_for_node( $node );
		return [[
			'title'           => '',
			'links'           => array_slice( $link_list, 0, 8 ),
			'list_icon'       => $list_icon['icon'],
			'icon_source'     => $list_icon['source'],
			'has_list_pseudo' => $list_icon['has_pseudo'],
		]];
	}

	/**
	 * Detect repeated footer links across multiple extracted columns.
	 *
	 * @param array $cols Extracted footer columns.
	 * @return array<string,string>|null
	 */
	private function find_duplicate_footer_links_in_payload( array $cols ): ?array {
		$seen = [];

		foreach ( $cols as $col ) {
			$title = trim( (string) ( $col['title'] ?? '' ) );
			$title = '' !== $title ? $title : 'untitled';

			foreach ( $col['links'] ?? [] as $link ) {
				$link_text = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) $link ) ) );
				if ( '' === $link_text ) {
					continue;
				}

				$key = strtolower( $link_text );
				if ( isset( $seen[ $key ] ) && $seen[ $key ] !== $title ) {
					return [
						'link'   => $link_text,
						'first'  => $seen[ $key ],
						'second' => $title,
					];
				}

				$seen[ $key ] = $title;
			}
		}

		return null;
	}

	/**
	 * Resolve the best available brand label for decorative CSS copy.
	 *
	 * @return string
	 */
	private function resolve_brand_label(): string {
		$footer_brand = trim( (string) ( $this->section_payloads['footer']['brand_name'] ?? '' ) );
		if ( '' !== $footer_brand ) {
			return $footer_brand;
		}

		foreach ( $this->elements as $element ) {
			$html = (string) ( $element['settings']['html'] ?? '' );
			if ( '' === $html ) {
				continue;
			}

			if ( preg_match( '/class="' . preg_quote( $this->prefix, '/' ) . '-nav-logo">([^<]+)</', $html, $match ) ) {
				$nav_brand = trim( wp_strip_all_tags( html_entity_decode( $match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
				if ( '' !== $nav_brand ) {
					return $nav_brand;
				}
			}
		}

		return '';
	}

	/**
	 * Infer a list icon from source pseudo-content instead of defaulting blindly.
	 *
	 * @param \DOMElement $node Source list/card/column node.
	 * @return array{icon:string,source:string,has_pseudo:bool}
	 */
	private function infer_list_icon_for_node( \DOMElement $node ): array {
		if ( empty( $this->intel->raw_css ) ) {
			return [ 'icon' => '', 'source' => '', 'has_pseudo' => false ];
		}

		$selectors = [];
		$current = $node;
		$depth = 0;
		while ( $current instanceof \DOMElement && $depth < 4 ) {
			$id = trim( (string) $current->getAttribute( 'id' ) );
			if ( '' !== $id && ! str_starts_with( $id, $this->prefix . '-' ) ) {
				$selectors[] = '#' . $id;
			}

			foreach ( preg_split( '/\s+/', trim( (string) $current->getAttribute( 'class' ) ) ) as $class_name ) {
				$class_name = trim( (string) $class_name );
				if ( $this->should_bridge_source_class( $class_name ) ) {
					$selectors[] = '.' . $class_name;
				}
			}

			$parent = $current->parentNode;
			$current = $parent instanceof \DOMElement ? $parent : null;
			$depth++;
		}

		$selectors = array_values( array_unique( array_filter( $selectors ) ) );
		if ( empty( $selectors ) ) {
			return [ 'icon' => '', 'source' => '', 'has_pseudo' => false ];
		}

		$css = (string) $this->intel->raw_css;
		$has_pseudo = false;
		foreach ( $selectors as $selector ) {
			$escaped = preg_quote( $selector, '/' );
			$patterns = [
				'/' . $escaped . '(?:[^\{;}]*)li::(?:before|after)\s*\{([^}]*)\}/is',
				'/' . $escaped . '(?:[^\{;}]*)a::(?:before|after)\s*\{([^}]*)\}/is',
				'/' . $escaped . '(?:[^\{;}]*)\.elementor-icon-list-item::(?:before|after)\s*\{([^}]*)\}/is',
				'/' . $escaped . '(?:[^\{;}]*)::(?:before|after)\s*\{([^}]*)\}/is',
			];

			foreach ( $patterns as $pattern ) {
				if ( ! preg_match_all( $pattern, $css, $matches, PREG_SET_ORDER ) ) {
					continue;
				}
				foreach ( $matches as $match ) {
					$rule_body = (string) ( $match[1] ?? '' );
					$content = $this->extract_css_content_literal( $rule_body );
					if ( '' === $content ) {
						continue;
					}
					$has_pseudo = true;
					$icon = $this->map_css_rule_to_elementor_icon( $rule_body, $content );
					if ( '' !== $icon ) {
						return [
							'icon'       => $icon,
							'source'     => $content,
							'has_pseudo' => true,
						];
					}
				}
			}
		}

		return [ 'icon' => '', 'source' => '', 'has_pseudo' => $has_pseudo ];
	}

	private function map_css_rule_to_elementor_icon( string $rule_body, string $content ): string {
		$font_family = '';
		if ( preg_match( '/font-family\s*:\s*([^;]+);?/i', $rule_body, $match ) ) {
			$font_family = strtolower( trim( preg_replace( '/["\']/', '', (string) $match[1] ) ) );
		}

		$content_token = '';
		if ( preg_match( '/content\s*:\s*([^;]+);?/i', $rule_body, $match ) ) {
			$content_token = strtolower( trim( (string) $match[1] ) );
		}

		if ( '' !== $font_family && (
			str_contains( $font_family, 'font awesome' ) ||
			str_contains( $font_family, 'fontawesome' ) ||
			str_contains( $font_family, 'bootstrap-icons' ) ||
			str_contains( $font_family, 'elementoricons' )
		) ) {
			$icon = $this->map_icon_font_content_to_elementor_icon( $content_token );
			if ( '' !== $icon ) {
				return $icon;
			}
		}

		return $this->map_css_content_to_elementor_icon( $content );
	}

	private function map_icon_font_content_to_elementor_icon( string $content_token ): string {
		$normalized = strtolower( trim( preg_replace( '/["\'\s]/', '', $content_token ) ) );
		$maps = [
			'check'        => [ '\\f00c', '\\2713', '\\2714' ],
			'arrow-right'  => [ '\\f061', '\\2192', '\\27a4' ],
			'angle-right'  => [ '\\f105', '\\203a', '\\00bb', '\\f054', '\\f0da' ],
			'plus'         => [ '\\f067', '\\002b' ],
			'minus'        => [ '\\f068', '\\2212', '\\2013', '\\2014' ],
			'circle'       => [ '\\f111', '\\2022', '\\25cf', '\\25cb' ],
			'star'         => [ '\\f005', '\\2605', '\\2606' ],
			'times'        => [ '\\f00d', '\\2715', '\\00d7' ],
		];

		foreach ( $maps as $icon => $codes ) {
			foreach ( $codes as $code ) {
				if ( $normalized === strtolower( $code ) ) {
					return $icon;
				}
			}
		}

		return '';
	}

	private function extract_css_content_literal( string $rule_body ): string {
		if ( ! preg_match( '/content\s*:\s*([^;]+);?/i', $rule_body, $match ) ) {
			return '';
		}

		$content = trim( (string) $match[1] );
		if ( in_array( strtolower( $content ), [ 'none', 'normal', 'initial', 'inherit', 'unset' ], true ) ) {
			return '';
		}

		$content = preg_replace( '/^["\']|["\']$/', '', $content );
		$content = preg_replace_callback(
			'/\\\\([0-9a-fA-F]{1,6})\s?/',
			static function( array $m ): string {
				$codepoint = hexdec( (string) $m[1] );
				if ( $codepoint <= 0 ) {
					return '';
				}
				return html_entity_decode( '&#' . $codepoint . ';', ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			},
			(string) $content
		);
		$content = str_replace( [ '\\"', "\\'" ], [ '"', "'" ], (string) $content );
		$content = trim( wp_strip_all_tags( html_entity_decode( (string) $content, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );

		return $content;
	}

	private function map_css_content_to_elementor_icon( string $content ): string {
		$content = trim( $content );
		if ( '' === $content ) {
			return '';
		}

		$normalized = strtolower( preg_replace( '/\s+/', '', $content ) );
		$maps = [
			'check'       => [ '✓', '✔', '✅', '☑', 'check', 'checkmark' ],
			'arrow-right' => [ '→', '➜', '➝', '⟶', '⟹', 'arrowright' ],
			'angle-right' => [ '›', '»', 'angle', 'chevron', 'greaterthan' ],
			'plus'        => [ '+', 'plus' ],
			'minus'       => [ '-', '−', '–', '—', 'minus' ],
			'circle'      => [ '•', '●', '○', '◦', 'dot', 'bullet', 'circle' ],
			'diamond'     => [ '◆', '◇', 'diamond' ],
			'star'        => [ '★', '☆', 'star' ],
		];

		foreach ( $maps as $icon => $needles ) {
			foreach ( $needles as $needle ) {
				$needle_normalized = strtolower( preg_replace( '/\s+/', '', (string) $needle ) );
				if ( '' !== $needle_normalized && str_contains( $normalized, $needle_normalized ) ) {
					return $icon;
				}
			}
		}

		return '';
	}

	/**
	 * Reapply extracted bento/mosaic spans through companion CSS.
	 *
	 * @return string
	 */
	private function build_bento_span_css(): string {
		$p = $this->prefix;
		$payload = $this->section_payloads['bento'] ?? $this->section_payloads['features'] ?? [];
		$spans = $payload['bento_spans'] ?? [];
		$cards = $payload['cards'] ?? [];
		if ( empty( $spans ) || ! is_array( $spans ) || empty( $cards ) || ! is_array( $cards ) ) {
			return '';
		}

		$css = '';
		$spans = array_slice( array_values( $spans ), 0, count( $cards ) );
		foreach ( $spans as $index => $span ) {
			$letter = chr( 97 + $index );
			$col = max( 1, (int) ( $span['col'] ?? 1 ) );
			$row = max( 1, (int) ( $span['row'] ?? 1 ) );
			$css .= ".{$p}-bc-{$letter}, .{$p}-bento-card-{$letter} { grid-column: span {$col}; grid-row: span {$row}; }\n";
		}

		return $css;
	}

	/**
	 * Regex fallback for simple repeated stats markup when DOM heuristics miss.
	 *
	 * @param \DOMElement $node Stats section node.
	 * @return array
	 */
	private function extract_simple_stats_from_html( \DOMElement $node ): array {
		$html = (string) $node->ownerDocument->saveHTML( $node );
		if ( '' === trim( $html ) ) {
			return [];
		}

		$stats = [];
		$pattern = '/data-target="([^"]+)"[^>]*>([^<]*)<\/span>\s*(?:<span[^>]*class="[^"]*unit[^"]*"[^>]*>([^<]*)<\/span>)?.*?<div[^>]*class="[^"]*(?:label|caption|desc|copy)[^"]*"[^>]*>(.*?)<\/div>/si';
		if ( preg_match_all( $pattern, $html, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				$num   = trim( preg_replace( '/[^\d.]/', '', (string) ( $match[1] ?? '' ) ) );
				$unit  = trim( wp_strip_all_tags( (string) ( $match[3] ?? '' ) ) );
				$label = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( (string) ( $match[4] ?? '' ) ) ) );
				if ( '' === $num ) {
					continue;
				}
				$stats[] = [
					'num'   => $num,
					'unit'  => mb_substr( $unit, 0, 12 ),
					'label' => mb_substr( $label, 0, 40 ),
					'dec'   => str_contains( $num, '.' ) ? strlen( substr( $num, strpos( $num, '.' ) + 1 ) ) : 0,
					'source_class' => '',
				];
			}
		}

		return $stats;
	}

	/**
	 * Normalize CTA copy so repeated arrows and whitespace do not leak through.
	 *
	 * @param string $text Raw CTA text.
	 * @return string
	 */
	private function normalize_cta_text( string $text ): string {
		$text = trim( preg_replace( '/\s+/', ' ', html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		$text = preg_replace( '/(?:\s*[→↗]+\s*){2,}$/u', ' →', $text );
		$text = preg_replace( '/\s{2,}/', ' ', $text );
		return trim( $text );
	}

	/**
	 * Render nav CTA markup only when extraction found a CTA.
	 *
	 * @param string $cta CTA label.
	 * @return string
	 */
	private function render_nav_cta_html( string $cta ): string {
		$cta = trim( $cta );
		if ( '' === $cta ) {
			return '';
		}

		return '<button class="' . esc_attr( $this->prefix ) . '-nav-cta">' . wp_kses_post( $cta ) . '</button>';
	}

	/**
	 * Remove duplicate wrapping quotes and normalize testimonial text.
	 *
	 * @param string $text Raw quote text.
	 * @return string
	 */
	private function normalize_quote_text( string $text ): string {
		$text = trim( html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		$text = preg_replace( '/\s+/', ' ', $text );
		$text = trim( $text, "\"'“”‘’ " );
		return $text;
	}

	/**
	 * Detect when a section description is actually a pulled quote.
	 *
	 * @param string $text Description candidate.
	 * @return bool
	 */
	private function looks_like_quote( string $text ): bool {
		$text = trim( html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
		if ( '' === $text ) {
			return false;
		}

		if ( preg_match( '/^[\"\']|[\"\']$/', $text ) ) {
			return true;
		}

		return str_word_count( $text ) > 14 && str_contains( strtolower( $text ), 'first platform' );
	}

	// ═══════════════════════════════════════════════════════════
	// DOM HELPERS
	// ═══════════════════════════════════════════════════════════

	/**
	 * Extract grid-column and grid-row spans from resolved CSS for a list of cards.
	 * Returns an array of [col_span, row_span] pairs.
	 */
	private function get_bento_spans( array $cards, \DOMElement $node, \DOMXPath $xp ): array {
		$spans = [];
		// Architecture article §20: "Bento parity requires 12-column mapping".
		$card_nodes = $xp->query('.//*[contains(@class,"card") or contains(@class,"item") or contains(@class,"bento-card")]', $node);
		
		if ( $card_nodes && $card_nodes->length > 0 ) {
			foreach ( $card_nodes as $cn ) {
				$col = 4; $row = 4; // defaults
				$cls = $cn->getAttribute('class');
				foreach ( explode(' ', $cls) as $c ) {
					$c = trim($c); if (!$c) continue;
					if ( preg_match('/\.'.preg_quote($c).'[^{]*\{[^}]*grid-column\s*:\s*span\s*(\d+)/s', $this->intel->raw_css, $m) ) $col = (int)$m[1];
					if ( preg_match('/\.'.preg_quote($c).'[^{]*\{[^}]*grid-row\s*:\s*span\s*(\d+)/s', $this->intel->raw_css, $m) )    $row = (int)$m[1];
				}
				$spans[] = [ 'col' => $col, 'row' => $row ];
			}
		}

		return $spans;

		return $spans;
	}

	private function get_bento_spans_strict( array $cards, \DOMElement $node, \DOMXPath $xp ): array {
		$spans = [];
		if ( empty( $cards ) ) {
			return $spans;
		}

		$card_nodes = null;
		$grid_queries = [
			'.//*[contains(@class,"bento") and not(contains(@class,"bento-card"))][1]',
			'.//*[contains(@class,"features-grid")][1]',
			'.//*[contains(@class,"grid")][1]',
		];

		foreach ( $grid_queries as $grid_query ) {
			$grid_wrap = $xp->query( $grid_query, $node )->item(0);
			if ( $grid_wrap instanceof \DOMElement ) {
				$card_nodes = $xp->query('./div | ./article | ./section', $grid_wrap);
				if ( $card_nodes && $card_nodes->length > 0 ) {
					break;
				}
			}
		}

		if ( ! $card_nodes || $card_nodes->length < 1 ) {
			$card_nodes = $xp->query('.//*[contains(@class,"bento-card") or contains(@class,"card-a") or contains(@class,"card-b") or contains(@class,"card-c") or contains(@class,"card-d") or contains(@class,"card-e") or contains(@class,"card-f")]', $node);
		}

		if ( $card_nodes && $card_nodes->length > 0 ) {
			$index = 0;
			$max_cards = count( $cards );
			foreach ( $card_nodes as $cn ) {
				if ( $index >= $max_cards ) {
					break;
				}

				$col = 4;
				$row = 4;
				$cls = $cn->getAttribute( 'class' );
				foreach ( explode( ' ', $cls ) as $c ) {
					$c = trim( $c );
					if ( ! $c ) {
						continue;
					}
					if ( preg_match( '/\.' . preg_quote( $c ) . '[^{]*\{[^}]*grid-column\s*:\s*span\s*(\d+)/s', $this->intel->raw_css, $m ) ) {
						$col = (int) $m[1];
					}
					if ( preg_match( '/\.' . preg_quote( $c ) . '[^{]*\{[^}]*grid-row\s*:\s*span\s*(\d+)/s', $this->intel->raw_css, $m ) ) {
						$row = (int) $m[1];
					}
				}

				$spans[] = [ 'col' => $col, 'row' => $row ];
				$index++;
			}
		}

		return $spans;
	}

	private function bw( int $t, int $r, int $b, int $l ): array {
		return [ 'unit' => 'px', 'top' => (string)$t, 'right' => (string)$r, 'bottom' => (string)$b, 'left' => (string)$l, 'isLinked' => false ];
	}

	private function get_heading( \DOMElement $n, \DOMXPath $xp, array $tags ): string {
		foreach ($tags as $tag) {
			$r = $xp->query(".//{$tag}", $n);
			if ( ! $r || $r->length === 0 ) continue;
			$el   = $r->item(0);
			$text = $this->extract_inline_markup_text( $el );
			if ( strlen( wp_strip_all_tags( $text ) ) < 3 || strlen( wp_strip_all_tags( $text ) ) > 400 ) continue;
			return $text;
		}
		return '';
	}

	private function get_para( \DOMElement $n, \DOMXPath $xp ): string {
		$r = $xp->query('.//p[not(ancestor::nav) and not(contains(@class,"tag")) and not(contains(@class,"label"))]',$n);
		if ($r) foreach ($r as $p) { $t = trim($p->textContent); if (strlen($t)>20 && strlen($t)<1000) return $t; }
		return '';
	}

	private function get_text( \DOMXPath $xp, array $queries, \DOMElement $n, int $max_len ): string {
		foreach ($queries as $q) {
			try { $r = $xp->query($q,$n); } catch (\Exception $e) { continue; }
			if ($r && $r->length > 0) {
				$t = $this->extract_inline_markup_text( $r->item(0) );
				if ( $t && strlen( wp_strip_all_tags( $t ) ) < $max_len ) return $t;
			}
		}
		return '';
	}

	private function get_btn( \DOMElement $n, \DOMXPath $xp, int $idx ): string {
		$r = $xp->query('.//a[contains(@class,"btn") or contains(@class,"cta")] | .//button[not(ancestor::nav)]',$n);
		return ($r && $r->length > $idx) ? trim( wp_strip_all_tags( $this->extract_inline_markup_text( $r->item($idx) ) ) ) : '';
	}

	private function extract_inline_markup_text( \DOMElement $element ): string {
		$text = trim( preg_replace( '/\s+/', ' ', html_entity_decode( $element->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) );
		if ( '' === $text ) {
			return '';
		}

		$has_inline_markup = false;
		foreach ( $element->childNodes as $child ) {
			if ( ! $child instanceof \DOMElement ) {
				continue;
			}
			$name = strtolower( $child->nodeName );
			if ( in_array( $name, [ 'span', 'em', 'strong', 'i', 'b', 'small', 'mark', 'sup', 'sub', 'br' ], true ) ) {
				$has_inline_markup = true;
				break;
			}
		}

		if ( ! $has_inline_markup ) {
			return $text;
		}

		$inner = '';
		foreach ( $element->childNodes as $child ) {
			$inner .= $element->ownerDocument->saveHTML( $child );
		}

		$allowed = [
			'span'   => [ 'class' => true, 'id' => true ],
			'em'     => [],
			'strong' => [],
			'i'      => [],
			'b'      => [],
			'small'  => [],
			'mark'   => [],
			'sup'    => [],
			'sub'    => [],
			'br'     => [],
		];
		$inner = trim( wp_kses( $inner, $allowed ) );

		return '' !== $inner ? $inner : $text;
	}

	// ═══════════════════════════════════════════════════════════
	// ELEMENTOR ELEMENT FACTORIES
	// ═══════════════════════════════════════════════════════════

	private function con( string $dir, string $cls, string $id, array $children, array $extra = [], bool $inner = false ): array {
		$s = array_merge(['_css_classes'=>$cls,'flex_direction'=>'column'===$dir?'column':'row','flex_wrap'=>'wrap','gap'=>['unit'=>'px','size'=>24,'column'=>24,'row'=>24]], $extra);
		if ($id) $s['_element_id'] = $id;
		return ['id'=>$this->genid(),'elType'=>'container','isInner'=>$inner,'settings'=>$s,'elements'=>array_values(array_filter($children))];
	}

	private function grid_con( array $children, string $cols, string $cls ): array {
		return ['id'=>$this->genid(),'elType'=>'container','isInner'=>true,'settings'=>['_css_classes'=>$cls,'container_type'=>'grid','grid_columns_fr'=>$cols,'gap'=>['unit'=>'px','size'=>16,'column'=>16,'row'=>16]],'elements'=>array_values(array_filter($children))];
	}

	private function w( string $type, array $settings ): array {
		return ['id'=>$this->genid(),'elType'=>'widget','widgetType'=>$type,'isInner'=>false,'settings'=>$settings,'elements'=>[]];
	}

	private function heading_w( string $text, string $tag, string $cls, array $typo = [] ): array {
		return $this->w('heading', array_merge(['_css_classes'=>$cls,'title'=>wp_kses_post($text),'header_size'=>$tag,'align'=>'left','typography_typography'=>'custom','typography_font_family'=>$this->f_display,'typography_font_weight'=>'700','typography_font_size'=>['unit'=>'px','size'=>32],'title_color'=>$this->c_text], $typo));
	}

	private function text_w( string $html, string $cls ): array {
		return $this->w('text-editor',['_css_classes'=>$cls,'editor'=>$html]);
	}

	private function icon_list_w( array $items, string $cls, string $icon_value ): ?array {
		$items = array_values( array_filter( array_map( fn( $item ) => trim( (string) $item ), $items ) ) );
		if ( empty( $items ) ) {
			return null;
		}

		$list_items = array_map(
			function( string $item ) use ( $icon_value ) {
				$entry = [
					'text' => $item,
					'link' => [ 'url' => '#', 'is_external' => false, 'nofollow' => false ],
				];
				if ( '' !== $icon_value ) {
					$entry['selected_icon'] = [ 'value' => $icon_value, 'library' => 'fas' ];
				}
				return $entry;
			},
			$items
		);

		return $this->w( 'icon-list', [
			'_css_classes'          => $cls,
			'icon_list'             => $list_items,
			'view'                  => 'traditional',
			'icon_align'            => 'left',
			'icon_indent'           => [ 'unit' => 'px', 'size' => 10 ],
			'text_indent'           => [ 'unit' => 'px', 'size' => 0 ],
			'divider'               => 'none',
			'link_click'            => 'none',
			'icon_color'            => $this->c_accent,
			'text_color'            => $this->c_text,
			'typography_typography' => 'custom',
			'typography_font_family'=> $this->f_body,
			'typography_font_weight'=> '400',
			'typography_font_size'  => [ 'unit' => 'px', 'size' => 14 ],
		] );
	}

	private function btn_w( string $text, string $url, string $cls, string $bg, string $color ): array {
		return $this->w('button',['_css_classes'=>$cls,'text'=>$text,'link'=>['url'=>$url?:'#','is_external'=>false,'nofollow'=>false],'background_color'=>$bg,'button_text_color'=>$color,'typography_typography'=>'custom','typography_font_family'=>$this->f_mono,'typography_font_weight'=>'700','typography_font_size'=>['unit'=>'px','size'=>12],'typography_letter_spacing'=>['unit'=>'em','size'=>0.1],'border_radius'=>['unit'=>'px','top'=>0,'right'=>0,'bottom'=>0,'left'=>0],'padding'=>$this->pad(18,36,18,36)]);
	}

	private function pad( int $t, int $r, int $b, int $l ): array {
		return ['unit'=>'px','top'=>(string)$t,'right'=>(string)$r,'bottom'=>(string)$b,'left'=>(string)$l,'isLinked'=>false];
	}

	private function genid(): string { return bin2hex( random_bytes(4) ); }

	/**
	 * Fidelity Scorer (The Training Engine).
	 * Calculates a quantitative score for a node to determine its qualitative value.
	 * Used for autonomous deduplication of responsive variations.
	 */
	private function calculate_fidelity_score( \DOMElement $node, \DOMXPath $xp ): float {
		$score = 0;
		// Character count weight
		$score += strlen( trim( $node->textContent ) ) / 100;
		// Child element density
		$score += $xp->query('.//*', $node)->length * 0.5;
		// High-value attributes (links, images, SVGs)
		$score += $xp->query('.//a', $node)->length * 5.0;
		$score += $xp->query('.//img | .//svg | .//canvas', $node)->length * 3.0;
		// Semantic headers
		$score += $xp->query('.//h1|.//h2|.//h3', $node)->length * 2.0;
		// Classes/Keywords
		if ( preg_match('/\b(desktop|main|full)\b/i', $node->getAttribute('class')) ) $score += 10;
		if ( preg_match('/\b(mobile|lite|small|hidden-md|hidden-lg)\b/i', $node->getAttribute('class')) ) $score -= 15;
		
		return $score;
	}

	private function promote_structural_section_root( \DOMElement $node, string $type, \DOMXPath $xp ): \DOMElement {
		if ( 'footer' !== $type ) {
			return $node;
		}

		$best_node = $node;
		$best_score = $this->score_footer_root_candidate( $node, $xp );
		$current = $node->parentNode;
		$depth = 0;

		while ( $current instanceof \DOMElement && $depth < 5 ) {
			$score = $this->score_footer_root_candidate( $current, $xp );
			if ( $score > $best_score ) {
				$best_score = $score;
				$best_node = $current;
			}
			$current = $current->parentNode;
			$depth++;
		}

		return $best_node;
	}

	private function score_footer_root_candidate( \DOMElement $node, \DOMXPath $xp ): float {
		$tag = strtolower( $node->nodeName );
		$class_id = strtolower( trim( (string) $node->getAttribute( 'class' ) . ' ' . (string) $node->getAttribute( 'id' ) ) );
		$score = 0.0;

		if ( 'footer' === $tag ) {
			$score += 50.0;
		}
		if ( str_contains( $class_id, 'footer' ) ) {
			$score += 20.0;
		}
		if ( str_contains( $class_id, 'footer-top' ) || str_contains( $class_id, 'footer-wrap' ) ) {
			$score += 10.0;
		}
		if ( str_contains( $class_id, 'footer-bottom' ) ) {
			$score -= 15.0;
		}

		$brand_identity = $this->extract_footer_brand_identity( $node, $xp );
		if ( ! empty( $brand_identity['brand_name'] ) || ! empty( $brand_identity['brand_logo'] ) ) {
			$score += 10.0;
		}

		$brand_name_key = strtolower( preg_replace( '/\s+/', ' ', (string) ( $brand_identity['brand_name'] ?? '' ) ) );
		$cols = $this->extract_footer_columns_generic( $node, $xp, $brand_name_key );
		$score += count( $cols ) * 15.0;

		if ( $xp->query( './/*[contains(@class,"footer-bottom") or contains(@class,"footer-status") or contains(@class,"footer-copy")]', $node )->length > 0 ) {
			$score += 10.0;
		}

		return $score;
	}

	private function cmap( string $c, string $e, string $l ): void { $this->class_map[] = ['class'=>$c,'element'=>$e,'location'=>$l]; }
}

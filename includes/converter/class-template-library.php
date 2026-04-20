<?php
/**
 * Template Library.
 *
 * Pattern-matched section templates for the 8 most common landing page
 * sections. When the classifier identifies a known pattern, the library
 * provides a pre-built skeleton with correct Elementor settings, which
 * is then populated with the actual content and styles extracted from
 * the source HTML.
 *
 * This is the highest-ROI feature per the architecture documentation:
 * common patterns produce dramatically better output from templates
 * than from general-purpose parsing.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Converter;

if ( ! defined( 'ABSPATH' ) ) exit;

class TemplateLibrary {

	private string $prefix;
	private string $bg_color;
	private string $accent;
	private string $text_color;
	private string $surface;
	private string $border;
	private string $f_display;
	private string $f_body;
	private string $f_mono;

	public function __construct( array $config ) {
		$this->prefix    = $config['prefix']    ?? 'sb';
		$this->bg_color  = $config['bg']        ?? '#0a0a12';
		$this->accent    = $config['accent']    ?? '#c8ff00';
		$this->text_color= $config['text']      ?? '#f5f3ee';
		$this->surface   = $config['surface']   ?? 'rgba(255,255,255,0.03)';
		$this->border    = $config['border']    ?? 'rgba(255,255,255,0.1)';
		$this->f_display = $config['f_display'] ?? 'Syne';
		$this->f_body    = $config['f_body']    ?? 'DM Sans';
		$this->f_mono    = $config['f_mono']    ?? 'Space Mono';
	}

	/**
	 * Get a section template skeleton, populated with provided content.
	 *
	 * @param string $type     Section type identifier.
	 * @param array  $content  Content extracted from source HTML.
	 * @return array|null      Elementor container/widget tree, or null if not in library.
	 */
	/** Types the library has explicit templates for. */
	private const TEMPLATE_TYPES = [
		'hero','marquee','stats','features','bento',
		'process','testimonials','pricing','cta','footer',
	];

	/**
	 * Check whether the library has a template for a given type.
	 * Used by the converter's F-01b routing: marquee/stats/cta should go
	 * through the library even when decide_strategy() returns 'html'.
	 */
	public function has_template( string $type ): bool {
		return in_array( $type, self::TEMPLATE_TYPES, true );
	}

	public function get( string $type, array $content = [] ): ?array {
		return match($type) {
			'hero'         => $this->hero( $content ),
			'marquee'      => $this->marquee( $content ),
			'stats'        => $this->stats( $content ),
			'features'     => $this->features( $content ),
			'bento'        => $this->bento( $content ),
			'process'      => $this->process( $content ),
			'testimonials' => $this->testimonials( $content ),
			'pricing'      => $this->pricing( $content ),
			'cta'          => $this->cta( $content ),
			'footer'       => $this->footer( $content ),
			default        => null,
		};
	}

	// ═══════════════════════════════════════════════════════════
	// HERO
	// ═══════════════════════════════════════════════════════════
	private function hero( array $c ): array {
		$p = $this->prefix;
		$elements = [];

		// Eyebrow.
		if ( ! empty( $c['eyebrow'] ) ) {
			$elements[] = $this->html_w( "<div class=\"{$p}-eyebrow\">{$c['eyebrow']}</div>", "{$p}-eyebrow-widget" );
		}

		// H1 — with full typography. Elementor Heading widget supports inline HTML in title.
		$headline = $c['headline'] ?? '';
		if ( '' !== trim( $headline ) ) {
			$elements[] = $this->heading( $headline, 'h1', "{$p}-hero-headline", [
				'typography_font_family'    => $this->f_display,
				'typography_font_weight'    => '800',
				'typography_font_size'      => [ 'unit' => 'px', 'size' => 120 ],
				'typography_letter_spacing' => [ 'unit' => 'px', 'size' => -3 ],
				'typography_line_height'    => [ 'unit' => 'em', 'size' => 0.92 ],
				'title_color'               => $this->text_color,
			]);
		}

		// Hero bottom row: sub + actions.
		$bottom_children = [];

		$sub = $c['sub'] ?? '';
		if ( $sub ) {
			$bottom_children[] = $this->text( "<p>{$sub}</p>", "{$p}-hero-sub" );
		}

		// CTA buttons.
		$btn_col_children = [];
		$primary_cta = $c['cta_primary'] ?? '';
		$ghost_cta   = $c['cta_secondary'] ?? '';

		if ( isset( $c['cta_primary'] ) && '' !== trim( (string) $c['cta_primary'] ) ) {
			$btn_col_children[] = $this->button( $primary_cta, '#', "{$p}-btn-primary {$p}-btn-hero-primary", $this->accent, $this->bg_color );
		}
		if ( $ghost_cta ) {
			$btn_col_children[] = $this->text(
				"<p><a href=\"#\" class=\"{$p}-btn-ghost\">{$ghost_cta}</a></p>",
				"{$p}-hero-ghost-wrap"
			);
		}

		if ( ! empty( $btn_col_children ) ) {
			$bottom_children[] = $this->container( 'column', "{$p}-hero-actions", '', $btn_col_children, [
				'gap'         => [ 'unit' => 'px', 'size' => 12, 'column' => 12, 'row' => 12 ],
				'align_items' => 'flex-end',
			]);
		}

		if ( ! empty( $bottom_children ) ) {
			$elements[] = $this->container( 'row', "{$p}-hero-bottom", '', $bottom_children, [
				'align_items'     => 'flex-end',
				'justify_content' => 'space-between',
				'gap'             => [ 'unit' => 'px', 'size' => 40, 'column' => 40, 'row' => 40 ],
			]);
		}

		return $this->container( 'column', "{$p}-hero {$p}-section {$p}-reveal", "{$p}-hero", $elements, [
			'min_height'             => [ 'unit' => 'vh', 'size' => 100 ],
			'min_height_type'        => 'min-height',
			'align_items'            => 'flex-start',
			'justify_content'        => 'flex-end',
			'background_background'  => 'classic',
			'background_color'       => $this->bg_color,
			'overflow'               => 'hidden',
			'padding'                => $this->pad( 0, 60, 80, 60 ),
		]);
	}

	// ═══════════════════════════════════════════════════════════
	// MARQUEE — Always HTML widget
	// ═══════════════════════════════════════════════════════════
	private function marquee( array $c ): array {
		$p     = $this->prefix;
		$items = $c['items'] ?? [];
		$ac    = $this->accent;
		$bc    = $this->border;

		$items_html = '';
		foreach ( $items as $item ) {
			$items_html .= "<div class=\"{$p}-mq-item\"><span class=\"{$p}-mq-dot\">◆</span>" . esc_html( $item ) . "</div>\n";
		}
		$double_html = $items_html . $items_html; // Double for seamless loop.

		$html = <<<HTML
<style>
.{$p}-mq-wrap{border-top:1px solid {$bc};border-bottom:1px solid {$bc};overflow:hidden;padding:18px 0;background:rgba(200,255,0,.04);}
.{$p}-mq-track{display:flex;gap:0;animation:{$p}-mq-scroll 22s linear infinite;white-space:nowrap;}
.{$p}-mq-track:hover{animation-play-state:paused;}
.{$p}-mq-item{display:inline-flex;align-items:center;gap:32px;padding:0 32px;font-family:'{$this->f_mono}',monospace;font-size:11px;letter-spacing:.15em;color:rgba(245,243,238,.3);}
.{$p}-mq-dot{color:{$ac};font-size:16px;line-height:1;}
@keyframes {$p}-mq-scroll{from{transform:translateX(0);}to{transform:translateX(-50%);}}
</style>
<div class="{$p}-mq-wrap">
  <div class="{$p}-mq-track">
{$double_html}  </div>
</div>
HTML;

		$widget = $this->html_w( $html, "{$p}-marquee-section", "{$p}-marquee" );

		return $this->container( 'row', "{$p}-marquee-wrap {$p}-section", "{$p}-marquee-section", [ $widget ], [
			'background_background' => 'classic',
			'background_color'      => $this->bg_color,
			'padding'               => $this->pad( 0, 0, 0, 0 ),
			'flex_wrap'             => 'nowrap',
			'overflow'              => 'hidden',
		]);
	}

	// ═══════════════════════════════════════════════════════════
	// STATS — native repeated cards
	// ═══════════════════════════════════════════════════════════
	private function stats( array $c ): array {
		$p     = $this->prefix;
		$stats = $c['stats'] ?? [];
		$count = max( 1, count( $stats ) );
		$cols  = $count >= 4 ? '1fr 1fr 1fr 1fr' : ( 3 === $count ? '1fr 1fr 1fr' : '1fr 1fr' );
		$cells = [];

		foreach ( $stats as $index => $stat ) {
			$value = trim( (string) ( $stat['num'] ?? '' ) );
			$unit  = trim( (string) ( $stat['unit'] ?? '' ) );
			$label = trim( (string) ( $stat['label'] ?? '' ) );

			$cell_letter = chr( 97 + $index );
			$cells[] = $this->container( 'column', "{$p}-stat-cell {$p}-stat-card-{$cell_letter} {$p}-reveal {$p}-d" . min( 3, $index + 1 ), "{$p}-stat-{$cell_letter}", array_values( array_filter( [
				$this->value_heading( trim( $value . $unit ), "{$p}-stat-num", [
					'typography_font_size'      => [ 'unit' => 'px', 'size' => 64 ],
					'typography_letter_spacing' => [ 'unit' => 'px', 'size' => -2 ],
					'title_color'               => $this->text_color,
				] ),
				'' !== $label ? $this->text( '<p>' . esc_html( $label ) . '</p>', "{$p}-stat-label" ) : null,
			] ) ), [
				'background_background' => 'classic',
				'background_color'      => $this->bg_color,
				'padding'               => $this->pad( 60, 40, 60, 40 ),
				'overflow'              => 'hidden',
			] );
		}

		$grid = $this->grid_container( $cells, $cols, "{$p}-stats-grid" );
		$grid['settings']['gap'] = [ 'unit' => 'px', 'size' => 1, 'column' => 1, 'row' => 1 ];

		return $this->container( 'column', "{$p}-stats-section-wrap {$p}-section", "{$p}-stats", [ $grid ], [
			'background_background' => 'classic',
			'background_color'      => $this->bg_color,
			'padding'               => $this->pad( 120, 60, 120, 60 ),
		] );
	}

	// ═══════════════════════════════════════════════════════════
	// FEATURES / BENTO — native-first card grids
	// ═══════════════════════════════════════════════════════════
	private function features( array $c ): array {
		$p   = $this->prefix;
		$els = [ $this->section_header_native( $c ) ];

		$cards = $c['cards'] ?? [];

		$grid_items = [];
		foreach ( $cards as $i => $card ) {
			$inner = [];
			if ( ! empty( $card['tag'] ) ) {
				$inner[] = $this->eyebrow_label( $card['tag'], "{$p}-card-tag" );
			}
			if ( ! empty( $card['title'] ) ) {
				$inner[] = $this->heading( $card['title'], 'h3', "{$p}-card-title {$p}-bento-title", [
					'typography_font_family' => $this->f_display,
					'typography_font_weight' => '700',
					'typography_font_size'   => [ 'unit' => 'px', 'size' => 24 ],
					'title_color'            => $this->text_color,
				]);
			}
			if ( ! empty( $card['body'] ) ) {
				$inner[] = $this->text( "<p>{$card['body']}</p>", "{$p}-card-body" );
			}
			if ( ! empty( $card['visual_html'] ) ) {
				$inner[] = $this->html_w( $card['visual_html'], "{$p}-card-visual-widget {$p}-card-visual" );
			}
			$letter = chr(97+$i);
			$grid_items[] = $this->container( 'column', "{$p}-bento-card {$p}-bc-{$letter} {$p}-bento-card-{$letter} {$p}-reveal", "{$p}-bento-card-{$letter}", $inner, [
				'background_background' => 'classic',
				'background_color'      => $this->surface,
				'border_border'         => 'solid',
				'border_color'          => $this->border,
				'border_width'          => $this->bw( 1, 1, 1, 1 ),
				'padding'               => $this->pad( 36, 36, 36, 36 ),
				'overflow'              => 'hidden',
			]);
		}

		$els[] = $this->grid_container( $grid_items, trim( str_repeat( '1fr ', 12 ) ), "{$p}-bento-grid" );

		return $this->container( 'column', "{$p}-features {$p}-section", "{$p}-features", $els, [
			'background_background' => 'classic',
			'background_color'      => $this->bg_color,
			'padding'               => $this->pad( 120, 60, 120, 60 ),
		]);
	}

	// ═══════════════════════════════════════════════════════════
	// BENTO — Native-first simple grid
	// ═══════════════════════════════════════════════════════════
	private function bento( array $c ): array {
		$p     = $this->prefix;
		$cards = $c['cards'] ?? [];
		$els   = [ $this->section_header_native( $c ) ];
		$grid_items = [];

		foreach ( $cards as $i => $card ) {
			$inner = [];
			if ( ! empty( $card['tag'] ) ) {
				$inner[] = $this->eyebrow_label( $card['tag'], "{$p}-card-tag" );
			}
			if ( ! empty( $card['title'] ) ) {
				$inner[] = $this->heading( $card['title'], 'h3', "{$p}-card-title {$p}-bento-title", [
					'typography_font_family' => $this->f_display,
					'typography_font_weight' => '700',
					'typography_font_size'   => [ 'unit' => 'px', 'size' => 24 ],
					'title_color'            => $this->text_color,
				] );
			}
			if ( ! empty( $card['body'] ) ) {
				$inner[] = $this->text( "<p>{$card['body']}</p>", "{$p}-card-body" );
			}
			if ( ! empty( $card['visual_html'] ) ) {
				$inner[] = $this->html_w( $card['visual_html'], "{$p}-card-visual-widget {$p}-card-visual" );
			}

			$letter = chr(97+$i);
			$grid_items[] = $this->container( 'column', "{$p}-bento-card {$p}-bc-{$letter} {$p}-bento-card-{$letter} {$p}-reveal", "{$p}-bento-card-{$letter}", $inner, [
				'background_background' => 'classic',
				'background_color'      => $this->surface,
				'border_border'         => 'solid',
				'border_color'          => $this->border,
				'border_width'          => $this->bw( 1, 1, 1, 1 ),
				'padding'               => $this->pad( 36, 36, 36, 36 ),
				'overflow'              => 'hidden',
			] );
		}

		$els[] = $this->grid_container( $grid_items, trim( str_repeat( '1fr ', 12 ) ), "{$p}-bento-grid" );

		return $this->container( 'column', "{$p}-features {$p}-section", "{$p}-features", $els, [
			'background_background' => 'classic',
			'background_color'      => $this->bg_color,
			'padding'               => $this->pad( 120, 60, 120, 60 ),
		]);
	}

	// ═══════════════════════════════════════════════════════════
	// PROCESS
	// ═══════════════════════════════════════════════════════════
	private function process( array $c ): array {
		$p     = $this->prefix;
		$ac    = $this->accent;
		$bc    = $this->border;
		$els   = [ $this->section_header_native( $c ) ];

		$steps = $c['steps'] ?? [];

		$step_widgets = [];
		foreach ( $steps as $i => $step ) {
			$num = str_pad( $i + 1, 2, '0', STR_PAD_LEFT );
			$step_letter = chr( 97 + $i );
			$step_inner = [
				$this->eyebrow_label( $num, "{$p}-step-num", [
					'typography_font_size'      => [ 'unit' => 'px', 'size' => 11 ],
					'typography_letter_spacing' => [ 'unit' => 'em', 'size' => 0.1 ],
					'title_color'               => 'rgba(245,243,238,.2)',
				] ),
				$this->container( 'column', "{$p}-step-content", '', [
					$this->heading( $step['title'] ?? '', 'h4', "{$p}-step-title", [
						'typography_font_family' => $this->f_display,
						'typography_font_weight' => '700',
						'typography_font_size'   => [ 'unit' => 'px', 'size' => 20 ],
						'title_color'            => $this->text_color,
					]),
					! empty( $step['desc'] ) ? $this->text( "<p>{$step['desc']}</p>", "{$p}-step-desc" ) : null,
				]),
			];
			if ( ! empty( $step['visual_html'] ) ) {
				$step_inner[] = $this->html_w( $step['visual_html'], "{$p}-step-visual-widget {$p}-card-visual", "{$p}-step-visual-{$step_letter}" );
			}

			$step_widgets[] = $this->container( 'row', "{$p}-process-step {$p}-process-step-{$step_letter}", "{$p}-process-step-{$step_letter}", array_filter( $step_inner ), [
				'gap'           => [ 'unit' => 'px', 'size' => 32, 'column' => 32, 'row' => 32 ],
				'align_items'   => 'flex-start',
				'border_border' => 'solid',
				'border_color'  => $bc,
				'border_width'  => $this->bw( 0, 0, 1, 0 ),
				'padding'       => $this->pad( 36, 0, 36, 0 ),
			]);
		}

		$steps_col  = $this->container( 'column', "{$p}-process-steps", '', $step_widgets );
		$orbital    = $this->build_orbital();

		$process_grid = $this->container( 'row', "{$p}-process-grid", '', [ $steps_col, $orbital ], [
			'gap'         => [ 'unit' => 'px', 'size' => 100, 'column' => 100, 'row' => 100 ],
			'align_items' => 'center',
		], true );

		$els[] = $process_grid;

		return $this->container( 'column', "{$p}-process {$p}-section {$p}-reveal", "{$p}-process", $els, [
			'background_background' => 'classic',
			'background_color'      => $this->bg_color,
			'border_border'         => 'solid',
			'border_color'          => $bc,
			'border_width'          => $this->bw( 1, 0, 0, 0 ),
			'padding'               => $this->pad( 120, 60, 120, 60 ),
		]);
	}

	private function build_orbital(): array {
		$p  = $this->prefix;
		$ac = $this->accent;
		$bc = $this->border;
		$bg = $this->bg_color;
		$html = <<<HTML
<style>
.{$p}-orb-wrap{position:relative;height:520px;background:rgba(255,255,255,.02);border:1px solid {$bc};display:flex;align-items:center;justify-content:center;overflow:hidden;}
.{$p}-orb-ring{position:absolute;border-radius:50%;border:1px solid rgba(200,255,0,.2);animation:{$p}-orb-pulse 4s ease-in-out infinite;}
.{$p}-orb-ring:nth-child(1){width:300px;height:300px;}
.{$p}-orb-ring:nth-child(2){width:220px;height:220px;animation-delay:-1s;border-color:rgba(200,255,0,.3);}
.{$p}-orb-ring:nth-child(3){width:140px;height:140px;animation-delay:-2s;background:rgba(200,255,0,.06);border-color:rgba(200,255,0,.5);}
.{$p}-orb-center{width:64px;height:64px;background:{$ac};border-radius:50%;position:absolute;display:flex;align-items:center;justify-content:center;font-family:'{$this->f_mono}',monospace;font-size:11px;color:{$bg};font-weight:700;letter-spacing:.05em;}
.{$p}-orb-node{position:absolute;width:40px;height:40px;background:rgba(200,255,0,.1);border:1px solid rgba(200,255,0,.4);border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:14px;}
@keyframes {$p}-orb-pulse{0%,100%{transform:scale(1);opacity:.6;}50%{transform:scale(1.05);opacity:1;}}
</style>
<div class="{$p}-orb-wrap">
  <div class="{$p}-orb-ring"></div>
  <div class="{$p}-orb-ring"></div>
  <div class="{$p}-orb-ring"></div>
  <div class="{$p}-orb-center">AI</div>
  <div class="{$p}-orb-node" style="top:80px;left:50%;transform:translateX(-50%);">⚙</div>
  <div class="{$p}-orb-node" style="bottom:80px;left:50%;transform:translateX(-50%);">→</div>
  <div class="{$p}-orb-node" style="left:60px;top:50%;transform:translateY(-50%);">◈</div>
  <div class="{$p}-orb-node" style="right:60px;top:50%;transform:translateY(-50%);">⬡</div>
</div>
HTML;
		return $this->html_w( $html, "{$p}-orbital-widget {$p}-reveal" );
	}

	// ═══════════════════════════════════════════════════════════
	// TESTIMONIALS
	// ═══════════════════════════════════════════════════════════
	private function testimonials( array $c ): array {
		$p     = $this->prefix;
		$bc    = $this->border;
		$els   = [ $this->section_header_native( $c ) ];
		$cards = $c['cards'] ?? [];

		$row = [];
		foreach ( $cards as $i => $card ) {
			$q        = esc_html( $card['quote'] ?? '' );
			$name     = esc_html( $card['name'] ?? '' );
			$role     = esc_html( $card['role'] ?? '' );
			$initials = strtoupper( substr( $name, 0, 1 ) . ( strpos($name,' ') ? substr( $name, strpos($name,' ')+1, 1 ) : '' ) );

			$inner = [
				$this->text( "<p>\"{$q}\"</p>", "{$p}-testi-quote" ),
				$this->person_meta_row( $initials, $name, $role, "{$p}-testi-author" ),
			];

			if ( ! empty( $card['visual_html'] ) ) {
				$inner[] = $this->html_w( $card['visual_html'], "{$p}-testi-visual-widget" );
			}

			$delay_class = $i < 3 ? " {$p}-d" . ($i+1) : '';
			$card_letter = chr( 97 + $i );
			$row[] = $this->container( 'column', trim( "{$p}-testi-card {$p}-testi-card-{$card_letter} {$p}-reveal{$delay_class}" ), "{$p}-testi-card-{$card_letter}", $inner, [
				'background_background' => 'classic',
				'background_color'      => $this->surface,
				'border_border'         => 'solid',
				'border_color'          => $bc,
				'border_width'          => $this->bw( 1, 1, 1, 1 ),
				'padding'               => $this->pad( 40, 40, 40, 40 ),
				'gap'                   => [ 'unit' => 'px', 'size' => 24, 'column' => 24, 'row' => 24 ],
			]);
		}

		$els[] = $this->container( 'row', "{$p}-testi-grid", '', $row, [
			'gap' => [ 'unit' => 'px', 'size' => 24, 'column' => 24, 'row' => 24 ],
		]);

		return $this->container( 'column', "{$p}-testimonials {$p}-section {$p}-reveal", "{$p}-testimonials", $els, [
			'background_background' => 'classic',
			'background_color'      => $this->bg_color,
			'border_border'         => 'solid',
			'border_color'          => $bc,
			'border_width'          => $this->bw( 1, 0, 0, 0 ),
			'overflow'              => 'hidden',
			'padding'               => $this->pad( 120, 60, 120, 60 ),
		]);
	}

	// ═══════════════════════════════════════════════════════════
	// PRICING
	// ═══════════════════════════════════════════════════════════
	private function pricing( array $c ): array {
		$p     = $this->prefix;
		$ac    = $this->accent;
		$bc    = $this->border;
		$bg    = $this->bg_color;
		$els   = [ $this->section_header_native( $c ) ];
		$cards = $c['cards'] ?? [];

		$row = [];
		foreach ( $cards as $i => $card ) {
			$is_f   = ! empty( $card['featured'] );
			$bg_col = $is_f ? $ac : $this->surface;
			$inner  = [];

			if ( ! empty( $card['visual_html'] ) ) {
				$inner[] = $this->html_w( $card['visual_html'], "{$p}-price-visual-widget" );
			}

			if ( ! empty( $card['badge'] ) ) {
				$inner[] = $this->eyebrow_label( $card['badge'], "{$p}-price-badge", [
					'typography_font_size'      => [ 'unit' => 'px', 'size' => 10 ],
					'typography_letter_spacing' => [ 'unit' => 'em', 'size' => 0.15 ],
				] );
			}

			$inner[] = $this->eyebrow_label( $card['plan'] ?? '', "{$p}-price-plan", [
				'typography_font_size'      => [ 'unit' => 'px', 'size' => 11 ],
				'typography_letter_spacing' => [ 'unit' => 'em', 'size' => 0.2 ],
				'title_color'               => $is_f ? $bg : 'rgba(245,243,238,.4)',
			] );
			$inner[] = $this->value_heading( $card['price'] ?? '0', "{$p}-price-amount", [
				'typography_font_size'      => [ 'unit' => 'px', 'size' => 60 ],
				'typography_letter_spacing' => [ 'unit' => 'px', 'size' => -3 ],
				'title_color'               => $is_f ? $bg : $this->text_color,
			] );
			$inner[] = $this->text( '<p>' . esc_html( (string) ( $card['period'] ?? '' ) ) . '</p>', "{$p}-price-period" );

			if ( ! empty( $card['features'] ) ) {
				$inner[] = $this->icon_list(
					$card['features'],
					"{$p}-price-feats-list",
					(string) ( $card['features_icon'] ?? '' )
				);
			}

			$btn_cls = $is_f ? "{$p}-btn-price-dark" : "{$p}-btn-price";
			if ( ! empty( $card['cta'] ) ) {
				$inner[] = $this->button( $card['cta'], '#', $btn_cls, $is_f ? $bg : 'transparent', $is_f ? $ac : $this->text_color );
			}

			$card_letter = chr( 97 + $i );
			$card_cls = trim( "{$p}-price-card {$p}-price-card-{$card_letter} {$p}-reveal {$p}-d" . ($i+1) . ( $is_f ? " {$p}-price-featured" : '' ) );
			$row[] = $this->container( 'column', $card_cls, "{$p}-price-card-{$card_letter}", $inner, [
				'background_background' => 'classic',
				'background_color'      => $bg_col,
				'border_border'         => 'solid',
				'border_color'          => $is_f ? $ac : $bc,
				'border_width'          => $this->bw( 1, 1, 1, 1 ),
				'padding'               => $this->pad( 48, 40, 48, 40 ),
				'position'              => 'relative',
			]);
		}

		$els[] = $this->container( 'row', "{$p}-pricing-grid", '', $row, [ 'gap' => [ 'unit' => 'px', 'size' => 24, 'column' => 24, 'row' => 24 ] ]);

		return $this->container( 'column', "{$p}-pricing {$p}-section {$p}-reveal", "{$p}-pricing", $els, [
			'background_background' => 'classic',
			'background_color'      => $bg,
			'border_border'         => 'solid',
			'border_color'          => $bc,
			'border_width'          => $this->bw( 1, 0, 0, 0 ),
			'padding'               => $this->pad( 120, 60, 120, 60 ),
		]);
	}

	// ═══════════════════════════════════════════════════════════
	// CTA
	// ═══════════════════════════════════════════════════════════
	private function cta( array $c ): array {
		$p   = $this->prefix;
		$ac  = $this->accent;
		$bg  = $this->bg_color;
		$els = [];

		$title = $c['title'] ?? '';
		$sub   = $c['sub']   ?? '';
		$cta1  = $c['cta_primary']   ?? '';
		$cta2  = $c['cta_secondary'] ?? '';

		if ( '' !== trim( $title ) ) {
			$els[] = $this->heading( $title, 'h2', "{$p}-cta-title", [
				'typography_font_family'    => $this->f_display,
				'typography_font_weight'    => '800',
				'typography_font_size'      => [ 'unit' => 'px', 'size' => 68 ],
				'typography_letter_spacing' => [ 'unit' => 'px', 'size' => -2 ],
				'typography_line_height'    => [ 'unit' => 'em', 'size' => 1.0 ],
				'title_color'               => $bg,
			]);
		}
		if ( '' !== trim( $sub ) ) {
			$els[] = $this->text( "<p>{$sub}</p>", "{$p}-cta-sub" );
		}
		$cta_buttons = [];
		if ( '' !== trim( $cta1 ) ) {
			$cta_buttons[] = $this->button( $cta1, '#', "{$p}-btn-dark", $bg, $ac );
		}
		if ( '' !== trim( $cta2 ) ) {
			$cta_buttons[] = $this->button( $cta2, '#', "{$p}-btn-outline-dark", 'transparent', $bg );
		}
		if ( ! empty( $cta_buttons ) ) {
			$els[] = $this->container( 'row', "{$p}-cta-actions", '', $cta_buttons, [ 'gap' => [ 'unit' => 'px', 'size' => 16, 'column' => 16, 'row' => 16 ], 'align_items' => 'center' ]);
		}

		return $this->container( 'column', "{$p}-cta {$p}-section {$p}-reveal", "{$p}-cta", $els, [
			'background_background' => 'classic',
			'background_color'      => $ac,
			'padding'               => $this->pad( 100, 100, 100, 100 ),
			'margin'                => [ 'unit' => 'px', 'top' => '0', 'right' => '60', 'bottom' => '0', 'left' => '60', 'isLinked' => false ],
			'overflow'              => 'hidden',
			'position'              => 'relative',
		]);
	}

	// ═══════════════════════════════════════════════════════════
	// FOOTER
	// ═══════════════════════════════════════════════════════════
	private function footer( array $c ): array {
		$p       = $this->prefix;
		$ac      = $this->accent;
		$bc      = $this->border;
		$project = $c['brand_name'] ?? '';
		$cols    = $c['cols'] ?? [];

		// ── New: Identity Training
		$logo_html = ! empty( $c['brand_logo'] ) ? $c['brand_logo'] : ( $project ? "<a href=\"#\" class=\"{$p}-footer-logo\">{$project}</a>" : '' );
		$bio_text  = $c['brand_desc'] ?? '';

		// Brand column.
		$brand_elements = [];
		if ( '' !== trim( $logo_html ) ) {
			$brand_elements[] = $this->html_w( $logo_html, "{$p}-footer-logo-widget" );
		}
		if ( '' !== trim( $bio_text ) ) {
			$brand_elements[] = $this->text( "<p>{$bio_text}</p>", "{$p}-footer-brand-desc" );
		}

		$nav_cols = [];
		if ( ! empty( $brand_elements ) ) {
			$nav_cols[] = $this->container( 'column', "{$p}-footer-brand-col", '', $brand_elements, [ 'gap' => [ 'unit' => 'px', 'size' => 20, 'column' => 20, 'row' => 20 ] ]);
		}
		foreach ( $cols as $col ) {
			$links = array_values( array_filter( array_map( 'trim', (array) ( $col['links'] ?? [] ) ) ) );
			if ( empty( $links ) ) {
				continue;
			}

			$title_widget = null;
			if ( ! empty( $col['title'] ) ) {
				$title_widget = $this->heading( $col['title'], 'h5', "{$p}-footer-col-title", [
					'typography_font_family'    => $this->f_mono,
					'typography_font_weight'    => '700',
					'typography_font_size'      => [ 'unit' => 'px', 'size' => 10 ],
					'typography_letter_spacing' => [ 'unit' => 'em', 'size' => 0.2 ],
					'title_color'               => 'rgba(245,243,238,.25)',
				] );
			}

			$col_letter = chr( 97 + count( $nav_cols ) );
			$nav_cols[] = $this->container( 'column', "{$p}-footer-nav-col {$p}-footer-nav-col-{$col_letter}", "{$p}-footer-nav-col-{$col_letter}", array_values( array_filter( [
				$title_widget,
				$this->icon_list(
					$links,
					"{$p}-footer-links-list",
					(string) ( $col['list_icon'] ?? '' )
				),
			] ) ), [ 'gap' => [ 'unit' => 'px', 'size' => 20, 'column' => 20, 'row' => 20 ] ]);
		}

		$footer_grid = [
			'id'       => $this->genid(),
			'elType'   => 'container',
			'isInner'  => true,
			'settings' => [
				'container_type'  => 'grid',
				'grid_columns_fr' => '2fr 1fr 1fr 1fr',
				'gap'             => [ 'unit' => 'px', 'size' => 60, 'column' => 60, 'row' => 60 ],
				'_css_classes'    => "{$p}-footer-top {$p}-footer-grid",
				'margin'          => [ 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '60', 'left' => '0', 'isLinked' => false ],
			],
			'elements' => $nav_cols,
		];

		$bottom = $this->html_w(
			"<div class=\"{$p}-footer-bottom\"><span class=\"{$p}-footer-copy\">© " . date('Y') . " {$project} — ALL RIGHTS RESERVED.</span><div class=\"{$p}-footer-status\"><div class=\"{$p}-status-dot\"></div>ALL SYSTEMS OPERATIONAL</div></div>",
			"{$p}-footer-bottom-widget"
		);

		return $this->container( 'column', "{$p}-footer {$p}-section", "{$p}-footer", [ $footer_grid, $bottom ], [
			'background_background' => 'classic',
			'background_color'      => $this->bg_color,
			'border_border'         => 'solid',
			'border_color'          => $bc,
			'border_width'          => $this->bw( 1, 0, 0, 0 ),
			'padding'               => $this->pad( 80, 60, 40, 60 ),
		]);
	}

	// ═══════════════════════════════════════════════════════════
	// SHARED SECTION HEADER
	// ═══════════════════════════════════════════════════════════

	private function section_header_native( array $c ): array {
		$p   = $this->prefix;
		$left_children = [];

		if ( ! empty( $c['tag'] ) ) {
			$left_children[] = $this->html_w( "<div class=\"{$p}-section-tag\">{$c['tag']}</div>", "{$p}-section-tag-widget" );
		}
		if ( ! empty( $c['title'] ) ) {
			$left_children[] = $this->heading( $c['title'], 'h2', "{$p}-section-title", [
				'typography_font_family'    => $this->f_display,
				'typography_font_weight'    => '800',
				'typography_font_size'      => [ 'unit' => 'px', 'size' => 52 ],
				'typography_letter_spacing' => [ 'unit' => 'px', 'size' => -2 ],
				'typography_line_height'    => [ 'unit' => 'em', 'size' => 1.05 ],
				'title_color'               => $this->text_color,
			]);
		}

		$left_col = $this->container( 'column', "{$p}-section-header-left", '', $left_children );

		$children = [ $left_col ];
		if ( ! empty( $c['desc'] ) ) {
			$children[] = $this->text( "<p>{$c['desc']}</p>", "{$p}-section-desc" );
		}

		return $this->container( 'row', "{$p}-section-header {$p}-reveal", '', $children, [
			'align_items'     => 'flex-end',
			'justify_content' => 'space-between',
			'margin'          => [ 'unit' => 'px', 'top' => '0', 'right' => '0', 'bottom' => '64', 'left' => '0', 'isLinked' => false ],
		]);
	}

	// ═══════════════════════════════════════════════════════════
	// ELEMENT FACTORIES
	// ═══════════════════════════════════════════════════════════

	private function container( string $dir, string $cls, string $id, array $children, array $extra = [], bool $balanced = false ): array {
		$s = array_merge([
			'_css_classes'   => $cls,
			'flex_direction' => 'column' === $dir ? 'column' : 'row',
			'flex_wrap'      => 'nowrap', // Default to nowrap to prevent horizontal track breakage.
			'gap'            => [ 'unit' => 'px', 'size' => 24, 'column' => 24, 'row' => 24 ],
		], $extra);
		if ( $id ) $s['_element_id'] = $id;

		// ── Autonomous Layout Balance
		// Architecture article §15: "Proportional layouts must be content-aware".
		if ( $balanced && count($children) === 2 && 'row' === ( $s['flex_direction'] ?? '' ) ) {
			$w1 = $this->calculate_node_weight($children[0]);
			$w2 = $this->calculate_node_weight($children[1]);
			$total = $w1 + $w2;
			if ($total > 0) {
				$r1 = round(($w1 / $total) * 100);
				$r2 = 100 - $r1;
				// Clamp ratios to prevent extreme layouts
				$r1 = max(30, min(70, $r1));
				$r2 = 100 - $r1;
				
				if (!isset($children[0]['settings'])) $children[0]['settings'] = [];
				if (!isset($children[1]['settings'])) $children[1]['settings'] = [];
				$children[0]['settings']['width'] = [ 'unit' => '%', 'size' => $r1 ];
				$children[1]['settings']['width'] = [ 'unit' => '%', 'size' => $r2 ];
			}
		}

		// isInner = true for any container nested inside another.
		$is_inner = isset( $extra['isInner'] ) ? $extra['isInner'] : false;

		return [
			'id'      => $this->genid(),
			'elType'  => 'container',
			'isInner' => $is_inner,
			'settings'=> $s,
			'elements'=> array_values( array_filter( $children ) ),
		];
	}

	private function grid_container( array $children, string $cols, string $cls ): array {
		return [
			'id'      => $this->genid(),
			'elType'  => 'container',
			'isInner' => true,
			'settings'=> [
				'_css_classes'   => $cls,
				'container_type' => 'grid',
				'grid_columns_fr'=> $cols,
				'gap'            => [ 'unit' => 'px', 'size' => 16, 'column' => 16, 'row' => 16 ],
			],
			'elements'=> array_values( array_filter( $children ) ),
		];
	}

	private function html_w( string $html, string $cls, string $id = '' ): array {
		$s = [ '_css_classes' => $cls, 'html' => $html ];
		if ( $id ) $s['_element_id'] = $id;
		return [ 'id' => $this->genid(), 'elType' => 'widget', 'widgetType' => 'html', 'isInner' => false, 'settings' => $s, 'elements' => [] ];
	}

	private function heading( string $text, string $tag, string $cls, array $typo = [] ): array {
		return [
			'id'         => $this->genid(),
			'elType'     => 'widget',
			'widgetType' => 'heading',
			'isInner'    => false,
			'settings'   => array_merge([
				'_css_classes'              => $cls,
				'title'                     => wp_kses_post( $text ),
				'header_size'               => $tag,
				'align'                     => 'left',
				'typography_typography'     => 'custom',
				'typography_font_family'    => $this->f_display,
				'typography_font_weight'    => '700',
				'typography_font_size'      => [ 'unit' => 'px', 'size' => 32 ],
				'typography_letter_spacing' => [ 'unit' => 'px', 'size' => -1 ],
				'typography_line_height'    => [ 'unit' => 'em', 'size' => 1.1 ],
				'title_color'               => $this->text_color,
			], $typo),
			'elements' => [],
		];
	}

	private function text( string $html, string $cls ): array {
		return [ 'id' => $this->genid(), 'elType' => 'widget', 'widgetType' => 'text-editor', 'isInner' => false, 'settings' => [ '_css_classes' => $cls, 'editor' => $html ], 'elements' => [] ];
	}

	private function eyebrow_label( string $text, string $cls, array $typo = [] ): ?array {
		$text = trim( $text );
		if ( '' === $text ) {
			return null;
		}

		return $this->heading( $text, 'h6', $cls, array_merge( [
			'typography_font_family'    => $this->f_mono,
			'typography_font_weight'    => '700',
			'typography_font_size'      => [ 'unit' => 'px', 'size' => 10 ],
			'typography_letter_spacing' => [ 'unit' => 'em', 'size' => 0.15 ],
			'title_color'               => $this->accent,
		], $typo ) );
	}

	private function value_heading( string $text, string $cls, array $typo = [] ): ?array {
		$text = trim( $text );
		if ( '' === $text ) {
			return null;
		}

		return $this->heading( esc_html( $text ), 'h3', $cls, array_merge( [
			'typography_font_family'    => $this->f_display,
			'typography_font_weight'    => '800',
			'typography_font_size'      => [ 'unit' => 'px', 'size' => 48 ],
			'typography_letter_spacing' => [ 'unit' => 'px', 'size' => -2 ],
			'typography_line_height'    => [ 'unit' => 'em', 'size' => 1 ],
			'title_color'               => $this->text_color,
		], $typo ) );
	}

	private function person_meta_row( string $initials, string $name, string $role, string $cls ): array {
		$avatar = $this->container( 'column', "{$this->prefix}-testi-avatar-wrap", '', [
			$this->heading( esc_html( $initials ), 'h6', "{$this->prefix}-testi-avatar", [
				'typography_font_family' => $this->f_display,
				'typography_font_weight' => '800',
				'typography_font_size'   => [ 'unit' => 'px', 'size' => 14 ],
				'title_color'            => $this->bg_color,
			] ),
		], [
			'justify_content' => 'center',
			'align_items'     => 'center',
			'width'           => [ 'unit' => 'px', 'size' => 40 ],
			'min_height'      => [ 'unit' => 'px', 'size' => 40 ],
			'min_height_type' => 'min-height',
			'background_background' => 'classic',
			'background_color'      => $this->accent,
			'border_radius'         => [ 'unit' => 'px', 'top' => 999, 'right' => 999, 'bottom' => 999, 'left' => 999 ],
		] );

		$meta = $this->container( 'column', "{$this->prefix}-testi-meta", '', array_values( array_filter( [
			'' !== trim( $name ) ? $this->heading( esc_html( $name ), 'h6', "{$this->prefix}-testi-name", [
				'typography_font_family' => $this->f_body,
				'typography_font_weight' => '500',
				'typography_font_size'   => [ 'unit' => 'px', 'size' => 14 ],
				'title_color'            => $this->text_color,
			] ) : null,
			'' !== trim( $role ) ? $this->text( '<p>' . esc_html( $role ) . '</p>', "{$this->prefix}-testi-role" ) : null,
		] ) ) );

		return $this->container( 'row', $cls, '', [ $avatar, $meta ], [
			'align_items'   => 'center',
			'gap'           => [ 'unit' => 'px', 'size' => 14, 'column' => 14, 'row' => 14 ],
			'border_border' => 'solid',
			'border_color'  => $this->border,
			'border_width'  => $this->bw( 1, 0, 0, 0 ),
			'padding'       => $this->pad( 20, 0, 0, 0 ),
		] );
	}

	private function icon_list( array $items, string $cls, string $icon_value ): ?array {
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

		return [
			'id'         => $this->genid(),
			'elType'     => 'widget',
			'widgetType' => 'icon-list',
			'isInner'    => false,
			'settings'   => [
				'_css_classes'                    => $cls,
				'icon_list'                       => $list_items,
				'view'                            => 'traditional',
				'icon_align'                      => 'left',
				'icon_indent'                     => [ 'unit' => 'px', 'size' => 10 ],
				'text_indent'                     => [ 'unit' => 'px', 'size' => 0 ],
				'divider'                         => 'none',
				'link_click'                      => 'none',
				'icon_color'                      => $this->accent,
				'text_color'                      => $this->text_color,
				'typography_typography'           => 'custom',
				'typography_font_family'          => $this->f_body,
				'typography_font_weight'          => '400',
				'typography_font_size'            => [ 'unit' => 'px', 'size' => 14 ],
			],
			'elements' => [],
		];
	}

	private function button( string $text, string $url, string $cls, string $bg, string $color ): array {
		return [
			'id'         => $this->genid(),
			'elType'     => 'widget',
			'widgetType' => 'button',
			'isInner'    => false,
			'settings'   => [
				'_css_classes'               => $cls,
				'text'                       => $text,
				'link'                       => [ 'url' => $url ?: '#', 'is_external' => false, 'nofollow' => false ],
				'background_color'           => $bg,
				'button_text_color'          => $color,
				'typography_typography'      => 'custom',
				'typography_font_family'     => $this->f_mono,
				'typography_font_weight'     => '700',
				'typography_font_size'       => [ 'unit' => 'px', 'size' => 12 ],
				'typography_letter_spacing'  => [ 'unit' => 'em', 'size' => 0.1 ],
				'border_radius'              => [ 'unit' => 'px', 'top' => 0, 'right' => 0, 'bottom' => 0, 'left' => 0 ],
				'padding'                    => [ 'unit' => 'px', 'top' => '18', 'right' => '36', 'bottom' => '18', 'left' => '36', 'isLinked' => false ],
			],
			'elements' => [],
		];
	}

	private function pad( int $t, int $r, int $b, int $l ): array {
		return [ 'unit' => 'px', 'top' => (string)$t, 'right' => (string)$r, 'bottom' => (string)$b, 'left' => (string)$l, 'isLinked' => false ];
	}

	private function bw( int $t, int $r, int $b, int $l ): array {
		return [ 'unit' => 'px', 'top' => (string)$t, 'right' => (string)$r, 'bottom' => (string)$b, 'left' => (string)$l, 'isLinked' => false ];
	}

	/**
	 * Autonomous Weight Calculator.
	 * Measures character volume and node density to determine layout proportions.
	 */
	private function calculate_node_weight( array $node ): int {
		$weight = 0;
		if ( isset($node['settings']['html']) ) $weight += strlen($node['settings']['html']);
		if ( isset($node['settings']['editor']) ) $weight += strlen($node['settings']['editor']);
		if ( isset($node['settings']['title']) ) $weight += strlen($node['settings']['title']) * 2;
		
		if ( ! empty($node['elements']) ) {
			foreach ( $node['elements'] as $child ) {
				$weight += $this->calculate_node_weight($child);
			}
		}
		return $weight ?: 100;
	}

	/**
	 * Autonomous Weight Calculator.
	 * Measures character volume and node density to determine layout proportions.
	 */
	private function genid(): string { return bin2hex( random_bytes(4) ); }
}

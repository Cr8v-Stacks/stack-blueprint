<?php
/**
 * Converter Page.
 *
 * @package StackBlueprint
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$sb_page      = 'converter';
require_once SB_ADMIN_PATH . 'partials/layout-open.php';

$ant_key = get_option( 'sb_api_key', '' );
$oai_key = get_option( 'sb_openai_key', '' );
$gem_key = get_option( 'sb_gemini_key', '' );
$has_any_key = ! empty($ant_key) || ! empty($oai_key) || ! empty($gem_key);

$def_strategy = (string) get_option( 'sb_default_strategy', 'v2' );
?>

<div id="sb-converter-page">

	<div class="sb-page-header">
		<p class="sb-eyebrow"><?php esc_html_e( 'Stack Blueprint', 'stack-blueprint' ); ?></p>
		<h1 class="sb-page-title"><?php esc_html_e( 'HTML to Elementor', 'stack-blueprint' ); ?></h1>
		<p class="sb-page-desc"><?php esc_html_e( 'Upload your HTML prototype and let AI convert it to a ready-to-import Elementor JSON template.', 'stack-blueprint' ); ?></p>
	</div>

	<div class="sb-conv-grid">

		<!-- ── Left: Form ── -->
		<div>
			<form id="sb-form" enctype="multipart/form-data">
				<?php wp_nonce_field( 'sb_convert', 'sb_nonce' ); ?>

				<!-- Provider Selector -->
				<div class="sb-engine-tabs" style="grid-template-columns: 1fr 1fr 1fr;">
					<button type="button" class="sb-engine-tab is-active" data-provider="anthropic">
						<svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="6" cy="6" r="4.5"/><path d="M4 6h4M6 4v4"/></svg>
						<?php esc_html_e( 'Claude', 'stack-blueprint' ); ?>
					</button>
					<button type="button" class="sb-engine-tab" data-provider="openai">
						<svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="6" cy="6" r="4.5"/><path d="M4 6h4M6 4v4"/></svg>
						<?php esc_html_e( 'ChatGPT', 'stack-blueprint' ); ?>
					</button>
					<button type="button" class="sb-engine-tab" data-provider="gemini">
						<svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="6" cy="6" r="4.5"/><path d="M4 6h4M6 4v4"/></svg>
						<?php esc_html_e( 'Gemini', 'stack-blueprint' ); ?>
					</button>
				</div>
				<input type="hidden" id="sb-provider" name="provider" value="anthropic">

				<!-- API info banners -->
				<div class="sb-engine-info">
					<?php if ( ! $has_any_key ) : ?>
					<div class="sb-notice sb-notice--warn" style="margin-bottom:16px">
						<svg class="sb-notice__icon" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M7 1l5.8 11H1.2L7 1z"/><line x1="7" y1="5.5" x2="7" y2="8"/><circle cx="7" cy="10" r="0.5" fill="currentColor"/></svg>
						<div class="sb-notice__body"><strong><?php esc_html_e( 'API not configured', 'stack-blueprint' ); ?></strong> <?php esc_html_e( 'Please add at least one API key in the Settings page.', 'stack-blueprint' ); ?></div>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=stack-blueprint-settings' ) ); ?>" class="sb-btn sb-btn--ghost sb-btn--sm sb-notice__cta"><?php esc_html_e( 'Settings', 'stack-blueprint' ); ?></a>
					</div>
					<?php endif; ?>
				</div>

				<!-- Strategy selector -->
				<div id="sb-strategy-area">
					<div class="sb-strategy-grid">
						<label class="sb-strategy-card<?php echo 'v1' === $def_strategy ? ' is-selected' : ''; ?>" data-strategy="v1">
							<input type="radio" name="strategy" value="v1" <?php checked( $def_strategy, 'v1' ); ?>>
							<span class="sb-strategy-card__check"><svg viewBox="0 0 8 6"><polyline points="1,3 3,5 7,1"/></svg></span>
							<p class="sb-strategy-card__tag">V1</p>
							<p class="sb-strategy-card__title"><?php esc_html_e( 'HTML Fidelity', 'stack-blueprint' ); ?></p>
							<p class="sb-strategy-card__desc"><?php esc_html_e( 'Max visual accuracy. Complex sections stay as self-contained HTML widgets. For developer-maintained sites.', 'stack-blueprint' ); ?></p>
						</label>

						<label class="sb-strategy-card v2<?php echo 'v2' === $def_strategy ? ' is-selected' : ''; ?>" data-strategy="v2">
							<input type="radio" name="strategy" value="v2" <?php checked( $def_strategy, 'v2' ); ?>>
							<span class="sb-strategy-card__check"><svg viewBox="0 0 8 6"><polyline points="1,3 3,5 7,1"/></svg></span>
							<p class="sb-strategy-card__tag">V2 &mdash; <?php esc_html_e( 'Recommended', 'stack-blueprint' ); ?></p>
							<p class="sb-strategy-card__title"><?php esc_html_e( 'Native Components', 'stack-blueprint' ); ?></p>
							<p class="sb-strategy-card__desc"><?php esc_html_e( 'Every editable element becomes a native widget. Best for client sites and non-technical editors.', 'stack-blueprint' ); ?></p>
						</label>
					</div>
				</div>

				<!-- HTML Drop Zone -->
				<div id="sb-dropzone" class="sb-dropzone">
					<input type="file" name="html_file" id="sb-html-file" accept=".html,.htm">
					<div class="sb-dropzone__prompt">
						<svg class="sb-dropzone__icon" viewBox="0 0 32 32" fill="none" stroke="currentColor" stroke-width="1.5">
							<path d="M16 22V10M11 15l5-5 5 5"/><rect x="4" y="4" width="24" height="24" rx="4"/>
						</svg>
						<p class="sb-dropzone__title"><?php esc_html_e( 'Drop your HTML prototype here', 'stack-blueprint' ); ?></p>
						<p class="sb-dropzone__hint"><?php esc_html_e( 'or click to browse &middot; .html / .htm', 'stack-blueprint' ); ?></p>
					</div>
					<div class="sb-dropzone__file">
						<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M2 2h6l3 3v7H2z"/></svg>
						<span id="sb-file-name"></span>
					</div>
				</div>

				<!-- Optional companion files -->
				<div class="sb-files-row">
					<div class="sb-mini-up" id="sb-css-up">
						<input type="file" id="sb-css-file" name="css_file" accept=".css">
						<span class="sb-mini-up__badge css">CSS</span>
						<div>
							<p class="sb-mini-up__lbl"><?php esc_html_e( 'Companion CSS', 'stack-blueprint' ); ?> <span style="opacity:.5"><?php esc_html_e( '(optional)', 'stack-blueprint' ); ?></span></p>
							<p class="sb-mini-up__name" id="sb-css-name"><?php esc_html_e( 'No file', 'stack-blueprint' ); ?></p>
						</div>
					</div>
					<div class="sb-mini-up" id="sb-js-up">
						<input type="file" id="sb-js-file" name="js_file" accept=".js">
						<span class="sb-mini-up__badge js">JS</span>
						<div>
							<p class="sb-mini-up__lbl"><?php esc_html_e( 'Companion JS', 'stack-blueprint' ); ?> <span style="opacity:.5"><?php esc_html_e( '(optional)', 'stack-blueprint' ); ?></span></p>
							<p class="sb-mini-up__name" id="sb-js-name"><?php esc_html_e( 'No file', 'stack-blueprint' ); ?></p>
						</div>
					</div>
				</div>

				<!-- Convert CTA -->
				<button type="submit" id="sb-convert-btn" class="sb-btn sb-btn--primary sb-btn--full sb-btn--lg">
					<span class="sb-btn__spin"></span>
					<span class="sb-btn__lbl">
						<svg width="13" height="13" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="1.8" style="margin-right:5px;vertical-align:middle"><polyline points="1,6.5 4.5,3 4.5,5 8.5,5"/><polyline points="12,6.5 8.5,10 8.5,8 4.5,8"/></svg>
						<?php esc_html_e( 'Convert with AI', 'stack-blueprint' ); ?>
					</span>
				</button>

			</form>

			<!-- Progress -->
			<div id="sb-progress" class="sb-progress">
				<p class="sb-progress__lbl"><?php esc_html_e( 'AI Processing', 'stack-blueprint' ); ?></p>
				<div class="sb-progress__track"><div class="sb-progress__fill" id="sb-prog-fill"></div></div>
				<div class="sb-progress__steps">
					<div class="sb-progress__step" id="sb-step-1"><span class="sb-progress__dot"></span><?php esc_html_e( 'Analyzing DOM & Prefix...', 'stack-blueprint' ); ?></div>
					<div class="sb-progress__step" id="sb-step-2"><span class="sb-progress__dot"></span><?php esc_html_e( 'Sending to AI Provider...', 'stack-blueprint' ); ?></div>
					<div class="sb-progress__step" id="sb-step-3"><span class="sb-progress__dot"></span><?php esc_html_e( 'Generating Elementor Tree...', 'stack-blueprint' ); ?></div>
					<div class="sb-progress__step" id="sb-step-4"><span class="sb-progress__dot"></span><?php esc_html_e( 'Assembling CSS & Output...', 'stack-blueprint' ); ?></div>
				</div>
			</div>


			<!-- Result -->
			<div id="sb-result" class="sb-result">
				<div class="sb-panel">
					<div class="sb-panel__head">
						<svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="var(--sb-green)" stroke-width="1.4"><circle cx="7" cy="7" r="5.8"/><polyline points="4.5,7 6.2,8.8 9.5,5.5"/></svg>
						<p class="sb-panel__title"><?php esc_html_e( 'Conversion Complete', 'stack-blueprint' ); ?></p>
						<span class="sb-panel__sub" id="sb-result-engine-badge">AI Engine</span>
					</div>
					<div class="sb-panel__body">
						<div class="sb-result-actions">
							<a href="#" id="sb-dl-json" class="sb-dl-btn" download>
								<svg class="sb-dl-btn__icon" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M16 12v3a1 1 0 01-1 1H3a1 1 0 01-1-1v-3"/><polyline points="5,8 9,12 13,8"/><line x1="9" y1="12" x2="9" y2="2"/></svg>
								<p class="sb-dl-btn__lbl">JSON Template</p>
								<p class="sb-dl-btn__sub"><?php esc_html_e( 'Import in Elementor', 'stack-blueprint' ); ?></p>
							</a>
							<a href="#" id="sb-dl-css" class="sb-dl-btn" download>
								<svg class="sb-dl-btn__icon" viewBox="0 0 18 18" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M16 12v3a1 1 0 01-1 1H3a1 1 0 01-1-1v-3"/><polyline points="5,8 9,12 13,8"/><line x1="9" y1="12" x2="9" y2="2"/></svg>
								<p class="sb-dl-btn__lbl">Companion CSS</p>
								<p class="sb-dl-btn__sub"><?php esc_html_e( 'Paste into Site Settings', 'stack-blueprint' ); ?></p>
							</a>
						</div>

						<button id="sb-save-tmpl" class="sb-btn sb-btn--ghost sb-btn--full" style="margin-bottom:12px">
							<span class="sb-btn__spin"></span>
							<span class="sb-btn__lbl"><?php esc_html_e( 'Save to Elementor Library', 'stack-blueprint' ); ?></span>
						</button>

						<div id="sb-warnings"></div>
						<div class="sb-div"></div>

						<p class="sb-label" style="margin-bottom:8px"><?php esc_html_e( 'CSS Class Map', 'stack-blueprint' ); ?></p>
						<div class="sb-cmap">
							<table>
								<thead>
									<tr>
										<th><?php esc_html_e( 'Class', 'stack-blueprint' ); ?></th>
										<th><?php esc_html_e( 'Element', 'stack-blueprint' ); ?></th>
										<th><?php esc_html_e( 'Elementor Location', 'stack-blueprint' ); ?></th>
									</tr>
								</thead>
								<tbody id="sb-cmap-body"></tbody>
							</table>
						</div>
					</div>
				</div>
			</div>

		</div><!-- /.left -->

		<!-- ── Right: Sidebar ── -->
		<div id="sb-sidebar-panel">

			<div id="sb-live-preview-card" class="sb-panel sb-preview-card">
				<div class="sb-panel__head">
					<svg width="13" height="13" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="1.5" y="2" width="10" height="8.5" rx="1.2"/><path d="M4 1v2M9 1v2M1.5 4.5h10"/></svg>
					<p class="sb-panel__title"><?php esc_html_e( 'Live Preview', 'stack-blueprint' ); ?></p>
					<span class="sb-panel__sub" id="sb-preview-status"><?php esc_html_e( 'Awaiting conversion', 'stack-blueprint' ); ?></span>
				</div>
				<div class="sb-panel__body">
					<div class="sb-preview-toolbar">
						<div class="sb-preview-devices" role="tablist" aria-label="<?php esc_attr_e( 'Preview device size', 'stack-blueprint' ); ?>">
							<button type="button" class="sb-preview-device is-active" data-preview-device="desktop"><?php esc_html_e( 'Desktop', 'stack-blueprint' ); ?></button>
							<button type="button" class="sb-preview-device" data-preview-device="tablet"><?php esc_html_e( 'Tablet', 'stack-blueprint' ); ?></button>
							<button type="button" class="sb-preview-device" data-preview-device="mobile"><?php esc_html_e( 'Mobile', 'stack-blueprint' ); ?></button>
						</div>
						<div class="sb-preview-actions">
							<button type="button" id="sb-preview-expand" class="sb-btn sb-btn--ghost sb-btn--sm"><?php esc_html_e( 'Fullscreen', 'stack-blueprint' ); ?></button>
							<button type="button" id="sb-preview-refresh" class="sb-btn sb-btn--ghost sb-btn--sm"><?php esc_html_e( 'Refresh', 'stack-blueprint' ); ?></button>
						</div>
					</div>
					<p id="sb-preview-meta" class="sb-preview-meta"><?php esc_html_e( 'Sandboxed preview of the converted Elementor JSON with companion CSS.', 'stack-blueprint' ); ?></p>
					<div id="sb-preview-audits" class="sb-preview-audits"></div>
					<div id="sb-preview-sanitize-report" class="sb-preview-sanitize-report"></div>
					<div class="sb-preview-frame-wrap is-desktop" id="sb-preview-frame-wrap">
						<div id="sb-preview-empty" class="sb-preview-empty">
							<p class="sb-preview-empty__title"><?php esc_html_e( 'No preview yet', 'stack-blueprint' ); ?></p>
							<p class="sb-preview-empty__desc"><?php esc_html_e( 'Run a conversion to inspect the generated layout here without opening Elementor.', 'stack-blueprint' ); ?></p>
						</div>
						<iframe id="sb-preview-frame" class="sb-preview-frame" title="<?php esc_attr_e( 'Converted template preview', 'stack-blueprint' ); ?>" sandbox></iframe>
					</div>
				</div>
			</div>

			<div class="sb-info">
				<p class="sb-info__title"><?php esc_html_e( 'AI Provider Notes', 'stack-blueprint' ); ?></p>
				<ul class="sb-info__list">
					<li><strong style="color:var(--sb-accent)">Claude:</strong> <?php esc_html_e( 'Unmatched accuracy in JSON formatting and Elementor tree structure.', 'stack-blueprint' ); ?></li>
					<li><strong style="color:var(--sb-accent-2)">OpenAI:</strong> <?php esc_html_e( 'Very fast, highly reliable text extraction.', 'stack-blueprint' ); ?></li>
					<li><strong style="color:#4285F4">Gemini:</strong> <?php esc_html_e( 'Best for extremely large HTML files due to its massive context window.', 'stack-blueprint' ); ?></li>
				</ul>
			</div>

			<!-- Design Tokens widget (populated after upload) -->
			<div id="sb-token-widget" class="sb-panel" style="display:none;margin-top:12px">
				<div class="sb-panel__head">
					<svg width="13" height="13" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="1.4"><circle cx="3" cy="3" r="2"/><circle cx="10" cy="3" r="2"/><circle cx="3" cy="10" r="2"/><circle cx="10" cy="10" r="2"/></svg>
					<p class="sb-panel__title"><?php esc_html_e( 'Design Tokens', 'stack-blueprint' ); ?></p>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=stack-blueprint-tokens' ) ); ?>" class="sb-panel__sub" style="color:var(--sb-accent);text-decoration:none;font-size:9px"><?php esc_html_e( 'Full page', 'stack-blueprint' ); ?> &rarr;</a>
				</div>
				<div class="sb-panel__body">
					<p class="sb-label" style="margin-bottom:7px"><?php esc_html_e( 'Colours', 'stack-blueprint' ); ?></p>
					<div class="sb-token-swatches" id="sb-tok-swatches"></div>

					<p class="sb-label" style="margin-bottom:7px;margin-top:12px"><?php esc_html_e( 'Fonts', 'stack-blueprint' ); ?></p>
					<div id="sb-tok-fonts"></div>

					<div class="sb-push-row" style="margin-top:14px">
						<button id="sb-push-tokens" class="sb-btn sb-btn--secondary sb-btn--sm">
							<span class="sb-btn__spin"></span>
							<span class="sb-btn__lbl"><?php esc_html_e( 'Push to Elementor Globals', 'stack-blueprint' ); ?></span>
						</button>
					</div>
					<p class="sb-push-msg" id="sb-push-msg"></p>
				</div>
			</div>

			<!-- After import checklist -->
			<div class="sb-info" style="margin-top:12px">
				<p class="sb-info__title"><?php esc_html_e( 'After import', 'stack-blueprint' ); ?></p>
				<ul class="sb-info__list">
					<li><?php esc_html_e( 'Push design tokens to Elementor Globals (button above, appears after upload).', 'stack-blueprint' ); ?></li>
					<li><?php esc_html_e( 'Paste companion CSS into Elementor Site Settings → Custom CSS.', 'stack-blueprint' ); ?></li>
					<li><?php esc_html_e( 'Verify particle canvas and cursor on the published frontend, not the editor.', 'stack-blueprint' ); ?></li>
					<li><?php esc_html_e( 'Adjust tablet/mobile responsive settings in Elementor panel per section.', 'stack-blueprint' ); ?></li>
				</ul>
			</div>

		</div><!-- /.right -->

	</div><!-- /.sb-conv-grid -->

</div>

<?php require_once SB_ADMIN_PATH . 'partials/layout-close.php'; ?>

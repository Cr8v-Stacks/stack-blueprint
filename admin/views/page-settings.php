<?php
/**
 * Settings Page.
 *
 * @package StackBlueprint
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$sb_page = 'settings';
require_once SB_ADMIN_PATH . 'partials/layout-open.php';

$ant_key = get_option( 'sb_api_key', '' );
$oai_key = get_option( 'sb_openai_key', '' );
$gem_key = get_option( 'sb_gemini_key', '' );

$has_any_key = ! empty($ant_key) || ! empty($oai_key) || ! empty($gem_key);
?>

<div id="sb-settings-page">

	<div class="sb-page-header">
		<p class="sb-eyebrow"><?php esc_html_e( 'Stack Blueprint', 'stack-blueprint' ); ?></p>
		<h1 class="sb-page-title"><?php esc_html_e( 'Settings', 'stack-blueprint' ); ?></h1>
		<p class="sb-page-desc"><?php esc_html_e( 'Configure how Stack Blueprint connects to your preferred AI models.', 'stack-blueprint' ); ?></p>
	</div>

	<div class="sb-settings-layout">

		<!-- Settings form -->
		<div>

			<!-- API Connection Panel -->
			<div class="sb-panel" style="margin-bottom:14px">
				<div class="sb-panel__head">
					<svg width="13" height="13" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="1.4"><circle cx="6.5" cy="6.5" r="5.2"/><path d="M4 6.5a2.5 2.5 0 015 0"/><circle cx="6.5" cy="6.5" r="1"/></svg>
					<p class="sb-panel__title"><?php esc_html_e( 'API Keys', 'stack-blueprint' ); ?></p>
					<div class="sb-topbar__conn" id="sb-api-conn-status">
						<span class="sb-conn-dot <?php echo $has_any_key ? 'live' : ''; ?>" id="sb-api-dot"></span>
						<span id="sb-api-status-txt" style="font-family:var(--sb-font-mono);font-size:9px;color:var(--sb-text-3)">
							<?php echo $has_any_key ? esc_html__( 'Keys Configured', 'stack-blueprint' ) : esc_html__( 'No Keys Configured', 'stack-blueprint' ); ?>
						</span>
					</div>
				</div>
				<div class="sb-panel__body">
					<div class="sb-tab-nav" id="sb-api-tabs">
						<button type="button" class="sb-tab-btn active" data-target="tab-anthropic"><?php esc_html_e( 'Anthropic (Claude)', 'stack-blueprint' ); ?></button>
						<button type="button" class="sb-tab-btn" data-target="tab-openai"><?php esc_html_e( 'OpenAI (ChatGPT)', 'stack-blueprint' ); ?></button>
						<button type="button" class="sb-tab-btn" data-target="tab-gemini"><?php esc_html_e( 'Google Gemini', 'stack-blueprint' ); ?></button>
					</div>

					<!-- Anthropic Key -->
					<div class="sb-tab-content active" id="tab-anthropic">
						<div class="sb-field" style="margin-bottom:24px">
							<label class="sb-label"><?php esc_html_e( 'Anthropic API Key', 'stack-blueprint' ); ?></label>
							
							<?php if ( ! empty( $ant_key ) ) : ?>
								<div class="sb-key-configured" id="sb-ant-configured">
									<span class="sb-key-dot"></span>
									<span><?php printf( esc_html__( 'Key configured ending in ••••%s', 'stack-blueprint' ), esc_html( substr( $ant_key, -4 ) ) ); ?></span>
									<button type="button" class="sb-change-key-btn" data-provider="anthropic"><?php esc_html_e( 'Change Key', 'stack-blueprint' ); ?></button>
									<button type="button" class="sb-remove-key-btn" data-provider="anthropic"><?php esc_html_e( 'Remove', 'stack-blueprint' ); ?></button>
								</div>
							<?php endif; ?>

							<div class="sb-key-edit" id="sb-ant-edit" style="<?php echo ! empty( $ant_key ) ? 'display:none;' : ''; ?>">
								<div class="sb-key-wrap">
									<input type="password" id="sb-api-key" name="sb_api_key" class="sb-input"
										   placeholder="sk-ant-api03-…" autocomplete="off" spellcheck="false" value="<?php echo esc_attr($ant_key); ?>">
									<button type="button" class="sb-toggle-password" title="Show/Hide Password">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
									</button>
								</div>
								<a href="https://console.anthropic.com/settings/keys" target="_blank" class="sb-get-key-link"><?php esc_html_e( 'Get an Anthropic API Key →', 'stack-blueprint' ); ?></a>
							</div>

							<div class="sb-field" style="margin-top:12px">
								<label class="sb-label" for="sb-api-model"><?php esc_html_e( 'Claude Model', 'stack-blueprint' ); ?></label>
								<select id="sb-api-model" name="sb_api_model" class="sb-select">
									<option value="claude-sonnet-4.6" <?php selected( get_option('sb_api_model'), 'claude-sonnet-4.6' ); ?>>Claude 4.6 Sonnet &mdash; <?php esc_html_e( 'Recommended', 'stack-blueprint' ); ?></option>
									<option value="claude-fable-5" <?php selected( get_option('sb_api_model'), 'claude-fable-5' ); ?>>Claude Fable 5</option>
									<option value="claude-opus-4.8" <?php selected( get_option('sb_api_model'), 'claude-opus-4.8' ); ?>>Claude 4.8 Opus</option>
								</select>
							</div>
						</div>
					</div>

					<!-- OpenAI Key -->
					<div class="sb-tab-content" id="tab-openai">
						<div class="sb-field" style="margin-bottom:24px">
							<label class="sb-label"><?php esc_html_e( 'OpenAI API Key', 'stack-blueprint' ); ?></label>
							
							<?php if ( ! empty( $oai_key ) ) : ?>
								<div class="sb-key-configured" id="sb-oai-configured">
									<span class="sb-key-dot"></span>
									<span><?php printf( esc_html__( 'Key configured ending in ••••%s', 'stack-blueprint' ), esc_html( substr( $oai_key, -4 ) ) ); ?></span>
									<button type="button" class="sb-change-key-btn" data-provider="openai"><?php esc_html_e( 'Change Key', 'stack-blueprint' ); ?></button>
									<button type="button" class="sb-remove-key-btn" data-provider="openai"><?php esc_html_e( 'Remove', 'stack-blueprint' ); ?></button>
								</div>
							<?php endif; ?>

							<div class="sb-key-edit" id="sb-oai-edit" style="<?php echo ! empty( $oai_key ) ? 'display:none;' : ''; ?>">
								<div class="sb-key-wrap">
									<input type="password" id="sb-openai-key" name="sb_openai_key" class="sb-input"
										   placeholder="sk-proj-…" autocomplete="off" spellcheck="false" value="<?php echo esc_attr($oai_key); ?>">
									<button type="button" class="sb-toggle-password" title="Show/Hide Password">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
									</button>
								</div>
								<a href="https://platform.openai.com/api-keys" target="_blank" class="sb-get-key-link"><?php esc_html_e( 'Get an OpenAI API Key →', 'stack-blueprint' ); ?></a>
							</div>

							<div class="sb-field" style="margin-top:12px">
								<label class="sb-label" for="sb-openai-model"><?php esc_html_e( 'OpenAI Model', 'stack-blueprint' ); ?></label>
								<select id="sb-openai-model" name="sb_openai_model" class="sb-select">
									<option value="gpt-5.5" <?php selected( get_option('sb_openai_model'), 'gpt-5.5' ); ?>>GPT-5.5 &mdash; <?php esc_html_e( 'Recommended', 'stack-blueprint' ); ?></option>
									<option value="gpt-5.5-pro" <?php selected( get_option('sb_openai_model'), 'gpt-5.5-pro' ); ?>>GPT-5.5 Pro</option>
									<option value="gpt-5.4-mini" <?php selected( get_option('sb_openai_model'), 'gpt-5.4-mini' ); ?>>GPT-5.4 Mini</option>
								</select>
							</div>
						</div>
					</div>

					<!-- Gemini Key -->
					<div class="sb-tab-content" id="tab-gemini">
						<div class="sb-field" style="margin-bottom:16px">
							<label class="sb-label"><?php esc_html_e( 'Google Gemini API Key', 'stack-blueprint' ); ?></label>
							
							<?php if ( ! empty( $gem_key ) ) : ?>
								<div class="sb-key-configured" id="sb-gem-configured">
									<span class="sb-key-dot"></span>
									<span><?php printf( esc_html__( 'Key configured ending in ••••%s', 'stack-blueprint' ), esc_html( substr( $gem_key, -4 ) ) ); ?></span>
									<button type="button" class="sb-change-key-btn" data-provider="gemini"><?php esc_html_e( 'Change Key', 'stack-blueprint' ); ?></button>
									<button type="button" class="sb-remove-key-btn" data-provider="gemini"><?php esc_html_e( 'Remove', 'stack-blueprint' ); ?></button>
								</div>
							<?php endif; ?>

							<div class="sb-key-edit" id="sb-gem-edit" style="<?php echo ! empty( $gem_key ) ? 'display:none;' : ''; ?>">
								<div class="sb-key-wrap">
									<input type="password" id="sb-gemini-key" name="sb_gemini_key" class="sb-input"
										   placeholder="AIzaSy…" autocomplete="off" spellcheck="false" value="<?php echo esc_attr($gem_key); ?>">
									<button type="button" class="sb-toggle-password" title="Show/Hide Password">
										<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
									</button>
								</div>
								<a href="https://aistudio.google.com/app/apikey" target="_blank" class="sb-get-key-link"><?php esc_html_e( 'Get a Gemini API Key →', 'stack-blueprint' ); ?></a>
							</div>

							<div class="sb-field" style="margin-top:12px">
								<label class="sb-label" for="sb-gemini-model"><?php esc_html_e( 'Gemini Model', 'stack-blueprint' ); ?></label>
								<select id="sb-gemini-model" name="sb_gemini_model" class="sb-select">
									<option value="gemini-3.5-flash" <?php selected( get_option('sb_gemini_model'), 'gemini-3.5-flash' ); ?>>Gemini 3.5 Flash &mdash; <?php esc_html_e( 'Recommended', 'stack-blueprint' ); ?></option>
									<option value="gemini-3.5-pro" <?php selected( get_option('sb_gemini_model'), 'gemini-3.5-pro' ); ?>>Gemini 3.5 Pro</option>
									<option value="gemini-3.1-flash-lite" <?php selected( get_option('sb_gemini_model'), 'gemini-3.1-flash-lite' ); ?>>Gemini 3.1 Flash Lite</option>
								</select>
							</div>
						</div>
					</div>

				</div>
			</div>

			<!-- Conversion Defaults -->
			<div class="sb-panel" style="margin-bottom:14px">
				<div class="sb-panel__head">
					<svg width="13" height="13" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="1.4"><rect x="0.7" y="0.7" width="5" height="5" rx="0.6"/><rect x="7.3" y="0.7" width="5" height="2.7" rx="0.6"/><rect x="7.3" y="5" width="5" height="2.7" rx="0.6"/><rect x="0.7" y="7.3" width="5" height="5" rx="0.6"/></svg>
					<p class="sb-panel__title"><?php esc_html_e( 'Conversion Defaults', 'stack-blueprint' ); ?></p>
				</div>
				<div class="sb-panel__body">
					<div class="sb-field-row">
						<div class="sb-field">
							<label class="sb-label" for="sb-default-strategy"><?php esc_html_e( 'Default Strategy', 'stack-blueprint' ); ?></label>
							<select id="sb-default-strategy" class="sb-select">
								<option value="v2"><?php esc_html_e( 'V2 — Native Components (Recommended)', 'stack-blueprint' ); ?></option>
								<option value="v1"><?php esc_html_e( 'V1 — HTML Fidelity', 'stack-blueprint' ); ?></option>
							</select>
						</div>
						<div class="sb-field">
							<label class="sb-label" for="sb-max-size"><?php esc_html_e( 'Max Upload Size (MB)', 'stack-blueprint' ); ?></label>
							<input type="number" id="sb-max-size" class="sb-input" min="1" max="20" step="1" style="max-width:100px" value="<?php echo esc_attr( get_option('sb_max_upload_size', 5) ); ?>">
						</div>
					</div>
					<p class="sb-hint" style="margin-top:0"><?php esc_html_e( 'The CSS prefix is auto-detected per-conversion from the uploaded file. No need to set a global default.', 'stack-blueprint' ); ?></p>
				</div>
			</div>

			<button id="sb-save-settings" class="sb-btn sb-btn--primary sb-btn--lg">
				<span class="sb-btn__spin"></span>
				<span class="sb-btn__lbl"><?php esc_html_e( 'Save Settings', 'stack-blueprint' ); ?></span>
			</button>

		</div>

		<!-- Info sidebar -->
		<div>

			<div class="sb-info">
				<p class="sb-info__title"><?php esc_html_e( 'Which API to choose?', 'stack-blueprint' ); ?></p>
				<ul class="sb-info__list">
					<li><strong style="color:var(--sb-accent)"><?php esc_html_e( 'Claude 3.5 Sonnet:', 'stack-blueprint' ); ?></strong> <?php esc_html_e( 'The absolute best model for frontend code interpretation and JSON generation.', 'stack-blueprint' ); ?></li>
					<li><strong style="color:var(--sb-accent-2)"><?php esc_html_e( 'GPT-4o:', 'stack-blueprint' ); ?></strong> <?php esc_html_e( 'Highly capable and fast, great for standard conversions.', 'stack-blueprint' ); ?></li>
					<li><strong style="color:#4285F4"><?php esc_html_e( 'Gemini 1.5 Pro:', 'stack-blueprint' ); ?></strong> <?php esc_html_e( 'Huge context window (up to 2M tokens), perfect for massive HTML pages that would otherwise require chunking.', 'stack-blueprint' ); ?></li>
				</ul>
			</div>

			<div class="sb-info">
				<p class="sb-info__title"><?php esc_html_e( 'Plugin', 'stack-blueprint' ); ?></p>
				<ul class="sb-info__list">
					<li><?php printf( esc_html__( 'Version: %s', 'stack-blueprint' ), esc_html( SB_VERSION ) ); ?></li>
					<li><?php printf( esc_html__( 'PHP: %s', 'stack-blueprint' ), esc_html( PHP_VERSION ) ); ?></li>
					<li><?php printf( esc_html__( 'WordPress: %s', 'stack-blueprint' ), esc_html( get_bloginfo( 'version' ) ) ); ?></li>
					<li><?php printf( esc_html__( 'Author: %s', 'stack-blueprint' ), '<a href="https://cr8vstacks.com" target="_blank" rel="noopener" style="color:var(--sb-accent)">Cr8v Stacks</a>' ); ?></li>
				</ul>
			</div>

		</div>

	</div>

</div>

<?php require_once SB_ADMIN_PATH . 'partials/layout-close.php'; ?>

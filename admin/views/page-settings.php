<?php
/**
 * Settings Page.
 *
 * @package StackBlueprint
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$sb_page = 'settings';
require_once SB_ADMIN_PATH . 'partials/layout-open.php';

$api_key_set = ! empty( get_option( 'sb_api_key' ) );
$api_mode    = get_option( 'sb_api_mode', 'own' );
?>

<div id="sb-settings-page">

	<div class="sb-page-header">
		<p class="sb-eyebrow"><?php esc_html_e( 'Stack Blueprint', 'stack-blueprint' ); ?></p>
		<h1 class="sb-page-title"><?php esc_html_e( 'Settings', 'stack-blueprint' ); ?></h1>
		<p class="sb-page-desc"><?php esc_html_e( 'Configure how Stack Blueprint connects to Claude AI and handles conversions.', 'stack-blueprint' ); ?></p>
	</div>

	<div class="sb-settings-layout">

		<!-- Settings form -->
		<div>

			<!-- API Connection Panel -->
			<div class="sb-panel" style="margin-bottom:14px">
				<div class="sb-panel__head">
					<svg width="13" height="13" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="1.4"><circle cx="6.5" cy="6.5" r="5.2"/><path d="M4 6.5a2.5 2.5 0 015 0"/><circle cx="6.5" cy="6.5" r="1"/></svg>
					<p class="sb-panel__title"><?php esc_html_e( 'API Connection', 'stack-blueprint' ); ?></p>
					<div class="sb-topbar__conn" id="sb-api-conn-status">
						<span class="sb-conn-dot <?php echo $api_key_set || 'builtin' === $api_mode ? 'live' : ''; ?>" id="sb-api-dot"></span>
						<span id="sb-api-status-txt" style="font-family:var(--sb-font-mono);font-size:9px;color:var(--sb-text-3)">
							<?php echo $api_key_set || 'builtin' === $api_mode ? esc_html__( 'Connected', 'stack-blueprint' ) : esc_html__( 'Not connected', 'stack-blueprint' ); ?>
						</span>
					</div>
				</div>
				<div class="sb-panel__body">

					<!-- Mode tabs -->
					<div class="sb-mode-tabs" id="sb-api-mode-tabs">
						<button class="sb-mode-tab<?php echo 'own' === $api_mode ? ' is-active' : ''; ?>" data-mode="own"><?php esc_html_e( 'My Own API Key', 'stack-blueprint' ); ?></button>
						<button class="sb-mode-tab<?php echo 'builtin' === $api_mode ? ' is-active' : ''; ?>" data-mode="builtin"><?php esc_html_e( 'Built-in (Cr8v Stacks)', 'stack-blueprint' ); ?></button>
					</div>
					<input type="hidden" id="sb-api-mode-val" value="<?php echo esc_attr( $api_mode ); ?>">

					<!-- Own key panel -->
					<div class="sb-mode-panel<?php echo 'own' === $api_mode ? ' is-active' : ''; ?>" id="sb-mode-own">
						<div class="sb-field" style="margin-bottom:14px">
							<label class="sb-label" for="sb-api-key"><?php esc_html_e( 'Anthropic API Key', 'stack-blueprint' ); ?></label>
							<div class="sb-key-wrap">
								<input type="password" id="sb-api-key" class="sb-input"
									   placeholder="sk-ant-api03-…" autocomplete="off" spellcheck="false">
								<button type="button" class="sb-key-toggle" aria-label="<?php esc_attr_e( 'Toggle visibility', 'stack-blueprint' ); ?>">
									<svg width="15" height="15" viewBox="0 0 15 15" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M1 7.5s2.5-4.5 6.5-4.5 6.5 4.5 6.5 4.5-2.5 4.5-6.5 4.5-6.5-4.5-6.5-4.5z"/><circle cx="7.5" cy="7.5" r="1.8"/></svg>
								</button>
							</div>
							<p class="sb-hint">
								<?php printf(
									/* translators: %s: Anthropic console URL */
									esc_html__( 'Get your key at %s. Stored securely in WordPress options.', 'stack-blueprint' ),
									'<a href="https://console.anthropic.com" target="_blank" rel="noopener" style="color:var(--sb-accent)">console.anthropic.com</a>'
								); ?>
							</p>
						</div>
						<div class="sb-field" style="margin-bottom:16px">
							<label class="sb-label" for="sb-api-model"><?php esc_html_e( 'Model', 'stack-blueprint' ); ?></label>
							<select id="sb-api-model" class="sb-select">
								<option value="claude-sonnet-4-20250514">claude-sonnet-4 &mdash; <?php esc_html_e( 'Recommended', 'stack-blueprint' ); ?></option>
								<option value="claude-opus-4-5">claude-opus-4 &mdash; <?php esc_html_e( 'Most capable, slower', 'stack-blueprint' ); ?></option>
								<option value="claude-haiku-4-5-20251001">claude-haiku-4 &mdash; <?php esc_html_e( 'Fast, lower cost', 'stack-blueprint' ); ?></option>
							</select>
						</div>
						<button id="sb-test-key" class="sb-btn sb-btn--ghost">
							<span class="sb-btn__spin"></span>
							<span class="sb-btn__lbl"><?php esc_html_e( 'Test Connection', 'stack-blueprint' ); ?></span>
						</button>
					</div>

					<!-- Built-in panel -->
					<div class="sb-mode-panel<?php echo 'builtin' === $api_mode ? ' is-active' : ''; ?>" id="sb-mode-builtin">
						<div class="sb-notice sb-notice--info" style="margin-bottom:0">
							<svg class="sb-notice__icon" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="1.4"><circle cx="6.5" cy="6.5" r="5.2"/><line x1="6.5" y1="5" x2="6.5" y2="9"/><circle cx="6.5" cy="3.5" r="0.5" fill="currentColor"/></svg>
							<div class="sb-notice__body">
								<strong><?php esc_html_e( 'Cr8v Stacks Managed API', 'stack-blueprint' ); ?></strong>
								<?php esc_html_e( 'Use our API pool — no Anthropic account needed. A small per-conversion fee applies. Conversions are processed via cr8vstacks.com. No prototype content is stored.', 'stack-blueprint' ); ?>
							</div>
						</div>
						<p style="margin-top:12px;font-size:12px;color:var(--sb-text-2)">
							<?php printf(
								/* translators: %s: cr8vstacks.com pricing URL */
								esc_html__( 'See %s for pricing and fair-use details.', 'stack-blueprint' ),
								'<a href="https://cr8vstacks.com/stack-blueprint/pricing" target="_blank" rel="noopener" style="color:var(--sb-accent)">cr8vstacks.com/stack-blueprint/pricing</a>'
							); ?>
						</p>
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
							<input type="number" id="sb-max-size" class="sb-input" min="1" max="20" step="1" style="max-width:100px">
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
				<p class="sb-info__title"><?php esc_html_e( 'Which API mode?', 'stack-blueprint' ); ?></p>
				<ul class="sb-info__list">
					<li><strong style="color:var(--sb-accent)"><?php esc_html_e( 'Own Key:', 'stack-blueprint' ); ?></strong> <?php esc_html_e( 'Full control, pay Anthropic directly. Best for agencies running many conversions.', 'stack-blueprint' ); ?></li>
					<li><strong style="color:var(--sb-accent-2)"><?php esc_html_e( 'Built-in:', 'stack-blueprint' ); ?></strong> <?php esc_html_e( 'Zero setup — no Anthropic account needed. Per-conversion fee billed through Cr8v Stacks.', 'stack-blueprint' ); ?></li>
				</ul>
			</div>

			<div class="sb-info">
				<p class="sb-info__title"><?php esc_html_e( 'Getting an Anthropic key', 'stack-blueprint' ); ?></p>
				<ul class="sb-info__list">
					<li><?php esc_html_e( 'Go to console.anthropic.com and sign in or create an account.', 'stack-blueprint' ); ?></li>
					<li><?php esc_html_e( 'Navigate to API Keys → Create Key.', 'stack-blueprint' ); ?></li>
					<li><?php esc_html_e( 'Copy the key and paste it in the field on the left.', 'stack-blueprint' ); ?></li>
				</ul>
			</div>

			<div class="sb-info">
				<p class="sb-info__title"><?php esc_html_e( 'Model guidance', 'stack-blueprint' ); ?></p>
				<ul class="sb-info__list">
					<li><strong style="color:var(--sb-accent)">Sonnet 4:</strong> <?php esc_html_e( 'Best balance of quality, speed, and cost for most conversions.', 'stack-blueprint' ); ?></li>
					<li><strong style="color:var(--sb-accent)">Opus 4:</strong> <?php esc_html_e( 'Use for very complex multi-section prototypes. Slower.', 'stack-blueprint' ); ?></li>
					<li><strong style="color:var(--sb-accent)">Haiku 4:</strong> <?php esc_html_e( 'Fast and cheap. Good for simple single-section conversions.', 'stack-blueprint' ); ?></li>
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

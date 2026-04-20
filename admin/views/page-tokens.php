<?php
/**
 * Design Tokens Page.
 *
 * @package StackBlueprint
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$sb_page = 'tokens';
require_once SB_ADMIN_PATH . 'partials/layout-open.php';

$elementor_active = class_exists( '\Elementor\Plugin' );
?>

<div id="sb-tokens-page">

	<div class="sb-page-header">
		<p class="sb-eyebrow"><?php esc_html_e( 'Stack Blueprint', 'stack-blueprint' ); ?></p>
		<h1 class="sb-page-title"><?php esc_html_e( 'Design Tokens', 'stack-blueprint' ); ?></h1>
		<p class="sb-page-desc"><?php esc_html_e( 'Extract your prototype\'s colour palette and font stack, then push them directly to Elementor\'s Global Colors and Global Fonts — so every widget already matches your brand before you import the template.', 'stack-blueprint' ); ?></p>
	</div>

	<?php if ( ! $elementor_active ) : ?>
	<div class="sb-notice sb-notice--warn" style="margin-bottom:20px">
		<svg class="sb-notice__icon" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M6.5 1L12 12H1L6.5 1z"/><line x1="6.5" y1="5" x2="6.5" y2="8"/><circle cx="6.5" cy="10" r="0.5" fill="currentColor"/></svg>
		<div class="sb-notice__body">
			<strong><?php esc_html_e( 'Elementor not active', 'stack-blueprint' ); ?></strong>
			<?php esc_html_e( 'Token extraction works without Elementor, but the "Push to Globals" button requires Elementor to be installed and active.', 'stack-blueprint' ); ?>
		</div>
	</div>
	<?php endif; ?>

	<div class="sb-tokens-layout">

		<!-- Input + Output -->
		<div>

			<!-- Paste zone -->
			<div class="sb-panel" style="margin-bottom:14px">
				<div class="sb-panel__head">
					<svg width="13" height="13" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M2 2h5l3 3v6H2z"/><polyline points="7,2 7,5 10,5"/></svg>
					<p class="sb-panel__title"><?php esc_html_e( 'Paste HTML Prototype', 'stack-blueprint' ); ?></p>
				</div>
				<div class="sb-panel__body">
					<div class="sb-field" style="margin-bottom:14px">
						<label class="sb-label" for="sb-tok-html"><?php esc_html_e( 'HTML Source', 'stack-blueprint' ); ?></label>
						<textarea id="sb-tok-html" class="sb-input sb-textarea" placeholder="<?php esc_attr_e( 'Paste your full HTML prototype here…', 'stack-blueprint' ); ?>"></textarea>
					</div>
					<button id="sb-extract-btn" class="sb-btn sb-btn--primary">
						<span class="sb-btn__spin"></span>
						<span class="sb-btn__lbl"><?php esc_html_e( 'Extract Tokens', 'stack-blueprint' ); ?></span>
					</button>
				</div>
			</div>

			<!-- Colour Output -->
			<div id="sb-colors-panel" class="sb-panel" style="display:none;margin-bottom:14px">
				<div class="sb-panel__head">
					<p class="sb-panel__title"><?php esc_html_e( 'Colour Palette', 'stack-blueprint' ); ?></p>
					<span class="sb-panel__sub" id="sb-color-count"></span>
				</div>
				<div class="sb-panel__body">
					<div class="sb-token-grid" id="sb-color-grid"></div>

					<div class="sb-div"></div>

					<p class="sb-label" style="margin-bottom:10px"><?php esc_html_e( 'Map to Elementor Global Token Names', 'stack-blueprint' ); ?></p>
					<p class="sb-hint" style="margin-bottom:12px"><?php esc_html_e( 'Assign each colour a semantic role. These names appear in Elementor\'s colour picker.', 'stack-blueprint' ); ?></p>
					<div id="sb-color-map-rows"></div>
				</div>
			</div>

			<!-- Font Output -->
			<div id="sb-fonts-panel" class="sb-panel" style="display:none;margin-bottom:14px">
				<div class="sb-panel__head">
					<p class="sb-panel__title"><?php esc_html_e( 'Typography', 'stack-blueprint' ); ?></p>
					<span class="sb-panel__sub" id="sb-font-count"></span>
				</div>
				<div class="sb-panel__body">
					<div id="sb-font-list"></div>
					<div class="sb-div"></div>
					<p class="sb-hint"><?php esc_html_e( 'These fonts will be added to Elementor\'s Global Fonts with the role you assign. Make sure each font is available via Google Fonts or your theme.', 'stack-blueprint' ); ?></p>
					<div id="sb-font-map-rows" style="margin-top:10px"></div>
				</div>
			</div>

			<!-- Push CTA -->
			<div id="sb-push-panel" style="display:none">
				<button id="sb-push-globals" class="sb-btn sb-btn--secondary sb-btn--lg sb-btn--full" <?php echo ! $elementor_active ? 'disabled' : ''; ?>>
					<span class="sb-btn__spin"></span>
					<span class="sb-btn__lbl">
						<svg width="13" height="13" viewBox="0 0 13 13" fill="none" stroke="currentColor" stroke-width="1.8" style="margin-right:5px;vertical-align:middle"><path d="M6.5 1v8M3 5l3.5-4 3.5 4"/><path d="M2 10h9"/></svg>
						<?php esc_html_e( 'Push to Elementor Global Colors & Fonts', 'stack-blueprint' ); ?>
					</span>
				</button>
				<p id="sb-push-result" style="margin-top:8px;font-family:var(--sb-font-mono);font-size:10px;color:var(--sb-text-3);min-height:14px"></p>

				<?php if ( ! $elementor_active ) : ?>
				<p class="sb-hint" style="color:var(--sb-amber);margin-top:6px"><?php esc_html_e( 'Elementor must be active to push globals.', 'stack-blueprint' ); ?></p>
				<?php endif; ?>
			</div>

		</div><!-- /left -->

		<!-- Info sidebar -->
		<div>

			<div class="sb-info">
				<p class="sb-info__title"><?php esc_html_e( 'Why do this first?', 'stack-blueprint' ); ?></p>
				<ul class="sb-info__list">
					<li><?php esc_html_e( 'Elementor\'s Global Colors apply automatically to any widget that references them — including imported Stack Blueprint templates.', 'stack-blueprint' ); ?></li>
					<li><?php esc_html_e( 'Setting fonts as globals ensures consistent typography across every new section you build after import.', 'stack-blueprint' ); ?></li>
					<li><?php esc_html_e( 'The Push button writes directly to Elementor\'s kit settings — no manual entry needed.', 'stack-blueprint' ); ?></li>
				</ul>
			</div>

			<div class="sb-info">
				<p class="sb-info__title"><?php esc_html_e( 'Recommended token names', 'stack-blueprint' ); ?></p>
				<div class="sb-cmap" style="margin-top:8px">
					<table>
						<thead>
							<tr>
								<th><?php esc_html_e( 'Name', 'stack-blueprint' ); ?></th>
								<th><?php esc_html_e( 'Use', 'stack-blueprint' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$rows = [
								[ 'Brand Primary',    __( 'Main accent / CTA', 'stack-blueprint' ) ],
								[ 'Brand Background', __( 'Page background', 'stack-blueprint' ) ],
								[ 'Brand Text',       __( 'Primary text', 'stack-blueprint' ) ],
								[ 'Brand Surface',    __( 'Card backgrounds', 'stack-blueprint' ) ],
								[ 'Brand Border',     __( 'Borders / strokes', 'stack-blueprint' ) ],
								[ 'Font Display',     __( 'Headline typeface', 'stack-blueprint' ) ],
								[ 'Font Body',        __( 'Body text', 'stack-blueprint' ) ],
								[ 'Font Mono',        __( 'Labels / code', 'stack-blueprint' ) ],
							];
							foreach ( $rows as $r ) : ?>
							<tr>
								<td class="cls"><?php echo esc_html( $r[0] ); ?></td>
								<td><?php echo esc_html( $r[1] ); ?></td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</div>

			<div class="sb-info">
				<p class="sb-info__title"><?php esc_html_e( 'Manual setup (alternative)', 'stack-blueprint' ); ?></p>
				<ul class="sb-info__list">
					<li><?php esc_html_e( 'Open any page in Elementor → hamburger menu → Site Settings.', 'stack-blueprint' ); ?></li>
					<li><?php esc_html_e( 'Go to Global Colors → add each colour from the palette above.', 'stack-blueprint' ); ?></li>
					<li><?php esc_html_e( 'Go to Global Fonts → add each font family.', 'stack-blueprint' ); ?></li>
					<li><?php esc_html_e( 'Save, then import your converted template.', 'stack-blueprint' ); ?></li>
				</ul>
			</div>

		</div><!-- /sidebar -->

	</div>

</div>

<?php require_once SB_ADMIN_PATH . 'partials/layout-close.php'; ?>

<?php
/**
 * History Page View.
 *
 * @package StackBlueprint
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$sb_page  = 'history';
$sb_title = __( 'Conversion History', 'stack-blueprint' );

require_once SB_ADMIN_PATH . 'partials/layout-open.php';
?>

<div id="sb-history-page">

	<div class="sb-page-header">
		<p class="sb-page-eyebrow"><?php esc_html_e( 'Stack Blueprint', 'stack-blueprint' ); ?></p>
		<h1 class="sb-page-title"><?php esc_html_e( 'Conversion History', 'stack-blueprint' ); ?></h1>
		<p class="sb-page-desc"><?php esc_html_e( 'All past conversions. Re-download any previous output or review conversion warnings.', 'stack-blueprint' ); ?></p>
	</div>

	<div class="sb-panel">
		<div class="sb-panel__header">
			<svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.4" xmlns="http://www.w3.org/2000/svg"><circle cx="8" cy="8" r="6.5"/><polyline points="8,4.5 8,8 10.5,10"/></svg>
			<p class="sb-panel__title"><?php esc_html_e( 'Recent Conversions', 'stack-blueprint' ); ?></p>
			<span style="font-family:var(--sb-font-mono);font-size:10px;color:var(--sb-text-3)"><?php esc_html_e( 'Last 20', 'stack-blueprint' ); ?></span>
		</div>
		<div class="sb-panel__body" style="padding:0">
			<div class="sb-table-wrap">
				<table class="sb-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Project', 'stack-blueprint' ); ?></th>
							<th><?php esc_html_e( 'Strategy', 'stack-blueprint' ); ?></th>
							<th><?php esc_html_e( 'Status', 'stack-blueprint' ); ?></th>
							<th><?php esc_html_e( 'Prefix', 'stack-blueprint' ); ?></th>
							<th><?php esc_html_e( 'Date', 'stack-blueprint' ); ?></th>
							<th><?php esc_html_e( 'Downloads', 'stack-blueprint' ); ?></th>
						</tr>
					</thead>
					<tbody id="sb-history-tbody">
						<tr>
							<td colspan="6">
								<div class="sb-empty-state">
									<svg class="sb-empty-state__icon" viewBox="0 0 48 48" fill="none" stroke="currentColor" stroke-width="1.2" xmlns="http://www.w3.org/2000/svg"><circle cx="24" cy="24" r="20"/><polyline points="24,12 24,24 31,30"/></svg>
									<p class="sb-empty-state__title"><?php esc_html_e( 'No conversions yet', 'stack-blueprint' ); ?></p>
									<p class="sb-empty-state__desc"><?php esc_html_e( 'Your conversion history will appear here once you run your first conversion.', 'stack-blueprint' ); ?></p>
								</div>
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</div>
	</div>

</div><!-- /#sb-history-page -->

<?php require_once SB_ADMIN_PATH . 'partials/layout-close.php'; ?>

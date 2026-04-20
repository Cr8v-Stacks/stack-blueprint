<?php
/**
 * Admin layout wrapper — open.
 *
 * @package StackBlueprint
 */

if ( ! defined( 'ABSPATH' ) ) exit;

$sb_api_set   = ! empty( get_option( 'sb_api_key' ) );
$sb_api_mode  = get_option( 'sb_api_mode', 'own' ); // 'own' | 'builtin'
$sb_connected = $sb_api_set || 'builtin' === $sb_api_mode;

$sb_current_page = $sb_page ?? 'converter';

$sb_nav = [
	'converter' => [
		'label' => __( 'Converter', 'stack-blueprint' ),
		'href'  => admin_url( 'admin.php?page=stack-blueprint' ),
		'icon'  => '<svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.4" xmlns="http://www.w3.org/2000/svg"><rect x="0.7" y="0.7" width="5.3" height="5.3" rx="0.8"/><rect x="8" y="0.7" width="5.3" height="3" rx="0.8"/><rect x="8" y="5.3" width="5.3" height="3" rx="0.8"/><rect x="0.7" y="8" width="5.3" height="5.3" rx="0.8"/><rect x="8" y="10" width="5.3" height="3.3" rx="0.8"/></svg>',
	],
	'history'   => [
		'label' => __( 'History', 'stack-blueprint' ),
		'href'  => admin_url( 'admin.php?page=stack-blueprint-history' ),
		'icon'  => '<svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.4" xmlns="http://www.w3.org/2000/svg"><circle cx="7" cy="7" r="5.8"/><polyline points="7,3.8 7,7 9,9"/></svg>',
	],
	'tokens'    => [
		'label' => __( 'Design Tokens', 'stack-blueprint' ),
		'href'  => admin_url( 'admin.php?page=stack-blueprint-tokens' ),
		'icon'  => '<svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.4" xmlns="http://www.w3.org/2000/svg"><circle cx="3.5" cy="3.5" r="2.2"/><circle cx="10.5" cy="3.5" r="2.2"/><circle cx="3.5" cy="10.5" r="2.2"/><circle cx="10.5" cy="10.5" r="2.2"/></svg>',
	],
	'settings'  => [
		'label' => __( 'Settings', 'stack-blueprint' ),
		'href'  => admin_url( 'admin.php?page=stack-blueprint-settings' ),
		'icon'  => '<svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.4" xmlns="http://www.w3.org/2000/svg"><circle cx="7" cy="7" r="1.8"/><path d="M7 1v1.4M7 11.6V13M1 7h1.4M11.6 7H13M2.8 2.8l1 1M10.2 10.2l1 1M11.2 2.8l-1 1M3.8 10.2l-1 1"/></svg>',
	],
];
?>
<div class="sb-app">

	<!-- ── Topbar ── -->
	<header class="sb-topbar">
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=stack-blueprint' ) ); ?>" class="sb-topbar__logo">
			<span class="sb-topbar__mark" aria-hidden="true">
				<span></span><span></span><span></span>
			</span>
			Stack Blueprint
		</a>
		<div class="sb-topbar__right">
			<span class="sb-topbar__badge">v<?php echo esc_html( SB_VERSION ); ?></span>
			<span class="sb-topbar__spacer"></span>
			<div class="sb-topbar__conn">
				<span class="sb-conn-dot <?php echo $sb_connected ? 'live' : ''; ?>" id="sb-conn-dot"></span>
				<span id="sb-conn-text"><?php echo $sb_connected ? esc_html__( 'API Ready', 'stack-blueprint' ) : esc_html__( 'API Not Set', 'stack-blueprint' ); ?></span>
			</div>
		</div>
	</header>

	<!-- ── Sidebar ── -->
	<nav class="sb-sidebar" aria-label="<?php esc_attr_e( 'Stack Blueprint navigation', 'stack-blueprint' ); ?>">

		<p class="sb-nav-group-label"><?php esc_html_e( 'Tools', 'stack-blueprint' ); ?></p>

		<?php foreach ( [ 'converter', 'history', 'tokens' ] as $key ) :
			$item = $sb_nav[ $key ];
		?>
		<a href="<?php echo esc_url( $item['href'] ); ?>"
		   class="sb-nav-link<?php echo $sb_current_page === $key ? ' is-active' : ''; ?>">
			<span class="sb-nav-icon"><?php echo $item['icon']; // phpcs:ignore ?></span>
			<?php echo esc_html( $item['label'] ); ?>
		</a>
		<?php endforeach; ?>

		<div class="sb-nav-sep"></div>
		<p class="sb-nav-group-label"><?php esc_html_e( 'Config', 'stack-blueprint' ); ?></p>

		<a href="<?php echo esc_url( $sb_nav['settings']['href'] ); ?>"
		   class="sb-nav-link<?php echo 'settings' === $sb_current_page ? ' is-active' : ''; ?>">
			<span class="sb-nav-icon"><?php echo $sb_nav['settings']['icon']; // phpcs:ignore ?></span>
			<?php echo esc_html( $sb_nav['settings']['label'] ); ?>
		</a>

		<div class="sb-nav-sep"></div>
		<p class="sb-nav-group-label"><?php esc_html_e( 'Links', 'stack-blueprint' ); ?></p>

		<a href="https://cr8vstacks.com/stack-blueprint/docs" target="_blank" rel="noopener" class="sb-nav-link">
			<span class="sb-nav-icon">
				<svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M2 2h6l3 3v7H2z"/><line x1="4" y1="6" x2="9" y2="6"/><line x1="4" y1="8.5" x2="9" y2="8.5"/></svg>
			</span>
			<?php esc_html_e( 'Docs', 'stack-blueprint' ); ?>
		</a>

		<a href="https://cr8vstacks.com" target="_blank" rel="noopener" class="sb-nav-link">
			<span class="sb-nav-icon">
				<svg viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.4"><circle cx="7" cy="7" r="5.8"/><path d="M4.5 7c0-2.7 1.1-4.8 2.5-4.8S9.5 4.3 9.5 7s-1.1 4.8-2.5 4.8S4.5 9.7 4.5 7z"/><line x1="1.2" y1="7" x2="12.8" y2="7"/></svg>
			</span>
			<?php esc_html_e( 'Cr8v Stacks', 'stack-blueprint' ); ?>
		</a>

		<div class="sb-sidebar__push"></div>

		<div class="sb-sidebar__foot">
			<p class="sb-sidebar__by">
				<?php esc_html_e( 'By', 'stack-blueprint' ); ?>
				<a href="https://cr8vstacks.com" target="_blank" rel="noopener">Cr8v Stacks</a>
			</p>
		</div>

	</nav>

	<!-- ── Main ── -->
	<main class="sb-main">

<?php

namespace {
	$wp_root = dirname( __DIR__, 4 );
	if ( ! is_dir( $wp_root . '/wp-includes' ) ) {
		fwrite( STDOUT, "WP root not found at: {$wp_root}\n" );
		exit( 3 );
	}

	define( 'ABSPATH', $wp_root . '/' );
	define( 'WPINC', 'wp-includes' );

	if ( ! function_exists( 'do_action' ) ) {
		function do_action( ...$args ) { return null; }
	}
	if ( ! function_exists( 'apply_filters' ) ) {
		function apply_filters( $tag, $value ) { return $value; }
	}

	require ABSPATH . 'wp-includes/class-wp-error.php';

	if ( ! function_exists( 'is_wp_error' ) ) {
		function is_wp_error( $thing ) { return $thing instanceof \WP_Error; }
	}

	$plugin_root = dirname( __DIR__ );
	require $plugin_root . '/includes/converter/class-browser-extractor.php';

	$args = $_SERVER['argv'] ?? [];
	$get_arg = static function( string $name, ?string $default = null ) use ( $args ): ?string {
		$index = array_search( $name, $args, true );
		return ( false !== $index && isset( $args[ $index + 1 ] ) ) ? (string) $args[ $index + 1 ] : $default;
	};

	$file = $get_arg( '--file', '' );
	$file = realpath( (string) $file ) ?: (string) $file;
	if ( '' === $file || ! is_file( $file ) ) {
		fwrite( STDOUT, "Usage: php tools/run-browser-extraction-cli.php --file <html-path> [--output-base <slug>]\n" );
		exit( 2 );
	}

	$output_base = (string) $get_arg( '--output-base', '' );
	if ( '' === $output_base ) {
		$output_base = preg_replace( '/[^a-z0-9._-]/', '-', strtolower( basename( $file, '.html' ) . '-browser-truth' ) );
	}

	$extractor = new \StackBlueprint\Converter\BrowserExtractor( $plugin_root );
	$status    = $extractor->get_runtime_status();

	$out_dir = $plugin_root . '/audit-output';
	if ( ! is_dir( $out_dir ) ) {
		mkdir( $out_dir, 0777, true );
	}

	$status_path = $out_dir . '/' . $output_base . '-status.json';
	file_put_contents( $status_path, json_encode( $status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

	if ( empty( $status['available'] ) ) {
		$result = [
			'ok'      => false,
			'message' => 'Browser extractor runtime is not available.',
			'status'  => $status,
			'outputs' => [
				'status' => $status_path,
			],
		];
		fwrite( STDOUT, json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL );
		exit( 1 );
	}

	$result = $extractor->extract_file(
		$file,
		[
			'base_dir' => realpath( dirname( $file ) ) ?: dirname( $file ),
		]
	);

	if ( is_wp_error( $result ) ) {
		$out = [
			'ok'      => false,
			'message' => $result->get_error_message(),
			'error'   => [
				'code' => $result->get_error_code(),
				'data' => $result->get_error_data(),
			],
			'outputs' => [
				'status' => $status_path,
			],
		];
		fwrite( STDOUT, json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL );
		exit( 1 );
	}

	$output_path  = $out_dir . '/' . $output_base . '.json';
	$summary_path = $out_dir . '/' . $output_base . '.md';

	file_put_contents( $output_path, json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );
	file_put_contents( $summary_path, build_summary_markdown( $file, $result, $status_path, $output_path ) );

	$out = [
		'ok'      => true,
		'source'  => $file,
		'outputs' => [
			'status'  => $status_path,
			'json'    => $output_path,
			'summary' => $summary_path,
		],
	];

	fwrite( STDOUT, json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . PHP_EOL );

	function build_summary_markdown( string $source, array $result, string $status_path, string $json_path ): string {
		$lines   = [];
		$lines[] = '# Browser Truth Extraction Audit';
		$lines[] = '';
		$lines[] = '- Source: `' . $source . '`';
		$lines[] = '- Status JSON: `' . $status_path . '`';
		$lines[] = '- Extracted JSON: `' . $json_path . '`';
		$lines[] = '- Title: `' . (string) ( $result['title'] ?? '' ) . '`';
		$lines[] = '- Primary viewport: `' . (string) ( $result['primaryViewport'] ?? '' ) . '`';
		$lines[] = '- Fonts detected: `' . implode( ', ', array_map( 'strval', (array) ( $result['fonts'] ?? [] ) ) ) . '`';
		$lines[] = '- Design token count: `' . (string) count( (array) ( $result['designTokens'] ?? [] ) ) . '`';
		$lines[] = '';
		$lines[] = '## Viewports';
		$lines[] = '';

		foreach ( (array) ( $result['viewports'] ?? [] ) as $name => $viewport ) {
			$metrics = (array) ( $viewport['documentMetrics'] ?? [] );
			$stats   = (array) ( $viewport['stats'] ?? [] );
			$lines[] = '### ' . $name;
			$lines[] = '';
			$lines[] = '- Size: `' . (string) ( $viewport['viewport']['width'] ?? 0 ) . 'x' . (string) ( $viewport['viewport']['height'] ?? 0 ) . '`';
			$lines[] = '- Body scroll: `' . (string) ( $metrics['bodyWidth'] ?? 0 ) . 'x' . (string) ( $metrics['bodyHeight'] ?? 0 ) . '`';
			$lines[] = '- Total extracted nodes: `' . (string) ( $stats['totalNodes'] ?? 0 ) . '`';
			$lines[] = '';
		}

		return implode( PHP_EOL, $lines ) . PHP_EOL;
	}
}

<?php

namespace StackBlueprint\Utilities {
	function sanitize_title( $title ) {
		$title = strtolower( (string) $title );
		$title = preg_replace( '/[^a-z0-9\s\-_]/', '', $title );
		$title = preg_replace( '/\s+/', '-', trim( $title ) );
		$title = preg_replace( '/-+/', '-', $title );
		return trim( $title, '-' );
	}
}

namespace StackBlueprint\Converter {
	function wp_strip_all_tags( $text ) {
		return trim( strip_tags( (string) $text ) );
	}
	function sanitize_html_class( $class ) {
		$class = (string) $class;
		$class = preg_replace( '/[^A-Za-z0-9_-]/', '', $class );
		return $class;
	}
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
	function wp_kses_post( $content ) {
		return (string) $content;
	}
	function wp_kses( $content, $allowed_html = [], $allowed_protocols = [] ) {
		return (string) $content;
	}
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
	}
}

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

	if ( ! function_exists( '__' ) ) {
		function __( $text, $domain = null ) { return $text; }
	}
	if ( ! function_exists( 'is_wp_error' ) ) {
		function is_wp_error( $thing ) { return $thing instanceof \WP_Error; }
	}
	if ( ! function_exists( 'wp_json_encode' ) ) {
		function wp_json_encode( $data, $options = 0, $depth = 512 ) { return json_encode( $data, $options, $depth ); }
	}
	if ( ! function_exists( 'sanitize_text_field' ) ) {
		function sanitize_text_field( $str ) {
			$str = (string) $str;
			$str = preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', '', $str );
			$str = strip_tags( $str );
			return trim( $str );
		}
	}
	if ( ! function_exists( 'sanitize_key' ) ) {
		function sanitize_key( $key ) {
			$key = strtolower( (string) $key );
			$key = preg_replace( '/[^a-z0-9_\-]/', '', $key );
			return $key;
		}
	}
	if ( ! function_exists( 'set_transient' ) ) {
		function set_transient( $transient, $value, $expiration = 0 ) { return true; }
	}

	$plugin_root = dirname( __DIR__ );
	require $plugin_root . '/includes/utilities/class-helpers.php';
	require $plugin_root . '/includes/converter/generated/class-simulation-knowledge.php';
	require $plugin_root . '/includes/converter/skills/class-priority-rules-engine.php';
	require $plugin_root . '/includes/converter/skills/class-tailwind-resolver.php';
	require $plugin_root . '/includes/converter/passes/class-pass-document-intelligence.php';
	require $plugin_root . '/includes/converter/class-html-parser.php';
	require $plugin_root . '/includes/converter/class-converter-v1.php';
	require $plugin_root . '/includes/converter/class-converter-v2.php';
	require $plugin_root . '/includes/converter/class-css-resolver.php';
	require $plugin_root . '/includes/converter/class-template-library.php';
	require $plugin_root . '/includes/converter/class-native-converter.php';
	require $plugin_root . '/includes/converter/class-browser-extractor.php';
	require $plugin_root . '/includes/converter/class-browser-layout-normalizer.php';

	$args = $_SERVER['argv'] ?? [];

	$get_arg = static function( string $name, ?string $default = null ) use ( $args ): ?string {
		$index = array_search( $name, $args, true );
		return ( false !== $index && isset( $args[ $index + 1 ] ) ) ? (string) $args[ $index + 1 ] : $default;
	};

	$file = realpath( (string) $get_arg( '--file', '' ) ) ?: (string) $get_arg( '--file', '' );
	if ( '' === $file || ! is_file( $file ) ) {
		fwrite( STDOUT, "Usage: php tools/run-browser-v2-comparison-cli.php --file <html-path> [--strategy v1|v2] [--output-base <slug>]\n" );
		exit( 2 );
	}

	$strategy = strtolower( (string) $get_arg( '--strategy', 'v2' ) );
	if ( ! in_array( $strategy, [ 'v1', 'v2' ], true ) ) {
		$strategy = 'v2';
	}

	$output_base = (string) $get_arg( '--output-base', '' );
	if ( '' === $output_base ) {
		$output_base = \StackBlueprint\Utilities\sanitize_title( basename( $file, '.html' ) . '-browser-' . $strategy . '-compare' );
	}

	$html = file_get_contents( $file );
	if ( false === $html ) {
		fwrite( STDOUT, "Could not read input file: {$file}\n" );
		exit( 2 );
	}

	$out_dir = $plugin_root . '/audit-output';
	if ( ! is_dir( $out_dir ) ) {
		mkdir( $out_dir, 0777, true );
	}

	$browser_extractor = new \StackBlueprint\Converter\BrowserExtractor( $plugin_root );
	$browser_result    = $browser_extractor->extract_file(
		$file,
		[
			'base_dir' => realpath( dirname( $file ) ) ?: dirname( $file ),
		]
	);

	if ( is_wp_error( $browser_result ) ) {
		$out = [
			'ok'      => false,
			'phase'   => 'browser',
			'error'   => [
				'code'    => $browser_result->get_error_code(),
				'message' => $browser_result->get_error_message(),
				'data'    => $browser_result->get_error_data(),
			],
		];
		fwrite( STDOUT, wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . PHP_EOL );
		exit( 1 );
	}

	$converter = new \StackBlueprint\Converter\NativeConverter();
	$v2_result = $converter->convert(
		$html,
		[
			'project_name' => 'browser-compare',
			'strategy'     => $strategy,
			'filename'     => basename( $file ),
		]
	);

	if ( is_wp_error( $v2_result ) ) {
		$out = [
			'ok'      => false,
			'phase'   => 'elementor',
			'error'   => [
				'code'    => $v2_result->get_error_code(),
				'message' => $v2_result->get_error_message(),
				'data'    => $v2_result->get_error_data(),
			],
		];
		fwrite( STDOUT, wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . PHP_EOL );
		exit( 1 );
	}

	$template = json_decode( (string) ( $v2_result['json_output'] ?? '' ), true );
	if ( ! is_array( $template ) ) {
		fwrite( STDOUT, wp_json_encode( [
			'ok'    => false,
			'phase' => 'elementor',
			'error' => [
				'code'    => 'invalid_elementor_json',
				'message' => 'Elementor JSON output could not be parsed.',
			],
		], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . PHP_EOL );
		exit( 1 );
	}

	$normalizer      = new \StackBlueprint\Converter\BrowserLayoutNormalizer();
	$browser_model   = $normalizer->normalize_browser_extraction( $browser_result );
	$elementor_model = $normalizer->normalize_elementor_template( $template );
	$comparison      = $normalizer->compare_models( $browser_model, $elementor_model );

	$browser_model_path   = $out_dir . '/' . $output_base . '-browser-model.json';
	$elementor_model_path = $out_dir . '/' . $output_base . '-elementor-model.json';
	$comparison_path      = $out_dir . '/' . $output_base . '-comparison.json';
	$summary_path         = $out_dir . '/' . $output_base . '.md';

	file_put_contents( $browser_model_path, wp_json_encode( $browser_model, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
	file_put_contents( $elementor_model_path, wp_json_encode( $elementor_model, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
	file_put_contents( $comparison_path, wp_json_encode( $comparison, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
	file_put_contents( $summary_path, build_summary_markdown( $file, $strategy, $browser_model, $elementor_model, $comparison ) );

	$out = [
		'ok'      => true,
		'source'  => $file,
		'strategy'=> $strategy,
		'outputs' => [
			'browser_model'   => $browser_model_path,
			'elementor_model' => $elementor_model_path,
			'comparison'      => $comparison_path,
			'summary'         => $summary_path,
		],
		'summary' => $comparison['summary'] ?? [],
	];

	fwrite( STDOUT, wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . PHP_EOL );

	function build_summary_markdown( string $source, string $strategy, array $browser, array $elementor, array $comparison ): string {
		$summary = (array) ( $comparison['summary'] ?? [] );
		$lines   = [];
		$lines[] = '# Browser vs Elementor Comparison';
		$lines[] = '';
		$lines[] = '- Source: `' . $source . '`';
		$lines[] = '- Strategy: `' . $strategy . '`';
		$lines[] = '- Browser title: `' . (string) ( $browser['title'] ?? '' ) . '`';
		$lines[] = '- Browser sections: `' . (string) ( $summary['browser_sections'] ?? 0 ) . '`';
		$lines[] = '- Elementor sections: `' . (string) ( $summary['elementor_sections'] ?? 0 ) . '`';
		$lines[] = '- Browser behavior islands: `' . (string) ( $summary['browser_behavior_islands'] ?? 0 ) . '`';
		$lines[] = '- Elementor html widgets: `' . (string) ( $summary['elementor_html_widgets'] ?? 0 ) . '`';
		$lines[] = '- Native widget ratio: `' . (string) ( $summary['native_widget_ratio'] ?? 0 ) . '`';
		$lines[] = '';
		$lines[] = '## Counts';
		$lines[] = '';
		$lines[] = '| Signal | Browser | Elementor |';
		$lines[] = '| --- | ---: | ---: |';
		$lines[] = '| Headings | ' . (string) ( $summary['browser_headings'] ?? 0 ) . ' | ' . (string) ( $summary['elementor_headings'] ?? 0 ) . ' |';
		$lines[] = '| Text blocks | ' . (string) ( $summary['browser_text_blocks'] ?? 0 ) . ' | ' . (string) ( $summary['elementor_text_blocks'] ?? 0 ) . ' |';
		$lines[] = '| Actions | ' . (string) ( $summary['browser_actions'] ?? 0 ) . ' | ' . (string) ( $summary['elementor_buttons'] ?? 0 ) . ' |';
		$lines[] = '| Lists | ' . (string) ( $summary['browser_lists'] ?? 0 ) . ' | ' . (string) ( $summary['elementor_lists'] ?? 0 ) . ' |';
		$lines[] = '| Media | ' . (string) ( $summary['browser_media'] ?? 0 ) . ' | ' . (string) ( $summary['elementor_media'] ?? 0 ) . ' |';
		$lines[] = '';
		$lines[] = '## Missing Hooks';
		$lines[] = '';
		$lines[] = '- Missing ids: `' . implode( ', ', array_map( 'strval', (array) ( $comparison['missing']['ids'] ?? [] ) ) ) . '`';
		$lines[] = '- Missing classes: `' . implode( ', ', array_map( 'strval', (array) ( $comparison['missing']['classes'] ?? [] ) ) ) . '`';
		$lines[] = '- Missing section kinds: `' . implode( ', ', array_map( 'strval', (array) ( $comparison['missing']['section_kinds'] ?? [] ) ) ) . '`';
		$lines[] = '';
		$lines[] = '## Section Role Gaps';
		$lines[] = '';
		foreach ( (array) ( $comparison['section_role_gaps'] ?? [] ) as $gap ) {
			$message = trim( (string) ( $gap['message'] ?? '' ) );
			if ( '' !== $message ) {
				$lines[] = '- ' . $message;
			}
		}
		$lines[] = '';
		$lines[] = '## Deductions';
		$lines[] = '';
		foreach ( (array) ( $comparison['deductions'] ?? [] ) as $line ) {
			$lines[] = '- ' . (string) $line;
		}
		$lines[] = '';

		return implode( PHP_EOL, $lines ) . PHP_EOL;
	}
}

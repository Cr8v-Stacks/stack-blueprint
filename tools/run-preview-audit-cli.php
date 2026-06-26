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

	$args = $_SERVER['argv'] ?? [];

	$get_arg = static function( string $name, ?string $default = null ) use ( $args ): ?string {
		$index = array_search( $name, $args, true );
		return ( false !== $index && isset( $args[ $index + 1 ] ) ) ? (string) $args[ $index + 1 ] : $default;
	};

	$file = $get_arg( '--file', '' );
	if ( '' === $file || ! is_file( $file ) ) {
		fwrite( STDOUT, "Usage: php tools/run-preview-audit-cli.php --file <html-path> [--strategy v1|v2] [--output-base <slug>]\n" );
		exit( 2 );
	}

	$strategy = strtolower( (string) $get_arg( '--strategy', 'v2' ) );
	if ( ! in_array( $strategy, [ 'v1', 'v2' ], true ) ) {
		$strategy = 'v2';
	}

	$output_base = (string) $get_arg( '--output-base', '' );
	if ( '' === $output_base ) {
		$output_base = \StackBlueprint\Utilities\sanitize_title( basename( $file, '.html' ) . '-' . $strategy . '-audit' );
	}

	$html = file_get_contents( $file );
	if ( false === $html ) {
		fwrite( STDOUT, "Could not read input file: {$file}\n" );
		exit( 2 );
	}

	$converter = new \StackBlueprint\Converter\NativeConverter();
	$result    = $converter->convert(
		$html,
		[
			'project_name' => 'preview-audit',
			'strategy'     => $strategy,
			'filename'     => basename( $file ),
		]
	);

	if ( is_wp_error( $result ) ) {
		$out = [
			'ok'      => false,
			'error'   => [
				'code'    => $result->get_error_code(),
				'message' => $result->get_error_message(),
				'data'    => $result->get_error_data(),
			],
			'source'  => $file,
			'strategy'=> $strategy,
		];
		fwrite( STDOUT, wp_json_encode( $out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . PHP_EOL );
		exit( 1 );
	}

	$out_dir = $plugin_root . '/audit-output';
	if ( ! is_dir( $out_dir ) ) {
		mkdir( $out_dir, 0777, true );
	}

	$json_output   = (string) ( $result['json_output'] ?? '' );
	$css_output    = (string) ( $result['css_output'] ?? '' );
	$diagnostics   = (array) ( $result['diagnostics'] ?? [] );
	$warnings      = array_values( array_map( 'strval', (array) ( $result['warnings'] ?? [] ) ) );
	$template      = json_decode( $json_output, true );
	$structure     = summarize_template_structure( is_array( $template ) ? $template : [] );
	$report        = find_conversion_report( $diagnostics );
	$section_modes = find_section_modes( $diagnostics );

	$json_path       = $out_dir . '/' . $output_base . '.json';
	$css_path        = $out_dir . '/' . $output_base . '.css';
	$diagnostics_path= $out_dir . '/' . $output_base . '-diagnostics.json';
	$report_path     = $out_dir . '/' . $output_base . '-report.json';
	$audit_path      = $out_dir . '/' . $output_base . '-audit.json';

	file_put_contents( $json_path, $json_output );
	file_put_contents( $css_path, $css_output );
	file_put_contents( $diagnostics_path, wp_json_encode( $diagnostics, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
	file_put_contents( $report_path, wp_json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );

	$audit = [
		'ok'           => true,
		'source'       => $file,
		'strategy'     => $strategy,
		'outputs'      => [
			'json'        => $json_path,
			'css'         => $css_path,
			'diagnostics' => $diagnostics_path,
			'report'      => $report_path,
		],
		'preview_like' => [
			'top_level_elements' => (int) ( $structure['top_level_elements'] ?? 0 ),
			'total_nodes'        => (int) ( $structure['total_nodes'] ?? 0 ),
			'containers'         => (int) ( $structure['containers'] ?? 0 ),
			'widgets'            => (int) ( $structure['widgets'] ?? 0 ),
			'html_widgets'       => (int) ( $structure['html_widgets'] ?? 0 ),
			'widget_counts'      => (array) ( $structure['widget_counts'] ?? [] ),
		],
		'coverage'     => (array) ( $report['coverage'] ?? [] ),
		'bridges'      => (array) ( $report['bridges'] ?? [] ),
		'assets'       => (array) ( $report['assets'] ?? [] ),
		'sections'     => [
			'summary'      => (array) ( $report['sections'] ?? [] ),
			'render_modes' => $section_modes,
		],
		'warnings'     => $warnings,
	];

	file_put_contents( $audit_path, wp_json_encode( $audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );
	$audit['outputs']['audit'] = $audit_path;

	fwrite( STDOUT, wp_json_encode( $audit, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . PHP_EOL );

	function find_conversion_report( array $diagnostics ): array {
		foreach ( $diagnostics as $diag ) {
			if ( ( $diag['code'] ?? '' ) === 'conversion_run_report' ) {
				return (array) ( $diag['context'] ?? [] );
			}
		}
		return [];
	}

	function find_section_modes( array $diagnostics ): array {
		$modes = [];
		foreach ( $diagnostics as $diag ) {
			if ( ( $diag['code'] ?? '' ) !== 'section_render_mode' ) {
				continue;
			}
			$context = (array) ( $diag['context'] ?? [] );
			$modes[] = [
				'type'         => (string) ( $context['type'] ?? '' ),
				'render_mode'  => (string) ( $context['render_mode'] ?? '' ),
				'decision'     => (string) ( $context['decision'] ?? '' ),
				'widget_counts'=> (array) ( $context['widget_counts'] ?? [] ),
			];
		}
		return $modes;
	}

	function summarize_template_structure( array $template ): array {
		$summary = [
			'top_level_elements' => is_array( $template['content'] ?? null ) ? count( $template['content'] ) : 0,
			'total_nodes'        => 0,
			'containers'         => 0,
			'widgets'            => 0,
			'html_widgets'       => 0,
			'widget_counts'      => [],
		];

		$walk = static function( array $nodes ) use ( &$walk, &$summary ): void {
			foreach ( $nodes as $node ) {
				if ( ! is_array( $node ) ) {
					continue;
				}
				$summary['total_nodes']++;
				$el_type = (string) ( $node['elType'] ?? '' );
				if ( 'container' === $el_type ) {
					$summary['containers']++;
				}
				if ( 'widget' === $el_type ) {
					$summary['widgets']++;
					$widget_type = strtolower( (string) ( $node['widgetType'] ?? 'unknown' ) );
					if ( ! isset( $summary['widget_counts'][ $widget_type ] ) ) {
						$summary['widget_counts'][ $widget_type ] = 0;
					}
					$summary['widget_counts'][ $widget_type ]++;
					if ( 'html' === $widget_type ) {
						$summary['html_widgets']++;
					}
				}
				if ( ! empty( $node['elements'] ) && is_array( $node['elements'] ) ) {
					$walk( $node['elements'] );
				}
			}
		};

		$walk( (array) ( $template['content'] ?? [] ) );
		ksort( $summary['widget_counts'] );
		return $summary;
	}
}

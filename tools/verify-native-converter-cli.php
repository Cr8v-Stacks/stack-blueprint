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
	/**
	 * DB-free CLI verification runner.
	 *
	 * Runs NativeConverter without wp-load.php (no DB bootstrap).
	 */

	$wp_root = dirname( __DIR__, 4 );
	if ( ! is_dir( $wp_root . '/wp-includes' ) ) {
		fwrite( STDOUT, "WP root not found at: {$wp_root}\n" );
		exit( 3 );
	}

	define( 'ABSPATH', $wp_root . '/' );
	define( 'WPINC', 'wp-includes' );

	// WP_Error triggers hooks; provide no-op stubs for CLI verification.
	if ( ! function_exists( 'do_action' ) ) {
		function do_action( ...$args ) { return null; }
	}
	if ( ! function_exists( 'apply_filters' ) ) {
		function apply_filters( $tag, $value ) { return $value; }
	}

	require ABSPATH . 'wp-includes/class-wp-error.php';

	// Minimal shims used by the native converter in CLI verification.
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

	$converter = new \StackBlueprint\Converter\NativeConverter();
	$argv = $_SERVER['argv'] ?? [];

	$run = function( string $label, string $html, array $params ) use ( $converter ): array {
		echo "=== {$label} ===\n";
		$result = $converter->convert( $html, $params );
		if ( is_wp_error( $result ) ) {
			echo 'WP_ERROR: ' . $result->get_error_code() . PHP_EOL;
			echo $result->get_error_message() . PHP_EOL;
			$data = $result->get_error_data();
			if ( ! empty( $data ) ) {
				echo wp_json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . PHP_EOL;
			}
			return [ 'ok' => false, 'result' => $result ];
		}

		$diagnostics  = $result['diagnostics'] ?? [];
		$report       = null;
		$tailwind_pre = null;
		foreach ( $diagnostics as $diag ) {
			if ( ( $diag['code'] ?? '' ) === 'conversion_run_report' ) {
				$report = $diag;
			}
			if ( ( $diag['code'] ?? '' ) === 'tailwind_pre_resolution' ) {
				$tailwind_pre = $diag;
			}
		}

		echo ( $report ? "REPORT_OK\n" : "NO_REPORT\n" );
		if ( $tailwind_pre ) {
			echo "TAILWIND_PRE\n";
			echo wp_json_encode( ( $tailwind_pre['context'] ?? [] ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . PHP_EOL;
		}
		if ( $report ) {
			echo wp_json_encode( ( $report['context'] ?? [] ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . PHP_EOL;
		}
		$section_modes = array_values( array_filter(
			$diagnostics,
			static fn( $d ) => ( $d['code'] ?? '' ) === 'section_render_mode'
		) );
		if ( ! empty( $section_modes ) ) {
			echo "SECTION_RENDER_MODES\n";
			$compact = array_map(
				static function( $d ) {
					$c = (array) ( $d['context'] ?? [] );
					return [
						'type' => (string) ( $c['type'] ?? '' ),
						'render_mode' => (string) ( $c['render_mode'] ?? '' ),
						'decision' => (string) ( $c['decision'] ?? '' ),
						'widget_counts' => (array) ( $c['widget_counts'] ?? [] ),
					];
				},
				$section_modes
			);
			echo wp_json_encode( $compact, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) . PHP_EOL;
		}

		return [ 'ok' => true, 'result' => $result ];
	};

	// Run 1: non-template sample file (may be rejected as prototype sheet; still useful for Tailwind detection).
	$html_path = $plugin_root . '/cr8vstacks-headers-full (1).html';
	$html1 = file_get_contents( $html_path );
	if ( false !== $html1 ) {
		$run( 'sample_file', $html1, [
			'project_name' => 'non-template-check',
			'strategy'     => 'v2',
			'filename'     => 'cr8vstacks-headers-full (1).html',
		] );
	} else {
		echo "=== sample_file ===\nHTML sample not found: {$html_path}\n";
	}

	// Run 2: minimal single-page HTML (guarantees we can verify conversion_run_report in CLI).
	$html2 = '<!doctype html><html><head><style>
	.wrap{display:flex;gap:12px}.card{border:1px solid #ccc;padding:12px}.card:hover{border-color:#000}.card::before{content:"";display:block;height:2px;background:#000;margin-bottom:8px}
	</style></head><body>
	<section class="wrap" id="demo"><div class="card"><h2>Title</h2><p>Body</p></div><div class="card"><h2>Title 2</h2><p>Body 2</p></div></section>
	</body></html>';
	$run( 'minimal_page', $html2, [
		'project_name' => 'minimal-check',
		'strategy'     => 'v2',
		'filename'     => 'minimal.html',
	] );

	if ( in_array( '--file', $argv, true ) ) {
		$idx = array_search( '--file', $argv, true );
		$file_arg = ( $idx !== false && isset( $argv[ $idx + 1 ] ) ) ? (string) $argv[ $idx + 1 ] : '';
		if ( '' !== $file_arg && is_file( $file_arg ) ) {
			$strategy = 'v2';
			if ( in_array( '--strategy', $argv, true ) ) {
				$sidx = array_search( '--strategy', $argv, true );
				if ( $sidx !== false && isset( $argv[ $sidx + 1 ] ) ) {
					$candidate = strtolower( (string) $argv[ $sidx + 1 ] );
					if ( in_array( $candidate, [ 'v1', 'v2' ], true ) ) {
						$strategy = $candidate;
					}
				}
			}
			$html_custom = file_get_contents( $file_arg );
			if ( false !== $html_custom ) {
				$run( 'custom_file', $html_custom, [
					'project_name' => 'custom-file-check',
					'strategy'     => $strategy,
					'filename'     => basename( $file_arg ),
				] );
			}
		} else {
			echo "Custom file not found: {$file_arg}\n";
		}
	}
}

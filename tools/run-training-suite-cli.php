<?php

/**
 * DB-free training-suite runner.
 *
 * Runs all HTML files under `training-files/` through NativeConverter for both
 * strategies (v1 and v2) and prints a single JSON summary based on converter
 * diagnostics (engine interpretation), not file-specific expectations.
 */

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
	function wp_kses_post( $content ) {
		// DB-free verifier shim: keep content as-is (best effort).
		return (string) $content;
	}
	function wp_kses( $content, $allowed_html = [], $allowed_protocols = [] ) {
		// DB-free verifier shim: keep content as-is (best effort).
		return (string) $content;
	}
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' );
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

	$training_root = $plugin_root . '/training-files';
	if ( ! is_dir( $training_root ) ) {
		fwrite( STDOUT, "Training dir not found: {$training_root}\n" );
		exit( 4 );
	}

	$rii = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $training_root ) );
	$files = [];
	foreach ( $rii as $file ) {
		if ( ! $file instanceof \SplFileInfo ) {
			continue;
		}
		if ( $file->isDir() ) {
			continue;
		}
		$path = $file->getPathname();
		if ( preg_match( '/\.html?$/i', $path ) ) {
			$files[] = $path;
		}
	}
	sort( $files );

	$converter = new \StackBlueprint\Converter\NativeConverter();
	$argv = $_SERVER['argv'] ?? [];

	$quality_floor_enabled = in_array( '--quality-floor', $argv, true ) || '1' === (string) getenv( 'SB_QUALITY_FLOOR' );
	$save_history = ! in_array( '--no-history', $argv, true );
	$trend_report_enabled = in_array( '--trend-report', $argv, true ) || '1' === (string) getenv( 'SB_TREND_REPORT' );
	$trend_report_only = in_array( '--trend-report-only', $argv, true );
	$history_path = $plugin_root . '/training-suite-history.json';

	$build_trend_report = static function( array $history ): array {
		$count = count( $history );
		$empty = [
			'available' => false,
			'total_runs' => $count,
			'rolling' => [],
			'warnings' => [ 'Not enough history for trend report (need at least 2 snapshots).' ],
		];
		if ( $count < 2 ) {
			return $empty;
		}
		$windows = [ 7, 30 ];
		$report = [
			'available' => true,
			'total_runs' => $count,
			'rolling' => [],
			'warnings' => [],
		];
		$latest = (array) ( $history[ $count - 1 ]['quality_metrics'] ?? [] );
		$prev = (array) ( $history[ $count - 2 ]['quality_metrics'] ?? [] );
		foreach ( $windows as $window ) {
			$slice = array_slice( $history, -$window );
			$n = count( $slice );
			$avg = [
				'run_success_rate' => 0.0,
				'selector_output_css_ratio' => 0.0,
				'script_rewrite_ratio' => 0.0,
				'global_script_bridge_ratio' => 0.0,
			];
			foreach ( $slice as $entry ) {
				$m = (array) ( $entry['quality_metrics'] ?? [] );
				$avg['run_success_rate'] += (float) ( $m['run_success_rate'] ?? 0 );
				$avg['selector_output_css_ratio'] += (float) ( $m['selector_output_css_ratio'] ?? 0 );
				$avg['script_rewrite_ratio'] += (float) ( $m['script_rewrite_ratio'] ?? 0 );
				$avg['global_script_bridge_ratio'] += (float) ( $m['global_script_bridge_ratio'] ?? 0 );
			}
			foreach ( $avg as $k => $v ) {
				$avg[ $k ] = $n > 0 ? $v / $n : 0.0;
			}
			$report['rolling'][ (string) $window ] = [
				'window' => $n,
				'averages' => $avg,
			];
		}
		$latest_run_success = (float) ( $latest['run_success_rate'] ?? 0 );
		$prev_run_success = (float) ( $prev['run_success_rate'] ?? 0 );
		$latest_selector = (float) ( $latest['selector_output_css_ratio'] ?? 0 );
		$latest_script = (float) ( $latest['script_rewrite_ratio'] ?? 0 );
		$latest_global = (float) ( $latest['global_script_bridge_ratio'] ?? 0 );
		if ( $latest_run_success + 0.000001 < $prev_run_success ) {
			$report['warnings'][] = sprintf( 'Regression: run_success_rate dropped from %.4f to %.4f.', $prev_run_success, $latest_run_success );
		}
		$rolling_7 = (array) ( $report['rolling']['7']['averages'] ?? [] );
		if ( $latest_selector + 0.000001 < (float) ( $rolling_7['selector_output_css_ratio'] ?? 0 ) ) {
			$report['warnings'][] = 'Regression: selector_output_css_ratio is below 7-run average.';
		}
		if ( $latest_script + 0.000001 < (float) ( $rolling_7['script_rewrite_ratio'] ?? 0 ) ) {
			$report['warnings'][] = 'Regression: script_rewrite_ratio is below 7-run average.';
		}
		if ( $latest_global + 0.000001 < (float) ( $rolling_7['global_script_bridge_ratio'] ?? 0 ) ) {
			$report['warnings'][] = 'Regression: global_script_bridge_ratio is below 7-run average.';
		}
		return $report;
	};

	if ( $trend_report_only ) {
		$history = [];
		if ( is_file( $history_path ) ) {
			$raw_history = file_get_contents( $history_path );
			$parsed_history = json_decode( (string) $raw_history, true );
			if ( is_array( $parsed_history ) ) {
				$history = $parsed_history;
			}
		}
		echo wp_json_encode(
			[
				'history_path' => str_replace( '\\', '/', $history_path ),
				'trend_report' => $build_trend_report( $history ),
			],
			JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
		) . PHP_EOL;
		exit( 0 );
	}
	$profile = 'balanced';
	foreach ( $argv as $arg ) {
		if ( str_starts_with( (string) $arg, '--profile=' ) ) {
			$profile = strtolower( trim( (string) substr( (string) $arg, strlen( '--profile=' ) ) ) );
			break;
		}
	}
	$env_profile = strtolower( trim( (string) getenv( 'SB_QUALITY_PROFILE' ) ) );
	if ( '' !== $env_profile ) {
		$profile = $env_profile;
	}
	$floor_file = null;
	foreach ( $argv as $arg ) {
		if ( str_starts_with( (string) $arg, '--floor-file=' ) ) {
			$floor_file = substr( (string) $arg, strlen( '--floor-file=' ) );
			break;
		}
	}
	if ( null === $floor_file ) {
		$env_floor = (string) getenv( 'SB_FLOOR_FILE' );
		if ( '' !== $env_floor ) {
			$floor_file = $env_floor;
		}
	}
	if ( null === $floor_file || '' === trim( (string) $floor_file ) ) {
		$floor_file = $plugin_root . '/training-suite-floor.json';
	}

	$profiles_defaults = [
		'bootstrap' => [
			'min_run_success_rate' => 0.80,
			'max_fail_runs' => 4,
			'selector_output_css_ratio_min' => 0.05,
			'script_rewrite_ratio_min' => 0.40,
			'global_script_bridge_ratio_min' => 0.65,
			'max_selector_source_without_output_gaps' => 4,
			'max_script_source_without_rewrite_gaps' => 3,
			'max_global_source_js_without_bridge_gaps' => 2,
		],
		'balanced' => [
			'min_run_success_rate' => 0.95,
			'max_fail_runs' => 0,
			'selector_output_css_ratio_min' => 0.15,
			'script_rewrite_ratio_min' => 0.55,
			'global_script_bridge_ratio_min' => 0.80,
			'max_selector_source_without_output_gaps' => 0,
			'max_script_source_without_rewrite_gaps' => 0,
			'max_global_source_js_without_bridge_gaps' => 0,
		],
		'strict' => [
			'min_run_success_rate' => 1.0,
			'max_fail_runs' => 0,
			'selector_output_css_ratio_min' => 0.20,
			'script_rewrite_ratio_min' => 0.65,
			'global_script_bridge_ratio_min' => 0.90,
			'max_selector_source_without_output_gaps' => 0,
			'max_script_source_without_rewrite_gaps' => 0,
			'max_global_source_js_without_bridge_gaps' => 0,
		],
	];

	$strategy_defaults = [
		'v1' => [
			'selector_output_css_ratio_min' => 0.12,
			'script_rewrite_ratio_min' => 0.50,
			'global_script_bridge_ratio_min' => 0.75,
		],
		'v2' => [
			'selector_output_css_ratio_min' => 0.18,
			'script_rewrite_ratio_min' => 0.58,
			'global_script_bridge_ratio_min' => 0.82,
		],
	];

	$profiles = $profiles_defaults;
	$strategy_overrides = $strategy_defaults;
	if ( is_string( $floor_file ) && is_file( $floor_file ) ) {
		$raw_floor = file_get_contents( $floor_file );
		$parsed_floor = json_decode( (string) $raw_floor, true );
		if ( is_array( $parsed_floor ) ) {
			if ( isset( $parsed_floor['profiles'] ) && is_array( $parsed_floor['profiles'] ) ) {
				foreach ( $parsed_floor['profiles'] as $name => $cfg ) {
					if ( ! is_array( $cfg ) ) {
						continue;
					}
					$base = $profiles_defaults[ (string) $name ] ?? $profiles_defaults['balanced'];
					$profiles[ (string) $name ] = array_merge( $base, $cfg );
				}
			}
			if ( isset( $parsed_floor['strategy_overrides'] ) && is_array( $parsed_floor['strategy_overrides'] ) ) {
				foreach ( $parsed_floor['strategy_overrides'] as $name => $cfg ) {
					if ( ! is_array( $cfg ) ) {
						continue;
					}
					$base = $strategy_defaults[ (string) $name ] ?? [];
					$strategy_overrides[ (string) $name ] = array_merge( $base, $cfg );
				}
			}
		}
	}
	if ( ! isset( $profiles[ $profile ] ) ) {
		$profile = 'balanced';
	}
	$floor = (array) $profiles[ $profile ];

	$run_one = function( string $file_path, string $strategy ) use ( $converter, $plugin_root ): array {
		$html = file_get_contents( $file_path );
		$rel  = str_replace( '\\', '/', str_replace( $plugin_root . '/', '', str_replace( '\\', '/', $file_path ) ) );
		if ( false === $html ) {
			return [
				'ok'       => false,
				'file'     => $rel,
				'strategy' => $strategy,
				'error'    => [ 'code' => 'read_failed', 'message' => 'Failed to read training HTML file.' ],
			];
		}

		$result = $converter->convert( $html, [
			'project_name' => 'training-suite',
			'strategy'     => $strategy,
			'filename'     => basename( $file_path ),
		] );

		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			$diagnostics = (array) ( is_array( $data ) ? ( $data['diagnostics'] ?? [] ) : [] );
			return [
				'ok'         => false,
				'file'       => $rel,
				'strategy'   => $strategy,
				'error'      => [ 'code' => $result->get_error_code(), 'message' => $result->get_error_message() ],
				'diagnostics'=> $diagnostics,
			];
		}

		$diagnostics = (array) ( $result['diagnostics'] ?? [] );
		$report = null;
		$policy = null;
		$complexity_scores = [];
		foreach ( $diagnostics as $d ) {
			if ( ! is_array( $d ) ) {
				continue;
			}
			if ( ( $d['code'] ?? '' ) === 'conversion_run_report' ) {
				$report = $d;
			}
			if ( ( $d['code'] ?? '' ) === 'strategy_policy' ) {
				$policy = $d;
			}
			if ( ( $d['code'] ?? '' ) === 'strategy_complexity_score' ) {
				$complexity_scores[] = $d['context'] ?? [];
			}
		}

		return [
			'ok'               => true,
			'file'             => $rel,
			'strategy'         => $strategy,
			'strategy_policy'  => $policy['context']['policy'] ?? null,
			'complexity_scores'=> $complexity_scores,
			'run_report'       => $report['context'] ?? null,
		];
	};

	$results = [];
	foreach ( $files as $file_path ) {
		$results[] = $run_one( $file_path, 'v2' );
		$results[] = $run_one( $file_path, 'v1' );
	}

	// Aggregate summary to guide tuning without file-specific rules.
	$summary = [
		'total_files' => count( $files ),
		'runs'        => count( $results ),
		'ok'          => 0,
		'fail'        => 0,
		'fail_codes'  => [],
		'by_strategy' => [
			'v1' => [ 'ok' => 0, 'fail' => 0 ],
			'v2' => [ 'ok' => 0, 'fail' => 0 ],
		],
		'by_strategy_quality' => [
			'v1' => [
				'runs' => 0,
				'ok' => 0,
				'selector_source_css_runs' => 0,
				'selector_output_css_runs' => 0,
				'script_source_js_runs' => 0,
				'script_rewrite_runs' => 0,
				'global_source_js_runs' => 0,
				'global_script_bridge_runs' => 0,
			],
			'v2' => [
				'runs' => 0,
				'ok' => 0,
				'selector_source_css_runs' => 0,
				'selector_output_css_runs' => 0,
				'script_source_js_runs' => 0,
				'script_rewrite_runs' => 0,
				'global_source_js_runs' => 0,
				'global_script_bridge_runs' => 0,
			],
		],
		'bridge_quality' => [
			'runs_with_report' => 0,
			'selector_source_css_runs' => 0,
			'selector_output_css_runs' => 0,
			'selector_pseudo_source_runs' => 0,
			'selector_pseudo_output_runs' => 0,
			'selector_hover_source_runs' => 0,
			'selector_hover_output_runs' => 0,
			'selector_media_source_runs' => 0,
			'selector_media_output_runs' => 0,
			'selector_supports_source_runs' => 0,
			'selector_supports_output_runs' => 0,
			'script_source_js_runs' => 0,
			'script_rewrite_runs' => 0,
			'global_source_js_runs' => 0,
			'global_script_bridge_runs' => 0,
		],
		'bridge_gaps' => [
			'selector_source_without_output' => 0,
			'selector_pseudo_gap' => 0,
			'selector_hover_gap' => 0,
			'selector_media_gap' => 0,
			'selector_supports_gap' => 0,
			'script_source_without_rewrite' => 0,
			'global_source_js_without_bridge' => 0,
		],
	];
	foreach ( $results as $r ) {
		$strategy = (string) ( $r['strategy'] ?? 'unknown' );
		if ( isset( $summary['by_strategy_quality'][ $strategy ] ) ) {
			$summary['by_strategy_quality'][ $strategy ]['runs']++;
		}
		if ( ! empty( $r['ok'] ) ) {
			$summary['ok']++;
			if ( isset( $summary['by_strategy'][ $strategy ] ) ) {
				$summary['by_strategy'][ $strategy ]['ok']++;
			}
			$report = (array) ( $r['run_report'] ?? [] );
			if ( ! empty( $report ) ) {
				$summary['bridge_quality']['runs_with_report']++;
				$selector = (array) ( $report['bridges']['selector'] ?? [] );
				$script   = (array) ( $report['bridges']['script'] ?? [] );
				$global   = (array) ( $report['assets']['global_setup'] ?? [] );

				$sel_source_css   = ! empty( $selector['has_source_css'] );
				$sel_output_css   = ! empty( $selector['has_output_css'] );
				$sel_targets      = ! empty( $selector['has_bridge_targets'] );
				$sel_hits         = ! empty( $selector['has_source_selector_hits'] );
				$sel_pseudo_src   = ! empty( $selector['source_has_pseudo'] );
				$sel_pseudo_out   = ! empty( $selector['output_has_pseudo'] );
				$sel_hover_src    = ! empty( $selector['source_has_hover'] );
				$sel_hover_out    = ! empty( $selector['output_has_hover'] );
				$sel_media_src    = ! empty( $selector['source_has_media'] );
				$sel_media_out    = ! empty( $selector['output_has_media'] );
				$sel_supports_src = ! empty( $selector['source_has_supports'] );
				$sel_supports_out = ! empty( $selector['output_has_supports'] );
				$scr_source_js    = ! empty( $script['has_source_js'] );
				$scr_has_rewrite  = ! empty( $script['has_rewrite'] );
				$scr_targets      = ! empty( $script['has_bridge_targets'] );
				$scr_hits         = ! empty( $script['has_source_selector_hits'] );
				$scr_candidates   = ! empty( $script['has_source_hook_candidates'] );
				$g_source_js      = ! empty( $global['has_source_js'] );
				$g_has_bridge     = ! empty( $global['has_script_bridge'] );

				if ( $sel_source_css ) { $summary['bridge_quality']['selector_source_css_runs']++; }
				if ( $sel_output_css ) { $summary['bridge_quality']['selector_output_css_runs']++; }
				if ( $sel_pseudo_src ) { $summary['bridge_quality']['selector_pseudo_source_runs']++; }
				if ( $sel_pseudo_out ) { $summary['bridge_quality']['selector_pseudo_output_runs']++; }
				if ( $sel_hover_src ) { $summary['bridge_quality']['selector_hover_source_runs']++; }
				if ( $sel_hover_out ) { $summary['bridge_quality']['selector_hover_output_runs']++; }
				if ( $sel_media_src ) { $summary['bridge_quality']['selector_media_source_runs']++; }
				if ( $sel_media_out ) { $summary['bridge_quality']['selector_media_output_runs']++; }
				if ( $sel_supports_src ) { $summary['bridge_quality']['selector_supports_source_runs']++; }
				if ( $sel_supports_out ) { $summary['bridge_quality']['selector_supports_output_runs']++; }
				if ( $scr_source_js ) { $summary['bridge_quality']['script_source_js_runs']++; }
				if ( $scr_has_rewrite ) { $summary['bridge_quality']['script_rewrite_runs']++; }
				if ( $g_source_js ) { $summary['bridge_quality']['global_source_js_runs']++; }
				if ( $g_has_bridge ) { $summary['bridge_quality']['global_script_bridge_runs']++; }
				if ( isset( $summary['by_strategy_quality'][ $strategy ] ) ) {
					if ( $sel_source_css ) { $summary['by_strategy_quality'][ $strategy ]['selector_source_css_runs']++; }
					if ( $sel_output_css ) { $summary['by_strategy_quality'][ $strategy ]['selector_output_css_runs']++; }
					if ( $scr_source_js ) { $summary['by_strategy_quality'][ $strategy ]['script_source_js_runs']++; }
					if ( $scr_has_rewrite ) { $summary['by_strategy_quality'][ $strategy ]['script_rewrite_runs']++; }
					if ( $g_source_js ) { $summary['by_strategy_quality'][ $strategy ]['global_source_js_runs']++; }
					if ( $g_has_bridge ) { $summary['by_strategy_quality'][ $strategy ]['global_script_bridge_runs']++; }
					$summary['by_strategy_quality'][ $strategy ]['ok']++;
				}

				if ( $sel_source_css && $sel_targets && $sel_hits && ! $sel_output_css ) {
					$summary['bridge_gaps']['selector_source_without_output']++;
				}
				if ( $sel_pseudo_src && ! $sel_pseudo_out ) {
					$summary['bridge_gaps']['selector_pseudo_gap']++;
				}
				if ( $sel_hover_src && ! $sel_hover_out ) {
					$summary['bridge_gaps']['selector_hover_gap']++;
				}
				if ( $sel_media_src && ! $sel_media_out ) {
					$summary['bridge_gaps']['selector_media_gap']++;
				}
				if ( $sel_supports_src && ! $sel_supports_out ) {
					$summary['bridge_gaps']['selector_supports_gap']++;
				}
				if ( $scr_source_js && $scr_targets && $scr_hits && ! $scr_has_rewrite ) {
					$summary['bridge_gaps']['script_source_without_rewrite']++;
				}
				if ( $g_source_js && ( $scr_candidates || $scr_hits ) && ! $g_has_bridge ) {
					$summary['bridge_gaps']['global_source_js_without_bridge']++;
				}
			}
			continue;
		}
		$summary['fail']++;
		if ( isset( $summary['by_strategy'][ $strategy ] ) ) {
			$summary['by_strategy'][ $strategy ]['fail']++;
		}
		$code = (string) ( $r['error']['code'] ?? 'unknown_error' );
		$summary['fail_codes'][ $code ] = ( $summary['fail_codes'][ $code ] ?? 0 ) + 1;
	}
	arsort( $summary['fail_codes'] );

	$bridge_quality = (array) ( $summary['bridge_quality'] ?? [] );
	$bridge_gaps    = (array) ( $summary['bridge_gaps'] ?? [] );

	$run_success_rate = $summary['runs'] > 0 ? ( $summary['ok'] / $summary['runs'] ) : 0.0;
	$selector_output_css_ratio = ! empty( $bridge_quality['selector_source_css_runs'] )
		? ( (float) ( $bridge_quality['selector_output_css_runs'] ?? 0 ) / (float) $bridge_quality['selector_source_css_runs'] )
		: 1.0;
	$script_rewrite_ratio = ! empty( $bridge_quality['script_source_js_runs'] )
		? ( (float) ( $bridge_quality['script_rewrite_runs'] ?? 0 ) / (float) $bridge_quality['script_source_js_runs'] )
		: 1.0;
	$global_script_bridge_ratio = ! empty( $bridge_quality['global_source_js_runs'] )
		? ( (float) ( $bridge_quality['global_script_bridge_runs'] ?? 0 ) / (float) $bridge_quality['global_source_js_runs'] )
		: 1.0;

	$quality_metrics = [
		'run_success_rate' => $run_success_rate,
		'selector_output_css_ratio' => $selector_output_css_ratio,
		'script_rewrite_ratio' => $script_rewrite_ratio,
		'global_script_bridge_ratio' => $global_script_bridge_ratio,
	];

	$quality_floor = [
		'enabled' => $quality_floor_enabled,
		'profile' => $profile,
		'profiles_available' => array_values( array_keys( $profiles ) ),
		'file'    => str_replace( '\\', '/', (string) $floor_file ),
		'targets' => $floor,
		'strategy_overrides' => $strategy_overrides,
		'metrics' => $quality_metrics,
		'strategy_metrics' => [],
		'breaches'=> [],
		'passed'  => true,
	];

	if ( $run_success_rate < (float) ( $floor['min_run_success_rate'] ?? 0.95 ) ) {
		$quality_floor['breaches'][] = 'min_run_success_rate';
	}
	if ( (int) $summary['fail'] > (int) ( $floor['max_fail_runs'] ?? 0 ) ) {
		$quality_floor['breaches'][] = 'max_fail_runs';
	}
	if ( $selector_output_css_ratio < (float) ( $floor['selector_output_css_ratio_min'] ?? 0.15 ) ) {
		$quality_floor['breaches'][] = 'selector_output_css_ratio_min';
	}
	if ( $script_rewrite_ratio < (float) ( $floor['script_rewrite_ratio_min'] ?? 0.55 ) ) {
		$quality_floor['breaches'][] = 'script_rewrite_ratio_min';
	}
	if ( $global_script_bridge_ratio < (float) ( $floor['global_script_bridge_ratio_min'] ?? 0.80 ) ) {
		$quality_floor['breaches'][] = 'global_script_bridge_ratio_min';
	}
	if ( (int) ( $bridge_gaps['selector_source_without_output'] ?? 0 ) > (int) ( $floor['max_selector_source_without_output_gaps'] ?? 0 ) ) {
		$quality_floor['breaches'][] = 'max_selector_source_without_output_gaps';
	}
	if ( (int) ( $bridge_gaps['script_source_without_rewrite'] ?? 0 ) > (int) ( $floor['max_script_source_without_rewrite_gaps'] ?? 0 ) ) {
		$quality_floor['breaches'][] = 'max_script_source_without_rewrite_gaps';
	}
	if ( (int) ( $bridge_gaps['global_source_js_without_bridge'] ?? 0 ) > (int) ( $floor['max_global_source_js_without_bridge_gaps'] ?? 0 ) ) {
		$quality_floor['breaches'][] = 'max_global_source_js_without_bridge_gaps';
	}

	foreach ( [ 'v1', 'v2' ] as $strategy_name ) {
		$s = (array) ( $summary['by_strategy_quality'][ $strategy_name ] ?? [] );
		$selector_ratio = ! empty( $s['selector_source_css_runs'] )
			? ( (float) ( $s['selector_output_css_runs'] ?? 0 ) / (float) $s['selector_source_css_runs'] )
			: 1.0;
		$script_ratio = ! empty( $s['script_source_js_runs'] )
			? ( (float) ( $s['script_rewrite_runs'] ?? 0 ) / (float) $s['script_source_js_runs'] )
			: 1.0;
		$global_ratio = ! empty( $s['global_source_js_runs'] )
			? ( (float) ( $s['global_script_bridge_runs'] ?? 0 ) / (float) $s['global_source_js_runs'] )
			: 1.0;
		$quality_floor['strategy_metrics'][ $strategy_name ] = [
			'selector_output_css_ratio' => $selector_ratio,
			'script_rewrite_ratio' => $script_ratio,
			'global_script_bridge_ratio' => $global_ratio,
		];

		$ovr = (array) ( $strategy_overrides[ $strategy_name ] ?? [] );
		$sel_min = (float) ( $ovr['selector_output_css_ratio_min'] ?? $floor['selector_output_css_ratio_min'] );
		$scr_min = (float) ( $ovr['script_rewrite_ratio_min'] ?? $floor['script_rewrite_ratio_min'] );
		$gbr_min = (float) ( $ovr['global_script_bridge_ratio_min'] ?? $floor['global_script_bridge_ratio_min'] );
		if ( $selector_ratio < $sel_min ) {
			$quality_floor['breaches'][] = "{$strategy_name}.selector_output_css_ratio_min";
		}
		if ( $script_ratio < $scr_min ) {
			$quality_floor['breaches'][] = "{$strategy_name}.script_rewrite_ratio_min";
		}
		if ( $global_ratio < $gbr_min ) {
			$quality_floor['breaches'][] = "{$strategy_name}.global_script_bridge_ratio_min";
		}
	}

	$quality_floor['passed'] = empty( $quality_floor['breaches'] );

	$payload = [
		'summary' => $summary,
		'quality_floor' => $quality_floor,
		'results' => $results,
	];

	// Write UTF-8 JSON directly to disk to avoid PowerShell UTF-16 redirection.
	$out_path = $plugin_root . '/training-suite-report.json';
	$json = wp_json_encode( $payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	file_put_contents( $out_path, $json );

	// Historical snapshots for trend analysis.
	$trend = [ 'available' => false, 'window' => 0, 'delta' => [] ];
	$trend_report = [ 'available' => false, 'total_runs' => 0, 'rolling' => [], 'warnings' => [] ];
	if ( $save_history ) {
		$history = [];
		if ( is_file( $history_path ) ) {
			$raw_history = file_get_contents( $history_path );
			$parsed_history = json_decode( (string) $raw_history, true );
			if ( is_array( $parsed_history ) ) {
				$history = $parsed_history;
			}
		}
		$history[] = [
			'timestamp' => gmdate( 'c' ),
			'profile' => $profile,
			'summary' => [
				'runs' => $summary['runs'],
				'ok' => $summary['ok'],
				'fail' => $summary['fail'],
			],
			'quality_metrics' => $quality_metrics,
			'quality_floor' => [
				'enabled' => $quality_floor_enabled,
				'passed' => $quality_floor['passed'],
				'breaches' => $quality_floor['breaches'],
			],
		];
		$max_history = 300;
		if ( count( $history ) > $max_history ) {
			$history = array_slice( $history, -$max_history );
		}
		file_put_contents( $history_path, wp_json_encode( $history, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE ) );

		$trend_window = min( 20, count( $history ) );
		if ( $trend_window >= 2 ) {
			$latest = $history[ count( $history ) - 1 ];
			$prev   = $history[ count( $history ) - 2 ];
			$trend = [
				'available' => true,
				'window' => $trend_window,
				'delta' => [
					'run_success_rate' => (float) ( $latest['quality_metrics']['run_success_rate'] ?? 0 ) - (float) ( $prev['quality_metrics']['run_success_rate'] ?? 0 ),
					'selector_output_css_ratio' => (float) ( $latest['quality_metrics']['selector_output_css_ratio'] ?? 0 ) - (float) ( $prev['quality_metrics']['selector_output_css_ratio'] ?? 0 ),
					'script_rewrite_ratio' => (float) ( $latest['quality_metrics']['script_rewrite_ratio'] ?? 0 ) - (float) ( $prev['quality_metrics']['script_rewrite_ratio'] ?? 0 ),
					'global_script_bridge_ratio' => (float) ( $latest['quality_metrics']['global_script_bridge_ratio'] ?? 0 ) - (float) ( $prev['quality_metrics']['global_script_bridge_ratio'] ?? 0 ),
				],
			];
		}
		if ( $trend_report_enabled ) {
			$trend_report = $build_trend_report( $history );
		}
	}

	// Print small UTF-8 summary to stdout.
	echo wp_json_encode(
		[
			'written' => str_replace( '\\', '/', $out_path ),
			'history' => [
				'saved' => $save_history,
				'path' => str_replace( '\\', '/', $plugin_root . '/training-suite-history.json' ),
			],
			'summary' => $summary,
			'quality_floor' => $quality_floor,
			'trend' => $trend,
			'trend_report' => $trend_report,
		],
		JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE
	) . PHP_EOL;

	if ( $quality_floor_enabled && ! $quality_floor['passed'] ) {
		exit( 2 );
	}
}


<?php
/**
 * Compile simulation corpora into generated runtime artifacts.
 *
 * Usage:
 *   php tools/compile-patterns.php
 */

$root = dirname( __DIR__ );
$corpusPatterns = [
	$root . DIRECTORY_SEPARATOR . 'simulation-corpus-v1*.json',
	$root . DIRECTORY_SEPARATOR . 'simulation-corpus-v2*.json',
	$root . DIRECTORY_SEPARATOR . 'simulation-corpus-v3*.json',
];

$files = [];
foreach ( $corpusPatterns as $pattern ) {
	foreach ( glob( $pattern ) as $file ) {
		$files[] = $file;
	}
}
$files = array_values( array_unique( $files ) );

if ( empty( $files ) ) {
	fwrite( STDERR, "No simulation corpus files found.\n" );
	exit( 1 );
}

$tailwindMap = [];
$hardRules = [];

/**
 * Normalize free-form hard-rule text into runtime rule objects.
 *
 * @param string $text Rule text.
 * @return array<string,mixed>|null
 */
function sb_compile_hard_rule_from_text( string $text ): ?array {
	$t = strtolower( trim( $text ) );
	if ( '' === $t ) {
		return null;
	}

	if ( str_contains( $t, 'script_mutation' ) || str_contains( $t, 'script mutation' ) || str_contains( $t, 'mutates text' ) || str_contains( $t, 'innerhtml' ) || str_contains( $t, 'textcontent' ) ) {
		return [
			'id'     => 'RULE-003',
			'type'   => 'script_mutation',
			'action' => 'html',
			'reason' => 'Compiled: script-driven DOM/text mutation requires HTML preservation path.',
		];
	}

	if ( str_contains( $t, 'position:fixed' ) ) {
		return [
			'id' => 'RULE-001',
			'type' => 'style_contains',
			'needle' => 'position:fixed',
			'action' => 'html',
			'reason' => 'Compiled: fixed position requires HTML preservation path.',
		];
	}

	if ( str_contains( $t, '<table>') || str_contains( $t, 'table') ) {
		return [
			'id' => 'RULE-004',
			'type' => 'tag',
			'tag' => 'table',
			'action' => 'html',
			'reason' => 'Compiled: table structures use HTML widget path.',
		];
	}

	if ( str_contains( $t, 'column-count' ) || str_contains( $t, 'css columns' ) || str_contains( $t, 'masonry' ) ) {
		return [
			'id' => 'RULE-005',
			'type' => 'style_contains',
			'needle' => 'column-count',
			'action' => 'html',
			'reason' => 'Compiled: CSS Columns/masonry has no stable native mapping.',
		];
	}

	if ( str_contains( $t, 'canvas' ) ) {
		return [
			'id' => 'RULE-002',
			'type' => 'tag',
			'tag' => 'canvas',
			'action' => 'html',
			'reason' => 'Compiled: canvas requires preserved/global handling.',
		];
	}

	if ( str_contains( $t, 'copy_verbatim' ) || str_contains( $t, 'verbatim' ) ) {
		return [
			'id' => 'RULE-006',
			'type' => 'meta',
			'meta' => 'html_widget_copy_verbatim',
			'action' => 'html',
			'reason' => 'Compiled: HTML widget boundaries must copy descendant markup verbatim.',
		];
	}

	return null;
}

foreach ( $files as $file ) {
	$raw = file_get_contents( $file );
	$data = json_decode( (string) $raw, true );
	if ( ! is_array( $data ) ) {
		continue;
	}

	$simulations = $data['simulations'] ?? [];
	if ( ! is_array( $simulations ) ) {
		continue;
	}

	foreach ( $simulations as $sim ) {
		if ( ! is_array( $sim ) ) {
			continue;
		}

		$signals = $sim['extracted_signals'] ?? [];
		if ( is_array( $signals ) ) {
			$maps = [];
			if ( isset( $signals['tailwind_resolver_utility_map'] ) && is_array( $signals['tailwind_resolver_utility_map'] ) ) {
				$maps[] = $signals['tailwind_resolver_utility_map'];
			}
			foreach ( $signals as $key => $value ) {
				if ( is_string( $key ) && str_contains( strtolower( $key ), 'tailwind' ) && is_array( $value ) ) {
					$maps[] = $value;
				}
			}
			foreach ( $maps as $map ) {
				foreach ( $map as $k => $v ) {
					if ( is_string( $k ) && is_string( $v ) ) {
						$tailwindMap[ $k ] = $v;
					}
				}
			}
		}

		$updates = $sim['pattern_library_updates'] ?? [];
		$newHardRules = $updates['new_hard_rules'] ?? [];
		if ( is_array( $newHardRules ) ) {
			foreach ( $newHardRules as $rule ) {
				if ( is_array( $rule ) ) {
					$hardRules[] = $rule;
				} elseif ( is_string( $rule ) ) {
					$compiled = sb_compile_hard_rule_from_text( $rule );
					if ( is_array( $compiled ) ) {
						$hardRules[] = $compiled;
					}
				}
			}
		}

		// Some corpus rounds expose hard rule text under extracted_signals.hard_rules.
		$signalHardRules = $signals['hard_rules'] ?? [];
		if ( is_array( $signalHardRules ) ) {
			foreach ( $signalHardRules as $rule ) {
				if ( is_string( $rule ) ) {
					$compiled = sb_compile_hard_rule_from_text( $rule );
					if ( is_array( $compiled ) ) {
						$hardRules[] = $compiled;
					}
				}
			}
		}
	}
}

// Ensure key baseline hard rules always exist.
$baselineHardRules = [
	sb_compile_hard_rule_from_text( 'position:fixed' ),
	sb_compile_hard_rule_from_text( 'canvas' ),
	sb_compile_hard_rule_from_text( 'script_mutation' ),
	sb_compile_hard_rule_from_text( '<table> -> html widget' ),
	sb_compile_hard_rule_from_text( 'column-count -> html widget' ),
];
foreach ( $baselineHardRules as $rule ) {
	if ( is_array( $rule ) ) {
		$hardRules[] = $rule;
	}
}

// De-duplicate hard rules by id+type+needle/tag.
$hardRulesByKey = [];
foreach ( $hardRules as $rule ) {
	if ( ! is_array( $rule ) ) {
		continue;
	}
	$key = implode( '|', [
		(string) ( $rule['id'] ?? '' ),
		(string) ( $rule['type'] ?? '' ),
		(string) ( $rule['needle'] ?? '' ),
		(string) ( $rule['tag'] ?? '' ),
		(string) ( $rule['meta'] ?? '' ),
	]);
	$hardRulesByKey[ $key ] = $rule;
}
$hardRules = array_values( $hardRulesByKey );

if ( empty( $tailwindMap ) ) {
	$tailwindMap = [
		'flex' => 'display:flex',
		'grid' => 'display:grid',
		'hidden' => 'display:none',
	];
}

$generatedDir = $root . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'converter' . DIRECTORY_SEPARATOR . 'generated';
if ( ! is_dir( $generatedDir ) ) {
	mkdir( $generatedDir, 0777, true );
}

$outFile = $generatedDir . DIRECTORY_SEPARATOR . 'class-simulation-knowledge.php';
$hardRulesExport = var_export( array_values( $hardRules ), true );
$tailwindExport  = var_export( $tailwindMap, true );

$php = <<<PHP
<?php
/**
 * AUTO-GENERATED by tools/compile-patterns.php
 * Do not edit manually.
 */
namespace StackBlueprint\\Converter\\Generated;
if ( ! defined( 'ABSPATH' ) ) { exit; }
class SimulationKnowledge {
	public static function hard_rules(): array {
		return {$hardRulesExport};
	}
	public static function tailwind_map(): array {
		return {$tailwindExport};
	}
}
PHP;

file_put_contents( $outFile, $php );
echo "Generated: {$outFile}\n";
echo "Corpus files: " . count( $files ) . "\n";
echo "Tailwind map entries: " . count( $tailwindMap ) . "\n";
echo "Hard rule entries: " . count( $hardRules ) . "\n";


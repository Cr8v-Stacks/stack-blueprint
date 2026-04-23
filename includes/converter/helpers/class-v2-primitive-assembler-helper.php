<?php
/**
 * V2 Primitive Assembler Helper.
 *
 * @package StackBlueprint
 */

namespace StackBlueprint\Converter\Helpers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class V2PrimitiveAssemblerHelper {

	/**
	 * Extract common native-capable primitives from a section subtree.
	 *
	 * @return array<string,mixed>
	 */
	public static function extract_primitives( \DOMElement $node, \DOMXPath $xp ): array {
		$out = [
			'headings' => [],
			'paragraphs' => [],
			'buttons' => [],
			'lists' => [],
			'images' => [],
			'tables' => [],
		];

		$headings = $xp->query( './/h1 | .//h2 | .//h3 | .//h4', $node );
		if ( $headings ) {
			foreach ( $headings as $el ) {
				if ( ! $el instanceof \DOMElement ) {
					continue;
				}
				$text = trim( preg_replace( '/\s+/', ' ', (string) $el->textContent ) );
				if ( '' === $text || strlen( $text ) > 180 ) {
					continue;
				}
				$out['headings'][] = [ 'text' => $text, 'tag' => strtolower( $el->tagName ) ];
				if ( count( $out['headings'] ) >= 4 ) {
					break;
				}
			}
		}

		$paragraphs = $xp->query( './/p', $node );
		if ( $paragraphs ) {
			foreach ( $paragraphs as $el ) {
				if ( ! $el instanceof \DOMElement ) {
					continue;
				}
				$text = trim( preg_replace( '/\s+/', ' ', (string) $el->textContent ) );
				if ( '' === $text || strlen( $text ) < 16 || strlen( $text ) > 560 ) {
					continue;
				}
				$out['paragraphs'][] = $text;
				if ( count( $out['paragraphs'] ) >= 4 ) {
					break;
				}
			}
		}

		$buttons = $xp->query( './/a[contains(@class,"btn") or contains(@class,"cta")] | .//button | .//a[@href]', $node );
		if ( $buttons ) {
			foreach ( $buttons as $el ) {
				if ( ! $el instanceof \DOMElement ) {
					continue;
				}
				$text = trim( preg_replace( '/\s+/', ' ', (string) $el->textContent ) );
				if ( '' === $text || strlen( $text ) > 48 ) {
					continue;
				}
				$url = (string) $el->getAttribute( 'href' );
				$out['buttons'][] = [ 'text' => $text, 'url' => '' !== trim( $url ) ? trim( $url ) : '#' ];
				if ( count( $out['buttons'] ) >= 3 ) {
					break;
				}
			}
		}

		$list_nodes = $xp->query( './/ul | .//ol', $node );
		if ( $list_nodes ) {
			foreach ( $list_nodes as $list_node ) {
				if ( ! $list_node instanceof \DOMElement ) {
					continue;
				}
				$items = [];
				$li_nodes = $xp->query( './/li', $list_node );
				if ( $li_nodes ) {
					foreach ( $li_nodes as $li ) {
						if ( ! $li instanceof \DOMElement ) {
							continue;
						}
						$text = trim( preg_replace( '/\s+/', ' ', (string) $li->textContent ) );
						if ( '' !== $text ) {
							$items[] = $text;
						}
						if ( count( $items ) >= 8 ) {
							break;
						}
					}
				}
				if ( ! empty( $items ) ) {
					$out['lists'][] = $items;
				}
				if ( count( $out['lists'] ) >= 2 ) {
					break;
				}
			}
		}

		$images = $xp->query( './/img', $node );
		if ( $images ) {
			foreach ( $images as $img ) {
				if ( ! $img instanceof \DOMElement ) {
					continue;
				}
				$src = trim( (string) $img->getAttribute( 'src' ) );
				if ( '' === $src ) {
					continue;
				}
				$alt = trim( (string) $img->getAttribute( 'alt' ) );
				$out['images'][] = [ 'src' => $src, 'alt' => $alt ];
				if ( count( $out['images'] ) >= 2 ) {
					break;
				}
			}
		}

		$tables = $xp->query( './/table', $node );
		if ( $tables ) {
			foreach ( $tables as $table ) {
				if ( ! $table instanceof \DOMElement ) {
					continue;
				}
				$rows = [];
				$tr_nodes = $xp->query( './/tr', $table );
				if ( $tr_nodes ) {
					foreach ( $tr_nodes as $tr ) {
						if ( ! $tr instanceof \DOMElement ) {
							continue;
						}
						$cells = [];
						$cell_nodes = $xp->query( './/th | .//td', $tr );
						if ( $cell_nodes ) {
							foreach ( $cell_nodes as $cell ) {
								if ( ! $cell instanceof \DOMElement ) {
									continue;
								}
								$cell_text = trim( preg_replace( '/\s+/', ' ', (string) $cell->textContent ) );
								$cells[] = $cell_text;
							}
						}
						if ( ! empty( $cells ) ) {
							$rows[] = $cells;
						}
						if ( count( $rows ) >= 6 ) {
							break;
						}
					}
				}
				if ( ! empty( $rows ) ) {
					$out['tables'][] = $rows;
				}
				if ( count( $out['tables'] ) >= 1 ) {
					break;
				}
			}
		}

		return $out;
	}

	/**
	 * Extract feature cards from common JS data arrays used to render grids.
	 *
	 * @return array<int,array<string,string>>
	 */
	public static function extract_feature_cards_from_scripts( \DOMElement $node, \DOMXPath $xp ): array {
		$scripts = $xp->query( '//script' );
		if ( ! $scripts || 0 === $scripts->length ) {
			return [];
		}

		$cards = [];
		foreach ( $scripts as $script ) {
			$js = (string) $script->textContent;
			if ( ! str_contains( $js, 'features' ) || ! preg_match( '/(?:const|let|var)\s+features\s*=\s*\[(.*?)\]\s*;/s', $js, $array_match ) ) {
				continue;
			}

			if ( ! preg_match_all( '/\{([^{}]*?)\}/s', (string) $array_match[1], $object_matches ) ) {
				continue;
			}

			foreach ( $object_matches[1] as $object_body ) {
				$fields = self::parse_js_object_string_fields( (string) $object_body );
				$title = trim( (string) ( $fields['title'] ?? '' ) );
				$desc  = trim( (string) ( $fields['desc'] ?? '' ) );
				if ( '' === $title && '' === $desc ) {
					continue;
				}

				$icon = trim( (string) ( $fields['icon'] ?? '' ) );
				$cards[] = [
					'title'       => $title,
					'body'        => $desc,
					'tag'         => trim( (string) ( $fields['cat'] ?? '' ) ),
					'author'      => '',
					'quote'       => $desc,
					'visual_html' => '' !== $icon ? '<div class="sb-source-feature-icon">' . htmlspecialchars( $icon, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8' ) . '</div>' : '',
					'source_class'=> '',
					'source_id'   => '',
				];
			}
		}

		return $cards;
	}

	/**
	 * @return array<string,string>
	 */
	private static function parse_js_object_string_fields( string $object_body ): array {
		$fields = [];
		if ( ! preg_match_all( '/([A-Za-z_][A-Za-z0-9_]*)\s*:\s*([\'"])((?:\\\\.|(?!\2).)*)\2/s', $object_body, $matches, PREG_SET_ORDER ) ) {
			return [];
		}

		foreach ( $matches as $match ) {
			$key = (string) ( $match[1] ?? '' );
			$value = stripcslashes( (string) ( $match[3] ?? '' ) );
			if ( '' !== $key ) {
				$fields[ $key ] = $value;
			}
		}

		return $fields;
	}
}

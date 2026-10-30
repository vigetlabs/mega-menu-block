<?php
/**
 * Block Renderer Class
 *
 * @package MegaMenuBlock
 */

namespace MegaMenuBlock;

use Exception;
use WP_Block;
use WP_Block_List;

// Exit if accessed directly.
if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block Renderer Handler
 */
class Renderer {

	/**
	 * Storage for nested Navigation blocks that need to be rendered separately.
	 *
	 * @var array
	 */
	private static $nested_navigation_blocks = [];

	/**
	 * Menu ref of the Navigation block about to render.
	 *
	 * @var int|null
	 */
	private static $current_menu_ref = null;

	/**
	 * Render the mega menu block on the frontend.
	 *
	 * @param array    $attributes Block attributes.
	 * @param string   $content    Block content.
	 * @param WP_Block $block      Block instance.
	 * @return string Rendered block HTML.
	 */
	public static function render( $attributes, $content, $block ) {
		// Sanitize attributes.
		$attributes = self::sanitize_attributes( $attributes );

		// Get inner blocks content.
		$inner_blocks_html = self::render_inner_blocks( $block, $content );

		// If no inner blocks, return empty string.
		if ( empty( $inner_blocks_html ) ) {
			return '';
		}

		// Get wrapper attributes.
		$wrapper_attributes = get_block_wrapper_attributes(
			[
				'class' => 'wp-block-mega-menu',
			]
		);

		// Build the output - inner blocks HTML is already escaped by render_block().
		// Use <li> tag since this block is a child of <ul> in Navigation submenus.
		$output = \sprintf(
			'<li %s>%s</li>',
			$wrapper_attributes,
			$inner_blocks_html
		);

		return $output;
	}

	/**
	 * Render inner blocks.
	 *
	 * @param WP_Block $block    Block instance.
	 * @param string   $content  Block content (fallback).
	 * @return string Rendered inner blocks HTML.
	 */
	private static function render_inner_blocks( $block, $content = '' ) {
		$inner_blocks_html = '';

		// Check if inner_blocks is a WP_Block_List object.
		if ( $block->inner_blocks instanceof WP_Block_List ) {
			// WP_Block_List is iterable, so we can loop through it.
			foreach ( $block->inner_blocks as $inner_block ) {
				try {
					$inner_blocks_html .= $inner_block->render();
				} catch ( Exception $e ) {
					// Continue to next block instead of breaking the entire render.
					continue;
				}
			}
		} elseif ( \is_array( $block->inner_blocks ) && ! empty( $block->inner_blocks ) ) {
			// If inner_blocks is an array, render each block.
			foreach ( $block->inner_blocks as $inner_block ) {
				try {
					if ( $inner_block instanceof WP_Block ) {
						$inner_blocks_html .= $inner_block->render();
					} elseif ( \is_array( $inner_block ) ) {
						$inner_blocks_html .= render_block( $inner_block );
					}
				} catch ( Exception $e ) {
					// Continue to next block instead of breaking the entire render.
					continue;
				}
			}
		} elseif ( ! empty( $block->parsed_block['innerBlocks'] ) ) {
			// Check if inner blocks are in parsed_block.
			foreach ( $block->parsed_block['innerBlocks'] as $inner_block ) {
				try {
					$inner_blocks_html .= render_block( $inner_block );
				} catch ( Exception $e ) {
					// Continue to next block instead of breaking the entire render.
					continue;
				}
			}
		} elseif ( ! empty( $content ) ) {
			// Fallback: use the content if available.
			$inner_blocks_html = $content;
		}

		return $inner_blocks_html;
	}

	/**
	 * Sanitize block attributes.
	 *
	 * @param array $attributes Block attributes.
	 * @return array Sanitized attributes.
	 */
	private static function sanitize_attributes( $attributes ) {
		if ( ! \is_array( $attributes ) ) {
			return [];
		}

		$sanitized = [];

		foreach ( $attributes as $key => $value ) {
			$sanitized_key = sanitize_key( $key );
			if ( \is_string( $value ) ) {
				$sanitized[ $sanitized_key ] = sanitize_text_field( $value );
			} elseif ( \is_array( $value ) ) {
				$sanitized[ $sanitized_key ] = self::sanitize_attributes( $value );
			} else {
				$sanitized[ $sanitized_key ] = $value;
			}
		}

		return $sanitized;
	}

	/**
	 * Hold the menu ref of the Navigation block about to render.
	 *
	 * Core's `block_core_navigation_render_inner_blocks` filter doesn't pass the block, so
	 * mask_navigation_inner_blocks() reads the ref from here.
	 *
	 * @param array $parsed_block The parsed block data.
	 * @return array Unchanged block data.
	 */
	public static function track_navigation_ref( $parsed_block ) {
		if ( empty( $parsed_block['blockName'] ) || 'core/navigation' !== $parsed_block['blockName'] ) {
			return $parsed_block;
		}

		self::$current_menu_ref = $parsed_block['attrs']['ref'] ?? null;

		return $parsed_block;
	}

	/**
	 * Replace Navigation blocks nested in Mega Menus with placeholders.
	 *
	 * Core renders nothing for a Navigation block that contains another one, so the nested
	 * blocks are masked here and put back by inject_nested_navigation_blocks(). Only this
	 * render's block list changes. The menu post and the object cache are left alone.
	 *
	 * @param WP_Block_List $inner_blocks The Navigation block's inner blocks.
	 * @return WP_Block_List Inner blocks with nested Navigation blocks masked.
	 */
	public static function mask_navigation_inner_blocks( $inner_blocks ) {
		$menu_ref = self::$current_menu_ref;

		// Only the first call after track_navigation_ref() belongs to that block.
		self::$current_menu_ref = null;

		if ( ! $menu_ref || ! $inner_blocks instanceof WP_Block_List ) {
			return $inner_blocks;
		}

		$storage_key = 'nav-post-' . $menu_ref;

		// A menu nested in itself, directly or through another menu, is left for core to skip.
		if ( isset( self::$nested_navigation_blocks[ $storage_key ] ) ) {
			return $inner_blocks;
		}

		$nested_blocks     = [];
		$masked_blocks     = [];
		$placeholder_index = 0;

		foreach ( $inner_blocks as $offset => $inner_block ) {
			$result = self::extract_nested_navigation_blocks( [ $inner_block->parsed_block ], $storage_key, false, $placeholder_index );

			if ( empty( $result['nested_blocks'] ) ) {
				continue;
			}

			$nested_blocks            = array_merge( $nested_blocks, $result['nested_blocks'] );
			$masked_blocks[ $offset ] = $result['masked_blocks'][0];
			$placeholder_index        = $result['next_placeholder_index'];
		}

		if ( empty( $nested_blocks ) ) {
			return $inner_blocks;
		}

		self::$nested_navigation_blocks[ $storage_key ] = [
			'nested_blocks' => $nested_blocks,
			'menu_ref'      => $menu_ref,
		];

		// Setting a parsed block makes the list rebuild it with the Navigation block's context.
		foreach ( $masked_blocks as $offset => $masked_block ) {
			$inner_blocks[ $offset ] = $masked_block;
		}

		return $inner_blocks;
	}

	/**
	 * Inject nested Navigation blocks back into the rendered output.
	 *
	 * @param string $block_content The rendered block content.
	 * @param array  $block The block data.
	 * @return string Modified block content with nested Navigation blocks injected.
	 */
	public static function inject_nested_navigation_blocks( $block_content, $block ) {
		// Only process core/navigation blocks.
		if ( empty( $block['blockName'] ) || 'core/navigation' !== $block['blockName'] ) {
			return $block_content;
		}

		// A copy of the menu nested in itself shares its ref, so only output with placeholders is filled.
		if ( false === strpos( $block_content, '<!-- mega-menu-nested-nav-placeholder-' ) ) {
			return $block_content;
		}

		// Generate a unique ID for this Navigation block instance.
		$block_id = md5( wp_json_encode( $block ) );

		// Also check by menu ref for stored blocks.
		$menu_ref        = $block['attrs']['ref'] ?? null;
		$stored_block_id = $menu_ref ? 'nav-post-' . $menu_ref : null;

		// Check if we have nested Navigation blocks to inject (try both IDs).
		$nested_data     = null;
		$actual_block_id = null;

		if ( $stored_block_id && ! empty( self::$nested_navigation_blocks[ $stored_block_id ] ) ) {
			$nested_data     = self::$nested_navigation_blocks[ $stored_block_id ];
			$actual_block_id = $stored_block_id;
		} elseif ( ! empty( self::$nested_navigation_blocks[ $block_id ] ) ) {
			$nested_data     = self::$nested_navigation_blocks[ $block_id ];
			$actual_block_id = $block_id;
		}

		if ( ! $nested_data || ( empty( $nested_data['nested_blocks'] ) && $actual_block_id ) ) {
			return $block_content;
		}

		$nested_blocks = $nested_data['nested_blocks'];

		if ( empty( $nested_blocks ) ) {
			return $block_content;
		}

		// Render each nested Navigation block and replace placeholders.
		foreach ( $nested_blocks as $nested_block_data ) {
			try {
				// Render the nested Navigation block.
				$nested_html = render_block( $nested_block_data['block'] );

				// Find and replace the placeholder with the rendered Navigation block.
				$placeholder = '<!-- mega-menu-nested-nav-placeholder-' . $nested_block_data['placeholder_index'] . ' -->';

				// Replace the placeholder comment with the actual Navigation block HTML.
				$block_content = str_replace( $placeholder, $nested_html, $block_content );
			} catch ( Exception $e ) {
				// Continue to next block if rendering fails.
				continue;
			}
		}

		// Clean up stored nested blocks.
		unset( self::$nested_navigation_blocks[ $actual_block_id ] );

		return $block_content;
	}

	/**
	 * Extract nested Navigation blocks from inner blocks and mask them with placeholders.
	 *
	 * @param array  $inner_blocks The inner blocks array.
	 * @param string $parent_block_id The parent block ID.
	 * @param bool   $inside_mega_menu Whether we're currently inside a Mega Menu block.
	 * @param int    $start_placeholder_index The starting placeholder index (for recursive calls).
	 * @return array Array with 'masked_blocks', 'nested_blocks', and 'next_placeholder_index'.
	 */
	private static function extract_nested_navigation_blocks( $inner_blocks, $parent_block_id, $inside_mega_menu = false, $start_placeholder_index = 0 ) {
		$masked_blocks     = [];
		$nested_blocks     = [];
		$placeholder_index = $start_placeholder_index;

		foreach ( $inner_blocks as $inner_block ) {
			// Check if this is a Mega Menu block.
			$is_mega_menu             = ! empty( $inner_block['blockName'] ) && 'mega-menu-block/mega-menu' === $inner_block['blockName'];
			$current_inside_mega_menu = $inside_mega_menu || $is_mega_menu;

			// If we're inside a Mega Menu and find a Navigation block, capture it.
			if ( $current_inside_mega_menu && ! empty( $inner_block['blockName'] ) && 'core/navigation' === $inner_block['blockName'] ) {
				// Store this nested Navigation block for later rendering.
				$nested_blocks[] = [
					'block'             => $inner_block,
					'placeholder_index' => $placeholder_index,
				];

				// Replace with a placeholder paragraph block.
				$masked_blocks[] = [
					'blockName'    => 'core/paragraph',
					'attrs'        => [],
					'innerBlocks'  => [],
					'innerHTML'    => '<!-- mega-menu-nested-nav-placeholder-' . $placeholder_index . ' -->',
					'innerContent' => [ '<!-- mega-menu-nested-nav-placeholder-' . $placeholder_index . ' -->' ],
				];
				++$placeholder_index;
				continue;
			}

			// Recursively process nested blocks.
			if ( ! empty( $inner_block['innerBlocks'] ) ) {
				$nested_result = self::extract_nested_navigation_blocks( $inner_block['innerBlocks'], $parent_block_id, $current_inside_mega_menu, $placeholder_index );
				if ( ! empty( $nested_result['nested_blocks'] ) ) {
					$nested_blocks = array_merge( $nested_blocks, $nested_result['nested_blocks'] );
					// Update placeholder_index to continue from where recursive call left off.
					$placeholder_index = $nested_result['next_placeholder_index'] ?? $placeholder_index + count( $nested_result['nested_blocks'] );
				}
				$inner_block['innerBlocks'] = $nested_result['masked_blocks'];
			}

			$masked_blocks[] = $inner_block;
		}

		return [
			'masked_blocks'          => $masked_blocks,
			'nested_blocks'          => $nested_blocks,
			'next_placeholder_index' => $placeholder_index,
		];
	}

	/**
	 * Check if inner blocks contain a Mega Menu with nested Navigation block.
	 *
	 * @param array $inner_blocks Array of inner block data.
	 * @param bool  $inside Whether we're currently inside a Mega Menu block.
	 * @return bool True if a Mega Menu with nested Navigation is found.
	 */
	private static function has_nested_navigation_in_mega_menu( $inner_blocks, $inside = false ) {
		if ( ! \is_array( $inner_blocks ) || empty( $inner_blocks ) ) {
			return false;
		}

		foreach ( $inner_blocks as $inner_block ) {
			if ( empty( $inner_block['blockName'] ) ) {
				continue;
			}

			if ( $inside && 'core/navigation' === $inner_block['blockName'] ) {
				return true;
			}

			// Check if this is a Mega Menu block.
			if ( 'mega-menu-block/mega-menu' === $inner_block['blockName'] ) {

				if ( self::has_nested_navigation_in_mega_menu( $inner_block['innerBlocks'], true ) ) {
					return true;
				}
			}
		}

		return false;
	}
}

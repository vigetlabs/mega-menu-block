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
use WP_Post;

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
	 * Track which Navigation blocks are currently being rendered to avoid recursion.
	 *
	 * @var array
	 */
	private static $rendering_navigation_blocks = [];

	/**
	 * Temporary storage for masked post content during rendering.
	 *
	 * @var array
	 */
	private static $masked_post_content = [];

	/**
	 * Render the mega menu block on the frontend.
	 *
	 * @param array     $attributes Block attributes.
	 * @param string    $content    Block content.
	 * @param WP_Block  $block      Block instance.
	 * @return string Rendered block HTML.
	 */
	public static function render( $attributes, $content, $block ) {
		// Sanitize attributes.
		$attributes = self::sanitize_attributes( $attributes );

		if ( ! empty( $attributes['ref'] ) ) {
			// Entity mode: content is stored in a wp_block post, not inline.
			// Rendering separately via do_blocks() avoids the nested-Navigation conflict
			// that occurs when inner blocks are part of the outer wp_navigation post.
			$ref           = absint( $attributes['ref'] );
			$wp_block_post = get_post( $ref );

			if (
				$wp_block_post instanceof WP_Post &&
				'wp_block' === $wp_block_post->post_type &&
				\in_array( $wp_block_post->post_status, [ 'publish', 'private' ], true )
			) {
				$inner_blocks_html = do_blocks( $wp_block_post->post_content );
			} else {
				$inner_blocks_html = '';
			}
		} else {
			// Legacy mode: inner blocks are serialized inline in the navigation post.
			$inner_blocks_html = self::render_inner_blocks( $block, $content );
		}

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
	 * Filter navigation post content to mask nested Navigation blocks.
	 * This modifies the post object's content before WordPress uses it to render Navigation blocks.
	 *
	 * @param WP_Post $post The post object.
	 * @param string  $context The context (not used but required by filter).
	 * @return WP_Post Modified post object.
	 */
	public static function filter_navigation_post_content( $post, $context = null ) {
		// Only process wp_navigation post type.
		if ( ! $post || 'wp_navigation' !== $post->post_type ) {
			return $post;
		}

		// Only modify if we're in a frontend rendering context.
		if ( is_admin() && ! wp_doing_ajax() ) {
			return $post;
		}

		// Check if we've already stored masked content for this post.
		// If so, use it directly.
		if ( ! empty( self::$masked_post_content[ $post->ID ] ) ) {
			$post->post_content = self::$masked_post_content[ $post->ID ];
			return $post;
		}

		// Check if this navigation post contains nested Navigation blocks in Mega Menus.
		$parsed_blocks = parse_blocks( $post->post_content );
		$storage_key   = 'nav-post-' . $post->ID;

		$result = self::extract_nested_navigation_blocks( $parsed_blocks, $storage_key );

		if ( ! empty( $result['nested_blocks'] ) ) {
			// Store nested blocks.
			self::$nested_navigation_blocks[ $storage_key ] = array(
				'nested_blocks' => $result['nested_blocks'],
				'menu_ref' => $post->ID,
				'masked_blocks' => $result['masked_blocks'],
			);

			// Rebuild content with masked blocks (nested Navigation blocks replaced with placeholders).
			$masked_content = '';
			foreach ( $result['masked_blocks'] as $block ) {
				$masked_content .= serialize_block( $block );
			}

			// Store masked content.
			self::$masked_post_content[ $post->ID ] = $masked_content;

			// Modify the post object's content.
			$post->post_content = $masked_content;
		}

		return $post;
	}

	/**
	 * Modify block data to mask nested Navigation blocks during parent Navigation rendering.
	 *
	 * This filter temporarily replaces nested Navigation blocks with placeholder blocks
	 * so the parent Navigation block can render, then we'll restore them.
	 *
	 * @param array $parsed_block The parsed block data.
	 * @return array Modified block data.
	 */
	public static function mask_nested_navigation_blocks( $parsed_block ) {
		// Only process core/navigation blocks.
		if ( empty( $parsed_block['blockName'] ) || 'core/navigation' !== $parsed_block['blockName'] ) {
			return $parsed_block;
		}

		// Generate a unique ID for this Navigation block instance.
		$block_id = md5( wp_json_encode( $parsed_block ) );

		// Check if we're already rendering this block (avoid recursion).
		if ( \in_array( $block_id, self::$rendering_navigation_blocks, true ) ) {
			return $parsed_block;
		}

		// Navigation blocks load menu items from wp_navigation post, not innerBlocks.
		// We need to check the navigation post content for nested Navigation blocks.
		$menu_ref = $parsed_block['attrs']['ref'] ?? null;
		if ( $menu_ref ) {
			$navigation_post = get_post( $menu_ref );
			if ( $navigation_post && 'wp_navigation' === $navigation_post->post_type ) {
				// Parse the navigation post content to find nested Navigation blocks in Mega Menus.
				$parsed_blocks = parse_blocks( $navigation_post->post_content );

				// Check for nested Navigation blocks in Mega Menus.
				$result = self::extract_nested_navigation_blocks( $parsed_blocks, $block_id );

				if ( ! empty( $result['nested_blocks'] ) ) {
					// Store the nested Navigation blocks for later rendering.
					// Use menu ref as the key for easier lookup.
					$storage_key = 'nav-post-' . $menu_ref;
					self::$nested_navigation_blocks[ $storage_key ] = array(
						'nested_blocks' => $result['nested_blocks'],
						'menu_ref' => $menu_ref,
						'masked_blocks' => $result['masked_blocks'],
					);

					// Rebuild the navigation post content with masked blocks.
					$masked_content = '';
					foreach ( $result['masked_blocks'] as $block ) {
						$masked_content .= serialize_block( $block );
					}

					// Store masked content for use in get_post filter.
					self::$masked_post_content[ $menu_ref ] = $masked_content;

					// Temporarily modify the post content and update cache.
					$navigation_post->post_content = $masked_content;

					// Update WordPress object cache to ensure our modified version is used.
					wp_cache_set( $menu_ref, $navigation_post, 'posts' );
				}
			}
		}

		return $parsed_block;
	}

	/**
	 * Allow Navigation blocks with nested Navigation blocks to render.
	 *
	 * This filter ensures that Navigation blocks can render even when they contain
	 * nested Navigation blocks via Mega Menu blocks. We render the parent first,
	 * then inject the nested Navigation blocks back.
	 *
	 * @param string|null $pre_render The pre-rendered block content. Return null to allow normal rendering.
	 * @param array       $parsed_block The parsed block data.
	 * @param WP_Block    $block The block instance.
	 * @return string|null Return null to allow normal rendering, or a string to replace the output.
	 */
	public static function allow_nested_navigation_rendering( $pre_render, $parsed_block, $block ) {
		// Only process core/navigation blocks.
		if ( empty( $parsed_block['blockName'] ) || 'core/navigation' !== $parsed_block['blockName'] ) {
			return $pre_render;
		}

		// Generate a unique ID for this Navigation block instance.
		$block_id = md5( wp_json_encode( $parsed_block ) );

		// Also check by menu ref for stored blocks.
		$menu_ref        = $parsed_block['attrs']['ref'] ?? null;
		$stored_block_id = $menu_ref ? 'nav-post-' . $menu_ref : null;

		// Check if we have nested Navigation blocks stored (prefer stored_block_id).
		$has_nested      = false;
		$nested_data     = null;
		$actual_block_id = null;

		if ( $stored_block_id && ! empty( self::$nested_navigation_blocks[ $stored_block_id ] ) ) {
			$nested_data     = self::$nested_navigation_blocks[ $stored_block_id ];
			$actual_block_id = $stored_block_id;
			$has_nested      = true;
		} elseif ( ! empty( self::$nested_navigation_blocks[ $block_id ] ) ) {
			$nested_data     = self::$nested_navigation_blocks[ $block_id ];
			$actual_block_id = $block_id;
			$has_nested      = true;
		}

		// If we have nested blocks, we need to manually render the Navigation block
		// with modified content to prevent WordPress from blocking it.
		if ( ! $has_nested || empty( $nested_data['nested_blocks'] ) ) {
			return null;
		}

		// Mark that we're rendering this Navigation block.
		self::$rendering_navigation_blocks[] = $actual_block_id;

		// The masked content should already be stored in self::$masked_post_content
		// and the post cache should be updated. Render normally and WordPress should use it.
		$menu_ref = $parsed_block['attrs']['ref'] ?? null;
		if ( ! $menu_ref || empty( self::$masked_post_content[ $menu_ref ] ) ) {
			return null;
		}

		$rendered = null;

		// Ensure $block is not null and is a valid WP_Block instance.
		if ( ! $block || ! $block instanceof WP_Block ) {
			return null;
		}

		try {
			// Ensure the post in cache has masked content.
			$cached_post = wp_cache_get( $menu_ref, 'posts' );
			if ( $cached_post && $cached_post->post_content !== self::$masked_post_content[ $menu_ref ] ) {
				$cached_post->post_content = self::$masked_post_content[ $menu_ref ];
				wp_cache_set( $menu_ref, $cached_post, 'posts' );
			}

			$rendered = $block->render();
		} catch ( Exception $e ) {
			return null;
		}

		// Return the rendered content - we'll inject nested blocks in render_block filter.
		return $rendered;
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

		// Generate a unique ID for this Navigation block instance.
		$block_id = md5( wp_json_encode( $block ) );

		// Also check by menu ref for stored blocks.
		$menu_ref        = $block['attrs']['ref'] ?? null;
		$stored_block_id = $menu_ref ? 'nav-post-' . $menu_ref : null;

		// Check if we have nested Navigation blocks to inject (try both IDs).
		$nested_data = null;
		$actual_block_id = null;

		if ( $stored_block_id && ! empty( self::$nested_navigation_blocks[ $stored_block_id ] ) ) {
			$nested_data = self::$nested_navigation_blocks[ $stored_block_id ];
			$actual_block_id = $stored_block_id;
		} elseif ( ! empty( self::$nested_navigation_blocks[ $block_id ] ) ) {
			$nested_data = self::$nested_navigation_blocks[ $block_id ];
			$actual_block_id = $block_id;
		}

		if ( ! $nested_data || empty( $nested_data['nested_blocks'] ) && $actual_block_id ) {
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

		// Clean up stored nested blocks and masked content.
		unset( self::$nested_navigation_blocks[ $actual_block_id ] );

		// Clean up masked post content.
		$menu_ref = $block['attrs']['ref'] ?? null;
		if ( $menu_ref && isset( self::$masked_post_content[ $menu_ref ] ) ) {
			unset( self::$masked_post_content[ $menu_ref ] );
		}

		// Remove from rendering list.
		$key = array_search( $actual_block_id, self::$rendering_navigation_blocks, true );
		if ( false !== $key ) {
			unset( self::$rendering_navigation_blocks[ $key ] );
		}

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
			$is_mega_menu = ! empty( $inner_block['blockName'] ) && 'mega-menu-block/mega-menu' === $inner_block['blockName'];
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
				$placeholder_index++;
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

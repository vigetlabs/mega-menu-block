<?php
/**
 * Dynamic Styles
 *
 * @package MegaMenuBlock
 */

namespace MegaMenuBlock;

// Exit if accessed directly.
if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Generates the styles that widen a Mega Menu beyond its Navigation item.
 *
 * A Navigation item is `position: relative`, so core's absolutely positioned submenu
 * container can only ever be as wide as the link it belongs to. These styles neutralize
 * that positioning on items containing a Mega Menu and stretch the submenu container
 * across a positioning context instead — the Navigation block by default, or whatever
 * ancestor the theme points the `mega_menu_block_positioning_context` filter at.
 */
class Styles {

	/**
	 * Default positioning context selector.
	 *
	 * @var string
	 */
	const DEFAULT_CONTEXT = '.wp-block-navigation';

	/**
	 * Default navigation breakpoint.
	 *
	 * Matches the width at which core's Navigation block leaves its overlay menu.
	 *
	 * @var string
	 */
	const DEFAULT_BREAKPOINT = '782px';

	/**
	 * Style handles that have already received the inline styles.
	 *
	 * @var array
	 */
	private static $handled = [];

	/**
	 * Initialize style output.
	 */
	public static function init() {
		// Fires in both the front end and the editor iframe.
		add_action( 'enqueue_block_assets', [ __CLASS__, 'add_inline_styles' ] );
	}

	/**
	 * Get the selector for the element the Mega Menu should span.
	 *
	 * The selector is made `position: relative` so the submenu container resolves its
	 * `left`/`right`/`top` offsets against it. Point it at a full-width header wrapper to
	 * make Mega Menus span the whole header.
	 *
	 * @return string CSS selector.
	 */
	public static function get_positioning_context() {
		/**
		 * Filters the selector the Mega Menu's submenu container is stretched across.
		 *
		 * @param string $selector CSS selector for an ancestor of the Navigation block.
		 */
		$selector = apply_filters( 'mega_menu_block_positioning_context', self::DEFAULT_CONTEXT );

		$selector = self::sanitize_css_value( $selector );

		return '' === $selector ? self::DEFAULT_CONTEXT : $selector;
	}

	/**
	 * Get the maximum width of the Mega Menu's submenu container.
	 *
	 * @return string CSS length or keyword.
	 */
	public static function get_max_width() {
		/**
		 * Filters the max width of the Mega Menu's submenu container.
		 *
		 * @param string $max_width Any CSS `max-width` value.
		 */
		$max_width = apply_filters( 'mega_menu_block_max_width', 'none' );

		$max_width = self::sanitize_css_value( $max_width );

		return '' === $max_width ? 'none' : $max_width;
	}

	/**
	 * Get the width at which the Navigation block stops being a mobile drawer.
	 *
	 * Below this width the Mega Menu is left in the document flow, so an overlay or
	 * off-canvas menu keeps working. Set it to the theme's own navigation breakpoint.
	 *
	 * @return string CSS length.
	 */
	public static function get_breakpoint() {
		/**
		 * Filters the min-width at which Mega Menus are positioned as dropdown panels.
		 *
		 * @param string $breakpoint Any CSS length.
		 */
		$breakpoint = apply_filters( 'mega_menu_block_breakpoint', self::DEFAULT_BREAKPOINT );

		$breakpoint = self::sanitize_css_value( $breakpoint );

		return '' === $breakpoint ? self::DEFAULT_BREAKPOINT : $breakpoint;
	}

	/**
	 * Build the inline CSS.
	 *
	 * @return string CSS.
	 */
	public static function get_inline_css() {
		$context    = self::get_positioning_context();
		$max_width  = self::get_max_width();
		$breakpoint = self::get_breakpoint();

		/*
		 * Everything between the positioning context and the submenu container has to be
		 * unpositioned, or it becomes the containing block instead of the context. That chain
		 * is the Navigation block, its items, and any theme or editor wrappers in between, so
		 * it is matched by what it contains rather than by class name.
		 */
		$ancestors = ':has(.wp-block-navigation__submenu-container .wp-block-mega-menu)';

		// Repeat the submenu class so these rules outweigh core and theme Navigation styles.
		$submenu = str_repeat( '.wp-block-navigation__submenu-container', 3 );

		return <<<CSS
@media (min-width: {$breakpoint}) {
	{$context} {
		position: relative;
	}

	{$context} {$ancestors} {
		position: static;
	}

	{$context} {$submenu}:has(.wp-block-mega-menu) {
		top: 100%;
		left: 0;
		right: 0;
		width: auto;
		min-width: 0;
		max-width: {$max_width};
	}
}
CSS;
	}

	/**
	 * Attach the inline CSS to the block's registered stylesheets.
	 */
	public static function add_inline_styles() {
		$block_type = \WP_Block_Type_Registry::get_instance()->get_registered( 'mega-menu-block/mega-menu' );

		if ( ! $block_type ) {
			return;
		}

		$handles = array_merge(
			(array) $block_type->style_handles,
			(array) $block_type->editor_style_handles
		);

		$css = self::get_inline_css();

		foreach ( $handles as $handle ) {
			if ( isset( self::$handled[ $handle ] ) || ! wp_style_is( $handle, 'registered' ) ) {
				continue;
			}

			self::$handled[ $handle ] = true;

			wp_add_inline_style( $handle, $css );
		}
	}

	/**
	 * Strip characters that would let a filtered value escape its declaration or the
	 * surrounding style tag. The child combinator is left intact so selectors keep working.
	 *
	 * @param mixed $value Filtered value.
	 * @return string Sanitized value.
	 */
	private static function sanitize_css_value( $value ) {
		if ( ! \is_string( $value ) ) {
			return '';
		}

		return trim( str_replace( [ '{', '}', ';', '<', '*/' ], '', $value ) );
	}
}

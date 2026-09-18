<?php
/**
 * Block Registration Class
 *
 * @package MegaMenuBlock
 */

namespace MegaMenuBlock;

use WP_Block_Type_Registry;

// Exit if accessed directly.
if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Block Registration Handler
 */
class Registration {

	/**
	 * Initialize block registration.
	 */
	public static function init() {
		// Register the block.
		self::register_block();

		// Enqueue block assets.
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'enqueue_editor_assets' ] );

		// Output the styles that widen the Mega Menu beyond its Navigation item.
		Styles::init();

		// Explicitly allow block in Navigation block children.
		add_filter( 'block_editor_settings_all', [ __CLASS__, 'allow_in_navigation' ], 10, 2 );

		// Filter navigation post content to mask nested Navigation blocks.
		// Use get_post filter to modify post content when WordPress reads it.
		add_filter( 'get_post', [ Renderer::class, 'filter_navigation_post_content' ], 10, 2 );

		// Mask nested Navigation blocks during parent Navigation block rendering.
		// Use priority 1 to run VERY early, before WordPress's own processing.
		add_filter( 'render_block_data', [ Renderer::class, 'mask_nested_navigation_blocks' ], 1 );

		// Allow Navigation blocks with nested Navigation blocks to render.
		// Use priority 10 to run AFTER mask_nested_navigation_blocks has stored the nested blocks.
		add_filter( 'pre_render_block', [ Renderer::class, 'allow_nested_navigation_rendering' ], 10, 3 );

		// Inject nested Navigation blocks back into rendered output.
		// Use priority 20 to run after WordPress renders the block.
		add_filter( 'render_block', [ Renderer::class, 'inject_nested_navigation_blocks' ], 20, 2 );

		// Apply the theme's default attributes. Runs late on `init` so that attributes added
		// by block supports (backgroundColor, textColor, style) are already registered.
		add_action( 'init', [ __CLASS__, 'register_default_attributes' ], 100 );

		// Block supports read the attributes parsed from the markup, which do not include
		// registered defaults, so fill them in before the block renders.
		add_filter( 'render_block_data', [ __CLASS__, 'apply_default_attributes' ], 5 );
	}

	/**
	 * Apply the theme's default attributes to the registered block type.
	 *
	 * Setting them as registered defaults means the editor shows them on a newly inserted
	 * block, and WordPress leaves them out of the saved markup.
	 */
	public static function register_default_attributes() {
		$defaults = Template::get_default_attributes();

		if ( empty( $defaults ) ) {
			return;
		}

		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( 'mega-menu-block/mega-menu' );

		if ( ! $block_type || ! \is_array( $block_type->attributes ) ) {
			return;
		}

		$attributes = $block_type->attributes;

		foreach ( $defaults as $name => $value ) {
			if ( ! isset( $attributes[ $name ] ) ) {
				continue;
			}

			$attributes[ $name ]['default'] = $value;
		}

		$block_type->attributes = $attributes;
	}

	/**
	 * Fill in the theme's default attributes before a Mega Menu block renders.
	 *
	 * Only attributes the author has not set are filled, so existing content is untouched.
	 *
	 * @param array $parsed_block The parsed block data.
	 * @return array Modified block data.
	 */
	public static function apply_default_attributes( $parsed_block ) {
		if ( empty( $parsed_block['blockName'] ) || 'mega-menu-block/mega-menu' !== $parsed_block['blockName'] ) {
			return $parsed_block;
		}

		$defaults = Template::get_default_attributes();

		if ( empty( $defaults ) ) {
			return $parsed_block;
		}

		if ( ! isset( $parsed_block['attrs'] ) || ! \is_array( $parsed_block['attrs'] ) ) {
			$parsed_block['attrs'] = [];
		}

		foreach ( $defaults as $name => $value ) {
			if ( ! isset( $parsed_block['attrs'][ $name ] ) ) {
				$parsed_block['attrs'][ $name ] = $value;
			}
		}

		return $parsed_block;
	}

	/**
	 * Explicitly allow Mega Menu block in Navigation block children.
	 *
	 * @param array $settings Editor settings.
	 * @param array $context   Editor context.
	 * @return array Modified settings.
	 */
	public static function allow_in_navigation( $settings, $context ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter -- Required by the filter signature.
		// Ensure our block is allowed in Navigation block contexts.
		if ( ! isset( $settings['allowedBlockTypes'] ) || true === $settings['allowedBlockTypes'] ) {
			return $settings;
		}

		// If allowedBlockTypes is an array, ensure our block is included.
		if ( \is_array( $settings['allowedBlockTypes'] ) ) {
			$settings['allowedBlockTypes'][] = 'mega-menu-block/mega-menu';
		}

		return $settings;
	}

	/**
	 * Attach the default template data to the editor script.
	 *
	 * Runs before the script is enqueued so the data is inlined ahead of it.
	 */
	public static function add_editor_data() {
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( 'mega-menu-block/mega-menu' );

		if ( ! $block_type || empty( $block_type->editor_script_handles ) ) {
			return;
		}

		foreach ( $block_type->editor_script_handles as $script_handle ) {
			if ( ! wp_script_is( $script_handle, 'registered' ) ) {
				continue;
			}

			wp_add_inline_script( $script_handle, Template::get_inline_script(), 'before' );
		}
	}

	/**
	 * Enqueue block editor assets.
	 */
	public static function enqueue_editor_assets() {
		$block_type = WP_Block_Type_Registry::get_instance()->get_registered( 'mega-menu-block/mega-menu' );

		$script_handles = $block_type->editor_script_handles;

		if ( ! $block_type || empty( $script_handles ) ) {
			return;
		}

		foreach ( $script_handles as $script_handle ) {
			if ( ! wp_script_is( $script_handle, 'registered' ) || wp_script_is( $script_handle, 'enqueued' ) ) {
				continue;
			}

			wp_enqueue_script( $script_handle );
		}
	}

	/**
	 * Register the block.
	 */
	public static function register_block() {
		// Check if block.json exists.
		$block_json_path = MEGA_MENU_BLOCK_PATH . 'block.json';
		if ( ! file_exists( $block_json_path ) ) {
			return;
		}

		// Register block type from metadata.
		$block_type = register_block_type_from_metadata(
			$block_json_path,
			[
				'render_callback' => [ Renderer::class, 'render' ],
			]
		);

		if ( ! $block_type ) {
			return;
		}

		$script_handles = $block_type->editor_script_handles;

		if ( empty( $script_handles ) ) {
			return;
		}

		// Get the script asset file for dependencies and version.
		$script_asset_path = MEGA_MENU_BLOCK_PATH . 'build/index.asset.php';
		$script_asset      = file_exists( $script_asset_path ) ? require $script_asset_path : [
			'dependencies' => [ 'react', 'wp-block-editor', 'wp-blocks', 'wp-i18n' ],
			'version'      => MEGA_MENU_BLOCK_VERSION,
		];

		foreach ( $script_handles as $script_handle ) {
			if ( ! wp_script_is( $script_handle, 'registered' ) ) {
				continue;
			}

			wp_register_script(
				$script_handle,
				MEGA_MENU_BLOCK_URL . 'build/index.js',
				$script_asset['dependencies'],
				$script_asset['version'],
				true
			);

			// Set script translations.
			wp_set_script_translations(
				$script_handle,
				'mega-menu-block',
				MEGA_MENU_BLOCK_PATH . 'languages'
			);
		}

		// Expose the default inner blocks template to the editor script.
		add_action( 'enqueue_block_editor_assets', [ __CLASS__, 'add_editor_data' ], 6 );

		// Ensure the script is enqueued in the editor.
		add_action(
			'enqueue_block_editor_assets',
			function () use ( $script_handles ) {
				foreach ( $script_handles as $script_handle ) {
					if ( ! wp_script_is( $script_handle, 'enqueued' ) ) {
						wp_enqueue_script( $script_handle );
					}
				}
			},
			5
		);
	}
}

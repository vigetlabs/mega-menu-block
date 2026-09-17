<?php
/**
 * Default Inner Blocks Template
 *
 * @package MegaMenuBlock
 */

namespace MegaMenuBlock;

// Exit if accessed directly.
if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Supplies the default inner blocks template used when a Mega Menu block is inserted.
 *
 * Themes can override the template with the `mega_menu_block_default_template` filter,
 * or from the editor with the `megaMenuBlock.defaultTemplate` JavaScript filter.
 *
 * Default block attributes can be set with the `mega_menu_block_default_attributes` filter.
 */
class Template {

	/**
	 * Script data object exposed to the editor.
	 *
	 * @var string
	 */
	const JS_GLOBAL = 'megaMenuBlockEditorData';

	/**
	 * Get the default inner blocks template.
	 *
	 * The template uses the same nested array format as the block editor's
	 * `template` setting: `[ [ 'block/name', $attributes, $inner_blocks ] ]`.
	 *
	 * @return array Block template.
	 */
	public static function get_default_template() {
		$template = [
			[ 'core/paragraph', [], [] ],
		];

		/**
		 * Filters the default inner blocks template for the Mega Menu block.
		 *
		 * @param array $template Nested array of `[ $name, $attributes, $inner_blocks ]` entries.
		 */
		return (array) apply_filters( 'mega_menu_block_default_template', $template );
	}

	/**
	 * Get the default template lock.
	 *
	 * @return string|false One of 'all', 'insert', 'contentOnly', or false for no lock.
	 */
	public static function get_default_template_lock() {
		/**
		 * Filters the template lock applied to the Mega Menu block's inner blocks.
		 *
		 * @param string|false $lock One of 'all', 'insert', 'contentOnly', or false.
		 */
		return apply_filters( 'mega_menu_block_default_template_lock', false );
	}

	/**
	 * Get the default attributes applied to Mega Menu blocks.
	 *
	 * Values are used as the registered attribute defaults, so the editor shows them on a
	 * newly inserted block and leaves them out of the saved markup. They are also filled in
	 * at render time, since block supports read the attributes parsed from the markup.
	 *
	 * An author's own choice always wins: only attributes absent from the markup are filled.
	 *
	 * @return array Attribute values keyed by attribute name.
	 */
	public static function get_default_attributes() {
		/**
		 * Filters the default attributes applied to Mega Menu blocks.
		 *
		 * Example: set the background colour every new Mega Menu should start with.
		 *
		 *     add_filter(
		 *         'mega_menu_block_default_attributes',
		 *         function ( $attributes ) {
		 *             $attributes['backgroundColor'] = 'primary';
		 *             return $attributes;
		 *         }
		 *     );
		 *
		 * @param array $attributes Attribute values keyed by attribute name.
		 */
		return (array) apply_filters( 'mega_menu_block_default_attributes', [] );
	}

	/**
	 * Get the editor data passed to the block's editor script.
	 *
	 * @return array Editor data.
	 */
	public static function get_editor_data() {
		return [
			'defaultTemplate'     => self::normalize_template( self::get_default_template() ),
			'defaultTemplateLock' => self::get_default_template_lock(),
		];
	}

	/**
	 * Build the inline script that exposes the editor data.
	 *
	 * @return string JavaScript.
	 */
	public static function get_inline_script() {
		return sprintf(
			'window.%1$s = %2$s;',
			self::JS_GLOBAL,
			wp_json_encode( self::get_editor_data() )
		);
	}

	/**
	 * Normalize a template so it survives JSON encoding.
	 *
	 * Empty attribute arrays would otherwise encode as `[]` instead of `{}`, which the
	 * block editor rejects. Objects are used for attributes and arrays for inner blocks.
	 *
	 * @param array $template Block template.
	 * @return array Normalized template.
	 */
	private static function normalize_template( array $template ) {
		$normalized = [];

		foreach ( $template as $entry ) {
			if ( ! \is_array( $entry ) || ! isset( $entry[0] ) || ! \is_string( $entry[0] ) ) {
				continue;
			}

			$attributes   = isset( $entry[1] ) && \is_array( $entry[1] ) ? $entry[1] : [];
			$inner_blocks = isset( $entry[2] ) && \is_array( $entry[2] ) ? $entry[2] : [];

			$normalized[] = [
				$entry[0],
				empty( $attributes ) ? new \stdClass() : (object) $attributes,
				self::normalize_template( $inner_blocks ),
			];
		}

		return $normalized;
	}
}

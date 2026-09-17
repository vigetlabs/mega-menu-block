=== Mega Menu Block ===
Contributors: viget, briandichiara
Tags: blocks, navigation, menu, mega-menu, gutenberg
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A custom WordPress block that enables mega menu functionality within Navigation blocks, allowing any blocks to be added as children of Navigation items.

== Description ==

Mega Menu Block is a custom WordPress plugin that adds a Mega Menu block to the Full Site Editor. This block can be added as a child of Navigation block items (Page Link and Custom Link), similar to the core Submenu block.

== Features ==

* Add Mega Menu block as a child of Navigation items
* Insert any available blocks inside the Mega Menu
* Behaves like the core Group block for maximum flexibility
* Honors Navigation block submenu settings (hover/click behavior)
* Displays inline in the editor when parent link is selected
* Fully compatible with Full Site Editor
* Default inner blocks template and block attributes can be overridden by the theme
* Mega Menus span a full-width panel instead of the width of their Navigation item

== Customization ==

= Overriding the default template =

A newly inserted Mega Menu starts with a single empty paragraph. Themes can replace that
starter content with the `mega_menu_block_default_template` filter. The value uses the same
nested array format as the block editor's `template` setting:
`[ $block_name, $attributes, $inner_blocks ]`.

`
add_filter(
	'mega_menu_block_default_template',
	function( $template ) {
		return [
			[
				'core/columns',
				[],
				[
					[ 'core/column', [], [ [ 'core/heading', [ 'level' => 3 ] ] ] ],
					[ 'core/column', [], [ [ 'core/navigation', [ 'overlayMenu' => 'never' ] ] ] ],
				],
			],
		];
	}
);
`

Use `mega_menu_block_default_template_lock` to lock the template. It accepts `all`,
`insert`, `contentOnly`, or `false` (the default, meaning no lock).

`
add_filter( 'mega_menu_block_default_template_lock', fn() => 'insert' );
`

Both values are also filterable from editor JavaScript via `@wordpress/hooks`, which is
useful when the template needs to be built client side:

`
wp.hooks.addFilter(
	'megaMenuBlock.defaultTemplate',
	'my-theme/mega-menu-template',
	( template ) => [ [ 'core/paragraph', {} ] ]
);
`

= Setting default block attributes =

`mega_menu_block_default_attributes` sets the attributes a newly inserted Mega Menu starts
with -- a background colour, say, so every panel matches without an author picking one.

They are applied as registered attribute defaults, so the editor shows them on insertion and
WordPress leaves them out of the saved markup. They are also filled in at render time,
because block supports read the attributes parsed from the markup, which do not include
registered defaults. An author's own choice always wins: only attributes absent from the
markup are filled.

`
add_filter(
	'mega_menu_block_default_attributes',
	function( $attributes ) {
		$attributes['backgroundColor'] = 'primary';
		return $attributes;
	}
);
`

= Controlling the Mega Menu's width =

A Navigation item is `position: relative`, so core's submenu container can only ever be as
wide as the link it hangs off. The plugin neutralizes that on items containing a Mega Menu
and stretches the submenu container across a positioning context instead. By default the
context is the Navigation block, which is already far wider than a single link.

To make Mega Menus span the whole header, point the context at a full-width ancestor:

`
add_filter( 'mega_menu_block_positioning_context', fn() => '.site-header' );
`

These rules only apply above a navigation breakpoint, so an overlay or off-canvas mobile
menu keeps its Mega Menus in the document flow. The default is `782px`, matching core's
Navigation block. Set it to the theme's own navigation breakpoint:

`
add_filter( 'mega_menu_block_breakpoint', fn() => '600px' );
`

To keep the panel from filling the entire context, cap its width:

`
add_filter( 'mega_menu_block_max_width', fn() => '1200px' );
`

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/mega-menu-block` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Run `npm install` in the plugin directory to install dependencies.
4. Run `npm run build` to build the assets.
5. Use the Mega Menu block in your Navigation blocks.

== Frequently Asked Questions ==

= How do I use the Mega Menu block? =

Add a Navigation block to your site, then add a Page Link or Custom Link. When you select the link, you'll see the option to add a Mega Menu block as a child. Click the inserter and select "Mega Menu" to add it.

= Can I add any blocks inside the Mega Menu? =

Yes! The Mega Menu block behaves like the core Group block and allows any available blocks to be inserted inside it.

= Does it work with Navigation block settings? =

Yes, the Mega Menu honors the Navigation block's submenu settings, including hover/click behavior and overlay menu settings.

== Changelog ==

= 1.1.0 =
* The default inner blocks template is now a single empty paragraph instead of a three-column layout.
* Mega Menus now span a positioning context wider than their Navigation item, in the editor and on the front end.
* Added the `mega_menu_block_positioning_context`, `mega_menu_block_breakpoint`, and `mega_menu_block_max_width` PHP filters.
* Added the `mega_menu_block_default_template`, `mega_menu_block_default_template_lock`, and `mega_menu_block_default_attributes` PHP filters.
* Added the `megaMenuBlock.defaultTemplate` and `megaMenuBlock.defaultTemplateLock` JavaScript filters.
* Checks GitHub releases for plugin updates from the WordPress dashboard.
* Updated to @wordpress/scripts 30, which also emits RTL stylesheets.
* Added a POT file, GPL-2.0 license, Composer support, CI, and developer documentation.

= 1.0.1 =
* Fixed a scroll issue in the full site editor.

= 1.0.0 =
* Initial release
* Mega Menu block registration
* Support for all block types inside Mega Menu
* Integration with Navigation block

== Upgrade Notice ==

= 1.0.0 =
Initial release of Mega Menu Block.


=== Mega Menu Block ===
Contributors: viget, briandichiara
Tags: blocks, navigation, menu, mega-menu, gutenberg
Requires at least: 6.0
Tested up to: 6.9
Stable tag: 1.0.1
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

= 1.0.0 =
* Initial release
* Mega Menu block registration
* Support for all block types inside Mega Menu
* Integration with Navigation block

== Upgrade Notice ==

= 1.0.0 =
Initial release of Mega Menu Block.


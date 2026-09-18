# Developer API

Every filter below is optional - the plugin works with none of them set.

## PHP filters

### `mega_menu_block_positioning_context`

CSS selector the Mega Menu panel is stretched across.

A Navigation item is `position: relative`, so core's submenu container can only ever be as wide as the link it hangs off. The plugin neutralizes that on items containing a Mega Menu and stretches the submenu container across this selector instead.

- **Default:** `.wp-block-navigation`
- **Returns:** `string`

```php
add_filter( 'mega_menu_block_positioning_context', fn() => '.site-header' );
```

The selector must be an *ancestor* of the Navigation block. Everything between it and the submenu container is set to `position: static` automatically, matched by what it contains rather than by class name, so theme and editor wrappers in between don't need to be named.

### `mega_menu_block_breakpoint`

Minimum viewport width at which Mega Menus are positioned as dropdown panels. Below it they're left in the document flow so an overlay or off-canvas mobile menu keeps working.

- **Default:** `782px` (matches core's Navigation overlay breakpoint)
- **Returns:** `string` - any CSS length

```php
add_filter( 'mega_menu_block_breakpoint', fn() => '880px' );
```

### `mega_menu_block_max_width`

Caps the panel's width inside the positioning context.

- **Default:** `none`
- **Returns:** `string` - any CSS `max-width` value

```php
add_filter( 'mega_menu_block_max_width', fn() => '1200px' );
```

### `mega_menu_block_default_template`

Blocks a newly inserted Mega Menu starts with. Same nested array format as the block editor's `template` setting: `[ $block_name, $attributes, $inner_blocks ]`.

- **Default:** `[ [ 'core/paragraph', [], [] ] ]`
- **Returns:** `array`

```php
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
```

### `mega_menu_block_default_template_lock`

Template lock applied to the Mega Menu's inner blocks.

- **Default:** `false`
- **Returns:** `'all'`, `'insert'`, `'contentOnly'`, or `false`

```php
add_filter( 'mega_menu_block_default_template_lock', fn() => 'insert' );
```

### `mega_menu_block_default_attributes`

Attribute values a newly inserted Mega Menu starts with, keyed by attribute name.

- **Default:** `[]`
- **Returns:** `array`

```php
add_filter(
	'mega_menu_block_default_attributes',
	function( $attributes ) {
		$attributes['backgroundColor'] = 'primary';
		return $attributes;
	}
);
```

These are applied in two places. They're set as registered attribute defaults on `init` priority 100 - after block supports have registered their own attributes - so the editor shows them on a newly inserted block and WordPress leaves them out of the saved markup. They're also filled in on `render_block_data`, because block supports read the attributes parsed from the markup, which don't include registered defaults.

An author's own choice always wins: only attributes absent from the markup are filled, so existing content is untouched when the filter changes.

## JavaScript filters

Both are applied through `@wordpress/hooks` in the editor, for cases where the template has to be built client side. They run on the value already resolved by their PHP counterpart.

### `megaMenuBlock.defaultTemplate`

```js
wp.hooks.addFilter(
	'megaMenuBlock.defaultTemplate',
	'my-theme/mega-menu-template',
	( template ) => [ [ 'core/paragraph', {} ] ]
);
```

### `megaMenuBlock.defaultTemplateLock`

```js
wp.hooks.addFilter(
	'megaMenuBlock.defaultTemplateLock',
	'my-theme/mega-menu-lock',
	() => 'insert'
);
```

## PHP classes

All under the `MegaMenuBlock` namespace.

| Class | Purpose |
|---|---|
| `Block` | Plugin bootstrap. Loads the text domain, registers the block on `init`, wires the updater on `admin_init`. |
| `Registration` | Registers the block type, editor assets, and default attributes. |
| `Renderer` | Server-side render callback, including re-injecting nested Navigation blocks. |
| `Styles` | Builds and enqueues the inline CSS that widens the panel. `Styles::get_inline_css()` returns it if you need to inspect what the filters produced. |
| `Template` | Resolves the default template, lock, and attributes, and exposes them to the editor script. |
| `GitHub_Plugin_Updater` | Checks GitHub releases for updates. |

## Storage

The plugin stores no options or post meta. A Mega Menu's content lives inside the Navigation block's `wp_navigation` post, like any other Navigation child block. The only thing it writes is the updater's release cache, in two site transients, which `uninstall.php` removes.

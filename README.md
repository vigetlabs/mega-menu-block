<!-- markdownlint-disable MD033 MD041 -->
<p align="left">
  <img src="https://img.shields.io/badge/WordPress-6.6%2B-21759b?logo=wordpress&logoColor=white" alt="WordPress 6.6+">
  <img src="https://img.shields.io/badge/PHP-7.4%2B-777bb4?logo=php&logoColor=white" alt="PHP 7.4+">
  <img src="https://img.shields.io/github/v/release/vigetlabs/mega-menu-block" alt="Latest release">
  <img src="https://img.shields.io/github/actions/workflow/status/vigetlabs/mega-menu-block/ci.yaml?branch=main&label=CI" alt="CI status">
  <img src="https://img.shields.io/badge/license-GPL--2.0--or--later-blue" alt="License">
</p>

# Mega Menu Block

Adds a Mega Menu block that can be inserted inside a Navigation item, so a menu link can open a panel containing any blocks - columns, headings, images, a nested Navigation, whatever the design calls for.

## Features

- Inserts as a child of `core/navigation-link` or `core/navigation-submenu`, so it works anywhere a Navigation block does.
- Accepts any block inside, and behaves like a Group block for layout and styling.
- Honors the Navigation block's own submenu settings (hover vs click).
- Panels span a positioning context wider than their Navigation item, in the editor and on the front end.
- The starter template, template lock, and default block attributes are all overridable by the theme.
- Works in the Site Editor and on the front end.
- Checks for updates from GitHub releases directly in the WordPress dashboard.

## Installation

### Manual

1. Download the [latest release](https://github.com/vigetlabs/mega-menu-block/releases/latest) zip.
2. Upload it via **Plugins → Add New → Upload Plugin**, or extract it to `wp-content/plugins/mega-menu-block/`.
3. Activate the plugin.

### Composer

```bash
composer require viget/mega-menu-block
```

Requires [`composer/installers`](https://github.com/composer/installers) (installed automatically as a dependency) to place the plugin in `wp-content/plugins/`.

## Usage

1. Edit a Navigation block.
2. Select a menu item and use the **Add Mega Menu** toolbar button.
3. Add any blocks inside the panel.

By default the panel spans the Navigation block and opens at `782px` and up, matching core's Navigation breakpoint. Below that it stays in the document flow so an overlay or off-canvas mobile menu keeps working.

## Theme integration

Most projects set three things:

```php
// Span the whole header rather than just the Navigation block.
add_filter( 'mega_menu_block_positioning_context', fn() => '.site-header' );

// Match the theme's own navigation breakpoint.
add_filter( 'mega_menu_block_breakpoint', fn() => '880px' );

// Start every new Mega Menu on the same background.
add_filter(
	'mega_menu_block_default_attributes',
	function( $attributes ) {
		$attributes['backgroundColor'] = 'primary';
		return $attributes;
	}
);
```

Every filter, in PHP and in editor JavaScript, is documented in [docs/api.md](docs/api.md).

## Auto-Updates

This plugin checks GitHub releases every 12 hours and surfaces available updates in **Plugins** in wp-admin - no wordpress.org listing required. See [`includes/class-github-plugin-updater.php`](includes/class-github-plugin-updater.php).

Note: shipping a custom update checker is intentionally incompatible with wordpress.org's plugin directory (the [Plugin Check](https://wordpress.org/plugins/plugin-check/) tool flags it as a hard error under `plugin_updater_detected`). If this plugin is ever submitted there, the updater would need to be removed first.

## Multisite

Network activation is supported. The block registers per site on `init`, and the updater caches release data in site transients, so one network-wide check covers every site. The plugin stores no options or post meta of its own - a Mega Menu's content lives in the Navigation block's `wp_navigation` post.

## Development

```bash
npm install
npm start          # watch build
npm run build      # production build (commit the result - CI checks it)
npm run lint:js
npm run lint:css
npm run lint:php
npm run env:start  # local WordPress at http://localhost:8888
npm run update-pot # regenerate languages/mega-menu-block.pot
```

`build/` is committed so the plugin runs straight from a git checkout. CI fails if it's out of date.

### Releasing

```bash
npm run release -- patch   # or minor / major
```

That bumps `package.json`, syncs the version into the plugin header and `readme.txt` via `bin/sync-version.js`, commits, tags, and pushes. The tag triggers `.github/workflows/release.yaml`, which builds the zip and creates the GitHub release the updater reads.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

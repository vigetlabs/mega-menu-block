<?php
/**
 * Plugin Name:       Mega Menu Block
 * Plugin URI:        https://github.com/vigetlabs/mega-menu-block
 * Description:       A custom block that enables mega menu functionality within Navigation blocks, allowing any blocks to be added as children of Navigation items.
 * Version:           1.1.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Viget
 * Author URI:        https://viget.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       mega-menu-block
 * Domain Path:       /languages
 *
 * @package MegaMenuBlock
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin version.
const MEGA_MENU_BLOCK_VERSION = '1.1.0';

// Plugin file.
const MEGA_MENU_BLOCK_PLUGIN_FILE = __FILE__;

// Plugin directory path.
define( 'MEGA_MENU_BLOCK_PATH', plugin_dir_path( MEGA_MENU_BLOCK_PLUGIN_FILE ) );

// Plugin directory URL.
define( 'MEGA_MENU_BLOCK_URL', plugin_dir_url( MEGA_MENU_BLOCK_PLUGIN_FILE ) );

// Include required files.
require_once MEGA_MENU_BLOCK_PATH . 'includes/class-template.php';
require_once MEGA_MENU_BLOCK_PATH . 'includes/class-styles.php';
require_once MEGA_MENU_BLOCK_PATH . 'includes/class-registration.php';
require_once MEGA_MENU_BLOCK_PATH . 'includes/class-renderer.php';

// Include the main plugin class.
require_once MEGA_MENU_BLOCK_PATH . 'includes/class-block.php';

// Initialize the plugin.
MegaMenuBlock\Block::get_instance();

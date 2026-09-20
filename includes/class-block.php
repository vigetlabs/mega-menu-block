<?php
/**
 * Main Plugin Class
 *
 * @package MegaMenuBlock
 */

namespace MegaMenuBlock;

// Exit if accessed directly.
if ( ! \defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main Mega Menu Block Plugin Class
 */
class Block {

	/**
	 * Plugin instance.
	 *
	 * @var Block
	 */
	private static $instance = null;

	/**
	 * Get plugin instance.
	 *
	 * @return Block
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Load plugin text domain.
		add_action( 'plugins_loaded', [ $this, 'load_textdomain' ] );

		// Initialize block registration.
		add_action( 'init', [ $this, 'init_blocks' ] );

		// Check for plugin updates from GitHub releases.
		add_action( 'admin_init', [ $this, 'init_updater' ] );
	}

	/**
	 * Check for updates from GitHub releases.
	 *
	 * Runs on admin_init so the HTTP request only happens in the dashboard. On
	 * multisite the updater caches with site transients, so one network-wide
	 * check covers every site.
	 */
	public function init_updater() {
		require_once MEGA_MENU_BLOCK_PATH . 'includes/class-github-plugin-updater.php';

		new GitHub_Plugin_Updater( MEGA_MENU_BLOCK_PLUGIN_FILE, 'vigetlabs', 'mega-menu-block' );
	}

	/**
	 * Load plugin text domain for translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'mega-menu-block',
			false,
			dirname( plugin_basename( MEGA_MENU_BLOCK_PATH . 'mega-menu-block.php' ) ) . '/languages'
		);
	}

	/**
	 * Initialize block registration.
	 */
	public function init_blocks() {
		Registration::init();
	}
}

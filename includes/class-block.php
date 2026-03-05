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


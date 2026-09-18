<?php
/**
 * Uninstall routine for Mega Menu Block
 *
 * The block stores nothing of its own - menu content lives in the Navigation
 * block's post content and is left alone. Only the GitHub updater's release
 * cache is removed.
 *
 * @package MegaMenuBlock
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$mmb_transient_key = 'mmb_github_updater_' . md5( 'vigetlabs/mega-menu-block' );

delete_site_transient( $mmb_transient_key );
delete_site_transient( $mmb_transient_key . '_error' );

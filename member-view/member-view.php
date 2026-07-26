<?php
/**
 * Plugin Name:       Member View
 * Plugin URI:        https://github.com/
 * Description:       Two-tier access: Visitors (logged-out or "Visitor" role) see only a configurable landing page and a login modal; the Community (any other logged-in role) gets standard WordPress access.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       member-view
 * Domain Path:       /languages
 *
 * @package MemberView
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // No direct access.
}

define( 'MEMBER_VIEW_VERSION', '1.0.0' );
define( 'MEMBER_VIEW_FILE', __FILE__ );
define( 'MEMBER_VIEW_BASENAME', plugin_basename( __FILE__ ) );
define( 'MEMBER_VIEW_DIR', plugin_dir_path( __FILE__ ) );
define( 'MEMBER_VIEW_URL', plugin_dir_url( __FILE__ ) );

/**
 * Role slug for this plugin's "Visitor" role. Treated identically to a
 * logged-out user everywhere in this plugin.
 */
define( 'MEMBER_VIEW_ROLE', 'member_view_visitor' );

require_once MEMBER_VIEW_DIR . 'includes/helpers.php';
require_once MEMBER_VIEW_DIR . 'includes/roles.php';
require_once MEMBER_VIEW_DIR . 'includes/gate.php';
require_once MEMBER_VIEW_DIR . 'includes/modal.php';
require_once MEMBER_VIEW_DIR . 'includes/settings.php';
require_once MEMBER_VIEW_DIR . 'includes/preview.php';
require_once MEMBER_VIEW_DIR . 'includes/users-columns.php';
require_once MEMBER_VIEW_DIR . 'includes/updater.php';

/**
 * Load translations.
 */
function member_view_load_textdomain() {
	load_plugin_textdomain( 'member-view', false, dirname( MEMBER_VIEW_BASENAME ) . '/languages' );
}
add_action( 'init', 'member_view_load_textdomain' );

/**
 * Activation. On a multisite Network Activate, provision every existing site;
 * a plain per-site Activate provisions only the current site.
 *
 * @param bool $network_wide True when network-activated.
 */
function member_view_activate( $network_wide ) {
	if ( is_multisite() && $network_wide ) {
		$site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
		foreach ( $site_ids as $site_id ) {
			switch_to_blog( (int) $site_id );
			member_view_provision_site();
			restore_current_blog();
		}
		return;
	}

	member_view_provision_site();
}
register_activation_hook( __FILE__, 'member_view_activate' );

/**
 * Provision a brand-new subsite created after activation.
 *
 * @param WP_Site $new_site The new site.
 */
function member_view_on_new_site( $new_site ) {
	if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
	}
	if ( ! is_plugin_active_for_network( MEMBER_VIEW_BASENAME ) ) {
		return;
	}
	switch_to_blog( (int) $new_site->blog_id );
	member_view_provision_site();
	restore_current_blog();
}
add_action( 'wp_initialize_site', 'member_view_on_new_site', 900 );

/**
 * Deactivation. Roles/options are left in place; real cleanup happens on
 * uninstall (see uninstall.php) so deactivation is always reversible.
 */
function member_view_deactivate() {
	// Intentionally nothing destructive.
}
register_deactivation_hook( __FILE__, 'member_view_deactivate' );

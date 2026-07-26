<?php
/**
 * Uninstall cleanup. Runs standalone (the main plugin file is NOT loaded), so
 * everything here is self-contained. The action taken on Visitor accounts is
 * whatever the admin chose in advance (member_view_uninstall_visitor_action);
 * it defaults to the non-destructive "keep".
 *
 * @package MemberView
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! defined( 'MEMBER_VIEW_ROLE' ) ) {
	define( 'MEMBER_VIEW_ROLE', 'member_view_visitor' );
}

/**
 * Clean up a single site: honor the chosen Visitor-account action, then remove
 * the plugin's own options and per-site last-login meta.
 */
function member_view_uninstall_site() {
	$action = get_option( 'member_view_uninstall_visitor_action', 'keep' );

	if ( 'keep' !== $action ) {
		if ( ! function_exists( 'get_users' ) ) {
			return;
		}
		$user_ids = get_users(
			array(
				'role'   => MEMBER_VIEW_ROLE,
				'fields' => 'ids',
			)
		);

		if ( 'delete' === $action ) {
			require_once ABSPATH . 'wp-admin/includes/user.php';
			foreach ( $user_ids as $uid ) {
				if ( is_multisite() ) {
					// Remove from this site only; the global account may live elsewhere.
					remove_user_from_blog( $uid, get_current_blog_id() );
				} else {
					wp_delete_user( $uid );
				}
			}
		} elseif ( 'reassign' === $action ) {
			foreach ( $user_ids as $uid ) {
				$user = new WP_User( $uid );
				$user->set_role( 'subscriber' );
			}
		}

		// Remove the role once accounts have been handled.
		remove_role( MEMBER_VIEW_ROLE );
	}

	// Always remove this plugin's own footprint.
	delete_option( 'member_view_landing_page_id' );
	delete_option( 'member_view_uninstall_visitor_action' );
	delete_option( 'member_view_update_repo' );
	delete_option( 'member_view_update_token' );

	// Per-site last-login meta (key is suffixed with blog ID on multisite).
	$meta_key = is_multisite() ? 'member_view_last_login_' . get_current_blog_id() : 'member_view_last_login';
	delete_metadata( 'user', 0, $meta_key, '', true );
	delete_metadata( 'user', 0, 'member_view_requested_at', '', true );
	delete_metadata( 'user', 0, 'member_view_pending_verification', '', true );
	delete_metadata( 'user', 0, 'member_view_preview', '', true );
}

if ( is_multisite() ) {
	$site_ids = get_sites( array( 'fields' => 'ids', 'number' => 0 ) );
	foreach ( $site_ids as $site_id ) {
		switch_to_blog( (int) $site_id );
		member_view_uninstall_site();
		restore_current_blog();
	}
} else {
	member_view_uninstall_site();
}

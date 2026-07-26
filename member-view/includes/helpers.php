<?php
/**
 * Shared access-decision helpers. The whole plugin routes its access checks
 * through here so the Visitor/Community boundary lives in exactly one place.
 *
 * @package MemberView
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Is the current (or given) user a Community member on the current site?
 *
 * Returns true ONLY when the user is logged in AND holds at least one role
 * other than the plugin's Visitor role on the current site. Logged-out users
 * and users whose only role is Visitor (or who hold no role at all on this
 * site) are NOT Community. Super Admins always count as Community.
 *
 * @param int|null $user_id Optional user ID. Defaults to the current user.
 * @return bool
 */
function member_view_is_community( $user_id = null ) {
	$user = ( null === $user_id ) ? wp_get_current_user() : get_userdata( $user_id );

	if ( ! $user || ! $user->exists() ) {
		return false;
	}

	// Network super admins can always see everything.
	if ( is_multisite() && is_super_admin( $user->ID ) ) {
		return true;
	}

	$roles       = (array) $user->roles; // Roles on the CURRENT site.
	$non_visitor = array_diff( $roles, array( MEMBER_VIEW_ROLE ) );

	return ! empty( $non_visitor );
}

/**
 * The configured landing page ID for the current site, or 0 if none/invalid.
 *
 * @return int Published page ID, else 0.
 */
function member_view_get_landing_page_id() {
	$page_id = (int) get_option( 'member_view_landing_page_id', 0 );

	if ( $page_id <= 0 ) {
		return 0;
	}

	$post = get_post( $page_id );
	if ( ! $post || 'page' !== $post->post_type || 'publish' !== $post->post_status ) {
		return 0;
	}

	return $page_id;
}

/**
 * The role new "Request access" sign-ups are created with. Admin-configurable
 * (Settings → Member View), defaulting to the Visitor role. Falls back to
 * Visitor if the stored role no longer exists.
 *
 * @return string Role slug.
 */
function member_view_get_default_signup_role() {
	$role = (string) get_option( 'member_view_default_signup_role', MEMBER_VIEW_ROLE );
	// Administrator is never a valid public-signup role, even if the option is
	// somehow set to it (e.g. via direct DB edit). Fall back to Visitor.
	if ( '' === $role || 'administrator' === $role || ! get_role( $role ) ) {
		return MEMBER_VIEW_ROLE;
	}
	return $role;
}

/**
 * Is the current user in admin "preview as visitor" mode?
 *
 * Only meaningful for a privileged, logged-in user who has toggled preview on
 * for their own session. Never affects anyone else.
 *
 * @return bool
 */
function member_view_is_previewing() {
	$user_id = get_current_user_id();
	if ( ! $user_id ) {
		return false;
	}
	if ( ! current_user_can( 'manage_options' ) ) {
		return false;
	}
	return (bool) get_user_meta( $user_id, 'member_view_preview', true );
}

/**
 * Should the current request be gated (treated as a Visitor)?
 *
 * True for anyone who is not Community, and also for a privileged user who has
 * turned on preview mode against their own session.
 *
 * @return bool
 */
function member_view_should_gate() {
	if ( member_view_is_previewing() ) {
		return true;
	}
	return ! member_view_is_community();
}

/**
 * The per-site user-meta key used to store a user's last-login timestamp.
 *
 * User meta is network-global, so on multisite we suffix the key with the blog
 * ID to keep "Last Login" genuinely per-site.
 *
 * @param int|null $blog_id Optional blog ID. Defaults to the current site.
 * @return string
 */
function member_view_last_login_meta_key( $blog_id = null ) {
	if ( ! is_multisite() ) {
		return 'member_view_last_login';
	}
	$blog_id = $blog_id ? (int) $blog_id : get_current_blog_id();
	return 'member_view_last_login_' . $blog_id;
}

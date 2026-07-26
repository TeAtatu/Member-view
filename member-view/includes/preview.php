<?php
/**
 * "Preview as Visitor" mode. A privileged user can experience the visitor gate
 * against their own session without logging out. It only ever adds gating for
 * that one user; it can never weaken the gate for anyone else.
 *
 * @package MemberView
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Admin-bar toggle.
 *
 * @param WP_Admin_Bar $bar Admin bar instance.
 */
function member_view_admin_bar_toggle( $bar ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}

	if ( member_view_is_previewing() ) {
		$bar->add_node(
			array(
				'id'    => 'member-view-preview',
				'title' => __( 'Exit Visitor Preview', 'member-view' ),
				'href'  => wp_nonce_url( admin_url( 'admin-post.php?action=member_view_preview_off' ), 'member_view_preview' ),
				'meta'  => array( 'title' => __( 'You are viewing the site as a Visitor', 'member-view' ) ),
			)
		);
	} else {
		$bar->add_node(
			array(
				'id'    => 'member-view-preview',
				'title' => __( 'Preview as Visitor', 'member-view' ),
				'href'  => wp_nonce_url( admin_url( 'admin-post.php?action=member_view_preview_on' ), 'member_view_preview' ),
			)
		);
	}
}
add_action( 'admin_bar_menu', 'member_view_admin_bar_toggle', 100 );

/**
 * Turn preview on for the current user.
 */
function member_view_preview_on() {
	member_view_handle_preview_toggle( true );
}
add_action( 'admin_post_member_view_preview_on', 'member_view_preview_on' );

/**
 * Turn preview off for the current user.
 */
function member_view_preview_off() {
	member_view_handle_preview_toggle( false );
}
add_action( 'admin_post_member_view_preview_off', 'member_view_preview_off' );

/**
 * Shared toggle handler.
 *
 * @param bool $on Whether to turn preview on.
 */
function member_view_handle_preview_toggle( $on ) {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_die( esc_html__( 'You are not allowed to do this.', 'member-view' ) );
	}
	check_admin_referer( 'member_view_preview' );

	$user_id = get_current_user_id();
	if ( $on ) {
		update_user_meta( $user_id, 'member_view_preview', 1 );
		$dest = member_view_landing_url();
		if ( '' === $dest ) {
			$dest = home_url( '/' );
		}
	} else {
		delete_user_meta( $user_id, 'member_view_preview' );
		$dest = wp_get_referer();
		if ( ! $dest ) {
			$dest = home_url( '/' );
		}
	}

	wp_safe_redirect( $dest );
	exit;
}

/**
 * Visible banner while previewing, so an admin can't get stuck in it.
 */
function member_view_preview_banner() {
	if ( ! member_view_is_previewing() ) {
		return;
	}
	$exit = wp_nonce_url( admin_url( 'admin-post.php?action=member_view_preview_off' ), 'member_view_preview' );
	printf(
		'<div style="position:fixed;left:0;right:0;bottom:0;z-index:100000;background:#7c2d12;color:#fff;text-align:center;padding:8px 12px;font:14px/1.4 sans-serif;">%s <a style="color:#fde68a;font-weight:600;" href="%s" data-member-view-allow>%s</a></div>',
		esc_html__( 'Previewing as a Visitor.', 'member-view' ),
		esc_url( $exit ),
		esc_html__( 'Exit preview', 'member-view' )
	);
}
add_action( 'wp_footer', 'member_view_preview_banner', 5 );

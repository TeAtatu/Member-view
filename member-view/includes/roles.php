<?php
/**
 * Visitor role provisioning. The role is added per-site (see main file's
 * activation loop and wp_initialize_site hook) with only the 'read'
 * capability — gating is enforced by member_view_is_community(), not by
 * withholding capabilities.
 *
 * @package MemberView
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Ensure the Visitor role exists on the current site with exactly 'read'.
 */
function member_view_provision_site() {
	$role = get_role( MEMBER_VIEW_ROLE );

	if ( ! $role ) {
		add_role(
			MEMBER_VIEW_ROLE,
			_x( 'Visitor', 'Role name', 'member-view' ),
			array( 'read' => true )
		);
		return;
	}

	// Role already present: make sure it still carries only 'read'.
	if ( ! $role->has_cap( 'read' ) ) {
		$role->add_cap( 'read' );
	}
}

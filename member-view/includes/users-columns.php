<?php
/**
 * Users list additions: "Date Created" and "Last Login" columns (both
 * sortable), plus per-site last-login tracking. Last Login is not tracked by
 * core, so we capture it on wp_login.
 *
 * @package MemberView
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Record last-login timestamp (per-site on multisite).
 *
 * @param string       $user_login User login (unused).
 * @param WP_User|null $user       The user.
 */
function member_view_record_last_login( $user_login, $user = null ) {
	if ( ! $user instanceof WP_User ) {
		$user = get_user_by( 'login', $user_login );
	}
	if ( ! $user ) {
		return;
	}
	update_user_meta( $user->ID, member_view_last_login_meta_key(), time() );
}
add_action( 'wp_login', 'member_view_record_last_login', 10, 2 );

/**
 * Add the two columns.
 *
 * @param array $columns Existing columns.
 * @return array
 */
function member_view_user_columns( $columns ) {
	$columns['member_view_created']    = __( 'Date Created', 'member-view' );
	$columns['member_view_last_login'] = __( 'Last Login', 'member-view' );
	return $columns;
}
add_filter( 'manage_users_columns', 'member_view_user_columns' );

/**
 * Render column values.
 *
 * @param string $output      Existing output.
 * @param string $column_name Column key.
 * @param int    $user_id     User ID.
 * @return string
 */
function member_view_user_column_content( $output, $column_name, $user_id ) {
	if ( 'member_view_created' === $column_name ) {
		$user = get_userdata( $user_id );
		if ( $user && ! empty( $user->user_registered ) ) {
			$ts = strtotime( $user->user_registered . ' UTC' );
			return esc_html( wp_date( get_option( 'date_format' ), $ts ) );
		}
		return '&mdash;';
	}

	if ( 'member_view_last_login' === $column_name ) {
		$ts = get_user_meta( $user_id, member_view_last_login_meta_key(), true );
		if ( ! $ts ) {
			return esc_html__( 'Never', 'member-view' );
		}
		return esc_html(
			wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), (int) $ts )
		);
	}

	return $output;
}
add_filter( 'manage_users_custom_column', 'member_view_user_column_content', 10, 3 );

/**
 * Make both columns sortable.
 *
 * @param array $columns Sortable columns.
 * @return array
 */
function member_view_sortable_user_columns( $columns ) {
	$columns['member_view_created']    = 'member_view_created';
	$columns['member_view_last_login'] = 'member_view_last_login';
	return $columns;
}
add_filter( 'manage_users_sortable_columns', 'member_view_sortable_user_columns' );

/**
 * Apply ordering for the custom columns on the Users screen.
 *
 * @param WP_User_Query $query User query.
 */
function member_view_users_orderby( $query ) {
	if ( ! is_admin() ) {
		return;
	}
	$orderby = $query->get( 'orderby' );

	if ( 'member_view_created' === $orderby ) {
		$query->set( 'orderby', 'registered' );
		return;
	}

	if ( 'member_view_last_login' === $orderby ) {
		$query->set( 'meta_key', member_view_last_login_meta_key() );
		$query->set( 'orderby', 'meta_value_num' );
		// Users without the meta still appear, sorted as 0.
		$query->set(
			'meta_query',
			array(
				'relation' => 'OR',
				array(
					'key'     => member_view_last_login_meta_key(),
					'compare' => 'EXISTS',
				),
				array(
					'key'     => member_view_last_login_meta_key(),
					'compare' => 'NOT EXISTS',
				),
			)
		);
	}
}
add_action( 'pre_get_users', 'member_view_users_orderby' );

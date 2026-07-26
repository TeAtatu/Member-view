<?php
/**
 * Settings screen (Settings → Member View): landing page selection, uninstall
 * behavior, and the optional GitHub-update repo. Per-site by design.
 *
 * @package MemberView
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Register the options page.
 */
function member_view_add_settings_page() {
	add_options_page(
		__( 'Member View', 'member-view' ),
		__( 'Member View', 'member-view' ),
		'manage_options',
		'member-view',
		'member_view_render_settings_page'
	);
}
add_action( 'admin_menu', 'member_view_add_settings_page' );

/**
 * Register settings + fields.
 */
function member_view_register_settings() {
	register_setting(
		'member_view_settings',
		'member_view_landing_page_id',
		array(
			'type'              => 'integer',
			'sanitize_callback' => 'member_view_sanitize_landing_page_id',
			'default'           => 0,
		)
	);

	register_setting(
		'member_view_settings',
		'member_view_uninstall_visitor_action',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'member_view_sanitize_uninstall_action',
			'default'           => 'keep',
		)
	);

	register_setting(
		'member_view_settings',
		'member_view_update_repo',
		array(
			'type'              => 'string',
			'sanitize_callback' => 'member_view_sanitize_repo',
			'default'           => '',
		)
	);

	add_settings_section(
		'member_view_main',
		__( 'Access settings', 'member-view' ),
		'__return_false',
		'member-view'
	);

	add_settings_field(
		'member_view_landing_page_id',
		__( 'Visitor landing page', 'member-view' ),
		'member_view_field_landing_page',
		'member-view',
		'member_view_main'
	);

	add_settings_field(
		'member_view_uninstall_visitor_action',
		__( 'On uninstall', 'member-view' ),
		'member_view_field_uninstall_action',
		'member-view',
		'member_view_main'
	);

	add_settings_section(
		'member_view_updates',
		__( 'Updates', 'member-view' ),
		'__return_false',
		'member-view'
	);

	add_settings_field(
		'member_view_update_repo',
		__( 'GitHub repository', 'member-view' ),
		'member_view_field_update_repo',
		'member-view',
		'member_view_updates'
	);
}
add_action( 'admin_init', 'member_view_register_settings' );

/**
 * Sanitize the landing page ID: must be a published page.
 *
 * @param mixed $value Raw value.
 * @return int
 */
function member_view_sanitize_landing_page_id( $value ) {
	$id = absint( $value );
	if ( ! $id ) {
		return 0;
	}
	$post = get_post( $id );
	if ( ! $post || 'page' !== $post->post_type || 'publish' !== $post->post_status ) {
		add_settings_error( 'member_view_settings', 'landing_invalid', __( 'The selected landing page must be a published page.', 'member-view' ) );
		return 0;
	}
	return $id;
}

/**
 * Sanitize the uninstall action against a fixed whitelist.
 *
 * @param mixed $value Raw value.
 * @return string
 */
function member_view_sanitize_uninstall_action( $value ) {
	$allowed = array( 'keep', 'reassign', 'delete' );
	$value   = is_string( $value ) ? $value : 'keep';
	return in_array( $value, $allowed, true ) ? $value : 'keep';
}

/**
 * Sanitize the GitHub repo "owner/name".
 *
 * @param mixed $value Raw value.
 * @return string
 */
function member_view_sanitize_repo( $value ) {
	$value = is_string( $value ) ? trim( $value ) : '';
	if ( '' === $value ) {
		return '';
	}
	if ( ! preg_match( '#^[A-Za-z0-9_.-]+/[A-Za-z0-9_.-]+$#', $value ) ) {
		add_settings_error( 'member_view_settings', 'repo_invalid', __( 'Repository must be in the form owner/name.', 'member-view' ) );
		return '';
	}
	return $value;
}

/**
 * Field: landing page dropdown.
 */
function member_view_field_landing_page() {
	$selected = member_view_get_landing_page_id();
	wp_dropdown_pages(
		array(
			'name'              => 'member_view_landing_page_id',
			'selected'          => $selected,
			'show_option_none'  => __( '— None (gating disabled) —', 'member-view' ),
			'option_none_value' => '0',
		)
	);
	echo '<p class="description">' . esc_html__( 'Logged-out and Visitor-role users can see only this page. If none is set, gating is disabled (fail open).', 'member-view' ) . '</p>';
}

/**
 * Field: uninstall action.
 */
function member_view_field_uninstall_action() {
	$value   = get_option( 'member_view_uninstall_visitor_action', 'keep' );
	$options = array(
		'keep'     => __( 'Keep the Visitor role and accounts (non-destructive)', 'member-view' ),
		'reassign' => __( 'Reassign Visitor accounts to Subscriber, then remove the role', 'member-view' ),
		'delete'   => __( 'Delete Visitor-role accounts and remove the role', 'member-view' ),
	);
	echo '<select name="member_view_uninstall_visitor_action">';
	foreach ( $options as $key => $label ) {
		printf(
			'<option value="%s" %s>%s</option>',
			esc_attr( $key ),
			selected( $value, $key, false ),
			esc_html( $label )
		);
	}
	echo '</select>';
	echo '<p class="description">' . esc_html__( 'Chosen in advance because uninstall runs without a UI. Defaults to non-destructive.', 'member-view' ) . '</p>';
}

/**
 * Field: GitHub update repo.
 */
function member_view_field_update_repo() {
	$value = get_option( 'member_view_update_repo', '' );
	printf(
		'<input type="text" name="member_view_update_repo" value="%s" class="regular-text" placeholder="owner/name">',
		esc_attr( $value )
	);
	echo '<p class="description">' . esc_html__( 'Optional. Set to enable GitHub-release auto-updates. Leave blank to disable the updater.', 'member-view' ) . '</p>';
}

/**
 * Render the settings page.
 */
function member_view_render_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'Member View', 'member-view' ); ?></h1>
		<form action="options.php" method="post">
			<?php
			settings_fields( 'member_view_settings' );
			do_settings_sections( 'member-view' );
			submit_button();
			?>
		</form>
	</div>
	<?php
}

/**
 * Nudge admins to configure a landing page while none is set.
 */
function member_view_settings_notice() {
	if ( ! current_user_can( 'manage_options' ) ) {
		return;
	}
	if ( member_view_get_landing_page_id() ) {
		return;
	}
	// Don't nag on the settings page itself.
	$screen = get_current_screen();
	if ( $screen && 'settings_page_member-view' === $screen->id ) {
		return;
	}
	printf(
		'<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
		esc_html__( 'Member View: no visitor landing page is set, so visitor gating is currently disabled.', 'member-view' ),
		esc_url( admin_url( 'options-general.php?page=member-view' ) ),
		esc_html__( 'Set one now.', 'member-view' )
	);
}
add_action( 'admin_notices', 'member_view_settings_notice' );

<?php
/**
 * Login modal: front-end assets, footer markup, and the two AJAX handlers
 * (inline login + "Request access"). Only loaded for gated users.
 *
 * @package MemberView
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Enqueue modal assets for gated users only.
 */
function member_view_enqueue_modal() {
	if ( ! member_view_should_gate() ) {
		return;
	}

	wp_enqueue_style(
		'member-view',
		MEMBER_VIEW_URL . 'assets/css/member-view.css',
		array(),
		MEMBER_VIEW_VERSION
	);

	wp_enqueue_script(
		'member-view',
		MEMBER_VIEW_URL . 'assets/js/member-view.js',
		array(),
		MEMBER_VIEW_VERSION,
		true
	);

	// Carry a validated return-to target through to the client so a successful
	// login can send the visitor onward.
	$redirect_to = '';
	if ( isset( $_GET['redirect_to'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$redirect_to = wp_validate_redirect(
			esc_url_raw( wp_unslash( $_GET['redirect_to'] ) ), // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			''
		);
	}

	wp_localize_script(
		'member-view',
		'MemberView',
		array(
			'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
			'origin'       => home_url(),
			'siteUrl'      => site_url(),
			'loginNonce'   => wp_create_nonce( 'member_view_login' ),
			'requestNonce' => wp_create_nonce( 'member_view_request' ),
			'redirectTo'   => $redirect_to,
			'isVisitor'    => is_user_logged_in() ? 1 : 0,
			'i18n'         => array(
				'title'          => __( 'Sign in', 'member-view' ),
				'close'          => __( 'Close', 'member-view' ),
				'loginTab'       => __( 'Sign in', 'member-view' ),
				'requestTab'     => __( 'Request access', 'member-view' ),
				'genericError'   => __( 'Something went wrong. Please try again.', 'member-view' ),
				'requestSuccess' => __( 'Thanks — check your email to finish setting up access. An administrator has been notified.', 'member-view' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'member_view_enqueue_modal' );

/**
 * Render the modal markup (and the persistent trigger) in the footer, for gated
 * users only.
 */
function member_view_render_modal() {
	if ( ! member_view_should_gate() ) {
		return;
	}
	$is_visitor = is_user_logged_in();
	?>
	<button type="button" class="member-view-trigger" data-member-view-open aria-haspopup="dialog">
		<?php esc_html_e( 'Sign in', 'member-view' ); ?>
	</button>

	<div class="member-view-overlay" data-member-view-overlay hidden>
		<div class="member-view-modal" role="dialog" aria-modal="true" aria-labelledby="member-view-title" data-member-view-dialog>
			<button type="button" class="member-view-close" data-member-view-close aria-label="<?php esc_attr_e( 'Close', 'member-view' ); ?>">&times;</button>

			<h2 id="member-view-title" class="member-view-heading"><?php esc_html_e( 'Sign in', 'member-view' ); ?></h2>

			<div class="member-view-tabs" role="tablist">
				<button type="button" class="member-view-tab is-active" role="tab" aria-selected="true" data-member-view-tab="login"><?php esc_html_e( 'Sign in', 'member-view' ); ?></button>
				<button type="button" class="member-view-tab" role="tab" aria-selected="false" data-member-view-tab="request"><?php esc_html_e( 'Request access', 'member-view' ); ?></button>
			</div>

			<div class="member-view-message" data-member-view-message role="status" aria-live="polite" hidden></div>

			<?php if ( $is_visitor ) : ?>
				<p class="member-view-note" data-member-view-panel="login">
					<?php esc_html_e( 'Your account is pending approval. You can request additional access below.', 'member-view' ); ?>
				</p>
			<?php else : ?>
				<form class="member-view-form" data-member-view-panel="login" data-member-view-login novalidate>
					<label>
						<span><?php esc_html_e( 'Username or Email', 'member-view' ); ?></span>
						<input type="text" name="log" autocomplete="username" required>
					</label>
					<label>
						<span><?php esc_html_e( 'Password', 'member-view' ); ?></span>
						<input type="password" name="pwd" autocomplete="current-password" required>
					</label>
					<label class="member-view-check">
						<input type="checkbox" name="rememberme" value="1">
						<span><?php esc_html_e( 'Remember me', 'member-view' ); ?></span>
					</label>
					<button type="submit" class="member-view-submit"><?php esc_html_e( 'Sign in', 'member-view' ); ?></button>
					<p class="member-view-alt">
						<a href="<?php echo esc_url( wp_lostpassword_url() ); ?>" data-member-view-allow><?php esc_html_e( 'Lost your password?', 'member-view' ); ?></a>
					</p>
				</form>
			<?php endif; ?>

			<form class="member-view-form" data-member-view-panel="request" data-member-view-request novalidate hidden>
				<label>
					<span><?php esc_html_e( 'Your name', 'member-view' ); ?></span>
					<input type="text" name="name" autocomplete="name" required>
				</label>
				<label>
					<span><?php esc_html_e( 'Email address', 'member-view' ); ?></span>
					<input type="email" name="email" autocomplete="email" required>
				</label>
				<?php // Honeypot: real users leave this empty. Hidden from view and from assistive tech. ?>
				<div class="member-view-hp" aria-hidden="true">
					<label>
						<?php esc_html_e( 'Leave this field empty', 'member-view' ); ?>
						<input type="text" name="member_view_hp" tabindex="-1" autocomplete="off">
					</label>
				</div>
				<button type="submit" class="member-view-submit"><?php esc_html_e( 'Request access', 'member-view' ); ?></button>
			</form>
		</div>
	</div>
	<?php
}
add_action( 'wp_footer', 'member_view_render_modal' );

/**
 * AJAX: inline login via core authentication.
 */
function member_view_ajax_login() {
	check_ajax_referer( 'member_view_login', 'nonce' );

	$creds = array(
		'user_login'    => isset( $_POST['log'] ) ? sanitize_text_field( wp_unslash( $_POST['log'] ) ) : '',
		'user_password' => isset( $_POST['pwd'] ) ? (string) wp_unslash( $_POST['pwd'] ) : '',
		'remember'      => ! empty( $_POST['rememberme'] ),
	);

	$user = wp_signon( $creds, is_ssl() );

	if ( is_wp_error( $user ) ) {
		wp_send_json_error( array( 'message' => __( 'Invalid username or password.', 'member-view' ) ) );
	}

	$redirect = isset( $_POST['redirect'] ) ? esc_url_raw( wp_unslash( $_POST['redirect'] ) ) : '';
	$redirect = wp_validate_redirect( $redirect, home_url( '/' ) );

	wp_send_json_success( array( 'redirect' => $redirect ) );
}
add_action( 'wp_ajax_nopriv_member_view_login', 'member_view_ajax_login' );
add_action( 'wp_ajax_member_view_login', 'member_view_ajax_login' );

/**
 * AJAX: "Request access". Creates a gated Visitor-role account, emails the user
 * a set-password/verify link, and notifies an administrator. Always returns a
 * generic success message so it can't be used to enumerate existing accounts.
 */
function member_view_ajax_request_access() {
	check_ajax_referer( 'member_view_request', 'nonce' );

	$generic = array(
		'message' => __( 'Thanks — check your email to finish setting up access. An administrator has been notified.', 'member-view' ),
	);

	// Honeypot tripped: pretend success, do nothing.
	if ( ! empty( $_POST['member_view_hp'] ) ) {
		wp_send_json_success( $generic );
	}

	$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
	$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';

	if ( '' === $name || ! is_email( $email ) ) {
		wp_send_json_error( array( 'message' => __( 'Please enter a valid name and email address.', 'member-view' ) ) );
	}

	// Existing account: don't reveal it; return generic success.
	if ( email_exists( $email ) ) {
		wp_send_json_success( $generic );
	}

	$username = member_view_unique_username_from_email( $email );
	$user_id  = wp_insert_user(
		array(
			'user_login'   => $username,
			'user_email'   => $email,
			'user_pass'    => wp_generate_password( 24, true, true ),
			'display_name' => $name,
			'first_name'   => $name,
			'role'         => MEMBER_VIEW_ROLE,
		)
	);

	if ( is_wp_error( $user_id ) ) {
		wp_send_json_error( array( 'message' => __( 'We could not create your request. Please try again later.', 'member-view' ) ) );
	}

	if ( is_multisite() ) {
		add_user_to_blog( get_current_blog_id(), $user_id, MEMBER_VIEW_ROLE );
	}

	update_user_meta( $user_id, 'member_view_requested_at', time() );
	update_user_meta( $user_id, 'member_view_pending_verification', 1 );

	// Send the user a "set your password" email (also serves as verification —
	// the account is unusable until they follow it).
	wp_new_user_notification( $user_id, null, 'user' );

	// Notify an administrator.
	member_view_notify_admin_of_request( $name, $email );

	wp_send_json_success( $generic );
}
add_action( 'wp_ajax_nopriv_member_view_request_access', 'member_view_ajax_request_access' );
add_action( 'wp_ajax_member_view_request_access', 'member_view_ajax_request_access' );

/**
 * Build a unique username from an email local-part.
 *
 * @param string $email Email address.
 * @return string
 */
function member_view_unique_username_from_email( $email ) {
	$base = sanitize_user( current( explode( '@', $email ) ), true );
	if ( '' === $base ) {
		$base = 'member';
	}
	$username = $base;
	$i        = 1;
	while ( username_exists( $username ) ) {
		$username = $base . $i;
		$i++;
	}
	return $username;
}

/**
 * Email an administrator that someone requested access.
 *
 * @param string $name  Requester name.
 * @param string $email Requester email.
 */
function member_view_notify_admin_of_request( $name, $email ) {
	$admin_email = get_option( 'admin_email' );
	if ( ! $admin_email ) {
		return;
	}

	$site    = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
	$subject = sprintf(
		/* translators: %s: site name. */
		__( '[%s] New access request', 'member-view' ),
		$site
	);

	$body = sprintf(
		/* translators: 1: requester name, 2: requester email, 3: site name, 4: users screen URL. */
		__( "%1\$s (%2\$s) has requested access to %3\$s.\n\nA gated \"Visitor\" account has been created. Review and promote them here:\n%4\$s", 'member-view' ),
		$name,
		$email,
		$site,
		admin_url( 'users.php?role=' . MEMBER_VIEW_ROLE )
	);

	wp_mail( $admin_email, $subject, $body );
}

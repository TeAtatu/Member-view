<?php
/**
 * Access gate. The rule is the same everywhere — !member_view_should_gate()
 * grants access — but it has to be enforced at several entry points, because
 * front-end pages, REST, and wp-admin don't share a single hook.
 *
 * @package MemberView
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The URL a gated request should be sent to (the landing page), or '' if no
 * landing page is configured (in which case we fail open).
 *
 * @return string
 */
function member_view_landing_url() {
	$page_id = member_view_get_landing_page_id();
	if ( ! $page_id ) {
		return '';
	}
	$url = get_permalink( $page_id );
	return $url ? $url : '';
}

/**
 * The full URL of the current request, for use as a post-login return target.
 *
 * @return string
 */
function member_view_current_url() {
	$req = isset( $_SERVER['REQUEST_URI'] ) ? esc_url_raw( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
	return home_url( $req );
}

/**
 * Redirect a gated request to the landing page, preserving where they wanted
 * to go as a validated redirect_to parameter.
 *
 * @param string $landing_url Landing page URL.
 */
function member_view_redirect_to_landing( $landing_url ) {
	$return_to = member_view_current_url();
	$target    = add_query_arg( 'redirect_to', rawurlencode( $return_to ), $landing_url );
	wp_safe_redirect( $target );
	exit;
}

/**
 * Front-end page gate.
 */
function member_view_template_redirect_gate() {
	if ( ! member_view_should_gate() ) {
		return;
	}

	// Exempt: feeds (content is stripped to excerpts separately) and sitemaps,
	// so search engines can still discover the site.
	if ( is_feed() || is_robots() || '' !== (string) get_query_var( 'sitemap' ) || '' !== (string) get_query_var( 'sitemap-stylesheet' ) ) {
		return;
	}

	$landing_url = member_view_landing_url();
	if ( '' === $landing_url ) {
		// No landing page configured: fail open rather than loop/404.
		return;
	}

	// Already on the landing page? Never redirect (loop guard).
	$landing_id = member_view_get_landing_page_id();
	if ( $landing_id && ( is_page( $landing_id ) || get_queried_object_id() === $landing_id ) ) {
		return;
	}

	member_view_redirect_to_landing( $landing_url );
}
add_action( 'template_redirect', 'member_view_template_redirect_gate', 1 );

/**
 * wp-admin gate. Community keeps full admin; Visitor-role users (and previewing
 * admins) are bounced to the landing page so they can't reach profile.php etc.
 *
 * admin-ajax.php and admin-post.php are exempt here — their handlers are
 * individually nonce- and capability-checked (that's where the modal's AJAX and
 * the preview toggle live).
 */
function member_view_admin_gate() {
	if ( wp_doing_ajax() ) {
		return;
	}
	if ( isset( $GLOBALS['pagenow'] ) && 'admin-post.php' === $GLOBALS['pagenow'] ) {
		return;
	}
	if ( ! member_view_should_gate() ) {
		return;
	}

	$landing_url = member_view_landing_url();
	if ( '' === $landing_url ) {
		return; // Fail open.
	}

	wp_safe_redirect( $landing_url );
	exit;
}
add_action( 'admin_init', 'member_view_admin_gate' );

/**
 * REST gate. Blocks REST for gated users except this plugin's own namespace
 * (reserved for the modal / request-access endpoints if they are ever moved to
 * REST). Core cookie auth still applies for Community users.
 *
 * @param mixed           $result  Existing dispatch result.
 * @param WP_REST_Server  $server  Server instance.
 * @param WP_REST_Request $request Current request.
 * @return mixed
 */
function member_view_rest_gate( $result, $server, $request ) {
	if ( ! member_view_should_gate() ) {
		return $result;
	}

	$route = (string) $request->get_route();

	// Allow this plugin's own endpoints through.
	if ( 0 === strpos( $route, '/member-view/' ) ) {
		return $result;
	}

	return new WP_Error(
		'member_view_gated',
		__( 'You must sign in to access this resource.', 'member-view' ),
		array( 'status' => 401 )
	);
}
add_filter( 'rest_pre_dispatch', 'member_view_rest_gate', 10, 3 );

/**
 * Keep feeds crawlable without leaking gated content: emit excerpts only, never
 * the full post body. Applies regardless of who's asking, since feed consumers
 * are anonymous.
 *
 * @param string $content Feed item content.
 * @return string
 */
function member_view_feed_excerpt_only( $content ) {
	// Only strip feeds when gating is actually active (a landing page is set);
	// otherwise the site is open and feeds should behave normally.
	if ( ! member_view_get_landing_page_id() ) {
		return $content;
	}

	$excerpt = get_the_excerpt();
	if ( '' === trim( (string) $excerpt ) ) {
		$excerpt = wp_trim_words( wp_strip_all_tags( (string) $content ), 55 );
	}
	return wpautop( wp_kses_post( $excerpt ) );
}
add_filter( 'the_content_feed', 'member_view_feed_excerpt_only', 20 );

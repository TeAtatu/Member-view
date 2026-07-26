<?php
/**
 * GitHub-releases auto-updater. Uses only wp_remote_get() and core update
 * filters — no bundled library. Dormant unless a repo (owner/name) is
 * configured in Settings → Member View.
 *
 * @package MemberView
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Configured "owner/name" repo, or '' if the updater is disabled.
 *
 * @return string
 */
function member_view_update_repo() {
	return (string) get_option( 'member_view_update_repo', MEMBER_VIEW_DEFAULT_REPO );
}

/**
 * Fetch the latest GitHub release for the configured repo (cached 6h).
 *
 * @return array|null Normalized release data, or null on failure/disabled.
 */
function member_view_get_latest_release() {
	$repo = member_view_update_repo();
	if ( '' === $repo ) {
		return null;
	}

	$cache_key = 'member_view_release_' . md5( $repo );
	$cached    = get_site_transient( $cache_key );
	if ( false !== $cached ) {
		return is_array( $cached ) ? $cached : null;
	}

	$headers = array( 'Accept' => 'application/vnd.github+json' );
	$token   = get_option( 'member_view_update_token', '' );
	if ( $token ) {
		$headers['Authorization'] = 'Bearer ' . $token;
	}

	$response = wp_remote_get(
		'https://api.github.com/repos/' . $repo . '/releases/latest',
		array(
			'headers' => $headers,
			'timeout' => 15,
		)
	);

	if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
		// Cache the miss briefly so a broken repo doesn't hammer the API.
		set_site_transient( $cache_key, array(), HOUR_IN_SECONDS );
		return null;
	}

	$body = json_decode( wp_remote_retrieve_body( $response ), true );
	if ( empty( $body['tag_name'] ) ) {
		set_site_transient( $cache_key, array(), HOUR_IN_SECONDS );
		return null;
	}

	// Prefer an uploaded .zip asset; fall back to the source zipball.
	$package = isset( $body['zipball_url'] ) ? $body['zipball_url'] : '';
	if ( ! empty( $body['assets'] ) && is_array( $body['assets'] ) ) {
		foreach ( $body['assets'] as $asset ) {
			if ( isset( $asset['browser_download_url'] ) && preg_match( '/\.zip$/i', $asset['browser_download_url'] ) ) {
				$package = $asset['browser_download_url'];
				break;
			}
		}
	}

	$release = array(
		'version' => ltrim( (string) $body['tag_name'], 'vV' ),
		'package' => $package,
		'url'     => isset( $body['html_url'] ) ? $body['html_url'] : '',
		'notes'   => isset( $body['body'] ) ? (string) $body['body'] : '',
	);

	set_site_transient( $cache_key, $release, 6 * HOUR_IN_SECONDS );
	return $release;
}

/**
 * Inject an available update into the plugins update transient.
 *
 * @param object $transient Update transient.
 * @return object
 */
function member_view_check_for_update( $transient ) {
	if ( ! is_object( $transient ) ) {
		return $transient;
	}

	$release = member_view_get_latest_release();
	if ( ! $release || '' === $release['package'] ) {
		return $transient;
	}

	$item = array(
		'slug'        => dirname( MEMBER_VIEW_BASENAME ),
		'plugin'      => MEMBER_VIEW_BASENAME,
		'new_version' => $release['version'],
		'url'         => $release['url'],
		'package'     => $release['package'],
	);

	if ( version_compare( $release['version'], MEMBER_VIEW_VERSION, '>' ) ) {
		$transient->response[ MEMBER_VIEW_BASENAME ] = (object) $item;
	} else {
		// Report "no update" so WordPress's native per-plugin auto-update
		// toggle is offered on the Plugins screen.
		$item['new_version'] = MEMBER_VIEW_VERSION;
		unset( $item['package'] );
		$transient->no_update[ MEMBER_VIEW_BASENAME ] = (object) $item;
	}

	return $transient;
}
add_filter( 'pre_set_site_transient_update_plugins', 'member_view_check_for_update' );

/**
 * Provide plugin info for the "View details" modal.
 *
 * @param false|object|array $result Existing result.
 * @param string             $action API action.
 * @param object             $args   Request args.
 * @return false|object
 */
function member_view_plugins_api( $result, $action, $args ) {
	if ( 'plugin_information' !== $action ) {
		return $result;
	}
	if ( empty( $args->slug ) || dirname( MEMBER_VIEW_BASENAME ) !== $args->slug ) {
		return $result;
	}

	$release = member_view_get_latest_release();
	if ( ! $release ) {
		return $result;
	}

	return (object) array(
		'name'          => 'Member View',
		'slug'          => dirname( MEMBER_VIEW_BASENAME ),
		'version'       => $release['version'],
		'homepage'      => $release['url'],
		'download_link' => $release['package'],
		'sections'      => array(
			'changelog' => wpautop( esc_html( $release['notes'] ) ),
		),
	);
}
add_filter( 'plugins_api', 'member_view_plugins_api', 10, 3 );

/**
 * GitHub zipballs unpack to a hash-suffixed folder; rename it back to the
 * plugin slug so WordPress installs it in place.
 *
 * @param string      $source        Unpacked source path.
 * @param string      $remote_source Remote source path.
 * @param WP_Upgrader $upgrader      Upgrader instance.
 * @param array       $hook_extra    Extra args.
 * @return string|WP_Error
 */
function member_view_fix_source_dir( $source, $remote_source, $upgrader, $hook_extra = array() ) {
	global $wp_filesystem;

	if ( empty( $hook_extra['plugin'] ) || MEMBER_VIEW_BASENAME !== $hook_extra['plugin'] ) {
		return $source;
	}
	if ( ! $wp_filesystem ) {
		return $source;
	}

	$desired = trailingslashit( $remote_source ) . dirname( MEMBER_VIEW_BASENAME );
	if ( trailingslashit( $source ) === trailingslashit( $desired ) ) {
		return $source;
	}

	if ( $wp_filesystem->move( $source, $desired, true ) ) {
		return trailingslashit( $desired );
	}

	return $source;
}
add_filter( 'upgrader_source_selection', 'member_view_fix_source_dir', 10, 4 );

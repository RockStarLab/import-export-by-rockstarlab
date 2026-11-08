<?php
/**
 * Remote API helper
 *
 * Builds remote REST URLs and performs requests with backward-compatible
 * fallback to legacy route namespaces.
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

/** Requests to the explicitly configured peer, without credential redirects. */
class Remote_API {
	/**
	 * Current REST API namespace.
	 *
	 * @var string
	 */
	public const REST_NAMESPACE = 'rsl-ie/v1';

	/**
	 * Build a REST URL for a remote site.
	 *
	 * @param string $remote_url Remote site URL.
	 * @param string $route      Route path without leading slash.
	 * @return string
	 */
	/**
	 * The same REST route for sites without pretty permalinks.
	 *
	 * @param string $remote_url Site URL.
	 * @param string $route Route below the plugin namespace.
	 * @return string
	 */
	public static function build_query_url( $remote_url, $route ) {
		return add_query_arg( 'rest_route', '/' . self::REST_NAMESPACE . '/' . ltrim( $route, '/' ), trailingslashit( $remote_url ) );
	}

	/**
	 * Whether a response body is JSON (a WordPress REST answer) rather than an HTML page.
	 *
	 * @param string $body Response body.
	 * @return bool
	 */
	public static function is_json( $body ) {
		$body = ltrim( (string) $body );
		if ( '' === $body || ( '{' !== $body[0] && '[' !== $body[0] ) ) {
			return false;
		}
		json_decode( $body );
		return JSON_ERROR_NONE === json_last_error();
	}

	public static function build_url( $remote_url, $route ) {
		return trailingslashit( $remote_url ) . 'wp-json/' . self::REST_NAMESPACE . '/' . ltrim( $route, '/' );
	}

	/**
	 * Perform a POST request to a remote site.
	 *
	 * @param string $remote_url Remote site URL.
	 * @param string $route      Route path without leading slash.
	 * @param array  $args       wp_remote_post() args.
	 * @return array|\WP_Error
	 */
	public static function post( $remote_url, $route, $args ) {
		$parts = wp_parse_url( $remote_url );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! in_array( $parts['scheme'] ?? '', [ 'http', 'https' ], true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) || isset( $parts['query'] ) || isset( $parts['fragment'] ) ) {
			return new \WP_Error( 'rsl_ie_invalid_remote_url', __( 'Use an HTTP or HTTPS site URL without credentials, query or fragment.', 'import-export-by-rockstarlab' ) );
		}
		// A connected site's bearer credential must never follow a redirect.
		$args['redirection'] = 0;
		$cache_key           = 'rsl_ie_rest_style_' . md5( untrailingslashit( $remote_url ) );
		if ( 'query' === get_transient( $cache_key ) ) {
			return wp_remote_post( self::build_query_url( $remote_url, $route ), $args );
		}
		$response = wp_remote_post( self::build_url( $remote_url, $route ), $args );
		// Sites with plain permalinks have no /wp-json/ path; WordPress answers the same route at ?rest_route=.
		if ( ! is_wp_error( $response ) && in_array( (int) wp_remote_retrieve_response_code( $response ), [ 200, 404 ], true ) && ! self::is_json( wp_remote_retrieve_body( $response ) ) ) {
			$fallback = wp_remote_post( self::build_query_url( $remote_url, $route ), $args );
			if ( ! is_wp_error( $fallback ) && self::is_json( wp_remote_retrieve_body( $fallback ) ) ) {
				set_transient( $cache_key, 'query', DAY_IN_SECONDS );
				return $fallback;
			}
		}
		return $response;
	}
}

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
		return wp_remote_post( self::build_url( $remote_url, $route ), $args );
	}
}

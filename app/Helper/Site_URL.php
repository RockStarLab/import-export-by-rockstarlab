<?php
/**
 * Site URL helper.
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

class Site_URL {

	/**
	 * Return the current request origin with WordPress' site path.
	 *
	 * Local proxies may expose a pretty host while WordPress stores an internal
	 * port in siteurl. For UI/API copy-paste values, prefer the host currently
	 * used by the browser or remote request.
	 *
	 * @return string
	 */
	public static function current_request_site_url() {
		$site_url = get_site_url();
		$host     = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_HOST'] ) ) : '';

		if ( '' === $host ) {
			return $site_url;
		}

		$scheme = is_ssl() ? 'https' : 'http';
		$path   = wp_parse_url( $site_url, PHP_URL_PATH );
		$path   = is_string( $path ) ? untrailingslashit( $path ) : '';

		return untrailingslashit( esc_url_raw( $scheme . '://' . $host . $path ) );
	}

	/**
	 * Return an admin URL that follows the current browser origin.
	 *
	 * @param string $path Optional path relative to wp-admin.
	 * @return string
	 */
	public static function admin_url( $path = '' ) {
		return admin_url( $path, 'relative' );
	}
}

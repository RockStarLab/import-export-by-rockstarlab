<?php
/**
 * Keep the source site's year/month uploads folder for copied media.
 *
 * @package RockStarLab\ImportExport
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Media copied by Content Sync, import or migration used to land in the current month
 * (2008/06/photo.jpg became 2026/11/photo.jpg), so its URL differed between sites.
 * The WordPress importer keeps the original folder; so does this plugin now.
 */
class Upload_Subdir {

	/**
	 * "YYYY/MM" of an attachment of this site, or ''.
	 *
	 * @param int $attachment_id Attachment.
	 * @return string
	 */
	public static function of_attachment( $attachment_id ) {
		$file = (string) get_post_meta( (int) $attachment_id, '_wp_attached_file', true );
		return preg_match( '#^(\d{4}/\d{2})/[^/]+$#', $file, $m ) ? $m[1] : '';
	}

	/**
	 * "YYYY/MM" from an uploads URL (…/wp-content/uploads/[sites/N/]2008/06/photo.jpg), or ''.
	 *
	 * @param string $url File URL.
	 * @return string
	 */
	public static function of_url( $url ) {
		$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
		return preg_match( '#/uploads/(?:sites/\d+/)?(\d{4}/\d{2})/[^/]+$#', $path, $m ) ? $m[1] : '';
	}

	/**
	 * Accept only a plain year/month value.
	 *
	 * @param mixed $subdir Untrusted value.
	 * @return string
	 */
	public static function clean( $subdir ) {
		$subdir = is_string( $subdir ) ? trim( $subdir, '/' ) : '';
		if ( ! preg_match( '#^(\d{4})/(\d{2})$#', $subdir, $m ) || (int) $m[2] < 1 || (int) $m[2] > 12 || (int) $m[1] < 1970 ) {
			return '';
		}
		return $subdir;
	}

	/**
	 * Run $callback with uploads pointed at /YYYY/MM (only when the site files uploads by month).
	 *
	 * @param string   $subdir   "YYYY/MM" (anything else: no change).
	 * @param callable $callback Upload work.
	 * @return mixed Callback result.
	 */
	public static function with( $subdir, callable $callback ) {
		$subdir = self::clean( $subdir );
		if ( '' === $subdir || ! get_option( 'uploads_use_yearmonth_folders' ) ) {
			return $callback();
		}
		$filter = static function ( $dirs ) use ( $subdir ) {
			if ( ! is_array( $dirs ) || ! empty( $dirs['error'] ) ) {
				return $dirs;
			}
			$dirs['subdir'] = '/' . $subdir;
			$dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
			$dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
			return $dirs;
		};
		add_filter( 'upload_dir', $filter, PHP_INT_MAX );
		try {
			return $callback();
		} finally {
			remove_filter( 'upload_dir', $filter, PHP_INT_MAX );
		}
	}
}

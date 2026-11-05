<?php
/**
 * Private filesystem journal, independent of restored WordPress options.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

// Atomic rename, restrictive modes and flock require local PHP filesystem primitives.
// phpcs:disable WordPress.WP.AlternativeFunctions

/** Private filesystem journal, independent of restored WordPress options. */
class UpdraftPlus_Store {
	/**
	 * Resolve private journal storage outside WordPress and the web document root.
	 *
	 * @param bool $create Whether to create the private directory.
	 * @return string
	 * @throws \RuntimeException When the operation cannot safely proceed.
	 */
	public static function root( $create = true ) {
		global $wpdb;
		$base = defined( 'RSL_IE_UPDRAFT_STATE_DIR' ) ? RSL_IE_UPDRAFT_STATE_DIR : dirname( untrailingslashit( ABSPATH ) );
		$base = realpath( $base );
		if ( ! $base || ! is_writable( $base ) ) {
			throw new \RuntimeException( 'updraft_private_storage_unavailable' );
		}
		foreach ( [ ABSPATH, WP_CONTENT_DIR, $_SERVER['DOCUMENT_ROOT'] ?? ABSPATH ] as $public ) {
			$public = realpath( $public );
			if ( $public && ( $base === $public || strpos( $base, trailingslashit( $public ) ) === 0 ) ) {
				throw new \RuntimeException( 'updraft_private_storage_public' );
			}
		}
		$path = $base . '/rsl-ie-updraft-' . hash( 'sha256', ABSPATH . DB_NAME . $wpdb->prefix );
		if ( is_link( $path ) || ( $create && ! is_dir( $path ) && ! mkdir( $path, 0700 ) ) ) {
			throw new \RuntimeException( 'updraft_private_storage_unavailable' );
		}
		return $path;
	}
	/**
	 * Read an atomic journal record without creating storage on read-only requests.
	 *
	 * @param string $key Validated record key.
	 * @return array|null
	 * @throws \RuntimeException When the operation cannot safely proceed.
	 */
	public static function read( $key ) {
		if ( ! preg_match( '/^[a-z0-9_-]+$/D', $key ) ) {
			throw new \RuntimeException( 'updraft_invalid_record' );
		}
		$path = self::root( false ) . '/' . $key . '.json';
		if ( is_link( $path ) ) {
			throw new \RuntimeException( 'updraft_invalid_record' );
		}
		if ( ! file_exists( $path ) ) {
			return null;
		}
		$data = json_decode( file_get_contents( $path ), true );
		if ( ! is_array( $data ) ) {
			throw new \RuntimeException( 'updraft_corrupt_record' );
		}
		return $data;
	}
	/**
	 * Atomically replace a private JSON record with restrictive permissions.
	 *
	 * @param string $key Validated record key.
	 * @param array  $data JSON-serializable record.
	 * @return void
	 * @throws \RuntimeException When the operation cannot safely proceed.
	 */
	public static function write( $key, array $data ) {
		if ( ! preg_match( '/^[a-z0-9_-]+$/D', $key ) ) {
			throw new \RuntimeException( 'updraft_invalid_record' );
		}
		$path     = self::root() . '/' . $key . '.json';
		$tmp      = $path . '.' . bin2hex( random_bytes( 8 ) );
		$json     = wp_json_encode( $data );
		$old_mask = umask( 0077 );
		try {
			if ( false === $json || strlen( $json ) !== file_put_contents( $tmp, $json, LOCK_EX ) || ! chmod( $tmp, 0600 ) || ! rename( $tmp, $path ) ) {
				throw new \RuntimeException( 'updraft_journal_write_failed' );
			}
		} finally {
			umask( $old_mask );
			if ( is_file( $tmp ) ) {
				unlink( $tmp );
			}
		}
	}
	/**
	 * Acquire a nonblocking filesystem lock independent of restored database state.
	 *
	 * @param string $name Ability or lock name.
	 * @return resource
	 * @throws \RuntimeException When the operation cannot safely proceed.
	 */
	public static function lock( $name = 'site' ) {
		if ( ! preg_match( '/^[a-z0-9_-]+$/D', $name ) ) {
			throw new \RuntimeException( 'updraft_invalid_record' );
		}
		$path = self::root() . '/' . $name . '.lock';
		if ( is_link( $path ) ) {
			throw new \RuntimeException( 'updraft_invalid_record' );
		}
		$handle = fopen( $path, 'c' );
		if ( ! $handle || ! flock( $handle, LOCK_EX | LOCK_NB ) ) {
			if ( $handle ) {
				fclose( $handle );
			}
			throw new \RuntimeException( 'updraft_operation_busy' );
		}
		chmod( $path, 0600 );
		return $handle;
	}
	/**
	 * Release a previously acquired lock.
	 *
	 * @param resource $handle Held lock handle.
	 * @return void
	 */
	public static function unlock( $handle ) {
		flock( $handle, LOCK_UN );
		fclose( $handle );
	}
}

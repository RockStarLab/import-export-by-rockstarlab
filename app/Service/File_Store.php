<?php
/**
 * Site-local file records and private storage.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Non-autoloaded records survive requests; binary data never needs a public URL.
 */
class File_Store {

	/**
	 * Validate opaque identifiers before using them in paths or option keys.
	 *
	 * @param mixed $id File ID.
	 * @return bool
	 */
	public static function valid_id( $id ) {
		return is_string( $id ) && 1 === preg_match( '/\Afile_[a-f0-9]{32}\z/', $id );
	}

	/**
	 * Resolve private storage, rejecting directories inside known document roots.
	 *
	 * Configure RSL_IE_PRIVATE_DIR for durable/shared storage on managed hosts.
	 *
	 * @return string|\WP_Error
	 */
	public static function root() {
		global $wpdb;
		// CLI and PHP-FPM can have different temporary directories. Persist the
		// first server-selected base so both transports resolve the same files.
		if ( ! defined( 'RSL_IE_PRIVATE_DIR' ) && ! get_option( 'rsl_ie_private_storage_base' ) ) {
			add_option( 'rsl_ie_private_storage_base', sys_get_temp_dir(), '', false );
		}
		$base = defined( 'RSL_IE_PRIVATE_DIR' ) ? RSL_IE_PRIVATE_DIR : get_option( 'rsl_ie_private_storage_base' );
		$base = is_string( $base ) ? realpath( $base ) : false;
		if ( ! $base || ! is_dir( $base ) ) {
			return self::error( 'private_storage', 503 );
		}
		$roots = [ ABSPATH, WP_CONTENT_DIR ];
		if ( ! empty( $_SERVER['DOCUMENT_ROOT'] ) ) {
			$roots[] = $_SERVER['DOCUMENT_ROOT']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Server configuration, canonicalized below; never a request path.
		}
		foreach ( $roots as $root ) {
			$root = realpath( $root );
			if ( $root && ( $base === $root || 0 === strpos( $base, trailingslashit( $root ) ) ) ) {
				return self::error( 'private_storage', 503 );
			}
		}
		$path = trailingslashit( $base ) . 'rsl-ie-' . hash( 'sha256', ABSPATH . DB_NAME . $wpdb->prefix );
		if ( is_link( $path ) || ( ! is_dir( $path ) && ! mkdir( $path, 0700 ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions -- Explicit private permissions rather than public upload permissions.
			return self::error( 'private_storage', 503 );
		}
		return $path;
	}

	/**
	 * Resolve an isolated file directory.
	 *
	 * @param string $id File ID.
	 * @return string|\WP_Error
	 */
	public static function directory( $id ) {
		if ( ! self::valid_id( $id ) ) {
			return self::error( 'file_not_found', 404 );
		}
		$root = self::root();
		if ( is_wp_error( $root ) ) {
			return $root;
		}
		$dir = $root . '/' . $id;
		if ( is_link( $dir ) || ( ! is_dir( $dir ) && ! mkdir( $dir, 0700 ) ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions -- Private per-resource directory.
			return self::error( 'private_storage', 503 );
		}
		return $dir;
	}

	/**
	 * Read a server-owned record; callers must authorize it before use.
	 *
	 * @param string $id File ID.
	 * @return array|\WP_Error
	 */
	public static function get( $id ) {
		if ( self::valid_id( $id ) ) {
			// Uploads are often written by separate requests (chunk REST calls) while a long-running
			// process such as the WP-CLI MCP server reads the record, so never trust the in-memory copy.
			wp_cache_delete( 'rsl_ie_file_' . $id, 'options' );
			$notoptions = wp_cache_get( 'notoptions', 'options' );
			if ( is_array( $notoptions ) && isset( $notoptions[ 'rsl_ie_file_' . $id ] ) ) {
				unset( $notoptions[ 'rsl_ie_file_' . $id ] );
				wp_cache_set( 'notoptions', $notoptions, 'options' );
			}
		}
		$record = self::valid_id( $id ) ? get_option( 'rsl_ie_file_' . $id ) : false;
		return is_array( $record ) && ( $record['file_id'] ?? '' ) === $id ? $record : self::error( 'file_not_found', 404 );
	}

	/**
	 * Save under the resource lock. Existing values may legitimately be equal.
	 *
	 * @param array $record Trusted metadata.
	 * @return true|\WP_Error
	 */
	public static function save( array $record ) {
		$key = 'rsl_ie_file_' . $record['file_id'];
		if ( ! update_option( $key, $record, false ) && get_option( $key ) !== $record ) {
			return self::error( 'file_write', 500 );
		}
		return true;
	}

	/**
	 * Remove only regular files directly inside this resource's private directory.
	 *
	 * @param string $id File ID.
	 * @param int    $limit Maximum attempted unlinks; zero removes all.
	 * @return true|\WP_Error
	 */
	public static function remove( $id, $limit = 0 ) {
		$dir = self::directory( $id );
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}
		foreach ( new \FilesystemIterator( $dir, \FilesystemIterator::SKIP_DOTS ) as $entry ) {
			if ( ! $entry->isLink() && $entry->isDir() && preg_match( '/\A(?:validate-file_[a-f0-9]{32}-[0-9]+|source)\.(json|xlsx|ods)\.rows-[0-9]+\.json\.work\z/', $entry->getFilename() ) ) {
				if ( ! Spreadsheet_Spool::discard( 0, substr( $entry->getPathname(), 0, -5 ) ) ) {
					return self::error( 'file_write', 500 ); }
			}
		}
		$attempted = 0;
		foreach ( self::regular_files( $dir ) as $path ) {
			if ( '.archive-owner' === basename( $path ) ) {
				continue; }
			if ( $limit > 0 && $attempted >= $limit ) {
				return self::error( 'cleanup_pending', 409 );
			}
			++$attempted;
			if ( is_file( $path ) && ! is_link( $path ) ) {
				wp_delete_file( $path );
			}
		}
		$marker = $dir . '/.archive-owner';
		if ( is_file( $marker ) && ! is_link( $marker ) && 1 === iterator_count( new \FilesystemIterator( $dir, \FilesystemIterator::SKIP_DOTS ) ) ) {
			wp_delete_file( $marker ); }
		if ( ! rmdir( $dir ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions -- Delete only an empty private resource directory.
			return self::error( 'file_write', 500 );
		}
		$record = self::get( $id );
		if ( ! is_wp_error( $record ) && 'table' === ( $record['chunk_storage'] ?? '' ) ) {
			global $wpdb;
			$table   = $wpdb->prefix . 'rsl_ie_file_chunks';
			$deleted = $limit > 0
				? $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE file_id=%s ORDER BY chunk_index LIMIT %d', $table, $id, $limit ) )
				: $wpdb->delete( $table, [ 'file_id' => $id ], [ '%s' ] );
			if ( false === $deleted ) {
				return self::error( 'file_write', 500 );
			}
			if ( $limit > 0 ) {
				$remaining = $wpdb->get_var( $wpdb->prepare( 'SELECT chunk_index FROM %i WHERE file_id=%s LIMIT 1', $table, $id ) );
				if ( $wpdb->last_error ) {
					return self::error( 'file_write', 500 );
				}
				if ( null !== $remaining ) {
					return self::error( 'cleanup_pending', 409 );
				}
			}
		}
		$key = 'rsl_ie_file_' . $id;
		if ( ! delete_option( $key ) ) {
			global $wpdb;
			// WordPress marks the option missing in cache even when DELETE fails.
			wp_cache_delete( 'notoptions', 'options' );
			wp_cache_delete( $key, 'options' );
			$remaining = $wpdb->get_var( $wpdb->prepare( 'SELECT option_id FROM %i WHERE option_name=%s', $wpdb->options, $key ) );
			if ( $wpdb->last_error || null !== $remaining ) {
				return self::error( 'file_write', 500 );
			}
		}
		return true;
	}

	/**
	 * Walk direct regular files without materializing the directory listing.
	 *
	 * @param string $directory Private resource directory.
	 * @param string $prefix Optional basename prefix.
	 * @return \Generator
	 */
	public static function regular_files( $directory, $prefix = '' ) {
		foreach ( new \FilesystemIterator( $directory, \FilesystemIterator::SKIP_DOTS ) as $entry ) {
			if ( ! $entry->isLink() && $entry->isFile() && ( '' === $prefix || 0 === strpos( $entry->getFilename(), $prefix ) ) ) {
				yield $entry->getPathname();
			}
		}
	}

	/**
	 * Enumerate resource keys in bounded pages, stable across deletions.
	 *
	 * @param int $after Last visited option ID.
	 * @return \Generator
	 */
	public static function record_keys( $after = 0 ) {
		global $wpdb;
		$cursor = max( 0, (int) $after );
		do {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Keyset pagination over private resource records, without cached full listings.
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT option_id,option_name FROM %i WHERE option_name LIKE %s AND option_id > %d ORDER BY option_id LIMIT 100', $wpdb->options, $wpdb->esc_like( 'rsl_ie_file_file_' ) . '%', $cursor ) );
			if ( ! is_array( $rows ) ) {
				return;
			}
			foreach ( $rows as $row ) {
				$cursor = (int) $row->option_id;
				yield $cursor => $row->option_name;
			}
		} while ( count( $rows ) === 100 );
	}

	/**
	 * Consistent transport-independent failures.
	 *
	 * @param string $reason Machine-readable reason.
	 * @param int    $status HTTP status.
	 * @return \WP_Error
	 */
	public static function error( $reason, $status = 400, $message = '' ) {
		// Problems a person can fix themselves get a sentence instead of a code.
		$readable = [
			'spreadsheet_invalid' => __( 'The spreadsheet could not be read. Open it in Excel, Google Sheets or LibreOffice and save it again as .xlsx, .ods or .csv.', 'import-export-by-rockstarlab' ),
			// A broken or wrong package said "File operation failed: zip_invalid.".
			'zip_invalid'         => __( 'This file is not a valid ZIP archive. Download the content migration package again from the source site and upload the .zip file without changing it.', 'import-export-by-rockstarlab' ),
			'zip_read'            => __( 'The ZIP archive could not be read. Download the package again and upload it without changing it.', 'import-export-by-rockstarlab' ),
			'zip_integrity'       => __( 'The ZIP archive is damaged or incomplete. Download the package again and upload it without changing it.', 'import-export-by-rockstarlab' ),
			'zip_unsafe_entry'    => __( 'The ZIP archive contains files with unsafe names and was refused.', 'import-export-by-rockstarlab' ),
			'zip_content_limit'   => __( 'The ZIP archive is too large or contains too many files to be unpacked on this site.', 'import-export-by-rockstarlab' ),
			'zip_unavailable'     => __( 'ZIP archives cannot be opened on this site: the PHP Zip extension is missing. Ask your host to enable it.', 'import-export-by-rockstarlab' ),
			'upload_incomplete'   => __( 'The upload did not finish. Upload the file again.', 'import-export-by-rockstarlab' ),
			'file_not_found'      => __( 'The file is no longer on the server (old uploads are cleaned up). Upload it again.', 'import-export-by-rockstarlab' ),
			'busy'                => __( 'This file is being processed by another job. Try again in a moment.', 'import-export-by-rockstarlab' ),
		];
		if ( '' === $message ) {
			$message = $readable[ $reason ] ?? sprintf( /* translators: %s: File operation failure code. */ __( 'File operation failed: %s.', 'import-export-by-rockstarlab' ), $reason );
		}
		return new \WP_Error( 'rsl_ie_' . $reason, $message, [ 'status' => $status ] );
	}
}

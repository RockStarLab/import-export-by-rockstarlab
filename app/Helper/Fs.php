<?php
/**
 * File System Helper
 *
 * Provides file system operations for import/export files.
 * Handles uploads, validation, cleanup, and file path management.
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

class FS {

	/**
	 * Load a whitelisted WordPress admin include file.
	 *
	 * @param string $file File name from wp-admin/includes.
	 * @return void
	 */
	public static function require_admin_include( $file ) {
		$file    = wp_basename( $file );
		$allowed = array(
			'file.php',
			'image.php',
			'media.php',
			'upgrade.php',
		);

		if ( ! in_array( $file, $allowed, true ) ) {
			return;
		}

		require_once ABSPATH . 'wp-admin/includes/' . $file;
	}

	/**
	 * Load WordPress media helper functions when running outside wp-admin.
	 *
	 * @return void
	 */
	public static function load_media_core() {
		if ( ! function_exists( 'download_url' ) ) {
			self::require_admin_include( 'file.php' );
		}

		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			self::require_admin_include( 'image.php' );
		}

		if ( ! function_exists( 'media_handle_sideload' ) ) {
			self::require_admin_include( 'media.php' );
		}
	}

	/**
	 * Load WordPress image helper functions when running outside wp-admin.
	 *
	 * @return void
	 */
	public static function load_image_core() {
		if ( ! function_exists( 'wp_generate_attachment_metadata' ) ) {
			self::require_admin_include( 'image.php' );
		}
	}

	/**
	 * Load dbDelta() when running outside wp-admin.
	 *
	 * @return void
	 */
	public static function load_db_delta_core() {
		if ( ! function_exists( 'dbDelta' ) ) {
			self::require_admin_include( 'upgrade.php' );
		}
	}

	/**
	 * Get plugin's upload directory
	 * Creates directory if it doesn't exist
	 *
	 * @return array {
	 *     Upload directory information.
	 *
	 *     @type string $path Absolute file system path
	 *     @type string $url  URL to the directory
	 * }
	 */
	public static function get_upload_dir() {
		$upload     = wp_upload_dir();
		$rsl_ie_dir = $upload['basedir'] . '/rsl-ie-uploads';
		$rsl_ie_url = $upload['baseurl'] . '/rsl-ie-uploads';

		if ( ! file_exists( $rsl_ie_dir ) ) {
			wp_mkdir_p( $rsl_ie_dir );
		}

		return [
			'path' => $rsl_ie_dir,
			'url'  => $rsl_ie_url,
		];
	}

	/**
	 * Handle file upload from $_FILES
	 * Moves uploaded file to plugin's upload directory with unique filename
	 *
	 * @param array $file File array from $_FILES
	 * @return array|WP_Error {
	 *     Upload result on success, WP_Error on failure.
	 *
	 *     @type string $file Filename
	 *     @type string $path Absolute file path
	 * }
	 */
	public static function handle_upload( $file ) {
		if ( ! isset( $file['error'] ) || is_array( $file['error'] ) ) {
			return new \WP_Error( 'invalid_upload', 'Invalid file upload.' );
		}

		// Use WordPress upload handling to keep uploads within WP's checks and filters.
		$upload        = wp_upload_dir();
		$upload_subdir = '/rsl-ie-uploads';

		$upload_dir_filter   = static function ( $dirs ) use ( $upload, $upload_subdir ) {
			$dirs['subdir'] = $upload_subdir;
			$dirs['path']   = $upload['basedir'] . $upload_subdir;
			$dirs['url']    = $upload['baseurl'] . $upload_subdir;
			return $dirs;
		};
		$upload_mimes_filter = static function ( $mimes ) {
			$mimes['csv']  = 'text/csv';
			$mimes['xlsx'] = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
			$mimes['ods']  = 'application/vnd.oasis.opendocument.spreadsheet';
			$mimes['zip']  = 'application/zip';
			return $mimes;
		};

		add_filter( 'upload_dir', $upload_dir_filter );
		add_filter( 'upload_mimes', $upload_mimes_filter );
		$result = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
			)
		);
		remove_filter( 'upload_mimes', $upload_mimes_filter );
		remove_filter( 'upload_dir', $upload_dir_filter );

		if ( isset( $result['error'] ) ) {
			return new \WP_Error( 'upload_failed', $result['error'] );
		}

		$filename  = isset( $result['file'] ) ? wp_basename( $result['file'] ) : '';
		$file_path = $result['file'] ?? '';

		if ( empty( $filename ) || empty( $file_path ) ) {
			return new \WP_Error( 'upload_failed', 'Failed to process uploaded file.' );
		}

		return [
			'file' => $filename,
			'path' => $file_path,
		];
	}

	/**
	 * Back-compat wrapper for older call sites.
	 *
	 * @param array $file File array from $_FILES (sanitized).
	 * @return array|WP_Error
	 */
	public static function upload_file( $file ) {
		$result = self::handle_upload( $file );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return [
			'id'   => wp_generate_uuid4(),
			'name' => $result['file'],
			'path' => $result['path'],
		];
	}

	/**
	 * Extract a single supported import file from a ZIP archive.
	 *
	 * @param string $zip_path        ZIP archive path.
	 * @param string $destination_dir Destination directory.
	 * @return array|\WP_Error {
	 *     Extracted file data on success.
	 *
	 *     @type string $file   Extracted filename.
	 *     @type string $path   Extracted absolute file path.
	 *     @type string $format Extracted file format.
	 * }
	 */
	public static function extract_import_file_from_zip( $zip_path, $destination_dir ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return new \WP_Error( 'rsl_ie_zip_unavailable', __( 'ZIP imports are not available because the ZipArchive PHP extension is not enabled on this server.', 'import-export-by-rockstarlab' ) );
		}

		$real_zip_path = realpath( $zip_path );
		if ( false === $real_zip_path || ! is_file( $real_zip_path ) || ! is_readable( $real_zip_path ) ) {
			return new \WP_Error( 'rsl_ie_zip_unreadable', __( 'ZIP file cannot be read.', 'import-export-by-rockstarlab' ) );
		}

		if ( ! file_exists( $destination_dir ) ) {
			wp_mkdir_p( $destination_dir );
		}

		$real_destination = realpath( $destination_dir );
		if ( false === $real_destination || ! is_dir( $real_destination ) ) {
			return new \WP_Error( 'rsl_ie_zip_destination_invalid', __( 'Import upload directory is not available.', 'import-export-by-rockstarlab' ) );
		}

		$zip = new \ZipArchive();
		if ( true !== $zip->open( $real_zip_path ) ) {
			return new \WP_Error( 'rsl_ie_zip_open_failed', __( 'Could not open ZIP archive.', 'import-export-by-rockstarlab' ) );
		}

		$allowed_extensions = array( 'csv', 'xml', 'xlsx', 'ods' );
		$candidates         = array();

		for ( $i = 0; $i < $zip->numFiles; $i++ ) {
			$name = (string) $zip->getNameIndex( $i );
			$name = str_replace( '\\', '/', $name );

			if ( '' === $name || '/' === substr( $name, -1 ) || false !== strpos( $name, '__MACOSX/' ) ) {
				continue;
			}

			$basename = basename( $name );
			if ( '' === $basename || '.' === $basename[0] ) {
				continue;
			}

			$extension = strtolower( pathinfo( $basename, PATHINFO_EXTENSION ) );
			if ( in_array( $extension, $allowed_extensions, true ) ) {
				$candidates[] = array(
					'index'     => $i,
					'name'      => $name,
					'basename'  => $basename,
					'extension' => $extension,
				);
			}
		}

		if ( 0 === count( $candidates ) ) {
			$zip->close();
			return new \WP_Error( 'rsl_ie_zip_no_supported_file', __( 'The ZIP archive does not contain a supported import file. Please include one CSV, XML, XLSX, or ODS file.', 'import-export-by-rockstarlab' ) );
		}

		if ( count( $candidates ) > 1 ) {
			$zip->close();
			return new \WP_Error( 'rsl_ie_zip_multiple_supported_files', __( 'The ZIP archive contains more than one supported import file. Please upload a ZIP with exactly one CSV, XML, XLSX, or ODS file.', 'import-export-by-rockstarlab' ) );
		}

		$candidate = $candidates[0];
		$filename  = sanitize_file_name( $candidate['basename'] );
		if ( '' === $filename ) {
			$zip->close();
			return new \WP_Error( 'rsl_ie_zip_invalid_filename', __( 'The import file inside the ZIP archive has an invalid filename.', 'import-export-by-rockstarlab' ) );
		}

		$target_path = trailingslashit( $real_destination ) . $filename;
		if ( file_exists( $target_path ) ) {
			$file_info   = pathinfo( $filename );
			$filename    = sanitize_file_name( $file_info['filename'] . '_' . time() . '.' . $file_info['extension'] );
			$target_path = trailingslashit( $real_destination ) . $filename;
		}

		$source = $zip->getStream( $candidate['name'] );
		if ( false === $source ) {
			$zip->close();
			return new \WP_Error( 'rsl_ie_zip_read_failed', __( 'Could not read the import file inside the ZIP archive.', 'import-export-by-rockstarlab' ) );
		}

		$target = fopen( $target_path, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Stream copy from ZipArchive is required here.
		if ( false === $target ) {
			fclose( $source ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing stream opened above.
			$zip->close();
			return new \WP_Error( 'rsl_ie_zip_write_failed', __( 'Could not write the extracted import file.', 'import-export-by-rockstarlab' ) );
		}

		while ( ! feof( $source ) ) {
			$chunk = fread( $source, 1048576 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Stream copy from ZipArchive is required here.
			if ( false === $chunk ) {
				fclose( $source ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing stream opened above.
				fclose( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing stream opened above.
				$zip->close();
				wp_delete_file( $target_path );
				return new \WP_Error( 'rsl_ie_zip_copy_failed', __( 'Could not extract the import file from the ZIP archive.', 'import-export-by-rockstarlab' ) );
			}

			fwrite( $target, $chunk ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Stream copy from ZipArchive is required here.
		}

		fclose( $source ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing stream opened above.
		fclose( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing stream opened above.
		$zip->close();

		return array(
			'file'   => $filename,
			'path'   => $target_path,
			'format' => $candidate['extension'],
		);
	}

	/**
	 * Get export file path
	 * Prepares path for export file in a secure subdirectory
	 * Path: WordPress uploads directory/import-export-by-rockstarlab-files/{secure_hash}/
	 *
	 * @param string $filename Desired filename
	 * @return array|WP_Error {
	 *     File path information on success, WP_Error on failure.
	 *
	 *     @type string $file Filename
	 *     @type string $path Absolute file path
	 *     @type string $dir  Directory path
	 *     @type string $hash Secure directory hash
	 * }
	 */
	public static function get_export_file_path( $filename ) {
		$upload = wp_upload_dir();

		// Create base directory for exports
		$base_dir = $upload['basedir'] . '/import-export-by-rockstarlab-files';
		if ( ! file_exists( $base_dir ) ) {
			wp_mkdir_p( $base_dir );

			// Add .htaccess to prevent directory listing
			$htaccess_content = "Options -Indexes\n<FilesMatch \"\\.(csv|json|xml|xlsx|ods)$\">\n  Order Deny,Allow\n  Deny from all\n</FilesMatch>";
			file_put_contents( $base_dir . '/.htaccess', $htaccess_content );
		}

		// Generate secure hash for this export session
		// Use time + user ID + salt for uniqueness and security
		$secure_hash = md5( current_time( 'timestamp' ) . get_current_user_id() . wp_salt( 'nonce' ) . uniqid( '', true ) );

		// Create subdirectory with secure hash
		$export_dir = $base_dir . '/' . $secure_hash;
		if ( ! file_exists( $export_dir ) ) {
			wp_mkdir_p( $export_dir );
		}

		$file_path = $export_dir . '/' . $filename;

		return [
			'file' => $filename,
			'path' => $file_path,
			'dir'  => $export_dir,
			'hash' => $secure_hash,
		];
	}
}

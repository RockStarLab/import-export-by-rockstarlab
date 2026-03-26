<?php
/**
 * File System Helper
 *
 * Provides file system operations for import/export files.
 * Handles uploads, validation, cleanup, and file path management.
 *
 * @package WP_AIE\Helper
 */

namespace WP_AIE\Helper;

defined( 'ABSPATH' ) || exit;

class FS {

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
		$upload  = wp_upload_dir();
		$aie_dir = $upload['basedir'] . '/aie-uploads';
		$aie_url = $upload['baseurl'] . '/aie-uploads';

		if ( ! file_exists( $aie_dir ) ) {
			wp_mkdir_p( $aie_dir );
		}

		return [
			'path' => $aie_dir,
			'url'  => $aie_url,
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

		$upload_dir = self::get_upload_dir();
		$filename   = wp_unique_filename( $upload_dir['path'], $file['name'] );
		$file_path  = $upload_dir['path'] . '/' . $filename;

		// phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- move_uploaded_file is the correct function for securely handling uploaded files.
		if ( ! move_uploaded_file( $file['tmp_name'], $file_path ) ) { // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found
			return new \WP_Error( 'upload_failed', 'Failed to move uploaded file.' );
		}

		return [
			'file' => $filename,
			'path' => $file_path,
		];
	}

	/**
	 * Get export file path
	 * Prepares path for export file in a secure subdirectory
	 * Path: wp-content/uploads/wp-advanced-import-export-files/{secure_hash}/
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
		$base_dir = $upload['basedir'] . '/wp-advanced-import-export-files';
		if ( ! file_exists( $base_dir ) ) {
			wp_mkdir_p( $base_dir );

			// Add .htaccess to prevent directory listing
			$htaccess_content = "Options -Indexes\n<FilesMatch \"\\.(csv|json|xml)$\">\n  Order Deny,Allow\n  Deny from all\n</FilesMatch>";
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

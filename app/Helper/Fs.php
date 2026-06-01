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

		$upload_dir_filter = static function ( $dirs ) use ( $upload, $upload_subdir ) {
			$dirs['subdir'] = $upload_subdir;
			$dirs['path']   = $upload['basedir'] . $upload_subdir;
			$dirs['url']    = $upload['baseurl'] . $upload_subdir;
			return $dirs;
		};

		add_filter( 'upload_dir', $upload_dir_filter );
		$result = wp_handle_upload(
			$file,
			array(
				'test_form' => false,
			)
		);
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

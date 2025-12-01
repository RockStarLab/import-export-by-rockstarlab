<?php
/**
 * Media Sync Helper
 *
 * Provides folder scanning and file importing for Media Folder Sync feature.
 *
 * @package WP_AIE\Helper
 */

namespace WP_AIE\Helper;

/**
 * Media Sync Helper Class
 *
 * Handles folder scanning, duplicate detection, and file importing
 * for the Media Folder Sync feature.
 *
 * @package WP_AIE\Helper
 */
class Media_Sync {

	/**
	 * Scan folder and return list of files matching options
	 *
	 * @param string $folder_path Absolute path to folder.
	 * @param array  $options Options (recursive, file_types).
	 * @return array|\WP_Error Array of files or WP_Error on failure.
	 */
	public static function scan_folder( $folder_path, $options = [] ) {
		$recursive = ! empty( $options['recursive'] );
		$allowed   = $options['file_types'] ?? [];

		if ( ! is_dir( $folder_path ) ) {
			return new \WP_Error(
				'folder_not_found',
				sprintf(
					/* translators: %s: folder path */
					__( 'Folder %s not found', 'wp-advanced-import-export' ),
					$folder_path
				)
			);
		}

		$files = [];
		$it    = new \RecursiveDirectoryIterator( $folder_path, \FilesystemIterator::SKIP_DOTS );
		$ri    = $recursive ? new \RecursiveIteratorIterator( $it ) : $it;

		foreach ( $ri as $file ) {
			if ( $file->isFile() ) {
				$ext = strtolower( pathinfo( $file->getFilename(), PATHINFO_EXTENSION ) );
				if ( empty( $allowed ) || in_array( $ext, $allowed, true ) ) {
					$files[] = [
						'path' => $file->getPathname(),
						'name' => $file->getFilename(),
						'size' => $file->getSize(),
					];
				}
			}
		}

		return $files;
	}

	/**
	 * Check if file is duplicate in media library
	 *
	 * @param string $file_path Absolute path to file.
	 * @param string $method Detection method: hash|filename|filesize.
	 * @return int|false Attachment ID if duplicate found, false otherwise.
	 */
	public static function check_duplicate( $file_path, $method = 'hash' ) {
		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		if ( 'filename' === $method ) {
			return self::check_duplicate_by_filename( $file_path );
		}

		if ( 'filesize' === $method ) {
			return self::check_duplicate_by_filesize( $file_path );
		}

		return self::check_duplicate_by_hash( $file_path );
	}

	/**
	 * Check duplicate by filename only
	 *
	 * @param string $file_path Absolute path to file.
	 * @return int|false Attachment ID or false.
	 */
	protected static function check_duplicate_by_filename( $file_path ) {
		$filename = basename( $file_path );
		$args     = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'fields'         => 'ids',
			'posts_per_page' => 1,
			's'              => $filename,
		];

		$query = new \WP_Query( $args );
		return $query->have_posts() ? (int) $query->posts[0] : false;
	}

	/**
	 * Check duplicate by filesize + filename
	 *
	 * @param string $file_path Absolute path to file.
	 * @return int|false Attachment ID or false.
	 */
	protected static function check_duplicate_by_filesize( $file_path ) {
		$filename = basename( $file_path );
		$size     = filesize( $file_path );

		$args = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => [
				[
					'key'   => 'aie_file_size',
					'value' => $size,
				],
			],
			's'              => $filename,
			'fields'         => 'ids',
			'posts_per_page' => 1,
		];

		$query = new \WP_Query( $args );
		return $query->have_posts() ? (int) $query->posts[0] : false;
	}

	/**
	 * Check duplicate by MD5 hash (most accurate)
	 *
	 * @param string $file_path Absolute path to file.
	 * @return int|false Attachment ID or false.
	 */
	protected static function check_duplicate_by_hash( $file_path ) {
		$hash = md5_file( $file_path );

		$args = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => [
				[
					'key'   => 'aie_file_hash',
					'value' => $hash,
				],
			],
			'fields'         => 'ids',
			'posts_per_page' => 1,
		];

		$query = new \WP_Query( $args );
		return $query->have_posts() ? (int) $query->posts[0] : false;
	}

	/**
	 * Import a single file into WordPress media library
	 *
	 * @param string $file_path Absolute path to file.
	 * @param array  $options Import options.
	 * @return int|\WP_Error Attachment ID on success, WP_Error on failure.
	 */
	public static function import_file( $file_path, $options = [] ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'File not found', 'wp-advanced-import-export' ) );
		}

		$filetype = wp_check_filetype( $file_path );
		if ( ! $filetype['type'] ) {
			return new \WP_Error( 'invalid_file_type', __( 'Unsupported file type', 'wp-advanced-import-export' ) );
		}

		// Check for duplicates if requested.
		if ( ! empty( $options['skip_duplicates'] ) ) {
			$method    = $options['duplicate_method'] ?? 'hash';
			$duplicate = self::check_duplicate( $file_path, $method );
			if ( $duplicate ) {
				return new \WP_Error( 'duplicate_file', __( 'File already exists in media library', 'wp-advanced-import-export' ), [ 'attachment_id' => $duplicate ] );
			}
		}

		// Copy file to uploads directory.
		$uploads  = wp_upload_dir();
		$dest_dir = $uploads['basedir'] . '/aie-media-sync';
		if ( ! file_exists( $dest_dir ) ) {
			wp_mkdir_p( $dest_dir );
		}

		$dest_path = $dest_dir . '/' . wp_unique_filename( $dest_dir, basename( $file_path ) );
		if ( ! copy( $file_path, $dest_path ) ) {
			return new \WP_Error( 'copy_failed', __( 'Failed to copy file to uploads directory', 'wp-advanced-import-export' ) );
		}

		// Create attachment.
		$attachment = [
			'post_mime_type' => $filetype['type'],
			'post_title'     => sanitize_file_name( basename( $dest_path ) ),
			'post_status'    => 'inherit',
		];

		$attach_id = wp_insert_attachment( $attachment, $dest_path );
		if ( is_wp_error( $attach_id ) ) {
			return $attach_id;
		}

		// Generate metadata.
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$meta = wp_generate_attachment_metadata( $attach_id, $dest_path );
		wp_update_attachment_metadata( $attach_id, $meta );

		// Set alt text if requested.
		if ( ! empty( $options['set_alt_text'] ) ) {
			$alt_text = ! empty( $options['alt_text'] )
				? sanitize_text_field( $options['alt_text'] )
				: sanitize_text_field( pathinfo( $file_path, PATHINFO_FILENAME ) );
			update_post_meta( $attach_id, '_wp_attachment_image_alt', $alt_text );
		}

		// Store metadata for duplicate detection.
		$hash = md5_file( $dest_path );
		update_post_meta( $attach_id, 'aie_file_hash', $hash );
		update_post_meta( $attach_id, 'aie_file_size', filesize( $dest_path ) );
		update_post_meta( $attach_id, 'aie_original_path', $file_path );

		return $attach_id;
	}

	/**
	 * Get allowed file types based on option
	 *
	 * @param string $type Type: all|images|custom.
	 * @param array  $custom_types Custom file extensions.
	 * @return array Array of allowed extensions.
	 */
	public static function get_allowed_file_types( $type = 'all', $custom_types = [] ) {
		if ( 'custom' === $type && ! empty( $custom_types ) ) {
			return $custom_types;
		}

		if ( 'images' === $type ) {
			return [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg' ];
		}

		// All allowed WordPress mime types.
		$mime_types = get_allowed_mime_types();
		$extensions = [];

		foreach ( $mime_types as $ext => $mime ) {
			$ext_array  = explode( '|', $ext );
			$extensions = array_merge( $extensions, $ext_array );
		}

		return array_unique( $extensions );
	}
}

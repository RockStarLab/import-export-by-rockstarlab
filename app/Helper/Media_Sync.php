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

		// Get file type filter
		$file_type    = $options['file_types'] ?? 'all';
		$custom_types = $options['custom_types'] ?? [];

		// Get allowed extensions array
		$allowed = self::get_allowed_file_types( $file_type, $custom_types );

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

		// First check our custom meta
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
		if ( $query->have_posts() ) {
			return (int) $query->posts[0];
		}

		// Fallback: check all attachments with same filename and compare sizes
		$args = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'fields'         => 'ids',
			'posts_per_page' => 50,
			's'              => pathinfo( $filename, PATHINFO_FILENAME ),
		];

		$query = new \WP_Query( $args );
		if ( $query->have_posts() ) {
			foreach ( $query->posts as $attachment_id ) {
				$existing_file = get_attached_file( $attachment_id );
				if ( $existing_file && file_exists( $existing_file ) ) {
					if ( filesize( $existing_file ) === $size && basename( $existing_file ) === $filename ) {
						return (int) $attachment_id;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Check duplicate by MD5 hash (most accurate)
	 *
	 * @param string $file_path Absolute path to file.
	 * @return int|false Attachment ID or false.
	 */
	protected static function check_duplicate_by_hash( $file_path ) {
		$hash = md5_file( $file_path );

		// First check our custom meta
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
		if ( $query->have_posts() ) {
			return (int) $query->posts[0];
		}

		// Fallback: check all attachments and compare their file hashes
		$filename = basename( $file_path );
		$args     = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'fields'         => 'ids',
			'posts_per_page' => 100, // Check up to 100 files with same name
			's'              => pathinfo( $filename, PATHINFO_FILENAME ),
		];

		$query = new \WP_Query( $args );
		if ( $query->have_posts() ) {
			foreach ( $query->posts as $attachment_id ) {
				$existing_file = get_attached_file( $attachment_id );
				if ( $existing_file && file_exists( $existing_file ) ) {
					if ( md5_file( $existing_file ) === $hash ) {
						return (int) $attachment_id;
					}
				}
			}
		}

		return false;
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

		// Determine file operation mode
		$file_operation = $options['file_operation'] ?? 'keep';

		// For 'keep' mode - use file in current location without copying/moving
		if ( 'keep' === $file_operation ) {
			$dest_path = $file_path;
		} else {
			// For 'copy' or 'move' modes - copy/move to uploads directory
			// Get WordPress upload directory (uses year/month structure).
			$uploads      = wp_upload_dir();
			$uploads_root = trailingslashit( $uploads['basedir'] );

			// Calculate relative path from uploads root to preserve structure
			$relative_path = '';
			$file_dir      = trailingslashit( dirname( $file_path ) );

			// Get path relative to uploads root
			if ( 0 === strpos( $file_dir, $uploads_root ) ) {
				$relative_path = substr( $file_dir, strlen( $uploads_root ) );
				$relative_path = trim( $relative_path, '/' );
			}

			// Build destination directory: uploads/YYYY/MM/relative/path
			$dest_dir = $uploads['path'];
			if ( ! empty( $relative_path ) ) {
				$dest_dir = $dest_dir . '/' . $relative_path;
				if ( ! file_exists( $dest_dir ) ) {
					wp_mkdir_p( $dest_dir );
				}
			}

			// Generate unique filename.
			$dest_path = $dest_dir . '/' . wp_unique_filename( $dest_dir, basename( $file_path ) );

			// Copy or move file based on option.
			if ( 'move' === $file_operation ) {
				if ( ! rename( $file_path, $dest_path ) ) {
					return new \WP_Error( 'move_failed', __( 'Failed to move file to uploads directory', 'wp-advanced-import-export' ) );
				}
			} else {
				// Default to 'copy'
				if ( ! copy( $file_path, $dest_path ) ) {
					return new \WP_Error( 'copy_failed', __( 'Failed to copy file to uploads directory', 'wp-advanced-import-export' ) );
				}
			}
		}

		// Create attachment.
		$attachment = [
			'post_mime_type' => $filetype['type'],
			'post_title'     => sanitize_file_name( basename( $dest_path ) ),
			'post_status'    => 'inherit',
		];

		$attach_id = wp_insert_attachment( $attachment, $dest_path );
		if ( is_wp_error( $attach_id ) ) {
			// Clean up file if attachment creation failed (only if we copied/moved it).
			if ( 'keep' !== $file_operation && file_exists( $dest_path ) ) {
				unlink( $dest_path );
			}
			return $attach_id;
		}

		// Generate metadata (thumbnails) if requested.
		if ( empty( $options['skip_thumbnails'] ) && wp_attachment_is_image( $attach_id ) ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$meta = wp_generate_attachment_metadata( $attach_id, $dest_path );
			wp_update_attachment_metadata( $attach_id, $meta );
		}

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

		// Assign to Real Media Library folder if requested.
		if ( ! empty( $options['rml_integration'] ) && ! empty( $options['rml_folder_structure'] ) ) {
			self::assign_to_rml_folder( $attach_id, $file_path, $options );
		}

		return $attach_id;
	}

	/**
	 * Assign attachment to Real Media Library folder
	 *
	 * @param int    $attach_id Attachment ID.
	 * @param string $file_path Original file path (before copy/move).
	 * @param array  $options   Import options.
	 * @return void
	 */
	private static function assign_to_rml_folder( $attach_id, $file_path, $options ) {
		if ( ! function_exists( 'wp_rml_create' ) || ! function_exists( 'wp_rml_move' ) ) {
			return; // RML not available.
		}

		$base_folder = $options['base_folder'] ?? '';
		if ( empty( $base_folder ) ) {
			return;
		}

		// Get uploads root directory
		$uploads      = wp_upload_dir();
		$uploads_root = trailingslashit( $uploads['basedir'] );

		// Determine which path to use for RML structure:
		// - If file_operation = 'keep', use original file_path (e.g., /uploads/ftp/ddd/file.jpg)
		// - If file_operation = 'copy' or 'move', file is now in /uploads/YYYY/MM/ftp/ddd/file.jpg
		// But we still want RML structure based on ORIGINAL location (ftp -> ddd)

		// Use original file location to determine RML structure
		$file_dir = dirname( $file_path );

		// Calculate relative path from uploads root
		$relative_dir = '';
		if ( 0 === strpos( $file_dir, $uploads_root ) ) {
			$relative_dir = substr( $file_dir, strlen( $uploads_root ) );
			$relative_dir = trim( $relative_dir, '/' );
		}

		if ( empty( $relative_dir ) || '.' === $relative_dir ) {
			return; // No subfolder - file is in uploads root.
		}

		// Create RML folder hierarchy from uploads root.
		// Example: ftp/ddd -> creates "ftp" folder in root, then "ddd" inside it
		$folders   = explode( '/', $relative_dir );
		$parent_id = -1; // Root folder.

		foreach ( $folders as $folder_name ) {
			if ( empty( $folder_name ) ) {
				continue;
			}

			$folder_id = self::find_rml_folder_by_name( $folder_name, $parent_id );
			if ( ! $folder_id ) {
				// Create folder - type 0 = normal folder
				// Using wp_rml_create_or_return_existing_id for better compatibility
				$folder_id = function_exists( 'wp_rml_create_or_return_existing_id' )
					? wp_rml_create_or_return_existing_id( $folder_name, $parent_id, 0 )
					: wp_rml_create( $folder_name, $parent_id, 0 );
			}
			$parent_id = $folder_id;
		}

		// Move attachment to final folder.
		if ( $parent_id > 0 ) {
			wp_rml_move( $parent_id, [ $attach_id ] );
		}
	}

	/**
	 * Find RML folder by name and parent
	 *
	 * @param string $name      Folder name.
	 * @param int    $parent_id Parent folder ID (-1 for root).
	 * @return int|false Folder ID or false if not found.
	 */
	private static function find_rml_folder_by_name( $name, $parent_id = -1 ) {
		global $wpdb;

		$result = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$wpdb->prefix}realmedialibrary WHERE name = %s AND parent = %d",
				$name,
				$parent_id
			)
		);

		return $result ? (int) $result : false;
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
			return [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico', 'tiff', 'tif' ];
		}

		if ( 'videos' === $type ) {
			return [ 'mp4', 'mov', 'avi', 'wmv', 'flv', 'mkv', 'webm', 'ogv', 'm4v', 'mpeg', 'mpg' ];
		}

		if ( 'audio' === $type ) {
			return [ 'mp3', 'wav', 'ogg', 'oga', 'm4a', 'aac', 'flac', 'wma' ];
		}

		if ( 'documents' === $type ) {
			return [ 'pdf', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx', 'odt', 'txt', 'rtf' ];
		}

		// For "all" - only media types (images, videos, audio)
		$mime_types = get_allowed_mime_types();
		$extensions = [];

		foreach ( $mime_types as $ext => $mime ) {
			// Only include media mime types (image/, video/, audio/)
			if ( preg_match( '/^(image|video|audio)\//', $mime ) ) {
				$ext_array  = explode( '|', $ext );
				$extensions = array_merge( $extensions, $ext_array );
			}
		}

		return array_unique( $extensions );
	}
}

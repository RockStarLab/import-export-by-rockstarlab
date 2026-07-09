<?php
/**
 * Media Sync Helper
 *
 * Provides folder scanning and file importing for Media Folder Sync feature.
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

class Media_Sync {

	/**
	 * Resolve and validate a media-sync source file.
	 *
	 * Both the file and its allowed root must already exist. Using real paths
	 * prevents traversal and symlink escapes before a copy, move, or delete.
	 *
	 * @param string $file_path    Source file path.
	 * @param string $allowed_root Selected media-sync folder.
	 * @return string|\WP_Error Canonical file path or an error.
	 */
	public static function validate_source_file( $file_path, $allowed_root ) {
		$file_path    = wp_normalize_path( (string) $file_path );
		$allowed_root = wp_normalize_path( (string) $allowed_root );
		$real_file    = realpath( $file_path );
		$real_root    = realpath( $allowed_root );

		if ( false === $real_file || false === $real_root || ! is_file( $real_file ) || ! is_dir( $real_root ) ) {
			return new \WP_Error( 'invalid_source_file', __( 'The selected media file is invalid or no longer exists.', 'import-export-by-rockstarlab' ) );
		}

		$real_file = wp_normalize_path( $real_file );
		$real_root = untrailingslashit( wp_normalize_path( $real_root ) );

		if ( 0 !== strpos( $real_file, trailingslashit( $real_root ) ) ) {
			return new \WP_Error( 'invalid_source_file', __( 'The selected media file is outside the scanned folder.', 'import-export-by-rockstarlab' ) );
		}

		$uploads      = wp_upload_dir();
		$uploads_root = realpath( $uploads['basedir'] );
		if ( false === $uploads_root || 0 !== strpos( $real_file, trailingslashit( wp_normalize_path( $uploads_root ) ) ) ) {
			return new \WP_Error( 'invalid_source_file', __( 'The selected media file must be inside the WordPress uploads directory.', 'import-export-by-rockstarlab' ) );
		}

		return $real_file;
	}

	/**
	 * Scan folder and return list of files matching options
	 *
	 * @param string $folder_path Absolute path to folder.
	 * @param array  $options Options (recursive, file_types).
	 * @return array|\WP_Error Array of files or WP_Error on failure.
	 */
	public static function scan_folder( $folder_path, $options = [] ) {
		// Convert string 'false'/'true' to boolean
		$recursive = filter_var( $options['recursive'] ?? false, FILTER_VALIDATE_BOOLEAN );

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
					__( 'Folder %s not found', 'import-export-by-rockstarlab' ),
					$folder_path
				)
			);
		}

		$files = [];

		if ( $recursive ) {
			$it = new \RecursiveDirectoryIterator( $folder_path, \FilesystemIterator::SKIP_DOTS );
			$ri = new \RecursiveIteratorIterator( $it );
		} else {
			$ri = new \FilesystemIterator( $folder_path, \FilesystemIterator::SKIP_DOTS );
		}

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
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.
				[
					'key'   => 'rsl_ie_file_size',
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
		if ( ! $hash ) {
			return false;
		}

		// Use the shared index before the legacy filename-based fallback.
		$indexed_attachment = Media_Hash::get_attachment_by_hash( $hash );
		if ( $indexed_attachment ) {
			return $indexed_attachment;
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
						Media_Hash::store_attachment_hash( $attachment_id, $hash, $existing_file );
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
		$base_folder = $options['base_folder'] ?? '';
		$file_path   = self::validate_source_file( $file_path, $base_folder );
		if ( is_wp_error( $file_path ) ) {
			return $file_path;
		}

		// Get allowed mime types (this will include SVG if SVG Support plugin is active)
		$allowed_mimes = get_allowed_mime_types();
		$filetype      = wp_check_filetype( $file_path, $allowed_mimes );

		if ( ! $filetype['type'] ) {
			return new \WP_Error( 'invalid_file_type', __( 'Unsupported file type', 'import-export-by-rockstarlab' ) );
		}

		// Handle duplicate checking based on duplicate_handling option
		$duplicate_handling = $options['duplicate_handling'] ?? 'skip';
		$duplicate_check    = $options['duplicate_check'] ?? 'hash';
		$existing_attach_id = null;

		if ( 'skip' === $duplicate_handling ) {
			// Skip mode: check for duplicate and return error if found
			$duplicate = self::check_duplicate( $file_path, $duplicate_check );
			if ( $duplicate ) {
				return new \WP_Error( 'duplicate_file', __( 'File already exists in media library', 'import-export-by-rockstarlab' ), [ 'attachment_id' => $duplicate ] );
			}
		} elseif ( 'overwrite' === $duplicate_handling || 'override' === $duplicate_handling ) {
			// Override/Overwrite mode: find existing attachment to update
			$existing_attach_id = self::check_duplicate( $file_path, $duplicate_check );
		}
		// If duplicate_handling is 'import' (or any other value), always import without checking

		// Determine file operation mode
		$file_operation = $options['file_operation'] ?? 'keep';

		// If override mode and existing attachment found, replace its file
		if ( $existing_attach_id ) {
			// Get existing attachment file path
			$existing_file = get_attached_file( $existing_attach_id );

			// Replace the file
			if ( 'keep' === $file_operation ) {
				// For keep mode, we can't override (file is in different location)
				// Fall through to create new attachment
				$existing_attach_id = null;
				$dest_path          = $file_path;
			} else {
				// Copy/move new file to replace existing.
				if ( 'move' === $file_operation ) {
					if ( ! @rename( $file_path, $existing_file ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.rename_rename -- Move is intentionally attempted before copy/delete fallback.
						if ( ! copy( $file_path, $existing_file ) ) {
							return new \WP_Error( 'move_failed', __( 'Failed to move file to replace existing', 'import-export-by-rockstarlab' ) );
						}
						wp_delete_file( $file_path );
					}
					if ( ! file_exists( $existing_file ) ) {
						return new \WP_Error( 'move_failed', __( 'Failed to move file to replace existing', 'import-export-by-rockstarlab' ) );
					}
				} elseif ( ! copy( $file_path, $existing_file ) ) {
					return new \WP_Error( 'copy_failed', __( 'Failed to copy file to replace existing', 'import-export-by-rockstarlab' ) );
				}

				$dest_path = $existing_file;
				$attach_id = $existing_attach_id;
			}
		}

		// For new attachments (not overriding existing), handle file location
		if ( ! $existing_attach_id ) {
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
					if ( ! @rename( $file_path, $dest_path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged,WordPress.WP.AlternativeFunctions.rename_rename -- Move is intentionally attempted before copy/delete fallback.
						if ( ! copy( $file_path, $dest_path ) ) {
							return new \WP_Error( 'move_failed', __( 'Failed to move file to uploads directory', 'import-export-by-rockstarlab' ) );
						}
						wp_delete_file( $file_path );
					}
					if ( ! file_exists( $dest_path ) ) {
						return new \WP_Error( 'move_failed', __( 'Failed to move file to uploads directory', 'import-export-by-rockstarlab' ) );
					}
				} else {
					// Default to 'copy'
					if ( ! copy( $file_path, $dest_path ) ) {
						return new \WP_Error( 'copy_failed', __( 'Failed to copy file to uploads directory', 'import-export-by-rockstarlab' ) );
					}
				}
			}
		}

		// Create new attachment if not overriding existing
		if ( ! $existing_attach_id ) {
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
					wp_delete_file( $dest_path );
				}
				return $attach_id;
			}

			// Fix URL for "keep" mode when the file is already inside uploads.
			if ( 'keep' === $file_operation ) {
				$uploads_check = wp_upload_dir();
				$uploads_base  = trailingslashit( wp_normalize_path( $uploads_check['basedir'] ) );
				$dest_path     = wp_normalize_path( $dest_path );

				if ( 0 === strpos( $dest_path, $uploads_base ) ) {
					$relative = ltrim( substr( $dest_path, strlen( $uploads_base ) ), '/' );
					// Encode each path segment so that spaces and special chars become valid URL parts.
					$encoded_relative = implode(
						'/',
						array_map( 'rawurlencode', explode( '/', $relative ) )
					);
					$correct_url      = trailingslashit( $uploads_check['baseurl'] ) . $encoded_relative;
					update_post_meta( $attach_id, 'rsl_ie_file_url', esc_url_raw( $correct_url ) );
				}
			}
		}

			// Generate metadata (thumbnails) if requested.
		if ( empty( $options['skip_thumbnails'] ) && wp_attachment_is_image( $attach_id ) ) {
			Fs::load_image_core();
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
		Media_Hash::store_attachment_hash( $attach_id, $hash, $dest_path );
		update_post_meta( $attach_id, 'rsl_ie_original_path', $file_path );

		// Assign to Real Media Library folder if requested.
		// Use filter_var to handle the string "false" that jQuery AJAX serializes from a boolean false.
		if ( filter_var( $options['rml_integration'] ?? false, FILTER_VALIDATE_BOOLEAN ) && ! empty( $options['rml_folder_structure'] ) ) {
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

		// Use original file location to determine RML structure.
		$file_dir = dirname( $file_path );

		// Strategy 1: compute relative path from the uploads root (files already inside uploads).
		$relative_dir = '';
		if ( 0 === strpos( $file_dir, $uploads_root ) ) {
			$relative_dir = substr( $file_dir, strlen( $uploads_root ) );
			$relative_dir = trim( $relative_dir, '/' );
		}

		// Strategy 2: if file is outside uploads (e.g. "keep" mode with an external directory),
		// compute the relative path from the base_folder the user selected instead.
		// Example: base=/home/.../imgs, file=/home/.../imgs/222/333/file.jpg → relative=222/333
		if ( ( empty( $relative_dir ) || '.' === $relative_dir ) && ! empty( $base_folder ) ) {
			$base_folder_slash = trailingslashit( $base_folder );
			if ( 0 === strpos( trailingslashit( $file_dir ), $base_folder_slash ) ) {
				$relative_dir = substr( $file_dir, strlen( $base_folder_slash ) );
				$relative_dir = trim( $relative_dir, '/' );
			}
		}

		if ( empty( $relative_dir ) || '.' === $relative_dir ) {
			return; // No subfolder - file is directly in root/base folder.
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

	/** // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
	 * Find RML folder by name and parent
	 *
	 * @param string $name      Folder name.
	 * @param int    $parent_id Parent folder ID (-1 for root).
	 * @return int|false Folder ID or false if not found.
	 */
	private static function find_rml_folder_by_name( $name, $parent_id = -1 ) {
		global $wpdb;

		$result = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
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
			return array_values(
				array_filter(
					array_unique(
						array_map(
							static function ( $ext ) {
								return strtolower( ltrim( trim( (string) $ext ), '.' ) );
							},
							$custom_types
						)
					)
				)
			);
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

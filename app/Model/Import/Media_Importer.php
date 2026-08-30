<?php
/**
 * Media Importer
 *
 * Handles importing media files (images, documents, etc.)
 *
 * @package RockStarLab\ImportExport\Model\Import
 */

namespace RockStarLab\ImportExport\Model\Import;

use RockStarLab\ImportExport\Helper\FS;
use RockStarLab\ImportExport\Helper\ACF_Fields;

defined( 'ABSPATH' ) || exit;

class Media_Importer extends Abstract_Importer {

	/**
	 * Get importer name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'media';
	}

	/**
	 * Get importer description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Import media files (images, documents, videos)', 'import-export-by-rockstarlab' );
	}

	/**
	 * Get required fields
	 *
	 * @return array
	 */
	public function get_required_fields() {
		return [ 'file' ]; // URL or file path
	}

	/**
	 * Get optional fields
	 *
	 * @return array
	 */
	public function get_optional_fields() {
		return [
			// ID field (for updating existing attachments)
			'ID',              // Attachment ID

			// Primary fields (aliases)
			'title',           // Alias for post_title
			'caption',         // Alias for post_excerpt
			'alt_text',        // Alt text meta
			'description',     // Alias for post_content
			'post_parent',     // Parent post ID
			'filename',        // Desired filename

			// Standard WordPress fields
			'post_title',      // Media title
			'post_content',    // Media description
			'post_excerpt',    // Media caption
			'post_author',     // Author ID
			'post_date',       // Upload date
			'post_date_gmt',   // Upload date GMT
			'post_modified',   // Modified date
			'post_modified_gmt', // Modified date GMT
			'guid',            // GUID (read-only, for reference)

			// File fields (frontend naming)
			'file_url',        // File URL (alias for url)
			'file_path',       // Local file path (alias for path)
			'file_name',       // File name (alias for filename)
			'file_extension',  // File extension (read-only, for reference)

			// File fields (backend naming)
			'url',             // File URL
			'path',            // Local file path

			// Mime type
			'post_mime_type',  // MIME type (read-only, auto-detected)
			'mime_type',       // MIME type alias

			// Image metadata
			'width',           // Image width (read-only, auto-detected)
			'height',          // Image height (read-only, auto-detected)
			'file_size',       // File size in bytes (read-only, auto-detected)

			// Author fields
			'author_name',     // Author display name (for reference)
			'author_email',    // Author email (for reference)

			// Parent post fields
			'attached_post_title', // Parent post title (for reference)
		];
	}

	/**
	 * Get supported options
	 *
	 * @return array
	 */
	public function get_supported_options() {
		return [
			'duplicate_mode'      => 'How to handle duplicates: skip, update, create',
			'duplicate_check'     => 'Field to check for duplicates: ID, file_url, filename, post_title',
			'download_timeout'    => 'Timeout for downloading files from URL (seconds)',
			'generate_thumbnails' => 'Whether to generate thumbnail sizes',
		];
	}

	/**
	 * Set options
	 *
	 * Maps UI option names to the internal option names used by this importer.
	 *
	 * @param array $options Options to set.
	 */
	public function set_options( $options ) {
		// UI sends: duplicate_handling and/or if_exists, plus unique_field.
		if ( isset( $options['duplicate_handling'] ) && ! isset( $options['duplicate_mode'] ) ) {
			$options['duplicate_mode'] = $options['duplicate_handling'];
		}
		if ( isset( $options['if_exists'] ) && ! isset( $options['duplicate_mode'] ) ) {
			$options['duplicate_mode'] = $options['if_exists'];
		}
		if ( isset( $options['unique_field'] ) && ! isset( $options['duplicate_check'] ) ) {
			$options['duplicate_check'] = $options['unique_field'];
		}

		// Back-compat: UI previously supported 'ignore'. Treat it as 'update'.
		if ( isset( $options['duplicate_mode'] ) && 'ignore' === $options['duplicate_mode'] ) {
			$options['duplicate_mode'] = 'update';
		}

		parent::set_options( $options );
	}

	/**
	 * Get default options
	 *
	 * @return array
	 */
	protected function get_default_options() {
		return array_merge(
			parent::get_default_options(),
			[
				'download_timeout'    => 30,
				'generate_thumbnails' => true,
				'duplicate_check'     => 'file_url',
			]
		);
	}

	/**
	 * Import single media file
	 *
	 * @param array $item  Media data
	 * @param int   $index Item index
	 * @return int|string|WP_Error Attachment ID, 'skipped', or WP_Error
	 */
	public function import_item( $item, $index ) {
		// Normalize field aliases
		$item = $this->normalize_item_fields( $item );

		// Get file source (support multiple field names)
		$file_source = $item['file'] ?? $item['file_url'] ?? $item['file_path'] ?? $item['url'] ?? $item['path'] ?? '';

		if ( empty( $file_source ) ) {
			return new \WP_Error( 'missing_file', __( 'File source is required', 'import-export-by-rockstarlab' ) );
		}

		// Check if file is URL or local path
		if ( filter_var( $file_source, FILTER_VALIDATE_URL ) ) {
			return $this->import_from_url( $file_source, $item );
		}

		return $this->import_from_path( $file_source, $item );
	}

	/**
	 * Normalize item field names (convert aliases to canonical names)
	 *
	 * @param array $item Media data
	 * @return array Normalized item data
	 */
	private function normalize_item_fields( $item ) {
		// Title aliases
		if ( isset( $item['title'] ) && ! isset( $item['post_title'] ) ) {
			$item['post_title'] = $item['title'];
		}

		// Caption aliases
		if ( isset( $item['caption'] ) && ! isset( $item['post_excerpt'] ) ) {
			$item['post_excerpt'] = $item['caption'];
		}

		// Description aliases
		if ( isset( $item['description'] ) && ! isset( $item['post_content'] ) ) {
			$item['post_content'] = $item['description'];
		}

		// Filename aliases
		if ( isset( $item['file_name'] ) && ! isset( $item['filename'] ) ) {
			$item['filename'] = $item['file_name'];
		}

		// File URL aliases
		if ( isset( $item['file_url'] ) && ! isset( $item['url'] ) ) {
			$item['url'] = $item['file_url'];
		}

		// File path aliases
		if ( isset( $item['file_path'] ) && ! isset( $item['path'] ) ) {
			$item['path'] = $item['file_path'];
		}

		// MIME type aliases
		if ( isset( $item['post_mime_type'] ) && ! isset( $item['mime_type'] ) ) {
			$item['mime_type'] = $item['post_mime_type'];
		}

		return $item;
	}

	/**
	 * Import media from URL
	 *
	 * @param string $url  File URL
	 * @param array  $item Media data
	 * @return int|WP_Error Attachment ID or WP_Error
	 */
	private function import_from_url( $url, $item ) {
		$source_match = $this->find_existing_by_source_id( $item );
		if ( ! $source_match ) {
			$source_match = $this->find_existing_by_source_url( (string) $url );
		}
		if ( $source_match ) {
			$this->set_attachment_metadata( (int) $source_match, $item );
			$this->record_source_id_map( $item, (int) $source_match );
			return 'updated';
		}

		// Check for an exact existing attachment first.
		$existing = $this->find_existing_attachment( $item, $url, false );
		if ( $existing ) {
			$duplicate_mode = $this->get_option( 'duplicate_mode', 'skip' );

			if ( 'skip' === $duplicate_mode ) {
				return 'skipped';
			}

			if ( 'update' === $duplicate_mode ) {
				$this->set_attachment_metadata( (int) $existing, $item );
				$this->record_source_id_map( $item, (int) $existing );
				return 'updated';
			}

			// 'create' falls through to create new attachment.
		}

		// If the source file already exists in the local uploads directory but is
		// not attached yet, attach that exact file before falling back to a
		// download. This preserves filename/permalink identity across reruns.
		$local_attachment_id = $this->attach_existing_local_file_from_url( $url, $item );
		if ( $local_attachment_id ) {
			$this->set_attachment_metadata( (int) $local_attachment_id, $item );
			$this->record_source_id_map( $item, (int) $local_attachment_id );
			if ( $this->get_option( 'generate_thumbnails', true ) ) {
				$this->generate_thumbnails( (int) $local_attachment_id );
			}
			return (int) $local_attachment_id;
		}

		// Last duplicate check: compare hashes, which may find a previously
		// imported attachment whose filename was auto-renamed by WordPress.
		$existing = $this->find_existing_attachment( $item, $url, true );
		if ( $existing ) {
			$duplicate_mode = $this->get_option( 'duplicate_mode', 'skip' );

			if ( 'skip' === $duplicate_mode ) {
				return 'skipped';
			}

			if ( 'update' === $duplicate_mode ) {
				$this->set_attachment_metadata( (int) $existing, $item );
				$this->record_source_id_map( $item, (int) $existing );
				return 'updated';
			}
		}

		// No existing match found — honor "If No Match Found" option (UI: if_not_exists).
		$if_not_exists = (string) $this->get_option( 'if_not_exists', 'create' );
		if ( 'skip' === $if_not_exists ) {
			return 'skipped';
		}

		// Download file
		$tmp_file = $this->download_file( $url );
		if ( is_wp_error( $tmp_file ) ) {
			return $tmp_file;
		}

		// Get filename
		$filename = $item['filename'] ?? basename( wp_parse_url( $url, PHP_URL_PATH ) );

		$relative_path = $this->get_upload_relative_path_from_url( $url );
		if ( '' !== $relative_path ) {
			$attachment_id = $this->upload_file_to_relative_path( $tmp_file, $relative_path, $item );

			@wp_delete_file( $tmp_file );

			return $attachment_id;
		}

		// Upload to media library
		$attachment_id = $this->upload_file( $tmp_file, $filename, $item );

		// Clean up temp file
		@wp_delete_file( $tmp_file );

		return $attachment_id;
	}

	/**
	 * Copy a downloaded file into the source uploads-relative path and attach it.
	 *
	 * @param string $tmp_file      Temporary downloaded file path.
	 * @param string $relative_path Uploads-relative path from the source site.
	 * @param array  $item          Media data.
	 * @return int|\WP_Error Attachment ID or error.
	 */
	private function upload_file_to_relative_path( $tmp_file, $relative_path, $item ) {
		$relative_path = ltrim( wp_normalize_path( (string) $relative_path ), '/\\' );
		if ( '' === $relative_path || false !== strpos( $relative_path, '..' ) ) {
			return new \WP_Error( 'invalid_relative_path', __( 'Invalid media relative path.', 'import-export-by-rockstarlab' ) );
		}

		$uploads = wp_upload_dir();
		if ( empty( $uploads['basedir'] ) || is_wp_error( $uploads ) ) {
			return new \WP_Error( 'upload_dir_error', __( 'Could not resolve uploads directory.', 'import-export-by-rockstarlab' ) );
		}

		$base_dir    = wp_normalize_path( trailingslashit( $uploads['basedir'] ) );
		$target_file = wp_normalize_path( $base_dir . $relative_path );
		$target_dir  = dirname( $target_file );

		if ( ! wp_mkdir_p( $target_dir ) ) {
			return new \WP_Error( 'upload_dir_create_failed', __( 'Could not create target media directory.', 'import-export-by-rockstarlab' ) );
		}

		$real_base      = realpath( $base_dir );
		$real_dir       = realpath( $target_dir );
		$real_base_path = $real_base ? wp_normalize_path( untrailingslashit( $real_base ) ) : '';
		$real_dir_path  = $real_dir ? wp_normalize_path( untrailingslashit( $real_dir ) ) : '';
		if ( ! $real_base_path || ! $real_dir_path || ( $real_base_path !== $real_dir_path && 0 !== strpos( $real_dir_path, trailingslashit( $real_base_path ) ) ) ) {
			return new \WP_Error( 'invalid_target_path', __( 'Target media path is outside uploads directory.', 'import-export-by-rockstarlab' ) );
		}

		if ( file_exists( $target_file ) ) {
			$filename      = wp_unique_filename( $target_dir, wp_basename( $relative_path ) );
			$relative_path = trailingslashit( dirname( $relative_path ) ) . $filename;
			$target_file   = wp_normalize_path( trailingslashit( $target_dir ) . $filename );
		}

		if ( ! copy( $tmp_file, $target_file ) ) {
			return new \WP_Error( 'upload_copy_failed', __( 'Could not copy media file into uploads directory.', 'import-export-by-rockstarlab' ) );
		}

		$attachment_id = $this->create_attachment_for_file( $target_file, $relative_path, $item );
		if ( is_wp_error( $attachment_id ) ) {
			@wp_delete_file( $target_file );
		}

		return $attachment_id;
	}

	/**
	 * Import media from local file path
	 *
	 * @param string $file_path File path
	 * @param array  $item      Media data
	 * @return int|WP_Error Attachment ID or WP_Error
	 */
	private function import_from_path( $file_path, $item ) {
		if ( ! file_exists( $file_path ) ) {
			// translators: %s is a dynamic value.
			return new \WP_Error( 'file_not_found', sprintf( __( 'File not found: %s', 'import-export-by-rockstarlab' ), $file_path ) );
		}

		// Local path imports should still honor skip/create based on duplicate checks.
		$existing = $this->find_existing_attachment( $item );
		if ( $existing ) {
			$duplicate_mode = $this->get_option( 'duplicate_mode', 'skip' );

			if ( 'skip' === $duplicate_mode ) {
				return 'skipped';
			}

			if ( 'update' === $duplicate_mode ) {
				$this->set_attachment_metadata( (int) $existing, $item );
				$this->record_source_id_map( $item, (int) $existing );
				return 'updated';
			}
			// 'create' falls through to create new attachment.
		} else {
			$if_not_exists = (string) $this->get_option( 'if_not_exists', 'create' );
			if ( 'skip' === $if_not_exists ) {
				return 'skipped';
			}
		}

		$filename      = $item['filename'] ?? basename( $file_path );
		$attachment_id = $this->upload_file( $file_path, $filename, $item );

		return $attachment_id;
	}

	/**
	 * Download file from URL
	 *
	 * @param string $url File URL
	 * @return string|WP_Error Temporary file path or WP_Error
	 */
	private function download_file( $url ) {
		$timeout = $this->get_option( 'download_timeout', 30 );

		$args = [
			'timeout'  => $timeout,
			'stream'   => true,
			'filename' => wp_tempnam( $url ),
		];

		$response = wp_remote_get( $url, $args );

		// In local/dev environments, WordPress can reject ".local"/".test" hosts as
		// "unsafe" and return "A valid URL was not provided." even though the URL
		// is reachable. Retry with `reject_unsafe_urls=false` for common dev TLDs.
		if ( is_wp_error( $response ) ) {
			$host        = wp_parse_url( $url, PHP_URL_HOST );
			$is_dev_host = is_string( $host ) && preg_match( '/\\.(local|test|localhost)$/i', $host );

			if ( $is_dev_host ) {
				$args['reject_unsafe_urls'] = false;
				$response                   = wp_remote_get( $url, $args );
			}
		}

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error(
				'download_failed',
				sprintf(
					/* translators: %s: HTTP status code */
					__( 'Failed to download file. HTTP status: %s', 'import-export-by-rockstarlab' ),
					wp_remote_retrieve_response_code( $response )
				)
			);
		}

		return $response['filename'];
	}

	/**
	 * Upload file to media library
	 *
	 * @param string $file_path File path
	 * @param string $filename  Desired filename
	 * @param array  $item      Media data
	 * @return int|WP_Error Attachment ID or WP_Error
	 */
	private function upload_file( $file_path, $filename, $item ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Prepare file array
		$file_array = [
			'name'     => $filename,
			'tmp_name' => $file_path,
		];

		// Get parent post ID
		$parent_id = 0;
		if ( ! empty( $item['post_parent'] ) && is_numeric( $item['post_parent'] ) ) {
			$parent_id = absint( $item['post_parent'] );
		}

		// Get title (support both 'title' and 'post_title')
		$title = $item['post_title'] ?? $item['title'] ?? null;

		// Upload file
		$attachment_id = media_handle_sideload(
			$file_array,
			$parent_id,
			$title
		);

		if ( is_wp_error( $attachment_id ) ) {
			return $attachment_id;
		}

		// Set attachment metadata
		$this->set_attachment_metadata( $attachment_id, $item );
		$this->record_source_id_map( $item, (int) $attachment_id );

		// Generate thumbnails if needed
		if ( $this->get_option( 'generate_thumbnails', true ) ) {
			$this->generate_thumbnails( $attachment_id );
		}

		return $attachment_id;
	}

	/**
	 * Set attachment metadata
	 *
	 * @param int   $attachment_id Attachment ID
	 * @param array $item          Media data
	 */
	private function set_attachment_metadata( $attachment_id, $item ) {
		// Prepare post data for update
		$post_data = [ 'ID' => $attachment_id ];

		// Set title (post_title)
		if ( ! empty( $item['post_title'] ) ) {
			$post_data['post_title'] = $item['post_title'];
		}

		// Set caption (post_excerpt)
		if ( ! empty( $item['post_excerpt'] ) ) {
			$post_data['post_excerpt'] = $item['post_excerpt'];
		}

		// Set description (post_content)
		if ( ! empty( $item['post_content'] ) ) {
			$post_data['post_content'] = $item['post_content'];
		}

		// Set author
		if ( ! empty( $item['post_author'] ) && is_numeric( $item['post_author'] ) ) {
			$post_data['post_author'] = absint( $item['post_author'] );
		}

		// Set dates (only if not empty)
		if ( ! empty( $item['post_date'] ) ) {
			$post_data['post_date'] = $item['post_date'];
		}
		if ( ! empty( $item['post_date_gmt'] ) ) {
			$post_data['post_date_gmt'] = $item['post_date_gmt'];
		}
		if ( ! empty( $item['post_modified'] ) ) {
			$post_data['post_modified'] = $item['post_modified'];
		}
		if ( ! empty( $item['post_modified_gmt'] ) ) {
			$post_data['post_modified_gmt'] = $item['post_modified_gmt'];
		}

		// Update post if we have any fields to update
		if ( count( $post_data ) > 1 ) {
			wp_update_post( $post_data );
		}

		// Set alt text
		if ( ! empty( $item['alt_text'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $item['alt_text'] );
		}

		$source_url = $item['file'] ?? $item['file_url'] ?? $item['url'] ?? '';
		if ( is_string( $source_url ) && filter_var( $source_url, FILTER_VALIDATE_URL ) ) {
			$source_url = esc_url_raw( $source_url );
			update_post_meta( $attachment_id, 'rsl_ie_source_url', $source_url );
			update_post_meta( $attachment_id, 'rsl_ie_source_url_hash', md5( $source_url ) );
		}

		// Handle ACF fields (with acf_ prefix)
		foreach ( $item as $field_key => $field_value ) {
			if ( strpos( $field_key, 'acf_' ) === 0 ) {
				// Remove 'acf_' prefix to get the actual field name
				$acf_field_name = substr( $field_key, 4 );
				if ( class_exists( ACF_Fields::class ) ) {
					ACF_Fields::import_value( 'media', $attachment_id, $acf_field_name, $field_value );
					continue;
				}

				// Use ACF's update_field() if available, otherwise use update_post_meta()
				if ( function_exists( 'update_field' ) ) {
					update_field( $acf_field_name, $field_value, $attachment_id );
				} else {
					// Fallback to direct post meta
					update_post_meta( $attachment_id, $acf_field_name, $field_value );
				}
			}
		}
	}


	/**
	 *
	 * @param array $item          Imported item data.
	 * @param int   $attachment_id Target attachment ID.
	 * @return void
	 */
	private function record_source_id_map( array $item, $attachment_id ) {
		$source_id     = absint( $item['_rsl_ie_source_id'] ?? ( $item['ID'] ?? 0 ) );
		$attachment_id = absint( $attachment_id );
		$job_id        = absint( $this->job_id );
		if ( $source_id <= 0 || $attachment_id <= 0 || $job_id <= 0 ) {
			return;
		}

		$key = 'rsl_ie_import_post_id_map_' . $job_id;
		$map = get_transient( $key );
		if ( ! is_array( $map ) ) {
			$map = [];
		}

		$map[ (string) $source_id ] = $attachment_id;
		set_transient( $key, $map, DAY_IN_SECONDS );
		// Keep the mapping on the attachment as well. Content/ACF updates may
		// run in a different job, so a job-scoped transient is not sufficient.
		update_post_meta( $attachment_id, '_rsl_ie_source_id', $source_id );
		update_post_meta( $attachment_id, '_rsl_ie_source_attachment_id', $source_id );
	}

	/**
	 * Generate thumbnails for image
	 *
	 * @param int $attachment_id Attachment ID
	 */
	private function generate_thumbnails( $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return;
		}

		// Check if it's an image
		$filetype = wp_check_filetype( $file_path );
		if ( ! in_array( $filetype['type'], [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ], true ) ) {
			return;
		}

		// Generate metadata and thumbnails
		$metadata = wp_generate_attachment_metadata( $attachment_id, $file_path );
		wp_update_attachment_metadata( $attachment_id, $metadata );
	}

	/**
	 * Find existing attachment based on duplicate_check option
	 *
	 * @param array  $item       Media data
	 * @param string $url        File URL (optional, for file_url check)
	 * @param bool   $allow_hash Whether to fall back to remote hash matching.
	 * @return int|null Attachment ID or null
	 */
	private function find_existing_attachment( $item, $url = '', $allow_hash = true ) {
		$by_source_id = $this->find_existing_by_source_id( $item );
		if ( $by_source_id ) {
			return $by_source_id;
		}

		if ( ! empty( $url ) ) {
			$by_source_url = $this->find_existing_by_source_url( (string) $url );
			if ( $by_source_url ) {
				return $by_source_url;
			}
		}

		$check_field = $this->get_option( 'duplicate_check', 'file_url' );

		// Check by title
		if ( in_array( $check_field, [ 'post_title', 'title' ], true ) && ! empty( $item['post_title'] ) ) {
			return $this->find_existing_by_title( (string) $item['post_title'] );
		}

		// Check by ID
		if ( 'ID' === $check_field && ! empty( $item['ID'] ) ) {
			$attachment = get_post( $item['ID'] );
			if ( $attachment && 'attachment' === $attachment->post_type ) {
				return (int) $attachment->ID;
			}
			return null;
		}

		// Check by file_url (filename)
		if ( 'file_url' === $check_field && ! empty( $url ) ) {
			// Fast path: exact basename match.
			$exact = $this->find_existing_by_url( $url );
			if ( $exact ) {
				return $exact;
			}

			// Fallback: handle WordPress auto-renaming (e.g. logo.jpg → logo-1.jpg)
			// by comparing the remote file hash/size against candidates.
			return $allow_hash ? $this->find_existing_by_remote_hash( $url ) : null;
		}

		// Check by filename
		if ( 'filename' === $check_field && ! empty( $item['filename'] ) ) {
			$exact = $this->find_existing_by_filename( $item['filename'] );
			if ( $exact ) {
				return $exact;
			}

			// Best-effort fallback: treat as URL-like filename match.
			if ( $allow_hash && ! empty( $url ) ) {
				return $this->find_existing_by_remote_hash( $url );
			}
		}

			return null;
	}

	/**
	 * Find an existing attachment imported from the same source attachment ID.
	 *
	 * @param array $item Media row.
	 * @return int|null Attachment ID or null.
	 */
	private function find_existing_by_source_id( array $item ) {
		$source_id = absint( $item['_rsl_ie_source_id'] ?? ( $item['ID'] ?? 0 ) );
		if ( $source_id <= 0 ) {
			return null;
		}

		global $wpdb;

		$attachment_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				 WHERE meta_key IN ('_rsl_ie_source_id', '_rsl_ie_source_attachment_id')
				   AND meta_value = %s
				 LIMIT 1",
				(string) $source_id
			)
		);

		return $attachment_id && 'attachment' === get_post_type( (int) $attachment_id ) ? (int) $attachment_id : null;
	}

	/**
	 * Find an existing attachment imported from the same source URL.
	 *
	 * @param string $url Source URL.
	 * @return int|null Attachment ID or null.
	 */
	private function find_existing_by_source_url( string $url ) {
		$source_url = esc_url_raw( $url );
		if ( '' === $source_url ) {
			return null;
		}

		global $wpdb;

		$attachment_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta}
				 WHERE meta_key = 'rsl_ie_source_url_hash'
				   AND meta_value = %s
				 LIMIT 1",
				md5( $source_url )
			)
		);

		return $attachment_id && 'attachment' === get_post_type( (int) $attachment_id ) ? (int) $attachment_id : null;
	}

	/**
	 * Attach an existing local uploads file referenced by a source URL.
	 *
	 * @param string $url  Source attachment URL.
	 * @param array  $item Media row data.
	 * @return int Attachment ID, or 0 when no exact local file is available.
	 */
	private function attach_existing_local_file_from_url( $url, $item ) {
		$relative_path = $this->get_upload_relative_path_from_url( $url );
		if ( '' === $relative_path ) {
			return 0;
		}

		$existing = $this->find_existing_by_attached_file( $relative_path );
		if ( $existing ) {
			return (int) $existing;
		}

		$uploads = wp_upload_dir();
		if ( empty( $uploads['basedir'] ) || is_wp_error( $uploads ) ) {
			return 0;
		}

		$base_dir  = wp_normalize_path( trailingslashit( $uploads['basedir'] ) );
		$file_path = wp_normalize_path( $base_dir . ltrim( $relative_path, '/\\' ) );

		if ( ! file_exists( $file_path ) || ! is_file( $file_path ) ) {
			return 0;
		}

		$real_base = realpath( $base_dir );
		$real_file = realpath( $file_path );
		if ( ! $real_base || ! $real_file || 0 !== strpos( wp_normalize_path( $real_file ), wp_normalize_path( trailingslashit( $real_base ) ) ) ) {
			return 0;
		}

		if ( ! $this->local_file_matches_remote_url( $file_path, $url ) ) {
			return 0;
		}

		$attachment_id = $this->create_attachment_for_file( $file_path, $relative_path, $item );
		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return 0;
		}

		return (int) $attachment_id;
	}

	/**
	 * Check that an unregistered uploads file is the same file as the source URL.
	 *
	 * @param string $file_path Local file path.
	 * @param string $url       Source URL.
	 * @return bool
	 */
	private function local_file_matches_remote_url( $file_path, $url ) {
		if ( ! is_string( $file_path ) || ! file_exists( $file_path ) || ! is_file( $file_path ) ) {
			return false;
		}

		$tmp_file = $this->download_file( $url );
		if ( is_wp_error( $tmp_file ) || ! $tmp_file || ! file_exists( $tmp_file ) ) {
			return false;
		}

		$matches = filesize( $file_path ) === filesize( $tmp_file )
			&& md5_file( $file_path ) === md5_file( $tmp_file );

		@wp_delete_file( $tmp_file );

		return $matches;
	}

	/**
	 * Create a WordPress attachment for an existing uploads file.
	 *
	 * @param string $file_path     Absolute file path.
	 * @param string $relative_path Uploads-relative file path.
	 * @param array  $item          Media row data.
	 * @return int|\WP_Error Attachment ID or error.
	 */
	private function create_attachment_for_file( $file_path, $relative_path, $item ) {
		FS::load_media_core();

		$uploads   = wp_upload_dir();
		$filetype  = wp_check_filetype( $file_path );
		$parent_id = ! empty( $item['post_parent'] ) && is_numeric( $item['post_parent'] ) ? absint( $item['post_parent'] ) : 0;
		$title     = ! empty( $item['post_title'] ) ? (string) $item['post_title'] : preg_replace( '/\.[^.]+$/', '', wp_basename( $file_path ) );

		$attachment = [
			'post_mime_type' => ! empty( $item['mime_type'] ) ? (string) $item['mime_type'] : ( $filetype['type'] ?? '' ),
			'post_title'     => sanitize_text_field( $title ),
			'post_content'   => isset( $item['post_content'] ) ? (string) $item['post_content'] : '',
			'post_excerpt'   => isset( $item['post_excerpt'] ) ? (string) $item['post_excerpt'] : '',
			'post_status'    => 'inherit',
			'post_author'    => ! empty( $item['post_author'] ) && is_numeric( $item['post_author'] ) ? absint( $item['post_author'] ) : get_current_user_id(),
			'guid'           => trailingslashit( $uploads['baseurl'] ) . ltrim( $relative_path, '/\\' ),
		];

		$attachment_id = wp_insert_attachment( wp_slash( $attachment ), $file_path, $parent_id, true );
		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return $attachment_id;
		}

		$this->set_attachment_metadata( (int) $attachment_id, $item );
		$this->record_source_id_map( $item, (int) $attachment_id );

		$metadata = wp_generate_attachment_metadata( (int) $attachment_id, $file_path );
		if ( ! is_wp_error( $metadata ) && is_array( $metadata ) ) {
			wp_update_attachment_metadata( (int) $attachment_id, $metadata );
		}

		return (int) $attachment_id;
	}

	/**
	 * Convert a source uploads URL to a target-site uploads-relative path.
	 *
	 * @param string $url Source URL.
	 * @return string Relative uploads path.
	 */
	private function get_upload_relative_path_from_url( $url ) {
		$path = wp_parse_url( (string) $url, PHP_URL_PATH );
		if ( ! is_string( $path ) || '' === $path ) {
			return '';
		}

		$uploads = wp_upload_dir();
		if ( empty( $uploads['baseurl'] ) || is_wp_error( $uploads ) ) {
			return '';
		}

		$uploads_path = wp_parse_url( $uploads['baseurl'], PHP_URL_PATH );
		$uploads_path = is_string( $uploads_path ) ? untrailingslashit( $uploads_path ) : '';
		if ( '' === $uploads_path ) {
			return '';
		}

		$pos = strpos( $path, $uploads_path . '/' );
		if ( false === $pos ) {
			return '';
		}

		$relative = substr( $path, $pos + strlen( $uploads_path ) + 1 );
		$relative = ltrim( wp_normalize_path( rawurldecode( (string) $relative ) ), '/\\' );

		return false === strpos( $relative, '..' ) ? $relative : '';
	}

	/**
	 * Find an attachment by exact _wp_attached_file value.
	 *
	 * @param string $relative_path Uploads-relative path.
	 * @return int|null Attachment ID.
	 */
	private function find_existing_by_attached_file( $relative_path ) {
		global $wpdb;

		$attachment_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta
				WHERE meta_key = '_wp_attached_file'
				AND meta_value = %s
				LIMIT 1",
				$relative_path
			)
		);

		return $attachment_id ? (int) $attachment_id : null;
	}

		/**
		 * Find existing attachment by exact post_title match.
		 *
		 * @param string $title Attachment title.
		 * @return int|null Attachment ID.
		 */
	private function find_existing_by_title( $title ) {
		$title = (string) $title;
		if ( '' === $title ) {
			return null;
		}

		global $wpdb;

		$attachment_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts}
					WHERE post_type = 'attachment'
					AND post_status = 'inherit'
					AND post_title = %s
					ORDER BY ID DESC
					LIMIT 1",
				$title
			)
		);

		return $attachment_id ? (int) $attachment_id : null;
	}

	/**
	 * Find existing attachment by comparing remote file hash/size to local files.
	 *
	 * This helps detect duplicates even when WordPress generated a unique filename
	 * on upload (e.g. "logo.jpg" becomes "logo-1.jpg").
	 *
	 * @param string $url Remote file URL.
	 * @return int|null Attachment ID or null.
	 */
	private function find_existing_by_remote_hash( $url ) {
		$filename = basename( wp_parse_url( $url, PHP_URL_PATH ) );
		$base     = pathinfo( $filename, PATHINFO_FILENAME );

		if ( '' === $base ) {
			return null;
		}

		global $wpdb;

		// Candidate attachments: anything whose _wp_attached_file contains the base.
		$candidate_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta
				WHERE meta_key = '_wp_attached_file'
				AND meta_value LIKE %s",
				'%' . $wpdb->esc_like( $base ) . '%'
			)
		);

		$tmp_file = $this->download_file( $url );
		if ( is_wp_error( $tmp_file ) || ! file_exists( $tmp_file ) ) {
			return null;
		}

		$remote_size = filesize( $tmp_file );
		$remote_hash = md5_file( $tmp_file );
		$indexed_id  = \RockStarLab\ImportExport\Helper\Media_Hash::get_attachment_by_hash( $remote_hash );
		if ( $indexed_id ) {
			@wp_delete_file( $tmp_file );
			return (int) $indexed_id;
		}
		if ( empty( $candidate_ids ) ) {
			@wp_delete_file( $tmp_file );
			return null;
		}

		foreach ( $candidate_ids as $candidate_id ) {
			$local_file = get_attached_file( (int) $candidate_id );
			if ( ! $local_file || ! file_exists( $local_file ) ) {
				continue;
			}

			// Quick size check first.
			if ( filesize( $local_file ) !== $remote_size ) {
				continue;
			}

			if ( md5_file( $local_file ) === $remote_hash ) {
				\RockStarLab\ImportExport\Helper\Media_Hash::store_attachment_hash( (int) $candidate_id, $remote_hash, $local_file );
				@wp_delete_file( $tmp_file );
				return (int) $candidate_id;
			}
		}

		@wp_delete_file( $tmp_file );
		return null;
	}

	/**
	 * Find existing attachment by filename
	 *
	 * @param string $filename Filename to search for
	 * @return int|null Attachment ID or null
	 */
	private function find_existing_by_filename( $filename ) {
		global $wpdb;
 // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta 
				WHERE meta_key = '_wp_attached_file' 
				AND meta_value LIKE %s 
				LIMIT 1",
				'%' . $wpdb->esc_like( $filename )
			)
		);

		return $attachment_id ? (int) $attachment_id : null;
	}

	/**
	 * Find existing attachment by URL
	 *
	 * @param string $url File URL
	 * @return int|null Attachment ID or null
	 */
	private function find_existing_by_url( $url ) {
		global $wpdb;

		$filename = basename( wp_parse_url( $url, PHP_URL_PATH ) );
 // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM $wpdb->postmeta 
				WHERE meta_key = '_wp_attached_file' 
				AND meta_value LIKE %s 
				LIMIT 1",
				'%' . $wpdb->esc_like( $filename )
			)
		);

		return $attachment_id ? (int) $attachment_id : null;
	}
}

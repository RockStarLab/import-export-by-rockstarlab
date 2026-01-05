<?php
/**
 * Media Importer
 *
 * Handles importing media files (images, documents, etc.)
 *
 * @package WP_AIE\Model\Import
 */

namespace WP_AIE\Model\Import;

use WP_AIE\Helper\FS;

/**
 * Media Importer Class
 *
 * Imports media files with support for:
 * - Upload from URL
 * - Upload from local file path
 * - Thumbnail generation
 * - Attachment metadata
 * - Parent post association
 *
 * @package WP_AIE\Model\Import
 */
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
		return __( 'Import media files (images, documents, videos)', 'wp-advanced-import-export' );
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
			'duplicate_check'     => 'Field to check for duplicates: ID, file_url, filename',
			'download_timeout'    => 'Timeout for downloading files from URL (seconds)',
			'generate_thumbnails' => 'Whether to generate thumbnail sizes',
		];
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
	protected function import_item( $item, $index ) {
		// Normalize field aliases
		$item = $this->normalize_item_fields( $item );
		
		// Get file source (support multiple field names)
		$file_source = $item['file'] ?? $item['file_url'] ?? $item['file_path'] ?? $item['url'] ?? $item['path'] ?? '';
		
		if ( empty( $file_source ) ) {
			return new \WP_Error( 'missing_file', __( 'File source is required', 'wp-advanced-import-export' ) );
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
		// Check for existing attachment
		$existing = $this->find_existing_attachment( $item, $url );
		if ( $existing && 'skip' === $this->get_option( 'duplicate_mode', 'skip' ) ) {
			return 'skipped';
		}

		// Download file
		$tmp_file = $this->download_file( $url );
		if ( is_wp_error( $tmp_file ) ) {
			return $tmp_file;
		}

		// Get filename
		$filename = $item['filename'] ?? basename( parse_url( $url, PHP_URL_PATH ) );

		// Upload to media library
		$attachment_id = $this->upload_file( $tmp_file, $filename, $item );

		// Clean up temp file
		@unlink( $tmp_file );

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
			return new \WP_Error( 'file_not_found', sprintf( __( 'File not found: %s', 'wp-advanced-import-export' ), $file_path ) );
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

		$response = wp_remote_get(
			$url,
			[
				'timeout'  => $timeout,
				'stream'   => true,
				'filename' => wp_tempnam( $url ),
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return new \WP_Error(
				'download_failed',
				sprintf(
					/* translators: %s: HTTP status code */
					__( 'Failed to download file. HTTP status: %s', 'wp-advanced-import-export' ),
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
		
		// Handle ACF fields (with acf_ prefix)
		foreach ( $item as $field_key => $field_value ) {
			if ( strpos( $field_key, 'acf_' ) === 0 ) {
				// Remove 'acf_' prefix to get the actual field name
				$acf_field_name = substr( $field_key, 4 );
				
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
	 * @param array  $item Media data
	 * @param string $url  File URL (optional, for file_url check)
	 * @return int|null Attachment ID or null
	 */
	private function find_existing_attachment( $item, $url = '' ) {
		$check_field = $this->get_option( 'duplicate_check', 'file_url' );

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
			return $this->find_existing_by_url( $url );
		}

		// Check by filename
		if ( 'filename' === $check_field && ! empty( $item['filename'] ) ) {
			return $this->find_existing_by_filename( $item['filename'] );
		}

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

		$filename = basename( parse_url( $url, PHP_URL_PATH ) );

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

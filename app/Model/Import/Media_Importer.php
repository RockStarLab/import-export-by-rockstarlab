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
			'title',
			'caption',
			'alt_text',
			'description',
			'post_parent',
			'filename',
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
		$file_source = $item['file'];

		// Check if file is URL or local path
		if ( filter_var( $file_source, FILTER_VALIDATE_URL ) ) {
			return $this->import_from_url( $file_source, $item );
		}

		return $this->import_from_path( $file_source, $item );
	}

	/**
	 * Import media from URL
	 *
	 * @param string $url  File URL
	 * @param array  $item Media data
	 * @return int|WP_Error Attachment ID or WP_Error
	 */
	private function import_from_url( $url, $item ) {
		// Check for existing attachment by URL
		$existing = $this->find_existing_by_url( $url );
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

		// Upload file
		$attachment_id = media_handle_sideload(
			$file_array,
			$item['post_parent'] ?? 0,
			$item['title'] ?? null
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
		// Set alt text
		if ( ! empty( $item['alt_text'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $item['alt_text'] );
		}

		// Set caption
		if ( ! empty( $item['caption'] ) ) {
			wp_update_post(
				[
					'ID'           => $attachment_id,
					'post_excerpt' => $item['caption'],
				]
			);
		}

		// Set description
		if ( ! empty( $item['description'] ) ) {
			wp_update_post(
				[
					'ID'           => $attachment_id,
					'post_content' => $item['description'],
				]
			);
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

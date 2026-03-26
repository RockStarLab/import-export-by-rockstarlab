<?php
/**
 * Media Hash Controller
 *
 * AJAX endpoints for managing media file hashes
 *
 * @package WP_AIE\Controller
 */

namespace WP_AIE\Controller;

use WP_AIE\Helper\Media_Hash;

defined( 'ABSPATH' ) or exit;

class Media_Hash_Controller extends Base_Controller {

	protected function get_ajax_actions() {
		return [
			'get_hash_statistics'  => [ 'callback' => 'get_hash_statistics' ],
			'bulk_add_hashes'      => [ 'callback' => 'bulk_add_hashes' ],
			'check_duplicate_hash' => [ 'callback' => 'check_duplicate_hash' ],
		];
	}

	/**
	 * Get statistics about hashed media files
	 */
	public function get_hash_statistics() {
		$verification = $this->verify_request( 'aie_media_hash_stats' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification );
		}

		$stats = Media_Hash::get_statistics();
		$this->send_success( $stats );
	}

	/**
	 * Bulk add hashes to existing media files
	 */
	public function bulk_add_hashes() {
		$verification = $this->verify_request( 'aie_bulk_add_hashes' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification );
		}

		$batch_size = absint( $this->get_request_param( 'batch_size', 50 ) );
		$offset     = absint( $this->get_request_param( 'offset', 0 ) );

		// Limit batch size for performance
		$batch_size = min( $batch_size, 100 );

		$result = Media_Hash::bulk_add_hashes( $batch_size, $offset );

		$this->send_success( $result );
	}

	/**
	 * Check if file is a duplicate based on hash
	 */
	public function check_duplicate_hash() {
		$verification = $this->verify_request( 'aie_check_duplicate' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification );
		}

		$this->validate_required_params( [ 'file_path' ] );
		$file_path = $this->get_request_param( 'file_path' );

		// Security: validate file path is within uploads directory
		$upload_dir = wp_upload_dir();
		$base_dir   = $upload_dir['basedir'];
		$real_path  = realpath( $file_path );
		$real_base  = realpath( $base_dir );

		if ( false === $real_path || false === strpos( $real_path, $real_base ) ) {
			$this->send_error(
				new \WP_Error(
					'invalid_path',
					__( 'Invalid file path. Path must be within uploads directory.', 'wp-advanced-import-export' )
				)
			);
		}

		$duplicate_id = Media_Hash::find_duplicate( $real_path );

		if ( $duplicate_id ) {
			$attachment_url = wp_get_attachment_url( $duplicate_id );
			$this->send_success(
				[
					'is_duplicate'   => true,
					'attachment_id'  => $duplicate_id,
					'attachment_url' => $attachment_url,
				]
			);
		} else {
			$this->send_success(
				[
					'is_duplicate' => false,
				]
			);
		}
	}
}

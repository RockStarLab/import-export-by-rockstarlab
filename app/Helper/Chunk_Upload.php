<?php
/**
 * Compatibility adapter for the existing upload wizard.
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

use RockStarLab\ImportExport\Service\File_Service;
use RockStarLab\ImportExport\Service\File_Store;
use RockStarLab\ImportExport\Abilities\Access_Policy;

defined( 'ABSPATH' ) || exit;

/**
 * Keep AJAX names and preview envelopes; the file service owns all file I/O.
 */
class Chunk_Upload {

	/** Register legacy endpoints and cleanup. */
	public function __construct() {
		add_action( 'wp_ajax_rsl_ie_upload_chunk', [ $this, 'handle_chunk_upload' ] );
		add_action( 'wp_ajax_rsl_ie_finalize_upload', [ $this, 'handle_finalize_upload' ] );
		add_action( 'wp_ajax_rsl_ie_abort_upload', [ $this, 'handle_abort_upload' ] );
		add_action( 'wp_ajax_rsl_ie_reload_preview', [ $this, 'handle_reload_preview' ] );
		add_action( 'wp_ajax_rsl_ie_get_custom_post_types', [ $this, 'handle_get_custom_post_types' ] );
		foreach ( [ 'rsl_ie_upload_chunk', 'rsl_ie_finalize_upload', 'rsl_ie_abort_upload', 'rsl_ie_reload_preview', 'rsl_ie_get_custom_post_types' ] as $action ) {
			Ajax_Security::register_action( $action );
		}
		if ( ! wp_next_scheduled( 'rsl_ie_cleanup_old_chunks' ) ) {
			wp_schedule_event( time(), 'daily', 'rsl_ie_cleanup_old_chunks' );
		}
		add_action( 'rsl_ie_cleanup_old_chunks', [ $this, 'cleanup_old_chunks' ] );
	}

	/** Receive one multipart fragment. */
	public function handle_chunk_upload() {
		$this->verify( 'rsl_ie_upload_chunk' );
		$service = new File_Service();
		$record  = $service->begin( $this->manifest(), $this->param( 'upload_id' ) );
		if ( is_wp_error( $record ) ) {
			$this->respond( $record );
			return;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput,WordPress.Security.NonceVerification -- PHP upload origin is verified by File_Service after the nonce above.
		$file = isset( $_FILES['chunk'] ) && is_array( $_FILES['chunk'] ) ? $_FILES['chunk'] : [];
		$this->respond( $service->upload_chunk( $record['file_id'], $this->param( 'chunk_index' ), $file ) );
	}

	/** Finalize using the immutable manifest submitted by the old client. */
	public function handle_finalize_upload() {
		$this->verify( 'rsl_ie_finalize_upload' );
		$service = new File_Service();
		$record  = $service->begin( $this->manifest(), $this->param( 'upload_id' ) );
		if ( is_wp_error( $record ) ) {
			$this->respond( $record );
			return;
		}
		$this->respond( $service->finalize( $record['file_id'], $this->parser_options() ), true );
	}

	/** Cancel only the current administrator's upload session. */
	public function handle_abort_upload() {
		$this->verify( 'rsl_ie_abort_upload' );
		$id = File_Service::legacy_id( $this->param( 'upload_id' ) );
		$this->respond( is_wp_error( $id ) ? $id : ( new File_Service() )->abort( $id ) );
	}

	/** Reload preview from an opaque compatibility reference. */
	public function handle_reload_preview() {
		$this->verify( 'rsl_ie_reload_preview' );
		$service = new File_Service();
		$ref     = $this->param( 'file_id', $this->param( 'file_path' ) );
		$id      = $service->import_reference( $ref );
		$this->respond( is_wp_error( $id ) ? $id : $service->preview( $id, $this->parser_options() ), true );
	}

	/**
	 * Scalar request values, preserving the literal CSV tab delimiter.
	 *
	 * @param string $key Field name.
	 * @param string $fallback Default value.
	 * @return string
	 */
	private function param( $key, $fallback = '' ) {
		// phpcs:ignore WordPress.Security.NonceVerification,WordPress.Security.ValidatedSanitizedInput -- Nonce checked by every handler; services validate each scalar contextually.
		return isset( $_POST[ $key ] ) && is_scalar( $_POST[ $key ] ) ? (string) wp_unslash( $_POST[ $key ] ) : $fallback;
	}

	/**
	 * Read the legacy upload manifest.
	 *
	 * @return array
	 */
	private function manifest() {
		return [
			'file_name'    => $this->param( 'file_name' ),
			'file_size'    => $this->param( 'file_size' ),
			'total_chunks' => $this->param( 'total_chunks' ),
		];
	}

	/**
	 * Convert form booleans at the transport boundary.
	 *
	 * @return array
	 */
	private function parser_options() {
		return [
			'delimiter'  => $this->param( 'delimiter', ',' ),
			'has_header' => in_array( $this->param( 'has_header', 'true' ), [ 'true', '1' ], true ),
			'encoding'   => $this->param( 'encoding', 'auto' ),
		];
	}

	/**
	 * Authenticate a legacy request.
	 *
	 * @param string $action Nonce action.
	 */
	private function verify( $action ) {
		check_ajax_referer( Ajax_Security::nonce_action( $action ), 'nonce' );
		$permission = Access_Policy::check_management();
		if ( is_wp_error( $permission ) ) {
			$this->respond( $permission );
		}
	}

	/**
	 * Preserve the wizard envelope without exposing private paths or public URLs.
	 *
	 * @param array|\WP_Error $result Service result.
	 * @param bool            $preview Wrap rows for the legacy wizard.
	 */
	private function respond( $result, $preview = false ) {
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( $result->get_error_message(), $result->get_error_data()['status'] ?? 400 );
		}
		if ( $preview ) {
			$result['file_path'] = 'rsl-ie-file:' . $result['file_id'];
			$result['columns']   = $result['headers'];
			$rows                = [];
			foreach ( $result['preview'] as $row ) {
				$values  = [];
				$numeric = array_keys( $row ) === range( 0, count( $row ) - 1 );
				foreach ( $result['headers'] as $index => $column ) {
					$value    = $row[ $numeric ? $index : $column ] ?? '';
					$values[] = is_array( $value ) || is_object( $value ) ? wp_json_encode( $value ) : $value;
				}
				$rows[] = $values;
			}
			$result['preview'] = [
				'headers' => $result['headers'],
				'data'    => $rows,
			];
		}
		wp_send_json_success( $result );
	}

	/** Cleanup abandoned uploads and unreferenced old files. */
	public function cleanup_old_chunks() {
		( new File_Service() )->cleanup();
	}

	/** Existing content discovery; provider migration is tracked separately. */
	public function handle_get_custom_post_types() {
		// Verify nonce.
		check_ajax_referer( Ajax_Security::nonce_action( 'rsl_ie_get_custom_post_types' ), 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'import-export-by-rockstarlab' ), 403 );
		}

		// Get all public custom post types.
		$args = array(
			'public'   => true,
			'_builtin' => false,
		);

		$post_types = get_post_types( $args, 'objects' );
		$result     = array();

		foreach ( $post_types as $post_type ) {
			$result[] = array(
				'name'  => $post_type->name,
				'label' => $post_type->label,
			);
		}

		wp_send_json_success( $result );
	}
}

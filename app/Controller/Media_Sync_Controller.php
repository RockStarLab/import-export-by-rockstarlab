<?php
/**
 * AJAX adapters for the shared folder media service.
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Service\Media_Folder_Service;
defined( 'ABSPATH' ) || exit;
/** Preserve the existing UI actions while workers run independently of cookies. */
class Media_Sync_Controller extends Base_Controller {
	/**
	 * Registered AJAX handlers.
	 *
	 * @return array
	 */
	protected function get_ajax_actions() {
		return [
			'scan_folder'              => [ 'callback' => 'scan_folder' ],
			'start_media_sync'         => [ 'callback' => 'start_media_sync' ],
			'get_sync_progress'        => [ 'callback' => 'get_sync_progress' ],
			'pause_media_sync'         => [ 'callback' => 'pause_media_sync' ],
			'resume_media_sync'        => [ 'callback' => 'resume_media_sync' ],
			'cancel_media_sync'        => [ 'callback' => 'cancel_media_sync' ],
			'browse_folders'           => [ 'callback' => 'browse_folders' ],
			'process_media_sync_batch' => [ 'callback' => 'process_media_sync_batch' ],
		];
	}

	/** Execute the authorized scan folder operation. */
	public function scan_folder() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification ); }
		$input   = [
			'folder_path' => $this->get_request_param( 'folder_path', '' ),
			'options'     => $this->get_request_array( 'options' ),
		];
		$service = new Media_Folder_Service();
		$result  = $service->scan( $input, false );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result ); }
		$this->send_success( $result );
	}

	/** Execute the authorized browse folders operation. */
	public function browse_folders() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification ); }
		$input   = [ 'path' => $this->get_request_param( 'path', '' ) ];
		$service = new Media_Folder_Service();
		$result  = $service->browse( $input, false );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result ); }
		$this->send_success( $result );
	}

	/** Execute the authorized start media sync operation. */
	public function start_media_sync() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification ); }
		$input   = [
			'folder_path'    => $this->get_request_param( 'folder_path', '' ),
			'selected_files' => $this->get_request_array( 'selected_files' ),
			'scan_options'   => $this->get_request_array( 'scan_options' ),
			'sync_options'   => $this->get_request_array( 'sync_options' ),
			'request_id'     => $this->get_request_param( 'request_id', '' ),
		];
		$service = new Media_Folder_Service();
		$result  = $service->start( $input );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result ); }
		$this->send_success( $result );
	}

	/** Execute the authorized get sync progress operation. */
	public function get_sync_progress() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification ); }
		$id      = (int) $this->get_request_param( 'job_id' );
		$service = new Media_Folder_Service();
		$result  = $service->status( $id );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result ); }
		$this->send_success( $result );
	}

	/** Execute the authorized process media sync batch operation. */
	public function process_media_sync_batch() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification ); }
		$id      = (int) $this->get_request_param( 'job_id' );
		$service = new Media_Folder_Service();
		$result  = $service->process_batch( $id );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result ); }
		$this->send_success( $result );
	}

	/** Execute the authorized pause media sync operation. */
	public function pause_media_sync() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification ); }
		$id      = (int) $this->get_request_param( 'job_id' );
		$service = new Media_Folder_Service();
		$result  = $service->transition( $id, 'pause' );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result ); }
		$this->send_success( $result );
	}

	/** Execute the authorized resume media sync operation. */
	public function resume_media_sync() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification ); }
		$id      = (int) $this->get_request_param( 'job_id' );
		$service = new Media_Folder_Service();
		$result  = $service->transition( $id, 'resume' );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result ); }
		$this->send_success( $result );
	}

	/** Execute the authorized cancel media sync operation. */
	public function cancel_media_sync() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification ); }
		$id      = (int) $this->get_request_param( 'job_id' );
		$service = new Media_Folder_Service();
		$result  = $service->transition( $id, 'cancel' );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result ); }
		$this->send_success( $result );
	}
}

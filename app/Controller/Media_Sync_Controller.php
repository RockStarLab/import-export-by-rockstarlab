<?php
/**
 * Media Sync Controller (AJAX endpoints skeleton)
 *
 * @package WP_AIE\Controller
 */

namespace WP_AIE\Controller;

use WP_AIE\Helper\Media_Sync;
use WP_AIE\Model\Job;
use WP_AIE\Model\Queue\Media_Sync_Processor;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Media_Sync_Controller extends Base_Controller {

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

	public function scan_folder() {
		$verification = $this->verify_request( 'aie_scan_folder' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification );
		}

		$this->validate_required_params( [ 'folder_path' ] );

		$folder_path = $this->get_request_param( 'folder_path' );
		$options     = $this->get_request_array( 'options' );

		// Convert path to absolute (supports absolute paths and relative-to-uploads)
		$upload_dir = wp_upload_dir();
		$base_dir   = $upload_dir['basedir'];

		if ( empty( $folder_path ) ) {
			// Root uploads directory
			$absolute_path = $base_dir;
		} elseif ( '/' === substr( $folder_path, 0, 1 ) ) {
			// Already an absolute path
			$absolute_path = $folder_path;
		} else {
			// Relative to uploads directory
			$absolute_path = $base_dir . '/' . trim( $folder_path, '/' );
		}

		// Security check: ensure path is within WordPress installation
		$real_path  = realpath( $absolute_path );
		$real_limit = realpath( ABSPATH );

		if ( false === $real_path || false === $real_limit || 0 !== strpos( $real_path . '/', $real_limit . '/' ) ) {
			$this->send_error(
				new \WP_Error(
					'invalid_path',
					__( 'Invalid folder path. Path must be within the WordPress directory.', 'wp-advanced-import-export' )
				)
			);
		}

		$result = Media_Sync::scan_folder( $absolute_path, $options );

		if ( is_wp_error( $result ) ) {
			$this->send_error( $result );
		}

		$this->send_success( [ 'files' => $result ] );
	}

	public function start_media_sync() {
		$verification = $this->verify_request( 'aie_start_media_sync' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification );
		}

		$this->validate_required_params( [ 'folder_path' ] );

		$folder_path    = $this->get_request_param( 'folder_path' );
		$selected_files = $this->get_request_array( 'selected_files' ); // Get selected files
		$scan_options   = $this->get_request_array( 'scan_options' );
		$sync_options   = $this->get_request_array( 'sync_options' );

		// Validate folder path (supports absolute paths and relative-to-uploads)
		$upload_dir = wp_upload_dir();
		$base_dir   = $upload_dir['basedir'];

		if ( empty( $folder_path ) ) {
			$absolute_path = $base_dir;
		} elseif ( '/' === substr( $folder_path, 0, 1 ) ) {
			// Already an absolute path
			$absolute_path = $folder_path;
		} else {
			// Relative to uploads directory
			$absolute_path = $base_dir . '/' . trim( $folder_path, '/' );
		}

		$real_path  = realpath( $absolute_path );
		$real_limit = realpath( ABSPATH );

		if ( false === $real_path || false === $real_limit || 0 !== strpos( $real_path . '/', $real_limit . '/' ) ) {
			$this->send_error(
				new \WP_Error(
					'invalid_path',
					__( 'Invalid folder path', 'wp-advanced-import-export' )
				)
			);
		}

		// Create job record with folder path, selected files and options
		$job_model = WP_AIE()->Model->job;
		$job_data  = [
			'type'     => 'media_sync',
			'status'   => 'pending',
			'user_id'  => $this->get_current_user_id(),
			'settings' => wp_json_encode(
				[
					'folder_path'  => $absolute_path,
					'all_files'    => $selected_files, // Store selected files
					'total_files'  => count( $selected_files ), // Store total count
					'scan_options' => $scan_options,
					'sync_options' => $sync_options,
					'offset'       => 0,
				]
			),
		];

		$job_id = $job_model->create( $job_data );
		if ( is_wp_error( $job_id ) ) {
			$this->send_error( $job_id );
		}

		// Update job to processing status with total items
		$job_model->update(
			$job_id,
			[
				'status'      => 'processing',
				'total_items' => count( $selected_files ),
			]
		);

		// Return job info immediately so UI can open progress dialog
		// JS will trigger the first processing request
		$response_data = [
			'job_id'      => $job_id,
			'folder_path' => $folder_path,
			'progress'    => 0,
			'status'      => 'processing',
			'result'      => null,
		];

		$this->send_success(
			$response_data,
			__( 'Media sync job started', 'wp-advanced-import-export' )
		);
	}

	public function get_sync_progress() {
		$verification = $this->verify_request( 'aie_get_sync_progress' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification );
		}

		$this->validate_required_params( [ 'job_id' ] );
		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = WP_AIE()->Model->job;
		$data      = $job_model->find( $job_id );

		if ( ! $data ) {
			$this->send_error( __( 'Job not found', 'wp-advanced-import-export' ) );
		}

		// Get result, handle if column doesn't exist or is null
		$result = isset( $data->result ) ? $data->result : null;

		$this->send_success(
			[
				'status'   => $data->status,
				'progress' => $data->progress,
				'result'   => $result ? json_decode( $result, true ) : null,
			]
		);
	}

	public function pause_media_sync() {
		$verification = $this->verify_request( 'aie_pause_media_sync' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification );
		}

		$this->validate_required_params( [ 'job_id' ] );
		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = WP_AIE()->Model->job;
		$job_model->update( $job_id, [ 'status' => 'paused' ] );

		$this->send_success();
	}

	public function resume_media_sync() {
		$verification = $this->verify_request( 'aie_resume_media_sync' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification );
		}

		$this->validate_required_params( [ 'job_id' ] );
		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = WP_AIE()->Model->job;
		$job_model->update( $job_id, [ 'status' => 'processing' ] );

		$this->send_success();
	}

	public function cancel_media_sync() {
		$verification = $this->verify_request( 'aie_cancel_media_sync' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification );
		}

		$this->validate_required_params( [ 'job_id' ] );
		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = WP_AIE()->Model->job;

		// Update status to cancelled
		$job_model->update( $job_id, [ 'status' => 'cancelled' ] );

		// Clear any scheduled cron events for this job
		$timestamp = wp_next_scheduled( 'aie_process_media_sync_job', array( $job_id ) );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'aie_process_media_sync_job', array( $job_id ) );
		}

		// Optionally delete the job record to clean up
		// Uncomment if you want to remove cancelled jobs completely:
		// $job_model->delete( $job_id );

		$this->send_success();
	}

	/**
	 * Process media sync batch (called via AJAX for async processing)
	 */
	public function process_media_sync_batch() {
		$verification = $this->verify_request( 'aie_process_media_sync_batch' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification );
		}

		$this->validate_required_params( [ 'job_id' ] );
		$job_id = (int) $this->get_request_param( 'job_id' );

		// Process the job
		$processor = new \WP_AIE\Model\Queue\Media_Sync_Processor();
		$result    = $processor->process( $job_id );

		$this->send_success( $result );
	}

	/**
	 * Browse folders in uploads directory
	 *
	 * @return void
	 */
	public function browse_folders() {
		try {
			// Manual verification using the general nonce
			$nonce = $this->get_request_param( 'nonce', '' );

			if ( ! wp_verify_nonce( $nonce, 'aie_nonce' ) ) {
				$this->send_error(
					new \WP_Error( 'invalid_nonce', __( 'Security check failed', 'wp-advanced-import-export' ) )
				);
			}

			// Check capability
			if ( ! current_user_can( $this->required_capability ) ) {
				$this->send_error(
					new \WP_Error( 'insufficient_permissions', __( 'You do not have permission to perform this action', 'wp-advanced-import-export' ) )
				);
			}

			$path = $this->get_request_param( 'path', '' );

			// Get uploads directory
			$upload_dir = wp_upload_dir();
			$base_dir   = $upload_dir['basedir'];

			// Determine absolute path: supports absolute paths and relative-to-uploads
			if ( empty( $path ) ) {
				$absolute_path = $base_dir;
			} elseif ( '/' === substr( $path, 0, 1 ) ) {
				// Already an absolute path
				$absolute_path = $path;
			} else {
				// Relative to uploads (backward compatibility)
				$absolute_path = $base_dir . '/' . trim( $path, '/' );
			}

			// Security check: must be within WordPress installation
			$real_path  = realpath( $absolute_path );
			$real_limit = realpath( ABSPATH );

			if ( false === $real_path ) {
				$this->send_error(
					new \WP_Error(
						'directory_not_found',
						sprintf(
						/* translators: %s: directory path */
							__( 'Directory not found: %s', 'wp-advanced-import-export' ),
							$absolute_path
						)
					)
				);
			}

			if ( false === $real_limit || 0 !== strpos( $real_path . '/', $real_limit . '/' ) ) {
				$this->send_error(
					new \WP_Error(
						'invalid_path',
						__( 'Invalid path. Must be within the WordPress directory.', 'wp-advanced-import-export' )
					)
				);
			}

			// Check if directory exists
			if ( ! is_dir( $real_path ) ) {
				$this->send_error(
					new \WP_Error(
						'not_directory',
						__( 'Path is not a directory.', 'wp-advanced-import-export' )
					)
				);
			}

			// Get subdirectories
			$folders = [];
			try {
				$items = scandir( $real_path );
				if ( false === $items ) {
					throw new \Exception( 'Unable to read directory' );
				}

				foreach ( $items as $item ) {
					if ( '.' === $item || '..' === $item ) {
						continue;
					}

					$item_path = $real_path . '/' . $item;
					if ( is_dir( $item_path ) ) {
						$folders[] = [
							'name' => $item,
							'path' => $item_path, // Absolute path
						];
					}
				}

				// Sort folders alphabetically
				usort(
					$folders,
					function ( $a, $b ) {
						return strcasecmp( $a['name'], $b['name'] );
					}
				);

			} catch ( \Exception $e ) {
				$this->send_error(
					new \WP_Error(
						'read_error',
						$e->getMessage()
					)
				);
			}

			// Determine whether the user can navigate up within the WordPress directory
			$parent_path = dirname( $real_path );
			$can_go_up   = ( $real_path !== $real_limit )
				&& ( 0 === strpos( $parent_path . '/', $real_limit . '/' ) );

			$this->send_success(
				[
					'folders'      => $folders,
					'current_path' => $real_path,  // Absolute path
					'can_go_up'    => $can_go_up,
					'parent_path'  => $can_go_up ? $parent_path : null,
				]
			);

		} catch ( \Exception $e ) {
			$this->send_error(
				new \WP_Error(
					'fatal_error',
					'Internal error: ' . $e->getMessage()
				)
			);
		}
	}
}

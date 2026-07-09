<?php
/**
 * Media Sync Controller (AJAX endpoints skeleton)
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Helper\Media_Sync;
use RockStarLab\ImportExport\Model\Job;
use RockStarLab\ImportExport\Model\Queue\Media_Sync_Processor;

defined( 'ABSPATH' ) or exit;

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
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification );
		}

		$this->validate_required_params( [ 'folder_path' ] );

		$folder_path = $this->get_request_param( 'folder_path' );
		$options     = $this->get_request_array( 'options' );

		// Convert path to absolute (supports absolute paths and relative-to-uploads).
		$upload_dir = wp_upload_dir();
		$base_dir   = $upload_dir['basedir'];
		$path_check = $this->resolve_uploads_directory_path( $folder_path, $base_dir );
		if ( is_wp_error( $path_check ) ) {
			$this->send_error( $path_check );
		}

		$absolute_path = $path_check;

		if ( ! is_dir( $absolute_path ) ) {
			$this->send_error(
				new \WP_Error(
					'invalid_path',
					__( 'Invalid folder path. Path must be a directory within the WordPress uploads directory.', 'import-export-by-rockstarlab' )
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
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification );
		}

		$this->validate_required_params( [ 'folder_path' ] );

		$folder_path    = $this->get_request_param( 'folder_path' );
		$selected_files = $this->get_request_array( 'selected_files' ); // Get selected files
		$scan_options   = $this->get_request_array( 'scan_options' );
		$sync_options   = $this->get_request_array( 'sync_options' );

		// Validate folder path (supports absolute paths and relative-to-uploads).
		$upload_dir = wp_upload_dir();
		$base_dir   = $upload_dir['basedir'];
		$path_check = $this->resolve_uploads_directory_path( $folder_path, $base_dir );
		if ( is_wp_error( $path_check ) ) {
			$this->send_error( $path_check );
		}

		$absolute_path = $path_check;

		if ( ! is_dir( $absolute_path ) ) {
			$this->send_error(
				new \WP_Error(
					'invalid_path',
					__( 'Invalid folder path. Path must be a directory within the WordPress uploads directory.', 'import-export-by-rockstarlab' )
				)
			);
		}

		$validated_files = [];
		foreach ( $selected_files as $selected_file ) {
			$selected_path  = is_array( $selected_file ) ? ( $selected_file['path'] ?? '' ) : $selected_file;
			$validated_path = Media_Sync::validate_source_file( $selected_path, $absolute_path );

			if ( is_wp_error( $validated_path ) ) {
				$this->send_error( $validated_path, null, 400 );
			}

			if ( is_array( $selected_file ) ) {
				$selected_file['path'] = $validated_path;
				$validated_files[]     = $selected_file;
			} else {
				$validated_files[] = $validated_path;
			}
		}
		$selected_files = $validated_files;

		// Create job record with folder path, selected files and options
		$job_model = rsl_ie()->Model->job;
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
			__( 'Media sync job started', 'import-export-by-rockstarlab' )
		);
	}

	public function get_sync_progress() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification );
		}

		$this->validate_required_params( [ 'job_id' ] );
		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = rsl_ie()->Model->job;
		$data      = $job_model->find( $job_id );

		if ( ! $data ) {
			$this->send_error( __( 'Job not found', 'import-export-by-rockstarlab' ) );
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
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification );
		}

		$this->validate_required_params( [ 'job_id' ] );
		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = rsl_ie()->Model->job;
		$job_model->update( $job_id, [ 'status' => 'paused' ] );

		$this->send_success();
	}

	public function resume_media_sync() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification );
		}

		$this->validate_required_params( [ 'job_id' ] );
		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = rsl_ie()->Model->job;
		$job_model->update( $job_id, [ 'status' => 'processing' ] );

		$this->send_success();
	}

	public function cancel_media_sync() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification );
		}

		$this->validate_required_params( [ 'job_id' ] );
		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = rsl_ie()->Model->job;

		// Update status to cancelled
		$job_model->update( $job_id, [ 'status' => 'cancelled' ] );

		// Clear any scheduled cron events for this job
		$timestamp = wp_next_scheduled( 'rsl_ie_process_media_sync_job', array( $job_id ) );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'rsl_ie_process_media_sync_job', array( $job_id ) );
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
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification );
		}

		$this->validate_required_params( [ 'job_id' ] );
		$job_id = (int) $this->get_request_param( 'job_id' );

		// Process the job
		$processor = new \RockStarLab\ImportExport\Model\Queue\Media_Sync_Processor();
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
			$verification = $this->verify_request();
			if ( is_wp_error( $verification ) ) {
				$this->send_error( $verification, null, 403 );
			}

			$path = $this->get_request_param( 'path', '' );

			// Get uploads directory
			$upload_dir = wp_upload_dir();
			$base_dir   = $upload_dir['basedir'];

			// Determine absolute path: supports absolute paths and relative-to-uploads.
			$absolute_path = $this->resolve_uploads_path( $path, $base_dir );
			$real_path     = realpath( $absolute_path );
			$real_limit    = realpath( $base_dir );

			if ( false === $real_path ) {
				$this->send_error(
					new \WP_Error(
						'directory_not_found',
						sprintf(
						/* translators: %s: directory path */
							__( 'Directory not found: %s', 'import-export-by-rockstarlab' ),
							$absolute_path
						)
					)
				);
			}

			if ( false === $real_limit ) {
				$this->send_error(
					new \WP_Error(
						'invalid_path',
						__( 'Invalid path. Must be within the WordPress uploads directory.', 'import-export-by-rockstarlab' )
					)
				);
			}

			$real_path_normalized  = trailingslashit( wp_normalize_path( $real_path ) );
			$real_limit_normalized = trailingslashit( wp_normalize_path( $real_limit ) );

			if ( 0 !== strpos( $real_path_normalized, $real_limit_normalized ) ) {
				$this->send_error(
					new \WP_Error(
						'invalid_path',
						__( 'Invalid path. Must be within the WordPress uploads directory.', 'import-export-by-rockstarlab' )
					)
				);
			}

			// Check if directory exists
			if ( ! is_dir( $real_path ) ) {
				$this->send_error(
					new \WP_Error(
						'not_directory',
						__( 'Path is not a directory.', 'import-export-by-rockstarlab' )
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

			// Determine whether the user can navigate up within the uploads directory.
			$parent_path = dirname( $real_path );
			$can_go_up   = ( wp_normalize_path( $real_path ) !== wp_normalize_path( $real_limit ) )
				&& ( 0 === strpos( trailingslashit( wp_normalize_path( $parent_path ) ), $real_limit_normalized ) );

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

	/**
	 * Resolve a requested folder path against the WordPress uploads directory.
	 *
	 * @param string $path     Requested path; absolute or relative to uploads.
	 * @param string $base_dir Uploads base directory.
	 * @return string Normalized absolute path.
	 */
	private function resolve_uploads_path( $path, $base_dir ) {
		$base_dir = wp_normalize_path( untrailingslashit( $base_dir ) );
		$path     = is_string( $path ) ? trim( $path ) : '';

		if ( '' === $path ) {
			return $base_dir;
		}

		if ( path_is_absolute( $path ) ) {
			return wp_normalize_path( $path );
		}

		return wp_normalize_path( $base_dir . '/' . ltrim( $path, '/\\' ) );
	}

	/**
	 * Resolve and verify a requested directory path against uploads.
	 *
	 * @param string $path     Requested path; absolute or relative to uploads.
	 * @param string $base_dir Uploads base directory.
	 * @return string|\WP_Error Real absolute path or validation error.
	 */
	private function resolve_uploads_directory_path( $path, $base_dir ) {
		$absolute_path = $this->resolve_uploads_path( $path, $base_dir );
		$real_path     = realpath( $absolute_path );
		$real_limit    = realpath( $base_dir );

		if ( false === $real_path || false === $real_limit ) {
			return new \WP_Error(
				'invalid_path',
				__( 'Invalid folder path. Path must be a directory within the WordPress uploads directory.', 'import-export-by-rockstarlab' )
			);
		}

		$real_path_normalized  = trailingslashit( wp_normalize_path( $real_path ) );
		$real_limit_normalized = trailingslashit( wp_normalize_path( $real_limit ) );

		if ( 0 !== strpos( $real_path_normalized, $real_limit_normalized ) ) {
			return new \WP_Error(
				'invalid_path',
				__( 'Invalid folder path. Path must be within the WordPress uploads directory.', 'import-export-by-rockstarlab' )
			);
		}

		return wp_normalize_path( $real_path );
	}
}

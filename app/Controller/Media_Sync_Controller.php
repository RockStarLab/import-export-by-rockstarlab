<?php
/**
 * Media Sync Controller (AJAX endpoints skeleton)
 *
 * @package WP_AIE\Controller
 */

namespace WP_AIE\Controller;

use WP_AIE\Helper\Media_Sync;
use WP_AIE\Model\Job;

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Media_Sync_Controller extends Base_Controller {

	protected function get_ajax_actions() {
		return [
			'scan_folder'       => [ 'callback' => 'scan_folder' ],
			'start_media_sync'  => [ 'callback' => 'start_media_sync' ],
			'get_sync_progress' => [ 'callback' => 'get_sync_progress' ],
			'pause_media_sync'  => [ 'callback' => 'pause_media_sync' ],
			'cancel_media_sync' => [ 'callback' => 'cancel_media_sync' ],
			'browse_folders'    => [ 'callback' => 'browse_folders' ],
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

		// Convert relative path to absolute (relative to uploads directory)
		$upload_dir = wp_upload_dir();
		$base_dir   = $upload_dir['basedir'];

		// Remove leading/trailing slashes
		$folder_path = trim( $folder_path, '/' );

		// Build absolute path
		if ( empty( $folder_path ) ) {
			// Root uploads directory
			$absolute_path = $base_dir;
		} else {
			$absolute_path = $base_dir . '/' . $folder_path;
		}

		// Security check: ensure path is within uploads directory
		$real_path = realpath( $absolute_path );
		$real_base = realpath( $base_dir );

		if ( false === $real_path || false === strpos( $real_path, $real_base ) ) {
			$this->send_error(
				new \WP_Error(
					'invalid_path',
					__( 'Invalid folder path. Path must be within uploads directory.', 'wp-advanced-import-export' )
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

		$this->validate_required_params( [ 'files' ] );

		$files   = $this->get_request_array( 'files' );
		$options = $this->get_request_array( 'options' );

		// Extract file paths from file objects
		$file_paths = array_map(
			function ( $file ) {
				return $file['path'] ?? '';
			},
			$files
		);
		$file_paths = array_filter( $file_paths ); // Remove empty paths

		if ( empty( $file_paths ) ) {
			$this->send_error(
				new \WP_Error(
					'no_files',
					__( 'No valid files provided', 'wp-advanced-import-export' )
				)
			);
		}

		// Create job record.
		$job      = new Job();
		$job_data = [
			'type'       => 'media_sync',
			'status'     => 'pending',
			'user_id'    => $this->get_current_user_id(),
			'parameters' => wp_json_encode(
				[
					'files'   => $file_paths,
					'options' => $options,
					'offset'  => 0,
				]
			),
		];

		$job_id = $job->create( $job_data );
		if ( is_wp_error( $job_id ) ) {
			$this->send_error( $job_id );
		}

		// Schedule job to be processed in background queue
		if ( ! wp_next_scheduled( 'aie_process_queue' ) ) {
			wp_schedule_single_event( time(), 'aie_process_queue' );
		}

		$this->send_success(
			[
				'job_id'      => $job_id,
				'total_files' => count( $file_paths ),
			],
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

		$job  = new Job();
		$data = $job->read( $job_id );

		if ( ! $data ) {
			$this->send_error( __( 'Job not found', 'wp-advanced-import-export' ) );
		}

		$this->send_success(
			[
				'status'   => $data->status,
				'progress' => $data->progress,
				'result'   => $data->result ? json_decode( $data->result, true ) : null,
			]
		);
	}

	public function pause_media_sync() {
		$verification = $this->verify_request( 'aie_pause_media_sync' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification );
		}
		$this->send_success();
	}

	public function cancel_media_sync() {
		$verification = $this->verify_request( 'aie_cancel_media_sync' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification );
		}

		$this->validate_required_params( [ 'job_id' ] );
		$job_id = (int) $this->get_request_param( 'job_id' );

		$job = new Job();
		$job->update( $job_id, [ 'status' => 'cancelled' ] );

		$this->send_success();
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

			$relative_path = $this->get_request_param( 'path', '' );

			// Get uploads directory
			$upload_dir = wp_upload_dir();
			$base_dir   = $upload_dir['basedir'];

			// Build absolute path
			$relative_path = trim( $relative_path, '/' );
			if ( empty( $relative_path ) ) {
				$absolute_path = $base_dir;
			} else {
				$absolute_path = $base_dir . '/' . $relative_path;
			}

			// Security check: ensure path is within uploads directory
			$real_path = realpath( $absolute_path );
			$real_base = realpath( $base_dir );

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

			if ( false === strpos( $real_path, $real_base ) ) {
				$this->send_error(
					new \WP_Error(
						'invalid_path',
						__( 'Invalid path. Must be within uploads directory.', 'wp-advanced-import-export' )
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
						// Calculate relative path from base uploads dir
						$item_relative = str_replace( $real_base . '/', '', $item_path );

						$folders[] = [
							'name' => $item,
							'path' => $item_relative,
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

			$this->send_success(
				[
					'folders'      => $folders,
					'current_path' => $relative_path,
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

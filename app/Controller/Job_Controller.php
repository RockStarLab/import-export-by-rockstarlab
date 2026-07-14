<?php
/**
 * Job Controller
 *
 * Handles job management operations via AJAX
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Model\Job;
use RockStarLab\ImportExport\Model\Log;
use RockStarLab\ImportExport\Helper\Ajax_Security;

defined( 'ABSPATH' ) or exit;

class Job_Controller extends Base_Controller {
	/**
	 * Format duration in human-readable format
	 *
	 * @param int $seconds Duration in seconds
	 * @return string
	 */
	private function format_duration( $seconds ) {
		$seconds = (int) max( 0, $seconds );

		if ( $seconds < 60 ) {
			return $seconds . 's';
		}

		if ( $seconds < 3600 ) {
			$minutes = (int) floor( $seconds / 60 );
			$secs    = (int) ( $seconds % 60 );
			return sprintf( '%dm %ds', $minutes, $secs );
		}

		$hours   = (int) floor( $seconds / 3600 );
		$minutes = (int) floor( ( $seconds % 3600 ) / 60 );
		return sprintf( '%dh %dm', $hours, $minutes );
	}

	/**
	 * Normalize job parameters for rerun.
	 *
	 * Some job types mutate their parameters during execution (e.g. import stores
	 * prepared_data/offset). When re-running from Jobs Log we want to preserve
	 * the user configuration but reset runtime state so processing starts over.
	 *
	 * @param string $job_type
	 * @param mixed  $raw_parameters
	 * @return mixed
	 */
	private function normalize_rerun_parameters( $job_type, $raw_parameters ) {
		if ( 'import' !== $job_type ) {
			return $raw_parameters;
		}

		if ( empty( $raw_parameters ) || ! is_string( $raw_parameters ) ) {
			return $raw_parameters;
		}

		$params = json_decode( $raw_parameters, true );
		if ( ! is_array( $params ) ) {
			return $raw_parameters;
		}

		// Strip runtime state.
		unset( $params['prepared_data'] );
		unset( $params['total_items'] );
		unset( $params['cumulative_result'] );
		unset( $params['offset'] );

		// Ensure fresh start.
		$params['offset'] = 0;

		return wp_json_encode( $params );
	}

	/**
	 * Get AJAX actions
	 *
	 * @return array
	 */
	protected function get_ajax_actions() {
		return [
			'job_list'         => [ 'callback' => 'list_jobs' ],
			'job_get'          => [ 'callback' => 'get_job' ],
			'job_delete'       => [ 'callback' => 'delete_job' ],
			'job_get_logs'     => [ 'callback' => 'get_logs' ],
			'job_cleanup'      => [ 'callback' => 'cleanup_old_jobs' ],
			'job_resume'       => [ 'callback' => 'resume_job' ],
			'job_restart'      => [ 'callback' => 'restart_job' ],
			'job_retry'        => [ 'callback' => 'retry_job' ],
			'job_download_url' => [ 'callback' => 'get_download_url' ],
		];
	}

	/**
	 * List jobs
	 */
	public function list_jobs() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$type   = $this->get_request_param( 'type', '' );
		$status = $this->get_request_param( 'status', '' );
		$limit  = (int) $this->get_request_param( 'limit', 20 );
		$offset = (int) $this->get_request_param( 'offset', 0 );

		$job_model = rsl_ie()->Model->job;
		$where     = [];

		if ( ! empty( $type ) ) {
			$where['type'] = $type;
		}

		if ( ! empty( $status ) ) {
			$where['status'] = $status;
		}

		// Get jobs using get_all method
		$jobs = $job_model->get_all( $where, $limit, $offset, 'created_at DESC' );

		// Add action flags to each job
		foreach ( $jobs as &$job ) {
			// Can resume: paused or failed jobs
			$job->can_resume = in_array( $job->status, [ 'paused', 'failed' ], true );

			// Can delete: any job
			$job->can_delete = true;

			// Can retry: any job
			$job->can_retry = true;

			// Aliases for JS layer (DB uses imported_items / error_items)
			$job->success_items = (int) ( $job->imported_items ?? 0 );
			$job->failed_items  = (int) ( $job->error_items ?? 0 );

			// Provide real elapsed time for Jobs Log table.
			// Prefer started_at/completed_at; fall back to created_at for jobs that
			// never set started_at (e.g. restarted jobs that haven't started yet).
			$job->elapsed_time = '-';

			$start_ts = 0;
			if ( ! empty( $job->started_at ) ) {
				$start_ts = (int) strtotime( $job->started_at );
			} elseif ( ! empty( $job->created_at ) && ! in_array( $job->status, [ 'pending' ], true ) ) {
				$start_ts = (int) strtotime( $job->created_at );
			}

			if ( $start_ts > 0 ) {
				$end_ts = 0;
				if ( ! empty( $job->completed_at ) && in_array( $job->status, [ 'completed', 'failed', 'cancelled' ], true ) ) {
					$end_ts = (int) strtotime( $job->completed_at );
				} else {
					$end_ts = (int) current_time( 'timestamp' );
				}

				if ( $end_ts > 0 && $end_ts >= $start_ts ) {
					$job->elapsed_time = $this->format_duration( $end_ts - $start_ts );
				}
			}
		}
		unset( $job );

		// Get total count
		$total = $job_model->count( $where );

		$this->send_success(
			[
				'jobs'   => $jobs,
				'total'  => $total,
				'limit'  => $limit,
				'offset' => $offset,
			]
		);
	}

	/**
	 * Get single job
	 */
	public function get_job() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = rsl_ie()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data ) {
			$this->send_error( __( 'Job not found', 'import-export-by-rockstarlab' ), null, 404 );
		}

		// Parse parameters and result
		$job_data->parameters = json_decode( $job_data->parameters, true );
		$job_data->result     = json_decode( $job_data->result, true );

		// Aliases for JS layer (DB uses imported_items / error_items)
		$job_data->success_items = (int) ( $job_data->imported_items ?? 0 );
		$job_data->failed_items  = (int) ( $job_data->error_items ?? 0 );

		$this->send_success( $job_data );
	}

	/**
	 * Delete job
	 */
	public function delete_job() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = rsl_ie()->Model->job;
		$result    = $job_model->delete( $job_id );

		if ( is_wp_error( $result ) ) {
			$this->send_error( $result, null, 500 );
		}

		$this->send_success( null, __( 'Job deleted successfully', 'import-export-by-rockstarlab' ) );
	}

	/**
	 * Get job logs
	 */
	public function get_logs() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );
		$level  = $this->get_request_param( 'level', 'all' ); // all, info, warning, error
		$limit  = (int) $this->get_request_param( 'limit', 100 );

		$log_model = rsl_ie()->Model->log;
		$where     = 'job_id = %d';
		$params    = [ $job_id ];

		if ( 'all' !== $level ) {
			$where   .= ' AND level = %s';
			$params[] = $level;
		}

		$logs = $log_model->find_all( $where, $params, $limit );

		$this->send_success(
			[
				'logs'  => $logs,
				'total' => count( $logs ),
			]
		);
	}

	/**
	 * Cleanup old jobs
	 */
	public function cleanup_old_jobs() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$days = (int) $this->get_request_param( 'days', 30 );

		if ( $days < 1 ) {
			$this->send_error( __( 'Days must be greater than 0', 'import-export-by-rockstarlab' ), null, 400 );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'rsl_ie_jobs';

		// Delete old completed jobs
		$deleted = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Cleaning expired rows from the plugin's custom jobs table.
			$wpdb->prepare(
				"DELETE FROM %i WHERE status IN ('completed', 'failed', 'cancelled') AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$table_name,
				$days
			)
		);

		$this->send_success(
			[
				'deleted' => $deleted,
			],
			sprintf(
			/* translators: %d: number of deleted jobs */
				__( 'Deleted %d old jobs', 'import-export-by-rockstarlab' ),
				$deleted
			)
		);
	}

	/**
	 * Resume job
	 */
	public function resume_job() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = rsl_ie()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data ) {
			$this->send_error( __( 'Job not found', 'import-export-by-rockstarlab' ), null, 404 );
		}

		// Check if job can be resumed
		if ( ! in_array( $job_data->status, [ 'paused', 'failed', 'processing' ], true ) ) {
			$this->send_error(
				sprintf(
					/* translators: %s: current job status */
					__( 'Job cannot be resumed. Current status: %s', 'import-export-by-rockstarlab' ),
					$job_data->status
				),
				null,
				400
			);
		}

		// Update job status
		$updated = $job_model->update(
			$job_id,
			[
				'status'     => 'processing',
				'updated_at' => current_time( 'mysql' ),
			]
		);

		if ( is_wp_error( $updated ) ) {
			$this->send_error( $updated, null, 500 );
		}

		// Parse parameters for frontend
		$parameters = maybe_unserialize( $job_data->parameters );

		$this->send_success(
			[
				'job_id'     => $job_id,
				'type'       => $job_data->type,
				'data_type'  => $job_data->data_type,
				'parameters' => $parameters,
			],
			__( 'Job resumed successfully', 'import-export-by-rockstarlab' )
		);
	}

	/**
	 * Restart job (create new job with same settings)
	 */
	public function restart_job() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = rsl_ie()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data ) {
			$this->send_error( __( 'Job not found', 'import-export-by-rockstarlab' ), null, 404 );
		}

		// Prepare settings - reset progress for media_sync jobs
		$settings_to_use = $job_data->settings;
		if ( 'media_sync' === $job_data->type && ! empty( $job_data->settings ) ) {
			$settings = json_decode( $job_data->settings, true );
			if ( is_array( $settings ) ) {
				// Reset progress tracking fields but keep the original configuration
				unset( $settings['offset'] );
				unset( $settings['processed_count'] );
				unset( $settings['total_files'] );
				unset( $settings['all_files'] );
				// Reset offset to 0 for fresh start
				$settings['offset'] = 0;
				$settings_to_use    = wp_json_encode( $settings );
			}
		}

		// Get job parameters for response
		$parameters = maybe_unserialize( $job_data->parameters );

		// For media_sync, parameters might be empty but settings should contain all info
		if ( empty( $parameters ) && 'media_sync' === $job_data->type ) {
			$parameters = json_decode( $job_data->settings, true );
		}

		if ( empty( $parameters ) && empty( $settings_to_use ) ) {
			$this->send_error( __( 'Job configuration not found', 'import-export-by-rockstarlab' ), null, 400 );
		}

		// Create new job with same settings
		$new_job_payload = [
			'user_id'     => $job_data->user_id,
			'type'        => $job_data->type,
			'data_type'   => $job_data->data_type,
			'file_format' => $job_data->file_format,
			'parameters'  => $this->normalize_rerun_parameters( $job_data->type, $job_data->parameters ),
			'settings'    => $settings_to_use,
		];

		// Import/update jobs depend on the source file path for processing. When
		// restarting from Jobs Log, keep the same file reference when available.
		if ( in_array( $job_data->type, [ 'import', 'update' ], true ) ) {
			$new_job_payload['file_path'] = $job_data->file_path ?? null;
			$new_job_payload['file_size'] = $job_data->file_size ?? null;
		}

		// Preserve total_items for update jobs so the Jobs Log shows the expected
		// denominator immediately after rerun.
		if ( 'update' === $job_data->type && ! empty( $job_data->total_items ) ) {
			$new_job_payload['total_items'] = (int) $job_data->total_items;
		}

		$new_job_id = $job_model->create( $new_job_payload );

		if ( is_wp_error( $new_job_id ) ) {
			$this->send_error( $new_job_id, null, 500 );
		}

		$this->send_success(
			[
				'job_id'     => $new_job_id,
				'type'       => $job_data->type,
				'data_type'  => $job_data->data_type,
				'parameters' => $parameters,
			],
			__( 'Job restarted successfully', 'import-export-by-rockstarlab' )
		);
	}

	/**
	 * Retry job (create new job and set to processing immediately)
	 */
	public function retry_job() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = rsl_ie()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data ) {
			$this->send_error( __( 'Job not found', 'import-export-by-rockstarlab' ), null, 404 );
		}

		// Prepare settings - reset progress for media_sync jobs
		$settings_to_use = $job_data->settings;
		if ( 'media_sync' === $job_data->type && ! empty( $job_data->settings ) ) {
			$settings = json_decode( $job_data->settings, true );
			if ( is_array( $settings ) ) {
				// Reset progress tracking fields but keep the original configuration
				unset( $settings['offset'] );
				unset( $settings['processed_count'] );
				unset( $settings['total_files'] );
				unset( $settings['all_files'] );
				// Reset offset to 0 for fresh start
				$settings['offset'] = 0;
				$settings_to_use    = wp_json_encode( $settings );
			}
		}

		// Get job parameters for response
		$parameters = maybe_unserialize( $job_data->parameters );

		// For media_sync, parameters might be empty but settings should contain all info
		if ( empty( $parameters ) && 'media_sync' === $job_data->type ) {
			$parameters = json_decode( $job_data->settings, true );
		}

		if ( empty( $parameters ) && empty( $settings_to_use ) ) {
			$this->send_error( __( 'Job configuration not found', 'import-export-by-rockstarlab' ), null, 400 );
		}

		// Create new job with same settings but set status to processing
		$new_job_payload = [
			'user_id'     => $job_data->user_id,
			'type'        => $job_data->type,
			'data_type'   => $job_data->data_type,
			'file_format' => $job_data->file_format,
			'parameters'  => $this->normalize_rerun_parameters( $job_data->type, $job_data->parameters ),
			'settings'    => $settings_to_use,
			'status'      => 'processing', // Set to processing immediately
		];

		// Import/update jobs depend on the source file path for processing. When
		// retrying from Jobs Log, keep the same file reference when available.
		if ( in_array( $job_data->type, [ 'import', 'update' ], true ) ) {
			$new_job_payload['file_path'] = $job_data->file_path ?? null;
			$new_job_payload['file_size'] = $job_data->file_size ?? null;
		}

		// Preserve total_items for update jobs so the Jobs Log shows the expected
		// denominator immediately after rerun.
		if ( 'update' === $job_data->type && ! empty( $job_data->total_items ) ) {
			$new_job_payload['total_items'] = (int) $job_data->total_items;
		}

		$new_job_id = $job_model->create( $new_job_payload );

		if ( is_wp_error( $new_job_id ) ) {
			$this->send_error( $new_job_id, null, 500 );
		}

		$this->send_success(
			[
				'job_id'     => $new_job_id,
				'type'       => $job_data->type,
				'data_type'  => $job_data->data_type,
				'parameters' => $parameters,
			],
			__( 'Job created and ready to process', 'import-export-by-rockstarlab' )
		);
	}

	/**
	 * Get download URL with nonce
	 */
	public function get_download_url() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$job_id = $this->get_request_param( 'job_id', 0 );

		if ( empty( $job_id ) ) {
			$this->send_error(
				new \WP_Error( 'missing_job_id', __( 'Job ID is required', 'import-export-by-rockstarlab' ) ),
				null,
				400
			);
		}

		// Verify job exists
		$job_model = rsl_ie()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data ) {
			$this->send_error(
				new \WP_Error( 'job_not_found', __( 'Job not found', 'import-export-by-rockstarlab' ) ),
				null,
				404
			);
		}

		// Generate nonce for this specific job
		$nonce = wp_create_nonce( 'rsl_ie_download_' . $job_id );
		$url   = add_query_arg(
			[
				'action'   => 'rsl_ie_secure_download',
				'job_id'   => $job_id,
				'_wpnonce' => $nonce,
				'nonce'    => Ajax_Security::create_nonce( 'rsl_ie_secure_download' ),
			],
			admin_url( 'admin-ajax.php' )
		);

		$this->send_success(
			[
				'url'   => $url,
				'nonce' => $nonce,
			],
			__( 'Download URL generated', 'import-export-by-rockstarlab' )
		);
	}
}

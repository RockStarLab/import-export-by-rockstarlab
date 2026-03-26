<?php
/**
 * Job Controller
 *
 * Handles job management operations via AJAX
 *
 * @package WP_AIE\Controller
 */

namespace WP_AIE\Controller;

use WP_AIE\Model\Job;
use WP_AIE\Model\Log;

defined( 'ABSPATH' ) or exit;

class Job_Controller extends Base_Controller {

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
		$verification = $this->verify_request( 'job_list' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$type   = $this->get_request_param( 'type', '' );
		$status = $this->get_request_param( 'status', '' );
		$limit  = (int) $this->get_request_param( 'limit', 20 );
		$offset = (int) $this->get_request_param( 'offset', 0 );

		$job_model = WP_AIE()->Model->job;
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
		}

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
		$verification = $this->verify_request( 'job_get' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = WP_AIE()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data ) {
			$this->send_error( __( 'Job not found', 'wp-advanced-import-export' ), null, 404 );
		}

		// Parse parameters and result
		$job_data->parameters = json_decode( $job_data->parameters, true );
		$job_data->result     = json_decode( $job_data->result, true );

		$this->send_success( $job_data );
	}

	/**
	 * Delete job
	 */
	public function delete_job() {
		$verification = $this->verify_request( 'job_delete' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = WP_AIE()->Model->job;
		$result    = $job_model->delete( $job_id );

		if ( is_wp_error( $result ) ) {
			$this->send_error( $result, null, 500 );
		}

		$this->send_success( null, __( 'Job deleted successfully', 'wp-advanced-import-export' ) );
	}

	/**
	 * Get job logs
	 */
	public function get_logs() {
		$verification = $this->verify_request( 'job_logs' );
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

		$log_model = WP_AIE()->Model->log;
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
		$verification = $this->verify_request( 'job_cleanup' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$days = (int) $this->get_request_param( 'days', 30 );

		if ( $days < 1 ) {
			$this->send_error( __( 'Days must be greater than 0', 'wp-advanced-import-export' ), null, 400 );
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'aie_jobs';

		// Delete old completed jobs
		$deleted = $wpdb->query( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE status IN ('completed', 'failed', 'cancelled') AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct DB query required here.
				$days
			)
		);

		$this->send_success(
			[
				'deleted' => $deleted,
			],
			sprintf(
			/* translators: %d: number of deleted jobs */
				__( 'Deleted %d old jobs', 'wp-advanced-import-export' ),
				$deleted
			)
		);
	}

	/**
	 * Resume job
	 */
	public function resume_job() {
		$verification = $this->verify_request( 'job_resume' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = WP_AIE()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data ) {
			$this->send_error( __( 'Job not found', 'wp-advanced-import-export' ), null, 404 );
		}

		// Check if job can be resumed
		if ( ! in_array( $job_data->status, [ 'paused', 'failed', 'processing' ], true ) ) {
			$this->send_error(
				sprintf(
					/* translators: %s: current job status */
					__( 'Job cannot be resumed. Current status: %s', 'wp-advanced-import-export' ),
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
			__( 'Job resumed successfully', 'wp-advanced-import-export' )
		);
	}

	/**
	 * Restart job (create new job with same settings)
	 */
	public function restart_job() {
		$verification = $this->verify_request( 'job_restart' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = WP_AIE()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data ) {
			$this->send_error( __( 'Job not found', 'wp-advanced-import-export' ), null, 404 );
		}

		// Verify premium license for premium content types.
		// Import jobs store the type in parameters, export jobs in data_type.
		$data_type = $job_data->data_type ?? '';
		if ( empty( $data_type ) ) {
			$params    = json_decode( $job_data->parameters, true );
			$data_type = $params['import_type'] ?? $params['export_type'] ?? '';
		}

		$license_check = $this->verify_premium_for_type( $data_type );
		if ( is_wp_error( $license_check ) ) {
			$this->send_error( $license_check, null, 403 );
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
			$this->send_error( __( 'Job configuration not found', 'wp-advanced-import-export' ), null, 400 );
		}

		// Create new job with same settings
		$new_job_id = $job_model->create(
			[
				'user_id'     => $job_data->user_id,
				'type'        => $job_data->type,
				'data_type'   => $job_data->data_type,
				'file_format' => $job_data->file_format,
				'parameters'  => $job_data->parameters,
				'settings'    => $settings_to_use,
			]
		);

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
			__( 'Job restarted successfully', 'wp-advanced-import-export' )
		);
	}

	/**
	 * Retry job (create new job and set to processing immediately)
	 */
	public function retry_job() {
		$verification = $this->verify_request( 'job_retry' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = WP_AIE()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data ) {
			$this->send_error( __( 'Job not found', 'wp-advanced-import-export' ), null, 404 );
		}

		// Verify premium license for premium content types.
		// Import jobs store the type in parameters, export jobs in data_type.
		$data_type = $job_data->data_type ?? '';
		if ( empty( $data_type ) ) {
			$params    = json_decode( $job_data->parameters, true );
			$data_type = $params['import_type'] ?? $params['export_type'] ?? '';
		}

		$license_check = $this->verify_premium_for_type( $data_type );
		if ( is_wp_error( $license_check ) ) {
			$this->send_error( $license_check, null, 403 );
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
			$this->send_error( __( 'Job configuration not found', 'wp-advanced-import-export' ), null, 400 );
		}

		// Create new job with same settings but set status to processing
		$new_job_id = $job_model->create(
			[
				'user_id'     => $job_data->user_id,
				'type'        => $job_data->type,
				'data_type'   => $job_data->data_type,
				'file_format' => $job_data->file_format,
				'parameters'  => $job_data->parameters,
				'settings'    => $settings_to_use,
				'status'      => 'processing', // Set to processing immediately
			]
		);

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
			__( 'Job created and ready to process', 'wp-advanced-import-export' )
		);
	}

	/**
	 * Get download URL with nonce
	 */
	public function get_download_url() {
		$verification = $this->verify_request( 'job_download_url' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$job_id = $this->get_request_param( 'job_id', 0 );

		if ( empty( $job_id ) ) {
			$this->send_error(
				new \WP_Error( 'missing_job_id', __( 'Job ID is required', 'wp-advanced-import-export' ) ),
				null,
				400
			);
		}

		// Verify job exists
		$job_model = WP_AIE()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data ) {
			$this->send_error(
				new \WP_Error( 'job_not_found', __( 'Job not found', 'wp-advanced-import-export' ) ),
				null,
				404
			);
		}

		// Generate nonce for this specific job
		$nonce = wp_create_nonce( 'aie_download_' . $job_id );
		$url   = add_query_arg(
			[
				'action'   => 'aie_secure_download',
				'job_id'   => $job_id,
				'_wpnonce' => $nonce,
			],
			admin_url( 'admin-ajax.php' )
		);

		$this->send_success(
			[
				'url'   => $url,
				'nonce' => $nonce,
			],
			__( 'Download URL generated', 'wp-advanced-import-export' )
		);
	}
}

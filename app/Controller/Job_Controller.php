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

/**
 * Job Controller Class
 *
 * Manages import/export jobs:
 * - List jobs
 * - Get job details
 * - Delete jobs
 * - Get job logs
 *
 * @package WP_AIE\Controller
 */
class Job_Controller extends Base_Controller {

	/**
	 * Get AJAX actions
	 *
	 * @return array
	 */
	protected function get_ajax_actions() {
		return [
			'job_list'     => [ 'callback' => 'list_jobs' ],
			'job_get'      => [ 'callback' => 'get_job' ],
			'job_delete'   => [ 'callback' => 'delete_job' ],
			'job_get_logs' => [ 'callback' => 'get_logs' ],
			'job_cleanup'  => [ 'callback' => 'cleanup_old_jobs' ],
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

		$type   = $this->get_request_param( 'type', 'all' ); // all, import, export
		$status = $this->get_request_param( 'status', 'all' ); // all, pending, processing, completed, failed, cancelled
		$limit  = (int) $this->get_request_param( 'limit', 20 );
		$offset = (int) $this->get_request_param( 'offset', 0 );

		$job    = new Job();
		$where  = [];
		$params = [];

		if ( 'all' !== $type ) {
			$where[]  = 'type = %s';
			$params[] = $type;
		}

		if ( 'all' !== $status ) {
			$where[]  = 'status = %s';
			$params[] = $status;
		}

		$jobs = $job->find_all( implode( ' AND ', $where ), $params, $limit, $offset );

		// Get total count
		$total = $job->count( implode( ' AND ', $where ), $params );

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

		$job      = new Job();
		$job_data = $job->read( $job_id );

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

		$job    = new Job();
		$result = $job->delete( $job_id );

		if ( is_wp_error( $result ) ) {
			$this->send_error( $result, null, 500 );
		}

		$this->log( 'delete_job', [ 'job_id' => $job_id ] );

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

		$log    = new Log();
		$where  = 'job_id = %d';
		$params = [ $job_id ];

		if ( 'all' !== $level ) {
			$where   .= ' AND level = %s';
			$params[] = $level;
		}

		$logs = $log->find_all( $where, $params, $limit );

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
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table_name} WHERE status IN ('completed', 'failed', 'cancelled') AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);

		$this->log(
			'cleanup_jobs',
			[
				'days'    => $days,
				'deleted' => $deleted,
			]
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
}

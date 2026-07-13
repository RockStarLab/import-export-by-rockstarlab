<?php
/**
 * Job Schedule Controller
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Helper\Ajax_Security;
use RockStarLab\ImportExport\Helper\Job_Scheduler;

defined( 'ABSPATH' ) || exit;

/**
 * Handles schedule create, update, and delete actions.
 */
class Schedule_Controller {

	/**
	 * Register admin actions.
	 *
	 * @return void
	 */
	public function init() {
		Ajax_Security::register_action( 'rsl_ie_schedule_search_source_jobs' );

		add_action( 'admin_post_rsl_ie_save_schedule', [ $this, 'save_schedule' ] );
		add_action( 'admin_post_rsl_ie_delete_schedule', [ $this, 'delete_schedule' ] );
		add_action( 'wp_ajax_rsl_ie_schedule_search_source_jobs', [ $this, 'search_source_jobs' ] );
	}

	/**
	 * Search schedulable source jobs for the Source Job Select2 field.
	 *
	 * @return void
	 */
	public function search_source_jobs() {
		global $wpdb;

		if ( ! check_ajax_referer( Ajax_Security::nonce_action( 'rsl_ie_schedule_search_source_jobs' ), 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => __( 'Security check failed', 'import-export-by-rockstarlab' ) ], 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( [ 'message' => __( 'You do not have permission to manage schedules.', 'import-export-by-rockstarlab' ) ], 403 );
		}

		$search_raw = filter_has_var( INPUT_GET, 'q' ) ? filter_input( INPUT_GET, 'q', FILTER_UNSAFE_RAW ) : '';
		if ( null === $search_raw || false === $search_raw ) {
			$search_raw = filter_has_var( INPUT_POST, 'q' ) ? filter_input( INPUT_POST, 'q', FILTER_UNSAFE_RAW ) : '';
		}

		$page_raw = filter_has_var( INPUT_GET, 'page' ) ? filter_input( INPUT_GET, 'page', FILTER_UNSAFE_RAW ) : 1;
		if ( null === $page_raw || false === $page_raw ) {
			$page_raw = filter_has_var( INPUT_POST, 'page' ) ? filter_input( INPUT_POST, 'page', FILTER_UNSAFE_RAW ) : 1;
		}

		$search   = sanitize_text_field( wp_unslash( (string) $search_raw ) );
		$page     = max( 1, absint( wp_unslash( (string) $page_raw ) ) );
		$per_page = 10;
		$offset   = ( $page - 1 ) * $per_page;
		$table    = $wpdb->prefix . 'rsl_ie_jobs';
		$like     = '%' . $wpdb->esc_like( $search ) . '%';
		$job_id   = absint( $search );

		if ( '' === $search ) {
			$jobs = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Select2 source search for plugin jobs table.
				$wpdb->prepare(
					"SELECT id, type, data_type, status, created_at
					FROM %i
					WHERE type IN ('import', 'export', 'media_sync', 'update')
						AND status NOT IN ('pending', 'processing')
					ORDER BY id DESC
					LIMIT %d OFFSET %d",
					$table,
					$per_page + 1,
					$offset
				)
			);
		} else {
			$jobs = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Select2 source search for plugin jobs table.
				$wpdb->prepare(
					"SELECT id, type, data_type, status, created_at
					FROM %i
					WHERE type IN ('import', 'export', 'media_sync', 'update')
						AND status NOT IN ('pending', 'processing')
						AND (
							id = %d
							OR type LIKE %s
							OR data_type LIKE %s
							OR status LIKE %s
						)
					ORDER BY id DESC
					LIMIT %d OFFSET %d",
					$table,
					$job_id,
					$like,
					$like,
					$like,
					$per_page + 1,
					$offset
				)
			);
		}

		$has_more = count( $jobs ) > $per_page;
		$jobs     = array_slice( $jobs, 0, $per_page );
		$results  = array_map(
			function ( $job ) {
				return [
					'id'   => (int) $job->id,
					'text' => $this->format_source_job_label( $job ),
				];
			},
			$jobs
		);

		wp_send_json(
			[
				'results'    => $results,
				'pagination' => [
					'more' => $has_more,
				],
			]
		);
	}

	/**
	 * Create or update a schedule.
	 *
	 * @return void
	 */
	public function save_schedule() {
		$this->verify_capability();
		check_admin_referer( 'rsl_ie_save_schedule' );

		$schedule_id   = isset( $_POST['schedule_id'] ) ? absint( wp_unslash( $_POST['schedule_id'] ) ) : 0;
		$source_job_id = isset( $_POST['source_job_id'] ) ? absint( wp_unslash( $_POST['source_job_id'] ) ) : 0;
		$name          = isset( $_POST['schedule_name'] ) ? sanitize_text_field( wp_unslash( $_POST['schedule_name'] ) ) : '';
		$schedule_type = isset( $_POST['schedule_type'] ) ? sanitize_key( wp_unslash( $_POST['schedule_type'] ) ) : 'once';
		$recurrence    = isset( $_POST['recurrence'] ) ? sanitize_key( wp_unslash( $_POST['recurrence'] ) ) : 'once';
		$start_at      = isset( $_POST['start_at'] ) ? sanitize_text_field( wp_unslash( $_POST['start_at'] ) ) : '';

		$source_job = rsl_ie()->Model->job->find( $source_job_id );
		if (
			! $source_job ||
			! in_array( $source_job->type, [ 'import', 'export', 'media_sync', 'update' ], true ) ||
			in_array( $source_job->status, [ 'pending', 'processing' ], true )
		) {
			$this->redirect_with_error( __( 'Select a supported source Job.', 'import-export-by-rockstarlab' ) );
		}

		if ( ! in_array( $schedule_type, [ 'once', 'recurring' ], true ) ) {
			$this->redirect_with_error( __( 'Invalid schedule type.', 'import-export-by-rockstarlab' ) );
		}

		$allowed_recurrences = [ 'hourly', 'twicedaily', 'daily', 'weekly' ];
		if ( 'recurring' === $schedule_type && ! in_array( $recurrence, $allowed_recurrences, true ) ) {
			$this->redirect_with_error( __( 'Invalid recurrence interval.', 'import-export-by-rockstarlab' ) );
		}

		if ( 'once' === $schedule_type ) {
			$recurrence = 'once';
		}

		$start_timestamp = $this->parse_local_datetime( $start_at );
		if ( ! $start_timestamp ) {
			$this->redirect_with_error( __( 'Enter a valid execution date and time.', 'import-export-by-rockstarlab' ) );
		}

		if ( $start_timestamp <= time() ) {
			$this->redirect_with_error( __( 'Execution time must be in the future.', 'import-export-by-rockstarlab' ) );
		}

		if ( '' === $name ) {
			$name = sprintf(
				/* translators: %d: source Job ID. */
				__( 'Schedule for Job #%d', 'import-export-by-rockstarlab' ),
				$source_job_id
			);
		}

		$data = [
			'source_job_id' => $source_job_id,
			'name'          => $name,
			'schedule_type' => $schedule_type,
			'recurrence'    => $recurrence,
			'status'        => 'active',
			'start_at_gmt'  => gmdate( 'Y-m-d H:i:s', $start_timestamp ),
			'next_run_gmt'  => gmdate( 'Y-m-d H:i:s', $start_timestamp ),
			'updated_at'    => current_time( 'mysql', true ),
		];

		$model = rsl_ie()->Model->job_schedule;
		if ( $schedule_id > 0 ) {
			if ( ! $model->find( $schedule_id ) ) {
				$this->redirect_with_error( __( 'Schedule not found.', 'import-export-by-rockstarlab' ) );
			}
			$result = $model->update( $schedule_id, $data );
		} else {
			$schedule_id = $model->create( $data );
			$result      = $schedule_id;
		}

		if ( is_wp_error( $result ) ) {
			$this->redirect_with_error( $result->get_error_message() );
		}

		$scheduled = Job_Scheduler::sync_event( $schedule_id );
		if ( is_wp_error( $scheduled ) ) {
			$this->redirect_with_error( $scheduled->get_error_message() );
		}

		wp_safe_redirect( $this->get_schedules_url( [ 'schedule_saved' => '1' ] ) );
		exit;
	}

	/**
	 * Delete a schedule and its cron event.
	 *
	 * @return void
	 */
	public function delete_schedule() {
		$this->verify_capability();
		check_admin_referer( 'rsl_ie_delete_schedule' );

		$schedule_id = isset( $_POST['schedule_id'] ) ? absint( wp_unslash( $_POST['schedule_id'] ) ) : 0;
		if ( $schedule_id <= 0 || ! rsl_ie()->Model->job_schedule->find( $schedule_id ) ) {
			$this->redirect_with_error( __( 'Schedule not found.', 'import-export-by-rockstarlab' ) );
		}

		Job_Scheduler::unschedule( $schedule_id );
		rsl_ie()->Model->job_schedule->delete( $schedule_id );

		wp_safe_redirect( $this->get_schedules_url( [ 'schedule_deleted' => '1' ] ) );
		exit;
	}

	/**
	 * Verify capability and request nonce.
	 *
	 * @return void
	 */
	private function verify_capability() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to manage schedules.', 'import-export-by-rockstarlab' ), '', [ 'response' => 403 ] );
		}
	}

	/**
	 * Parse a datetime-local value using the WordPress timezone.
	 *
	 * @param string $value Datetime-local value.
	 * @return int|false
	 */
	private function parse_local_datetime( $value ) {
		if ( '' === $value ) {
			return false;
		}

		$date = \DateTimeImmutable::createFromFormat( 'Y-m-d\TH:i', $value, wp_timezone() );
		if ( ! $date ) {
			return false;
		}

		return $date->getTimestamp();
	}

	/**
	 * Build a nonce-protected schedules screen URL.
	 *
	 * @param array $args Additional query args.
	 * @return string
	 */
	private function get_schedules_url( $args = [] ) {
		$args = array_merge(
			[
				'page'     => 'rsl-ie-schedules',
				'_wpnonce' => wp_create_nonce( 'rsl_ie_schedules_screen' ),
			],
			$args
		);

		return add_query_arg( $args, admin_url( 'admin.php' ) );
	}

	/**
	 * Redirect back to the schedules screen with an error message.
	 *
	 * @param string $message Error message.
	 * @return void
	 */
	private function redirect_with_error( $message ) {
		wp_safe_redirect( $this->get_schedules_url( [ 'schedule_error' => $message ] ) );
		exit;
	}

	/**
	 * Format a Source Job option label.
	 *
	 * @param object $job Job row.
	 * @return string
	 */
	private function format_source_job_label( $job ) {
		return sprintf(
			'#%1$d — %2$s / %3$s (%4$s)',
			(int) $job->id,
			(string) $job->type,
			(string) $job->data_type,
			(string) $job->status
		);
	}
}

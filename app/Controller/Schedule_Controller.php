<?php
/**
 * Job Schedule Controller
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Helper\Ajax_Security;

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
		if ( ! check_ajax_referer( Ajax_Security::nonce_action( 'rsl_ie_schedule_search_source_jobs' ), 'nonce', false ) ) {
			wp_send_json_error( [ 'message' => 'Security check failed.' ], 403 );
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce checked above.
		$input  = [
			'q'    => sanitize_text_field( wp_unslash( $_REQUEST['q'] ?? '' ) ),
			'page' => max( 1, absint( $_REQUEST['page'] ?? 1 ) ),
		];
		$result = ( new \RockStarLab\ImportExport\Service\Schedule_Service() )->execute( 'schedule-list-source-jobs', $input );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ], $result->get_error_data()['status'] ?? 400 );
		}
		foreach ( $result['results'] as &$item ) {
			$item['disabled'] = ! $item['schedulable']; }
		unset( $item );
		wp_send_json( $result );
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

		$input = [
			'source_job_id' => $source_job_id,
			'name'          => $name,
			'schedule_type' => $schedule_type,
			'recurrence'    => $recurrence,
			'start_at'      => $start_at,
			'timezone'      => wp_timezone_string(),
			'status'        => 'active',
			'changed_only'  => ! empty( $_POST['changed_only'] ),
		];
		if ( $schedule_id ) {
			$input['schedule_id'] = $schedule_id; }
		$result = ( new \RockStarLab\ImportExport\Service\Schedule_Service() )->execute( $schedule_id ? 'schedule-update' : 'schedule-create', $input );
		if ( is_wp_error( $result ) ) {
			$this->redirect_with_error( $result->get_error_message() ); }

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
		$result      = ( new \RockStarLab\ImportExport\Service\Schedule_Service() )->execute( 'schedule-delete', [ 'schedule_id' => $schedule_id ] );
		if ( is_wp_error( $result ) ) {
			$this->redirect_with_error( $result->get_error_message() ); }

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
}

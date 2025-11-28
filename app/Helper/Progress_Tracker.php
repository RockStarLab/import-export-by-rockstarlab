<?php
/**
 * Progress Tracker Helper
 *
 * Facade for job progress tracking operations.
 * Provides convenient static methods for updating job progress and status.
 *
 * @package WP_AIE\Helper
 */

namespace WP_AIE\Helper;

/**
 * Progress Tracker Helper Class
 *
 * Facade for job progress tracking operations.
 *
 * @package WP_AIE\Helper
 */
class Progress_Tracker {

	/**
	 * Update job progress with item counts
	 *
	 * @param int $job_id   Job ID to update
	 * @param int $total    Total number of items
	 * @param int $processed Number of items processed
	 * @param int $success  Number of successful items
	 * @param int $failed   Number of failed items
	 * @return int|WP_Error Number of rows affected or WP_Error
	 */
	public static function update_progress( $job_id, $total, $processed, $success, $failed ) {
		return WP_AIE()->Model->Job->update_progress( $job_id, $total, $processed, $success, $failed );
	}

	/**
	 * Increment processed item counter
	 *
	 * @param int  $job_id  Job ID to increment
	 * @param bool $success Whether the item was successful
	 * @return int|WP_Error|false Number of rows affected or false
	 */
	public static function increment( $job_id, $success = true ) {
		return WP_AIE()->Model->Job->increment( $job_id, $success );
	}

	/**
	 * Get current progress information
	 *
	 * @param int $job_id Job ID to get progress for
	 * @return array|null Progress information array or null
	 */
	public static function get_progress( $job_id ) {
		return WP_AIE()->Model->Job->get_progress( $job_id );
	}

	/**
	 * Mark job as complete or failed
	 *
	 * @param int  $job_id  Job ID to mark
	 * @param bool $success Whether completed successfully
	 * @return int|WP_Error Number of rows affected or WP_Error
	 */
	public static function mark_complete( $job_id, $success = true ) {
		return $success
			? WP_AIE()->Model->Job->mark_completed( $job_id )
			: WP_AIE()->Model->Job->mark_failed( $job_id );
	}

	/**
	 * Mark job as running/processing
	 *
	 * @param int $job_id Job ID to mark as running
	 * @return int|WP_Error Number of rows affected or WP_Error
	 */
	public static function mark_running( $job_id ) {
		return WP_AIE()->Model->Job->mark_running( $job_id );
	}

	/**
	 * Mark job as failed and optionally log error
	 *
	 * @param int    $job_id       Job ID to mark as failed
	 * @param string $error_message Optional. Error message to log
	 * @return int|WP_Error Number of rows affected or WP_Error
	 */
	public static function mark_failed( $job_id, $error_message = '' ) {
		if ( $error_message ) {
			WP_AIE()->Model->Log->error( $job_id, $error_message );
		}
		return WP_AIE()->Model->Job->mark_failed( $job_id );
	}
}

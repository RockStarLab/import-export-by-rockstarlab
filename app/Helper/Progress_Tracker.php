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
	 * Calculate and update percentage progress
	 *
	 * @param int $job_id Job ID to update
	 * @param int $processed Number of items processed
	 * @param int $total Total number of items
	 * @return bool|int False on failure, percentage on success
	 */
	public static function update_percentage( $job_id, $processed, $total ) {
		if ( $total <= 0 ) {
			return false;
		}

		$percentage = min( 100, round( ( $processed / $total ) * 100 ) );

		global $wpdb;
		$table = $wpdb->prefix . 'aie_jobs';

		$result = $wpdb->update(
			$table,
			array( 'progress' => $percentage ),
			array( 'id' => $job_id ),
			array( '%d' ),
			array( '%d' )
		);

		return false !== $result ? $percentage : false;
	}

	/**
	 * Estimate time remaining
	 *
	 * @param int $job_id Job ID
	 * @return array|null Estimate data or null
	 */
	public static function estimate_time_remaining( $job_id ) {
		$progress = self::get_progress( $job_id );

		if ( ! $progress || empty( $progress['started_at'] ) ) {
			return null;
		}

		$processed = (int) ( $progress['processed'] ?? 0 );
		$total     = (int) ( $progress['total'] ?? 0 );

		if ( $processed <= 0 || $total <= 0 ) {
			return null;
		}

		// Calculate time elapsed
		$started = strtotime( $progress['started_at'] );
		$now     = time();
		$elapsed = $now - $started;

		// Calculate average time per item
		$time_per_item = $elapsed / $processed;

		// Calculate remaining items and time
		$remaining_items = $total - $processed;
		$remaining_time  = $remaining_items * $time_per_item;

		// Calculate estimated completion time
		$estimated_completion = $now + $remaining_time;

		return array(
			'elapsed_seconds'      => $elapsed,
			'elapsed_formatted'    => self::format_duration( $elapsed ),
			'remaining_seconds'    => $remaining_time,
			'remaining_formatted'  => self::format_duration( $remaining_time ),
			'estimated_completion' => date( 'Y-m-d H:i:s', $estimated_completion ),
			'items_per_second'     => $elapsed > 0 ? round( $processed / $elapsed, 2 ) : 0,
			'percentage'           => round( ( $processed / $total ) * 100 ),
		);
	}

	/**
	 * Format duration in seconds to human-readable
	 *
	 * @param int $seconds Duration in seconds
	 * @return string Formatted duration
	 */
	protected static function format_duration( $seconds ) {
		$seconds = (int) $seconds;

		if ( $seconds < 60 ) {
			return $seconds . 's';
		}

		$minutes = floor( $seconds / 60 );
		$seconds = $seconds % 60;

		if ( $minutes < 60 ) {
			return sprintf( '%dm %ds', $minutes, $seconds );
		}

		$hours   = floor( $minutes / 60 );
		$minutes = $minutes % 60;

		return sprintf( '%dh %dm', $hours, $minutes );
	}

	/**
	 * Get real-time progress data
	 *
	 * @param int $job_id Job ID
	 * @return array|null Progress data with estimates
	 */
	public static function get_realtime_progress( $job_id ) {
		$progress = self::get_progress( $job_id );

		if ( ! $progress ) {
			return null;
		}

		$estimates = self::estimate_time_remaining( $job_id );

		return array_merge(
			$progress,
			array(
				'estimates' => $estimates,
				'timestamp' => current_time( 'mysql' ),
			)
		);
	}

	/**
	 * Batch update progress (for chunked operations)
	 *
	 * @param int   $job_id Job ID
	 * @param array $batch_result Batch processing result
	 * @return bool Success
	 */
	public static function update_batch_progress( $job_id, $batch_result ) {
		$progress = self::get_progress( $job_id );

		if ( ! $progress ) {
			return false;
		}

		$processed = (int) ( $progress['processed'] ?? 0 ) + (int) ( $batch_result['processed'] ?? 0 );
		$success   = (int) ( $progress['success'] ?? 0 ) + (int) ( $batch_result['success'] ?? 0 );
		$failed    = (int) ( $progress['failed'] ?? 0 ) + (int) ( $batch_result['failed'] ?? 0 );
		$total     = (int) ( $progress['total'] ?? 0 );

		return self::update_progress( $job_id, $total, $processed, $success, $failed );
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

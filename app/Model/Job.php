<?php
/**
 * Job Model
 *
 * Handles import/export job records in aie_jobs table.
 * Manages job creation, progress tracking, and status updates.
 *
 * Usage: WP_AIE()->model->job->find( $id )
 *
 * @package WP_AIE\Model
 */

namespace WP_AIE\Model;

/**
 * Job Model Class
 *
 * Handles import/export job records in aie_jobs table.
 *
 * @package WP_AIE\Model
 */
class Job extends Model {

	/**
	 * Table name without prefix
	 *
	 * @var string
	 */
	protected $table_name = 'aie_jobs';

	/**
	 * Create a new job record
	 *
	 * @param array $data {
	 *     Job data array.
	 *
	 *     @type int    $user_id      User ID who created the job
	 *     @type string $type         Job type: 'import' or 'export'
	 *     @type string $data_type    Content type being processed
	 *     @type string $file_format  File format: csv, json, xml, etc.
	 *     @type array  $settings     Optional. Job settings as array
	 * }
	 * @return int|WP_Error Created job ID on success, WP_Error on failure
	 */
	public function create( $data ) {
		$defaults = [
			'status'          => 'pending',
			'total_items'     => 0,
			'processed_items' => 0,
			'success_items'   => 0,
			'failed_items'    => 0,
			'created_at'      => current_time( 'mysql' ),
			'updated_at'      => current_time( 'mysql' ),
		];

		$data = wp_parse_args( $data, $defaults );

		return $this->insert( $data );
	}

	/**
	 * Update job progress with item counts
	 * Automatically calculates percentage based on total and processed
	 *
	 * @param int $job_id   Job ID to update
	 * @param int $total    Total number of items to process
	 * @param int $processed Number of items processed so far
	 * @param int $success  Number of successfully processed items
	 * @param int $failed   Number of failed items
	 * @return int|WP_Error Number of rows affected or WP_Error
	 */
	public function update_progress( $job_id, $total, $processed, $success, $failed ) {
		$percentage = $total > 0 ? round( ( $processed / $total ) * 100, 2 ) : 0;

		return $this->update(
			$job_id,
			[
				'total_items'     => $total,
				'processed_items' => $processed,
				'success_items'   => $success,
				'failed_items'    => $failed,
				'percentage'      => $percentage,
				'updated_at'      => current_time( 'mysql' ),
			]
		);
	}

	/**
	 * Increment processed item counters
	 * Increases processed count by 1 and updates success or failed count
	 *
	 * @param int  $job_id  Job ID to increment
	 * @param bool $success Whether the item was processed successfully
	 * @return int|WP_Error|false Number of rows affected or false
	 */
	public function increment( $job_id, $success = true ) {
		$job = $this->find( $job_id );

		if ( ! $job ) {
			return false;
		}

		$processed     = (int) $job->processed_items + 1;
		$success_count = $success ? (int) $job->success_items + 1 : (int) $job->success_items;
		$failed_count  = ! $success ? (int) $job->failed_items + 1 : (int) $job->failed_items;

		return $this->update_progress(
			$job_id,
			(int) $job->total_items,
			$processed,
			$success_count,
			$failed_count
		);
	}

	/**
	 * Get current progress information for a job
	 *
	 * @param int $job_id Job ID to get progress for
	 * @return array|null {
	 *     Progress information array or null if job not found.
	 *
	 *     @type int    $total      Total items to process
	 *     @type int    $processed  Items processed so far
	 *     @type int    $success    Successfully processed items
	 *     @type int    $failed     Failed items
	 *     @type float  $percentage Completion percentage (0-100)
	 *     @type string $status     Current job status
	 * }
	 */
	public function get_progress( $job_id ) {
		$job = $this->find( $job_id );

		if ( ! $job ) {
			return null;
		}

		return [
			'total'      => (int) $job->total_items,
			'processed'  => (int) $job->processed_items,
			'success'    => (int) $job->success_items,
			'failed'     => (int) $job->failed_items,
			'percentage' => (float) $job->percentage,
			'status'     => $job->status,
		];
	}

	/**
	 * Update job status
	 * Sets completed_at timestamp for final statuses (completed, failed, cancelled)
	 *
	 * @param int    $job_id Job ID to update
	 * @param string $status New status: pending, processing, completed, failed, paused, cancelled
	 * @return int|WP_Error Number of rows affected or WP_Error
	 */
	public function update_status( $job_id, $status ) {
		$data = [
			'status'     => $status,
			'updated_at' => current_time( 'mysql' ),
		];

		if ( in_array( $status, [ 'completed', 'failed', 'cancelled' ] ) ) {
			$data['completed_at'] = current_time( 'mysql' );
		}

		return $this->update( $job_id, $data );
	}

	/**
	 * Mark job as running/processing
	 *
	 * @param int $job_id Job ID to mark as running
	 * @return int|WP_Error Number of rows affected or WP_Error
	 */
	public function mark_running( $job_id ) {
		return $this->update_status( $job_id, 'running' );
	}

	/**
	 * Mark job as completed successfully
	 *
	 * @param int $job_id Job ID to mark as completed
	 * @return int|WP_Error Number of rows affected or WP_Error
	 */
	public function mark_completed( $job_id ) {
		return $this->update_status( $job_id, 'completed' );
	}

	/**
	 * Mark job as failed
	 *
	 * @param int $job_id Job ID to mark as failed
	 * @return int|WP_Error Number of rows affected or WP_Error
	 */
	public function mark_failed( $job_id ) {
		return $this->update_status( $job_id, 'failed' );
	}

	/**
	 * Get jobs created by a specific user
	 *
	 * @param int   $user_id User ID to search for
	 * @param array $args    Optional. Same as all() method arguments
	 * @return array Array of job records
	 */
	public function get_by_user( $user_id, $args = [] ) {
		global $wpdb;
		$table = $this->get_table_name();

		$limit  = isset( $args['limit'] ) ? intval( $args['limit'] ) : 20;
		$offset = isset( $args['offset'] ) ? intval( $args['offset'] ) : 0;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d",
				$user_id,
				$limit,
				$offset
			)
		);
	}

	/**
	 * Get jobs by status
	 *
	 * @param string $status Status to filter by: pending, processing, completed, failed, etc.
	 * @param array  $args   Optional. Same as all() method arguments
	 * @return array Array of job records
	 */
	public function get_by_status( $status, $args = [] ) {
		global $wpdb;
		$table = $this->get_table_name();

		$limit = isset( $args['limit'] ) ? intval( $args['limit'] ) : 100;

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE status = %s ORDER BY created_at DESC LIMIT %d",
				$status,
				$limit
			)
		);
	}

	/**
	 * Clean up old completed/failed jobs
	 * Deletes jobs older than specified days (default 30)
	 * Also cleans up orphaned logs via Log model
	 *
	 * @param int $days Number of days to keep, default 30
	 * @return int|false Number of jobs deleted or false on failure
	 */
	public function cleanup_old( $days = 30 ) {
		global $wpdb;
		$table = $this->get_table_name();

		$days = apply_filters( 'aie_cleanup_old_jobs_days', $days );

		// Delete old jobs
		$deleted = $wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$table} 
			WHERE status IN ('completed', 'failed', 'cancelled') 
			AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);

		// Delete orphaned logs
		$logs_table = $wpdb->prefix . 'aie_logs';
		$wpdb->query(
			"DELETE l FROM {$logs_table} l
			LEFT JOIN {$table} j ON l.job_id = j.id
			WHERE j.id IS NULL"
		);

		do_action( 'aie_old_jobs_cleaned', $deleted );

		return $deleted;
	}

	/**
	 * Clean up exported files older than specified days
	 * Deletes physical files and clears file_path in database
	 *
	 * @param int $days Number of days to keep files, default 7
	 * @return int Number of files deleted
	 */
	public function cleanup_old_files( $days = 7 ) {
		global $wpdb;
		$table = $this->get_table_name();

		$days = apply_filters( 'aie_cleanup_old_files_days', $days );

		// Get old export jobs with file paths
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, file_path FROM {$table} 
			WHERE type = 'export' 
			AND status = 'completed' 
			AND file_path IS NOT NULL 
			AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$days
			)
		);

		$deleted_count = 0;

		foreach ( $results as $row ) {
			// Delete physical file
			if ( file_exists( $row->file_path ) ) {
				@unlink( $row->file_path );
				++$deleted_count;
			}

			// Clear file_path in database
			$wpdb->update(
				$table,
				[ 'file_path' => null ],
				[ 'id' => $row->id ],
				[ '%s' ],
				[ '%d' ]
			);
		}

		do_action( 'aie_old_files_cleaned', $deleted_count );

		return $deleted_count;
	}
}

<?php
/**
 * Job Model
 *
 * Handles import/export job records in rsl_ie_jobs table.
 * Manages job creation, progress tracking, and status updates.
 *
 * Usage: rsl_ie()->model->job->find( $id )
 *
 * @package RockStarLab\ImportExport\Model
 */

namespace RockStarLab\ImportExport\Model;

defined( 'ABSPATH' ) || exit;

class Job extends Model {

	/**
	 * Table name without prefix
	 *
	 * @var string
	 */
	protected $table_name = 'rsl_ie_jobs';

	/**
	 * Get columns that may be used in dynamic SQL identifiers.
	 *
	 * @return array
	 */
	protected function get_allowed_columns() {
		return [
			'id',
			'user_id',
			'type',
			'data_type',
			'file_format',
			'status',
			'total_items',
			'processed_items',
			'success_items',
			'failed_items',
			'imported_items',
			'skipped_items',
			'error_items',
			'progress',
			'file_path',
			'file_size',
			'settings',
			'parameters',
			'result',
			'retries',
			'created_at',
			'updated_at',
			'started_at',
			'completed_at',
		];
	}

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
				'progress'        => $percentage,
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
	 *     @type string $started_at When job processing started
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
			'percentage' => (float) $job->progress,
			'status'     => $job->status,
			'started_at' => $job->started_at ?? null,
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

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying the plugin's custom jobs table.
			$wpdb->prepare(
				'SELECT * FROM %i WHERE user_id = %d ORDER BY created_at DESC LIMIT %d OFFSET %d',
				$table,
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

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying the plugin's custom jobs table.
			$wpdb->prepare(
				'SELECT * FROM %i WHERE status = %s ORDER BY created_at DESC LIMIT %d',
				$table,
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

		$days = apply_filters( 'rsl_ie_cleanup_old_jobs_days', $days );

		// Delete old jobs
		$deleted = $wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Deleting expired rows from the plugin's custom jobs table.
			$wpdb->prepare(
				"DELETE FROM %i
				WHERE status IN ('completed', 'failed', 'cancelled')
				AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$table,
				$days
			)
		);

		do_action( 'rsl_ie_old_jobs_cleaned', $deleted );

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

		$days = apply_filters( 'rsl_ie_cleanup_old_files_days', $days );

		// Get old export jobs with file paths
		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying expired export rows in the plugin's custom jobs table.
			$wpdb->prepare(
				"SELECT id, file_path FROM %i
				WHERE type = 'export'
				AND status = 'completed'
				AND file_path IS NOT NULL
				AND created_at < DATE_SUB(NOW(), INTERVAL %d DAY)",
				$table,
				$days
			)
		);

		$deleted_count = 0;

		foreach ( $results as $row ) {
			// Delete physical file
			if ( file_exists( $row->file_path ) ) {
				@wp_delete_file( $row->file_path );
				++$deleted_count;
			}

			// Clear file_path in database
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
				$table,
				[ 'file_path' => null ],
				[ 'id' => $row->id ],
				[ '%s' ],
				[ '%d' ]
			);
		}

		do_action( 'rsl_ie_old_files_cleaned', $deleted_count );

		return $deleted_count;
	}

	/**
	 * Get all jobs with filters
	 *
	 * @param array  $where  WHERE conditions
	 * @param int    $limit  Limit
	 * @param int    $offset Offset
	 * @param string $order  ORDER BY clause
	 * @return array Jobs
	 */
	public function get_all( $where = [], $limit = 50, $offset = 0, $order = 'created_at DESC' ) {
		global $wpdb;
		$table = $this->get_table_name();

		$user_id_filter     = ( is_array( $where ) && array_key_exists( 'user_id', $where ) ) ? (int) $where['user_id'] : 0;
		$type_filter        = ( is_array( $where ) && array_key_exists( 'type', $where ) ) ? (string) $where['type'] : '';
		$data_type_filter   = ( is_array( $where ) && array_key_exists( 'data_type', $where ) ) ? (string) $where['data_type'] : '';
		$file_format_filter = ( is_array( $where ) && array_key_exists( 'file_format', $where ) ) ? (string) $where['file_format'] : '';
		$status_filter      = ( is_array( $where ) && array_key_exists( 'status', $where ) ) ? (string) $where['status'] : '';

		$allowed_order_fields = [
			'id',
			'user_id',
			'type',
			'data_type',
			'file_format',
			'status',
			'total_items',
			'processed_items',
			'success_items',
			'failed_items',
			'progress',
			'created_at',
			'updated_at',
			'started_at',
			'completed_at',
		];

		$field     = 'created_at';
		$direction = 'DESC';
		if ( is_string( $order ) ) {
			$order = trim( $order );
			if ( preg_match( '/^([a-z_]+)(?:\s+(ASC|DESC))?$/i', $order, $matches ) ) {
				$maybe_field = strtolower( $matches[1] );
				$maybe_dir   = isset( $matches[2] ) ? strtoupper( $matches[2] ) : 'ASC';
				if ( in_array( $maybe_field, $allowed_order_fields, true ) ) {
					$field     = $maybe_field;
					$direction = ( 'DESC' === $maybe_dir ) ? 'DESC' : 'ASC';
				}
			}
		}

		$order_field = $field;
		$limit       = max( 0, (int) $limit );
		$offset      = max( 0, (int) $offset );

		$cache_key = 'rsl_ie_jobs:get_all:' . md5(
			wp_json_encode(
				[
					$where,
					$limit,
					$offset,
					$order_field,
					$direction,
				]
			)
		);
		$cached    = wp_cache_get( $cache_key, 'rsl_ie' );
		if ( false !== $cached ) {
			return $cached;
		}

		if ( 'DESC' === $direction ) {
			$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying the plugin's custom jobs table.
				$wpdb->prepare(
					'SELECT * FROM %i WHERE 1=1
					AND ( %d = 0 OR user_id = %d )
					AND ( %s = \'\' OR type = %s )
					AND ( %s = \'\' OR data_type = %s )
					AND ( %s = \'\' OR file_format = %s )
					AND ( %s = \'\' OR status = %s )
					ORDER BY %i DESC
					LIMIT %d OFFSET %d',
					$table,
					$user_id_filter,
					$user_id_filter,
					$type_filter,
					$type_filter,
					$data_type_filter,
					$data_type_filter,
					$file_format_filter,
					$file_format_filter,
					$status_filter,
					$status_filter,
					$order_field,
					$limit,
					$offset
				)
			);
		} else {
			$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying the plugin's custom jobs table.
				$wpdb->prepare(
					'SELECT * FROM %i WHERE 1=1
					AND ( %d = 0 OR user_id = %d )
					AND ( %s = \'\' OR type = %s )
					AND ( %s = \'\' OR data_type = %s )
					AND ( %s = \'\' OR file_format = %s )
					AND ( %s = \'\' OR status = %s )
					ORDER BY %i ASC
					LIMIT %d OFFSET %d',
					$table,
					$user_id_filter,
					$user_id_filter,
					$type_filter,
					$type_filter,
					$data_type_filter,
					$data_type_filter,
					$file_format_filter,
					$file_format_filter,
					$status_filter,
					$status_filter,
					$order_field,
					$limit,
					$offset
				)
			);
		}
		wp_cache_set( $cache_key, $results, 'rsl_ie', MINUTE_IN_SECONDS );

		return $results;
	}

	/**
	 * Count jobs with filters
	 *
	 * @param array $where WHERE conditions
	 * @return int Count
	 */
	public function count( $where = [] ) {
		global $wpdb;
		$table = $this->get_table_name();

		$user_id_filter     = ( is_array( $where ) && array_key_exists( 'user_id', $where ) ) ? (int) $where['user_id'] : 0;
		$type_filter        = ( is_array( $where ) && array_key_exists( 'type', $where ) ) ? (string) $where['type'] : '';
		$data_type_filter   = ( is_array( $where ) && array_key_exists( 'data_type', $where ) ) ? (string) $where['data_type'] : '';
		$file_format_filter = ( is_array( $where ) && array_key_exists( 'file_format', $where ) ) ? (string) $where['file_format'] : '';
		$status_filter      = ( is_array( $where ) && array_key_exists( 'status', $where ) ) ? (string) $where['status'] : '';

		$cache_key = 'rsl_ie_jobs:count:' . md5(
			wp_json_encode(
				[
					$where,
					$user_id_filter,
					$type_filter,
					$data_type_filter,
					$file_format_filter,
					$status_filter,
				]
			)
		);
		$cached    = wp_cache_get( $cache_key, 'rsl_ie' );
		if ( false !== $cached ) {
			return (int) $cached;
		}

		$count = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Counting rows in the plugin's custom jobs table.
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE 1=%d
					AND ( %d = 0 OR user_id = %d )
					AND ( %s = \'\' OR type = %s )
					AND ( %s = \'\' OR data_type = %s )
					AND ( %s = \'\' OR file_format = %s )
					AND ( %s = \'\' OR status = %s )',
				$table,
				1,
				$user_id_filter,
				$user_id_filter,
				$type_filter,
				$type_filter,
				$data_type_filter,
				$data_type_filter,
				$file_format_filter,
				$file_format_filter,
				$status_filter,
				$status_filter
			)
		);

		wp_cache_set( $cache_key, $count, 'rsl_ie', MINUTE_IN_SECONDS );

		return $count;
	}

	/**
	 * Delete job and associated files
	 *
	 * Overrides parent delete() to also remove export files and folders
	 *
	 * @param int $id Job ID to delete
	 * @return int|false|WP_Error Number of rows affected, false on error, or WP_Error
	 */
	public function delete( $id ) {
		$schedule_model = rsl_ie()->Model->job_schedule;
		if ( $schedule_model ) {
			$active_schedules = $schedule_model->find_by(
				[
					'source_job_id' => $id,
					'status'        => 'active',
				]
			);
			if ( ! empty( $active_schedules ) ) {
				return new \WP_Error(
					'job_has_active_schedule',
					__( 'This Job is used by an active schedule. Delete the schedule first.', 'import-export-by-rockstarlab' )
				);
			}
		}

		// Get job details before deletion
		$job = $this->find( $id );

		if ( ! $job ) {
			return new \WP_Error( 'job_not_found', __( 'Job not found', 'import-export-by-rockstarlab' ) );
		}

		// Delete associated export file if exists
		if ( ! empty( $job->file_path ) ) {
			$file_path = $job->file_path;

			// Convert relative path to absolute if needed
			// Check if path is absolute (starts with / on Unix or C:\ on Windows)
			$is_absolute = ( '/' === $file_path[0] || preg_match( '/^[a-zA-Z]:[\\\\\/]/', $file_path ) );

			if ( ! $is_absolute ) {
				$upload_dir = wp_upload_dir();
				$file_path  = trailingslashit( $upload_dir['basedir'] ) . ltrim( $file_path, '/' );
			}

			// Delete the file
			if ( file_exists( $file_path ) ) {
				wp_delete_file( $file_path );

				// Try to delete the parent directory if it's empty
				$dir = dirname( $file_path );
				if ( is_dir( $dir ) && $this->is_directory_empty( $dir ) ) {
					rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
				}
			}
		}

		// Delete job record from database
		return parent::delete( $id );
	}

	/**
	 * Check if directory is empty
	 *
	 * @param string $dir Directory path
	 * @return bool True if empty or only contains . and ..
	 */
	private function is_directory_empty( $dir ) {
		if ( ! is_readable( $dir ) ) {
			return false;
		}

		$handle = opendir( $dir );
		while ( false !== ( $entry = readdir( $handle ) ) ) {
			if ( $entry !== '.' && $entry !== '..' ) {
				closedir( $handle );
				return false;
			}
		}
		closedir( $handle );
		return true;
	}
}

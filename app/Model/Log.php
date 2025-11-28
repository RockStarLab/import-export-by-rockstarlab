<?php
/**
 * Log Model
 *
 * Handles log records in aie_logs table.
 * Manages logging of job execution events, errors, and messages.
 *
 * Usage: WP_AIE()->model->log->info( $job_id, 'Message' )
 *
 * @package WP_AIE\Model
 */

namespace WP_AIE\Model;

/**
 * Log Model Class
 *
 * Handles log records in aie_logs table.
 *
 * @package WP_AIE\Model
 */
class Log extends Model {

	/**
	 * Table name without prefix
	 *
	 * @var string
	 */
	protected $table_name = 'aie_logs';

	/**
	 * Log level constants
	 */
	const INFO    = 'info';
	const WARNING = 'warning';
	const ERROR   = 'error';

	/**
	 * Create a new log entry
	 *
	 * @param int    $job_id  Job ID this log belongs to
	 * @param string $level   Log level: info, warning, error
	 * @param string $message Log message text
	 * @param array  $data    Optional. Additional data to store as JSON
	 * @return int|WP_Error Created log ID on success, WP_Error on failure
	 */
	public function create( $job_id, $level, $message, $data = [] ) {
		return $this->insert(
			[
				'job_id'     => $job_id,
				'level'      => $level,
				'message'    => $message,
				'data'       => ! empty( $data ) ? wp_json_encode( $data ) : null,
				'created_at' => current_time( 'mysql' ),
			]
		);
	}

	/**
	 * Log an info-level message
	 * Convenience method for create() with INFO level
	 *
	 * @param int    $job_id  Job ID this log belongs to
	 * @param string $message Info message text
	 * @param array  $data    Optional. Additional data to store
	 * @return int|WP_Error Created log ID on success, WP_Error on failure
	 */
	public function info( $job_id, $message, $data = [] ) {
		return $this->create( $job_id, self::INFO, $message, $data );
	}

	/**
	 * Log a warning-level message
	 * Convenience method for create() with WARNING level
	 *
	 * @param int    $job_id  Job ID this log belongs to
	 * @param string $message Warning message text
	 * @param array  $data    Optional. Additional data to store
	 * @return int|WP_Error Created log ID on success, WP_Error on failure
	 */
	public function warning( $job_id, $message, $data = [] ) {
		return $this->create( $job_id, self::WARNING, $message, $data );
	}

	/**
	 * Log an error-level message
	 * Convenience method for create() with ERROR level
	 *
	 * @param int    $job_id  Job ID this log belongs to
	 * @param string $message Error message text
	 * @param array  $data    Optional. Additional error details
	 * @return int|WP_Error Created log ID on success, WP_Error on failure
	 */
	public function error( $job_id, $message, $data = [] ) {
		return $this->create( $job_id, self::ERROR, $message, $data );
	}

	/**
	 * Get all log entries for a specific job
	 *
	 * @param int         $job_id Job ID to get logs for
	 * @param string|null $level  Optional. Filter by level: info, warning, error
	 * @return array Array of log records ordered by creation time
	 */
	public function get_by_job( $job_id, $level = null ) {
		global $wpdb;
		$table = $this->get_table_name();

		if ( $level ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE job_id = %d AND level = %s ORDER BY created_at ASC",
					$job_id,
					$level
				)
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE job_id = %d ORDER BY created_at ASC",
				$job_id
			)
		);
	}

	/**
	 * Delete all log entries for a specific job
	 * Used for cleanup when job is deleted
	 *
	 * @param int $job_id Job ID to delete logs for
	 * @return int|false Number of rows deleted or false on failure
	 */
	public function delete_by_job( $job_id ) {
		global $wpdb;
		$table = $this->get_table_name();

		return $wpdb->delete(
			$table,
			[ 'job_id' => $job_id ],
			[ '%d' ]
		);
	}

	/**
	 * Get only error-level logs for a specific job
	 * Convenience method for get_by_job() filtered to errors
	 *
	 * @param int $job_id Job ID to get error logs for
	 * @return array Array of error log records
	 */
	public function get_errors_by_job( $job_id ) {
		return $this->get_by_job( $job_id, self::ERROR );
	}
}

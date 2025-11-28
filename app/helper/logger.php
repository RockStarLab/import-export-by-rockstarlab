<?php
/**
 * Logger Helper
 *
 * Facade for logging operations.
 * Provides convenient static methods for logging job events.
 *
 * @package WP_AIE\Helper
 */

namespace WP_AIE\helper;

/**
 * Logger Helper Class
 *
 * Facade for logging operations.
 *
 * @package WP_AIE\Helper
 */
class Logger {

	/**
	 * Log an info-level message
	 *
	 * @param int    $job_id  Job ID this log belongs to
	 * @param string $message Info message text
	 * @param array  $data    Optional. Additional data to store
	 * @return int|WP_Error Created log ID on success, WP_Error on failure
	 */
	public static function info( $job_id, $message, $data = [] ) {
		return WP_AIE()->model->log->info( $job_id, $message, $data );
	}

	/**
	 * Log a warning-level message
	 *
	 * @param int    $job_id  Job ID this log belongs to
	 * @param string $message Warning message text
	 * @param array  $data    Optional. Additional data to store
	 * @return int|WP_Error Created log ID on success, WP_Error on failure
	 */
	public static function warning( $job_id, $message, $data = [] ) {
		return WP_AIE()->model->log->warning( $job_id, $message, $data );
	}

	/**
	 * Log an error-level message
	 *
	 * @param int    $job_id  Job ID this log belongs to
	 * @param string $message Error message text
	 * @param array  $data    Optional. Additional error details
	 * @return int|WP_Error Created log ID on success, WP_Error on failure
	 */
	public static function error( $job_id, $message, $data = [] ) {
		return WP_AIE()->model->log->error( $job_id, $message, $data );
	}

	/**
	 * Get all log entries for a specific job
	 *
	 * @param int         $job_id Job ID to get logs for
	 * @param string|null $level  Optional. Filter by level: info, warning, error
	 * @return array Array of log records
	 */
	public static function get_logs( $job_id, $level = null ) {
		return WP_AIE()->model->log->get_by_job( $job_id, $level );
	}

	/**
	 * Clear all log entries for a specific job
	 *
	 * @param int $job_id Job ID to clear logs for
	 * @return int|false Number of rows deleted or false on failure
	 */
	public static function clear_logs( $job_id ) {
		return WP_AIE()->model->log->delete_by_job( $job_id );
	}
}

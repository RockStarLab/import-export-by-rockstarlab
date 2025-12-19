<?php
/**
 * Logger Helper
 *
 * Facade for logging operations.
 * Provides convenient static methods for logging job events.
 *
 * @package WP_AIE\Helper
 */

namespace WP_AIE\Helper;

/**
 * Logger Helper Class
 *
 * Facade for logging operations.
 *
 * @package WP_AIE\Helper
 */
class Logger {

	/**
	 * Generic log method that routes to appropriate level method
	 *
	 * @param int    $job_id  Job ID this log belongs to (0 = no database logging)
	 * @param string $level   Log level: 'info', 'warning', or 'error'
	 * @param string $message Log message text
	 * @param array  $data    Optional. Additional data to store
	 * @return int|WP_Error|true Created log ID on success, WP_Error on failure, true if logged to error_log
	 */
	public static function log( $job_id, $level, $message, $data = array() ) {
		// If job_id is 0, just log to error_log instead of database
		if ( 0 === $job_id ) {
			$log_message = sprintf( '[WP_AIE] [%s] %s', strtoupper( $level ), $message );
			if ( ! empty( $data ) ) {
				$log_message .= ' | Data: ' . wp_json_encode( $data );
			}
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			return true;
		}

		switch ( $level ) {
			case 'warning':
				return self::warning( $job_id, $message, $data );
			case 'error':
				return self::error( $job_id, $message, $data );
			case 'info':
			default:
				return self::info( $job_id, $message, $data );
		}
	}

	/**
	 * Log an info-level message
	 *
	 * @param int    $job_id  Job ID this log belongs to
	 * @param string $message Info message text
	 * @param array  $data    Optional. Additional data to store
	 * @return int|WP_Error Created log ID on success, WP_Error on failure
	 */
	public static function info( $job_id, $message, $data = array() ) {
		return WP_AIE()->Model->Log->info( $job_id, $message, $data );
	}

	/**
	 * Log a warning-level message
	 *
	 * @param int    $job_id  Job ID this log belongs to
	 * @param string $message Warning message text
	 * @param array  $data    Optional. Additional data to store
	 * @return int|WP_Error Created log ID on success, WP_Error on failure
	 */
	public static function warning( $job_id, $message, $data = array() ) {
		return WP_AIE()->Model->Log->warning( $job_id, $message, $data );
	}

	/**
	 * Log an error-level message
	 *
	 * @param int    $job_id  Job ID this log belongs to
	 * @param string $message Error message text
	 * @param array  $data    Optional. Additional error details
	 * @return int|WP_Error Created log ID on success, WP_Error on failure
	 */
	public static function error( $job_id, $message, $data = array() ) {
		return WP_AIE()->Model->Log->error( $job_id, $message, $data );
	}

	/**
	 * Get all log entries for a specific job
	 *
	 * @param int         $job_id Job ID to get logs for
	 * @param string|null $level  Optional. Filter by level: info, warning, error
	 * @return array Array of log records
	 */
	public static function get_logs( $job_id, $level = null ) {
		return WP_AIE()->Model->Log->get_by_job( $job_id, $level );
	}

	/**
	 * Clear all log entries for a specific job
	 *
	 * @param int $job_id Job ID to clear logs for
	 * @return int|false Number of rows deleted or false on failure
	 */
	public static function clear_logs( $job_id ) {
		return WP_AIE()->Model->Log->delete_by_job( $job_id );
	}
}

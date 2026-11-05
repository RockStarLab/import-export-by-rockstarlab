<?php
/**
 * Append-only job events without copied item payloads or credentials.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

/** Server-only logging; read access belongs to Job_Service. */
class Job_Log {
	/**
	 * Store one bounded event from a trusted service or importer/exporter.
	 *
	 * @param int    $id Job ID.
	 * @param string $level Severity.
	 * @param string $message Human-readable event.
	 * @return void
	 */
	public static function write( $id, $level, $message ) {
		if ( (int) $id < 1 || ! in_array( $level, [ 'info', 'warning', 'error', 'debug' ], true ) ) {
			return;
		}
		global $wpdb;
		$wpdb->insert(
			$wpdb->prefix . 'rsl_ie_job_logs',
			[
				'job_id'     => (int) $id,
				'level'      => $level,
				'message'    => substr( sanitize_text_field( $message ), 0, 8000 ),
				'created_at' => current_time( 'mysql' ),
			],
			[ '%d', '%s', '%s', '%s' ]
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Append an event without affecting the running job on logging failure.
	}
}

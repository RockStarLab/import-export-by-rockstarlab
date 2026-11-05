<?php
/**
 * Cookie-independent scheduling for managed import/export jobs.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

/** Per-job events avoid head-of-queue starvation; recovery repairs lost events. */
class Job_Runner {
	/** Register hooks once during plugin bootstrap. */
	public static function register() {
		// phpcs:ignore WordPress.WP.CronInterval -- Minute recovery schedules at most 100 jobs and does not execute batches.
		add_filter( 'cron_schedules', [ __CLASS__, 'schedules' ] );
		add_action( 'rsl_ie_run_job', [ __CLASS__, 'run_scheduled' ] );
		add_action( 'rsl_ie_recover_jobs', [ __CLASS__, 'recover' ] );
		add_action( 'init', [ __CLASS__, 'ensure_recovery' ] );
	}

	/** Schedule after init, when translated interval labels may be loaded. */
	public static function ensure_recovery() {
		if ( ! wp_next_scheduled( 'rsl_ie_recover_jobs' ) ) {
			wp_schedule_event( time() + 60, 'rsl_ie_minute', 'rsl_ie_recover_jobs' );
		}
	}

	/**
	 * Add a recovery interval without changing other plugin schedules.
	 *
	 * @param array $schedules Core intervals.
	 * @return array
	 */
	public static function schedules( $schedules ) {
		// phpcs:ignore WordPress.WP.CronInterval -- Bounded recovery only schedules jobs; batches run in separate events.
		$schedules['rsl_ie_minute'] = [
			'interval' => 60,
			'display'  => __( 'Plugin job recovery every minute', 'import-export-by-rockstarlab' ),
		];
		return $schedules;
	}

	/**
	 * Idempotently schedule execution; the persisted job survives schedule errors.
	 *
	 * @param int $job_id Job ID.
	 * @return true|\WP_Error
	 */
	public static function schedule( $job_id ) {
		$job = rsl_ie()->Model->job->find( (int) $job_id );
		if ( $job && ! empty( json_decode( $job->parameters, true )['migration_parent_id'] ) ) {
			return true; }
		$args = [ (int) $job_id ];
		if ( wp_next_scheduled( 'rsl_ie_run_job', $args ) ) {
			return true;
		}
		$result = wp_schedule_single_event( time() + 5, 'rsl_ie_run_job', $args, true );
		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Execute one batch under the service's existing connection-owned job lock.
	 *
	 * @param int $job_id Trusted scheduled job ID.
	 * @return void
	 */
	public static function run_scheduled( $job_id ) {
		self::run( $job_id, true );
	}

	public static function run( $job_id, $drain_migration = false ) {
		$job = rsl_ie()->Model->job->find( (int) $job_id );
		if ( ! $job || ( ! Job_Service::supports( $job ) && ! Sync_Job_Service::supports( $job ) && ! Migration_Service::supports( $job ) && ! AI_URL_Service::supports( $job ) && ! ( 'import' === $job->type && 'media_sync' === $job->data_type ) ) || ! in_array( $job->status, [ 'pending', 'processing' ], true ) ) {
			return;
		}
		if ( AI_URL_Service::supports( $job ) ) {
			( new AI_URL_Service() )->process_background_batch( (int) $job_id );
		} else {
			$service = Migration_Service::supports( $job ) ? new Migration_Service() : ( Sync_Job_Service::supports( $job ) ? new Sync_Job_Service() : ( 'media_sync' === $job->data_type ? new Media_Folder_Service() : ( 'import' === $job->type ? new Import_Service() : new Export_Service() ) ) );
			$started  = microtime( true );
			$deadline = $started + 8;
			do {
				// Imports share one time budget per cron request: each drained batch only gets
				// the time that is left, so the whole request stays near a single batch budget.
				$cap = null;
				if ( $service instanceof Import_Service ) {
					$remaining = max( 2.0, Import_Service::BATCH_TIME_BUDGET - ( microtime( true ) - $started ) );
					$cap       = static function ( $budget ) use ( $remaining ) {
						return min( (float) $budget, $remaining );
					};
					add_filter( 'rsl_ie_import_batch_time_budget', $cap, PHP_INT_MAX );
				}
				try {
					$outcome = $service->process_background_batch( (int) $job_id );
				} finally {
					if ( $cap ) {
						remove_filter( 'rsl_ie_import_batch_time_budget', $cap, PHP_INT_MAX );
					}
				}
				$current = rsl_ie()->Model->job->find( (int) $job_id );
				// Drain migration checkpoints and import batches without another cron wait between
				// each one: WP-Cron only fires on page visits, so one batch per event left a closed
				// browser tab importing ~50 rows per visitor minute. Each import batch has its own
				// time budget, and no new batch starts after the deadline.
				$drains  = Migration_Service::supports( $job ) || $service instanceof Import_Service;
				$advance = ! is_wp_error( $outcome ) && empty( $outcome['locked'] ) && $drain_migration && $drains && $current && in_array( $current->status, [ 'pending', 'processing' ], true );
			} while ( $advance && microtime( true ) < ( $service instanceof Import_Service ? $started + Import_Service::BATCH_TIME_BUDGET / 2 : $deadline ) );
		}
		$job = rsl_ie()->Model->job->find( (int) $job_id );
		if ( $job && in_array( $job->status, [ 'pending', 'processing' ], true ) ) {
			self::schedule( $job_id );
		}
	}

	/** Recover jobs whose requests/processes ended before scheduling succeeded. */
	public static function recover() {
		global $wpdb;
		Import_Object_Store::cleanup_cancelled();
		$jobs = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE request_payload IS NOT NULL AND status IN ('pending','processing') AND type IN ('import','export','sync_background','migration_export','migration_import') AND data_type != 'content_update' ORDER BY updated_at ASC, id ASC LIMIT 100", $wpdb->prefix . 'rsl_ie_jobs' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Durable queue recovery bypasses object caches.
		foreach ( $jobs as $job ) {
			self::schedule( $job->id );
		}
	}
}

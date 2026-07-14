<?php
/**
 * Job Scheduler
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Creates cron events and clones source Jobs into the normal queue.
 */
class Job_Scheduler {

	/** Cron action used for individual schedules. */
	const CRON_HOOK = 'rsl_ie_run_job_schedule';

	/**
	 * Register the scheduler callback.
	 *
	 * @return void
	 */
	public static function init() {
		add_action( self::CRON_HOOK, [ __CLASS__, 'run' ] );
	}

	/**
	 * Schedule or reschedule a database schedule.
	 *
	 * @param int $schedule_id Schedule ID.
	 * @return true|\WP_Error
	 */
	public static function sync_event( $schedule_id ) {
		$schedule_id = absint( $schedule_id );
		$schedule    = rsl_ie()->Model->job_schedule->find( $schedule_id );

		wp_clear_scheduled_hook( self::CRON_HOOK, [ $schedule_id ] );

		if ( ! $schedule || 'active' !== $schedule->status ) {
			return true;
		}

		$timestamp = strtotime( $schedule->start_at_gmt . ' UTC' );
		if ( ! $timestamp ) {
			return new \WP_Error( 'invalid_schedule_time', __( 'Invalid schedule start time.', 'import-export-by-rockstarlab' ) );
		}

		$timestamp = max( $timestamp, time() + 1 );

		if ( 'recurring' === $schedule->schedule_type ) {
			$scheduled = wp_schedule_event( $timestamp, $schedule->recurrence, self::CRON_HOOK, [ $schedule_id ], true );
		} else {
			$scheduled = wp_schedule_single_event( $timestamp, self::CRON_HOOK, [ $schedule_id ], true );
		}

		if ( is_wp_error( $scheduled ) ) {
			return $scheduled;
		}

		rsl_ie()->Model->job_schedule->update(
			$schedule_id,
			[
				'next_run_gmt' => gmdate( 'Y-m-d H:i:s', $timestamp ),
				'updated_at'   => current_time( 'mysql', true ),
			]
		);

		return true;
	}

	/**
	 * Remove all cron events for a schedule.
	 *
	 * @param int $schedule_id Schedule ID.
	 * @return void
	 */
	public static function unschedule( $schedule_id ) {
		wp_clear_scheduled_hook( self::CRON_HOOK, [ absint( $schedule_id ) ] );
	}

	/**
	 * Run a scheduled job by cloning its source Job into the normal queue.
	 *
	 * @param int $schedule_id Schedule ID.
	 * @return void
	 */
	public static function run( $schedule_id ) {
		$schedule_id    = absint( $schedule_id );
		$schedule_model = rsl_ie()->Model->job_schedule;
		$schedule       = $schedule_model->find( $schedule_id );

		if ( ! $schedule || 'active' !== $schedule->status ) {
			return;
		}

		// Do not overlap recurring runs of the same schedule.
		if ( ! empty( $schedule->last_job_id ) ) {
			$last_job = rsl_ie()->Model->job->find( (int) $schedule->last_job_id );
			if ( $last_job && in_array( $last_job->status, [ 'pending', 'processing' ], true ) ) {
				self::update_after_run( $schedule, 0 );
				return;
			}
		}

		$new_job_id = self::clone_job( (int) $schedule->source_job_id );
		if ( is_wp_error( $new_job_id ) ) {
			$error_data = [
				'last_run_gmt' => current_time( 'mysql', true ),
				'updated_at'   => current_time( 'mysql', true ),
			];
			if ( 'once' === $schedule->schedule_type ) {
				$error_data['status']       = 'failed';
				$error_data['next_run_gmt'] = null;
			}
			$schedule_model->update(
				$schedule_id,
				$error_data
			);
			return;
		}

		self::update_after_run( $schedule, $new_job_id );

		// Put the clone onto the existing queue immediately. The recurring queue
		// event remains as a fallback when a host blocks loopback requests.
		wp_schedule_single_event( time() + 1, 'rsl_ie_process_queue' );
	}

	/**
	 * Clone a source Job with runtime state reset.
	 *
	 * @param int $source_job_id Source Job ID.
	 * @return int|\WP_Error
	 */
	public static function clone_job( $source_job_id ) {
		$source = rsl_ie()->Model->job->find( absint( $source_job_id ) );
		if ( ! $source ) {
			return new \WP_Error( 'source_job_not_found', __( 'The source Job no longer exists.', 'import-export-by-rockstarlab' ) );
		}

		if ( ! in_array( $source->type, [ 'import', 'export', 'media_sync', 'update' ], true ) ) {
			return new \WP_Error( 'unsupported_job_type', __( 'This Job type cannot be scheduled.', 'import-export-by-rockstarlab' ) );
		}

		$parameters = self::reset_parameters( $source->type, $source->parameters );
		$settings   = self::reset_settings( $source->type, $source->settings );
		$payload    = [
			'user_id'     => (int) $source->user_id,
			'type'        => $source->type,
			'data_type'   => $source->data_type,
			'file_format' => $source->file_format,
			'status'      => 'pending',
			'parameters'  => $parameters,
			'settings'    => $settings,
		];

		if ( in_array( $source->type, [ 'import', 'update' ], true ) ) {
			$payload['file_path'] = $source->file_path;
			$payload['file_size'] = $source->file_size;
		}

		if ( 'update' === $source->type ) {
			$payload['total_items'] = (int) $source->total_items;
		}

		return rsl_ie()->Model->job->create( $payload );
	}

	/**
	 * Reset mutable parameters before a rerun.
	 *
	 * @param string $type Job type.
	 * @param mixed  $raw  Raw parameters.
	 * @return mixed
	 */
	private static function reset_parameters( $type, $raw ) {
		if ( 'import' !== $type || ! is_string( $raw ) ) {
			return $raw;
		}

		$parameters = json_decode( $raw, true );
		if ( ! is_array( $parameters ) ) {
			return $raw;
		}

		unset( $parameters['prepared_data'], $parameters['total_items'], $parameters['cumulative_result'] );
		$parameters['offset'] = 0;

		return wp_json_encode( $parameters );
	}

	/**
	 * Reset mutable media sync settings before a rerun.
	 *
	 * @param string $type Job type.
	 * @param mixed  $raw  Raw settings.
	 * @return mixed
	 */
	private static function reset_settings( $type, $raw ) {
		if ( 'media_sync' !== $type || ! is_string( $raw ) ) {
			return $raw;
		}

		$settings = json_decode( $raw, true );
		if ( ! is_array( $settings ) ) {
			return $raw;
		}

		unset( $settings['processed_count'], $settings['total_files'], $settings['all_files'] );
		$settings['offset'] = 0;

		return wp_json_encode( $settings );
	}

	/**
	 * Update schedule bookkeeping after an occurrence.
	 *
	 * @param object $schedule Schedule row.
	 * @param int    $job_id   New Job ID, or zero when an overlapping run was skipped.
	 * @return void
	 */
	private static function update_after_run( $schedule, $job_id ) {
		$data = [
			'last_run_gmt' => current_time( 'mysql', true ),
			'updated_at'   => current_time( 'mysql', true ),
		];

		if ( $job_id > 0 ) {
			$data['last_job_id'] = $job_id;
		}

		if ( 'once' === $schedule->schedule_type ) {
			$data['status']       = 'completed';
			$data['next_run_gmt'] = null;
		} else {
			$next                 = wp_next_scheduled( self::CRON_HOOK, [ (int) $schedule->id ] );
			$data['next_run_gmt'] = $next ? gmdate( 'Y-m-d H:i:s', $next ) : null;
		}

		rsl_ie()->Model->job_schedule->update( (int) $schedule->id, $data );
	}
}

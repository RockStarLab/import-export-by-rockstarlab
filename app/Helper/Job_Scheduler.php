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
		add_action( 'rsl_ie_recover_jobs', [ __CLASS__, 'recover' ] );
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

		$cleared = wp_clear_scheduled_hook( self::CRON_HOOK, [ $schedule_id ], true );
		if ( is_wp_error( $cleared ) ) {
			return $cleared; }

		if ( ! $schedule || 'active' !== $schedule->status ) {
			return true;
		}

		$timestamp = strtotime( ( $schedule->next_run_gmt ? $schedule->next_run_gmt : $schedule->start_at_gmt ) . ' UTC' );
		if ( ! $timestamp ) {
			return new \WP_Error( 'invalid_schedule_time', __( 'Invalid schedule start time.', 'import-export-by-rockstarlab' ) );
		}

		$due_timestamp = $timestamp;
		$timestamp     = max( $timestamp, time() + 1 );

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
				'next_run_gmt' => gmdate( 'Y-m-d H:i:s', $due_timestamp ),
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
		$schedule_id = absint( $schedule_id );
		$lock        = \RockStarLab\ImportExport\Service\Job_Lock::acquire_resource( 'schedule', $schedule_id );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return; }
		try {
			$model    = rsl_ie()->Model->job_schedule;
			$schedule = $model->find( $schedule_id );
			if ( ! $schedule || 'active' !== $schedule->status || ! $schedule->next_run_gmt ) {
				return; }
			$due = strtotime( $schedule->next_run_gmt . ' UTC' );
			if ( ! $due || $due > time() ) {
				return; }
			$context = \RockStarLab\ImportExport\Abilities\Execution_Context::from_storage( (int) $schedule->created_by, get_current_blog_id() );
			$options = get_option( 'rsl_ie_schedule_meta_' . $schedule_id, [] );
			$result  = is_wp_error( $context ) ? $context : $context->run(
				static function () use ( $schedule, $due, $options ) {
					$last = $schedule->last_job_id ? rsl_ie()->Model->job->find( (int) $schedule->last_job_id ) : null;
					if ( $last && in_array( $last->status, [ 'pending', 'processing', 'paused' ], true ) ) {
						return new \WP_Error( 'rsl_ie_schedule_overlap', 'Previous occurrence is still active.', [ 'status' => 409 ] );
					}
					// "Changes only": everything modified since the previous completed run started.
					// Without a completed previous run, export everything so nothing is missed.
					$since = ! empty( $options['changed_only'] ) && $last && 'completed' === $last->status ? (string) $last->created_at : '';
					return ( new \RockStarLab\ImportExport\Service\Schedule_Source_Service() )->execute( (int) $schedule->source_job_id, 'schedule:' . $schedule->id . ':' . $due, $since );
				}
			);
			$meta    = get_option( 'rsl_ie_schedule_meta_' . $schedule_id, [] );
			if ( is_wp_error( $result ) && 'rsl_ie_schedule_overlap' === $result->get_error_code() ) {
				$interval = 'recurring' === $schedule->schedule_type ? ( wp_get_schedules()[ $schedule->recurrence ]['interval'] ?? 60 ) : 60;
				$next     = $due + ( max( 0, (int) floor( ( time() - $due ) / $interval ) ) + 1 ) * $interval;
				$model->update(
					$schedule_id,
					[
						'next_run_gmt' => gmdate( 'Y-m-d H:i:s', $next ),
						'last_run_gmt' => current_time( 'mysql', true ),
					]
				);
				self::sync_event( $schedule_id );
				return;
			}
			if ( is_wp_error( $result ) ) {
				$meta['error_code'] = $result->get_error_code();
				update_option( 'rsl_ie_schedule_meta_' . $schedule_id, $meta, false );
				$model->update(
					$schedule_id,
					[
						'status'       => 'failed',
						'next_run_gmt' => null,
						'last_run_gmt' => current_time( 'mysql', true ),
					]
				);
				self::unschedule( $schedule_id );
				return;
			}
			if ( empty( $result['job_id'] ) ) {
				return; }
			$data = [
				'last_job_id'  => (int) $result['job_id'],
				'last_run_gmt' => current_time( 'mysql', true ),
				'updated_at'   => current_time( 'mysql', true ),
			];
			if ( 'once' === $schedule->schedule_type ) {
				$data['status']       = 'completed';
				$data['next_run_gmt'] = null;
			} else {
				$interval = wp_get_schedules()[ $schedule->recurrence ]['interval'] ?? 0;
				if ( ! $interval ) {
					$data['status']       = 'failed';
					$data['next_run_gmt'] = null;
				} else {
					$next                 = $due + ( max( 0, (int) floor( ( time() - $due ) / $interval ) ) + 1 ) * $interval;
					$data['next_run_gmt'] = gmdate( 'Y-m-d H:i:s', $next );
				}
			}
			$saved = $model->update( $schedule_id, $data );
			if ( false === $saved || is_wp_error( $saved ) ) {
				return; }
			self::sync_event( $schedule_id );
		} finally {
			\RockStarLab\ImportExport\Service\Job_Lock::release( $lock );
		}
	}

	/** Recover a lost cron event without executing content in recovery. */
	public static function recover() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM %i WHERE status = 'active' AND next_run_gmt <= %s ORDER BY next_run_gmt ASC LIMIT 100", $wpdb->prefix . 'rsl_ie_job_schedules', current_time( 'mysql', true ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Durable schedule recovery.
		foreach ( $rows as $row ) {
			$next = wp_next_scheduled( self::CRON_HOOK, [ (int) $row->id ] );
			if ( ! $next || $next > time() + 60 ) {
				$lock = \RockStarLab\ImportExport\Service\Job_Lock::acquire_resource( 'schedule', $row->id );
				if ( $lock && ! is_wp_error( $lock ) ) {
					try {
						self::sync_event( (int) $row->id ); } finally {
						\RockStarLab\ImportExport\Service\Job_Lock::release( $lock ); }
				}
			}
		}
	}

	/**
	 * Create a fresh authorized job from a supported terminal source.
	 *
	 * @param int $source_job_id Source job.
	 * @return int|\WP_Error
	 */
	public static function clone_job( $source_job_id ) {
		$result = ( new \RockStarLab\ImportExport\Service\Schedule_Source_Service() )->execute( $source_job_id, 'schedule-manual:' . wp_generate_uuid4() );
		return is_wp_error( $result ) ? $result : (int) $result['job_id'];
	}
}

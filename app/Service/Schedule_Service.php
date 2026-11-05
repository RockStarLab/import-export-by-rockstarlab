<?php
/**
 * Shared schedule CRUD and source search.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Schedule_Catalog;
use RockStarLab\ImportExport\Helper\Job_Scheduler;

defined( 'ABSPATH' ) || exit;

/** Transport-independent schedule management with serialized updates. */
class Schedule_Service {
	/**
	 * Validate and dispatch an operation.
	 *
	 * @param string $name Ability suffix.
	 * @param array  $input Closed input.
	 * @return mixed
	 */
	public function execute( $name, array $input = [] ) {
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		$definition = Schedule_Catalog::definitions()[ $name ] ?? null;
		if ( ! $definition ) {
			return $this->error( 'operation', 400 );
		}
		$valid = rest_validate_value_from_schema( $input, $definition['input'], 'input' );
		if ( is_wp_error( $valid ) ) {
			$valid->add_data( [ 'status' => 400 ] );
			return $valid;
		}
		if ( 'schedule-options' === $name ) {
			$recurrences = [];
			foreach ( [ 'hourly', 'twicedaily', 'daily', 'weekly' ] as $key ) {
				$interval = wp_get_schedules()[ $key ]['interval'] ?? 0;
				if ( $interval ) {
					$recurrences[] = [
						'value'    => $key,
						'interval' => (int) $interval,
					];
				}
			}
			return [
				'timezone'    => wp_timezone_string(),
				'now'         => wp_date( 'Y-m-d\TH:i' ),
				'recurrences' => $recurrences,
			];
		}
		if ( 'schedule-list-source-jobs' === $name ) {
			return $this->sources( $input );
		}
		if ( 'schedule-list' === $name ) {
			global $wpdb;
			$limit = $input['per_page'] ?? 20;
			$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i ORDER BY id DESC LIMIT %d OFFSET %d', $wpdb->prefix . 'rsl_ie_job_schedules', $limit + 1, ( ( $input['page'] ?? 1 ) - 1 ) * $limit ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Live schedule page.
			return [
				'schedules' => array_map( [ $this, 'snapshot' ], array_slice( $rows, 0, $limit ) ),
				'more'      => count( $rows ) > $limit,
			];
		}
		$id   = $input['schedule_id'] ?? 0;
		$lock = Job_Lock::acquire_resource( 'schedule', $id );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return $this->error( 'busy', 409 );
		}
		try {
			$model = rsl_ie()->Model->job_schedule;
			$row   = $id ? $model->find( $id ) : null;
			if ( 'schedule-create' !== $name && ! $row ) {
				return $this->error( 'not_found', 404 );
			}
			if ( 'schedule-get' === $name ) {
				return $this->snapshot( $row );
			}
			if ( 'schedule-delete' === $name ) {
				$cleared = wp_clear_scheduled_hook( Job_Scheduler::CRON_HOOK, [ $id ], true );
				if ( is_wp_error( $cleared ) ) {
					return $cleared;
				}
				$deleted = $model->delete( $id );
				if ( false === $deleted || is_wp_error( $deleted ) ) {
					return $this->error( 'delete_failed', 500 );
				}
				delete_option( 'rsl_ie_schedule_meta_' . $id );
				return [
					'schedule_id' => $id,
					'deleted'     => true,
				];
			}
			return $this->save( $row, $input );
		} finally {
			Job_Lock::release( $lock );
		}
	}

	/**
	 * Persist a validated definition and its cron event.
	 *
	 * @param object|null $row Existing schedule.
	 * @param array       $input Validated fields.
	 * @return mixed
	 */
	private function save( $row, array $input ) {
		$id   = $row ? (int) $row->id : 0;
		$meta = $id ? get_option( 'rsl_ie_schedule_meta_' . $id, [] ) : [];
		$zone = $input['timezone'] ?? $meta['timezone'] ?? wp_timezone_string();
		try {
			$timezone = new \DateTimeZone( $zone );
		} catch ( \Exception $error ) {
			return $this->error( 'timezone', 400 );
		}
		$local = $input['start_at'] ?? ( $row ? wp_date( 'Y-m-d\TH:i', strtotime( $row->start_at_gmt . ' UTC' ), $timezone ) : '' );
		$date  = \DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $local, $timezone );
		if ( ! $date || $date->format( 'Y-m-d\TH:i' ) !== $local ) {
			return $this->error( 'date', 400 );
		}
		$transitions     = $timezone->getTransitions( $date->getTimestamp() - DAY_IN_SECONDS, $date->getTimestamp() + DAY_IN_SECONDS );
		$previous_offset = null;
		foreach ( $transitions ? $transitions : [] as $transition ) {
			$wall = strtotime( $local . ' UTC' );
			if ( null !== $previous_offset && $transition['offset'] < $previous_offset && $wall >= $transition['ts'] + $transition['offset'] && $wall < $transition['ts'] + $previous_offset ) {
				return $this->error( 'ambiguous_time_use_utc', 400 );
			}
			$previous_offset = $transition['offset'];
		}
		$type       = $input['schedule_type'] ?? $row->schedule_type ?? 'once';
		$recurrence = 'once' === $type ? 'once' : ( $input['recurrence'] ?? $row->recurrence ?? 'daily' );
		if ( 'recurring' === $type && ( 'once' === $recurrence || empty( wp_get_schedules()[ $recurrence ]['interval'] ) ) ) {
			return $this->error( 'recurrence', 400 );
		}
		$status         = $input['status'] ?? ( $row ? $row->status : 'active' );
		$timing_changed = ! $row || isset( $input['start_at'], $input['timezone'] ) || array_intersect( array_keys( $input ), [ 'start_at', 'timezone', 'schedule_type', 'recurrence' ] );
		$next           = $timing_changed ? $date->getTimestamp() : ( $row->next_run_gmt ? strtotime( $row->next_run_gmt . ' UTC' ) : $date->getTimestamp() );
		if ( 'active' === $status && $next <= time() ) {
			return $this->error( 'future_time_required', 400 );
		}
		$source_id = $input['source_job_id'] ?? (int) $row->source_job_id;
		$source    = ( ! $row || isset( $input['source_job_id'] ) || 'active' === $status ) ? ( new Schedule_Source_Service() )->execute( $source_id ) : true;
		if ( is_wp_error( $source ) ) {
			return $source;
		}
		$name   = sanitize_text_field( $input['name'] ?? $row->name ?? '' );
		$data   = [
			'source_job_id' => $source_id,
			'name'          => '' === $name ? 'Schedule for Job #' . $source_id : $name,
			'schedule_type' => $type,
			'recurrence'    => $recurrence,
			'status'        => $status,
			'start_at_gmt'  => gmdate( 'Y-m-d H:i:s', $date->getTimestamp() ),
			'next_run_gmt'  => 'active' === $status ? gmdate( 'Y-m-d H:i:s', $next ) : null,
			'updated_at'    => current_time( 'mysql', true ),
			'created_by'    => get_current_user_id(),
		];
		$result = $id ? rsl_ie()->Model->job_schedule->update( $id, $data ) : rsl_ie()->Model->job_schedule->create( $data );
		if ( false === $result || is_wp_error( $result ) ) {
			return $this->error( 'save_failed', 500 );
		}
		$id = $id ? $id : (int) $result;
		update_option(
			'rsl_ie_schedule_meta_' . $id,
			[
				'timezone'   => $zone,
				'error_code' => '',
			],
			false
		);
		$scheduled = Job_Scheduler::sync_event( $id );
		if ( is_wp_error( $scheduled ) ) {
			rsl_ie()->Model->job_schedule->update(
				$id,
				[
					'status'       => 'failed',
					'next_run_gmt' => null,
				]
			);
			update_option(
				'rsl_ie_schedule_meta_' . $id,
				[
					'timezone'   => $zone,
					'error_code' => $scheduled->get_error_code(),
				],
				false
			);
			return $this->error( 'cron_failed', 500 );
		}
		return $this->snapshot( rsl_ie()->Model->job_schedule->find( $id ) );
	}

	/**
	 * Return public fields only.
	 *
	 * @param object $row Schedule.
	 * @return array
	 */
	public function snapshot( $row ) {
		$meta = get_option( 'rsl_ie_schedule_meta_' . $row->id, [] );
		return [
			'id'            => (int) $row->id,
			'source_job_id' => (int) $row->source_job_id,
			'last_job_id'   => (int) $row->last_job_id,
			'name'          => (string) $row->name,
			'schedule_type' => $row->schedule_type,
			'recurrence'    => $row->recurrence,
			'status'        => $row->status,
			'start_at_gmt'  => $row->start_at_gmt,
			'next_run_gmt'  => $row->next_run_gmt,
			'last_run_gmt'  => $row->last_run_gmt,
			'timezone'      => $meta['timezone'] ?? wp_timezone_string(),
			'error_code'    => $meta['error_code'] ?? '',
		];
	}

	/**
	 * Read a bounded source page without revealing parameters.
	 *
	 * @param array $input Search and pagination.
	 * @return array
	 */
	private function sources( array $input ) {
		global $wpdb;
		$limit   = $input['per_page'] ?? 10;
		$search  = $input['q'] ?? '';
		$rows    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE type IN ('import','export','content_sync_push','content_sync_pull','sync_background') AND status IN ('completed','failed','cancelled') AND (%s = '' OR job_name LIKE %s OR id = %d) ORDER BY id DESC LIMIT %d OFFSET %d", $wpdb->prefix . 'rsl_ie_jobs', $search, '%' . $wpdb->esc_like( $search ) . '%', (int) $search, $limit + 1, ( ( $input['page'] ?? 1 ) - 1 ) * $limit ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Bounded source search.
		$results = [];
		foreach ( array_slice( $rows, 0, $limit ) as $row ) {
			$valid     = ( new Schedule_Source_Service() )->execute( $row->id );
			$results[] = [
				'id'          => (int) $row->id,
				'text'        => sprintf( '%s — #%d — %s / %s (%s)', $row->job_name ?? '', $row->id, $row->type, $row->data_type, $row->status ),
				'schedulable' => ! is_wp_error( $valid ),
				'error_code'  => is_wp_error( $valid ) ? $valid->get_error_code() : '',
			];
		}
		return [
			'results'    => $results,
			'pagination' => [ 'more' => count( $rows ) > $limit ],
		];
	}

	/**
	 * Return a stable error.
	 *
	 * @param string $code Suffix.
	 * @param int    $status HTTP status.
	 * @return \WP_Error
	 */
	private function error( $code, $status ) {
		return new \WP_Error( 'rsl_ie_schedule_' . $code, 'Schedule operation failed: ' . $code, [ 'status' => $status ] );
	}
}

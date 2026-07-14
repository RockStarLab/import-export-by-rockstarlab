<?php
/**
 * Job Schedule Model
 *
 * @package RockStarLab\ImportExport\Model
 */

namespace RockStarLab\ImportExport\Model;

defined( 'ABSPATH' ) || exit;

/**
 * Stores reusable Job schedule definitions.
 */
class JobSchedule extends Model {

	/**
	 * Table name without prefix.
	 *
	 * @var string
	 */
	protected $table_name = 'rsl_ie_job_schedules';

	/**
	 * Return columns allowed in dynamic queries.
	 *
	 * @return array
	 */
	protected function get_allowed_columns() {
		return [
			'id',
			'source_job_id',
			'last_job_id',
			'name',
			'schedule_type',
			'recurrence',
			'status',
			'start_at_gmt',
			'next_run_gmt',
			'last_run_gmt',
			'created_by',
			'created_at',
			'updated_at',
		];
	}

	/**
	 * Create a schedule.
	 *
	 * @param array $data Schedule data.
	 * @return int|\WP_Error
	 */
	public function create( $data ) {
		$now  = current_time( 'mysql', true );
		$data = wp_parse_args(
			$data,
			[
				'last_job_id'  => null,
				'status'       => 'active',
				'created_by'   => get_current_user_id(),
				'created_at'   => $now,
				'updated_at'   => $now,
				'last_run_gmt' => null,
			]
		);

		return $this->insert( $data );
	}

	/**
	 * Return schedules with source and last-run job information.
	 *
	 * @return array
	 */
	public function get_with_jobs() {
		global $wpdb;

		$schedules_table = $wpdb->prefix . 'rsl_ie_job_schedules';
		$jobs_table      = $wpdb->prefix . 'rsl_ie_jobs';

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Admin schedule list requires joined custom-table data.
			$wpdb->prepare(
				'SELECT schedules.*, source_job.type AS source_type, source_job.data_type AS source_data_type,
					source_job.status AS source_status, last_job.status AS last_job_status
				FROM %i AS schedules
				LEFT JOIN %i AS source_job ON source_job.id = schedules.source_job_id
				LEFT JOIN %i AS last_job ON last_job.id = schedules.last_job_id
				ORDER BY schedules.id DESC',
				$schedules_table,
				$jobs_table,
				$jobs_table
			)
		);
	}
}

<?php
/**
 * Schedule workflow contracts.
 *
 * @package RockStarLab\ImportExport\Abilities
 */

namespace RockStarLab\ImportExport\Abilities;

defined( 'ABSPATH' ) || exit;

/** Shared closed schemas for schedules. */
class Schedule_Catalog {
	/**
	 * Return schedule contracts.
	 *
	 * @return array
	 */
	public static function definitions() {
		$s           = [ 'type' => 'string' ];
		$n           = Workflow_Schemas::integer();
		$id          = Workflow_Schemas::integer( 1 );
		$row         = Workflow_Schemas::object(
			[
				'id'            => $id,
				'source_job_id' => $id,
				'last_job_id'   => $n,
				'name'          => $s,
				'schedule_type' => $s,
				'recurrence'    => $s,
				'status'        => $s,
				'start_at_gmt'  => $s,
				'next_run_gmt'  => [ 'type' => [ 'string', 'null' ] ],
				'last_run_gmt'  => [ 'type' => [ 'string', 'null' ] ],
				'timezone'      => $s,
				'error_code'    => $s,
			],
			[ 'id', 'source_job_id', 'last_job_id', 'name', 'schedule_type', 'recurrence', 'status', 'start_at_gmt', 'next_run_gmt', 'last_run_gmt', 'timezone', 'error_code' ]
		);
		$fields      = [
			'source_job_id' => $id,
			'name'          => $s + [ 'maxLength' => 190 ],
			'schedule_type' => $s + [ 'enum' => [ 'once', 'recurring' ] ],
			'recurrence'    => $s + [ 'enum' => [ 'once', 'hourly', 'twicedaily', 'daily', 'weekly' ] ],
			'start_at'      => $s + [ 'pattern' => '^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}$' ],
			'timezone'      => $s + [ 'maxLength' => 100 ],
			'status'        => $s + [ 'enum' => [ 'active', 'paused' ] ],
		];
		$page        = [
			'page'     => Workflow_Schemas::integer( 1, 100000 ),
			'per_page' => Workflow_Schemas::integer( 1, 100 ),
		];
		$definitions = [];
		foreach ( [
			'schedule-options'          => [
				Schema_Registry::empty_input(),
				Workflow_Schemas::object(
					[
						'timezone'    => $s,
						'now'         => $s,
						'recurrences' => Workflow_Schemas::items(
							Workflow_Schemas::object(
								[
									'value'    => $s,
									'interval' => $n,
								],
								[ 'value', 'interval' ]
							)
						),
					],
					[ 'timezone', 'now', 'recurrences' ]
				),
				true,
			],
			'schedule-list'             => [
				Workflow_Schemas::object( $page ),
				Workflow_Schemas::object(
					[
						'schedules' => Workflow_Schemas::items( $row ),
						'more'      => [ 'type' => 'boolean' ],
					],
					[ 'schedules', 'more' ]
				),
				true,
			],
			'schedule-get'              => [ Workflow_Schemas::object( [ 'schedule_id' => $id ], [ 'schedule_id' ] ), $row, true ],
			'schedule-create'           => [ Workflow_Schemas::object( $fields, [ 'source_job_id', 'start_at' ] ), $row, false ],
			'schedule-update'           => [ Workflow_Schemas::object( [ 'schedule_id' => $id ] + $fields, [ 'schedule_id' ] ), $row, false ],
			'schedule-delete'           => [
				Workflow_Schemas::object( [ 'schedule_id' => $id ], [ 'schedule_id' ] ),
				Workflow_Schemas::object(
					[
						'deleted'     => [ 'type' => 'boolean' ],
						'schedule_id' => $id,
					],
					[ 'deleted', 'schedule_id' ]
				),
				false,
			],
			'schedule-list-source-jobs' => [
				Workflow_Schemas::object( $page + [ 'q' => $s + [ 'maxLength' => 200 ] ] ),
				Workflow_Schemas::object(
					[
						'results'    => Workflow_Schemas::items(
							Workflow_Schemas::object(
								[
									'id'          => $id,
									'text'        => $s,
									'schedulable' => [ 'type' => 'boolean' ],
									'error_code'  => $s,
								],
								[ 'id', 'text', 'schedulable', 'error_code' ]
							)
						),
						'pagination' => Workflow_Schemas::object( [ 'more' => [ 'type' => 'boolean' ] ], [ 'more' ] ),
					],
					[ 'results', 'pagination' ]
				),
				true,
			],
		] as $name => $contract ) {
			$definitions[ $name ] = [
				'label'       => ucwords( str_replace( '-', ' ', $name ) ),
				'description' => 'Manage native job schedules. start_at is a future local minute in timezone (defaults to WordPress). Recurrences are fixed elapsed intervals, not wall-clock DST rules. Updates preserve omitted fields; set a future start_at to reactivate an elapsed schedule. Each occurrence creates a fresh authorized job as the schedule owner, with live provider and transformation checks. Source parameters are read at run time. No credentials or job parameters are returned. Cron requires site traffic or host cron.',
				'input'       => $contract[0],
				'output'      => $contract[1],
				'readonly'    => $contract[2],
				'destructive' => 'schedule-delete' === $name,
				'idempotent'  => $contract[2],
			];
		}
		return $definitions;
	}
}

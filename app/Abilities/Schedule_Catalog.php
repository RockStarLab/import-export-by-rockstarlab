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
		$s           = array( 'type' => 'string' );
		$n           = Workflow_Schemas::integer();
		$id          = Workflow_Schemas::integer( 1 );
		$row         = Workflow_Schemas::object(
			array(
				'id'            => $id,
				'source_job_id' => $id,
				'last_job_id'   => $n,
				'name'          => $s,
				'schedule_type' => $s,
				'recurrence'    => $s,
				'status'        => $s,
				'start_at_gmt'  => $s,
				'next_run_gmt'  => array( 'type' => array( 'string', 'null' ) ),
				'last_run_gmt'  => array( 'type' => array( 'string', 'null' ) ),
				'timezone'      => $s,
				'error_code'    => $s,
				'error_message' => $s,
				'error_gmt'     => $s,
				'changed_only'  => array( 'type' => 'boolean' ),
			),
			array( 'id', 'source_job_id', 'last_job_id', 'name', 'schedule_type', 'recurrence', 'status', 'start_at_gmt', 'next_run_gmt', 'last_run_gmt', 'timezone', 'error_code', 'changed_only' )
		);
		$fields      = array(
			'source_job_id' => $id,
			'name'          => $s + array( 'maxLength' => 190 ),
			'schedule_type' => $s + array( 'enum' => array( 'once', 'recurring' ) ),
			'recurrence'    => $s + array( 'enum' => array( 'once', 'hourly', 'twicedaily', 'daily', 'weekly' ) ),
			'start_at'      => $s + array( 'pattern' => '^\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}$' ),
			'timezone'      => $s + array( 'maxLength' => 100 ),
			'status'        => $s + array( 'enum' => array( 'active', 'paused' ) ),
			'changed_only'  => array(
				'type'        => 'boolean',
				'description' => 'Export sources only: each run after the first exports only posts, pages or WooCommerce orders modified since the previous completed run started.',
			),
		);
		$page        = array(
			'page'     => Workflow_Schemas::integer( 1, 100000 ),
			'per_page' => Workflow_Schemas::integer( 1, 100 ),
		);
		$definitions = array();
		foreach ( array(
			'schedule-options'          => array(
				Schema_Registry::empty_input(),
				Workflow_Schemas::object(
					array(
						'timezone'    => $s,
						'now'         => $s,
						'recurrences' => Workflow_Schemas::items(
							Workflow_Schemas::object(
								array(
									'value'    => $s,
									'interval' => $n,
								),
								array( 'value', 'interval' )
							)
						),
					),
					array( 'timezone', 'now', 'recurrences' )
				),
				true,
			),
			'schedule-list'             => array(
				Workflow_Schemas::object( $page ),
				Workflow_Schemas::object(
					array(
						'schedules' => Workflow_Schemas::items( $row ),
						'more'      => array( 'type' => 'boolean' ),
					),
					array( 'schedules', 'more' )
				),
				true,
			),
			'schedule-get'              => array( Workflow_Schemas::object( array( 'schedule_id' => $id ), array( 'schedule_id' ) ), $row, true ),
			'schedule-create'           => array( Workflow_Schemas::object( $fields, array( 'source_job_id', 'start_at' ) ), $row, false ),
			'schedule-update'           => array( Workflow_Schemas::object( array( 'schedule_id' => $id ) + $fields, array( 'schedule_id' ) ), $row, false ),
			'schedule-delete'           => array(
				Workflow_Schemas::object( array( 'schedule_id' => $id ), array( 'schedule_id' ) ),
				Workflow_Schemas::object(
					array(
						'deleted'     => array( 'type' => 'boolean' ),
						'schedule_id' => $id,
					),
					array( 'deleted', 'schedule_id' )
				),
				false,
			),
			'schedule-list-source-jobs' => array(
				Workflow_Schemas::object( $page + array( 'q' => $s + array( 'maxLength' => 200 ) ) ),
				Workflow_Schemas::object(
					array(
						'results'    => Workflow_Schemas::items(
							Workflow_Schemas::object(
								array(
									'id'          => $id,
									'text'        => $s,
									'schedulable' => array( 'type' => 'boolean' ),
									'error_code'  => $s,
								),
								array( 'id', 'text', 'schedulable', 'error_code' )
							)
						),
						'pagination' => Workflow_Schemas::object( array( 'more' => array( 'type' => 'boolean' ) ), array( 'more' ) ),
					),
					array( 'results', 'pagination' )
				),
				true,
			),
		) as $name => $contract ) {
			$lead                 = array(
				'schedule-options'          => 'Read what a schedule accepts: the site timezone, the current local time and the recurrences with their intervals.',
				'schedule-list'             => 'List the job schedules with their status, next run and last job.',
				'schedule-get'              => 'Read one job schedule.',
				'schedule-create'           => 'Create a schedule that runs a finished import or export job again, once or at a fixed interval.',
				'schedule-update'           => 'Change a schedule (name, timing, recurrence or status).',
				'schedule-delete'           => 'Delete a schedule. Jobs it already ran stay in Jobs Log.',
				'schedule-list-source-jobs' => 'List the jobs a schedule can be made from, with whether each one can be scheduled.',
			);
			$definitions[ $name ] = array(
				'label'       => ucwords( str_replace( '-', ' ', $name ) ),
				'description' => ( $lead[ $name ] ?? '' ) . ' Job schedules: start_at is a future local minute in timezone (defaults to WordPress). Recurrences are fixed elapsed intervals, not wall-clock DST rules. Updates preserve omitted fields; set a future start_at to reactivate an elapsed schedule. Each occurrence creates a fresh authorized job as the schedule owner, with live provider and transformation checks. Source parameters are read at run time. No credentials or job parameters are returned. Cron requires site traffic or host cron.',
				'input'       => $contract[0],
				'output'      => $contract[1],
				'readonly'    => $contract[2],
				'destructive' => 'schedule-delete' === $name,
				'idempotent'  => $contract[2],
			);
		}
		return $definitions;
	}
}

<?php
/**
 * UpdraftPlus tools with closed request and response contracts.
 *
 * @package RockStarLab\ImportExport\Abilities
 */

namespace RockStarLab\ImportExport\Abilities;

defined( 'ABSPATH' ) || exit;

/** UpdraftPlus tools with closed request and response contracts. */
class UpdraftPlus_Catalog {
	/**
	 * Return the reviewed closed schemas and transport annotations.
	 *
	 * @return array
	 */
	public static function definitions() {
		$str        = array( 'type' => 'string' );
		$bool       = array( 'type' => 'boolean' );
		$int        = array(
			'type'    => 'integer',
			'minimum' => 0,
		);
		$id         = array(
			'type'    => 'string',
			'pattern' => '^[0-9]{1,12}$',
		);
		$job        = array(
			'type'    => 'string',
			'pattern' => '^[a-f0-9]{32}$',
		);
		$components = array(
			'type'        => 'array',
			'minItems'    => 1,
			'maxItems'    => 5,
			'uniqueItems' => true,
			'items'       => array(
				'type' => 'string',
				'enum' => array( 'db', 'uploads', 'themes', 'plugins', 'others' ),
			),
		);
		$backup     = Schema_Registry::object(
			array(
				'backup_id'           => $id,
				'created_at'          => $int,
				'components'          => Schema_Registry::strings(),
				'archive_count'       => $int,
				'local_archive_count' => $int,
				'size_bytes'          => $int,
				'result'              => $str,
				'remote_storage'      => Schema_Registry::strings(),
			)
		);
		$operation  = Schema_Registry::object(
			array(
				'job_id'        => $job,
				'type'          => $str,
				'status'        => $str,
				'stage'         => $str,
				'created_at'    => $int,
				'updated_at'    => $int,
				'backup_id'     => $str,
				'native_job_id' => $str,
				'error_code'    => $str,
				'message'       => $str,
				'recovery_url'  => $str,
			)
		);
		$request    = array(
			'type'      => 'string',
			'minLength' => 8,
			'maxLength' => 100,
			'pattern'   => '^[A-Za-z0-9_-]+$',
		);
		$plan       = Schema_Registry::object(
			array(
				'plan_id'             => $job,
				'confirmation_token'  => array(
					'type'    => 'string',
					'pattern' => '^[a-f0-9]{64}$',
				),
				'expires_at'          => $int,
				'backup_id'           => $id,
				'components'          => $components,
				'required_free_bytes' => $int,
				'warning'             => $str,
			)
		);
		$specs      = array(
			'updraftplus-status'         => array(
				'Read UpdraftPlus status',
				'Read availability, native activity and backup integration limits. Does not start a backup.',
				array(),
				array(),
				Schema_Registry::object(
					array(
						'version'           => $str,
						'native_busy'       => $bool,
						'cron_disabled'     => $bool,
						'restore_supported' => $bool,
						'limitations'       => Schema_Registry::strings(),
					)
				),
				true,
			),
			'updraftplus-backup-list'    => array(
				'List UpdraftPlus backups',
				'List backup metadata. History alone does not prove successful completion.',
				array(
					'offset' => $int,
					'limit'  => array(
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 100,
					),
				),
				array(),
				Schema_Registry::object(
					array(
						'backups' => array(
							'type'  => 'array',
							'items' => $backup,
						),
						'total'   => $int,
					)
				),
				true,
			),
			'updraftplus-backup-get'     => array( 'Read UpdraftPlus backup', 'Inspect one backup set without exposing paths or credentials.', array( 'backup_id' => $id ), array( 'backup_id' ), $backup, true ),
			'updraftplus-backup-start'   => array(
				'Create UpdraftPlus backup',
				'Queue a native backup. Poll backup-status until completed. Reuse request_id only for an identical request.',
				array(
					'request_id'     => $request,
					'components'     => $components,
					'send_to_remote' => $bool,
				),
				array( 'request_id', 'components' ),
				$operation,
				false,
			),
			'updraftplus-backup-status'  => array( 'Read backup operation', 'Read an operation created by this user. Queued or unknown is not success.', array( 'job_id' => $job ), array( 'job_id' ), $operation, true ),
			'updraftplus-restore-plan'   => array(
				'Prepare backup restoration',
				'Validate local archives and create an expiring user-bound plan. Does not restore data. Show the warning and selected components to the user before requesting confirmation.',
				array(
					'backup_id'  => $id,
					'components' => $components,
				),
				array( 'backup_id', 'components' ),
				$plan,
				false,
			),
			'updraftplus-restore-start'  => array(
				'Restore confirmed backup',
				'Overwrite selected site components using the exact confirmed plan. Requires explicit user approval; newer data may be lost. Poll restore-status; connection loss is not success.',
				array(
					'request_id'         => $request,
					'plan_id'            => $job,
					'confirmation_token' => array(
						'type'    => 'string',
						'pattern' => '^[a-f0-9]{64}$',
					),
					'confirm'            => array(
						'type' => 'boolean',
						'enum' => array( true ),
					),
				),
				array( 'request_id', 'plan_id', 'confirmation_token', 'confirm' ),
				$operation,
				false,
			),
			'updraftplus-restore-status' => array( 'Read restore operation', 'Read durable restore state. If access was restored to older settings, reconnect via WordPress and enable this integration again. Never infer success from a disconnected request.', array( 'job_id' => $job ), array( 'job_id' ), $operation, true ),
			'updraftplus-restore-resume' => array( 'Resume interrupted restore', 'Resume only a paused native checkpoint, without restarting completed archives. Requires the same authorized administrator.', array( 'job_id' => $job ), array( 'job_id' ), $operation, false ),
		);
		$result     = array();
		foreach ( $specs as $name => $spec ) {
			$result[ $name ] = array(
				'label'       => $spec[0],
				'description' => $spec[1],
				'input'       => array(
					'type'                 => 'object',
					'properties'           => $spec[2],
					'required'             => $spec[3],
					'additionalProperties' => false,
				),
				'output'      => $spec[4],
				'readonly'    => $spec[5],
				'destructive' => in_array( $name, array( 'updraftplus-restore-start', 'updraftplus-restore-resume' ), true ),
				'idempotent'  => $spec[5],
			);
		}
		return $result;
	}
}

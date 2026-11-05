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
		$str        = [ 'type' => 'string' ];
		$bool       = [ 'type' => 'boolean' ];
		$int        = [
			'type'    => 'integer',
			'minimum' => 0,
		];
		$id         = [
			'type'    => 'string',
			'pattern' => '^[0-9]{1,12}$',
		];
		$job        = [
			'type'    => 'string',
			'pattern' => '^[a-f0-9]{32}$',
		];
		$components = [
			'type'        => 'array',
			'minItems'    => 1,
			'maxItems'    => 5,
			'uniqueItems' => true,
			'items'       => [
				'type' => 'string',
				'enum' => [ 'db', 'uploads', 'themes', 'plugins', 'others' ],
			],
		];
		$backup     = Schema_Registry::object(
			[
				'backup_id'           => $id,
				'created_at'          => $int,
				'components'          => Schema_Registry::strings(),
				'archive_count'       => $int,
				'local_archive_count' => $int,
				'size_bytes'          => $int,
				'result'              => $str,
				'remote_storage'      => Schema_Registry::strings(),
			]
		);
		$operation  = Schema_Registry::object(
			[
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
			]
		);
		$request    = [
			'type'      => 'string',
			'minLength' => 8,
			'maxLength' => 100,
			'pattern'   => '^[A-Za-z0-9_-]+$',
		];
		$plan       = Schema_Registry::object(
			[
				'plan_id'             => $job,
				'confirmation_token'  => [
					'type'    => 'string',
					'pattern' => '^[a-f0-9]{64}$',
				],
				'expires_at'          => $int,
				'backup_id'           => $id,
				'components'          => $components,
				'required_free_bytes' => $int,
				'warning'             => $str,
			]
		);
		$specs      = [
			'updraftplus-status'         => [
				'Read UpdraftPlus status',
				'Read availability, native activity and backup integration limits. Does not start a backup.',
				[],
				[],
				Schema_Registry::object(
					[
						'version'           => $str,
						'native_busy'       => $bool,
						'cron_disabled'     => $bool,
						'restore_supported' => $bool,
						'limitations'       => Schema_Registry::strings(),
					]
				),
				true,
			],
			'updraftplus-backup-list'    => [
				'List UpdraftPlus backups',
				'List backup metadata. History alone does not prove successful completion.',
				[
					'offset' => $int,
					'limit'  => [
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 100,
					],
				],
				[],
				Schema_Registry::object(
					[
						'backups' => [
							'type'  => 'array',
							'items' => $backup,
						],
						'total'   => $int,
					]
				),
				true,
			],
			'updraftplus-backup-get'     => [ 'Read UpdraftPlus backup', 'Inspect one backup set without exposing paths or credentials.', [ 'backup_id' => $id ], [ 'backup_id' ], $backup, true ],
			'updraftplus-backup-start'   => [
				'Create UpdraftPlus backup',
				'Queue a native backup. Poll backup-status until completed. Reuse request_id only for an identical request.',
				[
					'request_id'     => $request,
					'components'     => $components,
					'send_to_remote' => $bool,
				],
				[ 'request_id', 'components' ],
				$operation,
				false,
			],
			'updraftplus-backup-status'  => [ 'Read backup operation', 'Read an operation created by this user. Queued or unknown is not success.', [ 'job_id' => $job ], [ 'job_id' ], $operation, true ],
			'updraftplus-restore-plan'   => [
				'Prepare backup restoration',
				'Validate local archives and create an expiring user-bound plan. Does not restore data. Show the warning and selected components to the user before requesting confirmation.',
				[
					'backup_id'  => $id,
					'components' => $components,
				],
				[ 'backup_id', 'components' ],
				$plan,
				false,
			],
			'updraftplus-restore-start'  => [
				'Restore confirmed backup',
				'Overwrite selected site components using the exact confirmed plan. Requires explicit user approval; newer data may be lost. Poll restore-status; connection loss is not success.',
				[
					'request_id'         => $request,
					'plan_id'            => $job,
					'confirmation_token' => [
						'type'    => 'string',
						'pattern' => '^[a-f0-9]{64}$',
					],
					'confirm'            => [
						'type' => 'boolean',
						'enum' => [ true ],
					],
				],
				[ 'request_id', 'plan_id', 'confirmation_token', 'confirm' ],
				$operation,
				false,
			],
			'updraftplus-restore-status' => [ 'Read restore operation', 'Read durable restore state. If access was restored to older settings, reconnect via WordPress and enable this integration again. Never infer success from a disconnected request.', [ 'job_id' => $job ], [ 'job_id' ], $operation, true ],
			'updraftplus-restore-resume' => [ 'Resume interrupted restore', 'Resume only a paused native checkpoint, without restarting completed archives. Requires the same authorized administrator.', [ 'job_id' => $job ], [ 'job_id' ], $operation, false ],
		];
		$result     = [];
		foreach ( $specs as $name => $spec ) {
			$result[ $name ] = [
				'label'       => $spec[0],
				'description' => $spec[1],
				'input'       => [
					'type'                 => 'object',
					'properties'           => $spec[2],
					'required'             => $spec[3],
					'additionalProperties' => false,
				],
				'output'      => $spec[4],
				'readonly'    => $spec[5],
				'destructive' => in_array( $name, [ 'updraftplus-restore-start', 'updraftplus-restore-resume' ], true ),
				'idempotent'  => $spec[5],
			];
		}
		return $result;
	}
}

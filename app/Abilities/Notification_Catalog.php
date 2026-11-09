<?php
/**
 * Job notification and row error ability contracts.
 *
 * @package RockStarLab\ImportExport\Abilities
 */

namespace RockStarLab\ImportExport\Abilities;

defined( 'ABSPATH' ) || exit;

/** Closed schemas for notifications and readable import errors. */
class Notification_Catalog {
	/**
	 * Return contracts.
	 *
	 * @return array
	 */
	public static function definitions() {
		$s        = Workflow_Schemas::string();
		$n        = Workflow_Schemas::integer();
		$b        = [ 'type' => 'boolean' ];
		$settings = Workflow_Schemas::object(
			[
				'enabled'        => $b,
				'when'           => $s,
				'job_types'      => Workflow_Schemas::items( $s, 10 ),
				'email'          => Workflow_Schemas::value(),
				'slack'          => Workflow_Schemas::value(),
				'webhook'        => Workflow_Schemas::value(),
				'subject'        => $s,
				'message'        => $s,
				'placeholders'   => Workflow_Schemas::items( $s, 50 ),
				'webhook_secret' => $s,
			],
			[ 'enabled', 'when', 'job_types', 'email', 'slack', 'webhook', 'subject', 'message', 'placeholders' ]
		);
		$update   = Workflow_Schemas::object(
			[
				'enabled'           => $b,
				'when'              => Workflow_Schemas::string( [ 'always', 'errors' ] ),
				'job_types'         => Workflow_Schemas::items( Workflow_Schemas::string( [ 'import', 'export', 'sync', 'migration' ] ), 4 ),
				'email'             => Workflow_Schemas::object(
					[
						'enabled'    => $b,
						'recipients' => Workflow_Schemas::items(
							[
								'type'      => 'string',
								'maxLength' => 254,
							],
							10
						),
					]
				),
				'slack'             => Workflow_Schemas::object(
					[
						'enabled'   => $b,
						'url'       => [
							'type'      => 'string',
							'maxLength' => 500,
						],
						'clear_url' => $b,
					]
				),
				'webhook'           => Workflow_Schemas::object(
					[
						'enabled'   => $b,
						'url'       => [
							'type'      => 'string',
							'maxLength' => 2000,
						],
						'clear_url' => $b,
					]
				),
				'regenerate_secret' => $b,
				'subject'           => [
					'type'      => 'string',
					'maxLength' => 200,
				],
				'message'           => [
					'type'      => 'string',
					'maxLength' => 2000,
				],
			]
		);
		$result   = Workflow_Schemas::object(
			[
				'ok'     => $b,
				'detail' => $s,
				'retry'  => $b,
			],
			[ 'ok', 'detail' ]
		);
		$error    = Workflow_Schemas::object(
			[
				'row'     => $n,
				'message' => $s,
				'code'    => $s,
				'field'   => $s,
				'hint'    => $s,
			],
			[ 'row', 'message', 'field', 'hint' ]
		);
		$list     = [
			'notification-settings-get'    => [
				'Read the notification settings for finished jobs: on/off, when (always, or only when a job failed or had failed rows), job types, email recipients, whether Slack and webhook URLs are set (masked), and the message template with its placeholders.',
				Schema_Registry::empty_input(),
				$settings,
				true,
				false,
			],
			'notification-settings-update' => [
				'Change notification settings; omitted fields keep their values. Slack takes an incoming webhook URL (https://hooks.slack.com/...). The webhook gets a JSON POST signed with HMAC-SHA256 (header X-RSL-Signature: sha256=HMAC(secret, timestamp + "." + body), X-RSL-Timestamp); a new signing secret is returned once in webhook_secret when it is created or regenerated. Webhook URLs must be public https addresses. Jobs that finished before notifications were turned on are not reported.',
				$update,
				$settings,
				false,
				false,
			],
			'notification-test'            => [
				'Send a sample notification to every enabled channel now and return the result per channel (sent, or the HTTP status). Counts toward the limit of 30 notifications per hour.',
				Schema_Registry::empty_input(),
				Workflow_Schemas::object( [ 'results' => Workflow_Schemas::value() ], [ 'results' ] ),
				false,
				false,
			],
			'notification-log'             => [
				'Read the last 30 notification deliveries: time (UTC), job_id (0 for tests), channel, ok, detail (sent, HTTP status, rate_limited) and attempt. No addresses or URLs are returned.',
				Schema_Registry::empty_input(),
				Workflow_Schemas::object(
					[
						'entries' => Workflow_Schemas::items( Workflow_Schemas::value(), 30 ),
					],
					[ 'entries' ]
				),
				true,
				false,
			],
			'job-errors'                   => [
				'Read the failed rows of an import job: row number in the file, field, error and how to fix it. With csv=true also returns csv: the failed rows with their values under the WordPress field names, ready to correct and import again (Auto Map matches the columns).',
				Workflow_Schemas::object(
					[
						'job_id' => Workflow_Schemas::integer( 1 ),
						'limit'  => Workflow_Schemas::integer( 1, 500 ),
						'offset' => $n,
						'csv'    => $b,
					],
					[ 'job_id' ]
				),
				Workflow_Schemas::object(
					[
						'job_id'       => $n,
						'status'       => $s,
						'errors_total' => $n,
						'errors'       => Workflow_Schemas::items( $error, 500 ),
						'file_name'    => $s,
						'csv'          => $s,
					],
					[ 'job_id', 'status', 'errors_total', 'errors' ]
				),
				true,
				false,
			],
		];
		$out      = [];
		foreach ( $list as $key => $contract ) {
			$out[ $key ] = [
				'label'       => ucwords( str_replace( '-', ' ', $key ) ),
				'description' => $contract[0],
				'input'       => $contract[1],
				'output'      => $contract[2],
				'readonly'    => $contract[3],
				'destructive' => $contract[4],
				'idempotent'  => $contract[3],
			];
		}
		$out['notification-test']['output'] = Workflow_Schemas::object( [ 'results' => Workflow_Schemas::value() ], [ 'results' ] );
		unset( $result );
		return $out;
	}
}

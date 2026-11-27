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
		$b        = array( 'type' => 'boolean' );
		$settings = Workflow_Schemas::object(
			array(
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
			),
			array( 'enabled', 'when', 'job_types', 'email', 'slack', 'webhook', 'subject', 'message', 'placeholders' )
		);
		$update   = Workflow_Schemas::object(
			array(
				'enabled'           => $b,
				'when'              => Workflow_Schemas::string( array( 'always', 'errors' ) ),
				'job_types'         => Workflow_Schemas::items( Workflow_Schemas::string( array( 'import', 'export', 'sync', 'migration' ) ), 4 ),
				'email'             => Workflow_Schemas::object(
					array(
						'enabled'    => $b,
						'recipients' => Workflow_Schemas::items(
							array(
								'type'      => 'string',
								'maxLength' => 254,
							),
							10
						),
					)
				),
				'slack'             => Workflow_Schemas::object(
					array(
						'enabled'   => $b,
						'url'       => array(
							'type'      => 'string',
							'maxLength' => 500,
						),
						'clear_url' => $b,
					)
				),
				'webhook'           => Workflow_Schemas::object(
					array(
						'enabled'   => $b,
						'url'       => array(
							'type'      => 'string',
							'maxLength' => 2000,
						),
						'clear_url' => $b,
					)
				),
				'regenerate_secret' => $b,
				'subject'           => array(
					'type'      => 'string',
					'maxLength' => 200,
				),
				'message'           => array(
					'type'      => 'string',
					'maxLength' => 2000,
				),
			)
		);
		$result   = Workflow_Schemas::object(
			array(
				'ok'     => $b,
				'detail' => $s,
				'retry'  => $b,
			),
			array( 'ok', 'detail' )
		);
		$error    = Workflow_Schemas::object(
			array(
				'row'     => $n,
				'message' => $s,
				'code'    => $s,
				'field'   => $s,
				'hint'    => $s,
			),
			array( 'row', 'message', 'field', 'hint' )
		);
		$list     = array(
			'notification-settings-get'    => array(
				'Read the notification settings for finished jobs: on/off, when (always, or only when a job failed or had failed rows), job types, email recipients, whether Slack and webhook URLs are set (masked), and the message template with its placeholders.',
				Schema_Registry::empty_input(),
				$settings,
				true,
				false,
			),
			'notification-settings-update' => array(
				'Change notification settings; omitted fields keep their values. Slack takes an incoming webhook URL (https://hooks.slack.com/...). The webhook gets a JSON POST signed with HMAC-SHA256 (header X-RSL-Signature: sha256=HMAC(secret, timestamp + "." + body), X-RSL-Timestamp); a new signing secret is returned once in webhook_secret when it is created or regenerated. Webhook URLs must be public https addresses. Jobs that finished before notifications were turned on are not reported.',
				$update,
				$settings,
				false,
				false,
			),
			'notification-test'            => array(
				'Send a sample notification to every enabled channel now and return the result per channel (sent, or the HTTP status). Counts toward the limit of 30 notifications per hour.',
				Schema_Registry::empty_input(),
				Workflow_Schemas::object( array( 'results' => Workflow_Schemas::value() ), array( 'results' ) ),
				false,
				false,
			),
			'notification-log'             => array(
				'Read the last 30 notification deliveries: time (UTC), job_id (0 for tests), channel, ok, detail (sent, HTTP status, rate_limited) and attempt. No addresses or URLs are returned.',
				Schema_Registry::empty_input(),
				Workflow_Schemas::object(
					array(
						'entries' => Workflow_Schemas::items( Workflow_Schemas::value(), 30 ),
					),
					array( 'entries' )
				),
				true,
				false,
			),
			'notification-n8n-workflow'    => array(
				'Return a ready n8n workflow (workflow_json is the exact text to save as a .json file and import in n8n; workflow is the same data as an object) that receives the job-finished webhook at the path rsl-job-finished and checks the X-RSL-Signature header. The user replaces PASTE-THE-SIGNING-SECRET-HERE with the signing secret, sets NODE_FUNCTION_ALLOW_BUILTIN=crypto in n8n, activates the workflow and saves its production webhook URL as the notification webhook.',
				Schema_Registry::empty_input(),
				Workflow_Schemas::object(
					array(
						'workflow'      => Workflow_Schemas::value(),
						'workflow_json' => Workflow_Schemas::string(),
					),
					array( 'workflow', 'workflow_json' )
				),
				true,
				false,
			),
			'job-errors'                   => array(
				'Read the failed rows of an import job: row number in the file, field, error and how to fix it. With csv=true also returns csv: the failed rows with their values under the WordPress field names, ready to correct and import again (Auto Map matches the columns).',
				Workflow_Schemas::object(
					array(
						'job_id' => Workflow_Schemas::integer( 1 ),
						'limit'  => Workflow_Schemas::integer( 1, 500 ),
						'offset' => $n,
						'csv'    => $b,
					),
					array( 'job_id' )
				),
				Workflow_Schemas::object(
					array(
						'job_id'       => $n,
						'status'       => $s,
						'errors_total' => $n,
						'errors'       => Workflow_Schemas::items( $error, 500 ),
						'file_name'    => $s,
						'csv'          => $s,
					),
					array( 'job_id', 'status', 'errors_total', 'errors' )
				),
				true,
				false,
			),
		);
		$out      = array();
		foreach ( $list as $key => $contract ) {
			$out[ $key ] = array(
				'label'       => ucwords( str_replace( '-', ' ', $key ) ),
				'description' => $contract[0],
				'input'       => $contract[1],
				'output'      => $contract[2],
				'readonly'    => $contract[3],
				'destructive' => $contract[4],
				'idempotent'  => $contract[3],
			);
		}
		$out['notification-test']['output'] = Workflow_Schemas::object( array( 'results' => Workflow_Schemas::value() ), array( 'results' ) );
		unset( $result );
		return $out;
	}
}

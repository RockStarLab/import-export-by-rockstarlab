<?php
/**
 * Event trigger ability contracts.
 *
 * @package RockStarLab\ImportExport\Abilities
 */

namespace RockStarLab\ImportExport\Abilities;

defined( 'ABSPATH' ) || exit;

/** Closed schemas for triggers that run a saved import or export on an event. */
class Trigger_Catalog {
	/**
	 * Return contracts.
	 *
	 * @return array
	 */
	public static function definitions() {
		$s       = Workflow_Schemas::string();
		$n       = Workflow_Schemas::integer();
		$b       = [ 'type' => 'boolean' ];
		$id      = [
			'type'    => 'string',
			'pattern' => '^trg_[a-f0-9]{12}$',
		];
		$by_id   = Workflow_Schemas::object( [ 'trigger_id' => $id ], [ 'trigger_id' ] );
		$events  = Workflow_Schemas::string( [ 'webhook', 'post_published', 'woo_order_created', 'file_changed' ] );
		$types   = Workflow_Schemas::items(
			[
				'type'      => 'string',
				'maxLength' => 20,
			],
			20
		);
		$delay   = Workflow_Schemas::integer( 0, 3600 );
		$name    = [
			'type'      => 'string',
			'maxLength' => 100,
		];
		$trigger = Workflow_Schemas::object(
			[
				'trigger_id'    => $s,
				'name'          => $s,
				'event'         => $s,
				'source_job_id' => $n,
				'source'        => $s,
				'source_type'   => $s,
				'post_types'    => Workflow_Schemas::items( $s, 20 ),
				'delay'         => $n,
				'status'        => $s,
				'webhook_url'   => $s,
				'pending'       => $b,
				'last_run_at'   => $s,
				'last_job_id'   => $n,
				'last_result'   => $s,
				'runs'          => $n,
				'allow_file_url' => $b,
				'created_at'    => $s,
				'token'         => $s,
			],
			[ 'trigger_id', 'name', 'event', 'source_job_id', 'status' ]
		);
		$list    = [
			'trigger-sources' => [
				'List finished imports and exports a trigger can run again (newest 50): job_id, label, type, content_type, and from_url (true when the import downloads its file from a URL, needed for file_changed).',
				Schema_Registry::empty_input(),
				Workflow_Schemas::object( [ 'sources' => Workflow_Schemas::items( Workflow_Schemas::value(), 50 ) ], [ 'sources' ] ),
				true,
				false,
			],
			'trigger-list'    => [
				'List triggers: what starts each one (event), the job it runs again (source_job_id), status (active or paused), the webhook URL for webhook triggers, and the last run. Tokens are never returned here.',
				Schema_Registry::empty_input(),
				Workflow_Schemas::object( [ 'triggers' => Workflow_Schemas::items( $trigger, 50 ) ], [ 'triggers' ] ),
				true,
				false,
			],
			'trigger-get'     => [
				'Read one trigger.',
				$by_id,
				$trigger,
				true,
				false,
			],
			'trigger-create'  => [
				'Create a trigger that runs a fresh copy of a finished import or export (source_job_id) when an event happens. Events: webhook (an external system POSTs to webhook_url with the token in the X-RSL-Token header or as "Authorization: Bearer <token>"; the token is returned once, in token), post_published (a post of post_types is published; not fired by the plugin\'s own imports), woo_order_created (a WooCommerce order is created), file_changed (the URL file of the import changed; checked hourly, the first check only records the file). delay (seconds, post and order events, default 60) groups many events into one run. With allow_file_url=true (webhook triggers of imports only) the caller may send a JSON body {"file_url": "https://..."} to import that file once instead of the source job file; columns are matched by name and a file missing a mapped column is refused (422). The webhook answers 202 with job_id; GET <webhook_url without /run>/jobs/<job_id> with the same token returns the status and counts of that job. A trigger never starts a job while its previous job still runs, and webhook runs are limited to one a minute. Up to 50 triggers.',
				Workflow_Schemas::object(
					[
						'name'          => $name,
						'source_job_id' => Workflow_Schemas::integer( 1 ),
						'event'         => $events,
						'post_types'    => $types,
						'delay'         => $delay,
						'allow_file_url' => $b,
					],
					[ 'source_job_id', 'event' ]
				),
				$trigger,
				false,
				false,
			],
			'trigger-update'  => [
				'Rename a trigger, pause or resume it (status active or paused), change post_types and delay, or let a webhook import trigger accept a file URL in the request (allow_file_url). The event and the source job cannot be changed: create a new trigger instead.',
				Workflow_Schemas::object(
					[
						'trigger_id' => $id,
						'name'       => $name,
						'status'     => Workflow_Schemas::string( [ 'active', 'paused' ] ),
						'post_types' => $types,
						'delay'      => $delay,
						'allow_file_url' => $b,
					],
					[ 'trigger_id' ]
				),
				$trigger,
				false,
				false,
			],
			'trigger-delete'  => [
				'Delete a trigger. Its webhook URL stops working at once. Jobs it already started are kept.',
				$by_id,
				Workflow_Schemas::object(
					[
						'deleted'    => $b,
						'trigger_id' => $s,
					],
					[ 'deleted', 'trigger_id' ]
				),
				false,
				true,
			],
			'trigger-run'     => [
				'Run a trigger now, as a test, even when it is paused. Returns the new job_id; follow it with job-get. Refused (409) while the previous job of the trigger still runs, and (429) within a minute of the last run.',
				$by_id,
				Workflow_Schemas::object(
					[
						'job_id'     => $n,
						'trigger_id' => $s,
					],
					[ 'job_id', 'trigger_id' ]
				),
				false,
				false,
			],
			'trigger-rotate-token' => [
				'Create a new token for a webhook trigger; the old token stops working at once. The new token is returned once, in token.',
				$by_id,
				$trigger,
				false,
				false,
			],
			'trigger-n8n-workflow' => [
				'Return a ready n8n workflow (workflow_json is the exact text to save as a .json file and import in n8n; workflow is the same data as an object) for a webhook trigger: POST to the trigger URL, wait 30 seconds, read the job status from the trigger jobs URL and repeat until finished. The token is a placeholder (PASTE-THE-TRIGGER-TOKEN-HERE) that the user replaces in both HTTP Request nodes.',
				$by_id,
				Workflow_Schemas::object( [ 'workflow' => Workflow_Schemas::value(), 'workflow_json' => Workflow_Schemas::string() ], [ 'workflow', 'workflow_json' ] ),
				true,
				false,
			],
			'trigger-log'     => [
				'Read the last 50 trigger runs and refusals: time (UTC), trigger_id, name, reason (webhook, event, file, manual), ok, job_id and detail (started, or why it did not start, such as trigger_overlap or trigger_too_soon). Failed webhook calls with a wrong token are not logged.',
				Schema_Registry::empty_input(),
				Workflow_Schemas::object( [ 'entries' => Workflow_Schemas::items( Workflow_Schemas::value(), 50 ) ], [ 'entries' ] ),
				true,
				false,
			],
		];
		$out     = [];
		foreach ( $list as $key => $contract ) {
			$out[ $key ] = [
				'label'       => ucwords( str_replace( '-', ' ', $key ) ),
				'description' => $contract[0],
				'input'       => $contract[1],
				'output'      => $contract[2],
				'readonly'    => $contract[3],
				'destructive' => $contract[4],
				'idempotent'  => $contract[3] || 'trigger-delete' === $key,
			];
		}
		return $out;
	}
}

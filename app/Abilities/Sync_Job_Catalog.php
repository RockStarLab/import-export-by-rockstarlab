<?php
/**
 * Durable background content-sync contracts.
 *
 * @package RockStarLab\ImportExport\Abilities
 */

namespace RockStarLab\ImportExport\Abilities;

defined( 'ABSPATH' ) || exit;

/** Preserve each provider's existing selection schema. */
class Sync_Job_Catalog {
	/**
	 * Start contracts for a server-selected provider.
	 *
	 * @param array $definitions Existing synchronous provider contracts.
	 * @return array
	 */
	public static function starts( array $definitions ) {
		$output = Workflow_Schemas::object(
			[
				'job_id'             => Workflow_Schemas::integer( 1 ),
				'status'             => Workflow_Schemas::string(),
				'deduplicated'       => [ 'type' => 'boolean' ],
				'poll_after_seconds' => Workflow_Schemas::integer( 1 ),
				'scheduling_warning' => Workflow_Schemas::string(),
			],
			[ 'job_id', 'status', 'deduplicated', 'poll_after_seconds' ]
		);
		$result = [];
		foreach ( $definitions as $operation => $definition ) {
			if ( ! in_array( $operation, [ 'sync-push', 'sync-pull' ], true ) && ( ( 'standalone' !== ( $definition['handler'] ?? '' ) && empty( $definition['background'] ) ) || ! preg_match( '/-(push|pull)$/D', $operation ) ) ) {
				continue;
			}
			$selection_description             = $definition['background']['description'] ?? ( 'standalone' === ( $definition['handler'] ?? '' ) ? strstr( $definition['description'], 'Synchronous transfer', true ) : '' );
			$input                             = $definitions[ $operation ]['input'];
			$input['properties']['request_id'] = Workflow_Schemas::request_id();
			$input['required'][]               = 'request_id';
			$execution_description             = in_array( $operation, [ 'sync-media-push', 'sync-media-pull' ], true )
				? ' New standalone media jobs and children of versioned migration parents use persisted per-file chunk checkpoints. Older jobs retain their original transport. Cancellation during an unconfirmed finalization returns 409; resume resolves the same idempotent finalization. Failed jobs cannot resume but staged resources can be cleaned by cancellation when no finalization is uncertain.'
				: ' Checkpoints are queued, ready, transferring and terminal. The complete selection transfers as one unit to preserve hierarchy and cross-record mappings. Pause/resume/cancel work before transfer; a running unit returns busy. Interrupted transfers are marked uncertain and never automatically replayed. There is no per-record resume or failed-job retry yet.';
			$result[ $operation . '-start' ]   = [
				'label'       => ucwords( str_replace( '-', ' ', $operation . '-start' ) ),
				'description' => $selection_description . ' Queue the existing ' . $operation . ' selection (1–50 records) for WP-Cron. request_id deduplicates identical starts for this user/site/provider/direction; changed input conflicts. Poll rsl-ie/sync-job-status.' . $execution_description . ' Source content is read when the worker runs. Connection credentials, initiating user permissions and provider/license are rechecked. No client cookies or nonce are required by the worker.',
				'input'       => $input,
				'output'      => $output,
				'readonly'    => false,
				'destructive' => true,
				'idempotent'  => false,
			];
		}
		return $result;
	}

	/**
	 * Shared controls return only safe persisted progress and public workflow results.
	 *
	 * @return array
	 */
	public static function controls() {
		$id     = Workflow_Schemas::integer( 1 );
		$n      = Workflow_Schemas::integer();
		$s      = Workflow_Schemas::string();
		$output = Workflow_Schemas::object(
			[
				'job_id'             => $id,
				'status'             => $s,
				'stage'              => $s,
				'edition'            => $s,
				'operation'          => $s,
				'processed'          => $n,
				'total'              => $n,
				'history_job_id'     => $n,
				'error_code'         => $s,
				'uncertain'          => [ 'type' => 'boolean' ],
				'result'             => [ 'type' => [ 'object', 'null' ] ],
				'poll_after_seconds' => $id,
			],
			[ 'job_id', 'status', 'stage', 'edition', 'operation', 'processed', 'total', 'history_job_id', 'error_code', 'uncertain', 'result', 'poll_after_seconds' ]
		);
		$result = [];
		foreach ( [ 'status', 'pause', 'resume', 'cancel' ] as $action ) {
			$result[ 'sync-job-' . $action ] = [
				'label'       => 'Sync Job ' . ucfirst( $action ),
				'description' => 'Background content-sync ' . $action . '. Reads never advance execution. Mutations use the worker job lock; an active transfer returns 409. Pause/resume preserve the ready checkpoint. Cancel prevents a not-yet-started transfer. For staged media it schedules temporary-resource cleanup, with status processing until cancellation completes; uncertain finalization cannot be cancelled. Failed/completed jobs cannot resume. For uncertain results inspect the destination and, when nonzero, history_job_id before creating a new request. Standalone operations store the public result on this queue job and have history_job_id=0. Results are hidden when the provider is unavailable.',
				'input'       => Workflow_Schemas::object( [ 'job_id' => $id ], [ 'job_id' ] ),
				'output'      => $output,
				'readonly'    => 'status' === $action,
				'destructive' => 'cancel' === $action,
				'idempotent'  => true,
			];
		}
		return $result;
	}
}

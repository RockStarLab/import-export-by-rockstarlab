<?php
/**
 * Folder import ability contracts.
 *
 * @package RockStarLab\ImportExport\Abilities
 */

namespace RockStarLab\ImportExport\Abilities;

defined( 'ABSPATH' ) || exit;
/** Closed contracts shared with the folder service. */
class Media_Folder_Catalog {
	/**
	 * Folder scan filters.
	 *
	 * @return array
	 */
	public static function scan_options() {
		return Workflow_Schemas::object(
			array(
				'recursive'    => array( 'type' => 'boolean' ),
				'file_types'   => Workflow_Schemas::string( array( 'all', 'images', 'videos', 'audio', 'documents', 'custom' ) ),
				'custom_types' => Workflow_Schemas::items(
					Workflow_Schemas::string() + array(
						'pattern'   => '^\\.?[a-zA-Z0-9]+$',
						'maxLength' => 20,
					),
					100
				),
			)
		);
	}
	/**
	 * Import options from the folder UI and helper.
	 *
	 * @return array
	 */
	public static function sync_options() {
		return Workflow_Schemas::object(
			array(
				'duplicate_check'    => Workflow_Schemas::string( array( 'hash', 'filename', 'filesize' ) ),
				'duplicate_handling' => Workflow_Schemas::string( array( 'skip', 'import', 'overwrite', 'override' ) ),
				'file_operation'     => Workflow_Schemas::string( array( 'keep', 'copy', 'move' ) ),
				'batch_size'         => Workflow_Schemas::integer( 1, 100 ),
				'rml_integration'    => array( 'type' => 'boolean' ),
				'set_alt_text'       => array( 'type' => 'boolean' ),
				'alt_text'           => Workflow_Schemas::string() + array( 'maxLength' => 1000 ),
			)
		);
	}
	/**
	 * Public definitions; the batch worker remains internal.
	 *
	 * @return array
	 */
	public static function definitions() {
		$path                         = Workflow_Schemas::string() + array( 'maxLength' => 4096 );
		$n                            = Workflow_Schemas::integer();
		$b                            = array( 'type' => 'boolean' );
		$file                         = Workflow_Schemas::object(
			array(
				'path' => $path,
				'name' => $path,
				'size' => $n,
			),
			array( 'path', 'name', 'size' )
		);
		$result                       = Workflow_Schemas::object(
			array(
				'processed' => $n,
				'success'   => $n,
				'skipped'   => $n,
				'failed'    => $n,
				'errors'    => Workflow_Schemas::items( Workflow_Schemas::string(), 20 ),
				'items'     => Workflow_Schemas::items(
					Workflow_Schemas::object(
						array(
							'action'        => Workflow_Schemas::string( array( 'created', 'updated', 'skipped', 'failed' ) ),
							'file'          => $path,
							'attachment_id' => $n,
							'reason'        => Workflow_Schemas::string(),
						),
						array( 'action', 'file' )
					),
					200
				),
				'error'     => Workflow_Schemas::string(),
			),
			array( 'processed', 'success', 'skipped', 'failed', 'errors', 'items' )
		);
		$job                          = Workflow_Schemas::object(
			array(
				'job_id'             => Workflow_Schemas::integer( 1 ),
				'status'             => Workflow_Schemas::string( array( 'pending', 'processing', 'paused', 'completed', 'cancelled', 'failed' ) ),
				'progress'           => array(
					'type'    => 'number',
					'minimum' => 0,
					'maximum' => 100,
				),
				'completed'          => $b,
				'offset'             => $n,
				'total'              => $n,
				'result'             => $result,
				'poll_after_seconds' => Workflow_Schemas::integer( 1 ),
				'deduplicated'       => $b,
				'scheduling_warning' => Workflow_Schemas::string(),
			),
			array( 'job_id', 'status', 'progress', 'completed', 'offset', 'total', 'result', 'poll_after_seconds' )
		);
		$page                         = array(
			'limit'  => Workflow_Schemas::integer( 1, 500 ),
			'offset' => $n,
		);
		$defs                         = array();
		$defs['media-browse-folders'] = array(
			'input'       => Workflow_Schemas::object( array( 'path' => $path ) + $page ),
			'output'      => Workflow_Schemas::object(
				array(
					'folders'      => Workflow_Schemas::items(
						Workflow_Schemas::object(
							array(
								'name' => $path,
								'path' => $path,
							),
							array( 'name', 'path' )
						),
						500
					),
					'current_path' => $path,
					'can_go_up'    => $b,
					'parent_path'  => $path,
					'total'        => $n,
					'next_offset'  => $n,
					'complete'     => $b,
				)
			),
			'readonly'    => true,
			'description' => 'Browse directories inside this site uploads. Paths in responses are relative to uploads. Default limit 100, maximum 500. Follow next_offset until complete; directory listings may change. Symlinks escaping uploads are excluded.',
		);
		$defs['media-scan-folder']    = array(
			'input'       => Workflow_Schemas::object(
				array(
					'folder_path' => $path,
					'options'     => self::scan_options(),
				) + $page,
				array( 'folder_path' )
			),
			'output'      => Workflow_Schemas::object(
				array(
					'files'       => Workflow_Schemas::items( $file, 500 ),
					'total'       => $n,
					'next_offset' => $n,
					'complete'    => $b,
				)
			),
			'readonly'    => true,
			'description' => 'Scan uploads folder using recursive and file-type filters. Returns uploads-relative paths; pass selected paths to media-sync-start. Default limit 100, maximum 500 per response; at most 10000 matching files per scan. Select a narrower folder if the scan limit is exceeded. No content is imported.',
		);
		$defs['media-sync-start']     = array(
			'input'       => Workflow_Schemas::object(
				array(
					'folder_path'    => $path,
					'selected_files' => Workflow_Schemas::items( $path + array( 'minLength' => 1 ), 1000 ) + array(
						'minItems'    => 1,
						'uniqueItems' => true,
					),
					'scan_options'   => self::scan_options(),
					'sync_options'   => self::sync_options(),
					'request_id'     => Workflow_Schemas::string() + array(
						'pattern' => '^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$',
					),
				),
				array( 'folder_path', 'selected_files' )
			),
			'output'      => $job,
			'readonly'    => false,
			'description' => 'Queue 1–1000 explicitly selected uploads file paths. Runs without the browser via WP-Cron, using the initiator and checking current permissions. Defaults: duplicate skip by hash, keep source file, batch size 3. copy/move preserves the existing UI destination layout; move removes the source, overwrite may replace an existing attachment (keep creates a new attachment). Optional request_id deduplicates identical requests. Read status with media-sync-status; pause/resume/cancel control this domain. A crash during a file operation stops with an uncertain-result error instead of replaying it. Cron requires site traffic or host cron.',
		);
		foreach ( array( 'status', 'pause', 'resume', 'cancel' ) as $action ) {
			$defs[ 'media-sync-' . $action ] = array(
				'input'       => Workflow_Schemas::object( array( 'job_id' => Workflow_Schemas::integer( 1 ) ), array( 'job_id' ) ),
				'output'      => $job,
				'readonly'    => 'status' === $action,
				'description' => ucfirst( $action ) . ' a folder media import by job_id. Status only reads persisted progress. Pause/cancel acquire the same lock as the worker; a busy job returns 409. Resume retains the checkpoint and schedules cron. Completed/cancelled/failed jobs cannot resume; inspect partial or uncertain results before starting another selection. Result items retain the latest 200 records and errors the latest 20.',
			);
		}
		foreach ( $defs as $name => &$def ) {
			$def += array(
				'label'       => ucwords( str_replace( '-', ' ', $name ) ),
				'destructive' => 'media-sync-start' === $name || 'media-sync-cancel' === $name,
				'idempotent'  => 'media-sync-start' !== $name,
			);
		}unset( $def );
		return $defs;
	}
}

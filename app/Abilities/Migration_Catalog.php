<?php
/**
 * Durable migration package export contracts.
 *
 * @package RockStarLab\ImportExport\Abilities
 */

namespace RockStarLab\ImportExport\Abilities;

defined( 'ABSPATH' ) || exit;

/** Closed inputs never accept paths, child jobs or client-reported progress. */
class Migration_Catalog {
	/**
	 * Return the first migration vertical: plan, ZIP export and lifecycle.
	 *
	 * @return array
	 */
	public static function definitions() {
		$s                              = Workflow_Schemas::string();
		$n                              = Workflow_Schemas::integer();
		$id                             = Workflow_Schemas::integer( 1 );
		$step                           = Workflow_Schemas::object(
			[
				'key'          => $s,
				'type'         => $s,
				'label'        => $s,
				'filename'     => $s,
				'fields'       => Workflow_Schemas::items( $s ),
				'importable'   => [ 'type' => 'boolean' ],
				'order'        => $n,
				'taxonomy'     => $s,
				'post_type'    => $s,
			],
			[ 'key', 'type', 'label', 'filename', 'fields', 'importable', 'order' ]
		);
		$result                         = Workflow_Schemas::object( array_fill_keys( [ 'files_count', 'empty_files', 'success', 'created', 'updated', 'skipped', 'failed', 'replaced_urls' ], $n ), [ 'files_count', 'empty_files', 'success', 'created', 'updated', 'skipped', 'failed', 'replaced_urls' ] );
		$status                         = Workflow_Schemas::object(
			[
				'job_id'             => $id,
				'status'             => $s,
				'stage'              => $s,
				'processed'          => $n,
				'total'              => $n,
				'child_job_id'       => $n,
				'error_code'         => $s,
				'file_id'            => $s,
				'poll_after_seconds' => $n,
				'result'             => $result,

			],
			[ 'job_id', 'status', 'stage', 'processed', 'total', 'child_job_id', 'error_code', 'file_id', 'poll_after_seconds' ]
		);
		$defs                           = [
			'migration-plan'  => [
				'description' => 'List authorized migration export steps in dependency order. Premium families come from the installed Pro provider. Use migration-start for export_file and migration-import-start for import_file after migration-prepare. Media exports contain URLs/metadata, not bundled media bytes. No full database or filesystem backup.',
				'input'       => Schema_Registry::empty_input(),
				'output'      => Workflow_Schemas::object(
					[
						'steps'      => Workflow_Schemas::items( $step ),
						'operations' => Workflow_Schemas::items( $s ),
					],
					[ 'steps', 'operations' ]
				),
				'readonly'    => true,
			],
			'migration-start' => [
				'description' => 'Queue an export_file migration to a private JSON ZIP with manifest.json. Select unique step keys from migration-plan; dependency order is server-owned. Optional selected_ids per step narrow its existing exporter. request_id deduplicates starts. WP-Cron runs child exports and packages only verified completed files. Poll migration-get; download using file-get and authenticated file transport. No caller paths, child jobs, status or counters are accepted. Content is read as each export runs; this is not a transactional snapshot.',
				'input'       => Workflow_Schemas::object(
					[
						'operation'  => Workflow_Schemas::string( [ 'export_file' ] ),
						'steps'      => Workflow_Schemas::items(
							Workflow_Schemas::object(
								[
									'key'          => $s + [
										'minLength' => 1,
										'maxLength' => 100,
									],
									'selected_ids' => Workflow_Schemas::items( $id, 1000 ) + [
										'minItems'    => 1,
										'uniqueItems' => true,
									],
								],
								[ 'key' ]
							),
							100
						) + [ 'minItems' => 1 ],
						'request_id' => Workflow_Schemas::request_id(),
					],
					[ 'operation', 'steps', 'request_id' ]
				),
				'output'      => Sync_Job_Catalog::starts( Sync_Catalog::definitions() )['sync-push-start']['output'],
				'readonly'    => false,
			],
		];
		$defs['migration-import-start'] = [
			'description' => 'Queue all steps from a prepared migration ZIP, or pass steps with exact keys from migration-prepare to import only those types. Unknown, empty and duplicate selections are rejected. Supply its package file_id and exact sha256 from migration-prepare. Server-owned identity mapping and dependency order; the worker prepares one archive entry per checkpoint and checks all steps before child creation. Entry-content failures are reported on the queued parent. CSV, XML, JSON-array and normalized JSON-object/XLSX/ODS entry validation checkpoints at 100 rows or about 1 MiB; individual rows remain atomic. Entry extraction and workbook/JSON-object snapshots resume between requests. Source hashing remains synchronous; ZipArchive replays the decoded prefix when reopening an unseekable compressed member. Defaults to skipping duplicates and downloading media URLs; optional replace_links_rules replace URLs. WP-Cron continues after closing the browser. Poll migration-get; Jobs lifecycle applies to the parent only. Partial/uncertain children stop the parent; cancel does not roll back completed rows. No automatic retry or transactional rollback. Cross-step core ID maps are persisted; specialized ACF/Woo relationships retain existing importer semantics.',
			'input'       => Workflow_Schemas::object(
				[
					'file_id'             => $s + [ 'pattern' => '^file_[a-f0-9]{32}$' ],
					'steps'               => Workflow_Schemas::items(
						$s + [
							'minLength' => 1,
							'maxLength' => 100,
						],
						100
					) + [
						'minItems'    => 1,
						'uniqueItems' => true,
					],
					'sha256'              => $s + [ 'pattern' => '^[a-f0-9]{64}$' ],
					'request_id'          => Workflow_Schemas::request_id(),
					'duplicate_handling'  => Workflow_Schemas::string( [ 'skip', 'update' ] ),
					'download_media'      => [ 'type' => 'boolean' ],
					'replace_links_rules' => Workflow_Schemas::items(
						Workflow_Schemas::object(
							[
								'search'  => $s + [
									'minLength' => 1,
									'maxLength' => 2000,
								],
								'replace' => $s + [ 'maxLength' => 2000 ],
							],
							[ 'search', 'replace' ]
						),
						50
					),
				],
				[ 'file_id', 'sha256', 'request_id' ]
			),
			'output'      => $defs['migration-start']['output'],
			'readonly'    => false,
			'destructive' => true,
		];
		$prepared                       = Workflow_Schemas::object(
			[
				'key'          => $s,
				'type'         => $s,
				'file_id'      => $s,
				'file_name'    => $s,
				'format'       => $s,
				'headers'      => Workflow_Schemas::items( $s ),
				'total_rows'   => $n,
				'taxonomy'     => $s,
				'post_type'    => $s,
			],
			[ 'key', 'type', 'file_id', 'file_name', 'format', 'headers', 'total_rows', 'taxonomy', 'post_type' ]
		);
		$defs['migration-prepare']      = [
			'description' => 'Validate and prepare a migration ZIP from an uploaded or exported file_id. Requires manifest.json version 1.0 and one or more unique supported content steps. Flat regular files only. The manifest has no fixed byte cap; input steps are spooled to temporary storage. Prepared steps are returned in pages: offset defaults to 0, limit to 100 (maximum 100). After pending=false, continue with next_offset until complete=true. Each page is limited to about 1 MiB except an atomic individual step. JSON array, CSV, XML, XLSX and ODS entries use streaming readers without a fixed decoded file-size cap. Repeat migration-prepare with the same file_id while pending is true; each call advances extraction or validation within one entry. Pending responses contain no prepared steps. Extracted bytes advance in 1 MiB slices; validation and workbook/object snapshot building resume between calls. There is no aggregate decoded archive byte cap; the uploaded ZIP still follows the site file limit. Entries are validated sequentially before publication; row counts and headers come from the same data pass. Every declared file and actual row scope is checked before publishing private import file IDs. Repeated preparation reuses immutable file IDs; changed derived bytes are rejected. Returns headers/counts in dependency order, no content preview or filesystem paths. Does not import content; use migration-import-start for the full archive or the existing import workflow per prepared file.',
			'input'       => Workflow_Schemas::object(
				[
					'file_id' => $s + [ 'pattern' => '^file_[a-f0-9]{32}$' ],
					'offset'  => $n,
					'limit'   => [
						'type'    => 'integer',
						'minimum' => 1,
						'maximum' => 100,
					],
				],
				[ 'file_id' ]
			),
			'output'      => Workflow_Schemas::object(
				[
					'pending'         => [ 'type' => 'boolean' ],
					'offset'          => $n,
					'next_offset'     => $n,
					'total_steps'     => $n,
					'complete'        => [ 'type' => 'boolean' ],
					'package_file_id' => $s,
					'sha256'          => $s,
					'steps'           => Workflow_Schemas::items( $prepared, 100 ),
					// True when WooCommerce steps in the package were skipped because WooCommerce is not active here.
					'woocommerce_excluded' => [ 'type' => 'boolean' ],
				],
				[ 'package_file_id', 'sha256', 'steps' ]
			),
			'readonly'    => false,
			'destructive' => false,
			'idempotent'  => true,
		];
		// Content-kind count is not a total migration limit.
		unset( $defs['migration-start']['input']['properties']['steps']['maxItems'], $defs['migration-import-start']['input']['properties']['steps']['maxItems'] );
		foreach ( [ 'get', 'pause', 'resume', 'cancel' ] as $action ) {
			$defs[ 'migration-' . $action ] = [
				'description' => 'Migration ' . $action . '. Progress counts completed content steps; child_job_id identifies the active import/export. result contains aggregate import counters (zero outside import). Reads never advance work. Pause/resume/cancel serialize with the parent worker and stop future batches. Terminal jobs cannot resume or retry. file_id is available only after successful packaging and current provider checks. Use file-get for authenticated download.',
				'input'       => Workflow_Schemas::object( [ 'job_id' => $id ], [ 'job_id' ] ),
				'output'      => $status,
				'readonly'    => 'get' === $action,
			];
		}

		foreach ( $defs as $name => &$def ) {
			$def += [
				'label'       => ucwords( str_replace( '-', ' ', $name ) ),
				'destructive' => 'migration-cancel' === $name,
				'idempotent'  => in_array( $name, [ 'migration-plan', 'migration-get', 'migration-pause', 'migration-cancel' ], true ),
			]; }
		unset( $def );
		return $defs;
	}
}

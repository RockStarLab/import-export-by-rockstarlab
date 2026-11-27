<?php
/**
 * Connected-site Media Library contracts, available in Free.
 *
 * @package RockStarLab\ImportExport\Abilities
 */

namespace RockStarLab\ImportExport\Abilities;

defined( 'ABSPATH' ) || exit;

/** Bounded synchronous media transfers. */
class Media_Library_Catalog {
	/**
	 * Describe browse and explicit push/pull.
	 *
	 * @return array
	 */
	public static function definitions() {
		$n           = Workflow_Schemas::integer();
		$s           = Workflow_Schemas::string();
		$summary     = Workflow_Schemas::object(
			array(
				'ID'            => Workflow_Schemas::integer( 1 ),
				'title'         => $s,
				'url'           => $s,
				'preview_url'   => $s,
				'thumbnail_url' => $s,
				'mime_type'     => $s,
				'file_name'     => $s,
				'date'          => $s,
			),
			array( 'ID', 'title', 'url', 'preview_url', 'thumbnail_url', 'mime_type', 'file_name', 'date' )
		);
		$site        = array( 'site_id' => Workflow_Schemas::integer( 1 ) );
		$selection   = $site + array(
			'media_ids' => Workflow_Schemas::items( Workflow_Schemas::integer( 1 ), 50 ) + array(
				'minItems'    => 1,
				'uniqueItems' => true,
			),
		);
		$output      = Workflow_Schemas::object(
			array(
				'created' => $n,
				'skipped' => $n,
				'failed'  => $n,
				'items'   => Workflow_Schemas::items(
					Workflow_Schemas::object(
						array(
							'source_id'     => Workflow_Schemas::integer( 1 ),
							'attachment_id' => $n,
							'status'        => Workflow_Schemas::string( array( 'created', 'skipped', 'failed' ) ),
							'error_code'    => $s,
						),
						array( 'source_id', 'attachment_id', 'status', 'error_code' )
					),
					50
				),
			),
			array( 'created', 'skipped', 'failed', 'items' )
		);
		$definitions = array(
			'sync-media-browse' => array(
				'description' => 'Browse a connected site’s Media Library, with search and page/per_page (default 60, maximum 100). Returns attachment summaries, no file bytes or private paths. Requires an active connection and a peer key bound to an administrator with upload_files.',
				'input'       => Workflow_Schemas::object(
					$site + array(
						'search'   => $s + array( 'maxLength' => 200 ),
						'page'     => Workflow_Schemas::integer( 1 ),
						'per_page' => Workflow_Schemas::integer( 1, 100 ),
					),
					array( 'site_id' )
				),
				'output'      => Workflow_Schemas::object(
					array(
						'media'        => Workflow_Schemas::items( $summary, 100 ),
						'total'        => $n,
						'pages'        => $n,
						'current_page' => Workflow_Schemas::integer( 1 ),
					),
					array( 'media', 'total', 'pages', 'current_page' )
				),
				'readonly'    => true,
			),
		);
		foreach ( array( 'push', 'pull' ) as $direction ) {
			$definitions[ 'sync-media-' . $direction ] = array(
				'description' => 'Synchronously ' . $direction . ' 1–50 selected media_ids. Push IDs belong to this site; pull IDs belong to the peer. Uses the chunk protocol with 1 MiB blocks; metadata remains bounded to 1 MiB per file. No inline 16/32 MiB file limits. Transfers bytes, title/alt/caption/description, registered media ACF fields and source IDs. Hash duplicates reuse an editable attachment after checking its current bytes; existing standard text fields are preserved, ACF and source IDs are updated. Build the destination hash index first to find previously unindexed files. Returns per-source destination IDs and created/skipped/failed outcomes. Partial results are possible; inspect items before retrying. No background job, resume or request deduplication. Both sites must support the chunk protocol; use the -start variant for durable background resume.',
				'action'      => $direction,
				'handler'     => 'media-library',
				'background'  => array(
					'selection'    => 'media_ids',
					'content_type' => 'media',
					'counters'     => array(
						'success_items' => 'created',
						'skipped_items' => 'skipped',
						'failed_items'  => 'failed',
					),
					'description'  => 'Queue 1–50 media_ids for resumable ' . $direction . ' with a compatible chunk-protocol peer. Push IDs are local; pull IDs belong to the peer. New standalone jobs and children of new migration parents copy and transmit one 1 MiB block per step, without the inline 16/32 MiB byte limits. Metadata remains bounded to 1 MiB per file; preparation and finalization may scan the full file. Pause/resume retains checkpoints; cancellation cleans staged resources before completing. Transient peer failures retry three times then pause for explicit resume. Inspect per-source outcomes. Existing jobs and children of older migration parents retain their original transport.',
				),
				'input'       => Workflow_Schemas::object( $selection, array( 'site_id', 'media_ids' ) ),
				'output'      => $output,
				'readonly'    => false,
			);
		}
		foreach ( $definitions as $name => &$definition ) {
			$definition += array(
				'label'       => ucwords( str_replace( '-', ' ', $name ) ),
				'destructive' => ! $definition['readonly'],
				'idempotent'  => $definition['readonly'],
			);
		}
		unset( $definition );
		return apply_filters( 'rsl_ie_sync_media_definitions', $definitions );
	}
}

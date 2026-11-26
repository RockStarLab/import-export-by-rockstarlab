<?php
/**
 * Implemented Free media abilities.
 *
 * @package RockStarLab\ImportExport\Abilities
 */

namespace RockStarLab\ImportExport\Abilities;

defined( 'ABSPATH' ) || exit;

/** Add complete media contracts as their shared services become available. */
class Media_Catalog {
	/**
	 * Describe the hash index workflow.
	 *
	 * @return array
	 */
	public static function definitions() {
		$n              = Workflow_Schemas::integer();
		$b              = array( 'type' => 'boolean' );
		$cursor         = array(
			'after_id'        => $n,
			'snapshot_max_id' => $n,
			'limit'           => Workflow_Schemas::integer( 1, 100 ),
		);
		$selection      = array(
			'attachment_ids' => Workflow_Schemas::items( Workflow_Schemas::integer( 1 ), 100 ) + array(
				'minItems'    => 1,
				'uniqueItems' => true,
			),
		);
		$build          = Workflow_Schemas::object( $cursor + $selection );
		$build['oneOf'] = array( Workflow_Schemas::object( $selection, array( 'attachment_ids' ) ), Workflow_Schemas::object( $cursor ) );
		$source         = array(
			'hash'          => Workflow_Schemas::string() + array(
				'pattern'   => '^[a-fA-F0-9]{32}$',
				'minLength' => 32,
				'maxLength' => 32,
			),
			'file_path'     => Workflow_Schemas::string() + array(
				'minLength' => 1,
				'maxLength' => 4096,
			),
			'attachment_id' => Workflow_Schemas::integer( 1 ),
		);
		$check          = Workflow_Schemas::object( $source );
		$check['oneOf'] = array_map(
			static function ( $key ) {
				return array( 'required' => array( $key ) );
			},
			array_keys( $source )
		);
		$definitions    = array(
			'media-hash-stats'      => array(
				'description' => 'Read Media Library hash index counts. Only valid canonical MD5 hashes count as indexed; legacy-only hashes can match duplicates but are not counted until rebuilt. Does not read file bytes or write metadata.',
				'input'       => Schema_Registry::empty_input(),
				'output'      => Workflow_Schemas::object(
					array(
						'total'      => $n,
						'hashed'     => $n,
						'unhashed'   => $n,
						'percentage' => array(
							'type'    => 'number',
							'minimum' => 0,
							'maximum' => 100,
						),
					),
					array( 'total', 'hashed', 'unhashed', 'percentage' )
				),
				'readonly'    => true,
			),
			'media-hash-build'      => array(
				'description' => 'Refresh MD5 hashes from uploads file bytes for 1–100 selected attachment_ids, or scan by ID with limit (default 50). Do not combine selection with cursor fields. For scanning, start with limit only and pass returned after_id and snapshot_max_id unchanged to the next call until complete. The upper ID bound excludes new uploads and avoids offset skips after deletion; this is not a database snapshot. Returns individual unavailable files as errors and advances past them. Requires edit permission on every attachment in the batch. Each POST completes one synchronous batch; there is no background job. Refresh again if file bytes change.',
				'input'       => $build,
				'output'      => Workflow_Schemas::object(
					array(
						'processed'       => $n,
						'errors'          => $n,
						'attempted'       => $n,
						'after_id'        => $n,
						'snapshot_max_id' => $n,
						'complete'        => $b,
						'results'         => Workflow_Schemas::items(
							Workflow_Schemas::object(
								array(
									'attachment_id' => Workflow_Schemas::integer( 1 ),
									'status'        => Workflow_Schemas::string( array( 'indexed', 'unavailable' ) ),
								),
								array( 'attachment_id', 'status' )
							),
							100
						),
					),
					array( 'processed', 'errors', 'attempted', 'after_id', 'snapshot_max_id', 'complete', 'results' )
				),
				'readonly'    => false,
			),
			'media-check-duplicate' => array(
				'description' => 'Read the hash index using exactly one of: an MD5 hash, an uploads-relative or absolute file_path, or an attachment_id. Paths and attachments are hashed from current file bytes without storing metadata. Attachment lookup excludes itself. Hash/file lookup may match the source attachment. Searches canonical and legacy indexes only, never scans unindexed files or rewrites metadata. No match does not prove uniqueness until the index is built. MD5 is used for duplicate matching, not security verification.',
				'input'       => $check,
				'output'      => Workflow_Schemas::object(
					array(
						'is_duplicate'   => $b,
						'attachment_id'  => $n,
						'attachment_url' => Workflow_Schemas::string(),
					),
					array( 'is_duplicate', 'attachment_id', 'attachment_url' )
				),
				'readonly'    => true,
			),
		);
		foreach ( $definitions as $name => &$definition ) {
			$definition += array(
				'label'       => ucwords( str_replace( '-', ' ', $name ) ),
				'destructive' => false,
				'idempotent'  => $definition['readonly'],
			);
		}
		unset( $definition );
		return $definitions + Media_Folder_Catalog::definitions() + Media_Library_Catalog::definitions();
	}
}

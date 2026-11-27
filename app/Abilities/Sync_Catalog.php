<?php
/**
 * Connection and Free content sync ability contracts.
 *
 * @package RockStarLab\ImportExport\Abilities
 */

namespace RockStarLab\ImportExport\Abilities;

defined( 'ABSPATH' ) || exit;

/** Only implemented operations, with explicit synchronous transfer semantics. */
class Sync_Catalog {
	/**
	 * Build contracts shared by registration and direct service validation.
	 *
	 * @return array
	 */
	public static function definitions() {
		$s           = Workflow_Schemas::string();
		$n           = Workflow_Schemas::integer();
		$id          = Workflow_Schemas::integer( 1 );
		$ids         = Workflow_Schemas::items( $id, 50 ) + array(
			'minItems'    => 1,
			'uniqueItems' => true,
		);
		$map         = array(
			'type'                 => 'object',
			'additionalProperties' => array(
				'type'    => array( 'integer', 'null' ),
				'minimum' => 1,
			),
			'maxProperties'        => 50,
		);
		$site_id     = array( 'site_id' => $id );
		$type        = array( 'post_type' => Workflow_Schemas::string( array( 'post', 'page' ) ) );
		$connection  = Workflow_Schemas::object(
			array(
				'id'           => $id,
				'name'         => $s,
				'remote_url'   => $s,
				'direction'    => $s,
				'status'       => $s,
				'created_by'   => $n,
				'created_at'   => $s,
				'updated_at'   => $s,
				'last_sync_at' => array( 'type' => array( 'string', 'null' ) ),
			),
			array( 'id', 'name', 'remote_url', 'direction', 'status' )
		);
		$message     = Workflow_Schemas::object( array( 'message' => $s ), array( 'message' ) );
		$post        = Workflow_Schemas::object(
			array(
				'ID'             => $id,
				'post_title'     => $s,
				'post_name'      => $s,
				'post_type'      => $s,
				'post_status'    => $s,
				'post_date'      => $s,
				'post_modified'  => $s,
				'post_parent'    => $n,
				'menu_order'     => array( 'type' => 'integer' ),
				'children_count' => $n,
				'original_id'    => $n,
			),
			array( 'ID', 'post_type' )
		);
		$posts       = Workflow_Schemas::items( $post, 1000 );
		$browse      = Workflow_Schemas::object(
			array(
				'posts'         => $posts,
				'total'         => $n,
				'pages'         => $n,
				'current_page'  => $id,
				'status_counts' => array(
					'type'                 => 'object',
					'additionalProperties' => $n,
				),
			),
			array( 'posts' )
		);
		$selection   = $site_id + $type + array( 'post_ids' => $ids );
		$page        = array(
			'search'   => $s + array( 'maxLength' => 200 ),
			'page'     => Workflow_Schemas::integer( 1, 10000 ),
			'per_page' => Workflow_Schemas::integer( 1, 50 ),
		);
		$definitions = array();
		$add         = static function ( $name, $action, $description, $input, $output, $read_only = false, $destructive = false, $idempotent = true ) use ( &$definitions ) {
			$definitions[ $name ] = array(
				'action'      => $action,
				'description' => $description,
				'input'       => $input,
				'output'      => $output,
				'readonly'    => $read_only,
				'destructive' => $destructive,
				'idempotent'  => $idempotent,
			) + array( 'label' => ucwords( str_replace( '-', ' ', $name ) ) );
		};
		$add(
			'connection-list',
			'get_sites',
			'List connected sites without API keys or raw remote errors. Existing administrators can manage all connections on this site.',
			Workflow_Schemas::object(
				array(
					'limit'  => Workflow_Schemas::integer( 1, 100 ),
					'offset' => $n,
				)
			),
			Workflow_Schemas::object(
				array(
					'sites' => Workflow_Schemas::items( $connection, 100 ),
					'total' => $n,
				),
				array( 'sites', 'total' )
			),
			true
		);
		$fields = array(
			'name'       => $s + array(
				'minLength' => 1,
				'maxLength' => 200,
			),
			'remote_url' => $s + array(
				'minLength' => 1,
				'maxLength' => 2000,
			),
			'api_key'    => $s + array(
				'minLength' => 1,
				'maxLength' => 512,
			),
			// Informational label only (owner, 24 Nov 2026): every Push/Pull action works on any connection.
			'direction'  => Workflow_Schemas::string( array( 'push', 'pull', 'bidirectional' ) ) + array( 'description' => 'Informational label kept for compatibility; it does not restrict push or pull. Default bidirectional.' ),
		);
		$add(
			'connection-create',
			'add_site',
			'Connect a site using its incoming Content Sync key. Validates the peer before saving. Credentials are accepted as input and never echoed. Use the existing UI to obtain the peer key.',
			Workflow_Schemas::object( $fields, array( 'name', 'remote_url', 'api_key' ) ),
			Workflow_Schemas::object(
				array(
					'site'    => $connection,
					'message' => $s,
				),
				array( 'site', 'message' )
			),
			false,
			false,
			false
		);
		$add(
			'connection-update',
			'update_site',
			'Update only supplied connection fields. URL or credential changes are validated before saving. An in-use connection cannot be changed.',
			Workflow_Schemas::object( $site_id + $fields + array( 'status' => Workflow_Schemas::string( array( 'active', 'inactive', 'error' ) ) ), array( 'site_id' ) ),
			Workflow_Schemas::object(
				array(
					'site'    => $connection,
					'message' => $s,
				),
				array( 'site', 'message' )
			),
			false,
			true,
			false
		);
		$add( 'connection-delete', 'delete_site', 'Remove the local connection record. Does not delete remote content or revoke the remote key. An in-use connection cannot be removed.', Workflow_Schemas::object( $site_id, array( 'site_id' ) ), $message, false, true, false );
		$add( 'connection-test', 'test_connection', 'Test the stored credential; saves connection status and last-sync time. Success activates this connection.', Workflow_Schemas::object( $site_id, array( 'site_id' ) ), $message, false, false, false );
		$add(
			'sync-browse',
			'get_remote_posts',
			'Browse or search remote posts/pages with pagination, status and language. Specify one Free post_type.',
			Workflow_Schemas::object(
				$site_id + $type + $page + array(
					'status'           => Workflow_Schemas::string( array( '', 'publish', 'draft', 'pending', 'private', 'future', 'trash' ) ),
					'language'         => $s + array( 'maxLength' => 32 ),
					'include_children' => array( 'type' => 'boolean' ),
				),
				array( 'site_id', 'post_type' )
			),
			$browse,
			true
		);
		$add( 'sync-search-local', 'search_local_posts', 'Search local Free posts/pages for explicit destination mappings.', Workflow_Schemas::object( $type + $page, array( 'post_type' ) ), $browse, true );
		$add(
			'sync-children',
			'get_children_posts',
			'Read children of a remote Free post/page. The response is limited to 1000 children; use sync-browse pagination for larger selections.',
			Workflow_Schemas::object(
				$site_id + $type + array(
					'parent_id' => $id,
					'language'  => $s + array( 'maxLength' => 32 ),
				),
				array( 'site_id', 'post_type', 'parent_id' )
			),
			Workflow_Schemas::object( array( 'children' => $posts ), array( 'children' ) ),
			true
		);
		$add( 'sync-local-info', 'get_local_posts_info', 'Read up to 50 selected local posts/pages, including existing original IDs.', Workflow_Schemas::object( $type + array( 'post_ids' => $ids ), array( 'post_type', 'post_ids' ) ), Workflow_Schemas::object( array( 'posts' => $posts ), array( 'posts' ) ), true );
		$add(
			'sync-auto-map',
			'auto_map_by_title',
			'Suggest local-ID to remote-ID mappings by exact normalized title (case, spacing and HTML entities ignored) and type. A post whose title matches several remote posts gets null in mapping and an entry in ambiguous with the remote IDs: ask the user which one to update. Incomplete remote searches fail; review suggestions before transferring.',
			Workflow_Schemas::object( $selection, array( 'site_id', 'post_type', 'post_ids' ) ),
			Workflow_Schemas::object(
				array(
					'mapping'   => $map,
					'matches'   => Workflow_Schemas::value(),
					'ambiguous' => Workflow_Schemas::value(),
				),
				array( 'mapping', 'matches' )
			),
			true
		);
		// The result can hold more posts than the 50 selected: translations (Polylang) and grouped
		// children travel with them.
		$result = Workflow_Schemas::object(
			array(
				'job_id'        => $id,
				'message'       => $s,
				'images_synced' => $n,
				'result'        => Workflow_Schemas::object(
					array(
						'direction'     => $s,
						'selected_ids'  => Workflow_Schemas::items( $id, 1000 ),
						'post_mapping'  => array_merge( $map, array( 'maxProperties' => 1000 ) ),
						'created'       => $n,
						'updated'       => $n,
						'failed'        => $n,
						'images_synced' => $n,
						'image_map'     => array_merge( $map, array( 'maxProperties' => 1000 ) ),
						'variation_map' => array(
							'type'                 => 'object',
							'maxProperties'        => 1000,
							'additionalProperties' => $id,
						),
						'posts'         => Workflow_Schemas::items(
							Workflow_Schemas::object(
								array(
									'source_id' => $id,
									'target_id' => $id,
									'title'     => $s,
									'type'      => $s,
									'slug'      => $s,
								)
							),
							1000
						),
					)
				),
			),
			array( 'job_id', 'message', 'result' )
		);
		$add(
			'sync-push',
			'push_content',
			'Synchronously push up to 50 local posts/pages with metadata, ACF, comments, taxonomy relationships and media. post_mapping is local ID to remote ID (null means automatic/new). Returns a history job and maps. This may outlast an HTTP timeout; inspect job history before retrying. No automatic retry or pause/resume for this operation. With Polylang and PRO, with_translations (default true) also sends the translations of the selected posts and links them on the other site.',
			Workflow_Schemas::object(
				$selection + array(
					'post_mapping'      => $map,
					'hierarchy'         => \RockStarLab\ImportExport\Service\Sync_Hierarchy::schema(),
					'with_translations' => array( 'type' => 'boolean' ),
				),
				array( 'site_id', 'post_type', 'post_ids' )
			),
			$result,
			false,
			true,
			false
		);
		$pull_map                         = $map;
		$pull_map['additionalProperties'] = $id;
		$add(
			'sync-pull',
			'pull_content',
			'Synchronously pull up to 50 remote posts/pages. post_ids are remote IDs; optional post_mapping is local ID to remote ID and must cover the same selection. Without it, existing slug/original-ID matches are updated. Preserves comments, metadata, media and hierarchy. Returns a history job and maps; inspect history after a timeout before retrying. No automatic retry or pause/resume. With Polylang and PRO on both sites, with_translations (default true) also pulls the translations of the selected posts.',
			Workflow_Schemas::object(
				$selection + array(
					'post_mapping'      => $pull_map,
					'hierarchy'         => \RockStarLab\ImportExport\Service\Sync_Hierarchy::pull_schema(),
					'with_translations' => array( 'type' => 'boolean' ),
				),
				array( 'site_id', 'post_type', 'post_ids' )
			),
			$result,
			false,
			true,
			false
		);
		unset( $result['properties']['result']['properties']['image_map']['maxProperties'] );
		$add( 'sync-result', 'sync_result', 'Read safe counters and source/target/media maps from an A12 Free sync job, including after an HTTP timeout. Legacy and Pro sync jobs have separate contracts. Reads never run or repeat transfers.', Workflow_Schemas::object( array( 'job_id' => $id ), array( 'job_id' ) ), $result, true );
		foreach ( array( 'sync-push', 'sync-pull' ) as $operation ) {
			$definitions[ $operation ]['output'] = $result;
		}
		return apply_filters( 'rsl_ie_sync_post_definitions', $definitions ) + Standalone_Sync_Catalog::definitions();
	}
}

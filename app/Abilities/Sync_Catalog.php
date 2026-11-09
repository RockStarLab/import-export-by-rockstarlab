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
		$ids         = Workflow_Schemas::items( $id, 50 ) + [
			'minItems'    => 1,
			'uniqueItems' => true,
		];
		$map         = [
			'type'                 => 'object',
			'additionalProperties' => [
				'type'    => [ 'integer', 'null' ],
				'minimum' => 1,
			],
			'maxProperties'        => 50,
		];
		$site_id     = [ 'site_id' => $id ];
		$type        = [ 'post_type' => Workflow_Schemas::string( [ 'post', 'page' ] ) ];
		$connection  = Workflow_Schemas::object(
			[
				'id'           => $id,
				'name'         => $s,
				'remote_url'   => $s,
				'direction'    => $s,
				'status'       => $s,
				'created_by'   => $n,
				'created_at'   => $s,
				'updated_at'   => $s,
				'last_sync_at' => [ 'type' => [ 'string', 'null' ] ],
			],
			[ 'id', 'name', 'remote_url', 'direction', 'status' ]
		);
		$message     = Workflow_Schemas::object( [ 'message' => $s ], [ 'message' ] );
		$post        = Workflow_Schemas::object(
			[
				'ID'             => $id,
				'post_title'     => $s,
				'post_name'      => $s,
				'post_type'      => $s,
				'post_status'    => $s,
				'post_date'      => $s,
				'post_modified'  => $s,
				'post_parent'    => $n,
				'menu_order'     => [ 'type' => 'integer' ],
				'children_count' => $n,
				'original_id'    => $n,
			],
			[ 'ID', 'post_type' ]
		);
		$posts       = Workflow_Schemas::items( $post, 1000 );
		$browse      = Workflow_Schemas::object(
			[
				'posts'         => $posts,
				'total'         => $n,
				'pages'         => $n,
				'current_page'  => $id,
				'status_counts' => [
					'type'                 => 'object',
					'additionalProperties' => $n,
				],
			],
			[ 'posts' ]
		);
		$selection   = $site_id + $type + [ 'post_ids' => $ids ];
		$page        = [
			'search'   => $s + [ 'maxLength' => 200 ],
			'page'     => Workflow_Schemas::integer( 1, 10000 ),
			'per_page' => Workflow_Schemas::integer( 1, 50 ),
		];
		$definitions = [];
		$add         = static function ( $name, $action, $description, $input, $output, $read_only = false, $destructive = false, $idempotent = true ) use ( &$definitions ) {
			$definitions[ $name ] = [
				'action'      => $action,
				'description' => $description,
				'input'       => $input,
				'output'      => $output,
				'readonly'    => $read_only,
				'destructive' => $destructive,
				'idempotent'  => $idempotent,
			] + [ 'label' => ucwords( str_replace( '-', ' ', $name ) ) ];
		};
		$add(
			'connection-list',
			'get_sites',
			'List connected sites without API keys or raw remote errors. Existing administrators can manage all connections on this site.',
			Workflow_Schemas::object(
				[
					'limit'  => Workflow_Schemas::integer( 1, 100 ),
					'offset' => $n,
				]
			),
			Workflow_Schemas::object(
				[
					'sites' => Workflow_Schemas::items( $connection, 100 ),
					'total' => $n,
				],
				[ 'sites', 'total' ]
			),
			true
		);
		$fields = [
			'name'       => $s + [
				'minLength' => 1,
				'maxLength' => 200,
			],
			'remote_url' => $s + [
				'minLength' => 1,
				'maxLength' => 2000,
			],
			'api_key'    => $s + [
				'minLength' => 1,
				'maxLength' => 512,
			],
			'direction'  => Workflow_Schemas::string( [ 'push', 'pull', 'bidirectional' ] ),
		];
		$add(
			'connection-create',
			'add_site',
			'Connect a site using its incoming Content Sync key. Validates the peer before saving. Credentials are accepted as input and never echoed. Use the existing UI to obtain the peer key.',
			Workflow_Schemas::object( $fields, [ 'name', 'remote_url', 'api_key' ] ),
			Workflow_Schemas::object(
				[
					'site'    => $connection,
					'message' => $s,
				],
				[ 'site', 'message' ]
			),
			false,
			false,
			false
		);
		$add(
			'connection-update',
			'update_site',
			'Update only supplied connection fields. URL or credential changes are validated before saving. An in-use connection cannot be changed.',
			Workflow_Schemas::object( $site_id + $fields + [ 'status' => Workflow_Schemas::string( [ 'active', 'inactive', 'error' ] ) ], [ 'site_id' ] ),
			Workflow_Schemas::object(
				[
					'site'    => $connection,
					'message' => $s,
				],
				[ 'site', 'message' ]
			),
			false,
			true,
			false
		);
		$add( 'connection-delete', 'delete_site', 'Remove the local connection record. Does not delete remote content or revoke the remote key. An in-use connection cannot be removed.', Workflow_Schemas::object( $site_id, [ 'site_id' ] ), $message, false, true, false );
		$add( 'connection-test', 'test_connection', 'Test the stored credential; saves connection status and last-sync time. Success activates this connection.', Workflow_Schemas::object( $site_id, [ 'site_id' ] ), $message, false, false, false );
		$add(
			'sync-browse',
			'get_remote_posts',
			'Browse or search remote posts/pages with pagination, status and language. Specify one Free post_type.',
			Workflow_Schemas::object(
				$site_id + $type + $page + [
					'status'           => Workflow_Schemas::string( [ '', 'publish', 'draft', 'pending', 'private', 'future', 'trash' ] ),
					'language'         => $s + [ 'maxLength' => 32 ],
					'include_children' => [ 'type' => 'boolean' ],
				],
				[ 'site_id', 'post_type' ]
			),
			$browse,
			true
		);
		$add( 'sync-search-local', 'search_local_posts', 'Search local Free posts/pages for explicit destination mappings.', Workflow_Schemas::object( $type + $page, [ 'post_type' ] ), $browse, true );
		$add(
			'sync-children',
			'get_children_posts',
			'Read children of a remote Free post/page. The response is limited to 1000 children; use sync-browse pagination for larger selections.',
			Workflow_Schemas::object(
				$site_id + $type + [
					'parent_id' => $id,
					'language'  => $s + [ 'maxLength' => 32 ],
				],
				[ 'site_id', 'post_type', 'parent_id' ]
			),
			Workflow_Schemas::object( [ 'children' => $posts ], [ 'children' ] ),
			true
		);
		$add( 'sync-local-info', 'get_local_posts_info', 'Read up to 50 selected local posts/pages, including existing original IDs.', Workflow_Schemas::object( $type + [ 'post_ids' => $ids ], [ 'post_type', 'post_ids' ] ), Workflow_Schemas::object( [ 'posts' => $posts ], [ 'posts' ] ), true );
		$add(
			'sync-auto-map',
			'auto_map_by_title',
			'Suggest local-ID to remote-ID mappings by exact normalized title (case, spacing and HTML entities ignored) and type. A post whose title matches several remote posts gets null in mapping and an entry in ambiguous with the remote IDs: ask the user which one to update. Incomplete remote searches fail; review suggestions before transferring.',
			Workflow_Schemas::object( $selection, [ 'site_id', 'post_type', 'post_ids' ] ),
			Workflow_Schemas::object(
				[
					'mapping'   => $map,
					'matches'   => Workflow_Schemas::value(),
					'ambiguous' => Workflow_Schemas::value(),
				],
				[ 'mapping', 'matches' ]
			),
			true
		);
		$result = Workflow_Schemas::object(
			[
				'job_id'        => $id,
				'message'       => $s,
				'images_synced' => $n,
				'result'        => Workflow_Schemas::object(
					[
						'direction'     => $s,
						'selected_ids'  => $ids,
						'post_mapping'  => $map,
						'created'       => $n,
						'updated'       => $n,
						'failed'        => $n,
						'images_synced' => $n,
						'image_map'     => $map,
						'variation_map' => [
							'type'                 => 'object',
							'maxProperties'        => 50,
							'additionalProperties' => $id,
						],
						'posts'         => Workflow_Schemas::items(
							Workflow_Schemas::object(
								[
									'source_id' => $id,
									'target_id' => $id,
									'title'     => $s,
									'type'      => $s,
									'slug'      => $s,
								]
							),
							50
						),
					]
				),
			],
			[ 'job_id', 'message', 'result' ]
		);
		$add(
			'sync-push',
			'push_content',
			'Synchronously push up to 50 local posts/pages with metadata, ACF, comments, taxonomy relationships and media. post_mapping is local ID to remote ID (null means automatic/new). Returns a history job and maps. This may outlast an HTTP timeout; inspect job history before retrying. No automatic retry or pause/resume for this operation.',
			Workflow_Schemas::object(
				$selection + [
					'post_mapping' => $map,
					'hierarchy'    => \RockStarLab\ImportExport\Service\Sync_Hierarchy::schema(),
				],
				[ 'site_id', 'post_type', 'post_ids' ]
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
			'Synchronously pull up to 50 remote posts/pages. post_ids are remote IDs; optional post_mapping is local ID to remote ID and must cover the same selection. Without it, existing slug/original-ID matches are updated. Preserves comments, metadata, media and hierarchy. Returns a history job and maps; inspect history after a timeout before retrying. No automatic retry or pause/resume.',
			Workflow_Schemas::object(
				$selection + [
					'post_mapping' => $pull_map,
					'hierarchy'    => \RockStarLab\ImportExport\Service\Sync_Hierarchy::pull_schema(),
				],
				[ 'site_id', 'post_type', 'post_ids' ]
			),
			$result,
			false,
			true,
			false
		);
		unset( $result['properties']['result']['properties']['image_map']['maxProperties'] );
		$add( 'sync-result', 'sync_result', 'Read safe counters and source/target/media maps from an A12 Free sync job, including after an HTTP timeout. Legacy and Pro sync jobs have separate contracts. Reads never run or repeat transfers.', Workflow_Schemas::object( [ 'job_id' => $id ], [ 'job_id' ] ), $result, true );
		foreach ( [ 'sync-push', 'sync-pull' ] as $operation ) {
			$definitions[ $operation ]['output'] = $result;
		}
		return apply_filters( 'rsl_ie_sync_post_definitions', $definitions ) + Standalone_Sync_Catalog::definitions();
	}
}

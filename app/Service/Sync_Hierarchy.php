<?php
/**
 * Confirm parent relationships across bounded migration transfers.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Sync_Peer_Policy;
use RockStarLab\ImportExport\Abilities\Workflow_Schemas as S;
use RockStarLab\ImportExport\Helper\Remote_API;

defined( 'ABSPATH' ) || exit;

/** Every part acknowledges its parents before the next part can use its maps. */
class Sync_Hierarchy {
	/**
	 * Explicit parent protocol, also used by the background child schema.
	 *
	 * @return array
	 */
	public static function schema() {
		return S::object(
			[
				'version' => [
					'type' => 'integer',
					'enum' => [ 1 ],
				],
				'parents' => [
					'type'                 => 'object',
					'additionalProperties' => S::integer( 1 ),
					'maxProperties'        => 50,
				],
			],
			[ 'version', 'parents' ]
		);
	}

	/**
	 * Resolve an authorized domain implementation; premium code lives in Pro.
	 *
	 * @param string $kind Identity namespace.
	 * @param string $type Concrete type.
	 * @return array|\WP_Error
	 */
	public static function provider( $kind, $type ) {
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access; }
		if ( 'post_type' === $kind && in_array( $type, [ 'post', 'page' ], true ) ) {
			$access = Sync_Peer_Policy::post( $type, 'sync_pull' );
			return is_wp_error( $access ) ? $access : self::post_provider( $type );
		}
		return apply_filters( 'rsl_ie_sync_hierarchy_provider', self::error(), $kind, $type );
	}

	/**
	 * Shared post mechanics after the owning package has authorized its type.
	 *
	 * @param string $type Authorized post type.
	 * @return array
	 */
	public static function post_provider( $type ) {
		return [
			'records' => 'posts',
			'id'      => 'ID',
			'parent'  => 'post_parent',
			'mapping' => 'post_mapping',
			'read'    => static function ( $id ) use ( $type ) {
				$access = Sync_Peer_Policy::destination( $id, $type );
				$post   = get_post( $id );
				return is_wp_error( $access ) || ! $post ? self::error() : (int) $post->post_parent;
			},
			'write'   => static function ( $id, $parent_id ) {
				$post  = get_post( $id );
				$dates = static function ( $data, $postarr ) use ( $id, $post ) {
					if ( (int) ( $postarr['ID'] ?? 0 ) === $id ) {
						$data['post_modified']     = $post->post_modified;
						$data['post_modified_gmt'] = $post->post_modified_gmt;
					}
					return $data;
				};
				add_filter( 'wp_insert_post_data', $dates, 10, 2 );
				try {
					$result = wp_update_post(
						[
							'ID'          => $id,
							'post_parent' => $parent_id,
						],
						true
					);
					return is_wp_error( $result ) || (int) get_post( $id )->post_parent !== $parent_id ? self::error() : true;
				} finally {
					remove_filter( 'wp_insert_post_data', $dates, 10 ); }
			},
			'receive' => static function ( $payload ) {
				$request = new \WP_REST_Request( 'POST' );
				$request->set_body_params( $payload );
				return ( new \RockStarLab\ImportExport\Controller\Content_Sync_API_Controller( false ) )->receive_content( $request );
			},
			'maps'    => static function ( $data ) {
				return $data['data']['post_map'] ?? [];
			},
		];
	}

	/**
	 * Verify a protocol schema and exactly the parents outside this selection.
	 *
	 * @param array $edges Source ID/parent pairs.
	 * @param mixed $hierarchy Protocol data.
	 * @return true|\WP_Error
	 */
	public static function validate( array $edges, $hierarchy ) {
		if ( ! is_array( $hierarchy ) || ! is_array( $hierarchy['parents'] ?? null ) || ! $edges || count( $edges ) > 50 || is_wp_error( rest_validate_value_from_schema( $hierarchy, self::schema() ) ) || is_wp_error( Sync_Graph::order( $edges, 'id', 'parent' ) ) ) {
			return self::error(); }
		$ids      = array_column( $edges, 'id' );
		$external = array_values( array_diff( array_unique( array_filter( array_column( $edges, 'parent' ) ) ), $ids ) );
		$parents  = $hierarchy['parents'];
		foreach ( $parents as $source => $target ) {
			if ( ! preg_match( '/^[1-9][0-9]*$/D', (string) $source ) || ! is_int( $target ) || $target < 1 ) {
				return self::error(); }
		}
		return array_diff( $external, array_keys( $parents ) ) || array_diff( array_keys( $parents ), $external ) || count( $parents ) !== count( array_unique( $parents ) ) ? self::error() : true;
	}

	/**
	 * Recheck live source edges before a child makes any remote request.
	 *
	 * @param string $kind Domain.
	 * @param string $type Subtype.
	 * @param array  $ids Selected sources.
	 * @param array  $hierarchy Resolved external parents.
	 * @return true|\WP_Error
	 */
	public static function outgoing( $kind, $type, array $ids, array $hierarchy ) {
		$provider = self::provider( $kind, $type );
		if ( is_wp_error( $provider ) ) {
			return $provider; }
		$edges = [];
		foreach ( $ids as $id ) {
			$parent = call_user_func( $provider['read'], $id );
			if ( is_wp_error( $parent ) ) {
				return $parent; }
			$edges[] = [
				'id'     => $id,
				'parent' => $parent,
			];
		}
		return self::validate( $edges, $hierarchy );
	}

	/**
	 * Check the peer before sending content or media; old peers fail closed.
	 *
	 * @param array  $site Authorized connection.
	 * @param string $kind Domain.
	 * @param string $type Subtype.
	 * @return true|\WP_Error
	 */
	public static function probe( array $site, $kind, $type ) {
		$response = Remote_API::post(
			$site['remote_url'],
			'migration-hierarchy-check',
			[
				'timeout' => 30,
				'headers' => [
					'Authorization' => 'Bearer ' . $site['api_key'],
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode(
					[
						'kind' => $kind,
						'type' => $type,
					]
				),
			]
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return self::error(); }
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		return 1 === ( $data['hierarchy_version'] ?? null ) && ( $data['kind'] ?? '' ) === $kind && ( $data['type'] ?? '' ) === $type ? true : self::error();
	}

	/**
	 * Wrap an existing transport only when the versioned protocol was requested.
	 *
	 * @param string $kind Domain.
	 * @param string $type Subtype.
	 * @param array  $payload Native transport data.
	 * @param array  $input Native ability input.
	 * @return array
	 */
	public static function envelope( $kind, $type, array $payload, array $input ) {
		return isset( $input['hierarchy'] ) ? [
			'kind'      => $kind,
			'type'      => $type,
			'payload'   => $payload,
			'hierarchy' => $input['hierarchy'],
		] : $payload;
	}

	/**
	 * Derive only cross-part parent destinations from confirmed earlier maps.
	 *
	 * @param array $step Frozen parent step.
	 * @param array $maps Confirmed typed identities.
	 * @return array|\WP_Error
	 */
	public static function resolve( array $step, array $maps ) {
		$input   = $step['params']['input'];
		$kind    = isset( $input['taxonomy'] ) ? 'taxonomy' : 'post_type';
		$type    = $input[ $kind ];
		$edges   = $step['hierarchy_edges'];
		$parents = [];
		foreach ( array_diff( array_filter( array_column( $edges, 'parent' ) ), array_column( $edges, 'id' ) ) as $parent ) {
			$target = $maps[ $kind . ':' . $type . ':' . $parent ] ?? 0;
			if ( ! is_int( $target ) || $target < 1 ) {
				return self::error(); }
			$parents[ $parent ] = $target;
		}
		$input['hierarchy'] = [
			'version' => 1,
			'parents' => $parents,
		];
		if ( preg_match( '/-pull$/D', $step['params']['operation'] ) ) {
			$input['hierarchy']['edges'] = $edges; }
		return $input;
	}

	/**
	 * Authenticated peer capability and receive callbacks.
	 *
	 * @param \WP_REST_Request $request Authenticated request.
	 * @param bool             $receive Whether content should be applied.
	 * @return array|\WP_Error
	 */
	public static function peer( $request, $receive = false ) {
		$schema = S::object(
			[
				'kind' => S::string( [ 'post_type', 'taxonomy' ] ),
				'type' => S::string() + [
					'pattern'   => '^[a-z0-9_-]+$',
					'minLength' => 1,
					'maxLength' => 32,
				],
			] + ( $receive ? [
				'payload'   => [ 'type' => 'object' ],
				'hierarchy' => self::schema(),
			] : [] ),
			$receive ? [ 'kind', 'type', 'payload', 'hierarchy' ] : [ 'kind', 'type' ]
		);
		$input  = $request->get_params();
		if ( is_wp_error( rest_validate_value_from_schema( $input, $schema ) ) ) {
			return self::error(); }
		$provider = self::provider( $input['kind'], $input['type'] );
		if ( is_wp_error( $provider ) ) {
			return $provider; }
		if ( ! $receive ) {
			return [
				'hierarchy_version' => 1,
				'kind'              => $input['kind'],
				'type'              => $input['type'],
			]; }
		$payload = $input['payload'];
		if ( isset( $payload[ $input['kind'] ] ) && $payload[ $input['kind'] ] !== $input['type'] ) {
			return self::error(); }
		$records = $payload[ $provider['records'] ] ?? [];
		if ( ! is_array( $records ) ) {
			return self::error(); }
		$edges = [];
		foreach ( $records as $record ) {
			if ( ! is_array( $record ) || ( $record[ $input['kind'] ] ?? $payload[ $input['kind'] ] ?? '' ) !== $input['type'] ) {
				return self::error(); }
			$edges[] = [
				'id'     => $record[ $provider['id'] ] ?? 0,
				'parent' => $record[ $provider['parent'] ] ?? 0,
			];
		}
		$valid = self::validate( $edges, $input['hierarchy'] );
		if ( is_wp_error( $valid ) ) {
			return $valid; }
		foreach ( $input['hierarchy']['parents'] as $target ) {
			$parent = call_user_func( $provider['read'], $target );
			if ( is_wp_error( $parent ) ) {
				return $parent; }
		}
		$explicit = $payload[ $provider['mapping'] ] ?? [];
		if ( ! is_array( $explicit ) || array_intersect( $explicit, $input['hierarchy']['parents'] ) ) {
			return self::error(); }
		// All records enter through their native preflight/importer, then exact parent finalization.
		// No slug/original-ID fallback may guess a parent outside this request.
		foreach ( $payload[ $provider['records'] ] as &$record ) {
			$record[ $provider['parent'] ] = 0;
			unset( $record['parent_path'], $record['parent_slug'] );
		}
		unset( $record );
		$response = call_user_func( $provider['receive'], $payload );
		if ( is_wp_error( $response ) ) {
			return $response; }
		$data = $response instanceof \WP_REST_Response ? $response->get_data() : $response;
		if ( empty( $data['success'] ) || ! empty( $data['failed'] ) ) {
			return self::error(); }
		$mapping = call_user_func( $provider['maps'], $data );
		$valid   = self::finalize( $input['kind'], $input['type'], $edges, $input['hierarchy'], $mapping );
		if ( is_wp_error( $valid ) ) {
			return $valid; }
		$data['hierarchy_version'] = 1;
		return apply_filters( 'rsl_ie_sync_hierarchy_result', $data, $input['kind'], $input['type'], $payload );
	}

	/** Describe frozen source edges plus confirmed local external parents for PULL. */
	public static function pull_schema() {
		$schema                                    = self::schema();
		$schema['properties']['edges'] = S::items( S::object( [ 'id' => S::integer( 1 ), 'parent' => S::integer() ], [ 'id', 'parent' ] ), 50 );
		$schema['required'][]                      = 'edges';
		return $schema;
	}

	/**
	 * Authorize the local destinations of an immutable pull hierarchy.
	 *
	 * @param string $kind Identity namespace.
	 * @param string $type Concrete domain.
	 * @param array  $ids Remote source IDs.
	 * @param array  $hierarchy Frozen edges and local external parents.
	 * @return true|\WP_Error
	 */
	public static function incoming( $kind, $type, array $ids, array $hierarchy ) {
		if ( is_wp_error( rest_validate_value_from_schema( $hierarchy, self::pull_schema() ) ) ) {
			return self::error(); }
		$protocol = $hierarchy;
		unset( $protocol['edges'] );
		if ( is_wp_error( self::validate( $hierarchy['edges'], $protocol ) ) || count( $ids ) !== count( $hierarchy['edges'] ) || array_diff( $ids, array_column( $hierarchy['edges'], 'id' ) ) ) {
			return self::error(); }
		$provider = self::provider( $kind, $type );
		if ( is_wp_error( $provider ) ) {
			return $provider; }
		foreach ( $hierarchy['parents'] as $target ) {
			$parent = call_user_func( $provider['read'], $target );
			if ( is_wp_error( $parent ) ) {
				return $parent; }
		}
		return true;
	}

	/**
	 * Verify actual payload edges before downloads or writes, then defer parent assignment.
	 *
	 * @param string $kind Domain.
	 * @param string $type Subtype.
	 * @param array  $records Authorized native records.
	 * @param array  $hierarchy Frozen edge evidence and local parents.
	 * @param array  $mapping Source-to-local explicit targets.
	 * @return array|\WP_Error
	 */
	public static function prepare_pull( $kind, $type, array $records, array $hierarchy, array $mapping ) {
		$provider = self::provider( $kind, $type );
		if ( is_wp_error( $provider ) ) {
			return $provider; }
		$valid = self::incoming( $kind, $type, array_column( $records, $provider['id'] ), $hierarchy );
		if ( is_wp_error( $valid ) || array_intersect( $mapping, $hierarchy['parents'] ) ) {
			return self::error(); }
		$expected = array_column( $hierarchy['edges'], 'parent', 'id' );
		foreach ( $records as &$record ) {
			$id = $record[ $provider['id'] ];
			if ( ! is_int( $record[ $provider['parent'] ] ?? null ) || ( $expected[ $id ] ?? null ) !== $record[ $provider['parent'] ] ) {
				return self::error(); }
			$record[ $provider['parent'] ] = 0;
			unset( $record['parent_path'], $record['parent_slug'] );
		}
		unset( $record );
		return $records;
	}

	/**
	 * Confirm exact parent destinations before a transfer publishes its maps.
	 *
	 * @param string $kind Domain.
	 * @param string $type Subtype.
	 * @param array  $edges Frozen edges.
	 * @param array  $hierarchy External parents.
	 * @param array  $mapping Actual source-to-destination identities.
	 * @return true|\WP_Error
	 */
	public static function finalize( $kind, $type, array $edges, array $hierarchy, array $mapping ) {
		$provider = self::provider( $kind, $type );
		if ( is_wp_error( $provider ) ) {
			return $provider; }
		if ( count( $mapping ) !== count( $edges ) || array_diff( array_column( $edges, 'id' ), array_keys( $mapping ) ) || count( $mapping ) !== count( array_unique( $mapping ) ) || array_intersect( $mapping, $hierarchy['parents'] ) ) {
			return self::error(); }
		$valid = Migration_Sync_Maps::selected( $kind, $type, array_column( $edges, 'id' ), $mapping );
		if ( is_wp_error( $valid ) ) {
			return $valid; }
		$ordered = Sync_Graph::order( $edges, 'id', 'parent' );
		foreach ( $ordered as $edge ) {
			$id       = (int) $mapping[ $edge['id'] ];
			$parent   = $edge['parent'] ? (int) ( $mapping[ $edge['parent'] ] ?? $hierarchy['parents'][ $edge['parent'] ] ?? 0 ) : 0;
			$seen     = [ $id => true ];
			$ancestor = $parent;
			// Refuse destination cycles, inaccessible ancestors and unbounded chains.
			while ( $ancestor ) {
				if ( isset( $seen[ $ancestor ] ) || count( $seen ) > 2500 ) {
					return self::error(); }
				$seen[ $ancestor ] = true;
				$ancestor          = call_user_func( $provider['read'], $ancestor );
				if ( is_wp_error( $ancestor ) ) {
					return $ancestor; }
			}
			$current = call_user_func( $provider['read'], $id );
			if ( is_wp_error( $current ) ) {
				return $current; }
			if ( $current !== $parent ) {
				$written = call_user_func( $provider['write'], $id, $parent );
				if ( is_wp_error( $written ) ) {
					return $written; }
			}
		}
		return true;
	}

	/**
	 * Safe stable error, including partial/unacknowledged transfers.
	 *
	 * @return \WP_Error
	 */
	public static function error() {
		return new \WP_Error( 'rsl_ie_sync_hierarchy_unavailable', __( 'The peer could not confirm the requested hierarchy. Inspect completed transfers before repeating.', 'import-export-by-rockstarlab' ), [ 'status' => 409 ] );
	}
}

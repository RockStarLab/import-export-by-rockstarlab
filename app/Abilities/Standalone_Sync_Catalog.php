<?php
/**
 * Standalone comment synchronization contracts.
 *
 * @package RockStarLab\ImportExport\Abilities
 */

namespace RockStarLab\ImportExport\Abilities;

defined( 'ABSPATH' ) || exit;
/** Shared schema builder; premium registrations are assembled by Pro. */
class Standalone_Sync_Catalog {
	/**
	 * First sentence of an ability description: what this one ability does.
	 *
	 * @param string $family    comments, reviews or terms.
	 * @param string $operation browse, search-local, push or pull.
	 * @return string
	 */
	private static function lead( $family, $operation ) {
		$what = 'terms' === $family ? 'taxonomy terms' : ( 'reviews' === $family ? 'product reviews' : 'comments' );
		switch ( $operation ) {
			case 'browse':
				return 'terms' === $family ? 'List the taxonomy terms of the connected site, to choose what to pull.' : 'List the ' . $what . ' of one ' . ( 'reviews' === $family ? 'product' : 'post' ) . ' on the connected site (post_id), to choose what to pull.';
			case 'search-local':
				return 'Search the taxonomy terms of this site, to choose where pushed or pulled terms go.';
			case 'push':
				return 'Send selected ' . $what . ' from this site to the connected site.';
			case 'pull':
				return 'Copy selected ' . $what . ' from the connected site to this site.';
		}
		return 'Synchronize selected ' . $what . '.';
	}

	/**
	 * Build explicit selection and destination contracts.
	 *
	 * @param string $family comments, reviews or terms.
	 * @param bool   $premium Whether the provider is premium.
	 * @return array
	 */
	public static function definitions( $family = 'comments', $premium = false ) {
		$id          = Workflow_Schemas::integer( 1 );
		$ids         = Workflow_Schemas::items( $id, 50 ) + [
			'minItems'    => 1,
			'uniqueItems' => true,
		];
		$map         = [
			'type'                 => 'object',
			'additionalProperties' => $id,
			'maxProperties'        => 50,
		];
		$terms       = 'terms' === $family;
		$type        = $terms ? 'taxonomy' : 'post_type';
		$type_schema = $premium ? Workflow_Schemas::string() + [
			'pattern'   => '^[a-z0-9_-]+$',
			'maxLength' => 32,
			'minLength' => 1,
		] : Workflow_Schemas::string( [ 'post', 'page' ] );
		if ( 'reviews' === $family ) {
			$type_schema = Workflow_Schemas::string( [ 'product' ] ); }
		$definitions = [];
		foreach ( $terms ? [ 'browse', 'search-local', 'push', 'pull' ] : [ 'browse', 'push', 'pull' ] as $operation ) {
			$read       = in_array( $operation, [ 'browse', 'search-local' ], true );
			$properties = [ $type => $type_schema ];
			$required   = [ $type ];
			if ( 'search-local' !== $operation ) {
				$properties['site_id'] = $id;
				$required[]            = 'site_id'; }
			if ( $read ) {
				$properties += [
					'search'   => Workflow_Schemas::string() + [ 'maxLength' => 200 ],
					'page'     => Workflow_Schemas::integer( 1, 10000 ),
					'per_page' => Workflow_Schemas::integer( 1, 50 ),
				];
				if ( ! $terms ) {
					$properties['post_id'] = $id;
					$required[]            = 'post_id'; }
			} else {
				$key                = $terms ? 'term_ids' : 'comment_ids';
				$properties[ $key ] = $ids;
				$required[]         = $key;
				if ( $terms ) {
					$properties['term_mapping'] = $map;
					$properties['hierarchy']    = 'pull' === $operation ? \RockStarLab\ImportExport\Service\Sync_Hierarchy::pull_schema() : \RockStarLab\ImportExport\Service\Sync_Hierarchy::schema();
				} else {
					$properties['target_post_id'] = $id;
					$properties['post_mapping']   = $map; }
			}
			if ( 'comments' === $family ) {
				$properties['comment_type'] = Workflow_Schemas::string( [ '', 'comment', 'pingback', 'trackback' ] ); }
			$input = Workflow_Schemas::object( $properties, $required );
			if ( ! $terms && ! $read ) {
				$input['anyOf'] = [ [ 'required' => [ 'target_post_id' ] ], [ 'required' => [ 'post_mapping' ] ] ]; }
			$name                 = 'sync-' . $family . '-' . $operation;
			$definitions[ $name ] = [
				'action'      => ( 'browse' === $operation ? 'get_remote' : ( 'search-local' === $operation ? 'search_local' : $operation ) ) . '_' . ( $terms ? 'terms' : 'comments' ),
				'handler'     => 'standalone',
				'domain'      => $terms ? 'terms' : ( 'reviews' === $family ? 'reviews' : ( $premium ? 'pro-comments' : 'comments' ) ),
				'label'       => ucwords( str_replace( '-', ' ', $name ) ),
				'description' => self::lead( $family, $operation ) . ' Maximum 50 IDs.' . ( $terms ? '' : ' Browse on an explicit remote post_id.' ) . ' For push, selected IDs and mapping keys are local; mapping values and target_post_id are remote. For pull, selected IDs and mapping keys are remote; mapping values and target_post_id are local. Comment post_mapping maps source parent post IDs to destination post IDs; term_mapping maps source term IDs to destination term IDs. Unmapped terms match slug or are created. Comment destinations must be explicit. Preserves hierarchy, metadata and ACF/media. Synchronous transfer returns counters and source-to-target mapping; inspect destination after timeout before repeating. No background lifecycle or automatic replay. Term pull accepts optional hierarchy with exact source edges and confirmed external local parents; finalization must succeed before the operation reports success.',
				'input'       => $input,
				'output'      => Workflow_Schemas::object(
					[
						'success'      => [ 'type' => 'boolean' ],
						'message'      => Workflow_Schemas::string(),
						'comments'     => Workflow_Schemas::items( [ 'type' => 'object' ], 50 ),
						'terms'        => Workflow_Schemas::items( [ 'type' => 'object' ], 50 ),
						'total'        => Workflow_Schemas::integer(),
						'pages'        => Workflow_Schemas::integer( 1 ),
						'current_page' => $id,
						'per_page'     => $id,
						'created'      => Workflow_Schemas::integer(),
						'updated'      => Workflow_Schemas::integer(),
						'failed'       => Workflow_Schemas::integer(),
						'mapping'      => $map,
					]
				),
				'readonly'    => $read,
				'destructive' => ! $read,
				'idempotent'  => $read,
			];
		}
		return apply_filters( 'rsl_ie_sync_standalone_definitions', $definitions );
	}
}

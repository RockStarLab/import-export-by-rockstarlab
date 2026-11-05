<?php
/**
 * Confirmed cross-step identities for site-to-site migration.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

/** Typed keys keep post IDs and term IDs in separate identity spaces. */
class Migration_Sync_Maps {
	/**
	 * Extract only confirmed selected identities; premium extraction stays in Pro.
	 *
	 * @param array $maps Persisted identities.
	 * @param array $params Child parameters.
	 * @param array $result Confirmed public child result.
	 * @return array|\WP_Error
	 */
	public static function capture( array $maps, array $params, array $result ) {
		$found = [];
		if ( 'free' === $params['edition'] && in_array( $params['operation'], [ 'sync-push', 'sync-pull' ], true ) ) {
			$found = self::posts( $params['input'], $result );
		} elseif ( 'free' === $params['edition'] && in_array( $params['operation'], [ 'sync-media-push', 'sync-media-pull' ], true ) ) {
			$mapping = [];
			foreach ( $result['items'] ?? [] as $item ) {
				$source = $item['source_id'] ?? 0;
				if ( ! is_int( $source ) || $source < 1 || isset( $mapping[ $source ] ) || ! in_array( $item['status'] ?? '', [ 'created', 'skipped' ], true ) || ! empty( $item['error_code'] ) ) {
					return self::error(); }
				$mapping[ $source ] = $item['attachment_id'] ?? 0;
			}
			$found = self::selected( 'post_type', 'attachment', $params['input']['media_ids'], $mapping );
		} else {
			$found = apply_filters( 'rsl_ie_migration_sync_map_records', $found, $params, $result );
		}
		if ( is_wp_error( $found ) ) {
			return $found;
		}
		foreach ( $found as $key => $target ) {
			if ( ! preg_match( '/^(post_type|taxonomy|user):[a-z0-9_-]+:[1-9][0-9]*$/D', $key ) || ! is_int( $target ) || $target < 1 || ( isset( $maps[ $key ] ) && $maps[ $key ] !== $target ) ) {
				return self::error();
			}
			$maps[ $key ] = $target;
		}
		ksort( $maps );
		return $maps;
	}

	/**
	 * Normalize post results in source-to-destination direction for push and pull.
	 *
	 * @param array $input Child selection.
	 * @param array $result Confirmed result.
	 * @return array|\WP_Error
	 */
	public static function posts( array $input, array $result ) {
		$mapping = [];
		foreach ( $result['result']['posts'] ?? [] as $post ) {
			$source = $post['source_id'] ?? 0;
			$target = $post['target_id'] ?? 0;
			if ( ! is_int( $source ) || ! is_int( $target ) || $source < 1 || $target < 1 || isset( $mapping[ $source ] ) || ( $post['type'] ?? '' ) !== $input['post_type'] ) {
				return self::error();
			}
			$mapping[ $source ] = $target;
		}
		return self::selected( 'post_type', $input['post_type'], $input['post_ids'], $mapping );
	}

	/**
	 * Require exactly the selected source IDs before publishing a typed map.
	 *
	 * @param string $kind Identity kind.
	 * @param string $type Post type or taxonomy.
	 * @param array  $ids Source selection.
	 * @param array  $mapping Source-to-target IDs.
	 * @return array|\WP_Error
	 */
	public static function selected( $kind, $type, array $ids, array $mapping ) {
		if ( count( $ids ) !== count( $mapping ) || array_diff( $ids, array_keys( $mapping ) ) || array_diff( array_keys( $mapping ), $ids ) ) {
			return self::error();
		}
		$result = [];
		foreach ( $mapping as $source => $target ) {
			if ( ! preg_match( '/^[1-9][0-9]*$/D', (string) $source ) || ! is_int( $target ) || $target < 1 ) {
				return self::error();
			}
			$result[ $kind . ':' . $type . ':' . $source ] = $target;
		}
		return $result;
	}

	/**
	 * Resolve an opted-in dependent input using executable provider code.
	 *
	 * @param array $step Frozen step.
	 * @param array $maps Confirmed earlier identities.
	 * @return array|\WP_Error
	 */
	public static function resolve( array $step, array $maps ) {
		if ( empty( $step['use_migration_maps'] ) ) {
			return $step['params']['input'];
		}
		if ( ! $maps ) {
			return self::error();
		}
		if ( in_array( $step['params']['operation'], [ 'sync-push', 'sync-pull' ], true ) ) {
			$input = apply_filters( 'rsl_ie_migration_sync_resolve_maps', $step['params']['input'], $step['params'], $maps, [] );
			return $input === $step['params']['input'] && empty( $step['params']['migration_map_page'] ) ? self::error() : $input;
		}

		if ( 'free' === $step['params']['edition'] && in_array( $step['params']['operation'], [ 'sync-media-push', 'sync-media-pull' ], true ) ) {
			return apply_filters( 'rsl_ie_migration_sync_resolve_maps', $step['params']['input'], $step['params'], $maps, [] );
		}
		$sources = $step['source_post_ids'] ?? [];
		if ( 'free' === $step['params']['edition'] && in_array( $step['params']['operation'], [ 'sync-comments-push', 'sync-comments-pull' ], true ) ) {
			return apply_filters( 'rsl_ie_migration_sync_resolve_maps', self::comments( $step['params']['input'], $maps, $sources ), $step['params'], $maps, $sources );
		}
		return apply_filters( 'rsl_ie_migration_sync_resolve_maps', self::error(), $step['params'], $maps, $sources );
	}

	/** Apply one map page through the same edition-owned resolvers. */
	public static function resolve_page( array $step, array $maps, array $previous ) {
		$step['params']['migration_map_page'] = true;
		$input                                = self::resolve( $step, $maps );
		$input                                = Sync_ACF_Posts::resolve( $input, $maps );
		if ( is_wp_error( $input ) ) {
			return $input;
		}
		foreach ( $input as $field => $value ) {
			if ( isset( $previous[ $field ] ) && $previous[ $field ] !== $value ) {
				if ( ! is_array( $value ) || ! is_array( $previous[ $field ] ) || substr( $field, -8 ) !== '_mapping' ) {
					return self::error();
				}
				foreach ( $value as $key => $target ) {
					if ( isset( $previous[ $field ][ $key ] ) && $previous[ $field ][ $key ] !== $target ) {
						return self::error();
					}
				}
				$value = $previous[ $field ] + $value;
			}
			$previous[ $field ] = $value;
		}
		return $previous;
	}

	/**
	 * Resolve only declared source parents from confirmed maps of the exact type.
	 *
	 * @param array $input Comment selection without destination overrides.
	 * @param array $maps Typed confirmed maps.
	 * @param array $sources Exact selected source parent IDs.
	 * @return array|\WP_Error
	 */
	public static function comments( array $input, array $maps, array $sources ) {
		if ( ! $sources || isset( $input['post_mapping'] ) || isset( $input['target_post_id'] ) ) {
			return self::error();
		}
		$mapping = [];
		foreach ( $sources as $source ) {
			$key = 'post_type:' . $input['post_type'] . ':' . $source;
			if ( empty( $maps[ $key ] ) ) {
				return self::error();
			}
			$mapping[ $source ] = $maps[ $key ];
		}
		$input['post_mapping'] = $mapping;
		return $input;
	}

	/**
	 * Stable failure without raw peer content.
	 *
	 * @return \WP_Error
	 */
	public static function error() {
		return new \WP_Error( 'rsl_ie_migration_mapping_unavailable', __( 'Migration mappings are incomplete, conflicting or unavailable. Inspect completed steps before starting another migration.', 'import-export-by-rockstarlab' ), [ 'status' => 409 ] );
	}
}

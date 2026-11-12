<?php
/**
 * Confirmed ACF post references across bounded post transfers.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Sync_Peer_Policy;
use RockStarLab\ImportExport\Helper\Remote_API;

defined( 'ABSPATH' ) || exit;

/** Resolve selected references after all posts exist, without slug matching. */
class Sync_ACF_Posts {
	/**
	 * Advertise the explicit protocol on the shared post catalog.
	 *
	 * @param array $definitions Existing definitions.
	 * @return array
	 */
	public static function definitions( array $definitions ) {
		foreach ( [ 'sync-push', 'sync-pull' ] as $operation ) {
			$definitions[ $operation ]['input']['properties']['acf_post_mapping'] = [
				'type'                 => 'object',
				'additionalProperties' => [
					'type'    => 'integer',
					'minimum' => 1,
				],
			];
			$definitions[ $operation ]['description']                            .= ' Optional acf_post_mapping enables confirmed ACF Post Object, Relationship and Page Link references. Keys are post_type:TYPE:SOURCE_ID and values are destination IDs in both directions. An empty object resolves references within this batch, including self/cycles. External references require explicit or earlier confirmed parent maps; future batches are not resolved. Both peers need ACF and compatible field definitions. Missing references, unknown definitions and bidirectional fields fail closed. Free supports post/page references; premium types require their PRO policy. No slug/original-ID fallback is used for these fields in this mode.';
		}
		return $definitions;
	}

	/**
	 * Merge earlier typed identities into an explicitly opted-in child.
	 *
	 * @param mixed $input Earlier resolved input.
	 * @param array $maps Confirmed parent maps.
	 * @return mixed
	 */
	public static function resolve( $input, array $maps ) {
		if ( is_wp_error( $input ) || ! isset( $input['acf_post_mapping'] ) ) {
			return $input;
		}
		foreach ( $maps as $key => $target ) {
			if ( 0 !== strpos( $key, 'post_type:' ) ) {
				continue;
			}
			if ( isset( $input['acf_post_mapping'][ $key ] ) && $input['acf_post_mapping'][ $key ] !== $target ) {
				return Migration_Sync_Maps::error();
			}
			$input['acf_post_mapping'][ $key ] = $target;
		}
		return $input;
	}

	/**
	 * Export evidence, prepare assignments, or verify final destination values.
	 *
	 * @param string $operation Server-selected operation.
	 * @param array  $data Actual post payloads or prepared assignments.
	 * @param array  $context Mapping, connection or imported identities.
	 * @return array|true|\WP_Error
	 */
	public static function run( $operation, array $data, array $context = [] ) {
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		if ( ! function_exists( 'acf_get_field' ) || ! in_array( $operation, [ 'export', 'prepare', 'finalize', 'verify' ], true ) ) {
			return Migration_Sync_Maps::error();
		}
		if ( in_array( $operation, [ 'finalize', 'verify' ], true ) ) {
			return self::finalize( $data, $context['posts'] ?? [], 'verify' === $operation );
		}
		$selected = $context['selected'] ?? [];
		foreach ( $data as $post ) {
			$selected[ (int) $post['ID'] ] = $post['post_type'];
		}
		$mapping = $context['mapping'] ?? [];
		if ( ! is_array( $mapping ) ) {
			return Migration_Sync_Maps::error();
		}
		$outgoing = 'export' === $operation || ! empty( $context['outgoing'] );
		foreach ( $mapping as $key => $target ) {
			if ( ! preg_match( '/^post_type:([a-z0-9_-]+):([1-9][0-9]*)$/D', $key, $match ) || (string) (int) $match[2] !== $match[2] || ! is_int( $target ) || $target < 1 || isset( $selected[ (int) $match[2] ] ) ) {
				return Migration_Sync_Maps::error();
			}
			$access = Sync_Peer_Policy::destination( $outgoing ? (int) $match[2] : $target, $match[1] );
			if ( is_wp_error( $access ) ) {
				return $access;
			}
		}
		$assignments = [];
		foreach ( $data as &$post ) {
			$fields = self::fields( (array) ( $post['meta'] ?? [] ) );
			if ( is_wp_error( $fields ) ) {
				return $fields;
			}
			if ( 'prepare' === $operation && ( 1 !== ( $post['acf_posts_version'] ?? null ) || wp_json_encode( $fields ) !== wp_json_encode( $post['acf_post_fields'] ?? null ) ) ) {
				return Migration_Sync_Maps::error();
			}
			$record = [
				'type'   => $post['post_type'],
				'fields' => [],
			];
			$types  = [];
			foreach ( $fields as $name => $field ) {
				$ids = self::ids( $post['meta'][ $name ], $field['multiple'] );
				if ( is_wp_error( $ids ) ) {
					return $ids;
				}
				$field['references'] = [];
				foreach ( $ids as $source ) {
					$type = $outgoing ? get_post_type( $source ) : ( $post['acf_post_types'][ $source ] ?? null );
					if ( ! is_string( $type ) || ! preg_match( '/^[a-z0-9_-]+$/D', $type ) || ( $field['post_types'] && ! in_array( $type, $field['post_types'], true ) ) ) {
						return Migration_Sync_Maps::error();
					}
					$access = Sync_Peer_Policy::post( $type, 'sync_push', $outgoing ? $source : 0 );
					if ( is_wp_error( $access ) ) {
						return $access;
					}
					$types[ $source ] = $type;
					$target           = $mapping[ 'post_type:' . $type . ':' . $source ] ?? null;
					if ( 'prepare' === $operation && ( isset( $selected[ $source ] ) ? $selected[ $source ] !== $type : ! $target ) ) {
						return Migration_Sync_Maps::error();
					}
					$field['references'][] = [
						'source' => $source,
						'type'   => $type,
						'target' => $target,
					];
				}
				$record['fields'][ $name ] = $field;
			}
			ksort( $types );
			if ( 'export' === $operation ) {
				$post['acf_posts_version'] = 1;
				$post['acf_post_fields']   = $fields;
				$post['acf_post_types']    = $types;
			} elseif ( wp_json_encode( $types ) !== wp_json_encode( $post['acf_post_types'] ?? null ) ) {
				return Migration_Sync_Maps::error();
			}
			$assignments[ (int) $post['ID'] ] = $record;
		}
		unset( $post );
		if ( 'export' === $operation ) {
			return $data;
		}
		if ( $outgoing ) {
			$site     = $context['site'];
			$response = Remote_API::post(
				$site['remote_url'],
				'validate',
				[
					'timeout' => 30,
					'headers' => [
						'Authorization' => 'Bearer ' . $site['api_key'],
						'Content-Type'  => 'application/json',
					],
					'body'    => '{}',
				]
			);
			$probe    = is_wp_error( $response ) ? [] : json_decode( wp_remote_retrieve_body( $response ), true );
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) || 1 !== ( $probe['data']['extensions']['acf_posts_version'] ?? null ) ) {
				return Migration_Sync_Maps::error();
			}
		}
		return $assignments;
	}

	/**
	 * Keep only validated reference metadata for the migration checkpoint.
	 *
	 * @param array $posts Actual post records with raw metadata.
	 * @return array|\WP_Error
	 */
	public static function snapshot( array $posts ) {
		$posts = self::run( 'export', $posts );
		if ( is_wp_error( $posts ) ) {
			return $posts;
		}
		$result = [];
		foreach ( $posts as $post ) {
			$meta = [];
			foreach ( $post['acf_post_fields'] as $name => $field ) {
				$meta[ $name ]       = \RockStarLab\ImportExport\Helper\Serialized::maybe( $post['meta'][ $name ] );
				$meta[ '_' . $name ] = $field['key'];
			}
			$result[] = [
				'ID'                => (int) $post['ID'],
				'post_type'         => $post['post_type'],
				'meta'              => $meta,
				'acf_post_fields'   => $post['acf_post_fields'],
				'acf_post_types'    => $post['acf_post_types'],
				'acf_posts_version' => 1,
			];
		}
		return $result;
	}

	/**
	 * Resolve intra-batch references only after all destinations exist.
	 *
	 * @param array $data Prepared assignments.
	 * @param array $posts Actual source-to-destination post identities.
	 * @param bool  $read_only Verify without writing.
	 * @return true|\WP_Error
	 */
	private static function finalize( array $data, array $posts, $read_only = false ) {
		foreach ( $data as $source => $record ) {
			$target = $posts[ $source ] ?? 0;
			if ( ! $target || is_wp_error( Sync_Peer_Policy::destination( $target, $record['type'] ) ) ) {
				return Migration_Sync_Maps::error();
			}
			foreach ( $record['fields'] as $name => $field ) {
				$definition = $field;
				unset( $definition['references'] );
				$live = self::fields(
					[
						$name       => 0,
						'_' . $name => $field['key'],
					]
				);
				if ( is_wp_error( $live ) || [ $name => $definition ] !== $live ) {
					return Migration_Sync_Maps::error();
				}
				$ids = [];
				foreach ( $field['references'] as $reference ) {
					$id = $reference['target'] ?? ( $posts[ $reference['source'] ] ?? 0 );
					if ( ! $id || is_wp_error( Sync_Peer_Policy::destination( $id, $reference['type'] ) ) ) {
						return Migration_Sync_Maps::error();
					}
					$ids[] = $id;
				}
				$value = $field['multiple'] ? $ids : ( $ids[0] ?? 0 );
				if ( ! $read_only ) {
					update_post_meta( $target, $name, $value );
					update_post_meta( $target, '_' . $name, $field['key'] );
				}
				if ( function_exists( 'acf_flush_value_cache' ) ) {
					acf_flush_value_cache( $target, $name );
				}
				if ( self::ids( get_post_meta( $target, $name, true ), $field['multiple'] ) !== $ids || get_post_meta( $target, '_' . $name, true ) !== $field['key'] ) {
					return Migration_Sync_Maps::error();
				}
			}
		}
		return true;
	}

	/**
	 * Inspect actual companion metadata and locally installed field definitions.
	 *
	 * @param array $meta Unformatted metadata.
	 * @return array|\WP_Error
	 */
	public static function fields( array $meta ) {
		$fields = [];
		foreach ( $meta as $name => $value ) {
			$key = $meta[ '_' . $name ] ?? null;
			if ( 0 === strpos( $name, '_' ) || ! is_string( $key ) || 0 !== strpos( $key, 'field_' ) ) {
				continue;
			}
			$field = acf_get_field( $key );
			if ( ! $field ) {
				return Migration_Sync_Maps::error();
			}
			if ( ! in_array( $field['type'] ?? '', [ 'post_object', 'relationship', 'page_link' ], true ) ) {
				continue;
			}
			if ( ! empty( $field['bidirectional'] ) || empty( $field['name'] ) || ! ( $name === $field['name'] || substr( $name, -strlen( '_' . $field['name'] ) ) === '_' . $field['name'] ) ) {
				return Migration_Sync_Maps::error();
			}
			// ACF stores its unrestricted (All post types) selection as an empty string.
			$types = array_values( (array) ( empty( $field['post_type'] ) ? [] : $field['post_type'] ) );
			sort( $types );
			$fields[ $name ] = [
				'key'        => $key,
				'type'       => $field['type'],
				'multiple'   => 'relationship' === $field['type'] || ! empty( $field['multiple'] ),
				'post_types' => $types,
			];
		}
		ksort( $fields );
		return $fields;
	}

	/**
	 * Parse raw ACF IDs without guessing from titles, URLs or numeric prefixes.
	 *
	 * @param mixed $value Raw field value.
	 * @param bool  $multiple Cardinality.
	 * @return array|\WP_Error
	 */
	public static function ids( $value, $multiple ) {
		$value = \RockStarLab\ImportExport\Helper\Serialized::maybe( $value );
		if ( in_array( $value, [ null, false, '', 0, '0', [] ], true ) ) {
			return [];
		}
		if ( $multiple ? ! is_array( $value ) : is_array( $value ) ) {
			return Migration_Sync_Maps::error();
		}
		$ids = [];
		foreach ( $multiple ? $value : [ $value ] as $id ) {
			if ( ( ! is_int( $id ) && ! is_string( $id ) ) || ! preg_match( '/^[1-9][0-9]*$/D', (string) $id ) || (string) (int) $id !== (string) $id ) {
				return Migration_Sync_Maps::error();
			}
			$ids[] = (int) $id;
		}
		return $ids;
	}

	/**
	 * Advertise version support under the authenticated peer identity.
	 *
	 * @param array $extensions Existing extensions.
	 * @return array
	 */
	public static function extensions( array $extensions ) {
		if ( function_exists( 'acf_get_field' ) && ! is_wp_error( Access_Policy::check_management() ) ) {
			$extensions['acf_posts_version'] = 1;
		}
		return $extensions;
	}
}

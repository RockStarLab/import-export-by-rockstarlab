<?php
/**
 * Verified ACF Image, File and Gallery references in post transfers.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Sync_Peer_Policy;
use RockStarLab\ImportExport\Helper\Content_Sync_Media;
use RockStarLab\ImportExport\Helper\Remote_API;

defined( 'ABSPATH' ) || exit;

/** Bind raw ACF references to transferred files and verify final stored IDs. */
class Sync_ACF_Media {
	/**
	 * Advertise the opt-in post protocol, inherited by the licensed PRO catalog.
	 *
	 * @param array $definitions Catalog.
	 * @return array
	 */
	public static function definitions( array $definitions ) {
		foreach ( [ 'sync-push', 'sync-pull' ] as $operation ) {
			$definitions[ $operation ]['input']['properties']['acf_media_references'] = [
				'type'    => 'boolean',
				'default' => false,
			];
			$definitions[ $operation ]['description']                                .= ' Optional acf_media_references verifies ACF Image/File/Gallery through actual transferred attachment IDs and SHA-256 file evidence. Both peers require ACF and matching field keys/types. Raw flattened nested fields and empty values are supported; gallery order is preserved. Missing mappings, changed bytes, incompatible definitions or unacknowledged writes fail. At most 50 distinct ACF attachments and files use the chunk transport without inline byte limits. This does not finalize fields on attachment/user/term records or nested Woo entities.';
		}
		return $definitions;
	}

	/**
	 * Advertise authenticated peer support.
	 *
	 * @param array $extensions Extensions.
	 * @return array
	 */
	public static function extensions( array $extensions ) {
		if ( function_exists( 'acf_get_field' ) && ! is_wp_error( Access_Policy::check_management() ) ) {
			$extensions['acf_media_version'] = 1;
		}
		return $extensions;
	}

	/**
	 * Freeze raw references and actual file evidence; include every linked file.
	 *
	 * @param array      $posts Source records.
	 * @param array      $images Existing media collection, updated on success.
	 * @param array|null $site PUSH peer to probe before media transfer.
	 * @param bool       $collect Collect transport images; false for read-only graph snapshots.
	 * @return array|\WP_Error
	 */
	public static function export( array $posts, array &$images, $site = null, $collect = true ) {
		$access = self::access();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		$proofs = [];
		$bytes  = 0;
		$media  = new Media_Library_Sync_Service();
		foreach ( $posts as &$post ) {
			$fields = self::fields( (array) ( $post['meta'] ?? [] ) );
			if ( is_wp_error( $fields ) || is_wp_error( Sync_Peer_Policy::destination( (int) $post['ID'], $post['post_type'] ) ) ) {
				return Migration_Sync_Maps::error();
			}
			$post['acf_media_version'] = 1;
			$post['acf_media_fields']  = $fields;
			$post['acf_media_values']  = [];
			foreach ( $fields as $name => $field ) {
				$ids = self::ids( $post['meta'][ $name ], $field['multiple'] );
				if ( is_wp_error( $ids ) ) {
					return $ids;
				}
				$post['acf_media_values'][ $name ] = $ids;
				foreach ( $ids as $id ) {
					if ( isset( $proofs[ $id ] ) ) {
						continue;
					}
					$proof = $media->reference_proof( $id, true );
					if ( is_wp_error( $proof ) ) {
						return $proof;
					}
					$proofs[ $id ] = $proof;
					$bytes        += $proof['size'];
					if ( $collect && ( count( $proofs ) > 50 ) ) {
						return Migration_Sync_Maps::error();
					}
					if ( ! $collect ) {
						continue;
					}
					$image = Content_Sync_Media::prepare_image_data( $id, 'acf_media_references' );
					if ( ! is_array( $image ) || empty( $image['attachment_id'] ) ) {
						return Migration_Sync_Maps::error();
					}
					$images[ $id ] = $image;
				}
			}
		}
		unset( $post );
		foreach ( $posts as &$post ) {
			$post['acf_media_proofs'] = [];
			foreach ( $post['acf_media_values'] as $ids ) {
				foreach ( $ids as $id ) {
					$post['acf_media_proofs'][ $id ] = $proofs[ $id ];
				}
			}
		}
		unset( $post );
		$valid = self::prepare( $posts, null, ! $collect );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		if ( null !== $site ) {
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
			if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) || 1 !== ( $probe['data']['extensions']['acf_media_version'] ?? null ) ) {
				return Migration_Sync_Maps::error();
			}
		}
		return $posts;
	}

	/**
	 * Freeze only raw managed fields and file evidence without media index writes.
	 *
	 * @param array $posts Actual source attachment records.
	 * @return array|\WP_Error
	 */
	public static function snapshot( array $posts ) {
		$images = [];
		$result = self::export( $posts, $images, null, false );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		foreach ( $result as &$post ) {
			$meta = [];
			foreach ( $post['acf_media_fields'] as $name => $field ) {
				$meta[ $name ]       = \RockStarLab\ImportExport\Helper\Serialized::maybe( $post['meta'][ $name ] );
				$meta[ '_' . $name ] = $field['key'];
			}
			$post         = array_intersect_key( $post, array_flip( [ 'ID', 'post_type', 'acf_media_version', 'acf_media_fields', 'acf_media_values', 'acf_media_proofs' ] ) );
			$post['meta'] = $meta;
		}
		unset( $post );
		return $result;
	}

	/**
	 * Validate the field contract, then resolve and verify actual local files.
	 *
	 * @param array      $posts Received records (before or after domain replacement).
	 * @param array|null $mapping Null validates evidence before downloading files.
	 * @return array|\WP_Error
	 */
	public static function prepare( array $posts, $mapping = null, $chunked = true ) {
		$access = self::access();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		if ( ! $posts || count( $posts ) > 50 || ( null !== $mapping && ! is_array( $mapping ) ) ) {
			return Migration_Sync_Maps::error();
		}
		$assignments = [];
		$proofs      = [];
		$bytes       = 0;
		$verified    = [];
		$media       = new Media_Library_Sync_Service();
		foreach ( $posts as $post ) {
			$fields   = self::fields( (array) ( $post['meta'] ?? [] ) );
			$values   = $post['acf_media_values'] ?? null;
			$evidence = $post['acf_media_proofs'] ?? null;
			if ( is_wp_error( $fields ) || 1 !== ( $post['acf_media_version'] ?? null ) || ( $post['acf_media_fields'] ?? null ) !== $fields || ! is_array( $values ) || array_keys( $fields ) !== array_keys( $values ) || ! is_array( $evidence ) || ( ! $chunked && count( $evidence ) > 50 ) || ! is_int( $post['ID'] ?? null ) || $post['ID'] < 1 || isset( $assignments[ $post['ID'] ] ) || is_wp_error( Sync_Peer_Policy::post( $post['post_type'] ?? '', 'sync_pull' ) ) ) {
				return Migration_Sync_Maps::error();
			}
			$record = [
				'type'   => $post['post_type'],
				'fields' => [],
			];
			$used   = [];
			foreach ( $fields as $name => $field ) {
				$ids = self::ids( $values[ $name ], true );
				if ( is_wp_error( $ids ) || $ids !== $values[ $name ] || ( ! $field['multiple'] && count( $ids ) > 1 ) ) {
					return Migration_Sync_Maps::error();
				}
				$field['targets'] = [];
				foreach ( $ids as $id ) {
					$proof = $evidence[ $id ] ?? null;
					if ( ! is_array( $proof ) || array_keys( $proof ) !== [ 'sha256', 'size', 'mime' ] || ! is_string( $proof['sha256'] ) || ! preg_match( '/^[a-f0-9]{64}$/D', $proof['sha256'] ) || ! is_int( $proof['size'] ) || $proof['size'] < 1 || ( ! $chunked && $proof['size'] > Media_Library_Sync_Service::FILE_LIMIT ) || ! is_string( $proof['mime'] ) || ! preg_match( '#^[a-z0-9.+-]+/[a-z0-9.+-]+$#D', $proof['mime'] ) || ( 'file' !== $field['type'] && 0 !== strpos( $proof['mime'], 'image/' ) ) || ( isset( $proofs[ $id ] ) && $proofs[ $id ] !== $proof ) ) {
						return Migration_Sync_Maps::error();
					}
					if ( ! isset( $proofs[ $id ] ) ) {
						$proofs[ $id ] = $proof;
						$bytes        += $proof['size'];
					}
					if ( ! $chunked && ( count( $proofs ) > 50 ) ) {
						return Migration_Sync_Maps::error();
					}
					$used[ $id ] = true;
					if ( null !== $mapping ) {
						$target = $mapping[ $id ] ?? 0;
						if ( ! isset( $verified[ $id ] ) ) {
							$actual = $media->reference_proof( $target, $chunked );
							if ( is_wp_error( $actual ) || $actual !== $proof ) {
								return Migration_Sync_Maps::error();
							}
							$verified[ $id ] = true;
						}
						$field['targets'][] = [
							'id'    => $target,
							'proof' => $proof,
						];
					}
				}
				$record['fields'][ $name ] = $field;
			}
			if ( count( $used ) !== count( $evidence ) ) {
				return Migration_Sync_Maps::error();
			}
			$assignments[ $post['ID'] ] = $record;
		}
		return $assignments;
	}

	/**
	 * Hold media fields out of the native importer until confirmed finalization.
	 *
	 * @param array $posts Validated received posts.
	 * @param array $assignments Prepared fields.
	 * @return array
	 */
	public static function strip( array $posts, array $assignments ) {
		foreach ( $posts as &$post ) {
			foreach ( $assignments[ $post['ID'] ]['fields'] as $name => $field ) {
				unset( $post['meta'][ $name ], $post['meta'][ '_' . $name ] );
			}
		}
		unset( $post );
		return $posts;
	}

	/**
	 * Recheck definitions, permissions and files; persist and read back raw IDs.
	 *
	 * @param array $assignments Validated media assignments.
	 * @param array $posts Confirmed source-to-local post map.
	 * @param bool  $read_only Verify without writing.
	 * @return true|\WP_Error
	 */
	public static function finalize( array $assignments, array $posts, $read_only = false, $chunked = true ) {
		$access = self::access();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		$media    = new Media_Library_Sync_Service();
		$verified = [];
		foreach ( $assignments as $source => $record ) {
			$target = $posts[ $source ] ?? 0;
			if ( ! $target || is_wp_error( Sync_Peer_Policy::destination( $target, $record['type'] ) ) ) {
				return Migration_Sync_Maps::error();
			}
			foreach ( $record['fields'] as $name => $field ) {
				$definition = $field;
				unset( $definition['targets'] );
				if ( self::fields(
					[
						$name       => 0,
						'_' . $name => $field['key'],
					]
				) !== [ $name => $definition ] ) {
					return Migration_Sync_Maps::error();
				}
				$ids = [];
				foreach ( $field['targets'] as $file ) {
					if ( ( $verified[ $file['id'] ] ?? $media->reference_proof( $file['id'], $chunked ) ) !== $file['proof'] ) {
						return Migration_Sync_Maps::error();
					}
					$verified[ $file['id'] ] = $file['proof'];
					$ids[]                   = $file['id'];
				}
				if ( ! $read_only ) {
					update_post_meta( $target, $name, $field['multiple'] ? $ids : ( $ids[0] ?? 0 ) );
					update_post_meta( $target, '_' . $name, $field['key'] );
				}
				acf_flush_value_cache( $target, $name );
				if ( self::ids( get_post_meta( $target, $name, true ), $field['multiple'] ) !== $ids || get_post_meta( $target, '_' . $name, true ) !== $field['key'] ) {
					return Migration_Sync_Maps::error();
				}
			}
		}
		// Recheck each distinct file once after metadata hooks have finished.
		foreach ( $verified as $id => $proof ) {
			if ( $media->reference_proof( $id, $chunked ) !== $proof ) {
				return Migration_Sync_Maps::error();
			}
		}
		return true;
	}

	/**
	 * Require management and the installed ACF runtime.
	 *
	 * @return true|\WP_Error
	 */
	private static function access() {
		$access = Access_Policy::check_management();
		return is_wp_error( $access ) ? $access : ( function_exists( 'acf_get_field' ) ? true : Migration_Sync_Maps::error() );
	}

	/**
	 * Resolve companion field keys including flattened nested metadata.
	 *
	 * @param array $meta Raw post metadata.
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
			if ( ! in_array( $field['type'] ?? '', [ 'image', 'file', 'gallery' ], true ) ) {
				continue;
			}
			if ( empty( $field['name'] ) || ! ( $name === $field['name'] || substr( $name, -strlen( '_' . $field['name'] ) ) === '_' . $field['name'] ) || ! empty( $field['bidirectional'] ) ) {
				return Migration_Sync_Maps::error();
			}
			$fields[ $name ] = [
				'key'      => $key,
				'type'     => $field['type'],
				'multiple' => 'gallery' === $field['type'],
			];
		}
		ksort( $fields );
		return count( $fields ) > 500 ? Migration_Sync_Maps::error() : $fields;
	}

	/**
	 * Parse raw IDs without guessing from URLs or titles.
	 *
	 * @param mixed $value Stored value.
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
}

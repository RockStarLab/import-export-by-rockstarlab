<?php
/**
 * Deferred post-reference fields carried by Media Library transfers.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

/** Clear portable relation lookups until the parent has confirmed identities. */
class Sync_ACF_Media_Fields {
	/**
	 * Blank typed relation fields without interpreting ordinary text as a lookup.
	 *
	 * @param mixed $value Portable ACF value.
	 * @param array $field Installed field definition.
	 * @param int   $depth Bounded nesting depth.
	 * @param bool  $media_values Hold Image/File/Gallery for graph finalization.
	 * @return mixed
	 */
	public static function hold( $value, array $field, $depth = 0, $media_values = false ) {
		return self::walk(
			$value,
			$field,
			static function ( $value, $field ) use ( $media_values ) {
				$type = $field['type'] ?? '';
				if ( $media_values && in_array( $type, [ 'image', 'file', 'gallery' ], true ) ) {
					return 'gallery' === $type ? [] : 0;
				}
				if ( in_array( $type, [ 'post_object', 'relationship', 'page_link' ], true ) ) {
					return wp_json_encode(
						[
							'acf_type' => 'relation',
							'values'   => [],
							'single'   => 'relationship' !== $type && empty( $field['multiple'] ),
						]
					);
				}
				return $value;
			},
			$depth
		);
	}

	/**
	 * Visit typed portable leaves while preserving bounded ACF containers.
	 *
	 * @param mixed    $value Portable value.
	 * @param array    $field Installed definition.
	 * @param callable $visitor Typed leaf transformation.
	 * @param int      $depth Nesting depth.
	 * @return mixed
	 */
	public static function walk( $value, array $field, $visitor, $depth = 0 ) {
		if ( $depth > 32 ) {
			return Migration_Sync_Maps::error();
		}
		$type = $field['type'] ?? '';
		if ( ! in_array( $type, [ 'group', 'repeater', 'flexible_content' ], true ) ) {
			return call_user_func( $visitor, $value, $field );
		}
		$encoded = is_string( $value );
		$parsed  = $encoded ? json_decode( $value, true ) : $value;
		if ( ! is_array( $parsed ) || ( $parsed['acf_type'] ?? null ) !== $type ) {
			return Migration_Sync_Maps::error();
		}
		if ( 'group' === $type ) {
			if ( ! is_array( $parsed['value'] ?? null ) ) {
				return Migration_Sync_Maps::error();
			}
			$parsed['value'] = self::row( $parsed['value'] ?? [], $field['sub_fields'] ?? [], $depth, $visitor );
			if ( is_wp_error( $parsed['value'] ) ) {
				return $parsed['value'];
			}
		} else {
			if ( ! is_array( $parsed['rows'] ?? null ) ) {
				return Migration_Sync_Maps::error();
			}
			$layouts = array_column( $field['layouts'] ?? [], null, 'name' );
			foreach ( $parsed['rows'] as &$row ) {
				if ( ! is_array( $row ) || ( 'flexible_content' === $type && ( ! is_string( $row['layout'] ?? null ) || ! is_array( $row['value'] ?? null ) ) ) ) {
					return Migration_Sync_Maps::error();
				}
				if ( 'flexible_content' === $type ) {
					$layout = $layouts[ $row['layout'] ?? '' ] ?? null;
					if ( ! $layout ) {
						return Migration_Sync_Maps::error();
					}
					$row['value'] = self::row( $row['value'], $layout['sub_fields'] ?? [], $depth, $visitor );
					if ( is_wp_error( $row['value'] ) ) {
						return $row['value'];
					}
				} else {
					$row = self::row( $row, $field['sub_fields'] ?? [], $depth, $visitor );
					if ( is_wp_error( $row ) ) {
						return $row;
					}
				}
			}
			unset( $row );
		}
		return $encoded ? wp_json_encode( $parsed ) : $parsed;
	}

	/**
	 * Preserve scalar/container values while visiting their actual definitions.
	 *
	 * @param array    $row Portable row.
	 * @param array    $fields Installed subfields.
	 * @param int      $depth Nesting depth.
	 * @param callable $visitor Typed leaf transformation.
	 * @return array|\WP_Error
	 */
	private static function row( array $row, array $fields, $depth, $visitor ) {
		foreach ( $fields as $field ) {
			$name = $field['name'] ?? '';
			if ( array_key_exists( $name, $row ) ) {
				$row[ $name ] = self::walk( $row[ $name ], $field, $visitor, $depth + 1 );
				if ( is_wp_error( $row[ $name ] ) ) {
					return $row[ $name ];
				}
			}
		}
		return $row;
	}

	/**
	 * Verify the attachment object file itself, even without a self-reference.
	 *
	 * @param array      $posts Graph snapshots.
	 * @param array|null $maps Confirmed maps, or null before transfer.
	 * @param bool       $chunked Server-selected staged transport.
	 * @return true|\WP_Error
	 */
	public static function files( array $posts, $maps = null, $chunked = false ) {
		$files = [];
		foreach ( $posts as $post ) {
			if ( 'attachment' === $post['post_type'] ) {
				$files[] = [
					'acf_post_types'        => [ $post['ID'] => 'attachment' ],
					'acf_attachment_proofs' => [ $post['ID'] => $post['acf_object_file_proof'] ?? null ],
				];
			}
		}
		return Sync_ACF_Attachments::verify( $files, $maps, $chunked );
	}

	/**
	 * Resolve an unambiguous installed top-level media field definition.
	 *
	 * @param string $name Field name from the allowed media catalog.
	 * @return array|\WP_Error
	 */
	public static function definition( $name ) {
		$found   = [];
		$matched = array_column( acf_get_field_groups( [ 'attachment' => 'all' ] ), 'key' );
		foreach ( acf_get_field_groups() as $group ) {
			$eligible = in_array( $group['key'], $matched, true );
			foreach ( (array) ( $group['location'] ?? [] ) as $rules ) {
				foreach ( $rules as $rule ) {
					$eligible = $eligible || ( 'attachment' === ( $rule['param'] ?? '' ) && '==' === ( $rule['operator'] ?? '==' ) );
				}
			}
			if ( ! $eligible ) {
				continue;
			}
			foreach ( (array) acf_get_fields( $group ) as $field ) {
				if ( ( $field['name'] ?? '' ) === $name ) {
					$found[ $field['key'] ] = $field;
				}
			}
		}
		return 1 === count( $found ) ? reset( $found ) : Migration_Sync_Maps::error();
	}

	/** Narrow the owning parent's graph context to one staged attachment. */
	public static function chunk_request( $id ) {
		$request = Sync_ACF_Graph::request();
		if ( ! $request ) {
			return []; }
		if ( array_diff( array_keys( $request ), [ 'acf_graph_hashes', 'acf_attachment_media_version' ] ) || ! is_string( $request['acf_graph_hashes'][ $id ] ?? null ) || ! preg_match( '/^[a-f0-9]{64}$/D', $request['acf_graph_hashes'][ $id ] ) ) {
			return Migration_Sync_Maps::error(); }
		$request['acf_graph_hashes'] = [ $id => $request['acf_graph_hashes'][ $id ] ];
		return $request;
	}

	/**
	 * Confirm the source snapshot before sending portable media fields.
	 *
	 * @param array $ids Actual source attachment IDs.
	 * @param mixed $hashes Parent reference fingerprints.
	 * @return true|\WP_Error
	 */
	public static function source( array $ids, $hashes ) {
		$posts = [];
		foreach ( $ids as $id ) {
			$meta = [];
			foreach ( get_post_meta( $id ) as $key => $values ) {
				$meta[ $key ] = maybe_unserialize( $values[0] );
			}
			$posts[] = [
				'ID'        => (int) $id,
				'post_type' => 'attachment',
				'meta'      => $meta,
			];
		}
		$result = Sync_ACF_Graph::strip( $posts, $hashes );
		return is_wp_error( $result ) ? $result : true;
	}

	/**
	 * Validate a bounded graph transport envelope before importing files.
	 *
	 * @param array $input Peer payload.
	 * @return true|\WP_Error
	 */
	public static function envelope( array $input ) {
		if ( isset( $input['acf_attachment_media_version'] ) && 1 !== $input['acf_attachment_media_version'] ) {
			return Migration_Sync_Maps::error();
		}
		$hashes = $input['acf_graph_hashes'] ?? null;
		$items  = $input['media'] ?? null;
		if ( 1 !== ( $input['acf_media_fields_version'] ?? null ) || ! is_array( $hashes ) || ! $hashes || count( $hashes ) > 50 || ! is_array( $items ) || count( $items ) !== count( $hashes ) ) {
			return Migration_Sync_Maps::error();
		}
		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || ! is_array( $item['acf'] ?? [] ) ) {
				return Migration_Sync_Maps::error();
			}
		}
		$seen = [];
		foreach ( $items as $item ) {
			$id = $item['attachment_id'] ?? 0;
			if ( ! is_int( $id ) || $id < 1 || isset( $seen[ $id ] ) || ! is_string( $hashes[ $id ] ?? null ) || ! preg_match( '/^[a-f0-9]{64}$/D', $hashes[ $id ] ) ) {
				return Migration_Sync_Maps::error();
			}
			$seen[ $id ] = true;
		}
		return true;
	}

	/**
	 * Refuse ambiguous shared attachment destinations before any graph writes.
	 *
	 * @param array $snapshots All frozen graph batches.
	 * @param array $maps Confirmed typed identities.
	 * @return true|\WP_Error
	 */
	public static function unique_targets( array $snapshots, array $maps ) {
		$seen = [];
		foreach ( Migration_Snapshot_Store::iterate( $snapshots ) as $posts ) {
			if ( is_wp_error( $posts ) ) {
				return $posts; }
			foreach ( $posts as $post ) {
				if ( 'attachment' !== $post['post_type'] ) {
					continue;
				}
				$target = $maps[ 'post_type:attachment:' . $post['ID'] ] ?? 0;
				if ( ! is_int( $target ) || $target < 1 ) {
					return Migration_Sync_Maps::error();
				}
				$has_fields = ! empty( $post['acf_post_fields'] ) || ! empty( $post['acf_attachment_media']['acf_media_fields'] );
				if ( isset( $seen[ $target ] ) && ( $has_fields || $seen[ $target ] ) ) {
					return Migration_Sync_Maps::error();
				}
				$seen[ $target ] = $has_fields;
			}
		}
		return true;
	}
}

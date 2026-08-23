<?php
/**
 * Portable ACF field helpers for non-post objects.
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Portable ACF field helper.
 */
class ACF_Fields {

	/**
	 * Get ACF fields for an Import/Export content type.
	 *
	 * @param string $content_type Import/export content type.
	 * @param string $taxonomy     Optional taxonomy for term fields.
	 * @return array<int,array{name:string,label:string,type:string}>
	 */
	public static function get_fields_for_content_type( $content_type, $taxonomy = '' ) {
		if ( ! function_exists( 'acf_get_field_groups' ) || ! function_exists( 'acf_get_fields' ) ) {
			return [];
		}

		$content_type = sanitize_key( (string) $content_type );
		$taxonomy     = sanitize_key( (string) $taxonomy );
		$location     = self::get_location_args( $content_type, $taxonomy );
		if ( empty( $location ) ) {
			return [];
		}

		$field_groups = self::get_field_groups_for_location( $location );
		if ( in_array( $content_type, [ 'menu', 'menus', 'nav_menu', 'nav_menu_item' ], true ) ) {
			$field_groups = array_merge(
				$field_groups,
				self::get_field_groups_for_location( [ 'nav_menu_item' => 'all' ] )
			);
		}
		$fields = [];
		$seen   = [];

		foreach ( $field_groups as $group ) {
			$group_fields = acf_get_fields( $group['key'] ?? $group );
			if ( empty( $group_fields ) || ! is_array( $group_fields ) ) {
				continue;
			}

			foreach ( $group_fields as $field ) {
				if ( empty( $field['name'] ) || empty( $field['type'] ) || in_array( $field['type'], [ 'accordion', 'tab', 'message', 'clone' ], true ) ) {
					continue;
				}

				$name = (string) $field['name'];
				if ( isset( $seen[ $name ] ) ) {
					continue;
				}
				$seen[ $name ] = true;

				$fields[] = [
					'name'  => $name,
					'label' => (string) ( $field['label'] ?? $name ),
					'type'  => (string) $field['type'],
				];
			}
		}

		return $fields;
	}

	/**
	 * Export a single ACF field value in a portable shape.
	 *
	 * @param string $object_type Object type: post, media, user, comment, term, menu.
	 * @param int    $object_id   Object ID.
	 * @param string $field_name  ACF field name.
	 * @param string $taxonomy    Optional taxonomy for terms.
	 * @return mixed
	 */
	public static function export_value( $object_type, $object_id, $field_name, $taxonomy = '' ) {
		$object_id  = absint( $object_id );
		$field_name = (string) $field_name;
		if ( $object_id <= 0 || '' === $field_name ) {
			return '';
		}

		$acf_id = self::get_acf_object_id( $object_type, $object_id, $taxonomy );
		$value  = false;
		if ( function_exists( 'get_field' ) ) {
			$value = get_field( $field_name, $acf_id );
		}

		if ( false === $value || null === $value ) {
			$value = self::get_raw_meta_value( $object_type, $object_id, $field_name );
			if ( is_string( $value ) && is_serialized( $value ) ) {
				$value = maybe_unserialize( $value );
			}
		}

		$field_object = self::get_field_object( $field_name, $acf_id, $object_type, $taxonomy );
		$portable     = self::to_portable_value( $value, is_array( $field_object ) ? $field_object : [] );

		return is_array( $portable ) || is_object( $portable ) ? wp_json_encode( $portable ) : $portable;
	}

	/**
	 * Import/update a single ACF field value.
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id   Object ID.
	 * @param string $field_name  Field name.
	 * @param mixed  $value       Exported value.
	 * @param string $taxonomy    Optional taxonomy.
	 * @return bool
	 */
	public static function import_value( $object_type, $object_id, $field_name, $value, $taxonomy = '' ) {
		$object_id  = absint( $object_id );
		$field_name = (string) $field_name;
		if ( $object_id <= 0 || '' === $field_name ) {
			return false;
		}

		$acf_id       = self::get_acf_object_id( $object_type, $object_id, $taxonomy );
		$field_object = self::get_field_object( $field_name, $acf_id, $object_type, $taxonomy );
		$media_parent = self::get_media_parent_id( $object_type, $object_id );
		$prepared     = self::from_portable_value( $value, is_array( $field_object ) ? $field_object : [], $media_parent );
		$selector     = is_array( $field_object ) && ! empty( $field_object['key'] ) ? (string) $field_object['key'] : $field_name;

		if ( function_exists( 'update_field' ) ) {
			update_field( $selector, $prepared, $acf_id );
		}

		$field_type     = is_array( $field_object ) ? (string) ( $field_object['type'] ?? '' ) : '';
		$is_complex_acf = in_array( $field_type, [ 'repeater', 'group', 'flexible_content' ], true );
		if ( ! $is_complex_acf ) {
			self::update_raw_meta_value( $object_type, $object_id, $field_name, $prepared );
		}
		if ( is_array( $field_object ) && ! empty( $field_object['key'] ) ) {
			self::update_raw_meta_value( $object_type, $object_id, '_' . $field_name, (string) $field_object['key'] );
		}

		return true;
	}

	/**
	 * Get ACF object id for update_field/get_field.
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id   Object ID.
	 * @param string $taxonomy    Taxonomy.
	 * @return string|int
	 */
	public static function get_acf_object_id( $object_type, $object_id, $taxonomy = '' ) {
		$object_type = sanitize_key( (string) $object_type );
		$object_id   = absint( $object_id );

		if ( 'user' === $object_type ) {
			return 'user_' . $object_id;
		}

		if ( 'comment' === $object_type ) {
			return 'comment_' . $object_id;
		}

		if ( in_array( $object_type, [ 'term', 'taxonomy', 'menu' ], true ) ) {
			return 'term_' . $object_id;
		}

		return $object_id;
	}

	/**
	 * Build ACF location args for a content type.
	 *
	 * @param string $content_type Content type.
	 * @param string $taxonomy     Taxonomy.
	 * @return array<string,string>
	 */
	private static function get_location_args( $content_type, $taxonomy = '' ) {
		$type_map = [
			'woo_product'   => 'product',
			'woo_order'     => 'shop_order',
			'woo_coupon'    => 'shop_coupon',
			'media'         => 'attachment',
			'comment'       => 'comment',
			'menu'          => 'nav_menu',
			'menus'         => 'nav_menu',
			'nav_menu_item' => 'nav_menu_item',
			'term'          => 'taxonomy',
			'taxonomy'      => 'taxonomy',
		];

		$content_type = $type_map[ $content_type ] ?? $content_type;

		if ( 'user' === $content_type ) {
			return [ 'user_form' => 'all' ];
		}

		if ( 'attachment' === $content_type ) {
			return [ 'attachment' => 'all' ];
		}

		if ( 'comment' === $content_type ) {
			return [ 'comment' => 'all' ];
		}

		if ( 'nav_menu' === $content_type ) {
			return [ 'nav_menu' => 'all' ];
		}

		if ( 'nav_menu_item' === $content_type ) {
			return [ 'nav_menu_item' => 'all' ];
		}

		if ( 'taxonomy' === $content_type ) {
			return [ 'taxonomy' => '' !== $taxonomy ? $taxonomy : 'all' ];
		}

		return '' !== $content_type ? [ 'post_type' => $content_type ] : [];
	}

	/**
	 * Get ACF field groups for a screen, including broader non-post screens.
	 *
	 * ACF's normal screen matching is exact for values like user_form=all.
	 * For Import/Export we need all field groups that can apply to the object
	 * kind, for example any User Form rule, not only a literal "all" rule.
	 *
	 * @param array<string,string> $location Location args.
	 * @return array<int,array>
	 */
	private static function get_field_groups_for_location( array $location ) {
		$field_groups = acf_get_field_groups( $location );
		$indexed      = [];

		foreach ( is_array( $field_groups ) ? $field_groups : [] as $group ) {
			if ( isset( $group['key'] ) ) {
				$indexed[ $group['key'] ] = $group;
			}
		}

		$param  = (string) key( $location );
		$target = (string) current( $location );
		if ( '' === $param ) {
			return array_values( $indexed );
		}

		foreach ( acf_get_field_groups() as $group ) {
			if ( empty( $group['key'] ) || isset( $indexed[ $group['key'] ] ) || empty( $group['location'] ) ) {
				continue;
			}

			if ( self::field_group_matches_location( $group, $param, $target ) ) {
				$indexed[ $group['key'] ] = $group;
			}
		}

		return array_values( $indexed );
	}

	/**
	 * Check whether an ACF field group has a compatible location rule.
	 *
	 * @param array  $group  Field group.
	 * @param string $param  Location param.
	 * @param string $target Target location value.
	 * @return bool
	 */
	private static function field_group_matches_location( array $group, $param, $target ) {
		foreach ( (array) $group['location'] as $or_rules ) {
			foreach ( (array) $or_rules as $rule ) {
				if ( ! is_array( $rule ) || ( $rule['param'] ?? '' ) !== $param || ( $rule['operator'] ?? '==' ) !== '==' ) {
					continue;
				}

				$value = (string) ( $rule['value'] ?? '' );
				if ( 'all' === $target || 'all' === $value || $value === $target ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Get field object without formatted value.
	 *
	 * @param string     $field_name Field name.
	 * @param string|int $acf_id     ACF object id.
	 * @return array|null
	 */
	private static function get_field_object( $field_name, $acf_id, $object_type = '', $taxonomy = '' ) {
		if ( function_exists( 'get_field_object' ) ) {
			$field_object = get_field_object( $field_name, $acf_id, false, false );
			if ( is_array( $field_object ) ) {
				return $field_object;
			}
		}

		if ( function_exists( 'acf_get_field' ) ) {
			$field_object = acf_get_field( $field_name );
			if ( is_array( $field_object ) ) {
				return $field_object;
			}
		}

		if ( function_exists( 'acf_get_fields' ) && '' !== (string) $object_type ) {
			foreach ( self::get_field_groups_for_location( self::get_location_args( (string) $object_type, (string) $taxonomy ) ) as $group ) {
				$field_object = self::find_field_object_in_group( $field_name, $group );
				if ( is_array( $field_object ) ) {
					return $field_object;
				}
			}
		}

		return null;
	}

	/**
	 * Find an ACF field object by name/key inside a field group, including nested fields.
	 *
	 * @param string $field_name Field name or field key.
	 * @param array  $group      ACF field group.
	 * @return array|null
	 */
	private static function find_field_object_in_group( $field_name, array $group ) {
		if ( ! function_exists( 'acf_get_fields' ) ) {
			return null;
		}

		$fields = acf_get_fields( $group['key'] ?? $group );
		return self::find_field_object_in_fields( $field_name, is_array( $fields ) ? $fields : [] );
	}

	/**
	 * Find an ACF field object recursively in a fields array.
	 *
	 * @param string $field_name Field name or field key.
	 * @param array  $fields     ACF fields.
	 * @return array|null
	 */
	private static function find_field_object_in_fields( $field_name, array $fields ) {
		$field_name = (string) $field_name;

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) ) {
				continue;
			}

			if ( $field_name === (string) ( $field['name'] ?? '' ) || $field_name === (string) ( $field['key'] ?? '' ) ) {
				return $field;
			}

			foreach ( [ 'sub_fields', 'layouts' ] as $children_key ) {
				if ( empty( $field[ $children_key ] ) || ! is_array( $field[ $children_key ] ) ) {
					continue;
				}

				if ( 'layouts' === $children_key ) {
					foreach ( $field[ $children_key ] as $layout ) {
						if ( empty( $layout['sub_fields'] ) || ! is_array( $layout['sub_fields'] ) ) {
							continue;
						}
						$found = self::find_field_object_in_fields( $field_name, $layout['sub_fields'] );
						if ( is_array( $found ) ) {
							return $found;
						}
					}
					continue;
				}

				$found = self::find_field_object_in_fields( $field_name, $field[ $children_key ] );
				if ( is_array( $found ) ) {
					return $found;
				}
			}
		}

		return null;
	}

	/**
	 * Get a valid attachment parent ID for media sideloads.
	 *
	 * Users, comments, terms, and menus are not posts, so using their object ID as
	 * media parent can incorrectly attach media to an unrelated post with the same ID.
	 *
	 * @param string $object_type Object type.
	 * @param int    $object_id   Object ID.
	 * @return int
	 */
	private static function get_media_parent_id( $object_type, $object_id ) {
		$object_type = sanitize_key( (string) $object_type );
		$object_id   = absint( $object_id );

		if ( in_array( $object_type, [ 'post', 'media', 'attachment' ], true ) ) {
			return $object_id;
		}

		return 0;
	}

	/**
	 * Convert native ACF value to portable value.
	 *
	 * @param mixed $value        Native value.
	 * @param array $field_object Field object.
	 * @return mixed
	 */
	private static function to_portable_value( $value, array $field_object ) {
		$type = (string) ( $field_object['type'] ?? '' );

		if ( in_array( $type, [ 'image', 'file' ], true ) ) {
			return self::media_url_from_value( $value );
		}

		if ( 'gallery' === $type ) {
			$urls = [];
			foreach ( is_array( $value ) ? $value : [] as $item ) {
				$url = self::media_url_from_value( $item );
				if ( '' !== $url ) {
					$urls[] = $url;
				}
			}
			return [
				'acf_type' => 'gallery',
				'values'   => $urls,
			];
		}

		if ( 'repeater' === $type ) {
			$rows = [];
			foreach ( is_array( $value ) ? $value : [] as $row ) {
				$rows[] = self::portable_row_value( is_array( $row ) ? $row : [], (array) ( $field_object['sub_fields'] ?? [] ) );
			}

			return [
				'acf_type' => 'repeater',
				'rows'     => $rows,
			];
		}

		if ( 'group' === $type ) {
			return [
				'acf_type' => 'group',
				'value'    => self::portable_row_value( is_array( $value ) ? $value : [], (array) ( $field_object['sub_fields'] ?? [] ) ),
			];
		}

		if ( in_array( $type, [ 'relationship', 'post_object', 'page_link' ], true ) ) {
			$single = ! is_array( $value );
			$items  = $single ? [ $value ] : $value;
			$out    = [];
			foreach ( is_array( $items ) ? $items : [] as $item ) {
				$post_id = self::post_id_from_value( $item );
				if ( $post_id <= 0 ) {
					continue;
				}
				if ( 'attachment' === get_post_type( $post_id ) ) {
					$url = wp_get_attachment_url( $post_id );
					if ( $url ) {
						$out[] = $url;
					}
				} else {
					$post = get_post( $post_id );
					if ( $post ) {
						$out[] = get_page_uri( $post_id ) ?: $post->post_name;
					}
				}
			}
			return [
				'acf_type' => 'relation',
				'values'   => $out,
				'single'   => $single,
			];
		}

		if ( 'taxonomy' === $type ) {
			$single   = ! is_array( $value );
			$items    = $single ? [ $value ] : $value;
			$taxonomy = (string) ( $field_object['taxonomy'] ?? '' );
			$names    = [];
			foreach ( is_array( $items ) ? $items : [] as $item ) {
				$term_id = is_object( $item ) && isset( $item->term_id ) ? (int) $item->term_id : (int) $item;
				$term    = $term_id > 0 ? get_term( $term_id, $taxonomy ?: '' ) : null;
				if ( $term && ! is_wp_error( $term ) ) {
					$names[] = $term->name;
				}
			}
			return [
				'acf_type' => 'taxonomy',
				'taxonomy' => $taxonomy,
				'values'   => $names,
				'single'   => $single,
			];
		}

		if ( 'user' === $type ) {
			$single = ! is_array( $value );
			$items  = $single ? [ $value ] : $value;
			$logins = [];
			foreach ( is_array( $items ) ? $items : [] as $item ) {
				$user_id = is_object( $item ) && isset( $item->ID ) ? (int) $item->ID : (int) $item;
				$user    = $user_id > 0 ? get_userdata( $user_id ) : false;
				if ( $user ) {
					$logins[] = $user->user_login;
				}
			}
			return [
				'acf_type' => 'user',
				'values'   => $logins,
				'single'   => $single,
			];
		}

		return $value;
	}

	/**
	 * Convert a nested ACF row/group value to portable values using sub-field definitions.
	 *
	 * @param array $row        Native row value.
	 * @param array $sub_fields ACF sub-field definitions.
	 * @return array
	 */
	private static function portable_row_value( array $row, array $sub_fields ) {
		$out = [];

		foreach ( $row as $key => $value ) {
			$field       = self::find_field_object_in_fields( (string) $key, $sub_fields );
			$out[ $key ] = is_array( $field )
				? self::to_portable_value( $value, $field )
				: self::portable_unknown_nested_value( $value );
		}

		return $out;
	}

	/**
	 * Best-effort portable conversion for nested values without field definitions.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function portable_unknown_nested_value( $value ) {
		$url = self::media_url_from_value( $value );
		if ( is_string( $url ) && filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return $url;
		}

		if ( is_array( $value ) ) {
			$out = [];
			foreach ( $value as $key => $child ) {
				$out[ $key ] = self::portable_unknown_nested_value( $child );
			}
			return $out;
		}

		return $value;
	}

	/**
	 * Convert portable ACF value to native value.
	 *
	 * @param mixed $value        Exported value.
	 * @param array $field_object Field object.
	 * @param int   $parent_id    Parent post/attachment id for media imports.
	 * @return mixed
	 */
	private static function from_portable_value( $value, array $field_object, $parent_id = 0 ) {
		$value = self::maybe_decode( $value );
		$type  = (string) ( $field_object['type'] ?? '' );

		if ( is_array( $value ) && isset( $value['acf_type'] ) ) {
			switch ( $value['acf_type'] ) {
				case 'gallery':
					$ids = [];
					foreach ( (array) ( $value['values'] ?? [] ) as $url ) {
						$id = self::attachment_id_from_value( $url, $parent_id );
						if ( $id > 0 ) {
							$ids[] = $id;
						}
					}
					return $ids;
				case 'relation':
					$ids = [];
					foreach ( (array) ( $value['values'] ?? [] ) as $ref ) {
						$id = self::post_id_from_portable_ref( $ref, $parent_id );
						if ( $id > 0 ) {
							$ids[] = $id;
						}
					}
					return ! empty( $value['single'] ) ? ( $ids[0] ?? 0 ) : $ids;
				case 'taxonomy':
					$ids      = [];
					$taxonomy = (string) ( $value['taxonomy'] ?? ( $field_object['taxonomy'] ?? '' ) );
					foreach ( (array) ( $value['values'] ?? [] ) as $term_name ) {
						$term_id = self::term_id_from_name( (string) $term_name, $taxonomy );
						if ( $term_id > 0 ) {
							$ids[] = $term_id;
						}
					}
					return ! empty( $value['single'] ) ? ( $ids[0] ?? 0 ) : $ids;
				case 'user':
					$ids = [];
					foreach ( (array) ( $value['values'] ?? [] ) as $login ) {
						$user = get_user_by( 'login', (string) $login );
						if ( ! $user ) {
							$user = get_user_by( 'email', (string) $login );
						}
						if ( $user ) {
							$ids[] = (int) $user->ID;
						}
					}
					return ! empty( $value['single'] ) ? ( $ids[0] ?? 0 ) : $ids;
				case 'repeater':
					$rows = [];
					foreach ( (array) ( $value['rows'] ?? [] ) as $row ) {
						$rows[] = self::native_row_value( is_array( $row ) ? $row : [], (array) ( $field_object['sub_fields'] ?? [] ), $parent_id );
					}
					return $rows;
				case 'group':
					return self::native_row_value( is_array( $value['value'] ?? null ) ? $value['value'] : [], (array) ( $field_object['sub_fields'] ?? [] ), $parent_id );
			}
		}

		if ( in_array( $type, [ 'image', 'file' ], true ) ) {
			$id = self::attachment_id_from_value( $value, $parent_id );
			return $id > 0 ? $id : $value;
		}

		if ( 'gallery' === $type ) {
			$values = is_array( $value ) ? $value : array_filter( array_map( 'trim', explode( ',', (string) $value ) ) );
			$ids    = [];
			foreach ( $values as $item ) {
				$id = self::attachment_id_from_value( $item, $parent_id );
				if ( $id > 0 ) {
					$ids[] = $id;
				}
			}
			return $ids;
		}

		if ( is_string( $value ) && '' !== $value && ( 'wysiwyg' === $type || false !== stripos( $value, '<img' ) || false !== stripos( $value, 'srcset=' ) || false !== stripos( $value, '<a' ) ) ) {
			return self::replace_media_urls_in_html( $value, $parent_id );
		}

		if ( is_array( $value ) ) {
			return self::native_unknown_nested_value( $value, $parent_id );
		}

		return $value;
	}

	/**
	 * Convert nested portable row/group values back to native values.
	 *
	 * @param array $row        Portable row value.
	 * @param array $sub_fields ACF sub-field definitions.
	 * @param int   $parent_id  Media parent ID.
	 * @return array
	 */
	private static function native_row_value( array $row, array $sub_fields, $parent_id = 0 ) {
		$out = [];

		foreach ( $row as $key => $value ) {
			$field       = self::find_field_object_in_fields( (string) $key, $sub_fields );
			$out[ $key ] = is_array( $field )
				? self::from_portable_value( $value, $field, $parent_id )
				: self::native_unknown_nested_value( $value, $parent_id );
		}

		return $out;
	}

	/**
	 * Best-effort nested media import for values without field definitions.
	 *
	 * @param mixed $value     Value.
	 * @param int   $parent_id Media parent ID.
	 * @return mixed
	 */
	private static function native_unknown_nested_value( $value, $parent_id = 0 ) {
		$value = self::maybe_decode( $value );

		if ( is_string( $value ) && filter_var( $value, FILTER_VALIDATE_URL ) ) {
			$id = self::attachment_id_from_value( $value, $parent_id );
			return $id > 0 ? $id : $value;
		}

		if ( is_array( $value ) ) {
			if ( isset( $value['url'] ) && is_string( $value['url'] ) ) {
				$id = self::attachment_id_from_value( $value['url'], $parent_id );
				return $id > 0 ? $id : $value;
			}

			$out = [];
			foreach ( $value as $key => $child ) {
				$out[ $key ] = self::native_unknown_nested_value( $child, $parent_id );
			}
			return $out;
		}

		return $value;
	}

	/**
	 * Decode JSON/serialized values.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	private static function maybe_decode( $value ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		$trimmed = trim( $value );
		if ( '' === $trimmed ) {
			return $value;
		}

		if ( is_serialized( $trimmed ) ) {
			return maybe_unserialize( $trimmed );
		}

		if ( in_array( $trimmed[0], [ '{', '[' ], true ) ) {
			$decoded = json_decode( $trimmed, true );
			if ( JSON_ERROR_NONE === json_last_error() ) {
				return $decoded;
			}
		}

		return $value;
	}

	private static function media_url_from_value( $value ) {
		if ( is_numeric( $value ) ) {
			$url = wp_get_attachment_url( (int) $value );
			return $url ? $url : '';
		}
		if ( is_array( $value ) ) {
			if ( isset( $value['url'] ) && is_string( $value['url'] ) ) {
				return $value['url'];
			}
			foreach ( [ 'ID', 'id' ] as $key ) {
				if ( isset( $value[ $key ] ) && is_numeric( $value[ $key ] ) ) {
					$url = wp_get_attachment_url( (int) $value[ $key ] );
					return $url ? $url : '';
				}
			}
		}
		if ( is_object( $value ) && isset( $value->ID ) ) {
			$url = wp_get_attachment_url( (int) $value->ID );
			return $url ? $url : '';
		}
		return is_string( $value ) ? $value : '';
	}

	private static function post_id_from_value( $value ) {
		if ( is_numeric( $value ) ) {
			return absint( $value );
		}
		if ( is_object( $value ) && isset( $value->ID ) ) {
			return absint( $value->ID );
		}
		if ( is_array( $value ) && isset( $value['ID'] ) ) {
			return absint( $value['ID'] );
		}
		return 0;
	}

	private static function post_id_from_portable_ref( $ref, $parent_id = 0 ) {
		if ( is_numeric( $ref ) ) {
			return absint( $ref );
		}
		if ( is_string( $ref ) && filter_var( $ref, FILTER_VALIDATE_URL ) ) {
			return self::attachment_id_from_value( $ref, $parent_id );
		}
		if ( is_string( $ref ) && '' !== $ref ) {
			$post = get_page_by_path( $ref, OBJECT, 'any' );
			return $post ? (int) $post->ID : 0;
		}
		return 0;
	}

	private static function attachment_id_from_value( $value, $parent_id = 0 ) {
		static $url_to_attachment_cache = [];

		$value = self::maybe_decode( $value );
		if ( is_numeric( $value ) ) {
			return absint( $value );
		}
		if ( is_array( $value ) ) {
			foreach ( [ 'ID', 'id' ] as $key ) {
				if ( isset( $value[ $key ] ) && is_numeric( $value[ $key ] ) ) {
					return absint( $value[ $key ] );
				}
			}
			if ( isset( $value['url'] ) ) {
				return self::attachment_id_from_value( $value['url'], $parent_id );
			}
		}
		if ( ! is_string( $value ) || '' === $value ) {
			return 0;
		}
		$existing = attachment_url_to_postid( $value );
		if ( $existing > 0 ) {
			return (int) $existing;
		}
		if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return 0;
		}
		$source_url  = esc_url_raw( $value );
		$source_hash = md5( $source_url );
		if ( isset( $url_to_attachment_cache[ $source_hash ] ) ) {
			return (int) $url_to_attachment_cache[ $source_hash ];
		}
		$existing_by_source = self::attachment_id_from_source_hash( $source_hash );
		if ( $existing_by_source > 0 ) {
			$url_to_attachment_cache[ $source_hash ] = $existing_by_source;
			return $existing_by_source;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $source_url, 30 );
		if ( is_wp_error( $tmp ) ) {
			$host        = wp_parse_url( $source_url, PHP_URL_HOST );
			$is_dev_host = is_string( $host ) && preg_match( '/(\.local|\.test|localhost)$/i', $host );

			if ( $is_dev_host && 'http_request_failed' === $tmp->get_error_code() ) {
				$retry = self::download_url_unrestricted( $source_url, 30 );
				if ( ! is_wp_error( $retry ) ) {
					$tmp = $retry;
				}
			}
		}
		if ( is_wp_error( $tmp ) ) {
			return 0;
		}

		$file = [
			'name'     => wp_basename( (string) wp_parse_url( $value, PHP_URL_PATH ) ),
			'tmp_name' => $tmp,
		];

		$extension           = strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );
		$upload_mimes_filter = self::get_sideload_mimes_filter( $extension );
		if ( is_callable( $upload_mimes_filter ) ) {
			add_filter( 'upload_mimes', $upload_mimes_filter );
		}

		$id = media_handle_sideload( $file, absint( $parent_id ) );

		if ( is_callable( $upload_mimes_filter ) ) {
			remove_filter( 'upload_mimes', $upload_mimes_filter );
		}

		if ( is_wp_error( $id ) ) {
			wp_delete_file( $tmp );
			return 0;
		}

		update_post_meta( (int) $id, 'rsl_ie_source_url', $source_url );
		update_post_meta( (int) $id, 'rsl_ie_source_url_hash', $source_hash );
		if ( class_exists( '\RockStarLab\ImportExport\Helper\Media_Hash' ) ) {
			Media_Hash::get_or_create_hash( (int) $id );
		}

		$url_to_attachment_cache[ $source_hash ] = (int) $id;

		return (int) $id;
	}

	/**
	 * Return a narrowly scoped mime allow-list callback for media sideloads.
	 *
	 * @param string $extension File extension.
	 * @return callable|null
	 */
	private static function get_sideload_mimes_filter( $extension ) {
		$extension = strtolower( sanitize_key( (string) $extension ) );
		$mimes     = array(
			'svg'  => 'image/svg+xml',
			'avif' => 'image/avif',
			'webp' => 'image/webp',
		);

		if ( ! isset( $mimes[ $extension ] ) ) {
			return null;
		}

		return static function ( $allowed_mimes ) use ( $extension, $mimes ) {
			$allowed_mimes[ $extension ] = $mimes[ $extension ];
			return $allowed_mimes;
		};
	}

	/**
	 * Find an attachment previously imported from the same source URL hash.
	 *
	 * @param string $source_hash Source URL hash.
	 * @return int Attachment ID or 0.
	 */
	private static function attachment_id_from_source_hash( $source_hash ) {
		global $wpdb;

		if ( '' === $source_hash ) {
			return 0;
		}

		$attachment_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s AND meta_value = %s LIMIT 1",
				'rsl_ie_source_url_hash',
				$source_hash
			)
		);

		return $attachment_id ? absint( $attachment_id ) : 0;
	}

		/**
		 * Replace media URLs in HTML/WYSIWYG values with local media library URLs.
		 *
		 * @param string $html      HTML value.
		 * @param int    $parent_id Parent object ID.
		 * @return string
		 */
	private static function replace_media_urls_in_html( $html, $parent_id = 0 ) {
		return preg_replace_callback(
			'~https?://[^\s\'"]+\.(?:jpe?g|png|gif|webp|avif|svg|pdf|mp3|m4a|ogg|wav|mp4|m4v|mov|webm)(?:\?[^\s\'"]*)?~i',
			static function ( $matches ) use ( $parent_id ) {
				$url = html_entity_decode( $matches[0], ENT_QUOTES, get_bloginfo( 'charset' ) );
				$id  = self::attachment_id_from_value( $url, $parent_id );
				if ( $id <= 0 ) {
					return $matches[0];
				}

				$local_url = wp_get_attachment_url( $id );
				return $local_url ? esc_url_raw( $local_url ) : $matches[0];
			},
			$html
		);
	}

		/**
		 * Download a URL without WordPress unsafe URL rejection.
		 *
		 * This is only used as a fallback for local/dev hosts.
		 *
		 * @param string $url     URL.
		 * @param int    $timeout Timeout in seconds.
		 * @return string|\WP_Error
		 */
	private static function download_url_unrestricted( $url, $timeout ) {
		$tmp = wp_tempnam( $url );
		if ( ! $tmp ) {
			return new \WP_Error( 'rsl_ie_temp_file_failed', __( 'Could not create a temporary file for download.', 'import-export-by-rockstarlab' ) );
		}

		$response = wp_remote_get(
			$url,
			[
				'timeout'            => absint( $timeout ),
				'stream'             => true,
				'filename'           => $tmp,
				'reject_unsafe_urls' => false,
			]
		);

		if ( is_wp_error( $response ) ) {
			wp_delete_file( $tmp );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			wp_delete_file( $tmp );
			return new \WP_Error( 'rsl_ie_download_failed', sprintf( 'Download failed with HTTP %d', $code ) );
		}

		return $tmp;
	}

	private static function term_id_from_name( $name, $taxonomy ) {
		$name     = trim( $name );
		$taxonomy = sanitize_key( (string) $taxonomy );
		if ( '' === $name || '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return 0;
		}
		$term = get_term_by( 'name', $name, $taxonomy );
		if ( ! $term ) {
			$term = get_term_by( 'slug', sanitize_title( $name ), $taxonomy );
		}
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}
		$created = wp_insert_term( $name, $taxonomy );
		return is_wp_error( $created ) ? 0 : (int) $created['term_id'];
	}

	private static function get_raw_meta_value( $object_type, $object_id, $key ) {
		switch ( sanitize_key( (string) $object_type ) ) {
			case 'user':
				return get_user_meta( $object_id, $key, true );
			case 'comment':
				return get_comment_meta( $object_id, $key, true );
			case 'term':
			case 'taxonomy':
			case 'menu':
				return get_term_meta( $object_id, $key, true );
			default:
				return get_post_meta( $object_id, $key, true );
		}
	}

	private static function update_raw_meta_value( $object_type, $object_id, $key, $value ) {
		switch ( sanitize_key( (string) $object_type ) ) {
			case 'user':
				update_user_meta( $object_id, $key, $value );
				break;
			case 'comment':
				update_comment_meta( $object_id, $key, $value );
				break;
			case 'term':
			case 'taxonomy':
			case 'menu':
				update_term_meta( $object_id, $key, $value );
				break;
			default:
				update_post_meta( $object_id, $key, $value );
				break;
		}
	}
}

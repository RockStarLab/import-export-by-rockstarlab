<?php
/**
 * Elementor templates (elementor_library) used by a page: they travel with the page.
 *
 * A page refers to saved templates by ID: the Template widget (template_id), the
 * [elementor-template id="N"] shortcode, global widgets (templateID) and tab/loop widgets
 * (saved_template). On another site that ID names nothing, so the templates are sent along,
 * matched there by slug (updated) or created, and every reference is renumbered.
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

/** Collect, send and receive the Elementor templates a document refers to. */
class Elementor_Templates {
	const POST_TYPE = 'elementor_library';

	/** Meta key that carries the templates in exported files. */
	const META_KEY = '_rsl_ie_elementor_templates';

	/** Templates inside templates are followed this deep. */
	const MAX_DEPTH = 5;

	/** At most this many templates travel with one page. */
	const MAX_TEMPLATES = 50;

	/** Setting keys that hold a template ID. */
	const ID_KEYS = [ 'template_id', 'saved_template', 'templateID' ];

	/**
	 * Template IDs referred to by Elementor data.
	 *
	 * @param mixed $data Decoded _elementor_data (or page settings), or its JSON.
	 * @return int[]
	 */
	public static function ids_in( $data ) {
		if ( is_string( $data ) ) {
			$decoded = json_decode( $data, true );
			$data    = is_array( $decoded ) ? $decoded : $data;
		}
		$ids  = [];
		$walk = static function ( $value, $key = '' ) use ( &$walk, &$ids ) {
			if ( is_array( $value ) ) {
				foreach ( $value as $k => $v ) {
					$walk( $v, (string) $k );
				}
				return;
			}
			if ( in_array( $key, self::ID_KEYS, true ) && is_numeric( $value ) && (int) $value > 0 ) {
				$ids[] = (int) $value;
			} elseif ( is_string( $value ) && false !== strpos( $value, 'elementor-template' ) && preg_match_all( '/\[elementor-template\s+id=["\']?(\d+)/i', $value, $m ) ) {
				foreach ( $m[1] as $id ) {
					$ids[] = (int) $id;
				}
			}
		};
		$walk( $data );
		return array_values( array_unique( $ids ) );
	}

	/**
	 * Records of the templates a post refers to, nested templates included.
	 *
	 * @param int $post_id Post ID.
	 * @return array<int,array<string,mixed>>
	 */
	public static function records_for_post( $post_id ) {
		if ( ! post_type_exists( self::POST_TYPE ) ) {
			return [];
		}
		$queue = array_merge(
			self::ids_in( (string) get_post_meta( (int) $post_id, '_elementor_data', true ) ),
			self::ids_in( get_post_meta( (int) $post_id, '_elementor_page_settings', true ) )
		);
		$seen    = [ (int) $post_id => true ];
		$records = [];
		for ( $depth = 0; $queue && $depth < self::MAX_DEPTH; $depth++ ) {
			$next = [];
			foreach ( $queue as $id ) {
				if ( isset( $seen[ $id ] ) || count( $records ) >= self::MAX_TEMPLATES ) {
					continue;
				}
				$seen[ $id ] = true;
				$record      = self::record( $id );
				if ( ! $record ) {
					continue;
				}
				$records[] = $record;
				$next      = array_merge( $next, self::ids_in( $record['data'] ), self::ids_in( $record['page_settings'] ) );
			}
			$queue = $next;
		}
		return $records;
	}

	/**
	 * Portable record of one template.
	 *
	 * @param int $id Template ID.
	 * @return array<string,mixed>|null
	 */
	private static function record( $id ) {
		$post = get_post( (int) $id );
		if ( ! $post || self::POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) {
			return null;
		}
		$types = wp_get_object_terms( $post->ID, 'elementor_library_type', [ 'fields' => 'slugs' ] );
		$meta  = [];
		foreach ( [ '_elementor_edit_mode', '_elementor_template_type', '_elementor_version', '_elementor_conditions', '_wp_page_template' ] as $key ) {
			$value = get_post_meta( $post->ID, $key, true );
			if ( '' !== $value && null !== $value && false !== $value ) {
				$meta[ $key ] = $value;
			}
		}
		$settings = get_post_meta( $post->ID, '_elementor_page_settings', true );
		return [
			'source_id'     => (int) $post->ID,
			'post_title'    => $post->post_title,
			'post_name'     => $post->post_name,
			'post_status'   => $post->post_status,
			'lib_types'     => is_wp_error( $types ) ? [] : array_values( $types ),
			'meta'          => $meta,
			'data'          => (string) get_post_meta( $post->ID, '_elementor_data', true ),
			'page_settings' => is_array( $settings ) ? $settings : [],
		];
	}

	/**
	 * Attachment IDs of the media used in template records (sent along with a synced post).
	 *
	 * @param array $records Template records.
	 * @return array<string,mixed> Elementor-style elements to scan.
	 */
	public static function media_elements( array $records ) {
		$elements = [];
		foreach ( $records as $record ) {
			$data = json_decode( (string) ( $record['data'] ?? '' ), true );
			if ( is_array( $data ) ) {
				$elements = array_merge( $elements, $data );
			}
			if ( ! empty( $record['page_settings'] ) && is_array( $record['page_settings'] ) ) {
				$elements[] = [ 'settings' => $record['page_settings'] ];
			}
		}
		return $elements;
	}

	/**
	 * Create or update the templates on this site.
	 *
	 * @param mixed    $records  Template records (array or JSON).
	 * @param callable $prepare  fn( int $template_id, array $meta ): array with _elementor_data and
	 *                           _elementor_page_settings ready to store (media and domain mapped).
	 * @return array<int,int> Source template ID => local template ID.
	 */
	public static function import( $records, callable $prepare ) {
		if ( is_string( $records ) ) {
			$records = json_decode( $records, true );
		}
		if ( ! is_array( $records ) || ! $records || ! post_type_exists( self::POST_TYPE ) ) {
			return [];
		}
		$type = get_post_type_object( self::POST_TYPE );
		if ( ! $type || ! current_user_can( $type->cap->edit_posts ) ) {
			return [];
		}
		$map  = [];
		$done = [];
		foreach ( array_slice( $records, 0, self::MAX_TEMPLATES ) as $record ) {
			$source = (int) ( $record['source_id'] ?? 0 );
			$slug   = sanitize_title( (string) ( $record['post_name'] ?? '' ) );
			if ( $source <= 0 || '' === $slug || isset( $map[ $source ] ) ) {
				continue;
			}
			$status   = in_array( $record['post_status'] ?? '', [ 'publish', 'draft', 'private' ], true ) ? $record['post_status'] : 'publish';
			$existing = get_posts(
				[
					'post_type'      => self::POST_TYPE,
					'name'           => $slug,
					'post_status'    => [ 'publish', 'draft', 'private', 'pending', 'future' ],
					'posts_per_page' => 1,
					'fields'         => 'ids',
				]
			);
			$args = [
				'post_type'   => self::POST_TYPE,
				'post_title'  => sanitize_text_field( (string) ( $record['post_title'] ?? $slug ) ),
				'post_name'   => $slug,
				'post_status' => $status,
			];
			if ( $existing ) {
				$args['ID'] = (int) $existing[0];
				$local      = wp_update_post( wp_slash( $args ), true );
			} else {
				$local = wp_insert_post( wp_slash( $args ), true );
			}
			if ( is_wp_error( $local ) || ! $local ) {
				continue;
			}
			$local          = (int) $local;
			$map[ $source ] = $local;
			$done[ $local ] = $record;
			foreach ( (array) ( $record['meta'] ?? [] ) as $key => $value ) {
				if ( in_array( $key, [ '_elementor_edit_mode', '_elementor_template_type', '_elementor_version', '_elementor_conditions', '_wp_page_template' ], true ) ) {
					update_post_meta( $local, $key, $value );
				}
			}
			$lib_types = array_filter( array_map( 'sanitize_key', (array) ( $record['lib_types'] ?? [] ) ) );
			if ( $lib_types && taxonomy_exists( 'elementor_library_type' ) ) {
				wp_set_object_terms( $local, $lib_types, 'elementor_library_type' );
			}
		}
		// Every template exists now: store the designs with renumbered nested templates.
		foreach ( $done as $local => $record ) {
			$meta = call_user_func(
				$prepare,
				(int) $local,
				[
					'_elementor_data'          => self::remap_json( (string) ( $record['data'] ?? '' ), $map ),
					'_elementor_page_settings' => self::remap( (array) ( $record['page_settings'] ?? [] ), $map ),
				]
			);
			if ( isset( $meta['_elementor_data'] ) && is_string( $meta['_elementor_data'] ) && '' !== $meta['_elementor_data'] ) {
				update_post_meta( (int) $local, '_elementor_data', wp_slash( $meta['_elementor_data'] ) );
			}
			if ( ! empty( $meta['_elementor_page_settings'] ) && is_array( $meta['_elementor_page_settings'] ) ) {
				update_post_meta( (int) $local, '_elementor_page_settings', $meta['_elementor_page_settings'] );
			}
			self::clear_cache( (int) $local );
		}
		return $map;
	}

	/**
	 * Renumber template references in decoded Elementor data.
	 *
	 * @param mixed $data Data.
	 * @param array $map  Source template ID => local template ID.
	 * @return mixed
	 */
	public static function remap( $data, array $map ) {
		if ( ! $map ) {
			return $data;
		}
		if ( is_array( $data ) ) {
			foreach ( $data as $key => $value ) {
				if ( in_array( (string) $key, self::ID_KEYS, true ) && is_numeric( $value ) && isset( $map[ (int) $value ] ) ) {
					$data[ $key ] = is_int( $value ) ? (int) $map[ (int) $value ] : (string) $map[ (int) $value ];
				} else {
					$data[ $key ] = self::remap( $value, $map );
				}
			}
			return $data;
		}
		if ( is_string( $data ) && false !== strpos( $data, 'elementor-template' ) ) {
			return preg_replace_callback(
				'/(\[elementor-template\s+id=["\']?)(\d+)/i',
				static function ( $m ) use ( $map ) {
					return $m[1] . ( isset( $map[ (int) $m[2] ] ) ? (int) $map[ (int) $m[2] ] : $m[2] );
				},
				$data
			);
		}
		return $data;
	}

	/**
	 * Renumber template references in _elementor_data JSON.
	 *
	 * @param string $json JSON.
	 * @param array  $map  Source template ID => local template ID.
	 * @return string
	 */
	public static function remap_json( $json, array $map ) {
		if ( ! $map || ! is_string( $json ) || '' === $json ) {
			return (string) $json;
		}
		$data = json_decode( $json, true );
		return is_array( $data ) ? (string) wp_json_encode( self::remap( $data, $map ) ) : $json;
	}

	/**
	 * Renumber the template references stored on a post.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $map     Source template ID => local template ID.
	 * @return void
	 */
	public static function remap_post( $post_id, array $map ) {
		if ( ! $map || (int) $post_id <= 0 ) {
			return;
		}
		$json = (string) get_post_meta( (int) $post_id, '_elementor_data', true );
		$new  = self::remap_json( $json, $map );
		if ( $new !== $json ) {
			update_post_meta( (int) $post_id, '_elementor_data', wp_slash( $new ) );
		}
		$settings = get_post_meta( (int) $post_id, '_elementor_page_settings', true );
		if ( is_array( $settings ) ) {
			$remapped = self::remap( $settings, $map );
			if ( $remapped !== $settings ) {
				update_post_meta( (int) $post_id, '_elementor_page_settings', $remapped );
			}
		}
		self::clear_cache( (int) $post_id );
	}

	/**
	 * Drop Elementor's generated CSS of a post.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	private static function clear_cache( $post_id ) {
		delete_post_meta( (int) $post_id, '_elementor_css' );
		delete_post_meta( (int) $post_id, '_elementor_element_cache' );
		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}
}

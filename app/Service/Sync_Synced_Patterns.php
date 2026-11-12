<?php
/** Carry synced patterns (wp_block, referenced as <!-- wp:block {"ref":N} /-->) with synchronized content. */
namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

/**
 * A post that uses a synced pattern only stores the pattern's post ID. Without the pattern the
 * target shows "This block has been deleted or is unavailable". The sender adds the referenced
 * patterns to the payload; the receiver creates or updates them and re-points the refs.
 */
class Sync_Synced_Patterns {
	const SOURCE_ID_META   = '_rsl_ie_source_pattern_id';
	const SOURCE_SITE_META = '_rsl_ie_source_pattern_site';
	const MAX_PATTERNS     = 100;
	/** Keys in an export file's post_meta column that carry the patterns and block terms (not stored as meta). */
	const FILE_PATTERNS_KEY = 'rsl_ie_synced_patterns';
	const FILE_TERMS_KEY    = 'rsl_ie_block_terms';

	/**
	 * Re-point a file-imported post's pattern and block-term refs.
	 *
	 * @param int   $post_id  Imported post.
	 * @param mixed $patterns FILE_PATTERNS_KEY value (source ID => pattern data).
	 * @param mixed $terms    FILE_TERMS_KEY value (source term ID => taxonomy/slug/name).
	 * @return int[] Local patterns created or updated from the file.
	 */
	public static function apply_file_payload( $post_id, $patterns, $terms ) {
		$post_id = (int) $post_id;
		$content = (string) get_post_field( 'post_content', $post_id, 'raw' );
		if ( $post_id <= 0 || '' === $content ) {
			return array();
		}
		// XML exports write numeric keys as <field_123> elements: restore the source IDs.
		$unprefix = static function ( $list ) {
			$out = array();
			foreach ( is_array( $list ) ? $list : array() as $key => $value ) {
				$out[ absint( preg_replace( '/^field_/', '', (string) $key ) ) ] = $value;
			}
			unset( $out[0] );
			return $out;
		};
		$patterns = $unprefix( $patterns );
		$terms    = $unprefix( $terms );
		$site     = '';
		foreach ( $patterns as $p ) {
			$site = is_array( $p ) ? untrailingslashit( (string) ( $p['site'] ?? '' ) ) : '';
			break;
		}
		// A file made on this site already points at the right patterns and terms.
		if ( '' !== $site && untrailingslashit( home_url() ) === $site ) {
			return array();
		}
		$fixed = $content;
		$map   = array();
		if ( $patterns && self::refs( $fixed ) ) {
			$map   = self::import( $patterns );
			$fixed = self::apply( $fixed, $map );
		}
		// Terms are matched by taxonomy and slug, so a same-site file maps each ID to itself.
		if ( is_array( $terms ) && $terms ) {
			$fixed = self::apply_terms( $fixed, self::import_terms( $terms ) );
		}
		if ( $fixed !== $content ) {
			wp_update_post( wp_slash( array( 'ID' => $post_id, 'post_content' => $fixed ) ) );
		}
		return array_values( array_unique( array_map( 'intval', $map ) ) );
	}

	/**
	 * Pattern IDs referenced by block markup.
	 *
	 * @param string $content Block markup.
	 * @return int[]
	 */
	public static function refs( $content ) {
		if ( ! is_string( $content ) || ( false === strpos( $content, 'wp:block' ) && false === strpos( $content, 'wp:navigation' ) ) ) {
			return array();
		}
		// Synced patterns (wp:block) and navigation menus (wp:navigation) store a post ID in "ref".
		preg_match_all( '/<!--\s+wp:(?:block|navigation)\s+\{[^}]*"ref"\s*:\s*(\d+)/', $content, $m );
		return array_values( array_unique( array_map( 'intval', $m[1] ) ) );
	}

	/**
	 * Collect the referenced patterns of outgoing posts (and patterns used inside them).
	 *
	 * @param array $posts_data Outgoing posts (post_content).
	 * @return array Source ID => pattern data.
	 */
	public static function collect( array $posts_data ) {
		$queue = array();
		foreach ( $posts_data as $post ) {
			$queue = array_merge( $queue, self::refs( is_array( $post ) ? ( $post['post_content'] ?? '' ) : '' ) );
		}
		$out = array();
		for ( $depth = 0; $queue && $depth < 4 && count( $out ) < self::MAX_PATTERNS; ++$depth ) {
			$next = array();
			foreach ( array_unique( $queue ) as $id ) {
				if ( isset( $out[ $id ] ) ) {
					continue;
				}
				$block = get_post( $id );
				if ( ! $block || ! in_array( $block->post_type, array( 'wp_block', 'wp_navigation' ), true ) || ! current_user_can( 'read_post', $id ) ) {
					continue;
				}
				$out[ $id ] = array(
					'post_type' => $block->post_type,
					'title'   => $block->post_title,
					'name'    => $block->post_name,
					'status'  => $block->post_status,
					'content' => $block->post_content,
					'site'    => untrailingslashit( home_url() ),
					'sync'    => (string) get_post_meta( $id, 'wp_pattern_sync_status', true ),
					// Where this pattern came from, so a round trip finds the original again.
					'origin_id'   => (int) get_post_meta( $id, self::SOURCE_ID_META, true ),
					'origin_site' => (string) get_post_meta( $id, self::SOURCE_SITE_META, true ),
					'categories'  => self::categories_of( $id ),
				);
				$next = array_merge( $next, self::refs( $block->post_content ) );
			}
			$queue = $next;
		}
		return $out;
	}

	/**
	 * Create or update the incoming patterns here.
	 *
	 * @param mixed $patterns Source ID => data from the sender.
	 * @return array Source ID => local pattern ID.
	 */
	public static function import( $patterns ) {
		if ( ! is_array( $patterns ) || ! $patterns || ! current_user_can( 'edit_posts' ) ) {
			return array();
		}
		$map = array();
		foreach ( array_slice( $patterns, 0, self::MAX_PATTERNS, true ) as $source_id => $p ) {
			$source_id = absint( $source_id );
			if ( ! $source_id || ! is_array( $p ) || ! isset( $p['content'] ) || ! is_string( $p['content'] ) ) {
				continue;
			}
			$site  = esc_url_raw( (string) ( $p['site'] ?? '' ) );
			$type  = 'wp_navigation' === ( $p['post_type'] ?? '' ) ? 'wp_navigation' : 'wp_block';
			$p['post_type'] = $type;
			$local = self::find( $source_id, $site, $p );
			$args  = array(
				'post_type'    => $type,
				'post_title'   => sanitize_text_field( (string) ( $p['title'] ?? '' ) ),
				'post_content' => $p['content'],
				'post_status'  => in_array( $p['status'] ?? '', array( 'publish', 'draft', 'private' ), true ) ? $p['status'] : 'publish',
			);
			if ( $local ) {
				if ( ! current_user_can( 'edit_post', $local ) ) {
					$map[ $source_id ] = $local;
					continue;
				}
				$args['ID'] = $local;
				$id         = wp_update_post( wp_slash( $args ), true );
			} else {
				$args['post_name'] = sanitize_title( (string) ( $p['name'] ?? '' ) );
				$id                = wp_insert_post( wp_slash( $args ), true );
			}
			if ( is_wp_error( $id ) || ! $id ) {
				continue;
			}
			update_post_meta( $id, self::SOURCE_ID_META, $source_id );
			update_post_meta( $id, self::SOURCE_SITE_META, $site );
			if ( 'unsynced' === ( $p['sync'] ?? '' ) ) {
				update_post_meta( $id, 'wp_pattern_sync_status', 'unsynced' );
			} elseif ( 'wp_block' === $type ) {
				delete_post_meta( $id, 'wp_pattern_sync_status' );
			}
			if ( 'wp_block' === $type && isset( $p['categories'] ) && is_array( $p['categories'] ) ) {
				self::set_categories( $id, $p['categories'] );
			}
			$map[ $source_id ] = (int) $id;
		}
		// Patterns used inside other patterns.
		foreach ( $map as $local ) {
			$content = get_post_field( 'post_content', $local, 'raw' );
			$fixed   = self::apply( $content, $map );
			if ( $fixed !== $content ) {
				wp_update_post( wp_slash( array( 'ID' => $local, 'post_content' => $fixed ) ) );
			}
		}
		return $map;
	}

	/**
	 * Re-point pattern refs in block markup.
	 *
	 * @param string $content Block markup.
	 * @param array  $map     Source ID => local ID.
	 * @return string
	 */
	public static function apply( $content, array $map ) {
		if ( ! $map || ! is_string( $content ) || ( false === strpos( $content, 'wp:block' ) && false === strpos( $content, 'wp:navigation' ) ) ) {
			return $content;
		}
		return (string) preg_replace_callback(
			'/(<!--\s+wp:(?:block|navigation)\s+\{[^}]*"ref"\s*:\s*)(\d+)/',
			static function ( $m ) use ( $map ) {
				return $m[1] . ( isset( $map[ (int) $m[2] ] ) ? (int) $map[ (int) $m[2] ] : $m[2] );
			},
			$content
		);
	}

	/**
	 * Terms referenced by ID in block attributes (Query Loop taxQuery, Latest Posts categories).
	 *
	 * @param array $posts_data Outgoing posts.
	 * @return array term_id => [taxonomy, slug, name]
	 */
	public static function collect_terms( array $posts_data ) {
		$out = array();
		foreach ( $posts_data as $post ) {
			$content = is_array( $post ) ? (string) ( $post['post_content'] ?? '' ) : '';
			foreach ( self::block_term_ids( $content ) as $term_id ) {
				$term = get_term( $term_id );
				if ( $term && ! is_wp_error( $term ) ) {
					$out[ $term_id ] = array( 'taxonomy' => $term->taxonomy, 'slug' => $term->slug, 'name' => $term->name );
				}
			}
		}
		return array_slice( $out, 0, 500, true );
	}

	/**
	 * Map incoming block term IDs to local terms (same taxonomy and slug, created when missing).
	 *
	 * @param mixed $terms term_id => [taxonomy, slug, name].
	 * @return array Source term ID => local term ID.
	 */
	public static function import_terms( $terms ) {
		$map = array();
		foreach ( is_array( $terms ) ? array_slice( $terms, 0, 500, true ) : array() as $source => $t ) {
			$taxonomy = sanitize_key( (string) ( $t['taxonomy'] ?? '' ) );
			$slug     = sanitize_title( (string) ( $t['slug'] ?? '' ) );
			if ( ! absint( $source ) || '' === $slug || ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}
			$local = get_term_by( 'slug', $slug, $taxonomy );
			if ( ! $local && current_user_can( get_taxonomy( $taxonomy )->cap->manage_terms ) ) {
				$new   = wp_insert_term( wp_slash( sanitize_text_field( (string) ( $t['name'] ?? $slug ) ) ), $taxonomy, array( 'slug' => $slug ) );
				$local = is_wp_error( $new ) ? false : get_term( (int) $new['term_id'], $taxonomy );
			}
			if ( $local && ! is_wp_error( $local ) ) {
				$map[ absint( $source ) ] = (int) $local->term_id;
			}
		}
		return $map;
	}

	/**
	 * Rewrite term IDs inside Query Loop / Latest Posts block attributes; other bytes stay.
	 *
	 * @param string $content Block markup.
	 * @param array  $map     Source term ID => local term ID.
	 * @return string
	 */
	public static function apply_terms( $content, array $map ) {
		if ( ! $map || ! is_string( $content ) ) {
			return $content;
		}
		return (string) preg_replace_callback(
			'/<!--\s+wp:(query|latest-posts)\s+(\{(?:(?:[^}]+|}+(?=})|(?!}\s+\/?-->).)*+)?})(\s+\/?-->)/s',
			static function ( $m ) use ( $map ) {
				$attrs = json_decode( $m[2], true );
				if ( ! is_array( $attrs ) ) {
					return $m[0];
				}
				$swap    = static function ( $id ) use ( $map ) { return isset( $map[ (int) $id ] ) ? $map[ (int) $id ] : $id; };
				$changed = $attrs;
				if ( 'query' === $m[1] && isset( $changed['query']['taxQuery'] ) && is_array( $changed['query']['taxQuery'] ) ) {
					foreach ( $changed['query']['taxQuery'] as $tax => $ids ) {
						if ( is_array( $ids ) ) {
							$changed['query']['taxQuery'][ $tax ] = array_map( $swap, $ids );
						}
					}
				}
				if ( 'latest-posts' === $m[1] && isset( $changed['categories'] ) && is_array( $changed['categories'] ) ) {
					foreach ( $changed['categories'] as $k => $cat ) {
						if ( is_array( $cat ) && isset( $cat['id'] ) ) {
							$changed['categories'][ $k ]['id'] = $swap( $cat['id'] );
						}
					}
				}
				return $changed === $attrs ? $m[0] : '<!-- wp:' . $m[1] . ' ' . serialize_block_attributes( self::keep_empty_objects( $changed, json_decode( $m[2] ) ) ) . $m[3];
			},
			$content
		);
	}

	/** Keep {} where the original attributes had an empty object (json_decode turns it into []). */
	private static function keep_empty_objects( $value, $original ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( array() === $value ) {
			return ( $original instanceof \stdClass && array() === get_object_vars( $original ) ) ? new \stdClass() : $value;
		}
		foreach ( $value as $key => $item ) {
			$orig          = $original instanceof \stdClass ? ( $original->$key ?? null ) : ( is_array( $original ) ? ( $original[ $key ] ?? null ) : null );
			$value[ $key ] = self::keep_empty_objects( $item, $orig );
		}
		return $value;
	}

	/** Term IDs used by Query Loop taxQuery and Latest Posts categories. */
	private static function block_term_ids( $content ) {
		$ids = array();
		if ( false === strpos( $content, 'wp:query' ) && false === strpos( $content, 'wp:latest-posts' ) ) {
			return $ids;
		}
		preg_match_all( '/<!--\s+wp:(query|latest-posts)\s+(\{(?:(?:[^}]+|}+(?=})|(?!}\s+\/?-->).)*+)?})\s+\/?-->/s', $content, $m, PREG_SET_ORDER );
		foreach ( $m as $one ) {
			$attrs = json_decode( $one[2], true );
			foreach ( (array) ( $attrs['query']['taxQuery'] ?? array() ) as $list ) {
				foreach ( (array) $list as $id ) { $ids[] = (int) $id; }
			}
			foreach ( (array) ( $attrs['categories'] ?? array() ) as $cat ) {
				if ( is_array( $cat ) && isset( $cat['id'] ) ) { $ids[] = (int) $cat['id']; }
			}
		}
		return array_values( array_unique( array_filter( $ids ) ) );
	}

	/**
	 * Pattern categories of a pattern as slug => name.
	 *
	 * @param int $id Pattern ID.
	 * @return array
	 */
	private static function categories_of( $id ) {
		$out   = array();
		$terms = taxonomy_exists( 'wp_pattern_category' ) ? wp_get_object_terms( $id, 'wp_pattern_category' ) : array();
		foreach ( is_wp_error( $terms ) ? array() : $terms as $term ) {
			$out[ $term->slug ] = $term->name;
		}
		return $out;
	}

	/**
	 * Give a local pattern the incoming categories (matched by slug, created when allowed).
	 *
	 * @param int   $id         Local pattern ID.
	 * @param array $categories Slug => name.
	 */
	private static function set_categories( $id, array $categories ) {
		if ( ! taxonomy_exists( 'wp_pattern_category' ) ) {
			return;
		}
		$ids = array();
		foreach ( array_slice( $categories, 0, 50, true ) as $slug => $name ) {
			$slug = sanitize_title( (string) $slug );
			$name = sanitize_text_field( (string) $name );
			if ( '' === $slug ) {
				continue;
			}
			$term = get_term_by( 'slug', $slug, 'wp_pattern_category' );
			if ( ! $term && current_user_can( get_taxonomy( 'wp_pattern_category' )->cap->manage_terms ) ) {
				$made = wp_insert_term( '' !== $name ? $name : $slug, 'wp_pattern_category', array( 'slug' => $slug ) );
				$term = is_wp_error( $made ) ? null : get_term( (int) $made['term_id'], 'wp_pattern_category' );
			}
			if ( $term && ! is_wp_error( $term ) ) {
				$ids[] = (int) $term->term_id;
			}
		}
		wp_set_object_terms( $id, $ids, 'wp_pattern_category' );
	}

	/**
	 * The local copy of a source pattern: one synced earlier from the same site, else an
	 * existing pattern with the same slug and identical content (never another pattern).
	 */
	private static function find( $source_id, $site, array $p ) {
		$type = $p['post_type'] ?? 'wp_block';
		$ids  = get_posts(
			array(
				'post_type'      => $type,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery -- Bounded identity lookup.
					array( 'key' => self::SOURCE_ID_META, 'value' => $source_id ),
					array( 'key' => self::SOURCE_SITE_META, 'value' => $site ),
				),
			)
		);
		if ( $ids ) {
			return (int) $ids[0];
		}
		if ( untrailingslashit( home_url() ) === $site && $type === get_post_type( $source_id ) ) {
			return (int) $source_id; // Same site (loopback): the pattern already exists.
		}
		$origin = absint( $p['origin_id'] ?? 0 );
		if ( $origin && untrailingslashit( home_url() ) === untrailingslashit( (string) ( $p['origin_site'] ?? '' ) ) && $type === get_post_type( $origin ) ) {
			return $origin; // A pattern that went from here to the other site and now comes back.
		}
		$name = sanitize_title( (string) ( $p['name'] ?? '' ) );
		if ( '' === $name ) {
			return 0;
		}
		$same = get_page_by_path( $name, OBJECT, $type );
		return $same && $same->post_content === $p['content'] ? (int) $same->ID : 0;
	}
}

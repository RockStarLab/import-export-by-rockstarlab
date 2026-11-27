<?php
/**
 * Content Sync term payload: names of parent terms and the description, so a receiving site that has
 * to create a term (or its parents) gives them their real names, not names made from the slug.
 *
 * @package RockStarLab\ImportExport
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

class Term_Payload {

	/** Register the payload filter (every sender of post, product and taxonomy sync applies it). */
	public static function hooks() {
		add_filter( 'rsl_ie_sync_term_payload', array( __CLASS__, 'add_to_payload' ), 5, 3 );
	}

	/**
	 * Add parent names (slug => name, root first) and the description.
	 *
	 * @param array    $info     Term payload.
	 * @param \WP_Term $term     Term.
	 * @param string   $taxonomy Taxonomy.
	 * @return array
	 */
	public static function add_to_payload( $info, $term, $taxonomy ) {
		$info = (array) $info;
		if ( ! $term instanceof \WP_Term ) {
			return $info;
		}
		if ( ! isset( $info['description'] ) ) {
			$info['description'] = (string) $term->description;
		}
		if ( ! isset( $info['parent_names'] ) && $term->parent ) {
			$names = array();
			foreach ( array_reverse( get_ancestors( (int) $term->term_id, (string) $taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
				$ancestor = get_term( (int) $ancestor_id, (string) $taxonomy );
				if ( $ancestor instanceof \WP_Term ) {
					$names[ $ancestor->slug ] = $ancestor->name;
				}
			}
			$info['parent_names'] = $names;
		}
		// Plain term meta for a receiver that has to create the term (a term sync payload carries
		// its own full "meta" and is left alone).
		if ( ! isset( $info['term_meta'] ) && ! isset( $info['meta'] ) ) {
			$meta = self::portable_meta( $term, $taxonomy, array_keys( (array) ( $info['acf'] ?? array() ) ) );
			if ( $meta ) {
				$info['term_meta'] = $meta;
			}
		}
		return $info;
	}

	/** Largest serialized size of one meta value sent with a post (bytes). */
	const MAX_META_BYTES = 65536;

	/** Terms created while a resolve runs (term_id => true), per nesting level. */
	private static $watch = array();

	/**
	 * Term meta worth sending with a post: no private "_" keys, no SEO keys (sent as SEO settings),
	 * no ACF fields (sent as ACF values), no counters.
	 *
	 * @param \WP_Term $term       Term.
	 * @param string   $taxonomy   Taxonomy.
	 * @param array    $acf_fields ACF field names already in the payload.
	 * @return array key => list of values
	 */
	public static function portable_meta( $term, $taxonomy, array $acf_fields = array() ) {
		$out = array();
		foreach ( (array) get_term_meta( (int) $term->term_id ) as $key => $values ) {
			$key = (string) $key;
			if ( '' === $key || '_' === $key[0] || in_array( $key, $acf_fields, true ) || preg_match( '/^(rank_math_|wpseo|pll_|icl_|product_count_)/', $key ) ) {
				continue;
			}
			$list = array();
			foreach ( (array) $values as $value ) {
				if ( strlen( (string) $value ) > self::MAX_META_BYTES ) {
					continue;
				}
				$list[] = maybe_unserialize( $value );
			}
			if ( $list ) {
				$out[ $key ] = $list;
			}
		}
		/**
		 * Filter the term meta sent with a synced post (key => list of values).
		 *
		 * @param array    $out      Meta.
		 * @param \WP_Term $term     Term.
		 * @param string   $taxonomy Taxonomy.
		 */
		return (array) apply_filters( 'rsl_ie_sync_post_term_meta', $out, $term, (string) $taxonomy );
	}

	/**
	 * Attachment IDs in the sent term meta that the receiver needs as files (the term image).
	 *
	 * @param array $info Term payload.
	 * @return int[]
	 */
	public static function meta_image_ids( array $info ) {
		$ids = array();
		foreach ( (array) ( $info['term_meta']['thumbnail_id'] ?? array() ) as $id ) {
			if ( is_scalar( $id ) && absint( $id ) && wp_attachment_is_image( absint( $id ) ) ) {
				$ids[] = absint( $id );
			}
		}
		return $ids;
	}

	/**
	 * Run a term resolve and give the term the sent meta when the resolve created it.
	 *
	 * @param string   $taxonomy  Taxonomy.
	 * @param array    $term_info Received term.
	 * @param callable $resolve   Returns the local term ID.
	 * @param array    $image_map Source attachment ID => local attachment ID.
	 * @return int
	 */
	public static function resolve_with_meta( $taxonomy, array $term_info, callable $resolve, array $image_map = array() ) {
		self::$watch[] = array();
		$level         = count( self::$watch ) - 1;
		$listener      = static function ( $term_id, $tt_id, $created_taxonomy ) use ( $level, $taxonomy ) {
			if ( (string) $created_taxonomy === (string) $taxonomy && isset( self::$watch[ $level ] ) ) {
				self::$watch[ $level ][ (int) $term_id ] = true;
			}
		};
		add_action( 'created_term', $listener, 10, 3 );
		try {
			$term_id = (int) $resolve();
		} finally {
			remove_action( 'created_term', $listener, 10 );
			$created = (array) array_pop( self::$watch );
		}
		if ( $term_id > 0 && isset( $created[ $term_id ] ) && ! empty( $term_info['term_meta'] ) && is_array( $term_info['term_meta'] ) ) {
			self::apply_new_term_meta( $term_id, $term_info['term_meta'], $image_map );
		}
		return $term_id;
	}

	/**
	 * Save sent meta on a term this site has just created. Keys the term already has (a plugin set
	 * them on creation) are left alone; the term image points at this site's copy of the file or is
	 * left out when the file did not come.
	 *
	 * @param int   $term_id   Local term ID.
	 * @param array $meta      key => list of values.
	 * @param array $image_map Source attachment ID => local attachment ID.
	 * @return void
	 */
	public static function apply_new_term_meta( $term_id, array $meta, array $image_map = array() ) {
		foreach ( $meta as $key => $values ) {
			$key = preg_match( '/^[A-Za-z0-9][A-Za-z0-9_\-:.]{0,190}$/', (string) $key ) ? (string) $key : '';
			if ( '' === $key || '_' === $key[0] || metadata_exists( 'term', (int) $term_id, $key ) ) {
				continue;
			}
			foreach ( (array) $values as $value ) {
				if ( 'thumbnail_id' === $key ) {
					$source = is_scalar( $value ) ? absint( $value ) : 0;
					$value  = $source && isset( $image_map[ $source ] ) ? (int) $image_map[ $source ] : 0;
					if ( ! $value ) {
						continue;
					}
				}
				add_term_meta( (int) $term_id, $key, wp_slash( $value ) );
			}
		}
	}

	/**
	 * Name for a parent term the receiver creates from a slug path.
	 *
	 * @param string $slug  Sanitized slug.
	 * @param array  $names Parent names from the payload (slug => name).
	 * @return string
	 */
	public static function parent_name( $slug, array $names ) {
		foreach ( $names as $key => $name ) {
			if ( is_scalar( $name ) && '' !== trim( (string) $name ) && sanitize_title( (string) $key ) === $slug ) {
				return sanitize_text_field( (string) $name );
			}
		}
		// Older senders: a non-Latin slug is percent-encoded; show the decoded words.
		$decoded = rawurldecode( $slug );
		if ( $decoded !== $slug ) {
			return sanitize_text_field( str_replace( array( '-', '_' ), ' ', $decoded ) );
		}
		return ucwords( str_replace( array( '-', '_' ), ' ', $slug ) );
	}

	/**
	 * Assign the resolved local terms to a synced object. Polylang filters get_terms() (and so
	 * term_exists(), which wp_set_object_terms() uses) by the request's language: a term of another
	 * language looked missing and was silently left out. A "lang" that names no language turns that filter
	 * off (0, not "": Polylang resolves "" to a language in some requests); other plugins ignore the argument.
	 *
	 * @param int    $object_id Object ID.
	 * @param array  $term_ids  Local term IDs.
	 * @param string $taxonomy  Taxonomy.
	 * @return array|\WP_Error
	 */
	public static function set_object_terms( $object_id, array $term_ids, $taxonomy ) {
		$all_languages = static function ( $args ) {
			$args         = (array) $args;
			$args['lang'] = 0;
			return $args;
		};
		add_filter( 'get_terms_args', $all_languages, 99 );
		try {
			return wp_set_object_terms( (int) $object_id, array_map( 'intval', $term_ids ), (string) $taxonomy );
		} finally {
			remove_filter( 'get_terms_args', $all_languages, 99 );
		}
	}

	/**
	 * Description for a term the receiver creates.
	 *
	 * @param array $info Term payload.
	 * @return string
	 */
	public static function description( array $info ) {
		return isset( $info['description'] ) && is_scalar( $info['description'] ) ? wp_kses_post( (string) $info['description'] ) : '';
	}
}

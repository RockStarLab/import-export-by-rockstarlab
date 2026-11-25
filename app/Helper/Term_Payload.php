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
		add_filter( 'rsl_ie_sync_term_payload', [ __CLASS__, 'add_to_payload' ], 5, 3 );
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
			$names = [];
			foreach ( array_reverse( get_ancestors( (int) $term->term_id, (string) $taxonomy, 'taxonomy' ) ) as $ancestor_id ) {
				$ancestor = get_term( (int) $ancestor_id, (string) $taxonomy );
				if ( $ancestor instanceof \WP_Term ) {
					$names[ $ancestor->slug ] = $ancestor->name;
				}
			}
			$info['parent_names'] = $names;
		}
		return $info;
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
			return sanitize_text_field( str_replace( [ '-', '_' ], ' ', $decoded ) );
		}
		return ucwords( str_replace( [ '-', '_' ], ' ', $slug ) );
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

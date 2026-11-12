<?php
/**
 * Content Sync: children that arrive before their parent are attached once the parent arrives.
 *
 * @package RockStarLab\ImportExport
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Remembers the source parent of a synced child whose parent is not on this site yet.
 */
class Sync_Pending_Parents {
	/** Source-site ID of the parent a synced child is waiting for. */
	const META_KEY = '_rsl_ie_pending_source_parent';

	/**
	 * Remember that a local child waits for a parent from the source site.
	 *
	 * @param int $local_id         Local child ID.
	 * @param int $source_parent_id Parent ID on the source site.
	 * @return void
	 */
	public static function remember( $local_id, $source_parent_id ) {
		$local_id         = absint( $local_id );
		$source_parent_id = absint( $source_parent_id );
		if ( $local_id && $source_parent_id ) {
			update_post_meta( $local_id, self::META_KEY, $source_parent_id );
		}
	}

	/**
	 * The child's parent is settled (found, or top level on the source).
	 *
	 * @param int $local_id Local child ID.
	 * @return void
	 */
	public static function clear( $local_id ) {
		delete_post_meta( absint( $local_id ), self::META_KEY );
	}

	/**
	 * Attach the local children that wait for this parent.
	 *
	 * @param int    $source_id Parent ID on the source site.
	 * @param int    $local_id  The parent's local ID.
	 * @param string $post_type Post type of the parent.
	 * @return int[] Local IDs of the children attached.
	 */
	public static function adopt( $source_id, $local_id, $post_type ) {
		$source_id = absint( $source_id );
		$local_id  = absint( $local_id );
		if ( ! $source_id || ! $local_id || ! is_string( $post_type ) || '' === $post_type || ! is_post_type_hierarchical( $post_type ) ) {
			return array();
		}
		$children = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => 'any',
				'posts_per_page'   => 200,
				'fields'           => 'ids',
				'suppress_filters' => true,
				'meta_key'         => self::META_KEY, // phpcs:ignore WordPress.DB.SlowDBQuery -- Bounded lookup of waiting children.
				'meta_value'       => $source_id, // phpcs:ignore WordPress.DB.SlowDBQuery -- Bounded lookup of waiting children.
			)
		);
		$attached = array();
		foreach ( $children as $child_id ) {
			$child_id = (int) $child_id;
			if ( $child_id === $local_id ) {
				continue;
			}
			// A parent chosen on this site in the meantime is kept.
			if ( 0 === (int) get_post_field( 'post_parent', $child_id ) && current_user_can( 'edit_post', $child_id ) ) {
				$modified = get_post_field( 'post_modified', $child_id, 'raw' );
				$gmt      = get_post_field( 'post_modified_gmt', $child_id, 'raw' );
				$result   = wp_update_post(
					array(
						'ID'          => $child_id,
						'post_parent' => $local_id,
					),
					true
				);
				if ( is_wp_error( $result ) ) {
					continue;
				}
				// Attaching is bookkeeping, not an edit of the child.
				global $wpdb;
				$wpdb->update( $wpdb->posts, array( 'post_modified' => $modified, 'post_modified_gmt' => $gmt ), array( 'ID' => $child_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Restore the dates wp_update_post() changed.
				clean_post_cache( $child_id );
				$attached[] = $child_id;
			}
			self::clear( $child_id );
		}
		return $attached;
	}
}

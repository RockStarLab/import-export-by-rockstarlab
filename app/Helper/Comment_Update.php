<?php
/**
 * wp_update_comment() that keeps the comment's GMT date.
 *
 * @package RockStarLab\ImportExport
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * WordPress recomputes comment_date_gmt from comment_date with this site's time zone on every
 * wp_update_comment() call, even when the GMT date is passed or only the parent changes. Comments
 * from a site in another time zone (or with mixed offsets) then moved by hours on every repeat
 * import or sync.
 */
class Comment_Update {
	/**
	 * Update a comment and keep its GMT date: the one passed, or the stored one when the call
	 * does not change the local date.
	 *
	 * @param array $data     Comment data, as for wp_update_comment() (may be slashed).
	 * @param bool  $wp_error Return a WP_Error on failure.
	 * @return int|false|\WP_Error Result of wp_update_comment().
	 */
	public static function update( array $data, $wp_error = false ) {
		$id  = isset( $data['comment_ID'] ) ? absint( $data['comment_ID'] ) : 0;
		$gmt = isset( $data['comment_date_gmt'] ) && '' !== (string) $data['comment_date_gmt'] ? (string) wp_unslash( $data['comment_date_gmt'] ) : null;
		if ( null === $gmt && $id > 0 && empty( $data['comment_date'] ) ) {
			$current = get_comment( $id );
			$gmt     = $current ? (string) $current->comment_date_gmt : null;
		}

		$result = wp_update_comment( $data, $wp_error );

		if ( $id > 0 && null !== $gmt && false !== $result && ! is_wp_error( $result ) && preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $gmt ) ) {
			global $wpdb;
			$stored = $wpdb->get_var( $wpdb->prepare( "SELECT comment_date_gmt FROM {$wpdb->comments} WHERE comment_ID = %d", $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			if ( null !== $stored && $stored !== $gmt ) {
				$wpdb->update( $wpdb->comments, array( 'comment_date_gmt' => $gmt ), array( 'comment_ID' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
				clean_comment_cache( $id );
			}
		}

		return $result;
	}
}

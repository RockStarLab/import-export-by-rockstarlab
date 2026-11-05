<?php
/**
 * Content Sync post author helper.
 *
 * User IDs differ between sites, so the sender adds the author's email and login
 * to every synced post and the receiver resolves them to a local user.
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

class Sync_Post_Author {

	/**
	 * Portable author identity added to an outgoing post payload.
	 *
	 * @param int $user_id Local author ID.
	 * @return array{post_author_email?: string, post_author_login?: string}
	 */
	public static function payload( $user_id ) {
		$user = get_userdata( (int) $user_id );
		if ( ! $user ) {
			return array();
		}
		return array(
			'post_author_email' => (string) $user->user_email,
			'post_author_login' => (string) $user->user_login,
		);
	}

	/**
	 * Resolve the author for an incoming post.
	 *
	 * 1. A local user with the same email (then the same login) who can edit this post type.
	 * 2. Update of an existing post: keep its current author.
	 * 3. New post: the user the request runs as (connection key owner), as before.
	 *
	 * @param array $post_data        Incoming post payload.
	 * @param int   $existing_post_id Local post being updated, 0 for a new post.
	 * @return int
	 */
	public static function resolve( array $post_data, $existing_post_id = 0 ) {
		$matched = self::match_local_user( $post_data );
		if ( $matched ) {
			return $matched;
		}
		$existing_post_id = (int) $existing_post_id;
		if ( $existing_post_id > 0 ) {
			$current = (int) get_post_field( 'post_author', $existing_post_id );
			if ( $current > 0 ) {
				return $current;
			}
		}
		return (int) get_current_user_id();
	}

	/**
	 * @param array $post_data Incoming post payload.
	 * @return int Local user ID or 0.
	 */
	private static function match_local_user( array $post_data ) {
		$user  = false;
		$email = isset( $post_data['post_author_email'] ) ? sanitize_email( (string) $post_data['post_author_email'] ) : '';
		if ( '' !== $email ) {
			$user = get_user_by( 'email', $email );
		}
		if ( ! $user && ! empty( $post_data['post_author_login'] ) ) {
			$user = get_user_by( 'login', sanitize_user( (string) $post_data['post_author_login'], true ) );
		}
		if ( ! $user ) {
			return 0;
		}
		$post_type = isset( $post_data['post_type'] ) ? (string) $post_data['post_type'] : 'post';
		$type      = get_post_type_object( $post_type );
		$cap       = ( $type && isset( $type->cap->edit_posts ) ) ? $type->cap->edit_posts : 'edit_posts';
		return user_can( $user, $cap ) ? (int) $user->ID : 0;
	}
}

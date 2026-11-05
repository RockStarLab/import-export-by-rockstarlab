<?php
/**
 * Domain authorization for the existing Content Sync peer protocol.
 *
 * @package RockStarLab\ImportExport\Abilities
 */

namespace RockStarLab\ImportExport\Abilities;

defined( 'ABSPATH' ) || exit;

/** Treat every selected ID and resolved destination as an authorization boundary. */
class Sync_Peer_Policy {
	/**
	 * Check a concrete WordPress post type through its owning provider.
	 *
	 * @param string $type Actual WordPress post type.
	 * @param string $operation Sync direction.
	 * @param int    $id Optional local target.
	 * @return true|\WP_Error
	 */
	public static function post( $type, $operation, $id = 0 ) {
		if ( in_array( $type, [ 'post', 'page', 'attachment' ], true ) ) {
			$result = Access_Policy::check_content_type( $type, $operation, [ 'selected_ids' => $id ? [ (int) $id ] : [] ] );
		} else {
			$result = apply_filters( 'rsl_ie_authorize_sync_post_type', null, $type, $operation, $id );
		}
		if ( is_wp_error( $result ) ) {
			return $result; }
		if ( null === $result ) {
			return self::denied(); }
		$definition = get_post_type_object( $type );
		if ( ! $definition || ! current_user_can( $definition->cap->edit_posts ) || ( 'sync_pull' === $operation && ! $id && ! current_user_can( $definition->cap->create_posts ) ) ) {
			return self::denied(); }
		return $id ? Access_Policy::check_object( 'edit_post', $id ) : true;
	}

	/**
	 * Authorize an actual local destination before updating it.
	 *
	 * @param int    $id Resolved ID.
	 * @param string $type Expected type.
	 * @return true|\WP_Error
	 */
	public static function destination( $id, $type ) {
		if ( $id && get_post_type( $id ) !== $type ) {
			return self::denied(); }
		return self::post( $type, 'sync_pull', (int) $id );
	}

	/**
	 * Check the resolved parent and the real comment subtype.
	 *
	 * @param array $comment Incoming record.
	 * @param int   $post_id Resolved local destination.
	 * @return true|\WP_Error
	 */
	public static function comment_destination( array $comment, $post_id, $explicit_mapping = false ) {
		$type = $comment['comment_type'] ?? '';
		if ( ! current_user_can( 'manage_options' ) || ! current_user_can( 'moderate_comments' ) || ! in_array( $type, [ '', 'comment', 'pingback', 'trackback', 'review' ], true ) || ! $post_id ) {
			return self::denied(); }
		$actual = get_post_type( $post_id );
		if ( ( 'review' === $type ) !== ( 'product' === $actual ) ) {
			return self::denied(); }
		// An explicit ordinary-comment mapping may move between posts and pages.
		$source_type = $comment['post']['post_type'] ?? '';
		$mapped_standard_post = $explicit_mapping && in_array( $source_type, [ 'post', 'page' ], true ) && in_array( $actual, [ 'post', 'page' ], true );
		if ( $source_type && $source_type !== $actual && ! $mapped_standard_post ) {
			return self::denied(); }
		$access = Access_Policy::check_content_type( 'review' === $type ? 'woo_review' : 'comment', 'sync_pull' );
		if ( is_wp_error( $access ) ) {
			return $access; }
		return self::destination( $post_id, $actual );
	}

	/**
	 * Check the request before the legacy domain handler executes.
	 *
	 * @param string $action Handler name.
	 * @param array  $input Parsed payload.
	 * @return true|\WP_Error
	 */
	public static function check( $action, array $input ) {
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access; }
		if ( in_array( $action, [ 'validate_connection', 'get_site_info' ], true ) ) {
			return true; }
		if ( false !== strpos( $action, 'terms' ) ) {
			$access = Access_Policy::check_content_type( 'taxonomy', 'sync_pull', [ 'options' => [ 'taxonomy' => $input['taxonomy'] ?? '' ] ] );
			if ( is_wp_error( $access ) ) {
				return $access; }
			$taxonomy = get_taxonomy( $input['taxonomy'] ?? '' );
			return $taxonomy && current_user_can( $taxonomy->cap->manage_terms ) ? true : self::denied();
		}
		if ( false !== strpos( $action, 'comments' ) ) {
			foreach ( [ 'comments', 'comment_ids' ] as $key ) {
				if ( isset( $input[ $key ] ) && ! is_array( $input[ $key ] ) ) {
					return self::denied(); }
			}
			if ( ! current_user_can( 'moderate_comments' ) ) {
				return self::denied(); }
			$type   = 'review' === ( $input['comment_type'] ?? '' ) ? 'woo_review' : 'comment';
			$access = Access_Policy::check_content_type( $type, 'sync_pull' );
			if ( is_wp_error( $access ) ) {
				return $access; }
			foreach ( $input['comment_ids'] ?? [] as $id ) {
				$comment = get_comment( (int) $id );
				if ( $comment && ( ! current_user_can( 'edit_comment', $id ) || ! Standalone_Sync_Policy::comment_type( $comment->comment_type, $input['comment_type'] ?? '' ) ) ) {
					return self::denied(); }
				if ( ! $comment ) {
					return self::denied(); }
				$access = self::post( get_post_type( $comment->comment_post_ID ), 'sync_push', (int) $comment->comment_post_ID );
				if ( is_wp_error( $access ) ) {
					return $access; }
			}
			foreach ( $input['comments'] ?? [] as $comment ) {
				if ( ! is_array( $comment ) ) {
					return self::denied(); }
				$access = Access_Policy::check_content_type( 'review' === ( $comment['comment_type'] ?? '' ) ? 'woo_review' : 'comment', 'sync_pull' );
				if ( is_wp_error( $access ) ) {
					return $access; }
			}
			foreach ( [ 'target_post_id', 'post_id' ] as $key ) {
				if ( ! empty( $input[ $key ] ) ) {
					$access = self::post( get_post_type( $input[ $key ] ), 'sync_pull', (int) $input[ $key ] );
					if ( is_wp_error( $access ) ) {
						return $access; }
				}
			}
			return true;
		}
		if ( in_array( $action, [ 'check_media', 'upload_media' ], true ) ) {
			return current_user_can( 'upload_files' ) ? true : self::denied(); }
		$ids = $input['post_ids'] ?? [];
		if ( 'get_children_posts' === $action ) {
			$ids = [ $input['parent_id'] ?? 0 ]; }
		foreach ( $ids as $id ) {
			$access = self::post( get_post_type( (int) $id ), 'sync_push', (int) $id );
			if ( is_wp_error( $access ) ) {
				return $access; }
		}
		if ( 'list_posts' === $action && ! empty( $input['post_type'] ) && 'any' !== $input['post_type'] ) {
			return self::post( $input['post_type'], 'sync_push' );
		}
		foreach ( $input['posts'] ?? [] as $post ) {
			if ( ! is_array( $post ) || empty( $post['post_type'] ) || empty( $post['ID'] ) ) {
				return self::denied(); }
			$target = (int) ( $input['post_mapping'][ $post['ID'] ] ?? 0 );
			$access = self::destination( $target, $post['post_type'] );
			if ( is_wp_error( $access ) ) {
				return $access; }
			$allows_nested = empty( $post['variations'] ) && empty( $post['grouped_children'] );
			if ( ! apply_filters( 'rsl_ie_sync_payload_allowed', $allows_nested, $post ) ) {
				return self::denied(); }
			$definition = get_post_type_object( $post['post_type'] );
			if ( ! in_array( $post['post_status'] ?? '', [ 'publish', 'future', 'private', 'draft', 'pending' ], true ) || ( in_array( $post['post_status'], [ 'publish', 'future', 'private' ], true ) && ! current_user_can( $definition->cap->publish_posts ) ) ) {
				return self::denied(); }
		}
		return true;
	}

	/**
	 * Available post types for legacy "any" browsing.
	 *
	 * @return array
	 */
	public static function browsable_types() {
		$types = [];
		foreach ( get_post_types( [ 'show_ui' => true ] ) as $type ) {
			if ( ! is_wp_error( self::post( $type, 'sync_push' ) ) ) {
				$types[] = $type; }
		}
		return $types ? $types : [ '__rsl_ie_denied' ];
	}

	/**
	 * Return the provider synchronization contract.
	 *
	 * @return \WP_Error
	 */
	private static function denied() {
		return new \WP_Error( 'rsl_ie_sync_peer_forbidden', __( 'The current account cannot synchronize this content or destination.', 'import-export-by-rockstarlab' ), [ 'status' => 403 ] );
	}
}

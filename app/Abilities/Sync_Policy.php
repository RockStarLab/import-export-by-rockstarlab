<?php
/**
 * Free sync boundaries, including resolved destinations and remote records.
 *
 * @package RockStarLab\ImportExport\Abilities
 */

namespace RockStarLab\ImportExport\Abilities;

defined( 'ABSPATH' ) || exit;

/** Remote IDs never count as proof of a local object's type or permission. */
class Sync_Policy {
	/**
	 * Check a local post against the selected domain.
	 *
	 * @param int    $id Local ID.
	 * @param string $type Expected WordPress post type.
	 * @return true|\WP_Error
	 */
	public static function local_post( $id, $type ) {
		if ( ! static::allows_type( $type ) || get_post_type( $id ) !== $type ) {
			return static::denied();
		}
		return Access_Policy::check_object( 'edit_post', $id );
	}

	/**
	 * Validate all local selections before remote traffic or mutations.
	 *
	 * @param string $action Service operation.
	 * @param array  $input Validated input.
	 * @return true|\WP_Error
	 */
	public static function input( $action, array $input ) {
		if ( ! in_array( $action, [ 'get_remote_posts', 'search_remote_posts', 'search_local_posts', 'get_children_posts', 'get_local_posts_info', 'auto_map_by_title', 'push_content', 'pull_content' ], true ) ) {
			return static::denied();
		}
		$type = $input['post_type'] ?? '';
		if ( ! static::allows_type( $type ) ) {
			return static::denied();
		}
		$definition = get_post_type_object( $type );
		if ( ! current_user_can( $definition->cap->edit_posts ) || ( 'pull_content' === $action && ( ! current_user_can( $definition->cap->create_posts ) || ! current_user_can( 'upload_files' ) ) ) ) {
			return static::denied();
		}
		$ids     = in_array( $action, [ 'push_content', 'auto_map_by_title', 'get_local_posts_info' ], true ) ? ( $input['post_ids'] ?? [] ) : [];
		$mapping = (array) ( $input['post_mapping'] ?? [] );
		$targets = array_filter( $mapping );
		if ( count( $targets ) !== count( array_unique( $targets ) ) ) {
			return static::denied();
		}
		if ( 'pull_content' === $action ) {
			$ids = array_keys( $mapping );
			if ( $mapping && ( array_diff( (array) ( $input['post_ids'] ?? [] ), array_values( $mapping ) ) || array_diff( array_values( $mapping ), (array) ( $input['post_ids'] ?? [] ) ) ) ) {
				return static::denied();
			}
		} elseif ( 'push_content' === $action && array_diff( array_keys( $mapping ), $ids ) ) {
			return static::denied();
		}
		foreach ( $ids as $id ) {
			$access = static::local_post( (int) $id, $type );
			if ( is_wp_error( $access ) ) {
				return $access;
			}
		}
		if ( isset( $input['hierarchy'] ) ) {
			if ( 'pull_content' === $action ) {
				return \RockStarLab\ImportExport\Service\Sync_Hierarchy::incoming( 'post_type', $type, $input['post_ids'], $input['hierarchy'] ); }
			if ( 'push_content' !== $action ) {
				return static::denied(); }
			return \RockStarLab\ImportExport\Service\Sync_Hierarchy::outgoing( 'post_type', $type, $ids, $input['hierarchy'] );
		}
		return true;
	}

	/**
	 * Check a full remote selection before media downloads or content writes.
	 *
	 * @param mixed  $posts Remote payload.
	 * @param array  $ids Expected remote IDs.
	 * @param string $type Expected post type.
	 * @return true|\WP_Error
	 */
	public static function remote_posts( $posts, array $ids, $type ) {
		if ( ! is_array( $posts ) || count( array_unique( $ids ) ) !== count( $posts ) ) {
			return static::denied();
		}
		$seen = [];
		foreach ( $posts as $post ) {
			if ( ! is_array( $post ) || ( $post['post_type'] ?? '' ) !== $type || ! in_array( (int) ( $post['ID'] ?? 0 ), $ids, true ) || isset( $seen[ $post['ID'] ] ) || ! static::allows_nested( $post ) ) {
				return static::denied();
			}
			$seen[ $post['ID'] ] = true;
			foreach ( [ 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_name', 'post_date' ] as $field ) {
				if ( ! isset( $post[ $field ] ) || ! is_string( $post[ $field ] ) ) {
					return static::denied();
				}
			}
			if ( ! in_array( $post['post_status'], [ 'publish', 'future', 'private', 'draft', 'pending' ], true ) ) {
				return static::denied();
			}
			$definition = get_post_type_object( $type );
			if ( in_array( $post['post_status'], [ 'publish', 'future', 'private' ], true ) && ! current_user_can( $definition->cap->publish_posts ) ) {
				return static::denied();
			}
			foreach ( [ 'meta', 'terms', 'acf', 'comments' ] as $field ) {
				if ( isset( $post[ $field ] ) && ! is_array( $post[ $field ] ) ) {
					return static::denied();
				}
			}
			foreach ( $post['comments'] ?? [] as $comment ) {
				if ( ! is_array( $comment ) || ! static::allows_comment( $comment['comment_type'] ?? '', $type ) ) {
					return static::denied();
				}
			}
		}
		return true;
	}

	/**
	 * Restrict embedded comment domains.
	 *
	 * @param string $comment_type Comment type.
	 * @param string $post_type Parent post type.
	 * @return bool
	 */
	public static function allows_comment( $comment_type, $post_type ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed -- Pro policy uses the parent domain.
		return in_array( $comment_type, [ '', 'comment', 'pingback', 'trackback' ], true ); }

	/**
	 * Declare this provider's post types.
	 *
	 * @param string $type WordPress post type.
	 * @return bool
	 */
	public static function allows_type( $type ) {
		return in_array( $type, [ 'post', 'page' ], true ); }

	/**
	 * Free posts do not include premium nested entities.
	 *
	 * @param array $post Parsed post.
	 * @return bool
	 */
	public static function allows_nested( array $post ) {
		return empty( $post['variations'] ) && empty( $post['grouped_children'] ); }

	/**
	 * Return the provider synchronization contract.
	 *
	 * @return int
	 */
	public static function version() {
		return 1; }

	/**
	 * Stable error without disclosing a rejected object's fields.
	 *
	 * @return \WP_Error
	 */
	public static function denied() {
		return new \WP_Error( 'rsl_ie_sync_scope', __( 'The sync selection or destination is outside the allowed content scope.', 'import-export-by-rockstarlab' ), [ 'status' => 403 ] );
	}
}

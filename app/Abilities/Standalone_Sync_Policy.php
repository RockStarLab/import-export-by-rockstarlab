<?php
/**
 * Selection and destination checks for standalone sync.
 *
 * @package RockStarLab\ImportExport\Abilities
 */

namespace RockStarLab\ImportExport\Abilities;

defined( 'ABSPATH' ) || exit;
/** Validate complete selections before downloading media or writing content. */
class Standalone_Sync_Policy {
	/**
	 * Return a stable standalone policy failure.
	 *
	 * @return \WP_Error
	 */
	public static function denied() {
		return new \WP_Error( 'rsl_ie_standalone_sync_forbidden', __( 'The selected sync domain, records or destinations are invalid or unavailable.', 'import-export-by-rockstarlab' ), [ 'status' => 403 ] ); }
	/**
	 * Authorize explicit local selections and maps.
	 *
	 * @param string $domain Server-selected domain.
	 * @param string $action Operation.
	 * @param array  $input Parsed input.
	 * @param bool   $defer_destinations Internal migration planning only; execution always checks destinations.
	 * @return true|\WP_Error
	 */
	public static function input( $domain, $action, array $input, $defer_destinations = false ) {
		if ( 'terms' === $domain ) {
			return apply_filters( 'rsl_ie_sync_terms_policy', self::denied(), $action, $input ); }
		$type = $input['post_type'] ?? '';
		if ( 'comments' === $domain && ! in_array( $type, [ 'post', 'page' ], true ) ) {
			return self::denied(); }
		if ( 'reviews' === $domain ? 'product' !== $type : 'product' === $type ) {
			return self::denied(); }
		$access = Sync_Peer_Policy::post( $type, 'sync_push' );
		if ( is_wp_error( $access ) ) {
			return $access; }
		if ( ! current_user_can( 'moderate_comments' ) ) {
			return self::denied(); }
		$mapping = (array) ( $input['post_mapping'] ?? [] );
		foreach ( $mapping as $source => $target ) {
			if ( ! preg_match( '/^[1-9][0-9]*$/D', (string) $source ) || ! is_int( $target ) || $target < 1 ) {
				return self::denied(); }
		}
		if ( 'push_comments' === $action ) {
			$parents = [];
			foreach ( $input['comment_ids'] ?? [] as $id ) {
				$comment = get_comment( $id );
				if ( ! $comment || ! self::comment_type( $comment->comment_type, $input['comment_type'] ?? '' ) || get_post_type( $comment->comment_post_ID ) !== $type || ! current_user_can( 'edit_comment', $id ) ) {
					return self::denied(); }
				$parents[] = (int) $comment->comment_post_ID;
			}
			if ( ! $defer_destinations && empty( $input['target_post_id'] ) && ( ! $mapping || array_diff( $parents, array_keys( $mapping ) ) || array_diff( array_keys( $mapping ), $parents ) ) ) {
				return self::denied(); }
		} elseif ( 'pull_comments' === $action && ! $defer_destinations ) {
			$targets = ! empty( $input['target_post_id'] ) ? [ $input['target_post_id'] ] : array_values( $mapping );
			if ( ! $targets ) {
				return self::denied(); }
			foreach ( $targets as $id ) {
				$access = Sync_Peer_Policy::destination( $id, $type );
				if ( is_wp_error( $access ) ) {
					return $access; }
			}
		}
		return true;
	}
	/**
	 * Whether a payload comment belongs to the requested selection.
	 *
	 * @param string $actual Actual comment type.
	 * @param string $requested Selection type.
	 * @return bool
	 */
	public static function comment_type( $actual, $requested ) {
		return '' === $requested ? in_array( $actual, [ '', 'comment', 'pingback', 'trackback' ], true ) : ( 'comment' === $requested ? in_array( $actual, [ '', 'comment' ], true ) : $actual === $requested ); }
	/**
	 * Require exactly the selected source IDs without duplicates.
	 *
	 * @param mixed  $records Remote records.
	 * @param array  $ids Expected IDs.
	 * @param string $key Identity field.
	 * @return bool
	 */
	public static function selection( $records, array $ids, $key ) {
		if ( ! is_array( $records ) || count( $records ) !== count( $ids ) ) {
			return false; }
		$seen = [];
		foreach ( $records as $record ) {
			if ( ! is_array( $record ) || ! isset( $record[ $key ] ) || ! is_scalar( $record[ $key ] ) || ! ctype_digit( (string) $record[ $key ] ) || ! in_array( (int) $record[ $key ], $ids, true ) || isset( $seen[ (int) $record[ $key ] ] ) ) {
				return false; }
			$seen[ (int) $record[ $key ] ] = true;
		}
		return true;
	}
	/**
	 * Validate complete remote comment data before any local media transfer.
	 *
	 * @param array $records Remote comments.
	 * @param array $input Operation input.
	 * @return true|\WP_Error
	 */
	public static function comments( array $records, array $input ) {
		if ( ! self::selection( $records, array_map( 'intval', $input['comment_ids'] ), 'comment_ID' ) ) {
			return self::denied(); }
		$parents = [];
		foreach ( $records as $record ) {
			if ( ( $record['post']['post_type'] ?? '' ) !== $input['post_type'] || ! self::comment_type( $record['comment_type'] ?? '', $input['comment_type'] ?? '' ) ) {
				return self::denied(); }
			$source    = (int) ( $record['comment_post_ID'] ?? 0 );
			$parents[] = $source;
			$target    = (int) ( $input['target_post_id'] ?? $input['post_mapping'][ $source ] ?? 0 );
			if ( ! $target || is_wp_error( Sync_Peer_Policy::destination( $target, $input['post_type'] ) ) ) {
				return self::denied(); }
		}
		if ( empty( $input['target_post_id'] ) && array_diff( array_keys( $input['post_mapping'] ?? [] ), $parents ) ) {
			return self::denied(); }
		return true;
	}
}

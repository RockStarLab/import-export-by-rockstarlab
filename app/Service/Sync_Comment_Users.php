<?php
/**
 * Provider boundary for portable comment account identities.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

/** Source numeric user IDs never identify destination accounts by themselves. */
class Sync_Comment_Users {
	/**
	 * Resolve local assignments through the owning user provider.
	 *
	 * @param array $comments Actual comment payloads.
	 * @param mixed $mapping Optional source-to-destination user map.
	 * @param bool  $destination Whether this is the destination.
	 * @param array $site Optional PUSH connection.
	 * @return array|\WP_Error
	 */
	public static function prepare( array $comments, $mapping = null, $destination = true, array $site = [] ) {
		return null === $mapping ? [] : apply_filters( 'rsl_ie_sync_comment_users', Migration_Sync_Maps::error(), $comments, $mapping, $destination, $site );
	}

	/**
	 * Validate every embedded comment before posts or media are written.
	 *
	 * @param array $posts Actual post payloads.
	 * @param mixed $mapping Optional source-to-destination user map.
	 * @param bool  $destination Whether this is the destination.
	 * @param array $site Optional PUSH connection.
	 * @return array|\WP_Error
	 */
	public static function posts( array $posts, $mapping = null, $destination = true, array $site = [] ) {
		$comments = [];
		foreach ( $posts as $post ) {
			foreach ( $post['comments'] ?? [] as $comment ) {
				$comments[] = $comment;
			}
		}
		return self::prepare( $comments, $mapping, $destination, $site );
	}
}

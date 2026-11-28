<?php
/** Keep bbPress forums, topics and replies connected after Content Sync (push and pull). */
namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

/**
 * bbPress links a topic to its forum and a reply to its topic (and to the reply it answers)
 * through post_parent and the _bbp_forum_id, _bbp_topic_id and _bbp_reply_to meta, all of
 * which hold post IDs of the sending site. After the posts are saved on the receiving site,
 * point these IDs at the local copies (from this sync or an earlier one) and recount the
 * forum and topic totals, so the forum shows its topics and the topics their replies.
 */
class Sync_BbPress {
	const TYPES       = array( 'forum', 'topic', 'reply' );
	const PAYLOAD_KEY = 'rsl_ie_bbp_origins';
	const LINK_META   = array( '_bbp_forum_id', '_bbp_topic_id', '_bbp_reply_to' );

	/** Sender post ID => the ID it was copied from (the receiver's own post on a pull back). */
	private static $origins = array();

	public static function register() {
		add_filter( 'rsl_ie_sync_post_payload', array( __CLASS__, 'payload' ), 10, 2 );
		add_action( 'rsl_ie_sync_post_received', array( __CLASS__, 'received' ), 10, 2 );
	}

	/**
	 * Sender: for each forum, topic or reply a post links to, add the ID that post was copied
	 * from, so a pull back to the original site finds its own posts.
	 *
	 * @param array    $post_data Post payload.
	 * @param \WP_Post $post      Post.
	 * @return array
	 */
	public static function payload( $post_data, $post ) {
		if ( ! self::active() || ! $post instanceof \WP_Post || ! in_array( $post->post_type, self::TYPES, true ) ) {
			return $post_data;
		}
		$origins = array();
		foreach ( self::LINK_META as $key ) {
			$ref    = (int) get_post_meta( $post->ID, $key, true );
			$origin = $ref > 0 ? (int) get_post_meta( $ref, '_rsl_ie_original_post_id', true ) : 0;
			if ( $origin > 0 ) {
				$origins[ $ref ] = $origin;
			}
		}
		if ( $origins ) {
			$post_data[ self::PAYLOAD_KEY ] = $origins;
		}
		return $post_data;
	}

	/** Receiver: remember the origins sent with a post until the batch is re-linked. */
	public static function received( $post_id, $post_data ) {
		$origins = is_array( $post_data ) ? ( $post_data[ self::PAYLOAD_KEY ] ?? null ) : null;
		if ( is_array( $origins ) ) {
			foreach ( $origins as $ref => $origin ) {
				self::$origins[ (int) $ref ] = (int) $origin;
			}
		}
	}

	private static function active() {
		return function_exists( 'bbp_get_forum_post_type' ) && post_type_exists( 'topic' );
	}

	/**
	 * Receiver: re-link the bbPress posts of a sync.
	 *
	 * @param array $source_to_local Source post ID => local post ID of this sync.
	 * @return void
	 */
	public static function fix_received( array $source_to_local ) {
		if ( ! self::active() || ! $source_to_local ) {
			return;
		}
		$source_to_local = array_map( 'intval', $source_to_local );
		$forums          = array();
		$topics          = array();
		foreach ( $source_to_local as $local ) {
			$type = get_post_type( $local );
			if ( ! in_array( $type, self::TYPES, true ) ) {
				continue;
			}
			$forum = self::local( (int) get_post_meta( $local, '_bbp_forum_id', true ), array( 'forum' ), $source_to_local );
			$topic = 'reply' === $type ? self::local( (int) get_post_meta( $local, '_bbp_topic_id', true ), array( 'topic' ), $source_to_local ) : 0;
			if ( 'forum' === $type ) {
				self::set( $local, '_bbp_forum_id', $forum );
				self::parent( $local, $forum );
				$forums[] = $local;
				if ( $forum ) {
					$forums[] = $forum;
				}
			} elseif ( 'topic' === $type ) {
				self::set( $local, '_bbp_forum_id', $forum );
				self::set( $local, '_bbp_topic_id', $local );
				self::parent( $local, $forum );
				$topics[] = $local;
				if ( $forum ) {
					$forums[] = $forum;
				}
			} else {
				self::set( $local, '_bbp_forum_id', $forum );
				self::set( $local, '_bbp_topic_id', $topic );
				$to = (int) get_post_meta( $local, '_bbp_reply_to', true );
				if ( $to ) {
					self::set( $local, '_bbp_reply_to', self::local( $to, array( 'reply' ), $source_to_local ) );
				}
				self::parent( $local, $topic );
				if ( $topic ) {
					$topics[] = $topic;
				}
				if ( $forum ) {
					$forums[] = $forum;
				}
			}
		}
		self::recount( array_unique( $topics ), array_unique( $forums ) );
		self::$origins = array();
	}

	/**
	 * Local ID of a post of the sending site: from this sync, or a copy saved by an earlier one.
	 *
	 * @param int   $source_id       Source post ID.
	 * @param array $types           Expected post types.
	 * @param array $source_to_local Map of this sync.
	 * @return int 0 when there is no local copy.
	 */
	private static function local( $source_id, array $types, array $source_to_local ) {
		if ( $source_id <= 0 ) {
			return 0;
		}
		if ( ! empty( $source_to_local[ $source_id ] ) && in_array( get_post_type( $source_to_local[ $source_id ] ), $types, true ) ) {
			return (int) $source_to_local[ $source_id ];
		}
		// The linked post is a copy of one of ours (pull back to the original site).
		$origin = self::$origins[ $source_id ] ?? 0;
		if ( $origin > 0 && in_array( get_post_type( $origin ), $types, true ) ) {
			return (int) $origin;
		}
		$found = get_posts(
			array(
				'post_type'      => $types,
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery -- Exact source-id lookup.
					'relation' => 'OR',
					array(
						'key'   => '_rsl_ie_original_post_id',
						'value' => $source_id,
					),
					array(
						'key'   => '_rsl_ie_source_id',
						'value' => $source_id,
					),
				),
			)
		);
		return $found ? (int) $found[0] : 0;
	}

	/** Write a link meta; a link to a post with no local copy is cleared rather than left pointing elsewhere. */
	private static function set( $post_id, $key, $value ) {
		update_post_meta( $post_id, $key, (int) $value );
	}

	/** Set post_parent without running the save hooks of the post again. */
	private static function parent( $post_id, $parent ) {
		global $wpdb;
		if ( (int) wp_get_post_parent_id( $post_id ) !== (int) $parent ) {
			$wpdb->update( $wpdb->posts, array( 'post_parent' => (int) $parent ), array( 'ID' => (int) $post_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery
			clean_post_cache( $post_id );
		}
	}

	/** Recount replies, voices and last activity of topics, then the totals of forums. */
	private static function recount( array $topics, array $forums ) {
		foreach ( $topics as $topic ) {
			if ( function_exists( 'bbp_update_topic_reply_count' ) ) {
				bbp_update_topic_reply_count( $topic );
				bbp_update_topic_reply_count_hidden( $topic );
				bbp_update_topic_last_reply_id( $topic );
				bbp_update_topic_last_active_id( $topic );
				bbp_update_topic_last_active_time( $topic );
				bbp_update_topic_voice_count( $topic );
			}
		}
		foreach ( $forums as $forum ) {
			if ( function_exists( 'bbp_update_forum' ) ) {
				bbp_update_forum_topic_count( (int) $forum );
				bbp_update_forum_topic_count_hidden( (int) $forum );
				bbp_update_forum_reply_count( (int) $forum );
				bbp_update_forum( array( 'forum_id' => (int) $forum ) );
			}
		}
	}
}

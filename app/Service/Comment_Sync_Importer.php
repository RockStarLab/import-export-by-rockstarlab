<?php
/**
 * Shared standalone comment import engine.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Standalone_Sync_Policy;
use RockStarLab\ImportExport\Abilities\Sync_Peer_Policy;
defined( 'ABSPATH' ) || exit;
/** One implementation for inbound peer requests and local pull. */
class Comment_Sync_Importer {
	/**
	 * Server-selected metadata and resolver callbacks.
	 *
	 * @var array
	 */
	private $helpers;
	/**
	 * Supply shared field integrations.
	 *
	 * @param array $helpers Trusted callbacks.
	 */
	public function __construct( array $helpers ) {
		$this->helpers = $helpers; }
	/**
	 * Resolve and authorize every destination before modifying any record.
	 *
	 * @param array $comments Payload.
	 * @param array $mapping Source post to destination post IDs.
	 * @param int   $target Optional common destination post.
	 * @param bool  $allow_missing_parents Allow selected-only UI pulls to detach omitted parents.
	 * @return array|\WP_Error
	 */
	public function prepare( array $comments, array $mapping, $target, $allow_missing_parents = false ) {
		$comments = Sync_Graph::order( $comments, 'comment_ID', 'comment_parent' );
		if ( is_wp_error( $comments ) ) {
			return $comments; }
		$targets = [];
		foreach ( $comments as &$comment ) {
			foreach ( [ 'meta', 'acf', 'post' ] as $key ) {
				if ( isset( $comment[ $key ] ) && ! is_array( $comment[ $key ] ) ) {
					return Standalone_Sync_Policy::denied(); }
			}

			$source_post = (int) ( $comment['comment_post_ID'] ?? $comment['post']['ID'] ?? 0 );
			if ( isset( $comment['post']['ID'] ) && $source_post !== (int) $comment['post']['ID'] ) {
				return Standalone_Sync_Policy::denied(); }
			$post_id = $target ? (int) $target : ( isset( $mapping[ $source_post ] ) ? (int) $mapping[ $source_post ] : (int) ( $this->helpers['resolve'] )( $comment, $mapping ) );
			$access  = Sync_Peer_Policy::comment_destination( $comment, $post_id, (bool) $target || isset( $mapping[ $source_post ] ) );
			if ( is_wp_error( $access ) ) {
				return $access; }
			$existing = $this->existing( $post_id, $comment['comment_ID'] );
			if ( $existing && ( ! current_user_can( 'edit_comment', $existing ) || ! Standalone_Sync_Policy::comment_type( get_comment( $existing )->comment_type, $comment['comment_type'] ?? 'comment' ) ) ) {
				return Standalone_Sync_Policy::denied(); }
			$comment['_sync_target']           = $post_id;
			$comment['_sync_existing']         = $existing;
			$targets[ $comment['comment_ID'] ] = $post_id;
		}
		unset( $comment );
		foreach ( $comments as &$comment ) {
			$parent = (int) ( $comment['comment_parent'] ?? 0 );
			if ( isset( $targets[ $parent ] ) && $targets[ $parent ] !== $comment['_sync_target'] ) {
				return Standalone_Sync_Policy::denied(); }
			$comment['_sync_parent'] = 0;
			if ( $parent && ! isset( $targets[ $parent ] ) ) {
				$existing_parent = $this->existing( $comment['_sync_target'], $parent );
				if ( ! $existing_parent && $allow_missing_parents ) {
					// A selective pull imports only the chosen records, not their ancestors.
					$comment['comment_parent'] = 0;
					continue;
				}
				$record          = $existing_parent ? get_comment( $existing_parent ) : null;
				if ( ! $record || ! current_user_can( 'edit_comment', $existing_parent ) || ! Standalone_Sync_Policy::comment_type( $record->comment_type, $comment['comment_type'] ?? 'comment' ) || $existing_parent === $comment['_sync_existing'] ) {
					return Standalone_Sync_Policy::denied();
				}
				$comment['_sync_parent'] = $existing_parent;
			}
		}
		unset( $comment );
		return $comments;
	}
	/**
	 * Import a preflighted set, preserving hierarchy and stable local IDs.
	 *
	 * @param array $comments Source comments.
	 * @param array $mapping Source post to local post mapping.
	 * @param int   $target Optional common local post.
	 * @param mixed $user_mapping Optional source-to-local account mapping.
	 * @param bool  $allow_missing_parents Allow selected-only UI pulls to detach omitted parents.
	 * @return array|\WP_Error
	 */
	public function import( array $comments, array $mapping, $target, $user_mapping = null, $allow_missing_parents = false ) {
		$users = Sync_Comment_Users::prepare( $comments, $user_mapping );
		if ( is_wp_error( $users ) ) {
			return $users; }
		$comments = $this->prepare( $comments, $mapping, $target, $allow_missing_parents );
		if ( is_wp_error( $comments ) ) {
			return $comments; }
		$result = [
			'created' => 0,
			'updated' => 0,
			'failed'  => 0,
			'errors'  => [],
			'mapping' => [],
		];
		$touched = [];
		foreach ( $comments as $comment ) {
			$source        = (int) $comment['comment_ID'];
			$post          = $comment['_sync_target'];
			$existing      = $comment['_sync_existing'];
			$parent        = (int) ( $comment['comment_parent'] ?? 0 );
			$source_parent = $parent;
			$parent        = $parent ? ( $result['mapping'][ $parent ] ?? $comment['_sync_parent'] ) : 0;
			if ( $source_parent && ! $parent ) {
				// A preceding parent write failed; never turn its reply into a root.
				return Migration_Sync_Maps::error();
			}
			$data = [
				'comment_post_ID' => $post,
				'comment_parent'  => $parent,
				'user_id'         => $users[ $source ] ?? 0,
			];
			foreach ( [ 'comment_author', 'comment_author_IP', 'comment_agent', 'comment_date', 'comment_date_gmt', 'comment_approved' ] as $key ) {
				if ( isset( $comment[ $key ] ) ) {
					$data[ $key ] = sanitize_text_field( (string) $comment[ $key ] ); }
			}
			$data['comment_author_email'] = sanitize_email( (string) ( $comment['comment_author_email'] ?? '' ) );
			$data['comment_author_url']   = esc_url_raw( (string) ( $comment['comment_author_url'] ?? '' ) );
			$data['comment_type']         = sanitize_key( (string) ( $comment['comment_type'] ?? '' ) );
			$data['comment_content']      = wp_kses_post( ( $this->helpers['media'] )( (string) ( $comment['comment_content'] ?? '' ), 0 ) );
			$data['comment_karma']        = (int) ( $comment['comment_karma'] ?? 0 );
			if ( $existing ) {
				$data['comment_ID'] = $existing;
				// Keep the same safe HTML policy on updates as on initial imports.
				// Core otherwise applies the smaller public-comment allowlist.
				$sync_html = static function ( $tags, $context ) {
					return 'pre_comment_content' === $context ? wp_kses_allowed_html( 'post' ) : $tags;
				};
				add_filter( 'wp_kses_allowed_html', $sync_html, 10, 2 );
				try {
					$saved = \RockStarLab\ImportExport\Helper\Comment_Update::update( wp_slash( $data ), true );
				} finally {
					remove_filter( 'wp_kses_allowed_html', $sync_html, 10 );
				}
				$id                 = is_wp_error( $saved ) ? $saved : $existing; } else {
				$id = wp_insert_comment( wp_slash( $data ) ); }
				if ( ! $id || is_wp_error( $id ) ) {
					++$result['failed'];
					$result['errors'][] = 'Comment could not be saved.';
					continue; }
				$saved_comment = get_comment( $id );
				if ( ! $saved_comment || (int) $saved_comment->user_id !== $data['user_id'] ) {
					return Migration_Sync_Maps::error(); }
				++$result[ $existing ? 'updated' : 'created' ];
				$result['mapping'][ $source ] = (int) $id;
				update_comment_meta( $id, '_rsl_ie_original_comment_id', $source );
				( $this->helpers['acf'] )( $id, $comment );
				foreach ( $comment['meta'] ?? [] as $key => $value ) {
					if ( '_rsl_ie_original_comment_id' === $key || ( $this->helpers['acf_meta'] )( $key, $comment ) ) {
						continue; }
					update_comment_meta( $id, sanitize_key( $key ), ( $this->helpers['media'] )( $value, 0 ) );
				}
				$touched[ (int) $post ] = true;
		}
		// WooCommerce counts reviews when the comment is saved, before its rating meta
		// exists; recount so the product shows the right average rating and count.
		if ( class_exists( '\WC_Comments' ) ) {
			foreach ( array_keys( $touched ) as $product_id ) {
				if ( 'product' === get_post_type( $product_id ) ) {
					\WC_Comments::clear_transients( $product_id );
				}
			}
		}
		return $result;
	}
	/**
	 * Scope source identity to the receiving post.
	 *
	 * @param int $post Destination post.
	 * @param int $source Source comment.
	 * @return int
	 */
	private function existing( $post, $source ) {
		$ids = get_comments(
			[
				'post_id'    => $post,
				'status'     => 'any',
				'number'     => 1,
				'fields'     => 'ids',
				'meta_key'   => '_rsl_ie_original_comment_id',
				'meta_value' => (int) $source,
			]
		); // phpcs:ignore WordPress.DB.SlowDBQuery -- Scoped source identity lookup.
		return $ids ? (int) $ids[0] : 0;
	}
}

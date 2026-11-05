<?php
/**
 * Verified media-valued ACF fields within the selected attachment graph.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

/** Reuse file evidence and raw field finalization from the post media protocol. */
class Sync_ACF_Media_Graph {
	/**
	 * Validate nested records without accepting alternate source identities.
	 *
	 * @param array $posts Frozen graph snapshots.
	 * @return array|\WP_Error
	 */
	private static function records( array $posts ) {
		$records = [];
		foreach ( $posts as $post ) {
			if ( 'attachment' !== $post['post_type'] ) {
				if ( isset( $post['acf_attachment_media'] ) ) {
					return Migration_Sync_Maps::error();
				}
				continue;
			}
			$record = $post['acf_attachment_media'] ?? null;
			if ( ! is_array( $record ) || ( $record['ID'] ?? null ) !== $post['ID'] || 'attachment' !== ( $record['post_type'] ?? null ) || ! is_array( $record['meta'] ?? null ) ) {
				return Migration_Sync_Maps::error();
			}
			$records[] = $record;
		}
		return $records;
	}

	/**
	 * Require every referenced media ID to belong to a selected media step.
	 *
	 * @param array $posts Graph snapshots.
	 * @param array $selected Full source ID to type selection.
	 * @return true|\WP_Error
	 */
	public static function check( array $posts, array $selected ) {
		$records = self::records( $posts );
		if ( is_wp_error( $records ) || ! $records ) {
			return is_wp_error( $records ) ? $records : true;
		}
		$prepared = Sync_ACF_Media::prepare( $records, null, true );
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}
		foreach ( $records as $record ) {
			foreach ( $record['acf_media_values'] as $ids ) {
				foreach ( $ids as $id ) {
					if ( 'attachment' !== ( $selected[ $id ] ?? null ) ) {
						return Migration_Sync_Maps::error();
					}
				}
			}
		}
		return true;
	}

	/**
	 * Prepare verified assignments before any graph metadata writes.
	 *
	 * @param array $posts Frozen graph snapshots.
	 * @param array $maps Confirmed typed maps.
	 * @return array|\WP_Error
	 */
	public static function prepare( array $posts, array $maps ) {
		$records = self::records( $posts );
		if ( is_wp_error( $records ) || ! $records ) {
			return $records;
		}
		$media = [];
		foreach ( $maps as $key => $target ) {
			if ( 0 === strpos( $key, 'post_type:attachment:' ) ) {
				$media[ substr( $key, 21 ) ] = $target;
			}
		}
		return Sync_ACF_Media::prepare( $records, $media, true );
	}
}

<?php
/**
 * File evidence for attachment references in deferred ACF post graphs.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;

defined( 'ABSPATH' ) || exit;

/** Resolve attachment identities only through selected, confirmed media steps. */
class Sync_ACF_Attachments {
	/**
	 * Detect attachment references when negotiating the graph peer extension.
	 *
	 * @param array $posts Reference snapshots.
	 * @return bool
	 */
	public static function required( array $posts ) {
		foreach ( $posts as $post ) {
			if ( in_array( 'attachment', (array) ( $post['acf_post_types'] ?? [] ), true ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Bind a source snapshot to bounded, readable, current uploads files.
	 *
	 * @param array $posts Authorized ACF reference snapshots.
	 * @return array|\WP_Error
	 */
	public static function snapshot( array $posts ) {
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		$media  = new Media_Library_Sync_Service();
		$proofs = [];
		$bytes  = 0;
		foreach ( $posts as &$post ) {
			foreach ( $post['acf_post_types'] as $id => $type ) {
				if ( 'attachment' !== $type ) {
					continue;
				}
				if ( ! isset( $proofs[ $id ] ) ) {
					$proof = $media->reference_proof( (int) $id, true );
					if ( is_wp_error( $proof ) ) {
						return $proof;
					}
					$proofs[ $id ] = $proof;
					$bytes        += $proof['size'];
					if ( count( $proofs ) > 50 ) {
						return Migration_Sync_Maps::error();
					}
				}
				$post['acf_attachment_proofs'][ $id ] = $proofs[ $id ];
			}
		}
		unset( $post );
		return $posts;
	}

	/**
	 * Validate frozen evidence and, when available, actual confirmed targets.
	 *
	 * @param array      $posts Reference snapshots.
	 * @param array|null $maps Typed source-to-destination identities; null before transfer.
	 * @param bool       $chunked Server-selected staged transport; permit large files and retained originals.
	 * @return true|\WP_Error
	 */
	public static function verify( array $posts, $maps = null, $chunked = true ) {
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		if ( count( $posts ) > 50 || ( null !== $maps && ! is_array( $maps ) ) ) {
			return Migration_Sync_Maps::error();
		}
		$media    = new Media_Library_Sync_Service();
		$proofs   = [];
		$verified = [];
		$bytes    = 0;
		foreach ( $posts as $post ) {
			$evidence = $post['acf_attachment_proofs'] ?? [];
			if ( ! is_array( $evidence ) || count( $evidence ) > 50 ) {
				return Migration_Sync_Maps::error();
			}
			$used = 0;
			foreach ( (array) ( $post['acf_post_types'] ?? [] ) as $id => $type ) {
				if ( 'attachment' !== $type ) {
					continue;
				}
				++$used;
				$proof = $evidence[ $id ] ?? null;
				if ( ! is_array( $proof ) || array_keys( $proof ) !== [ 'sha256', 'size', 'mime' ] || ! is_string( $proof['sha256'] ) || ! preg_match( '/^[a-f0-9]{64}$/D', $proof['sha256'] ) || ! is_int( $proof['size'] ) || $proof['size'] < 1 || ( ! $chunked && $proof['size'] > Media_Library_Sync_Service::FILE_LIMIT ) || ! is_string( $proof['mime'] ) || ! preg_match( '#^[a-z0-9.+-]+/[a-z0-9.+-]+$#D', $proof['mime'] ) || ( isset( $proofs[ $id ] ) && $proofs[ $id ] !== $proof ) ) {
					return Migration_Sync_Maps::error();
				}
				if ( ! isset( $proofs[ $id ] ) ) {
					$proofs[ $id ] = $proof;
					$bytes        += $proof['size'];
				}
				if ( count( $proofs ) > 50 || ( ! $chunked && $bytes > Media_Library_Sync_Service::BATCH_LIMIT ) ) {
					return Migration_Sync_Maps::error();
				}
				if ( null !== $maps ) {
					$target = $maps[ 'post_type:attachment:' . $id ] ?? 0;
					if ( ! is_int( $target ) || $target < 1 ) {
						return Migration_Sync_Maps::error();
					}
					$actual = $verified[ $target ] ?? $media->reference_proof( $target, $chunked );
					if ( $chunked && ! is_wp_error( $actual ) && $actual !== $proof ) {
						$actual = $media->reference_proof( $target, true, true );
					}
					if ( is_wp_error( $actual ) || $actual !== $proof ) {
						return Migration_Sync_Maps::error();
					}
					$verified[ $target ] = $actual;
				}
			}
			if ( count( $evidence ) !== $used ) {
				return Migration_Sync_Maps::error();
			}
		}
		return true;
	}
}

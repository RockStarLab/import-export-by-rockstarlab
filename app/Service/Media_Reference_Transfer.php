<?php
/** Reference-only media envelopes for content, menu and ACF transfers. */
namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

class Media_Reference_Transfer {
	private static $context = [];

	/** The connection comes only from an authorized outbound service, never peer input. */
	public static function run( $direction, array $site, callable $callback ) {
		self::$context[] = [ 'direction' => $direction, 'site' => $site ];
		try {
			return $callback();
		} finally {
			array_pop( self::$context );
		}
	}

	public static function export( array $proofs ) {
		$files = [];
		$context = end( self::$context );
		$media = new Media_Library_Sync_Service();
		foreach ( $proofs as $id => $proof ) {
			if ( $media->reference_proof( (int) $id, true ) !== $proof ) {
				return Migration_Sync_Maps::error();
			}
			$file = [ 'attachment_id' => (int) $id, 'media_reference_version' => 1, 'proof' => $proof, 'acf' => [] ];
			if ( $context && 'push' === $context['direction'] ) {
				$result = ( new Media_Chunk_Transfer() )->execute( 'push', $context['site'], [ 'media_ids' => [ (int) $id ] ] );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				if ( $result['failed'] ) {
					return Migration_Sync_Maps::error();
				}
				$file['target_attachment_id'] = $result['items'][0]['attachment_id'];
			}
			$files[] = $file;
		}
		return $files;
	}

	/** Verify every receipt against current destination bytes before assigning any references. */
	public static function receive( array $files, array $proofs ) {
		$check = self::check( $files, $proofs );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		$context = end( self::$context );
		$mapping = [];
		$media = new Media_Library_Sync_Service();
		foreach ( $files as $file ) {
			$id = $file['attachment_id'];
			if ( $context && 'pull' === $context['direction'] ) {
				// A peer cannot choose a local target ID during Pull.
				$result = ( new Media_Chunk_Transfer() )->execute( 'pull', $context['site'], [ 'media_ids' => [ $id ] ] );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				if ( $result['failed'] ) {
					return Migration_Sync_Maps::error();
				}
				$target = $result['items'][0]['attachment_id'];
			} else {
				$target = $file['target_attachment_id'] ?? 0;
			}
			if ( ! is_int( $target ) || $target < 1 || $media->reference_proof( $target, true ) !== $proofs[ $id ] ) {
				return Migration_Sync_Maps::error();
			}
			$mapping[ $id ] = $target;
		}
		return $mapping;
	}

	public static function check( array $files, array $proofs ) {
		if ( count( $files ) !== count( $proofs ) ) {
			return Migration_Sync_Maps::error();
		}
		$seen = [];
		foreach ( $files as $file ) {
			$id = $file['attachment_id'] ?? null;
			if ( ! is_int( $id ) || isset( $seen[ $id ] ) || ! isset( $proofs[ $id ] ) || 1 !== ( $file['media_reference_version'] ?? null ) || ( $file['proof'] ?? null ) !== $proofs[ $id ] || ( $file['acf'] ?? null ) !== [] || array_diff( array_keys( $file ), [ 'attachment_id', 'media_reference_version', 'proof', 'acf', 'target_attachment_id', 'url' ] ) ) {
				return Migration_Sync_Maps::error();
			}
			$seen[ $id ] = true;
		}
		return true;
	}
}

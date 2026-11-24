<?php
/** Shared synchronous adapter for the resumable media protocol. */
namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

/** Keep the direct API result contract while bounding each network request. */
class Media_Chunk_Transfer {
	public function execute( $direction, array $site, array $input, array $metadata = [] ) {
		$media = new Media_Library_Sync_Service();
		$access = $media->preflight( $direction, [ 'site_id' => (int) $site['id'] ] + $input, true );
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		$fingerprint = ( new Sync_Job_Service() )->fingerprint( $site );
		$driver = 'push' === $direction ? new Media_Chunk_Push() : new Media_Chunk_Pull();
		$result = [ 'created' => 0, 'skipped' => 0, 'failed' => 0, 'items' => [] ];
		foreach ( $input['media_ids'] as $id ) {
			$request = 'direct-media-' . wp_generate_uuid4();
			$key = 'rsl_ie_' . $request;
			$state = [];
			$selection = array_replace( $input, [ 'media_ids' => [ $id ] ] );
			$deadline = microtime( true ) + 180;
			do {
				$next = $driver->advance( (int) $site['id'], $fingerprint, $request, $selection, $state );
				if ( is_wp_error( $next ) && 'rsl_ie_media_library_connection_busy' === $next->get_error_code() && microtime( true ) + 2 < $deadline ) {
					// Another transfer holds the other site's media lock: retry the same step.
					sleep( 1 );
					continue;
				}
				if ( is_wp_error( $next ) ) {
					$next->add_data( [ 'status' => $next->get_error_data()['status'] ?? 502, 'result' => $result, 'source_id' => $id ] );
					return $next;
				}
				$state = $next;
				if ( 'begin' === $state['phase'] ) {
					$extra = array_intersect_key( $metadata, array_flip( [ 'force_unique', 'source_origin_attachment_id' ] ) );
					if ( 'pull' === $direction ) {
						// This site receives: keep the folder of the file on the other site (…/uploads/2018/03/).
						$subdir = \RockStarLab\ImportExport\Helper\Upload_Subdir::clean( $metadata['upload_subdir'] ?? \RockStarLab\ImportExport\Helper\Upload_Subdir::of_url( $metadata['url'] ?? '' ) );
						if ( '' !== $subdir ) {
							$extra['upload_subdir'] = $subdir;
						}
					}
					$state['metadata'] = array_replace( $state['metadata'], $extra );
				}
				set_transient( $key, $state, DAY_IN_SECONDS );
				if ( 'done' === $state['phase'] ) {
					foreach ( [ 'created', 'skipped', 'failed' ] as $counter ) {
						$result[ $counter ] += $state['result'][ $counter ];
					}
					$result['items'][] = $state['result']['items'][0];
					for ( $i = 0; $i < 2 && empty( $state['cleaned'] ); ++$i ) {
						$next = $driver->cleanup( (int) $site['id'], $fingerprint, $state );
						if ( is_wp_error( $next ) ) {
							break;
						}
						$state = $next;
						set_transient( $key, $state, DAY_IN_SECONDS );
					}
					if ( ! empty( $state['cleaned'] ) ) {
						delete_transient( $key );
					}
					break;
				}
			} while ( microtime( true ) < $deadline );
			if ( 'done' !== $state['phase'] ) {
				return new \WP_Error( 'rsl_ie_media_transfer_timeout', __( 'Media transfer timed out. Inspect the destination before repeating.', 'import-export-by-rockstarlab' ), [ 'status' => 504, 'result' => $result, 'source_id' => $id ] );
			}
		}
		return $result;
	}
}

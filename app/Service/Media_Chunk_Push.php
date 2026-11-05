<?php
/** Resumable single-file push steps for a durable caller. */
namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Media_Library_Catalog;

defined( 'ABSPATH' ) || exit;

/** The caller must persist returned state before scheduling another step. */
class Media_Chunk_Push {
	/** Execute one step. State and request identity must come from the owning job. */
	public function advance( $site_id, $fingerprint, $request_id, array $input, array $state = [] ) {
		$media  = new Media_Library_Sync_Service();
		$access = $media->preflight( 'push', [ 'site_id' => $site_id ] + $input, true );
		if ( is_wp_error( $access ) ) {
			return $access; }
		if ( ! is_string( $request_id ) || ! preg_match( '/^[A-Za-z0-9_-]{16,128}$/D', $request_id ) || ! is_array( $input['media_ids'] ?? null ) || 1 !== count( $input['media_ids'] ) || ! is_int( $input['media_ids'][0] ?? null ) ) {
			return $this->error(); }
		$id = $input['media_ids'][0];
		if ( 'attachment' !== get_post_type( $id ) || ! current_user_can( 'read_post', $id ) || ! current_user_can( 'edit_post', $id ) ) {
			return $this->error(); }
		$graph = Sync_ACF_Media_Fields::chunk_request( $id );
		if ( is_wp_error( $graph ) ) {
			return $graph; }
		$input   += $graph;
		$identity = hash( 'sha256', wp_json_encode( [ $site_id, $fingerprint, $request_id, $input ] ) );
		if ( $state && ( $state['identity'] ?? null ) !== $identity ) {
			return $this->error(); }
		$access = $state['capabilities'] ?? $media->negotiate_chunks( $site_id, 'push', $fingerprint );
		if ( is_wp_error( $access ) ) { return $access; }
		$state['capabilities'] = $access;
		$state += [
			'identity'   => $identity,
			'request_id' => $request_id,
			'phase'      => 'snapshot',
		];
		$call   = static function ( $payload ) use ( $media, $site_id, $fingerprint ) {
			return $media->chunk_request( $site_id, 'push', $fingerprint, $payload );
		};
		switch ( $state['phase'] ) {
			case 'snapshot':
				$snapshot = isset( $state['snapshot'] ) ? $media->advance_source_snapshot( $state['snapshot']['snapshot_id'] ) : $media->source_snapshot( $id, $request_id, 'local', true );
				if ( is_wp_error( $snapshot ) ) {
					return $snapshot; }
				$state['snapshot'] = $snapshot;
				if ( $snapshot['ready'] ) {
					$state['phase'] = 'metadata'; }
				break;
			case 'metadata':
				$payload = $media->prepare_snapshot_media( $state['snapshot']['snapshot_id'], $input );
				if ( is_wp_error( $payload ) ) {
					return $payload; }
				$item                         = $payload['media'][0];
				$item['source_attachment_id'] = $item['attachment_id'];
				unset( $item['attachment_id'], $item['file_name'], $item['file_size'], $item['file_sha256'], $payload['media'] );
				$state['metadata'] = $item;
				$state['envelope'] = $payload;
				$state['phase']    = 'begin';
				break;
			case 'begin':
				$s = $state['snapshot'];
				$initial = [];
				if ( ! empty( $access['begin_reuse'] ) ) {
					$file = Media_Chunk_Store::resolve( $s['snapshot_id'], 'source:local' );
					if ( is_wp_error( $file ) ) { return $file; }
					$initial['file_hash'] = md5_file( $file['path'] );
				} elseif ( ! empty( $access['begin_data'] ) ) {
					$slice = Media_Chunk_Store::read_snapshot( $s['snapshot_id'], 0 );
					if ( is_wp_error( $slice ) ) { return $slice; }
					$initial['data'] = $slice['data'];
				}
				$result = $call(
					[
						'action'     => 'begin',
						'request_id' => $request_id,
						'file_name'  => $s['file_name'],
						'size'       => $s['size'],
						'sha256'     => $s['sha256'],
					] + $initial
				);
				if ( is_wp_error( $result ) ) {
					return $result; }
				if ( ! $this->progress( $result, $s['size'] ) ) {
					return $this->error(); }
				$state['transfer'] = $result;
				$state['phase']    = $result['ready'] ? 'finalize' : 'append';
				break;
			case 'append':
				$t     = $state['transfer'];
				$slice = Media_Chunk_Store::read_snapshot( $state['snapshot']['snapshot_id'], $t['offset'] );
				if ( is_wp_error( $slice ) ) {
					return $slice; }
				$result = $call(
					[
						'action'      => 'append',
						'transfer_id' => $t['transfer_id'],
						'offset'      => $t['offset'],
						'data'        => $slice['data'],
					]
				);
				if ( is_wp_error( $result ) ) {
					return $result; }
				if ( ! $this->progress( $result, $state['snapshot']['size'] ) || $result['transfer_id'] !== $t['transfer_id'] || $result['offset'] !== $slice['next_offset'] ) {
					return $this->error(); }
				$state['transfer'] = $result;
				$state['phase']    = $result['ready'] ? 'finalize' : 'append';
				break;
			case 'finalize':
				$result = $call(
					[
						'action'       => 'finalize',
						'transfer_id'  => $state['transfer']['transfer_id'],
						'metadata'     => $state['metadata'],
						'envelope'     => $state['envelope'],
						'defer_fields' => false,
					] + ( ! empty( $access['bounded_metadata'] ) ? [ 'bounded_metadata' => true ] : [] )
				);
				if ( is_wp_error( $result ) ) {
					return $result; }
				if ( ! empty( $access['bounded_metadata'] ) && [ 'pending' => true ] === $result ) {
					break;
				}
				foreach ( [
					'acf_media_fields_version'     => 'acf_media_fields_version',
					'acf_attachment_media_version' => 'acf_attachment_media_version',
					'media_authors'                => 'media_authors_version',
					'acf_attachment_users'         => 'acf_attachment_users_version',
					'acf_attachment_terms'         => 'acf_attachment_terms_version',
				] as $key => $version ) {
					if ( isset( $state['envelope'][ $key ] ) && 1 !== ( $result[ $version ] ?? null ) ) {
						return $this->error(); }
				}
				$result = array_intersect_key( $result, array_flip( [ 'created', 'skipped', 'failed', 'items' ] ) );
				if ( is_wp_error( rest_validate_value_from_schema( $result, Media_Library_Catalog::definitions()['sync-media-push']['output'] ) ) || 1 !== count( $result['items'] ) || $result['items'][0]['source_id'] !== $id || 1 !== $result['created'] + $result['skipped'] + $result['failed'] ) {
					return $this->error(); }
				$item = $result['items'][0];
				if ( 1 !== $result[ $item['status'] ] || ( 'failed' === $item['status'] ? '' === $item['error_code'] : ( $item['attachment_id'] < 1 || '' !== $item['error_code'] ) ) ) {
					return $this->error(); }
				$state['result'] = $result;
				$state['phase']  = 'done';
				break;
			case 'done':
				break;
			default:
				return $this->error();
		}
		return $state;
	}

	/** Clean one resource after the caller has durably stored the completed result. */
	public function cleanup( $site_id, $fingerprint, array $state, $abandon = false ) {
		if ( ! $abandon && ( 'done' !== ( $state['phase'] ?? null ) || ! isset( $state['result'] ) ) ) {
			return $this->error(); }
		if ( $abandon && 'finalize' === ( $state['phase'] ?? '' ) ) {
			return $this->error(); }
		if ( $abandon && 'begin' === ( $state['phase'] ?? '' ) && ! isset( $state['transfer'] ) ) {
			$s      = $state['snapshot'];
			$result = ( new Media_Library_Sync_Service() )->chunk_request(
				$site_id,
				'push',
				$fingerprint,
				[
					'action'     => 'begin',
					'request_id' => $state['request_id'],
					'file_name'  => $s['file_name'],
					'size'       => $s['size'],
					'sha256'     => $s['sha256'],
				]
			);
			if ( is_wp_error( $result ) ) {
				return $result; }
			if ( ! $this->progress( $result, $s['size'] ) ) {
				return $this->error(); }
			$state['transfer'] = $result;
			$state['phase']    = 'abandoned';
			return $state;
		}
		if ( isset( $state['transfer'] ) ) {
			$result = ( new Media_Library_Sync_Service() )->chunk_request(
				$site_id,
				'push',
				$fingerprint,
				[
					'action'      => 'discard',
					'transfer_id' => $state['transfer']['transfer_id'],
				]
			);
			if ( is_wp_error( $result ) ) {
				return $result; }
			unset( $state['transfer'] );
		} elseif ( isset( $state['snapshot'] ) ) {
			$result = Media_Chunk_Store::discard( $state['snapshot']['snapshot_id'], 'source:local' );
			if ( is_wp_error( $result ) ) {
				return $result; }
			unset( $state['snapshot'], $state['metadata'], $state['envelope'] );
		}
		$state['cleaned'] = ! isset( $state['transfer'] ) && ! isset( $state['snapshot'] );
		return $state;
	}

	private function progress( array $result, $size ) {
		return File_Store::valid_id( $result['transfer_id'] ?? null ) && ( $result['size'] ?? null ) === $size && is_int( $result['offset'] ?? null ) && $result['offset'] >= 0 && $result['offset'] <= $size && ( $result['ready'] ?? null ) === ( $result['offset'] === $size ) && ( $result['offset'] === $size || 0 === $result['offset'] % Media_Chunk_Store::CHUNK_BYTES );
	}

	private function error() {
		return new \WP_Error( 'rsl_ie_media_chunk_checkpoint', 'The media transfer checkpoint or peer response is invalid.', [ 'status' => 409 ] );
	}
}

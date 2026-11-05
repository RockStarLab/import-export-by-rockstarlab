<?php
/** Resumable single-file pull steps for a durable caller. */
namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Media_Library_Catalog;

defined( 'ABSPATH' ) || exit;

/** The caller must persist returned state before scheduling another step. */
class Media_Chunk_Pull {
	/** Execute one step. State and request identity must come from the owning job. */
	public function advance( $site_id, $fingerprint, $request_id, array $input, array $state = [] ) {
		$media  = new Media_Library_Sync_Service();
		$access = $media->preflight( 'pull', [ 'site_id' => $site_id ] + $input, true );
		if ( is_wp_error( $access ) ) {
			return $access; }
		if ( ! is_string( $request_id ) || ! preg_match( '/^[A-Za-z0-9_-]{16,128}$/D', $request_id ) || ! is_array( $input['media_ids'] ?? null ) || 1 !== count( $input['media_ids'] ) || ! is_int( $input['media_ids'][0] ?? null ) ) {
			return $this->error(); }
		$id    = $input['media_ids'][0];
		$graph = Sync_ACF_Media_Fields::chunk_request( $id );
		if ( is_wp_error( $graph ) ) {
			return $graph; }
		$input   += $graph;
		$identity = hash( 'sha256', wp_json_encode( [ $site_id, $fingerprint, $request_id, $input ] ) );
		if ( $state && ( $state['identity'] ?? null ) !== $identity ) {
			return $this->error(); }
		$access = $state['capabilities'] ?? $media->negotiate_chunks( $site_id, 'pull', $fingerprint );
		if ( is_wp_error( $access ) ) { return $access; }
		$state['capabilities'] = $access;
		$state += [
			'identity'   => $identity,
			'request_id' => $request_id,
			'phase'      => 'snapshot',
		];
		$call   = static function ( $payload ) use ( $media, $site_id, $fingerprint ) {
			return $media->chunk_request( $site_id, 'pull', $fingerprint, $payload );
		};
		switch ( $state['phase'] ) {
			case 'snapshot':
				$snapshot = isset( $state['snapshot'] ) ? $call(
					[
						'action'      => 'advance-snapshot',
						'snapshot_id' => $state['snapshot']['snapshot_id'],
					]
				) : $call(
					[
						'action'        => 'snapshot',
						'attachment_id' => $id,
						'request_id'    => $request_id,
						'bounded'       => true,
					]
				);
				if ( is_wp_error( $snapshot ) ) {
					return $snapshot; }
				if ( ! $this->snapshot_valid( $snapshot, $id, $state['snapshot'] ?? null ) ) {
					return $this->error(); }
				$state['snapshot'] = $snapshot;
				if ( $snapshot['ready'] ) {
					$state['phase'] = 'metadata'; }
				break;
			case 'metadata':
				$payload = $call(
					[
						'action'      => 'prepare-snapshot',
						'snapshot_id' => $state['snapshot']['snapshot_id'],
						'input'       => $input,
					]
				);
				if ( is_wp_error( $payload ) ) {
					return $payload; }
				if ( ! $this->metadata_valid( $payload, $state['snapshot'], $input ) ) {
					return $this->error(); }
				$item                         = $payload['media'][0];
				$item['source_attachment_id'] = $item['attachment_id'];
				unset( $item['attachment_id'], $item['file_name'], $item['file_size'], $item['file_sha256'], $payload['media'] );
				$state['metadata'] = $item;
				$state['envelope'] = $payload;
				$state['phase']    = 'begin';
				break;
			case 'begin':
				$s      = $state['snapshot'];
				$result = Media_Chunk_Store::begin( $s['file_name'], $s['size'], $s['sha256'], 'local', $request_id );
				if ( is_wp_error( $result ) ) {
					return $result; }
				if ( ! $this->progress( $result, $s['size'] ) ) {
					return $this->error(); }
				$state['transfer'] = $result;
				$state['phase']    = $result['ready'] ? 'finalize' : 'append';
				break;
			case 'append':
				$t     = $state['transfer'];
				$slice = $call(
					[
						'action'      => 'read-snapshot',
						'snapshot_id' => $state['snapshot']['snapshot_id'],
						'offset'      => $t['offset'],
					]
				);
				if ( is_wp_error( $slice ) ) {
					return $slice; }
				if ( ! is_string( $slice['data'] ?? null ) || strlen( $slice['data'] ) > 4 * ceil( Media_Chunk_Store::CHUNK_BYTES / 3 ) || ( $slice['offset'] ?? null ) !== $t['offset'] ) {
					return $this->error(); }
				$bytes  = base64_decode( $slice['data'], true );
				$length = min( Media_Chunk_Store::CHUNK_BYTES, $state['snapshot']['size'] - $t['offset'] );
				if ( false === $bytes || strlen( $bytes ) !== $length || ( $slice['next_offset'] ?? null ) !== $t['offset'] + $length ) {
					return $this->error(); }
				$result = Media_Chunk_Store::append( $t['transfer_id'], $t['offset'], $bytes );
				if ( is_wp_error( $result ) ) {
					return $result; }
				if ( ! $this->progress( $result, $state['snapshot']['size'] ) || $result['transfer_id'] !== $t['transfer_id'] || $result['offset'] !== $slice['next_offset'] ) {
					return $this->error(); }
				$state['transfer'] = $result;
				$state['phase']    = $result['ready'] ? 'finalize' : 'append';
				break;
			case 'finalize':
				$result = $media->receive_staged_file( $state['transfer']['transfer_id'], $state['metadata'], 'local', false, $state['envelope'], true );
				if ( is_wp_error( $result ) ) {
					return $result; }
				if ( [ 'pending' => true ] === $result ) {
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
				if ( is_wp_error( rest_validate_value_from_schema( $result, Media_Library_Catalog::definitions()['sync-media-pull']['output'] ) ) || 1 !== count( $result['items'] ) || $result['items'][0]['source_id'] !== $id || 1 !== $result['created'] + $result['skipped'] + $result['failed'] ) {
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
			$result = Media_Chunk_Store::begin( $s['file_name'], $s['size'], $s['sha256'], 'local', $state['request_id'] );
			if ( is_wp_error( $result ) ) {
				return $result; }
			if ( ! $this->progress( $result, $s['size'] ) ) {
				return $this->error(); }
			$state['transfer'] = $result;
			$state['phase']    = 'abandoned';
			return $state;
		}
		if ( isset( $state['transfer'] ) ) {
			$result = Media_Chunk_Store::discard( $state['transfer']['transfer_id'] );
			if ( is_wp_error( $result ) ) {
				return $result; }
			unset( $state['transfer'] );
		} elseif ( isset( $state['snapshot'] ) ) {
			$result = ( new Media_Library_Sync_Service() )->chunk_request(
				$site_id,
				'pull',
				$fingerprint,
				[
					'action'      => 'discard-snapshot',
					'snapshot_id' => $state['snapshot']['snapshot_id'],
				]
			);
			if ( is_wp_error( $result ) ) {
				return $result; }
			unset( $state['snapshot'], $state['metadata'], $state['envelope'] );
		}
		$state['cleaned'] = ! isset( $state['transfer'] ) && ! isset( $state['snapshot'] );
		return $state;
	}

	private function snapshot_valid( array $s, $id, $previous ) {
		if ( ! File_Store::valid_id( $s['snapshot_id'] ?? null ) || ( $s['attachment_id'] ?? null ) !== $id || ! is_string( $s['file_name'] ?? null ) || sanitize_file_name( $s['file_name'] ) !== $s['file_name'] || ! is_int( $s['size'] ?? null ) || $s['size'] < 1 || ! $this->progress( [ 'transfer_id' => $s['snapshot_id'] ] + $s, $s['size'] ) ) {
			return false; }
		if ( $s['ready'] && ( ! is_string( $s['sha256'] ?? null ) || ! preg_match( '/^[a-f0-9]{64}$/D', $s['sha256'] ) ) ) {
			return false; }
		$generation = $s['generation'] ?? 0;
		if ( ! is_int( $generation ) || $generation < 0 ) {
			return false; }
		if ( $previous ) {
			$before = $previous['generation'] ?? 0;
			if ( $previous['snapshot_id'] !== $s['snapshot_id'] || $previous['size'] !== $s['size'] || $previous['file_name'] !== $s['file_name'] || $generation < $before ) {
				return false; }
			if ( $generation === $before && $s['offset'] < $previous['offset'] ) {
				return false; }
			// Lost responses can skip several rebuilt blocks; identity and final digest remain checked.
			if ( ! is_int( $before ) || $before < 0 || ( $generation > $before && ! empty( $previous['ready'] ) ) ) {
				return false; }
		}

		return true;
	}

	private function metadata_valid( array $payload, array $snapshot, array $input ) {
		if ( ! is_array( $payload['media'] ?? null ) || count( $payload['media'] ) !== 1 ) {
			return false; }
		$item = $payload['media'][0] ?? [];
		if ( ( $item['attachment_id'] ?? null ) !== $snapshot['attachment_id'] || ( $item['file_size'] ?? null ) !== $snapshot['size'] || ( $item['file_sha256'] ?? null ) !== $snapshot['sha256'] || ( $item['file_name'] ?? null ) !== $snapshot['file_name'] ) {
			return false; }
		if ( isset( $input['acf_graph_hashes'] ) ) {
			if ( is_wp_error( Sync_ACF_Media_Fields::envelope( $payload ) ) || ( $payload['acf_graph_hashes'] ?? null ) !== $input['acf_graph_hashes'] || ( $payload['acf_attachment_media_version'] ?? null ) !== ( $input['acf_attachment_media_version'] ?? null ) ) {
				return false; }
		} elseif ( isset( $payload['acf_graph_hashes'] ) || isset( $payload['acf_media_fields_version'] ) || isset( $payload['acf_attachment_media_version'] ) ) {
			return false; }
		foreach ( [
			'author_mapping'   => 'media_authors',
			'acf_user_mapping' => 'acf_attachment_users',
			'acf_term_mapping' => 'acf_attachment_terms',
		] as $option => $key ) {
			if ( isset( $input[ $option ] ) && ( 1 !== ( $payload[ $key ]['version'] ?? null ) || ( $payload[ $key ]['mapping'] ?? null ) !== $input[ $option ] ) ) {
				return false; }
			if ( ! isset( $input[ $option ] ) && isset( $payload[ $key ] ) ) {
				return false; }
		}
		return true;
	}

	private function progress( array $result, $size ) {
		return File_Store::valid_id( $result['transfer_id'] ?? null ) && ( $result['size'] ?? null ) === $size && is_int( $result['offset'] ?? null ) && $result['offset'] >= 0 && $result['offset'] <= $size && ( $result['ready'] ?? null ) === ( $result['offset'] === $size ) && ( $result['offset'] === $size || 0 === $result['offset'] % Media_Chunk_Store::CHUNK_BYTES );
	}

	private function error() {
		return new \WP_Error( 'rsl_ie_media_chunk_checkpoint', 'The media transfer checkpoint or peer response is invalid.', [ 'status' => 409 ] );
	}
}

<?php
/**
 * Shared Content Sync media peer operations.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Workflow_Schemas;
use RockStarLab\ImportExport\Helper\Remote_API;

defined( 'ABSPATH' ) || exit;

/** Validate the old peer envelope before entering the shared attachment importer. */
class Media_Transport_Service {
	/**
	 * Validate closed transport input and recheck execution permissions.
	 *
	 * @param array $input Peer payload.
	 * @param bool  $upload Whether file bytes are required.
	 * @return array|\WP_Error
	 */
	private function validate( array $input, $upload ) {
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access; }
		if ( ! current_user_can( 'upload_files' ) ) {
			return $this->error( 'forbidden', 403 ); }
		$s          = Workflow_Schemas::string();
		$properties = [
			'file_hash'                   => $s + [
				'pattern'   => '^[a-fA-F0-9]{32}$',
				'minLength' => 32,
				'maxLength' => 32,
			],
			'source_attachment_id'        => Workflow_Schemas::integer(),
			'source_origin_attachment_id' => Workflow_Schemas::integer(),
		];
		$required   = [ 'file_hash' ];
		if ( $upload ) {
			$properties += [
				'file_name'    => $s + [
					'minLength' => 1,
					'maxLength' => 255,
				],
				'file_data'    => $s + [
					'minLength' => 1,
					'maxLength' => 22369624,
				],
				'force_unique' => [ 'type' => 'boolean' ],
				'mime_type'    => $s,
				'title'        => $s,
				'alt_text'     => $s,
				'caption'      => $s,
				'description'  => $s,
				'acf'          => [
					'type'                 => 'object',
					'additionalProperties' => Workflow_Schemas::value(),
				],
			];
			$required    = [ 'file_hash', 'file_name', 'file_data' ];
		}
		$schema = Workflow_Schemas::object( $properties, $required );
		$valid  = rest_validate_value_from_schema( $input, $schema, 'input' );
		if ( is_wp_error( $valid ) ) {
			$valid->add_data( [ 'status' => 400 ] );
			return $valid; }
		return rest_sanitize_value_from_schema( $input, $schema );
	}

	/**
	 * Read source/origin/hash matches without modifying the hash index or image sizes.
	 *
	 * @param array $input Hash and optional source IDs.
	 * @return array|\WP_Error
	 */
	public function check( array $input ) {
		$input = $this->validate( $input, false );
		if ( is_wp_error( $input ) ) {
			return $input; }
		$id = ( new Media_Library_Sync_Service() )->check_file( $input );
		return is_wp_error( $id ) ? $id : [
			'exists'        => (bool) $id,
			'attachment_id' => (int) $id,
		];
	}

	/**
	 * Receive one file through the same byte validation, lock, sideload and ACF path as Library Sync.
	 *
	 * @param array $input File payload; source ID zero supports external media.
	 * @return array|\WP_Error
	 */
	public function upload( array $input ) {
		$input = $this->validate( $input, true );
		if ( is_wp_error( $input ) ) {
			return $input; }
		$result = ( new Media_Library_Sync_Service() )->receive_file( $input );
		if ( is_wp_error( $result ) ) {
			return $result; }
		$item = $result['items'][0];
		if ( 'failed' === $item['status'] ) {
			return $this->error( 'upload_failed', 500 ); }
		return [
			'attachment_id' => $item['attachment_id'],
			'url'           => (string) wp_get_attachment_url( $item['attachment_id'] ),
			'status'        => $item['status'],
		];
	}

	/**
	 * Send actual current bytes rather than trusting a cached source hash or arbitrary disk path.
	 *
	 * @param array $image Server-collected source media description.
	 * @param array $site Stored connected site.
	 * @return int|\WP_Error
	 */
	public function push_image( array $image, array $site ) {
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access; }
		if ( ! current_user_can( 'upload_files' ) ) {
			return $this->error( 'forbidden', 403 ); }
		if ( ! empty( $image['file_path'] ) ) {
			return $this->push_chunked_image( $image, $site );
		}
		return $this->push_external_image( $image, $site );
	}

	/** Download public media to disk, then send bounded blocks without a Base64 file envelope. */
	private function push_external_image( array $image, array $site ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$temp = wp_tempnam( $image['file_name'] ?? 'media' );
		if ( ! $temp ) {
			return $this->error( 'download_failed', 500 );
		}
		$stream = null;
		$transfer = null;
		$media = new Media_Library_Sync_Service();
		$fingerprint = ( new Sync_Job_Service() )->fingerprint( $site );
		$call = static function ( $payload ) use ( $media, $site, $fingerprint ) {
			return $media->chunk_request( (int) $site['id'], 'push', $fingerprint, $payload );
		};
		try {
			$capabilities = $media->negotiate_chunks( (int) $site['id'], 'push', $fingerprint );
			if ( is_wp_error( $capabilities ) ) {
				return $capabilities;
			}
			$limit = wp_max_upload_size();
			$response = wp_safe_remote_get( $image['url'] ?? '', [ 'stream' => true, 'filename' => $temp, 'timeout' => 180, 'limit_response_size' => $limit + 1 ] );
			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				return $this->error( 'download_failed', 502 );
			}
			$size = filesize( $temp );
			if ( ! $size || $size > $limit ) {
				return $this->error( 'size_limit', 413 );
			}
			$transfer = $call( [ 'action' => 'begin', 'request_id' => 'external-media-' . wp_generate_uuid4(), 'file_name' => sanitize_file_name( $image['file_name'] ?? wp_basename( wp_parse_url( $image['url'], PHP_URL_PATH ) ) ), 'size' => $size, 'sha256' => hash_file( 'sha256', $temp ) ] );
			if ( is_wp_error( $transfer ) ) {
				return $transfer;
			}
			if ( ! File_Store::valid_id( $transfer['transfer_id'] ?? null ) || 0 !== ( $transfer['offset'] ?? null ) || $size !== ( $transfer['size'] ?? null ) ) {
				return $this->error( 'peer_rejected', 502 );
			}
			$stream = fopen( $temp, 'rb' );
			if ( ! $stream ) {
				return $this->error( 'download_failed', 500 );
			}
			for ( $offset = 0; $offset < $size; $offset += strlen( $bytes ) ) {
				$bytes = fread( $stream, Media_Chunk_Store::CHUNK_BYTES );
				if ( false === $bytes || '' === $bytes ) {
					return $this->error( 'download_failed', 500 );
				}
				$next = $call( [ 'action' => 'append', 'transfer_id' => $transfer['transfer_id'], 'offset' => $offset, 'data' => base64_encode( $bytes ) ] );
				if ( is_wp_error( $next ) ) {
					return $next;
				}
				if ( ( $next['transfer_id'] ?? null ) !== $transfer['transfer_id'] || ( $next['offset'] ?? null ) !== $offset + strlen( $bytes ) || ( $next['size'] ?? null ) !== $size ) {
					return $this->error( 'peer_rejected', 502 );
				}
			}
			$metadata = array_intersect_key( $image, array_flip( [ 'title', 'alt_text', 'caption', 'description', 'acf', 'source_origin_attachment_id', 'force_unique' ] ) );
			$metadata['source_attachment_id'] = (int) ( $image['attachment_id'] ?? 0 );
			$deadline = microtime( true ) + 180;
			do {
				$result = $call( [ 'action' => 'finalize', 'transfer_id' => $transfer['transfer_id'], 'metadata' => $metadata, 'defer_fields' => false, 'bounded_metadata' => ! empty( $capabilities['bounded_metadata'] ) ] );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			} while ( [ 'pending' => true ] === $result && microtime( true ) < $deadline );
			$item = $result['items'][0] ?? [];
			if ( 1 !== count( $result['items'] ?? [] ) || ( $item['source_id'] ?? null ) !== $metadata['source_attachment_id'] || ! in_array( $item['status'] ?? '', [ 'created', 'skipped' ], true ) || ! is_int( $item['attachment_id'] ?? null ) || $item['attachment_id'] < 1 ) {
				return $this->error( 'peer_rejected', 502 );
			}
			$call( [ 'action' => 'discard', 'transfer_id' => $transfer['transfer_id'] ] );
			return $item['attachment_id'];
		} finally {
			if ( is_resource( $stream ) ) {
				fclose( $stream );
			}
			wp_delete_file( $temp );
		}
	}


	/** Transfer large local attachments using the same validated chunk protocol as media sync. */
	private function push_chunked_image( array $image, array $site ) {
		$result = ( new Media_Chunk_Transfer() )->execute( 'push', $site, [ 'media_ids' => [ (int) $image['attachment_id'] ] ], $image );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$item = $result['items'][0];
		return 'failed' === $item['status'] ? $this->error( 'peer_rejected', 502 ) : $item['attachment_id'];
	}

	/**
	 * Fetch current remote bytes; remote file_path and cached hashes never select a local file.
	 *
	 * @param array $image Source image metadata.
	 * @param array $site Stored connection.
	 * @return int|\WP_Error
	 */
	public function pull_image( array $image, array $site ) {
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access; }
		if ( ! current_user_can( 'upload_files' ) ) {
			return $this->error( 'forbidden', 403 ); }
		$url = $image['source_urls']['full'] ?? $image['url'] ?? '';
		if ( ! is_string( $url ) ) {
			return $this->error( 'invalid_url', 400 ); }
		$parts = wp_parse_url( $url );
		$peer  = wp_parse_url( $site['remote_url'] );
		if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! in_array( $parts['scheme'] ?? '', [ 'http', 'https' ], true ) || isset( $parts['user'] ) || isset( $parts['pass'] ) ) {
			return $this->error( 'invalid_url', 400 ); }
		$origin = static function ( $parts ) {
			return strtolower( $parts['scheme'] ?? '' ) . '://' . strtolower( $parts['host'] ?? '' ) . ':' . ( $parts['port'] ?? ( 'https' === ( $parts['scheme'] ?? '' ) ? 443 : 80 ) );
		};
		// The saved peer is explicitly configured and may be a local/private site. Never follow its redirects or forward its key to file URLs.
		$options         = [
			'timeout'             => 30,
			'redirection'         => 0,
			'limit_response_size' => Media_Library_Sync_Service::FILE_LIMIT + 1,
		];
		$configured_peer = $origin( $parts ) === $origin( $peer );
		if ( $configured_peer && ! empty( $site['id'] ) && ctype_digit( (string) ( $image['attachment_id'] ?? '' ) ) && (int) $image['attachment_id'] > 0 ) {
			$result = ( new Media_Chunk_Transfer() )->execute( 'pull', $site, [ 'media_ids' => [ (int) $image['attachment_id'] ] ], $image );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$item = $result['items'][0];
			return 'failed' === $item['status'] ? $this->error( 'import_failed', 500 ) : $item['attachment_id'];
		}
		$url             = \RockStarLab\ImportExport\Helper\Content_Sync_Media::canonicalize_import_media_url(
			$url,
			static function ( $candidate ) use ( $configured_peer ) {
				$options  = [
					'method'              => 'HEAD',
					'timeout'             => 10,
					'redirection'         => 0,
					'limit_response_size' => 1,
				];
				$response = $configured_peer ? wp_remote_request( $candidate, $options ) : wp_safe_remote_request( $candidate, $options );
				return ! is_wp_error( $response ) && 200 === (int) wp_remote_retrieve_response_code( $response );
			}
		);
		$parts = wp_parse_url( $url );
		$temp = wp_tempnam( wp_basename( $parts['path'] ?? '' ) );
		if ( ! $temp ) {
			return $this->error( 'download_failed', 500 );
		}
		$transfer_id = null;
		$stream = null;
		try {
			// Stream to disk: the inline 16 MB envelope is not a file download limit.
			$limit = wp_max_upload_size();
			$options['stream'] = true;
			$options['filename'] = $temp;
			$options['timeout'] = 180;
			$options['limit_response_size'] = $limit + 1;
			$response = $configured_peer ? wp_remote_get( $url, $options ) : wp_safe_remote_get( $url, $options );
			if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
				return $this->error( 'download_failed', 502 );
			}
			$size = filesize( $temp );
			if ( ! $size || $size > $limit ) {
				return $this->error( 'size_limit', 413 );
			}
			$transfer = Media_Chunk_Store::begin( sanitize_file_name( wp_basename( $parts['path'] ?? '' ) ), $size, hash_file( 'sha256', $temp ) );
			if ( is_wp_error( $transfer ) ) {
				return $transfer;
			}
			$transfer_id = $transfer['transfer_id'];
			$stream = fopen( $temp, 'rb' );
			if ( ! $stream ) {
				return $this->error( 'download_failed', 500 );
			}
			for ( $offset = 0; $offset < $size; $offset += strlen( $bytes ) ) {
				$bytes = fread( $stream, Media_Chunk_Store::CHUNK_BYTES );
				if ( false === $bytes || '' === $bytes ) {
					return $this->error( 'download_failed', 500 );
				}
				$chunk = Media_Chunk_Store::append( $transfer_id, $offset, $bytes );
				if ( is_wp_error( $chunk ) ) {
					return $chunk;
				}
			}
			$payload = array_intersect_key( $image, array_flip( [ 'title', 'alt_text', 'caption', 'description', 'acf', 'source_origin_attachment_id', 'force_unique' ] ) );
			$payload['source_attachment_id'] = $image['attachment_id'] ?? 0;
			$result = ( new Media_Library_Sync_Service() )->receive_staged_file( $transfer_id, $payload, 'local', true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			return 'failed' === $result['items'][0]['status'] ? $this->error( 'import_failed', 500 ) : $result['items'][0]['attachment_id'];
		} finally {
			if ( is_resource( $stream ) ) {
				fclose( $stream );
			}
			wp_delete_file( $temp );
			if ( $transfer_id ) {
				Media_Chunk_Store::discard( $transfer_id );
			}
		}
	}

	/**
	 * Apply attachment text and ACF only after all source IDs have destination mappings.
	 *
	 * @param int   $id Authorized target attachment.
	 * @param array $image Source metadata.
	 * @param array $map Complete source-to-target map.
	 * @param array $sources Source URL metadata.
	 * @return true|\WP_Error
	 */
	public function apply_pull_fields( $id, array $image, array $map, array $sources ) {
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access; }
		if ( ! current_user_can( 'upload_files' ) || 'attachment' !== get_post_type( $id ) || ! current_user_can( 'read_post', $id ) || ! current_user_can( 'edit_post', $id ) ) {
			return $this->error( 'forbidden', 403 ); }
		$acf    = $image['acf'] ?? [];
		$fields = array_column( \RockStarLab\ImportExport\Helper\ACF_Fields::get_fields_for_content_type( 'media' ), 'name' );
		if ( ! is_array( $acf ) || array_diff( array_keys( $acf ), $fields ) ) {
			return $this->error( 'unknown_acf_fields', 400 ); }
		foreach ( [ $image['attachment_id'] ?? 0, $image['source_origin_attachment_id'] ?? 0 ] as $source ) {
			if ( $source > 0 ) {
				$map[ $source ] = (int) $id; }
		}
		$urls = [];
		foreach ( $sources as $source ) {
			$target = $map[ $source['attachment_id'] ?? 0 ] ?? 0;
			if ( ! $target ) {
				continue; }
			$target_url = wp_get_attachment_url( $target );
			foreach ( array_merge( [ $source['url'] ?? '', $source['source_urls']['full'] ?? '' ], (array) ( $source['source_urls']['by_size'] ?? [] ) ) as $url ) {
				if ( is_string( $url ) && '' !== $url && $target_url ) {
					$urls[ $url ] = $target_url; }
			}
		}
		$replace = function ( $value ) use ( $map, $sources, $urls ) {
			return $this->map_field_value( $value, $map, $sources, $urls );
		};
		$update  = [ 'ID' => $id ];
		if ( array_key_exists( 'description', $image ) ) {
			$description            = $replace( $image['description'] );
			$update['post_content'] = wp_kses_post( \RockStarLab\ImportExport\Helper\ACF_Fields::replace_media_urls_in_html( $description, $id ) );
		}
		if ( ! empty( $image['caption'] ) ) {
			$update['post_excerpt'] = wp_kses_post( $image['caption'] ); }
		if ( ! empty( $image['title'] ) ) {
			$update['post_title'] = sanitize_text_field( $image['title'] ); }
		$updated = wp_update_post( wp_slash( $update ), true );
		if ( is_wp_error( $updated ) ) {
			return $this->error( 'fields_failed', 500 ); }
		foreach ( $acf as $name => $value ) {
			\RockStarLab\ImportExport\Helper\ACF_Fields::import_value( 'media', $id, $name, $replace( $value ) ); }
		return true;
	}

	/**
	 * Map portable JSON, nested media objects and HTML using the completed transfer map.
	 *
	 * @param mixed $value Portable field value.
	 * @param array $map Media IDs.
	 * @param array $sources Source metadata.
	 * @param array $urls Exact source URL replacements.
	 * @return mixed
	 */
	private function map_field_value( $value, array $map, array $sources, array $urls ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $child ) {
				if ( in_array( $key, [ 'id', 'ID' ], true ) && is_numeric( $child ) && isset( $map[ (int) $child ] ) && isset( $value['url'] ) ) {
					$value[ $key ] = (int) $map[ (int) $child ]; } else {
					$value[ $key ] = $this->map_field_value( $child, $map, $sources, $urls ); }
			}
			return $value;
		}
		if ( ! is_string( $value ) ) {
			return $value; }
		$decoded = json_decode( $value, true );
		if ( is_array( $decoded ) ) {
			return wp_json_encode( $this->map_field_value( $decoded, $map, $sources, $urls ) ); }
		$value = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::fix_local_image_urls_in_content( $value, $map, $sources );
		return strtr( $value, $urls );
	}

	/**
	 * Safe errors do not expose remote text, credentials or disk paths.
	 *
	 * @param string $code Error suffix.
	 * @param int    $status HTTP status.
	 * @return \WP_Error
	 */
	private function error( $code, $status ) {
		return new \WP_Error( 'rsl_ie_media_transport_' . $code, __( 'Media transfer could not complete. Check permissions, file limits and the destination before retrying.', 'import-export-by-rockstarlab' ), [ 'status' => $status ] );
	}
}

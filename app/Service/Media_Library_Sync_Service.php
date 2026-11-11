<?php
/**
 * Shared Media Library browse/push/pull for abilities, AJAX and peer REST.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Media_Library_Catalog;
use RockStarLab\ImportExport\Abilities\Standalone_Sync_Policy;
use RockStarLab\ImportExport\Helper\ACF_Fields;
use RockStarLab\ImportExport\Helper\Media_Hash;
use RockStarLab\ImportExport\Helper\Remote_API;
use RockStarLab\ImportExport\Model\Connected_Site;

defined( 'ABSPATH' ) || exit;

/** Preflight whole selections and never accept file paths from a peer. */
class Media_Library_Sync_Service {
	private static $active_svg_transfers = [];
	/** Whether this request generates at most one missing image size. */
	private $bounded_metadata = false;
	private $metadata_pending = false;

	const FILE_LIMIT  = 16777216;
	const BATCH_LIMIT = 33554432;

	/**
	 * Require management and media permission, including direct PHP calls.
	 *
	 * @return true|\WP_Error
	 */
	private function authorize() {
		$access = Access_Policy::check_management();
		return is_wp_error( $access ) ? $access : ( current_user_can( 'upload_files' ) ? true : $this->error( 'forbidden', 403 ) );
	}

	/**
	 * Recheck queued local selection without loading or persisting file bytes.
	 *
	 * @param string $action Push or pull.
	 * @param array  $input Selection and connection.
	 * @param bool   $chunked Internal staged transport; retain selection and permission checks.
	 * @param array  $confirmed_ids Server-owned completed sources; never caller input.
	 * @return true|\WP_Error
	 */
	public function preflight( $action, array $input, $chunked = false, array $confirmed_ids = [] ) {
		$access = $this->authorize();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		$definition = Media_Library_Catalog::definitions()[ 'sync-media-' . $action ] ?? null;
		if ( ! $definition || ! in_array( $action, [ 'push', 'pull' ], true ) ) {
			return $this->error( 'invalid_action', 400 );
		}
		$valid = rest_validate_value_from_schema( $input, $definition['input'], 'input' );
		if ( is_wp_error( $valid ) ) {
			$valid->add_data( [ 'status' => 400 ] );
			return $valid;
		}
		if ( isset( $input['author_mapping'] ) && is_wp_error( Sync_Media_Authors::run( 'authorize', [] ) ) ) {
			return Migration_Sync_Maps::error();
		}
		if ( isset( $input['acf_user_mapping'] ) && is_wp_error( Sync_ACF_Media_Users::run( 'authorize', [] ) ) ) {
			return Migration_Sync_Maps::error();
		}
		if ( isset( $input['acf_term_mapping'] ) && is_wp_error( Sync_ACF_Media_Terms::run( 'authorize', [] ) ) ) {
			return Migration_Sync_Maps::error();
		}
		$files = 'push' === $action ? $this->selected_files( $chunked ? array_values( array_diff( $input['media_ids'], $confirmed_ids ) ) : $input['media_ids'], ! $chunked ) : true;
		return is_wp_error( $files ) ? $files : true;
	}

	/**
	 * Resolve authorized uploads and reject a selection exceeding byte limits.
	 *
	 * @param array $ids Selected attachment IDs.
	 * @return array|\WP_Error
	 */
	private function selected_files( array $ids, $inline = true ) {
		$files = [];
		$total = 0;
		foreach ( $ids as $id ) {
			if ( 'attachment' !== get_post_type( $id ) || ! current_user_can( 'read_post', $id ) || ! current_user_can( 'edit_post', $id ) ) {
				return $this->error( 'attachment_forbidden', 403 ); }
			$path = $this->file( $id );
			if ( is_wp_error( $path ) ) {
				return $path; }
			clearstatcache( true, $path );
			$size   = filesize( $path );
			$total += $size;
			if ( ! $size || ( $inline && ( $size > self::FILE_LIMIT || $total > self::BATCH_LIMIT ) ) ) {
				return $this->error( 'size_limit', 413 ); }
			$files[ $id ] = $path;
		}
		return $files;
	}

	/** Describe authorized source bytes for a resumable transfer, without a public path. */
	public function source_manifest( $attachment_id ) {
		$access = $this->authorize();
		if ( is_wp_error( $access ) ) {
			return $access; }
		if ( ! is_int( $attachment_id ) || $attachment_id < 1 || 'attachment' !== get_post_type( $attachment_id ) || ! current_user_can( 'read_post', $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
			return $this->error( 'attachment_forbidden', 403 ); }
		$path = $this->file( $attachment_id );
		if ( is_wp_error( $path ) ) {
			return $path; }
		$name = basename( $path );
		if ( sanitize_file_name( $name ) !== $name || ! wp_check_filetype( $name, Sync_SVG::mimes() )['type'] ) {
			return $this->error( 'invalid_file_type', 400 ); }
		$stream = fopen( $path, 'rb' );
		if ( ! $stream ) {
			return $this->error( 'file_unavailable', 400 ); }
		try {
			$before = fstat( $stream );
			$hash   = hash_init( 'sha256' );
			$read   = hash_update_stream( $hash, $stream );
			$after  = fstat( $stream );
			if ( $before['size'] < 1 || $read !== $before['size'] || $before['size'] !== $after['size'] || $before['mtime'] !== $after['mtime'] || $before['ctime'] !== $after['ctime'] ) {
				return $this->error( 'source_changed', 409 ); }
			return [
				'attachment_id' => $attachment_id,
				'file_name'     => $name,
				'size'          => $read,
				'sha256'        => hash_final( $hash ),
			];
		} finally {
			fclose( $stream ); }
	}

	/** Freeze authorized bytes once; later reads use only the private snapshot and current attachment permissions. */
	public function source_snapshot( $attachment_id, $request_id, $credential_scope = 'local', $bounded = false ) {
		if ( ! is_string( $request_id ) || ! preg_match( '/^[A-Za-z0-9_-]{16,128}$/D', $request_id ) ) {
			return File_Store::error( 'invalid_request_id' ); }
		if ( $bounded ) {
			$access = $this->authorize();
			if ( is_wp_error( $access ) ) {
				return $access; }
			if ( ! is_int( $attachment_id ) || $attachment_id < 1 || 'attachment' !== get_post_type( $attachment_id ) || ! current_user_can( 'read_post', $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
				return $this->error( 'attachment_forbidden', 403 ); }
			$path = $this->file( $attachment_id );
			return is_wp_error( $path ) ? $path : Media_Chunk_Store::start_source_snapshot( $attachment_id, $path, $credential_scope, $request_id );
		}
		$manifest = $this->source_manifest( $attachment_id );
		if ( is_wp_error( $manifest ) ) {
			return $manifest; }
		$path = $this->file( $attachment_id );
		return is_wp_error( $path ) ? $path : Media_Chunk_Store::snapshot_source( $attachment_id, $path, $manifest, $credential_scope, $request_id, $bounded ? 1 : 0 );
	}

	/** Advance only one source block, without recomputing its complete manifest. */
	public function advance_source_snapshot( $snapshot_id, $credential_scope = 'local' ) {
		$info = Media_Chunk_Store::snapshot_info( $snapshot_id, $credential_scope );
		if ( is_wp_error( $info ) ) {
			return $info; }
		$id = $info['attachment_id'];
		if ( 'attachment' !== get_post_type( $id ) || ! current_user_can( 'read_post', $id ) || ! current_user_can( 'edit_post', $id ) ) {
			return $this->error( 'attachment_forbidden', 403 ); }
		$path = $this->file( $id );
		return is_wp_error( $path ) ? $path : Media_Chunk_Store::advance_snapshot( $snapshot_id, $id, $path, $credential_scope );
	}

	/** Read one authorized source slice without allocating the complete file. */
	public function read_source_chunk( $attachment_id, $offset, $expected_size, $expected_sha256 ) {
		$access = $this->authorize();
		if ( is_wp_error( $access ) ) {
			return $access; }
		if ( ! is_int( $attachment_id ) || ! is_int( $offset ) || $offset < 0 || ! is_int( $expected_size ) || $expected_size < 1 || $offset >= $expected_size || ! is_string( $expected_sha256 ) || ! preg_match( '/^[a-f0-9]{64}$/D', $expected_sha256 ) ) {
			return $this->error( 'invalid_payload', 400 ); }
		if ( 'attachment' !== get_post_type( $attachment_id ) || ! current_user_can( 'read_post', $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
			return $this->error( 'attachment_forbidden', 403 ); }
		$path = $this->file( $attachment_id );
		if ( is_wp_error( $path ) ) {
			return $path; }
		$stream = fopen( $path, 'rb' );
		if ( ! $stream ) {
			return $this->error( 'file_unavailable', 400 ); }
		try {
			if ( fstat( $stream )['size'] !== $expected_size ) {
				return $this->error( 'source_changed', 409 ); }
			$hash = hash_init( 'sha256' );
			hash_update_stream( $hash, $stream );
			if ( ! hash_equals( $expected_sha256, hash_final( $hash ) ) || 0 !== fseek( $stream, $offset ) ) {
				return $this->error( 'source_changed', 409 ); }
			$length = min( Media_Chunk_Store::CHUNK_BYTES, $expected_size - $offset );
			$bytes  = fread( $stream, $length );
			if ( false === $bytes || strlen( $bytes ) !== $length ) {
				return $this->error( 'source_changed', 409 ); }
			return [
				'offset'      => $offset,
				'next_offset' => $offset + $length,
				'data'        => base64_encode( $bytes ),
			];
		} finally {
			fclose( $stream ); }
	}

	/**
	 * Group saved migration IDs by current decoded bytes and transport item limit.
	 *
	 * @param array $ids Authorized saved attachment identities.
	 * @return array|\WP_Error
	 */
	public function migration_batches( array $ids, $chunked = false ) {
		$access = $this->authorize();
		if ( is_wp_error( $access ) ) {
			return $access; }
		if ( ! $ids ) {
			return $this->error( 'size_limit', 413 ); }
		$batches = [];
		$batch   = [];
		$total   = 0;
		foreach ( $ids as $id ) {
			$files = $this->selected_files( [ $id ], ! $chunked );
			if ( is_wp_error( $files ) ) {
				return $files; }
			$size = filesize( $files[ $id ] );
			if ( $batch && ( count( $batch ) >= 50 || ( ! $chunked && $total + $size > self::BATCH_LIMIT ) ) ) {
				$batches[] = $batch;
				$batch     = [];
				$total     = 0;
			}
			$batch[] = $id;
			$total  += $size;
		}
		$batches[] = $batch;
		return $batches;
	}

	/**
	 * Execute a connected-site operation with its closed input contract.
	 *
	 * @param string $action Browse, push or pull.
	 * @param array  $input Parsed caller input.
	 * @return array|\WP_Error
	 */
	public function execute( $action, array $input ) {
		$access = $this->authorize();
		if ( is_wp_error( $access ) ) {
			return $access; }
		$definition = Media_Library_Catalog::definitions()[ 'sync-media-' . $action ] ?? null;
		if ( ! $definition ) {
			return $this->error( 'invalid_action', 400 ); }
		$valid = rest_validate_value_from_schema( $input, $definition['input'], 'input' );
		if ( is_wp_error( $valid ) ) {
			$valid->add_data( [ 'status' => 400 ] );
			return $valid; }
		$input = rest_sanitize_value_from_schema( $input, $definition['input'] );
		$lock  = Job_Lock::acquire_resource( 'connection', $input['site_id'] );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return $this->error( 'connection_busy', 409 ); }
		try {
			$site = Connected_Site::get_by_id( $input['site_id'] );
			if ( ! $site || 'active' !== $site['status'] || ( 'browse' !== $action && ! in_array( $site['direction'], [ $action, 'bidirectional' ], true ) ) ) {
				return $this->error( 'connection_unavailable', 409 ); }
			$guard = apply_filters( 'rsl_ie_sync_connection_guard', true, $site );
			if ( is_wp_error( $guard ) ) {
				return $guard;
			}
			unset( $input['site_id'] );
			if ( in_array( $action, [ 'push', 'pull' ], true ) ) {
				return ( new Media_Chunk_Transfer() )->execute( $action, $site, $input );
			}
			if ( 'browse' === $action ) {
				$result = $this->remote( $site, 'list-media', $input );
				if ( is_wp_error( $result ) ) {
					return $result; }
				unset( $result['success'], $result['media_contract_version'] );
			}
			unset( $result['media_authors_version'], $result['acf_media_fields_version'], $result['acf_attachment_media_version'], $result['acf_attachment_users_version'], $result['acf_attachment_terms_version'] );
			if ( is_wp_error( rest_validate_value_from_schema( $result, $definition['output'], 'result' ) ) ) {
				return $this->error( 'invalid_peer_response', 502 ); }
			if ( 'browse' !== $action && ! $this->valid_result( $result, $input['media_ids'] ) ) {
				return $this->error( 'invalid_peer_response', 502 ); }
			return $result;
		} catch ( \Throwable $exception ) {
			return $this->error( 'transfer_interrupted', 500 );
		} finally {
			Job_Lock::release( $lock );
		}
	}

	/**
	 * Read authorized attachment summaries without hashing or writing metadata.
	 *
	 * @param array $input Search and pagination.
	 * @return array|\WP_Error
	 */
	public function list_media( array $input ) {
		$access = $this->authorize();
		if ( is_wp_error( $access ) ) {
			return $access; }
		$schema = Media_Library_Catalog::definitions()['sync-media-browse']['input'];
		unset( $schema['properties']['site_id'] );
		$schema['required'] = [];
		$valid              = rest_validate_value_from_schema( $input, $schema, 'input' );
		if ( is_wp_error( $valid ) ) {
			$valid->add_data( [ 'status' => 400 ] );
			return $valid; }
		$input = rest_sanitize_value_from_schema( $input, $schema );
		$page  = (int) ( $input['page'] ?? 1 );
		$query = new \WP_Query(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => $input['per_page'] ?? 60,
				'paged'          => $page,
				's'              => $input['search'] ?? '',
				'orderby'        => [
					'date' => 'DESC',
					'ID'   => 'DESC',
				],
			]
		);
		$media = [];
		foreach ( $query->posts as $post ) {
			if ( ! current_user_can( 'read_post', $post->ID ) ) {
				return $this->error( 'attachment_forbidden', 403 ); }
			$media[] = [
				'ID'            => (int) $post->ID,
				'title'         => (string) $post->post_title,
				'url'           => (string) wp_get_attachment_url( $post->ID ),
				'preview_url'   => (string) wp_get_attachment_image_url( $post->ID, 'thumbnail' ),
				'thumbnail_url' => (string) wp_get_attachment_image_url( $post->ID, 'thumbnail' ),
				'mime_type'     => (string) $post->post_mime_type,
				'file_name'     => basename( (string) get_attached_file( $post->ID ) ),
				'date'          => (string) $post->post_date,
			];
		}
		return [
			'media'        => $media,
			'total'        => (int) $query->found_posts,
			'pages'        => (int) $query->max_num_pages,
			'current_page' => $page,
		];
	}

	/**
	 * Read only the selected files, bounded before reading bytes.
	 *
	 * @param array $input Media IDs.
	 * @return array|\WP_Error
	 */
	public function send_media( array $input ) {
		return $this->prepare_media_payload( $input, false );
	}

	/** Internal preparation for chunk transport; retains graph and mapping envelopes. */
	public function prepare_chunk_media( array $input ) {
		return $this->prepare_media_payload( $input, true );
	}

	/** Bind current authorized metadata to one completed source snapshot. */
	public function prepare_snapshot_media( $snapshot_id, array $input, $credential_scope = 'local' ) {
		$snapshot = Media_Chunk_Store::snapshot_info( $snapshot_id, $credential_scope );
		if ( is_wp_error( $snapshot ) ) {
			return $snapshot; }
		if ( ! $snapshot['ready'] ) {
			return $this->error( 'snapshot_incomplete', 409 ); }
		if ( ( $input['media_ids'] ?? null ) !== [ $snapshot['attachment_id'] ] ) {
			return $this->error( 'invalid_payload', 400 ); }
		$payload = $this->prepare_chunk_media( $input );
		if ( is_wp_error( $payload ) ) {
			return $payload; }
		$item = $payload['media'][0];
		if ( $item['file_size'] !== $snapshot['size'] || $item['file_sha256'] !== $snapshot['sha256'] || $item['file_name'] !== $snapshot['file_name'] ) {
			return $this->error( 'source_changed', 409 ); }
		// Providers run before the main manifest; reject proofs from different source bytes.
		foreach ( [
			'media_authors'        => [ 'records', 'attachment_id', 'file' ],
			'acf_attachment_users' => [ 'posts', 'ID', 'acf_object_file_proof' ],
			'acf_attachment_terms' => [ 'posts', 'ID', 'acf_object_file_proof' ],
		] as $key => $fields ) {
			if ( ! isset( $payload[ $key ] ) ) {
				continue; }
			$records = $payload[ $key ][ $fields[0] ] ?? null;
			if ( ! is_array( $records ) || 1 !== count( $records ) ) {
				return $this->error( 'source_changed', 409 ); }
			$record = reset( $records );
			$proof  = $record[ $fields[2] ] ?? [];
			if ( ( $record[ $fields[1] ] ?? null ) !== $snapshot['attachment_id'] || ( $proof['size'] ?? null ) !== $snapshot['size'] || ( $proof['sha256'] ?? null ) !== $snapshot['sha256'] ) {
				return $this->error( 'source_changed', 409 ); }
		}
		// Freeze metadata and mapping choices to this snapshot before any destination writes.
		$bound = Media_Chunk_Store::bind_finalization( $snapshot_id, $payload, 'source:' . $credential_scope );
		return is_wp_error( $bound ) ? $bound : $payload;
	}

	private function prepare_media_payload( array $input, $chunked ) {
		$graph_hashes  = $input['acf_graph_hashes'] ?? null;
		$media_version = $input['acf_attachment_media_version'] ?? null;
		if ( null !== $media_version && ( 1 !== $media_version || null === $graph_hashes ) ) {
			return Migration_Sync_Maps::error();
		}
		unset( $input['acf_attachment_media_version'] );
		unset( $input['acf_graph_hashes'] );
		$access = $this->authorize();
		if ( is_wp_error( $access ) ) {
			return $access; }
		$schema = Media_Library_Catalog::definitions()['sync-media-push']['input'];
		unset( $schema['properties']['site_id'] );
		$schema['required'] = [ 'media_ids' ];
		$valid              = rest_validate_value_from_schema( $input, $schema, 'input' );
		if ( is_wp_error( $valid ) ) {
			$valid->add_data( [ 'status' => 400 ] );
			return $valid; }
		$input   = rest_sanitize_value_from_schema( $input, $schema );
		$authors = isset( $input['author_mapping'] ) ? Sync_Media_Authors::run( $chunked ? 'export_staged' : 'export', $input ) : [];
		if ( is_wp_error( $authors ) ) {
			return $authors;
		}
		$users = isset( $input['acf_user_mapping'] ) ? Sync_ACF_Media_Users::run( $chunked ? 'export_staged' : 'export', $input ) : [];
		if ( is_wp_error( $users ) ) {
			return $users;
		}
		$terms = isset( $input['acf_term_mapping'] ) ? Sync_ACF_Media_Terms::run( $chunked ? 'export_staged' : 'export', $input ) : [];
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		$files = $this->selected_files( $input['media_ids'], ! $chunked );
		if ( is_wp_error( $files ) ) {
			return $files;
		}
		$media = [];
		$total = 0;
		foreach ( $files as $id => $path ) {
			if ( $chunked ) {
				$manifest = $this->source_manifest( (int) $id );
				if ( is_wp_error( $manifest ) ) {
					return $manifest; }
				$binary = [
					'file_size'   => $manifest['size'],
					'file_sha256' => $manifest['sha256'],
				];
			} else {
				$bytes  = file_get_contents( $path, false, null, 0, self::FILE_LIMIT + 1 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Bounded local uploads file.
				$total += strlen( (string) $bytes );
				if ( false === $bytes || '' === $bytes || strlen( $bytes ) > self::FILE_LIMIT || $total > self::BATCH_LIMIT ) {
					return $this->error( 'size_limit', 413 ); }
				$binary = [
					'file_data' => base64_encode( $bytes ),
					'file_hash' => md5( $bytes ),
				];
			}

			$post = get_post( $id );
			$acf  = [];
			foreach ( ACF_Fields::get_fields_for_content_type( 'media' ) as $field ) {
				$acf[ $field['name'] ] = ACF_Fields::export_value( 'media', $id, $field['name'] ); }
			$media[] = [
				'attachment_id'               => (int) $id,
				'file_name'                   => basename( $path ),
				'source_origin_attachment_id' => absint( get_post_meta( $id, '_rsl_ie_original_attachment_id', true ) ),
				'title'                       => $post->post_title,
				'alt_text'                    => (string) get_post_meta( $id, '_wp_attachment_image_alt', true ),
				'caption'                     => $post->post_excerpt,
				'description'                 => ACF_Fields::export_string_with_media_shortcode_tokens( $post->post_content ),
				'acf'                         => $acf,
			] + $binary;
		}
		if ( null !== $graph_hashes ) {
			$check = Sync_ACF_Media_Fields::source( $input['media_ids'], $graph_hashes );
			if ( is_wp_error( $check ) ) {
				return $check;
			}
			return [
				'media'                    => $media,
				'acf_media_fields_version' => 1,
				'acf_graph_hashes'         => $graph_hashes,
			] + ( 1 === $media_version ? [ 'acf_attachment_media_version' => 1 ] : [] ) + $users + $terms + $authors;
		}
		return [ 'media' => $media ] + $users + $terms + $authors;
	}

	/**
	 * Validate every payload before writing, then serialize destination imports.
	 *
	 * @param array $input Encoded media items.
	 * @return array|\WP_Error
	 */
	public function receive_media( array $input ) {
		return $this->receive_media_payload( $input );
	}

	/** Shared relationship preparation/finalization; staged bytes remain server-resolved. */
	private function receive_media_payload( array $input, $verified_file = null, $defer_fields = false ) {
		$authors = array_key_exists( 'media_authors', $input ) ? Sync_Media_Authors::run( null !== $verified_file ? 'prepare_staged' : 'prepare', $input, $verified_file ?? [] ) : null;
		if ( is_wp_error( $authors ) ) {
			return $authors;
		}
		$users = isset( $input['acf_attachment_users'] ) ? Sync_ACF_Media_Users::run( null !== $verified_file ? 'prepare_staged' : 'prepare', $input, $verified_file ?? [] ) : null;
		if ( is_wp_error( $users ) ) {
			return $users;
		}
		if ( null !== $users ) {
			$input = $users['input'];
		}
		$terms = isset( $input['acf_attachment_terms'] ) ? Sync_ACF_Media_Terms::run( null !== $verified_file ? 'prepare_staged' : 'prepare', $input, $verified_file ?? [] ) : null;
		if ( is_wp_error( $terms ) ) {
			return $terms;
		}
		if ( null !== $terms ) {
			$input = $terms['input'];
		}
		$graph = isset( $input['acf_media_fields_version'] ) || isset( $input['acf_graph_hashes'] ) || isset( $input['acf_attachment_media_version'] );
		if ( $graph ) {
			$access = $this->authorize();
			if ( is_wp_error( $access ) || ! function_exists( 'acf_get_field' ) ) {
				return is_wp_error( $access ) ? $access : Migration_Sync_Maps::error();
			}
			$definitions = array_column( ACF_Fields::get_fields_for_content_type( 'media' ), null, 'name' );
			$check       = Sync_ACF_Media_Fields::envelope( $input );
			if ( is_wp_error( $check ) ) {
				return $check;
			}
			$resolved = [];
			foreach ( $input['media'] as &$item ) {
				foreach ( (array) ( $item['acf'] ?? [] ) as $name => $value ) {
					$field = $resolved[ $name ] ?? ( isset( $definitions[ $name ] ) ? Sync_ACF_Media_Fields::definition( $name ) : Migration_Sync_Maps::error() );
					if ( is_wp_error( $field ) ) {
						return $field;
					}
					$resolved[ $name ]    = $field;
					$item['acf'][ $name ] = Sync_ACF_Media_Fields::hold( $value, $field, 0, isset( $input['acf_attachment_media_version'] ) );
					if ( is_wp_error( $item['acf'][ $name ] ) ) {
						return $item['acf'][ $name ];
					}
				}
			}
			unset( $item );
		}
		$result = $this->receive_items( $input, null !== $verified_file, $defer_fields, $verified_file );
		if ( is_array( $result ) && ! empty( $verified_file['transfer_id'] ) ) {
			foreach ( $result['items'] ?? [] as $item ) {
				self::$active_svg_transfers[ $item['attachment_id'] ] = $verified_file['transfer_id'];
			}
		}
		if ( is_array( $result ) && ! empty( $result['pending'] ) ) {
			return $result;
		}
		if ( null !== $authors && ! is_wp_error( $result ) ) {
			$check = Sync_Media_Authors::run( null !== $verified_file ? 'finalize_staged' : 'finalize', $result, $authors );
			if ( is_wp_error( $check ) ) {
				return $check;
			}
		}
		if ( null !== $users && ! is_wp_error( $result ) ) {
			$check = Sync_ACF_Media_Users::run( null !== $verified_file ? 'finalize_staged' : 'finalize', $result, $users );
			if ( is_wp_error( $check ) ) {
				return $check;
			}
			$result['acf_attachment_users_version'] = 1;
		}
		if ( null !== $terms && ! is_wp_error( $result ) ) {
			$check = Sync_ACF_Media_Terms::run( null !== $verified_file ? 'finalize_staged' : 'finalize', $result, $terms );
			if ( is_wp_error( $check ) ) {
				return $check;
			}
			$result['acf_attachment_terms_version'] = 1;
		}
		if ( null !== $authors && ! is_wp_error( $result ) ) {
			$check = Sync_Media_Authors::run( null !== $verified_file ? 'verify_staged' : 'verify', $result, $authors );
			if ( is_wp_error( $check ) ) {
				return $check;
			}
			$result['media_authors_version'] = 1;
		}
		if ( $graph && ! is_wp_error( $result ) ) {
			$result['acf_media_fields_version'] = 1;
			if ( isset( $input['acf_attachment_media_version'] ) ) {
				$result['acf_attachment_media_version'] = 1;
			}
		}
		return $result;
	}

	/**
	 * Receive one validated Content Sync payload, including anonymous external media.
	 *
	 * @param array $input Validated transport input.
	 * @param bool  $defer_fields Server-selected pull mode; apply fields after building the complete map.
	 * @return array|\WP_Error
	 */
	public function receive_file( array $input, $defer_fields = false ) {
		$input['attachment_id'] = $input['source_attachment_id'] ?? 0;
		return $this->receive_items( [ 'media' => [ $input ] ], true, $defer_fields );
	}

	/** Receive an owner-scoped staged file; filesystem paths never come from input. */
	public function receive_staged_file( $transfer_id, array $input, $credential_scope = 'local', $defer_fields = false, array $envelope = [], $bounded_metadata = false ) {
		$access = $this->authorize();
		if ( is_wp_error( $access ) ) {
			return $access; }
		$this->bounded_metadata = (bool) $bounded_metadata;
		$this->metadata_pending = false;
		$envelope_fields        = [ 'media_authors', 'acf_attachment_users', 'acf_attachment_terms', 'acf_media_fields_version', 'acf_graph_hashes', 'acf_attachment_media_version' ];
		if ( array_diff( array_keys( $envelope ), $envelope_fields ) || ( $defer_fields && $envelope ) ) {
			return $this->error( 'invalid_payload', 400 ); }
		$allowed = [ 'source_attachment_id', 'source_origin_attachment_id', 'title', 'alt_text', 'caption', 'description', 'acf', 'force_unique' ];
		if ( array_diff( array_keys( $input ), $allowed ) ) {
			return $this->error( 'invalid_payload', 400 ); }
		if ( ! File_Store::valid_id( $transfer_id ) ) {
			return $this->error( 'invalid_payload', 400 ); }
		$finalize_lock = Job_Lock::acquire_resource( 'media-finalize', $transfer_id );
		if ( ! $finalize_lock || is_wp_error( $finalize_lock ) ) {
			return $this->error( 'import_busy', 409 ); }
		try {
			$completed = Media_Chunk_Store::finalization_result( $transfer_id, $credential_scope );
			if ( is_wp_error( $completed ) ) {
				return $completed; }
			// A confirmed replay needs verified bytes for provider checks, but no sideload copy.
			$temporary = null === $completed;
			$file      = $temporary
				? Media_Chunk_Store::copy_ready( $transfer_id, $credential_scope )
				: Media_Chunk_Store::resolve( $transfer_id, $credential_scope );
			if ( is_wp_error( $file ) ) {
				return $file; }
			try {
				$input['attachment_id'] = $input['source_attachment_id'] ?? 0;
				$input['file_name']     = $file['file_name'];
				$input['file_hash']     = hash_file( 'md5', $file['path'] );
				if ( ! is_scalar( $input['attachment_id'] ) || ! ctype_digit( (string) $input['attachment_id'] ) || ( isset( $input['force_unique'] ) && ! is_bool( $input['force_unique'] ) ) ) {
					return $this->error( 'invalid_payload', 400 ); }
				$valid = $this->validate_received_metadata( $input, array_column( ACF_Fields::get_fields_for_content_type( 'media' ), 'name' ) );
				if ( is_wp_error( $valid ) ) {
					return $valid; }
				$bound = Media_Chunk_Store::bind_finalization(
					$transfer_id,
					[
						'item'         => $input,
						'defer_fields' => (bool) $defer_fields,
						'envelope'     => $envelope,
					],
					$credential_scope
				);
				if ( is_wp_error( $bound ) ) {
					return $bound; }
				$completed = Media_Chunk_Store::finalization_result( $transfer_id, $credential_scope );
				if ( is_wp_error( $completed ) ) {
					return $completed; }
				if ( null !== $completed ) {
					$attachment_id = (int) $completed['items'][0]['attachment_id'];
					if ( 'attachment' !== get_post_type( $attachment_id ) ) {
						return $this->error( 'completed_attachment_missing', 409 ); }
					if ( ! current_user_can( 'read_post', $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
						return $this->error( 'attachment_forbidden', 403 ); }
					// A saved success does not preserve a revoked provider entitlement or mapping permission.
					foreach ( [
						'media_authors'        => Sync_Media_Authors::class,
						'acf_attachment_users' => Sync_ACF_Media_Users::class,
						'acf_attachment_terms' => Sync_ACF_Media_Terms::class,
					] as $key => $provider ) {
						if ( array_key_exists( $key, $envelope ) ) {
							$authorized = $provider::run( 'prepare_staged', [ 'media' => [ $input ] ] + $envelope, $file );
							if ( is_wp_error( $authorized ) ) {
								return $authorized; }
						}
					}
					return $completed;
				}
				$file['transfer_id'] = $transfer_id;
				$result              = $this->receive_media_payload( [ 'media' => [ $input ] ] + $envelope, $file, $defer_fields );
				if ( is_wp_error( $result ) || ! empty( $result['pending'] ) || $result['failed'] ) {
					return $result; }
				return Media_Chunk_Store::finalization_result( $transfer_id, $credential_scope, $result );
			} finally {
				if ( $temporary && is_file( $file['path'] ) ) {
					wp_delete_file( $file['path'] ); }
			}
		} finally {
			self::$active_svg_transfers = [];
			Job_Lock::release( $finalize_lock ); }
	}

	/**
	 * Look up current bytes without mutating metadata or thumbnails.
	 *
	 * @param array $input Validated hash and source identities.
	 * @return int|\WP_Error
	 */
	public function check_file( array $input ) {
		$access = $this->authorize();
		if ( is_wp_error( $access ) ) {
			return $access; }
		$input['attachment_id'] = $input['source_attachment_id'] ?? 0;
		return $this->duplicate( $input, true );
	}

	/**
	 * Apply shared byte validation and destination locking.
	 *
	 * @param array $input Media items.
	 * @param bool  $content_sync Server-selected single-file transport mode.
	 * @param bool  $defer_fields Apply pull metadata after the media map is complete.
	 * @return array|\WP_Error
	 */
	private function receive_items( array $input, $content_sync = false, $defer_fields = false, $verified_file = null ) {
		$access = $this->authorize();
		if ( is_wp_error( $access ) ) {
			return $access; }
		$items = $input['media'] ?? null;
		if ( ! is_array( $items ) || ! $items || count( $items ) > 50 ) {
			return $this->error( 'invalid_selection', 400 ); }
		$seen   = [];
		$total  = 0;
		$fields = array_column( ACF_Fields::get_fields_for_content_type( 'media' ), 'name' );
		foreach ( $items as &$item ) {
			if ( ! is_array( $item ) || ! isset( $item['attachment_id'] ) || ! is_scalar( $item['attachment_id'] ) || ! ctype_digit( (string) $item['attachment_id'] ) || (int) $item['attachment_id'] < ( $content_sync ? 0 : 1 ) || isset( $seen[ (int) $item['attachment_id'] ] ) ) {
				return $this->error( 'invalid_selection', 400 ); }
			$seen[ (int) $item['attachment_id'] ] = true;
			if ( null === $verified_file ) {
				if ( ! isset( $item['file_name'], $item['file_data'], $item['file_hash'] ) || ! is_string( $item['file_name'] ) || ! is_string( $item['file_data'] ) || ! is_string( $item['file_hash'] ) || ! preg_match( '/^[a-f0-9]{32}$/iD', $item['file_hash'] ) || strlen( $item['file_data'] ) > 4 * ceil( self::FILE_LIMIT / 3 ) ) {
					return $this->error( 'invalid_payload', 400 ); }
				$bytes  = base64_decode( $item['file_data'], true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Strict binary media decoding.
				$size   = strlen( (string) $bytes );
				$total += $size;
				if ( false === $bytes || ! $size || $size > self::FILE_LIMIT || $total > self::BATCH_LIMIT || ! hash_equals( strtolower( $item['file_hash'] ), md5( $bytes ) ) ) {
					return $this->error( 'invalid_file', 400 ); }
				$item['file_hash'] = md5( $bytes );
			}
			$valid_metadata = $this->validate_received_metadata( $item, $fields );
			if ( is_wp_error( $valid_metadata ) ) {
				return $valid_metadata; }
		}
		unset( $item, $bytes );
		// Share the folder import lock so both media entry points cannot create the same indexed duplicate concurrently.
		$lock = Job_Lock::acquire_resource( 'media', 'folder-import' );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return $this->error( 'import_busy', 409 ); }
		try {
			$recovered = ! empty( $verified_file['transfer_id'] ) ? $this->recover_staged_attachment( $verified_file['transfer_id'], $items[0], $verified_file ) : 0;
			if ( is_wp_error( $recovered ) ) {
				return $recovered; }
			if ( $this->metadata_pending ) {
				return [ 'pending' => true ];
			}
			foreach ( $items as $item ) {
				$existing = $recovered ?: $this->duplicate( $item, $content_sync, $verified_file );
				if ( is_wp_error( $existing ) ) {
					return $existing; }
			}
			$result = [
				'created' => 0,
				'skipped' => 0,
				'failed'  => 0,
				'items'   => [],
			];
			foreach ( $items as $item ) {
				$id     = 0;
				$status = 'failed';
				$code   = '';
				try {
					$existing = $recovered ?: $this->duplicate( $item, $content_sync, $verified_file );
					if ( is_wp_error( $existing ) ) {
						$code = $existing->get_error_code(); } else {
						$id = $existing ? $existing : ( null === $verified_file ? $this->create_attachment( $item ) : $this->sideload_staged_file( $verified_file['path'], $item, $verified_file['transfer_id'] ?? '' ) );
						if ( is_wp_error( $id ) ) {
							$id   = 0;
							$code = 'import_failed'; } else {
							if ( $this->metadata_pending ) {
								return [ 'pending' => true ];
							}
							$this->record_source_ids( $id, $item );
							if ( $existing && $content_sync && ! $recovered ) {
								$before_metadata = wp_get_attachment_metadata( $id );
								$size_filter     = $this->image_size_filter();
								add_filter( 'intermediate_image_sizes_advanced', $size_filter, PHP_INT_MAX );
								add_filter( 'wp_get_missing_image_subsizes', $size_filter, PHP_INT_MAX );
								try {
									\RockStarLab\ImportExport\Helper\Content_Sync_Media::ensure_image_sizes( $id );
								} finally {
									remove_filter( 'intermediate_image_sizes_advanced', $size_filter, PHP_INT_MAX );
									remove_filter( 'wp_get_missing_image_subsizes', $size_filter, PHP_INT_MAX );
								}
								if ( $this->pending_image_sizes( $id ) ) {
									if ( $before_metadata === wp_get_attachment_metadata( $id ) ) {
										return $this->error( 'image_size_failed', 500 );
									}
									return [ 'pending' => true ];
								}
							}
							foreach ( $defer_fields ? [] : ( $item['acf'] ?? [] ) as $name => $value ) {
								ACF_Fields::import_value( 'media', $id, $name, $value ); }
							// Continuing this transfer's own attachment is still a creation, not a duplicate.
							$status = $existing && ! $recovered ? 'skipped' : 'created';
							}
						}
				} catch ( \Throwable $exception ) {
					$code = 'import_interrupted'; }
				++$result[ $status ];
				$result['items'][] = [
					'source_id'     => (int) $item['attachment_id'],
					'attachment_id' => (int) $id,
					'status'        => $status,
					'error_code'    => $code,
				];
			}
			return $result;
		} finally {
			Job_Lock::release( $lock ); }
	}

	/** Shared metadata rules for inline and staged media receivers. */
	private function validate_received_metadata( array $item, array $fields ) {
		$name = sanitize_file_name( $item['file_name'] );
		if ( '' === $name || $name !== $item['file_name'] || ! wp_check_filetype( $name, Sync_SVG::mimes() )['type'] ) {
			return $this->error( 'invalid_file_type', 400 ); }
		foreach ( [ 'title', 'alt_text', 'caption', 'description' ] as $key ) {
			if ( isset( $item[ $key ] ) && ! is_string( $item[ $key ] ) ) {
				return $this->error( 'invalid_payload', 400 ); }
		}
		if ( isset( $item['source_origin_attachment_id'] ) && ( ! is_scalar( $item['source_origin_attachment_id'] ) || ! ctype_digit( (string) $item['source_origin_attachment_id'] ) ) ) {
			return $this->error( 'invalid_payload', 400 ); }
		$acf = $item['acf'] ?? [];
		if ( ! is_array( $acf ) || array_diff( array_keys( $acf ), $fields ) ) {
			return $this->error( 'unknown_acf_fields', 400 ); }
		return true;
	}

	/**
	 * Walk indexed candidates without changing stale entries or scanning unindexed files.
	 *
	 * @param array $item Validated incoming file.
	 * @param bool  $content_sync Resolve source identities before hash candidates.
	 * @return int|\WP_Error Existing attachment or zero.
	 */
	private function duplicate( array $item, $content_sync = false, $verified_file = null ) {
		$excluded  = [];
		$algorithm = isset( $item['file_data'] ) ? 'sha256' : 'md5';
		$digest    = isset( $item['file_data'] ) ? hash( 'sha256', base64_decode( $item['file_data'], true ) ) : strtolower( $item['file_hash'] ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Validated binary media transport.
		// Only server-resolved staging metadata may bypass the inline byte bound.
		$expected_size = null;
		if ( is_array( $verified_file ) ) {
			$algorithm     = 'sha256';
			$digest        = $verified_file['sha256'];
			$expected_size = $verified_file['size'];
		}
		if ( $content_sync ) {
			$candidates = ! empty( $item['source_origin_attachment_id'] ) ? [ (int) $item['source_origin_attachment_id'] ] : [];
			if ( ! empty( $item['attachment_id'] ) ) {
				$candidates = array_merge(
					$candidates,
					get_posts(
						[
							'post_type'      => 'attachment',
							'post_status'    => 'inherit',
							'posts_per_page' => 10,
							'fields'         => 'ids',
							'orderby'        => 'ID',
							'order'          => 'ASC',
							'meta_query'     => [
								'relation' => 'OR',
								[
									'key'   => '_rsl_ie_original_attachment_id',
									'value' => $item['attachment_id'],
								],
								[
									'key'   => '_rsl_ie_source_attachment_id',
									'value' => $item['attachment_id'],
								],
							], // phpcs:ignore WordPress.DB.SlowDBQuery -- Bounded source identity lookup.
						]
					)
				);
			}
			foreach ( array_unique( $candidates ) as $id ) {
				$match = $this->candidate( (int) $id, $algorithm, $digest, $expected_size );
				if ( is_wp_error( $match ) || $match ) {
					return $match; }
				$excluded[] = (int) $id;
			}
			if ( ! empty( $item['force_unique'] ) ) {
				return 0; }
		}
		for ( $attempt = 0; $attempt < 10; ++$attempt ) {
			$id = Media_Hash::find_indexed_attachment( $item['file_hash'], $excluded );
			if ( ! $id && is_array( $verified_file ) ) {
				$sanitized = $this->sanitized_svg( $verified_file['path'], $item['file_name'] ?? '' );
				if ( is_string( $sanitized ) ) {
					$id = Media_Hash::find_indexed_attachment( md5( $sanitized ) );
					if ( $id ) { return $this->candidate( $id, 'sha256', hash( 'sha256', $sanitized ), strlen( $sanitized ) ); }
				}
			}
			if ( ! $id ) { return 0; }
			$match = $this->candidate( $id, $algorithm, $digest, $expected_size );
			if ( is_wp_error( $match ) || $match ) {
				return $match; }
			$excluded[] = $id;
		}
		return $this->error( 'index_refresh_required', 409 );
	}

	/**
	 * Verify an authorized, bounded uploads candidate against current bytes.
	 *
	 * @param int    $id Candidate attachment.
	 * @param string $algorithm Hash algorithm.
	 * @param string $digest Expected digest.
	 * @return int|\WP_Error
	 */
	private function candidate( $id, $algorithm, $digest, $expected_size = null ) {
		if ( 'attachment' !== get_post_type( $id ) ) {
			return 0; }
		if ( ! current_user_can( 'edit_post', $id ) || ! current_user_can( 'read_post', $id ) ) {
			return $this->error( 'attachment_forbidden', 403 ); }
		$path = $this->file( $id );
		if ( is_wp_error( $path ) ) {
			return 0; }
		clearstatcache( true, $path );
		$size       = filesize( $path );
		$valid_size = null === $expected_size ? $size <= self::FILE_LIMIT : $size === $expected_size;
		return $valid_size && hash_equals( $digest, (string) hash_file( $algorithm, $path ) ) ? (int) $id : 0;
	}

	/**
	 * Describe current attachment bytes for a confirmed ACF reference.
	 *
	 * @param int $id Local attachment ID.
	 * @return array|\WP_Error
	 */
	public function reference_proof( $id, $chunked = false, $original = false ) {
		if ( ! is_int( $id ) || $id < 1 || 'attachment' !== get_post_type( $id ) || ! current_user_can( 'upload_files' ) || ! current_user_can( 'edit_post', $id ) || ! current_user_can( 'read_post', $id ) ) {
			return $this->error( 'attachment_forbidden', 403 );
		}
		$path = $this->file( $id );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		if ( $original && wp_attachment_is_image( $id ) ) {
			$original_path = wp_get_original_image_path( $id );
			$base          = realpath( wp_get_upload_dir()['basedir'] );
			$real          = is_string( $original_path ) ? realpath( $original_path ) : false;
			if ( ! $base || ! $real || 0 !== strpos( $real, trailingslashit( $base ) ) || ! is_file( $real ) || ! is_readable( $real ) ) {
				return $this->error( 'file_unavailable', 400 ); }
			$path = $real;
		}

		clearstatcache( true, $path );
		$size = filesize( $path );
		$mime = get_post_mime_type( $id );
		// WordPress's raster image detector does not recognize SVG files.
		$detected = $mime;
		if ( 0 === strpos( $mime, 'image/' ) ) {
			$detected = 'image/svg+xml' === $mime ? ( new \finfo( FILEINFO_MIME_TYPE ) )->file( $path ) : wp_get_image_mime( $path );
		}
		if ( ! $size || ( ! $chunked && $size > self::FILE_LIMIT ) || ! $mime || ( 0 === strpos( $mime, 'image/' ) && $detected !== $mime ) ) {
			return $this->error( 'file_unavailable', 400 );
		}
		$hash = hash_file( 'sha256', $path );
		if ( ! $hash ) {
			return $this->error( 'file_unavailable', 400 );
		}
		if ( $original && 'image/svg+xml' === $mime ) {
			$slug = (string) get_post_field( 'post_name', $id );
			$transfer = self::$active_svg_transfers[ $id ] ?? ( 0 === strpos( $slug, 'rsl-ie-file_' ) ? substr( $slug, 7 ) : '' );
			$record = File_Store::valid_id( $transfer ) ? get_option( 'rsl_ie_media_chunk_' . $transfer ) : null;
			if ( is_array( $record ) && (int) $record['owner'] === get_current_user_id() && (int) $record['blog'] === get_current_blog_id() ) {
				$dir = File_Store::directory( $transfer );
				$source = is_wp_error( $dir ) ? '' : $dir . '/media.part';
				if ( is_file( $source ) && ! is_link( $source ) && filesize( $source ) === $record['size'] && hash_file( 'sha256', $source ) === $record['sha256'] ) {
					$sanitized = $this->sanitized_svg( $source, $record['name'] );
					if ( is_string( $sanitized ) && strlen( $sanitized ) === $size && hash( 'sha256', $sanitized ) === $hash ) {
						return [ 'sha256' => $record['sha256'], 'size' => $record['size'], 'mime' => $mime ];
					}
				}
			}
		}
		return [
			'sha256' => $hash,
			'size'   => $size,
			'mime'   => $mime,
		];
	}

	/** Reproduce the installed SVG upload filters on a disposable copy. */
	private function sanitized_svg( $path, $name ) {
		if ( 'svg' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) || ! is_file( $path ) ) { return false; }
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$tmp = wp_tempnam( $name );
		if ( ! $tmp ) { return false; }
		try {
			if ( ! copy( $path, $tmp ) || ! Sync_SVG::sanitize( $tmp, $name ) ) { return false; }
			$file = apply_filters( 'wp_handle_sideload_prefilter', [ 'name' => $name, 'tmp_name' => $tmp, 'type' => 'image/svg+xml', 'size' => filesize( $tmp ), 'error' => 0 ] );
			return empty( $file['error'] ) && ( $file['tmp_name'] ?? '' ) === $tmp && is_file( $tmp ) ? file_get_contents( $tmp ) : false;
		} finally {
			if ( is_file( $tmp ) ) { wp_delete_file( $tmp ); }
		}
	}

	/**
	 * Create an attachment through the normal WordPress sideload pipeline.
	 *
	 * @param array $item Prevalidated payload.
	 * @return int|\WP_Error
	 */
	private function create_attachment( array $item ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$tmp = wp_tempnam( $item['file_name'] );
		if ( ! $tmp ) {
			return $this->error( 'import_failed', 500 ); }
		try {
			$stream = fopen( $tmp, 'wb' );
			if ( ! $stream ) {
				return $this->error( 'import_failed', 500 ); }
			try {
				// Input was validated before sideloading. Split on base64 quartet
				// boundaries so staging does not allocate another full binary copy.
				$pending = '';
				for ( $offset = 0, $length = strlen( $item['file_data'] ); $offset < $length; $offset += 65536 ) {
					$pending .= preg_replace( '/\s+/', '', substr( $item['file_data'], $offset, 65536 ) );
					$take     = $offset + 65536 >= $length ? strlen( $pending ) : intdiv( strlen( $pending ), 4 ) * 4;
					$bytes    = base64_decode( substr( $pending, 0, $take ), true );
					$pending  = substr( $pending, $take );
					if ( false === $bytes || fwrite( $stream, $bytes ) !== strlen( $bytes ) ) {
						return $this->error( 'import_failed', 500 ); }
				}
			} finally {
				fclose( $stream );
			}
			return $this->sideload_staged_file( $tmp, $item );
		} finally {
			if ( is_file( $tmp ) ) {
				wp_delete_file( $tmp ); }
		}
	}

	/**
	 * Recover a journalled move or the attachment's initial INSERT.
	 *
	 * @param string $transfer_id Private transfer identifier.
	 * @param array  $item Validated attachment metadata.
	 * @param array  $verified_file Independently verified staged bytes.
	 * @return int|\WP_Error Existing/new attachment ID, zero before any move, or error.
	 */
	private function recover_staged_attachment( $transfer_id, array $item, array $verified_file ) {
		global $wpdb;
		$id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM %i WHERE post_type = 'attachment' AND post_name = %s ORDER BY ID LIMIT 1", $wpdb->posts, 'rsl-ie-' . $transfer_id ) );
		if ( $wpdb->last_error ) {
			return $this->error( 'staged_attachment_incomplete', 409 );
		}
		if ( ! $id ) {
			$dir = File_Store::directory( $transfer_id );
			if ( is_wp_error( $dir ) ) {
				return $dir; }
			$journal = $dir . '/media-move.json';
			if ( is_link( $journal ) ) {
				return $this->error( 'staged_attachment_incomplete', 409 ); }
			if ( ! is_file( $journal ) ) {
				return 0; }
			$move    = json_decode( file_get_contents( $journal ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Private local recovery journal.
			$uploads = wp_get_upload_dir();
			$base    = realpath( $uploads['basedir'] );
			$path    = is_array( $move ) && is_string( $move['path'] ?? null ) ? $move['path'] : '';
			if ( ! $base || ! $path || is_link( $path ) || 0 !== strpos( $path, trailingslashit( $base ) ) ) {
				return $this->error( 'staged_attachment_incomplete', 409 ); }
			if ( ! file_exists( $path ) ) {
				return 0; }
			clearstatcache( true, $path );
			if ( realpath( $path ) !== $path || ! is_file( $path ) || filesize( $path ) !== $verified_file['size'] || ! hash_equals( $verified_file['sha256'], (string) hash_file( 'sha256', $path ) ) ) {
				return $this->error( 'staged_attachment_incomplete', 409 ); }
			$type = wp_check_filetype_and_ext( $path, $item['file_name'], Sync_SVG::mimes() );
			if ( empty( $type['type'] ) ) {
				return $this->error( 'invalid_file_type', 400 ); }
			$id = wp_insert_attachment(
				[
					'post_name'      => 'rsl-ie-' . $transfer_id,
					'post_title'     => sanitize_text_field( $item['title'] ?? '' ),
					'post_excerpt'   => wp_kses_post( $item['caption'] ?? '' ),
					'post_content'   => wp_kses_post( $item['description'] ?? '' ),
					'post_mime_type' => $type['type'],
					'guid'           => trailingslashit( $uploads['baseurl'] ) . substr( $path, strlen( trailingslashit( $base ) ) ),
				],
				$path,
				0,
				true
			);
			if ( is_wp_error( $id ) ) {
				return $id; }
		}
		if ( 'trash' === get_post_status( $id ) || ! current_user_can( 'read_post', $id ) || ! current_user_can( 'edit_post', $id ) ) {
			return $this->error( 'attachment_forbidden', 403 ); }
		$path = $this->file( $id );
		if ( is_wp_error( $path ) && ! get_post_meta( $id, '_wp_attached_file', true ) ) {
			// The GUID is inserted with the attachment row, before attached-file meta.
			// Recover only this site's uploads URL and independently verified bytes.
			$uploads   = wp_get_upload_dir();
			$prefix    = trailingslashit( $uploads['baseurl'] );
			$url       = get_post_field( 'guid', $id, 'raw' );
			$base      = realpath( $uploads['basedir'] );
			$relative  = is_string( $url ) && 0 === strpos( $url, $prefix ) ? rawurldecode( substr( $url, strlen( $prefix ) ) ) : '';
			$candidate = $base && '' !== $relative && false === strpos( $relative, "\0" ) ? realpath( trailingslashit( $base ) . $relative ) : false;
			if ( $candidate && 0 === strpos( $candidate, trailingslashit( $base ) ) && is_file( $candidate ) && is_readable( $candidate ) ) {
				clearstatcache( true, $candidate );
				if ( filesize( $candidate ) === $verified_file['size'] && hash_equals( $verified_file['sha256'], (string) hash_file( 'sha256', $candidate ) ) ) {
					update_attached_file( $id, $candidate );
					$path = $this->file( $id );
				}
			}
		}
		if ( is_wp_error( $path ) ) {
			return $this->error( 'staged_attachment_incomplete', 409 ); }
		if ( get_post_meta( $id, '_rsl_ie_chunk_sideload_complete', true ) !== $transfer_id ) {
			require_once ABSPATH . 'wp-admin/includes/image.php';
			$before_sizes = wp_get_missing_image_subsizes( $id );
			$size_filter  = $this->image_size_filter();
			add_filter( 'intermediate_image_sizes_advanced', $size_filter, PHP_INT_MAX );
			add_filter( 'wp_get_missing_image_subsizes', $size_filter, PHP_INT_MAX );
			try {
				$saved_metadata = wp_get_attachment_metadata( $id );
				$metadata       = wp_attachment_is_image( $id ) && is_array( $saved_metadata ) && ! empty( $saved_metadata['file'] )
					? wp_update_image_subsizes( $id ) : wp_generate_attachment_metadata( $id, $path );
			} finally {
				remove_filter( 'intermediate_image_sizes_advanced', $size_filter, PHP_INT_MAX );
				remove_filter( 'wp_get_missing_image_subsizes', $size_filter, PHP_INT_MAX );
			}
			if ( is_wp_error( $metadata ) ) {
				return $metadata;
			}
			wp_update_attachment_metadata( $id, $metadata );
			if ( $this->pending_image_sizes( $id ) ) {
				if ( $before_sizes === wp_get_missing_image_subsizes( $id ) ) {
					return $this->error( 'image_size_failed', 500 );
				}
				return $id;
			}
			update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $item['alt_text'] ?? '' ) );
			$path = $this->file( $id );
			if ( is_wp_error( $path ) ) {
				return $path; }
			Media_Hash::store_attachment_hash( $id, md5_file( $path ), $path );
			update_post_meta( $id, '_rsl_ie_chunk_sideload_complete', $transfer_id );
		}
		return $id;
	}

	/**
	 * Import a server-resolved temporary file through the standard media pipeline.
	 *
	 * @param string $tmp Verified temporary path.
	 * @param array  $item Validated attachment metadata.
	 * @param string $transfer_id Optional resumable transfer identifier.
	 * @return int|\WP_Error Attachment identifier or import error.
	 */
	private function sideload_staged_file( $tmp, array $item, $transfer_id = '' ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';
		if ( ! Sync_SVG::sanitize( $tmp, $item['file_name'] ) ) {
			return $this->error( 'invalid_svg', 400 );
		}
		$type = wp_check_filetype_and_ext( $tmp, $item['file_name'], Sync_SVG::mimes() );
		if ( ! $type['ext'] || ! $type['type'] ) {
			return $this->error( 'invalid_file_type', 400 ); }
		$post_data = [
			'post_excerpt' => wp_kses_post( $item['caption'] ?? '' ),
			'post_content' => wp_kses_post( $item['description'] ?? '' ),
		];
		if ( $transfer_id ) {
			$post_data['post_name'] = 'rsl-ie-' . $transfer_id; }
		$journal_move = static function ( $move, $file, $destination ) use ( $tmp, $transfer_id ) {
			if ( ! $transfer_id || ( $file['tmp_name'] ?? '' ) !== $tmp ) {
				return $move; }
			$dir = File_Store::directory( $transfer_id );
			if ( is_wp_error( $dir ) ) {
				throw new \RuntimeException( 'Cannot persist media move.' ); }
			$journal = $dir . '/media-move.json';
			$json    = wp_json_encode( [ 'path' => $destination ] );
			// phpcs:ignore WordPress.WP.AlternativeFunctions -- Atomic local journal publication must precede WordPress moving the file.
			if ( is_link( $journal ) || is_link( $journal . '.tmp' ) || false === $json || file_put_contents( $journal . '.tmp', $json, LOCK_EX ) !== strlen( $json ) || ! rename( $journal . '.tmp', $journal ) ) {
				throw new \RuntimeException( 'Cannot persist media move.' ); }
			return $move;
		};
		$sync_mimes = Sync_SVG::mimes();
		$mime_filter = static function ( $mimes ) use ( $sync_mimes ) { return $mimes + $sync_mimes; };
		add_filter( 'upload_mimes', $mime_filter );
		add_filter( 'pre_move_uploaded_file', $journal_move, PHP_INT_MAX, 3 );
		$size_filter = $this->image_size_filter();
		add_filter( 'intermediate_image_sizes_advanced', $size_filter, PHP_INT_MAX );
		add_filter( 'wp_get_missing_image_subsizes', $size_filter, PHP_INT_MAX );
		try {
			$id = media_handle_sideload(
				[
					'name'     => $item['file_name'],
					'tmp_name' => $tmp,
					'type'     => $type['type'],
				],
				0,
				sanitize_text_field( $item['title'] ?? '' ),
				$post_data
			);
		} finally {
			remove_filter( 'upload_mimes', $mime_filter );
			remove_filter( 'pre_move_uploaded_file', $journal_move, PHP_INT_MAX );
			remove_filter( 'intermediate_image_sizes_advanced', $size_filter, PHP_INT_MAX );
			remove_filter( 'wp_get_missing_image_subsizes', $size_filter, PHP_INT_MAX );
		}
		if ( is_wp_error( $id ) ) {
			return $id; }
		update_post_meta( $id, '_wp_attachment_image_alt', sanitize_text_field( $item['alt_text'] ?? '' ) );
		$path = get_attached_file( $id );
		// WordPress image processing may scale or rotate the sideloaded file. Index the resulting bytes.
		Media_Hash::store_attachment_hash( $id, md5_file( $path ), $path );
		if ( $transfer_id && ! $this->pending_image_sizes( $id ) ) {
			update_post_meta( $id, '_rsl_ie_chunk_sideload_complete', $transfer_id ); }
		return (int) $id;
	}

	/** WordPress persists every generated size; the next request asks for missing sizes. */
	private function image_size_filter() {
		return function ( $sizes ) {
			return $this->bounded_metadata ? array_slice( $sizes, 0, 1, true ) : $sizes;
		};
	}

	private function pending_image_sizes( $id ) {
		// SVG metadata can contain dimensions, but WordPress does not generate raster subsizes for it.
		$this->metadata_pending = $this->bounded_metadata && 'image/svg+xml' !== get_post_mime_type( $id ) && wp_attachment_is_image( $id ) && (bool) wp_get_missing_image_subsizes( $id );
		return $this->metadata_pending;
	}

	/**
	 * Preserve all source identities across repeated and reverse transfers.
	 *
	 * @param int   $id Destination attachment.
	 * @param array $item Source payload.
	 * @return void
	 */
	private function record_source_ids( $id, array $item ) {
		foreach ( [
			'_rsl_ie_original_attachment_id'      => $item['attachment_id'],
			'_rsl_ie_source_attachment_id'        => $item['attachment_id'],
			'_rsl_ie_source_origin_attachment_id' => $item['source_origin_attachment_id'] ?? 0,
		] as $key => $source ) {
			$source = (int) $source;
			if ( $source && ! in_array( $source, array_map( 'intval', get_post_meta( $id, $key, false ) ), true ) ) {
				add_post_meta( $id, $key, $source, false ); }
		}
	}

	/**
	 * Resolve readable files strictly inside real uploads, including symlinks.
	 *
	 * @param int $id Attachment ID.
	 * @return string|\WP_Error
	 */
	private function file( $id ) {
		$base = realpath( wp_get_upload_dir()['basedir'] );
		$path = get_attached_file( $id );
		$real = is_string( $path ) && '' !== $path ? realpath( $path ) : false;
		return $base && $real && 0 === strpos( $real, trailingslashit( $base ) ) && is_file( $real ) && is_readable( $real ) ? $real : $this->error( 'file_unavailable', 400 );
	}

	/**
	 * Require complete, internally consistent per-source acknowledgements.
	 *
	 * @param array $result Remote result.
	 * @param array $ids Requested sources.
	 * @return bool
	 */
	private function valid_result( array $result, array $ids ) {
		if ( ! Standalone_Sync_Policy::selection( $result['items'], $ids, 'source_id' ) ) {
			return false; }
		$counts = [
			'created' => 0,
			'skipped' => 0,
			'failed'  => 0,
		];
		foreach ( $result['items'] as $item ) {
			if ( 'failed' !== $item['status'] && ( ! $item['attachment_id'] || '' !== $item['error_code'] ) ) {
				return false; }
			if ( 'failed' === $item['status'] && '' === $item['error_code'] ) {
				return false; }
			++$counts[ $item['status'] ];
		}
		return $counts['created'] === $result['created'] && $counts['skipped'] === $result['skipped'] && $counts['failed'] === $result['failed'];
	}

	/** Verify the connected peer before starting a resumable media transfer. */
	public function negotiate_chunks( $site_id, $direction, $fingerprint ) {
		$data = $this->chunk_request( $site_id, $direction, $fingerprint, [ 'action' => 'capabilities' ] );
		if ( is_wp_error( $data ) ) {
			return $data; }
		$required = [
			'chunk_bytes'               => Media_Chunk_Store::CHUNK_BYTES,
			'snapshot_version'          => 1,
			'bounded_snapshot'          => true,
			'snapshot_metadata_binding' => true,
			'idempotent_begin'          => true,
			'idempotent_finalize'       => true,
		];
		foreach ( $required as $name => $value ) {
			if ( ( $data[ $name ] ?? null ) !== $value ) {
				return $this->error( 'peer_upgrade_required', 502 ); }
		}
		return $required + [ 'bounded_metadata' => true === ( $data['bounded_metadata'] ?? false ), 'begin_data' => true === ( $data['begin_data'] ?? false ), 'begin_reuse' => true === ( $data['begin_reuse'] ?? false ) ];
	}

	/** One bounded peer step, rechecking the saved connection on every request. */
	public function chunk_request( $site_id, $direction, $fingerprint, array $payload ) {
		$action = $payload['action'] ?? null;
		$limits = [
			'capabilities'     => 8192,
			'begin'            => 8192,
			'append'           => 8192,
			'status'           => 8192,
			'discard'          => 8192,
			'snapshot'         => 8192,
			'advance-snapshot' => 8192,
			'discard-snapshot' => 8192,
			'read-snapshot'    => 2097152,
			'prepare-snapshot' => 0,
			'finalize'         => 2097152,
		];
		if ( ! is_string( $action ) || ! isset( $limits[ $action ] ) ) {
			return $this->error( 'invalid_payload', 400 ); }
		$limit  = $limits[ $action ];
		$access = $this->authorize();
		if ( is_wp_error( $access ) ) {
			return $access; }
		if ( ! is_int( $site_id ) || $site_id < 1 || ! in_array( $direction, [ 'push', 'pull' ], true ) || ! is_string( $fingerprint ) || ! preg_match( '/^[a-f0-9]{64}$/D', $fingerprint ) ) {
			return $this->error( 'invalid_payload', 400 ); }
		// Content Sync already owns this connection for the whole operation.
		$owned = Job_Lock::holds_resource( 'connection', $site_id );
		$lock  = $owned ? true : Job_Lock::acquire_resource( 'connection', $site_id );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return $this->error( 'connection_busy', 409 ); }
		try {
			$site = Connected_Site::get_by_id( $site_id );
			if ( ! $site || 'active' !== $site['status'] || ! in_array( $site['direction'], [ $direction, 'bidirectional' ], true ) || ! hash_equals( $fingerprint, ( new Sync_Job_Service() )->fingerprint( $site ) ) ) {
				return $this->error( 'connection_unavailable', 409 ); }
			$guard = apply_filters( 'rsl_ie_sync_connection_guard', true, $site );
			if ( is_wp_error( $guard ) ) {
				return $guard; }
			$response = Remote_API::post(
				$site['remote_url'],
				'media-chunk',
				[
					'timeout'             => 30,
					'limit_response_size' => $limit,
					'headers'             => [
						'Authorization' => 'Bearer ' . $site['api_key'],
						'Content-Type'  => 'application/json',
					],
					'body'                => wp_json_encode( $payload ),
				]
			);
			if ( is_wp_error( $response ) ) {
				return $this->error( 'peer_unavailable', 502 ); }
			$data = json_decode( wp_remote_retrieve_body( $response ), true );
			if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) && is_array( $data ) && 'rsl_ie_media_library_file_unavailable' === ( $data['code'] ?? '' ) ) {
				// The other site has the attachment but not its file: report this one file.
				return $this->error( 'file_unavailable', 404 ); }
			if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) || ! is_array( $data ) || true !== ( $data['success'] ?? null ) || 1 !== ( $data['chunk_contract_version'] ?? null ) || ! array_key_exists( 'result', $data ) ) {
				return $this->error( 'peer_rejected', 502 ); }
			if ( in_array( $action, [ 'discard', 'discard-snapshot' ], true ) ) {
				return true === $data['result'] ? true : $this->error( 'invalid_peer_response', 502 );
			}
			return is_array( $data['result'] ) ? $data['result'] : $this->error( 'invalid_peer_response', 502 );
		} finally {
			if ( ! $owned ) {
				Job_Lock::release( $lock );
			}
		}
	}

	/**
	 * Peer POST with bounded response; never echo credentials or remote error text.
	 *
	 * @param array  $site Stored connection.
	 * @param string $endpoint Server-selected route.
	 * @param array  $payload Parsed data.
	 * @return array|\WP_Error
	 */
	private function remote( array $site, $endpoint, array $payload ) {
		$response = Remote_API::post(
			$site['remote_url'],
			$endpoint,
			[
				'timeout'             => 180,
				'limit_response_size' => 64000000,
				'headers'             => [
					'Authorization' => 'Bearer ' . $site['api_key'],
					'Content-Type'  => 'application/json',
				],
				'body'                => wp_json_encode( $payload ),
			]
		);
		if ( is_wp_error( $response ) ) {
			return $this->error( 'peer_unavailable', 502 ); }
		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( 200 !== (int) wp_remote_retrieve_response_code( $response ) || ! is_array( $data ) || empty( $data['success'] ) ) {
			return $this->error( 'peer_rejected', 502 ); }
		if ( 1 !== ( $data['media_contract_version'] ?? null ) ) {
			return $this->error( 'peer_upgrade_required', 502 ); }
		return $data;
	}

	/**
	 * Stable safe failures; interrupted transfers may already have changed data.
	 *
	 * @param string $code Error suffix.
	 * @param int    $status HTTP status.
	 * @return \WP_Error
	 */
	private function error( $code, $status ) {
		return new \WP_Error( 'rsl_ie_media_library_' . $code, __( 'Media synchronization could not complete. Check selection, limits, registered ACF fields, connection and permissions. Inspect the destination before repeating a transfer.', 'import-export-by-rockstarlab' ), [ 'status' => $status ] );
	}
}

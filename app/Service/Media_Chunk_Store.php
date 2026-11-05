<?php
/** Private, owner-scoped binary staging for resumable media transport. */
namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
defined( 'ABSPATH' ) || exit;

class Media_Chunk_Store {
	const CHUNK_BYTES = 1048576;

	private static function access() {
		$access = Access_Policy::check_management();
		return is_wp_error( $access ) ? $access : ( current_user_can( 'upload_files' ) ? true : File_Store::error( 'forbidden', 403 ) );
	}

	public static function begin( $name, $size, $sha256, $credential_scope = 'local', $request_id = null ) {
		if ( null === $request_id ) {
			return self::create_transfer( $name, $size, $sha256, $credential_scope ); }
		$access = self::access();
		if ( is_wp_error( $access ) ) {
			return $access; }
		if ( ! is_string( $request_id ) || ! preg_match( '/^[A-Za-z0-9_-]{16,128}$/D', $request_id ) ) {
			return File_Store::error( 'invalid_request_id' ); }
		$identity = wp_json_encode( [ get_current_blog_id(), get_current_user_id(), $credential_scope, $request_id ] );
		$id       = 'file_' . substr( hash_hmac( 'sha256', $identity, wp_salt( 'auth' ) ), 0, 32 );
		$lock     = Job_Lock::acquire_resource( 'media-chunk', $id );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return File_Store::error( 'busy', 409 ); }
		try {
			wp_cache_delete( 'rsl_ie_media_chunk_' . $id, 'options' );
			if ( false !== get_option( 'rsl_ie_media_chunk_' . $id, false ) ) {
				$r = self::record( $id, $credential_scope );
				if ( is_wp_error( $r ) ) {
					return $r; }
				return $r['name'] === $name && $r['size'] === $size && $r['sha256'] === $sha256 ? self::describe( $r ) : File_Store::error( 'request_conflict', 409 );
			}
			return self::create_transfer( $name, $size, $sha256, $credential_scope, $id );
		} finally {
			Job_Lock::release( $lock ); }
	}

	/** Seed private staging from a verified local duplicate instead of retransmitting bytes. */
	public static function reuse_attachment( $id, $md5, $scope = 'local' ) {
		$r = self::record( $id, $scope );
		if ( is_wp_error( $r ) ) { return $r; }
		$attachment = ( new Media_Library_Sync_Service() )->check_file( [ 'file_hash' => $md5, 'source_attachment_id' => 0 ] );
		if ( is_wp_error( $attachment ) ) { return $attachment; }
		if ( ! $attachment || $r['offset'] || $r['ready'] ) { return self::describe( $r ); }
		$proof = ( new Media_Library_Sync_Service() )->reference_proof( $attachment, true );
		if ( is_wp_error( $proof ) || $proof['sha256'] !== $r['sha256'] || $proof['size'] !== $r['size'] ) { return self::describe( $r ); }
		$stream = fopen( get_attached_file( $attachment ), 'rb' );
		if ( ! $stream ) { return self::describe( $r ); }
		try {
			$result = self::describe( $r );
			$deadline = microtime( true ) + 4;
			while ( ! $result['ready'] ) {
				$bytes = fread( $stream, self::CHUNK_BYTES );
				if ( false === $bytes || '' === $bytes ) { return File_Store::error( 'chunk_integrity', 409 ); }
				$result = self::append( $id, $result['offset'], $bytes, $scope );
				if ( is_wp_error( $result ) ) { return $result; }
				if ( microtime( true ) >= $deadline ) { break; }
			}
			return $result;
		} finally { fclose( $stream ); }
	}

	private static function create_transfer( $name, $size, $sha256, $credential_scope, $id = null, array $source = [] ) {
		$access = self::access();
		if ( is_wp_error( $access ) ) {
			return $access; }
		if ( ! is_string( $name ) || '' === $name || sanitize_file_name( $name ) !== $name || ! wp_check_filetype( $name, Sync_SVG::mimes() )['type'] || ! is_int( $size ) || $size < 1 || ! is_string( $sha256 ) || ! preg_match( '/^[a-f0-9]{64}$/D', $sha256 ) ) {
			return File_Store::error( 'invalid_media_manifest' ); }
		$id  = $id ?? 'file_' . str_replace( '-', '', wp_generate_uuid4() );
		$dir = File_Store::directory( $id );
		if ( is_wp_error( $dir ) ) {
			return $dir; }
		$record = [
			'id'               => $id,
			'owner'            => get_current_user_id(),
			'blog'             => get_current_blog_id(),
			'name'             => $name,
			'size'             => $size,
			'sha256'           => $sha256,
			'offset'           => 0,
			'ready'            => false,
			'updated_at'       => time(),
			'credential_scope' => hash( 'sha256', $credential_scope ),
		] + $source;
		if ( ! add_option( 'rsl_ie_media_chunk_' . $id, $record, '', false ) ) {
			File_Store::remove( $id );
			return File_Store::error( 'file_write', 500 ); }
		return self::describe( $record );
	}

	private static function record( $id, $credential_scope = 'local', $allow_cleanup = false ) {
		$access = self::access();
		if ( is_wp_error( $access ) ) {
			return $access; }
		if ( ! File_Store::valid_id( $id ) ) {
			return File_Store::error( 'file_not_found', 404 ); }
		$r = get_option( 'rsl_ie_media_chunk_' . $id );
		return is_array( $r ) && ( $allow_cleanup || empty( $r['cleanup_pending'] ) ) && $r['owner'] === get_current_user_id() && $r['blog'] === get_current_blog_id() && hash_equals( $r['credential_scope'], hash( 'sha256', $credential_scope ) ) ? $r : File_Store::error( 'file_not_found', 404 );
	}

	private static function describe( array $r ) {
		return [
			'transfer_id' => $r['id'],
			'offset'      => $r['offset'],
			'size'        => $r['size'],
			'ready'       => $r['ready'],
		];
	}

	public static function status( $id, $credential_scope = 'local' ) {
		$r = self::record( $id, $credential_scope );
		return is_wp_error( $r ) ? $r : self::describe( $r );
	}

	/** Create an immutable source copy. Path/manifest come only from the authorized media service. */
	public static function snapshot_source( $attachment_id, $path, array $manifest, $credential_scope, $request_id, $chunk_limit = 0 ) {
		$scope = 'source:' . $credential_scope;
		$state = self::begin( $manifest['file_name'], $manifest['size'], $manifest['sha256'], $scope, $request_id );
		if ( is_wp_error( $state ) ) {
			return $state; }
		return self::copy_source_step( $state['transfer_id'], $attachment_id, $path, $scope, $chunk_limit );
	}

	/** Initialize without reading file contents; the digest becomes available only after copying. */
	public static function start_source_snapshot( $attachment_id, $path, $credential_scope, $request_id ) {
		if ( ! is_int( $attachment_id ) || $attachment_id < 1 || ! is_string( $request_id ) || ! preg_match( '/^[A-Za-z0-9_-]{16,128}$/D', $request_id ) ) {
			return File_Store::error( 'invalid_request_id' ); }
		$access = self::access();
		if ( is_wp_error( $access ) ) {
			return $access; }
		$scope    = 'source:' . $credential_scope;
		$identity = wp_json_encode( [ get_current_blog_id(), get_current_user_id(), $scope, 'discover', $request_id ] );
		$id       = 'file_' . substr( hash_hmac( 'sha256', $identity, wp_salt( 'auth' ) ), 0, 32 );
		$lock     = Job_Lock::acquire_resource( 'media-chunk', $id );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return File_Store::error( 'busy', 409 ); }
		try {
			wp_cache_delete( 'rsl_ie_media_chunk_' . $id, 'options' );
			$r = get_option( 'rsl_ie_media_chunk_' . $id, false );
			if ( false === $r ) {
				clearstatcache( true, $path );
				$stat = stat( $path );
				if ( ! $stat ) {
					return File_Store::error( 'file_not_found', 404 ); }
				// Publish the source identity together with the initial record, never in a second write.
				$created = self::create_transfer(
					basename( $path ),
					$stat['size'],
					str_repeat( '0', 64 ),
					$scope,
					$id,
					[
						'source_attachment_id' => $attachment_id,
						'discover_digest'      => true,
						'source_stat'          => self::source_stat( $stat ),
					]
				);
				if ( is_wp_error( $created ) ) {
					return $created; }
			} else {
				$r = self::record( $id, $scope );
				if ( is_wp_error( $r ) ) {
					return $r; }
				if ( ( $r['source_attachment_id'] ?? null ) !== $attachment_id || empty( $r['discover_digest'] ) ) {
					return File_Store::error( 'request_conflict', 409 ); }
			}
		} finally {
			Job_Lock::release( $lock ); }
		return self::copy_source_step( $id, $attachment_id, $path, $scope, 1 );
	}

	private static function source_stat( array $stat ) {
		return array_intersect_key( $stat, array_flip( [ 'dev', 'ino', 'size', 'mtime', 'ctime' ] ) );
	}

	/** Private checkpoint lookup; returns no path or serialized hash state. */
	public static function snapshot_info( $id, $credential_scope = 'local' ) {
		$r = self::record( $id, 'source:' . $credential_scope );
		if ( is_wp_error( $r ) ) {
			return $r; }
		return isset( $r['source_attachment_id'] ) ? self::snapshot_description( $r ) : File_Store::error( 'file_not_found', 404 );
	}

	/** Path is re-resolved by the authorized media service on every advance. */
	public static function advance_snapshot( $id, $attachment_id, $path, $credential_scope = 'local' ) {
		return self::copy_source_step( $id, $attachment_id, $path, 'source:' . $credential_scope, 1 );
	}

	private static function copy_source_step( $id, $attachment_id, $path, $scope, $chunk_limit ) {
		if ( ! File_Store::valid_id( $id ) ) {
			return File_Store::error( 'file_not_found', 404 ); }
		$lock = Job_Lock::acquire_resource( 'media-chunk', $id );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return File_Store::error( 'busy', 409 ); }
		try {
			wp_cache_delete( 'rsl_ie_media_chunk_' . $id, 'options' );
			$r = self::record( $id, $scope );
			if ( is_wp_error( $r ) ) {
				return $r; }
			if ( isset( $r['source_attachment_id'] ) && $r['source_attachment_id'] !== $attachment_id ) {
				return File_Store::error( 'request_conflict', 409 ); }
			if ( $r['ready'] ) {
				return isset( $r['source_attachment_id'] ) ? self::snapshot_description( $r ) : File_Store::error( 'request_conflict', 409 ); }
			if ( ! isset( $r['source_attachment_id'] ) ) {
				$r['source_attachment_id'] = $attachment_id;
				if ( ! update_option( 'rsl_ie_media_chunk_' . $id, $r, false ) ) {
					return File_Store::error( 'file_write', 500 ); }
			}
			$dir = File_Store::directory( $id );
			if ( is_wp_error( $dir ) ) {
				return $dir; }
			if ( is_link( $dir . '/media.part' ) || is_link( $dir . '/media.chunks' ) ) {
				return File_Store::error( 'file_write', 500 ); }
			$source = fopen( $path, 'rb' );
			if ( ! $source ) {
				return File_Store::error( 'file_not_found', 404 ); }
			$output = null;
			$index  = null;
			try {
				if ( isset( $r['source_stat'] ) && self::source_stat( fstat( $source ) ) !== $r['source_stat'] ) {
					return File_Store::error( 'source_changed', 409 ); }
				$output = fopen( $dir . '/media.part', 'c+b' );
				$index  = fopen( $dir . '/media.chunks', 'c+b' );
				if ( ! $output || ! $index ) {
					return File_Store::error( 'file_write', 500 ); }
				$hash = self::restore_snapshot_hash( $r );
				if ( is_wp_error( $hash ) && 'rsl_ie_hash_checkpoint_runtime_changed' === $hash->get_error_code() ) {
					// No unfinished snapshot is readable. Rebuild it in bounded slices on this runtime.
					$r['offset']              = 0;
					$r['snapshot_generation'] = ( $r['snapshot_generation'] ?? 0 ) + 1;
					$hash                     = hash_init( 'sha256' );
				}
				$offset = $r['offset'];
				if ( is_wp_error( $hash ) ) {
					return $hash; }
				$index_offset = intdiv( $offset, self::CHUNK_BYTES ) * 32;
				if ( fstat( $output )['size'] < $offset || fstat( $index )['size'] < $index_offset || ! ftruncate( $output, $offset ) || ! ftruncate( $index, $index_offset ) || 0 !== fseek( $output, $offset ) || 0 !== fseek( $index, $index_offset ) || 0 !== fseek( $source, $offset ) ) {
					return File_Store::error( 'chunk_integrity', 409 ); }
				$copied = 0;
				while ( $offset < $r['size'] && ( ! $chunk_limit || $copied < $chunk_limit ) ) {
					$length = min( self::CHUNK_BYTES, $r['size'] - $offset );
					$bytes  = fread( $source, $length );
					if ( false === $bytes || strlen( $bytes ) !== $length ) {
						return File_Store::error( 'source_changed', 409 ); }
					if ( fwrite( $output, $bytes ) !== $length || fwrite( $index, self::chunk_signature( $id, $offset, $bytes ) ) !== 32 ) {
						return File_Store::error( 'file_write', 500 ); }
					hash_update( $hash, $bytes );
					$offset += $length;
					++$copied;
				}
				$complete = $offset === $r['size'];
				if ( fstat( $source )['size'] !== $r['size'] || ( isset( $r['source_stat'] ) && self::source_stat( fstat( $source ) ) !== $r['source_stat'] ) ) {
					return File_Store::error( 'source_changed', 409 ); }
				if ( $complete ) {
					if ( ! fflush( $output ) ) {
						return File_Store::error( 'file_write', 500 );
					}
					$digest = PHP_VERSION_ID < 80000 ? hash_file( 'sha256', $dir . '/media.part' ) : hash_final( $hash );
					if ( empty( $r['discover_digest'] ) && ! hash_equals( $r['sha256'], $digest ) ) {
						return File_Store::error( 'source_changed', 409 ); }
					$r['sha256'] = $digest;
				}
				if ( ! fflush( $output ) || ! fflush( $index ) ) {
					return File_Store::error( 'file_write', 500 ); }
			} finally {
				fclose( $source );
				if ( is_resource( $output ) ) {
					fclose( $output ); }
				if ( is_resource( $index ) ) {
					fclose( $index ); }
			}
			$r['source_attachment_id'] = $attachment_id;
			$r['offset']               = $offset;
			$r['ready']                = $complete;
			if ( $complete ) {
				unset( $r['snapshot_hash_state'], $r['snapshot_hash_mac'], $r['snapshot_hash_runtime'] );
			} else {
				try {
					$r['snapshot_hash_state'] = PHP_VERSION_ID < 80000 ? 'legacy-final-pass' : base64_encode( serialize( $hash ) ); } catch ( \Throwable $exception ) {
					return File_Store::error( 'hash_checkpoint_unsupported', 500 ); }
					$r['snapshot_hash_runtime'] = PHP_VERSION_ID . ':' . PHP_INT_SIZE;
					$r['snapshot_hash_mac']     = self::snapshot_hash_mac( $r );
			}
			$r['updated_at'] = time();
			if ( ! update_option( 'rsl_ie_media_chunk_' . $id, $r, false ) ) {
				return File_Store::error( 'file_write', 500 ); }
			return self::snapshot_description( $r );
		} finally {
			Job_Lock::release( $lock ); }
	}

	private static function snapshot_hash_mac( array $r ) {
		return hash_hmac( 'sha256', $r['id'] . ':' . $r['offset'] . ':' . $r['snapshot_hash_runtime'] . ':' . $r['snapshot_hash_state'], wp_salt( 'auth' ) );
	}

	private static function restore_snapshot_hash( array $r ) {
		if ( 0 === $r['offset'] ) {
			return hash_init( 'sha256' ); }
		if ( ! is_string( $r['snapshot_hash_runtime'] ?? null ) || ! is_string( $r['snapshot_hash_state'] ?? null ) || strlen( $r['snapshot_hash_state'] ) > 4096 || ! is_string( $r['snapshot_hash_mac'] ?? null ) || ! hash_equals( self::snapshot_hash_mac( $r ), $r['snapshot_hash_mac'] ) ) {
			return File_Store::error( 'chunk_integrity', 409 ); }
		if ( ( $r['snapshot_hash_runtime'] ?? null ) !== PHP_VERSION_ID . ':' . PHP_INT_SIZE ) {
			return File_Store::error( 'hash_checkpoint_runtime_changed', 409 ); }
		// PHP 7.4 cannot serialize HashContext. Copy bounded blocks and hash once at completion.
		if ( PHP_VERSION_ID < 80000 && 'legacy-final-pass' === $r['snapshot_hash_state'] ) {
			return hash_init( 'sha256' );
		}
		try {
			$serialized = base64_decode( $r['snapshot_hash_state'], true );
			// Only locally authenticated hash state is decoded; no peer-controlled classes.
			$hash = false === $serialized ? false : @unserialize( $serialized, [ 'allowed_classes' => [ 'HashContext' ] ] );
			return $hash instanceof \HashContext ? $hash : File_Store::error( 'chunk_integrity', 409 );
		} catch ( \Throwable $exception ) {
			return File_Store::error( 'chunk_integrity', 409 ); }
	}

	private static function snapshot_description( array $r ) {
		return [
			'snapshot_id'   => $r['id'],
			'generation'    => $r['snapshot_generation'] ?? 0,
			'attachment_id' => $r['source_attachment_id'],
			'file_name'     => $r['name'],
			'size'          => $r['size'],
			'sha256'        => ! empty( $r['discover_digest'] ) && ! $r['ready'] ? null : $r['sha256'],
			'offset'        => $r['offset'],
			'ready'         => $r['ready'],
		];
	}

	/** Authenticate each fixed-size block independently; never rehash the entire snapshot per read. */
	private static function chunk_signature( $id, $offset, $bytes ) {
		return hash_hmac( 'sha256', $id . ':' . $offset . ':' . $bytes, wp_salt( 'auth' ), true );
	}

	public static function read_snapshot( $id, $offset, $credential_scope = 'local' ) {
		$scope = 'source:' . $credential_scope;
		$r     = self::record( $id, $scope );
		if ( is_wp_error( $r ) ) {
			return $r; }
		if ( empty( $r['ready'] ) || empty( $r['source_attachment_id'] ) || ! is_int( $offset ) || $offset < 0 || $offset >= $r['size'] || 0 !== $offset % self::CHUNK_BYTES ) {
			return File_Store::error( 'invalid_chunk' ); }
		$attachment_id = $r['source_attachment_id'];
		if ( 'attachment' !== get_post_type( $attachment_id ) || ! current_user_can( 'read_post', $attachment_id ) || ! current_user_can( 'edit_post', $attachment_id ) ) {
			return File_Store::error( 'forbidden', 403 ); }
		$lock = Job_Lock::acquire_resource( 'media-chunk', $id );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return File_Store::error( 'busy', 409 ); }
		try {
			wp_cache_delete( 'rsl_ie_media_chunk_' . $id, 'options' );
			$r = self::record( $id, $scope );
			if ( is_wp_error( $r ) ) {
				return $r; }
			$dir = File_Store::directory( $id );
			if ( is_wp_error( $dir ) ) {
				return $dir; }
			$path       = $dir . '/media.part';
			$index_path = $dir . '/media.chunks';
			clearstatcache( true, $path );
			clearstatcache( true, $index_path );
			if ( is_link( $path ) || is_link( $index_path ) || ! is_file( $path ) || ! is_file( $index_path ) || filesize( $path ) !== $r['size'] || filesize( $index_path ) !== ( intdiv( $r['size'] - 1, self::CHUNK_BYTES ) + 1 ) * 32 ) {
				return File_Store::error( 'chunk_integrity', 409 ); }
			$file  = fopen( $path, 'rb' );
			$index = fopen( $index_path, 'rb' );
			try {
				if ( ! $file || ! $index || 0 !== fseek( $file, $offset ) || 0 !== fseek( $index, intdiv( $offset, self::CHUNK_BYTES ) * 32 ) ) {
					return File_Store::error( 'chunk_integrity', 409 ); }
				$length    = min( self::CHUNK_BYTES, $r['size'] - $offset );
				$bytes     = fread( $file, $length );
				$signature = fread( $index, 32 );
				if ( false === $bytes || strlen( $bytes ) !== $length || ! is_string( $signature ) || ! hash_equals( self::chunk_signature( $id, $offset, $bytes ), $signature ) ) {
					return File_Store::error( 'chunk_integrity', 409 ); }
				// Remote consumers have no local job reference to protect an active snapshot.
				$now = time();
				if ( $now > ( $r['updated_at'] ?? 0 ) ) {
					$r['updated_at'] = $now;
					if ( ! update_option( 'rsl_ie_media_chunk_' . $id, $r, false ) ) {
						return File_Store::error( 'file_write', 500 ); }
				}
				return [
					'offset'      => $offset,
					'next_offset' => $offset + $length,
					'data'        => base64_encode( $bytes ),
				];
			} finally {
				if ( is_resource( $file ) ) {
					fclose( $file ); }
				if ( is_resource( $index ) ) {
					fclose( $index ); }
			}
		} finally {
			Job_Lock::release( $lock ); }
	}

	/** Append the next chunk or acknowledge an identical committed retry. */
	public static function append( $id, $offset, $bytes, $credential_scope = 'local' ) {
		$r = self::record( $id, $credential_scope );
		if ( is_wp_error( $r ) ) {
			return $r; }
		if ( ! is_int( $offset ) || $offset < 0 || ! is_string( $bytes ) || '' === $bytes || strlen( $bytes ) > self::CHUNK_BYTES ) {
			return File_Store::error( 'invalid_chunk' ); }
		$lock = Job_Lock::acquire_resource( 'media-chunk', $id );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return File_Store::error( 'busy', 409 ); }
		try {
			// The pre-lock authorization read may be stale after another request
			// commits. Reload the checkpoint only after owning the resource lock.
			wp_cache_delete( 'rsl_ie_media_chunk_' . $id, 'options' );
			$r = self::record( $id, $credential_scope );
			if ( is_wp_error( $r ) ) {
				return $r; }
			$dir = File_Store::directory( $id );
			if ( is_wp_error( $dir ) ) {
				return $dir; }
			if ( isset( $r['source_attachment_id'] ) ) {
				return File_Store::error( 'immutable_snapshot', 409 ); }
			$path = $dir . '/media.part';
			if ( is_link( $path ) ) {
				return File_Store::error( 'unsafe_path' ); }
			$stream = fopen( $path, 'c+b' );
			if ( ! $stream ) {
				return File_Store::error( 'file_write', 500 ); }
			try {
				$length = strlen( $bytes );
				if ( fstat( $stream )['size'] < $r['offset'] ) {
					return File_Store::error( 'chunk_integrity', 409 ); }
				if ( $offset < $r['offset'] && $length <= $r['offset'] - $offset ) {
					if ( 0 !== fseek( $stream, $offset ) || ! hash_equals( $bytes, fread( $stream, $length ) ) ) {
						return File_Store::error( 'chunk_conflict', 409 ); }
					return self::describe( $r );
				}
				if ( $r['ready'] || $offset !== $r['offset'] || $length > $r['size'] - $offset ) {
					return File_Store::error( 'chunk_position', 409 ); }
				// A process may have died after writing but before publishing its offset.
				if ( ! ftruncate( $stream, $offset ) || 0 !== fseek( $stream, $offset ) || fwrite( $stream, $bytes ) !== $length || ! fflush( $stream ) ) {
					return File_Store::error( 'file_write', 500 ); }
				$r['offset'] += $length;
				if ( $r['offset'] === $r['size'] ) {
					if ( ! hash_equals( $r['sha256'], hash_file( 'sha256', $path ) ) ) {
						return File_Store::error( 'chunk_integrity', 409 ); }
					$r['ready'] = true;
				}
				$r['updated_at'] = time();
				if ( ! update_option( 'rsl_ie_media_chunk_' . $id, $r, false ) ) {
					return File_Store::error( 'file_write', 500 ); }
				return self::describe( $r );
			} finally {
				fclose( $stream ); }
		} finally {
			Job_Lock::release( $lock ); }
	}

	/** Server-only resolution; recheck bytes before consuming staged content. */
	public static function resolve( $id, $credential_scope = 'local' ) {
		$r = self::record( $id, $credential_scope );
		if ( is_wp_error( $r ) ) {
			return $r; }
		if ( ! $r['ready'] || $r['offset'] !== $r['size'] ) {
			return File_Store::error( 'upload_incomplete', 409 ); }
		$dir = File_Store::directory( $id );
		if ( is_wp_error( $dir ) ) {
			return $dir; }
		$path = $dir . '/media.part';
		clearstatcache( true, $path );
		if ( is_link( $path ) || ! is_file( $path ) || filesize( $path ) !== $r['size'] || ! hash_equals( $r['sha256'], (string) hash_file( 'sha256', $path ) ) ) {
			return File_Store::error( 'chunk_integrity', 409 ); }
		return [
			'path'      => $path,
			'file_name' => $r['name'],
			'size'      => $r['size'],
			'sha256'    => $r['sha256'],
		];
	}

	/** Copy verified bytes to a WordPress temporary file while holding the transfer lock. */
	public static function copy_ready( $id, $credential_scope = 'local' ) {
		$r = self::record( $id, $credential_scope );
		if ( is_wp_error( $r ) ) {
			return $r; }
		$lock = Job_Lock::acquire_resource( 'media-chunk', $id );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return File_Store::error( 'busy', 409 ); }
		$tmp = '';
		try {
			wp_cache_delete( 'rsl_ie_media_chunk_' . $id, 'options' );
			$file = self::resolve( $id, $credential_scope );
			if ( is_wp_error( $file ) ) {
				return $file; }
			require_once ABSPATH . 'wp-admin/includes/file.php';
			$tmp = wp_tempnam( $file['file_name'] );
			if ( ! $tmp || ! copy( $file['path'], $tmp ) ) {
				return File_Store::error( 'file_write', 500 ); }
			clearstatcache( true, $tmp );
			if ( filesize( $tmp ) !== $file['size'] || ! hash_equals( $file['sha256'], (string) hash_file( 'sha256', $tmp ) ) ) {
				return File_Store::error( 'chunk_integrity', 409 ); }
			$file['path'] = $tmp;
			$tmp          = '';
			return $file;
		} finally {
			if ( $tmp && is_file( $tmp ) ) {
				wp_delete_file( $tmp ); }
			Job_Lock::release( $lock );
		}
	}

	/** Freeze validated receiver semantics before the first attachment mutation. */
	public static function bind_finalization( $id, array $metadata, $credential_scope = 'local' ) {
		$r = self::record( $id, $credential_scope );
		if ( is_wp_error( $r ) ) {
			return $r; }
		$json = wp_json_encode( self::canonical_metadata( $metadata ) );
		if ( false === $json ) {
			return File_Store::error( 'invalid_payload' ); }
		$digest = hash( 'sha256', $json );
		$lock   = Job_Lock::acquire_resource( 'media-chunk', $id );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return File_Store::error( 'busy', 409 ); }
		try {
			wp_cache_delete( 'rsl_ie_media_chunk_' . $id, 'options' );
			$r = self::record( $id, $credential_scope );
			if ( is_wp_error( $r ) ) {
				return $r; }
			if ( ! $r['ready'] ) {
				return File_Store::error( 'upload_incomplete', 409 ); }
			if ( isset( $r['finalization_hash'] ) ) {
				return hash_equals( $r['finalization_hash'], $digest ) ? true : File_Store::error( 'finalization_conflict', 409 );
			}
			$r['finalization_hash'] = $digest;
			$r['updated_at']        = time();
			return update_option( 'rsl_ie_media_chunk_' . $id, $r, false ) ? true : File_Store::error( 'file_write', 500 );
		} finally {
			Job_Lock::release( $lock ); }
	}

	/** Read or persist a successful receiver outcome; retries never replace it. */
	public static function finalization_result( $id, $credential_scope = 'local', $result = null ) {
		$r = self::record( $id, $credential_scope );
		if ( is_wp_error( $r ) ) {
			return $r; }
		$lock = Job_Lock::acquire_resource( 'media-chunk', $id );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return File_Store::error( 'busy', 409 ); }
		try {
			wp_cache_delete( 'rsl_ie_media_chunk_' . $id, 'options' );
			$r = self::record( $id, $credential_scope );
			if ( is_wp_error( $r ) ) {
				return $r; }
			if ( isset( $r['finalization_result'] ) ) {
				return $r['finalization_result']; }
			if ( null === $result ) {
				return null; }
			if ( ! isset( $r['finalization_hash'] ) || ! is_array( $result ) || 0 !== ( $result['failed'] ?? -1 ) || count( $result['items'] ?? [] ) !== 1 || empty( $result['items'][0]['attachment_id'] ) ) {
				return File_Store::error( 'invalid_finalization_result' );
			}
			$r['finalization_result'] = $result;
			$r['updated_at']          = time();
			return update_option( 'rsl_ie_media_chunk_' . $id, $r, false ) ? $result : File_Store::error( 'file_write', 500 );
		} finally {
			Job_Lock::release( $lock ); }
	}

	private static function canonical_metadata( $value ) {
		if ( ! is_array( $value ) ) {
			return $value; }
		if ( [] !== $value && array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
			ksort( $value ); }
		foreach ( $value as &$entry ) {
			$entry = self::canonical_metadata( $entry ); }
		unset( $entry );
		return $value;
	}

	/**
	 * Remove a journalled orphan only while both transfer locks are held.
	 *
	 * @param string $id Private transfer identifier.
	 * @param array  $record Stored byte manifest.
	 * @return true|\WP_Error Cleanup acknowledgement or retryable error.
	 */
	private static function cleanup_moved_file( $id, array $record ) {
		$dir = File_Store::directory( $id );
		if ( is_wp_error( $dir ) ) {
			return $dir; }
		$journal = $dir . '/media-move.json';
		if ( is_link( $journal ) ) {
			return File_Store::error( 'file_write', 500 ); }
		if ( ! is_file( $journal ) ) {
			return true; }
		global $wpdb;
		$attachment = $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM %i WHERE post_type='attachment' AND post_name=%s LIMIT 1", $wpdb->posts, 'rsl-ie-' . $id ) );
		if ( $wpdb->last_error ) {
			return File_Store::error( 'file_write', 500 ); }
		// Once inserted, the attachment owns its original and generated files.
		if ( $attachment ) {
			return true; }
		$move    = json_decode( file_get_contents( $journal ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Private local recovery journal.
		$uploads = wp_get_upload_dir();
		$base    = realpath( $uploads['basedir'] );
		$path    = is_array( $move ) && is_string( $move['path'] ?? null ) ? $move['path'] : '';
		if ( ! $base || ! $path || is_link( $path ) || 0 !== strpos( $path, trailingslashit( $base ) ) ) {
			return File_Store::error( 'file_write', 500 ); }
		if ( ! file_exists( $path ) ) {
			return true; }
		clearstatcache( true, $path );
		if ( realpath( $path ) !== $path || ! is_file( $path ) || filesize( $path ) !== $record['size'] || ! hash_equals( $record['sha256'], (string) hash_file( 'sha256', $path ) ) ) {
			return File_Store::error( 'file_write', 500 ); }
		$relative  = substr( $path, strlen( trailingslashit( $base ) ) );
		$reference = $wpdb->get_var( $wpdb->prepare( "SELECT post_id FROM %i WHERE meta_key='_wp_attached_file' AND (meta_value=%s OR meta_value=%s) LIMIT 1", $wpdb->postmeta, $relative, $path ) );
		if ( $wpdb->last_error || $reference ) {
			return File_Store::error( 'file_write', 500 ); }
		return unlink( $path ) ? true : File_Store::error( 'file_write', 500 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.unlink_unlink -- Require confirmed deletion before forgetting the recovery journal.
	}

	/** Remove abandoned transfers; retain any transfer referenced by a job. */
	public static function cleanup() {
		global $wpdb;
		$prefix  = 'rsl_ie_media_chunk_';
		$removed = 0;
		$after   = (string) get_option( 'rsl_ie_media_cleanup_cursor', '' );
		// Keyset pagination remains stable while expired options are deleted.
		$keys = $wpdb->get_col( $wpdb->prepare( 'SELECT option_name FROM %i WHERE option_name LIKE %s AND option_name > %s ORDER BY option_name LIMIT 100', $wpdb->options, $wpdb->esc_like( $prefix ) . '%', $after ) );
		if ( $wpdb->last_error || ! is_array( $keys ) ) {
			return 0;
		}
		$retry = false;
		foreach ( $keys as $key ) {
			$before_key = $after;
			$after      = $key;
			$id         = substr( $key, strlen( $prefix ) );
			if ( ! File_Store::valid_id( $id ) ) {
				continue; }
			$finalize_lock = Job_Lock::acquire_resource( 'media-finalize', $id );
			if ( ! $finalize_lock || is_wp_error( $finalize_lock ) ) {
				continue; }
			$lock = Job_Lock::acquire_resource( 'media-chunk', $id );
			if ( ! $lock || is_wp_error( $lock ) ) {
				Job_Lock::release( $finalize_lock );
				continue; }
			try {
				wp_cache_delete( $key, 'options' );
				$r = get_option( $key );
				if ( ! is_array( $r ) || ( $r['blog'] ?? 0 ) !== get_current_blog_id() || ( $r['updated_at'] ?? time() ) + DAY_IN_SECONDS > time() ) {
					continue; }
				$referenced = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE parameters LIKE %s OR settings LIKE %s LIMIT 1', $wpdb->prefix . 'rsl_ie_jobs', '%' . $wpdb->esc_like( $id ) . '%', '%' . $wpdb->esc_like( $id ) . '%' ) );
				if ( $wpdb->last_error ) {
					$after = $before_key;
					$retry = true;
					break;
				}
				if ( $referenced ) {
					continue; }
				if ( empty( $r['cleanup_pending'] ) ) {
					$r['cleanup_pending'] = true;
					if ( ! update_option( $key, $r, false ) ) {
						continue;
					}
				}
				if ( is_wp_error( self::cleanup_moved_file( $id, $r ) ) ) {
					$after = $before_key;
					$retry = true;
					break;
				}
				if ( true === File_Store::remove( $id, 100 ) ) {
					if ( delete_option( $key ) ) {
						++$removed;
					} else {
						// WordPress caches absence even when the DELETE query fails.
						wp_cache_delete( 'notoptions', 'options' );
						wp_cache_delete( $key, 'options' );
						$after = $before_key;
						$retry = true;
						break;
					}
				}
			} finally {
				Job_Lock::release( $lock );
				Job_Lock::release( $finalize_lock ); }
		}
		update_option( 'rsl_ie_media_cleanup_cursor', $retry || count( $keys ) === 100 ? $after : '', false );
		return $removed;
	}

	public static function discard( $id, $credential_scope = 'local' ) {
		$access = self::access();
		if ( is_wp_error( $access ) ) {
			return $access; }
		if ( ! File_Store::valid_id( $id ) ) {
			return File_Store::error( 'file_not_found', 404 ); }
		$finalize_lock = Job_Lock::acquire_resource( 'media-finalize', $id );
		if ( ! $finalize_lock || is_wp_error( $finalize_lock ) ) {
			return File_Store::error( 'busy', 409 ); }
		$lock = Job_Lock::acquire_resource( 'media-chunk', $id );
		if ( ! $lock || is_wp_error( $lock ) ) {
			Job_Lock::release( $finalize_lock );
			return File_Store::error( 'busy', 409 ); }
		try {
			wp_cache_delete( 'rsl_ie_media_chunk_' . $id, 'options' );
			if ( false === get_option( 'rsl_ie_media_chunk_' . $id, false ) ) {
				global $wpdb;
				// Confirm absence independently: get_option also returns false on a failed SELECT.
				$exists = $wpdb->get_var( $wpdb->prepare( 'SELECT option_id FROM %i WHERE option_name=%s', $wpdb->options, 'rsl_ie_media_chunk_' . $id ) );
				if ( $wpdb->last_error || null !== $exists ) {
					wp_cache_delete( 'notoptions', 'options' );
					return File_Store::error( 'file_write', 500 );
				}
				return true;
			}
			$r = self::record( $id, $credential_scope, true );
			if ( is_wp_error( $r ) ) {
				return $r; }
			$result = self::cleanup_moved_file( $id, $r );
			if ( is_wp_error( $result ) ) {
				return $result; }
			$result = File_Store::remove( $id );
			if ( ! is_wp_error( $result ) && ! delete_option( 'rsl_ie_media_chunk_' . $id ) ) {
				wp_cache_delete( 'notoptions', 'options' );
				wp_cache_delete( 'rsl_ie_media_chunk_' . $id, 'options' );
				return File_Store::error( 'file_write', 500 );
			}
			return $result;
		} finally {
			Job_Lock::release( $lock );
			Job_Lock::release( $finalize_lock ); }
	}
}

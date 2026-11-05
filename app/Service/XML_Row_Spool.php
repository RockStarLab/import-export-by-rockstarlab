<?php
/** Private, expiring row snapshots for XML encodings without seekable byte boundaries. */
namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

class XML_Row_Spool {
	const TTL = 604800;

	/** Parse a source once, then resume directly at a serialized row boundary. */
	public static function rows( $source, $identity, $settings, &$cursor, callable $records ) {
		$root = File_Store::root();
		if ( is_wp_error( $root ) ) {
			throw new \RuntimeException( 'Cannot open XML snapshot storage.' ); }
		$key  = hash( 'sha256', $source . serialize( $identity ) . $settings );
		$path = $root . '/xml-rows-' . $key;
		if ( is_link( $path ) ) {
			throw new \RuntimeException( 'Invalid XML snapshot.' ); }
		$handle = fopen( $path, 'c+b' );
		if ( ! $handle ) {
			throw new \RuntimeException( 'Cannot open XML snapshot.' ); }
		try {
			if ( ! flock( $handle, LOCK_EX ) ) {
				throw new \RuntimeException( 'Cannot lock XML snapshot.' ); }
			// A cleanup may have unlinked this inode while we waited for its lock.
			clearstatcache( true, $path );
			if ( ! is_file( $path ) || stat( $path )['ino'] !== fstat( $handle )['ino'] ) {
				throw new \RuntimeException( 'Expired XML snapshot.' ); }
			$header = fgets( $handle );
			if ( ! is_string( $header ) || ! preg_match( '/^1([a-f0-9]{64}) ([a-f0-9]{32}) ([0-9]{20})\n$/D', $header, $match ) ) {
				if ( isset( $cursor['spool'] ) ) {
					throw new \RuntimeException( 'Expired XML snapshot.' ); }
				$digest = hash_file( 'sha256', $source );
				$token  = bin2hex( random_bytes( 16 ) );
				$header = '0' . $digest . ' ' . $token . ' ' . str_repeat( '0', 20 ) . "\n";
				$total  = 0;
				if ( ! ftruncate( $handle, 0 ) || fseek( $handle, 0 ) !== 0 ) {
					throw new \RuntimeException( 'Cannot reset XML snapshot.' ); }
				self::write( $handle, $header );
				foreach ( $records() as $row ) {
					$raw = serialize( $row );
					self::write( $handle, hash( 'sha256', $raw ) . ' ' . base64_encode( $raw ) . "\n" );
					++$total;
				}
				if ( ! hash_equals( $digest, (string) hash_file( 'sha256', $source ) ) ) {
					throw new \RuntimeException( 'Changed XML prefix.' ); }
				if ( ! fflush( $handle ) || fseek( $handle, 0 ) !== 0 ) {
					throw new \RuntimeException( 'Cannot finish XML snapshot.' ); }
				$header = '1' . $digest . ' ' . $token . ' ' . sprintf( '%020d', $total ) . "\n";
				self::write( $handle, $header );
				if ( ! fflush( $handle ) ) {
					throw new \RuntimeException( 'Cannot finish XML snapshot.' ); }
				$match = array( '', $digest, $token, (string) $total );
			}
			$state  = $cursor['spool'] ?? null;
			$offset = $state['offset'] ?? strlen( $header );
			$count  = $state['count'] ?? 0;
			if ( null !== $state && ( ! is_array( $state ) || ( $state['key'] ?? null ) !== $key || ( $state['token'] ?? null ) !== $match[2] || ! is_int( $offset ) || $offset < strlen( $header ) || $offset > fstat( $handle )['size'] || $count !== $cursor['count'] ) ) {
				throw new \RuntimeException( 'Invalid XML snapshot cursor.' ); }
			if ( fseek( $handle, $offset - 1 ) !== 0 || fread( $handle, 1 ) !== "\n" ) {
				throw new \RuntimeException( 'Invalid XML snapshot boundary.' ); }
			touch( $path );
			$skip     = null === $state ? ( $cursor['count'] ?? 0 ) : 0;
			$prefix   = hash_init( 'sha256' );
			$expected = $cursor['prefix_sha256'] ?? null;
			while ( false !== ( $line = fgets( $handle ) ) ) {
				$raw = base64_decode( rtrim( substr( $line, 65 ), "\n" ), true );
				$row = false === $raw ? false : unserialize( $raw, array( 'allowed_classes' => false ) );
				if ( ! is_array( $row ) || substr( $line, -1 ) !== "\n" || ! hash_equals( substr( $line, 0, 64 ), hash( 'sha256', $raw ) ) ) {
					throw new \RuntimeException( 'Invalid XML snapshot row.' ); }
				++$count;
				if ( $skip > 0 ) {
					hash_update( $prefix, serialize( $row ) );
					if ( $count === $skip && null !== $expected && ( ! is_string( $expected ) || ! hash_equals( $expected, hash_final( hash_copy( $prefix ) ) ) ) ) {
						throw new \RuntimeException( 'Changed XML prefix.' ); }
					if ( $count <= $skip ) {
						continue; }
				}
				$cursor = array(
					'identity' => $identity,
					'settings' => $settings,
					'count'    => $count,
					'spool'    => array(
						'key'    => $key,
						'token'  => $match[2],
						'offset' => ftell( $handle ),
						'count'  => $count,
					),
				);
				yield $row;
			}
			if ( ! feof( $handle ) || $count < $skip || $count !== (int) $match[3] ) {
				throw new \RuntimeException( 'Invalid XML snapshot count.' ); }
			if ( ! hash_equals( $match[1], (string) hash_file( 'sha256', $source ) ) ) {
				throw new \RuntimeException( 'Changed XML prefix.' ); }
		} finally {
			flock( $handle, LOCK_UN );
			fclose( $handle );
		}
	}

	private static function write( $handle, $bytes ) {
		$offset = 0;
		while ( $offset < strlen( $bytes ) ) {
			$n = fwrite( $handle, substr( $bytes, $offset ) );
			if ( ! $n ) {
				throw new \RuntimeException( 'Cannot write XML snapshot.' ); }
			$offset += $n;
		}
	}

	/** Bounded deletion, including incomplete snapshots left by terminated requests. */
	public static function cleanup() {
		$root = File_Store::root();
		if ( is_wp_error( $root ) ) {
			return; }
		$removed = 0;
		foreach ( new \DirectoryIterator( $root ) as $file ) {
			if ( $removed >= 100 ) {
				break; }
			if ( $file->isLink() || ! $file->isFile() || ! preg_match( '/^xml-rows-[a-f0-9]{64}$/D', $file->getFilename() ) || $file->getMTime() >= time() - self::TTL ) {
				continue; }
			$path   = $file->getPathname();
			$handle = fopen( $path, 'r+b' );
			if ( ! $handle ) {
				continue; }
			if ( flock( $handle, LOCK_EX | LOCK_NB ) ) {
				if ( fstat( $handle )['mtime'] < time() - self::TTL && unlink( $path ) ) {
					++$removed; }
				flock( $handle, LOCK_UN );
			}
			fclose( $handle );
		}
	}
}

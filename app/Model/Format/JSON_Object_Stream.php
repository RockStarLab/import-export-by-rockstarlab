<?php
/** Disk-backed legacy object iteration with native duplicate-key semantics. */
namespace RockStarLab\ImportExport\Model\Format;

defined( 'ABSPATH' ) || exit;

class JSON_Object_Stream {
	/** Keep first-key order and last-key value without retaining all keys or rows. */
	public static function rows( $path, array $options = [] ) {
		$directory = rtrim( get_temp_dir(), '/\\' ) . '/rsl-json-' . bin2hex( random_bytes( 16 ) );
		if ( ! mkdir( $directory, 0700 ) ) {
			throw new \RuntimeException( 'Cannot create JSON index.' ); }
		$order = null;
		try {
			$order = fopen( $directory . '/order', 'x+b' );
			if ( ! $order ) {
				throw new \RuntimeException( 'Cannot create JSON order.' ); }
			foreach ( JSON_Row_Stream::rows( $path, [ 'object_members' => true ] + $options ) as $member ) {
				$key    = array_key_first( $member );
				$hash   = hash( 'sha256', serialize( $key ) );
				$bucket = $directory . '/' . substr( $hash, 0, 2 );
				if ( ! is_dir( $bucket ) && ! mkdir( $bucket, 0700 ) ) {
					throw new \RuntimeException( 'Cannot create JSON bucket.' ); }
				$file = $bucket . '/' . $hash;
				if ( ! file_exists( $file ) ) {
					if ( 65 !== fwrite( $order, $hash . "\n" ) ) {
						throw new \RuntimeException( 'Cannot index JSON member.' ); }
				} else {
					$previous = unserialize( file_get_contents( $file ), [ 'allowed_classes' => false ] );
					if ( ! is_array( $previous ) || array_key_first( $previous ) !== $key ) {
						throw new \RuntimeException( 'Conflicting JSON key.' ); }
					unset( $previous );
				}
				$encoded = serialize( $member );
				if ( strlen( $encoded ) !== file_put_contents( $file, $encoded ) ) {
					throw new \RuntimeException( 'Cannot store JSON member.' ); }
			}
			if ( ! rewind( $order ) ) {
				throw new \RuntimeException( 'Cannot rewind JSON order.' ); }
			while ( false !== ( $line = fgets( $order ) ) ) {
				$hash   = rtrim( $line, "\n" );
				$member = unserialize( file_get_contents( $directory . '/' . substr( $hash, 0, 2 ) . '/' . $hash ), [ 'allowed_classes' => false ] );
				if ( ! is_array( $member ) || count( $member ) !== 1 ) {
					throw new \RuntimeException( 'Invalid JSON index.' ); }
				foreach ( $member as $key => $value ) {
					yield $key => $value; }
			}
		} finally {
			if ( is_resource( $order ) ) {
				fclose( $order ); }
			// Iterate directory entries rather than collecting every temporary filename.
			$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $directory, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );
			foreach ( $files as $file ) {
				if ( $file->isDir() ) {
					rmdir( $file->getPathname() );
				} else {
					unlink( $file->getPathname() ); }
			}
			rmdir( $directory );
		}
	}
}

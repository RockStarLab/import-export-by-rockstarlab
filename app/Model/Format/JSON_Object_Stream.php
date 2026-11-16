<?php
/** Disk-backed legacy object iteration with native duplicate-key semantics. */
namespace RockStarLab\ImportExport\Model\Format;

defined( 'ABSPATH' ) || exit;

class JSON_Object_Stream {
	/** Keep first-key order and last-key value without retaining all keys or rows. */
	public static function rows( $path, array $options = [] ) {
		$directory = rtrim( get_temp_dir(), '/\\' ) . '/rsl-json-' . bin2hex( random_bytes( 16 ) );
		if ( ! mkdir( $directory, 0700 ) ) {
			throw new \RuntimeException( __( 'Cannot create JSON index.', 'import-export-by-rockstarlab' ) ); }
		$order = null;
		try {
			$order = fopen( $directory . '/order', 'x+b' );
			if ( ! $order ) {
				throw new \RuntimeException( __( 'Cannot create JSON order.', 'import-export-by-rockstarlab' ) ); }
			foreach ( JSON_Row_Stream::rows( $path, [ 'object_members' => true ] + $options ) as $member ) {
				$key    = array_key_first( $member );
				$hash   = hash( 'sha256', serialize( $key ) );
				$bucket = $directory . '/' . substr( $hash, 0, 2 );
				if ( ! is_dir( $bucket ) && ! mkdir( $bucket, 0700 ) ) {
					throw new \RuntimeException( __( 'Cannot create JSON bucket.', 'import-export-by-rockstarlab' ) ); }
				$file = $bucket . '/' . $hash;
				if ( ! file_exists( $file ) ) {
					if ( 65 !== fwrite( $order, $hash . "\n" ) ) {
						throw new \RuntimeException( __( 'Cannot index JSON member.', 'import-export-by-rockstarlab' ) ); }
				} else {
					$previous = unserialize( file_get_contents( $file ), [ 'allowed_classes' => false ] );
					if ( ! is_array( $previous ) || array_key_first( $previous ) !== $key ) {
						throw new \RuntimeException( __( 'Conflicting JSON key.', 'import-export-by-rockstarlab' ) ); }
					unset( $previous );
				}
				$encoded = serialize( $member );
				if ( strlen( $encoded ) !== file_put_contents( $file, $encoded ) ) {
					throw new \RuntimeException( __( 'Cannot store JSON member.', 'import-export-by-rockstarlab' ) ); }
			}
			if ( ! rewind( $order ) ) {
				throw new \RuntimeException( __( 'Cannot rewind JSON order.', 'import-export-by-rockstarlab' ) ); }
			while ( false !== ( $line = fgets( $order ) ) ) {
				$hash   = rtrim( $line, "\n" );
				$member = unserialize( file_get_contents( $directory . '/' . substr( $hash, 0, 2 ) . '/' . $hash ), [ 'allowed_classes' => false ] );
				if ( ! is_array( $member ) || count( $member ) !== 1 ) {
					throw new \RuntimeException( __( 'Invalid JSON index.', 'import-export-by-rockstarlab' ) ); }
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

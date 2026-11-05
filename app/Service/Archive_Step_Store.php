<?php
/** One private record per archive step; counts are published by the owning checkpoint. */
namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;
class Archive_Step_Store {
	private static function path( array $reference, $index ) {
		if ( ! is_int( $index ) || $index < 0 || ! preg_match( '/\A(public|job-[0-9]+)\z/', $reference['scope'] ?? '' ) ) {
			throw new \RuntimeException( 'Invalid archive step reference.' ); }
		$dir = File_Store::directory( $reference['file_id'] );
		if ( is_wp_error( $dir ) ) {
			throw new \RuntimeException( 'Archive storage unavailable.' ); }
		$path = $dir . '/steps-' . $reference['scope'] . '-' . $index . '.json';
		if ( is_link( $path ) || is_link( $path . '.tmp' ) ) {
			throw new \RuntimeException( 'Unsafe archive step.' ); }
		return $path;
	}
	public static function put( array $reference, $index, array $record ) {
		$path = self::path( $reference, $index );
		$json = wp_json_encode( $record );
		if ( false === $json ) {
			throw new \RuntimeException( 'Invalid archive step.' ); }
		$bytes = hash( 'sha256', $json ) . "\n" . $json;
		if ( strlen( $bytes ) !== file_put_contents( $path . '.tmp', $bytes ) || ! rename( $path . '.tmp', $path ) ) {
			throw new \RuntimeException( 'Cannot save archive step.' ); }
	}
	public static function get( array $reference, $index ) {
		$bytes = file_get_contents( self::path( $reference, $index ) );
		if ( ! is_string( $bytes ) || strlen( $bytes ) < 66 || ! hash_equals( substr( $bytes, 0, 64 ), hash( 'sha256', substr( $bytes, 65 ) ) ) ) {
			throw new \RuntimeException( 'Archive step unavailable or changed.' ); }
		$record = json_decode( substr( $bytes, 65 ), true );
		if ( ! is_array( $record ) ) {
			throw new \RuntimeException( 'Invalid archive step.' ); }
		return $record;
	}
	public static function rows( array $reference ) {
		for ( $index = 0; $index < $reference['count']; ++$index ) {
			yield $index => self::get( $reference, $index ); }
	}
	/** Reclaim only marked archive directories left before metadata publication. */
	public static function cleanup() {
		$root = File_Store::root();
		if ( is_wp_error( $root ) ) {
			return; }
		$visited = 0;
		foreach ( new \FilesystemIterator( $root, \FilesystemIterator::SKIP_DOTS ) as $entry ) {
			if ( $entry->isLink() || ! $entry->isDir() || ! File_Store::valid_id( $entry->getFilename() ) ) {
				continue; }
			$marker = $entry->getPathname() . '/.archive-owner';
			if ( is_link( $marker ) || ! is_file( $marker ) || filemtime( $marker ) > time() - DAY_IN_SECONDS || ! is_wp_error( File_Store::get( $entry->getFilename() ) ) ) {
				continue; }
			$source = file_get_contents( $marker );
			if ( ! File_Store::valid_id( $source ) || ! is_wp_error( File_Store::get( $source ) ) ) {
				continue; }
			$lock = Job_Lock::acquire_resource( 'migration-package', $source );
			if ( ! $lock || is_wp_error( $lock ) ) {
				continue; }
			try {
				File_Store::remove( $entry->getFilename(), 100 );
			} finally {
				Job_Lock::release( $lock ); }
			if ( ++$visited >= 100 ) {
				break; }
		}
	}
}

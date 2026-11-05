<?php
/** Disk-backed shared formula masters, scoped to one worksheet scan. */
namespace RockStarLab\ImportExport\Model\Format;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\ReferenceHelper;

defined( 'ABSPATH' ) || exit;

class XLSX_Formula_Store {
	private $directory;
	private $persistent = false;

	public function __construct( $directory = null ) {
		$this->persistent = null !== $directory;
		$this->directory  = $directory ?? rtrim( get_temp_dir(), '/\\' ) . '/rsl-formula-' . bin2hex( random_bytes( 16 ) );
		if ( is_link( $this->directory ) || ( ! is_dir( $this->directory ) && ! mkdir( $this->directory, 0700 ) ) ) {
			throw new \RuntimeException( 'Cannot create shared-formula store.' ); }
	}

	/** Match Xlsx's first-encounter master semantics, including empty masters. */
	public function resolve( $id, $coordinate, $formula ) {
		if ( ! is_string( $id ) || ! is_string( $coordinate ) || ! is_string( $formula ) ) {
			throw new \RuntimeException( 'Invalid shared formula.' ); }
		$hash    = hash( 'sha256', $id );
		$shard   = $this->directory . '/' . substr( $hash, 0, 2 );
		$path    = $shard . '/' . $hash;
		$current = Coordinate::indexesFromString( $coordinate );
		if ( ! is_file( $path ) ) {
			if ( ! is_dir( $shard ) && ! mkdir( $shard, 0700 ) ) {
				throw new \RuntimeException( 'Cannot create formula shard.' ); }
			$data = serialize( [ $id, $coordinate, $formula ] );
			if ( strlen( $data ) !== file_put_contents( $path, $data ) ) {
				throw new \RuntimeException( 'Cannot store shared formula.' ); }
			return $formula;
		}
		$data   = file_get_contents( $path );
		$master = false === $data ? false : unserialize( $data, [ 'allowed_classes' => false ] );
		if ( ! is_array( $master ) || count( $master ) !== 3 || $master[0] !== $id || ! is_string( $master[1] ) || ! is_string( $master[2] ) ) {
			throw new \RuntimeException( 'Invalid shared-formula record.' ); }
		$origin = Coordinate::indexesFromString( $master[1] );
		return ReferenceHelper::getInstance()->updateFormulaReferences( $master[2], 'A1', $current[0] - $origin[0], $current[1] - $origin[1] );
	}

	public function close() {
		if ( $this->persistent ) {
			return; }
		if ( $this->directory && is_dir( $this->directory ) ) {
			foreach ( new \DirectoryIterator( $this->directory ) as $shard ) {
				if ( $shard->isDot() ) {
					continue; }
				foreach ( new \DirectoryIterator( $shard->getPathname() ) as $file ) {
					if ( ! $file->isDot() ) {
						unlink( $file->getPathname() ); }
				}
				rmdir( $shard->getPathname() );
			}
			rmdir( $this->directory );
		}
	}
	public function __destruct() {
		$this->close(); }
}

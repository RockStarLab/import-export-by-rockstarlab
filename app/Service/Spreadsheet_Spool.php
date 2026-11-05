<?php
/**
 * Private normalized workbook snapshot for bounded database indexing.
 *
 * @package RockStarLab\ImportExport
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions -- Streaming local snapshots require checked writes, atomic rename and checked removal.
/** Server-owned snapshots, never paths supplied by a caller. */
class Spreadsheet_Spool {
	/**
	 * Resolve a deterministic private snapshot path.
	 *
	 * @param int $id Job ID.
	 * @return string
	 * @throws \RuntimeException On invalid storage.
	 */
	public static function path( $id ) {
		$root = File_Store::root();
		if ( is_wp_error( $root ) || (int) $id < 1 ) {
			throw new \RuntimeException( 'Spreadsheet storage unavailable.' );
		}
		$path = $root . '/sheet-' . (int) $id . '.json';
		if ( is_link( $path ) || is_link( $path . '.tmp' ) ) {
			throw new \RuntimeException( 'Invalid spreadsheet snapshot.' );
		}
		return $path;
	}

	/** Start preparation without scanning the workbook. The job owns every artifact. */
	public static function create( $id, array $request, $snapshot = null ) {
		$snapshot = $snapshot ?? self::path( $id );
		clearstatcache( true, $request['file_path'] );
		return array(
			'count'       => 0,
			'total'       => 0,
			'cursor'      => null,
			'done'        => false,
			'format'      => $request['format'],
			'sha256'      => hash_file( 'sha256', $request['file_path'] ),
			'rows_sha256' => hash( 'sha256', '' ),
			'read_sha256' => hash( 'sha256', '' ),
			'build'       => array(
				'phase'        => 'parts',
				'identity'     => array_intersect_key( stat( $request['file_path'] ), array_flip( array( 'dev', 'ino', 'size', 'mtime', 'ctime' ) ) ),
				'cell_bytes'   => 0,
				'string_count' => 0,
				'width'        => 1,
				'row'          => 0,
				'json_bytes'   => 1,
			),
		);
	}

	/** Advance one bounded parse/assembly/snapshot slice under the existing job lock. */
	public static function advance( $id, array $request, array $state, $snapshot = null ) {
		$path = $snapshot ?? self::path( $id );
		$dir  = $path . '.work';
		if ( is_link( $dir ) || ( ! is_dir( $dir ) && ! mkdir( $dir, 0700 ) ) ) {
			throw new \RuntimeException( 'Cannot create spreadsheet workspace.' ); }
		$build = &$state['build'];
		clearstatcache( true, $request['file_path'] );
		if ( $build['identity'] !== array_intersect_key( stat( $request['file_path'] ), array_flip( array( 'dev', 'ino', 'size', 'mtime', 'ctime' ) ) ) ) {
			throw new \RuntimeException( 'Changed spreadsheet source.' ); }
		$parts   = null;
		$strings = null;
		$store   = null;
		$stream  = null;
		try {
			if ( in_array( $build['phase'], array( 'parts', 'extract', 'select', 'strings', 'cells' ), true ) ) {
				$parts = new \RockStarLab\ImportExport\Model\Format\Spreadsheet_XML_Parts( $request['file_path'], $dir . '/parts' );
			}
			if ( 'parts' === $build['phase'] ) {
				// Content detection keeps XLSX/ODS extension-independent import behavior.
				$reader        = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile( $request['file_path'] );
				$build['xlsx'] = $reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Xlsx;
				if ( $build['xlsx'] ) {
					$build['map']           = \RockStarLab\ImportExport\Model\Format\XLSX_Part_Map::resolve( $parts );
					$build['members']       = array_values( array_filter( [ $build['map']['worksheet'], $build['map']['strings'] ] ) );
					$build['after_extract'] = null === $build['map']['strings'] ? 'cells' : 'strings';
				} elseif ( $reader instanceof \PhpOffice\PhpSpreadsheet\Reader\Ods ) {
					$build['members']       = [ 'content.xml' ];
					$build['after_extract'] = 'select';
				} else {
					throw new \RuntimeException( 'Unsupported spreadsheet container.' ); }
				$build['phase'] = 'extract';
			} elseif ( 'extract' === $build['phase'] ) {
				$build['extraction'] = $build['extraction'] ?? [];
				if ( $parts->stage( $build['members'][0], $build['extraction'] ) ) {
					array_shift( $build['members'] );
					unset( $build['extraction'] );
					if ( ! $build['members'] ) {
						$build['phase'] = $build['after_extract'];
						unset( $build['members'], $build['after_extract'] ); }
				}
			} elseif ( 'select' === $build['phase'] ) {
				$selected = \RockStarLab\ImportExport\Model\Format\ODS_Sheet_Selector::select( $parts, $build['selection'] );
				if ( null !== $selected ) {
					$build['selected'] = $selected;
					unset( $build['selection'] );
					$build['phase'] = 'cells'; }
			} elseif ( 'strings' === $build['phase'] ) {
				$strings = new \RockStarLab\ImportExport\Model\Format\Spreadsheet_String_Store( $dir . '/strings', $build['string_count'] );
				\RockStarLab\ImportExport\Model\Format\Spreadsheet_String_Store::from_xml( $parts, $build['map']['strings'], $strings, $build['strings'] );
				$build['string_count'] = $strings->count();
				if ( ! empty( $build['strings']['nodes']['done'] ) ) {
					unset( $build['strings'] );
					$build['phase'] = 'cells'; }
			} elseif ( 'cells' === $build['phase'] ) {
				$stream = self::output( $dir . '/cells', $build['cell_bytes'] );
				if ( $build['xlsx'] ) {
					$strings  = new \RockStarLab\ImportExport\Model\Format\Spreadsheet_String_Store( $dir . '/strings', $build['string_count'] );
					$formulas = new \RockStarLab\ImportExport\Model\Format\XLSX_Formula_Store( $dir . '/formulas' );
					$cells    = \RockStarLab\ImportExport\Model\Format\XLSX_Cell_Stream::cells( $parts, $build['map'], $strings, $build['cells'], $formulas );
				} else {
					$cells = \RockStarLab\ImportExport\Model\Format\ODS_Cell_Stream::cells( $parts, $build['selected'], $build['cells'], true ); }
				foreach ( $cells as $cell ) {
					self::write( $stream, base64_encode( serialize( $cell ) ) . "\n" ); }
				if ( ! fflush( $stream ) ) {
					throw new \RuntimeException( 'Cannot flush spreadsheet cells.' ); }
				$build['cell_bytes'] = ftell( $stream );
				if ( ! empty( $build['cells']['nodes']['done'] ) ) {
					unset( $build['cells'] );
					$build['phase']       = 'assemble';
					$build['cell_offset'] = 0;
					$build['repeat']      = 0; }
			} elseif ( 'assemble' === $build['phase'] ) {
				$store  = new \RockStarLab\ImportExport\Model\Format\Spreadsheet_Row_Store( $dir . '/rows', $build['width'] );
				$stream = fopen( $dir . '/cells', 'rb' );
				if ( ! $stream || fseek( $stream, $build['cell_offset'] ) !== 0 ) {
					throw new \RuntimeException( 'Invalid spreadsheet cell offset.' ); }
				$count = 0;
				$bytes = 0;
				while ( false !== ( $line = fgets( $stream ) ) ) {
					$cell = unserialize( base64_decode( rtrim( $line, "\n" ), true ), array( 'allowed_classes' => false ) );
					if ( ! is_array( $cell ) ) {
						throw new \RuntimeException( 'Invalid staged spreadsheet cell.' ); }
					$columns = $cell['repeat_columns'] ?? 1;
					$rows    = $cell['repeat_rows'] ?? 1;
					// Division avoids overflowing a repeated rectangle's total cell count.
					while ( intdiv( $build['repeat'], $columns ) < $rows ) {
						$current            = $cell;
						$current['row']    += intdiv( $build['repeat'], $columns );
						$current['column'] += $build['repeat'] % $columns;
						$store->append( $current );
						++$build['repeat'];
						++$count;
						$bytes += strlen( $cell['value'] );
						if ( $count >= 1000 || $bytes >= 1048576 ) {
							break 2; }
					}
					$build['cell_offset'] = ftell( $stream );
					$build['repeat']      = 0;
				}
				$build['width'] = $store->width();
				if ( feof( $stream ) && $count < 1000 && $bytes < 1048576 ) {
					$build['phase'] = 'snapshot'; }
			} elseif ( 'snapshot' === $build['phase'] ) {
				// Recover a rename whose subsequent job checkpoint was not committed.
				if ( ! is_file( $path . '.tmp' ) && is_file( $path ) && ! rename( $path, $path . '.tmp' ) ) {
					throw new \RuntimeException( 'Cannot recover spreadsheet snapshot.' ); }
				if ( 1 === $build['json_bytes'] ) {
					file_put_contents( $path . '.tmp', '[' ); }
				$stream           = self::output( $path . '.tmp', $build['json_bytes'] );
				$store            = new \RockStarLab\ImportExport\Model\Format\Spreadsheet_Row_Store( $dir . '/rows', $build['width'] );
				$state['headers'] = $store->headers( $request['options']['has_header'] ?? true );
				$count            = 0;
				$bytes            = 0;
				$done             = true;
				foreach ( $store->rows( $request['options']['has_header'] ?? true, $build['row'], true ) as $number => $row ) {
					$build['row'] = $number;
					if ( null !== $row ) {
						$json = wp_json_encode( $row );
						if ( false === $json ) {
							throw new \RuntimeException( 'Invalid spreadsheet row.' ); }
						self::write( $stream, ( $build['json_bytes'] > 1 ? ',' : '' ) . $json );
						++$state['total'];
						$state['rows_sha256'] = hash( 'sha256', $state['rows_sha256'] . $json );
						$build['json_bytes']  = ftell( $stream );
						$bytes               += strlen( $json );
					}
					if ( ++$count >= 100 || $bytes >= 1048576 ) {
						$done = false;
						break; }
				}
				if ( $done ) {
					self::write( $stream, ']' );
					if ( ! hash_equals( $state['sha256'], (string) hash_file( 'sha256', $request['file_path'] ) ) ) {
						throw new \RuntimeException( 'Changed spreadsheet source.' ); }
				}
				if ( ! fflush( $stream ) ) {
					throw new \RuntimeException( 'Cannot flush spreadsheet snapshot.' ); }
				fclose( $stream );
				$stream = null;
				if ( $done ) {
					if ( ! rename( $path . '.tmp', $path ) ) {
						throw new \RuntimeException( 'Cannot publish spreadsheet snapshot.' ); }
					unset( $state['build'] );
				}
			} else {
				throw new \RuntimeException( 'Invalid spreadsheet preparation phase.' ); }
			return $state;
		} finally {
			if ( is_resource( $stream ) ) {
				fclose( $stream ); }
			if ( $strings ) {
				$strings->close();
			} if ( $parts ) {
				$parts->close();
			} if ( $store ) {
				$store->close(); }
		}
	}

	/** Drop uncommitted tail bytes before replaying a slice. */
	private static function output( $path, $offset ) {
		$stream = fopen( $path, 'c+b' );
		if ( ! $stream ) {
			throw new \RuntimeException( 'Cannot open spreadsheet staging file.' ); }
		if ( fstat( $stream )['size'] < $offset || ! ftruncate( $stream, $offset ) || fseek( $stream, $offset ) !== 0 ) {
			fclose( $stream );
			throw new \RuntimeException( 'Invalid spreadsheet staging offset.' ); }
		return $stream;
	}

	/**
	 * Require a complete write.
	 *
	 * @param resource $stream Output stream.
	 * @param string   $value Bytes to write.
	 * @return void
	 * @throws \RuntimeException On short writes.
	 */
	private static function write( $stream, $value ) {
		if ( strlen( $value ) !== fwrite( $stream, $value ) ) {
			throw new \RuntimeException( 'Cannot write spreadsheet snapshot.' );
		}
	}

	/**
	 * Remove only this job's snapshot and unfinished publication.
	 *
	 * @param int $id Job ID.
	 * @return bool
	 */
	public static function discard( $id, $snapshot = null ) {
		$path = $snapshot ?? self::path( $id );
		$dir  = $path . '.work';
		if ( is_link( $dir ) ) {
			return false; }
		if ( is_dir( $dir ) ) {
			$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $dir, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );
			foreach ( $files as $file ) {
				if ( $file->isLink() || ! ( $file->isDir() ? rmdir( $file->getPathname() ) : unlink( $file->getPathname() ) ) ) {
					return false; }
			}
			if ( ! rmdir( $dir ) ) {
				return false; }
		}
		foreach ( array( $path, $path . '.tmp' ) as $file ) {
			if ( is_file( $file ) && ! unlink( $file ) ) {
				return false;
			}
		}
		return true;
	}
}

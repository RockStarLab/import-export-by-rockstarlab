<?php
/** Assemble unordered cells on disk; memory is bounded by one output row. */
namespace RockStarLab\ImportExport\Model\Format;

defined( 'ABSPATH' ) || exit;

class Spreadsheet_Row_Store {
	private $directory;
	private $width      = 1;
	private $persistent = false;

	public function __construct( $directory = null, $width = 1 ) {
		$this->persistent = null !== $directory;
		$this->width      = $width;
		$this->directory  = $directory ?? rtrim( get_temp_dir(), '/\\' ) . '/rsl-sheet-rows-' . bin2hex( random_bytes( 16 ) );
		if ( is_link( $this->directory ) || ( ! is_dir( $this->directory ) && ! mkdir( $this->directory, 0700 ) ) ) {
			throw new \RuntimeException( 'Cannot create spreadsheet row store.' ); }
	}

	public function append( array $cell ) {
		$row    = $cell['row'];
		$column = $cell['column'];
		$value  = $cell['value'];
		if ( ! is_int( $row ) || ! is_int( $column ) || $row < 1 || $column < 1 || ! is_string( $value ) ) {
			throw new \RuntimeException( 'Invalid spreadsheet cell record.' ); }
		$path      = $this->path( $row );
		$directory = dirname( $path );
		if ( ! is_dir( $directory ) && ! mkdir( $directory, 0700, true ) ) {
			throw new \RuntimeException( 'Cannot create row shard.' ); }
		$data   = serialize( [ $column, $value, ! empty( $cell['preserve_existing'] ) ] );
		$record = sprintf( '%020d', strlen( $data ) ) . $data;
		if ( strlen( $record ) !== file_put_contents( $path, $record, FILE_APPEND ) ) {
			throw new \RuntimeException( 'Cannot store spreadsheet cell.' ); }
		$this->width = max( $this->width, $column );
	}

	private function path( $row ) {
		// Fixed-width decimal paths sort numerically without collecting all row IDs.
		return $this->directory . '/' . implode( '/', str_split( sprintf( '%021d', $row ), 3 ) );
	}

	private function stored_rows( $directory, $prefix = '', $after = 0 ) {
		// Each level has at most 1,000 entries, even for widely separated coordinates.
		foreach ( scandir( $directory ) as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue; }
			$path   = $directory . '/' . $entry;
			$digits = $prefix . $entry;
			if ( strcmp( str_pad( $digits, 21, '9' ), sprintf( '%021d', $after ) ) <= 0 ) {
				continue; }
			if ( is_dir( $path ) ) {
				yield from $this->stored_rows( $path, $digits, $after ); } else {
				yield (int) $digits; }
		}
	}

	public function row( $row ) {
		$values = array_fill( 0, $this->width, '' );
		$path   = $this->path( $row );
		if ( ! is_file( $path ) ) {
			return $values; }
		$input = fopen( $path, 'rb' );
		if ( ! $input ) {
			throw new \RuntimeException( 'Cannot read spreadsheet row.' ); }
		try {
			while ( ! feof( $input ) ) {
				$header = fread( $input, 20 );
				if ( '' === $header && feof( $input ) ) {
					break; }
				if ( false === $header || strlen( $header ) !== 20 || ! ctype_digit( $header ) ) {
					throw new \RuntimeException( 'Invalid spreadsheet row index.' ); }
				$remaining = (int) $header;
				$data      = '';
				while ( $remaining > 0 ) {
					$chunk = fread( $input, min( 65536, $remaining ) );
					if ( false === $chunk || '' === $chunk ) {
						throw new \RuntimeException( 'Incomplete spreadsheet row.' ); }
					$data      .= $chunk;
					$remaining -= strlen( $chunk );
				}
				$cell = unserialize( $data, [ 'allowed_classes' => false ] );
				if ( ! is_array( $cell ) || count( $cell ) !== 3 || ! is_int( $cell[0] ) || $cell[0] < 1 || $cell[0] > $this->width || ! is_string( $cell[1] ) ) {
					throw new \RuntimeException( 'Invalid stored spreadsheet cell.' ); }
				if ( ! $cell[2] ) {
					$values[ $cell[0] - 1 ] = $cell[1]; }
			}
		} finally {
			fclose( $input ); }
		return $values;
	}

	public function headers( $has_header = true ) {
		return $has_header ? $this->row( 1 ) : array_map(
			static function ( $column ) {
				return 'Column ' . $column;
			},
			range( 1, $this->width )
		);
	}

	public function rows( $has_header = true, $after = 0, $include_empty = false ) {
		$headers = $this->headers( $has_header );
		foreach ( $this->stored_rows( $this->directory, '', $after ) as $row ) {
			if ( $has_header && 1 === $row ) {
				continue; }
			$values = $this->row( $row );
			$empty  = true;
			foreach ( $values as $value ) {
				if ( '' !== trim( $value ) ) {
					$empty = false;
					break; }
			}
			if ( ! $empty ) {
				yield $row => array_combine( $headers, $values );
			} elseif ( $include_empty ) {
				yield $row => null; }
		}
	}

	/** Derive preview and count from the same staged worksheet, retaining five rows. */
	public function preview( $has_header = true ) {
		$result = [
			'headers'    => $this->headers( $has_header ),
			'preview'    => [],
			'total_rows' => 0,
		];
		foreach ( $this->rows( $has_header ) as $row ) {
			if ( $result['total_rows'] < 5 ) {
				$result['preview'][] = $row;
			}
			++$result['total_rows'];
		}
		return $result;
	}

	public function width() {
		return $this->width; }

	public function close() {
		if ( $this->persistent ) {
			return; }
		if ( $this->directory && is_dir( $this->directory ) ) {
			$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->directory, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );
			foreach ( $files as $file ) {
				if ( $file->isDir() ) {
					rmdir( $file->getPathname() ); } else {
					unlink( $file->getPathname() ); }
			}
			rmdir( $this->directory );
		}
	}
	public function __destruct() {
		$this->close(); }
}

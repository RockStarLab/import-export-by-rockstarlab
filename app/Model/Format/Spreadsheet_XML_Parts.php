<?php
/** Bounded ZIP-to-XML staging for spreadsheet readers. */
namespace RockStarLab\ImportExport\Model\Format;

defined( 'ABSPATH' ) || exit;

class Spreadsheet_XML_Parts {
	private $zip;
	private $directory;
	private $paths      = array();
	private $persistent = false;

	public function __construct( $path, $directory = null ) {
		$this->zip = new \ZipArchive();
		if ( true !== $this->zip->open( $path ) ) {
			throw new \RuntimeException( __( 'Invalid spreadsheet archive.', 'import-export-by-rockstarlab' ) ); }
		$this->persistent = null !== $directory;
		$this->directory  = $directory ?? rtrim( get_temp_dir(), '/\\' ) . '/rsl-sheet-' . bin2hex( random_bytes( 16 ) );
		if ( is_link( $this->directory ) || ( ! is_dir( $this->directory ) && ! mkdir( $this->directory, 0700 ) ) ) {
			$this->zip->close();
			throw new \RuntimeException( __( 'Cannot stage spreadsheet XML.', 'import-export-by-rockstarlab' ) ); }
	}

	public function has( $name ) {
		return false !== $this->zip->locateName( $name ); }

	/** ZIP names are lookup keys only; they never become filesystem paths. */
	public function path( $name ) {
		if ( isset( $this->paths[ $name ] ) ) {
			return $this->paths[ $name ]; }
		$cached = $this->directory . '/' . hash( 'sha256', $name );
		if ( $this->persistent && is_file( $cached ) && is_file( $cached . '.ready' ) && ! is_link( $cached ) ) {
			return $this->paths[ $name ] = $cached;
		}
		// Match the legacy Xlsx reader's case-insensitive and Windows-separator lookup.
		$index = $this->zip->locateName( $name, \ZipArchive::FL_NOCASE );
		if ( false === $index ) {
			$index = $this->zip->locateName( str_replace( '/', '\\', $name ), \ZipArchive::FL_NOCASE ); }
		$stat = false === $index ? false : $this->zip->statIndex( $index );
		if ( ! $stat ) {
			throw new \RuntimeException( __( 'Missing spreadsheet part.', 'import-export-by-rockstarlab' ) ); }
		$input = $this->zip->getStream( $stat['name'] );
		if ( ! $input ) {
			throw new \RuntimeException( __( 'Cannot read spreadsheet part.', 'import-export-by-rockstarlab' ) ); }
		$path   = $this->directory . '/' . hash( 'sha256', $name );
		$output = null;
		try {
			$output = fopen( $path, $this->persistent ? 'wb' : 'xb' );
			if ( ! $output ) {
				throw new \RuntimeException( __( 'Cannot stage spreadsheet part.', 'import-export-by-rockstarlab' ) ); }
			$hash  = hash_init( 'crc32b' );
			$bytes = 0;
			while ( ! feof( $input ) ) {
				$chunk = fread( $input, 65536 );
				if ( false === $chunk || ( '' === $chunk && ! feof( $input ) ) ) {
					throw new \RuntimeException( __( 'Unreadable spreadsheet part.', 'import-export-by-rockstarlab' ) ); }
				$bytes += strlen( $chunk );
				if ( $bytes > $stat['size'] || strlen( $chunk ) !== fwrite( $output, $chunk ) ) {
					throw new \RuntimeException( __( 'Incomplete spreadsheet part.', 'import-export-by-rockstarlab' ) ); }
				hash_update( $hash, $chunk );
			}
			if ( $bytes !== $stat['size'] || hash_final( $hash ) !== sprintf( '%08x', $stat['crc'] ) ) {
				throw new \RuntimeException( __( 'Spreadsheet part integrity failure.', 'import-export-by-rockstarlab' ) ); }
			if ( $this->persistent && false === file_put_contents( $path . '.ready', '1' ) ) {
				throw new \RuntimeException( __( 'Cannot commit spreadsheet part.', 'import-export-by-rockstarlab' ) ); }
			$this->paths[ $name ] = $path;
			return $path;
		} finally {
			fclose( $input );
			if ( is_resource( $output ) ) {
				fclose( $output ); }
			if ( ! isset( $this->paths[ $name ] ) && is_file( $path ) ) {
				unlink( $path ); }
		}
	}

	/** Stage a large XML member over requests before opening its parser. */
	public function stage( $name, array &$cursor ) {
		$path = $this->directory . '/' . hash( 'sha256', $name );
		if ( is_link( $path ) || is_link( $path . '.ready' ) ) {
			throw new \RuntimeException( __( 'Unsafe spreadsheet part.', 'import-export-by-rockstarlab' ) ); }
		$index = $this->zip->locateName( $name, \ZipArchive::FL_NOCASE );
		if ( false === $index ) {
			$index = $this->zip->locateName( str_replace( '/', '\\', $name ), \ZipArchive::FL_NOCASE ); }
		$stat = false === $index ? false : $this->zip->statIndex( $index );
		if ( ! $stat ) {
			throw new \RuntimeException( __( 'Missing spreadsheet part.', 'import-export-by-rockstarlab' ) ); }
		$result = ( new \RockStarLab\ImportExport\Service\Migration_Package_Service() )->copy_entry( $this->zip, $stat['name'], $path, $cursor );
		if ( is_wp_error( $result ) ) {
			throw new \RuntimeException( __( 'Invalid spreadsheet part.', 'import-export-by-rockstarlab' ) ); }
		if ( is_array( $result ) ) {
			return false; }
		if ( false === file_put_contents( $path . '.ready', '1' ) ) {
			throw new \RuntimeException( __( 'Cannot commit spreadsheet part.', 'import-export-by-rockstarlab' ) ); }
		return true;
	}

	/** Visit XML nodes without enabling external entities or document types. */
	public function nodes( $name, &$checkpoint = null ) {
		if ( func_num_args() > 1 ) {
			yield from $this->slice_nodes( $name, $checkpoint );
			return; }
		$reader   = new \XMLReader();
		$previous = libxml_use_internal_errors( true );
		libxml_clear_errors();
		try {
			if ( ! $reader->open( $this->path( $name ), null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE ) ) {
				throw new \RuntimeException( __( 'Invalid spreadsheet XML.', 'import-export-by-rockstarlab' ) ); }
			while ( $reader->read() ) {
				if ( \XMLReader::DOC_TYPE === $reader->nodeType ) {
					throw new \RuntimeException( __( 'Spreadsheet document types are unsupported.', 'import-export-by-rockstarlab' ) ); }
				yield $reader;
			}
			foreach ( libxml_get_errors() as $error ) {
				if ( $error->level >= LIBXML_ERR_ERROR ) {
					throw new \RuntimeException( __( 'Invalid spreadsheet XML.', 'import-export-by-rockstarlab' ) ); }
			}
		} finally {
			$reader->close();
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );
		}
	}

	/** Read a bounded set of complete cell/string fragments from a byte checkpoint. */
	private function slice_nodes( $name, &$checkpoint ) {
		$state   = $checkpoint['xml'] ?? array(
			'offset'      => 0,
			'stack'       => array(),
			'roots'       => 0,
			'declaration' => '',
		);
		$input   = new XML_Fragment_Stream( $this->path( $name ), $state['offset'] );
		$records = 0;
		$bytes   = 0;
		foreach ( $input->segments( array( 'c', 'si', 'table-cell', 'covered-table-cell', 'config-item' ), $state, 65536 ) as $segment ) {
			$nonce = bin2hex( random_bytes( 16 ) );
			$lead  = '';
			$body  = $segment['body'];
			if ( '' === $segment['prefix'] && preg_match( '/^(?:\xEF\xBB\xBF)?<\?xml\s[^?]*\?>/', $body, $declaration ) ) {
				$lead = $declaration[0];
				$body = substr( $body, strlen( $lead ) ); }
			$xml      = $lead . $segment['prefix'] . '<?rsl-slice ' . $nonce . '-start?>' . $body . '<?rsl-slice ' . $nonce . '-end?>' . $segment['suffix'];
			$reader   = new \XMLReader();
			$previous = libxml_use_internal_errors( true );
			libxml_clear_errors();
			try {
				if ( ! $reader->XML( $xml, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE ) ) {
					throw new \RuntimeException( __( 'Invalid spreadsheet XML.', 'import-export-by-rockstarlab' ) ); }
				$inside = false;
				while ( $reader->read() ) {
					if ( \XMLReader::PI === $reader->nodeType && 'rsl-slice' === $reader->name ) {
						if ( $nonce . '-start' === $reader->value ) {
							$inside = true;
							continue; }
						if ( $nonce . '-end' === $reader->value ) {
							$inside = false;
							continue; }
					}
					if ( $inside ) {
						yield $reader; }
				}
				foreach ( libxml_get_errors() as $error ) {
					if ( $error->level >= LIBXML_ERR_ERROR ) {
						throw new \RuntimeException( __( 'Invalid spreadsheet XML.', 'import-export-by-rockstarlab' ) ); }
				}
			} finally {
				$reader->close();
				libxml_clear_errors();
				libxml_use_internal_errors( $previous ); }
			$checkpoint = array(
				'xml'  => $segment['next'],
				'done' => ! empty( $segment['eof'] ),
			);
			$bytes     += strlen( $segment['body'] );
			if ( ++$records >= 100 || $bytes >= 1048576 ) {
				return; }
		}
	}

	/** Release only after readers for this part have closed; it can be staged again. */
	public function release( $name ) {
		if ( $this->persistent ) {
			return; }
		if ( ! isset( $this->paths[ $name ] ) ) {
			return; }
		$path = $this->paths[ $name ];
		if ( is_file( $path ) && ! unlink( $path ) ) {
			throw new \RuntimeException( __( 'Cannot release spreadsheet part.', 'import-export-by-rockstarlab' ) ); }
		unset( $this->paths[ $name ] );
	}

	public function close() {
		if ( $this->zip ) {
			$this->zip->close();
			$this->zip = null; }
		if ( ! $this->persistent && $this->directory && is_dir( $this->directory ) ) {
			foreach ( new \DirectoryIterator( $this->directory ) as $file ) {
				if ( ! $file->isDot() ) {
					unlink( $file->getPathname() ); }
			}
			rmdir( $this->directory );
		}
		$this->paths = array();
	}

	public function __destruct() {
		$this->close(); }
}

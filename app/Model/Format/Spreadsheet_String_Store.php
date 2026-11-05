<?php
/** Disk-backed shared-string lookup with constant-size index entries. */
namespace RockStarLab\ImportExport\Model\Format;

defined( 'ABSPATH' ) || exit;

class Spreadsheet_String_Store {
	private $index;
	private $values;
	private $count = 0;

	public function __construct( $path = null, $count = 0 ) {
		$this->index  = null === $path ? tmpfile() : fopen( $path . '.index', 'c+b' );
		$this->values = null === $path ? tmpfile() : fopen( $path . '.values', 'c+b' );
		if ( ! $this->index || ! $this->values ) {
			$this->close();
			throw new \RuntimeException( 'Cannot create spreadsheet string index.' ); }
		if ( null !== $path ) {
			$length = 0;
			if ( $count ) {
				if ( fseek( $this->index, ( $count - 1 ) * 40 ) !== 0 ) {
					throw new \RuntimeException( 'Invalid string checkpoint.' ); }
				$last = fread( $this->index, 40 );
				if ( strlen( $last ) !== 40 || ! ctype_digit( $last ) ) {
					throw new \RuntimeException( 'Invalid string checkpoint.' ); }
				$length = (int) substr( $last, 0, 20 ) + (int) substr( $last, 20 );
			}
			if ( fstat( $this->values )['size'] < $length || ! ftruncate( $this->index, $count * 40 ) || ! ftruncate( $this->values, $length ) ) {
				throw new \RuntimeException( 'Invalid string checkpoint.' ); }
			$this->count = $count;
		}
	}

	/** Load one shared string at a time; formatting and phonetic hints are not values. */
	public static function from_xml( Spreadsheet_XML_Parts $parts, $name, $store = null, &$checkpoint = null ) {
		$resume = func_num_args() > 3;
		$store  = $store ?? new self();
		try {
			$root      = $checkpoint['root'] ?? false;
			$namespace = $checkpoint['namespace'] ?? null;
			foreach ( ( $resume ? $parts->nodes( $name, $checkpoint['nodes'] ) : $parts->nodes( $name ) ) as $node ) {
				if ( \XMLReader::ELEMENT !== $node->nodeType ) {
					continue; }
				if ( 0 === $node->depth ) {
					if ( 'sst' !== $node->localName || ! in_array( $node->namespaceURI, [ 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'http://purl.oclc.org/ooxml/spreadsheetml/main' ], true ) ) {
						throw new \RuntimeException( 'Invalid shared-string root.' ); }
					$root      = true;
					$namespace = $node->namespaceURI;
				}
				if ( 1 !== $node->depth || 'si' !== $node->localName ) {
					continue; }
				if ( $node->namespaceURI !== $namespace ) {
					throw new \RuntimeException( 'Invalid shared-string namespace.' ); }
				$xml = simplexml_load_string( $node->readOuterXml(), \SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE );
				if ( false === $xml ) {
					throw new \RuntimeException( 'Invalid shared string.' ); }
				$item  = $xml->children( $namespace );
				$value = '';
				if ( isset( $item->t ) ) {
					$value = \PhpOffice\PhpSpreadsheet\Shared\StringHelper::controlCharacterOOXML2PHP( (string) $item->t );
				} else {
					foreach ( $item->r as $run ) {
						$value .= \PhpOffice\PhpSpreadsheet\Shared\StringHelper::controlCharacterOOXML2PHP( (string) $run->t );
					}
				}
				$store->append( $value );
			}
			if ( $resume ) {
				$checkpoint['root']      = $root;
				$checkpoint['namespace'] = $namespace; }
			if ( ! $root && ( ! $resume || ! empty( $checkpoint['nodes']['done'] ) ) ) {
				throw new \RuntimeException( 'Missing shared-string root.' ); }
			return $store;
		} catch ( \Throwable $error ) {
			$store->close();
			throw $error; }
	}

	public function count() {
		return $this->count; }

	public function append( $value ) {
		if ( ! is_string( $value ) || 0 !== fseek( $this->values, 0, SEEK_END ) || 0 !== fseek( $this->index, 0, SEEK_END ) ) {
			throw new \RuntimeException( 'Invalid spreadsheet string.' ); }
		$offset = ftell( $this->values );
		$length = strlen( $value );
		$entry  = sprintf( '%020d%020d', $offset, $length );
		if ( 40 !== strlen( $entry ) || $length !== fwrite( $this->values, $value ) || 40 !== fwrite( $this->index, $entry ) ) {
			throw new \RuntimeException( 'Cannot store spreadsheet string.' ); }
		return $this->count++;
	}

	public function get( $id ) {
		if ( ! is_int( $id ) || $id < 0 || $id >= $this->count || 0 !== fseek( $this->index, $id * 40 ) ) {
			throw new \RuntimeException( 'Invalid shared-string reference.' ); }
		$entry = fread( $this->index, 40 );
		if ( strlen( $entry ) !== 40 || ! ctype_digit( $entry ) ) {
			throw new \RuntimeException( 'Invalid spreadsheet string index.' ); }
		$offset    = (int) substr( $entry, 0, 20 );
		$remaining = (int) substr( $entry, 20 );
		if ( 0 !== fseek( $this->values, $offset ) ) {
			throw new \RuntimeException( 'Cannot seek spreadsheet string.' ); }
		$value = '';
		while ( $remaining ) {
			$chunk = fread( $this->values, min( 65536, $remaining ) );
			if ( false === $chunk || '' === $chunk ) {
				throw new \RuntimeException( 'Incomplete spreadsheet string.' ); }
			$value     .= $chunk;
			$remaining -= strlen( $chunk );
		}
		return $value;
	}

	public function close() {
		if ( is_resource( $this->index ) ) {
			fclose( $this->index ); }
		if ( is_resource( $this->values ) ) {
			fclose( $this->values ); }
		$this->index  = null;
		$this->values = null;
		$this->count  = 0;
	}
	public function __destruct() {
		$this->close(); }
}

<?php
/** Resolve import parts without retaining the package relationship tables. */
namespace RockStarLab\ImportExport\Model\Format;

defined( 'ABSPATH' ) || exit;

class XLSX_Part_Map {

	private const MAIN = [ 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'http://purl.oclc.org/ooxml/spreadsheetml/main' ];
	private const REL  = [ 'http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'http://purl.oclc.org/ooxml/officeDocument/relationships' ];

	/** Data-only XLSX imports use the first sheet, independently of activeTab. */
	public static function resolve( Spreadsheet_XML_Parts $parts, $sheet_index = 0 ) {
		if ( ! is_int( $sheet_index ) || $sheet_index < 0 ) {
			throw new \RuntimeException( 'Invalid worksheet index.' ); }
		$sheet_count = 0;
		$workbook    = null;
		foreach ( self::relationships( $parts, '_rels/.rels' ) as $rel ) {
			if ( self::type( $rel['type'], 'officeDocument' ) ) {
				if ( null !== $workbook ) {
					throw new \RuntimeException( 'Ambiguous spreadsheet workbook.' ); }
				$workbook = self::target( '', $rel );
			}
		}
		if ( null === $workbook ) {
			throw new \RuntimeException( 'Missing spreadsheet workbook.' ); }
		$calendar       = 1900;
		$id             = null;
		$root           = false;
		$in_sheets      = false;
		$main_namespace = null;
		foreach ( $parts->nodes( $workbook ) as $node ) {
			if ( \XMLReader::END_ELEMENT === $node->nodeType && 1 === $node->depth ) {
				$in_sheets = false; }
			if ( \XMLReader::ELEMENT !== $node->nodeType ) {
				continue; }
			if ( 0 === $node->depth ) {
				$root = 'workbook' === $node->localName && in_array( $node->namespaceURI, self::MAIN, true );
				if ( ! $root ) {
					throw new \RuntimeException( 'Invalid spreadsheet workbook.' ); }
				$main_namespace = $node->namespaceURI;
			}
			if ( 1 === $node->depth && 'workbookPr' === $node->localName && $main_namespace === $node->namespaceURI ) {
				$value    = (string) $node->getAttribute( 'date1904' );
				$calendar = ( is_numeric( $value ) ? (bool) $value : in_array( $value, [ 'true', 'TRUE' ], true ) ) ? 1904 : 1900;
			}
			if ( 1 === $node->depth ) {
				$in_sheets = 'sheets' === $node->localName && $main_namespace === $node->namespaceURI && ! $node->isEmptyElement; }
			if ( $in_sheets && 2 === $node->depth && 'sheet' === $node->localName && $main_namespace === $node->namespaceURI ) {
				if ( $sheet_count++ !== $sheet_index ) {
					continue; }
				foreach ( self::REL as $namespace ) {
					$value = $node->getAttributeNs( 'id', $namespace );
					if ( null !== $value && '' !== $value ) {
						$id = $value;
						break; }
				}
				if ( null === $id ) {
					throw new \RuntimeException( 'Missing spreadsheet sheet relationship.' ); }
			}
		}
		if ( ! $root || null === $id ) {
			throw new \RuntimeException( 'Missing spreadsheet sheet.' ); }
		$directory = dirname( $workbook );
		$rels      = ( '.' === $directory ? '' : $directory . '/' ) . '_rels/' . basename( $workbook ) . '.rels';
		$result    = [
			'worksheet' => null,
			'strings'   => null,
			'styles'    => null,
		];
		foreach ( self::relationships( $parts, $rels ) as $rel ) {
			$key = null;
			if ( $id === $rel['id'] ) {
				if ( ! self::type( $rel['type'], 'worksheet' ) ) {
					throw new \RuntimeException( 'Unsupported spreadsheet sheet type.' ); }
				$key = 'worksheet';
			} elseif ( self::type( $rel['type'], 'sharedStrings' ) ) {
				$key = 'strings';
			} elseif ( self::type( $rel['type'], 'styles' ) ) {
				$key = 'styles'; }
			if ( null === $key ) {
				continue; }
			if ( null !== $result[ $key ] ) {
				throw new \RuntimeException( 'Ambiguous spreadsheet relationship.' ); }
			$result[ $key ] = self::target( $workbook, $rel );
		}
		if ( null === $result['worksheet'] ) {
			throw new \RuntimeException( 'Missing spreadsheet worksheet relationship.' ); }
		$result['calendar']    = $calendar;
		$result['sheet_count'] = $sheet_count;
		$result['decimals']    = null === $result['styles'] ? [] : self::fixed_decimals( $parts, $result['styles'] );
		unset( $result['styles'] );
		return $result;
	}

	/**
	 * Cell styles that show a plain number with a fixed count of decimals (0.00, #,##0.00).
	 *
	 * The worksheet stores 13.80 as 13.8; the style says how many decimals were shown,
	 * so the importer can read back the same text that was exported.
	 *
	 * @return array<int,int> Style index => decimals.
	 */
	private static function fixed_decimals( Spreadsheet_XML_Parts $parts, $path ) {
		$codes    = [ 2 => 2, 4 => 2 ];
		$decimals = [];
		$in_xfs   = false;
		$index    = 0;
		try {
			foreach ( $parts->nodes( $path ) as $node ) {
				if ( \XMLReader::END_ELEMENT === $node->nodeType && 1 === $node->depth ) {
					$in_xfs = false; }
				if ( \XMLReader::ELEMENT !== $node->nodeType ) {
					continue; }
				if ( 0 === $node->depth && ( 'styleSheet' !== $node->localName || ! in_array( $node->namespaceURI, self::MAIN, true ) ) ) {
					return []; }
				if ( 2 === $node->depth && 'numFmt' === $node->localName ) {
					if ( preg_match( '/^(?:#,##)?0\.(0{1,10})$/', (string) $node->getAttribute( 'formatCode' ), $match ) ) {
						$codes[ (int) $node->getAttribute( 'numFmtId' ) ] = strlen( $match[1] ); }
					continue;
				}
				if ( 1 === $node->depth ) {
					$in_xfs = 'cellXfs' === $node->localName && ! $node->isEmptyElement; }
				if ( $in_xfs && 2 === $node->depth && 'xf' === $node->localName ) {
					$format = (int) $node->getAttribute( 'numFmtId' );
					if ( isset( $codes[ $format ] ) && count( $decimals ) < 4096 ) {
						$decimals[ $index ] = $codes[ $format ]; }
					++$index;
				}
			}
		} catch ( \RuntimeException $e ) {
			// Styles are optional for data import; a broken styles part only loses decimals.
			return [];
		}
		return $decimals;
	}

	/** Yield theme parts without collecting relationship records. */
	public static function themes( Spreadsheet_XML_Parts $parts ) {
		foreach ( self::relationships( $parts, '_rels/.rels' ) as $root ) {
			if ( ! self::type( $root['type'], 'officeDocument' ) ) {
				continue; }
			$workbook  = self::target( '', $root );
			$directory = dirname( $workbook );
			$rels      = ( '.' === $directory ? '' : $directory . '/' ) . '_rels/' . basename( $workbook ) . '.rels';
			foreach ( self::relationships( $parts, $rels ) as $rel ) {
				if ( self::type( $rel['type'], 'theme' ) ) {
					yield self::target( $workbook, $rel ); }
			}
		}
	}

	private static function relationships( Spreadsheet_XML_Parts $parts, $name ) {
		$namespace = 'http://schemas.openxmlformats.org/package/2006/relationships';
		$root      = false;
		foreach ( $parts->nodes( $name ) as $node ) {
			if ( \XMLReader::ELEMENT !== $node->nodeType ) {
				continue; }
			if ( 0 === $node->depth ) {
				$root = 'Relationships' === $node->localName && $namespace === $node->namespaceURI;
				if ( ! $root ) {
					throw new \RuntimeException( 'Invalid spreadsheet relationships.' ); }
			}
			if ( 1 === $node->depth && 'Relationship' === $node->localName && $namespace === $node->namespaceURI ) {
				yield [
					'id'     => $node->getAttribute( 'Id' ),
					'type'   => $node->getAttribute( 'Type' ),
					'target' => $node->getAttribute( 'Target' ),
					'mode'   => $node->getAttribute( 'TargetMode' ),
				];
			}
		}
		if ( ! $root ) {
			throw new \RuntimeException( 'Missing spreadsheet relationships.' ); }
	}

	private static function type( $type, $suffix ) {
		foreach ( self::REL as $namespace ) {
			if ( $namespace . '/' . $suffix === $type ) {
				return true; }
		}
		return false;
	}

	private static function target( $source, array $rel ) {
		$target = $rel['target'];
		if ( ! in_array( $rel['mode'], [ null, '', 'Internal' ], true ) || ! is_string( $target ) || '' === $target || preg_match( '/[\\\\\\x00-\\x20?#:]|^\/\//', $target ) ) {
			throw new \RuntimeException( 'Invalid spreadsheet part target.' ); }
		$directory = dirname( $source );
		$path      = '/' === $target[0] || '.' === $directory ? $target : $directory . '/' . $target;
		$segments  = [];
		foreach ( explode( '/', $path ) as $segment ) {
			if ( '' === $segment || '.' === $segment ) {
				continue; }
			if ( '..' === $segment ) {
				if ( ! $segments ) {
					throw new \RuntimeException( 'Spreadsheet target escapes package.' ); }
				array_pop( $segments );
			} else {
				$segments[] = $segment; }
		}
		if ( ! $segments ) {
			throw new \RuntimeException( 'Empty spreadsheet part target.' ); }
		return implode( '/', $segments );
	}
}

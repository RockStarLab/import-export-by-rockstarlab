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
		$result['dates']       = null === $result['styles'] ? [] : self::date_styles( $parts, $result['styles'] );
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

	/**
	 * Index of the worksheet to import: the first sheet that has a header row and at least
	 * one row of data. A workbook that starts with a cover or notes sheet is read from the
	 * sheet that holds the table. Falls back to the first sheet.
	 *
	 * @param Spreadsheet_XML_Parts $parts Workbook parts.
	 * @return int
	 */
	public static function data_sheet_index( Spreadsheet_XML_Parts $parts ) {
		$first = self::resolve( $parts );
		if ( $first['sheet_count'] < 2 ) {
			return 0; }
		$strings = $first['strings'] ? Spreadsheet_String_Store::from_xml( $parts, $first['strings'] ) : null;
		try {
			for ( $index = 0; $index < min( $first['sheet_count'], 20 ); ++$index ) {
				$map  = $index ? self::resolve( $parts, $index ) : $first;
				$rows = [];
				try {
					foreach ( XLSX_Cell_Stream::cells( $parts, $map, $strings ) as $cell ) {
						if ( '' !== trim( (string) $cell['value'] ) ) {
							$rows[ $cell['row'] ] = true;
							if ( count( $rows ) >= 2 ) {
								return $index; }
						}
					}
				} catch ( \Exception $e ) {
					// A broken first sheet must still fail validation; later broken sheets are skipped.
					if ( ! $index ) {
						return 0; }
					continue;
				} finally {
					$parts->release( $map['worksheet'] ); }
			}
		} finally {
			if ( $strings ) {
				$strings->close(); }
		}
		return 0;
	}

	/**
	 * Cell styles that show a number as a date or time. The worksheet stores dates as
	 * serial numbers (45366.39 = 15 March 2024 09:30), which are useless after import.
	 *
	 * @return array<int,string> Style index => 'date', 'datetime' or 'time'.
	 */
	private static function date_styles( Spreadsheet_XML_Parts $parts, $path ) {
		$codes  = [ 14 => 'date', 15 => 'date', 16 => 'date', 17 => 'date', 22 => 'datetime', 18 => 'time', 19 => 'time', 20 => 'time', 21 => 'time', 45 => 'time', 46 => 'time', 47 => 'time' ];
		$styles = [];
		$in_xfs = false;
		$index  = 0;
		try {
			foreach ( $parts->nodes( $path ) as $node ) {
				if ( \XMLReader::END_ELEMENT === $node->nodeType && 1 === $node->depth ) {
					$in_xfs = false; }
				if ( \XMLReader::ELEMENT !== $node->nodeType ) {
					continue; }
				if ( 0 === $node->depth && ( 'styleSheet' !== $node->localName || ! in_array( $node->namespaceURI, self::MAIN, true ) ) ) {
					return []; }
				if ( 2 === $node->depth && 'numFmt' === $node->localName ) {
					$kind = self::date_kind( (string) $node->getAttribute( 'formatCode' ) );
					if ( $kind ) {
						$codes[ (int) $node->getAttribute( 'numFmtId' ) ] = $kind; }
					continue;
				}
				if ( 1 === $node->depth ) {
					$in_xfs = 'cellXfs' === $node->localName && ! $node->isEmptyElement; }
				if ( $in_xfs && 2 === $node->depth && 'xf' === $node->localName ) {
					$format = (int) $node->getAttribute( 'numFmtId' );
					if ( isset( $codes[ $format ] ) && count( $styles ) < 4096 ) {
						$styles[ $index ] = $codes[ $format ]; }
					++$index;
				}
			}
		} catch ( \RuntimeException $e ) {
			return [];
		}
		return $styles;
	}

	/** Whether a custom number format shows a date, a date and time, or a time. */
	private static function date_kind( $code ) {
		// Drop quoted text, escaped characters, colors/conditions and the elapsed-time brackets.
		$plain = preg_replace( [ '/"[^"]*"/', '/\\\\./', '/\[[^\]]*\]/' ], '', strtolower( explode( ';', $code )[0] ) );
		$date  = (bool) preg_match( '/[dy]|m{3,}/', $plain );
		$time  = (bool) preg_match( '/[hs]/', $plain );
		if ( $date && $time ) {
			return 'datetime'; }
		if ( $date ) {
			return 'date'; }
		return $time ? 'time' : null;
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

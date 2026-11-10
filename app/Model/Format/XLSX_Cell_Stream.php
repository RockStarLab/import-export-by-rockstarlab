<?php
/** Iterate worksheet cells in source order, without materializing rows or sheets. */
namespace RockStarLab\ImportExport\Model\Format;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;

defined( 'ABSPATH' ) || exit;

class XLSX_Cell_Stream {
	public static function cells( Spreadsheet_XML_Parts $parts, array $map, ?Spreadsheet_String_Store $shared_strings = null, &$checkpoint = null, $formula_store = null ) {
		$resume       = func_num_args() > 3;
		$strings      = $shared_strings;
		$owns_strings = false;
		$formulas     = null;
		$decoder      = null;
		try {
			if ( null === $strings && null !== $map['strings'] ) {
				$strings      = Spreadsheet_String_Store::from_xml( $parts, $map['strings'] );
				$owns_strings = true; }
			$formulas  = $formula_store ?? new XLSX_Formula_Store();
			$decoder   = new XLSX_Cell_Decoder( $map['calendar'] );
			$namespace = null;
			$in_data   = false;
			$in_row    = false;
			$row       = 0;
			$column    = 0;
			foreach ( [ 'namespace', 'in_data', 'in_row', 'row', 'column' ] as $field ) {
				if ( isset( $checkpoint['state'] ) && array_key_exists( $field, $checkpoint['state'] ) ) {
					$$field = $checkpoint['state'][ $field ]; }
			}
			foreach ( ( $resume ? $parts->nodes( $map['worksheet'], $checkpoint['nodes'] ) : $parts->nodes( $map['worksheet'] ) ) as $node ) {
				if ( \XMLReader::END_ELEMENT === $node->nodeType ) {
					if ( 1 === $node->depth ) {
						$in_data = false; }
					if ( 2 === $node->depth ) {
						$in_row = false; }
					continue;
				}
				if ( \XMLReader::ELEMENT !== $node->nodeType ) {
					continue; }
				if ( 0 === $node->depth ) {
					$namespace = $node->namespaceURI;
					if ( 'worksheet' !== $node->localName || ! in_array( $namespace, [ 'http://schemas.openxmlformats.org/spreadsheetml/2006/main', 'http://purl.oclc.org/ooxml/spreadsheetml/main' ], true ) ) {
						throw new \RuntimeException( 'Invalid worksheet root.' ); }
				}
				if ( 1 === $node->depth ) {
					$in_data = 'sheetData' === $node->localName && $namespace === $node->namespaceURI && ! $node->isEmptyElement; }
				if ( 2 === $node->depth ) {
					$in_row = $in_data && 'row' === $node->localName && $namespace === $node->namespaceURI;
					if ( $in_row ) {
						++$row;
						$column = 0; }
				}
				if ( ! $in_row || 3 !== $node->depth || 'c' !== $node->localName || $namespace !== $node->namespaceURI ) {
					continue; }
				++$column;
				$coordinate = $node->getAttribute( 'r' );
				// Legacy Xlsx uses source row/cell ordinals when r is absent, not row/@r.
				if ( null === $coordinate || '' === $coordinate ) {
					$coordinate = Coordinate::stringFromColumnIndex( $column ) . $row; }
				$indices = Coordinate::indexesFromString( $coordinate );
				$xml     = simplexml_load_string( $node->readOuterXml(), \SimpleXMLElement::class, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE );
				if ( false === $xml ) {
					throw new \RuntimeException( 'Invalid worksheet cell.' ); }
				$value = $decoder->decode( $xml, $namespace, $coordinate, $strings, [ $formulas, 'resolve' ] );
				$style = $node->getAttribute( 's' );
				if ( ! empty( $map['decimals'] ) && null !== $style && '' !== $style && isset( $map['decimals'][ (int) $style ] ) && in_array( (string) $node->getAttribute( 't' ), [ '', 'n' ], true ) ) {
					$value = self::pad_decimals( $value, $map['decimals'][ (int) $style ] ); }
				yield [
					'row'               => $indices[1],
					'column'            => $indices[0],
					'value'             => $value,
					'preserve_existing' => $decoder->preserves_existing(),
				];
			}
			if ( $resume ) {
				foreach ( [ 'namespace', 'in_data', 'in_row', 'row', 'column' ] as $field ) {
					$checkpoint['state'][ $field ] = $$field; }
			}
			if ( null === $namespace && ( ! $resume || ! empty( $checkpoint['nodes']['done'] ) ) ) {
				throw new \RuntimeException( 'Missing worksheet root.' ); }
		} finally {
			if ( $decoder ) {
				$decoder->close(); }
			if ( $formulas ) {
				$formulas->close(); }
			if ( $owns_strings && $strings ) {
				$strings->close(); }
		}
	}

	/**
	 * Add the trailing zeros a fixed-decimals style shows (13.8 shown as 13.80).
	 * Never rounds: a value with more decimals than the style keeps all of them.
	 *
	 * @param mixed $value    Decoded cell value.
	 * @param int   $decimals Decimals shown by the cell style.
	 * @return mixed
	 */
	private static function pad_decimals( $value, $decimals ) {
		if ( ! is_string( $value ) || ! preg_match( '/^-?\d+(?:\.(\d*))?$/', $value, $match ) ) {
			return $value; }
		$current = isset( $match[1] ) ? strlen( $match[1] ) : 0;
		if ( $current >= $decimals ) {
			return $value; }
		return ( isset( $match[1] ) ? $value : $value . '.' ) . str_repeat( '0', $decimals - $current );
	}
}

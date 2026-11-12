<?php
/** Decode a single ODS cell, preserving the installed data-only reader's values. */
namespace RockStarLab\ImportExport\Model\Format;

use PhpOffice\PhpSpreadsheet\Reader\Ods;
use PhpOffice\PhpSpreadsheet\Reader\Ods\FormulaTranslator;
use PhpOffice\PhpSpreadsheet\Shared\Date;

defined( 'ABSPATH' ) || exit;

class ODS_Cell_Decoder extends Ods {
	public function decode( \DOMElement $cell ) {
		$office = 'urn:oasis:names:tc:opendocument:xmlns:office:1.0';
		$table  = 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';
		// A formula with a saved result imports the result, as the sheet shows it.
		if ( $cell->hasAttributeNS( $table, 'formula' ) && ( ! $cell->hasAttributeNS( $office, 'value-type' ) || 0 === $cell->getElementsByTagNameNS( 'urn:oasis:names:tc:opendocument:xmlns:text:1.0', 'p' )->length ) ) {
			$formula = $cell->getAttributeNS( $table, 'formula' );
			return FormulaTranslator::convertToExcelFormulaValue( substr( $formula, strpos( $formula, ':=' ) + 1 ) );
		}
		$text_namespace = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';
		// The inherited text walker expects canonical prefixes; XML prefixes are arbitrary.
		foreach ( $cell->getElementsByTagNameNS( $text_namespace, '*' ) as $element ) {
			$element->prefix = 'text'; }
		$paragraphs = [];
		foreach ( $cell->childNodes as $child ) {
			// Match the legacy reader's paragraph selection and text traversal.
			if ( 'p' === $child->localName && $text_namespace === $child->namespaceURI ) {
				$paragraphs[] = $this->scanElementForText( $child ); }
		}
		if ( ! $paragraphs ) {
			return ''; }
		$text = implode( "\n", $paragraphs );
		switch ( $cell->getAttributeNS( $office, 'value-type' ) ) {
			case 'string':
				// Keep newline normalization without the spreadsheet writer's text limit.
				return str_replace( [ "\r\n", "\r" ], "\n", $text );
			case 'boolean':
				return 'TRUE' === $text ? '1' : '0';
			case 'percentage':
			case 'currency':
			case 'float':
				$number = (string) (float) $cell->getAttributeNS( $office, 'value' );
				// Shown as 13.80 but stored as 13.8: keep the shown text when it is the same number with trailing zeros.
				if ( preg_match( '/^-?\d+\.\d*0$/', $text ) && rtrim( rtrim( $text, '0' ), '.' ) === $number ) {
					return $text; }
				return $number;
			case 'date':
				// Dates import as text (2024-03-15 or 2024-03-15 09:30:00), not as spreadsheet serial numbers.
				$date = (string) $cell->getAttributeNS( $office, 'date-value' );
				if ( preg_match( '/^(\d{4}-\d{2}-\d{2})(?:T(\d{2}:\d{2}:\d{2}))?/', $date, $parts ) ) {
					return empty( $parts[2] ) || '00:00:00' === $parts[2] ? $parts[1] : $parts[1] . ' ' . $parts[2]; }
				return (string) Date::convertIsoDate( $date );
			case 'time':
				$time = sscanf( $cell->getAttributeNS( $office, 'time-value' ), 'PT%dH%dM%dS' );
				if ( is_array( $time ) && 3 === count( array_filter( $time, 'is_int' ) ) ) {
					return sprintf( '%02d:%02d:%02d', $time[0], $time[1], $time[2] ); }
				return (string) Date::PHPToExcel( strtotime( '01-01-1970 ' . implode( ':', $time ?? [] ) ) );
			default:
				return '';
		}
	}
}

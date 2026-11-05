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
		if ( $cell->hasAttributeNS( $table, 'formula' ) ) {
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
				return (string) (float) $cell->getAttributeNS( $office, 'value' );
			case 'date':
				return (string) Date::convertIsoDate( $cell->getAttributeNS( $office, 'date-value' ) );
			case 'time':
				return (string) Date::PHPToExcel( strtotime( '01-01-1970 ' . implode( ':', sscanf( $cell->getAttributeNS( $office, 'time-value' ), 'PT%dH%dM%dS' ) ?? [] ) ) );
			default:
				return '';
		}
	}
}

<?php
/** Normalize one XLSX cell using the same value binder as the legacy reader. */
namespace RockStarLab\ImportExport\Model\Format;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Shared\StringHelper;

defined( 'ABSPATH' ) || exit;

class XLSX_Cell_Decoder {
	private $book;
	private $calendar;
	private $preserve_existing = false;
	public function preserves_existing() {
		return $this->preserve_existing; }

	public function __construct( $calendar = 1900 ) {
		if ( ! in_array( $calendar, [ 1900, 1904 ], true ) ) {
			throw new \RuntimeException( 'Invalid spreadsheet calendar.' ); }
		$this->calendar = $calendar;
		$this->book     = new Spreadsheet();
	}

	/** The worksheet reader must supply shared formula resolution. */
	public function decode( \SimpleXMLElement $cell, $namespace, $coordinate, ?Spreadsheet_String_Store $strings, callable $shared_formula ) {
		$previous = \PhpOffice\PhpSpreadsheet\Shared\Date::getExcelCalendar();
		try {
			\PhpOffice\PhpSpreadsheet\Shared\Date::setExcelCalendar( $this->calendar );
			return $this->decode_value( $cell, $namespace, $coordinate, $strings, $shared_formula );
		} finally {
			\PhpOffice\PhpSpreadsheet\Shared\Date::setExcelCalendar( $previous ); }
	}

	private function decode_value( \SimpleXMLElement $cell, $namespace, $coordinate, ?Spreadsheet_String_Store $strings, callable $shared_formula ) {
		$type               = (string) $cell['t'];
		$c                  = $cell->children( $namespace );
		$value              = isset( $c->v ) ? (string) $c->v : null;
		$formula            = isset( $c->f );
		$formula_attributes = $formula ? $c->f->attributes() : null;
		$shared             = $formula && 'shared' === strtolower( (string) $formula_attributes['t'] );
		$use_formula        = $formula && ( '' !== (string) $c->f || $shared );
		if ( 's' === $type ) {
			if ( null === $value || '' === $value ) {
				$value = ''; } else {
				if ( ! $strings ) {
					throw new \RuntimeException( 'Missing shared-string table.' ); }
				$value = $strings->get( (int) $value );
				}
		} elseif ( $formula && ( 'str' !== $type || $use_formula ) ) {
			$value = '=' . (string) $c->f;
			if ( $shared ) {
				$value = $shared_formula( (string) $formula_attributes['si'], $coordinate, $value ); }
			$type = 'f';
		} elseif ( 'b' === $type ) {
			if ( null === $value ) {
				$type = 'null'; } else {
				$value = '0' === $value ? false : ( '1' === $value ? true : (bool) $c->v ); }
		} elseif ( 'inlineStr' === $type ) {
			$value = '';
			if ( isset( $c->is->t ) ) {
				$value = StringHelper::controlCharacterOOXML2PHP( (string) $c->is->t ); } else {
				foreach ( $c->is->r as $run ) {
					$value .= StringHelper::controlCharacterOOXML2PHP( (string) $run->t ); }
				}
		}
		if ( 'n' === $type && ( null === $value || '' === $value ) ) {
			$type = 'null'; }
		$this->preserve_existing = 'null' === $type;
		// Import text verbatim: the worksheet writer's 32,767-character limit must
		// not silently truncate data supplied by another exporter.
		$string_type = '' === $type ? \PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder::dataTypeForValue( $value ) : $type;
		if ( in_array( $string_type, [ 's', 'str', 'inlineStr' ], true ) ) {
			return str_replace( [ "\r\n", "\r" ], "\n", (string) $value );
		}
		// Reuse a single cell; this retains binder/date/string semantics without a workbook-sized collection.
		$target = $this->book->getActiveSheet()->getCell( 'A1' );
		$target->setValueExplicit( null, 'null' );
		if ( '' === $type ) {
			$target->setValue( $value ); } elseif ( 'null' !== $type ) {
			$target->setValueExplicit( $value, $type ); }
			$value = $target->getValue();
			if ( null === $value ) {
				return ''; }
			if ( is_bool( $value ) ) {
				return $value ? '1' : '0'; }
			return (string) $value;
	}

	public function close() {
		if ( $this->book ) {
			$this->book->disconnectWorksheets();
			$this->book = null; } }
	public function __destruct() {
		$this->close(); }
}

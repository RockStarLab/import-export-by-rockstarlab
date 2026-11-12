<?php
/** Complete XLSX staging before exposing normalized import rows. */
namespace RockStarLab\ImportExport\Model\Format;

defined( 'ABSPATH' ) || exit;

class XLSX_Row_Stream {
	/** Validate every worksheet's cells without retaining their values. */
	public static function validate_cells( $path, $validate_theme = false ) {
		$parts   = new Spreadsheet_XML_Parts( $path );
		$strings = null;
		try {
			if ( $validate_theme ) {
				XLSX_Theme_Validator::validate( $parts ); }
			$map   = XLSX_Part_Map::resolve( $parts );
			$count = $map['sheet_count'];
			if ( null !== $map['strings'] ) {
				$strings = Spreadsheet_String_Store::from_xml( $parts, $map['strings'] );
				$parts->release( $map['strings'] ); }
			for ( $index = 0; $index < $count; ++$index ) {
				if ( $index ) {
					$map = XLSX_Part_Map::resolve( $parts, $index ); }
				try {
					foreach ( XLSX_Cell_Stream::cells( $parts, $map, $strings ) as $cell ) {
						/* Decode to validate, retaining no row collection. */ }
				} finally {
					$parts->release( $map['worksheet'] ); }
			}
			return true;
		} finally {
			if ( $strings ) {
				$strings->close();
			} $parts->close(); }
	}

	public static function headers( $path, array $options = [] ) {
		foreach ( self::scan( $path, $options, true ) as $headers ) {
			return $headers; }
	}

	/** Return complete worksheet metadata and a bounded sample from one staging pass. */
	public static function preview( $path, array $options = [] ) {
		foreach ( self::scan( $path, $options, false, true ) as $summary ) {
			return $summary;
		}
	}

	public static function rows( $path, array $options = [], &$raw_headers = null ) {
		yield from self::scan( $path, $options, false, false, $raw_headers ); }

	private static function scan( $path, array $options, $headers_only, $preview = false, &$raw_headers = null ) {
		$parts = null;
		$store = null;
		try {
			$parts = new Spreadsheet_XML_Parts( $path );
			$map   = XLSX_Part_Map::resolve( $parts, XLSX_Part_Map::data_sheet_index( $parts ) );
			$store = new Spreadsheet_Row_Store();
			foreach ( XLSX_Cell_Stream::cells( $parts, $map ) as $cell ) {
				$store->append( $cell ); }
			$parts->close();
			$parts       = null;
			$raw_headers = $store->headers( (bool) ( $options['has_header'] ?? true ) );
			if ( $preview ) {
				yield $store->preview( (bool) ( $options['has_header'] ?? true ) );
			} elseif ( $headers_only ) {
				yield $store->headers( (bool) ( $options['has_header'] ?? true ) ); } else {
				yield from $store->rows( array_key_exists( 'has_header', $options ) ? (bool) $options['has_header'] : true ); }
		} finally {
			if ( $store ) {
				$store->close(); }
			if ( $parts ) {
				$parts->close(); }
		}
	}
}

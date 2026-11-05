<?php
/** Stage the selected ODS worksheet into the shared disk row store. */
namespace RockStarLab\ImportExport\Model\Format;

defined( 'ABSPATH' ) || exit;

class ODS_Row_Stream {
	public static function validate( $path ) {
		$parts = new Spreadsheet_XML_Parts( $path );
		try {
			foreach ( [ 'meta.xml', 'styles.xml' ] as $name ) {
				try {
					foreach ( $parts->nodes( $name ) as $node ) {
					}
				} catch ( \RuntimeException $error ) {
					if ( 'styles.xml' !== $name || 'Invalid spreadsheet XML.' !== $error->getMessage() ) {
						throw $error; }
				} finally {
					$parts->release( $name ); }
			}
			ODS_Sheet_Selector::select( $parts );
			foreach ( ODS_Cell_Stream::cells( $parts, null ) as $cell ) {
			}
			return true;
		} finally {
			$parts->close(); }
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
			$parts    = new Spreadsheet_XML_Parts( $path );
			$selected = ODS_Sheet_Selector::select( $parts );
			$store    = new Spreadsheet_Row_Store();
			foreach ( ODS_Cell_Stream::cells( $parts, $selected ) as $cell ) {
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

<?php
/** Read ODS cell repetitions without retaining a worksheet DOM. */
namespace RockStarLab\ImportExport\Model\Format;

defined( 'ABSPATH' ) || exit;

class ODS_Cell_Stream {
	private const TABLE = 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';
	public static function cells( Spreadsheet_XML_Parts $parts, $selected, &$checkpoint = null, $compact = false ) {
		$resume            = func_num_args() > 2;
		$decoder           = new ODS_Cell_Decoder();
		$sheet             = -1;
		$sheet_depth       = null;
		$row_depth         = null;
		$row               = 1;
		$column            = 1;
		$repeat_rows       = 1;
		$wrappers          = [];
		$spreadsheet_depth = null;
		foreach ( [ 'sheet', 'sheet_depth', 'row_depth', 'row', 'column', 'repeat_rows', 'wrappers', 'spreadsheet_depth' ] as $field ) {
			if ( isset( $checkpoint['state'] ) && array_key_exists( $field, $checkpoint['state'] ) ) {
				$$field = $checkpoint['state'][ $field ]; }
		}
		foreach ( ( $resume ? $parts->nodes( 'content.xml', $checkpoint['nodes'] ) : $parts->nodes( 'content.xml' ) ) as $node ) {
			if ( \XMLReader::END_ELEMENT === $node->nodeType ) {
				if ( $spreadsheet_depth === $node->depth ) {
					$spreadsheet_depth = null; }
				if ( $row_depth === $node->depth ) {
					$row      += $repeat_rows;
					$row_depth = null; }
				if ( isset( $wrappers[ $node->depth ] ) ) {
					unset( $wrappers[ $node->depth ] ); }
				if ( $sheet_depth === $node->depth ) {
					$sheet_depth = null;
					$wrappers    = []; }
				continue;
			}
			if ( \XMLReader::ELEMENT !== $node->nodeType ) {
				continue; }
			if ( 'spreadsheet' === $node->localName && 'urn:oasis:names:tc:opendocument:xmlns:office:1.0' === $node->namespaceURI ) {
				$spreadsheet_depth = $node->isEmptyElement ? null : $node->depth; }
			if ( self::TABLE !== $node->namespaceURI ) {
				continue; }
			if ( 'table' === $node->localName && null === $sheet_depth && null !== $spreadsheet_depth && $node->depth === $spreadsheet_depth + 1 ) {
				++$sheet;
				$sheet_depth = $node->depth;
				$row         = 1;
				if ( $node->isEmptyElement ) {
					$sheet_depth = null; }
				continue;
			}
			if ( ( null !== $selected && $sheet !== $selected ) || null === $sheet_depth ) {
				continue; }
			$parent_is_rows = $node->depth === $sheet_depth + 1 || isset( $wrappers[ $node->depth - 1 ] );
			if ( $parent_is_rows && in_array( $node->localName, [ 'table-row-group', 'table-header-rows', 'table-rows' ], true ) ) {
				if ( ! $node->isEmptyElement ) {
					$wrappers[ $node->depth ] = true; }
				continue;
			}
			if ( $parent_is_rows && 'table-row' === $node->localName ) {
				$repeat_rows = self::repeat( $node, 'number-rows-repeated' );
				$column      = 1;
				if ( $repeat_rows > PHP_INT_MAX - $row ) {
					throw new \RuntimeException( 'ODS coordinate overflow.' ); }
				if ( $node->isEmptyElement ) {
					$row += $repeat_rows;
				} else {
					$row_depth = $node->depth; }
				continue;
			}
			if ( null === $row_depth || $node->depth !== $row_depth + 1 || ! in_array( $node->localName, [ 'table-cell', 'covered-table-cell' ], true ) ) {
				continue; }
			$repeat_columns = self::repeat( $node, 'number-columns-repeated' );
			if ( $repeat_columns > PHP_INT_MAX - $column || $repeat_rows > PHP_INT_MAX - $row ) {
				throw new \RuntimeException( 'ODS coordinate overflow.' ); }
			$document = new \DOMDocument();
			if ( ! $document->loadXML( $node->readOuterXml(), LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE ) ) {
				throw new \RuntimeException( 'Invalid ODS cell.' ); }
			$cell      = $document->documentElement;
			$value     = $decoder->decode( $cell );
			$populated = $cell->hasAttributeNS( self::TABLE, 'formula' );
			foreach ( $cell->childNodes as $child ) {
				if ( 'p' === $child->localName && 'urn:oasis:names:tc:opendocument:xmlns:text:1.0' === $child->namespaceURI ) {
					$populated = true;
					break; }
			}
			if ( $populated && $compact ) {
				yield [
					'row'            => $row,
					'column'         => $column,
					'value'          => $value,
					'repeat_rows'    => $repeat_rows,
					'repeat_columns' => $repeat_columns,
				];
			} elseif ( $populated ) {
				for ( $r = 0; $r < $repeat_rows; ++$r ) {
					for ( $c = 0; $c < $repeat_columns; ++$c ) {
						yield [
							'row'    => $row + $r,
							'column' => $column + $c,
							'value'  => $value,
						]; }
				}
			}
			$column += max( 1, $repeat_columns );
		}
		if ( $resume ) {
			foreach ( [ 'sheet', 'sheet_depth', 'row_depth', 'row', 'column', 'repeat_rows', 'wrappers', 'spreadsheet_depth' ] as $field ) {
				$checkpoint['state'][ $field ] = $$field; }
		}
	}
	private static function repeat( \XMLReader $node, $attribute ) {
		$value = $node->getAttributeNs( $attribute, self::TABLE );
		if ( null === $value ) {
			return 1; }
		$count = filter_var( $value, FILTER_VALIDATE_INT, [ 'options' => [ 'min_range' => 1 ] ] );
		if ( false === $count ) {
			throw new \RuntimeException( 'Invalid ODS repetition count.' ); }
		return $count;
	}
}

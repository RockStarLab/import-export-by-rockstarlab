<?php
/** Validate required theme structure without retaining palettes or font lists. */
namespace RockStarLab\ImportExport\Model\Format;

defined( 'ABSPATH' ) || exit;

class XLSX_Theme_Validator {
	public static function validate( Spreadsheet_XML_Parts $parts ) {
		foreach ( XLSX_Part_Map::themes( $parts ) as $path ) {
			try {
				$namespace    = null;
				$has_elements = false;
				foreach ( $parts->nodes( $path ) as $node ) {
					if ( \XMLReader::ELEMENT !== $node->nodeType ) {
						continue; }
					if ( 0 === $node->depth ) {
						$namespace = $node->namespaceURI;
						if ( 'theme' !== $node->localName || ! in_array( $namespace, [ 'http://schemas.openxmlformats.org/drawingml/2006/main', 'http://purl.oclc.org/ooxml/drawingml/main' ], true ) ) {
							throw new \RuntimeException( 'Invalid spreadsheet theme.' ); }
					}
					if ( 1 === $node->depth && 'themeElements' === $node->localName && $namespace === $node->namespaceURI ) {
						$has_elements = true; }
				}
				if ( ! $has_elements ) {
					throw new \RuntimeException( 'Incomplete spreadsheet theme.' ); }
			} finally {
				$parts->release( $path ); }
		}
		return true;
	}
}

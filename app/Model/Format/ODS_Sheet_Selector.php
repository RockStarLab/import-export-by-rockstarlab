<?php
/** Resolve the stored ODS active sheet without loading settings/content documents. */
namespace RockStarLab\ImportExport\Model\Format;

defined( 'ABSPATH' ) || exit;

class ODS_Sheet_Selector {
	public static function select( Spreadsheet_XML_Parts $parts, &$checkpoint = null ) {
		$resume = func_num_args() > 1;
		$active = $checkpoint['active'] ?? null;
		if ( $parts->has( 'settings.xml' ) && empty( $checkpoint['settings_done'] ) ) {
			try {
				foreach ( ( $resume ? $parts->nodes( 'settings.xml', $checkpoint['settings'] ) : $parts->nodes( 'settings.xml' ) ) as $node ) {
					if ( null === $active && \XMLReader::ELEMENT === $node->nodeType && 'config-item' === $node->localName && 'urn:oasis:names:tc:opendocument:xmlns:config:1.0' === $node->namespaceURI && 'ActiveTable' === $node->getAttributeNs( 'name', 'urn:oasis:names:tc:opendocument:xmlns:config:1.0' ) ) {
						$active = $node->readString(); }
				}
			} catch ( \RuntimeException $error ) {
				// Legacy DOM settings loading ignores malformed XML and keeps the first sheet.
				if ( 'Invalid spreadsheet XML.' !== $error->getMessage() ) {
					throw $error; }
				$active = null;
				if ( $resume ) {
					$checkpoint['settings']['done'] = true; }
			} finally {
				$parts->release( 'settings.xml' ); }
		}
		if ( $resume ) {
			$checkpoint['active'] = $active;
			if ( $parts->has( 'settings.xml' ) && empty( $checkpoint['settings']['done'] ) ) {
				return null; }
			$checkpoint['settings_done'] = true;
		}
		$index       = 0;
		$selected    = 0;
		$matched     = false;
		$sheet_depth = null;
		foreach ( [ 'index', 'selected', 'matched', 'sheet_depth' ] as $field ) {
			if ( isset( $checkpoint['state'] ) && array_key_exists( $field, $checkpoint['state'] ) ) {
				$$field = $checkpoint['state'][ $field ]; }
		}
		foreach ( ( $resume ? $parts->nodes( 'content.xml', $checkpoint['nodes'] ) : $parts->nodes( 'content.xml' ) ) as $node ) {
			if ( \XMLReader::END_ELEMENT === $node->nodeType && $sheet_depth === $node->depth ) {
				$sheet_depth = null; }
			if ( \XMLReader::ELEMENT !== $node->nodeType ) {
				continue; }
			if ( 'spreadsheet' === $node->localName && 'urn:oasis:names:tc:opendocument:xmlns:office:1.0' === $node->namespaceURI ) {
				$sheet_depth = $node->depth; }
			if ( null !== $sheet_depth && $node->depth === $sheet_depth + 1 && 'table' === $node->localName && 'urn:oasis:names:tc:opendocument:xmlns:table:1.0' === $node->namespaceURI ) {
				if ( ! $matched && null !== $active && $active === $node->getAttributeNs( 'name', 'urn:oasis:names:tc:opendocument:xmlns:table:1.0' ) ) {
					$selected = $index;
					$matched  = true; }
				++$index;
			}
		}
		if ( $resume ) {
			foreach ( [ 'index', 'selected', 'matched', 'sheet_depth' ] as $field ) {
				$checkpoint['state'][ $field ] = $$field; }
			if ( empty( $checkpoint['nodes']['done'] ) ) {
				return null; }
		}
		if ( ! $index ) {
			throw new \RuntimeException( 'Missing ODS worksheet.' ); }
		return $selected;
	}
}

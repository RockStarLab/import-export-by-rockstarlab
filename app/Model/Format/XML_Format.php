<?php
/**
 * XML Format Handler
 *
 * Handles parsing and generation of XML files
 *
 * @package RockStarLab\ImportExport\Model\Format
 */

namespace RockStarLab\ImportExport\Model\Format;

defined( 'ABSPATH' ) || exit;

class XML_Format implements File_Format_Interface {

	/**
	 * Default root element name
	 *
	 * @var string
	 */
	const DEFAULT_ROOT = 'items';

	/**
	 * Default item element name
	 *
	 * @var string
	 */
	const DEFAULT_ITEM = 'item';

	/**
	 * Parse entire XML file
	 *
	 * @param string $file_path Absolute path to XML file
	 * @param array  $options   Optional. Parser options (root, item_tag)
	 * @return array|WP_Error Array of parsed data or WP_Error on failure
	 */
	public function parse( $file_path, $options = [] ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'XML file not found', 'import-export-by-rockstarlab' ) );
		}

		$item_tag = $options['item_tag'] ?? self::DEFAULT_ITEM;

		// Use XMLReader for memory efficiency
		$reader = new \XMLReader();
		$result = $reader->open( $file_path, null, LIBXML_NONET | LIBXML_COMPACT );

		if ( ! $result ) {
			return new \WP_Error( 'file_open_error', __( 'Cannot open XML file', 'import-export-by-rockstarlab' ) );
		}

		$data = [];

		// Read until we find item elements
		while ( $reader->read() ) {
			if ( $reader->nodeType === \XMLReader::ELEMENT && $reader->name === $item_tag ) {
				$item = $this->parse_xml_element( $reader );
				if ( is_wp_error( $item ) ) {
					$reader->close();
					return $item;
				}
				if ( ! empty( $item ) ) {
					$data[] = $item;
				}
			}
		}

		$reader->close();

		return $data;
	}

	/**
	 * Parse XML file in chunks
	 *
	 * @param string $file_path Absolute path to XML file
	 * @param int    $offset    Starting index
	 * @param int    $limit     Number of items to read
	 * @param array  $options   Optional. Parser options
	 * @return array|WP_Error Array of parsed data or WP_Error on failure
	 */
	public function parse_chunk( $file_path, $offset, $limit, $options = [] ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'XML file not found', 'import-export-by-rockstarlab' ) );
		}

		$item_tag = $options['item_tag'] ?? self::DEFAULT_ITEM;

		$reader = new \XMLReader();
		$result = $reader->open( $file_path, null, LIBXML_NONET | LIBXML_COMPACT );

		if ( ! $result ) {
			return new \WP_Error( 'file_open_error', __( 'Cannot open XML file', 'import-export-by-rockstarlab' ) );
		}

		$data    = [];
		$current = 0;
		$read    = 0;

		// Read until we find item elements
		while ( $reader->read() ) {
			if ( $reader->nodeType === \XMLReader::ELEMENT && $reader->name === $item_tag ) {
				// Skip items before offset
				if ( $current < $offset ) {
					++$current;
					continue;
				}

				// Stop when limit reached
				if ( $read >= $limit ) {
					break;
				}

				$item = $this->parse_xml_element( $reader );
				if ( is_wp_error( $item ) ) {
					$reader->close();
					return $item;
				}
				if ( ! empty( $item ) ) {
					$data[] = $item;
				}

				++$current;
				++$read;
			}
		}

		$reader->close();

		return $data;
	}

	/**
	 * Parse single XML element into array
	 *
	 * @param \XMLReader $reader XMLReader instance
	 * @return array|\WP_Error Parsed element data or WP_Error on failure.
	 */
	private function parse_xml_element( $reader ) {
		$outer_xml = $reader->readOuterXml();
		if ( false === $outer_xml || '' === $outer_xml ) {
			return new \WP_Error( 'xml_parse_error', __( 'Cannot read XML item.', 'import-export-by-rockstarlab' ) );
		}

		libxml_use_internal_errors( true );
		$element = simplexml_load_string( $outer_xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT );

		if ( false === $element ) {
			libxml_clear_errors();
			return new \WP_Error( 'xml_parse_error', __( 'Cannot parse XML item.', 'import-export-by-rockstarlab' ) );
		}

		$data = json_decode( wp_json_encode( $element ), true );
		return is_array( $data ) ? $data : array();
	}

	/**
	 * Generate XML file from data
	 *
	 * @param array  $data      Data array to write
	 * @param string $file_path Target file path
	 * @param array  $options   Optional. Generation options (root, item_tag, pretty_print)
	 * @return bool|WP_Error True on success or WP_Error on failure
	 */
	public function generate( $data, $file_path, $options = [] ) {
		$root_tag = $options['root_tag'] ?? self::DEFAULT_ROOT;
		$item_tag = $options['item_tag'] ?? self::DEFAULT_ITEM;
		$pretty   = $options['pretty_print'] ?? true;

		if ( empty( $data ) ) {
			$xml = new \XMLWriter();
			$xml->openMemory();
			$xml->startDocument( '1.0', 'UTF-8' );

			if ( $pretty ) {
				$xml->setIndent( true );
				$xml->setIndentString( '  ' );
			}

			$xml->startElement( $root_tag );
			$xml->endElement();
			$xml->endDocument();

			$result = file_put_contents( $file_path, $xml->outputMemory() );

			if ( false === $result ) {
				return new \WP_Error( 'file_write_error', __( 'Cannot write XML file', 'import-export-by-rockstarlab' ) );
			}

			return true;
		}

		$xml = new \XMLWriter();
		$xml->openMemory();
		$xml->startDocument( '1.0', 'UTF-8' );

		if ( $pretty ) {
			$xml->setIndent( true );
			$xml->setIndentString( '  ' );
		}

		// Start root element
		$xml->startElement( $root_tag );

		// Write each item
		foreach ( $data as $item ) {
			$xml->startElement( $item_tag );
			$this->write_xml_array( $xml, $item );
			$xml->endElement();
		}

		// End root element
		$xml->endElement();
		$xml->endDocument();

		$result = file_put_contents( $file_path, $xml->outputMemory() );

		if ( false === $result ) {
			return new \WP_Error( 'file_write_error', __( 'Cannot write XML file', 'import-export-by-rockstarlab' ) );
		}

		return true;
	}

	/**
	 * Write array data to XML
	 *
	 * @param \XMLWriter $xml  XMLWriter instance
	 * @param array      $data Data to write
	 */
	private function write_xml_array( $xml, $data ) {
		foreach ( $data as $key => $value ) {
			// Skip numeric keys
			if ( is_numeric( $key ) ) {
				$key = 'field_' . $key;
			}

			// Sanitize key for XML
			$key = preg_replace( '/[^a-zA-Z0-9_-]/', '_', $key );

			if ( is_array( $value ) ) {
				$xml->startElement( $key );
				$this->write_xml_array( $xml, $value );
				$xml->endElement();
			} else {
				$xml->writeElement( $key, (string) $value );
			}
		}
	}

	/**
	 * Validate XML file
	 *
	 * @param string $file_path Absolute path to file
	 * @return bool|WP_Error True if valid or WP_Error with errors
	 */
	public function validate( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'XML file not found', 'import-export-by-rockstarlab' ) );
		}

		if ( ! is_readable( $file_path ) ) {
			return new \WP_Error( 'file_not_readable', __( 'XML file is not readable', 'import-export-by-rockstarlab' ) );
		}

		// Check file extension
		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, $this->get_extensions(), true ) ) {
			return new \WP_Error( 'invalid_extension', __( 'Invalid XML file extension', 'import-export-by-rockstarlab' ) );
		}

		// Try to parse with error handling
		libxml_use_internal_errors( true );

		$xml = simplexml_load_file( $file_path, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT );

		if ( false === $xml ) {
			$errors         = libxml_get_errors();
			$error_messages = [];

			foreach ( $errors as $error ) {
				$error_messages[] = trim( $error->message );
			}

			libxml_clear_errors();

			return new \WP_Error(
				'xml_invalid',
				sprintf(
					/* translators: %s: XML error messages */
					__( 'Invalid XML: %s', 'import-export-by-rockstarlab' ),
					implode( ', ', $error_messages )
				)
			);
		}

		return true;
	}

	/**
	 * Get XML headers (field names from first item)
	 *
	 * @param string $file_path Absolute path to file
	 * @param array  $options   Optional. Parser options
	 * @return array|WP_Error Array of headers or WP_Error on failure
	 */
	public function get_headers( $file_path, $options = [] ) {
		$chunk = $this->parse_chunk( $file_path, 0, 1, $options );

		if ( is_wp_error( $chunk ) ) {
			return $chunk;
		}

		if ( empty( $chunk ) ) {
			return [];
		}

		$first_item = $chunk[0];

		if ( ! is_array( $first_item ) ) {
			return [];
		}

		return array_keys( $first_item );
	}

	/**
	 * Count items in XML file
	 *
	 * @param string $file_path Absolute path to file
	 * @return int|WP_Error Item count or WP_Error on failure
	 */
	public function count_rows( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'XML file not found', 'import-export-by-rockstarlab' ) );
		}

		$item_tag = self::DEFAULT_ITEM;

		$reader = new \XMLReader();
		$result = $reader->open( $file_path, null, LIBXML_NONET | LIBXML_COMPACT );

		if ( ! $result ) {
			return new \WP_Error( 'file_open_error', __( 'Cannot open XML file', 'import-export-by-rockstarlab' ) );
		}

		$count = 0;

		while ( $reader->read() ) {
			if ( $reader->nodeType === \XMLReader::ELEMENT && $reader->name === $item_tag ) {
				++$count;
			}
		}

		$reader->close();

		return $count;
	}

	/**
	 * Get supported extensions
	 *
	 * @return array
	 */
	public function get_extensions() {
		return [ 'xml' ];
	}

	/**
	 * Get supported MIME types
	 *
	 * @return array
	 */
	public function get_mime_types() {
		return [
			'application/xml',
			'text/xml',
		];
	}
}

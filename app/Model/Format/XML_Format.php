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

		try {
			return iterator_to_array( $this->rows( $file_path, $options ), false );
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'xml_parse_error', __( 'Invalid XML file', 'import-export-by-rockstarlab' ) );
		}
	}


	/** Stream records while validating the complete document before publication. */
	public function rows( $file_path, $options = array(), &$cursor = null ) {
		// Cursor and spool identities stay keyed by the caller's options; the
		// detected record element depends only on the (fingerprinted) file.
		$requested = $options;
		$options   = $this->resolve_record_options( $file_path, (array) $options );
		if ( func_num_args() < 3 ) {
			yield from $this->document_rows( $file_path, $options );
			return; }
		clearstatcache( true, $file_path );
		$stat     = stat( $file_path );
		$identity = array_intersect_key( $stat ?: array(), array_flip( array( 'dev', 'ino', 'size', 'mtime', 'ctime' ) ) );
		$settings = hash( 'sha256', serialize( $requested ) );
		if ( null !== $cursor && ( ! is_array( $cursor ) || ( $cursor['identity'] ?? null ) !== $identity || ( $cursor['settings'] ?? null ) !== $settings || ! isset( $cursor['count'] ) || ! is_int( $cursor['count'] ) || $cursor['count'] < 0 ) ) {
			throw new \RuntimeException( 'Invalid XML cursor.' );
		}
		$encoding = isset( $cursor['spool'] ) ? false : ( $cursor['stream_encoding'] ?? $this->seekable_encoding( $file_path ) );
		if ( isset( $cursor['fragment'] ) || $encoding ) {
			if ( null === $cursor || isset( $cursor['fragment'] ) ) {
				yield from $this->seekable_rows( $file_path, $options, $cursor, $identity, $settings, $encoding );
			} else {
				// Upgrade a persisted row-count cursor once, checking its old prefix fingerprint.
				$skip     = $cursor['count'];
				$expected = $cursor['prefix_sha256'] ?? null;
				$prefix   = hash_init( 'sha256' );
				$next     = null;
				$count    = 0;
				foreach ( $this->seekable_rows( $file_path, $options, $next, $identity, $settings, $encoding ) as $row ) {
					++$count;
					hash_update( $prefix, serialize( $row ) );
					$digest = hash_final( hash_copy( $prefix ) );
					if ( $count === $skip && null !== $expected && ( ! is_string( $expected ) || ! hash_equals( $expected, $digest ) ) ) {
						throw new \RuntimeException( 'Changed XML prefix.' ); }
					if ( $count <= $skip ) {
						continue; }
					$cursor = $next + array( 'prefix_sha256' => $digest );
					yield $row;
				}
				if ( $count < $skip ) {
					throw new \RuntimeException( 'Invalid XML cursor count.' ); }
			}
			return;
		}
		yield from \RockStarLab\ImportExport\Service\XML_Row_Spool::rows(
			$file_path,
			$identity,
			$settings,
			$cursor,
			function () use ( $file_path, $options ) {
				return $this->document_rows( $file_path, $options ); }
		);
	}

	/** Detect byte width once; old cursors retain their established decoder. */
	private function seekable_encoding( $path ) {
		$input = fopen( $path, 'rb' );
		if ( ! $input ) {
			return false; }
		try {
			$head = fread( $input, 1024 );
			while ( preg_match( '/^(?:\xEF\xBB\xBF)?<\?xml\s/', $head ) && strpos( $head, '?>' ) === false && ! feof( $input ) ) {
				$head .= fread( $input, 1024 ); }
		} finally {
			fclose( $input ); }
		if ( false === $head ) {
			return false; }
		$encoding = array(
			'name'   => 'UTF-8',
			'width'  => 1,
			'little' => false,
		);
		foreach ( array(
			"\xff\xfe\0\0" => array( 'UTF-32LE', 4, true ),
			"\0\0\xfe\xff" => array( 'UTF-32BE', 4, false ),
			"<\0\0\0"      => array( 'UTF-32LE', 4, true ),
			"\0\0\0<"      => array( 'UTF-32BE', 4, false ),
			"\xff\xfe"     => array( 'UTF-16LE', 2, true ),
			"\xfe\xff"     => array( 'UTF-16BE', 2, false ),
			"<\0"          => array( 'UTF-16LE', 2, true ),
			"\0<"          => array( 'UTF-16BE', 2, false ),
		) as $prefix => $spec ) {
			if ( substr( $head, 0, strlen( $prefix ) ) === $prefix ) {
				$encoding = array(
					'name'   => $spec[0],
					'width'  => $spec[1],
					'little' => $spec[2],
				);
				break; }
		}
		if ( 1 === $encoding['width'] && preg_match( '/^(?:\xEF\xBB\xBF)?<\?xml[^?]*encoding\s*=\s*[\'"]([^\'"]+)/i', $head, $match ) ) {
			$encoding['name'] = strtoupper( $match[1] ); }
		if ( 1 === $encoding['width'] && ! preg_match( '/^(?:UTF-?8|US-ASCII|ASCII|ISO-8859-[0-9]+|WINDOWS-125[0-8])$/i', $encoding['name'] ) ) {
			return false; }
		if ( 'UTF8' === $encoding['name'] ) {
			$encoding['name'] = 'UTF-8'; }
		if ( 'UTF-8' !== $encoding['name'] && ( ! function_exists( 'iconv' ) || false === @iconv( $encoding['name'], 'UTF-8', '' ) ) ) {
			return false; }
		return $encoding;
	}

	/** Resume from a source byte boundary with inherited element/namespace context. */
	private function seekable_rows( $path, $options, &$cursor, $identity, $settings, $encoding ) {
		$initial = array(
			'offset'      => 0,
			'stack'       => array(),
			'roots'       => 0,
			'declaration' => '',
		);
		$state   = $cursor['fragment'] ?? $initial;
		$skip    = $cursor['fragment_skip'] ?? 0;
		$count   = $cursor['count'] ?? 0;
		$digest  = $cursor['source_sha256'] ?? hash_file( 'sha256', $path );
		if ( ! is_array( $encoding ) || ! is_string( $encoding['name'] ?? null ) || ! in_array( $encoding['width'] ?? null, array( 1, 2, 4 ), true ) || ! is_bool( $encoding['little'] ?? null ) || ! is_array( $state ) || ! is_int( $state['offset'] ?? null ) || $state['offset'] < 0 || $state['offset'] > $identity['size'] || ! is_array( $state['stack'] ?? null ) || ! is_int( $state['roots'] ?? null ) || ! is_string( $state['declaration'] ?? null ) || ! is_int( $skip ) || $skip < 0 || ! is_string( $digest ) || ( isset( $cursor['fragment_count'] ) && $cursor['fragment_count'] !== $count ) ) {
			throw new \RuntimeException( 'Invalid XML cursor.' );
		}
		foreach ( $state['stack'] as $ancestor ) {
			if ( ! is_array( $ancestor ) || ! is_string( $ancestor['name'] ?? null ) || ! is_string( $ancestor['open'] ?? null ) ) {
				throw new \RuntimeException( 'Invalid XML context.' ); }
		}
		$input = new XML_Fragment_Stream( $path, $state['offset'], $encoding );
		foreach ( $input->segments( $options['item_tag'] ?? self::DEFAULT_ITEM, $state ) as $segment ) {
			$xml = $segment['xml'];
			if ( 'UTF-8' !== $encoding['name'] ) {
				if ( 2 === $encoding['width'] && substr( $xml, 0, 3 ) !== "\xef\xbb\xbf" ) {
					$xml = "\xef\xbb\xbf" . $xml; }
				$xml = @iconv( 'UTF-8', $encoding['name'], $xml );
				if ( false === $xml ) {
					throw new \RuntimeException( 'Invalid XML encoding.' ); }
			}
			$rows  = iterator_to_array( $this->document_rows( $xml, $options, true ), false );
			$total = count( $rows );
			if ( $skip > $total ) {
				throw new \RuntimeException( 'Invalid XML cursor count.' ); }
			for ( $i = $skip; $i < $total; ++$i ) {
				++$count;
				$cursor = array(
					'identity'        => $identity,
					'settings'        => $settings,
					'count'           => $count,
					'fragment_count'  => $count,
					'stream_encoding' => $encoding,
					'source_sha256'   => $digest,
					'fragment'        => $i + 1 === $total ? $segment['next'] : $state,
					'fragment_skip'   => $i + 1 === $total ? 0 : $i + 1,
				);
				yield $rows[ $i ];
			}
			$skip  = 0;
			$state = $segment['next'];
		}
		// Publication still requires the complete source to match the initial fingerprint.
		if ( ! hash_equals( $digest, (string) hash_file( 'sha256', $path ) ) ) {
			throw new \RuntimeException( 'Changed XML prefix.' ); }
	}
	/** XMLReader validates source documents or isolated fragments with ancestor context. */
	private function document_rows( $file_path, $options, $memory = false ) {
		$reader   = new \XMLReader();
		$previous = libxml_use_internal_errors( true );
		libxml_clear_errors();
		try {
			// Large individual fields are allowed; DTDs remain rejected and entities are not substituted.
			if ( ! ( $memory ? $reader->XML( $file_path, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE ) : $reader->open( $file_path, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE ) ) ) {
				throw new \RuntimeException( 'Cannot open XML file.' ); }
			$item_tag = $options['item_tag'] ?? self::DEFAULT_ITEM;
			while ( $reader->read() ) {
				if ( \XMLReader::DOC_TYPE === $reader->nodeType ) {
					throw new \RuntimeException( 'XML document types are unsupported.' ); }
				if ( \XMLReader::ELEMENT === $reader->nodeType && $reader->name === $item_tag ) {
					$item = $this->parse_xml_element( $reader, ! empty( $options['xml_flat'] ) );
					if ( is_wp_error( $item ) ) {
						throw new \RuntimeException( 'Invalid XML record.' ); }
					if ( ! empty( $item ) ) {
						yield $item; }
				}
			}
			foreach ( libxml_get_errors() as $error ) {
				if ( $error->level >= LIBXML_ERR_ERROR ) {
					throw new \RuntimeException( 'Invalid XML document.' ); }
			}
		} finally {
			$reader->close();
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );
		}
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

		$data  = [];
		$index = 0;
		try {
			foreach ( $this->rows( $file_path, $options ) as $row ) {
				if ( $index++ >= max( 0, (int) $offset ) && count( $data ) < max( 0, (int) $limit ) ) {
					$data[] = $row;
				}
			}
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'xml_parse_error', __( 'Invalid XML file', 'import-export-by-rockstarlab' ) );
		}
		return $data;
	}

	/**
	 * Parse single XML element into array
	 *
	 * @param \XMLReader $reader XMLReader instance
	 * @return array|\WP_Error Parsed element data or WP_Error on failure.
	 */
	private function parse_xml_element( $reader, $flat = false ) {
		$outer_xml = $reader->readOuterXml();
		if ( false === $outer_xml || '' === $outer_xml ) {
			return new \WP_Error( 'xml_parse_error', __( 'Cannot read XML item.', 'import-export-by-rockstarlab' ) );
		}

		libxml_use_internal_errors( true );
		$element = simplexml_load_string( $outer_xml, 'SimpleXMLElement', LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE | LIBXML_NOCDATA );

		if ( false === $element ) {
			libxml_clear_errors();
			return new \WP_Error( 'xml_parse_error', __( 'Cannot parse XML item.', 'import-export-by-rockstarlab' ) );
		}

		if ( $flat ) {
			return $this->flatten_element( $element );
		}

		$data = json_decode( wp_json_encode( $element ), true );
		return is_array( $data ) ? $this->normalize_xml_values( $data ) : array();
	}

	/**
	 * Work out which element is one record.
	 *
	 * The plugin's own files use <items><item>. Feeds from suppliers and shops use
	 * other names (<catalog><product>, <rss><channel><item>, <offers><offer>): the
	 * record is the element that repeats under the root, or under a single wrapper.
	 * Feeds are read as flat rows (see flatten_element()).
	 *
	 * @param string $file_path File path or, for fragments, XML text.
	 * @param array  $options   Parser options.
	 * @return array Options with item_tag and xml_flat set.
	 */
	private function resolve_record_options( $file_path, array $options ) {
		if ( ! empty( $options['item_tag'] ) && isset( $options['xml_flat'] ) ) {
			return $options;
		}
		static $cache = array();
		$key = '';
		if ( is_string( $file_path ) && strlen( $file_path ) < 4096 && is_file( $file_path ) ) {
			clearstatcache( true, $file_path );
			$head = (string) file_get_contents( $file_path, false, null, 0, 65536 );
			$key  = $file_path . '|' . filesize( $file_path ) . '|' . md5( $head );
		}
		if ( '' === $key ) {
			$options['item_tag'] = $options['item_tag'] ?? self::DEFAULT_ITEM;
			$options['xml_flat'] = $options['xml_flat'] ?? false;
			return $options;
		}
		if ( ! isset( $cache[ $key ] ) ) {
			$cache[ $key ] = $this->detect_record( $file_path );
		}
		list( $root, $tag ) = $cache[ $key ];
		if ( ! empty( $options['item_tag'] ) ) {
			$tag = (string) $options['item_tag'];
		}
		$options['item_tag'] = $tag;
		// An unreadable start (the parser reports the error later) keeps the classic reading.
		$options['xml_flat'] = '' !== $root && ! ( self::DEFAULT_ROOT === $root && self::DEFAULT_ITEM === $tag );
		return $options;
	}

	/**
	 * Scan the start of a file for the record element.
	 *
	 * @param string $file_path File path.
	 * @return array{0:string,1:string} Root element name and record element name.
	 */
	private function detect_record( $file_path ) {
		$reader   = new \XMLReader();
		$previous = libxml_use_internal_errors( true );
		$root     = '';
		$level1   = array();
		$level2   = array();
		$parents  = array();
		$seen     = 0;
		try {
			if ( ! $reader->open( $file_path, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE ) ) {
				return array( '', self::DEFAULT_ITEM );
			}
			while ( $reader->read() && $seen < 20000 ) {
				if ( \XMLReader::ELEMENT !== $reader->nodeType ) {
					continue;
				}
				++$seen;
				if ( 0 === $reader->depth ) {
					$root = $reader->name;
				} elseif ( 1 === $reader->depth ) {
					$level1[ $reader->name ] = ( $level1[ $reader->name ] ?? 0 ) + 1;
					$parents[1]              = $reader->name;
					// Two records under the root, or the plugin's own <item>, are enough to decide.
					if ( $level1[ $reader->name ] >= 2 || self::DEFAULT_ITEM === $reader->name ) {
						break;
					}
				} elseif ( 2 === $reader->depth ) {
					$name            = $parents[1] . '>' . $reader->name;
					$level2[ $name ] = ( $level2[ $name ] ?? 0 ) + 1;
				}
			}
		} finally {
			$reader->close();
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );
		}
		// A lone <item> document is read the classic way.
		if ( self::DEFAULT_ITEM === $root ) {
			return array( self::DEFAULT_ROOT, self::DEFAULT_ITEM );
		}
		if ( isset( $level1[ self::DEFAULT_ITEM ] ) || empty( $level1 ) ) {
			return array( $root, self::DEFAULT_ITEM );
		}
		arsort( $level1 );
		$best = (string) key( $level1 );
		if ( $level1[ $best ] >= 2 ) {
			return array( $root, $best );
		}
		// One wrapper under the root (<channel>, <products>): look one level deeper.
		// Header elements may sit next to the wrapper (<feed><updated/><stockists>…),
		// so check every single element under the root, not only the first one.
		$known  = array( 'item', 'product', 'offer', 'entry', 'record', 'row' );
		$choice = array( '', '', 0 );
		foreach ( array_keys( $level1 ) as $wrapper ) {
			foreach ( $level2 as $path => $count ) {
				if ( 0 !== strpos( $path, $wrapper . '>' ) ) {
					continue;
				}
				$child = substr( $path, strlen( $wrapper ) + 1 );
				$score = $count >= 2 ? $count : ( in_array( $child, $known, true ) ? 1 : 0 );
				// RSS and WordPress export (WXR) files list many <wp:tag>/<wp:category>
				// header elements before the <item> records: a known record name wins.
				if ( in_array( $child, $known, true ) ) {
					$score += 1000000;
				}
				if ( $score > $choice[2] ) {
					$choice = array( $wrapper, $child, $score );
				}
			}
		}
		if ( '' !== $choice[1] ) {
			return array( $root, $choice[1] );
		}
		return array( $root, $best );
	}

	/**
	 * Turn one feed record into a flat row.
	 *
	 * <sku>A1</sku> becomes sku => A1; attributes become name@attribute
	 * (<price currency="USD">9</price> gives price => 9 and price@currency => USD);
	 * repeated values and lists such as <images><image>..</image></images> become
	 * one comma-separated value; other nested elements get path names (shipping/weight).
	 *
	 * @param \SimpleXMLElement $element Record element.
	 * @param string            $prefix  Name prefix for nested elements.
	 * @return array<string,string>
	 */
	private function flatten_element( \SimpleXMLElement $element, $prefix = '' ) {
		$row = array();
		foreach ( $this->element_attributes( $element ) as $name => $value ) {
			$row[ ( '' === $prefix ? '' : rtrim( $prefix, '/' ) ) . '@' . $name ] = $value;
		}
		$groups = array();
		foreach ( $this->element_children( $element ) as $child ) {
			$groups[ $child[0] ][] = $child[1];
		}
		foreach ( $groups as $name => $children ) {
			$key    = $prefix . $name;
			$leaves = true;
			foreach ( $children as $child ) {
				if ( $this->element_children( $child ) ) {
					$leaves = false;
					break;
				}
			}
			if ( $leaves ) {
				$values = array();
				foreach ( $children as $child ) {
					$values[] = trim( (string) $child );
					foreach ( $this->element_attributes( $child ) as $attribute => $value ) {
						$row[ $key . '@' . $attribute ] = isset( $row[ $key . '@' . $attribute ] ) ? $row[ $key . '@' . $attribute ] . ', ' . $value : $value;
					}
				}
				$row[ $key ] = implode( ', ', array_filter( $values, 'strlen' ) );
				continue;
			}
			if ( 1 === count( $children ) ) {
				$inner = $this->element_children( $children[0] );
				$names = array_values( array_unique( array_column( $inner, 0 ) ) );
				$plain = 1 === count( $names ) && ( count( $inner ) > 1 || $this->is_plural_of( $name, $names[0] ) );
				foreach ( $inner as $item ) {
					if ( $this->element_children( $item[1] ) ) {
						$plain = false;
						break;
					}
				}
				if ( $plain ) {
					// A list wrapper: <images><image>a</image><image>b</image></images>.
					$values = array();
					foreach ( $inner as $item ) {
						$values[] = trim( (string) $item[1] );
					}
					$row[ $key ] = implode( ', ', array_filter( $values, 'strlen' ) );
					continue;
				}
				$row += $this->flatten_element( $children[0], $key . '/' );
				continue;
			}
			// Repeated complex elements (variants, attributes) stay together as JSON.
			$list = array();
			foreach ( $children as $child ) {
				$list[] = $this->flatten_element( $child );
			}
			$row[ $key ] = wp_json_encode( $list );
		}
		return $row;
	}

	/**
	 * Whether a wrapper name is the plural of its child (images/image, categories/category).
	 *
	 * @param string $wrapper Wrapper element name.
	 * @param string $child   Child element name.
	 * @return bool
	 */
	private function is_plural_of( $wrapper, $child ) {
		$wrapper = strtolower( $wrapper );
		$child   = strtolower( $child );
		return in_array( $wrapper, array( $child . 's', $child . 'es', substr( $child, 0, -1 ) . 'ies' ), true ) || 'list' === substr( $wrapper, -4 );
	}

	/**
	 * Child elements of an element in every namespace, as [name, element] pairs.
	 *
	 * @param \SimpleXMLElement $element Element.
	 * @return array<int,array{0:string,1:\SimpleXMLElement}>
	 */
	private function element_children( \SimpleXMLElement $element ) {
		$out = array();
		foreach ( $element->children() as $name => $child ) {
			$out[] = array( (string) $name, $child );
		}
		foreach ( $element->getDocNamespaces( true ) as $ns_prefix => $uri ) {
			if ( '' === $ns_prefix ) {
				continue;
			}
			foreach ( $element->children( $uri ) as $name => $child ) {
				$out[] = array( $ns_prefix . ':' . $name, $child );
			}
		}
		return $out;
	}

	/**
	 * Attributes of an element as name => value.
	 *
	 * @param \SimpleXMLElement $element Element.
	 * @return array<string,string>
	 */
	private function element_attributes( \SimpleXMLElement $element ) {
		$out = array();
		foreach ( $element->attributes() as $name => $value ) {
			$out[ (string) $name ] = (string) $value;
		}
		return $out;
	}

	/**
	 * Normalize SimpleXML's decoded values.
	 *
	 * SimpleXML represents empty leaf tags as empty arrays after JSON decoding,
	 * but importers expect scalar fields like post_excerpt to be strings.
	 *
	 * @param mixed $value Parsed XML value.
	 * @return mixed Normalized value.
	 */
	private function normalize_xml_values( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}

		if ( empty( $value ) ) {
			return '';
		}

		foreach ( $value as $key => $child_value ) {
			$value[ $key ] = $this->normalize_xml_values( $child_value );
		}

		return $value;
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

		$reader   = new \XMLReader();
		$previous = libxml_use_internal_errors( true );
		libxml_clear_errors();
		try {
			// Large individual fields are allowed; DTDs remain rejected and entities are not substituted.
			if ( ! $reader->open( $file_path, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE ) ) {
				return new \WP_Error( 'xml_invalid', __( 'Cannot read XML file', 'import-export-by-rockstarlab' ) );
			}
			while ( $reader->read() ) {
				if ( \XMLReader::DOC_TYPE === $reader->nodeType ) {
					return new \WP_Error( 'xml_invalid', __( 'XML document types are unsupported.', 'import-export-by-rockstarlab' ) );
				}
			}
			foreach ( libxml_get_errors() as $error ) {
				if ( $error->level >= LIBXML_ERR_ERROR ) {
					return new \WP_Error( 'xml_invalid', __( 'Invalid XML file', 'import-export-by-rockstarlab' ) );
				}
			}
		} finally {
			$reader->close();
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );
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

		$count = 0;
		try {
			foreach ( $this->rows( $file_path ) as $row ) {
				++$count;
			}
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'xml_parse_error', __( 'Invalid XML file', 'import-export-by-rockstarlab' ) );
		}
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

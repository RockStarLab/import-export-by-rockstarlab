<?php
/**
 * JSON Format Handler
 *
 * Handles parsing and generation of JSON files
 *
 * @package RockStarLab\ImportExport\Model\Format
 */

namespace RockStarLab\ImportExport\Model\Format;

defined( 'ABSPATH' ) || exit;

class JSON_Format implements File_Format_Interface {

	/**
	 * Parse entire JSON file
	 *
	 * @param string $file_path Absolute path to JSON file
	 * @param array  $options   Optional. Parser options (depth, flags)
	 * @return array|WP_Error Array of parsed data or WP_Error on failure
	 */
	public function parse( $file_path, $options = [] ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'JSON file not found', 'import-export-by-rockstarlab' ) );
		}

		$handle = @fopen( $file_path, 'rb' );
		if ( ! $handle ) {
			return new \WP_Error( 'file_read_error', __( 'Cannot read JSON file', 'import-export-by-rockstarlab' ) ); }
		try {
			do {
				$first = fgetc( $handle );
			} while ( false !== $first && false !== strpos( " \t\r\n", $first ) );
		} finally {
			fclose( $handle ); }
		if ( '[' === $first || '{' === $first ) {
			$data = [];
			try {
				foreach ( JSON_Row_Stream::rows( $file_path, [ 'object_members' => '{' === $first ] + $options ) as $value ) {
					if ( '{' === $first ) {
						foreach ( $value as $key => $member ) {
							$data[ $key ] = $member; }
					} else {
						$data[] = $value; }
				}
			} catch ( \Throwable $error ) {
				return self::invalid_json_error( 'json_parse_error', $error );
			}
			return $data;
		}

		$content = file_get_contents( $file_path );
		if ( false === $content ) {
			return new \WP_Error( 'file_read_error', __( 'Cannot read JSON file', 'import-export-by-rockstarlab' ) );
		}

		$depth = $options['depth'] ?? 512;
		$flags = $options['flags'] ?? 0;

		$data = json_decode( $content, true, $depth, $flags );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error(
				'json_parse_error',
				sprintf(
					/* translators: %s: JSON error message */
					__( 'JSON parse error: %s', 'import-export-by-rockstarlab' ),
					json_last_error_msg()
				)
			);
		}

		// Ensure data is array of items
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'invalid_format', __( 'JSON file must contain an array', 'import-export-by-rockstarlab' ) );
		}

		return $data;
	}

	/**
	 * Parse JSON file in chunks
	 *
	 * @param string $file_path Absolute path to JSON file
	 * @param int    $offset    Starting index
	 * @param int    $limit     Number of items to read
	 * @param array  $options   Optional. Parser options
	 * @return array|WP_Error Array of parsed data or WP_Error on failure
	 */
	public function parse_chunk( $file_path, $offset, $limit, $options = [] ) {
		return $this->scan_rows( $file_path, $offset, $limit, $options );
	}

	/** Iterate validated rows; legacy objects use a private disk index for duplicate keys. */
	public function rows( $path, array $options = [] ) {
		$handle = @fopen( $path, 'rb' );
		if ( ! $handle ) {
			throw new \RuntimeException( 'Cannot read JSON file.' ); }
		try {
			do {
				$first = fgetc( $handle );
			} while ( false !== $first && false !== strpos( " \t\r\n", $first ) );
		} finally {
			fclose( $handle ); }
		yield from ( '{' === $first ? JSON_Object_Stream::rows( $path, $options ) : JSON_Row_Stream::rows( $path, $options ) );
	}

	/**
	 * Records nested deeper in the file, such as a Ghost export ({"db":[{"data":{"posts":[...]}}]}).
	 * Used only when the file would otherwise give a single row that holds nothing but lists and
	 * objects: then the list of objects that looks most like records becomes the rows.
	 *
	 * @param string $path File path.
	 * @return array|null [ 'path' => 'db.0.data.posts', 'rows' => [...] ] or null.
	 */
	public static function nested_records( $path ) {
		static $cache = [];
		$size = @filesize( $path );
		if ( false === $size || $size > 64 * MB_IN_BYTES ) {
			return null;
		}
		$key = $path . '|' . $size . '|' . @filemtime( $path );
		if ( array_key_exists( $key, $cache ) ) {
			return $cache[ $key ];
		}
		$cache[ $key ] = null;
		// Cheap check first: the file holds a single object member or a single array element.
		try {
			$probe = 0;
			$reader = JSON_Row_Stream::rows( $path, [ 'object_members' => true ] );
			foreach ( $reader as $unused ) {
				if ( ++$probe > 1 ) {
					return null;
				}
			}
		} catch ( \Throwable $error ) {
			return null;
		}
		$data          = json_decode( (string) file_get_contents( $path ), true, 512 );
		if ( ! is_array( $data ) || 1 !== count( $data ) ) {
			return null;
		}
		$top   = reset( $data );
		$first = (string) key( $data );
		if ( ! is_array( $top ) ) {
			return null;
		}
		foreach ( $top as $value ) {
			if ( ! is_array( $value ) ) {
				return null; // A single real record with its own values.
			}
		}
		$best = null;
		$walk = static function ( $node, $node_path, $depth ) use ( &$walk, &$best ) {
			if ( $depth > 6 || ! is_array( $node ) ) {
				return;
			}
			if ( self::is_list( $node ) && count( $node ) >= 2 ) {
				$records = true;
				$keys    = 0;
				foreach ( $node as $item ) {
					if ( ! is_array( $item ) || ! $item || self::is_list( $item ) ) {
						$records = false;
						break;
					}
					$keys += count( $item );
				}
				if ( $records ) {
					$name  = strtolower( (string) substr( strrchr( '.' . $node_path, '.' ), 1 ) );
					$score = $keys * ( in_array( $name, [ 'posts', 'pages', 'items', 'records', 'entries', 'rows', 'products', 'results' ], true ) ? 2 : 1 );
					if ( null === $best || $score > $best['score'] ) {
						$best = [ 'path' => $node_path, 'rows' => array_values( $node ), 'score' => $score ];
					}
					return;
				}
			}
			foreach ( $node as $child_key => $child ) {
				$walk( $child, '' === $node_path ? (string) $child_key : $node_path . '.' . $child_key, $depth + 1 );
			}
		};
		$walk( $top, $first, 1 );
		if ( null === $best ) {
			return null;
		}
		unset( $best['score'] );
		$cache[ $key ] = $best;
		return $best;
	}

	/**
	 * Files the streaming readers do not take as they are, turned into a plain list of records:
	 * a UTF-8 byte order mark, JSON Lines (one object per line), a single object with its own
	 * values (one record), and an object whose records are a list next to other members, such
	 * as {"meta": {...}, "data": [...]} or a list nested deeper with only one record.
	 *
	 * @param string $path File path.
	 * @return array|null [ 'rows' => [...], 'source' => 'json_bom'|'json_lines'|'json_object'|'json_records', 'path' => string ] or null.
	 */
	public static function normalized_records( $path ) {
		$size = @filesize( $path );
		if ( false === $size || $size > 64 * MB_IN_BYTES ) {
			return null;
		}
		$raw = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$bom = 0 === strncmp( $raw, "\xEF\xBB\xBF", 3 );
		if ( $bom ) {
			$raw = substr( $raw, 3 );
		}
		$data = json_decode( $raw, true, 512 );
		if ( JSON_ERROR_NONE !== json_last_error() ) {
			// JSON Lines: every non-empty line is one object.
			$rows = [];
			foreach ( preg_split( '/\r\n|\r|\n/', trim( $raw ) ) as $line ) {
				$line = trim( $line );
				if ( '' === $line ) {
					continue;
				}
				$item = json_decode( $line, true, 512 );
				if ( ! is_array( $item ) || ! $item || self::is_list( $item ) ) {
					return null;
				}
				$rows[] = $item;
			}
			return count( $rows ) > 1 ? [ 'rows' => $rows, 'source' => 'json_lines', 'path' => '' ] : null;
		}
		if ( ! is_array( $data ) || ! $data ) {
			return null;
		}
		if ( self::is_list( $data ) ) {
			return $bom ? [ 'rows' => $data, 'source' => 'json_bom', 'path' => '' ] : null;
		}
		$scalars = true;
		$objects = true;
		foreach ( $data as $value ) {
			if ( is_array( $value ) ) {
				$scalars = false;
				if ( ! $value || self::is_list( $value ) ) {
					$objects = false;
				}
			} else {
				$objects = false;
			}
		}
		if ( $scalars ) {
			return [ 'rows' => [ $data ], 'source' => 'json_object', 'path' => '' ];
		}
		if ( $objects && count( $data ) > 1 ) {
			return $bom ? [ 'rows' => array_values( $data ), 'source' => 'json_bom', 'path' => '' ] : null; // Keyed records {"a": {...}, "b": {...}}.
		}
		// The largest list of objects anywhere in the object (up to 6 levels deep).
		$best = null;
		$walk = static function ( $node, $node_path, $depth ) use ( &$walk, &$best ) {
			if ( $depth > 6 || ! is_array( $node ) ) {
				return;
			}
			if ( self::is_list( $node ) && $node ) {
				$keys = 0;
				foreach ( $node as $item ) {
					if ( ! is_array( $item ) || ! $item || self::is_list( $item ) ) {
						$keys = -1;
						break;
					}
					$keys += count( $item );
				}
				if ( $keys > 0 && ( null === $best || $keys > $best['score'] ) ) {
					$best = [ 'path' => $node_path, 'rows' => array_values( $node ), 'score' => $keys ];
				}
				if ( $keys > 0 ) {
					return;
				}
			}
			foreach ( $node as $child_key => $child ) {
				$walk( $child, '' === $node_path ? (string) $child_key : $node_path . '.' . $child_key, $depth + 1 );
			}
		};
		$walk( $data, '', 0 );
		if ( null === $best ) {
			return $bom ? [ 'rows' => [ $data ], 'source' => 'json_bom', 'path' => '' ] : null;
		}
		return [ 'rows' => $best['rows'], 'source' => 'json_records', 'path' => $best['path'] ];
	}

	/**
	 * Add a column for every value inside nested objects, named by its path ("author.name",
	 * "image.url"), after the row's own columns. The original columns stay, so mappings and
	 * files that use the whole object (post_meta, ACF groups) keep working. Lists stay as they are.
	 *
	 * @param array $row   Row from a JSON file.
	 * @param int   $depth Maximum nesting depth to expand.
	 * @return array
	 */
	public static function with_nested_columns( $row, $depth = 3 ) {
		if ( ! is_array( $row ) ) {
			return $row;
		}
		$extra = [];
		$walk  = static function ( $value, $prefix, $level ) use ( &$walk, &$extra, $depth ) {
			foreach ( $value as $key => $child ) {
				$path = $prefix . '.' . $key;
				if ( is_array( $child ) && $child && ! self::is_list( $child ) && $level < $depth ) {
					$walk( $child, $path, $level + 1 );
				} else {
					$extra[ $path ] = $child;
				}
			}
		};
		foreach ( $row as $key => $value ) {
			if ( is_string( $key ) && is_array( $value ) && $value && ! self::is_list( $value ) ) {
				$walk( $value, $key, 1 );
			}
		}
		foreach ( $extra as $key => $value ) {
			if ( ! array_key_exists( $key, $row ) ) {
				$row[ $key ] = $value;
			}
		}
		return $row;
	}

	/**
	 * Whether an array is a list (0..n-1 keys), like a JSON array.
	 *
	 * @param array $value Array.
	 * @return bool
	 */
	private static function is_list( array $value ) {
		return array_keys( $value ) === range( 0, count( $value ) - 1 );
	}

	/** Validate every item and derive preview metadata from one container scan. */
	public function preview( $path, array $options = [] ) {
		$result = [
			'headers'    => [],
			'preview'    => [],
			'total_rows' => 0,
		];
		try {
			foreach ( $this->rows( $path, $options ) as $key => $row ) {
				if ( ! is_array( $row ) ) {
					return new \WP_Error( 'invalid_structure', __( 'JSON items must be objects/arrays', 'import-export-by-rockstarlab' ) );
				}
				$row = self::with_nested_columns( $row );
				// Records can have different keys: the columns are those of the first 50 records.
				if ( $result['total_rows'] < 50 ) {
					foreach ( array_keys( $row ) as $column ) {
						if ( ! in_array( $column, $result['headers'], true ) ) {
							$result['headers'][] = $column;
						}
					}
				}
				if ( $result['total_rows'] < 5 ) {
					// Match array_slice: string keys survive, integer keys are renumbered.
					if ( is_string( $key ) ) {
						$result['preview'][ $key ] = $row;
					} else {
						$result['preview'][] = $row;
					}
				}
				++$result['total_rows'];
			}
			return $result;
		} catch ( \Throwable $error ) {
			return self::invalid_json_error( 'json_parse_error', $error );
		}
	}

	/**
	 * Write rows one at a time (large exports): the same bytes json_encode() writes for the
	 * whole list, without holding every row in memory.
	 *
	 * @param \Traversable $rows      Rows.
	 * @param string       $file_path Target file.
	 * @param array        $options   Options (pretty_print, flags).
	 * @return true|\WP_Error
	 */
	private function generate_stream( \Traversable $rows, $file_path, $options ) {
		$flags  = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | (int) ( $options['flags'] ?? 0 );
		$pretty = ! empty( $options['pretty_print'] );
		if ( $pretty ) {
			$flags |= JSON_PRETTY_PRINT;
		}
		$handle = fopen( $file_path, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Streamed export file.
		if ( ! $handle ) {
			return new \WP_Error( 'file_write_error', __( 'Cannot write JSON file', 'import-export-by-rockstarlab' ) );
		}
		try {
			$count = 0;
			foreach ( $rows as $row ) {
				$json = json_encode( $row, $flags );
				if ( false === $json ) {
					return new \WP_Error( 'json_encode_error', sprintf( /* translators: %s: JSON error message */ __( 'JSON encode error: %s', 'import-export-by-rockstarlab' ), json_last_error_msg() ) );
				}
				if ( $pretty ) {
					$json = '    ' . str_replace( "\n", "\n    ", $json );
				}
				$chunk = ( 0 === $count ? ( $pretty ? "[\n" : '[' ) : ( $pretty ? ",\n" : ',' ) ) . $json;
				if ( strlen( $chunk ) !== fwrite( $handle, $chunk ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
					return new \WP_Error( 'file_write_error', __( 'Cannot write JSON file', 'import-export-by-rockstarlab' ) );
				}
				++$count;
			}
			$tail = 0 === $count ? '[]' : ( $pretty ? "\n]" : ']' );
			if ( strlen( $tail ) !== fwrite( $handle, $tail ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions
				return new \WP_Error( 'file_write_error', __( 'Cannot write JSON file', 'import-export-by-rockstarlab' ) );
			}
		} finally {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		}
		return true;
	}

	/**
	 * Generate JSON file from data
	 *
	 * @param array  $data      Data array to write
	 * @param string $file_path Target file path
	 * @param array  $options   Optional. Generation options (pretty_print, flags)
	 * @return bool|WP_Error True on success or WP_Error on failure
	 */
	public function generate( $data, $file_path, $options = [] ) {
		if ( $data instanceof \Traversable ) {
			return $this->generate_stream( $data, $file_path, $options );
		}
		if ( empty( $data ) ) {
			$result = file_put_contents( $file_path, '[]' );
			if ( false === $result ) {
				return new \WP_Error( 'file_write_error', __( 'Cannot write JSON file', 'import-export-by-rockstarlab' ) );
			}
			return true;
		}

		$flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;

		if ( ! empty( $options['pretty_print'] ) ) {
			$flags |= JSON_PRETTY_PRINT;
		}

		if ( ! empty( $options['flags'] ) ) {
			$flags |= $options['flags'];
		}

		$json = json_encode( $data, $flags );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error(
				'json_encode_error',
				sprintf(
					/* translators: %s: JSON error message */
					__( 'JSON encode error: %s', 'import-export-by-rockstarlab' ),
					json_last_error_msg()
				)
			);
		}

		$result = file_put_contents( $file_path, $json );

		if ( false === $result ) {
			return new \WP_Error( 'file_write_error', __( 'Cannot write JSON file', 'import-export-by-rockstarlab' ) );
		}

		return true;
	}

	/**
	 * Validate JSON file
	 *
	 * @param string $file_path Absolute path to file
	 * @return bool|WP_Error True if valid or WP_Error with errors
	 */
	public function validate( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'JSON file not found', 'import-export-by-rockstarlab' ) );
		}

		if ( ! is_readable( $file_path ) ) {
			return new \WP_Error( 'file_not_readable', __( 'JSON file is not readable', 'import-export-by-rockstarlab' ) );
		}

		// Check file extension
		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, $this->get_extensions(), true ) ) {
			return new \WP_Error( 'invalid_extension', __( 'Invalid JSON file extension', 'import-export-by-rockstarlab' ) );
		}

		// Validate arrays and legacy objects one member at a time, including trailing syntax.
		$stream = fopen( $file_path, 'rb' );
		if ( false === $stream ) {
			return new \WP_Error( 'file_read_error', __( 'Cannot read JSON file', 'import-export-by-rockstarlab' ) );
		}
		try {
			do {
				$first = fgetc( $stream );
			} while ( false !== $first && false !== strpos( " \t\r\n", $first ) );
		} finally {
			fclose( $stream );
		}
		if ( '[' === $first || '{' === $first ) {
			try {
				foreach ( JSON_Row_Stream::rows( $file_path, [ 'object_members' => '{' === $first ] ) as $row ) {
					unset( $row );
				}
				return true;
			} catch ( \Throwable $error ) {
				return self::invalid_json_error( 'json_invalid', $error );
			}
		}
		// Retain validation of scalar documents.
		$content = file_get_contents( $file_path );
		if ( false === $content ) {
			return new \WP_Error( 'file_read_error', __( 'Cannot read JSON file', 'import-export-by-rockstarlab' ) );
		}

		if ( empty( trim( $content ) ) ) {
			return new \WP_Error( 'empty_file', __( 'JSON file is empty', 'import-export-by-rockstarlab' ) );
		}

		json_decode( $content );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error(
				'json_invalid',
				sprintf(
					/* translators: %s: JSON error message */
					__( 'Invalid JSON: %s', 'import-export-by-rockstarlab' ),
					json_last_error_msg()
				)
			);
		}

		return true;
	}

	/**
	 * Get JSON headers (field names from first object)
	 *
	 * @param string $file_path Absolute path to file
	 * @param array  $options   Optional. Parser options
	 * @return array|WP_Error Array of headers or WP_Error on failure
	 */
	public function get_headers( $file_path, $options = [] ) {
		// Records can have different keys: the columns are those of the first 50 records.
		$data = $this->scan_rows( $file_path, 0, 50, $options );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( empty( $data ) ) {
			return [];
		}

		$headers = [];
		foreach ( $data as $item ) {
			if ( ! is_array( $item ) ) {
				return new \WP_Error( 'invalid_structure', __( 'JSON items must be objects/arrays', 'import-export-by-rockstarlab' ) );
			}
			foreach ( array_keys( $item ) as $key ) {
				$headers[ $key ] = true;
			}
		}
		return array_keys( $headers );
	}

	/**
	 * Count items in JSON file
	 *
	 * @param string $file_path Absolute path to file
	 * @return int|WP_Error Item count or WP_Error on failure
	 */
	public function count_rows( $file_path ) {
		return $this->scan_rows( $file_path, 0, 0, [], true );
	}

	/** Validate the entire document while retaining only the requested rows. */
	private function scan_rows( $path, $offset, $limit, array $options, $count_only = false ) {
		// Preserve native key and slicing semantics while bounding retained rows.
		$stream = @fopen( $path, 'rb' );
		if ( ! $stream ) {
			return new \WP_Error( 'file_read_error', __( 'Cannot read JSON file', 'import-export-by-rockstarlab' ) ); }
		do {
			$first = fgetc( $stream );
		} while ( false !== $first && false !== strpos( " \t\r\n", $first ) );
		fclose( $stream );
		if ( ! in_array( $first, [ '[', '{' ], true ) ) {
			$all = $this->parse( $path, $options );
			return is_wp_error( $all ) ? $all : ( $count_only ? count( $all ) : array_slice( $all, $offset, $limit ) );
		}
		if ( ! $count_only && ( $offset < 0 || $limit < 0 ) ) {
			$total = $this->scan_rows( $path, 0, 0, $options, true );
			if ( is_wp_error( $total ) ) {
				return $total; }
			$offset = $offset < 0 ? max( 0, $total + $offset ) : $offset;
			$limit  = $limit < 0 ? max( 0, $total + $limit - $offset ) : $limit;
		}
		$rows  = [];
		$count = 0;
		try {
			foreach ( ( '{' === $first ? JSON_Object_Stream::rows( $path, $options ) : JSON_Row_Stream::rows( $path, $options ) ) as $key => $row ) {
				if ( ! $count_only && $count >= $offset && count( $rows ) < $limit ) {
					$row = self::with_nested_columns( $row );
					if ( is_string( $key ) ) {
						$rows[ $key ] = $row;
					} else {
						$rows[] = $row; }
				}
				++$count;
			}
		} catch ( \Throwable $error ) {
			return self::invalid_json_error( 'json_parse_error', $error );
		}
		return $count_only ? $count : $rows;
	}

	/**
	 * Get supported extensions
	 *
	 * @return array
	 */
	public function get_extensions() {
		return [ 'json' ];
	}

	/**
	 * Get supported MIME types
	 *
	 * @return array
	 */
	public function get_mime_types() {
		return [
			'application/json',
			'text/json',
		];
	}

	/**
	 * "Invalid JSON file" with a hint people can act on, from the stream reader's exception.
	 *
	 * @param string     $code  Error code.
	 * @param \Throwable $error Parser exception.
	 * @return \WP_Error
	 */
	public static function invalid_json_error( $code, $error ) {
		$reason = $error instanceof \Throwable ? $error->getMessage() : '';
		$hints  = [
			'Incomplete JSON container.' => __( 'The file ends before the JSON is complete: a closing ] or } is missing. Download or export it again.', 'import-export-by-rockstarlab' ),
			'Unbalanced JSON row.'       => __( 'The file ends before the JSON is complete: a closing ] or } is missing. Download or export it again.', 'import-export-by-rockstarlab' ),
			'Invalid JSON row.'          => __( 'A record is not valid JSON, for example because of a missing comma or quotation mark. Check the file in a JSON validator.', 'import-export-by-rockstarlab' ),
			'Missing JSON row.'          => __( 'A record is not valid JSON, for example because of a missing comma or quotation mark. Check the file in a JSON validator.', 'import-export-by-rockstarlab' ),
			'Expected a JSON container without trailing content.' => __( 'There is text after the end of the JSON. Remove it, or save only the JSON array.', 'import-export-by-rockstarlab' ),
			'JSON depth exceeded.'       => __( 'The JSON is nested too deeply to import.', 'import-export-by-rockstarlab' ),
		];
		$message = __( 'Invalid JSON file', 'import-export-by-rockstarlab' );
		if ( isset( $hints[ $reason ] ) ) {
			/* translators: %s: what is wrong with the file */
			$message = sprintf( __( 'Invalid JSON file: %s', 'import-export-by-rockstarlab' ), $hints[ $reason ] );
		}
		return new \WP_Error( $code, $message );
	}
}

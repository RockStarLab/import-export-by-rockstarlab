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
				return new \WP_Error( 'json_parse_error', __( 'Invalid JSON file', 'import-export-by-rockstarlab' ) );
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
				if ( 0 === $result['total_rows'] ) {
					$result['headers'] = array_keys( $row );
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
			return new \WP_Error( 'json_parse_error', __( 'Invalid JSON file', 'import-export-by-rockstarlab' ) );
		}
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
				return new \WP_Error( 'json_invalid', __( 'Invalid JSON file', 'import-export-by-rockstarlab' ) );
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
		$data = $this->scan_rows( $file_path, 0, 1, $options );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( empty( $data ) ) {
			return [];
		}

		$first_item = reset( $data );

		if ( ! is_array( $first_item ) ) {
			return new \WP_Error( 'invalid_structure', __( 'JSON items must be objects/arrays', 'import-export-by-rockstarlab' ) );
		}

		return array_keys( $first_item );
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
			return new \WP_Error( 'json_parse_error', __( 'Invalid JSON file', 'import-export-by-rockstarlab' ) );
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
}

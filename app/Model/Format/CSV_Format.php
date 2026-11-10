<?php
/**
 * CSV Format Handler
 *
 * Handles parsing and generation of CSV files
 *
 * @package RockStarLab\ImportExport\Model\Format
 */

namespace RockStarLab\ImportExport\Model\Format;

defined( 'ABSPATH' ) || exit;

class CSV_Format implements File_Format_Interface {
	/**
	 * Default delimiter
	 *
	 * @var string
	 */
	const DEFAULT_DELIMITER = ',';

	/**
	 * Default enclosure
	 *
	 * @var string
	 */
	const DEFAULT_ENCLOSURE = '"';

	/**
	 * Default escape character
	 *
	 * @var string
	 */
	// Disable PHP's proprietary backslash escaping for RFC-4180 compatibility.
	// This is required so JSON strings containing `\"` survive round-trips
	// through fputcsv/fgetcsv (otherwise backslashes may be stripped and the JSON
	// becomes invalid, breaking ACF repeater/flexible content portable exports).
	const DEFAULT_ESCAPE = '';

	/**
	 * Parse entire CSV file
	 *
	 * @param string $file_path Absolute path to CSV file
	 * @param array  $options   Optional. Parser options (delimiter, enclosure, has_header)
	 * @return array|\WP_Error Array of parsed data or WP_Error on failure
	 */
	public function parse( $file_path, $options = [] ) {
		list( $file_path, $options ) = $this->normalize_source( $file_path, (array) $options );
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'CSV file not found', 'import-export-by-rockstarlab' ) );
		}

		$delimiter  = $options['delimiter'] ?? self::DEFAULT_DELIMITER;
		$delimiter  = $this->normalize_delimiter( $delimiter );
		$enclosure  = $options['enclosure'] ?? self::DEFAULT_ENCLOSURE;
		$escape     = $options['escape'] ?? self::DEFAULT_ESCAPE;
		$has_header = $options['has_header'] ?? true;
		$encoding   = $options['encoding'] ?? 'UTF-8';

		$use_custom_parser = strlen( $delimiter ) > 1;

		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		if ( ! $handle ) {
			return new \WP_Error( 'file_open_error', __( 'Cannot open CSV file', 'import-export-by-rockstarlab' ) );
		}

		$data    = [];
		$headers = [];
		$row_num = 0;

		while ( true ) {
			if ( $use_custom_parser ) {
				$row_line = $this->read_csv_record( $handle, $enclosure, $escape );
				if ( false === $row_line ) {
					break;
				}
				$row = $this->parse_csv_line( $row_line, $delimiter, $enclosure, $escape );
			} else {
				$row = fgetcsv( $handle, 0, $delimiter, $enclosure, $escape );
				if ( false === $row ) {
					break;
				}
			}

			if ( $row === null ) {
				continue;
			}

			if ( $encoding !== 'UTF-8' ) {
				$row = array_map(
					function ( $value ) use ( $encoding ) {
						return mb_convert_encoding( $value, 'UTF-8', $encoding );
					},
					$row
				);
			}

			if ( 0 === $row_num && $has_header ) {
				$headers = $this->strip_header_bom( $row );
				++$row_num;
				continue;
			}

			if ( $has_header && ! empty( $headers ) ) {
				// Skip rows where column count doesn't match headers (e.g. wrong delimiter)
				if ( count( $row ) !== count( $headers ) ) {
					// Pad or truncate row to match header count
					$row = array_slice( array_pad( $row, count( $headers ), '' ), 0, count( $headers ) );
				}
				$combined = array_combine( $headers, $row );
				if ( false !== $combined ) {
					$data[] = $combined;
				}
			} else {
				$data[] = $row;
			}

			++$row_num;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		return $data;
	}

	/** Stream rows with the same CSV mapping and quoting rules as parse(). */
	public function rows( $file_path, $options = [], &$cursor = null, &$preview_headers = null ) {
		list( $file_path, $options ) = $this->normalize_source( $file_path, (array) $options );
		if ( ! file_exists( $file_path ) ) {
			throw new \RuntimeException( 'CSV file not found.' );
		}

		$delimiter  = $options['delimiter'] ?? self::DEFAULT_DELIMITER;
		$delimiter  = $this->normalize_delimiter( $delimiter );
		$enclosure  = $options['enclosure'] ?? self::DEFAULT_ENCLOSURE;
		$escape     = $options['escape'] ?? self::DEFAULT_ESCAPE;
		$has_header = $options['has_header'] ?? true;
		$encoding   = $options['encoding'] ?? 'UTF-8';

		$use_custom_parser = strlen( $delimiter ) > 1;

		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		if ( ! $handle ) {
			throw new \RuntimeException( 'Cannot open CSV file.' );
		}

		$headers = [];
		$row_num = 0;

		try {
			$stat     = fstat( $handle );
			$identity = array_intersect_key( $stat, array_flip( [ 'dev', 'ino', 'size', 'mtime', 'ctime' ] ) );
			$settings = hash( 'sha256', serialize( [ $delimiter, $enclosure, $escape, $has_header, $encoding ] ) );
			if ( null !== $cursor ) {
				if ( ! is_array( $cursor ) || ( $cursor['identity'] ?? null ) !== $identity || ( $cursor['settings'] ?? null ) !== $settings || ! isset( $cursor['offset'], $cursor['headers'], $cursor['row_num'] ) || ! is_int( $cursor['offset'] ) || $cursor['offset'] < 0 || ! is_array( $cursor['headers'] ) || ! is_int( $cursor['row_num'] ) || $cursor['row_num'] < 0 || $cursor['offset'] > fstat( $handle )['size'] || 0 !== fseek( $handle, $cursor['offset'] ) ) {
					throw new \RuntimeException( 'Invalid CSV cursor.' );
				}
				$headers = $cursor['headers'];
				$row_num = $cursor['row_num'];
			}
			while ( true ) {
				if ( $use_custom_parser ) {
					$row_line = $this->read_csv_record( $handle, $enclosure, $escape );
					if ( false === $row_line ) {
						break;
					}
					$row = $this->parse_csv_line( $row_line, $delimiter, $enclosure, $escape );
				} else {
					$row = fgetcsv( $handle, 0, $delimiter, $enclosure, $escape );
					if ( false === $row ) {
						break;
					}
				}

				if ( $row === null ) {
					continue;
				}

				if ( $encoding !== 'UTF-8' ) {
					$row = array_map(
						function ( $value ) use ( $encoding ) {
							return mb_convert_encoding( $value, 'UTF-8', $encoding );
						},
						$row
					);
				}

				if ( 0 === $row_num ) {
					$preview_headers = $has_header ? $this->strip_header_bom( $row ) : array_map(
						static function ( $index ) {
							return 'Column ' . $index;
						},
						range( 1, count( $row ) )
					);
				}

				if ( 0 === $row_num && $has_header ) {
					$headers = $this->strip_header_bom( $row );
					++$row_num;
					continue;
				}

				$cursor = [
					'identity' => $identity,
					'settings' => $settings,
					'offset'   => ftell( $handle ),
					'headers'  => $headers,
					'row_num'  => $row_num + 1,
				];
				if ( $has_header && ! empty( $headers ) ) {
					// Skip rows where column count doesn't match headers (e.g. wrong delimiter)
					if ( count( $row ) !== count( $headers ) ) {
						// Pad or truncate row to match header count
						$row = array_slice( array_pad( $row, count( $headers ), '' ), 0, count( $headers ) );
					}
					$combined = array_combine( $headers, $row );
					if ( false !== $combined ) {
						yield $combined;
					}
				} else {
					yield $row;
				}

				++$row_num;
			}
		} finally {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		}
	}

	/** Read headers, sample and logical row count from one CSV stream. */
	public function preview( $path, array $options = [] ) {
		list( $path, $options ) = $this->normalize_source( $path, (array) $options );
		$result  = [
			'headers'    => [],
			'preview'    => [],
			'total_rows' => 0,
		];
		$cursor  = null;
		$headers = null;
		try {
			foreach ( $this->rows( $path, $options, $cursor, $headers ) as $row ) {
				if ( $result['total_rows'] < 5 ) {
					$result['preview'][] = $row;
				}
				++$result['total_rows'];
			}
			if ( null === $headers ) {
				return new \WP_Error( 'read_error', __( 'Cannot read CSV headers', 'import-export-by-rockstarlab' ) );
			}
			$result['headers'] = $headers;
			// The delimiter actually used, so the wizard can show what was detected.
			$result['detected_delimiter'] = (string) $options['delimiter'];
			return $result;
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'csv_parse_error', __( 'Cannot read CSV file', 'import-export-by-rockstarlab' ) );
		}
	}

	/**
	 * Parse CSV file in chunks
	 *
	 * @param string $file_path Absolute path to CSV file
	 * @param int    $offset    Starting row (0-based, excluding header)
	 * @param int    $limit     Number of rows to read
	 * @param array  $options   Optional. Parser options
	 * @return array|\WP_Error Array of parsed data or WP_Error on failure
	 */
	public function parse_chunk( $file_path, $offset, $limit, $options = [] ) {
		list( $file_path, $options ) = $this->normalize_source( $file_path, (array) $options );
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'CSV file not found', 'import-export-by-rockstarlab' ) );
		}

		$delimiter  = $options['delimiter'] ?? self::DEFAULT_DELIMITER;
		$delimiter  = $this->normalize_delimiter( $delimiter );
		$enclosure  = $options['enclosure'] ?? self::DEFAULT_ENCLOSURE;
		$escape     = $options['escape'] ?? self::DEFAULT_ESCAPE;
		$has_header = $options['has_header'] ?? true;
		$encoding   = $options['encoding'] ?? 'UTF-8';

		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		if ( ! $handle ) {
			return new \WP_Error( 'file_open_error', __( 'Cannot open CSV file', 'import-export-by-rockstarlab' ) );
		}

		$data    = [];
		$headers = [];
		$row_num = 0;
		$read    = 0;

		$use_custom_parser = strlen( $delimiter ) > 1;

		while ( true ) {
			if ( $use_custom_parser ) {
				$row_line = $this->read_csv_record( $handle, $enclosure, $escape );
				if ( false === $row_line ) {
					break;
				}
				$row = $this->parse_csv_line( $row_line, $delimiter, $enclosure, $escape );
			} else {
				$row = fgetcsv( $handle, 0, $delimiter, $enclosure, $escape );
				if ( false === $row ) {
					break;
				}
			}

			if ( $encoding !== 'UTF-8' ) {
				$row = array_map(
					function ( $value ) use ( $encoding ) {
						return mb_convert_encoding( $value, 'UTF-8', $encoding );
					},
					$row
				);
			}

			if ( 0 === $row_num && $has_header ) {
				$headers = $this->strip_header_bom( $row );
				++$row_num;
				continue;
			}

			if ( $row_num - ( $has_header ? 1 : 0 ) < $offset ) {
				++$row_num;
				continue;
			}

			if ( $read >= $limit ) {
				break;
			}

			if ( $has_header && ! empty( $headers ) ) {
				// Pad or truncate row to match header count (wrong delimiter safety)
				if ( count( $row ) !== count( $headers ) ) {
					$row = array_slice( array_pad( $row, count( $headers ), '' ), 0, count( $headers ) );
				}
				$combined = array_combine( $headers, $row );
				if ( false !== $combined ) {
					$data[] = $combined;
				}
			} else {
				$data[] = $row;
			}

			++$row_num;
			++$read;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		return $data;
	}

	/**
	 * Generate CSV file from data
	 *
	 * @param array  $data      Data array to write
	 * @param string $file_path Target file path
	 * @param array  $options   Optional. Generation options (delimiter, headers)
	 * @return bool|\WP_Error True on success or WP_Error on failure
	 */
	public function generate( $data, $file_path, $options = [] ) {
		$delimiter = $options['delimiter'] ?? self::DEFAULT_DELIMITER;
		$delimiter = $this->normalize_delimiter( $delimiter );
		$enclosure = $options['enclosure'] ?? self::DEFAULT_ENCLOSURE;
		$escape    = $options['escape'] ?? self::DEFAULT_ESCAPE;
		$headers   = $options['headers'] ?? null;
		$use_bom   = $options['use_bom'] ?? false;

		$handle = fopen( $file_path, 'w' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		if ( ! $handle ) {
			return new \WP_Error( 'file_create_error', __( 'Cannot create CSV file', 'import-export-by-rockstarlab' ) );
		}

		if ( $use_bom ) {
			fprintf( $handle, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		}

		// Rows may also come from a generator, so large exports are written without loading every row.
		$rows = $data instanceof \Iterator ? $data : new \ArrayIterator( is_array( $data ) ? array_values( $data ) : iterator_to_array( $data, false ) );
		$rows->rewind();
		if ( ! $rows->valid() ) {
			if ( ! empty( $headers ) && is_array( $headers ) ) {
				$this->write_csv_row( $handle, $headers, $delimiter, $enclosure, $escape );
			}

			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			return true;
		}

		$first = $rows->current();
		if ( null === $headers && is_array( $first ) && ! isset( $first[0] ) ) {
			$headers = array_keys( $first );
		}

		if ( ! empty( $headers ) ) {
			$this->write_csv_row( $handle, $headers, $delimiter, $enclosure, $escape );
		}

		for ( ; $rows->valid(); $rows->next() ) {
			$row = $rows->current();
			if ( ! is_array( $row ) ) {
				continue;
			}

			if ( ! empty( $headers ) && ! isset( $row[0] ) ) {
				$ordered_row = [];
				foreach ( $headers as $header ) {
					$value = $row[ $header ] ?? '';
					if ( is_array( $value ) || is_object( $value ) ) {
						$value = wp_json_encode( $value );
					}
					$ordered_row[] = $value;
				}
				$row = $ordered_row;
			} else {
				$row = array_map(
					function ( $value ) {
						if ( is_array( $value ) || is_object( $value ) ) {
							return wp_json_encode( $value );
						}
						return $value;
					},
					$row
				);
			}

			$this->write_csv_row( $handle, $row, $delimiter, $enclosure, $escape );
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		return true;
	}

	/**
	 * Canonical name of a supported source encoding, 'auto', or null when unsupported.
	 *
	 * @param mixed $encoding Requested encoding.
	 * @return string|null
	 */
	public static function normalize_encoding( $encoding ) {
		$map = [
			'auto'         => 'auto',
			'utf-8'        => 'UTF-8',
			'utf8'         => 'UTF-8',
			'utf-16le'     => 'UTF-16LE',
			'utf-16be'     => 'UTF-16BE',
			'windows-1250' => 'Windows-1250',
			'windows-1251' => 'Windows-1251',
			'windows-1252' => 'Windows-1252',
			'iso-8859-1'   => 'ISO-8859-1',
			'iso-8859-2'   => 'ISO-8859-2',
			'iso-8859-15'  => 'ISO-8859-15',
		];
		$key = strtolower( trim( (string) $encoding ) );
		return '' === $key ? 'auto' : ( $map[ $key ] ?? null );
	}

	/**
	 * Detect the file's encoding and delimiter before parsing.
	 *
	 * Spreadsheet programs save CSV files in the system code page (Windows-1252,
	 * Windows-1251), in UTF-16 ("Unicode Text") or with a semicolon. Files that are not
	 * UTF-8 are converted once into a cached UTF-8 copy, so byte offsets stay stable
	 * between import batches. An explicitly chosen encoding is respected.
	 *
	 * @param string $file_path File path.
	 * @param array  $options   Parser options.
	 * @return array Path to read and adjusted options.
	 */
	protected function normalize_source( $file_path, array $options ) {
		if ( ! is_string( $file_path ) || '' === $file_path || ! is_readable( $file_path ) ) {
			return [ $file_path, $options ];
		}
		$requested = self::normalize_encoding( $options['encoding'] ?? '' );
		$encoding  = ( null === $requested || 'auto' === $requested ) ? $this->detect_encoding( $file_path ) : $requested;
		if ( 'UTF-8' !== strtoupper( $encoding ) ) {
			$converted = $this->utf8_copy( $file_path, $encoding );
			// Without a converted copy the bytes are read as they are; the per-value
			// conversion below would fail on the same unsupported encoding.
			$file_path = $converted ? $converted : $file_path;
			$encoding  = 'UTF-8';
		}
		$options['encoding']  = $encoding;
		$options['delimiter'] = $this->detect_delimiter( $file_path, $options['delimiter'] ?? self::DEFAULT_DELIMITER, $options['enclosure'] ?? self::DEFAULT_ENCLOSURE );
		return [ $file_path, $options ];
	}

	/**
	 * Guess the encoding from the first 64 KB of the file.
	 *
	 * @param string $file_path File path.
	 * @return string
	 */
	protected function detect_encoding( $file_path ) {
		$sample = (string) file_get_contents( $file_path, false, null, 0, 65536 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( 0 === strncmp( $sample, "\xEF\xBB\xBF", 3 ) ) {
			return 'UTF-8';
		}
		if ( 0 === strncmp( $sample, "\xFF\xFE", 2 ) ) {
			return 'UTF-16LE';
		}
		if ( 0 === strncmp( $sample, "\xFE\xFF", 2 ) ) {
			return 'UTF-16BE';
		}
		$length = strlen( $sample );
		if ( $length >= 4 && substr_count( $sample, "\0" ) * 3 > $length ) {
			// UTF-16 without a byte order mark: ASCII text leaves every other byte empty.
			return "\0" === $sample[1] ? 'UTF-16LE' : 'UTF-16BE';
		}
		// Do not let a multibyte character cut at the end of the sample decide.
		$trimmed = $length >= 65536 ? preg_replace( '/[\xC0-\xFF][\x80-\xBF]*$/', '', $sample ) : $sample;
		if ( ! function_exists( 'mb_check_encoding' ) || mb_check_encoding( $trimmed, 'UTF-8' ) ) {
			return 'UTF-8';
		}
		// Single-byte code page. Cyrillic words are runs of high bytes; Western accents
		// are isolated letters between ASCII ones.
		$high  = preg_match_all( '/[\xC0-\xFF]/', $sample );
		$pairs = preg_match_all( '/[\xC0-\xFF](?=[\xC0-\xFF])/', $sample );
		return ( $high > 0 && $pairs * 2 >= $high ) ? 'Windows-1251' : 'Windows-1252';
	}

	/**
	 * Convert a file to a cached UTF-8 copy.
	 *
	 * @param string $file_path File path.
	 * @param string $encoding  Source encoding.
	 * @return string|false
	 */
	protected function utf8_copy( $file_path, $encoding ) {
		if ( null === $this->to_utf8( 'a', $encoding ) ) {
			return false;
		}
		$dir = trailingslashit( get_temp_dir() ) . 'rsl-ie-csv';
		if ( ! wp_mkdir_p( $dir ) ) {
			return false;
		}
		$target = $dir . '/' . md5( $file_path . '|' . filesize( $file_path ) . '|' . filemtime( $file_path ) . '|' . $encoding ) . '.csv';
		if ( is_readable( $target ) ) {
			return $target;
		}
		foreach ( (array) glob( $dir . '/*.csv' ) as $old ) {
			if ( is_string( $old ) && filemtime( $old ) < time() - DAY_IN_SECONDS ) {
				wp_delete_file( $old );
			}
		}
		$in  = fopen( $file_path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		$out = fopen( $target . '.part', 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $in || ! $out ) {
			return false;
		}
		$utf16 = 0 === stripos( $encoding, 'UTF-16' );
		$big   = 'UTF-16BE' === strtoupper( $encoding );
		$head  = fread( $in, 2 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
		if ( ! ( $utf16 && in_array( $head, [ "\xFF\xFE", "\xFE\xFF" ], true ) ) ) {
			$carry = $head;
		} else {
			$carry = '';
		}
		while ( ! feof( $in ) || '' !== $carry ) {
			$chunk = $carry . (string) fread( $in, 262144 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			$carry = '';
			if ( '' === $chunk ) {
				break;
			}
			if ( $utf16 && ! feof( $in ) ) {
				if ( strlen( $chunk ) % 2 ) {
					$carry = substr( $chunk, -1 );
					$chunk = substr( $chunk, 0, -1 );
				}
				// Keep a high surrogate together with its pair (emoji and other astral characters).
				$last = substr( $chunk, -2 );
				$unit = $big ? ( ord( $last[0] ) << 8 ) | ord( $last[1] ) : ( ord( $last[1] ) << 8 ) | ord( $last[0] );
				if ( $unit >= 0xD800 && $unit <= 0xDBFF ) {
					$carry = $last . $carry;
					$chunk = substr( $chunk, 0, -2 );
				}
			}
			fwrite( $out, (string) $this->to_utf8( $chunk, $encoding ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			if ( feof( $in ) && '' === $carry ) {
				break;
			}
		}
		fclose( $in ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		fclose( $out ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		return rename( $target . '.part', $target ) ? $target : false;
	}

	/**
	 * Convert text to UTF-8 with whichever extension supports the encoding.
	 *
	 * mbstring has no Windows-1250, iconv on some systems has no UTF-16, so neither
	 * alone covers every encoding the importer offers.
	 *
	 * @param string $text     Text.
	 * @param string $encoding Source encoding.
	 * @return string|null Converted text, or null when no extension supports the encoding.
	 */
	protected function to_utf8( $text, $encoding ) {
		static $mb = null;
		if ( null === $mb ) {
			$mb = function_exists( 'mb_list_encodings' ) ? array_map( 'strtolower', mb_list_encodings() ) : [];
		}
		if ( in_array( strtolower( $encoding ), $mb, true ) ) {
			return mb_convert_encoding( $text, 'UTF-8', $encoding );
		}
		if ( function_exists( 'iconv' ) ) {
			$converted = @iconv( $encoding, 'UTF-8//IGNORE', $text ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
			if ( false !== $converted ) {
				return $converted;
			}
		}
		return null;
	}

	/**
	 * Use the delimiter that the header line actually contains.
	 *
	 * Applies only to the default comma: when the header contains no comma, the most
	 * frequent of semicolon, tab and pipe is used, so a semicolon file from a European
	 * spreadsheet does not end up as a single column. A delimiter chosen explicitly is kept.
	 *
	 * @param string $file_path File path (UTF-8).
	 * @param string $delimiter Configured delimiter.
	 * @param string $enclosure Enclosure character.
	 * @return string
	 */
	protected function detect_delimiter( $file_path, $delimiter, $enclosure ) {
		$configured = $this->normalize_delimiter( $delimiter );
		// Only the default comma is a guess; any other delimiter was chosen on purpose.
		if ( ',' !== $configured ) {
			return $delimiter;
		}
		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen
		if ( ! $handle ) {
			return $delimiter;
		}
		$line = (string) fgets( $handle, 65536 );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		$line = ltrim( $line, "\xEF\xBB\xBF" );
		if ( '' !== $enclosure ) {
			$q    = preg_quote( $enclosure, '/' );
			$line = (string) preg_replace( '/' . $q . '(?:[^' . $q . ']|' . $q . $q . ')*' . $q . '/', '', $line );
		}
		if ( substr_count( $line, $configured ) > 0 ) {
			return $delimiter;
		}
		$best  = $delimiter;
		$count = 0;
		foreach ( [ ',', ';', "\t", '|' ] as $candidate ) {
			$found = substr_count( $line, $candidate );
			if ( $found > $count ) {
				$best  = $candidate;
				$count = $found;
			}
		}
		return $best;
	}

	/**
	 * Remove a UTF-8 byte order mark from the first header cell.
	 *
	 * Spreadsheet applications (and WooCommerce's own CSV export) prepend a BOM,
	 * which would otherwise become part of the first column name.
	 *
	 * @param array $row Header row.
	 * @return array
	 */
	protected function strip_header_bom( $row ) {
		if ( is_array( $row ) && isset( $row[0] ) && is_string( $row[0] ) && 0 === strpos( $row[0], "\xEF\xBB\xBF" ) ) {
			$row[0] = substr( $row[0], 3 );
		}
		return $row;
	}

	/**
	 * Normalize delimiter string.
	 *
	 * @param string $delimiter Input delimiter from options
	 * @return string Normalized delimiter
	 */
	protected function normalize_delimiter( $delimiter ) {
		if ( $delimiter === '\\t' || $delimiter === "\t" || strtolower( $delimiter ) === 'tab' ) {
			return "\t";
		}

		if ( $delimiter === '' ) {
			return self::DEFAULT_DELIMITER;
		}

		return $delimiter;
	}

	/**
	 * Write a CSV row with custom delimiter support (multi char allowed)
	 *
	 * @param resource $handle    File handle
	 * @param array    $row       Row values array
	 * @param string   $delimiter Delimiter
	 * @param string   $enclosure Enclosure
	 * @param string   $escape    Escape char
	 * @return bool
	 */
	protected function write_csv_row( $handle, $row, $delimiter, $enclosure, $escape ) {
		if ( strlen( $delimiter ) === 1 ) {
			return (bool) fputcsv( $handle, $row, $delimiter, $enclosure, $escape );
		}

		$encoded_row = [];
		foreach ( $row as $value ) {
			if ( is_array( $value ) || is_object( $value ) ) {
				$value = wp_json_encode( $value );
			}

			$value = (string) $value;

			if ( $enclosure !== '' ) {
				$value = str_replace( $enclosure, $enclosure . $enclosure, $value );
			}

			if ( $enclosure !== '' && ( strpos( $value, $delimiter ) !== false || strpos( $value, "\n" ) !== false || strpos( $value, "\r" ) !== false || strpos( $value, $enclosure ) !== false ) ) {
				$value = $enclosure . $value . $enclosure;
			}

			$encoded_row[] = $value;
		}

		$line = implode( $delimiter, $encoded_row ) . "\n";
		return (bool) fwrite( $handle, $line ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
	}

	/** * Read a possibly multiline CSV record (for quoted fields containing newlines).
	 *
	 * @param resource $handle   File handle
	 * @param string   $enclosure Enclosure char
	 * @param string   $escape    Escape char
	 * @return string|false Record line or false on EOF
	 */
	protected function read_csv_record( $handle, $enclosure, $escape ) {
		$record     = '';
		$in_quotes  = false;
		$encl_len   = strlen( $enclosure );
		$escape_len = strlen( $escape );

		while ( ( $line = fgets( $handle ) ) !== false ) {
			$record .= $line;

			for ( $i = 0; $i < strlen( $line ); $i++ ) {
				$char = $line[ $i ];

				if ( $char === $escape && $escape !== '' && $i + 1 < strlen( $line ) && $line[ $i + 1 ] === $enclosure ) {
					++$i;
					continue;
				}

				if ( $char === $enclosure ) {
					$in_quotes = ! $in_quotes;
				}
			}

			if ( ! $in_quotes ) {
				break;
			}
		}

		return $record === '' ? false : $record;
	}

	/**
	 * Parse a single CSV line into columns using custom delimiter
	 *
	 * @param string $line      CSV row line
	 * @param string $delimiter Column delimiter
	 * @param string $enclosure Enclosure char
	 * @param string $escape    Escape char
	 * @return array Parsed row
	 */
	protected function parse_csv_line( $line, $delimiter, $enclosure, $escape ) {
		$columns   = [];
		$token     = '';
		$in_quotes = false;
		$len       = strlen( $line );
		$delim_len = strlen( $delimiter );

		for ( $i = 0; $i < $len; $i++ ) {
			if ( $in_quotes ) {
				if ( $line[ $i ] === $enclosure ) {
					if ( $i + 1 < $len && $line[ $i + 1 ] === $enclosure ) {
						$token .= $enclosure;
						++$i;
					} else {
						$in_quotes = false;
					}
				} else {
					$token .= $line[ $i ];
				}
			} else {
				if ( $delim_len > 0 && substr( $line, $i, $delim_len ) === $delimiter ) {
					$columns[] = $token;
					$token     = '';
					$i        += $delim_len - 1;
					continue;
				}

				if ( $line[ $i ] === $enclosure ) {
					$in_quotes = true;
					continue;
				}

				if ( $line[ $i ] === "\r" || $line[ $i ] === "\n" ) {
					continue;
				}

				$token .= $line[ $i ];
			}
		}

		$columns[] = $token;

		return $columns;
	}

	/** * Validate CSV file
	 *
	 * @param string $file_path Absolute path to file
	 * @return bool|\WP_Error True if valid or WP_Error on failure
	 */
	public function validate( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'CSV file not found', 'import-export-by-rockstarlab' ) );
		}

		if ( ! is_readable( $file_path ) ) {
			return new \WP_Error( 'file_not_readable', __( 'CSV file is not readable', 'import-export-by-rockstarlab' ) );
		}

		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, $this->get_extensions(), true ) ) {
			return new \WP_Error( 'invalid_extension', __( 'Invalid CSV file extension', 'import-export-by-rockstarlab' ) );
		}

		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		if ( ! $handle ) {
			return new \WP_Error( 'file_open_error', __( 'Cannot open CSV file', 'import-export-by-rockstarlab' ) );
		}

		$first_line = fgets( $handle );
		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		if ( false === $first_line ) {
			return new \WP_Error( 'empty_file', __( 'CSV file is empty', 'import-export-by-rockstarlab' ) );
		}

		return true;
	}

	/**
	 * Get CSV headers
	 *
	 * @param string $file_path Absolute path to file
	 * @param array  $options   Optional. Parser options
	 * @return array|\WP_Error Array of headers or WP_Error on failure
	 */
	public function get_headers( $file_path, $options = [] ) {
		list( $file_path, $options ) = $this->normalize_source( $file_path, (array) $options );
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'CSV file not found', 'import-export-by-rockstarlab' ) );
		}

		$delimiter = $options['delimiter'] ?? self::DEFAULT_DELIMITER;
		$delimiter = $this->normalize_delimiter( $delimiter );
		$enclosure = $options['enclosure'] ?? self::DEFAULT_ENCLOSURE;
		$escape    = $options['escape'] ?? self::DEFAULT_ESCAPE;
		$encoding  = $options['encoding'] ?? 'UTF-8';

		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		if ( ! $handle ) {
			return new \WP_Error( 'file_open_error', __( 'Cannot open CSV file', 'import-export-by-rockstarlab' ) );
		}

		if ( strlen( $delimiter ) === 1 ) {
			$headers = fgetcsv( $handle, 0, $delimiter, $enclosure, $escape );
		} else {
			$line    = $this->read_csv_record( $handle, $enclosure, $escape );
			$headers = false === $line ? false : $this->parse_csv_line( $line, $delimiter, $enclosure, $escape );
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		if ( is_array( $headers ) ) {
			$headers = $this->strip_header_bom( $headers );
		}

		if ( false === $headers ) {
			return new \WP_Error( 'read_error', __( 'Cannot read CSV headers', 'import-export-by-rockstarlab' ) );
		}

		if ( $encoding !== 'UTF-8' ) {
			$headers = array_map(
				function ( $value ) use ( $encoding ) {
					return mb_convert_encoding( $value, 'UTF-8', $encoding );
				},
				$headers
			);
		}

		if ( isset( $options['has_header'] ) && ! $options['has_header'] ) {
			return array_map(
				static function ( $index ) {
					return 'Column ' . $index;
				},
				range( 1, count( $headers ) )
			);
		}
		return $headers;
	}

	/**
	 * Count rows in CSV file
	 *
	 * @param string $file_path Absolute path to file
	 * @param array  $options Optional parser settings.
	 * @return int|\WP_Error Row count or WP_Error on failure
	 */
	public function count_rows( $file_path, $options = [] ) {
		list( $file_path, $options ) = $this->normalize_source( $file_path, (array) $options );
		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Count logical CSV records without loading the file into memory.
		if ( ! $handle ) {
			return new \WP_Error( 'file_open_error', __( 'Cannot open CSV file', 'import-export-by-rockstarlab' ) );
		}
		$delimiter = $this->normalize_delimiter( $options['delimiter'] ?? self::DEFAULT_DELIMITER );
		$enclosure = $options['enclosure'] ?? self::DEFAULT_ENCLOSURE;
		$escape    = $options['escape'] ?? self::DEFAULT_ESCAPE;
		$count     = 0;
		try {
			while ( false !== ( strlen( $delimiter ) > 1 ? $this->read_csv_record( $handle, $enclosure, $escape ) : fgetcsv( $handle, 0, $delimiter, $enclosure, $escape ) ) ) {
				++$count;
			}
		} finally {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Close the counting stream.
		}
		return max( 0, $count - ( ( $options['has_header'] ?? true ) ? 1 : 0 ) );
	}

	/**
	 * Get supported extensions
	 *
	 * @return array
	 */
	public function get_extensions() {
		return [ 'csv', 'txt' ];
	}

	/**
	 * Get supported MIME types
	 *
	 * @return array
	 */
	public function get_mime_types() {
		return [
			'text/csv',
			'text/plain',
			'application/csv',
			'application/vnd.ms-excel',
		];
	}
}

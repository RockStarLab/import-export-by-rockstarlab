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
				$headers = $row;
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
				$headers = $row;
				++$row_num;
				continue;
			}

			if ( $row_num - 1 < $offset ) {
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

		if ( empty( $data ) ) {
			if ( ! empty( $headers ) && is_array( $headers ) ) {
				$this->write_csv_row( $handle, $headers, $delimiter, $enclosure, $escape );
			}

			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			return true;
		}

		if ( null === $headers && isset( $data[0] ) && is_array( $data[0] ) && ! isset( $data[0][0] ) ) {
			$headers = array_keys( $data[0] );
		}

		if ( ! empty( $headers ) ) {
			$this->write_csv_row( $handle, $headers, $delimiter, $enclosure, $escape );
		}

		foreach ( $data as $row ) {
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

		return $headers;
	}

	/**
	 * Count rows in CSV file
	 *
	 * @param string $file_path Absolute path to file
	 * @return int|\WP_Error Row count or WP_Error on failure
	 */
	public function count_rows( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'CSV file not found', 'import-export-by-rockstarlab' ) );
		}

		$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		if ( ! $handle ) {
			return new \WP_Error( 'file_open_error', __( 'Cannot open CSV file', 'import-export-by-rockstarlab' ) );
		}

		$count = 0;
		while ( fgets( $handle ) !== false ) {
			++$count;
		}

		fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		return max( 0, $count - 1 );
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

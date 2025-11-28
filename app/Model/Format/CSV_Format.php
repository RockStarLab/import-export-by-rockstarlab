<?php
/**
 * CSV Format Handler
 *
 * Handles parsing and generation of CSV files
 *
 * @package WP_AIE\Model\Format
 */

namespace WP_AIE\Model\Format;

/**
 * CSV Format Handler Class
 *
 * Implements CSV file parsing, generation, and validation with support
 * for large files through chunked reading.
 *
 * @package WP_AIE\Model\Format
 */
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
	const DEFAULT_ESCAPE = '\\';

	/**
	 * Parse entire CSV file
	 *
	 * @param string $file_path Absolute path to CSV file
	 * @param array  $options   Optional. Parser options (delimiter, enclosure, has_header)
	 * @return array|WP_Error Array of parsed data or WP_Error on failure
	 */
	public function parse( $file_path, $options = [] ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'CSV file not found', 'wp-advanced-import-export' ) );
		}

		$delimiter  = $options['delimiter'] ?? self::DEFAULT_DELIMITER;
		$enclosure  = $options['enclosure'] ?? self::DEFAULT_ENCLOSURE;
		$escape     = $options['escape'] ?? self::DEFAULT_ESCAPE;
		$has_header = $options['has_header'] ?? true;
		$encoding   = $options['encoding'] ?? 'UTF-8';

		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			return new \WP_Error( 'file_open_error', __( 'Cannot open CSV file', 'wp-advanced-import-export' ) );
		}

		$data    = [];
		$headers = [];
		$row_num = 0;

		while ( ( $row = fgetcsv( $handle, 0, $delimiter, $enclosure, $escape ) ) !== false ) {
			// Convert encoding if needed
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
			} elseif ( $has_header && ! empty( $headers ) ) {
					$data[] = array_combine( $headers, $row );
			} else {
				$data[] = $row;
			}

			++$row_num;
		}

		fclose( $handle );

		return $data;
	}

	/**
	 * Parse CSV file in chunks
	 *
	 * @param string $file_path Absolute path to CSV file
	 * @param int    $offset    Starting row (0-based, excluding header)
	 * @param int    $limit     Number of rows to read
	 * @param array  $options   Optional. Parser options
	 * @return array|WP_Error Array of parsed data or WP_Error on failure
	 */
	public function parse_chunk( $file_path, $offset, $limit, $options = [] ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'CSV file not found', 'wp-advanced-import-export' ) );
		}

		$delimiter  = $options['delimiter'] ?? self::DEFAULT_DELIMITER;
		$enclosure  = $options['enclosure'] ?? self::DEFAULT_ENCLOSURE;
		$escape     = $options['escape'] ?? self::DEFAULT_ESCAPE;
		$has_header = $options['has_header'] ?? true;
		$encoding   = $options['encoding'] ?? 'UTF-8';

		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			return new \WP_Error( 'file_open_error', __( 'Cannot open CSV file', 'wp-advanced-import-export' ) );
		}

		$data    = [];
		$headers = [];
		$row_num = 0;
		$read    = 0;

		while ( ( $row = fgetcsv( $handle, 0, $delimiter, $enclosure, $escape ) ) !== false ) {
			// Convert encoding if needed
			if ( $encoding !== 'UTF-8' ) {
				$row = array_map(
					function ( $value ) use ( $encoding ) {
						return mb_convert_encoding( $value, 'UTF-8', $encoding );
					},
					$row
				);
			}

			// Read header
			if ( 0 === $row_num && $has_header ) {
				$headers = $row;
				++$row_num;
				continue;
			}

			// Skip rows before offset
			if ( $row_num - 1 < $offset ) {
				++$row_num;
				continue;
			}

			// Stop when limit reached
			if ( $read >= $limit ) {
				break;
			}

			// Add row to data
			if ( $has_header && ! empty( $headers ) ) {
				$data[] = array_combine( $headers, $row );
			} else {
				$data[] = $row;
			}

			++$row_num;
			++$read;
		}

		fclose( $handle );

		return $data;
	}

	/**
	 * Generate CSV file from data
	 *
	 * @param array  $data      Data array to write
	 * @param string $file_path Target file path
	 * @param array  $options   Optional. Generation options (delimiter, headers)
	 * @return bool|WP_Error True on success or WP_Error on failure
	 */
	public function generate( $data, $file_path, $options = [] ) {
		if ( empty( $data ) ) {
			return new \WP_Error( 'empty_data', __( 'No data to export', 'wp-advanced-import-export' ) );
		}

		$delimiter = $options['delimiter'] ?? self::DEFAULT_DELIMITER;
		$enclosure = $options['enclosure'] ?? self::DEFAULT_ENCLOSURE;
		$escape    = $options['escape'] ?? self::DEFAULT_ESCAPE;
		$headers   = $options['headers'] ?? null;
		$use_bom   = $options['use_bom'] ?? false;

		$handle = fopen( $file_path, 'w' );
		if ( ! $handle ) {
			return new \WP_Error( 'file_create_error', __( 'Cannot create CSV file', 'wp-advanced-import-export' ) );
		}

		// Write BOM for Excel compatibility
		if ( $use_bom ) {
			fprintf( $handle, chr( 0xEF ) . chr( 0xBB ) . chr( 0xBF ) );
		}

		// Write headers
		if ( null === $headers && is_array( $data[0] ) && ! isset( $data[0][0] ) ) {
			// Extract headers from associative array
			$headers = array_keys( $data[0] );
		}

		if ( ! empty( $headers ) ) {
			fputcsv( $handle, $headers, $delimiter, $enclosure, $escape );
		}

		// Write data rows
		foreach ( $data as $row ) {
			if ( ! empty( $headers ) && is_array( $row ) && ! isset( $row[0] ) ) {
				// Associative array - reorder by headers
				$ordered_row = [];
				foreach ( $headers as $header ) {
					$ordered_row[] = $row[ $header ] ?? '';
				}
				$row = $ordered_row;
			}

			fputcsv( $handle, $row, $delimiter, $enclosure, $escape );
		}

		fclose( $handle );

		return true;
	}

	/**
	 * Validate CSV file
	 *
	 * @param string $file_path Absolute path to file
	 * @return bool|WP_Error True if valid or WP_Error with errors
	 */
	public function validate( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'CSV file not found', 'wp-advanced-import-export' ) );
		}

		if ( ! is_readable( $file_path ) ) {
			return new \WP_Error( 'file_not_readable', __( 'CSV file is not readable', 'wp-advanced-import-export' ) );
		}

		// Check file extension
		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, $this->get_extensions(), true ) ) {
			return new \WP_Error( 'invalid_extension', __( 'Invalid CSV file extension', 'wp-advanced-import-export' ) );
		}

		// Try to read first line
		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			return new \WP_Error( 'file_open_error', __( 'Cannot open CSV file', 'wp-advanced-import-export' ) );
		}

		$first_line = fgets( $handle );
		fclose( $handle );

		if ( false === $first_line ) {
			return new \WP_Error( 'empty_file', __( 'CSV file is empty', 'wp-advanced-import-export' ) );
		}

		return true;
	}

	/**
	 * Get CSV headers
	 *
	 * @param string $file_path Absolute path to file
	 * @param array  $options   Optional. Parser options
	 * @return array|WP_Error Array of headers or WP_Error on failure
	 */
	public function get_headers( $file_path, $options = [] ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'CSV file not found', 'wp-advanced-import-export' ) );
		}

		$delimiter = $options['delimiter'] ?? self::DEFAULT_DELIMITER;
		$enclosure = $options['enclosure'] ?? self::DEFAULT_ENCLOSURE;
		$escape    = $options['escape'] ?? self::DEFAULT_ESCAPE;
		$encoding  = $options['encoding'] ?? 'UTF-8';

		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			return new \WP_Error( 'file_open_error', __( 'Cannot open CSV file', 'wp-advanced-import-export' ) );
		}

		$headers = fgetcsv( $handle, 0, $delimiter, $enclosure, $escape );
		fclose( $handle );

		if ( false === $headers ) {
			return new \WP_Error( 'read_error', __( 'Cannot read CSV headers', 'wp-advanced-import-export' ) );
		}

		// Convert encoding if needed
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
	 * @return int|WP_Error Row count or WP_Error on failure
	 */
	public function count_rows( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'CSV file not found', 'wp-advanced-import-export' ) );
		}

		$handle = fopen( $file_path, 'r' );
		if ( ! $handle ) {
			return new \WP_Error( 'file_open_error', __( 'Cannot open CSV file', 'wp-advanced-import-export' ) );
		}

		$count = 0;
		while ( fgets( $handle ) !== false ) {
			++$count;
		}

		fclose( $handle );

		// Subtract 1 for header row (assuming first row is header)
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

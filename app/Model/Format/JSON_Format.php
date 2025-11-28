<?php
/**
 * JSON Format Handler
 *
 * Handles parsing and generation of JSON files
 *
 * @package WP_AIE\Model\Format
 */

namespace WP_AIE\Model\Format;

/**
 * JSON Format Handler Class
 *
 * Implements JSON file parsing, generation, and validation with support
 * for large files through streaming.
 *
 * @package WP_AIE\Model\Format
 */
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
			return new \WP_Error( 'file_not_found', __( 'JSON file not found', 'wp-advanced-import-export' ) );
		}

		$content = file_get_contents( $file_path );
		if ( false === $content ) {
			return new \WP_Error( 'file_read_error', __( 'Cannot read JSON file', 'wp-advanced-import-export' ) );
		}

		$depth = $options['depth'] ?? 512;
		$flags = $options['flags'] ?? 0;

		$data = json_decode( $content, true, $depth, $flags );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error(
				'json_parse_error',
				sprintf(
					/* translators: %s: JSON error message */
					__( 'JSON parse error: %s', 'wp-advanced-import-export' ),
					json_last_error_msg()
				)
			);
		}

		// Ensure data is array of items
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'invalid_format', __( 'JSON file must contain an array', 'wp-advanced-import-export' ) );
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
		// Parse entire file first (JSON doesn't support true streaming easily)
		$all_data = $this->parse( $file_path, $options );

		if ( is_wp_error( $all_data ) ) {
			return $all_data;
		}

		// Return slice of data
		return array_slice( $all_data, $offset, $limit );
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
			return new \WP_Error( 'empty_data', __( 'No data to export', 'wp-advanced-import-export' ) );
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
					__( 'JSON encode error: %s', 'wp-advanced-import-export' ),
					json_last_error_msg()
				)
			);
		}

		$result = file_put_contents( $file_path, $json );

		if ( false === $result ) {
			return new \WP_Error( 'file_write_error', __( 'Cannot write JSON file', 'wp-advanced-import-export' ) );
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
			return new \WP_Error( 'file_not_found', __( 'JSON file not found', 'wp-advanced-import-export' ) );
		}

		if ( ! is_readable( $file_path ) ) {
			return new \WP_Error( 'file_not_readable', __( 'JSON file is not readable', 'wp-advanced-import-export' ) );
		}

		// Check file extension
		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, $this->get_extensions(), true ) ) {
			return new \WP_Error( 'invalid_extension', __( 'Invalid JSON file extension', 'wp-advanced-import-export' ) );
		}

		// Try to parse
		$content = file_get_contents( $file_path );
		if ( false === $content ) {
			return new \WP_Error( 'file_read_error', __( 'Cannot read JSON file', 'wp-advanced-import-export' ) );
		}

		if ( empty( trim( $content ) ) ) {
			return new \WP_Error( 'empty_file', __( 'JSON file is empty', 'wp-advanced-import-export' ) );
		}

		json_decode( $content );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error(
				'json_invalid',
				sprintf(
					/* translators: %s: JSON error message */
					__( 'Invalid JSON: %s', 'wp-advanced-import-export' ),
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
		$data = $this->parse( $file_path, $options );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		if ( empty( $data ) ) {
			return [];
		}

		$first_item = $data[0];

		if ( ! is_array( $first_item ) ) {
			return new \WP_Error( 'invalid_structure', __( 'JSON items must be objects/arrays', 'wp-advanced-import-export' ) );
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
		$data = $this->parse( $file_path );

		if ( is_wp_error( $data ) ) {
			return $data;
		}

		return count( $data );
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

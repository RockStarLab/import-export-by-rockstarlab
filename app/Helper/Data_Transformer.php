<?php
/**
 * Data Transformer Helper
 *
 * Provides data sanitization, validation, and transformation utilities.
 * Handles various data types and transformations for import/export operations.
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

class Data_Transformer {

	/**
	 * Sanitize data based on type
	 * Applies WordPress sanitization functions based on data type
	 *
	 * @param mixed  $data Data to sanitize
	 * @param string $type Type of sanitization: text, email, url, html, int, float, bool, slug, key
	 * @return mixed Sanitized data
	 */
	public static function sanitize_data( $data, $type = 'text' ) {
		switch ( $type ) {
			case 'email':
				return sanitize_email( $data );
			case 'url':
				return esc_url_raw( $data );
			case 'html':
				return wp_kses_post( $data );
			case 'int':
				return intval( $data );
			case 'float':
				return floatval( $data );
			case 'bool':
				return filter_var( $data, FILTER_VALIDATE_BOOLEAN );
			case 'slug':
				return sanitize_title( $data );
			case 'key':
				return sanitize_key( $data );
			case 'text':
			default:
				return sanitize_text_field( $data );
		}
	}

	/**
	 * Format date string to specified format
	 * Converts timestamps and date strings to desired format
	 *
	 * @param string|int $date   Date string or Unix timestamp
	 * @param string     $format PHP date format, default 'Y-m-d H:i:s'
	 * @return string Formatted date string or original value if invalid
	 */
	public static function format_date( $date, $format = 'Y-m-d H:i:s' ) {
		if ( empty( $date ) ) {
			return '';
		}

		$timestamp = is_numeric( $date ) ? $date : strtotime( $date );
		if ( ! $timestamp ) {
			return $date;
		}

		return gmdate( $format, $timestamp );
	}

	/**
	 * Validate required fields in data array
	 * Checks if all required fields are present and not empty
	 *
	 * @param array $data           Data array to validate
	 * @param array $required_fields Array of required field names
	 * @return bool|array True if all fields valid, array of error messages if validation fails
	 */
	public static function validate_required( $data, $required_fields = [] ) {
		$errors = [];

		foreach ( $required_fields as $field ) {
			if ( ! isset( $data[ $field ] ) || empty( $data[ $field ] ) ) {
				$errors[] = sprintf( 'Field "%s" is required', $field );
			}
		}

		return empty( $errors ) ? true : $errors;
	}

	/**
	 * Apply multiple transformations to a field value.
	 * Supports search/replace, regex, case changes, prefix/suffix, and PRO integrations.
	 *
	 * @param mixed $value          Value to transform
	 * @param array $transformations {
	 *     Array of transformation rules to apply sequentially.
	 *
	 *     @type string $type   Transformation type: search_replace, regex_replace,
	 *                          uppercase, lowercase, trim, prefix, suffix, pro_transformation
	 *     @type array  $params Type-specific parameters (search, replace, pattern, function, etc.)
	 * }
	 * @return mixed Transformed value
	 */
	public static function transform_field( $value, $transformations = [] ) {
		foreach ( $transformations as $transformation ) {
			$type   = $transformation['type'] ?? '';
			$params = $transformation['params'] ?? [];

			switch ( $type ) {
				case 'search_replace':
					$search  = $params['search'] ?? '';
					$replace = $params['replace'] ?? '';
					$value   = str_replace( $search, $replace, $value );
					break;

				case 'regex_replace':
					$pattern = $params['pattern'] ?? '';
					$replace = $params['replace'] ?? '';
					$value   = preg_replace( $pattern, $replace, $value );
					break;

				case 'uppercase':
					$value = strtoupper( $value );
					break;

				case 'lowercase':
					$value = strtolower( $value );
					break;

				case 'trim':
					$value = trim( $value );
					break;

				case 'prefix':
					$prefix = $params['prefix'] ?? '';
					$value  = $prefix . $value;
					break;

				case 'suffix':
					$suffix = $params['suffix'] ?? '';
					$value  = $value . $suffix;
					break;

				case 'pro_transformation':
					$value = Field_Transformation_Bridge::apply(
						$value,
						(array) ( $params['ids'] ?? [] ),
						[
							'operation' => 'data_transformer',
							'params'    => $params,
						]
					);
					break;
			}
		}

		return $value;
	}

	/**
	 * Parse CSV line into array
	 * Uses str_getcsv with configurable delimiter and enclosure
	 *
	 * @param string $line      CSV line to parse
	 * @param string $delimiter Field delimiter, default ','
	 * @param string $enclosure Field enclosure character, default '"'
	 * @return array Parsed CSV fields
	 */
	public static function parse_csv_line( $line, $delimiter = ',', $enclosure = '"' ) {
		return str_getcsv( $line, $delimiter, $enclosure );
	}

	/**
	 * Convert array to CSV line
	 * Uses fputcsv with configurable delimiter and enclosure
	 *
	 * @param array  $data      Array of values to convert
	 * @param string $delimiter Field delimiter, default ','
	 * @param string $enclosure Field enclosure character, default '"'
	 * @return string CSV line without trailing newline
	 */
	public static function array_to_csv( $data, $delimiter = ',', $enclosure = '"' ) {
		$output = fopen( 'php://temp', 'r+' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		fputcsv( $output, $data, $delimiter, $enclosure );
		rewind( $output );
		$csv = stream_get_contents( $output );
		fclose( $output ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite

		return rtrim( $csv );
	}

	/**
	 * Normalize array keys to valid WordPress meta keys
	 * Converts keys to lowercase and sanitizes them
	 *
	 * @param array $array     Array with keys to normalize
	 * @param bool  $lowercase Whether to convert keys to lowercase, default true
	 * @return array Array with normalized keys
	 */
	public static function normalize_array_keys( $array, $lowercase = true ) {
		$normalized = [];

		foreach ( $array as $key => $value ) {
			$new_key                = $lowercase ? strtolower( $key ) : $key;
			$new_key                = sanitize_key( $new_key );
			$normalized[ $new_key ] = $value;
		}

		return $normalized;
	}

	/**
	 * Recursively clean string values
	 * Strips slashes and trims whitespace from strings, processes arrays recursively
	 *
	 * @param mixed $value Value to clean (string, array, or other)
	 * @return mixed Cleaned value
	 */
	public static function deep_clean( $value ) {
		if ( is_array( $value ) ) {
			return array_map( [ __CLASS__, 'deep_clean' ], $value );
		}

		if ( is_string( $value ) ) {
			$value = stripslashes( $value );
			$value = trim( $value );
		}

		return $value;
	}
}

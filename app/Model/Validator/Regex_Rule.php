<?php
/**
 * Regex Validation Rule
 *
 * Validates values against regular expression patterns
 *
 * @package RockStarLab\ImportExport\Model\Validator
 */

namespace RockStarLab\ImportExport\Model\Validator;

defined( 'ABSPATH' ) || exit;

class Regex_Rule extends Validation_Rule {

	/**
	 * Common regex patterns
	 *
	 * @var array
	 */
	const PATTERNS = [
		'alphanumeric' => '/^[a-zA-Z0-9]+$/',
		'alpha'        => '/^[a-zA-Z]+$/',
		'numeric'      => '/^[0-9]+$/',
		'slug'         => '/^[a-z0-9-]+$/',
		'username'     => '/^[a-zA-Z0-9_-]{3,20}$/',
		'phone'        => '/^[\d\s\-\(\)\+]+$/',
		'zipcode'      => '/^[0-9]{5}(?:-[0-9]{4})?$/',
		'ipv4'         => '/^(?:(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)\.){3}(?:25[0-5]|2[0-4][0-9]|[01]?[0-9][0-9]?)$/',
	];

	/**
	 * Check if value matches regex pattern
	 *
	 * @param mixed $value   Value to validate
	 * @param array $context Optional. Additional context
	 * @return true|WP_Error True if valid, WP_Error if validation fails
	 */
	protected function check( $value, $context ) {
		// Skip validation for empty values (use Required_Rule for that)
		if ( $this->is_empty( $value ) ) {
			return true;
		}

		$field_name = $this->get_field_name( $context );
		$pattern    = $this->get_pattern();

		if ( empty( $pattern ) ) {
			return $this->create_error(
				'no_pattern',
				__( 'No regex pattern specified', 'import-export-by-rockstarlab' )
			);
		}

		// Convert value to string
		$string_value = (string) $value;

		if ( ! preg_match( $pattern, $string_value ) ) {
			$message = $this->get_option( 'message' );

			if ( empty( $message ) ) {
				$message = sprintf(
					/* translators: %s: field name */
					__( '%s format is invalid', 'import-export-by-rockstarlab' ),
					$field_name
				);
			}

			return $this->create_error(
				'pattern_mismatch',
				$message,
				[
					'field'   => $context['field_name'] ?? null,
					'value'   => $string_value,
					'pattern' => $pattern,
				]
			);
		}

		return true;
	}

	/**
	 * Get regex pattern from options
	 *
	 * @return string|null Regex pattern or null
	 */
	private function get_pattern() {
		$pattern = $this->get_option( 'pattern' );

		// If pattern is a string starting with /, use it directly
		if ( is_string( $pattern ) && '/' === $pattern[0] ) {
			return $pattern;
		}

		// Check if it's a named pattern
		if ( is_string( $pattern ) && isset( self::PATTERNS[ $pattern ] ) ) {
			return self::PATTERNS[ $pattern ];
		}

		return $pattern;
	}
}

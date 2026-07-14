<?php
/**
 * Data Type Validation Rule
 *
 * Validates that a value matches the expected data type
 *
 * @package RockStarLab\ImportExport\Model\Validator
 */

namespace RockStarLab\ImportExport\Model\Validator;

defined( 'ABSPATH' ) || exit;

class Data_Type_Rule extends Validation_Rule {

	/**
	 * Check if value matches expected data type
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

		$type       = $this->get_option( 'type', 'string' );
		$field_name = $this->get_field_name( $context );

		switch ( $type ) {
			case 'string':
				return $this->validate_string( $value, $field_name, $context );

			case 'integer':
			case 'int':
				return $this->validate_integer( $value, $field_name, $context );

			case 'float':
			case 'number':
			case 'decimal':
				return $this->validate_float( $value, $field_name, $context );

			case 'boolean':
			case 'bool':
				return $this->validate_boolean( $value, $field_name, $context );

			case 'email':
				return $this->validate_email( $value, $field_name, $context );

			case 'url':
				return $this->validate_url( $value, $field_name, $context );

			case 'date':
				return $this->validate_date( $value, $field_name, $context );

			case 'datetime':
				return $this->validate_datetime( $value, $field_name, $context );

			default:
				return $this->create_error(
					'unknown_type',
					sprintf(
						/* translators: %s: data type */
						__( 'Unknown data type: %s', 'import-export-by-rockstarlab' ),
						$type
					)
				);
		}
	}

	/**
	 * Validate string type
	 *
	 * @param mixed  $value       Value to validate
	 * @param string $field_name  Field name
	 * @param array  $context     Validation context
	 * @return true|WP_Error
	 */
	private function validate_string( $value, $field_name, $context ) {
		if ( ! is_string( $value ) && ! is_numeric( $value ) ) {
			return $this->create_error(
				'invalid_string',
				sprintf(
					/* translators: %s: field name */
					__( '%s must be a string', 'import-export-by-rockstarlab' ),
					$field_name
				),
				[ 'field' => $context['field_name'] ?? null ]
			);
		}

		return true;
	}

	/**
	 * Validate integer type
	 *
	 * @param mixed  $value       Value to validate
	 * @param string $field_name  Field name
	 * @param array  $context     Validation context
	 * @return true|WP_Error
	 */
	private function validate_integer( $value, $field_name, $context ) {
		if ( ! is_numeric( $value ) || (int) $value != $value ) {
			return $this->create_error(
				'invalid_integer',
				sprintf(
					/* translators: %s: field name */
					__( '%s must be an integer', 'import-export-by-rockstarlab' ),
					$field_name
				),
				[ 'field' => $context['field_name'] ?? null ]
			);
		}

		return true;
	}

	/**
	 * Validate float type
	 *
	 * @param mixed  $value       Value to validate
	 * @param string $field_name  Field name
	 * @param array  $context     Validation context
	 * @return true|WP_Error
	 */
	private function validate_float( $value, $field_name, $context ) {
		if ( ! is_numeric( $value ) ) {
			return $this->create_error(
				'invalid_float',
				sprintf(
					/* translators: %s: field name */
					__( '%s must be a number', 'import-export-by-rockstarlab' ),
					$field_name
				),
				[ 'field' => $context['field_name'] ?? null ]
			);
		}

		return true;
	}

	/**
	 * Validate boolean type
	 *
	 * @param mixed  $value       Value to validate
	 * @param string $field_name  Field name
	 * @param array  $context     Validation context
	 * @return true|WP_Error
	 */
	private function validate_boolean( $value, $field_name, $context ) {
		$valid_values = [ true, false, 1, 0, '1', '0', 'true', 'false', 'yes', 'no' ];

		if ( ! in_array( $value, $valid_values, true ) ) {
			return $this->create_error(
				'invalid_boolean',
				sprintf(
					/* translators: %s: field name */
					__( '%s must be a boolean value', 'import-export-by-rockstarlab' ),
					$field_name
				),
				[ 'field' => $context['field_name'] ?? null ]
			);
		}

		return true;
	}

	/**
	 * Validate email format
	 *
	 * @param mixed  $value       Value to validate
	 * @param string $field_name  Field name
	 * @param array  $context     Validation context
	 * @return true|WP_Error
	 */
	private function validate_email( $value, $field_name, $context ) {
		if ( ! is_email( $value ) ) {
			return $this->create_error(
				'invalid_email',
				sprintf(
					/* translators: %s: field name */
					__( '%s must be a valid email address', 'import-export-by-rockstarlab' ),
					$field_name
				),
				[
					'field' => $context['field_name'] ?? null,
					'value' => $value,
				]
			);
		}

		return true;
	}

	/**
	 * Validate URL format
	 *
	 * @param mixed  $value       Value to validate
	 * @param string $field_name  Field name
	 * @param array  $context     Validation context
	 * @return true|WP_Error
	 */
	private function validate_url( $value, $field_name, $context ) {
		if ( ! filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return $this->create_error(
				'invalid_url',
				sprintf(
					/* translators: %s: field name */
					__( '%s must be a valid URL', 'import-export-by-rockstarlab' ),
					$field_name
				),
				[
					'field' => $context['field_name'] ?? null,
					'value' => $value,
				]
			);
		}

		return true;
	}

	/**
	 * Validate date format
	 *
	 * @param mixed  $value       Value to validate
	 * @param string $field_name  Field name
	 * @param array  $context     Validation context
	 * @return true|WP_Error
	 */
	private function validate_date( $value, $field_name, $context ) {
		$format = $this->get_option( 'format', 'Y-m-d' );

		$date = \DateTime::createFromFormat( $format, $value );

		if ( ! $date || $date->format( $format ) !== $value ) {
			return $this->create_error(
				'invalid_date',
				sprintf(
					/* translators: 1: field name, 2: expected format */
					__( '%1$s must be a valid date in format %2$s', 'import-export-by-rockstarlab' ),
					$field_name,
					$format
				),
				[
					'field'  => $context['field_name'] ?? null,
					'value'  => $value,
					'format' => $format,
				]
			);
		}

		return true;
	}

	/**
	 * Validate datetime format
	 *
	 * @param mixed  $value       Value to validate
	 * @param string $field_name  Field name
	 * @param array  $context     Validation context
	 * @return true|WP_Error
	 */
	private function validate_datetime( $value, $field_name, $context ) {
		$format = $this->get_option( 'format', 'Y-m-d H:i:s' );

		$date = \DateTime::createFromFormat( $format, $value );

		if ( ! $date || $date->format( $format ) !== $value ) {
			return $this->create_error(
				'invalid_datetime',
				sprintf(
					/* translators: 1: field name, 2: expected format */
					__( '%1$s must be a valid datetime in format %2$s', 'import-export-by-rockstarlab' ),
					$field_name,
					$format
				),
				[
					'field'  => $context['field_name'] ?? null,
					'value'  => $value,
					'format' => $format,
				]
			);
		}

		return true;
	}
}

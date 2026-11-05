<?php
/**
 * Range Validation Rule
 *
 * Validates numeric values against min/max range
 *
 * @package RockStarLab\ImportExport\Model\Validator
 */

namespace RockStarLab\ImportExport\Model\Validator;

defined( 'ABSPATH' ) || exit;

class Range_Rule extends Validation_Rule {

	/**
	 * Check if value is within numeric range
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

		// Ensure value is numeric
		if ( ! is_numeric( $value ) ) {
			return $this->create_error(
				'not_numeric',
				sprintf(
					/* translators: %s: field name */
					__( '%s must be a number', 'import-export-by-rockstarlab' ),
					$field_name
				),
				[ 'field' => $context['field_name'] ?? null ]
			);
		}

		$numeric_value = (float) $value;
		$min           = $this->get_option( 'min' );
		$max           = $this->get_option( 'max' );

		// Check minimum value
		if ( null !== $min && $numeric_value < $min ) {
			return $this->create_error(
				'min_value',
				sprintf(
					/* translators: 1: field name, 2: minimum value */
					__( '%1$s must be at least %2$s', 'import-export-by-rockstarlab' ),
					$field_name,
					$min
				),
				[
					'field' => $context['field_name'] ?? null,
					'value' => $numeric_value,
					'min'   => $min,
				]
			);
		}

		// Check maximum value
		if ( null !== $max && $numeric_value > $max ) {
			return $this->create_error(
				'max_value',
				sprintf(
					/* translators: 1: field name, 2: maximum value */
					__( '%1$s must not exceed %2$s', 'import-export-by-rockstarlab' ),
					$field_name,
					$max
				),
				[
					'field' => $context['field_name'] ?? null,
					'value' => $numeric_value,
					'max'   => $max,
				]
			);
		}

		return true;
	}
}

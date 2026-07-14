<?php
/**
 * Length Validation Rule
 *
 * Validates string length against min/max constraints
 *
 * @package RockStarLab\ImportExport\Model\Validator
 */

namespace RockStarLab\ImportExport\Model\Validator;

defined( 'ABSPATH' ) || exit;

class Length_Rule extends Validation_Rule {

	/**
	 * Check if value length is within bounds
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

		// Convert to string for length checking
		$string_value = (string) $value;
		$length       = mb_strlen( $string_value, 'UTF-8' );
		$field_name   = $this->get_field_name( $context );

		$min = $this->get_option( 'min' );
		$max = $this->get_option( 'max' );

		// Check minimum length
		if ( null !== $min && $length < $min ) {
			return $this->create_error(
				'min_length',
				sprintf(
					/* translators: 1: field name, 2: minimum length */
					__( '%1$s must be at least %2$d characters', 'import-export-by-rockstarlab' ),
					$field_name,
					$min
				),
				[
					'field'  => $context['field_name'] ?? null,
					'length' => $length,
					'min'    => $min,
				]
			);
		}

		// Check maximum length
		if ( null !== $max && $length > $max ) {
			return $this->create_error(
				'max_length',
				sprintf(
					/* translators: 1: field name, 2: maximum length */
					__( '%1$s must not exceed %2$d characters', 'import-export-by-rockstarlab' ),
					$field_name,
					$max
				),
				[
					'field'  => $context['field_name'] ?? null,
					'length' => $length,
					'max'    => $max,
				]
			);
		}

		// Check exact length if specified
		$exact = $this->get_option( 'exact' );
		if ( null !== $exact && $length !== $exact ) {
			return $this->create_error(
				'exact_length',
				sprintf(
					/* translators: 1: field name, 2: exact length */
					__( '%1$s must be exactly %2$d characters', 'import-export-by-rockstarlab' ),
					$field_name,
					$exact
				),
				[
					'field'  => $context['field_name'] ?? null,
					'length' => $length,
					'exact'  => $exact,
				]
			);
		}

		return true;
	}
}

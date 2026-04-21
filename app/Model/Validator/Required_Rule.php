<?php
/**
 * Required Validation Rule
 *
 * Validates that a field has a non-empty value
 *
 * @package RockStarLab\ImportExport\Model\Validator
 */

namespace RockStarLab\ImportExport\Model\Validator;

defined( 'ABSPATH' ) || exit;

class Required_Rule extends Validation_Rule {

	/**
	 * Check if value is not empty
	 *
	 * @param mixed $value   Value to validate
	 * @param array $context Optional. Additional context
	 * @return true|WP_Error True if valid, WP_Error if validation fails
	 */
	protected function check( $value, $context ) {
		if ( $this->is_empty( $value ) ) {
			$field_name = $this->get_field_name( $context );

			return $this->create_error(
				'required_field',
				sprintf(
					/* translators: %s: field name */
					__( '%s is required', 'import-export-by-rockstarlab' ),
					$field_name
				),
				[
					'field' => $context['field_name'] ?? null,
					'row'   => $context['row'] ?? null,
					'rule'  => 'required',
				]
			);
		}

		return true;
	}
}

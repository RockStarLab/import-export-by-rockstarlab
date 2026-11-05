<?php
/**
 * Validation Rule Base Class
 *
 * Abstract base class for all validation rules using Chain of Responsibility pattern
 *
 * @package RockStarLab\ImportExport\Model\Validator
 */

namespace RockStarLab\ImportExport\Model\Validator;

defined( 'ABSPATH' ) || exit;

abstract class Validation_Rule {

	/**
	 * Next rule in the chain
	 *
	 * @var Validation_Rule|null
	 */
	protected $next = null;

	/**
	 * Rule configuration options
	 *
	 * @var array
	 */
	protected $options = [];

	/**
	 * Constructor
	 *
	 * @param array $options Optional. Rule configuration options
	 */
	public function __construct( $options = [] ) {
		$this->options = $options;
	}

	/**
	 * Set next rule in the chain
	 *
	 * @param Validation_Rule $rule Next validation rule
	 * @return Validation_Rule Returns the next rule for chaining
	 */
	public function set_next( Validation_Rule $rule ) {
		$this->next = $rule;
		return $rule;
	}

	/**
	 * Validate value and pass to next rule if valid
	 *
	 * @param mixed $value   Value to validate
	 * @param array $context Optional. Additional context (field name, all data, etc.)
	 * @return true|WP_Error True if valid, WP_Error if validation fails
	 */
	public function validate( $value, $context = [] ) {
		// Run this rule's check
		$result = $this->check( $value, $context );

		// If validation failed, return error
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// If there's a next rule, pass to it
		if ( null !== $this->next ) {
			return $this->next->validate( $value, $context );
		}

		// All rules passed
		return true;
	}

	/**
	 * Check if value passes this specific rule
	 *
	 * Must be implemented by child classes.
	 *
	 * @param mixed $value   Value to validate
	 * @param array $context Optional. Additional context
	 * @return true|WP_Error True if valid, WP_Error if validation fails
	 */
	abstract protected function check( $value, $context );

	/**
	 * Get field name from context
	 *
	 * @param array $context Validation context
	 * @return string Field name or 'Field'
	 */
	protected function get_field_name( $context ) {
		return $context['field_name'] ?? __( 'Field', 'import-export-by-rockstarlab' );
	}

	/**
	 * Get option value
	 *
	 * @param string $key     Option key
	 * @param mixed  $default Default value if not set
	 * @return mixed Option value
	 */
	protected function get_option( $key, $default = null ) {
		return $this->options[ $key ] ?? $default;
	}

	/**
	 * Check if value is empty
	 *
	 * @param mixed $value Value to check
	 * @return bool True if empty
	 */
	protected function is_empty( $value ) {
		if ( is_null( $value ) ) {
			return true;
		}

		if ( is_string( $value ) && '' === trim( $value ) ) {
			return true;
		}

		if ( is_array( $value ) && empty( $value ) ) {
			return true;
		}

		return false;
	}

	/**
	 * Create validation error
	 *
	 * @param string $code    Error code
	 * @param string $message Error message
	 * @param array  $data    Optional. Additional error data
	 * @return WP_Error
	 */
	protected function create_error( $code, $message, $data = [] ) {
		return new \WP_Error( $code, $message, $data );
	}
}

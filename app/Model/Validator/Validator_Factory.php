<?php
/**
 * Validator Factory
 *
 * Factory class for creating and chaining validation rules
 *
 * @package RockStarLab\ImportExport\Model\Validator
 */

namespace RockStarLab\ImportExport\Model\Validator;

defined( 'ABSPATH' ) || exit;

class Validator_Factory {

	/**
	 * Create validation rule by name
	 *
	 * @param string $rule_name Rule name (required, type, length, range, regex)
	 * @param array  $options   Optional. Rule configuration options
	 * @return Validation_Rule|WP_Error Rule instance or WP_Error if unknown rule
	 */
	public static function create_rule( $rule_name, $options = [] ) {
		$rule_map = [
			'required' => Required_Rule::class,
			'type'     => Data_Type_Rule::class,
			'length'   => Length_Rule::class,
			'range'    => Range_Rule::class,
			'regex'    => Regex_Rule::class,
		];

		$rule_name = strtolower( $rule_name );

		if ( ! isset( $rule_map[ $rule_name ] ) ) {
			return new \WP_Error(
				'unknown_rule',
				sprintf(
					/* translators: %s: rule name */
					__( 'Unknown validation rule: %s', 'import-export-by-rockstarlab' ),
					$rule_name
				)
			);
		}

		$class = $rule_map[ $rule_name ];
		return new $class( $options );
	}

	/**
	 * Create validation chain from rules array
	 *
	 * @param array $rules Array of rule configurations
	 *                     Format: [['rule' => 'required'], ['rule' => 'type', 'type' => 'email']]
	 * @return Validation_Rule|WP_Error First rule in chain or WP_Error
	 */
	public static function create_chain( $rules ) {
		if ( empty( $rules ) ) {
			return new \WP_Error( 'empty_rules', __( 'No validation rules provided', 'import-export-by-rockstarlab' ) );
		}

		$first_rule    = null;
		$previous_rule = null;

		foreach ( $rules as $rule_config ) {
			if ( ! isset( $rule_config['rule'] ) ) {
				continue;
			}

			$rule_name = $rule_config['rule'];
			$options   = $rule_config;
			unset( $options['rule'] );

			$rule = self::create_rule( $rule_name, $options );

			if ( is_wp_error( $rule ) ) {
				return $rule;
			}

			if ( null === $first_rule ) {
				$first_rule = $rule;
			}

			if ( null !== $previous_rule ) {
				$previous_rule->set_next( $rule );
			}

			$previous_rule = $rule;
		}

		return $first_rule;
	}

	/**
	 * Create field validator with multiple rules
	 *
	 * @param string $field_name Field name for error messages
	 * @param array  $rules      Array of rule configurations
	 * @return callable Validator function
	 */
	public static function create_field_validator( $field_name, $rules ) {
		$chain = self::create_chain( $rules );

		return function ( $value, $context = [] ) use ( $field_name, $chain ) {
			if ( is_wp_error( $chain ) ) {
				return $chain;
			}

			$context['field_name'] = $field_name;
			return $chain->validate( $value, $context );
		};
	}

	/**
	 * Validate multiple fields with their rules
	 *
	 * @param array $data       Data to validate (associative array)
	 * @param array $field_rules Field rules configuration
	 *                           Format: ['email' => [['rule' => 'required'], ['rule' => 'type', 'type' => 'email']]]
	 * @return true|array True if all valid, array of errors if validation fails
	 */
	public static function validate_fields( $data, $field_rules ) {
		$errors = [];

		foreach ( $field_rules as $field_name => $rules ) {
			$value     = $data[ $field_name ] ?? null;
			$validator = self::create_field_validator( $field_name, $rules );
			$result    = $validator( $value );

			if ( is_wp_error( $result ) ) {
				$errors[ $field_name ] = $result;
			}
		}

		return empty( $errors ) ? true : $errors;
	}

	/**
	 * Quick validator creators for common rules
	 */

	/**
	 * Create required field validator
	 *
	 * @return Validation_Rule
	 */
	public static function required() {
		return new Required_Rule();
	}

	/**
	 * Create type validator
	 *
	 * @param string $type Data type (string, int, email, url, date, etc.)
	 * @return Validation_Rule
	 */
	public static function type( $type ) {
		return new Data_Type_Rule( [ 'type' => $type ] );
	}

	/**
	 * Create length validator
	 *
	 * @param int|null $min Minimum length
	 * @param int|null $max Maximum length
	 * @return Validation_Rule
	 */
	public static function length( $min = null, $max = null ) {
		return new Length_Rule(
			[
				'min' => $min,
				'max' => $max,
			]
		);
	}

	/**
	 * Create range validator
	 *
	 * @param float|null $min Minimum value
	 * @param float|null $max Maximum value
	 * @return Validation_Rule
	 */
	public static function range( $min = null, $max = null ) {
		return new Range_Rule(
			[
				'min' => $min,
				'max' => $max,
			]
		);
	}

	/**
	 * Create regex validator
	 *
	 * @param string $pattern Regex pattern or named pattern
	 * @param string $message Optional. Custom error message
	 * @return Validation_Rule
	 */
	public static function regex( $pattern, $message = '' ) {
		return new Regex_Rule(
			[
				'pattern' => $pattern,
				'message' => $message,
			]
		);
	}

	/**
	 * Create email validator (shortcut)
	 *
	 * @return Validation_Rule
	 */
	public static function email() {
		return new Data_Type_Rule( [ 'type' => 'email' ] );
	}

	/**
	 * Create URL validator (shortcut)
	 *
	 * @return Validation_Rule
	 */
	public static function url() {
		return new Data_Type_Rule( [ 'type' => 'url' ] );
	}

	/**
	 * Create integer validator (shortcut)
	 *
	 * @return Validation_Rule
	 */
	public static function integer() {
		return new Data_Type_Rule( [ 'type' => 'integer' ] );
	}
}

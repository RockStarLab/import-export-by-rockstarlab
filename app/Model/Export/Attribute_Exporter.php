<?php
/**
 * WooCommerce Product Attribute Exporter
 *
 * Handles exporting WooCommerce product attributes
 *
 * @package WP_AIE\Model\Export
 */

namespace WP_AIE\Model\Export;

defined( 'ABSPATH' ) || exit;

class Attribute_Exporter extends Abstract_Exporter {

	/**
	 * Get exporter name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'attributes';
	}

	/**
	 * Get exporter description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Export WooCommerce product attributes', 'wp-advanced-import-export' );
	}

	/**
	 * Get supported export filters
	 *
	 * @return array
	 */
	public function get_supported_filters() {
		return [
			'attribute_id'      => __( 'Attribute ID', 'wp-advanced-import-export' ),
			'attribute_name'    => __( 'Attribute Name', 'wp-advanced-import-export' ),
			'attribute_label'   => __( 'Attribute Label', 'wp-advanced-import-export' ),
			'attribute_type'    => __( 'Attribute Type', 'wp-advanced-import-export' ),
			'attribute_orderby' => __( 'Order By', 'wp-advanced-import-export' ),
			'attribute_public'  => __( 'Public', 'wp-advanced-import-export' ),
		];
	}

	/**
	 * Get default fields for export
	 *
	 * @return array
	 */
	public function get_default_fields() {
		return [
			'attribute_id',
			'attribute_name',
			'attribute_label',
			'attribute_type',
			'attribute_orderby',
			'attribute_public',
		];
	}

	/**
	 * Get count of items to export
	 *
	 * @param array $options Export options
	 * @return int
	 */
	public function get_count( $options = [] ) {
		$attributes = $this->get_all_attributes();

		// Apply filters if present
		if ( ! empty( $options['filters'] ) && is_array( $options['filters'] ) ) {
			$count = 0;
			foreach ( $attributes as $attribute ) {
				if ( $this->passes_all_filters( $attribute, $options['filters'] ) ) {
					++$count;
				}
			}
			return $count;
		}

		return count( $attributes );
	}

	/**
	 * Get data based on export options
	 *
	 * @param array $options Export options
	 * @return array
	 */
	public function get_data( $options = [] ) {
		$attributes = $this->get_all_attributes();

		// Apply filters
		if ( ! empty( $options['filters'] ) && is_array( $options['filters'] ) ) {
			$attributes = array_filter(
				$attributes,
				function ( $attribute ) use ( $options ) {
					return $this->passes_all_filters( $attribute, $options['filters'] );
				}
			);
		}

		// Apply limit and offset
		$offset = $options['offset'] ?? 0;
		$limit  = $options['limit'] ?? -1;

		if ( $offset > 0 ) {
			$attributes = array_slice( $attributes, $offset );
		}

		if ( $limit > 0 ) {
			$attributes = array_slice( $attributes, 0, $limit );
		}

		// Format data
		$data = [];
		foreach ( $attributes as $attribute ) {
			$data[] = $this->format_attribute( $attribute, $options );
		}

		return $data;
	}

	/**
	 * Get all attributes from database
	 *
	 * @return array
	 */
	protected function get_all_attributes() {
		global $wpdb;

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			"SELECT * FROM {$wpdb->prefix}woocommerce_attribute_taxonomies ORDER BY attribute_id ASC"
		);

		return $results ?: [];
	}

	/**
	 * Check if attribute passes all filters
	 *
	 * @param object $attribute Attribute object
	 * @param array  $filters   Array of filters
	 * @return bool
	 */
	protected function passes_all_filters( $attribute, $filters ) {
		foreach ( $filters as $filter ) {
			if ( empty( $filter['field'] ) || empty( $filter['condition'] ) ) {
				continue;
			}

			$field_value = $this->get_attribute_field_value( $attribute, $filter['field'] );

			if ( ! $this->check_condition( $field_value, $filter['condition'], $filter['value'] ?? '' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Get attribute field value
	 *
	 * @param object $attribute  Attribute object
	 * @param string $field_name Field name
	 * @return mixed
	 */
	protected function get_attribute_field_value( $attribute, $field_name ) {
		// Map field names to attribute properties
		$field_map = [
			'attribute_id'      => 'attribute_id',
			'attribute_name'    => 'attribute_name',
			'attribute_label'   => 'attribute_label',
			'attribute_type'    => 'attribute_type',
			'attribute_orderby' => 'attribute_orderby',
			'attribute_public'  => 'attribute_public',
		];

		if ( isset( $field_map[ $field_name ] ) ) {
			$property = $field_map[ $field_name ];
			return $attribute->$property ?? '';
		}

		return '';
	}

	/**
	 * Check if a condition matches
	 *
	 * @param mixed  $field_value The value to test
	 * @param string $condition   The condition type
	 * @param mixed  $test_value  The value to test against
	 * @return bool
	 */
	protected function check_condition( $field_value, $condition, $test_value ) {
		switch ( $condition ) {
			case 'equals':
				return $field_value == $test_value;

			case 'not_equals':
				return $field_value != $test_value;

			case 'contains':
				return stripos( (string) $field_value, (string) $test_value ) !== false;

			case 'not_contains':
				return stripos( (string) $field_value, (string) $test_value ) === false;

			case 'starts_with':
				return stripos( (string) $field_value, (string) $test_value ) === 0;

			case 'ends_with':
				$field_lower = strtolower( (string) $field_value );
				$test_lower  = strtolower( (string) $test_value );
				return substr( $field_lower, -strlen( $test_lower ) ) === $test_lower;

			case 'greater':
				return $field_value > $test_value;

			case 'less':
				return $field_value < $test_value;

			case 'equals_or_greater':
				return $field_value >= $test_value;

			case 'equals_or_less':
				return $field_value <= $test_value;

			case 'between':
				$values = array_map( 'trim', explode( ',', (string) $test_value ) );
				if ( count( $values ) === 2 ) {
					return $field_value >= $values[0] && $field_value <= $values[1];
				}
				return true;

			case 'in':
				$values = array_map( 'trim', explode( ',', (string) $test_value ) );
				return in_array( $field_value, $values, false );

			case 'not_in':
				$values = array_map( 'trim', explode( ',', (string) $test_value ) );
				return ! in_array( $field_value, $values, false );

			case 'is_empty':
				return empty( $field_value );

			case 'is_not_empty':
				return ! empty( $field_value );

			default:
				return true;
		}
	}

	/**
	 * Format attribute data
	 *
	 * @param object $attribute Attribute object
	 * @param array  $options   Export options
	 * @return array
	 */
	protected function format_attribute( $attribute, $options ) {
		$fields = $options['fields'] ?? $this->get_default_fields();
		$data   = [];

		foreach ( $fields as $field ) {
			switch ( $field ) {
				case 'attribute_id':
					$data['attribute_id'] = $attribute->attribute_id;
					break;

				case 'attribute_name':
					$data['attribute_name'] = $attribute->attribute_name;
					break;

				case 'attribute_label':
					$data['attribute_label'] = $attribute->attribute_label;
					break;

				case 'attribute_type':
					$data['attribute_type'] = $attribute->attribute_type;
					break;

				case 'attribute_orderby':
					$data['attribute_orderby'] = $attribute->attribute_orderby;
					break;

				case 'attribute_public':
					$data['attribute_public'] = $attribute->attribute_public;
					break;

				default:
					// Allow custom fields via filter
					$data[ $field ] = apply_filters( 'aie_attribute_export_field_value', '', $field, $attribute, $options );
					break;
			}
		}

		return apply_filters( 'aie_attribute_export_data', $data, $attribute, $options );
	}

	/**
	 * Validate export options
	 *
	 * @param array $options Export options
	 * @return true|\WP_Error
	 */
	public function validate_options( $options ) {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new \WP_Error(
				'woocommerce_not_active',
				__( 'WooCommerce is not active', 'wp-advanced-import-export' )
			);
		}

		return true;
	}
}

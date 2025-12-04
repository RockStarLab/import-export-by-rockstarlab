<?php
/**
 * WooCommerce Attribute Exporter
 *
 * Handles exporting WooCommerce product attributes
 *
 * @package WP_AIE\Model\Export
 */

namespace WP_AIE\Model\Export;

/**
 * WooCommerce Attribute Exporter Class
 *
 * Exports product attributes with support for:
 * - Global attributes
 * - Attribute terms
 * - Attribute properties (type, orderby, etc.)
 *
 * @package WP_AIE\Model\Export
 */
class Woo_Attribute_Exporter extends Abstract_Exporter {

	/**
	 * Get exporter name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'woo_attributes';
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
			'include_terms' => __( 'Include attribute terms', 'wp-advanced-import-export' ),
			'orderby'       => __( 'Order by field', 'wp-advanced-import-export' ),
			'order'         => __( 'Order direction (ASC or DESC)', 'wp-advanced-import-export' ),
		];
	}

	/**
	 * Get available fields for export
	 *
	 * @return array
	 */
	public function get_available_fields() {
		return [
			'attribute_id',
			'attribute_name',
			'attribute_label',
			'attribute_type',
			'attribute_orderby',
			'attribute_public',
			'terms',
		];
	}

	/**
	 * Get default export fields
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
			'terms',
		];
	}

	/**
	 * Get total count of items
	 *
	 * @param array $options Optional. Export filters
	 * @return int
	 */
	public function get_count( $options = [] ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return 0;
		}

		global $wpdb;
		$count = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}woocommerce_attribute_taxonomies" );

		return (int) $count;
	}

	/**
	 * Get data based on export options
	 *
	 * @param array $options Export options
	 * @return array|WP_Error
	 */
	public function get_data( $options = [] ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new \WP_Error(
				'woocommerce_not_active',
				__( 'WooCommerce is not active', 'wp-advanced-import-export' )
			);
		}

		$this->log_info( 'Querying WooCommerce attributes' );

		global $wpdb;
		$attributes = $wpdb->get_results(
			"SELECT * FROM {$wpdb->prefix}woocommerce_attribute_taxonomies ORDER BY attribute_name"
		);

		if ( empty( $attributes ) ) {
			return [];
		}

		$data = [];
		foreach ( $attributes as $attribute ) {
			$data[] = $this->format_attribute( $attribute, $options );
		}

		return $data;
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

				case 'terms':
					$include_terms = $options['include_terms'] ?? true;
					if ( $include_terms ) {
						$data['terms'] = $this->get_attribute_terms( $attribute->attribute_name );
					}
					break;

				default:
					// Allow custom fields via filter
					$data[ $field ] = apply_filters( 'aie_woo_attribute_export_field_value', '', $field, $attribute, $options );
					break;
			}
		}

		return apply_filters( 'aie_woo_attribute_export_data', $data, $attribute, $options );
	}

	/**
	 * Get attribute terms
	 *
	 * @param string $attribute_name Attribute name
	 * @return array
	 */
	protected function get_attribute_terms( $attribute_name ) {
		$taxonomy = wc_attribute_taxonomy_name( $attribute_name );

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return [];
		}

		$terms = get_terms(
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
			]
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		$formatted_terms = [];
		foreach ( $terms as $term ) {
			$formatted_terms[] = [
				'term_id'     => $term->term_id,
				'name'        => $term->name,
				'slug'        => $term->slug,
				'description' => $term->description,
			];
		}

		return $formatted_terms;
	}

	/**
	 * Build query arguments from options
	 *
	 * @param array $options Export options
	 * @return array
	 */
	protected function build_query_args( $options ) {
		return $options;
	}

	/**
	 * Validate export options
	 *
	 * @param array $options Export options
	 * @return true|\WP_Error
	 */
	public function validate_options( $options ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new \WP_Error(
				'woocommerce_not_active',
				__( 'WooCommerce is not active', 'wp-advanced-import-export' )
			);
		}

		return true;
	}
}

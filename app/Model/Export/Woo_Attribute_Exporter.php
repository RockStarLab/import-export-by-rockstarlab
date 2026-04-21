<?php
/**
 * WooCommerce Attribute Exporter
 *
 * Handles exporting WooCommerce product attributes
 *
 * @package RockStarLab\ImportExport\Model\Export
 */

namespace RockStarLab\ImportExport\Model\Export;

defined( 'ABSPATH' ) || exit;

class Woo_Attribute_Exporter extends Abstract_Exporter {

	/**
	 * Get exporter name
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'WooCommerce Attributes', 'import-export-by-rockstarlab' );
	}

	/**
	 * Get exporter description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Export WooCommerce product attributes', 'import-export-by-rockstarlab' );
	}

	/**
	 * Get supported export filters
	 *
	 * @return array
	 */
	public function get_supported_filters() {
		return [
			'include_terms' => __( 'Include attribute terms', 'import-export-by-rockstarlab' ),
			'orderby'       => __( 'Order by field', 'import-export-by-rockstarlab' ),
			'order'         => __( 'Order direction (ASC or DESC)', 'import-export-by-rockstarlab' ),
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
			'term_count',
			'attribute_terms',
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
			'term_count',
			'attribute_terms',
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
		$attributes = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			"SELECT * FROM {$wpdb->prefix}woocommerce_attribute_taxonomies"
		);

		if ( empty( $attributes ) ) {
			return 0;
		}

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
	 * @return array|WP_Error
	 */
	public function get_data( $options = [] ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new \WP_Error(
				'woocommerce_not_active',
				__( 'WooCommerce is not active', 'import-export-by-rockstarlab' )
			);
		}

		$this->log_info( 'Querying WooCommerce attributes' );

		global $wpdb;
		$attributes = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			"SELECT * FROM {$wpdb->prefix}woocommerce_attribute_taxonomies ORDER BY attribute_name"
		);

		if ( empty( $attributes ) ) {
			return [];
		}

		// Apply filters
		if ( ! empty( $options['filters'] ) && is_array( $options['filters'] ) ) {
			$attributes = array_filter(
				$attributes,
				function ( $attribute ) use ( $options ) {
					return $this->passes_all_filters( $attribute, $options['filters'] );
				}
			);
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

		// Field aliases for backward compatibility
		$field_aliases = [
			'terms' => 'attribute_terms',
		];

		foreach ( $fields as $field ) {
			// Check if field has an alias
			$alias_field = $field_aliases[ $field ] ?? $field;

			switch ( $alias_field ) {
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

				case 'term_count':
					$taxonomy                = wc_attribute_taxonomy_name( $attribute->attribute_name );
					$data['term_count'] = taxonomy_exists( $taxonomy ) ? wp_count_terms( [ 'taxonomy' => $taxonomy ] ) : 0;
					break;

				case 'attribute_terms':
					$include_terms = $options['include_terms'] ?? true;
					if ( $include_terms ) {
						$data['attribute_terms'] = $this->get_attribute_terms( $attribute->attribute_name );
					}
					break;

				default:
					// Allow custom fields via filter
					$data[ $alias_field ] = apply_filters( 'rsl_ie_woo_attribute_export_field_value', '', $alias_field, $attribute, $options );
					break;
			}
		}

		return apply_filters( 'rsl_ie_woo_attribute_export_data', $data, $attribute, $options );
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
				'meta'        => $this->get_term_meta_portable( $term->term_id ),
			];
		}

		return $formatted_terms;
	}

	/**
	 * Get term meta in a portable format (attachment IDs → file:<filename>).
	 *
	 * @param int $term_id Term ID.
	 * @return array
	 */
	protected function get_term_meta_portable( $term_id ) {
		$term_id = (int) $term_id;
		if ( $term_id <= 0 ) {
			return [];
		}

		$raw = get_term_meta( $term_id );
		if ( empty( $raw ) || ! is_array( $raw ) ) {
			return [];
		}

		$out = [];
		foreach ( $raw as $key => $values ) {
			if ( ! is_array( $values ) ) {
				continue;
			}

			// Normalize "array of one" to scalar for readability.
			if ( 1 === count( $values ) ) {
				$out[ $key ] = $this->normalize_meta_value_for_export( $values[0] );
			} else {
				$out[ $key ] = array_map( [ $this, 'normalize_meta_value_for_export' ], $values );
			}
		}

		return $out;
	}

	/**
	 * Normalize meta values for export (unserialize, map attachment IDs).
	 *
	 * @param mixed $value Meta value.
	 * @return mixed
	 */
	protected function normalize_meta_value_for_export( $value ) {
		$value = maybe_unserialize( $value );

		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$value[ $k ] = $this->normalize_meta_value_for_export( $v );
			}
			return $value;
		}

		if ( is_object( $value ) ) {
			if ( $value instanceof \stdClass ) {
				return $this->normalize_meta_value_for_export( (array) $value );
			}
			return (string) $value;
		}

		// Attachment ID → file:<filename>
		if ( is_numeric( $value ) ) {
			$id = (int) $value;
			if ( $id > 0 && 'attachment' === get_post_type( $id ) ) {
				$url = wp_get_attachment_url( $id );
				if ( ! empty( $url ) ) {
					$path = wp_parse_url( $url, PHP_URL_PATH );
					$base = $path ? wp_basename( $path ) : wp_basename( $url );
					if ( ! empty( $base ) ) {
						return 'file:' . $base;
					}
				}

				$file = get_post_meta( $id, '_wp_attached_file', true );
				if ( ! empty( $file ) ) {
					return 'file:' . wp_basename( (string) $file );
				}
			}
		}

		return $value;
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
	 * Get attribute field value
	 *
	 * @param object $attribute  Attribute object
	 * @param string $field_name Field name
	 * @return mixed
	 */
	protected function get_attribute_field_value( $attribute, $field_name ) {
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
	 * Check if attribute passes all filters
	 *
	 * @param object $attribute Attribute object
	 * @param array  $filters   Filters array
	 * @return bool
	 */
	protected function passes_all_filters( $attribute, $filters ) {
		if ( empty( $filters ) ) {
			return true;
		}

		foreach ( $filters as $filter ) {
			$field_value = $this->get_attribute_field_value( $attribute, $filter['field'] );
			$condition   = $filter['condition'];
			$test_value  = $filter['value'] ?? '';

			if ( ! $this->check_condition( $field_value, $condition, $test_value ) ) {
				return false;
			}
		}

		return true;
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
	 * Validate export options
	 *
	 * @param array $options Export options
	 * @return true|\WP_Error
	 */
	public function validate_options( $options ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new \WP_Error(
				'woocommerce_not_active',
				__( 'WooCommerce is not active', 'import-export-by-rockstarlab' )
			);
		}

		return true;
	}
}

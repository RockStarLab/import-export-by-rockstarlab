<?php
/**
 * WooCommerce Coupon Exporter
 *
 * Handles exporting WooCommerce coupons
 *
 * @package WP_AIE\Model\Export
 */

namespace WP_AIE\Model\Export;

/**
 * Coupon Exporter Class
 *
 * Exports WooCommerce coupons with support for:
 * - All coupon types (percent, fixed_cart, fixed_product)
 * - Discount amounts and restrictions
 * - Usage limits and tracking
 * - Product/category restrictions
 * - Email restrictions
 * - Date restrictions
 *
 * @package WP_AIE\Model\Export
 */
class Coupon_Exporter extends Abstract_Exporter {

	/**
	 * Content type
	 *
	 * @var string
	 */
	protected $content_type = 'woo_coupon';

	/**
	 * Get exporter name
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'WooCommerce Coupons', 'wp-advanced-import-export' );
	}

	/**
	 * Get exporter description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Export WooCommerce discount coupons with all settings and restrictions', 'wp-advanced-import-export' );
	}

	/**
	 * Get supported export filters
	 *
	 * @return array
	 */
	public function get_supported_filters() {
		return [
			'code'           => __( 'Coupon code', 'wp-advanced-import-export' ),
			'discount_type'  => __( 'Discount type (percent, fixed_cart, fixed_product)', 'wp-advanced-import-export' ),
			'amount'         => __( 'Discount amount', 'wp-advanced-import-export' ),
			'date_expires'   => __( 'Expiration date', 'wp-advanced-import-export' ),
			'usage_count'    => __( 'Usage count', 'wp-advanced-import-export' ),
			'usage_limit'    => __( 'Usage limit', 'wp-advanced-import-export' ),
			'free_shipping'  => __( 'Free shipping enabled', 'wp-advanced-import-export' ),
			'minimum_amount' => __( 'Minimum spend amount', 'wp-advanced-import-export' ),
			'maximum_amount' => __( 'Maximum spend amount', 'wp-advanced-import-export' ),
		];
	}

	/**
	 * Get available fields for export
	 *
	 * @return array
	 */
	public function get_available_fields() {
		return [
			'coupon_id',
			'code',
			'discount_type',
			'amount',
			'description',
			'date_created',
			'date_modified',
			'date_expires',
			'usage_count',
			'usage_limit',
			'usage_limit_per_user',
			'limit_usage_to_x_items',
			'individual_use',
			'product_ids',
			'excluded_product_ids',
			'product_categories',
			'excluded_product_categories',
			'free_shipping',
			'exclude_sale_items',
			'minimum_amount',
			'maximum_amount',
			'email_restrictions',
			'used_by',
		];
	}

	/**
	 * Get default fields for coupon export
	 *
	 * @return array
	 */
	public function get_default_fields() {
		return [
			'coupon_id',
			'code',
			'discount_type',
			'amount',
			'description',
			'date_expires',
			'usage_count',
			'usage_limit',
			'usage_limit_per_user',
			'individual_use',
			'free_shipping',
			'minimum_amount',
			'maximum_amount',
		];
	}

	/**
	 * Get total count of coupons
	 *
	 * @param array $options Optional. Export filters
	 * @return int
	 */
	public function get_count( $options = [] ) {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WC_Coupon' ) ) {
			return 0;
		}

		$args = $this->build_query_args( $options );

		$coupon_posts = get_posts( $args );

		if ( empty( $coupon_posts ) ) {
			return 0;
		}

		// Apply custom filters that need to be checked manually
		$custom_filters = $options['filters'] ?? [];
		if ( empty( $custom_filters ) ) {
			return count( $coupon_posts );
		}

		$count = 0;
		foreach ( $coupon_posts as $post ) {
			$coupon = new \WC_Coupon( $post->ID );
			if ( ! $coupon->get_id() ) {
				continue;
			}

			// Check if coupon passes all filters
			$passes_filters = true;
			foreach ( $custom_filters as $filter ) {
				if ( empty( $filter['field'] ) || empty( $filter['condition'] ) ) {
					continue;
				}

				$field_name = $filter['field'];
				$condition  = $filter['condition'];
				$value      = $filter['value'] ?? '';

				// Get field value from coupon
				$field_value = $this->get_coupon_field_value( $coupon, $field_name );

				if ( ! $this->check_condition( $field_value, $condition, $value ) ) {
					$passes_filters = false;
					break;
				}
			}

			if ( $passes_filters ) {
				++$count;
			}
		}

		return $count;
	}

	/**
	 * Get data based on export options
	 *
	 * @param array $options Export options
	 * @return array|WP_Error
	 */
	public function get_data( $options = [] ) {
		// Check if WooCommerce is active
		if ( ! class_exists( 'WC_Coupon' ) ) {
			return new \WP_Error( 'woocommerce_inactive', __( 'WooCommerce is not active', 'wp-advanced-import-export' ) );
		}

		$args = $this->build_query_args( $options );

		$this->log_info( 'Querying coupons', $args );

		$coupon_posts = get_posts( $args );

		if ( empty( $coupon_posts ) ) {
			return [];
		}

		$this->log_info( 'Found ' . count( $coupon_posts ) . ' coupons' );

		$data   = [];
		$fields = $this->get_option( 'fields', $this->get_default_fields() );

		// Get custom filters that need to be applied manually
		$custom_filters = $options['filters'] ?? [];

		foreach ( $coupon_posts as $post ) {
			$coupon = new \WC_Coupon( $post->ID );
			if ( ! $coupon->get_id() ) {
				continue;
			}

			// Apply custom filters if any
			if ( ! empty( $custom_filters ) ) {
				$passes_filters = true;
				foreach ( $custom_filters as $filter ) {
					if ( empty( $filter['field'] ) || empty( $filter['condition'] ) ) {
						continue;
					}

					$field_name = $filter['field'];
					$condition  = $filter['condition'];
					$value      = $filter['value'] ?? '';

					// Get field value from coupon
					$field_value = $this->get_coupon_field_value( $coupon, $field_name );

					if ( ! $this->check_condition( $field_value, $condition, $value ) ) {
						$passes_filters = false;
						break;
					}
				}

				if ( ! $passes_filters ) {
					continue;
				}
			}

			// Prepare coupon data
			$item   = $this->prepare_coupon_data( $coupon, $fields );
			$data[] = $item;
		}

		return $data;
	}

	/**
	 * Build query arguments from options
	 *
	 * @param array $options Export options
	 * @return array Query arguments
	 */
	protected function build_query_args( $options ) {
		$args = [
			'post_type'      => 'shop_coupon',
			'posts_per_page' => $options['limit'] ?? -1,
			'offset'         => $options['offset'] ?? 0,
			'orderby'        => $options['orderby'] ?? 'date',
			'order'          => $options['order'] ?? 'DESC',
			'post_status'    => 'any',
		];

		return $args;
	}

	/**
	 * Get field value from WooCommerce coupon
	 *
	 * @param \WC_Coupon $coupon Coupon object
	 * @param string     $field_name Field name
	 * @return mixed Field value
	 */
	protected function get_coupon_field_value( $coupon, $field_name ) {
		// Map field aliases
		$field_aliases = [
			'coupon_id'       => 'id',
			'expires_date'    => 'date_expires',
			'discount_amount' => 'amount',
		];

		// Replace alias with actual field name
		if ( isset( $field_aliases[ $field_name ] ) ) {
			$field_name = $field_aliases[ $field_name ];
		}

		// Map common coupon fields to getter methods
		$field_map = [
			'id'                          => 'get_id',
			'code'                        => 'get_code',
			'amount'                      => 'get_amount',
			'discount_type'               => 'get_discount_type',
			'description'                 => 'get_description',
			'date_created'                => 'get_date_created',
			'date_modified'               => 'get_date_modified',
			'date_expires'                => 'get_date_expires',
			'usage_count'                 => 'get_usage_count',
			'usage_limit'                 => 'get_usage_limit',
			'usage_limit_per_user'        => 'get_usage_limit_per_user',
			'limit_usage_to_x_items'      => 'get_limit_usage_to_x_items',
			'individual_use'              => 'get_individual_use',
			'product_ids'                 => 'get_product_ids',
			'excluded_product_ids'        => 'get_excluded_product_ids',
			'product_categories'          => 'get_product_categories',
			'excluded_product_categories' => 'get_excluded_product_categories',
			'free_shipping'               => 'get_free_shipping',
			'exclude_sale_items'          => 'get_exclude_sale_items',
			'minimum_amount'              => 'get_minimum_amount',
			'maximum_amount'              => 'get_maximum_amount',
			'email_restrictions'          => 'get_email_restrictions',
			'used_by'                     => 'get_used_by',
		];

		if ( isset( $field_map[ $field_name ] ) ) {
			$method = $field_map[ $field_name ];
			if ( method_exists( $coupon, $method ) ) {
				$value = $coupon->$method();

				// Convert WC_DateTime to string
				if ( $value instanceof \WC_DateTime ) {
					return $value->format( 'Y-m-d H:i:s' );
				}

				// Convert boolean to string
				if ( is_bool( $value ) ) {
					return $value ? '1' : '0';
				}

				// Convert array to JSON
				if ( is_array( $value ) ) {
					return json_encode( $value );
				}

				return (string) $value;
			}
		}

		return '';
	}

	/**
	 * Prepare coupon data for export
	 *
	 * @param \WC_Coupon $coupon Coupon object
	 * @param array      $fields Fields to export
	 * @return array Prepared data
	 */
	protected function prepare_coupon_data( $coupon, $fields ) {
		$data = [];

		foreach ( $fields as $field ) {
			$data[ $field ] = $this->get_coupon_field_value( $coupon, $field );
		}

		return $data;
	}

	/**
	 * Check if a condition matches
	 *
	 * @param mixed  $field_value The value to test
	 * @param string $condition   The condition type
	 * @param mixed  $test_value  The value to test against
	 * @return bool True if condition matches
	 */
	protected function check_condition( $field_value, $condition, $test_value ) {
		// For date comparisons, extract only the date part (YYYY-MM-DD)
		$is_date_value   = false;
		$field_date_only = null;
		$test_date_only  = null;

		if ( is_string( $field_value ) && preg_match( '/^\d{4}-\d{2}-\d{2}/', $field_value ) ) {
			$is_date_value   = true;
			$field_date_only = substr( $field_value, 0, 10 ); // Get YYYY-MM-DD part
		}
		if ( is_string( $test_value ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $test_value ) ) {
			$test_date_only = $test_value;
		}

		// For date comparisons (greater/less/between), exclude empty values
		$is_date_comparison = in_array( $condition, [ 'greater', 'less', 'equals_or_greater', 'equals_or_less', 'between' ], true );
		if ( $is_date_comparison && $test_date_only && empty( $field_value ) ) {
			return false; // Empty dates shouldn't match numeric/date comparisons
		}

		switch ( $condition ) {
			case 'equals':
				// For dates, compare only date parts
				if ( $is_date_value && isset( $field_date_only ) && isset( $test_date_only ) ) {
					return $field_date_only === $test_date_only;
				}
				return $field_value == $test_value;

			case 'not_equals':
				// For dates, compare only date parts
				if ( $is_date_value && isset( $field_date_only ) && isset( $test_date_only ) ) {
					return $field_date_only !== $test_date_only;
				}
				return $field_value != $test_value;

			case 'contains':
				return stripos( $field_value, $test_value ) !== false;

			case 'not_contains':
				return stripos( $field_value, $test_value ) === false;

			case 'starts_with':
				return stripos( $field_value, $test_value ) === 0;

			case 'ends_with':
				return substr( strtolower( $field_value ), -strlen( $test_value ) ) === strtolower( $test_value );

			case 'greater':
				// For dates, compare only date parts
				if ( $is_date_value && isset( $field_date_only ) && isset( $test_date_only ) ) {
					return $field_date_only > $test_date_only;
				}
				return $field_value > $test_value;

			case 'less':
				// For dates, compare only date parts
				if ( $is_date_value && isset( $field_date_only ) && isset( $test_date_only ) ) {
					return $field_date_only < $test_date_only;
				}
				return $field_value < $test_value;

			case 'equals_or_greater':
				// For dates, compare only date parts
				if ( $is_date_value && isset( $field_date_only ) && isset( $test_date_only ) ) {
					return $field_date_only >= $test_date_only;
				}
				return $field_value >= $test_value;

			case 'equals_or_less':
				// For dates, compare only date parts
				if ( $is_date_value && isset( $field_date_only ) && isset( $test_date_only ) ) {
					return $field_date_only <= $test_date_only;
				}
				return $field_value <= $test_value;

			case 'between':
				$values = array_map( 'trim', explode( ',', $test_value ) );
				if ( count( $values ) === 2 ) {
					return $field_value >= $values[0] && $field_value <= $values[1];
				}
				return true;

			case 'in':
				$values = array_map( 'trim', explode( ',', $test_value ) );
				return in_array( $field_value, $values, true );

			case 'not_in':
				$values = array_map( 'trim', explode( ',', $test_value ) );
				return ! in_array( $field_value, $values, true );

			case 'is_empty':
				return empty( $field_value );

			case 'is_not_empty':
				return ! empty( $field_value );

			default:
				return true;
		}
	}
}

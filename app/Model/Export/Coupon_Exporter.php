<?php
/**
 * WooCommerce Coupon Exporter
 *
 * Handles exporting WooCommerce coupons
 *
 * @package RockStarLab\ImportExport\Model\Export
 */

namespace RockStarLab\ImportExport\Model\Export;

defined( 'ABSPATH' ) || exit;

class Coupon_Exporter extends Abstract_Exporter {
	/**
	 * Convert a product ID to a portable reference.
	 *
	 * @param int $product_id Product ID.
	 * @return string Portable reference (sku:..., slug:..., id:...).
	 */
	protected function format_product_ref( $product_id ) {
		$product_id = (int) $product_id;
		if ( $product_id <= 0 ) {
			return '';
		}

		if ( function_exists( 'wc_get_product' ) ) {
			$product = wc_get_product( $product_id );
			if ( $product ) {
				$sku = $product->get_sku();
				if ( ! empty( $sku ) ) {
					return 'sku:' . (string) $sku;
				}
			}
		}

		$slug = get_post_field( 'post_name', $product_id );
		if ( ! empty( $slug ) ) {
			return 'slug:' . (string) $slug;
		}

		return 'id:' . (string) $product_id;
	}

	/**
	 * Convert a product_cat term ID to a portable reference.
	 *
	 * @param int $term_id Term ID.
	 * @return string Portable reference (slug:..., id:...).
	 */
	protected function format_product_cat_ref( $term_id ) {
		$term_id = (int) $term_id;
		if ( $term_id <= 0 ) {
			return '';
		}

		$term = get_term( $term_id, 'product_cat' );
		if ( $term && ! is_wp_error( $term ) && ! empty( $term->slug ) ) {
			return 'slug:' . (string) $term->slug;
		}

		return 'id:' . (string) $term_id;
	}

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
		return __( 'WooCommerce Coupons', 'import-export-by-rockstarlab' );
	}

	/**
	 * Get exporter description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Export WooCommerce discount coupons with all settings and restrictions', 'import-export-by-rockstarlab' );
	}

	/**
	 * Get supported export filters
	 *
	 * @return array
	 */
	public function get_supported_filters() {
		return [
			'post_title'     => __( 'Coupon code', 'import-export-by-rockstarlab' ),
			'discount_type'  => __( 'Discount type (percent, fixed_cart, fixed_product)', 'import-export-by-rockstarlab' ),
			'coupon_amount'  => __( 'Discount amount', 'import-export-by-rockstarlab' ),
			'date_expires'   => __( 'Expiration date', 'import-export-by-rockstarlab' ),
			'usage_count'    => __( 'Usage count', 'import-export-by-rockstarlab' ),
			'usage_limit'    => __( 'Usage limit', 'import-export-by-rockstarlab' ),
			'free_shipping'  => __( 'Free shipping enabled', 'import-export-by-rockstarlab' ),
			'minimum_amount' => __( 'Minimum spend amount', 'import-export-by-rockstarlab' ),
			'maximum_amount' => __( 'Maximum spend amount', 'import-export-by-rockstarlab' ),
		];
	}

	/**
	 * Get available fields for export
	 *
	 * @return array
	 */
	public function get_available_fields() {
		return [
			'ID',
			'post_title',
			'post_excerpt',
			'post_status',
			'post_date',
			'post_modified',
			'discount_type',
			'coupon_amount',
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
			'allowed_emails',
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
			'ID',
			'post_title',
			'post_excerpt',
			'post_status',
			'discount_type',
			'coupon_amount',
			'free_shipping',
			'minimum_amount',
			'maximum_amount',
			'individual_use',
			'exclude_sale_items',
			'product_ids',
			'excluded_product_ids',
			'product_categories',
			'excluded_product_categories',
			'allowed_emails',
			'usage_count',
			'usage_limit',
			'usage_limit_per_user',
			'limit_usage_to_x_items',
			'date_expires',
			'post_date',
			'post_modified',
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
		
		// Remove offset and paged for count query - we want total count
		unset( $args['offset'] );
		unset( $args['paged'] );
		
		// Get all coupons for counting
		$args['posts_per_page'] = -1;
		$args['fields']         = 'ids';

		$coupon_ids = get_posts( $args );

		if ( empty( $coupon_ids ) ) {
			return 0;
		}

		// Apply custom filters that need to be checked manually
		$custom_filters = $options['filters'] ?? [];
		if ( empty( $custom_filters ) ) {
			return count( $coupon_ids );
		}

		// Group filters by field: OR within same field, AND across different fields
		$filters_by_field = [];
		foreach ( $custom_filters as $filter ) {
			if ( empty( $filter['field'] ) || empty( $filter['condition'] ) ) {
				continue;
			}
			$filters_by_field[ $filter['field'] ][] = $filter;
		}

		$count = 0;
		foreach ( $coupon_ids as $coupon_id ) {
			$coupon = new \WC_Coupon( $coupon_id );
			if ( ! $coupon->get_id() ) {
				continue;
			}

			// Check if coupon passes all filter groups
			$passes_filters = true;
			foreach ( $filters_by_field as $field_name => $field_filters ) {
				$field_value  = $this->get_coupon_field_value( $coupon, $field_name );
				$field_passes = false;
				foreach ( $field_filters as $filter ) {
					if ( $this->check_condition( $field_value, $filter['condition'], $filter['value'] ?? '' ) ) {
						$field_passes = true;
						break;
					}
				}
				if ( ! $field_passes ) {
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
			return new \WP_Error( 'woocommerce_inactive', __( 'WooCommerce is not active', 'import-export-by-rockstarlab' ) );
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

		// CRITICAL: Force include ID for Content Updater
		$force_include_id = $this->get_option( 'force_include_id', false );
		if ( $force_include_id && ! in_array( 'ID', $fields, true ) ) {
			// Prepend ID to fields array to ensure it's always included
			array_unshift( $fields, 'ID' );

			// IMPORTANT: Also update options['fields'] so select_fields() doesn't remove ID
			$this->options['fields'] = $fields;
		}

		// Get custom filters that need to be applied manually
		$custom_filters = $options['filters'] ?? [];

		// Group filters by field: OR within same field, AND across different fields
		$filters_by_field = [];
		foreach ( $custom_filters as $filter ) {
			if ( empty( $filter['field'] ) || empty( $filter['condition'] ) ) {
				continue;
			}
			$filters_by_field[ $filter['field'] ][] = $filter;
		}

		foreach ( $coupon_posts as $post ) {
			$coupon = new \WC_Coupon( $post->ID );
			if ( ! $coupon->get_id() ) {
				continue;
			}

			// Apply custom filters if any
			if ( ! empty( $filters_by_field ) ) {
				$passes_filters = true;
				foreach ( $filters_by_field as $field_name => $field_filters ) {
					$field_value  = $this->get_coupon_field_value( $coupon, $field_name );
					$field_passes = false;
					foreach ( $field_filters as $filter ) {
						if ( $this->check_condition( $field_value, $filter['condition'], $filter['value'] ?? '' ) ) {
							$field_passes = true;
							break;
						}
					}
					if ( ! $field_passes ) {
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
		// Map field aliases (JS uses different names than WC methods)
		$field_aliases = [
			'coupon_id'       => 'ID',
			'code'            => 'post_title',
			'description'     => 'post_excerpt',
			'expires_date'    => 'date_expires',
			'discount_amount' => 'coupon_amount',
			'amount'          => 'coupon_amount',
			'date_created'    => 'post_date',
			'date_modified'   => 'post_modified',
			'status'          => 'post_status',
			'email_restrictions' => 'allowed_emails',
		];

		// Replace alias with actual field name
		if ( isset( $field_aliases[ $field_name ] ) ) {
			$field_name = $field_aliases[ $field_name ];
		}

		// Map common coupon fields to getter methods
		$field_map = [
			'ID'                          => 'get_id',
			'post_title'                  => 'get_code',
			'post_excerpt'                => 'get_description',
			'post_status'                 => 'get_status',
			'post_date'                   => 'get_date_created',
			'post_modified'               => 'get_date_modified',
			'coupon_amount'               => 'get_amount',
			'discount_type'               => 'get_discount_type',
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
			'allowed_emails'              => 'get_email_restrictions',
			'used_by'                     => 'get_used_by',
		];

		if ( isset( $field_map[ $field_name ] ) ) {
			$method = $field_map[ $field_name ];
			if ( method_exists( $coupon, $method ) ) {
				$value = $coupon->$method();

				// Make product/category restrictions portable across sites (IDs differ).
				if ( is_array( $value ) ) {
					if ( in_array( $field_name, [ 'product_ids', 'excluded_product_ids' ], true ) ) {
						$value = array_values(
							array_filter(
								array_map(
									function ( $id ) {
										return $this->format_product_ref( $id );
									},
									$value
								)
							)
						);
					} elseif ( in_array( $field_name, [ 'product_categories', 'excluded_product_categories' ], true ) ) {
						$value = array_values(
							array_filter(
								array_map(
									function ( $id ) {
										return $this->format_product_cat_ref( $id );
									},
									$value
								)
							)
						);
					}
				}

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

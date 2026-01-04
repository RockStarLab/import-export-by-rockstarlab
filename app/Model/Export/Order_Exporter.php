<?php
/**
 * WooCommerce Order Exporter
 *
 * Handles exporting WooCommerce orders with HPOS support
 *
 * @package WP_AIE\Model\Export
 */

namespace WP_AIE\Model\Export;

/**
 * Order Exporter Class
 *
 * Exports WooCommerce orders with support for:
 * - HPOS (High-Performance Order Storage)
 * - Order meta data
 * - Billing and shipping information
 * - Order items and line items
 * - Order status filtering
 * - Date range filtering
 *
 * @package WP_AIE\Model\Export
 */
class Order_Exporter extends Abstract_Exporter {

	/**
	 * Get exporter name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'orders';
	}

	/**
	 * Get exporter description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Export WooCommerce orders', 'wp-advanced-import-export' );
	}

	/**
	 * Get supported export filters
	 *
	 * @return array
	 */
	public function get_supported_filters() {
		return [
			'status'         => __( 'Order status (completed, processing, pending, etc.)', 'wp-advanced-import-export' ),
			'date_created'   => __( 'Date created range', 'wp-advanced-import-export' ),
			'date_modified'  => __( 'Date modified range', 'wp-advanced-import-export' ),
			'customer_id'    => __( 'Customer user ID', 'wp-advanced-import-export' ),
			'billing_email'  => __( 'Billing email', 'wp-advanced-import-export' ),
			'payment_method' => __( 'Payment method', 'wp-advanced-import-export' ),
			'total'          => __( 'Order total', 'wp-advanced-import-export' ),
			'order_key'      => __( 'Order key', 'wp-advanced-import-export' ),
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
			'order_number',
			'order_key',
			'order_status',
			'currency',
			'order_date',
			'date_modified',
			'completed_date',
			'paid_date',
			'customer_id',
			'billing_first_name',
			'billing_last_name',
			'billing_company',
			'billing_address_1',
			'billing_address_2',
			'billing_city',
			'billing_state',
			'billing_postcode',
			'billing_country',
			'billing_email',
			'billing_phone',
			'shipping_first_name',
			'shipping_last_name',
			'shipping_company',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_city',
			'shipping_state',
			'shipping_postcode',
			'shipping_country',
			'payment_method',
			'payment_method_title',
			'transaction_id',
			'customer_ip_address',
			'customer_user_agent',
			'customer_note',
			'order_total',
			'order_subtotal',
			'order_tax',
			'order_shipping',
			'order_discount',
			'cart_tax',
			'shipping_tax',
			'total_tax',
			'order_items',
			'item_count',
			'shipping_method',
			'shipping_lines',
			'fee_lines',
			'coupon_lines',
			'order_notes',
			'order_meta',
		];
	}

	/**
	 * Get default export fields
	 *
	 * @return array
	 */
	public function get_default_fields() {
		return [
			// Core Order Fields
			'ID',
			'order_number',
			'order_status',
			'order_key',
			'currency',
			
			// Order Totals
			'order_total',
			'order_subtotal',
			'order_tax',
			'order_shipping',
			'order_discount',
			'cart_tax',
			'shipping_tax',
			'total_tax',
			
			// Customer Information
			'customer_id',
			'billing_email',
			'customer_note',
			
			// Billing Address
			'billing_first_name',
			'billing_last_name',
			'billing_company',
			'billing_address_1',
			'billing_address_2',
			'billing_city',
			'billing_state',
			'billing_postcode',
			'billing_country',
			'billing_phone',
			
			// Shipping Address
			'shipping_first_name',
			'shipping_last_name',
			'shipping_company',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_city',
			'shipping_state',
			'shipping_postcode',
			'shipping_country',
			
			// Order Items
			'order_items',
			'item_count',
			
			// Payment & Shipping
			'payment_method',
			'payment_method_title',
			'transaction_id',
			'shipping_method',
			
			// Dates
			'order_date',
			'date_modified',
			'completed_date',
			'paid_date',
			
			// Additional Order Data
			'customer_ip_address',
			'customer_user_agent',
			
			// Additional Lines
			'shipping_lines',
			'fee_lines',
			'coupon_lines',
			
			// Notes and Meta
			'order_notes',
			'order_meta',
		];
	}

	/**
	 * Get total count of orders
	 *
	 * @param array $options Optional. Export filters
	 * @return int
	 */
	public function get_count( $options = [] ) {
		// Check if WooCommerce is active
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return 0;
		}

		$query_args = $this->build_query_args( $options );
		
		// Remove offset and paged for count query - we want total count
		unset( $query_args['offset'] );
		unset( $query_args['paged'] );
		unset( $query_args['page'] );
		
		$query_args['return'] = 'ids';
		$query_args['limit']  = -1;

		$order_ids = wc_get_orders( $query_args );

		if ( empty( $order_ids ) ) {
			return 0;
		}

		// Apply custom filters that need to be checked manually
		$custom_filters = $options['filters'] ?? [];
		if ( empty( $custom_filters ) ) {
			return count( $order_ids );
		}

		$count = 0;
		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				continue;
			}

			// Check if order passes all filters
			$passes_filters = true;
			foreach ( $custom_filters as $filter ) {
				if ( empty( $filter['field'] ) || empty( $filter['condition'] ) ) {
					continue;
				}

				$field_name = $filter['field'];
				$condition  = $filter['condition'];
				$value      = $filter['value'] ?? '';

				// Get field value from order
				$field_value = $this->get_order_field_value( $order, $field_name );

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
		if ( ! function_exists( 'wc_get_orders' ) ) {
			$error = new \WP_Error( 'woocommerce_inactive', __( 'WooCommerce is not active', 'wp-advanced-import-export' ) );
			$this->log_error( 'WooCommerce is not active' );
			return $error;
		}

		$query_args = $this->build_query_args( $options );

		$this->log_info( 'Querying orders', $query_args );

		// Get order IDs first
		$query_args['return'] = 'ids';
		$order_ids            = wc_get_orders( $query_args );

		if ( empty( $order_ids ) ) {
			$this->log_warning( 'No order IDs found' );
			return [];
		}

		$this->log_info( 'Found ' . count( $order_ids ) . ' order IDs' );

		$data   = [];
		$fields = $this->get_option( 'fields', $this->get_default_fields() );

		// Merge with default fields to ensure all new fields are included
		// This helps when user has old export settings without new fields
		$default_fields = $this->get_default_fields();
		$fields = array_unique( array_merge( $fields, $default_fields ) );

		$this->log_info( 'Fields to export: ' . implode( ', ', $fields ) );

		// Get custom filters that need to be applied manually
		$custom_filters = $options['filters'] ?? [];

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				$this->log_warning( "Failed to load order #{$order_id}" );
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

					// Get field value from order
					$field_value = $this->get_order_field_value( $order, $field_name );

					if ( ! $this->check_condition( $field_value, $condition, $value ) ) {
						$passes_filters = false;
						break;
					}
				}

				if ( ! $passes_filters ) {
					continue;
				}
			}

			// Prepare order data
			$item   = $this->prepare_order_data( $order, $fields );
			$data[] = $item;

			$this->log_info( "Prepared order #{$order_id} data" );
		}

		$this->log_info( 'Total orders prepared: ' . count( $data ) );

		return $data;
	}

	/**
	 * Build WooCommerce order query arguments from options
	 *
	 * @param array $options Export options
	 * @return array Query arguments
	 */
	protected function build_query_args( $options ) {
		$args = [
			'limit'   => $options['limit'] ?? -1,
			'offset'  => $options['offset'] ?? 0,
			'orderby' => $options['orderby'] ?? 'date',
			'order'   => $options['order'] ?? 'DESC',
			'return'  => 'ids',
		];

		// Status filter
		if ( ! empty( $options['status'] ) ) {
			$args['status'] = $options['status'];
		}

		// Customer filter
		if ( ! empty( $options['customer_id'] ) ) {
			$args['customer_id'] = $options['customer_id'];
		}

		// Billing email filter
		if ( ! empty( $options['billing_email'] ) ) {
			$args['billing_email'] = $options['billing_email'];
		}

		// Payment method filter
		if ( ! empty( $options['payment_method'] ) ) {
			$args['payment_method'] = $options['payment_method'];
		}

		// Date created filter
		if ( ! empty( $options['date_created'] ) ) {
			$args['date_created'] = $options['date_created'];
		}

		// Date modified filter
		if ( ! empty( $options['date_modified'] ) ) {
			$args['date_modified'] = $options['date_modified'];
		}

		// Process dynamic filters
		if ( ! empty( $options['filters'] ) && is_array( $options['filters'] ) ) {
			$this->apply_dynamic_filters( $args, $options['filters'] );
		}

		return $args;
	}

	/**
	 * Apply dynamic filters to query args
	 *
	 * @param array $args    Query arguments (by reference)
	 * @param array $filters Dynamic filters
	 */
	protected function apply_dynamic_filters( &$args, $filters ) {
		foreach ( $filters as $filter ) {
			if ( empty( $filter['field'] ) || empty( $filter['condition'] ) ) {
				continue;
			}

			$field     = $filter['field'];
			$condition = $filter['condition'];
			$value     = $filter['value'] ?? '';

			// Map fields to WC order query args
			switch ( $field ) {
				case 'status':
				case 'order_status':
					if ( $condition === 'equals' ) {
						$args['status'] = str_replace( 'wc-', '', $value );
					} elseif ( $condition === 'in' ) {
						$statuses       = array_map( 'trim', explode( ',', $value ) );
						$statuses       = array_map(
							function ( $s ) {
								return str_replace( 'wc-', '', $s );
							},
							$statuses
						);
						$args['status'] = $statuses;
					}
					break;

				case 'customer_id':
					if ( $condition === 'equals' ) {
						$args['customer_id'] = absint( $value );
					}
					break;

				case 'billing_email':
					if ( $condition === 'equals' || $condition === 'contains' ) {
						$args['billing_email'] = $value;
					}
					break;

				case 'payment_method':
					if ( $condition === 'equals' ) {
						$args['payment_method'] = $value;
					}
					break;

				// For other fields, they will be filtered manually in get_data()
			}
		}
	}

	/**
	 * Get field value from WooCommerce order
	 *
	 * @param \WC_Order $order Order object
	 * @param string    $field_name Field name
	 * @return mixed Field value
	 */
	protected function get_order_field_value( $order, $field_name ) {
		// Store original field name for meta lookup
		$original_field = $field_name;

		// Map common order fields to getter methods
		$field_map = [
			'ID'                   => 'get_id',
			'order_number'         => 'get_order_number',
			'order_key'            => 'get_order_key',
			'order_status'         => 'get_status',
			'currency'             => 'get_currency',
			'order_date'           => 'get_date_created',
			'date_modified'        => 'get_date_modified',
			'completed_date'       => 'get_date_completed',
			'paid_date'            => 'get_date_paid',
			'customer_id'          => 'get_customer_id',
			'billing_first_name'   => 'get_billing_first_name',
			'billing_last_name'    => 'get_billing_last_name',
			'billing_company'      => 'get_billing_company',
			'billing_address_1'    => 'get_billing_address_1',
			'billing_address_2'    => 'get_billing_address_2',
			'billing_city'         => 'get_billing_city',
			'billing_state'        => 'get_billing_state',
			'billing_postcode'     => 'get_billing_postcode',
			'billing_country'      => 'get_billing_country',
			'billing_email'        => 'get_billing_email',
			'billing_phone'        => 'get_billing_phone',
			'shipping_first_name'  => 'get_shipping_first_name',
			'shipping_last_name'   => 'get_shipping_last_name',
			'shipping_company'     => 'get_shipping_company',
			'shipping_address_1'   => 'get_shipping_address_1',
			'shipping_address_2'   => 'get_shipping_address_2',
			'shipping_city'        => 'get_shipping_city',
			'shipping_state'       => 'get_shipping_state',
			'shipping_postcode'    => 'get_shipping_postcode',
			'shipping_country'     => 'get_shipping_country',
			'payment_method'       => 'get_payment_method',
			'payment_method_title' => 'get_payment_method_title',
			'transaction_id'       => 'get_transaction_id',
			'customer_ip_address'  => 'get_customer_ip_address',
			'customer_user_agent'  => 'get_customer_user_agent',
			'customer_note'        => 'get_customer_note',
			'order_total'          => 'get_total',
			'order_subtotal'       => 'get_subtotal',
			'order_tax'            => 'get_total_tax',
			'order_shipping'       => 'get_shipping_total',
			'order_discount'       => 'get_discount_total',
			'cart_tax'             => 'get_cart_tax',
			'shipping_tax'         => 'get_shipping_tax',
			'total_tax'            => 'get_total_tax',
		];

	if ( isset( $field_map[ $field_name ] ) ) {
		$method = $field_map[ $field_name ];
		$value  = $order->$method();

		// Convert DateTime objects to strings
		if ( $value instanceof \WC_DateTime ) {
			return $value->format( 'Y-m-d H:i:s' );
		}

		// Handle order_status - remove 'wc-' prefix if present
		if ( $field_name === 'order_status' && ! empty( $value ) ) {
			return str_replace( 'wc-', '', $value );
		}

		// Return empty string for null values (e.g., completed_date, paid_date when not set)
		if ( $value === null ) {
			return '';
		}

		return $value;
	}

		// Special fields that need custom handling
		if ( $field_name === 'item_count' ) {
			return $order->get_item_count();
		}

		if ( $field_name === 'shipping_method' ) {
			$shipping_methods = [];
			foreach ( $order->get_shipping_methods() as $shipping_item ) {
				$shipping_methods[] = $shipping_item->get_method_title();
			}
			return ! empty( $shipping_methods ) ? implode( ', ', $shipping_methods ) : '';
		}

		// Try to get as meta
		return $order->get_meta( $original_field );
	}

	/**
	 * Prepare order data for export
	 *
	 * @param \WC_Order $order Order object
	 * @param array     $fields Fields to export
	 * @return array Order data
	 */
	protected function prepare_order_data( $order, $fields ) {
		$data = [];

		$this->log_info( "Preparing data for order #{$order->get_id()}" );

		foreach ( $fields as $field ) {
			$field_name = is_array( $field ) ? ( $field['name'] ?? '' ) : $field;

			if ( empty( $field_name ) ) {
				continue;
			}

			// Handle field aliases
			$field_aliases = [
				'line_items' => 'order_items',
			];

			$export_field_name = $field_aliases[ $field_name ] ?? $field_name;

			try {
				switch ( $field_name ) {
					case 'order_items':
					case 'line_items':
						$items                 = $this->get_line_items( $order );
						$data['order_items']   = is_array( $items ) ? wp_json_encode( $items ) : $items;
						break;

					case 'shipping_lines':
						$lines                   = $this->get_shipping_lines( $order );
						$data['shipping_lines']  = is_array( $lines ) ? wp_json_encode( $lines ) : $lines;
						break;

					case 'fee_lines':
						$lines               = $this->get_fee_lines( $order );
						$data['fee_lines']   = is_array( $lines ) ? wp_json_encode( $lines ) : $lines;
						break;

					case 'coupon_lines':
						$lines                 = $this->get_coupon_lines( $order );
						$data['coupon_lines']  = is_array( $lines ) ? wp_json_encode( $lines ) : $lines;
						break;

					case 'order_notes':
						$notes                = $this->get_order_notes( $order );
						$data['order_notes']  = is_array( $notes ) ? wp_json_encode( $notes ) : $notes;
						break;

					case 'order_meta':
						$meta                = $this->get_order_meta( $order );
						$data['order_meta']  = is_array( $meta ) ? wp_json_encode( $meta ) : $meta;
						break;				default:
					$value = $this->get_order_field_value( $order, $field_name );

					// Convert DateTime objects to strings
					if ( $value instanceof \WC_DateTime ) {
						$value = $value->format( 'Y-m-d H:i:s' );
					}

					// Convert null to empty string
					if ( $value === null ) {
						$value = '';
					}

					// Convert arrays to JSON
					if ( is_array( $value ) ) {
						$value = wp_json_encode( $value );
					}

						$data[ $export_field_name ] = $value;
						break;
				}
			} catch ( \Exception $e ) {
				$this->log_error( "Error preparing field {$field_name} for order #{$order->get_id()}: " . $e->getMessage() );
				$data[ $export_field_name ] = '';
			}
		}

		return $data;
	}

	/**
	 * Get order line items
	 *
	 * @param \WC_Order $order Order object
	 * @return array Line items
	 */
	protected function get_line_items( $order ) {
		$items = [];

		foreach ( $order->get_items() as $item_id => $item ) {
			$product = $item->get_product();

			$items[] = [
				'id'           => $item_id,
				'name'         => $item->get_name(),
				'product_id'   => $item->get_product_id(),
				'variation_id' => $item->get_variation_id(),
				'quantity'     => $item->get_quantity(),
				'subtotal'     => $item->get_subtotal(),
				'total'        => $item->get_total(),
				'tax'          => $item->get_total_tax(),
				'sku'          => $product ? $product->get_sku() : '',
				'meta_data'    => $this->get_item_meta_data( $item ),
			];
		}

		return $items;
	}

	/**
	 * Get order shipping lines
	 *
	 * @param \WC_Order $order Order object
	 * @return array Shipping lines
	 */
	protected function get_shipping_lines( $order ) {
		$items = [];

		foreach ( $order->get_shipping_methods() as $item_id => $item ) {
			$items[] = [
				'id'           => $item_id,
				'method_id'    => $item->get_method_id(),
				'method_title' => $item->get_method_title(),
				'total'        => $item->get_total(),
				'tax'          => $item->get_total_tax(),
				'meta_data'    => $this->get_item_meta_data( $item ),
			];
		}

		return $items;
	}

	/**
	 * Get order fee lines
	 *
	 * @param \WC_Order $order Order object
	 * @return array Fee lines
	 */
	protected function get_fee_lines( $order ) {
		$items = [];

		foreach ( $order->get_fees() as $item_id => $item ) {
			$items[] = [
				'id'        => $item_id,
				'name'      => $item->get_name(),
				'total'     => $item->get_total(),
				'tax'       => $item->get_total_tax(),
				'tax_class' => $item->get_tax_class(),
				'meta_data' => $this->get_item_meta_data( $item ),
			];
		}

		return $items;
	}

	/**
	 * Get order coupon lines
	 *
	 * @param \WC_Order $order Order object
	 * @return array Coupon lines
	 */
	protected function get_coupon_lines( $order ) {
		$items = [];

		foreach ( $order->get_coupons() as $item_id => $item ) {
			$items[] = [
				'id'           => $item_id,
				'code'         => $item->get_code(),
				'discount'     => $item->get_discount(),
				'discount_tax' => $item->get_discount_tax(),
				'meta_data'    => $this->get_item_meta_data( $item ),
			];
		}

		return $items;
	}

	/**
	 * Get order notes
	 *
	 * @param \WC_Order $order Order object
	 * @return array Order notes
	 */
	protected function get_order_notes( $order ) {
		$notes      = [];
		$order_notes = wc_get_order_notes(
			[
				'order_id' => $order->get_id(),
				'orderby'  => 'date_created',
				'order'    => 'ASC',
			]
		);

		foreach ( $order_notes as $note ) {
			$notes[] = [
				'id'            => $note->id,
				'date_created'  => $note->date_created,
				'content'       => $note->content,
				'customer_note' => $note->customer_note,
				'added_by'      => $note->added_by,
			];
		}

		return $notes;
	}

	/**
	 * Get order meta data
	 *
	 * @param \WC_Order $order Order object
	 * @return array Meta data
	 */
	protected function get_order_meta( $order ) {
		$meta_data = [];

		foreach ( $order->get_meta_data() as $meta ) {
			// Skip internal meta
			if ( '_' === substr( $meta->key, 0, 1 ) ) {
				continue;
			}

			$meta_data[ $meta->key ] = $meta->value;
		}

		return $meta_data;
	}

	/**
	 * Get item meta data
	 *
	 * @param \WC_Order_Item $item Order item object
	 * @return array Meta data
	 */
	protected function get_item_meta_data( $item ) {
		$meta_data = [];

		foreach ( $item->get_meta_data() as $meta ) {
			// Skip internal meta
			if ( '_' === substr( $meta->key, 0, 1 ) ) {
				continue;
			}

			$meta_data[ $meta->key ] = $meta->value;
		}

		return $meta_data;
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

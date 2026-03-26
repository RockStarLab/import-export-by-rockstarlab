<?php
/**
 * WooCommerce Order Importer
 *
 * Handles importing WooCommerce orders with all settings
 *
 * IMPORT BEHAVIOR:
 * ----------------
 * 
 * Orders:
 * - If order with same order_number exists, it will be UPDATED (if update_existing = true)
 * - If order doesn't exist, it will be CREATED
 * - Order number is used as unique identifier
 * - Preserves relationships with products and customers
 * 
 * DUPLICATE PREVENTION:
 * ---------------------
 * 
 * - Orders are checked by order_number field
 * - If order exists and update_existing=false, it will be skipped
 * - If order exists and update_existing=true, it will be updated
 * - Uses WC_Order API to maintain data integrity
 * 
 * DATA FORMATS:
 * -------------
 * 
 * Order Items (order_items):
 * - JSON format: [{"id":1,"name":"Product","product_id":123,"quantity":2,"total":"50.00"}]
 * 
 * Order Notes (order_notes):
 * - JSON format: [{"content":"Note text","customer_note":true}]
 * 
 * Boolean fields:
 * - Accepts: 1, 0, true, false, yes, no
 * 
 * Date fields (order_date, completed_date, paid_date):
 * - Format: "YYYY-MM-DD HH:MM:SS" or "YYYY-MM-DD"
 * 
 * RELATIONSHIPS:
 * --------------
 * 
 * Customer ID:
 * - If customer_id is provided, order will be assigned to that user
 * - If customer_id is 0 or empty, guest checkout will be used
 * - Customer must exist in database
 * 
 * Product IDs:
 * - Products in order_items must exist in database
 * - Product SKUs can be used for matching
 * - Variation IDs must be valid if specified
 * 
 * BEST PRACTICES:
 * ---------------
 * 
 * When re-importing exported orders:
 * - Set update_existing = true to update existing orders
 * - Order items, notes, and meta will be preserved or updated
 * - Customer and product relationships maintained
 * 
 * When importing new orders:
 * - Ensure customer_id exists in database
 * - Ensure all product_ids and variation_ids exist
 * - Provide complete billing and shipping addresses
 *
 * @package WP_AIE\Model\Import
 */

namespace WP_AIE\Model\Import;

defined( 'ABSPATH' ) || exit;

class Woo_Order_Importer extends Abstract_Importer {

	/**
	 * Get importer name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'woo_order';
	}

	/**
	 * Get importer description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Import WooCommerce orders with items, customer data, and complete order information', 'wp-advanced-import-export' );
	}

	/**
	 * Get required fields for import
	 *
	 * @return array
	 */
	public function get_required_fields() {
		return [ 'order_items' ];
	}

	/**
	 * Get optional fields for import
	 *
	 * @return array
	 */
	public function get_optional_fields() {
		return [
			'ID',
			'order_number',
			'order_status',
			'order_key',
			'currency',
			'order_total',
			'order_subtotal',
			'order_tax',
			'order_shipping',
			'order_discount',
			'customer_id',
			'billing_email',
			'customer_note',
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
			'shipping_first_name',
			'shipping_last_name',
			'shipping_company',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_city',
			'shipping_state',
			'shipping_postcode',
			'shipping_country',
			'item_count',
			'payment_method',
			'payment_method_title',
			'transaction_id',
			'shipping_method',
			'order_date',
			'date_modified',
			'completed_date',
			'paid_date',
			'customer_ip_address',
			'customer_user_agent',
			'cart_tax',
			'shipping_tax',
			'total_tax',
			'shipping_lines',
			'fee_lines',
			'coupon_lines',
			'order_notes',
			'order_meta',
		];
	}

	/**
	 * Get supported import options
	 *
	 * @return array
	 */
	public function get_supported_options() {
		return [
			'duplicate_mode'  => 'How to handle duplicates: skip, update, create',
			'duplicate_check' => 'Field to check for duplicates: order_number, ID',
			'order_status'    => 'Default order status if not specified: pending, processing, completed',
			'send_emails'     => 'Whether to send order emails: yes, no',
			'update_stock'    => 'Whether to update stock levels: yes, no',
		];
	}

	/**
	 * Get available fields for import
	 *
	 * @return array
	 */
	public function get_available_fields() {
		return [
			// Core Order Fields
			'ID'                    => [
				'label'       => __( 'Order ID', 'wp-advanced-import-export' ),
				'description' => __( 'Existing order ID (optional, for updates)', 'wp-advanced-import-export' ),
			],
			'order_number'          => [
				'label'       => __( 'Order Number', 'wp-advanced-import-export' ),
				'description' => __( 'Unique order number (used for duplicate detection)', 'wp-advanced-import-export' ),
			],
			'order_status'          => [
				'label'       => __( 'Order Status', 'wp-advanced-import-export' ),
				'description' => __( 'Status: pending, processing, on-hold, completed, cancelled, refunded, failed', 'wp-advanced-import-export' ),
			],
			'order_key'             => [
				'label'       => __( 'Order Key', 'wp-advanced-import-export' ),
				'description' => __( 'Unique order key', 'wp-advanced-import-export' ),
			],
			'currency'              => [
				'label'       => __( 'Currency', 'wp-advanced-import-export' ),
				'description' => __( 'Currency code (USD, EUR, etc.)', 'wp-advanced-import-export' ),
			],

			// Order Totals
			'order_total'           => [
				'label'       => __( 'Order Total', 'wp-advanced-import-export' ),
				'description' => __( 'Total order amount', 'wp-advanced-import-export' ),
			],
			'order_subtotal'        => [
				'label'       => __( 'Subtotal', 'wp-advanced-import-export' ),
				'description' => __( 'Order subtotal before tax and shipping', 'wp-advanced-import-export' ),
			],
			'order_tax'             => [
				'label'       => __( 'Tax', 'wp-advanced-import-export' ),
				'description' => __( 'Total tax amount', 'wp-advanced-import-export' ),
			],
			'order_shipping'        => [
				'label'       => __( 'Shipping', 'wp-advanced-import-export' ),
				'description' => __( 'Shipping cost', 'wp-advanced-import-export' ),
			],
			'order_discount'        => [
				'label'       => __( 'Discount', 'wp-advanced-import-export' ),
				'description' => __( 'Discount amount', 'wp-advanced-import-export' ),
			],

			// Customer Information
			'customer_id'           => [
				'label'       => __( 'Customer ID', 'wp-advanced-import-export' ),
				'description' => __( 'WordPress user ID (0 for guest)', 'wp-advanced-import-export' ),
			],
			'billing_email'         => [
				'label'       => __( 'Billing Email', 'wp-advanced-import-export' ),
				'description' => __( 'Customer email address', 'wp-advanced-import-export' ),
			],
			'customer_note'         => [
				'label'       => __( 'Customer Note', 'wp-advanced-import-export' ),
				'description' => __( 'Note from customer', 'wp-advanced-import-export' ),
			],

			// Billing Address
			'billing_first_name'    => [
				'label'       => __( 'Billing First Name', 'wp-advanced-import-export' ),
				'description' => __( 'First name', 'wp-advanced-import-export' ),
			],
			'billing_last_name'     => [
				'label'       => __( 'Billing Last Name', 'wp-advanced-import-export' ),
				'description' => __( 'Last name', 'wp-advanced-import-export' ),
			],
			'billing_company'       => [
				'label'       => __( 'Billing Company', 'wp-advanced-import-export' ),
				'description' => __( 'Company name', 'wp-advanced-import-export' ),
			],
			'billing_address_1'     => [
				'label'       => __( 'Billing Address 1', 'wp-advanced-import-export' ),
				'description' => __( 'Street address', 'wp-advanced-import-export' ),
			],
			'billing_address_2'     => [
				'label'       => __( 'Billing Address 2', 'wp-advanced-import-export' ),
				'description' => __( 'Apartment, suite, etc.', 'wp-advanced-import-export' ),
			],
			'billing_city'          => [
				'label'       => __( 'Billing City', 'wp-advanced-import-export' ),
				'description' => __( 'City', 'wp-advanced-import-export' ),
			],
			'billing_state'         => [
				'label'       => __( 'Billing State', 'wp-advanced-import-export' ),
				'description' => __( 'State or province code', 'wp-advanced-import-export' ),
			],
			'billing_postcode'      => [
				'label'       => __( 'Billing Postcode', 'wp-advanced-import-export' ),
				'description' => __( 'Postal code', 'wp-advanced-import-export' ),
			],
			'billing_country'       => [
				'label'       => __( 'Billing Country', 'wp-advanced-import-export' ),
				'description' => __( 'Country code (US, GB, etc.)', 'wp-advanced-import-export' ),
			],
			'billing_phone'         => [
				'label'       => __( 'Billing Phone', 'wp-advanced-import-export' ),
				'description' => __( 'Phone number', 'wp-advanced-import-export' ),
			],

			// Shipping Address
			'shipping_first_name'   => [
				'label'       => __( 'Shipping First Name', 'wp-advanced-import-export' ),
				'description' => __( 'First name', 'wp-advanced-import-export' ),
			],
			'shipping_last_name'    => [
				'label'       => __( 'Shipping Last Name', 'wp-advanced-import-export' ),
				'description' => __( 'Last name', 'wp-advanced-import-export' ),
			],
			'shipping_company'      => [
				'label'       => __( 'Shipping Company', 'wp-advanced-import-export' ),
				'description' => __( 'Company name', 'wp-advanced-import-export' ),
			],
			'shipping_address_1'    => [
				'label'       => __( 'Shipping Address 1', 'wp-advanced-import-export' ),
				'description' => __( 'Street address', 'wp-advanced-import-export' ),
			],
			'shipping_address_2'    => [
				'label'       => __( 'Shipping Address 2', 'wp-advanced-import-export' ),
				'description' => __( 'Apartment, suite, etc.', 'wp-advanced-import-export' ),
			],
			'shipping_city'         => [
				'label'       => __( 'Shipping City', 'wp-advanced-import-export' ),
				'description' => __( 'City', 'wp-advanced-import-export' ),
			],
			'shipping_state'        => [
				'label'       => __( 'Shipping State', 'wp-advanced-import-export' ),
				'description' => __( 'State or province code', 'wp-advanced-import-export' ),
			],
			'shipping_postcode'     => [
				'label'       => __( 'Shipping Postcode', 'wp-advanced-import-export' ),
				'description' => __( 'Postal code', 'wp-advanced-import-export' ),
			],
			'shipping_country'      => [
				'label'       => __( 'Shipping Country', 'wp-advanced-import-export' ),
				'description' => __( 'Country code (US, GB, etc.)', 'wp-advanced-import-export' ),
			],

			// Order Items
			'order_items'           => [
				'label'       => __( 'Order Items', 'wp-advanced-import-export' ),
				'description' => __( 'JSON array of order items with product details', 'wp-advanced-import-export' ),
				'required'    => true,
			],
			'item_count'            => [
				'label'       => __( 'Item Count', 'wp-advanced-import-export' ),
				'description' => __( 'Total number of items', 'wp-advanced-import-export' ),
			],

			// Payment & Shipping
			'payment_method'        => [
				'label'       => __( 'Payment Method', 'wp-advanced-import-export' ),
				'description' => __( 'Payment method ID', 'wp-advanced-import-export' ),
			],
			'payment_method_title'  => [
				'label'       => __( 'Payment Method Title', 'wp-advanced-import-export' ),
				'description' => __( 'Payment method display name', 'wp-advanced-import-export' ),
			],
			'transaction_id'        => [
				'label'       => __( 'Transaction ID', 'wp-advanced-import-export' ),
				'description' => __( 'Payment transaction ID', 'wp-advanced-import-export' ),
			],
			'shipping_method'       => [
				'label'       => __( 'Shipping Method', 'wp-advanced-import-export' ),
				'description' => __( 'Shipping method used', 'wp-advanced-import-export' ),
			],

			// Dates
			'order_date'            => [
				'label'       => __( 'Order Date', 'wp-advanced-import-export' ),
				'description' => __( 'Date order was created (YYYY-MM-DD HH:MM:SS)', 'wp-advanced-import-export' ),
			],
			'date_modified'         => [
				'label'       => __( 'Date Modified', 'wp-advanced-import-export' ),
				'description' => __( 'Date order was last modified', 'wp-advanced-import-export' ),
			],
			'completed_date'        => [
				'label'       => __( 'Completed Date', 'wp-advanced-import-export' ),
				'description' => __( 'Date order was completed', 'wp-advanced-import-export' ),
			],
			'paid_date'             => [
				'label'       => __( 'Paid Date', 'wp-advanced-import-export' ),
				'description' => __( 'Date payment was received', 'wp-advanced-import-export' ),
			],

			// Additional Order Data
			'customer_ip_address'   => [
				'label'       => __( 'Customer IP Address', 'wp-advanced-import-export' ),
				'description' => __( 'IP address of customer', 'wp-advanced-import-export' ),
			],
			'customer_user_agent'   => [
				'label'       => __( 'Customer User Agent', 'wp-advanced-import-export' ),
				'description' => __( 'Browser user agent string', 'wp-advanced-import-export' ),
			],
			'cart_tax'              => [
				'label'       => __( 'Cart Tax', 'wp-advanced-import-export' ),
				'description' => __( 'Tax amount for cart items', 'wp-advanced-import-export' ),
			],
			'shipping_tax'          => [
				'label'       => __( 'Shipping Tax', 'wp-advanced-import-export' ),
				'description' => __( 'Tax amount for shipping', 'wp-advanced-import-export' ),
			],
			'total_tax'             => [
				'label'       => __( 'Total Tax', 'wp-advanced-import-export' ),
				'description' => __( 'Total tax amount', 'wp-advanced-import-export' ),
			],

			// Additional Lines
			'shipping_lines'        => [
				'label'       => __( 'Shipping Lines', 'wp-advanced-import-export' ),
				'description' => __( 'JSON array of shipping line items', 'wp-advanced-import-export' ),
			],
			'fee_lines'             => [
				'label'       => __( 'Fee Lines', 'wp-advanced-import-export' ),
				'description' => __( 'JSON array of fee line items', 'wp-advanced-import-export' ),
			],
			'coupon_lines'          => [
				'label'       => __( 'Coupon Lines', 'wp-advanced-import-export' ),
				'description' => __( 'JSON array of applied coupons', 'wp-advanced-import-export' ),
			],

			// Notes and Meta
			'order_notes'           => [
				'label'       => __( 'Order Notes', 'wp-advanced-import-export' ),
				'description' => __( 'JSON array of order notes', 'wp-advanced-import-export' ),
			],
			'order_meta'            => [
				'label'       => __( 'Order Meta', 'wp-advanced-import-export' ),
				'description' => __( 'JSON array of custom order metadata', 'wp-advanced-import-export' ),
			],
		];
	}

	/**
	 * Get default import options
	 *
	 * @return array
	 */
	protected function get_default_options() {
		return [
			'duplicate_mode'       => 'update',  // skip, update, or create
			'duplicate_check'      => 'ID',      // ID or order_number
			'send_notifications'   => false,     // Don't send emails during import
			'stock_management'     => false,     // Don't update stock on import
			'preserve_order_dates' => true,      // Keep original order dates
			'calculate_totals'     => false,     // Use imported totals, don't recalculate
		];
	}

	/**
	 * Validate import data
	 *
	 * @param array $data Data to validate
	 * @return bool|WP_Error
	 */
	public function validate( $data ) {
		if ( empty( $data ) ) {
			return new \WP_Error( 'empty_data', __( 'No data provided for import', 'wp-advanced-import-export' ) );
		}

		if ( ! function_exists( 'WC' ) || ! class_exists( 'WooCommerce' ) ) {
			return new \WP_Error( 'woocommerce_not_active', __( 'WooCommerce is not active', 'wp-advanced-import-export' ) );
		}

		// Validate required fields
		$required_fields = $this->get_required_fields();
		foreach ( $data as $index => $item ) {
			foreach ( $required_fields as $field ) {
				if ( ! isset( $item[ $field ] ) || '' === $item[ $field ] ) {
					return new \WP_Error(
						'missing_required_field',
						sprintf(
							/* translators: 1: field name, 2: row number */
							__( 'Required field "%1$s" is missing in row %2$d', 'wp-advanced-import-export' ),
							$field,
							$index + 1
						)
					);
				}
			}
		}

		return true;
	}

	/**
	 * Prepare data for import with field mapping
	 * 
	 * Override parent to handle object-based mapping format
	 *
	 * @param array $raw_data Raw data from file
	 * @param array $mapping  Field mapping (array of objects with source_field and target_field)
	 * @return array Prepared data
	 */
	public function prepare( $raw_data, $mapping = [] ) {
		if ( empty( $mapping ) ) {
			return $raw_data;
		}

		// Convert object-based mapping to simple array format
		// Frontend sends: [{"source_field": "ID", "target_field": "ID"}, ...]
		// We need: ["ID" => "ID", "order_number" => "order_number", ...]
		$simple_mapping = [];
		
		foreach ( $mapping as $map_item ) {
			if ( is_array( $map_item ) && isset( $map_item['source_field'], $map_item['target_field'] ) ) {
				$simple_mapping[ $map_item['source_field'] ] = $map_item['target_field'];
			} elseif ( is_object( $map_item ) && isset( $map_item->source_field, $map_item->target_field ) ) {
				$simple_mapping[ $map_item->source_field ] = $map_item->target_field;
			}
		}

		// Now use parent's prepare with simple mapping
		return parent::prepare( $raw_data, $simple_mapping );
	}

	/**
	 * Import a single order
	 *
	 * @param array $item  Item data
	 * @param int   $index Item index
	 * @return string|WP_Error 'created', 'updated', 'skipped', or WP_Error
	 */
	public function import_item( $item, $index ) {
		try {
			// Check for existing order
			$existing_order = $this->find_existing_order( $item );

			if ( $existing_order ) {
				$duplicate_mode = $this->get_option( 'duplicate_mode', 'update' );

				if ( 'skip' === $duplicate_mode ) {
					return 'skipped';
				}

				if ( 'update' === $duplicate_mode ) {
					// Update existing order
					$order  = $existing_order;
					$action = 'updated';
				} else {
					// 'create' mode - create new order even if duplicate exists
					$order  = wc_create_order();
					$action = 'created';
				}
			} else {
				// Create new order
				$order  = wc_create_order();
				$action = 'created';
			}

			if ( is_wp_error( $order ) ) {
				return $order;
			}

			// Set order properties
			$this->set_order_properties( $order, $item );

			// Set billing address
			$this->set_billing_address( $order, $item );

			// Set shipping address
			$this->set_shipping_address( $order, $item );

			// Set order items
			$items_result = $this->set_order_items( $order, $item, $action );
			if ( is_wp_error( $items_result ) ) {
				return $items_result;
			}

			// Set payment method
			$this->set_payment_method( $order, $item );

			// Set shipping method
			$this->set_shipping_method( $order, $item );

			// Set order notes
			$this->set_order_notes( $order, $item );

			// Set shipping lines
			$this->set_shipping_lines( $order, $item );

			// Set fee lines
			$this->set_fee_lines( $order, $item );

			// Set coupon lines
			$this->set_coupon_lines( $order, $item );
		// Set order meta
		$this->set_order_meta( $order, $item );

		// Set dynamic custom meta fields (meta_* prefix)
		$this->set_dynamic_meta_fields( $order, $item );

		// Calculate totals if needed
			if ( $this->options['calculate_totals'] ) {
				$order->calculate_totals();
			} else {
				// Set totals manually
				$this->set_order_totals( $order, $item );
			}

			// Set dates
			$this->set_order_dates( $order, $item );

			// Set status
			$this->set_order_status( $order, $item );

			// Save order
			$order_id = $order->save();

			// Set order number if provided
			if ( ! empty( $item['order_number'] ) && method_exists( $order, 'set_order_number' ) ) {
				$order->update_meta_data( '_order_number', $item['order_number'] );
				$order->save_meta_data();
			}

			return $action;

		} catch ( \Exception $e ) {
			return new \WP_Error( 'import_failed', $e->getMessage() );
		}
	}

	/**
	 * Find existing order by ID or order number
	 *
	 * @param array $item Item data
	 * @return WC_Order|null
	 */
	protected function find_existing_order( $item ) {
		$duplicate_check = $this->get_option( 'duplicate_check', 'ID' );

		// Try by ID first (most reliable)
		if ( 'ID' === $duplicate_check || ! empty( $item['ID'] ) ) {
			if ( ! empty( $item['ID'] ) ) {
				$order = wc_get_order( $item['ID'] );
				if ( $order && ! is_wp_error( $order ) ) {
					return $order;
				}
			}
		}

		// Try by order number
		if ( 'order_number' === $duplicate_check || ! empty( $item['order_number'] ) ) {
			if ( ! empty( $item['order_number'] ) ) {
				// First check if order_number is stored in custom meta
				$orders = wc_get_orders( [
					'meta_key'   => '_order_number', // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.
					'meta_value' => $item['order_number'], // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.
					'limit'      => 1,
				] );

				if ( ! empty( $orders ) ) {
					return $orders[0];
				}

				// In WooCommerce, order number is typically the order ID
				// Try to get order directly by ID if order_number is numeric
				if ( is_numeric( $item['order_number'] ) ) {
					$order = wc_get_order( $item['order_number'] );
					if ( $order && ! is_wp_error( $order ) ) {
						return $order;
					}
				}
			}
		}

		return null;
	}

	/**
	 * Set order properties
	 *
	 * @param WC_Order $order Order object
	 * @param array    $item  Item data
	 */
	protected function set_order_properties( $order, $item ) {
		// Set customer
		if ( isset( $item['customer_id'] ) ) {
			$customer_id = (int) $item['customer_id'];
			
			// Verify customer exists
			if ( $customer_id > 0 ) {
				$user = get_user_by( 'id', $customer_id );
				if ( $user ) {
					$order->set_customer_id( $customer_id );
				} else {
					$order->set_customer_id( 0 );
				}
			} else {
				$order->set_customer_id( 0 );
			}
		}

		// Set currency
		if ( ! empty( $item['currency'] ) ) {
			$order->set_currency( $item['currency'] );
		}

		// Set customer note
		if ( ! empty( $item['customer_note'] ) ) {
			$order->set_customer_note( $item['customer_note'] );
		}

		// Set order key
		if ( ! empty( $item['order_key'] ) ) {
			$order->set_order_key( $item['order_key'] );
		}

		// Set transaction ID
		if ( ! empty( $item['transaction_id'] ) ) {
			$order->set_transaction_id( $item['transaction_id'] );
		}

		// Set customer IP and user agent
		if ( ! empty( $item['customer_ip_address'] ) ) {
			$order->set_customer_ip_address( $item['customer_ip_address'] );
		}

		if ( ! empty( $item['customer_user_agent'] ) ) {
			$order->set_customer_user_agent( $item['customer_user_agent'] );
		}
	}

	/**
	 * Set billing address
	 *
	 * @param WC_Order $order Order object
	 * @param array    $item  Item data
	 */
	protected function set_billing_address( $order, $item ) {
		$billing_fields = [
			'first_name',
			'last_name',
			'company',
			'address_1',
			'address_2',
			'city',
			'state',
			'postcode',
			'country',
			'email',
			'phone',
		];

		foreach ( $billing_fields as $field ) {
			$key = 'billing_' . $field;
			if ( isset( $item[ $key ] ) && '' !== $item[ $key ] ) {
				$method = 'set_billing_' . $field;
				if ( method_exists( $order, $method ) ) {
					$order->$method( $item[ $key ] );
				}
			}
		}
	}

	/**
	 * Set shipping address
	 *
	 * @param WC_Order $order Order object
	 * @param array    $item  Item data
	 */
	protected function set_shipping_address( $order, $item ) {
		$shipping_fields = [
			'first_name',
			'last_name',
			'company',
			'address_1',
			'address_2',
			'city',
			'state',
			'postcode',
			'country',
		];

		foreach ( $shipping_fields as $field ) {
			$key = 'shipping_' . $field;
			if ( isset( $item[ $key ] ) && '' !== $item[ $key ] ) {
				$method = 'set_shipping_' . $field;
				if ( method_exists( $order, $method ) ) {
					$order->$method( $item[ $key ] );
				}
			}
		}
	}

	/**
	 * Set order items
	 *
	 * @param WC_Order $order  Order object
	 * @param array    $item   Item data
	 * @param string   $action Action type (created/updated)
	 * @return bool|WP_Error
	 */
	protected function set_order_items( $order, $item, $action ) {
		if ( empty( $item['order_items'] ) ) {
			return new \WP_Error( 'no_order_items', __( 'No order items provided', 'wp-advanced-import-export' ) );
		}

		// Parse order items
		$order_items = $this->parse_order_items( $item['order_items'] );
		if ( is_wp_error( $order_items ) ) {
			return $order_items;
		}

		// Remove existing items if updating
		foreach ( $order->get_items() as $order_item ) {
			$order->remove_item( $order_item->get_id() );
		}

		// Add items
		foreach ( $order_items as $order_item_data ) {
			$product = null;

			// Try to get product by ID
			if ( ! empty( $order_item_data['product_id'] ) ) {
				if ( ! empty( $order_item_data['variation_id'] ) ) {
					$product = wc_get_product( $order_item_data['variation_id'] );
				}
				
				if ( ! $product ) {
					$product = wc_get_product( $order_item_data['product_id'] );
				}
			}

			// Try to get product by SKU
			if ( ! $product && ! empty( $order_item_data['sku'] ) ) {
				$product_id = wc_get_product_id_by_sku( $order_item_data['sku'] );
				if ( $product_id ) {
					$product = wc_get_product( $product_id );
				}
			}

			if ( ! $product ) {
				continue;
			}

			// Add product to order
			$product_item = new \WC_Order_Item_Product();
			$product_item->set_product( $product );
			$product_item->set_name( $order_item_data['name'] ?? $product->get_name() );
			$product_item->set_quantity( $order_item_data['quantity'] ?? 1 );

			// Set prices
			if ( isset( $order_item_data['subtotal'] ) ) {
				$product_item->set_subtotal( $order_item_data['subtotal'] );
			}
			if ( isset( $order_item_data['total'] ) ) {
				$product_item->set_total( $order_item_data['total'] );
			}
			if ( isset( $order_item_data['tax'] ) ) {
				$product_item->set_subtotal_tax( $order_item_data['tax'] );
				$product_item->set_total_tax( $order_item_data['tax'] );
			}

			// Set meta data
			if ( ! empty( $order_item_data['meta_data'] ) ) {
				foreach ( $order_item_data['meta_data'] as $meta ) {
					if ( isset( $meta['key'] ) && isset( $meta['value'] ) ) {
						$product_item->add_meta_data( $meta['key'], $meta['value'] );
					}
				}
			}

			// Add item to order
			$order->add_item( $product_item );

			// Reduce stock if enabled
			if ( $this->options['stock_management'] && 'created' === $action ) {
				wc_update_product_stock( $product, $order_item_data['quantity'] ?? 1, 'decrease' );
			}
		}

		return true;
	}

	/**
	 * Parse order items from JSON or array
	 *
	 * @param string|array $order_items Order items data
	 * @return array|WP_Error
	 */
	protected function parse_order_items( $order_items ) {
		if ( is_string( $order_items ) ) {
			$decoded = json_decode( $order_items, true );
			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return new \WP_Error( 'invalid_json', sprintf(
					/* translators: %s: JSON error message */
					__( 'Invalid JSON in order_items: %s', 'wp-advanced-import-export' ),
					json_last_error_msg()
				) );
			}
			$order_items = $decoded;
		}

		if ( ! is_array( $order_items ) ) {
			return new \WP_Error( 'invalid_items', __( 'Order items must be an array', 'wp-advanced-import-export' ) );
		}

		return $order_items;
	}

	/**
	 * Set payment method
	 *
	 * @param WC_Order $order Order object
	 * @param array    $item  Item data
	 */
	protected function set_payment_method( $order, $item ) {
		if ( ! empty( $item['payment_method'] ) ) {
			$order->set_payment_method( $item['payment_method'] );
		}

		if ( ! empty( $item['payment_method_title'] ) ) {
			$order->set_payment_method_title( $item['payment_method_title'] );
		}
	}

	/**
	 * Set shipping method
	 *
	 * @param WC_Order $order Order object
	 * @param array    $item  Item data
	 */
	protected function set_shipping_method( $order, $item ) {
		if ( empty( $item['shipping_method'] ) && empty( $item['order_shipping'] ) ) {
			return;
		}

		// Remove existing shipping items
		foreach ( $order->get_items( 'shipping' ) as $shipping_item ) {
			$order->remove_item( $shipping_item->get_id() );
		}

		// Add shipping item
		if ( ! empty( $item['shipping_method'] ) || ! empty( $item['order_shipping'] ) ) {
			$shipping = new \WC_Order_Item_Shipping();
			$shipping->set_method_title( $item['shipping_method'] ?? __( 'Shipping', 'wp-advanced-import-export' ) );
			$shipping->set_total( $item['order_shipping'] ?? 0 );
			$order->add_item( $shipping );
		}
	}

	/**
	 * Set order totals
	 *
	 * @param WC_Order $order Order object
	 * @param array    $item  Item data
	 */
	protected function set_order_totals( $order, $item ) {
		if ( isset( $item['order_total'] ) ) {
			$order->set_total( $item['order_total'] );
		}

		if ( isset( $item['order_discount'] ) ) {
			$order->set_discount_total( $item['order_discount'] );
		}

		// Note: set_cart_tax(), set_shipping_tax(), and set_total_tax() are protected methods
		// Tax totals are automatically calculated from line items and shipping
		// If you need to override them, use update_meta_data() after save()
		
		if ( isset( $item['order_shipping'] ) ) {
			$order->set_shipping_total( $item['order_shipping'] );
		}
	}

	/**
	 * Set order dates
	 *
	 * @param WC_Order $order Order object
	 * @param array    $item  Item data
	 */
	protected function set_order_dates( $order, $item ) {
		if ( ! $this->options['preserve_order_dates'] ) {
			return;
		}

		if ( ! empty( $item['order_date'] ) ) {
			try {
				$date = new \WC_DateTime( $item['order_date'] );
				$order->set_date_created( $date );
			} catch ( \Exception $e ) {
				// Invalid date, skip
			}
		}

		if ( ! empty( $item['date_modified'] ) ) {
			try {
				$date = new \WC_DateTime( $item['date_modified'] );
				$order->set_date_modified( $date );
			} catch ( \Exception $e ) {
				// Invalid date, skip
			}
		}

		if ( ! empty( $item['completed_date'] ) ) {
			try {
				$date = new \WC_DateTime( $item['completed_date'] );
				$order->set_date_completed( $date );
			} catch ( \Exception $e ) {
				// Invalid date, skip
			}
		}

		if ( ! empty( $item['paid_date'] ) ) {
			try {
				$date = new \WC_DateTime( $item['paid_date'] );
				$order->set_date_paid( $date );
			} catch ( \Exception $e ) {
				// Invalid date, skip
			}
		}
	}

	/**
	 * Set order status
	 *
	 * @param WC_Order $order Order object
	 * @param array    $item  Item data
	 */
	protected function set_order_status( $order, $item ) {
		if ( ! empty( $item['order_status'] ) ) {
			$status = $item['order_status'];
			
			// Remove 'wc-' prefix if present
			$status = str_replace( 'wc-', '', $status );
			
			// Set status without sending emails
			$order->set_status( $status, '', ! $this->options['send_notifications'] );
		}
	}

	/**
	 * Set order notes
	 *
	 * @param WC_Order $order Order object
	 * @param array    $item  Item data
	 */
	protected function set_order_notes( $order, $item ) {
		if ( empty( $item['order_notes'] ) ) {
			return;
		}

		$notes = $item['order_notes'];

		// Parse JSON if string
		if ( is_string( $notes ) ) {
			$decoded = json_decode( $notes, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$notes = $decoded;
			} else {
				// Single note as string
				$notes = [ [ 'content' => $notes, 'customer_note' => false ] ];
			}
		}

		if ( ! is_array( $notes ) ) {
			return;
		}

		// Add notes
		foreach ( $notes as $note_data ) {
			if ( empty( $note_data['content'] ) ) {
				continue;
			}

			$is_customer_note = false;
			if ( isset( $note_data['customer_note'] ) ) {
				$is_customer_note = filter_var( $note_data['customer_note'], FILTER_VALIDATE_BOOLEAN );
			}

			$order->add_order_note(
				$note_data['content'],
				$is_customer_note,
				false // Don't add by admin
			);
		}
	}

	/**
	 * Set shipping lines
	 *
	 * @param WC_Order $order Order object
	 * @param array    $item  Item data
	 */
	protected function set_shipping_lines( $order, $item ) {
		if ( empty( $item['shipping_lines'] ) ) {
			return;
		}

		$shipping_lines = $item['shipping_lines'];

		// Parse JSON if string
		if ( is_string( $shipping_lines ) ) {
			$decoded = json_decode( $shipping_lines, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$shipping_lines = $decoded;
			} else {
				return;
			}
		}

		if ( ! is_array( $shipping_lines ) ) {
			return;
		}

		// Remove existing shipping items
		foreach ( $order->get_items( 'shipping' ) as $shipping_item ) {
			$order->remove_item( $shipping_item->get_id() );
		}

		// Add shipping lines
		foreach ( $shipping_lines as $line_data ) {
			if ( empty( $line_data['method_title'] ) ) {
				continue;
			}

			$shipping = new \WC_Order_Item_Shipping();
			$shipping->set_method_title( $line_data['method_title'] );
			
			if ( isset( $line_data['method_id'] ) ) {
				$shipping->set_method_id( $line_data['method_id'] );
			}
			
			if ( isset( $line_data['total'] ) ) {
				$shipping->set_total( $line_data['total'] );
			}

			if ( isset( $line_data['tax'] ) ) {
				$shipping->set_total_tax( $line_data['tax'] );
			}

			// Set meta data
			if ( ! empty( $line_data['meta_data'] ) ) {
				foreach ( $line_data['meta_data'] as $meta ) {
					if ( isset( $meta['key'] ) && isset( $meta['value'] ) ) {
						$shipping->add_meta_data( $meta['key'], $meta['value'] );
					}
				}
			}

			$order->add_item( $shipping );
		}
	}

	/**
	 * Set fee lines
	 *
	 * @param WC_Order $order Order object
	 * @param array    $item  Item data
	 */
	protected function set_fee_lines( $order, $item ) {
		if ( empty( $item['fee_lines'] ) ) {
			return;
		}

		$fee_lines = $item['fee_lines'];

		// Parse JSON if string
		if ( is_string( $fee_lines ) ) {
			$decoded = json_decode( $fee_lines, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$fee_lines = $decoded;
			} else {
				return;
			}
		}

		if ( ! is_array( $fee_lines ) ) {
			return;
		}

		// Remove existing fee items
		foreach ( $order->get_items( 'fee' ) as $fee_item ) {
			$order->remove_item( $fee_item->get_id() );
		}

		// Add fee lines
		foreach ( $fee_lines as $line_data ) {
			if ( empty( $line_data['name'] ) ) {
				continue;
			}

			$fee = new \WC_Order_Item_Fee();
			$fee->set_name( $line_data['name'] );
			
			if ( isset( $line_data['total'] ) ) {
				$fee->set_total( $line_data['total'] );
			}

			if ( isset( $line_data['tax'] ) ) {
				$fee->set_total_tax( $line_data['tax'] );
			}

			if ( isset( $line_data['tax_class'] ) ) {
				$fee->set_tax_class( $line_data['tax_class'] );
			}

			// Set meta data
			if ( ! empty( $line_data['meta_data'] ) ) {
				foreach ( $line_data['meta_data'] as $meta ) {
					if ( isset( $meta['key'] ) && isset( $meta['value'] ) ) {
						$fee->add_meta_data( $meta['key'], $meta['value'] );
					}
				}
			}

			$order->add_item( $fee );
		}
	}

	/**
	 * Set coupon lines
	 *
	 * @param WC_Order $order Order object
	 * @param array    $item  Item data
	 */
	protected function set_coupon_lines( $order, $item ) {
		if ( empty( $item['coupon_lines'] ) ) {
			return;
		}

		$coupon_lines = $item['coupon_lines'];

		// Parse JSON if string
		if ( is_string( $coupon_lines ) ) {
			$decoded = json_decode( $coupon_lines, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$coupon_lines = $decoded;
			} else {
				return;
			}
		}

		if ( ! is_array( $coupon_lines ) ) {
			return;
		}

		// Remove existing coupon items
		foreach ( $order->get_items( 'coupon' ) as $coupon_item ) {
			$order->remove_item( $coupon_item->get_id() );
		}

		// Add coupon lines
		foreach ( $coupon_lines as $line_data ) {
			if ( empty( $line_data['code'] ) ) {
				continue;
			}

			$coupon = new \WC_Order_Item_Coupon();
			$coupon->set_code( $line_data['code'] );
			
			if ( isset( $line_data['discount'] ) ) {
				$coupon->set_discount( $line_data['discount'] );
			}

			if ( isset( $line_data['discount_tax'] ) ) {
				$coupon->set_discount_tax( $line_data['discount_tax'] );
			}

			// Set meta data
			if ( ! empty( $line_data['meta_data'] ) ) {
				foreach ( $line_data['meta_data'] as $meta ) {
					if ( isset( $meta['key'] ) && isset( $meta['value'] ) ) {
						$coupon->add_meta_data( $meta['key'], $meta['value'] );
					}
				}
			}

			$order->add_item( $coupon );
		}
	}

	/**
	 * Set order meta data
	 *
	 * @param WC_Order $order Order object
	 * @param array    $item  Item data
	 */
	protected function set_order_meta( $order, $item ) {
		if ( empty( $item['order_meta'] ) ) {
			return;
		}

		$order_meta = $item['order_meta'];

		// Parse JSON if string
		if ( is_string( $order_meta ) ) {
			$decoded = json_decode( $order_meta, true );
			if ( json_last_error() === JSON_ERROR_NONE ) {
				$order_meta = $decoded;
			} else {
				return;
			}
		}

		if ( ! is_array( $order_meta ) ) {
			return;
		}

		// Set meta data
		foreach ( $order_meta as $meta ) {
			if ( ! isset( $meta['key'] ) || ! isset( $meta['value'] ) ) {
				continue;
			}

			// Skip protected meta keys
			if ( substr( $meta['key'], 0, 1 ) === '_' ) {
				continue;
			}

			$order->update_meta_data( $meta['key'], $meta['value'] );
		}
	}

	/**
	 * Set dynamic custom meta fields (those with meta_ prefix from field mapping)
	 *
	 * This handles custom fields added dynamically during field mapping.
	 * Frontend adds "meta_" prefix to custom field names (e.g., meta_my_custom_field).
	 * 
	 * WooCommerce 8+ HPOS Support:
	 * - Uses $order->update_meta_data() which works with both HPOS and legacy storage
	 * - Meta data is automatically saved when $order->save() is called
	 * - Fully compatible with High-Performance Order Storage
	 *
	 * @param WC_Order $order Order object
	 * @param array    $item  Item data
	 */
	protected function set_dynamic_meta_fields( $order, $item ) {
		// List of known order fields to exclude (they're handled by other methods)
		$known_fields = [
			'ID',
			'order_number',
			'order_status',
			'order_key',
			'currency',
			'order_total',
			'order_subtotal',
			'order_tax',
			'order_shipping',
			'order_discount',
			'customer_id',
			'billing_email',
			'customer_note',
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
			'shipping_first_name',
			'shipping_last_name',
			'shipping_company',
			'shipping_address_1',
			'shipping_address_2',
			'shipping_city',
			'shipping_state',
			'shipping_postcode',
			'shipping_country',
			'order_items',
			'item_count',
			'payment_method',
			'payment_method_title',
			'transaction_id',
			'shipping_method',
			'order_date',
			'date_modified',
			'completed_date',
			'paid_date',
			'customer_ip_address',
			'customer_user_agent',
			'cart_tax',
			'shipping_tax',
			'total_tax',
			'shipping_lines',
			'fee_lines',
			'coupon_lines',
			'order_notes',
			'order_meta',
		];

		// Process remaining fields as custom meta
		foreach ( $item as $key => $value ) {
			// Skip known fields
			if ( in_array( $key, $known_fields, true ) ) {
				continue;
			}

			// Skip empty values
			if ( $value === '' || $value === null ) {
				continue;
			}

			// Handle meta_ prefix (added by frontend for custom fields)
			$meta_key = $key;
			if ( strpos( $key, 'meta_' ) === 0 ) {
				$meta_key = substr( $key, 5 ); // Remove 'meta_' prefix
			}

			// Skip if trying to set protected meta (starts with _)
			// These should be handled by specific methods
			if ( substr( $meta_key, 0, 1 ) === '_' && ! in_array( $meta_key, [ '_order_number' ], true ) ) {
				continue;
			}

			// Update meta field using WooCommerce API (HPOS compatible)
			$order->update_meta_data( $meta_key, $value );
		}
	}

	/**
	 * Log info message
	 *
	 * @param string $message Message to log
	 * @param array  $data    Additional data to log
	 */
	protected function log_info( $message, $data = [] ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			$context = array_merge( [ 'source' => 'woo-order-importer' ], $data );
			wc_get_logger()->info( $message, $context );
		}
	}

	/**
	 * Log warning message
	 *
	 * @param string $message Message to log
	 * @param array  $data    Additional data to log
	 */
	protected function log_warning( $message, $data = [] ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			$context = array_merge( [ 'source' => 'woo-order-importer' ], $data );
			wc_get_logger()->warning( $message, $context );
		}
	}

	/**
	 * Log error message
	 *
	 * @param string $message Message to log
	 * @param array  $data    Additional data to log
	 */
	protected function log_error( $message, $data = [] ) {
		if ( function_exists( 'wc_get_logger' ) ) {
			$context = array_merge( [ 'source' => 'woo-order-importer' ], $data );
			wc_get_logger()->error( $message, $context );
		}
	}

	/**
	 * Reset statistics
	 */
	protected function reset_stats() {
		$this->stats = [
			'total'   => 0,
			'success' => 0,
			'skipped' => 0,
			'failed'  => 0,
			'updated' => 0,
			'created' => 0,
			'errors'  => [],
		];
	}

	/**
	 * Get import statistics
	 *
	 * @return array
	 */
	public function get_stats() {
		return $this->stats;
	}
}

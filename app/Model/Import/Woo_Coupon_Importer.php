<?php
/**
 * WooCommerce Coupon Importer
 *
 * Handles importing WooCommerce coupons with all settings
 *
 * IMPORT BEHAVIOR:
 * ----------------
 * 
 * Coupons:
 * - If coupon with same code exists, it will be UPDATED (if update_existing = true)
 * - If coupon doesn't exist, it will be CREATED
 * - Coupon code (post_title) is used as unique identifier
 * 
 * DATA FORMATS:
 * -------------
 * 
 * Arrays fields (product_ids, product_categories, etc.) accept:
 * 1. JSON format: [123,456,789]
 * 2. Comma-separated: "123,456,789"
 * 
 * Boolean fields (individual_use, free_shipping, etc.):
 * - Accepts: 1, 0, true, false, yes, no
 * 
 * Date fields (date_expires):
 * - Format: "YYYY-MM-DD HH:MM:SS" or "YYYY-MM-DD"
 * 
 * BEST PRACTICES:
 * ---------------
 * 
 * When re-importing exported coupons:
 * - Existing coupons will be updated with new settings
 * - Usage statistics can be preserved or reset via options
 * - No duplicate coupons will be created
 * 
 * When importing new coupons:
 * - Set update_existing = true to update if code exists
 * - Set update_existing = false to skip existing coupons
 *
 * @package WP_AIE\Model\Import
 */

namespace WP_AIE\Model\Import;

defined( 'ABSPATH' ) || exit;

class Woo_Coupon_Importer extends Abstract_Importer {

	/**
	 * Get importer name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'woo_coupon';
	}

	/**
	 * Get importer description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Import WooCommerce discount coupons with all settings and restrictions', 'advanced-import-export' );
	}

	/**
	 * Get required fields for import
	 *
	 * @return array
	 */
	public function get_required_fields() {
		return [ 'post_title' ];
	}

	/**
	 * Get available fields for import
	 *
	 * @return array
	 */
	public function get_available_fields() {
		return [
			'post_title'                  => [
				'label'       => __( 'Coupon Code', 'advanced-import-export' ),
				'description' => __( 'Unique coupon code', 'advanced-import-export' ),
				'required'    => true,
			],
			'post_excerpt'                => [
				'label'       => __( 'Description', 'advanced-import-export' ),
				'description' => __( 'Coupon description', 'advanced-import-export' ),
			],
			'post_status'                 => [
				'label'       => __( 'Status', 'advanced-import-export' ),
				'description' => __( 'Coupon status (publish, draft, pending)', 'advanced-import-export' ),
			],
			'discount_type'               => [
				'label'       => __( 'Discount Type', 'advanced-import-export' ),
				'description' => __( 'Type: percent, fixed_cart, fixed_product', 'advanced-import-export' ),
			],
			'coupon_amount'               => [
				'label'       => __( 'Amount', 'advanced-import-export' ),
				'description' => __( 'Discount amount', 'advanced-import-export' ),
			],
			'date_expires'                => [
				'label'       => __( 'Expiry Date', 'advanced-import-export' ),
				'description' => __( 'Format: YYYY-MM-DD or YYYY-MM-DD HH:MM:SS', 'advanced-import-export' ),
			],
			'usage_limit'                 => [
				'label'       => __( 'Usage Limit', 'advanced-import-export' ),
				'description' => __( 'Maximum number of times coupon can be used', 'advanced-import-export' ),
			],
			'usage_count'                 => [
				'label'       => __( 'Usage Count', 'advanced-import-export' ),
				'description' => __( 'Current number of times coupon has been used', 'advanced-import-export' ),
			],
			'usage_limit_per_user'        => [
				'label'       => __( 'Usage Limit Per User', 'advanced-import-export' ),
				'description' => __( 'Maximum uses per user', 'advanced-import-export' ),
			],
			'limit_usage_to_x_items'      => [
				'label'       => __( 'Limit Usage to X Items', 'advanced-import-export' ),
				'description' => __( 'Limit discount to X items in cart', 'advanced-import-export' ),
			],
			'individual_use'              => [
				'label'       => __( 'Individual Use', 'advanced-import-export' ),
				'description' => __( 'Can\'t be used with other coupons (1 or 0)', 'advanced-import-export' ),
			],
			'free_shipping'               => [
				'label'       => __( 'Free Shipping', 'advanced-import-export' ),
				'description' => __( 'Grant free shipping (1 or 0)', 'advanced-import-export' ),
			],
			'exclude_sale_items'          => [
				'label'       => __( 'Exclude Sale Items', 'advanced-import-export' ),
				'description' => __( 'Exclude items on sale (1 or 0)', 'advanced-import-export' ),
			],
			'product_ids'                 => [
				'label'       => __( 'Product IDs', 'advanced-import-export' ),
				'description' => __( 'JSON array or comma-separated product IDs', 'advanced-import-export' ),
			],
			'excluded_product_ids'        => [
				'label'       => __( 'Excluded Product IDs', 'advanced-import-export' ),
				'description' => __( 'JSON array or comma-separated excluded product IDs', 'advanced-import-export' ),
			],
			'product_categories'          => [
				'label'       => __( 'Product Categories', 'advanced-import-export' ),
				'description' => __( 'JSON array or comma-separated category IDs', 'advanced-import-export' ),
			],
			'excluded_product_categories' => [
				'label'       => __( 'Excluded Categories', 'advanced-import-export' ),
				'description' => __( 'JSON array or comma-separated excluded category IDs', 'advanced-import-export' ),
			],
			'minimum_amount'              => [
				'label'       => __( 'Minimum Amount', 'advanced-import-export' ),
				'description' => __( 'Minimum order amount required', 'advanced-import-export' ),
			],
			'maximum_amount'              => [
				'label'       => __( 'Maximum Amount', 'advanced-import-export' ),
				'description' => __( 'Maximum order amount allowed', 'advanced-import-export' ),
			],
			'allowed_emails'              => [
				'label'       => __( 'Allowed Emails', 'advanced-import-export' ),
				'description' => __( 'JSON array or comma-separated email addresses', 'advanced-import-export' ),
			],
		];
	}

	/**
	 * Get optional fields for import
	 *
	 * @return array
	 */
	public function get_optional_fields() {
		$all_fields = array_keys( $this->get_available_fields() );
		return array_diff( $all_fields, $this->get_required_fields() );
	}

	/**
	 * Get supported import options
	 *
	 * @return array
	 */
	public function get_supported_options() {
		return [
			'update_existing'      => __( 'Update existing coupons if found', 'advanced-import-export' ),
			'preserve_usage_count' => __( 'Keep existing usage count when updating', 'advanced-import-export' ),
		];
	}

	/**
	 * Get default import options
	 *
	 * @return array
	 */
	protected function get_default_options() {
		return [
			'update_existing'      => true,  // Update existing coupons if found
			'preserve_usage_count' => false, // Reset usage count on update by default
		];
	}

	/**
	 * Prepare data with mapping
	 *
	 * @param array $data    Raw data from parser
	 * @param array $mapping Field mapping
	 * @return array Prepared data
	 */
	public function prepare( $data, $mapping = [] ) {
		$prepared = [];

		foreach ( $data as $row ) {
			$item = [];

			// Process mapping array
			foreach ( $mapping as $map ) {
				$source_field = $map['source_field'] ?? '';
				$target_field = $map['target_field'] ?? '';

				if ( empty( $target_field ) ) {
					continue;
				}

				// Get value from source
				$value = '';
				if ( isset( $map['source_index'] ) && isset( $row[ $map['source_index'] ] ) ) {
					$value = $row[ $map['source_index'] ];
				} elseif ( ! empty( $source_field ) && isset( $row[ $source_field ] ) ) {
					$value = $row[ $source_field ];
				}

				// Special handling for certain fields
				$item[ $target_field ] = $this->sanitize_field_value( $target_field, $value );
			}

			// Set defaults
			if ( ! isset( $item['post_status'] ) ) {
				$item['post_status'] = 'publish';
			}

			if ( ! isset( $item['discount_type'] ) ) {
				$item['discount_type'] = 'fixed_cart';
			}

			$prepared[] = $item;
		}

		return $prepared;
	}

	/**
	 * Sanitize field value based on field type
	 *
	 * @param string $field Field name
	 * @param mixed  $value Field value
	 * @return mixed Sanitized value
	 */
	protected function sanitize_field_value( $field, $value ) {
		// Array fields (product_ids, categories, etc.)
		$array_fields = [
			'product_ids',
			'excluded_product_ids',
			'product_categories',
			'excluded_product_categories',
			'allowed_emails',
		];

		if ( in_array( $field, $array_fields, true ) ) {
			$parsed = $this->parse_array_field( $value );

			// Make exported coupons portable across sites (IDs differ):
			// Coupon exporter may output product/category restrictions as refs:
			// - sku:ABC123
			// - slug:my-product
			// - id:123
			if ( in_array( $field, [ 'product_ids', 'excluded_product_ids' ], true ) ) {
				return $this->resolve_coupon_product_ids( $parsed );
			}
			if ( in_array( $field, [ 'product_categories', 'excluded_product_categories' ], true ) ) {
				return $this->resolve_coupon_product_cat_ids( $parsed );
			}

			return $parsed;
		}

		// Boolean fields
		$boolean_fields = [
			'individual_use',
			'free_shipping',
			'exclude_sale_items',
		];

		if ( in_array( $field, $boolean_fields, true ) ) {
			return $this->parse_boolean( $value );
		}

		// Numeric fields
		$numeric_fields = [
			'coupon_amount',
			'usage_limit',
			'usage_limit_per_user',
			'limit_usage_to_x_items',
			'minimum_amount',
			'maximum_amount',
		];

		if ( in_array( $field, $numeric_fields, true ) ) {
			return $value !== '' ? floatval( $value ) : '';
		}

		// Date fields
		if ( 'date_expires' === $field ) {
			return $this->parse_date( $value );
		}

		return $value;
	}

	/**
	 * Resolve coupon product restriction values to product IDs.
	 *
	 * @param array $values Parsed values.
	 * @return int[] Product IDs.
	 */
	protected function resolve_coupon_product_ids( $values ) {
		if ( empty( $values ) || ! is_array( $values ) ) {
			return [];
		}

		$out  = [];
		$seen = [];

		foreach ( $values as $ref ) {
			$id = $this->resolve_coupon_product_id_ref( $ref );
			if ( $id > 0 && ! isset( $seen[ $id ] ) ) {
				$seen[ $id ] = true;
				$out[]       = $id;
			}
		}

		return $out;
	}

	/**
	 * Resolve coupon product_cat restriction values to term IDs.
	 *
	 * @param array $values Parsed values.
	 * @return int[] Term IDs.
	 */
	protected function resolve_coupon_product_cat_ids( $values ) {
		if ( empty( $values ) || ! is_array( $values ) ) {
			return [];
		}

		$out  = [];
		$seen = [];

		foreach ( $values as $ref ) {
			$id = $this->resolve_coupon_product_cat_id_ref( $ref );
			if ( $id > 0 && ! isset( $seen[ $id ] ) ) {
				$seen[ $id ] = true;
				$out[]       = $id;
			}
		}

		return $out;
	}

	/**
	 * Resolve a single portable product reference to a product/variation ID.
	 *
	 * @param mixed $ref Portable ref (sku:..., slug:..., id:...) or legacy int/string.
	 * @return int Product ID.
	 */
	protected function resolve_coupon_product_id_ref( $ref ) {
		if ( is_int( $ref ) ) {
			return $ref > 0 ? $ref : 0;
		}

		$raw = trim( (string) $ref );
		if ( '' === $raw ) {
			return 0;
		}

		$prefix  = '';
		$payload = $raw;
		if ( false !== strpos( $raw, ':' ) ) {
			$parts = explode( ':', $raw, 2 );
			if ( 2 === count( $parts ) ) {
				$prefix  = strtolower( trim( $parts[0] ) );
				$payload = trim( $parts[1] );
			}
		}

		if ( 'sku' === $prefix && '' !== $payload && function_exists( 'wc_get_product_id_by_sku' ) ) {
			$id = (int) wc_get_product_id_by_sku( $payload );
			return $id > 0 ? $id : 0;
		}

		if ( 'slug' === $prefix && '' !== $payload ) {
			$ids = get_posts(
				[
					'name'           => $payload,
					'post_type'      => [ 'product', 'product_variation' ],
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
				]
			);
			if ( ! empty( $ids ) ) {
				return (int) $ids[0];
			}
			return 0;
		}

		if ( 'id' === $prefix && is_numeric( $payload ) ) {
			$id = (int) $payload;
			return $id > 0 ? $id : 0;
		}

		// Backwards compatibility: plain value (try SKU first, then ID, then slug).
		if ( function_exists( 'wc_get_product_id_by_sku' ) ) {
			$id = (int) wc_get_product_id_by_sku( $raw );
			if ( $id > 0 ) {
				return $id;
			}
		}

		if ( is_numeric( $raw ) && function_exists( 'wc_get_product' ) ) {
			$id      = (int) $raw;
			$product = $id > 0 ? wc_get_product( $id ) : null;
			if ( $product ) {
				return $id;
			}
		}

		$ids = get_posts(
			[
				'name'           => $raw,
				'post_type'      => [ 'product', 'product_variation' ],
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			]
		);
		if ( ! empty( $ids ) ) {
			return (int) $ids[0];
		}

		return 0;
	}

	/**
	 * Resolve a single portable product_cat reference to a term ID.
	 *
	 * @param mixed $ref Portable ref (slug:..., id:...) or legacy int/string.
	 * @return int Term ID.
	 */
	protected function resolve_coupon_product_cat_id_ref( $ref ) {
		if ( is_int( $ref ) ) {
			return $ref > 0 ? $ref : 0;
		}

		$raw = trim( (string) $ref );
		if ( '' === $raw ) {
			return 0;
		}

		$prefix  = '';
		$payload = $raw;
		if ( false !== strpos( $raw, ':' ) ) {
			$parts = explode( ':', $raw, 2 );
			if ( 2 === count( $parts ) ) {
				$prefix  = strtolower( trim( $parts[0] ) );
				$payload = trim( $parts[1] );
			}
		}

		if ( 'slug' === $prefix && '' !== $payload ) {
			$term = get_term_by( 'slug', $payload, 'product_cat' );
			return ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;
		}

		if ( 'id' === $prefix && is_numeric( $payload ) ) {
			$term = get_term( (int) $payload, 'product_cat' );
			return ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;
		}

		if ( is_numeric( $raw ) ) {
			$term = get_term( (int) $raw, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				return (int) $term->term_id;
			}
		}

		$term = get_term_by( 'slug', $raw, 'product_cat' );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}

		$term = get_term_by( 'name', $raw, 'product_cat' );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}

		return 0;
	}

	/**
	 * Parse array field (JSON or comma-separated)
	 *
	 * @param mixed $value Value to parse
	 * @return array Parsed array
	 */
	protected function parse_array_field( $value ) {
		if ( is_array( $value ) ) {
			return $value;
		}

		if ( empty( $value ) ) {
			return [];
		}

		// Try JSON decode first
		$decoded = json_decode( $value, true );
		if ( is_array( $decoded ) ) {
			return $decoded;
		}

		// Try comma-separated
		return array_map( 'trim', explode( ',', $value ) );
	}

	/**
	 * Parse boolean value
	 *
	 * @param mixed $value Value to parse
	 * @return bool Boolean value
	 */
	protected function parse_boolean( $value ) {
		if ( is_bool( $value ) ) {
			return $value;
		}

		$value = strtolower( trim( $value ) );
		return in_array( $value, [ '1', 'true', 'yes', 'on' ], true );
	}

	/**
	 * Parse date value
	 *
	 * @param mixed $value Value to parse
	 * @return string Formatted date or empty string
	 */
	protected function parse_date( $value ) {
		if ( empty( $value ) ) {
			return '';
		}

		// Try to create DateTime object
		try {
			$date = new \DateTime( $value );
			return $date->format( 'Y-m-d H:i:s' );
		} catch ( \Exception $e ) {
			return '';
		}
	}

	/**
	 * Import single coupon item
	 *
	 * @param array $item  Prepared item data
	 * @param int   $index Item index
	 * @return int|string|WP_Error Coupon ID, 'skipped', 'updated', or WP_Error
	 */
	public function import_item( $item, $index ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new \WP_Error(
				'woocommerce_not_active',
				__( 'WooCommerce is not active', 'advanced-import-export' )
			);
		}

		// Validate required fields
		if ( empty( $item['post_title'] ) ) {
			return new \WP_Error(
				'missing_coupon_code',
				__( 'Coupon code is required', 'advanced-import-export' )
			);
		}

		$coupon_code = wc_sanitize_coupon_code( $item['post_title'] );

		// Check if coupon exists
		$existing_id = wc_get_coupon_id_by_code( $coupon_code );

		if ( $existing_id ) {
			// Coupon exists
			if ( ! $this->options['update_existing'] ) {
				$this->log_info( sprintf( 'Skipping existing coupon: %s', $coupon_code ) );
				return 'skipped';
			}

			// Update existing coupon
			$result = $this->update_coupon( $existing_id, $item );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return 'updated';
		} else {
			// Create new coupon
			$coupon_id = $this->create_coupon( $item );
			if ( is_wp_error( $coupon_id ) ) {
				return $coupon_id;
			}

			return $coupon_id;
		}
	}

	/**
	 * Create new coupon
	 *
	 * @param array $item Coupon data
	 * @return int|WP_Error Coupon ID or WP_Error
	 */
	protected function create_coupon( $item ) {
		$coupon = new \WC_Coupon();

		// Set basic properties
		$coupon->set_code( $item['post_title'] );
		
		if ( isset( $item['post_excerpt'] ) ) {
			$coupon->set_description( $item['post_excerpt'] );
		}

		if ( isset( $item['post_status'] ) ) {
			$coupon->set_status( $item['post_status'] );
		}

		// Set discount properties
		if ( isset( $item['discount_type'] ) ) {
			$coupon->set_discount_type( $item['discount_type'] );
		}

		if ( isset( $item['coupon_amount'] ) ) {
			$coupon->set_amount( $item['coupon_amount'] );
		}

		// Set expiry date
		if ( ! empty( $item['date_expires'] ) ) {
			$coupon->set_date_expires( $item['date_expires'] );
		}

		// Set usage limits
		if ( isset( $item['usage_limit'] ) && $item['usage_limit'] !== '' ) {
			$coupon->set_usage_limit( (int) $item['usage_limit'] );
		}

		if ( isset( $item['usage_limit_per_user'] ) && $item['usage_limit_per_user'] !== '' ) {
			$coupon->set_usage_limit_per_user( (int) $item['usage_limit_per_user'] );
		}

		if ( isset( $item['limit_usage_to_x_items'] ) && $item['limit_usage_to_x_items'] !== '' ) {
			$coupon->set_limit_usage_to_x_items( (int) $item['limit_usage_to_x_items'] );
		}

		// Set boolean properties
		if ( isset( $item['individual_use'] ) ) {
			$coupon->set_individual_use( $item['individual_use'] );
		}

		if ( isset( $item['free_shipping'] ) ) {
			$coupon->set_free_shipping( $item['free_shipping'] );
		}

		if ( isset( $item['exclude_sale_items'] ) ) {
			$coupon->set_exclude_sale_items( $item['exclude_sale_items'] );
		}

		// Set product restrictions
		if ( isset( $item['product_ids'] ) ) {
			$coupon->set_product_ids( $item['product_ids'] );
		}

		if ( isset( $item['excluded_product_ids'] ) ) {
			$coupon->set_excluded_product_ids( $item['excluded_product_ids'] );
		}

		// Set category restrictions
		if ( isset( $item['product_categories'] ) ) {
			$coupon->set_product_categories( $item['product_categories'] );
		}

		if ( isset( $item['excluded_product_categories'] ) ) {
			$coupon->set_excluded_product_categories( $item['excluded_product_categories'] );
		}

		// Set amount restrictions
		if ( isset( $item['minimum_amount'] ) && $item['minimum_amount'] !== '' ) {
			$coupon->set_minimum_amount( $item['minimum_amount'] );
		}

		if ( isset( $item['maximum_amount'] ) && $item['maximum_amount'] !== '' ) {
			$coupon->set_maximum_amount( $item['maximum_amount'] );
		}

		// Set email restrictions
		if ( isset( $item['allowed_emails'] ) ) {
			$coupon->set_email_restrictions( $item['allowed_emails'] );
		}

		// Save coupon
		$coupon_id = $coupon->save();

		if ( ! $coupon_id ) {
			return new \WP_Error(
				'coupon_create_failed',
				sprintf(
					/* translators: %s: coupon code */
					__( 'Failed to create coupon: %s', 'advanced-import-export' ),
					$item['post_title']
				)
			);
		}

		// Import custom meta fields
		$this->import_meta_fields( $coupon_id, $item );

		$this->log_info( sprintf( 'Created coupon: %s (ID: %d)', $item['post_title'], $coupon_id ) );

		return $coupon_id;
	}

	/**
	 * Update existing coupon
	 *
	 * @param int   $coupon_id Coupon ID
	 * @param array $item      Coupon data
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	protected function update_coupon( $coupon_id, $item ) {
		$coupon = new \WC_Coupon( $coupon_id );

		if ( ! $coupon->get_id() ) {
			return new \WP_Error(
				'coupon_not_found',
				sprintf(
					/* translators: %s: coupon ID */
					__( 'Coupon not found: %s', 'advanced-import-export' ),
					$coupon_id
				)
			);
		}

		// Preserve usage count if option is set
		$preserve_usage = $this->options['preserve_usage_count'] ?? false;
		$original_usage_count = $preserve_usage ? $coupon->get_usage_count() : 0;

		// Update code (title)
		$coupon->set_code( $item['post_title'] );

		// Update description
		if ( isset( $item['post_excerpt'] ) ) {
			$coupon->set_description( $item['post_excerpt'] );
		}

		// Update status
		if ( isset( $item['post_status'] ) ) {
			$coupon->set_status( $item['post_status'] );
		}

		// Update discount properties
		if ( isset( $item['discount_type'] ) ) {
			$coupon->set_discount_type( $item['discount_type'] );
		}

		if ( isset( $item['coupon_amount'] ) ) {
			$coupon->set_amount( $item['coupon_amount'] );
		}

		// Update expiry date
		if ( isset( $item['date_expires'] ) ) {
			if ( ! empty( $item['date_expires'] ) ) {
				$coupon->set_date_expires( $item['date_expires'] );
			} else {
				$coupon->set_date_expires( null );
			}
		}

		// Update usage limits
		if ( isset( $item['usage_limit'] ) ) {
			$coupon->set_usage_limit( $item['usage_limit'] !== '' ? (int) $item['usage_limit'] : null );
		}

		if ( isset( $item['usage_count'] ) ) {
			$coupon->set_usage_count( $item['usage_count'] !== '' ? (int) $item['usage_count'] : 0 );
		}

		if ( isset( $item['usage_limit_per_user'] ) ) {
			$coupon->set_usage_limit_per_user( $item['usage_limit_per_user'] !== '' ? (int) $item['usage_limit_per_user'] : null );
		}

		if ( isset( $item['limit_usage_to_x_items'] ) ) {
			$coupon->set_limit_usage_to_x_items( $item['limit_usage_to_x_items'] !== '' ? (int) $item['limit_usage_to_x_items'] : null );
		}

		// Update boolean properties
		if ( isset( $item['individual_use'] ) ) {
			$coupon->set_individual_use( $item['individual_use'] );
		}

		if ( isset( $item['free_shipping'] ) ) {
			$coupon->set_free_shipping( $item['free_shipping'] );
		}

		if ( isset( $item['exclude_sale_items'] ) ) {
			$coupon->set_exclude_sale_items( $item['exclude_sale_items'] );
		}

		// Update product restrictions
		if ( isset( $item['product_ids'] ) ) {
			$coupon->set_product_ids( $item['product_ids'] );
		}

		if ( isset( $item['excluded_product_ids'] ) ) {
			$coupon->set_excluded_product_ids( $item['excluded_product_ids'] );
		}

		// Update category restrictions
		if ( isset( $item['product_categories'] ) ) {
			$coupon->set_product_categories( $item['product_categories'] );
		}

		if ( isset( $item['excluded_product_categories'] ) ) {
			$coupon->set_excluded_product_categories( $item['excluded_product_categories'] );
		}

		// Update amount restrictions
		if ( isset( $item['minimum_amount'] ) ) {
			$coupon->set_minimum_amount( $item['minimum_amount'] !== '' ? $item['minimum_amount'] : '' );
		}

		if ( isset( $item['maximum_amount'] ) ) {
			$coupon->set_maximum_amount( $item['maximum_amount'] !== '' ? $item['maximum_amount'] : '' );
		}

		// Update email restrictions
		if ( isset( $item['allowed_emails'] ) ) {
			$coupon->set_email_restrictions( $item['allowed_emails'] );
		}

		// Restore usage count if preserving
		if ( $preserve_usage && $original_usage_count > 0 ) {
			update_post_meta( $coupon_id, 'usage_count', $original_usage_count );
		}

		// Save coupon
		$coupon->save();

		// Import custom meta fields
		$this->import_meta_fields( $coupon_id, $item );

		$this->log_info( sprintf( 'Updated coupon: %s (ID: %d)', $item['post_title'], $coupon_id ) );

		return true;
	}

	/**
	 * Import custom meta fields for coupon
	 *
	 * @param int   $coupon_id Coupon ID
	 * @param array $item      Coupon data with meta fields
	 * @return void
	 */
	protected function import_meta_fields( $coupon_id, $item ) {
		// List of known WooCommerce coupon fields to exclude
		$known_fields = [
			'post_title',
			'post_excerpt',
			'post_status',
			'post_date',
			'post_modified',
			'discount_type',
			'coupon_amount',
			'date_expires',
			'usage_limit',
			'usage_count',
			'usage_limit_per_user',
			'limit_usage_to_x_items',
			'individual_use',
			'free_shipping',
			'exclude_sale_items',
			'product_ids',
			'excluded_product_ids',
			'product_categories',
			'excluded_product_categories',
			'minimum_amount',
			'maximum_amount',
			'allowed_emails',
			'used_by',
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

			// Update meta field
			update_post_meta( $coupon_id, $meta_key, $value );
			
			$this->log_info( sprintf( 'Set meta field "%s" for coupon ID %d', $meta_key, $coupon_id ) );
		}
	}

	/**
	 * Validate import data
	 *
	 * @param array $data Data to validate
	 * @return true|WP_Error
	 */
	public function validate( $data ) {
		// Check WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new \WP_Error(
				'woocommerce_not_active',
				__( 'WooCommerce is not active', 'advanced-import-export' )
			);
		}

		// Call parent validation
		return parent::validate( $data );
	}
}

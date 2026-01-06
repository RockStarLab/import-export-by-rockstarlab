<?php
/**
 * WooCommerce Product Importer
 *
 * Handles importing WooCommerce products
 *
 * @package WP_AIE\Model\Import
 */

namespace WP_AIE\Model\Import;

/**
 * Product Importer Class
 *
 * Imports WooCommerce products with support for:
 * - Simple, Variable, Grouped, External products
 * - Product metadata (SKU, price, stock, dimensions, etc.)
 * - Product categories and tags
 * - Product gallery images
 * - Featured image
 * - Product attributes
 * - Duplicate handling
 * - ACF fields
 * - Yoast SEO meta
 *
 * @package WP_AIE\Model\Import
 */
class Product_Importer extends Abstract_Importer {

	/**
	 * Get importer name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'products';
	}

	/**
	 * Get importer description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Import WooCommerce products with all metadata, categories, tags, and images', 'wp-advanced-import-export' );
	}

	/**
	 * Get required fields
	 *
	 * @return array
	 */
	public function get_required_fields() {
		return [ 'post_title', 'product_type' ];
	}

	/**
	 * Get optional fields
	 *
	 * @return array
	 */
	public function get_optional_fields() {
		return [
			'ID',
			'post_name',
			'post_status',
			'post_author',
			'post_content',
			'post_excerpt',
			'post_date',
			'post_modified',
			'comment_status',
			'sku',
			'regular_price',
			'sale_price',
			'tax_status',
			'tax_class',
			'stock_quantity',
			'stock_status',
			'manage_stock',
			'backorders',
			'product_type',
			'downloadable',
			'virtual',
			'weight',
			'length',
			'width',
			'height',
			'shipping_class',
			'featured_image_id',
			'featured_image_url',
			'featured_image_title',
			'featured_image_caption',
			'product_gallery',
			'product_cat',
			'product_tag',
			'average_rating',
			'review_count',
			'featured',
			'visibility',
			'total_sales',
			// ACF and Yoast SEO fields
			'acf_test',
			'_yoast_wpseo_title',
			'_yoast_wpseo_metadesc',
			'_yoast_wpseo_focuskw',
			'_yoast_wpseo_canonical',
			'_yoast_wpseo_meta-robots-noindex',
			'_yoast_wpseo_meta-robots-nofollow',
			'_yoast_wpseo_opengraph-title',
			'_yoast_wpseo_opengraph-description',
			'_yoast_wpseo_opengraph-image',
			'_yoast_wpseo_twitter-title',
			'_yoast_wpseo_twitter-description',
			'_yoast_wpseo_twitter-image',
		];
	}

	/**
	 * Get supported options
	 *
	 * @return array
	 */
	public function get_supported_options() {
		return [
			'duplicate_mode'         => 'How to handle duplicates: skip, update, create',
			'duplicate_check'        => 'Field to check for duplicates: ID, sku, post_title',
			'default_product_type'   => 'Default product type: simple, variable, grouped, external',
			'default_status'         => 'Default product status: publish, draft, pending',
			'update_existing'        => 'Update existing products: true, false',
			'import_acf'             => 'Import ACF fields: true, false',
			'import_categories'      => 'Import product categories: true, false',
			'import_tags'            => 'Import product tags: true, false',
			'import_images'          => 'Import product images: true, false',
			'download_remote_images' => 'Download remote images: true, false',
		];
	}

	/**
	 * Get default options
	 *
	 * @return array
	 */
	protected function get_default_options() {
		return array_merge(
			parent::get_default_options(),
			[
				'duplicate_check'        => 'ID',
				'default_product_type'   => 'simple',
				'default_status'         => 'publish',
				'update_existing'        => false,
				'import_acf'             => true,
				'import_categories'      => true,
				'import_tags'            => true,
				'import_images'          => true,
				'download_remote_images' => false,
			]
		);
	}

	/**
	 * Set importer options
	 *
	 * Overrides parent to map UI field names to internal option names
	 *
	 * @param array $options Options to set
	 * @return void
	 */
	public function set_options( $options ) {
		// Map duplicate_handling (from UI) to duplicate_mode (used internally)
		if ( isset( $options['duplicate_handling'] ) && ! isset( $options['duplicate_mode'] ) ) {
			$options['duplicate_mode'] = $options['duplicate_handling'];
		}

		// Map if_exists (from UI) to duplicate_mode (used internally)
		if ( isset( $options['if_exists'] ) && ! isset( $options['duplicate_mode'] ) ) {
			$options['duplicate_mode'] = $options['if_exists'];
		}

		// Map unique_field (from UI) to duplicate_check (used internally)
		if ( isset( $options['unique_field'] ) && ! isset( $options['duplicate_check'] ) ) {
			$options['duplicate_check'] = $options['unique_field'];
		}

		parent::set_options( $options );
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

				$item[ $target_field ] = $value;
			}

			$prepared[] = $item;
		}

		return $prepared;
	}

	/**
	 * Import single product
	 *
	 * @param array $item  Product data
	 * @param int   $index Item index
	 * @return int|string|WP_Error Product ID, 'skipped', 'updated', or WP_Error
	 */
	public function import_item( $item, $index ) {
		// Sanitize data
		$item = $this->sanitize_item( $item );

		// Validate required fields
		if ( empty( $item['post_title'] ) ) {
			return new \WP_Error(
				'missing_product_title',
				__( 'Product title is required', 'wp-advanced-import-export' )
			);
		}

		// Check for existing product based on duplicate_check setting
		$existing_product_id = null;
		$duplicate_check     = $this->get_option( 'duplicate_check', 'ID' );

		if ( 'ID' === $duplicate_check ) {
			$product_id = absint( $item['ID'] ?? 0 );
			if ( $product_id ) {
				$existing_product = get_post( $product_id );
				if ( $existing_product && 'product' === $existing_product->post_type ) {
					$existing_product_id = $product_id;
				}
			}
		} elseif ( 'sku' === $duplicate_check ) {
			if ( ! empty( $item['sku'] ) ) {
				$existing_product_id = wc_get_product_id_by_sku( $item['sku'] );
			}
		} elseif ( 'post_title' === $duplicate_check ) {
			$existing_product_id = $this->find_product_by_title( $item['post_title'] ?? '' );
		}

		// Handle duplicate
		if ( $existing_product_id ) {
			$duplicate_mode = $this->get_option( 'duplicate_mode', 'skip' );

			if ( 'skip' === $duplicate_mode ) {
				return 'skipped';
			} elseif ( 'update' === $duplicate_mode || $this->get_option( 'update_existing', false ) ) {
				return $this->update_product( $existing_product_id, $item );
			}
			// If 'create' mode, continue to create new product
		}

		// Create new product
		return $this->create_product( $item );
	}

	/**
	 * Create new product
	 *
	 * @param array $item Product data
	 * @return int|WP_Error Product ID or WP_Error
	 */
	private function create_product( $item ) {
		$product_data = $this->prepare_product_data( $item );

		// Remove ID if present to force creation
		unset( $product_data['ID'] );

		// Create product post
		$product_id = wp_insert_post( $product_data, true );

		if ( is_wp_error( $product_id ) ) {
			return $product_id;
		}

		// Import product metadata
		$this->import_product_meta( $product_id, $item );

		// Import taxonomies (categories, tags)
		$this->import_product_taxonomies( $product_id, $item );

		// Import images
		$this->import_product_images( $product_id, $item );

		return $product_id;
	}

	/**
	 * Update existing product
	 *
	 * @param int   $product_id Existing product ID
	 * @param array $item       Product data
	 * @return string|WP_Error 'updated' or WP_Error
	 */
	private function update_product( $product_id, $item ) {
		$product_data       = $this->prepare_product_data( $item );
		$product_data['ID'] = $product_id;

		// Update product post
		$result = wp_update_post( $product_data, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Update product metadata
		$this->import_product_meta( $product_id, $item );

		// Update taxonomies (categories, tags)
		$this->import_product_taxonomies( $product_id, $item );

		// Update images
		$this->import_product_images( $product_id, $item );

		return 'updated';
	}

	/**
	 * Prepare product data for wp_insert_post or wp_update_post
	 *
	 * @param array $item Raw product data
	 * @return array Prepared product data
	 */
	private function prepare_product_data( $item ) {
		$product_data = [
			'post_type'    => 'product',
			'post_title'   => $item['post_title'] ?? '',
			'post_name'    => $item['post_name'] ?? '',
			'post_status'  => $item['post_status'] ?? $this->get_option( 'default_status', 'publish' ),
			'post_author'  => absint( $item['post_author'] ?? get_current_user_id() ),
			'post_content' => $item['post_content'] ?? '',
			'post_excerpt' => $item['post_excerpt'] ?? '',
		];

		// Handle dates
		if ( ! empty( $item['post_date'] ) ) {
			$product_data['post_date'] = $item['post_date'];
		}

		if ( ! empty( $item['post_modified'] ) ) {
			$product_data['post_modified'] = $item['post_modified'];
		}

		// Comment status
		if ( ! empty( $item['comment_status'] ) ) {
			$product_data['comment_status'] = $item['comment_status'];
		}

		return $product_data;
	}

	/**
	 * Import product metadata
	 *
	 * @param int   $product_id Product ID
	 * @param array $item       Product data
	 * @return void
	 */
	private function import_product_meta( $product_id, $item ) {
		// SKU
		if ( isset( $item['sku'] ) ) {
			update_post_meta( $product_id, '_sku', wc_clean( $item['sku'] ) );
		}

		// Price
		if ( isset( $item['regular_price'] ) && '' !== $item['regular_price'] ) {
			update_post_meta( $product_id, '_regular_price', wc_format_decimal( $item['regular_price'] ) );
			update_post_meta( $product_id, '_price', wc_format_decimal( $item['regular_price'] ) );
		}

		if ( isset( $item['sale_price'] ) && '' !== $item['sale_price'] ) {
			update_post_meta( $product_id, '_sale_price', wc_format_decimal( $item['sale_price'] ) );
			update_post_meta( $product_id, '_price', wc_format_decimal( $item['sale_price'] ) );
		}

		// Tax
		if ( isset( $item['tax_status'] ) ) {
			update_post_meta( $product_id, '_tax_status', wc_clean( $item['tax_status'] ) );
		}

		if ( isset( $item['tax_class'] ) ) {
			update_post_meta( $product_id, '_tax_class', wc_clean( $item['tax_class'] ) );
		}

		// Stock
		if ( isset( $item['stock_quantity'] ) && '' !== $item['stock_quantity'] ) {
			update_post_meta( $product_id, '_stock', wc_stock_amount( $item['stock_quantity'] ) );
		}

		if ( isset( $item['stock_status'] ) ) {
			update_post_meta( $product_id, '_stock_status', wc_clean( $item['stock_status'] ) );
		}

		if ( isset( $item['manage_stock'] ) ) {
			$manage_stock = wc_string_to_bool( $item['manage_stock'] );
			update_post_meta( $product_id, '_manage_stock', $manage_stock ? 'yes' : 'no' );
		}

		if ( isset( $item['backorders'] ) ) {
			update_post_meta( $product_id, '_backorders', wc_clean( $item['backorders'] ) );
		}

		// Product type
		$product_type = $item['product_type'] ?? $this->get_option( 'default_product_type', 'simple' );
		wp_set_object_terms( $product_id, $product_type, 'product_type' );

		// Downloadable and Virtual
		if ( isset( $item['downloadable'] ) ) {
			$downloadable = wc_string_to_bool( $item['downloadable'] );
			update_post_meta( $product_id, '_downloadable', $downloadable ? 'yes' : 'no' );
		}

		if ( isset( $item['virtual'] ) ) {
			$virtual = wc_string_to_bool( $item['virtual'] );
			update_post_meta( $product_id, '_virtual', $virtual ? 'yes' : 'no' );
		}

		// Dimensions
		if ( isset( $item['weight'] ) && '' !== $item['weight'] ) {
			update_post_meta( $product_id, '_weight', wc_format_decimal( $item['weight'] ) );
		}

		if ( isset( $item['length'] ) && '' !== $item['length'] ) {
			update_post_meta( $product_id, '_length', wc_format_decimal( $item['length'] ) );
		}

		if ( isset( $item['width'] ) && '' !== $item['width'] ) {
			update_post_meta( $product_id, '_width', wc_format_decimal( $item['width'] ) );
		}

		if ( isset( $item['height'] ) && '' !== $item['height'] ) {
			update_post_meta( $product_id, '_height', wc_format_decimal( $item['height'] ) );
		}

		// Shipping class
		if ( ! empty( $item['shipping_class'] ) ) {
			wp_set_object_terms( $product_id, wc_clean( $item['shipping_class'] ), 'product_shipping_class' );
		}

		// Reviews
		if ( isset( $item['review_count'] ) ) {
			update_post_meta( $product_id, '_wc_review_count', absint( $item['review_count'] ) );
		}

		if ( isset( $item['average_rating'] ) ) {
			update_post_meta( $product_id, '_wc_average_rating', wc_format_decimal( $item['average_rating'] ) );
		}

		// Featured
		if ( isset( $item['featured'] ) ) {
			$featured = wc_string_to_bool( $item['featured'] );
			update_post_meta( $product_id, '_featured', $featured ? 'yes' : 'no' );
		}

		// Visibility
		if ( isset( $item['visibility'] ) ) {
			update_post_meta( $product_id, '_visibility', wc_clean( $item['visibility'] ) );
		}

		// Total sales
		if ( isset( $item['total_sales'] ) ) {
			update_post_meta( $product_id, 'total_sales', absint( $item['total_sales'] ) );
		}

		// Import ACF fields if enabled
		if ( $this->get_option( 'import_acf', true ) ) {
			$this->import_acf_fields( $product_id, $item );
		}

		// Import Yoast SEO meta
		$this->import_yoast_meta( $product_id, $item );
	}

	/**
	 * Import product taxonomies (categories, tags)
	 *
	 * @param int   $product_id Product ID
	 * @param array $item       Product data
	 * @return void
	 */
	private function import_product_taxonomies( $product_id, $item ) {
		// Import categories
		if ( $this->get_option( 'import_categories', true ) && ! empty( $item['product_cat'] ) ) {
			$categories = array_map( 'trim', explode( ',', $item['product_cat'] ) );
			$term_ids   = [];

			foreach ( $categories as $category ) {
				if ( empty( $category ) ) {
					continue;
				}

				$term = term_exists( $category, 'product_cat' );
				if ( ! $term ) {
					$term = wp_insert_term( $category, 'product_cat' );
				}

				if ( ! is_wp_error( $term ) ) {
					$term_ids[] = $term['term_id'];
				}
			}

			if ( ! empty( $term_ids ) ) {
				wp_set_object_terms( $product_id, $term_ids, 'product_cat' );
			}
		}

		// Import tags
		if ( $this->get_option( 'import_tags', true ) && ! empty( $item['product_tag'] ) ) {
			$tags     = array_map( 'trim', explode( ',', $item['product_tag'] ) );
			$term_ids = [];

			foreach ( $tags as $tag ) {
				if ( empty( $tag ) ) {
					continue;
				}

				$term = term_exists( $tag, 'product_tag' );
				if ( ! $term ) {
					$term = wp_insert_term( $tag, 'product_tag' );
				}

				if ( ! is_wp_error( $term ) ) {
					$term_ids[] = $term['term_id'];
				}
			}

			if ( ! empty( $term_ids ) ) {
				wp_set_object_terms( $product_id, $term_ids, 'product_tag' );
			}
		}
	}

	/**
	 * Import product images
	 *
	 * @param int   $product_id Product ID
	 * @param array $item       Product data
	 * @return void
	 */
	private function import_product_images( $product_id, $item ) {
		if ( ! $this->get_option( 'import_images', true ) ) {
			return;
		}

		// Featured image
		if ( ! empty( $item['featured_image_id'] ) ) {
			set_post_thumbnail( $product_id, absint( $item['featured_image_id'] ) );
		} elseif ( ! empty( $item['featured_image_url'] ) && $this->get_option( 'download_remote_images', false ) ) {
			// Download and attach remote image
			$image_id = $this->download_image( $item['featured_image_url'], $product_id );
			if ( $image_id ) {
				set_post_thumbnail( $product_id, $image_id );
			}
		}

		// Product gallery
		if ( ! empty( $item['product_gallery'] ) ) {
			$gallery_ids = array_map( 'absint', array_filter( explode( ',', $item['product_gallery'] ) ) );
			if ( ! empty( $gallery_ids ) ) {
				update_post_meta( $product_id, '_product_image_gallery', implode( ',', $gallery_ids ) );
			}
		}
	}

	/**
	 * Download remote image and attach to product
	 *
	 * @param string $url        Image URL
	 * @param int    $product_id Product ID
	 * @return int|false Attachment ID or false on failure
	 */
	private function download_image( $url, $product_id ) {
		if ( ! function_exists( 'media_sideload_image' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}

		$image_id = media_sideload_image( $url, $product_id, null, 'id' );

		if ( is_wp_error( $image_id ) ) {
			return false;
		}

		return $image_id;
	}

	/**
	 * Import ACF fields for product
	 *
	 * @param int   $product_id Product ID
	 * @param array $item       Product data
	 * @return void
	 */
	private function import_acf_fields( $product_id, $item ) {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return;
		}

		// Get ACF field groups for products
		$field_groups = acf_get_field_groups(
			[
				'post_type' => 'product',
			]
		);

		if ( empty( $field_groups ) ) {
			return;
		}

		foreach ( $field_groups as $field_group ) {
			$fields = acf_get_fields( $field_group['key'] );

			if ( empty( $fields ) ) {
				continue;
			}

			foreach ( $fields as $field ) {
				$field_name = $field['name'];

				// Check if field value exists in import data
				if ( isset( $item[ $field_name ] ) ) {
					update_field( $field['key'], $item[ $field_name ], $product_id );
				}
			}
		}
	}

	/**
	 * Import Yoast SEO meta
	 *
	 * @param int   $product_id Product ID
	 * @param array $item       Product data
	 * @return void
	 */
	private function import_yoast_meta( $product_id, $item ) {
		$yoast_fields = [
			'_yoast_wpseo_title',
			'_yoast_wpseo_metadesc',
			'_yoast_wpseo_focuskw',
			'_yoast_wpseo_canonical',
			'_yoast_wpseo_meta-robots-noindex',
			'_yoast_wpseo_meta-robots-nofollow',
			'_yoast_wpseo_opengraph-title',
			'_yoast_wpseo_opengraph-description',
			'_yoast_wpseo_opengraph-image',
			'_yoast_wpseo_twitter-title',
			'_yoast_wpseo_twitter-description',
			'_yoast_wpseo_twitter-image',
		];

		foreach ( $yoast_fields as $field ) {
			if ( isset( $item[ $field ] ) && '' !== $item[ $field ] ) {
				update_post_meta( $product_id, $field, $item[ $field ] );
			}
		}
	}

	/**
	 * Sanitize item data
	 *
	 * @param array $item Item data
	 * @return array Sanitized data
	 */
	protected function sanitize_item( $item ) {
		if ( ! is_array( $item ) ) {
			return [];
		}

		$sanitized = [];

		foreach ( $item as $key => $value ) {
			// Skip if value is empty
			if ( '' === $value || null === $value ) {
				$sanitized[ $key ] = $value;
				continue;
			}

			// Sanitize based on field type
			switch ( $key ) {
				case 'post_content':
					$sanitized[ $key ] = wp_kses_post( $value );
					break;

				case 'post_title':
				case 'post_name':
				case 'post_excerpt':
					$sanitized[ $key ] = sanitize_text_field( $value );
					break;

				case 'ID':
				case 'post_author':
				case 'featured_image_id':
				case 'stock_quantity':
				case 'review_count':
				case 'total_sales':
					$sanitized[ $key ] = absint( $value );
					break;

				case 'regular_price':
				case 'sale_price':
				case 'weight':
				case 'length':
				case 'width':
				case 'height':
				case 'average_rating':
					$sanitized[ $key ] = wc_format_decimal( $value );
					break;

				case 'featured_image_url':
					$sanitized[ $key ] = esc_url_raw( $value );
					break;

				default:
					$sanitized[ $key ] = sanitize_text_field( $value );
					break;
			}
		}

		return $sanitized;
	}

	/**
	 * Find product by title
	 *
	 * @param string $title Product title
	 * @return int|null Product ID or null if not found
	 */
	private function find_product_by_title( $title ) {
		global $wpdb;

		$product_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'product' LIMIT 1",
				$title
			)
		);

		return $product_id ? absint( $product_id ) : null;
	}
}

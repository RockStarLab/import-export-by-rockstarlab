<?php
/**
 * WooCommerce Product Importer
 *
 * Handles importing WooCommerce products
 *
 * @package WP_AIE\Model\Import
 */

namespace WP_AIE\Model\Import;

defined( 'ABSPATH' ) || exit;

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
			'variations',
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
		$to_bool = function ( $v ) {
			if ( is_bool( $v ) ) {
				return $v;
			}
			$s = strtolower( trim( (string) $v ) );
			return in_array( $s, [ '1', 'true', 'yes', 'on' ], true );
		};

		// Map the generic UI media options to this importer's image download option.
		// (The UI uses auto_import_media / download_images across multiple importers.)
		if ( isset( $options['auto_import_media'] ) && ! isset( $options['download_remote_images'] ) ) {
			$options['download_remote_images'] = $to_bool( $options['auto_import_media'] );
		}
		if ( isset( $options['download_images'] ) && ! isset( $options['download_remote_images'] ) ) {
			$options['download_remote_images'] = $to_bool( $options['download_images'] );
		}

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

		// Import variations for variable products
		$this->import_product_variations( $product_id, $item );

		// Ensure WooCommerce lookup tables/caches are updated (SKU lookups, prices, etc.).
		$this->sync_woocommerce_product( $product_id );

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

		// Update variations for variable products
		$this->import_product_variations( $product_id, $item );

		// Ensure WooCommerce lookup tables/caches are updated (SKU lookups, prices, etc.).
		$this->sync_woocommerce_product( $product_id );

		return 'updated';
	}

	/**
	 * Sync a product through WooCommerce CRUD to update lookup tables/transients.
	 *
	 * When importing we often use direct post/meta updates. WooCommerce's
	 * `wc_get_product_id_by_sku()` and other lookups rely on the
	 * `wc_product_meta_lookup` table which is maintained by CRUD saves.
	 *
	 * @param int $product_id Product ID.
	 * @return void
	 */
	private function sync_woocommerce_product( int $product_id ): void {
		if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return;
		}

		try {
			$product->save();
		} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Best-effort: don't fail the entire import if WooCommerce validation fails.
		}

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $product_id );
		}
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
		$featured_value = null;
		if ( isset( $item['featured'] ) ) {
			$featured_value = function_exists( 'wc_string_to_bool' )
				? wc_string_to_bool( $item['featured'] )
				: in_array( strtolower( trim( (string) $item['featured'] ) ), [ '1', 'true', 'yes', 'on', 'featured' ], true );
			update_post_meta( $product_id, '_featured', $featured_value ? 'yes' : 'no' );
		}

		// Visibility
		$visibility_value = null;
		if ( isset( $item['visibility'] ) ) {
			$visibility_value = strtolower( trim( wc_clean( $item['visibility'] ) ) );
			update_post_meta( $product_id, '_visibility', $visibility_value );
		}

		// WooCommerce uses product_visibility taxonomy for featured/visibility.
		// Keep other terms intact (e.g. outofstock) and only adjust the relevant ones.
		if ( taxonomy_exists( 'product_visibility' ) && ( null !== $featured_value || null !== $visibility_value ) ) {
			$current = wp_get_object_terms( $product_id, 'product_visibility', [ 'fields' => 'slugs' ] );
			if ( ! is_wp_error( $current ) ) {
				$current = is_array( $current ) ? $current : [];
				$keep    = array_values( array_diff( $current, [ 'featured', 'exclude-from-search', 'exclude-from-catalog' ] ) );

				$desired = $keep;

				if ( null !== $featured_value && $featured_value ) {
					$desired[] = 'featured';
				}

				if ( null !== $visibility_value ) {
					if ( 'catalog' === $visibility_value ) {
						$desired[] = 'exclude-from-search';
					} elseif ( 'search' === $visibility_value ) {
						$desired[] = 'exclude-from-catalog';
					} elseif ( 'hidden' === $visibility_value ) {
						$desired[] = 'exclude-from-search';
						$desired[] = 'exclude-from-catalog';
					}
				}

				$desired = array_values( array_unique( array_filter( $desired ) ) );
				wp_set_object_terms( $product_id, $desired, 'product_visibility', false );
			}
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
						$term_id = 0;
						if ( is_array( $term ) && isset( $term['term_id'] ) ) {
							$term_id = absint( $term['term_id'] );
						} elseif ( is_int( $term ) ) {
							$term_id = absint( $term );
						}
						if ( $term_id > 0 ) {
							$term_ids[] = $term_id;
						}
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
						$term_id = 0;
						if ( is_array( $term ) && isset( $term['term_id'] ) ) {
							$term_id = absint( $term['term_id'] );
						} elseif ( is_int( $term ) ) {
							$term_id = absint( $term );
						}
						if ( $term_id > 0 ) {
							$term_ids[] = $term_id;
						}
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

		// Try to map a (source) URL to a local attachment ID.
		$resolve_attachment_id = function ( $url ) {
			return $this->resolve_attachment_id_from_url( (string) $url );
		};

		// Featured image
		$thumb_id = 0;
		if ( ! empty( $item['featured_image_url'] ) ) {
			$thumb_id = $resolve_attachment_id( $item['featured_image_url'] );
		}

		// IMPORTANT: Do not treat `featured_image_id` as a target-site attachment ID when a URL is available.
		// In cross-site imports the numeric IDs refer to the SOURCE site and can accidentally point to an
		// unrelated attachment on the target site, resulting in wrong thumbnails.
		// Only use `featured_image_id` as a fallback when the file provides no URL.
		if ( ! $thumb_id && empty( $item['featured_image_url'] ) && ! empty( $item['featured_image_id'] ) && get_post( absint( $item['featured_image_id'] ) ) ) {
			$thumb_id = absint( $item['featured_image_id'] );
		}

		if ( $thumb_id ) {
			set_post_thumbnail( $product_id, $thumb_id );
		} elseif ( ! empty( $item['featured_image_url'] ) && $this->get_option( 'download_remote_images', false ) ) {
			// Download and attach remote image
			$image_id = $this->download_image( $item['featured_image_url'], $product_id );
			if ( $image_id ) {
				set_post_thumbnail( $product_id, $image_id );
			}
		}

		// Product gallery
		if ( ! empty( $item['product_gallery'] ) ) {
			$raw_items = array_filter( array_map( 'trim', explode( ',', (string) $item['product_gallery'] ) ) );
			$gallery_ids = [];

			foreach ( $raw_items as $raw ) {
				if ( $raw === '' ) {
					continue;
				}
				if ( is_numeric( $raw ) ) {
					$id = absint( $raw );
					if ( $id > 0 && get_post( $id ) ) {
						$gallery_ids[] = $id;
					}
					continue;
				}

				$id = $resolve_attachment_id( $raw );
				if ( $id > 0 ) {
					$gallery_ids[] = $id;
				} elseif ( $this->get_option( 'download_remote_images', false ) ) {
					$dl = $this->download_image( $raw, $product_id );
					if ( $dl ) {
						$gallery_ids[] = absint( $dl );
					}
				}
			}

			$gallery_ids = array_values( array_unique( array_filter( array_map( 'absint', $gallery_ids ) ) ) );
			if ( ! empty( $gallery_ids ) ) {
				update_post_meta( $product_id, '_product_image_gallery', implode( ',', $gallery_ids ) );
			}
		}
	}

	/**
	 * Resolve an attachment ID from an absolute URL by rewriting it to the local site.
	 *
	 * @param string $url Attachment URL from the import file.
	 * @return int Attachment ID, or 0 if not found.
	 */
	private function resolve_attachment_id_from_url( string $url ): int {
		$url = trim( $url );
		if ( $url === '' ) {
			return 0;
		}

		$local_url = $this->rewrite_url_to_local_site( $url );
		$id        = absint( attachment_url_to_postid( $local_url ) );
		if ( $id > 0 ) {
			return $id;
		}

		// Fallback: try by filename against _wp_attached_file.
		$filename = wp_basename( wp_parse_url( $local_url, PHP_URL_PATH ) ?? '' );
		if ( $filename === '' ) {
			return 0;
		}

		$query = new \WP_Query(
			[
				'post_type'              => 'attachment',
				'post_status'            => 'inherit',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'orderby'                => 'ID',
				'order'                  => 'DESC',
				'meta_query'             => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Targeted fallback lookup by _wp_attached_file.
					[
						'key'     => '_wp_attached_file',
						'value'   => $filename,
						'compare' => 'LIKE',
					],
				],
			]
		);

		return ! empty( $query->posts[0] ) ? absint( $query->posts[0] ) : 0;
	}

	/**
	 * Import WooCommerce product variations (variable products).
	 *
	 * Expects a JSON array (exported by the Post_Exporter `variations` field).
	 *
	 * @param int   $product_id Parent product ID.
	 * @param array $item       Imported row data.
	 * @return void
	 */
	private function import_product_variations( int $product_id, array $item ): void {
		$raw = $item['variations'] ?? '';
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return;
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) || empty( $decoded ) ) {
			return;
		}

		// Build a map of source variation ID → existing local variation ID.
		$source_to_local      = [];
		$existing_local_var_ids = get_posts(
			[
				'post_type'      => 'product_variation',
				'post_parent'    => $product_id,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			]
		);

		foreach ( $existing_local_var_ids as $local_var_id ) {
			$orig_id = (int) get_post_meta( (int) $local_var_id, '_aie_original_post_id', true );
			if ( $orig_id ) {
				$source_to_local[ $orig_id ] = (int) $local_var_id;
			}
		}

		$processed_source_ids = [];

		foreach ( $decoded as $variation_data ) {
			if ( ! is_array( $variation_data ) ) {
				continue;
			}

			$source_var_id = absint( $variation_data['ID'] ?? 0 );

			$variation_args = [
				'post_title'  => $variation_data['post_title'] ?? '',
				'post_name'   => $variation_data['post_name'] ?? '',
				'post_status' => $variation_data['post_status'] ?? 'publish',
				'post_type'   => 'product_variation',
				'post_parent' => $product_id,
				'menu_order'  => isset( $variation_data['menu_order'] ) ? (int) $variation_data['menu_order'] : 0,
			];

			if ( $source_var_id && isset( $source_to_local[ $source_var_id ] ) ) {
				$variation_args['ID'] = (int) $source_to_local[ $source_var_id ];
				$local_var_id         = wp_update_post( $variation_args, true );
			} else {
				$local_var_id = wp_insert_post( $variation_args, true );
				if ( $local_var_id && ! is_wp_error( $local_var_id ) && $source_var_id ) {
					update_post_meta( (int) $local_var_id, '_aie_original_post_id', $source_var_id );
				}
			}

			if ( is_wp_error( $local_var_id ) || ! $local_var_id ) {
				continue;
			}

			if ( $source_var_id ) {
				$processed_source_ids[] = $source_var_id;
			}

			$var_meta = $variation_data['meta'] ?? null;
			if ( is_array( $var_meta ) ) {
				// Portable thumbnail mapping: prefer exported _thumbnail_url if present.
				if ( ! empty( $var_meta['_thumbnail_url'] ) ) {
					$thumb_id = $this->resolve_attachment_id_from_url( (string) $var_meta['_thumbnail_url'] );
					if ( $thumb_id > 0 ) {
						$var_meta['_thumbnail_id'] = $thumb_id;
					} elseif ( $this->get_option( 'download_remote_images', false ) ) {
						$dl = $this->download_image( (string) $var_meta['_thumbnail_url'], (int) $local_var_id );
						if ( $dl ) {
							$var_meta['_thumbnail_id'] = (int) $dl;
						}
					}
				}

				foreach ( $var_meta as $key => $value ) {
					if ( in_array( $key, [ '_edit_lock', '_edit_last', '_thumbnail_url' ], true ) ) {
						continue;
					}
					update_post_meta( (int) $local_var_id, (string) $key, $value );
				}
			}

			// Ensure variation lookup tables are updated (SKU/price/stock).
			if ( function_exists( 'wc_get_product' ) ) {
				$wc_var = wc_get_product( (int) $local_var_id );
				if ( $wc_var ) {
					try {
						$wc_var->save();
					} catch ( \Throwable $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
					}
				}
			}
		}

		// Delete stale local variations that no longer exist in the import data.
		foreach ( $existing_local_var_ids as $local_var_id ) {
			$orig_id = (int) get_post_meta( (int) $local_var_id, '_aie_original_post_id', true );
			if ( $orig_id && ! in_array( $orig_id, $processed_source_ids, true ) ) {
				wp_delete_post( (int) $local_var_id, true );
			}
		}

		// Recalculate the variable product's price range from the synced variations.
		if ( function_exists( 'wc_get_product' ) && class_exists( 'WC_Product_Variable' ) ) {
			$wc_product = wc_get_product( $product_id );
			if ( $wc_product && method_exists( $wc_product, 'is_type' ) && $wc_product->is_type( 'variable' ) ) {
				\WC_Product_Variable::sync( $wc_product );
			}
		}
	}

	/**
	 * Rewrite an absolute URL from another site to the current site's origin.
	 *
	 * @param string $url Raw URL from the import file.
	 * @return string URL rewritten to current site, or original if not absolute/parseable.
	 */
	private function rewrite_url_to_local_site( $url ) {
		$url = trim( (string) $url );
		if ( $url === '' ) {
			return '';
		}

		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['host'] ) ) {
			return esc_url_raw( $url );
		}

		$home        = home_url();
		$home_parsed = wp_parse_url( $home );
		if ( empty( $home_parsed['host'] ) ) {
			return esc_url_raw( $url );
		}

		$scheme = $home_parsed['scheme'] ?? 'http';
		$host   = $home_parsed['host'];
		$port   = isset( $home_parsed['port'] ) ? ':' . (int) $home_parsed['port'] : '';

		$path     = $parsed['path'] ?? '';
		$query    = isset( $parsed['query'] ) ? '?' . $parsed['query'] : '';
		$fragment = isset( $parsed['fragment'] ) ? '#' . $parsed['fragment'] : '';

		return esc_url_raw( $scheme . '://' . $host . $port . $path . $query . $fragment );
	}

	/**
	 * Download remote image and attach to product
	 *
	 * @param string $url        Image URL
	 * @param int    $product_id Product ID
	 * @return int|false Attachment ID or false on failure
	 */
	private function download_image( $url, $product_id ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp_file = download_url( $url, 30 );

		// In local/dev environments, WordPress can reject ".local"/".test" hosts as
		// "unsafe" and return "A valid URL was not provided." even though the URL
		// is reachable. Retry with `reject_unsafe_urls=false` for common dev TLDs.
		if ( is_wp_error( $tmp_file ) ) {
			$host        = wp_parse_url( $url, PHP_URL_HOST );
			$is_dev_host = is_string( $host ) && preg_match( '/\\.(local|test|localhost)$/i', $host );

			if ( $is_dev_host && 'http_request_failed' === $tmp_file->get_error_code() ) {
				$retry = $this->download_url_unrestricted( (string) $url, 30 );
				if ( ! is_wp_error( $retry ) ) {
					$tmp_file = $retry;
				}
			}
		}

		if ( is_wp_error( $tmp_file ) || ! $tmp_file || ! file_exists( $tmp_file ) ) {
			return false;
		}

		$file = [
			'name'     => basename( wp_parse_url( $url, PHP_URL_PATH ) ),
			'tmp_name' => $tmp_file,
		];

		$attachment_id = media_handle_sideload( $file, (int) $product_id );

		// Clean up temp file.
		if ( file_exists( $tmp_file ) ) {
			@wp_delete_file( $tmp_file );
		}

		if ( is_wp_error( $attachment_id ) ) {
			return false;
		}

		return (int) $attachment_id;
	}

	/**
	 * Download a URL to a temporary file without WordPress "unsafe URL" rejection.
	 *
	 * This is a fallback only for dev domains like *.local / *.test.
	 *
	 * @param string $url     Remote file URL.
	 * @param int    $timeout Timeout in seconds.
	 * @return string|\WP_Error Path to the temporary file or WP_Error.
	 */
	private function download_url_unrestricted( string $url, int $timeout ) {
		$tmp = wp_tempnam( $url );
		if ( ! $tmp ) {
			return new \WP_Error( 'aie_temp_file_failed', __( 'Could not create a temporary file for download.', 'wp-advanced-import-export' ) );
		}

		$response = wp_remote_get(
			$url,
			[
				'timeout'            => $timeout,
				'stream'             => true,
				'filename'           => $tmp,
				'reject_unsafe_urls' => false,
			]
		);

		if ( is_wp_error( $response ) ) {
			@wp_delete_file( $tmp );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			@wp_delete_file( $tmp );
			return new \WP_Error( 'aie_download_failed', sprintf( 'Download failed with HTTP %d', $code ) );
		}

		return $tmp;
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

		$product_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_title = %s AND post_type = 'product' LIMIT 1",
				$title
			)
		);

		return $product_id ? absint( $product_id ) : null;
	}
}

<?php
/**
 * Post Exporter
 *
 * Handles exporting WordPress posts, pages, and custom post types
 *
 * @package RockStarLab\ImportExport\Model\Export
 */

namespace RockStarLab\ImportExport\Model\Export;

defined( 'ABSPATH' ) || exit;

class Post_Exporter extends Abstract_Exporter {
	/**
	 * Cache of ACF field configs loaded from DB (field_key => config|null).
	 *
	 * @var array<string, array|null>
	 */
	private $acf_field_config_cache = [];

	/**
	 * Get exporter name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'posts';
	}

	/**
	 * Get exporter description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Export WordPress posts, pages, and custom post types', 'import-export-by-rockstarlab' );
	}

	/**
	 * Get supported export filters
	 *
	 * @return array
	 */
	public function get_supported_filters() {
		return [
			'post_type'     => __( 'Post type (post, page, or custom post type)', 'import-export-by-rockstarlab' ),
			'post_status'   => __( 'Post status (publish, draft, pending, etc.)', 'import-export-by-rockstarlab' ),
			'author'        => __( 'Author ID or array of IDs', 'import-export-by-rockstarlab' ), // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.
			'date_query'    => __( 'Date query parameters', 'import-export-by-rockstarlab' ), // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.
			'tax_query'     => __( 'Taxonomy query parameters', 'import-export-by-rockstarlab' ), // phpcs:ignore WordPress.DB.SlowDBQuery -- tax_query required for filtering.
			'meta_query'    => __( 'Meta query parameters', 'import-export-by-rockstarlab' ), // phpcs:ignore WordPress.DB.SlowDBQuery -- meta_query required for filtering.
			'custom_fields' => __( 'Custom field filters: array of [name, value, condition]', 'import-export-by-rockstarlab' ),
			'taxonomy'      => __( 'Taxonomy filters: array of [taxonomy, terms, condition]', 'import-export-by-rockstarlab' ),
			's'             => __( 'Search query', 'import-export-by-rockstarlab' ),
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
			'ID',
			'post_title',
			'post_content',
			'post_excerpt',
			'post_status',
			'post_type',
			'post_author',
			'post_date',
			'post_date_gmt',
			'post_modified',
			'post_modified_gmt',
			'post_name',
			'post_parent',
			'menu_order',
			'comment_status',
			'ping_status',
			'post_password',
			'guid',
			'post_meta',
			'taxonomies',
			'featured_image',
			'featured_image_id',
			'featured_image_url',
			'featured_image_title',
			'featured_image_caption',
			'author_name',
			'author_email',
			// WooCommerce: variable product variations (JSON)
			'variations',
			// WooCommerce Product fields (with underscore prefix)
			'_sku',
			'_regular_price',
			'_sale_price',
			'_tax_status',
			'_tax_class',
			'_stock',
			'_stock_status',
			'_manage_stock',
			'_backorders',
			'_product_type',
			'_downloadable',
			'_virtual',
			'_weight',
			'_length',
			'_width',
			'_height',
			'_shipping_class',
			'_product_image_gallery',
			'_wc_average_rating',
			'_wc_review_count',
			'_featured',
			'_visibility',
			'total_sales',
			// WooCommerce Product fields (alternative names without underscore)
			'sku',
			'regular_price',
			'sale_price',
			'tax_status',
			'tax_class',
			'stock_quantity',
			'stock_status',
			'manage_stock',
			'backorders',
			'downloadable',
			'virtual',
			'weight',
			'length',
			'width',
			'height',
			'shipping_class',
			'product_gallery',
			'average_rating',
			'review_count',
			'featured',
			'visibility',
			// WooCommerce taxonomies
			'product_cat',
			'product_tag',
			// Yoast SEO fields
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
	 * Get default export fields
	 *
	 * @return array
	 */
	public function get_default_fields() {
		return [
			'ID',
			'post_title',
			'post_content',
			'post_excerpt',
			'post_status',
			'post_type',
			'post_author',
			'post_date',
			'post_name',
			'post_meta',
			'taxonomies',
			'featured_image',
		];
	}

	/**
	 * Get total count of items
	 *
	 * @param array $options Optional. Export filters
	 * @return int
	 */
	public function get_count( $options = [] ) {
		// Special handling for menus - count nav_menu terms, not nav_menu_item posts
		if ( isset( $options['post_type'] ) && $options['post_type'] === 'nav_menu_item' ) {
			$term_args = [
				'taxonomy'   => 'nav_menu',
				'hide_empty' => false,
				'fields'     => 'all',
			];

			// Get all terms first
			$terms = get_terms( $term_args );

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				return 0;
			}

			// Apply filters if present
			if ( ! empty( $options['filters'] ) && is_array( $options['filters'] ) ) {
				$terms = $this->apply_menu_filters( $terms, $options['filters'] );
			}

			return count( $terms );
		}

		$query_args = $this->build_query_args( $options );

		// Remove offset and paged for count query - we want total count
		unset( $query_args['offset'] );
		unset( $query_args['paged'] );

		$query_args['fields']         = 'ids';
		$query_args['posts_per_page'] = -1;

		// Combine custom filters
		$custom_id_filters     = $query_args['_custom_id_filters'] ?? [];
		$custom_field_filters  = $query_args['_custom_field_filters'] ?? [];
		$custom_author_filters = $query_args['_custom_author_filters'] ?? [];
		unset( $query_args['_custom_id_filters'], $query_args['_custom_field_filters'], $query_args['_custom_author_filters'] );

		// Add JOIN for author filters
		if ( ! empty( $custom_author_filters ) ) {
			add_filter(
				'posts_join',
				function ( $join ) {
					global $wpdb;
					// Join with users table for author name/email filtering
					$join .= " INNER JOIN {$wpdb->users} ON {$wpdb->posts}.post_author = {$wpdb->users}.ID";
					return $join;
				},
				10,
				1
			);
		}

		// Add custom filters via posts_where hook
		if ( ! empty( $custom_id_filters ) || ! empty( $custom_field_filters ) || ! empty( $custom_author_filters ) ) {
			add_filter(
				'posts_where',
				function ( $where ) use ( $custom_id_filters, $custom_field_filters, $custom_author_filters ) {
					global $wpdb;

					// Handle ID filters
					foreach ( $custom_id_filters as $filter ) {
						$condition = $filter['condition'];
						$value     = $filter['value'];

						if ( $condition === 'greater' ) {
							$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", absint( $value ) );
						} elseif ( $condition === 'less' ) {
							$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID < %d", absint( $value ) );
						} elseif ( $condition === 'equals_or_greater' ) {
							$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID >= %d", absint( $value ) );
						} elseif ( $condition === 'equals_or_less' ) {
							$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID <= %d", absint( $value ) );
						} elseif ( $condition === 'between' ) {
							$values = array_map( 'absint', explode( ',', $value ) );
							if ( count( $values ) === 2 ) {
								$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID BETWEEN %d AND %d", $values[0], $values[1] );
							}
						}
					}

					// Handle field filters
					if ( ! empty( $custom_field_filters ) ) {
						$where .= $this->build_custom_field_where( $custom_field_filters );
					}

					// Handle author filters
					if ( ! empty( $custom_author_filters ) ) {
						$where .= $this->build_custom_author_where( $custom_author_filters );
					}

					return $where;
				},
				10,
				1
			);
		}

		$query = new \WP_Query( $query_args );

		// Remove the filters after query
		remove_all_filters( 'posts_join', 10 );
		remove_all_filters( 'posts_where', 10 );

		return $query->found_posts;
	}

	/**
	 * Get data based on export options
	 *
	 * @param array $options Export options
	 * @return array|WP_Error
	 */
	public function get_data( $options = [] ) {
		// Special handling for menus - export nav_menu terms with items
		if ( isset( $options['post_type'] ) && $options['post_type'] === 'nav_menu_item' ) {
			return $this->get_menu_data( $options );
		}

		$query_args = $this->build_query_args( $options );

		$this->log_info( 'Querying posts', $query_args );

		// Combine custom filters
		$custom_id_filters     = $query_args['_custom_id_filters'] ?? [];
		$custom_field_filters  = $query_args['_custom_field_filters'] ?? [];
		$custom_author_filters = $query_args['_custom_author_filters'] ?? [];
		unset( $query_args['_custom_id_filters'], $query_args['_custom_field_filters'], $query_args['_custom_author_filters'] );

		// Add JOIN for author filters
		if ( ! empty( $custom_author_filters ) ) {
			add_filter(
				'posts_join',
				function ( $join ) {
					global $wpdb;
					// Join with users table for author name/email filtering
					$join .= " INNER JOIN {$wpdb->users} ON {$wpdb->posts}.post_author = {$wpdb->users}.ID";
					return $join;
				},
				10,
				1
			);
		}

		// Add custom filters via posts_where hook
		if ( ! empty( $custom_id_filters ) || ! empty( $custom_field_filters ) || ! empty( $custom_author_filters ) ) {
			add_filter(
				'posts_where',
				function ( $where ) use ( $custom_id_filters, $custom_field_filters, $custom_author_filters ) {
					global $wpdb;

					// Handle ID filters
					foreach ( $custom_id_filters as $filter ) {
						$condition = $filter['condition'];
						$value     = $filter['value'];

						if ( $condition === 'greater' ) {
							$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", absint( $value ) );
						} elseif ( $condition === 'less' ) {
							$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID < %d", absint( $value ) );
						} elseif ( $condition === 'equals_or_greater' ) {
							$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID >= %d", absint( $value ) );
						} elseif ( $condition === 'equals_or_less' ) {
							$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID <= %d", absint( $value ) );
						} elseif ( $condition === 'between' ) {
							$values = array_map( 'absint', explode( ',', $value ) );
							if ( count( $values ) === 2 ) {
								$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID BETWEEN %d AND %d", $values[0], $values[1] );
							}
						}
					}

					// Handle field filters
					if ( ! empty( $custom_field_filters ) ) {
						$where .= $this->build_custom_field_where( $custom_field_filters );
					}

					// Handle author filters
					if ( ! empty( $custom_author_filters ) ) {
						$where .= $this->build_custom_author_where( $custom_author_filters );
					}

					return $where;
				},
				10,
				1
			);
		}

		$query = new \WP_Query( $query_args );

		// Remove the filters after query
		remove_all_filters( 'posts_join', 10 );
		remove_all_filters( 'posts_where', 10 );

		if ( ! $query->have_posts() ) {
			return [];
		}

		$data   = [];
		$fields = $this->get_option( 'fields', $this->get_default_fields() );

		// If fields is empty array, use default fields
		if ( empty( $fields ) ) {
			$fields = $this->get_default_fields();
		}

		// CRITICAL: Force include ID for Content Updater
		$force_include_id = $this->get_option( 'force_include_id', false );
		if ( $force_include_id && ! in_array( 'ID', $fields, true ) ) {
			// Prepend ID to fields array to ensure it's always included
			array_unshift( $fields, 'ID' );

			// IMPORTANT: Also update options['fields'] so select_fields() doesn't remove ID
			$this->options['fields'] = $fields;
		}

		while ( $query->have_posts() ) {
			$query->the_post();
			$post = get_post();

			$item   = $this->prepare_post_data( $post, $fields );
			$data[] = $item;
		}

		wp_reset_postdata();

		return $data;
	}

	/**
	 * Build WP_Query arguments from options
	 *
	 * @param array $options Export options
	 * @return array Query arguments
	 */
	protected function build_query_args( $options ) {
		if ( ! empty( $options['taxonomy'] ) ) {
		}

		$post_type = $options['post_type'] ?? 'any';

		// Map virtual content-type identifiers to real WordPress post_types
		$post_type_map = [
			'woo_product' => 'product',
			'woo_order'   => 'shop_order',
			'woo_coupon'  => 'shop_coupon',
		];
		if ( isset( $post_type_map[ $post_type ] ) ) {
			$post_type = $post_type_map[ $post_type ];
		}

		$args = [
			'post_type'      => $post_type,
			'post_status'    => $options['post_status'] ?? 'any',
			'posts_per_page' => $options['limit'] ?? -1,
			'offset'         => $options['offset'] ?? 0,
			'orderby'        => $options['orderby'] ?? 'date',
			'order'          => $options['order'] ?? 'DESC',
		];

		// When querying products, exclude variations (child post_type = product_variation)
		if ( $post_type === 'product' ) {
			$args['post_parent'] = 0; // Only top-level products, no variations
		}

		// Author filter
		if ( ! empty( $options['author'] ) ) {
			$args['author'] = is_array( $options['author'] ) ? implode( ',', $options['author'] ) : $options['author'];
		}

		// Search query
		if ( ! empty( $options['s'] ) ) {
			$args['s'] = $options['s'];
		}

		// Date query
		if ( ! empty( $options['date_query'] ) ) {
			$args['date_query'] = $options['date_query'];
		}

		// Tax query // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.
		if ( ! empty( $options['tax_query'] ) ) {
			$args['tax_query'] = $options['tax_query']; // phpcs:ignore WordPress.DB.SlowDBQuery -- tax_query required for filtering.
		}

		// Meta query // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.
		if ( ! empty( $options['meta_query'] ) ) {
			$args['meta_query'] = $options['meta_query']; // phpcs:ignore WordPress.DB.SlowDBQuery -- meta_query required for filtering.
		}

		// Custom field filters
		if ( ! empty( $options['custom_fields'] ) && is_array( $options['custom_fields'] ) ) {
			$this->apply_custom_field_filters( $args, $options['custom_fields'] );
		}

		// Taxonomy filters
		if ( ! empty( $options['taxonomy'] ) && is_array( $options['taxonomy'] ) ) {
			$this->apply_taxonomy_filters( $args, $options['taxonomy'] );
		}

		// Process dynamic filters
		if ( ! empty( $options['filters'] ) && is_array( $options['filters'] ) ) {
			$this->apply_dynamic_filters( $args, $options['filters'] );
		}

		return $args;
	}

	/**
	 * Apply custom field (meta) filters to query args
	 *
	 * @param array $args    Query arguments (by reference)
	 * @param array $filters Custom field filters
	 *                       Format: [
	 *                           [
	 *                               'name' => 'field_name',
	 *                               'value' => 'field_value',
	 *                               'condition' => 'equals|not_equals|contains|not_contains|...'
	 *                           ]
	 *                       ]
	 */
	protected function apply_custom_field_filters( &$args, $filters ) {
		if ( empty( $filters ) || ! is_array( $filters ) ) {
			return;
		}

		// Initialize meta_query if not exists // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.
		if ( ! isset( $args['meta_query'] ) ) {
			$args['meta_query'] = []; // phpcs:ignore WordPress.DB.SlowDBQuery -- meta_query required for filtering.
		}

		foreach ( $filters as $filter ) {
			if ( empty( $filter['name'] ) || ! isset( $filter['condition'] ) ) {
				continue;
			}

			$name      = sanitize_text_field( $filter['name'] );
			$condition = $filter['condition'];
			$value     = $filter['value'] ?? '';

			// Convert condition to meta compare
			$meta_condition = $this->convert_condition_to_meta_compare( $condition );

			if ( ! $meta_condition ) {
				continue;
			}

			$meta_query_item = [
				'key'     => $name,
				'compare' => $meta_condition,
			];

			// Add value only if condition requires it
			if ( ! in_array( $condition, [ 'is_empty', 'is_not_empty' ], true ) ) {
				// For IN and NOT IN, value should be an array
				if ( in_array( $condition, [ 'in', 'not_in' ], true ) ) {
					$values                   = array_map(
						function ( $v ) {
							$v = trim( $v );
							// Remove surrounding quotes if present
							return trim( $v, '\'"' );
						},
						is_array( $value ) ? $value : explode( ',', $value )
					);
					$meta_query_item['value'] = array_filter( $values ); // Remove empty values
				} else {
					$meta_query_item['value'] = $value;
				}
			}

			$args['meta_query'][] = $meta_query_item;
		}
	}

	/**
	 * Apply taxonomy filters to query args
	 *
	 * @param array $args    Query arguments (by reference)
	 * @param array $filters Taxonomy filters
	 *                       Format: [
	 *                           [
	 *                               'taxonomy' => 'category',
	 *                               'terms' => ['term1', 'term2'] or 'term1,term2',
	 *                               'condition' => 'in|not_in|and|or'
	 *                           ]
	 *                       ]
	 */
	protected function apply_taxonomy_filters( &$args, $filters ) {

		if ( empty( $filters ) || ! is_array( $filters ) ) {
			return;
		}

		// Initialize tax_query if not exists // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.
		if ( ! isset( $args['tax_query'] ) ) {
			$args['tax_query'] = []; // phpcs:ignore WordPress.DB.SlowDBQuery -- tax_query required for filtering.
		}

		foreach ( $filters as $filter ) {
			if ( empty( $filter['taxonomy'] ) || empty( $filter['terms'] ) ) {
				continue;
			}

			$taxonomy  = sanitize_text_field( $filter['taxonomy'] );
			$terms     = $filter['terms'];
			$condition = $filter['condition'] ?? 'in';

			// Ensure terms is an array
			if ( ! is_array( $terms ) ) {
				$terms = array_map( 'trim', explode( ',', $terms ) );
			}

			// Clean up terms
			$terms = array_map( 'sanitize_text_field', $terms );
			$terms = array_filter( $terms ); // Remove empty values

			if ( empty( $terms ) ) {
				continue;
			}

			// Map condition to operator
			$operator = 'IN';
			if ( $condition === 'not_in' ) {
				$operator = 'NOT IN';
			} elseif ( $condition === 'and' || $condition === 'AND' ) {
				$operator = 'AND';
			}

			$tax_query_item = [
				'taxonomy' => $taxonomy,
				'field'    => 'slug',
				'terms'    => $terms,
				'operator' => $operator,
			];

			$args['tax_query'][] = $tax_query_item;
		}
	}

	/**
	 * Apply dynamic filters to query args
	 *
	 * @param array $args    Query arguments (by reference)
	 * @param array $filters Dynamic filters
	 */
	protected function apply_dynamic_filters( &$args, $filters ) {
		$meta_query = $args['meta_query'] ?? [];

		foreach ( $filters as $filter ) {
			if ( empty( $filter['field'] ) || empty( $filter['condition'] ) ) {
				continue;
			}

			$field     = $filter['field'];
			$condition = $filter['condition'];
			$value     = $filter['value'] ?? '';

			// Handle post_type field
			if ( $field === 'post_type' ) {
				if ( ! empty( $value ) ) {
					// If specific post type is selected, override the default
					$args['post_type'] = sanitize_text_field( $value );
				}
				continue;
			}

			// Handle WooCommerce product fields (convert to meta fields with underscore prefix)
			$woo_product_fields = [
				'sku'            => '_sku',
				'regular_price'  => '_regular_price',
				'sale_price'     => '_sale_price',
				'stock_quantity' => '_stock',
				'stock_status'   => '_stock_status',
				'manage_stock'   => '_manage_stock',
				'total_sales'    => 'total_sales',
				'weight'         => '_weight',
				'length'         => '_length',
				'width'          => '_width',
				'height'         => '_height',
				'price'          => '_price',
			];

			if ( isset( $woo_product_fields[ $field ] ) ) {
				$field = $woo_product_fields[ $field ]; // Convert to actual meta key
				// Continue processing as meta field below
			}

			// Handle featured as product_visibility taxonomy
			if ( $field === 'featured' ) { // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.
				if ( ! isset( $args['tax_query'] ) ) {
					$args['tax_query'] = []; // phpcs:ignore WordPress.DB.SlowDBQuery -- tax_query required for filtering.
				}

				// Featured is a term in product_visibility taxonomy
				if ( $condition === 'equals' ) {
					// Check if value indicates featured (yes, true, 1, featured)
					$is_featured = in_array( strtolower( $value ), [ 'yes', 'true', '1', 'featured' ], true );
					if ( $is_featured ) {
						$args['tax_query'][] = [
							'taxonomy' => 'product_visibility',
							'field'    => 'slug',
							'terms'    => 'featured',
							'operator' => 'IN',
						];
					} else {
						$args['tax_query'][] = [
							'taxonomy' => 'product_visibility',
							'field'    => 'slug',
							'terms'    => 'featured',
							'operator' => 'NOT IN',
						];
					}
				} elseif ( $condition === 'not_equals' ) {
					$is_featured = in_array( strtolower( $value ), [ 'yes', 'true', '1', 'featured' ], true );
					if ( $is_featured ) {
						$args['tax_query'][] = [
							'taxonomy' => 'product_visibility',
							'field'    => 'slug',
							'terms'    => 'featured',
							'operator' => 'NOT IN',
						];
					} else {
						$args['tax_query'][] = [
							'taxonomy' => 'product_visibility',
							'field'    => 'slug',
							'terms'    => 'featured',
							'operator' => 'IN',
						];
					}
				}
				continue;
			}

			// Handle visibility as product_visibility taxonomy
			if ( $field === 'visibility' ) { // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.
				if ( ! isset( $args['tax_query'] ) ) {
					$args['tax_query'] = []; // phpcs:ignore WordPress.DB.SlowDBQuery -- tax_query required for filtering.
				}

				// Visibility values: visible, catalog, search, hidden
				// visible = no terms, catalog = exclude-from-search, search = exclude-from-catalog, hidden = both
				$visibility_map = [
					'visible' => [],
					'catalog' => [ 'exclude-from-search' ],
					'search'  => [ 'exclude-from-catalog' ],
					'hidden'  => [ 'exclude-from-catalog', 'exclude-from-search' ],
				];

				$visibility_value = strtolower( $value );

				if ( isset( $visibility_map[ $visibility_value ] ) ) {
					$terms = $visibility_map[ $visibility_value ];

					if ( empty( $terms ) ) {
						// Visible = no visibility terms
						if ( $condition === 'equals' ) {
							$args['tax_query'][] = [
								'taxonomy' => 'product_visibility',
								'field'    => 'slug',
								'terms'    => [ 'exclude-from-catalog', 'exclude-from-search' ],
								'operator' => 'NOT IN',
							];
						}
					} else {
						// Has specific visibility terms
						if ( $condition === 'equals' ) {
							$args['tax_query'][] = [
								'taxonomy' => 'product_visibility',
								'field'    => 'slug',
								'terms'    => $terms,
								'operator' => 'IN',
							];
						} elseif ( $condition === 'not_equals' ) {
							$args['tax_query'][] = [
								'taxonomy' => 'product_visibility',
								'field'    => 'slug',
								'terms'    => $terms,
								'operator' => 'NOT IN',
							];
						}
					}
				}
				continue;
			}

			// Handle product_type as taxonomy
			if ( $field === 'product_type' ) {
				// product_type is actually a taxonomy in WooCommerce // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.
				if ( ! isset( $args['tax_query'] ) ) {
					$args['tax_query'] = []; // phpcs:ignore WordPress.DB.SlowDBQuery -- tax_query required for filtering.
				}

				if ( $condition === 'equals' ) {
					$args['tax_query'][] = [
						'taxonomy' => 'product_type',
						'field'    => 'slug',
						'terms'    => sanitize_title( $value ),
						'operator' => 'IN',
					];
				} elseif ( $condition === 'not_equals' ) {
					$args['tax_query'][] = [
						'taxonomy' => 'product_type',
						'field'    => 'slug',
						'terms'    => sanitize_title( $value ),
						'operator' => 'NOT IN',
					];
				} elseif ( $condition === 'in' ) {
					$term_values         = array_map( 'trim', explode( ',', $value ) );
					$term_slugs          = array_map( 'sanitize_title', $term_values );
					$args['tax_query'][] = [
						'taxonomy' => 'product_type',
						'field'    => 'slug',
						'terms'    => $term_slugs,
						'operator' => 'IN',
					];
				} elseif ( $condition === 'not_in' ) {
					$term_values         = array_map( 'trim', explode( ',', $value ) );
					$term_slugs          = array_map( 'sanitize_title', $term_values );
					$args['tax_query'][] = [
						'taxonomy' => 'product_type',
						'field'    => 'slug',
						'terms'    => $term_slugs,
						'operator' => 'NOT IN',
					];
				}
				continue;
			}           // Handle WooCommerce order fields
			$woo_order_fields = [
				'order_number'   => '_order_number',
				'order_status'   => 'post_status', // This is actually post_status
				'order_key'      => '_order_key',
				'order_total'    => '_order_total',
				'order_subtotal' => '_order_subtotal',
				'order_tax'      => '_order_tax',
				'order_shipping' => '_order_shipping',
				'payment_method' => '_payment_method',
				'customer_id'    => '_customer_user',
			];

			if ( isset( $woo_order_fields[ $field ] ) ) {
				$field = $woo_order_fields[ $field ]; // Convert to actual meta key
				// Continue processing as meta field below
			}

			// Handle specific post fields
			if ( $field === 'ID' ) {
				// ID filtering — accumulate so multiple filters OR together
				if ( $condition === 'equals' ) {
					$args['post__in'] = array_merge( $args['post__in'] ?? [], [ absint( $value ) ] ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- post__not_in required for correct filtering.
				} elseif ( $condition === 'not_equals' ) {
					$args['post__not_in'] = array_merge( $args['post__not_in'] ?? [], [ absint( $value ) ] ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- post__not_in required for correct export filtering.
				} elseif ( $condition === 'in' ) {
					$new_ids          = array_map( 'absint', array_map( 'trim', explode( ',', $value ) ) );
					$args['post__in'] = array_merge( $args['post__in'] ?? [], $new_ids ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- post__not_in required for correct filtering.
				} elseif ( $condition === 'not_in' ) {
					$new_ids              = array_map( 'absint', array_map( 'trim', explode( ',', $value ) ) );
					$args['post__not_in'] = array_merge( $args['post__not_in'] ?? [], $new_ids ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- post__not_in required for correct export filtering.
				} elseif ( $condition === 'is_empty' ) {
					// ID cannot be empty - return no results
					$args['post__in'] = [ 0 ];
				} elseif ( $condition === 'is_not_empty' ) {
					// ID is always not empty - this condition is always true, no filter needed
					// Do nothing, return all posts
				} elseif ( in_array( $condition, [ 'greater', 'less', 'equals_or_greater', 'equals_or_less', 'between' ], true ) ) {
					// For numeric comparisons on ID, we need to use a custom WHERE clause
					// Store the condition in a temporary property to be used in posts_where filter
					if ( ! isset( $args['_custom_id_filters'] ) ) {
						$args['_custom_id_filters'] = [];
					}
					$args['_custom_id_filters'][] = [
						'condition' => $condition,
						'value'     => $value,
					];
				}
				continue;
			}

			if ( $field === 'post_author' ) {
				$args['author'] = absint( $value );
				continue;
			}

			// Handle author_name and author_email fields (need JOIN with users table)
			if ( $field === 'author_name' || $field === 'author_email' ) {
				// Store condition for custom WHERE clause with JOIN
				if ( ! isset( $args['_custom_author_filters'] ) ) {
					$args['_custom_author_filters'] = [];
				}
				$args['_custom_author_filters'][] = [
					'field'     => $field,
					'condition' => $condition,
					'value'     => $value,
				];
				continue;
			}

			if ( $field === 'post_parent' ) {
				$args['post_parent'] = absint( $value );
				continue;
			}

			if ( $field === 'post_status' ) {
				$args['post_status'] = sanitize_text_field( $value );
				continue;
			}

			// For other post fields that need custom SQL (like is_empty, contains, etc.)
			$post_fields = [ 'post_title', 'post_content', 'post_excerpt', 'post_date', 'post_modified', 'post_name', 'comment_status' ];
			if ( in_array( $field, $post_fields, true ) ) {
				// Store condition for custom WHERE clause
				if ( ! isset( $args['_custom_field_filters'] ) ) {
					$args['_custom_field_filters'] = [];
				}
				$args['_custom_field_filters'][] = [
					'field'     => $field,
					'condition' => $condition,
					'value'     => $value,
				];
				continue;
			}

			// Handle taxonomy filters (categories, tags, etc.)
			$taxonomy_map = [
				'categories'    => 'category',
				'tags'          => 'post_tag',
				'product_cat'   => 'product_cat',
				'product_tag'   => 'product_tag',
				'product_brand' => 'product_brand',
			];

			if ( isset( $taxonomy_map[ $field ] ) ) {
				$taxonomy = $taxonomy_map[ $field ];                // Initialize tax_query if not exists // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.
				if ( ! isset( $args['tax_query'] ) ) {
					$args['tax_query'] = []; // phpcs:ignore WordPress.DB.SlowDBQuery -- tax_query required for filtering.
				}

				// Handle different conditions for taxonomies
				if ( $condition === 'equals' || $condition === 'contains' ) {
					// Single term by slug or name
					$args['tax_query'][] = [
						'taxonomy' => $taxonomy,
						'field'    => 'slug',
						'terms'    => sanitize_title( $value ),
						'operator' => 'IN',
					];
				} elseif ( $condition === 'not_equals' || $condition === 'not_contains' ) {
					// Exclude term
					$args['tax_query'][] = [
						'taxonomy' => $taxonomy,
						'field'    => 'slug',
						'terms'    => sanitize_title( $value ),
						'operator' => 'NOT IN',
					];
				} elseif ( $condition === 'in' ) {
					// Multiple terms
					$term_values         = array_map( 'trim', explode( ',', $value ) );
					$term_slugs          = array_map( 'sanitize_title', $term_values );
					$args['tax_query'][] = [
						'taxonomy' => $taxonomy,
						'field'    => 'slug',
						'terms'    => $term_slugs,
						'operator' => 'IN',
					];
				} elseif ( $condition === 'not_in' ) {
					// Exclude multiple terms
					$term_values         = array_map( 'trim', explode( ',', $value ) );
					$term_slugs          = array_map( 'sanitize_title', $term_values );
					$args['tax_query'][] = [
						'taxonomy' => $taxonomy,
						'field'    => 'slug',
						'terms'    => $term_slugs,
						'operator' => 'NOT IN',
					];
				} elseif ( $condition === 'is_empty' ) {
					// Posts without this taxonomy OR with only Uncategorized category
					if ( $taxonomy === 'category' ) {
						// Get default category ID (usually "Uncategorized")
						$default_category = get_option( 'default_category' );

						// For categories, include posts without categories OR with only default category
						$args['tax_query'][] = [
							'relation' => 'OR',
							[
								'taxonomy' => $taxonomy,
								'operator' => 'NOT EXISTS',
							],
							[
								'taxonomy' => $taxonomy,
								'field'    => 'term_id',
								'terms'    => $default_category,
								'operator' => 'IN',
							],
						];
					} else {
						// For other taxonomies, just check if not exists
						$args['tax_query'][] = [
							'taxonomy' => $taxonomy,
							'operator' => 'NOT EXISTS',
						];
					}
				} elseif ( $condition === 'is_not_empty' ) {
					// Posts with any term in this taxonomy (excluding Uncategorized for categories)
					if ( $taxonomy === 'category' ) {
						// Get default category ID (usually "Uncategorized")
						$default_category    = get_option( 'default_category' );                   // For categories, exclude default category
						$args['tax_query'][] = [
							'relation' => 'AND',
							[
								'taxonomy' => $taxonomy,
								'operator' => 'EXISTS',
							],
							[
								'taxonomy' => $taxonomy,
								'field'    => 'term_id',
								'terms'    => $default_category,
								'operator' => 'NOT IN',
							],
						];
					} else {
						// For other taxonomies, just check if exists
						$args['tax_query'][] = [
							'taxonomy' => $taxonomy,
							'operator' => 'EXISTS',
						];
					}
				}

				continue;
			}

			// Handle as meta field
			$meta_condition = $this->convert_condition_to_meta_compare( $condition );

			if ( $meta_condition ) {
				$meta_query_item = [
					'key'     => $field,
					'compare' => $meta_condition,
				];

				// Add value only if condition requires it
				if ( ! in_array( $condition, [ 'is_empty', 'is_not_empty' ], true ) ) {
					// For IN and NOT IN, value should be an array
					if ( in_array( $condition, [ 'in', 'not_in' ], true ) ) {
						$values                   = array_map(
							function ( $v ) {
								$v = trim( $v );
								// Remove surrounding quotes if present
								return trim( $v, '\'"' );
							},
							explode( ',', $value )
						);
						$meta_query_item['value'] = array_filter( $values ); // Remove empty values
					} else {
						$meta_query_item['value'] = $value;
					}
				}

				$meta_query[] = $meta_query_item;
			}
		}
 // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.
		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = $meta_query; // phpcs:ignore WordPress.DB.SlowDBQuery -- meta_query required for filtering.
		}
	}

	/**
	 * Convert filter condition to WP meta compare operator
	 *
	 * @param string $condition Filter condition
	 * @return string|null Meta compare operator
	 */
	protected function convert_condition_to_meta_compare( $condition ) {
		$map = [
			'equals'            => '=',
			'not_equals'        => '!=',
			'greater'           => '>',
			'less'              => '<',
			'equals_or_greater' => '>=',
			'equals_or_less'    => '<=',
			'contains'          => 'LIKE',
			'not_contains'      => 'NOT LIKE',
			'is_empty'          => 'NOT EXISTS',
			'is_not_empty'      => 'EXISTS',
			'in'                => 'IN',
			'not_in'            => 'NOT IN',
		];

		return $map[ $condition ] ?? null;
	}

	/**
	 * Normalize a date value to YYYY-MM-DD format
	 *
	 * Accepts any format that PHP's strtotime() understands, including:
	 * - MM/DD/YYYY (from jQuery UI datepicker)
	 * - DD/MM/YYYY
	 * - YYYY-MM-DD (already correct)
	 * - YYYY-MM-DD HH:MM:SS (strips time)
	 *
	 * @param string $value Raw date value from user input
	 * @return string Date formatted as YYYY-MM-DD, or original value if parsing fails
	 */
	protected function normalize_date_value( $value ) {
		if ( empty( $value ) ) {
			return $value;
		}

		$value = trim( $value );

		// Already YYYY-MM-DD (with optional time part) — just return the date portion
		if ( preg_match( '/^(\d{4}-\d{2}-\d{2})/', $value, $m ) ) {
			return $m[1];
		}

		// Try strtotime for any other format (e.g. MM/DD/YYYY from jQuery datepicker)
		$ts = strtotime( $value );
		if ( false !== $ts ) {
			return gmdate( 'Y-m-d', $ts );
		}

		// Fallback: return as-is and let MySQL deal with it
		return $value;
	}

	/**
	 * Build WHERE clause for custom field filters
	 *
	 * @param array $filters Custom field filters
	 * @return string WHERE clause
	 */
	protected function build_custom_field_where( $filters ) {
		global $wpdb;
		$where = '';

		foreach ( $filters as $filter ) {
			$field     = $filter['field'];
			$condition = $filter['condition'];
			$value     = $filter['value'] ?? '';

			// Date fields that need special handling
			$date_fields   = [ 'post_date', 'post_modified', 'post_date_gmt', 'post_modified_gmt' ];
			$is_date_field = in_array( $field, $date_fields, true );

			// Normalize date value to YYYY-MM-DD before using in SQL
			if ( $is_date_field ) {
				$value = $this->normalize_date_value( $value );
			}

			switch ( $condition ) {
				case 'equals':
					if ( $is_date_field ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct DB query required here.
						// For date fields, compare only the date part (ignore time)
						$where .= $wpdb->prepare( " AND DATE({$wpdb->posts}.{$field}) = %s", $value ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct DB query required here.
					} else {
						$where .= $wpdb->prepare( " AND {$wpdb->posts}.{$field} = %s", $value ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table/column name is controlled, not user input.
					}
					break;
				case 'not_equals':
					if ( $is_date_field ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct DB query required here.
						// For date fields, compare only the date part (ignore time)
						$where .= $wpdb->prepare( " AND DATE({$wpdb->posts}.{$field}) != %s", $value ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct DB query required here.
					} else {
						$where .= $wpdb->prepare( " AND {$wpdb->posts}.{$field} != %s", $value ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table/column name is controlled, not user input.
					}
					break;
				case 'in':
					// Split by comma and prepare IN clause
					$values = array_map(
						function ( $v ) {
							$v = trim( $v );
							// Remove surrounding quotes if present
							return trim( $v, '\'"' );
						},
						explode( ',', $value )
					);
					$values = array_filter( $values ); // Remove empty values
					if ( ! empty( $values ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Direct DB query required here.
						$placeholders = implode( ', ', array_fill( 0, count( $values ), '%s' ) );
						$where       .= $wpdb->prepare( " AND {$wpdb->posts}.{$field} IN ($placeholders)", $values ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table/column name is controlled, not user input. Placeholder handled correctly.
					}
					break;
				case 'not_in':
					// Split by comma and prepare NOT IN clause
					$values = array_map(
						function ( $v ) {
							$v = trim( $v );
							// Remove surrounding quotes if present
							return trim( $v, '\'"' );
						},
						explode( ',', $value )
					);
					$values = array_filter( $values ); // Remove empty values
					if ( ! empty( $values ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Direct DB query required here.
						$placeholders = implode( ', ', array_fill( 0, count( $values ), '%s' ) );
						$where       .= $wpdb->prepare( " AND {$wpdb->posts}.{$field} NOT IN ($placeholders)", $values ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table/column name is controlled, not user input. Placeholder handled correctly.
					}
					break; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct DB query required here.
				case 'contains':
					$where .= $wpdb->prepare( " AND {$wpdb->posts}.{$field} LIKE %s", '%' . $wpdb->esc_like( $value ) . '%' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table/column name is controlled, not user input.
					break; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct DB query required here.
				case 'not_contains':
					$where .= $wpdb->prepare( " AND {$wpdb->posts}.{$field} NOT LIKE %s", '%' . $wpdb->esc_like( $value ) . '%' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table/column name is controlled, not user input.
					break;
				case 'is_empty':
					$where .= " AND ({$wpdb->posts}.{$field} IS NULL OR {$wpdb->posts}.{$field} = '')";
					break;
				case 'is_not_empty':
					$where .= " AND ({$wpdb->posts}.{$field} IS NOT NULL AND {$wpdb->posts}.{$field} != '')";
					break;
				case 'greater':
					if ( $is_date_field ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct DB query required here.
						// For date fields, compare only the date part
						$where .= $wpdb->prepare( " AND DATE({$wpdb->posts}.{$field}) > %s", $value ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct DB query required here.
					} else {
						$where .= $wpdb->prepare( " AND {$wpdb->posts}.{$field} > %s", $value ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table/column name is controlled, not user input.
					}
					break;
				case 'less':
					if ( $is_date_field ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct DB query required here.
						// For date fields, compare only the date part
						$where .= $wpdb->prepare( " AND DATE({$wpdb->posts}.{$field}) < %s", $value ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct DB query required here.
					} else {
						$where .= $wpdb->prepare( " AND {$wpdb->posts}.{$field} < %s", $value ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table/column name is controlled, not user input.
					}
					break;
				case 'equals_or_greater':
					if ( $is_date_field ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct DB query required here.
						// For date fields, compare only the date part
						$where .= $wpdb->prepare( " AND DATE({$wpdb->posts}.{$field}) >= %s", $value ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct DB query required here.
					} else {
						$where .= $wpdb->prepare( " AND {$wpdb->posts}.{$field} >= %s", $value ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table/column name is controlled, not user input.
					}
					break;
				case 'equals_or_less':
					if ( $is_date_field ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct DB query required here.
						// For date fields, compare only the date part
						$where .= $wpdb->prepare( " AND DATE({$wpdb->posts}.{$field}) <= %s", $value ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct DB query required here.
					} else {
						$where .= $wpdb->prepare( " AND {$wpdb->posts}.{$field} <= %s", $value ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table/column name is controlled, not user input.
					}
					break;
			}
		}

		return $where;
	}

	/**
	 * Build WHERE clause for custom author filters (author_name, author_email)
	 *
	 * @param array $filters Custom author filters
	 * @return string WHERE clause
	 */
	protected function build_custom_author_where( $filters ) {
		global $wpdb;
		$where = '';

		foreach ( $filters as $filter ) {
			$field     = $filter['field'];
			$condition = $filter['condition'];
			$value     = $filter['value'] ?? '';

			// Map field to users table column
			$user_field = $field === 'author_name' ? 'display_name' : 'user_email';

			switch ( $condition ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct DB query required here.
				case 'equals':
					$where .= $wpdb->prepare( " AND {$wpdb->users}.{$user_field} = %s", $value ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table/column name is controlled, not user input.
					break; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct DB query required here.
				case 'not_equals':
					$where .= $wpdb->prepare( " AND {$wpdb->users}.{$user_field} != %s", $value ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table/column name is controlled, not user input.
					break;
				case 'in':
					// Split by comma and prepare IN clause
					$values = array_map(
						function ( $v ) {
							$v = trim( $v );
							// Remove surrounding quotes if present
							return trim( $v, '\'"' );
						},
						explode( ',', $value )
					);
					$values = array_filter( $values ); // Remove empty values
					if ( ! empty( $values ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Direct DB query required here.
						$placeholders = implode( ', ', array_fill( 0, count( $values ), '%s' ) );
						$where       .= $wpdb->prepare( " AND {$wpdb->users}.{$user_field} IN ($placeholders)", $values ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table/column name is controlled, not user input. Placeholder handled correctly.
					}
					break;
				case 'not_in':
					// Split by comma and prepare NOT IN clause
					$values = array_map(
						function ( $v ) {
							$v = trim( $v );
							// Remove surrounding quotes if present
							return trim( $v, '\'"' );
						},
						explode( ',', $value )
					);
					$values = array_filter( $values ); // Remove empty values
					if ( ! empty( $values ) ) { // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Direct DB query required here.
						$placeholders = implode( ', ', array_fill( 0, count( $values ), '%s' ) );
						$where       .= $wpdb->prepare( " AND {$wpdb->users}.{$user_field} NOT IN ($placeholders)", $values ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- Table/column name is controlled, not user input. Placeholder handled correctly.
					}
					break; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct DB query required here.
				case 'contains':
					$where .= $wpdb->prepare( " AND {$wpdb->users}.{$user_field} LIKE %s", '%' . $wpdb->esc_like( $value ) . '%' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table/column name is controlled, not user input.
					break; // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct DB query required here.
				case 'not_contains':
					$where .= $wpdb->prepare( " AND {$wpdb->users}.{$user_field} NOT LIKE %s", '%' . $wpdb->esc_like( $value ) . '%' ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table/column name is controlled, not user input.
					break;
				case 'is_empty':
					$where .= " AND ({$wpdb->users}.{$user_field} IS NULL OR {$wpdb->users}.{$user_field} = '')";
					break;
				case 'is_not_empty':
					$where .= " AND ({$wpdb->users}.{$user_field} IS NOT NULL AND {$wpdb->users}.{$user_field} != '')";
					break;
			}
		}

		return $where;
	}

	/**
	 * Prepare post data for export
	 *
	 * @param \WP_Post $post   Post object
	 * @param array    $fields Fields to include
	 * @return array
	 */
	protected function prepare_post_data( $post, $fields ) {
		$data = [];

		// Basic fields
		$basic_fields = [
			'ID',
			'post_title',
			'post_content',
			'post_excerpt',
			'post_status',
			'post_type',
			'post_author',
			'post_date',
			'post_date_gmt',
			'post_modified',
			'post_modified_gmt',
			'post_name',
			'post_parent',
			'menu_order',
			'comment_status',
			'ping_status',
			'post_password',
			'guid',
		];

		foreach ( $basic_fields as $field ) {
			// Include field if it's in the fields list
			if ( in_array( $field, $fields, true ) ) {
				$data[ $field ] = $post->$field;
			}
		}

		// Author fields
		if ( in_array( 'author_name', $fields, true ) ) {
			$author              = get_userdata( $post->post_author );
			$data['author_name'] = $author ? $author->display_name : '';
		}

		if ( in_array( 'author_email', $fields, true ) ) {
			$author               = get_userdata( $post->post_author );
			$data['author_email'] = $author ? $author->user_email : '';
		}

		// Post meta
		if ( in_array( 'post_meta', $fields, true ) ) {
			$data['post_meta'] = $this->get_post_meta( $post->ID );
		}

		// Taxonomies
		if ( in_array( 'taxonomies', $fields, true ) ) {
			$data['taxonomies'] = $this->get_post_taxonomies( $post->ID, $post->post_type );
		}

		// Featured image
		if ( in_array( 'featured_image', $fields, true ) ) {
			$data['featured_image'] = $this->get_featured_image( $post->ID );
		}

		// Individual featured image fields
		$featured_image_fields = [ 'featured_image_id', 'featured_image_url', 'featured_image_title', 'featured_image_caption' ];
		$has_featured_fields   = array_intersect( $featured_image_fields, $fields );

		if ( ! empty( $has_featured_fields ) ) {
			$thumbnail_id = get_post_thumbnail_id( $post->ID );

			if ( $thumbnail_id ) {
				if ( in_array( 'featured_image_id', $fields, true ) ) {
					$data['featured_image_id'] = $thumbnail_id;
				}

				if ( in_array( 'featured_image_url', $fields, true ) ) {
					$data['featured_image_url'] = wp_get_attachment_url( $thumbnail_id );
				}

				if ( in_array( 'featured_image_title', $fields, true ) ) {
					$image                        = get_post( $thumbnail_id );
					$data['featured_image_title'] = $image ? $image->post_title : '';
				}

				if ( in_array( 'featured_image_caption', $fields, true ) ) {
					$image                          = get_post( $thumbnail_id );
					$data['featured_image_caption'] = $image ? $image->post_excerpt : '';
				}
			} else {
				// No featured image - set empty values
				if ( in_array( 'featured_image_id', $fields, true ) ) {
					$data['featured_image_id'] = '';
				}
				if ( in_array( 'featured_image_url', $fields, true ) ) {
					$data['featured_image_url'] = '';
				}
				if ( in_array( 'featured_image_title', $fields, true ) ) {
					$data['featured_image_title'] = '';
				}
				if ( in_array( 'featured_image_caption', $fields, true ) ) {
					$data['featured_image_caption'] = '';
				}
			}
		}

		// WooCommerce variable product variations (export as JSON in a single column).
		if ( 'product' === $post->post_type && in_array( 'variations', $fields, true ) ) {
			$data['variations'] = $this->get_product_variations_json( (int) $post->ID );
		}

		// Process individual taxonomy fields (taxonomy_category, taxonomy_post_tag, product_cat, product_tag, etc.)
		foreach ( $fields as $field ) {
			if ( strpos( $field, 'taxonomy_' ) === 0 ) {
				$taxonomy_name = substr( $field, 9 ); // Remove 'taxonomy_' prefix
				$terms         = wp_get_object_terms( $post->ID, $taxonomy_name, [ 'fields' => 'names' ] );

				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$data[ $field ] = implode( ', ', $terms );
				} else {
					$data[ $field ] = '';
				}
			}
			// Handle direct taxonomy names (product_cat, product_tag, etc.)
			elseif ( taxonomy_exists( $field ) ) {
				$terms = wp_get_object_terms( $post->ID, $field, [ 'fields' => 'names' ] );

				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$data[ $field ] = implode( ', ', $terms );
				} else {
					$data[ $field ] = '';
				}
			}
		}       // Process individual meta fields
		foreach ( $fields as $field ) {
			// Skip if already processed
			if ( isset( $data[ $field ] ) ) {
				continue;
			}

			// Check if it's a meta field (starts with _ or not in basic/special fields)
			// Skip if already processed as taxonomy
			if ( ! in_array( $field, $basic_fields, true ) &&
				! in_array( $field, [ 'author_name', 'author_email', 'post_meta', 'taxonomies', 'featured_image', 'featured_image_id', 'featured_image_url', 'featured_image_title', 'featured_image_caption' ], true ) &&
				strpos( $field, 'taxonomy_' ) !== 0 &&
				! taxonomy_exists( $field ) ) {
					// Handle ACF fields (with acf_ prefix)
				if ( strpos( $field, 'acf_' ) === 0 ) {
					$acf_field_name = substr( $field, 4 ); // Remove 'acf_' prefix (4 characters)

					// Export ACF values using raw meta + ACF field definitions from DB.
					// This avoids relying on ACF runtime APIs (get_field/get_field_object)
					// which can become unreliable in export/background contexts, especially
					// for nested fields like repeaters.
					$field_key_ref = get_post_meta( $post->ID, "_{$acf_field_name}", true );
					if ( $field_key_ref && 0 === strpos( $field_key_ref, 'field_' ) ) {
						$field_cfg = $this->acf_db_load_field_config_by_key( $field_key_ref );
						if ( $field_cfg ) {
							$exported = $this->acf_export_value_from_meta( $post->ID, $field_cfg, $acf_field_name );
							if ( is_array( $exported ) || is_object( $exported ) ) {
								$data[ $field ] = wp_json_encode( $exported );
							} elseif ( $exported === null || $exported === false ) {
								$data[ $field ] = '';
							} else {
								$data[ $field ] = $exported;
							}
							continue;
						}
					}

					// Try get_field() first (handles complex fields like images, relationships).
					// Prefer using the ACF field KEY when available — it is more reliable than
					// using the name on non-edit screens (repeaters in particular can return
					// false when ACF can't resolve the reference by name).
					$acf_value = false;
					$field_key = '';
					if ( function_exists( 'get_field' ) ) {
						$field_key = get_post_meta( $post->ID, "_{$acf_field_name}", true );
						if ( $field_key && 0 === strpos( $field_key, 'field_' ) ) {
							$acf_value = get_field( $field_key, $post->ID );
						}
						if ( $acf_value === false || $acf_value === null ) {
							$acf_value = get_field( $acf_field_name, $post->ID );
						}
					}

					// Some environments (background/cron/ajax) can cause get_field() to return
					// false for nested fields (repeaters/flexible/group) even when the field
					// reference key is present.  Fall back to ACF internals to load and format
					// the field by KEY.
					if ( ( $acf_value === false || $acf_value === null ) && $field_key && function_exists( 'acf_get_field' )
					&& function_exists( 'acf_get_value' ) && function_exists( 'acf_format_value' ) ) {
						$field_obj = acf_get_field( $field_key );
						if ( is_array( $field_obj ) ) {
							$raw       = acf_get_value( $post->ID, $field_obj );
							$acf_value = acf_format_value( $raw, $post->ID, $field_obj );
						}
					}

					// If get_field() returns false or null (field not found / ACF 6.x empty field),
					// fall back to direct get_post_meta() and collect all related meta
					if ( $acf_value === false || $acf_value === null ) {
						$acf_value = get_post_meta( $post->ID, $acf_field_name, true );

						// Check if this is a repeater/component field (numeric value AND has sub-fields)
						if ( is_numeric( $acf_value ) && $acf_value > 0 ) {
							global $wpdb;
							$count = intval( $acf_value );

							// Check if there are sub-fields (check first row)
							$pattern        = $acf_field_name . '_0_%';
							$has_sub_fields = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
								$wpdb->prepare(
									"SELECT COUNT(*) FROM {$wpdb->postmeta} 
						WHERE post_id = %d AND meta_key LIKE %s",
									$post->ID,
									$pattern
								)
							);

							// Only treat as repeater/component if sub-fields exist
							if ( $has_sub_fields > 0 ) {
								$repeater_data = [];

								for ( $i = 0; $i < $count; $i++ ) {
									// Get all meta keys for this row
									$pattern    = $acf_field_name . '_' . $i . '_%';
									$sub_fields = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
										$wpdb->prepare(
											"SELECT meta_key, meta_value FROM {$wpdb->postmeta} 
								WHERE post_id = %d AND meta_key LIKE %s",
											$post->ID,
											$pattern
										),
										ARRAY_A
									);

									if ( ! empty( $sub_fields ) ) {
												$row_data = [];
										foreach ( $sub_fields as $sub_field ) {
											// Extract sub-field name (remove prefix)
											$sub_field_name              = str_replace( $acf_field_name . '_' . $i . '_', '', $sub_field['meta_key'] );
											$row_data[ $sub_field_name ] = $sub_field['meta_value'];
										}
												$repeater_data[] = $row_data;
									}
								}

								// Use the repeater data
								if ( ! empty( $repeater_data ) ) {
									$acf_value = $repeater_data;
								}
							}
							// Otherwise it's just a number field, keep the numeric value
						}
						// If it's a serialized array, unserialize it
						elseif ( is_string( $acf_value ) && $acf_value !== '' ) {
							$unserialized = @unserialize( $acf_value );
							if ( $unserialized !== false || $acf_value === 'b:0;' ) {
								$acf_value = $unserialized;
							}
						}
					}

					// Convert ACF value to exportable format
					if ( is_array( $acf_value ) ) {
						// For arrays (images, files, etc.), try to get just the URL or serialize
						if ( isset( $acf_value['url'] ) ) {
							$data[ $field ] = $acf_value['url'];
						} elseif ( isset( $acf_value['ID'] ) ) {
							$data[ $field ] = $acf_value['ID'];
						} else {
							$data[ $field ] = $this->export_acf_complex_array( $acf_value, $acf_field_name, $post->ID );
						}
					} elseif ( $acf_value === false || $acf_value === null || $acf_value === '' ) {
						// Empty field
						$data[ $field ] = '';
					} else {
						// String, number, or true boolean
						$data[ $field ] = $acf_value;
					}
					continue;
				}

				// Remove 'meta_' prefix if present (added by frontend for custom fields)
				$meta_key = $field;
				if ( strpos( $field, 'meta_' ) === 0 ) {
					$meta_key = substr( $field, 5 ); // Remove 'meta_' prefix (5 characters)
				}

				// If this meta key belongs to an ACF field, export it in a portable format.
				// This is important for edge cases like nested fields with empty names
				// (their meta key ends with an underscore, so there is no matching acf_* column).
				$field_key_ref = get_post_meta( $post->ID, "_{$meta_key}", true );
				if ( $field_key_ref && 0 === strpos( $field_key_ref, 'field_' ) ) {
					$field_cfg  = $this->acf_db_load_field_config_by_key( $field_key_ref );
					$field_type = is_array( $field_cfg ) ? ( $field_cfg['type'] ?? '' ) : '';

					// Only apply to field types where portability matters across sites.
					if ( $field_cfg && in_array( $field_type, [ 'image', 'file', 'gallery', 'relationship', 'post_object', 'taxonomy', 'user' ], true ) ) {
						$exported = $this->acf_export_value_from_meta( $post->ID, $field_cfg, $meta_key );
						if ( is_array( $exported ) || is_object( $exported ) ) {
							$data[ $field ] = wp_json_encode( $exported );
						} elseif ( $exported === null || $exported === false ) {
							$data[ $field ] = '';
						} else {
							$data[ $field ] = $exported;
						}
						continue;
					}
				}

				// Special handling for WooCommerce fields - use WC_Product object
				if ( $post->post_type === 'product' && class_exists( 'WC_Product' ) && function_exists( 'wc_get_product' ) ) {
					$wc_product = wc_get_product( $post->ID );

					if ( $wc_product ) {
						// Map field names to WooCommerce getter methods
						$woo_field_map = [
							'_sku'                   => 'get_sku',
							'_regular_price'         => 'get_regular_price',
							'_sale_price'            => 'get_sale_price',
							'_tax_status'            => 'get_tax_status',
							'_tax_class'             => 'get_tax_class',
							'_stock'                 => 'get_stock_quantity',
							'_stock_quantity'        => 'get_stock_quantity',
							'_stock_status'          => 'get_stock_status',
							'_manage_stock'          => 'get_manage_stock',
							'_backorders'            => 'get_backorders',
							'_downloadable'          => 'get_downloadable',
							'_virtual'               => 'get_virtual',
							'_weight'                => 'get_weight',
							'_length'                => 'get_length',
							'_width'                 => 'get_width',
							'_height'                => 'get_height',
							'_product_image_gallery' => 'get_gallery_image_ids',
							'_wc_average_rating'     => 'get_average_rating',
							'_wc_review_count'       => 'get_review_count',
							'total_sales'            => 'get_total_sales',
							// Add alternative field names without underscore
							'sku'                    => 'get_sku',
							'regular_price'          => 'get_regular_price',
							'sale_price'             => 'get_sale_price',
							'tax_status'             => 'get_tax_status',
							'tax_class'              => 'get_tax_class',
							'stock_quantity'         => 'get_stock_quantity',
							'stock_status'           => 'get_stock_status',
							'manage_stock'           => 'get_manage_stock',
							'backorders'             => 'get_backorders',
							'downloadable'           => 'get_downloadable',
							'virtual'                => 'get_virtual',
							'weight'                 => 'get_weight',
							'length'                 => 'get_length',
							'width'                  => 'get_width',
							'height'                 => 'get_height',
							'shipping_class'         => 'get_shipping_class',
							'product_gallery'        => 'get_gallery_image_ids',
							'average_rating'         => 'get_average_rating',
							'review_count'           => 'get_review_count',
						];

						// Check if this field is a WooCommerce field
						if ( isset( $woo_field_map[ $field ] ) || isset( $woo_field_map[ $meta_key ] ) ) {
							$method = $woo_field_map[ $field ] ?? $woo_field_map[ $meta_key ];

							if ( method_exists( $wc_product, $method ) ) {
								$value = $wc_product->$method();

								// Convert boolean values to yes/no
								if ( is_bool( $value ) ) {
									$value = $value ? 'yes' : 'no';
								}
								// Convert array to comma-separated string (for gallery)
								elseif ( is_array( $value ) ) {
									// Make gallery portable across sites: export attachment URLs instead of IDs.
									if ( 'get_gallery_image_ids' === $method ) {
										$urls = [];
										foreach ( $value as $id ) {
											if ( ! is_numeric( $id ) ) {
												continue;
											}
											$url = wp_get_attachment_url( (int) $id );
											if ( $url ) {
												$urls[] = $url;
											}
										}
										$value = implode( ',', $urls );
									} else {
										$value = implode( ',', $value );
									}
								}
								// Ensure empty strings for null values
								elseif ( $value === null || $value === false ) {
									$value = '';
								}

								$data[ $field ] = $value;
								continue;
							}
						}

						// Handle _featured / featured field
						if ( $field === '_featured' || $meta_key === '_featured' || $field === 'featured' ) {
							$data[ $field ] = $wc_product->get_featured() ? 'yes' : 'no';
							continue;
						}

						// Handle _visibility / visibility field
						if ( $field === '_visibility' || $meta_key === '_visibility' || $field === 'visibility' ) {
							$catalog_visibility = $wc_product->get_catalog_visibility();
							$data[ $field ]     = $catalog_visibility ? $catalog_visibility : 'visible';
							continue;
						}

						// Handle _product_type field
						if ( $field === '_product_type' || $meta_key === '_product_type' ) {
							$data[ $field ] = $wc_product->get_type();
							continue;
						}

						// Handle _shipping_class field (already in map but keep for backward compatibility)
						if ( $field === '_shipping_class' || $meta_key === '_shipping_class' ) {
							$shipping_class = $wc_product->get_shipping_class();
							$data[ $field ] = $shipping_class ? $shipping_class : '';
							continue;
						}
					}
				}

				// Get meta value - always include the field if it was explicitly selected
				$meta_value = get_post_meta( $post->ID, $meta_key, true );

				$data[ $field ] = $meta_value !== false ? $meta_value : '';
			}
		}

		return $data;
	}

	/**
	 * Export a complex ACF array value in a portable, typed JSON format.
	 *
	 * Instead of PHP-serializing the raw ACF array (which contains source-site IDs
	 * that won't exist on the target site), this method converts:
	 *  - gallery field       → {"acf_type":"gallery","values":["url1","url2",...]}
	 *  - relationship/post_object → {"acf_type":"relation","values":["slug1","slug2",...]}
	 *  - taxonomy field      → {"acf_type":"taxonomy","taxonomy":"cat","values":["Term Name",...]}
	 * Falls back to maybe_serialize() for unknown/unresolvable structures.
	 *
	 * @param array  $value      ACF array value.
	 * @param string $field_name ACF field name (without 'acf_' prefix).
	 * @param int    $post_id    Post ID.
	 * @return string Encoded string for CSV cell.
	 */
	private function export_acf_complex_array( array $value, string $field_name, int $post_id ): string {
		if ( empty( $value ) ) {
			return '';
		}

		// ── Determine definitive field type via ACF field object ──────────────
		// IMPORTANT: Only use heuristics when field_type is empty (unknown).
		// Heuristic guessing from ID values is unreliable because:
		// - term IDs and post IDs overlap on every site
		// - attachment IDs can match relation post IDs
		// - repeater rows can contain a 'url' sub-field that looks like a gallery
		$field_type     = '';
		$field_taxonomy = '';
		$field_obj      = null;

		// Prefer resolving the field by its reference KEY stored in post meta.
		// Looking up by name can be ambiguous, especially after ACF has loaded
		// nested sub-fields in the current request.
		$field_key_ref = get_post_meta( $post_id, "_{$field_name}", true );

		if ( $field_key_ref && 0 === strpos( $field_key_ref, 'field_' ) ) {
			if ( function_exists( 'get_field_object' ) ) {
				$field_obj = get_field_object( $field_key_ref );
			}
			if ( ! is_array( $field_obj ) && function_exists( 'acf_get_field' ) ) {
				$field_obj = acf_get_field( $field_key_ref );
			}
			if ( is_array( $field_obj ) ) {
				$field_type     = $field_obj['type'] ?? '';
				$field_taxonomy = $field_obj['taxonomy'] ?? '';
			}
		} elseif ( function_exists( 'get_field_object' ) ) {
			// Fallback: resolve by field name (works when reference meta is missing).
			$field_obj = get_field_object( $field_name, $post_id );
			if ( is_array( $field_obj ) ) {
				$field_type     = $field_obj['type'] ?? '';
				$field_taxonomy = $field_obj['taxonomy'] ?? '';
			}
		}

		$first = reset( $value );

		// ── Repeater / Flexible Content / Group ───────────────────────────────
		// Export nested structures as JSON, but convert nested media/relations
		// to portable values (URLs/slugs/names) so imports work cross-site.
		if ( in_array( $field_type, [ 'repeater', 'flexible_content', 'group' ], true ) ) {
			if ( is_array( $field_obj ) ) {
				$portable = $this->export_acf_portable_nested_value( $value, $field_obj, $post_id );
				return wp_json_encode( $portable );
			}
			return wp_json_encode( $value );
		}

		// ── Gallery ───────────────────────────────────────────────────────────
		// Trigger ONLY when:
		// a) ACF confirms type = 'gallery' / 'image' / 'file', OR
		// b) type is unknown AND first item looks exactly like an ACF attachment
		// array: has both 'url' AND a numeric 'ID' key.
		// ACF image/gallery/file arrays always include 'ID' as an integer.
		// Repeater row arrays use user-defined sub-field names and do NOT
		// have an 'ID' key, so they are excluded by this check.
		$is_gallery_by_type  = in_array( $field_type, [ 'gallery', 'image', 'file' ], true );
		$is_gallery_by_shape = '' === $field_type
			&& is_array( $first )
			&& isset( $first['url'], $first['ID'] )
			&& is_numeric( $first['ID'] )
			&& ! isset( $first['post_name'] )
			&& ! isset( $first['term_id'] );

		// Even if ACF reports type=gallery/image/file, do NOT treat the value as
		// media unless it actually looks like a list of attachments.  This
		// prevents mis-detecting repeaters (rows often contain a 'url' sub-field).
		if ( ( $is_gallery_by_type || $is_gallery_by_shape ) && $this->acf_array_looks_like_gallery( $value ) ) {
			$urls = [];
			foreach ( $value as $item ) {
				if ( is_array( $item ) && isset( $item['url'] ) ) {
					$urls[] = $item['url'];
				} elseif ( is_numeric( $item ) ) {
					$url = wp_get_attachment_url( (int) $item );
					if ( $url ) {
						$urls[] = $url;
					}
				}
			}
			if ( ! empty( $urls ) ) {
				return wp_json_encode(
					[
						'acf_type' => 'gallery',
						'values'   => $urls,
					]
				);
			}
		}

		// ── Relationship / Post Object ────────────────────────────────────────
		// Trigger ONLY when ACF confirms type OR first item IS a WP_Post object.
		// Do NOT trigger on plain integer arrays without a confirmed type —
		// integer IDs that happen to match posts are indistinguishable from
		// term IDs or ACF field-config post IDs.
		$is_relation = in_array( $field_type, [ 'relationship', 'post_object' ], true )
			|| ( '' === $field_type && $first instanceof \WP_Post );

		if ( $is_relation ) {
			$slugs = [];
			foreach ( $value as $item ) {
				if ( $item instanceof \WP_Post ) {
					if ( 'attachment' === $item->post_type ) {
						$url = wp_get_attachment_url( $item->ID );
						if ( $url ) {
							$slugs[] = $url;
						}
					} else {
						$slugs[] = $item->post_name;
					}
				} elseif ( is_array( $item ) && isset( $item['post_name'] ) ) {
					// Best-effort: attachment arrays include an ID key.
					if ( isset( $item['ID'] ) && is_numeric( $item['ID'] ) ) {
						$url = wp_get_attachment_url( (int) $item['ID'] );
						if ( $url ) {
							$slugs[] = $url;
							continue;
						}
					}
					$slugs[] = $item['post_name'];
				} elseif ( is_numeric( $item ) && '' !== $field_type ) {
					// Only resolve IDs to slugs when the type is confirmed by ACF.
					$p = get_post( (int) $item );
					if ( $p ) {
						if ( 'attachment' === $p->post_type ) {
							$url = wp_get_attachment_url( $p->ID );
							if ( $url ) {
								$slugs[] = $url;
								continue;
							}
						}
						$slugs[] = $p->post_name;
					}
				}
			}
			if ( ! empty( $slugs ) ) {
				return wp_json_encode(
					[
						'acf_type' => 'relation',
						'values'   => $slugs,
					]
				);
			}
		}

		// ── Taxonomy ─────────────────────────────────────────────────────────
		// Same rule: only resolve integer IDs to term names when type is confirmed.
		$is_taxonomy = 'taxonomy' === $field_type
			|| ( '' === $field_type && $first instanceof \WP_Term );

		if ( $is_taxonomy ) {
			$names = [];
			foreach ( $value as $item ) {
				if ( $item instanceof \WP_Term ) {
					$names[] = $item->name;
				} elseif ( is_array( $item ) && isset( $item['name'] ) && isset( $item['term_id'] ) ) {
					$names[] = $item['name'];
				} elseif ( is_numeric( $item ) && '' !== $field_type ) {
					$t = get_term( (int) $item );
					if ( ! is_wp_error( $t ) && $t ) {
						$names[] = $t->name;
					}
				}
			}
			if ( ! empty( $names ) ) {
				$encoded = [
					'acf_type' => 'taxonomy',
					'values'   => $names,
				];
				if ( $field_taxonomy ) {
					$encoded['taxonomy'] = $field_taxonomy;
				}
				return wp_json_encode( $encoded );
			}
		}

		// ── Fallback: raw JSON ────────────────────────────────────────────────
		// Use wp_json_encode (not maybe_serialize) to avoid double-serialization
		// on import: update_post_meta() would serialize a PHP-serialized string
		// a second time, causing ACF to receive a string instead of an array.
		return wp_json_encode( $value );
	}

	/**
	 * Does this array look like a gallery/media list?
	 *
	 * Guard against mis-detecting repeater rows (arrays that happen to contain
	 * a 'url' sub-field) as gallery items.
	 *
	 * @param array $value Candidate array.
	 * @return bool
	 */
	private function acf_array_looks_like_gallery( array $value ): bool {
		if ( empty( $value ) ) {
			return false;
		}

		// Gallery values should be list-like: [item0, item1, ...]
		if ( ! $this->acf_is_list_array( $value ) ) {
			return false;
		}

		foreach ( $value as $item ) {
			if ( is_numeric( $item ) ) {
				continue;
			}

			if ( is_array( $item ) && isset( $item['url'] ) && ( isset( $item['ID'] ) || isset( $item['id'] ) ) ) {
				$id = $item['ID'] ?? $item['id'];
				if ( is_numeric( $id ) ) {
					continue;
				}
			}

			return false;
		}

		return true;
	}

	/**
	 * Export nested ACF values (repeater / group / flexible content) into a
	 * portable structure (URLs/slugs/names) that can be imported cross-site.
	 *
	 * @param mixed $value     Field value.
	 * @param array $field_obj ACF field object array.
	 * @param int   $post_id   Post ID (for resolving attachment URLs).
	 * @return mixed Portable value (to be encoded as JSON by the caller).
	 */
	private function export_acf_portable_nested_value( $value, array $field_obj, int $post_id ) {
		$type = $field_obj['type'] ?? '';

		switch ( $type ) {
			case 'image':
			case 'file':
				return $this->acf_export_media_url( $value, $post_id );

			case 'gallery':
				$urls = [];
				if ( is_array( $value ) ) {
					foreach ( $value as $item ) {
						$url = $this->acf_export_media_url( $item, $post_id );
						if ( $url ) {
							$urls[] = $url;
						}
					}
				}
				return ! empty( $urls ) ? [
					'acf_type' => 'gallery',
					'values'   => $urls,
				] : [];

			case 'relationship':
			case 'post_object':
				$items = is_array( $value ) ? $value : [ $value ];
				$slugs = [];
				foreach ( $items as $item ) {
					if ( $item instanceof \WP_Post ) {
						$slugs[] = $item->post_name;
					} elseif ( is_array( $item ) && isset( $item['post_name'] ) ) {
						$slugs[] = $item['post_name'];
					} elseif ( is_numeric( $item ) ) {
						$p = get_post( (int) $item );
						if ( $p ) {
							$slugs[] = $p->post_name;
						}
					}
				}
				$slugs   = array_values( array_filter( array_map( 'sanitize_title', $slugs ) ) );
				$payload = [
					'acf_type' => 'relation',
					'values'   => $slugs,
				];
				// post_object can be single or multiple; relationship is always multiple.
				if ( 'post_object' === $type && empty( $field_obj['multiple'] ) ) {
					$payload['single'] = true;
				}
				return $payload;

			case 'taxonomy':
				$taxonomy = $field_obj['taxonomy'] ?? '';
				$items    = is_array( $value ) ? $value : [ $value ];
				$names    = [];
				foreach ( $items as $item ) {
					if ( $item instanceof \WP_Term ) {
						$names[] = $item->name;
					} elseif ( is_array( $item ) && isset( $item['name'] ) && isset( $item['term_id'] ) ) {
						$names[] = $item['name'];
					} elseif ( is_numeric( $item ) ) {
						$t = get_term( (int) $item );
						if ( ! is_wp_error( $t ) && $t ) {
							$names[] = $t->name;
						}
					}
				}
				$names   = array_values( array_filter( array_map( 'trim', $names ) ) );
				$payload = [
					'acf_type' => 'taxonomy',
					'values'   => $names,
				];
				if ( $taxonomy ) {
					$payload['taxonomy'] = $taxonomy;
				}
				// Preserve single-selection fields.
				if ( ! is_array( $value ) ) {
					$payload['single'] = true;
				}
				return $payload;

			case 'group':
				if ( ! is_array( $value ) ) {
					return $value;
				}
				$out = [];
				foreach ( ( $field_obj['sub_fields'] ?? [] ) as $sub ) {
					$name = $sub['name'] ?? '';
					if ( '' === $name ) {
						continue;
					}
					$sub_value    = array_key_exists( $name, $value ) ? $value[ $name ] : null;
					$out[ $name ] = $this->export_acf_portable_nested_value( $sub_value, $sub, $post_id );
				}
				return $out;

			case 'repeater':
				if ( ! is_array( $value ) ) {
					return $value;
				}
				$sub_fields  = $field_obj['sub_fields'] ?? [];
				$sub_by_name = [];
				foreach ( $sub_fields as $sub ) {
					$name = $sub['name'] ?? '';
					if ( '' !== $name ) {
						$sub_by_name[ $name ] = $sub;
					}
				}

				$rows = [];
				foreach ( $value as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}
					$row_out = [];
					foreach ( $sub_by_name as $name => $sub ) {
						$sub_value        = array_key_exists( $name, $row ) ? $row[ $name ] : null;
						$row_out[ $name ] = $this->export_acf_portable_nested_value( $sub_value, $sub, $post_id );
					}
					$rows[] = $row_out;
				}
				return $rows;

			case 'flexible_content':
				if ( ! is_array( $value ) ) {
					return $value;
				}

				$layouts_by_name = [];
				foreach ( ( $field_obj['layouts'] ?? [] ) as $layout ) {
					if ( ! empty( $layout['name'] ) ) {
						$layouts_by_name[ $layout['name'] ] = $layout;
					}
				}

				$out_rows = [];
				foreach ( $value as $row ) {
					if ( ! is_array( $row ) ) {
						continue;
					}

					$layout_name = $row['acf_fc_layout'] ?? '';
					$layout_obj  = $layouts_by_name[ $layout_name ] ?? null;

					$row_out = [ 'acf_fc_layout' => $layout_name ];
					foreach ( ( is_array( $layout_obj ) ? ( $layout_obj['sub_fields'] ?? [] ) : [] ) as $sub ) {
						$name = $sub['name'] ?? '';
						if ( '' === $name ) {
							continue;
						}
						$sub_value        = array_key_exists( $name, $row ) ? $row[ $name ] : null;
						$row_out[ $name ] = $this->export_acf_portable_nested_value( $sub_value, $sub, $post_id );
					}

					$out_rows[] = $row_out;
				}

				return $out_rows;
		}

		return $value;
	}

	/**
	 * Convert an ACF media value (image/file) into a URL for portable export.
	 *
	 * @param mixed $value   Field value.
	 * @param int   $post_id Post ID (unused, kept for signature symmetry).
	 * @return string Media URL or empty string.
	 */
	private function acf_export_media_url( $value, int $post_id ): string {
		unset( $post_id );

		if ( is_array( $value ) && isset( $value['url'] ) && filter_var( $value['url'], FILTER_VALIDATE_URL ) ) {
			return (string) $value['url'];
		}

		if ( is_numeric( $value ) ) {
			$url = wp_get_attachment_url( (int) $value );
			return $url ? (string) $url : '';
		}

		if ( is_string( $value ) && '' !== $value && filter_var( $value, FILTER_VALIDATE_URL ) ) {
			return $value;
		}

		return '';
	}

	/**
	 * Check if an array is list-like (0..n-1 numeric keys).
	 *
	 * @param array $arr Array.
	 * @return bool
	 */
	private function acf_is_list_array( array $arr ): bool {
		if ( [] === $arr ) {
			return true;
		}

		$keys = array_keys( $arr );
		return $keys === range( 0, count( $keys ) - 1 );
	}

	/**
	 * Load an ACF field config directly from the DB by its field KEY.
	 *
	 * Avoids ACF runtime caches/APIs which can become unreliable during long
	 * export requests with nested fields.
	 *
	 * @param string $field_key ACF field key (field_...).
	 * @return array|null Field config or null when not found.
	 */
	private function acf_db_load_field_config_by_key( string $field_key ): ?array {
		if ( '' === $field_key || 0 !== strpos( $field_key, 'field_' ) ) {
			return null;
		}

		if ( array_key_exists( $field_key, $this->acf_field_config_cache ) ) {
			return $this->acf_field_config_cache[ $field_key ];
		}

		global $wpdb;

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- ACF field definitions are stored in wp_posts.
			$wpdb->prepare(
				"SELECT ID, post_title, post_excerpt, post_content
				 FROM {$wpdb->posts}
				 WHERE post_type = 'acf-field' AND post_name = %s
				 LIMIT 1",
				$field_key
			),
			ARRAY_A
		);

		if ( ! $row ) {
			$this->acf_field_config_cache[ $field_key ] = null;
			return null;
		}

		$settings = @unserialize( (string) $row['post_content'] );
		if ( ! is_array( $settings ) ) {
			$settings = [];
		}

		$type = $settings['type'] ?? '';

			$cfg = [
				'key'           => $field_key,
				'name'          => (string) ( $row['post_excerpt'] ?? '' ),
				'label'         => (string) ( $row['post_title'] ?? '' ),
				'type'          => (string) $type,
				'taxonomy'      => (string) ( $settings['taxonomy'] ?? '' ),
				'multiple'      => ! empty( $settings['multiple'] ),
				'return_format' => (string) ( $settings['return_format'] ?? '' ),
				'field_type'    => (string) ( $settings['field_type'] ?? '' ),
				'settings'      => $settings,
				'sub_fields'    => [],
				'layouts'       => $settings['layouts'] ?? [],
				'_post_id'      => (int) ( $row['ID'] ?? 0 ),
			];

			if ( in_array( $cfg['type'], [ 'repeater', 'group' ], true ) ) {
				$cfg['sub_fields'] = $this->acf_db_load_child_field_configs( (int) $cfg['_post_id'] );
			}

			if ( 'flexible_content' === $cfg['type'] ) {
				// Flexible content stores layouts in settings, but stores each layout's
				// sub-fields as child `acf-field` posts with a `parent_layout` setting.
				$children  = $this->acf_db_load_child_field_configs( (int) $cfg['_post_id'] );
				$by_layout = [];
				foreach ( $children as $child ) {
					if ( ! is_array( $child ) ) {
						continue;
					}
					$parent_layout = '';
					if ( isset( $child['settings'] ) && is_array( $child['settings'] ) && ! empty( $child['settings']['parent_layout'] ) ) {
						$parent_layout = (string) $child['settings']['parent_layout'];
					}
					if ( '' === $parent_layout ) {
						continue;
					}
					if ( ! isset( $by_layout[ $parent_layout ] ) ) {
						$by_layout[ $parent_layout ] = [];
					}
					$by_layout[ $parent_layout ][] = $child;
				}

				// Merge sub_fields into each layout config.
				if ( is_array( $cfg['layouts'] ) ) {
					foreach ( $cfg['layouts'] as $layout_key => $layout_cfg ) {
						$key = is_string( $layout_key ) ? $layout_key : ( is_array( $layout_cfg ) ? (string) ( $layout_cfg['key'] ?? '' ) : '' );
						if ( '' === $key || ! is_array( $layout_cfg ) ) {
							continue;
						}
						$cfg['layouts'][ $layout_key ]['sub_fields'] = $by_layout[ $key ] ?? [];
					}
				}
			}

			$this->acf_field_config_cache[ $field_key ] = $cfg;
			return $cfg;
	}

	/**
	 * Load child ACF field configs for a parent acf-field post ID.
	 *
	 * @param int $parent_field_post_id Parent acf-field post ID.
	 * @return array List of field configs.
	 */
	private function acf_db_load_child_field_configs( int $parent_field_post_id ): array {
		if ( $parent_field_post_id <= 0 ) {
			return [];
		}

		global $wpdb;

		$keys = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reading field definitions.
			$wpdb->prepare(
				"SELECT post_name
				 FROM {$wpdb->posts}
				 WHERE post_type = 'acf-field' AND post_parent = %d
				 ORDER BY menu_order ASC, ID ASC",
				$parent_field_post_id
			)
		);

		$out = [];
		foreach ( $keys as $key ) {
			$cfg = $this->acf_db_load_field_config_by_key( (string) $key );
			if ( $cfg ) {
				$out[] = $cfg;
			}
		}

		return $out;
	}

	/**
	 * Export an ACF field value from raw post meta using a DB-loaded field config.
	 *
	 * Returns a portable structure:
	 * - image/file  → URL string
	 * - gallery     → {acf_type:"gallery", values:[urls]}
	 * - relation    → {acf_type:"relation", values:[slugs], single?:true}
	 * - taxonomy    → {acf_type:"taxonomy", values:[names], taxonomy?:..., single?:true}
	 * - user        → {acf_type:"user", values:[logins], single?:true}
	 * - repeater/group → nested arrays (will be JSON-encoded by caller)
	 *
	 * @param int    $post_id       Post ID.
	 * @param array  $field_cfg     Field config.
	 * @param string $base_meta_key Meta key that stores this field's value (for nested fields includes row/group prefixes).
	 * @return mixed Portable value.
	 */
	private function acf_export_value_from_meta( int $post_id, array $field_cfg, string $base_meta_key ) {
		$type = $field_cfg['type'] ?? '';

		switch ( $type ) {
			case 'wysiwyg':
			case 'textarea':
			case 'text':
				$raw = get_post_meta( $post_id, $base_meta_key, true );
				if ( is_string( $raw ) && '' !== $raw ) {
					return $this->acf_export_string_with_gallery_tokens( $raw );
				}
				return $raw;

			case 'repeater':
				$count_raw = get_post_meta( $post_id, $base_meta_key, true );
				$count     = is_numeric( $count_raw ) ? (int) $count_raw : 0;
				if ( $count <= 0 ) {
					return [];
				}

				$rows = [];
				for ( $i = 0; $i < $count; $i++ ) {
					$row_out = [];
					foreach ( ( $field_cfg['sub_fields'] ?? [] ) as $sub ) {
						$sub_name = $sub['name'] ?? '';
						if ( '' === $sub_name ) {
							continue;
						}
						$sub_base             = $base_meta_key . '_' . $i . '_' . $sub_name;
						$row_out[ $sub_name ] = $this->acf_export_value_from_meta( $post_id, $sub, $sub_base );
					}
					$rows[] = $row_out;
				}

				return $rows;

			case 'group':
				$out = [];
				foreach ( ( $field_cfg['sub_fields'] ?? [] ) as $sub ) {
					$sub_name = $sub['name'] ?? '';
					if ( '' === $sub_name ) {
						continue;
					}
					$sub_base         = $base_meta_key . '_' . $sub_name;
					$out[ $sub_name ] = $this->acf_export_value_from_meta( $post_id, $sub, $sub_base );
				}
				return $out;

			case 'flexible_content':
				// Flexible content stores an ordered list of layout names in the base key,
				// and each row's sub-fields as additional meta keys:
				// {$base}_0_{subfield}, {$base}_1_{subfield}, ...
				$raw     = get_post_meta( $post_id, $base_meta_key, true );
				$layouts = maybe_unserialize( $raw );
				if ( ! is_array( $layouts ) || empty( $layouts ) ) {
					return [];
				}

				$layouts_by_name = [];
				foreach ( ( $field_cfg['layouts'] ?? [] ) as $layout ) {
					if ( is_array( $layout ) && ! empty( $layout['name'] ) ) {
						$layouts_by_name[ (string) $layout['name'] ] = $layout;
					}
				}

				$rows = [];
				foreach ( array_values( $layouts ) as $i => $layout_name ) {
					$layout_name = (string) $layout_name;
					$layout_cfg  = $layouts_by_name[ $layout_name ] ?? null;

					$row_out = [ 'acf_fc_layout' => $layout_name ];
					if ( is_array( $layout_cfg ) ) {
						foreach ( ( $layout_cfg['sub_fields'] ?? [] ) as $sub ) {
							$sub_name = $sub['name'] ?? '';
							if ( '' === $sub_name ) {
								continue;
							}
							$sub_base             = $base_meta_key . '_' . (int) $i . '_' . $sub_name;
							$row_out[ $sub_name ] = $this->acf_export_value_from_meta( $post_id, $sub, $sub_base );
						}
					}

					$rows[] = $row_out;
				}

				return $rows;

			case 'image':
			case 'file':
				return $this->acf_export_media_url( get_post_meta( $post_id, $base_meta_key, true ), $post_id );

			case 'gallery':
				$raw  = get_post_meta( $post_id, $base_meta_key, true );
				$ids  = maybe_unserialize( $raw );
				$list = [];
				if ( is_numeric( $ids ) ) {
					$list = [ (int) $ids ];
				} elseif ( is_array( $ids ) ) {
					$list = $ids;
				} elseif ( is_string( $ids ) && '' !== $ids ) {
					$list = array_map( 'trim', explode( ',', $ids ) );
				}

				$urls = [];
				foreach ( $list as $id ) {
					$url = $this->acf_export_media_url( $id, $post_id );
					if ( $url ) {
						$urls[] = $url;
					}
				}

				return ! empty( $urls ) ? [
					'acf_type' => 'gallery',
					'values'   => $urls,
				] : [];

			case 'relationship':
				$raw = get_post_meta( $post_id, $base_meta_key, true );
				$ids = maybe_unserialize( $raw );
				if ( is_numeric( $ids ) ) {
					$ids = [ (int) $ids ];
				}
				$values = $this->acf_export_relation_values_from_ids( is_array( $ids ) ? $ids : [] );
				return [
					'acf_type' => 'relation',
					'values'   => $values,
				];

			case 'post_object':
				$raw      = get_post_meta( $post_id, $base_meta_key, true );
				$multiple = ! empty( $field_cfg['multiple'] );

				if ( $multiple ) {
					$ids = maybe_unserialize( $raw );
					if ( is_numeric( $ids ) ) {
						$ids = [ (int) $ids ];
					}
					$values = $this->acf_export_relation_values_from_ids( is_array( $ids ) ? $ids : [] );
					return [
						'acf_type' => 'relation',
						'values'   => $values,
					];
				}

				$values = $this->acf_export_relation_values_from_ids( [ $raw ] );
				return [
					'acf_type' => 'relation',
					'values'   => $values,
					'single'   => true,
				];

			case 'taxonomy':
				$raw     = get_post_meta( $post_id, $base_meta_key, true );
				$decoded = maybe_unserialize( $raw );
				$single  = ! is_array( $decoded );
				if ( is_numeric( $decoded ) ) {
					$decoded = [ (int) $decoded ];
				}
				$taxonomy = $field_cfg['taxonomy'] ?? '';
				$names    = $this->acf_export_term_names_from_ids( is_array( $decoded ) ? $decoded : [], (string) $taxonomy );

				$payload = [
					'acf_type' => 'taxonomy',
					'values'   => $names,
				];
				if ( $taxonomy ) {
					$payload['taxonomy'] = (string) $taxonomy;
				}
				if ( $single ) {
					$payload['single'] = true;
				}
				return $payload;

			case 'user':
				$raw     = get_post_meta( $post_id, $base_meta_key, true );
				$decoded = maybe_unserialize( $raw );
				$single  = ! is_array( $decoded );
				if ( is_numeric( $decoded ) ) {
					$decoded = [ (int) $decoded ];
				}
				$logins  = $this->acf_export_user_logins_from_ids( is_array( $decoded ) ? $decoded : [] );
				$payload = [
					'acf_type' => 'user',
					'values'   => $logins,
				];
				if ( $single ) {
					$payload['single'] = true;
				}
				return $payload;

			case 'page_link':
				$raw = get_post_meta( $post_id, $base_meta_key, true );
				$val = maybe_unserialize( $raw );
				if ( is_numeric( $val ) ) {
					$url = get_permalink( (int) $val );
					return $url ? $url : (string) $val;
				}
				return $val;
		}

		// Default: return raw meta, decoding serialized arrays/objects.
		$raw = get_post_meta( $post_id, $base_meta_key, true );
		return maybe_unserialize( $raw );
	}

	/**
	 * Replace `[gallery ids="1,2,3"]` shortcodes inside a string with portable tokens.
	 *
	 * The importer will download the referenced media (via URLs) and reconstruct
	 * the shortcode with the target-site attachment IDs.
	 *
	 * Token format: [[RSL_IE:<base64(json)>]]
	 * JSON payload:
	 *  - acf_type: "gallery_shortcode"
	 *  - shortcode: original matched shortcode string
	 *  - urls: list of attachment URLs
	 *
	 * @param string $value String that may contain gallery shortcodes.
	 * @return string
	 */
	private function acf_export_string_with_gallery_tokens( string $value ): string {
		if ( false === stripos( $value, '[gallery' ) ) {
			return $value;
		}

		$pattern = '/\\[gallery\\b[^\\]]*\\bids=(["\'])([^"\']+)\\1[^\\]]*\\]/i';
		return preg_replace_callback(
			$pattern,
			function ( array $m ) {
				$shortcode = $m[0] ?? '';
				$ids_raw   = $m[2] ?? '';

				$ids = array_filter(
					array_map(
						'trim',
						preg_split( '/\\s*,\\s*/', (string) $ids_raw ) ?: []
					),
					fn( $x ) => '' !== $x
				);

				$urls = [];
				foreach ( $ids as $id ) {
					if ( ! is_numeric( $id ) ) {
						continue;
					}
					$url = wp_get_attachment_url( (int) $id );
					if ( $url ) {
						$urls[] = $url;
					}
				}

				if ( empty( $urls ) ) {
					return $shortcode;
				}

				$payload = [
					'acf_type'  => 'gallery_shortcode',
					'shortcode' => $shortcode,
					'urls'      => array_values( $urls ),
				];

				$json  = wp_json_encode( $payload );
				$token = base64_encode( $json ? $json : '' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
				if ( '' === $token ) {
					return $shortcode;
				}

				return '[[RSL_IE:' . $token . ']]';
			},
			$value
		);
	}

	/**
	 * Convert post IDs into portable relation values.
	 *
	 * - Attachments are exported as absolute URLs (so the importer can download them)
	 * - Other post types are exported as slugs (post_name)
	 *
	 * @param array $ids IDs (may be numeric strings).
	 * @return array Values (URLs and/or slugs).
	 */
	private function acf_export_relation_values_from_ids( array $ids ): array {
		$values = [];
		foreach ( $ids as $id ) {
			if ( ! is_numeric( $id ) ) {
				continue;
			}
			$p = get_post( (int) $id );
			if ( ! $p ) {
				continue;
			}

			if ( 'attachment' === $p->post_type ) {
				$url = wp_get_attachment_url( $p->ID );
				if ( $url ) {
					$values[] = $url;
				}
				continue;
			}

			if ( ! empty( $p->post_name ) ) {
				$values[] = $p->post_name;
			}
		}

		$out = [];
		foreach ( $values as $v ) {
			if ( is_string( $v ) && filter_var( $v, FILTER_VALIDATE_URL ) ) {
				$out[] = $v;
			} else {
				$out[] = sanitize_title( (string) $v );
			}
		}

		return array_values( array_filter( $out ) );
	}

	/**
	 * Convert term IDs into term names.
	 *
	 * @param array  $ids      Term IDs (may be numeric strings).
	 * @param string $taxonomy Optional taxonomy hint.
	 * @return array Term names.
	 */
	private function acf_export_term_names_from_ids( array $ids, string $taxonomy = '' ): array {
		$names = [];
		foreach ( $ids as $id ) {
			if ( ! is_numeric( $id ) ) {
				continue;
			}
			$term = $taxonomy ? get_term( (int) $id, $taxonomy ) : get_term( (int) $id );
			if ( $term && ! is_wp_error( $term ) && ! empty( $term->name ) ) {
				$names[] = $term->name;
			}
		}
		return array_values( array_filter( array_map( 'trim', $names ) ) );
	}

	/**
	 * Convert user IDs into user logins.
	 *
	 * @param array $ids User IDs (may be numeric strings).
	 * @return array User logins.
	 */
	private function acf_export_user_logins_from_ids( array $ids ): array {
		$logins = [];
		foreach ( $ids as $id ) {
			if ( ! is_numeric( $id ) ) {
				continue;
			}
			$user = get_userdata( (int) $id );
			if ( $user && ! empty( $user->user_login ) ) {
				$logins[] = $user->user_login;
			}
		}
		return array_values( array_filter( array_map( 'sanitize_user', $logins ) ) );
	}

	/**
	 * Get post meta data
	 *
	 * @param int $post_id Post ID
	 * @return array
	 */
	protected function get_post_meta( $post_id ) {
		$all_meta = get_post_meta( $post_id );
		$meta     = [];

		foreach ( $all_meta as $key => $values ) {
			// Skip internal WordPress meta, but KEEP ACF reference keys
			// (e.g. `_my_field` = `field_abc123`) so imports can restore ACF fields
			// even when a field name is empty or when only meta_* columns are mapped.
			if ( '_' === substr( $key, 0, 1 ) ) {
				if ( 1 === count( $values ) && is_string( $values[0] ) && 0 === strpos( $values[0], 'field_' ) ) {
					$meta[ $key ] = $values[0];
				}
				continue;
			}

			// Single value meta
			if ( 1 === count( $values ) ) {
				$meta[ $key ] = maybe_unserialize( $values[0] );
			} else {
				// Multiple values
				$meta[ $key ] = array_map( 'maybe_unserialize', $values );
			}
		}

		return $meta;
	}

	/**
	 * Get post taxonomies and terms
	 *
	 * @param int    $post_id   Post ID
	 * @param string $post_type Post type
	 * @return array
	 */
	protected function get_post_taxonomies( $post_id, $post_type ) {
		$taxonomies = get_object_taxonomies( $post_type, 'names' );
		$data       = [];

		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_object_terms( $post_id, $taxonomy, [ 'fields' => 'names' ] );

			if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
				$data[ $taxonomy ] = $terms;
			}
		}

		return $data;
	}

	/**
	 * Get featured image data
	 *
	 * @param int $post_id Post ID
	 * @return array|null
	 */
	protected function get_featured_image( $post_id ) {
		$thumbnail_id = get_post_thumbnail_id( $post_id );

		if ( ! $thumbnail_id ) {
			return null;
		}

		$image = get_post( $thumbnail_id );

		if ( ! $image ) {
			return null;
		}

		return [
			'id'       => $thumbnail_id,
			'url'      => wp_get_attachment_url( $thumbnail_id ),
			'title'    => $image->post_title,
			'alt'      => get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true ),
			'caption'  => $image->post_excerpt,
			'filename' => basename( get_attached_file( $thumbnail_id ) ),
		];
	}

	/**
	 * Get menu data (nav_menu terms with their items)
	 *
	 * @param array $options Export options
	 * @return array Menu data
	 */
	protected function get_menu_data( $options ) {
		$term_args = [
			'taxonomy'   => 'nav_menu',
			'hide_empty' => false,
			'fields'     => 'all',
		];

		// Apply limit and offset if present
		if ( isset( $options['limit'] ) && $options['limit'] > 0 ) {
			$term_args['number'] = $options['limit'];
		}
		if ( isset( $options['offset'] ) ) {
			$term_args['offset'] = $options['offset'];
		}

		// Get all menu terms
		$terms = get_terms( $term_args );

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return [];
		}

		// Apply filters if present
		if ( ! empty( $options['filters'] ) && is_array( $options['filters'] ) ) {
			$terms = $this->apply_menu_filters( $terms, $options['filters'] );
		}

		$data   = [];
		$fields = $this->get_option( 'fields', $this->get_default_fields() );

		// If fields is empty array, use default fields
		if ( empty( $fields ) ) {
			$fields = $this->get_default_fields();
		}

		foreach ( $terms as $term ) {
			// Get menu items for this menu
			$menu_items = wp_get_nav_menu_items( $term->term_id );

			$menu_data = [
				'term_id'    => $term->term_id,
				'name'       => $term->name,
				'slug'       => $term->slug,
				'count'      => $term->count,
				'menu_items' => [],
			];

			// Process each field
			foreach ( $fields as $field ) {
				// Handle ACF fields (with acf_ prefix) for menu term
				if ( strpos( $field, 'acf_' ) === 0 ) {
					$acf_field_name = substr( $field, 4 ); // Remove 'acf_' prefix

					// Try get_field() first (handles complex fields)
					$acf_value = false;
					if ( function_exists( 'get_field' ) ) {
						$acf_value = get_field( $acf_field_name, 'term_' . $term->term_id );
					}

					// If get_field() returns false, try get_term_meta()
					if ( $acf_value === false ) {
						$acf_value = get_term_meta( $term->term_id, $acf_field_name, true );
					}

					// Convert ACF value to exportable format
					if ( is_array( $acf_value ) ) {
						if ( isset( $acf_value['url'] ) ) {
							$menu_data[ $field ] = $acf_value['url'];
						} elseif ( isset( $acf_value['ID'] ) ) {
							$menu_data[ $field ] = $acf_value['ID'];
						} else {
							$menu_data[ $field ] = wp_json_encode( $acf_value );
						}
					} elseif ( is_object( $acf_value ) ) {
						$menu_data[ $field ] = wp_json_encode( $acf_value );
					} else {
						$menu_data[ $field ] = $acf_value !== false ? $acf_value : '';
					}
				}
			}

			if ( ! empty( $menu_items ) ) {
				foreach ( $menu_items as $item ) {
					$item_data = [
						'ID'               => $item->ID,
						'title'            => $item->title,
						'url'              => $item->url,
						'menu_order'       => $item->menu_order,
						'menu_item_parent' => $item->menu_item_parent,
						'object'           => $item->object,
						'object_id'        => $item->object_id,
						'type'             => $item->type,
						'type_label'       => $item->type_label,
						'target'           => $item->target,
						'attr_title'       => $item->attr_title,
						'classes'          => $item->classes,
						'xfn'              => $item->xfn,
						'description'      => $item->description,
					];

					// Extra hints to allow cross-site ID mapping on import.
					if ( $item->type === 'post_type' && ! empty( $item->object ) && ! empty( $item->object_id ) ) {
						$post = get_post( (int) $item->object_id );
						if ( $post ) {
							$item_data['object_name'] = $post->post_name;
							$item_data['object_path'] = get_page_uri( (int) $item->object_id );
						}
					} elseif ( $item->type === 'taxonomy' && ! empty( $item->object ) && ! empty( $item->object_id ) ) {
						$term = get_term( (int) $item->object_id, $item->object );
						if ( $term && ! is_wp_error( $term ) ) {
							$item_data['term_slug'] = $term->slug;
							$item_data['term_name'] = $term->name;
						}
					}

					// Add ACF fields for the menu item as a nested array
					if ( function_exists( 'get_fields' ) ) {
						$item_acf_fields = get_fields( $item->ID );
						if ( ! empty( $item_acf_fields ) && is_array( $item_acf_fields ) ) {
							$item_data['acf_fields'] = $item_acf_fields;
						}
					}

					$menu_data['menu_items'][] = $item_data;
				}
			}

			$data[] = $menu_data;
		}

		return $data;
	}

	/**
	 * Apply filters to menu terms
	 *
	 * @param array $terms   Array of term objects
	 * @param array $filters Array of filter conditions
	 * @return array Filtered terms
	 */
	protected function apply_menu_filters( $terms, $filters ) {
		if ( empty( $filters ) || ! is_array( $filters ) ) {
			return $terms;
		}

		$filtered = $terms;

		foreach ( $filters as $filter ) {
			if ( empty( $filter['field'] ) || empty( $filter['condition'] ) ) {
				continue;
			}

			$field     = $filter['field'];
			$condition = $filter['condition'];
			$value     = $filter['value'] ?? '';

			$filtered = array_filter(
				$filtered,
				function ( $term ) use ( $field, $condition, $value ) {
					// Get the field value from term object
					$term_value = null;
					if ( $field === 'term_id' ) {
						$term_value = $term->term_id;
					} elseif ( $field === 'name' ) {
						$term_value = $term->name;
					} else {
						// Field not supported for menus
						return true;
					}

					// Apply condition
					return $this->evaluate_condition( $term_value, $condition, $value );
				}
			);
		}

		return array_values( $filtered ); // Re-index array
	}

	/**
	 * Export WooCommerce variations for a variable product as JSON.
	 *
	 * @param int $product_id Parent product ID.
	 * @return string JSON string or empty string.
	 */
	private function get_product_variations_json( int $product_id ): string {
		if ( $product_id <= 0 || ! function_exists( 'wc_get_product' ) || ! class_exists( 'WC_Product' ) ) {
			return '';
		}

		$wc_product = wc_get_product( $product_id );
		if ( ! $wc_product || ! method_exists( $wc_product, 'is_type' ) || ! $wc_product->is_type( 'variable' ) ) {
			return '';
		}

		$variation_ids = method_exists( $wc_product, 'get_children' ) ? $wc_product->get_children() : [];
		if ( empty( $variation_ids ) ) {
			return '';
		}

		$variations_data = [];

		foreach ( $variation_ids as $variation_id ) {
			$variation_id = absint( $variation_id );
			if ( $variation_id <= 0 ) {
				continue;
			}

			$variation_post = get_post( $variation_id );
			if ( ! $variation_post || 'product_variation' !== $variation_post->post_type ) {
				continue;
			}

			$raw_meta = get_post_meta( $variation_id );
			$meta     = [];
			foreach ( $raw_meta as $vk => $vv ) {
				if ( ! isset( $vv[0] ) ) {
					continue;
				}
				$meta[ $vk ] = maybe_unserialize( $vv[0] );
			}

			// Add portable URL for the variation thumbnail when present.
			if ( isset( $meta['_thumbnail_id'] ) && is_numeric( $meta['_thumbnail_id'] ) ) {
				$url = wp_get_attachment_url( (int) $meta['_thumbnail_id'] );
				if ( $url ) {
					$meta['_thumbnail_url'] = $url;
				}
			}

			$variations_data[] = [
				'ID'          => (int) $variation_post->ID,
				'post_title'  => (string) $variation_post->post_title,
				'post_name'   => (string) $variation_post->post_name,
				'post_status' => (string) $variation_post->post_status,
				'menu_order'  => (int) $variation_post->menu_order,
				'meta'        => $meta,
			];
		}

		if ( empty( $variations_data ) ) {
			return '';
		}

		$json = wp_json_encode( $variations_data );
		return $json ? $json : '';
	}

	/**
	 * Evaluate a filter condition
	 *
	 * @param mixed  $field_value The value to test
	 * @param string $condition   The condition type
	 * @param mixed  $test_value  The value to test against
	 * @return bool True if condition matches
	 */
	protected function evaluate_condition( $field_value, $condition, $test_value ) {
		switch ( $condition ) {
			case 'equals':
				return $field_value == $test_value;

			case 'not_equals':
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
				return $field_value > $test_value;

			case 'less':
				return $field_value < $test_value;

			case 'equals_or_greater':
				return $field_value >= $test_value;

			case 'equals_or_less':
				return $field_value <= $test_value;

			case 'between':
				$values = array_map( 'trim', explode( ',', $test_value ) );
				if ( count( $values ) === 2 ) {
					return $field_value >= $values[0] && $field_value <= $values[1];
				}
				return true;

			default:
				return true;
		}
	}
}

<?php
/**
 * Post Exporter
 *
 * Handles exporting WordPress posts, pages, and custom post types
 *
 * @package WP_AIE\Model\Export
 */

namespace WP_AIE\Model\Export;

/**
 * Post Exporter Class
 *
 * Exports posts with support for:
 * - Multiple post types (post, page, custom)
 * - Filtering by status, date, author, taxonomy
 * - Post meta export
 * - Taxonomy terms export
 * - Featured image export
 *
 * @package WP_AIE\Model\Export
 */
class Post_Exporter extends Abstract_Exporter {

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
		return __( 'Export WordPress posts, pages, and custom post types', 'wp-advanced-import-export' );
	}

	/**
	 * Get supported export filters
	 *
	 * @return array
	 */
	public function get_supported_filters() {
		return [
			'post_type'     => __( 'Post type (post, page, or custom post type)', 'wp-advanced-import-export' ),
			'post_status'   => __( 'Post status (publish, draft, pending, etc.)', 'wp-advanced-import-export' ),
			'author'        => __( 'Author ID or array of IDs', 'wp-advanced-import-export' ),
			'date_query'    => __( 'Date query parameters', 'wp-advanced-import-export' ),
			'tax_query'     => __( 'Taxonomy query parameters', 'wp-advanced-import-export' ),
			'meta_query'    => __( 'Meta query parameters', 'wp-advanced-import-export' ),
			'custom_fields' => __( 'Custom field filters: array of [name, value, condition]', 'wp-advanced-import-export' ),
			'taxonomy'      => __( 'Taxonomy filters: array of [taxonomy, terms, condition]', 'wp-advanced-import-export' ),
			's'             => __( 'Search query', 'wp-advanced-import-export' ),
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
			'author_name',
			'author_email',
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

		$query_args                   = $this->build_query_args( $options );
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
		// Debug logging
		if ( ! empty( $options['taxonomy'] ) ) {
			error_log( 'Post_Exporter: Received taxonomy filters: ' . print_r( $options['taxonomy'], true ) );
		}

		$args = [
			'post_type'      => $options['post_type'] ?? 'any',  // Changed from 'post' to 'any' to show all post types by default
			'post_status'    => $options['post_status'] ?? 'any',
			'posts_per_page' => $options['limit'] ?? -1,
			'offset'         => $options['offset'] ?? 0,
			'orderby'        => $options['orderby'] ?? 'date',
			'order'          => $options['order'] ?? 'DESC',
		];

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

		// Tax query
		if ( ! empty( $options['tax_query'] ) ) {
			$args['tax_query'] = $options['tax_query'];
		}

		// Meta query
		if ( ! empty( $options['meta_query'] ) ) {
			$args['meta_query'] = $options['meta_query'];
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

		// Initialize meta_query if not exists
		if ( ! isset( $args['meta_query'] ) ) {
			$args['meta_query'] = [];
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
		error_log( 'apply_taxonomy_filters called with filters: ' . print_r( $filters, true ) );

		if ( empty( $filters ) || ! is_array( $filters ) ) {
			error_log( 'apply_taxonomy_filters: filters empty or not array' );
			return;
		}

		// Initialize tax_query if not exists
		if ( ! isset( $args['tax_query'] ) ) {
			$args['tax_query'] = [];
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

		error_log( 'apply_taxonomy_filters: Final tax_query: ' . print_r( $args['tax_query'], true ) );
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
			if ( $field === 'featured' ) {
				if ( ! isset( $args['tax_query'] ) ) {
					$args['tax_query'] = [];
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
			if ( $field === 'visibility' ) {
				if ( ! isset( $args['tax_query'] ) ) {
					$args['tax_query'] = [];
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
				// product_type is actually a taxonomy in WooCommerce
				if ( ! isset( $args['tax_query'] ) ) {
					$args['tax_query'] = [];
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
				// ID filtering
				if ( $condition === 'equals' ) {
					$args['post__in'] = [ absint( $value ) ];
				} elseif ( $condition === 'not_equals' ) {
					$args['post__not_in'] = [ absint( $value ) ];
				} elseif ( $condition === 'in' ) {
					$args['post__in'] = array_map( 'absint', explode( ',', $value ) );
				} elseif ( $condition === 'not_in' ) {
					$args['post__not_in'] = array_map( 'absint', explode( ',', $value ) );
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
				$taxonomy = $taxonomy_map[ $field ];                // Initialize tax_query if not exists
				if ( ! isset( $args['tax_query'] ) ) {
					$args['tax_query'] = [];
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
						$default_category = get_option( 'default_category' );

						// For categories, exclude default category
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

		if ( ! empty( $meta_query ) ) {
			$args['meta_query'] = $meta_query;
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

			switch ( $condition ) {
				case 'equals':
					if ( $is_date_field ) {
						// For date fields, compare only the date part (ignore time)
						$where .= $wpdb->prepare( " AND DATE({$wpdb->posts}.{$field}) = %s", $value );
					} else {
						$where .= $wpdb->prepare( " AND {$wpdb->posts}.{$field} = %s", $value );
					}
					break;
				case 'not_equals':
					if ( $is_date_field ) {
						// For date fields, compare only the date part (ignore time)
						$where .= $wpdb->prepare( " AND DATE({$wpdb->posts}.{$field}) != %s", $value );
					} else {
						$where .= $wpdb->prepare( " AND {$wpdb->posts}.{$field} != %s", $value );
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
					if ( ! empty( $values ) ) {
						$placeholders = implode( ', ', array_fill( 0, count( $values ), '%s' ) );
						$where       .= $wpdb->prepare( " AND {$wpdb->posts}.{$field} IN ($placeholders)", $values );
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
					if ( ! empty( $values ) ) {
						$placeholders = implode( ', ', array_fill( 0, count( $values ), '%s' ) );
						$where       .= $wpdb->prepare( " AND {$wpdb->posts}.{$field} NOT IN ($placeholders)", $values );
					}
					break;
				case 'contains':
					$where .= $wpdb->prepare( " AND {$wpdb->posts}.{$field} LIKE %s", '%' . $wpdb->esc_like( $value ) . '%' );
					break;
				case 'not_contains':
					$where .= $wpdb->prepare( " AND {$wpdb->posts}.{$field} NOT LIKE %s", '%' . $wpdb->esc_like( $value ) . '%' );
					break;
				case 'is_empty':
					$where .= " AND ({$wpdb->posts}.{$field} IS NULL OR {$wpdb->posts}.{$field} = '')";
					break;
				case 'is_not_empty':
					$where .= " AND ({$wpdb->posts}.{$field} IS NOT NULL AND {$wpdb->posts}.{$field} != '')";
					break;
				case 'greater':
					if ( $is_date_field ) {
						// For date fields, compare only the date part
						$where .= $wpdb->prepare( " AND DATE({$wpdb->posts}.{$field}) > %s", $value );
					} else {
						$where .= $wpdb->prepare( " AND {$wpdb->posts}.{$field} > %s", $value );
					}
					break;
				case 'less':
					if ( $is_date_field ) {
						// For date fields, compare only the date part
						$where .= $wpdb->prepare( " AND DATE({$wpdb->posts}.{$field}) < %s", $value );
					} else {
						$where .= $wpdb->prepare( " AND {$wpdb->posts}.{$field} < %s", $value );
					}
					break;
				case 'equals_or_greater':
					if ( $is_date_field ) {
						// For date fields, compare only the date part
						$where .= $wpdb->prepare( " AND DATE({$wpdb->posts}.{$field}) >= %s", $value );
					} else {
						$where .= $wpdb->prepare( " AND {$wpdb->posts}.{$field} >= %s", $value );
					}
					break;
				case 'equals_or_less':
					if ( $is_date_field ) {
						// For date fields, compare only the date part
						$where .= $wpdb->prepare( " AND DATE({$wpdb->posts}.{$field}) <= %s", $value );
					} else {
						$where .= $wpdb->prepare( " AND {$wpdb->posts}.{$field} <= %s", $value );
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

			switch ( $condition ) {
				case 'equals':
					$where .= $wpdb->prepare( " AND {$wpdb->users}.{$user_field} = %s", $value );
					break;
				case 'not_equals':
					$where .= $wpdb->prepare( " AND {$wpdb->users}.{$user_field} != %s", $value );
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
					if ( ! empty( $values ) ) {
						$placeholders = implode( ', ', array_fill( 0, count( $values ), '%s' ) );
						$where       .= $wpdb->prepare( " AND {$wpdb->users}.{$user_field} IN ($placeholders)", $values );
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
					if ( ! empty( $values ) ) {
						$placeholders = implode( ', ', array_fill( 0, count( $values ), '%s' ) );
						$where       .= $wpdb->prepare( " AND {$wpdb->users}.{$user_field} NOT IN ($placeholders)", $values );
					}
					break;
				case 'contains':
					$where .= $wpdb->prepare( " AND {$wpdb->users}.{$user_field} LIKE %s", '%' . $wpdb->esc_like( $value ) . '%' );
					break;
				case 'not_contains':
					$where .= $wpdb->prepare( " AND {$wpdb->users}.{$user_field} NOT LIKE %s", '%' . $wpdb->esc_like( $value ) . '%' );
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

		return $data;
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
			// Skip internal WordPress meta
			if ( '_' === substr( $key, 0, 1 ) ) {
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

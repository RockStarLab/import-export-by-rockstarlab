<?php
/**
 * Taxonomy Exporter
 *
 * Handles exporting WordPress taxonomy terms
 *
 * @package WP_AIE\Model\Export
 */

namespace WP_AIE\Model\Export;

/**
 * Taxonomy Exporter Class
 *
 * Exports taxonomy terms with support for:
 * - Multiple taxonomies (categories, tags, custom)
 * - Hierarchical structure
 * - Term meta export
 * - Parent/child relationships
 *
 * @package WP_AIE\Model\Export
 */
class Taxonomy_Exporter extends Abstract_Exporter {

	/**
	 * Get exporter name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'taxonomy';
	}

	/**
	 * Get exporter description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Export WordPress taxonomy terms', 'wp-advanced-import-export' );
	}

	/**
	 * Get supported export filters
	 *
	 * @return array
	 */
	public function get_supported_filters() {
		return [
			'taxonomy'      => __( 'Taxonomy name', 'wp-advanced-import-export' ),
			'hide_empty'    => __( 'Hide empty terms', 'wp-advanced-import-export' ),
			'parent'        => __( 'Parent term ID', 'wp-advanced-import-export' ),
			'search'        => __( 'Search query', 'wp-advanced-import-export' ),
			'custom_fields' => __( 'Custom field filters: array of [name, value, condition]', 'wp-advanced-import-export' ),
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
			'term_id',
			'name',
			'slug',
			'term_group',
			'term_taxonomy_id',
			'taxonomy',
			'description',
			'parent',
			'count',
			'term_meta',
		];
	}

	/**
	 * Get default export fields
	 *
	 * @return array
	 */
	public function get_default_fields() {
		return [
			'term_id',
			'name',
			'slug',
			'taxonomy',
			'description',
			'parent',
			'count',
		];
	}

	/**
	 * Get total count of items
	 *
	 * @param array $options Optional. Export filters
	 * @return int
	 */
	public function get_count( $options = [] ) {
		// Check if a specific taxonomy is requested
		if ( ! empty( $options['taxonomy'] ) && is_string( $options['taxonomy'] ) ) {
			// Count terms in the specific taxonomy
			$query_args           = $this->build_query_args( $options );
			$query_args['fields'] = 'count';

			$count = get_terms( $query_args );

			return is_wp_error( $count ) ? 0 : (int) $count;
		}

		// Count unique taxonomies (not terms)
		$taxonomies = get_taxonomies(
			[
				'public'   => true,
				'_builtin' => false,
			],
			'names'
		);

		// Include built-in taxonomies
		$builtin_taxonomies = [ 'category', 'post_tag', 'nav_menu', 'link_category', 'post_format' ];
		$all_taxonomies     = array_merge( $builtin_taxonomies, $taxonomies );

		// Apply filters if provided
		if ( ! empty( $options['filters'] ) && is_array( $options['filters'] ) ) {
			foreach ( $options['filters'] as $filter ) {
				if ( empty( $filter['field'] ) || empty( $filter['condition'] ) ) {
					continue;
				}

				$field     = $filter['field'];
				$condition = $filter['condition'];
				$value     = $filter['value'] ?? '';

				// Filter by taxonomy name
				if ( $field === 'name' ) {
					$all_taxonomies = array_filter(
						$all_taxonomies,
						function ( $tax_name ) use ( $condition, $value ) {
							$tax_obj = get_taxonomy( $tax_name );
							if ( ! $tax_obj ) {
								return false;
							}
							$name = $tax_obj->label;
							return $this->apply_string_condition( $name, $condition, $value );
						}
					);
				}

				// Filter by taxonomy slug
				if ( $field === 'slug' || $field === 'taxonomy' ) {
					$all_taxonomies = array_filter(
						$all_taxonomies,
						function ( $tax_name ) use ( $condition, $value ) {
							return $this->apply_string_condition( $tax_name, $condition, $value );
						}
					);
				}
			}
		}

		return count( $all_taxonomies );
	}

	/**
	 * Get data based on export options
	 *
	 * @param array $options Export options
	 * @return array|WP_Error
	 */
	public function get_data( $options = [] ) {
		$query_args = $this->build_query_args( $options );

		$this->log_info( 'Querying taxonomy terms', $query_args );

		$terms = get_terms( $query_args );

		if ( is_wp_error( $terms ) ) {
			return $terms;
		}

		if ( empty( $terms ) ) {
			return [];
		}

		$data = [];
		foreach ( $terms as $term ) {
			$data[] = $this->format_term( $term, $options );
		}

		return $data;
	}

	/**
	 * Format term data
	 *
	 * @param \WP_Term $term    Term object
	 * @param array    $options Export options
	 * @return array
	 */
	protected function format_term( $term, $options ) {
		$fields = $options['fields'] ?? $this->get_default_fields();
		$data   = [];

		foreach ( $fields as $field ) {
			switch ( $field ) {
				case 'term_id':
					$data['term_id'] = $term->term_id;
					break;

				case 'name':
					$data['name'] = $term->name;
					break;

				case 'slug':
					$data['slug'] = $term->slug;
					break;

				case 'term_group':
					$data['term_group'] = $term->term_group;
					break;

				case 'term_taxonomy_id':
					$data['term_taxonomy_id'] = $term->term_taxonomy_id;
					break;

				case 'taxonomy':
					$data['taxonomy'] = $term->taxonomy;
					break;

				case 'description':
					$data['description'] = $term->description;
					break;

				case 'parent':
					$data['parent'] = $term->parent;
					break;

				case 'count':
					$data['count'] = $term->count;
					break;

				case 'term_meta':
					$data['term_meta'] = $this->get_term_meta( $term->term_id, $options );
					break;

				default:
					// Allow custom fields via filter
					$data[ $field ] = apply_filters( 'aie_taxonomy_export_field_value', '', $field, $term, $options );
					break;
			}
		}

		return apply_filters( 'aie_taxonomy_export_data', $data, $term, $options );
	}

	/**
	 * Get term meta data
	 *
	 * @param int   $term_id Term ID
	 * @param array $options Export options
	 * @return array
	 */
	protected function get_term_meta( $term_id, $options ) {
		$meta = get_term_meta( $term_id );

		if ( empty( $meta ) ) {
			return [];
		}

		$formatted_meta = [];
		foreach ( $meta as $key => $values ) {
			// Skip keys starting with _
			if ( strpos( $key, '_' ) === 0 ) {
				continue;
			}

			$formatted_meta[ $key ] = maybe_unserialize( $values[0] );
		}

		return $formatted_meta;
	}

	/**
	 * Build query arguments from options
	 *
	 * @param array $options Export options
	 * @return array
	 */
	protected function build_query_args( $options ) {
		$args = [
			'taxonomy'   => $options['taxonomy'] ?? 'category',
			'hide_empty' => isset( $options['hide_empty'] ) ? (bool) $options['hide_empty'] : false,
			'number'     => $options['limit'] ?? 0,
			'offset'     => $options['offset'] ?? 0,
			'orderby'    => $options['orderby'] ?? 'name',
			'order'      => $options['order'] ?? 'ASC',
		];

		// Parent filter
		if ( isset( $options['parent'] ) ) {
			$args['parent'] = (int) $options['parent'];
		}

		// Search query
		if ( ! empty( $options['search'] ) ) {
			$args['search'] = $options['search'];
		}

		// Custom field filters
		if ( ! empty( $options['custom_fields'] ) && is_array( $options['custom_fields'] ) ) {
			$this->apply_custom_field_filters( $args, $options['custom_fields'] );
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

			// Handle term fields
			if ( $field === 'term_id' ) {
				if ( $condition === 'equals' ) {
					$args['include'] = [ absint( $value ) ];
				} elseif ( $condition === 'not_equals' ) {
					$args['exclude'] = [ absint( $value ) ];
				} elseif ( $condition === 'in' ) {
					$args['include'] = array_map( 'absint', explode( ',', $value ) );
				} elseif ( $condition === 'not_in' ) {
					$args['exclude'] = array_map( 'absint', explode( ',', $value ) );
				}
				continue;
			}

			if ( $field === 'name' ) {
				if ( $condition === 'contains' || $condition === 'equals' ) {
					$args['search'] = sanitize_text_field( $value );
				}
				continue;
			}

			if ( $field === 'slug' ) {
				if ( $condition === 'equals' ) {
					$args['slug'] = sanitize_title( $value );
				} elseif ( $condition === 'in' ) {
					$args['slug'] = array_map( 'sanitize_title', explode( ',', $value ) );
				}
				continue;
			}

			if ( $field === 'parent' ) {
				if ( $condition === 'equals' ) {
					$args['parent'] = absint( $value );
				}
				continue;
			}
		}
	}

	/**
	 * Apply string condition to a value
	 *
	 * @param string $haystack The value to check
	 * @param string $condition The condition to apply
	 * @param string $needle The value to compare against
	 * @return bool
	 */
	protected function apply_string_condition( $haystack, $condition, $needle ) {
		$haystack = strtolower( $haystack );
		$needle   = strtolower( $needle );

		switch ( $condition ) {
			case 'equals':
				return $haystack === $needle;
			case 'not_equals':
				return $haystack !== $needle;
			case 'contains':
				return strpos( $haystack, $needle ) !== false;
			case 'not_contains':
				return strpos( $haystack, $needle ) === false;
			case 'starts_with':
				return strpos( $haystack, $needle ) === 0;
			case 'ends_with':
				return substr( $haystack, -strlen( $needle ) ) === $needle;
			case 'in':
				$values = array_map( 'trim', explode( ',', $needle ) );
				return in_array( $haystack, $values, true );
			case 'not_in':
				$values = array_map( 'trim', explode( ',', $needle ) );
				return ! in_array( $haystack, $values, true );
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
		// Validate taxonomy if provided
		if ( ! empty( $options['taxonomy'] ) ) {
			if ( ! taxonomy_exists( $options['taxonomy'] ) ) {
				return new \WP_Error(
					'invalid_taxonomy',
					sprintf(
						/* translators: %s: taxonomy name */
						__( 'Invalid taxonomy: %s', 'wp-advanced-import-export' ),
						$options['taxonomy']
					)
				);
			}
		}

		return true;
	}
}

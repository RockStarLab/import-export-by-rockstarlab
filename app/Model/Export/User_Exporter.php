<?php
/**
 * User Exporter
 *
 * Handles exporting WordPress users
 *
 * @package WP_AIE\Model\Export
 */

namespace WP_AIE\Model\Export;

/**
 * User Exporter Class
 *
 * Exports users with support for:
 * - Role filtering
 * - User meta export
 * - Custom field filters
 *
 * @package WP_AIE\Model\Export
 */
class User_Exporter extends Abstract_Exporter {

	/**
	 * Get exporter name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'users';
	}

	/**
	 * Get exporter description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Export WordPress users', 'wp-advanced-import-export' );
	}

	/**
	 * Get supported export filters
	 *
	 * @return array
	 */
	public function get_supported_filters() {
		return [
			'role'          => __( 'User role', 'wp-advanced-import-export' ),
			'role__in'      => __( 'Array of roles', 'wp-advanced-import-export' ),
			'role__not_in'  => __( 'Array of roles to exclude', 'wp-advanced-import-export' ),
			'meta_query'    => __( 'Meta query parameters', 'wp-advanced-import-export' ),
			'custom_fields' => __( 'Custom field filters: array of [name, value, condition]', 'wp-advanced-import-export' ),
			'search'        => __( 'Search query', 'wp-advanced-import-export' ),
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
			'user_login',
			'user_email',
			'user_nicename',
			'user_url',
			'user_registered',
			'display_name',
			'first_name',
			'last_name',
			'nickname',
			'description',
			'roles',
			'user_meta',
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
			'user_login',
			'user_email',
			'display_name',
			'first_name',
			'last_name',
			'user_registered',
			'roles',
		];
	}

	/**
	 * Get total count of items
	 *
	 * @param array $options Optional. Export filters
	 * @return int
	 */
	public function get_count( $options = [] ) {
		$query_args                = $this->build_query_args( $options );
		$query_args['fields']      = 'all';
		$query_args['number']      = -1;
		$query_args['count_total'] = false;

		// Apply custom ID filters if present
		$custom_id_filters = $query_args['_custom_id_filters'] ?? [];
		unset( $query_args['_custom_id_filters'] );

		// Extract other filters for manual checking
		$other_filters = $query_args['_other_filters'] ?? [];
		unset( $query_args['_other_filters'] );

		if ( ! empty( $custom_id_filters ) ) {
			add_action(
				'pre_user_query',
				function ( $query ) use ( $custom_id_filters ) {
					$this->apply_custom_id_filters( $query, $custom_id_filters );
				}
			);
		}

		$user_query = new \WP_User_Query( $query_args );

		// Remove the filter after query
		if ( ! empty( $custom_id_filters ) ) {
			remove_all_actions( 'pre_user_query' );
		}

		$users = $user_query->get_results();

		// If no other filters, return count directly
		if ( empty( $other_filters ) ) {
			return count( $users );
		}

		// Apply manual filtering for non-ID filters
		$count = 0;
		foreach ( $users as $user ) {
			$passes_all_filters = true;

			foreach ( $other_filters as $filter ) {
				$field_value = $this->get_user_field_value( $user, $filter['field'] );

				if ( ! $this->check_condition( $field_value, $filter['condition'], $filter['value'] ) ) {
					$passes_all_filters = false;
					break;
				}
			}

			if ( $passes_all_filters ) {
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
		$query_args = $this->build_query_args( $options );

		$this->log_info( 'Querying users', $query_args );

		// Apply custom ID filters if present
		$custom_id_filters = $query_args['_custom_id_filters'] ?? [];
		unset( $query_args['_custom_id_filters'] );

		// Extract other filters for manual checking
		$other_filters = $query_args['_other_filters'] ?? [];
		unset( $query_args['_other_filters'] );

		if ( ! empty( $custom_id_filters ) ) {
			add_action(
				'pre_user_query',
				function ( $query ) use ( $custom_id_filters ) {
					$this->apply_custom_id_filters( $query, $custom_id_filters );
				}
			);
		}

		$user_query = new \WP_User_Query( $query_args );
		$users      = $user_query->get_results();

		// Remove the filter after query
		if ( ! empty( $custom_id_filters ) ) {
			remove_all_actions( 'pre_user_query' );
		}

		if ( empty( $users ) ) {
			return [];
		}

		$data = [];
		foreach ( $users as $user ) {
			// Apply manual filtering for non-ID filters
			if ( ! empty( $other_filters ) ) {
				$passes_all_filters = true;

				foreach ( $other_filters as $filter ) {
					$field_value = $this->get_user_field_value( $user, $filter['field'] );

					if ( ! $this->check_condition( $field_value, $filter['condition'], $filter['value'] ) ) {
						$passes_all_filters = false;
						break;
					}
				}

				if ( ! $passes_all_filters ) {
					continue;
				}
			}

			$data[] = $this->format_user( $user, $options );
		}

		return $data;
	}

	/**
	 * Format user data
	 *
	 * @param \WP_User $user    User object
	 * @param array    $options Export options
	 * @return array
	 */
	protected function format_user( $user, $options ) {
		$fields = $options['fields'] ?? $this->get_default_fields();
		$data   = [];

		// Check if ID should be forced (for Content Updater)
		$force_include_id = $options['force_include_id'] ?? false;

		// Add ID if requested or forced
		if ( in_array( 'ID', $fields, true ) || $force_include_id ) {
			$data['ID'] = $user->ID;
		}

		foreach ( $fields as $field ) {
			switch ( $field ) {
				case 'ID':
					// Already handled above, skip
					break;

				case 'user_login':
					$data['user_login'] = $user->user_login;
					break;

				case 'user_email':
					$data['user_email'] = $user->user_email;
					break;

				case 'user_nicename':
					$data['user_nicename'] = $user->user_nicename;
					break;

				case 'user_url':
					$data['user_url'] = $user->user_url;
					break;

				case 'user_registered':
					$data['user_registered'] = $user->user_registered;
					break;

				case 'display_name':
					$data['display_name'] = $user->display_name;
					break;

				case 'first_name':
					$data['first_name'] = get_user_meta( $user->ID, 'first_name', true );
					break;

				case 'last_name':
					$data['last_name'] = get_user_meta( $user->ID, 'last_name', true );
					break;

				case 'nickname':
					$data['nickname'] = get_user_meta( $user->ID, 'nickname', true );
					break;

				case 'description':
					$data['description'] = get_user_meta( $user->ID, 'description', true );
					break;

				case 'roles':
					$data['roles'] = implode( ',', $user->roles );
					break;

				case 'user_meta':
					$data['user_meta'] = $this->get_user_meta( $user->ID, $options );
					break;

				default:
					// Allow custom fields via filter
					$data[ $field ] = apply_filters( 'aie_user_export_field_value', '', $field, $user, $options );
					break;
			}
		}

		return apply_filters( 'aie_user_export_data', $data, $user, $options );
	}

	/**
	 * Get user meta data
	 *
	 * @param int   $user_id User ID
	 * @param array $options Export options
	 * @return array
	 */
	protected function get_user_meta( $user_id, $options ) {
		$meta = get_user_meta( $user_id );

		if ( empty( $meta ) ) {
			return [];
		}

		$exclude_keys = [
			'session_tokens',
			'capabilities',
			'user_level',
			'dismissed_wp_pointers',
		];

		$exclude_keys = apply_filters( 'aie_user_export_exclude_meta_keys', $exclude_keys );

		$formatted_meta = [];
		foreach ( $meta as $key => $values ) {
			// Skip excluded keys
			if ( in_array( $key, $exclude_keys, true ) ) {
				continue;
			}

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
			'number'  => $options['limit'] ?? -1,
			'offset'  => $options['offset'] ?? 0,
			'orderby' => $options['orderby'] ?? 'ID',
			'order'   => $options['order'] ?? 'ASC',
		];

		// Role filter
		if ( ! empty( $options['role'] ) ) {
			$args['role'] = $options['role'];
		}

		if ( ! empty( $options['role__in'] ) ) {
			$args['role__in'] = $options['role__in'];
		}

		if ( ! empty( $options['role__not_in'] ) ) {
			$args['role__not_in'] = $options['role__not_in'];
		}

		// Search query
		if ( ! empty( $options['search'] ) ) {
			$args['search'] = '*' . $options['search'] . '*';
		}

		// Meta query
		if ( ! empty( $options['meta_query'] ) ) {
			$args['meta_query'] = $options['meta_query'];
		}

		// Custom field filters
		if ( ! empty( $options['custom_fields'] ) && is_array( $options['custom_fields'] ) ) {
			$this->apply_custom_field_filters( $args, $options['custom_fields'] );
		}

		// Process dynamic filters
		if ( ! empty( $options['filters'] ) && is_array( $options['filters'] ) ) {
			$this->log_info( 'Applying dynamic filters', [ 'filters' => $options['filters'] ] );
			$this->apply_dynamic_filters( $args, $options['filters'] );
			// Log resulting args
			$this->log_info( 'Query args after filters', [ 'args' => $args ] );
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
	 * Apply dynamic filters to query args
	 *
	 * @param array $args    Query arguments (by reference)
	 * @param array $filters Dynamic filters
	 */
	protected function apply_dynamic_filters( &$args, $filters ) {
		// Store all filters for manual checking
		if ( ! isset( $args['_other_filters'] ) ) {
			$args['_other_filters'] = [];
		}

		// Group filters by type to avoid conflicts
		$search_filters = [];

		foreach ( $filters as $filter ) {
			if ( empty( $filter['field'] ) || empty( $filter['condition'] ) ) {
				continue;
			}

			$field     = $filter['field'];
			$condition = $filter['condition'];
			$value     = $filter['value'] ?? '';

			// Skip empty values for most conditions (except is_empty/is_not_empty)
			if ( empty( $value ) && ! in_array( $condition, [ 'is_empty', 'is_not_empty' ], true ) ) {
				continue;
			}

			// Store all non-ID filters for manual checking
			if ( $field !== 'ID' ) {
				$args['_other_filters'][] = $filter;
			}

			// Handle user ID field with all conditions
			if ( $field === 'ID' ) {
				if ( $condition === 'equals' ) {
					$args['include'] = [ absint( $value ) ];
				} elseif ( $condition === 'not_equals' ) {
					$args['exclude'] = [ absint( $value ) ];
				} elseif ( $condition === 'in' ) {
					$args['include'] = array_map( 'absint', explode( ',', $value ) );
				} elseif ( $condition === 'not_in' ) {
					$args['exclude'] = array_map( 'absint', explode( ',', $value ) );
				} elseif ( in_array( $condition, [ 'greater', 'less', 'equals_or_greater', 'equals_or_less', 'between' ], true ) ) {
					// For numeric comparisons, we need custom WHERE clause
					if ( ! isset( $args['_custom_id_filters'] ) ) {
						$args['_custom_id_filters'] = [];
					}
					$args['_custom_id_filters'][] = [
						'condition' => $condition,
						'value'     => $value,
					];
				} elseif ( $condition === 'is_not_empty' ) {
					// ID is always not empty, no filter needed
				}
				continue;
			}

			// Handle user_login field
			if ( $field === 'user_login' ) {
				if ( $condition === 'equals' ) {
					$args['login'] = sanitize_text_field( $value );
				} elseif ( in_array( $condition, [ 'contains', 'starts_with', 'ends_with' ], true ) ) {
					$search_filters[] = [
						'field'     => 'user_login',
						'condition' => $condition,
						'value'     => sanitize_text_field( $value ),
					];
				}
				continue;
			}

			// Handle user_email field
			if ( $field === 'user_email' ) {
				if ( in_array( $condition, [ 'equals', 'contains', 'starts_with', 'ends_with' ], true ) ) {
					$search_filters[] = [
						'field'     => 'user_email',
						'condition' => $condition,
						'value'     => sanitize_email( $value ),
					];
				}
				continue;
			}

			// Handle user_nicename field
			if ( $field === 'user_nicename' ) {
				if ( in_array( $condition, [ 'equals', 'contains', 'starts_with', 'ends_with' ], true ) ) {
					$search_filters[] = [
						'field'     => 'user_nicename',
						'condition' => $condition,
						'value'     => sanitize_text_field( $value ),
					];
				}
				continue;
			}

			// Handle display_name field
			if ( $field === 'display_name' ) {
				if ( in_array( $condition, [ 'equals', 'contains', 'starts_with', 'ends_with' ], true ) ) {
					$search_filters[] = [
						'field'     => 'display_name',
						'condition' => $condition,
						'value'     => sanitize_text_field( $value ),
					];
				}
				continue;
			}

			// Handle roles field
			if ( $field === 'roles' || $field === 'role' ) {
				if ( $condition === 'equals' || $condition === 'contains' ) {
					// Single role
					$args['role'] = sanitize_text_field( $value );
				} elseif ( $condition === 'not_equals' || $condition === 'not_contains' ) {
					// Exclude single role
					$args['role__not_in'] = [ sanitize_text_field( $value ) ];
				} elseif ( $condition === 'in' ) {
					// Multiple roles (any of)
					$roles            = array_map( 'trim', explode( ',', $value ) );
					$args['role__in'] = array_map( 'sanitize_text_field', $roles );
				} elseif ( $condition === 'not_in' ) {
					// Exclude multiple roles
					$roles                = array_map( 'trim', explode( ',', $value ) );
					$args['role__not_in'] = array_map( 'sanitize_text_field', $roles );
				}
				continue;
			}

			// Handle user_registered date field
			if ( $field === 'user_registered' ) {
				if ( ! isset( $args['date_query'] ) ) {
					$args['date_query'] = [];
				}

				$date_query = [
					'column' => 'user_registered',
				];

				if ( $condition === 'equals' ) {
					$date = strtotime( $value );
					if ( $date ) {
						$date_query['year']  = gmdate( 'Y', $date );
						$date_query['month'] = gmdate( 'm', $date );
						$date_query['day']   = gmdate( 'd', $date );
					}
				} elseif ( $condition === 'greater' ) {
					$date_query['after'] = $value;
				} elseif ( $condition === 'less' ) {
					$date_query['before'] = $value;
				} elseif ( $condition === 'equals_or_greater' ) {
					$date_query['after']     = $value;
					$date_query['inclusive'] = true;
				} elseif ( $condition === 'equals_or_less' ) {
					$date_query['before']    = $value;
					$date_query['inclusive'] = true;
				} elseif ( $condition === 'between' ) {
					$dates = array_map( 'trim', explode( ',', $value ) );
					if ( count( $dates ) === 2 ) {
						$date_query['after']     = $dates[0];
						$date_query['before']    = $dates[1];
						$date_query['inclusive'] = true;
					}
				}

				if ( count( $date_query ) > 1 ) {
					$args['date_query'][] = $date_query;
				}
				continue;
			}

			// Handle user meta filters (first_name, last_name, nickname, description)
			$user_meta_fields = [ 'first_name', 'last_name', 'nickname', 'description' ];
			if ( in_array( $field, $user_meta_fields, true ) ) {
				if ( ! isset( $args['meta_query'] ) ) {
					$args['meta_query'] = [];
				}

				$meta_query = [
					'key' => $field,
				];

				if ( $condition === 'equals' ) {
					$meta_query['value']   = $value;
					$meta_query['compare'] = '=';
				} elseif ( $condition === 'not_equals' ) {
					$meta_query['value']   = $value;
					$meta_query['compare'] = '!=';
				} elseif ( $condition === 'contains' ) {
					$meta_query['value']   = $value;
					$meta_query['compare'] = 'LIKE';
				} elseif ( $condition === 'not_contains' ) {
					$meta_query['value']   = $value;
					$meta_query['compare'] = 'NOT LIKE';
				} elseif ( $condition === 'starts_with' ) {
					$meta_query['value']   = $value;
					$meta_query['compare'] = 'LIKE';
				} elseif ( $condition === 'ends_with' ) {
					$meta_query['value']   = $value;
					$meta_query['compare'] = 'LIKE';
				} elseif ( $condition === 'is_empty' ) {
					$meta_query['compare'] = 'NOT EXISTS';
				} elseif ( $condition === 'is_not_empty' ) {
					$meta_query['compare'] = 'EXISTS';
				}

				$args['meta_query'][] = $meta_query;
			}
		}

		// Apply search filters (only use the first one to avoid conflicts)
		// If we have multiple search filters, we'll need to use a custom query
		if ( ! empty( $search_filters ) ) {
			$first_filter = $search_filters[0];
			$search_value = $first_filter['value'];

			if ( $first_filter['condition'] === 'starts_with' ) {
				$search_value = $search_value . '*';
			} elseif ( $first_filter['condition'] === 'ends_with' ) {
				$search_value = '*' . $search_value;
			} elseif ( $first_filter['condition'] === 'contains' ) {
				$search_value = '*' . $search_value . '*';
			}

			$args['search']         = $search_value;
			$args['search_columns'] = [ $first_filter['field'] ];
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
	 * Apply custom ID filters to user query
	 *
	 * @param \WP_User_Query $query   User query object
	 * @param array          $filters Custom ID filters
	 */
	protected function apply_custom_id_filters( $query, $filters ) {
		global $wpdb;

		foreach ( $filters as $filter ) {
			$condition = $filter['condition'];
			$value     = $filter['value'];

			if ( $condition === 'greater' ) {
				$query->query_where .= $wpdb->prepare( " AND {$wpdb->users}.ID > %d", absint( $value ) );
			} elseif ( $condition === 'less' ) {
				$query->query_where .= $wpdb->prepare( " AND {$wpdb->users}.ID < %d", absint( $value ) );
			} elseif ( $condition === 'equals_or_greater' ) {
				$query->query_where .= $wpdb->prepare( " AND {$wpdb->users}.ID >= %d", absint( $value ) );
			} elseif ( $condition === 'equals_or_less' ) {
				$query->query_where .= $wpdb->prepare( " AND {$wpdb->users}.ID <= %d", absint( $value ) );
			} elseif ( $condition === 'between' ) {
				$values = array_map( 'absint', explode( ',', $value ) );
				if ( count( $values ) === 2 ) {
					$query->query_where .= $wpdb->prepare( " AND {$wpdb->users}.ID BETWEEN %d AND %d", $values[0], $values[1] );
				}
			}
		}
	}

	/**
	 * Get user field value
	 *
	 * @param WP_User $user       User object
	 * @param string  $field_name Field name
	 * @return mixed Field value
	 */
	protected function get_user_field_value( $user, $field_name ) {
		// Map field names to user properties
		$field_map = array(
			'ID'              => 'ID',
			'user_login'      => 'user_login',
			'user_email'      => 'user_email',
			'user_nicename'   => 'user_nicename',
			'display_name'    => 'display_name',
			'user_registered' => 'user_registered',
		);

		// Check if it's a standard field
		if ( isset( $field_map[ $field_name ] ) ) {
			$property = $field_map[ $field_name ];
			return $user->$property ?? '';
		}

		// Check if it's a role field
		if ( $field_name === 'role' ) {
			$roles = $user->roles;
			return ! empty( $roles ) ? $roles[0] : '';
		}

		// Check if it's a meta field
		return get_user_meta( $user->ID, $field_name, true );
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
				return stripos( (string) $field_value, (string) $test_value ) !== false;

			case 'not_contains':
				return stripos( (string) $field_value, (string) $test_value ) === false;

			case 'starts_with':
				return stripos( (string) $field_value, (string) $test_value ) === 0;

			case 'ends_with':
				$field_lower = strtolower( (string) $field_value );
				$test_lower  = strtolower( (string) $test_value );
				return substr( $field_lower, -strlen( $test_lower ) ) === $test_lower;

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
				$values = array_map( 'trim', explode( ',', (string) $test_value ) );
				if ( count( $values ) === 2 ) {
					return $field_value >= $values[0] && $field_value <= $values[1];
				}
				return true;

			case 'in':
				$values = array_map( 'trim', explode( ',', (string) $test_value ) );
				return in_array( $field_value, $values, false ); // Non-strict for flexibility

			case 'not_in':
				$values = array_map( 'trim', explode( ',', (string) $test_value ) );
				return ! in_array( $field_value, $values, false );

			case 'is_empty':
				return empty( $field_value );

			case 'is_not_empty':
				return ! empty( $field_value );

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
		// Validate role if provided
		if ( ! empty( $options['role'] ) ) {
			$valid_roles = array_keys( wp_roles()->roles );
			if ( ! in_array( $options['role'], $valid_roles, true ) ) {
				return new \WP_Error(
					'invalid_role',
					sprintf(
						/* translators: %s: role name */
						__( 'Invalid user role: %s', 'wp-advanced-import-export' ),
						$options['role']
					)
				);
			}
		}

		return true;
	}
}

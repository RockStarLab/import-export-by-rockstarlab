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
			'role'         => __( 'User role', 'wp-advanced-import-export' ),
			'role__in'     => __( 'Array of roles', 'wp-advanced-import-export' ),
			'role__not_in' => __( 'Array of roles to exclude', 'wp-advanced-import-export' ),
			'meta_query'   => __( 'Meta query parameters', 'wp-advanced-import-export' ),
			'search'       => __( 'Search query', 'wp-advanced-import-export' ),
			'orderby'      => __( 'Order by field', 'wp-advanced-import-export' ),
			'order'        => __( 'Order direction (ASC or DESC)', 'wp-advanced-import-export' ),
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
		$query_args['fields']      = 'ID';
		$query_args['number']      = -1;
		$query_args['count_total'] = true;

		// Apply custom ID filters if present
		$custom_id_filters = $query_args['_custom_id_filters'] ?? [];
		unset( $query_args['_custom_id_filters'] );

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

		return $user_query->get_total();
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

		foreach ( $fields as $field ) {
			switch ( $field ) {
				case 'ID':
					$data['ID'] = $user->ID;
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

		// Process dynamic filters
		if ( ! empty( $options['filters'] ) && is_array( $options['filters'] ) ) {
			// Log filters for debugging
			$this->log_info( 'Applying dynamic filters', [ 'filters' => $options['filters'] ] );
			$this->apply_dynamic_filters( $args, $options['filters'] );
			// Log resulting args
			$this->log_info( 'Query args after filters', [ 'args' => $args ] );
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

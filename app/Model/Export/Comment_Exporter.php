<?php
/**
 * Comment Exporter
 *
 * Handles exporting WordPress comments
 *
 * @package WP_AIE\Model\Export
 */

namespace WP_AIE\Model\Export;

/**
 * Comment Exporter Class
 *
 * Exports comments with support for:
 * - Status filtering (approved, pending, spam, trash)
 * - Type filtering
 * - Post filtering
 * - Author filtering
 * - Date filtering
 *
 * @package WP_AIE\Model\Export
 */
class Comment_Exporter extends Abstract_Exporter {

	/**
	 * Get exporter name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'comments';
	}

	/**
	 * Get exporter description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Export WordPress comments', 'wp-advanced-import-export' );
	}

	/**
	 * Get supported export filters
	 *
	 * @return array
	 */
	public function get_supported_filters() {
		return [
			'status'        => __( 'Comment status', 'wp-advanced-import-export' ),
			'type'          => __( 'Comment type', 'wp-advanced-import-export' ),
			'post_id'       => __( 'Post ID', 'wp-advanced-import-export' ),
			'author'        => __( 'Author name or email', 'wp-advanced-import-export' ),
			'date_query'    => __( 'Date query parameters', 'wp-advanced-import-export' ),
			'meta_query'    => __( 'Meta query parameters', 'wp-advanced-import-export' ),
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
			'comment_ID',
			'comment_post_ID',
			'comment_author',
			'comment_author_email',
			'comment_author_url',
			'comment_author_IP',
			'comment_date',
			'comment_date_gmt',
			'comment_content',
			'comment_karma',
			'comment_approved',
			'comment_agent',
			'comment_type',
			'comment_parent',
			'user_id',
			'post_title',
			'comment_meta',
		];
	}

	/**
	 * Get default export fields
	 *
	 * @return array
	 */
	public function get_default_fields() {
		return [
			'comment_ID',
			'comment_post_ID',
			'comment_author',
			'comment_author_email',
			'comment_date',
			'comment_content',
			'comment_approved',
			'comment_type',
			'post_title',
		];
	}

	/**
	 * Get total count of items
	 *
	 * @param array $options Optional. Export filters
	 * @return int
	 */
	public function get_count( $options = [] ) {
		$this->log_info( 'get_count called', [ 'options' => $options ] );

		$query_args          = $this->build_query_args( $options );
		$query_args['count'] = true;

		$this->log_info(
			'get_count query_args',
			[
				'query_args'         => $query_args,
				'custom_filters_set' => ! empty( $this->custom_filters ),
			]
		);

		$comment_query = new \WP_Comment_Query( $query_args );
		$count         = (int) $comment_query->get_comments();

		$this->log_info( 'get_count result', [ 'count' => $count ] );

		// Remove filter after count query
		$this->remove_custom_filters();

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

		$this->log_info( 'Querying comments', $query_args );

		$comment_query = new \WP_Comment_Query( $query_args );
		$comments      = $comment_query->get_comments();

		// Remove filter after data query
		$this->remove_custom_filters();

		if ( empty( $comments ) ) {
			return [];
		}

		$data = [];
		foreach ( $comments as $comment ) {
			$data[] = $this->format_comment( $comment, $options );
		}

		return $data;
	}

	/**
	 * Format comment data
	 *
	 * @param \WP_Comment $comment Comment object
	 * @param array       $options Export options
	 * @return array
	 */
	protected function format_comment( $comment, $options ) {
		$fields = $options['fields'] ?? $this->get_default_fields();
		$data   = [];

		foreach ( $fields as $field ) {
			switch ( $field ) {
				case 'comment_ID':
					$data['comment_ID'] = $comment->comment_ID;
					break;

				case 'comment_post_ID':
					$data['comment_post_ID'] = $comment->comment_post_ID;
					break;

				case 'comment_author':
					$data['comment_author'] = $comment->comment_author;
					break;

				case 'comment_author_email':
					$data['comment_author_email'] = $comment->comment_author_email;
					break;

				case 'comment_author_url':
					$data['comment_author_url'] = $comment->comment_author_url;
					break;

				case 'comment_author_IP':
					$data['comment_author_IP'] = $comment->comment_author_IP;
					break;

				case 'comment_date':
					$data['comment_date'] = $comment->comment_date;
					break;

				case 'comment_date_gmt':
					$data['comment_date_gmt'] = $comment->comment_date_gmt;
					break;

				case 'comment_content':
					$data['comment_content'] = $comment->comment_content;
					break;

				case 'comment_karma':
					$data['comment_karma'] = $comment->comment_karma;
					break;

				case 'comment_approved':
					$data['comment_approved'] = $comment->comment_approved;
					break;

				case 'comment_agent':
					$data['comment_agent'] = $comment->comment_agent;
					break;

				case 'comment_type':
					$data['comment_type'] = $comment->comment_type;
					break;

				case 'comment_parent':
					$data['comment_parent'] = $comment->comment_parent;
					break;

				case 'user_id':
					$data['user_id'] = $comment->user_id;
					break;

				case 'post_title':
					$post               = get_post( $comment->comment_post_ID );
					$data['post_title'] = $post ? $post->post_title : '';
					break;

				case 'comment_meta':
					$data['comment_meta'] = $this->get_comment_meta( $comment->comment_ID, $options );
					break;

				default:
					// Allow custom fields via filter
					$data[ $field ] = apply_filters( 'aie_comment_export_field_value', '', $field, $comment, $options );
					break;
			}
		}

		return apply_filters( 'aie_comment_export_data', $data, $comment, $options );
	}

	/**
	 * Get comment meta data
	 *
	 * @param int   $comment_id Comment ID
	 * @param array $options    Export options
	 * @return array
	 */
	protected function get_comment_meta( $comment_id, $options ) {
		$meta = get_comment_meta( $comment_id );

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
			'number'  => $options['limit'] ?? -1,
			'offset'  => $options['offset'] ?? 0,
			'orderby' => $options['orderby'] ?? 'comment_date',
			'order'   => $options['order'] ?? 'DESC',
		];

		// Status filter
		if ( ! empty( $options['status'] ) ) {
			$args['status'] = $options['status'];
		}

		// Type filter
		if ( ! empty( $options['type'] ) ) {
			$args['type'] = $options['type'];
		}

		// Post ID filter
		if ( ! empty( $options['post_id'] ) ) {
			$args['post_id'] = $options['post_id'];
		}

		// Author filter
		if ( ! empty( $options['author'] ) ) {
			$args['author__in'] = is_array( $options['author'] ) ? $options['author'] : [ $options['author'] ];
		}

		// Date query
		if ( ! empty( $options['date_query'] ) ) {
			$args['date_query'] = $options['date_query'];
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
		global $wpdb;

		$this->log_info( 'Applying dynamic filters', [ 'filters' => $filters ] );

		// Collect search filters separately to avoid conflicts
		$search_filters    = [];
		$custom_id_filters = [];

		foreach ( $filters as $filter ) {
			if ( empty( $filter['field'] ) || empty( $filter['condition'] ) ) {
				continue;
			}

			$field     = $filter['field'];
			$condition = $filter['condition'];
			$value     = isset( $filter['value'] ) ? $filter['value'] : '';

			// Skip empty values unless condition is is_empty or is_not_empty
			if ( ! in_array( $condition, [ 'is_empty', 'is_not_empty' ], true ) && '' === $value ) {
				continue;
			}

			// Handle comment ID with numeric comparisons
			if ( $field === 'comment_ID' ) {
				if ( $condition === 'equals' ) {
					$args['comment__in'] = [ absint( $value ) ];
				} elseif ( $condition === 'not_equals' ) {
					$args['comment__not_in'] = [ absint( $value ) ];
				} elseif ( $condition === 'in' ) {
					$args['comment__in'] = array_map( 'trim', explode( ',', $value ) );
					$args['comment__in'] = array_map( 'absint', $args['comment__in'] );
				} elseif ( $condition === 'not_in' ) {
					$args['comment__not_in'] = array_map( 'trim', explode( ',', $value ) );
					$args['comment__not_in'] = array_map( 'absint', $args['comment__not_in'] );
				} elseif ( in_array( $condition, [ 'greater', 'less', 'equals_or_greater', 'equals_or_less', 'between' ], true ) ) {
					$custom_id_filters[] = [
						'field'     => $field,
						'condition' => $condition,
						'value'     => $value,
					];
				}
				continue;
			}

			// Handle comment status
			if ( $field === 'comment_approved' || $field === 'status' ) {
				if ( $condition === 'equals' ) {
					$args['status'] = sanitize_text_field( $value );
				} elseif ( $condition === 'not_equals' ) {
					$args['status__not_in'] = [ sanitize_text_field( $value ) ];
				} elseif ( $condition === 'in' ) {
					$values         = array_map( 'trim', explode( ',', $value ) );
					$args['status'] = array_map( 'sanitize_text_field', $values );
				} elseif ( $condition === 'not_in' ) {
					$values                 = array_map( 'trim', explode( ',', $value ) );
					$args['status__not_in'] = array_map( 'sanitize_text_field', $values );
				}
				continue;
			}

			// Handle comment type
			if ( $field === 'comment_type' || $field === 'type' ) {
				if ( $condition === 'equals' ) {
					$args['type'] = sanitize_text_field( $value );
				} elseif ( $condition === 'not_equals' ) {
					$args['type__not_in'] = [ sanitize_text_field( $value ) ];
				} elseif ( $condition === 'in' ) {
					$values           = array_map( 'trim', explode( ',', $value ) );
					$args['type__in'] = array_map( 'sanitize_text_field', $values );
				} elseif ( $condition === 'not_in' ) {
					$values               = array_map( 'trim', explode( ',', $value ) );
					$args['type__not_in'] = array_map( 'sanitize_text_field', $values );
				}
				continue;
			}

			// Handle post ID with numeric comparisons
			if ( $field === 'comment_post_ID' || $field === 'post_id' ) {
				if ( $condition === 'equals' ) {
					$args['post_id'] = absint( $value );
				} elseif ( $condition === 'not_equals' ) {
					$args['post__not_in'] = [ absint( $value ) ];
				} elseif ( $condition === 'in' ) {
					$values           = array_map( 'trim', explode( ',', $value ) );
					$args['post__in'] = array_map( 'absint', $values );
				} elseif ( $condition === 'not_in' ) {
					$values               = array_map( 'trim', explode( ',', $value ) );
					$args['post__not_in'] = array_map( 'absint', $values );
				} elseif ( in_array( $condition, [ 'greater', 'less', 'equals_or_greater', 'equals_or_less', 'between' ], true ) ) {
					$custom_id_filters[] = [
						'field'     => 'comment_post_ID',
						'condition' => $condition,
						'value'     => $value,
					];
				}
				continue;
			}

			// Handle user ID with numeric comparisons
			if ( $field === 'user_id' ) {
				if ( $condition === 'equals' ) {
					$args['user_id'] = absint( $value );
				} elseif ( $condition === 'not_equals' ) {
					$custom_id_filters[] = [
						'field'     => $field,
						'condition' => $condition,
						'value'     => $value,
					];
				} elseif ( $condition === 'in' ) {
					$values = array_map( 'trim', explode( ',', $value ) );
					$values = array_map( 'absint', $values );
					// WP_Comment_Query doesn't support user_id__in, use custom filter
					$custom_id_filters[] = [
						'field'     => $field,
						'condition' => 'in',
						'value'     => implode( ',', $values ),
					];
				} elseif ( in_array( $condition, [ 'greater', 'less', 'equals_or_greater', 'equals_or_less', 'between', 'not_in', 'is_empty', 'is_not_empty' ], true ) ) {
					$custom_id_filters[] = [
						'field'     => $field,
						'condition' => $condition,
						'value'     => $value,
					];
				}
				continue;
			}

			// Handle parent comment ID
			if ( $field === 'comment_parent' ) {
				if ( $condition === 'equals' ) {
					$args['parent'] = absint( $value );
				} elseif ( $condition === 'not_equals' ) {
					$args['parent__not_in'] = [ absint( $value ) ];
				} elseif ( $condition === 'in' ) {
					$values             = array_map( 'trim', explode( ',', $value ) );
					$args['parent__in'] = array_map( 'absint', $values );
				} elseif ( $condition === 'not_in' ) {
					$values                 = array_map( 'trim', explode( ',', $value ) );
					$args['parent__not_in'] = array_map( 'absint', $values );
				} elseif ( in_array( $condition, [ 'greater', 'less', 'equals_or_greater', 'equals_or_less', 'between' ], true ) ) {
					$custom_id_filters[] = [
						'field'     => $field,
						'condition' => $condition,
						'value'     => $value,
					];
				}
				continue;
			}

			// Handle karma (numeric field)
			if ( $field === 'comment_karma' ) {
				// WP_Comment_Query doesn't natively support karma filtering, use custom filter
				$custom_id_filters[] = [
					'field'     => $field,
					'condition' => $condition,
					'value'     => $value,
				];
				continue;
			}

			// Handle date field
			if ( $field === 'comment_date' ) {
				if ( ! isset( $args['date_query'] ) ) {
					$args['date_query'] = [];
				}

				if ( $condition === 'equals' ) {
					$args['date_query'][] = [
						'column' => 'comment_date',
						'year'   => date( 'Y', strtotime( $value ) ),
						'month'  => date( 'm', strtotime( $value ) ),
						'day'    => date( 'd', strtotime( $value ) ),
					];
				} elseif ( $condition === 'not_equals' ) {
					$args['date_query'][] = [
						'column'  => 'comment_date',
						'compare' => '!=',
						'year'    => date( 'Y', strtotime( $value ) ),
						'month'   => date( 'm', strtotime( $value ) ),
						'day'     => date( 'd', strtotime( $value ) ),
					];
				} elseif ( $condition === 'greater' ) {
					$args['date_query'][] = [
						'column' => 'comment_date',
						'after'  => $value,
					];
				} elseif ( $condition === 'equals_or_greater' ) {
					$args['date_query'][] = [
						'column'    => 'comment_date',
						'after'     => $value,
						'inclusive' => true,
					];
				} elseif ( $condition === 'less' ) {
					$args['date_query'][] = [
						'column' => 'comment_date',
						'before' => $value,
					];
				} elseif ( $condition === 'equals_or_less' ) {
					$args['date_query'][] = [
						'column'    => 'comment_date',
						'before'    => $value,
						'inclusive' => true,
					];
				} elseif ( $condition === 'between' ) {
					$dates = array_map( 'trim', explode( ',', $value ) );
					if ( count( $dates ) === 2 ) {
						$args['date_query'][] = [
							'column'    => 'comment_date',
							'after'     => $dates[0],
							'before'    => $dates[1],
							'inclusive' => true,
						];
					}
				}
				continue;
			}

			// Handle text search fields (content, author, email, url, IP)
			if ( in_array( $field, [ 'comment_content', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_author_IP' ], true ) ) {
				if ( in_array( $condition, [ 'contains', 'equals', 'not_contains', 'starts_with', 'ends_with' ], true ) ) {
					$search_filters[] = [
						'field'     => $field,
						'condition' => $condition,
						'value'     => $value,
					];
				} elseif ( $condition === 'is_empty' ) {
					$custom_id_filters[] = [
						'field'     => $field,
						'condition' => 'is_empty',
						'value'     => '',
					];
				} elseif ( $condition === 'is_not_empty' ) {
					$custom_id_filters[] = [
						'field'     => $field,
						'condition' => 'is_not_empty',
						'value'     => '',
					];
				}
				continue;
			}
		}

		// Apply search filters (WP_Comment_Query search is limited)
		// We'll use custom WHERE clause for search
		if ( ! empty( $search_filters ) ) {
			$custom_id_filters = array_merge( $custom_id_filters, $search_filters );
		}

		// Apply custom filters via comments_clauses hook
		if ( ! empty( $custom_id_filters ) ) {
			$this->custom_filters = $custom_id_filters;
			add_filter( 'comments_clauses', [ $this, 'apply_custom_comment_filters' ], 99, 2 );
		}

		$this->log_info(
			'Applied filters to query',
			[
				'args'           => $args,
				'custom_filters' => $custom_id_filters,
			]
		);
	}

	/**
	 * Validate export options
	 *
	 * @param array $options Export options
	 * @return true|\WP_Error
	 */
	public function validate_options( $options ) {
		// Validate status if provided
		if ( ! empty( $options['status'] ) ) {
			$valid_statuses = [ 'approve', 'hold', 'spam', 'trash', 'all' ];
			if ( ! in_array( $options['status'], $valid_statuses, true ) ) {
				return new \WP_Error(
					'invalid_status',
					sprintf(
						/* translators: %s: status name */
						__( 'Invalid comment status: %s', 'wp-advanced-import-export' ),
						$options['status']
					)
				);
			}
		}

		return true;
	}

	/**
	 * Custom filters storage for comments_clauses hook
	 *
	 * @var array
	 */
	protected $custom_filters = [];

	/**
	 * Apply custom filters to comment query via WHERE clause
	 *
	 * @param array             $clauses Comment query clauses
	 * @param \WP_Comment_Query $query   Comment query object
	 * @return array Modified clauses
	 */
	public function apply_custom_comment_filters( $clauses, $query ) {
		global $wpdb;

		$this->log_info(
			'apply_custom_comment_filters called',
			[
				'has_custom_filters' => ! empty( $this->custom_filters ),
				'custom_filters'     => $this->custom_filters,
			]
		);

		if ( empty( $this->custom_filters ) ) {
			return $clauses;
		}

		$where_parts = [];

		foreach ( $this->custom_filters as $filter ) {
			$field     = $filter['field'];
			$condition = $filter['condition'];
			$value     = $filter['value'];

			// Map field names to database columns
			$column_map = [
				'comment_ID'           => $wpdb->comments . '.comment_ID',
				'comment_post_ID'      => $wpdb->comments . '.comment_post_ID',
				'comment_author'       => $wpdb->comments . '.comment_author',
				'comment_author_email' => $wpdb->comments . '.comment_author_email',
				'comment_author_url'   => $wpdb->comments . '.comment_author_url',
				'comment_author_IP'    => $wpdb->comments . '.comment_author_IP',
				'comment_date'         => $wpdb->comments . '.comment_date',
				'comment_content'      => $wpdb->comments . '.comment_content',
				'comment_karma'        => $wpdb->comments . '.comment_karma',
				'comment_parent'       => $wpdb->comments . '.comment_parent',
				'user_id'              => $wpdb->comments . '.user_id',
			];

			if ( ! isset( $column_map[ $field ] ) ) {
				continue;
			}

			$column = $column_map[ $field ];

			// Numeric fields
			if ( in_array( $field, [ 'comment_ID', 'comment_post_ID', 'user_id', 'comment_parent', 'comment_karma' ], true ) ) {
				if ( $condition === 'equals' ) {
					$where_parts[] = $wpdb->prepare( "$column = %d", absint( $value ) );
				} elseif ( $condition === 'not_equals' ) {
					$where_parts[] = $wpdb->prepare( "$column != %d", absint( $value ) );
				} elseif ( $condition === 'greater' ) {
					$where_parts[] = $wpdb->prepare( "$column > %d", absint( $value ) );
				} elseif ( $condition === 'equals_or_greater' ) {
					$where_parts[] = $wpdb->prepare( "$column >= %d", absint( $value ) );
				} elseif ( $condition === 'less' ) {
					$where_parts[] = $wpdb->prepare( "$column < %d", absint( $value ) );
				} elseif ( $condition === 'equals_or_less' ) {
					$where_parts[] = $wpdb->prepare( "$column <= %d", absint( $value ) );
				} elseif ( $condition === 'in' ) {
					$values = array_map( 'trim', explode( ',', $value ) );
					$values = array_map( 'absint', $values );
					if ( ! empty( $values ) ) {
						$placeholders  = implode( ',', array_fill( 0, count( $values ), '%d' ) );
						$where_parts[] = $wpdb->prepare( "$column IN ($placeholders)", ...$values );
					}
				} elseif ( $condition === 'not_in' ) {
					$values = array_map( 'trim', explode( ',', $value ) );
					$values = array_map( 'absint', $values );
					if ( ! empty( $values ) ) {
						$placeholders  = implode( ',', array_fill( 0, count( $values ), '%d' ) );
						$where_parts[] = $wpdb->prepare( "$column NOT IN ($placeholders)", ...$values );
					}
				} elseif ( $condition === 'between' ) {
					$values = array_map( 'trim', explode( ',', $value ) );
					if ( count( $values ) === 2 ) {
						$where_parts[] = $wpdb->prepare( "$column BETWEEN %d AND %d", absint( $values[0] ), absint( $values[1] ) );
					}
				} elseif ( $condition === 'is_empty' ) {
					$where_parts[] = "($column = 0 OR $column IS NULL)";
				} elseif ( $condition === 'is_not_empty' ) {
					$where_parts[] = "($column != 0 AND $column IS NOT NULL)";
				}
			}

			// Text fields
			if ( in_array( $field, [ 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_author_IP', 'comment_content' ], true ) ) {
				if ( $condition === 'equals' ) {
					$where_parts[] = $wpdb->prepare( "$column = %s", $value );
				} elseif ( $condition === 'contains' ) {
					$where_parts[] = $wpdb->prepare( "$column LIKE %s", '%' . $wpdb->esc_like( $value ) . '%' );
				} elseif ( $condition === 'not_contains' ) {
					$where_parts[] = $wpdb->prepare( "$column NOT LIKE %s", '%' . $wpdb->esc_like( $value ) . '%' );
				} elseif ( $condition === 'starts_with' ) {
					$where_parts[] = $wpdb->prepare( "$column LIKE %s", $wpdb->esc_like( $value ) . '%' );
				} elseif ( $condition === 'ends_with' ) {
					$where_parts[] = $wpdb->prepare( "$column LIKE %s", '%' . $wpdb->esc_like( $value ) );
				} elseif ( $condition === 'is_empty' ) {
					$where_parts[] = "($column = '' OR $column IS NULL)";
				} elseif ( $condition === 'is_not_empty' ) {
					$where_parts[] = "($column != '' AND $column IS NOT NULL)";
				}
			}
		}

		if ( ! empty( $where_parts ) ) {
			$clauses['where'] .= ' AND (' . implode( ' AND ', $where_parts ) . ')';
			$this->log_info( 'Applied custom WHERE clauses', [ 'where_parts' => $where_parts ] );
		}

		return $clauses;
	}

	/**
	 * Remove custom filters hook
	 */
	protected function remove_custom_filters() {
		if ( ! empty( $this->custom_filters ) ) {
			remove_filter( 'comments_clauses', [ $this, 'apply_custom_comment_filters' ], 99 );
			$this->custom_filters = [];
		}
	}
}

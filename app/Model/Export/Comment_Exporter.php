<?php
/**
 * Comment Exporter
 *
 * Handles exporting WordPress comments
 *
 * @package RockStarLab\ImportExport\Model\Export
 */

namespace RockStarLab\ImportExport\Model\Export;

use RockStarLab\ImportExport\Helper\ACF_Fields;

defined( 'ABSPATH' ) || exit;

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
		return __( 'Export WordPress comments', 'import-export-by-rockstarlab' );
	}

	/**
	 * Get supported export filters
	 *
	 * @return array
	 */
	public function get_supported_filters() {
		return [
			'status'        => __( 'Comment status', 'import-export-by-rockstarlab' ),
			'type'          => __( 'Comment type', 'import-export-by-rockstarlab' ),
			'post_id'       => __( 'Post ID', 'import-export-by-rockstarlab' ),
			'author'        => __( 'Author name or email', 'import-export-by-rockstarlab' ), // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.
			'date_query'    => __( 'Date query parameters', 'import-export-by-rockstarlab' ),
			'meta_query'    => __( 'Meta query parameters', 'import-export-by-rockstarlab' ), // phpcs:ignore WordPress.DB.SlowDBQuery -- meta_query required for filtering.
			'custom_fields' => __( 'Custom field filters: array of [name, value, condition]', 'import-export-by-rockstarlab' ),
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
			'comment_ID',
			'comment_post_ID',
			'post_permalink',
			'post_type',
			'post_slug',
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
			'post_author',
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

		$query_args      = $this->build_query_args( $options );
		$comment_filters = $query_args['_comment_filters'] ?? [];

		unset( $query_args['offset'], $query_args['number'], $query_args['_comment_filters'] );

		$comment_query = new \WP_Comment_Query( $query_args );
		$comments      = $this->filter_comments( $comment_query->get_comments(), $comment_filters );

		$this->log_info( 'get_count result', [ 'count' => count( $comments ) ] );

		return count( $comments );
	}

	/**
	 * Get data based on export options
	 *
	 * @param array $options Export options
	 * @return array|WP_Error
	 */
	public function get_data( $options = [] ) {
		$query_args      = $this->build_query_args( $options );
		$comment_filters = $query_args['_comment_filters'] ?? [];
		$offset          = isset( $query_args['offset'] ) ? max( 0, (int) $query_args['offset'] ) : 0;
		$number          = isset( $query_args['number'] ) ? (int) $query_args['number'] : -1;

		unset( $query_args['_comment_filters'] );
		if ( ! empty( $comment_filters ) ) {
			unset( $query_args['offset'], $query_args['number'] );
		}

		$this->log_info( 'Querying comments', $query_args );

		$comment_query = new \WP_Comment_Query( $query_args );
		$comments      = $this->filter_comments( $comment_query->get_comments(), $comment_filters );

		if ( ! empty( $comment_filters ) && ( $offset > 0 || $number > 0 ) ) {
			$comments = array_slice( $comments, $offset, $number > 0 ? $number : null );
		}

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

		// Check if ID should be forced by the caller.
		$force_include_id = $options['force_include_id'] ?? false;

		// Ensure portable post hints are always exported when comment_post_ID is present.
		if ( is_array( $fields ) && in_array( 'comment_post_ID', $fields, true ) ) {
			foreach ( [ 'post_permalink', 'post_type', 'post_slug' ] as $hint_field ) {
				if ( ! in_array( $hint_field, $fields, true ) ) {
					$fields[] = $hint_field;
				}
			}
		}

		// Parent relationships require a stable per-row source identifier.
		if ( is_array( $fields ) && in_array( 'comment_parent', $fields, true ) && ! in_array( 'comment_ID', $fields, true ) ) {
			$fields[] = 'comment_ID';
		}

		// IMPORTANT: also update exporter options so Abstract_Exporter::select_fields() doesn't drop auto-added fields.
		if ( is_array( $fields ) ) {
			$this->options['fields'] = $fields;
		}

		// Add comment_ID if requested or forced
		if ( in_array( 'comment_ID', $fields, true ) || $force_include_id ) {
			$data['comment_ID'] = $comment->comment_ID;
		}

		foreach ( $fields as $field ) {
			switch ( $field ) {
				case 'comment_ID':
					// Already handled above, skip
					break;

				case 'comment_post_ID':
					$data['comment_post_ID'] = $comment->comment_post_ID;
					break;

				case 'post_permalink':
					$data['post_permalink'] = get_permalink( $comment->comment_post_ID );
					break;

				case 'post_type':
					$post              = get_post( $comment->comment_post_ID );
					$data['post_type'] = $post ? $post->post_type : '';
					break;

				case 'post_slug':
					$post = get_post( $comment->comment_post_ID );
					// Use full path for hierarchical post types (pages) so importer can resolve cross-site IDs.
					$data['post_slug'] = $post ? get_page_uri( (int) $post->ID ) : '';
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

				case 'post_author':
					$post                = get_post( $comment->comment_post_ID );
					$data['post_author'] = $post ? $post->post_author : '';
					break;

				case 'comment_meta':
					$data['comment_meta'] = $this->get_comment_meta( $comment->comment_ID, $options );
					break;

				default:
					if ( strpos( $field, 'acf_' ) === 0 ) {
						$data[ $field ] = ACF_Fields::export_value( 'comment', $comment->comment_ID, substr( $field, 4 ) );
						break;
					}

					// Allow custom fields via filter
					$data[ $field ] = apply_filters( 'rsl_ie_comment_export_field_value', '', $field, $comment, $options );
					break;
			}
		}

		return apply_filters( 'rsl_ie_comment_export_data', $data, $comment, $options );
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
			'offset'  => $options['offset'] ?? 0,
			'orderby' => $options['orderby'] ?? 'comment_date',
			'order'   => $options['order'] ?? 'DESC',
			'status'  => 'all', // Get all comment statuses by default.
		];

		// Apply a positive batch limit. Omitting number returns all comments.
		if ( isset( $options['limit'] ) && $options['limit'] > 0 ) {
			$args['number'] = $options['limit'];
		}

		// Status filter.
		if ( ! empty( $options['status'] ) ) {
			$args['status'] = $options['status'];
		}

		// Type filter.
		if ( ! empty( $options['type'] ) ) {
			$args['type'] = $options['type'];
		}

		// Post ID filter.
		if ( ! empty( $options['post_id'] ) ) {
			$args['post_id'] = $options['post_id'];
		}

		// Author filter.
		if ( ! empty( $options['author'] ) ) {
			$args['author__in'] = is_array( $options['author'] ) ? $options['author'] : [ $options['author'] ];
		}

		// Date query.
		if ( ! empty( $options['date_query'] ) ) {
			$args['date_query'] = $options['date_query'];
		}

		// Meta query // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.
		if ( ! empty( $options['meta_query'] ) ) {
			$args['meta_query'] = $options['meta_query']; // phpcs:ignore WordPress.DB.SlowDBQuery -- meta_query required for filtering.
		}

		// Custom field filters.
		if ( ! empty( $options['custom_fields'] ) && is_array( $options['custom_fields'] ) ) {
			foreach ( $options['custom_fields'] as $filter ) {
				if ( empty( $filter['name'] ) || empty( $filter['condition'] ) ) {
					continue;
				}

				$args['_comment_filters'][] = [
					'field'     => sanitize_text_field( $filter['name'] ),
					'condition' => $filter['condition'],
					'value'     => $filter['value'] ?? '',
				];
			}
		}

		// Process dynamic filters.
		if ( ! empty( $options['filters'] ) && is_array( $options['filters'] ) ) {
			foreach ( $options['filters'] as $filter ) {
				if ( empty( $filter['field'] ) || empty( $filter['condition'] ) ) {
					continue;
				}

				$args['_comment_filters'][] = [
					'field'     => sanitize_text_field( $filter['field'] ),
					'condition' => $filter['condition'],
					'value'     => $filter['value'] ?? '',
				];
			}
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

		// Store all filters for manual checking
		if ( ! isset( $args['_other_filters'] ) ) {
			$args['_other_filters'] = [];
		}

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
				// Only basic conditions (equals, in, not_equals, not_in) use SQL
				// Complex conditions (greater, less, between, etc.) use manual filtering
				if ( in_array( $condition, [ 'greater', 'less', 'equals_or_greater', 'equals_or_less', 'between' ], true ) ) {
					$args['_other_filters'][] = $filter;
					continue; // Handle manually
				}

				// Apply simple ID filters via SQL
				// Accumulate so multiple equals filters OR together
				if ( $condition === 'equals' ) {
					$args['comment__in'] = array_merge( $args['comment__in'] ?? [], [ absint( $value ) ] );
				} elseif ( $condition === 'not_equals' ) {
					$args['comment__not_in'] = array_merge( $args['comment__not_in'] ?? [], [ absint( $value ) ] );
				} elseif ( $condition === 'in' ) {
					$new_ids             = array_map( 'absint', array_map( 'trim', explode( ',', $value ) ) );
					$args['comment__in'] = array_merge( $args['comment__in'] ?? [], $new_ids );
				} elseif ( $condition === 'not_in' ) {
					$new_ids                 = array_map( 'absint', array_map( 'trim', explode( ',', $value ) ) );
					$args['comment__not_in'] = array_merge( $args['comment__not_in'] ?? [], $new_ids );
				}
				continue;
			}

			// Handle comment status
			if ( $field === 'comment_approved' || $field === 'status' ) {
				if ( $condition === 'equals' ) {
					$args['status'] = $this->normalize_comment_status_value( $value );
				} elseif ( $condition === 'in' ) {
					$values         = array_map( 'trim', explode( ',', $value ) );
					$args['status'] = array_map( [ $this, 'normalize_comment_status_value' ], $values );
				} elseif ( in_array( $condition, [ 'not_equals', 'not_in' ], true ) ) {
					$args['_other_filters'][] = $filter;
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
					$args['post_id'] = absint( $value ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- post__not_in required for correct filtering.
				} elseif ( $condition === 'not_equals' ) {
					$args['post__not_in'] = [ absint( $value ) ]; // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- post__not_in required for correct export filtering.
				} elseif ( $condition === 'in' ) {
					$values           = array_map( 'trim', explode( ',', $value ) );
					$args['post__in'] = array_map( 'absint', $values );
				} elseif ( $condition === 'not_in' ) { // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- post__not_in required for correct filtering.
					$values               = array_map( 'trim', explode( ',', $value ) );
					$args['post__not_in'] = array_map( 'absint', $values ); // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- post__not_in required for correct export filtering.
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
						'year'   => gmdate( 'Y', strtotime( $value ) ),
						'month'  => gmdate( 'm', strtotime( $value ) ),
						'day'    => gmdate( 'd', strtotime( $value ) ),
					];
				} elseif ( $condition === 'not_equals' ) {
					$args['date_query'][] = [
						'column'  => 'comment_date',
						'compare' => '!=',
						'year'    => gmdate( 'Y', strtotime( $value ) ),
						'month'   => gmdate( 'm', strtotime( $value ) ),
						'day'     => gmdate( 'd', strtotime( $value ) ),
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
						__( 'Invalid comment status: %s', 'import-export-by-rockstarlab' ),
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
	 * Combine already prepared SQL filter clauses with an allowlisted operator.
	 *
	 * @param array  $parts    Prepared SQL fragments.
	 * @param string $operator Boolean operator.
	 * @return string Combined SQL fragment.
	 */
	private function combine_prepared_filter_parts( $parts, $operator ) {
		$operator = strtoupper( (string) $operator );
		if ( ! in_array( $operator, [ 'AND', 'OR' ], true ) ) {
			return '';
		}

		$combined = '';
		foreach ( $parts as $part ) {
			if ( '' === $part ) {
				continue;
			}

			if ( '' === $combined ) {
				$combined = $part;
				continue;
			}

			if ( 'OR' === $operator ) {
				$combined .= ' OR ' . $part;
			} else {
				$combined .= ' AND ' . $part;
			}
		}

		return '' === $combined ? '' : '(' . $combined . ')';
	}

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

		$prepared_filter_parts = [];

		foreach ( $this->custom_filters as $filter ) {
			$field     = $filter['field'];
			$condition = $filter['condition'];
			$value     = $filter['value'];

			$allowed_columns = [
				'comment_ID',
				'comment_post_ID',
				'comment_author',
				'comment_author_email',
				'comment_author_url',
				'comment_author_IP',
				'comment_date',
				'comment_content',
				'comment_karma',
				'comment_parent',
				'user_id',
			];

			if ( ! in_array( $field, $allowed_columns, true ) ) {
				continue;
			}

			$column = $field;

			// Numeric fields
			if ( in_array( $field, [ 'comment_ID', 'comment_post_ID', 'user_id', 'comment_parent', 'comment_karma' ], true ) ) {
				if ( $condition === 'equals' ) {
					$prepared_filter_parts[] = $wpdb->prepare( '%i = %d', $column, absint( $value ) );
				} elseif ( $condition === 'not_equals' ) {
					$prepared_filter_parts[] = $wpdb->prepare( '%i != %d', $column, absint( $value ) );
				} elseif ( $condition === 'greater' ) {
					$prepared_filter_parts[] = $wpdb->prepare( '%i > %d', $column, absint( $value ) );
				} elseif ( $condition === 'equals_or_greater' ) {
					$prepared_filter_parts[] = $wpdb->prepare( '%i >= %d', $column, absint( $value ) );
				} elseif ( $condition === 'less' ) {
					$prepared_filter_parts[] = $wpdb->prepare( '%i < %d', $column, absint( $value ) );
				} elseif ( $condition === 'equals_or_less' ) {
					$prepared_filter_parts[] = $wpdb->prepare( '%i <= %d', $column, absint( $value ) );
				} elseif ( $condition === 'in' ) {
					$values = array_map( 'trim', explode( ',', $value ) );
					$values = array_map( 'absint', $values );
					if ( ! empty( $values ) ) {
						$parts = [];
						foreach ( $values as $id ) {
							$parts[] = $wpdb->prepare( '%i = %d', $column, $id );
						}
							$prepared_filter_parts[] = $this->combine_prepared_filter_parts( $parts, 'OR' );
					}
				} elseif ( $condition === 'not_in' ) {
					$values = array_map( 'trim', explode( ',', $value ) );
					$values = array_map( 'absint', $values );
					if ( ! empty( $values ) ) {
						$parts = [];
						foreach ( $values as $id ) {
							$parts[] = $wpdb->prepare( '%i != %d', $column, $id );
						}
							$prepared_filter_parts[] = $this->combine_prepared_filter_parts( $parts, 'AND' );
					}
				} elseif ( $condition === 'between' ) {
					$values = array_map( 'trim', explode( ',', $value ) );
					if ( count( $values ) === 2 ) {
						$prepared_filter_parts[] = $wpdb->prepare( '%i BETWEEN %d AND %d', $column, absint( $values[0] ), absint( $values[1] ) );
					}
				} elseif ( $condition === 'is_empty' ) {
					$prepared_filter_parts[] = $wpdb->prepare( '(%i = 0 OR %i IS NULL)', $column, $column );
				} elseif ( $condition === 'is_not_empty' ) {
					$prepared_filter_parts[] = $wpdb->prepare( '(%i != 0 AND %i IS NOT NULL)', $column, $column );
				}
			}

			// Text fields
			if ( in_array( $field, [ 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_author_IP', 'comment_content' ], true ) ) {
				if ( $condition === 'equals' ) {
					$prepared_filter_parts[] = $wpdb->prepare( '%i = %s', $column, $value );
				} elseif ( $condition === 'contains' ) {
					$prepared_filter_parts[] = $wpdb->prepare( '%i LIKE %s', $column, '%' . $wpdb->esc_like( $value ) . '%' );
				} elseif ( $condition === 'not_contains' ) {
					$prepared_filter_parts[] = $wpdb->prepare( '%i NOT LIKE %s', $column, '%' . $wpdb->esc_like( $value ) . '%' );
				} elseif ( $condition === 'starts_with' ) {
					$prepared_filter_parts[] = $wpdb->prepare( '%i LIKE %s', $column, $wpdb->esc_like( $value ) . '%' );
				} elseif ( $condition === 'ends_with' ) {
					$prepared_filter_parts[] = $wpdb->prepare( '%i LIKE %s', $column, '%' . $wpdb->esc_like( $value ) );
				} elseif ( $condition === 'is_empty' ) {
					$prepared_filter_parts[] = $wpdb->prepare( '(%i = %s OR %i IS NULL)', $column, '', $column );
				} elseif ( $condition === 'is_not_empty' ) {
					$prepared_filter_parts[] = $wpdb->prepare( '(%i != %s AND %i IS NOT NULL)', $column, '', $column );
				}
			}
		}

		if ( ! empty( $prepared_filter_parts ) ) {
			$combined_where = '';
			foreach ( $prepared_filter_parts as $prepared_clause ) {
				$combined_where .= '' === $combined_where ? $prepared_clause : ' AND ' . $prepared_clause;
			}

			$clauses['where'] .= ' AND (' . $combined_where . ')';
			$this->log_info( 'Applied custom WHERE clauses', [ 'prepared_filter_parts' => $prepared_filter_parts ] );
		}

		return $clauses;
	}

	/**
	 * Filter comments using the values presented by the exporter.
	 *
	 * @param array $comments Comment objects.
	 * @param array $filters  Filter definitions.
	 * @return array
	 */
	protected function filter_comments( $comments, $filters ) {
		if ( empty( $filters ) ) {
			return $comments;
		}

		return array_values(
			array_filter(
				$comments,
				function ( $comment ) use ( $filters ) {
					foreach ( $filters as $filter ) {
						$field_value = $this->get_comment_field_value( $comment, $filter['field'] );
						if ( ! $this->check_condition( $field_value, $filter['condition'], $filter['value'] ?? '' ) ) {
							return false;
						}
					}

					return true;
				}
			)
		);
	}

	/**
	 * Get comment field value
	 *
	 * @param \WP_Comment $comment    Comment object
	 * @param string      $field_name Field name
	 * @return mixed Field value
	 */
	protected function get_comment_field_value( $comment, $field_name ) {
		// Map field names to comment properties
		$field_map = array(
			'comment_ID'           => 'comment_ID',
			'comment_post_ID'      => 'comment_post_ID',
			'comment_author'       => 'comment_author',
			'comment_author_email' => 'comment_author_email',
			'comment_author_url'   => 'comment_author_url',
			'comment_author_IP'    => 'comment_author_IP',
			'comment_date'         => 'comment_date',
			'comment_date_gmt'     => 'comment_date_gmt',
			'comment_content'      => 'comment_content',
			'comment_karma'        => 'comment_karma',
			'comment_approved'     => 'comment_approved',
			'comment_agent'        => 'comment_agent',
			'comment_type'         => 'comment_type',
			'comment_parent'       => 'comment_parent',
			'user_id'              => 'user_id',
		);

		// Check if it is a standard field.
		if ( isset( $field_map[ $field_name ] ) ) {
			$property = $field_map[ $field_name ];
			return $comment->$property ?? '';
		}

		// Computed fields from the related post.
		if ( 'post_title' === $field_name || 'post_author' === $field_name ) {
			$post = get_post( $comment->comment_post_ID );
			if ( ! $post ) {
				return '';
			}
			return 'post_title' === $field_name ? $post->post_title : $post->post_author;
		}

		// Check if it is a meta field.
		$value = get_comment_meta( $comment->comment_ID, $field_name, true );
		return is_array( $value ) || is_object( $value ) ? wp_json_encode( $value ) : $value;
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
	 * Normalize UI comment status values to WP_Comment_Query status values.
	 *
	 * WordPress stores approved comments as "1" in the database, but
	 * WP_Comment_Query expects "approve". Likewise, pending comments are
	 * stored as "0" and queried via "hold".
	 *
	 * @param string $value Raw status value from the UI filter.
	 * @return string
	 */
	protected function normalize_comment_status_value( $value ) {
		$value = sanitize_text_field( (string) $value );

		$status_map = [
			'1'        => 'approve',
			'0'        => 'hold',
			'approve'  => 'approve',
			'approved' => 'approve',
			'hold'     => 'hold',
			'pending'  => 'hold',
			'spam'     => 'spam',
			'trash'    => 'trash',
			'all'      => 'all',
		];

		return $status_map[ $value ] ?? $value;
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

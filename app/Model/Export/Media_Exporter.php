<?php
/**
 * Media Exporter
 *
 * Handles exporting WordPress media attachments
 *
 * @package WP_AIE\Model\Export
 */

namespace WP_AIE\Model\Export;

/**
 * Media Exporter Class
 *
 * Exports media attachments with support for:
 * - Filtering by mime type, date, parent post
 * - Attachment metadata (alt, caption, description)
 * - File URLs and local paths
 * - Image metadata (dimensions, sizes)
 * - Attached vs unattached media
 *
 * @package WP_AIE\Model\Export
 */
class Media_Exporter extends Abstract_Exporter {

	/**
	 * Get exporter name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'media';
	}

	/**
	 * Get exporter description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Export WordPress media attachments', 'wp-advanced-import-export' );
	}

	/**
	 * Get supported export filters
	 *
	 * @return array
	 */
	public function get_supported_filters() {
		return [
			'mime_type'    => __( 'MIME type (image/jpeg, image/png, etc.) or type group (image, video, audio, document)', 'wp-advanced-import-export' ),
			'post_parent'  => __( 'Parent post ID (0 for unattached)', 'wp-advanced-import-export' ),
			'date_query'   => __( 'Date query parameters', 'wp-advanced-import-export' ),
			'author'       => __( 'Author ID or array of IDs', 'wp-advanced-import-export' ),
			's'            => __( 'Search query', 'wp-advanced-import-export' ),
			'orderby'      => __( 'Order by field', 'wp-advanced-import-export' ),
			'order'        => __( 'Order direction (ASC or DESC)', 'wp-advanced-import-export' ),
			'include_file' => __( 'Include file content (base64 encoded)', 'wp-advanced-import-export' ),
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
			'post_author',
			'post_date',
			'post_date_gmt',
			'post_modified',
			'post_modified_gmt',
			'post_parent',
			'guid',
			'file',
			'url',
			'path',
			'filename',
			'mime_type',
			'file_size',
			'alt_text',
			'caption',
			'description',
			'metadata',
			'image_meta',
			'sizes',
			'author_name',
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
			'file',
			'url',
			'filename',
			'mime_type',
			'alt_text',
			'caption',
			'description',
			'post_parent',
			'post_date',
		];
	}

	/**
	 * Get total count of items
	 *
	 * @param array $options Optional. Export filters
	 * @return int
	 */
	public function get_count( $options = [] ) {
		$query_args                   = $this->build_query_args( $options );
		$query_args['fields']         = 'ids';
		$query_args['posts_per_page'] = -1;

		// Add custom ID filters via posts_where hook
		if ( ! empty( $query_args['_custom_id_filters'] ) ) {
			$custom_id_filters = $query_args['_custom_id_filters'];
			unset( $query_args['_custom_id_filters'] );

			add_filter(
				'posts_where',
				function ( $where ) use ( $custom_id_filters ) {
					global $wpdb;

					foreach ( $custom_id_filters as $filter ) {
						$condition = $filter['condition'];
						$value     = $filter['value'];

						if ( $condition === 'greater_than' ) {
							$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", absint( $value ) );
						} elseif ( $condition === 'less_than' ) {
							$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID < %d", absint( $value ) );
						} elseif ( $condition === 'greater_than_or_equal' ) {
							$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID >= %d", absint( $value ) );
						} elseif ( $condition === 'less_than_or_equal' ) {
							$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID <= %d", absint( $value ) );
						} elseif ( $condition === 'between' ) {
							$values = array_map( 'absint', explode( ',', $value ) );
							if ( count( $values ) === 2 ) {
								$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID BETWEEN %d AND %d", $values[0], $values[1] );
							}
						}
					}

					return $where;
				},
				10,
				1
			);
		}

		$query = new \WP_Query( $query_args );

		// Remove the filter after query
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

		$this->log_info( 'Querying media', $query_args );

		// Add custom ID filters via posts_where hook
		if ( ! empty( $query_args['_custom_id_filters'] ) ) {
			$custom_id_filters = $query_args['_custom_id_filters'];
			unset( $query_args['_custom_id_filters'] );

			add_filter(
				'posts_where',
				function ( $where ) use ( $custom_id_filters ) {
					global $wpdb;

					foreach ( $custom_id_filters as $filter ) {
						$condition = $filter['condition'];
						$value     = $filter['value'];

						if ( $condition === 'greater_than' ) {
							$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID > %d", absint( $value ) );
						} elseif ( $condition === 'less_than' ) {
							$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID < %d", absint( $value ) );
						} elseif ( $condition === 'greater_than_or_equal' ) {
							$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID >= %d", absint( $value ) );
						} elseif ( $condition === 'less_than_or_equal' ) {
							$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID <= %d", absint( $value ) );
						} elseif ( $condition === 'between' ) {
							$values = array_map( 'absint', explode( ',', $value ) );
							if ( count( $values ) === 2 ) {
								$where .= $wpdb->prepare( " AND {$wpdb->posts}.ID BETWEEN %d AND %d", $values[0], $values[1] );
							}
						}
					}

					return $where;
				},
				10,
				1
			);
		}

		$query = new \WP_Query( $query_args );

		// Remove the filter after query
		if ( ! empty( $custom_id_filters ) ) {
			remove_all_filters( 'posts_where', 10 );
		}

		if ( ! $query->have_posts() ) {
			return [];
		}

		$data   = [];
		$fields = $this->get_option( 'fields', $this->get_default_fields() );

		while ( $query->have_posts() ) {
			$query->the_post();
			$attachment = get_post();

			$item   = $this->prepare_media_data( $attachment, $fields, $options );
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
		$args = [
			'post_type'      => 'attachment',
			'post_status'    => 'any',
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

		// Parent post filter
		if ( isset( $options['post_parent'] ) ) {
			$args['post_parent'] = $options['post_parent'];
		}

		// MIME type filter
		if ( ! empty( $options['mime_type'] ) ) {
			$args['post_mime_type'] = $this->parse_mime_type( $options['mime_type'] );
		}

		// Process dynamic filters
		if ( ! empty( $options['filters'] ) && is_array( $options['filters'] ) ) {
			$this->apply_dynamic_filters( $args, $options['filters'] );
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
		$meta_query = $args['meta_query'] ?? [];

		foreach ( $filters as $filter ) {
			if ( empty( $filter['field'] ) || empty( $filter['condition'] ) ) {
				continue;
			}

			$field     = $filter['field'];
			$condition = $filter['condition'];
			$value     = $filter['value'] ?? '';

			// Handle specific post fields
			if ( $field === 'ID' ) {
				// ID filtering
				if ( $condition === 'equals' ) {
					$args['post__in'] = [ absint( $value ) ];
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
				} elseif ( in_array( $condition, [ 'greater_than', 'less_than', 'greater_than_or_equal', 'less_than_or_equal', 'between' ], true ) ) {
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

			if ( $field === 'post_parent' ) {
				$args['post_parent'] = absint( $value );
				continue;
			}

			// For other post fields, skip for now
			$post_fields = [ 'post_title', 'post_content', 'post_excerpt', 'post_date', 'post_status' ];
			if ( in_array( $field, $post_fields, true ) ) {
				// TODO: Add support for these fields using custom SQL
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
					$meta_query_item['value'] = $value;
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
	 * Parse MIME type filter
	 *
	 * Converts type groups (image, video, audio, document) to actual MIME types.
	 *
	 * @param string|array $mime_type MIME type or type group
	 * @return string|array
	 */
	protected function parse_mime_type( $mime_type ) {
		$type_groups = [
			'image'    => 'image',
			'video'    => 'video',
			'audio'    => 'audio',
			'document' => [ 'application/pdf', 'application/msword', 'application/vnd.ms-excel', 'application/vnd.openxmlformats' ],
		];

		if ( is_array( $mime_type ) ) {
			return $mime_type;
		}

		if ( isset( $type_groups[ $mime_type ] ) ) {
			return $type_groups[ $mime_type ];
		}

		return $mime_type;
	}

	/**
	 * Prepare media data for export
	 *
	 * @param \WP_Post $attachment Attachment post object
	 * @param array    $fields     Fields to include
	 * @param array    $options    Export options
	 * @return array
	 */
	protected function prepare_media_data( $attachment, $fields, $options ) {
		$data = [];

		// Basic fields
		$basic_fields = [
			'ID',
			'post_title',
			'post_content',
			'post_excerpt',
			'post_author',
			'post_date',
			'post_date_gmt',
			'post_modified',
			'post_modified_gmt',
			'post_parent',
			'guid',
		];

		foreach ( $basic_fields as $field ) {
			if ( in_array( $field, $fields, true ) ) {
				$data[ $field ] = $attachment->$field;
			}
		}

		// File information
		if ( in_array( 'file', $fields, true ) || in_array( 'path', $fields, true ) ) {
			$file_path = get_attached_file( $attachment->ID );
			if ( in_array( 'file', $fields, true ) ) {
				$data['file'] = $file_path;
			}
			if ( in_array( 'path', $fields, true ) ) {
				$data['path'] = $file_path;
			}
		}

		if ( in_array( 'url', $fields, true ) ) {
			$data['url'] = wp_get_attachment_url( $attachment->ID );
		}

		if ( in_array( 'filename', $fields, true ) ) {
			$data['filename'] = basename( get_attached_file( $attachment->ID ) );
		}

		if ( in_array( 'mime_type', $fields, true ) ) {
			$data['mime_type'] = get_post_mime_type( $attachment->ID );
		}

		if ( in_array( 'file_size', $fields, true ) ) {
			$file_path         = get_attached_file( $attachment->ID );
			$data['file_size'] = $file_path && file_exists( $file_path ) ? filesize( $file_path ) : 0;
		}

		// Attachment metadata
		if ( in_array( 'alt_text', $fields, true ) ) {
			$data['alt_text'] = get_post_meta( $attachment->ID, '_wp_attachment_image_alt', true );
		}

		if ( in_array( 'caption', $fields, true ) ) {
			$data['caption'] = $attachment->post_excerpt;
		}

		if ( in_array( 'description', $fields, true ) ) {
			$data['description'] = $attachment->post_content;
		}

		// WordPress attachment metadata
		if ( in_array( 'metadata', $fields, true ) ) {
			$data['metadata'] = wp_get_attachment_metadata( $attachment->ID );
		}

		// Image-specific metadata
		if ( in_array( 'image_meta', $fields, true ) && wp_attachment_is_image( $attachment->ID ) ) {
			$metadata           = wp_get_attachment_metadata( $attachment->ID );
			$data['image_meta'] = $metadata['image_meta'] ?? [];
		}

		// Image sizes
		if ( in_array( 'sizes', $fields, true ) && wp_attachment_is_image( $attachment->ID ) ) {
			$data['sizes'] = $this->get_image_sizes( $attachment->ID );
		}

		// Author name
		if ( in_array( 'author_name', $fields, true ) ) {
			$author              = get_userdata( $attachment->post_author );
			$data['author_name'] = $author ? $author->display_name : '';
		}

		// Include file content if requested
		if ( ! empty( $options['include_file'] ) ) {
			$file_path = get_attached_file( $attachment->ID );
			if ( $file_path && file_exists( $file_path ) ) {
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$data['file_content'] = base64_encode( file_get_contents( $file_path ) );
			}
		}

		return $data;
	}

	/**
	 * Get image sizes information
	 *
	 * @param int $attachment_id Attachment ID
	 * @return array
	 */
	protected function get_image_sizes( $attachment_id ) {
		$sizes       = [];
		$image_sizes = get_intermediate_image_sizes();

		foreach ( $image_sizes as $size ) {
			$image = wp_get_attachment_image_src( $attachment_id, $size );
			if ( $image ) {
				$sizes[ $size ] = [
					'url'    => $image[0],
					'width'  => $image[1],
					'height' => $image[2],
				];
			}
		}

		// Full size
		$full = wp_get_attachment_image_src( $attachment_id, 'full' );
		if ( $full ) {
			$sizes['full'] = [
				'url'    => $full[0],
				'width'  => $full[1],
				'height' => $full[2],
			];
		}

		return $sizes;
	}
}

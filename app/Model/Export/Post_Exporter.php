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
			'post_type'   => __( 'Post type (post, page, or custom post type)', 'wp-advanced-import-export' ),
			'post_status' => __( 'Post status (publish, draft, pending, etc.)', 'wp-advanced-import-export' ),
			'author'      => __( 'Author ID or array of IDs', 'wp-advanced-import-export' ),
			'date_query'  => __( 'Date query parameters', 'wp-advanced-import-export' ),
			'tax_query'   => __( 'Taxonomy query parameters', 'wp-advanced-import-export' ),
			'meta_query'  => __( 'Meta query parameters', 'wp-advanced-import-export' ),
			's'           => __( 'Search query', 'wp-advanced-import-export' ),
			'orderby'     => __( 'Order by field', 'wp-advanced-import-export' ),
			'order'       => __( 'Order direction (ASC or DESC)', 'wp-advanced-import-export' ),
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
		$query_args                   = $this->build_query_args( $options );
		$query_args['fields']         = 'ids';
		$query_args['posts_per_page'] = -1;

		$query = new \WP_Query( $query_args );
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

		$query = new \WP_Query( $query_args );

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
		$args = [
			'post_type'      => $options['post_type'] ?? 'post',
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

			if ( $field === 'post_status' ) {
				$args['post_status'] = sanitize_text_field( $value );
				continue;
			}

			// For other post fields, skip for now
			$post_fields = [ 'post_title', 'post_content', 'post_excerpt', 'post_date' ];
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
			'equals'           => '=',
			'not_equals'       => '!=',
			'greater_than'     => '>',
			'less_than'        => '<',
			'greater_or_equal' => '>=',
			'less_or_equal'    => '<=',
			'contains'         => 'LIKE',
			'not_contains'     => 'NOT LIKE',
			'is_empty'         => 'NOT EXISTS',
			'is_not_empty'     => 'EXISTS',
		];

		return $map[ $condition ] ?? null;
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
}

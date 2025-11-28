<?php
/**
 * Post Importer
 *
 * Handles importing WordPress posts, pages, and custom post types
 *
 * @package WP_AIE\Model\Import
 */

namespace WP_AIE\Model\Import;

/**
 * Post Importer Class
 *
 * Imports WordPress posts with support for:
 * - Post meta
 * - Taxonomies (categories, tags, custom taxonomies)
 * - Featured images
 * - Post status and visibility
 * - Duplicate handling
 *
 * @package WP_AIE\Model\Import
 */
class Post_Importer extends Abstract_Importer {

	/**
	 * Get importer name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'posts';
	}

	/**
	 * Get importer description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Import WordPress posts, pages, and custom post types', 'wp-advanced-import-export' );
	}

	/**
	 * Get required fields
	 *
	 * @return array
	 */
	public function get_required_fields() {
		return [ 'post_title' ];
	}

	/**
	 * Get optional fields
	 *
	 * @return array
	 */
	public function get_optional_fields() {
		return [
			'post_content',
			'post_excerpt',
			'post_status',
			'post_type',
			'post_author',
			'post_date',
			'post_name',
			'post_parent',
			'menu_order',
			'comment_status',
			'ping_status',
			'post_password',
			'post_meta',
			'taxonomies',
			'featured_image',
		];
	}

	/**
	 * Get supported options
	 *
	 * @return array
	 */
	public function get_supported_options() {
		return [
			'duplicate_mode'  => 'How to handle duplicates: skip, update, create',
			'post_status'     => 'Default post status: publish, draft, pending',
			'post_type'       => 'Post type to import as: post, page, or custom type',
			'post_author'     => 'Default author ID if not specified in data',
			'comment_status'  => 'Default comment status: open, closed',
			'ping_status'     => 'Default ping status: open, closed',
			'duplicate_check' => 'Field to check for duplicates: post_title, post_name, ID',
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
				'post_status'     => 'draft',
				'post_type'       => 'post',
				'post_author'     => get_current_user_id(),
				'comment_status'  => 'open',
				'ping_status'     => 'closed',
				'duplicate_check' => 'post_title',
			]
		);
	}

	/**
	 * Import single post
	 *
	 * @param array $item  Post data
	 * @param int   $index Item index
	 * @return int|string|WP_Error Post ID, 'skipped', 'updated', or WP_Error
	 */
	protected function import_item( $item, $index ) {
		// Sanitize data
		$item = $this->sanitize_item( $item );

		// Check for duplicates
		$existing_post = $this->find_existing_post( $item );

		if ( $existing_post ) {
			$duplicate_mode = $this->get_option( 'duplicate_mode', 'skip' );

			if ( 'skip' === $duplicate_mode ) {
				return 'skipped';
			}

			if ( 'update' === $duplicate_mode ) {
				return $this->update_post( $existing_post->ID, $item );
			}

			// 'create' mode - fall through to create new post
		}

		// Create new post
		return $this->create_post( $item );
	}

	/**
	 * Find existing post
	 *
	 * @param array $item Post data
	 * @return WP_Post|null Existing post or null
	 */
	private function find_existing_post( $item ) {
		$check_field = $this->get_option( 'duplicate_check', 'post_title' );

		// Check by ID
		if ( 'ID' === $check_field && ! empty( $item['ID'] ) ) {
			$post = get_post( $item['ID'] );
			return $post ? $post : null;
		}

		// Check by post_name (slug)
		if ( 'post_name' === $check_field && ! empty( $item['post_name'] ) ) {
			$args = [
				'name'           => $item['post_name'],
				'post_type'      => $this->get_option( 'post_type', 'post' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
			];

			$posts = get_posts( $args );
			return ! empty( $posts ) ? get_post( $posts[0] ) : null;
		}

		// Check by post_title
		if ( 'post_title' === $check_field && ! empty( $item['post_title'] ) ) {
			$args = [
				'title'          => $item['post_title'],
				'post_type'      => $this->get_option( 'post_type', 'post' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
			];

			$posts = get_posts( $args );
			return ! empty( $posts ) ? get_post( $posts[0] ) : null;
		}

		return null;
	}

	/**
	 * Create new post
	 *
	 * @param array $item Post data
	 * @return int|WP_Error Post ID or WP_Error
	 */
	private function create_post( $item ) {
		$post_data = $this->prepare_post_data( $item );

		$post_id = wp_insert_post( $post_data, true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Import post meta
		if ( ! empty( $item['post_meta'] ) ) {
			$this->import_post_meta( $post_id, $item['post_meta'] );
		}

		// Import taxonomies
		if ( ! empty( $item['taxonomies'] ) ) {
			$this->import_taxonomies( $post_id, $item['taxonomies'] );
		}

		// Import featured image
		if ( ! empty( $item['featured_image'] ) ) {
			$this->import_featured_image( $post_id, $item['featured_image'] );
		}

		return $post_id;
	}

	/**
	 * Update existing post
	 *
	 * @param int   $post_id Post ID
	 * @param array $item    Post data
	 * @return string|WP_Error 'updated' or WP_Error
	 */
	private function update_post( $post_id, $item ) {
		$post_data       = $this->prepare_post_data( $item );
		$post_data['ID'] = $post_id;

		$result = wp_update_post( $post_data, true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Update post meta
		if ( ! empty( $item['post_meta'] ) ) {
			$this->import_post_meta( $post_id, $item['post_meta'] );
		}

		// Update taxonomies
		if ( ! empty( $item['taxonomies'] ) ) {
			$this->import_taxonomies( $post_id, $item['taxonomies'] );
		}

		// Update featured image
		if ( ! empty( $item['featured_image'] ) ) {
			$this->import_featured_image( $post_id, $item['featured_image'] );
		}

		return 'updated';
	}

	/**
	 * Prepare post data for wp_insert_post/wp_update_post
	 *
	 * @param array $item Raw post data
	 * @return array Prepared post data
	 */
	private function prepare_post_data( $item ) {
		$defaults = [
			'post_status'    => $this->get_option( 'post_status', 'draft' ),
			'post_type'      => $this->get_option( 'post_type', 'post' ),
			'post_author'    => $this->get_option( 'post_author', get_current_user_id() ),
			'comment_status' => $this->get_option( 'comment_status', 'open' ),
			'ping_status'    => $this->get_option( 'ping_status', 'closed' ),
		];

		$allowed_fields = [
			'post_title',
			'post_content',
			'post_excerpt',
			'post_status',
			'post_type',
			'post_author',
			'post_date',
			'post_date_gmt',
			'post_name',
			'post_parent',
			'menu_order',
			'comment_status',
			'ping_status',
			'post_password',
		];

		$post_data = [];

		foreach ( $allowed_fields as $field ) {
			if ( isset( $item[ $field ] ) ) {
				$post_data[ $field ] = $item[ $field ];
			}
		}

		return array_merge( $defaults, $post_data );
	}

	/**
	 * Import post meta
	 *
	 * @param int   $post_id Post ID
	 * @param array $meta    Meta data (key => value)
	 */
	private function import_post_meta( $post_id, $meta ) {
		if ( ! is_array( $meta ) ) {
			return;
		}

		foreach ( $meta as $key => $value ) {
			update_post_meta( $post_id, $key, $value );
		}
	}

	/**
	 * Import taxonomies
	 *
	 * @param int   $post_id    Post ID
	 * @param array $taxonomies Taxonomies data
	 */
	private function import_taxonomies( $post_id, $taxonomies ) {
		if ( ! is_array( $taxonomies ) ) {
			return;
		}

		foreach ( $taxonomies as $taxonomy => $terms ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			// Terms can be array of IDs, names, or slugs
			if ( is_string( $terms ) ) {
				$terms = array_map( 'trim', explode( ',', $terms ) );
			}

			wp_set_object_terms( $post_id, $terms, $taxonomy );
		}
	}

	/**
	 * Import featured image
	 *
	 * @param int        $post_id Post ID
	 * @param string|int $image   Image URL, path, or attachment ID
	 */
	private function import_featured_image( $post_id, $image ) {
		// If it's already an attachment ID
		if ( is_numeric( $image ) && get_post( $image ) ) {
			set_post_thumbnail( $post_id, $image );
			return;
		}

		// If it's a URL or path, we'd need Media_Importer
		// For now, just log a warning
		$this->log_warning( sprintf( 'Featured image import not yet implemented for URLs: %s', $image ) );
	}
}

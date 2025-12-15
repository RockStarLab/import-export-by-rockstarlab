<?php
/**
 * Content Sync API Controller
 *
 * Handles REST API endpoints for content synchronization
 *
 * @package WP_AIE\Controller
 */

namespace WP_AIE\Controller;

/**
 * Content Sync API Controller Class
 *
 * Provides REST API endpoints for remote sites to connect and sync.
 *
 * @package WP_AIE\Controller
 */
class Content_Sync_API_Controller {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		try {
			register_rest_route(
				'aie/v1',
				'/validate',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'validate_connection' ),
					'permission_callback' => array( $this, 'validate_api_key' ),
				)
			);

			register_rest_route(
				'aie/v1',
				'/info',
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_site_info' ),
					'permission_callback' => array( $this, 'validate_api_key' ),
				)
			);

			register_rest_route(
				'aie/v1',
				'/receive-content',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'receive_content' ),
					'permission_callback' => array( $this, 'validate_api_key' ),
				)
			);

			register_rest_route(
				'aie/v1',
				'/send-content',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'send_content' ),
					'permission_callback' => array( $this, 'validate_api_key' ),
				)
			);
		} catch ( \Exception $e ) {
			// Log error but don't break the site
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'WP_AIE: Failed to register REST API routes: ' . $e->getMessage() );
			}
		}
	}

	/**
	 * Validate API key from request
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_api_key( $request ) {
		$auth_header = $request->get_header( 'Authorization' );

		if ( empty( $auth_header ) ) {
			return false;
		}

		// Extract Bearer token
		if ( preg_match( '/Bearer\s+(.+)/i', $auth_header, $matches ) ) {
			$provided_key = trim( $matches[1] );
		} else {
			return false;
		}

		// Get this site's API key
		$site_key = get_option( 'aie_site_api_key' );

		if ( empty( $site_key ) ) {
			return false;
		}

		// Compare keys
		return hash_equals( $site_key, $provided_key );
	}

	/**
	 * Validate connection endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function validate_connection( $request ) {
		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Connection validated successfully', 'wp-advanced-import-export' ),
				'data'    => array(
					'site_name'    => get_bloginfo( 'name' ),
					'site_url'     => get_site_url(),
					'wp_version'   => get_bloginfo( 'version' ),
					'plugin_version' => defined( 'WP_AIE_VERSION' ) ? WP_AIE_VERSION : '1.0.0',
				),
			),
			200
		);
	}

	/**
	 * Get site information endpoint
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function get_site_info( $request ) {
		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => array(
					'site_name'      => get_bloginfo( 'name' ),
					'site_url'       => get_site_url(),
					'description'    => get_bloginfo( 'description' ),
					'wp_version'     => get_bloginfo( 'version' ),
					'plugin_version' => defined( 'WP_AIE_VERSION' ) ? WP_AIE_VERSION : '1.0.0',
					'timezone'       => get_option( 'timezone_string' ),
					'date_format'    => get_option( 'date_format' ),
					'time_format'    => get_option( 'time_format' ),
				),
			),
			200
		);
	}

	/**
	 * Receive content from remote site (Push)
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function receive_content( $request ) {
		$posts_data = $request->get_param( 'posts' );

		if ( empty( $posts_data ) || ! is_array( $posts_data ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'No posts data provided', 'wp-advanced-import-export' ),
				),
				400
			);
		}

		$imported_count = 0;
		$errors         = array();

		foreach ( $posts_data as $post_data ) {
			// Prepare post data
			$post_args = array(
				'post_title'   => $post_data['post_title'],
				'post_content' => $post_data['post_content'],
				'post_excerpt' => $post_data['post_excerpt'],
				'post_status'  => $post_data['post_status'],
				'post_type'    => $post_data['post_type'],
				'post_name'    => $post_data['post_name'],
				'post_date'    => $post_data['post_date'],
				'post_author'  => 1, // Admin user
			);

			// Check if post exists by name
			$existing_post = get_page_by_path( $post_data['post_name'], OBJECT, $post_data['post_type'] );
			
			if ( $existing_post ) {
				// Update existing post
				$post_args['ID'] = $existing_post->ID;
				$post_id         = wp_update_post( $post_args );
			} else {
				// Create new post
				$post_id = wp_insert_post( $post_args );
			}

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				$errors[] = sprintf(
					/* translators: %s: post title */
					__( 'Failed to import post: %s', 'wp-advanced-import-export' ),
					$post_data['post_title']
				);
				continue;
			}

			// Import meta
			if ( ! empty( $post_data['meta'] ) ) {
				foreach ( $post_data['meta'] as $key => $value ) {
					// Skip internal WordPress meta
					if ( strpos( $key, '_' ) === 0 ) {
						continue;
					}
					update_post_meta( $post_id, $key, $value );
				}
			}

			// Import terms
			if ( ! empty( $post_data['terms'] ) ) {
				foreach ( $post_data['terms'] as $taxonomy => $term_names ) {
					// Check if taxonomy exists
					if ( taxonomy_exists( $taxonomy ) ) {
						wp_set_object_terms( $post_id, $term_names, $taxonomy );
					}
				}
			}

			$imported_count++;
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => sprintf(
					/* translators: %d: number of posts */
					__( 'Successfully imported %d post(s)', 'wp-advanced-import-export' ),
					$imported_count
				),
				'data'    => array(
					'imported' => $imported_count,
					'errors'   => $errors,
				),
			),
			200
		);
	}

	/**
	 * Send content to requesting site (Pull)
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function send_content( $request ) {
		$post_ids = $request->get_param( 'post_ids' );

		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'No post IDs provided', 'wp-advanced-import-export' ),
				),
				400
			);
		}

		$posts_data = array();

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			// Get post meta
			$meta          = get_post_meta( $post_id );
			$prepared_meta = array();
			foreach ( $meta as $key => $values ) {
				$prepared_meta[ $key ] = maybe_unserialize( $values[0] );
			}

			// Get post terms
			$taxonomies = get_object_taxonomies( $post->post_type );
			$terms_data = array();
			foreach ( $taxonomies as $taxonomy ) {
				$terms = wp_get_post_terms( $post_id, $taxonomy );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$terms_data[ $taxonomy ] = wp_list_pluck( $terms, 'name' );
				}
			}

			$posts_data[] = array(
				'ID'            => $post->ID,
				'post_title'    => $post->post_title,
				'post_content'  => $post->post_content,
				'post_excerpt'  => $post->post_excerpt,
				'post_status'   => $post->post_status,
				'post_type'     => $post->post_type,
				'post_name'     => $post->post_name,
				'post_date'     => $post->post_date,
				'post_modified' => $post->post_modified,
				'post_author'   => $post->post_author,
				'meta'          => $prepared_meta,
				'terms'         => $terms_data,
			);
		}

		if ( empty( $posts_data ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'No valid posts found', 'wp-advanced-import-export' ),
				),
				404
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => sprintf(
					/* translators: %d: number of posts */
					__( 'Found %d post(s)', 'wp-advanced-import-export' ),
					count( $posts_data )
				),
				'data'    => array(
					'posts' => $posts_data,
				),
			),
			200
		);
	}
}

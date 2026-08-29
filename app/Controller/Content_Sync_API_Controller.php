<?php
/**
 * Content Sync API Controller
 *
 * Handles REST API endpoints for content synchronization
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Helper\ACF_Fields;
use RockStarLab\ImportExport\Helper\WPML_Compatibility;

defined( 'ABSPATH' ) || exit;

class Content_Sync_API_Controller {

	/**
	 * Current REST API namespace.
	 */
	private const REST_NAMESPACE = 'rsl-ie/v1';

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
			$routes = array(
				'/validate'           => array(
					'methods'  => 'POST',
					'callback' => array( $this, 'validate_connection' ),
				),
				'/info'               => array(
					'methods'  => 'GET',
					'callback' => array( $this, 'get_site_info' ),
				),
				'/receive-content'    => array(
					'methods'  => 'POST',
					'callback' => array( $this, 'receive_content' ),
				),
				'/send-content'       => array(
					'methods'  => 'POST',
					'callback' => array( $this, 'send_content' ),
				),
				'/check-media'        => array(
					'methods'  => 'POST',
					'callback' => array( $this, 'check_media' ),
				),
				'/upload-media'       => array(
					'methods'  => 'POST',
					'callback' => array( $this, 'upload_media' ),
				),
				'/list-posts'         => array(
					'methods'  => 'POST',
					'callback' => array( $this, 'list_posts' ),
				),
				'/get-children-posts' => array(
					'methods'  => 'POST',
					'callback' => array( $this, 'get_children_posts' ),
				),
				'/list-terms'         => array(
					'methods'  => 'POST',
					'callback' => array( $this, 'list_terms' ),
				),
				'/send-terms'         => array(
					'methods'  => 'POST',
					'callback' => array( $this, 'send_terms' ),
				),
				'/receive-terms'      => array(
					'methods'  => 'POST',
					'callback' => array( $this, 'receive_terms' ),
				),
				'/list-comments'      => array(
					'methods'  => 'POST',
					'callback' => array( $this, 'list_comments' ),
				),
				'/send-comments'      => array(
					'methods'  => 'POST',
					'callback' => array( $this, 'send_comments' ),
				),
				'/receive-comments'   => array(
					'methods'  => 'POST',
					'callback' => array( $this, 'receive_comments' ),
				),
			);

			foreach ( $routes as $route => $config ) {
				register_rest_route(
					self::REST_NAMESPACE,
					$route,
					array(
						'methods'             => $config['methods'],
						'callback'            => $config['callback'],
						'permission_callback' => array( $this, 'validate_api_key' ),
					)
				);
			}
		} catch ( \Exception $e ) {
			// Log error but don't break the site
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
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
		$site_key = get_option( 'rsl_ie_site_api_key' );

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
				'message' => __( 'Connection validated successfully', 'import-export-by-rockstarlab' ),
				'data'    => array(
					'site_name'      => get_bloginfo( 'name' ),
					'site_url'       => get_site_url(),
					'wp_version'     => get_bloginfo( 'version' ),
					'plugin_version' => defined( 'RSL_IE_VERSION' ) ? RSL_IE_VERSION : '1.0.0',
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
					'plugin_version' => defined( 'RSL_IE_VERSION' ) ? RSL_IE_VERSION : '1.0.0',
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
		$posts_data    = $request->get_param( 'posts' );
		$image_map     = $request->get_param( 'image_map' );
		$image_sources = $request->get_param( 'image_sources' );
		$post_mapping  = $request->get_param( 'post_mapping' );

		if ( empty( $posts_data ) || ! is_array( $posts_data ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'No posts data provided', 'import-export-by-rockstarlab' ),
				),
				400
			);
		}

		// Parse post_mapping
		if ( ! is_array( $post_mapping ) ) {
			$post_mapping = array();
		}
		if ( ! is_array( $image_sources ) ) {
			$image_sources = array();
		}

		$imported_count      = 0;
		$updated_count       = 0;
		$errors              = array();
		$source_to_local_map = array();
		$source_parent_map   = array();
		$source_type_map     = array();
		$product_post_ids    = array();

		foreach ( $posts_data as $post_data ) {
			$source_post_id = $post_data['ID'];
			$target_post_id = null;

			// Check post mapping
			if ( array_key_exists( $source_post_id, $post_mapping ) ) {
				$mapped_value = $post_mapping[ $source_post_id ];

				// If mapped to specific ID, use it
				if ( is_numeric( $mapped_value ) && $mapped_value > 0 ) {
					$target_post_id = (int) $mapped_value;
				}
				// If mapped to "new" or null, create new post (target_post_id stays null)
			} else {
				// No mapping provided, use default logic (find by original ID)
				$target_post_id = $this->find_existing_post( $post_data );
			}

			// Check if images referenced in content exist
			if ( preg_match_all( '/wp-image-(\d+)/', $post_data['post_content'], $matches ) ) {
				foreach ( $matches[1] as $img_id ) {
					$attachment = get_post( $img_id );
					if ( $attachment && 'attachment' === $attachment->post_type ) {
						$url         = wp_get_attachment_url( $img_id );
						$file_path   = get_attached_file( $img_id );
						$file_exists = file_exists( $file_path );
					} else {
					}
				}
			}

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

			$is_update = false;

			// Use target_post_id from mapping if available
			if ( $target_post_id ) {
				// Update specific post
				$existing_post = get_post( $target_post_id );
				if ( $existing_post ) {
					$post_args['ID'] = $target_post_id;
					$post_id         = wp_update_post( $post_args );
					$is_update       = true;
				} else {
					// Mapped ID doesn't exist, create new post
					$post_id = wp_insert_post( $post_args );

					// Store original ID for future sync operations
					if ( ! is_wp_error( $post_id ) && $post_id ) {
						update_post_meta( $post_id, '_rsl_ie_original_post_id', $source_post_id );
					}
				}
			} else {
				// Create new post (no mapping or mapped to "new")
				$post_id = wp_insert_post( $post_args );

				// Store original ID for future sync operations
				if ( ! is_wp_error( $post_id ) && $post_id ) {
					update_post_meta( $post_id, '_rsl_ie_original_post_id', $source_post_id );
				}
			}

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				$errors[] = sprintf(
					/* translators: %s: post title */
					__( 'Failed to import post: %s', 'import-export-by-rockstarlab' ),
					$post_data['post_title']
				);
				continue;
			}

			$source_to_local_map[ (int) $source_post_id ] = (int) $post_id;
			$source_type_map[ (int) $source_post_id ]     = isset( $post_data['post_type'] ) ? (string) $post_data['post_type'] : '';
			if ( array_key_exists( 'post_parent', $post_data ) ) {
				$source_parent_map[ (int) $source_post_id ] = (int) $post_data['post_parent'];
			}
			if ( isset( $post_data['post_type'] ) && 'product' === $post_data['post_type'] ) {
				$product_post_ids[] = (int) $post_id;
			}
			$this->apply_synced_post_wpml_data( (int) $post_id, (array) $post_data, $source_to_local_map );

			// Count created vs updated
			if ( $is_update ) {
				++$updated_count;
			} else {
				++$imported_count;
			}

			// Fix image URLs in content after import (replace with correct attachment URLs)
			if ( ! empty( $image_map ) ) {
				$post_content = get_post_field( 'post_content', $post_id );
				$new_content  = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::fix_local_image_urls_in_content( $post_content, (array) $image_map, (array) $image_sources );

				// Update post content if URLs were fixed
				if ( $new_content !== $post_content ) {
					wp_update_post(
						array(
							'ID'           => $post_id,
							'post_content' => $new_content,
						)
					);
				}
			}

			// Import meta
			if ( ! empty( $post_data['meta'] ) ) {

				// Replace image IDs and domain in meta using the proper meta-aware replacer.
				// replace_in_meta correctly handles _thumbnail_id, ACF image/file fields
				// (using field-type introspection), flat ACF repeater keys, etc.
				if ( ! empty( $image_map ) ) {
					$post_data['meta'] = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::replace_in_meta_public(
						$post_data['meta'],
						'', // No domain replacement needed for push (already replaced on sender)
						'',
						$image_map,
						$image_sources
					);
				}

				foreach ( $post_data['meta'] as $key => $value ) {
					// Skip some internal WordPress meta
					if ( in_array( $key, array( '_edit_lock', '_edit_last', '_rsl_ie_original_post_id' ), true ) ) {
						continue;
					}

					$this->save_synced_post_meta( (int) $post_id, (string) $key, $value );
				}
			}

			// Import terms with ACF fields
			if ( ! empty( $post_data['terms'] ) ) {
				// Build a map of source_term_id → local_term_id so we can fix
				// ACF taxonomy fields in meta that still hold source-site IDs.
				$term_id_map = array();

				// Clear ALL existing term assignments for every taxonomy the source
				// sent (including empty ones) so stale remote terms are removed.
				foreach ( array_keys( $post_data['terms'] ) as $taxonomy_to_clear ) {
					$this->ensure_woocommerce_attribute_taxonomy( $taxonomy_to_clear );
					if ( taxonomy_exists( $taxonomy_to_clear ) ) {
						wp_set_object_terms( $post_id, array(), $taxonomy_to_clear );
					}
				}

				foreach ( $post_data['terms'] as $taxonomy => $terms_info ) {
					$this->ensure_woocommerce_attribute_taxonomy( $taxonomy );
					// Check if taxonomy exists
					if ( ! taxonomy_exists( $taxonomy ) ) {
						continue;
					}

					$term_ids = array();
					foreach ( $terms_info as $term_info ) {
						// Validate term info
						if ( empty( $term_info['name'] ) || empty( $term_info['slug'] ) ) {
							continue;
						}

						$term_id = $this->resolve_synced_term( $taxonomy, $term_info );
						if ( $term_id <= 0 ) {
							continue;
						}

						$term_ids[] = (int) $term_id;

						// Record source → local term ID mapping.
						if ( ! empty( $term_info['term_id'] ) ) {
							$term_id_map[ (int) $term_info['term_id'] ] = (int) $term_id;
						}

						// Import ACF fields for this term
						if ( ! empty( $term_info['acf'] ) && function_exists( 'update_field' ) ) {
							// Replace image IDs in term ACF fields
							$term_acf = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::replace_in_array(
								$term_info['acf'],
								'', // No domain replacement needed for term meta
								'',
								$image_map,
								$image_sources
							);

							foreach ( $term_acf as $field_key => $field_value ) {
								ACF_Fields::import_value( 'term', $term_id, sanitize_text_field( (string) $field_key ), $field_value, $taxonomy );
							}
						}
					}

					// Assign terms to post
					wp_set_object_terms( $post_id, $term_ids, $taxonomy );
				}

				// Re-save ACF taxonomy fields with correct local term IDs.
				if ( ! empty( $term_id_map ) && ! empty( $post_data['meta'] ) ) {
					\RockStarLab\ImportExport\Helper\Content_Sync_Replacer::translate_acf_taxonomy_fields_in_meta(
						$post_data['meta'],
						$post_id,
						$term_id_map
					);
				}
			}

				// Re-save ACF post reference fields (post_object / relationship) with correct local IDs.
			if ( ! empty( $post_data['meta'] ) ) {
				\RockStarLab\ImportExport\Helper\Content_Sync_Replacer::translate_acf_post_reference_fields_in_meta(
					$post_data['meta'],
					$post_id,
					$source_post_id,
					isset( $post_data['post_refs'] ) ? $post_data['post_refs'] : array()
				);
			}

				// Import WooCommerce product variations and recalculate the variable
				// product price range so the remote site shows the correct prices.
			if ( 'product' === $post_data['post_type']
				&& ! empty( $post_data['variations'] )
				&& class_exists( 'WC_Product' )
				&& function_exists( 'wc_get_product' )
			) {
				$this->import_product_variations( $post_id, $post_data['variations'], (array) $image_map, (array) $image_sources );
			}

			// Import WooCommerce grouped product children and remap _children meta.
			// Children are regular products whose IDs differ between sites, so we
			// must import them and rewrite the _children meta with local IDs.
			if ( 'product' === $post_data['post_type']
				&& ! empty( $post_data['grouped_children'] )
			&& class_exists( 'WC_Product' )
			&& function_exists( 'wc_get_product' )
			) {
				$local_child_ids = $this->import_grouped_children( $post_id, $post_data['grouped_children'], (array) $image_map, (array) $image_sources );
				if ( ! empty( $local_child_ids ) ) {
					update_post_meta( $post_id, '_children', $local_child_ids );
				}
			}

			if ( ! empty( $post_data['comments'] ) ) {
				$this->import_synced_comments( $post_id, $post_data['comments'] );
			}

			if ( 'product' === $post_data['post_type'] ) {
				$this->refresh_woocommerce_product_after_sync( $post_id );
			}
		}

		$this->apply_synced_posts_wpml_data( $posts_data, $source_to_local_map );

		// Fix hierarchical relationships (e.g. pages) after import so we can resolve
		// parent IDs created in the same request.
		foreach ( $source_to_local_map as $source_id => $local_id ) {
			if ( ! array_key_exists( $source_id, $source_parent_map ) ) {
				continue;
			}

			$source_parent_id = $source_parent_map[ $source_id ];
			$post_type        = isset( $source_type_map[ $source_id ] ) ? $source_type_map[ $source_id ] : '';

			if ( empty( $post_type ) || ! is_post_type_hierarchical( $post_type ) ) {
				continue;
			}

			$local_parent_id = 0;
			if ( $source_parent_id > 0 ) {
				if ( isset( $source_to_local_map[ $source_parent_id ] ) ) {
					$local_parent_id = (int) $source_to_local_map[ $source_parent_id ];
				} else {
					$local_parent_id = (int) $this->find_existing_post_by_original_id( $source_parent_id, $post_type );
				}

				// Parent not available locally - don't force reset the relationship.
				if ( empty( $local_parent_id ) ) {
					continue;
				}

				$parent_post = get_post( $local_parent_id );
				if ( ! $parent_post || $parent_post->post_type !== $post_type ) {
					continue;
				}
			}

			$child_post = get_post( $local_id );
			if ( ! $child_post || $child_post->post_type !== $post_type ) {
				continue;
			}

			if ( (int) $child_post->post_parent === (int) $local_parent_id ) {
				continue;
			}

			wp_update_post(
				array(
					'ID'          => $local_id,
					'post_parent' => $local_parent_id,
				)
			);
		}

		foreach ( array_unique( $product_post_ids ) as $product_post_id ) {
			$this->refresh_woocommerce_product_after_sync( $product_post_id );
		}

		$total_processed = $imported_count + $updated_count;
		$message         = array();

		if ( $imported_count > 0 ) {
			$message[] = sprintf(
				/* translators: %d: number of posts */
				_n( 'Created %d post', 'Created %d posts', $imported_count, 'import-export-by-rockstarlab' ),
				$imported_count
			);
		}

		if ( $updated_count > 0 ) {
			$message[] = sprintf(
				/* translators: %d: number of posts */
				_n( 'Updated %d post', 'Updated %d posts', $updated_count, 'import-export-by-rockstarlab' ),
				$updated_count
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => implode( ', ', $message ),
				'data'    => array(
					'created' => $imported_count,
					'updated' => $updated_count,
					'total'   => $total_processed,
					'errors'  => $errors,
				),
			),
			200
		);
	}

	/**
	 * Convert flat ACF meta structure to hierarchical for repeater/flexible content fields
	 *
	 * @param array $meta Post meta array
	 * @param array $acf_field_keys ACF field keys mapping
	 * @return array Modified meta array
	 */
	private function convert_acf_flat_to_hierarchical( $meta, $acf_field_keys ) {
		$processed_parents = array();

		// Find all repeater/flexible content parent fields
		foreach ( $acf_field_keys as $field_name => $field_key ) {
			// Skip nested fields
			if ( preg_match( '/_\d+_/', $field_name ) ) {
				continue;
			}

			// Check if this field has a numeric value (count of rows) - typical for repeater
			if ( isset( $meta[ $field_name ] ) && is_numeric( $meta[ $field_name ] ) ) {
				$row_count = intval( $meta[ $field_name ] );

				// Verify this is actually a repeater by checking if sub-fields exist
				// Look for pattern: field_name_0_*
				$has_sub_fields = false;
				$row_prefix     = $field_name . '_0_';
				foreach ( $meta as $meta_key => $meta_value ) {
					if ( strpos( $meta_key, $row_prefix ) === 0 ) {
						$has_sub_fields = true;
						break;
					}
				}

				// If no sub-fields found, this is not a repeater (probably just a numeric field like image ID)
				if ( ! $has_sub_fields ) {
					continue;
				}

				// Build hierarchical structure
				$rows = array();
				for ( $i = 0; $i < $row_count; $i++ ) {
					$row_data     = array();
					$row_prefix   = $field_name . '_' . $i . '_';
					$found_fields = 0;

					// Find all fields for this row
					foreach ( $meta as $meta_key => $meta_value ) {
						if ( strpos( $meta_key, $row_prefix ) === 0 ) {
							++$found_fields;
							// Extract field name without row prefix
							$sub_field_name = substr( $meta_key, strlen( $row_prefix ) );

							// Check if this is a nested repeater/flexible content
							if ( isset( $acf_field_keys[ $field_name . '_' . $i . '_' . $sub_field_name ] ) && is_numeric( $meta_value ) ) {
								// Verify nested repeater has sub-fields
								$nested_prefix         = $field_name . '_' . $i . '_' . $sub_field_name . '_0_';
								$nested_has_sub_fields = false;
								foreach ( $meta as $nested_key => $nested_val ) {
									if ( strpos( $nested_key, $nested_prefix ) === 0 ) {
										$nested_has_sub_fields = true;
										break;
									}
								}

								if ( $nested_has_sub_fields ) {
									// Recursively process nested repeater
									$nested_rows                 = $this->extract_nested_repeater_data( $meta, $field_name . '_' . $i . '_' . $sub_field_name, $meta_value, $acf_field_keys );
									$row_data[ $sub_field_name ] = $nested_rows;
								} else {
									// Just a numeric value (like image ID)
									$row_data[ $sub_field_name ] = $meta_value;
								}
							} else {
								$row_data[ $sub_field_name ] = $meta_value;
							}
						}
					}

					$rows[] = $row_data;
				}

				// Replace numeric count with actual data array
				$meta[ $field_name ] = $rows;
				$processed_parents[] = $field_name;

			}
		}

		return $meta;
	}

	/**
	 * Extract nested repeater data recursively
	 *
	 * @param array  $meta Post meta array
	 * @param string $parent_prefix Parent field prefix (e.g., "repeater_0_nested_repeater")
	 * @param int    $row_count Number of rows
	 * @param array  $acf_field_keys ACF field keys mapping
	 * @return array Nested rows data
	 */
	private function extract_nested_repeater_data( $meta, $parent_prefix, $row_count, $acf_field_keys ) {
		$rows = array();

		for ( $i = 0; $i < $row_count; $i++ ) {
			$row_data   = array();
			$row_prefix = $parent_prefix . '_' . $i . '_';

			foreach ( $meta as $meta_key => $meta_value ) {
				if ( strpos( $meta_key, $row_prefix ) === 0 ) {
					$sub_field_name = substr( $meta_key, strlen( $row_prefix ) );

					// Check for even deeper nesting
					if ( isset( $acf_field_keys[ $parent_prefix . '_' . $i . '_' . $sub_field_name ] ) && is_numeric( $meta_value ) ) {
						// Verify this nested field actually has sub-fields (is a real repeater)
						$nested_prefix         = $parent_prefix . '_' . $i . '_' . $sub_field_name . '_0_';
						$has_nested_sub_fields = false;
						foreach ( $meta as $check_key => $check_value ) {
							if ( strpos( $check_key, $nested_prefix ) === 0 ) {
								$has_nested_sub_fields = true;
								break;
							}
						}

						if ( $has_nested_sub_fields ) {
							// This is a nested repeater
							$row_data[ $sub_field_name ] = $this->extract_nested_repeater_data(
								$meta,
								$parent_prefix . '_' . $i . '_' . $sub_field_name,
								$meta_value,
								$acf_field_keys
							);
						} else {
							// Just a numeric value (like image ID)
							$row_data[ $sub_field_name ] = $meta_value;
						}
					} else {
						$row_data[ $sub_field_name ] = $meta_value;
					}
				}
			}

			$rows[] = $row_data;
		}

		return $rows;
	}

	/**
	 * Import WooCommerce product variations and recalculate the variable product
	 * price range so the remote site displays the correct prices.
	 *
	 * @param int   $parent_post_id Local product post ID.
	 * @param array $variations     Variation data from the source site.
	 * @param array $image_map      Source attachment ID → local attachment ID map.
	 * @return void
	 */
	private function import_product_variations( $parent_post_id, $variations, $image_map, $image_sources = array() ) {
		if ( empty( $variations ) ) {
			return;
		}

		// Build a map of source-variation-ID → existing local variation ID so we
		// can update existing variations instead of always creating new ones.
		$source_to_local        = array();
		$existing_local_var_ids = get_posts(
			array(
				'post_type'      => 'product_variation',
				'post_parent'    => $parent_post_id,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $existing_local_var_ids as $local_var_id ) {
			$orig_id = (int) get_post_meta( $local_var_id, '_rsl_ie_original_post_id', true );
			if ( $orig_id ) {
				$source_to_local[ $orig_id ] = (int) $local_var_id;
			}
		}

		// Track which source variation IDs were processed so we can remove stale ones.
		$processed_source_ids = array();

		foreach ( $variations as $variation_data ) {
			$source_var_id = (int) ( isset( $variation_data['ID'] ) ? $variation_data['ID'] : 0 );

			$variation_args = array(
				'post_title'  => isset( $variation_data['post_title'] ) ? $variation_data['post_title'] : '',
				'post_name'   => isset( $variation_data['post_name'] ) ? $variation_data['post_name'] : '',
				'post_status' => isset( $variation_data['post_status'] ) ? $variation_data['post_status'] : 'publish',
				'post_type'   => 'product_variation',
				'post_parent' => $parent_post_id,
				'menu_order'  => isset( $variation_data['menu_order'] ) ? (int) $variation_data['menu_order'] : 0,
			);

			if ( $source_var_id && isset( $source_to_local[ $source_var_id ] ) ) {
				// Update existing variation.
				$variation_args['ID'] = $source_to_local[ $source_var_id ];
				$local_var_id         = wp_update_post( $variation_args );
			} else {
				// Create new variation.
				$local_var_id = wp_insert_post( $variation_args );
				if ( $local_var_id && ! is_wp_error( $local_var_id ) && $source_var_id ) {
					update_post_meta( $local_var_id, '_rsl_ie_original_post_id', $source_var_id );
				}
			}

			if ( is_wp_error( $local_var_id ) || ! $local_var_id ) {
				continue;
			}

			if ( $source_var_id ) {
				$processed_source_ids[] = $source_var_id;
			}

			// Import variation meta.
			if ( ! empty( $variation_data['meta'] ) ) {
				$var_meta = $variation_data['meta'];

				// Replace source attachment IDs with local ones.
				if ( ! empty( $image_map ) ) {
					$var_meta = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::replace_in_meta_public(
						$var_meta,
						'', // Domain replacement already done on the sender side.
						'',
						$image_map,
						$image_sources
					);
				}

				foreach ( $var_meta as $key => $value ) {
					if ( in_array( $key, array( '_edit_lock', '_edit_last' ), true ) ) {
						continue;
					}
					update_post_meta( $local_var_id, $key, $value );
				}
			}
		}

		// Delete stale local variations that no longer exist on the source site.
		foreach ( $existing_local_var_ids as $local_var_id ) {
			$orig_id = (int) get_post_meta( $local_var_id, '_rsl_ie_original_post_id', true );
			if ( $orig_id && ! in_array( $orig_id, $processed_source_ids, true ) ) {
				wp_delete_post( (int) $local_var_id, true );
			}
		}

		// Recalculate the variable product's price range from the synced variations.
		// This updates _price, _min_variation_price, _max_variation_price, etc.
		if ( function_exists( 'wc_get_product' ) && class_exists( 'WC_Product_Variable' ) ) {
			$wc_product = wc_get_product( $parent_post_id );
			if ( $wc_product && $wc_product->is_type( 'variable' ) ) {
				\WC_Product_Variable::sync( $wc_product );
			}
		}
	}

	/**
	 * Import WooCommerce grouped product children (regular product posts) and
	 * return an array of local post IDs in the same order as the source list.
	 *
	 * @param int   $parent_post_id Local grouped product post ID.
	 * @param array $children       Array of child product data from the source site.
	 * @param array $image_map      Source attachment ID → local attachment ID map.
	 * @return int[] Array of local child product post IDs.
	 */
	private function import_grouped_children( $parent_post_id, $children, $image_map, $image_sources = array() ) {
		$local_child_ids = array();

		foreach ( $children as $child_data ) {
			$source_child_id = (int) ( $child_data['ID'] ?? 0 );

			// Try to find an existing local product that was previously synced
			// from this source child.
			$local_child_id = null;
			if ( $source_child_id ) {
				$existing = get_posts(
					array(
						'post_type'      => 'product',
						'posts_per_page' => 1,
						'post_status'    => 'any',
						'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery
							array(
								'key'   => '_rsl_ie_original_post_id',
								'value' => $source_child_id,
							),
						),
					)
				);
				if ( ! empty( $existing ) ) {
					$local_child_id = (int) $existing[0]->ID;
				}
			}

			$child_args = array(
				'post_title'   => $child_data['post_title'] ?? '',
				'post_name'    => $child_data['post_name'] ?? '',
				'post_content' => $child_data['post_content'] ?? '',
				'post_excerpt' => $child_data['post_excerpt'] ?? '',
				'post_status'  => $child_data['post_status'] ?? 'publish',
				'post_type'    => 'product',
				'menu_order'   => (int) ( $child_data['menu_order'] ?? 0 ),
			);

			if ( $local_child_id ) {
				$child_args['ID'] = $local_child_id;
				$result           = wp_update_post( $child_args );
			} else {
				$result = wp_insert_post( $child_args );
				if ( $result && ! is_wp_error( $result ) && $source_child_id ) {
					update_post_meta( $result, '_rsl_ie_original_post_id', $source_child_id );
				}
				$local_child_id = $result;
			}

			if ( is_wp_error( $result ) || ! $result ) {
				continue;
			}

			// Import child meta.
			if ( ! empty( $child_data['meta'] ) ) {
				$child_meta = $child_data['meta'];
				if ( ! empty( $image_map ) ) {
					$child_meta = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::replace_in_meta_public(
						$child_meta,
						'',
						'',
						$image_map,
						$image_sources
					);
				}
				foreach ( $child_meta as $key => $value ) {
					if ( in_array( $key, array( '_edit_lock', '_edit_last' ), true ) ) {
						continue;
					}
					update_post_meta( $local_child_id, $key, $value );
				}
			}

			// Import child terms.
			if ( ! empty( $child_data['terms'] ) ) {
				foreach ( $child_data['terms'] as $taxonomy => $terms_info ) {
					$this->ensure_woocommerce_attribute_taxonomy( $taxonomy );
					if ( ! taxonomy_exists( $taxonomy ) ) {
						continue;
					}
					$term_ids = array();
					foreach ( $terms_info as $term_info ) {
						if ( empty( $term_info['name'] ) || empty( $term_info['slug'] ) ) {
							continue;
						}
						$term_id = $this->resolve_synced_term( $taxonomy, $term_info );
						if ( $term_id > 0 ) {
							$term_ids[] = (int) $term_id;
						}
					}
					wp_set_object_terms( $local_child_id, $term_ids, $taxonomy );
				}
			}

			$local_child_ids[] = (int) $local_child_id;
		}

		return $local_child_ids;
	}

	/**
	 * Find an existing local post that was previously synced from the given source post.
	 *
	 * @param array $post_data Post data from the remote site (must contain 'ID' and 'post_type').
	 * @return int|null Local post ID if found, null otherwise.
	 */
	private function find_existing_post( $post_data ) {
		// Only search by original post ID stored in meta
		if ( ! isset( $post_data['ID'] ) || ! isset( $post_data['post_type'] ) ) {
			return null;
		}

		$posts = get_posts(
			array(
				'post_type'      => $post_data['post_type'],
				'posts_per_page' => 1,
				'post_status'    => 'any',
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.
					array(
						'key'   => '_rsl_ie_original_post_id',
						'value' => $post_data['ID'],
					),
				),
			)
		);

		if ( ! empty( $posts ) ) {
			return (int) $posts[0]->ID;
		}

		return null;
	}

	/**
	 * Find existing post by original (source) post ID.
	 *
	 * @param int    $original_post_id Original post ID from source site.
	 * @param string $post_type        Optional. Post type to limit search.
	 * @return int|null Local post ID if found, null otherwise.
	 */
	private function find_existing_post_by_original_id( $original_post_id, $post_type = 'any' ) {
		$args = array(
			'post_type'      => $post_type ?: 'any',
			'posts_per_page' => 1,
			'post_status'    => 'any',
			'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery -- meta_query required for filtering.
				array(
					'key'   => '_rsl_ie_original_post_id',
					'value' => (int) $original_post_id,
				),
			),
			'fields'         => 'ids',
		);

		$posts = get_posts( $args );
		if ( ! empty( $posts ) ) {
			return (int) $posts[0];
		}

		return null;
	}

	/**
	 * Check whether a post meta key should be excluded from Content Sync payloads.
	 *
	 * @param string $key Meta key.
	 * @return bool Whether the key should be skipped.
	 */
	private function should_skip_synced_meta_key( $key ) {
		$key = (string) $key;

		if ( 0 === strpos( $key, '_icl_' ) || 0 === strpos( $key, '_wpml_' ) ) {
			return true;
		}

		return class_exists( '\RockStarLab\ImportExport\Helper\Elementor_Fields' )
			&& \RockStarLab\ImportExport\Helper\Elementor_Fields::is_generated_cache_key( $key );
	}

	/**
	 * Save synced post meta with special handling for generated/builder metadata.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Meta value.
	 * @return void
	 */
	private function save_synced_post_meta( $post_id, $key, $value ) {
		if ( $this->should_skip_synced_meta_key( $key ) ) {
			delete_post_meta( $post_id, $key );
			return;
		}

		if ( class_exists( '\RockStarLab\ImportExport\Helper\Elementor_Fields' )
			&& \RockStarLab\ImportExport\Helper\Elementor_Fields::is_elementor_meta_key( $key ) ) {
			\RockStarLab\ImportExport\Helper\Elementor_Fields::import_meta_value( (int) $post_id, $key, $value, true, false );
			return;
		}

		update_post_meta( $post_id, $key, $value );
	}

	/**
	 * Collect comments/reviews for a synced post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function collect_post_comments_for_sync( $post_id ) {
		$comments = get_comments(
			array(
				'post_id' => (int) $post_id,
				'status'  => 'all',
				'orderby' => 'comment_ID',
				'order'   => 'ASC',
			)
		);

		$data = array();
		foreach ( $comments as $comment ) {
			$meta          = get_comment_meta( $comment->comment_ID );
			$prepared_meta = array();
			foreach ( $meta as $key => $values ) {
				if ( '_rsl_ie_original_comment_id' === $key ) {
					continue;
				}
				$prepared_meta[ $key ] = maybe_unserialize( $values[0] );
			}

			$data[] = array(
				'comment_ID'           => (int) $comment->comment_ID,
				'comment_parent'       => (int) $comment->comment_parent,
				'comment_author'       => $comment->comment_author,
				'comment_author_email' => $comment->comment_author_email,
				'comment_author_url'   => $comment->comment_author_url,
				'comment_author_IP'    => $comment->comment_author_IP,
				'comment_date'         => $comment->comment_date,
				'comment_date_gmt'     => $comment->comment_date_gmt,
				'comment_content'      => $comment->comment_content,
				'comment_karma'        => (int) $comment->comment_karma,
				'comment_approved'     => $comment->comment_approved,
				'comment_agent'        => $comment->comment_agent,
				'comment_type'         => $comment->comment_type,
				'user_id'              => (int) $comment->user_id,
				'meta'                 => $prepared_meta,
			);
		}

		return $data;
	}

	/**
	 * Import synced comments/reviews for a local post.
	 *
	 * @param int   $post_id  Local post ID.
	 * @param array $comments Synced comments payload.
	 * @return void
	 */
	private function import_synced_comments( $post_id, $comments ) {
		$source_to_local = array();

		foreach ( (array) $comments as $comment_data ) {
			$source_comment_id = isset( $comment_data['comment_ID'] ) ? (int) $comment_data['comment_ID'] : 0;
			if ( $source_comment_id <= 0 ) {
				continue;
			}

			$existing_id = $this->find_existing_comment_by_original_id( $post_id, $source_comment_id );
			$parent_id   = 0;
			if ( ! empty( $comment_data['comment_parent'] ) ) {
				$source_parent = (int) $comment_data['comment_parent'];
				$parent_id     = isset( $source_to_local[ $source_parent ] ) ? (int) $source_to_local[ $source_parent ] : 0;
			}

			$args = array(
				'comment_post_ID'      => (int) $post_id,
				'comment_author'       => isset( $comment_data['comment_author'] ) ? sanitize_text_field( $comment_data['comment_author'] ) : '',
				'comment_author_email' => isset( $comment_data['comment_author_email'] ) ? sanitize_email( $comment_data['comment_author_email'] ) : '',
				'comment_author_url'   => isset( $comment_data['comment_author_url'] ) ? esc_url_raw( $comment_data['comment_author_url'] ) : '',
				'comment_author_IP'    => isset( $comment_data['comment_author_IP'] ) ? sanitize_text_field( $comment_data['comment_author_IP'] ) : '',
				'comment_date'         => isset( $comment_data['comment_date'] ) ? sanitize_text_field( $comment_data['comment_date'] ) : current_time( 'mysql' ),
				'comment_date_gmt'     => isset( $comment_data['comment_date_gmt'] ) ? sanitize_text_field( $comment_data['comment_date_gmt'] ) : current_time( 'mysql', true ),
				'comment_content'      => isset( $comment_data['comment_content'] ) ? wp_kses_post( $comment_data['comment_content'] ) : '',
				'comment_karma'        => isset( $comment_data['comment_karma'] ) ? (int) $comment_data['comment_karma'] : 0,
				'comment_approved'     => isset( $comment_data['comment_approved'] ) ? sanitize_text_field( $comment_data['comment_approved'] ) : '1',
				'comment_agent'        => isset( $comment_data['comment_agent'] ) ? sanitize_text_field( $comment_data['comment_agent'] ) : '',
				'comment_type'         => isset( $comment_data['comment_type'] ) ? sanitize_key( $comment_data['comment_type'] ) : '',
				'comment_parent'       => $parent_id,
				'user_id'              => isset( $comment_data['user_id'] ) ? (int) $comment_data['user_id'] : 0,
			);

			if ( $existing_id ) {
				$args['comment_ID'] = $existing_id;
				$result_id          = wp_update_comment( $args ) ? $existing_id : 0;
			} else {
				$result_id = wp_insert_comment( $args );
			}

			if ( ! $result_id || is_wp_error( $result_id ) ) {
				continue;
			}

			$source_to_local[ $source_comment_id ] = (int) $result_id;
			update_comment_meta( $result_id, '_rsl_ie_original_comment_id', $source_comment_id );

			if ( ! empty( $comment_data['meta'] ) && is_array( $comment_data['meta'] ) ) {
				foreach ( $comment_data['meta'] as $key => $value ) {
					if ( '_rsl_ie_original_comment_id' === $key ) {
						continue;
					}
					update_comment_meta( $result_id, sanitize_key( $key ), $value );
				}
			}
		}
	}

	/**
	 * Find an existing synced comment on a local post.
	 *
	 * @param int $post_id            Local post ID.
	 * @param int $source_comment_id  Source comment ID.
	 * @return int
	 */
	private function find_existing_comment_by_original_id( $post_id, $source_comment_id ) {
		$comments = get_comments(
			array(
				'post_id'    => (int) $post_id,
				'status'     => 'all',
				'number'     => 1,
				'meta_key'   => '_rsl_ie_original_comment_id', // phpcs:ignore WordPress.DB.SlowDBQuery -- Exact source comment lookup for sync mapping.
				'meta_value' => (int) $source_comment_id, // phpcs:ignore WordPress.DB.SlowDBQuery -- Exact source comment lookup for sync mapping.
				'fields'     => 'ids',
			)
		);

		return ! empty( $comments ) ? (int) $comments[0] : 0;
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
					'message' => __( 'No post IDs provided', 'import-export-by-rockstarlab' ),
				),
				400
			);
		}

		$posts_data    = array();
		$all_images    = array();
		$not_found_ids = array();

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				$not_found_ids[] = $post_id;
				continue;
			}

			// Extract all images from post
			$post_images = \RockStarLab\ImportExport\Helper\Content_Sync_Media::extract_post_images( $post_id );

			// Store images
			foreach ( $post_images as $image ) {
				$image_key                = $image['attachment_id'];
				$all_images[ $image_key ] = $image;
			}

			// Get post meta
			$meta          = get_post_meta( $post_id );
			$prepared_meta = array();

			// Keys to skip (WordPress internal and potentially problematic)
			$skip_keys = array(
				'_edit_lock',
				'_edit_last',
				'_wp_old_slug',
				'_wp_old_date',
				'_rsl_ie_original_post_id', // Our own sync meta
			);

			foreach ( $meta as $key => $values ) {
				// Skip protected keys and certain internal WordPress keys
				if ( in_array( $key, $skip_keys, true ) || $this->should_skip_synced_meta_key( (string) $key ) ) {
					continue;
				}

				$prepared_meta[ $key ] = maybe_unserialize( $values[0] );
			}

			// Get post terms with ACF fields
			$taxonomies = get_object_taxonomies( $post->post_type );
			$terms_data = array();
			foreach ( $taxonomies as $taxonomy ) {
				$terms = wp_get_post_terms( $post_id, $taxonomy );
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					$terms_data[ $taxonomy ] = array();
					foreach ( $terms as $term ) {
						$term_info = array(
							'term_id'        => $term->term_id,
							'name'           => $term->name,
							'slug'           => $term->slug,
							'parent_term_id' => (int) $term->parent,
							'parent_slug'    => $this->get_term_slug_by_id( (int) $term->parent, $taxonomy ),
							'parent_path'    => $this->get_term_parent_path( (int) $term->parent, $taxonomy ),
						);
						$this->append_wpml_term_sync_data( $term_info, (int) $term->term_id, $taxonomy );

						// Get ACF fields for this term
						if ( function_exists( 'get_field_objects' ) ) {
							$acf_fields = $this->get_term_acf_field_objects( (int) $term->term_id, $taxonomy );
							if ( $acf_fields ) {
								$term_info['acf'] = array();
								foreach ( $acf_fields as $field_key => $field ) {
									$field_name                      = ! empty( $field['name'] ) ? (string) $field['name'] : (string) $field_key;
									$term_info['acf'][ $field_name ] = ACF_Fields::export_value( 'term', (int) $term->term_id, $field_name, $taxonomy );
								}
							}
						}

						$terms_data[ $taxonomy ][] = $term_info;

						// Extract images from term ACF fields
						if ( ! empty( $term_info['acf'] ) ) {
							$term_images = $this->extract_term_acf_images( $term_info['acf'] );
							foreach ( $term_images as $image_id ) {
								if ( ! isset( $all_images[ $image_id ] ) ) {
									// Use prepare_image_data to include file_hash for proper dedup on receiving side.
									$image_data = \RockStarLab\ImportExport\Helper\Content_Sync_Media::prepare_image_data( $image_id, 'term_acf' );
									if ( ! $image_data ) {
										// Fallback if file is missing on disk.
										$image_data = array(
											'attachment_id' => $image_id,
											'url'  => wp_get_attachment_url( $image_id ),
											'type' => 'term_acf',
										);
									}
									$image_data['term_id']   = $term->term_id;
									$image_data['taxonomy']  = $taxonomy;
									$all_images[ $image_id ] = $image_data;
								}
							}
						}
					}
				}
			}

			// Augment $terms_data with terms referenced inside ACF taxonomy fields.
			// ACF's "save_terms" option defaults to disabled, meaning term IDs are stored
			// only in post_meta and never appear in wp_term_relationships / wp_get_post_terms.
			if ( function_exists( 'acf_get_field' ) ) {
				foreach ( $prepared_meta as $meta_key => $meta_value ) {
					if ( strpos( $meta_key, '_' ) === 0 ) {
						continue;
					}
					$field_ref_key = '_' . $meta_key;
					if ( ! isset( $prepared_meta[ $field_ref_key ] ) ) {
						continue;
					}
					$field_ref = $prepared_meta[ $field_ref_key ];
					if ( ! is_string( $field_ref ) || strpos( $field_ref, 'field_' ) !== 0 ) {
						continue;
					}
					$field_obj = acf_get_field( $field_ref );
					if ( ! $field_obj || ! isset( $field_obj['type'] ) || $field_obj['type'] !== 'taxonomy' ) {
						continue;
					}
					$acf_taxonomy = isset( $field_obj['taxonomy'] ) ? $field_obj['taxonomy'] : '';
					if ( ! $acf_taxonomy || ! taxonomy_exists( $acf_taxonomy ) ) {
						continue;
					}
					$raw_ids = is_array( $meta_value ) ? $meta_value : array( $meta_value );
					if ( ! isset( $terms_data[ $acf_taxonomy ] ) ) {
						$terms_data[ $acf_taxonomy ] = array();
					}
					$known_ids = array_column( $terms_data[ $acf_taxonomy ], 'term_id' );
					foreach ( $raw_ids as $raw_id ) {
						if ( ! is_numeric( $raw_id ) || (int) $raw_id <= 0 ) {
							continue;
						}
						$raw_id = (int) $raw_id;
						if ( in_array( $raw_id, $known_ids, true ) ) {
							continue;
						}
						$term = get_term( $raw_id, $acf_taxonomy );
						if ( ! $term || is_wp_error( $term ) ) {
							continue;
						}
						$terms_data[ $acf_taxonomy ][] = array(
							'term_id'        => $term->term_id,
							'name'           => $term->name,
							'slug'           => $term->slug,
							'parent_term_id' => (int) $term->parent,
							'parent_slug'    => $this->get_term_slug_by_id( (int) $term->parent, $acf_taxonomy ),
							'parent_path'    => $this->get_term_parent_path( (int) $term->parent, $acf_taxonomy ),
						);
						$last_index                    = array_key_last( $terms_data[ $acf_taxonomy ] );
						if ( null !== $last_index ) {
							$this->append_wpml_term_sync_data( $terms_data[ $acf_taxonomy ][ $last_index ], (int) $term->term_id, $acf_taxonomy );
						}
						$known_ids[] = $raw_id;
					}
				}
			}

				$post_data = array(
					'ID'            => $post->ID,
					'post_title'    => $post->post_title,
					'post_content'  => $post->post_content,
					'post_excerpt'  => $post->post_excerpt,
					'post_status'   => $post->post_status,
					'post_type'     => $post->post_type,
					'post_parent'   => $post->post_parent,
					'post_name'     => $post->post_name,
					'post_date'     => $post->post_date,
					'post_modified' => $post->post_modified,
					'post_author'   => $post->post_author,
					'meta'          => $prepared_meta,
					'post_refs'     => \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::collect_acf_post_reference_map_from_meta( $prepared_meta ),
					'terms'         => $terms_data,
					'comments'      => $this->collect_post_comments_for_sync( $post->ID ),
				);
				$this->append_wpml_post_sync_data( $post_data, (int) $post->ID, (string) $post->post_type );

				// Collect WooCommerce product variations for variable products.
				if ( 'product' === $post->post_type
					&& class_exists( 'WC_Product' )
					&& function_exists( 'wc_get_product' )
				) {
					$wc_product = wc_get_product( $post->ID );
					if ( $wc_product && $wc_product->is_type( 'variable' ) ) {
						$variation_ids   = $wc_product->get_children();
						$variations_data = array();

						foreach ( $variation_ids as $variation_id ) {
							$variation_post = get_post( $variation_id );
							if ( ! $variation_post ) {
								continue;
							}

							$var_images = \RockStarLab\ImportExport\Helper\Content_Sync_Media::extract_post_images( $variation_id );
							foreach ( $var_images as $var_img ) {
								$var_img_key                = $var_img['attachment_id'];
								$all_images[ $var_img_key ] = $var_img;
							}

							$var_raw_meta  = get_post_meta( $variation_id );
							$var_prep_meta = array();
							foreach ( $var_raw_meta as $vk => $vv ) {
								$var_prep_meta[ $vk ] = maybe_unserialize( $vv[0] );
							}

							$variations_data[] = array(
								'ID'          => $variation_post->ID,
								'post_title'  => $variation_post->post_title,
								'post_name'   => $variation_post->post_name,
								'post_status' => $variation_post->post_status,
								'post_type'   => $variation_post->post_type,
								'menu_order'  => $variation_post->menu_order,
								'meta'        => $var_prep_meta,
							);
						}

						$post_data['variations'] = $variations_data;
					}
				}

				$posts_data[] = $post_data;
		}

		if ( empty( $posts_data ) ) {
			$error_message = __( 'No valid posts found', 'import-export-by-rockstarlab' );
			if ( ! empty( $not_found_ids ) ) {
				$error_message .= sprintf(
					/* translators: %s: comma-separated list of post IDs */
					__( '. Post IDs not found: %s', 'import-export-by-rockstarlab' ),
					implode( ', ', $not_found_ids )
				);
			}
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => $error_message,
				),
				404
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => sprintf(
					/* translators: %d: number of posts */
					__( 'Found %d post(s)', 'import-export-by-rockstarlab' ),
					count( $posts_data )
				),
				'data'    => array(
					'posts'  => $posts_data,
					'images' => array_values( $all_images ),
				),
			),
			200
		);
	}

	/**
	 * Check if media exists by hash
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function check_media( $request ) {
		$file_hash            = $request->get_param( 'file_hash' );
		$source_attachment_id = absint( $request->get_param( 'source_attachment_id' ) );

		if ( $source_attachment_id > 0 ) {
			$existing_attachment = $this->find_attachment_by_original_attachment_id( $source_attachment_id );
			if ( $existing_attachment ) {
				return new \WP_REST_Response(
					array(
						'success'       => true,
						'exists'        => true,
						'attachment_id' => $existing_attachment,
					),
					200
				);
			}
		}

		if ( empty( $file_hash ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'File hash is required', 'import-export-by-rockstarlab' ),
				),
				400
			);
		}

		// Check if attachment with this hash exists
		$existing_attachment = $this->find_attachment_by_hash( $file_hash );

		if ( $existing_attachment ) {
			return new \WP_REST_Response(
				array(
					'success'       => true,
					'exists'        => true,
					'attachment_id' => $existing_attachment,
				),
				200
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'exists'  => false,
			),
			200
		);
	}

	/**
	 * Upload media file
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response object.
	 */
	public function upload_media( $request ) {
		$file_name            = $request->get_param( 'file_name' );
		$file_data            = $request->get_param( 'file_data' );
		$file_hash            = $request->get_param( 'file_hash' );
		$mime_type            = $request->get_param( 'mime_type' );
		$alt_text             = $request->get_param( 'alt_text' );
		$title                = $request->get_param( 'title' );
		$caption              = $request->get_param( 'caption' );
		$description          = $request->get_param( 'description' );
		$source_attachment_id = absint( $request->get_param( 'source_attachment_id' ) );
		$force_unique         = (bool) $request->get_param( 'force_unique' );
		$wpml_data            = $request->get_param( 'wpml' );
		if ( ! is_array( $wpml_data ) ) {
			$wpml_data = array();
		}

		if ( empty( $file_name ) || empty( $file_data ) || empty( $file_hash ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Missing required file data', 'import-export-by-rockstarlab' ),
				),
				400
			);
		}

		if ( $source_attachment_id > 0 ) {
			$existing_attachment = $this->find_attachment_by_original_attachment_id( $source_attachment_id );
			if ( $existing_attachment ) {
				\RockStarLab\ImportExport\Helper\Content_Sync_Media::ensure_image_sizes( $existing_attachment );
				$this->apply_synced_attachment_wpml_data( (int) $existing_attachment, $wpml_data );
				return new \WP_REST_Response(
					array(
						'success'       => true,
						'attachment_id' => $existing_attachment,
						'message'       => __( 'Media already exists', 'import-export-by-rockstarlab' ),
					),
					200
				);
			}
		}

		// Check if file already exists
		if ( ! $force_unique ) {
			$existing_attachment = $this->find_attachment_by_hash( $file_hash );
			if ( $existing_attachment ) {
				\RockStarLab\ImportExport\Helper\Content_Sync_Media::ensure_image_sizes( $existing_attachment );
				$this->apply_synced_attachment_wpml_data( (int) $existing_attachment, $wpml_data );
				return new \WP_REST_Response(
					array(
						'success'       => true,
						'attachment_id' => $existing_attachment,
						'message'       => __( 'Media already exists', 'import-export-by-rockstarlab' ),
					),
					200
				);
			}
		}

		// Decode transfer payload only; this is file content, not executable code.
		$file_contents = base64_decode( (string) $file_data, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		if ( false === $file_contents ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid file data', 'import-export-by-rockstarlab' ),
				),
				400
			);
		}

		// Verify file hash
		if ( md5( $file_contents ) !== $file_hash ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'File hash mismatch', 'import-export-by-rockstarlab' ),
				),
				400
			);
		}

			$upload_dir = wp_upload_dir();
			$file_path  = $upload_dir['path'] . '/' . wp_unique_filename( $upload_dir['path'], $file_name );

		// Write file
		$saved = @file_put_contents( $file_path, $file_contents );

		if ( false === $saved ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Failed to save file', 'import-export-by-rockstarlab' ),
				),
				500
			);
		}

		// Create attachment
		$attachment_data = array(
			'post_mime_type' => $mime_type,
			'post_title'     => $title ?: sanitize_file_name( pathinfo( $file_name, PATHINFO_FILENAME ) ),
			'post_content'   => $description ?: '',
			'post_excerpt'   => $caption ?: '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment_data, $file_path );

		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			@wp_delete_file( $file_path );
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Failed to create attachment', 'import-export-by-rockstarlab' ),
				),
				500
			);
		}

			// Generate and update attachment metadata
			\RockStarLab\ImportExport\Helper\Fs::load_attachment_metadata_core();
			$attach_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
			wp_update_attachment_metadata( $attachment_id, $attach_data );

		// Set alt text
		if ( ! empty( $alt_text ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
		}

		// Store file hash for future lookups
		\RockStarLab\ImportExport\Helper\Media_Hash::store_attachment_hash( $attachment_id, $file_hash, $file_path );
		if ( $source_attachment_id > 0 ) {
			update_post_meta( $attachment_id, '_rsl_ie_original_attachment_id', $source_attachment_id );
		}
		$this->apply_synced_attachment_wpml_data( (int) $attachment_id, $wpml_data );

		return new \WP_REST_Response(
			array(
				'success'       => true,
				'attachment_id' => $attachment_id,
				'url'           => wp_get_attachment_url( $attachment_id ),
				'message'       => __( 'Media uploaded successfully', 'import-export-by-rockstarlab' ),
			),
			200
		);
	}

	/**
	 * Find attachment by file hash
	 *
	 * @param string $file_hash File MD5 hash.
	 * @return int|false Attachment ID or false
	 */
	private function find_attachment_by_hash( $file_hash ) {
		return \RockStarLab\ImportExport\Helper\Media_Hash::get_attachment_by_hash( $file_hash, true );
	}

	/**
	 * Find an attachment previously synced from a specific source attachment ID.
	 *
	 * @param int $source_attachment_id Source attachment ID.
	 * @return int|false Attachment ID or false.
	 */
	private function find_attachment_by_original_attachment_id( $source_attachment_id ) {
		$source_attachment_id = absint( $source_attachment_id );
		if ( $source_attachment_id <= 0 ) {
			return false;
		}

		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_rsl_ie_original_attachment_id', // phpcs:ignore WordPress.DB.SlowDBQuery -- Exact source attachment lookup for sync mapping.
				'meta_value'     => $source_attachment_id, // phpcs:ignore WordPress.DB.SlowDBQuery -- Exact source attachment lookup for sync mapping.
			)
		);

		return ! empty( $attachments ) ? (int) $attachments[0] : false;
	}

	private function apply_synced_attachment_wpml_data( $attachment_id, array $wpml_data ) {
		if ( empty( $wpml_data ) || ! WPML_Compatibility::is_active() ) {
			return;
		}

		WPML_Compatibility::apply_post_language_details( (int) $attachment_id, $wpml_data, array() );
	}

	/**
	 * Extract image IDs from term ACF fields
	 *
	 * @param array $acf_data ACF field data.
	 * @return array Array of image IDs
	 */
	private function extract_term_acf_images( $acf_data ) {
		$image_ids = array();

		foreach ( $acf_data as $key => $value ) {
			if ( is_string( $value ) && '' !== $value ) {
				$decoded = json_decode( $value, true );
				if ( is_array( $decoded ) ) {
					$image_ids = array_merge( $image_ids, $this->extract_term_acf_images( $decoded ) );
				}

				if ( class_exists( ACF_Fields::class ) ) {
					foreach ( ACF_Fields::extract_media_shortcode_token_source_ids( $value ) as $image_id ) {
						$image_ids[] = (int) $image_id;
					}
				}

				if ( preg_match_all( '/\bwp-image-(\d+)\b/', $value, $matches ) ) {
					foreach ( $matches[1] as $image_id ) {
						$image_ids[] = (int) $image_id;
					}
				}

				if ( preg_match_all( '/\[(gallery|playlist)\b[^\]]*\bids=["\']([\d,\s]+)["\'][^\]]*\]/i', $value, $matches ) ) {
					foreach ( $matches[2] as $ids_string ) {
						foreach ( array_filter( array_map( 'absint', preg_split( '/\s*,\s*/', (string) $ids_string ) ?: array() ) ) as $attachment_id ) {
							$image_ids[] = (int) $attachment_id;
						}
					}
				}

				foreach ( $this->extract_image_urls_from_term_acf_html( $value ) as $url ) {
					$image_id = attachment_url_to_postid( $url );
					if ( $image_id > 0 ) {
						$image_ids[] = (int) $image_id;
					}
				}

				if ( filter_var( $value, FILTER_VALIDATE_URL ) && $this->is_term_acf_image_url( $value ) ) {
					$image_id = attachment_url_to_postid( $value );
					if ( $image_id > 0 ) {
						$image_ids[] = (int) $image_id;
					}
				}
			}

			if ( is_array( $value ) ) {
				foreach ( array( 'ID', 'id', 'attachment_id' ) as $id_key ) {
					if ( isset( $value[ $id_key ] ) && is_numeric( $value[ $id_key ] ) ) {
						$image_id   = (int) $value[ $id_key ];
						$attachment = get_post( $image_id );
						if ( $attachment && 'attachment' === $attachment->post_type ) {
							$image_ids[] = $image_id;
						}
					}
				}

				if ( isset( $value['url'] ) && is_string( $value['url'] ) && $this->is_term_acf_image_url( $value['url'] ) ) {
					$image_id = attachment_url_to_postid( $value['url'] );
					if ( $image_id > 0 ) {
						$image_ids[] = (int) $image_id;
					}
				}
			}

			// Single image field (numeric ID)
			if ( is_numeric( $value ) && $value > 0 ) {
				$attachment = get_post( $value );
				if ( $attachment && 'attachment' === $attachment->post_type ) {
					$image_ids[] = (int) $value;
				}
			}
			// Gallery field (array of IDs)
			elseif ( is_array( $value ) ) {
				foreach ( $value as $item ) {
					if ( is_numeric( $item ) && $item > 0 ) {
						$attachment = get_post( $item );
						if ( $attachment && 'attachment' === $attachment->post_type ) {
							$image_ids[] = (int) $item;
						}
					}
					// Nested arrays (repeater, flexible content)
					elseif ( is_array( $item ) ) {
						$nested_images = $this->extract_term_acf_images( $item );
						$image_ids     = array_merge( $image_ids, $nested_images );
					}
				}
			}
		}

		return array_unique( $image_ids );
	}

	private function extract_image_urls_from_term_acf_html( $content ) {
		$urls = array();

		if ( false === stripos( $content, '<img' ) && false === stripos( $content, 'srcset=' ) ) {
			return $urls;
		}

		if ( preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				$url = html_entity_decode( $url, ENT_QUOTES, get_bloginfo( 'charset' ) );
				if ( $this->is_term_acf_image_url( $url ) ) {
					$urls[] = $url;
				}
			}
		}

		if ( preg_match_all( '/srcset=["\']([^"\']+)["\']/i', $content, $matches ) ) {
			foreach ( $matches[1] as $srcset ) {
				foreach ( array_map( 'trim', explode( ',', $srcset ) ) as $candidate ) {
					$parts = preg_split( '/\s+/', $candidate );
					$url   = isset( $parts[0] ) ? html_entity_decode( $parts[0], ENT_QUOTES, get_bloginfo( 'charset' ) ) : '';
					if ( $this->is_term_acf_image_url( $url ) ) {
						$urls[] = $url;
					}
				}
			}
		}

		return array_values( array_unique( $urls ) );
	}

	private function is_term_acf_image_url( $url ) {
		$path = (string) wp_parse_url( html_entity_decode( (string) $url, ENT_QUOTES, get_bloginfo( 'charset' ) ), PHP_URL_PATH );
		return '' !== $path && (bool) preg_match( '~\.(?:jpe?g|png|gif|webp|avif|svg)$~i', $path );
	}

	private function get_term_acf_field_objects( $term_id, $taxonomy ) {
		if ( ! function_exists( 'get_field_objects' ) ) {
			return false;
		}

		$fields = get_field_objects( 'term_' . (int) $term_id );
		if ( ! empty( $fields ) ) {
			return $fields;
		}

		return get_field_objects( sanitize_key( (string) $taxonomy ) . '_' . (int) $term_id );
	}

	/**
	 * List posts for mapping
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function list_posts( $request ) {
			$post_type        = $request->get_param( 'post_type' );
			$search           = $request->get_param( 'search' );
			$status           = $request->get_param( 'status' );
			$commentable_only = filter_var( $request->get_param( 'commentable_only' ), FILTER_VALIDATE_BOOLEAN );
			$comment_type     = sanitize_key( (string) ( $request->get_param( 'comment_type' ) ?: '' ) );
		$page                 = absint( $request->get_param( 'page' ) ?: 1 );
		$per_page             = absint( $request->get_param( 'per_page' ) ?: 20 );
		$language             = sanitize_key( (string) ( $request->get_param( 'language' ) ?: '' ) );
		if ( ! WPML_Compatibility::is_active() ) {
			$language = '';
		}
		$is_wpml_language_browse = '' !== $language;

		// When searching, include all posts (parent and children)
		// When not searching, show only parent posts (to maintain hierarchy)
		$post_parent_filter = 0; // Default: only top-level posts
		if ( ! empty( $search ) || $is_wpml_language_browse ) {
			$post_parent_filter = ''; // Empty string means no parent filter - include all posts
		}

			$args = array(
				'post_type'           => $commentable_only ? $this->get_commentable_sync_post_types_for_context( $comment_type, $post_type ) : ( $post_type ?: 'any' ),
				'post_status'         => ! empty( $status ) ? $status : ( 'attachment' === sanitize_key( (string) $post_type ) ? 'inherit' : 'any' ),
				'posts_per_page'      => $per_page,
				'suppress_filters'    => false,
				'paged'               => $page,
				'orderby'             => 'date',
				'order'               => 'DESC',
				'ignore_sticky_posts' => true,
			);
			if ( '' !== $post_parent_filter ) {
				$args['post_parent'] = $post_parent_filter;
			}
			$is_attachment_browse = 'attachment' === $post_type;
			if ( '' !== $language && 'all' !== $language && $is_attachment_browse && WPML_Compatibility::is_media_active() ) {
				$args['post__in'] = $this->get_post_ids_for_wpml_language( $language, 'attachment' );
				unset( $args['lang'] );
			} elseif ( '' !== $language && 'all' !== $language && $is_attachment_browse ) {
				$args['suppress_filters'] = true;
				unset( $args['lang'] );
			} elseif ( '' !== $language && 'all' !== $language ) {
				$args['lang'] = $language;
			}
			if ( 'all' === $language ) {
				// WPML keeps the current language filter active in REST/admin-ajax
				// contexts even when lang=all is passed. Suppress filters only for
				// the "all languages" view so WP_Query behaves like the WP admin list.
				$args['suppress_filters'] = true;
				unset( $args['lang'] );
				if ( empty( $search ) ) {
					unset( $args['post_parent'] );
				}
			}

			if ( ! empty( $search ) ) {
				$args['s'] = $search;
			}

			$previous_wpml_language = $this->switch_wpml_query_language( $language );
			$query                  = new \WP_Query( $args );
			$posts_list             = array();
			$total                  = $query->found_posts;
			foreach ( $query->posts as $post ) {
				$post_data = array(
					'ID'            => $post->ID,
					'post_title'    => $post->post_title,
					'post_type'     => $post->post_type,
					'post_status'   => $post->post_status,
					'post_date'     => $post->post_date,
					'post_modified' => $post->post_modified,
					'post_parent'   => $post->post_parent,
				);

				// Get children count (same post type only, excluding attachments)
				$children_count = 0;
				if ( empty( $search ) && ! $is_wpml_language_browse ) {
					$children_count = $this->count_children( $post->ID, $post->post_type );
				}
				$post_data['children_count'] = $children_count;

				if ( WPML_Compatibility::is_active() ) {
					$wpml_data = WPML_Compatibility::export_post_data( (int) $post->ID, (string) $post->post_type );
					if ( ! empty( $wpml_data['language_code'] ) ) {
						$post_data['wpml_language'] = sanitize_key( (string) $wpml_data['language_code'] );
						$post_data['wpml']          = $wpml_data;
					}
				}

				$posts_list[] = $post_data;
			}

			// Get status counts for filters
			$status_counts = $this->get_status_counts( $post_type, $language );

			if ( '' !== $previous_wpml_language ) {
				$this->switch_wpml_query_language( $previous_wpml_language );
			}

			return new \WP_REST_Response(
				array(
					'success'       => true,
					'posts'         => $posts_list,
					'total'         => $total,
					'pages'         => ceil( $total / $per_page ),
					'current_page'  => $page,
					'per_page'      => $per_page,
					'status_counts' => $status_counts,
				),
				200
			);
	}

	/**
	 * Get public post types that support comments.
	 *
	 * @return string[]
	 */
	private function get_commentable_sync_post_types() {
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'names'
		);

		$post_types = array_values(
			array_filter(
				(array) $post_types,
				static function ( $post_type ) {
					return 'attachment' !== $post_type && post_type_supports( $post_type, 'comments' );
				}
			)
		);

		return ! empty( $post_types ) ? $post_types : array( 'post', 'page' );
	}

	/**
	 * Return commentable post types for comments vs WooCommerce reviews.
	 *
	 * @param string $comment_type Comment subtype.
	 * @param string $requested_post_type Requested post type.
	 * @return string|array
	 */
	private function get_commentable_sync_post_types_for_context( $comment_type, $requested_post_type = 'any' ) {
		if ( 'review' === sanitize_key( (string) $comment_type ) ) {
			return post_type_exists( 'product' ) ? 'product' : '__rsl_ie_no_post_type';
		}

		$requested_post_type = sanitize_key( (string) $requested_post_type );
		if ( 'product' === $requested_post_type ) {
			return '__rsl_ie_no_post_type';
		}
		if ( '' !== $requested_post_type && 'any' !== $requested_post_type ) {
			return $requested_post_type;
		}

		return array_values( array_diff( $this->get_commentable_sync_post_types(), array( 'product' ) ) );
	}

	/**
	 * Get product IDs for separating normal comments from WooCommerce reviews.
	 *
	 * @param string $language WPML language code.
	 * @return int[]
	 */
	private function get_product_post_ids_for_comment_filter( $language = '' ) {
		if ( ! post_type_exists( 'product' ) ) {
			return array();
		}

		$args = array(
			'post_type'              => 'product',
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'suppress_filters'       => false,
		);

		$language = sanitize_key( (string) $language );
		if ( '' !== $language && 'all' !== $language && WPML_Compatibility::is_active() ) {
			$args['lang'] = $language;
		} elseif ( 'all' === $language ) {
			$args['suppress_filters'] = true;
		}

		return array_values( array_map( 'absint', get_posts( $args ) ) );
	}

	/**
	 * Check whether a comment belongs to the requested sync entity.
	 *
	 * @param \WP_Comment $comment      Comment object.
	 * @param string      $comment_type Requested comment subtype.
	 * @param string      $language     WPML language code.
	 * @return bool
	 */
	private function is_comment_in_sync_context( $comment, $comment_type = '', $language = '' ) {
		$post      = get_post( (int) $comment->comment_post_ID );
		$post_type = $post ? (string) $post->post_type : '';

		if ( 'review' === sanitize_key( (string) $comment_type ) ) {
			$matches_context = 'review' === (string) $comment->comment_type && 'product' === $post_type;
		} else {
			$matches_context = 'review' !== (string) $comment->comment_type && 'product' !== $post_type;
		}

		if ( ! $matches_context ) {
			return false;
		}

		$language = sanitize_key( (string) $language );
		if ( '' === $language || 'all' === $language || ! WPML_Compatibility::is_active() || ! $post ) {
			return true;
		}

		return $language === WPML_Compatibility::get_post_language_code( (int) $post->ID, $post_type );
	}

	/**
	 * List taxonomy terms for remote Browse dialogs.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function list_terms( $request ) {
		$taxonomy = sanitize_key( (string) $request->get_param( 'taxonomy' ) );
		$search   = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$page     = absint( $request->get_param( 'page' ) ?: 1 );
		$per_page = absint( $request->get_param( 'per_page' ) ?: 20 );
		$language = sanitize_key( (string) ( $request->get_param( 'language' ) ?: '' ) );
		if ( ! WPML_Compatibility::is_active() ) {
			$language = '';
		}

		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid taxonomy.', 'import-export-by-rockstarlab' ),
				),
				400
			);
		}

		$per_page = min( max( $per_page, 1 ), 100 );
		$offset   = ( max( $page, 1 ) - 1 ) * $per_page;
		$args     = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'number'     => $per_page,
			'offset'     => $offset,
			'orderby'    => 'name',
			'order'      => 'ASC',
		);

		if ( 'all' === $language ) {
			$args['suppress_filter']  = true;
			$args['suppress_filters'] = true;
		} elseif ( '' !== $language ) {
			$args['lang'] = $language;
		}

		if ( '' !== $search ) {
			$args['search'] = $search;
		}

		$previous_wpml_language = $this->switch_wpml_query_language( $language );
		$terms                  = get_terms( $args );
		$count_args             = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => false,
			'search'     => $search,
		);
		if ( 'all' === $language ) {
			$count_args['suppress_filter']  = true;
			$count_args['suppress_filters'] = true;
		} elseif ( '' !== $language ) {
			$count_args['lang'] = $language;
		}
		$total = wp_count_terms( $count_args );

		if ( '' !== $previous_wpml_language ) {
			$this->switch_wpml_query_language( $previous_wpml_language );
		}

		if ( is_wp_error( $terms ) || is_wp_error( $total ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Failed to load terms.', 'import-export-by-rockstarlab' ),
				),
				500
			);
		}

		$list = array();
		foreach ( $terms as $term ) {
			$list[] = $this->prepare_term_for_sync( $term, $taxonomy, false );
		}

		return new \WP_REST_Response(
			array(
				'success'      => true,
				'terms'        => $list,
				'total'        => (int) $total,
				'pages'        => max( 1, (int) ceil( (int) $total / $per_page ) ),
				'current_page' => $page,
				'per_page'     => $per_page,
			),
			200
		);
	}

	/**
	 * Send selected taxonomy terms to a connected site.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function send_terms( $request ) {
		$taxonomy = sanitize_key( (string) $request->get_param( 'taxonomy' ) );
		$term_ids = $request->get_param( 'term_ids' );

		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid taxonomy.', 'import-export-by-rockstarlab' ),
				),
				400
			);
		}

		$term_ids = is_array( $term_ids ) ? array_map( 'absint', $term_ids ) : array();
		$term_ids = array_values( array_filter( array_unique( $term_ids ) ) );
		if ( empty( $term_ids ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'No terms selected.', 'import-export-by-rockstarlab' ),
				),
				400
			);
		}

		$terms      = array();
		$all_images = array();
		foreach ( $term_ids as $term_id ) {
			$term = get_term( $term_id, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				$term_info = $this->prepare_term_for_sync( $term, $taxonomy, true );
				$terms[]   = $term_info;

				$term_image_ids = array();
				if ( ! empty( $term_info['description'] ) && is_string( $term_info['description'] ) ) {
					$term_image_ids = array_merge( $term_image_ids, $this->extract_term_acf_images( array( 'description' => $term_info['description'] ) ) );
				}
				if ( ! empty( $term_info['acf'] ) && is_array( $term_info['acf'] ) ) {
					$term_image_ids = array_merge( $term_image_ids, $this->extract_term_acf_images( $term_info['acf'] ) );
				}

				foreach ( array_values( array_unique( array_filter( array_map( 'absint', $term_image_ids ) ) ) ) as $image_id ) {
					if ( isset( $all_images[ $image_id ] ) ) {
						continue;
					}

					$image_data = \RockStarLab\ImportExport\Helper\Content_Sync_Media::prepare_image_data( $image_id, 'term_acf' );
					if ( ! $image_data ) {
						$image_data = array(
							'attachment_id' => $image_id,
							'url'           => wp_get_attachment_url( $image_id ),
							'type'          => 'term_acf',
						);
					}
					$image_data['term_id']   = (int) $term->term_id;
					$image_data['taxonomy']  = $taxonomy;
					$all_images[ $image_id ] = $image_data;
				}
			}
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'terms'   => $terms,
				'images'  => array_values( $all_images ),
			),
			200
		);
	}

	/**
	 * Receive taxonomy terms from a connected site.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function receive_terms( $request ) {
		$taxonomy      = sanitize_key( (string) $request->get_param( 'taxonomy' ) );
		$terms         = $request->get_param( 'terms' );
		$image_map     = $request->get_param( 'image_map' );
		$image_sources = $request->get_param( 'image_sources' );

		if ( ! is_array( $image_map ) ) {
			$image_map = array();
		}
		if ( ! is_array( $image_sources ) ) {
			$image_sources = array();
		}

		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid taxonomy.', 'import-export-by-rockstarlab' ),
				),
				400
			);
		}

		if ( empty( $terms ) || ! is_array( $terms ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'No terms data provided.', 'import-export-by-rockstarlab' ),
				),
				400
			);
		}

		$created               = 0;
		$updated               = 0;
		$errors                = array();
		$source_to_local_terms = array();

		foreach ( $terms as $term_info ) {
			if ( ! is_array( $term_info ) ) {
				continue;
			}

			$existing = ! empty( $term_info['slug'] ) ? get_term_by( 'slug', sanitize_title( (string) $term_info['slug'] ), $taxonomy ) : false;
			$term_id  = $this->resolve_synced_term( $taxonomy, $term_info );

			if ( $term_id <= 0 ) {
				$errors[] = isset( $term_info['name'] ) ? sanitize_text_field( (string) $term_info['name'] ) : __( 'Unknown term', 'import-export-by-rockstarlab' );
				continue;
			}
			if ( ! empty( $term_info['term_id'] ) ) {
				$source_to_local_terms[ (int) $term_info['term_id'] ] = (int) $term_id;
			}

			$args = array();
			if ( isset( $term_info['description'] ) ) {
				$description = (string) $term_info['description'];
				if ( ! empty( $image_map ) ) {
					$description = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::fix_local_image_urls_in_content(
						$description,
						$image_map,
						$image_sources
					);
				}
				$args['description'] = wp_kses_post( $description );
			}
			if ( ! empty( $args ) ) {
				wp_update_term( $term_id, $taxonomy, $args );
			}

			if ( ! empty( $term_info['meta'] ) && is_array( $term_info['meta'] ) ) {
				foreach ( $term_info['meta'] as $meta_key => $meta_value ) {
					update_term_meta( $term_id, sanitize_key( (string) $meta_key ), $meta_value );
				}
			}

			if ( ! empty( $term_info['acf'] ) && is_array( $term_info['acf'] ) && function_exists( 'update_field' ) ) {
				if ( ! empty( $image_map ) ) {
					$term_info['acf'] = $this->replace_term_acf_value_media_references(
						$term_info['acf'],
						'',
						'',
						$image_map,
						$image_sources
					);
				}

				foreach ( $term_info['acf'] as $field_key => $field_value ) {
					ACF_Fields::import_value( 'term', $term_id, sanitize_text_field( (string) $field_key ), $field_value, $taxonomy );
				}
			}

			if ( $existing ) {
				++$updated;
			} else {
				++$created;
			}
		}

		$this->apply_synced_terms_wpml_data( $taxonomy, $terms, $source_to_local_terms );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'created' => $created,
				'updated' => $updated,
				'failed'  => count( $errors ),
				'errors'  => $errors,
				'message' => sprintf(
					/* translators: 1: created terms, 2: updated terms. */
					__( 'Terms synced. Created: %1$d, Updated: %2$d', 'import-export-by-rockstarlab' ),
					$created,
					$updated
				),
			),
			200
		);
	}

	/**
	 * Prepare a taxonomy term payload for sync.
	 *
	 * @param \WP_Term $term         Term object.
	 * @param string   $taxonomy     Taxonomy name.
	 * @param bool     $include_meta Include meta/ACF data.
	 * @return array
	 */
	private function prepare_term_for_sync( $term, $taxonomy, $include_meta ) {
		$data = array(
			'term_id'        => (int) $term->term_id,
			'name'           => (string) $term->name,
			'slug'           => (string) $term->slug,
			'taxonomy'       => $taxonomy,
			'description'    => (string) $term->description,
			'count'          => (int) $term->count,
			'parent_term_id' => (int) $term->parent,
			'parent_slug'    => $this->get_term_slug_by_id( (int) $term->parent, $taxonomy ),
			'parent_path'    => $this->get_term_parent_path( (int) $term->parent, $taxonomy ),
		);

		if ( $include_meta ) {
			$meta         = get_term_meta( (int) $term->term_id );
			$data['meta'] = array();
			foreach ( $meta as $key => $values ) {
				$data['meta'][ $key ] = isset( $values[0] ) ? maybe_unserialize( $values[0] ) : '';
			}

			if ( function_exists( 'get_field_objects' ) ) {
				$acf_fields = $this->get_term_acf_field_objects( (int) $term->term_id, $taxonomy );
				if ( $acf_fields ) {
					$data['acf'] = array();
					foreach ( $acf_fields as $field_key => $field ) {
						$field_name                 = ! empty( $field['name'] ) ? (string) $field['name'] : (string) $field_key;
						$data['acf'][ $field_name ] = ACF_Fields::export_value( 'term', (int) $term->term_id, $field_name, $taxonomy );
					}
				}
			}
		}

		$this->append_wpml_term_sync_data( $data, (int) $term->term_id, $taxonomy );

		return $data;
	}

	/**
	 * Replace media references inside one ACF value.
	 *
	 * @param mixed  $value         ACF value.
	 * @param string $source_domain Source site URL.
	 * @param string $target_domain Target site URL.
	 * @param array  $image_map     Source attachment ID => target attachment ID.
	 * @param array  $image_sources Source image metadata.
	 * @return mixed
	 */
	private function replace_term_acf_value_media_references( $value, $source_domain, $target_domain, array $image_map, array $image_sources ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $key => $child ) {
				if ( is_numeric( $child ) && isset( $image_map[ (int) $child ] ) && $this->looks_like_term_acf_media_key( $key ) ) {
					$out[ $key ] = (int) $image_map[ (int) $child ];
					continue;
				}

				$out[ $key ] = $this->replace_term_acf_value_media_references(
					$child,
					$source_domain,
					$target_domain,
					$image_map,
					$image_sources
				);
			}

			if ( isset( $out['id'] ) && is_numeric( $out['id'] ) && isset( $image_map[ (int) $out['id'] ] ) ) {
				$mapped_id = (int) $image_map[ (int) $out['id'] ];
				$out['id'] = $mapped_id;
				$url       = wp_get_attachment_url( $mapped_id );
				if ( $url && isset( $out['url'] ) ) {
					$out['url'] = $url;
				}
			}

			return $out;
		}

		if ( is_string( $value ) && '' !== $value ) {
			$decoded = json_decode( $value, true );
			if ( is_array( $decoded ) ) {
				return wp_json_encode(
					$this->replace_term_acf_value_media_references(
						$decoded,
						$source_domain,
						$target_domain,
						$image_map,
						$image_sources
					)
				);
			}

			$mapped_url = $this->get_mapped_acf_media_url( $value, $image_map, $image_sources );
			if ( '' !== $mapped_url ) {
				return $mapped_url;
			}

			return \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::fix_local_image_urls_in_content(
				$value,
				$image_map,
				$image_sources
			);
		}

		return $value;
	}

	private function looks_like_term_acf_media_key( $key ) {
		if ( is_int( $key ) ) {
			return true;
		}

		return in_array(
			(string) $key,
			array( 'id', 'ID', 'attachment_id', 'image_id', 'media_id', 'file_id' ),
			true
		);
	}

	/**
	 * Return the target attachment URL for a source media URL when it is mapped.
	 *
	 * @param string $url           Source URL.
	 * @param array  $image_map     Source attachment ID => target attachment ID.
	 * @param array  $image_sources Source image metadata.
	 * @return string
	 */
	private function get_mapped_acf_media_url( $url, array $image_map, array $image_sources ) {
		$url = trim( html_entity_decode( (string) $url, ENT_QUOTES, get_bloginfo( 'charset' ) ) );
		if ( '' === $url || empty( $image_map ) || empty( $image_sources ) ) {
			return '';
		}

		foreach ( $image_sources as $source_id => $source ) {
			if ( ! is_array( $source ) ) {
				continue;
			}

			$attachment_id = isset( $source['attachment_id'] ) ? (int) $source['attachment_id'] : (int) $source_id;
			if ( $attachment_id <= 0 || empty( $image_map[ $attachment_id ] ) ) {
				continue;
			}

			$source_urls = array_filter(
				array(
					isset( $source['url'] ) ? (string) $source['url'] : '',
					isset( $source['full_url'] ) ? (string) $source['full_url'] : '',
				)
			);

			foreach ( $source_urls as $source_url ) {
				if ( $url !== html_entity_decode( $source_url, ENT_QUOTES, get_bloginfo( 'charset' ) ) ) {
					continue;
				}

				$target_url = wp_get_attachment_url( (int) $image_map[ $attachment_id ] );
				return $target_url ? (string) $target_url : '';
			}
		}

		return '';
	}

	/**
	 * List comments for remote Browse dialogs.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function list_comments( $request ) {
		$comment_type = sanitize_key( (string) $request->get_param( 'comment_type' ) );
		$search       = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$post_id      = absint( $request->get_param( 'post_id' ) );
		$page         = absint( $request->get_param( 'page' ) ?: 1 );
		$per_page     = min( max( absint( $request->get_param( 'per_page' ) ?: 20 ), 1 ), 100 );
		$language     = sanitize_key( (string) ( $request->get_param( 'language' ) ?: '' ) );
		if ( ! WPML_Compatibility::is_active() ) {
			$language = '';
		}
		$offset = ( max( $page, 1 ) - 1 ) * $per_page;

		$args = array(
			'number'  => $per_page,
			'offset'  => $offset,
			'orderby' => 'comment_date_gmt',
			'order'   => 'DESC',
			'status'  => 'all',
		);

		if ( 'review' === $comment_type ) {
			$args['type'] = 'review';
		} elseif ( '' !== $comment_type ) {
			$args['type'] = $comment_type;
		} else {
			$args['type__not_in'] = array( 'review' );
		}

		if ( '' !== $search ) {
			$args['search'] = $search;
		}

		if ( $post_id > 0 ) {
			$args['post_id'] = $post_id;
		}

		$previous_wpml_language = $this->switch_wpml_query_language( $language );
		if ( '' !== $language && 'all' !== $language && $post_id <= 0 ) {
			$args['post__in'] = $this->get_post_ids_for_wpml_language( $language );
		}
		if ( $post_id <= 0 ) {
			$product_ids = $this->get_product_post_ids_for_comment_filter( $language );
			if ( 'review' === $comment_type ) {
				if ( empty( $product_ids ) ) {
					$product_ids = array( 0 );
				}
				$args['post__in'] = isset( $args['post__in'] ) ? array_values( array_intersect( $args['post__in'], $product_ids ) ) : $product_ids;
				if ( empty( $args['post__in'] ) ) {
					$args['post__in'] = array( 0 );
				}
			} elseif ( ! empty( $product_ids ) ) {
				$args['post__not_in'] = $product_ids;
			}
		}

		$query    = new \WP_Comment_Query();
		$comments = $query->query( $args );
		$total    = get_comments(
			array_merge(
				$args,
				array(
					'count'  => true,
					'number' => 0,
					'offset' => 0,
				)
			)
		);

		if ( '' !== $previous_wpml_language ) {
			$this->switch_wpml_query_language( $previous_wpml_language );
		}

		$list = array();
		foreach ( $comments as $comment ) {
			$list[] = $this->prepare_comment_for_sync( $comment, false );
		}

		return new \WP_REST_Response(
			array(
				'success'      => true,
				'comments'     => $list,
				'total'        => (int) $total,
				'pages'        => max( 1, (int) ceil( (int) $total / $per_page ) ),
				'current_page' => $page,
				'per_page'     => $per_page,
			),
			200
		);
	}

	/**
	 * Send selected comments to a connected site.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function send_comments( $request ) {
		$comment_ids  = $request->get_param( 'comment_ids' );
		$comment_ids  = is_array( $comment_ids ) ? array_values( array_filter( array_unique( array_map( 'absint', $comment_ids ) ) ) ) : array();
		$comment_type = sanitize_key( (string) ( $request->get_param( 'comment_type' ) ?: '' ) );
		$language     = sanitize_key( (string) ( $request->get_param( 'language' ) ?: '' ) );
		if ( ! WPML_Compatibility::is_active() ) {
			$language = '';
		}

		if ( empty( $comment_ids ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'No comments selected.', 'import-export-by-rockstarlab' ),
				),
				400
			);
		}

		$comments = array();
		$images   = array();
		foreach ( $comment_ids as $comment_id ) {
			$comment = get_comment( $comment_id );
			if ( $comment ) {
				if ( ! $this->is_comment_in_sync_context( $comment, $comment_type, $language ) ) {
					continue;
				}

				$comment_data = $this->prepare_comment_for_sync( $comment, true );
				$comments[]   = $comment_data;
				$this->collect_comment_acf_images_for_sync( $comment_data, $images );
			}
		}

		return new \WP_REST_Response(
			array(
				'success'  => true,
				'comments' => $comments,
				'images'   => array_values( $images ),
			),
			200
		);
	}

	/**
	 * Receive comments from a connected site.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function receive_comments( $request ) {
		$comments       = $request->get_param( 'comments' );
		$target_post_id = absint( $request->get_param( 'target_post_id' ) );
		$post_mapping   = $request->get_param( 'post_mapping' );
		$image_map      = $request->get_param( 'image_map' );
		$image_sources  = $request->get_param( 'image_sources' );

		if ( ! is_array( $image_map ) ) {
			$image_map = array();
		}
		if ( ! is_array( $image_sources ) ) {
			$image_sources = array();
		}

		if ( is_string( $post_mapping ) ) {
			$post_mapping = json_decode( $post_mapping, true );
		}
		if ( ! is_array( $post_mapping ) ) {
			$post_mapping = array();
		}

		if ( empty( $comments ) || ! is_array( $comments ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'No comments data provided.', 'import-export-by-rockstarlab' ),
				),
				400
			);
		}

		$comments = $this->replace_comment_acf_media_references( $comments, '', '', $image_map, $image_sources );
		$result   = $this->import_synced_standalone_comments( $comments, $post_mapping, $target_post_id );

		$message = sprintf(
			/* translators: 1: created comments, 2: updated comments, 3: failed comments. */
			__( 'Comments synced. Created: %1$d, Updated: %2$d, Failed: %3$d', 'import-export-by-rockstarlab' ),
			$result['created'],
			$result['updated'],
			$result['failed']
		);

		if ( ! empty( $result['errors'] ) ) {
			$message .= ' ' . reset( $result['errors'] );
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'created' => $result['created'],
				'updated' => $result['updated'],
				'failed'  => $result['failed'],
				'errors'  => $result['errors'],
				'message' => $message,
			),
			200
		);
	}

	/**
	 * Prepare a comment payload for standalone sync.
	 *
	 * @param \WP_Comment $comment      Comment object.
	 * @param bool        $include_meta Include comment meta.
	 * @return array
	 */
	private function prepare_comment_for_sync( $comment, $include_meta ) {
		$post = get_post( (int) $comment->comment_post_ID );
		$data = array(
			'comment_ID'           => (int) $comment->comment_ID,
			'comment_post_ID'      => (int) $comment->comment_post_ID,
			'comment_author'       => (string) $comment->comment_author,
			'comment_author_email' => (string) $comment->comment_author_email,
			'comment_author_url'   => (string) $comment->comment_author_url,
			'comment_author_IP'    => (string) $comment->comment_author_IP,
			'comment_date'         => (string) $comment->comment_date,
			'comment_date_gmt'     => (string) $comment->comment_date_gmt,
			'comment_content'      => (string) $comment->comment_content,
			'comment_karma'        => (int) $comment->comment_karma,
			'comment_approved'     => (string) $comment->comment_approved,
			'comment_agent'        => (string) $comment->comment_agent,
			'comment_type'         => (string) $comment->comment_type,
			'comment_parent'       => (int) $comment->comment_parent,
			'user_id'              => (int) $comment->user_id,
			'post'                 => $post ? array(
				'ID'        => (int) $post->ID,
				'post_type' => (string) $post->post_type,
				'post_name' => (string) $post->post_name,
				'title'     => (string) get_the_title( $post ),
			) : array(),
		);

		if ( $include_meta ) {
			$data['meta'] = array();
			$meta         = get_comment_meta( (int) $comment->comment_ID );
			foreach ( $meta as $key => $values ) {
				$data['meta'][ $key ] = isset( $values[0] ) ? maybe_unserialize( $values[0] ) : '';
			}

			$data['acf'] = $this->export_comment_acf_for_sync( (int) $comment->comment_ID );
		}

		return $data;
	}

	/**
	 * Import standalone synced comments.
	 *
	 * @param array $comments Comment payloads.
	 * @return array Counts.
	 */
	private function import_synced_standalone_comments( $comments, $post_mapping = array(), $target_post_id = 0 ) {
		$result = array(
			'created' => 0,
			'updated' => 0,
			'failed'  => 0,
			'errors'  => array(),
		);

		foreach ( $comments as $comment_data ) {
			if ( ! is_array( $comment_data ) ) {
				++$result['failed'];
				$result['errors'][] = __( 'A remote comment had an invalid payload and was skipped.', 'import-export-by-rockstarlab' );
				continue;
			}

			$source_comment_id = isset( $comment_data['comment_ID'] ) ? absint( $comment_data['comment_ID'] ) : 0;
			$post_id           = $target_post_id > 0 ? $target_post_id : $this->resolve_synced_comment_post_id( $comment_data, $post_mapping );
			if ( $post_id <= 0 ) {
				++$result['failed'];
				$result['errors'][] = sprintf(
					/* translators: %d: source comment ID. */
					__( 'Comment #%d was skipped because no destination post was selected or matched.', 'import-export-by-rockstarlab' ),
					$source_comment_id
				);
				continue;
			}

			if ( ! get_post( $post_id ) ) {
				++$result['failed'];
				$result['errors'][] = sprintf(
					/* translators: 1: source comment ID, 2: destination post ID. */
					__( 'Comment #%1$d was skipped because destination post #%2$d does not exist.', 'import-export-by-rockstarlab' ),
					$source_comment_id,
					$post_id
				);
				continue;
			}

			$existing_id = $this->find_comment_by_original_id( $source_comment_id );

			$args = array(
				'comment_post_ID'      => $post_id,
				'comment_author'       => isset( $comment_data['comment_author'] ) ? sanitize_text_field( (string) $comment_data['comment_author'] ) : '',
				'comment_author_email' => isset( $comment_data['comment_author_email'] ) ? sanitize_email( (string) $comment_data['comment_author_email'] ) : '',
				'comment_author_url'   => isset( $comment_data['comment_author_url'] ) ? esc_url_raw( (string) $comment_data['comment_author_url'] ) : '',
				'comment_author_IP'    => isset( $comment_data['comment_author_IP'] ) ? sanitize_text_field( (string) $comment_data['comment_author_IP'] ) : '',
				'comment_date'         => isset( $comment_data['comment_date'] ) ? sanitize_text_field( (string) $comment_data['comment_date'] ) : current_time( 'mysql' ),
				'comment_date_gmt'     => isset( $comment_data['comment_date_gmt'] ) ? sanitize_text_field( (string) $comment_data['comment_date_gmt'] ) : current_time( 'mysql', true ),
				'comment_content'      => isset( $comment_data['comment_content'] ) ? wp_kses_post( (string) $comment_data['comment_content'] ) : '',
				'comment_karma'        => isset( $comment_data['comment_karma'] ) ? (int) $comment_data['comment_karma'] : 0,
				'comment_approved'     => isset( $comment_data['comment_approved'] ) ? sanitize_text_field( (string) $comment_data['comment_approved'] ) : '1',
				'comment_agent'        => isset( $comment_data['comment_agent'] ) ? sanitize_text_field( (string) $comment_data['comment_agent'] ) : '',
				'comment_type'         => isset( $comment_data['comment_type'] ) ? sanitize_key( (string) $comment_data['comment_type'] ) : '',
				'comment_parent'       => 0,
				'user_id'              => 0,
			);

			if ( $existing_id > 0 ) {
				$args['comment_ID'] = $existing_id;
				$comment_id         = wp_update_comment( $args );
				++$result['updated'];
			} else {
				$comment_id = wp_insert_comment( $args );
				if ( $comment_id && $source_comment_id > 0 ) {
					update_comment_meta( (int) $comment_id, '_rsl_ie_original_comment_id', $source_comment_id );
				}
				++$result['created'];
			}

			if ( ! $comment_id ) {
				++$result['failed'];
				$result['errors'][] = sprintf(
					/* translators: %d: source comment ID. */
					__( 'Comment #%d could not be saved.', 'import-export-by-rockstarlab' ),
					$source_comment_id
				);
				continue;
			}

			$this->import_comment_acf_for_sync( (int) $comment_id, $comment_data );

			if ( ! empty( $comment_data['meta'] ) && is_array( $comment_data['meta'] ) ) {
				foreach ( $comment_data['meta'] as $meta_key => $meta_value ) {
					if ( '_rsl_ie_original_comment_id' === $meta_key || $this->is_acf_comment_sync_meta_key( (string) $meta_key, $comment_data ) ) {
						continue;
					}
					update_comment_meta( (int) $comment_id, sanitize_key( (string) $meta_key ), $meta_value );
				}
			}
		}

		return $result;
	}

	/**
	 * Resolve the target post for a synced standalone comment.
	 *
	 * @param array $comment_data Comment payload.
	 * @return int Post ID or 0.
	 */
	private function resolve_synced_comment_post_id( $comment_data, $post_mapping = array() ) {
		$post_info      = isset( $comment_data['post'] ) && is_array( $comment_data['post'] ) ? $comment_data['post'] : array();
		$source_post_id = isset( $post_info['ID'] ) ? absint( $post_info['ID'] ) : absint( $comment_data['comment_post_ID'] ?? 0 );
		$post_type      = isset( $post_info['post_type'] ) ? sanitize_key( (string) $post_info['post_type'] ) : '';
		$post_name      = isset( $post_info['post_name'] ) ? sanitize_title( (string) $post_info['post_name'] ) : '';

		if ( $source_post_id > 0 && isset( $post_mapping[ $source_post_id ] ) ) {
			$mapped_post_id = absint( $post_mapping[ $source_post_id ] );
			if ( $mapped_post_id > 0 && get_post( $mapped_post_id ) ) {
				return $mapped_post_id;
			}
		}

		if ( $source_post_id > 0 ) {
			$matched = $this->find_existing_post_by_original_id( $source_post_id, '' !== $post_type ? $post_type : 'any' );
			if ( $matched > 0 ) {
				return (int) $matched;
			}
		}

		if ( '' !== $post_name && '' !== $post_type ) {
			$post = get_page_by_path( $post_name, OBJECT, $post_type );
			if ( $post ) {
				return (int) $post->ID;
			}
		}

		if ( ! empty( $post_info['title'] ) && '' !== $post_type ) {
			$matched_posts = get_posts(
				array(
					'post_type'              => $post_type,
					'post_status'            => 'any',
					'title'                  => sanitize_text_field( (string) $post_info['title'] ),
					'posts_per_page'         => 1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			if ( ! empty( $matched_posts ) ) {
				return (int) $matched_posts[0];
			}
		}

		return 0;
	}

	/**
	 * Export ACF fields attached to a comment.
	 *
	 * @param int $comment_id Comment ID.
	 * @return array
	 */
	private function export_comment_acf_for_sync( $comment_id ) {
		$acf = array();

		if ( ! class_exists( ACF_Fields::class ) ) {
			return $acf;
		}

		foreach ( ACF_Fields::get_fields_for_content_type( 'comment' ) as $field ) {
			if ( empty( $field['name'] ) ) {
				continue;
			}

			$name         = (string) $field['name'];
			$acf[ $name ] = ACF_Fields::export_value( 'comment', (int) $comment_id, $name );
		}

		return $acf;
	}

	/**
	 * Import synced ACF fields into a comment.
	 *
	 * @param int   $comment_id    Comment ID.
	 * @param array $comment_data Comment sync payload.
	 * @return void
	 */
	private function import_comment_acf_for_sync( $comment_id, $comment_data ) {
		if ( $comment_id <= 0 || empty( $comment_data['acf'] ) || ! is_array( $comment_data['acf'] ) || ! class_exists( ACF_Fields::class ) ) {
			return;
		}

		foreach ( $comment_data['acf'] as $field_name => $value ) {
			$field_name = sanitize_text_field( (string) $field_name );
			if ( '' === $field_name ) {
				continue;
			}

			ACF_Fields::import_value( 'comment', (int) $comment_id, $field_name, $value );
		}
	}

	/**
	 * Collect media referenced by a comment's ACF payload.
	 *
	 * @param array $comment_data Prepared comment payload.
	 * @param array $all_images   Accumulator keyed by attachment ID.
	 * @return void
	 */
	private function collect_comment_acf_images_for_sync( array $comment_data, array &$all_images ) {
		$image_ids = array();
		if ( ! empty( $comment_data['comment_content'] ) && is_string( $comment_data['comment_content'] ) ) {
			$image_ids = array_merge( $image_ids, $this->extract_term_acf_images( array( 'comment_content' => $comment_data['comment_content'] ) ) );
		}

		if ( empty( $comment_data['acf'] ) || ! is_array( $comment_data['acf'] ) ) {
			$comment_data['acf'] = array();
		}

		$image_ids = array_merge( $image_ids, $this->extract_term_acf_images( $comment_data['acf'] ) );
		$image_ids = array_values( array_unique( array_filter( array_map( 'absint', $image_ids ) ) ) );
		foreach ( $image_ids as $image_id ) {
			if ( isset( $all_images[ $image_id ] ) ) {
				continue;
			}

			$image_data = \RockStarLab\ImportExport\Helper\Content_Sync_Media::prepare_image_data( $image_id, 'comment_acf' );
			if ( ! $image_data ) {
				$image_data = array(
					'attachment_id' => $image_id,
					'url'           => wp_get_attachment_url( $image_id ),
					'type'          => 'comment_acf',
				);
			}

			$image_data['comment_id'] = isset( $comment_data['comment_ID'] ) ? (int) $comment_data['comment_ID'] : 0;
			$all_images[ $image_id ]  = $image_data;
		}
	}

	/**
	 * Replace media references inside comment ACF payloads.
	 *
	 * @param array  $comments      Comment payloads.
	 * @param string $source_domain Source site URL.
	 * @param string $target_domain Target site URL.
	 * @param array  $image_map     Source attachment ID => target attachment ID.
	 * @param array  $image_sources Source image metadata.
	 * @return array
	 */
	private function replace_comment_acf_media_references( array $comments, $source_domain, $target_domain, array $image_map, array $image_sources ) {
		if ( empty( $image_map ) ) {
			return $comments;
		}

		foreach ( $comments as &$comment_data ) {
			if ( isset( $comment_data['comment_content'] ) && is_string( $comment_data['comment_content'] ) ) {
				$comment_data['comment_content'] = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::fix_local_image_urls_in_content(
					$comment_data['comment_content'],
					$image_map,
					$image_sources
				);
			}

			if ( empty( $comment_data['acf'] ) || ! is_array( $comment_data['acf'] ) ) {
				continue;
			}

			$comment_data['acf'] = $this->replace_term_acf_value_media_references(
				$comment_data['acf'],
				$source_domain,
				$target_domain,
				$image_map,
				$image_sources
			);
		}
		unset( $comment_data );

		return $comments;
	}

	/**
	 * Determine whether a raw comment meta key belongs to ACF.
	 *
	 * @param string $meta_key Meta key.
	 * @return bool
	 */
	private function is_acf_comment_sync_meta_key( $meta_key, $comment_data ) {
		if ( '' === $meta_key ) {
			return false;
		}

		if ( empty( $comment_data['acf'] ) || ! is_array( $comment_data['acf'] ) ) {
			return false;
		}

		$acf_names = array_map( 'strval', array_keys( $comment_data['acf'] ) );
		foreach ( $acf_names as $acf_name ) {
			if ( '' === $acf_name ) {
				continue;
			}
			if (
				$meta_key === $acf_name
				|| $meta_key === '_' . $acf_name
				|| 0 === strpos( $meta_key, $acf_name . '_' )
				|| 0 === strpos( $meta_key, '_' . $acf_name . '_' )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Find a local comment by original source comment ID.
	 *
	 * @param int $source_comment_id Source comment ID.
	 * @return int Comment ID or 0.
	 */
	private function find_comment_by_original_id( $source_comment_id ) {
		$source_comment_id = absint( $source_comment_id );
		if ( $source_comment_id <= 0 ) {
			return 0;
		}

		$comments = get_comments(
			array(
				'number'     => 1,
				'fields'     => 'ids',
				'meta_key'   => '_rsl_ie_original_comment_id', // phpcs:ignore WordPress.DB.SlowDBQuery -- Sync mapping lookup.
				'meta_value' => $source_comment_id, // phpcs:ignore WordPress.DB.SlowDBQuery -- Sync mapping lookup.
			)
		);

		return ! empty( $comments ) ? (int) $comments[0] : 0;
	}

	/**
	 * Count direct children of a post
	 *
	 * @param int $post_id Post ID.
	 * @return int Children count.
	 */
	private function count_children( $post_id, $post_type = '' ) {
		$children = get_posts(
			array(
				'post_parent'         => $post_id,
				'post_type'           => ! empty( $post_type ) ? $post_type : 'any',
				'post_status'         => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page'      => -1,
				'fields'              => 'ids',
				'ignore_sticky_posts' => true,
				'post__not_in'        => get_option( 'sticky_posts', array() ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- post__not_in required for correct filtering.
			)
		);
		return count( $children );
	}

	/**
	 * Get status counts for filters
	 *
	 * @param string $post_type Post type.
	 * @return array Status counts.
	 */
	private function get_status_counts( $post_type, $language = '' ) {
		if ( ! WPML_Compatibility::is_active() ) {
			$language = '';
		}

		$counts = array(
			'all'     => 0,
			'publish' => 0,
			'draft'   => 0,
			'pending' => 0,
		);

		$statuses               = array( 'publish', 'draft', 'pending', 'private', 'future' );
		$previous_wpml_language = $this->switch_wpml_query_language( $language );

		foreach ( $statuses as $status ) {
			$count_args = array(
				'post_type'           => $post_type ?: 'any',
				'post_status'         => $status,
				'posts_per_page'      => 1,
				'fields'              => 'ids',
				'ignore_sticky_posts' => true,
			);
			if ( 'all' === $language ) {
				$count_args['suppress_filters'] = true;
				unset( $count_args['lang'] );
			} elseif ( '' !== $language ) {
				$count_args['suppress_filters'] = false;
				$count_args['lang']             = sanitize_key( $language );
			}
			$count_query = new \WP_Query( $count_args );

			$count = $count_query->found_posts;

			if ( $status === 'publish' || $status === 'private' || $status === 'future' ) {
				$counts['publish'] += $count;
			} elseif ( isset( $counts[ $status ] ) ) {
				$counts[ $status ] = $count;
			}

			$counts['all'] += $count;
		}

		if ( '' !== $previous_wpml_language ) {
			$this->switch_wpml_query_language( $previous_wpml_language );
		}

		return $counts;
	}

	/**
	 * Switch WPML language for internal sync queries and return previous language.
	 *
	 * @param string $language Language code or all.
	 * @return string Previous language code.
	 */
	private function switch_wpml_query_language( $language ) {
		$language = sanitize_key( (string) $language );
		if ( '' === $language || ! WPML_Compatibility::is_active() || ! function_exists( 'apply_filters' ) ) {
			return '';
		}

		$previous_language = sanitize_key( (string) apply_filters( 'wpml_current_language', '' ) );

		global $sitepress;
		if ( is_object( $sitepress ) && method_exists( $sitepress, 'switch_lang' ) ) {
			$sitepress->switch_lang( $language, true );
		} elseif ( function_exists( 'do_action' ) ) {
			do_action( 'wpml_switch_language', $language );
		}

		return $previous_language;
	}

	/**
	 * Get object IDs that belong to a WPML language for comment filtering.
	 *
	 * @param string $language Language code.
	 * @return int[]
	 */
	private function get_post_ids_for_wpml_language( $language, $post_type = 'any' ) {
		$language = sanitize_key( (string) $language );
		if ( '' === $language || 'all' === $language || ! WPML_Compatibility::is_active() ) {
			return array();
		}
		$post_type = sanitize_key( (string) $post_type );
		if ( '' === $post_type ) {
			$post_type = 'any';
		}

		if ( 'attachment' === $post_type ) {
			if ( ! WPML_Compatibility::is_media_active() ) {
				return array();
			}

			$attachment_ids = get_posts(
				array(
					'post_type'              => 'attachment',
					'post_status'            => 'inherit',
					'posts_per_page'         => -1,
					'fields'                 => 'ids',
					'suppress_filters'       => true,
					'ignore_sticky_posts'    => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);

			$attachment_ids = array_values(
				array_filter(
					array_map( 'absint', (array) $attachment_ids ),
					static function ( $attachment_id ) use ( $language ) {
						return $language === WPML_Compatibility::get_post_language_code( $attachment_id, 'attachment' );
					}
				)
			);

			return ! empty( $attachment_ids ) ? $attachment_ids : array( 0 );
		}

		$post_ids = get_posts(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'any',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'suppress_filters'       => false,
				'lang'                   => $language,
				'ignore_sticky_posts'    => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$post_ids = array_values( array_filter( array_map( 'absint', (array) $post_ids ) ) );

		return ! empty( $post_ids ) ? $post_ids : array( 0 );
	}

	/**
	 * Resolve or create a synced taxonomy term, preserving hierarchy when present.
	 *
	 * @param string $taxonomy  Taxonomy name.
	 * @param array  $term_info Synced term payload.
	 * @return int Local term ID, or 0 on failure.
	 */
	private function resolve_synced_term( $taxonomy, $term_info ) {
		if ( ! taxonomy_exists( $taxonomy ) || empty( $term_info['name'] ) || empty( $term_info['slug'] ) ) {
			return 0;
		}

		$parent_id = 0;
		if ( is_taxonomy_hierarchical( $taxonomy ) ) {
			if ( ! empty( $term_info['parent_path'] ) ) {
				$parent_id = $this->resolve_synced_term_parent_path( $taxonomy, (string) $term_info['parent_path'] );
			} elseif ( ! empty( $term_info['parent_slug'] ) ) {
				$parent_id = $this->resolve_synced_term_parent_path( $taxonomy, (string) $term_info['parent_slug'] );
			}
		}

		$args = array(
			'name' => sanitize_text_field( (string) $term_info['name'] ),
		);
		if ( is_taxonomy_hierarchical( $taxonomy ) ) {
			$args['parent'] = $parent_id;
		}

		$existing_term = get_term_by( 'slug', sanitize_title( (string) $term_info['slug'] ), $taxonomy );
		if ( $existing_term ) {
			wp_update_term( (int) $existing_term->term_id, $taxonomy, $args );
			$this->apply_synced_term_wpml_data( (int) $existing_term->term_id, $taxonomy, (array) $term_info, [] );
			return (int) $existing_term->term_id;
		}

		$args['slug'] = sanitize_title( (string) $term_info['slug'] );
		$new_term     = wp_insert_term( sanitize_text_field( (string) $term_info['name'] ), $taxonomy, $args );
		if ( is_wp_error( $new_term ) || empty( $new_term['term_id'] ) ) {
			return 0;
		}

		$term_id = (int) $new_term['term_id'];
		$this->apply_synced_term_wpml_data( $term_id, $taxonomy, (array) $term_info, [] );

		return $term_id;
	}

	/**
	 * Resolve or create parent terms from a slash-separated slug path.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param string $path     Parent slug path.
	 * @return int Final parent term ID.
	 */
	private function resolve_synced_term_parent_path( $taxonomy, $path ) {
		$slugs     = array_filter( array_map( 'sanitize_title', explode( '/', (string) $path ) ) );
		$parent_id = 0;

		foreach ( $slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				if ( (int) $term->parent !== $parent_id ) {
					wp_update_term( (int) $term->term_id, $taxonomy, array( 'parent' => $parent_id ) );
				}
				$parent_id = (int) $term->term_id;
				continue;
			}

			$new_term = wp_insert_term(
				ucwords( str_replace( array( '-', '_' ), ' ', $slug ) ),
				$taxonomy,
				array(
					'slug'   => $slug,
					'parent' => $parent_id,
				)
			);
			if ( is_wp_error( $new_term ) || empty( $new_term['term_id'] ) ) {
				return $parent_id;
			}
			$parent_id = (int) $new_term['term_id'];
		}

		return $parent_id;
	}

	/**
	 * Get term slug by ID.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return string Term slug.
	 */
	private function get_term_slug_by_id( $term_id, $taxonomy ) {
		if ( $term_id <= 0 ) {
			return '';
		}
		$term = get_term( (int) $term_id, $taxonomy );
		return ( $term && ! is_wp_error( $term ) ) ? (string) $term->slug : '';
	}

	/**
	 * Get a slash-separated parent slug path for a term parent.
	 *
	 * @param int    $parent_id Parent term ID.
	 * @param string $taxonomy  Taxonomy name.
	 * @return string Parent path.
	 */
	private function get_term_parent_path( $parent_id, $taxonomy ) {
		if ( $parent_id <= 0 || ! taxonomy_exists( $taxonomy ) ) {
			return '';
		}

		$ancestors   = array_reverse( get_ancestors( (int) $parent_id, $taxonomy, 'taxonomy' ) );
		$ancestors[] = (int) $parent_id;

		$slugs = array();
		foreach ( $ancestors as $ancestor_id ) {
			$slug = $this->get_term_slug_by_id( (int) $ancestor_id, $taxonomy );
			if ( '' !== $slug ) {
				$slugs[] = $slug;
			}
		}

		return implode( '/', $slugs );
	}

	/**
	 * Ensure a WooCommerce global product attribute taxonomy exists in this request.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return bool Whether the taxonomy is available.
	 */
	private function ensure_woocommerce_attribute_taxonomy( $taxonomy ) {
		if ( ! is_string( $taxonomy ) || 0 !== strpos( $taxonomy, 'pa_' ) ) {
			return taxonomy_exists( $taxonomy );
		}

		if ( taxonomy_exists( $taxonomy ) ) {
			return true;
		}

		if ( ! function_exists( 'wc_create_attribute' ) || ! function_exists( 'wc_sanitize_taxonomy_name' ) ) {
			return false;
		}

		$attribute_name = wc_sanitize_taxonomy_name( substr( $taxonomy, 3 ) );
		if ( '' === $attribute_name ) {
			return false;
		}

		$attribute_id = function_exists( 'wc_attribute_taxonomy_id_by_name' ) ? wc_attribute_taxonomy_id_by_name( $attribute_name ) : 0;
		if ( ! $attribute_id ) {
			$attribute_id = wc_create_attribute(
				array(
					'name'         => ucwords( str_replace( array( '-', '_' ), ' ', $attribute_name ) ),
					'slug'         => $attribute_name,
					'type'         => 'select',
					'order_by'     => 'menu_order',
					'has_archives' => false,
				)
			);

			if ( is_wp_error( $attribute_id ) ) {
				return false;
			}

			delete_transient( 'wc_attribute_taxonomies' );
		}

		register_taxonomy(
			$taxonomy,
			apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy, array( 'product' ) ),
			apply_filters(
				'woocommerce_taxonomy_args_' . $taxonomy,
				array(
					'labels'       => array(
						'name' => ucwords( str_replace( array( '-', '_' ), ' ', $attribute_name ) ),
					),
					'hierarchical' => true,
					'show_ui'      => false,
					'query_var'    => true,
					'rewrite'      => false,
				)
			)
		);

		return taxonomy_exists( $taxonomy );
	}

	/**
	 * Refresh WooCommerce product caches and lookup tables after direct meta sync.
	 *
	 * @param int $post_id Product post ID.
	 * @return void
	 */
	private function refresh_woocommerce_product_after_sync( $post_id ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		clean_post_cache( $post_id );

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $post_id );
		}

		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return;
		}

		if ( $product->is_type( 'variable' ) && class_exists( 'WC_Product_Variable' ) ) {
			\WC_Product_Variable::sync( $product );
		}

		$product->save();

		if ( function_exists( 'wc_update_product_lookup_tables' ) ) {
			wc_update_product_lookup_tables( $post_id );
		}

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $post_id );
		}
	}

	/**
	 * Get children posts of a parent
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response Response.
	 */
	public function get_children_posts( $request ) {
		$parent_id = absint( $request->get_param( 'parent_id' ) );
		$post_type = sanitize_text_field( $request->get_param( 'post_type' ) ?: '' );
		$language  = sanitize_key( (string) ( $request->get_param( 'language' ) ?: '' ) );
		if ( ! WPML_Compatibility::is_active() ) {
			$language = '';
		}

		// If no post_type provided, derive it from the parent post type.
		if ( empty( $post_type ) ) {
			$parent    = get_post( $parent_id );
			$post_type = $parent ? $parent->post_type : '';
		}

		if ( empty( $parent_id ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Parent ID is required.', 'import-export-by-rockstarlab' ),
				),
				400
			);
		}

		$args = array(
			'post_parent'         => $parent_id,
			'post_type'           => ! empty( $post_type ) ? $post_type : 'any',
			'post_status'         => array( 'publish', 'draft', 'pending', 'private', 'future' ),
			'posts_per_page'      => -1,
			'suppress_filters'    => false,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'ignore_sticky_posts' => true,
			'post__not_in'        => get_option( 'sticky_posts', array() ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- post__not_in required for correct filtering.
		);

		if ( '' !== $language ) {
			$args['lang'] = $language;
		}

		if ( 'all' === $language ) {
			$args['suppress_filters'] = true;
			unset( $args['lang'] );
		}

		$previous_wpml_language = '';
		if ( '' !== $language && function_exists( 'apply_filters' ) && function_exists( 'do_action' ) ) {
			$previous_wpml_language = sanitize_key( (string) apply_filters( 'wpml_current_language', '' ) );
			do_action( 'wpml_switch_language', $language );
		}

		$children = get_posts( $args );

		if ( '' !== $previous_wpml_language && function_exists( 'do_action' ) ) {
			do_action( 'wpml_switch_language', $previous_wpml_language );
		}

		$children_list = array();

		foreach ( $children as $child ) {
			$child_data = array(
				'ID'            => $child->ID,
				'post_title'    => $child->post_title,
				'post_type'     => $child->post_type,
				'post_status'   => $child->post_status,
				'post_date'     => $child->post_date,
				'post_modified' => $child->post_modified,
				'post_parent'   => $child->post_parent,
			);

			// Check if this child has children (same post type only)
			$child_data['children_count'] = $this->count_children( $child->ID, $child->post_type );

			$children_list[] = $child_data;
		}

		return new \WP_REST_Response(
			array(
				'success'  => true,
				'children' => $children_list,
			),
			200
		);
	}

	/**
	 * Append WPML language data to a post sync payload.
	 *
	 * @param array  $post_data Post sync payload, passed by reference.
	 * @param int    $post_id   Source post ID.
	 * @param string $post_type Source post type.
	 * @return void
	 */
	private function append_wpml_post_sync_data( array &$post_data, $post_id, $post_type ) {
		if ( ! WPML_Compatibility::is_active() ) {
			return;
		}

		$wpml_data = WPML_Compatibility::export_post_data( (int) $post_id, (string) $post_type );
		if ( ! empty( $wpml_data ) ) {
			$post_data['wpml'] = $wpml_data;
		}
	}

	/**
	 * Append WPML language data to a term sync payload.
	 *
	 * @param array  $term_info Term sync payload, passed by reference.
	 * @param int    $term_id   Source term ID.
	 * @param string $taxonomy  Taxonomy name.
	 * @return void
	 */
	private function append_wpml_term_sync_data( array &$term_info, $term_id, $taxonomy ) {
		if ( ! WPML_Compatibility::is_active() ) {
			return;
		}

		$wpml_data = WPML_Compatibility::export_term_data( (int) $term_id, (string) $taxonomy );
		if ( ! empty( $wpml_data ) ) {
			$term_info['wpml'] = $wpml_data;
		}
	}

	/**
	 * Apply WPML language data received through content sync.
	 *
	 * @param int   $post_id       Target post ID.
	 * @param array $post_data     Incoming post payload.
	 * @param array $source_id_map Source post ID => target post ID.
	 * @return void
	 */
	private function apply_synced_post_wpml_data( $post_id, array $post_data, array $source_id_map ) {
		if ( ! WPML_Compatibility::is_active() || empty( $post_data['wpml'] ) || ! is_array( $post_data['wpml'] ) ) {
			return;
		}

		WPML_Compatibility::apply_post_language_details( (int) $post_id, $post_data['wpml'], $source_id_map );
	}

	/**
	 * Apply WPML data for a set of synced posts after the full ID map is known.
	 *
	 * @param array $posts_data    Incoming posts payload.
	 * @param array $source_id_map Source post ID => target post ID.
	 * @return void
	 */
	private function apply_synced_posts_wpml_data( array $posts_data, array $source_id_map ) {
		if ( ! WPML_Compatibility::is_active() || empty( $source_id_map ) ) {
			return;
		}

		usort(
			$posts_data,
			static function ( $left, $right ) {
				$left_wpml       = isset( $left['wpml'] ) && is_array( $left['wpml'] ) ? $left['wpml'] : [];
				$right_wpml      = isset( $right['wpml'] ) && is_array( $right['wpml'] ) ? $right['wpml'] : [];
				$left_is_source  = empty( $left_wpml['source_language_code'] ) || 'source' === ( $left_wpml['translation_role'] ?? '' );
				$right_is_source = empty( $right_wpml['source_language_code'] ) || 'source' === ( $right_wpml['translation_role'] ?? '' );

				if ( $left_is_source === $right_is_source ) {
					return 0;
				}

				return $left_is_source ? -1 : 1;
			}
		);

		foreach ( $posts_data as $post_data ) {
			$source_id = absint( $post_data['ID'] ?? 0 );
			$target_id = $source_id > 0 ? absint( $source_id_map[ $source_id ] ?? 0 ) : 0;
			if ( $target_id <= 0 ) {
				continue;
			}

			$this->apply_synced_post_wpml_data( $target_id, (array) $post_data, $source_id_map );
		}
	}

	/**
	 * Apply WPML language data received for a synced term.
	 *
	 * @param int    $term_id       Target term ID.
	 * @param string $taxonomy      Taxonomy name.
	 * @param array  $term_info     Incoming term payload.
	 * @param array  $source_id_map Source term ID => target term ID.
	 * @return void
	 */
	private function apply_synced_term_wpml_data( $term_id, $taxonomy, array $term_info, array $source_id_map ) {
		if ( ! WPML_Compatibility::is_active() || empty( $term_info['wpml'] ) || ! is_array( $term_info['wpml'] ) ) {
			return;
		}

		WPML_Compatibility::apply_term_language_details( (int) $term_id, (string) $taxonomy, $term_info['wpml'], $source_id_map );
	}

	/**
	 * Apply WPML data for synced terms once all target term IDs are known.
	 *
	 * @param string $taxonomy      Taxonomy name.
	 * @param array  $terms         Incoming term payloads.
	 * @param array  $source_id_map Source term ID => target term ID.
	 * @return void
	 */
	private function apply_synced_terms_wpml_data( $taxonomy, array $terms, array $source_id_map ) {
		if ( ! WPML_Compatibility::is_active() || empty( $source_id_map ) ) {
			return;
		}

		usort(
			$terms,
			static function ( $left, $right ) {
				$left_wpml       = isset( $left['wpml'] ) && is_array( $left['wpml'] ) ? $left['wpml'] : [];
				$right_wpml      = isset( $right['wpml'] ) && is_array( $right['wpml'] ) ? $right['wpml'] : [];
				$left_is_source  = empty( $left_wpml['source_language_code'] ) || 'source' === ( $left_wpml['translation_role'] ?? '' );
				$right_is_source = empty( $right_wpml['source_language_code'] ) || 'source' === ( $right_wpml['translation_role'] ?? '' );

				if ( $left_is_source === $right_is_source ) {
					return 0;
				}

				return $left_is_source ? -1 : 1;
			}
		);

		foreach ( $terms as $term_info ) {
			$source_id = absint( $term_info['term_id'] ?? 0 );
			$target_id = $source_id > 0 ? absint( $source_id_map[ $source_id ] ?? 0 ) : 0;
			if ( $target_id <= 0 ) {
				continue;
			}

			$this->apply_synced_term_wpml_data( $target_id, $taxonomy, (array) $term_info, $source_id_map );
		}
	}
}

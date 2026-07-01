<?php
/**
 * Content Sync API Controller
 *
 * Handles REST API endpoints for content synchronization
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

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
		$posts_data   = $request->get_param( 'posts' );
		$image_map    = $request->get_param( 'image_map' );
		$post_mapping = $request->get_param( 'post_mapping' );

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

		$imported_count      = 0;
		$updated_count       = 0;
		$errors              = array();
		$source_to_local_map = array();
		$source_parent_map   = array();
		$source_type_map     = array();

		foreach ( $posts_data as $post_data ) {
			$source_post_id = $post_data['ID'];
			$target_post_id = null;

			// Check post mapping
			if ( isset( $post_mapping[ $source_post_id ] ) ) {
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

			// Count created vs updated
			if ( $is_update ) {
				++$updated_count;
			} else {
				++$imported_count;
			}

			// Fix image URLs in content after import (replace with correct attachment URLs)
			if ( ! empty( $image_map ) ) {
				$post_content    = get_post_field( 'post_content', $post_id );
				$content_updated = false;

				foreach ( $image_map as $old_id => $new_id ) {
					$new_url = wp_get_attachment_url( $new_id );
					if ( $new_url ) {
						// Find and replace old image URLs with new ones
						// This handles the case where upload date folder changed
						$pattern     = '/(<img[^>]+src=")([^"]*\/)[^"\/]+(\.(?:jpg|jpeg|png|gif|webp|svg))("[^>]*class="[^"]*wp-image-' . $new_id . '[^"]*"[^>]*>)/i';
						$replacement = '$1' . $new_url . '$4';
						$new_content = preg_replace( $pattern, $replacement, $post_content );

						if ( $new_content && $new_content !== $post_content ) {
							$post_content    = $new_content;
							$content_updated = true;
						}
					}
				}

				// Update post content if URLs were fixed
				if ( $content_updated ) {
					wp_update_post(
						array(
							'ID'           => $post_id,
							'post_content' => $post_content,
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
						$image_map
					);
				}

				// Simplified approach: Import all meta fields directly with update_post_meta()
				// ACF will automatically handle the processing of its fields
				foreach ( $post_data['meta'] as $key => $value ) {
					// Skip some internal WordPress meta
					if ( in_array( $key, array( '_edit_lock', '_edit_last', '_rsl_ie_original_post_id' ), true ) ) {
						continue;
					}

					// Import all fields directly - ACF handles its own fields automatically
					update_post_meta( $post_id, $key, $value );
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
					if ( taxonomy_exists( $taxonomy_to_clear ) ) {
						wp_set_object_terms( $post_id, array(), $taxonomy_to_clear );
					}
				}

				foreach ( $post_data['terms'] as $taxonomy => $terms_info ) {
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

						// Find by slug and update name, or create if not found.
						$existing_term = get_term_by( 'slug', $term_info['slug'], $taxonomy );
						if ( $existing_term ) {
							wp_update_term( $existing_term->term_id, $taxonomy, array( 'name' => $term_info['name'] ) );
							$term_id = $existing_term->term_id;
						} else {
							$new_term = wp_insert_term( $term_info['name'], $taxonomy, array( 'slug' => $term_info['slug'] ) );
							if ( is_wp_error( $new_term ) ) {
								continue;
							}
							$term_id = $new_term['term_id'];
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
								$image_map
							);

							foreach ( $term_acf as $field_key => $field_value ) {
								update_field( $field_key, $field_value, $taxonomy . '_' . $term_id );
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
				$this->import_product_variations( $post_id, $post_data['variations'], (array) $image_map );
			}

			// Import WooCommerce grouped product children and remap _children meta.
			// Children are regular products whose IDs differ between sites, so we
			// must import them and rewrite the _children meta with local IDs.
			if ( 'product' === $post_data['post_type']
				&& ! empty( $post_data['grouped_children'] )
				&& class_exists( 'WC_Product' )
				&& function_exists( 'wc_get_product' )
			) {
				$local_child_ids = $this->import_grouped_children( $post_id, $post_data['grouped_children'], (array) $image_map );
				if ( ! empty( $local_child_ids ) ) {
					update_post_meta( $post_id, '_children', $local_child_ids );
				}
			}
		}

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
	private function import_product_variations( $parent_post_id, $variations, $image_map ) {
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
						$image_map
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
	private function import_grouped_children( $parent_post_id, $children, $image_map ) {
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
						$image_map
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
					if ( ! taxonomy_exists( $taxonomy ) ) {
						continue;
					}
					$term_ids = array();
					foreach ( $terms_info as $term_info ) {
						if ( empty( $term_info['name'] ) || empty( $term_info['slug'] ) ) {
							continue;
						}
						$existing_term = get_term_by( 'slug', $term_info['slug'], $taxonomy );
						if ( $existing_term ) {
							wp_update_term( $existing_term->term_id, $taxonomy, array( 'name' => $term_info['name'] ) );
							$term_ids[] = (int) $existing_term->term_id;
						} else {
							$new_term = wp_insert_term( $term_info['name'], $taxonomy, array( 'slug' => $term_info['slug'] ) );
							if ( ! is_wp_error( $new_term ) ) {
								$term_ids[] = (int) $new_term['term_id'];
							}
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
				if ( in_array( $key, $skip_keys, true ) ) {
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
							'term_id' => $term->term_id,
							'name'    => $term->name,
							'slug'    => $term->slug,
						);

						// Get ACF fields for this term
						if ( function_exists( 'get_field_objects' ) ) {
							$acf_fields = get_field_objects( $taxonomy . '_' . $term->term_id );
							if ( $acf_fields ) {
								$term_info['acf'] = array();
								foreach ( $acf_fields as $field_key => $field ) {
									$term_info['acf'][ $field_key ] = $field['value'];
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
							'term_id' => $term->term_id,
							'name'    => $term->name,
							'slug'    => $term->slug,
						);
						$known_ids[]                   = $raw_id;
					}
				}
			}

				$posts_data[] = array(
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
				);
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
		$file_hash = $request->get_param( 'file_hash' );

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
		$file_name   = $request->get_param( 'file_name' );
		$file_data   = $request->get_param( 'file_data' );
		$file_hash   = $request->get_param( 'file_hash' );
		$mime_type   = $request->get_param( 'mime_type' );
		$alt_text    = $request->get_param( 'alt_text' );
		$title       = $request->get_param( 'title' );
		$caption     = $request->get_param( 'caption' );
		$description = $request->get_param( 'description' );

		if ( empty( $file_name ) || empty( $file_data ) || empty( $file_hash ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Missing required file data', 'import-export-by-rockstarlab' ),
				),
				400
			);
		}

		// Check if file already exists
		$existing_attachment = $this->find_attachment_by_hash( $file_hash );
		if ( $existing_attachment ) {
			\RockStarLab\ImportExport\Helper\Content_Sync_Media::ensure_image_sizes( $existing_attachment );
			return new \WP_REST_Response(
				array(
					'success'       => true,
					'attachment_id' => $existing_attachment,
					'message'       => __( 'Media already exists', 'import-export-by-rockstarlab' ),
				),
				200
			);
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
			\RockStarLab\ImportExport\Helper\Fs::load_image_core();
			$attach_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
			wp_update_attachment_metadata( $attachment_id, $attach_data );

		// Set alt text
		if ( ! empty( $alt_text ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
		}

		// Store file hash for future lookups
		\RockStarLab\ImportExport\Helper\Media_Hash::store_attachment_hash( $attachment_id, $file_hash, $file_path );

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
	 * Extract image IDs from term ACF fields
	 *
	 * @param array $acf_data ACF field data.
	 * @return array Array of image IDs
	 */
	private function extract_term_acf_images( $acf_data ) {
		$image_ids = array();

		foreach ( $acf_data as $key => $value ) {
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

	/**
	 * List posts for mapping
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function list_posts( $request ) {
		$post_type = $request->get_param( 'post_type' );
		$search    = $request->get_param( 'search' );
		$status    = $request->get_param( 'status' );
		$page      = absint( $request->get_param( 'page' ) ?: 1 );
		$per_page  = absint( $request->get_param( 'per_page' ) ?: 20 );

		// When searching, include all posts (parent and children)
		// When not searching, show only parent posts (to maintain hierarchy)
		$post_parent_filter = 0; // Default: only top-level posts
		if ( ! empty( $search ) ) {
			$post_parent_filter = ''; // Empty string means no parent filter - include all posts
		}

		$args = array(
			'post_type'           => $post_type ?: 'any',
			'post_status'         => ! empty( $status ) ? $status : 'any',
			'posts_per_page'      => $per_page,
			'paged'               => $page,
			'orderby'             => 'date',
			'order'               => 'DESC',
			'post_parent'         => $post_parent_filter,
			'ignore_sticky_posts' => true, // Exclude sticky posts from results
			'post__not_in'        => get_option( 'sticky_posts', array() ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- post__not_in required to exclude sticky posts.
		);

		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		$query      = new \WP_Query( $args );
		$posts_list = array();
		$total      = $query->found_posts;

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
			if ( empty( $search ) ) {
				$children_count = $this->count_children( $post->ID, $post->post_type );
			}
			$post_data['children_count'] = $children_count;

			$posts_list[] = $post_data;
		}

		// Get status counts for filters
		$status_counts = $this->get_status_counts( $post_type );

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
	private function get_status_counts( $post_type ) {
		$counts = array(
			'all'     => 0,
			'publish' => 0,
			'draft'   => 0,
			'pending' => 0,
		);

		$statuses = array( 'publish', 'draft', 'pending', 'private', 'future' );

		foreach ( $statuses as $status ) {
			$count_query = new \WP_Query(
				array(
					'post_type'           => $post_type ?: 'any',
					'post_status'         => $status,
					'posts_per_page'      => 1,
					'fields'              => 'ids',
					'ignore_sticky_posts' => true,
					'post__not_in'        => get_option( 'sticky_posts', array() ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- post__not_in required for correct filtering.
				)
			);

			$count = $count_query->found_posts;

			if ( $status === 'publish' || $status === 'private' || $status === 'future' ) {
				$counts['publish'] += $count;
			} elseif ( isset( $counts[ $status ] ) ) {
				$counts[ $status ] = $count;
			}

			$counts['all'] += $count;
		}

		return $counts;
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

		$children = get_posts(
			array(
				'post_parent'         => $parent_id,
				'post_type'           => ! empty( $post_type ) ? $post_type : 'any',
				'post_status'         => array( 'publish', 'draft', 'pending', 'private', 'future' ),
				'posts_per_page'      => -1,
				'orderby'             => 'date',
				'order'               => 'DESC',
				'ignore_sticky_posts' => true,
				'post__not_in'        => get_option( 'sticky_posts', array() ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in -- post__not_in required for correct filtering.
			)
		);

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
}

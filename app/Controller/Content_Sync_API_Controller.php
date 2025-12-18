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

			register_rest_route(
				'aie/v1',
				'/check-media',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'check_media' ),
					'permission_callback' => array( $this, 'validate_api_key' ),
				)
			);

			register_rest_route(
				'aie/v1',
				'/upload-media',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'upload_media' ),
					'permission_callback' => array( $this, 'validate_api_key' ),
				)
			);

			register_rest_route(
				'aie/v1',
				'/list-posts',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'list_posts' ),
					'permission_callback' => array( $this, 'validate_api_key' ),
				)
			);

			register_rest_route(
				'aie/v1',
				'/get-children-posts',
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'get_children_posts' ),
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
		// Check if premium license is active
		$is_premium = function_exists( 'waie_fs' ) && waie_fs()->can_use_premium_code();
		
		if ( ! $is_premium ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Premium license is required for Content Sync feature.', 'wp-advanced-import-export' ),
					'error_code' => 'license_inactive',
				),
				403
			);
		}
		
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
		// Check if premium license is active
		$is_premium = function_exists( 'waie_fs' ) && waie_fs()->can_use_premium_code();
		
		if ( ! $is_premium ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Premium license is required for Content Sync feature.', 'wp-advanced-import-export' ),
					'error_code' => 'license_inactive',
				),
				403
			);
		}
		
		$posts_data   = $request->get_param( 'posts' );
		$image_map    = $request->get_param( 'image_map' );
		$post_mapping = $request->get_param( 'post_mapping' );
		
		// Debug: Log received parameters
		error_log( 'WP_AIE: receive_content called with ' . count( $posts_data ) . ' posts' );
		error_log( 'WP_AIE: image_map provided: ' . ( ! empty( $image_map ) ? 'YES (' . count( $image_map ) . ' mappings)' : 'NO' ) );
		error_log( 'WP_AIE: post_mapping provided: ' . ( ! empty( $post_mapping ) ? 'YES (' . count( $post_mapping ) . ' mappings)' : 'NO' ) );

		if ( empty( $posts_data ) || ! is_array( $posts_data ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'No posts data provided', 'wp-advanced-import-export' ),
				),
				400
			);
		}

		// Parse post_mapping
		if ( ! is_array( $post_mapping ) ) {
			$post_mapping = array();
		}

		$imported_count = 0;
		$updated_count  = 0;
		$errors         = array();

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
				$target_post_id = $this->find_existing_post( $source_post_id );
			}
			
			// Check if images referenced in content exist
			if ( preg_match_all( '/wp-image-(\d+)/', $post_data['post_content'], $matches ) ) {
				foreach ( $matches[1] as $img_id ) {
					$attachment = get_post( $img_id );
					if ( $attachment && 'attachment' === $attachment->post_type ) {
						$url = wp_get_attachment_url( $img_id );
						$file_path = get_attached_file( $img_id );
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
						update_post_meta( $post_id, '_aie_original_post_id', $source_post_id );
					}
				}
			} else {
				// Create new post (no mapping or mapped to "new")
				$post_id = wp_insert_post( $post_args );
				
				// Store original ID for future sync operations
				if ( ! is_wp_error( $post_id ) && $post_id ) {
					update_post_meta( $post_id, '_aie_original_post_id', $source_post_id );
				}
			}
			

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				$errors[] = sprintf(
					/* translators: %s: post title */
					__( 'Failed to import post: %s', 'wp-advanced-import-export' ),
					$post_data['post_title']
				);
				continue;
			}

			// Count created vs updated
			if ( $is_update ) {
				$updated_count++;
			} else {
				$imported_count++;
			}
			
			// Fix image URLs in content after import (replace with correct attachment URLs)
			if ( ! empty( $image_map ) ) {
				$post_content = get_post_field( 'post_content', $post_id );
				$content_updated = false;
				
				foreach ( $image_map as $old_id => $new_id ) {
					$new_url = wp_get_attachment_url( $new_id );
					if ( $new_url ) {
						// Find and replace old image URLs with new ones
						// This handles the case where upload date folder changed
						$pattern = '/(<img[^>]+src=")([^"]*\/)[^"\/]+(\.(?:jpg|jpeg|png|gif|webp|svg))("[^>]*class="[^"]*wp-image-' . $new_id . '[^"]*"[^>]*>)/i';
						$replacement = '$1' . $new_url . '$4';
						$new_content = preg_replace( $pattern, $replacement, $post_content );
						
						if ( $new_content && $new_content !== $post_content ) {
							$post_content = $new_content;
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
				
				// Replace image IDs in meta using image_map
				if ( ! empty( $image_map ) ) {
					error_log( 'WP_AIE: Replacing image IDs in meta for post ' . $post_id );
					error_log( 'WP_AIE: Image map: ' . print_r( $image_map, true ) );
					
					$post_data['meta'] = \WP_AIE\Helper\Content_Sync_Replacer::replace_in_array(
						$post_data['meta'],
						'', // No domain replacement
						'',
						$image_map,
						0 // Start at depth 0
					);
					
					error_log( 'WP_AIE: Meta after replacement: ' . print_r( array_filter( $post_data['meta'], function( $key ) {
						return in_array( $key, array( 'image', 'repeater' ) );
					}, ARRAY_FILTER_USE_KEY ), true ) );
				}
				
				// Check if ACF is available
				$use_acf = function_exists( 'update_field' );
				$acf_field_keys = array();
				
				// First pass: identify ACF field reference keys (those starting with _ that contain field_xxxxx)
				if ( $use_acf ) {
					foreach ( $post_data['meta'] as $key => $value ) {
						if ( strpos( $key, '_' ) === 0 && is_string( $value ) && strpos( $value, 'field_' ) === 0 ) {
							// This is an ACF field reference (e.g., _my_field => field_xxxxx)
							$field_name = substr( $key, 1 ); // Remove leading underscore to get field name
							$acf_field_keys[ $field_name ] = $value; // Store field key
						}
					}
					
					// Convert flat ACF structure to hierarchical for repeater/flexible content fields
					$post_data['meta'] = $this->convert_acf_flat_to_hierarchical( $post_data['meta'], $acf_field_keys );
				}
				
				// Group fields by parent (for repeater/flexible content sub-fields)
				$top_level_fields = array();
				$nested_fields = array();
				
				foreach ( $acf_field_keys as $field_name => $field_key ) {
					// Check if this is a nested field (contains _0_, _1_, etc.)
					if ( preg_match( '/_\d+_/', $field_name ) ) {
						$nested_fields[ $field_name ] = $field_key;
					} else {
						$top_level_fields[ $field_name ] = $field_key;
					}
				}
				
				// Second pass: import all meta fields
				// First, process top-level fields and complex fields (repeater, flex) - they will handle nested data
				foreach ( $post_data['meta'] as $key => $value ) {
					// Skip some internal WordPress meta
					if ( in_array( $key, array( '_edit_lock', '_edit_last' ), true ) ) {
						continue;
					}
					
					// Check if this is a top-level ACF field
					if ( $use_acf && isset( $top_level_fields[ $key ] ) ) {
						// This is a top-level ACF field - use update_field()
						$field_key = $top_level_fields[ $key ];
						
						// Debug: Log field values with detailed structure
						if ( in_array( $key, array( 'image', 'repeater', 'flexible_content' ) ) || strpos( $key, 'repeater' ) !== false ) {
							error_log( '======================================' );
							error_log( 'WP_AIE: Updating top-level ACF field "' . $key . '"' );
							error_log( 'WP_AIE: Field key: ' . $field_key );
							error_log( 'WP_AIE: Value type: ' . gettype( $value ) );
							if ( is_array( $value ) ) {
								error_log( 'WP_AIE: Array structure (first 2000 chars): ' . substr( print_r( $value, true ), 0, 2000 ) );
							} else {
								error_log( 'WP_AIE: Value: ' . $value );
							}
							error_log( '======================================' );
						}
						
						// Try update_field with field key first
						$result = update_field( $field_key, $value, $post_id );
						
						error_log( 'WP_AIE: update_field(' . $field_key . ', ..., ' . $post_id . ') result: ' . ( $result ? 'SUCCESS' : 'FAILED' ) );
						
						// If that failed, try with field name instead
						if ( ! $result ) {
							$result = update_field( $key, $value, $post_id );
							error_log( 'WP_AIE: update_field(' . $key . ', ..., ' . $post_id . ') result: ' . ( $result ? 'SUCCESS' : 'FAILED' ) );
						}
						
						// Also set the reference meta directly
						update_post_meta( $post_id, '_' . $key, $field_key );
						
						// If update_field failed completely, fall back to direct meta update
						if ( ! $result ) {
							update_post_meta( $post_id, $key, $value );
						}
					} 
					// Check if this is a nested ACF field
					elseif ( $use_acf && isset( $nested_fields[ $key ] ) ) {
						// This is a nested field - just save the reference, data is in parent field
						$field_key = $nested_fields[ $key ];
						update_post_meta( $post_id, '_' . $key, $field_key );
						// Also save the value directly in case parent doesn't include it
						update_post_meta( $post_id, $key, $value );
					} 
					else {
						// Regular meta field or ACF reference field
						update_post_meta( $post_id, $key, $value );
					}
				}
			}

			// Import terms with ACF fields
			if ( ! empty( $post_data['terms'] ) ) {
				foreach ( $post_data['terms'] as $taxonomy => $terms_info ) {
					// Check if taxonomy exists
					if ( ! taxonomy_exists( $taxonomy ) ) {
						continue;
					}

					$term_ids = array();
					foreach ( $terms_info as $term_info ) {
						// Get or create term
						$term = term_exists( $term_info['slug'], $taxonomy );
						if ( ! $term ) {
							$term = wp_insert_term( $term_info['name'], $taxonomy, array( 'slug' => $term_info['slug'] ) );
							if ( is_wp_error( $term ) ) {
								continue;
							}
						}

						$term_id = is_array( $term ) ? $term['term_id'] : $term;
						$term_ids[] = $term_id;

						// Import ACF fields for this term
						if ( ! empty( $term_info['acf'] ) && function_exists( 'update_field' ) ) {
							// Replace image IDs in term ACF fields
							$term_acf = \WP_AIE\Helper\Content_Sync_Replacer::replace_in_array(
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
			}
		}

		$total_processed = $imported_count + $updated_count;
		$message         = array();
		
		if ( $imported_count > 0 ) {
			$message[] = sprintf(
				/* translators: %d: number of posts */
				_n( 'Created %d post', 'Created %d posts', $imported_count, 'wp-advanced-import-export' ),
				$imported_count
			);
		}
		
		if ( $updated_count > 0 ) {
			$message[] = sprintf(
				/* translators: %d: number of posts */
				_n( 'Updated %d post', 'Updated %d posts', $updated_count, 'wp-advanced-import-export' ),
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
				$row_prefix = $field_name . '_0_';
				foreach ( $meta as $meta_key => $meta_value ) {
					if ( strpos( $meta_key, $row_prefix ) === 0 ) {
						$has_sub_fields = true;
						break;
					}
				}
				
				// If no sub-fields found, this is not a repeater (probably just a numeric field like image ID)
				if ( ! $has_sub_fields ) {
					error_log( 'WP_AIE ACF Convert: Skipping "' . $field_name . '" - numeric value but no sub-fields found (likely an image/file ID)' );
					continue;
				}
				
				error_log( 'WP_AIE ACF Convert: Found repeater/flex field "' . $field_name . '" with ' . $row_count . ' rows' );
				error_log( 'WP_AIE ACF Convert: Looking for fields starting with "' . $field_name . '_0_"' );
				
				// Build hierarchical structure
				$rows = array();
				for ( $i = 0; $i < $row_count; $i++ ) {
					$row_data = array();
					$row_prefix = $field_name . '_' . $i . '_';
					$found_fields = 0;
					
					// Find all fields for this row
					foreach ( $meta as $meta_key => $meta_value ) {
						if ( strpos( $meta_key, $row_prefix ) === 0 ) {
							$found_fields++;
							// Extract field name without row prefix
							$sub_field_name = substr( $meta_key, strlen( $row_prefix ) );
							
							// Check if this is a nested repeater/flexible content
							if ( isset( $acf_field_keys[ $field_name . '_' . $i . '_' . $sub_field_name ] ) && is_numeric( $meta_value ) ) {
								// Verify nested repeater has sub-fields
								$nested_prefix = $field_name . '_' . $i . '_' . $sub_field_name . '_0_';
								$nested_has_sub_fields = false;
								foreach ( $meta as $nested_key => $nested_val ) {
									if ( strpos( $nested_key, $nested_prefix ) === 0 ) {
										$nested_has_sub_fields = true;
										break;
									}
								}
								
								if ( $nested_has_sub_fields ) {
									// Recursively process nested repeater
									$nested_rows = $this->extract_nested_repeater_data( $meta, $field_name . '_' . $i . '_' . $sub_field_name, $meta_value, $acf_field_keys );
									$row_data[ $sub_field_name ] = $nested_rows;
									error_log( 'WP_AIE ACF Convert: Added nested repeater "' . $sub_field_name . '" with ' . count( $nested_rows ) . ' rows' );
								} else {
									// Just a numeric value (like image ID)
									$row_data[ $sub_field_name ] = $meta_value;
									error_log( 'WP_AIE ACF Convert: Added field "' . $sub_field_name . '" = ' . $meta_value );
								}
							} else {
								$row_data[ $sub_field_name ] = $meta_value;
								if ( strlen( print_r( $meta_value, true ) ) < 100 ) {
									error_log( 'WP_AIE ACF Convert: Added field "' . $sub_field_name . '" = ' . ( is_array( $meta_value ) ? json_encode( $meta_value ) : $meta_value ) );
								}
							}
						}
					}
					
					error_log( 'WP_AIE ACF Convert: Row ' . $i . ' - found ' . $found_fields . ' fields with prefix "' . $row_prefix . '"' );
					$rows[] = $row_data;
				}
				
				// Replace numeric count with actual data array
				$meta[ $field_name ] = $rows;
				$processed_parents[] = $field_name;
				
				error_log( 'WP_AIE ACF Convert: Converted "' . $field_name . '" to array with ' . count( $rows ) . ' rows' );
				error_log( 'WP_AIE ACF Convert: Full structure: ' . substr( print_r( $rows, true ), 0, 2000 ) );
			}
		}
		
		return $meta;
	}

	/**
	 * Extract nested repeater data recursively
	 *
	 * @param array $meta Post meta array
	 * @param string $parent_prefix Parent field prefix (e.g., "repeater_0_nested_repeater")
	 * @param int $row_count Number of rows
	 * @param array $acf_field_keys ACF field keys mapping
	 * @return array Nested rows data
	 */
	private function extract_nested_repeater_data( $meta, $parent_prefix, $row_count, $acf_field_keys ) {
		$rows = array();
		
		for ( $i = 0; $i < $row_count; $i++ ) {
			$row_data = array();
			$row_prefix = $parent_prefix . '_' . $i . '_';
			
			foreach ( $meta as $meta_key => $meta_value ) {
				if ( strpos( $meta_key, $row_prefix ) === 0 ) {
					$sub_field_name = substr( $meta_key, strlen( $row_prefix ) );
					
					// Check for even deeper nesting
					if ( isset( $acf_field_keys[ $parent_prefix . '_' . $i . '_' . $sub_field_name ] ) && is_numeric( $meta_value ) ) {
						// Verify this nested field actually has sub-fields (is a real repeater)
						$nested_prefix = $parent_prefix . '_' . $i . '_' . $sub_field_name . '_0_';
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
	 * Find existing post by original post ID only
	 *
	 * @param array $post_data Post data from remote site.
	 * @return \WP_Post|null Existing post or null
	 */
	private function find_existing_post( $post_data ) {
		// Only search by original post ID stored in meta
		if ( ! isset( $post_data['ID'] ) ) {
			return null;
		}


		$posts = get_posts(
			array(
				'post_type'      => $post_data['post_type'],
				'posts_per_page' => -1, // Get all to debug
				'post_status'    => 'any',
				'meta_query'     => array(
					array(
						'key'   => '_aie_original_post_id',
						'value' => $post_data['ID'],
					),
				),
			)
		);

		
		if ( ! empty( $posts ) ) {
			foreach ( $posts as $post ) {
				$original_id = get_post_meta( $post->ID, '_aie_original_post_id', true );
			}
			return $posts[0];
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
		// Check if premium license is active
		$is_premium = function_exists( 'waie_fs' ) && waie_fs()->can_use_premium_code();
		
		if ( ! $is_premium ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Premium license is required for Content Sync feature.', 'wp-advanced-import-export' ),
					'error_code' => 'license_inactive',
				),
				403
			);
		}
		
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
		$all_images = array();
		$not_found_ids = array();

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				$not_found_ids[] = $post_id;
				continue;
			}

			// Extract all images from post
			$post_images = \WP_AIE\Helper\Content_Sync_Media::extract_post_images( $post_id );
			
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
				'_aie_original_post_id', // Our own sync meta
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
									$image_data = array(
										'attachment_id' => $image_id,
										'url'           => wp_get_attachment_url( $image_id ),
										'type'          => 'term_acf',
										'term_id'       => $term->term_id,
										'taxonomy'      => $taxonomy,
									);
									$all_images[ $image_id ] = $image_data;
								}
							}
						}
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
				'post_name'     => $post->post_name,
				'post_date'     => $post->post_date,
				'post_modified' => $post->post_modified,
				'post_author'   => $post->post_author,
				'meta'          => $prepared_meta,
				'terms'         => $terms_data,
			);
		}

		if ( empty( $posts_data ) ) {
			$error_message = __( 'No valid posts found', 'wp-advanced-import-export' );
			if ( ! empty( $not_found_ids ) ) {
				$error_message .= sprintf(
					/* translators: %s: comma-separated list of post IDs */
					__( '. Post IDs not found: %s', 'wp-advanced-import-export' ),
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
					__( 'Found %d post(s)', 'wp-advanced-import-export' ),
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
		// Check if premium license is active
		$is_premium = function_exists( 'waie_fs' ) && waie_fs()->can_use_premium_code();
		
		if ( ! $is_premium ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Premium license is required for Content Sync feature.', 'wp-advanced-import-export' ),
					'error_code' => 'license_inactive',
				),
				403
			);
		}

		$file_hash = $request->get_param( 'file_hash' );

		if ( empty( $file_hash ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'File hash is required', 'wp-advanced-import-export' ),
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
		// Check if premium license is active
		$is_premium = function_exists( 'waie_fs' ) && waie_fs()->can_use_premium_code();
		
		if ( ! $is_premium ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Premium license is required for Content Sync feature.', 'wp-advanced-import-export' ),
					'error_code' => 'license_inactive',
				),
				403
			);
		}

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
					'message' => __( 'Missing required file data', 'wp-advanced-import-export' ),
				),
				400
			);
		}

		// Check if file already exists
		$existing_attachment = $this->find_attachment_by_hash( $file_hash );
		if ( $existing_attachment ) {
			return new \WP_REST_Response(
				array(
					'success'       => true,
					'attachment_id' => $existing_attachment,
					'message'       => __( 'Media already exists', 'wp-advanced-import-export' ),
				),
				200
			);
		}

		// Decode base64 data
		$file_contents = base64_decode( $file_data );
		
		if ( false === $file_contents ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid file data', 'wp-advanced-import-export' ),
				),
				400
			);
		}

		// Verify file hash
		if ( md5( $file_contents ) !== $file_hash ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'File hash mismatch', 'wp-advanced-import-export' ),
				),
				400
			);
		}

		// Upload file
		require_once ABSPATH . 'wp-admin/includes/file.php';

		$upload_dir = wp_upload_dir();
		$file_path  = $upload_dir['path'] . '/' . wp_unique_filename( $upload_dir['path'], $file_name );

		// Write file
		$saved = @file_put_contents( $file_path, $file_contents );

		if ( false === $saved ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Failed to save file', 'wp-advanced-import-export' ),
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
			@unlink( $file_path );
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Failed to create attachment', 'wp-advanced-import-export' ),
				),
				500
			);
		}

		// Generate and update attachment metadata
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_data = wp_generate_attachment_metadata( $attachment_id, $file_path );
		wp_update_attachment_metadata( $attachment_id, $attach_data );

		// Set alt text
		if ( ! empty( $alt_text ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $alt_text ) );
		}

		// Store file hash for future lookups
		update_post_meta( $attachment_id, '_aie_file_hash', $file_hash );

		return new \WP_REST_Response(
			array(
				'success'       => true,
				'attachment_id' => $attachment_id,
				'url'           => wp_get_attachment_url( $attachment_id ),
				'message'       => __( 'Media uploaded successfully', 'wp-advanced-import-export' ),
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
		global $wpdb;

		// First check by stored hash meta
		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} 
				WHERE meta_key = '_aie_file_hash' 
				AND meta_value = %s 
				LIMIT 1",
				$file_hash
			)
		);

		if ( $attachment_id ) {
			return (int) $attachment_id;
		}

		// Fallback: check all attachments
		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $attachments as $attachment_id ) {
			$file_path = get_attached_file( $attachment_id );
			
			if ( $file_path && file_exists( $file_path ) ) {
				$hash = md5_file( $file_path );
				
				if ( $hash === $file_hash ) {
					// Store hash for future lookups
					update_post_meta( $attachment_id, '_aie_file_hash', $file_hash );
					return $attachment_id;
				}
			}
		}

		return false;
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
		// Check if premium license is active
		$is_premium = function_exists( 'waie_fs' ) && waie_fs()->can_use_premium_code();
		
		if ( ! $is_premium ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Premium license is required for Content Sync feature.', 'wp-advanced-import-export' ),
					'error_code' => 'license_inactive',
				),
				403
			);
		}

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
			'post__not_in'        => get_option( 'sticky_posts', array() ), // Exclude all sticky posts regardless of status
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

			// Get children count
			$children_count = 0;
			if ( empty( $search ) ) {
				$children_count = $this->count_children( $post->ID );
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
	private function count_children( $post_id ) {
		$children = get_posts(
			array(
				'post_parent'         => $post_id,
				'post_type'           => 'any',
				'post_status'         => 'any',
				'posts_per_page'      => -1,
				'fields'              => 'ids',
				'ignore_sticky_posts' => true,
				'post__not_in'        => get_option( 'sticky_posts', array() ),
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
					'post__not_in'        => get_option( 'sticky_posts', array() ),
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
		// Check if premium license is active
		$is_premium = function_exists( 'waie_fs' ) && waie_fs()->can_use_premium_code();
		
		if ( ! $is_premium ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Premium license is required for Content Sync feature.', 'wp-advanced-import-export' ),
					'error_code' => 'license_inactive',
				),
				403
			);
		}

		$parent_id = absint( $request->get_param( 'parent_id' ) );

		if ( empty( $parent_id ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Parent ID is required.', 'wp-advanced-import-export' ),
				),
				400
			);
		}

		$children = get_posts(
			array(
				'post_parent'         => $parent_id,
				'post_type'           => 'any',
				'post_status'         => 'any',
				'posts_per_page'      => -1,
				'orderby'             => 'date',
				'order'               => 'DESC',
				'ignore_sticky_posts' => true,
				'post__not_in'        => get_option( 'sticky_posts', array() ),
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

			// Check if this child has children
			$child_data['children_count'] = $this->count_children( $child->ID );

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

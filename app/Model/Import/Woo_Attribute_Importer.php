<?php
/**
 * WooCommerce Attribute Importer
 *
 * Handles importing WooCommerce product attributes with terms
 *
 * IMPORT BEHAVIOR:
 * ----------------
 * 
 * Attributes:
 * - If attribute with same name exists, it will be UPDATED (if update_existing = true)
 * - If attribute doesn't exist, it will be CREATED
 * - attribute_name is used as unique identifier (not attribute_id)
 * 
 * Terms:
 * - If term with same slug exists, it will be UPDATED (if update_terms = true)
 * - If term doesn't exist, it will be CREATED
 * - Terms are matched by slug, not by term_id
 * - You can skip updating existing terms by setting skip_existing_terms = true
 * 
 * DATA FORMATS:
 * -------------
 * 
 * attribute_terms field accepts two formats:
 * 
 * 1. JSON format (from export):
 *    [{"name":"Blue","slug":"blue","description":""},{"name":"Red","slug":"red","description":""}]
 * 
 * 2. Simple comma-separated format:
 *    "Blue, Red, Green, Yellow"
 * 
 * BEST PRACTICES:
 * ---------------
 * 
 * When re-importing exported data:
 * - Terms with existing slugs will be updated with new names/descriptions
 * - No duplicate terms will be created
 * - Existing term relationships with products are preserved
 * 
 * When importing new terms to existing attributes:
 * - Set update_existing = true and update_terms = true
 * - New terms will be added, existing terms will be updated
 * 
 * When you want to add only new terms without touching existing ones:
 * - Set skip_existing_terms = true
 * - Only new terms (by slug) will be created
 *
 * @package WP_AIE\Model\Import
 */

namespace WP_AIE\Model\Import;

defined( 'ABSPATH' ) || exit;

class Woo_Attribute_Importer extends Abstract_Importer {

	/**
	 * Get importer name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'woo_attribute';
	}

	/**
	 * Get importer description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Import WooCommerce product attributes with terms', 'amplified-import-export' );
	}

	/**
	 * Get required fields for import
	 *
	 * @return array
	 */
	public function get_required_fields() {
		return [ 'attribute_name' ];
	}

	/**
	 * Get optional fields for import
	 *
	 * @return array
	 */
	public function get_optional_fields() {
		return [
			'attribute_label',
			'attribute_type',
			'attribute_orderby',
			'attribute_public',
			'attribute_terms',
		];
	}

	/**
	 * Get available fields for import
	 *
	 * @return array
	 */
	public function get_available_fields() {
		return [
			'attribute_name'    => [
				'label'       => __( 'Attribute Name', 'amplified-import-export' ),
				'description' => __( 'Unique attribute slug (e.g., "color", "size")', 'amplified-import-export' ),
				'required'    => true,
			],
			'attribute_label'   => [
				'label'       => __( 'Attribute Label', 'amplified-import-export' ),
				'description' => __( 'Human-readable label (e.g., "Color", "Size")', 'amplified-import-export' ),
			],
			'attribute_type'    => [
				'label'       => __( 'Attribute Type', 'amplified-import-export' ),
				'description' => __( 'Type of attribute (select, text)', 'amplified-import-export' ),
			],
			'attribute_orderby' => [
				'label'       => __( 'Order By', 'amplified-import-export' ),
				'description' => __( 'Default sort order (menu_order, name, name_num, id)', 'amplified-import-export' ),
			],
			'attribute_public'  => [
				'label'       => __( 'Enable Archives', 'amplified-import-export' ),
				'description' => __( 'Enable archives for this attribute (0 or 1)', 'amplified-import-export' ),
			],
			'attribute_terms'   => [
				'label'       => __( 'Attribute Terms', 'amplified-import-export' ),
				'description' => __( 'JSON array of terms with name, slug, and description OR comma-separated list of term names', 'amplified-import-export' ),
			],
		];
	}

	/**
	 * Get supported import options
	 *
	 * @return array
	 */
	public function get_supported_options() {
		return [
			'update_existing'     => __( 'Update existing attributes if found', 'amplified-import-export' ),
			'update_terms'        => __( 'Update existing terms if found', 'amplified-import-export' ),
			'skip_existing_terms' => __( 'Skip existing terms (don\'t update them)', 'amplified-import-export' ),
		];
	}

	/**
	 * Get default import options
	 *
	 * @return array
	 */
	protected function get_default_options() {
		return [
			'update_existing'     => true,  // Update existing attributes if found
			'update_terms'        => true,  // Update existing terms if found
			'skip_existing_terms' => false, // Skip existing terms (don't update them)
			'delete_missing'      => false, // NOT IMPLEMENTED: Delete terms not in import
		];
	}

	/**
	 * Prepare data with mapping
	 *
	 * @param array $data    Raw data from parser
	 * @param array $mapping Field mapping
	 * @return array Prepared data
	 */
	public function prepare( $data, $mapping = [] ) {
		$prepared = [];

		foreach ( $data as $row ) {
			$item = [];

			// Process mapping array
			foreach ( $mapping as $map ) {
				$source_field = $map['source_field'] ?? '';
				$target_field = $map['target_field'] ?? '';

				if ( empty( $target_field ) ) {
					continue;
				}

				// Get value from source
				$value = '';
				if ( isset( $map['source_index'] ) && isset( $row[ $map['source_index'] ] ) ) {
					$value = $row[ $map['source_index'] ];
				} elseif ( ! empty( $source_field ) && isset( $row[ $source_field ] ) ) {
					$value = $row[ $source_field ];
				}

				// Special handling for certain fields
				switch ( $target_field ) {
					case 'attribute_name':
						// Sanitize attribute name (lowercase, no spaces)
						$item[ $target_field ] = sanitize_title( $value );
						break;

					case 'attribute_public':
						// Convert to integer
						$item[ $target_field ] = (int) $value;
						break;

					case 'attribute_terms':
						// Parse JSON or handle array or comma-separated string
						if ( is_string( $value ) && ! empty( $value ) ) {
							// Try to decode as JSON first
							$decoded = json_decode( $value, true );
							if ( is_array( $decoded ) ) {
								$item[ $target_field ] = $decoded;
							} else {
								// Treat as comma-separated list of term names
								$terms = array_map( 'trim', explode( ',', $value ) );
								$item[ $target_field ] = array_map( function( $term_name ) {
									return [
										'name'        => $term_name,
										'slug'        => sanitize_title( $term_name ),
										'description' => '',
									];
								}, array_filter( $terms ) );
							}
						} elseif ( is_array( $value ) ) {
							$item[ $target_field ] = $value;
						} else {
							$item[ $target_field ] = [];
						}
						break;

					default:
						$item[ $target_field ] = $value;
						break;
				}
			}

			// Set defaults
			if ( ! isset( $item['attribute_label'] ) && isset( $item['attribute_name'] ) ) {
				$item['attribute_label'] = ucwords( str_replace( [ '-', '_' ], ' ', $item['attribute_name'] ) );
			}

			if ( ! isset( $item['attribute_type'] ) ) {
				$item['attribute_type'] = 'select';
			}

			if ( ! isset( $item['attribute_orderby'] ) ) {
				$item['attribute_orderby'] = 'menu_order';
			}

			if ( ! isset( $item['attribute_public'] ) ) {
				$item['attribute_public'] = 0;
			}

			$prepared[] = $item;
		}

		return $prepared;
	}

	/**
	 * Import single attribute item
	 *
	 * @param array $item  Prepared item data
	 * @param int   $index Item index
	 * @return int|string|WP_Error Item ID, 'skipped', 'updated', or WP_Error
	 */
	public function import_item( $item, $index ) {
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new \WP_Error(
				'woocommerce_not_active',
				__( 'WooCommerce is not active', 'amplified-import-export' )
			);
		}

		// Validate required fields
		if ( empty( $item['attribute_name'] ) ) {
			return new \WP_Error(
				'missing_attribute_name',
				__( 'Attribute name is required', 'amplified-import-export' )
			);
		}

		$attribute_name = wc_sanitize_taxonomy_name( $item['attribute_name'] );

		// Check if attribute exists
		$existing_id = $this->get_attribute_id_by_name( $attribute_name );

		if ( $existing_id ) {
			// Attribute exists
			if ( ! $this->options['update_existing'] ) {
				$this->log_info( sprintf( 'Skipping existing attribute: %s', $attribute_name ) );
				return 'skipped';
			}

			// Update existing attribute
			$result = $this->update_attribute( $existing_id, $item );
			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$attribute_id = $existing_id;
			$action       = 'updated';
		} else {
			// Create new attribute
			$attribute_id = $this->create_attribute( $item );
			if ( is_wp_error( $attribute_id ) ) {
				return $attribute_id;
			}

			$action = 'created';
		}

		// Import terms if provided
		if ( ! empty( $item['attribute_terms'] ) && is_array( $item['attribute_terms'] ) ) {
			$terms_result = $this->import_attribute_terms( $attribute_name, $item['attribute_terms'] );
			if ( is_wp_error( $terms_result ) ) {
				$this->log_error(
					sprintf(
						'Failed to import terms for attribute %s: %s',
						$attribute_name,
						$terms_result->get_error_message()
					)
				);
			}
		}

		return $action;
	}

	/**
	 * Create new attribute
	 *
	 * @param array $item Attribute data
	 * @return int|WP_Error Attribute ID or WP_Error
	 */
	protected function create_attribute( $item ) {
		global $wpdb;

		$attribute_name = wc_sanitize_taxonomy_name( $item['attribute_name'] );

		$data = [
			'attribute_name'    => $attribute_name,
			'attribute_label'   => $item['attribute_label'] ?? ucwords( str_replace( '-', ' ', $attribute_name ) ),
			'attribute_type'    => $item['attribute_type'] ?? 'select',
			'attribute_orderby' => $item['attribute_orderby'] ?? 'menu_order',
			'attribute_public'  => isset( $item['attribute_public'] ) ? (int) $item['attribute_public'] : 0,
		];

		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct DB query required here.
			$wpdb->prefix . 'woocommerce_attribute_taxonomies',
			$data,
			[ '%s', '%s', '%s', '%s', '%d' ]
		);

		if ( false === $result ) {
			return new \WP_Error(
				'db_insert_error',
				sprintf(
					/* translators: %s: attribute name */
					__( 'Failed to create attribute: %s', 'amplified-import-export' ),
					$attribute_name
				)
			);
		}

		$attribute_id = $wpdb->insert_id;

		// Register the taxonomy
		$this->register_attribute_taxonomy( $attribute_name );

		// Clear caches
		delete_transient( 'wc_attribute_taxonomies' );
		wp_cache_flush();

		$this->log_info( sprintf( 'Created attribute: %s (ID: %d)', $attribute_name, $attribute_id ) );

		return $attribute_id;
	}

	/**
	 * Update existing attribute
	 *
	 * @param int   $attribute_id Attribute ID
	 * @param array $item         Attribute data
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	protected function update_attribute( $attribute_id, $item ) {
		global $wpdb;

		$attribute_name = wc_sanitize_taxonomy_name( $item['attribute_name'] );

		$data = [
			'attribute_label'   => $item['attribute_label'] ?? ucwords( str_replace( '-', ' ', $attribute_name ) ),
			'attribute_type'    => $item['attribute_type'] ?? 'select',
			'attribute_orderby' => $item['attribute_orderby'] ?? 'menu_order',
			'attribute_public'  => isset( $item['attribute_public'] ) ? (int) $item['attribute_public'] : 0,
		];

		$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			$wpdb->prefix . 'woocommerce_attribute_taxonomies',
			$data,
			[ 'attribute_id' => $attribute_id ],
			[ '%s', '%s', '%s', '%d' ],
			[ '%d' ]
		);

		if ( false === $result ) {
			return new \WP_Error(
				'db_update_error',
				sprintf(
					/* translators: %s: attribute name */
					__( 'Failed to update attribute: %s', 'amplified-import-export' ),
					$attribute_name
				)
			);
		}

		// Clear caches
		delete_transient( 'wc_attribute_taxonomies' );
		wp_cache_flush();

		$this->log_info( sprintf( 'Updated attribute: %s (ID: %d)', $attribute_name, $attribute_id ) );

		return true;
	}

	/**
	 * Import attribute terms
	 *
	 * @param string $attribute_name Attribute name
	 * @param array  $terms          Terms data
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	protected function import_attribute_terms( $attribute_name, $terms ) {
		$taxonomy = wc_attribute_taxonomy_name( $attribute_name );

		// Register taxonomy if not exists
		if ( ! taxonomy_exists( $taxonomy ) ) {
			$this->register_attribute_taxonomy( $attribute_name );
		}

		$imported_count = 0;
		$updated_count  = 0;
		$skipped_count  = 0;

		foreach ( $terms as $term_data ) {
			if ( empty( $term_data['name'] ) ) {
				continue;
			}

			$term_name = $term_data['name'];
			$term_slug = ! empty( $term_data['slug'] ) ? $term_data['slug'] : sanitize_title( $term_name );

			// Check if term exists
			$existing_term = term_exists( $term_slug, $taxonomy );

			if ( $existing_term ) {
				// Term exists
				if ( $this->options['skip_existing_terms'] ) {
					++$skipped_count;
					continue;
				}

				if ( $this->options['update_terms'] ) {
					// Update existing term
					$result = wp_update_term(
						$existing_term['term_id'],
						$taxonomy,
						[
							'name'        => $term_name,
							'slug'        => $term_slug,
							'description' => $term_data['description'] ?? '',
						]
					);

					if ( ! is_wp_error( $result ) ) {
						$this->import_attribute_term_meta( (int) $existing_term['term_id'], $term_data );
						++$updated_count;
					}
				} else {
					++$skipped_count;
				}
			} else {
				// Create new term
				$result = wp_insert_term(
					$term_name,
					$taxonomy,
					[
						'slug'        => $term_slug,
						'description' => $term_data['description'] ?? '',
					]
				);

				if ( ! is_wp_error( $result ) ) {
					$term_id = is_array( $result ) && isset( $result['term_id'] ) ? (int) $result['term_id'] : 0;
					if ( $term_id > 0 ) {
						$this->import_attribute_term_meta( $term_id, $term_data );
					}
					++$imported_count;
				} else {
					$this->log_error(
						sprintf(
							'Failed to create term %s: %s',
							$term_name,
							$result->get_error_message()
						)
					);
				}
			}
		}

		$this->log_info(
			sprintf(
				'Imported terms for %s: %d created, %d updated, %d skipped',
				$attribute_name,
				$imported_count,
				$updated_count,
				$skipped_count
			)
		);

		return true;
	}

	/**
	 * Import portable term meta.
	 *
	 * Exporter can store attachment IDs as "file:<filename>" so imports can resolve
	 * the correct attachment ID on the target site.
	 *
	 * @param int   $term_id   Term ID.
	 * @param array $term_data Term data array.
	 * @return void
	 */
	protected function import_attribute_term_meta( $term_id, $term_data ) {
		$term_id = (int) $term_id;
		if ( $term_id <= 0 ) {
			return;
		}

		if ( empty( $term_data['meta'] ) || ! is_array( $term_data['meta'] ) ) {
			return;
		}

		foreach ( $term_data['meta'] as $key => $value ) {
			if ( '' === $key || null === $key ) {
				continue;
			}

			$resolved = $this->resolve_meta_attachments_recursive( $value );
			update_term_meta( $term_id, (string) $key, $resolved );
		}
	}

	/**
	 * Recursively resolve attachment references in meta values.
	 *
	 * @param mixed $value Meta value.
	 * @return mixed
	 */
	protected function resolve_meta_attachments_recursive( $value ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $k => $v ) {
				$value[ $k ] = $this->resolve_meta_attachments_recursive( $v );
			}
			return $value;
		}

		if ( is_object( $value ) ) {
			if ( $value instanceof \stdClass ) {
				return $this->resolve_meta_attachments_recursive( (array) $value );
			}
			return $value;
		}

		if ( is_string( $value ) && 0 === strpos( $value, 'file:' ) ) {
			$filename = trim( substr( $value, 5 ) );
			if ( '' !== $filename ) {
				$id = $this->resolve_attachment_id_by_filename( $filename );
				if ( $id > 0 ) {
					return $id;
				}
			}
		}

		return $value;
	}

	/**
	 * Resolve attachment by basename.
	 *
	 * @param string $filename Basename, e.g. image.jpg.
	 * @return int Attachment ID or 0.
	 */
	protected function resolve_attachment_id_by_filename( $filename ) {
		$filename = wp_basename( (string) $filename );
		if ( '' === $filename ) {
			return 0;
		}

		global $wpdb;
		$like = '%' . $wpdb->esc_like( $filename );

		$id = (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			$wpdb->prepare(
				"SELECT p.ID
				FROM {$wpdb->posts} p
				INNER JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID
				WHERE p.post_type = 'attachment'
				AND pm.meta_key = '_wp_attached_file'
				AND pm.meta_value LIKE %s
				ORDER BY p.ID DESC
				LIMIT 1",
				$like
			)
		);

		return $id > 0 ? $id : 0;
	}

	/**
	 * Get attribute ID by name
	 *
	 * @param string $attribute_name Attribute name
	 * @return int|null Attribute ID or null if not found
	 */
	protected function get_attribute_id_by_name( $attribute_name ) {
		global $wpdb;

		$attribute_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			$wpdb->prepare(
				"SELECT attribute_id FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = %s",
				$attribute_name
			)
		);

		return $attribute_id ? (int) $attribute_id : null;
	}

	/**
	 * Register attribute taxonomy
	 *
	 * @param string $attribute_name Attribute name
	 */
	protected function register_attribute_taxonomy( $attribute_name ) {
		$taxonomy = wc_attribute_taxonomy_name( $attribute_name );

		if ( taxonomy_exists( $taxonomy ) ) {
			return;
		}

		$labels = [
			'name'          => $attribute_name,
			'singular_name' => $attribute_name,
			// translators: %s is a dynamic value.
			'search_items'  => sprintf( __( 'Search %s', 'amplified-import-export' ), $attribute_name ),
			// translators: %s is a dynamic value.
			'all_items'     => sprintf( __( 'All %s', 'amplified-import-export' ), $attribute_name ),
			// translators: %s is a dynamic value.
			'edit_item'     => sprintf( __( 'Edit %s', 'amplified-import-export' ), $attribute_name ),
			// translators: %s is a dynamic value.
			'update_item'   => sprintf( __( 'Update %s', 'amplified-import-export' ), $attribute_name ),
			// translators: %s is a dynamic value.
			'add_new_item'  => sprintf( __( 'Add new %s', 'amplified-import-export' ), $attribute_name ),
		];

		register_taxonomy(
			$taxonomy,
			[ 'product' ],
			[
				'labels'       => $labels,
				'hierarchical' => false,
				'show_ui'      => false,
				'query_var'    => true,
				'rewrite'      => false,
			]
		);
	}

	/**
	 * Validate import data
	 *
	 * @param array $data Data to validate
	 * @return true|WP_Error
	 */
	public function validate( $data ) {
		// Check WooCommerce is active
		if ( ! class_exists( 'WooCommerce' ) ) {
			return new \WP_Error(
				'woocommerce_not_active',
				__( 'WooCommerce is not active', 'amplified-import-export' )
			);
		}

		// Call parent validation
		return parent::validate( $data );
	}
}

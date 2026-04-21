<?php
/**
 * Menu Importer
 *
 * Handles importing WordPress navigation menus
 *
 * @package RockStarLab\ImportExport\Model\Import
 */

namespace RockStarLab\ImportExport\Model\Import;

defined( 'ABSPATH' ) || exit;

class Menu_Importer extends Abstract_Importer {

	/**
	 * Get importer name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'menu';
	}

	/**
	 * Get importer description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Import WordPress navigation menus', 'import-export-by-rockstarlab' );
	}

	/**
	 * Get required fields
	 *
	 * @return array
	 */
	public function get_required_fields() {
		return [ 'name' ];
	}

	/**
	 * Get optional fields
	 *
	 * @return array
	 */
	public function get_optional_fields() {
		$all_fields = array_keys( $this->get_available_fields() );
		return array_diff( $all_fields, $this->get_required_fields() );
	}

	/**
	 * Get available fields for import
	 *
	 * @return array
	 */
	public function get_available_fields() {
		return [
			'name'        => [
				'label'       => __( 'Menu Name', 'import-export-by-rockstarlab' ),
				'description' => __( 'The name of the menu', 'import-export-by-rockstarlab' ),
				'required'    => true,
			],
			'menu_items'  => [
				'label'       => __( 'Menu Items (Array)', 'import-export-by-rockstarlab' ),
				'description' => __( 'JSON array of menu items with hierarchy', 'import-export-by-rockstarlab' ),
				'type'        => 'json',
			],
			'slug'        => [
				'label'       => __( 'Menu Slug', 'import-export-by-rockstarlab' ),
				'description' => __( 'URL-friendly menu identifier', 'import-export-by-rockstarlab' ),
			],
			'description' => [
				'label'       => __( 'Description', 'import-export-by-rockstarlab' ),
				'description' => __( 'Menu description', 'import-export-by-rockstarlab' ),
			],
			'count'       => [
				'label'       => __( 'Item Count', 'import-export-by-rockstarlab' ),
				'description' => __( 'Number of menu items (informational)', 'import-export-by-rockstarlab' ),
			],
		];
	}

	/**
	 * Get supported options
	 *
	 * @return array
	 */
	public function get_supported_options() {
		return [
			'duplicate_mode'        => 'How to handle duplicates: skip, update, create',
			'duplicate_check'       => 'Field to check for duplicates: name, slug',
			'update_menu_locations' => 'Update theme menu locations: true, false',
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
				'duplicate_check'       => 'name',
				'update_menu_locations' => false,
			]
		);
	}

	/**
	 * Set importer options
	 *
	 * Overrides parent to map duplicate_handling to duplicate_mode
	 *
	 * @param array $options Options to set
	 * @return void
	 */
	public function set_options( $options ) {
		// Map duplicate_handling (from UI) to duplicate_mode (used internally)
		if ( isset( $options['duplicate_handling'] ) && ! isset( $options['duplicate_mode'] ) ) {
			$options['duplicate_mode'] = $options['duplicate_handling'];
		}

		// Map unique_field (from UI) to duplicate_check (used internally)
		if ( isset( $options['unique_field'] ) && ! isset( $options['duplicate_check'] ) ) {
			$options['duplicate_check'] = $options['unique_field'];
		}

		parent::set_options( $options );
	}

	/**
	 * Prepare raw data for import
	 *
	 * Overrides parent to handle JSON decoding of menu_items field
	 *
	 * @param array $raw_data Raw data from file
	 * @param array $mapping  Field mapping
	 * @return array Prepared data
	 */
	public function prepare( $raw_data, $mapping = [] ) {
		// Convert mapping from array of objects to associative array if needed
		$normalized_mapping = [];
		if ( ! empty( $mapping ) ) {
			// Check if mapping is array of objects with source_field/target_field
			if ( isset( $mapping[0] ) && is_array( $mapping[0] ) && isset( $mapping[0]['source_field'] ) ) {
				foreach ( $mapping as $map_item ) {
					$source = $map_item['source_field'] ?? null;
					$target = $map_item['target_field'] ?? null;
					if ( $source && $target ) {
						$normalized_mapping[ $source ] = $target;
					}
				}
			} else {
				// Already in correct format
				$normalized_mapping = $mapping;
			}
		}

		// Use parent's prepare method with normalized mapping
		$prepared = parent::prepare( $raw_data, $normalized_mapping );

		// Decode menu_items JSON strings immediately
		foreach ( $prepared as &$item ) {
			if ( isset( $item['menu_items'] ) && is_string( $item['menu_items'] ) && ! empty( $item['menu_items'] ) ) {
				$decoded = json_decode( $item['menu_items'], true );
				if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
					$item['menu_items'] = $decoded;
				} else {
					// Leave as empty array if decode fails
					$item['menu_items'] = [];
				}
			}
		}

		return $prepared;
	}

	/**
	 * Import single menu
	 *
	 * @param array $item  Menu data
	 * @param int   $index Item index
	 * @return int|string|WP_Error Menu ID, 'skipped', 'updated', or WP_Error
	 */
	public function import_item( $item, $index ) {
		// Sanitize data
		$item = $this->sanitize_item( $item );

		// Check for existing menu
		$existing_menu = $this->find_existing_menu( $item );

		if ( $existing_menu ) {
			$duplicate_mode = $this->get_option( 'duplicate_mode', 'skip' );

			if ( 'skip' === $duplicate_mode ) {
				return 'skipped';
			}

			if ( 'update' === $duplicate_mode ) {
				return $this->update_menu( $existing_menu, $item );
			}

			// 'create' mode - fall through to create new menu
		}

		// Create new menu
		return $this->create_menu( $item );
	}

	/**
	 * Sanitize menu item
	 *
	 * @param array $item Menu data
	 * @return array Sanitized data
	 */
	protected function sanitize_item( $item ) {
		$sanitized = [
			'name'        => isset( $item['name'] ) ? sanitize_text_field( $item['name'] ) : '',
			'slug'        => isset( $item['slug'] ) ? sanitize_title( $item['slug'] ) : '',
			'description' => isset( $item['description'] ) ? sanitize_textarea_field( $item['description'] ) : '',
			'menu_items'  => isset( $item['menu_items'] ) ? $item['menu_items'] : [],
		];

		// Parse menu_items if it's still a JSON string (for backward compatibility)
		if ( is_string( $sanitized['menu_items'] ) && ! empty( $sanitized['menu_items'] ) ) {
			$decoded = json_decode( $sanitized['menu_items'], true );
			if ( json_last_error() === JSON_ERROR_NONE && is_array( $decoded ) ) {
				$sanitized['menu_items'] = $decoded;
				$this->log_info( sprintf( 'Successfully parsed %d menu items from JSON in sanitize', count( $decoded ) ) );
			} else {
				$error_msg = json_last_error_msg();
				$this->log_error(
					sprintf( 'Failed to parse menu_items JSON: %s', $error_msg ),
					[
						'json_preview' => substr( $sanitized['menu_items'], 0, 200 ),
						'menu_name'    => $sanitized['name'],
					]
				);
				$sanitized['menu_items'] = [];
			}
		} elseif ( is_array( $sanitized['menu_items'] ) && ! empty( $sanitized['menu_items'] ) ) {
			// Already an array (decoded in prepare()), just log
			$this->log_info( sprintf( 'Menu items already decoded: %d items', count( $sanitized['menu_items'] ) ) );
		}

		// Generate slug from name if not provided
		if ( empty( $sanitized['slug'] ) && ! empty( $sanitized['name'] ) ) {
			$sanitized['slug'] = sanitize_title( $sanitized['name'] );
		}

		return $sanitized;
	}

	/**
	 * Validate menu data
	 *
	 * @param array $data Menu data array
	 * @return bool|WP_Error True if valid, WP_Error otherwise
	 */
	public function validate( $data ) {
		if ( empty( $data ) || ! is_array( $data ) ) {
			return new \WP_Error(
				'invalid_data',
				__( 'Menu data is empty or invalid', 'import-export-by-rockstarlab' )
			);
		}

		foreach ( $data as $index => $item ) {
			if ( ! is_array( $item ) ) {
				return new \WP_Error(
					'invalid_item',
					sprintf(
						/* translators: %d: row number */
						__( 'Row %d is not a valid array', 'import-export-by-rockstarlab' ),
						$index + 1
					)
				);
			}

			if ( empty( $item['name'] ) ) {
				return new \WP_Error(
					'missing_required_field',
					sprintf(
						/* translators: %d: row number */
						__( 'Row %d is missing required field: name', 'import-export-by-rockstarlab' ),
						$index + 1
					)
				);
			}
		}

		return true;
	}

	/**
	 * Find existing menu
	 *
	 * @param array $item Menu data
	 * @return object|null Menu object or null
	 */
	protected function find_existing_menu( $item ) {
		$check_field = $this->get_option( 'duplicate_check', 'name' );

		if ( 'slug' === $check_field && ! empty( $item['slug'] ) ) {
			$menu = wp_get_nav_menu_object( $item['slug'] );
			if ( $menu ) {
				return $menu;
			}
		}

		// Check by name
		if ( ! empty( $item['name'] ) ) {
			$menu = wp_get_nav_menu_object( $item['name'] );
			if ( $menu ) {
				return $menu;
			}
		}

		return null;
	}

	/**
	 * Create new menu
	 *
	 * @param array $item Menu data
	 * @return int|WP_Error Menu ID or WP_Error
	 */
	protected function create_menu( $item ) {
		// Validate menu name
		if ( empty( $item['name'] ) ) {
			$error = new \WP_Error( 'empty_menu_name', __( 'Menu name is required', 'import-export-by-rockstarlab' ) );
			$this->log_error( sprintf( 'Failed to create menu: %s', $error->get_error_message() ), $item );
			return $error;
		}

		// Create the menu
		$menu_id = wp_create_nav_menu( $item['name'] );

		if ( is_wp_error( $menu_id ) ) {
			$this->log_error( sprintf( 'Failed to create menu "%s": %s', $item['name'], $menu_id->get_error_message() ), $item );
			return $menu_id;
		}

		// Update menu description if provided
		if ( ! empty( $item['description'] ) ) {
			$menu_obj = wp_get_nav_menu_object( $menu_id );
			if ( $menu_obj ) {
				wp_update_term(
					$menu_id,
					'nav_menu',
					[
						'description' => $item['description'],
					]
				);
			}
		}

		// Import menu items
		if ( ! empty( $item['menu_items'] ) && is_array( $item['menu_items'] ) ) {
			$this->log_info( sprintf( 'Importing %d menu items for menu: %s', count( $item['menu_items'] ), $item['name'] ) );
			$result = $this->import_menu_items( $menu_id, $item['menu_items'] );
			if ( is_wp_error( $result ) ) {
				$this->log_error( sprintf( 'Failed to import menu items for "%s": %s', $item['name'], $result->get_error_message() ), $item );
				return $result;
			}
		}

		$this->log_info( sprintf( 'Created menu: %s (ID: %d)', $item['name'], $menu_id ) );

		return 'created';
	}

	/**
	 * Update existing menu
	 *
	 * @param object $existing_menu Existing menu object
	 * @param array  $item         Menu data
	 * @return string|WP_Error 'updated' or WP_Error
	 */
	protected function update_menu( $existing_menu, $item ) {
		$menu_id = $existing_menu->term_id;

		$this->log_info( sprintf( 'Updating existing menu: %s (ID: %d)', $item['name'], $menu_id ) );

		// Update menu name and description
		$update_args = [
			'name' => $item['name'],
		];

		if ( ! empty( $item['description'] ) ) {
			$update_args['description'] = $item['description'];
		}

		$result = wp_update_term( $menu_id, 'nav_menu', $update_args );

		if ( is_wp_error( $result ) ) {
			$this->log_error( sprintf( 'Failed to update menu "%s": %s', $item['name'], $result->get_error_message() ), $item );
			return $result;
		}

		// Delete existing menu items
		$existing_items = wp_get_nav_menu_items( $menu_id );
		if ( $existing_items ) {
			$this->log_info( sprintf( 'Deleting %d existing menu items', count( $existing_items ) ) );
			foreach ( $existing_items as $existing_item ) {
				wp_delete_post( $existing_item->ID, true );
			}
		}

		// Import new menu items
		if ( ! empty( $item['menu_items'] ) && is_array( $item['menu_items'] ) ) {
			$this->log_info( sprintf( 'Importing %d new menu items', count( $item['menu_items'] ) ) );
			$import_result = $this->import_menu_items( $menu_id, $item['menu_items'] );
			if ( is_wp_error( $import_result ) ) {
				$this->log_error( sprintf( 'Failed to import menu items for "%s": %s', $item['name'], $import_result->get_error_message() ), $item );
				return $import_result;
			}
		}

		$this->log_info( sprintf( 'Updated menu: %s (ID: %d)', $item['name'], $menu_id ) );

		return 'updated';
	}

	/**
	 * Import menu items
	 *
	 * @param int   $menu_id    Menu ID
	 * @param array $menu_items Menu items data
	 * @return bool|WP_Error True on success, WP_Error on failure
	 */
	protected function import_menu_items( $menu_id, $menu_items ) {
		if ( empty( $menu_items ) || ! is_array( $menu_items ) ) {
			return true;
		}

		// Map old IDs to new IDs for parent relationships
		$id_map = [];

		// Sort by menu_order to maintain order
		usort(
			$menu_items,
			function ( $a, $b ) {
				$order_a = isset( $a['menu_order'] ) ? intval( $a['menu_order'] ) : 0;
				$order_b = isset( $b['menu_order'] ) ? intval( $b['menu_order'] ) : 0;
				return $order_a - $order_b;
			}
		);

		foreach ( $menu_items as $menu_item ) {
			$old_id     = isset( $menu_item['ID'] ) ? intval( $menu_item['ID'] ) : 0;
			$new_item_id = $this->import_single_menu_item( $menu_id, $menu_item, $id_map );

			if ( is_wp_error( $new_item_id ) ) {
				$this->log_error( sprintf( 'Failed to import menu item: %s', $new_item_id->get_error_message() ) );
				continue;
			}

			if ( $old_id > 0 && $new_item_id > 0 ) {
				$id_map[ $old_id ] = $new_item_id;
			}
		}

		return true;
	}

	/**
	 * Import single menu item
	 *
	 * @param int   $menu_id   Menu ID
	 * @param array $item      Menu item data
	 * @param array $id_map    Map of old IDs to new IDs
	 * @return int|WP_Error Menu item ID or WP_Error
	 */
	protected function import_single_menu_item( $menu_id, $item, $id_map ) {
		// Validate menu item has title
		if ( empty( $item['title'] ) ) {
			$error = new \WP_Error( 'empty_menu_item_title', __( 'Menu item title is required', 'import-export-by-rockstarlab' ) );
			$this->log_error( 'Failed to import menu item: title is empty', $item );
			return $error;
		}

		$item_type   = isset( $item['type'] ) ? sanitize_text_field( $item['type'] ) : '';
		$item_object = isset( $item['object'] ) ? sanitize_text_field( $item['object'] ) : '';

		// Prepare menu item data
		$menu_item_data = [
			'menu-item-title'       => isset( $item['title'] ) ? sanitize_text_field( $item['title'] ) : '',
			'menu-item-status'      => 'publish',
			'menu-item-position'    => isset( $item['menu_order'] ) ? intval( $item['menu_order'] ) : 0,
			'menu-item-target'      => isset( $item['target'] ) ? sanitize_text_field( $item['target'] ) : '',
			'menu-item-attr-title'  => isset( $item['attr_title'] ) ? sanitize_text_field( $item['attr_title'] ) : '',
			'menu-item-description' => isset( $item['description'] ) ? sanitize_textarea_field( $item['description'] ) : '',
			'menu-item-classes'     => isset( $item['classes'] ) && is_array( $item['classes'] ) ? implode( ' ', array_map( 'sanitize_html_class', $item['classes'] ) ) : '',
			'menu-item-xfn'         => isset( $item['xfn'] ) ? sanitize_text_field( $item['xfn'] ) : '',
		];

		// Handle menu item type
		if ( $item_type !== '' ) {
			$menu_item_data['menu-item-type'] = $item_type;
		}

		// Handle object (post_type, taxonomy, custom)
		if ( $item_object !== '' ) {
			$menu_item_data['menu-item-object'] = $item_object;
		}

		// For custom links, URL matters; for post_type/taxonomy WP derives the URL from object-id.
		if ( 'custom' === $item_type ) {
			if ( isset( $item['url'] ) ) {
				$menu_item_data['menu-item-url'] = $this->rewrite_url_to_local_site( (string) $item['url'] );
			}
		}

		// Handle object ID (cross-site safe)
		$resolved_object_id = $this->resolve_menu_item_object_id( $item_type, $item_object, $item );
		if ( $resolved_object_id > 0 ) {
			$menu_item_data['menu-item-object-id'] = $resolved_object_id;
		} elseif ( isset( $item['object_id'] ) && ! empty( $item['object_id'] ) && 'custom' === $item_type ) {
			// Custom links sometimes carry object_id=0; ignore otherwise.
			$menu_item_data['menu-item-object-id'] = intval( $item['object_id'] );
		}

		// Handle parent menu item
		$parent_id = isset( $item['menu_item_parent'] ) ? intval( $item['menu_item_parent'] ) : 0;
		if ( $parent_id > 0 && isset( $id_map[ $parent_id ] ) ) {
			$menu_item_data['menu-item-parent-id'] = $id_map[ $parent_id ];
		}

		// Add menu item
		$new_item_id = wp_update_nav_menu_item( $menu_id, 0, $menu_item_data );

		if ( is_wp_error( $new_item_id ) ) {
			$this->log_error( sprintf( 'Failed to add menu item "%s": %s', $item['title'], $new_item_id->get_error_message() ), $item );
			return $new_item_id;
		}

		$this->log_info( sprintf( 'Added menu item: %s (ID: %d)', $item['title'], $new_item_id ) );

		// Import ACF fields if present
		if ( isset( $item['acf_fields'] ) && is_array( $item['acf_fields'] ) && function_exists( 'update_field' ) ) {
			foreach ( $item['acf_fields'] as $field_name => $field_value ) {
				// Handle image fields
				if ( is_array( $field_value ) && isset( $field_value['ID'] ) ) {
					update_field( $field_name, $field_value['ID'], $new_item_id );
				} else {
					update_field( $field_name, $field_value, $new_item_id );
				}
			}
		}

		// Import other meta fields
		if ( isset( $item['meta'] ) && is_array( $item['meta'] ) ) {
			foreach ( $item['meta'] as $meta_key => $meta_value ) {
				update_post_meta( $new_item_id, $meta_key, $meta_value );
			}
		}

		return $new_item_id;
	}

	/**
	 * Rewrite an absolute URL from another site to the current site's origin.
	 *
	 * Keeps path/query/fragment intact; only replaces scheme/host/port when the URL is absolute.
	 *
	 * @param string $url Raw URL from the import file.
	 * @return string URL rewritten to current site, or original if not absolute/parseable.
	 */
	protected function rewrite_url_to_local_site( $url ) {
		$url = trim( (string) $url );
		if ( $url === '' ) {
			return '';
		}

		$parsed = wp_parse_url( $url );
		if ( empty( $parsed['host'] ) ) {
			// Relative or unparseable; return as-is.
			return esc_url_raw( $url );
		}

		$home        = home_url();
		$home_parsed = wp_parse_url( $home );
		if ( empty( $home_parsed['host'] ) ) {
			return esc_url_raw( $url );
		}

		$scheme = $home_parsed['scheme'] ?? 'http';
		$host   = $home_parsed['host'];
		$port   = isset( $home_parsed['port'] ) ? ':' . (int) $home_parsed['port'] : '';

		$path     = $parsed['path'] ?? '';
		$query    = isset( $parsed['query'] ) ? '?' . $parsed['query'] : '';
		$fragment = isset( $parsed['fragment'] ) ? '#' . $parsed['fragment'] : '';

		return esc_url_raw( $scheme . '://' . $host . $port . $path . $query . $fragment );
	}

	/**
	 * Resolve cross-site object IDs for post_type/taxonomy menu items.
	 *
	 * @param string $type   Menu item type (post_type|taxonomy|custom).
	 * @param string $object Menu item object (post type or taxonomy slug).
	 * @param array  $item   Raw item data from file.
	 * @return int Resolved local object ID or 0 if not found / not applicable.
	 */
	protected function resolve_menu_item_object_id( $type, $object, $item ) {
		$type   = (string) $type;
		$object = (string) $object;

		if ( $type === 'post_type' ) {
			return $this->resolve_post_type_object_id( $object, $item );
		}

		if ( $type === 'taxonomy' ) {
			return $this->resolve_taxonomy_object_id( $object, $item );
		}

		return 0;
	}

	/**
	 * Resolve a post ID for a post_type menu item using URL/path/title.
	 *
	 * @param string $post_type Post type slug.
	 * @param array  $item      Raw menu item data.
	 * @return int Post ID or 0.
	 */
	protected function resolve_post_type_object_id( $post_type, $item ) {
		$post_type = sanitize_key( (string) $post_type );
		if ( $post_type === '' ) {
			return 0;
		}

		$url = isset( $item['url'] ) ? (string) $item['url'] : '';
		if ( $url !== '' ) {
			$local_url = $this->rewrite_url_to_local_site( $url );
			$post_id   = url_to_postid( $local_url );
			if ( $post_id > 0 ) {
				return (int) $post_id;
			}

			$path = wp_parse_url( $local_url, PHP_URL_PATH );
			if ( is_string( $path ) ) {
				$path = trim( $path, '/' );
				if ( $path !== '' ) {
					$post = get_page_by_path( $path, OBJECT, $post_type );
					if ( $post ) {
						return (int) $post->ID;
					}
				}
			}
		}

		// Backward-compatible extra hints if exporter provides them.
		$object_path = isset( $item['object_path'] ) ? trim( (string) $item['object_path'], '/' ) : '';
		if ( $object_path !== '' ) {
			$post = get_page_by_path( $object_path, OBJECT, $post_type );
			if ( $post ) {
				return (int) $post->ID;
			}
		}

		$object_name = isset( $item['object_name'] ) ? sanitize_title( (string) $item['object_name'] ) : '';
		if ( $object_name !== '' ) {
			$post = get_page_by_path( $object_name, OBJECT, $post_type );
			if ( $post ) {
				return (int) $post->ID;
			}
		}

		$title = isset( $item['title'] ) ? (string) $item['title'] : '';
		if ( $title !== '' ) {
			$post_id_by_title = $this->find_post_id_by_title( $title, $post_type );
			if ( $post_id_by_title > 0 ) {
				return $post_id_by_title;
			}
		}

		return 0;
	}

	/**
	 * Best-effort title lookup using WP_Query (get_page_by_title() is deprecated).
	 *
	 * @param string       $title     Post title.
	 * @param string|array $post_type Post type(s).
	 * @return int Post ID or 0.
	 */
	private function find_post_id_by_title( $title, $post_type ) {
		$title = trim( (string) $title );
		if ( '' === $title ) {
			return 0;
		}

		$query = new \WP_Query(
			[
				'post_type'              => $post_type,
				'post_status'            => 'any',
				'posts_per_page'         => 10,
				's'                      => $title,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			]
		);

		if ( empty( $query->posts ) ) {
			return 0;
		}

		foreach ( $query->posts as $candidate_id ) {
			$candidate = get_post( $candidate_id );
			if ( $candidate && (string) $candidate->post_title === $title ) {
				return (int) $candidate_id;
			}
		}

		return 0;
	}

	/**
	 * Resolve a term ID for a taxonomy menu item using slug/name.
	 *
	 * @param string $taxonomy Taxonomy slug.
	 * @param array  $item     Raw menu item data.
	 * @return int Term ID or 0.
	 */
	protected function resolve_taxonomy_object_id( $taxonomy, $item ) {
		$taxonomy = sanitize_key( (string) $taxonomy );
		if ( $taxonomy === '' ) {
			return 0;
		}

		$slug = '';
		if ( isset( $item['term_slug'] ) ) {
			$slug = sanitize_title( (string) $item['term_slug'] );
		} elseif ( isset( $item['object_slug'] ) ) {
			$slug = sanitize_title( (string) $item['object_slug'] );
		}

		if ( $slug !== '' ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				return (int) $term->term_id;
			}
		}

		$name = isset( $item['term_name'] ) ? (string) $item['term_name'] : ( isset( $item['title'] ) ? (string) $item['title'] : '' );
		if ( $name !== '' ) {
			$term = get_term_by( 'name', $name, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				return (int) $term->term_id;
			}
		}

		return 0;
	}

	/**
	 * Get sample data structure
	 *
	 * @return array
	 */
	public function get_sample_data() {
		return [
			[
				'name'        => 'Main Menu',
				'slug'        => 'main-menu',
				'description' => 'Primary navigation menu',
				'menu_items'  => [
					[
						'title'            => 'Home',
						'url'              => 'https://example.com/',
						'menu_order'       => 1,
						'menu_item_parent' => 0,
						'object'           => 'page',
						'object_id'        => 1,
						'type'             => 'post_type',
						'target'           => '',
						'attr_title'       => '',
						'classes'          => [ '' ],
						'xfn'              => '',
						'description'      => '',
					],
					[
						'title'            => 'About',
						'url'              => 'https://example.com/about/',
						'menu_order'       => 2,
						'menu_item_parent' => 0,
						'object'           => 'page',
						'object_id'        => 2,
						'type'             => 'post_type',
						'target'           => '',
						'attr_title'       => '',
						'classes'          => [ '' ],
						'xfn'              => '',
						'description'      => '',
					],
				],
			],
		];
	}
}

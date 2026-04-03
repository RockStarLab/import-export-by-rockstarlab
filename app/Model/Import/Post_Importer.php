<?php
/**
 * Post Importer
 *
 * Handles importing WordPress posts, pages, and custom post types
 *
 * @package WP_AIE\Model\Import
 */

namespace WP_AIE\Model\Import;

defined( 'ABSPATH' ) || exit;

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
		return __( 'Import WordPress posts, pages, and custom post types', 'amplified-import-export' );
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
			'author_name',
			'author_email',
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
			'duplicate_mode'       => 'How to handle duplicates: skip, update, create',
			'post_status'          => 'Default post status: publish, draft, pending',
			'post_type'            => 'Post type to import as: post, page, or custom type',
			'post_author'          => 'Default author ID if not specified in data',
			'comment_status'       => 'Default comment status: open, closed',
			'ping_status'          => 'Default ping status: open, closed',
			'duplicate_check'      => 'Field to check for duplicates: post_title, post_name, ID',
			'auto_import_media'    => 'Automatically import media files from content: true, false',
			'media_duplicate_mode' => 'How to handle duplicate media: skip, create, replace',
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
	 * Set options
	 *
	 * Overrides parent to handle custom_post_type → post_type mapping
	 * when import_type is 'custom_post_types'.
	 *
	 * @param array $options Options to set.
	 */
	public function set_options( $options ) {
		// When importing custom post types, the frontend sends 'custom_post_type'
		// with the selected CPT slug. Map it to 'post_type' for the importer.
		if ( ! empty( $options['custom_post_type'] ) && empty( $options['post_type'] ) ) {
			$options['post_type'] = $options['custom_post_type'];
		}

		// Map duplicate handling options from the UI to the internal option names
		// used by this importer (keeps behavior consistent with other importers).
		//
		// UI sends: duplicate_handling and/or if_exists, plus unique_field.
		if ( isset( $options['duplicate_handling'] ) && ! isset( $options['duplicate_mode'] ) ) {
			$options['duplicate_mode'] = $options['duplicate_handling'];
		}
		if ( isset( $options['if_exists'] ) && ! isset( $options['duplicate_mode'] ) ) {
			$options['duplicate_mode'] = $options['if_exists'];
		}
		if ( isset( $options['unique_field'] ) && ! isset( $options['duplicate_check'] ) ) {
			$options['duplicate_check'] = $options['unique_field'];
		}

		// Back-compat: UI previously supported 'ignore'. That mode caused duplicate
		// rows by always creating new items; treat it as 'update'.
		if ( isset( $options['duplicate_mode'] ) && 'ignore' === $options['duplicate_mode'] ) {
			$options['duplicate_mode'] = 'update';
		}

		parent::set_options( $options );
	}

	/**
	 * Normalize flat prefixed columns into nested structures.
	 *
	 * When the auto-mapper maps CSV columns such as `taxonomy_category`,
	 * `taxonomy_post_tag`, `meta_button_group`, `acf_text_field`, etc. it
	 * keeps them as top-level keys in the prepared item.  This method
	 * collects those keys and moves their values into the `taxonomies` and
	 * `post_meta` sub-arrays that the rest of the importer expects.
	 *
	 * Rules:
	 *  - `taxonomy_<slug>`  → $item['taxonomies']['<slug>']
	 *  - `meta_<key>`       → $item['post_meta']['<key>']
	 *  - `acf_<key>`        → $item['post_meta']['<key>']   (ACF stores values as regular post meta)
	 *
	 * @param array $item Prepared item data.
	 * @return array Item with taxonomies / post_meta populated.
	 */
	private function normalize_prefixed_columns( array $item ): array {
		$taxonomy_formats = $item['_taxonomy_formats'] ?? [];
		unset( $item['_taxonomy_formats'] );

		if ( ! isset( $item['post_meta'] ) || ! is_array( $item['post_meta'] ) ) {
			$item['post_meta'] = [];
		}
		if ( ! isset( $item['taxonomies'] ) || ! is_array( $item['taxonomies'] ) ) {
			$item['taxonomies'] = [];
		}
			// Track which post_meta keys came from acf_* columns so the importer
			// can use update_field() for them (stores the ACF reference key _field_name).
			if ( ! isset( $item['_acf_field_names'] ) || ! is_array( $item['_acf_field_names'] ) ) {
				$item['_acf_field_names'] = [];
			}
			// Backup raw meta_* values that may be overridden by portable acf_* values.
			// When ACF field definitions are missing on the target site, update_field()
			// can fail; in that case we should fall back to the raw ACF storage format.
			if ( ! isset( $item['_aie_raw_post_meta'] ) || ! is_array( $item['_aie_raw_post_meta'] ) ) {
				$item['_aie_raw_post_meta'] = [];
			}

		// ── Pass 1: taxonomy_* and meta_* (lower priority) ────────────────────
		foreach ( array_keys( $item ) as $key ) {
			$value = $item[ $key ] ?? '';
			if ( '' === $value || null === $value ) {
				if ( 0 === strpos( $key, 'taxonomy_' ) || 0 === strpos( $key, 'meta_' ) ) {
					unset( $item[ $key ] );
				}
				continue;
			}

			if ( 0 === strpos( $key, 'taxonomy_' ) ) {
				$taxonomy = substr( $key, strlen( 'taxonomy_' ) );
				if ( '' !== $taxonomy && ! isset( $item['taxonomies'][ $taxonomy ] ) ) {
					$item['taxonomies'][ $taxonomy ] = [
						'terms'  => $value,
						'format' => $taxonomy_formats[ $key ] ?? 'name',
					];
				}
				unset( $item[ $key ] );
				continue;
			}

			if ( 0 === strpos( $key, 'meta_' ) ) {
				$meta_key = substr( $key, strlen( 'meta_' ) );
				if ( '' !== $meta_key && ! isset( $item['post_meta'][ $meta_key ] ) ) {
					$item['post_meta'][ $meta_key ] = $value;
				}
				unset( $item[ $key ] );
				continue;
			}
		}

		// ── Pass 2: acf_* (higher priority — overrides meta_* for same key) ───
		// acf_* columns export more portable data: URLs for images/files,
		// typed JSON {acf_type:"gallery/relation/taxonomy",...} for complex fields.
		foreach ( array_keys( $item ) as $key ) {
			$value = $item[ $key ] ?? '';
			if ( '' === $value || null === $value ) {
				if ( 0 === strpos( $key, 'acf_' ) ) {
					unset( $item[ $key ] );
				}
				continue;
			}

				if ( 0 === strpos( $key, 'acf_' ) ) {
					$meta_key = substr( $key, strlen( 'acf_' ) );
					if ( '' !== $meta_key ) {
						if ( array_key_exists( $meta_key, $item['post_meta'] ) && ! array_key_exists( $meta_key, $item['_aie_raw_post_meta'] ) ) {
							$item['_aie_raw_post_meta'][ $meta_key ] = $item['post_meta'][ $meta_key ];
						}
						// Always override meta_* values: acf_* data is more portable
						$item['post_meta'][ $meta_key ]    = $value;
						$item['_acf_field_names'][ $meta_key ] = true;
					}
				unset( $item[ $key ] );
				continue;
			}
		}

		return $item;
	}

	/**
	 * Import single post
	 *
	 * @param array $item  Post data
	 * @param int   $index Item index
	 * @return int|string|WP_Error Post ID, 'skipped', 'updated', or WP_Error
	 */
	public function import_item( $item, $index ) {
		// Sanitize data
		$item = $this->sanitize_item( $item );

		// Normalize flat prefixed columns produced by the auto-mapper
		// (e.g. taxonomy_category, meta_*, acf_*) into nested structures.
		$item = $this->normalize_prefixed_columns( $item );

			// Check for duplicates
			$existing_post = $this->find_existing_post( $item );
			$source_id     = isset( $item['_aie_source_id'] ) ? absint( $item['_aie_source_id'] ) : 0;

			if ( $existing_post ) {
			$duplicate_mode = $this->get_option( 'duplicate_mode', 'skip' );

			if ( 'skip' === $duplicate_mode ) {
				$this->record_source_id_map( $source_id, $existing_post->ID );
				return 'skipped';
			}

			if ( 'update' === $duplicate_mode ) {
				$result = $this->update_post( $existing_post->ID, $item );
				if ( ! is_wp_error( $result ) ) {
					$this->record_source_id_map( $source_id, $existing_post->ID );
				}
				return $result;
			}

				// 'create' mode - fall through to create new post
			}

			// No existing item found — honor "If No Match Found" option.
			$if_not_exists = $this->get_option( 'if_not_exists', 'create' );
			if ( 'skip' === $if_not_exists ) {
				return 'skipped';
			}

			// Create new post
			$created = $this->create_post( $item );
			if ( is_int( $created ) && $created > 0 ) {
				$this->record_source_id_map( $source_id, $created );
			}
		return $created;
	}

	/**
	 * Persist a source->target ID mapping for this import job.
	 *
	 * Used for post-import fixups like resolving cross-site post_parent IDs.
	 *
	 * @param int $source_id Source site post ID from the import file.
	 * @param int $target_id Target site post ID created/updated.
	 * @return void
	 */
	private function record_source_id_map( $source_id, $target_id ) {
		$source_id = absint( $source_id );
		$target_id = absint( $target_id );

		if ( $source_id <= 0 || $target_id <= 0 ) {
			return;
		}

		if ( empty( $this->job_id ) ) {
			// No job context available; skip persisting.
			return;
		}

		$key = $this->get_job_id_map_key();
		$map = get_transient( $key );
		if ( ! is_array( $map ) ) {
			$map = [];
		}

		$map[ (string) $source_id ] = $target_id;

		// Keep around long enough for the final batch to run fixups.
		set_transient( $key, $map, DAY_IN_SECONDS );
	}

	/**
	 * Get the transient key for the current job's ID map.
	 *
	 * @return string
	 */
	private function get_job_id_map_key() {
		return 'aie_import_post_id_map_' . absint( $this->job_id );
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
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			];

			$posts = get_posts( $args );
			return ! empty( $posts ) ? get_post( $posts[0] ) : null;
		}

			// Check by post_title
			if ( 'post_title' === $check_field && ! empty( $item['post_title'] ) ) {
				global $wpdb;

				$post_type = (string) $this->get_option( 'post_type', 'post' );
				$title     = (string) $item['post_title'];

				// WP_Query does not support a strict "title" argument in get_posts().
				// Use a direct, exact-match lookup so "update by Title" actually works.
				$post_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Exact match lookup.
					$wpdb->prepare(
						"SELECT ID
						 FROM {$wpdb->posts}
						 WHERE post_type = %s
						   AND post_title = %s
						   AND post_status <> 'trash'
						 ORDER BY ID DESC
						 LIMIT 1",
						$post_type,
						$title
					)
				);

				return $post_id ? get_post( (int) $post_id ) : null;
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

		// wp_insert_post() expects slashed data; without this, backslashes can be
		// stripped from content (e.g. ASCII art like "(\_/)" in markup pages).
		$post_id = wp_insert_post( wp_slash( $post_data ), true );

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

			// Import post meta
			if ( ! empty( $item['post_meta'] ) ) {
				$this->import_post_meta( $post_id, $item['post_meta'], $item['_acf_field_names'] ?? [], $item['_aie_raw_post_meta'] ?? [] );
			}

		// Auto-import media from ACF fields if enabled
		if ( $this->get_option( 'auto_import_media', false ) ) {
			$this->auto_import_acf_media( $post_id, $item );
		}

			// Import taxonomies
			if ( ! empty( $item['taxonomies'] ) ) {
				$this->import_taxonomies( $post_id, $item['taxonomies'] );
			}

			// Import featured image (honor Step 5 "Automatically Import Media Files")
			if ( $this->get_option( 'auto_import_media', false ) && ! empty( $item['featured_image'] ) ) {
				$this->import_featured_image( $post_id, $item['featured_image'] );
			}

		// Auto-import media from content if enabled
		if ( $this->get_option( 'auto_import_media', false ) && ! empty( $item['post_content'] ) ) {
			$this->auto_import_content_media( $post_id, $item['post_content'] );
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

		// wp_update_post() expects slashed data; keep backslashes intact.
		$result = wp_update_post( wp_slash( $post_data ), true );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

			// Update post meta
			if ( ! empty( $item['post_meta'] ) ) {
				$this->import_post_meta( $post_id, $item['post_meta'], $item['_acf_field_names'] ?? [], $item['_aie_raw_post_meta'] ?? [] );
			}

		// Auto-import media from ACF fields if enabled
		if ( $this->get_option( 'auto_import_media', false ) ) {
			$this->auto_import_acf_media( $post_id, $item );
		}

			// Update taxonomies
			if ( ! empty( $item['taxonomies'] ) ) {
				$this->import_taxonomies( $post_id, $item['taxonomies'] );
			}

			// Update featured image (honor Step 5 "Automatically Import Media Files")
			if ( $this->get_option( 'auto_import_media', false ) && ! empty( $item['featured_image'] ) ) {
				$this->import_featured_image( $post_id, $item['featured_image'] );
			}

		// Auto-import media from content if enabled
		if ( $this->get_option( 'auto_import_media', false ) && ! empty( $item['post_content'] ) ) {
			$this->auto_import_content_media( $post_id, $item['post_content'] );
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

		// Handle author_name or author_email if post_author is not set
		if ( empty( $post_data['post_author'] ) ) {
			if ( ! empty( $item['author_email'] ) ) {
				$user = get_user_by( 'email', $item['author_email'] );
				if ( $user ) {
					$post_data['post_author'] = $user->ID;
				}
			} elseif ( ! empty( $item['author_name'] ) ) {
				$user = get_user_by( 'login', $item['author_name'] );
				if ( $user ) {
					$post_data['post_author'] = $user->ID;
				}
			}
		}

		return array_merge( $defaults, $post_data );
	}

	/**
	 * Import post meta
	 *
	 * @param int   $post_id   Post ID
		 * @param array $meta      Meta data (key => value)
		 * @param array $acf_keys  Keys that originated from acf_* CSV columns.
		 *                         When ACF is active, update_field() is used for these
		 *                         keys so the ACF reference meta (_field_name) is stored
		 *                         automatically and the field is recognised on this site.
		 * @param array $raw_meta_backup Raw meta_* values overridden by acf_* (key => value).
		 */
		private function import_post_meta( $post_id, $meta, $acf_keys = [], $raw_meta_backup = [] ) {
			if ( ! is_array( $meta ) ) {
				return;
			}

		$has_acf = function_exists( 'update_field' );

		// When importing ACF fields via update_field(), ACF will manage its own
		// internal meta keys for nested structures (repeater/flexible/group).
		// If we ALSO import the flat meta_* columns for those nested keys, we can
		// accidentally overwrite what ACF just wrote.
		//
		// Build a skip list for nested meta keys that should be owned by ACF.
		$skip_meta_key_patterns = [];
		$skip_meta_keys         = [];
		if ( $has_acf && is_array( $acf_keys ) ) {
			foreach ( array_keys( $acf_keys ) as $acf_field_name ) {
				// If ACF can't resolve the field on this site, don't skip anything:
				// update_field() will likely fail and we still want to import flat meta.
				if ( ! function_exists( 'get_field_object' ) ) {
					// Fall through to DB lookup below.
				}

				// Avoid formatting values while importing; some field types (e.g. icon_picker)
				// can fatal if the stored value is not yet in the expected shape.
				$field_object = function_exists( 'get_field_object' ) ? get_field_object( $acf_field_name, $post_id, false, false ) : null;
				$field_type   = is_array( $field_object ) ? ( $field_object['type'] ?? '' ) : '';

				// Fallback: resolve field type from ACF field definitions in DB.
				// This avoids cases where get_field_object() returns a wrong nested field
				// (name ambiguity) or returns null before ACF reference meta exists.
				$db_field = null;
				if ( '' === $field_type ) {
					$db_field = $this->acf_db_find_field_by_name( $acf_field_name );
					if ( $db_field && ! empty( $db_field['type'] ) ) {
						$field_type = (string) $db_field['type'];
					}
				}

				// Repeater/flexible content sub-field meta keys look like:
				//   {field}_{row}_{subfield}
				if ( in_array( $field_type, [ 'repeater', 'flexible_content' ], true ) ) {
					$prefix = preg_quote( $acf_field_name, '/' );
					$skip_meta_key_patterns[] = '/^' . $prefix . '_\\d+_/';
					continue;
				}

				// Group sub-field meta keys look like:
				//   {field}_{subfield}
				if ( 'group' === $field_type ) {
					$sub_fields = is_array( $field_object ) ? ( $field_object['sub_fields'] ?? null ) : null;
					if ( ! is_array( $sub_fields ) && $db_field && ! empty( $db_field['sub_field_names'] ) ) {
						$sub_fields = array_map( fn( $n ) => [ 'name' => $n ], (array) $db_field['sub_field_names'] );
					}

					if ( is_array( $sub_fields ) ) {
						foreach ( $sub_fields as $sub ) {
							if ( empty( $sub['name'] ) ) {
								continue;
							}
							$skip_meta_keys[] = $acf_field_name . '_' . $sub['name'];
						}
					}
				}
			}
		}

			foreach ( $meta as $key => $value ) {
				// ACF stores field reference meta keys as "_<field_name>" = "field_XXXX".
				// Those keys are site-specific and must NOT be imported from another site,
				// otherwise they break get_field_object()/update_field() resolution.
				//
				// Detect these keys by the standard ACF pairing pattern: if "_foo" exists
				// alongside "foo" in the same import payload, "_foo" is almost certainly an
				// ACF reference key and should be skipped.
				if ( $has_acf && is_string( $key ) && '' !== $key && '_' === $key[0] ) {
					$plain = substr( $key, 1 );
					if ( '' !== $plain && array_key_exists( $plain, $meta ) ) {
						continue;
					}
				}

				// Skip nested meta keys that should be written by ACF itself.
				if ( ! empty( $skip_meta_keys ) && in_array( $key, $skip_meta_keys, true ) ) {
					continue;
				}
			// Do not skip keys that end with "_" (empty sub-field names can produce
			// meta keys like "repeater_0_group_" that ACF won't populate via update_field()).
			if ( ! empty( $skip_meta_key_patterns ) && is_string( $key ) && '' !== $key && '_' !== substr( $key, -1 ) ) {
				$should_skip = false;
				foreach ( $skip_meta_key_patterns as $pattern ) {
					if ( preg_match( $pattern, (string) $key ) ) {
						$should_skip = true;
						break;
					}
				}
				if ( $should_skip ) {
					continue;
				}
			}

			// The CSV stores complex ACF values (arrays/objects) in two formats:
			//
			// 1. PHP serialized — "a:2:{s:4:"type";...}" — written by the ACF exporter
			//    branch that calls maybe_serialize() for array values without 'url'/'ID'.
			//    update_post_meta() also calls maybe_serialize() internally, so passing a
			//    serialized string produces DOUBLE serialization.
			//
			// 2. JSON — '{"type":"dashicons","value":"..."}' or the typed format produced by
			//    our improved exporter: {"acf_type":"gallery/relation/taxonomy","values":[...]}
			//
			// Restore both formats to native PHP types, then resolve ACF-specific structures.
			if ( is_string( $value ) && '' !== $value ) {
				if ( is_serialized( $value ) ) {
					// PHP serialized → array / scalar
					$value = maybe_unserialize( $value );
				} elseif ( ( '{' === $value[0] || '[' === $value[0] ) ) {
					// Looks like JSON — try to decode.
					$decoded = json_decode( $value, true );
					if ( null !== $decoded ) {
						$value = $decoded;
					}
				}
			}

			// Resolve ACF-specific structures (typed JSON, media URLs, etc.)
			$value = $this->resolve_acf_meta_value( $value, $post_id );

			// For ACF fields, use update_field() so ACF stores the reference key
			// (_field_name = 'field_abc123') automatically.  This makes the field
			// visible and editable in the WordPress admin on this site.
			//
			// Prefer detecting ACF fields by DB definitions as well (not only by
			// origin from acf_* columns). Auto-map can map ACF columns into meta_*
			// targets; we still want to write those values via ACF when possible.
			$acf_field_key = '';
			$db_field      = null;

			if ( $has_acf ) {
				// Avoid formatting values while importing; see note above.
				$field_object = function_exists( 'get_field_object' ) ? get_field_object( $key, $post_id, false, false ) : null;
				if ( is_array( $field_object ) && ! empty( $field_object['key'] ) ) {
					$acf_field_key = (string) $field_object['key'];
				}

				if ( '' === $acf_field_key ) {
					$db_field = $this->acf_db_find_field_by_name( (string) $key );
					if ( $db_field && ! empty( $db_field['key'] ) ) {
						$acf_field_key = (string) $db_field['key'];
					}
				}
			}

			$should_update_as_acf = $has_acf && ( isset( $acf_keys[ $key ] ) || ( is_array( $db_field ) && ! empty( $db_field['key'] ) ) || ( '' !== $acf_field_key ) );

			if ( $should_update_as_acf ) {
				// Prefer updating by ACF FIELD KEY (field_abc123) — it is more reliable
				// than updating by field name, especially for complex/nested fields.
				$updated = ( '' !== $acf_field_key && 0 === strpos( $acf_field_key, 'field_' ) )
					? update_field( $acf_field_key, $value, $post_id )
					: update_field( $key, $value, $post_id );

					// If the field group doesn't exist on the target site (or ACF can't
					// resolve the field by name), update_field() can fail and write nothing.
					// Fall back to raw meta so the data isn't silently lost.
					if ( false === $updated ) {
						// Prefer the original meta_* value when available. This preserves
						// ACF's native storage format for complex fields (repeaters/flexible)
						// even when field definitions are missing on the target site.
						if ( is_array( $raw_meta_backup ) && array_key_exists( $key, $raw_meta_backup ) ) {
							update_post_meta( $post_id, $key, $raw_meta_backup[ $key ] );
						} else {
							update_post_meta( $post_id, $key, $value );
						}
					}

					// Some installations have ACF enabled but no field group definitions
					// on the target site. In that case update_field() can return true while
					// simply storing the portable array into the main meta key (e.g.
					// `repeater` becomes an array of rows instead of a row-count integer).
					//
					// Detect this "stored portable array" scenario and prefer the original
					// meta_* value when available.
					if ( true === $updated && is_array( $raw_meta_backup ) && array_key_exists( $key, $raw_meta_backup ) && is_array( $value ) ) {
						$first_val = reset( $value );
						if ( is_array( $first_val ) ) {
							update_post_meta( $post_id, $key, $raw_meta_backup[ $key ] );
						}
					}

					// Safety net: some complex fields (repeaters in particular) can end up
					// stored as a serialized array in a single meta key when they were not
					// properly recognised by ACF. Detect and re-apply update_field().
					$field_type = '';
				if ( isset( $field_object ) && is_array( $field_object ) && ! empty( $field_object['type'] ) ) {
					$field_type = (string) $field_object['type'];
				} elseif ( is_array( $db_field ) && ! empty( $db_field['type'] ) ) {
					$field_type = (string) $db_field['type'];
				}

					if ( in_array( $field_type, [ 'repeater', 'flexible_content' ], true ) ) {
						$stored = get_post_meta( $post_id, (string) $key, true );

						$looks_like_rows = false;
						if ( 'repeater' === $field_type ) {
							// Correct repeater storage is an integer row count; arrays indicate failure.
							$looks_like_rows = is_array( $stored );
						} elseif ( 'flexible_content' === $field_type ) {
							// Correct flexible content storage is an array of layout names (strings).
							// Arrays of row objects indicate failure.
							$first = is_array( $stored ) ? reset( $stored ) : null;
							$looks_like_rows = is_array( $first );
						}

						if ( $looks_like_rows ) {
							$force_key = ( '' !== $acf_field_key && 0 === strpos( $acf_field_key, 'field_' ) ) ? $acf_field_key : $key;
							update_field( $force_key, $value, $post_id );
						}
					}
				} else {
					if ( is_array( $raw_meta_backup ) && array_key_exists( $key, $raw_meta_backup ) ) {
						update_post_meta( $post_id, $key, $raw_meta_backup[ $key ] );
					} else {
						update_post_meta( $post_id, $key, $value );
					}
				}
			}
		}

	/**
	 * Resolve an ACF meta value to its importable form.
	 *
	 * Handles three cases after unserialize/JSON-decode:
	 *
	 *  1. Typed JSON array from our improved exporter:
	 *     {"acf_type":"gallery",  "values":["url1","url2",...]}  → downloads each URL, returns int[]
	 *     {"acf_type":"relation", "values":["slug1","slug2",...]} → looks up post IDs by slug
	 *     {"acf_type":"taxonomy", "values":["Name1",...], "taxonomy":"cat"} → finds/creates term IDs
	 *
	 *  2. Single string that is a media-file URL (jpg/png/pdf/etc.) → downloads, returns int ID
	 *
	 *  3. Everything else → returned unchanged.
	 *
	 * @param mixed $value   Already-decoded meta value.
	 * @param int   $post_id Post ID (used as attachment parent).
	 * @return mixed Resolved value ready for update_post_meta().
	 */
		private function resolve_acf_meta_value( $value, int $post_id ) {
			// Some import pipelines may pass JSON as raw strings (e.g. '["a","b"]' or
			// '{"acf_type":"taxonomy",...}'). Decode here as a safety net so all code paths
			// (including auto-import media and direct meta updates) can resolve typed ACF
			// values into native PHP structures.
			if ( is_string( $value ) && '' !== $value ) {
				$trimmed = ltrim( $value );
				if ( '' !== $trimmed && ( '{' === $trimmed[0] || '[' === $trimmed[0] ) ) {
					$decoded = json_decode( $trimmed, true );
					if ( is_array( $decoded ) ) {
						$value = $decoded;
					}
				}
			}

			// ── 0. Gallery shortcode tokens (portable) ───────────────────────────
			if ( is_string( $value ) && '' !== $value && false !== strpos( $value, '[[AIE:' ) ) {
				$value = $this->resolve_gallery_shortcode_tokens( $value, $post_id );
			}

			$auto_import_media    = (bool) $this->get_option( 'auto_import_media', false );
			$media_duplicate_mode = (string) $this->get_option( 'media_duplicate_mode', 'skip' );

			// ── 1. Typed JSON from our improved exporter ──────────────────────────
			if ( is_array( $value ) && isset( $value['acf_type'] ) ) {
				$type   = $value['acf_type'];
				$values = $value['values'] ?? [];

				switch ( $type ) {
					case 'gallery':
						if ( ! $auto_import_media ) {
							return $value;
						}

						// Array of image/file URLs → download each and collect new attachment IDs.
						$ids = [];
						foreach ( $values as $url ) {
							if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
								continue;
							}

						// Avoid downloading non-media URLs (HTML pages, oembed, etc.).
						$ext = strtolower( pathinfo( wp_parse_url( $url, PHP_URL_PATH ) ?? '', PATHINFO_EXTENSION ) );
						$media_exts = [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'mp4', 'mov', 'avi', 'wmv', 'mp3', 'wav', 'ogg', 'zip' ];
							if ( '' === $ext || ! in_array( $ext, $media_exts, true ) ) {
								continue;
							}

							$id = $this->import_media_for_acf( $url, $post_id, $media_duplicate_mode );
							if ( $id ) {
								$ids[] = (int) $id;
							}
						}
						return $ids;

					case 'relation':
					// Array of post slugs OR attachment URLs:
					// - slug  → look up post ID by post_name (including attachments)
					// - URL   → download media and return attachment ID
					$ids = [];
						foreach ( $values as $raw ) {
							$raw = (string) $raw;

							// URL values are assumed to point to media (most commonly when a
							// relationship/post_object field references an attachment).
							if ( '' !== $raw && filter_var( $raw, FILTER_VALIDATE_URL ) ) {
								$ext = strtolower( pathinfo( wp_parse_url( $raw, PHP_URL_PATH ) ?? '', PATHINFO_EXTENSION ) );
								$media_exts = [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'mp4', 'mov', 'avi', 'wmv', 'mp3', 'wav', 'ogg', 'zip' ];
								if ( $auto_import_media && '' !== $ext && in_array( $ext, $media_exts, true ) ) {
									$new_id = $this->import_media_for_acf( $raw, $post_id, $media_duplicate_mode );
									if ( $new_id ) {
										$ids[] = (int) $new_id;
									}
								}
								// Do not treat a URL as a post slug.
								continue;
							}

							$slug = sanitize_title( $raw );
							if ( '' === $slug ) {
								continue;
						}

						$posts = get_posts(
							[
								'name'           => $slug,
								'post_type'      => 'any',
								'post_status'    => 'any',
								'posts_per_page' => 1,
								'fields'         => 'ids',
							]
						);
						if ( ! empty( $posts ) ) {
							$ids[] = (int) $posts[0];
						}
					}
					if ( ! empty( $value['single'] ) ) {
						return ! empty( $ids ) ? $ids[0] : '';
					}
					return $ids;

				case 'taxonomy':
					// Array of term names → find or create terms, return IDs.
					$taxonomy = $value['taxonomy'] ?? '';
					$ids      = [];
					foreach ( $values as $name ) {
						$name = trim( (string) $name );
						if ( '' === $name ) {
							continue;
						}
						// Search across all taxonomies if none specified
						if ( $taxonomy ) {
							$term = get_term_by( 'name', $name, $taxonomy );
							if ( ! $term ) {
								$result = wp_insert_term( $name, $taxonomy );
								$term   = ! is_wp_error( $result ) ? get_term( $result['term_id'] ) : null;
							}
						} else {
							// No taxonomy hint — try a broad search
							$terms = get_terms( [ 'name' => $name, 'hide_empty' => false ] );
							$term  = ! is_wp_error( $terms ) && ! empty( $terms ) ? $terms[0] : null;
						}
						if ( $term ) {
							$ids[] = (int) $term->term_id;
						}
					}
					if ( ! empty( $value['single'] ) ) {
						return ! empty( $ids ) ? $ids[0] : '';
					}
					return $ids;

					case 'user':
					// Array of user logins → look up (or create) users on this site.
					$ids = [];
					foreach ( $values as $login ) {
						$login = trim( (string) $login );
						if ( '' === $login ) {
							continue;
						}

						$user = get_user_by( 'login', $login );
						if ( ! $user && false !== strpos( $login, '@' ) ) {
							$user = get_user_by( 'email', $login );
						}

						if ( ! $user ) {
							// Best-effort: create the missing user so the ACF user field
							// remains functional after cross-site import.
							$sanitized_login = sanitize_user( $login, true );
							if ( '' === $sanitized_login ) {
								continue;
							}

							$email = $sanitized_login . '@example.invalid';
							if ( email_exists( $email ) ) {
								$email = $sanitized_login . '+' . wp_generate_password( 6, false, false ) . '@example.invalid';
							}

							$user_id = wp_insert_user(
								[
									'user_login' => $sanitized_login,
									'user_pass'  => wp_generate_password( 20, true, true ),
									'user_email' => $email,
									'role'       => 'subscriber',
								]
							);

							if ( ! is_wp_error( $user_id ) ) {
								$user = get_user_by( 'id', (int) $user_id );
							}
						}

						if ( $user ) {
							$ids[] = (int) $user->ID;
						}
					}
					if ( ! empty( $value['single'] ) ) {
						return ! empty( $ids ) ? $ids[0] : '';
					}
					return $ids;
				}
			}

			// ── 1b. ACF attachment array (image/file return_format=array) ─────────
			if ( is_array( $value ) && isset( $value['url'] ) && ( isset( $value['ID'] ) || isset( $value['id'] ) ) ) {
				if ( ! $auto_import_media ) {
					return $value;
				}

				$url = $value['url'];
				$id  = $value['ID'] ?? $value['id'];
				if ( is_string( $url ) && filter_var( $url, FILTER_VALIDATE_URL ) && is_numeric( $id ) ) {
					$ext = strtolower( pathinfo( wp_parse_url( $url, PHP_URL_PATH ) ?? '', PATHINFO_EXTENSION ) );
					$media_exts = [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'mp4', 'mov', 'avi', 'wmv', 'mp3', 'wav', 'ogg', 'zip' ];
					if ( '' !== $ext && in_array( $ext, $media_exts, true ) ) {
						$new_id = $this->import_media_for_acf( $url, $post_id, $media_duplicate_mode );
						if ( $new_id ) {
							return (int) $new_id;
						}
					}
				}
			}

		// ── 1c. Recurse into nested arrays (repeaters/groups/flexible content) ─
		if ( is_array( $value ) ) {
			$out = [];
			foreach ( $value as $k => $v ) {
				$out[ $k ] = $this->resolve_acf_meta_value( $v, $post_id );
			}
			return $out;
		}

			// ── 2. Single string that looks like a downloadable media URL ─────────
			// Applies to ACF image and file fields exported as bare URLs
			// (e.g. acf_image_field = 'http://source.local/.../photo.jpg').
			// We detect media by file extension to avoid downloading page/oembed URLs.
			if ( $auto_import_media && is_string( $value ) && '' !== $value && filter_var( $value, FILTER_VALIDATE_URL ) ) {
				$ext = strtolower( pathinfo( wp_parse_url( $value, PHP_URL_PATH ) ?? '', PATHINFO_EXTENSION ) );
				$media_exts = [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'mp4', 'mov', 'avi', 'wmv', 'mp3', 'wav', 'ogg', 'zip' ];
				if ( in_array( $ext, $media_exts, true ) ) {
					$id = $this->import_media_for_acf( $value, $post_id, $media_duplicate_mode );
					if ( $id ) {
						return (int) $id;
					}
				}
			}

			return $value;
		}

	/**
	 * Resolve exported gallery shortcode tokens back into `[gallery ids="..."]`.
	 *
	 * Tokens are produced by the exporter for WYSIWYG/text fields and look like:
	 *   [[AIE:<base64(json)>]]
	 * with JSON payload:
	 *   { "acf_type":"gallery_shortcode", "shortcode":"[gallery ids=\"1,2\"]", "urls":[...] }
	 *
	 * When auto_import_media is enabled, the URLs are downloaded and the shortcode
	 * is reconstructed with the new attachment IDs.
	 *
	 * @param string $value   String containing tokens.
	 * @param int    $post_id Post ID (attachment parent).
	 * @return string
	 */
		private function resolve_gallery_shortcode_tokens( string $value, int $post_id ): string {
			$pattern = '/\\[\\[AIE:([A-Za-z0-9+\\/=]+)\\]\\]/';

			$media_duplicate_mode = (string) $this->get_option( 'media_duplicate_mode', 'skip' );

			return preg_replace_callback(
				$pattern,
				function ( array $m ) use ( $post_id, $media_duplicate_mode ) {
					$blob = $m[1] ?? '';
					if ( '' === $blob ) {
						return $m[0] ?? '';
					}

				$json = base64_decode( $blob, true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
				if ( false === $json || '' === $json ) {
					return $m[0] ?? '';
				}

				$payload = json_decode( $json, true );
				if ( ! is_array( $payload ) || ( $payload['acf_type'] ?? '' ) !== 'gallery_shortcode' ) {
					return $m[0] ?? '';
				}

				$shortcode = (string) ( $payload['shortcode'] ?? '' );
				$urls      = $payload['urls'] ?? [];
				if ( '' === $shortcode || ! is_array( $urls ) ) {
					return $m[0] ?? '';
				}

				// If media import is disabled, keep the original shortcode (source IDs).
				if ( ! $this->get_option( 'auto_import_media', false ) ) {
					return $shortcode;
				}

					$new_ids = [];
					foreach ( $urls as $url ) {
						if ( ! is_string( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
							continue;
						}
						$id = $this->import_media_for_acf( $url, $post_id, $media_duplicate_mode );
						if ( $id ) {
							$new_ids[] = (int) $id;
						}
					}

				if ( empty( $new_ids ) ) {
					return $shortcode;
				}

				$ids_str = implode( ',', $new_ids );

				// Replace ids attribute while preserving quote style if present.
				$updated = preg_replace(
					'/\\bids=(["\'])([^"\']*)\\1/i',
					'ids=${1}' . $ids_str . '${1}',
					$shortcode
				);

				return is_string( $updated ) && '' !== $updated ? $updated : $shortcode;
			},
			$value
		);
	}

		/**
		 * Find an ACF field definition in DB by its field name (post_excerpt).
		 *
		 * Used as a fallback when get_field_object() can't reliably resolve a field
		 * on a new post (before reference meta exists) or when field names are ambiguous.
		 *
		 * @param string $field_name ACF field name.
		 * @return array|null { id, key, type, sub_field_names? } or null.
		 */
		private function acf_db_find_field_by_name( string $field_name ): ?array {
			static $cache = [];

			if ( array_key_exists( $field_name, $cache ) ) {
				return $cache[ $field_name ];
			}

			global $wpdb;

			$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reading ACF field definitions.
				$wpdb->prepare(
					"SELECT ID, post_name, post_content
					 FROM {$wpdb->posts}
					 WHERE post_type = 'acf-field' AND post_excerpt = %s
					 ORDER BY ID DESC
					 LIMIT 1",
					$field_name
				),
				ARRAY_A
			);

				if ( ! $row ) {
					// Not stored in DB. Many setups register fields via local JSON/PHP
					// (acf_add_local_field_group) so there may be no acf-field posts.
					// Fall back to scanning runtime field groups.
					$runtime = $this->acf_runtime_find_field_by_name( $field_name );
					$cache[ $field_name ] = $runtime;
					return $runtime;
				}

			$settings = @unserialize( (string) $row['post_content'] );
			if ( ! is_array( $settings ) ) {
				$settings = [];
			}

			$type = (string) ( $settings['type'] ?? '' );

			$result = [
				'id'              => (int) ( $row['ID'] ?? 0 ),
				'key'             => (string) ( $row['post_name'] ?? '' ),
				'type'            => $type,
				'sub_field_names' => [],
			];

			if ( 'group' === $type && $result['id'] > 0 ) {
				$names = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Reading ACF field definitions.
					$wpdb->prepare(
						"SELECT post_excerpt
						 FROM {$wpdb->posts}
						 WHERE post_type = 'acf-field' AND post_parent = %d
						 ORDER BY menu_order ASC, ID ASC",
						$result['id']
					)
				);
				if ( is_array( $names ) ) {
					$result['sub_field_names'] = array_values( array_filter( array_map( 'strval', $names ) ) );
				}
			}

				$cache[ $field_name ] = $result;
				return $result;
			}

			/**
			 * Find an ACF field definition registered at runtime by field name.
			 *
			 * Supports installations where ACF fields are registered via local JSON/PHP
			 * and are not present as `acf-field` posts in the database.
			 *
			 * @param string $field_name ACF field name.
			 * @return array|null { id, key, type, sub_field_names? } or null.
			 */
			private function acf_runtime_find_field_by_name( string $field_name ): ?array {
				static $index = null;

				if ( null === $index ) {
					$index = [];

					if ( function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ) ) {
						$groups = acf_get_field_groups();

						$walk = function ( $fields ) use ( &$walk, &$index ) {
							if ( ! is_array( $fields ) ) {
								return;
							}

							foreach ( $fields as $field ) {
								if ( ! is_array( $field ) ) {
									continue;
								}

								$name = (string) ( $field['name'] ?? '' );
								if ( '' !== $name && ! isset( $index[ $name ] ) ) {
									$index[ $name ] = $field;
								}

								$type = (string) ( $field['type'] ?? '' );
								if ( in_array( $type, [ 'repeater', 'group' ], true ) && ! empty( $field['sub_fields'] ) ) {
									$walk( $field['sub_fields'] );
								}

								if ( 'flexible_content' === $type && ! empty( $field['layouts'] ) && is_array( $field['layouts'] ) ) {
									foreach ( $field['layouts'] as $layout ) {
										if ( is_array( $layout ) && ! empty( $layout['sub_fields'] ) ) {
											$walk( $layout['sub_fields'] );
										}
									}
								}
							}
						};

						foreach ( $groups as $group ) {
							$fields = acf_get_fields( $group );
							$walk( $fields );
						}
					}
				}

				if ( ! isset( $index[ $field_name ] ) ) {
					return null;
				}

				$field = $index[ $field_name ];

				$type = (string) ( $field['type'] ?? '' );

				$result = [
					'id'              => 0,
					'key'             => (string) ( $field['key'] ?? '' ),
					'type'            => $type,
					'sub_field_names' => [],
				];

				if ( 'group' === $type && ! empty( $field['sub_fields'] ) && is_array( $field['sub_fields'] ) ) {
					$result['sub_field_names'] = array_values(
						array_filter(
							array_map(
								fn( $sub ) => is_array( $sub ) ? (string) ( $sub['name'] ?? '' ) : '',
								$field['sub_fields']
							)
						)
					);
				}

				return $result;
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

		foreach ( $taxonomies as $taxonomy => $terms_data ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			// Support both plain value and enriched [ 'terms' => ..., 'format' => ... ] from normalize_prefixed_columns.
			$format = 'name';
			if ( is_array( $terms_data ) && isset( $terms_data['terms'] ) ) {
				$format     = $terms_data['format'] ?? 'name';
				$terms_data = $terms_data['terms'];
			}

			// Terms can be array of IDs, names, or slugs
			if ( is_string( $terms_data ) ) {
				$terms_data = array_map( 'trim', explode( ',', $terms_data ) );
			}

			// Remove empty values that come from empty CSV cells
			$terms_data = array_filter( $terms_data, fn( $t ) => '' !== (string) $t );

			if ( empty( $terms_data ) ) {
				continue;
			}

			// Resolve terms by the declared format so we always pass IDs to
			// wp_set_object_terms (most reliable approach).
			$term_ids = [];
			foreach ( $terms_data as $term_value ) {
				$term_value = trim( (string) $term_value );
				if ( '' === $term_value ) {
					continue;
				}

				switch ( $format ) {
					case 'id':
						$term_ids[] = (int) $term_value;
						break;

					case 'slug':
						$term = get_term_by( 'slug', $term_value, $taxonomy );
						if ( $term ) {
							$term_ids[] = $term->term_id;
						} else {
							// Create term with this slug
							$result = wp_insert_term( $term_value, $taxonomy, [ 'slug' => $term_value ] );
							if ( ! is_wp_error( $result ) ) {
								$term_ids[] = $result['term_id'];
							}
						}
						break;

					default: // 'name' and fallback
						$term = get_term_by( 'name', $term_value, $taxonomy );
						if ( $term ) {
							$term_ids[] = $term->term_id;
						} else {
							// Create term with this name
							$result = wp_insert_term( $term_value, $taxonomy );
							if ( ! is_wp_error( $result ) ) {
								$term_ids[] = $result['term_id'];
							}
						}
						break;
				}
			}

			if ( ! empty( $term_ids ) ) {
				wp_set_object_terms( $post_id, $term_ids, $taxonomy );
			}
		}
	}

	/**
	 * Import featured image
	 *
	 * @param int        $post_id Post ID
	 * @param string|int $image   Image URL, path, or attachment ID
	 */
		private function import_featured_image( $post_id, $image ) {
			// Already a local attachment ID
			if ( is_numeric( $image ) && get_post( $image ) ) {
				set_post_thumbnail( $post_id, $image );
				return;
			}

			// URL — download and create attachment
			if ( is_string( $image ) && filter_var( $image, FILTER_VALIDATE_URL ) ) {
				$media_duplicate_mode = $this->get_option( 'media_duplicate_mode', 'skip' );
				$attachment_id        = $this->import_media_for_acf( $image, $post_id, $media_duplicate_mode );

				if ( $attachment_id ) {
					set_post_thumbnail( $post_id, (int) $attachment_id );
				} else {
					$this->log_warning( sprintf( 'Failed to import featured image from URL: %s', $image ) );
				}
			}
		}

	/**
	 * Auto-import media from ACF fields
	 * Processes ACF image, gallery, and file fields including nested repeaters and flexible content
	 *
	 * @param int   $post_id Post ID
	 * @param array $item    Import item data
	 */
	private function auto_import_acf_media( $post_id, $item ) {
		// Check if ACF is active
		if ( ! function_exists( 'get_field_object' ) ) {
			return;
		}

		$media_duplicate_mode = $this->get_option( 'media_duplicate_mode', 'skip' );
		$imported_count = 0;
		$skipped_count = 0;
		
		// Get all post meta for this post
		$all_meta = get_post_meta( $post_id );
		
		foreach ( $all_meta as $meta_key => $meta_values ) {
			// Skip ACF reference keys (they start with _)
			if ( strpos( $meta_key, '_' ) === 0 ) {
				continue;
			}
			
			$meta_value = $meta_values[0];
			
			// Try to get ACF field object to determine type
			// We only need field settings (type/sub_fields). Formatting can fatal for some
			// field types when values are in a portable form (e.g. strings for icon_picker).
			$field_object = get_field_object( $meta_key, $post_id, false, false );
			
			if ( ! $field_object ) {
				// Not an ACF field or field not found
				continue;
			}
			
			$field_type = $field_object['type'] ?? '';

			// Safety net: if some ACF fields were stored as portable JSON strings (e.g.
			// {"acf_type":"relation",...} or ["a","b"]), resolve them into native PHP
			// values and re-apply via update_field() so the field remains functional.
			//
			// This is especially important for fields like post_object/user where the
			// importer must look up local IDs by slug/login.
			if ( is_string( $meta_value ) && '' !== $meta_value && in_array( $field_type, [ 'post_object', 'relationship', 'taxonomy', 'user', 'checkbox', 'google_map', 'gallery' ], true ) ) {
				$resolved = $this->resolve_acf_meta_value( $meta_value, $post_id );
				if ( $resolved !== $meta_value ) {
					$acf_key = is_array( $field_object ) && ! empty( $field_object['key'] ) ? (string) $field_object['key'] : '';
					if ( '' !== $acf_key && 0 === strpos( $acf_key, 'field_' ) ) {
						update_field( $acf_key, $resolved, $post_id );
					} else {
						update_field( $meta_key, $resolved, $post_id );
					}
					$meta_value = $resolved;
				}
			}
			
			// Handle different ACF field types
			switch ( $field_type ) {
				case 'image':
				case 'file':
					// Check if it's a URL (string) or ID (numeric)
					if ( is_string( $meta_value ) && filter_var( $meta_value, FILTER_VALIDATE_URL ) ) {
						// It's a URL - import it
						$new_id = $this->import_and_replace_acf_media( $meta_value, $post_id, $meta_key, $media_duplicate_mode );
						if ( $new_id ) {
							$imported_count++;
						} else {
							$skipped_count++;
						}
					}
					break;
					
				case 'gallery':
					// Gallery stores array of IDs or URLs
					$gallery_value = maybe_unserialize( $meta_value );
					
					if ( is_array( $gallery_value ) ) {
						$new_gallery = [];
						$changed = false;
						
						foreach ( $gallery_value as $item_value ) {
							if ( is_string( $item_value ) && filter_var( $item_value, FILTER_VALIDATE_URL ) ) {
								// It's a URL - import it
								$new_id = $this->import_media_for_acf( $item_value, $post_id, $media_duplicate_mode );
								if ( $new_id ) {
									$new_gallery[] = $new_id;
									$changed = true;
									$imported_count++;
								} else {
									$new_gallery[] = $item_value;
									$skipped_count++;
								}
							} else {
								// Keep as is (probably already an ID)
								$new_gallery[] = $item_value;
							}
						}
						
						// Update meta if changed
						if ( $changed ) {
							update_post_meta( $post_id, $meta_key, $new_gallery );
						}
					}
					break;
					
				case 'repeater':
				case 'flexible_content':
					// Process nested fields recursively
					$result = $this->process_acf_nested_field( $post_id, $meta_key, $meta_value, $media_duplicate_mode );
					$imported_count += $result['imported'];
					$skipped_count += $result['skipped'];
					break;
			}
		}
		
		if ( $imported_count > 0 || $skipped_count > 0 ) {
			$this->log_info( sprintf( 
				'ACF media import for post %d: %d imported, %d skipped',
				$post_id,
				$imported_count,
				$skipped_count
			) );
		}
	}

	/**
	 * Process nested ACF field (repeater or flexible content)
	 * Recursively handles media in nested structures
	 *
	 * @param int    $post_id              Post ID
	 * @param string $field_name           Field name
	 * @param mixed  $field_value          Field value
	 * @param string $media_duplicate_mode Media duplicate mode
	 * @return array Array with 'imported' and 'skipped' counts
	 */
	private function process_acf_nested_field( $post_id, $field_name, $field_value, $media_duplicate_mode ) {
		$imported_count = 0;
		$skipped_count = 0;
		
		// Repeater and flexible content store row count
		$row_count = intval( $field_value );
		
		if ( $row_count <= 0 ) {
			return [ 'imported' => 0, 'skipped' => 0 ];
		}
		
		// Process each row
		for ( $i = 0; $i < $row_count; $i++ ) {
			// Get field object for this row
			// Field definition only; do not format values during import.
			$field_object = get_field_object( $field_name, $post_id, false, false );
			
			if ( ! $field_object || empty( $field_object['sub_fields'] ) ) {
				continue;
			}
			
			// Process each sub field
			foreach ( $field_object['sub_fields'] as $sub_field ) {
				$sub_field_name = $sub_field['name'];
				$sub_field_type = $sub_field['type'];
				
				// Construct meta key for this sub field
				// Format: {field_name}_{row}_{sub_field_name}
				$meta_key = "{$field_name}_{$i}_{$sub_field_name}";
				
				// Get the value
				$meta_value = get_post_meta( $post_id, $meta_key, true );
				
				if ( empty( $meta_value ) ) {
					continue;
				}
				
				// Handle different sub field types
				switch ( $sub_field_type ) {
					case 'image':
					case 'file':
						if ( is_string( $meta_value ) && filter_var( $meta_value, FILTER_VALIDATE_URL ) ) {
							$new_id = $this->import_and_replace_acf_media( $meta_value, $post_id, $meta_key, $media_duplicate_mode );
							if ( $new_id ) {
								$imported_count++;
							} else {
								$skipped_count++;
							}
						}
						break;
						
					case 'gallery':
						$gallery_value = maybe_unserialize( $meta_value );
						
						if ( is_array( $gallery_value ) ) {
							$new_gallery = [];
							$changed = false;
							
							foreach ( $gallery_value as $item_value ) {
								if ( is_string( $item_value ) && filter_var( $item_value, FILTER_VALIDATE_URL ) ) {
									$new_id = $this->import_media_for_acf( $item_value, $post_id, $media_duplicate_mode );
									if ( $new_id ) {
										$new_gallery[] = $new_id;
										$changed = true;
										$imported_count++;
									} else {
										$new_gallery[] = $item_value;
										$skipped_count++;
									}
								} else {
									$new_gallery[] = $item_value;
								}
							}
							
							if ( $changed ) {
								update_post_meta( $post_id, $meta_key, $new_gallery );
							}
						}
						break;
						
					case 'repeater':
					case 'flexible_content':
						// Nested repeater/flexible - process recursively
						$nested_meta_key = $meta_key;
						$result = $this->process_acf_nested_field( $post_id, $nested_meta_key, $meta_value, $media_duplicate_mode );
						$imported_count += $result['imported'];
						$skipped_count += $result['skipped'];
						break;
						
					case 'group':
						// Group field - process its sub fields
						if ( ! empty( $sub_field['sub_fields'] ) ) {
							foreach ( $sub_field['sub_fields'] as $group_sub_field ) {
								$group_field_name = $group_sub_field['name'];
								$group_field_type = $group_sub_field['type'];
								$group_meta_key = "{$meta_key}_{$group_field_name}";
								$group_meta_value = get_post_meta( $post_id, $group_meta_key, true );
								
								if ( empty( $group_meta_value ) ) {
									continue;
								}
								
								if ( in_array( $group_field_type, [ 'image', 'file' ], true ) ) {
									if ( is_string( $group_meta_value ) && filter_var( $group_meta_value, FILTER_VALIDATE_URL ) ) {
										$new_id = $this->import_and_replace_acf_media( $group_meta_value, $post_id, $group_meta_key, $media_duplicate_mode );
										if ( $new_id ) {
											$imported_count++;
										} else {
											$skipped_count++;
										}
									}
								} elseif ( 'gallery' === $group_field_type ) {
									$gallery_value = maybe_unserialize( $group_meta_value );
									
									if ( is_array( $gallery_value ) ) {
										$new_gallery = [];
										$changed = false;
										
										foreach ( $gallery_value as $item_value ) {
											if ( is_string( $item_value ) && filter_var( $item_value, FILTER_VALIDATE_URL ) ) {
												$new_id = $this->import_media_for_acf( $item_value, $post_id, $media_duplicate_mode );
												if ( $new_id ) {
													$new_gallery[] = $new_id;
													$changed = true;
													$imported_count++;
												} else {
													$new_gallery[] = $item_value;
													$skipped_count++;
												}
											} else {
												$new_gallery[] = $item_value;
											}
										}
										
										if ( $changed ) {
											update_post_meta( $post_id, $group_meta_key, $new_gallery );
										}
									}
								}
							}
						}
						break;
				}
			}
		}
		
		return [
			'imported' => $imported_count,
			'skipped'  => $skipped_count,
		];
	}

	/**
	 * Import media for ACF field and replace with new ID
	 *
	 * @param string $url                  Media URL
	 * @param int    $post_id              Post ID
	 * @param string $meta_key             Meta key to update
	 * @param string $media_duplicate_mode Duplicate handling mode
	 * @return int|false New attachment ID or false on failure
	 */
	private function import_and_replace_acf_media( $url, $post_id, $meta_key, $media_duplicate_mode ) {
		$new_id = $this->import_media_for_acf( $url, $post_id, $media_duplicate_mode );
		
		if ( $new_id ) {
			// Update the meta field with new ID
			update_post_meta( $post_id, $meta_key, $new_id );
			return $new_id;
		}
		
		return false;
	}

	/**
	 * Import media file for ACF field
	 *
	 * @param string $url                  Media URL
	 * @param int    $post_id              Post ID
	 * @param string $media_duplicate_mode Duplicate handling mode
	 * @return int|false New attachment ID or false
	 */
		private function import_media_for_acf( $url, $post_id, $media_duplicate_mode ) {
			static $url_to_attachment_cache = [];

			// Skip if not a valid URL
			if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
				return false;
			}

			// Avoid importing the same URL multiple times within a single request/job.
			// This also prevents duplicate attachments when the same media is referenced
			// by multiple fields in the same item.
			if ( isset( $url_to_attachment_cache[ $url ] ) ) {
				return (int) $url_to_attachment_cache[ $url ];
			}

			// If it's already a local media URL, try to map it back to an attachment ID
			// without downloading. This keeps ACF fields functional when importing a file
			// that references media already present on this site.
			if ( strpos( $url, wp_upload_dir()['baseurl'] ) === 0 ) {
				$existing = attachment_url_to_postid( $url );
				if ( $existing ) {
					$url_to_attachment_cache[ $url ] = (int) $existing;
					return (int) $existing;
				}
				return false;
			}

		// Check for existing media
		$filename = basename( wp_parse_url( $url, PHP_URL_PATH ) );
			$existing_attachment = $this->find_existing_media( $filename, $url );

			if ( $existing_attachment ) {
				// Handle duplicate based on mode
				if ( 'skip' === $media_duplicate_mode ) {
					$this->log_info( sprintf( 'Skipped duplicate ACF media: %s (using existing ID: %d)', $filename, $existing_attachment ) );
					$url_to_attachment_cache[ $url ] = (int) $existing_attachment;
					return $existing_attachment;
				} elseif ( 'replace' === $media_duplicate_mode ) {
					wp_delete_attachment( $existing_attachment, true );
					$this->log_info( sprintf( 'Deleted existing ACF media for replacement: %s (ID: %d)', $filename, $existing_attachment ) );
				}
			}

		// Import the media file
			$attachment_id = $this->import_media_from_url( $url, $post_id );

			if ( ! is_wp_error( $attachment_id ) && $attachment_id ) {
				$this->log_info( sprintf( 'Imported ACF media: %s (new ID: %d)', $filename, $attachment_id ) );
				$url_to_attachment_cache[ $url ] = (int) $attachment_id;
				return $attachment_id;
			}

			return false;
		}

	/**
	 * Auto-import media files from post content
	 *
	 * @param int    $post_id      Post ID
	 * @param string $post_content Post content with media URLs
	 */
	private function auto_import_content_media( $post_id, $post_content ) {
		$media_urls = [];
		
		// 1. Find traditional <img> tags
		preg_match_all( '/<img[^>]+src=[\'"]([^\'"]+)[\'"][^>]*>/i', $post_content, $img_matches );
		if ( ! empty( $img_matches[1] ) ) {
			$media_urls = array_merge( $media_urls, $img_matches[1] );
		}
		
		// 2. Find Gutenberg block image URLs (wp:image, wp:cover, wp:media-text, wp:gallery)
		// Pattern: "url":"https://example.com/image.jpg"
		preg_match_all( '/"url"\s*:\s*"([^"]+\.(jpg|jpeg|png|gif|webp|svg)[^"]*)"/i', $post_content, $gutenberg_matches );
		if ( ! empty( $gutenberg_matches[1] ) ) {
			$media_urls = array_merge( $media_urls, $gutenberg_matches[1] );
		}
		
		// 3. Find background-image URLs in style attributes
		preg_match_all( '/background-image\s*:\s*url\([\'"]?([^\'"()]+)[\'"]?\)/i', $post_content, $bg_matches );
		if ( ! empty( $bg_matches[1] ) ) {
			$media_urls = array_merge( $media_urls, $bg_matches[1] );
		}
		
		// 4. Find srcset URLs (responsive images)
		preg_match_all( '/srcset=[\'"]([^\'"]+)[\'"]/i', $post_content, $srcset_matches );
		if ( ! empty( $srcset_matches[1] ) ) {
			foreach ( $srcset_matches[1] as $srcset ) {
				// Split by comma and extract URLs
				$srcset_parts = explode( ',', $srcset );
				foreach ( $srcset_parts as $part ) {
					// Extract URL (before the width descriptor)
					if ( preg_match( '/^\s*([^\s]+)/', trim( $part ), $url_match ) ) {
						$media_urls[] = $url_match[1];
					}
				}
			}
		}
		
		// Remove duplicates and empty values
		$media_urls = array_unique( array_filter( $media_urls ) );
		
		if ( empty( $media_urls ) ) {
			return;
		}

		$media_duplicate_mode = $this->get_option( 'media_duplicate_mode', 'skip' );
		$imported_count = 0;
		$skipped_count = 0;
		$error_count = 0;
		$url_mapping = []; // Store old URL => new URL mappings
		
		foreach ( $media_urls as $url ) {
			// Skip if not a valid URL
			if ( ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
				continue;
			}

			// Skip if it's already a local media URL
			if ( strpos( $url, wp_upload_dir()['baseurl'] ) === 0 ) {
				continue;
			}
			
			// Skip data URIs
			if ( strpos( $url, 'data:' ) === 0 ) {
				continue;
			}

			// Check for existing media by filename, size and hash
			$filename = basename( wp_parse_url( $url, PHP_URL_PATH ) );
			$existing_attachment = $this->find_existing_media( $filename, $url );

			if ( $existing_attachment ) {
				// Handle duplicate based on mode
				if ( 'skip' === $media_duplicate_mode ) {
					// Use existing media - store URL mapping
					$new_url = wp_get_attachment_url( $existing_attachment );
					$url_mapping[ $url ] = [
						'url' => $new_url,
						'id'  => $existing_attachment,
					];
					$skipped_count++;
					$this->log_info( sprintf( 'Skipped duplicate media: %s (using existing ID: %d)', $filename, $existing_attachment ) );
					continue;
				} elseif ( 'replace' === $media_duplicate_mode ) {
					// Delete existing and import new
					wp_delete_attachment( $existing_attachment, true );
					$this->log_info( sprintf( 'Deleted existing media for replacement: %s (ID: %d)', $filename, $existing_attachment ) );
				}
				// 'create' mode - fall through to import as new
			}

			// Import the media file
			$attachment_id = $this->import_media_from_url( $url, $post_id );

			if ( ! is_wp_error( $attachment_id ) && $attachment_id ) {
				// Store URL mapping
				$new_url = wp_get_attachment_url( $attachment_id );
				$url_mapping[ $url ] = [
					'url' => $new_url,
					'id'  => $attachment_id,
				];
				$imported_count++;
				$this->log_info( sprintf( 'Imported media: %s (new ID: %d)', $filename, $attachment_id ) );
			} else {
				$error_count++;
				$error_message = is_wp_error( $attachment_id ) ? $attachment_id->get_error_message() : 'Unknown error';
				$this->log_error( sprintf( 'Failed to import media: %s - %s', $filename, $error_message ) );
			}
		}

		// Update post content with new media URLs and IDs
		if ( ! empty( $url_mapping ) ) {
			$post_content = $this->replace_media_urls_in_content( $post_content, $url_mapping );
			
			wp_update_post( [
				'ID'           => $post_id,
				'post_content' => $post_content,
			] );
		}

		// Log summary
		$this->log_info( sprintf( 
			'Media import summary for post %d: %d imported, %d skipped, %d errors',
			$post_id,
			$imported_count,
			$skipped_count,
			$error_count
		) );
	}

	/**
	 * Replace media URLs and IDs in content
	 * Handles both traditional HTML and Gutenberg blocks
	 *
	 * @param string $content     Post content
	 * @param array  $url_mapping Array of old_url => ['url' => new_url, 'id' => new_id]
	 * @return string Updated content
	 */
	private function replace_media_urls_in_content( $content, $url_mapping ) {
		foreach ( $url_mapping as $old_url => $new_data ) {
			$new_url = $new_data['url'];
			$new_id = $new_data['id'];
			
			// Replace direct URL occurrences
			$content = str_replace( $old_url, $new_url, $content );
			
			// Replace URL-encoded versions (common in Gutenberg)
			$content = str_replace( urlencode( $old_url ), urlencode( $new_url ), $content );
			
			// Try to find and replace attachment IDs in Gutenberg blocks
			// Pattern: "id":123 near the URL
			$old_id_pattern = '/"id"\s*:\s*(\d+)([^}]*"url"\s*:\s*"' . preg_quote( $old_url, '/' ) . '")/';
			$content = preg_replace(
				$old_id_pattern,
				'"id":' . $new_id . '$2',
				$content
			);
			
			// Also try reverse order: URL before ID
			$old_id_pattern_reverse = '/("url"\s*:\s*"' . preg_quote( $old_url, '/' ) . '"[^}]*"id"\s*:\s*)(\d+)/';
			$content = preg_replace(
				$old_id_pattern_reverse,
				'$1' . $new_id,
				$content
			);
		}
		
		return $content;
	}

	/**
	 * Find existing media by filename, size and hash
	 *
	 * @param string $filename  Media filename
	 * @param string $url       Media URL to download and check
	 * @return int|null Attachment ID or null
	 */
		private function find_existing_media( $filename, $url = '' ) {
			global $wpdb;

			$remote_data = null;
			if ( ! empty( $url ) ) {
				// Prefer hash-based duplicate detection (works even when WP renames files
				// to "-1", "-2", etc. and the filename no longer matches the source).
				$remote_data = $this->get_remote_file_data( $url );
				if ( $remote_data && ! empty( $remote_data['hash'] ) && class_exists( '\\WP_AIE\\Helper\\Media_Hash' ) ) {
					$by_hash = \WP_AIE\Helper\Media_Hash::get_attachment_by_hash( (string) $remote_data['hash'] );
					if ( $by_hash ) {
						return (int) $by_hash;
					}
				}
			}

			// First, find all attachments with matching filename
			$attachment_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
				$wpdb->prepare(
					"SELECT post_id FROM $wpdb->postmeta 
					WHERE meta_key = '_wp_attached_file' 
				AND meta_value LIKE %s",
				'%' . $wpdb->esc_like( $filename )
			)
		);

		if ( empty( $attachment_ids ) ) {
			return null;
		}

		// If we only have one match, return it
		if ( 1 === count( $attachment_ids ) ) {
			return (int) $attachment_ids[0];
			}

			// If we have multiple matches and URL is provided, check by filesize and hash
			if ( ! empty( $url ) ) {
				// Get remote file size and hash (may already be downloaded above)
				if ( ! $remote_data ) {
					$remote_data = $this->get_remote_file_data( $url );
				}

				if ( $remote_data ) {
					foreach ( $attachment_ids as $attachment_id ) {
						$local_file = get_attached_file( $attachment_id );
						
					if ( ! file_exists( $local_file ) ) {
						continue;
					}

					$local_size = filesize( $local_file );
					
					// Check file size first (faster)
					if ( $local_size === $remote_data['size'] ) {
						// If size matches, verify with MD5 hash
						$local_hash = md5_file( $local_file );
						
						if ( $local_hash === $remote_data['hash'] ) {
							return (int) $attachment_id;
						}
					}
				}
			}
		}

		// Return first match if no exact match found
		return (int) $attachment_ids[0];
	}

	/**
	 * Get remote file data (size and hash) without full download
	 *
	 * @param string $url Remote file URL
	 * @return array|null Array with 'size' and 'hash' or null on failure
	 */
		private function get_remote_file_data( $url ) {
			// Download file to temp location
			$temp_file = download_url( $url, 30 ); // 30 seconds timeout

			if ( is_wp_error( $temp_file ) ) {
				$host = wp_parse_url( $url, PHP_URL_HOST );
				$is_dev_host = is_string( $host ) && preg_match( '/\\.(local|test|localhost)$/i', $host );

				// In local/dev environments, WordPress can reject ".local"/".test" hosts as
				// "unsafe" and return "A valid URL was not provided." even though the URL
				// is reachable. Retry with `reject_unsafe_urls=false` for common dev TLDs.
				if ( $is_dev_host && 'http_request_failed' === $temp_file->get_error_code() ) {
					$retry = $this->download_url_unrestricted( (string) $url, 30 );
					if ( ! is_wp_error( $retry ) ) {
						$temp_file = $retry;
					}
				}

				if ( is_wp_error( $temp_file ) ) {
					return null;
				}
			}

		if ( ! file_exists( $temp_file ) ) {
			return null;
		}

		$data = [
			'size' => filesize( $temp_file ),
			'hash' => md5_file( $temp_file ),
		];

		// Keep temp file for potential reuse in import_media_from_url
		// Store in transient with URL as key
		$transient_key = 'aie_temp_media_' . md5( $url );
		set_transient( $transient_key, $temp_file, HOUR_IN_SECONDS );

		return $data;
	}

	/**
	 * Import media from URL
	 *
	 * @param string $url     Media URL
	 * @param int    $post_id Post ID to attach media to
	 * @return int|WP_Error Attachment ID or WP_Error
	 */
	private function import_media_from_url( $url, $post_id = 0 ) {
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		// Check if we already downloaded this file during duplicate check
		$transient_key = 'aie_temp_media_' . md5( $url );
		$temp_file = get_transient( $transient_key );

		// If not in transient or file doesn't exist, download it
		if ( ! $temp_file || ! file_exists( $temp_file ) ) {
			$temp_file = download_url( $url, 30 ); // 30 seconds timeout

			// In local/dev environments, WordPress can reject ".local"/".test" hosts as
			// "unsafe" and return "A valid URL was not provided." even though the URL
			// is reachable. Retry with `reject_unsafe_urls=false` for common dev TLDs.
			if ( is_wp_error( $temp_file ) ) {
				$host = wp_parse_url( $url, PHP_URL_HOST );
				$is_dev_host = is_string( $host ) && preg_match( '/\\.(local|test|localhost)$/i', $host );

				if ( $is_dev_host && 'http_request_failed' === $temp_file->get_error_code() ) {
					$retry = $this->download_url_unrestricted( $url, 30 );
					if ( ! is_wp_error( $retry ) ) {
						$temp_file = $retry;
					}
				}
			}

			if ( is_wp_error( $temp_file ) ) {
				return $temp_file;
			}
		} else {
			// Delete transient since we're using the file now
			delete_transient( $transient_key );
		}

		// Prepare file array
		$file = [
			'name'     => basename( wp_parse_url( $url, PHP_URL_PATH ) ),
			'tmp_name' => $temp_file,
		];

		// Import the file
		$attachment_id = media_handle_sideload( $file, $post_id );

		// Clean up temp file
		if ( file_exists( $temp_file ) ) {
			@wp_delete_file( $temp_file );
		}

		return $attachment_id;
	}

	/**
	 * Download a URL to a temporary file without WordPress "unsafe URL" rejection.
	 *
	 * This is a fallback only for dev domains like *.local / *.test.
	 *
	 * @param string $url     Remote file URL.
	 * @param int    $timeout Timeout in seconds.
	 * @return string|\WP_Error Path to the temporary file or WP_Error.
	 */
	private function download_url_unrestricted( string $url, int $timeout ) {
		$tmp = wp_tempnam( $url );
		if ( ! $tmp ) {
			return new \WP_Error( 'aie_temp_file_failed', __( 'Could not create a temporary file for download.', 'amplified-import-export' ) );
		}

		$response = wp_remote_get(
			$url,
			[
				'timeout'            => $timeout,
				'stream'             => true,
				'filename'           => $tmp,
				'reject_unsafe_urls' => false,
			]
		);

		if ( is_wp_error( $response ) ) {
			@wp_delete_file( $tmp );
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			@wp_delete_file( $tmp );
			return new \WP_Error( 'aie_download_failed', sprintf( 'Download failed with HTTP %d', $code ) );
		}

		return $tmp;
	}

	/**
	 * Clean up old temporary media files from transients
	 * Called at the end of import batch
	 */
	public function cleanup_temp_media_files() {
		global $wpdb;

		// Get all transients related to temporary media files
		$transients = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			"SELECT option_name FROM $wpdb->options 
			WHERE option_name LIKE '_transient_aie_temp_media_%'"
		);

		$cleaned = 0;
		foreach ( $transients as $transient_option ) {
			$transient_key = str_replace( '_transient_', '', $transient_option );
			$temp_file = get_transient( $transient_key );

			if ( $temp_file && file_exists( $temp_file ) ) {
				@wp_delete_file( $temp_file );
				$cleaned++;
			}

			delete_transient( $transient_key );
		}

		if ( $cleaned > 0 ) {
			$this->log_info( sprintf( 'Cleaned up %d temporary media files', $cleaned ) );
		}
	}
}

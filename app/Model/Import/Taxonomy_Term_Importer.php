<?php
/**
 * Taxonomy Term Importer
 *
 * Handles importing taxonomy terms (categories, tags, custom taxonomies)
 *
 * @package WP_AIE\Model\Import
 */

namespace WP_AIE\Model\Import;

defined( 'ABSPATH' ) || exit;

class Taxonomy_Term_Importer extends Abstract_Importer {

	/**
	 * Term source-id meta key used for cross-site dedupe and hierarchy fixups.
	 *
	 * @var string
	 */
	const SOURCE_ID_META_KEY = '_aie_source_term_id';

	/**
	 * Get importer name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'taxonomy_terms';
	}

	/**
	 * Get importer description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Import taxonomy terms (categories, tags, custom taxonomies) with metadata and ACF fields', 'wp-advanced-import-export' );
	}

	/**
	 * Get required fields
	 *
	 * @return array
	 */
	public function get_required_fields() {
		return [ 'name', 'taxonomy' ];
	}

	/**
	 * Get optional fields
	 *
	 * @return array
	 */
	public function get_optional_fields() {
		return [
			'term_id',
			'slug',
			'description',
			'term_taxonomy_id',
			'parent',
			'count',
			'term_meta',
		];
	}

	/**
	 * Get supported options
	 *
	 * @return array
	 */
	public function get_supported_options() {
		return [
			'duplicate_mode'     => 'How to handle duplicates: skip, update, create',
			'duplicate_check'    => 'Field to check for duplicates: term_id, slug, name',
			'update_existing'    => 'Update existing terms: true, false',
			'import_acf'         => 'Import ACF fields: true, false',
			'update_count'       => 'Update term count: true, false',
			'validate_parent'    => 'Validate parent term exists: true, false',
			'create_taxonomy'    => 'Create taxonomy if it does not exist: true, false',
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
				'duplicate_check'    => 'term_id',
				'update_existing'    => false,
				'import_acf'         => true,
				'update_count'       => false, // Don't update count by default - let WordPress handle it
				'validate_parent'    => true,
				'create_taxonomy'    => false,
			]
		);
	}

	/**
	 * Set importer options
	 *
	 * Overrides parent to map UI field names to internal option names
	 *
	 * @param array $options Options to set
	 * @return void
	 */
	public function set_options( $options ) {
		// Map duplicate_handling (from UI) to duplicate_mode (used internally)
		if ( isset( $options['duplicate_handling'] ) && ! isset( $options['duplicate_mode'] ) ) {
			$options['duplicate_mode'] = $options['duplicate_handling'];
		}

		// Map if_exists (from UI) to duplicate_mode (used internally)
		if ( isset( $options['if_exists'] ) && ! isset( $options['duplicate_mode'] ) ) {
			$options['duplicate_mode'] = $options['if_exists'];
		}

		// Map unique_field (from UI) to duplicate_check (used internally)
		if ( isset( $options['unique_field'] ) && ! isset( $options['duplicate_check'] ) ) {
			$options['duplicate_check'] = $options['unique_field'];
		}

		parent::set_options( $options );
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

				$item[ $target_field ] = $value;
			}

			$prepared[] = $item;
		}

		return $prepared;
	}

	/**
	 * Import single taxonomy term
	 *
	 * @param array $item  Term data
	 * @param int   $index Item index
	 * @return int|string|WP_Error Term ID, 'skipped', 'updated', or WP_Error
	 */
	public function import_item( $item, $index ) {
		// Sanitize data
		$item = $this->sanitize_item( $item );

		$source_term_id       = absint( $item['_aie_source_term_id'] ?? ( $item['term_id'] ?? 0 ) );
		$source_parent_term_id = absint( $item['_aie_source_parent_term_id'] ?? ( $item['parent'] ?? 0 ) );
		$source_parent_slug   = isset( $item['_aie_source_parent_slug'] ) ? (string) $item['_aie_source_parent_slug'] : ( (string) ( $item['parent_slug'] ?? '' ) );

		// Validate required fields
		if ( empty( $item['name'] ) ) {
			return new \WP_Error(
				'missing_term_name',
				__( 'Term name is required', 'wp-advanced-import-export' )
			);
		}

		if ( empty( $item['taxonomy'] ) ) {
			return new \WP_Error(
				'missing_taxonomy',
				__( 'Taxonomy is required', 'wp-advanced-import-export' )
			);
		}

		$taxonomy = $item['taxonomy'];

		// Always defer parent mapping for job-based imports: source IDs don't exist on target.
		if ( ! empty( $this->job_id ) ) {
			$item['parent'] = 0;
		}

		// Check if taxonomy exists
		if ( ! taxonomy_exists( $taxonomy ) ) {
			if ( $this->get_option( 'create_taxonomy', false ) ) {
				// Register custom taxonomy (basic registration)
				register_taxonomy( $taxonomy, null );
			} else {
				return new \WP_Error(
					'taxonomy_not_found',
					sprintf(
						/* translators: %s: taxonomy name */
						__( 'Taxonomy "%s" does not exist', 'wp-advanced-import-export' ),
						$taxonomy
					)
				);
			}
		}

		// Check for existing term based on duplicate_check setting
		$existing_term_id = null;
		$duplicate_check  = $this->get_option( 'duplicate_check', 'term_id' );

		if ( 'term_id' === $duplicate_check ) {
			// Interpret term_id as SOURCE site ID; de-dupe using stored term meta when available.
			if ( $source_term_id ) {
				$existing_term_id = $this->find_existing_term_by_source_id( $taxonomy, $source_term_id );
			}

			// Fallback for same-site imports: allow direct ID match only when it also matches slug (if provided).
			if ( ! $existing_term_id ) {
				$term_id = absint( $item['term_id'] ?? 0 );
				if ( $term_id ) {
					$existing_term = get_term( $term_id, $taxonomy );
					if ( $existing_term && ! is_wp_error( $existing_term ) ) {
						$slug = isset( $item['slug'] ) ? (string) $item['slug'] : '';
						if ( $slug === '' || (string) $existing_term->slug === $slug ) {
							$existing_term_id = $term_id;
						}
					}
				}
			}
		} elseif ( 'slug' === $duplicate_check ) {
			if ( ! empty( $item['slug'] ) ) {
				$existing_term = get_term_by( 'slug', $item['slug'], $taxonomy );
				if ( $existing_term && ! is_wp_error( $existing_term ) ) {
					$existing_term_id = $existing_term->term_id;
				}
			}
		} elseif ( 'name' === $duplicate_check ) {
			$existing_term = get_term_by( 'name', $item['name'], $taxonomy );
			if ( $existing_term && ! is_wp_error( $existing_term ) ) {
				$existing_term_id = $existing_term->term_id;
			}
		}

		// Handle duplicate
		if ( $existing_term_id ) {
			$duplicate_mode = $this->get_option( 'duplicate_mode', 'skip' );

			if ( 'skip' === $duplicate_mode ) {
				$this->record_source_id_map( $taxonomy, $source_term_id, $existing_term_id );
				return 'skipped';
			} elseif ( 'update' === $duplicate_mode || $this->get_option( 'update_existing', false ) ) {
				$result = $this->update_term( $existing_term_id, $item );
				if ( ! is_wp_error( $result ) ) {
					$this->store_source_term_meta( $existing_term_id, $taxonomy, $source_term_id, $source_parent_term_id, $source_parent_slug );
					$this->record_source_id_map( $taxonomy, $source_term_id, $existing_term_id );
				}
				return $result;
			}
			// If 'create' mode, continue to create new term
		}

		// Create new term
		$created = $this->create_term( $item );
		if ( is_int( $created ) && $created > 0 ) {
			$this->store_source_term_meta( $created, $taxonomy, $source_term_id, $source_parent_term_id, $source_parent_slug );
			$this->record_source_id_map( $taxonomy, $source_term_id, $created );
		}
		return $created;
	}

	/**
	 * Store source IDs as term meta for cross-site dedupe and parent fixups.
	 *
	 * @param int    $term_id               Target term ID.
	 * @param string $taxonomy              Taxonomy.
	 * @param int    $source_term_id        Source site term_id.
	 * @param int    $source_parent_term_id Source site parent term_id.
	 * @param string $source_parent_slug    Source site parent slug (portable hint).
	 * @return void
	 */
	private function store_source_term_meta( $term_id, $taxonomy, $source_term_id, $source_parent_term_id, $source_parent_slug ) {
		$term_id = absint( $term_id );
		if ( $term_id <= 0 ) {
			return;
		}

		$source_term_id = absint( $source_term_id );
		if ( $source_term_id > 0 ) {
			update_term_meta( $term_id, self::SOURCE_ID_META_KEY, (string) $source_term_id );
		}

		$source_parent_term_id = absint( $source_parent_term_id );
		if ( $source_parent_term_id > 0 ) {
			update_term_meta( $term_id, '_aie_source_parent_term_id', (string) $source_parent_term_id );
		}

		$source_parent_slug = trim( (string) $source_parent_slug );
		if ( $source_parent_slug !== '' ) {
			update_term_meta( $term_id, '_aie_source_parent_slug', $source_parent_slug );
		}
	}

	/**
	 * Find an existing target term by stored source ID (taxonomy-scoped).
	 *
	 * @param string $taxonomy       Taxonomy.
	 * @param int    $source_term_id Source site term_id.
	 * @return int|null Target term_id or null.
	 */
	private function find_existing_term_by_source_id( $taxonomy, $source_term_id ) {
		$taxonomy       = (string) $taxonomy;
		$source_term_id = absint( $source_term_id );
		if ( $taxonomy === '' || $source_term_id <= 0 ) {
			return null;
		}

		$term_ids = get_terms( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Targeted lookup by importer-specific meta key.
			[
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'fields'     => 'ids',
					'number'     => 1,
					'orderby'    => 'term_id',
					'order'      => 'DESC',
					'meta_query' => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Targeted lookup by importer-specific meta key.
						[
							'key'     => self::SOURCE_ID_META_KEY,
							'value'   => (string) $source_term_id,
							'compare' => '=',
					],
				],
			]
		);

		if ( is_wp_error( $term_ids ) || empty( $term_ids ) ) {
			return null;
		}

		return absint( $term_ids[0] );
	}

	/**
	 * Persist a source->target term ID map for this import job (taxonomy-scoped).
	 *
	 * @param string $taxonomy   Taxonomy.
	 * @param int    $source_id  Source term_id.
	 * @param int    $target_id  Target term_id.
	 * @return void
	 */
	private function record_source_id_map( $taxonomy, $source_id, $target_id ) {
		$taxonomy = sanitize_key( (string) $taxonomy );
		$source_id = absint( $source_id );
		$target_id = absint( $target_id );

		if ( $taxonomy === '' || $source_id <= 0 || $target_id <= 0 ) {
			return;
		}

		if ( empty( $this->job_id ) ) {
			return;
		}

		$key = $this->get_job_id_map_key();
		$map = get_transient( $key );
		if ( ! is_array( $map ) ) {
			$map = [];
		}
		if ( ! isset( $map[ $taxonomy ] ) || ! is_array( $map[ $taxonomy ] ) ) {
			$map[ $taxonomy ] = [];
		}

		$map[ $taxonomy ][ (string) $source_id ] = $target_id;
		set_transient( $key, $map, DAY_IN_SECONDS );
	}

	/**
	 * Get transient key for this job's term ID map.
	 *
	 * @return string
	 */
	private function get_job_id_map_key() {
		return 'aie_import_term_id_map_' . absint( $this->job_id );
	}

	/**
	 * Create new term
	 *
	 * @param array $item Term data
	 * @return int|WP_Error Term ID or WP_Error
	 */
	private function create_term( $item ) {
		$taxonomy = $item['taxonomy'];
		$name     = $item['name'];
		$args     = $this->prepare_term_args( $item );

		// Create term
		$result = wp_insert_term( $name, $taxonomy, $args );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$term_id = $result['term_id'];

		// Import term metadata
		$this->import_term_meta( $term_id, $taxonomy, $item );

		// Update count if specified
		if ( $this->get_option( 'update_count', false ) && isset( $item['count'] ) ) {
			wp_update_term_count_now( [ $term_id ], $taxonomy );
		}

		return $term_id;
	}

	/**
	 * Update existing term
	 *
	 * @param int   $term_id Existing term ID
	 * @param array $item    Term data
	 * @return string|WP_Error 'updated' or WP_Error
	 */
	private function update_term( $term_id, $item ) {
		$taxonomy = $item['taxonomy'];
		$args     = $this->prepare_term_args( $item );

		// Update term
		$result = wp_update_term( $term_id, $taxonomy, $args );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		// Update term metadata
		$this->import_term_meta( $term_id, $taxonomy, $item );

		// Update count if specified
		if ( $this->get_option( 'update_count', false ) && isset( $item['count'] ) ) {
			wp_update_term_count_now( [ $term_id ], $taxonomy );
		}

		return 'updated';
	}

	/**
	 * Prepare term arguments for wp_insert_term or wp_update_term
	 *
	 * @param array $item Raw term data
	 * @return array Prepared term arguments
	 */
	private function prepare_term_args( $item ) {
		$args = [];

		// Slug
		if ( ! empty( $item['slug'] ) ) {
			$args['slug'] = $item['slug'];
		}

		// Description
		if ( isset( $item['description'] ) ) {
			$args['description'] = $item['description'];
		}

		// Parent
		if ( isset( $item['parent'] ) && '' !== $item['parent'] ) {
			$parent_id = absint( $item['parent'] );

			// Validate parent exists if option is enabled
			if ( $this->get_option( 'validate_parent', true ) && $parent_id > 0 ) {
				$parent_term = get_term( $parent_id, $item['taxonomy'] );
				if ( $parent_term && ! is_wp_error( $parent_term ) ) {
					$args['parent'] = $parent_id;
				}
			} else {
				$args['parent'] = $parent_id;
			}
		}

		return $args;
	}

	/**
	 * Import term metadata
	 *
	 * @param int    $term_id  Term ID
	 * @param string $taxonomy Taxonomy name
	 * @param array  $item     Term data
	 * @return void
	 */
	private function import_term_meta( $term_id, $taxonomy, $item ) {
		// Import term_meta if provided
		if ( ! empty( $item['term_meta'] ) ) {
			$meta_data = $item['term_meta'];

			// Parse if string (JSON)
			if ( is_string( $meta_data ) ) {
				$meta_data = json_decode( $meta_data, true );
			}

			if ( is_array( $meta_data ) ) {
				foreach ( $meta_data as $meta_key => $meta_value ) {
					update_term_meta( $term_id, $meta_key, $meta_value );
				}
			}
		}

		// Import ACF fields if enabled
		if ( $this->get_option( 'import_acf', true ) ) {
			$this->import_acf_fields( $term_id, $taxonomy, $item );
		}
	}

	/**
	 * Import ACF fields for term
	 *
	 * @param int    $term_id  Term ID
	 * @param string $taxonomy Taxonomy name
	 * @param array  $item     Term data
	 * @return void
	 */
	private function import_acf_fields( $term_id, $taxonomy, $item ) {
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			return;
		}

		// Get ACF field groups for this taxonomy
		$field_groups = acf_get_field_groups(
			[
				'taxonomy' => $taxonomy,
			]
		);

		if ( empty( $field_groups ) ) {
			return;
		}

		$term_context = $taxonomy . '_' . $term_id;

		foreach ( $field_groups as $field_group ) {
			$fields = acf_get_fields( $field_group['key'] );

			if ( empty( $fields ) ) {
				continue;
			}

			foreach ( $fields as $field ) {
				$field_name = $field['name'];

				// Check if field value exists in import data
				if ( isset( $item[ $field_name ] ) ) {
					update_field( $field['key'], $item[ $field_name ], $term_context );
				}
			}
		}
	}

	/**
	 * Sanitize item data
	 *
	 * @param array $item Item data
	 * @return array Sanitized data
	 */
	protected function sanitize_item( $item ) {
		if ( ! is_array( $item ) ) {
			return [];
		}

		$sanitized = [];

		foreach ( $item as $key => $value ) {
			// Skip if value is empty
			if ( '' === $value || null === $value ) {
				$sanitized[ $key ] = $value;
				continue;
			}

			// Sanitize based on field type
			switch ( $key ) {
				case 'description':
					$sanitized[ $key ] = wp_kses_post( $value );
					break;

				case 'term_id':
				case 'term_taxonomy_id':
				case 'parent':
				case 'count':
					$sanitized[ $key ] = absint( $value );
					break;

				case 'name':
				case 'slug':
				case 'taxonomy':
					$sanitized[ $key ] = sanitize_text_field( $value );
					break;

				default:
					$sanitized[ $key ] = sanitize_text_field( $value );
					break;
			}
		}

		return $sanitized;
	}
}

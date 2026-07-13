<?php
/**
 * Abstract Importer
 *
 * Base class for all importers with common functionality
 *
 * @package RockStarLab\ImportExport\Model\Import
 */

namespace RockStarLab\ImportExport\Model\Import;

use RockStarLab\ImportExport\Helper\Data_Transformer;
use RockStarLab\ImportExport\Helper\Field_Transformation_Bridge;

defined( 'ABSPATH' ) || exit;

abstract class Abstract_Importer implements Importer_Interface {

	/**
	 * Job ID for logging
	 *
	 * @var int
	 */
	protected $job_id;

	/**
	 * Import options
	 *
	 * @var array
	 */
	protected $options = [];

	/**
	 * Import statistics
	 *
	 * @var array
	 */
	protected $stats = [
		'total'   => 0,
		'success' => 0,
		'skipped' => 0,
		'failed'  => 0,
		'updated' => 0,
		'created' => 0,
		'errors'  => [],
	];

	/**
	 * Constructor
	 *
	 * @param int $job_id Optional. Job ID for logging
	 */
	public function __construct( $job_id = 0 ) {
		$this->job_id = $job_id;
	}

	/**
	 * Import data
	 *
	 * @param array $data    Data to import
	 * @param array $options Optional. Import options
	 * @return array|WP_Error Import results or WP_Error
	 */
	public function import( $data, $options = [] ) {
		$this->options = wp_parse_args( $options, $this->get_default_options() );
		$this->reset_stats();

		// Validate data first
		$validation = $this->validate( $data );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$this->log_info( sprintf( 'Starting import of %d items', count( $data ) ) );

		$this->stats['total'] = count( $data );

		// Import each item
		foreach ( $data as $index => $item ) {
			$result = $this->import_item( $item, $index );

			if ( is_wp_error( $result ) ) {
				++$this->stats['failed'];
				$this->stats['errors'][] = [
					'row'     => $index + 1,
					'message' => $result->get_error_message(),
					'data'    => $result->get_error_data(),
				];
				$this->log_error( sprintf( 'Failed to import row %d: %s', $index + 1, $result->get_error_message() ) );
			} elseif ( 'skipped' === $result ) {
				++$this->stats['skipped'];
				$this->log_info( sprintf( 'Skipped row %d', $index + 1 ) );
			} elseif ( 'updated' === $result ) {
				++$this->stats['updated'];
				++$this->stats['success'];
				$this->log_info( sprintf( 'Updated row %d', $index + 1 ) );
			} else {
				++$this->stats['created'];
				++$this->stats['success'];
				$this->log_info( sprintf( 'Created row %d', $index + 1 ) );
			}
		}

		$this->log_info(
			sprintf(
				'Import completed: %d total, %d success, %d failed, %d skipped',
				$this->stats['total'],
				$this->stats['success'],
				$this->stats['failed'],
				$this->stats['skipped']
			)
		);

		// Cleanup temporary files if method exists
		if ( method_exists( $this, 'cleanup_temp_media_files' ) ) {
			$this->cleanup_temp_media_files();
		}

		return $this->stats;
	}

	/**
	 * Validate data
	 *
	 * @param array $data Data to validate
	 * @return true|WP_Error
	 */
	public function validate( $data ) {
		if ( empty( $data ) || ! is_array( $data ) ) {
			return new \WP_Error( 'empty_data', __( 'No data to import', 'import-export-by-rockstarlab' ) );
		}

		$required_fields = $this->get_required_fields();

		// Check first item for required fields
		$first_item     = reset( $data );
		$missing_fields = [];

		foreach ( $required_fields as $field ) {
			if ( ! isset( $first_item[ $field ] ) ) {
				$missing_fields[] = $field;
			}
		}

		if ( ! empty( $missing_fields ) ) {
			return new \WP_Error(
				'missing_required_fields',
				sprintf(
					/* translators: %s: comma-separated list of missing fields */
					__( 'Missing required fields: %s', 'import-export-by-rockstarlab' ),
					implode( ', ', $missing_fields )
				)
			);
		}

		return true;
	}

	/**
	 * Prepare raw data for import
	 *
	 * Supports two mapping formats:
	 *  - Legacy: [ 'source_field' => 'target_field', ... ]
	 *  - UI format: [ ['source_field' => 'col', 'target_field' => 'wp_field', 'function_ids' => [], 'taxonomy_format' => 'name'], ... ]
	 *
	 * @param array $raw_data Raw data from file
	 * @param array $mapping  Optional. Field mapping
	 * @return array Prepared data
	 */
	public function prepare( $raw_data, $mapping = [] ) {
		if ( empty( $mapping ) ) {
			return $raw_data;
		}

		// Detect UI format: array of objects with 'source_field' / 'target_field' keys.
		$is_ui_format = isset( $mapping[0] ) && is_array( $mapping[0] ) && array_key_exists( 'source_field', $mapping[0] );

		// Normalise to a flat [ source => target ] map.
		// Also collect taxonomy_format and transformations per target field.
		$flat_map         = [];
		$taxonomy_formats = []; // [ target_field => 'name'|'slug'|'id' ]
		$field_functions  = []; // [ target_field => [ transformation_id, ... ] ]
		if ( $is_ui_format ) {
			foreach ( $mapping as $entry ) {
				$src = $entry['source_field'] ?? '';
				$tgt = $entry['target_field'] ?? '';
				if ( '' !== $src && '' !== $tgt ) {
					$flat_map[ $src ] = $tgt;
					if ( ! empty( $entry['taxonomy_format'] ) ) {
						$taxonomy_formats[ $tgt ] = $entry['taxonomy_format'];
					}
					$function_ids = $this->normalize_mapping_function_ids( $entry );
					if ( ! empty( $function_ids ) ) {
						$field_functions[ $tgt ] = $function_ids;
					}
				}
			}
		} else {
			// Legacy format already is flat.
			foreach ( $mapping as $src => $tgt ) {
				if ( is_string( $src ) && is_string( $tgt ) && '' !== $src && '' !== $tgt ) {
					$flat_map[ $src ] = $tgt;
				}
			}
		}

		if ( empty( $flat_map ) ) {
			return $raw_data;
		}

		$prepared = [];

		foreach ( $raw_data as $item ) {
			$prepared_item = [];

			foreach ( $flat_map as $source_field => $target_field ) {
				if ( isset( $item[ $source_field ] ) ) {
					$value = $item[ $source_field ];

					if ( ! empty( $field_functions[ $target_field ] ) ) {
						$value = $this->apply_import_field_functions(
							$value,
							$field_functions[ $target_field ],
							$source_field,
							$target_field,
							$item
						);
					}

					$prepared_item[ $target_field ] = $value;
				}
			}

			// Carry taxonomy format info so importers can resolve term by name/slug/id.
			if ( ! empty( $taxonomy_formats ) ) {
				$prepared_item['_taxonomy_formats'] = $taxonomy_formats;
			}

			$prepared[] = $prepared_item;
		}

		return $prepared;
	}

	/**
	 * Normalize field transformation IDs from current and legacy mapping payloads.
	 *
	 * @param array $entry UI mapping entry.
	 * @return array
	 */
	protected function normalize_mapping_function_ids( $entry ) {
		$ids = array();

		if ( ! empty( $entry['function_ids'] ) && is_array( $entry['function_ids'] ) ) {
			$ids = $entry['function_ids'];
		} elseif ( ! empty( $entry['functions'] ) && is_array( $entry['functions'] ) ) {
			$ids = $entry['functions'];
		} elseif ( ! empty( $entry['function_id'] ) ) {
			$ids = array( $entry['function_id'] );
		}

		$normalized = array();
		foreach ( $ids as $id ) {
			if ( is_array( $id ) && isset( $id['id'] ) ) {
				$id = $id['id'];
			}

			if ( is_scalar( $id ) ) {
				$id = sanitize_key( (string) $id );
				if ( '' !== $id ) {
					$normalized[] = $id;
				}
			}
		}

		return array_values( array_unique( $normalized ) );
	}

	/**
	 * Apply configured transformations to one imported field value.
	 *
	 * @param mixed  $value        Source field value.
	 * @param array  $function_ids Transformation IDs.
	 * @param string $source_field Source column name.
	 * @param string $target_field Target field name.
	 * @param array  $item         Full source row.
	 * @return mixed
	 */
	protected function apply_import_field_functions( $value, $function_ids, $source_field, $target_field, $item ) {
		if ( ! Field_Transformation_Bridge::is_enabled() ) {
			return $value;
		}

		foreach ( $function_ids as $function_id ) {
			$value = Field_Transformation_Bridge::apply(
				$value,
				array( $function_id ),
				array(
					'operation'    => 'import',
					'source_field' => $source_field,
					'target_field' => $target_field,
					'item'         => $item,
					'importer'     => $this,
				)
			);
		}

		return $value;
	}

	/**
	 * Import single item
	 *
	 * Must be implemented by child classes.
	 *
	 * @param array $item  Item data
	 * @param int   $index Item index
	 * @return int|string|WP_Error Item ID, 'skipped', 'updated', or WP_Error
	 */
	abstract public function import_item( $item, $index );

	/**
	 * Get default import options
	 *
	 * @return array Default options
	 */
	protected function get_default_options() {
		return [
			'duplicate_mode' => 'skip', // skip, update, create
			'batch_size'     => 100,
		];
	}

	/**
	 * Set importer options
	 *
	 * @param array $options Options to set
	 * @return void
	 */
	public function set_options( $options ) {
		$this->options = wp_parse_args( $options, $this->get_default_options() );

		// For Database_Table_Importer, set table_name
		if ( method_exists( $this, 'get_name' ) && 'database_table' === $this->get_name() ) {
			$this->table_name = $this->get_option( 'table_name', '' );
		}
	}

	/**
	 * Reset statistics
	 */
	protected function reset_stats() {
		$this->stats = [
			'total'   => 0,
			'success' => 0,
			'skipped' => 0,
			'failed'  => 0,
			'updated' => 0,
			'created' => 0,
			'errors'  => [],
		];
	}

	/**
	 * Get import statistics
	 *
	 * @return array Statistics
	 */
	public function get_stats() {
		return $this->stats;
	}

	/**
	 * Log info message
	 *
	 * @param string $message Log message
	 * @param array  $data    Optional. Additional data
	 */
	protected function log_info( $message, $data = [] ) {
		if ( $this->job_id ) {
		}
	}

	/**
	 * Log warning message
	 *
	 * @param string $message Log message
	 * @param array  $data    Optional. Additional data
	 */
	protected function log_warning( $message, $data = [] ) {
		if ( $this->job_id ) {
		}
	}

	/**
	 * Log error message
	 *
	 * @param string $message Log message
	 * @param array  $data    Optional. Additional data
	 */
	protected function log_error( $message, $data = [] ) {
		if ( $this->job_id ) {
		}
	}

	/**
	 * Sanitize item data
	 *
	 * @param array $item Item data
	 * @return array Sanitized data
	 */
	protected function sanitize_item( $item ) {
		// If $item is already an array (normal case), return as-is.
		// sanitize_text_field() returns '' for arrays, which would destroy all field data.
		// Individual field sanitization is handled by wp_insert_post() and the concrete importers.
		if ( is_array( $item ) ) {
			return $item;
		}

		return Data_Transformer::sanitize_data( $item );
	}

	/**
	 * Get option value
	 *
	 * @param string $key     Option key
	 * @param mixed  $default Default value
	 * @return mixed Option value
	 */
	protected function get_option( $key, $default = null ) {
		return $this->options[ $key ] ?? $default;
	}
}

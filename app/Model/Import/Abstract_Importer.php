<?php
/**
 * Abstract Importer
 *
 * Base class for all importers with common functionality
 *
 * @package WP_AIE\Model\Import
 */

namespace WP_AIE\Model\Import;

use WP_AIE\Helper\Data_Transformer;

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
			return new \WP_Error( 'empty_data', __( 'No data to import', 'wp-advanced-import-export' ) );
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
					__( 'Missing required fields: %s', 'wp-advanced-import-export' ),
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
	 *  - UI format: [ ['source_field' => 'col', 'target_field' => 'wp_field', 'function_id' => ''], ... ]
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
		$flat_map = [];
		if ( $is_ui_format ) {
			foreach ( $mapping as $entry ) {
				$src = $entry['source_field'] ?? '';
				$tgt = $entry['target_field'] ?? '';
				if ( $src !== '' && $tgt !== '' ) {
					$flat_map[ $src ] = $tgt;
				}
			}
		} else {
			// Legacy format already is flat.
			foreach ( $mapping as $src => $tgt ) {
				if ( is_string( $src ) && is_string( $tgt ) && $src !== '' && $tgt !== '' ) {
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
					$prepared_item[ $target_field ] = $item[ $source_field ];
				}
			}

			$prepared[] = $prepared_item;
		}

		return $prepared;
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

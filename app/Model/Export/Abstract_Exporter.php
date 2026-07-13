<?php
/**
 * Abstract Exporter
 *
 * Base class for all exporters with common functionality
 *
 * @package RockStarLab\ImportExport\Model\Export
 */

namespace RockStarLab\ImportExport\Model\Export;

use RockStarLab\ImportExport\Helper\Data_Transformer;

defined( 'ABSPATH' ) || exit;

abstract class Abstract_Exporter implements Exporter_Interface {

	/**
	 * Job ID for logging
	 *
	 * @var int
	 */
	protected $job_id;

	/**
	 * Export options
	 *
	 * @var array
	 */
	protected $options = [];

	/**
	 * Export statistics
	 *
	 * @var array
	 */
	protected $stats = [
		'total'      => 0,
		'exported'   => 0,
		'skipped'    => 0,
		'failed'     => 0,
		'errors'     => [],
		'start_time' => 0,
		'end_time'   => 0,
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
	 * Export data
	 *
	 * @param array $options Optional. Export options
	 * @return array|WP_Error Export results or WP_Error
	 */
	public function export( $options = [] ) {
		$this->stats['start_time'] = microtime( true );
		$this->options             = wp_parse_args( $options, $this->get_default_options() );
		$this->reset_stats();

		// Validate options first
		$validation = $this->validate_options( $this->options );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		$this->log_info( 'Starting export' );

		// Get total count
		$this->stats['total'] = $this->get_count( $this->options );

		if ( 0 === $this->stats['total'] ) {
			$this->log_warning( 'No items found to export' );
			return $this->stats;
		}

		$this->log_info( sprintf( 'Found %d items to export', $this->stats['total'] ) );

		// Get data
		$data = $this->get_data( $this->options );

		if ( is_wp_error( $data ) ) {
			$this->log_error( 'Failed to get data: ' . $data->get_error_message() );
			return $data;
		}

		// Process each item
		foreach ( $data as $index => $item ) {
			$processed = $this->process_item( $item, $index );

			if ( is_wp_error( $processed ) ) {
				++$this->stats['failed'];
				$this->stats['errors'][] = [
					'row'     => $index + 1,
					'message' => $processed->get_error_message(),
					'data'    => $processed->get_error_data(),
				];
				$this->log_error( sprintf( 'Failed to process row %d: %s', $index + 1, $processed->get_error_message() ) );
			} elseif ( 'skipped' === $processed ) {
				++$this->stats['skipped'];
			} else {
				++$this->stats['exported'];
				// Save the processed item back to data array
				$data[ $index ] = $processed;
			}
		}

		$this->stats['end_time'] = microtime( true );
		$duration                = round( $this->stats['end_time'] - $this->stats['start_time'], 2 );

		$this->log_info(
			sprintf(
				'Export completed in %s seconds: %d total, %d exported, %d failed, %d skipped',
				$duration,
				$this->stats['total'],
				$this->stats['exported'],
				$this->stats['failed'],
				$this->stats['skipped']
			)
		);

		return [
			'data'  => $data,
			'stats' => $this->stats,
		];
	}

	/**
	 * Validate export options
	 *
	 * @param array $options Options to validate
	 * @return true|WP_Error
	 */
	public function validate_options( $options ) {
		// Basic validation - can be extended by child classes
		if ( isset( $options['limit'] ) && ! is_numeric( $options['limit'] ) ) {
			return new \WP_Error( 'invalid_limit', __( 'Limit must be a number', 'import-export-by-rockstarlab' ) );
		}

		if ( isset( $options['offset'] ) && ! is_numeric( $options['offset'] ) ) {
			return new \WP_Error( 'invalid_offset', __( 'Offset must be a number', 'import-export-by-rockstarlab' ) );
		}

		return true;
	}

	/**
	 * Get data based on export options
	 *
	 * Must be implemented by child classes.
	 *
	 * @param array $options Export options
	 * @return array|WP_Error Data array or WP_Error
	 */
	abstract public function get_data( $options = [] );

	/**
	 * Process single item
	 *
	 * Can be overridden by child classes for custom processing.
	 *
	 * @param mixed $item  Item data
	 * @param int   $index Item index
	 * @return mixed|string|WP_Error Processed item, 'skipped', or WP_Error
	 */
	protected function process_item( $item, $index ) {
		// Apply field functions if they exist
		if ( ! empty( $this->options['field_functions'] ) && is_array( $item ) ) {
			$item = $this->apply_field_functions( $item, $this->options['field_functions'] );
		}

		// Apply field selection if specified.
		// Skip when force_include_id is set because the caller needs ID fields
		// present to identify items and handles its own update scope.
		if ( is_array( $item ) && ! empty( $this->options['fields'] ) && ! in_array( '*', $this->options['fields'], true )
			&& empty( $this->options['force_include_id'] ) ) {
			$item = $this->select_fields( $item, $this->options['fields'] );
		}

		// Default: return as-is
		// Child classes can override for filtering, transformation, etc.
		return $item;
	}

	/**
	 * Apply field transformations.
	 *
	 * @param array $item            Item data
	 * @param array $field_functions Field transformation mapping { fieldKey: [transformationId1, transformationId2, ...] }
	 * @return array Item with transformed fields
	 */
	protected function apply_field_functions( $item, $field_functions ) {
		if ( empty( $field_functions ) || ! is_array( $item ) ) {
			return $item;
		}

		if ( ! \RockStarLab\ImportExport\Helper\Field_Transformation_Bridge::is_enabled() ) {
			return $item;
		}

		foreach ( $field_functions as $field_key => $function_ids ) {
			// Skip if field doesn't exist in item or no transformations assigned.
			if ( ! isset( $item[ $field_key ] ) || empty( $function_ids ) || ! is_array( $function_ids ) ) {
				continue;
			}

			$value = $item[ $field_key ];

			// Apply each transformation in sequence.
			foreach ( $function_ids as $function_id ) {
				$value = \RockStarLab\ImportExport\Helper\Field_Transformation_Bridge::apply(
					$value,
					[ $function_id ],
					[
						'operation' => 'export',
						'field'     => $field_key,
						'item'      => $item,
						'exporter'  => $this,
					]
				);
			}

			// Update field with transformed value
			$item[ $field_key ] = $value;
		}

		return $item;
	}

	/**
	 * Get default export options
	 *
	 * @return array Default options
	 */
	protected function get_default_options() {
		return [
			'limit'  => -1,  // -1 = no limit
			'offset' => 0,
			'fields' => $this->get_default_fields(),
		];
	}

	/**
	 * Apply filters to data
	 *
	 * @param array $data    Data to filter
	 * @param array $filters Filters to apply
	 * @return array Filtered data
	 */
	protected function apply_filters( $data, $filters ) {
		if ( empty( $filters ) ) {
			return $data;
		}

		$filtered = [];

		foreach ( $data as $item ) {
			if ( $this->item_matches_filters( $item, $filters ) ) {
				$filtered[] = $item;
			}
		}

		return $filtered;
	}

	/**
	 * Check if item matches filters
	 *
	 * @param mixed $item    Item to check
	 * @param array $filters Filters
	 * @return bool True if matches
	 */
	protected function item_matches_filters( $item, $filters ) {
		// Default implementation - can be overridden
		return true;
	}

	/**
	 * Reset statistics
	 */
	protected function reset_stats() {
		$this->stats = [
			'total'      => 0,
			'exported'   => 0,
			'skipped'    => 0,
			'failed'     => 0,
			'errors'     => [],
			'start_time' => 0,
			'end_time'   => 0,
		];
	}

	/**
	 * Get export statistics
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
	 * Transform data
	 *
	 * @param array $data Data to transform
	 * @return array Transformed data
	 */
	protected function transform_data( $data ) {
		return Data_Transformer::transform( $data );
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

	/**
	 * Select fields from item
	 *
	 * @param array $item   Item data
	 * @param array $fields Fields to select
	 * @return array Filtered item
	 */
	protected function select_fields( $item, $fields ) {
		if ( empty( $fields ) || in_array( '*', $fields, true ) ) {
			return $item;
		}

		$selected = [];

		foreach ( $fields as $field ) {
			if ( array_key_exists( $field, $item ) ) {
				$selected[ $field ] = $item[ $field ];
			}
		}

		return $selected;
	}
}

<?php
/**
 * Export Processor
 *
 * Processes export jobs in batches
 *
 * @package RockStarLab\ImportExport\Model\Queue
 */

namespace RockStarLab\ImportExport\Model\Queue;

use RockStarLab\ImportExport\Model\Job;
use RockStarLab\ImportExport\Model\Export\Exporter_Factory;
use RockStarLab\ImportExport\Model\Format\Format_Factory;
use RockStarLab\ImportExport\Helper\Fs;

defined( 'ABSPATH' ) || exit;

class Export_Processor {

	/**
	 * Job model instance
	 *
	 * @var Job
	 */
	protected $job_model;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->job_model = rsl_ie()->Model->job;
	}

	/**
	 * Process export job
	 *
	 * @param int $job_id Job ID
	 * @return array Processing result
	 */
	public function process( $job_id ) {
		try {
			// Get job data
			$job = $this->job_model->find( $job_id );

			if ( ! $job ) {
				throw new \Exception( sprintf( 'Job #%d not found', $job_id ) );
			}

			// Check if job is paused or cancelled
			if ( in_array( $job->status, [ 'paused', 'cancelled' ], true ) ) {
				return [
					'status'    => $job->status,
					'completed' => false,
				];
			}

			// Set job to processing if it's pending
			if ( 'pending' === $job->status ) {
				$this->job_model->update(
					$job_id,
					[
						'status'     => 'processing',
						'started_at' => current_time( 'mysql' ),
					]
				);
			}

			// Parse parameters
			$parameters = json_decode( $job->parameters, true );
			if ( ! $parameters ) {
				throw new \Exception( 'Invalid job parameters' );
			}

			$export_type = $parameters['export_type'];
			$options     = $parameters['options'] ?? [];
			$fields      = $parameters['fields'] ?? [];
			// Get batch size (default to 100 for better performance)
			$batch_size = isset( $options['items_per_iteration'] ) ? (int) $options['items_per_iteration'] : 100;
			// Get current offset
			$current_offset = (int) ( $job->processed_items ?? 0 );

			// Get exporter
			$exporter = Exporter_Factory::get_exporter( $export_type, $job_id );
			if ( is_wp_error( $exporter ) ) {
				throw new \Exception( $exporter->get_error_message() );
			}

			// Map logical export types to actual WP post_type when needed
			$post_type_map = [
				'post'        => 'post',
				'page'        => 'page',
				'media'       => 'attachment',
				'menu'        => 'nav_menu_item',
				'woo_product' => 'product',
				'woo_order'   => 'shop_order',
				'woo_coupon'  => 'shop_coupon',
			];

			$mapped_post_type = isset( $post_type_map[ $export_type ] ) ? $post_type_map[ $export_type ] : $export_type;

			// For custom_post_types and taxonomy, extract real post_type/taxonomy from dynamic_filters
			if ( 'custom_post_types' === $export_type ) {
				// Look for post_type in dynamic_filters
				$dynamic_filters = $parameters['dynamic_filters'] ?? [];
				foreach ( $dynamic_filters as $filter ) {
					if ( isset( $filter['field'] ) && $filter['field'] === 'post_type' && ! empty( $filter['value'] ) ) {
						$mapped_post_type = $filter['value'];
						break;
					}
				}
			}
			if ( 'taxonomy' === $export_type ) {
				// Look for taxonomy in dynamic_filters
				$dynamic_filters = $parameters['dynamic_filters'] ?? [];
				foreach ( $dynamic_filters as $filter ) {
					if ( isset( $filter['field'] ) && $filter['field'] === 'taxonomy' && ! empty( $filter['value'] ) ) {
						$mapped_post_type = $filter['value'];
						break;
					}
				}
			}

			// Build export options
			// Note: dynamic_filters from Step 2 should be passed as 'filters' to the exporter
			$export_options = array_merge(
				$options,
				[
					'filters'         => $parameters['dynamic_filters'] ?? [],  // Use dynamic_filters from Step 2
					'fields'          => $fields,
					'custom_fields'   => $parameters['custom_fields'] ?? [],
					'taxonomy'        => $parameters['taxonomy'] ?? [],
					'field_functions' => $parameters['field_functions'] ?? [],
					'limit'           => $batch_size,
					'offset'          => $current_offset,
				]
			);

			// For taxonomy export, add taxonomy name to export_options
			if ( 'taxonomy' === $export_type && ! empty( $mapped_post_type ) ) {
				$export_options['taxonomy'] = $mapped_post_type;
			} else {
				// For other types, use post_type
				$export_options['post_type'] = $mapped_post_type;
			}

			// For database_table, add table_name to export_options
			if ( 'database_table' === $export_type && ! empty( $parameters['table_name'] ) ) {
				$export_options['table_name'] = $parameters['table_name'];
			}

			// Get total count on first batch
			if ( 0 === $current_offset ) {
				$total_count = Exporter_Factory::get_count( $export_type, $export_options );

				$this->job_model->update(
					$job_id,
					[
						'total_items' => $total_count,
					]
				);
			} else {
				$total_count = (int) $job->total_items;
			}

			// Export batch
			$export_result = $exporter->export( $export_options );
			if ( is_wp_error( $export_result ) ) {
				throw new \Exception( $export_result->get_error_message() );
			}

			$batch_data  = $export_result['data'] ?? [];
			$batch_count = count( $batch_data );

			// Append batch data to temp file
			if ( ! empty( $batch_data ) ) {
				$this->append_batch_data( $job_id, $batch_data );
			}

			// Update progress
			$new_processed = $current_offset + $batch_count;
			$progress      = $total_count > 0 ? ( $new_processed / $total_count ) * 100 : 0;

			$this->job_model->update(
				$job_id,
				[
					'processed_items' => $new_processed,
					'progress'        => $progress,
				]
			);

			// Check if completed
			// Export is complete when: all items processed OR no items returned (end of data)
			$completed = ( $new_processed >= $total_count ) || ( $batch_count === 0 );

			if ( $completed ) {
				// Get all accumulated data
				$all_data = $this->get_accumulated_data( $job_id );

				$this->finalize_export( $job_id, $parameters, $all_data );

				// Clean up temp file
				$this->cleanup_temp_file( $job_id );

				return [
					'completed' => true,
					'processed' => $new_processed,
					'total'     => $total_count,
					'progress'  => 100,
				];
			}

			return [
				'completed' => false,
				'processed' => $new_processed,
				'total'     => $total_count,
				'progress'  => $progress,
			];

		} catch ( \Exception $e ) {

			$this->job_model->update(
				$job_id,
				[
					'status' => 'failed',
					'result' => wp_json_encode( [ 'error' => $e->getMessage() ] ),
				]
			);

			return [
				'completed' => true,
				'error'     => $e->getMessage(),
			];
		}
	}

	/**
	 * Append batch data to temp file
	 *
	 * @param int   $job_id Job ID
	 * @param array $batch_data Batch data to append
	 */
	private function append_batch_data( $job_id, $batch_data ) {
		$temp_file = $this->get_temp_file_path( $job_id );

		// Ensure temp directory exists
		$temp_dir = dirname( $temp_file );
		if ( ! file_exists( $temp_dir ) ) {
			wp_mkdir_p( $temp_dir );
		}

		// Append data as JSON lines (one JSON object per line)
		$handle = fopen( $temp_file, 'a' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		if ( $handle ) {
			foreach ( $batch_data as $item ) {
				fwrite( $handle, json_encode( $item ) . "\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
			}
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		}
	}

	/**
	 * Get accumulated data from temp file
	 *
	 * @param int $job_id Job ID
	 * @return array Accumulated data
	 */
	private function get_accumulated_data( $job_id ) {
		$temp_file = $this->get_temp_file_path( $job_id );

		if ( ! file_exists( $temp_file ) ) {
			return [];
		}

		$data   = [];
		$handle = fopen( $temp_file, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		if ( $handle ) {
			while ( ( $line = fgets( $handle ) ) !== false ) {
				$item = json_decode( trim( $line ), true );
				if ( $item ) {
					$data[] = $item;
				}
			}
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen,WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite
		}

		return $data;
	}

	/**
	 * Clean up temp file
	 *
	 * @param int $job_id Job ID
	 */
	private function cleanup_temp_file( $job_id ) {
		$temp_file = $this->get_temp_file_path( $job_id );
		if ( file_exists( $temp_file ) ) {
			wp_delete_file( $temp_file );
		}
	}

	/**
	 * Get temp file path for job
	 *
	 * @param int $job_id Job ID
	 * @return string Temp file path
	 */
	private function get_temp_file_path( $job_id ) {
		$upload_dir = wp_upload_dir();
		$temp_dir   = $upload_dir['basedir'] . '/rsl-ie/temp';
		return $temp_dir . '/export-' . $job_id . '.jsonl';
	}

	/**
	 * Finalize export after all batches processed
	 *
	 * @param int   $job_id Job ID
	 * @param array $parameters Job parameters
	 * @param array $data Exported data
	 */
	private function finalize_export( $job_id, $parameters, $data ) {
		$format         = $parameters['format'] ?? 'csv';
		$format_options = $parameters['format_options'] ?? [];
		$export_type    = $parameters['export_type'];

		// Prepare file path
		$filename  = sprintf( 'export-%s-%d.%s', $export_type, $job_id, $format );
		$file_info = Fs::get_export_file_path( $filename );

		if ( is_wp_error( $file_info ) ) {
			$this->job_model->update(
				$job_id,
				[
					'status' => 'failed',
					'result' => wp_json_encode( [ 'error' => $file_info->get_error_message() ] ),
				]
			);
			return;
		}

		// Map format_options
		$formatter_options = [];
		if ( 'csv' === $format ) {
			$include_header    = ! empty( $format_options['csv_include_header'] );
			$formatter_options = [
				'delimiter' => $format_options['csv_delimiter'] ?? ',',
				'headers'   => $include_header ? null : false,
			];

			// If there is no data, explicit headers are needed to avoid empty CSV file when header row is requested.
			if ( $include_header && empty( $data ) ) {
				$fields = $parameters['fields'] ?? [];
				if ( empty( $fields ) ) {
					$exporter = Exporter_Factory::get_exporter( $export_type, $job_id );
					if ( ! is_wp_error( $exporter ) && method_exists( $exporter, 'get_default_fields' ) ) {
						$fields = $exporter->get_default_fields();
					}
				}

				if ( ! empty( $fields ) && is_array( $fields ) ) {
					$formatter_options['headers'] = $fields;
				}
			}
		} elseif ( 'json' === $format ) {
			$formatter_options = [
				'pretty_print' => ! empty( $format_options['json_pretty_print'] ),
			];
		} elseif ( 'xml' === $format ) {
			$formatter_options = [
				'pretty_print' => ! empty( $format_options['xml_pretty_print'] ),
			];
		} elseif ( in_array( $format, array( 'xlsx', 'ods' ), true ) ) {
			$formatter_options = [
				'headers' => ! empty( $format_options['spreadsheet_include_header'] ) ? null : false,
			];

			if ( ! empty( $format_options['spreadsheet_include_header'] ) && empty( $data ) ) {
				$fields = $parameters['fields'] ?? [];
				if ( ! empty( $fields ) && is_array( $fields ) ) {
					$formatter_options['headers'] = $fields;
				}
			}
		}

		// Generate file
		$formatter = Format_Factory::create( $format );
		$result    = $formatter->generate( $data, $file_info['path'], $formatter_options );

		if ( is_wp_error( $result ) ) {
			$this->job_model->update(
				$job_id,
				[
					'status' => 'failed',
					'result' => wp_json_encode( [ 'error' => $result->get_error_message() ] ),
				]
			);
			return;
		}

		// Get file size
		$file_size = file_exists( $file_info['path'] ) ? filesize( $file_info['path'] ) : 0;

		// Update job as completed
		$this->job_model->update(
			$job_id,
			[
				'status'        => 'completed',
				'progress'      => 100,
				'file_path'     => $file_info['path'],
				'file_size'     => $file_size,
				'success_items' => count( $data ),
				'completed_at'  => current_time( 'mysql' ),
			]
		);
	}
}

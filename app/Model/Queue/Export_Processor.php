<?php
/**
 * Export Processor
 *
 * Processes export jobs in batches
 *
 * @package WP_AIE\Model\Queue
 */

namespace WP_AIE\Model\Queue;

use WP_AIE\Model\Job;
use WP_AIE\Model\Export\Exporter_Factory;
use WP_AIE\Model\Format\Format_Factory;
use WP_AIE\Helper\Fs;
use WP_AIE\Helper\Logger;

/**
 * Export Processor Class
 *
 * Handles background processing of export jobs
 *
 * @package WP_AIE\Model\Queue
 */
class Export_Processor {

	/**
	 * Logger instance
	 *
	 * @var Logger
	 */
	protected $logger;

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
		$this->logger    = new Logger();
		$this->job_model = WP_AIE()->Model->job;
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

			// Get batch size
			$batch_size = isset( $options['items_per_iteration'] ) ? (int) $options['items_per_iteration'] : 3;

			// Get current offset
			$current_offset = (int) ( $job->processed_items ?? 0 );

			// Get exporter
			$exporter = Exporter_Factory::get_exporter( $export_type, $job_id );
			if ( is_wp_error( $exporter ) ) {
				throw new \Exception( $exporter->get_error_message() );
			}

			// Map logical export types to actual WP post_type when needed
			$post_type_map = [
				'post'                 => 'post',
				'page'                 => 'page',
				'media'                => 'attachment',
				'menu'                 => 'nav_menu_item',
				'block_theme_settings' => 'wp_template',
				'woo_product'          => 'product',
				'woo_order'            => 'shop_order',
				'woo_coupon'           => 'shop_coupon',
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
			$export_options = array_merge(
				$options,
				[
					'post_type'       => $mapped_post_type,
					'filters'         => $parameters['filters'] ?? [],
					'fields'          => $fields,
					'dynamic_filters' => $parameters['dynamic_filters'] ?? [],
					'custom_fields'   => $parameters['custom_fields'] ?? [],
					'taxonomy'        => $parameters['taxonomy'] ?? [],
					'field_functions' => $parameters['field_functions'] ?? [],
					'limit'           => $batch_size,
					'offset'          => $current_offset,
				]
			);

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
			$completed = ( $new_processed >= $total_count ) || ( $batch_count < $batch_size );

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
		$handle = fopen( $temp_file, 'a' );
		if ( $handle ) {
			foreach ( $batch_data as $item ) {
				fwrite( $handle, json_encode( $item ) . "\n" );
			}
			fclose( $handle );
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
		$handle = fopen( $temp_file, 'r' );
		if ( $handle ) {
			while ( ( $line = fgets( $handle ) ) !== false ) {
				$item = json_decode( trim( $line ), true );
				if ( $item ) {
					$data[] = $item;
				}
			}
			fclose( $handle );
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
			unlink( $temp_file );
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
		$temp_dir   = $upload_dir['basedir'] . '/wp-aie/temp';
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
			$formatter_options = [
				'delimiter' => $format_options['csv_delimiter'] ?? ',',
				'headers'   => ! empty( $format_options['csv_include_header'] ) ? null : false,
			];
		} elseif ( 'json' === $format ) {
			$formatter_options = [
				'pretty_print' => ! empty( $format_options['json_pretty_print'] ),
			];
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

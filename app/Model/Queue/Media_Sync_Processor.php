<?php
/**
 * Media Sync Processor
 *
 * Processes media synchronization jobs in batches
 *
 * @package RockStarLab\ImportExport\Model\Queue
 */

namespace RockStarLab\ImportExport\Model\Queue;

use RockStarLab\ImportExport\Helper\Media_Sync;
use RockStarLab\ImportExport\Helper\Progress_Tracker;
use RockStarLab\ImportExport\Model\Job;

defined( 'ABSPATH' ) || exit;

class Media_Sync_Processor {

	/**
	 * Batch processor instance
	 *
	 * @var Batch_Processor
	 */
	protected $batch_processor;

	/**
	 * Progress tracker instance
	 *
	 * @var Progress_Tracker
	 */
	protected $progress_tracker;

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
		$this->batch_processor  = new Batch_Processor( 20 ); // Process 20 files per batch
		$this->progress_tracker = new Progress_Tracker();
		$this->job_model        = rsl_ie()->Model->job;
	}

	/**
	 * Process media sync job
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

			// Check if job is paused
			if ( 'paused' === $job->status ) {
				return array(
					'status'  => 'paused',
					'message' => 'Job is paused',
				);
			}

			if ( 'cancelled' === $job->status ) {
				return array(
					'completed' => true,
					'status'    => 'cancelled',
					'message'   => 'Job is cancelled',
				);
			}

			// Update status to processing
			$this->job_model->update(
				$job_id,
				array(
					'status'     => 'processing',
					'updated_at' => current_time( 'mysql' ),
				)
			);

			// Parse settings
			$settings        = json_decode( $job->settings, true );
			$folder_path     = $settings['folder_path'] ?? '';
			$scan_options    = $settings['scan_options'] ?? array();
			$sync_options    = $settings['sync_options'] ?? array();
			$offset          = $settings['offset'] ?? 0;
			$processed_count = $settings['processed_count'] ?? 0;

			// Get cumulative results from previous batches
			$cumulative_result = $job->result ? json_decode( $job->result, true ) : array(
				'processed' => 0,
				'success'   => 0,
				'skipped'   => 0,
				'failed'    => 0,
				'errors'    => array(),
			);

			// Get all files from settings (already selected by user)
			$all_files = $settings['all_files'] ?? array();

			// If no files in settings, scan folder (backward compatibility)
			if ( empty( $all_files ) && ! isset( $settings['total_files'] ) ) {
				$files_result = Media_Sync::scan_folder( $folder_path, $scan_options );

				if ( is_wp_error( $files_result ) ) {
					throw new \Exception( $files_result->get_error_message() );
				}

				$all_files   = $files_result;
				$total_files = count( $files_result );

				// Update settings with total files count
				$settings['total_files'] = $total_files;
				$settings['all_files']   = $files_result;

				$this->job_model->update(
					$job_id,
					array(
						'settings'    => wp_json_encode( $settings ),
						'total_items' => $total_files,
					)
				);
			} else {
				$total_files = $settings['total_files'] ?? count( $all_files );
			}

			if ( empty( $all_files ) ) {
				throw new \Exception( 'No files found in folder' );
			}

			// Get batch size from sync options (default to 3 if not set).
			$chunk_size = isset( $sync_options['batch_size'] ) ? (int) $sync_options['batch_size'] : 3;
			$chunk_size = max( 1, min( 100, $chunk_size ) ); // Ensure between 1 and 100

			// Get files chunk from offset
			$chunk = array_slice( $all_files, $offset, $chunk_size );

			if ( empty( $chunk ) ) {
				// All files processed - use cumulative results from job
				return $this->complete_job( $job_id, $processed_count, $cumulative_result );
			}

			// Log duplicate handling options

			// Process batch
			// Add base folder to sync options for structure preservation
			$sync_options['base_folder'] = $folder_path;

			$result                          = $this->process_batch( $job_id, $chunk, $sync_options );           // Merge with cumulative results
			$cumulative_result['processed'] += $result['processed'];
			$cumulative_result['success']   += $result['success'];
			$cumulative_result['skipped']   += $result['skipped'];
			$cumulative_result['failed']    += $result['failed'];
			$cumulative_result['errors']     = array_merge(
				$cumulative_result['errors'],
				array_slice( $result['errors'], 0, 20 ) // Keep only last 20 errors
			);

			// Calculate progress
			$new_offset = $offset + count( $chunk );
			$progress   = round( ( $new_offset / $total_files ) * 100 );

			// Update progress with detailed stats
			$this->progress_tracker->update_percentage( $job_id, $new_offset, $total_files );

			// Check if completed
			if ( $new_offset >= $total_files ) {
				return $this->complete_job( $job_id, $new_offset, $cumulative_result );
			}

			// Not completed yet - need to process more batches

			// Update job settings with new offset
			$settings['offset']          = $new_offset;
			$settings['processed_count'] = $new_offset;

			// Prepare update data
			$update_data = array(
				'settings'        => wp_json_encode( $settings ),
				'progress'        => $progress,
				'total_items'     => $total_files,
				'processed_items' => $new_offset,
				'success_items'   => $cumulative_result['success'],
				'failed_items'    => $cumulative_result['failed'],
				'result'          => wp_json_encode( $cumulative_result ), // Save cumulative results
			);

			$this->job_model->update( $job_id, $update_data );

			return array(
				'completed' => false,
				'offset'    => $new_offset,
				'progress'  => $progress,
				'result'    => $cumulative_result, // Return cumulative results
			);          } catch ( \Exception $e ) {

			// Get current progress before marking as failed
			$job            = $this->job_model->find( $job_id );
			$current_result = $job && $job->result ? json_decode( $job->result, true ) : array(
				'processed' => 0,
				'success'   => 0,
				'skipped'   => 0,
				'failed'    => 0,
				'errors'    => array(),
			);

			$this->job_model->update(
				$job_id,
				array(
					'status'          => 'failed',
					'completed_at'    => current_time( 'mysql' ),
					'processed_items' => $current_result['processed'] ?? 0,
					'success_items'   => $current_result['success'] ?? 0,
					'failed_items'    => $current_result['failed'] ?? 0,
					'result'          => wp_json_encode(
						array_merge(
							$current_result,
							array(
								'error' => $e->getMessage(),
							)
						)
					),
				)
			);

			return array(
				'completed' => true,
				'error'     => $e->getMessage(),
			);
			}
	}

	/**
	 * Process batch of files
	 *
	 * @param int   $job_id Job ID
	 * @param array $files Files to process
	 * @param array $options Import options
	 * @return array Processing result
	 */
	protected function process_batch( $job_id, $files, $options ) {
		$results = array(
			'processed' => 0,
			'success'   => 0,
			'skipped'   => 0,
			'failed'    => 0,
			'errors'    => array(),
		);

		foreach ( $files as $file ) {
			++$results['processed'];

			try {
				// Get file path from array
				$file_path = is_array( $file ) ? $file['path'] : $file;

				// Revalidate persisted job data at execution time. Jobs can run
				// later via AJAX, cron, or WP-CLI, so their payload is not trusted.
				$file_path = Media_Sync::validate_source_file( $file_path, $options['base_folder'] ?? '' );
				if ( is_wp_error( $file_path ) ) {
					++$results['failed'];
					$results['errors'][] = sprintf(
						'Invalid source file: %s',
						$file_path->get_error_message()
					);
					continue;
				}

				// Check for duplicates
				$duplicate_handling = $options['duplicate_handling'] ?? 'skip';

				if ( 'skip' === $duplicate_handling ) {
					$duplicate_check = $options['duplicate_check'] ?? 'hash';
					$is_duplicate    = Media_Sync::check_duplicate( $file_path, $duplicate_check );

					// Log duplicate check

					if ( $is_duplicate ) {
						++$results['skipped'];
						continue;
					}
				}               // Import file
				// Map UI option names to helper option names
				$import_options = $options;

				// Always generate thumbnails (skip_thumbnails = false)
				$import_options['skip_thumbnails'] = false;

				// file_operation is passed directly: 'keep', 'copy', or 'move'
				// No need to convert, helper now uses file_operation directly

				// Enable RML folder structure if RML integration is enabled.
				// Use filter_var to handle the string "false" that jQuery AJAX serializes from a boolean false.
				if ( filter_var( $options['rml_integration'] ?? false, FILTER_VALIDATE_BOOLEAN ) ) {
					$import_options['rml_folder_structure'] = true;
				}

				$import_result = Media_Sync::import_file( $file_path, $import_options );

				if ( is_wp_error( $import_result ) ) {
					++$results['failed'];
					$results['errors'][] = sprintf(
						'%s: %s',
						basename( $file_path ),
						$import_result->get_error_message()
					);

				} else {
					++$results['success'];
				}
			} catch ( \Exception $e ) {
				++$results['failed'];
				$results['errors'][] = sprintf(
					'%s: %s',
					basename( $file_path ),
					$e->getMessage()
				);

			}

			// Prevent memory overflow
			if ( memory_get_usage() > ( $this->get_memory_limit() * 0.8 ) ) {
				break;
			}
		}

		return $results;
	}

	/**
	 * Complete job
	 *
	 * @param int   $job_id Job ID
	 * @param int   $processed Total files processed
	 * @param array $result Cumulative result (already includes all batches)
	 * @return array Completion result
	 */
	protected function complete_job( $job_id, $processed, $result = null ) {
		// Use cumulative result passed from process() - it already contains all accumulated data
		$final_result = $result ?? array(
			'processed' => $processed,
			'success'   => 0,
			'skipped'   => 0,
			'failed'    => 0,
			'errors'    => array(),
		);

		// Ensure processed count is set correctly
		$final_result['processed'] = $processed;

		// Update job with complete statistics
		$this->job_model->update(
			$job_id,
			array(
				'status'          => 'completed',
				'completed_at'    => current_time( 'mysql' ),
				'progress'        => 100,
				'total_items'     => $processed,
				'processed_items' => $processed,
				'success_items'   => $final_result['success'],
				'failed_items'    => $final_result['failed'],
				'result'          => wp_json_encode( $final_result ),
			)
		);

		return array(
			'completed' => true,
			'result'    => $final_result,
		);
	}

	/**
	 * Get PHP memory limit in bytes
	 *
	 * @return int Memory limit in bytes
	 */
	protected function get_memory_limit() {
		$memory_limit = ini_get( 'memory_limit' );

		if ( preg_match( '/^(\d+)(.)$/', $memory_limit, $matches ) ) {
			$value = (int) $matches[1];
			$unit  = strtoupper( $matches[2] );

			switch ( $unit ) {
				case 'G':
					return $value * 1024 * 1024 * 1024;
				case 'M':
					return $value * 1024 * 1024;
				case 'K':
					return $value * 1024;
				default:
					return $value;
			}
		}

		return 128 * 1024 * 1024; // Default 128MB
	}
}

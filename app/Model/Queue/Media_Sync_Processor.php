<?php
/**
 * Media Sync Processor
 *
 * Processes media synchronization jobs in batches
 *
 * @package WP_AIE\Model\Queue
 */

namespace WP_AIE\Model\Queue;

use WP_AIE\Helper\Media_Sync;
use WP_AIE\Helper\Logger;
use WP_AIE\Helper\Progress_Tracker;
use WP_AIE\Model\Job;

/**
 * Media Sync Processor Class
 *
 * Handles background processing of media sync jobs
 *
 * @package WP_AIE\Model\Queue
 */
class Media_Sync_Processor {

	/**
	 * Batch processor instance
	 *
	 * @var Batch_Processor
	 */
	protected $batch_processor;

	/**
	 * Logger instance
	 *
	 * @var Logger
	 */
	protected $logger;

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
		$this->logger           = new Logger();
		$this->progress_tracker = new Progress_Tracker();
		$this->job_model        = new Job();
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
				$this->logger->log(
					$job_id,
					'info',
					sprintf( 'Job #%d is paused, skipping processing', $job_id )
				);
				return array(
					'status'  => 'paused',
					'message' => 'Job is paused',
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

			$this->logger->log(
				$job_id,
				'info',
				sprintf( 'Started processing media sync job #%d', $job_id )
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

			// Debug logging
			error_log(
				sprintf(
					'[Media Sync] Job #%d - Initial state: offset=%d, result=%s, cumulative=%s',
					$job_id,
					$offset,
					$job->result ?? 'NULL',
					wp_json_encode( $cumulative_result )
				)
			);            // Scan folder for files (if not already scanned)
			if ( ! isset( $settings['total_files'] ) ) {
				$this->logger->log(
					$job_id,
					'info',
					sprintf( 'Scanning folder: %s', $folder_path )
				);

				$files_result = Media_Sync::scan_folder( $folder_path, $scan_options );

				if ( is_wp_error( $files_result ) ) {
					throw new \Exception( $files_result->get_error_message() );
				}

				$total_files = count( $files_result );

				$this->logger->log(
					$job_id,
					'info',
					sprintf( 'Found %d files in folder', $total_files )
				);

				// Update settings with total files count
				$settings['total_files'] = $total_files;
				$settings['all_files']   = $files_result;

				$this->job_model->update(
					$job_id,
					array(
						'settings' => wp_json_encode( $settings ),
					)
				);
			} else {
				$total_files = $settings['total_files'];
			}

			// Get all files from settings
			$all_files = $settings['all_files'] ?? array();

			if ( empty( $all_files ) ) {
				throw new \Exception( 'No files found in folder' );
			}

			// Get batch size from options (default to 3 if not set)
			$chunk_size = isset( $options['batch_size'] ) ? (int) $options['batch_size'] : 3;
			$chunk_size = max( 1, min( 100, $chunk_size ) ); // Ensure between 1 and 100

			// Get files chunk from offset
			$chunk = array_slice( $all_files, $offset, $chunk_size );

			if ( empty( $chunk ) ) {
				// All files processed - use cumulative results from job
				return $this->complete_job( $job_id, $processed_count, $cumulative_result );
			}

			$this->logger->log(
				$job_id,
				'info',
				sprintf(
					'Processing batch: files %d-%d of %d (batch size: %d)',
					$offset + 1,
					min( $offset + $chunk_size, $total_files ),
					$total_files,
					$chunk_size
				)
			);

			// Log duplicate handling options
			$this->logger->log(
				$job_id,
				'info',
				sprintf(
					'Duplicate options: handling=%s, check=%s',
					$sync_options['duplicate_handling'] ?? 'NOT SET',
					$sync_options['duplicate_check'] ?? 'NOT SET'
				)
			);

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

			// Debug logging
			error_log(
				sprintf(
					'[Media Sync] Job #%d - After batch: batch_result=%s, cumulative=%s',
					$job_id,
					wp_json_encode( $result ),
					wp_json_encode( $cumulative_result )
				)
			);

			// Calculate progress
			$new_offset = $offset + count( $chunk );
			$progress   = round( ( $new_offset / $total_files ) * 100 );

			// Update progress with detailed stats
			$this->progress_tracker->update_percentage( $job_id, $new_offset, $total_files );

			// Check if completed
			if ( $new_offset >= $total_files ) {
				error_log( sprintf( '[Media Sync] Job #%d COMPLETING: new_offset=%d >= total_files=%d', $job_id, $new_offset, $total_files ) );
				return $this->complete_job( $job_id, $new_offset, $cumulative_result );
			}

			// Not completed yet - need to process more batches
			error_log( sprintf( '[Media Sync] Job #%d NOT COMPLETE: new_offset=%d < total_files=%d, will return completed=false', $job_id, $new_offset, $total_files ) );

			// Update job settings with new offset
			$settings['offset']          = $new_offset;
			$settings['processed_count'] = $new_offset;

			// Prepare update data
			$update_data = array(
				'settings' => wp_json_encode( $settings ),
				'progress' => $progress,
				'result'   => wp_json_encode( $cumulative_result ), // Save cumulative results
			);

			// Debug logging
			error_log(
				sprintf(
					'[Media Sync] Updating job #%d: progress=%d%%, result=%s',
					$job_id,
					$progress,
					wp_json_encode( $cumulative_result )
				)
			);

			$this->job_model->update( $job_id, $update_data );

			$this->logger->log(
				$job_id,
				'info',
				sprintf(
					'Batch completed. Progress: %d%%. Total: Processed: %d, Success: %d, Skipped: %d, Failed: %d',
					$progress,
					$cumulative_result['processed'],
					$cumulative_result['success'],
					$cumulative_result['skipped'],
					$cumulative_result['failed']
				)
			);

			error_log( sprintf( '[Media Sync] Job #%d returning completed=false, offset=%d, progress=%d%%', $job_id, $new_offset, $progress ) );

			return array(
				'completed' => false,
				'offset'    => $new_offset,
				'progress'  => $progress,
				'result'    => $cumulative_result, // Return cumulative results
			);      } catch ( \Exception $e ) {
			$this->logger->log(
				$job_id,
				'error',
				sprintf( 'Job error: %s', $e->getMessage() )
			);

			$this->job_model->update(
				$job_id,
				array(
					'status'       => 'failed',
					'completed_at' => current_time( 'mysql' ),
					'result'       => wp_json_encode(
						array(
							'error' => $e->getMessage(),
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

				// Check if file still exists
				if ( ! file_exists( $file_path ) ) {
					++$results['failed'];
					$results['errors'][] = sprintf(
						'File not found: %s',
						basename( $file_path )
					);
					continue;
				}

				// Check for duplicates
				$duplicate_handling = $options['duplicate_handling'] ?? 'skip';

				if ( 'skip' === $duplicate_handling ) {
					$duplicate_check = $options['duplicate_check'] ?? 'hash';
					$is_duplicate    = Media_Sync::check_duplicate( $file_path, $duplicate_check );

					// Log duplicate check
					$this->logger->log(
						$job_id,
						'info',
						sprintf(
							'Duplicate check for %s: method=%s, result=%s',
							basename( $file_path ),
							$duplicate_check,
							$is_duplicate ? 'DUPLICATE (ID: ' . $is_duplicate . ')' : 'NOT DUPLICATE'
						)
					);

					if ( $is_duplicate ) {
						++$results['skipped'];
						$this->logger->log(
							$job_id,
							'info',
							sprintf( 'Skipped duplicate: %s', basename( $file_path ) )
						);
						continue;
					}
				}               // Import file
				// Map UI option names to helper option names
				$import_options = $options;

				// Always generate thumbnails (skip_thumbnails = false)
				$import_options['skip_thumbnails'] = false;

				// file_operation is passed directly: 'keep', 'copy', or 'move'
				// No need to convert, helper now uses file_operation directly

				// Enable RML folder structure if RML integration is enabled
				if ( ! empty( $options['rml_integration'] ) ) {
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

					$this->logger->log(
						$job_id,
						'error',
						sprintf(
							'Failed to import %s: %s',
							basename( $file_path ),
							$import_result->get_error_message()
						)
					);
				} else {
					++$results['success'];
					$this->logger->log(
						$job_id,
						'info',
						sprintf( 'Imported: %s (ID: %d)', basename( $file_path ), $import_result )
					);
				}
			} catch ( \Exception $e ) {
				++$results['failed'];
				$results['errors'][] = sprintf(
					'%s: %s',
					basename( $file_path ),
					$e->getMessage()
				);

				$this->logger->log(
					$job_id,
					'error',
					sprintf(
						'Exception while importing %s: %s',
						basename( $file_path ),
						$e->getMessage()
					)
				);
			}

			// Prevent memory overflow
			if ( memory_get_usage() > ( $this->get_memory_limit() * 0.8 ) ) {
				$this->logger->log(
					$job_id,
					'warning',
					'Memory limit approaching, stopping batch early'
				);
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

		$this->job_model->update(
			$job_id,
			array(
				'status'       => 'completed',
				'completed_at' => current_time( 'mysql' ),
				'progress'     => 100,
				'result'       => wp_json_encode( $final_result ),
			)
		);

		$this->logger->log(
			$job_id,
			'success',
			sprintf(
				'Media sync job #%d completed. Total: %d, Success: %d, Skipped: %d, Failed: %d',
				$job_id,
				$final_result['processed'],
				$final_result['success'],
				$final_result['skipped'],
				$final_result['failed']
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

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
			$job = $this->job_model->read( $job_id );

			if ( ! $job ) {
				throw new \Exception( sprintf( 'Job #%d not found', $job_id ) );
			}

			// Update status to processing
			$this->job_model->update(
				$job_id,
				array(
					'status'     => 'processing',
					'started_at' => current_time( 'mysql' ),
				)
			);

			$this->logger->log(
				$job_id,
				'info',
				sprintf( 'Started processing media sync job #%d', $job_id )
			);

			// Parse parameters
			$parameters = json_decode( $job->parameters, true );
			$files      = $parameters['files'] ?? array();
			$options    = $parameters['options'] ?? array();
			$offset     = $parameters['offset'] ?? 0;

			// Get files chunk from offset
			$chunk_size = 20; // Process 20 files at a time
			$chunk      = array_slice( $files, $offset, $chunk_size );

			if ( empty( $chunk ) ) {
				// All files processed
				return $this->complete_job( $job_id, $offset );
			}

			$this->logger->log(
				$job_id,
				'info',
				sprintf(
					'Processing batch: files %d-%d of %d',
					$offset + 1,
					min( $offset + $chunk_size, count( $files ) ),
					count( $files )
				)
			);

			// Process batch
			$result = $this->process_batch( $job_id, $chunk, $options );

			// Calculate progress
			$new_offset = $offset + count( $chunk );
			$progress   = round( ( $new_offset / count( $files ) ) * 100 );

			// Update progress
			$this->progress_tracker->update_progress( $job_id, $progress );

			// Check if completed
			if ( $new_offset >= count( $files ) ) {
				return $this->complete_job( $job_id, $new_offset, $result );
			}

			// Update job parameters with new offset
			$parameters['offset'] = $new_offset;

			$this->job_model->update(
				$job_id,
				array(
					'parameters' => wp_json_encode( $parameters ),
					'progress'   => $progress,
				)
			);

			$this->logger->log(
				$job_id,
				'info',
				sprintf(
					'Batch completed. Progress: %d%%. Processed: %d, Success: %d, Skipped: %d, Failed: %d',
					$progress,
					$result['processed'],
					$result['success'],
					$result['skipped'],
					$result['failed']
				)
			);

			return array(
				'completed' => false,
				'offset'    => $new_offset,
				'progress'  => $progress,
				'result'    => $result,
			);

		} catch ( \Exception $e ) {
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
				// Check if file still exists
				if ( ! file_exists( $file ) ) {
					++$results['failed'];
					$results['errors'][] = sprintf(
						'File not found: %s',
						basename( $file )
					);
					continue;
				}

				// Check for duplicates
				$duplicate_handling = $options['duplicate_handling'] ?? 'skip';

				if ( 'skip' === $duplicate_handling ) {
					$duplicate_check = $options['duplicate_check'] ?? 'hash';
					$is_duplicate    = Media_Sync::check_duplicate( $file, $duplicate_check );

					if ( $is_duplicate ) {
						++$results['skipped'];
						$this->logger->log(
							$job_id,
							'info',
							sprintf( 'Skipped duplicate: %s', basename( $file ) )
						);
						continue;
					}
				}

				// Import file
				$import_result = Media_Sync::import_file( $file, $options );

				if ( is_wp_error( $import_result ) ) {
					++$results['failed'];
					$results['errors'][] = sprintf(
						'%s: %s',
						basename( $file ),
						$import_result->get_error_message()
					);

					$this->logger->log(
						$job_id,
						'error',
						sprintf(
							'Failed to import %s: %s',
							basename( $file ),
							$import_result->get_error_message()
						)
					);
				} else {
					++$results['success'];
					$this->logger->log(
						$job_id,
						'info',
						sprintf( 'Imported: %s (ID: %d)', basename( $file ), $import_result )
					);
				}
			} catch ( \Exception $e ) {
				++$results['failed'];
				$results['errors'][] = sprintf(
					'%s: %s',
					basename( $file ),
					$e->getMessage()
				);

				$this->logger->log(
					$job_id,
					'error',
					sprintf(
						'Exception while importing %s: %s',
						basename( $file ),
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
	 * @param array $result Last batch result
	 * @return array Completion result
	 */
	protected function complete_job( $job_id, $processed, $result = null ) {
		// Get accumulated results from job
		$job          = $this->job_model->read( $job_id );
		$current_data = json_decode( $job->result, true );

		// Merge with final result
		$final_result = array(
			'processed' => $processed,
			'success'   => ( $current_data['success'] ?? 0 ) + ( $result['success'] ?? 0 ),
			'skipped'   => ( $current_data['skipped'] ?? 0 ) + ( $result['skipped'] ?? 0 ),
			'failed'    => ( $current_data['failed'] ?? 0 ) + ( $result['failed'] ?? 0 ),
			'errors'    => array_merge(
				$current_data['errors'] ?? array(),
				$result['errors'] ?? array()
			),
		);

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

		// Update progress to 100%
		$this->progress_tracker->update_progress( $job_id, 100 );

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

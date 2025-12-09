<?php
/**
 * Background Processor
 *
 * Processes jobs in the background using WP Cron
 *
 * @package WP_AIE\Model\Queue
 */

namespace WP_AIE\Model\Queue;

use WP_AIE\Model\Job;
use WP_AIE\Helper\Logger;
use WP_AIE\Helper\Progress_Tracker;

/**
 * Background Processor Class
 *
 * Handles background processing of import/export jobs
 *
 * @package WP_AIE\Model\Queue
 */
class Background_Processor {

	/**
	 * Job model instance
	 *
	 * @var Job
	 */
	protected $job_model;

	/**
	 * Batch processor
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
	 * Progress tracker
	 *
	 * @var Progress_Tracker
	 */
	protected $progress_tracker;

	/**
	 * Max retries for failed jobs
	 *
	 * @var int
	 */
	protected $max_retries = 3;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->job_model        = WP_AIE()->Model->job;
		$this->batch_processor  = new Batch_Processor();
		$this->logger           = new Logger();
		$this->progress_tracker = new Progress_Tracker();
	}

	/**
	 * Process next job in queue
	 *
	 * @return bool True if job was processed
	 */
	public function process_next_job() {
		// Get next pending job
		$job = $this->get_next_job();

		if ( ! $job ) {
			return false;
		}

		// Process the job
		$this->process_job( $job );

		return true;
	}

	/**
	 * Get next pending job
	 *
	 * @return array|null Job data or null
	 */
	protected function get_next_job() {
		global $wpdb;

		$table = $wpdb->prefix . 'aie_jobs';

		// Get oldest pending or processing job
		$job = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} 
				WHERE status IN ('pending', 'processing') 
				ORDER BY created_at ASC 
				LIMIT 1"
			),
			ARRAY_A
		);

		return $job ? $job : null;
	}

	/**
	 * Process a job
	 *
	 * @param array $job Job data
	 */
	protected function process_job( $job ) {
		$job_id = $job['id'];

		try {
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
				sprintf( 'Started processing job #%d', $job_id )
			);

			// Parse parameters
			$parameters = ! empty( $job['parameters'] ) ? json_decode( $job['parameters'], true ) : [];

			// Process based on job type
			if ( 'import' === $job['type'] ) {
				$result = $this->process_import_job( $job_id, $parameters );
			} elseif ( 'export' === $job['type'] ) {
				$result = $this->process_export_job( $job_id, $parameters );
			} elseif ( 'media_sync' === $job['type'] ) {
				$result = $this->process_media_sync_job( $job_id, $parameters );
				error_log( sprintf( '[Background Processor] Media sync job #%d result: %s', $job_id, wp_json_encode( $result ) ) );
			} else {
				throw new \Exception( 'Invalid job type: ' . $job['type'] );
			}           // Check if completed or needs to continue
			if ( isset( $result['completed'] ) && $result['completed'] ) {
				error_log( sprintf( '[Background Processor] Job #%d COMPLETED, calling complete_job()', $job_id ) );
				$this->complete_job( $job_id, $result );
			} else {
				// Job still processing - schedule next batch immediately
				error_log( sprintf( '[Background Processor] Job #%d NOT COMPLETED, scheduling next batch...', $job_id ) );

				$this->logger->log(
					$job_id,
					'info',
					sprintf(
						'Job #%d batch completed, scheduling next batch (progress: %s%%)',
						$job_id,
						isset( $result['progress'] ) ? $result['progress'] : 'unknown'
					)
				);

				// Schedule immediate next run for continued processing
				$this->schedule_next_run( 0 );
				error_log( sprintf( '[Background Processor] Scheduled next cron run for job #%d', $job_id ) );

				// For local development: spawn immediate cron check
				// This ensures processing continues even if WP-Cron is not triggered by page load
				if ( defined( 'DOING_CRON' ) && ! DOING_CRON ) {
					spawn_cron();
					error_log( sprintf( '[Background Processor] Called spawn_cron() for job #%d', $job_id ) );
				}

				// Fallback: trigger via AJAX for reliability (non-blocking)
				$this->trigger_ajax_processing( $job_id );
				error_log( sprintf( '[Background Processor] Triggered AJAX processing for job #%d', $job_id ) );
			}
		} catch ( \Exception $e ) {
			$this->handle_job_error( $job_id, $e );
		}
	}

	/**
	 * Process import job
	 *
	 * @param int   $job_id Job ID
	 * @param array $parameters Job parameters
	 * @return array Processing result
	 */
	protected function process_import_job( $job_id, $parameters ) {
		$file_path   = $parameters['file_path'] ?? '';
		$import_type = $parameters['import_type'] ?? 'post';
		$format      = $parameters['format'] ?? 'csv';
		$offset      = $parameters['offset'] ?? 0;

		// Parse file data
		$format_handler = \WP_AIE\Model\Format\Format_Factory::create( $format );
		$data           = $format_handler->parse( $file_path );

		// Get data chunk from offset
		$chunk_size = $this->batch_processor->get_batch_size();
		$chunk      = array_slice( $data, $offset, $chunk_size );

		if ( empty( $chunk ) ) {
			return array(
				'completed' => true,
				'processed' => $offset,
			);
		}

		// Get importer
		$importer = \WP_AIE\Model\Import\Importer_Factory::create( $import_type );
		$importer->set_duplicate_handling( $parameters['duplicate_handling'] ?? 'skip' );

		// Process batch
		$result = $this->batch_processor->process(
			$chunk,
			function ( $item ) use ( $importer ) {
				return $importer->import( $item );
			}
		);

		// Update result with offset
		$result['offset']    = $offset + $result['processed'];
		$result['completed'] = $result['offset'] >= count( $data );

		return $result;
	}

	/**
	 * Process export job
	 *
	 * @param int   $job_id Job ID
	 * @param array $parameters Job parameters
	 * @return array Processing result
	 */
	protected function process_export_job( $job_id, $parameters ) {
		$export_type = $parameters['export_type'] ?? 'post';
		$format      = $parameters['format'] ?? 'csv';
		$filters     = $parameters['filters'] ?? array();
		$offset      = $parameters['offset'] ?? 0;

		// Get exporter
		$exporter = \WP_AIE\Model\Export\Exporter_Factory::get_exporter( $export_type, $job_id );

		if ( is_wp_error( $exporter ) ) {
			return array(
				'error' => $exporter->get_error_message(),
			);
		}

		// Set filters
		if ( ! empty( $filters ) ) {
			$exporter->set_filters( $filters );
		}

		// Get batch size from parameters or use default
		$batch_size = isset( $parameters['options']['items_per_iteration'] )
			? (int) $parameters['options']['items_per_iteration']
			: $this->batch_processor->get_batch_size();

		// Export data
		$data = $exporter->export( $offset, $batch_size );

		if ( empty( $data ) ) {
			// Finalize export file
			$this->finalize_export( $job_id, $format, $parameters );

			return array(
				'completed' => true,
				'processed' => $offset,
			);
		}

		// Append to file
		$this->append_export_data( $job_id, $format, $data, $offset === 0 );

		return array(
			'completed' => false,
			'processed' => count( $data ),
			'offset'    => $offset + count( $data ),
		);
	}

	/**
	 * Append data to export file
	 *
	 * @param int    $job_id Job ID
	 * @param string $format File format
	 * @param array  $data Export data
	 * @param bool   $first_batch Is first batch
	 */
	protected function append_export_data( $job_id, $format, $data, $first_batch = false ) {
		$upload_dir = wp_upload_dir();
		$file_path  = $upload_dir['basedir'] . '/wp-aie-exports/job-' . $job_id . '.' . $format;

		// Create directory if needed
		wp_mkdir_p( dirname( $file_path ) );

		// Get format handler
		$format_handler = \WP_AIE\Model\Format\Format_Factory::create( $format );

		// Generate content (note: this is legacy code, new exports use Export_Processor)
		$result = $format_handler->generate( $data, $file_path );

		if ( is_wp_error( $result ) ) {
			return;
		}

		// File is already written by generate(), no need to append
	}

	/**
	 * Finalize export file
	 *
	 * @param int    $job_id Job ID
	 * @param string $format File format
	 * @param array  $parameters Job parameters
	 */
	protected function finalize_export( $job_id, $format, $parameters ) {
		// Format-specific finalization (e.g., close XML root tag)
		// This would be handled by format handlers in a real implementation
	}

	/**
	 * Complete job
	 *
	 * @param int   $job_id Job ID
	 * @param array $result Processing result
	 */
	protected function complete_job( $job_id, $result ) {
		$this->job_model->update(
			$job_id,
			array(
				'status'       => 'completed',
				'completed_at' => current_time( 'mysql' ),
				'result'       => wp_json_encode( $result ),
			)
		);

		$this->logger->log(
			$job_id,
			'success',
			sprintf(
				'Job #%d completed. Processed: %d, Success: %d, Failed: %d',
				$job_id,
				$result['processed'] ?? 0,
				$result['success'] ?? 0,
				$result['failed'] ?? 0
			)
		);

		// Update progress to 100%
		$this->progress_tracker->update_progress( $job_id, 100 );
	}

	/**
	 * Update job progress
	 *
	 * @param int   $job_id Job ID
	 * @param array $result Processing result
	 */
	protected function update_job_progress( $job_id, $result ) {
		// Update job parameters with new offset
		$job        = $this->job_model->find( $job_id );
		$parameters = json_decode( $job->parameters, true );

		$parameters['offset'] = $result['offset'] ?? 0;

		$this->job_model->update(
			$job_id,
			array(
				'parameters' => wp_json_encode( $parameters ),
			)
		);

		$this->logger->log(
			$job_id,
			'info',
			sprintf(
				'Progress updated. Processed: %d items',
				$result['processed'] ?? 0
			)
		);
	}

	/**
	 * Handle job error
	 *
	 * @param int        $job_id Job ID
	 * @param \Exception $e Exception
	 */
	protected function handle_job_error( $job_id, $e ) {
		// Get current retry count
		$job     = $this->job_model->find( $job_id );
		$retries = isset( $job->retries ) ? (int) $job->retries : 0;

		$this->logger->log(
			$job_id,
			'error',
			sprintf(
				'Job error (retry %d/%d): %s',
				$retries + 1,
				$this->max_retries,
				$e->getMessage()
			)
		);

		// Check if should retry
		if ( $retries < $this->max_retries ) {
			// Increment retry count and reset to pending
			$this->job_model->update(
				$job_id,
				array(
					'status'  => 'pending',
					'retries' => $retries + 1,
				)
			);

			// Schedule retry
			$this->schedule_next_run( 60 ); // Retry after 1 minute

		} else {
			// Max retries reached, mark as failed
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
		}
	}

	/**
	 * Process media sync job
	 *
	 * @param int   $job_id Job ID
	 * @param array $parameters Job parameters
	 * @return array Processing result
	 */
	protected function process_media_sync_job( $job_id, $parameters ) {
		$processor = new Media_Sync_Processor();
		return $processor->process( $job_id );
	}

	/**
	 * Schedule next run
	 *
	 * @param int $delay Optional. Delay in seconds (default: 0)
	 */
	protected function schedule_next_run( $delay = 0 ) {
		// Always schedule immediate processing for continued jobs
		// Don't check for existing scheduled events - we want to trigger NOW
		wp_schedule_single_event(
			time() + $delay,
			'aie_process_queue'
		);
	}

	/**
	 * Trigger next batch via AJAX (non-blocking)
	 *
	 * @param int $job_id Job ID
	 */
	protected function trigger_ajax_processing( $job_id ) {
		// Only trigger for media_sync jobs (they have dedicated AJAX endpoint)
		$job = $this->job_model->get( $job_id );
		if ( ! $job || $job['type'] !== 'media_sync' ) {
			return;
		}

		// Trigger via non-blocking HTTP request
		wp_remote_post(
			admin_url( 'admin-ajax.php' ),
			array(
				'timeout'   => 0.01,
				'blocking'  => false,
				'sslverify' => false,
				'body'      => array(
					'action' => 'aie_process_media_sync_batch',
					'nonce'  => wp_create_nonce( 'aie_process_media_sync_batch' ),
					'job_id' => $job_id,
				),
				'cookies'   => $_COOKIE,
			)
		);
	}

	/**
	 * Set batch size
	 *
	 * @param int $size Batch size
	 */
	public function set_batch_size( $size ) {
		$this->batch_processor->set_batch_size( $size );
	}

	/**
	 * Set max retries
	 *
	 * @param int $retries Max retries
	 */
	public function set_max_retries( $retries ) {
		$this->max_retries = max( 0, (int) $retries );
	}
}

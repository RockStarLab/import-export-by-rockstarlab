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
		$this->job_model        = new Job();
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
			$parameters = json_decode( $job['parameters'], true );

			// Process based on job type
			if ( 'import' === $job['type'] ) {
				$result = $this->process_import_job( $job_id, $parameters );
			} elseif ( 'export' === $job['type'] ) {
				$result = $this->process_export_job( $job_id, $parameters );
			} else {
				throw new \Exception( 'Invalid job type: ' . $job['type'] );
			}

			// Check if completed or needs to continue
			if ( $result['completed'] ) {
				$this->complete_job( $job_id, $result );
			} else {
				// Update progress and reschedule
				$this->update_job_progress( $job_id, $result );
				$this->schedule_next_run();
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
		$file_path    = $parameters['file_path'] ?? '';
		$content_type = $parameters['content_type'] ?? 'post';
		$format       = $parameters['format'] ?? 'csv';
		$offset       = $parameters['offset'] ?? 0;

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
		$importer = \WP_AIE\Model\Import\Importer_Factory::create( $content_type );
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
		$content_type = $parameters['content_type'] ?? 'post';
		$format       = $parameters['format'] ?? 'csv';
		$filters      = $parameters['filters'] ?? array();
		$offset       = $parameters['offset'] ?? 0;

		// Get exporter
		$exporter = \WP_AIE\Model\Export\Exporter_Factory::create( $content_type );

		// Set filters
		if ( ! empty( $filters ) ) {
			$exporter->set_filters( $filters );
		}

		// Get batch size
		$batch_size = $this->batch_processor->get_batch_size();

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

		// Generate content
		$content = $format_handler->generate( $data );

		// Append to file
		if ( $first_batch ) {
			file_put_contents( $file_path, $content );
		} else {
			file_put_contents( $file_path, $content, FILE_APPEND );
		}
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
		$job        = $this->job_model->get( $job_id );
		$parameters = json_decode( $job['parameters'], true );

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
		$job     = $this->job_model->get( $job_id );
		$retries = isset( $job['retries'] ) ? (int) $job['retries'] : 0;

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
	 * Schedule next run
	 *
	 * @param int $delay Optional. Delay in seconds (default: 0)
	 */
	protected function schedule_next_run( $delay = 0 ) {
		if ( ! wp_next_scheduled( 'aie_process_queue' ) ) {
			wp_schedule_single_event(
				time() + $delay,
				'aie_process_queue'
			);
		}
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

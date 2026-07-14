<?php
/**
 * Background Processor
 *
 * Processes jobs in the background using WP Cron
 *
 * @package RockStarLab\ImportExport\Model\Queue
 */

namespace RockStarLab\ImportExport\Model\Queue;

use RockStarLab\ImportExport\Helper\Ajax_Security;

use RockStarLab\ImportExport\Model\Job;
use RockStarLab\ImportExport\Helper\Progress_Tracker;

defined( 'ABSPATH' ) || exit;

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
		$this->job_model        = rsl_ie()->Model->job;
		$this->batch_processor  = new Batch_Processor();
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

		$table = $wpdb->prefix . 'rsl_ie_jobs';

		// Get oldest pending or processing job
		$job = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Polling the plugin's custom jobs table.
			$wpdb->prepare( "SELECT * FROM %i WHERE status IN ('pending', 'processing') ORDER BY created_at ASC LIMIT 1", $table ),
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

			// Parse parameters
			$parameters = ! empty( $job['parameters'] ) ? json_decode( $job['parameters'], true ) : [];

			// Process based on job type
			if ( 'import' === $job['type'] ) {
				$result = $this->process_import_job( $job_id, $parameters );
			} elseif ( 'export' === $job['type'] ) {
				// Export_Processor is also used by the AJAX batch endpoint. Delegate
				// cron exports to it so both routes use identical exporter mappings,
				// filters, pagination, progress tracking, and file finalization.
				$this->process_export_job( $job_id, $parameters );
				return;
			} elseif ( 'media_sync' === $job['type'] ) {
				$result = $this->process_media_sync_job( $job_id, $parameters );
			} elseif ( 'update' === $job['type'] ) {
				$result = $this->process_update_job( $job_id );
			} else {
				throw new \Exception( 'Invalid job type: ' . $job['type'] );
			}           // Check if completed or needs to continue
			if ( ! empty( $result['error'] ) ) {
				return;
			}

			if ( isset( $result['completed'] ) && $result['completed'] ) {
				$this->complete_job( $job_id, $result );
			} else {
				// Job still processing - schedule next batch immediately

				// Schedule immediate next run for continued processing
				$this->schedule_next_run( 0 );

				// For local development: spawn immediate cron check
				// This ensures processing continues even if WP-Cron is not triggered by page load
				if ( defined( 'DOING_CRON' ) && ! DOING_CRON ) {
					spawn_cron();
				}

				// Fallback: trigger via AJAX for reliability (non-blocking)
				$this->trigger_ajax_processing( $job_id );
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
		$job         = $this->job_model->find( $job_id );
		$file_path   = $job ? $job->file_path : '';
		$import_type = $parameters['import_type'] ?? 'post';
		$format      = $parameters['format'] ?? 'csv';
		$delimiter   = $parameters['delimiter'] ?? ',';
		$mapping     = $parameters['mapping'] ?? [];
		$options     = $parameters['options'] ?? [];
		$offset      = (int) ( $parameters['offset'] ?? 0 );
		$batch_size  = max( 1, (int) ( $options['batch_size'] ?? 50 ) );

		if ( ! $job || empty( $file_path ) ) {
			throw new \Exception( 'Import source file is missing.' );
		}

		$importer = \RockStarLab\ImportExport\Model\Import\Importer_Factory::get_importer( $import_type, $job_id );
		if ( is_wp_error( $importer ) ) {
			throw new \Exception( esc_html( $importer->get_error_message() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		if ( ! isset( $parameters['prepared_data'] ) ) {
			$format_handler = \RockStarLab\ImportExport\Model\Format\Format_Factory::create( $format );
			$data           = $format_handler->parse( $file_path, [ 'delimiter' => $delimiter ] );

			if ( is_wp_error( $data ) ) {
				throw new \Exception( esc_html( $data->get_error_message() ) ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
			}
			if ( ! is_array( $data ) ) {
				throw new \Exception( 'Failed to parse import file: unexpected format.' );
			}

				$parameters['prepared_data'] = $importer->prepare( $data, $mapping );
			if ( class_exists( \RockStarLab\ImportExport\Model\Import\Comment_Importer::class ) && $importer instanceof \RockStarLab\ImportExport\Model\Import\Comment_Importer ) {
				foreach ( $parameters['prepared_data'] as $row_index => &$prepared_row ) {
					if ( isset( $data[ $row_index ]['comment_date'] ) ) {
						$prepared_row['comment_date'] = (string) $data[ $row_index ]['comment_date'];
					}
					if ( isset( $data[ $row_index ]['comment_date_gmt'] ) ) {
						$prepared_row['comment_date_gmt'] = (string) $data[ $row_index ]['comment_date_gmt'];
					}
					if ( isset( $data[ $row_index ]['comment_ID'] ) ) {
						$prepared_row['_rsl_ie_source_comment_id'] = absint( $data[ $row_index ]['comment_ID'] );
					}
					if ( isset( $data[ $row_index ]['comment_parent'] ) ) {
						$prepared_row['_rsl_ie_source_comment_parent_id'] = absint( $data[ $row_index ]['comment_parent'] );
					}
					if ( isset( $data[ $row_index ]['post_permalink'] ) ) {
						$prepared_row['_rsl_ie_source_post_permalink'] = (string) $data[ $row_index ]['post_permalink'];
					}
					if ( isset( $data[ $row_index ]['post_slug'] ) ) {
						$prepared_row['_rsl_ie_source_post_slug'] = (string) $data[ $row_index ]['post_slug'];
					}
					if ( isset( $data[ $row_index ]['post_type'] ) ) {
						$prepared_row['_rsl_ie_source_post_type'] = (string) $data[ $row_index ]['post_type'];
					}
				}
				unset( $prepared_row );
			}
				$parameters['total_items']   = count( $parameters['prepared_data'] );
			$parameters['offset']            = 0;
			$parameters['cumulative_result'] = [
				'total'   => $parameters['total_items'],
				'success' => 0,
				'skipped' => 0,
				'failed'  => 0,
				'updated' => 0,
				'created' => 0,
				'errors'  => [],
			];
			$offset                          = 0;
		}

		$prepared   = $parameters['prepared_data'];
		$total      = (int) $parameters['total_items'];
		$cumulative = $parameters['cumulative_result'];
		$chunk      = array_slice( $prepared, $offset, $batch_size );

		if ( empty( $chunk ) ) {
			return [
				'completed' => true,
				'processed' => $offset,
				'total'     => $total,
			];
		}

			$importer->set_options( $options );
		foreach ( $chunk as $index => $item ) {
			$item_result = $importer->import_item( $item, $offset + $index );
			if ( is_wp_error( $item_result ) ) {
				++$cumulative['failed'];
				$cumulative['errors'][] = [
					'row'     => $offset + $index + 1,
					'message' => $item_result->get_error_message(),
				];
			} elseif ( 'skipped' === $item_result ) {
				++$cumulative['skipped'];
			} elseif ( 'updated' === $item_result ) {
				++$cumulative['updated'];
				++$cumulative['success'];
			} else {
				++$cumulative['created'];
				++$cumulative['success'];
			}

			if ( ! is_wp_error( $item_result ) && 'skipped' !== $item_result && class_exists( \RockStarLab\ImportExport\Model\Import\Comment_Importer::class ) && $importer instanceof \RockStarLab\ImportExport\Model\Import\Comment_Importer ) {
				$this->preserve_imported_comment_dates( $item_result, $item );
			}
		}

		$new_offset                      = $offset + count( $chunk );
		$parameters['offset']            = $new_offset;
		$parameters['cumulative_result'] = $cumulative;
		$completed                       = $new_offset >= $total;

		$this->job_model->update(
			$job_id,
			[
				'parameters'      => wp_json_encode( $parameters ),
				'total_items'     => $total,
				'processed_items' => $new_offset,
				'success_items'   => (int) $cumulative['success'],
				'failed_items'    => (int) $cumulative['failed'],
				'progress'        => $total > 0 ? round( ( $new_offset / $total ) * 100 ) : 100,
				'result'          => wp_json_encode( $cumulative ),
			]
		);

			return [
				'completed' => $completed,
				'processed' => $new_offset,
				'total'     => $total,
			];
	}

		/**
		 * Preserve source comment dates after WordPress insert/update filters run.
		 *
		 * @param int|string $item_result Import result.
		 * @param array      $item        Prepared import item.
		 * @return void
		 */
	private function preserve_imported_comment_dates( $item_result, $item ) {
		$comment_id = is_numeric( $item_result ) ? absint( $item_result ) : 0;
		if ( $comment_id <= 0 && ! empty( $item['_rsl_ie_source_comment_id'] ) ) {
			$comment_id = $this->find_imported_comment_id( $item['_rsl_ie_source_comment_id'] );
		}

		if ( $comment_id <= 0 ) {
			return;
		}

		$update = [];
		if ( isset( $item['comment_date'] ) && '' !== (string) $item['comment_date'] ) {
			$update['comment_date'] = (string) $item['comment_date'];
		}
		if ( isset( $item['comment_date_gmt'] ) && '' !== (string) $item['comment_date_gmt'] ) {
			$update['comment_date_gmt'] = (string) $item['comment_date_gmt'];
		}
		if ( empty( $update ) ) {
			return;
		}

		$update['comment_ID'] = $comment_id;
		wp_update_comment( $update );
	}

	/**
	 * Find an imported comment by its source ID without a direct or meta query.
	 *
	 * @param int|string $source_id Source-site comment ID.
	 * @return int Local comment ID, or zero when not found.
	 */
	private function find_imported_comment_id( $source_id ) {
		$source_id = (string) absint( $source_id );
		$offset    = 0;
		$page_size = 200;

		do {
			$comment_ids = get_comments(
				[
					'fields'  => 'ids',
					'number'  => $page_size,
					'offset'  => $offset,
					'orderby' => 'comment_ID',
					'order'   => 'DESC',
					'status'  => 'all',
				]
			);

			foreach ( $comment_ids as $comment_id ) {
				if ( $source_id === (string) get_comment_meta( $comment_id, '_aie_source_comment_id', true ) ) {
					return absint( $comment_id );
				}
			}

			$offset += $page_size;
		} while ( count( $comment_ids ) === $page_size );

		return 0;
	}

			/**
			 * Process export job
			 *
			 * @param int   $job_id Job ID
			 * @param array $parameters Job parameters
			 * @return array Processing result
			 */
	protected function process_export_job( $job_id, $parameters ) {
		unset( $parameters );
		$processor = new Export_Processor();
		return $processor->process( $job_id );
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
		$file_path  = $upload_dir['basedir'] . '/rsl-ie-exports/job-' . $job_id . '.' . $format;

		// Create directory if needed
		wp_mkdir_p( dirname( $file_path ) );

		// Get format handler
		$format_handler = \RockStarLab\ImportExport\Model\Format\Format_Factory::create( $format );

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
		$job  = $this->job_model->find( $job_id );
		$data = array(
			'status'       => 'completed',
			'progress'     => 100,
			'completed_at' => current_time( 'mysql' ),
		);

		// Import/update/media processors may already have stored richer result
		// details. Keep them rather than replacing them with batch metadata.
		if ( ! $job || empty( $job->result ) ) {
			$data['result'] = wp_json_encode( $result );
		}

		$this->job_model->update( $job_id, $data );
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
	 * Process a content update batch.
	 *
	 * @param int $job_id Job ID.
	 * @return array
	 */
	protected function process_update_job( $job_id ) {
		$result = apply_filters( 'rsl_ie_process_update_job', null, $job_id );

		if ( null !== $result ) {
			return $result;
		}

		throw new \RuntimeException( 'No Content Updater processor is registered.' );
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
			'rsl_ie_process_queue'
		);
	}

	/**
	 * Trigger next batch via AJAX (non-blocking)
	 *
	 * @param int $job_id Job ID
	 */
	protected function trigger_ajax_processing( $job_id ) {
		// Only trigger for media_sync jobs (they have dedicated AJAX endpoint)
		$job = $this->job_model->find( $job_id );
		if ( ! $job || $job->type !== 'media_sync' ) {
			return;
		}

		// Trigger via non-blocking HTTP request
		wp_remote_post(
			admin_url( 'admin-ajax.php' ),
			array(
				'timeout'  => 0.01,
				'blocking' => false,
				'body'     => array(
					'action' => 'rsl_ie_process_media_sync_batch',
					'nonce'  => Ajax_Security::create_nonce( 'rsl_ie_process_media_sync_batch' ),
					'job_id' => $job_id,
				),
				'cookies'  => $this->get_auth_cookies_for_request(),
			)
		);
	}

	/**
	 * Get sanitized WordPress auth cookies for the internal AJAX request.
	 *
	 * @return \WP_Http_Cookie[] Cookies for wp_remote_post().
	 */
	protected function get_auth_cookies_for_request() {
		$cookie_names = array_filter(
			array(
				defined( 'LOGGED_IN_COOKIE' ) ? LOGGED_IN_COOKIE : '',
				defined( 'AUTH_COOKIE' ) ? AUTH_COOKIE : '',
				defined( 'SECURE_AUTH_COOKIE' ) ? SECURE_AUTH_COOKIE : '',
			)
		);

		$cookies = array();

		foreach ( $cookie_names as $cookie_name ) {
			// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Cookie value is unslashed and sanitized before use.
			$raw_value = isset( $_COOKIE[ $cookie_name ] ) ? wp_unslash( $_COOKIE[ $cookie_name ] ) : '';
			if ( ! is_scalar( $raw_value ) || '' === $raw_value ) {
				continue;
			}

			$cookies[] = new \WP_Http_Cookie(
				array(
					'name'  => sanitize_text_field( $cookie_name ),
					'value' => sanitize_text_field( $raw_value ),
				)
			);
		}

		return $cookies;
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

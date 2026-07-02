<?php
/**
 * AI URL Import Processor
 *
 * Processes AI URL import jobs in background
 *
 * @package RockStarLab\ImportExport\Model\Queue
 */

namespace RockStarLab\ImportExport\Model\Queue;

use RockStarLab\ImportExport\Model\Job;
use RockStarLab\ImportExport\Helper\AI_Content_Extractor;

defined( 'ABSPATH' ) || exit;

class AI_URL_Import_Processor {

	/**
	 * Job model instance
	 *
	 * @var Job
	 */
	protected $job_model;

	/**
	 * AI Content Extractor instance
	 *
	 * @var AI_Content_Extractor
	 */
	protected $ai_extractor;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->job_model    = rsl_ie()->Model->job;
		$this->ai_extractor = new AI_Content_Extractor();
	}

	/**
	 * Process AI URL import job
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

			$urls           = $parameters['urls'] ?? [];
			$post_type      = $parameters['post_type'] ?? 'post';
			$post_status    = $parameters['post_status'] ?? 'draft';
			$content_field  = $parameters['content_field'] ?? 'post_content';
			$import_images  = $parameters['import_images'] ?? true;
			$import_excerpt = $parameters['import_excerpt'] ?? true;
			$request_delay  = $parameters['request_delay'] ?? ( $parameters['timeout'] ?? 0 );

			// Get current index
			$current_index = (int) ( $job->processed_items ?? 0 );

			// Get total count
			$total_count = count( $urls );

			// Update total items on first run
			if ( 0 === $current_index ) {
				$this->job_model->update(
					$job_id,
					[
						'total_items' => $total_count,
					]
				);
			}

			// Check if completed
			if ( $current_index >= $total_count ) {
				$this->complete_job( $job_id );
				return [
					'status'    => 'completed',
					'completed' => true,
				];
			}

			// Process current URL
			$url = $urls[ $current_index ];

			$import_log = [];

			try {
				// Extract content using AI
				$extracted_data = $this->ai_extractor->extract_from_url( $url, $request_delay );

				if ( is_wp_error( $extracted_data ) ) {
					throw new \Exception( $extracted_data->get_error_message() );
				}

				// Prepare post data
				$post_data = [
					'post_type'   => $post_type,
					'post_status' => $post_status,
					'post_title'  => $extracted_data['title'] ?? 'Untitled',
				];

				// Handle excerpt
				if ( $import_excerpt && ! empty( $extracted_data['excerpt'] ) ) {
					$post_data['post_excerpt'] = $extracted_data['excerpt'];
				}

				// Handle content field
				if ( 'post_content' === $content_field ) {
					$post_data['post_content'] = $extracted_data['content'] ?? '';
				}

				// Insert post
				$post_id = wp_insert_post( $post_data, true );

				if ( is_wp_error( $post_id ) ) {
					throw new \Exception( $post_id->get_error_message() );
				}

				// Store source URL as meta
				update_post_meta( $post_id, '_rsl_ie_source_url', $url );

				// Handle ACF field for content
				if ( 'post_content' !== $content_field ) {
					if ( function_exists( 'update_field' ) ) {
						update_field( $content_field, $extracted_data['content'] ?? '', $post_id );
					} else {
						update_post_meta( $post_id, $content_field, $extracted_data['content'] ?? '' );
					}
				}

				// Handle featured image
				if ( $import_images && ! empty( $extracted_data['featured_image'] ) ) {
					$image_id = $this->ai_extractor->import_image(
						$extracted_data['featured_image'],
						$extracted_data['title'] ?? 'Featured Image'
					);

					if ( ! is_wp_error( $image_id ) ) {
						set_post_thumbnail( $post_id, $image_id );
					}
				}

				// Handle additional images
				if ( $import_images && ! empty( $extracted_data['images'] ) ) {
					$imported_images = [];
					foreach ( $extracted_data['images'] as $image ) {
						$image_url = is_array( $image ) ? ( $image['url'] ?? '' ) : $image;
						$alt_text  = is_array( $image ) ? ( $image['alt'] ?? '' ) : '';

						if ( empty( $image_url ) ) {
							continue;
						}

						// Skip if it's the featured image
						if ( $image_url === $extracted_data['featured_image'] ) {
							continue;
						}

						$image_id = $this->ai_extractor->import_image( $image_url, $alt_text );

						if ( ! is_wp_error( $image_id ) ) {
							$imported_images[] = $image_id;
						}
					}

					// Store imported image IDs as meta
					if ( ! empty( $imported_images ) ) {
						update_post_meta( $post_id, '_rsl_ie_imported_images', $imported_images );
					}
				}

				// Success log
				$import_log[] = [
					'url'       => $url,
					'status'    => 'success',
					'post_id'   => $post_id,
					'message'   => sprintf( 'Successfully imported: %s', $extracted_data['title'] ?? 'Post' ),
					'timestamp' => current_time( 'mysql' ),
				];

			} catch ( \Exception $e ) {
				// Error log
				$import_log[] = [
					'url'       => $url,
					'status'    => 'error',
					'message'   => $e->getMessage(),
					'timestamp' => current_time( 'mysql' ),
				];
			}

			// Update job progress
			$new_processed = $current_index + 1;
			$progress      = ( $new_processed / $total_count ) * 100;

			// Count success/failed from import_log
			$success_increment = 0;
			$failed_increment  = 0;

			foreach ( $import_log as $log_entry ) {
				if ( 'success' === $log_entry['status'] ) {
					++$success_increment;
				} elseif ( 'error' === $log_entry['status'] ) {
					++$failed_increment;
				}
			}

			$this->job_model->update(
				$job_id,
				[
					'processed_items' => $new_processed,
					'success_items'   => ( $job->success_items ?? 0 ) + $success_increment,
					'failed_items'    => ( $job->failed_items ?? 0 ) + $failed_increment,
					'progress'        => $progress,
				]
			);

			// Check if completed
			$completed = ( $new_processed >= $total_count );

			if ( $completed ) {
				$this->complete_job( $job_id );
				return [
					'status'    => 'completed',
					'completed' => true,
				];
			}

			// Return processing status (JavaScript will call next batch)
			return [
				'status'    => 'processing',
				'completed' => false,
				'progress'  => $progress,
			];

		} catch ( \Exception $e ) {
			// Job failed
			$this->job_model->update(
				$job_id,
				[
					'status'       => 'failed',
					'error'        => $e->getMessage(),
					'completed_at' => current_time( 'mysql' ),
				]
			);

			return [
				'status'    => 'failed',
				'completed' => false,
				'error'     => $e->getMessage(),
			];
		}
	}

	/**
	 * Complete job
	 *
	 * @param int $job_id Job ID
	 * @return void
	 */
	protected function complete_job( $job_id ) {
		$this->job_model->update(
			$job_id,
			[
				'status'       => 'completed',
				'progress'     => 100,
				'completed_at' => current_time( 'mysql' ),
			]
		);
	}
}

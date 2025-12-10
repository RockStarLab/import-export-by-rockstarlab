<?php
/**
 * Update Processor
 *
 * Processes content update jobs in batches
 *
 * @package WP_AIE\Model\Queue
 */

namespace WP_AIE\Model\Queue;

use WP_AIE\Model\Job;
use WP_AIE\Model\Export\Exporter_Factory;
use WP_AIE\Helper\Logger;
use WP_AIE\Helper\Function_Executor;

/**
 * Update Processor Class
 *
 * Handles background processing of content update jobs
 *
 * @package WP_AIE\Model\Queue
 */
class Update_Processor {

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
	 * Function executor instance
	 *
	 * @var Function_Executor
	 */
	protected $function_executor;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->logger            = new Logger();
		$this->job_model         = WP_AIE()->Model->job;
		$this->function_executor = new Function_Executor();
	}

	/**
	 * Process update job
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

			$content_type    = $parameters['content_type'];
			$options         = $parameters['options'] ?? [];
			$fields          = $parameters['fields'] ?? [];
			$field_functions = $parameters['field_functions'] ?? [];

			// Get batch size
			$batch_size = isset( $options['items_per_iteration'] ) ? (int) $options['items_per_iteration'] : 10;

			// Get current offset
			$current_offset = (int) ( $job->processed_items ?? 0 );

			// Get exporter to fetch items
			$exporter = Exporter_Factory::get_exporter( $content_type, $job_id );
			if ( is_wp_error( $exporter ) ) {
				throw new \Exception( $exporter->get_error_message() );
			}

			// Build fetch options
			$fetch_options = array_merge(
				$options,
				[
					'fields' => $fields,
					'limit'  => $batch_size,
					'offset' => $current_offset,
				]
			);

			// Get total count on first batch
			if ( 0 === $current_offset ) {
				$total_count = Exporter_Factory::get_count( $content_type, $fetch_options );

				$this->job_model->update(
					$job_id,
					[
						'total_items' => $total_count,
					]
				);
			} else {
				$total_count = (int) $job->total_items;
			}

			// Fetch batch items
			$export_result = $exporter->export( $fetch_options );

			if ( is_wp_error( $export_result ) ) {
				throw new \Exception( $export_result->get_error_message() );
			}

			$batch_items = $export_result['data'] ?? [];
			$batch_count = count( $batch_items );

			// Update items with functions
			$update_stats = $this->update_items( $batch_items, $fields, $field_functions, $content_type );

			// Update progress
			$new_processed = $current_offset + $batch_count;
			$updated_items = (int) $job->imported_items + $update_stats['updated'];
			$skipped_items = (int) $job->skipped_items + $update_stats['skipped'];
			$error_items   = (int) $job->error_items + $update_stats['errors'];
			$progress      = $total_count > 0 ? ( $new_processed / $total_count ) * 100 : 0;

			$this->job_model->update(
				$job_id,
				[
					'processed_items' => $new_processed,
					'imported_items'  => $updated_items,
					'skipped_items'   => $skipped_items,
					'error_items'     => $error_items,
					'progress'        => $progress,
				]
			);

			// Log batch results
			$this->logger->log(
				$job_id,
				'info',
				sprintf(
					'Batch processed: %d items, %d updated, %d skipped, %d errors',
					$batch_count,
					$update_stats['updated'],
					$update_stats['skipped'],
					$update_stats['errors']
				)
			);

			// Check if completed
			$completed = ( $new_processed >= $total_count ) || ( $batch_count < $batch_size );

			if ( $completed ) {
				$this->job_model->update(
					$job_id,
					[
						'status'       => 'completed',
						'progress'     => 100,
						'completed_at' => current_time( 'mysql' ),
					]
				);

				$this->logger->log(
					$job_id,
					'info',
					sprintf(
						'Update completed: %d total items, %d updated, %d skipped, %d errors',
						$new_processed,
						$updated_items,
						$skipped_items,
						$error_items
					)
				);

				return [
					'completed'     => true,
					'processed'     => $new_processed,
					'total'         => $total_count,
					'updated_items' => $updated_items,
					'skipped_items' => $skipped_items,
					'error_items'   => $error_items,
					'progress'      => 100,
				];
			}

			return [
				'completed'     => false,
				'processed'     => $new_processed,
				'total'         => $total_count,
				'updated_items' => $updated_items,
				'skipped_items' => $skipped_items,
				'error_items'   => $error_items,
				'progress'      => $progress,
			];

		} catch ( \Exception $e ) {
			$this->logger->log(
				$job_id,
				'error',
				sprintf( 'Update error: %s', $e->getMessage() )
			);

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
	 * Update items with functions
	 *
	 * @param array  $items           Items to update
	 * @param array  $fields          Selected fields
	 * @param array  $field_functions Field functions mapping
	 * @param string $content_type    Content type
	 * @return array Update statistics
	 */
	private function update_items( $items, $fields, $field_functions, $content_type ) {
		$stats = [
			'updated' => 0,
			'skipped' => 0,
			'errors'  => 0,
		];

		foreach ( $items as $item ) {
			try {
				$updated       = false;
				$item_id       = $this->get_item_id( $item, $content_type );
				$original_item = $item; // Keep original for comparison

				// Apply functions to each field
				foreach ( $fields as $index => $field ) {
					// Check if this field has a function assigned
					if ( ! isset( $field_functions[ $index ] ) || empty( $field_functions[ $index ] ) || 'none' === $field_functions[ $index ] ) {
						continue;
					}

					$function_id = (int) $field_functions[ $index ];

					// Get current field value
					$current_value = isset( $item[ $field ] ) ? $item[ $field ] : '';

					// Execute function
					$new_value = $this->function_executor->execute( $function_id, $current_value, $item );

					if ( is_wp_error( $new_value ) ) {
						$this->logger->log(
							0,
							'warning',
							sprintf(
								'Function execution failed for item %s, field %s: %s',
								$item_id,
								$field,
								$new_value->get_error_message()
							)
						);
						continue;
					}

					// Update field value
					$item[ $field ] = $new_value;

					// Mark as updated if value changed
					if ( $current_value !== $new_value ) {
						$updated = true;
					}
				}

				// Save updated item if any changes were made
				if ( $updated ) {
					$save_result = $this->save_item( $item_id, $item, $content_type, $fields );

					if ( is_wp_error( $save_result ) ) {
						$this->logger->log(
							0,
							'error',
							sprintf(
								'Failed to save item %s: %s',
								$item_id,
								$save_result->get_error_message()
							)
						);
						++$stats['errors'];
					} else {
						++$stats['updated'];
					}
				} else {
					++$stats['skipped'];
				}
			} catch ( \Exception $e ) {
				$this->logger->log(
					0,
					'error',
					sprintf( 'Error updating item: %s', $e->getMessage() )
				);
				++$stats['errors'];
			}
		}

		return $stats;
	}

	/**
	 * Get item ID from item data
	 *
	 * @param array  $item         Item data
	 * @param string $content_type Content type
	 * @return mixed Item ID
	 */
	private function get_item_id( $item, $content_type ) {
		switch ( $content_type ) {
			case 'post':
			case 'page':
			case 'custom_post_types':
			case 'media':
			case 'menu':
			case 'woo_product':
			case 'woo_order':
			case 'woo_coupon':
				return isset( $item['ID'] ) ? $item['ID'] : ( isset( $item['id'] ) ? $item['id'] : 0 );

			case 'user':
				return isset( $item['ID'] ) ? $item['ID'] : ( isset( $item['user_id'] ) ? $item['user_id'] : 0 );

			case 'comment':
				return isset( $item['comment_ID'] ) ? $item['comment_ID'] : 0;

			case 'taxonomy':
				return isset( $item['term_id'] ) ? $item['term_id'] : 0;

			case 'database_table':
				// For database tables, use first column value as ID
				return reset( $item );

			default:
				return isset( $item['ID'] ) ? $item['ID'] : 0;
		}
	}

	/**
	 * Save updated item
	 *
	 * @param mixed  $item_id      Item ID
	 * @param array  $item         Updated item data
	 * @param string $content_type Content type
	 * @param array  $fields       Fields that were updated
	 * @return true|\WP_Error
	 */
	private function save_item( $item_id, $item, $content_type, $fields ) {
		try {
			switch ( $content_type ) {
				case 'post':
				case 'page':
				case 'custom_post_types':
				case 'media':
				case 'menu':
				case 'woo_product':
				case 'woo_order':
				case 'woo_coupon':
					return $this->save_post_item( $item_id, $item, $fields );

				case 'user':
					return $this->save_user_item( $item_id, $item, $fields );

				case 'comment':
					return $this->save_comment_item( $item_id, $item, $fields );

				case 'taxonomy':
					return $this->save_term_item( $item_id, $item, $fields );

				case 'database_table':
					return $this->save_database_item( $item_id, $item, $fields );

				default:
					return new \WP_Error( 'invalid_content_type', sprintf( 'Unknown content type: %s', $content_type ) );
			}
		} catch ( \Exception $e ) {
			return new \WP_Error( 'save_error', $e->getMessage() );
		}
	}

	/**
	 * Save post item
	 *
	 * @param int   $post_id Post ID
	 * @param array $item    Item data
	 * @param array $fields  Fields to update
	 * @return true|\WP_Error
	 */
	private function save_post_item( $post_id, $item, $fields ) {
		$post_data = [];
		$meta_data = [];

		// Separate post fields from meta fields
		$standard_fields = [ 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_name', 'post_author', 'post_date', 'post_modified' ];

		foreach ( $fields as $field ) {
			if ( ! isset( $item[ $field ] ) ) {
				continue;
			}

			if ( in_array( $field, $standard_fields, true ) ) {
				$post_data[ $field ] = $item[ $field ];
			} else {
				// Treat as meta field
				$meta_data[ $field ] = $item[ $field ];
			}
		}

		// Update post if there are standard fields to update
		if ( ! empty( $post_data ) ) {
			$post_data['ID'] = $post_id;
			$result          = wp_update_post( $post_data, true );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		// Update meta fields
		foreach ( $meta_data as $meta_key => $meta_value ) {
			update_post_meta( $post_id, $meta_key, $meta_value );
		}

		return true;
	}

	/**
	 * Save user item
	 *
	 * @param int   $user_id User ID
	 * @param array $item    Item data
	 * @param array $fields  Fields to update
	 * @return true|\WP_Error
	 */
	private function save_user_item( $user_id, $item, $fields ) {
		$user_data = [];
		$meta_data = [];

		// Separate user fields from meta fields
		$standard_fields = [ 'user_login', 'user_email', 'user_nicename', 'display_name', 'first_name', 'last_name', 'user_url', 'description', 'role' ];

		foreach ( $fields as $field ) {
			if ( ! isset( $item[ $field ] ) ) {
				continue;
			}

			if ( in_array( $field, $standard_fields, true ) ) {
				$user_data[ $field ] = $item[ $field ];
			} else {
				// Treat as meta field
				$meta_data[ $field ] = $item[ $field ];
			}
		}

		// Update user if there are standard fields to update
		if ( ! empty( $user_data ) ) {
			$user_data['ID'] = $user_id;
			$result          = wp_update_user( $user_data );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		// Update meta fields
		foreach ( $meta_data as $meta_key => $meta_value ) {
			update_user_meta( $user_id, $meta_key, $meta_value );
		}

		return true;
	}

	/**
	 * Save comment item
	 *
	 * @param int   $comment_id Comment ID
	 * @param array $item       Item data
	 * @param array $fields     Fields to update
	 * @return true|\WP_Error
	 */
	private function save_comment_item( $comment_id, $item, $fields ) {
		$comment_data = [];
		$meta_data    = [];

		// Separate comment fields from meta fields
		$standard_fields = [ 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_content', 'comment_approved' ];

		foreach ( $fields as $field ) {
			if ( ! isset( $item[ $field ] ) ) {
				continue;
			}

			if ( in_array( $field, $standard_fields, true ) ) {
				$comment_data[ $field ] = $item[ $field ];
			} else {
				// Treat as meta field
				$meta_data[ $field ] = $item[ $field ];
			}
		}

		// Update comment if there are standard fields to update
		if ( ! empty( $comment_data ) ) {
			$comment_data['comment_ID'] = $comment_id;
			$result                     = wp_update_comment( $comment_data, true );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		// Update meta fields
		foreach ( $meta_data as $meta_key => $meta_value ) {
			update_comment_meta( $comment_id, $meta_key, $meta_value );
		}

		return true;
	}

	/**
	 * Save term item
	 *
	 * @param int   $term_id Term ID
	 * @param array $item    Item data
	 * @param array $fields  Fields to update
	 * @return true|\WP_Error
	 */
	private function save_term_item( $term_id, $item, $fields ) {
		$term_data = [];
		$meta_data = [];

		// Separate term fields from meta fields
		$standard_fields = [ 'name', 'slug', 'description' ];

		$taxonomy = isset( $item['taxonomy'] ) ? $item['taxonomy'] : 'category';

		foreach ( $fields as $field ) {
			if ( ! isset( $item[ $field ] ) ) {
				continue;
			}

			if ( in_array( $field, $standard_fields, true ) ) {
				$term_data[ $field ] = $item[ $field ];
			} else {
				// Treat as meta field
				$meta_data[ $field ] = $item[ $field ];
			}
		}

		// Update term if there are standard fields to update
		if ( ! empty( $term_data ) ) {
			$result = wp_update_term( $term_id, $taxonomy, $term_data );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		// Update meta fields
		foreach ( $meta_data as $meta_key => $meta_value ) {
			update_term_meta( $term_id, $meta_key, $meta_value );
		}

		return true;
	}

	/**
	 * Save database table item
	 *
	 * @param mixed $item_id Item ID (primary key value)
	 * @param array $item    Item data
	 * @param array $fields  Fields to update
	 * @return true|\WP_Error
	 */
	private function save_database_item( $item_id, $item, $fields ) {
		global $wpdb;

		// Note: For database tables, we need the table name and primary key
		// These should be passed in the item data or stored in job parameters
		// For now, we'll skip direct database updates as they're more complex
		// and require knowing the table structure

		return new \WP_Error(
			'not_implemented',
			__( 'Direct database table updates are not yet supported', 'wp-advanced-import-export' )
		);
	}
}

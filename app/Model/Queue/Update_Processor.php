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
use WP_AIE\Helper\Function_Executor;

defined( 'ABSPATH' ) || exit;

class Update_Processor {

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
	 * Current job options (used by save helpers that need e.g. table_name)
	 *
	 * @var array
	 */
	protected $current_options = [];

	/**
	 * Constructor
	 */
	public function __construct() {
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
			$exporter_type   = $parameters['exporter_type'] ?? $content_type; // Use exporter_type if available
			$options         = $parameters['options'] ?? [];
			$fields          = $parameters['fields'] ?? [];
			$field_functions = $parameters['field_functions'] ?? [];

			// Store for use by save helpers (e.g. table_name for database_table)
			$this->current_options = $options;

			// Get batch size
			$batch_size = isset( $options['items_per_iteration'] ) ? (int) $options['items_per_iteration'] : 10;

			// Get current offset
			$current_offset = (int) ( $job->processed_items ?? 0 );

			// Get exporter to fetch items
			$exporter = Exporter_Factory::get_exporter( $exporter_type, $job_id );
			if ( is_wp_error( $exporter ) ) {
				throw new \Exception( $exporter->get_error_message() );
			}

			// Build fetch options
			// IMPORTANT: For Content Updater, we need ID field even if user didn't select it
			// Add a special flag to tell exporter to include ID field
			$fetch_options = array_merge(
				$options,
				[
					'fields'           => $fields,
					'limit'            => $batch_size,
					'offset'           => $current_offset,
					'force_include_id' => true,  // Force ID inclusion for updates
				]
			);

			// Get total count on first batch
			if ( 0 === $current_offset ) {
				$total_count = Exporter_Factory::get_count( $exporter_type, $fetch_options );

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
				$has_functions = false;
				$item_id       = $this->get_item_id( $item, $content_type );

				// Skip items without valid ID.
				// For database_table the PK can be any non-empty scalar (string UUIDs, etc.).
				$id_is_empty = ( 'database_table' === $content_type )
					? ( $item_id === null || $item_id === '' || $item_id === false )
					: ( empty( $item_id ) || $item_id <= 0 );

				if ( $id_is_empty ) {
					++$stats['skipped'];
					continue;
				}

				// Apply functions to each field
				foreach ( $fields as $index => $field ) {
					// Check if this field has a function assigned
					if ( ! isset( $field_functions[ $index ] ) || empty( $field_functions[ $index ] ) ) {
						continue;
					}

					$functions_for_field = $field_functions[ $index ];

					// Ensure it's an array
					if ( ! is_array( $functions_for_field ) ) {
						$functions_for_field = [ $functions_for_field ];
					}

					// Skip if empty or 'none'
					if ( empty( $functions_for_field ) || ( count( $functions_for_field ) === 1 && 'none' === $functions_for_field[0] ) ) {
						continue;
					}

					// Get current field value
					$current_value = isset( $item[ $field ] ) ? $item[ $field ] : '';
					$new_value     = $current_value;
					$field_updated = false;

					// Execute functions in pipeline (one after another)
					foreach ( $functions_for_field as $function_id ) {
						// Skip empty or invalid function IDs
						if ( empty( $function_id ) || 'none' === $function_id ) {
							continue;
						}

						// Support both integer IDs and string snippet IDs (e.g., "snippet_uppercase")
						if ( is_numeric( $function_id ) ) {
							$function_id = (int) $function_id;
							if ( $function_id <= 0 ) {
								continue;
							}
						}

						// Execute function
						$new_value = $this->function_executor->execute( $function_id, $new_value, $item );

						if ( is_wp_error( $new_value ) ) {
							// Stop pipeline on error, revert to original
							$new_value = $current_value;
							break;
						}

						// Mark that function was successfully executed
						$field_updated = true;
					}

					// Update field value
					$item[ $field ] = $new_value;

					// Mark that at least one field has functions
					if ( $field_updated ) {
						$has_functions = true;
					}
				}

				// Save item if any functions were executed successfully
				if ( $has_functions ) {
					$save_result = $this->save_item( $item_id, $item, $content_type, $fields );

					if ( is_wp_error( $save_result ) ) {
						// Skip items with validation errors
						$error_code = $save_result->get_error_code();
						if ( in_array( $error_code, [ 'empty_title', 'invalid_post_id', 'post_not_found' ], true ) ) {
							++$stats['skipped'];
						} else {
							++$stats['errors'];
						}
					} else {
						++$stats['updated'];
					}
				} else {
					// No functions were executed (all skipped or failed)
					++$stats['skipped'];
				}
			} catch ( \Exception $e ) {
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
				// The primary-key column is always injected as the first key by
				// Database_Table_Exporter::process_item when force_include_id = true.
				return ! empty( $item ) ? reset( $item ) : 0;

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
					return $this->save_post_item( $item_id, $item, $fields );

				case 'woo_coupon':
					return $this->save_coupon_item( $item_id, $item, $fields );

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
		// Validate post ID to prevent creating new posts
		if ( empty( $post_id ) || ! is_numeric( $post_id ) || $post_id <= 0 ) {
			return new \WP_Error( 'invalid_post_id', sprintf( 'Invalid post ID: %s', $post_id ) );
		}

		// Verify post exists
		if ( ! get_post( $post_id ) ) {
			return new \WP_Error( 'post_not_found', sprintf( 'Post #%d does not exist', $post_id ) );
		}

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
			// Validate post_title if it's being updated - skip if empty
			if ( isset( $post_data['post_title'] ) && trim( $post_data['post_title'] ) === '' ) {
				return new \WP_Error( 'empty_title', 'Post title is empty' );
			}

			// Use direct wpdb update to avoid WordPress validation of existing meta fields
			// This way we only update the fields we want without triggering validation
			global $wpdb;
			
			$update_data = [];
			foreach ( $post_data as $key => $value ) {
				$update_data[ $key ] = $value;
			}
			
			if ( ! empty( $update_data ) ) {
				$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct DB query required here.
					$wpdb->posts,
					$update_data,
					[ 'ID' => $post_id ],
					null,
					[ '%d' ]
				);
				
				if ( false === $result ) {
					return new \WP_Error( 'db_update_error', 'Failed to update post in database' );
				}
			}

			// Clear post cache to ensure fresh data
			clean_post_cache( $post_id );
		}

		// Update meta fields (strip acf_/meta_ prefixes added by the field library)
		foreach ( $meta_data as $meta_key => $meta_value ) {
			$resolved = $this->resolve_meta_key( $meta_key );
			$real_key = $resolved['key'];
			if ( $resolved['is_acf'] && function_exists( 'update_field' ) ) {
				update_field( $real_key, $meta_value, $post_id );
			} else {
				update_post_meta( $post_id, $real_key, $meta_value );
			}
		}

		return true;
	}

	/**
	 * Save WooCommerce coupon item using WC_Coupon API
	 *
	 * @param int   $coupon_id Coupon ID
	 * @param array $item      Item data
	 * @param array $fields    Fields to update
	 * @return true|\WP_Error
	 */
	private function save_coupon_item( $coupon_id, $item, $fields ) {
		if ( empty( $coupon_id ) || ! is_numeric( $coupon_id ) || $coupon_id <= 0 ) {
			return new \WP_Error( 'invalid_coupon_id', sprintf( 'Invalid coupon ID: %s', $coupon_id ) );
		}

		if ( ! class_exists( 'WC_Coupon' ) ) {
			// Fallback to generic post saving if WooCommerce is not active
			return $this->save_post_item( $coupon_id, $item, $fields );
		}

		$coupon = new \WC_Coupon( $coupon_id );
		if ( ! $coupon->get_id() ) {
			return new \WP_Error( 'coupon_not_found', sprintf( 'Coupon #%d does not exist', $coupon_id ) );
		}

		// Map field names to WC_Coupon setter methods
		$setter_map = [
			'post_title'                  => 'set_code',
			'post_excerpt'                => 'set_description',
			'post_status'                 => 'set_status',
			'coupon_amount'               => 'set_amount',
			'discount_type'               => 'set_discount_type',
			'date_expires'                => 'set_date_expires',
			'usage_count'                 => 'set_usage_count',
			'usage_limit'                 => 'set_usage_limit',
			'usage_limit_per_user'        => 'set_usage_limit_per_user',
			'limit_usage_to_x_items'      => 'set_limit_usage_to_x_items',
			'individual_use'              => 'set_individual_use',
			'product_ids'                 => 'set_product_ids',
			'excluded_product_ids'        => 'set_excluded_product_ids',
			'product_categories'          => 'set_product_categories',
			'excluded_product_categories' => 'set_excluded_product_categories',
			'free_shipping'               => 'set_free_shipping',
			'exclude_sale_items'          => 'set_exclude_sale_items',
			'minimum_amount'              => 'set_minimum_amount',
			'maximum_amount'              => 'set_maximum_amount',
			'allowed_emails'              => 'set_email_restrictions',
		];

		// Boolean fields — exported as '0'/'1', WC stores as 'yes'/'no'
		$boolean_fields = [ 'individual_use', 'free_shipping', 'exclude_sale_items' ];

		// Array fields — exported as JSON strings, must be decoded to PHP array
		$array_fields = [
			'product_ids',
			'excluded_product_ids',
			'product_categories',
			'excluded_product_categories',
			'allowed_emails',
		];

		// Collect post-level fields that WC_Coupon doesn't expose as setters
		$post_data = [];

		foreach ( $fields as $field ) {
			if ( ! array_key_exists( $field, $item ) ) {
				continue;
			}

			$value = $item[ $field ];

			// post_date / post_modified must be updated directly on the posts table
			if ( in_array( $field, [ 'post_date', 'post_modified' ], true ) ) {
				$post_data[ $field ] = $value;
				continue;
			}

			if ( ! isset( $setter_map[ $field ] ) ) {
				// Unknown / custom field — store as post meta
				update_post_meta( $coupon_id, $field, $value );
				continue;
			}

			$method = $setter_map[ $field ];

			// Convert boolean fields: '0'/'1' / true/false → 'yes'/'no'
			if ( in_array( $field, $boolean_fields, true ) ) {
				$value = ( 'yes' === $value || '1' === $value || true === $value ) ? 'yes' : 'no';
			}

			// Convert array fields: JSON string → PHP array
			if ( in_array( $field, $array_fields, true ) ) {
				if ( is_string( $value ) ) {
					$decoded = json_decode( $value, true );
					if ( is_array( $decoded ) ) {
						$value = $decoded;
					} else {
						// Fallback: treat as comma-separated
						$value = array_values( array_filter( array_map( 'trim', explode( ',', $value ) ) ) );
					}
				}
			}

			if ( method_exists( $coupon, $method ) ) {
				$coupon->$method( $value );
			}
		}

		// Persist via WooCommerce data layer (writes correct _meta keys, clears cache)
		$coupon->save();

		// Update post_date / post_modified directly if needed
		if ( ! empty( $post_data ) ) {
			global $wpdb;
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->posts,
				$post_data,
				[ 'ID' => $coupon_id ],
				null,
				[ '%d' ]
			);
			clean_post_cache( $coupon_id );
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
		// CRITICAL: Validate user ID to prevent creating new users
		if ( empty( $user_id ) || ! is_numeric( $user_id ) || $user_id <= 0 ) {
			return new \WP_Error( 'invalid_user_id', sprintf( 'Invalid user ID: %s', $user_id ) );
		}

		// Verify user exists
		if ( ! get_user_by( 'id', $user_id ) ) {
			return new \WP_Error( 'user_not_found', sprintf( 'User #%d does not exist', $user_id ) );
		}

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
			
			$result = wp_update_user( $user_data );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		// Update meta fields (strip acf_/meta_ prefixes added by the field library)
		foreach ( $meta_data as $meta_key => $meta_value ) {
			$resolved = $this->resolve_meta_key( $meta_key );
			update_user_meta( $user_id, $resolved['key'], $meta_value );
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
		// CRITICAL: Validate comment ID to prevent creating new comments
		if ( empty( $comment_id ) || ! is_numeric( $comment_id ) || $comment_id <= 0 ) {
			return new \WP_Error( 'invalid_comment_id', sprintf( 'Invalid comment ID: %s', $comment_id ) );
		}

		// Verify comment exists
		if ( ! get_comment( $comment_id ) ) {
			return new \WP_Error( 'comment_not_found', sprintf( 'Comment #%d does not exist', $comment_id ) );
		}

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
			
			$result = wp_update_comment( $comment_data, true );

			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}

		// Update meta fields (strip acf_/meta_ prefixes added by the field library)
		foreach ( $meta_data as $meta_key => $meta_value ) {
			$resolved = $this->resolve_meta_key( $meta_key );
			update_comment_meta( $comment_id, $resolved['key'], $meta_value );
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
		// CRITICAL: Validate term ID to prevent creating new terms
		if ( empty( $term_id ) || ! is_numeric( $term_id ) || $term_id <= 0 ) {
			return new \WP_Error( 'invalid_term_id', sprintf( 'Invalid term ID: %s', $term_id ) );
		}

		$taxonomy = isset( $item['taxonomy'] ) ? $item['taxonomy'] : 'category';

		// Verify term exists
		if ( ! get_term( $term_id, $taxonomy ) ) {
			return new \WP_Error( 'term_not_found', sprintf( 'Term #%d does not exist in taxonomy %s', $term_id, $taxonomy ) );
		}

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

		// Update meta fields (strip acf_/meta_ prefixes added by the field library)
		foreach ( $meta_data as $meta_key => $meta_value ) {
			$resolved = $this->resolve_meta_key( $meta_key );
			update_term_meta( $term_id, $resolved['key'], $meta_value );
		}

		return true;
	}

	/**
	 * Resolve the real meta key from a possibly-prefixed field name.
	 *
	 * Field names in the Content Updater carry prefixes that identify their
	 * origin (e.g. 'acf_dadada', 'meta_some_key'). These prefixes must be
	 * stripped before writing back to the database.
	 *
	 * @param string $field Prefixed field name.
	 * @return array { key: string, is_acf: bool }
	 */
	private function resolve_meta_key( $field ) {
		if ( strpos( $field, 'acf_' ) === 0 ) {
			return [ 'key' => substr( $field, 4 ), 'is_acf' => true ];
		}
		if ( strpos( $field, 'meta_' ) === 0 ) {
			return [ 'key' => substr( $field, 5 ), 'is_acf' => false ];
		}
		return [ 'key' => $field, 'is_acf' => false ];
	}

	/**
	 * Save database table item
	 *
	 * @param mixed $item_id Item ID (primary key value)
	 * @param array $item    Item data — first key is always the PK column
	 * @param array $fields  Fields selected by the user (to update)
	 * @return true|\WP_Error
	 */
	private function save_database_item( $item_id, $item, $fields ) {
		global $wpdb;

		$table_name = $this->current_options['table_name'] ?? '';
		if ( empty( $table_name ) ) {
			return new \WP_Error(
				'missing_table_name',
				__( 'Table name is required for database table updates', 'wp-advanced-import-export' )
			);
		}

		$table_name = sanitize_text_field( $table_name );

		// The first key in $item is always the primary-key column (injected by
		// Database_Table_Exporter::process_item when force_include_id is set).
		$pk_column = array_key_first( $item );
		if ( empty( $pk_column ) ) {
			return new \WP_Error(
				'no_pk_column',
				__( 'Could not determine primary key column for table update', 'wp-advanced-import-export' )
			);
		}

		// Build SET data from the user-selected fields (skip the PK column)
		$update_data   = [];
		$update_format = [];
		foreach ( $fields as $field ) {
			if ( $field === $pk_column ) {
				continue; // Never overwrite the primary key
			}
			if ( array_key_exists( $field, $item ) ) {
				$update_data[ $field ] = $item[ $field ];
				$update_format[]       = '%s';
			}
		}

		if ( empty( $update_data ) ) {
			return new \WP_Error(
				'no_fields_to_update',
				__( 'No updatable fields found in item', 'wp-advanced-import-export' )
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->update(
			$table_name,
			$update_data,
			[ $pk_column => $item_id ],
			$update_format,
			[ '%s' ]
		);

		if ( false === $result ) {
			return new \WP_Error(
				'db_update_error',
				sprintf(
					/* translators: 1: table name, 2: database error */
					__( 'Failed to update row in table %1$s: %2$s', 'wp-advanced-import-export' ),
					$table_name,
					$wpdb->last_error
				)
			);
		}

		return true;
	}
}

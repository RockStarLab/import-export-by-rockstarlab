<?php
/**
 * Update Processor
 *
 * Processes content update jobs in batches
 *
 * @package RockStarLab\ImportExport\Model\Queue
 */

namespace RockStarLab\ImportExport\Model\Queue;

use RockStarLab\ImportExport\Model\Job;
use RockStarLab\ImportExport\Model\Export\Exporter_Factory;
use RockStarLab\ImportExport\Helper\Function_Executor;
use RockStarLab\ImportExport\Helper\Seo_Fields;
use RockStarLab\ImportExport\Helper\Elementor_Fields;

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
		$this->job_model         = rsl_ie()->Model->job;
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

			$options = $this->normalize_fetch_options( $content_type, $options );

			// Store for use by save helpers (e.g. table_name for database_table)
			$this->current_options = $options;

			// Get batch size
			$batch_size = isset( $options['items_per_iteration'] ) ? (int) $options['items_per_iteration'] : 10;

			// Get current offset
			$current_offset = (int) ( $job->processed_items ?? 0 );

			$fetch_fields   = $fields;
			$identity_field = $this->get_identity_field( $content_type );
			if ( '' !== $identity_field && ! in_array( $identity_field, $fetch_fields, true ) ) {
				array_unshift( $fetch_fields, $identity_field );
			}

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
					'fields'           => $fetch_fields,
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
	 * Get the field needed to identify items fetched for updater jobs.
	 *
	 * @param string $content_type Content type.
	 * @return string Identity field name.
	 */
	private function get_identity_field( $content_type ) {
		switch ( $content_type ) {
			case 'comment':
				return 'comment_ID';
			case 'menu':
			case 'taxonomy':
				return 'term_id';
			case 'database_table':
				return '';
			default:
				return 'ID';
		}
	}

	/**
	 * Normalize exporter options needed by logical updater content types.
	 *
	 * @param string $content_type Content type.
	 * @param array  $options      Fetch options.
	 * @return array Normalized options.
	 */
	private function normalize_fetch_options( $content_type, $options ) {
		$post_type_map = [
			'post'        => 'post',
			'page'        => 'page',
			'media'       => 'attachment',
			'menu'        => 'nav_menu_item',
			'woo_product' => 'product',
			'woo_order'   => 'shop_order',
			'woo_coupon'  => 'shop_coupon',
		];

		if ( isset( $post_type_map[ $content_type ] ) && empty( $options['post_type'] ) ) {
			$options['post_type'] = $post_type_map[ $content_type ];
		}

		return $options;
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
			case 'woo_product':
			case 'woo_order':
			case 'woo_coupon':
				return isset( $item['ID'] ) ? $item['ID'] : ( isset( $item['id'] ) ? $item['id'] : 0 );

			case 'menu':
				// Menu exports represent nav_menu TERMS, not nav_menu_item posts.
				return isset( $item['term_id'] ) ? $item['term_id'] : ( isset( $item['ID'] ) ? $item['ID'] : ( isset( $item['id'] ) ? $item['id'] : 0 ) );

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
					return $this->save_post_item( $item_id, $item, $fields );

				case 'menu':
					// Menus are stored as nav_menu terms.
					if ( empty( $item['taxonomy'] ) ) {
						$item['taxonomy'] = 'nav_menu';
					}
					return $this->save_term_item( $item_id, $item, $fields );

				case 'woo_order':
					return $this->save_order_item( $item_id, $item, $fields );

				case 'woo_product':
					return $this->save_product_item( $item_id, $item, $fields );

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

		$post_data        = [];
		$meta_data        = [];
		$featured_updates = [];

		// Separate post fields from meta fields
		$standard_fields = [ 'post_title', 'post_content', 'post_excerpt', 'post_status', 'post_name', 'post_author', 'post_date', 'post_modified', 'comment_status', 'post_parent', 'menu_order' ];
		$featured_fields = [ 'featured_image_id', 'featured_image_url', 'featured_image_title', 'featured_image_caption' ];

		foreach ( $fields as $field ) {
			if ( ! isset( $item[ $field ] ) ) {
				continue;
			}

			$value = $item[ $field ];

			if ( in_array( $field, $featured_fields, true ) ) {
				$featured_updates[ $field ] = $value;
				continue;
			}

			if ( in_array( $field, $standard_fields, true ) ) {
				$post_data[ $field ] = $value;
			} else {
				// Treat as meta field
				$meta_data[ $field ] = $value;
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
		$post_type  = get_post_type( $post_id );
		$is_product = in_array( $post_type, [ 'product', 'product_variation' ], true );

		foreach ( $meta_data as $meta_key => $meta_value ) {
			if ( 'elementor_document' === $meta_key ) {
				Elementor_Fields::import_document( (int) $post_id, $meta_value );
				continue;
			}

			if ( is_string( $meta_key ) && Elementor_Fields::is_elementor_meta_key( $meta_key ) ) {
				Elementor_Fields::import_meta_value( (int) $post_id, $meta_key, $meta_value );
				continue;
			}

			if ( 'rank_math_schemas' === $meta_key ) {
				Seo_Fields::import_rank_math_schemas( (int) $post_id, $meta_value );
				continue;
			}

			$resolved = $this->resolve_meta_key( $meta_key );
			$real_key = $resolved['key'];
			if ( $is_product && $real_key === 'regular_price' ) {
				$price = function_exists( 'wc_format_decimal' ) ? wc_format_decimal( $meta_value ) : $meta_value;
				update_post_meta( $post_id, '_regular_price', $price );
				update_post_meta( $post_id, '_price', $price );
				continue;
			}
			if ( $resolved['is_acf'] && function_exists( 'update_field' ) ) {
				// update_field returns false when ACF can't resolve the field by name
				// (e.g. field group stored as PHP/JSON file, or value unchanged).
				// Always fall back to update_post_meta to guarantee the raw meta is saved.
				$prepared_acf = $this->prepare_acf_update_value( $real_key, $meta_value, (int) $post_id );
				if ( ! $prepared_acf['should_update'] ) {
					continue;
				}
				$meta_value = $prepared_acf['value'];
				update_field( $this->get_acf_update_selector( $real_key, (int) $post_id ), $meta_value, $post_id );
				update_post_meta( $post_id, $real_key, $meta_value );
			} else {
				update_post_meta( $post_id, $real_key, $meta_value );
			}
		}

		if ( ! empty( $featured_updates ) ) {
			$image_id = null;

			if ( isset( $featured_updates['featured_image_id'] ) ) {
				$image_id = (int) $featured_updates['featured_image_id'];
				if ( $image_id > 0 ) {
					set_post_thumbnail( $post_id, $image_id );
				} else {
					delete_post_thumbnail( $post_id );
				}
			} elseif ( isset( $featured_updates['featured_image_url'] ) ) {
				$url = trim( (string) $featured_updates['featured_image_url'] );
				if ( '' !== $url ) {
					$image_id = attachment_url_to_postid( $url );
					if ( $image_id ) {
						set_post_thumbnail( $post_id, $image_id );
					}
				}
			}

			if ( isset( $featured_updates['featured_image_title'] ) || isset( $featured_updates['featured_image_caption'] ) ) {
				if ( null === $image_id ) {
					$image_id = get_post_thumbnail_id( $post_id );
				}
				if ( $image_id ) {
					$attachment_update = [ 'ID' => $image_id ];
					if ( isset( $featured_updates['featured_image_title'] ) ) {
						$attachment_update['post_title'] = (string) $featured_updates['featured_image_title'];
					}
					if ( isset( $featured_updates['featured_image_caption'] ) ) {
						$attachment_update['post_excerpt'] = (string) $featured_updates['featured_image_caption'];
					}
					wp_update_post( $attachment_update );
				}
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

		// Fields that may contain portable refs (sku:/slug:/id:) rather than raw IDs.
		$portable_product_fields  = [ 'product_ids', 'excluded_product_ids' ];
		$portable_category_fields = [ 'product_categories', 'excluded_product_categories' ];

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

			// Resolve portable product/category refs to target IDs.
			if ( in_array( $field, $portable_product_fields, true ) ) {
				$value = $this->resolve_coupon_product_ids( $value );
			} elseif ( in_array( $field, $portable_category_fields, true ) ) {
				$value = $this->resolve_coupon_product_cat_ids( $value );
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
	 * Resolve coupon product restriction values to product IDs.
	 *
	 * Export may provide portable refs (sku:/slug:/id:) because IDs differ across sites.
	 *
	 * @param mixed $value Decoded value (array|string|int).
	 * @return int[] Product IDs.
	 */
	private function resolve_coupon_product_ids( $value ) {
		if ( empty( $value ) ) {
			return [];
		}

		$values = is_array( $value ) ? $value : [ $value ];
		$out    = [];
		$seen   = [];

		foreach ( $values as $ref ) {
			$id = $this->resolve_coupon_product_id_ref( $ref );
			if ( $id > 0 && ! isset( $seen[ $id ] ) ) {
				$seen[ $id ] = true;
				$out[]       = $id;
			}
		}

		return $out;
	}

	/**
	 * Resolve coupon product_cat restriction values to term IDs.
	 *
	 * @param mixed $value Decoded value (array|string|int).
	 * @return int[] Term IDs.
	 */
	private function resolve_coupon_product_cat_ids( $value ) {
		if ( empty( $value ) ) {
			return [];
		}

		$values = is_array( $value ) ? $value : [ $value ];
		$out    = [];
		$seen   = [];

		foreach ( $values as $ref ) {
			$id = $this->resolve_coupon_product_cat_id_ref( $ref );
			if ( $id > 0 && ! isset( $seen[ $id ] ) ) {
				$seen[ $id ] = true;
				$out[]       = $id;
			}
		}

		return $out;
	}

	/**
	 * Resolve a single portable product reference to a product/variation ID.
	 *
	 * @param mixed $ref Portable ref (sku:..., slug:..., id:...) or legacy int/string.
	 * @return int Product ID.
	 */
	private function resolve_coupon_product_id_ref( $ref ) {
		if ( is_int( $ref ) ) {
			return $ref > 0 ? $ref : 0;
		}

		$raw = trim( (string) $ref );
		if ( '' === $raw ) {
			return 0;
		}

		$prefix  = '';
		$payload = $raw;
		if ( false !== strpos( $raw, ':' ) ) {
			$parts = explode( ':', $raw, 2 );
			if ( 2 === count( $parts ) ) {
				$prefix  = strtolower( trim( $parts[0] ) );
				$payload = trim( $parts[1] );
			}
		}

		// sku:... is the preferred portable representation.
		if ( 'sku' === $prefix && '' !== $payload && function_exists( 'wc_get_product_id_by_sku' ) ) {
			$id = (int) wc_get_product_id_by_sku( $payload );
			return $id > 0 ? $id : 0;
		}

		// slug:... (best-effort fallback when SKU is missing).
		if ( 'slug' === $prefix && '' !== $payload ) {
			$ids = get_posts(
				[
					'name'           => $payload,
					'post_type'      => [ 'product', 'product_variation' ],
					'post_status'    => 'any',
					'posts_per_page' => 1,
					'fields'         => 'ids',
				]
			);
			if ( ! empty( $ids ) ) {
				return (int) $ids[0];
			}
			return 0;
		}

		// id:...
		if ( 'id' === $prefix && is_numeric( $payload ) ) {
			$id = (int) $payload;
			return $id > 0 ? $id : 0;
		}

		// Backwards compatibility: plain value (try SKU first, then ID, then slug).
		if ( function_exists( 'wc_get_product_id_by_sku' ) ) {
			$id = (int) wc_get_product_id_by_sku( $raw );
			if ( $id > 0 ) {
				return $id;
			}
		}

		if ( is_numeric( $raw ) && function_exists( 'wc_get_product' ) ) {
			$id      = (int) $raw;
			$product = $id > 0 ? wc_get_product( $id ) : null;
			if ( $product ) {
				return $id;
			}
		}

		$ids = get_posts(
			[
				'name'           => $raw,
				'post_type'      => [ 'product', 'product_variation' ],
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'fields'         => 'ids',
			]
		);
		if ( ! empty( $ids ) ) {
			return (int) $ids[0];
		}

		return 0;
	}

	/**
	 * Resolve a single portable product_cat reference to a term ID.
	 *
	 * @param mixed $ref Portable ref (slug:..., id:...) or legacy int/string.
	 * @return int Term ID.
	 */
	private function resolve_coupon_product_cat_id_ref( $ref ) {
		if ( is_int( $ref ) ) {
			return $ref > 0 ? $ref : 0;
		}

		$raw = trim( (string) $ref );
		if ( '' === $raw ) {
			return 0;
		}

		$prefix  = '';
		$payload = $raw;
		if ( false !== strpos( $raw, ':' ) ) {
			$parts = explode( ':', $raw, 2 );
			if ( 2 === count( $parts ) ) {
				$prefix  = strtolower( trim( $parts[0] ) );
				$payload = trim( $parts[1] );
			}
		}

		if ( 'slug' === $prefix && '' !== $payload ) {
			$term = get_term_by( 'slug', $payload, 'product_cat' );
			return ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;
		}

		if ( 'id' === $prefix && is_numeric( $payload ) ) {
			$term = get_term( (int) $payload, 'product_cat' );
			return ( $term && ! is_wp_error( $term ) ) ? (int) $term->term_id : 0;
		}

		// Backwards compatibility: raw ID or raw slug.
		if ( is_numeric( $raw ) ) {
			$term = get_term( (int) $raw, 'product_cat' );
			if ( $term && ! is_wp_error( $term ) ) {
				return (int) $term->term_id;
			}
		}

		$term = get_term_by( 'slug', $raw, 'product_cat' );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}

		$term = get_term_by( 'name', $raw, 'product_cat' );
		if ( $term && ! is_wp_error( $term ) ) {
			return (int) $term->term_id;
		}

		return 0;
	}

		/**
		 * Save WooCommerce order item using WC_Order API (HPOS compatible).
		 *
		 * @param int   $order_id Order ID.
		 * @param array $item     Item data.
		 * @param array $fields   Fields to update.
		 * @return true|\WP_Error
		 */
	private function save_order_item( $order_id, $item, $fields ) {
		if ( empty( $order_id ) || ! is_numeric( $order_id ) || $order_id <= 0 ) {
			return new \WP_Error( 'invalid_order_id', sprintf( 'Invalid order ID: %s', $order_id ) );
		}

		if ( ! function_exists( 'wc_get_order' ) ) {
			// Backwards compatibility fallback (non-HPOS installs still store orders as posts).
			return $this->save_post_item( $order_id, $item, $fields );
		}

		$order = wc_get_order( (int) $order_id );
		if ( ! $order ) {
			return new \WP_Error( 'order_not_found', sprintf( 'Order #%d does not exist', $order_id ) );
		}

		$setter_map = [
			'order_status'         => 'set_status',
			'customer_note'        => 'set_customer_note',
			'billing_first_name'   => 'set_billing_first_name',
			'billing_last_name'    => 'set_billing_last_name',
			'billing_company'      => 'set_billing_company',
			'billing_address_1'    => 'set_billing_address_1',
			'billing_address_2'    => 'set_billing_address_2',
			'billing_city'         => 'set_billing_city',
			'billing_state'        => 'set_billing_state',
			'billing_postcode'     => 'set_billing_postcode',
			'billing_country'      => 'set_billing_country',
			'billing_email'        => 'set_billing_email',
			'billing_phone'        => 'set_billing_phone',
			'shipping_first_name'  => 'set_shipping_first_name',
			'shipping_last_name'   => 'set_shipping_last_name',
			'shipping_company'     => 'set_shipping_company',
			'shipping_address_1'   => 'set_shipping_address_1',
			'shipping_address_2'   => 'set_shipping_address_2',
			'shipping_city'        => 'set_shipping_city',
			'shipping_state'       => 'set_shipping_state',
			'shipping_postcode'    => 'set_shipping_postcode',
			'shipping_country'     => 'set_shipping_country',
			'payment_method'       => 'set_payment_method',
			'payment_method_title' => 'set_payment_method_title',
			'transaction_id'       => 'set_transaction_id',
		];

		$touched = false;
		foreach ( $fields as $field ) {
			if ( ! isset( $item[ $field ] ) ) {
				continue;
			}

			$value = $item[ $field ];

			// Skip read-only/derived fields.
			if ( in_array(
				$field,
				[
					'ID',
					'order_number',
					'order_key',
					'order_total',
					'order_subtotal',
					'order_tax',
					'order_shipping',
					'order_discount',
					'cart_tax',
					'shipping_tax',
					'total_tax',
					'order_items',
					'item_count',
					'shipping_lines',
					'fee_lines',
					'coupon_lines',
					'order_notes',
					'order_meta',
					'currency',
					'order_date',
					'date_modified',
					'completed_date',
					'paid_date',
				],
				true
			) ) {
				continue;
			}

			if ( isset( $setter_map[ $field ] ) && method_exists( $order, $setter_map[ $field ] ) ) {
				$method = $setter_map[ $field ];

				if ( 'set_status' === $method ) {
					// Allow both "processing" and "wc-processing".
					$value = is_string( $value ) ? preg_replace( '/^wc-/', '', $value ) : $value;
				}

				$order->$method( $value );
				$touched = true;
				continue;
			}

			// Fallback: treat as order meta (supports acf_/meta_/yoast__ prefixes too).
			$resolved   = $this->resolve_meta_key( $field );
			$meta_value = is_array( $value ) ? wp_json_encode( $value ) : $value;
			$order->update_meta_data( $resolved['key'], $meta_value );
			$touched = true;
		}

		if ( $touched ) {
			$order->save();
		}

		return true;
	}

		/**
		 * Save WooCommerce product item using WC_Product API
		 *
		 * @param int   $product_id Product ID
		 * @param array $item       Item data
		 * @param array $fields     Fields to update
		 * @return true|\WP_Error
		 */
	private function save_product_item( $product_id, $item, $fields ) {
		if ( empty( $product_id ) || ! is_numeric( $product_id ) || $product_id <= 0 ) {
			return new \WP_Error( 'invalid_product_id', sprintf( 'Invalid product ID: %s', $product_id ) );
		}

		if ( ! class_exists( 'WC_Product' ) || ! function_exists( 'wc_get_product' ) ) {
			return $this->save_post_item( $product_id, $item, $fields );
		}

		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return new \WP_Error( 'product_not_found', sprintf( 'Product #%d does not exist', $product_id ) );
		}

		$setter_map = [
			'post_title'        => 'set_name',
			'post_content'      => 'set_description',
			'post_excerpt'      => 'set_short_description',
			'post_status'       => 'set_status',
			'post_name'         => 'set_slug',
			'sku'               => 'set_sku',
			'regular_price'     => 'set_regular_price',
			'sale_price'        => 'set_sale_price',
			'tax_status'        => 'set_tax_status',
			'tax_class'         => 'set_tax_class',
			'stock_quantity'    => 'set_stock_quantity',
			'stock_status'      => 'set_stock_status',
			'manage_stock'      => 'set_manage_stock',
			'backorders'        => 'set_backorders',
			'downloadable'      => 'set_downloadable',
			'virtual'           => 'set_virtual',
			'weight'            => 'set_weight',
			'length'            => 'set_length',
			'width'             => 'set_width',
			'height'            => 'set_height',
			'shipping_class'    => 'set_shipping_class_id',
			'featured'          => 'set_featured',
			'visibility'        => 'set_catalog_visibility',
			'product_gallery'   => 'set_gallery_image_ids',
			'featured_image_id' => 'set_image_id',
			'comment_status'    => 'set_reviews_allowed',
		];

		$boolean_fields = [ 'manage_stock', 'downloadable', 'virtual', 'featured', 'comment_status' ];
		$array_fields   = [ 'product_gallery' ];

		$post_data        = [];
		$meta_data        = [];
		$featured_updates = [];
		$featured_fields  = [ 'featured_image_id', 'featured_image_url', 'featured_image_title', 'featured_image_caption' ];

		foreach ( $fields as $field ) {
			if ( ! array_key_exists( $field, $item ) ) {
				continue;
			}

			$value = $item[ $field ];

			if ( in_array( $field, $featured_fields, true ) ) {
				$featured_updates[ $field ] = $value;
				if ( 'featured_image_id' !== $field ) {
					continue;
				}
			}

			// Read-only fields (computed by WooCommerce)
			if ( in_array( $field, [ 'average_rating', 'review_count', 'total_sales', 'product_type' ], true ) ) {
				continue;
			}

			if ( in_array( $field, [ 'post_date', 'post_modified' ], true ) ) {
				$post_data[ $field ] = $value;
				continue;
			}

			if ( isset( $setter_map[ $field ] ) && method_exists( $product, $setter_map[ $field ] ) ) {
				if ( in_array( $field, $boolean_fields, true ) ) {
					if ( 'comment_status' === $field ) {
						$value = ( 'open' === $value || 'yes' === $value || '1' === $value || true === $value );
					} else {
						$value = ( 'yes' === $value || '1' === $value || true === $value );
					}
				}

				if ( in_array( $field, $array_fields, true ) ) {
					if ( is_string( $value ) ) {
						$value = array_values( array_filter( array_map( 'trim', explode( ',', $value ) ) ) );
					}
					if ( is_array( $value ) ) {
						$value = array_map( 'intval', $value );
					}
				}

				if ( 'shipping_class' === $field ) {
					$term  = get_term_by( 'slug', $value, 'product_shipping_class' );
					$value = $term ? (int) $term->term_id : 0;
				}

				if ( in_array( $field, [ 'stock_quantity', 'featured_image_id' ], true ) ) {
					$value = ( '' === $value || null === $value ) ? null : (int) $value;
				}

				$product->{$setter_map[ $field ]}( $value );
				continue;
			}

			$meta_data[ $field ] = $value;
		}

		if ( isset( $featured_updates['featured_image_url'] ) && empty( $featured_updates['featured_image_id'] ) ) {
			$url = trim( (string) $featured_updates['featured_image_url'] );
			if ( '' !== $url ) {
				$resolved_id = attachment_url_to_postid( $url );
				if ( $resolved_id ) {
					$product->set_image_id( (int) $resolved_id );
					$featured_updates['featured_image_id'] = (int) $resolved_id;
				}
			}
		}

		$product->save();

		if ( ! empty( $post_data ) ) {
			global $wpdb;
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$wpdb->posts,
				$post_data,
				[ 'ID' => $product_id ],
				null,
				[ '%d' ]
			);
			clean_post_cache( $product_id );
		}

		foreach ( $meta_data as $meta_key => $meta_value ) {
			$resolved = $this->resolve_meta_key( $meta_key );
			$real_key = $resolved['key'];
			if ( $resolved['is_acf'] && function_exists( 'update_field' ) ) {
				$prepared_acf = $this->prepare_acf_update_value( $real_key, $meta_value, (int) $product_id );
				if ( ! $prepared_acf['should_update'] ) {
					continue;
				}
				$meta_value = $prepared_acf['value'];
				update_field( $this->get_acf_update_selector( $real_key, (int) $product_id ), $meta_value, $product_id );
				update_post_meta( $product_id, $real_key, $meta_value );
			} else {
				update_post_meta( $product_id, $real_key, $meta_value );
			}
		}

		if ( isset( $featured_updates['featured_image_title'] ) || isset( $featured_updates['featured_image_caption'] ) ) {
			$image_id = ! empty( $featured_updates['featured_image_id'] ) ? (int) $featured_updates['featured_image_id'] : 0;
			if ( $image_id <= 0 ) {
				$image_id = (int) get_post_thumbnail_id( $product_id );
			}
			if ( $image_id > 0 ) {
				$attachment_update = [ 'ID' => $image_id ];
				if ( isset( $featured_updates['featured_image_title'] ) ) {
					$attachment_update['post_title'] = (string) $featured_updates['featured_image_title'];
				}
				if ( isset( $featured_updates['featured_image_caption'] ) ) {
					$attachment_update['post_excerpt'] = (string) $featured_updates['featured_image_caption'];
				}
				wp_update_post( $attachment_update );
			}
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
		$standard_fields = [
			'comment_author',
			'comment_author_email',
			'comment_author_url',
			'comment_content',
			'comment_approved',
			'comment_type',
			'comment_post_ID',
			'comment_parent',
			'comment_karma',
			'comment_date',
			'comment_date_gmt',
		];

		foreach ( $fields as $field ) {
			if ( ! isset( $item[ $field ] ) ) {
				continue;
			}

			if ( $field === 'post_title' ) {
				// The comment "post_title" field is read-only (derived from the related post).
				continue;
			}

			if ( in_array( $field, $standard_fields, true ) ) {
				if ( in_array( $field, [ 'comment_post_ID', 'comment_parent', 'comment_karma' ], true ) ) {
					$comment_data[ $field ] = (int) $item[ $field ];
				} else {
					$comment_data[ $field ] = $item[ $field ];
				}
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
		$standard_fields = [ 'name', 'slug', 'description', 'parent' ];

		$taxonomy = isset( $item['taxonomy'] ) ? $item['taxonomy'] : 'category';

		foreach ( $fields as $field ) {
			if ( ! isset( $item[ $field ] ) ) {
				continue;
			}

			if ( $field === 'count' ) {
				// Term counts are calculated by WordPress and should not be updated directly.
				continue;
			}

			if ( in_array( $field, $standard_fields, true ) ) {
				if ( $field === 'parent' ) {
					$term_data[ $field ] = (int) $item[ $field ];
				} else {
					$term_data[ $field ] = $item[ $field ];
				}
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
			return [
				'key'    => substr( $field, 4 ),
				'is_acf' => true,
			];
		}
		if ( strpos( $field, 'meta_' ) === 0 ) {
			return [
				'key'    => substr( $field, 5 ),
				'is_acf' => false,
			];
		}
		if ( strpos( $field, 'yoast__' ) === 0 ) {
			$yoast_key = substr( $field, 7 );
			return [
				'key'    => '_' . ltrim( $yoast_key, '_' ),
				'is_acf' => false,
			];
		}
		if ( strpos( $field, 'yoast_' ) === 0 ) {
			$yoast_key = substr( $field, 6 );
			if ( preg_match( '/^_?yoast_|^wpseo_/', $yoast_key ) ) {
				return [
					'key'    => '_' . ltrim( $yoast_key, '_' ),
					'is_acf' => false,
				];
			}
		}
		return [
			'key'    => $field,
			'is_acf' => false,
		];
	}

	/**
	 * Prepare an ACF value before writing it through update_field().
	 *
	 * ACF image/file/gallery fields store attachment IDs, while the exporter emits
	 * portable URLs. Convert local attachment URLs back to IDs before saving.
	 *
	 * @param string $field_name ACF field name.
	 * @param mixed  $value      Exported/current field value.
	 * @param int    $post_id    Post ID.
	 * @return array {
	 *     Prepared ACF update result.
	 *
	 *     @type mixed $value         Prepared value.
	 *     @type bool  $should_update Whether the field can be updated safely.
	 * }
	 */
	private function prepare_acf_update_value( string $field_name, $value, int $post_id ): array {
		$field_object = $this->get_acf_field_object_for_update( $field_name, $post_id );
		$field_type   = is_array( $field_object ) && isset( $field_object['type'] ) ? (string) $field_object['type'] : '';
		$value        = $this->maybe_decode_json_value( $value );

		if ( in_array( $field_type, [ 'image', 'file' ], true ) ) {
			$attachment_id = $this->resolve_attachment_id_from_value( $value );
			if ( $attachment_id > 0 ) {
				return [
					'value'         => $attachment_id,
					'should_update' => true,
				];
			}

			if ( $this->value_contains_url( $value ) ) {
				return [
					'value'         => $value,
					'should_update' => false,
				];
			}
		}

		if ( 'gallery' === $field_type ) {
			$gallery_values = $this->extract_gallery_values( $value );
			if ( ! empty( $gallery_values ) ) {
				$attachment_ids = [];
				foreach ( $gallery_values as $gallery_value ) {
					$attachment_id = $this->resolve_attachment_id_from_value( $gallery_value );
					if ( $attachment_id > 0 ) {
						$attachment_ids[] = $attachment_id;
					} elseif ( $this->value_contains_url( $gallery_value ) ) {
						return [
							'value'         => $value,
							'should_update' => false,
						];
					}
				}

				return [
					'value'         => $attachment_ids,
					'should_update' => true,
				];
			}
		}

		return [
			'value'         => $value,
			'should_update' => true,
		];
	}

	/**
	 * Resolve the safest selector for update_field().
	 *
	 * @param string $field_name ACF field name.
	 * @param int    $post_id    Post ID.
	 * @return string Field key when available, otherwise field name.
	 */
	private function get_acf_update_selector( string $field_name, int $post_id ): string {
		$field_object = $this->get_acf_field_object_for_update( $field_name, $post_id );
		if ( is_array( $field_object ) && ! empty( $field_object['key'] ) && 0 === strpos( (string) $field_object['key'], 'field_' ) ) {
			return (string) $field_object['key'];
		}

		$field_ref = get_post_meta( $post_id, '_' . $field_name, true );
		if ( is_string( $field_ref ) && 0 === strpos( $field_ref, 'field_' ) ) {
			return $field_ref;
		}

		return $field_name;
	}

	/**
	 * Find an ACF field object without formatting the current field value.
	 *
	 * @param string $field_name ACF field name.
	 * @param int    $post_id    Post ID.
	 * @return array|null Field object or null.
	 */
	private function get_acf_field_object_for_update( string $field_name, int $post_id ): ?array {
		if ( function_exists( 'get_field_object' ) ) {
			$field_object = get_field_object( $field_name, $post_id, false, false );
			if ( is_array( $field_object ) ) {
				return $field_object;
			}
		}

		$field_ref = get_post_meta( $post_id, '_' . $field_name, true );
		if ( is_string( $field_ref ) && '' !== $field_ref && function_exists( 'acf_get_field' ) ) {
			$field_object = acf_get_field( $field_ref );
			if ( is_array( $field_object ) ) {
				return $field_object;
			}
		}

		return null;
	}

	/**
	 * Decode JSON payloads emitted by exporters while leaving plain strings intact.
	 *
	 * @param mixed $value Value to decode.
	 * @return mixed Decoded value or original value.
	 */
	private function maybe_decode_json_value( $value ) {
		if ( ! is_string( $value ) ) {
			return $value;
		}

		$trimmed = trim( $value );
		if ( '' === $trimmed || ! in_array( $trimmed[0], [ '{', '[' ], true ) ) {
			return $value;
		}

		$decoded = json_decode( $trimmed, true );
		return JSON_ERROR_NONE === json_last_error() ? $decoded : $value;
	}

	/**
	 * Resolve an attachment ID from a media value.
	 *
	 * @param mixed $value Media value: ID, URL, or array with ID/url.
	 * @return int Attachment ID or 0.
	 */
	private function resolve_attachment_id_from_value( $value ): int {
		$value = $this->maybe_decode_json_value( $value );

		if ( is_numeric( $value ) ) {
			return absint( $value );
		}

		if ( is_array( $value ) ) {
			foreach ( [ 'ID', 'id' ] as $id_key ) {
				if ( isset( $value[ $id_key ] ) && is_numeric( $value[ $id_key ] ) ) {
					return absint( $value[ $id_key ] );
				}
			}

			if ( isset( $value['url'] ) && is_string( $value['url'] ) ) {
				return $this->resolve_attachment_id_from_url( $value['url'] );
			}
		}

		if ( is_string( $value ) ) {
			return $this->resolve_attachment_id_from_url( $value );
		}

		return 0;
	}

	/**
	 * Resolve an attachment ID from a local attachment URL.
	 *
	 * @param string $url Attachment URL.
	 * @return int Attachment ID or 0.
	 */
	private function resolve_attachment_id_from_url( string $url ): int {
		$url = trim( $url );
		if ( '' === $url || ! filter_var( $url, FILTER_VALIDATE_URL ) || ! function_exists( 'attachment_url_to_postid' ) ) {
			return 0;
		}

		return (int) attachment_url_to_postid( $url );
	}

	/**
	 * Extract gallery items from exported ACF gallery values.
	 *
	 * @param mixed $value Gallery value.
	 * @return array Gallery item values.
	 */
	private function extract_gallery_values( $value ): array {
		$value = $this->maybe_decode_json_value( $value );

		if ( is_array( $value ) && isset( $value['acf_type'] ) && 'gallery' === $value['acf_type'] ) {
			return isset( $value['values'] ) && is_array( $value['values'] ) ? $value['values'] : [];
		}

		if ( is_array( $value ) ) {
			return $value;
		}

		if ( is_string( $value ) && '' !== trim( $value ) ) {
			return array_map( 'trim', explode( ',', $value ) );
		}

		return [];
	}

	/**
	 * Check whether a value contains a URL.
	 *
	 * @param mixed $value Value to inspect.
	 * @return bool True when a URL is present.
	 */
	private function value_contains_url( $value ): bool {
		$value = $this->maybe_decode_json_value( $value );

		if ( is_string( $value ) ) {
			return (bool) filter_var( trim( $value ), FILTER_VALIDATE_URL );
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( $this->value_contains_url( $item ) ) {
					return true;
				}
			}
		}

		return false;
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
				__( 'Table name is required for database table updates', 'import-export-by-rockstarlab' )
			);
		}

		$table_name = sanitize_text_field( $table_name );

		// The first key in $item is always the primary-key column (injected by
		// Database_Table_Exporter::process_item when force_include_id is set).
		$pk_column = array_key_first( $item );
		if ( empty( $pk_column ) ) {
			return new \WP_Error(
				'no_pk_column',
				__( 'Could not determine primary key column for table update', 'import-export-by-rockstarlab' )
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
				__( 'No updatable fields found in item', 'import-export-by-rockstarlab' )
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
					__( 'Failed to update row in table %1$s: %2$s', 'import-export-by-rockstarlab' ),
					$table_name,
					$wpdb->last_error
				)
			);
		}

		return true;
	}
}

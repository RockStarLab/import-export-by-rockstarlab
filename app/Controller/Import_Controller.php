<?php
/**
 * Import Controller
 *
 * Handles import operations via AJAX
 *
 * @package WP_AIE\Controller
 */

namespace WP_AIE\Controller;

use WP_AIE\Model\Job;
use WP_AIE\Model\Import\Importer_Factory;
use WP_AIE\Model\Format\Format_Factory;
use WP_AIE\Helper\Fs;

defined( 'ABSPATH' ) || exit;

class Import_Controller extends Base_Controller {

	/**
	 * Get AJAX actions
	 *
	 * @return array
	 */
	protected function get_ajax_actions() {
		return [
			'import_upload_file'    => [ 'callback' => 'upload_file' ],
			'import_validate_data'  => [ 'callback' => 'validate_data' ],
			'import_start'          => [ 'callback' => 'start_import' ],
			'import_process_batch'  => [ 'callback' => 'process_batch' ],
			'import_get_progress'   => [ 'callback' => 'get_progress' ],
			'import_cancel'         => [ 'callback' => 'cancel_import' ],
			'get_acf_fields'        => [ 'callback' => 'get_acf_fields' ],
			'get_yoast_fields'      => [ 'callback' => 'get_yoast_fields' ],
			'get_database_tables'   => [ 'callback' => 'get_database_tables' ],
			'get_table_columns'     => [ 'callback' => 'get_table_columns' ],
		];
	}

	/**
	 * Upload and parse file
	 */
	public function upload_file() {
		$verification = $this->verify_request( 'import_upload' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( empty( $_FILES['file'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce verified via verify_request().
			$this->send_error( __( 'No file uploaded', 'wp-advanced-import-export' ), null, 400 );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$file = $this->sanitize_file_upload( wp_unslash( $_FILES['file'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Nonce verified via verify_request()
		if ( is_wp_error( $file ) ) {
			$this->send_error( $file, null, 400 );
		}

		$format = $this->get_request_param( 'format', 'csv' );

		// Validate format
		if ( ! Format_Factory::is_supported( $format ) ) {
			$this->send_error( __( 'Unsupported file format', 'wp-advanced-import-export' ), null, 400 );
		}

		// JSON is not supported for import
		if ( 'json' === $format ) {
			$this->send_error( __( 'JSON format is not supported for import. Please use CSV.', 'wp-advanced-import-export' ), null, 400 );
		}

		// Move file to upload directory
		$upload_result = Fs::upload_file( $file );
		if ( is_wp_error( $upload_result ) ) {
			$this->send_error( $upload_result, null, 500 );
		}

		$file_path = $upload_result['path'];

		// Parse file
		$parser = Format_Factory::create( $format );
		if ( is_wp_error( $parser ) ) {
			$this->send_error( $parser, null, 500 );
		}

		// Get preview data (first 5 rows)
		$preview = $parser->parse_chunk( $file_path, 0, 5 );
		if ( is_wp_error( $preview ) ) {
			$this->send_error( $preview, null, 500 );
		}

		// Get headers
		$headers = $parser->get_headers( $file_path );
		if ( is_wp_error( $headers ) ) {
			$this->send_error( $headers, null, 500 );
		}

		// Count total rows
		$total_rows = $parser->count_rows( $file_path );

		$this->send_success(
			[
				'file_id'    => $upload_result['id'],
				'file_path'  => $upload_result['path'],
				'file_name'  => $upload_result['name'],
				'format'     => $format,
				'headers'    => $headers,
				'preview'    => $preview,
				'total_rows' => $total_rows,
			],
			__( 'File uploaded successfully', 'wp-advanced-import-export' )
		);
	}

	/**
	 * Validate import data
	 */
	public function validate_data() {
		$verification = $this->verify_request( 'import_validate' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'file_path', 'import_type', 'mapping' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$file_path   = $this->get_request_param( 'file_path' );
		$import_type = $this->get_request_param( 'import_type' );
		$mapping     = $this->get_request_array( 'mapping' );
		$format      = $this->get_request_param( 'format', 'csv' );
		$delimiter   = $this->get_request_param( 'delimiter', ',' );

		// Parse file
		$parser = Format_Factory::create( $format );
		if ( is_wp_error( $parser ) ) {
			$this->send_error( $parser, null, 500 );
		}

		$data = $parser->parse( $file_path, [ 'delimiter' => $delimiter ] );
		if ( is_wp_error( $data ) ) {
			$this->send_error( $data, null, 500 );
		}

		// Get importer
		$importer = Importer_Factory::get_importer( $import_type );
		if ( is_wp_error( $importer ) ) {
			$this->send_error( $importer, null, 400 );
		}

		// Prepare data with mapping
		$prepared_data = $importer->prepare( $data, $mapping );

		// Validate
		$validation_result = $importer->validate( $prepared_data );
		if ( is_wp_error( $validation_result ) ) {
			$this->send_error( $validation_result, null, 400 );
		}

		$this->send_success(
			[
				'valid'       => true,
				'total_items' => count( $prepared_data ),
			],
			__( 'Data validation passed', 'wp-advanced-import-export' )
		);
	}

	/**
	 * Start import
	 */
	public function start_import() {
		$verification = $this->verify_request( 'import_start' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'file_path', 'import_type', 'mapping' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$file_path   = $this->get_request_param( 'file_path' );
		$import_type = $this->get_request_param( 'import_type' );
		$mapping     = $this->get_request_array( 'mapping' );
		$options     = $this->normalize_post_options( $import_type, $this->get_request_array( 'options' ) );
		$format      = $this->get_request_param( 'format', 'csv' );
		$delimiter   = $this->get_request_param( 'delimiter', ',' );

		// Create job
		$job_model = WP_AIE()->Model->job;
		$job_data  = [
			'type'       => 'import',
			'status'     => 'pending',
			'user_id'    => $this->get_current_user_id(),
			'file_path'  => $file_path,
			'parameters' => wp_json_encode(
				[
					'import_type' => $import_type,
					'format'      => $format,
					'delimiter'   => $delimiter,
					'mapping'     => $mapping,
					'options'     => $options,
					'offset'      => 0,
				]
			),
		];

		$job_id = $job_model->create( $job_data );
		if ( is_wp_error( $job_id ) ) {
			$this->send_error( $job_id, null, 500 );
		}

		// Return job info immediately so UI can start batch processing
		$this->send_success(
			[
				'job_id' => $job_id,
			],
			__( 'Import started successfully', 'wp-advanced-import-export' )
		);
	}

	/**
	 * Get import progress
	 */
	public function get_progress() {
		$verification = $this->verify_request( 'import_progress' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = WP_AIE()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data ) {
			$this->send_error( __( 'Job not found', 'wp-advanced-import-export' ), null, 404 );
		}

		// Parse result
		$result = $job_data->result ? json_decode( $job_data->result, true ) : [];
		
		// Calculate processed and total
		$processed = 0;
		$total     = 0;
		
		if ( ! empty( $result ) ) {
			$processed = ( $result['success'] ?? 0 ) + ( $result['failed'] ?? 0 ) + ( $result['skipped'] ?? 0 );
			$total     = $result['total'] ?? $processed;
		}
		
		// Get estimates
		$estimates = \WP_AIE\Helper\Progress_Tracker::estimate_time_remaining( $job_id );

		$this->send_success(
			[
				'status'     => $job_data->status,
				'progress'   => (int) $job_data->progress,
				'percentage' => (int) $job_data->progress,
				'processed'  => $processed,
				'total'      => $total,
				'result'     => $result,
				'estimates'  => $estimates,
			]
		);
	}

	/**
	 * Cancel import
	 */
	public function cancel_import() {
		$verification = $this->verify_request( 'import_cancel' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model  = WP_AIE()->Model->job;
		$job_result = $job_model->update( $job_id, [ 'status' => 'cancelled' ] );

		if ( is_wp_error( $job_result ) ) {
			$this->send_error( $job_result, null, 500 );
		}

		$this->send_success( null, __( 'Import cancelled', 'wp-advanced-import-export' ) );
	}

	/**
	 * Process import batch
	 */
	public function process_batch() {
		$verification = $this->verify_request( 'import_process_batch' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = WP_AIE()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data ) {
			$this->send_error( __( 'Job not found', 'wp-advanced-import-export' ), null, 404 );
		}

		// Check if job is paused or cancelled
		if ( in_array( $job_data->status, [ 'paused', 'cancelled' ], true ) ) {
			$this->send_success(
				[
					'completed' => true,
					'status'    => $job_data->status,
				]
			);
			return;
		}

		$parameters  = json_decode( $job_data->parameters, true );
		$import_type = $parameters['import_type'];
		$format      = $parameters['format'];
		$delimiter   = $parameters['delimiter'] ?? ',';
		$mapping     = $parameters['mapping'];
		$options     = $this->normalize_post_options( $import_type, $parameters['options'] ?? [] );
		$offset      = $parameters['offset'] ?? 0;
		$batch_size  = isset( $options['batch_size'] ) ? (int) $options['batch_size'] : 50;

		// Verify premium license for premium content types.
		$license_check = $this->verify_premium_for_type( $import_type );
		if ( is_wp_error( $license_check ) ) {
			$this->send_error( $license_check, null, 403 );
		}

		// On first batch, parse file and prepare data
		if ( ! isset( $parameters['prepared_data'] ) ) {
			// Set started_at
			$job_model->update(
				$job_id,
				[
					'status'     => 'processing',
					'started_at' => current_time( 'mysql' ),
				]
			);

			// Parse file
			$parser = Format_Factory::create( $format );
			$data   = $parser->parse( $job_data->file_path, [ 'delimiter' => $delimiter ] );

			if ( is_wp_error( $data ) ) {
				$job_model->update(
					$job_id,
					[
						'status' => 'failed',
						'result' => wp_json_encode( [ 'error' => $data->get_error_message() ] ),
					]
				);
				$this->send_error( $data, null, 500 );
				return;
			}

			// Get importer
			$importer = Importer_Factory::get_importer( $import_type, $job_id );

			if ( is_wp_error( $importer ) ) {
				$job_model->update(
					$job_id,
					[
						'status' => 'failed',
						'result' => wp_json_encode( [ 'error' => $importer->get_error_message() ] ),
					]
				);
				$this->send_error( $importer, null, 500 );
				return;
			}

			// Prepare data
			$prepared_data = $importer->prepare( $data, $mapping );
			$total_items   = count( $prepared_data );

			// Preserve source IDs for cross-site relationship fixups (e.g. post_parent).
			if ( $importer instanceof \WP_AIE\Model\Import\Post_Importer ) {
				foreach ( $prepared_data as $row_index => &$prepared_row ) {
					if ( isset( $data[ $row_index ]['ID'] ) ) {
						$prepared_row['_aie_source_id'] = absint( $data[ $row_index ]['ID'] );
					}
					if ( isset( $data[ $row_index ]['post_parent'] ) ) {
						$prepared_row['_aie_source_parent_id'] = absint( $data[ $row_index ]['post_parent'] );
					}
				}
				unset( $prepared_row );
			}

			// Preserve source IDs + portable post hints for cross-site comment relationships.
			if ( $importer instanceof \WP_AIE\Model\Import\Comment_Importer ) {
				foreach ( $prepared_data as $row_index => &$prepared_row ) {
					// Ensure core date fields are available even if the UI mapping omits them.
					if ( isset( $data[ $row_index ]['comment_date'] ) && ( ! isset( $prepared_row['comment_date'] ) || '' === $prepared_row['comment_date'] ) ) {
						$prepared_row['comment_date'] = (string) $data[ $row_index ]['comment_date'];
					}
					if ( isset( $data[ $row_index ]['comment_date_gmt'] ) && ( ! isset( $prepared_row['comment_date_gmt'] ) || '' === $prepared_row['comment_date_gmt'] ) ) {
						$prepared_row['comment_date_gmt'] = (string) $data[ $row_index ]['comment_date_gmt'];
					}
					if ( isset( $data[ $row_index ]['comment_ID'] ) ) {
						$prepared_row['_aie_source_comment_id'] = absint( $data[ $row_index ]['comment_ID'] );
					}
					if ( isset( $data[ $row_index ]['comment_parent'] ) ) {
						$prepared_row['_aie_source_comment_parent_id'] = absint( $data[ $row_index ]['comment_parent'] );
					}
					if ( isset( $data[ $row_index ]['post_permalink'] ) ) {
						$prepared_row['_aie_source_post_permalink'] = (string) $data[ $row_index ]['post_permalink'];
					}
					if ( isset( $data[ $row_index ]['post_slug'] ) ) {
						$prepared_row['_aie_source_post_slug'] = (string) $data[ $row_index ]['post_slug'];
					}
					if ( isset( $data[ $row_index ]['post_type'] ) ) {
						$prepared_row['_aie_source_post_type'] = (string) $data[ $row_index ]['post_type'];
					}
				}
				unset( $prepared_row );
			}

			// Preserve source IDs + portable parent hints for cross-site term hierarchy fixups.
			if ( $importer instanceof \WP_AIE\Model\Import\Taxonomy_Term_Importer ) {
				foreach ( $prepared_data as $row_index => &$prepared_row ) {
					if ( isset( $data[ $row_index ]['term_id'] ) ) {
						$prepared_row['_aie_source_term_id'] = absint( $data[ $row_index ]['term_id'] );
					}
					if ( isset( $data[ $row_index ]['parent'] ) ) {
						$prepared_row['_aie_source_parent_term_id'] = absint( $data[ $row_index ]['parent'] );
					}
					if ( isset( $data[ $row_index ]['parent_slug'] ) ) {
						$prepared_row['_aie_source_parent_slug'] = (string) $data[ $row_index ]['parent_slug'];
					}
				}
				unset( $prepared_row );
			}

			// Store prepared data and total in job parameters
			$parameters['prepared_data'] = $prepared_data;
			$parameters['total_items']   = $total_items;
			$parameters['offset']        = 0;

			// Initialize cumulative result
			$parameters['cumulative_result'] = [
				'total'   => $total_items,
				'success' => 0,
				'skipped' => 0,
				'failed'  => 0,
				'updated' => 0,
				'created' => 0,
				'errors'  => [],
			];

			$job_model->update(
				$job_id,
				[
					'parameters' => wp_json_encode( $parameters ),
				]
			);

			// Initialize progress
			\WP_AIE\Helper\Progress_Tracker::update_progress( $job_id, $total_items, 0, 0, 0 );
		}

		$prepared_data     = $parameters['prepared_data'];
		$total_items       = $parameters['total_items'];
		$cumulative_result = $parameters['cumulative_result'];

		// Get batch
		$batch = array_slice( $prepared_data, $offset, $batch_size );

		if ( empty( $batch ) ) {
			// Post-import fixups for relationship fields (best-effort).
			$this->fix_post_parent_relationships( $job_id, $prepared_data );
			$this->fix_comment_parent_relationships( $job_id, $prepared_data );
			$this->fix_term_parent_relationships( $job_id, $prepared_data );

			// All items processed - complete job
			$job_model->update(
				$job_id,
				[
					'status'       => 'completed',
					'progress'     => 100,
					'result'       => wp_json_encode( $cumulative_result ),
					'completed_at' => current_time( 'mysql' ),
				]
			);

			$this->send_success(
				[
					'completed' => true,
					'result'    => $cumulative_result,
				]
			);
			return;
		}

		// Process batch
		$importer = Importer_Factory::get_importer( $import_type, $job_id );
		if ( is_wp_error( $importer ) ) {
			$this->send_error( $importer, null, 500 );
			return;
		}

		// Set importer options (CRITICAL for Database_Table_Importer)
		$importer->set_options( $options );

		// Process each item in batch
		foreach ( $batch as $index => $item ) {
			$result = $importer->import_item( $item, $offset + $index );

			if ( is_wp_error( $result ) ) {
				++$cumulative_result['failed'];
				$cumulative_result['errors'][] = [
					'row'     => $offset + $index + 1,
					'message' => $result->get_error_message(),
				];
			} elseif ( 'skipped' === $result ) {
				++$cumulative_result['skipped'];
			} elseif ( 'updated' === $result ) {
				++$cumulative_result['updated'];
				++$cumulative_result['success'];
			} else {
				++$cumulative_result['created'];
				++$cumulative_result['success'];
			}
		}

		// Update offset
		$new_offset = $offset + count( $batch );
		$processed  = $cumulative_result['success'] + $cumulative_result['failed'] + $cumulative_result['skipped'];
		$progress   = round( ( $new_offset / $total_items ) * 100 );

		// Update parameters
		$parameters['offset']            = $new_offset;
		$parameters['cumulative_result'] = $cumulative_result;

		// Update job
		$job_model->update(
			$job_id,
			[
				'parameters' => wp_json_encode( $parameters ),
				'progress'   => $progress,
				'result'     => wp_json_encode( $cumulative_result ),
			]
		);

		// Update progress tracker
		\WP_AIE\Helper\Progress_Tracker::update_progress(
			$job_id,
			$total_items,
			$processed,
			$cumulative_result['success'],
			$cumulative_result['failed']
		);

		// Return response
		$this->send_success(
			[
				'completed' => false,
				'offset'    => $new_offset,
				'progress'  => $progress,
				'result'    => $cumulative_result,
			]
		);
	}

	/**
	 * Get ACF fields for import
	 */
	public function get_acf_fields() {
		$verification = $this->verify_request( 'import_fields' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		// Check if ACF is active
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			$this->send_success( [ 'fields' => [] ] );
			return;
		}

		$post_type = $this->get_request_param( 'post_type', 'post' );

		// Map WooCommerce content types to actual post types
		$woo_type_map = [
			'woo_product' => 'product',
			'woo_order'   => 'shop_order',
			'woo_coupon'  => 'shop_coupon',
		];

		if ( isset( $woo_type_map[ $post_type ] ) ) {
			$post_type = $woo_type_map[ $post_type ];
		}

		// Determine the location rule based on content type
		$location_args = [];
		if ( $post_type === 'user' ) {
			$location_args['user_form'] = 'all'; // ACF User fields
		} elseif ( $post_type === 'media' || $post_type === 'attachment' ) {
			$location_args['attachment'] = 'all'; // ACF Media fields
		} else {
			$location_args['post_type'] = $post_type;
		}

		// Get field groups for this location
		$field_groups = acf_get_field_groups( $location_args );

		$fields = [];
		foreach ( $field_groups as $group ) {
			$group_fields = acf_get_fields( $group['key'] );

			if ( $group_fields ) {
				foreach ( $group_fields as $field ) {
					// Skip UI-only fields that don't store data
					if ( in_array( $field['type'], [ 'accordion', 'tab', 'message', 'clone' ], true ) ) {
						continue;
					}
					
					$fields[] = [
						'name'  => $field['name'],
						'label' => $field['label'],
						'type'  => $field['type'],
					];
				}
			}
		}

		$this->send_success( [ 'fields' => $fields ] );
	}

	/**
	 * Get Yoast SEO fields for import
	 */
	public function get_yoast_fields() {
		$verification = $this->verify_request( 'import_fields' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		// Check if Yoast SEO is active
		if ( ! defined( 'WPSEO_VERSION' ) ) {
			$this->send_success( [ 'fields' => [] ] );
			return;
		}

		// Standard Yoast SEO meta fields
		$fields = [
			[
				'name'  => '_yoast_wpseo_title',
				'label' => 'SEO Title',
			],
			[
				'name'  => '_yoast_wpseo_metadesc',
				'label' => 'Meta Description',
			],
			[
				'name'  => '_yoast_wpseo_focuskw',
				'label' => 'Focus Keyword',
			],
			[
				'name'  => '_yoast_wpseo_canonical',
				'label' => 'Canonical URL',
			],
			[
				'name'  => '_yoast_wpseo_meta-robots-noindex',
				'label' => 'Meta Robots (Index)',
			],		[
			'name'  => '_yoast_wpseo_meta-robots-nofollow',
			'label' => 'Meta Robots (Follow)',
		],
		[
			'name'  => '_yoast_wpseo_opengraph-title',
			'label' => 'Social Title',
		],
		[
			'name'  => '_yoast_wpseo_opengraph-description',
			'label' => 'Social Description',
		],
		[
			'name'  => '_yoast_wpseo_opengraph-image',
			'label' => 'Social Image',
		],
		[
			'name'  => '_yoast_wpseo_twitter-title',
			'label' => 'X (Twitter) Title',
		],
		[
			'name'  => '_yoast_wpseo_twitter-description',
			'label' => 'X (Twitter) Description',
		],
		[
			'name'  => '_yoast_wpseo_twitter-image',
			'label' => 'X (Twitter) Image',
		],
	];

		$this->send_success( [ 'fields' => $fields ] );
	}

	/**
	 * Get database tables
	 */
	public function get_database_tables() {
		$verification = $this->verify_request( 'import_upload' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		// Use Database_Table_Exporter to get tables with row counts
		$exporter = new \WP_AIE\Model\Export\Database_Table_Exporter();
		$tables   = $exporter->get_available_tables();

		$this->send_success( [ 'tables' => $tables ] );
	}

	/**
	 * Get table columns with types
	 */
	public function get_table_columns() {
		$verification = $this->verify_request( 'import_upload' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'table_name' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$table_name = $this->get_request_param( 'table_name' );

		// Use Database_Table_Exporter to get columns
		$exporter = new \WP_AIE\Model\Export\Database_Table_Exporter();
		$columns  = $exporter->get_table_columns( $table_name );
		
		// Get row count
		global $wpdb;
		$row_count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table_name}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter -- Direct DB query required here.

		if ( empty( $columns ) ) {
			$this->send_error( __( 'Could not retrieve table columns', 'wp-advanced-import-export' ), null, 400 );
		}

		$this->send_success(
			[
				'columns'   => $columns,
				'row_count' => (int) $row_count,
			]
		);
	}

	/**
	 * Normalize import options for post-based import types.
	 *
	 * Some UI flows (e.g. importing Pages) do not always send a `post_type`
	 * option. When importing posts/pages via Post_Importer we should default
	 * to the selected content type to prevent importing Pages as Posts.
	 *
	 * @param string $import_type Importer type from the request/job.
	 * @param array  $options     Options array.
	 * @return array Normalized options.
	 */
	private function normalize_post_options( $import_type, $options ) {
		if ( ! is_array( $options ) ) {
			$options = [];
		}

		$import_type = strtolower( trim( (string) $import_type ) );

		$map = [
			'post'  => 'post',
			'posts' => 'post',
			'page'  => 'page',
			'pages' => 'page',
		];

		if ( empty( $options['post_type'] ) && isset( $map[ $import_type ] ) ) {
			$options['post_type'] = $map[ $import_type ];
		}

		return $options;
	}

	/**
	 * Best-effort fix for cross-site post_parent IDs after a Post_Importer job.
	 *
	 * When exporting posts/pages, `post_parent` is an ID from the source site.
	 * During import into another site those numeric IDs usually point to the wrong
	 * objects. Post_Importer records a source->target ID map (per job) and we
	 * use it here to rewrite `post_parent` to the correct target IDs once all
	 * items have been created/updated.
	 *
	 * @param int   $job_id        Job ID.
	 * @param array $prepared_data Prepared items (includes `_aie_source_*` keys when available).
	 * @return void
	 */
	private function fix_post_parent_relationships( $job_id, $prepared_data ) {
		$job_id = absint( $job_id );
		if ( $job_id <= 0 || ! is_array( $prepared_data ) || empty( $prepared_data ) ) {
			return;
		}

		$key = 'aie_import_post_id_map_' . $job_id;
		$map = get_transient( $key );
		if ( ! is_array( $map ) || empty( $map ) ) {
			return;
		}

		foreach ( $prepared_data as $row ) {
			$source_id       = isset( $row['_aie_source_id'] ) ? absint( $row['_aie_source_id'] ) : 0;
			$source_parent   = isset( $row['_aie_source_parent_id'] ) ? absint( $row['_aie_source_parent_id'] ) : 0;
			$target_id       = $source_id ? absint( $map[ (string) $source_id ] ?? 0 ) : 0;
			$target_parent   = $source_parent ? absint( $map[ (string) $source_parent ] ?? 0 ) : 0;

			if ( $target_id <= 0 || $source_parent <= 0 || $target_parent <= 0 ) {
				continue;
			}

			$post = get_post( $target_id );
			if ( ! $post ) {
				continue;
			}

			if ( (int) $post->post_parent === $target_parent ) {
				continue;
			}

			wp_update_post(
				[
					'ID'          => $target_id,
					'post_parent' => $target_parent,
				]
			);
		}

		// Prevent stale maps affecting future jobs.
		delete_transient( $key );
	}

	/**
	 * Best-effort fix for cross-site comment_parent relationships after a Comment_Importer job.
	 *
	 * The CSV stores source-site comment IDs in `comment_parent`. We import comments with
	 * parent=0 and store a source->target comment ID map in a transient keyed by job_id.
	 * Once all comments are created, we rewrite comment_parent to the correct target IDs.
	 *
	 * @param int   $job_id        Job ID.
	 * @param array $prepared_data Prepared items.
	 * @return void
	 */
	private function fix_comment_parent_relationships( $job_id, $prepared_data ) {
		$job_id = absint( $job_id );
		if ( $job_id <= 0 || ! is_array( $prepared_data ) || empty( $prepared_data ) ) {
			return;
		}

		$key = 'aie_import_comment_id_map_' . $job_id;
		$map = get_transient( $key );
		if ( ! is_array( $map ) || empty( $map ) ) {
			return;
		}

		foreach ( $prepared_data as $row ) {
			$source_id     = isset( $row['_aie_source_comment_id'] ) ? absint( $row['_aie_source_comment_id'] ) : absint( $row['comment_ID'] ?? 0 );
			$source_parent = isset( $row['_aie_source_comment_parent_id'] ) ? absint( $row['_aie_source_comment_parent_id'] ) : absint( $row['comment_parent'] ?? 0 );

			$target_id     = $source_id ? absint( $map[ (string) $source_id ] ?? 0 ) : 0;
			$target_parent = $source_parent ? absint( $map[ (string) $source_parent ] ?? 0 ) : 0;

			if ( $target_id <= 0 || $source_parent <= 0 || $target_parent <= 0 ) {
				continue;
			}

			$comment = get_comment( $target_id );
			if ( ! $comment ) {
				continue;
			}

			if ( (int) $comment->comment_parent === $target_parent ) {
				continue;
			}

			// Preserve existing dates when updating parent relationship.
			wp_update_comment(
				wp_slash(
					[
						'comment_ID'       => $target_id,
						'comment_parent'   => $target_parent,
						'comment_date'     => (string) $comment->comment_date,
						'comment_date_gmt' => (string) $comment->comment_date_gmt,
					]
				)
			);

			clean_comment_cache( $target_id );
		}

		delete_transient( $key );
	}

	/**
	 * Best-effort fix for cross-site term parent relationships after a Taxonomy_Term_Importer job.
	 *
	 * Terms are exported with source-site `term_id`/`parent` IDs. On import we store a source->target
	 * map in a transient keyed by job_id and then rewrite parents once all terms exist.
	 *
	 * @param int   $job_id        Job ID.
	 * @param array $prepared_data Prepared items.
	 * @return void
	 */
	private function fix_term_parent_relationships( $job_id, $prepared_data ) {
		$job_id = absint( $job_id );
		if ( $job_id <= 0 || ! is_array( $prepared_data ) || empty( $prepared_data ) ) {
			return;
		}

		$key = 'aie_import_term_id_map_' . $job_id;
		$map = get_transient( $key );
		if ( ! is_array( $map ) || empty( $map ) ) {
			return;
		}

		foreach ( $prepared_data as $row ) {
			$taxonomy = isset( $row['taxonomy'] ) ? sanitize_key( (string) $row['taxonomy'] ) : '';
			if ( $taxonomy === '' ) {
				continue;
			}

			$tax_map = isset( $map[ $taxonomy ] ) && is_array( $map[ $taxonomy ] ) ? $map[ $taxonomy ] : [];
			if ( empty( $tax_map ) ) {
				continue;
			}

			$source_id     = isset( $row['_aie_source_term_id'] ) ? absint( $row['_aie_source_term_id'] ) : absint( $row['term_id'] ?? 0 );
			$source_parent = isset( $row['_aie_source_parent_term_id'] ) ? absint( $row['_aie_source_parent_term_id'] ) : absint( $row['parent'] ?? 0 );

			$target_id     = $source_id ? absint( $tax_map[ (string) $source_id ] ?? 0 ) : 0;
			$target_parent = $source_parent ? absint( $tax_map[ (string) $source_parent ] ?? 0 ) : 0;

			// Fallback: resolve parent by exported parent_slug when ID mapping is missing.
			if ( $target_parent <= 0 && ! empty( $row['_aie_source_parent_slug'] ) ) {
				$parent_slug = sanitize_title( (string) $row['_aie_source_parent_slug'] );
				if ( $parent_slug !== '' ) {
					$parent_term = get_term_by( 'slug', $parent_slug, $taxonomy );
					if ( $parent_term && ! is_wp_error( $parent_term ) ) {
						$target_parent = (int) $parent_term->term_id;
					}
				}
			}

			if ( $target_id <= 0 || $source_parent <= 0 || $target_parent <= 0 ) {
				continue;
			}

			$current = get_term( $target_id, $taxonomy );
			if ( ! $current || is_wp_error( $current ) ) {
				continue;
			}

			if ( (int) $current->parent === $target_parent ) {
				continue;
			}

			$result = wp_update_term( $target_id, $taxonomy, [ 'parent' => $target_parent ] );
			if ( is_wp_error( $result ) ) {
				continue;
			}
		}

		delete_transient( $key );
	}
}

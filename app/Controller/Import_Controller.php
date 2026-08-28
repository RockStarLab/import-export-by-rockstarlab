<?php
/**
 * Import Controller
 *
 * Handles import operations via AJAX
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Model\Job;
use RockStarLab\ImportExport\Model\Import\Importer_Factory;
use RockStarLab\ImportExport\Model\Format\Format_Factory;
use RockStarLab\ImportExport\Helper\Fs;
use RockStarLab\ImportExport\Helper\ACF_Fields;
use RockStarLab\ImportExport\Helper\WPML_Compatibility;

defined( 'ABSPATH' ) || exit;

class Import_Controller extends Base_Controller {

	/**
	 * Get AJAX actions
	 *
	 * @return array
	 */
	protected function get_ajax_actions() {
		return [
			'import_upload_file'   => [ 'callback' => 'upload_file' ],
			'import_validate_data' => [ 'callback' => 'validate_data' ],
			'import_start'         => [ 'callback' => 'start_import' ],
			'import_process_batch' => [ 'callback' => 'process_batch' ],
			'import_get_progress'  => [ 'callback' => 'get_progress' ],
			'import_cancel'        => [ 'callback' => 'cancel_import' ],
			'get_acf_fields'       => [ 'callback' => 'get_acf_fields' ],
			'get_yoast_fields'     => [ 'callback' => 'get_yoast_fields' ],
			'get_rank_math_fields' => [ 'callback' => 'get_rank_math_fields' ],
			'get_elementor_fields' => [ 'callback' => 'get_elementor_fields' ],
			'get_database_tables'  => [ 'callback' => 'get_database_tables' ],
			'get_table_columns'    => [ 'callback' => 'get_table_columns' ],
		];
	}

	/**
	 * Upload and parse file
	 */
	public function upload_file() {
		check_ajax_referer( \RockStarLab\ImportExport\Helper\Ajax_Security::nonce_action( 'rsl_ie_import_upload_file' ), 'nonce' );

		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Validated by sanitize_file_upload().
			$this->send_error( __( 'No file uploaded', 'import-export-by-rockstarlab' ), null, 400 );
		}

		$file = $this->sanitize_file_upload( wp_unslash( $_FILES['file'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Uploaded file arrays are validated contextually.
		if ( is_wp_error( $file ) ) {
			$this->send_error( $file, null, 400 );
		}
		$file_extension = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		if ( ! in_array( $file_extension, array( 'csv', 'json', 'xml', 'xlsx', 'ods', 'zip' ), true ) ) {
			$this->send_error( __( 'Invalid file type. Supported formats: CSV, JSON, XML, XLSX, ODS, and ZIP archives containing one supported import file.', 'import-export-by-rockstarlab' ), null, 400 );
		}

		$format = 'zip' === $file_extension ? 'zip' : $file_extension;

		// Validate parser format. ZIP is only an upload container and is parsed after extraction.
		if ( 'zip' !== $format && ! Format_Factory::is_supported( $format ) ) {
			$this->send_error( __( 'Unsupported file format', 'import-export-by-rockstarlab' ), null, 400 );
		}

		// Move file to upload directory
		$upload_result = Fs::upload_file( $file );
		if ( is_wp_error( $upload_result ) ) {
			$this->send_error( $upload_result, null, 500 );
		}

		$file_path = $upload_result['path'];

		if ( 'zip' === $format ) {
			$upload_dir = Fs::get_upload_dir();
			$zip_result = Fs::extract_import_file_from_zip( $file_path, $upload_dir['path'] );
			wp_delete_file( $file_path );

			if ( is_wp_error( $zip_result ) ) {
				$this->send_error( $zip_result, null, 400 );
			}

			$upload_result['name'] = $zip_result['file'];
			$upload_result['path'] = $zip_result['path'];
			$file_path             = $zip_result['path'];
			$format                = $zip_result['format'];
		}

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
			__( 'File uploaded successfully', 'import-export-by-rockstarlab' )
		);
	}

	/**
	 * Validate import data
	 */
	public function validate_data() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'file_path', 'import_type' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$file_path   = $this->get_request_param( 'file_path' );
		$import_type = $this->get_request_param( 'import_type' );
		$mapping     = $this->get_request_array( 'mapping' );
		$options     = $this->normalize_post_options( $import_type, $this->get_request_array( 'options' ) );
		if ( empty( $mapping ) ) {
			$this->send_error(
				new \WP_Error(
					'missing_parameters',
					__( 'Missing required parameters: mapping', 'import-export-by-rockstarlab' )
				),
				null,
				400
			);
		}
		$format    = $this->get_request_param( 'format', 'csv' );
		$delimiter = $this->get_request_param( 'delimiter', ',' );
		$file_path = $this->validate_import_file_path( $file_path, $format );
		if ( is_wp_error( $file_path ) ) {
			$this->send_error( $file_path, null, 400 );
		}

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
		$prepared_data = $this->apply_replace_links_rules_to_data( $prepared_data, $options );

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
			__( 'Data validation passed', 'import-export-by-rockstarlab' )
		);
	}

	/**
	 * Start import
	 */
	public function start_import() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'file_path', 'import_type' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$file_path   = $this->get_request_param( 'file_path' );
		$import_type = $this->get_request_param( 'import_type' );
		$mapping     = $this->get_request_array( 'mapping' );
		if ( empty( $mapping ) ) {
			$this->send_error(
				new \WP_Error(
					'missing_parameters',
					__( 'Missing required parameters: mapping', 'import-export-by-rockstarlab' )
				),
				null,
				400
			);
		}
		$options   = $this->normalize_post_options( $import_type, $this->get_request_array( 'options' ) );
		$format    = $this->get_request_param( 'format', 'csv' );
		$delimiter = $this->get_request_param( 'delimiter', ',' );
		$file_path = $this->validate_import_file_path( $file_path, $format );
		if ( is_wp_error( $file_path ) ) {
			$this->send_error( $file_path, null, 400 );
		}

		// Create job
		$job_model = rsl_ie()->Model->job;
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
			__( 'Import started successfully', 'import-export-by-rockstarlab' )
		);
	}

	/**
	 * Get import progress
	 */
	public function get_progress() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = rsl_ie()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data ) {
			$this->send_error( __( 'Job not found', 'import-export-by-rockstarlab' ), null, 404 );
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
		$estimates = \RockStarLab\ImportExport\Helper\Progress_Tracker::estimate_time_remaining( $job_id );

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
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model  = rsl_ie()->Model->job;
		$job_result = $job_model->update( $job_id, [ 'status' => 'cancelled' ] );

		if ( is_wp_error( $job_result ) ) {
			$this->send_error( $job_result, null, 500 );
		}

		$this->send_success( null, __( 'Import cancelled', 'import-export-by-rockstarlab' ) );
	}

	/**
	 * Process import batch
	 */
	public function process_batch() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = rsl_ie()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data ) {
			$this->send_error( __( 'Job not found', 'import-export-by-rockstarlab' ), null, 404 );
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

		$lock_key = 'rsl_ie_import_job_lock_' . $job_id;
		if ( ! $this->acquire_import_job_lock( $lock_key ) ) {
			$locked_parameters = json_decode( $job_data->parameters, true );
			$locked_result     = isset( $locked_parameters['cumulative_result'] ) && is_array( $locked_parameters['cumulative_result'] )
				? $locked_parameters['cumulative_result']
				: ( json_decode( (string) $job_data->result, true ) ?: [] );
			$locked_offset     = isset( $locked_parameters['offset'] ) ? (int) $locked_parameters['offset'] : (int) $job_data->processed_items;
			$locked_total      = isset( $locked_parameters['total_items'] ) ? (int) $locked_parameters['total_items'] : (int) $job_data->total_items;
			$locked_progress   = $locked_total > 0 ? round( ( $locked_offset / $locked_total ) * 100 ) : (int) $job_data->progress;

			$this->send_success(
				[
					'completed' => false,
					'locked'    => true,
					'offset'    => $locked_offset,
					'progress'  => $locked_progress,
					'result'    => $locked_result,
				]
			);
			return;
		}
		register_shutdown_function(
			static function () use ( $lock_key ) {
				delete_option( $lock_key );
			}
		);

		$parameters  = json_decode( $job_data->parameters, true );
		$import_type = $parameters['import_type'];
		$format      = $parameters['format'];
		$delimiter   = $parameters['delimiter'] ?? ',';
		$mapping     = $parameters['mapping'];
		$options     = $this->normalize_post_options( $import_type, $parameters['options'] ?? [] );
		$offset      = $parameters['offset'] ?? 0;
		$batch_size  = isset( $options['batch_size'] ) ? (int) $options['batch_size'] : 50;

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
			$prepared_data = $this->apply_replace_links_rules_to_data( $prepared_data, $options );
			$total_items   = count( $prepared_data );

			// Preserve source IDs for cross-site relationship fixups (e.g. post_parent).
			if ( $this->is_wpml_post_based_importer( $importer ) ) {
				foreach ( $prepared_data as $row_index => &$prepared_row ) {
					if ( isset( $data[ $row_index ]['ID'] ) ) {
						$prepared_row['_rsl_ie_source_id'] = absint( $data[ $row_index ]['ID'] );
					}
					if ( isset( $data[ $row_index ]['post_parent'] ) ) {
						$prepared_row['_rsl_ie_source_parent_id'] = absint( $data[ $row_index ]['post_parent'] );
					}
					if ( isset( $data[ $row_index ]['post_name'] ) && '' !== (string) $data[ $row_index ]['post_name'] ) {
						$prepared_row['_rsl_ie_source_post_name'] = (string) $data[ $row_index ]['post_name'];
					}
				}
				unset( $prepared_row );
			}

			// Preserve source IDs + portable post hints for cross-site comment relationships.
			if ( class_exists( \RockStarLab\ImportExport\Model\Import\Comment_Importer::class ) && $importer instanceof \RockStarLab\ImportExport\Model\Import\Comment_Importer ) {
				foreach ( $prepared_data as $row_index => &$prepared_row ) {
					// Ensure core date fields are available even if the UI mapping omits them.
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

			// Preserve source IDs + portable parent hints for cross-site term hierarchy fixups.
			if ( class_exists( \RockStarLab\ImportExport\Model\Import\Taxonomy_Term_Importer::class ) && $importer instanceof \RockStarLab\ImportExport\Model\Import\Taxonomy_Term_Importer ) {
				foreach ( $prepared_data as $row_index => &$prepared_row ) {
					if ( isset( $data[ $row_index ]['term_id'] ) ) {
						$prepared_row['_rsl_ie_source_term_id'] = absint( $data[ $row_index ]['term_id'] );
					}
					if ( isset( $data[ $row_index ]['parent'] ) ) {
						$prepared_row['_rsl_ie_source_parent_term_id'] = absint( $data[ $row_index ]['parent'] );
					}
					if ( isset( $data[ $row_index ]['parent_slug'] ) ) {
						$prepared_row['_rsl_ie_source_parent_slug'] = (string) $data[ $row_index ]['parent_slug'];
					}
				}
				unset( $prepared_row );
			}

			// Store prepared data and total in job parameters
			$parameters['prepared_data'] = $prepared_data;
			$parameters['total_items']   = $total_items;
			$parameters['offset']        = 0;

			if ( 'database_table' === $import_type && method_exists( $importer, 'set_options' ) && method_exists( $importer, 'ensure_target_table' ) ) {
				$importer->set_options( $options );
				$table_result = $importer->ensure_target_table( $prepared_data );
				if ( is_wp_error( $table_result ) ) {
					$job_model->update(
						$job_id,
						[
							'status' => 'failed',
							'result' => wp_json_encode( [ 'error' => $table_result->get_error_message() ] ),
						]
					);
					$this->send_error( $table_result, null, 400 );
					return;
				}
			}

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
			\RockStarLab\ImportExport\Helper\Progress_Tracker::update_progress( $job_id, $total_items, 0, 0, 0 );
		}

		$prepared_data     = $parameters['prepared_data'];
		$total_items       = $parameters['total_items'];
		$cumulative_result = $parameters['cumulative_result'];

		// Recreate importer before completion/fixup checks. Each AJAX batch is a
		// separate request, so the importer from the first batch is not available
		// when the final empty-batch request runs.
		$importer = Importer_Factory::get_importer( $import_type, $job_id );
		if ( is_wp_error( $importer ) ) {
			$this->send_error( $importer, null, 500 );
			return;
		}
		$importer->set_options( $options );

		// Get batch
		$batch = array_slice( $prepared_data, $offset, $batch_size );

		if ( empty( $batch ) ) {
			// Post-import fixups for relationship fields (best-effort).
			if ( $this->is_wpml_post_based_importer( $importer ) ) {
				$this->fix_wpml_post_relationships( $job_id, $prepared_data );
			}
			$this->fix_post_parent_relationships( $job_id, $prepared_data );
			$this->fix_comment_parent_relationships( $job_id, $prepared_data );
			$this->fix_wpml_term_relationships( $job_id, $prepared_data );
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

		$replace_links_rules = $this->get_replace_links_rules( $options );

		// Process each item in batch
		foreach ( $batch as $index => $item ) {
			$current_job_status = $job_model->find( $job_id );
			if ( $current_job_status && in_array( $current_job_status->status, [ 'paused', 'cancelled' ], true ) ) {
				$this->send_success(
					[
						'completed' => true,
						'status'    => $current_job_status->status,
						'result'    => $cumulative_result,
					]
				);
				return;
			}

			$item   = $this->apply_replace_links_rules_to_value( $item, $replace_links_rules );
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

			if ( ! is_wp_error( $result ) && 'skipped' !== $result && class_exists( \RockStarLab\ImportExport\Model\Import\Comment_Importer::class ) && $importer instanceof \RockStarLab\ImportExport\Model\Import\Comment_Importer ) {
				$this->preserve_imported_comment_dates( $result, $item );
			}

			$current_job_status = $job_model->find( $job_id );
			if ( $current_job_status && in_array( $current_job_status->status, [ 'paused', 'cancelled' ], true ) ) {
				$processed = $cumulative_result['success'] + $cumulative_result['failed'] + $cumulative_result['skipped'];
				\RockStarLab\ImportExport\Helper\Progress_Tracker::update_progress(
					$job_id,
					$total_items,
					$processed,
					$cumulative_result['success'],
					$cumulative_result['failed']
				);

				$this->send_success(
					[
						'completed' => true,
						'status'    => $current_job_status->status,
						'result'    => $cumulative_result,
					]
				);
				return;
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
		\RockStarLab\ImportExport\Helper\Progress_Tracker::update_progress(
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
	 * Acquire an atomic import job lock.
	 *
	 * Transient writes are not atomic enough for duplicate AJAX requests: two
	 * requests can both miss the transient and process offset 0. add_option()
	 * uses a unique option name, so only one request wins.
	 *
	 * @param string $lock_key Lock option name.
	 * @return bool True when the lock was acquired.
	 */
	private function acquire_import_job_lock( $lock_key ) {
		$lock_key = sanitize_key( (string) $lock_key );
		$now      = time();
		$ttl      = MINUTE_IN_SECONDS;

		if ( add_option( $lock_key, (string) $now, '', 'no' ) ) {
			return true;
		}

		$locked_at = absint( get_option( $lock_key, 0 ) );
		if ( $locked_at > 0 && ( $now - $locked_at ) > $ttl ) {
			delete_option( $lock_key );
			return add_option( $lock_key, (string) $now, '', 'no' );
		}

		return false;
	}

	/**
	 * Get ACF fields for import
	 */
	public function get_acf_fields() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		// Check if ACF is active
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			$this->send_success( [ 'fields' => [] ] );
			return;
		}

		$post_type = $this->get_request_param( 'post_type', '' );
		$taxonomy  = $this->get_request_param( 'taxonomy', '' );
		if ( '' === $post_type && '' !== $taxonomy ) {
			$post_type = 'taxonomy';
		}
		$fields = ACF_Fields::get_fields_for_content_type( $post_type, $taxonomy );

		$this->send_success( [ 'fields' => $fields ] );
	}

	/**
	 * Get Yoast SEO fields for import
	 */
	public function get_yoast_fields() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		// Check if Yoast SEO is active
		if ( ! defined( 'WPSEO_VERSION' ) ) {
			$this->send_success( [ 'fields' => [] ] );
			return;
		}

		$post_type = sanitize_key( (string) $this->get_request_param( 'post_type', '', 'post' ) );
		$fields    = \RockStarLab\ImportExport\Helper\Seo_Fields::get_yoast_fields( $post_type );

		$this->send_success( [ 'fields' => $fields ] );
	}

	/**
	 * Get Rank Math SEO fields for import
	 */
	public function get_rank_math_fields() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		if ( ! \RockStarLab\ImportExport\Helper\Seo_Fields::is_rank_math_active() ) {
			$this->send_success( [ 'fields' => [] ] );
			return;
		}

		$post_type = sanitize_key( (string) $this->get_request_param( 'post_type', '', 'post' ) );

		$this->send_success(
			[
				'fields' => \RockStarLab\ImportExport\Helper\Seo_Fields::get_rank_math_fields( $post_type ),
			]
		);
	}

	/**
	 * Get Elementor fields for import
	 */
	public function get_elementor_fields() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		if ( ! \RockStarLab\ImportExport\Helper\Elementor_Fields::is_active() ) {
			$this->send_success( [ 'fields' => [] ] );
			return;
		}

		$this->send_success(
			[
				'fields' => \RockStarLab\ImportExport\Helper\Elementor_Fields::get_fields(),
			]
		);
	}

	/**
	 * Get database tables
	 */
	public function get_database_tables() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		// Use Database_Table_Exporter to get tables with row counts
		$exporter = new \RockStarLab\ImportExport\Model\Export\Database_Table_Exporter();
		$tables   = $exporter->get_available_tables();

		$this->send_success( [ 'tables' => $tables ] );
	}

	/**
	 * Get table columns with types
	 */
	public function get_table_columns() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'table_name' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$table_name = (string) $this->get_request_param( 'table_name' );
		if ( ! preg_match( '/^[A-Za-z0-9_]+$/', $table_name ) ) {
			$this->send_error( __( 'Invalid table name', 'import-export-by-rockstarlab' ), null, 400 );
		}

		// Use Database_Table_Exporter to get columns
		$exporter = new \RockStarLab\ImportExport\Model\Export\Database_Table_Exporter();
		$columns  = $exporter->get_table_columns( $table_name );

		// Get row count
		global $wpdb;
		$row_count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Counting rows in a user-selected, validated table for import preview.

		if ( empty( $columns ) ) {
			$this->send_error( __( 'Could not retrieve table columns', 'import-export-by-rockstarlab' ), null, 400 );
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
	 * Apply import search/replace rules to prepared import data.
	 *
	 * @param array $data    Prepared import rows.
	 * @param array $options Import options.
	 * @return array Prepared data with replacements applied.
	 */
	private function apply_replace_links_rules_to_data( $data, $options ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$rules = $this->get_replace_links_rules( $options );
		if ( empty( $rules ) ) {
			return $data;
		}

		foreach ( $data as $index => $row ) {
			$data[ $index ] = $this->apply_replace_links_rules_to_value( $row, $rules );
		}

		return $data;
	}

	/**
	 * Normalize import search/replace rules.
	 *
	 * @param array $options Import options.
	 * @return array Normalized rules.
	 */
	private function get_replace_links_rules( $options ) {
		if ( empty( $options['replace_links_rules'] ) || ! is_array( $options['replace_links_rules'] ) ) {
			return [];
		}

		if ( isset( $options['replace_links'] ) && ! filter_var( $options['replace_links'], FILTER_VALIDATE_BOOLEAN ) ) {
			return [];
		}

		$rules = [];
		foreach ( $options['replace_links_rules'] as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$search  = isset( $rule['search'] ) && is_scalar( $rule['search'] ) ? sanitize_text_field( (string) $rule['search'] ) : '';
			$replace = isset( $rule['replace'] ) && is_scalar( $rule['replace'] ) ? sanitize_text_field( (string) $rule['replace'] ) : '';

			if ( '' === $search || $search === $replace ) {
				continue;
			}

			$rules[] = [
				'search'  => $search,
				'replace' => $replace,
			];
		}

		return $rules;
	}

	/**
	 * Recursively apply import search/replace rules to strings and nested arrays.
	 *
	 * JSON strings are intentionally handled as plain strings so URLs inside
	 * encoded Elementor/SEO/ACF payloads can be rewritten without changing the
	 * original structure.
	 *
	 * @param mixed $value Value to update.
	 * @param array $rules Normalized replacement rules.
	 * @return mixed Updated value.
	 */
	private function apply_replace_links_rules_to_value( $value, $rules ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $child_value ) {
				$value[ $key ] = $this->apply_replace_links_rules_to_value( $child_value, $rules );
			}

			return $value;
		}

		if ( is_string( $value ) && '' !== $value ) {
			if ( is_serialized( $value ) ) {
				$unserialized = maybe_unserialize( $value );
				if ( false !== $unserialized || 'b:0;' === $value ) {
					return maybe_serialize( $this->apply_replace_links_rules_to_value( $unserialized, $rules ) );
				}
			}

			foreach ( $rules as $rule ) {
				$value = str_replace( $rule['search'], $rule['replace'], $value );
				$value = str_replace( wp_json_encode( $rule['search'] ), wp_json_encode( $rule['replace'] ), $value );
				$value = str_replace( str_replace( '/', '\\/', $rule['search'] ), str_replace( '/', '\\/', $rule['replace'] ), $value );
				$value = str_replace( rawurlencode( $rule['search'] ), rawurlencode( $rule['replace'] ), $value );
			}
		}

		return $value;
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
	 * @param array $prepared_data Prepared items (includes `_rsl_ie_source_*` keys when available).
	 * @return void
	 */
	private function fix_post_parent_relationships( $job_id, $prepared_data ) {
		$job_id = absint( $job_id );
		if ( $job_id <= 0 || ! is_array( $prepared_data ) || empty( $prepared_data ) ) {
			return;
		}

		$key = 'rsl_ie_import_post_id_map_' . $job_id;
		$map = get_transient( $key );
		if ( ! is_array( $map ) || empty( $map ) ) {
			$map = $this->build_post_source_id_map_from_meta( $prepared_data );
			if ( empty( $map ) ) {
				return;
			}
		}

		$rows = $this->sort_wpml_rows_sources_first( $prepared_data );
		foreach ( $rows as $row ) {
			$source_id     = isset( $row['_rsl_ie_source_id'] ) ? absint( $row['_rsl_ie_source_id'] ) : 0;
			$source_parent = isset( $row['_rsl_ie_source_parent_id'] ) ? absint( $row['_rsl_ie_source_parent_id'] ) : 0;
			$target_id     = $source_id ? absint( $map[ (string) $source_id ] ?? 0 ) : 0;
			$target_parent = $source_parent ? absint( $map[ (string) $source_parent ] ?? 0 ) : 0;

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
	 * Rebuild source post ID => target post ID map from stored import meta.
	 *
	 * @param array $prepared_data Prepared rows.
	 * @return array
	 */
	private function build_post_source_id_map_from_meta( array $prepared_data ) {
		$map = [];
		foreach ( $prepared_data as $row ) {
			$source_id = isset( $row['_rsl_ie_source_id'] ) ? absint( $row['_rsl_ie_source_id'] ) : absint( $row['ID'] ?? 0 );
			if ( $source_id <= 0 ) {
				continue;
			}

			$target_id = $this->find_imported_post_by_source_id( $source_id );
			if ( $target_id > 0 ) {
				$map[ (string) $source_id ] = $target_id;
			}
		}

		return $map;
	}

	/**
	 * Find imported post by any source ID meta key used by free/PRO importers.
	 *
	 * @param int $source_id Source-site post ID.
	 * @return int
	 */
	private function find_imported_post_by_source_id( $source_id ) {
		$source_id = absint( $source_id );
		if ( $source_id <= 0 ) {
			return 0;
		}

		$posts = get_posts(
			[
				'post_type'              => 'any',
				'post_status'            => 'any',
				'posts_per_page'         => 1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Source ID repair lookup after import jobs.
					'relation' => 'OR',
					[
						'key'   => '_rsl_ie_source_post_id',
						'value' => (string) $source_id,
					],
					[
						'key'   => '_rsl_ie_source_id',
						'value' => (string) $source_id,
					],
					[
						'key'   => '_rsl_ie_original_post_id',
						'value' => (string) $source_id,
					],
				],
			]
		);

		return ! empty( $posts[0] ) ? absint( $posts[0] ) : 0;
	}

	/**
	 * Assign WPML languages and link imported post translations after all rows are processed.
	 *
	 * @param int   $job_id        Import job ID.
	 * @param array $prepared_data Prepared rows.
	 * @return void
	 */
	private function fix_wpml_post_relationships( $job_id, $prepared_data ) {
		$job_id = absint( $job_id );
		if ( $job_id <= 0 || ! WPML_Compatibility::is_active() || ! is_array( $prepared_data ) || empty( $prepared_data ) ) {
			return;
		}

		$key = 'rsl_ie_import_post_id_map_' . $job_id;
		$map = get_transient( $key );
		if ( ! is_array( $map ) || empty( $map ) ) {
			$map = $this->build_post_source_id_map_from_meta( $prepared_data );
			if ( empty( $map ) ) {
				return;
			}
		}

		$rows = $this->sort_wpml_rows_sources_first( $prepared_data );
		foreach ( $rows as $row ) {
			if ( empty( $row['wpml_language_code'] ) ) {
				continue;
			}

			$source_id = isset( $row['_rsl_ie_source_id'] ) ? absint( $row['_rsl_ie_source_id'] ) : 0;
			$target_id = $source_id > 0 ? absint( $map[ (string) $source_id ] ?? 0 ) : 0;
			if ( $target_id <= 0 ) {
				continue;
			}

			$wpml_data_raw = get_post_meta( $target_id, '_rsl_ie_wpml_import_data', true );
			$wpml_data     = is_string( $wpml_data_raw ) && '' !== $wpml_data_raw ? json_decode( $wpml_data_raw, true ) : [];
			if ( ! is_array( $wpml_data ) || empty( $wpml_data ) ) {
				$wpml_data = [
					'language_code'        => sanitize_key( (string) $row['wpml_language_code'] ),
					'source_language_code' => ! empty( $row['wpml_source_language_code'] ) ? sanitize_key( (string) $row['wpml_source_language_code'] ) : '',
					'translation_group'    => absint( $row['wpml_translation_group'] ?? 0 ),
					'translation_role'     => sanitize_key( (string) ( $row['wpml_translation_role'] ?? '' ) ),
					'translations'         => [],
				];
			}

			WPML_Compatibility::apply_post_language_details( $target_id, $wpml_data, $map );
		}
	}

	/**
	 * Whether an importer creates WordPress posts and can use WPML post linking.
	 *
	 * @param object $importer Importer instance.
	 * @return bool
	 */
	private function is_wpml_post_based_importer( $importer ) {
		if ( $importer instanceof \RockStarLab\ImportExport\Model\Import\Post_Importer ) {
			return true;
		}

		if ( class_exists( \RockStarLab\ImportExport\Model\Import\Media_Importer::class ) && $importer instanceof \RockStarLab\ImportExport\Model\Import\Media_Importer ) {
			return true;
		}

		if ( class_exists( \RockStarLab\ImportExport\Model\Import\Woo_Coupon_Importer::class ) && $importer instanceof \RockStarLab\ImportExport\Model\Import\Woo_Coupon_Importer ) {
			return true;
		}

		return class_exists( \RockStarLab\ImportExport\Model\Import\Product_Importer::class )
			&& $importer instanceof \RockStarLab\ImportExport\Model\Import\Product_Importer;
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
	 * This is only a fallback when an importer does not return the new comment
	 * ID. IDs are read in bounded pages and metadata uses WordPress caches.
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

		$key = 'rsl_ie_import_comment_id_map_' . $job_id;
		$map = get_transient( $key );
		if ( ! is_array( $map ) || empty( $map ) ) {
			return;
		}

		foreach ( $prepared_data as $row ) {
			$source_id     = isset( $row['_rsl_ie_source_comment_id'] ) ? absint( $row['_rsl_ie_source_comment_id'] ) : absint( $row['comment_ID'] ?? 0 );
			$source_parent = isset( $row['_rsl_ie_source_comment_parent_id'] ) ? absint( $row['_rsl_ie_source_comment_parent_id'] ) : absint( $row['comment_parent'] ?? 0 );

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

			wp_update_comment(
				[
					'comment_ID'     => $target_id,
					'comment_parent' => $target_parent,
				]
			);
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

		$key = 'rsl_ie_import_term_id_map_' . $job_id;
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

			$source_id     = isset( $row['_rsl_ie_source_term_id'] ) ? absint( $row['_rsl_ie_source_term_id'] ) : absint( $row['term_id'] ?? 0 );
			$source_parent = isset( $row['_rsl_ie_source_parent_term_id'] ) ? absint( $row['_rsl_ie_source_parent_term_id'] ) : absint( $row['parent'] ?? 0 );

			$target_id     = $source_id ? absint( $tax_map[ (string) $source_id ] ?? 0 ) : 0;
			$target_parent = $source_parent ? absint( $tax_map[ (string) $source_parent ] ?? 0 ) : 0;

			// Fallback: resolve parent by exported parent_slug when ID mapping is missing.
			if ( $target_parent <= 0 && ! empty( $row['_rsl_ie_source_parent_slug'] ) ) {
				$parent_slug = sanitize_title( (string) $row['_rsl_ie_source_parent_slug'] );
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

	/**
	 * Assign WPML languages and link imported term translations after all rows are processed.
	 *
	 * @param int   $job_id        Import job ID.
	 * @param array $prepared_data Prepared rows.
	 * @return void
	 */
	private function fix_wpml_term_relationships( $job_id, $prepared_data ) {
		$job_id = absint( $job_id );
		if ( $job_id <= 0 || ! WPML_Compatibility::is_active() || ! is_array( $prepared_data ) || empty( $prepared_data ) ) {
			return;
		}

		$map = get_transient( 'rsl_ie_import_term_id_map_' . $job_id );
		if ( ! is_array( $map ) || empty( $map ) ) {
			return;
		}

		foreach ( $prepared_data as $row ) {
			$taxonomy = isset( $row['taxonomy'] ) ? sanitize_key( (string) $row['taxonomy'] ) : '';
			if ( '' === $taxonomy || empty( $row['wpml_language_code'] ) || empty( $map[ $taxonomy ] ) || ! is_array( $map[ $taxonomy ] ) ) {
				continue;
			}

			$source_id = isset( $row['_rsl_ie_source_term_id'] ) ? absint( $row['_rsl_ie_source_term_id'] ) : absint( $row['term_id'] ?? 0 );
			$target_id = $source_id > 0 ? absint( $map[ $taxonomy ][ (string) $source_id ] ?? 0 ) : 0;
			if ( $target_id <= 0 ) {
				continue;
			}

			$wpml_data_raw = get_term_meta( $target_id, '_rsl_ie_wpml_import_data', true );
			$wpml_data     = is_string( $wpml_data_raw ) && '' !== $wpml_data_raw ? json_decode( $wpml_data_raw, true ) : [];
			if ( ! is_array( $wpml_data ) || empty( $wpml_data ) ) {
				$wpml_data = [
					'language_code'        => sanitize_key( (string) $row['wpml_language_code'] ),
					'source_language_code' => ! empty( $row['wpml_source_language_code'] ) ? sanitize_key( (string) $row['wpml_source_language_code'] ) : '',
					'translation_group'    => absint( $row['wpml_translation_group'] ?? 0 ),
					'translation_role'     => sanitize_key( (string) ( $row['wpml_translation_role'] ?? '' ) ),
					'translations'         => [],
				];
			}

			WPML_Compatibility::apply_term_language_details( $target_id, $taxonomy, $wpml_data, $map[ $taxonomy ] );
		}
	}

	/**
	 * Process WPML source-language items before their translations.
	 *
	 * @param array $rows Prepared import rows.
	 * @return array
	 */
	private function sort_wpml_rows_sources_first( array $rows ) {
		usort(
			$rows,
			static function ( $left, $right ) {
				$left_is_source  = empty( $left['wpml_source_language_code'] ) || 'source' === ( $left['wpml_translation_role'] ?? '' );
				$right_is_source = empty( $right['wpml_source_language_code'] ) || 'source' === ( $right['wpml_translation_role'] ?? '' );

				if ( $left_is_source === $right_is_source ) {
					return 0;
				}

				return $left_is_source ? -1 : 1;
			}
		);

		return $rows;
	}

	/**
	 * Resolve an import file only from plugin-managed upload directories.
	 *
	 * @param string $file_path Candidate file path.
	 * @param string $format    Requested parser format.
	 * @return string|\WP_Error
	 */
	private function validate_import_file_path( $file_path, $format ) {
		$format          = strtolower( (string) $format );
		$allowed_formats = array( 'csv', 'json', 'xml', 'xlsx', 'ods' );

		if ( ! in_array( $format, $allowed_formats, true ) ) {
			return new \WP_Error( 'invalid_format', __( 'Only CSV, JSON, XML, XLSX, and ODS import files are supported.', 'import-export-by-rockstarlab' ) );
		}

		$real_path = realpath( (string) $file_path );
		if ( false === $real_path || ! is_file( $real_path ) || $format !== strtolower( pathinfo( $real_path, PATHINFO_EXTENSION ) ) ) {
			return new \WP_Error( 'invalid_file_path', __( 'Invalid import file path.', 'import-export-by-rockstarlab' ) );
		}

		$uploads = wp_upload_dir();
		$allowed = array(
			realpath( trailingslashit( $uploads['basedir'] ) . 'rsl-ie-uploads' ),
			realpath( trailingslashit( $uploads['basedir'] ) . 'rsl-ie-imports' ),
		);

		foreach ( array_filter( $allowed ) as $directory ) {
			if ( 0 === strpos( $real_path, trailingslashit( $directory ) ) ) {
				return $real_path;
			}
		}

		return new \WP_Error( 'invalid_file_path', __( 'Import file must be inside the plugin upload directory.', 'import-export-by-rockstarlab' ) );
	}
}

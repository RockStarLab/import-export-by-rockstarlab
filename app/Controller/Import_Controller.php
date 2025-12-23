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

/**
 * Import Controller Class
 *
 * Manages import workflow:
 * 1. File upload and parsing
 * 2. Data validation
 * 3. Job creation
 * 4. Import execution
 * 5. Progress tracking
 *
 * @package WP_AIE\Controller
 */
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
			'import_get_progress'  => [ 'callback' => 'get_progress' ],
			'import_cancel'        => [ 'callback' => 'cancel_import' ],
			'get_acf_fields'       => [ 'callback' => 'get_acf_fields' ],
			'get_yoast_fields'     => [ 'callback' => 'get_yoast_fields' ],
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
		if ( empty( $_FILES['file'] ) ) {
			$this->send_error( __( 'No file uploaded', 'wp-advanced-import-export' ), null, 400 );
		}

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$file = $this->sanitize_file_upload( wp_unslash( $_FILES['file'] ) );
		if ( is_wp_error( $file ) ) {
			$this->send_error( $file, null, 400 );
		}

		$format = $this->get_request_param( 'format', 'csv' );

		// Validate format
		if ( ! Format_Factory::is_supported( $format ) ) {
			$this->send_error( __( 'Unsupported file format', 'wp-advanced-import-export' ), null, 400 );
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

		// Parse file
		$parser = Format_Factory::create( $format );
		if ( is_wp_error( $parser ) ) {
			$this->send_error( $parser, null, 500 );
		}

		$data = $parser->parse( $file_path );
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
		$options     = $this->get_request_array( 'options' );
		$format      = $this->get_request_param( 'format', 'csv' );

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
					'mapping'     => $mapping,
					'options'     => $options,
				]
			),
		];

		$job_id = $job_model->create( $job_data );
		if ( is_wp_error( $job_id ) ) {
			$this->send_error( $job_id, null, 500 );
		}

		// Start import in background
		$this->process_import_job( $job_id );

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

		$this->send_success(
			[
				'status'   => $job_data->status,
				'progress' => $job_data->progress,
				'result'   => $job_data->result ? json_decode( $job_data->result, true ) : null,
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
	 * Process import job
	 *
	 * @param int $job_id Job ID
	 */
	private function process_import_job( $job_id ) {
		$job_model = WP_AIE()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data ) {
			return;
		}

		// Update status to processing
		$job_model->update( $job_id, [ 'status' => 'processing' ] );

		$parameters  = json_decode( $job_data->parameters, true );
		$import_type = $parameters['import_type'];
		$format      = $parameters['format'];
		$mapping     = $parameters['mapping'];
		$options     = $parameters['options'] ?? [];

		// Parse file
		$parser = Format_Factory::create( $format );
		$data   = $parser->parse( $job_data->file_path );

		if ( is_wp_error( $data ) ) {
			$job_model->update(
				$job_id,
				[
					'status' => 'failed',
					'result' => wp_json_encode( [ 'error' => $data->get_error_message() ] ),
				]
			);
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
			return;
		}

		// Prepare and import
		$prepared_data = $importer->prepare( $data, $mapping );
		$result        = $importer->import( $prepared_data, $options );

		if ( is_wp_error( $result ) ) {
			$job_model->update(
				$job_id,
				[
					'status'   => 'failed',
					'progress' => 100,
					'result'   => wp_json_encode( [ 'error' => $result->get_error_message() ] ),
				]
			);
		} else {
			$job_model->update(
				$job_id,
				[
					'status'   => 'completed',
					'progress' => 100,
					'result'   => wp_json_encode( $result ),
				]
			);
		}
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
}

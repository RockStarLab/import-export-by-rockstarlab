<?php
/**
 * Export Controller
 *
 * Handles export operations via AJAX
 *
 * @package WP_AIE\Controller
 */

namespace WP_AIE\Controller;

use WP_AIE\Model\Job;
use WP_AIE\Model\Export\Exporter_Factory;
use WP_AIE\Model\Format\Format_Factory;
use WP_AIE\Helper\Fs;
use WP_AIE\Helper\Logger;

/**
 * Export Controller Class
 *
 * Manages export workflow:
 * 1. Configure export options
 * 2. Get preview/count
 * 3. Job creation
 * 4. Export execution
 * 5. File generation and download
 *
 * @package WP_AIE\Controller
 */
class Export_Controller extends Base_Controller {

	/**
	 * Get AJAX actions
	 *
	 * @return array
	 */
	protected function get_ajax_actions() {
		return [
			'export_get_count'    => [ 'callback' => 'get_count' ],
			'export_get_preview'  => [ 'callback' => 'get_preview' ],
			'export_start'        => [ 'callback' => 'start_export' ],
			'export_get_progress' => [ 'callback' => 'get_progress' ],
			'export_download'     => [ 'callback' => 'download_file' ],
			'export_cancel'       => [ 'callback' => 'cancel_export' ],
			'get_post_types'      => [ 'callback' => 'get_post_types' ],
			'get_database_tables' => [ 'callback' => 'get_database_tables' ],
			'get_table_columns'   => [ 'callback' => 'get_table_columns' ],
			'get_taxonomies'      => [ 'callback' => 'get_taxonomies' ],
			'get_custom_fields'   => [ 'callback' => 'get_custom_fields' ],
			'get_acf_fields'      => [ 'callback' => 'get_acf_fields' ],
			'get_yoast_fields'    => [ 'callback' => 'get_yoast_fields' ],
		];
	}

	/**
	 * Get count of items available for export
	 */
	public function get_count() {
		$verification = $this->verify_request( 'export_count' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'export_type' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$export_type = $this->get_request_param( 'export_type' );
		$options     = $this->get_request_array( 'options' );

		$count = Exporter_Factory::get_count( $export_type, $options );

		if ( is_wp_error( $count ) ) {
			$this->send_error( $count, null, 400 );
		}

		$this->send_success( [ 'count' => $count ] );
	}

	/**
	 * Get preview of export data
	 */
	public function get_preview() {
		$verification = $this->verify_request( 'export_preview' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'export_type' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$export_type = $this->get_request_param( 'export_type' );
		$options     = $this->get_request_array( 'options' );

		// Limit preview to 10 items
		$preview_options = array_merge( $options, [ 'limit' => 10 ] );

		$exporter = Exporter_Factory::get_exporter( $export_type );
		if ( is_wp_error( $exporter ) ) {
			$this->send_error( $exporter, null, 400 );
		}

		$data = $exporter->get_data( $preview_options );
		if ( is_wp_error( $data ) ) {
			$this->send_error( $data, null, 500 );
		}

		$this->send_success(
			[
				'preview' => $data,
				'fields'  => $exporter->get_available_fields(),
			]
		);
	}

	/**
	 * Start export
	 */
	public function start_export() {
		$verification = $this->verify_request( 'export_start' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'export_type', 'format' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$export_type = $this->get_request_param( 'export_type' );
		$format      = $this->get_request_param( 'format' );
		$options     = $this->get_request_array( 'options' );

		// Validate format
		if ( ! Format_Factory::is_supported( $format ) ) {
			$this->send_error( __( 'Unsupported export format', 'wp-advanced-import-export' ), null, 400 );
		}

		// Create job
		$job      = new Job();
		$job_data = [
			'type'       => 'export',
			'status'     => 'pending',
			'user_id'    => $this->get_current_user_id(),
			'parameters' => wp_json_encode(
				[
					'export_type' => $export_type,
					'format'      => $format,
					'options'     => $options,
				]
			),
		];

		$job_id = $job->create( $job_data );
		if ( is_wp_error( $job_id ) ) {
			$this->send_error( $job_id, null, 500 );
		}

		// Start export in background
		$this->process_export_job( $job_id );

		$this->log(
			'start_export',
			[
				'job_id'      => $job_id,
				'export_type' => $export_type,
			]
		);

		$this->send_success(
			[
				'job_id' => $job_id,
			],
			__( 'Export started successfully', 'wp-advanced-import-export' )
		);
	}

	/**
	 * Get export progress
	 */
	public function get_progress() {
		$verification = $this->verify_request( 'export_progress' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$job      = new Job();
		$job_data = $job->find( $job_id );

		if ( ! $job_data ) {
			$this->send_error( __( 'Job not found', 'wp-advanced-import-export' ), null, 404 );
		}

		$this->send_success(
			[
				'status'    => $job_data->status,
				'progress'  => $job_data->progress,
				'file_path' => $job_data->file_path,
				'result'    => $job_data->result ? json_decode( $job_data->result, true ) : null,
			]
		);
	}

	/**
	 * Download export file
	 */
	public function download_file() {
		$verification = $this->verify_request( 'export_download' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$job      = new Job();
		$job_data = $job->find( $job_id );

		if ( ! $job_data || empty( $job_data->file_path ) ) {
			$this->send_error( __( 'Export file not found', 'wp-advanced-import-export' ), null, 404 );
		}

		$file_path = $job_data->file_path;

		if ( ! file_exists( $file_path ) ) {
			$this->send_error( __( 'Export file does not exist', 'wp-advanced-import-export' ), null, 404 );
		}

		// Send file for download
		$parameters = json_decode( $job_data->parameters, true );
		$format     = $parameters['format'] ?? 'csv';
		$filename   = sprintf( 'export-%s.%s', gmdate( 'Y-m-d-His' ), $format );

		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $file_path );

		$this->log(
			'download_export',
			[
				'job_id'   => $job_id,
				'filename' => $filename,
			]
		);

		exit;
	}

	/**
	 * Cancel export
	 */
	public function cancel_export() {
		$verification = $this->verify_request( 'export_cancel' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$job        = new Job();
		$job_result = $job->update( $job_id, [ 'status' => 'cancelled' ] );

		if ( is_wp_error( $job_result ) ) {
			$this->send_error( $job_result, null, 500 );
		}

		$this->log( 'cancel_export', [ 'job_id' => $job_id ] );

		$this->send_success( null, __( 'Export cancelled', 'wp-advanced-import-export' ) );
	}

	/**
	 * Process export job
	 *
	 * @param int $job_id Job ID
	 */
	private function process_export_job( $job_id ) {
		$job      = new Job();
		$job_data = $job->find( $job_id );

		if ( ! $job_data ) {
			return;
		}

		// Update status to processing
		$job->update( $job_id, [ 'status' => 'processing' ] );

		$parameters  = json_decode( $job_data->parameters, true );
		$export_type = $parameters['export_type'];
		$format      = $parameters['format'];
		$options     = $parameters['options'] ?? [];

		// Get exporter
		$exporter = Exporter_Factory::get_exporter( $export_type, $job_id );

		if ( is_wp_error( $exporter ) ) {
			$job->update(
				$job_id,
				[
					'status' => 'failed',
					'result' => wp_json_encode( [ 'error' => $exporter->get_error_message() ] ),
				]
			);
			return;
		}

		// Export data
		$export_result = $exporter->export( $options );

		if ( is_wp_error( $export_result ) ) {
			$job->update(
				$job_id,
				[
					'status'   => 'failed',
					'progress' => 100,
					'result'   => wp_json_encode( [ 'error' => $export_result->get_error_message() ] ),
				]
			);
			return;
		}

		$data  = $export_result['data'];
		$stats = $export_result['stats'];

		// Generate file
		$formatter    = Format_Factory::create( $format );
		$file_content = $formatter->generate( $data );

		if ( is_wp_error( $file_content ) ) {
			$job->update(
				$job_id,
				[
					'status' => 'failed',
					'result' => wp_json_encode( [ 'error' => $file_content->get_error_message() ] ),
				]
			);
			return;
		}

		// Save file
		$filename  = sprintf( 'export-%s-%d.%s', $export_type, $job_id, $format );
		$file_save = Fs::save_export_file( $filename, $file_content );

		if ( is_wp_error( $file_save ) ) {
			$job->update(
				$job_id,
				[
					'status' => 'failed',
					'result' => wp_json_encode( [ 'error' => $file_save->get_error_message() ] ),
				]
			);
			return;
		}

		// Update job
		$job->update(
			$job_id,
			[
				'status'    => 'completed',
				'progress'  => 100,
				'file_path' => $file_save['path'],
				'result'    => wp_json_encode( $stats ),
			]
		);
	}

	/**
	 * Get all registered post types
	 */
	public function get_post_types() {
		$verification = $this->verify_request( 'export_get_post_types' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$include_hidden = $this->get_request_param( 'include_hidden', false );

		// Get all post types
		// When include_hidden is true, we need to explicitly get all post types
		// because get_post_types() with empty args doesn't return non-public types
		$args = [
			'show_ui' => true, // Get all post types that have UI (includes custom post types)
		];

		if ( ! $include_hidden ) {
			$args['public'] = true;
		}

		$post_types = get_post_types( $args, 'objects' );

		$result = [];
		foreach ( $post_types as $post_type ) {
			// Skip attachments as they're handled separately as media
			if ( 'attachment' === $post_type->name ) {
				continue;
			}

			$result[] = [
				'name'   => $post_type->name,
				'label'  => $post_type->label,
				'public' => $post_type->public,
			];
		}

		// Sort by label
		usort(
			$result,
			function ( $a, $b ) {
				return strcmp( $a['label'], $b['label'] );
			}
		);

		$this->send_success( $result );
	}

	/**
	 * Get database tables
	 */
	public function get_database_tables() {
		$verification = $this->verify_request( 'export_count' );
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
		$verification = $this->verify_request( 'export_count' );
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

		if ( empty( $columns ) ) {
			$this->send_error( __( 'Could not retrieve table columns', 'wp-advanced-import-export' ), null, 400 );
		}

		$this->send_success( [ 'columns' => $columns ] );
	}

	/**
	 * Map MySQL data type to filter data type
	 *
	 * @param string $mysql_type MySQL data type.
	 * @return string Filter data type (string, number, date).
	 */
	private function map_mysql_type_to_filter_type( $mysql_type ) {
		$mysql_type = strtolower( $mysql_type );

		// Number types
		if ( in_array(
			$mysql_type,
			[ 'int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'float', 'double' ],
			true
		) ) {
			return 'number';
		}

		// Date types
		if ( in_array(
			$mysql_type,
			[ 'date', 'datetime', 'timestamp', 'time', 'year' ],
			true
		) ) {
			return 'date';
		}

		// Default to string for all text types
		return 'string';
	}

	/**
	 * Get taxonomies for a post type
	 */
	public function get_taxonomies() {
		error_log( 'Export_Controller::get_taxonomies() called' );
		error_log( 'POST data: ' . print_r( $_POST, true ) );

		$verification = $this->verify_request( 'export_fields' );
		if ( is_wp_error( $verification ) ) {
			error_log( 'Verification failed: ' . $verification->get_error_message() );
			$this->send_error( $verification, null, 403 );
		}

		$post_type = $this->get_request_param( 'post_type', 'post' );
		error_log( 'Post type: ' . $post_type );

		// Get all taxonomies registered for this post type
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		error_log( 'Taxonomies found: ' . count( $taxonomies ) );

		$taxonomy_list = [];
		foreach ( $taxonomies as $taxonomy ) {
			$taxonomy_list[] = [
				'name'  => $taxonomy->name,
				'label' => $taxonomy->label,
			];
		}

		error_log( 'Sending success with ' . count( $taxonomy_list ) . ' taxonomies' );
		$this->send_success( [ 'taxonomies' => $taxonomy_list ] );
	}

	/**
	 * Get custom fields for a post type
	 */
	public function get_custom_fields() {
		$verification = $this->verify_request( 'export_fields' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		global $wpdb;

		$post_type = $this->get_request_param( 'post_type', 'post' );

		// Get unique meta keys for this post type
		$meta_keys = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_key 
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
				WHERE p.post_type = %s
				AND pm.meta_key NOT LIKE '\\_%%'
				ORDER BY pm.meta_key ASC
				LIMIT 100",
				$post_type
			)
		);

		$fields = [];
		foreach ( $meta_keys as $meta ) {
			$fields[] = [
				'name'  => $meta->meta_key,
				'label' => $meta->meta_key,
			];
		}

		$this->send_success( [ 'fields' => $fields ] );
	}

	/**
	 * Get ACF fields for a post type
	 */
	public function get_acf_fields() {
		$verification = $this->verify_request( 'export_fields' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		// Check if ACF is active
		if ( ! function_exists( 'acf_get_field_groups' ) ) {
			$this->send_success( [ 'fields' => [] ] );
			return;
		}

		$post_type = $this->get_request_param( 'post_type', 'post' );

		// Determine the location rule based on content type
		$location_args = [];
		if ( $post_type === 'user' ) {
			$location_args['user_form'] = 'all'; // ACF User fields
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
	 * Get Yoast SEO fields
	 */
	public function get_yoast_fields() {
		$verification = $this->verify_request( 'export_fields' );
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
			],
			[
				'name'  => '_yoast_wpseo_meta-robots-nofollow',
				'label' => 'Meta Robots (Follow)',
			],
			[
				'name'  => '_yoast_wpseo_opengraph-title',
				'label' => 'Facebook Title',
			],
			[
				'name'  => '_yoast_wpseo_opengraph-description',
				'label' => 'Facebook Description',
			],
			[
				'name'  => '_yoast_wpseo_opengraph-image',
				'label' => 'Facebook Image',
			],
			[
				'name'  => '_yoast_wpseo_twitter-title',
				'label' => 'Twitter Title',
			],
			[
				'name'  => '_yoast_wpseo_twitter-description',
				'label' => 'Twitter Description',
			],
			[
				'name'  => '_yoast_wpseo_twitter-image',
				'label' => 'Twitter Image',
			],
		];

		$this->send_success( [ 'fields' => $fields ] );
	}
}

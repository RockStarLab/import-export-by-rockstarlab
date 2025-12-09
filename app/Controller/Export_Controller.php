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
use WP_AIE\Model\Queue\Export_Processor;
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
			'export_get_count'     => [ 'callback' => 'get_count' ],
			'export_get_preview'   => [ 'callback' => 'get_preview' ],
			'export_start'         => [ 'callback' => 'start_export' ],
			'export_get_progress'  => [ 'callback' => 'get_progress' ],
			'export_download'      => [ 'callback' => 'download_file' ],
			'secure_download'      => [ 'callback' => 'secure_download' ],
			'export_cancel'        => [ 'callback' => 'cancel_export' ],
			'export_process_batch' => [ 'callback' => 'process_export_batch' ],
			'get_post_types'       => [ 'callback' => 'get_post_types' ],
			'get_database_tables'  => [ 'callback' => 'get_database_tables' ],
			'get_table_columns'    => [ 'callback' => 'get_table_columns' ],
			'get_taxonomies'       => [ 'callback' => 'get_taxonomies' ],
			'get_custom_fields'    => [ 'callback' => 'get_custom_fields' ],
			'get_acf_fields'       => [ 'callback' => 'get_acf_fields' ],
			'get_yoast_fields'     => [ 'callback' => 'get_yoast_fields' ],
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

		// Get all export parameters
		$filters         = $this->get_request_array( 'filters' );
		$fields          = $this->get_request_array( 'fields' );
		$format_options  = $this->get_request_array( 'format_options' );
		$dynamic_filters = $this->get_request_array( 'dynamic_filters' );
		$custom_fields   = $this->get_request_array( 'custom_fields' );
		$taxonomy        = $this->get_request_array( 'taxonomy' );
		$field_functions = $this->get_request_array( 'field_functions' );

		// Validate format
		if ( ! Format_Factory::is_supported( $format ) ) {
			$this->send_error( __( 'Unsupported export format', 'wp-advanced-import-export' ), null, 400 );
		}

		// Create job
		$job_model = WP_AIE()->Model->job;
		$job_data  = [
			'type'        => 'export',
			'status'      => 'pending',
			'user_id'     => $this->get_current_user_id(),
			'data_type'   => $export_type,
			'file_format' => $format,
			'parameters'  => wp_json_encode(
				[
					'export_type'     => $export_type,
					'format'          => $format,
					'options'         => $options,
					'filters'         => $filters,
					'fields'          => $fields,
					'format_options'  => $format_options,
					'dynamic_filters' => $dynamic_filters,
					'custom_fields'   => $custom_fields,
					'taxonomy'        => $taxonomy,
					'field_functions' => $field_functions,
				]
			),
		];

		$job_id = $job_model->create( $job_data );
		if ( is_wp_error( $job_id ) ) {
			$this->send_error( $job_id, null, 500 );
		}

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

		$job_model = WP_AIE()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data ) {
			$this->send_error( __( 'Job not found', 'wp-advanced-import-export' ), null, 404 );
		}

		// Calculate progress metrics
		$total      = (int) $job_data->total_items;
		$processed  = (int) $job_data->processed_items;
		$percentage = $total > 0 ? ( $processed / $total ) * 100 : 0;

		// Calculate time estimates
		$estimates = $this->calculate_time_estimates( $job_data );

		$this->send_success(
			[
				'status'     => $job_data->status,
				'progress'   => $job_data->progress,
				'percentage' => round( $percentage, 2 ),
				'processed'  => $processed,
				'total'      => $total,
				'file_path'  => $job_data->file_path,
				'file_size'  => $job_data->file_size,
				'estimates'  => $estimates,
				'result'     => $job_data->result ? json_decode( $job_data->result, true ) : null,
			]
		);
	}

	/**
	 * Calculate time estimates for job progress
	 *
	 * @param object $job_data Job data object
	 * @return array Time estimates
	 */
	private function calculate_time_estimates( $job_data ) {
		$estimates = [
			'elapsed_formatted'   => '-',
			'remaining_formatted' => '-',
			'items_per_second'    => 0,
		];

		// Calculate elapsed time
		$started_at = $job_data->started_at ?? $job_data->created_at;
		if ( $started_at ) {
			$start_timestamp = strtotime( $started_at );
			$now_timestamp   = current_time( 'timestamp' );
			$elapsed_seconds = $now_timestamp - $start_timestamp;

			$estimates['elapsed_formatted'] = $this->format_duration( $elapsed_seconds );
			$estimates['elapsed_seconds']   = $elapsed_seconds;

			// Calculate speed and remaining time
			$processed = (int) $job_data->processed_items;
			$total     = (int) $job_data->total_items;

			if ( $processed > 0 && $elapsed_seconds > 0 ) {
				$items_per_second              = $processed / $elapsed_seconds;
				$estimates['items_per_second'] = round( $items_per_second, 2 );

				$remaining = $total - $processed;
				if ( $remaining > 0 && $items_per_second > 0 ) {
					$remaining_seconds                = $remaining / $items_per_second;
					$estimates['remaining_formatted'] = $this->format_duration( (int) $remaining_seconds );
					$estimates['remaining_seconds']   = (int) $remaining_seconds;
				} else {
					$estimates['remaining_formatted'] = '0s';
					$estimates['remaining_seconds']   = 0;
				}
			}
		}

		return $estimates;
	}

	/**
	 * Format duration in human-readable format
	 *
	 * @param int $seconds Duration in seconds
	 * @return string Formatted duration
	 */
	private function format_duration( $seconds ) {
		if ( $seconds < 60 ) {
			return $seconds . 's';
		} elseif ( $seconds < 3600 ) {
			$minutes = floor( $seconds / 60 );
			$secs    = $seconds % 60;
			return sprintf( '%dm %ds', $minutes, $secs );
		} else {
			$hours   = floor( $seconds / 3600 );
			$minutes = floor( ( $seconds % 3600 ) / 60 );
			return sprintf( '%dh %dm', $hours, $minutes );
		}
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

		$job_model = WP_AIE()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data || empty( $job_data->file_path ) ) {
			$this->send_error( __( 'Export file not found', 'wp-advanced-import-export' ), null, 404 );
		}

		$file_path = $job_data->file_path;

		if ( ! file_exists( $file_path ) ) {
			$this->send_error( __( 'Export file does not exist', 'wp-advanced-import-export' ), null, 404 );
		}

		// Generate download URL with nonce for security
		$parameters = json_decode( $job_data->parameters, true );
		$format     = $parameters['format'] ?? 'csv';
		$filename   = sprintf( 'export-%s.%s', gmdate( 'Y-m-d-His' ), $format );

		// Generate secure download nonce
		$download_nonce = wp_create_nonce( 'aie_download_' . $job_id );

		$download_url = add_query_arg(
			[
				'action'   => 'aie_secure_download',
				'job_id'   => $job_id,
				'_wpnonce' => $download_nonce,
			],
			admin_url( 'admin-ajax.php' )
		);

		$this->log(
			'prepare_download',
			[
				'job_id'   => $job_id,
				'filename' => $filename,
			]
		);

		$this->send_success(
			[
				'download_url' => $download_url,
				'filename'     => $filename,
				'file_size'    => filesize( $file_path ),
			]
		);
	}

	/**
	 * Secure download handler
	 * Handles actual file download with nonce verification
	 */
	public function secure_download() {
		// Verify nonce
		$job_id = isset( $_GET['job_id'] ) ? (int) $_GET['job_id'] : 0;
		$nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'aie_download_' . $job_id ) ) {
			wp_die( esc_html__( 'Security check failed', 'wp-advanced-import-export' ), 403 );
		}

		// Get job
		$job_model = WP_AIE()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data || empty( $job_data->file_path ) ) {
			wp_die( esc_html__( 'Export file not found', 'wp-advanced-import-export' ), 404 );
		}

		$file_path = $job_data->file_path;

		if ( ! file_exists( $file_path ) ) {
			wp_die( esc_html__( 'Export file does not exist', 'wp-advanced-import-export' ), 404 );
		}

		// Send file for download
		$parameters = json_decode( $job_data->parameters, true );
		$format     = $parameters['format'] ?? 'csv';
		$filename   = sprintf( 'export-%s.%s', gmdate( 'Y-m-d-His' ), $format );

		// Set headers for download
		header( 'Content-Type: application/octet-stream' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . filesize( $file_path ) );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );
		header( 'Cache-Control: must-revalidate' );

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		readfile( $file_path );

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

		$job_model  = WP_AIE()->Model->job;
		$job_result = $job_model->update( $job_id, [ 'status' => 'cancelled' ] );

		if ( is_wp_error( $job_result ) ) {
			$this->send_error( $job_result, null, 500 );
		}

		$this->log( 'cancel_export', [ 'job_id' => $job_id ] );

		$this->send_success( null, __( 'Export cancelled', 'wp-advanced-import-export' ) );
	}

	/**
	 * Process export batch (called via AJAX for async processing)
	 */
	public function process_export_batch() {
		$verification = $this->verify_request( 'export_process_batch' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		// Process the job using Export_Processor
		$processor = new Export_Processor();
		$result    = $processor->process( $job_id );

		$this->send_success( $result );
	}

	/**
	 * Process export job
	 *
	 * @param int $job_id Job ID
	 */
	private function process_export_job( $job_id ) {
		$job_model = WP_AIE()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data ) {
			return;
		}

		// Update status to processing
		$job_model->update( $job_id, [ 'status' => 'processing' ] );

		$parameters      = json_decode( $job_data->parameters, true );
		$export_type     = $parameters['export_type'];
		$format          = $parameters['format'];
		$options         = $parameters['options'] ?? [];
		$filters         = $parameters['filters'] ?? [];
		$fields          = $parameters['fields'] ?? [];
		$format_options  = $parameters['format_options'] ?? [];
		$dynamic_filters = $parameters['dynamic_filters'] ?? [];
		$custom_fields   = $parameters['custom_fields'] ?? [];
		$taxonomy        = $parameters['taxonomy'] ?? [];
		$field_functions = $parameters['field_functions'] ?? [];

		// Merge all options for export
		$export_options = array_merge(
			$options,
			[
				'post_type'       => $export_type,  // Add post_type from export_type
				'filters'         => $filters,
				'fields'          => $fields,
				'dynamic_filters' => $dynamic_filters,
				'custom_fields'   => $custom_fields,
				'taxonomy'        => $taxonomy,
				'field_functions' => $field_functions,
			]
		);

		// Get exporter
		$exporter = Exporter_Factory::get_exporter( $export_type, $job_id );

		if ( is_wp_error( $exporter ) ) {
			$job_model->update(
				$job_id,
				[
					'status' => 'failed',
					'result' => wp_json_encode( [ 'error' => $exporter->get_error_message() ] ),
				]
			);
			return;
		}

		// Export data
		$export_result = $exporter->export( $export_options );

		if ( is_wp_error( $export_result ) ) {
			$job_model->update(
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

		// Prepare file path
		$filename  = sprintf( 'export-%s-%d.%s', $export_type, $job_id, $format );
		$file_info = Fs::get_export_file_path( $filename );

		if ( is_wp_error( $file_info ) ) {
			$job_model->update(
				$job_id,
				[
					'status' => 'failed',
					'result' => wp_json_encode( [ 'error' => $file_info->get_error_message() ] ),
				]
			);
			return;
		}

		// Map format_options to actual option names used by formatters
		$formatter_options = [];
		if ( 'csv' === $format ) {
			$formatter_options = [
				'delimiter' => $format_options['csv_delimiter'] ?? ',',
				'headers'   => ! empty( $format_options['csv_include_header'] ) ? null : false,
			];
		} elseif ( 'json' === $format ) {
			$formatter_options = [
				'pretty_print' => ! empty( $format_options['json_pretty_print'] ),
			];
		}

		// Generate file with format options
		$formatter = Format_Factory::create( $format );
		$result    = $formatter->generate( $data, $file_info['path'], $formatter_options );

		if ( is_wp_error( $result ) ) {
			$job_model->update(
				$job_id,
				[
					'status' => 'failed',
					'result' => wp_json_encode( [ 'error' => $result->get_error_message() ] ),
				]
			);
			return;
		}

		// Get file size
		$file_size = file_exists( $file_info['path'] ) ? filesize( $file_info['path'] ) : 0;

		// Update job with complete stats
		$job_model->update(
			$job_id,
			[
				'status'          => 'completed',
				'progress'        => 100,
				'total_items'     => $stats['total'] ?? 0,
				'processed_items' => $stats['exported'] ?? 0,
				'success_items'   => $stats['exported'] ?? 0,
				'failed_items'    => $stats['failed'] ?? 0,
				'file_path'       => $file_info['path'],
				'file_size'       => $file_size,
				'result'          => wp_json_encode( $stats ),
				'completed_at'    => current_time( 'mysql' ),
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
		$verification = $this->verify_request( 'export_fields' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$post_type = $this->get_request_param( 'post_type', 'post' );

		// Get all taxonomies registered for this post type
		$taxonomies = get_object_taxonomies( $post_type, 'objects' );

		$taxonomy_list = [];
		foreach ( $taxonomies as $taxonomy ) {
			$taxonomy_list[] = [
				'name'  => $taxonomy->name,
				'label' => $taxonomy->label,
			];
		}

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

		// Map WooCommerce content types to actual post types
		$woo_type_map = [
			'woo_order'  => 'shop_order',
			'woo_coupon' => 'shop_coupon',
		];

		if ( isset( $woo_type_map[ $post_type ] ) ) {
			$post_type = $woo_type_map[ $post_type ];
		}

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

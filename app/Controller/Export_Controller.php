<?php
/**
 * Export Controller
 *
 * Handles export operations via AJAX
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Model\Job;
use RockStarLab\ImportExport\Model\Export\Exporter_Factory;
use RockStarLab\ImportExport\Model\Format\Format_Factory;
use RockStarLab\ImportExport\Model\Queue\Export_Processor;
use RockStarLab\ImportExport\Helper\Fs;

defined( 'ABSPATH' ) || exit;

class Export_Controller extends Base_Controller {

	/**
	 * Check whether an export type is allowed in the current setup.
	 *
	 * Free plugin supports only basic types; PRO addon unlocks more.
	 *
	 * @param string $export_type Export type.
	 * @return bool
	 */
	private function is_export_type_allowed( $export_type ) {
		$export_type = strtolower( trim( (string) $export_type ) );

		$free_types = [ 'post', 'page' ];
		if ( in_array( $export_type, $free_types, true ) ) {
			return true;
		}

		return \RockStarLab\ImportExport\Helper\Pro_Addon::is_pro_active();
	}

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
			'get_all_taxonomies'   => [ 'callback' => 'get_all_taxonomies' ],
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

		if ( ! $this->is_export_type_allowed( $export_type ) ) {
			$this->send_error(
				new \WP_Error(
					'pro_required',
					__( 'This export type requires the PRO addon.', 'import-export-by-rockstarlab' )
				),
				null,
				403
			);
		}

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

		if ( ! $this->is_export_type_allowed( $export_type ) ) {
			$this->send_error(
				new \WP_Error(
					'pro_required',
					__( 'This export type requires the PRO addon.', 'import-export-by-rockstarlab' )
				),
				null,
				403
			);
		}

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

		if ( ! $this->is_export_type_allowed( $export_type ) ) {
			$this->send_error(
				new \WP_Error(
					'pro_required',
					__( 'This export type requires the PRO addon.', 'import-export-by-rockstarlab' )
				),
				null,
				403
			);
		}

		// Get all export parameters
		$filters         = $this->get_request_array( 'filters' );
		$fields          = $this->get_request_array( 'fields' );
		$format_options  = $this->get_request_array( 'format_options' );
		$dynamic_filters = $this->get_request_array( 'dynamic_filters' );
		$custom_fields   = $this->get_request_array( 'custom_fields' );
		$taxonomy        = $this->get_request_array( 'taxonomy' );
		$field_functions = $this->get_request_array( 'field_functions' );
		$table_name      = $this->get_request_param( 'table_name' );

		// Validate format
		if ( ! Format_Factory::is_supported( $format ) ) {
			$this->send_error( __( 'Unsupported export format', 'import-export-by-rockstarlab' ), null, 400 );
		}

		// Create job
		$job_model = rsl_ie()->Model->job;
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
					'table_name'      => $table_name,
				]
			),
		];

		$job_id = $job_model->create( $job_data );
		if ( is_wp_error( $job_id ) ) {
			$this->send_error( $job_id, null, 500 );
		}

		$this->send_success(
			[
				'job_id' => $job_id,
			],
			__( 'Export started successfully', 'import-export-by-rockstarlab' )
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

		$job_model = rsl_ie()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data ) {
			$this->send_error( __( 'Job not found', 'import-export-by-rockstarlab' ), null, 404 );
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

		$job_model = rsl_ie()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data || empty( $job_data->file_path ) ) {
			$this->send_error( __( 'Export file not found', 'import-export-by-rockstarlab' ), null, 404 );
		}

		$file_path = $job_data->file_path;

		if ( ! file_exists( $file_path ) ) {
			$this->send_error( __( 'Export file does not exist', 'import-export-by-rockstarlab' ), null, 404 );
		}

		// Generate download URL with nonce for security
		$parameters = json_decode( $job_data->parameters, true );
		$format     = $parameters['format'] ?? 'csv';
		$filename   = sprintf( 'export-%s.%s', gmdate( 'Y-m-d-His' ), $format );

		// Generate secure download nonce
			$download_nonce = wp_create_nonce( 'rsl_ie_download_' . $job_id );

		$download_url = add_query_arg(
			[
				'action'   => 'rsl_ie_secure_download',
				'job_id'   => $job_id,
				'_wpnonce' => $download_nonce,
			],
			admin_url( 'admin-ajax.php' )
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

		if ( ! wp_verify_nonce( $nonce, 'rsl_ie_download_' . $job_id ) ) {
			wp_die( esc_html__( 'Security check failed', 'import-export-by-rockstarlab' ), 403 );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Insufficient permissions', 'import-export-by-rockstarlab' ), 403 );
		}

		// Get job
		$job_model = rsl_ie()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data || empty( $job_data->file_path ) ) {
			wp_die( esc_html__( 'Export file not found', 'import-export-by-rockstarlab' ), 404 );
		}

		$file_path = $job_data->file_path;

		if ( ! file_exists( $file_path ) ) {
			wp_die( esc_html__( 'Export file does not exist', 'import-export-by-rockstarlab' ), 404 );
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

		$job_model  = rsl_ie()->Model->job;
		$job_result = $job_model->update( $job_id, [ 'status' => 'cancelled' ] );

		if ( is_wp_error( $job_result ) ) {
			$this->send_error( $job_result, null, 500 );
		}

		$this->send_success( null, __( 'Export cancelled', 'import-export-by-rockstarlab' ) );
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

		// Load job to verify the premium license before doing any processing.
		$job_model = rsl_ie()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data ) {
			$this->send_error( __( 'Job not found', 'import-export-by-rockstarlab' ), null, 404 );
		}

		$license_check = $this->verify_premium_for_type_in_context( $job_data->data_type ?? '', 'export' );
		if ( is_wp_error( $license_check ) ) {
			$this->send_error( $license_check, null, 403 );
		}

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
		$job_model = rsl_ie()->Model->job;
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
				'filters'         => $filters,
				'fields'          => $fields,
				'dynamic_filters' => $dynamic_filters,
				'custom_fields'   => $custom_fields,
				'taxonomy'        => $taxonomy,
				'field_functions' => $field_functions,
			]
		);

		// Add post_type only for actual post types (not for menu, user, taxonomy, etc.)
		$non_post_types = [ 'menu', 'user', 'taxonomy', 'comment', 'database_table', 'woo_attribute' ];
		if ( ! in_array( $export_type, $non_post_types, true ) ) {
			$export_options['post_type'] = $export_type;
		}

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

		if ( ! \RockStarLab\ImportExport\Helper\Pro_Addon::is_pro_active() ) {
			$this->send_error(
				new \WP_Error(
					'pro_required',
					__( 'This feature requires the PRO addon.', 'import-export-by-rockstarlab' )
				),
				null,
				403
			);
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
		$verification = $this->verify_request( 'export_count' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		if ( ! \RockStarLab\ImportExport\Helper\Pro_Addon::is_pro_active() ) {
			$this->send_error(
				new \WP_Error(
					'pro_required',
					__( 'This feature requires the PRO addon.', 'import-export-by-rockstarlab' )
				),
				null,
				403
			);
		}

		$validation = $this->validate_required_params( [ 'table_name' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$table_name = $this->get_request_param( 'table_name' );

		// Use Database_Table_Exporter to get columns
		$exporter = new \RockStarLab\ImportExport\Model\Export\Database_Table_Exporter();
		$columns  = $exporter->get_table_columns( $table_name );

		if ( empty( $columns ) ) {
			$this->send_error( __( 'Could not retrieve table columns', 'import-export-by-rockstarlab' ), null, 400 );
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
	 * Get all registered taxonomies
	 */
	public function get_all_taxonomies() {
		$verification = $this->verify_request( 'export_fields' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		// Get all registered taxonomies
		$taxonomies = get_taxonomies(
			[
				'public' => true,
			],
			'objects'
		);

		$taxonomy_list = [];
		foreach ( $taxonomies as $taxonomy ) {
			// Skip post_format as it's not a real taxonomy for export
			if ( 'post_format' === $taxonomy->name ) {
				continue;
			}

			$taxonomy_list[] = [
				'name'  => $taxonomy->name,
				'label' => $taxonomy->label,
			];
		}

		$this->send_success( $taxonomy_list );
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
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery -- \\_% is a valid SQL LIKE escape pattern, not a printf-style placeholder.
		$meta_keys = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
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
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery

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

		$post_type = $this->get_request_param( 'post_type', '' );
		$taxonomy  = $this->get_request_param( 'taxonomy', '' );

		// Determine the location rule based on what was provided
		$fields = [];

		// If taxonomy is provided, use taxonomy location
		if ( ! empty( $taxonomy ) ) {
			// Get all field groups and manually filter by location rules
			$all_groups = acf_get_field_groups();

			foreach ( $all_groups as $group ) {
				if ( empty( $group['location'] ) ) {
					continue;
				}

				$group_matches = false;

				// Check location rules (OR groups of AND rules)
				foreach ( $group['location'] as $or_rules ) {
					foreach ( $or_rules as $rule ) {
						if (
							$rule['param'] === 'taxonomy' &&
							$rule['operator'] === '==' &&
							$rule['value'] === $taxonomy
						) {
							$group_matches = true;
							break 2;
						}
					}
				}

				if ( $group_matches ) {
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
			}
		} elseif ( $post_type === 'user' ) {
			// Get all field groups and manually filter by user location rules
			$all_groups = acf_get_field_groups();

			foreach ( $all_groups as $group ) {
				if ( empty( $group['location'] ) ) {
					continue;
				}

				$group_matches = false;

				// Check location rules for user forms
				foreach ( $group['location'] as $or_rules ) {
					foreach ( $or_rules as $rule ) {
						if (
							$rule['param'] === 'user_form' &&
							$rule['operator'] === '=='
						) {
							$group_matches = true;
							break 2;
						}
					}
				}

				if ( $group_matches ) {
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
			}
		} elseif ( ! empty( $post_type ) ) {
			// Map content types to actual post types
			$type_map = [
				'woo_order'  => 'shop_order',
				'woo_coupon' => 'shop_coupon',
				'media'      => 'attachment',
			];

			if ( isset( $type_map[ $post_type ] ) ) {
				$post_type = $type_map[ $post_type ];
			}

			// Get all field groups and manually filter by post type location rules
			$all_groups = acf_get_field_groups();

			foreach ( $all_groups as $group ) {
				if ( empty( $group['location'] ) ) {
					continue;
				}

				$group_matches = false;

				// Check location rules for post types
				foreach ( $group['location'] as $or_rules ) {
					foreach ( $or_rules as $rule ) {
						if (
							$rule['param'] === 'post_type' &&
							$rule['operator'] === '==' &&
							$rule['value'] === $post_type
						) {
							$group_matches = true;
							break 2;
						}
					}
				}

				if ( $group_matches ) {
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

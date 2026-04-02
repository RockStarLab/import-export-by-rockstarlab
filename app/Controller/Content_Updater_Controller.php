<?php
/**
 * Content Updater Controller
 *
 * Handles content update operations via AJAX
 *
 * @package WP_AIE\Controller
 */

namespace WP_AIE\Controller;

use WP_AIE\Model\Job;
use WP_AIE\Model\Export\Exporter_Factory;
use WP_AIE\Model\Queue\Update_Processor;

defined( 'ABSPATH' ) || exit;

class Content_Updater_Controller extends Base_Controller {

	/**
	 * Get AJAX actions
	 *
	 * @return array
	 */
	protected function get_ajax_actions() {
		return [
			'updater_get_count'     => [ 'callback' => 'get_count' ],
			'updater_get_preview'   => [ 'callback' => 'get_preview' ],
			'updater_start'         => [ 'callback' => 'start_update' ],
			'updater_process_batch' => [ 'callback' => 'process_batch' ],
			'updater_get_progress'  => [ 'callback' => 'get_progress' ],
			'updater_cancel'        => [ 'callback' => 'cancel_update' ],
		];
	}

	/**
	 * Get count of items available for update
	 */
	public function get_count() {
		$verification = $this->verify_request( 'updater_count' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'content_type' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$content_type = $this->get_request_param( 'content_type' );
		$options      = $this->get_request_array( 'options' );

		// Get filters
		$filters_json = $this->get_request_param( 'filters', '[]' );
		$filters      = json_decode( $filters_json, true );
		if ( ! is_array( $filters ) ) {
			$filters = [];
		}

		// Add filters to options
		if ( ! empty( $filters ) ) {
			$options['filters'] = $filters;
		}

		// Get taxonomy filters
		$taxonomy_json = $this->get_request_param( 'taxonomy', '[]' );
		$taxonomy      = json_decode( $taxonomy_json, true );
		if ( ! is_array( $taxonomy ) ) {
			$taxonomy = [];
		}

		// Add taxonomy filters to options
		if ( ! empty( $taxonomy ) ) {
			$options['taxonomy'] = $taxonomy;
		}

		// Map content_type to appropriate exporter type and options
		$exporter_type = $this->map_content_type_to_exporter( $content_type, $options );

		// Use Exporter_Factory to get count (same logic as export)
		$count = Exporter_Factory::get_count( $exporter_type, $options );

		if ( is_wp_error( $count ) ) {
			$this->send_error( $count, null, 400 );
		}

		$this->send_success( [ 'count' => $count ] );
	}

	/**
	 * Get preview of items to update
	 */
	public function get_preview() {
		$verification = $this->verify_request( 'updater_preview' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'content_type' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$content_type = $this->get_request_param( 'content_type' );
		$options      = $this->get_request_array( 'options' );
		$fields       = $this->get_request_array( 'fields' );

		// Get preview data (limited to 5 items)
		$preview_options           = $options;
		$preview_options['limit']  = 5;
		$preview_options['offset'] = 0;

		// Map content_type to appropriate exporter type and options
		$exporter_type = $this->map_content_type_to_exporter( $content_type, $preview_options );

		// Use Exporter_Factory to get preview data
		$exporter = Exporter_Factory::get_exporter( $exporter_type );

		if ( is_wp_error( $exporter ) ) {
			$this->send_error( $exporter, null, 400 );
		}

		$data = $exporter->get_data( $preview_options, $fields );

		if ( is_wp_error( $data ) ) {
			$this->send_error( $data, null, 400 );
		}

		$this->send_success(
			[
				'preview' => $data,
				'count'   => count( $data ),
			]
		);
	}

	/**
	 * Start content update process
	 */
	public function start_update() {
		$verification = $this->verify_request( 'updater_start' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'content_type', 'fields', 'field_functions' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$content_type = $this->get_request_param( 'content_type' );

		// Decode JSON strings from frontend
		$fields_json = $this->get_request_param( 'fields' );
		$fields      = json_decode( $fields_json, true );
		if ( ! is_array( $fields ) ) {
			$fields = [];
		}

		$field_functions_json = $this->get_request_param( 'field_functions' );
		$field_functions      = json_decode( $field_functions_json, true );
		if ( ! is_array( $field_functions ) ) {
			$field_functions = [];
		}

		$filters_json = $this->get_request_param( 'filters', '[]' );
		$filters      = json_decode( $filters_json, true );
		if ( ! is_array( $filters ) ) {
			$filters = [];
		}

		$options_json = $this->get_request_param( 'options', '{}' );
		$options      = json_decode( $options_json, true );
		if ( ! is_array( $options ) ) {
			$options = [];
		}

		// Add filters to options
		if ( ! empty( $filters ) ) {
			$options['filters'] = $filters;
		}

		// Get taxonomy filters
		$taxonomy_json = $this->get_request_param( 'taxonomy', '[]' );
		$taxonomy      = json_decode( $taxonomy_json, true );
		if ( ! is_array( $taxonomy ) ) {
			$taxonomy = [];
		}

		// Add taxonomy filters to options
		if ( ! empty( $taxonomy ) ) {
			$options['taxonomy'] = $taxonomy;
		}

		// Validate fields and functions are not empty
		if ( empty( $fields ) ) {
			$this->send_error(
				new \WP_Error(
					'no_fields_selected',
					__( 'No fields selected. Please select at least one field to update.', 'amplified-import-export' )
				),
				null,
				400
			);
		}

		if ( empty( $field_functions ) ) {
			$this->send_error(
				new \WP_Error(
					'no_functions_assigned',
					__( 'No functions assigned. Please assign at least one function to a field.', 'amplified-import-export' )
				),
				null,
				400
			);
		}

		// Validate that at least one field has a function assigned
		$has_functions = false;
		foreach ( $field_functions as $function_list ) {
			if ( is_array( $function_list ) && ! empty( $function_list ) ) {
				$has_functions = true;
				break;
			}
		}

		if ( ! $has_functions ) {
			$this->send_error(
				new \WP_Error(
					'no_functions_assigned',
					__( 'Please assign at least one function to a field', 'amplified-import-export' )
				),
				null,
				400
			);
		}

		// Map content_type to appropriate exporter type and options
		$exporter_type = $this->map_content_type_to_exporter( $content_type, $options );

		// Get total count
		$total_count = Exporter_Factory::get_count( $exporter_type, $options );

		if ( is_wp_error( $total_count ) ) {
			$this->send_error( $total_count, null, 400 );
		}

		// Create job
		$job_model = WP_AIE()->Model->job;

		$parameters = [
			'content_type'    => $content_type,
			'exporter_type'   => $exporter_type,
			'fields'          => $fields,
			'field_functions' => $field_functions,
			'options'         => $options,
		];

		$job_id = $job_model->create(
			[
				'user_id'     => get_current_user_id(),
				'type'        => 'update',
				'data_type'   => $content_type,
				'file_format' => 'none',
				'status'      => 'pending',
				'total_items' => $total_count,
				'parameters'  => wp_json_encode( $parameters ),
			]
		);

		if ( is_wp_error( $job_id ) ) {
			$this->send_error( $job_id, null, 500 );
		}

		$this->send_success(
			[
				'job_id'      => $job_id,
				'total_count' => $total_count,
			]
		);
	}

	/**
	 * Process a batch of updates
	 */
	public function process_batch() {
		$verification = $this->verify_request( 'updater_process' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$processor = new Update_Processor();
		$result    = $processor->process( $job_id );

		if ( is_wp_error( $result ) ) {
			$this->send_error( $result, null, 500 );
		}

		$this->send_success( $result );
	}

	/**
	 * Get update progress
	 */
	public function get_progress() {
		$verification = $this->verify_request( 'updater_progress' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = WP_AIE()->Model->job;
		$job       = $job_model->find( $job_id );

		if ( ! $job ) {
			$this->send_error(
				new \WP_Error( 'job_not_found', __( 'Job not found', 'amplified-import-export' ) ),
				null,
				404
			);
		}

		$progress = [
			'status'          => $job->status,
			'total_items'     => (int) $job->total_items,
			'processed_items' => (int) $job->processed_items,
			'updated_items'   => (int) $job->imported_items, // Reuse imported_items field for updated count
			'skipped_items'   => (int) $job->skipped_items,
			'error_items'     => (int) $job->error_items,
			'percentage'      => $job->total_items > 0 ? round( ( $job->processed_items / $job->total_items ) * 100, 2 ) : 0,
		];

		$this->send_success( $progress );
	}

	/**
	 * Cancel update process
	 */
	public function cancel_update() {
		$verification = $this->verify_request( 'updater_cancel' );
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = WP_AIE()->Model->job;
		$updated   = $job_model->update(
			$job_id,
			[
				'status'       => 'cancelled',
				'completed_at' => current_time( 'mysql' ),
			]
		);

		if ( ! $updated ) {
			$this->send_error(
				new \WP_Error( 'update_failed', __( 'Failed to cancel update', 'amplified-import-export' ) ),
				null,
				500
			);
		}

		$this->send_success( [ 'cancelled' => true ] );
	}

	/**
	 * Map content type from UI to exporter type and options
	 *
	 * This method converts the content_type value from the frontend
	 * (like 'post', 'page', 'media') to the appropriate exporter type
	 * and adds necessary options like post_type.
	 *
	 * @param string $content_type Content type from UI
	 * @param array  $options      Options array (by reference)
	 * @return string Exporter type
	 */
	protected function map_content_type_to_exporter( $content_type, &$options ) {
		// Map UI content types to exporter types
		$mapping = [
			'post'              => 'post',
			'page'              => 'page',
			'media'             => 'media',
			'menu'              => 'menu',
			'user'              => 'user',
			'comment'           => 'comment',
			'taxonomy'          => 'taxonomy',
			'custom_post_types' => 'custom_post_types',
			'woo_product'       => 'woo_product',
			'woo_order'         => 'woo_order',
			'woo_coupon'        => 'woo_coupon',
		];

		// Get the exporter type
		$exporter_type = $mapping[ $content_type ] ?? $content_type;

		// For post-based content types, set the post_type in options
		// This ensures the correct post_type filter is applied
		if ( in_array( $content_type, [ 'post', 'page' ], true ) ) {
			// Only set post_type if not already specified in options
			if ( ! isset( $options['post_type'] ) ) {
				$options['post_type'] = $content_type;
			}
		}

		// Map WooCommerce content types to real WordPress post_types
		$woo_post_type_map = [
			'woo_product' => 'product',
			'woo_order'   => 'shop_order',
			'woo_coupon'  => 'shop_coupon',
		];

		if ( isset( $woo_post_type_map[ $content_type ] ) && ! isset( $options['post_type'] ) ) {
			$options['post_type'] = $woo_post_type_map[ $content_type ];
		}

		return $exporter_type;
	}
}

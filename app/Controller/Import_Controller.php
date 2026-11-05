<?php
/**
 * Import Controller
 *
 * Handles import operations via AJAX
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Service\Import_Service;
use RockStarLab\ImportExport\Helper\ACF_Fields;

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
		$permission = $this->verify_request();
		if ( is_wp_error( $permission ) ) {
			$this->send_error( $permission, null, 403 );
			return; }
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- File_Service verifies PHP upload origin and the complete manifest.
		$file   = isset( $_FILES['file'] ) && is_array( $_FILES['file'] ) ? $_FILES['file'] : [];
		$result = ( new \RockStarLab\ImportExport\Service\File_Service() )->upload( $file );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result, null, $result->get_error_data()['status'] ?? 400 );
			return; }
		$result['file_path'] = 'rsl-ie-file:' . $result['file_id'];
		$this->send_success( $result, __( 'File uploaded successfully', 'import-export-by-rockstarlab' ) );
	}

	/**
	 * Validate import data
	 */
	public function validate_data() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
			return;
		}
		$result = ( new Import_Service() )->validate(
			[
				'file_path'   => $this->get_request_param( 'file_path' ),
				'file_id'     => $this->get_request_param( 'file_id', '' ),
				'request_id'  => $this->get_request_param( 'request_id', '' ),
				'import_type' => $this->get_request_param( 'import_type' ),
				'mapping'     => $this->get_request_array( 'mapping' ),
				'options'     => $this->get_request_array( 'options' ),
				'format'      => $this->get_request_param( 'format', 'csv' ),
				'delimiter'   => $this->get_import_delimiter(),
				'encoding'    => (string) $this->get_request_param( 'encoding', 'auto' ),
			]
		);
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			$this->send_error( $result, null, is_array( $data ) ? ( $data['status'] ?? 400 ) : 400 );
			return;
		}
		$this->send_success( $result, __( 'Data validation passed', 'import-export-by-rockstarlab' ) );
	}


	/**
	 * Start import
	 */
	public function start_import() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
			return;
		}
		$result = ( new Import_Service() )->start(
			[
				'file_path'   => $this->get_request_param( 'file_path' ),
				'file_id'     => $this->get_request_param( 'file_id', '' ),
				'request_id'  => $this->get_request_param( 'request_id', '' ),
				'import_type' => $this->get_request_param( 'import_type' ),
				'mapping'     => $this->get_request_array( 'mapping' ),
				'options'     => $this->get_request_array( 'options' ),
				'format'      => $this->get_request_param( 'format', 'csv' ),
				'delimiter'   => $this->get_import_delimiter(),
				'encoding'    => (string) $this->get_request_param( 'encoding', 'auto' ),
			]
		);
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			$this->send_error( $result, null, is_array( $data ) ? ( $data['status'] ?? 400 ) : 400 );
			return;
		}
		$this->send_success( $result, __( 'Import started successfully', 'import-export-by-rockstarlab' ) );
	}


	/**
	 * Preserve the CSV tab separator after the authenticated request check.
	 *
	 * The generic text sanitizer removes tabs. Only this exact byte is restored;
	 * all other values follow the ordinary request reader and service validation.
	 *
	 * @return string
	 */
	protected function get_import_delimiter() {
		$delimiter = $this->get_request_param( 'delimiter', ',' );
		if ( '' === $delimiter && "\t" === filter_input( INPUT_POST, 'delimiter', FILTER_UNSAFE_RAW ) ) {
			return "\t";
		}
		return $delimiter;
	}

	/**
	 * Get import progress
	 */
	public function get_progress() {
		$permission = $this->verify_request();
		if ( is_wp_error( $permission ) ) {
			$this->send_error( $permission, null, 403 );
			return; }
		$result = ( new \RockStarLab\ImportExport\Service\Job_Service() )->progress( (int) $this->get_request_param( 'job_id' ), 'import' );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result, null, $result->get_error_data()['status'] ?? 400 );
			return; }
		$this->send_success( $result );
	}

	/**
	 * Cancel import
	 */
	public function cancel_import() {
		$permission = $this->verify_request();
		if ( is_wp_error( $permission ) ) {
			$this->send_error( $permission, null, 403 );
			return; }
		$service = new \RockStarLab\ImportExport\Service\Job_Service();
		$id      = (int) $this->get_request_param( 'job_id' );
		$job     = $service->record( $id );
		if ( is_wp_error( $job ) || 'import' !== $job->type ) {
			$this->send_error( is_wp_error( $job ) ? $job : 'Invalid job type', null, 400 );
			return; }
		$result = $service->stop( $id, 'cancel' );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result, null, $result->get_error_data()['status'] ?? 400 );
			return; }
		$this->send_success( $result );
	}

	/**
	 * Process import batch
	 */
	public function process_batch() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
			return;
		}
		$result = ( new Import_Service() )->process_batch( (int) $this->get_request_param( 'job_id' ) );
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			$this->send_error( $result, null, is_array( $data ) ? ( $data['status'] ?? 500 ) : 500 );
			return;
		}
		$this->send_success( $result );
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
}

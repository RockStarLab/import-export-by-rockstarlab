<?php
/**
 * Export Controller
 *
 * Handles export operations via AJAX
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Service\Export_Service;
use RockStarLab\ImportExport\Helper\Ajax_Security;
use RockStarLab\ImportExport\Helper\ACF_Fields;

defined( 'ABSPATH' ) || exit;

class Export_Controller extends Base_Controller {

	/**
	 * Translate the authenticated legacy UI envelope to the shared contract.
	 *
	 * @return array
	 */
	private function get_export_request() {
		$request = [
			'request_id'  => $this->get_request_param( 'request_id', '' ),
			'export_type' => $this->get_request_param( 'export_type' ),
			'format'      => $this->get_request_param( 'format', 'csv' ),
			'options'     => $this->get_request_array( 'options' ),
		];
		$arrays  = [
			'filters'         => $this->get_request_array( 'filters' ),
			'dynamic_filters' => $this->get_request_array( 'dynamic_filters' ),
			'fields'          => $this->get_request_array( 'fields' ),
			'format_options'  => $this->get_request_array( 'format_options' ),
			'custom_fields'   => $this->get_request_array( 'custom_fields' ),
			'taxonomy'        => $this->get_request_array( 'taxonomy' ),
			'field_functions' => $this->get_request_array( 'field_functions' ),
		];
		foreach ( $arrays as $key => $value ) {
			if ( $value ) {
				$request[ $key ] = $value;
			}
		}
		$table = $this->get_request_param( 'table_name' );
		if ( null !== $table && '' !== $table ) {
			$request['table_name'] = $table;
		}
		return $request;
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
			'export_get_url_types' => [ 'callback' => 'get_url_types' ],
			'form_entry_forms'     => [ 'callback' => 'form_entry_forms' ],
			'form_entry_fields'    => [ 'callback' => 'form_entry_fields' ],
			'get_post_types'       => [ 'callback' => 'get_post_types' ],
			'get_database_tables'  => [ 'callback' => 'get_database_tables' ],
			'get_table_columns'    => [ 'callback' => 'get_table_columns' ],
			'get_taxonomies'       => [ 'callback' => 'get_taxonomies' ],
			'get_all_taxonomies'   => [ 'callback' => 'get_all_taxonomies' ],
			'get_custom_fields'    => [ 'callback' => 'get_custom_fields' ],
			'get_acf_fields'       => [ 'callback' => 'get_acf_fields' ],
			'get_yoast_fields'     => [ 'callback' => 'get_yoast_fields' ],
			'get_rank_math_fields' => [ 'callback' => 'get_rank_math_fields' ],
			'get_elementor_fields' => [ 'callback' => 'get_elementor_fields' ],
		];
	}

	/**
	 * Get count of items available for export
	 */
	public function get_count() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
			return;
		}
		$result = ( new Export_Service() )->count( $this->get_export_request() );
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			$this->send_error( $result, null, is_array( $data ) ? ( $data['status'] ?? 500 ) : 500 );
			return;
		}
		$this->send_success( $result );
	}

	/**
	 * Get preview of export data
	 */
	public function get_preview() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
			return;
		}
		$result = ( new Export_Service() )->preview( $this->get_export_request() );
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			$this->send_error( $result, null, is_array( $data ) ? ( $data['status'] ?? 500 ) : 500 );
			return;
		}
		$this->send_success( $result );
	}

	/**
	 * Start export
	 */
	public function start_export() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
			return;
		}
		$result = ( new Export_Service() )->start( $this->get_export_request() );
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			$this->send_error( $result, null, is_array( $data ) ? ( $data['status'] ?? 500 ) : 500 );
			return;
		}
		$this->send_success( $result );
	}

	/**
	 * Get export progress
	 */
	public function get_progress() {
		$permission = $this->verify_request();
		if ( is_wp_error( $permission ) ) {
			$this->send_error( $permission, null, 403 );
			return; }
		$result = ( new \RockStarLab\ImportExport\Service\Job_Service() )->progress( (int) $this->get_request_param( 'job_id' ), 'export' );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result, null, $result->get_error_data()['status'] ?? 400 );
			return; }
		$this->send_success( $result );
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
		$permission = $this->verify_request();
		if ( is_wp_error( $permission ) ) {
			$this->send_error( $permission, null, 403 );
			return; }
		$job_id  = (int) $this->get_request_param( 'job_id' );
		$zip     = (bool) $this->get_request_param( 'download_zip', false );
		$service = new \RockStarLab\ImportExport\Service\File_Service();
		$file    = $service->from_job( $job_id );
		if ( is_wp_error( $file ) ) {
			$this->send_error( $file, null, $file->get_error_data()['status'] ?? 400 );
			return; }
		$download = $service->download( $file['file_id'], $zip );
		if ( is_wp_error( $download ) ) {
			$this->send_error( $download, null, $download->get_error_data()['status'] ?? 400 );
			return; }
		fclose( $download['stream'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- This endpoint returns metadata; secure_download streams the file.
		$url = add_query_arg(
			[
				'action'       => 'rsl_ie_secure_download',
				'job_id'       => $job_id,
				'_wpnonce'     => wp_create_nonce( 'rsl_ie_download_' . $job_id ),
				'nonce'        => Ajax_Security::create_nonce( 'rsl_ie_secure_download' ),
				'download_zip' => $zip ? 1 : 0,
			],
			admin_url( 'admin-ajax.php' )
		);
		$this->send_success(
			[
				'file_id'      => $file['file_id'],
				'download_url' => $url,
				'filename'     => $download['filename'],
				'file_size'    => $download['file_size'],
			]
		);
	}

	/** Stream a legacy cookie-authenticated download through the same file service. */
	public function secure_download() {
		// phpcs:ignore WordPress.Security.NonceVerification -- Nonce includes the Job ID and is verified immediately below.
		$job_id = isset( $_GET['job_id'] ) ? absint( $_GET['job_id'] ) : 0;
		// phpcs:ignore WordPress.Security.NonceVerification -- Nonce validation follows immediately.
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'rsl_ie_download_' . $job_id ) ) {
			wp_die( esc_html__( 'Security check failed', 'import-export-by-rockstarlab' ), '', [ 'response' => 403 ] ); }
		// phpcs:ignore WordPress.Security.NonceVerification -- Authorized by the job-specific nonce above.
		$zip     = isset( $_GET['download_zip'] ) && '1' === $_GET['download_zip'];
		$service = new \RockStarLab\ImportExport\Service\File_Service();
		$file    = $service->from_job( $job_id );
		$result  = is_wp_error( $file ) ? $file : $service->download( $file['file_id'], $zip );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', [ 'response' => $result->get_error_data()['status'] ?? 400 ] ); }
		$response = new File_Download_Response( $result );
		foreach ( $response->get_headers() as $name => $value ) {
			header( $name . ': ' . $value ); }
		$response->serve();
		exit;
	}

	/**
	 * Cancel export
	 */
	public function cancel_export() {
		$permission = $this->verify_request();
		if ( is_wp_error( $permission ) ) {
			$this->send_error( $permission, null, 403 );
			return; }
		$service = new \RockStarLab\ImportExport\Service\Job_Service();
		$id      = (int) $this->get_request_param( 'job_id' );
		$job     = $service->record( $id );
		if ( is_wp_error( $job ) || 'export' !== $job->type ) {
			$this->send_error( is_wp_error( $job ) ? $job : 'Invalid job type', null, 400 );
			return; }
		$result = $service->stop( $id, 'cancel' );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result, null, $result->get_error_data()['status'] ?? 400 );
			return; }
		$this->send_success( $result );
	}

	/**
	 * Process export batch (called via AJAX for async processing)
	 */
	public function process_export_batch() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
			return;
		}
		$result = ( new Export_Service() )->process_batch( (int) $this->get_request_param( 'job_id' ) );
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			$this->send_error( $result, null, is_array( $data ) ? ( $data['status'] ?? 500 ) : 500 );
			return;
		}
		$this->send_success( $result );
	}

	/**
	 * Get all registered post types
	 */
	public function form_entry_forms() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
			return;
		}
		$this->send_success( ( new \RockStarLab\ImportExport\Service\Form_Entries_Service() )->execute( 'form-entry-forms' ) );
	}

	/** Columns of one form (Form Entries export). */
	public function form_entry_fields() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
			return;
		}
		$result = ( new \RockStarLab\ImportExport\Service\Form_Entries_Service() )->execute(
			'form-entry-fields',
			[
				'form_source' => sanitize_key( (string) $this->get_request_param( 'form_source', '' ) ),
				'form_id'     => absint( $this->get_request_param( 'form_id', 0 ) ),
			]
		);
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result, null, (int) ( $result->get_error_data()['status'] ?? 400 ) );
			return;
		}
		$this->send_success( $result );
	}

	/**
	 * URL content types for the URLs export.
	 */
	public function get_url_types() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$exporter_class = \RockStarLab\ImportExport\Model\Export\Urls_Exporter::class;
		$post_types     = $exporter_class::get_exportable_post_types();
		$taxonomies     = $exporter_class::get_exportable_taxonomies();
		$result         = [
			[
				'kind'        => 'standard',
				'value'       => 'standard:homepage',
				'name'        => 'homepage',
				'slug'        => \RockStarLab\ImportExport\Helper\Site_URL::current_request_site_url() . '/',
				'label'       => __( 'Homepage / Front page', 'import-export-by-rockstarlab' ),
				'description' => __( 'The public site home URL.', 'import-export-by-rockstarlab' ),
				'count'       => $exporter_class::count_for_generated_source( 'standard', 'homepage' ),
			],
			[
				'kind'        => 'standard',
				'value'       => 'standard:authors',
				'name'        => 'authors',
				'slug'        => 'author',
				'label'       => __( 'Author archives', 'import-export-by-rockstarlab' ),
				'description' => __( 'Author archive URLs for users with published content.', 'import-export-by-rockstarlab' ),
				'count'       => $exporter_class::count_for_generated_source( 'standard', 'authors' ),
			],
			[
				'kind'        => 'standard',
				'value'       => 'standard:date_archives',
				'name'        => 'date_archives',
				'slug'        => 'year/month',
				'label'       => __( 'Date archives', 'import-export-by-rockstarlab' ),
				'description' => __( 'Yearly and monthly archive URLs based on published content.', 'import-export-by-rockstarlab' ),
				'count'       => $exporter_class::count_for_generated_source( 'standard', 'date_archives' ),
			],
			[
				'kind'        => 'standard',
				'value'       => 'standard:search_results',
				'name'        => 'search_results',
				'slug'        => '?s=',
				'label'       => __( 'Search results', 'import-export-by-rockstarlab' ),
				'description' => __( 'The WordPress search results URL template.', 'import-export-by-rockstarlab' ),
				'count'       => $exporter_class::count_for_generated_source( 'standard', 'search_results' ),
			],
			[
				'kind'        => 'feed',
				'value'       => 'feed:main',
				'name'        => 'main',
				'slug'        => 'feed',
				'label'       => __( 'Main RSS feed', 'import-export-by-rockstarlab' ),
				'description' => __( 'The main site RSS feed URL.', 'import-export-by-rockstarlab' ),
				'count'       => $exporter_class::count_for_generated_source( 'feed', 'main' ),
			],
			[
				'kind'        => 'feed',
				'value'       => 'feed:atom',
				'name'        => 'atom',
				'slug'        => 'feed/atom',
				'label'       => __( 'Atom feed', 'import-export-by-rockstarlab' ),
				'description' => __( 'The main site Atom feed URL.', 'import-export-by-rockstarlab' ),
				'count'       => $exporter_class::count_for_generated_source( 'feed', 'atom' ),
			],
			[
				'kind'        => 'feed',
				'value'       => 'feed:comments',
				'name'        => 'comments',
				'slug'        => 'comments/feed',
				'label'       => __( 'Comments feed', 'import-export-by-rockstarlab' ),
				'description' => __( 'The global comments feed URL.', 'import-export-by-rockstarlab' ),
				'count'       => $exporter_class::count_for_generated_source( 'feed', 'comments' ),
			],
			[
				'kind'        => 'rest',
				'value'       => 'rest:root',
				'name'        => 'root',
				'slug'        => 'wp-json',
				'label'       => __( 'REST API root', 'import-export-by-rockstarlab' ),
				'description' => __( 'The WordPress REST API root URL.', 'import-export-by-rockstarlab' ),
				'count'       => $exporter_class::count_for_generated_source( 'rest', 'root' ),
			],
		];

		foreach ( $post_types as $type_name => $post_type ) {
			$count = $exporter_class::count_for_post_type( $type_name );
			$slug  = $type_name;
			if ( is_array( $post_type->rewrite ) && ! empty( $post_type->rewrite['slug'] ) ) {
				$slug = $post_type->rewrite['slug'];
			}

			$result[] = [
				'kind'        => 'post_type',
				'value'       => 'post_type:' . $type_name,
				'name'        => $type_name,
				'slug'        => $slug,
				'label'       => $post_type->label,
				'description' => $post_type->description,
				'count'       => $count,
			];

			$archive_count = $exporter_class::count_for_generated_source( 'post_type_archive', $type_name );
			if ( $archive_count > 0 ) {
				$result[] = [
					'kind'        => 'post_type_archive',
					'value'       => 'post_type_archive:' . $type_name,
					'name'        => $type_name . '_archive',
					'slug'        => $slug,
					'label'       => sprintf(
						/* translators: %s: post type label. */
						__( '%s archive', 'import-export-by-rockstarlab' ),
						$post_type->label
					),
					'description' => __( 'Public archive URL for this post type.', 'import-export-by-rockstarlab' ),
					'objectTypes' => [ $type_name ],
					'count'       => $archive_count,
				];
			}

			$feed_count = $exporter_class::count_for_generated_source( 'post_type_feed', $type_name );
			if ( $feed_count > 0 ) {
				$result[] = [
					'kind'        => 'post_type_feed',
					'value'       => 'post_type_feed:' . $type_name,
					'name'        => $type_name . '_feed',
					'slug'        => $slug . '/feed',
					'label'       => sprintf(
						/* translators: %s: post type label. */
						__( '%s feed', 'import-export-by-rockstarlab' ),
						$post_type->label
					),
					'description' => __( 'RSS feed for this post type archive.', 'import-export-by-rockstarlab' ),
					'objectTypes' => [ $type_name ],
					'count'       => $feed_count,
				];
			}

			$rest_count = $exporter_class::count_for_generated_source( 'rest_post_type', $type_name );
			if ( $rest_count > 0 ) {
				$rest_base = $post_type->rest_base ? $post_type->rest_base : $type_name;
				$result[]  = [
					'kind'        => 'rest_post_type',
					'value'       => 'rest_post_type:' . $type_name,
					'name'        => $type_name . '_rest',
					'slug'        => 'wp/v2/' . $rest_base,
					'label'       => sprintf(
						/* translators: %s: post type label. */
						__( '%s REST endpoint', 'import-export-by-rockstarlab' ),
						$post_type->label
					),
					'description' => __( 'REST API collection endpoint for this post type.', 'import-export-by-rockstarlab' ),
					'objectTypes' => [ $type_name ],
					'count'       => $rest_count,
				];
			}
		}

		foreach ( $taxonomies as $taxonomy_name => $taxonomy ) {
			$count = $exporter_class::count_for_taxonomy( $taxonomy_name );
			$slug  = $taxonomy_name;
			if ( is_array( $taxonomy->rewrite ) && ! empty( $taxonomy->rewrite['slug'] ) ) {
				$slug = $taxonomy->rewrite['slug'];
			}

			$result[] = [
				'kind'        => 'taxonomy',
				'value'       => 'taxonomy:' . $taxonomy_name,
				'name'        => $taxonomy_name,
				'slug'        => $slug,
				'label'       => $taxonomy->label,
				'description' => $taxonomy->description,
				'objectTypes' => array_values( array_map( 'sanitize_key', (array) $taxonomy->object_type ) ),
				'count'       => $count,
			];
		}

		// Only offer sources accepted by the count and export request policy.
		$result = array_values( array_filter( $result, static function ( $source ) {
			return ! is_wp_error( \RockStarLab\ImportExport\Service\Export_Request::url_sources( [ $source['value'] ] ) );
		} ) );
		$this->send_success( [ 'types' => $result ] );
	}

	/**
	 * Get all registered post types
	 */
	public function get_post_types() {
		$verification = $this->verify_request();
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

		$table_name = $this->get_request_param( 'table_name' );

		// Use Database_Table_Exporter to get columns
		$exporter = new \RockStarLab\ImportExport\Model\Export\Database_Table_Exporter();
		$columns  = $exporter->get_table_columns( $table_name );

		global $wpdb;
		$row_count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table_name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Counting rows in a user-selected table for export preview.

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
		$verification = $this->verify_request();
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
		$verification = $this->verify_request();
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
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		global $wpdb;

		$post_type = $this->get_request_param( 'post_type', 'post' );
		$limit     = absint( $this->get_request_param( 'limit', 0 ) );
		if ( $limit <= 0 ) {
			$limit = 5000;
		}
		if ( $limit > 20000 ) {
			$limit = 20000;
		}

		// WooCommerce orders keep their meta in the orders tables (HPOS) or as shop_order posts.
		if ( in_array( $post_type, [ 'woo_order', 'shop_order' ], true ) ) {
			$this->send_success( [ 'fields' => self::order_meta_fields( $limit ) ] );
			return;
		}

		if ( 'user' === $post_type ) {
			$this->send_success( [ 'fields' => self::user_meta_fields( $limit ) ] );
			return;
		}

		// Get unique meta keys for this post type
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery -- \\_% is a valid SQL LIKE escape pattern, not a printf-style placeholder.
		$meta_keys = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			$wpdb->prepare(
				"SELECT DISTINCT pm.meta_key 
				FROM {$wpdb->postmeta} pm
				INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
				WHERE p.post_type = %s
				AND pm.meta_key NOT LIKE '\\_%%'
				AND pm.meta_value <> ''
				ORDER BY pm.meta_key ASC
				LIMIT %d",
				$post_type,
				$limit
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
	 * User meta keys offered as custom field columns of the user export (meta_<key>).
	 *
	 * Columns the user export already has, ACF fields (their own group), roles,
	 * sessions and per-site screen settings are left out.
	 *
	 * @param int $limit Maximum number of keys.
	 * @return array<int,array{name:string,label:string}>
	 */
	public static function user_meta_fields( $limit ) {
		global $wpdb;
		$prefix = $wpdb->get_blog_prefix();
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Distinct keys need a direct query; \\_% is a LIKE escape.
		$keys = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT meta_key FROM {$wpdb->usermeta}
				WHERE meta_key NOT LIKE '\\_%%' AND meta_value <> ''
				ORDER BY meta_key ASC LIMIT %d",
				$limit
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
		$skip = [
			'first_name', 'last_name', 'nickname', 'description', 'locale', 'admin_color', 'rich_editing', 'avatar_url',
			'facebook', 'instagram', 'linkedin', 'myspace', 'pinterest', 'soundcloud', 'tumblr', 'wikipedia', 'twitter', 'youtube',
			'syntax_highlighting', 'comment_shortcuts', 'use_ssl', 'show_admin_bar_front', 'show_welcome_panel', 'dismissed_wp_pointers',
			'session_tokens', 'community-events-location', 'default_password_nag', 'wp_persisted_preferences', 'last_update',
		];
		$acf = [];
		if ( function_exists( 'acf_get_field_groups' ) ) {
			foreach ( (array) acf_get_field_groups( [ 'user_form' => 'all' ] ) as $group ) {
				foreach ( (array) acf_get_fields( $group ) as $acf_field ) {
					if ( ! empty( $acf_field['name'] ) ) {
						$acf[] = (string) $acf_field['name'];
					}
				}
			}
		}
		$fields = [];
		foreach ( (array) $keys as $key ) {
			$key = (string) $key;
			// Admin screen settings, plugin notices, sessions and per-site WooCommerce counters.
			if ( in_array( $key, $skip, true ) || in_array( $key, $acf, true ) || 0 === strpos( $key, $wpdb->base_prefix ) || 0 === strpos( $key, $prefix )
				|| preg_match( '/^(?:closedpostboxes_|metaboxhidden_|meta-box-order_|screen_layout_|manage.*columnshidden|.*_per_page$|elementor_|mcp_adapter_|rsl_ie_|nav_menu_recently_edited$|infinite_scrolling$|wc_last_active$|wc_.*_' . preg_quote( rtrim( $prefix, '_' ), '/' ) . '$)/', $key ) ) {
				continue;
			}
			$fields[] = [
				'name'  => $key,
				'label' => $key,
			];
		}
		return $fields;
	}

	/**
	 * Visible order meta keys (checkout fields and other plugin data), like custom fields of posts.
	 *
	 * @param int $limit Maximum number of keys.
	 * @return array<int,array{name:string,label:string}>
	 */
	public static function order_meta_fields( $limit ) {
		global $wpdb;
		$hpos = class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' ) && \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled();
		// phpcs:disable WordPress.DB.PreparedSQLPlaceholders.LikeWildcardsInQuery,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Distinct keys need a direct query; \\_% is a LIKE escape.
		if ( $hpos ) {
			$keys = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT m.meta_key FROM %i m INNER JOIN %i o ON m.order_id = o.id
					WHERE o.type = 'shop_order' AND m.meta_key NOT LIKE '\\_%%' AND m.meta_value <> ''
					ORDER BY m.meta_key ASC LIMIT %d",
					$wpdb->prefix . 'wc_orders_meta',
					$wpdb->prefix . 'wc_orders',
					$limit
				)
			);
		} else {
			$keys = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT DISTINCT pm.meta_key FROM {$wpdb->postmeta} pm INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
					WHERE p.post_type = 'shop_order' AND pm.meta_key NOT LIKE '\\_%%' AND pm.meta_value <> ''
					ORDER BY pm.meta_key ASC LIMIT %d",
					$limit
				)
			);
		}
		// phpcs:enable
		return array_map(
			static function ( $key ) {
				return [
					'name'  => (string) $key,
					'label' => (string) $key,
				];
			},
			is_array( $keys ) ? $keys : []
		);
	}

	/**
	 * Get ACF fields for a post type
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
	 * Get Yoast SEO fields
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
	 * Get Rank Math SEO fields
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
	 * Get Elementor fields
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
}

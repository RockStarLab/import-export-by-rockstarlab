<?php
/**
 * Site Migration Controller
 *
 * Orchestrates full-site migration jobs while reusing existing import/export/sync jobs.
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Helper\Fs;
use RockStarLab\ImportExport\Model\Connected_Site;
use RockStarLab\ImportExport\Model\Export\Exporter_Factory;
use RockStarLab\ImportExport\Model\Format\Format_Factory;
use RockStarLab\ImportExport\Model\Import\Importer_Factory;

defined( 'ABSPATH' ) || exit;

class Site_Migration_Controller extends Base_Controller {

	protected function get_ajax_actions() {
		return array(
			'migration_get_plan'           => array( 'callback' => 'get_plan' ),
			'migration_start'              => array( 'callback' => 'start_migration' ),
			'migration_update'             => array( 'callback' => 'update_migration' ),
			'migration_package_exports'    => array( 'callback' => 'package_exports' ),
			'migration_upload_package'     => array( 'callback' => 'upload_package' ),
			'migration_finalize_import'    => array( 'callback' => 'finalize_import' ),
			'migration_get_file_headers'   => array( 'callback' => 'get_file_headers' ),
			'migration_get_local_post_ids' => array( 'callback' => 'get_local_post_ids' ),
			'migration_get_download_url'   => array( 'callback' => 'get_download_url' ),
			'migration_secure_download'    => array( 'callback' => 'secure_download' ),
		);
	}

	public function get_plan() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$steps = $this->get_available_steps();
		$sites = Connected_Site::get_all();

		$this->send_success(
			array(
				'steps'          => $steps,
				'connectedSites' => array_map(
					static function ( $site ) {
						return array(
							'id'       => (int) ( $site['id'] ?? 0 ),
							'name'     => $site['name'] ?? '',
							'url'      => $site['remote_url'] ?? '',
							'status'   => $site['status'] ?? '',
							'isActive' => 'active' === ( $site['status'] ?? '' ),
						);
					},
					is_array( $sites ) ? $sites : array()
				),
				'isProActive'    => \RockStarLab\ImportExport\Helper\Pro_Addon::is_pro_active(),
				'proOnlyTypes'   => $this->get_pro_only_labels(),
				'proCta'         => \RockStarLab\ImportExport\Helper\Pro_Addon::get_promo_cta(),
				'proDismissed'   => (bool) get_user_meta( get_current_user_id(), 'rsl_ie_dismiss_pro_promo_migration', true ),
			)
		);
	}

	public function start_migration() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$mode      = sanitize_key( (string) $this->get_request_param( 'mode', 'export_file' ) );
		$operation = sanitize_key( (string) $this->get_request_param( 'operation', 'export' ) );
		$direction = sanitize_key( (string) $this->get_request_param( 'direction', '' ) );
		$type      = $this->resolve_job_type( $mode, $operation, $direction );

		$job_id = rsl_ie()->Model->job->create(
			array(
				'job_name'    => __( 'Full site migration', 'import-export-by-rockstarlab' ),
				'type'        => $type,
				'data_type'   => 'site_migration',
				'file_format' => 'zip',
				'status'      => 'processing',
				'user_id'     => $this->get_current_user_id(),
				'parameters'  => wp_json_encode(
					array(
						'mode'      => $mode,
						'operation' => $operation,
						'direction' => $direction,
						'steps'     => $this->sanitize_step_keys( $this->get_request_array( 'steps' ) ),
						'site_id'   => absint( $this->get_request_param( 'site_id', 0 ) ),
					)
				),
				'started_at'  => current_time( 'mysql' ),
			)
		);

		if ( is_wp_error( $job_id ) ) {
			$this->send_error( $job_id, null, 500 );
		}

		$this->send_success( array( 'job_id' => $job_id ) );
	}

	public function update_migration() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$job_id = absint( $this->get_request_param( 'job_id', 0 ) );
		$job    = rsl_ie()->Model->job->find( $job_id );
		if ( ! $job || 'site_migration' !== $job->data_type ) {
			$this->send_error( __( 'Migration job not found.', 'import-export-by-rockstarlab' ), null, 404 );
		}

		$total     = max( 1, absint( $this->get_request_param( 'total', 1 ) ) );
		$processed = min( $total, absint( $this->get_request_param( 'processed', 0 ) ) );
		$status    = sanitize_key( (string) $this->get_request_param( 'status', 'processing' ) );
		$result    = $this->get_request_array( 'result' );
		$data      = array(
			'total_items'     => $total,
			'processed_items' => $processed,
			'success_items'   => $processed,
			'progress'        => (int) round( ( $processed / $total ) * 100 ),
			'status'          => in_array( $status, array( 'processing', 'completed', 'failed', 'cancelled' ), true ) ? $status : 'processing',
			'updated_at'      => current_time( 'mysql' ),
		);

		if ( ! empty( $result ) ) {
			$data['result'] = wp_json_encode( $result );
		}
		if ( in_array( $data['status'], array( 'completed', 'failed', 'cancelled' ), true ) ) {
			$data['completed_at'] = current_time( 'mysql' );
		}

		rsl_ie()->Model->job->update( $job_id, $data );
		$this->send_success(
			array(
				'progress' => $data['progress'],
				'status'   => $data['status'],
			)
		);
	}

	public function package_exports() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		if ( ! class_exists( 'ZipArchive' ) ) {
			$this->send_error( __( 'ZIP archives are not available on this server.', 'import-export-by-rockstarlab' ), null, 500 );
		}

		$job_id = absint( $this->get_request_param( 'job_id', 0 ) );
		$files  = $this->get_request_array( 'files' );
		$job    = rsl_ie()->Model->job->find( $job_id );
		if ( ! $job || empty( $files ) ) {
			$this->send_error( __( 'No migration export files were provided.', 'import-export-by-rockstarlab' ), null, 400 );
		}

		$file_info = Fs::get_export_file_path( 'site-migration-' . gmdate( 'Y-m-d-His' ) . '.zip' );
		if ( is_wp_error( $file_info ) ) {
			$this->send_error( $file_info, null, 500 );
		}

		$zip      = new \ZipArchive();
		$manifest = array(
			'version'    => '1.0',
			'created_at' => current_time( 'mysql' ),
			'steps'      => array(),
		);
		if ( true !== $zip->open( $file_info['path'], \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			$this->send_error( __( 'Could not create migration package.', 'import-export-by-rockstarlab' ), null, 500 );
		}

		$exports_base = realpath( wp_upload_dir()['basedir'] . '/import-export-by-rockstarlab-files' );
		foreach ( $files as $file ) {
			$path      = isset( $file['path'] ) ? (string) $file['path'] : '';
			$real_path = realpath( $path );
			$name      = isset( $file['name'] ) ? sanitize_file_name( (string) $file['name'] ) : '';
			$type      = isset( $file['type'] ) ? sanitize_key( (string) $file['type'] ) : '';
			$taxonomy  = isset( $file['taxonomy'] ) ? sanitize_key( (string) $file['taxonomy'] ) : '';
			$post_type = isset( $file['post_type'] ) ? sanitize_key( (string) $file['post_type'] ) : '';
			if ( false === $exports_base || false === $real_path || 0 !== strpos( $real_path, $exports_base ) || '' === $name || '' === $type || ! is_file( $real_path ) || ! is_readable( $real_path ) ) {
				continue;
			}
			$zip->addFile( $real_path, $name );
			$manifest_step = array(
				'type' => $type,
				'file' => $name,
			);
			if ( '' !== $taxonomy ) {
				$manifest_step['taxonomy'] = $taxonomy;
			}
			if ( '' !== $post_type ) {
				$manifest_step['post_type'] = $post_type;
			}
			$manifest['steps'][] = $manifest_step;
		}

		$zip->addFromString( 'manifest.json', wp_json_encode( $manifest, JSON_PRETTY_PRINT ) );
		$zip->close();

		rsl_ie()->Model->job->update(
			$job_id,
			array(
				'file_path'    => $file_info['path'],
				'file_size'    => file_exists( $file_info['path'] ) ? filesize( $file_info['path'] ) : 0,
				'status'       => 'completed',
				'progress'     => 100,
				'completed_at' => current_time( 'mysql' ),
				'result'       => wp_json_encode(
					array(
						'package' => basename( $file_info['path'] ),
						'steps'   => $manifest['steps'],
					)
				),
			)
		);

		$this->send_success(
			array(
				'job_id'    => $job_id,
				'file_path' => $file_info['path'],
				'filename'  => basename( $file_info['path'] ),
			)
		);
	}

	public function upload_package() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		if ( empty( $_FILES['file'] ) || ! is_array( $_FILES['file'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$this->send_error( __( 'No file uploaded.', 'import-export-by-rockstarlab' ), null, 400 );
		}

		$file = $this->sanitize_file_upload( wp_unslash( $_FILES['file'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		if ( is_wp_error( $file ) ) {
			$this->send_error( $file, null, 400 );
		}
		if ( 'zip' !== strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) ) || ! class_exists( 'ZipArchive' ) ) {
			$this->send_error( __( 'Please upload a migration ZIP package.', 'import-export-by-rockstarlab' ), null, 400 );
		}

		$upload = Fs::upload_file( $file );
		if ( is_wp_error( $upload ) ) {
			$this->send_error( $upload, null, 500 );
		}

		$dir = trailingslashit( Fs::get_upload_dir()['path'] ) . 'migration-' . wp_generate_uuid4();
		wp_mkdir_p( $dir );
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $upload['path'] ) ) {
			$this->send_error( __( 'Could not open migration package.', 'import-export-by-rockstarlab' ), null, 400 );
		}

		$manifest_json = $this->read_zip_entry( $zip, 'manifest.json' );
		if ( is_wp_error( $manifest_json ) ) {
			$zip->close();
			$this->send_error( $manifest_json, null, 400 );
		}

		$manifest = json_decode( (string) $manifest_json, true );
		if ( ! is_array( $manifest ) || empty( $manifest['steps'] ) || ! is_array( $manifest['steps'] ) ) {
			$zip->close();
			$this->send_error( __( 'Migration package manifest is invalid.', 'import-export-by-rockstarlab' ), null, 400 );
		}

		$steps = array();
		foreach ( (array) ( $manifest['steps'] ?? array() ) as $step ) {
			$type      = sanitize_key( (string) ( $step['type'] ?? '' ) );
			$taxonomy  = sanitize_key( (string) ( $step['taxonomy'] ?? '' ) );
			$post_type = sanitize_key( (string) ( $step['post_type'] ?? '' ) );
			$name      = $this->sanitize_package_entry_name( (string) ( $step['file'] ?? '' ) );
			$path      = $dir . '/' . $name;
			$format    = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
			if ( ! $type || ! $name || ! Format_Factory::is_supported( $format ) || ! Importer_Factory::is_supported( $type ) ) {
				continue;
			}

			$copy_result = $this->copy_zip_entry( $zip, $name, $path );
			if ( is_wp_error( $copy_result ) ) {
				$zip->close();
				$this->send_error( $copy_result, null, 400 );
			}

			if ( is_readable( $path ) ) {
				$steps[] = array(
					'type'      => $type,
					'taxonomy'  => $taxonomy,
					'post_type' => $post_type,
					'file_path' => $path,
					'file_name' => $name,
					'format'    => $format,
				);
			}
		}
		$zip->close();

		$this->send_success( array( 'steps' => $steps ) );
	}

	public function finalize_import() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		if ( ! function_exists( 'acf_get_field' ) || ! class_exists( '\RockStarLab\ImportExport\Helper\Content_Sync_Replacer' ) ) {
			$this->send_success( array( 'repaired' => 0 ) );
		}

		$post_ids = get_posts(
			array(
				'post_type'              => 'any',
				'post_status'            => 'any',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'cache_results'          => false,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Migration final repair needs exact source-id lookup.
					'relation' => 'OR',
					array(
						'key'     => '_rsl_ie_source_post_id',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => '_rsl_ie_source_id',
						'compare' => 'EXISTS',
					),
					array(
						'key'     => '_rsl_ie_original_post_id',
						'compare' => 'EXISTS',
					),
				),
			)
		);

		$term_id_map = $this->build_imported_term_id_map();
		$repaired    = 0;
		foreach ( $post_ids as $post_id ) {
			$post_id   = absint( $post_id );
			$source_id = absint( get_post_meta( $post_id, '_rsl_ie_source_post_id', true ) );
			if ( $source_id <= 0 ) {
				$source_id = absint( get_post_meta( $post_id, '_rsl_ie_source_id', true ) );
			}
			if ( $source_id <= 0 ) {
				$source_id = absint( get_post_meta( $post_id, '_rsl_ie_original_post_id', true ) );
			}
			if ( $source_id <= 0 ) {
				continue;
			}

			$raw_meta = get_post_meta( $post_id );
			if ( empty( $raw_meta ) || ! is_array( $raw_meta ) ) {
				continue;
			}

			$before = wp_json_encode( $raw_meta );
			$meta   = array();
			foreach ( $raw_meta as $key => $values ) {
				if ( ! is_array( $values ) || ! array_key_exists( 0, $values ) ) {
					continue;
				}
				$meta[ $key ] = maybe_unserialize( $values[0] );
			}

			\RockStarLab\ImportExport\Helper\Content_Sync_Replacer::translate_acf_post_reference_fields_in_meta(
				$meta,
				$post_id,
				$source_id
			);

			if ( ! empty( $term_id_map ) ) {
				\RockStarLab\ImportExport\Helper\Content_Sync_Replacer::translate_acf_taxonomy_fields_in_meta(
					$meta,
					$post_id,
					$term_id_map
				);
			}

			if ( wp_json_encode( get_post_meta( $post_id ) ) !== $before ) {
				++$repaired;
			}
		}

		$this->send_success( array( 'repaired' => $repaired ) );
	}

	/**
	 * Build a source-site term ID => local term ID map from imported term metadata.
	 *
	 * @return array<int,int>
	 */
	private function build_imported_term_id_map() {
		global $wpdb;

		$keys = array(
			'_aie_source_term_id',
			'_rsl_ie_source_term_id',
			'_rsl_ie_original_term_id',
			'_rsl_ie_source_id',
		);

		$placeholders = implode( ', ', array_fill( 0, count( $keys ), '%s' ) );
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Placeholder list is generated from a fixed local array.
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT term_id, meta_value FROM {$wpdb->termmeta} WHERE meta_key IN ({$placeholders})",
				$keys
			)
		);
		// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( empty( $rows ) ) {
			return array();
		}

		$map = array();
		foreach ( $rows as $row ) {
			$source_id = absint( $row->meta_value ?? 0 );
			$target_id = absint( $row->term_id ?? 0 );
			if ( $source_id > 0 && $target_id > 0 && get_term( $target_id ) ) {
				$map[ $source_id ] = $target_id;
			}
		}

		return $map;
	}

	public function get_download_url() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}
		$job_id = absint( $this->get_request_param( 'job_id', 0 ) );
		$job    = rsl_ie()->Model->job->find( $job_id );
		if ( ! $job || empty( $job->file_path ) || ! is_readable( $job->file_path ) ) {
			$this->send_error( __( 'Migration package not found.', 'import-export-by-rockstarlab' ), null, 404 );
		}

		$this->send_success(
			array(
				'download_url' => add_query_arg(
					array(
						'action'   => 'rsl_ie_migration_secure_download',
						'job_id'   => $job_id,
						'_wpnonce' => wp_create_nonce( 'rsl_ie_migration_download_' . $job_id ),
						'nonce'    => \RockStarLab\ImportExport\Helper\Ajax_Security::create_nonce( 'rsl_ie_migration_secure_download' ),
					),
					admin_url( 'admin-ajax.php' )
				),
				'filename'     => basename( $job->file_path ),
				'file_size'    => filesize( $job->file_path ),
			)
		);
	}

	public function get_file_headers() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$file_path  = (string) $this->get_request_param( 'file_path', '' );
		$format     = strtolower( (string) $this->get_request_param( 'format', 'json' ) );
		$upload_dir = realpath( Fs::get_upload_dir()['path'] );
		$real_path  = realpath( $file_path );
		if ( false === $upload_dir || false === $real_path || 0 !== strpos( $real_path, $upload_dir ) || ! is_readable( $real_path ) ) {
			$this->send_error( __( 'Migration file cannot be read.', 'import-export-by-rockstarlab' ), null, 400 );
		}
		if ( ! Format_Factory::is_supported( $format ) ) {
			$this->send_error( __( 'Unsupported migration file format.', 'import-export-by-rockstarlab' ), null, 400 );
		}

		$parser  = Format_Factory::create( $format );
		$headers = $parser->get_headers( $real_path );
		if ( is_wp_error( $headers ) ) {
			$this->send_error( $headers, null, 400 );
		}

		$this->send_success( array( 'headers' => $headers ) );
	}

	public function get_local_post_ids() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$post_type = sanitize_key( (string) $this->get_request_param( 'post_type', 'post' ) );
		$ids       = get_posts(
			array(
				'post_type'              => $post_type,
				'post_status'            => 'any',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			)
		);

		$this->send_success( array( 'ids' => array_values( array_map( 'absint', $ids ) ) ) );
	}

	public function secure_download() {
		$job_id = isset( $_GET['job_id'] ) ? absint( wp_unslash( $_GET['job_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $nonce, 'rsl_ie_migration_download_' . $job_id ) ) {
			wp_die( esc_html__( 'Security check failed', 'import-export-by-rockstarlab' ), 403 );
		}
		$job = rsl_ie()->Model->job->find( $job_id );
		if ( ! $job || empty( $job->file_path ) || ! is_readable( $job->file_path ) ) {
			wp_die( esc_html__( 'Migration package not found.', 'import-export-by-rockstarlab' ), 404 );
		}
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( basename( $job->file_path ) ) . '"' );
		header( 'Content-Length: ' . filesize( $job->file_path ) );
		readfile( $job->file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	private function resolve_job_type( $mode, $operation, $direction ) {
		if ( 'content_sync' === $mode ) {
			return 'pull' === $direction ? 'migration_sync_pull' : 'migration_sync_push';
		}
		return 'import' === $operation ? 'migration_import' : 'migration_export';
	}

	private function get_available_steps() {
		$order  = array(
			'user'              => '01-users',
			'media'             => '02-media',
			'taxonomy'          => '03-taxonomies',
			'post'              => '04-posts',
			'page'              => '05-pages',
			'custom_post_types' => '06-custom-post-types',
			'woo_attribute'     => '07-woo-attributes',
			'woo_product'       => '08-woo-products',
			'woo_coupon'        => '09-woo-coupons',
			'woo_customer'      => '10-woo-customers',
			'woo_order'         => '11-woo-orders',
			'woo_refund'        => '12-woo-refunds',
			'woo_review'        => '13-woo-reviews',
			'comment'           => '14-comments',
		);
		$labels = array(
			'user'              => __( 'Users', 'import-export-by-rockstarlab' ),
			'media'             => __( 'Media', 'import-export-by-rockstarlab' ),
			'taxonomy'          => __( 'Taxonomy terms', 'import-export-by-rockstarlab' ),
			'post'              => __( 'Posts', 'import-export-by-rockstarlab' ),
			'page'              => __( 'Pages', 'import-export-by-rockstarlab' ),
			'custom_post_types' => __( 'Custom post types', 'import-export-by-rockstarlab' ),
			'woo_attribute'     => __( 'WooCommerce attributes', 'import-export-by-rockstarlab' ),
			'woo_product'       => __( 'WooCommerce products', 'import-export-by-rockstarlab' ),
			'woo_coupon'        => __( 'WooCommerce coupons', 'import-export-by-rockstarlab' ),
			'woo_customer'      => __( 'WooCommerce customers', 'import-export-by-rockstarlab' ),
			'woo_order'         => __( 'WooCommerce orders', 'import-export-by-rockstarlab' ),
			'woo_refund'        => __( 'WooCommerce refunds', 'import-export-by-rockstarlab' ),
			'woo_review'        => __( 'WooCommerce reviews', 'import-export-by-rockstarlab' ),
			'comment'           => __( 'Comments', 'import-export-by-rockstarlab' ),
		);

		$steps = array();
		foreach ( $order as $type => $filename ) {
			if ( ! Exporter_Factory::is_supported( $type ) ) {
				continue;
			}
			$exporter = Exporter_Factory::get_exporter( $type );
			$fields   = is_wp_error( $exporter ) ? array() : $this->normalize_export_fields( $exporter->get_available_fields() );
			$children = array();
			if ( 'taxonomy' === $type ) {
				$children = $this->get_taxonomy_steps( $fields );
			} elseif ( 'custom_post_types' === $type ) {
				$children = $this->get_custom_post_type_steps( $fields );
				if ( empty( $children ) ) {
					continue;
				}
			}

			$steps[] = array(
				'key'        => $type,
				'type'       => $type,
				'label'      => $labels[ $type ] ?? $type,
				'filename'   => $filename . '.json',
				'fields'     => array_values( array_filter( $fields ) ),
				'importable' => Importer_Factory::is_supported( $type ),
				'children'   => $children,
			);
		}

		return $steps;
	}

	private function sanitize_step_keys( $steps ) {
		return array_values( array_filter( array_map( 'sanitize_key', (array) $steps ) ) );
	}

	private function normalize_export_fields( $fields ) {
		$normalized = array();
		foreach ( (array) $fields as $field ) {
			if ( is_string( $field ) ) {
				$normalized[] = $field;
				continue;
			}
			if ( ! is_array( $field ) ) {
				continue;
			}
			foreach ( array( 'name', 'key', 'id', 'value' ) as $key ) {
				if ( ! empty( $field[ $key ] ) && is_string( $field[ $key ] ) ) {
					$normalized[] = $field[ $key ];
					break;
				}
			}
		}

		return array_values( array_unique( array_filter( array_map( 'sanitize_text_field', $normalized ) ) ) );
	}

	private function sanitize_package_entry_name( $name ) {
		$name = str_replace( '\\', '/', $name );
		if ( '' === $name || false !== strpos( $name, '/' ) || false !== strpos( $name, '..' ) ) {
			return '';
		}

		$basename = basename( $name );
		if ( '' === $basename || '.' === $basename[0] ) {
			return '';
		}

		return sanitize_file_name( $basename );
	}

	private function read_zip_entry( $zip, $entry_name ) {
		$stream = $zip->getStream( $entry_name );
		if ( false === $stream ) {
			return new \WP_Error( 'rsl_ie_migration_manifest_missing', __( 'Migration package manifest is missing.', 'import-export-by-rockstarlab' ) );
		}

		$contents = '';
		while ( ! feof( $stream ) ) {
			$chunk = fread( $stream, 1048576 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Stream read from ZipArchive is required here.
			if ( false === $chunk ) {
				fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing stream opened above.
				return new \WP_Error( 'rsl_ie_migration_manifest_read_failed', __( 'Could not read migration package manifest.', 'import-export-by-rockstarlab' ) );
			}
			$contents .= $chunk;
		}
		fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing stream opened above.

		return $contents;
	}

	private function copy_zip_entry( $zip, $entry_name, $target_path ) {
		$entry_name = $this->sanitize_package_entry_name( $entry_name );
		if ( '' === $entry_name ) {
			return new \WP_Error( 'rsl_ie_migration_invalid_entry', __( 'Migration package contains an invalid file name.', 'import-export-by-rockstarlab' ) );
		}

		$stream = $zip->getStream( $entry_name );
		if ( false === $stream ) {
			return new \WP_Error( 'rsl_ie_migration_entry_missing', __( 'Migration package contains a missing export file.', 'import-export-by-rockstarlab' ) );
		}

		$target = fopen( $target_path, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Stream copy from ZipArchive is required here.
		if ( false === $target ) {
			fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing stream opened above.
			return new \WP_Error( 'rsl_ie_migration_entry_write_failed', __( 'Could not write migration package file.', 'import-export-by-rockstarlab' ) );
		}

		while ( ! feof( $stream ) ) {
			$chunk = fread( $stream, 1048576 ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- Stream copy from ZipArchive is required here.
			if ( false === $chunk ) {
				fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing stream opened above.
				fclose( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing stream opened above.
				wp_delete_file( $target_path );
				return new \WP_Error( 'rsl_ie_migration_entry_copy_failed', __( 'Could not extract migration package file.', 'import-export-by-rockstarlab' ) );
			}
			fwrite( $target, $chunk ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Stream copy from ZipArchive is required here.
		}

		fclose( $stream ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing stream opened above.
		fclose( $target ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing stream opened above.

		return true;
	}

	private function get_taxonomy_steps( $fields ) {
		$taxonomies = get_taxonomies(
			array(
				'public' => true,
			),
			'objects'
		);

		$steps = array();
		$index = 3;
		foreach ( $taxonomies as $taxonomy ) {
			if ( 'post_format' === $taxonomy->name ) {
				continue;
			}
			if ( ! $this->taxonomy_has_migratable_content( $taxonomy ) ) {
				continue;
			}

			$steps[] = array(
				'key'        => 'taxonomy:' . $taxonomy->name,
				'type'       => 'taxonomy',
				'taxonomy'   => $taxonomy->name,
				'label'      => sprintf(
					/* translators: %s: taxonomy label. */
					__( 'Taxonomy: %s', 'import-export-by-rockstarlab' ),
					$taxonomy->label
				),
				'filename'   => sprintf( '%02d-taxonomy-%s.json', $index, sanitize_file_name( $taxonomy->name ) ),
				'fields'     => array_values( array_filter( $fields ) ),
				'importable' => Importer_Factory::is_supported( 'taxonomy' ),
			);
			++$index;
		}

		return $steps;
	}

	private function taxonomy_has_migratable_content( $taxonomy ) {
		$object_types = isset( $taxonomy->object_type ) ? (array) $taxonomy->object_type : array();
		if ( empty( $object_types ) ) {
			return false;
		}

		$excluded_object_types = array(
			'acf-field',
			'acf-field-group',
			'e-floating-buttons',
			'elementor_library',
			'nav_menu_item',
			'portfolio',
			'revision',
			'wp_block',
			'wp_global_styles',
			'wp_navigation',
			'wp_template',
			'wp_template_part',
		);

		foreach ( $object_types as $object_type ) {
			if ( in_array( $object_type, $excluded_object_types, true ) ) {
				continue;
			}
			$query = new \WP_Query(
				array(
					'post_type'              => $object_type,
					'post_status'            => 'any',
					'posts_per_page'         => 1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			if ( $query->have_posts() ) {
				return true;
			}
		}

		return false;
	}

	private function get_custom_post_type_steps( $fields ) {
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);

		$excluded = array(
			'post',
			'page',
			'attachment',
			'product',
			'product_variation',
			'shop_order',
			'shop_order_refund',
			'shop_coupon',
			'acf-field',
			'acf-field-group',
			'e-floating-buttons',
			'elementor_library',
			'portfolio',
			'wp_block',
			'wp_template',
			'wp_template_part',
			'wp_global_styles',
			'wp_navigation',
			'revision',
			'nav_menu_item',
		);

		$steps = array();
		$index = 6;
		foreach ( $post_types as $post_type ) {
			if ( in_array( $post_type->name, $excluded, true ) ) {
				continue;
			}

			$steps[] = array(
				'key'        => 'custom_post_types:' . $post_type->name,
				'type'       => 'custom_post_types',
				'post_type'  => $post_type->name,
				'label'      => sprintf(
					/* translators: %s: post type label. */
					__( 'Custom post type: %s', 'import-export-by-rockstarlab' ),
					$post_type->label
				),
				'filename'   => sprintf( '%02d-cpt-%s.json', $index, sanitize_file_name( $post_type->name ) ),
				'fields'     => array_values( array_filter( $fields ) ),
				'importable' => Importer_Factory::is_supported( 'custom_post_types' ),
			);
			++$index;
		}

		return $steps;
	}

	private function get_pro_only_labels() {
		return array( 'Menus', 'Users', 'Taxonomies', 'WooCommerce products', 'WooCommerce orders', 'WooCommerce refunds', 'WooCommerce customers', 'WooCommerce reviews', 'Coupons', 'Custom post types' );
	}
}

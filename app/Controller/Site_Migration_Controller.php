<?php
/**
 * Content Migration Controller
 *
 * Orchestrates content migration jobs while reusing existing import/export jobs.
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Helper\Fs;
use RockStarLab\ImportExport\Model\Export\Exporter_Factory;
use RockStarLab\ImportExport\Model\Format\Format_Factory;
use RockStarLab\ImportExport\Model\Import\Importer_Factory;

defined( 'ABSPATH' ) || exit;

class Site_Migration_Controller extends Base_Controller {

	protected function get_ajax_actions() {
		return array(
			'migration_get_plan'              => array( 'callback' => 'get_plan' ),
			'migration_dismiss_intro_notice'  => array( 'callback' => 'dismiss_intro_notice' ),
			'migration_start'                 => array( 'callback' => 'start_migration' ),
			'migration_get'                   => array( 'callback' => 'get_migration' ),
			'migration_advance'               => array( 'callback' => 'advance_migration' ),
			'migration_update'                => array( 'callback' => 'update_migration' ),
			'migration_package_exports'       => array( 'callback' => 'package_exports' ),
			'migration_upload_package'        => array( 'callback' => 'upload_package' ),
			'migration_prepare_package'       => array( 'callback' => 'prepare_package' ),
			'migration_get_file_headers'      => array( 'callback' => 'get_file_headers' ),
			'migration_get_download_url'      => array( 'callback' => 'get_download_url' ),
			'migration_secure_download'       => array( 'callback' => 'secure_download' ),
		);
	}

	public function dismiss_intro_notice() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->send_error( __( 'You do not have permission to perform this action', 'import-export-by-rockstarlab' ), null, 403 );
		}

		$result = ( new \RockStarLab\ImportExport\Service\Settings_Service() )->execute( 'preferences-update', [ 'migration_intro_dismissed' => true ] );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result, null, 400 );
			return;
		}
		$this->send_success( array( 'dismissed' => true ) );
	}

	public function get_plan() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$steps = $this->get_available_steps();

		$this->send_success(
			array(
				'steps'          => $steps,
				'isProActive'    => \RockStarLab\ImportExport\Helper\Pro_Addon::is_pro_active(),
				'proOnlyTypes'   => $this->get_pro_only_labels(),
				'proCta'         => \RockStarLab\ImportExport\Helper\Pro_Addon::get_promo_cta(),
				'proDismissed'   => (bool) get_user_meta( get_current_user_id(), 'rsl_ie_dismiss_pro_promo_migration', true ),
			)
		);
	}

	/** Read progress computed by the migration worker. */
	public function get_migration() {
		$this->migration_progress( false );
	}

	/** Advance an authorized migration through the same locked background worker. */
	public function advance_migration() {
		$this->migration_progress( true );
	}

	private function migration_progress( $advance ) {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
			return; }
		$input  = [ 'job_id' => absint( $this->get_request_param( 'job_id', 0 ) ) ];
		$result = ( new \RockStarLab\ImportExport\Service\Migration_Service() )->execute( 'migration-get', $input );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result, null, $result->get_error_data()['status'] ?? 400 );
			return; }
		if ( $advance && in_array( $result['status'], [ 'pending', 'processing' ], true ) ) {
			\RockStarLab\ImportExport\Service\Job_Runner::run_scheduled( $input['job_id'] );
			$result = ( new \RockStarLab\ImportExport\Service\Migration_Service() )->execute( 'migration-get', $input );
			if ( is_wp_error( $result ) ) {
				$this->send_error( $result, null, $result->get_error_data()['status'] ?? 400 );
				return;
			}
		}
		if ( ! empty( $result['child_job_id'] ) ) {
			$child = ( new \RockStarLab\ImportExport\Service\Job_Service() )->record( $result['child_job_id'] );
			if ( ! is_wp_error( $child ) && (int) ( json_decode( $child->parameters, true )['migration_parent_id'] ?? 0 ) === $input['job_id'] ) {
				$result['active_file'] = [ 'processed' => (int) $child->processed_items, 'total' => (int) $child->total_items, 'type' => $child->data_type ];
			}
		}
		$this->send_success( $result );
	}

	public function start_migration() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$mode      = sanitize_key( (string) $this->get_request_param( 'mode', 'export_file' ) );
		$operation = sanitize_key( (string) $this->get_request_param( 'operation', 'export' ) );
		if ( 'export_file' === $mode && 'import' === $operation ) {
			$input    = [
				'file_id'             => (string) $this->get_request_param( 'file_id', '' ),
				'sha256'              => (string) $this->get_request_param( 'sha256', '' ),
				'request_id'          => (string) $this->get_request_param( 'request_id', wp_generate_uuid4() ),
				'duplicate_handling'  => 'update' === sanitize_key( (string) $this->get_request_param( 'duplicate_handling', 'skip' ) ) ? 'update' : 'skip',
				'download_media'      => true,
				'replace_links_rules' => $this->get_request_array( 'replace_links_rules' ),
			];
			$selected = $this->get_request_array( 'steps' );
			if ( $selected ) {
				$input['steps'] = $selected;
			}
			$result = ( new \RockStarLab\ImportExport\Service\Migration_Service() )->execute( 'migration-import-start', $input );
			if ( is_wp_error( $result ) ) {
				$this->send_error( $result, null, $result->get_error_data()['status'] ?? 400 );
				return; }
			$this->send_success( $result );
			return;
		}
		if ( 'export_file' === $mode && 'export' === $operation ) {
			$steps  = array_map(
				static function ( $key ) {
					return [ 'key' => $key ];
				},
				$this->get_request_array( 'steps' )
			);
			$result = ( new \RockStarLab\ImportExport\Service\Migration_Service() )->execute(
				'migration-start',
				[
					'operation'  => 'export_file',
					'steps'      => $steps,
					'request_id' => (string) $this->get_request_param( 'request_id', wp_generate_uuid4() ),
				]
			);
			if ( is_wp_error( $result ) ) {
				$this->send_error( $result, null, $result->get_error_data()['status'] ?? 400 );
				return; }
			$this->send_success( $result );
			return;
		}

		$this->send_error( 'Only file migration is supported.', null, 400 );
	}

	public function update_migration() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$job_id = absint( $this->get_request_param( 'job_id', 0 ) );
		$job    = rsl_ie()->Model->job->find( $job_id );
		if ( \RockStarLab\ImportExport\Service\Migration_Service::supports( $job ) || ( $job && ! empty( json_decode( $job->parameters, true )['migration_parent_id'] ) ) ) {
			$this->send_error( 'Migration progress is owned by the server.', null, 409 );
			return; }
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
		if ( \RockStarLab\ImportExport\Service\Migration_Service::supports( $job ) || ( $job && ! empty( json_decode( $job->parameters, true )['migration_parent_id'] ) ) ) {
			$this->send_error( 'Migration progress is owned by the server.', null, 409 );
			return; }
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

		$file_service = new \RockStarLab\ImportExport\Service\File_Service();
		foreach ( $files as $file ) {
			$path      = isset( $file['path'] ) ? (string) $file['path'] : '';
			$reference = $file_service->export_reference( $path );
			if ( is_wp_error( $reference ) ) {
				$zip->close();
				wp_delete_file( $file_info['path'] );
				$this->send_error( $reference, null, $reference->get_error_data()['status'] ?? 400 );
				return;
			}
			$real_path = $reference['path'];
			$name      = isset( $file['name'] ) ? sanitize_file_name( (string) $file['name'] ) : '';
			$type      = isset( $file['type'] ) ? sanitize_key( (string) $file['type'] ) : '';
			$taxonomy  = isset( $file['taxonomy'] ) ? sanitize_key( (string) $file['taxonomy'] ) : '';
			$post_type = isset( $file['post_type'] ) ? sanitize_key( (string) $file['post_type'] ) : '';
			if ( '' === $name || '' === $type ) {
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

		$files  = new \RockStarLab\ImportExport\Service\File_Service();
		$upload = $files->upload( $file );
		if ( is_wp_error( $upload ) ) {
			$this->send_error( $upload, null, $upload->get_error_data()['status'] ?? 400 );
			return;
		}
		$prepared = ( new \RockStarLab\ImportExport\Service\Migration_Package_Service() )->prepare_public( $upload['file_id'] );
		if ( is_wp_error( $prepared ) ) {
			\RockStarLab\ImportExport\Service\File_Store::remove( $upload['file_id'] );
			$this->send_error( $prepared, null, $prepared->get_error_data()['status'] ?? 400 );
			return;
		}
		foreach ( $prepared['steps'] as &$step ) {
			$step['file_path'] = 'rsl-ie-file:' . $step['file_id'];
		}
		unset( $step );
		$this->send_success( $prepared );
	}

	public function prepare_package() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
			return; }
		$id       = sanitize_text_field( $this->get_request_param( 'file_id', '' ) );
		$prepared = ( new \RockStarLab\ImportExport\Service\Migration_Package_Service() )->prepare_public( $id, (int) $this->get_request_param( 'offset', 0 ), (int) $this->get_request_param( 'limit', 100 ) );
		if ( is_wp_error( $prepared ) ) {
			$this->send_error( $prepared, null, $prepared->get_error_data()['status'] ?? 400 );
			return; }
		$this->send_success( $prepared );
	}

	public function get_download_url() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}
		$job_id = absint( $this->get_request_param( 'job_id', 0 ) );
		$job    = rsl_ie()->Model->job->find( $job_id );
		if ( \RockStarLab\ImportExport\Service\Migration_Service::supports( $job ) ) {
			$access = ( new \RockStarLab\ImportExport\Service\Migration_Service() )->authorize( $job );
			if ( is_wp_error( $access ) || 'completed' !== $job->status ) {
				$this->send_error( 'Migration package is unavailable.', null, 403 );
				return; }
		}
		if ( ! $job || empty( $job->file_path ) || ! is_readable( $job->file_path ) ) {
			$this->send_error( __( 'Migration package not found.', 'import-export-by-rockstarlab' ), null, 404 );
		}

		$exported_items = 0;
		$files_count    = 0;
		if ( \RockStarLab\ImportExport\Service\Migration_Service::supports( $job ) ) {
			$state = json_decode( $job->settings, true );
			foreach ( $state['children'] as $child_id ) {
				$child = rsl_ie()->Model->job->find( $child_id );
				if ( $child && $child->processed_items > 0 ) {
					++$files_count;
					$exported_items += (int) $child->success_items; }
			}
		}
		$this->send_success(
			array(
				'files_count'    => $files_count,
				'exported_items' => $exported_items,
				'download_url'   => add_query_arg(
					array(
						'action'   => 'rsl_ie_migration_secure_download',
						'job_id'   => $job_id,
						'_wpnonce' => wp_create_nonce( 'rsl_ie_migration_download_' . $job_id ),
						'nonce'    => \RockStarLab\ImportExport\Helper\Ajax_Security::create_nonce( 'rsl_ie_migration_secure_download' ),
					),
					admin_url( 'admin-ajax.php' )
				),
				'filename'       => basename( $job->file_path ),
				'file_size'      => filesize( $job->file_path ),
			)
		);
	}

	public function get_file_headers() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$files = new \RockStarLab\ImportExport\Service\File_Service();
		$id    = $files->import_reference( (string) $this->get_request_param( 'file_path', '' ) );
		if ( is_wp_error( $id ) ) {
			$this->send_error( $id, null, 400 );
			return;
		}
		$preview = $files->preview( $id );
		if ( is_wp_error( $preview ) ) {
			$this->send_error( $preview, null, $preview->get_error_data()['status'] ?? 400 );
			return;
		}
		$this->send_success( array( 'headers' => $preview['headers'] ) );
	}

	public function secure_download() {
		$job_id = isset( $_GET['job_id'] ) ? absint( wp_unslash( $_GET['job_id'] ) ) : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$nonce  = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! current_user_can( 'manage_options' ) || ! wp_verify_nonce( $nonce, 'rsl_ie_migration_download_' . $job_id ) ) {
			wp_die( esc_html__( 'Security check failed', 'import-export-by-rockstarlab' ), 403 );
		}
		$job = rsl_ie()->Model->job->find( $job_id );
		if ( \RockStarLab\ImportExport\Service\Migration_Service::supports( $job ) ) {
			$access = ( new \RockStarLab\ImportExport\Service\Migration_Service() )->authorize( $job );
			if ( is_wp_error( $access ) || 'completed' !== $job->status ) {
				$this->send_error( 'Migration package is unavailable.', null, 403 );
				return; }
		}
		if ( ! $job || empty( $job->file_path ) || ! is_readable( $job->file_path ) ) {
			wp_die( esc_html__( 'Migration package not found.', 'import-export-by-rockstarlab' ), 404 );
		}
		// Signal the browser only once the authorized package is ready to stream.
		$download_token = isset( $_GET['download_token'] ) ? sanitize_key( wp_unslash( $_GET['download_token'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( $download_token && strlen( $download_token ) <= 64 ) {
			setcookie( 'rsl_ie_migration_download_' . $download_token, '1', array(
				'expires'  => time() + 180,
				'path'     => '/',
				'secure'   => is_ssl(),
				'httponly' => false,
				'samesite' => 'Lax',
			) );
		}
		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( basename( $job->file_path ) ) . '"' );
		header( 'Content-Length: ' . filesize( $job->file_path ) );
		readfile( $job->file_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile
		exit;
	}

	private function get_available_steps() {
		$steps = \RockStarLab\ImportExport\Abilities\Migration_Plan::steps();
		return is_wp_error( $steps ) ? [] : $steps;
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

	private function get_pro_only_labels() {
		return array( 'Menus', 'Users', 'Taxonomies', 'WooCommerce products', 'WooCommerce orders', 'WooCommerce refunds', 'WooCommerce customers', 'WooCommerce reviews', 'Coupons', 'Custom post types' );
	}
}

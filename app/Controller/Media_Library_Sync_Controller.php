<?php
/**
 * Media Library Sync Controller
 *
 * Adds Media Library push/pull sync to the free plugin.
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Helper\Ajax_Security;
use RockStarLab\ImportExport\Helper\Button_Location_Settings;
use RockStarLab\ImportExport\Model\Connected_Site;
use RockStarLab\ImportExport\Service\Media_Library_Sync_Service;
use RockStarLab\ImportExport\Service\Sync_Authentication;

defined( 'ABSPATH' ) || exit;

/** Media Library UI and peer transport adapters. */
class Media_Library_Sync_Controller {

	/**
	 * Shared Content Sync REST namespace.
	 */
	private const REST_NAMESPACE = 'rsl-ie/v1';

	/**
	 * Initialize hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_admin_assets' ) );
		add_action( 'wp_ajax_rsl_ie_pro_sync_get_remote_media', array( $this, 'ajax_get_remote_media' ) );
		add_action( 'wp_ajax_rsl_ie_pro_sync_push_media', array( $this, 'ajax_push_media' ) );
		add_action( 'wp_ajax_rsl_ie_pro_sync_media_step', [ $this, 'ajax_media_step' ] );
		add_action( 'wp_ajax_rsl_ie_pro_sync_pull_media', array( $this, 'ajax_pull_media' ) );

		foreach (
			array(
				'rsl_ie_pro_sync_media_step',
				'rsl_ie_pro_sync_get_remote_media',
				'rsl_ie_pro_sync_push_media',
				'rsl_ie_pro_sync_pull_media',
			) as $action
		) {
			Ajax_Security::register_action( $action );
		}
	}

	/**
	 * Register media sync REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		foreach (
			array(
				'/list-media'    => array( 'callback' => array( $this, 'rest_list_media' ) ),
				'/send-media'    => array( 'callback' => array( $this, 'rest_send_media' ) ),
				'/media-chunk'   => array( 'callback' => array( $this, 'rest_media_chunk' ) ),
				'/receive-media' => array( 'callback' => array( $this, 'rest_receive_media' ) ),
			) as $route => $config
		) {
			register_rest_route(
				self::REST_NAMESPACE,
				$route,
				array(
					'methods'             => 'POST',
					'callback'            => $config['callback'],
					'permission_callback' => array( $this, 'validate_api_key' ),
				)
			);
		}
	}

	/**
	 * Validate API key from request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function validate_api_key( $request ) {
		return Sync_Authentication::permission( $request );
	}

	/**
	 * Load Media Library sync button script.
	 *
	 * @param string $admin_page Current admin page.
	 * @return void
	 */
	public function load_admin_assets( $admin_page ) {
		if ( 'upload.php' !== $admin_page || ! class_exists( Button_Location_Settings::class ) ) {
			return;
		}

		if ( ! Button_Location_Settings::is_sync_enabled( 'admin:media' ) ) {
			return;
		}

		$script_path = plugin_dir_path( RSL_IE_FILE ) . 'assets/js/media-library-sync.js';
		$version     = file_exists( $script_path ) ? filemtime( $script_path ) : RSL_IE_VERSION;

		wp_enqueue_script(
			'rsl-ie-media-library-sync',
			plugins_url( 'assets/js/media-library-sync.js', RSL_IE_FILE ),
			array( 'jquery' ),
			$version,
			array( 'in_footer' => true )
		);

		$style_path = plugin_dir_path( RSL_IE_FILE ) . 'assets/css/app.css';
		if ( file_exists( $style_path ) ) {
			wp_enqueue_style(
				'import-export-by-rockstarlab-styles',
				plugins_url( 'assets/css/app.css', RSL_IE_FILE ),
				array(),
				filemtime( $style_path )
			);
		}

		wp_add_inline_script(
			'rsl-ie-media-library-sync',
			'window.rslIeProMediaLibrarySync = ' . wp_json_encode(
				array(
					'nonces'         => Ajax_Security::get_nonces(),
					'ajaxurl'        => Ajax_Security::ajax_url(),
					'contentSyncUrl' => \RockStarLab\ImportExport\Helper\Site_URL::admin_url( 'admin.php?page=rsl-ie-content-sync' ),
					'connectedSites' => $this->get_connected_sites_for_quick_actions(),
					'i18n'           => array(
						'sync'             => __( 'Sync', 'import-export-by-rockstarlab' ),
						'selectMedia'      => __( 'Select one or more media files.', 'import-export-by-rockstarlab' ),
						'selectSite'       => __( 'Please select a site.', 'import-export-by-rockstarlab' ),
						'syncMedia'        => __( 'Sync Media', 'import-export-by-rockstarlab' ),
						'pushMedia'        => __( 'Push selected media', 'import-export-by-rockstarlab' ),
						'browseMedia'      => __( 'Browse remote media', 'import-export-by-rockstarlab' ),
						'remoteMedia'      => __( 'Remote Media', 'import-export-by-rockstarlab' ),
						'searchMedia'      => __( 'Search media...', 'import-export-by-rockstarlab' ),
						'pullSelected'     => __( 'Pull selected media', 'import-export-by-rockstarlab' ),
						'loading'          => __( 'Loading...', 'import-export-by-rockstarlab' ),
						'noItemsFound'     => __( 'No media found.', 'import-export-by-rockstarlab' ),
						'syncComplete'     => __( 'Sync completed successfully.', 'import-export-by-rockstarlab' ),
						/* translators: %s: number of files. */
						'syncCompleteMissing' => __( 'Sync completed. %s file(s) could not be copied because the file is missing on the source site.', 'import-export-by-rockstarlab' ),
						/* translators: 1: number of copied files, 2: number of files that were already there. */
						'syncCompleteSkipped' => __( 'Sync completed. Copied: %1$s. Skipped, already there: %2$s.', 'import-export-by-rockstarlab' ),
						/* translators: 1: batch number, 2: number of batches. */
						'batchOf'          => __( 'batch %1$s of %2$s', 'import-export-by-rockstarlab' ),
						'syncFailed'       => __( 'Sync failed.', 'import-export-by-rockstarlab' ),
						'close'            => __( 'Close', 'import-export-by-rockstarlab' ),
						'cancel'           => __( 'Cancel', 'import-export-by-rockstarlab' ),
						'selectRemoteItem' => __( 'Please select one or more remote media files.', 'import-export-by-rockstarlab' ),
					),
				)
			) . ';',
			'before'
		);
	}

	/** Advance a bounded group of media checkpoints while the modal is open. */
	public function ajax_media_step() {
		if ( ! $this->verify_ajax( 'rsl_ie_pro_sync_media_step' ) ) {
			return;
		}
		$id = absint( $_POST['job_id'] ?? 0 );
		$service = new \RockStarLab\ImportExport\Service\Sync_Job_Service();
		$status = $service->status( $id );
		if ( is_wp_error( $status ) || ! in_array( $status['operation'] ?? '', [ 'sync-media-push', 'sync-media-pull' ], true ) ) {
			wp_send_json_error( [ 'message' => __( 'Media job is unavailable.', 'import-export-by-rockstarlab' ) ], 403 );
		}
		$started = microtime( true );
		for ( $step = 0; $step < 12 && in_array( $status['status'], [ 'pending', 'processing' ], true ); ++$step ) {
			$result = $service->process_background_batch( $id );
			$status = $service->status( $id );
			if ( is_wp_error( $status ) ) {
				wp_send_json_error( [ 'message' => $status->get_error_message() ], 400 );
			}
			if ( is_wp_error( $result ) || microtime( true ) - $started >= 2 ) {
				break;
			}
		}
		wp_send_json_success( $status );
	}

	/** AJAX browse adapter. */
	public function ajax_get_remote_media() {
		$this->ajax_operation( 'browse', 'rsl_ie_pro_sync_get_remote_media' );
	}
	/** AJAX push adapter. */
	public function ajax_push_media() {
		$this->ajax_operation( 'push', 'rsl_ie_pro_sync_push_media' );
	}
	/** AJAX pull adapter. */
	public function ajax_pull_media() {
		$this->ajax_operation( 'pull', 'rsl_ie_pro_sync_pull_media' );
	}
	/**
	 * Keep the existing nonce and response envelope for the Media Library UI.
	 *
	 * @param string $operation Operation.
	 * @param string $action Registered AJAX action.
	 * @return void
	 */
	private function ajax_operation( $operation, $action ) {
		if ( ! $this->verify_ajax( $action ) ) {
			return; }
		$input  = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- verify_ajax checks the action nonce above; service validates the closed input schema.
		$keys   = 'browse' === $operation ? [ 'site_id', 'search', 'page', 'per_page' ] : [ 'site_id', 'media_ids' ];
		$input  = array_intersect_key( $input, array_flip( $keys ) );
		$result = 'browse' === $operation
			? ( new Media_Library_Sync_Service() )->execute( $operation, $input )
			: ( new \RockStarLab\ImportExport\Service\Sync_Job_Service() )->execute(
				'sync-media-' . $operation . '-start',
				$input + [ 'request_id' => wp_generate_uuid4() ]
			);
		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				[
					'message' => $result->get_error_message(),
					'code'    => $result->get_error_code(),
				],
				$result->get_error_data()['status'] ?? 400
			); }
		if ( 'browse' !== $operation ) {
			$result['message'] = __( 'Media synchronization started.', 'import-export-by-rockstarlab' );
			$result['jobs_url'] = \RockStarLab\ImportExport\Helper\Site_URL::admin_url( 'admin.php?page=rsl-ie-jobs-log' );
		}

		wp_send_json_success( $result );
	}
	/**
	 * Peer browse adapter.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function rest_list_media( $request ) {
		return $this->peer_operation( 'list_media', $request );
	}
	/**
	 * Peer export adapter.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function rest_send_media( $request ) {
		return $this->peer_operation( 'send_media', $request );
	}
	/**
	 * Peer import adapter.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	public function rest_receive_media( $request ) {
		return $this->peer_operation( 'receive_media', $request );
	}
	/** Authenticated staging protocol; no filesystem path is returned. */
	public function rest_media_chunk( $request ) {
		return Sync_Authentication::run(
			$request,
			static function ( $request ) {
				$input = $request->get_json_params();
				if ( ! is_array( $input ) ) {
					return new \WP_Error( 'rsl_ie_invalid_chunk_request', 'Invalid media chunk request.', [ 'status' => 400 ] ); }
				$action                     = $input['action'] ?? '';
				$fields                     = [
					'begin'    => [ 'action', 'file_name', 'size', 'sha256' ],
					'append'   => [ 'action', 'transfer_id', 'offset', 'data' ],
					'status'   => [ 'action', 'transfer_id' ],
					'discard'  => [ 'action', 'transfer_id' ],
					'finalize' => [ 'action', 'transfer_id', 'metadata', 'defer_fields' ],
				];
				$fields['capabilities']     = [ 'action' ];
				$fields['prepare']          = [ 'action', 'input' ];
				$fields['prepare-snapshot'] = [ 'action', 'snapshot_id', 'input' ];
				$fields['snapshot']         = [ 'action', 'attachment_id', 'request_id' ];
				$fields['advance-snapshot'] = [ 'action', 'snapshot_id' ];
				$fields['read-snapshot']    = [ 'action', 'snapshot_id', 'offset' ];
				$fields['discard-snapshot'] = [ 'action', 'snapshot_id' ];
				$fields['source']           = [ 'action', 'attachment_id' ];
				$fields['read']             = [ 'action', 'attachment_id', 'offset', 'size', 'sha256' ];
				if ( 'snapshot' === $action && array_key_exists( 'bounded', $input ) ) {
					if ( ! is_bool( $input['bounded'] ) ) {
						return new \WP_Error( 'rsl_ie_invalid_chunk_request', 'Invalid media chunk request.', [ 'status' => 400 ] ); }
					$fields['snapshot'][] = 'bounded';
				}
				if ( 'begin' === $action && array_key_exists( 'file_hash', $input ) ) {
					if ( ! is_string( $input['file_hash'] ) || ! preg_match( '/^[a-f0-9]{32}$/D', $input['file_hash'] ) ) {
						return new \WP_Error( 'rsl_ie_invalid_chunk_request', 'Invalid file hash.', [ 'status' => 400 ] );
					}
					$fields['begin'][] = 'file_hash';
				}
				if ( 'begin' === $action && array_key_exists( 'data', $input ) ) {
					if ( ! is_string( $input['data'] ) || strlen( $input['data'] ) > 4 * ceil( \RockStarLab\ImportExport\Service\Media_Chunk_Store::CHUNK_BYTES / 3 ) || false === base64_decode( $input['data'], true ) ) {
						return new \WP_Error( 'rsl_ie_invalid_chunk_request', 'Invalid media chunk request.', [ 'status' => 400 ] );
					}
					$fields['begin'][] = 'data';
				}
				if ( 'begin' === $action && array_key_exists( 'request_id', $input ) ) {
					if ( ! is_string( $input['request_id'] ) ) {
						return new \WP_Error( 'rsl_ie_invalid_chunk_request', 'Invalid media chunk request.', [ 'status' => 400 ] ); }
					$fields['begin'][] = 'request_id';
				}
				if ( 'finalize' === $action && array_key_exists( 'bounded_metadata', $input ) ) {
					if ( ! is_bool( $input['bounded_metadata'] ) ) {
						return new \WP_Error( 'rsl_ie_invalid_chunk_request', 'Invalid media chunk request.', [ 'status' => 400 ] );
					}
					$fields['finalize'][] = 'bounded_metadata';
				}
				if ( 'finalize' === $action && array_key_exists( 'envelope', $input ) ) {
					if ( ! is_array( $input['envelope'] ) ) {
						return new \WP_Error( 'rsl_ie_invalid_chunk_request', 'Invalid media chunk request.', [ 'status' => 400 ] ); }
					$fields['finalize'][] = 'envelope';
				}
				if ( ! is_string( $action ) || ! isset( $fields[ $action ] ) || array_diff( array_keys( $input ), $fields[ $action ] ) || array_diff( $fields[ $action ], array_keys( $input ) ) ) {
					return new \WP_Error( 'rsl_ie_invalid_chunk_request', 'Invalid media chunk request.', [ 'status' => 400 ] ); }
				// Sync_Authentication already verified this bearer value. Normalize
				// header whitespace/case so equivalent headers retain one scope.
				preg_match( '/^Bearer\s+(\S+)\s*$/iD', (string) $request->get_header( 'Authorization' ), $matches );
				$scope = 'sync:' . hash( 'sha256', $matches[1] );
				$store = \RockStarLab\ImportExport\Service\Media_Chunk_Store::class;
				if ( 'capabilities' === $action ) {
					if ( ! current_user_can( 'upload_files' ) ) {
						return new \WP_Error( 'rsl_ie_media_forbidden', 'Media upload permission is required.', [ 'status' => 403 ] ); }
					$result = [
						'chunk_bytes'               => $store::CHUNK_BYTES,
						'snapshot_version'          => 1,
						'bounded_snapshot'          => true,
						'snapshot_metadata_binding' => true,
						'idempotent_begin'          => true,
						'idempotent_finalize'       => true,
						'bounded_metadata'          => true,
						'begin_data'                => true,
						'begin_reuse'               => true,
					];
				} elseif ( 'begin' === $action ) {
					$result = $store::begin( $input['file_name'], $input['size'], $input['sha256'], $scope, $input['request_id'] ?? null );
					if ( ! is_wp_error( $result ) && isset( $input['file_hash'] ) && ! $result['ready'] && 0 === $result['offset'] ) {
						$result = $store::reuse_attachment( $result['transfer_id'], $input['file_hash'], $scope );
					}
					if ( ! is_wp_error( $result ) && isset( $input['data'] ) ) {
						$result = $store::append( $result['transfer_id'], 0, base64_decode( $input['data'], true ), $scope );
					}
				} elseif ( 'append' === $action ) {
					if ( ! is_string( $input['data'] ) || strlen( $input['data'] ) > 4 * ceil( $store::CHUNK_BYTES / 3 ) ) {
						return new \WP_Error( 'rsl_ie_invalid_chunk_request', 'Invalid media chunk request.', [ 'status' => 400 ] ); }
					$bytes  = base64_decode( $input['data'], true );
					$result = $store::append( $input['transfer_id'], $input['offset'], $bytes, $scope );
				} elseif ( 'snapshot' === $action ) {
					$result = ( new Media_Library_Sync_Service() )->source_snapshot( $input['attachment_id'], $input['request_id'], $scope, $input['bounded'] ?? false );
				} elseif ( 'advance-snapshot' === $action ) {
					$result = ( new Media_Library_Sync_Service() )->advance_source_snapshot( $input['snapshot_id'], $scope );
				} elseif ( 'read-snapshot' === $action ) {
					$result = $store::read_snapshot( $input['snapshot_id'], $input['offset'], $scope );
				} elseif ( 'discard-snapshot' === $action ) {
					$result = $store::discard( $input['snapshot_id'], 'source:' . $scope );
				} elseif ( 'prepare-snapshot' === $action ) {
					if ( ! is_array( $input['input'] ) ) {
						return new \WP_Error( 'rsl_ie_invalid_chunk_request', 'Invalid media chunk request.', [ 'status' => 400 ] ); }
					$result = ( new Media_Library_Sync_Service() )->prepare_snapshot_media( $input['snapshot_id'], $input['input'], $scope );
				} elseif ( 'prepare' === $action ) {
					if ( ! is_array( $input['input'] ) ) {
						return new \WP_Error( 'rsl_ie_invalid_chunk_request', 'Invalid media chunk request.', [ 'status' => 400 ] ); }
					$result = ( new Media_Library_Sync_Service() )->prepare_chunk_media( $input['input'] );
				} elseif ( 'source' === $action ) {
					$result = ( new Media_Library_Sync_Service() )->source_manifest( $input['attachment_id'] );
				} elseif ( 'read' === $action ) {
					$result = ( new Media_Library_Sync_Service() )->read_source_chunk( $input['attachment_id'], $input['offset'], $input['size'], $input['sha256'] );
				} elseif ( 'finalize' === $action ) {
					if ( ! is_array( $input['metadata'] ) || ! is_bool( $input['defer_fields'] ) ) {
						return new \WP_Error( 'rsl_ie_invalid_chunk_request', 'Invalid media chunk request.', [ 'status' => 400 ] ); }
					$result = ( new Media_Library_Sync_Service() )->receive_staged_file( $input['transfer_id'], $input['metadata'], $scope, $input['defer_fields'], $input['envelope'] ?? [], $input['bounded_metadata'] ?? false );
				} else {
					$result = $store::$action( $input['transfer_id'], $scope );
				}
				return is_wp_error( $result ) ? $result : new \WP_REST_Response(
					[
						'success'                => true,
						'chunk_contract_version' => 1,
						'result'                 => $result,
					],
					200
				);
			}
		);
	}

	/**
	 * Revalidate and restore the bound API-key owner for every peer call.
	 *
	 * @param string           $operation Server-selected operation.
	 * @param \WP_REST_Request $request Request.
	 * @return mixed
	 */
	private function peer_operation( $operation, $request ) {
		return Sync_Authentication::run(
			$request,
			static function ( $request ) use ( $operation ) {
				$input = $request->get_json_params();
				if ( ! is_array( $input ) ) {
					$input = $request->get_body_params(); }
				$result = ( new Media_Library_Sync_Service() )->$operation( $input );
				return is_wp_error( $result ) ? $result : new \WP_REST_Response(
					[
						'success'                => true,
						'media_contract_version' => 1,
					] + $result,
					200
				);
			}
		);
	}
	/**
	 * Read safe site summaries for the existing UI.
	 *
	 * @return array
	 */
	private function get_connected_sites_for_quick_actions() {
		$sites     = Connected_Site::get_all();
		$sites_map = array();

		foreach ( $sites as $site ) {
			$site_id = isset( $site['id'] ) ? (string) $site['id'] : '';
			if ( '' === $site_id ) {
				continue;
			}

			$sites_map[ $site_id ] = array(
				'id'         => $site_id,
				'name'       => isset( $site['name'] ) ? (string) $site['name'] : '',
				'remote_url' => isset( $site['remote_url'] ) ? (string) $site['remote_url'] : '',
			);
		}

		return $sites_map;
	}

	/**
	 * Verify AJAX permission and nonce.
	 *
	 * @param string $action AJAX action.
	 * @return bool
	 */
	private function verify_ajax( $action ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'import-export-by-rockstarlab' ) ), 403 );
			return false;
		}

		if ( ! Ajax_Security::verify_nonce( $action ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'import-export-by-rockstarlab' ) ), 403 );
			return false;
		}

		return true;
	}
}

<?php
/**
 * Media Library Sync Controller
 *
 * Adds Media Library push/pull sync to the free plugin.
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Helper\ACF_Fields;
use RockStarLab\ImportExport\Helper\Ajax_Security;
use RockStarLab\ImportExport\Helper\Button_Location_Settings;
use RockStarLab\ImportExport\Helper\Content_Sync_Media;
use RockStarLab\ImportExport\Helper\Media_Hash;
use RockStarLab\ImportExport\Helper\Remote_API;
use RockStarLab\ImportExport\Model\Connected_Site;

defined( 'ABSPATH' ) || exit;

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
		add_action( 'wp_ajax_rsl_ie_pro_sync_pull_media', array( $this, 'ajax_pull_media' ) );

		foreach (
			array(
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
		$auth_header = $request->get_header( 'Authorization' );
		if ( empty( $auth_header ) || ! preg_match( '/Bearer\s+(.+)/i', $auth_header, $matches ) ) {
			return false;
		}

		$site_key = get_option( 'rsl_ie_site_api_key' );
		return ! empty( $site_key ) && hash_equals( (string) $site_key, trim( $matches[1] ) );
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
					'ajaxurl'        => admin_url( 'admin-ajax.php' ),
					'contentSyncUrl' => admin_url( 'admin.php?page=rsl-ie-content-sync' ),
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

	/**
	 * AJAX: browse remote media.
	 *
	 * @return void
	 */
	public function ajax_get_remote_media() {
		if ( ! $this->verify_ajax( 'rsl_ie_pro_sync_get_remote_media' ) ) {
			return;
		}

		$site = $this->get_connected_site_from_request();
		if ( is_wp_error( $site ) ) {
			wp_send_json_error( array( 'message' => $site->get_error_message() ), 400 );
		}

		$response = $this->remote_post(
			$site,
			'list-media',
			array(
				'search'   => isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '',
				'page'     => isset( $_POST['page'] ) ? max( 1, absint( wp_unslash( $_POST['page'] ) ) ) : 1,
				'per_page' => 60,
			),
			30
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ), 400 );
		}

		wp_send_json_success( $response );
	}

	/**
	 * AJAX: push selected media.
	 *
	 * @return void
	 */
	public function ajax_push_media() {
		if ( ! $this->verify_ajax( 'rsl_ie_pro_sync_push_media' ) ) {
			return;
		}

		$site = $this->get_connected_site_from_request();
		if ( is_wp_error( $site ) ) {
			wp_send_json_error( array( 'message' => $site->get_error_message() ), 400 );
		}

		$media_ids = isset( $_POST['media_ids'] ) && is_array( $_POST['media_ids'] ) ? array_values( array_filter( array_unique( array_map( 'absint', wp_unslash( $_POST['media_ids'] ) ) ) ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via absint map.
		if ( empty( $media_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No media selected.', 'import-export-by-rockstarlab' ) ), 400 );
		}

		$result = array(
			'created' => 0,
			'skipped' => 0,
			'failed'  => 0,
		);

		foreach ( $media_ids as $attachment_id ) {
			$item = $this->prepare_media_payload_for_sync( $attachment_id );
			if ( ! $item ) {
				++$result['failed'];
				continue;
			}

			$response = $this->remote_post( $site, 'receive-media', array( 'media' => array( $item ) ), 180 );
			if ( is_wp_error( $response ) ) {
				++$result['failed'];
				continue;
			}

			$result['created'] += absint( $response['created'] ?? 0 );
			$result['skipped'] += absint( $response['skipped'] ?? 0 );
			$result['failed']  += absint( $response['failed'] ?? 0 );
		}

		wp_send_json_success(
			array_merge(
				array(
					'message' => sprintf(
						/* translators: 1: created attachments, 2: skipped attachments, 3: failed attachments. */
						__( 'Media pushed. Created: %1$d, Skipped: %2$d, Failed: %3$d', 'import-export-by-rockstarlab' ),
						$result['created'],
						$result['skipped'],
						$result['failed']
					),
				),
				$result
			)
		);
	}

	/**
	 * AJAX: pull selected media.
	 *
	 * @return void
	 */
	public function ajax_pull_media() {
		if ( ! $this->verify_ajax( 'rsl_ie_pro_sync_pull_media' ) ) {
			return;
		}

		$site = $this->get_connected_site_from_request();
		if ( is_wp_error( $site ) ) {
			wp_send_json_error( array( 'message' => $site->get_error_message() ), 400 );
		}

		$media_ids = isset( $_POST['media_ids'] ) && is_array( $_POST['media_ids'] ) ? array_values( array_filter( array_unique( array_map( 'absint', wp_unslash( $_POST['media_ids'] ) ) ) ) ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized via absint map.
		if ( empty( $media_ids ) ) {
			wp_send_json_error( array( 'message' => __( 'No remote media selected.', 'import-export-by-rockstarlab' ) ), 400 );
		}

		$response = $this->remote_post( $site, 'send-media', array( 'media_ids' => $media_ids ), 180 );
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => $response->get_error_message() ), 400 );
		}

		$result = $this->import_synced_media_items( isset( $response['media'] ) && is_array( $response['media'] ) ? $response['media'] : array() );
		wp_send_json_success(
			array_merge(
				array(
					'message' => sprintf(
						/* translators: 1: created attachments, 2: skipped attachments. */
						__( 'Media pulled. Created: %1$d, Skipped: %2$d', 'import-export-by-rockstarlab' ),
						$result['created'],
						$result['skipped']
					),
				),
				$result
			)
		);
	}

	/**
	 * REST: list media attachments.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_list_media( $request ) {
		$search   = sanitize_text_field( (string) $request->get_param( 'search' ) );
		$page     = max( 1, absint( $request->get_param( 'page' ) ?: 1 ) );
		$per_page = min( max( 1, absint( $request->get_param( 'per_page' ) ?: 50 ) ), 100 );

		$args = array(
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $per_page,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		$query = new \WP_Query( $args );
		$media = array();
		foreach ( $query->posts as $attachment ) {
			$media[] = $this->prepare_media_summary_for_sync( (int) $attachment->ID );
		}

		return new \WP_REST_Response(
			array(
				'success'      => true,
				'media'        => array_values( array_filter( $media ) ),
				'total'        => (int) $query->found_posts,
				'pages'        => max( 1, (int) $query->max_num_pages ),
				'current_page' => $page,
			),
			200
		);
	}

	/**
	 * REST: send selected media with file data.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_send_media( $request ) {
		$media_ids = $request->get_param( 'media_ids' );
		$media_ids = is_array( $media_ids ) ? array_values( array_filter( array_unique( array_map( 'absint', $media_ids ) ) ) ) : array();

		if ( empty( $media_ids ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'No media selected.', 'import-export-by-rockstarlab' ),
				),
				400
			);
		}

		$media = array();
		foreach ( $media_ids as $attachment_id ) {
			$item = $this->prepare_media_payload_for_sync( $attachment_id );
			if ( $item ) {
				$media[] = $item;
			}
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'media'   => $media,
			),
			200
		);
	}

	/**
	 * REST: receive media.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function rest_receive_media( $request ) {
		$media = $request->get_param( 'media' );
		if ( empty( $media ) || ! is_array( $media ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'No media data provided.', 'import-export-by-rockstarlab' ),
				),
				400
			);
		}

		$result = $this->import_synced_media_items( $media );

		return new \WP_REST_Response(
			array_merge(
				array(
					'success' => true,
					'message' => sprintf(
						/* translators: 1: created attachments, 2: skipped attachments. */
						__( 'Media synced. Created: %1$d, Skipped: %2$d', 'import-export-by-rockstarlab' ),
						$result['created'],
						$result['skipped']
					),
				),
				$result
			),
			200
		);
	}

	/**
	 * Prepare media summary for browse lists.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array|null
	 */
	private function prepare_media_summary_for_sync( $attachment_id ) {
		$attachment = get_post( $attachment_id );
		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return null;
		}

		return array(
			'ID'            => (int) $attachment->ID,
			'title'         => (string) $attachment->post_title,
			'url'           => (string) wp_get_attachment_url( $attachment->ID ),
			'preview_url'   => (string) wp_get_attachment_image_url( $attachment->ID, 'thumbnail' ),
			'thumbnail_url' => (string) wp_get_attachment_image_url( $attachment->ID, 'thumbnail' ),
			'mime_type'     => (string) get_post_mime_type( $attachment->ID ),
			'file_name'     => basename( (string) get_attached_file( $attachment->ID ) ),
			'date'          => (string) $attachment->post_date,
		);
	}

	/**
	 * Prepare media payload with file data.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array|null
	 */
	private function prepare_media_payload_for_sync( $attachment_id ) {
		$data = Content_Sync_Media::prepare_image_data( $attachment_id, 'media_library_sync' );
		if ( ! $data || empty( $data['file_path'] ) || ! file_exists( $data['file_path'] ) ) {
			return null;
		}

		$file_data = file_get_contents( $data['file_path'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local attachment file read for sync payload.
		if ( false === $file_data ) {
			return null;
		}

		unset( $data['file_path'] );
		$data['file_data'] = base64_encode( $file_data );
		$data['acf']       = $this->export_media_acf_for_sync( $attachment_id );

		return $data;
	}

	/**
	 * Import media payloads.
	 *
	 * @param array $media_items Media payloads.
	 * @return array
	 */
	private function import_synced_media_items( $media_items ) {
		$result = array(
			'created' => 0,
			'skipped' => 0,
			'failed'  => 0,
		);

		foreach ( $media_items as $item ) {
			if ( ! is_array( $item ) || empty( $item['file_data'] ) || empty( $item['file_name'] ) ) {
				++$result['failed'];
				continue;
			}

			$file_hash = isset( $item['file_hash'] ) ? strtolower( sanitize_text_field( (string) $item['file_hash'] ) ) : '';
			if ( '' !== $file_hash && class_exists( Media_Hash::class ) ) {
				$existing = Media_Hash::get_attachment_by_hash( $file_hash, true );
				if ( $existing ) {
					$this->record_synced_attachment_source_ids( (int) $existing, absint( $item['attachment_id'] ?? 0 ), absint( $item['source_origin_attachment_id'] ?? 0 ) );
					$this->import_media_acf_for_sync( (int) $existing, $item );
					++$result['skipped'];
					continue;
				}
			}

			$attachment_id = $this->create_attachment_from_media_payload( $item );
			if ( $attachment_id > 0 ) {
				$this->import_media_acf_for_sync( $attachment_id, $item );
				++$result['created'];
			} else {
				++$result['failed'];
			}
		}

		return $result;
	}

	/**
	 * Export ACF fields attached to a media item for sync.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return array
	 */
	private function export_media_acf_for_sync( $attachment_id ) {
		if ( ! class_exists( ACF_Fields::class ) ) {
			return array();
		}

		$acf = array();
		foreach ( ACF_Fields::get_fields_for_content_type( 'media' ) as $field ) {
			if ( empty( $field['name'] ) ) {
				continue;
			}

			$name         = (string) $field['name'];
			$acf[ $name ] = ACF_Fields::export_value( 'media', (int) $attachment_id, $name );
		}

		return $acf;
	}

	/**
	 * Import synced ACF fields into a media item.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $item Media sync payload.
	 * @return void
	 */
	private function import_media_acf_for_sync( $attachment_id, $item ) {
		if ( $attachment_id <= 0 || empty( $item['acf'] ) || ! is_array( $item['acf'] ) || ! class_exists( ACF_Fields::class ) ) {
			return;
		}

		foreach ( $item['acf'] as $field_name => $value ) {
			$field_name = sanitize_text_field( (string) $field_name );
			if ( '' !== $field_name ) {
				ACF_Fields::import_value( 'media', (int) $attachment_id, $field_name, $value );
			}
		}
	}

	/**
	 * Create an attachment from a media sync payload.
	 *
	 * @param array $item Media payload.
	 * @return int
	 */
	private function create_attachment_from_media_payload( $item ) {
		$filename = sanitize_file_name( (string) $item['file_name'] );
		$decoded  = base64_decode( (string) $item['file_data'], true );
		if ( '' === $filename || false === $decoded ) {
			return 0;
		}

		$file_hash   = ! empty( $item['file_hash'] ) ? strtolower( sanitize_text_field( (string) $item['file_hash'] ) ) : '';
		$actual_hash = md5( $decoded );
		if ( '' !== $file_hash && $actual_hash !== $file_hash ) {
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';

		$temp_file = \wp_tempnam( $filename );
		if ( ! $temp_file || false === file_put_contents( $temp_file, $decoded ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- Temporary upload payload write.
			if ( $temp_file ) {
				wp_delete_file( $temp_file );
			}
			return 0;
		}

		$filetype_check = wp_check_filetype_and_ext( $temp_file, $filename );
		if ( empty( $filetype_check['type'] ) ) {
			wp_delete_file( $temp_file );
			return 0;
		}

		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attachment_id = media_handle_sideload(
			array(
				'name'     => $filename,
				'tmp_name' => $temp_file,
				'type'     => $filetype_check['type'],
			),
			0,
			sanitize_text_field( (string) ( $item['title'] ?? '' ) )
		);

		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $temp_file );
			return 0;
		}

		if ( ! empty( $item['alt_text'] ) ) {
			update_post_meta( (int) $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( (string) $item['alt_text'] ) );
		}

		if ( ! empty( $item['caption'] ) || ! empty( $item['description'] ) ) {
			wp_update_post(
				array(
					'ID'           => (int) $attachment_id,
					'post_excerpt' => sanitize_text_field( (string) ( $item['caption'] ?? '' ) ),
					'post_content' => wp_kses_post( (string) ( $item['description'] ?? '' ) ),
				)
			);
		}

		if ( class_exists( Media_Hash::class ) ) {
			Media_Hash::store_attachment_hash( (int) $attachment_id, $actual_hash );
		}

		if ( ! empty( $item['attachment_id'] ) ) {
			update_post_meta( (int) $attachment_id, '_rsl_ie_original_attachment_id', absint( $item['attachment_id'] ) );
			update_post_meta( (int) $attachment_id, '_rsl_ie_source_attachment_id', absint( $item['attachment_id'] ) );
		}
		if ( ! empty( $item['source_origin_attachment_id'] ) ) {
			update_post_meta( (int) $attachment_id, '_rsl_ie_source_origin_attachment_id', absint( $item['source_origin_attachment_id'] ) );
		}

		return (int) $attachment_id;
	}

	/**
	 * Persist source attachment IDs when reusing an existing synced media item.
	 *
	 * @param int $attachment_id        Local attachment ID.
	 * @param int $source_attachment_id Source attachment ID from the sender.
	 * @param int $source_origin_id     Original ancestor attachment ID, if any.
	 * @return void
	 */
	private function record_synced_attachment_source_ids( $attachment_id, $source_attachment_id, $source_origin_id ) {
		$attachment_id        = absint( $attachment_id );
		$source_attachment_id = absint( $source_attachment_id );
		$source_origin_id     = absint( $source_origin_id );

		if ( $attachment_id <= 0 ) {
			return;
		}

		if ( $source_attachment_id > 0 ) {
			update_post_meta( $attachment_id, '_rsl_ie_original_attachment_id', $source_attachment_id );
			update_post_meta( $attachment_id, '_rsl_ie_source_attachment_id', $source_attachment_id );
		}

		if ( $source_origin_id > 0 ) {
			update_post_meta( $attachment_id, '_rsl_ie_source_origin_attachment_id', $source_origin_id );
		}
	}

	/**
	 * Return connected sites without exposing API keys.
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

	/**
	 * Get connected site from current AJAX request.
	 *
	 * @return array|\WP_Error
	 */
	private function get_connected_site_from_request() {
		$site_id = isset( $_POST['site_id'] ) ? absint( wp_unslash( $_POST['site_id'] ) ) : 0;
		if ( $site_id <= 0 ) {
			return new \WP_Error( 'rsl_ie_site_required', __( 'Site ID is required.', 'import-export-by-rockstarlab' ) );
		}

		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			return new \WP_Error( 'rsl_ie_site_missing', __( 'Site not found.', 'import-export-by-rockstarlab' ) );
		}

		return $site;
	}

	/**
	 * POST to a connected site's Content Sync REST API.
	 *
	 * @param array  $site Connected site.
	 * @param string $endpoint Endpoint.
	 * @param array  $payload Payload.
	 * @param int    $timeout Timeout.
	 * @return array|\WP_Error
	 */
	private function remote_post( $site, $endpoint, $payload, $timeout = 30 ) {
		$response = Remote_API::post(
			$site['remote_url'],
			$endpoint,
			array(
				'timeout' => $timeout,
				'headers' => array(
					'Authorization' => 'Bearer ' . $site['api_key'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$data        = json_decode( wp_remote_retrieve_body( $response ), true );
		$status_code = (int) wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code || ! is_array( $data ) || empty( $data['success'] ) ) {
			return new \WP_Error( 'rsl_ie_remote_error', $this->get_remote_error_message( $data, $status_code ) );
		}

		return $data;
	}

	/**
	 * Extract a readable remote error message.
	 *
	 * @param mixed $data Remote response body.
	 * @param int   $status_code HTTP status code.
	 * @return string
	 */
	private function get_remote_error_message( $data, $status_code ) {
		if ( is_array( $data ) ) {
			if ( ! empty( $data['message'] ) && is_scalar( $data['message'] ) ) {
				return (string) $data['message'];
			}

			if ( ! empty( $data['data']['message'] ) && is_scalar( $data['data']['message'] ) ) {
				return (string) $data['data']['message'];
			}
		}

		return sprintf(
			/* translators: %d: HTTP status code. */
			__( 'Remote site returned HTTP %d.', 'import-export-by-rockstarlab' ),
			$status_code
		);
	}
}

<?php
/**
 * Content Sync Controller
 *
 * Handles AJAX requests for content synchronization between sites
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Helper\Ajax_Security;
use RockStarLab\ImportExport\Helper\ACF_Fields;
use RockStarLab\ImportExport\Helper\Field_Transformation_Bridge;
use RockStarLab\ImportExport\Helper\WPML_Compatibility;
use RockStarLab\ImportExport\Model\Connected_Site;

defined( 'ABSPATH' ) || exit;

/**
 * Content Sync Controller Class
 *
 * Manages connected sites and synchronization operations.
 *
 * @package RockStarLab\ImportExport\Controller
 */
class Content_Sync_Controller extends Base_Controller {
	/**
	 * Resolve the language selected in the current WPML admin screen.
	 *
	 * @return string
	 */
	private function get_admin_wpml_language() {
		if ( ! WPML_Compatibility::is_active() ) {
			return '';
		}

		$language = isset( $_GET['lang'] ) ? sanitize_key( wp_unslash( $_GET['lang'] ) ) : '';
		if ( 'all' === $language ) {
			return 'all';
		}
		if ( '' !== $language ) {
			return $language;
		}

		foreach ( array( '_icl_current_language', 'wpml_current_language', 'wpml_admin_language' ) as $cookie_name ) {
			if ( ! empty( $_COOKIE[ $cookie_name ] ) ) {
				$language = sanitize_key( wp_unslash( $_COOKIE[ $cookie_name ] ) );
				if ( '' !== $language ) {
					return $language;
				}
			}
		}

		return function_exists( 'apply_filters' ) ? sanitize_key( (string) apply_filters( 'wpml_current_language', '' ) ) : '';
	}

	/**
	 * Switch WPML language for internal sync queries and return previous language.
	 *
	 * @param string $language Language code or all.
	 * @return string Previous language code.
	 */
	private function switch_wpml_query_language( $language ) {
		$language = sanitize_key( (string) $language );
		if ( '' === $language || ! WPML_Compatibility::is_active() || ! function_exists( 'apply_filters' ) ) {
			return '';
		}

		$previous_language = sanitize_key( (string) apply_filters( 'wpml_current_language', '' ) );

		global $sitepress;
		if ( is_object( $sitepress ) && method_exists( $sitepress, 'switch_lang' ) ) {
			$sitepress->switch_lang( $language, true );
		} elseif ( function_exists( 'do_action' ) ) {
			do_action( 'wpml_switch_language', $language );
		}

		return $previous_language;
	}

	/**
	 * Return the current admin screen post status without reading query params.
	 *
	 * @return string
	 */
	private function get_current_admin_post_status() {
		global $post_status;

		return is_string( $post_status ) ? sanitize_key( $post_status ) : '';
	}

	/**
	 * Return the current post ID from WordPress admin globals.
	 *
	 * @return int
	 */
	private function get_current_admin_post_id() {
		global $post, $post_ID;

		if ( $post instanceof \WP_Post ) {
			return (int) $post->ID;
		}

		return ! empty( $post_ID ) ? absint( $post_ID ) : 0;
	}

	/**
	 * Return the current admin post type from screen/global context.
	 *
	 * @param string $default Default post type.
	 * @return string
	 */
	private function get_current_admin_post_type( $default = '' ) {
		global $typenow, $post;

		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( $screen && ! empty( $screen->post_type ) ) {
			return sanitize_key( $screen->post_type );
		}

		if ( ! empty( $typenow ) ) {
			return sanitize_key( $typenow );
		}

		if ( $post instanceof \WP_Post ) {
			return sanitize_key( $post->post_type );
		}

		$post_id = $this->get_current_admin_post_id();
		if ( $post_id ) {
			$maybe_post = get_post( $post_id );
			if ( $maybe_post ) {
				return sanitize_key( $maybe_post->post_type );
			}
		}

		return sanitize_key( $default );
	}

	/**
	 * Get AJAX actions to register
	 *
	 * @return array Array of action => config
	 */
	protected function get_ajax_actions() {
		return array(
			'content_sync_get_sites'            => array( 'callback' => 'get_sites' ),
			'content_sync_add_site'             => array( 'callback' => 'add_site' ),
			'content_sync_update_site'          => array( 'callback' => 'update_site' ),
			'content_sync_delete_site'          => array( 'callback' => 'delete_site' ),
			'content_sync_regenerate_key'       => array( 'callback' => 'regenerate_key' ),
			'content_sync_test_connection'      => array( 'callback' => 'test_connection' ),
			'content_sync_get_my_key'           => array( 'callback' => 'get_my_site_key' ),
			'content_sync_regenerate_my_key'    => array( 'callback' => 'regenerate_my_site_key' ),
			'content_sync_get_remote_posts'     => array( 'callback' => 'get_remote_posts' ),
			'content_sync_search_remote_posts'  => array( 'callback' => 'search_remote_posts' ),
			'content_sync_search_local_posts'   => array( 'callback' => 'search_local_posts' ),
			'content_sync_auto_map_by_title'    => array( 'callback' => 'auto_map_by_title' ),
			'content_sync_get_children_posts'   => array( 'callback' => 'get_children_posts' ),
			'content_sync_get_local_posts_info' => array( 'callback' => 'get_local_posts_info' ),
			'content_sync_push'                 => array( 'callback' => 'push_content' ),
			'content_sync_pull'                 => array( 'callback' => 'pull_content' ),
			'content_sync_get_remote_terms'     => array( 'callback' => 'get_remote_terms' ),
			'content_sync_push_terms'           => array( 'callback' => 'push_terms' ),
			'content_sync_pull_terms'           => array( 'callback' => 'pull_terms' ),
			'content_sync_get_remote_comments'  => array( 'callback' => 'get_remote_comments' ),
			'content_sync_push_comments'        => array( 'callback' => 'push_comments' ),
			'content_sync_pull_comments'        => array( 'callback' => 'pull_comments' ),
		);
	}

	/**
	 * Get all connected sites
	 */
	public function get_sites() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$sites = Connected_Site::get_all();
		$stats = Connected_Site::get_stats();

		$this->send_success(
			array(
				'sites' => $sites,
				'stats' => $stats,
			)
		);
	}

	/**
	 * Add new site connection
	 */
	public function add_site() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$name       = $this->get_request_param( 'name', '' );
		$remote_url = $this->get_request_param( 'remote_url', '' );
		$api_key    = $this->get_request_param( 'api_key', '' );
		$direction  = $this->get_request_param( 'direction', 'bidirectional' );

		// Validate input
		if ( empty( $name ) ) {
			$this->send_error( __( 'Site name is required', 'import-export-by-rockstarlab' ) );
		}

		if ( empty( $remote_url ) ) {
			$this->send_error( __( 'Remote URL is required', 'import-export-by-rockstarlab' ) );
		}

		if ( empty( $api_key ) ) {
			$this->send_error( __( 'API key is required', 'import-export-by-rockstarlab' ) );
		}

		// Validate URL
		$remote_url = esc_url_raw( $remote_url );
		if ( ! filter_var( $remote_url, FILTER_VALIDATE_URL ) ) {
			$this->send_error( __( 'Invalid URL format', 'import-export-by-rockstarlab' ) );
		}

		// Check if URL already exists
		if ( Connected_Site::exists_by_url( $remote_url ) ) {
			$this->send_error( __( 'This site is already connected', 'import-export-by-rockstarlab' ) );
		}

		$validation_result = $this->validate_remote_site( $remote_url, $api_key );
		if ( is_wp_error( $validation_result ) ) {
			$this->send_error( $validation_result->get_error_message() );
		}

		// Validate direction
		$allowed_directions = array( 'pull', 'push', 'bidirectional' );
		if ( ! in_array( $direction, $allowed_directions, true ) ) {
			$direction = 'bidirectional';
		}

		$data = array(
			'name'       => sanitize_text_field( $name ),
			'remote_url' => $remote_url,
			'direction'  => $direction,
			'api_key'    => sanitize_text_field( $api_key ),
		);

		$site_id = Connected_Site::create( $data );

		if ( ! $site_id ) {
			$this->send_error( __( 'Failed to add site connection', 'import-export-by-rockstarlab' ) );
		}

		$site = Connected_Site::get_by_id( $site_id );

		$this->send_success(
			array(
				'message' => __( 'Site connection added successfully', 'import-export-by-rockstarlab' ),
				'site'    => $site,
			)
		);
	}

	/**
	 * Update site connection
	 */
	public function update_site() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$site_id    = $this->get_request_param( 'site_id', 0, 'post' );
		$name       = $this->get_request_param( 'name', '', 'post' );
		$remote_url = $this->get_request_param( 'remote_url', '', 'post' );
		$api_key    = $this->get_request_param( 'api_key', '', 'post' );
		$direction  = $this->get_request_param( 'direction', 'bidirectional', 'post' );
		$status     = $this->get_request_param( 'status', 'active', 'post' );

		if ( ! $site_id ) {
			$this->send_error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		// Check if site exists
		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			$this->send_error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}

		$data = array();

		if ( ! empty( $name ) ) {
			$data['name'] = sanitize_text_field( $name );
		}

		// Determine which URL to use for validation
		$url_to_validate = ! empty( $remote_url ) ? $remote_url : $site['remote_url'];

		if ( ! empty( $remote_url ) ) {
			$remote_url = esc_url_raw( $remote_url );
			if ( ! filter_var( $remote_url, FILTER_VALIDATE_URL ) ) {
				$this->send_error( __( 'Invalid URL format', 'import-export-by-rockstarlab' ) );
			}

			// Check if URL already exists (excluding current site)
			if ( Connected_Site::exists_by_url( $remote_url, $site_id ) ) {
				$this->send_error( __( 'This site is already connected', 'import-export-by-rockstarlab' ) );
			}

			$data['remote_url'] = $remote_url;
		}

		// If API key is provided, validate it
		if ( ! empty( $api_key ) ) {
			$validation_result = $this->validate_remote_site( $url_to_validate, $api_key );
			if ( is_wp_error( $validation_result ) ) {
				$this->send_error( $validation_result->get_error_message() );
			}
			$data['api_key'] = sanitize_text_field( $api_key );
		}

		if ( ! empty( $direction ) ) {
			$allowed_directions = array( 'pull', 'push', 'bidirectional' );
			if ( in_array( $direction, $allowed_directions, true ) ) {
				$data['direction'] = $direction;
			}
		}

		if ( ! empty( $status ) ) {
			$allowed_statuses = array( 'active', 'inactive', 'error' );
			if ( in_array( $status, $allowed_statuses, true ) ) {
				$data['status'] = $status;
			}
		}

		if ( empty( $data ) ) {
			// No changes made - return current site data
			$this->send_success(
				array(
					'message' => __( 'No changes were made', 'import-export-by-rockstarlab' ),
					'site'    => $site,
				)
			);
		}

		$result = Connected_Site::update( $site_id, $data );

		if ( ! $result ) {
			$this->send_error( __( 'Failed to update site connection', 'import-export-by-rockstarlab' ) );
		}

		$updated_site = Connected_Site::get_by_id( $site_id );

		// Create detailed message about what was updated
		$updated_fields = array_keys( $data );
		$message        = __( 'Site connection updated successfully', 'import-export-by-rockstarlab' );

		if ( count( $updated_fields ) === 1 ) {
			$field_name   = $updated_fields[0];
			$field_labels = array(
				'name'       => __( 'name', 'import-export-by-rockstarlab' ),
				'remote_url' => __( 'URL', 'import-export-by-rockstarlab' ),
				'direction'  => __( 'direction', 'import-export-by-rockstarlab' ),
				'status'     => __( 'status', 'import-export-by-rockstarlab' ),
				'api_key'    => __( 'API key', 'import-export-by-rockstarlab' ),
			);

			if ( isset( $field_labels[ $field_name ] ) ) {
				$message = sprintf(
					/* translators: %s: field name that was updated */
					__( 'Site %s updated successfully', 'import-export-by-rockstarlab' ),
					$field_labels[ $field_name ]
				);
			}
		}

		$this->send_success(
			array(
				'message' => $message,
				'site'    => $updated_site,
			)
		);
	}

	/**
	 * Delete site connection
	 */
	public function delete_site() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$site_id = $this->get_request_param( 'site_id', 0 );

		if ( ! $site_id ) {
			$this->send_error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		// Check if site exists
		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			$this->send_error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}

		$result = Connected_Site::delete( $site_id );

		if ( ! $result ) {
			$this->send_error( __( 'Failed to delete site connection', 'import-export-by-rockstarlab' ) );
		}

		$this->send_success(
			array(
				'message' => __( 'Site connection deleted successfully', 'import-export-by-rockstarlab' ),
			)
		);
	}

	/**
	 * Regenerate API key for a site
	 */
	public function regenerate_key() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$site_id = $this->get_request_param( 'site_id', 0 );

		if ( ! $site_id ) {
			$this->send_error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		// Check if site exists
		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			$this->send_error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}

		$new_key = Connected_Site::regenerate_api_key( $site_id );

		if ( ! $new_key ) {
			$this->send_error( __( 'Failed to regenerate API key', 'import-export-by-rockstarlab' ) );
		}

		$this->send_success(
			array(
				'message' => __( 'API key regenerated successfully', 'import-export-by-rockstarlab' ),
				'api_key' => $new_key,
			)
		);
	}

	/**
	 * Test connection to remote site
	 */
	public function test_connection() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$site_id = $this->get_request_param( 'site_id', 0 );

		if ( ! $site_id ) {
			$this->send_error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		// Check if site exists
		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			$this->send_error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}

		$validation_result = $this->validate_remote_site( $site['remote_url'], $site['api_key'] );

		if ( is_wp_error( $validation_result ) ) {
			Connected_Site::update_last_sync( $site_id, $validation_result->get_error_message() );
			// Update status to error
			Connected_Site::update( $site_id, array( 'status' => 'error' ) );
			$this->send_error( $validation_result->get_error_message() );
		}

		// Connection successful - update last sync and status
		Connected_Site::update_last_sync( $site_id );
		Connected_Site::update( $site_id, array( 'status' => 'active' ) );

		$this->send_success(
			array(
				'message' => __( 'Connection successful. API key is valid.', 'import-export-by-rockstarlab' ),
			)
		);
	}

	/**
	 * Get this site's API key for incoming connections
	 */
	public function get_my_site_key() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		// Get or create API key for this site
		$site_key = get_option( 'rsl_ie_site_api_key' );

		if ( ! $site_key ) {
			// Generate new API key for this site
			$site_key = Connected_Site::generate_api_key();
			update_option( 'rsl_ie_site_api_key', $site_key );
		}

		$site_url  = get_site_url();
		$site_name = get_bloginfo( 'name' );

		$this->send_success(
			array(
				'site_key'  => $site_key,
				'site_url'  => $site_url,
				'site_name' => $site_name,
			)
		);
	}

	/**
	 * Regenerate this site's API key
	 */
	public function regenerate_my_site_key() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		// Generate new API key
		$new_key = Connected_Site::generate_api_key();
		update_option( 'rsl_ie_site_api_key', $new_key );

		$this->send_success(
			array(
				'message'  => __( 'API key regenerated successfully. All remote sites will need to update their connection with the new key.', 'import-export-by-rockstarlab' ),
				'site_key' => $new_key,
			)
		);
	}

	/**
	 * Validate remote site connection and API key
	 *
	 * @param string $remote_url Remote site URL.
	 * @param string $api_key API key to validate.
	 * @return true|\WP_Error True if valid, WP_Error on failure.
	 */
	private function validate_remote_site( $remote_url, $api_key ) {
		$response = \RockStarLab\ImportExport\Helper\Remote_API::post(
			$remote_url,
			'validate',
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body'    => wp_json_encode(
					array(
						'action' => 'validate_connection',
					)
				),
			)
		);

		// Check for connection errors
		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'connection_failed',
				sprintf(
					/* translators: %s: error message */
					__( 'Cannot connect to remote site: %s', 'import-export-by-rockstarlab' ),
					$response->get_error_message()
				)
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		// Check status code
		if ( 404 === $status_code ) {
			return new \WP_Error(
				'plugin_not_installed',
				__( 'Import Export by RockStarLab plugin is not installed or activated on the remote site.', 'import-export-by-rockstarlab' )
			);
		}

		// Try to parse JSON response.
		$data = json_decode( $body, true );

		if ( 403 === $status_code ) {
			return new \WP_Error(
				'invalid_api_key',
				__( 'Access forbidden. Please check the API key and try again.', 'import-export-by-rockstarlab' )
			);
		}

		if ( 401 === $status_code ) {
			return new \WP_Error(
				'invalid_api_key',
				__( 'Invalid API key. Please check the API key and try again.', 'import-export-by-rockstarlab' )
			);
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			return new \WP_Error(
				'connection_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Remote site returned error status: %d', 'import-export-by-rockstarlab' ),
					$status_code
				)
			);
		}

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error(
				'invalid_response',
				__( 'Remote site returned invalid response format.', 'import-export-by-rockstarlab' )
			);
		}

		// Check if response indicates success
		if ( ! isset( $data['success'] ) || ! $data['success'] ) {
			$error_message = isset( $data['message'] ) ? $data['message'] : __( 'Unknown error from remote site.', 'import-export-by-rockstarlab' );
			return new \WP_Error( 'validation_failed', $error_message );
		}

		return true;
	}

	/**
	 * Register hooks for post list screens
	 */
	public function register_post_list_hooks() {
		// Load assets for post list screens
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_post_list_assets' ) );

		// Add sync button and modal to post list screens
		add_action( 'restrict_manage_posts', array( $this, 'render_sync_button' ) );
		add_action( 'admin_footer-edit.php', array( $this, 'render_sync_modal' ) );

		// Add sync button to post edit screen
		add_action( 'post_submitbox_misc_actions', array( $this, 'render_post_edit_sync_button' ) );
		add_action( 'admin_footer-post.php', array( $this, 'render_gutenberg_sync_button' ) );
		add_action( 'admin_footer-post-new.php', array( $this, 'render_gutenberg_sync_button' ) );
		add_action( 'admin_footer-post.php', array( $this, 'render_sync_modal' ) );
		add_action( 'admin_footer-post-new.php', array( $this, 'render_sync_modal' ) );
		// Some admin contexts (notably the block editor) can behave differently with
		// hook-suffixed footer actions. Also hook into generic admin_footer and guard
		// against duplicate rendering inside render_sync_modal().
		add_action( 'admin_footer', array( $this, 'render_sync_modal' ) );
	}

	/**
	 * Enqueue assets for post list screens
	 */
	public function enqueue_post_list_assets( $hook_suffix ) {
		// Load on edit.php (post list) and post.php/post-new.php (edit post)
		if ( ! in_array( $hook_suffix, array( 'edit.php', 'post.php', 'post-new.php' ) ) ) {
			return;
		}

		// Don't load on trash page
		if ( 'edit.php' === $hook_suffix ) {
			$post_status = $this->get_current_admin_post_status();
			if ( 'trash' === $post_status ) {
				return;
			}
		}

		// Get current post type
		global $post;
		$current_post_type = '';

		if ( 'edit.php' === $hook_suffix ) {
			$current_post_type = $this->get_current_admin_post_type();
		} elseif ( in_array( $hook_suffix, array( 'post.php', 'post-new.php' ), true ) ) {
			// On post.php, $post may not yet be populated during admin_enqueue_scripts.
			if ( $post ) {
				$current_post_type = $post->post_type;
			} else {
				$post_id = $this->get_current_admin_post_id();
				if ( $post_id ) {
					$maybe_post = get_post( (int) $post_id );
					if ( $maybe_post ) {
						$current_post_type = $maybe_post->post_type;
					}
				}
				if ( empty( $current_post_type ) ) {
					$current_post_type = $this->get_current_admin_post_type( 'post' );
				}
			}
		}

		if ( empty( $current_post_type ) || ! $this->should_show_post_type_quick_actions( $current_post_type ) ) {
			return;
		}

		// Enqueue post sync script
		// Note: on Gutenberg post editor screens, admin footer scripts are not always printed
		// the same way as on list screens. Load in header for post.php/post-new.php so the
		// sync UI is reliably available.
		$in_footer = ( 'edit.php' === $hook_suffix );
		wp_enqueue_script(
			'rsl-ie-post-sync',
			plugins_url( 'assets/js/post-sync-standalone.js', RSL_IE_FILE ),
			array( 'jquery' ),
			filemtime( plugin_dir_path( RSL_IE_FILE ) . 'assets/js/post-sync-standalone.js' ),
			$in_footer
		);

		// Localize script
		// Get connected sites for Select2 AJAX
		$sites     = Connected_Site::get_all();
		$sites_map = array();
		foreach ( $sites as $site ) {
			$sites_map[ $site['id'] ] = array(
				'id'         => $site['id'],
				'name'       => $site['name'],
				'remote_url' => $site['remote_url'],
				'api_key'    => $site['api_key'], // Needed for remote API calls
			);
		}

		wp_localize_script(
			'rsl-ie-post-sync',
			'rslIePostSyncData',
			array(
				'nonces'                      => Ajax_Security::get_nonces(),
				'ajaxurl'                     => admin_url( 'admin-ajax.php' ),
				'ajaxUrl'                     => admin_url( 'admin-ajax.php' ),
				'adminUrl'                    => admin_url(),
				'functionsUrl'                => Field_Transformation_Bridge::get_management_url(),
				'fieldTransformationsEnabled' => Field_Transformation_Bridge::is_enabled(),
				'exportUrl'                   => admin_url( 'admin.php?page=rsl-ie-export' ),
				'contentSyncUrl'              => admin_url( 'admin.php?page=rsl-ie-content-sync' ),
				'connectedSites'              => $sites_map,
				'wpmlLanguage'                => $this->get_admin_wpml_language(),
				'i18n'                        => array(
					// Alerts & Messages
					'pleaseSavePost'          => __( 'Please save the post first', 'import-export-by-rockstarlab' ),
					'pleaseSelectSite'        => __( 'Please select a site', 'import-export-by-rockstarlab' ),
					'noPostsSelected'         => __( 'No posts selected', 'import-export-by-rockstarlab' ),
					'failedLoadRemotePosts'   => __( 'Failed to load remote posts', 'import-export-by-rockstarlab' ),
					'unknownError'            => __( 'Unknown error', 'import-export-by-rockstarlab' ),
					'failedConnectRemote'     => __( 'Failed to connect to remote site', 'import-export-by-rockstarlab' ),
					'failedLoadLocalPosts'    => __( 'Failed to load local posts info', 'import-export-by-rockstarlab' ),
					'pleaseSelectOnePost'     => __( 'Please select at least one post', 'import-export-by-rockstarlab' ),

					// translators: %s = content placeholder.
					// Count Text
					'onePost'                 => __( '1 post', 'import-export-by-rockstarlab' ),
					// translators: %s is a dynamic value.
					'postsCount'              => __( '%s posts', 'import-export-by-rockstarlab' ),

					// Post Info
					// translators: %s is a dynamic value.
					'postHash'                => __( 'Post #%s', 'import-export-by-rockstarlab' ),
					// translators: %s = content placeholder.
					'idLabel'                 => __( 'ID:', 'import-export-by-rockstarlab' ),
					'noTitle'                 => __( '(No title)', 'import-export-by-rockstarlab' ),

					// translators: %s = content placeholder.
					// Actions
					'createNewPost'           => __( '➕ Create New Post', 'import-export-by-rockstarlab' ),
					// translators: 1: post title or name, 2: post ID.
					'updatePost'              => __( '🔄 Update: %1$s (ID: %2$s)', 'import-export-by-rockstarlab' ),
					// translators: %s is a dynamic value.
					'searchForUpdate'         => __( 'Search for a %s to update...', 'import-export-by-rockstarlab' ),

					// Progress
					// translators: %s is a dynamic value.
					'starting'                => __( 'Starting %s...', 'import-export-by-rockstarlab' ),
					'completed'               => __( 'Completed!', 'import-export-by-rockstarlab' ),
					'syncCompletedSuccess'    => __( 'Sync completed successfully', 'import-export-by-rockstarlab' ),
					'pullingPosts'            => __( 'Pulling posts...', 'import-export-by-rockstarlab' ),
					'syncFailed'              => __( 'Sync failed', 'import-export-by-rockstarlab' ),
					'errorDuringSync'         => __( 'An error occurred during sync', 'import-export-by-rockstarlab' ),
					'matchingByTitle'         => __( 'Matching...', 'import-export-by-rockstarlab' ),
					'selectRemotePostForPull' => __( 'Please select at least one remote post to pull.', 'import-export-by-rockstarlab' ),

					// Browse
					'noPostsFound'            => __( 'No posts found', 'import-export-by-rockstarlab' ),
					'child'                   => __( 'child', 'import-export-by-rockstarlab' ),
					'children'                => __( 'children', 'import-export-by-rockstarlab' ),
					'pluginDataNotLoaded'     => __( 'Plugin data not loaded. Please refresh the page.', 'import-export-by-rockstarlab' ),
					'errorLoadingPosts'       => __( 'An error occurred while loading posts', 'import-export-by-rockstarlab' ),
					'failedLoadChildren'      => __( 'Failed to load children', 'import-export-by-rockstarlab' ),
					'errorLoadingChildren'    => __( 'Error loading children', 'import-export-by-rockstarlab' ),
				),
			)
		);

		// Enqueue styles (reuse the main plugin styles)
		wp_enqueue_style(
			'rsl-ie-post-sync-styles',
			plugins_url( 'assets/css/app.css', RSL_IE_FILE ),
			array(),
			filemtime( plugin_dir_path( RSL_IE_FILE ) . 'assets/css/app.css' )
		);

		// WordPress 7+ admin UI tweaks.
		global $wp_version;
		if ( isset( $wp_version ) && version_compare( $wp_version, '7.0', '>=' ) ) {
			wp_enqueue_style(
				'rsl-ie-post-sync-admin-wp7',
				plugins_url( 'assets/css/admin-wp7.css', RSL_IE_FILE ),
				array( 'rsl-ie-post-sync-styles' ),
				filemtime( plugin_dir_path( RSL_IE_FILE ) . 'assets/css/admin-wp7.css' )
			);
		}

		// Enqueue Gutenberg sync script for post edit screens
		if ( in_array( $hook_suffix, array( 'post.php', 'post-new.php' ) ) ) {

			// Only show for posts being edited
			if ( $post ) {
				// Enqueue the Gutenberg sync script
				wp_enqueue_script(
					'rsl-ie-gutenberg-sync',
					plugins_url( 'assets/js/gutenberg-sync.js', RSL_IE_FILE ),
					array( 'jquery', 'wp-editor', 'wp-data', 'wp-element', 'wp-components' ),
					filemtime( plugin_dir_path( RSL_IE_FILE ) . 'assets/js/gutenberg-sync.js' ),
					false
				);

				// Localize script with necessary data
				wp_localize_script(
					'rsl-ie-gutenberg-sync',
					'rslIeData',
					array(
						'nonces'                      => Ajax_Security::get_nonces(),
						'ajaxurl'                     => admin_url( 'admin-ajax.php' ),
						'ajaxUrl'                     => admin_url( 'admin-ajax.php' ),
						'adminUrl'                    => admin_url(),
						'functionsUrl'                => Field_Transformation_Bridge::get_management_url(),
						'fieldTransformationsEnabled' => Field_Transformation_Bridge::is_enabled(),
						'exportUrl'                   => admin_url( 'admin.php?page=rsl-ie-export' ),
						'i18n'                        => array(
							'syncContent'  => __( 'Sync Content', 'import-export-by-rockstarlab' ),
							'syncThisPost' => __( 'Sync This Post', 'import-export-by-rockstarlab' ),
						),
					)
				);
			}
		}
	}

		/**
		 * Render sync button on post list screen
		 */
	public function render_sync_button() {
		global $typenow;

		// Only show on post list screens
		if ( empty( $typenow ) ) {
			return;
		}

		// Don't show on trash page
		$post_status = $this->get_current_admin_post_status();
		if ( 'trash' === $post_status ) {
			return;
		}

		if ( ! $this->should_show_post_type_quick_actions( $typenow ) ) {
			return;
		}

		require RSL_IE_PATH . '/app/View/sync/sync-button.php';
	}

		/**
		 * Render sync modal
		 */
	public function render_sync_modal() {
		static $rendered = false;
		if ( $rendered ) {
			return;
		}

		$current_post_type = $this->get_current_admin_post_type();

		if ( empty( $current_post_type ) ) {
			return;
		}

		// Don't show on trash page
		$post_status = $this->get_current_admin_post_status();
		if ( 'trash' === $post_status ) {
			return;
		}

		if ( ! \RockStarLab\ImportExport\Helper\Button_Location_Settings::is_sync_enabled( 'post_type:' . $current_post_type ) ) {
			return;
		}

		// Get connected sites
		$sites = Connected_Site::get_all();

		$rendered = true;

		// Load view templates
		require RSL_IE_PATH . '/app/View/sync/sync-modal.php';
		require RSL_IE_PATH . '/app/View/sync/mapping-modal.php';
		require RSL_IE_PATH . '/app/View/sync/browse-modal.php';
	}

	/**
	 * Render sync button in post edit screen (Classic Editor)
	 */
	public function render_post_edit_sync_button() {
		global $post;

		// Only show for published posts or posts being edited
		if ( ! $post || ( 'auto-draft' === $post->post_status ) ) {
			return;
		}

		if ( ! \RockStarLab\ImportExport\Helper\Button_Location_Settings::is_sync_enabled( 'post_type:' . $post->post_type ) ) {
			return;
		}

		require RSL_IE_PATH . '/app/View/sync/post-edit-button.php';
	}

	/**
	 * Check whether Export or Sync quick actions are enabled for a post type.
	 *
	 * @param string $post_type Post type slug.
	 * @return bool
	 */
	private function should_show_post_type_quick_actions( $post_type ) {
		$post_type = sanitize_key( $post_type );
		if ( '' === $post_type ) {
			return false;
		}

		$location_id = 'post_type:' . $post_type;

		return \RockStarLab\ImportExport\Helper\Button_Location_Settings::is_export_enabled( $location_id )
			|| \RockStarLab\ImportExport\Helper\Button_Location_Settings::is_sync_enabled( $location_id );
	}

	/**
	 * Render Gutenberg sync button
	 *
	 * @deprecated Moved to enqueue_post_list_assets
	 */
	public function render_gutenberg_sync_button() {
		// This method is kept for backwards compatibility but the functionality
		// has been moved to enqueue_post_list_assets() for proper script enqueueing
	}

	/**
	 * Get list of posts from remote site for mapping
	 */
	public function get_remote_posts() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$site_id          = $this->get_request_param( 'site_id', 0 );
		$post_type        = $this->get_request_param( 'post_type', 'any' );
		$search           = $this->get_request_param( 'search', '' );
		$status           = $this->get_request_param( 'status', '' );
		$page             = $this->get_request_param( 'page', 1 );
		$per_page         = $this->get_request_param( 'per_page', 20 );
		$commentable_only = filter_var( $this->get_request_param( 'commentable_only', false ), FILTER_VALIDATE_BOOLEAN );
		$comment_type     = sanitize_key( (string) $this->get_request_param( 'comment_type', '' ) );
		$language         = sanitize_key( (string) $this->get_request_param( 'language', '' ) );
		if ( '' === $language ) {
			$language = $this->get_admin_wpml_language();
		}

		// Validate input
		if ( empty( $site_id ) ) {
			$this->send_error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		// Get site details
		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			$this->send_error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}

		// Request posts list from remote site
		$response = \RockStarLab\ImportExport\Helper\Remote_API::post(
			$site['remote_url'],
			'list-posts',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $site['api_key'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'post_type'        => $post_type,
						'search'           => $search,
						'status'           => $status,
						'page'             => $page,
						'per_page'         => $per_page,
						'commentable_only' => $commentable_only,
						'comment_type'     => $comment_type,
						'language'         => $language,
					)
				),
			)
		);
		// translators: %s = content placeholder.

		if ( is_wp_error( $response ) ) {
			$this->send_error( __( 'Failed to connect to remote site: ', 'import-export-by-rockstarlab' ) . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( $status_code !== 200 ) {
			$error_data = json_decode( $body, true );
			// translators: %d is a dynamic value.
			$error_msg = isset( $error_data['message'] ) ? $error_data['message'] : sprintf( __( 'Request failed with status code: %d', 'import-export-by-rockstarlab' ), $status_code );
			$this->send_error( $error_msg );
		}

		$data = json_decode( $body, true );
		if ( ! isset( $data['success'] ) || ! $data['success'] || ! isset( $data['posts'] ) ) {
			$this->send_error( __( 'Remote site returned invalid data', 'import-export-by-rockstarlab' ) );
		}

		$this->send_success(
			array(
				'posts'         => $data['posts'],
				'total'         => isset( $data['total'] ) ? $data['total'] : count( $data['posts'] ),
				'pages'         => isset( $data['pages'] ) ? $data['pages'] : 1,
				'current_page'  => isset( $data['current_page'] ) ? $data['current_page'] : 1,
				'status_counts' => isset( $data['status_counts'] ) ? $data['status_counts'] : array(),
			)
		);
	}

	/**
	 * Search remote posts with pagination (alias for get_remote_posts for Select2)
	 */
	public function search_remote_posts() {
		// This is just an alias for get_remote_posts with a different action name
		return $this->get_remote_posts();
	}

	/**
	 * Search local posts with pagination for Select2 mapping fields.
	 */
	public function search_local_posts() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$post_type        = sanitize_key( (string) $this->get_request_param( 'post_type', 'any' ) );
		$commentable_only = filter_var( $this->get_request_param( 'commentable_only', false ), FILTER_VALIDATE_BOOLEAN );
		$comment_type     = sanitize_key( (string) $this->get_request_param( 'comment_type', '' ) );
		$search           = sanitize_text_field( (string) $this->get_request_param( 'search', '' ) );
		$page             = max( 1, absint( $this->get_request_param( 'page', 1 ) ) );
		$per_page         = min( max( 1, absint( $this->get_request_param( 'per_page', 20 ) ) ), 50 );
		$language         = sanitize_key( (string) $this->get_request_param( 'language', '' ) );
		if ( '' === $language ) {
			$language = $this->get_admin_wpml_language();
		}

		$args = array(
			'post_type'              => $commentable_only ? $this->get_commentable_sync_post_types_for_context( $comment_type, $post_type ) : ( '' !== $post_type ? $post_type : 'any' ),
			'post_status'            => 'any',
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => 'date',
			'order'                  => 'DESC',
			'ignore_sticky_posts'    => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
		);

		if ( '' !== $search ) {
			$args['s'] = $search;
		}

		if ( '' !== $language && 'all' !== $language && WPML_Compatibility::is_active() ) {
			$args['lang']             = $language;
			$args['suppress_filters'] = false;
		} elseif ( 'all' === $language ) {
			$args['suppress_filters'] = true;
			unset( $args['lang'] );
		}

		$previous_wpml_language = $this->switch_wpml_query_language( $language );
		$query                  = new \WP_Query( $args );
		if ( '' !== $previous_wpml_language ) {
			$this->switch_wpml_query_language( $previous_wpml_language );
		}
		$posts = array();
		foreach ( $query->posts as $post ) {
			$posts[] = array(
				'ID'          => (int) $post->ID,
				'title'       => (string) get_the_title( $post ),
				'post_title'  => (string) $post->post_title,
				'post_name'   => (string) $post->post_name,
				'post_type'   => (string) $post->post_type,
				'post_status' => (string) $post->post_status,
			);
		}

		$this->send_success(
			array(
				'posts'        => $posts,
				'total'        => (int) $query->found_posts,
				'pages'        => max( 1, (int) $query->max_num_pages ),
				'current_page' => $page,
			)
		);
	}

	/**
	 * Get public post types that support comments.
	 *
	 * @return string[]
	 */
	private function get_commentable_sync_post_types() {
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'names'
		);

		$post_types = array_values(
			array_filter(
				(array) $post_types,
				static function ( $post_type ) {
					return 'attachment' !== $post_type && post_type_supports( $post_type, 'comments' );
				}
			)
		);

		return ! empty( $post_types ) ? $post_types : array( 'post', 'page' );
	}

	/**
	 * Return commentable post types for comments vs WooCommerce reviews.
	 *
	 * @param string $comment_type Comment subtype.
	 * @param string $requested_post_type Requested post type.
	 * @return string|array
	 */
	private function get_commentable_sync_post_types_for_context( $comment_type, $requested_post_type = 'any' ) {
		if ( 'review' === sanitize_key( (string) $comment_type ) ) {
			return post_type_exists( 'product' ) ? 'product' : '__rsl_ie_no_post_type';
		}

		$requested_post_type = sanitize_key( (string) $requested_post_type );
		if ( 'product' === $requested_post_type ) {
			return '__rsl_ie_no_post_type';
		}
		if ( '' !== $requested_post_type && 'any' !== $requested_post_type ) {
			return $requested_post_type;
		}

		return array_values( array_diff( $this->get_commentable_sync_post_types(), array( 'product' ) ) );
	}

	/**
	 * Get product IDs for separating normal comments from WooCommerce reviews.
	 *
	 * @param string $language WPML language code.
	 * @return int[]
	 */
	private function get_product_post_ids_for_comment_filter( $language = '' ) {
		if ( ! post_type_exists( 'product' ) ) {
			return array();
		}

		$args = array(
			'post_type'              => 'product',
			'post_status'            => 'any',
			'posts_per_page'         => -1,
			'fields'                 => 'ids',
			'no_found_rows'          => true,
			'update_post_meta_cache' => false,
			'update_post_term_cache' => false,
			'suppress_filters'       => false,
		);

		$language = sanitize_key( (string) $language );
		if ( '' !== $language && 'all' !== $language && WPML_Compatibility::is_active() ) {
			$args['lang'] = $language;
		} elseif ( 'all' === $language ) {
			$args['suppress_filters'] = true;
		}

		return array_values( array_map( 'absint', get_posts( $args ) ) );
	}

	/**
	 * Check whether a comment belongs to the requested sync entity.
	 *
	 * @param \WP_Comment $comment      Comment object.
	 * @param string      $comment_type Requested comment subtype.
	 * @return bool
	 */
	private function is_comment_in_sync_context( $comment, $comment_type = '' ) {
		$post      = get_post( (int) $comment->comment_post_ID );
		$post_type = $post ? (string) $post->post_type : '';

		if ( 'review' === sanitize_key( (string) $comment_type ) ) {
			return 'review' === (string) $comment->comment_type && 'product' === $post_type;
		}

		return 'review' !== (string) $comment->comment_type && 'product' !== $post_type;
	}

	/**
	 * Get taxonomy terms from a remote site for Browse dialogs.
	 */
	public function get_remote_terms() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$site_id  = $this->get_request_param( 'site_id', 0 );
		$taxonomy = sanitize_key( (string) $this->get_request_param( 'taxonomy', '' ) );
		$search   = sanitize_text_field( (string) $this->get_request_param( 'search', '' ) );
		$page     = absint( $this->get_request_param( 'page', 1 ) );
		$per_page = absint( $this->get_request_param( 'per_page', 20 ) );
		$language = sanitize_key( (string) $this->get_request_param( 'language', '' ) );

		if ( empty( $site_id ) ) {
			$this->send_error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			$this->send_error( __( 'Invalid taxonomy.', 'import-export-by-rockstarlab' ) );
		}

		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			$this->send_error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}

		$response = $this->remote_post(
			$site,
			'list-terms',
			array(
				'taxonomy' => $taxonomy,
				'search'   => $search,
				'page'     => max( 1, $page ),
				'per_page' => min( max( 1, $per_page ), 100 ),
				'language' => $language,
			),
			30
		);

		if ( is_wp_error( $response ) ) {
			$this->send_error( $response->get_error_message() );
		}

		$this->send_success( $response );
	}

	/**
	 * Push selected local taxonomy terms to a remote site.
	 */
	public function push_terms() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$site_id  = $this->get_request_param( 'site_id', 0 );
		$taxonomy = sanitize_key( (string) $this->get_request_param( 'taxonomy', '' ) );
		$term_ids = $this->get_request_array( 'term_ids', array() );

		if ( empty( $site_id ) ) {
			$this->send_error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			$this->send_error( __( 'Invalid taxonomy.', 'import-export-by-rockstarlab' ) );
		}

		$term_ids = array_values( array_filter( array_unique( array_map( 'absint', $term_ids ) ) ) );
		if ( empty( $term_ids ) ) {
			$this->send_error( __( 'No terms selected.', 'import-export-by-rockstarlab' ) );
		}

		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			$this->send_error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}

		$terms      = array();
		$all_images = array();
		foreach ( $term_ids as $term_id ) {
			$term = get_term( $term_id, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				$term_info = $this->prepare_term_for_sync( $term, $taxonomy );
				$terms[]   = $term_info;
				$this->collect_term_acf_images_for_sync( $term_info, $term, $taxonomy, $all_images );
			}
		}

		if ( empty( $terms ) ) {
			$this->send_error( __( 'No valid terms selected.', 'import-export-by-rockstarlab' ) );
		}

		$image_sources = $all_images;
		$image_map     = $this->upload_images_to_remote( array_values( $all_images ), $site );

		$response = $this->remote_post(
			$site,
			'receive-terms',
			array(
				'taxonomy'      => $taxonomy,
				'terms'         => $terms,
				'image_map'     => $image_map,
				'image_sources' => $image_sources,
			),
			60
		);

		if ( is_wp_error( $response ) ) {
			$this->send_error( $response->get_error_message() );
		}

		$this->send_success(
			array(
				'message' => isset( $response['message'] ) ? $response['message'] : __( 'Terms pushed successfully.', 'import-export-by-rockstarlab' ),
				'created' => isset( $response['created'] ) ? (int) $response['created'] : 0,
				'updated' => isset( $response['updated'] ) ? (int) $response['updated'] : 0,
				'failed'  => isset( $response['failed'] ) ? (int) $response['failed'] : 0,
			)
		);
	}

	/**
	 * Pull selected taxonomy terms from a remote site into this site.
	 */
	public function pull_terms() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$site_id  = $this->get_request_param( 'site_id', 0 );
		$taxonomy = sanitize_key( (string) $this->get_request_param( 'taxonomy', '' ) );
		$term_ids = $this->get_request_array( 'term_ids', array() );
		$mapping  = $this->get_request_array( 'term_mapping', array() );

		if ( empty( $site_id ) ) {
			$this->send_error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		if ( '' === $taxonomy || ! taxonomy_exists( $taxonomy ) ) {
			$this->send_error( __( 'Invalid taxonomy.', 'import-export-by-rockstarlab' ) );
		}

		$term_ids = array_values( array_filter( array_unique( array_map( 'absint', $term_ids ) ) ) );
		if ( empty( $term_ids ) ) {
			$this->send_error( __( 'No remote terms selected.', 'import-export-by-rockstarlab' ) );
		}

		$term_mapping = array();
		foreach ( $mapping as $remote_id => $local_id ) {
			$remote_id = absint( $remote_id );
			$local_id  = absint( $local_id );
			if ( $remote_id > 0 && $local_id > 0 ) {
				$term_mapping[ $remote_id ] = $local_id;
			}
		}

		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			$this->send_error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}

		$response = $this->remote_post(
			$site,
			'send-terms',
			array(
				'taxonomy' => $taxonomy,
				'term_ids' => $term_ids,
			),
			60
		);

		if ( is_wp_error( $response ) ) {
			$this->send_error( $response->get_error_message() );
		}

		$terms = isset( $response['terms'] ) && is_array( $response['terms'] ) ? $response['terms'] : array();
		if ( empty( $terms ) ) {
			$this->send_error( __( 'Remote site returned no terms.', 'import-export-by-rockstarlab' ) );
		}

		$remote_images = isset( $response['images'] ) && is_array( $response['images'] ) ? $response['images'] : array();
		$image_map     = $this->download_remote_images_for_sync( $remote_images, $site );
		$terms         = $this->replace_term_acf_media_references( $terms, $site['remote_url'], get_site_url(), $image_map, $remote_images );

		$result = $this->import_synced_terms( $taxonomy, $terms, $term_mapping );
		$this->send_success(
			array(
				'message' => sprintf(
					/* translators: 1: created terms, 2: updated terms. */
					__( 'Terms pulled. Created: %1$d, Updated: %2$d', 'import-export-by-rockstarlab' ),
					$result['created'],
					$result['updated']
				),
				'created' => $result['created'],
				'updated' => $result['updated'],
				'failed'  => $result['failed'],
			)
		);
	}

	/**
	 * Get comments from a remote site for Browse dialogs.
	 */
	public function get_remote_comments() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$site_id      = $this->get_request_param( 'site_id', 0 );
		$comment_type = sanitize_key( (string) $this->get_request_param( 'comment_type', '' ) );
		$search       = sanitize_text_field( (string) $this->get_request_param( 'search', '' ) );
		$post_id      = absint( $this->get_request_param( 'post_id', 0 ) );
		$page         = absint( $this->get_request_param( 'page', 1 ) );
		$per_page     = absint( $this->get_request_param( 'per_page', 20 ) );
		$language     = sanitize_key( (string) $this->get_request_param( 'language', '' ) );
		if ( '' === $language ) {
			$language = $this->get_admin_wpml_language();
		}

		if ( empty( $site_id ) ) {
			$this->send_error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			$this->send_error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}

		$response = $this->remote_post(
			$site,
			'list-comments',
			array(
				'comment_type' => $comment_type,
				'search'       => $search,
				'post_id'      => $post_id,
				'page'         => max( 1, $page ),
				'per_page'     => min( max( 1, $per_page ), 100 ),
				'language'     => $language,
			),
			30
		);

		if ( is_wp_error( $response ) ) {
			$this->send_error( $response->get_error_message() );
		}

		$this->send_success( $response );
	}

	/**
	 * Push selected local comments to a remote site.
	 */
	public function push_comments() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$site_id        = $this->get_request_param( 'site_id', 0 );
		$comment_ids    = array_values( array_filter( array_unique( array_map( 'absint', $this->get_request_array( 'comment_ids', array() ) ) ) ) );
		$target_post_id = absint( $this->get_request_param( 'target_post_id', 0 ) );
		$comment_type   = sanitize_key( (string) $this->get_request_param( 'comment_type', '' ) );

		if ( empty( $site_id ) ) {
			$this->send_error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		if ( empty( $comment_ids ) ) {
			$this->send_error( __( 'No comments selected.', 'import-export-by-rockstarlab' ) );
		}

		if ( $target_post_id <= 0 ) {
			$this->send_error( __( 'Please select a destination post on the remote site before pushing comments.', 'import-export-by-rockstarlab' ) );
		}

		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			$this->send_error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}

		$comments = array();
		$images   = array();
		foreach ( $comment_ids as $comment_id ) {
			$comment = get_comment( $comment_id );
			if ( $comment instanceof \WP_Comment ) {
				if ( ! $this->is_comment_in_sync_context( $comment, $comment_type ) ) {
					continue;
				}

				$comment_data = $this->prepare_standalone_comment_for_sync( $comment );
				$comments[]   = $comment_data;
				$this->collect_comment_acf_images_for_sync( $comment_data, $images );
			}
		}

		if ( empty( $comments ) ) {
			$this->send_error( __( 'No valid comments selected.', 'import-export-by-rockstarlab' ) );
		}

		$image_sources = $images;
		$image_map     = $this->upload_images_to_remote( array_values( $images ), $site );
		$comments      = $this->replace_comment_acf_media_references( $comments, get_site_url(), $site['remote_url'], $image_map, $image_sources );

		$result = $this->remote_post(
			$site,
			'receive-comments',
			array(
				'comments'       => $comments,
				'image_map'      => $image_map,
				'image_sources'  => $image_sources,
				'target_post_id' => $target_post_id,
			),
			60
		);

		if ( is_wp_error( $result ) ) {
			$this->send_error( $result->get_error_message() );
		}

		$this->send_success(
			array(
				'message' => isset( $result['message'] ) ? $result['message'] : __( 'Comments pushed successfully.', 'import-export-by-rockstarlab' ),
				'created' => isset( $result['created'] ) ? (int) $result['created'] : 0,
				'updated' => isset( $result['updated'] ) ? (int) $result['updated'] : 0,
				'failed'  => isset( $result['failed'] ) ? (int) $result['failed'] : 0,
			)
		);
	}

	/**
	 * Pull selected comments from a remote site into this site.
	 */
	public function pull_comments() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$site_id        = $this->get_request_param( 'site_id', 0 );
		$comment_ids    = array_values( array_filter( array_unique( array_map( 'absint', $this->get_request_array( 'comment_ids', array() ) ) ) ) );
		$target_post_id = absint( $this->get_request_param( 'target_post_id', 0 ) );
		$post_mapping   = $this->get_request_param( 'post_mapping', array() );
		$comment_type   = sanitize_key( (string) $this->get_request_param( 'comment_type', '' ) );
		$language       = sanitize_key( (string) $this->get_request_param( 'language', '' ) );
		if ( '' === $language ) {
			$language = $this->get_admin_wpml_language();
		}

		if ( is_string( $post_mapping ) ) {
			$post_mapping = json_decode( $post_mapping, true );
		}
		if ( ! is_array( $post_mapping ) ) {
			$post_mapping = array();
		}

		if ( empty( $site_id ) ) {
			$this->send_error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		if ( empty( $comment_ids ) ) {
			$this->send_error( __( 'No remote comments selected.', 'import-export-by-rockstarlab' ) );
		}

		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			$this->send_error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}

		$response = $this->remote_post(
			$site,
			'send-comments',
			array(
				'comment_ids'  => $comment_ids,
				'comment_type' => $comment_type,
				'language'     => $language,
			),
			60
		);

		if ( is_wp_error( $response ) ) {
			$this->send_error( $response->get_error_message() );
		}

		$comments      = isset( $response['comments'] ) && is_array( $response['comments'] ) ? $response['comments'] : array();
		$remote_images = isset( $response['images'] ) && is_array( $response['images'] ) ? $response['images'] : array();
		$image_map     = $this->download_remote_images_for_sync( $remote_images, $site );
		$comments      = $this->replace_comment_acf_media_references( $comments, $site['remote_url'], get_site_url(), $image_map, $remote_images );
		$result        = $this->import_standalone_synced_comments( $comments, $post_mapping, $target_post_id );

		$message = sprintf(
			/* translators: 1: created comments, 2: updated comments, 3: failed comments. */
			__( 'Comments pulled. Created: %1$d, Updated: %2$d, Failed: %3$d', 'import-export-by-rockstarlab' ),
			$result['created'],
			$result['updated'],
			$result['failed']
		);

		if ( ! empty( $result['errors'] ) ) {
			$message .= ' ' . reset( $result['errors'] );
		}

		$this->send_success(
			array(
				'message' => $message,
				'created' => $result['created'],
				'updated' => $result['updated'],
				'failed'  => $result['failed'],
				'errors'  => $result['errors'],
			)
		);
	}

	/**
	 * POST to a connected site's Content Sync REST API and decode response.
	 *
	 * @param array  $site Connected site record.
	 * @param string $endpoint Remote endpoint without namespace.
	 * @param array  $payload JSON payload.
	 * @param int    $timeout Request timeout.
	 * @return array|\WP_Error
	 */
	private function remote_post( $site, $endpoint, $payload, $timeout = 30 ) {
		$response = \RockStarLab\ImportExport\Helper\Remote_API::post(
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
			return new \WP_Error( 'rsl_ie_remote_error', __( 'Failed to connect to remote site: ', 'import-export-by-rockstarlab' ) . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );
		$data        = json_decode( $body, true );

		if ( 200 !== (int) $status_code ) {
			$message = $this->get_remote_error_message( $data, $status_code );
			return new \WP_Error( 'rsl_ie_remote_status', $message );
		}

		if ( ! is_array( $data ) || empty( $data['success'] ) ) {
			return new \WP_Error( 'rsl_ie_remote_invalid', __( 'Remote site returned invalid data.', 'import-export-by-rockstarlab' ) );
		}

		return $data;
	}

	/**
	 * Extract a readable message from a remote REST error response.
	 *
	 * @param mixed $data Remote decoded response.
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

			if ( ! empty( $data['code'] ) && is_scalar( $data['code'] ) ) {
				return (string) $data['code'];
			}
		}

		return sprintf(
			/* translators: %d: HTTP status code. */
			__( 'Request failed with status code: %d', 'import-export-by-rockstarlab' ),
			(int) $status_code
		);
	}

	/**
	 * Build a local-to-remote post map by exact title and post type.
	 */
	public function auto_map_by_title() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$site_id  = $this->get_request_param( 'site_id', 0 );
		$post_ids = $this->get_request_array( 'post_ids', array() );

		if ( empty( $site_id ) ) {
			$this->send_error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			$this->send_error( __( 'No posts selected', 'import-export-by-rockstarlab' ) );
		}

		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			$this->send_error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}

		$mapping = array();
		$matches = array();

		foreach ( $post_ids as $post_id ) {
			$post_id = absint( $post_id );
			if ( ! $post_id ) {
				continue;
			}

			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			$remote_post = $this->find_remote_post_by_exact_title( $site, $post );
			if ( ! $remote_post ) {
				$mapping[ $post_id ] = null;
				continue;
			}

			$remote_id = isset( $remote_post['ID'] ) ? absint( $remote_post['ID'] ) : 0;
			if ( ! $remote_id ) {
				$mapping[ $post_id ] = null;
				continue;
			}

			$mapping[ $post_id ] = $remote_id;
			$matches[ $post_id ] = array(
				'ID'            => $remote_id,
				'post_title'    => isset( $remote_post['post_title'] ) ? sanitize_text_field( $remote_post['post_title'] ) : '',
				'post_type'     => isset( $remote_post['post_type'] ) ? sanitize_key( $remote_post['post_type'] ) : '',
				'post_date'     => isset( $remote_post['post_date'] ) ? sanitize_text_field( $remote_post['post_date'] ) : '',
				'post_modified' => isset( $remote_post['post_modified'] ) ? sanitize_text_field( $remote_post['post_modified'] ) : '',
			);
		}

		$this->send_success(
			array(
				'mapping' => $mapping,
				'matches' => $matches,
			)
		);
	}

	/**
	 * Find a matching remote post by exact normalized title and post type.
	 *
	 * @param array    $site Connected site data.
	 * @param \WP_Post $post Local post object.
	 * @return array|null Matching remote post data, or null.
	 */
	private function find_remote_post_by_exact_title( $site, $post ) {
		$response = \RockStarLab\ImportExport\Helper\Remote_API::post(
			$site['remote_url'],
			'list-posts',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $site['api_key'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'post_type' => $post->post_type,
						'search'    => $post->post_title,
						'status'    => '',
						'page'      => 1,
						'per_page'  => 50,
					)
				),
			)
		);

		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		if ( ! is_array( $data ) || empty( $data['success'] ) || empty( $data['posts'] ) || ! is_array( $data['posts'] ) ) {
			return null;
		}

		$local_title = $this->normalize_post_title_for_mapping( $post->post_title );

		foreach ( $data['posts'] as $remote_post ) {
			if ( ! is_array( $remote_post ) ) {
				continue;
			}

			$remote_type  = isset( $remote_post['post_type'] ) ? sanitize_key( $remote_post['post_type'] ) : '';
			$remote_title = isset( $remote_post['post_title'] ) ? $this->normalize_post_title_for_mapping( $remote_post['post_title'] ) : '';

			if ( $remote_type === $post->post_type && $remote_title === $local_title ) {
				return $remote_post;
			}
		}

		return null;
	}

	/**
	 * Normalize titles before exact matching.
	 *
	 * @param string $title Post title.
	 * @return string Normalized title.
	 */
	private function normalize_post_title_for_mapping( $title ) {
		$title = html_entity_decode( wp_strip_all_tags( (string) $title ), ENT_QUOTES, get_bloginfo( 'charset' ) );
		$title = preg_replace( '/\s+/u', ' ', trim( $title ) );

		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $title, get_bloginfo( 'charset' ) ) : strtolower( $title );
	}

	/**
	 * Get children posts from remote site
	 */
	public function get_children_posts() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$site_id   = $this->get_request_param( 'site_id', 0 );
		$parent_id = $this->get_request_param( 'parent_id', 0 );
		$post_type = $this->get_request_param( 'post_type', '' );
		$language  = sanitize_key( (string) $this->get_request_param( 'language', '' ) );

		// Validate input
		if ( empty( $site_id ) ) {
			$this->send_error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		if ( empty( $parent_id ) ) {
			$this->send_error( __( 'Parent ID is required', 'import-export-by-rockstarlab' ) );
		}

		// Get site details
		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			$this->send_error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}

			// Request children posts from remote site
			$response = \RockStarLab\ImportExport\Helper\Remote_API::post(
				$site['remote_url'],
				'get-children-posts',
				array(
					'timeout' => 30,
					'headers' => array(
						'Authorization' => 'Bearer ' . $site['api_key'],
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode(
						array(
							'parent_id' => $parent_id,
							'post_type' => $post_type,
							'language'  => $language,
						)
					),
				// translators: %s = content placeholder.
				)
			);

		if ( is_wp_error( $response ) ) {
			$this->send_error( __( 'Failed to connect to remote site: ', 'import-export-by-rockstarlab' ) . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( $status_code !== 200 ) {
			$error_data = json_decode( $body, true );
			// translators: %d is a dynamic value.
			$error_msg = isset( $error_data['message'] ) ? $error_data['message'] : sprintf( __( 'Request failed with status code: %d', 'import-export-by-rockstarlab' ), $status_code );
			$this->send_error( $error_msg );
		}

		$data = json_decode( $body, true );
		if ( ! isset( $data['success'] ) || ! $data['success'] || ! isset( $data['children'] ) ) {
			$this->send_error( __( 'Remote site returned invalid data', 'import-export-by-rockstarlab' ) );
		}

		$this->send_success(
			array(
				'children' => $data['children'],
			)
		);
	}

	/**
	 * Get local posts info
	 */
	public function get_local_posts_info() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$post_ids = $this->get_request_array( 'post_ids', array() );

		// Validate input
		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			$this->send_error( __( 'Post IDs are required', 'import-export-by-rockstarlab' ) );
		}

		$posts_info = array();

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$orig         = get_post_meta( $post->ID, '_rsl_ie_original_post_id', true );
				$posts_info[] = array(
					'ID'          => $post->ID,
					'post_title'  => $post->post_title,
					'post_type'   => $post->post_type,
					'post_date'   => $post->post_date,
					'post_status' => $post->post_status,
					'original_id' => is_numeric( $orig ) ? (int) $orig : 0,
				);
			}
		}

		$this->send_success(
			array(
				'posts' => $posts_info,
			)
		);
	}

	/**
	 * Push content to remote site
	 */
	public function push_content() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$site_id      = $this->get_request_param( 'site_id', 0 );
		$post_ids     = $this->get_request_array( 'post_ids', array() );
		$post_mapping = $this->get_request_param( 'post_mapping', array() );

		// Validate input
		if ( empty( $site_id ) ) {
			$this->send_error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			$this->send_error( __( 'No posts selected', 'import-export-by-rockstarlab' ) );
		}

		// Parse post_mapping if it's a JSON string
		if ( is_string( $post_mapping ) ) {
			$post_mapping = json_decode( $post_mapping, true );
		}
		if ( ! is_array( $post_mapping ) ) {
			$post_mapping = array();
		}

		// Get site details
		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			$this->send_error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}

		// Get source and target domains
		$source_domain = get_site_url();
		$target_domain = $site['remote_url'];

		// Prepare posts data with images
		$posts_data    = array();
		$all_images    = array();
		$image_context = array(); // Track which post each image belongs to

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( ! $post ) {
				continue;
			}

			// Extract all images from post
			$post_images = \RockStarLab\ImportExport\Helper\Content_Sync_Media::extract_post_images( $post_id );

			// Store images with post context
			foreach ( $post_images as $image ) {
				$image_key                     = $image['attachment_id'];
				$all_images[ $image_key ]      = $image;
				$image_context[ $image_key ][] = $post_id;
			}

			// Get post meta
			$meta           = get_post_meta( $post_id );
			$prepared_meta  = array();
			$skip_meta_keys = array(
				'_edit_lock',
				'_edit_last',
				'_wp_old_slug',
				'_wp_old_date',
				// Internal Content Sync meta: must never be pushed to remote, otherwise it
				// overwrites the receiving site's own "_rsl_ie_original_post_id" mapping.
				'_rsl_ie_original_post_id',
			);
			foreach ( $meta as $key => $values ) {
				if ( in_array( $key, $skip_meta_keys, true ) || $this->should_skip_synced_meta_key( (string) $key ) ) {
					continue;
				}
				$prepared_meta[ $key ] = maybe_unserialize( $values[0] );
			}

			// Get post terms with ACF fields.
			// All taxonomies are included — even empty ones — so the receiving site
			// can clear any stale term assignments it already has for those taxonomies.
			$taxonomies = get_object_taxonomies( $post->post_type );
			$terms_data = array();
			foreach ( $taxonomies as $taxonomy ) {
				$terms                   = wp_get_post_terms( $post_id, $taxonomy );
				$terms_data[ $taxonomy ] = array(); // always initialise, even when empty
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					foreach ( $terms as $term ) {
						$term_info = array(
							'term_id'        => $term->term_id,
							'name'           => $term->name,
							'slug'           => $term->slug,
							'parent_term_id' => (int) $term->parent,
							'parent_slug'    => $this->get_term_slug_by_id( (int) $term->parent, $taxonomy ),
							'parent_path'    => $this->get_term_parent_path( (int) $term->parent, $taxonomy ),
						);
						$this->append_wpml_term_sync_data( $term_info, (int) $term->term_id, $taxonomy );

						// Get ACF fields for this term
						if ( function_exists( 'get_field_objects' ) ) {
							$acf_fields = $this->get_term_acf_field_objects( (int) $term->term_id, $taxonomy );
							if ( $acf_fields ) {
								$term_info['acf'] = array();
								foreach ( $acf_fields as $field_key => $field ) {
									$field_name                      = ! empty( $field['name'] ) ? (string) $field['name'] : (string) $field_key;
									$term_info['acf'][ $field_name ] = $this->export_term_acf_sync_value( (int) $term->term_id, $field_name, $field );
								}
							}
						}

						$terms_data[ $taxonomy ][] = $term_info;

						// Extract images from term ACF fields
						if ( ! empty( $term_info['acf'] ) ) {
							$term_images = $this->extract_term_acf_images( $term_info['acf'] );
							foreach ( $term_images as $image_id ) {
								if ( ! isset( $all_images[ $image_id ] ) ) {
									// Use prepare_image_data to include file_hash for proper dedup on receiving side.
									$image_data = \RockStarLab\ImportExport\Helper\Content_Sync_Media::prepare_image_data( $image_id, 'term_acf' );
									if ( ! $image_data ) {
										// Fallback if file is missing on disk.
										$image_data = array(
											'attachment_id' => $image_id,
											'url'  => wp_get_attachment_url( $image_id ),
											'type' => 'term_acf',
										);
									}
									$image_data['term_id']        = $term->term_id;
									$image_data['taxonomy']       = $taxonomy;
									$all_images[ $image_id ]      = $image_data;
									$image_context[ $image_id ][] = 'term_' . $term->term_id;
								}
							}
						}
					}
				} // end foreach $terms
			} // end if not empty

			// Augment $terms_data with terms referenced inside ACF taxonomy fields.
			// ACF's "save_terms" option defaults to disabled, meaning term IDs are stored
			// only in post_meta and never appear in wp_term_relationships / wp_get_post_terms.
			// Without this augmentation, those term IDs have no name/slug information on
			// the target site and the translator cannot map them.
			if ( function_exists( 'acf_get_field' ) ) {
				foreach ( $prepared_meta as $meta_key => $meta_value ) {
					if ( strpos( $meta_key, '_' ) === 0 ) {
						continue;
					}
					$field_ref_key = '_' . $meta_key;
					if ( ! isset( $prepared_meta[ $field_ref_key ] ) ) {
						continue;
					}
					$field_ref = $prepared_meta[ $field_ref_key ];
					if ( ! is_string( $field_ref ) || strpos( $field_ref, 'field_' ) !== 0 ) {
						continue;
					}
					$field_obj = acf_get_field( $field_ref );
					if ( ! $field_obj || ! isset( $field_obj['type'] ) || $field_obj['type'] !== 'taxonomy' ) {
						continue;
					}
					$acf_taxonomy = isset( $field_obj['taxonomy'] ) ? $field_obj['taxonomy'] : '';
					if ( ! $acf_taxonomy || ! taxonomy_exists( $acf_taxonomy ) ) {
						continue;
					}
					$raw_ids = is_array( $meta_value ) ? $meta_value : array( $meta_value );
					if ( ! isset( $terms_data[ $acf_taxonomy ] ) ) {
						$terms_data[ $acf_taxonomy ] = array();
					}
					$known_ids = array_column( $terms_data[ $acf_taxonomy ], 'term_id' );
					foreach ( $raw_ids as $raw_id ) {
						if ( ! is_numeric( $raw_id ) || (int) $raw_id <= 0 ) {
							continue;
						}
						$raw_id = (int) $raw_id;
						if ( in_array( $raw_id, $known_ids, true ) ) {
							continue;
						}
						$term = get_term( $raw_id, $acf_taxonomy );
						if ( ! $term || is_wp_error( $term ) ) {
							continue;
						}
								$terms_data[ $acf_taxonomy ][] = array(
									'term_id'        => $term->term_id,
									'name'           => $term->name,
									'slug'           => $term->slug,
									'parent_term_id' => (int) $term->parent,
									'parent_slug'    => $this->get_term_slug_by_id( (int) $term->parent, $acf_taxonomy ),
									'parent_path'    => $this->get_term_parent_path( (int) $term->parent, $acf_taxonomy ),
								);
								$last_index                    = array_key_last( $terms_data[ $acf_taxonomy ] );
								if ( null !== $last_index ) {
									$this->append_wpml_term_sync_data( $terms_data[ $acf_taxonomy ][ $last_index ], (int) $term->term_id, $acf_taxonomy );
								}
								$known_ids[] = $raw_id;
					}
				}
			}

				$post_data = array(
					'ID'            => $post->ID,
					'post_title'    => $post->post_title,
					'post_content'  => $post->post_content,
					'post_excerpt'  => $post->post_excerpt,
					'post_status'   => $post->post_status,
					'post_type'     => $post->post_type,
					'post_parent'   => $post->post_parent,
					'post_name'     => $post->post_name,
					'post_date'     => $post->post_date,
					'post_modified' => $post->post_modified,
					'post_author'   => $post->post_author,
					'meta'          => $prepared_meta,
					'post_refs'     => \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::collect_acf_post_reference_map_from_meta( $prepared_meta ),
					'terms'         => $terms_data,
					'comments'      => $this->collect_post_comments_for_sync( $post->ID ),
				);
				$this->append_wpml_post_sync_data( $post_data, (int) $post->ID, (string) $post->post_type );

				if ( isset( $prepared_meta['repeater'] ) ) {
				}

				// Collect WooCommerce product variations for variable products.
				// Variations are separate posts (post_type=product_variation) and must be
				// synced together with their parent so the remote site can show the correct
				// price range.
				if ( 'product' === $post->post_type
				&& class_exists( 'WC_Product' )
				&& function_exists( 'wc_get_product' )
				) {
					$wc_product = wc_get_product( $post->ID );
					if ( $wc_product && $wc_product->is_type( 'variable' ) ) {
						$variation_ids   = $wc_product->get_children();
						$variations_data = array();

						foreach ( $variation_ids as $variation_id ) {
							$variation_post = get_post( $variation_id );
							if ( ! $variation_post ) {
								continue;
							}

							// Include variation images in the global image upload queue.
							$var_images = \RockStarLab\ImportExport\Helper\Content_Sync_Media::extract_post_images( $variation_id );
							foreach ( $var_images as $var_img ) {
								$var_img_key                     = $var_img['attachment_id'];
								$all_images[ $var_img_key ]      = $var_img;
								$image_context[ $var_img_key ][] = $post_id;
							}

							// Collect variation meta.
							$var_raw_meta  = get_post_meta( $variation_id );
							$var_prep_meta = array();
							foreach ( $var_raw_meta as $vk => $vv ) {
								$var_prep_meta[ $vk ] = maybe_unserialize( $vv[0] );
							}

							$variations_data[] = array(
								'ID'          => $variation_post->ID,
								'post_title'  => $variation_post->post_title,
								'post_name'   => $variation_post->post_name,
								'post_status' => $variation_post->post_status,
								'post_type'   => $variation_post->post_type,
								'menu_order'  => $variation_post->menu_order,
								'meta'        => $var_prep_meta,
							);
						}

						$post_data['variations'] = $variations_data;
					}

					// Collect WooCommerce grouped product children.
					// Children are regular `product` posts linked via _children meta.
					// We ship their data so the remote site can create/update them and
					// the _children meta can be remapped to correct local IDs.
					if ( $wc_product->is_type( 'grouped' ) ) {
						$child_ids     = $wc_product->get_children();
						$children_data = array();

						foreach ( $child_ids as $child_id ) {
							$child_post = get_post( $child_id );
							if ( ! $child_post ) {
								continue;
							}

							// Include child images in the global upload queue.
							$child_imgs = \RockStarLab\ImportExport\Helper\Content_Sync_Media::extract_post_images( $child_id );
							foreach ( $child_imgs as $child_img ) {
								$cimg_key                     = $child_img['attachment_id'];
								$all_images[ $cimg_key ]      = $child_img;
								$image_context[ $cimg_key ][] = $post_id;
							}

							// Collect child meta.
							$child_raw_meta  = get_post_meta( $child_id );
							$child_prep_meta = array();
							foreach ( $child_raw_meta as $ck => $cv ) {
								$child_prep_meta[ $ck ] = maybe_unserialize( $cv[0] );
							}

							// Collect child terms.
							$child_taxonomies = get_object_taxonomies( $child_post->post_type );
							$child_terms      = array();
							foreach ( $child_taxonomies as $child_tax ) {
								$c_terms                   = wp_get_post_terms( $child_id, $child_tax );
								$child_terms[ $child_tax ] = array();
								if ( ! is_wp_error( $c_terms ) ) {
									foreach ( $c_terms as $c_term ) {
										$child_terms[ $child_tax ][] = array(
											'term_id'     => $c_term->term_id,
											'name'        => $c_term->name,
											'slug'        => $c_term->slug,
											'parent_term_id' => (int) $c_term->parent,
											'parent_slug' => $this->get_term_slug_by_id( (int) $c_term->parent, $child_tax ),
											'parent_path' => $this->get_term_parent_path( (int) $c_term->parent, $child_tax ),
										);
									}
								}
							}

							$children_data[] = array(
								'ID'           => $child_post->ID,
								'post_title'   => $child_post->post_title,
								'post_name'    => $child_post->post_name,
								'post_content' => $child_post->post_content,
								'post_excerpt' => $child_post->post_excerpt,
								'post_status'  => $child_post->post_status,
								'post_type'    => $child_post->post_type,
								'menu_order'   => $child_post->menu_order,
								'meta'         => $child_prep_meta,
								'terms'        => $child_terms,
							);
						}

						$post_data['grouped_children'] = $children_data;
					}
				}

				$posts_data[] = $post_data;
		}

		if ( empty( $posts_data ) ) {
			$this->send_error( __( 'No valid posts to sync', 'import-export-by-rockstarlab' ) );
		}

		// Upload images to remote site first
		$image_sources = $all_images;
		$image_map     = $this->upload_images_to_remote( array_values( $all_images ), $site );

		// Replace domains in post data
		foreach ( $posts_data as &$post_data ) {
			$post_data = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::replace_post_domains(
				$post_data,
				$source_domain,
				$target_domain,
				$image_map,
				$image_sources
			);
		}

		// Send to remote site
		$response = \RockStarLab\ImportExport\Helper\Remote_API::post(
			$site['remote_url'],
			'receive-content',
			array(
				'timeout' => 180,
				'headers' => array(
					'Authorization' => 'Bearer ' . $site['api_key'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'posts'         => $posts_data,
						'image_map'     => $image_map,
						'image_sources' => $image_sources,
						'post_mapping'  => $post_mapping,
					// translators: %s = content placeholder.
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->send_error( __( 'Failed to connect to remote site: ', 'import-export-by-rockstarlab' ) . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( $status_code !== 200 ) {
			$error_data = json_decode( $body, true );
			// translators: %d is a dynamic value.
			$error_msg = isset( $error_data['message'] ) ? $error_data['message'] : sprintf( __( 'Push failed with status code: %d', 'import-export-by-rockstarlab' ), $status_code );
			$this->send_error( $error_msg );
		}

		$data = json_decode( $body, true );
		if ( ! isset( $data['success'] ) || ! $data['success'] ) {
			$this->send_error( __( 'Remote site rejected the content', 'import-export-by-rockstarlab' ) );
		}

		$this->send_success(
			array(
				'message'       => sprintf(
					/* translators: %d: number of posts */
					__( 'Successfully pushed %d post(s) to remote site', 'import-export-by-rockstarlab' ),
					count( $posts_data )
				),
				'images_synced' => count( $image_map ),
			)
		);
	}

	/**
	 * Upload images to remote site
	 *
	 * @param array $images Array of image data.
	 * @param array $site Site connection data.
	 * @return array Mapping of old attachment IDs to new ones
	 */
	private function upload_images_to_remote( $images, $site ) {
		$image_map = array();

		if ( empty( $images ) ) {
			return $image_map;
		}

		$hash_sources = array();
		foreach ( $images as $image ) {
			$source_attachment_id = isset( $image['attachment_id'] ) ? (int) $image['attachment_id'] : 0;
			$file_hash            = isset( $image['file_hash'] ) ? (string) $image['file_hash'] : '';
			$force_unique         = false;

			if ( $source_attachment_id > 0 && '' !== $file_hash ) {
				if ( isset( $hash_sources[ $file_hash ] ) && (int) $hash_sources[ $file_hash ] !== $source_attachment_id ) {
					$force_unique = true;
				} else {
					$hash_sources[ $file_hash ] = $source_attachment_id;
				}
			}

			if ( ! $force_unique ) {
				// Check if this exact source attachment, or a matching file hash,
				// already exists on the remote site.
				$existing_id = \RockStarLab\ImportExport\Helper\Content_Sync_Media::check_remote_image_exists(
					$file_hash,
					$site['remote_url'],
					$site['api_key'],
					$source_attachment_id
				);

				if ( $existing_id ) {
					$this->sync_existing_remote_image_language( $image, $site, $force_unique );
					$image_map[ $image['attachment_id'] ] = $existing_id;
					continue;
				}
			}

			// Upload new image
			$image['force_unique'] = $force_unique;
			$new_id                = $this->upload_single_image_to_remote( $image, $site );
			if ( $new_id ) {
				$image_map[ $image['attachment_id'] ] = $new_id;
			}
		}

		return $image_map;
	}

	/**
	 * Download remote image payloads and return source attachment ID => local ID map.
	 *
	 * @param array $remote_images Remote image payloads.
	 * @param array $site Connected site.
	 * @return array<int,int>
	 */
	private function download_remote_images_for_sync( $remote_images, $site ) {
		$image_map = array();
		if ( empty( $remote_images ) || ! is_array( $remote_images ) ) {
			return $image_map;
		}

		foreach ( $remote_images as $image ) {
			if ( ! is_array( $image ) || empty( $image['attachment_id'] ) ) {
				continue;
			}

			$new_attachment_id = $this->download_image_from_remote( $image, $site );
			if ( $new_attachment_id ) {
				$image_map[ (int) $image['attachment_id'] ] = (int) $new_attachment_id;
			}
		}

		return $image_map;
	}

	/**
	 * Upload single image to remote site
	 *
	 * @param array $image Image data.
	 * @param array $site Site connection data.
	 * @return int|false New attachment ID or false on failure
	 */
	private function upload_single_image_to_remote( $image, $site ) {
		// Read file contents
		$file_contents = false;
		if ( ! empty( $image['file_path'] ) ) {
			$file_contents = @file_get_contents( $image['file_path'] );
		} elseif ( ! empty( $image['url'] ) && wp_http_validate_url( $image['url'] ) ) {
			$response = wp_remote_get( $image['url'], array( 'timeout' => 30 ) );
			if ( ! is_wp_error( $response ) ) {
				$file_contents = wp_remote_retrieve_body( $response );
			}
		}

		if ( false === $file_contents ) {
			return false;
		}

		// Prepare image data for upload
		$upload_data = array(
			'file_name'            => $image['file_name'],
			'file_data'            => base64_encode( $file_contents ),
			'file_hash'            => $image['file_hash'],
			'mime_type'            => $image['mime_type'],
			'alt_text'             => $image['alt_text'],
			'title'                => $image['title'],
			'caption'              => $image['caption'],
			'description'          => $image['description'],
			'source_attachment_id' => isset( $image['attachment_id'] ) ? (int) $image['attachment_id'] : 0,
			'force_unique'         => ! empty( $image['force_unique'] ) ? 1 : 0,
			'wpml'                 => isset( $image['wpml'] ) && is_array( $image['wpml'] ) ? $image['wpml'] : array(),
		);

		// Upload to remote
		$response = \RockStarLab\ImportExport\Helper\Remote_API::post(
			$site['remote_url'],
			'upload-media',
			array(
				'timeout' => 180,
				'headers' => array(
					'Authorization' => 'Bearer ' . $site['api_key'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $upload_data ),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['success'] ) && $body['success'] && isset( $body['attachment_id'] ) ) {
			return (int) $body['attachment_id'];
		}

		return false;
	}

	/**
	 * Extract external Elementor media references from pulled post payloads.
	 *
	 * This is a target-side fallback for older/source sites that do not include
	 * Elementor template media (notably SVG icon controls) in the normal images
	 * payload. The returned attachment_id is the source/builder ID so the regular
	 * image map can rewrite Elementor `{id,url}` controls after download.
	 *
	 * @param array $posts_data Remote post payloads.
	 * @return array Media items keyed by source/builder attachment ID or URL hash.
	 */
	private function extract_elementor_external_media_from_posts( $posts_data ) {
		$images = array();

		if ( empty( $posts_data ) || ! is_array( $posts_data ) ) {
			return $images;
		}

		foreach ( $posts_data as $post_data ) {
			if ( empty( $post_data['meta']['_elementor_data'] ) || ! is_string( $post_data['meta']['_elementor_data'] ) ) {
				continue;
			}

			$elementor_data = json_decode( $post_data['meta']['_elementor_data'], true );
			if ( ! is_array( $elementor_data ) ) {
				continue;
			}

			$this->collect_elementor_external_media( $elementor_data, $images );
		}

		return $images;
	}

	/**
	 * Recursively collect Elementor media URL controls.
	 *
	 * @param mixed $value  Elementor data node.
	 * @param array $images Collected media items.
	 * @return void
	 */
	private function collect_elementor_external_media( $value, &$images ) {
		if ( ! is_array( $value ) ) {
			return;
		}

		if ( isset( $value['url'] ) && is_string( $value['url'] ) && $this->is_supported_sync_media_url( $value['url'] ) ) {
			$source_attachment_id = isset( $value['id'] ) && is_numeric( $value['id'] ) ? absint( $value['id'] ) : 0;
			$key                  = $source_attachment_id > 0 ? (string) $source_attachment_id : md5( $value['url'] );

			if ( ! isset( $images[ $key ] ) ) {
				$url      = esc_url_raw( $value['url'] );
				$path     = (string) wp_parse_url( $url, PHP_URL_PATH );
				$filename = sanitize_file_name( wp_basename( $path ) );
				$ext      = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );
				$filetype = wp_check_filetype( $filename );
				$mime     = ! empty( $filetype['type'] ) ? $filetype['type'] : '';
				if ( '' === $mime && 'svg' === $ext ) {
					$mime = 'image/svg+xml';
				}

					$images[ $key ] = array(
						'attachment_id' => $source_attachment_id,
						'url'           => $url,
						'source_urls'   => array(
							'full'    => $url,
							'by_size' => array(),
						),
						'file_path'     => '',
						'file_name'     => '' !== $filename ? $filename : 'elementor-media.' . ( '' !== $ext ? $ext : 'bin' ),
						'file_hash'     => '',
						'file_size'     => 0,
						'mime_type'     => $mime,
						'alt_text'      => '',
						'title'         => '' !== $filename ? preg_replace( '/\.[^.]+$/', '', $filename ) : '',
						'caption'       => '',
						'description'   => '',
						'context'       => 'elementor_external_url',
						'metadata'      => array(),
					);
			}
		}

		foreach ( $value as $child ) {
			if ( is_array( $child ) ) {
				$this->collect_elementor_external_media( $child, $images );
			}
		}
	}

		/**
		 * Re-apply WPML language metadata to an already-existing remote media item.
		 *
		 * @param array $image        Image data.
		 * @param array $site         Site connection data.
		 * @param bool  $force_unique Whether force-unique mode is enabled.
		 * @return void
		 */
	private function sync_existing_remote_image_language( array $image, array $site, $force_unique = false ) {
		if ( empty( $image['wpml'] ) || empty( $image['file_path'] ) ) {
			return;
		}

		$image['force_unique'] = $force_unique;
		$this->upload_single_image_to_remote( $image, $site );
	}

		/**
		 * Check whether a URL points to a media file Content Sync can import.
		 *
		 * @param string $url Media URL.
		 * @return bool Whether the URL is supported.
		 */
	private function is_supported_sync_media_url( $url ) {
		$url = esc_url_raw( $url );
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return false;
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );

		return in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg' ), true );
	}

	/**
	 * Check whether a post meta key should be excluded from Content Sync payloads.
	 *
	 * @param string $key Meta key.
	 * @return bool Whether the key should be skipped.
	 */
	private function should_skip_synced_meta_key( $key ) {
		$key = (string) $key;

		if ( 0 === strpos( $key, '_icl_' ) || 0 === strpos( $key, '_wpml_' ) ) {
			return true;
		}

		return class_exists( '\RockStarLab\ImportExport\Helper\Elementor_Fields' )
			&& \RockStarLab\ImportExport\Helper\Elementor_Fields::is_generated_cache_key( $key );
	}

	/**
	 * Save synced post meta with special handling for generated/builder metadata.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Meta value.
	 * @return void
	 */
	private function save_synced_post_meta( $post_id, $key, $value ) {
		if ( $this->should_skip_synced_meta_key( $key ) ) {
			delete_post_meta( $post_id, $key );
			return;
		}

		if ( class_exists( '\RockStarLab\ImportExport\Helper\Elementor_Fields' )
			&& \RockStarLab\ImportExport\Helper\Elementor_Fields::is_elementor_meta_key( $key ) ) {
			\RockStarLab\ImportExport\Helper\Elementor_Fields::import_meta_value( (int) $post_id, $key, $value, true, false );
			return;
		}

		update_post_meta( $post_id, $key, $value );
	}

	/**
	 * Pull content from remote site
	 */
	public function pull_content() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$site_id      = $this->get_request_param( 'site_id', 0 );
		$post_ids     = $this->get_request_array( 'post_ids', array() );
		$post_mapping = $this->get_request_param( 'post_mapping', array() );

		// Validate input
		if ( empty( $site_id ) ) {
			$this->send_error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			$this->send_error( __( 'No posts selected', 'import-export-by-rockstarlab' ) );
		}

		// Parse post_mapping if it's a JSON string
		if ( is_string( $post_mapping ) ) {
			$post_mapping = json_decode( $post_mapping, true );
		}
		if ( ! is_array( $post_mapping ) ) {
			$post_mapping = array();
		}

		if ( ! empty( $post_mapping ) ) {
			$mapped_remote_ids = array();
			foreach ( $post_mapping as $remote_id ) {
				if ( is_numeric( $remote_id ) && (int) $remote_id > 0 ) {
					$mapped_remote_ids[] = (int) $remote_id;
				}
			}

			if ( ! empty( $mapped_remote_ids ) ) {
				$post_ids = array_values( array_unique( $mapped_remote_ids ) );
			}
		}

		// Get site details
		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			$this->send_error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}

		// Get domains for replacement
		$source_domain = wp_parse_url( $site['remote_url'], PHP_URL_HOST );
		$target_domain = wp_parse_url( home_url(), PHP_URL_HOST );

			// Request content from remote site
			$response = \RockStarLab\ImportExport\Helper\Remote_API::post(
				$site['remote_url'],
				'send-content',
				array(
					'timeout' => 180,
					'headers' => array(
						'Authorization' => 'Bearer ' . $site['api_key'],
						'Content-Type'  => 'application/json',
					),
					'body'    => wp_json_encode(
						// translators: %s = content placeholder.
						array(
							'post_ids' => $post_ids,
						)
					),
				)
			);

		if ( is_wp_error( $response ) ) {
			$this->send_error( __( 'Failed to connect to remote site: ', 'import-export-by-rockstarlab' ) . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( $status_code !== 200 ) {
			$error_data = json_decode( $body, true );
			// translators: %d is a dynamic value.
			$error_msg = isset( $error_data['message'] ) ? $error_data['message'] : sprintf( __( 'Pull failed with status code: %d', 'import-export-by-rockstarlab' ), $status_code );
			$this->send_error( $error_msg );
		}

		$data = json_decode( $body, true );
		if ( ! isset( $data['success'] ) || ! $data['success'] || ! isset( $data['data']['posts'] ) ) {
			$this->send_error( __( 'Remote site returned invalid data', 'import-export-by-rockstarlab' ) );
		}

		$posts_data = $data['data']['posts'];
		if ( empty( $posts_data ) ) {
			$this->send_error( __( 'No posts found on remote site', 'import-export-by-rockstarlab' ) );
		}

		// Get images from remote
		$remote_images = isset( $data['data']['images'] ) ? $data['data']['images'] : array();

		// Download images from remote site
		$image_map = array();
		if ( ! empty( $remote_images ) ) {
			$hash_sources = array();
			foreach ( $remote_images as $image ) {
				$source_attachment_id = isset( $image['attachment_id'] ) ? (int) $image['attachment_id'] : 0;
				$file_hash            = isset( $image['file_hash'] ) ? (string) $image['file_hash'] : '';
				if ( $source_attachment_id > 0 && '' !== $file_hash ) {
					if ( isset( $hash_sources[ $file_hash ] ) && (int) $hash_sources[ $file_hash ] !== $source_attachment_id ) {
						$image['force_unique'] = true;
					} else {
						$hash_sources[ $file_hash ] = $source_attachment_id;
					}
				}

				$new_attachment_id = $this->download_image_from_remote( $image, $site );
				if ( $new_attachment_id ) {
					$image_map[ $image['attachment_id'] ] = $new_attachment_id;
				} else {
				}
			}
		}

		$elementor_fallback_images = $this->extract_elementor_external_media_from_posts( $posts_data );
		if ( ! empty( $elementor_fallback_images ) ) {
			foreach ( $elementor_fallback_images as $image ) {
				$source_attachment_id = isset( $image['attachment_id'] ) ? (int) $image['attachment_id'] : 0;
				if ( $source_attachment_id > 0 && isset( $image_map[ $source_attachment_id ] ) ) {
					continue;
				}

				$new_attachment_id = $this->download_image_from_remote( $image, $site );
				if ( $new_attachment_id && $source_attachment_id > 0 ) {
					$image_map[ $source_attachment_id ] = $new_attachment_id;
				}
			}
		}

		// Replace domains and image IDs in post data
		foreach ( $posts_data as &$post_data ) {
			$post_data = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::replace_post_domains(
				$post_data,
				$source_domain,
				$target_domain,
				$image_map,
				$remote_images
			);
		}
		unset( $post_data ); // Break the reference to avoid bugs in the next foreach loop

		// Import posts
		$imported_count           = 0;
		$updated_count            = 0;
		$imported_remote_to_local = array();
		$imported_remote_parent   = array();
		$imported_remote_type     = array();
		$product_post_ids         = array();

		// JS sends post_mapping as { local_id: remote_id }, but here we need
		// to look up by REMOTE id (the ID coming from the remote post data).
		// Build a reversed map { remote_id: local_id } for fast lookup.
		$remote_to_local_map = array();
		foreach ( $post_mapping as $local_id => $remote_id ) {
			if ( $remote_id !== null && is_numeric( $remote_id ) && $remote_id > 0 ) {
				$remote_to_local_map[ intval( $remote_id ) ] = intval( $local_id );
			}
		}

		foreach ( $posts_data as $post_data ) {
			$remote_post_id = $post_data['ID'];
			$local_post_id  = null;
			$remote_parent  = array_key_exists( 'post_parent', $post_data ) ? (int) $post_data['post_parent'] : null;

			// Check post mapping (remote → local)
			if ( isset( $remote_to_local_map[ $remote_post_id ] ) ) {
				$mapped_value = $remote_to_local_map[ $remote_post_id ];

				// If mapped to specific ID, use it
				if ( $mapped_value > 0 ) {
					$local_post_id = $mapped_value;
				}
				// null / 0 means "new", but a grouped child may already have
				// been created earlier in this same sync.
				if ( ! $local_post_id ) {
					$local_post_id = $this->find_existing_post_by_original_id( $remote_post_id );
				}
			} else {
				// No mapping provided, use default logic (find by meta)
				$local_post_id = $this->find_existing_post_by_original_id( $remote_post_id );
			}

			// Prepare post data
			$post_args = array(
				'post_title'   => $post_data['post_title'],
				'post_content' => $post_data['post_content'],
				'post_excerpt' => $post_data['post_excerpt'],
				'post_status'  => $post_data['post_status'],
				'post_type'    => $post_data['post_type'],
				'post_name'    => $post_data['post_name'],
				'post_date'    => $post_data['post_date'],
				'post_author'  => get_current_user_id(),
			);

			if ( $local_post_id ) {
				// Update existing post
				$post_args['ID'] = $local_post_id;
				$post_id         = wp_update_post( $post_args, true ); // true to get WP_Error on failure
				if ( ! is_wp_error( $post_id ) && $post_id ) {
					++$updated_count;
				}
			} else {
				// Create new post
				$post_id = wp_insert_post( $post_args, true ); // true to get WP_Error on failure
				if ( ! is_wp_error( $post_id ) && $post_id ) {
					++$imported_count;
				}
			}

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			// Track remote → local ID mapping for this pull so we can fix parent/child
			// relationships after all posts are created/updated.
			$imported_remote_to_local[ (int) $remote_post_id ] = (int) $post_id;
			$imported_remote_parent[ (int) $remote_post_id ]   = $remote_parent;
			$imported_remote_type[ (int) $remote_post_id ]     = isset( $post_data['post_type'] ) ? (string) $post_data['post_type'] : '';
			if ( isset( $post_data['post_type'] ) && 'product' === $post_data['post_type'] ) {
				$product_post_ids[] = (int) $post_id;
			}
			$this->apply_synced_post_wpml_data( (int) $post_id, (array) $post_data, $imported_remote_to_local );

			// Store original post ID for future reference
			update_post_meta( $post_id, '_rsl_ie_original_post_id', $remote_post_id );

			// Import meta. Replace synced media IDs before saving so WooCommerce
			// galleries, featured images, ACF media fields, Elementor data, etc.
			// point at local attachments instead of source-site attachment IDs.
			if ( ! empty( $post_data['meta'] ) ) {
				if ( ! empty( $image_map ) ) {
					$post_data['meta'] = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::replace_in_meta_public(
						$post_data['meta'],
						'',
						'',
						$image_map,
						$remote_images
					);
				}

				foreach ( $post_data['meta'] as $key => $value ) {
					$this->save_synced_post_meta( (int) $post_id, (string) $key, $value );
				}
			}

			// Import terms with ACF fields
			if ( ! empty( $post_data['terms'] ) ) {
				// Build source_term_id → local_term_id map to fix ACF taxonomy fields.
				$term_id_map = array();

				// Clear ALL existing term assignments for every taxonomy the source
				// sent (including empty ones) so stale local terms are removed.
				foreach ( array_keys( $post_data['terms'] ) as $taxonomy_to_clear ) {
					$this->ensure_woocommerce_attribute_taxonomy( $taxonomy_to_clear );
					if ( taxonomy_exists( $taxonomy_to_clear ) ) {
						wp_set_object_terms( $post_id, array(), $taxonomy_to_clear );
					}
				}

				foreach ( $post_data['terms'] as $taxonomy => $terms_info ) {
					$this->ensure_woocommerce_attribute_taxonomy( $taxonomy );
					if ( ! taxonomy_exists( $taxonomy ) ) {
						continue;
					}

					$term_ids = array();
					foreach ( $terms_info as $term_info ) {
						// Validate term info
						if ( empty( $term_info['name'] ) || empty( $term_info['slug'] ) ) {
							continue;
						}

						$term_id = $this->resolve_synced_term( $taxonomy, $term_info );
						if ( $term_id <= 0 ) {
							continue;
						}

						$term_ids[] = (int) $term_id;

						// Record source → local term ID mapping.
						if ( ! empty( $term_info['term_id'] ) ) {
							$term_id_map[ (int) $term_info['term_id'] ] = (int) $term_id;
						}

						// Import ACF fields for this term
						if ( ! empty( $term_info['acf'] ) && function_exists( 'update_field' ) ) {
							foreach ( $term_info['acf'] as $field_key => $field_value ) {
								ACF_Fields::import_value( 'term', $term_id, sanitize_text_field( (string) $field_key ), $field_value, $taxonomy );
							}
						}
					}

					// Assign terms to post only if we have valid term IDs
					if ( ! empty( $term_ids ) ) {
						wp_set_object_terms( $post_id, $term_ids, $taxonomy );
					}
				}

				// Re-save ACF taxonomy fields with correct local term IDs.
				if ( ! empty( $term_id_map ) && ! empty( $post_data['meta'] ) ) {
					\RockStarLab\ImportExport\Helper\Content_Sync_Replacer::translate_acf_taxonomy_fields_in_meta(
						$post_data['meta'],
						$post_id,
						$term_id_map
					);
				}
			}

			// Re-save ACF post reference fields (post_object / relationship / page_link) with correct local IDs.
			if ( ! empty( $post_data['meta'] ) ) {
				\RockStarLab\ImportExport\Helper\Content_Sync_Replacer::translate_acf_post_reference_fields_in_meta(
					$post_data['meta'],
					$post_id,
					$remote_post_id,
					isset( $post_data['post_refs'] ) ? $post_data['post_refs'] : array()
				);
			}

			// Import WooCommerce product variations and recalculate the variable
			// product price range so the local site shows the correct prices.
			if ( 'product' === $post_data['post_type']
				&& ! empty( $post_data['variations'] )
				&& class_exists( 'WC_Product' )
				&& function_exists( 'wc_get_product' )
			) {
					$this->import_product_variations( $post_id, $post_data['variations'], (array) $image_map, (array) $remote_images );
			}

			// Import WooCommerce grouped product children and remap _children meta.
			if ( 'product' === $post_data['post_type']
				&& ! empty( $post_data['grouped_children'] )
				&& class_exists( 'WC_Product' )
				&& function_exists( 'wc_get_product' )
			) {
					$local_child_ids = $this->import_grouped_children( $post_id, $post_data['grouped_children'], (array) $image_map, (array) $remote_images );
				if ( ! empty( $local_child_ids ) ) {
					update_post_meta( $post_id, '_children', $local_child_ids );
				}
			}

			if ( ! empty( $post_data['comments'] ) ) {
				$this->import_synced_comments( $post_id, $post_data['comments'] );
			}

			if ( 'product' === $post_data['post_type'] ) {
				$this->sync_woocommerce_product_type( (int) $post_id, (array) $post_data );
				$this->refresh_woocommerce_product_after_sync( $post_id );
			}

			// Fix image URLs in content after import.
			$updated_content = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::fix_local_image_urls_in_content(
				$post_data['post_content'],
				$image_map,
				$remote_images
			);

			foreach ( $image_map as $old_id => $new_id ) {
				// Also update wp:image block ID.
				$updated_content = str_replace( '"id":' . (int) $old_id, '"id":' . (int) $new_id, $updated_content );
			}

			if ( $updated_content !== $post_data['post_content'] ) {

				$update_result = wp_update_post(
					array(
						'ID'           => $post_id,
						'post_content' => $updated_content,
					),
					true
				);

			} else {
			}
		}

		$this->apply_synced_posts_wpml_data( $posts_data, $imported_remote_to_local );

		// Fix hierarchical relationships (e.g. pages) after all imports so we can
		// resolve parent IDs that were created in the same pull batch.
		foreach ( $imported_remote_to_local as $remote_id => $local_id ) {
			if ( ! array_key_exists( $remote_id, $imported_remote_parent ) ) {
				continue;
			}

			$remote_parent_id = $imported_remote_parent[ $remote_id ];
			if ( null === $remote_parent_id ) {
				continue;
			}

			$post_type = isset( $imported_remote_type[ $remote_id ] ) ? $imported_remote_type[ $remote_id ] : '';
			if ( empty( $post_type ) || ! is_post_type_hierarchical( $post_type ) ) {
				continue;
			}

			$local_parent_id = 0;
			if ( $remote_parent_id > 0 ) {
				if ( isset( $imported_remote_to_local[ $remote_parent_id ] ) ) {
					$local_parent_id = (int) $imported_remote_to_local[ $remote_parent_id ];
				} else {
					$local_parent_id = (int) $this->find_existing_post_by_original_id( $remote_parent_id );
				}

				// Parent not available locally - don't force reset the relationship.
				if ( empty( $local_parent_id ) ) {
					continue;
				}

				$parent_post = get_post( $local_parent_id );
				if ( ! $parent_post || $parent_post->post_type !== $post_type ) {
					continue;
				}
			}

			$child_post = get_post( $local_id );
			if ( ! $child_post || $child_post->post_type !== $post_type ) {
				continue;
			}

			if ( (int) $child_post->post_parent === (int) $local_parent_id ) {
				continue;
			}

			wp_update_post(
				array(
					'ID'          => $local_id,
					'post_parent' => $local_parent_id,
				),
				true
			);
		}

		foreach ( array_unique( $product_post_ids ) as $product_post_id ) {
			$this->refresh_woocommerce_product_after_sync( $product_post_id );
		}

		$total_processed = $imported_count + $updated_count;
		$message         = array();

		if ( $imported_count > 0 ) {
			$message[] = sprintf(
				/* translators: %d: number of posts */
				_n( 'Created %d post', 'Created %d posts', $imported_count, 'import-export-by-rockstarlab' ),
				$imported_count
			);
		}

		if ( $updated_count > 0 ) {
			$message[] = sprintf(
				/* translators: %d: number of posts */
				_n( 'Updated %d post', 'Updated %d posts', $updated_count, 'import-export-by-rockstarlab' ),
				$updated_count
			);
		}

		$this->send_success(
			array(
				'message' => ! empty( $message ) ? implode( ', ', $message ) : __( 'No posts were processed', 'import-export-by-rockstarlab' ),
			)
		);
	}

	/**
	 * Download image from remote site
	 *
	 * @param array $image Image data from remote.
	 * @param array $site Site connection data.
	 * @return int|false New attachment ID or false on failure
	 */
	private function download_image_from_remote( $image, $site ) {
		$source_attachment_id = isset( $image['attachment_id'] ) ? (int) $image['attachment_id'] : 0;
		$force_unique         = ! empty( $image['force_unique'] );

		if ( $source_attachment_id > 0 ) {
			$existing_id = $this->find_attachment_by_original_attachment_id( $source_attachment_id );
			if ( $existing_id ) {
				\RockStarLab\ImportExport\Helper\Content_Sync_Media::ensure_image_sizes( $existing_id );
				$this->apply_synced_attachment_content_fields( (int) $existing_id, $image );
				$this->apply_synced_attachment_wpml_data( (int) $existing_id, $image );
				return $existing_id;
			}
		}

		// Check if image already exists by hash (fast path using stored meta).
		if ( ! $force_unique && ! empty( $image['file_hash'] ) ) {
			$existing_id = $this->find_attachment_by_hash( $image['file_hash'] );
			if ( $existing_id ) {
				\RockStarLab\ImportExport\Helper\Content_Sync_Media::ensure_image_sizes( $existing_id );
				$this->apply_synced_attachment_content_fields( (int) $existing_id, $image );
				$this->apply_synced_attachment_wpml_data( (int) $existing_id, $image );
				return $existing_id;
			}
		}

		// Download file from remote URL
		$image_url = $image['url'];
		$response  = wp_remote_get( $image_url, array( 'timeout' => 30 ) );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$file_contents = wp_remote_retrieve_body( $response );
		if ( empty( $file_contents ) ) {
			return false;
		}

		// Compute actual hash from downloaded bytes.
		// This covers two scenarios:
		// 1. file_hash was missing in the request (e.g. term-ACF images from older remotes).
		// 2. Race condition: two concurrent pull requests both passed the initial hash
		// check above before either saved the attachment. Re-checking here after the
		// download gives the second request a chance to detect the attachment created
		// by the first one and reuse it instead of creating a duplicate.
		$actual_hash = md5( $file_contents );
		if ( ! $force_unique ) {
			$existing_id = $this->find_attachment_by_hash( $actual_hash );
			if ( $existing_id ) {
				\RockStarLab\ImportExport\Helper\Content_Sync_Media::ensure_image_sizes( $existing_id );
				$this->apply_synced_attachment_content_fields( (int) $existing_id, $image );
				$this->apply_synced_attachment_wpml_data( (int) $existing_id, $image );
				return $existing_id;
			}
		}

		// Get filename
		$filename = isset( $image['file_name'] ) ? $image['file_name'] : basename( $image_url );

		// Upload to WordPress
		$upload = wp_upload_bits( $filename, null, $file_contents );
		if ( $upload['error'] ) {
			return false;
		}

		// Create attachment
		$attachment = array(
			'post_mime_type' => isset( $image['mime_type'] ) ? $image['mime_type'] : '',
			'post_title'     => isset( $image['title'] ) ? $image['title'] : '',
			'post_content'   => isset( $image['description'] ) ? $image['description'] : '',
			'post_excerpt'   => isset( $image['caption'] ) ? $image['caption'] : '',
			'post_status'    => 'inherit',
		);

		$attachment_id = wp_insert_attachment( $attachment, $upload['file'] );
		if ( is_wp_error( $attachment_id ) || ! $attachment_id ) {
			return false;
		}

			// Generate metadata
			\RockStarLab\ImportExport\Helper\Fs::load_attachment_metadata_core();
			$attach_data = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
			wp_update_attachment_metadata( $attachment_id, $attach_data );

		// Set alt text
		if ( ! empty( $image['alt_text'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $image['alt_text'] );
		}

		// Always store the actual hash (covers missing file_hash in request).
		\RockStarLab\ImportExport\Helper\Media_Hash::store_attachment_hash( $attachment_id, $actual_hash, $upload['file'] );
		if ( $source_attachment_id > 0 ) {
			update_post_meta( $attachment_id, '_rsl_ie_original_attachment_id', $source_attachment_id );
			update_post_meta( $attachment_id, '_rsl_ie_source_attachment_id', $source_attachment_id );
		}
		$this->apply_synced_attachment_content_fields( (int) $attachment_id, $image );
		$this->apply_synced_attachment_wpml_data( (int) $attachment_id, $image );

		return $attachment_id;
	}

	/**
	 * Find attachment by file hash
	 *
	 * First checks the shared media hash index (fast).
	 * Falls back to scanning all attachments on disk so that images already
	 * present in the library (uploaded manually or before hash storage was
	 * introduced) are detected and not duplicated.
	 *
	 * @param string $file_hash File MD5 hash.
	 * @return int|false Attachment ID or false if not found
	 */
	private function find_attachment_by_hash( $file_hash ) {
		return \RockStarLab\ImportExport\Helper\Media_Hash::get_attachment_by_hash( $file_hash, true );
	}

	/**
	 * Find an attachment previously synced from a specific source attachment ID.
	 *
	 * @param int $source_attachment_id Source attachment ID.
	 * @return int|false Attachment ID or false.
	 */
	private function find_attachment_by_original_attachment_id( $source_attachment_id ) {
		$source_attachment_id = absint( $source_attachment_id );
		if ( $source_attachment_id <= 0 ) {
			return false;
		}

		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'meta_key'       => '_rsl_ie_original_attachment_id', // phpcs:ignore WordPress.DB.SlowDBQuery -- Exact source attachment lookup for sync mapping.
				'meta_value'     => $source_attachment_id, // phpcs:ignore WordPress.DB.SlowDBQuery -- Exact source attachment lookup for sync mapping.
			)
		);

		return ! empty( $attachments ) ? (int) $attachments[0] : false;
	}

	private function apply_synced_attachment_content_fields( $attachment_id, array $image ) {
		$attachment_id = absint( $attachment_id );
		if ( $attachment_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
			return;
		}

		$update = array( 'ID' => $attachment_id );
		if ( array_key_exists( 'description', $image ) ) {
			$description            = (string) $image['description'];
			$update['post_content'] = class_exists( ACF_Fields::class )
				? ACF_Fields::replace_media_urls_in_html( $description, $attachment_id )
				: $description;
		}
		if ( ! empty( $image['caption'] ) ) {
			$update['post_excerpt'] = (string) $image['caption'];
		}
		if ( ! empty( $image['title'] ) ) {
			$update['post_title'] = (string) $image['title'];
		}
		wp_update_post( wp_slash( $update ) );

		if ( class_exists( ACF_Fields::class ) && ! empty( $image['acf'] ) && is_array( $image['acf'] ) ) {
			foreach ( $image['acf'] as $field_name => $value ) {
				ACF_Fields::import_value( 'media', $attachment_id, sanitize_text_field( (string) $field_name ), $value );
			}
		}
	}

	private function apply_synced_attachment_wpml_data( $attachment_id, array $image_data ) {
		if ( empty( $image_data['wpml'] ) || ! is_array( $image_data['wpml'] ) || ! WPML_Compatibility::is_active() ) {
			return;
		}

		WPML_Compatibility::apply_post_language_details( (int) $attachment_id, $image_data['wpml'], array() );
	}

	/**
	 * Prepare ACF value - ensure numeric IDs are integers, not strings
	 * This is important for file/image fields in repeaters
	 *
	 * @param mixed $value Value to prepare
	 * @return mixed Prepared value
	 */
	private function prepare_acf_value( $value ) {
		if ( is_array( $value ) ) {
			// Recursively process arrays (repeater rows, galleries, etc.)
			foreach ( $value as $key => $item ) {
				$value[ $key ] = $this->prepare_acf_value( $item );
			}
			return $value;
		}

		// Convert numeric strings to integers (important for attachment IDs)
		if ( is_string( $value ) && is_numeric( $value ) && strpos( $value, '.' ) === false ) {
			return intval( $value );
		}

		return $value;
	}

	/**
	 * Collect comments/reviews for a synced post.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function collect_post_comments_for_sync( $post_id ) {
		$comments = get_comments(
			array(
				'post_id' => (int) $post_id,
				'status'  => 'all',
				'orderby' => 'comment_ID',
				'order'   => 'ASC',
			)
		);

		$data = array();
		foreach ( $comments as $comment ) {
			$meta          = get_comment_meta( $comment->comment_ID );
			$prepared_meta = array();
			foreach ( $meta as $key => $values ) {
				if ( '_rsl_ie_original_comment_id' === $key ) {
					continue;
				}
				$prepared_meta[ $key ] = maybe_unserialize( $values[0] );
			}

			$data[] = array(
				'comment_ID'           => (int) $comment->comment_ID,
				'comment_parent'       => (int) $comment->comment_parent,
				'comment_author'       => $comment->comment_author,
				'comment_author_email' => $comment->comment_author_email,
				'comment_author_url'   => $comment->comment_author_url,
				'comment_author_IP'    => $comment->comment_author_IP,
				'comment_date'         => $comment->comment_date,
				'comment_date_gmt'     => $comment->comment_date_gmt,
				'comment_content'      => $comment->comment_content,
				'comment_karma'        => (int) $comment->comment_karma,
				'comment_approved'     => $comment->comment_approved,
				'comment_agent'        => $comment->comment_agent,
				'comment_type'         => $comment->comment_type,
				'user_id'              => (int) $comment->user_id,
				'meta'                 => $prepared_meta,
			);
		}

		return $data;
	}

	/**
	 * Import synced comments/reviews for a local post.
	 *
	 * @param int   $post_id  Local post ID.
	 * @param array $comments Synced comments payload.
	 * @return void
	 */
	private function import_synced_comments( $post_id, $comments ) {
		$source_to_local = array();

		foreach ( (array) $comments as $comment_data ) {
			$source_comment_id = isset( $comment_data['comment_ID'] ) ? (int) $comment_data['comment_ID'] : 0;
			if ( $source_comment_id <= 0 ) {
				continue;
			}

			$existing_id = $this->find_existing_comment_by_original_id( $post_id, $source_comment_id );
			$parent_id   = 0;
			if ( ! empty( $comment_data['comment_parent'] ) ) {
				$source_parent = (int) $comment_data['comment_parent'];
				$parent_id     = isset( $source_to_local[ $source_parent ] ) ? (int) $source_to_local[ $source_parent ] : 0;
			}

			$args = array(
				'comment_post_ID'      => (int) $post_id,
				'comment_author'       => isset( $comment_data['comment_author'] ) ? sanitize_text_field( $comment_data['comment_author'] ) : '',
				'comment_author_email' => isset( $comment_data['comment_author_email'] ) ? sanitize_email( $comment_data['comment_author_email'] ) : '',
				'comment_author_url'   => isset( $comment_data['comment_author_url'] ) ? esc_url_raw( $comment_data['comment_author_url'] ) : '',
				'comment_author_IP'    => isset( $comment_data['comment_author_IP'] ) ? sanitize_text_field( $comment_data['comment_author_IP'] ) : '',
				'comment_date'         => isset( $comment_data['comment_date'] ) ? sanitize_text_field( $comment_data['comment_date'] ) : current_time( 'mysql' ),
				'comment_date_gmt'     => isset( $comment_data['comment_date_gmt'] ) ? sanitize_text_field( $comment_data['comment_date_gmt'] ) : current_time( 'mysql', true ),
				'comment_content'      => isset( $comment_data['comment_content'] ) ? wp_kses_post( $comment_data['comment_content'] ) : '',
				'comment_karma'        => isset( $comment_data['comment_karma'] ) ? (int) $comment_data['comment_karma'] : 0,
				'comment_approved'     => isset( $comment_data['comment_approved'] ) ? sanitize_text_field( $comment_data['comment_approved'] ) : '1',
				'comment_agent'        => isset( $comment_data['comment_agent'] ) ? sanitize_text_field( $comment_data['comment_agent'] ) : '',
				'comment_type'         => isset( $comment_data['comment_type'] ) ? sanitize_key( $comment_data['comment_type'] ) : '',
				'comment_parent'       => $parent_id,
				'user_id'              => isset( $comment_data['user_id'] ) ? (int) $comment_data['user_id'] : 0,
			);

			if ( $existing_id ) {
				$args['comment_ID'] = $existing_id;
				$result_id          = wp_update_comment( $args ) ? $existing_id : 0;
			} else {
				$result_id = wp_insert_comment( $args );
			}

			if ( ! $result_id || is_wp_error( $result_id ) ) {
				continue;
			}

			$source_to_local[ $source_comment_id ] = (int) $result_id;
			update_comment_meta( $result_id, '_rsl_ie_original_comment_id', $source_comment_id );

			if ( ! empty( $comment_data['meta'] ) && is_array( $comment_data['meta'] ) ) {
				foreach ( $comment_data['meta'] as $key => $value ) {
					if ( '_rsl_ie_original_comment_id' === $key ) {
						continue;
					}
					update_comment_meta( $result_id, sanitize_key( $key ), $value );
				}
			}
		}
	}

	/**
	 * Find an existing synced comment on a local post.
	 *
	 * @param int $post_id            Local post ID.
	 * @param int $source_comment_id  Source comment ID.
	 * @return int
	 */
	private function find_existing_comment_by_original_id( $post_id, $source_comment_id ) {
		$comments = get_comments(
			array(
				'post_id'    => (int) $post_id,
				'status'     => 'all',
				'number'     => 1,
				'meta_key'   => '_rsl_ie_original_comment_id', // phpcs:ignore WordPress.DB.SlowDBQuery -- Exact source comment lookup for sync mapping.
				'meta_value' => (int) $source_comment_id, // phpcs:ignore WordPress.DB.SlowDBQuery -- Exact source comment lookup for sync mapping.
				'fields'     => 'ids',
			)
		);

		return ! empty( $comments ) ? (int) $comments[0] : 0;
	}

	/**
	 * Import standalone synced comments by resolving each target post first.
	 *
	 * @param array $comments Comment payloads.
	 * @return array Counts.
	 */
	private function import_standalone_synced_comments( $comments, $post_mapping = array(), $target_post_id = 0 ) {
		$result = array(
			'created' => 0,
			'updated' => 0,
			'failed'  => 0,
			'errors'  => array(),
		);

		foreach ( (array) $comments as $comment_data ) {
			if ( ! is_array( $comment_data ) ) {
				++$result['failed'];
				$result['errors'][] = __( 'A remote comment had an invalid payload and was skipped.', 'import-export-by-rockstarlab' );
				continue;
			}

			$source_comment_id = isset( $comment_data['comment_ID'] ) ? absint( $comment_data['comment_ID'] ) : 0;
			$post_id           = $target_post_id > 0 ? $target_post_id : $this->resolve_standalone_comment_post_id( $comment_data, $post_mapping );
			if ( $post_id <= 0 ) {
				++$result['failed'];
				$result['errors'][] = sprintf(
					/* translators: %d: source comment ID. */
					__( 'Comment #%d was skipped because no destination post was selected or matched.', 'import-export-by-rockstarlab' ),
					$source_comment_id
				);
				continue;
			}

			if ( ! get_post( $post_id ) ) {
				++$result['failed'];
				$result['errors'][] = sprintf(
					/* translators: 1: source comment ID, 2: destination post ID. */
					__( 'Comment #%1$d was skipped because destination post #%2$d does not exist.', 'import-export-by-rockstarlab' ),
					$source_comment_id,
					$post_id
				);
				continue;
			}

			$existing_id = $this->find_existing_comment_by_original_id( $post_id, $source_comment_id );

			$args = array(
				'comment_post_ID'      => $post_id,
				'comment_author'       => isset( $comment_data['comment_author'] ) ? sanitize_text_field( (string) $comment_data['comment_author'] ) : '',
				'comment_author_email' => isset( $comment_data['comment_author_email'] ) ? sanitize_email( (string) $comment_data['comment_author_email'] ) : '',
				'comment_author_url'   => isset( $comment_data['comment_author_url'] ) ? esc_url_raw( (string) $comment_data['comment_author_url'] ) : '',
				'comment_author_IP'    => isset( $comment_data['comment_author_IP'] ) ? sanitize_text_field( (string) $comment_data['comment_author_IP'] ) : '',
				'comment_date'         => isset( $comment_data['comment_date'] ) ? sanitize_text_field( (string) $comment_data['comment_date'] ) : current_time( 'mysql' ),
				'comment_date_gmt'     => isset( $comment_data['comment_date_gmt'] ) ? sanitize_text_field( (string) $comment_data['comment_date_gmt'] ) : current_time( 'mysql', true ),
				'comment_content'      => isset( $comment_data['comment_content'] ) ? wp_kses_post( (string) $comment_data['comment_content'] ) : '',
				'comment_karma'        => isset( $comment_data['comment_karma'] ) ? (int) $comment_data['comment_karma'] : 0,
				'comment_approved'     => isset( $comment_data['comment_approved'] ) ? sanitize_text_field( (string) $comment_data['comment_approved'] ) : '1',
				'comment_agent'        => isset( $comment_data['comment_agent'] ) ? sanitize_text_field( (string) $comment_data['comment_agent'] ) : '',
				'comment_type'         => isset( $comment_data['comment_type'] ) ? sanitize_key( (string) $comment_data['comment_type'] ) : '',
				'comment_parent'       => 0,
				'user_id'              => 0,
			);

			if ( $existing_id ) {
				$args['comment_ID'] = $existing_id;
				$comment_id         = wp_update_comment( $args ) ? $existing_id : 0;
				if ( $comment_id ) {
					++$result['updated'];
				}
			} else {
				$comment_id = wp_insert_comment( $args );
				if ( $comment_id ) {
					++$result['created'];
				}
			}

			if ( ! $comment_id || is_wp_error( $comment_id ) ) {
				++$result['failed'];
				$result['errors'][] = is_wp_error( $comment_id ) ? $comment_id->get_error_message() : sprintf(
					/* translators: %d: source comment ID. */
					__( 'Comment #%d could not be saved.', 'import-export-by-rockstarlab' ),
					$source_comment_id
				);
				continue;
			}

			if ( $source_comment_id > 0 ) {
				update_comment_meta( (int) $comment_id, '_rsl_ie_original_comment_id', $source_comment_id );
			}

			$this->import_comment_acf_for_sync( (int) $comment_id, $comment_data );

			if ( ! empty( $comment_data['meta'] ) && is_array( $comment_data['meta'] ) ) {
				foreach ( $comment_data['meta'] as $key => $value ) {
					if ( '_rsl_ie_original_comment_id' === $key || $this->is_acf_comment_sync_meta_key( (string) $key, $comment_data ) ) {
						continue;
					}
					update_comment_meta( (int) $comment_id, sanitize_key( (string) $key ), $value );
				}
			}
		}

		return $result;
	}

	/**
	 * Prepare a local comment payload for standalone comment sync.
	 *
	 * @param \WP_Comment $comment Comment object.
	 * @return array
	 */
	private function prepare_standalone_comment_for_sync( $comment ) {
		$post = get_post( (int) $comment->comment_post_ID );
		$data = array(
			'comment_ID'           => (int) $comment->comment_ID,
			'comment_post_ID'      => (int) $comment->comment_post_ID,
			'comment_author'       => (string) $comment->comment_author,
			'comment_author_email' => (string) $comment->comment_author_email,
			'comment_author_url'   => (string) $comment->comment_author_url,
			'comment_author_IP'    => (string) $comment->comment_author_IP,
			'comment_date'         => (string) $comment->comment_date,
			'comment_date_gmt'     => (string) $comment->comment_date_gmt,
			'comment_content'      => (string) $comment->comment_content,
			'comment_karma'        => (int) $comment->comment_karma,
			'comment_approved'     => (string) $comment->comment_approved,
			'comment_agent'        => (string) $comment->comment_agent,
			'comment_type'         => (string) $comment->comment_type,
			'comment_parent'       => (int) $comment->comment_parent,
			'user_id'              => (int) $comment->user_id,
			'post'                 => $post ? array(
				'ID'        => (int) $post->ID,
				'post_type' => (string) $post->post_type,
				'post_name' => (string) $post->post_name,
				'title'     => (string) get_the_title( $post ),
			) : array(),
			'meta'                 => array(),
		);

		$meta = get_comment_meta( (int) $comment->comment_ID );
		foreach ( $meta as $key => $values ) {
			$data['meta'][ $key ] = isset( $values[0] ) ? maybe_unserialize( $values[0] ) : '';
		}

		$data['acf'] = $this->export_comment_acf_for_sync( (int) $comment->comment_ID );

		return $data;
	}

	/**
	 * Export ACF fields attached to a comment.
	 *
	 * @param int $comment_id Comment ID.
	 * @return array
	 */
	private function export_comment_acf_for_sync( $comment_id ) {
		$acf = array();

		if ( ! class_exists( ACF_Fields::class ) ) {
			return $acf;
		}

		foreach ( ACF_Fields::get_fields_for_content_type( 'comment' ) as $field ) {
			if ( empty( $field['name'] ) ) {
				continue;
			}

			$name         = (string) $field['name'];
			$acf[ $name ] = ACF_Fields::export_value( 'comment', (int) $comment_id, $name );
		}

		return $acf;
	}

	/**
	 * Resolve target post for standalone comment sync.
	 *
	 * @param array $comment_data Comment payload.
	 * @return int
	 */
	private function resolve_standalone_comment_post_id( $comment_data, $post_mapping = array() ) {
		$post_info      = isset( $comment_data['post'] ) && is_array( $comment_data['post'] ) ? $comment_data['post'] : array();
		$source_post_id = isset( $post_info['ID'] ) ? absint( $post_info['ID'] ) : absint( $comment_data['comment_post_ID'] ?? 0 );
		$post_type      = isset( $post_info['post_type'] ) ? sanitize_key( (string) $post_info['post_type'] ) : '';
		$post_name      = isset( $post_info['post_name'] ) ? sanitize_title( (string) $post_info['post_name'] ) : '';

		if ( $source_post_id > 0 && isset( $post_mapping[ $source_post_id ] ) ) {
			$mapped_post_id = absint( $post_mapping[ $source_post_id ] );
			if ( $mapped_post_id > 0 && get_post( $mapped_post_id ) ) {
				return $mapped_post_id;
			}
		}

		if ( $source_post_id > 0 ) {
			$matched = $this->find_existing_post_by_original_id( $source_post_id );
			if ( $matched > 0 ) {
				return (int) $matched;
			}
		}

		if ( '' !== $post_type && '' !== $post_name ) {
			$post = get_page_by_path( $post_name, OBJECT, $post_type );
			if ( $post ) {
				return (int) $post->ID;
			}
		}

		if ( '' !== $post_type && ! empty( $post_info['title'] ) ) {
			$matched_posts = get_posts(
				array(
					'post_type'              => $post_type,
					'post_status'            => 'any',
					'title'                  => sanitize_text_field( (string) $post_info['title'] ),
					'posts_per_page'         => 1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
				)
			);
			if ( ! empty( $matched_posts ) ) {
				return (int) $matched_posts[0];
			}
		}

		return 0;
	}

	/**
	 * Import synced ACF fields into a comment.
	 *
	 * @param int   $comment_id   Comment ID.
	 * @param array $comment_data Comment sync payload.
	 * @return void
	 */
	private function import_comment_acf_for_sync( $comment_id, $comment_data ) {
		if ( $comment_id <= 0 || empty( $comment_data['acf'] ) || ! is_array( $comment_data['acf'] ) || ! class_exists( ACF_Fields::class ) ) {
			return;
		}

		foreach ( $comment_data['acf'] as $field_name => $value ) {
			$field_name = sanitize_text_field( (string) $field_name );
			if ( '' === $field_name ) {
				continue;
			}

			ACF_Fields::import_value( 'comment', (int) $comment_id, $field_name, $value );
		}
	}

	/**
	 * Collect media referenced by a comment's ACF payload.
	 *
	 * @param array $comment_data Prepared comment payload.
	 * @param array $all_images   Accumulator keyed by attachment ID.
	 * @return void
	 */
	private function collect_comment_acf_images_for_sync( array $comment_data, array &$all_images ) {
		$image_ids = array();
		if ( ! empty( $comment_data['comment_content'] ) && is_string( $comment_data['comment_content'] ) ) {
			$image_ids = array_merge( $image_ids, $this->extract_term_acf_images( array( 'comment_content' => $comment_data['comment_content'] ) ) );
		}

		if ( empty( $comment_data['acf'] ) || ! is_array( $comment_data['acf'] ) ) {
			$comment_data['acf'] = array();
		}

		$image_ids = array_merge( $image_ids, $this->extract_term_acf_images( $comment_data['acf'] ) );
		$image_ids = array_values( array_unique( array_filter( array_map( 'absint', $image_ids ) ) ) );
		foreach ( $image_ids as $image_id ) {
			if ( isset( $all_images[ $image_id ] ) ) {
				continue;
			}

			$image_data = \RockStarLab\ImportExport\Helper\Content_Sync_Media::prepare_image_data( $image_id, 'comment_acf' );
			if ( ! $image_data ) {
				$image_data = array(
					'attachment_id' => $image_id,
					'url'           => wp_get_attachment_url( $image_id ),
					'type'          => 'comment_acf',
				);
			}

			$image_data['comment_id'] = isset( $comment_data['comment_ID'] ) ? (int) $comment_data['comment_ID'] : 0;
			$all_images[ $image_id ]  = $image_data;
		}
	}

	/**
	 * Replace media references inside comment ACF payloads.
	 *
	 * @param array  $comments      Comment payloads.
	 * @param string $source_domain Source site URL.
	 * @param string $target_domain Target site URL.
	 * @param array  $image_map     Source attachment ID => target attachment ID.
	 * @param array  $image_sources Source image metadata.
	 * @return array
	 */
	private function replace_comment_acf_media_references( array $comments, $source_domain, $target_domain, array $image_map, array $image_sources ) {
		if ( empty( $image_map ) ) {
			return $comments;
		}

		foreach ( $comments as &$comment_data ) {
			if ( isset( $comment_data['comment_content'] ) && is_string( $comment_data['comment_content'] ) ) {
				$comment_data['comment_content'] = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::fix_local_image_urls_in_content(
					$comment_data['comment_content'],
					$image_map,
					$image_sources
				);
			}

			if ( empty( $comment_data['acf'] ) || ! is_array( $comment_data['acf'] ) ) {
				continue;
			}

			$comment_data['acf'] = $this->replace_term_acf_value_media_references(
				$comment_data['acf'],
				$source_domain,
				$target_domain,
				$image_map,
				$image_sources
			);
		}
		unset( $comment_data );

		return $comments;
	}

	/**
	 * Determine whether a raw comment meta key belongs to synced ACF data.
	 *
	 * @param string $meta_key     Meta key.
	 * @param array  $comment_data Comment sync payload.
	 * @return bool
	 */
	private function is_acf_comment_sync_meta_key( $meta_key, $comment_data ) {
		if ( '' === $meta_key || empty( $comment_data['acf'] ) || ! is_array( $comment_data['acf'] ) ) {
			return false;
		}

		$acf_names = array_map( 'strval', array_keys( $comment_data['acf'] ) );
		foreach ( $acf_names as $acf_name ) {
			if ( '' === $acf_name ) {
				continue;
			}
			if (
				$meta_key === $acf_name
				|| $meta_key === '_' . $acf_name
				|| 0 === strpos( $meta_key, $acf_name . '_' )
				|| 0 === strpos( $meta_key, '_' . $acf_name . '_' )
			) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Import WooCommerce product variations and recalculate the variable product.
	 *
	 * @param int   $parent_post_id Local product post ID.
	 * @param array $variations     Variation data from the source site.
	 * @param array $image_map      Source attachment ID => local attachment ID map.
	 * @return void
	 */
	private function import_product_variations( $parent_post_id, $variations, $image_map, $image_sources = array() ) {
		if ( empty( $variations ) ) {
			return;
		}

		$source_to_local        = array();
		$existing_local_var_ids = get_posts(
			array(
				'post_type'      => 'product_variation',
				'post_parent'    => $parent_post_id,
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
			)
		);

		foreach ( $existing_local_var_ids as $local_var_id ) {
			$orig_id = (int) get_post_meta( $local_var_id, '_rsl_ie_original_post_id', true );
			if ( $orig_id ) {
				$source_to_local[ $orig_id ] = (int) $local_var_id;
			}
		}

		$processed_source_ids = array();

		foreach ( $variations as $variation_data ) {
			$source_var_id = (int) ( isset( $variation_data['ID'] ) ? $variation_data['ID'] : 0 );

			$variation_args = array(
				'post_title'  => isset( $variation_data['post_title'] ) ? $variation_data['post_title'] : '',
				'post_name'   => isset( $variation_data['post_name'] ) ? $variation_data['post_name'] : '',
				'post_status' => isset( $variation_data['post_status'] ) ? $variation_data['post_status'] : 'publish',
				'post_type'   => 'product_variation',
				'post_parent' => $parent_post_id,
				'menu_order'  => isset( $variation_data['menu_order'] ) ? (int) $variation_data['menu_order'] : 0,
			);

			if ( $source_var_id && isset( $source_to_local[ $source_var_id ] ) ) {
				$variation_args['ID'] = $source_to_local[ $source_var_id ];
				$local_var_id         = wp_update_post( $variation_args );
			} else {
				$local_var_id = wp_insert_post( $variation_args );
				if ( $local_var_id && ! is_wp_error( $local_var_id ) && $source_var_id ) {
					update_post_meta( $local_var_id, '_rsl_ie_original_post_id', $source_var_id );
				}
			}

			if ( is_wp_error( $local_var_id ) || ! $local_var_id ) {
				continue;
			}

			if ( $source_var_id ) {
				$processed_source_ids[] = $source_var_id;
			}

			if ( ! empty( $variation_data['meta'] ) ) {
				$var_meta = $variation_data['meta'];
				if ( ! empty( $image_map ) ) {
					$var_meta = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::replace_in_meta_public(
						$var_meta,
						'',
						'',
						$image_map,
						$image_sources
					);
				}

				foreach ( $var_meta as $key => $value ) {
					if ( in_array( $key, array( '_edit_lock', '_edit_last' ), true ) ) {
						continue;
					}
					update_post_meta( $local_var_id, $key, $value );
				}
			}
		}

		foreach ( $existing_local_var_ids as $local_var_id ) {
			$orig_id = (int) get_post_meta( $local_var_id, '_rsl_ie_original_post_id', true );
			if ( $orig_id && ! in_array( $orig_id, $processed_source_ids, true ) ) {
				wp_delete_post( (int) $local_var_id, true );
			}
		}

		if ( function_exists( 'wc_get_product' ) && class_exists( 'WC_Product_Variable' ) ) {
			$wc_product = wc_get_product( $parent_post_id );
			if ( $wc_product && $wc_product->is_type( 'variable' ) ) {
				\WC_Product_Variable::sync( $wc_product );
			}
		}
	}

	/**
	 * Import WooCommerce grouped product children and return local child IDs.
	 *
	 * @param int   $parent_post_id Local grouped product post ID.
	 * @param array $children       Child product data from the source site.
	 * @param array $image_map      Source attachment ID => local attachment ID map.
	 * @return int[]
	 */
	private function import_grouped_children( $parent_post_id, $children, $image_map, $image_sources = array() ) {
		$local_child_ids = array();

		foreach ( $children as $child_data ) {
			$source_child_id = (int) ( isset( $child_data['ID'] ) ? $child_data['ID'] : 0 );
			$local_child_id  = null;

			if ( $source_child_id ) {
				$existing = get_posts(
					array(
						'post_type'      => 'product',
						'posts_per_page' => 1,
						'post_status'    => 'any',
						'fields'         => 'ids',
						'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery -- Exact source product lookup for grouped child mapping.
							array(
								'key'   => '_rsl_ie_original_post_id',
								'value' => $source_child_id,
							),
						),
					)
				);
				if ( ! empty( $existing ) ) {
					$local_child_id = (int) $existing[0];
				}
			}

			$child_args = array(
				'post_title'   => isset( $child_data['post_title'] ) ? $child_data['post_title'] : '',
				'post_name'    => isset( $child_data['post_name'] ) ? $child_data['post_name'] : '',
				'post_content' => isset( $child_data['post_content'] ) ? $child_data['post_content'] : '',
				'post_excerpt' => isset( $child_data['post_excerpt'] ) ? $child_data['post_excerpt'] : '',
				'post_status'  => isset( $child_data['post_status'] ) ? $child_data['post_status'] : 'publish',
				'post_type'    => 'product',
				'menu_order'   => isset( $child_data['menu_order'] ) ? (int) $child_data['menu_order'] : 0,
			);

			if ( $local_child_id ) {
				$child_args['ID'] = $local_child_id;
				$result           = wp_update_post( $child_args );
			} else {
				$result = wp_insert_post( $child_args );
				if ( $result && ! is_wp_error( $result ) && $source_child_id ) {
					update_post_meta( $result, '_rsl_ie_original_post_id', $source_child_id );
				}
				$local_child_id = $result;
			}

			if ( is_wp_error( $result ) || ! $result ) {
				continue;
			}

			if ( ! empty( $child_data['meta'] ) ) {
				$child_meta = $child_data['meta'];
				if ( ! empty( $image_map ) ) {
					$child_meta = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::replace_in_meta_public(
						$child_meta,
						'',
						'',
						$image_map,
						$image_sources
					);
				}
				foreach ( $child_meta as $key => $value ) {
					if ( in_array( $key, array( '_edit_lock', '_edit_last' ), true ) ) {
						continue;
					}
					update_post_meta( $local_child_id, $key, $value );
				}
			}

			if ( ! empty( $child_data['terms'] ) ) {
				foreach ( $child_data['terms'] as $taxonomy => $terms_info ) {
					$this->ensure_woocommerce_attribute_taxonomy( $taxonomy );
					if ( ! taxonomy_exists( $taxonomy ) ) {
						continue;
					}

					$term_ids = array();
					foreach ( $terms_info as $term_info ) {
						if ( empty( $term_info['name'] ) || empty( $term_info['slug'] ) ) {
							continue;
						}
						$term_id = $this->resolve_synced_term( $taxonomy, $term_info );
						if ( $term_id > 0 ) {
							$term_ids[] = (int) $term_id;
						}
					}
					wp_set_object_terms( $local_child_id, $term_ids, $taxonomy );
				}
			}

				$this->sync_woocommerce_product_type( (int) $local_child_id, (array) $child_data );

				$local_child_ids[] = (int) $local_child_id;
		}

		return $local_child_ids;
	}

	/**
	 * Convert flat ACF meta structure to hierarchical for repeater/flexible content fields
	 *
	 * @param array $meta Post meta array
	 * @param array $acf_field_keys ACF field keys mapping
	 * @return array Modified meta array
	 */
	private function convert_acf_flat_to_hierarchical( $meta, $acf_field_keys ) {
		$processed_parents = array();

		// Find all repeater/flexible content parent fields
		foreach ( $acf_field_keys as $field_name => $field_key ) {
			// Skip nested fields
			if ( preg_match( '/_\d+_/', $field_name ) ) {
				continue;
			}

			// Check if this field has a numeric value (count of rows) - typical for repeater
			if ( isset( $meta[ $field_name ] ) && is_numeric( $meta[ $field_name ] ) ) {
				$row_count = intval( $meta[ $field_name ] );

				// Verify this is actually a repeater by checking if sub-fields exist
				// Look for pattern: field_name_0_*
				$has_sub_fields = false;
				$row_prefix     = $field_name . '_0_';
				foreach ( $meta as $meta_key => $meta_value ) {
					if ( strpos( $meta_key, $row_prefix ) === 0 ) {
						$has_sub_fields = true;
						break;
					}
				}

				// If no sub-fields found, this is not a repeater (probably just a numeric field like image ID)
				if ( ! $has_sub_fields ) {
					continue;
				}

				// Build hierarchical structure
				$rows = array();
				for ( $i = 0; $i < $row_count; $i++ ) {
					$row_data     = array();
					$row_prefix   = $field_name . '_' . $i . '_';
					$found_fields = 0;

					// Find all fields for this row
					foreach ( $meta as $meta_key => $meta_value ) {
						if ( strpos( $meta_key, $row_prefix ) === 0 ) {
							++$found_fields;
							// Extract field name without row prefix
							$sub_field_name = substr( $meta_key, strlen( $row_prefix ) );

							// Check if this is a nested repeater/flexible content
							if ( isset( $acf_field_keys[ $field_name . '_' . $i . '_' . $sub_field_name ] ) && is_numeric( $meta_value ) ) {
								// Verify nested repeater has sub-fields
								$nested_prefix         = $field_name . '_' . $i . '_' . $sub_field_name . '_0_';
								$nested_has_sub_fields = false;
								foreach ( $meta as $nested_key => $nested_val ) {
									if ( strpos( $nested_key, $nested_prefix ) === 0 ) {
										$nested_has_sub_fields = true;
										break;
									}
								}

								if ( $nested_has_sub_fields ) {
									// Recursively process nested repeater
									$nested_rows                 = $this->extract_nested_repeater_data( $meta, $field_name . '_' . $i . '_' . $sub_field_name, $meta_value, $acf_field_keys );
									$row_data[ $sub_field_name ] = $nested_rows;
								} else {
									// Just a numeric value (like image ID)
									$row_data[ $sub_field_name ] = $meta_value;
								}
							} else {
								$row_data[ $sub_field_name ] = $meta_value;
							}
						}
					}

					$rows[] = $row_data;
				}

				// Replace numeric count with actual data array
				$meta[ $field_name ] = $rows;
				$processed_parents[] = $field_name;

			}
		}

		return $meta;
	}

	/**
	 * Extract nested repeater data recursively
	 *
	 * @param array  $meta Post meta array
	 * @param string $parent_prefix Parent field prefix (e.g., "repeater_0_nested_repeater")
	 * @param int    $row_count Number of rows
	 * @param array  $acf_field_keys ACF field keys mapping
	 * @return array Nested rows data
	 */
	private function extract_nested_repeater_data( $meta, $parent_prefix, $row_count, $acf_field_keys ) {
		$rows = array();

		for ( $i = 0; $i < $row_count; $i++ ) {
			$row_data   = array();
			$row_prefix = $parent_prefix . '_' . $i . '_';

			foreach ( $meta as $meta_key => $meta_value ) {
				if ( strpos( $meta_key, $row_prefix ) === 0 ) {
					$sub_field_name = substr( $meta_key, strlen( $row_prefix ) );

					// Check for even deeper nesting
					if ( isset( $acf_field_keys[ $parent_prefix . '_' . $i . '_' . $sub_field_name ] ) && is_numeric( $meta_value ) ) {
						// Verify this nested field actually has sub-fields (is a real repeater)
						$nested_prefix         = $parent_prefix . '_' . $i . '_' . $sub_field_name . '_0_';
						$has_nested_sub_fields = false;
						foreach ( $meta as $check_key => $check_value ) {
							if ( strpos( $check_key, $nested_prefix ) === 0 ) {
								$has_nested_sub_fields = true;
								break;
							}
						}

						if ( $has_nested_sub_fields ) {
							// This is a nested repeater
							$row_data[ $sub_field_name ] = $this->extract_nested_repeater_data(
								$meta,
								$parent_prefix . '_' . $i . '_' . $sub_field_name,
								$meta_value,
								$acf_field_keys
							);
						} else {
							// Just a numeric value (like image ID)
							$row_data[ $sub_field_name ] = $meta_value;
						}
					} else {
						$row_data[ $sub_field_name ] = $meta_value;
					}
				}
			}

			$rows[] = $row_data;
		}

		return $rows;
	}

	/**
	 * Find existing post by original post ID
	 *
	 * @param int $original_post_id Original post ID from source site.
	 * @return int|false Post ID or false if not found
	 */
	private function find_existing_post_by_original_id( $original_post_id ) {
		// Only update posts that were previously synced by this plugin. A plain
		// numeric ID match is not safe because a fresh target site may already
		// have an unrelated wp_posts row with the same ID (attachment, page,
		// revision, product variation, etc.).
		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_key'       => '_rsl_ie_original_post_id', // phpcs:ignore WordPress.DB.SlowDBQuery -- meta_key required for filtering.
				'meta_value'     => $original_post_id, // phpcs:ignore WordPress.DB.SlowDBQuery -- meta_value required for filtering.
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $posts ) ) {
			return $posts[0];
		}

		return false;
	}

	private function sync_woocommerce_product_type( $post_id, array $post_data ) {
		if ( ! taxonomy_exists( 'product_type' ) || 'product' !== get_post_type( $post_id ) ) {
			return;
		}

		$type = '';
		if ( ! empty( $post_data['terms']['product_type'][0]['slug'] ) ) {
			$type = sanitize_key( (string) $post_data['terms']['product_type'][0]['slug'] );
		} elseif ( ! empty( $post_data['meta']['_product_type'] ) ) {
			$type = sanitize_key( (string) $post_data['meta']['_product_type'] );
		}

		if ( '' === $type ) {
			return;
		}

		$allowed = array( 'simple', 'grouped', 'external', 'variable' );
		if ( function_exists( 'wc_get_product_types' ) ) {
			$allowed = array_keys( wc_get_product_types() );
		}
		if ( ! in_array( $type, $allowed, true ) ) {
			return;
		}

		if ( ! term_exists( $type, 'product_type' ) ) {
			wp_insert_term( ucfirst( $type ), 'product_type', array( 'slug' => $type ) );
		}

		wp_set_object_terms( (int) $post_id, $type, 'product_type', false );
		delete_transient( 'wc_product_children_' . (int) $post_id );
	}

	/**
	 * Resolve or create a synced taxonomy term, preserving hierarchy when present.
	 *
	 * @param string $taxonomy  Taxonomy name.
	 * @param array  $term_info Synced term payload.
	 * @return int Local term ID, or 0 on failure.
	 */
	private function resolve_synced_term( $taxonomy, $term_info ) {
		if ( ! taxonomy_exists( $taxonomy ) || empty( $term_info['name'] ) || empty( $term_info['slug'] ) ) {
			return 0;
		}

		$parent_id = 0;
		if ( is_taxonomy_hierarchical( $taxonomy ) ) {
			if ( ! empty( $term_info['parent_path'] ) ) {
				$parent_id = $this->resolve_synced_term_parent_path( $taxonomy, (string) $term_info['parent_path'] );
			} elseif ( ! empty( $term_info['parent_slug'] ) ) {
				$parent_id = $this->resolve_synced_term_parent_path( $taxonomy, (string) $term_info['parent_slug'] );
			}
		}

		$args = array(
			'name' => sanitize_text_field( (string) $term_info['name'] ),
		);
		if ( is_taxonomy_hierarchical( $taxonomy ) ) {
			$args['parent'] = $parent_id;
		}

		$existing_term = get_term_by( 'slug', sanitize_title( (string) $term_info['slug'] ), $taxonomy );
		if ( $existing_term ) {
			wp_update_term( (int) $existing_term->term_id, $taxonomy, $args );
			$this->apply_synced_term_wpml_data( (int) $existing_term->term_id, $taxonomy, (array) $term_info, [] );
			return (int) $existing_term->term_id;
		}

		$args['slug'] = sanitize_title( (string) $term_info['slug'] );
		$new_term     = wp_insert_term( sanitize_text_field( (string) $term_info['name'] ), $taxonomy, $args );
		if ( is_wp_error( $new_term ) || empty( $new_term['term_id'] ) ) {
			return 0;
		}

		$term_id = (int) $new_term['term_id'];
		$this->apply_synced_term_wpml_data( $term_id, $taxonomy, (array) $term_info, [] );

		return $term_id;
	}

	/**
	 * Update a specific mapped local taxonomy term from a remote term payload.
	 *
	 * @param string $taxonomy      Taxonomy name.
	 * @param int    $local_term_id Local term ID selected in the mapping UI.
	 * @param array  $term_info     Remote term payload.
	 * @return int Local term ID, or 0 on failure.
	 */
	private function update_mapped_synced_term( $taxonomy, $local_term_id, $term_info ) {
		if ( ! taxonomy_exists( $taxonomy ) || $local_term_id <= 0 || empty( $term_info['name'] ) ) {
			return 0;
		}

		$current_term = get_term( $local_term_id, $taxonomy );
		if ( ! $current_term || is_wp_error( $current_term ) ) {
			return 0;
		}

		$args = array(
			'name' => sanitize_text_field( (string) $term_info['name'] ),
		);

		if ( ! empty( $term_info['slug'] ) ) {
			$args['slug'] = sanitize_title( (string) $term_info['slug'] );
		}

		if ( is_taxonomy_hierarchical( $taxonomy ) ) {
			$parent_id = 0;
			if ( ! empty( $term_info['parent_path'] ) ) {
				$parent_id = $this->resolve_synced_term_parent_path( $taxonomy, (string) $term_info['parent_path'] );
			} elseif ( ! empty( $term_info['parent_slug'] ) ) {
				$parent_id = $this->resolve_synced_term_parent_path( $taxonomy, (string) $term_info['parent_slug'] );
			}
			if ( $parent_id !== $local_term_id ) {
				$args['parent'] = $parent_id;
			}
		}

		$updated = wp_update_term( $local_term_id, $taxonomy, $args );
		if ( is_wp_error( $updated ) ) {
			return 0;
		}

		return $local_term_id;
	}

	/**
	 * Prepare a taxonomy term payload for standalone taxonomy sync.
	 *
	 * @param \WP_Term $term     Term object.
	 * @param string   $taxonomy Taxonomy name.
	 * @return array
	 */
	private function prepare_term_for_sync( $term, $taxonomy ) {
		$data = array(
			'term_id'        => (int) $term->term_id,
			'name'           => (string) $term->name,
			'slug'           => (string) $term->slug,
			'taxonomy'       => $taxonomy,
			'description'    => (string) $term->description,
			'count'          => (int) $term->count,
			'parent_term_id' => (int) $term->parent,
			'parent_slug'    => $this->get_term_slug_by_id( (int) $term->parent, $taxonomy ),
			'parent_path'    => $this->get_term_parent_path( (int) $term->parent, $taxonomy ),
			'meta'           => array(),
		);

		$meta = get_term_meta( (int) $term->term_id );
		foreach ( $meta as $key => $values ) {
			$data['meta'][ $key ] = isset( $values[0] ) ? maybe_unserialize( $values[0] ) : '';
		}

		if ( function_exists( 'get_field_objects' ) ) {
			$acf_fields = $this->get_term_acf_field_objects( (int) $term->term_id, $taxonomy );
			if ( $acf_fields ) {
				$data['acf'] = array();
				foreach ( $acf_fields as $field_key => $field ) {
					$field_name                 = ! empty( $field['name'] ) ? (string) $field['name'] : (string) $field_key;
					$data['acf'][ $field_name ] = $this->export_term_acf_sync_value( (int) $term->term_id, $field_name, $field );
				}
			}
		}

		$this->append_wpml_term_sync_data( $data, (int) $term->term_id, $taxonomy );

		return $data;
	}

	/** Preserve taxonomy WYSIWYG shortcodes in sync payloads. */
	private function export_term_acf_sync_value( $term_id, $field_name, $field ) {
		$type = is_array( $field ) ? (string) ( $field['type'] ?? '' ) : '';
		if ( 'wysiwyg' === $type ) {
			$raw = get_term_meta( (int) $term_id, (string) $field_name, true );
			return class_exists( ACF_Fields::class )
				? ACF_Fields::export_string_with_media_shortcode_tokens( (string) $raw )
				: (string) $raw;
		}

		return ACF_Fields::export_value( 'term', (int) $term_id, (string) $field_name, '' );
	}

	/**
	 * Import synced taxonomy terms into the local site.
	 *
	 * @param string $taxonomy     Taxonomy name.
	 * @param array  $terms        Term payloads.
	 * @param array  $term_mapping Optional source remote term ID => local term ID mapping.
	 * @return array Counts.
	 */
	private function import_synced_terms( $taxonomy, $terms, $term_mapping = array() ) {
		$result                = array(
			'created' => 0,
			'updated' => 0,
			'failed'  => 0,
		);
		$source_to_local_terms = array();

		foreach ( $terms as $term_info ) {
			if ( ! is_array( $term_info ) ) {
				++$result['failed'];
				continue;
			}

			$source_term_id = ! empty( $term_info['term_id'] ) ? absint( $term_info['term_id'] ) : 0;
			$mapped_term_id = $source_term_id > 0 && isset( $term_mapping[ $source_term_id ] ) ? absint( $term_mapping[ $source_term_id ] ) : 0;
			$mapped_term    = $mapped_term_id > 0 ? get_term( $mapped_term_id, $taxonomy ) : null;
			$existing       = $mapped_term && ! is_wp_error( $mapped_term )
				? $mapped_term
				: ( ! empty( $term_info['slug'] ) ? get_term_by( 'slug', sanitize_title( (string) $term_info['slug'] ), $taxonomy ) : false );

			$term_id = $mapped_term && ! is_wp_error( $mapped_term )
				? $this->update_mapped_synced_term( $taxonomy, $mapped_term_id, $term_info )
				: $this->resolve_synced_term( $taxonomy, $term_info );
			if ( $term_id <= 0 ) {
				++$result['failed'];
				continue;
			}
			if ( $source_term_id > 0 ) {
				$source_to_local_terms[ $source_term_id ] = (int) $term_id;
			}

			$args = array();
			if ( isset( $term_info['description'] ) ) {
				$args['description'] = wp_kses_post( (string) $term_info['description'] );
			}
			if ( ! empty( $args ) ) {
				wp_update_term( $term_id, $taxonomy, $args );
			}

			if ( ! empty( $term_info['meta'] ) && is_array( $term_info['meta'] ) ) {
				foreach ( $term_info['meta'] as $meta_key => $meta_value ) {
					update_term_meta( $term_id, sanitize_key( (string) $meta_key ), $meta_value );
				}
			}

			if ( ! empty( $term_info['acf'] ) && is_array( $term_info['acf'] ) && function_exists( 'update_field' ) ) {
				foreach ( $term_info['acf'] as $field_key => $field_value ) {
					ACF_Fields::import_value( 'term', $term_id, sanitize_text_field( (string) $field_key ), $field_value, $taxonomy );
				}
			}

			if ( $existing ) {
				++$result['updated'];
			} else {
				++$result['created'];
			}
		}

		$this->apply_synced_terms_wpml_data( $taxonomy, $terms, $source_to_local_terms );

		return $result;
	}

	/**
	 * Collect media referenced by a term's ACF payload.
	 *
	 * @param array    $term_info  Prepared term payload.
	 * @param \WP_Term $term       Term object.
	 * @param string   $taxonomy   Taxonomy.
	 * @param array    $all_images Accumulator keyed by attachment ID.
	 * @return void
	 */
	private function collect_term_acf_images_for_sync( array $term_info, $term, $taxonomy, array &$all_images ) {
		$image_ids = array();
		if ( ! empty( $term_info['description'] ) && is_string( $term_info['description'] ) ) {
			$image_ids = array_merge( $image_ids, $this->extract_term_acf_images( array( 'description' => $term_info['description'] ) ) );
		}
		if ( ! empty( $term_info['acf'] ) && is_array( $term_info['acf'] ) ) {
			$image_ids = array_merge( $image_ids, $this->extract_term_acf_images( $term_info['acf'] ) );
		}
		$image_ids = array_values( array_unique( array_filter( array_map( 'absint', $image_ids ) ) ) );
		if ( empty( $image_ids ) ) {
			return;
		}

		foreach ( $image_ids as $image_id ) {
			if ( isset( $all_images[ $image_id ] ) ) {
				continue;
			}

			$image_data = \RockStarLab\ImportExport\Helper\Content_Sync_Media::prepare_image_data( $image_id, 'term_acf' );
			if ( ! $image_data ) {
				$image_data = array(
					'attachment_id' => $image_id,
					'url'           => wp_get_attachment_url( $image_id ),
					'type'          => 'term_acf',
				);
			}

			$image_data['term_id']   = (int) $term->term_id;
			$image_data['taxonomy']  = $taxonomy;
			$all_images[ $image_id ] = $image_data;
		}
	}

	private function get_term_acf_field_objects( $term_id, $taxonomy ) {
		if ( ! function_exists( 'get_field_objects' ) ) {
			return false;
		}

		$fields = get_field_objects( 'term_' . (int) $term_id );
		if ( ! empty( $fields ) ) {
			return $fields;
		}

		return get_field_objects( sanitize_key( (string) $taxonomy ) . '_' . (int) $term_id );
	}

	/**
	 * Replace media URLs and attachment IDs inside term ACF payloads.
	 *
	 * @param array  $terms         Term payloads.
	 * @param string $source_domain Source site URL.
	 * @param string $target_domain Target site URL.
	 * @param array  $image_map     Source attachment ID => target attachment ID.
	 * @param array  $image_sources Source image metadata.
	 * @return array
	 */
	private function replace_term_acf_media_references( array $terms, $source_domain, $target_domain, array $image_map, array $image_sources ) {
		foreach ( $terms as &$term_info ) {
			if ( isset( $term_info['description'] ) && is_string( $term_info['description'] ) && ! empty( $image_map ) ) {
				$term_info['description'] = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::fix_local_image_urls_in_content(
					$term_info['description'],
					$image_map,
					$image_sources
				);
			}

			if ( empty( $term_info['acf'] ) || ! is_array( $term_info['acf'] ) ) {
				continue;
			}

			$term_info['acf'] = $this->replace_term_acf_value_media_references(
				$term_info['acf'],
				$source_domain,
				$target_domain,
				$image_map,
				$image_sources
			);
		}
		unset( $term_info );

		return $terms;
	}

	/**
	 * Replace media references inside one ACF value.
	 *
	 * @param mixed  $value         ACF value.
	 * @param string $source_domain Source site URL.
	 * @param string $target_domain Target site URL.
	 * @param array  $image_map     Source attachment ID => target attachment ID.
	 * @param array  $image_sources Source image metadata.
	 * @return mixed
	 */
	private function replace_term_acf_value_media_references( $value, $source_domain, $target_domain, array $image_map, array $image_sources ) {
		if ( is_array( $value ) ) {
			$out = array();
			foreach ( $value as $key => $child ) {
				if ( is_numeric( $child ) && isset( $image_map[ (int) $child ] ) && $this->looks_like_term_acf_media_key( $key ) ) {
					$out[ $key ] = (int) $image_map[ (int) $child ];
					continue;
				}

				$out[ $key ] = $this->replace_term_acf_value_media_references(
					$child,
					$source_domain,
					$target_domain,
					$image_map,
					$image_sources
				);
			}

			if ( isset( $out['id'] ) && is_numeric( $out['id'] ) && isset( $image_map[ (int) $out['id'] ] ) ) {
				$mapped_id = (int) $image_map[ (int) $out['id'] ];
				$out['id'] = $mapped_id;
				$url       = wp_get_attachment_url( $mapped_id );
				if ( $url && isset( $out['url'] ) ) {
					$out['url'] = $url;
				}
			}

			return $out;
		}

		if ( is_string( $value ) && '' !== $value ) {
			$decoded = json_decode( $value, true );
			if ( is_array( $decoded ) ) {
				return wp_json_encode(
					$this->replace_term_acf_value_media_references(
						$decoded,
						$source_domain,
						$target_domain,
						$image_map,
						$image_sources
					)
				);
			}

			$mapped_url = $this->get_mapped_acf_media_url( $value, $image_map, $image_sources );
			if ( '' !== $mapped_url ) {
				return $mapped_url;
			}

			// Resolve portable gallery/playlist tokens back to shortcodes and
			// replace their source IDs with the downloaded local attachment IDs.
			if ( class_exists( ACF_Fields::class ) && false !== strpos( $value, '[[RSL_IE:' ) ) {
				return ACF_Fields::replace_media_urls_in_html( $value );
			}

			return \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::fix_local_image_urls_in_content(
				$value,
				$image_map,
				$image_sources
			);
		}

		return $value;
	}

	private function looks_like_term_acf_media_key( $key ) {
		if ( is_int( $key ) ) {
			return true;
		}

		return in_array(
			(string) $key,
			array( 'id', 'ID', 'attachment_id', 'image_id', 'media_id', 'file_id' ),
			true
		);
	}

	/**
	 * Return the target attachment URL for a source media URL when it is mapped.
	 *
	 * @param string $url           Source URL.
	 * @param array  $image_map     Source attachment ID => target attachment ID.
	 * @param array  $image_sources Source image metadata.
	 * @return string
	 */
	private function get_mapped_acf_media_url( $url, array $image_map, array $image_sources ) {
		$url = trim( html_entity_decode( (string) $url, ENT_QUOTES, get_bloginfo( 'charset' ) ) );
		if ( '' === $url || empty( $image_map ) || empty( $image_sources ) ) {
			return '';
		}

		foreach ( $image_sources as $source_id => $source ) {
			if ( ! is_array( $source ) ) {
				continue;
			}

			$attachment_id = isset( $source['attachment_id'] ) ? (int) $source['attachment_id'] : (int) $source_id;
			if ( $attachment_id <= 0 || empty( $image_map[ $attachment_id ] ) ) {
				continue;
			}

			$source_urls = array_filter(
				array(
					isset( $source['url'] ) ? (string) $source['url'] : '',
					isset( $source['full_url'] ) ? (string) $source['full_url'] : '',
				)
			);

			foreach ( $source_urls as $source_url ) {
				if ( $url !== html_entity_decode( $source_url, ENT_QUOTES, get_bloginfo( 'charset' ) ) ) {
					continue;
				}

				$target_url = wp_get_attachment_url( (int) $image_map[ $attachment_id ] );
				return $target_url ? (string) $target_url : '';
			}
		}

		return '';
	}

	/**
	 * Resolve or create parent terms from a slash-separated slug path.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @param string $path     Parent slug path.
	 * @return int Final parent term ID.
	 */
	private function resolve_synced_term_parent_path( $taxonomy, $path ) {
		$slugs     = array_filter( array_map( 'sanitize_title', explode( '/', (string) $path ) ) );
		$parent_id = 0;

		foreach ( $slugs as $slug ) {
			$term = get_term_by( 'slug', $slug, $taxonomy );
			if ( $term && ! is_wp_error( $term ) ) {
				if ( (int) $term->parent !== $parent_id ) {
					wp_update_term( (int) $term->term_id, $taxonomy, array( 'parent' => $parent_id ) );
				}
				$parent_id = (int) $term->term_id;
				continue;
			}

			$new_term = wp_insert_term(
				ucwords( str_replace( array( '-', '_' ), ' ', $slug ) ),
				$taxonomy,
				array(
					'slug'   => $slug,
					'parent' => $parent_id,
				)
			);
			if ( is_wp_error( $new_term ) || empty( $new_term['term_id'] ) ) {
				return $parent_id;
			}
			$parent_id = (int) $new_term['term_id'];
		}

		return $parent_id;
	}

	/**
	 * Get term slug by ID.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy name.
	 * @return string Term slug.
	 */
	private function get_term_slug_by_id( $term_id, $taxonomy ) {
		if ( $term_id <= 0 ) {
			return '';
		}
		$term = get_term( (int) $term_id, $taxonomy );
		return ( $term && ! is_wp_error( $term ) ) ? (string) $term->slug : '';
	}

	/**
	 * Get a slash-separated parent slug path for a term parent.
	 *
	 * @param int    $parent_id Parent term ID.
	 * @param string $taxonomy  Taxonomy name.
	 * @return string Parent path.
	 */
	private function get_term_parent_path( $parent_id, $taxonomy ) {
		if ( $parent_id <= 0 || ! taxonomy_exists( $taxonomy ) ) {
			return '';
		}

		$ancestors   = array_reverse( get_ancestors( (int) $parent_id, $taxonomy, 'taxonomy' ) );
		$ancestors[] = (int) $parent_id;

		$slugs = array();
		foreach ( $ancestors as $ancestor_id ) {
			$slug = $this->get_term_slug_by_id( (int) $ancestor_id, $taxonomy );
			if ( '' !== $slug ) {
				$slugs[] = $slug;
			}
		}

		return implode( '/', $slugs );
	}

	/**
	 * Ensure a WooCommerce global product attribute taxonomy exists in this request.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return bool Whether the taxonomy is available.
	 */
	private function ensure_woocommerce_attribute_taxonomy( $taxonomy ) {
		if ( ! is_string( $taxonomy ) || 0 !== strpos( $taxonomy, 'pa_' ) ) {
			return taxonomy_exists( $taxonomy );
		}

		if ( taxonomy_exists( $taxonomy ) ) {
			return true;
		}

		if ( ! function_exists( 'wc_create_attribute' ) || ! function_exists( 'wc_sanitize_taxonomy_name' ) ) {
			return false;
		}

		$attribute_name = wc_sanitize_taxonomy_name( substr( $taxonomy, 3 ) );
		if ( '' === $attribute_name ) {
			return false;
		}

		$attribute_id = function_exists( 'wc_attribute_taxonomy_id_by_name' ) ? wc_attribute_taxonomy_id_by_name( $attribute_name ) : 0;
		if ( ! $attribute_id ) {
			$attribute_id = wc_create_attribute(
				array(
					'name'         => ucwords( str_replace( array( '-', '_' ), ' ', $attribute_name ) ),
					'slug'         => $attribute_name,
					'type'         => 'select',
					'order_by'     => 'menu_order',
					'has_archives' => false,
				)
			);

			if ( is_wp_error( $attribute_id ) ) {
				return false;
			}

			delete_transient( 'wc_attribute_taxonomies' );
		}

		register_taxonomy(
			$taxonomy,
			apply_filters( 'woocommerce_taxonomy_objects_' . $taxonomy, array( 'product' ) ),
			apply_filters(
				'woocommerce_taxonomy_args_' . $taxonomy,
				array(
					'labels'       => array(
						'name' => ucwords( str_replace( array( '-', '_' ), ' ', $attribute_name ) ),
					),
					'hierarchical' => true,
					'show_ui'      => false,
					'query_var'    => true,
					'rewrite'      => false,
				)
			)
		);

		return taxonomy_exists( $taxonomy );
	}

	/**
	 * Refresh WooCommerce product caches and lookup tables after direct meta sync.
	 *
	 * @param int $post_id Product post ID.
	 * @return void
	 */
	private function refresh_woocommerce_product_after_sync( $post_id ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		clean_post_cache( $post_id );

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $post_id );
		}

		$product = wc_get_product( $post_id );
		if ( ! $product ) {
			return;
		}

		if ( $product->is_type( 'variable' ) && class_exists( 'WC_Product_Variable' ) ) {
			\WC_Product_Variable::sync( $product );
		}

		$product->save();

		if ( function_exists( 'wc_update_product_lookup_tables' ) ) {
			wc_update_product_lookup_tables( $post_id );
		}

		if ( function_exists( 'wc_delete_product_transients' ) ) {
			wc_delete_product_transients( $post_id );
		}
	}

	/**
	 * Extract image IDs from term ACF fields
	 *
	 * @param array $acf_data ACF field data.
	 * @return array Array of image IDs
	 */
	private function extract_term_acf_images( $acf_data ) {
		$image_ids = array();

		foreach ( $acf_data as $key => $value ) {
			if ( is_string( $value ) && '' !== $value ) {
				$decoded = json_decode( $value, true );
				if ( is_array( $decoded ) ) {
					$image_ids = array_merge( $image_ids, $this->extract_term_acf_images( $decoded ) );
				}

				if ( class_exists( ACF_Fields::class ) ) {
					foreach ( ACF_Fields::extract_media_shortcode_token_source_ids( $value ) as $image_id ) {
						$image_ids[] = (int) $image_id;
					}
				}

				if ( preg_match_all( '/\bwp-image-(\d+)\b/', $value, $matches ) ) {
					foreach ( $matches[1] as $image_id ) {
						$image_ids[] = (int) $image_id;
					}
				}

				if ( preg_match_all( '/\[(gallery|playlist)\b[^\]]*\bids=["\']([\d,\s]+)["\'][^\]]*\]/i', $value, $matches ) ) {
					foreach ( $matches[2] as $ids_string ) {
						foreach ( array_filter( array_map( 'absint', preg_split( '/\s*,\s*/', (string) $ids_string ) ?: array() ) ) as $attachment_id ) {
							$image_ids[] = (int) $attachment_id;
						}
					}
				}

				foreach ( $this->extract_image_urls_from_term_acf_html( $value ) as $url ) {
					$image_id = attachment_url_to_postid( $url );
					if ( $image_id > 0 ) {
						$image_ids[] = (int) $image_id;
					}
				}

				if ( filter_var( $value, FILTER_VALIDATE_URL ) && $this->is_term_acf_image_url( $value ) ) {
					$image_id = attachment_url_to_postid( $value );
					if ( $image_id > 0 ) {
						$image_ids[] = (int) $image_id;
					}
				}
			}

			if ( is_array( $value ) ) {
				foreach ( array( 'ID', 'id', 'attachment_id' ) as $id_key ) {
					if ( isset( $value[ $id_key ] ) && is_numeric( $value[ $id_key ] ) ) {
						$image_id   = (int) $value[ $id_key ];
						$attachment = get_post( $image_id );
						if ( $attachment && 'attachment' === $attachment->post_type ) {
							$image_ids[] = $image_id;
						}
					}
				}

				if ( isset( $value['url'] ) && is_string( $value['url'] ) && $this->is_term_acf_image_url( $value['url'] ) ) {
					$image_id = attachment_url_to_postid( $value['url'] );
					if ( $image_id > 0 ) {
						$image_ids[] = (int) $image_id;
					}
				}
			}

			// Single image field (numeric ID)
			if ( is_numeric( $value ) && $value > 0 ) {
				$attachment = get_post( $value );
				if ( $attachment && 'attachment' === $attachment->post_type ) {
					$image_ids[] = (int) $value;
				}
			}
			// Gallery field (array of IDs)
			elseif ( is_array( $value ) ) {
				foreach ( $value as $item ) {
					if ( is_numeric( $item ) && $item > 0 ) {
						$attachment = get_post( $item );
						if ( $attachment && 'attachment' === $attachment->post_type ) {
							$image_ids[] = (int) $item;
						}
					}
					// Nested arrays (repeater, flexible content)
					elseif ( is_array( $item ) ) {
						$nested_images = $this->extract_term_acf_images( $item );
						$image_ids     = array_merge( $image_ids, $nested_images );
					}
				}
			}
		}

		return array_unique( $image_ids );
	}

	private function extract_image_urls_from_term_acf_html( $content ) {
		$urls = array();

		if ( false === stripos( $content, '<img' ) && false === stripos( $content, 'srcset=' ) ) {
			return $urls;
		}

		if ( preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches ) ) {
			foreach ( $matches[1] as $url ) {
				$url = html_entity_decode( $url, ENT_QUOTES, get_bloginfo( 'charset' ) );
				if ( $this->is_term_acf_image_url( $url ) ) {
					$urls[] = $url;
				}
			}
		}

		if ( preg_match_all( '/srcset=["\']([^"\']+)["\']/i', $content, $matches ) ) {
			foreach ( $matches[1] as $srcset ) {
				foreach ( array_map( 'trim', explode( ',', $srcset ) ) as $candidate ) {
					$parts = preg_split( '/\s+/', $candidate );
					$url   = isset( $parts[0] ) ? html_entity_decode( $parts[0], ENT_QUOTES, get_bloginfo( 'charset' ) ) : '';
					if ( $this->is_term_acf_image_url( $url ) ) {
						$urls[] = $url;
					}
				}
			}
		}

		return array_values( array_unique( $urls ) );
	}

	private function is_term_acf_image_url( $url ) {
		$path = (string) wp_parse_url( html_entity_decode( (string) $url, ENT_QUOTES, get_bloginfo( 'charset' ) ), PHP_URL_PATH );
		return '' !== $path && (bool) preg_match( '~\.(?:jpe?g|png|gif|webp|avif|svg)$~i', $path );
	}

	/**
	 * Append WPML language data to a post sync payload.
	 *
	 * @param array  $post_data Post sync payload, passed by reference.
	 * @param int    $post_id   Source post ID.
	 * @param string $post_type Source post type.
	 * @return void
	 */
	private function append_wpml_post_sync_data( array &$post_data, $post_id, $post_type ) {
		if ( ! WPML_Compatibility::is_active() ) {
			return;
		}

		$wpml_data = WPML_Compatibility::export_post_data( (int) $post_id, (string) $post_type );
		if ( ! empty( $wpml_data ) ) {
			$post_data['wpml'] = $wpml_data;
		}
	}

	/**
	 * Append WPML language data to a term sync payload.
	 *
	 * @param array  $term_info Term sync payload, passed by reference.
	 * @param int    $term_id   Source term ID.
	 * @param string $taxonomy  Taxonomy name.
	 * @return void
	 */
	private function append_wpml_term_sync_data( array &$term_info, $term_id, $taxonomy ) {
		if ( ! WPML_Compatibility::is_active() ) {
			return;
		}

		$wpml_data = WPML_Compatibility::export_term_data( (int) $term_id, (string) $taxonomy );
		if ( ! empty( $wpml_data ) ) {
			$term_info['wpml'] = $wpml_data;
		}
	}

	/**
	 * Apply WPML language data received through content sync.
	 *
	 * @param int   $post_id       Target post ID.
	 * @param array $post_data     Incoming post payload.
	 * @param array $source_id_map Source post ID => target post ID.
	 * @return void
	 */
	private function apply_synced_post_wpml_data( $post_id, array $post_data, array $source_id_map ) {
		if ( ! WPML_Compatibility::is_active() || empty( $post_data['wpml'] ) || ! is_array( $post_data['wpml'] ) ) {
			return;
		}

		WPML_Compatibility::apply_post_language_details( (int) $post_id, $post_data['wpml'], $source_id_map );
	}

	/**
	 * Apply WPML data for a set of synced posts after the full ID map is known.
	 *
	 * @param array $posts_data    Incoming posts payload.
	 * @param array $source_id_map Source post ID => target post ID.
	 * @return void
	 */
	private function apply_synced_posts_wpml_data( array $posts_data, array $source_id_map ) {
		if ( ! WPML_Compatibility::is_active() || empty( $source_id_map ) ) {
			return;
		}

		usort(
			$posts_data,
			static function ( $left, $right ) {
				$left_wpml       = isset( $left['wpml'] ) && is_array( $left['wpml'] ) ? $left['wpml'] : [];
				$right_wpml      = isset( $right['wpml'] ) && is_array( $right['wpml'] ) ? $right['wpml'] : [];
				$left_is_source  = empty( $left_wpml['source_language_code'] ) || 'source' === ( $left_wpml['translation_role'] ?? '' );
				$right_is_source = empty( $right_wpml['source_language_code'] ) || 'source' === ( $right_wpml['translation_role'] ?? '' );

				if ( $left_is_source === $right_is_source ) {
					return 0;
				}

				return $left_is_source ? -1 : 1;
			}
		);

		foreach ( $posts_data as $post_data ) {
			$source_id = absint( $post_data['ID'] ?? 0 );
			$target_id = $source_id > 0 ? absint( $source_id_map[ $source_id ] ?? 0 ) : 0;
			if ( $target_id <= 0 ) {
				continue;
			}

			$this->apply_synced_post_wpml_data( $target_id, (array) $post_data, $source_id_map );
		}
	}

	/**
	 * Apply WPML language data received for a synced term.
	 *
	 * @param int    $term_id       Target term ID.
	 * @param string $taxonomy      Taxonomy name.
	 * @param array  $term_info     Incoming term payload.
	 * @param array  $source_id_map Source term ID => target term ID.
	 * @return void
	 */
	private function apply_synced_term_wpml_data( $term_id, $taxonomy, array $term_info, array $source_id_map ) {
		if ( ! WPML_Compatibility::is_active() || empty( $term_info['wpml'] ) || ! is_array( $term_info['wpml'] ) ) {
			return;
		}

		WPML_Compatibility::apply_term_language_details( (int) $term_id, (string) $taxonomy, $term_info['wpml'], $source_id_map );
	}

	/**
	 * Apply WPML data for synced terms once all target term IDs are known.
	 *
	 * @param string $taxonomy      Taxonomy name.
	 * @param array  $terms         Incoming term payloads.
	 * @param array  $source_id_map Source term ID => target term ID.
	 * @return void
	 */
	private function apply_synced_terms_wpml_data( $taxonomy, array $terms, array $source_id_map ) {
		if ( ! WPML_Compatibility::is_active() || empty( $source_id_map ) ) {
			return;
		}

		usort(
			$terms,
			static function ( $left, $right ) {
				$left_wpml       = isset( $left['wpml'] ) && is_array( $left['wpml'] ) ? $left['wpml'] : [];
				$right_wpml      = isset( $right['wpml'] ) && is_array( $right['wpml'] ) ? $right['wpml'] : [];
				$left_is_source  = empty( $left_wpml['source_language_code'] ) || 'source' === ( $left_wpml['translation_role'] ?? '' );
				$right_is_source = empty( $right_wpml['source_language_code'] ) || 'source' === ( $right_wpml['translation_role'] ?? '' );

				if ( $left_is_source === $right_is_source ) {
					return 0;
				}

				return $left_is_source ? -1 : 1;
			}
		);

		foreach ( $terms as $term_info ) {
			$source_id = absint( $term_info['term_id'] ?? 0 );
			$target_id = $source_id > 0 ? absint( $source_id_map[ $source_id ] ?? 0 ) : 0;
			if ( $target_id <= 0 ) {
				continue;
			}

			$this->apply_synced_term_wpml_data( $target_id, $taxonomy, (array) $term_info, $source_id_map );
		}
	}
}

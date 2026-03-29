<?php
/**
 * Content Sync Controller
 *
 * Handles AJAX requests for content synchronization between sites
 *
 * @package WP_AIE\Controller
 */

namespace WP_AIE\Controller;

defined( 'ABSPATH' ) || exit;

use WP_AIE\Model\Connected_Site;

/**
 * Content Sync Controller Class
 *
 * Manages connected sites and synchronization operations.
 *
 * @package WP_AIE\Controller
 */
class Content_Sync_Controller extends Base_Controller {

	/**
	 * Get AJAX actions to register
	 *
	 * @return array Array of action => config
	 */
	protected function get_ajax_actions() {
		return array(
			'content_sync_get_sites'             => array( 'callback' => 'get_sites' ),
			'content_sync_add_site'              => array( 'callback' => 'add_site' ),
			'content_sync_update_site'           => array( 'callback' => 'update_site' ),
			'content_sync_delete_site'           => array( 'callback' => 'delete_site' ),
			'content_sync_regenerate_key'        => array( 'callback' => 'regenerate_key' ),
			'content_sync_test_connection'       => array( 'callback' => 'test_connection' ),
			'content_sync_get_my_key'            => array( 'callback' => 'get_my_site_key' ),
			'content_sync_regenerate_my_key'     => array( 'callback' => 'regenerate_my_site_key' ),
			'content_sync_get_remote_posts'      => array( 'callback' => 'get_remote_posts' ),
			'content_sync_search_remote_posts'   => array( 'callback' => 'search_remote_posts' ),
			'content_sync_get_children_posts'    => array( 'callback' => 'get_children_posts' ),
			'content_sync_get_local_posts_info'  => array( 'callback' => 'get_local_posts_info' ),
			'content_sync_push'                  => array( 'callback' => 'push_content' ),
			'content_sync_pull'                  => array( 'callback' => 'pull_content' ),
		);
	}

	/**
	 * Get all connected sites
	 */
	public function get_sites() {
		$verify = $this->verify_request( 'content_sync_get_sites' );
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
		$verify = $this->verify_request( 'content_sync_add_site' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$name       = $this->get_request_param( 'name', '' );
		$remote_url = $this->get_request_param( 'remote_url', '' );
		$api_key    = $this->get_request_param( 'api_key', '' );
		$direction  = $this->get_request_param( 'direction', 'bidirectional' );

		// Validate input
		if ( empty( $name ) ) {
			$this->send_error( __( 'Site name is required', 'wp-advanced-import-export' ) );
		}

		if ( empty( $remote_url ) ) {
			$this->send_error( __( 'Remote URL is required', 'wp-advanced-import-export' ) );
		}

		if ( empty( $api_key ) ) {
			$this->send_error( __( 'API key is required', 'wp-advanced-import-export' ) );
		}

		// Validate URL
		$remote_url = esc_url_raw( $remote_url );
		if ( ! filter_var( $remote_url, FILTER_VALIDATE_URL ) ) {
			$this->send_error( __( 'Invalid URL format', 'wp-advanced-import-export' ) );
		}

		// Check if URL already exists
		if ( Connected_Site::exists_by_url( $remote_url ) ) {
			$this->send_error( __( 'This site is already connected', 'wp-advanced-import-export' ) );
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
			$this->send_error( __( 'Failed to add site connection', 'wp-advanced-import-export' ) );
		}

		$site = Connected_Site::get_by_id( $site_id );

		$this->send_success(
			array(
				'message' => __( 'Site connection added successfully', 'wp-advanced-import-export' ),
				'site'    => $site,
			)
		);
	}

	/**
	 * Update site connection
	 */
	public function update_site() {
		$verify = $this->verify_request( 'content_sync_update_site' );
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
			$this->send_error( __( 'Site ID is required', 'wp-advanced-import-export' ) );
		}

		// Check if site exists
		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			$this->send_error( __( 'Site not found', 'wp-advanced-import-export' ) );
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
				$this->send_error( __( 'Invalid URL format', 'wp-advanced-import-export' ) );
			}

			// Check if URL already exists (excluding current site)
			if ( Connected_Site::exists_by_url( $remote_url, $site_id ) ) {
				$this->send_error( __( 'This site is already connected', 'wp-advanced-import-export' ) );
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
					'message' => __( 'No changes were made', 'wp-advanced-import-export' ),
					'site'    => $site,
				)
			);
		}

		$result = Connected_Site::update( $site_id, $data );

		if ( ! $result ) {
			$this->send_error( __( 'Failed to update site connection', 'wp-advanced-import-export' ) );
		}

		$updated_site = Connected_Site::get_by_id( $site_id );

		// Create detailed message about what was updated
		$updated_fields = array_keys( $data );
		$message        = __( 'Site connection updated successfully', 'wp-advanced-import-export' );
		
		if ( count( $updated_fields ) === 1 ) {
			$field_name = $updated_fields[0];
			$field_labels = array(
				'name'       => __( 'name', 'wp-advanced-import-export' ),
				'remote_url' => __( 'URL', 'wp-advanced-import-export' ),
				'direction'  => __( 'direction', 'wp-advanced-import-export' ),
				'status'     => __( 'status', 'wp-advanced-import-export' ),
				'api_key'    => __( 'API key', 'wp-advanced-import-export' ),
			);
			
			if ( isset( $field_labels[ $field_name ] ) ) {
				$message = sprintf(
					/* translators: %s: field name that was updated */
					__( 'Site %s updated successfully', 'wp-advanced-import-export' ),
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
		$verify = $this->verify_request( 'content_sync_delete_site' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$site_id = $this->get_request_param( 'site_id', 0 );

		if ( ! $site_id ) {
			$this->send_error( __( 'Site ID is required', 'wp-advanced-import-export' ) );
		}

		// Check if site exists
		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			$this->send_error( __( 'Site not found', 'wp-advanced-import-export' ) );
		}

		$result = Connected_Site::delete( $site_id );

		if ( ! $result ) {
			$this->send_error( __( 'Failed to delete site connection', 'wp-advanced-import-export' ) );
		}

		$this->send_success(
			array(
				'message' => __( 'Site connection deleted successfully', 'wp-advanced-import-export' ),
			)
		);
	}

	/**
	 * Regenerate API key for a site
	 */
	public function regenerate_key() {
		$verify = $this->verify_request( 'content_sync_regenerate_key' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$site_id = $this->get_request_param( 'site_id', 0 );

		if ( ! $site_id ) {
			$this->send_error( __( 'Site ID is required', 'wp-advanced-import-export' ) );
		}

		// Check if site exists
		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			$this->send_error( __( 'Site not found', 'wp-advanced-import-export' ) );
		}

		$new_key = Connected_Site::regenerate_api_key( $site_id );

		if ( ! $new_key ) {
			$this->send_error( __( 'Failed to regenerate API key', 'wp-advanced-import-export' ) );
		}

		$this->send_success(
			array(
				'message' => __( 'API key regenerated successfully', 'wp-advanced-import-export' ),
				'api_key' => $new_key,
			)
		);
	}

	/**
	 * Test connection to remote site
	 */
	public function test_connection() {
		$verify = $this->verify_request( 'content_sync_test_connection' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$site_id = $this->get_request_param( 'site_id', 0 );

		if ( ! $site_id ) {
			$this->send_error( __( 'Site ID is required', 'wp-advanced-import-export' ) );
		}

		// Check if site exists
		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			$this->send_error( __( 'Site not found', 'wp-advanced-import-export' ) );
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
				'message' => __( 'Connection successful. API key is valid.', 'wp-advanced-import-export' ),
			)
		);
	}

	/**
	 * Get this site's API key for incoming connections
	 */
	public function get_my_site_key() {
		$verify = $this->verify_request( 'content_sync_get_my_key' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		// Get or create API key for this site
		$site_key = get_option( 'aie_site_api_key' );

		if ( ! $site_key ) {
			// Generate new API key for this site
			$site_key = Connected_Site::generate_api_key();
			update_option( 'aie_site_api_key', $site_key );
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
		$verify = $this->verify_request( 'content_sync_regenerate_my_key' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		// Generate new API key
		$new_key = Connected_Site::generate_api_key();
		update_option( 'aie_site_api_key', $new_key );

		$this->send_success(
			array(
				'message'  => __( 'API key regenerated successfully. All remote sites will need to update their connection with the new key.', 'wp-advanced-import-export' ),
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
		// Build the validation endpoint URL
		$endpoint_url = trailingslashit( $remote_url ) . 'wp-json/aie/v1/validate';

		// Make request to remote site
		$response = wp_remote_post(
			$endpoint_url,
			array(
				'timeout' => 15,
				'headers' => array(
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $api_key,
				),
				'body' => wp_json_encode(
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
					__( 'Cannot connect to remote site: %s', 'wp-advanced-import-export' ),
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
				__( 'Advanced Import Export plugin is not installed or activated on the remote site.', 'wp-advanced-import-export' )
			);
		}

		// Try to parse JSON response first to check for license errors
		$data = json_decode( $body, true );
		
		if ( 403 === $status_code ) {
			// Check if it's a license error
			if ( is_array( $data ) && isset( $data['error_code'] ) && 'license_inactive' === $data['error_code'] ) {
				return new \WP_Error(
					'license_inactive',
					__( 'Premium license is not active on the remote site. Content Sync requires an active premium license.', 'wp-advanced-import-export' )
				);
			}
			
			return new \WP_Error(
				'invalid_api_key',
				__( 'Access forbidden. Please check the API key and try again.', 'wp-advanced-import-export' )
			);
		}

		if ( 401 === $status_code ) {
			return new \WP_Error(
				'invalid_api_key',
				__( 'Invalid API key. Please check the API key and try again.', 'wp-advanced-import-export' )
			);
		}

		if ( $status_code < 200 || $status_code >= 300 ) {
			return new \WP_Error(
				'connection_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'Remote site returned error status: %d', 'wp-advanced-import-export' ),
					$status_code
				)
			);
		}

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error(
				'invalid_response',
				__( 'Remote site returned invalid response format.', 'wp-advanced-import-export' )
			);
		}

		// Check if response indicates success
		if ( ! isset( $data['success'] ) || ! $data['success'] ) {
			$error_message = isset( $data['message'] ) ? $data['message'] : __( 'Unknown error from remote site.', 'wp-advanced-import-export' );
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
			$post_status = $this->get_request_param( 'post_status', '' );
			if ( 'trash' === $post_status ) {
				return;
			}
		}

		// Check if premium is active
		$is_premium = function_exists( 'waie_fs' ) && waie_fs()->can_use_premium_code();
		
		// Get current post type
		global $typenow, $post;
		$current_post_type = '';
		
		if ( 'edit.php' === $hook_suffix && ! empty( $typenow ) ) {
			$current_post_type = $typenow;
		} elseif ( in_array( $hook_suffix, array( 'post.php', 'post-new.php' ) ) && $post ) {
			$current_post_type = $post->post_type;
		}
		
		// In free version, only load for 'post' type
		if ( ! $is_premium && 'post' !== $current_post_type ) {
			return;
		}

		// Enqueue post sync script
		wp_enqueue_script(
			'aie-post-sync',
			plugins_url( 'assets/js/post-sync-standalone.js', WP_AIE_FILE ),
			array( 'jquery' ),
			filemtime( plugin_dir_path( WP_AIE_FILE ) . 'assets/js/post-sync-standalone.js' ),
			true
		);

		// Localize script
		$nonce = wp_create_nonce( 'aie_nonce' );
		
		// Get connected sites for Select2 AJAX
		$sites = Connected_Site::get_all();
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
			'aie-post-sync',
			'aiePostSyncData',
			array(
				'nonce'          => $nonce,
				'ajaxurl'        => admin_url( 'admin-ajax.php' ),
				'connectedSites' => $sites_map,
				'i18n'           => array(
					// Alerts & Messages
					'pleaseSavePost'              => __( 'Please save the post first', 'wp-advanced-import-export' ),
					'pleaseSelectSite'            => __( 'Please select a site', 'wp-advanced-import-export' ),
					'noPostsSelected'             => __( 'No posts selected', 'wp-advanced-import-export' ),
					'failedLoadRemotePosts'       => __( 'Failed to load remote posts', 'wp-advanced-import-export' ),
					'unknownError'                => __( 'Unknown error', 'wp-advanced-import-export' ),
					'failedConnectRemote'         => __( 'Failed to connect to remote site', 'wp-advanced-import-export' ),
					'failedLoadLocalPosts'        => __( 'Failed to load local posts info', 'wp-advanced-import-export' ),
					'pleaseSelectOnePost'         => __( 'Please select at least one post', 'wp-advanced-import-export' ),
					
					// translators: %s = content placeholder.
					// Count Text
					'onePost'                     => __( '1 post', 'wp-advanced-import-export' ),
					// translators: %s is a dynamic value.
					'postsCount'                  => __( '%s posts', 'wp-advanced-import-export' ),
					
					// Post Info
					// translators: %s is a dynamic value.
					'postHash'                    => __( 'Post #%s', 'wp-advanced-import-export' ),
					// translators: %s = content placeholder.
					'idLabel'                     => __( 'ID:', 'wp-advanced-import-export' ),
					'noTitle'                     => __( '(No title)', 'wp-advanced-import-export' ),
					
					// translators: %s = content placeholder.
					// Actions
					'createNewPost'               => __( '➕ Create New Post', 'wp-advanced-import-export' ),
					// translators: 1: post title or name, 2: post ID.
					'updatePost'                  => __( '🔄 Update: %1$s (ID: %2$s)', 'wp-advanced-import-export' ),
					// translators: %s is a dynamic value.
					'searchForUpdate'             => __( 'Search for a %s to update...', 'wp-advanced-import-export' ),
					
					// Progress
					// translators: %s is a dynamic value.
					'starting'                    => __( 'Starting %s...', 'wp-advanced-import-export' ),
					'completed'                   => __( 'Completed!', 'wp-advanced-import-export' ),
					'syncCompletedSuccess'        => __( 'Sync completed successfully', 'wp-advanced-import-export' ),
					'pullingPosts'                => __( 'Pulling posts...', 'wp-advanced-import-export' ),
					'syncFailed'                  => __( 'Sync failed', 'wp-advanced-import-export' ),
					'errorDuringSync'             => __( 'An error occurred during sync', 'wp-advanced-import-export' ),
					
					// Browse
					'noPostsFound'                => __( 'No posts found', 'wp-advanced-import-export' ),
					'child'                       => __( 'child', 'wp-advanced-import-export' ),
					'children'                    => __( 'children', 'wp-advanced-import-export' ),
					'pluginDataNotLoaded'         => __( 'Plugin data not loaded. Please refresh the page.', 'wp-advanced-import-export' ),
					'errorLoadingPosts'           => __( 'An error occurred while loading posts', 'wp-advanced-import-export' ),
					'failedLoadChildren'          => __( 'Failed to load children', 'wp-advanced-import-export' ),
					'errorLoadingChildren'        => __( 'Error loading children', 'wp-advanced-import-export' ),
				),
			)
		);

		// Enqueue styles (reuse the main plugin styles)
		wp_enqueue_style(
			'aie-post-sync-styles',
			plugins_url( 'assets/css/app.css', WP_AIE_FILE ),
			array(),
			filemtime( plugin_dir_path( WP_AIE_FILE ) . 'assets/css/app.css' )
		);
		
		// Enqueue Gutenberg sync script for post edit screens
		if ( in_array( $hook_suffix, array( 'post.php', 'post-new.php' ) ) ) {
			
			// Only show for posts being edited
			if ( $post ) {
				// Enqueue the Gutenberg sync script
				wp_enqueue_script(
					'aie-gutenberg-sync',
					plugins_url( 'assets/js/gutenberg-sync.js', WP_AIE_FILE ),
					array( 'jquery', 'wp-editor', 'wp-data', 'wp-element', 'wp-components' ),
					filemtime( plugin_dir_path( WP_AIE_FILE ) . 'assets/js/gutenberg-sync.js' ),
					true
				);
				
				// Localize script with necessary data
				wp_localize_script(
					'aie-gutenberg-sync',
					'aieData',
					array(
						'nonce'   => wp_create_nonce( 'aie_nonce' ),
						'ajaxurl' => admin_url( 'admin-ajax.php' ),
						'i18n'    => array(
							'syncContent'  => __( 'Sync Content', 'wp-advanced-import-export' ),
							'syncThisPost' => __( 'Sync This Post', 'wp-advanced-import-export' ),
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
		$post_status = $this->get_request_param( 'post_status', '' );
		if ( 'trash' === $post_status ) {
			return;
		}
		
		// Check if premium is active
		$is_premium = function_exists( 'waie_fs' ) && waie_fs()->can_use_premium_code();
		
		// In free version, only show for 'post' type
		if ( ! $is_premium && 'post' !== $typenow ) {
			return;
		}
		
		require WP_AIE_PATH . '/app/View/sync/sync-button.php';
	}

	/**
	 * Render sync modal
	 */
	public function render_sync_modal() {
		global $typenow;
		
		// Only show on post list screens
		if ( empty( $typenow ) ) {
			return;
		}
		
		// Don't show on trash page
		$post_status = $this->get_request_param( 'post_status', '' );
		if ( 'trash' === $post_status ) {
			return;
		}

		// Check if premium is active
		$is_premium = function_exists( 'waie_fs' ) && waie_fs()->can_use_premium_code();
		
		// In free version, only show for 'post' type
		if ( ! $is_premium && 'post' !== $typenow ) {
			return;
		}

		// Get connected sites
		$sites = Connected_Site::get_all();
		
		// Load view templates
		require WP_AIE_PATH . '/app/View/sync/sync-modal.php';
		require WP_AIE_PATH . '/app/View/sync/mapping-modal.php';
		require WP_AIE_PATH . '/app/View/sync/browse-modal.php';
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
		
		// Check if premium is active
		$is_premium = function_exists( 'waie_fs' ) && waie_fs()->can_use_premium_code();
		
		// In free version, only show for 'post' type
		if ( ! $is_premium && 'post' !== $post->post_type ) {
			return;
		}
		
		require WP_AIE_PATH . '/app/View/sync/post-edit-button.php';
	}

	/**
	 * Render Gutenberg sync button
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
		$verify = $this->verify_request( 'content_sync_get_remote_posts' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$site_id   = $this->get_request_param( 'site_id', 0 );
		$post_type = $this->get_request_param( 'post_type', 'any' );
		$search    = $this->get_request_param( 'search', '' );
		$status    = $this->get_request_param( 'status', '' );
		$page      = $this->get_request_param( 'page', 1 );
		$per_page  = $this->get_request_param( 'per_page', 20 );

		// Validate input
		if ( empty( $site_id ) ) {
			$this->send_error( __( 'Site ID is required', 'wp-advanced-import-export' ) );
		}

		// Get site details
		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			$this->send_error( __( 'Site not found', 'wp-advanced-import-export' ) );
		}

		// Request posts list from remote site
		$response = wp_remote_post(
			trailingslashit( $site['remote_url'] ) . 'wp-json/aie/v1/list-posts',
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $site['api_key'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'post_type' => $post_type,
						'search'    => $search,
						'status'    => $status,
						'page'      => $page,
						'per_page'  => $per_page,
					)
				),
			)
		);
// translators: %s = content placeholder.

		if ( is_wp_error( $response ) ) {
			$this->send_error( __( 'Failed to connect to remote site: ', 'wp-advanced-import-export' ) . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( $status_code !== 200 ) {
			$error_data = json_decode( $body, true );
			// translators: %d is a dynamic value.
			$error_msg  = isset( $error_data['message'] ) ? $error_data['message'] : sprintf( __( 'Request failed with status code: %d', 'wp-advanced-import-export' ), $status_code );
			$this->send_error( $error_msg );
		}

		$data = json_decode( $body, true );
		if ( ! isset( $data['success'] ) || ! $data['success'] || ! isset( $data['posts'] ) ) {
			$this->send_error( __( 'Remote site returned invalid data', 'wp-advanced-import-export' ) );
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
	 * Get children posts from remote site
	 */
	public function get_children_posts() {
		$verify = $this->verify_request( 'content_sync_get_children_posts' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$site_id   = $this->get_request_param( 'site_id', 0 );
		$parent_id = $this->get_request_param( 'parent_id', 0 );
		$post_type = $this->get_request_param( 'post_type', '' );

		// Validate input
		if ( empty( $site_id ) ) {
			$this->send_error( __( 'Site ID is required', 'wp-advanced-import-export' ) );
		}

		if ( empty( $parent_id ) ) {
			$this->send_error( __( 'Parent ID is required', 'wp-advanced-import-export' ) );
		}

		// Get site details
		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			$this->send_error( __( 'Site not found', 'wp-advanced-import-export' ) );
		}

		// Request children posts from remote site
		$response = wp_remote_post(
			trailingslashit( $site['remote_url'] ) . 'wp-json/aie/v1/get-children-posts',
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
					)
				),
			// translators: %s = content placeholder.
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->send_error( __( 'Failed to connect to remote site: ', 'wp-advanced-import-export' ) . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( $status_code !== 200 ) {
			$error_data = json_decode( $body, true );
			// translators: %d is a dynamic value.
			$error_msg  = isset( $error_data['message'] ) ? $error_data['message'] : sprintf( __( 'Request failed with status code: %d', 'wp-advanced-import-export' ), $status_code );
			$this->send_error( $error_msg );
		}

		$data = json_decode( $body, true );
		if ( ! isset( $data['success'] ) || ! $data['success'] || ! isset( $data['children'] ) ) {
			$this->send_error( __( 'Remote site returned invalid data', 'wp-advanced-import-export' ) );
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
		$verify = $this->verify_request( 'content_sync_get_local_posts_info' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$post_ids = $this->get_request_array( 'post_ids', array() );

		// Validate input
		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			$this->send_error( __( 'Post IDs are required', 'wp-advanced-import-export' ) );
		}

		$posts_info = array();

		foreach ( $post_ids as $post_id ) {
			$post = get_post( $post_id );
			if ( $post ) {
				$posts_info[] = array(
					'ID'         => $post->ID,
					'post_title' => $post->post_title,
					'post_type'  => $post->post_type,
					'post_date'  => $post->post_date,
					'post_status' => $post->post_status,
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
		$verify = $this->verify_request( 'content_sync_push' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$site_id      = $this->get_request_param( 'site_id', 0 );
		$post_ids     = $this->get_request_array( 'post_ids', array() );
		$post_mapping = $this->get_request_param( 'post_mapping', array() );

		// Validate input
		if ( empty( $site_id ) ) {
			$this->send_error( __( 'Site ID is required', 'wp-advanced-import-export' ) );
		}

		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			$this->send_error( __( 'No posts selected', 'wp-advanced-import-export' ) );
		}

		// Check if premium is active
		$is_premium = function_exists( 'waie_fs' ) && waie_fs()->can_use_premium_code();
		
		// In free version, validate that only 'post' type is being synced
		if ( ! $is_premium ) {
			foreach ( $post_ids as $post_id ) {
				$post = get_post( $post_id );
				if ( $post && 'post' !== $post->post_type ) {
					$this->send_error( __( 'Premium license is required to sync Pages and Custom Post Types. Free version supports Posts only.', 'wp-advanced-import-export' ) );
				}
			}
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
			$this->send_error( __( 'Site not found', 'wp-advanced-import-export' ) );
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
			$post_images = \WP_AIE\Helper\Content_Sync_Media::extract_post_images( $post_id );
			
			// Store images with post context
			foreach ( $post_images as $image ) {
				$image_key                    = $image['attachment_id'];
				$all_images[ $image_key ]     = $image;
				$image_context[ $image_key ][] = $post_id;
			}

			// Get post meta
			$meta          = get_post_meta( $post_id );
			$prepared_meta = array();
			foreach ( $meta as $key => $values ) {
				$prepared_meta[ $key ] = maybe_unserialize( $values[0] );
			}

			// Get post terms with ACF fields.
			// All taxonomies are included — even empty ones — so the receiving site
			// can clear any stale term assignments it already has for those taxonomies.
			$taxonomies = get_object_taxonomies( $post->post_type );
			$terms_data = array();
			foreach ( $taxonomies as $taxonomy ) {
				$terms = wp_get_post_terms( $post_id, $taxonomy );
				$terms_data[ $taxonomy ] = array(); // always initialise, even when empty
				if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
					foreach ( $terms as $term ) {
						$term_info = array(
							'term_id' => $term->term_id,
							'name'    => $term->name,
							'slug'    => $term->slug,
						);

						// Get ACF fields for this term
						if ( function_exists( 'get_field_objects' ) ) {
							$acf_fields = get_field_objects( $taxonomy . '_' . $term->term_id );
							if ( $acf_fields ) {
								$term_info['acf'] = array();
								foreach ( $acf_fields as $field_key => $field ) {
									$term_info['acf'][ $field_key ] = $field['value'];
								}
							}
						}

						$terms_data[ $taxonomy ][] = $term_info;

						// Extract images from term ACF fields
						if ( ! empty( $term_info['acf'] ) ) {
							$term_images = $this->extract_term_acf_images( $term_info['acf'] );
							foreach ( $term_images as $image_id ) {
								if ( ! isset( $all_images[ $image_id ] ) ) {
									$image_data = array(
										'attachment_id' => $image_id,
										'url'           => wp_get_attachment_url( $image_id ),
										'type'          => 'term_acf',
										'term_id'       => $term->term_id,
										'taxonomy'      => $taxonomy,
									);
									$all_images[ $image_id ]     = $image_data;
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
							'term_id' => $term->term_id,
							'name'    => $term->name,
							'slug'    => $term->slug,
						);
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
				'post_name'     => $post->post_name,
				'post_date'     => $post->post_date,
				'post_modified' => $post->post_modified,
				'post_author'   => $post->post_author,
				'meta'          => $prepared_meta,
				'terms'         => $terms_data,
			);
			
			if ( isset( $prepared_meta['repeater'] ) ) {
			}

			$posts_data[] = $post_data;
		}

		if ( empty( $posts_data ) ) {
			$this->send_error( __( 'No valid posts to sync', 'wp-advanced-import-export' ) );
		}

		// Upload images to remote site first
		$image_map = $this->upload_images_to_remote( array_values( $all_images ), $site );

		// Replace domains in post data
		foreach ( $posts_data as &$post_data ) {
			$post_data = \WP_AIE\Helper\Content_Sync_Replacer::replace_post_domains(
				$post_data,
				$source_domain,
				$target_domain,
				$image_map
			);
		}

		// Send to remote site
		$response = wp_remote_post(
			trailingslashit( $site['remote_url'] ) . 'wp-json/aie/v1/receive-content',
			array(
				'timeout' => 60,
				'headers' => array(
					'Authorization' => 'Bearer ' . $site['api_key'],
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'posts'        => $posts_data,
						'image_map'    => $image_map,
						'post_mapping' => $post_mapping,
					// translators: %s = content placeholder.
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->send_error( __( 'Failed to connect to remote site: ', 'wp-advanced-import-export' ) . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( $status_code !== 200 ) {
			$error_data = json_decode( $body, true );
			// translators: %d is a dynamic value.
			$error_msg  = isset( $error_data['message'] ) ? $error_data['message'] : sprintf( __( 'Push failed with status code: %d', 'wp-advanced-import-export' ), $status_code );
			$this->send_error( $error_msg );
		}

		$data = json_decode( $body, true );
		if ( ! isset( $data['success'] ) || ! $data['success'] ) {
			$this->send_error( __( 'Remote site rejected the content', 'wp-advanced-import-export' ) );
		}

		$this->send_success(
			array(
				'message'       => sprintf(
					/* translators: %d: number of posts */
					__( 'Successfully pushed %d post(s) to remote site', 'wp-advanced-import-export' ),
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

		foreach ( $images as $image ) {
			// Check if image already exists on remote
			$existing_id = \WP_AIE\Helper\Content_Sync_Media::check_remote_image_exists(
				$image['file_hash'],
				$site['remote_url'],
				$site['api_key']
			);

			if ( $existing_id ) {
				// Image already exists, map old ID to existing ID
				$image_map[ $image['attachment_id'] ] = $existing_id;
				continue;
			}

			// Upload new image
			$new_id = $this->upload_single_image_to_remote( $image, $site );
			if ( $new_id ) {
				$image_map[ $image['attachment_id'] ] = $new_id;
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
		$file_contents = @file_get_contents( $image['file_path'] );
		
		if ( false === $file_contents ) {
			return false;
		}

		// Prepare image data for upload
		$upload_data = array(
			'file_name'   => $image['file_name'],
			'file_data'   => base64_encode( $file_contents ),
			'file_hash'   => $image['file_hash'],
			'mime_type'   => $image['mime_type'],
			'alt_text'    => $image['alt_text'],
			'title'       => $image['title'],
			'caption'     => $image['caption'],
			'description' => $image['description'],
		);

		// Upload to remote
		$response = wp_remote_post(
			trailingslashit( $site['remote_url'] ) . 'wp-json/aie/v1/upload-media',
			array(
				'timeout' => 60,
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
	 * Pull content from remote site
	 */
	public function pull_content() {
		$verify = $this->verify_request( 'content_sync_pull' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		$site_id      = $this->get_request_param( 'site_id', 0 );
		$post_ids     = $this->get_request_array( 'post_ids', array() );
		$post_mapping = $this->get_request_param( 'post_mapping', array() );

		// Validate input
		if ( empty( $site_id ) ) {
			$this->send_error( __( 'Site ID is required', 'wp-advanced-import-export' ) );
		}

		if ( empty( $post_ids ) || ! is_array( $post_ids ) ) {
			$this->send_error( __( 'No posts selected', 'wp-advanced-import-export' ) );
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
			$this->send_error( __( 'Site not found', 'wp-advanced-import-export' ) );
		}

		// Get domains for replacement
		$source_domain = wp_parse_url( $site['remote_url'], PHP_URL_HOST );
		$target_domain = wp_parse_url( home_url(), PHP_URL_HOST );

		// Request content from remote site
		$response = wp_remote_post(
			trailingslashit( $site['remote_url'] ) . 'wp-json/aie/v1/send-content',
			array(
				'timeout' => 60,
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
			$this->send_error( __( 'Failed to connect to remote site: ', 'wp-advanced-import-export' ) . $response->get_error_message() );
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		$body        = wp_remote_retrieve_body( $response );

		if ( $status_code !== 200 ) {
			$error_data = json_decode( $body, true );
			// translators: %d is a dynamic value.
			$error_msg  = isset( $error_data['message'] ) ? $error_data['message'] : sprintf( __( 'Pull failed with status code: %d', 'wp-advanced-import-export' ), $status_code );
			$this->send_error( $error_msg );
		}

		$data = json_decode( $body, true );
		if ( ! isset( $data['success'] ) || ! $data['success'] || ! isset( $data['data']['posts'] ) ) {
			$this->send_error( __( 'Remote site returned invalid data', 'wp-advanced-import-export' ) );
		}

		$posts_data = $data['data']['posts'];
		if ( empty( $posts_data ) ) {
			$this->send_error( __( 'No posts found on remote site', 'wp-advanced-import-export' ) );
		}

		// Check if premium is active
		$is_premium = function_exists( 'waie_fs' ) && waie_fs()->can_use_premium_code();
		
		// In free version, validate that only 'post' type is being synced
		if ( ! $is_premium ) {
			foreach ( $posts_data as $post_data ) {
				if ( isset( $post_data['post_type'] ) && 'post' !== $post_data['post_type'] ) {
					$this->send_error( __( 'Premium license is required to sync Pages and Custom Post Types. Free version supports Posts only.', 'wp-advanced-import-export' ) );
				}
			}
		}

		// Get images from remote
		$remote_images = isset( $data['data']['images'] ) ? $data['data']['images'] : array();

		// Download images from remote site
		$image_map = array();
		if ( ! empty( $remote_images ) ) {
			foreach ( $remote_images as $image ) {
				$new_attachment_id = $this->download_image_from_remote( $image, $site );
				if ( $new_attachment_id ) {
					$image_map[ $image['attachment_id'] ] = $new_attachment_id;
				} else {
				}
			}
		}
		

	

	// Replace domains and image IDs in post data
	foreach ( $posts_data as &$post_data ) {
		$post_data = \WP_AIE\Helper\Content_Sync_Replacer::replace_post_domains(
			$post_data,
			$source_domain,
			$target_domain,
			$image_map
		);
	}
	unset( $post_data ); // Break the reference to avoid bugs in the next foreach loop

		// Import posts
		$imported_count = 0;
		$updated_count  = 0;

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

			// Check post mapping (remote → local)
			if ( isset( $remote_to_local_map[ $remote_post_id ] ) ) {
				$mapped_value = $remote_to_local_map[ $remote_post_id ];

				// If mapped to specific ID, use it
				if ( $mapped_value > 0 ) {
					$local_post_id = $mapped_value;
				}
				// null / 0 means "create new post", local_post_id stays null
			} else {
				// No mapping provided, use default logic (find by meta)
				$local_post_id = $this->find_existing_post_by_original_id( $remote_post_id );
			}

			// Prepare post data
			$post_args = array(
				'post_title'    => $post_data['post_title'],
				'post_content'  => $post_data['post_content'],
				'post_excerpt'  => $post_data['post_excerpt'],
				'post_status'   => $post_data['post_status'],
				'post_type'     => $post_data['post_type'],
				'post_name'     => $post_data['post_name'],
				'post_date'     => $post_data['post_date'],
				'post_author'   => get_current_user_id(),
			);

			if ( $local_post_id ) {
				// Update existing post
				$post_args['ID'] = $local_post_id;
				$post_id         = wp_update_post( $post_args, true ); // true to get WP_Error on failure
				if ( ! is_wp_error( $post_id ) && $post_id ) {
					$updated_count++;
				}
			} else {
				// Create new post
				$post_id = wp_insert_post( $post_args, true ); // true to get WP_Error on failure
				if ( ! is_wp_error( $post_id ) && $post_id ) {
					$imported_count++;
				}
			}

			if ( is_wp_error( $post_id ) || ! $post_id ) {
				continue;
			}

			// Store original post ID for future reference
			update_post_meta( $post_id, '_aie_original_post_id', $remote_post_id );

		// Import meta - simple approach: save all fields as-is, ACF will handle them
		if ( ! empty( $post_data['meta'] ) ) {
			foreach ( $post_data['meta'] as $key => $value ) {
				update_post_meta( $post_id, $key, $value );
			}
		}

		// Import terms with ACF fields
		if ( ! empty( $post_data['terms'] ) ) {
			// Build source_term_id → local_term_id map to fix ACF taxonomy fields.
			$term_id_map = array();

			// Clear ALL existing term assignments for every taxonomy the source
			// sent (including empty ones) so stale local terms are removed.
			foreach ( array_keys( $post_data['terms'] ) as $taxonomy_to_clear ) {
				if ( taxonomy_exists( $taxonomy_to_clear ) ) {
					wp_set_object_terms( $post_id, array(), $taxonomy_to_clear );
				}
			}

			foreach ( $post_data['terms'] as $taxonomy => $terms_info ) {
				if ( ! taxonomy_exists( $taxonomy ) ) {
					continue;
				}

				$term_ids = array();
				foreach ( $terms_info as $term_info ) {
					// Validate term info
					if ( empty( $term_info['name'] ) || empty( $term_info['slug'] ) ) {
						continue;
					}

					// Find by slug and update name, or create if not found.
					$existing_term = get_term_by( 'slug', $term_info['slug'], $taxonomy );
					if ( $existing_term ) {
						wp_update_term( $existing_term->term_id, $taxonomy, array( 'name' => $term_info['name'] ) );
						$term_id = $existing_term->term_id;
					} else {
						$new_term = wp_insert_term( $term_info['name'], $taxonomy, array( 'slug' => $term_info['slug'] ) );
						if ( is_wp_error( $new_term ) ) {
							continue;
						}
						$term_id = $new_term['term_id'];
					}

					$term_ids[] = (int) $term_id;

					// Record source → local term ID mapping.
					if ( ! empty( $term_info['term_id'] ) ) {
						$term_id_map[ (int) $term_info['term_id'] ] = (int) $term_id;
					}

					// Import ACF fields for this term
					if ( ! empty( $term_info['acf'] ) && function_exists( 'update_field' ) ) {
						foreach ( $term_info['acf'] as $field_key => $field_value ) {
							update_field( $field_key, $field_value, $taxonomy . '_' . $term_id );
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
				\WP_AIE\Helper\Content_Sync_Replacer::translate_acf_taxonomy_fields_in_meta(
					$post_data['meta'],
					$post_id,
					$term_id_map
				);
			}
		}

			// Fix image URLs in content after import
			$updated_content = $post_data['post_content'];
			
			foreach ( $image_map as $old_id => $new_id ) {
				$new_url = wp_get_attachment_url( $new_id );
				if ( $new_url ) {
					
					// Replace old image URL with new one
					$pattern = '/(<img[^>]+src=")https?:\/\/[^\/]+\/wp-content\/uploads\/[^"]+(' . preg_quote( basename( $new_url ), '/' ) . ')(")/i';
					$replacement = '${1}' . $new_url . '${3}';
					$updated_content = preg_replace( $pattern, $replacement, $updated_content );
					
					// Also update wp:image block ID
					$updated_content = str_replace( '"id":' . $old_id, '"id":' . $new_id, $updated_content );
					$updated_content = str_replace( 'wp-image-' . $old_id, 'wp-image-' . $new_id, $updated_content );
				}
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

		$total_processed = $imported_count + $updated_count;
		$message         = array();
		
		if ( $imported_count > 0 ) {
			$message[] = sprintf(
				/* translators: %d: number of posts */
				_n( 'Created %d post', 'Created %d posts', $imported_count, 'wp-advanced-import-export' ),
				$imported_count
			);
		}
		
		if ( $updated_count > 0 ) {
			$message[] = sprintf(
				/* translators: %d: number of posts */
				_n( 'Updated %d post', 'Updated %d posts', $updated_count, 'wp-advanced-import-export' ),
				$updated_count
			);
		}

		$this->send_success(
			array(
				'message' => ! empty( $message ) ? implode( ', ', $message ) : __( 'No posts were processed', 'wp-advanced-import-export' ),
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
		// Check if image already exists by hash
		if ( ! empty( $image['file_hash'] ) ) {
			$existing_id = $this->find_attachment_by_hash( $image['file_hash'] );
			if ( $existing_id ) {
				\WP_AIE\Helper\Content_Sync_Media::ensure_image_sizes( $existing_id );
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
		require_once ABSPATH . 'wp-admin/includes/image.php';
		$attach_data = wp_generate_attachment_metadata( $attachment_id, $upload['file'] );
		wp_update_attachment_metadata( $attachment_id, $attach_data );

		// Set alt text
		if ( ! empty( $image['alt_text'] ) ) {
			update_post_meta( $attachment_id, '_wp_attachment_image_alt', $image['alt_text'] );
		}

		// Store file hash
		if ( ! empty( $image['file_hash'] ) ) {
			update_post_meta( $attachment_id, '_aie_file_hash', $image['file_hash'] );
		}

		return $attachment_id;
	}

	/**
	 * Find attachment by file hash
	 * // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.
	 * @param string $file_hash File MD5 hash. // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.
	 * @return int|false Attachment ID or false if not found
	 */
	private function find_attachment_by_hash( $file_hash ) {
		// Try to find by stored hash first
		$attachments = get_posts(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_key'       => '_aie_file_hash', // phpcs:ignore WordPress.DB.SlowDBQuery -- meta_key required for filtering.
				'meta_value'     => $file_hash, // phpcs:ignore WordPress.DB.SlowDBQuery -- meta_value required for filtering.
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $attachments ) ) {
			return $attachments[0];
		}

		return false;
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
				$row_prefix = $field_name . '_0_';
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
					$row_data = array();
					$row_prefix = $field_name . '_' . $i . '_';
					$found_fields = 0;
					
					// Find all fields for this row
					foreach ( $meta as $meta_key => $meta_value ) {
						if ( strpos( $meta_key, $row_prefix ) === 0 ) {
							$found_fields++;
							// Extract field name without row prefix
							$sub_field_name = substr( $meta_key, strlen( $row_prefix ) );
							
							// Check if this is a nested repeater/flexible content
							if ( isset( $acf_field_keys[ $field_name . '_' . $i . '_' . $sub_field_name ] ) && is_numeric( $meta_value ) ) {
								// Verify nested repeater has sub-fields
								$nested_prefix = $field_name . '_' . $i . '_' . $sub_field_name . '_0_';
								$nested_has_sub_fields = false;
								foreach ( $meta as $nested_key => $nested_val ) {
									if ( strpos( $nested_key, $nested_prefix ) === 0 ) {
										$nested_has_sub_fields = true;
										break;
									}
								}
								
								if ( $nested_has_sub_fields ) {
									// Recursively process nested repeater
									$nested_rows = $this->extract_nested_repeater_data( $meta, $field_name . '_' . $i . '_' . $sub_field_name, $meta_value, $acf_field_keys );
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
	 * @param array $meta Post meta array
	 * @param string $parent_prefix Parent field prefix (e.g., "repeater_0_nested_repeater")
	 * @param int $row_count Number of rows
	 * @param array $acf_field_keys ACF field keys mapping
	 * @return array Nested rows data
	 */
	private function extract_nested_repeater_data( $meta, $parent_prefix, $row_count, $acf_field_keys ) {
		$rows = array();
		
		for ( $i = 0; $i < $row_count; $i++ ) {
			$row_data = array();
			$row_prefix = $parent_prefix . '_' . $i . '_';
			
			foreach ( $meta as $meta_key => $meta_value ) {
				if ( strpos( $meta_key, $row_prefix ) === 0 ) {
					$sub_field_name = substr( $meta_key, strlen( $row_prefix ) );
					
					// Check for even deeper nesting
					if ( isset( $acf_field_keys[ $parent_prefix . '_' . $i . '_' . $sub_field_name ] ) && is_numeric( $meta_value ) ) {
						// Verify this nested field actually has sub-fields (is a real repeater)
						$nested_prefix = $parent_prefix . '_' . $i . '_' . $sub_field_name . '_0_';
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
		
		// First priority: check if post with same ID exists locally
		$post = get_post( $original_post_id );
		if ( $post && $post->ID == $original_post_id ) {
			return $post->ID; // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.
		} // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.

		// Second priority: try to find by meta (if post was previously synced to different ID)
		$posts = get_posts(
			array(
				'post_type'      => 'any',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_key'       => '_aie_original_post_id', // phpcs:ignore WordPress.DB.SlowDBQuery -- meta_key required for filtering.
				'meta_value'     => $original_post_id, // phpcs:ignore WordPress.DB.SlowDBQuery -- meta_value required for filtering.
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $posts ) ) {
			return $posts[0];
		}

		return false;
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
}

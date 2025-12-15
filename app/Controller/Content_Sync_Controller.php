<?php
/**
 * Content Sync Controller
 *
 * Handles AJAX requests for content synchronization between sites
 *
 * @package WP_AIE\Controller
 */

namespace WP_AIE\Controller;

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
			'content_sync_get_sites'          => array( 'callback' => 'get_sites' ),
			'content_sync_add_site'           => array( 'callback' => 'add_site' ),
			'content_sync_update_site'        => array( 'callback' => 'update_site' ),
			'content_sync_delete_site'        => array( 'callback' => 'delete_site' ),
			'content_sync_regenerate_key'     => array( 'callback' => 'regenerate_key' ),
			'content_sync_test_connection'    => array( 'callback' => 'test_connection' ),
			'content_sync_get_my_key'         => array( 'callback' => 'get_my_site_key' ),
			'content_sync_regenerate_my_key'  => array( 'callback' => 'regenerate_my_site_key' ),
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

		// Validate API key by testing connection to remote site
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

		// Test connection using our validation endpoint
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

		if ( 401 === $status_code || 403 === $status_code ) {
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

		// Try to parse JSON response
		$data = json_decode( $body, true );

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
}

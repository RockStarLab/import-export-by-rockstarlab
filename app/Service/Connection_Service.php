<?php
/**
 * Connection Service
 *
 * Transport-independent connected-site operations
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Helper\ACF_Fields;
use RockStarLab\ImportExport\Helper\Field_Transformation_Bridge;
use RockStarLab\ImportExport\Helper\Site_URL;
use RockStarLab\ImportExport\Model\Connected_Site;

defined( 'ABSPATH' ) || exit;

/** Shared connection business operations. */
class Connection_Service {
	use Sync_Service_Input;

	/**
	 * Dispatch an explicit service operation.
	 *
	 * @param string $action Operation name.
	 * @param array  $input Parsed input, independent of HTTP globals.
	 * @return array|\WP_Error
	 */
	public function execute( $action, array $input = [] ) {
		$access = \RockStarLab\ImportExport\Abilities\Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		if ( ! in_array( $action, [ 'add_site', 'delete_site', 'get_my_site_key', 'get_sites', 'regenerate_key', 'regenerate_my_site_key', 'test_connection', 'update_site' ], true ) ) {
			return $this->error( __( 'Unknown sync operation.', 'import-export-by-rockstarlab' ) );
		}
		$site_id = absint( $input['site_id'] ?? 0 );
		$lock    = $site_id ? Job_Lock::acquire_resource( 'connection', $site_id ) : null;
		if ( false === $lock || is_wp_error( $lock ) ) {
			return new \WP_Error( 'rsl_ie_connection_busy', __( 'This connection is already in use. Try again later.', 'import-export-by-rockstarlab' ), [ 'status' => 409 ] );
		}
		try {
			return $this->$action( $input );
		} finally {
			if ( $lock ) {
				Job_Lock::release( $lock );
			}
		}
	}

	/**
	 * Get all connected sites
	 *
	 * @return array|\WP_Error
	 */
	private function get_sites() {
		$sites = Connected_Site::get_all();
		$stats = Connected_Site::get_stats();

		return $this->success(
			array(
				'sites' => $sites,
				'stats' => $stats,
			)
		);
	}

	/**
	 * Add new site connection
	 *
	 * @param array $input Parsed operation input.
	 * @return array|\WP_Error
	 */
	private function add_site( array $input ) {
		$name       = $this->param( $input, 'name', '' );
		$remote_url = $this->param( $input, 'remote_url', '' );
		$api_key    = $this->param( $input, 'api_key', '' );
		$direction  = $this->param( $input, 'direction', 'bidirectional' );

		// Validate input.
		if ( empty( $name ) ) {
			return $this->error( __( 'Site name is required', 'import-export-by-rockstarlab' ) );
		}

		if ( empty( $remote_url ) ) {
			return $this->error( __( 'Remote URL is required', 'import-export-by-rockstarlab' ) );
		}

		if ( empty( $api_key ) ) {
			return $this->error( __( 'API key is required', 'import-export-by-rockstarlab' ) );
		}

		// Validate URL.
		$remote_url = self::site_address( esc_url_raw( $remote_url ) );
		if ( ! filter_var( $remote_url, FILTER_VALIDATE_URL ) ) {
			return $this->error( __( 'Invalid URL format', 'import-export-by-rockstarlab' ) );
		}

		// Check if URL already exists.
		if ( Connected_Site::exists_by_url( $remote_url ) ) {
			return $this->error( __( 'This site is already connected', 'import-export-by-rockstarlab' ) );
		}

		$validation_result = $this->validate_remote_site( $remote_url, $api_key );
		if ( is_wp_error( $validation_result ) ) {
			return $this->check_error( $validation_result );
		}

		// Validate direction.
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
			return $this->error( __( 'Failed to add site connection', 'import-export-by-rockstarlab' ) );
		}

		$site = Connected_Site::get_by_id( $site_id );

		return $this->success(
			array(
				'message' => __( 'Site connection added successfully', 'import-export-by-rockstarlab' ),
				'site'    => $site,
			)
		);
	}

	/**
	 * Update site connection
	 *
	 * @param array $input Parsed operation input.
	 * @return array|\WP_Error
	 */
	private function update_site( array $input ) {
		$site_id    = $this->param( $input, 'site_id', 0 );
		$name       = $this->param( $input, 'name', '' );
		$remote_url = $this->param( $input, 'remote_url', '' );
		$api_key    = $this->param( $input, 'api_key', '' );
		$direction  = $this->param( $input, 'direction', '' );
		$status     = $this->param( $input, 'status', '' );

		if ( ! $site_id ) {
			return $this->error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		// Check if site exists.
		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			return $this->error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}

		$data = array();

		if ( ! empty( $name ) ) {
			$data['name'] = sanitize_text_field( $name );
		}

		// Determine which URL to use for validation.
		$url_to_validate = ! empty( $remote_url ) ? self::site_address( esc_url_raw( $remote_url ) ) : $site['remote_url'];

		if ( ! empty( $remote_url ) ) {
			$remote_url = self::site_address( esc_url_raw( $remote_url ) );
			if ( ! filter_var( $remote_url, FILTER_VALIDATE_URL ) ) {
				return $this->error( __( 'Invalid URL format', 'import-export-by-rockstarlab' ) );
			}

			// Check if URL already exists (excluding current site).
			if ( Connected_Site::exists_by_url( $remote_url, $site_id ) ) {
				return $this->error( __( 'This site is already connected', 'import-export-by-rockstarlab' ) );
			}

			$data['remote_url'] = $remote_url;
		}

		// Revalidate both URL and credential changes before storing either.
		if ( ! empty( $api_key ) || ! empty( $remote_url ) ) {
			$validation_result = $this->validate_remote_site( $url_to_validate, ! empty( $api_key ) ? $api_key : $site['api_key'] );
			if ( is_wp_error( $validation_result ) ) {
				return $this->check_error( $validation_result );
			}
			// The new address or key works, so the connection is no longer in error.
			$data['status'] = 'active';
			if ( ! empty( $api_key ) ) {
				$data['api_key'] = sanitize_text_field( $api_key );
			}
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
			// No changes made - return current site data.
			return $this->success(
				array(
					'message' => __( 'No changes were made', 'import-export-by-rockstarlab' ),
					'site'    => $site,
				)
			);
		}

		$result = Connected_Site::update( $site_id, $data );

		if ( ! $result ) {
			return $this->error( __( 'Failed to update site connection', 'import-export-by-rockstarlab' ) );
		}

		$updated_site = Connected_Site::get_by_id( $site_id );

		// Create detailed message about what was updated.
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

		return $this->success(
			array(
				'message' => $message,
				'site'    => $updated_site,
			)
		);
	}

	/**
	 * Delete site connection
	 *
	 * @param array $input Parsed operation input.
	 * @return array|\WP_Error
	 */
	private function delete_site( array $input ) {
		$site_id = $this->param( $input, 'site_id', 0 );

		if ( ! $site_id ) {
			return $this->error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		// Check if site exists.
		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			return $this->error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}

		$result = Connected_Site::delete( $site_id );

		if ( ! $result ) {
			return $this->error( __( 'Failed to delete site connection', 'import-export-by-rockstarlab' ) );
		}

		return $this->success(
			array(
				'message' => __( 'Site connection deleted successfully', 'import-export-by-rockstarlab' ),
			)
		);
	}

	/**
	 * Regenerate API key for a site
	 *
	 * @param array $input Parsed operation input.
	 * @return array|\WP_Error
	 */
	private function regenerate_key( array $input ) {
		$site_id = $this->param( $input, 'site_id', 0 );

		if ( ! $site_id ) {
			return $this->error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		// Check if site exists.
		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			return $this->error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}

		$new_key = Connected_Site::regenerate_api_key( $site_id );

		if ( ! $new_key ) {
			return $this->error( __( 'Failed to regenerate API key', 'import-export-by-rockstarlab' ) );
		}

		return $this->success(
			array(
				'message' => __( 'API key regenerated successfully', 'import-export-by-rockstarlab' ),
				'api_key' => $new_key,
			)
		);
	}

	/**
	 * Strip admin and API paths from an address people often paste ("/wp-admin/", "/wp-login.php").
	 *
	 * @param string $url Address.
	 * @return string
	 */
	public static function site_address( $url ) {
		$url = (string) preg_replace( '#[?\#].*$#', '', (string) $url );
		$url = (string) preg_replace( '#/(wp-admin|wp-login\.php|wp-json|index\.php)(/.*)?$#i', '', $url );
		return untrailingslashit( $url );
	}

	/**
	 * Connection check failures are written by this plugin, so callers may show them.
	 *
	 * @param \WP_Error $error Check result.
	 * @return \WP_Error
	 */
	private function check_error( \WP_Error $error ) {
		$safe = [ 'connection_failed', 'remote_redirect', 'plugin_not_installed', 'rest_not_found', 'invalid_api_key', 'password_protected', 'rest_blocked', 'firewall_blocked', 'connection_error', 'invalid_response' ];
		if ( in_array( $error->get_error_code(), $safe, true ) ) {
			return new \WP_Error( 'rsl_ie_connection_check', $error->get_error_message(), [ 'status' => 400, 'reason' => $error->get_error_code() ] );
		}
		return $this->error( $error->get_error_message() );
	}

	/**
	 * Test connection to remote site
	 *
	 * @param array $input Parsed operation input.
	 * @return array|\WP_Error
	 */
	private function test_connection( array $input ) {
		$site_id = $this->param( $input, 'site_id', 0 );

		if ( ! $site_id ) {
			return $this->error( __( 'Site ID is required', 'import-export-by-rockstarlab' ) );
		}

		// Check if site exists.
		$site = Connected_Site::get_by_id( $site_id );
		if ( ! $site ) {
			return $this->error( __( 'Site not found', 'import-export-by-rockstarlab' ) );
		}

		$validation_result = $this->validate_remote_site( $site['remote_url'], $site['api_key'] );

		if ( is_wp_error( $validation_result ) ) {
			Connected_Site::update_last_sync( $site_id, $validation_result->get_error_message() );
			// Update status to error.
			Connected_Site::update( $site_id, array( 'status' => 'error' ) );
			return $this->check_error( $validation_result );
		}

		// Connection successful - update last sync and status.
		Connected_Site::update_last_sync( $site_id );
		Connected_Site::update( $site_id, array( 'status' => 'active' ) );

		return $this->success(
			array(
				'message' => __( 'Connection successful. API key is valid.', 'import-export-by-rockstarlab' ),
			)
		);
	}

	/**
	 * Get this site's API key for incoming connections
	 *
	 * @return array|\WP_Error
	 */
	private function get_my_site_key() {
		$service = new Credential_Service();
		$created = $service->execute( 'site-key-create' );
		return is_wp_error( $created ) ? $created : $service->execute( 'site-key-reveal' );
	}

	/**
	 * Regenerate this site's API key
	 *
	 * @return array|\WP_Error
	 */
	private function regenerate_my_site_key() {
		$result = ( new Credential_Service() )->execute( 'site-key-rotate' );
		return is_wp_error( $result ) ? $result : $result + [ 'message' => __( 'API key regenerated successfully. All remote sites must update their connection.', 'import-export-by-rockstarlab' ) ];
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

		// Check for connection errors.
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

		$is_json = \RockStarLab\ImportExport\Helper\Remote_API::is_json( $body );
		$data    = $is_json ? json_decode( $body, true ) : null;
		$code    = is_array( $data ) && isset( $data['code'] ) ? sanitize_key( (string) $data['code'] ) : '';

		// The bearer key never follows a redirect, so the connection has to use the final address.
		if ( $status_code >= 300 && $status_code < 400 ) {
			$location = esc_url_raw( (string) wp_remote_retrieve_header( $response, 'location' ) );
			return new \WP_Error(
				'remote_redirect',
				$location
					/* translators: %s: address the remote site redirects to */
					? sprintf( __( 'The remote site redirects to %s. Enter the address it redirects to (for example with https:// or www) as the site URL.', 'import-export-by-rockstarlab' ), self::site_address( $location ) )
					: __( 'The remote site answered with a redirect. Enter its final address (for example with https:// or www) as the site URL.', 'import-export-by-rockstarlab' )
			);
		}

		// Check status code.
		if ( 404 === $status_code || ( $status_code >= 200 && $status_code < 300 && ! $is_json ) ) {
			if ( 'rest_no_route' === $code ) {
				return new \WP_Error(
					'plugin_not_installed',
					__( 'The Content Sync, Media Sync, Import, Export plugin (Import Export by RockStarLab) is not installed or activated on the remote site.', 'import-export-by-rockstarlab' )
				);
			}
			return new \WP_Error(
				'rest_not_found',
				__( 'No WordPress REST API answered at this address. Check the site URL: use the home address of the site, without /wp-admin or other paths.', 'import-export-by-rockstarlab' )
			);
		}

		if ( 401 === $status_code || 403 === $status_code ) {
			if ( 'rsl_ie_sync_key_setup_required' === $code ) {
				return new \WP_Error(
					'invalid_api_key',
					__( 'The key is right, but the remote site has not activated it yet: an administrator there must open Import Export > Content Sync once. The key does not change.', 'import-export-by-rockstarlab' )
				);
			}
			// Our own endpoint rejected the key.
			if ( 0 === strpos( $code, 'rsl_ie_' ) ) {
				return new \WP_Error(
					'invalid_api_key',
					__( 'Invalid API key. Copy the key again from Import Export > Content Sync on the remote site.', 'import-export-by-rockstarlab' )
				);
			}
			if ( '' !== (string) wp_remote_retrieve_header( $response, 'www-authenticate' ) ) {
				return new \WP_Error(
					'password_protected',
					__( 'The remote site asks for a username and password before WordPress loads (HTTP authentication, common on staging sites). Allow requests to /wp-json/rsl-ie/v1/ without the password, or turn the protection off while you sync.', 'import-export-by-rockstarlab' )
				);
			}
			if ( '' !== $code ) {
				return new \WP_Error(
					'rest_blocked',
					/* translators: 1: HTTP status code, 2: REST error code from the remote site */
					sprintf( __( 'The remote site blocks REST API requests (HTTP %1$d, %2$s). A security plugin or setting that limits the REST API must allow /wp-json/rsl-ie/v1/.', 'import-export-by-rockstarlab' ), $status_code, $code )
				);
			}
			return new \WP_Error(
				'firewall_blocked',
				/* translators: %d: HTTP status code */
				sprintf( __( 'A firewall or security service on the remote site refused the request (HTTP %d). Allow requests to /wp-json/rsl-ie/v1/ from this site.', 'import-export-by-rockstarlab' ), $status_code )
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

		if ( ! $is_json ) {
			return new \WP_Error(
				'invalid_response',
				__( 'Remote site returned invalid response format.', 'import-export-by-rockstarlab' )
			);
		}

		// Check if response indicates success.
		if ( ! isset( $data['success'] ) || ! $data['success'] ) {
			$error_message = isset( $data['message'] ) ? $data['message'] : __( 'Unknown error from remote site.', 'import-export-by-rockstarlab' );
			return new \WP_Error( 'validation_failed', $error_message );
		}

		return true;
	}
}

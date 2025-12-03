<?php
/**
 * Base Controller
 *
 * Base class for all AJAX controllers with common functionality
 *
 * @package WP_AIE\Controller
 */

namespace WP_AIE\Controller;

/**
 * Base Controller Class
 *
 * Provides common functionality for AJAX controllers including
 * security checks, request validation, and response formatting.
 *
 * @package WP_AIE\Controller
 */
abstract class Base_Controller {

	/**
	 * Required capability for this controller
	 *
	 * @var string
	 */
	protected $required_capability = 'manage_options';

	/**
	 * Initialize controller
	 *
	 * Registers AJAX hooks for both admin and non-admin users.
	 */
	public function init() {
		$actions = $this->get_ajax_actions();

		foreach ( $actions as $action => $config ) {
			$callback = $config['callback'] ?? $action;
			$nopriv   = $config['nopriv'] ?? false;

			// Admin AJAX
			add_action( 'wp_ajax_aie_' . $action, [ $this, $callback ] );

			// Non-admin AJAX (if allowed)
			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_aie_' . $action, [ $this, $callback ] );
			}
		}
	}

	/**
	 * Get AJAX actions to register
	 *
	 * Must be implemented by child classes.
	 *
	 * @return array Array of action => config
	 */
	abstract protected function get_ajax_actions();

	/**
	 * Verify request security
	 *
	 * Checks nonce and user capabilities.
	 *
	 * @param string $action     Action name for nonce verification
	 * @param string $capability Optional. Required capability (default: from property)
	 * @return true|WP_Error True if valid or WP_Error
	 */
	protected function verify_request( $action, $capability = null ) {
		// Check nonce - using general aie_nonce instead of action-specific
		$nonce = $this->get_request_param( 'nonce', '' );
		if ( ! wp_verify_nonce( $nonce, 'aie_nonce' ) ) {
			return new \WP_Error( 'invalid_nonce', __( 'Security check failed', 'wp-advanced-import-export' ) );
		}

		// Check capability
		$required_cap = $capability ?? $this->required_capability;
		if ( ! current_user_can( $required_cap ) ) {
			return new \WP_Error( 'insufficient_permissions', __( 'You do not have permission to perform this action', 'wp-advanced-import-export' ) );
		}

		return true;
	}

	/**
	 * Get request parameter
	 *
	 * @param string $key     Parameter key
	 * @param mixed  $default Default value
	 * @param string $method  Request method (get, post, request)
	 * @return mixed Parameter value
	 */
	protected function get_request_param( $key, $default = null, $method = 'request' ) {
		switch ( strtolower( $method ) ) {
			case 'get':
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return isset( $_GET[ $key ] ) ? sanitize_text_field( wp_unslash( $_GET[ $key ] ) ) : $default;

			case 'post':
				// phpcs:ignore WordPress.Security.NonceVerification.Missing
				return isset( $_POST[ $key ] ) ? sanitize_text_field( wp_unslash( $_POST[ $key ] ) ) : $default;

			default:
				// phpcs:ignore WordPress.Security.NonceVerification.Recommended
				return isset( $_REQUEST[ $key ] ) ? sanitize_text_field( wp_unslash( $_REQUEST[ $key ] ) ) : $default;
		}
	}

	/**
	 * Get request array parameter
	 *
	 * @param string $key     Parameter key
	 * @param array  $default Default value
	 * @return array Parameter value
	 */
	protected function get_request_array( $key, $default = [] ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_REQUEST[ $key ] ) ) {
			return $default;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$value = $_REQUEST[ $key ];

		if ( ! is_array( $value ) ) {
			return $default;
		}

		// Recursively sanitize nested arrays
		return $this->sanitize_array( $value );
	}

	/**
	 * Recursively sanitize array
	 *
	 * @param array $array Array to sanitize
	 * @return array Sanitized array
	 */
	private function sanitize_array( $array ) {
		$sanitized = [];

		foreach ( $array as $key => $value ) {
			$sanitized_key = sanitize_text_field( wp_unslash( $key ) );

			if ( is_array( $value ) ) {
				$sanitized[ $sanitized_key ] = $this->sanitize_array( $value );
			} else {
				$sanitized[ $sanitized_key ] = sanitize_text_field( wp_unslash( $value ) );
			}
		}

		return $sanitized;
	}

	/**
	 * Send JSON success response
	 *
	 * @param mixed  $data    Response data
	 * @param string $message Optional. Success message
	 * @param int    $status  Optional. HTTP status code (default: 200)
	 */
	protected function send_success( $data = null, $message = '', $status = 200 ) {
		$response = [
			'success' => true,
			'data'    => $data,
		];

		if ( ! empty( $message ) ) {
			$response['message'] = $message;
		}

		wp_send_json( $response, $status );
	}

	/**
	 * Send JSON error response
	 *
	 * @param string|WP_Error $error  Error message or WP_Error object
	 * @param mixed           $data   Optional. Additional error data
	 * @param int             $status Optional. HTTP status code (default: 400)
	 */
	protected function send_error( $error, $data = null, $status = 400 ) {
		$response = [
			'success' => false,
		];

		if ( is_wp_error( $error ) ) {
			$response['message'] = $error->get_error_message();
			$response['code']    = $error->get_error_code();

			if ( $error->get_error_data() ) {
				$response['data'] = $error->get_error_data();
			}
		} else {
			$response['message'] = $error;
		}

		if ( null !== $data ) {
			$response['data'] = $data;
		}

		wp_send_json( $response, $status );
	}

	/**
	 * Validate required parameters
	 *
	 * @param array $required Array of required parameter names
	 * @return true|WP_Error True if valid or WP_Error with missing params
	 */
	protected function validate_required_params( $required ) {
		$missing = [];

		foreach ( $required as $param ) {
			if ( empty( $this->get_request_param( $param ) ) ) {
				$missing[] = $param;
			}
		}

		if ( ! empty( $missing ) ) {
			return new \WP_Error(
				'missing_parameters',
				sprintf(
					/* translators: %s: comma-separated list of missing parameters */
					__( 'Missing required parameters: %s', 'wp-advanced-import-export' ),
					implode( ', ', $missing )
				)
			);
		}

		return true;
	}

	/**
	 * Sanitize file upload
	 *
	 * @param array $file File from $_FILES
	 * @return array|WP_Error Sanitized file or WP_Error
	 */
	protected function sanitize_file_upload( $file ) {
		if ( empty( $file ) || ! isset( $file['tmp_name'] ) ) {
			return new \WP_Error( 'no_file', __( 'No file uploaded', 'wp-advanced-import-export' ) );
		}

		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			return new \WP_Error( 'upload_error', __( 'File upload failed', 'wp-advanced-import-export' ) );
		}

		return [
			'name'     => sanitize_file_name( $file['name'] ),
			'type'     => $file['type'],
			'tmp_name' => $file['tmp_name'],
			'size'     => (int) $file['size'],
		];
	}

	/**
	 * Get current user ID
	 *
	 * @return int User ID
	 */
	protected function get_current_user_id() {
		return get_current_user_id();
	}

	/**
	 * Log controller action
	 *
	 * @param string $action  Action name
	 * @param mixed  $data    Optional. Additional data
	 * @param string $level   Optional. Log level (info, warning, error)
	 */
	protected function log( $action, $data = [], $level = 'info' ) {
		$log_data = [
			'controller' => get_class( $this ),
			'action'     => $action,
			'user_id'    => $this->get_current_user_id(),
			'timestamp'  => current_time( 'mysql' ),
			'data'       => $data,
		];

		/**
		 * Filter controller log data
		 *
		 * @param array  $log_data Log data
		 * @param string $action   Action name
		 * @param object $controller Controller instance
		 */
		$log_data = apply_filters( 'aie_controller_log_data', $log_data, $action, $this );

		// Log using WordPress error_log
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG && defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '[WP-AIE] %s: %s', strtoupper( $level ), wp_json_encode( $log_data ) ) );
		}

		/**
		 * Action fired when controller logs
		 *
		 * @param array  $log_data Log data
		 * @param string $level    Log level
		 * @param string $action   Action name
		 */
		do_action( 'aie_controller_log', $log_data, $level, $action );
	}
}

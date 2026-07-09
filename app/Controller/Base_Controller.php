<?php
/**
 * Base Controller
 *
 * Base class for all AJAX controllers with common functionality
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Helper\Ajax_Security;

defined( 'ABSPATH' ) || exit;

/**
 * Base class for AJAX controllers.
 */
abstract class Base_Controller {

	/**
	 * AJAX action prefix (sent via `action` to admin-ajax.php).
	 */
	protected const AJAX_PREFIX = 'rsl_ie_';

	/**
	 * Required capability for this controller
	 *
	 * @var string
	 */
	protected $required_capability = 'manage_options';

	/**
	 * Full AJAX action currently being dispatched.
	 *
	 * @var string
	 */
	private $current_ajax_action = '';

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

			$full_action = self::AJAX_PREFIX . $action;
			Ajax_Security::register_action( $full_action );

			// Bind the verified action on the server instead of trusting request data.
			$dispatcher = function () use ( $full_action, $callback ) {
				$this->current_ajax_action = $full_action;
				if ( ! Ajax_Security::verify_nonce( $full_action ) ) {
					wp_send_json_error( array( 'message' => __( 'Security check failed', 'import-export-by-rockstarlab' ) ), 403 );
				}
				if ( ! current_user_can( $this->required_capability ) ) {
					wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action', 'import-export-by-rockstarlab' ) ), 403 );
				}
				call_user_func( array( $this, $callback ) );
			};

			// Admin AJAX.
			add_action( 'wp_ajax_' . $full_action, $dispatcher );

			// Non-admin AJAX, if allowed.
			if ( $nopriv ) {
				add_action( 'wp_ajax_nopriv_' . $full_action, $dispatcher );
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
	 * @param string $capability Optional. Required capability (default: from property).
	 * @return true|WP_Error True if valid or WP_Error
	 */
	protected function verify_request( $capability = null ) {
		if ( ! Ajax_Security::verify_nonce( $this->current_ajax_action ) ) {
			return new \WP_Error( 'invalid_nonce', __( 'Security check failed', 'import-export-by-rockstarlab' ) );
		}

		// Check capability.
		$required_cap = $capability ?? $this->required_capability;
		if ( ! current_user_can( $required_cap ) ) {
			return new \WP_Error( 'insufficient_permissions', __( 'You do not have permission to perform this action', 'import-export-by-rockstarlab' ) );
		}

		return true;
	}

	/**
	 * Get request parameter
	 *
	 * @param string $key     Parameter key.
	 * @param mixed  $default_value Default value.
	 * @param string $method  Request method (get, post, request).
	 * @return mixed Parameter value
	 */
	protected function get_request_param( $key, $default_value = null, $method = 'request' ) {
		if ( '' === $this->current_ajax_action ) {
			return $default_value;
		}

		check_ajax_referer( Ajax_Security::nonce_action( $this->current_ajax_action ), 'nonce' );

		$value = $this->get_input_value( $key, $method );
		if ( null === $value || is_array( $value ) ) {
			return $default_value;
		}

		return sanitize_text_field( wp_unslash( $value ) );
	}

	/**
	 * Read a scalar request value from an already nonce-verified request.
	 *
	 * @param string $key    Request parameter key.
	 * @param string $method Request method (get, post, request).
	 * @return string|null Raw value, or null when absent.
	 */
	private function get_input_value( $key, $method = 'request' ) {
		$key = is_string( $key ) ? sanitize_key( $key ) : '';
		if ( '' === $key ) {
			return null;
		}

		foreach ( $this->get_input_sources( $method ) as $source ) {
			if ( ! filter_has_var( $source, $key ) ) {
				continue;
			}

			$value = filter_input( $source, $key, FILTER_UNSAFE_RAW );
			if ( null !== $value && false !== $value ) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * Read an array request value from an already nonce-verified request.
	 *
	 * @param string $key Request parameter key.
	 * @return array|null Raw value, or null when absent.
	 */
	private function get_input_array( $key ) {
		$key = is_string( $key ) ? sanitize_key( $key ) : '';
		if ( '' === $key ) {
			return null;
		}

		foreach ( $this->get_input_sources( 'request' ) as $source ) {
			if ( ! filter_has_var( $source, $key ) ) {
				continue;
			}

			$value = filter_input( $source, $key, FILTER_DEFAULT, FILTER_REQUIRE_ARRAY );
			if ( is_array( $value ) ) {
				return $value;
			}
		}

		return null;
	}

	/**
	 * Resolve the PHP input sources for a request method.
	 *
	 * @param string $method Request method (get, post, request).
	 * @return int[] Input source constants.
	 */
	private function get_input_sources( $method ) {
		switch ( strtolower( (string) $method ) ) {
			case 'get':
				return [ INPUT_GET ];

			case 'post':
				return [ INPUT_POST ];

			default:
				return [ INPUT_POST, INPUT_GET ];
		}
	}

	/**
	 * Get request array parameter
	 *
	 * @param string $key     Parameter key.
	 * @param array  $default_value Default value.
	 * @return array Parameter value
	 */
	protected function get_request_array( $key, $default_value = [] ) {
		if ( '' === $this->current_ajax_action ) {
			return $default_value;
		}

		// This accessor is only used by AJAX handlers after verify_request(). Keep
		// the nonce check adjacent to the raw array input for security scanners.
		check_ajax_referer( Ajax_Security::nonce_action( $this->current_ajax_action ), 'nonce' );

		$value = $this->get_input_array( $key );
		if ( null === $value ) {
			return $default_value;
		}

		if ( ! is_array( $value ) ) {
			return $default_value;
		}

		// Recursively sanitize nested arrays.
		return $this->sanitize_array( wp_unslash( $value ) );
	}

	/**
	 * Recursively sanitize array
	 *
	 * @param array $input_array Array to sanitize.
	 * @return array Sanitized array
	 */
	private function sanitize_array( $input_array ) {
		$sanitized = [];

		foreach ( $input_array as $key => $value ) {
			$sanitized_key = sanitize_text_field( $key );

			if ( is_array( $value ) ) {
				$sanitized[ $sanitized_key ] = $this->sanitize_array( $value );
			} else {
				$sanitized[ $sanitized_key ] = sanitize_text_field( $value );
			}
		}

		return $sanitized;
	}

	/**
	 * Send JSON success response
	 *
	 * @param mixed  $data    Response data.
	 * @param string $message Optional. Success message.
	 * @param int    $status  Optional. HTTP status code (default: 200).
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
	 * @param string|WP_Error $error  Error message or WP_Error object.
	 * @param mixed           $data   Optional. Additional error data.
	 * @param int             $status Optional. HTTP status code (default: 400).
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
	 * @param array $required Array of required parameter names.
	 * @return true|WP_Error True if valid or WP_Error with missing params
	 */
	protected function validate_required_params( $required ) {
		if ( '' === $this->current_ajax_action ) {
			return new \WP_Error( 'invalid_ajax_context', __( 'Invalid AJAX request context', 'import-export-by-rockstarlab' ) );
		}

		check_ajax_referer( Ajax_Security::nonce_action( $this->current_ajax_action ), 'nonce' );

		$missing = [];

		foreach ( $required as $param ) {
			$value = $this->get_input_value( $param );
			if ( null === $value || ( is_string( $value ) && '' === trim( $value ) ) ) {
				$missing[] = $param;
			}
		}

		if ( ! empty( $missing ) ) {
			return new \WP_Error(
				'missing_parameters',
				sprintf(
					/* translators: %s: comma-separated list of missing parameters */
					__( 'Missing required parameters: %s', 'import-export-by-rockstarlab' ),
					implode( ', ', $missing )
				)
			);
		}

		return true;
	}

	/**
	 * Sanitize file upload
	 *
	 * @param array $file File from $_FILES.
	 * @return array|WP_Error Sanitized file or WP_Error
	 */
	protected function sanitize_file_upload( $file ) {
		if ( empty( $file ) || ! isset( $file['tmp_name'] ) ) {
			return new \WP_Error( 'no_file', __( 'No file uploaded', 'import-export-by-rockstarlab' ) );
		}

		if ( UPLOAD_ERR_OK !== $file['error'] ) {
			return new \WP_Error( 'upload_error', __( 'File upload failed', 'import-export-by-rockstarlab' ) );
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
	 * @param string $action  Action name.
	 * @param mixed  $data    Optional. Additional data.
	 * @param string $level   Optional. Log level (info, warning, error).
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
		 * @param array  $log_data   Log data.
		 * @param string $action     Action name.
		 * @param object $controller Controller instance.
		 */
		$log_data = apply_filters( 'rsl_ie_controller_log_data', $log_data, $action, $this );

		/**
		 * Action fired when controller logs
		 *
		 * @param array  $log_data Log data.
		 * @param string $level    Log level.
		 * @param string $action   Action name.
		 */
		do_action( 'rsl_ie_controller_log', $log_data, $level, $action );
	}
}

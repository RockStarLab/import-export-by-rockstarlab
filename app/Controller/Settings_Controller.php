<?php
/**
 * Settings Controller
 *
 * Handles plugin settings operations
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

defined( 'ABSPATH' ) or exit;

class Settings_Controller extends Base_Controller {

	/**
	 * Get AJAX actions
	 *
	 * @return array
	 */
	protected function get_ajax_actions() {
		return [
			'settings_save'          => [ 'callback' => 'save_settings' ],
			'dismiss_pro_promo'      => [ 'callback' => 'dismiss_pro_promo' ],
			'test_openai_connection' => [ 'callback' => 'test_openai_connection' ],
		];
	}

	/**
	 * Verify the configured OpenAI credential with a lightweight API request.
	 *
	 * @return void
	 */
	public function test_openai_connection() {
		$verify = $this->verify_request( 'test_openai_connection' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify, null, 403 );
		}

		$submitted_key = trim( (string) $this->get_request_param( 'api_key', '' ) );
		$api_key       = false === strpos( $submitted_key, '...' ) ? $submitted_key : '';
		if ( '' === $api_key ) {
			$api_key = \RockStarLab\ImportExport\Helper\OpenAI_API_Key::get_api_key();
		}

		if ( '' === $api_key ) {
			$this->send_error( __( 'No OpenAI API key is configured.', 'import-export-by-rockstarlab' ), null, 400 );
		}

		$response = wp_remote_get(
			'https://api.openai.com/v1/models',
			[
				'timeout' => 15,
				'headers' => [
					'Authorization' => 'Bearer ' . $api_key,
					'Accept'        => 'application/json',
				],
			]
		);

		if ( is_wp_error( $response ) ) {
			$this->send_error( $response->get_error_message(), null, 502 );
		}

		$status = (int) wp_remote_retrieve_response_code( $response );
		if ( $status < 200 || $status >= 300 ) {
			$body    = json_decode( wp_remote_retrieve_body( $response ), true );
			$message = is_array( $body ) && ! empty( $body['error']['message'] )
				? sanitize_text_field( $body['error']['message'] )
				: __( 'OpenAI rejected the connection request.', 'import-export-by-rockstarlab' );
			$this->send_error( $message, null, $status > 0 ? $status : 502 );
		}

		$this->send_success( [ 'message' => __( 'Connection successful', 'import-export-by-rockstarlab' ) ] );
	}

	/**
	 * Save plugin settings
	 */
	public function save_settings() {
		$verify = $this->verify_request( 'nonce' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
		}

		// Check permissions
		if ( ! current_user_can( 'manage_options' ) ) {
			$this->send_error( __( 'You do not have permission to manage settings', 'import-export-by-rockstarlab' ) );
		}

		$openai_api_key = $this->get_request_param( 'openai_api_key', '' );

		// Save OpenAI API Key
		if ( ! empty( $openai_api_key ) ) {
			// Validate API key format
			if ( ! $this->validate_openai_api_key( $openai_api_key ) ) {
				$this->send_error( __( 'Invalid OpenAI API key format. Key should start with "sk-"', 'import-export-by-rockstarlab' ) );
			}

			update_option( 'rsl_ie_openai_api_key', sanitize_text_field( $openai_api_key ) );
		} else {
			// Remove API key if empty
			delete_option( 'rsl_ie_openai_api_key' );
		}

		$this->send_success(
			[
				'message' => __( 'Settings saved successfully', 'import-export-by-rockstarlab' ),
			]
		);
	}

	/**
	 * Permanently dismiss the PRO promo card for the current user.
	 */
	public function dismiss_pro_promo() {
		$verify = $this->verify_request( 'dismiss_pro_promo' );
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message(), null, 403 );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->send_error( __( 'You do not have permission to perform this action', 'import-export-by-rockstarlab' ), null, 403 );
		}

		$context = strtolower( trim( (string) $this->get_request_param( 'context', '' ) ) );
		$allowed = [ 'import', 'export', 'updater' ];
		if ( ! in_array( $context, $allowed, true ) ) {
			$this->send_error( __( 'Invalid context', 'import-export-by-rockstarlab' ), null, 400 );
		}

		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			$this->send_error( __( 'User not found', 'import-export-by-rockstarlab' ), null, 400 );
		}

		update_user_meta( $user_id, 'rsl_ie_dismiss_pro_promo_' . $context, 1 );

		$this->send_success(
			[
				'dismissed' => true,
			]
		);
	}

	/**
	 * Validate OpenAI API key format
	 *
	 * @param string $api_key API key to validate.
	 * @return bool
	 */
	private function validate_openai_api_key( $api_key ) {
		// OpenAI API keys typically start with 'sk-'
		return strpos( $api_key, 'sk-' ) === 0 && strlen( $api_key ) > 10;
	}
}

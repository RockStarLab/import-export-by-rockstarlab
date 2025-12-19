<?php
/**
 * Settings Controller
 *
 * Handles plugin settings operations
 *
 * @package WP_AIE\Controller
 */

namespace WP_AIE\Controller;

/**
 * Settings Controller Class
 *
 * Manages plugin settings via AJAX:
 * - OpenAI API Key
 * - Other plugin options
 *
 * @package WP_AIE\Controller
 */
class Settings_Controller extends Base_Controller {

	/**
	 * Get AJAX actions
	 *
	 * @return array
	 */
	protected function get_ajax_actions() {
		return [
			'settings_save' => [ 'callback' => 'save_settings' ],
		];
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
			$this->send_error( __( 'You do not have permission to manage settings', 'wp-advanced-import-export' ) );
		}

		$openai_api_key = $this->get_request_param( 'openai_api_key', '' );

		// Save OpenAI API Key
		if ( ! empty( $openai_api_key ) ) {
			// Validate API key format
			if ( ! $this->validate_openai_api_key( $openai_api_key ) ) {
				$this->send_error( __( 'Invalid OpenAI API key format. Key should start with "sk-"', 'wp-advanced-import-export' ) );
			}

			update_option( 'wp_aie_openai_api_key', sanitize_text_field( $openai_api_key ) );
		} else {
			// Remove API key if empty
			delete_option( 'wp_aie_openai_api_key' );
		}

		$this->send_success(
			[
				'message' => __( 'Settings saved successfully', 'wp-advanced-import-export' ),
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

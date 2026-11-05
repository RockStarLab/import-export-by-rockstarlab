<?php
/**
 * OpenAI API Key resolver
 *
 * WordPress 7+ provides an AI Client and a central place to manage AI provider
 * credentials. This plugin must not read those connector credentials directly.
 *
 * On WordPress 7.0+, AI requests go through the WordPress AI Client. On older
 * WordPress versions, this helper may return the plugin-owned API key option.
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

class OpenAI_API_Key {

	/**
	 * Check if current WordPress version is 7.0+.
	 *
	 * @return bool
	 */
	public static function is_wp7_plus() {
		global $wp_version;

		return isset( $wp_version ) && version_compare( $wp_version, '7.0', '>=' );
	}

	/**
	 * Check whether the WordPress AI Client can generate text.
	 *
	 * @return bool
	 */
	public static function has_wp_ai_client() {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return false;
		}

		$builder = call_user_func( 'wp_ai_client_prompt', 'test' );

		if ( is_callable( [ $builder, 'using_model_preference' ] ) ) {
			$builder = $builder->using_model_preference( 'gpt-4.1-mini', 'gpt-4o-mini', 'gpt-4o' );
		}

		if ( is_callable( [ $builder, 'is_supported_for_text_generation' ] ) ) {
			return (bool) $builder->is_supported_for_text_generation();
		}

		return false;
	}

	/**
	 * Get an OpenAI API key explicitly configured for this plugin.
	 *
	 * @return string
	 */
	public static function get_api_key() {
		if ( self::is_wp7_plus() ) {
			return '';
		}

		$plugin_key = get_option( 'rsl_ie_openai_api_key', '' );
		if ( is_string( $plugin_key ) && $plugin_key !== '' ) {
			return $plugin_key;
		}

		return '';
	}

	/**
	 * Check if AI is available through this plugin's key or WordPress AI Client.
	 *
	 * @return bool
	 */
	public static function has_api_key() {
		if ( self::is_wp7_plus() ) {
			return self::has_wp_ai_client();
		}

		return self::get_api_key() !== '';
	}
}

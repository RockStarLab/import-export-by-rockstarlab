<?php
/**
 * OpenAI API Key resolver
 *
 * WordPress 7+ provides a Connectors API and a central place to manage AI provider
 * credentials (Settings > Connectors).
 *
 * This helper resolves the OpenAI API key with this priority:
 *  1) WordPress Connectors (WP 7+): env/const/db (OPENAI_API_KEY / connectors_ai_openai_api_key)
 *  2) Plugin option: rsl_ie_openai_api_key
 *  3) Plugin constant: RSL_IE_OPENAI_API_KEY
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
	 * Get OpenAI API key from WordPress Connectors (WP 7+), if configured.
	 *
	 * @return string
	 */
	public static function get_wp_connector_api_key() {
		if ( ! self::is_wp7_plus() ) {
			return '';
		}

		$env_key = getenv( 'OPENAI_API_KEY' );
		if ( is_string( $env_key ) && $env_key !== '' ) {
			return $env_key;
		}

		if ( defined( 'OPENAI_API_KEY' ) && is_string( OPENAI_API_KEY ) && OPENAI_API_KEY !== '' ) {
			return OPENAI_API_KEY;
		}

		$db_key = get_option( 'connectors_ai_openai_api_key', '' );
		if ( is_string( $db_key ) && $db_key !== '' ) {
			return $db_key;
		}

		return '';
	}

	/**
	 * Check if WordPress Connectors have an OpenAI key configured.
	 *
	 * @return bool
	 */
	public static function has_wp_connector_api_key() {
		return self::get_wp_connector_api_key() !== '';
	}

	/**
	 * Get OpenAI API key for this plugin.
	 *
	 * @return string
	 */
	public static function get_api_key() {
		$connector_key = self::get_wp_connector_api_key();
		if ( $connector_key !== '' ) {
			return $connector_key;
		}

		$plugin_key = get_option( 'rsl_ie_openai_api_key', '' );
		if ( is_string( $plugin_key ) && $plugin_key !== '' ) {
			return $plugin_key;
		}

		if ( defined( 'RSL_IE_OPENAI_API_KEY' ) && is_string( RSL_IE_OPENAI_API_KEY ) && RSL_IE_OPENAI_API_KEY !== '' ) {
			return RSL_IE_OPENAI_API_KEY;
		}

		return '';
	}

	/**
	 * Check if any OpenAI API key is available to the plugin.
	 *
	 * @return bool
	 */
	public static function has_api_key() {
		return self::get_api_key() !== '';
	}
}

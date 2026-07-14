<?php
/**
 * Action-specific AJAX nonce registry.
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Registers and verifies nonces scoped to individual admin-ajax actions.
 */
class Ajax_Security {

	/**
	 * Registered AJAX action names.
	 *
	 * @var array<string, bool>
	 */
	private static $actions = array();

	/**
	 * Register an AJAX action that needs a browser nonce.
	 *
	 * @param string $action Full admin-ajax action name.
	 */
	public static function register_action( $action ) {
		$action = sanitize_key( $action );
		if ( '' !== $action ) {
			self::$actions[ $action ] = true;
		}
	}

	/**
	 * Return the nonce action for one AJAX endpoint.
	 *
	 * @param string $action Full admin-ajax action name.
	 * @return string
	 */
	public static function nonce_action( $action ) {
		return 'rsl_ie_ajax_' . sanitize_key( $action );
	}

	/**
	 * Create a nonce for one AJAX endpoint.
	 *
	 * @param string $action Full admin-ajax action name.
	 * @return string
	 */
	public static function create_nonce( $action ) {
		return wp_create_nonce( self::nonce_action( $action ) );
	}

	/**
	 * Create the action => nonce map exposed to plugin scripts.
	 *
	 * @return array<string, string>
	 */
	public static function get_nonces() {
		$nonces = array();
		foreach ( array_keys( self::$actions ) as $action ) {
			$nonces[ $action ] = self::create_nonce( $action );
		}

		return $nonces;
	}

	/**
	 * Verify the nonce for a known server-side AJAX action.
	 *
	 * @param string $action Full admin-ajax action name.
	 * @return bool
	 */
	public static function verify_nonce( $action ) {
		if ( empty( $action ) ) {
			return false;
		}

		return false !== check_ajax_referer( self::nonce_action( $action ), 'nonce', false );
	}
}

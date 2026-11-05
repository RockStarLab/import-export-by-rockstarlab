<?php
/**
 * Bind peer credentials to a revocable WordPress execution identity.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Execution_Context;

defined( 'ABSPATH' ) || exit;

/** Authentication never leaves a changed current user behind. */
class Sync_Authentication {
	/**
	 * Bind a key only in an explicitly authorized key setup/read operation.
	 *
	 * @param string $key Current incoming key.
	 * @param bool   $replace New key requires a new binding.
	 * @return true|\WP_Error
	 */
	public static function bind( $key, $replace = false ) {
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access; }
		$binding = get_option( 'rsl_ie_sync_key_identity' );
		if ( ! $replace && is_array( $binding ) && hash_equals( (string) ( $binding['key_hash'] ?? '' ), hash( 'sha256', $key ) ) ) {
			return true; }
		update_option(
			'rsl_ie_sync_key_identity',
			[
				'user_id'  => get_current_user_id(),
				'blog_id'  => get_current_blog_id(),
				'key_hash' => hash( 'sha256', $key ),
			],
			false
		);
		return true;
	}

	/**
	 * Resolve the peer identity without modifying global state.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return Execution_Context|\WP_Error
	 */
	public static function context( $request ) {
		$header = $request->get_header( 'Authorization' );
		$key    = get_option( 'rsl_ie_site_api_key' );
		if ( ! is_string( $key ) || '' === $key || ! preg_match( '/^Bearer\s+(\S+)\s*$/iD', (string) $header, $matches ) || ! hash_equals( $key, $matches[1] ) ) {
			return new \WP_Error( 'rsl_ie_sync_key_invalid', __( 'A valid Content Sync bearer key is required.', 'import-export-by-rockstarlab' ), [ 'status' => 403 ] );
		}
		$binding = get_option( 'rsl_ie_sync_key_identity' );
		if ( ! is_array( $binding ) || ! hash_equals( (string) ( $binding['key_hash'] ?? '' ), hash( 'sha256', $key ) ) || get_current_blog_id() !== (int) ( $binding['blog_id'] ?? 0 ) ) {
			return new \WP_Error( 'rsl_ie_sync_key_setup_required', __( 'An administrator must open this site’s Content Sync API key once to bind the existing key to their account. The key does not need to change.', 'import-export-by-rockstarlab' ), [ 'status' => 403 ] );
		}
		return Execution_Context::from_storage( (int) ( $binding['user_id'] ?? 0 ), (int) $binding['blog_id'] );
	}

	/**
	 * Check that the bound owner still has access.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @return true|\WP_Error
	 */
	public static function permission( $request ) {
		$context = self::context( $request );
		return is_wp_error( $context ) ? $context : $context->run(
			static function () {
				return Access_Policy::check_management();
			}
		);
	}

	/**
	 * Revalidate credential and owner immediately before calling the handler.
	 *
	 * @param \WP_REST_Request $request Incoming request.
	 * @param callable         $callback Domain handler.
	 * @return mixed
	 */
	public static function run( $request, callable $callback ) {
		$context = self::context( $request );
		return is_wp_error( $context ) ? $context : $context->run(
			static function () use ( $callback, $request ) {
				return $callback( $request );
			}
		);
	}
}

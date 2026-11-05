<?php
/**
 * Explicit incoming and connection key operations.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Settings_Catalog;
use RockStarLab\ImportExport\Helper\Site_URL;
use RockStarLab\ImportExport\Model\Connected_Site;

defined( 'ABSPATH' ) || exit;

/** Separate status reads from secret disclosure and rotation. */
class Credential_Service {
	/**
	 * Execute one closed credential operation.
	 *
	 * @param string $name Operation.
	 * @param array  $input Input.
	 * @return mixed
	 */
	public function execute( $name, array $input = [] ) {
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access; }
		if ( ! in_array( $name, [ 'site-key-status', 'site-key-create', 'site-key-reveal', 'site-key-rotate', 'connection-key-rotate' ], true ) ) {
			return $this->error( 'operation', 400 );
		}
		$valid = rest_validate_value_from_schema( $input, Settings_Catalog::definitions()[ $name ]['input'], 'input' );
		if ( is_wp_error( $valid ) ) {
			$valid->add_data( [ 'status' => 400 ] );
			return $valid; }
		if ( 'connection-key-rotate' === $name ) {
			$result = ( new Connection_Service() )->execute( 'regenerate_key', $input );
			return is_wp_error( $result ) ? $this->error( 'connection_rotation_failed', $result->get_error_data()['status'] ?? 400 ) : $result;
		}
		$lock = Job_Lock::acquire_resource( 'site-key', 'current' );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return $this->error( 'busy', 409 ); }
		try {
			$key  = get_option( 'rsl_ie_site_api_key', '' );
			$key  = is_string( $key ) ? $key : '';
			$site = [
				'site_url'  => Site_URL::current_request_site_url(),
				'site_name' => get_bloginfo( 'name' ),
			];
			if ( 'site-key-status' === $name ) {
				return $this->status( $key ) + $site; }
			if ( 'site-key-reveal' === $name && '' === $key ) {
				return $this->error( 'missing', 404 ); }
			if ( 'site-key-rotate' === $name || '' === $key ) {
				$key = Connected_Site::generate_api_key();
				update_option( 'rsl_ie_site_api_key', $key, false );
				if ( ! hash_equals( $key, (string) get_option( 'rsl_ie_site_api_key', '' ) ) ) {
					return $this->error( 'write_failed', 500 ); }
			}
			$bound = Sync_Authentication::bind( $key, 'site-key-rotate' === $name );
			if ( is_wp_error( $bound ) || ! $this->status( $key )['bound'] ) {
				return $this->error( 'binding_failed', 500 ); }
			return 'site-key-create' === $name ? $this->status( $key ) + $site : [ 'site_key' => $key ] + $site;
		} finally {
			Job_Lock::release( $lock ); }
	}

	/**
	 * Read identity status without exposing its hash or credential.
	 *
	 * @param string $key Current key.
	 * @return array
	 */
	private function status( $key ) {
		$identity = get_option( 'rsl_ie_sync_key_identity', [] );
		$bound    = '' !== $key && is_array( $identity ) && ! empty( $identity['user_id'] ) && get_current_blog_id() === (int) ( $identity['blog_id'] ?? 0 ) && hash_equals( hash( 'sha256', $key ), (string) ( $identity['key_hash'] ?? '' ) );
		return [
			'configured'   => '' !== $key,
			'bound'        => $bound,
			'owner_active' => $bound && user_can( (int) $identity['user_id'], 'manage_options' ),
		];
	}

	/**
	 * Return a safe error.
	 *
	 * @param string $code Suffix.
	 * @param int    $status Status.
	 * @return \WP_Error
	 */
	private function error( $code, $status ) {
		return new \WP_Error( 'rsl_ie_credential_' . $code, 'Credential operation failed: ' . $code, [ 'status' => $status ] );
	}
}

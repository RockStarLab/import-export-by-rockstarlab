<?php
/**
 * Trusted user/site context for background application services.
 *
 * @package RockStarLab\ImportExport\Abilities
 */

namespace RockStarLab\ImportExport\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * Captures server-owned identity and restores it without cookies or nonces.
 *
 * Never deserialize this context from an ability's input. A worker must read
 * it from the job record created by the authenticated application service.
 */
class Execution_Context {

	/**
	 * Initiating user ID.
	 *
	 * @var int
	 */
	private $user_id;

	/**
	 * Originating site ID.
	 *
	 * @var int
	 */
	private $blog_id;

	/**
	 * Store validated identity.
	 *
	 * @param int $user_id Initiating user.
	 * @param int $blog_id Originating site.
	 */
	private function __construct( $user_id, $blog_id ) {
		$this->user_id = (int) $user_id;
		$this->blog_id = (int) $blog_id;
	}

	/**
	 * Capture the authenticated request, ignoring all agent-supplied identity.
	 *
	 * @return self|\WP_Error
	 */
	public static function capture() {
		$permission = Access_Policy::check_management();
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		return new self( get_current_user_id(), get_current_blog_id() );
	}

	/**
	 * Rehydrate only identity read by the job service from trusted storage.
	 *
	 * @param int $user_id Persisted initiating user ID.
	 * @param int $blog_id Persisted site ID.
	 * @return self|\WP_Error
	 */
	public static function from_storage( $user_id, $blog_id ) {
		if ( ! is_int( $user_id ) || ! is_int( $blog_id ) || $user_id < 1 || $blog_id < 1 ) {
			return new \WP_Error( 'rsl_ie_invalid_context', __( 'The job execution context is invalid.', 'import-export-by-rockstarlab' ), [ 'status' => 400 ] );
		}
		return new self( $user_id, $blog_id );
	}

	/**
	 * Serialize identity for job storage, never API responses.
	 *
	 * @return array
	 */
	public function to_storage() {
		return [
			'user_id' => $this->user_id,
			'blog_id' => $this->blog_id,
		];
	}

	/**
	 * Run a service as the initiating user and always restore ambient identity.
	 *
	 * The operation callback must additionally check content/provider/license
	 * permissions, because those can change after a job was queued.
	 *
	 * @param callable $operation Trusted server-side service callback.
	 * @return mixed|\WP_Error
	 */
	public function run( callable $operation ) {
		$previous_user = get_current_user_id();
		$switched      = false;
		if ( get_current_blog_id() !== $this->blog_id ) {
			if ( ! is_multisite() || ! get_site( $this->blog_id ) ) {
				return new \WP_Error( 'rsl_ie_site_unavailable', __( 'The job site is unavailable.', 'import-export-by-rockstarlab' ), [ 'status' => 404 ] );
			}
			switch_to_blog( $this->blog_id );
			$switched = true;
		}
		try {
			wp_set_current_user( $this->user_id );
			$permission = Access_Policy::check_management();
			if ( is_wp_error( $permission ) ) {
				return $permission;
			}
			return $operation();
		} finally {
			if ( $switched ) {
				restore_current_blog();
			}
			wp_set_current_user( $previous_user );
		}
	}
}

<?php
/**
 * Shared authorization rules for abilities and application services.
 *
 * @package RockStarLab\ImportExport\Abilities
 */

namespace RockStarLab\ImportExport\Abilities;

use RockStarLab\ImportExport\Abilities\Providers\Free_Content_Provider;

defined( 'ABSPATH' ) || exit;

/**
 * Preserves the existing admin boundary and checks resource ownership explicitly.
 */
class Access_Policy {

	/**
	 * Require an available provider for every requested field transformation.
	 *
	 * @param array $groups Lists of transformation IDs from normalized mappings.
	 * @return true|\WP_Error
	 */
	public static function check_transformations( array $groups ) {
		$ids = [];
		foreach ( $groups as $group ) {
			foreach ( (array) $group as $id ) {
				$id = is_array( $id ) ? ( $id['id'] ?? '' ) : $id;
				if ( ! is_scalar( $id ) || '' === (string) $id ) {
					return new \WP_Error( 'rsl_ie_invalid_transformation', __( 'Invalid field transformation.', 'import-export-by-rockstarlab' ), [ 'status' => 400 ] );
				}
				$ids[] = (string) $id;
			}
		}
		if ( ! $ids ) {
			return true;
		}
		$access = self::check_management();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		/**
		 * Authorize configured Functions without loading their code in Free.
		 *
		 * @param null|true|\WP_Error $result Provider authorization.
		 * @param array $ids Unique requested IDs.
		 */
		$result = apply_filters( 'rsl_ie_ability_authorize_transformations', null, array_values( array_unique( $ids ) ) );
		return true === $result || is_wp_error( $result ) ? $result : new \WP_Error( 'rsl_ie_transformations_unavailable', __( 'Requested field transformations require an available PRO provider.', 'import-export-by-rockstarlab' ), [ 'status' => 403 ] );
	}

	/**
	 * Authorize a type after parsing and before creating or executing a job.
	 *
	 * @param string $type      Requested type or legacy alias.
	 * @param string $operation Server-selected operation.
	 * @param array  $context   Normalized options, parsed records and target IDs.
	 * @return array|\WP_Error Canonical type and edition, or a failure.
	 */
	public static function check_content_type( $type, $operation, array $context = [] ) {
		$permission = self::check_management();
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		if ( ! is_string( $type ) || ! is_string( $operation ) ) {
			return new \WP_Error( 'rsl_ie_invalid_type', __( 'A content type and operation are required.', 'import-export-by-rockstarlab' ), [ 'status' => 400 ] );
		}
		$type      = strtolower( trim( $type ) );
		$provider  = new Free_Content_Provider();
		$canonical = $provider->normalize( $type );
		if ( null !== $canonical ) {
			$permission = $provider->check( $canonical, $operation, $context );
			if ( is_wp_error( $permission ) ) {
				return $permission;
			}
			return [
				'type'    => $canonical,
				'edition' => 'free',
			];
		}

		/**
		 * Let a separately installed addon authorize its own content definitions.
		 *
		 * Free types are resolved before this hook and cannot be widened here.
		 *
		 * @param null|array|\WP_Error $result    Authorized definition or failure.
		 * @param string              $type      Requested type.
		 * @param string              $operation Server-selected operation.
		 * @param array               $context   Normalized operation context.
		 */
		$result = apply_filters( 'rsl_ie_ability_authorize_content_type', null, $type, $operation, $context );
		if ( is_wp_error( $result ) || ( is_array( $result ) && ! empty( $result['type'] ) && ! empty( $result['edition'] ) ) ) {
			return $result;
		}
		return new \WP_Error( 'rsl_ie_unsupported_content_type', __( 'No available provider supports this content type.', 'import-export-by-rockstarlab' ), [ 'status' => 400 ] );
	}

	/**
	 * Require the plugin's existing management capability.
	 *
	 * @return true|\WP_Error
	 */
	public static function check_management() {
		if ( ! get_current_user_id() || ! current_user_can( 'manage_options' ) ) {
			return new \WP_Error(
				'rsl_ie_forbidden',
				__( 'You do not have permission to use the plugin API.', 'import-export-by-rockstarlab' ),
				[ 'status' => 403 ]
			);
		}
		return true;
	}

	/**
	 * Authorize a job/file/schedule owned by a user on the current site.
	 *
	 * Existing plugin administrators can manage other users' jobs. This is
	 * deliberate and does not grant capabilities to an unauthenticated worker.
	 *
	 * @param int $owner_id Resource owner from trusted storage.
	 * @param int $blog_id  Resource site from trusted storage.
	 * @return true|\WP_Error
	 */
	public static function check_resource( $owner_id, $blog_id ) {
		$permission = self::check_management();
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		if ( get_current_blog_id() !== (int) $blog_id || 1 > (int) $owner_id ) {
			return new \WP_Error(
				'rsl_ie_resource_unavailable',
				__( 'This resource is not available in the current site context.', 'import-export-by-rockstarlab' ),
				[ 'status' => 404 ]
			);
		}
		return true;
	}

	/**
	 * Check one WordPress object capability without trusting the selected ID.
	 *
	 * @param string $capability Capability selected by a server-side handler.
	 * @param int    $object_id  Actual target object ID.
	 * @return true|\WP_Error
	 */
	public static function check_object( $capability, $object_id ) {
		$permission = self::check_management();
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		if ( (int) $object_id < 1 || ! current_user_can( $capability, (int) $object_id ) ) {
			return new \WP_Error(
				'rsl_ie_object_forbidden',
				__( 'You do not have permission to access this object.', 'import-export-by-rockstarlab' ),
				[ 'status' => 403 ]
			);
		}
		return true;
	}
}

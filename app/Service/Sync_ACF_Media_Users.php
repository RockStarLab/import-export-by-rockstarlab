<?php
/**
 * Provider boundary for confirmed attachment ACF user references.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

/** Dispatch only server-selected operations to the installed user provider. */
class Sync_ACF_Media_Users {
	/**
	 * Execute an optional protocol operation.
	 *
	 * @param string $operation Export, prepare or finalize.
	 * @param array  $data Actual payload or prepared assignments.
	 * @param array  $context Parsed protocol input.
	 * @return array|true|\WP_Error
	 */
	public static function run( $operation, array $data, array $context = [] ) {
		return apply_filters( 'rsl_ie_sync_acf_media_users', Migration_Sync_Maps::error(), $operation, $data, $context );
	}
}

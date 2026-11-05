<?php
/**
 * Provider boundary for confirmed attachment authors.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

/** Dispatch only server-selected operations to the installed author provider. */
class Sync_Media_Authors {
	/**
	 * Execute an optional protocol operation.
	 *
	 * @param string $operation Export, prepare or finalize.
	 * @param array  $data Actual payload or prepared assignments.
	 * @param array  $context Parsed protocol input.
	 * @return array|true|\WP_Error
	 */
	public static function run( $operation, array $data, array $context = [] ) {
		return apply_filters( 'rsl_ie_sync_media_authors', Migration_Sync_Maps::error(), $operation, $data, $context );
	}
}

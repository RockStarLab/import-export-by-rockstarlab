<?php
/**
 * Compatibility entry point for the shared export service.
 *
 * @package RockStarLab\ImportExport\Model\Queue
 */

namespace RockStarLab\ImportExport\Model\Queue;

use RockStarLab\ImportExport\Service\Export_Service;

defined( 'ABSPATH' ) || exit;

/**
 * Existing server-side callers use the same worker as AJAX and abilities.
 */
class Export_Processor {

	/**
	 * Execute a stored export using the initiating user's current permissions.
	 *
	 * @param int $job_id Trusted site-local job ID.
	 * @return array|\WP_Error
	 */
	public function process( $job_id ) {
		return ( new Export_Service() )->process_background_batch( $job_id );
	}
}

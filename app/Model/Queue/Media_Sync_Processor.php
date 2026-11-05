<?php
/**
 * Legacy folder worker adapter.
 *
 * @package RockStarLab\ImportExport\Model\Queue
 */

namespace RockStarLab\ImportExport\Model\Queue;

defined( 'ABSPATH' ) || exit;
/** All cron/queue callers use the shared checkpointed folder service. */
class Media_Sync_Processor {
	/**
	 * Process a stored job under its initiating identity.
	 *
	 * @param int $job_id Stored job ID.
	 * @return array|\WP_Error
	 */
	public function process( $job_id ) {
		return ( new \RockStarLab\ImportExport\Service\Media_Folder_Service() )->process_background_batch( $job_id );
	}
}

<?php
/**
 * Cron Manager
 *
 * Manages WP Cron schedules and background job processing
 *
 * @package RockStarLab\ImportExport\Model\Queue
 */

namespace RockStarLab\ImportExport\Model\Queue;

defined( 'ABSPATH' ) || exit;

class Cron_Manager {

	/**
	 * Background processor instance
	 *
	 * @var Background_Processor
	 */
	protected $processor;

	/**
	 * Cron hook name
	 *
	 * @var string
	 */
	const CRON_HOOK = 'rsl_ie_process_queue';

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->processor = new Background_Processor();
	}

	/**
	 * Initialize cron hooks
	 */
	public function init() {
		// Register cron action
		add_action( self::CRON_HOOK, array( $this, 'process_queue' ) );

		// Register media sync job cron action
		add_action( 'rsl_ie_process_media_sync_job', array( $this, 'process_media_sync_job' ) );

		// Register AI URL import job cron action
		add_action( 'rsl_ie_process_ai_url_import', array( $this, 'process_ai_url_import' ) );

		// Add custom cron schedule
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );

		// Schedule cron if not already scheduled
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time(), 'rsl_ie_every_minute', self::CRON_HOOK );
		}
	}

	/**
	 * Add custom cron schedules
	 *
	 * @param array $schedules Existing schedules
	 * @return array Modified schedules
	 */
	public function add_cron_schedules( $schedules ) {
		$schedules['rsl_ie_every_minute'] = array(
			'interval' => 60,
			'display'  => __( 'Every Minute', 'import-export-by-rockstarlab' ),
		);

		$schedules['rsl_ie_every_five_minutes'] = array(
			'interval' => 300,
			'display'  => __( 'Every 5 Minutes', 'import-export-by-rockstarlab' ),
		);
		$schedules['weekly']                    = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Once Weekly', 'import-export-by-rockstarlab' ),
		);

		return $schedules;
	}

	/**
	 * Process queue (called by cron)
	 */
	public function process_queue() {
		// Process next job
		$processed = $this->processor->process_next_job();

		// If job was processed, check for more
		if ( $processed ) {
			// Schedule immediate next run if there are pending jobs
			$this->schedule_immediate_run();
		}
	}

	/**
	 * Schedule immediate run
	 */
	protected function schedule_immediate_run() {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time(), self::CRON_HOOK );
		}
	}

	/**
	 * Manually trigger queue processing
	 *
	 * @return bool Success
	 */
	public function trigger_process() {
		return $this->processor->process_next_job();
	}

	/**
	 * Clear scheduled cron
	 */
	public function clear_schedule() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
		}
	}

	/**
	 * Get next scheduled run
	 *
	 * @return int|false Timestamp or false
	 */
	public function get_next_scheduled() {
		return wp_next_scheduled( self::CRON_HOOK );
	}

	/**
	 * Check if cron is running
	 *
	 * @return bool
	 */
	public function is_running() {
		return (bool) get_transient( 'rsl_ie_cron_running' );
	}

	/**
	 * Set batch size for processor
	 *
	 * @param int $size Batch size
	 */
	public function set_batch_size( $size ) {
		$this->processor->set_batch_size( $size );
	}

	/**
	 * Set max retries for processor
	 *
	 * @param int $retries Max retries
	 */
	public function set_max_retries( $retries ) {
		$this->processor->set_max_retries( $retries );
	}

	/**
	 * Get processor instance
	 *
	 * @return Background_Processor
	 */
	public function get_processor() {
		return $this->processor;
	}

	/**
	 * Process media sync job
	 *
	 * @param int $job_id Job ID
	 */
	public function process_media_sync_job( $job_id ) {
		$processor = new Media_Sync_Processor();
		$processor->process( $job_id );
	}

	/**
	 * Process AI URL import job
	 *
	 * @param int $job_id Job ID
	 */
	public function process_ai_url_import( $job_id ) {
		$processor = new AI_URL_Import_Processor();
		$processor->process( $job_id );
	}
}

<?php
/**
 * Job Controller
 *
 * Handles job management operations via AJAX
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Model\Job;
use RockStarLab\ImportExport\Service\Job_Service;
use RockStarLab\ImportExport\Helper\Ajax_Security;

defined( 'ABSPATH' ) or exit;

class Job_Controller extends Base_Controller {
	/**
	 * Format duration in human-readable format
	 *
	 * @param int $seconds Duration in seconds
	 * @return string
	 */
	private function format_duration( $seconds ) {
		$seconds = (int) max( 0, $seconds );

		if ( $seconds < 60 ) {
			return $seconds . 's';
		}

		if ( $seconds < 3600 ) {
			$minutes = (int) floor( $seconds / 60 );
			$secs    = (int) ( $seconds % 60 );
			return sprintf( '%dm %ds', $minutes, $secs );
		}

		$hours   = (int) floor( $seconds / 3600 );
		$minutes = (int) floor( ( $seconds % 3600 ) / 60 );
		return sprintf( '%dh %dm', $hours, $minutes );
	}

	/**
	 * Normalize job parameters for rerun.
	 *
	 * Some job types mutate their parameters during execution (e.g. import stores
	 * prepared_data/offset). When re-running from Jobs Log we want to preserve
	 * the user configuration but reset runtime state so processing starts over.
	 *
	 * @param string $job_type
	 * @param mixed  $raw_parameters
	 * @return mixed
	 */
	private function normalize_rerun_parameters( $job_type, $raw_parameters ) {
		if ( 'import' !== $job_type ) {
			return $raw_parameters;
		}

		if ( empty( $raw_parameters ) || ! is_string( $raw_parameters ) ) {
			return $raw_parameters;
		}

		$params = json_decode( $raw_parameters, true );
		if ( ! is_array( $params ) ) {
			return $raw_parameters;
		}

		// Strip runtime state.
		unset( $params['prepared_data'], $params['prepared_rows_count'] );
		unset( $params['total_items'] );
		unset( $params['cumulative_result'] );
		unset( $params['offset'] );

		// Ensure fresh start.
		$params['offset'] = 0;

		return wp_json_encode( $params );
	}

	/**
	 * Recognize versioned domain jobs without depending on PRO classes.
	 *
	 * @param object|null $job Stored record.
	 * @return bool
	 */
	private static function is_special_managed_job( $job ) {
		if ( ! $job || 'import' !== $job->type ) {
			return false;
		}
		$params = json_decode( (string) $job->parameters, true );
		return ( 'ai_url' === $job->data_type && 1 === ( $params['ai_url_version'] ?? null ) ) || ( 'content_update' === $job->data_type && 1 === ( $params['updater_version'] ?? null ) );
	}

	/**
	 * Route existing file jobs through their service, preserving domain adapters.
	 *
	 * @return bool
	 */
	private function managed_request() {
		$job = rsl_ie()->Model->job->find( (int) $this->get_request_param( 'job_id' ) );
		return self::is_special_managed_job( $job ) || Job_Service::supports( $job ) || \RockStarLab\ImportExport\Service\Media_Folder_Service::supports( $job ) || \RockStarLab\ImportExport\Service\Sync_Job_Service::supports( $job ) || \RockStarLab\ImportExport\Service\Migration_Service::supports( $job ) || ( $job && ! empty( json_decode( $job->parameters, true )['migration_parent_id'] ) );
	}

	/** Stop a managed job after validating the existing AJAX nonce. */
	public function pause_job() {
		$permission = $this->verify_request();
		if ( is_wp_error( $permission ) ) {
			$this->send_error( $permission, null, 403 );
			return; }
		$result = ( new Job_Service() )->stop( (int) $this->get_request_param( 'job_id' ), 'pause' );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result, null, $result->get_error_data()['status'] ?? 400 );
			return; }
		$this->send_success( $result );
	}

	/**
	 * Get AJAX actions
	 *
	 * @return array
	 */
	protected function get_ajax_actions() {
		return [
			'job_list'         => [ 'callback' => 'list_jobs' ],
			'job_get'          => [ 'callback' => 'get_job' ],
			'job_delete'       => [ 'callback' => 'delete_job' ],
			'job_get_logs'     => [ 'callback' => 'get_logs' ],
			'job_cleanup'      => [ 'callback' => 'cleanup_old_jobs' ],
			'job_pause'        => [ 'callback' => 'pause_job' ],
			'job_resume'       => [ 'callback' => 'resume_job' ],
			'job_restart'      => [ 'callback' => 'restart_job' ],
			'job_retry'        => [ 'callback' => 'retry_job' ],
			'job_rename'       => [ 'callback' => 'rename_job' ],
			'job_download_url' => [ 'callback' => 'get_download_url' ],
		];
	}

	/**
	 * List jobs
	 */
	public function list_jobs() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$type   = $this->get_request_param( 'type', '' );
		$status = $this->get_request_param( 'status', '' );
		$limit  = (int) $this->get_request_param( 'limit', 20 );
		$offset = (int) $this->get_request_param( 'offset', 0 );

		$job_model = rsl_ie()->Model->job;
		$where     = [];

		if ( 'content_update' === $type ) {
			// Content Updater jobs are stored as type=import with data_type=content_update.
			$where['type']      = 'import';
			$where['data_type'] = 'content_update';
		} elseif ( ! empty( $type ) ) {
			$where['type'] = $type;
		}

		if ( ! empty( $status ) ) {
			$where['status'] = $status;
		}

		// Get jobs using get_all method
		$jobs = $job_model->get_all( $where, $limit, $offset, 'created_at DESC' );

		// Add action flags to each job
		foreach ( $jobs as &$job ) {
			// Can resume: paused or failed jobs
			$job->can_resume = in_array( $job->status, [ 'paused', 'failed' ], true );

			// Can delete: any job
			$job->can_delete = true;

			// Can retry: any job
			$job->can_retry   = true;
			$job->can_restart = true;
			if ( in_array( $job->type, [ 'content_sync_push', 'content_sync_pull' ], true ) ) {
				// History rows of immediate syncs: only Restart runs them again (see restart_job).
				$job->can_restart = in_array( $job->status, [ 'completed', 'failed', 'cancelled' ], true );
				$job->can_retry   = false;
				$job->can_resume  = false;
			}
			if ( 'migration_sources' === $job->type && ! empty( json_decode( (string) $job->parameters, true )['variation_workflow_version'] ) ) {
				// Variation transfer of a product sync: its parent sync runs it, it cannot run alone.
				$job->can_restart = false;
				$job->can_retry   = false;
				$job->can_resume  = false;
			}
			if ( self::is_special_managed_job( $job ) || Job_Service::supports( $job ) || \RockStarLab\ImportExport\Service\Media_Folder_Service::supports( $job ) || \RockStarLab\ImportExport\Service\Sync_Job_Service::supports( $job ) || \RockStarLab\ImportExport\Service\Migration_Service::supports( $job ) || ( $job && ! empty( json_decode( $job->parameters, true )['migration_parent_id'] ) ) ) {
				$snapshot         = ( new Job_Service() )->get( $job->id );
				$actions          = is_wp_error( $snapshot ) ? [] : $snapshot['allowed_actions'];
				$job->can_retry   = in_array( 'retry', $actions, true );
				$job->can_restart = in_array( 'restart', $actions, true );
				$job->can_resume  = in_array( 'resume', $actions, true );
				$job->can_delete  = in_array( 'delete', $actions, true );
			}
			unset( $job->request_payload, $job->request_key, $job->request_hash );

			$job->job_name = isset( $job->job_name ) ? (string) $job->job_name : '';

			// Aliases for JS layer; prefer the canonical counters when present.
			$job->success_items = (int) ( $job->success_items ?? $job->imported_items ?? 0 );
			$job->failed_items  = (int) ( $job->failed_items ?? $job->error_items ?? 0 );

			// Provide real elapsed time for Jobs Log table.
			// Prefer started_at/completed_at; fall back to created_at for jobs that
			// never set started_at (e.g. restarted jobs that haven't started yet).
			$job->elapsed_time = '-';

			$start_ts = 0;
			if ( ! empty( $job->started_at ) ) {
				$start_ts = (int) strtotime( $job->started_at );
			} elseif ( ! empty( $job->created_at ) && ! in_array( $job->status, [ 'pending' ], true ) ) {
				$start_ts = (int) strtotime( $job->created_at );
			}

			if ( $start_ts > 0 ) {
				$end_ts = 0;
				if ( ! empty( $job->completed_at ) && in_array( $job->status, [ 'completed', 'failed', 'cancelled' ], true ) ) {
					$end_ts = (int) strtotime( $job->completed_at );
				} else {
					$end_ts = (int) current_time( 'timestamp' );
				}

				if ( $end_ts > 0 && $end_ts >= $start_ts ) {
					$job->elapsed_time = $this->format_duration( $end_ts - $start_ts );
				}
			}
		}
		unset( $job );

		// Get total count
		$total = $job_model->count( $where );

		$this->send_success(
			[
				'jobs'   => $jobs,
				'total'  => $total,
				'limit'  => $limit,
				'offset' => $offset,
			]
		);
	}

	/**
	 * Get single job
	 */
	public function get_job() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = rsl_ie()->Model->job;
		$job_data  = ( new Job_Service() )->record( $job_id );

		if ( is_wp_error( $job_data ) ) {
			$this->send_error( $job_data, null, $job_data->get_error_data()['status'] ?? 400 );
			return;
		}
		if ( ! $job_data ) {
			$this->send_error( __( 'Job not found', 'import-export-by-rockstarlab' ), null, 404 );
		}

		unset( $job_data->request_payload, $job_data->request_key, $job_data->request_hash );
		// Parse parameters and result
		$job_data->parameters = json_decode( $job_data->parameters, true );
		$job_data->result     = json_decode( $job_data->result, true );

		// Aliases for JS layer; prefer the canonical counters when present.
		$job_data->success_items = (int) ( $job_data->success_items ?? $job_data->imported_items ?? 0 );
		$job_data->failed_items  = (int) ( $job_data->failed_items ?? $job_data->error_items ?? 0 );
		$job_data->job_name      = isset( $job_data->job_name ) ? (string) $job_data->job_name : '';
		$job_data->file_size_human = isset( $job_data->file_size ) ? size_format( (int) $job_data->file_size, 2 ) : '';

		$this->send_success( $job_data );
	}

	/**
	 * Rename a job from Jobs Log.
	 */
	public function rename_job() {
		if ( $this->managed_request() ) {
			$permission = $this->verify_request();
			if ( is_wp_error( $permission ) ) {
				$this->send_error( $permission, null, 403 );
				return; }
			$service = new Job_Service();
			$id      = (int) $this->get_request_param( 'job_id' );
			$result  = $service->rename( $id, $this->get_request_param( 'job_name', '' ) );
			if ( is_wp_error( $result ) ) {
				$this->send_error( $result, null, $result->get_error_data()['status'] ?? 400 );
				return; }
			$job = $service->record( $result['job_id'] );
			if ( ! is_wp_error( $job ) ) {
				$result['type']      = $job->type;
				$result['data_type'] = $job->data_type; }
			$this->send_success( $result );
			return;
		}

		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id   = (int) $this->get_request_param( 'job_id' );
		$job_name = $this->get_request_param( 'job_name', '' );
		$job_name = sanitize_text_field( wp_unslash( (string) $job_name ) );
		$job_name = function_exists( 'mb_substr' ) ? mb_substr( $job_name, 0, 255 ) : substr( $job_name, 0, 255 );

		$job_model = rsl_ie()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data ) {
			$this->send_error( __( 'Job not found', 'import-export-by-rockstarlab' ), null, 404 );
		}

		$result = $job_model->update(
			$job_id,
			[
				'job_name'   => '' === $job_name ? null : $job_name,
				'updated_at' => current_time( 'mysql' ),
			]
		);

		if ( is_wp_error( $result ) ) {
			$this->send_error( $result, null, 500 );
		}

		$this->send_success(
			[
				'job_id'   => $job_id,
				'job_name' => $job_name,
			],
			__( 'Job name updated successfully', 'import-export-by-rockstarlab' )
		);
	}

	/**
	 * Delete job
	 */
	public function delete_job() {
		if ( $this->managed_request() ) {
			$permission = $this->verify_request();
			if ( is_wp_error( $permission ) ) {
				$this->send_error( $permission, null, 403 );
				return; }
			$service = new Job_Service();
			$id      = (int) $this->get_request_param( 'job_id' );
			$result  = $service->delete( $id );
			if ( is_wp_error( $result ) ) {
				$this->send_error( $result, null, $result->get_error_data()['status'] ?? 400 );
				return; }
			$job = $service->record( $result['job_id'] );
			if ( ! is_wp_error( $job ) ) {
				$result['type']      = $job->type;
				$result['data_type'] = $job->data_type; }
			$this->send_success( $result );
			return;
		}

		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = rsl_ie()->Model->job;
		$result    = $job_model->delete( $job_id );

		if ( is_wp_error( $result ) ) {
			$this->send_error( $result, null, 500 );
		}

		$this->send_success( null, __( 'Job deleted successfully', 'import-export-by-rockstarlab' ) );
	}

	/**
	 * Get job logs
	 */
	public function get_logs() {
		$permission = $this->verify_request();
		if ( is_wp_error( $permission ) ) {
			$this->send_error( $permission, null, 403 );
			return; }
		$result = ( new Job_Service() )->logs( (int) $this->get_request_param( 'job_id' ), $this->get_request_param( 'level', 'all' ), (int) $this->get_request_param( 'limit', 100 ), (int) $this->get_request_param( 'offset', 0 ) );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result, null, $result->get_error_data()['status'] ?? 400 );
			return; }
		$this->send_success( $result );
	}

	/**
	 * Cleanup old jobs
	 */
	public function cleanup_old_jobs() {
		$permission = $this->verify_request();
		if ( is_wp_error( $permission ) ) {
			$this->send_error( $permission, null, 403 );
			return; }
		$result = ( new Job_Service() )->cleanup( (int) $this->get_request_param( 'days', 30 ) );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result, null, $result->get_error_data()['status'] ?? 400 );
			return; }
		$this->send_success( $result );
	}

	/**
	 * Resume job
	 */
	public function resume_job() {
		if ( $this->managed_request() ) {
			$permission = $this->verify_request();
			if ( is_wp_error( $permission ) ) {
				$this->send_error( $permission, null, 403 );
				return; }
			$service = new Job_Service();
			$id      = (int) $this->get_request_param( 'job_id' );
			$result  = $service->continue_job( $id, 'resume' );
			if ( is_wp_error( $result ) ) {
				$this->send_error( $result, null, $result->get_error_data()['status'] ?? 400 );
				return; }
			$job = $service->record( $result['job_id'] );
			if ( ! is_wp_error( $job ) ) {
				$result['type']      = $job->type;
				$result['data_type'] = $job->data_type; }
			$this->send_success( $result );
			return;
		}

		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = rsl_ie()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data ) {
			$this->send_error( __( 'Job not found', 'import-export-by-rockstarlab' ), null, 404 );
		}

		// Check if job can be resumed
		if ( ! in_array( $job_data->status, [ 'paused', 'failed', 'processing' ], true ) ) {
			$this->send_error(
				sprintf(
					/* translators: %s: current job status */
					__( 'Job cannot be resumed. Current status: %s', 'import-export-by-rockstarlab' ),
					$job_data->status
				),
				null,
				400
			);
		}

		// Update job status
		$updated = $job_model->update(
			$job_id,
			[
				'status'     => 'processing',
				'updated_at' => current_time( 'mysql' ),
			]
		);

		if ( is_wp_error( $updated ) ) {
			$this->send_error( $updated, null, 500 );
		}

		// Parse parameters for frontend
		$parameters = maybe_unserialize( $job_data->parameters );

		$this->send_success(
			[
				'job_id'     => $job_id,
				'type'       => $job_data->type,
				'data_type'  => $job_data->data_type,
				'parameters' => $parameters,
			],
			__( 'Job resumed successfully', 'import-export-by-rockstarlab' )
		);
	}

	/**
	 * Restart job (create new job with same settings)
	 */
	public function restart_job() {
		$source = rsl_ie()->Model->job->find( (int) $this->get_request_param( 'job_id' ) );
		if ( $source && in_array( $source->type, [ 'content_sync_push', 'content_sync_pull' ], true ) ) {
			// Immediate syncs keep only a history row; run them again through the queued sync
			// workflow that schedules use. A plain copy of the row would stay pending forever.
			$permission = $this->verify_request();
			if ( is_wp_error( $permission ) ) {
				$this->send_error( $permission, null, 403 );
				return; }
			$result = ( new \RockStarLab\ImportExport\Service\Schedule_Source_Service() )->execute( (int) $source->id, 'restart:' . (int) $source->id . ':' . wp_generate_uuid4() );
			if ( is_wp_error( $result ) ) {
				$this->send_error( $result, null, $result->get_error_data()['status'] ?? 400 );
				return; }
			$job = is_array( $result ) && ! empty( $result['job_id'] ) ? rsl_ie()->Model->job->find( (int) $result['job_id'] ) : null;
			if ( $job ) {
				$result['type']      = $job->type;
				$result['data_type'] = $job->data_type; }
			$this->send_success( $result );
			return;
		}
		if ( $this->managed_request() ) {
			$permission = $this->verify_request();
			if ( is_wp_error( $permission ) ) {
				$this->send_error( $permission, null, 403 );
				return; }
			$service = new Job_Service();
			$id      = (int) $this->get_request_param( 'job_id' );
			$result  = $service->restart( $id, $this->get_request_param( 'request_id', '' ) );
			if ( is_wp_error( $result ) ) {
				$this->send_error( $result, null, $result->get_error_data()['status'] ?? 400 );
				return; }
			$job = $service->record( $result['job_id'] );
			if ( ! is_wp_error( $job ) ) {
				$result['type']      = $job->type;
				$result['data_type'] = $job->data_type; }
			$this->send_success( $result );
			return;
		}

		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = rsl_ie()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data ) {
			$this->send_error( __( 'Job not found', 'import-export-by-rockstarlab' ), null, 404 );
		}

		// Prepare settings - reset progress for media sync jobs
		$settings_to_use = $job_data->settings;
		if ( 'media_sync' === $job_data->data_type && ! empty( $job_data->settings ) ) {
			$settings = json_decode( $job_data->settings, true );
			if ( is_array( $settings ) ) {
				// Reset progress tracking fields but keep the original configuration
				unset( $settings['offset'] );
				unset( $settings['processed_count'] );
				unset( $settings['total_files'] );
				unset( $settings['all_files'] );
				// Reset offset to 0 for fresh start
				$settings['offset'] = 0;
				$settings_to_use    = wp_json_encode( $settings );
			}
		}

		// Get job parameters for response
		$parameters = maybe_unserialize( $job_data->parameters );

		// For media sync, parameters might be empty but settings should contain all info
		if ( empty( $parameters ) && 'media_sync' === $job_data->data_type ) {
			$parameters = json_decode( $job_data->settings, true );
		}

		if ( empty( $parameters ) && empty( $settings_to_use ) ) {
			$this->send_error( __( 'Job configuration not found', 'import-export-by-rockstarlab' ), null, 400 );
		}

		// Create new job with same settings
		$new_job_payload = [
			'user_id'     => $job_data->user_id,
			'type'        => $job_data->type,
			'data_type'   => $job_data->data_type,
			'file_format' => $job_data->file_format,
			'job_name'    => isset( $job_data->job_name ) ? $job_data->job_name : null,
			'parameters'  => $this->normalize_rerun_parameters( $job_data->type, $job_data->parameters ),
			'settings'    => $settings_to_use,
		];

		// Import/update jobs depend on the source file path for processing. When
		// restarting from Jobs Log, keep the same file reference when available.
		if ( 'import' === $job_data->type ) {
			$new_job_payload['file_path'] = $job_data->file_path ?? null;
			$new_job_payload['file_size'] = $job_data->file_size ?? null;
		}

		// Preserve total_items for content update jobs so the Jobs Log shows the expected
		// denominator immediately after rerun.
		if ( 'content_update' === (string) $job_data->data_type && ! empty( $job_data->total_items ) ) {
			$new_job_payload['total_items'] = (int) $job_data->total_items;
		}

		$new_job_id = $job_model->create( $new_job_payload );

		if ( is_wp_error( $new_job_id ) ) {
			$this->send_error( $new_job_id, null, 500 );
		}

		$this->send_success(
			[
				'job_id'     => $new_job_id,
				'type'       => $job_data->type,
				'data_type'  => $job_data->data_type,
				'parameters' => $parameters,
			],
			__( 'Job restarted successfully', 'import-export-by-rockstarlab' )
		);
	}

	/**
	 * Retry job (create new job and set to processing immediately)
	 */
	public function retry_job() {
		if ( $this->managed_request() ) {
			$permission = $this->verify_request();
			if ( is_wp_error( $permission ) ) {
				$this->send_error( $permission, null, 403 );
				return; }
			$service = new Job_Service();
			$id      = (int) $this->get_request_param( 'job_id' );
			$result  = $service->continue_job( $id, 'retry' );
			if ( is_wp_error( $result ) ) {
				$this->send_error( $result, null, $result->get_error_data()['status'] ?? 400 );
				return; }
			$job = $service->record( $result['job_id'] );
			if ( ! is_wp_error( $job ) ) {
				$result['type']      = $job->type;
				$result['data_type'] = $job->data_type; }
			$this->send_success( $result );
			return;
		}

		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$validation = $this->validate_required_params( [ 'job_id' ] );
		if ( is_wp_error( $validation ) ) {
			$this->send_error( $validation, null, 400 );
		}

		$job_id = (int) $this->get_request_param( 'job_id' );

		$job_model = rsl_ie()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data ) {
			$this->send_error( __( 'Job not found', 'import-export-by-rockstarlab' ), null, 404 );
		}

		// Prepare settings - reset progress for media sync jobs
		$settings_to_use = $job_data->settings;
		if ( 'media_sync' === $job_data->data_type && ! empty( $job_data->settings ) ) {
			$settings = json_decode( $job_data->settings, true );
			if ( is_array( $settings ) ) {
				// Reset progress tracking fields but keep the original configuration
				unset( $settings['offset'] );
				unset( $settings['processed_count'] );
				unset( $settings['total_files'] );
				unset( $settings['all_files'] );
				// Reset offset to 0 for fresh start
				$settings['offset'] = 0;
				$settings_to_use    = wp_json_encode( $settings );
			}
		}

		// Get job parameters for response
		$parameters = maybe_unserialize( $job_data->parameters );

		// For media sync, parameters might be empty but settings should contain all info
		if ( empty( $parameters ) && 'media_sync' === $job_data->data_type ) {
			$parameters = json_decode( $job_data->settings, true );
		}

		if ( empty( $parameters ) && empty( $settings_to_use ) ) {
			$this->send_error( __( 'Job configuration not found', 'import-export-by-rockstarlab' ), null, 400 );
		}

		// Create new job with same settings but set status to processing
		$new_job_payload = [
			'user_id'     => $job_data->user_id,
			'type'        => $job_data->type,
			'data_type'   => $job_data->data_type,
			'file_format' => $job_data->file_format,
			'job_name'    => isset( $job_data->job_name ) ? $job_data->job_name : null,
			'parameters'  => $this->normalize_rerun_parameters( $job_data->type, $job_data->parameters ),
			'settings'    => $settings_to_use,
			'status'      => 'processing', // Set to processing immediately
		];

		// Import/update jobs depend on the source file path for processing. When
		// retrying from Jobs Log, keep the same file reference when available.
		if ( 'import' === $job_data->type ) {
			$new_job_payload['file_path'] = $job_data->file_path ?? null;
			$new_job_payload['file_size'] = $job_data->file_size ?? null;
		}

		// Preserve total_items for content update jobs so the Jobs Log shows the expected
		// denominator immediately after rerun.
		if ( 'content_update' === (string) $job_data->data_type && ! empty( $job_data->total_items ) ) {
			$new_job_payload['total_items'] = (int) $job_data->total_items;
		}

		$new_job_id = $job_model->create( $new_job_payload );

		if ( is_wp_error( $new_job_id ) ) {
			$this->send_error( $new_job_id, null, 500 );
		}

		$this->send_success(
			[
				'job_id'     => $new_job_id,
				'type'       => $job_data->type,
				'data_type'  => $job_data->data_type,
				'parameters' => $parameters,
			],
			__( 'Job created and ready to process', 'import-export-by-rockstarlab' )
		);
	}

	/**
	 * Get download URL with nonce
	 */
	public function get_download_url() {
		$verification = $this->verify_request();
		if ( is_wp_error( $verification ) ) {
			$this->send_error( $verification, null, 403 );
		}

		$job_id = $this->get_request_param( 'job_id', 0 );

		if ( empty( $job_id ) ) {
			$this->send_error(
				new \WP_Error( 'missing_job_id', __( 'Job ID is required', 'import-export-by-rockstarlab' ) ),
				null,
				400
			);
		}

		// Verify job exists
		$job_model = rsl_ie()->Model->job;
		$job_data  = $job_model->find( $job_id );

		if ( ! $job_data ) {
			$this->send_error(
				new \WP_Error( 'job_not_found', __( 'Job not found', 'import-export-by-rockstarlab' ) ),
				null,
				404
			);
		}

		$file = ( new \RockStarLab\ImportExport\Service\File_Service() )->from_job( $job_id );
		if ( is_wp_error( $file ) ) {
			$this->send_error( $file, null, $file->get_error_data()['status'] ?? 400 );
			return;
		}

		// Generate nonce for this specific job.
		$nonce = wp_create_nonce( 'rsl_ie_download_' . $job_id );
		$url   = add_query_arg(
			[
				'action'   => 'rsl_ie_secure_download',
				'job_id'   => $job_id,
				'_wpnonce' => $nonce,
				'nonce'    => Ajax_Security::create_nonce( 'rsl_ie_secure_download' ),
			],
			admin_url( 'admin-ajax.php' )
		);

		$this->send_success(
			[
				'file_id' => $file['file_id'],
				'url'     => $url,
				'nonce'   => $nonce,
			],
			__( 'Download URL generated', 'import-export-by-rockstarlab' )
		);
	}
}

<?php
/**
 * Durable request identity, authorized job snapshots and lifecycle transitions.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Execution_Context;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Atomic transitions and deduplication must read/write the database directly.

/** Manages ordinary file imports/exports; domain runners join in later stages. */
class Job_Service {
	/**
	 * Decide whether the generic file workflow owns this existing job.
	 *
	 * @param object|null $job Persisted job.
	 * @return bool
	 */
	public static function supports( $job ) {
		return $job && empty( json_decode( $job->parameters, true )['migration_parent_id'] ) && in_array( $job->type, [ 'import', 'export' ], true ) && ! in_array( $job->data_type, [ 'media_sync', 'content_update', 'ai_url' ], true );
	}

	/**
	 * Authorize removal of versioned domain history without replaying its work.
	 *
	 * @param object $job Stored job.
	 * @return bool
	 */
	private static function domain_deletable( $job ) {
		$params = json_decode( (string) $job->parameters, true );
		$state  = json_decode( (string) $job->settings, true );
		if ( ! empty( $params['migration_parent_id'] ) || ! empty( $state['writing'] ) || ! empty( $state['uncertain'] ) || 'transferring' === ( $state['stage'] ?? '' ) ) {
			return false;
		}
		if ( AI_URL_Service::supports( $job ) ) {
			return ! is_wp_error( ( new AI_URL_Service() )->authorize( $params['input']['post_type'] ?? '', $params['edition'] ?? 'free' ) );
		}
		if ( Sync_Job_Service::supports( $job ) && 1 === ( $params['sync_queue_version'] ?? null ) ) {
			$provider = ( new Sync_Job_Service() )->provider( $params['edition'] ?? '' );
			return ! is_wp_error( $provider ) && isset( $provider['definitions'][ $params['operation'] ?? '' ] );
		}
		return true === apply_filters( 'rsl_ie_job_domain_deletable', false, $job );
	}

	/**
	 * Insert the job and its unique request identity in the same database row.
	 *
	 * @param array         $data Server-normalized job columns from Import/Export Service.
	 * @param string        $request_id Optional client identity; same user/site/operation.
	 * @param callable|null $before_create Optional domain preflight after deduplication, before insertion.
	 * @return array|\WP_Error
	 */
	public static function enqueue( array $data, $request_id = '', $before_create = null ) {
		$permission = Access_Policy::check_management();
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		if ( ! is_string( $request_id ) || ( '' !== $request_id && ! preg_match( '/\A[A-Za-z0-9][A-Za-z0-9._:-]{0,127}\z/', $request_id ) ) ) {
			return self::error( 'invalid_request_id', 400 );
		}
		$input = json_decode( $data['parameters'], true );
		unset( $input['execution_context'], $input['offset'] );
		if ( 'import' === $data['type'] ) {
			$input['file_path'] = $data['file_path'];
		}
		$data['request_payload'] = wp_json_encode( $input );
		$data['request_hash']    = hash( 'sha256', wp_json_encode( self::canonical( $input ) ) );
		$key                     = '' === $request_id ? null : hash( 'sha256', get_current_blog_id() . ':' . get_current_user_id() . ':' . $data['type'] . ':' . $request_id );
		$data['request_key']     = $key;
		$lock                    = null;
		if ( $key ) {
			$lock = Job_Lock::acquire_resource( 'request', $key );
			if ( is_wp_error( $lock ) ) {
				return $lock;
			}
			if ( false === $lock ) {
				return self::error( 'request_busy', 409 );
			}
		}
		try {
			global $wpdb;
			$existing = $key ? $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM %i WHERE request_key = %s', $wpdb->prefix . 'rsl_ie_jobs', $key ) ) : null;
			if ( $existing ) {
				if ( ! hash_equals( $existing->request_hash, $data['request_hash'] ) ) {
					return self::error( 'request_conflict', 409 );
				}
				// A replay cannot revive terminal jobs or replace their immutable input.
				if ( in_array( $existing->status, [ 'pending', 'processing' ], true ) ) {
					Job_Runner::schedule( $existing->id );
				}
				return [
					'job_id'             => (int) $existing->id,
					'status'             => $existing->status,
					'deduplicated'       => true,
					'poll_after_seconds' => 2,
				];
			}
			if ( $key && get_option( 'rsl_ie_job_request_' . $key ) ) {
				return self::error( 'request_job_deleted', 410 );
			}
			if ( $before_create ) {
				$valid = $before_create();
				if ( is_wp_error( $valid ) ) {
					return $valid;
				}
			}
			$id = rsl_ie()->Model->job->create( $data );
			if ( is_wp_error( $id ) ) {
				return $id;
			}
			Job_Log::write( $id, 'info', 'Job queued.' );
			$scheduled = Job_Runner::schedule( $id );
			$result    = [
				'job_id'             => (int) $id,
				'status'             => 'pending',
				'deduplicated'       => false,
				'poll_after_seconds' => 2,
			];
			if ( is_wp_error( $scheduled ) ) {
				$result['scheduling_warning'] = $scheduled->get_error_code();
			}
			return $result;
		} finally {
			if ( $lock ) {
				Job_Lock::release( $lock );
			}
		}
	}

	/**
	 * Sort object keys recursively while preserving meaningful array order.
	 *
	 * @param mixed $value Normalized input.
	 * @return mixed
	 */
	private static function canonical( $value ) {
		if ( ! is_array( $value ) ) {
			return $value;
		}
		if ( $value && array_keys( $value ) !== range( 0, count( $value ) - 1 ) ) {
			ksort( $value );
		}
		return array_map( [ __CLASS__, 'canonical' ], $value );
	}

	/**
	 * Authorize a stored job without altering it or impersonating its initiator.
	 *
	 * @param int  $id Job ID.
	 * @param bool $execute Require current content/provider permissions.
	 * @return object|\WP_Error
	 */
	public function record( $id, $execute = false ) {
		$permission = Access_Policy::check_management();
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		$id  = filter_var( $id, FILTER_VALIDATE_INT );
		$job = $id && $id > 0 ? rsl_ie()->Model->job->find( $id ) : null;
		if ( ! $job ) {
			return self::error( 'job_not_found', 404 );
		}
		$params  = json_decode( (string) $job->parameters, true );
		$context = $params['execution_context'] ?? [
			'user_id' => (int) $job->user_id,
			'blog_id' => get_current_blog_id(),
		];
		if ( ! is_array( $context ) || (int) ( $context['user_id'] ?? 0 ) !== (int) $job->user_id ) {
			return self::error( 'invalid_context', 403 );
		}
		$permission = Access_Policy::check_resource( $job->user_id, $context['blog_id'] ?? 0 );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		if ( $execute ) {
			if ( ! self::supports( $job ) ) {
				return self::error( 'job_runner_unavailable', 400 );
			}
			$permission = Access_Policy::check_content_type( $job->data_type, $job->type, [ 'options' => $params['options'] ?? [] ] );
			if ( is_wp_error( $permission ) ) {
				return $permission;
			}
		}
		return $job;
	}

	/**
	 * Safe public snapshot; configuration, prepared rows, paths and secrets stay internal.
	 *
	 * @param int $id Job ID.
	 * @return array|\WP_Error
	 */
	public function get( $id ) {
		$job = $this->record( $id );
		return is_wp_error( $job ) ? $job : $this->snapshot( $job );
	}

	/**
	 * Derive action availability from lifecycle state without mutating the job.
	 *
	 * @param object $job Stored job.
	 * @return array
	 */
	private function snapshot( $job ) {
		$actions = [ 'get', 'logs', 'rename' ];
		if ( self::supports( $job ) || Media_Folder_Service::supports( $job ) ) {
			if ( in_array( $job->status, [ 'pending', 'processing' ], true ) ) {
				$actions = array_merge( $actions, [ 'pause', 'cancel' ] );
			}
			if ( 'paused' === $job->status ) {
				$actions = array_merge( $actions, [ 'resume', 'cancel' ] );
			}
			if ( 'failed' === $job->status && ! Media_Folder_Service::supports( $job ) ) {
				$actions[] = 'retry';
			}
			if ( in_array( $job->status, [ 'completed', 'failed', 'cancelled' ], true ) ) {
				$actions = array_merge( $actions, [ 'restart', 'delete' ] );
			}
		}
		if ( self::domain_deletable( $job ) && in_array( $job->status, [ 'completed', 'failed', 'cancelled' ], true ) ) {
			$actions[] = 'delete';
			if ( ! Sync_Job_Service::supports( $job ) ) {
				$actions[] = 'restart';
			}
		}
		if ( Migration_History_Service::supports( $job ) && in_array( $job->status, [ 'completed', 'failed', 'cancelled' ], true ) ) {
			$actions[] = 'delete';
		}
		if ( Migration_Service::supports( $job ) ) {
			if ( in_array( $job->status, [ 'pending', 'processing' ], true ) ) {
				$actions = array_merge( $actions, [ 'pause', 'cancel' ] ); }
			if ( 'paused' === $job->status ) {
				$actions = array_merge( $actions, [ 'resume', 'cancel' ] ); }
		}
		if ( Sync_Job_Service::supports( $job ) ) {
			$state = json_decode( $job->settings, true );
			if ( in_array( $state['stage'] ?? '', [ 'queued', 'ready' ], true ) ) {
				if ( in_array( $job->status, [ 'pending', 'processing' ], true ) ) {
					$actions = array_merge( $actions, [ 'pause', 'cancel' ] ); }
				if ( 'paused' === $job->status ) {
					$actions = array_merge( $actions, [ 'resume', 'cancel' ] ); }
			}
		}
		// Earlier import checkpoints stored skips only in their cumulative result.
		$result = json_decode( (string) ( $job->result ?? '' ), true );
		$skipped = (int) ( $job->skipped_items ?? 0 );
		if ( 'import' === $job->type && self::supports( $job ) && is_array( $result ) && isset( $result['skipped'] ) ) {
			$skipped = (int) $result['skipped'];
		}
		$snapshot    = [
			'job_id'             => (int) $job->id,
			'operation'          => $job->type,
			'content_type'       => $job->data_type,
			'job_name'           => (string) $job->job_name,
			'status'             => $job->status,
			'processed'          => (int) $job->processed_items,
			'total'              => (int) $job->total_items,
			'success'            => (int) $job->success_items,
			'failed'             => (int) $job->failed_items,
			'skipped'            => $skipped,
			'progress'           => (float) $job->progress,
			'retries'            => (int) ( $job->retries ?? 0 ),
			'allowed_actions'    => apply_filters( 'rsl_ie_job_domain_actions', $actions, $job ),
			'poll_after_seconds' => in_array( $job->status, [ 'pending', 'processing' ], true ) ? 2 : 0,
		];
		$preparation = $this->preparation_progress( $job );
		if ( null !== $preparation ) {
			$snapshot['preparation'] = $preparation;
		}
		return $snapshot;
	}

	/**
	 * Publish only counters from server-owned import preparation checkpoints.
	 *
	 * @param object $job Stored job.
	 * @return array|null Safe progress, absent for jobs without preparation state.
	 */
	private function preparation_progress( $job ) {
		if ( 'import' !== $job->type ) {
			return null;
		}
		$params = json_decode( (string) $job->parameters, true );
		$format = $params['format'] ?? '';
		if ( ! in_array( $format, [ 'csv', 'json', 'xml', 'xlsx', 'ods' ], true ) ) {
			return null;
		}
		$index = $params['spreadsheet_index'] ?? $params['json_object_index'] ?? null;
		$state = $params[ $format . '_preparation' ] ?? null;
		$ready = isset( $params['prepared_rows_count'] ) || isset( $params['prepared_data'] );
		if ( null === $index && null === $state && ! $ready ) {
			return null;
		}
		$result = [
			'format'        => $format,
			'phase'         => $ready || ! empty( $state['done'] ) ? 'ready' : ( is_array( $index ) && isset( $index['done'] ) && ! $index['done'] ? 'indexing' : 'preparing' ),
			'prepared_rows' => max( 0, (int) ( $params['prepared_rows_count'] ?? $state['count'] ?? ( isset( $params['prepared_data'] ) && is_array( $params['prepared_data'] ) ? count( $params['prepared_data'] ) : 0 ) ) ),
		];
		if ( is_array( $index ) ) {
			$result['indexed_rows'] = max( 0, (int) ( $index['count'] ?? 0 ) );
		}
		return $result;
	}

	/**
	 * Read a bounded page of job summaries with accurate authorization filtering.
	 *
	 * @param array $input Filters and pagination.
	 * @return array|\WP_Error
	 */
	public function listing( array $input = [] ) {
		$permission = Access_Policy::check_management();
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		$limit  = max( 1, min( 100, (int) ( $input['limit'] ?? 20 ) ) );
		$offset = max( 0, (int) ( $input['offset'] ?? 0 ) );
		$where  = [];
		foreach ( [ 'status', 'type' ] as $key ) {
			if ( ! empty( $input[ $key ] ) && is_string( $input[ $key ] ) ) {
				$where[ $key ] = $input[ $key ];
			}
		}
		$jobs  = rsl_ie()->Model->job->get_all( $where, $limit, $offset, 'id DESC' );
		$items = [];
		foreach ( $jobs as $job ) {
			$item = $this->get( $job->id );
			if ( ! is_wp_error( $item ) ) {
				$items[] = $item;
			}
		}
		return [
			'jobs'   => $items,
			'limit'  => $limit,
			'offset' => $offset,
			'total'  => (int) rsl_ie()->Model->job->count( $where ),
		];
	}

	/**
	 * Stop future work immediately; conditional updates do not wait for a batch lock.
	 *
	 * @param int    $id Job ID.
	 * @param string $action pause or cancel.
	 * @return array|\WP_Error
	 */
	public function stop( $id, $action ) {
		$job = $this->record( $id );
		if ( is_wp_error( $job ) ) {
			return $job;
		}
		$domain = apply_filters( 'rsl_ie_job_domain_transition', null, $job, $action );
		if ( null !== $domain ) {
			return is_wp_error( $domain ) ? $domain : $this->get( $id ); }
		if ( Migration_Service::supports( $job ) ) {
			$result = ( new Migration_Service() )->transition( $id, $action );
			return is_wp_error( $result ) ? $result : $this->get( $id ); }
		if ( Sync_Job_Service::supports( $job ) ) {
			$result = ( new Sync_Job_Service() )->transition( $id, $action );
			return is_wp_error( $result ) ? $result : $this->get( $id );
		}
		if ( Media_Folder_Service::supports( $job ) ) {
			$result = ( new Media_Folder_Service() )->transition( $id, $action );
			return is_wp_error( $result ) ? $result : $this->get( $id );
		}
		if ( ! self::supports( $job ) || ! in_array( $action, [ 'pause', 'cancel' ], true ) ) {
			return self::error( 'job_action_unavailable', 400 );
		}
		$status = 'pause' === $action ? 'paused' : 'cancelled';
		if ( $status === $job->status ) {
			return $this->snapshot( $job );
		}
		$allowed = 'pause' === $action ? [ 'pending', 'processing' ] : [ 'pending', 'processing', 'paused' ];
		if ( ! in_array( $job->status, $allowed, true ) ) {
			return self::error( 'job_state_conflict', 409 );
		}
		global $wpdb;
		$saved = $wpdb->query( $wpdb->prepare( "UPDATE %i SET status = %s, updated_at = %s, completed_at = CASE WHEN %s = 'cancelled' THEN %s ELSE completed_at END WHERE id = %d AND status = %s", $wpdb->prefix . 'rsl_ie_jobs', $status, current_time( 'mysql' ), $status, current_time( 'mysql' ), $job->id, $job->status ) );
		if ( false === $saved ) {
			return self::error( 'job_write_failed', 500 );
		}
		$fresh = rsl_ie()->Model->job->find( $id );
		if ( ! $fresh || $status !== $fresh->status ) {
			return self::error( 'job_state_conflict', 409 );
		}
		Job_Log::write( $id, 'info', 'Job ' . $status . '.' );
		wp_clear_scheduled_hook( 'rsl_ie_run_job', [ (int) $id ] );
		return $this->snapshot( $fresh );
	}

	/**
	 * Resume paused work or retry a failed checkpoint without resetting counters.
	 *
	 * @param int    $id Job ID.
	 * @param string $action resume or retry.
	 * @return array|\WP_Error
	 */
	public function continue_job( $id, $action = 'resume' ) {
		$authorized = $this->record( $id );
		if ( is_wp_error( $authorized ) ) {
			return $authorized; }
		$domain = apply_filters( 'rsl_ie_job_domain_transition', null, $authorized, $action );
		if ( null !== $domain ) {
			return is_wp_error( $domain ) ? $domain : $this->get( $id ); }
		$folder = rsl_ie()->Model->job->find( (int) $id );
		if ( Migration_Service::supports( $folder ) ) {
			if ( 'resume' !== $action ) {
				return self::error( 'job_action_unavailable', 400 ); }
			$result = ( new Migration_Service() )->transition( $id, 'resume' );
			return is_wp_error( $result ) ? $result : $this->get( $id );
		}
		if ( Sync_Job_Service::supports( $folder ) ) {
			if ( 'resume' !== $action ) {
				return self::error( 'job_action_unavailable', 400 ); }
			$result = ( new Sync_Job_Service() )->transition( $id, 'resume' );
			return is_wp_error( $result ) ? $result : $this->get( $id );
		}
		if ( Media_Folder_Service::supports( $folder ) ) {
			if ( 'resume' !== $action ) {
				return self::error( 'job_action_unavailable', 400 ); }
			$result = ( new Media_Folder_Service() )->transition( $id, 'resume' );
			return is_wp_error( $result ) ? $result : $this->get( $id );
		}
		return $this->locked(
			$id,
			function () use ( $id, $action ) {
				$job = $this->record( $id, true );
				if ( is_wp_error( $job ) ) {
					return $job;
				}
				if ( ! in_array( $action, [ 'resume', 'retry' ], true ) ) {
					return self::error( 'job_action_unavailable', 400 );
				}
				if ( in_array( $job->status, [ 'pending', 'processing' ], true ) ) {
					Job_Runner::schedule( $id );
					return $this->snapshot( $job );
				}
				$expected = 'resume' === $action ? 'paused' : 'failed';
				if ( $expected !== $job->status ) {
					return self::error( 'job_state_conflict', 409 );
				}
				$params = json_decode( $job->parameters, true );
				if ( ! is_array( $params ) ) {
					return self::error( 'invalid_job_parameters', 400 );
				}
				$context = Execution_Context::from_storage( (int) $job->user_id, get_current_blog_id() );
				if ( is_wp_error( $context ) ) {
					return $context;
				}
				// Revalidate as the original initiator, but do not parse/transform any rows.
				$valid = $context->run(
					function () use ( $job, $params ) {
						return 'export' === $job->type ? Export_Request::normalize( $params ) : ( new Import_Service() )->check_request( array_merge( $params, [ 'file_path' => $job->file_path ] ) );
					}
				);
				if ( is_wp_error( $valid ) ) {
					return $valid;
				}
				global $wpdb;
				$saved = $wpdb->query( $wpdb->prepare( "UPDATE %i SET status = 'pending', retries = retries + %d, updated_at = %s, completed_at = NULL, request_payload = COALESCE(request_payload, %s) WHERE id = %d AND status = %s", $wpdb->prefix . 'rsl_ie_jobs', 'retry' === $action ? 1 : 0, current_time( 'mysql' ), wp_json_encode( $valid ), $id, $expected ) );
				if ( 1 !== $saved ) {
					return self::error( 'job_state_conflict', 409 );
				}
				Job_Log::write( $id, 'info', 'Job ' . $action . ' requested; checkpoint retained.' );
				Job_Runner::schedule( $id );
				return $this->get( $id );
			}
		);
	}

	/**
	 * Create a fresh job using immutable input and the requesting administrator.
	 *
	 * @param int    $id Source job.
	 * @param string $request_id Optional identity for the restart operation.
	 * @return array|\WP_Error
	 */
	public function restart( $id, $request_id = '' ) {
		if ( Media_Folder_Service::supports( rsl_ie()->Model->job->find( (int) $id ) ) ) {
			return ( new Media_Folder_Service() )->restart( $id, $request_id );
		}
		if ( ! is_string( $request_id ) || strlen( $request_id ) > 96 || ( '' !== $request_id && ! preg_match( '/\A[A-Za-z0-9][A-Za-z0-9._:-]*\z/', $request_id ) ) ) {
			return self::error( 'invalid_request_id', 400 );
		}
		return $this->locked(
			$id,
			function () use ( $id, $request_id ) {
				$source = $this->record( $id );
				if ( is_wp_error( $source ) ) {
					return $source;
				}
				if ( ! Sync_Job_Service::supports( $source ) && self::domain_deletable( $source ) ) {
					if ( ! in_array( $source->status, [ 'completed', 'failed', 'cancelled' ], true ) ) {
						return self::error( 'job_state_conflict', 409 );
					}
					$params = json_decode( (string) $source->parameters, true );
					$input  = $params['input'] ?? null;
					if ( ! is_array( $input ) ) {
						return self::error( 'invalid_job', 400 );
					}
					$input['request_id'] = 'restart:' . $id . ':' . ( '' === $request_id ? wp_generate_uuid4() : $request_id );
					$result              = AI_URL_Service::supports( $source )
						? ( new AI_URL_Service() )->execute( 'ai-url-import-start', $input, $params['edition'] ?? 'free' )
						: apply_filters( 'rsl_ie_job_domain_restart', self::error( 'job_runner_unavailable', 400 ), $source, $input );
					if ( ! is_wp_error( $result ) ) {
						rsl_ie()->Model->job->update( $result['job_id'], [ 'job_name' => $source->job_name ] );
					}
					return $result;
				}
				$job = $this->record( $id, true );
				if ( is_wp_error( $job ) ) {
					return $job;
				}
				if ( ! in_array( $job->status, [ 'completed', 'failed', 'cancelled' ], true ) ) {
					return self::error( 'job_state_conflict', 409 );
				}
				$input = json_decode( (string) ( $job->request_payload ?? '' ), true );
				if ( ! is_array( $input ) ) {
					$input = json_decode( (string) $job->parameters, true );
					if ( ! is_array( $input ) ) {
						return self::error( 'invalid_job', 400 );
					}
					if ( 'import' === $job->type ) {
						$input['file_path'] = $job->file_path;
					}
				}
				$input['request_id'] = '' === $request_id ? '' : 'restart:' . $id . ':' . $request_id;
				$result              = 'import' === $job->type ? ( new Import_Service() )->start( $input ) : ( new Export_Service() )->start( $input );
				if ( is_wp_error( $result ) ) {
					return $result;
				}
				rsl_ie()->Model->job->update( $result['job_id'], [ 'job_name' => $job->job_name ] );
				return $result;
			}
		);
	}

	/**
	 * Rename without modifying processing parameters.
	 *
	 * @param int    $id Job ID.
	 * @param string $name Display name.
	 * @return array|\WP_Error
	 */
	public function rename( $id, $name ) {
		$job = $this->record( $id );
		if ( is_wp_error( $job ) ) {
			return $job;
		}
		if ( ! is_string( $name ) ) {
			return self::error( 'invalid_job_name', 400 );
		}
		$name = sanitize_text_field( $name );
		if ( strlen( $name ) > 255 ) {
			return self::error( 'invalid_job_name', 400 );
		}
		$saved = rsl_ie()->Model->job->update( $id, [ 'job_name' => $name ] );
		return is_wp_error( $saved ) ? $saved : [
			'job_id'   => (int) $id,
			'job_name' => $name,
		];
	}

	/**
	 * Read bounded log pages after site/resource authorization.
	 *
	 * @param int    $id Job ID.
	 * @param string $level Optional severity.
	 * @param int    $limit Page size.
	 * @param int    $offset Page offset.
	 * @return array|\WP_Error
	 */
	public function logs( $id, $level = 'all', $limit = 100, $offset = 0 ) {
		$job = $this->record( $id );
		if ( is_wp_error( $job ) ) {
			return $job;
		}
		if ( ! in_array( $level, [ 'all', 'debug', 'info', 'warning', 'error' ], true ) ) {
			return self::error( 'invalid_log_level', 400 );
		}
		$limit  = max( 1, min( 100, (int) $limit ) );
		$offset = max( 0, (int) $offset );
		global $wpdb;
		$table = $wpdb->prefix . 'rsl_ie_job_logs';
		$where = $wpdb->prepare( 'job_id = %d', $id );
		if ( 'all' !== $level ) {
			$where .= $wpdb->prepare( ' AND level = %s', $level );
		}
		$logs  = $wpdb->get_results( $wpdb->prepare( "SELECT id, level, message, created_at FROM %i WHERE $where ORDER BY id ASC LIMIT %d OFFSET %d", $table, $limit, $offset ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Where fragments use prepare above.
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM %i WHERE $where", $table ) ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Where fragments use prepare above.
		return [
			'logs'   => $logs,
			'total'  => $total,
			'limit'  => $limit,
			'offset' => $offset,
		];
	}

	/**
	 * Delete terminal jobs only, under the worker lock. Shared import files survive.
	 *
	 * @param int $id Job ID.
	 * @return array|\WP_Error
	 */
	public function delete( $id ) {
		return $this->locked(
			$id,
			function () use ( $id ) {
				$job = $this->record( $id );
				if ( is_wp_error( $job ) ) {
					return $job;
				}
				if ( Migration_History_Service::supports( $job ) ) {
					return ( new Migration_History_Service() )->delete( $job );
				}
				if ( ( ! self::supports( $job ) && ! Media_Folder_Service::supports( $job ) && ! self::domain_deletable( $job ) ) || ! in_array( $job->status, [ 'completed', 'failed', 'cancelled' ], true ) ) {
					return self::error( 'job_state_conflict', 409 );
				}
				$schedule_model = rsl_ie()->Model->job_schedule;
				if ( $schedule_model && $schedule_model->find_by(
					[
						'source_job_id' => $id,
						'status'        => 'active',
					]
				) ) {
					return self::error( 'job_has_active_schedule', 409 );
				}
				// Keep request identity as a tombstone, so replay cannot recreate deleted work.
				if ( ! empty( $job->request_key ) ) {
					$saved = add_option(
						'rsl_ie_job_request_' . $job->request_key,
						[
							'job_id' => (int) $id,
							'hash'   => $job->request_hash,
						],
						'',
						false
					);
					if ( ! $saved && ! get_option( 'rsl_ie_job_request_' . $job->request_key ) ) {
						return self::error( 'job_write_failed', 500 );
					}
				}
				global $wpdb;
				$result = $wpdb->delete( $wpdb->prefix . 'rsl_ie_jobs', [ 'id' => (int) $id ], [ '%d' ] );
				if ( 1 !== $result ) {
					return self::error( 'job_write_failed', 500 );
				}
				$wpdb->delete( $wpdb->prefix . 'rsl_ie_job_logs', [ 'job_id' => (int) $id ], [ '%d' ] );
				wp_clear_scheduled_hook( 'rsl_ie_run_job', [ (int) $id ] );
				if ( 'export' === $job->type ) {
					File_Store::remove( 'file_' . md5( 'export:' . get_current_blog_id() . ':' . $id ) );
				}
				return [
					'job_id'  => (int) $id,
					'deleted' => true,
				];
			}
		);
	}

	/**
	 * Return legacy progress fields without touching status, clocks or counters.
	 *
	 * @param int    $id Job ID.
	 * @param string $operation Expected import/export operation.
	 * @return array|\WP_Error
	 */
	public function progress( $id, $operation ) {
		$job = $this->record( $id );
		if ( is_wp_error( $job ) ) {
			return $job;
		}
		if ( ! self::supports( $job ) || $operation !== $job->type ) {
			return self::error( 'job_not_found', 404 );
		}
		$result = json_decode( (string) $job->result, true );
		return $this->snapshot( $job ) + [
			'percentage' => (float) $job->progress,
			'result'     => is_array( $result ) ? $result : [],
			'file_path'  => $job->file_path,
			'file_size'  => (int) $job->file_size,
			'estimates'  => $this->estimates( $job ),
		];
	}

	/**
	 * Derive timing from stored site-local dates without updating the checkpoint.
	 *
	 * @param object $job Stored job.
	 * @return array
	 */
	private function estimates( $job ) {
		$estimates = [
			'elapsed_formatted'   => '-',
			'remaining_formatted' => '-',
			'items_per_second'    => 0,
		];
		$started   = ! empty( $job->started_at ) ? $job->started_at : $job->created_at;
		if ( empty( $started ) ) {
			return $estimates;
		}
		try {
			$start = new \DateTimeImmutable( $started, wp_timezone() );
			$end   = ! empty( $job->completed_at ) ? new \DateTimeImmutable( $job->completed_at, wp_timezone() ) : current_datetime();
		} catch ( \Exception $error ) {
			return $estimates;
		}
		$elapsed                        = max( 0, $end->getTimestamp() - $start->getTimestamp() );
		$estimates['elapsed_seconds']   = $elapsed;
		$estimates['elapsed_formatted'] = $this->format_duration( $elapsed );
		$processed                      = (int) $job->processed_items;
		$total                          = (int) $job->total_items;
		if ( $elapsed > 0 && $processed > 0 ) {
			$remaining                         = (int) ceil( max( 0, $total - $processed ) * $elapsed / $processed );
			$estimates['items_per_second']     = round( $processed / $elapsed, 2 );
			$estimates['remaining_seconds']    = $remaining;
			$estimates['remaining_formatted']  = $this->format_duration( $remaining );
			$estimates['estimated_completion'] = gmdate( 'Y-m-d H:i:s', $end->getTimestamp() + $remaining );
		}
		return $estimates;
	}

	/**
	 * Keep the duration labels used by the existing progress UI.
	 *
	 * @param int $seconds Duration.
	 * @return string
	 */
	private function format_duration( $seconds ) {
		if ( $seconds < 60 ) {
			return $seconds . 's';
		}
		if ( $seconds < 3600 ) {
			return sprintf( '%dm %ds', (int) floor( $seconds / 60 ), $seconds % 60 );
		}
		return sprintf( '%dh %dm', (int) floor( $seconds / 3600 ), (int) floor( ( $seconds % 3600 ) / 60 ) );
	}

	/**
	 * Read result counters and a bounded page of errors, without prepared input.
	 *
	 * @param int $id Job ID.
	 * @param int $limit Maximum error items.
	 * @param int $offset Error offset.
	 * @return array|\WP_Error
	 */
	public function result( $id, $limit = 100, $offset = 0 ) {
		$job = $this->record( $id );
		if ( is_wp_error( $job ) ) {
			return $job;
		}
		if ( Media_Folder_Service::supports( $job ) ) {
			$view = ( new Media_Folder_Service() )->status( $id );
			if ( is_wp_error( $view ) ) {
				return $view;
			}
			$data = $view['result'];
		} else {
			$job = $this->record( $id, true );
			if ( is_wp_error( $job ) ) {
				return $job;
			}
			$data = json_decode( (string) $job->result, true );
		}
		$data   = is_array( $data ) ? $data : [];
		$errors = isset( $data['errors'] ) && is_array( $data['errors'] ) ? $data['errors'] : [];
		$limit  = max( 1, min( 100, (int) $limit ) );
		$offset = max( 0, (int) $offset );
		return $this->snapshot( $job ) + [
			'error'          => $data['error'] ?? null,
			'errors'         => array_slice( $errors, $offset, $limit ),
			'errors_total'   => count( $errors ),
			'limit'          => $limit,
			'offset'         => $offset,
			'file_available' => 'completed' === $job->status && 'export' === $job->type && ! empty( $job->file_path ) && is_file( $job->file_path ),
		];
	}

	/**
	 * Remove a bounded page of old terminal file jobs through the same lifecycle.
	 *
	 * @param int $days Minimum age in days.
	 * @return array|\WP_Error
	 */
	public function cleanup( $days = 30 ) {
		$permission = Access_Policy::check_management();
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		$days = filter_var( $days, FILTER_VALIDATE_INT );
		if ( false === $days || $days < 1 ) {
			return self::error( 'invalid_retention', 400 );
		}
		global $wpdb;
		$jobs    = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM %i WHERE status IN ('completed','failed','cancelled') AND type IN ('import','export','sync_background','migration_export','migration_import','migration_sync_push','migration_sync_pull') AND data_type NOT IN ('media_sync') AND created_at < %s ORDER BY id ASC LIMIT 100", $wpdb->prefix . 'rsl_ie_jobs', wp_date( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS ) ) );
		$deleted = 0;
		$skipped = [];
		foreach ( $jobs as $job ) {
			$result = $this->delete( $job->id );
			if ( is_wp_error( $result ) ) {
				$skipped[] = [
					'job_id' => (int) $job->id,
					'reason' => $result->get_error_code(),
				];
			} else {
				++$deleted;
			}
		}
		return [
			'deleted'     => $deleted,
			'skipped'     => $skipped,
			'batch_limit' => 100,
		];
	}

	/**
	 * Serialize lifecycle changes that could race a batch or another restart.
	 *
	 * @param int      $id Job ID.
	 * @param callable $callback Critical section.
	 * @return mixed
	 */
	private function locked( $id, callable $callback ) {
		$permission = Access_Policy::check_management();
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		if ( false === filter_var( $id, FILTER_VALIDATE_INT ) || (int) $id < 1 ) {
			return self::error( 'job_not_found', 404 );
		}
		$lock = Job_Lock::acquire( (int) $id );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		if ( false === $lock ) {
			return self::error( 'job_busy', 409 );
		}
		try {
			return $callback();
		} finally {
			Job_Lock::release( $lock );
		}
	}

	/**
	 * Stable failures for transports.
	 *
	 * @param string $code Failure reason.
	 * @param int    $status HTTP status.
	 * @return \WP_Error
	 */
	private static function error( $code, $status ) {
		return new \WP_Error( 'rsl_ie_' . $code, sprintf( /* translators: %s: Job failure code. */ __( 'Job operation failed: %s.', 'import-export-by-rockstarlab' ), $code ), [ 'status' => $status ] );
	}
}

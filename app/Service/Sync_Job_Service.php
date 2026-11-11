<?php
/**
 * Durable orchestration of existing content-sync selections.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Execution_Context;
use RockStarLab\ImportExport\Abilities\Sync_Catalog;
use RockStarLab\ImportExport\Abilities\Media_Library_Catalog;
use RockStarLab\ImportExport\Abilities\Sync_Job_Catalog;
use RockStarLab\ImportExport\Abilities\Sync_Policy;
use RockStarLab\ImportExport\Model\Connected_Site;

defined( 'ABSPATH' ) || exit;

/** Never replay a transfer whose outcome is not durably confirmed. */
class Sync_Job_Service {
	/**
	 * Identify only this runner's queue records, never legacy history jobs.
	 *
	 * @param object|null $job Persisted record.
	 * @param bool        $include_children Internal worker lookup.
	 * @return bool
	 */
	public static function supports( $job, $include_children = false ) {
		return $job && 'sync_background' === $job->type && ( $include_children || empty( json_decode( $job->parameters, true )['migration_parent_id'] ) );
	}

	/**
	 * Resolve executable code from the installed provider, never from job data.
	 *
	 * @param string $edition Stored provider name.
	 * @return array|\WP_Error
	 */
	public function provider( $edition ) {
		if ( 'free' === $edition ) {
			return [
				'definitions' => Sync_Catalog::definitions() + Media_Library_Catalog::definitions(),
				'policy'      => [ Sync_Policy::class, 'input' ],
				'execute'     => static function ( $operation, $input ) {
					return isset( Media_Library_Catalog::definitions()[ $operation ] )
						? ( new Media_Library_Sync_Service() )->execute( substr( $operation, 11 ), $input )
						: ( new Sync_Workflow_Service() )->execute( $operation, $input );
				},
			];
		}
		$provider = apply_filters( 'rsl_ie_sync_job_provider', null, $edition );
		return is_array( $provider ) || is_wp_error( $provider ) ? $provider : $this->error( 'provider_unavailable', 403 );
	}

	/**
	 * Validate start/controls, including direct PHP calls.
	 *
	 * @param string $name Ability suffix.
	 * @param array  $input Caller input.
	 * @param string $edition Server-selected namespace provider.
	 * @param int    $migration_parent_id Internal active parent.
	 * @return array|\WP_Error
	 */
	public function execute( $name, array $input, $edition = 'free', $migration_parent_id = 0 ) {
		if ( $migration_parent_id && ! Migration_Service::running( $migration_parent_id ) ) {
			return $this->error( 'migration_parent_required', 409 );
		}
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		$controls = Sync_Job_Catalog::controls();
		if ( isset( $controls[ $name ] ) ) {
			$definition = $controls[ $name ];
		} else {
			$provider = $this->provider( $edition );
			if ( is_wp_error( $provider ) ) {
				return $provider;
			}
			$definition = Sync_Job_Catalog::starts( $provider['definitions'] )[ $name ] ?? null;
		}
		if ( ! $definition ) {
			return $this->error( 'unknown_operation', 400 );
		}
			$valid = rest_validate_value_from_schema( $input, $definition['input'], 'input' );
		if ( is_wp_error( $valid ) ) {
			$valid->add_data( [ 'status' => 400 ] );
			return $valid;
		}
			$input = rest_sanitize_value_from_schema( $input, $definition['input'] );
		if ( isset( $controls[ $name ] ) ) {
			return 'sync-job-status' === $name ? $this->status( $input['job_id'] ) : $this->transition( $input['job_id'], substr( $name, 9 ) );
		}
			return $this->start( substr( $name, 0, -6 ), $input, $edition, $migration_parent_id );
	}

	/**
	 * Queue immutable input and connection fingerprint, without persisting a credential.
	 *
	 * @param string $operation Existing sync ability suffix.
	 * @param array  $input Validated start input.
	 * @param string $edition Server-selected provider.
	 * @param int    $migration_parent_id Internal active parent.
	 * @return array|\WP_Error
	 */
	private function start( $operation, array $input, $edition, $migration_parent_id = 0 ) {
		$provider = $this->provider( $edition );
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}
		$queue     = $provider['definitions'][ $operation ]['background'] ?? [];
		$selection = isset( $queue['selection'] ) ? $input[ $queue['selection'] ] : ( $input['post_ids'] ?? $input['comment_ids'] ?? $input['term_ids'] );
		$request   = $input['request_id'];
		unset( $input['request_id'] );
		$context = Execution_Context::capture();
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		$lock = Job_Lock::acquire_resource( 'connection', $input['site_id'] );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return $this->error( 'connection_busy', 409 );
		}
		try {
			$site          = Connected_Site::get_by_id( $input['site_id'] );
			$params        = [
				'sync_queue_version'     => 1,
				'edition'                => $edition,
				'operation'              => $operation,
				'input'                  => $input,
				'connection_fingerprint' => $site ? $this->fingerprint( $site ) : '',
				'execution_context'      => $context->to_storage(),
			];
			$parent_job    = $migration_parent_id ? rsl_ie()->Model->job->find( $migration_parent_id ) : null;
			$parent_params = $parent_job ? json_decode( $parent_job->parameters, true ) : [];
			if ( $migration_parent_id && ! $parent_job ) {
				return $this->error( 'migration_parent_required', 409 ); }
			if ( in_array( $operation, [ 'sync-media-push', 'sync-media-pull' ], true ) && ( ! $migration_parent_id || 1 === ( $parent_params['media_chunk_version'] ?? null ) ) ) {
				$params['media_chunk_version'] = 1; }
			if ( $migration_parent_id ) {
				$params['migration_parent_id'] = (int) $migration_parent_id;
			}
			return Job_Service::enqueue(
				[
					'type'        => 'sync_background',
					'data_type'   => $queue['content_type'] ?? $input['post_type'] ?? $input['taxonomy'],
					'file_format' => 'json',
					'file_path'   => '',
					'status'      => 'pending',
					'user_id'     => get_current_user_id(),
					'total_items' => count( $selection ),
					'parameters'  => wp_json_encode( $params ),
					'settings'    => wp_json_encode(
						[
							'stage'          => 'queued',
							'history_job_id' => 0,
						]
					),
				],
				hash( 'sha256', $edition . ':' . $operation . ':' . $request ),
				function () use ( $params ) {
					return $this->preflight( $params );
				}
			);
		} finally {
			Job_Lock::release( $lock );
		}
	}

	/**
	 * Recheck local selection, current provider/license and saved connection identity.
	 *
	 * @param array $params Trusted immutable queue parameters.
	 * @param array $source_post_ids Internal deferred comment destinations; never used for execution.
	 * @return true|\WP_Error
	 */
	public function preflight( array $params, array $source_post_ids = [], array $confirmed_media_ids = [] ) {
		if ( 1 !== ( $params['sync_queue_version'] ?? 0 ) || ! is_array( $params['input'] ?? null ) ) {
			return $this->error( 'invalid_job', 400 );
		}
		$provider = $this->provider( $params['edition'] );
		if ( is_wp_error( $provider ) ) {
			return $provider;
		}
		$definition = $provider['definitions'][ $params['operation'] ] ?? null;
		if ( ! $definition || ! isset( Sync_Job_Catalog::starts( $provider['definitions'] )[ $params['operation'] . '-start' ] ) ) {
			return $this->error( 'invalid_job', 400 );
		}
		$schema = $definition['input'];
		if ( $source_post_ids ) {
			if ( 'standalone' !== ( $definition['handler'] ?? '' ) || ! in_array( $definition['action'], [ 'push_comments', 'pull_comments' ], true ) || isset( $params['input']['post_mapping'] ) || isset( $params['input']['target_post_id'] ) ) {
				return $this->error( 'invalid_mapping', 400 );
			}
			unset( $schema['anyOf'] );
			if ( 'push_comments' === $definition['action'] ) {
				$parents = [];
				foreach ( $params['input']['comment_ids'] ?? [] as $id ) {
					$comment   = get_comment( $id );
					$parents[] = $comment ? (int) $comment->comment_post_ID : 0;
				}
				if ( array_diff( $parents, $source_post_ids ) || array_diff( $source_post_ids, $parents ) ) {
					return $this->error( 'invalid_mapping', 403 );
				}
			}
		}
		$valid = rest_validate_value_from_schema( $params['input'], $schema, 'input' );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		foreach ( array_keys( $params['input']['post_mapping'] ?? $params['input']['term_mapping'] ?? [] ) as $id ) {
			if ( ! preg_match( '/^[1-9][0-9]*$/D', (string) $id ) ) {
				return $this->error( 'invalid_mapping', 400 );
			}
		}
		if ( 'media-library' === ( $definition['handler'] ?? '' ) ) {
			$permission = ( new Media_Library_Sync_Service() )->preflight( $definition['action'], $params['input'], $this->chunked_media( $params ), $confirmed_media_ids );
		} else {
			$permission = 'standalone' === ( $definition['handler'] ?? '' )
			? Standalone_Sync_Workflow::check_input( $definition, $params['input'], ! empty( $source_post_ids ) )
			: call_user_func( $provider['policy'], $definition['action'], $params['input'] );
		}
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		$site      = Connected_Site::get_by_id( $params['input']['site_id'] );
		$parts     = explode( '-', $params['operation'] );
		$direction = end( $parts );
		if ( ! $site || 'active' !== $site['status'] || ! in_array( $site['direction'], [ $direction, 'bidirectional' ], true ) || ! hash_equals( $params['connection_fingerprint'], $this->fingerprint( $site ) ) ) {
			return $this->error( 'connection_changed', 409 );
		}
		return true;
	}

	/**
	 * Credential changes invalidate queued work without storing the credential itself.
	 *
	 * @param array $site Stored connection.
	 * @return string
	 */
	public function fingerprint( array $site ) {
		return hash( 'sha256', $site['id'] . ':' . $site['remote_url'] . ':' . $site['api_key'] );
	}

	/**
	 * Authorize the queue record and reject malformed execution identity.
	 *
	 * @param int $id Job ID.
	 * @return object|\WP_Error
	 */
	private function record( $id, $migration_parent_id = 0 ) {
		$job = ( new Job_Service() )->record( $id );
		if ( is_wp_error( $job ) ) {
			return $job;
		}
		$params = json_decode( $job->parameters, true );
		return self::supports( $job, $migration_parent_id > 0 ) && (int) ( $params['migration_parent_id'] ?? 0 ) === $migration_parent_id && 1 === ( $params['sync_queue_version'] ?? 0 ) ? $job : $this->error( 'wrong_job', 400 );
	}

	/**
	 * Read without running work or exposing parameters and credentials.
	 *
	 * @param int $id Queue ID.
	 * @return array|\WP_Error
	 */
	public function status( $id ) {
		$job = $this->record( $id );
		return is_wp_error( $job ) ? $job : $this->snapshot( $job );
	}

	/**
	 * Safe persisted snapshot; provider absence hides its result but permits stopping work.
	 *
	 * @param object $job Queue record.
	 * @return array
	 */
	private function snapshot( $job ) {
		$params = json_decode( $job->parameters, true );
		$state  = json_decode( $job->settings, true );
		$result = json_decode( (string) $job->result, true );
		return [
			'job_id'             => (int) $job->id,
			'status'             => $job->status,
			'stage'              => $state['stage'] ?? 'queued',
			'edition'            => $params['edition'],
			'operation'          => $params['operation'],
			'processed'          => (int) $job->processed_items,
			'total'              => (int) $job->total_items,
			'history_job_id'     => (int) ( $state['history_job_id'] ?? 0 ),
			'error_code'         => $state['error_code'] ?? '',
			'uncertain'          => ! empty( $state['uncertain'] ),
			'result'             => is_wp_error( $this->provider( $params['edition'] ) ) ? null : ( $result ? $result : null ),
			'poll_after_seconds' => 2,
		];
	}

	/**
	 * Pause/resume/cancel before the indivisible transfer begins.
	 *
	 * @param int    $id Queue ID.
	 * @param string $action Lifecycle action.
	 * @return array|\WP_Error
	 */
	public function transition( $id, $action, $migration_parent_id = 0 ) {
		$job = $this->record( $id, $migration_parent_id );
		if ( is_wp_error( $job ) ) {
			return $job;
		}
		$lock = Job_Lock::acquire( $id );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return $this->error( 'busy', 409 );
		}
		try {
			$job = $this->record( $id, $migration_parent_id );
			if ( is_wp_error( $job ) ) {
				return $job;
			}
			$allowed = [
				'pause'  => [ 'pending', 'processing', 'paused' ],
				'resume' => [ 'pending', 'processing', 'paused' ],
				'cancel' => [ 'pending', 'processing', 'paused', 'cancelled' ],
			];
			$params  = json_decode( $job->parameters, true );
			$handled = apply_filters( 'rsl_ie_sync_queue_transition', null, $job, $action );
			if ( null !== $handled ) {
				return $handled; }
			if ( $this->chunked_media( $params ) ) {
				$allowed['cancel'][] = 'failed'; }
			if ( ! isset( $allowed[ $action ] ) || ! in_array( $job->status, $allowed[ $action ], true ) ) {
				return $this->error( 'state_conflict', 409 );
			}
			$state = json_decode( $job->settings, true );
			if ( 'transferring' === ( $state['stage'] ?? '' ) ) {
				return $this->error( 'uncertain', 409 );
			}
			if ( 'resume' === $action ) {
				$result = $this->in_context(
					$job,
					function ( $params ) use ( $state ) {
						return $this->preflight( $params, [], $this->confirmed_media_ids( $params, $state ) );
					}
				);
				if ( is_wp_error( $result ) ) {
					return $result;
				}
			}
			$params = json_decode( $job->parameters, true );
			if ( 'cancel' === $action && 'finalize' === ( $state['media']['phase'] ?? '' ) ) {
				return $this->error( 'uncertain', 409 ); }
			if ( 'cancel' === $action && $this->chunked_media( $params ) && ! empty( $state['media'] ) ) {
				$state['stage'] = 'media_cancel';
				$saved          = $this->save( $id, $state, [ 'status' => 'processing' ] );
				if ( is_wp_error( $saved ) ) {
					return $saved; }
				Job_Runner::schedule( $id );
				return $this->snapshot( rsl_ie()->Model->job->find( $id ) );
			}
			$status = [
				'pause'  => 'paused',
				'resume' => 'pending',
				'cancel' => 'cancelled',
			][ $action ];
			$saved  = rsl_ie()->Model->job->update(
				$id,
				[
					'status'       => $status,
					'completed_at' => 'cancelled' === $status ? current_time( 'mysql' ) : null,
					'updated_at'   => current_time( 'mysql' ),
				]
			);
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
			wp_clear_scheduled_hook( 'rsl_ie_run_job', [ (int) $id ] );
			if ( 'resume' === $action ) {
				Job_Runner::schedule( $id );
			}
			return $this->snapshot( rsl_ie()->Model->job->find( $id ) );
		} finally {
			Job_Lock::release( $lock );
		}
	}

	/**
	 * Restore the queued user, validating user/site against the actual row.
	 *
	 * @param object   $job Trusted queue record.
	 * @param callable $callback Worker operation.
	 * @return mixed
	 */
	private function in_context( $job, callable $callback ) {
		$params   = json_decode( $job->parameters, true );
		$identity = $params['execution_context'] ?? [];
		if ( ( $identity['user_id'] ?? 0 ) !== (int) $job->user_id || ( $identity['blog_id'] ?? 0 ) !== get_current_blog_id() ) {
			return $this->error( 'invalid_context', 403 );
		}
		$context = Execution_Context::from_storage( $identity['user_id'], $identity['blog_id'] );
		return is_wp_error( $context ) ? $context : $context->run(
			function () use ( $callback, $params ) {
				return $callback( $params );
			}
		);
	}

	/**
	 * Execute a single persisted phase; interrupted transfer phases never replay.
	 *
	 * @param int $id Trusted scheduled queue ID.
	 * @return array|\WP_Error
	 */
	public function process_background_batch( $id ) {
		$model = rsl_ie()->Model->job;
		$job   = $model->find( (int) $id );
		if ( ! self::supports( $job, true ) ) {
			return $this->error( 'wrong_job', 400 );
		}
		$parent = json_decode( $job->parameters, true )['migration_parent_id'] ?? 0;
		if ( $parent && ! Migration_Service::running( $parent ) ) {
			return $this->error( 'migration_parent_required', 409 );
		}
		$lock = Job_Lock::acquire( $id );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return $this->error( 'busy', 409 );
		}
		try {
			$job = $model->find( $id );
			if ( ! $job || ! in_array( $job->status, [ 'pending', 'processing' ], true ) ) {
				return [];
			}
			$state = json_decode( $job->settings, true );
			if ( 'transferring' === ( $state['stage'] ?? '' ) ) {
				return $this->fail( $id, 'interrupted_transfer', true );
			}
			$result = $this->in_context(
				$job,
				function ( $params ) use ( $job, $state ) {
					$handled = apply_filters( 'rsl_ie_sync_queue_step', null, $job, $params );
					if ( null !== $handled ) {
						return $handled; }
					if ( $this->chunked_media( $params ) && 'media_cancel' === ( $state['stage'] ?? '' ) ) {
						return $this->media_cancel( $job, $params, $state ); }
					$valid = $this->preflight( $params, [], $this->confirmed_media_ids( $params, $state ) );
					if ( is_wp_error( $valid ) ) {
						return $valid;
					}
					if ( $this->chunked_media( $params ) ) {
						return $this->media_step( $job, $params, $state ); }
					if ( 'queued' === ( $state['stage'] ?? '' ) ) {
						return $this->save(
							$job->id,
							[
								'stage'          => 'ready',
								'history_job_id' => 0,
							],
							[ 'status' => 'processing' ]
						);
					}
					if ( 'ready' !== ( $state['stage'] ?? '' ) ) {
						return $this->error( 'invalid_checkpoint', 400 );
					}
					return $this->transfer( $job, $params );
				}
			);
			if ( is_wp_error( $result ) ) {
				$fresh = $model->find( $id );
				$state = json_decode( $fresh->settings, true );
				if ( $this->chunked_media( json_decode( $fresh->parameters, true ) ) && in_array( $result->get_error_code(), [ 'rsl_ie_media_library_peer_unavailable', 'rsl_ie_media_library_peer_rejected', 'rsl_ie_media_library_connection_busy' ], true ) ) {
					$state['error_code']  = $result->get_error_code();
					$state['retry_count'] = ( $state['retry_count'] ?? 0 ) + 1;
					$state['uncertain']   = $this->uncertain_state( $state );
					return $this->save( $id, $state, [ 'status' => $state['retry_count'] >= 3 ? 'paused' : 'processing' ] );
				}
				if ( $this->chunked_media( json_decode( $fresh->parameters, true ) ) && 'finalize' === ( $state['media']['phase'] ?? '' ) ) {
					$state['error_code'] = sanitize_key( $result->get_error_code() );
					$state['uncertain']  = true;
					return $this->save( $id, $state, [ 'status' => 'paused' ] );
				}
				return $this->fail( $id, $result->get_error_code(), $this->uncertain_state( $state ) );
			}
			return $result;
		} catch ( \Throwable $exception ) {
			$fresh     = $model->find( $id );
			$state     = json_decode( $fresh->settings, true );
			$uncertain = $this->uncertain_state( $state );
			if ( $this->chunked_media( json_decode( $fresh->parameters, true ) ) ) {
				$state['error_code'] = 'rsl_ie_sync_job_worker_interrupted';
				$state['uncertain']  = $uncertain;
				return $this->save( $id, $state, [ 'status' => 'paused' ] );
			}
			return $this->fail( $id, 'worker_interrupted', $uncertain );
		} finally {
			Job_Lock::release( $lock );
		}
	}

	private function uncertain_state( array $state ) {
		return 'transferring' === ( $state['stage'] ?? '' ) || 'finalize' === ( $state['media']['phase'] ?? '' );
	}

	/** Read confirmed sources only from the persisted worker checkpoint. */
	private function confirmed_media_ids( array $params, array $state ) {
		if ( ! $this->chunked_media( $params ) ) {
			return []; }
		$ids = array_column( $state['aggregate']['items'] ?? [], 'source_id' );
		if ( 'done' === ( $state['media']['phase'] ?? '' ) ) {
			$ids[] = $state['media']['result']['items'][0]['source_id'];
		}
		return $ids;
	}

	/** Only explicitly versioned media jobs use the staged transport. */
	private function chunked_media( array $params ) {
		return 1 === ( $params['media_chunk_version'] ?? null ) && in_array( $params['operation'] ?? '', [ 'sync-media-push', 'sync-media-pull' ], true );
	}

	/** Advance one file step and atomically persist the returned checkpoint. */
	private function media_step( $job, array $params, array $state ) {
		if ( ! in_array( $state['stage'] ?? '', [ 'queued', 'media_push' ], true ) ) {
			return $this->error( 'invalid_checkpoint', 400 ); }
		$state         += [
			'cursor'    => 0,
			'aggregate' => [
				'created' => 0,
				'skipped' => 0,
				'failed'  => 0,
				'items'   => [],
			],
		];
		$state['stage'] = 'media_push';
		unset( $state['retry_count'], $state['error_code'], $state['uncertain'] );
		$driver = 'sync-media-pull' === $params['operation'] ? new Media_Chunk_Pull() : new Media_Chunk_Push();
		$media  = $state['media'] ?? [];
		if ( 'done' === ( $media['phase'] ?? '' ) ) {
			if ( empty( $media['cleaned'] ) ) {
				$next = $driver->cleanup( (int) $params['input']['site_id'], $params['connection_fingerprint'], $media );
				if ( is_wp_error( $next ) ) {
					return $next; }
				$state['media'] = $next;
				return $this->save( $job->id, $state, $this->media_result_columns( $job, $state ) );
			}
			foreach ( [ 'created', 'skipped', 'failed' ] as $count ) {
				$state['aggregate'][ $count ] += $media['result'][ $count ]; }
			$state['aggregate']['items'][] = $media['result']['items'][0];
			++$state['cursor'];
			unset( $state['media'] );
		} else {
			$input = $params['input'];
			unset( $input['site_id'] );
			if ( ! isset( $input['media_ids'][ $state['cursor'] ] ) ) {
				return $this->error( 'invalid_checkpoint', 400 ); }
			$input['media_ids'] = [ $input['media_ids'][ $state['cursor'] ] ];
			$request            = 'media-job-' . $job->id . '-' . $state['cursor'] . '-' . substr( hash( 'sha256', $job->parameters ), 0, 32 );
			$next               = $driver->advance( (int) $params['input']['site_id'], $params['connection_fingerprint'], $request, $input, $media );
			if ( is_wp_error( $next ) && empty( $media ) && 'rsl_ie_media_library_file_unavailable' === $next->get_error_code() ) {
				// A missing source file fails only this item; the rest of the batch continues.
				++$state['aggregate']['failed'];
				$state['aggregate']['items'][] = [
					'source_id'     => (int) $input['media_ids'][0],
					'attachment_id' => 0,
					'status'        => 'failed',
					'error_code'    => 'file_unavailable',
				];
				++$state['cursor'];
				$next = null;
			}
			if ( is_wp_error( $next ) ) {
				return $next; }
			if ( null !== $next ) {
				$state['media'] = $next; }
		}
		$columns = [ 'status' => 'processing' ] + $this->media_result_columns( $job, $state );
		if ( $state['cursor'] === (int) $job->total_items ) {
			$definition = $this->provider( $params['edition'] )['definitions'][ $params['operation'] ];
			if ( is_wp_error( rest_validate_value_from_schema( $state['aggregate'], $definition['output'] ) ) || array_column( $state['aggregate']['items'], 'source_id' ) !== $params['input']['media_ids'] ) {
				return $this->error( 'invalid_result', 500 ); }
			$partial                 = $state['aggregate']['failed'] > 0;
			$state['stage']          = $partial ? 'failed' : 'done';
			$state['uncertain']      = $partial;
			$state['error_code']     = $partial ? 'rsl_ie_sync_job_partial_failure' : '';
			$columns['status']       = $partial ? 'failed' : 'completed';
			$columns['result']       = wp_json_encode( $state['aggregate'] );
			$columns['completed_at'] = current_time( 'mysql' );
		}
		return $this->save( $job->id, $state, $columns );
	}

	/** Publish every confirmed outcome, including the file awaiting cleanup. */
	private function media_result_columns( $job, array $state ) {
		$result = $state['aggregate'] ?? [
			'created' => 0,
			'skipped' => 0,
			'failed'  => 0,
			'items'   => [],
		];
		if ( 'done' === ( $state['media']['phase'] ?? '' ) ) {
			foreach ( [ 'created', 'skipped', 'failed' ] as $count ) {
				$result[ $count ] += $state['media']['result'][ $count ]; }
			$result['items'][] = $state['media']['result']['items'][0];
		}
		$processed = count( $result['items'] );
		return [
			'processed_items' => $processed,
			'progress'        => (int) floor( 100 * $processed / $job->total_items ),
			'success_items'   => $result['created'],
			'skipped_items'   => $result['skipped'],
			'failed_items'    => $result['failed'],
			'result'          => $processed ? wp_json_encode( $result ) : null,
		];
	}

	/** Called only by the locked parent; persist each cleanup result before stopping. */
	public function cancel_parent_media( $parent, $id ) {
		$lock = Job_Lock::acquire( $id );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return $this->error( 'busy', 409 ); }
		try {
			$job    = rsl_ie()->Model->job->find( $id );
			$params = $job ? json_decode( $job->parameters, true ) : [];
			if ( ! $job || (int) $job->user_id !== (int) $parent->user_id || ( $params['migration_parent_id'] ?? 0 ) !== (int) $parent->id ) {
				return $this->error( 'migration_parent_required', 409 ); }
			$handled = $this->in_context(
				$job,
				function () use ( $parent, $job ) {
					return apply_filters( 'rsl_ie_sync_queue_parent_cleanup', null, $parent, $job );
				}
			);
			if ( null !== $handled ) {
				return $handled; }
			if ( ! $this->chunked_media( $params ) || 'completed' === $job->status ) {
				return true; }
			return $this->in_context(
				$job,
				function ( $params ) use ( $job ) {
					$state = json_decode( $job->settings, true );
					if ( 'finalize' === ( $state['media']['phase'] ?? '' ) ) {
						return $this->error( 'uncertain', 409 ); }
					if ( empty( $state['media'] ) ) {
						return true; }
					$state['stage'] = 'media_cancel';
					$saved          = $this->save( $job->id, $state, [] );
					if ( is_wp_error( $saved ) ) {
						return $saved; }
					// At most one transfer and one snapshot, with a checkpoint after each.
					for ( $step = 0; $step < 2; ++$step ) {
						$result = $this->media_cancel( $job, $params, $state );
						if ( is_wp_error( $result ) ) {
							return $result; }
						$state = json_decode( rsl_ie()->Model->job->find( $job->id )->settings, true );
						if ( ! empty( $state['media']['cleaned'] ) ) {
							return true; }
					}
					return $this->error( 'cleanup_pending', 409 );
				}
			);
		} finally {
			Job_Lock::release( $lock );
		}
	}

	/** Cancel only after staged resources are discarded in the captured owner context. */
	private function media_cancel( $job, array $params, array $state ) {
		$driver = 'sync-media-pull' === $params['operation'] ? new Media_Chunk_Pull() : new Media_Chunk_Push();
		$next   = $driver->cleanup( (int) $params['input']['site_id'], $params['connection_fingerprint'], $state['media'], true );
		if ( is_wp_error( $next ) ) {
			return $next; }
		$state['media'] = $next;
		$columns        = [];
		if ( ! empty( $next['cleaned'] ) ) {
			$state['stage'] = 'cancelled';
			$columns        = [
				'status'       => 'cancelled',
				'completed_at' => current_time( 'mysql' ),
			]; }
		return $this->save( $job->id, $state, $columns + $this->media_result_columns( $job, $state ) );
	}

	/**
	 * Persist in-flight checkpoint before entering the existing domain workflow.
	 *
	 * @param object $job Queue record.
	 * @param array  $params Immutable parameters.
	 * @return array|\WP_Error
	 */
	private function transfer( $job, array $params ) {
		$saved = $this->save(
			$job->id,
			[
				'stage'          => 'transferring',
				'history_job_id' => 0,
			],
			[
				'status'     => 'processing',
				'started_at' => current_time( 'mysql' ),
			]
		);
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$link  = function ( $history_id ) use ( $job ) {
			$saved = $this->save(
				$job->id,
				[
					'stage'          => 'transferring',
					'history_job_id' => (int) $history_id,
				],
				[]
			);
			if ( is_wp_error( $saved ) ) {
				throw new \RuntimeException( 'Sync checkpoint failed.' );
			}
		};
		$guard = function ( $access, $site ) use ( $params ) {
			return hash_equals( $params['connection_fingerprint'], $this->fingerprint( $site ) ) ? $access : $this->error( 'connection_changed', 409 );
		};
		add_action( 'rsl_ie_content_sync_job_created', $link );
		add_filter( 'rsl_ie_sync_connection_guard', $guard, 10, 2 );
		try {
			$provider = $this->provider( $params['edition'] );
			$result   = is_wp_error( $provider ) ? $provider : call_user_func( $provider['execute'], $params['operation'], $params['input'] );
		} finally {
			remove_action( 'rsl_ie_content_sync_job_created', $link );
			remove_filter( 'rsl_ie_sync_connection_guard', $guard, 10 );
		}
		if ( is_wp_error( $result ) ) {
			return $this->fail( $job->id, $result->get_error_code(), true );
		}
		$valid = rest_validate_value_from_schema( $result, $provider['definitions'][ $params['operation'] ]['output'], 'result' );
		if ( is_wp_error( $valid ) ) {
			return $this->fail( $job->id, 'invalid_result', true );
		}
		$counts = [
			'success_items' => (int) $job->total_items,
			'skipped_items' => 0,
			'failed_items'  => 0,
		];
		foreach ( $provider['definitions'][ $params['operation'] ]['background']['counters'] ?? [] as $column => $field ) {
			$counts[ $column ] = $result[ $field ];
		}
		if ( array_sum( $counts ) !== (int) $job->total_items ) {
			return $this->fail( $job->id, 'invalid_result', true );
		}
		$partial = $counts['failed_items'] > 0;
		return $this->save(
			$job->id,
			[
				'error_code'     => $partial ? 'rsl_ie_sync_job_partial_failure' : '',
				'uncertain'      => $partial,
				'stage'          => $partial ? 'failed' : 'done',
				'history_job_id' => (int) ( $result['job_id'] ?? 0 ),
			],
			[
				'status'          => $partial ? 'failed' : 'completed',
				'processed_items' => $job->total_items,
				'progress'        => 100,
				'result'          => wp_json_encode( $result ),
				'completed_at'    => current_time( 'mysql' ),
			] + $counts
		);
	}

	/**
	 * Persist one complete checkpoint atomically.
	 *
	 * @param int   $id Queue ID.
	 * @param array $state Checkpoint state.
	 * @param array $columns Additional columns in the same write.
	 * @return array|\WP_Error
	 */
	private function save( $id, array $state, array $columns ) {
		$result = rsl_ie()->Model->job->update(
			$id,
			[
				'settings'   => wp_json_encode( $state ),
				'updated_at' => current_time( 'mysql' ),
			] + $columns
		);
		return is_wp_error( $result ) ? $result : [ 'saved' => true ];
	}

	/**
	 * Mark failure while preserving the last linked history and uncertain-outcome flag.
	 *
	 * @param int    $id Queue ID.
	 * @param string $code Safe stable failure code.
	 * @param bool   $uncertain Transfer may already have changed content.
	 * @return array|\WP_Error
	 */
	private function fail( $id, $code, $uncertain ) {
		$job                 = rsl_ie()->Model->job->find( $id );
		$state               = json_decode( $job->settings, true );
		$state['stage']      = 'failed';
		$state['error_code'] = sanitize_key( $code );
		$state['uncertain']  = $uncertain || ! empty( $state['uncertain'] );
		return $this->save(
			$id,
			$state,
			[
				'status'       => 'failed',
				'completed_at' => current_time( 'mysql' ),
			]
		);
	}

	/**
	 * Return stable failures without raw remote data.
	 *
	 * @param string $code Error suffix.
	 * @param int    $status HTTP status.
	 * @return \WP_Error
	 */
	private function error( $code, $status ) {
		if ( 'connection_busy' === $code || 'busy' === $code ) {
			return new \WP_Error( 'rsl_ie_sync_job_' . $code, __( 'This connection is busy with another sync transfer. Wait until it finishes (check rsl-ie/sync-job-status), then try again.', 'import-export-by-rockstarlab' ), [ 'status' => $status ] );
		}
		return new \WP_Error( 'rsl_ie_sync_job_' . $code, __( 'The background sync cannot continue. Check its status, provider, connection and permissions before retrying.', 'import-export-by-rockstarlab' ), [ 'status' => $status ] );
	}
}

<?php
/**
 * Server-owned migration export orchestration.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Execution_Context;
use RockStarLab\ImportExport\Abilities\Migration_Catalog;
use RockStarLab\ImportExport\Abilities\Migration_Plan;

defined( 'ABSPATH' ) || exit;

/** Export children are driven only while their parent worker holds its lock. */
class Migration_Service {
	/**
	 * Parent currently executing in this PHP process.
	 *
	 * @var int
	 */
	private static $active = 0;

	/**
	 * Identify versioned server migrations, excluding legacy client-owned history.
	 *
	 * @param object|null $job Record.
	 * @return bool
	 */
	public static function supports( $job ) {
		return $job && in_array( $job->type, [ 'migration_export', 'migration_import' ], true ) && 'site_migration' === $job->data_type && 1 === ( json_decode( $job->parameters, true )['migration_version'] ?? 0 );
	}

	/**
	 * Allow only the current parent to execute its children.
	 *
	 * @param int $id Parent ID.
	 * @return bool
	 */
	public static function running( $id ) {
		return $id > 0 && self::$active === (int) $id; }

	/**
	 * Execute a closed public contract, including direct PHP calls.
	 *
	 * @param string       $name Ability suffix.
	 * @param array|object $input Parsed input.
	 * @return array|\WP_Error
	 */
	public function execute( $name, $input = [] ) {
		if ( $input instanceof \stdClass ) {
			$input = (array) $input; }
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access; }
		$definition = Migration_Catalog::definitions()[ $name ] ?? null;
		if ( ! $definition ) {
			return $this->error( 'operation', 400 ); }
		$valid = rest_validate_value_from_schema( $input, $definition['input'], 'input' );
		if ( is_wp_error( $valid ) ) {
			$valid->add_data( [ 'status' => 400 ] );
			return $valid; }
		$input = rest_sanitize_value_from_schema( $input, $definition['input'] );
		if ( 'migration-plan' === $name ) {
			$steps = Migration_Plan::leaves();
			return is_wp_error( $steps ) ? $steps : [
				'steps'      => array_values( $steps ),
				'operations' => [ 'export_file', 'import_file' ],
			]; }
		if ( 'migration-prepare' === $name ) {
			return ( new Migration_Package_Service() )->prepare_public( $input['file_id'], $input['offset'] ?? 0, $input['limit'] ?? 100 ); }
		if ( 'migration-import-start' === $name ) {
			return ( new Migration_Import_Service() )->start_import( $input ); }
		if ( 'migration-start' === $name ) {
			return $this->start( $input ); }
		return 'migration-get' === $name ? $this->status( $input['job_id'] ) : $this->transition( $input['job_id'], substr( $name, 10 ) );
	}

	/**
	 * Resolve unique selected leaves and freeze their server-owned export requests.
	 *
	 * @param array $input Validated start input.
	 * @return array|\WP_Error
	 */
	private function requests( array $input ) {
		$available = Migration_Plan::leaves();
		if ( is_wp_error( $available ) ) {
			return $available; }
		$selected = [];
		foreach ( $input['steps'] as $selection ) {
			$key = $selection['key'];
			if ( isset( $selected[ $key ] ) || ! isset( $available[ $key ] ) ) {
				return $this->error( 'step_unavailable', 400 ); }
			$step    = $available[ $key ];
			$options = [
				'custom_export_file_name' => pathinfo( $step['filename'], PATHINFO_FILENAME ),
				'items_per_iteration'     => 100,
			];
			foreach ( [ 'taxonomy', 'post_type' ] as $field ) {
				if ( isset( $step[ $field ] ) ) {
						$options[ $field ] = $step[ $field ]; }
			}
			if ( isset( $selection['selected_ids'] ) ) {
				$options['selected_ids'] = $selection['selected_ids']; }
			$request = [
				'export_type'    => $step['type'],
				'format'         => 'json',
				'options'        => $options,
				'fields'         => $step['fields'],
				'format_options' => [ 'json_pretty_print' => 1 ],
			];
			if ( isset( $step['taxonomy'] ) ) {
				$request['dynamic_filters'] = [
					[
						'field'     => 'taxonomy',
						'condition' => 'equals',
						'value'     => $step['taxonomy'],
					],
				]; }
			$valid = Export_Request::normalize( $request );
			if ( is_wp_error( $valid ) ) {
				return $valid; }
			$selected[ $key ] = [
				'key'          => $key,
				'order'        => $step['order'],
				'request'      => $request,
			];
		}
		uasort(
			$selected,
			static function ( $a, $b ) {
				$order = $a['order'] <=> $b['order'];
				return $order ? $order : strcmp( $a['key'], $b['key'] );
			}
		);
		return array_values( $selected );
	}

	/**
	 * Persist immutable selection identity before any export is created.
	 *
	 * @param array $input Validated start.
	 * @return array|\WP_Error
	 */
	private function start( array $input ) {
		$context = Execution_Context::capture();
		if ( is_wp_error( $context ) ) {
			return $context; }
		$request_id = $input['request_id'];
		unset( $input['request_id'] );
		// Definitions are resolved only after dedup, so a repeated completed request
		// remains stable if registered fields or the installed provider change.
		$params = [
			'migration_version' => 1,
			'input'             => $input,
			'execution_context' => $context->to_storage(),
		];
		return Job_Service::enqueue(
			[
				'type'        => 'migration_export',
				'data_type'   => 'site_migration',
				'file_format' => 'zip',
				'job_name'    => 'Content Migration',
				'status'      => 'pending',
				'user_id'     => get_current_user_id(),
				'total_items' => count( $input['steps'] ),
				'parameters'  => wp_json_encode( $params ),
				'settings'    => wp_json_encode(
					[
						'stage'    => 'queued',
						'index'    => 0,
						'children' => [],
					]
				),
			],
			$request_id,
			function () use ( $input ) {
				if ( ! class_exists( 'ZipArchive' ) ) {
					return $this->error( 'zip_unavailable', 503 ); }
				$requests = $this->requests( $input );
				return is_wp_error( $requests ) ? $requests : true;
			}
		);
	}

	/**
	 * Reauthorize the entire frozen migration before execution or package access.
	 *
	 * @param object $job Parent record.
	 * @return true|\WP_Error
	 */
	public function authorize( $job ) {
		if ( ! self::supports( $job ) ) {
			return $this->error( 'not_found', 404 ); }
		$record = ( new Job_Service() )->record( $job->id );
		if ( is_wp_error( $record ) ) {
			return $record; }
		if ( 'migration_import' === $job->type ) {
			return ( new Migration_Import_Service() )->authorize_import( $job ); }
		$state = json_decode( $job->settings, true );
		if ( empty( $state['requests'] ) ) {
			$requests = $this->requests( json_decode( $job->parameters, true )['input'] ); } else {
					$requests = $state['requests']; }
			if ( is_wp_error( $requests ) ) {
				return $requests; }
			foreach ( $requests as $step ) {
				if ( 'database_table' === ( $step['request']['export_type'] ?? '' ) ) { return $this->error( 'scope', 400 ); }
				$valid = Export_Request::normalize( $step['request'] );
				if ( is_wp_error( $valid ) ) {
					return $valid; }
			}
			return true;
	}

	/**
	 * Read server-derived progress; never expose paths or prepared request data.
	 *
	 * @param int        $id Parent ID.
	 * @return array|\WP_Error
	 */
	public function status( $id ) {
		$job = ( new Job_Service() )->record( $id );
		if ( is_wp_error( $job ) ) {
			return $job; }
		if ( ! self::supports( $job ) ) {
			return $this->error( 'not_found', 404 ); }
		$state   = json_decode( $job->settings, true );
		$allowed = $this->authorize( $job );

		return [
			'job_id'             => (int) $job->id,
			'status'             => $job->status,
			'stage'              => $state['stage'],
			'processed'          => (int) $job->processed_items,
			'total'              => (int) $job->total_items,
			'child_job_id'       => is_wp_error( $allowed ) ? 0 : (int) ( $state['children'][ $state['index'] ] ?? 0 ),
			'error_code'         => $state['error_code'] ?? '',
			'file_id'            => ! is_wp_error( $allowed ) && 'completed' === $job->status ? ( $state['file_id'] ?? '' ) : '',
			'poll_after_seconds' => 2,
			'result'             => 'migration_import' === $job->type ? Migration_Import_Service::counters( $job, $state ) : Migration_Import_Service::empty_result(),
		];
	}

	/**
	 * Serialize parent lifecycle with all child batches and packaging.
	 *
	 * @param int    $id Parent ID.
	 * @param string $action Pause/resume/cancel.
	 * @return array|\WP_Error
	 */
	public function transition( $id, $action ) {
		$job = ( new Job_Service() )->record( $id );
		if ( is_wp_error( $job ) ) {
			return $job; }
		if ( ! self::supports( $job ) ) {
			return $this->error( 'not_found', 404 ); }
		if ( ! empty( json_decode( $job->parameters, true )['migration_parent_id'] ) ) {
			return $this->error( 'parent_required', 409 ); }
		$lock = Job_Lock::acquire( $id );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return $this->error( 'busy', 409 ); }
		try {
			$job     = rsl_ie()->Model->job->find( $id );
			$targets = [
				'pause'  => 'paused',
				'resume' => 'processing',
				'cancel' => 'cancelled',
			];
			if ( ! isset( $targets[ $action ] ) ) {
				return $this->error( 'operation', 400 ); }
			if ( $targets[ $action ] === $job->status && 'resume' !== $action ) {
				return $this->status( $id ); }
			$allowed = 'resume' === $action ? [ 'paused' ] : ( 'cancel' === $action ? [ 'pending', 'processing', 'paused' ] : [ 'pending', 'processing' ] );
			if ( ! in_array( $job->status, $allowed, true ) ) {
				return $this->error( 'state_conflict', 409 ); }
			if ( 'resume' === $action ) {
				$valid = $this->context(
					$job,
					function () use ( $job ) {
						return $this->authorize( $job );
					}
				);
				if ( is_wp_error( $valid ) ) {
							return $valid; }
			}
			$state = json_decode( $job->settings, true );
			if ( 'resume' === $action && ! empty( $state['children'][ $state['index'] ?? 0 ] ) ) {
				$child = rsl_ie()->Model->job->find( $state['children'][ $state['index'] ?? 0 ] );
				if ( Sync_Job_Service::supports( $child, true ) && 'paused' === $child->status ) {
					$resumed = ( new Sync_Job_Service() )->transition( (int) $child->id, 'resume', (int) $job->id );
					if ( is_wp_error( $resumed ) ) {
						return $resumed;
					}
				}
			}
			if ( 'resume' === $action ) {
				unset( $state['error_code'] );
			}
			if ( 'cancel' === $action ) {
				// Persist intent before cleanup: a pending cleanup must never resume transfer.
				$state['cancel_requested'] = true;
				$saved                     = $this->save(
					$id,
					$state,
					[
						'status'       => 'processing',
						'completed_at' => null,
					]
				);
				if ( is_wp_error( $saved ) ) {
					return $saved; }
				$job     = rsl_ie()->Model->job->find( $id );
				$stopped = $this->context(
					$job,
					function () use ( $job, $state ) {
						return $this->cancel_batch( $job, $state );
					}
				);
				if ( is_wp_error( $stopped ) ) {
					return $stopped; }
				return $this->status( $id );
			}
			$saved = $this->save(
				$id,
				$state,
				[
					'status'       => $targets[ $action ],
					'completed_at' => 'cancel' === $action ? current_time( 'mysql' ) : null,
				]
			);
			if ( is_wp_error( $saved ) ) {
				return $saved; }
			wp_clear_scheduled_hook( 'rsl_ie_run_job', [ (int) $id ] );
			if ( 'resume' === $action ) {
				Job_Runner::schedule( $id ); }
			return $this->status( $id );
		} finally {
			Job_Lock::release( $lock ); }
	}

	/**
	 * Execute a bounded child batch or deterministic package finalization.
	 *
	 * @param int $id Parent ID from trusted scheduler.
	 * @return array|\WP_Error
	 */
	public function process_background_batch( $id ) {
		$lock = Job_Lock::acquire( $id );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return $this->error( 'busy', 409 ); }
		try {
			$job = rsl_ie()->Model->job->find( $id );
			if ( ! self::supports( $job ) || ! in_array( $job->status, [ 'pending', 'processing' ], true ) ) {
				return []; }
			if ( ! empty( json_decode( $job->parameters, true )['migration_parent_id'] ) ) {
				return $this->error( 'parent_required', 409 ); }
			self::$active = (int) $id;
			$result       = $this->context(
				$job,
				function () use ( $job ) {
					return $this->batch( $job );
				}
			);
			if ( is_wp_error( $result ) ) {
				if ( 'rsl_ie_media_library_connection_busy' === $result->get_error_code() ) { return $result; }
				return $this->fail( $id, $result->get_error_code() ); }
			return $result;
		} catch ( \Throwable $exception ) {
			return $this->fail( $id, 'rsl_ie_migration_interrupted' ); } finally {
					self::$active = 0;
					Job_Lock::release( $lock ); }
	}

	/**
	 * Restore only the user/blog captured on this parent.
	 *
	 * @param object   $job Record.
	 * @param callable $callback Worker operation.
	 * @return mixed|\WP_Error
	 */
	private function context( $job, callable $callback ) {
		$stored = json_decode( $job->parameters, true )['execution_context'] ?? [];
		if ( ( $stored['user_id'] ?? 0 ) !== (int) $job->user_id || ( $stored['blog_id'] ?? 0 ) !== get_current_blog_id() ) {
			return $this->error( 'context', 403 ); }
		$context = Execution_Context::from_storage( $stored['user_id'], $stored['blog_id'] );
		return is_wp_error( $context ) ? $context : $context->run( $callback );
	}

	/**
	 * Freeze requests, deduplicate child creation, then verify the child outcome.
	 *
	 * @param object $job Active parent.
	 * @return array|\WP_Error
	 */
	private function batch( $job ) {
		$state = json_decode( $job->settings, true );
		if ( ! empty( $state['cancel_requested'] ) ) {
			return $this->cancel_batch( $job, $state ); }
		if ( 'migration_import' === $job->type ) {
			return ( new Migration_Import_Service() )->import_batch( $job ); }
		$valid = $this->authorize( $job );
		if ( is_wp_error( $valid ) ) {
			return $valid; }
		$state = json_decode( $job->settings, true );
		if ( 'queued' === $state['stage'] ) {
			$state['requests'] = $this->requests( json_decode( $job->parameters, true )['input'] );
			if ( is_wp_error( $state['requests'] ) ) {
				return $state['requests']; }
			$state['stage'] = 'exporting';
			return $this->save(
				$job->id,
				$state,
				[
					'status'     => 'processing',
					'started_at' => current_time( 'mysql' ),
				]
			);
		}
		if ( $state['index'] >= count( $state['requests'] ) ) {
			return $this->package( $job, $state ); }
		$index = $state['index'];
		if ( empty( $state['children'][ $index ] ) ) {
			$input               = $state['requests'][ $index ]['request'];
			$input['request_id'] = 'migration-' . $job->id . '-step-' . $index;
			$child               = ( new Export_Service() )->start( $input, (int) $job->id );
			if ( is_wp_error( $child ) ) {
				return $child; }
			$state['children'][ $index ] = $child['job_id'];
			return $this->save( $job->id, $state );
		}
		$child = rsl_ie()->Model->job->find( $state['children'][ $index ] );
		if ( ! $child || 'export' !== $child->type || (int) $child->user_id !== (int) $job->user_id || ( json_decode( $child->parameters, true )['migration_parent_id'] ?? 0 ) !== (int) $job->id ) {
			return $this->error( 'child_unavailable', 409 ); }
		if ( in_array( $child->status, [ 'pending', 'processing' ], true ) ) {
			$result = ( new Export_Service() )->process_background_batch( $child->id );
			return is_wp_error( $result ) ? $result : [ 'pending' => true ];
		}
		if ( 'completed' !== $child->status || $child->failed_items > 0 || $child->skipped_items > 0 ) {
			return $this->error( 'child_failed', 409 ); }
		++$state['index'];
		$state['stage'] = count( $state['requests'] ) === $state['index'] ? 'packaging' : 'exporting';
		return $this->save(
			$job->id,
			$state,
			[
				'processed_items' => $state['index'],
				'success_items'   => $state['index'],
				'progress'        => 95 * $state['index'] / count( $state['requests'] ),
			]
		);
	}

	/**
	 * Build only from verified child records into a private atomic ZIP result.
	 *
	 * @param object $job Parent.
	 * @param array  $state Checkpoint.
	 * @return array|\WP_Error
	 */
	private function package( $job, array $state ) {
		$id  = 'file_' . md5( 'migration:' . get_current_blog_id() . ':' . $job->id );
		$dir = File_Store::directory( $id );
		if ( is_wp_error( $dir ) ) {
			return $dir; }
		$path = $dir . '/migration.zip';
		$tmp  = $dir . '/package.tmp';
		if ( is_link( $tmp ) || is_link( $path ) ) {
			return $this->error( 'package_path', 400 ); }
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $tmp, \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
			return $this->error( 'zip_write', 500 ); }
		$manifest_path = $dir . '/manifest.tmp';
		if ( is_link( $manifest_path ) ) {
			$zip->close();
			return $this->error( 'package_path', 400 ); }
		$manifest = fopen( $manifest_path, 'wb' );
		if ( ! $manifest ) {
			$zip->close();
			return $this->error( 'zip_write', 500 ); }
		$header = '{"version":"1.0","created_at":' . wp_json_encode( current_time( 'mysql' ) ) . ',"steps":[';
		$count  = 0;
		$files  = new File_Service();
		try {
			if ( fwrite( $manifest, $header ) !== strlen( $header ) ) {
				return $this->error( 'zip_write', 500 ); }
			foreach ( $state['requests'] as $index => $step ) {
				$child = rsl_ie()->Model->job->find( $state['children'][ $index ] ?? 0 );
				if ( ! $child || 'completed' !== $child->status || $child->failed_items > 0 || $child->skipped_items > 0 || (int) $child->user_id !== (int) $job->user_id || ( json_decode( $child->parameters, true )['migration_parent_id'] ?? 0 ) !== (int) $job->id ) {
					return $this->error( 'child_failed', 409 ); }
				if ( 0 === (int) $child->processed_items ) {
					continue; }
				$name = sprintf( '%02d-%s.json', $index + 1, sanitize_key( str_replace( ':', '-', $step['key'] ) ) );
				if ( 0 === (int) $child->processed_items ) {
					$added = $zip->addFromString( $name, '[]' );
				} else {
					$file = $files->from_job( $child->id );
					if ( is_wp_error( $file ) ) { return $file; }
					$record = $files->resolve( $file['file_id'], 'export' );
					if ( is_wp_error( $record ) ) { return $record; }
					$added = $zip->addFile( $record['path'], $name );
				}
				if ( ! $added ) { return $this->error( 'zip_write', 500 ); }

				$entry = [
					'type' => $step['request']['export_type'],
					'file' => $name,
				];
				foreach ( [ 'taxonomy', 'post_type' ] as $field ) {
					if ( isset( $step['request']['options'][ $field ] ) ) {
								$entry[ $field ] = $step['request']['options'][ $field ]; }
				}
				$entry = apply_filters( 'rsl_ie_migration_manifest_entry', $entry, $step );
				if ( is_wp_error( $entry ) ) { return $entry; }
				$line = ( $count ? ',' : '' ) . wp_json_encode( $entry );
				if ( fwrite( $manifest, $line ) !== strlen( $line ) ) {
					return $this->error( 'zip_write', 500 ); }
				++$count;
			}
			if ( ! $count ) {
				return $this->error( 'empty_export', 409 ); }
			if ( 2 !== fwrite( $manifest, ']}' ) || ! fflush( $manifest ) || ! $zip->addFile( $manifest_path, 'manifest.json' ) ) {
				return $this->error( 'zip_write', 500 ); }
			if ( ! $zip->close() ) {
				return $this->error( 'zip_write', 500 ); }
			$zip = null;
			// phpcs:ignore WordPress.WP.AlternativeFunctions -- Atomic publication within the same private directory.
			if ( ! rename( $tmp, $path ) ) {
				return $this->error( 'zip_write', 500 ); } // phpcs:ignore WordPress.WP.AlternativeFunctions -- Atomic publication within the same private directory.
			$record = [
				'file_id'    => $id,
				'owner_id'   => (int) $job->user_id,
				'blog_id'    => get_current_blog_id(),
				'purpose'    => 'export',
				'status'     => 'ready',
				'file_name'  => 'migration.zip',
				'format'     => 'zip',
				'path'       => $path,
				'file_size'  => filesize( $path ),
				'job_id'     => (int) $job->id,
				'created_at' => time(),
				'updated_at' => time(),
			];
			$saved  = File_Store::save( $record );
			if ( is_wp_error( $saved ) ) {
				return $saved; }
			$state['stage']   = 'done';
			$state['file_id'] = $id;
			return $this->save(
				$job->id,
				$state,
				[
					'status'       => 'completed',
					'progress'     => 100,
					'file_path'    => $path,
					'file_size'    => $record['file_size'],
					'completed_at' => current_time( 'mysql' ),
					'result'       => wp_json_encode(
						[
							'file_id'     => $id,
							'total_steps' => $count,
						]
					),
				]
			);
		} finally {
			if ( $zip ) {
					$zip->close();
			} if ( is_file( $tmp ) ) {
					wp_delete_file( $tmp ); }
			fclose( $manifest );
			if ( is_file( $manifest_path ) ) {
				wp_delete_file( $manifest_path ); }
		}
	}

	/**
	 * Persist one complete parent checkpoint atomically.
	 *
	 * @param int   $id Parent ID.
	 * @param array $state Checkpoint.
	 * @param array $columns Other columns.
	 * @return array|\WP_Error
	 */
	protected function save( $id, array $state, array $columns = [] ) {
		if ( in_array( $columns['status'] ?? '', [ 'failed', 'cancelled' ], true ) ) {
			$job = rsl_ie()->Model->job->find( $id );
			if ( $job && 'migration_import' === $job->type ) {
				$columns['result'] = wp_json_encode( Migration_Import_Service::counters( $job, $state ) );
			}
		}
		$saved = rsl_ie()->Model->job->update(
			$id,
			[
				'settings'   => wp_json_encode( $state ),
				'updated_at' => current_time( 'mysql' ),
			] + $columns
		);
		return is_wp_error( $saved ) ? $saved : [ 'saved' => true ];
	}

	/**
	 * Stop without erasing child identity or the last successful checkpoint.
	 *
	 * @param int    $id Parent ID.
	 * @param string $code Stable error code.
	 * @return array|\WP_Error
	 */
	private function fail( $id, $code ) {
		$job = rsl_ie()->Model->job->find( $id );
		if ( ! self::supports( $job ) ) {
			return $this->error( 'not_found', 404 ); }
		$state   = json_decode( $job->settings, true );
		$stopped = $this->stop_children( $job, $state );
		if ( is_wp_error( $stopped ) ) {
			$state['error_code'] = sanitize_key( $stopped->get_error_code() );
			return $this->save( $id, $state, [ 'status' => 'paused' ] );
		}
		$state['stage']      = 'failed';
		$state['error_code'] = sanitize_key( $code );
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
	 * Advance durable cancellation, waiting for owned resource cleanup.
	 *
	 * @param object $job Parent under its worker lock.
	 * @param array  $state Persisted child IDs.
	 */
	private function cancel_batch( $job, array $state ) {
		$result = $this->stop_children( $job, $state );
		if ( is_wp_error( $result ) ) {
			$state['error_code'] = sanitize_key( $result->get_error_code() );
			$saved               = $this->save(
				$job->id,
				$state,
				[
					'status'       => 'paused',
					'completed_at' => null,
				]
			);
			return $saved;
		}
		unset( $state['error_code'] );
		$saved = $this->save(
			$job->id,
			$state,
			[
				'status'       => 'cancelled',
				'completed_at' => current_time( 'mysql' ),
			]
		);
		if ( ! is_wp_error( $saved ) ) {
			wp_clear_scheduled_hook( 'rsl_ie_run_job', [ (int) $job->id ] ); }
		return $saved;
	}

	/** Close unfinished owned children only after their resources are cleaned. */
	private function stop_children( $job, array $state ) {
		foreach ( $state['children'] ?? [] as $id ) {
			$child = rsl_ie()->Model->job->find( $id );
			if ( $child && in_array( $child->status, [ 'pending', 'processing', 'paused' ], true ) && ( json_decode( $child->parameters, true )['migration_parent_id'] ?? 0 ) === (int) $job->id ) {
				$saved = rsl_ie()->Model->job->update(
					$id,
					[
						'status'       => 'cancelled',
						'completed_at' => current_time( 'mysql' ),
					]
				);
				if ( is_wp_error( $saved ) ) {
					return $saved; }
				$fresh = rsl_ie()->Model->job->find( $id );
				if ( ! $fresh || 'cancelled' !== $fresh->status ) {
					return $this->error( 'child_cancel_failed', 500 ); }
				wp_clear_scheduled_hook( 'rsl_ie_run_job', [ (int) $id ] );
			}
		}
	}

	/**
	 * Safe diagnostic without source data or local paths.
	 *
	 * @param string $code Suffix.
	 * @param int    $status HTTP status.
	 * @return \WP_Error
	 */
	protected function error( $code, $status ) {
		return new \WP_Error( 'rsl_ie_migration_' . $code, __( 'Migration cannot continue. Check its status, selected steps and current permissions.', 'import-export-by-rockstarlab' ), [ 'status' => $status ] ); }
}

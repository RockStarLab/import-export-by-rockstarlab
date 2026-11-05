<?php
/**
 * Authorized folder discovery and durable media import orchestration.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Execution_Context;
use RockStarLab\ImportExport\Abilities\Media_Folder_Catalog;
use RockStarLab\ImportExport\Helper\Media_Sync;
defined( 'ABSPATH' ) || exit;
/** AJAX, abilities and cron share one state machine and per-file checkpoint. */
class Media_Folder_Service {
	/**
	 * Whether the folder runner owns this job.
	 *
	 * @param object|null $job Stored job.
	 * @return bool
	 */
	public static function supports( $job ) {
		return $job && 'import' === $job->type && 'media_sync' === $job->data_type;
	}
	/**
	 * Start a new run from the original explicit selection, never from its cursor.
	 *
	 * @param int    $id Terminal source job.
	 * @param string $request_id Optional restart identity.
	 * @return array|\WP_Error
	 */
	public function restart( $id, $request_id = '' ) {
		$job = $this->record( $id );
		if ( is_wp_error( $job ) ) {
			return $job; }
		if ( ! in_array( $job->status, [ 'completed', 'failed', 'cancelled' ], true ) ) {
			return $this->error( 'state_conflict', 409 ); }
		$params = $this->decode( $job->parameters );
		if ( empty( $params['all_files'] ) ) {
			$params = $this->decode( $job->settings ); }
		return $this->start(
			[
				'folder_path'    => $params['folder_path'] ?? '',
				'selected_files' => $params['all_files'] ?? [],
				'sync_options'   => $params['sync_options'] ?? [],
				'scan_options'   => $params['scan_options'] ?? [],
				'request_id'     => '' === $request_id ? '' : 'restart:' . $id . ':' . $request_id,
			]
		);
	}

	/**
	 * Read stored JSON defensively.
	 *
	 * @param string|null $json Persisted JSON.
	 * @return array
	 */
	private function decode( $json ) {
		$value = json_decode( (string) $json, true );
		return is_array( $value ) ? $value : [];
	}

	/**
	 * Check folder management permission.
	 *
	 * @return true|\WP_Error
	 */
	private function authorize() {
		$access = Access_Policy::check_management();
		return is_wp_error( $access ) ? $access : ( current_user_can( 'upload_files' ) ? true : $this->error( 'forbidden', 403 ) ); }
	/**
	 * Resolve a directory inside this site's uploads.
	 *
	 * @param mixed $path Requested directory.
	 * @return string|\WP_Error
	 */
	private function directory( $path ) {
		if ( ! is_string( $path ) || strpos( $path, "\0" ) !== false || preg_match( '#^[a-z][a-z0-9+.-]*://#i', $path ) ) {
			return $this->error( 'invalid_path', 400 );
		}
		$base = realpath( wp_get_upload_dir()['basedir'] );
		$real = $base ? realpath( path_is_absolute( $path ) ? $path : $base . '/' . $path ) : false;
		if ( ! $real || ! is_dir( $real ) || ! is_readable( $real ) || strpos( trailingslashit( wp_normalize_path( $real ) ), trailingslashit( wp_normalize_path( $base ) ) ) !== 0 ) {
			return $this->error( 'invalid_path', 400 );
		}
		return wp_normalize_path( $real );
	}
	/**
	 * Return a public uploads-relative path without resolving missing source files.
	 *
	 * @param string $path Stored absolute path.
	 * @return string
	 */
	private function relative( $path ) {
		$base = trailingslashit( wp_normalize_path( (string) realpath( wp_get_upload_dir()['basedir'] ) ) );
		$path = wp_normalize_path( $path );
		return strpos( $path, $base ) === 0 ? substr( $path, strlen( $base ) ) : ''; }
	/**
	 * Normalize and validate both AJAX string booleans and API options.
	 *
	 * @param array $options Options.
	 * @param bool  $scan Select scan options.
	 * @return array|\WP_Error
	 */
	private function options( array $options, $scan = false ) {
		if ( ! $scan ) {
			unset( $options['copy_files'], $options['generate_thumbnails'] );
		}
		$schema = $scan ? Media_Folder_Catalog::scan_options() : Media_Folder_Catalog::sync_options();
		foreach ( $schema['properties'] as $key => $def ) {
			if ( 'boolean' === ( $def['type'] ?? '' ) && isset( $options[ $key ] ) && ! is_bool( $options[ $key ] ) ) {
				$value = filter_var( $options[ $key ], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
				if ( null === $value ) {
					return $this->error( 'invalid_options', 400 );
				}
				$options[ $key ] = $value;
			}
		}
		$valid = rest_validate_value_from_schema( $options, $schema, 'options' );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$options = rest_sanitize_value_from_schema( $options, $schema );
		if ( $scan ) {
			if ( ( $options['file_types'] ?? '' ) === 'custom' && empty( $options['custom_types'] ) ) {
				return $this->error( 'invalid_options', 400 );
			}
			return $options;
		}
		$options += [
			'duplicate_check'    => 'hash',
			'duplicate_handling' => 'skip',
			'file_operation'     => 'keep',
			'batch_size'         => 3,
			'rml_integration'    => false,
		];
		if ( 'override' === $options['duplicate_handling'] ) {
			$options['duplicate_handling'] = 'overwrite';
		}
		if ( $options['rml_integration'] && ( ! function_exists( 'wp_rml_create' ) || ! function_exists( 'wp_rml_move' ) ) ) {
			return $this->error( 'rml_unavailable', 409 );
		}
		return $options;
	}
	/**
	 * Browse uploads subdirectories with stable sorting and bounded output.
	 *
	 * @param array $input Path and pagination.
	 * @param bool  $public_paths Return relative paths for agents.
	 * @return array|\WP_Error
	 */
	public function browse( array $input = [], $public_paths = true ) {
		$access = $this->authorize();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		$dir = $this->directory( $input['path'] ?? '' );
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}
		$folders = [];
		$entries = scandir( $dir );
		if ( false === $entries ) {
			return $this->error( 'scan_unavailable', 400 ); }
		foreach ( $entries as $name ) {
			if ( '.' === $name || '..' === $name || ! is_dir( $dir . '/' . $name ) ) {
				continue;
			}
			$child = $this->directory( $dir . '/' . $name );
			if ( ! is_wp_error( $child ) ) {
				$folders[] = [
					'name' => $name,
					'path' => $public_paths ? $this->relative( $child ) : $child,
				];
			}
		}
		usort(
			$folders,
			static function ( $a, $b ) {
				return strnatcasecmp( $a['name'], $b['name'] );
			}
		);
		$up     = $this->relative( $dir ) !== '';
		$offset = max( 0, (int) ( $input['offset'] ?? 0 ) );
		$page   = array_slice( $folders, $offset, $public_paths ? min( 500, max( 1, (int) ( $input['limit'] ?? 100 ) ) ) : count( $folders ) );
		return [
			'folders'      => $page,
			'current_path' => $public_paths ? $this->relative( $dir ) : $dir,
			'can_go_up'    => $up,
			'parent_path'  => $up ? ( $public_paths ? $this->relative( dirname( $dir ) ) : dirname( $dir ) ) : ( $public_paths ? '' : null ),
			'total'        => count( $folders ),
			'next_offset'  => $offset + count( $page ),
			'complete'     => $offset + count( $page ) >= count( $folders ),
		];
	}
	/**
	 * Scan a bounded folder selection, preserving shared file filters.
	 *
	 * @param array $input Folder, options and pagination.
	 * @param bool  $public_paths Return relative paths for agents.
	 * @return array|\WP_Error
	 */
	public function scan( array $input, $public_paths = true ) {
		$access = $this->authorize();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		$dir = $this->directory( $input['folder_path'] ?? '' );
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}
		$options = $this->options( $input['options'] ?? [], true );
		if ( is_wp_error( $options ) ) {
			return $options;
		}
		try {
			$files = Media_Sync::scan_folder( $dir, $options + [ 'max_files' => 10000 ] );
		} catch ( \Throwable $error ) {
			return $this->error( 'scan_unavailable', 400 );
		}
		if ( is_wp_error( $files ) ) {
			return $files;
		}
		usort(
			$files,
			static function ( $a, $b ) {
				return strcmp( $a['path'], $b['path'] );
			}
		);
		$offset = max( 0, (int) ( $input['offset'] ?? 0 ) );
		$page   = array_slice( $files, $offset, $public_paths ? min( 500, max( 1, (int) ( $input['limit'] ?? 100 ) ) ) : count( $files ) );
		if ( $public_paths ) {
			foreach ( $page as &$file ) {
				$file['path'] = $this->relative( $file['path'] );
			}unset( $file );
		}
		return [
			'files'       => $page,
			'total'       => count( $files ),
			'next_offset' => $offset + count( $page ),
			'complete'    => $offset + count( $page ) >= count( $files ),
		];
	}
	/**
	 * Persist explicit sources, options and trusted execution identity.
	 *
	 * @param array $input Folder import configuration.
	 * @return array|\WP_Error
	 */
	public function start( array $input ) {
		$access = $this->authorize();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		$root = $this->directory( $input['folder_path'] ?? '' );
		if ( is_wp_error( $root ) ) {
			return $root;
		}
		$options = $this->options( $input['sync_options'] ?? [] );
		$scan    = $this->options( $input['scan_options'] ?? [], true );
		if ( is_wp_error( $options ) || is_wp_error( $scan ) ) {
			return is_wp_error( $options ) ? $options : $scan;
		}
		$files = $input['selected_files'] ?? [];
		if ( ! is_array( $files ) || ! $files || count( $files ) > 1000 ) {
			return $this->error( 'invalid_selection', 400 );
		}
		$paths = [];
		foreach ( $files as $file ) {
			$path = is_array( $file ) ? ( $file['path'] ?? '' ) : $file;
			if ( ! is_string( $path ) || '' === $path ) {
				return $this->error( 'invalid_selection', 400 );
			}
			$path   = path_is_absolute( $path ) ? $path : wp_get_upload_dir()['basedir'] . '/' . $path;
			$parent = $this->directory( dirname( $path ) );
			if ( is_wp_error( $parent ) || 0 !== strpos( trailingslashit( $parent ), trailingslashit( $root ) ) ) {
				return $this->error( 'invalid_source', 400 );
			}
			$path = $parent . '/' . basename( $path );
			if ( isset( $paths[ $path ] ) ) {
				return $this->error( 'duplicate_source', 400 );
			}
			$paths[ $path ] = $path;
		}
		$context = Execution_Context::capture();
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		$params   = [
			'folder_version'    => 1,
			'folder_path'       => $root,
			'all_files'         => array_values( $paths ),
			'total_files'       => count( $paths ),
			'scan_options'      => $scan,
			'sync_options'      => $options,
			'execution_context' => $context->to_storage(),
		];
		$settings = $params + [ 'offset' => 0 ];
		$created  = Job_Service::enqueue(
			[
				'type'        => 'import',
				'data_type'   => 'media_sync',
				'file_format' => 'folder',
				'file_path'   => '',
				'status'      => 'pending',
				'user_id'     => get_current_user_id(),
				'total_items' => count( $paths ),
				'parameters'  => wp_json_encode( $params ),
				'settings'    => wp_json_encode( $settings ),
			],
			$input['request_id'] ?? '',
			function () use ( $paths, $root ) {
				foreach ( $paths as $path ) {
					if ( is_wp_error( Media_Sync::validate_source_file( $path, $root ) ) ) {
						return $this->error( 'invalid_source', 400 );
					}
				}
				return true;
			}
		);
		if ( is_wp_error( $created ) ) {
			return $created;
		}
		$result = $this->status( $created['job_id'] );
		return is_wp_error( $result ) ? $result : array_merge( $result, array_intersect_key( $created, array_flip( [ 'deduplicated','scheduling_warning' ] ) ) );
	}
	/**
	 * Check that a job belongs to the folder domain and current site.
	 *
	 * @param int $id Job ID.
	 * @return object|\WP_Error
	 */
	private function record( $id ) {
		$access = $this->authorize();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		$job = ( new Job_Service() )->record( $id );
		return is_wp_error( $job ) ? $job : ( 'import' === $job->type && 'media_sync' === $job->data_type ? $job : $this->error( 'job_not_found', 404 ) ); }
	/**
	 * Read progress without advancing or scheduling work.
	 *
	 * @param int $id Job ID.
	 * @return array|\WP_Error
	 */
	public function status( $id ) {
		$job = $this->record( $id );
		return is_wp_error( $job ) ? $job : $this->snapshot( $job ); }
	/**
	 * Return a bounded, path-redacted result compatible with the old progress UI.
	 *
	 * @param object $job Persisted job.
	 * @return array
	 */
	private function snapshot( $job ) {
		$result           = $this->decode( $job->result );
		$result          += [
			'processed' => 0,
			'success'   => 0,
			'skipped'   => 0,
			'failed'    => 0,
			'errors'    => [],
			'items'     => [],
		];
		$result           = array_intersect_key( $result, array_flip( [ 'processed','success','skipped','failed','errors','items','error' ] ) );
		$code             = static function ( $value ) {
			return is_string( $value ) && preg_match( '/^[a-zA-Z0-9_.-]{1,100}$/D', $value ) ? $value : 'legacy_media_error';
		};
		$result['errors'] = array_map( $code, array_slice( (array) $result['errors'], -20 ) );
		if ( isset( $result['error'] ) ) {
			$result['error'] = $code( $result['error'] );
		}
		$result['items'] = array_slice( (array) $result['items'], -200 );
		foreach ( $result['items'] as &$item ) {
			$item['file'] = $this->relative( (string) ( $item['file'] ?? '' ) );
			$item         = array_intersect_key( $item, array_flip( [ 'action','file','attachment_id','reason' ] ) );
			if ( isset( $item['reason'] ) ) {
				$item['reason'] = $code( $item['reason'] );
			}
		}
		unset( $item );
		return [
			'job_id'             => (int) $job->id,
			'status'             => $job->status,
			'progress'           => (float) $job->progress,
			'completed'          => in_array( $job->status, [ 'completed','cancelled','failed' ], true ),
			'offset'             => (int) $job->processed_items,
			'total'              => (int) $job->total_items,
			'result'             => $result,
			'poll_after_seconds' => 2,
		];
	}
	/**
	 * Pause, resume or cancel under the same lock as the worker.
	 *
	 * @param int    $id Job ID.
	 * @param string $action Lifecycle action.
	 * @return array|\WP_Error
	 */
	public function transition( $id, $action ) {
		$job = $this->record( $id );
		if ( is_wp_error( $job ) ) {
			return $job;
		}
		$lock = Job_Lock::acquire( $id );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return is_wp_error( $lock ) ? $lock : $this->error( 'job_busy', 409 );
		}
		try {
			$job = $this->record( $id );
			if ( is_wp_error( $job ) ) {
				return $job;
			}
			$states = [
				'pause'  => [ 'pending', 'processing', 'paused' ],
				'resume' => [ 'paused', 'pending', 'processing' ],
				'cancel' => [ 'pending', 'processing', 'paused', 'cancelled' ],
			];
			if ( ! isset( $states[ $action ] ) || ! in_array( $job->status, $states[ $action ], true ) ) {
				return $this->error( 'state_conflict', 409 );
			}
			if ( 'resume' === $action ) {
				$valid = $this->run_context(
					$job,
					function () use ( $job ) {
						$access = $this->authorize();
						if ( is_wp_error( $access ) ) {
									return $access;
						}
						$settings = json_decode( $job->settings, true );
						return $this->directory( $settings['folder_path'] ?? '' );
					}
				);
				if ( is_wp_error( $valid ) ) {
								return $valid;
				}
			}
			$status = [
				'pause'  => 'paused',
				'resume' => 'processing',
				'cancel' => 'cancelled',
			][ $action ];
			$saved  = rsl_ie()->Model->job->update( $id, [ 'status' => $status ] );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
			wp_clear_scheduled_hook( 'rsl_ie_run_job', [ (int) $id ] );
			wp_clear_scheduled_hook( 'rsl_ie_process_media_sync_job', [ (int) $id ] );
			if ( 'resume' === $action ) {
				Job_Runner::schedule( $id );
			}
			return $this->status( $id );
		} finally {
			Job_Lock::release( $lock );
		}
	}
	/**
	 * AJAX entry point, never trust the caller's identity for the worker.
	 *
	 * @param int $id Job ID.
	 * @return array|\WP_Error
	 */
	public function process_batch( $id ) {
		$job = $this->record( $id );
		return is_wp_error( $job ) ? $job : $this->process_background_batch( $id ); }
	/**
	 * Restore only identity stored in the site's job record.
	 *
	 * @param object   $job Job record.
	 * @param callable $operation Trusted worker callback.
	 * @return mixed
	 */
	private function run_context( $job, callable $operation ) {
		$params = $this->decode( $job->parameters );
		$stored = $params['execution_context'] ?? [
			'user_id' => (int) $job->user_id,
			'blog_id' => get_current_blog_id(),
		];
		if ( ! is_array( $stored ) || ( $stored['user_id'] ?? 0 ) !== (int) $job->user_id || ( $stored['blog_id'] ?? 0 ) !== get_current_blog_id() ) {
			return $this->error( 'invalid_context', 403 );
		}
		$context = Execution_Context::from_storage( $stored['user_id'], $stored['blog_id'] );
		return is_wp_error( $context ) ? $context : $context->run( $operation );
	}
	/**
	 * Cookie-independent batch; never expose this method as an HTTP callback.
	 *
	 * @param int $id Job ID in the current site table.
	 * @return array|\WP_Error
	 */
	public function process_background_batch( $id ) {
		$model = rsl_ie()->Model->job;
		$job   = $model->find( (int) $id );
		if ( ! $job || 'import' !== $job->type || 'media_sync' !== $job->data_type ) {
			return $this->error( 'job_not_found', 404 );
		}
		$lock = Job_Lock::acquire( $id );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return is_wp_error( $lock ) ? $lock : $this->error( 'job_busy', 409 );
		}
		$media_lock = null;
		try {
			$job = $model->find( $id );
			if ( ! $job ) {
				return $this->error( 'job_not_found', 404 );
			}
			if ( ! in_array( $job->status, [ 'pending','processing' ], true ) ) {
				return $this->snapshot( $job );
			}
			$media_lock = Job_Lock::acquire_resource( 'media', 'folder-import' );
			if ( ! $media_lock || is_wp_error( $media_lock ) ) {
				return is_wp_error( $media_lock ) ? $media_lock : $this->error( 'media_busy', 409 );
			}
			$result = $this->run_context(
				$job,
				function () use ( $job ) {
					return $this->run_batch( $job );
				}
			);
			if ( is_wp_error( $result ) ) {
				$fresh         = $model->find( $id );
				$data          = $this->decode( $fresh->result );
				$data['error'] = $result->get_error_code();
				$model->update(
					$id,
					[
						'status'       => 'failed',
						'result'       => wp_json_encode( $data ),
						'completed_at' => current_time( 'mysql' ),
					]
				);
				return $result;
			}
			return $result;
		} catch ( \Throwable $error ) {
			$fresh         = $model->find( $id );
			$data          = $this->decode( $fresh->result );
			$data['error'] = 'rsl_ie_media_interrupted';
			$model->update(
				$id,
				[
					'status' => 'failed',
					'result' => wp_json_encode( $data ),
				]
			);
			return $this->error( 'interrupted', 409 );
		} finally {
			if ( is_string( $media_lock ) ) {
				Job_Lock::release( $media_lock );
			}Job_Lock::release( $lock );
			$fresh = $model->find( $id );
			if ( $fresh && in_array( $fresh->status, [ 'pending','processing' ], true ) ) {
				Job_Runner::schedule( $id );
			}
		}
	}
	/**
	 * Persist each completed file before moving the cursor.
	 *
	 * @param object $job Locked job record.
	 * @return array|\WP_Error
	 */
	private function run_batch( $job ) {
		$access = $this->authorize();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		$settings = json_decode( (string) $job->settings, true );
		if ( ! is_array( $settings ) ) {
			return $this->error( 'invalid_job', 400 );
		}
		if ( ! empty( $settings['in_flight'] ) ) {
			return $this->error( 'uncertain_file_result', 409 );
		}
		$root = $this->directory( $settings['folder_path'] ?? '' );
		if ( is_wp_error( $root ) ) {
			return $root;
		}
		$options = $this->options( $settings['sync_options'] ?? [] );
		if ( is_wp_error( $options ) ) {
			return $options;
		}
		if ( ! isset( $settings['all_files'] ) ) {
			$params                = $this->decode( $job->parameters );
			$settings['all_files'] = $params['all_files'] ?? null;
			if ( ! is_array( $settings['all_files'] ) ) {
				$scan = $this->scan(
					[
						'folder_path' => $root,
						'options'     => $settings['scan_options'] ?? [],
					],
					false
				);
				if ( is_wp_error( $scan ) ) {
					return $scan;
				}
				$settings['all_files'] = $scan['files'];
			}
		}
		$files  = $settings['all_files'];
		$offset = (int) ( $settings['offset'] ?? 0 );
		if ( ! is_array( $files ) || ! $files || $offset < 0 || $offset > count( $files ) ) {
			return $this->error( 'invalid_job', 400 );
		}
		$result  = $this->decode( $job->result );
		$result += [
			'processed' => 0,
			'success'   => 0,
			'skipped'   => 0,
			'failed'    => 0,
			'errors'    => [],
			'items'     => [],
		];
		unset( $result['error'] );
		$start = microtime( true );
		foreach ( array_slice( $files, $offset, $options['batch_size'] ) as $file ) {
			$path = is_array( $file ) ? ( $file['path'] ?? '' ) : $file;
			if ( isset( $settings['pending_image'] ) ) {
				$item    = $settings['pending_image'];
				$pending = $this->image_metadata_step( (int) $item['attachment_id'] );
				if ( is_wp_error( $pending ) ) {
					return $pending;
				}
				if ( $pending ) {
					return $this->snapshot( rsl_ie()->Model->job->find( $job->id ) );
				}
				unset( $settings['pending_image'] );
			} else {
				$settings['in_flight'] = [ 'offset' => $offset ];
				$saved                 = $this->save( $job->id, $settings, $result, count( $files ), 'processing' );
				if ( is_wp_error( $saved ) ) {
					return $saved;
				}
				$item = $this->import_one( $path, $root, $options );
				if ( in_array( $item['action'], [ 'created', 'updated' ], true ) && wp_attachment_is_image( $item['attachment_id'] ) ) {
					// An overwritten original requires new dimensions and sizes.
					if ( 'updated' === $item['action'] ) {
						wp_update_attachment_metadata( $item['attachment_id'], [] );
					}
					// Publish the identity before image work. Retries never copy/import it again.
					$settings['pending_image'] = $item;
					unset( $settings['in_flight'] );
					$saved = $this->save( $job->id, $settings, $result, count( $files ), 'processing' );
					if ( is_wp_error( $saved ) ) {
						return $saved;
					}
					$pending = $this->image_metadata_step( (int) $item['attachment_id'] );
					if ( is_wp_error( $pending ) ) {
						return $pending;
					}
					if ( $pending ) {
						return $this->snapshot( rsl_ie()->Model->job->find( $job->id ) );
					}
					unset( $settings['pending_image'] );
				}
			}
			++$offset;
			++$result['processed'];
			++$result[ 'failed' === $item['action'] ? 'failed' : ( 'skipped' === $item['action'] ? 'skipped' : 'success' ) ];
			$result['items'][] = $item;
			$result['items']   = array_slice( $result['items'], -200 );
			if ( 'failed' === $item['action'] ) {
				$result['errors'][] = $item['reason'];
				$result['errors']   = array_slice( $result['errors'], -20 );
			}
			$settings['offset'] = $offset;
			unset( $settings['in_flight'] );
			$saved = $this->save( $job->id, $settings, $result, count( $files ), $offset >= count( $files ) ? 'completed' : 'processing' );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
			if ( ( in_array( $item['action'], [ 'created', 'updated' ], true ) && wp_attachment_is_image( $item['attachment_id'] ) ) || microtime( true ) - $start >= 10 ) {
				break;
			}
		}
		if ( $offset >= count( $files ) ) {
			$saved = $this->save( $job->id, $settings, $result, count( $files ), 'completed' );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}
		return $this->snapshot( rsl_ie()->Model->job->find( $job->id ) );
	}
	/**
	 * Process one source through the existing file/RML/hash integration.
	 *
	 * @param mixed  $source Persisted path.
	 * @param string $root Resolved folder.
	 * @param array  $options Validated import options.
	 * @return array
	 */
	private function import_one( $source, $root, array $options ) {
		$item = [
			'action' => 'failed',
			'file'   => is_string( $source ) ? $source : '',
			'reason' => 'invalid_source',
		];
		$path = is_string( $source ) ? Media_Sync::validate_source_file( $source, $root ) : $this->error( 'invalid_source', 400 );
		if ( is_wp_error( $path ) ) {
			return $item;
		}
		$existing = 'import' === $options['duplicate_handling'] ? false : Media_Sync::check_duplicate( $path, $options['duplicate_check'] );
		if ( $existing && ! current_user_can( 'skip' === $options['duplicate_handling'] ? 'read_post' : 'edit_post', $existing ) ) {
			$item['reason'] = 'attachment_forbidden';
			return $item;
		}
		if ( $existing && 'skip' === $options['duplicate_handling'] ) {
			return [
				'action'        => 'skipped',
				'file'          => $path,
				'attachment_id' => (int) $existing,
				'reason'        => 'duplicate_' . $options['duplicate_check'],
			];
		}
		$options['base_folder']          = $root;
		$options['rml_folder_structure'] = $options['rml_integration'];
		$options['skip_thumbnails']      = true;
		$import                          = Media_Sync::import_file( $path, $options );
		if ( is_wp_error( $import ) || ! $import ) {
			$item['reason'] = is_wp_error( $import ) ? $import->get_error_code() : 'import_failed';
			return $item;
		}
		return [
			'action'        => $existing && (int) $existing === (int) $import ? 'updated' : 'created',
			'file'          => $path,
			'attachment_id' => (int) $import,
		];
	}
	/**
	 * Generate all missing sizes for one image, retaining native WordPress checkpoints.
	 *
	 * @param int $id Imported attachment.
	 * @return bool|\WP_Error Whether sizes remain.
	 */
	private function image_metadata_step( $id ) {
		if ( ! current_user_can( 'edit_post', $id ) || ! wp_attachment_is_image( $id ) ) {
			return $this->error( 'attachment_forbidden', 403 );
		}
		\RockStarLab\ImportExport\Helper\Fs::load_attachment_metadata_core();
		$before   = wp_get_missing_image_subsizes( $id );
		$metadata = wp_get_attachment_metadata( $id );
		if ( ! empty( $metadata['file'] ) ) {
			$metadata = wp_update_image_subsizes( $id );
		} else {
			$metadata = wp_generate_attachment_metadata( $id, get_attached_file( $id ) );
			if ( is_array( $metadata ) ) {
				wp_update_attachment_metadata( $id, $metadata );
			}
		}
		$after = wp_get_missing_image_subsizes( $id );
		if ( is_wp_error( $metadata ) || empty( $metadata['file'] ) || ( $after && $after === $before ) ) {
			return $this->error( 'image_size_failed', 500 );
		}
		if ( ! $after ) {
			$path = get_attached_file( $id );
			\RockStarLab\ImportExport\Helper\Media_Hash::store_attachment_hash( $id, md5_file( $path ), $path );
		}
		return ! empty( $after );
	}

	/**
	 * Store state and result together in one row update.
	 *
	 * @param int    $id Job ID.
	 * @param array  $settings Mutable checkpoint.
	 * @param array  $result Counters and recent items.
	 * @param int    $total Selection size.
	 * @param string $status State.
	 * @return mixed
	 */
	private function save( $id, array $settings, array $result, $total, $status ) {
		return rsl_ie()->Model->job->update(
			$id,
			[
				'settings'        => wp_json_encode( $settings ),
				'result'          => wp_json_encode( $result ),
				'status'          => $status,
				'total_items'     => $total,
				'processed_items' => $result['processed'],
				'skipped_items'   => $result['skipped'],
				'success_items'   => $result['success'],
				'failed_items'    => $result['failed'],
				'progress'        => $total ? round( 100 * $result['processed'] / $total, 2 ) : 100,
				'completed_at'    => 'completed' === $status ? current_time( 'mysql' ) : null,
			]
		); }
	/**
	 * Safe, stable domain error.
	 *
	 * @param string $code Error suffix.
	 * @param int    $status HTTP status.
	 * @return \WP_Error
	 */
	private function error( $code, $status ) {
		if ( 'rml_unavailable' === $code ) {
			return new \WP_Error( 'rsl_ie_media_' . $code, __( 'Real Media Library is unavailable. Activate the plugin or turn off Real Media Library integration and start sync again.', 'import-export-by-rockstarlab' ), [ 'status' => $status ] );
		}
		return new \WP_Error( 'rsl_ie_media_' . $code, __( 'Media folder operation could not complete. Check selection, permissions, job state and saved results.', 'import-export-by-rockstarlab' ), [ 'status' => $status ] ); }
}

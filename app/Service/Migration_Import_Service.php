<?php
/**
 * Durable package import through existing content importers.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Execution_Context;
use RockStarLab\ImportExport\Abilities\Migration_Plan;

defined( 'ABSPATH' ) || exit;

/** The parent owns selection, ordering, maps and child lifecycle. */
class Migration_Import_Service extends Migration_Service {
	public static function stored_requests( array $state ) {
		if ( isset( $state['request_store'] ) ) {
			yield from Archive_Step_Store::rows( $state['request_store'] ); } else {
			yield from $state['requests'] ?? []; }
	}
	private static function request_count( array $state ) {
		return $state['request_store']['count'] ?? count( $state['requests'] ?? [] );
	}

	/**
	 * Stable aggregate counters; no row content in the migration status.
	 *
	 * @return array
	 */
	public static function empty_result() {
		return array_fill_keys( [ 'files_count', 'empty_files', 'success', 'created', 'updated', 'skipped', 'failed', 'replaced_urls' ], 0 );
	}

	/**
	 * Include the active child's confirmed rows without trusting client counters.
	 *
	 * @param object $job Parent.
	 * @param array  $state Saved checkpoint.
	 * @return array
	 */
	public static function counters( $job, array $state ) {
		$result = $state['result'] ?? self::empty_result();
		$id     = $state['children'][ $state['index'] ] ?? 0;
		$child  = $id ? rsl_ie()->Model->job->find( $id ) : null;
		if ( $child && (int) $child->user_id === (int) $job->user_id && ( json_decode( $child->parameters, true )['migration_parent_id'] ?? 0 ) === (int) $job->id ) {
			$rows = json_decode( (string) $child->result, true );
			foreach ( [ 'success', 'created', 'updated', 'skipped', 'failed', 'replaced_urls' ] as $key ) {
				$result[ $key ] += (int) ( $rows[ $key ] ?? 0 ); }
		}
		return $result;
	}

	/**
	 * Queue exact package bytes, with dedup before preparation.
	 *
	 * @param array $input Validated ability input.
	 * @return array|\WP_Error
	 */
	public function start_import( array $input ) {
		$schema = \RockStarLab\ImportExport\Abilities\Migration_Catalog::definitions()['migration-import-start']['input'];
		$valid  = rest_validate_value_from_schema( $input, $schema, 'input' );
		if ( is_wp_error( $valid ) ) {
			$valid->add_data( [ 'status' => 400 ] );
			return $valid; }
		$context = Execution_Context::capture();
		if ( is_wp_error( $context ) ) {
			return $context; }
		$request_id = $input['request_id'];
		unset( $input['request_id'] );
		$input += [
			'duplicate_handling'  => 'skip',
			'download_media'      => true,
			'replace_links_rules' => [],
		];
		return Job_Service::enqueue(
			[
				'type'        => 'migration_import',
				'data_type'   => 'site_migration',
				'file_format' => 'zip',
				'job_name'    => 'Content Migration Import',
				'status'      => 'pending',
				'user_id'     => get_current_user_id(),
				'parameters'  => wp_json_encode(
					[
						'migration_version' => 1,
						'input'             => $input,
						'execution_context' => $context->to_storage(),
					]
				),
				'settings'    => wp_json_encode(
					[
						'stage'    => 'queued',
						'index'    => 0,
						'children' => [],
						'maps'     => [],
						'result'   => self::empty_result(),
					]
				),
			],
			$request_id,
			function () use ( $input ) {
				$valid = $this->source( $input );
				if ( is_wp_error( $valid ) ) {
					return $valid;
				}
				$files = new File_Service();
				$meta  = $files->get( $input['file_id'] );
				if ( is_wp_error( $meta ) ) {
					return $meta;
				}
				$file = $files->resolve( $input['file_id'], $meta['purpose'] );
				if ( is_wp_error( $file ) ) {
					return $file;
				}
				$steps = ( new Migration_Package_Service() )->inspect( $file['path'], true, $input['steps'] ?? [] );
				if ( is_wp_error( $steps ) ) {
					return 'rsl_ie_migration_package_selection' === $steps->get_error_code() ? $this->error( 'selection', 400 ) : $steps;
				}
				return true;
			}
		);
	}

	/**
	 * Check source fingerprint without publishing or parsing entry resources.
	 *
	 * @param array $input Frozen package identity.
	 * @return true|\WP_Error
	 */
	private function source( array $input ) {
		$files = new File_Service();
		$meta  = $files->get( $input['file_id'] );
		if ( is_wp_error( $meta ) ) {
			return $meta; }
		$file = $files->resolve( $input['file_id'], $meta['purpose'] );
		if ( is_wp_error( $file ) ) {
			return $file; }
		return 'zip' === $file['format'] && hash_equals( $input['sha256'], hash_file( 'sha256', $file['path'] ) ) ? true : $this->error( 'package_changed', 409 );
	}

	/**
	 * Recheck all scopes and immutable files before every parent batch.
	 *
	 * @param object $job Authorized parent record.
	 * @return true|\WP_Error
	 */
	public function authorize_import( $job ) {
		$input = json_decode( $job->parameters, true )['input'];
		$valid = $this->source( $input );
		if ( is_wp_error( $valid ) ) {
			return $valid; }
		$state = json_decode( $job->settings, true );
		if ( ! isset( $state['requests'] ) && ! isset( $state['request_store'] ) ) {
			$files = new File_Service();
			$meta  = $files->get( $input['file_id'] );
			$file  = $files->resolve( $input['file_id'], $meta['purpose'] );
			$valid = ( new Migration_Package_Service() )->inspect( $file['path'], true );
			return is_wp_error( $valid ) ? $valid : true;
		}
		try {
			foreach ( self::stored_requests( $state ) as $step ) {
				if ( 'database_table' === ( $step['request']['import_type'] ?? '' ) ) { return $this->error( 'scope', 400 ); }
				$valid = ( new File_Service() )->resolve( $step['request']['file_id'], 'import' );
				if ( is_wp_error( $valid ) ) {
					return $valid; }
				if ( ! $step['empty'] ) {
					$valid = ( new Import_Service() )->check_request( $step['request'] );
					if ( is_wp_error( $valid ) ) {
						return $valid; }
				}
			}
		} catch ( \Throwable $error ) {
			return $this->error( 'step_unavailable', 409 ); }
		return true;
	}

	/**
	 * Freeze every prepared step; callers cannot inject mappings or child jobs.
	 *
	 * @param array $input Validated public input.
	 * @param array $package Validated prepared entries.
	 * @return array|\WP_Error
	 */
	private function requests( array $input, array $package ) {
		$valid = $this->source( $input );
		if ( is_wp_error( $valid ) ) {
			return $valid; }
		if ( ! hash_equals( $input['sha256'], $package['sha256'] ) ) {
			return $this->error( 'package_changed', 409 ); }
		$requests = [];
		foreach ( $package['steps'] as $step ) {
			$resolved = ( new File_Service() )->resolve( $step['file_id'], 'import' );
			if ( is_wp_error( $resolved ) ) {
				return $resolved;
			}
			if ( isset( $input['steps'] ) && ! in_array( $step['key'], $input['steps'], true ) ) {
				continue;
			}
			$scope = Migration_Plan::import_scope( $step );
			if ( is_wp_error( $scope ) ) {
				return $scope; }
			$preferred = 'media' === $step['type'] ? 'file_url' : ( 'comment' === $step['type'] ? 'comment_ID' : 'ID' );
			// IDs belong to the source database and may identify unrelated destination objects.
			if ( in_array( $step['type'], [ 'post', 'page', 'custom_post_types' ], true ) ) {
				$preferred = in_array( 'post_name', $step['headers'], true ) ? 'post_name' : 'post_title';
			}
			$options   = $scope['options'] + [
				'duplicate_handling'  => $input['duplicate_handling'],
				'if_exists'           => $input['duplicate_handling'],
				'if_not_exists'       => 'create',
				'unique_field'        => in_array( $preferred, $step['headers'], true ) ? $preferred : ( $step['headers'][0] ?? '' ),
				'download_images'     => $input['download_media'],
				'auto_import_media'   => $input['download_media'],
				'batch_size'          => 25,
				'replace_links'       => ! empty( $input['replace_links_rules'] ),
				'replace_links_rules' => $input['replace_links_rules'],
			];
			$options   = apply_filters( 'rsl_ie_migration_import_options', $options, $step );
			$request   = [
				'file_id'     => $step['file_id'],
				'import_type' => $step['type'],
				'format'      => $step['format'],
				'options'     => $options,
				'mapping'     => array_map(
					static function ( $field ) {
						return [
							'source_field' => $field,
							'target_field' => $field,
						];
					},
					$step['headers']
				),
			];
			if ( $step['total_rows'] ) {
				$valid = ( new Import_Service() )->check_request( $request );
				if ( is_wp_error( $valid ) ) {
					return $valid; }
			}
			$requests[] = [
				'key'          => $step['key'],
				'request'      => $request,
				'empty'        => 0 === $step['total_rows'],
			];
		}
		return $requests;
	}

	/**
	 * Advance one checkpoint or one child batch under the parent's lock.
	 *
	 * @param object $job Active parent.
	 * @return array|\WP_Error
	 */
	public function import_batch( $job ) {
		$valid = $this->authorize_import( $job );
		if ( is_wp_error( $valid ) ) {
			return $valid; }
		$state = json_decode( $job->settings, true );
		if ( in_array( $state['stage'], [ 'queued', 'preparing' ], true ) ) {
			$input   = json_decode( $job->parameters, true )['input'];
			$package = $state['package_preparation'] ?? [
				'sha256'        => $input['sha256'],
				'count'         => 0,
				'request_store' => [
					'file_id' => $input['file_id'],
					'scope'   => 'job-' . $job->id,
					'count'   => 0,
				],
			];
			if ( isset( $package['steps'] ) ) {
				$package['request_store'] = [
					'file_id' => $input['file_id'],
					'scope'   => 'job-' . $job->id,
					'count'   => 0,
				];
				$legacy                   = $this->requests( $input, $package );
				if ( is_wp_error( $legacy ) ) {
					return $legacy; }
				foreach ( $legacy as $request ) {
					Archive_Step_Store::put( $package['request_store'], $package['request_store']['count']++, $request ); }
				$package['count'] = count( $package['steps'] );
				unset( $package['steps'] );
			}
			$package['entry'] = $package['entry'] ?? [ 'resource' => (int) $job->id ];
			$prepared         = ( new Migration_Package_Service() )->prepare( $input['file_id'], $package['count'], $package['entry'] );
			if ( is_wp_error( $prepared ) ) {
				return $prepared;
			}
			if ( ! empty( $prepared['pending'] ) ) {
				$state['stage']               = 'preparing';
				$state['package_preparation'] = $package;
				return $this->save( $job->id, $state, [ 'status' => 'processing' ] );
			}
			unset( $package['entry'] );
			if ( ! hash_equals( $input['sha256'], $prepared['sha256'] ) ) {
				return $this->error( 'package_changed', 409 );
			}
			$requests = $this->requests( $input, $prepared );
			if ( is_wp_error( $requests ) ) {
				return $requests; }
			foreach ( $requests as $request ) {
				Archive_Step_Store::put( $package['request_store'], $package['request_store']['count']++, $request ); }
			++$package['count'];
			if ( $package['count'] < $prepared['total_steps'] ) {
				$state['stage']               = 'preparing';
				$state['package_preparation'] = $package;
				return $this->save(
					$job->id,
					$state,
					[
						'status'     => 'processing',
						'started_at' => $job->started_at ? $job->started_at : current_time( 'mysql' ),
					]
				);
			}
			$state['request_store'] = $package['request_store'];
			unset( $state['package_preparation'] );
			$state['stage']                 = 'importing';
			$state['result']['files_count'] = self::request_count( $state );
			return $this->save(
				$job->id,
				$state,
				[
					'status'      => 'processing',
					'started_at'  => current_time( 'mysql' ),
					'total_items' => self::request_count( $state ),
				]
			);
		}
		if ( 'finalizing' === $state['stage'] ) {
			if ( ! empty( $state['finalizing_child'] ) ) {
				return $this->error( 'uncertain_finalization', 409 ); }
			$ids   = array_values( $state['children'] );
			$index = $state['finalize_index'] ?? 0;
			if ( isset( $ids[ $index ] ) ) {
				$state['finalizing_child'] = $ids[ $index ];
				$saved                     = $this->save( $job->id, $state );
				if ( is_wp_error( $saved ) ) {
					return $saved; }
				$cursor = $state['finalization_cursor'] ?? [];
				$valid  = ( new Import_Service() )->finalize_migration( $ids[ $index ], $state['maps'], $cursor );
				if ( is_wp_error( $valid ) ) {
					return $valid; }
				$state['finalizing_child'] = 0;
				if ( false === $valid ) {
					$state['finalization_cursor'] = $cursor;
				} else {
					unset( $state['finalization_cursor'] );
					$state['finalize_index'] = $index + 1;
				}
				return $this->save( $job->id, $state );
			}
			$state['stage'] = 'done';
			return $this->save(
				$job->id,
				$state,
				[
					'status'       => 'completed',
					'progress'     => 100,
					'completed_at' => current_time( 'mysql' ),
					'result'       => wp_json_encode( $state['result'] ),
				]
			);
		}
		$index = $state['index'];
		$step  = isset( $state['request_store'] ) ? Archive_Step_Store::get( $state['request_store'], $index ) : $state['requests'][ $index ];
		$ready = apply_filters( 'rsl_ie_migration_prepare_import_step', true, $step );
		if ( is_wp_error( $ready ) ) { return $ready; }
		if ( $step['empty'] ) {
			++$state['result']['empty_files'];
		} else {
			if ( empty( $state['children'][ $index ] ) ) {
				$input               = $step['request'];
				$input['request_id'] = 'migration-' . $job->id . '-step-' . $index;
				$child               = ( new Import_Service() )->start( $input, (int) $job->id );
				if ( is_wp_error( $child ) ) {
					return $child; }
				$state['children'][ $index ] = $child['job_id'];
				return $this->save( $job->id, $state );
			}
			$child  = rsl_ie()->Model->job->find( $state['children'][ $index ] );
			$params = $child ? json_decode( $child->parameters, true ) : [];
			if ( ! $child || 'import' !== $child->type || (int) $child->user_id !== (int) $job->user_id || ( $params['migration_parent_id'] ?? 0 ) !== (int) $job->id ) {
				return $this->error( 'child_unavailable', 409 ); }
			if ( in_array( $child->status, [ 'pending', 'processing' ], true ) ) {
				$result = ( new Import_Service() )->process_background_batch( $child->id );
				return is_wp_error( $result ) ? $result : [ 'pending' => true ];
			}
			if ( 'completed' !== $child->status || $child->failed_items > 0 ) {
				return $this->error( 'child_failed', 409 ); }
			foreach ( $params['migration_maps'] ?? [] as $domain => $map ) {
				// Taxonomy maps have a second namespace; all other source IDs share their domain.
				if ( 'terms' === $domain ) {
					foreach ( $map as $tax => $ids ) {
						$state['maps']['terms'][ $tax ] = array_replace( $state['maps']['terms'][ $tax ] ?? [], $ids ); }
				} else {
					$state['maps'][ $domain ] = array_replace( $state['maps'][ $domain ] ?? [], $map ); }
			}
			$counters = $params['cumulative_result'] ?? [];
			foreach ( [ 'success', 'created', 'updated', 'skipped', 'failed', 'replaced_urls' ] as $key ) {
				$state['result'][ $key ] += (int) ( $counters[ $key ] ?? 0 ); }
		}
		++$state['index'];
		$state['stage'] = self::request_count( $state ) === $state['index'] ? 'finalizing' : 'importing';
		return $this->save(
			$job->id,
			$state,
			[
				'processed_items' => $state['index'],
				'success_items'   => $state['index'],
				'progress'        => 95 * $state['index'] / self::request_count( $state ),
				'result'          => wp_json_encode( $state['result'] ),
			]
		);
	}

	/**
	 * Restore ephemeral importer maps from durable checkpoints.
	 *
	 * @param int   $id Child job.
	 * @param array $maps Durable maps.
	 */
	public static function restore_maps( $id, array $maps ) {
		foreach ( [
			'posts'    => 'post',
			'comments' => 'comment',
			'terms'    => 'term',
		] as $domain => $key ) {
			set_transient( 'rsl_ie_import_' . $key . '_id_map_' . $id, $maps[ $domain ] ?? [], DAY_IN_SECONDS );
		}
	}

	/**
	 * Capture identities after each completed row, before importer finalization.
	 *
	 * @param int   $id Child ID.
	 * @param array $maps Existing durable maps.
	 * @param array $request Server request.
	 * @param array $item Prepared row.
	 * @param mixed $result Importer result.
	 * @return array
	 */
	public static function capture_maps( $id, array $maps, array $request, array $item, $result ) {
		foreach ( [
			'posts'    => 'post',
			'comments' => 'comment',
			'terms'    => 'term',
		] as $domain => $key ) {
			$map = get_transient( 'rsl_ie_import_' . $key . '_id_map_' . $id );
			if ( is_array( $map ) ) {
				$maps[ $domain ] = $map; }
		}
		return apply_filters( 'rsl_ie_migration_capture_maps', $maps, $request, $item, $result );
	}

	/**
	 * Resolve cross-step references only from this parent's durable maps.
	 *
	 * @param int   $id Child ID or zero for ordinary validation.
	 * @param array $request Normalized request.
	 * @param array $rows Mapped rows.
	 * @return array|\WP_Error
	 */
	public static function prepare_rows( $id, array $request, array $rows ) {
		$job    = $id ? rsl_ie()->Model->job->find( $id ) : null;
		$params = $job ? json_decode( $job->parameters, true ) : [];
		if ( empty( $params['migration_parent_id'] ) ) {
			return $rows; }
		if ( ! self::running( $params['migration_parent_id'] ) ) {
			return File_Store::error( 'migration_parent_required', 409 ); }
		$maps = $params['migration_seed_maps'] ?? [];
		foreach ( $rows as &$row ) {
			if ( isset( $row['post_author'] ) ) {
				$row['post_author'] = $maps['users'][ absint( $row['post_author'] ) ] ?? get_current_user_id(); }
			if ( 'comment' === $request['import_type'] ) {
				$source = absint( $row['_aie_source_post_id'] ?? $row['source_post_id'] ?? $row['comment_post_ID'] ?? 0 );
				if ( $source ) {
					$target = $maps['posts'][ $source ] ?? 0;
					if ( ! $target ) {
						return File_Store::error( 'migration_missing_post_mapping', 409 ); }
					$access = \RockStarLab\ImportExport\Abilities\Access_Policy::check_content_type(
						'comment',
						'import',
						[
							'records'              => [ [ 'comment_post_ID' => $target ] ],
							'record_ids_are_local' => true,
						]
					);
					if ( is_wp_error( $access ) ) {
						return $access; }
					$row['comment_post_ID'] = $target;
					foreach ( [ '_aie_source_post_id', 'source_post_id', '_aie_source_post_permalink', 'post_permalink', '_aie_source_post_slug', 'post_slug' ] as $key ) {
						unset( $row[ $key ] ); }
				}
				if ( isset( $row['user_id'] ) ) {
					$row['user_id'] = $maps['users'][ absint( $row['user_id'] ) ] ?? 0; }
			}
		}
		unset( $row );
		return apply_filters( 'rsl_ie_migration_prepare_rows', $rows, $request, $maps );
	}
}

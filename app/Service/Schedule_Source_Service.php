<?php
/**
 * Authorize and replay a schedule source through its owning workflow.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Model\Connected_Site;

defined( 'ABSPATH' ) || exit;

/** Never copy a completed worker checkpoint into a new queue job. */
class Schedule_Source_Service {
	/**
	 * Validate or queue a fresh occurrence under the current identity.
	 *
	 * @param int    $id Source job.
	 * @param string $request_id Empty for validation, otherwise occurrence identity.
	 * @return mixed
	 */
	public function execute( $id, $request_id = '' ) {
		$result = $this->replay( $id, $request_id );
		if ( '' !== $request_id && is_array( $result ) && ! empty( $result['job_id'] ) ) {
			$source = rsl_ie()->Model->job->find( $id );
			if ( $source ) {
				rsl_ie()->Model->job->update( $result['job_id'], [ 'job_name' => $source->job_name ] ); }
		}
		return $result;
	}

	/**
	 * Dispatch to a server-selected workflow.
	 *
	 * @param int    $id Source job.
	 * @param string $request_id Occurrence identity.
	 * @return mixed
	 */
	private function replay( $id, $request_id ) {
		$job = ( new Job_Service() )->record( $id );
		if ( is_wp_error( $job ) ) {
			return $job;
		}
		$params = json_decode( (string) $job->parameters, true );
		if ( ! is_array( $params ) || ! empty( $params['migration_parent_id'] ) || ! in_array( $job->status, [ 'completed', 'failed', 'cancelled' ], true ) ) {
			return new \WP_Error( 'rsl_ie_schedule_source_unavailable', 'Select a terminal, independent source job.', [ 'status' => 409 ] );
		}
		$start = '' !== $request_id;
		if ( Media_Folder_Service::supports( $job ) ) {
			$folder    = new Media_Folder_Service();
			$selection = ! empty( $params['all_files'] ) ? $params : json_decode( (string) $job->settings, true );
			return $start ? $folder->restart( $id, $request_id ) : $folder->scan(
				[
					'folder_path' => $selection['folder_path'] ?? '',
					'options'     => $selection['scan_options'] ?? [],
					'limit'       => 1,
				]
			);
		}
		if ( AI_URL_Service::supports( $job ) ) {
			return ( new AI_URL_Service() )->execute( $start ? 'ai-url-import-start' : 'ai-url-prepare', $params['input'] + ( $start ? [ 'request_id' => $request_id ] : [] ), $params['edition'] );
		}
		if ( Sync_Job_Service::supports( $job ) || in_array( $job->type, [ 'content_sync_push', 'content_sync_pull' ], true ) ) {
			$service = new Sync_Job_Service();
			if ( is_array( $params['schedule_replay'] ?? null ) ) {
				$params = $params['schedule_replay'];
				// Pushes started from the admin screens stored their input as form values
				// (string IDs, mapping as a JSON string, no post type); normalize them for the schema.
				if ( is_array( $params['input'] ?? null ) ) {
					$in = $params['input'];
					if ( isset( $in['site_id'] ) ) {
						$in['site_id'] = (int) $in['site_id'];
					}
					if ( isset( $in['post_ids'] ) && is_array( $in['post_ids'] ) ) {
						$in['post_ids'] = array_values( array_map( 'intval', $in['post_ids'] ) );
					}
					if ( isset( $in['post_mapping'] ) && is_string( $in['post_mapping'] ) ) {
						$decoded            = json_decode( $in['post_mapping'], true );
						$in['post_mapping'] = is_array( $decoded ) ? $decoded : [];
					}
					if ( isset( $in['post_mapping'] ) && [] === $in['post_mapping'] ) {
						unset( $in['post_mapping'] );
					}
					if ( empty( $in['post_type'] ) && in_array( $job->data_type, [ 'post', 'page' ], true ) ) {
						$in['post_type'] = $job->data_type;
					}
					$params['input'] = $in;
				}
			} elseif ( ! Sync_Job_Service::supports( $job ) ) {
				$site = Connected_Site::get_by_id( $params['site_id'] ?? 0 );
				if ( ! $site ) {
					return new \WP_Error( 'rsl_ie_schedule_connection_missing', 'The source connection is unavailable.', [ 'status' => 409 ] );
				}
				$params = [
					'sync_queue_version'     => 1,
					'edition'                => in_array( $job->data_type, [ 'post', 'page' ], true ) ? 'free' : 'pro',
					'operation'              => 'content_sync_push' === $job->type ? 'sync-push' : 'sync-pull',
					'input'                  => [
						'site_id'   => (int) $params['site_id'],
						'post_ids'  => $params['post_ids'] ?? [],
						'post_type' => $job->data_type,
					] + ( ! empty( $params['post_mapping'] ) ? [ 'post_mapping' => $params['post_mapping'] ] : [] ),
					'connection_fingerprint' => $service->fingerprint( $site ),
				];
			}
			$valid = $service->preflight( $params );
			return is_wp_error( $valid ) || ! $start ? $valid : $service->execute( $params['operation'] . '-start', $params['input'] + [ 'request_id' => $request_id ], $params['edition'] );
		}
		if ( Job_Service::supports( $job ) ) {
			$input = json_decode( (string) ( $job->request_payload ?? '' ), true );
			$input = is_array( $input ) ? $input : $params;
			unset( $input['request_id'], $input['execution_context'], $input['offset'], $input['prepared_data'], $input['prepared_rows_count'], $input['cumulative_result'] );
			$input['request_id'] = $request_id;
			if ( 'import' === $job->type ) {
				$input['file_path'] = $job->file_path;
				// Imports from a URL fetch the current file on every scheduled run.
				if ( $start && ! empty( $params['source_url'] ) ) {
					$fresh = ( new File_Service() )->from_url( (string) $params['source_url'], $params['parser_options'] ?? [ 'delimiter' => $params['delimiter'] ?? ',', 'has_header' => $params['options']['has_header'] ?? true, 'encoding' => $params['encoding'] ?? 'auto' ] );
					if ( is_wp_error( $fresh ) ) {
						return $fresh;
					}
					$input['file_id']   = $fresh['file_id'];
					$input['file_path'] = 'rsl-ie-file:' . $fresh['file_id'];
				}
				$service            = new Import_Service();
				return $start ? $service->start( $input ) : $service->check_request( $input );
			}
			return $start ? ( new Export_Service() )->start( $input ) : Export_Request::normalize( $input );
		}
		/**
		 * Let PRO replay its own specialized source after authorization.
		 *
		 * @param mixed $result Unhandled result.
		 * @param object $job Trusted source.
		 * @param string $request_id Occurrence identity or empty preflight.
		 */
		$result = apply_filters( 'rsl_ie_schedule_source', null, $job, $request_id );
		return null !== $result ? $result : new \WP_Error( 'rsl_ie_schedule_provider_unavailable', 'No available schedule provider supports this source.', [ 'status' => 403 ] );
	}
}

<?php
/**
 * Typed, credential-free output adapter for connection and sync abilities.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Sync_Catalog;
use RockStarLab\ImportExport\Abilities\Sync_Policy;

defined( 'ABSPATH' ) || exit;

/** Each invocation uses a new service instance and a server-selected operation. */
class Sync_Workflow_Service {
	/**
	 * Validate and execute one implemented ability.
	 *
	 * @param string $name Ability suffix.
	 * @param array  $input Typed input.
	 * @return array|\WP_Error
	 */
	public function execute( $name, array $input = [] ) {
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		$definitions = $this->definitions();
		if ( ! isset( $definitions[ $name ] ) ) {
			return new \WP_Error( 'rsl_ie_unknown_sync', __( 'Unknown sync ability.', 'import-export-by-rockstarlab' ), [ 'status' => 404 ] );
		}
		$definition = $definitions[ $name ];
		if ( 'standalone' === ( $definition['handler'] ?? '' ) ) {
			return ( new \RockStarLab\ImportExport\Service\Standalone_Sync_Workflow() )->execute( $definition, $input ); }
		$valid = rest_validate_value_from_schema( $input, $definition['input'], 'input' );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$input = rest_sanitize_value_from_schema( $input, $definition['input'], 'input' );
		if ( is_wp_error( $input ) ) {
			return $input;
		}
		foreach ( (array) ( $input['post_mapping'] ?? [] ) as $local => $remote ) {
			if ( ! preg_match( '/^[1-9][0-9]*$/D', (string) $local ) ) {
				return Sync_Policy::denied();
			}
		}
		$is_connection = 0 === strpos( $name, 'connection-' );
		$service       = $is_connection ? new Connection_Service() : $this->content_service();
		$result        = 'sync-result' === $name ? $this->sync_result( $input['job_id'] ) : $service->execute( $definition['action'], $input );
		if ( is_wp_error( $result ) ) {
			// Remote error text is untrusted and may echo a bearer key or payload.
			return new \WP_Error( $result->get_error_code(), __( 'The sync operation failed. Check connection status, selection and job history.', 'import-export-by-rockstarlab' ), [ 'status' => $result->get_error_data()['status'] ?? 400 ] + array_intersect_key( (array) $result->get_error_data(), [ 'job_id' => true ] ) );
		}
		if ( 'connection-list' === $name ) {
			return [
				'sites' => array_map( [ $this, 'connection' ], array_slice( $result['sites'], $input['offset'] ?? 0, $input['limit'] ?? 100 ) ),
				'total' => count( $result['sites'] ),
			];
		}
		if ( isset( $result['site'] ) ) {
			$result['site'] = $this->connection( $result['site'] );
		}
		foreach ( [ 'posts', 'children' ] as $key ) {
			if ( isset( $result[ $key ] ) ) {
				if ( ! is_array( $result[ $key ] ) || count( $result[ $key ] ) > 1000 ) {
					return Sync_Policy::denied();
				}
				$fields = [ 'ID', 'post_title', 'post_name', 'post_type', 'post_status', 'post_date', 'post_modified', 'post_parent', 'menu_order', 'children_count', 'original_id' ];
				foreach ( $result[ $key ] as &$post ) {
					if ( ! is_array( $post ) || ( $post['post_type'] ?? '' ) !== $input['post_type'] ) {
						return Sync_Policy::denied();
					}
					if ( 'sync-search-local' === $name && is_wp_error( $this->local_result( (int) $post['ID'], $input['post_type'] ) ) ) {
						return Sync_Policy::denied();
					}
					$post = array_intersect_key( $post, array_flip( $fields ) );
					foreach ( [ 'ID', 'post_parent', 'menu_order', 'children_count', 'original_id' ] as $field ) {
						if ( isset( $post[ $field ] ) ) {
							$post[ $field ] = (int) $post[ $field ];
						}
					}
				}
				unset( $post );
			}
		}
		// Core serializes empty PHP maps as arrays; use JSON objects consistently.
		foreach ( [ 'mapping', 'matches', 'status_counts' ] as $field ) {
			if ( isset( $result[ $field ] ) ) {
				$result[ $field ] = (object) $result[ $field ];
			}
		}
		if ( isset( $result['result'] ) ) {
			foreach ( [ 'post_mapping', 'image_map', 'variation_map' ] as $field ) {
				$result['result'][ $field ] = (object) ( $result['result'][ $field ] ?? [] );
			}
		}
		return array_intersect_key( $result, $definition['output']['properties'] );
	}

	/**
	 * Authorize a concrete local search result.
	 *
	 * @param int    $id Local ID.
	 * @param string $type Expected type.
	 * @return true|\WP_Error
	 */
	protected function local_result( $id, $type ) {
		return Sync_Policy::local_post( $id, $type ); }

	/**
	 * Return the provider synchronization contract.
	 *
	 * @return array
	 */
	protected function definitions() {
		return Sync_Catalog::definitions(); }
	/**
	 * Return the provider synchronization contract.
	 *
	 * @return Content_Sync_Service
	 */
	protected function content_service() {
		return new Content_Sync_Service( true ); }
	/**
	 * Return the provider synchronization contract.
	 *
	 * @return int
	 */
	protected function result_version() {
		return 1; }
	/**
	 * Check the provider of a stored result.
	 *
	 * @param string $type Content type.
	 * @return bool
	 */
	protected function result_type( $type ) {
		return in_array( $type, [ 'post', 'page' ], true ); }

	/**
	 * Retrieve only the bounded, safe result written by this Free workflow.
	 *
	 * @param int $id History job ID.
	 * @return array|\WP_Error
	 */
	private function sync_result( $id ) {
		$job = ( new Job_Service() )->record( $id );
		if ( is_wp_error( $job ) ) {
			return $job;
		}
		$params = json_decode( (string) $job->parameters, true );
		if ( ! in_array( $job->type, [ 'content_sync_push', 'content_sync_pull' ], true ) || ! $this->result_type( $job->data_type ) || $this->result_version() !== ( $params['sync_ability_version'] ?? 0 ) ) {
			return Sync_Policy::denied();
		}
		$result = json_decode( (string) $job->result, true );
		$fields = [ 'direction', 'selected_ids', 'post_mapping', 'created', 'updated', 'failed', 'images_synced', 'image_map', 'variation_map', 'posts' ];
		return [
			'job_id'  => (int) $id,
			'message' => (string) $job->status,
			'result'  => array_intersect_key( (array) $result, array_flip( $fields ) ),
		];
	}

	/**
	 * Public connection DTO; never return stored credentials or raw errors.
	 *
	 * @param array $site Stored connection.
	 * @return array
	 */
	private function connection( array $site ) {
		$result               = array_intersect_key( $site, array_flip( [ 'id', 'name', 'remote_url', 'direction', 'status', 'created_by', 'created_at', 'updated_at', 'last_sync_at' ] ) );
		$result['id']         = (int) $result['id'];
		$result['created_by'] = (int) ( $result['created_by'] ?? 0 );
		$parts                = wp_parse_url( $result['remote_url'] );
		$result['remote_url'] = ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? '' ) . ( isset( $parts['port'] ) ? ':' . $parts['port'] : '' ) . ( $parts['path'] ?? '' );
		return $result;
	}
}

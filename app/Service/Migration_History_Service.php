<?php
/**
 * Remove terminal migration history while retaining reusable artifacts and selections.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Graph validation and atomic history deletion bypass model caches.

/** History-only deletion; never invokes a content or file deletion runner. */
class Migration_History_Service {
	/**
	 * Whether this record is a deletable migration root kind.
	 *
	 * @param object $job Stored record.
	 * @return bool
	 */
	public static function supports( $job ) {
		return Migration_Service::supports( $job ) && 'migration_sources' !== $job->type && empty( json_decode( $job->parameters, true )['migration_parent_id'] );
	}

	/**
	 * Delete owned terminal workers. Caller must hold the root worker lock.
	 *
	 * @param object $root Authorized, locked root.
	 * @return array|\WP_Error
	 */
	public function delete( $root ) {
		global $wpdb;
		$locks = [];
		$jobs  = [ (int) $root->id => $root ];
		try {
			// Inspect persisted ownership, not the parent's potentially incomplete checkpoint.
			do {
				$changed = false;
				$after   = 0;
				do {
					$candidates = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE id > %d AND parameters LIKE %s ORDER BY id ASC LIMIT 100', $wpdb->prefix . 'rsl_ie_jobs', $after, '%"migration_parent_id"%' ) );
					if ( $wpdb->last_error || ! is_array( $candidates ) ) {
						return $this->error( 'migration_history_scan_failed', 503 );
					}
					$page_count = count( $candidates );
					foreach ( $candidates as $candidate ) {
						$after  = (int) $candidate->id;
						$params = json_decode( $candidate->parameters, true );
						if ( isset( $jobs[ (int) ( $params['migration_parent_id'] ?? 0 ) ] ) && ! isset( $jobs[ $after ] ) ) {
							$jobs[ $after ] = $candidate;
							$changed        = true;
						}
					}
				} while ( 100 === $page_count );
				// Imported/restored history can have descendants ordered before their parent.
			} while ( $changed );
			ksort( $jobs );
			foreach ( $jobs as $id => $job ) {
				if ( $id !== (int) $root->id ) {
					$lock = Job_Lock::acquire( $id );
					if ( ! $lock || is_wp_error( $lock ) ) {
						return is_wp_error( $lock ) ? $lock : $this->error( 'job_busy', 409 );
					}
					$locks[] = $lock;
				}
				$job = ( new Job_Service() )->record( $id );
				if ( is_wp_error( $job ) ) {
					return $job;
				}
				$state = json_decode( $job->settings, true );
				if ( (int) $job->user_id !== (int) $root->user_id || ! in_array( $job->status, [ 'completed', 'failed', 'cancelled' ], true ) || ! empty( $state['writing'] ) || ! empty( $state['uncertain'] ) || 'transferring' === ( $state['stage'] ?? '' ) ) {
					return $this->error( 'job_state_conflict', 409 );
				}
				if ( rsl_ie()->Model->job_schedule->find_by(
					[
						'source_job_id' => $id,
						'status'        => 'active',
					]
				) ) {
					return $this->error( 'job_has_active_schedule', 409 );
				}
				$jobs[ $id ] = $job;
			}
			// Saved selections and their descendants remain reusable by other migrations.
			$retained = [];
			do {
				$changed = false;
				foreach ( $jobs as $id => $job ) {
					$params = json_decode( $job->parameters, true );
					if ( ! isset( $retained[ $id ] ) && ( 'migration_sources' === $job->type || isset( $retained[ (int) ( $params['migration_parent_id'] ?? 0 ) ] ) ) ) {
						$retained[ $id ] = true;
						$changed         = true;
					}
				}
			} while ( $changed );
			$jobs = array_diff_key( $jobs, $retained );
			// Preserve the export policy independently of history before removing its rows.
			foreach ( $jobs as $id => $job ) {
				if ( ! in_array( $job->type, [ 'export', 'migration_export' ], true ) || 'completed' !== $job->status ) {
					continue;
				}
				$kind    = 'export' === $job->type ? 'export:' : 'migration:';
				$file_id = 'file_' . md5( $kind . get_current_blog_id() . ':' . $id );
				$file    = File_Store::get( $file_id );
				if ( is_wp_error( $file ) ) {
					continue;
				}
				$lock = Job_Lock::acquire_resource( 'file', $file_id );
				if ( ! $lock || is_wp_error( $lock ) ) {
					return is_wp_error( $lock ) ? $lock : $this->error( 'file_busy', 409 );
				}
				$locks[] = $lock;
				$file    = ( new File_Service() )->resolve( $file_id, 'export' );
				if ( is_wp_error( $file ) ) {
					return $file;
				}
				if ( (int) $file['job_id'] !== $id || (int) $file['owner_id'] !== (int) $job->user_id ) {
					return $this->error( 'invalid_context', 403 );
				}
				$params   = json_decode( $job->parameters, true );
				$state    = json_decode( $job->settings, true );
				$requests = 'export' === $job->type ? [ $params ] : array_column( $state['requests'] ?? [], 'request' );
				if ( ! $requests ) {
					return $this->error( 'migration_history_export_policy', 409 );
				}
				$file['retained_export_requests'] = $requests;
				$saved                            = File_Store::save( $file );
				if ( is_wp_error( $saved ) ) {
					return $saved;
				}
			}
			// Refuse nontransactional tables rather than partially deleting a graph.
			foreach ( [ 'rsl_ie_jobs', 'rsl_ie_job_logs' ] as $suffix ) {
				$engine = $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = %s', $wpdb->prefix . $suffix ) );
				if ( 'innodb' !== strtolower( (string) $engine ) ) {
					return $this->error( 'migration_history_storage', 503 );
				}
			}
			foreach ( $jobs as $id => $job ) {
				if ( ! empty( $job->request_key ) ) {
					$name = 'rsl_ie_job_request_' . $job->request_key;
					if ( ! add_option(
						$name,
						[
							'job_id' => $id,
							'hash'   => $job->request_hash,
						],
						'',
						false
					) && ! get_option( $name ) ) {
						return $this->error( 'job_write_failed', 500 );
					}
				}
			}
			if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
				return $this->error( 'job_write_failed', 500 );
			}
			foreach ( $jobs as $id => $job ) {
				if ( 1 !== $wpdb->delete( $wpdb->prefix . 'rsl_ie_jobs', [ 'id' => $id ], [ '%d' ] ) || false === $wpdb->delete( $wpdb->prefix . 'rsl_ie_job_logs', [ 'job_id' => $id ], [ '%d' ] ) ) {
					$wpdb->query( 'ROLLBACK' );
					return $this->error( 'job_write_failed', 500 );
				}
			}
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				$wpdb->query( 'ROLLBACK' );
				return $this->error( 'job_write_failed', 500 );
			}
			foreach ( array_keys( $jobs ) as $id ) {
				wp_clear_scheduled_hook( 'rsl_ie_run_job', [ $id ] );
			}
			return [
				'job_id'  => (int) $root->id,
				'deleted' => true,
			];
		} finally {
			foreach ( array_reverse( $locks ) as $lock ) {
				Job_Lock::release( $lock );
			}
		}
	}

	/**
	 * Stable transport failure.
	 *
	 * @param string $code Failure code.
	 * @param int    $status HTTP status.
	 * @return \WP_Error
	 */
	private function error( $code, $status ) {
		return new \WP_Error( 'rsl_ie_' . $code, 'Migration history operation failed: ' . $code . '.', [ 'status' => $status ] );
	}
}

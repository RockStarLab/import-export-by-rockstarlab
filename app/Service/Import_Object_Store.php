<?php
/**
 * Durable first-key order / last-value index for ordinary JSON object imports.
 *
 * @package RockStarLab\ImportExport
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;
// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Transactional job-owned staging must read authoritative uncached rows.
// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.serialize_serialize -- Only JSON-decoded arrays/scalars are encoded; nothing is unserialized.

/** Persist bounded member slices before making them available for import. */
class Import_Object_Store {
	/**
	 * Check transactional storage support.
	 *
	 * @return bool Whether required storage tables support transactions.
	 */
	public static function supported() {
		global $wpdb;
		return Import_Row_Store::supports_checkpoints() && 'innodb' === strtolower( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $wpdb->prefix . 'rsl_ie_import_objects' ) ) );
	}
	/**
	 * Index a bounded member slice and publish its parser position atomically.
	 *
	 * Caller holds the job lock.
	 *
	 * @param object $job Stored job.
	 * @param array  $request Normalized request.
	 * @param array  $parameters Mutable job parameters.
	 * @return bool|\WP_Error Publication result.
	 */
	public static function advance( $job, array $request, array &$parameters ) {
		global $wpdb;
		if ( ! self::supported() ) {
			return self::error();
		}
		$state = $parameters['json_object_index'] ?? array(
			'cursor' => null,
			'count'  => 0,
			'done'   => false,
		);
		if ( $state['done'] ) {
			return true;
		}
		if ( ! isset( $parameters['json_object_index'] ) ) {
			$state['source_sha256'] = hash_file( 'sha256', $request['file_path'] );
			if ( ! is_string( $state['source_sha256'] ) ) {
				return self::error();
			}
		}
		$cursor  = $state['cursor'];
		$members = array();
		$bytes   = 0;
		$done    = true;
		$reader  = \RockStarLab\ImportExport\Model\Format\JSON_Row_Stream::rows( $request['file_path'], array( 'object_members' => true ), $cursor );
		try {
			foreach ( $reader as $member ) {
				$members[] = $member;
				$bytes    += strlen( serialize( $member ) );
				if ( count( $members ) >= 100 || $bytes >= 1048576 ) {
					$done = false;
					break;
				}
			}
		} finally {
			unset( $reader );
		}
		if ( $done && isset( $state['source_sha256'] ) && ( ! is_string( $state['source_sha256'] ) || ! hash_equals( $state['source_sha256'], (string) hash_file( 'sha256', $request['file_path'] ) ) ) ) {
			return self::error();
		}
		$table = $wpdb->prefix . 'rsl_ie_import_objects';
		$jobs  = $wpdb->prefix . 'rsl_ie_jobs';
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return self::error();
		}
		$committed = false;
		try {
			$fresh = $wpdb->get_row( $wpdb->prepare( 'SELECT parameters,status FROM %i WHERE id=%d FOR UPDATE', $jobs, $job->id ) );
			if ( ! $fresh || $fresh->parameters !== $job->parameters || ! in_array( $fresh->status, array( 'pending', 'processing' ), true ) ) {
				return self::error();
			}
			$bounds = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) AS total,MIN(ordinal) AS first_row,MAX(ordinal) AS last_row FROM %i WHERE job_id=%d', $table, $job->id ) );
			if ( ! $bounds || (int) $bounds->total !== $state['count'] || ( $state['count'] && ( 0 !== (int) $bounds->first_row || $state['count'] - 1 !== (int) $bounds->last_row ) ) ) {
				return self::error();
			}
			foreach ( $members as $member ) {
				$key      = array_key_first( $member );
				$hash     = hash( 'sha256', serialize( $key ) );
				$existing = $wpdb->get_row( $wpdb->prepare( 'SELECT ordinal,digest,payload FROM %i WHERE job_id=%d AND key_hash=%s', $table, $job->id, $hash ) );
				if ( $wpdb->last_error ) {
					return self::error();
				}
				if ( $existing ) {
					$decoded = self::decode( $existing );
					if ( is_wp_error( $decoded ) || $decoded['key'] !== $key ) {
									return self::error();
					}
					$ordinal = (int) $existing->ordinal;
				} else {
							$ordinal = $state['count']++;
				}
				// Explicit key field retains integer versus string key semantics after JSON encoding.
				$payload = wp_json_encode(
					array(
						'key'   => $key,
						'value' => $member[ $key ],
					)
				);
				if ( false === $payload ) {
								return self::error();
				}
				$saved = $wpdb->query( $wpdb->prepare( 'INSERT INTO %i (job_id,ordinal,key_hash,digest,payload) VALUES (%d,%d,%s,%s,%s) ON DUPLICATE KEY UPDATE digest=VALUES(digest),payload=VALUES(payload)', $table, $job->id, $ordinal, $hash, hash( 'sha256', $payload ), $payload ) );
				if ( false === $saved ) {
					return self::error();
				}
			}
			$state['cursor']           = $cursor;
			$state['done']             = $done;
			$next                      = $parameters;
			$next['json_object_index'] = $state;
			$json                      = wp_json_encode( $next );
			if ( false === $json || false === $wpdb->update( $jobs, array( 'parameters' => $json ), array( 'id' => $job->id ), array( '%s' ), array( '%d' ) ) ) {
				return self::error();
			}
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				return self::error();
			}
			$committed  = true;
			$parameters = $next;
			return true;
		} finally {
			if ( ! $committed ) {
				$wpdb->query( 'ROLLBACK' );
			}
		}
	}
	/**
	 * Index a slice of the private normalized workbook snapshot.
	 *
	 * Initial workbook parsing is synchronous. Caller holds the import job lock.
	 *
	 * @param object $job Stored job.
	 * @param array  $request Normalized request.
	 * @param array  $parameters Mutable job parameters.
	 * @return bool|\WP_Error
	 */
	public static function stage_spreadsheet( $job, array $request, array &$parameters ) {
		global $wpdb;
		if ( ! self::supported() || ! in_array( $request['format'], [ 'xlsx', 'ods' ], true ) ) {
			return self::error();
		}
		$table     = $wpdb->prefix . 'rsl_ie_import_objects';
		$jobs      = $wpdb->prefix . 'rsl_ie_jobs';
		$committed = false;
		$started   = false;
		$reader    = null;
		try {
			$state = $parameters['spreadsheet_index'] ?? Spreadsheet_Spool::create( $job->id, $request );
			if ( isset( $state['build'] ) ) {
				$state = Spreadsheet_Spool::advance( $job->id, $request, $state ); }
			$batch  = [];
			$bytes  = 0;
			$done   = false;
			$cursor = $state['cursor'];
			$digest = $state['read_sha256'];
			if ( ! isset( $state['build'] ) ) {
				$path = Spreadsheet_Spool::path( $job->id );
				if ( ! is_file( $path ) ) {
					return self::error(); }
				$reader = \RockStarLab\ImportExport\Model\Format\JSON_Row_Stream::rows( $path, [], $cursor );
				$done   = true;
				foreach ( $reader as $row ) {
					$digest  = hash( 'sha256', $digest . wp_json_encode( $row ) );
					$batch[] = $row;
					$bytes  += strlen( serialize( $row ) );
					if ( count( $batch ) >= 100 || $bytes >= 1048576 ) {
						$done = false;
						break; }
				}
				unset( $reader );
				if ( $done && ! hash_equals( $state['rows_sha256'], $digest ) ) {
					return self::error(); }
			}

			if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
				return self::error();
			}
			$started = true;
			$fresh   = $wpdb->get_row( $wpdb->prepare( 'SELECT parameters,status FROM %i WHERE id=%d FOR UPDATE', $jobs, $job->id ) );
			if ( ! $fresh || $fresh->parameters !== $job->parameters || ! in_array( $fresh->status, [ 'pending', 'processing' ], true ) ) {
				return self::error();
			}
			$count  = $state['count'];
			$stored = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) AS total,MIN(ordinal) AS first_row,MAX(ordinal) AS last_row FROM %i WHERE job_id=%d', $table, $job->id ) );
			if ( ! $stored || (int) $stored->total !== $count || ( $count && ( 0 !== (int) $stored->first_row || (int) $stored->last_row !== $count - 1 ) ) ) {
				return self::error();
			}
			foreach ( $batch as $row ) {
				$payload = wp_json_encode(
					[
						'key'   => $count,
						'value' => $row,
					]
				);
				if ( ! is_array( $row ) || false === $payload || false === $wpdb->insert(
					$table,
					[
						'job_id'   => $job->id,
						'ordinal'  => $count,
						'key_hash' => hash( 'sha256', serialize( $count ) ),
						'digest'   => hash( 'sha256', $payload ),
						'payload'  => $payload,
					],
					[ '%d', '%d', '%s', '%s', '%s' ]
				) ) {
					return self::error();
				}
				++$count;
			}
			$state['read_sha256']      = $digest;
			$state['count']            = $count;
			$state['cursor']           = $cursor;
			$state['done']             = $done;
			$next                      = $parameters;
			$next['spreadsheet_index'] = $state;
			$json                      = wp_json_encode( $next );
			if ( false === $json || false === $wpdb->update( $jobs, [ 'parameters' => $json ], [ 'id' => $job->id ], [ '%s' ], [ '%d' ] ) || false === $wpdb->query( 'COMMIT' ) ) {
				return self::error();
			}
			$committed  = true;
			$parameters = $next;
			if ( $done ) {
				Spreadsheet_Spool::discard( $job->id );
			}
			return true;
		} finally {
			if ( $started && ! $committed ) {
				$wpdb->query( 'ROLLBACK' );
			}
			unset( $reader );
			if ( ! $committed && ! isset( $parameters['spreadsheet_index'] ) ) {
				Spreadsheet_Spool::discard( $job->id );
			}
		}
	}

	/**
	 * Reclaim at most 100 raw and prepared rows from an irreversibly cancelled import.
	 *
	 * Failed and paused jobs retain their index for retry/resume.
	 *
	 * @param int $job_id Optional cancelled job; zero discovers the next one.
	 * @return int|false Deleted row count or database failure.
	 */
	public static function cleanup_cancelled( $job_id = 0 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'rsl_ie_import_objects';
		$jobs  = $wpdb->prefix . 'rsl_ie_jobs';
		if ( ! $job_id ) {
			$job_id = (int) $wpdb->get_var( $wpdb->prepare( "SELECT j.id FROM %i j WHERE j.type='import' AND j.status='cancelled' AND (EXISTS (SELECT 1 FROM %i r WHERE r.job_id=j.id) OR EXISTS (SELECT 1 FROM %i p WHERE p.job_id=j.id) OR (j.parameters LIKE %s AND j.parameters LIKE %s)) ORDER BY j.id LIMIT 1", $jobs, $table, $wpdb->prefix . 'rsl_ie_import_rows', '%"spreadsheet_index":%', '%"build":%' ) );
		}
		if ( ! $job_id ) {
			return 0;
		}
		// A cancelled worker may still be finishing its current row or transaction.
		$lock = Job_Lock::acquire( $job_id );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return 0;
		}
		try {
			$status = $wpdb->get_var( $wpdb->prepare( "SELECT status FROM %i WHERE id=%d AND type='import'", $jobs, $job_id ) );
			if ( 'cancelled' === $status && ! Spreadsheet_Spool::discard( $job_id ) ) {
				return false;
			}
			// Recheck status in the mutation; active/paused/failed checkpoints must survive.
			$deleted = 0;
			foreach ( [ $table, $wpdb->prefix . 'rsl_ie_import_rows' ] as $storage ) {
				$removed = $wpdb->query( $wpdb->prepare( "DELETE FROM %i WHERE job_id=%d AND EXISTS (SELECT 1 FROM %i j WHERE j.id=%d AND j.type='import' AND j.status='cancelled') LIMIT %d", $storage, $job_id, $jobs, $job_id, 100 - $deleted ) );
				if ( false === $removed ) {
					return false;
				}
				$deleted += $removed;
				if ( 100 === $deleted ) {
					break;
				}
			}
			if ( 'cancelled' === $status ) {
				$job    = rsl_ie()->Model->job->find( $job_id );
				$params = json_decode( $job->parameters, true );
				if ( isset( $params['spreadsheet_index']['build'] ) ) {
					unset( $params['spreadsheet_index']['build'] );
					if ( false === $wpdb->update(
						$jobs,
						[ 'parameters' => wp_json_encode( $params ) ],
						[
							'id'     => $job_id,
							'status' => 'cancelled',
						]
					) ) {
						return false; }
				}
			}
			return $deleted;
		} finally {
			Job_Lock::release( $lock );
		}
	}

	/**
	 * Verify an indexed member payload.
	 *
	 * @param object $row Stored member.
	 * @return array|\WP_Error Decoded member or integrity error.
	 */
	private static function decode( $row ) {
		if ( ! is_string( $row->payload ) || ! hash_equals( $row->digest, hash( 'sha256', $row->payload ) ) ) {
			return self::error();
		}
		$value = json_decode( $row->payload, true );
		return is_array( $value ) && array_key_exists( 'key', $value ) && array_key_exists( 'value', $value ) ? $value : self::error();
	}
	/**
	 * Stream indexed values in first-occurrence order after indexing reaches EOF.
	 *
	 * @param int      $job_id Owning job.
	 * @param int      $count Published member count.
	 * @param int|null $cursor Next ordinal.
	 * @return \Generator
	 * @throws \RuntimeException On invalid cursor or changed storage.
	 */
	public static function rows( $job_id, $count, &$cursor ) {
		global $wpdb;
		$offset = $cursor ?? 0;
		if ( ! is_int( $offset ) || $offset < 0 || $offset > $count ) {
			throw new \RuntimeException( 'Invalid object cursor.' );
		}
		for ( ;$offset < $count;++$offset ) {
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT key_hash,digest,payload FROM %i WHERE job_id=%d AND ordinal=%d', $wpdb->prefix . 'rsl_ie_import_objects', $job_id, $offset ) );
			if ( ! $row ) {
				throw new \RuntimeException( 'Missing indexed object member.' );
			}
			$value = self::decode( $row );
			if ( is_wp_error( $value ) || ! hash_equals( $row->key_hash, hash( 'sha256', serialize( $value['key'] ) ) ) ) {
				throw new \RuntimeException( 'Changed indexed object member.' );
			}
			$cursor = $offset + 1;
			yield $value['value'];
		}
	}
	/**
	 * Report an unavailable staging index.
	 *
	 * @return \WP_Error Storage failure.
	 */
	private static function error() {
		return new \WP_Error( 'rsl_ie_object_index_unavailable', 'Import preparation index is unavailable or changed.', array( 'status' => 409 ) );
	}
}

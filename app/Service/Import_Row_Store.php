<?php
/** Prepared import rows, published by a compact job descriptor. */
namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;
class Import_Row_Store {
	/** Replace unpublished rows while the import job lock is held. */
	public static function reset( $job_id ) {
		global $wpdb;
		return false !== $wpdb->delete( $wpdb->prefix . 'rsl_ie_import_rows', [ 'job_id' => $job_id ], [ '%d' ] ) ? true : self::error();
	}
	/** Store a prepared slice before publishing its count. */
	public static function append( $job_id, $offset, array $rows ) {
		global $wpdb;
		foreach ( $rows as $row ) {
			$json = wp_json_encode( $row );
			if ( false === $json || ! is_array( $row ) ) {
				return self::error(); }
			if ( false === $wpdb->insert(
				$wpdb->prefix . 'rsl_ie_import_rows',
				[
					'job_id'  => $job_id,
					'ordinal' => $offset++,
					'digest'  => hash( 'sha256', $json ),
					'payload' => $json,
				],
				[ '%d', '%d', '%s', '%s' ]
			) ) {
				return self::error(); }
		}
		return true;
	}
	/** Preserve the legacy preparation path on nontransactional installations. */
	public static function supports_checkpoints() {
		global $wpdb;
		$engines = $wpdb->get_col( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN (%s,%s)', $wpdb->prefix . 'rsl_ie_jobs', $wpdb->prefix . 'rsl_ie_import_rows' ) );
		return count( $engines ) === 2 && ! array_filter(
			$engines,
			static function ( $engine ) {
				return 'innodb' !== strtolower( $engine );
			}
		);
	}

	/** Atomically publish a preparation slice and its server-owned cursor under the job lock. */
	public static function append_checkpoint( $job_id, $offset, array $rows, $expected_parameters, array $parameters ) {
		global $wpdb;
		$jobs    = $wpdb->prefix . 'rsl_ie_jobs';
		$storage = $wpdb->prefix . 'rsl_ie_import_rows';
		$json    = wp_json_encode( $parameters );
		if ( ! is_int( $offset ) || $offset < 0 || ! is_string( $expected_parameters ) || false === $json ) {
			return self::error(); }
		// Do not claim atomic publication on installations using nontransactional tables.
		if ( ! self::supports_checkpoints() ) {
			return self::error(); }
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return self::error(); }
		$committed = false;
		try {
			$job = $wpdb->get_row( $wpdb->prepare( 'SELECT parameters,status FROM %i WHERE id=%d FOR UPDATE', $jobs, $job_id ) );
			if ( ! $job || ! in_array( $job->status, [ 'pending', 'processing' ], true ) || $job->parameters !== $expected_parameters ) {
				return self::error(); }
			$bounds = $wpdb->get_row( $wpdb->prepare( 'SELECT COUNT(*) AS count_rows,MIN(ordinal) AS first_row,MAX(ordinal) AS last_row FROM %i WHERE job_id=%d', $storage, $job_id ) );
			if ( ! $bounds || (int) $bounds->count_rows !== $offset || ( $offset && ( 0 !== (int) $bounds->first_row || $offset - 1 !== (int) $bounds->last_row ) ) ) {
				return self::error(); }
			$saved = self::append( $job_id, $offset, $rows );
			if ( is_wp_error( $saved ) ) {
				return $saved; }
			if ( false === $wpdb->update( $jobs, [ 'parameters' => $json ], [ 'id' => $job_id ], [ '%s' ], [ '%d' ] ) ) {
				return self::error(); }
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				return self::error(); }
			$committed = true;
			return true;
		} finally {
			if ( ! $committed ) {
				$wpdb->query( 'ROLLBACK' ); }
		}
	}

	/** Read only requested rows, requiring contiguous identities and valid bytes. */
	public static function read( $job_id, $count, $offset, $limit ) {
		if ( ! is_int( $count ) || $count < 0 || $offset < 0 || $limit < 0 ) {
			return self::error(); }
		global $wpdb;
		$expected = min( $limit, max( 0, $count - $offset ) );
		if ( ! $expected ) {
			return []; }
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT ordinal,digest,payload FROM %i WHERE job_id=%d AND ordinal>=%d AND ordinal<%d ORDER BY ordinal', $wpdb->prefix . 'rsl_ie_import_rows', $job_id, $offset, $offset + $expected ), ARRAY_A );
		if ( $wpdb->last_error || count( $rows ) !== $expected ) {
			return self::error(); }
		$result = [];
		foreach ( $rows as $row ) {
			if ( (int) $row['ordinal'] !== $offset++ || ! hash_equals( $row['digest'], hash( 'sha256', $row['payload'] ) ) ) {
				return self::error(); }
			$value = json_decode( $row['payload'], true );
			if ( ! is_array( $value ) ) {
				return self::error(); }
			$result[] = $value;
		}
		return $result;
	}
	/** Bound worker payload allocation while always allowing one complete record. */
	public static function read_batch( $job_id, $count, $offset, $limit ) {
		if ( ! is_int( $count ) || $count < 0 || ! is_int( $offset ) || $offset < 0 || ! is_int( $limit ) || $limit < 1 || $limit > 500 ) {
			return self::error(); }
		$expected = min( $limit, max( 0, $count - $offset ) );
		if ( ! $expected ) {
			return []; }
		global $wpdb;
		$sizes = $wpdb->get_results( $wpdb->prepare( 'SELECT ordinal,OCTET_LENGTH(payload) AS bytes FROM %i WHERE job_id=%d AND ordinal>=%d AND ordinal<%d ORDER BY ordinal', $wpdb->prefix . 'rsl_ie_import_rows', $job_id, $offset, $offset + $expected ), ARRAY_A );
		if ( $wpdb->last_error || count( $sizes ) !== $expected ) {
			return self::error(); }
		$bytes = 0;
		$take  = 0;
		foreach ( $sizes as $size ) {
			if ( (int) $size['ordinal'] !== $offset + $take ) {
				return self::error(); }
			if ( $take && $bytes + (int) $size['bytes'] > 1048576 ) {
				break; }
			$bytes += (int) $size['bytes'];
			++$take;
		}
		return self::read( $job_id, $count, $offset, $take );
	}

	public static function all( $job_id, $count ) {
		$result = [];
		for ( $offset = 0; $offset < $count; $offset += 100 ) {
			$rows = self::read( $job_id, $count, $offset, 100 );
			if ( is_wp_error( $rows ) ) {
				return $rows; }
			foreach ( $rows as $row ) {
				$result[] = $row; }
		}
		return $result;
	}
	private static function error() {
		return new \WP_Error( 'rsl_ie_import_rows_unavailable', 'Prepared import rows are unavailable or changed.', [ 'status' => 409 ] );
	}
}

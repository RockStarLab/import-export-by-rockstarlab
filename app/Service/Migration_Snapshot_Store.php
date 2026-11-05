<?php
/** Graph batches become visible to a job when their reference is checkpointed. */
namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;
class Migration_Snapshot_Store {

	/** Write a graph batch or prepared source plan before publishing its checkpoint reference. */
	public static function put( $job_id, $family, $index, array $records ) {
		if ( ! is_numeric( $job_id ) || (int) $job_id < 1 || ! is_int( $index ) || $index < 0 || ! in_array( $family, array( 'acf', 'woo', 'woo_rows', 'woo_map', 'sources' ), true ) ) {
			return self::error();
		}
		global $wpdb;
		$json = wp_json_encode( $records );
		if ( false === $json ) {
			return self::error();
		}
		$hash   = hash( 'sha256', $json );
		$table  = $wpdb->prefix . 'rsl_ie_migration_snapshots';
		$update = in_array( $family, [ 'woo_rows', 'woo_map' ], true ) ? 'digest=digest' : 'digest=VALUES(digest),payload=VALUES(payload)';
		$saved  = $wpdb->query( $wpdb->prepare( 'INSERT INTO %i (job_id,family,batch_index,digest,payload) VALUES (%d,%s,%d,%s,%s) ON DUPLICATE KEY UPDATE ' . $update, $table, $job_id, $family, $index, $hash, $json ) );
		if ( false === $saved ) {
			return self::error();
		}
		if ( in_array( $family, [ 'woo_rows', 'woo_map' ], true ) ) {
			$stored = $wpdb->get_var( $wpdb->prepare( 'SELECT payload FROM %i WHERE job_id=%d AND family=%s AND batch_index=%d', $table, $job_id, $family, $index ) );
			if ( ! is_string( $stored ) || ! hash_equals( $hash, hash( 'sha256', $stored ) ) ) {
				return self::error(); }
		}
		return array(
			'snapshot_version' => 1,
			'job_id'           => (int) $job_id,
			'family'           => $family,
			'batch_index'      => (int) $index,
			'digest'           => $hash,
			'post_type'        => $records[0]['post_type'] ?? '',
		);
	}

	/** Read and verify one persisted batch, or retain a legacy inline checkpoint. */
	public static function get( array $reference ) {
		if ( ! isset( $reference['snapshot_version'] ) ) {
			return $reference;
		}
		if ( 1 !== $reference['snapshot_version'] || ! is_int( $reference['job_id'] ?? null ) || $reference['job_id'] < 1 || ! is_int( $reference['batch_index'] ?? null ) || $reference['batch_index'] < 0 || ! is_string( $reference['digest'] ?? null ) || ! preg_match( '/^[a-f0-9]{64}$/D', $reference['digest'] ) || ! in_array( $reference['family'] ?? '', array( 'acf', 'woo', 'woo_rows', 'woo_map', 'sources' ), true ) ) {
			return self::error();
		}
		global $wpdb;
		$json = $wpdb->get_var( $wpdb->prepare( 'SELECT payload FROM %i WHERE job_id=%d AND family=%s AND batch_index=%d', $wpdb->prefix . 'rsl_ie_migration_snapshots', $reference['job_id'], $reference['family'], $reference['batch_index'] ) );
		if ( ! is_string( $json ) || ! hash_equals( (string) ( $reference['digest'] ?? '' ), hash( 'sha256', $json ) ) ) {
			return self::error();
		}
		$records = json_decode( $json, true );
		return is_array( $records ) ? $records : self::error();
	}

	/** Load one batch at a time; callers must propagate an unavailable snapshot error. */
	public static function iterate( array $snapshots ) {
		foreach ( $snapshots as $index => $reference ) {
			yield $index => self::get( $reference );
		}
	}

	/** Read the ordering hint without loading the batch payload. */
	public static function type( array $reference ) {
		return isset( $reference['snapshot_version'] ) ? ( $reference['post_type'] ?? '' ) : ( $reference[0]['post_type'] ?? '' );
	}

	private static function error() {
		return new \WP_Error( 'rsl_ie_migration_snapshot_unavailable', 'Migration reference snapshot is unavailable or changed.', array( 'status' => 409 ) );
	}
}

<?php
/**
 * Transactional per-chunk upload metadata.
 *
 * @package RockStarLab\ImportExport
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

/** Caller holds the file resource lock; never call inside another transaction. */
class File_Chunk_Store {
	/**
	 * Check whether both metadata tables support atomic publication.
	 *
	 * @return bool
	 */
	public static function supported() {
		global $wpdb;
		foreach ( [ $wpdb->options, $wpdb->prefix . 'rsl_ie_file_chunks' ] as $table ) {
			if ( 'innodb' !== strtolower( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=%s', $table ) ) ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * Read one committed chunk without loading the entire manifest.
	 *
	 * @param string $id File identifier.
	 * @param int    $index Zero-based chunk index.
	 * @return array|null|\WP_Error
	 */
	public static function get( $id, $index ) {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT bytes,digest FROM %i WHERE file_id=%s AND chunk_index=%d', $wpdb->prefix . 'rsl_ie_file_chunks', $id, $index ) );
		if ( $wpdb->last_error ) {
			return File_Store::error( 'chunk_storage', 500 ); }
		return $row ? [
			'bytes'  => (int) $row->bytes,
			'sha256' => $row->digest,
		] : null;
	}

	/**
	 * Publish the immutable chunk and session counters together, or neither.
	 *
	 * @param string $id File identifier.
	 * @param int    $index Zero-based chunk index.
	 * @param array  $chunk Trusted byte count and SHA-256.
	 * @param array  $before Exact expected session state.
	 * @param array  $after Updated session state.
	 * @return true|\WP_Error
	 */
	public static function publish( $id, $index, array $chunk, array $before, array $after ) {
		global $wpdb;
		if ( ! self::supported() || ! File_Store::valid_id( $id ) || $index < 0 ) {
			return File_Store::error( 'chunk_storage', 500 ); }
		$key = 'rsl_ie_file_' . $id;
		if ( false === $wpdb->query( 'START TRANSACTION' ) ) {
			return File_Store::error( 'chunk_storage', 500 ); }
		$committed = false;
		try {
			$stored = $wpdb->get_var( $wpdb->prepare( 'SELECT option_value FROM %i WHERE option_name=%s FOR UPDATE', $wpdb->options, $key ) );
			if ( maybe_serialize( $before ) !== $stored ) {
				return File_Store::error( 'upload_state_conflict', 409 ); }
			$old = self::get( $id, $index );
			if ( is_wp_error( $old ) ) {
				return $old; }
			if ( null !== $old && $old !== $chunk ) {
				return File_Store::error( 'chunk_content_conflict', 409 ); }
			if ( null === $old && false === $wpdb->insert(
				$wpdb->prefix . 'rsl_ie_file_chunks',
				[
					'file_id'     => $id,
					'chunk_index' => $index,
					'bytes'       => $chunk['bytes'],
					'digest'      => $chunk['sha256'],
				],
				[ '%s', '%d', '%d', '%s' ]
			) ) {
				return File_Store::error( 'chunk_storage', 500 ); }
			if ( false === $wpdb->update( $wpdb->options, [ 'option_value' => maybe_serialize( $after ) ], [ 'option_name' => $key ], [ '%s' ], [ '%s' ] ) ) {
				return File_Store::error( 'chunk_storage', 500 ); }
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				return File_Store::error( 'chunk_storage', 500 ); }
			$committed = true;
			wp_cache_delete( $key, 'options' );
			return true;
		} finally {
			if ( ! $committed ) {
				$wpdb->query( 'ROLLBACK' ); }
		}
	}
}

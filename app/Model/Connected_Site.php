<?php
/**
 * Connected Site Model
 *
 * Model for managing connected sites for content synchronization
 *
 * @package RockStarLab\ImportExport
 * @subpackage Model
 */

namespace RockStarLab\ImportExport\Model;

defined( 'ABSPATH' ) || exit;

class Connected_Site {

	/**
	 * Table name
	 *
	 * @var string
	 */
	private static $table_name = 'rsl_ie_site_connections';

	/**
	 * Get all connected sites
	 *
	 * @return array List of connected sites
	 */
	public static function get_all() {
		global $wpdb;
		$table = $wpdb->prefix . self::$table_name;

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying the plugin's custom site-connections table.
			$wpdb->prepare( 'SELECT * FROM %i ORDER BY created_at DESC', $table ),
			ARRAY_A
		);

		return $results ?: array();
	}

	/**
	 * Get site by ID
	 *
	 * @param int $id Site ID.
	 * @return array|null Site data or null if not found
	 */
	public static function get_by_id( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . self::$table_name;

		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying the plugin's custom site-connections table.
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $id ),
			ARRAY_A
		);
	}

	/**
	 * Get site by API key
	 *
	 * @param string $api_key API key.
	 * @return array|null Site data or null if not found
	 */
	public static function get_by_api_key( $api_key ) {
		global $wpdb;
		$table = $wpdb->prefix . self::$table_name;

		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying the plugin's custom site-connections table.
			$wpdb->prepare( 'SELECT * FROM %i WHERE api_key = %s', $table, $api_key ),
			ARRAY_A
		);
	}

	/**
	 * Create new site connection
	 *
	 * @param array $data Site data.
	 * @return int|false Insert ID on success, false on failure
	 */
	public static function create( $data ) {
		global $wpdb;
		$table = $wpdb->prefix . self::$table_name;

		$defaults = array(
			'name'       => '',
			'remote_url' => '',
			'api_key'    => self::generate_api_key(),
			'direction'  => 'bidirectional',
			'status'     => 'active',
			'created_by' => get_current_user_id(),
			'created_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
		);

		$data = wp_parse_args( $data, $defaults );

		$result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct DB query required here.
			$table,
			$data,
			array(
				'%s', // name
				'%s', // remote_url
				'%s', // api_key
				'%s', // direction
				'%s', // status
				'%d', // created_by
				'%s', // created_at
				'%s', // updated_at
			)
		);

		return $result ? $wpdb->insert_id : false;
	}

	/**
	 * Update site connection
	 *
	 * @param int   $id   Site ID.
	 * @param array $data Site data to update.
	 * @return bool True on success, false on failure
	 */
	public static function update( $id, $data ) {
		global $wpdb;
		$table = $wpdb->prefix . self::$table_name;

		$data['updated_at'] = current_time( 'mysql' );

		$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			$table,
			$data,
			array( 'id' => $id ),
			array(
				'%s', // name
				'%s', // remote_url
				'%s', // api_key
				'%s', // direction
				'%s', // status
				'%s', // last_sync_at
				'%s', // last_error
				'%s', // updated_at
			),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Delete site connection
	 *
	 * @param int $id Site ID.
	 * @return bool True on success, false on failure
	 */
	public static function delete( $id ) {
		global $wpdb;
		$table = $wpdb->prefix . self::$table_name;

		$result = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			$table,
			array( 'id' => $id ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Update last sync time
	 *
	 * @param int    $id         Site ID.
	 * @param string $error      Optional error message.
	 * @return bool True on success, false on failure
	 */
	public static function update_last_sync( $id, $error = null ) {
		global $wpdb;
		$table = $wpdb->prefix . self::$table_name;

		$data = array(
			'last_sync_at' => current_time( 'mysql' ),
			'updated_at'   => current_time( 'mysql' ),
		);

		if ( null !== $error ) {
			$data['last_error'] = $error;
			$data['status']     = 'error';
		} else {
			$data['last_error'] = null;
			$data['status']     = 'active';
		}

		$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			$table,
			$data,
			array( 'id' => $id ),
			array( '%s', '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * Generate unique API key
	 *
	 * @return string Generated API key
	 */
	public static function generate_api_key() {
		return 'rsl_ie_' . wp_generate_password( 40, false, false );
	}

	/**
	 * Regenerate API key for a site
	 *
	 * @param int $id Site ID.
	 * @return string|false New API key on success, false on failure
	 */
	public static function regenerate_api_key( $id ) {
		$new_key = self::generate_api_key();
		$result  = self::update( $id, array( 'api_key' => $new_key ) );

		return $result ? $new_key : false;
	}

	/**
	 * Get statistics for connected sites
	 *
	 * @return array Statistics
	 */
	public static function get_stats() {
		global $wpdb;
		$table = $wpdb->prefix . self::$table_name;

		$stats = array(
			'total'  => 0,
			'active' => 0,
			'error'  => 0,
		);

		$results = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Aggregating the plugin's custom site-connections table.
			$wpdb->prepare( 'SELECT status, COUNT(*) as count FROM %i GROUP BY status', $table ),
			ARRAY_A
		);

		foreach ( $results as $row ) {
			$stats[ $row['status'] ] = (int) $row['count'];
			$stats['total']         += (int) $row['count'];
		}

		return $stats;
	}

	/**
	 * Check if site connection exists by URL
	 *
	 * @param string $remote_url Remote site URL.
	 * @param int    $exclude_id Optional ID to exclude from check.
	 * @return bool True if exists, false otherwise
	 */
	public static function exists_by_url( $remote_url, $exclude_id = null ) {
		global $wpdb;
		$table = $wpdb->prefix . self::$table_name;

		if ( $exclude_id ) {
			$count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Checking uniqueness in the plugin's custom site-connections table.
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE remote_url = %s AND id != %d',
					$table,
					$remote_url,
					$exclude_id
				)
			);
		} else {
			$count = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Checking uniqueness in the plugin's custom site-connections table.
				$wpdb->prepare(
					'SELECT COUNT(*) FROM %i WHERE remote_url = %s',
					$table,
					$remote_url
				)
			);
		}

		return $count > 0;
	}
}

<?php
/**
 * Connection-owned job locks shared by AJAX and cron.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

/**
 * A slow import cannot outlive a lease and run alongside its replacement.
 */
class Job_Lock {

	/**
	 * Prevent recursive acquisition on the same database connection.
	 *
	 * @var array
	 */
	private static $held = [];

	/**
	 * Try to acquire without waiting; connection closure also releases the lock.
	 *
	 * @param int $job_id Site-local job ID.
	 * @return string|false|\WP_Error Lock name, contention or database failure.
	 */
	public static function acquire( $job_id ) {
		return self::acquire_resource( 'job', (int) $job_id );
	}

	/**
	 * Lock a server-selected resource namespace on this site.
	 *
	 * @param string $scope Internal resource namespace.
	 * @param string $resource_id Resource identifier.
	 * @return string|false|\WP_Error
	 */
	public static function acquire_resource( $scope, $resource_id ) {
		global $wpdb;
		$name = 'rsl_ie_' . $scope . '_' . md5( DB_NAME . ':' . $wpdb->prefix . ':' . $resource_id );
		if ( isset( self::$held[ $name ] ) ) {
			return false;
		}
		$result = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, 0)', $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Connection-owned synchronization must bypass caches.
		if ( null === $result ) {
			return new \WP_Error( 'rsl_ie_lock_unavailable', __( 'The database could not acquire a job lock.', 'import-export-by-rockstarlab' ), [ 'status' => 503 ] );
		}
		if ( '1' !== (string) $result ) {
			return false;
		}
		self::$held[ $name ] = true;
		return $name;
	}

	/** Whether this process already owns a resource lock. */
	public static function holds_resource( $scope, $resource_id ) {
		global $wpdb;
		$name = 'rsl_ie_' . $scope . '_' . md5( DB_NAME . ':' . $wpdb->prefix . ':' . $resource_id );
		return isset( self::$held[ $name ] );
	}

	/**
	 * Whether this process is running any plugin job right now.
	 *
	 * @return bool
	 */
	public static function holds_any_job() {
		foreach ( array_keys( self::$held ) as $name ) {
			if ( 0 === strpos( $name, 'rsl_ie_job_' ) ) {
				return true;
			}
		}
		return false;
	}

	/** Verify an internal continuation is running under its owning queue lock. */
	public static function holds( $job_id ) {
		global $wpdb;
		$name = 'rsl_ie_job_' . md5( DB_NAME . ':' . $wpdb->prefix . ':' . (int) $job_id );
		return isset( self::$held[ $name ] );
	}

	/**
	 * Release only a lock acquired by this process.
	 *
	 * @param string $name Acquired lock name.
	 * @return void
	 */
	public static function release( $name ) {
		global $wpdb;
		if ( isset( self::$held[ $name ] ) ) {
			$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Connection-owned synchronization must bypass caches.
			unset( self::$held[ $name ] );
		}
	}
}

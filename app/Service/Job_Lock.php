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
	public static function acquire_resource( $scope, $resource_id, $wait = 0 ) {
		global $wpdb;
		$name = 'rsl_ie_' . $scope . '_' . md5( DB_NAME . ':' . $wpdb->prefix . ':' . $resource_id );
		if ( isset( self::$held[ $name ] ) ) {
			return false;
		}
		if ( self::sqlite() ) {
			return self::acquire_row( $name, $wait );
		}
		$result = $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $name, max( 0, min( 15, (int) $wait ) ) ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Connection-owned synchronization must bypass caches.
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
			if ( self::sqlite() ) {
				$wpdb->delete( $wpdb->options, [ 'option_name' => self::ROW . $name ] ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Lock rows must bypass caches.
			} else {
				$wpdb->get_var( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $name ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Connection-owned synchronization must bypass caches.
			}
			unset( self::$held[ $name ] );
		}
	}

	/** Option-name prefix of SQLite lock rows. */
	const ROW = '_rsl_ie_lock_';

	/** Seconds after which a lock row left by a crashed process is taken over. */
	const ROW_TTL = 900;

	/**
	 * SQLite (WordPress Studio, Playground, Reprint copies) has no GET_LOCK(): the
	 * SQLite translator answers it with a placeholder, which looked like contention.
	 *
	 * @return bool
	 */
	private static function sqlite() {
		global $wpdb;
		return ( defined( 'DB_ENGINE' ) && 'sqlite' === DB_ENGINE ) || ( is_object( $wpdb ) && 'WP_SQLite_DB' === get_class( $wpdb ) );
	}

	/**
	 * Lock as a unique options row: the insert either wins or finds the holder.
	 * A row older than ROW_TTL belongs to a process that died and is replaced.
	 *
	 * @param string $name Lock name.
	 * @param int    $wait Seconds to wait for a holder to release.
	 * @return string|false
	 */
	private static function acquire_row( $name, $wait ) {
		global $wpdb;
		$option = self::ROW . $name;
		$until  = microtime( true ) + max( 0, min( 15, (int) $wait ) );
		do {
			$now = time();
			// One statement, so SQLite's single writer makes it atomic. Copies made by some
			// tools lose the UNIQUE index on option_name, so a plain INSERT could succeed twice.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Lock rows must bypass caches.
			$won = (int) $wpdb->query( $wpdb->prepare( "INSERT INTO {$wpdb->options} (option_name, option_value, autoload) SELECT %s, %s, 'off' FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM {$wpdb->options} WHERE option_name = %s)", $option, (string) $now, $option ) );
			if ( ! $won ) {
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Lock rows must bypass caches.
				$since = (int) $wpdb->get_var( $wpdb->prepare( "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $option ) );
				if ( $since && $since < $now - self::ROW_TTL ) {
					// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Lock rows must bypass caches.
					$won = $wpdb->query( $wpdb->prepare( "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s", (string) $now, $option, (string) $since ) );
					$won = $won ? 1 : false;
				}
			}
			if ( $won ) {
				self::$held[ $name ] = true;
				return $name;
			}
			if ( microtime( true ) >= $until ) {
				return false;
			}
			usleep( 200000 );
		} while ( true );
	}
}

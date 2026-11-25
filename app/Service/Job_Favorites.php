<?php
/**
 * Favorite jobs of a user (Jobs Log star and "Favorites only" filter). Kept as a user option of this
 * site (user meta with the site's table prefix, because job IDs are per site on multisite), so each
 * administrator has their own list; the plugin's uninstall removes it. A deleted job simply stops
 * matching (job IDs are never reused).
 *
 * @package RockStarLab\ImportExport
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

class Job_Favorites {

	const META = 'rsl_ie_favorite_jobs';
	const MAX  = 1000;

	/**
	 * Job IDs the user marked as favorite, newest first.
	 *
	 * @param int $user_id User ID (0 = current user).
	 * @return int[]
	 */
	public static function ids( $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( $user_id <= 0 ) {
			return [];
		}
		$ids = get_user_option( self::META, $user_id );
		return is_array( $ids ) ? array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) ) : [];
	}

	/**
	 * Whether a job is a favorite of the user.
	 *
	 * @param int $job_id  Job ID.
	 * @param int $user_id User ID (0 = current user).
	 * @return bool
	 */
	public static function is_favorite( $job_id, $user_id = 0 ) {
		return in_array( (int) $job_id, self::ids( $user_id ), true );
	}

	/**
	 * Mark or unmark a job.
	 *
	 * @param int  $job_id   Job ID.
	 * @param bool $favorite True to add, false to remove.
	 * @param int  $user_id  User ID (0 = current user).
	 * @return bool|\WP_Error New state, or an error when the job does not exist.
	 */
	public static function set( $job_id, $favorite, $user_id = 0 ) {
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		$job_id  = (int) $job_id;
		if ( $user_id <= 0 ) {
			return new \WP_Error( 'rsl_ie_job_favorite_user', __( 'Sign in to mark favorite jobs.', 'import-export-by-rockstarlab' ), [ 'status' => 401 ] );
		}
		if ( $favorite && ( $job_id <= 0 || ! rsl_ie()->Model->job->find( $job_id ) ) ) {
			return new \WP_Error( 'rsl_ie_job_not_found', __( 'Job not found', 'import-export-by-rockstarlab' ), [ 'status' => 404 ] );
		}
		$ids = array_values( array_diff( self::ids( $user_id ), [ $job_id ] ) );
		if ( $favorite ) {
			array_unshift( $ids, $job_id );
			$ids = array_slice( $ids, 0, self::MAX );
		}
		if ( $ids ) {
			update_user_option( $user_id, self::META, $ids );
		} else {
			delete_user_option( $user_id, self::META );
		}
		return (bool) $favorite;
	}

	/** User meta key of this site's favorites (what update_user_option() writes). */
	public static function meta_key() {
		global $wpdb;
		return $wpdb->get_blog_prefix() . self::META;
	}
}

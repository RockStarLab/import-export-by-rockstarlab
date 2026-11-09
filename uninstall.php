<?php
/**
 * Uninstall script
 *
 * Fired when the plugin is uninstalled.
 * Drops all custom tables and cleans up options.
 *
 * @package RockStarLab\ImportExport
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Load the database migration helper
require_once plugin_dir_path( __FILE__ ) . 'app/Helper/Database_Migration.php';

if ( ! function_exists( 'rsl_ie_delete_directory' ) ) {
	/**
	 * Recursively delete a directory.
	 */
	function rsl_ie_delete_directory( $dir ) {
		if ( ! is_dir( $dir ) ) {
			return false;
		}

		$items = array_diff( scandir( $dir ), array( '.', '..' ) );

		foreach ( $items as $item ) {
			$path = $dir . DIRECTORY_SEPARATOR . $item;

			if ( is_dir( $path ) ) {
				rsl_ie_delete_directory( $path );
			} else {
				@wp_delete_file( $path );
			}
		}

		return @rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
	}
}

/**
 * Remove the plugin's tables, options, scheduled events and files from the current site.
 */
function rsl_ie_uninstall_site() {
	global $wpdb;

	// Clean up options
	delete_option( 'rsl_ie_db_version' );
	delete_option( 'rsl_ie_abilities_enabled' );
	delete_option( 'rsl_ie_updraftplus_abilities_enabled' );
	delete_option( 'rsl_ie_site_api_key' );
	delete_option( 'rsl_ie_sync_key_identity' );
	delete_option( 'rsl_ie_openai_api_key' );
	delete_option( 'rsl_ie_review_dismissed' );
	delete_option( 'rsl_ie_agent_pointer_dismissed' );
	delete_option( 'rsl_ie_install_date' );
	// Mapping templates, job notifications and event triggers.
	foreach ( [ 'rsl_ie_mapping_templates', 'rsl_ie_notifications', 'rsl_ie_notifications_state', 'rsl_ie_notifications_log', 'rsl_ie_notifications_retries', 'rsl_ie_notifications_lock', 'rsl_ie_triggers', 'rsl_ie_triggers_log' ] as $rsl_ie_option ) {
		delete_option( $rsl_ie_option );
	}
	wp_unschedule_hook( 'rsl_ie_send_notifications' );
	wp_unschedule_hook( 'rsl_ie_run_trigger' );
	wp_unschedule_hook( 'rsl_ie_check_trigger_files' );

	// Clean up transients
	// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $wpdb->options is a controlled WordPress table name.
	$wpdb->query( $wpdb->prepare( "DELETE FROM `{$wpdb->options}` WHERE option_name LIKE %s", '_transient_rsl_ie_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
	$wpdb->query( $wpdb->prepare( "DELETE FROM `{$wpdb->options}` WHERE option_name LIKE %s", '_transient_timeout_rsl_ie_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
	// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

	// Remove file-service resources from this site's configured private storage.
	require_once plugin_dir_path( __FILE__ ) . 'app/Service/File_Store.php';
	$rsl_ie_file_keys = $wpdb->get_col( $wpdb->prepare( 'SELECT option_name FROM %i WHERE option_name LIKE %s', $wpdb->options, $wpdb->esc_like( 'rsl_ie_file_file_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Enumerate this site's private file records for uninstall.
	foreach ( $rsl_ie_file_keys as $rsl_ie_file_key ) {
		\RockStarLab\ImportExport\Service\File_Store::remove( substr( $rsl_ie_file_key, strlen( 'rsl_ie_file_' ) ) );
	}
	$rsl_ie_chunk_keys = $wpdb->get_col( $wpdb->prepare( 'SELECT option_name FROM %i WHERE option_name LIKE %s', $wpdb->options, $wpdb->esc_like( 'rsl_ie_media_chunk_file_' ) . '%' ) );
	foreach ( $rsl_ie_chunk_keys as $rsl_ie_chunk_key ) {
		\RockStarLab\ImportExport\Service\File_Store::remove( substr( $rsl_ie_chunk_key, strlen( 'rsl_ie_media_chunk_' ) ) );
		delete_option( $rsl_ie_chunk_key );
	}
	delete_option( 'rsl_ie_private_storage_base' );
	wp_clear_scheduled_hook( 'rsl_ie_cleanup_old_chunks' );
	wp_clear_scheduled_hook( 'rsl_ie_recover_jobs' );
	wp_unschedule_hook( 'rsl_ie_run_job' );
	$rsl_ie_request_keys = $wpdb->get_col( $wpdb->prepare( 'SELECT option_name FROM %i WHERE option_name LIKE %s', $wpdb->options, $wpdb->esc_like( 'rsl_ie_job_request_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Remove this site's request tombstones.
	foreach ( $rsl_ie_request_keys as $rsl_ie_request_key ) {
		delete_option( $rsl_ie_request_key );
	}

	// Clean up uploaded files directory
	$rsl_ie_upload_dir_info = wp_upload_dir();
	$rsl_ie_upload_dir      = $rsl_ie_upload_dir_info['basedir'] . '/rsl-ie-uploads/';

	if ( is_dir( $rsl_ie_upload_dir ) ) {
		rsl_ie_delete_directory( $rsl_ie_upload_dir );
	}
	foreach ( [ 'rsl_ie_run_job_schedule', 'rsl_ie_process_queue', 'rsl_ie_updraft_worker' ] as $rsl_ie_hook ) {
		wp_unschedule_hook( $rsl_ie_hook );
	}

	// Tables last: removing stored files above still reads them.
	\RockStarLab\ImportExport\Helper\Database_Migration::drop_tables();

	// Any option the plugin left (cleanup cursors, menu settings, schedule details); PRO keeps its own.
	$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE option_name LIKE %s AND option_name NOT LIKE %s', $wpdb->options, $wpdb->esc_like( 'rsl_ie_' ) . '%', $wpdb->esc_like( 'rsl_ie_pro_' ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Uninstall cleanup of this plugin's options.
	wp_cache_flush();
}

// Every site of a network has its own tables, options and events.
if ( is_multisite() ) {
	foreach ( get_sites( [ 'fields' => 'ids', 'number' => 0 ] ) as $rsl_ie_blog_id ) {
		switch_to_blog( (int) $rsl_ie_blog_id );
		rsl_ie_uninstall_site();
		restore_current_blog();
	}
} else {
	rsl_ie_uninstall_site();
}

// Fire action for additional cleanup by extensions
do_action( 'rsl_ie_uninstall' );

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

// Drop all custom tables
\RockStarLab\ImportExport\Helper\Database_Migration::drop_tables();

// Clean up options
delete_option( 'rsl_ie_db_version' );
delete_option( 'rsl_ie_site_api_key' );
delete_option( 'rsl_ie_openai_api_key' );
delete_option( 'rsl_ie_review_dismissed' );
delete_option( 'rsl_ie_install_date' );

// Clean up transients
global $wpdb;
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $wpdb->options is a controlled WordPress table name.
$wpdb->query( $wpdb->prepare( "DELETE FROM `{$wpdb->options}` WHERE option_name LIKE %s", '_transient_rsl_ie_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
$wpdb->query( $wpdb->prepare( "DELETE FROM `{$wpdb->options}` WHERE option_name LIKE %s", '_transient_timeout_rsl_ie_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Clean up uploaded files directory
$rsl_ie_upload_dir_info = wp_upload_dir();
$rsl_ie_upload_dir      = $rsl_ie_upload_dir_info['basedir'] . '/rsl-ie-uploads/';

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

if ( is_dir( $rsl_ie_upload_dir ) ) {
	rsl_ie_delete_directory( $rsl_ie_upload_dir );
}

// Fire action for additional cleanup by extensions
do_action( 'rsl_ie_uninstall' );

<?php
/**
 * Uninstall script
 * 
 * Fired when the plugin is uninstalled.
 * Drops all custom tables and cleans up options.
 * 
 * @package WP_AIE
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Load the database migration helper
require_once plugin_dir_path( __FILE__ ) . 'app/Helper/Database_Migration.php';

// Drop all custom tables
\WP_AIE\Helper\Database_Migration::drop_tables();

// Clean up options
delete_option( 'aie_db_version' );
delete_option( 'aie_plugin_version' );

// Clean up transients
global $wpdb;
// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $wpdb->options is a controlled WordPress table name.
$wpdb->query( $wpdb->prepare( "DELETE FROM `{$wpdb->options}` WHERE option_name LIKE %s", '_transient_aie_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
$wpdb->query( $wpdb->prepare( "DELETE FROM `{$wpdb->options}` WHERE option_name LIKE %s", '_transient_timeout_aie_%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
// phpcs:enable WordPress.DB.PreparedSQL.InterpolatedNotPrepared

// Clean up uploaded files directory
$upload_dir = wp_upload_dir();
$aie_upload_dir = $upload_dir['basedir'] . '/aie-uploads/';

if ( is_dir( $aie_upload_dir ) ) {
    // Recursively delete directory
    function aie_delete_directory( $dir ) {
        if ( ! is_dir( $dir ) ) {
            return false;
        }
        
        $items = array_diff( scandir( $dir ), [ '.', '..' ] );
        
        foreach ( $items as $item ) {
            $path = $dir . DIRECTORY_SEPARATOR . $item;
            
            if ( is_dir( $path ) ) {
                aie_delete_directory( $path );
            } else {
                @wp_delete_file( $path );
            }
        }
        
        return @rmdir( $dir ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir
    }
    
    aie_delete_directory( $aie_upload_dir );
}

// Fire action for additional cleanup by extensions
do_action( 'aie_uninstall' );

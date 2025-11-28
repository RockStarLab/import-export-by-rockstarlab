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
require_once plugin_dir_path( __FILE__ ) . 'app/helper/database_migration.php';

// Drop all custom tables
\WP_AIE\helper\database_migration::drop_tables();

// Clean up options
delete_option( 'aie_db_version' );
delete_option( 'aie_plugin_version' );

// Clean up transients
global $wpdb;
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_aie_%'" );
$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '_transient_timeout_aie_%'" );

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
                @unlink( $path );
            }
        }
        
        return @rmdir( $dir );
    }
    
    aie_delete_directory( $aie_upload_dir );
}

// Fire action for additional cleanup by extensions
do_action( 'aie_uninstall' );

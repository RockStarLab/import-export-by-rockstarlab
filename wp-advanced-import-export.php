<?php
/*
	Plugin Name:				WP Advanced Import Export
	Plugin URI:					https://profiles.wordpress.org/rockstarlab/
	Description:				
	Version:						1.0.0
	Requires at least: 	4.3
	Author:							RockstarLab
	Author URI:					https://profiles.wordpress.org/rockstarlab/profile/
	Text Domain:				wp-advanced-import-export
	Domain Path:				/languages
	License:						GPL v2 or later
	License URI:				https://www.gnu.org/licenses/gpl-2.0.html
*/

// If this file is called directly, abort.
defined( 'ABSPATH') or exit;

define( 'WP_AIE_FILE', __FILE__);
define( 'WP_AIE_PATH', trailingslashit( plugin_dir_path( WP_AIE_FILE )));
define( 'WP_AIE_URL', plugins_url( '/', WP_AIE_FILE));
define( 'WP_AIE_VERSION', '1.0.0' );

// Autoloader - WP_AIE namespace only
spl_autoload_register( function( $class) {

	$prefix = 'WP_AIE\\';
	$base_dir = WP_AIE_PATH . 'app/';

	if( strncmp( $prefix, $class, strlen( $prefix ) ) === 0 ) {
		$relative_class = substr( $class, strlen( $prefix ) );
		$file = $base_dir . str_replace( '\\', '/', $relative_class) . '.php';
		
		if( file_exists( $file ) ) {
			require $file;
		}
	}

});

// Global point of enter
if( ! function_exists( 'WP_AIE')) {

	function WP_AIE() {
		return \WP_AIE\app::getInstance();
	}

}

if ( ! function_exists( 'waie_fs' ) ) {
	// Create a helper function for easy SDK access.
	function waie_fs() {
		global $waie_fs;

		if ( ! isset( $waie_fs ) ) {
			// Include Freemius SDK.
			require_once dirname( __FILE__ ) . '/vendor/freemius/start.php';

			$waie_fs = fs_dynamic_init( [
				'id'                  => '21998',
				'slug'                => 'wp-advanced-import-export',
				'type'                => 'plugin',
				'public_key'          => 'pk_c389cfb9437cdb5c934c0efd7e99c',
				'is_premium'          => false,
				'is_premium_only'     => false,
				'has_addons'          => false,
				'has_paid_plans'      => true,
				// Automatically removed in the free version. If you're not using the
				// auto-generated free version, delete this line before uploading to wp.org.
				'wp_org_gatekeeper'   => 'OA7#BoRiBNqdf52FvzEf!!074aRLPs8fspif$7K1#4u4Csys1fQlCecVcUTOs2mcpeVHi#C2j9d09fOTvbC0HloPT7fFee5WdS3G',
				'menu'                => [
					'slug'           => 'wp-advanced-import-export',
					'first-path'     => 'admin.php?page=wp-advanced-import-export',
				],
			]);
		}

		return $waie_fs;
	}

	// Init Freemius.
	waie_fs();
	// Signal that SDK was initiated.
	do_action( 'waie_fs_loaded' );
}

// Run the plugin
WP_AIE()->run();

// Activation hook - create database tables
register_activation_hook( WP_AIE_FILE, function() {
	\WP_AIE\helper\database_migration::create_tables();
});

// Deactivation hook - cleanup
register_deactivation_hook( WP_AIE_FILE, [ '\WP_AIE\app', 'deactivate_cleanup' ] );
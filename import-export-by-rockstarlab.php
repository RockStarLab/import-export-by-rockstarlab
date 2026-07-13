<?php
/*
	Plugin Name:                Import Export by RockStarLab
	Plugin URI:                 https://profiles.wordpress.org/rockstarlab/
	Description:                A powerful advanced plugin for importing and exporting WordPress content.
	Version:                    1.1.0
	Requires at least:          6.2
	Author:                     RockstarLab
	Author URI:                 https://profiles.wordpress.org/rockstarlab/profile/
	Text Domain:                import-export-by-rockstarlab
	Domain Path:                /languages
	License:                    GPL v2 or later
	License URI:                https://www.gnu.org/licenses/gpl-2.0.html
*/

// If this file is called directly, abort.
defined( 'ABSPATH' ) or exit;

define( 'RSL_IE_FILE', __FILE__ );
define( 'RSL_IE_PATH', trailingslashit( plugin_dir_path( RSL_IE_FILE ) ) );
define( 'RSL_IE_URL', plugins_url( '/', RSL_IE_FILE ) );
define( 'RSL_IE_VERSION', '1.1.0' );

// Composer autoloader (required for bundled third-party libraries).
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Autoloader - RockStarLab\ImportExport namespace only
spl_autoload_register(
	function ( $class ) {

		$prefix   = 'RockStarLab\\ImportExport\\';
		$base_dir = RSL_IE_PATH . 'app/';

		if ( strncmp( $prefix, $class, strlen( $prefix ) ) === 0 ) {
				$relative_class = substr( $class, strlen( $prefix ) );
				$file           = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

			if ( file_exists( $file ) ) {
				require $file;
			}
		}
	}
);

// Global point of enter
if ( ! function_exists( 'rsl_ie' ) ) {

	function rsl_ie() {
		return \RockStarLab\ImportExport\App::getInstance();
	}

}
if ( ! function_exists( 'rsl_ie_fs' ) ) {
	// Create a helper function for easy SDK access.
	function rsl_ie_fs() {
		global $rsl_ie_fs;

		if ( ! isset( $rsl_ie_fs ) ) {
			// Include Freemius SDK.
			require_once __DIR__ . '/vendor/freemius/start.php';

			$rsl_ie_fs = fs_dynamic_init(
				array(
					'id'               => '21998',
					'slug'             => 'import-export-by-rockstarlab',
					'premium_slug'     => 'import-export-by-rockstarlab',
					'type'             => 'plugin',
					'public_key'       => 'pk_c389cfb9437cdb5c934c0efd7e99c',
					// WP.org compliance: the directory-hosted plugin must not be "premium".
					'is_premium'       => false,
					'is_premium_only'  => false,
					'has_addons'       => true,
					'has_paid_plans'   => false,
					// Freemius WP.org compatibility mode.
					'is_org_compliant' => true,
					'has_affiliation'  => 'all',
					'menu'             => array(
						'slug'       => 'import-export-by-rockstarlab',
						'first-path' => 'admin.php?page=import-export-by-rockstarlab',
					),
				)
			);

		}

		return $rsl_ie_fs;
	}

	// Init Freemius.
	rsl_ie_fs()->add_filter(
		'connect_url',
		function ( $url ) {
			if ( strpos( $url, 'require_license' ) === false ) {
				$url = add_query_arg( 'require_license', 'false', $url );
			}
			return $url;
		}
	);

	// Signal that SDK was initiated.
	do_action( 'rsl_ie_fs_loaded' );
}

// Run the plugin
rsl_ie()->run();

// Activation hook - create database tables
register_activation_hook(
	RSL_IE_FILE,
	function () {
		\RockStarLab\ImportExport\Helper\Database_Migration::create_tables();

		// Record the install date for the review-notice timer (fires only once)
		\RockStarLab\ImportExport\Helper\Review_Notice::set_install_date();

		// Automatically activate the free plan — skip the Freemius opt-in screen.
		// skip_connection() stores the anonymous state in persistent storage, so on
		// the next admin load is_activation_mode() returns false and no redirect occurs.
		if ( function_exists( 'rsl_ie_fs' ) && ! rsl_ie_fs()->is_registered() && ! rsl_ie_fs()->is_anonymous() ) {
			rsl_ie_fs()->skip_connection();
		}
	}
);

// Deactivation hook - cleanup
register_deactivation_hook( RSL_IE_FILE, [ '\RockStarLab\ImportExport\App', 'deactivate_cleanup' ] );

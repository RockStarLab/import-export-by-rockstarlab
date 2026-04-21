<?php
/*
	Plugin Name:                Import Export by RockStarLab
	Plugin URI:                 https://profiles.wordpress.org/rockstarlab/
	Description:                A powerful advanced plugin for importing and exporting WordPress content.
	Version:                    1.0.0
	Requires at least:          5.8
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
define( 'RSL_IE_VERSION', '1.0.0' );

// Composer autoloader (required for bundled 3rd-party libraries like nikic/php-parser).
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
				[
					'id'                => '21998',
					'slug'              => 'import-export-by-rockstarlab',
					'premium_slug'      => 'import-export-by-rockstarlab',
					'type'              => 'plugin',
					'public_key'        => 'pk_c389cfb9437cdb5c934c0efd7e99c',
					'is_premium'        => true,
					'is_premium_only'   => false,
					'has_addons'        => false,
					'has_paid_plans'    => true,
					'has_affiliation'		=> 'all',
					'trial' => [
						'days' => 30,
						'is_require_payment' => true,
					],
					'menu'              => [
						'slug'       => 'import-export-by-rockstarlab',
						'first-path' => 'admin.php?page=import-export-by-rockstarlab',
					],
				]
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

// Handle license activation trigger on plugins page (via enqueue, not inline <script>)
add_action(
	'admin_enqueue_scripts',
	function ( $hook_suffix ) {
		if ( 'plugins.php' !== $hook_suffix ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Verified below.
		if ( ! isset( $_GET['rsl-ie-activate-license'] ) ) {
			return;
		}

		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce verified below.
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( ! wp_verify_nonce( $nonce, 'rsl_ie_activate_license' ) ) {
			return;
		}

		wp_enqueue_script(
			'rsl-ie-plugins-page',
			plugins_url( 'assets/js/plugins-page.js', RSL_IE_FILE ),
			array( 'jquery' ),
			filemtime( plugin_dir_path( RSL_IE_FILE ) . 'assets/js/plugins-page.js' ),
			array(
				'in_footer' => true,
			)
		);

		wp_localize_script(
			'rsl-ie-plugins-page',
			'rslIePluginsPage',
			array(
				'slug' => dirname( plugin_basename( RSL_IE_FILE ) ),
			)
		);
	}
);

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

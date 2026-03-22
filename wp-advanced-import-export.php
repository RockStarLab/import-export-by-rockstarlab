<?php
/*
	Plugin Name:                WP Advanced Import Export
	Plugin URI:                 https://profiles.wordpress.org/rockstarlab/
	Description:                A powerful advanced plugin for importing and exporting WordPress content.
	Version:                    1.0.0
	Requires at least:          5.8
	Author:                     RockstarLab
	Author URI:                 https://profiles.wordpress.org/rockstarlab/profile/
	Text Domain:                wp-advanced-import-export
	Domain Path:                /languages
	License:                    GPL v2 or later
	License URI:                https://www.gnu.org/licenses/gpl-2.0.html
*/

// If this file is called directly, abort.
defined( 'ABSPATH' ) or exit;

define( 'WP_AIE_FILE', __FILE__ );
define( 'WP_AIE_PATH', trailingslashit( plugin_dir_path( WP_AIE_FILE ) ) );
define( 'WP_AIE_URL', plugins_url( '/', WP_AIE_FILE ) );
define( 'WP_AIE_VERSION', '1.0.0' );

// Autoloader - WP_AIE namespace only
spl_autoload_register(
	function ( $class ) {

		$prefix   = 'WP_AIE\\';
		$base_dir = WP_AIE_PATH . 'app/';

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
if ( ! function_exists( 'WP_AIE' ) ) {

	function WP_AIE() {
		return \WP_AIE\App::getInstance();
	}

}
if ( ! function_exists( 'waie_fs' ) ) {
	// Create a helper function for easy SDK access.
	function waie_fs() {
		global $waie_fs;

		if ( ! isset( $waie_fs ) ) {
			// Include Freemius SDK.
			require_once __DIR__ . '/vendor/freemius/start.php';

			$waie_fs = fs_dynamic_init(
				[
					'id'                => '21998',
					'slug'              => 'wp-advanced-import-export',
					'type'              => 'plugin',
					'public_key'        => 'pk_c389cfb9437cdb5c934c0efd7e99c',
					'is_premium'        => true,
					'is_premium_only'   => false,
					'has_addons'        => false,
					'has_paid_plans'    => true,
					'wp_org_gatekeeper' => 'OA7#BoRiBNqdf52FvzEf!!074aRLPs8fspif$7K1#4u4Csys1fQlCecVcUTOs2mcpeVHi#C2j9d09fOTvbC0HloPT7fFee5WdS3G',
					'trial' => [
						'days' => 30,
						'is_require_payment' => true,
					],
					'menu'              => [
						'slug'       => 'wp-advanced-import-export',
						'first-path' => 'admin.php?page=wp-advanced-import-export',
					],
				]
			);
		}

		return $waie_fs;
	}

	// Init Freemius.
	waie_fs()->add_filter(
		'connect_url',
		function ( $url ) {
			if ( strpos( $url, 'require_license' ) === false ) {
				$url = add_query_arg( 'require_license', 'false', $url );
			}
			return $url;
		}
	);

	// Signal that SDK was initiated.
	do_action( 'waie_fs_loaded' );
}

// Run the plugin
WP_AIE()->run();

// Handle license activation trigger on plugins page
add_action( 'admin_footer-plugins.php', function() {
	if ( ! isset( $_GET['aie-activate-license'] ) ) {
		return;
	}
	?>
	<script type="text/javascript">
	jQuery(document).ready(function($) {
		// Wait for page to fully load, then trigger Freemius activation
		setTimeout(function() {
			// Find the Freemius activation link for our plugin
			var $activateLink = $('tr[data-slug="wp-advanced-import-export"] .fs-activate-license-trigger');
			
			if ($activateLink.length) {
				// Trigger click on Freemius activation link
				$activateLink[0].click();
			} else {
				// Fallback - look for any activation link in our plugin row
				$('tr[data-slug="wp-advanced-import-export"] a').each(function() {
					if ($(this).text().toLowerCase().indexOf('activate') !== -1 || 
					    $(this).hasClass('fs-activate-license-trigger')) {
						this.click();
						return false;
					}
				});
			}
		}, 500);
	});
	</script>
	<?php
} );

// Activation hook - create database tables
register_activation_hook(
	WP_AIE_FILE,
	function () {
		\WP_AIE\Helper\Database_Migration::create_tables();
	}
);

// Deactivation hook - cleanup
register_deactivation_hook( WP_AIE_FILE, [ '\WP_AIE\App', 'deactivate_cleanup' ] );

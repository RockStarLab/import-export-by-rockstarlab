<?php
/**
 * Plugin warnings and the AI agent introduction pointer.
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Helper\Admin_Menu_Settings;
use RockStarLab\ImportExport\Helper\Ajax_Security;

defined( 'ABSPATH' ) || exit;

/** Coordinates plugin notices without blocking admin requests on HTTP checks. */
class Notices_Controller extends Base_Controller {

	/** Site-wide dismissal option. */
	const POINTER_DISMISSED = 'rsl_ie_agent_pointer_dismissed';

	/** Register admin hooks and the protected dismissal action. */
	public function init() {
		parent::init();
		add_action( 'admin_notices', [ $this, 'render_notices' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		// Init registers the menu customization callback before this controller.
		add_action( 'admin_menu', [ $this, 'add_menu_count' ], PHP_INT_MAX );
	}

	/**
	 * AJAX actions, protected by Base_Controller capability and nonce checks.
	 *
	 * @return array
	 */
	protected function get_ajax_actions() {
		return [ 'dismiss_agent_pointer' => [ 'callback' => 'dismiss_pointer' ] ];
	}

	/** Persist dismissal for this site. */
	public function dismiss_pointer() {
		if ( ! update_option( self::POINTER_DISMISSED, true, false ) && ! get_option( self::POINTER_DISMISSED ) ) {
			wp_send_json_error( [], 500 );
			return;
		}
		wp_send_json_success();
	}

	/**
	 * Detect loaded multilingual plugins, including network activations.
	 *
	 * @return string[]
	 */
	public function multilingual_plugins() {
		$plugins = [];
		if ( defined( 'ICL_SITEPRESS_VERSION' ) || class_exists( 'SitePress' ) ) {
			$plugins[] = 'WPML';
		}
		if ( defined( 'POLYLANG_VERSION' ) || function_exists( 'pll_languages_list' ) ) {
			$plugins[] = 'Polylang';
		}
		return $plugins;
	}

	/**
	 * Count server-detected warnings shared by the menu and browser REST check.
	 *
	 * @return int
	 */
	private function warning_count() {
		return ( $this->multilingual_plugins() ? 1 : 0 ) + ( is_multisite() ? 1 : 0 );
	}

	/**
	 * Whether the current page belongs to the plugin.
	 *
	 * @return bool
	 */
	private function is_plugin_page() {
		global $plugin_page;
		return is_string( $plugin_page ) && ( Admin_Menu_Settings::PARENT_SLUG === $plugin_page || 0 === strpos( $plugin_page, 'rsl-ie-' ) || 0 === strpos( $plugin_page, Admin_Menu_Settings::PARENT_SLUG . '-' ) );
	}

	/** Append a native WordPress count bubble after the custom menu title. */
	public function add_menu_count() {
		global $menu;
		if ( ! current_user_can( 'manage_options' ) || is_network_admin() ) {
			return;
		}
		$count = $this->warning_count();
		foreach ( $menu as &$item ) {
			if ( Admin_Menu_Settings::PARENT_SLUG === $item[2] ) {
				$item[0] .= ' <span class="awaiting-mod rsl-ie-warning-count"' . ( $count ? '' : ' style="display:none"' ) . '><span class="pending-count" aria-hidden="true">' . (int) $count . '</span><span class="screen-reader-text">' . esc_html__( 'Plugin warnings', 'import-export-by-rockstarlab' ) . '</span></span>';
				break;
			}
		}
		unset( $item );
	}

	/** Render warnings on plugin screens only. */
	public function render_notices() {
		if ( ! current_user_can( 'manage_options' ) || is_network_admin() || ! $this->is_plugin_page() ) {
			return;
		}
		if ( is_multisite() ) {
			printf(
				'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'Import Export by RockStarLab:', 'import-export-by-rockstarlab' ),
				esc_html__( 'WordPress Multisite is enabled. This plugin has not yet been tested on Multisite installations.', 'import-export-by-rockstarlab' )
			);
		}
		$plugins = $this->multilingual_plugins();
		if ( $plugins ) {
			printf(
				'<div class="notice notice-warning"><p><strong>%s</strong> %s</p></div>',
				esc_html__( 'Import Export by RockStarLab:', 'import-export-by-rockstarlab' ),
				esc_html(
					sprintf(
					/* translators: %s: active multilingual plugin names. */
						__( '%s is active. Content Sync, import and export may not preserve language assignments and translation relationships correctly. Verify multilingual content on a staging site before using these operations on your live site.', 'import-export-by-rockstarlab' ),
						implode( ', ', $plugins )
					)
				)
			);
		}
		echo '<div id="rsl-ie-rest-warning" class="notice notice-warning" style="display:none"><p><strong>' . esc_html__( 'Import Export by RockStarLab:', 'import-export-by-rockstarlab' ) . '</strong> ' . esc_html__( 'The plugin REST API is disabled or could not be reached from your browser. Content Sync and AI agent connections may not work. Check security plugins, REST API restrictions and your server configuration.', 'import-export-by-rockstarlab' ) . '</p></div>';
	}

	/** Load the lightweight REST check and optional WordPress pointer. */
	public function enqueue() {
		if ( ! current_user_can( 'manage_options' ) || is_network_admin() ) {
			return;
		}
		$pointer = ! get_option( self::POINTER_DISMISSED );
		$deps    = [ 'jquery' ];
		if ( $pointer ) {
			$deps[] = 'wp-pointer';
			wp_enqueue_style( 'wp-pointer' );
		}
		wp_enqueue_script( 'rsl-ie-admin-notices', RSL_IE_URL . 'assets/js/admin-notices.js', $deps, filemtime( RSL_IE_PATH . 'assets/js/admin-notices.js' ), true );
		wp_localize_script(
			'rsl-ie-admin-notices',
			'rslIeNotices',
			[
				// Keep the browser check on the admin origin, including local proxy ports.
				'restUrl'      => wp_make_link_relative( rest_url( 'rsl-ie/v1' ) ),
				'restNonce'    => wp_create_nonce( 'wp_rest' ),
				'warningCount' => $this->warning_count(),
				'countLabel'   => __( 'Plugin warnings:', 'import-export-by-rockstarlab' ),
				'pointer'      => $pointer,
				'ajaxUrl'      => Ajax_Security::ajax_url(),
				'nonce'        => wp_create_nonce( Ajax_Security::nonce_action( 'rsl_ie_dismiss_agent_pointer' ) ),
				'content'      => '<h3>' . esc_html__( 'Connect your AI agent', 'import-export-by-rockstarlab' ) . '</h3><p>' . esc_html__( 'You can now use Claude, Codex / ChatGPT or another compatible AI agent with Import Export by RockStarLab. Open Plugin Options to set up the connection.', 'import-export-by-rockstarlab' ) . '</p><p><a href="' . esc_url( admin_url( 'admin.php?page=rsl-ie-agent-connection' ) ) . '">' . esc_html__( 'Open Plugin Options', 'import-export-by-rockstarlab' ) . '</a></p>',
				'dismiss'      => __( 'Dismiss', 'import-export-by-rockstarlab' ),
				'retry'        => __( 'Could not save. Try again.', 'import-export-by-rockstarlab' ),
			]
		);
	}
}

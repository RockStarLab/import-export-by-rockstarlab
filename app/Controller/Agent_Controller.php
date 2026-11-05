<?php
/**
 * Agent connection screen; transport belongs to the official MCP Adapter.
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Service\Agent_Connection_Service;
use RockStarLab\ImportExport\Abilities\Ability_Registry;

defined( 'ABSPATH' ) || exit;

/** Guided agent setup screen. */
class Agent_Controller {
	/** Register the options tab destination. */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_page' ], 20 );
		add_filter( 'parent_file', [ $this, 'options_parent' ] );
		add_filter( 'submenu_file', [ $this, 'options_submenu' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue' ] );
		add_action( 'admin_post_rsl_ie_toggle_updraftplus_abilities', [ $this, 'toggle_updraftplus_abilities' ] );
		add_action( 'admin_post_rsl_ie_toggle_abilities', [ $this, 'toggle_abilities' ] );
		add_action( 'admin_post_rsl_ie_setup_adapter', [ $this, 'setup_adapter' ] );
	}

	/** Register a hidden page linked from Plugin Options. */
	public function register_page() {
		add_submenu_page( null, __( 'AI Agent Connection', 'import-export-by-rockstarlab' ), __( 'AI Agent Connection', 'import-export-by-rockstarlab' ), 'manage_options', 'rsl-ie-agent-connection', [ $this, 'display' ] );
		// Remove null-parent entries too: core otherwise resets parent_file after its filter.
		foreach ( [ 'rsl-ie-agent-connection', 'rsl-ie-button-settings', 'rsl-ie-plugin-options' ] as $page ) {
			remove_submenu_page( '', $page );
		}
	}

	/** Keep all options tabs under the visible Plugin Options menu entry. */
	private function is_options_page() {
		global $plugin_page;
		return in_array( $plugin_page, [ 'rsl-ie-plugin-settings', 'rsl-ie-plugin-options', 'rsl-ie-button-settings', 'rsl-ie-agent-connection' ], true );
	}

	/**
	 * Select the plugin parent menu.
	 *
	 * @param string $parent_file Current parent menu.
	 */
	public function options_parent( $parent_file ) {
		return $this->is_options_page() ? 'import-export-by-rockstarlab' : $parent_file;
	}

	/**
	 * Select Plugin Options for its hidden tabs.
	 *
	 * @param string|null $submenu Current submenu.
	 */
	public function options_submenu( $submenu ) {
		return $this->is_options_page() ? 'rsl-ie-plugin-settings' : $submenu;
	}

	/** Load only the wizard assets. */
	public function enqueue() {
		global $plugin_page;
		if ( 'rsl-ie-agent-connection' !== $plugin_page ) {
			return;
		}
		wp_enqueue_style( 'rsl-ie-agent-connection', RSL_IE_URL . 'assets/css/agent-connection.css', [], filemtime( RSL_IE_PATH . '/assets/css/agent-connection.css' ) );
		wp_enqueue_script( 'rsl-ie-agent-connection', RSL_IE_URL . 'assets/js/agent-connection.js', [], filemtime( RSL_IE_PATH . '/assets/js/agent-connection.js' ), true );
	}

	/** Save the explicit site-level opt-in before showing connection setup. */
	public function toggle_abilities() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot change agent access on this site.', 'import-export-by-rockstarlab' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'rsl_ie_toggle_abilities' );
		$enabled = isset( $_POST['abilities_enabled'] ) && '1' === $_POST['abilities_enabled'];
		update_option( 'rsl_ie_abilities_enabled', $enabled ? '1' : '0' );
		wp_safe_redirect( admin_url( 'admin.php?page=rsl-ie-agent-connection' . ( $enabled ? '&abilities_enabled=1' : '' ) ) );
		exit;
	}

	/** Save the backup opt-in only when both dependencies are enabled. */
	public function toggle_updraftplus_abilities() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot change agent access on this site.', 'import-export-by-rockstarlab' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'rsl_ie_toggle_updraftplus_abilities' );
		if ( ! Ability_Registry::is_enabled() || ! Ability_Registry::is_updraftplus_available() ) {
			wp_die(
				esc_html__( 'Enable Abilities API and activate UpdraftPlus before enabling backup access.', 'import-export-by-rockstarlab' ),
				'',
				[
					'response'  => 403,
					'back_link' => true,
				]
			);
		}
		$enabled = isset( $_POST['updraftplus_abilities_enabled'] ) && '1' === $_POST['updraftplus_abilities_enabled'];
		update_option( 'rsl_ie_updraftplus_abilities_enabled', $enabled ? '1' : '0' );
		wp_safe_redirect( admin_url( 'admin.php?page=rsl-ie-agent-connection' ) );
		exit;
	}

	/** Install the official directory package and activate it after an explicit POST. */
	public function setup_adapter() {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'manage_options' ) || ! current_user_can( 'activate_plugins' ) ) {
			wp_die( esc_html__( 'You cannot set up this plugin.', 'import-export-by-rockstarlab' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'rsl_ie_setup_adapter' );
		$service = new Agent_Connection_Service();
		$info    = $service->get_info();
		if ( is_wp_error( $info ) || ! Ability_Registry::is_enabled() || ! $info['rest_status']['available'] || ! $info['abilities_available'] ) {
			wp_die( esc_html__( 'Setup is blocked. Restore the REST API and Abilities API, then try again.', 'import-export-by-rockstarlab' ), '', [ 'back_link' => true ] );
		}
		$plugin = $info['adapter_plugin'];
		if ( ! $plugin ) {
			if ( ! current_user_can( 'install_plugins' ) ) {
				wp_die( esc_html__( 'You cannot install plugins on this site.', 'import-export-by-rockstarlab' ), '', [ 'response' => 403 ] );
			}
			require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			$package = plugins_api(
				'plugin_information',
				[
					'slug'   => 'mcp-adapter',
					'fields' => [ 'sections' => false ],
				]
			);
			if ( is_wp_error( $package ) ) {
				wp_die( esc_html( $package->get_error_message() ), '', [ 'back_link' => true ] );
			}
			$skin     = new \Automatic_Upgrader_Skin();
			$upgrader = new \Plugin_Upgrader( $skin );
			$result   = $upgrader->install( $package->download_link );
			if ( true !== $result ) {
				$message = is_wp_error( $result ) ? $result->get_error_message() : __( 'Installation could not finish. Check filesystem permissions, or install MCP Adapter from Plugins → Add New, then return here.', 'import-export-by-rockstarlab' );
				wp_die( esc_html( $message ), '', [ 'back_link' => true ] );
			}
			$plugin = $upgrader->plugin_info();
		}
		if ( ! $plugin || ! current_user_can( 'activate_plugin', $plugin ) ) {
			wp_die( esc_html__( 'This plugin cannot be activated by your account.', 'import-export-by-rockstarlab' ), '', [ 'response' => 403 ] );
		}
		$result = activate_plugin( $plugin );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', [ 'back_link' => true ] );
		}
		wp_safe_redirect( admin_url( 'admin.php?page=rsl-ie-agent-connection' ) );
		exit;
	}

	/** Render authenticated, secret-free connection information. */
	public function display() {
		$data = ( new Agent_Connection_Service() )->get_info();
		if ( is_wp_error( $data ) ) {
			wp_die( esc_html( $data->get_error_message() ), '', [ 'response' => 403 ] );
		}
		rsl_ie()->View->load( 'settings/agent-connection', $data );
	}
}

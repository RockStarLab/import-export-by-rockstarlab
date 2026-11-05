<?php
/**
 * Settings Controller
 *
 * Handles plugin settings operations
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

defined( 'ABSPATH' ) || exit;

/**
 * Handles plugin settings operations.
 */
class Settings_Controller extends Base_Controller {

	/**
	 * Register AJAX and admin-post settings actions.
	 *
	 * @return void
	 */
	public function init() {
		parent::init();
		add_action( 'admin_post_rsl_ie_save_admin_menu_settings', [ $this, 'save_admin_menu_settings' ] );
		add_action( 'admin_post_rsl_ie_save_content_list_settings', [ $this, 'save_content_list_settings' ] );
		add_action( 'admin_post_rsl_ie_save_button_location_settings', [ $this, 'save_button_location_settings' ] );
	}

	/**
	 * Get AJAX actions
	 *
	 * @return array
	 */
	protected function get_ajax_actions() {
		return [
			'settings_save'          => [ 'callback' => 'save_settings' ],
			'dismiss_pro_promo'      => [ 'callback' => 'dismiss_pro_promo' ],
			'test_openai_connection' => [ 'callback' => 'test_openai_connection' ],
		];
	}

	/**
	 * Verify the configured OpenAI credential with a lightweight API request.
	 *
	 * @return void
	 */
	public function test_openai_connection() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify, null, 403 );
			return;
		}

		$key    = trim( (string) $this->get_request_param( 'api_key', '' ) );
		$input  = '' !== $key && false === strpos( $key, '...' ) ? [ 'api_key' => $key ] : [];
		$result = ( new \RockStarLab\ImportExport\Service\Settings_Service() )->execute( 'ai-key-test', $input );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result, null, $result->get_error_data()['status'] ?? 400 );
			return; }
		$this->send_success( $result );
	}

	/**
	 * Save plugin settings.
	 */
	public function save_settings() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message() );
			return;
		}

		if ( \RockStarLab\ImportExport\Helper\OpenAI_API_Key::is_wp7_plus() ) {
			$result = ( new \RockStarLab\ImportExport\Service\Settings_Service() )->execute( 'ai-key-delete', [ 'legacy' => true ] );
			if ( is_wp_error( $result ) ) {
				$this->send_error( $result, null, 500 );
				return; }
			$this->send_success( [ 'message' => __( 'WordPress AI Client is managed through Connectors on WordPress 7.0 and newer.', 'import-export-by-rockstarlab' ) ] );
			return;
		}
		$key    = (string) $this->get_request_param( 'openai_api_key', '' );
		$result = ( new \RockStarLab\ImportExport\Service\Settings_Service() )->execute( '' === $key ? 'ai-key-delete' : 'ai-key-set', '' === $key ? [] : [ 'api_key' => $key ] );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result, null, $result->get_error_data()['status'] ?? 400 );
			return; }
		$this->send_success( [ 'message' => __( 'Settings saved successfully', 'import-export-by-rockstarlab' ) ] );
	}

	/**
	 * Save general plugin and admin-menu settings.
	 *
	 * @return void
	 */
	public function save_admin_menu_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to manage settings.', 'import-export-by-rockstarlab' ),
				'',
				[ 'response' => 403 ]
			);
		}

		check_admin_referer( 'rsl_ie_save_admin_menu_settings' );

		$menu_title    = isset( $_POST['menu_title'] )
			? sanitize_text_field( wp_unslash( $_POST['menu_title'] ) )
			: '';
		$visible_items = isset( $_POST['visible_items'] ) && is_array( $_POST['visible_items'] )
			? array_map( 'sanitize_key', wp_unslash( $_POST['visible_items'] ) )
			: [];

		$this->save_patch(
			[
				'menu_title'    => $menu_title,
				'visible_items' => $visible_items,
			]
		);

		wp_safe_redirect(
			add_query_arg(
				[
					'settings-updated' => 'true',
					'_wpnonce'         => wp_create_nonce( 'rsl_ie_plugin_settings_notice' ),
				],
				admin_url( 'admin.php?page=rsl-ie-plugin-settings' )
			)
		);
		exit;
	}

	/**
	 * Save post-list display options.
	 *
	 * @return void
	 */
	public function save_content_list_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to manage settings.', 'import-export-by-rockstarlab' ),
				'',
				[ 'response' => 403 ]
			);
		}

		check_admin_referer( 'rsl_ie_save_content_list_settings' );

		$enabled = isset( $_POST['show_tree_action'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['show_tree_action'] ) );
		$this->save_patch( [ 'show_tree_action' => $enabled ] );

		wp_safe_redirect(
			add_query_arg(
				[
					'settings-updated' => 'true',
					'_wpnonce'         => wp_create_nonce( 'rsl_ie_plugin_settings_notice' ),
				],
				admin_url( 'admin.php?page=rsl-ie-plugin-settings' )
			)
		);
		exit;
	}

	/**
	 * Save export and sync quick-action button location settings.
	 *
	 * @return void
	 */
	public function save_button_location_settings() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'You do not have permission to manage settings.', 'import-export-by-rockstarlab' ),
				'',
				[ 'response' => 403 ]
			);
		}

		check_admin_referer( 'rsl_ie_save_button_location_settings' );

		$export_locations = isset( $_POST['export_button_locations'] ) && is_array( $_POST['export_button_locations'] )
			? wp_unslash( $_POST['export_button_locations'] )
			: [];
		$sync_locations   = isset( $_POST['sync_button_locations'] ) && is_array( $_POST['sync_button_locations'] )
			? wp_unslash( $_POST['sync_button_locations'] )
			: [];

		$this->save_patch(
			[
				'export_button_locations' => $export_locations,
				'sync_button_locations'   => $sync_locations,
			]
		);

		wp_safe_redirect(
			add_query_arg(
				[
					'settings-updated' => 'true',
					'_wpnonce'         => wp_create_nonce( 'rsl_ie_plugin_settings_notice' ),
				],
				admin_url( 'admin.php?page=rsl-ie-button-settings' )
			)
		);
		exit;
	}

	/**
	 * Permanently dismiss the PRO promo card for the current user.
	 */
	public function dismiss_pro_promo() {
		$verify = $this->verify_request();
		if ( is_wp_error( $verify ) ) {
			$this->send_error( $verify->get_error_message(), null, 403 );
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			$this->send_error( __( 'You do not have permission to perform this action', 'import-export-by-rockstarlab' ), null, 403 );
		}

		$context = strtolower( trim( (string) $this->get_request_param( 'context', '' ) ) );
		$allowed = [ 'import', 'export', 'updater', 'migration' ];
		if ( ! in_array( $context, $allowed, true ) ) {
			$this->send_error( __( 'Invalid context', 'import-export-by-rockstarlab' ), null, 400 );
		}

		$result = ( new \RockStarLab\ImportExport\Service\Settings_Service() )->execute( 'preferences-update', [ 'dismiss_pro_promo' => $context ] );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result, null, 400 );
			return; }

		$this->send_success(
			[
				'dismissed' => true,
			]
		);
	}

	/**
	 * Save a settings patch after the admin-post nonce check.
	 *
	 * @param array $input Settings patch.
	 * @return void
	 */
	private function save_patch( array $input ) {
		$result = ( new \RockStarLab\ImportExport\Service\Settings_Service() )->execute( 'settings-update', $input );
		if ( is_wp_error( $result ) ) {
			wp_die( esc_html( $result->get_error_message() ), '', [ 'response' => (int) ( $result->get_error_data()['status'] ?? 400 ) ] ); }
	}
}

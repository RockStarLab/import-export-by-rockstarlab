<?php
/**
 * Shared settings and preferences workflows.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Settings_Catalog;
use RockStarLab\ImportExport\Helper\Admin_Menu_Settings;
use RockStarLab\ImportExport\Helper\Button_Location_Settings;
use RockStarLab\ImportExport\Helper\OpenAI_API_Key;

defined( 'ABSPATH' ) || exit;

/** Fixed allowlists; current-user preferences and credential-free snapshots. */
class Settings_Service {
	/**
	 * Validate a direct or Abilities request.
	 *
	 * @param string $name Operation.
	 * @param array  $input Input.
	 * @return mixed
	 */
	public function execute( $name, array $input = [] ) {
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access; }
		$definition = Settings_Catalog::definitions()[ $name ] ?? null;
		if ( ! $definition ) {
			return $this->error( 'operation', 400 ); }
		$valid = rest_validate_value_from_schema( $input, $definition['input'], 'input' );
		if ( is_wp_error( $valid ) ) {
			$valid->add_data( [ 'status' => 400 ] );
			return $valid; }
		if ( 0 === strpos( $name, 'site-key-' ) || 'connection-key-rotate' === $name ) {
			return ( new Credential_Service() )->execute( $name, $input );
		}
		if ( 'settings-get' === $name ) {
			return $this->settings(); }
		if ( 'preferences-get' === $name ) {
			return $this->preferences(); }
		$lock = Job_Lock::acquire_resource( 'settings', 'current' );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return $this->error( 'busy', 409 ); }
		try {
			if ( 'settings-update' === $name ) {
				return $this->update_settings( $input ); }
			if ( 'preferences-update' === $name ) {
				return $this->update_preferences( $input ); }
			return $this->ai( $name, $input );
		} finally {
			Job_Lock::release( $lock ); }
	}

	/**
	 * Read normalized public configuration.
	 *
	 * @return array
	 */
	private function settings() {
		$menu    = Admin_Menu_Settings::get_settings();
		$buttons = Button_Location_Settings::get_settings();
		return [
			'menu_title'                 => $menu['menu_title'],
			'visible_items'              => array_values( array_keys( array_filter( $menu['visible_items'] ) ) ),
			'show_tree_action'           => (bool) $menu['show_tree_action'],
			'export_button_locations'    => array_keys( array_filter( $buttons['export_button_locations'] ) ),
			'sync_button_locations'      => array_keys( array_filter( $buttons['sync_button_locations'] ) ),
			'available_menu_items'       => array_keys( Admin_Menu_Settings::get_menu_items() ),
			'available_button_locations' => array_keys( $buttons['export_button_locations'] ),
			'ai'                         => $this->ai_status(),
		];
	}

	/**
	 * Validate every field before writing partial settings.
	 *
	 * @param array $input Patch.
	 * @return mixed
	 */
	private function update_settings( array $input ) {
		$before = $this->settings();
		foreach ( [ 'visible_items', 'export_button_locations', 'sync_button_locations' ] as $key ) {
			if ( isset( $input[ $key ] ) && ( count( $input[ $key ] ) !== count( array_unique( $input[ $key ] ) ) || array_diff( $input[ $key ], $before[ 'visible_items' === $key ? 'available_menu_items' : 'available_button_locations' ] ) ) ) {
				return $this->error( 'invalid_choice', 400 );
			}
		}
		if ( isset( $input['menu_title'] ) || isset( $input['visible_items'] ) ) {
			Admin_Menu_Settings::save( $input['menu_title'] ?? $before['menu_title'], $input['visible_items'] ?? $before['visible_items'] );
		}
		if ( isset( $input['show_tree_action'] ) ) {
			Admin_Menu_Settings::save_show_tree_action( $input['show_tree_action'] ); }
		if ( isset( $input['export_button_locations'] ) || isset( $input['sync_button_locations'] ) ) {
			Button_Location_Settings::save( $input['export_button_locations'] ?? $before['export_button_locations'], $input['sync_button_locations'] ?? $before['sync_button_locations'] );
		}
		$after = $this->settings();
		foreach ( $input as $key => $expected ) {
			$actual = $after[ $key ];
			if ( 'menu_title' === $key ) {
				$expected = sanitize_text_field( $expected );
				if ( '' === $expected ) {
					$expected = __( 'Import Export by RockStarLab', 'import-export-by-rockstarlab' ); }
			}
			if ( is_array( $expected ) ) {
				sort( $expected );
				sort( $actual ); }
			if ( $actual !== $expected ) {
				return $this->error( 'write_failed', 500 ); }
		}
		return $after;
	}

	/**
	 * Read current-user flags; review is intentionally site-wide.
	 *
	 * @return array
	 */
	private function preferences() {
		$user   = get_current_user_id();
		$promos = [];
		foreach ( [ 'import', 'export', 'updater', 'migration' ] as $context ) {
			if ( get_user_meta( $user, 'rsl_ie_dismiss_pro_promo_' . $context, true ) ) {
				$promos[] = $context; }
		}
		$until = (int) get_user_meta( $user, 'rsl_ie_media_hash_notice_snoozed_until', true );
		return [
			'dismissed_pro_promos'      => $promos,
			'migration_intro_dismissed' => (bool) get_user_meta( $user, 'rsl_ie_dismiss_content_migration_intro', true ),
			'media_hash_notice'         => get_user_meta( $user, 'rsl_ie_dismiss_media_hash_notice', true ) ? 'dismiss' : ( $until > time() ? 'snooze' : 'show' ),
			'media_hash_snoozed_until'  => $until,
			'review_notice_dismissed'   => (bool) get_option( 'rsl_ie_review_dismissed', false ),
		];
	}

	/**
	 * Write only documented preference fields.
	 *
	 * @param array $input Patch.
	 * @return array|\WP_Error
	 */
	private function update_preferences( array $input ) {
		if ( isset( $input['dismiss_pro_promo'] ) ) {
			$input['dismissed_pro_promos'] = array_values( array_unique( array_merge( $input['dismissed_pro_promos'] ?? $this->preferences()['dismissed_pro_promos'], [ $input['dismiss_pro_promo'] ] ) ) );
			unset( $input['dismiss_pro_promo'] );
		}
		$user = get_current_user_id();
		if ( isset( $input['dismissed_pro_promos'] ) ) {
			foreach ( [ 'import', 'export', 'updater', 'migration' ] as $context ) {
				update_user_meta( $user, 'rsl_ie_dismiss_pro_promo_' . $context, in_array( $context, $input['dismissed_pro_promos'], true ) ? 1 : 0 );
			}
		}
		if ( isset( $input['migration_intro_dismissed'] ) ) {
			update_user_meta( $user, 'rsl_ie_dismiss_content_migration_intro', $input['migration_intro_dismissed'] ? 1 : 0 ); }
		if ( isset( $input['review_notice_dismissed'] ) ) {
			update_option( 'rsl_ie_review_dismissed', $input['review_notice_dismissed'] ? 1 : 0, false ); }
		if ( isset( $input['media_hash_notice'] ) ) {
			update_user_meta( $user, 'rsl_ie_dismiss_media_hash_notice', 'dismiss' === $input['media_hash_notice'] ? 1 : 0 );
			if ( 'snooze' === $input['media_hash_notice'] ) {
				update_user_meta( $user, 'rsl_ie_media_hash_notice_snoozed_until', time() + WEEK_IN_SECONDS ); } else {
				delete_user_meta( $user, 'rsl_ie_media_hash_notice_snoozed_until' ); }
		}
		$after = $this->preferences();
		foreach ( $input as $key => $expected ) {
			$actual = $after[ $key ];
			if ( is_array( $expected ) ) {
				$expected = array_values( array_unique( $expected ) );
				sort( $expected );
				sort( $actual ); }
			if ( $actual !== $expected ) {
				return $this->error( 'preference_write_failed', 500 ); }
		}
		return $after;
	}

	/**
	 * Describe AI configuration without reading secrets into public data.
	 *
	 * @return array
	 */
	private function ai_status() {
		$core      = OpenAI_API_Key::is_wp7_plus();
		$providers = [];
		if ( $core && function_exists( 'wp_get_connectors' ) ) {
			foreach ( wp_get_connectors() as $id => $connector ) {
				if ( 'ai_provider' !== $connector['type'] || 'api_key' !== ( $connector['authentication']['method'] ?? '' ) ) {
					continue; }
				$auth        = $connector['authentication'];
				$active      = isset( $connector['plugin']['is_active'] ) && is_callable( $connector['plugin']['is_active'] ) && call_user_func( $connector['plugin']['is_active'] );
				$source      = function_exists( '_wp_connectors_get_api_key_source' ) && ! empty( $auth['setting_name'] ) ? _wp_connectors_get_api_key_source( $auth['setting_name'], $auth['env_var_name'] ?? '', $auth['constant_name'] ?? '' ) : 'none';
				$registered  = get_registered_settings()[ $auth['setting_name'] ?? '' ] ?? [];
				$providers[] = [
					'id'         => $id,
					'label'      => $connector['name'],
					'active'     => (bool) $active,
					'configured' => 'none' !== $source,
					'source'     => $source,
					'writable'   => $active && ! empty( $registered['show_in_rest'] ) && ! in_array( $source, [ 'env', 'constant' ], true ),
				];
			}
		} elseif ( ! $core ) {
			$configured  = '' !== (string) get_option( 'rsl_ie_openai_api_key', '' );
			$providers[] = [
				'id'         => 'openai',
				'label'      => 'OpenAI',
				'active'     => true,
				'configured' => $configured,
				'source'     => $configured ? 'database' : 'none',
				'writable'   => true,
			];
		}
		return [
			'mode'       => $core ? 'wordpress_ai_client' : 'plugin_openai',
			'configured' => (bool) array_filter(
				$providers,
				static function ( $provider ) {
					return $provider['active'] && $provider['configured']; }
			),
			'setup_url'  => admin_url( $core ? 'options-connectors.php' : 'admin.php?page=rsl-ie-plugin-options' ),
			'providers'  => $providers,
		];
	}

	/**
	 * Save/delete a selected key or test the existing resolver.
	 *
	 * @param string $name Operation.
	 * @param array  $input Input.
	 * @return mixed
	 */
	private function ai( $name, array $input ) {
		if ( 'ai-key-test' === $name ) {
			if ( empty( $input['api_key'] ) ) {
				$result = ( new \RockStarLab\ImportExport\Helper\AI_Content_Extractor() )->test_connection();
				return is_wp_error( $result ) ? $this->error( 'ai_test_failed', 502 ) : [
					'connected' => true,
					'message'   => 'Connection successful.',
				];
			}
			$response = wp_remote_get(
				'https://api.openai.com/v1/models',
				[
					'timeout'     => 15,
					'redirection' => 0,
					'headers'     => [
						'Authorization' => 'Bearer ' . $input['api_key'],
						'Accept'        => 'application/json',
					],
				]
			);
			$status   = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
			return $status >= 200 && $status < 300 ? [
				'connected' => true,
				'message'   => 'Connection successful.',
			] : $this->error( 'ai_test_failed', 502 );
		}
		if ( 'ai-key-delete' === $name && ! empty( $input['legacy'] ) ) {
			update_option( 'rsl_ie_openai_api_key', '', false );
			return '' === (string) get_option( 'rsl_ie_openai_api_key', '' ) ? $this->ai_status() : $this->error( 'ai_write_failed', 500 );
		}
		$id       = $input['provider'] ?? 'openai';
		$provider = null;
		foreach ( $this->ai_status()['providers'] as $candidate ) {
			if ( $id === $candidate['id'] ) {
				$provider = $candidate;
				break; }
		}
		if ( ! $provider || ! $provider['writable'] ) {
			return $this->error( 'ai_provider_not_writable', 409 ); }
		$key = $input['api_key'] ?? '';
		if ( 'ai-key-set' === $name && 'openai' === $id && ( 0 !== strpos( $key, 'sk-' ) || false !== strpos( $key, '...' ) ) ) {
			return $this->error( 'ai_key_format', 400 ); }
		$option = OpenAI_API_Key::is_wp7_plus() ? wp_get_connector( $id )['authentication']['setting_name'] : 'rsl_ie_openai_api_key';
		if ( 'ai-key-delete' === $name ) {
			update_option( $option, '', false ); } else {
			update_option( $option, sanitize_text_field( $key ), false ); }
			if ( (string) get_option( $option, '' ) !== ( 'ai-key-delete' === $name ? '' : sanitize_text_field( $key ) ) ) {
				return $this->error( 'ai_write_failed', 500 ); }
			return $this->ai_status();
	}

	/**
	 * Return a redacted stable error.
	 *
	 * @param string $code Suffix.
	 * @param int    $status HTTP status.
	 * @return \WP_Error
	 */
	private function error( $code, $status ) {
		return new \WP_Error( 'rsl_ie_settings_' . $code, 'Settings operation failed: ' . $code, [ 'status' => $status ] );
	}
}

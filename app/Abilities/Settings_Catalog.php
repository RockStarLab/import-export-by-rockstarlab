<?php
/**
 * Settings, preferences and explicit credential contracts.
 *
 * @package RockStarLab\ImportExport\Abilities
 */

namespace RockStarLab\ImportExport\Abilities;

defined( 'ABSPATH' ) || exit;

/** General reads never return a credential. */
class Settings_Catalog {
	/**
	 * Return operation schemas.
	 *
	 * @return array
	 */
	public static function definitions() {
		$s           = array( 'type' => 'string' );
		$b           = array( 'type' => 'boolean' );
		$n           = Workflow_Schemas::integer();
		$strings     = Workflow_Schemas::items( $s + array( 'maxLength' => 200 ), 10000 );
		$fields      = array(
			'menu_title'              => $s + array( 'maxLength' => 100 ),
			'visible_items'           => $strings,
			'show_tree_action'        => $b,
			'export_button_locations' => $strings,
			'sync_button_locations'   => $strings,
		);
		$ai          = Workflow_Schemas::object(
			array(
				'mode'       => $s,
				'configured' => $b,
				'setup_url'  => $s,
				'providers'  => Workflow_Schemas::items(
					Workflow_Schemas::object(
						array(
							'id'         => $s,
							'label'      => $s,
							'active'     => $b,
							'configured' => $b,
							'writable'   => $b,
							'source'     => $s,
						),
						array( 'id', 'label', 'active', 'configured', 'writable', 'source' )
					)
				),
			),
			array( 'mode', 'configured', 'setup_url', 'providers' )
		);
		$settings    = Workflow_Schemas::object(
			$fields + array(
				'available_menu_items'       => $strings,
				'available_button_locations' => $strings,
				'ai'                         => $ai,
			),
			array_merge( array_keys( $fields ), array( 'available_menu_items', 'available_button_locations', 'ai' ) )
		);
		$prefs       = array(
			'dismissed_pro_promos'      => Workflow_Schemas::items( $s + array( 'enum' => array( 'import', 'export', 'updater', 'migration' ) ), 4 ),
			'migration_intro_dismissed' => $b,
			'media_hash_notice'         => $s + array( 'enum' => array( 'show', 'snooze', 'dismiss' ) ),
			'review_notice_dismissed'   => $b,
		);
		$preferences = Workflow_Schemas::object( $prefs + array( 'media_hash_snoozed_until' => $n ), array_merge( array_keys( $prefs ), array( 'media_hash_snoozed_until' ) ) );
		$status      = Workflow_Schemas::object(
			array(
				'configured'   => $b,
				'bound'        => $b,
				'owner_active' => $b,
				'site_url'     => $s,
				'site_name'    => $s,
			),
			array( 'configured', 'bound', 'owner_active', 'site_url', 'site_name' )
		);
		$secret      = Workflow_Schemas::object(
			array(
				'site_key'  => $s,
				'site_url'  => $s,
				'site_name' => $s,
			),
			array( 'site_key', 'site_url', 'site_name' )
		);
		$provider    = array(
			'provider' => $s + array(
				'pattern'   => '^[a-z0-9_-]+$',
				'maxLength' => 100,
			),
		);
		$key         = $s + array(
			'minLength' => 11,
			'maxLength' => 4096,
			'pattern'   => '^\\S+$',
		);
		$result      = array();
		foreach ( array(
			'settings-get'          => array( Schema_Registry::empty_input(), $settings, true, false, 'Read plugin menu and button settings, available choices and AI credential status. Never returns secrets.' ),
			'settings-update'       => array( Workflow_Schemas::object( $fields ), $settings, false, false, 'Patch plugin settings. Omitted fields are preserved; supplied lists replace the enabled choices. Does not accept arbitrary option names or credentials.' ),
			'preferences-get'       => array( Schema_Registry::empty_input(), $preferences, true, false, 'Read the current user notice preferences. review_notice_dismissed is site-wide, matching the native review notice.' ),
			'preferences-update'    => array(
				Workflow_Schemas::object(
					$prefs + array(
						'dismiss_pro_promo' => array(
							'type' => 'string',
							'enum' => array( 'import', 'export', 'updater', 'migration' ),
						),
					)
				),
				$preferences,
				false,
				false,
				'Patch current-user preferences. Supplied dismissed_pro_promos replaces that list; dismiss_pro_promo atomically adds one context. snooze lasts one week; show clears dismissal and snooze. Review dismissal is site-wide.',
			),
			'ai-key-set'            => array( Workflow_Schemas::object( $provider + array( 'api_key' => $key ), array( 'api_key' ) ), $ai, false, true, 'Store a credential without echoing it. Default provider is openai. Before WP 7 only the plugin OpenAI key is supported. WP 7 uses the selected active core AI connector and its registered setting. Environment/constant credentials cannot be overwritten. This saves configuration; test it separately.' ),
			'ai-key-delete'         => array( Workflow_Schemas::object( $provider + array( 'legacy' => $b ) ), $ai, false, true, 'Remove the selected stored AI credential. WP 7 removes its core connector setting; earlier versions remove the plugin OpenAI key. legacy=true clears only the unused plugin key on WP 7. Does not revoke the token at the external provider.' ),
			'ai-key-test'           => array(
				Workflow_Schemas::object( array( 'api_key' => $key ) ),
				Workflow_Schemas::object(
					array(
						'connected' => $b,
						'message'   => $s,
					),
					array( 'connected', 'message' )
				),
				false,
				false,
				'With api_key, test an unsaved OpenAI credential using the models endpoint. Without api_key, test the existing plugin AI resolver, which may incur provider usage. Does not save a key. Provider errors are redacted.',
			),
			'site-key-status'       => array( Schema_Registry::empty_input(), $status, true, false, 'Read incoming Content Sync key and owner status without creating, revealing or rebinding the key.' ),
			'site-key-create'       => array( Schema_Registry::empty_input(), $status, false, false, 'Create an incoming Content Sync key only if absent, and bind an unbound key to the current administrator. Existing bindings are preserved. Use site-key-reveal to obtain the secret.' ),
			'site-key-reveal'       => array( Schema_Registry::empty_input(), $secret, false, false, 'Explicit secret read: return the existing incoming Content Sync bearer key. Does not create a missing key. An unbound legacy key is bound to the current administrator. Keep the result out of logs and public messages.' ),
			'site-key-rotate'       => array( Schema_Registry::empty_input(), $secret, false, true, 'Replace the incoming Content Sync key and bind it to the current administrator. All peers using the old key must be updated. Returns the new secret; do not blindly retry or publish the result.' ),
			'connection-key-rotate' => array(
				Workflow_Schemas::object( array( 'site_id' => Workflow_Schemas::integer( 1 ) ), array( 'site_id' ) ),
				Workflow_Schemas::object(
					array(
						'api_key' => $s,
						'message' => $s,
					),
					array( 'api_key', 'message' )
				),
				false,
				true,
				'Preserve the native regenerate-key action: replace the credential stored locally on this connection with a generated secret. It does NOT rotate the remote site key. Prefer rotating the peer site-key and applying that value with connection-update. Returns a secret; do not blindly retry.',
			),
		) as $name => $definition ) {
			$result[ $name ] = array(
				'label'       => ucwords( str_replace( '-', ' ', $name ) ),
				'input'       => $definition[0],
				'output'      => $definition[1],
				'readonly'    => $definition[2],
				'destructive' => $definition[3],
				'idempotent'  => $definition[2],
				'description' => $definition[4],
			);
		}
		return $result;
	}
}

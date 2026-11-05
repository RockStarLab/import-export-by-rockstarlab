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
		$s           = [ 'type' => 'string' ];
		$b           = [ 'type' => 'boolean' ];
		$n           = Workflow_Schemas::integer();
		$strings     = Workflow_Schemas::items( $s + [ 'maxLength' => 200 ], 10000 );
		$fields      = [
			'menu_title'              => $s + [ 'maxLength' => 100 ],
			'visible_items'           => $strings,
			'show_tree_action'        => $b,
			'export_button_locations' => $strings,
			'sync_button_locations'   => $strings,
		];
		$ai          = Workflow_Schemas::object(
			[
				'mode'       => $s,
				'configured' => $b,
				'setup_url'  => $s,
				'providers'  => Workflow_Schemas::items(
					Workflow_Schemas::object(
						[
							'id'         => $s,
							'label'      => $s,
							'active'     => $b,
							'configured' => $b,
							'writable'   => $b,
							'source'     => $s,
						],
						[ 'id', 'label', 'active', 'configured', 'writable', 'source' ]
					)
				),
			],
			[ 'mode', 'configured', 'setup_url', 'providers' ]
		);
		$settings    = Workflow_Schemas::object(
			$fields + [
				'available_menu_items'       => $strings,
				'available_button_locations' => $strings,
				'ai'                         => $ai,
			],
			array_merge( array_keys( $fields ), [ 'available_menu_items', 'available_button_locations', 'ai' ] )
		);
		$prefs       = [
			'dismissed_pro_promos'      => Workflow_Schemas::items( $s + [ 'enum' => [ 'import', 'export', 'updater', 'migration' ] ], 4 ),
			'migration_intro_dismissed' => $b,
			'media_hash_notice'         => $s + [ 'enum' => [ 'show', 'snooze', 'dismiss' ] ],
			'review_notice_dismissed'   => $b,
		];
		$preferences = Workflow_Schemas::object( $prefs + [ 'media_hash_snoozed_until' => $n ], array_merge( array_keys( $prefs ), [ 'media_hash_snoozed_until' ] ) );
		$status      = Workflow_Schemas::object(
			[
				'configured'   => $b,
				'bound'        => $b,
				'owner_active' => $b,
				'site_url'     => $s,
				'site_name'    => $s,
			],
			[ 'configured', 'bound', 'owner_active', 'site_url', 'site_name' ]
		);
		$secret      = Workflow_Schemas::object(
			[
				'site_key'  => $s,
				'site_url'  => $s,
				'site_name' => $s,
			],
			[ 'site_key', 'site_url', 'site_name' ]
		);
		$provider    = [
			'provider' => $s + [
				'pattern'   => '^[a-z0-9_-]+$',
				'maxLength' => 100,
			],
		];
		$key         = $s + [
			'minLength' => 11,
			'maxLength' => 4096,
			'pattern'   => '^\\S+$',
		];
		$result      = [];
		foreach ( [
			'settings-get'          => [ Schema_Registry::empty_input(), $settings, true, false, 'Read plugin menu and button settings, available choices and AI credential status. Never returns secrets.' ],
			'settings-update'       => [ Workflow_Schemas::object( $fields ), $settings, false, false, 'Patch plugin settings. Omitted fields are preserved; supplied lists replace the enabled choices. Does not accept arbitrary option names or credentials.' ],
			'preferences-get'       => [ Schema_Registry::empty_input(), $preferences, true, false, 'Read the current user notice preferences. review_notice_dismissed is site-wide, matching the native review notice.' ],
			'preferences-update'    => [
				Workflow_Schemas::object(
					$prefs + [
						'dismiss_pro_promo' => [
							'type' => 'string',
							'enum' => [ 'import', 'export', 'updater', 'migration' ],
						],
					]
				),
				$preferences,
				false,
				false,
				'Patch current-user preferences. Supplied dismissed_pro_promos replaces that list; dismiss_pro_promo atomically adds one context. snooze lasts one week; show clears dismissal and snooze. Review dismissal is site-wide.',
			],
			'ai-key-set'            => [ Workflow_Schemas::object( $provider + [ 'api_key' => $key ], [ 'api_key' ] ), $ai, false, true, 'Store a credential without echoing it. Default provider is openai. Before WP 7 only the plugin OpenAI key is supported. WP 7 uses the selected active core AI connector and its registered setting. Environment/constant credentials cannot be overwritten. This saves configuration; test it separately.' ],
			'ai-key-delete'         => [ Workflow_Schemas::object( $provider + [ 'legacy' => $b ] ), $ai, false, true, 'Remove the selected stored AI credential. WP 7 removes its core connector setting; earlier versions remove the plugin OpenAI key. legacy=true clears only the unused plugin key on WP 7. Does not revoke the token at the external provider.' ],
			'ai-key-test'           => [
				Workflow_Schemas::object( [ 'api_key' => $key ] ),
				Workflow_Schemas::object(
					[
						'connected' => $b,
						'message'   => $s,
					],
					[ 'connected', 'message' ]
				),
				false,
				false,
				'With api_key, test an unsaved OpenAI credential using the models endpoint. Without api_key, test the existing plugin AI resolver, which may incur provider usage. Does not save a key. Provider errors are redacted.',
			],
			'site-key-status'       => [ Schema_Registry::empty_input(), $status, true, false, 'Read incoming Content Sync key and owner status without creating, revealing or rebinding the key.' ],
			'site-key-create'       => [ Schema_Registry::empty_input(), $status, false, false, 'Create an incoming Content Sync key only if absent, and bind an unbound key to the current administrator. Existing bindings are preserved. Use site-key-reveal to obtain the secret.' ],
			'site-key-reveal'       => [ Schema_Registry::empty_input(), $secret, false, false, 'Explicit secret read: return the existing incoming Content Sync bearer key. Does not create a missing key. An unbound legacy key is bound to the current administrator. Keep the result out of logs and public messages.' ],
			'site-key-rotate'       => [ Schema_Registry::empty_input(), $secret, false, true, 'Replace the incoming Content Sync key and bind it to the current administrator. All peers using the old key must be updated. Returns the new secret; do not blindly retry or publish the result.' ],
			'connection-key-rotate' => [
				Workflow_Schemas::object( [ 'site_id' => Workflow_Schemas::integer( 1 ) ], [ 'site_id' ] ),
				Workflow_Schemas::object(
					[
						'api_key' => $s,
						'message' => $s,
					],
					[ 'api_key', 'message' ]
				),
				false,
				true,
				'Preserve the native regenerate-key action: replace the credential stored locally on this connection with a generated secret. It does NOT rotate the remote site key. Prefer rotating the peer site-key and applying that value with connection-update. Returns a secret; do not blindly retry.',
			],
		] as $name => $definition ) {
			$result[ $name ] = [
				'label'       => ucwords( str_replace( '-', ' ', $name ) ),
				'input'       => $definition[0],
				'output'      => $definition[1],
				'readonly'    => $definition[2],
				'destructive' => $definition[3],
				'idempotent'  => $definition[2],
				'description' => $definition[4],
			];
		}
		return $result;
	}
}

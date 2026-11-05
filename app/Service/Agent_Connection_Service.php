<?php
/**
 * Inspect registered official MCP servers without starting an inactive adapter.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Ability_Registry;

defined( 'ABSPATH' ) || exit;

/** Secret-free setup data for administrators. */
class Agent_Connection_Service {
	/**
	 * Initialize registered REST hooks, then inspect actual routes and tools.
	 *
	 * @return array|\WP_Error
	 */
	public function get_info() {
		$permission = Access_Policy::check_management();
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		if ( ! Ability_Registry::is_enabled() ) {
			return [ 'abilities_enabled' => false ];
		}
		$routes  = rest_get_server()->get_routes();
		$adapter = '\\WP\\MCP\\Core\\McpAdapter';
		$loaded  = class_exists( $adapter );
		$servers = [];
		// A vendored class alone is not an enabled MCP server. Never bootstrap it here.
		if ( $loaded && did_action( 'mcp_adapter_init' ) && method_exists( $adapter, 'get_servers' ) ) {
			foreach ( $adapter::instance()->get_servers() as $server ) {
				$route    = '/' . trim( $server->get_server_route_namespace(), '/' ) . '/' . trim( $server->get_server_route(), '/' );
				$tools    = $this->get_server_tool_names( $server );
				$required = [ 'mcp-adapter-discover-abilities', 'mcp-adapter-get-ability-info', 'mcp-adapter-execute-ability' ];
				$modern   = array_map(
					static function ( $name ) {
						return str_replace( 'mcp-adapter-', 'mcp-adapter/', $name );
					},
					$required
				);
				if ( ! isset( $routes[ $route ] ) || ( array_diff( $required, $tools ) && array_diff( $modern, $tools ) ) ) {
					continue;
				}
				$servers[] = [
					'tools' => array_diff( $required, $tools ) ? $modern : $required,
					'id'    => $server->get_server_id(),
					'url'   => rest_url( ltrim( $route, '/' ) ),
				];
			}
		}
		require_once ABSPATH . 'wp-admin/includes/plugin.php';
		$adapter_plugin = '';
		foreach ( get_plugins() as $file => $plugin ) {
			if ( 'mcp-adapter/mcp-adapter.php' === $file || ( 'mcp-adapter' === ( $plugin['TextDomain'] ?? '' ) && 'mcp-adapter.php' === basename( $file ) ) ) {
				$adapter_plugin = $file;
				break;
			}
		}
		$capabilities = ( new Discovery_Service() )->get_capabilities();
		return [
			'abilities_enabled'               => true,
			'rest_status'                     => $this->check_rest(),
			'adapter_plugin'                  => $adapter_plugin,
			'adapter_active'                  => $adapter_plugin && is_plugin_active( $adapter_plugin ),
			'username'                        => wp_get_current_user()->user_login,
			'abilities_available'             => Ability_Registry::is_available(),
			'ability_count'                   => count( $capabilities['abilities'] ),
			'adapter_available'               => $loaded,
			'adapter_version'                 => $loaded && defined( $adapter . '::VERSION' ) ? constant( $adapter . '::VERSION' ) : '',
			'servers'                         => $servers,
			'rest_url'                        => rest_url( 'wp-abilities/v1/abilities' ),
			'application_passwords_available' => function_exists( 'wp_is_application_passwords_available_for_user' ) && wp_is_application_passwords_available_for_user( wp_get_current_user() ),
		];
	}

	/**
	 * Inspect tools using the server-owned schema on MCP Adapter 0.7.0+.
	 *
	 * @param object $server Registered MCP server.
	 * @return string[]
	 */
	private function get_server_tool_names( $server ) {
		if ( method_exists( $server, 'get_schemas' ) ) {
			// Use the baseline revision supported by the adapter's HTTP transport.
			$schema = $server->get_schemas()->forVersion( '2025-11-25' );
			return array_keys( $server->get_tools( $schema ) );
		}
		return array_keys( $server->get_tools() );
	}

	/** Probe an authenticated HTTP route, including REST policy and web-server failures. */
	public function check_rest() {
		$cookies = [];
		foreach ( [ LOGGED_IN_COOKIE, AUTH_COOKIE, SECURE_AUTH_COOKIE ] as $name ) {
			if ( isset( $_COOKIE[ $name ] ) ) {
				$cookies[] = new \WP_Http_Cookie(
					[
						'name'  => $name,
						'value' => wp_unslash( $_COOKIE[ $name ] ),
					]
				);
			}
		}
		$response = wp_remote_get(
			rest_url( 'wp-abilities/v1/abilities' ),
			[
				'timeout'     => 8,
				'redirection' => 0,
				'cookies'     => $cookies,
				'headers'     => [
					'X-WP-Nonce' => wp_create_nonce( 'wp_rest' ),
					'Accept'     => 'application/json',
				],
			]
		);
		if ( is_wp_error( $response ) ) {
			return [
				'available' => false,
				'message'   => $response->get_error_message(),
			];
		}
		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );
		$ok     = 200 === $status && is_array( $body ) && ! isset( $body['code'] );
		return [
			'available' => $ok,
			/* translators: %d: HTTP response status code. */
			'message'   => $ok ? __( 'The authenticated Abilities REST API responds correctly.', 'import-export-by-rockstarlab' ) : sprintf( __( 'The Abilities REST API check failed (HTTP %d). Check REST restrictions, security plugins, HTTPS and your server configuration.', 'import-export-by-rockstarlab' ), $status ),
		];
	}
}

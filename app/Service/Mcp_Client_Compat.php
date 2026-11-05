<?php
/**
 * MCP client compatibility for the WordPress MCP Adapter HTTP transport.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

/**
 * Some MCP clients (VS Code / GitHub Copilot) negotiate a protocol revision that
 * requires the MCP-Protocol-Version header but do not send it after initialization.
 * MCP Adapter then rejects every request of that session with HTTP 400. The session
 * already stores the negotiated revision, so a missing header is filled with exactly
 * that value. A header the client did send is never changed, and requests without a
 * valid session of the signed-in user are left alone.
 */
class Mcp_Client_Compat {

	/**
	 * Register the request filter.
	 */
	public static function hooks() {
		add_filter( 'rest_pre_dispatch', [ self::class, 'fill_protocol_version' ], 5, 3 );
	}

	/**
	 * Fill a missing MCP-Protocol-Version header from the session's negotiated revision.
	 *
	 * @param mixed            $result  Pre-dispatch result.
	 * @param \WP_REST_Server  $server  REST server.
	 * @param \WP_REST_Request $request Request.
	 * @return mixed Unchanged result.
	 */
	public static function fill_protocol_version( $result, $server, $request ) {
		if ( null !== $result || ! $request instanceof \WP_REST_Request || 'POST' !== $request->get_method() ) {
			return $result;
		}
		if ( 0 !== strpos( (string) $request->get_route(), '/mcp/' ) || null !== $request->get_header( 'mcp-protocol-version' ) ) {
			return $result;
		}
		$session_id = (string) $request->get_header( 'mcp-session-id' );
		$user_id    = get_current_user_id();
		$sessions   = '\WP\MCP\Transport\Infrastructure\SessionManager';
		$wire       = '\WP\MCP\Transport\Infrastructure\McpWireOrchestrator';
		if ( '' === $session_id || ! $user_id || ! class_exists( $sessions ) || ! class_exists( $wire )
			|| ! method_exists( $sessions, 'get_session' ) || ! method_exists( $wire, 'negotiated_protocol_version' ) ) {
			return $result;
		}
		$session = $sessions::get_session( $user_id, $session_id );
		if ( ! is_array( $session ) || ! is_array( $session['client_params'] ?? null ) ) {
			return $result;
		}
		$version = $wire::negotiated_protocol_version( $session['client_params'] );
		if ( is_string( $version ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $version ) ) {
			$request->set_header( 'mcp-protocol-version', $version );
		}
		return $result;
	}
}

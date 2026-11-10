<?php
/**
 * Servers screen (Jobs Log → Servers): saved FTP, FTPS and SFTP servers.
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Service\Remote_File_Service;

defined( 'ABSPATH' ) || exit;

/** Admin adapter for Remote_File_Service (administrators only). */
class Remote_Server_Controller extends Base_Controller {
	const PAGE = 'rsl-ie-servers';

	/** Register AJAX actions, the hidden page and form handlers. */
	public function init() {
		parent::init();
		add_action( 'admin_menu', [ $this, 'register_page' ], 20 );
		add_filter( 'parent_file', [ $this, 'parent_file' ] );
		add_filter( 'submenu_file', [ $this, 'submenu_file' ] );
		add_action( 'admin_post_rsl_ie_save_remote_server', [ $this, 'save' ] );
		add_action( 'admin_post_rsl_ie_delete_remote_server', [ $this, 'delete' ] );
		add_action( 'admin_post_rsl_ie_test_remote_server', [ $this, 'test' ] );
	}

	/**
	 * AJAX actions used by the import and export screens.
	 *
	 * @return array
	 */
	protected function get_ajax_actions() {
		return [
			'remote_browse' => [ 'callback' => 'browse' ],
		];
	}

	/** List one folder of a saved server. */
	public function browse() {
		$permission = $this->verify_request();
		if ( is_wp_error( $permission ) ) {
			$this->send_error( $permission, null, 403 );
			return;
		}
		$id     = sanitize_key( (string) $this->get_request_param( 'server_id', '' ) );
		$result = ( new Remote_File_Service() )->browse( $id, (string) $this->get_request_param( 'path', '/' ) );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result, null, 400 );
			return;
		}
		foreach ( $result['items'] as &$item ) {
			$item['path']    = rtrim( $result['path'], '/' ) . '/' . $item['name'];
			$item['address'] = Remote_File_Service::address( $id, $item['path'] ) . ( 'dir' === $item['type'] ? '/' : '' );
		}
		unset( $item );
		$this->send_success( $result );
	}

	/** Hidden page reached from the Jobs Log tabs. */
	public function register_page() {
		add_submenu_page( null, __( 'Servers', 'import-export-by-rockstarlab' ), __( 'Servers', 'import-export-by-rockstarlab' ), 'manage_options', self::PAGE, [ $this, 'display' ] );
		remove_submenu_page( '', self::PAGE );
	}

	/**
	 * Highlight the plugin menu.
	 *
	 * @param string $parent_file Parent.
	 * @return string
	 */
	public function parent_file( $parent_file ) {
		global $plugin_page;
		return self::PAGE === $plugin_page ? 'import-export-by-rockstarlab' : $parent_file;
	}

	/**
	 * Highlight Jobs Log.
	 *
	 * @param string|null $submenu Submenu.
	 * @return string|null
	 */
	public function submenu_file( $submenu ) {
		global $plugin_page;
		return self::PAGE === $plugin_page ? 'rsl-ie-jobs-log' : $submenu;
	}

	/** Render the screen. */
	public function display() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot change servers on this site.', 'import-export-by-rockstarlab' ), '', [ 'response' => 403 ] );
		}
		$edit = isset( $_GET['edit'] ) ? sanitize_key( wp_unslash( $_GET['edit'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only screen state.
		rsl_ie()->View->load(
			'settings/servers',
			[
				'rsl_ie_servers'   => Remote_File_Service::servers(),
				'rsl_ie_edit'      => $edit,
				'rsl_ie_available' => Remote_File_Service::available(),
			]
		);
	}

	/** Add or change a server. */
	public function save() {
		$this->guard( 'rsl_ie_save_remote_server' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$post  = wp_unslash( $_POST );
		$text  = static function ( $key ) use ( $post ) {
			return isset( $post[ $key ] ) && is_scalar( $post[ $key ] ) ? (string) $post[ $key ] : '';
		};
		$input = [
			'id'       => $text( 'id' ),
			'label'    => $text( 'label' ),
			'protocol' => $text( 'protocol' ),
			'host'     => $text( 'host' ),
			'port'     => (int) $text( 'port' ),
			'username' => $text( 'username' ),
			'password' => $text( 'password' ), // Not sanitized: passwords may contain any character.
			'passive'  => '1' === $text( 'passive' ),
		];
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$result = Remote_File_Service::save( $input );
		if ( is_wp_error( $result ) ) {
			$this->notice( 'error', $result->get_error_message() );
			$this->back( '' !== $input['id'] ? [ 'edit' => sanitize_key( $input['id'] ) ] : [ 'add' => 1 ] );
		}
		// Check the connection right away, so a typo shows up now and not at the first scheduled run.
		$check = ( new Remote_File_Service() )->browse( $result['id'], '/' );
		if ( is_wp_error( $check ) ) {
			/* translators: %s: error message. */
			$this->notice( 'warning', sprintf( __( 'Server saved, but the connection failed: %s', 'import-export-by-rockstarlab' ), $check->get_error_message() ) );
		} else {
			/* translators: %s: server name. */
			$this->notice( 'success', sprintf( __( 'Server "%s" saved. Connection works.', 'import-export-by-rockstarlab' ), $result['label'] ) );
		}
		$this->back();
	}

	/** Remove a server. */
	public function delete() {
		$this->guard( 'rsl_ie_delete_remote_server' );
		$id     = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$result = Remote_File_Service::delete( $id );
		$this->notice( is_wp_error( $result ) ? 'error' : 'success', is_wp_error( $result ) ? $result->get_error_message() : __( 'Server removed. Imports and exports that used it need another server.', 'import-export-by-rockstarlab' ) );
		$this->back();
	}

	/** Test a saved server. */
	public function test() {
		$this->guard( 'rsl_ie_test_remote_server' );
		$id     = isset( $_POST['id'] ) ? sanitize_key( wp_unslash( $_POST['id'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$result = ( new Remote_File_Service() )->browse( $id, '/' );
		if ( is_wp_error( $result ) ) {
			$this->notice( 'error', $result->get_error_message() );
		} else {
			/* translators: %d: number of files and folders. */
			$this->notice( 'success', sprintf( _n( 'Connection works. The start folder has %d item.', 'Connection works. The start folder has %d items.', count( $result['items'] ), 'import-export-by-rockstarlab' ), count( $result['items'] ) ) );
		}
		$this->back();
	}

	/**
	 * Remember a notice for the next page view.
	 *
	 * @param string $type Notice type.
	 * @param string $text Message.
	 */
	private function notice( $type, $text ) {
		set_transient( 'rsl_ie_servers_notice_' . get_current_user_id(), [ $type, $text ], 120 );
	}

	/**
	 * Redirect back to the screen.
	 *
	 * @param array $args Extra query arguments.
	 */
	private function back( array $args = [] ) {
		wp_safe_redirect( add_query_arg( [ 'page' => self::PAGE ] + $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * POST, capability and nonce.
	 *
	 * @param string $action Nonce action.
	 */
	private function guard( $action ) {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot change servers on this site.', 'import-export-by-rockstarlab' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( $action );
	}
}

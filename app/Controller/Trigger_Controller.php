<?php
/**
 * Triggers screen (Jobs Log → Triggers).
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Service\Trigger_Service;

defined( 'ABSPATH' ) || exit;

/** Admin adapter for Trigger_Service (administrators only). */
class Trigger_Controller extends Base_Controller {
	const PAGE = 'rsl-ie-triggers';

	/** Register the hidden page and form handlers. */
	public function init() {
		parent::init();
		Trigger_Service::init();
		add_action( 'admin_menu', [ $this, 'register_page' ], 20 );
		add_filter( 'parent_file', [ $this, 'parent_file' ] );
		add_filter( 'submenu_file', [ $this, 'submenu_file' ] );
		add_action( 'admin_post_rsl_ie_create_trigger', [ $this, 'create' ] );
		add_action( 'admin_post_rsl_ie_trigger_action', [ $this, 'action' ] );
	}

	/**
	 * No AJAX actions.
	 *
	 * @return array
	 */
	protected function get_ajax_actions() {
		return [];
	}

	/** Hidden page reached from the Jobs Log tabs. */
	public function register_page() {
		add_submenu_page( null, __( 'Triggers', 'import-export-by-rockstarlab' ), __( 'Triggers', 'import-export-by-rockstarlab' ), 'manage_options', self::PAGE, [ $this, 'display' ] );
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
			wp_die( esc_html__( 'You cannot manage triggers on this site.', 'import-export-by-rockstarlab' ), '', [ 'response' => 403 ] );
		}
		$service = new Trigger_Service();
		$list    = $service->execute( 'trigger-list' );
		rsl_ie()->View->load(
			'settings/triggers',
			[
				'rsl_ie_triggers'    => is_wp_error( $list ) ? [] : $list['triggers'],
				'rsl_ie_sources'     => $service->sources(),
				'rsl_ie_trigger_log' => array_slice( $service->log_entries(), 0, 20 ),
				'rsl_ie_post_types'  => array_diff_key( get_post_types( [ 'public' => true ], 'objects' ), [ 'attachment' => 1 ] ),
				'rsl_ie_woo'         => class_exists( 'WooCommerce' ),
			]
		);
	}

	/** Create a trigger from the form. */
	public function create() {
		$this->guard( 'rsl_ie_create_trigger' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$post  = wp_unslash( $_POST );
		$input = [
			'name'          => isset( $post['name'] ) && is_scalar( $post['name'] ) ? sanitize_text_field( (string) $post['name'] ) : '',
			'source_job_id' => isset( $post['source_job_id'] ) ? max( 1, (int) $post['source_job_id'] ) : 1,
			'event'         => isset( $post['event'] ) && is_scalar( $post['event'] ) ? sanitize_key( (string) $post['event'] ) : '',
			'post_types'    => isset( $post['post_types'] ) && is_array( $post['post_types'] ) ? array_values( array_map( 'sanitize_key', $post['post_types'] ) ) : [],
			'delay'         => isset( $post['delay'] ) ? max( 0, min( 3600, (int) $post['delay'] ) ) : 60,
		];
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		if ( 'post_published' !== $input['event'] ) {
			unset( $input['post_types'] );
		}
		$result = ( new Trigger_Service() )->execute( 'trigger-create', $input );
		if ( is_wp_error( $result ) ) {
			$this->notice( 'error', $result->get_error_message() );
		} else {
			$this->notice( 'success', __( 'Trigger created.', 'import-export-by-rockstarlab' ) );
			$this->remember_token( $result );
		}
		$this->back();
	}

	/** Pause, resume, run, rotate the token of, or delete a trigger. */
	public function action() {
		$this->guard( 'rsl_ie_trigger_action' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$id   = isset( $_POST['trigger_id'] ) ? sanitize_key( wp_unslash( $_POST['trigger_id'] ) ) : '';
		$what = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$service = new Trigger_Service();
		$input   = [ 'trigger_id' => $id ];
		switch ( $what ) {
			case 'pause':
			case 'resume':
				$result = $service->execute( 'trigger-update', $input + [ 'status' => 'pause' === $what ? 'paused' : 'active' ] );
				$done   = 'pause' === $what ? __( 'Trigger paused.', 'import-export-by-rockstarlab' ) : __( 'Trigger resumed.', 'import-export-by-rockstarlab' );
				break;
			case 'run':
				$result = $service->execute( 'trigger-run', $input );
				/* translators: %d: job ID. */
				$done = is_wp_error( $result ) ? '' : sprintf( __( 'Job #%d started.', 'import-export-by-rockstarlab' ), (int) $result['job_id'] );
				break;
			case 'rotate':
				$result = $service->execute( 'trigger-rotate-token', $input );
				$done   = __( 'New token created. The old token no longer works.', 'import-export-by-rockstarlab' );
				break;
			case 'delete':
				$result = $service->execute( 'trigger-delete', $input );
				$done   = __( 'Trigger deleted.', 'import-export-by-rockstarlab' );
				break;
			default:
				$result = new \WP_Error( 'rsl_ie_trigger_action', __( 'Unknown action.', 'import-export-by-rockstarlab' ) );
				$done   = '';
		}
		if ( is_wp_error( $result ) ) {
			$this->notice( 'error', $result->get_error_message() );
		} else {
			$this->notice( 'success', $done );
			$this->remember_token( $result );
		}
		$this->back();
	}

	/**
	 * Keep a new token for one page view, for this administrator only.
	 *
	 * @param array $result Service result.
	 */
	private function remember_token( $result ) {
		if ( is_array( $result ) && ! empty( $result['token'] ) ) {
			set_transient(
				'rsl_ie_trigger_token_' . get_current_user_id(),
				[
					'trigger_id' => $result['trigger_id'],
					'url'        => $result['webhook_url'],
					'token'      => $result['token'],
				],
				300
			);
		}
	}

	/**
	 * Store a notice for the next page view.
	 *
	 * @param string $type    Type.
	 * @param string $message Message.
	 */
	private function notice( $type, $message ) {
		set_transient( 'rsl_ie_trigger_notice_' . get_current_user_id(), [ $type, $message ], 120 );
	}

	/** Back to the screen. */
	private function back() {
		wp_safe_redirect( add_query_arg( [ 'page' => self::PAGE ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * POST, capability and nonce.
	 *
	 * @param string $action Nonce action.
	 */
	private function guard( $action ) {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot manage triggers on this site.', 'import-export-by-rockstarlab' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( $action );
	}
}

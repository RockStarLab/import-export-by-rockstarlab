<?php
/**
 * Notifications screen (Jobs Log → Notifications) and the error CSV download.
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Service\Job_Notification_Service;

defined( 'ABSPATH' ) || exit;

/** Admin adapter for Job_Notification_Service (administrators only). */
class Notification_Controller extends Base_Controller {
	const PAGE = 'rsl-ie-notifications';

	/** Register AJAX actions, the hidden page and form handlers. */
	public function init() {
		parent::init();
		Job_Notification_Service::init();
		add_action( 'admin_menu', [ $this, 'register_page' ], 20 );
		add_filter( 'parent_file', [ $this, 'parent_file' ] );
		add_filter( 'submenu_file', [ $this, 'submenu_file' ] );
		add_action( 'admin_post_rsl_ie_save_notifications', [ $this, 'save' ] );
		add_action( 'admin_post_rsl_ie_test_notifications', [ $this, 'test' ] );
		add_action( 'admin_post_rsl_ie_notifications_n8n', [ $this, 'n8n' ] );
	}

	/**
	 * AJAX actions.
	 *
	 * @return array
	 */
	protected function get_ajax_actions() {
		return [
			'job_errors_csv' => [ 'callback' => 'errors_csv' ],
		];
	}

	/** Failed rows of an import job as CSV text for the browser to save. */
	public function errors_csv() {
		$permission = $this->verify_request();
		if ( is_wp_error( $permission ) ) {
			$this->send_error( $permission, null, 403 );
			return;
		}
		$result = ( new Job_Notification_Service() )->job_errors( (int) $this->get_request_param( 'job_id', 0 ), 500, 0, true );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result, null, 404 );
			return;
		}
		$this->send_success(
			[
				'file_name'    => $result['file_name'],
				'csv'          => $result['csv'],
				'errors_total' => $result['errors_total'],
			]
		);
	}

	/** Hidden page reached from the Jobs Log tabs. */
	public function register_page() {
		add_submenu_page( null, __( 'Notifications', 'import-export-by-rockstarlab' ), __( 'Notifications', 'import-export-by-rockstarlab' ), 'manage_options', self::PAGE, [ $this, 'display' ] );
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

	/** Render the settings screen. */
	public function display() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot change notifications on this site.', 'import-export-by-rockstarlab' ), '', [ 'response' => 403 ] );
		}
		$service  = new Job_Notification_Service();
		$settings = $service->public_settings();
		$draft    = get_transient( 'rsl_ie_notify_draft_' . get_current_user_id() );
		if ( is_array( $draft ) ) {
			delete_transient( 'rsl_ie_notify_draft_' . get_current_user_id() );
			$settings['enabled']             = ! empty( $draft['enabled'] );
			$settings['when']                = in_array( $draft['when'] ?? '', [ 'errors', 'always' ], true ) ? $draft['when'] : $settings['when'];
			$settings['job_types']           = (array) ( $draft['job_types'] ?? [] );
			$settings['email']['enabled']    = ! empty( $draft['email']['enabled'] );
			$settings['email']['recipients'] = array_values( array_filter( array_map( 'trim', explode( ',', (string) ( $draft['email']['recipients'] ?? '' ) ) ) ) );
			$settings['slack']['enabled']    = ! empty( $draft['slack']['enabled'] );
			$settings['webhook']['enabled']  = ! empty( $draft['webhook']['enabled'] );
			$settings['subject']             = (string) ( $draft['subject'] ?? $settings['subject'] );
			$settings['message']             = (string) ( $draft['message'] ?? $settings['message'] );
		}
		rsl_ie()->View->load(
			'settings/notifications',
			[
				'rsl_ie_notifications' => $settings,
				'rsl_ie_notify_log'    => $service->log_entries(),
			]
		);
	}

	/** Save the form. */
	public function save() {
		$this->guard( 'rsl_ie_save_notifications' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Verified in guard().
		$post   = wp_unslash( $_POST );
		$text   = static function ( $key ) use ( $post ) {
			return isset( $post[ $key ] ) && is_scalar( $post[ $key ] ) ? (string) $post[ $key ] : '';
		};
		$input  = [
			'enabled'           => '1' === $text( 'enabled' ),
			'when'              => $text( 'when' ),
			'job_types'         => isset( $post['job_types'] ) && is_array( $post['job_types'] ) ? array_map( 'sanitize_key', $post['job_types'] ) : [],
			'email'             => [
				'enabled'    => '1' === $text( 'email_enabled' ),
				'recipients' => sanitize_textarea_field( $text( 'email_recipients' ) ),
			],
			'slack'             => [
				'enabled'   => '1' === $text( 'slack_enabled' ),
				'url'       => esc_url_raw( trim( $text( 'slack_url' ) ) ),
				'clear_url' => '1' === $text( 'slack_clear' ),
			],
			'webhook'           => [
				'enabled'   => '1' === $text( 'webhook_enabled' ),
				'url'       => esc_url_raw( trim( $text( 'webhook_url' ) ) ),
				'clear_url' => '1' === $text( 'webhook_clear' ),
			],
			'regenerate_secret' => '1' === $text( 'regenerate_secret' ),
			'subject'           => sanitize_text_field( $text( 'subject' ) ),
			'message'           => sanitize_textarea_field( $text( 'message' ) ),
		];
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$result = ( new Job_Notification_Service() )->update( $input );
		$args   = [ 'page' => self::PAGE ];
		if ( is_wp_error( $result ) ) {
			set_transient( 'rsl_ie_notify_notice_' . get_current_user_id(), [ 'error', $result->get_error_message() . ' ' . __( 'Nothing was saved. Fix this and save again.', 'import-export-by-rockstarlab' ) ], 120 );
			// Show the form again as it was sent, so a typo does not wipe the other choices.
			// Slack and webhook URLs are never echoed back into the page.
			$draft = $input;
			unset( $draft['slack']['url'], $draft['webhook']['url'], $draft['slack']['clear_url'], $draft['webhook']['clear_url'], $draft['regenerate_secret'] );
			set_transient( 'rsl_ie_notify_draft_' . get_current_user_id(), $draft, 120 );
		} else {
			$notice = __( 'Notification settings saved.', 'import-export-by-rockstarlab' );
			if ( ! empty( $result['webhook_secret'] ) ) {
				// Shown once, to this administrator only; never stored in a URL or log.
				set_transient( 'rsl_ie_notify_secret_' . get_current_user_id(), $result['webhook_secret'], 300 );
			}
			set_transient( 'rsl_ie_notify_notice_' . get_current_user_id(), [ 'success', $notice ], 120 );
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Send a test message. */
	public function test() {
		$this->guard( 'rsl_ie_test_notifications' );
		$result = ( new Job_Notification_Service() )->send_test();
		if ( is_wp_error( $result ) ) {
			$notice = [ 'error', $result->get_error_message() ];
		} else {
			$parts = [];
			$ok    = true;
			foreach ( $result['results'] as $channel => $r ) {
				$parts[] = ucfirst( $channel ) . ': ' . ( $r['ok'] ? __( 'sent', 'import-export-by-rockstarlab' ) : $r['detail'] );
				$ok      = $ok && $r['ok'];
			}
			/* translators: %s: result per channel. */
			$notice = [ $ok ? 'success' : 'warning', sprintf( __( 'Test notification: %s', 'import-export-by-rockstarlab' ), implode( ', ', $parts ) ) ];
		}
		set_transient( 'rsl_ie_notify_notice_' . get_current_user_id(), $notice, 120 );
		wp_safe_redirect( add_query_arg( [ 'page' => self::PAGE ], admin_url( 'admin.php' ) ) );
		exit;
	}

	/** Download the n8n workflow that receives job notifications (no secret inside). */
	public function n8n() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot change notifications on this site.', 'import-export-by-rockstarlab' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( 'rsl_ie_notifications_n8n' );
		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="n8n-job-finished.json"' );
		echo wp_json_encode( Job_Notification_Service::n8n_workflow(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON download.
		exit;
	}

	/**
	 * POST, capability and nonce.
	 *
	 * @param string $action Nonce action.
	 */
	private function guard( $action ) {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) || ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You cannot change notifications on this site.', 'import-export-by-rockstarlab' ), '', [ 'response' => 403 ] );
		}
		check_admin_referer( $action );
	}
}

<?php
/**
 * AI URL Importer Controller
 *
 * Handles AI-powered content extraction from URLs
 * Uses the configured AI provider resolver
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

defined( 'ABSPATH' ) || exit;

/** Existing AJAX screen delegates to the authorized application service. */
class AI_URL_Importer_Controller extends Base_Controller {

	/**
	 * Get AJAX actions to register
	 *
	 * @return array Array of action => config
	 */
	protected function get_ajax_actions() {
		return array(
			'ai_url_test_connection' => array(
				'callback' => 'test_connection',
			),
			'ai_url_preview'         => array(
				'callback' => 'preview_url',
			),
			'ai_url_get_acf_fields'  => array(
				'callback' => 'get_acf_fields',
			),
			'ai_url_start_import'    => array(
				'callback' => 'start_import',
			),
			'ai_url_process_batch'   => array(
				'callback' => 'process_batch',
			),
			'ai_url_get_post_types'  => array(
				'callback' => 'get_post_types',
			),
			'ai_url_get_progress'    => array(
				'callback' => 'get_progress',
			),
			'ai_url_cancel_import'   => array(
				'callback' => 'cancel_import',
			),
		);
	}

	/**
	 * Verify the AJAX nonce and dispatch the shared service.
	 *
	 * @param string $name Service operation.
	 * @param array  $input Normalized input.
	 * @return void
	 */
	private function dispatch( $name, array $input = [] ) {
		$access = $this->verify_request();
		if ( is_wp_error( $access ) ) {
			$this->send_error( $access, null, 403 ); }
		$service = new \RockStarLab\ImportExport\Service\AI_URL_Service();
		$edition = apply_filters( 'rsl_ie_ai_url_admin_edition', 'free' );
		$result  = $service->execute( $name, $input, $edition );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result ); }
		$this->send_success( $result );
	}
	/** Test the configured provider. */
	public function test_connection() {
		$this->dispatch( 'ai-test-connection' ); }
	/** Extract a preview without creating a post. */
	public function preview_url() {
		$this->dispatch(
			'ai-url-preview',
			[
				'url'             => $this->get_request_param( 'url', '', 'post' ),
				'extraction_mode' => $this->get_request_param( 'extraction_mode', 'auto', 'post' ),
			]
		); }
	/** Read compatible ACF destinations. */
	public function get_acf_fields() {
		$this->dispatch( 'ai-url-fields', [ 'post_type' => $this->get_request_param( 'post_type', 'post', 'post' ) ] ); }
	/** Read authorized destination types. */
	public function get_post_types() {
		$this->dispatch( 'ai-url-settings' ); }
	/** Queue immutable import options for background execution. */
	public function start_import() {
		$input = [
			'urls'       => $this->get_request_array( 'urls' ),
			'request_id' => $this->get_request_param( 'request_id', wp_generate_uuid4(), 'post' ),
		];
		foreach ( \RockStarLab\ImportExport\Abilities\AI_URL_Catalog::options()['properties'] as $name => $schema ) {
			if ( 'urls' === $name ) {
				continue; }
			$value          = $this->get_request_param( $name, $schema['default'], 'post' );
			$input[ $name ] = 'integer' === $schema['type'] ? (int) $value : ( 'boolean' === $schema['type'] ? filter_var( $value, FILTER_VALIDATE_BOOLEAN ) : $value );
		}
		$this->dispatch( 'ai-url-import-start', $input );
	}
	/** Execute one locked batch; cron continues independently of the browser. */
	public function process_batch() {
		$access = $this->verify_request();
		if ( is_wp_error( $access ) ) {
			$this->send_error( $access, null, 403 ); }
		$id      = absint( $this->get_request_param( 'job_id', 0, 'post' ) );
		$service = new \RockStarLab\ImportExport\Service\AI_URL_Service();
		$result  = $service->execute( 'ai-url-import-status', [ 'job_id' => $id ] );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result ); }
		$service->process_background_batch( $id );
		$result = $service->execute( 'ai-url-import-status', [ 'job_id' => $id ] );
		if ( is_wp_error( $result ) ) {
			$this->send_error( $result ); }
		$result['completed'] = 'completed' === $result['status'];
		$this->send_success( $result );
	}
	/** Read progress. */
	public function get_progress() {
		$this->dispatch( 'ai-url-import-status', [ 'job_id' => absint( $this->get_request_param( 'job_id', 0, 'post' ) ) ] ); }
	/** Cancel without deleting completed items. */
	public function cancel_import() {
		$this->dispatch( 'ai-url-import-cancel', [ 'job_id' => absint( $this->get_request_param( 'job_id', 0, 'post' ) ) ] ); }
}

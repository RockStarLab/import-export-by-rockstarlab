<?php
/**
 * Mapping template AJAX endpoints for the import wizard and Jobs Log.
 *
 * @package RockStarLab\ImportExport\Controller
 */

namespace RockStarLab\ImportExport\Controller;

use RockStarLab\ImportExport\Service\Mapping_Template_Service;

defined( 'ABSPATH' ) || exit;

/** Thin admin adapter over Mapping_Template_Service (administrators only, per-action nonces). */
class Mapping_Template_Controller extends Base_Controller {

	/**
	 * AJAX actions.
	 *
	 * @return array
	 */
	protected function get_ajax_actions() {
		return [
			'mapping_template_list'     => [ 'callback' => 'list_templates' ],
			'mapping_template_get'      => [ 'callback' => 'get_template' ],
			'mapping_template_save'     => [ 'callback' => 'save_template' ],
			'mapping_template_rename'   => [ 'callback' => 'rename_template' ],
			'mapping_template_delete'   => [ 'callback' => 'delete_template' ],
			'mapping_template_from_job' => [ 'callback' => 'from_job' ],
		];
	}

	/** List templates, optionally for one import type. */
	public function list_templates() {
		if ( ! $this->allowed() ) {
			return;
		}
		$this->send_success( [ 'templates' => ( new Mapping_Template_Service() )->listing( (string) $this->get_request_param( 'import_type', '' ) ) ] );
	}

	/** Read one template. */
	public function get_template() {
		if ( ! $this->allowed() ) {
			return;
		}
		$this->respond( ( new Mapping_Template_Service() )->get( (string) $this->get_request_param( 'template_id', '' ) ) );
	}

	/** Create or replace a template from the wizard. */
	public function save_template() {
		if ( ! $this->allowed() ) {
			return;
		}
		$input = [
			'name'        => (string) $this->get_request_param( 'name', '' ),
			'import_type' => (string) $this->get_request_param( 'import_type', '' ),
			'mapping'     => $this->get_request_array( 'mapping' ),
			'options'     => $this->get_request_array( 'options' ),
			'parser'      => $this->get_request_array( 'parser' ),
			'overwrite'   => '1' === (string) $this->get_request_param( 'overwrite', '0' ),
		];
		$id    = (string) $this->get_request_param( 'template_id', '' );
		if ( '' !== $id ) {
			$input['template_id'] = $id;
		}
		$this->respond( ( new Mapping_Template_Service() )->save( $input ) );
	}

	/** Rename a template. */
	public function rename_template() {
		if ( ! $this->allowed() ) {
			return;
		}
		$this->respond( ( new Mapping_Template_Service() )->rename( (string) $this->get_request_param( 'template_id', '' ), (string) $this->get_request_param( 'name', '' ) ) );
	}

	/** Delete a template. */
	public function delete_template() {
		if ( ! $this->allowed() ) {
			return;
		}
		$this->respond( ( new Mapping_Template_Service() )->delete( (string) $this->get_request_param( 'template_id', '' ) ) );
	}

	/** Save the mapping of a finished import job as a template. */
	public function from_job() {
		if ( ! $this->allowed() ) {
			return;
		}
		$this->respond(
			( new Mapping_Template_Service() )->from_job(
				(int) $this->get_request_param( 'job_id', 0 ),
				(string) $this->get_request_param( 'name', '' ),
				'1' === (string) $this->get_request_param( 'overwrite', '0' )
			)
		);
	}

	/**
	 * Check nonce and capability.
	 *
	 * @return bool
	 */
	private function allowed() {
		$permission = $this->verify_request();
		if ( is_wp_error( $permission ) ) {
			$this->send_error( $permission, null, 403 );
			return false;
		}
		return true;
	}

	/**
	 * Send a service result.
	 *
	 * @param array|\WP_Error $result Result.
	 */
	private function respond( $result ) {
		if ( is_wp_error( $result ) ) {
			$data = $result->get_error_data();
			$this->send_error( $result, null, is_array( $data ) ? ( $data['status'] ?? 400 ) : 400 );
			return;
		}
		$this->send_success( $result );
	}
}

<?php
/**
 * JSON-only media ability adapter.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Media_Catalog;

defined( 'ABSPATH' ) || exit;

/** Validate direct PHP calls with the same schema as REST. */
class Media_Workflow_Service {
	/**
	 * Execute a registered media operation.
	 *
	 * @param string            $name Ability suffix.
	 * @param array|object|null $input Request input.
	 * @return array|\WP_Error
	 */
	public function execute( $name, $input = [] ) {
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		$definition = Media_Catalog::definitions()[ $name ] ?? null;
		if ( ! $definition ) {
			return new \WP_Error( 'rsl_ie_unknown_media_ability', __( 'Unknown media operation.', 'import-export-by-rockstarlab' ), [ 'status' => 404 ] );
		}
		$input = (array) $input;
		$valid = rest_validate_value_from_schema( $input, $definition['input'], 'input' );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$input = rest_sanitize_value_from_schema( $input, $definition['input'] );
		if ( is_wp_error( $input ) ) {
			return $input;
		}
		if ( 0 === strpos( $name, 'sync-media-' ) ) {
			return ( new Media_Library_Sync_Service() )->execute( substr( $name, 11 ), $input );
		}
		$folder = new Media_Folder_Service();
		if ( 'media-browse-folders' === $name ) {
			return $folder->browse( $input ); }
		if ( 'media-scan-folder' === $name ) {
			return $folder->scan( $input ); }
		if ( 'media-sync-start' === $name ) {
			return $folder->start( $input ); }
		if ( 'media-sync-status' === $name ) {
			return $folder->status( $input['job_id'] ); }
		if ( in_array( $name, [ 'media-sync-pause', 'media-sync-resume', 'media-sync-cancel' ], true ) ) {
			return $folder->transition( $input['job_id'], substr( $name, 11 ) ); }
		$service = new Media_Hash_Service();
		switch ( $name ) {
			case 'media-hash-stats':
				return $service->statistics();
			case 'media-hash-build':
				return $service->build( $input );
			default:
				return $service->check_duplicate( $input );
		}
	}
}

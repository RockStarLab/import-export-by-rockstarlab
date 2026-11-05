<?php
/**
 * Standalone sync ability adapter.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Standalone_Sync_Policy;
defined( 'ABSPATH' ) || exit;
/** The provider supplies the definition; input cannot choose the provider. */
class Standalone_Sync_Workflow {
	/**
	 * Apply the same server-selected domain normalization during queued preflight.
	 *
	 * @param array $definition Registered definition.
	 * @param array $input Validated input.
	 * @param bool  $defer_destinations Internal migration planning only.
	 * @return true|\WP_Error
	 */
	public static function check_input( array $definition, array $input, $defer_destinations = false ) {
		if ( 'reviews' === $definition['domain'] ) {
			$input['comment_type'] = 'review';
		}
		return Standalone_Sync_Policy::input( $definition['domain'], $definition['action'], $input, $defer_destinations );
	}

	/**
	 * Validate and execute a server-selected standalone contract.
	 *
	 * @param array $definition Registered definition.
	 * @param array $input Caller input.
	 * @return array|\WP_Error
	 */
	public function execute( array $definition, array $input ) {
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access; }
		$valid = rest_validate_value_from_schema( $input, $definition['input'], 'input' );
		if ( is_wp_error( $valid ) ) {
			return $valid; }
		$input = rest_sanitize_value_from_schema( $input, $definition['input'] );
		if ( is_wp_error( $input ) ) {
			return $input; }
		$result = ( new Content_Sync_Service() )->execute_standalone( $definition['action'], $input, $definition['domain'] );
		if ( is_wp_error( $result ) ) {
			return new \WP_Error( $result->get_error_code(), __( 'Sync failed. Check selection, destination, connection and permissions before repeating.', 'import-export-by-rockstarlab' ), [ 'status' => $result->get_error_data()['status'] ?? 400 ] ); }
		if ( ! empty( $result['failed'] ) ) {
			return new \WP_Error(
				'rsl_ie_standalone_sync_partial',
				__( 'Some selected objects failed. Inspect the destination before repeating.', 'import-export-by-rockstarlab' ),
				[
					'status'  => 409,
					'created' => (int) ( $result['created'] ?? 0 ),
					'updated' => (int) ( $result['updated'] ?? 0 ),
					'failed'  => (int) $result['failed'],
				]
			); }
		if ( ! $definition['readonly'] ) {
			$selected = $input[ 'terms' === $definition['domain'] ? 'term_ids' : 'comment_ids' ];
			if ( ! isset( $result['created'], $result['updated'] ) || false === ( $result['success'] ?? true ) || count( $selected ) !== (int) $result['created'] + (int) $result['updated'] ) {
				return new \WP_Error( 'rsl_ie_standalone_sync_incomplete', __( 'The peer did not confirm all selected objects. Inspect the destination before repeating.', 'import-export-by-rockstarlab' ), [ 'status' => 409 ] ); }
		}
		if ( isset( $result['mapping'] ) ) {
			$result['mapping'] = (object) $result['mapping']; }
		foreach ( $result['comments'] ?? [] as $comment ) {
			$requested = 'reviews' === $definition['domain'] ? 'review' : ( $input['comment_type'] ?? '' );
			if ( ! is_array( $comment ) || ( $comment['post']['post_type'] ?? '' ) !== $input['post_type'] || ! Standalone_Sync_Policy::comment_type( $comment['comment_type'] ?? '', $requested ) || (int) ( $comment['comment_post_ID'] ?? 0 ) !== (int) $input['post_id'] ) {
				return Standalone_Sync_Policy::denied(); }
		}
		foreach ( $result['terms'] ?? [] as $term ) {
			if ( ! is_array( $term ) || ( $term['taxonomy'] ?? '' ) !== $input['taxonomy'] ) {
				return Standalone_Sync_Policy::denied(); }
		}
		// Peer messages and arbitrary top-level data are untrusted.
		$result['message'] = __( 'Sync operation completed.', 'import-export-by-rockstarlab' );
		return array_intersect_key( $result, $definition['output']['properties'] );
	}
}

<?php
/**
 * Hierarchy preflight for standalone sync.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;
/** Parents are written before children; cycles fail before writes. */
class Sync_Graph {
	/**
	 * Order unique source records while rejecting cycles.
	 *
	 * @param array  $records Source records.
	 * @param string $key ID field.
	 * @param string $parent_key Parent ID field.
	 * @return array|\WP_Error
	 */
	public static function order( array $records, $key, $parent_key ) {
		$by_id = [];
		foreach ( $records as $record ) {
			if ( ! is_array( $record ) || ! isset( $record[ $key ] ) || ! is_scalar( $record[ $key ] ) || ! ctype_digit( (string) $record[ $key ] ) || (int) $record[ $key ] < 1 ) {
				return \RockStarLab\ImportExport\Abilities\Standalone_Sync_Policy::denied(); }
			$parent_id = $record[ $parent_key ] ?? 0;
			if ( ! is_scalar( $parent_id ) || ! ctype_digit( (string) $parent_id ) || isset( $by_id[ (int) $record[ $key ] ] ) ) {
				return \RockStarLab\ImportExport\Abilities\Standalone_Sync_Policy::denied(); }
			$by_id[ (int) $record[ $key ] ] = $record;
		}
		$state   = [];
		$ordered = [];
		foreach ( array_keys( $by_id ) as $id ) {
			$path = [];
			while ( isset( $by_id[ $id ] ) && 2 !== ( $state[ $id ] ?? 0 ) ) {
				if ( 1 === ( $state[ $id ] ?? 0 ) ) {
					return \RockStarLab\ImportExport\Abilities\Standalone_Sync_Policy::denied();
				}
				$state[ $id ] = 1;
				$path[]       = $id;
				$id           = (int) ( $by_id[ $id ][ $parent_key ] ?? 0 );
			}
			while ( $path ) {
				$id           = array_pop( $path );
				$state[ $id ] = 2;
				$ordered[]    = $by_id[ $id ];
			}
		}
		return $ordered;
	}
}

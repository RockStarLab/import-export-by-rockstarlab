<?php
/**
 * Exporter Factory
 *
 * Factory class for creating exporter instances
 *
 * @package RockStarLab\ImportExport\Model\Export
 */

namespace RockStarLab\ImportExport\Model\Export;

defined( 'ABSPATH' ) || exit;

class Exporter_Factory {

	/**
	 * Get exporter by type
	 *
	 * @param string $type   Exporter type (posts, users, comments, media)
	 * @param int    $job_id Optional. Job ID for logging
	 * @return Exporter_Interface|\WP_Error Exporter instance or WP_Error
	 */
	public static function get_exporter( $type, $job_id = 0 ) {
		$type = strtolower( trim( $type ) );

		$exporter_map = self::get_exporter_map();

		if ( ! isset( $exporter_map[ $type ] ) ) {
			return new \WP_Error(
				'unknown_exporter',
				sprintf(
					/* translators: %s: exporter type */
					__( 'Unknown exporter type: %s', 'import-export-by-rockstarlab' ),
					$type
				)
			);
		} else {
			$class = $exporter_map[ $type ];
		}

		// Create new instance with job_id
		return new $class( $job_id );
	}

	/**
	 * Get exporter map
	 *
	 * @return array Map of type => class
	 */
	private static function get_exporter_map() {
		$default_map = [
			// Free plugin content types.
			'post'    => Post_Exporter::class,
			'page'    => Post_Exporter::class,
			'urls'    => Urls_Exporter::class,
			'comment' => Comment_Exporter::class,
		];

		/**
		 * Filter registered exporters
		 *
		 * Allows registering custom exporters.
		 *
		 * @param array $exporters Map of type => class
		 */
		return apply_filters( 'rsl_ie_exporter_map', $default_map );
	}

	/**
	 * Get list of available exporters
	 *
	 * @return array Array of exporter types
	 */
	public static function get_available_exporters() {
		return array_keys( self::get_exporter_map() );
	}

	/**
	 * Check if exporter type is supported
	 *
	 * @param string $type Exporter type
	 * @return bool True if supported
	 */
	public static function is_supported( $type ) {
		$type = strtolower( trim( $type ) );
		return array_key_exists( $type, self::get_exporter_map() );
	}

	/**
	 * Get exporter info
	 *
	 * @param string $type Exporter type
	 * @return array|\WP_Error Exporter info or WP_Error
	 */
	public static function get_exporter_info( $type ) {
		$exporter = self::get_exporter( $type );

		if ( is_wp_error( $exporter ) ) {
			return $exporter;
		}

		return [
			'name'              => $exporter->get_name(),
			'description'       => $exporter->get_description(),
			'supported_filters' => $exporter->get_supported_filters(),
			'available_fields'  => $exporter->get_available_fields(),
			'default_fields'    => $exporter->get_default_fields(),
		];
	}

	/**
	 * Get info for all exporters
	 *
	 * @return array Array of exporter info
	 */
	public static function get_all_exporters_info() {
		$info      = [];
		$exporters = self::get_available_exporters();

		foreach ( $exporters as $type ) {
			$exporter_info = self::get_exporter_info( $type );
			if ( ! is_wp_error( $exporter_info ) ) {
				$info[ $type ] = $exporter_info;
			}
		}

		return $info;
	}

	/**
	 * Validate export options for specific exporter type
	 *
	 * @param string $type    Exporter type
	 * @param array  $options Options to validate
	 * @return true|\WP_Error True if valid or WP_Error
	 */
	public static function validate_options( $type, $options ) {
		$exporter = self::get_exporter( $type );

		if ( is_wp_error( $exporter ) ) {
			return $exporter;
		}

		return $exporter->validate_options( $options );
	}

	/**
	 * Get count of items available for export
	 *
	 * @param string $type    Exporter type
	 * @param array  $options Optional. Export filters
	 * @param int    $job_id  Optional. Job ID for logging
	 * @return int|\WP_Error Count or WP_Error
	 */
	public static function get_count( $type, $options = [], $job_id = 0 ) {
		$exporter = self::get_exporter( $type, $job_id );

		if ( is_wp_error( $exporter ) ) {
			return $exporter;
		}

		return $exporter->get_count( $options );
	}

	/**
	 * Quick export helper
	 *
	 * Convenience method for simple exports.
	 *
	 * @param string $type    Exporter type
	 * @param array  $options Optional. Export options
	 * @param int    $job_id  Optional. Job ID for logging
	 * @return array|\WP_Error Export results or WP_Error
	 */
	public static function export( $type, $options = [], $job_id = 0 ) {
		$exporter = self::get_exporter( $type, $job_id );

		if ( is_wp_error( $exporter ) ) {
			return $exporter;
		}

		return $exporter->export( $options );
	}
}

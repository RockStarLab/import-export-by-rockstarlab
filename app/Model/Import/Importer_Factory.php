<?php
/**
 * Importer Factory
 *
 * Factory class for creating importer instances
 *
 * @package RockStarLab\ImportExport\Model\Import
 */

namespace RockStarLab\ImportExport\Model\Import;

defined( 'ABSPATH' ) || exit;

class Importer_Factory {

	/**
	 * Registered importers
	 *
	 * @var array
	 */
	private static $importers = [];

	/**
	 * Get importer by type
	 *
	 * @param string $type    Importer type (posts, users, comments, media)
	 * @param int    $job_id  Optional. Job ID for logging
	 * @return Importer_Interface|WP_Error Importer instance or WP_Error
	 */
	public static function get_importer( $type, $job_id = 0 ) {
		$type = strtolower( trim( $type ) );

		$importer_map = self::get_importer_map();

		if ( ! isset( $importer_map[ $type ] ) ) {
			return new \WP_Error(
				'unknown_importer',
				sprintf(
					/* translators: %s: importer type */
					__( 'Unknown importer type: %s', 'import-export-by-rockstarlab' ),
					$type
				)
			);
		}

		$class = $importer_map[ $type ];

		// Create new instance with job_id
		return new $class( $job_id );
	}

	/**
	 * Get importer map
	 *
	 * @return array Map of type => class
	 */
	private static function get_importer_map() {
		$default_map = [
			// Free plugin content types.
			'post'  => Post_Importer::class,
			'posts' => Post_Importer::class,
			'page'  => Post_Importer::class,
			'pages' => Post_Importer::class,
		];

		/**
		 * Filter registered importers
		 *
		 * Allows registering custom importers.
		 *
		 * @param array $importers Map of type => class
		 */
		return apply_filters( 'rsl_ie_importer_map', $default_map );
	}

	/**
	 * Get list of available importers
	 *
	 * @return array Array of importer types
	 */
	public static function get_available_importers() {
		return array_keys( self::get_importer_map() );
	}

	/**
	 * Check if importer type is supported
	 *
	 * @param string $type Importer type
	 * @return bool True if supported
	 */
	public static function is_supported( $type ) {
		$type = strtolower( trim( $type ) );
		return array_key_exists( $type, self::get_importer_map() );
	}

	/**
	 * Get importer info
	 *
	 * @param string $type Importer type
	 * @return array|WP_Error Importer info or WP_Error
	 */
	public static function get_importer_info( $type ) {
		$importer = self::get_importer( $type );

		if ( is_wp_error( $importer ) ) {
			return $importer;
		}

		return [
			'name'              => $importer->get_name(),
			'description'       => $importer->get_description(),
			'required_fields'   => $importer->get_required_fields(),
			'optional_fields'   => $importer->get_optional_fields(),
			'supported_options' => $importer->get_supported_options(),
		];
	}

	/**
	 * Get info for all importers
	 *
	 * @return array Array of importer info
	 */
	public static function get_all_importers_info() {
		$info      = [];
		$importers = self::get_available_importers();

		foreach ( $importers as $type ) {
			$importer_info = self::get_importer_info( $type );
			if ( ! is_wp_error( $importer_info ) ) {
				$info[ $type ] = $importer_info;
			}
		}

		return $info;
	}

	/**
	 * Validate data for specific importer type
	 *
	 * @param string $type Importer type
	 * @param array  $data Data to validate
	 * @return true|WP_Error True if valid or WP_Error
	 */
	public static function validate_data( $type, $data ) {
		$importer = self::get_importer( $type );

		if ( is_wp_error( $importer ) ) {
			return $importer;
		}

		return $importer->validate( $data );
	}

	/**
	 * Quick import helper
	 *
	 * Convenience method for simple imports.
	 *
	 * @param string $type    Importer type
	 * @param array  $data    Data to import
	 * @param array  $options Optional. Import options
	 * @param int    $job_id  Optional. Job ID for logging
	 * @return array|WP_Error Import results or WP_Error
	 */
	public static function import( $type, $data, $options = [], $job_id = 0 ) {
		$importer = self::get_importer( $type, $job_id );

		if ( is_wp_error( $importer ) ) {
			return $importer;
		}

		return $importer->import( $data, $options );
	}
}

<?php
/**
 * Format Factory
 *
 * Factory class for creating format handler instances
 *
 * @package RockStarLab\ImportExport\Model\Format
 */

namespace RockStarLab\ImportExport\Model\Format;

defined( 'ABSPATH' ) || exit;

class Format_Factory {

	/**
	 * Registered format handlers
	 *
	 * @var array
	 */
	private static $handlers = [];

	/**
	 * Get format handler by file extension
	 *
	 * @param string $extension File extension (csv, json, xlsx, ods, xml)
	 * @return File_Format_Interface|WP_Error Handler instance or WP_Error
	 */
	public static function get_handler_by_extension( $extension ) {
		$extension = strtolower( trim( $extension, '.' ) );

		$handlers = self::get_all_handlers();

		foreach ( $handlers as $handler ) {
			if ( in_array( $extension, $handler->get_extensions(), true ) ) {
				return $handler;
			}
		}

		return new \WP_Error(
			'unsupported_format',
			sprintf(
				/* translators: %s: file extension */
				__( 'Unsupported file format: %s', 'import-export-by-rockstarlab' ),
				$extension
			)
		);
	}

	/**
	 * Get format handler by MIME type
	 *
	 * @param string $mime_type MIME type
	 * @return File_Format_Interface|WP_Error Handler instance or WP_Error
	 */
	public static function get_handler_by_mime( $mime_type ) {
		$mime_type = strtolower( trim( $mime_type ) );

		$handlers = self::get_all_handlers();

		foreach ( $handlers as $handler ) {
			if ( in_array( $mime_type, $handler->get_mime_types(), true ) ) {
				return $handler;
			}
		}

		return new \WP_Error(
			'unsupported_mime',
			sprintf(
				/* translators: %s: MIME type */
				__( 'Unsupported MIME type: %s', 'import-export-by-rockstarlab' ),
				$mime_type
			)
		);
	}

	/**
	 * Get format handler by file path
	 *
	 * Automatically detects format from file extension.
	 *
	 * @param string $file_path Absolute path to file
	 * @return File_Format_Interface|WP_Error Handler instance or WP_Error
	 */
	public static function get_handler_by_file( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return new \WP_Error( 'file_not_found', __( 'File not found', 'import-export-by-rockstarlab' ) );
		}

		$extension = pathinfo( $file_path, PATHINFO_EXTENSION );

		return self::get_handler_by_extension( $extension );
	}

	/**
	 * Get format handler by format name
	 *
	 * @param string $format Format name (csv, json, xlsx, ods, xml)
	 * @return File_Format_Interface|WP_Error Handler instance or WP_Error
	 */
	public static function get_handler( $format ) {
		$format = strtolower( trim( $format ) );

		$class_map = [
			'csv'  => CSV_Format::class,
			'json' => JSON_Format::class,
			'xlsx' => Spreadsheet_Format::class,
			'ods'  => Spreadsheet_Format::class,
			'xml'  => XML_Format::class,
		];

		if ( ! isset( $class_map[ $format ] ) ) {
			return new \WP_Error(
				'invalid_format',
				sprintf(
					/* translators: %s: format name */
					__( 'Invalid format: %s', 'import-export-by-rockstarlab' ),
					$format
				)
			);
		}

		$class = $class_map[ $format ];

		$handler_key = Spreadsheet_Format::class === $class ? 'spreadsheet' : $format;

		if ( ! isset( self::$handlers[ $handler_key ] ) ) {
			self::$handlers[ $handler_key ] = new $class();
		}

		return self::$handlers[ $handler_key ];
	}

	/**
	 * Create format handler by format name (alias for get_handler)
	 *
	 * @param string $format Format name (csv, json, xlsx, ods, xml)
	 * @return File_Format_Interface|WP_Error Handler instance or WP_Error
	 */
	public static function create( $format ) {
		return self::get_handler( $format );
	}

	/**
	 * Get all registered format handlers
	 *
	 * @return array Array of handler instances
	 */
	public static function get_all_handlers() {
		return [
			self::get_handler( 'csv' ),
			self::get_handler( 'json' ),
			self::get_handler( 'xlsx' ),
			self::get_handler( 'xml' ),
		];
	}

	/**
	 * Get list of supported formats
	 *
	 * @return array Array of format names
	 */
	public static function get_supported_formats() {
		return [ 'csv', 'json', 'xlsx', 'ods', 'xml' ];
	}

	/**
	 * Get list of all supported extensions
	 *
	 * @return array Array of file extensions
	 */
	public static function get_supported_extensions() {
		$extensions = [];
		$handlers   = self::get_all_handlers();

		foreach ( $handlers as $handler ) {
			$extensions = array_merge( $extensions, $handler->get_extensions() );
		}

		return array_unique( $extensions );
	}

	/**
	 * Get list of all supported MIME types
	 *
	 * @return array Array of MIME types
	 */
	public static function get_supported_mime_types() {
		$mime_types = [];
		$handlers   = self::get_all_handlers();

		foreach ( $handlers as $handler ) {
			$mime_types = array_merge( $mime_types, $handler->get_mime_types() );
		}

		return array_unique( $mime_types );
	}

	/**
	 * Check if format is supported
	 *
	 * @param string $format Format name or extension
	 * @return bool True if supported
	 */
	public static function is_supported( $format ) {
		$format = strtolower( trim( $format, '.' ) );

		return in_array( $format, self::get_supported_formats(), true ) ||
				in_array( $format, self::get_supported_extensions(), true );
	}

	/**
	 * Validate file format
	 *
	 * @param string $file_path Absolute path to file
	 * @return bool|WP_Error True if valid or WP_Error with validation errors
	 */
	public static function validate_file( $file_path ) {
		$handler = self::get_handler_by_file( $file_path );

		if ( is_wp_error( $handler ) ) {
			return $handler;
		}

		return $handler->validate( $file_path );
	}
}

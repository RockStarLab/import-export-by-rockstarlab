<?php
/**
 * Exporter Interface
 *
 * Defines the contract for all data exporters
 *
 * @package RockStarLab\ImportExport\Model\Export
 */

namespace RockStarLab\ImportExport\Model\Export;

defined( 'ABSPATH' ) || exit;

interface Exporter_Interface {

	/**
	 * Export data from WordPress
	 *
	 * @param array $options Optional. Export options (filters, fields, limits, etc.)
	 * @return array|WP_Error Exported data or WP_Error
	 */
	public function export( $options = [] );

	/**
	 * Get data based on export options
	 *
	 * Retrieve data from database without formatting.
	 *
	 * @param array $options Export options (filters, limits, etc.)
	 * @return array Raw data array
	 */
	public function get_data( $options = [] );

	/**
	 * Validate export options
	 *
	 * Check if provided options are valid for this exporter.
	 *
	 * @param array $options Options to validate
	 * @return true|WP_Error True if valid or WP_Error with validation errors
	 */
	public function validate_options( $options );

	/**
	 * Get supported export filters
	 *
	 * @return array Array of supported filter keys with descriptions
	 */
	public function get_supported_filters();

	/**
	 * Get available fields for export
	 *
	 * @return array Array of field names that can be exported
	 */
	public function get_available_fields();

	/**
	 * Get default export fields
	 *
	 * @return array Array of fields included by default
	 */
	public function get_default_fields();

	/**
	 * Get exporter name
	 *
	 * @return string Exporter name (e.g., 'posts', 'users', 'comments')
	 */
	public function get_name();

	/**
	 * Get exporter description
	 *
	 * @return string Human-readable description
	 */
	public function get_description();

	/**
	 * Get total count of items available for export
	 *
	 * @param array $options Optional. Export filters
	 * @return int Total count
	 */
	public function get_count( $options = [] );
}

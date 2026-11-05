<?php
/**
 * Importer Interface
 *
 * Defines the contract for all data importers
 *
 * @package RockStarLab\ImportExport\Model\Import
 */

namespace RockStarLab\ImportExport\Model\Import;

defined( 'ABSPATH' ) || exit;

interface Importer_Interface {

	/**
	 * Import data into WordPress
	 *
	 * @param array $data    Array of items to import
	 * @param array $options Optional. Import options (update mode, post status, etc.)
	 * @return array|WP_Error Import results with success/error counts or WP_Error
	 */
	public function import( $data, $options = [] );

	/**
	 * Validate data before import
	 *
	 * Check if data structure is correct and all required fields are present.
	 *
	 * @param array $data Data to validate
	 * @return true|WP_Error True if valid or WP_Error with validation errors
	 */
	public function validate( $data );

	/**
	 * Prepare raw data for import
	 *
	 * Transform and sanitize raw data into format ready for import.
	 *
	 * @param array $raw_data Raw data from file
	 * @param array $mapping  Optional. Field mapping configuration
	 * @return array Prepared data
	 */
	public function prepare( $raw_data, $mapping = [] );

	/**
	 * Get list of required fields
	 *
	 * @return array Array of required field names
	 */
	public function get_required_fields();

	/**
	 * Get list of optional fields
	 *
	 * @return array Array of optional field names
	 */
	public function get_optional_fields();

	/**
	 * Get supported import options
	 *
	 * @return array Array of supported option keys with descriptions
	 */
	public function get_supported_options();

	/**
	 * Get importer name
	 *
	 * @return string Importer name (e.g., 'posts', 'users', 'comments')
	 */
	public function get_name();

	/**
	 * Get importer description
	 *
	 * @return string Human-readable description
	 */
	public function get_description();
}

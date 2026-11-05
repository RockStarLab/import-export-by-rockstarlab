<?php
/**
 * File Format Interface
 *
 * Defines the contract for all file format handlers (CSV, JSON, XML)
 *
 * @package RockStarLab\ImportExport\Model\Format
 */

namespace RockStarLab\ImportExport\Model\Format;

defined( 'ABSPATH' ) || exit;

interface File_Format_Interface {

	/**
	 * Parse entire file and return all data
	 *
	 * @param string $file_path Absolute path to file
	 * @param array  $options   Optional. Parser options (delimiter, encoding, etc.)
	 * @return array|WP_Error Array of parsed data or WP_Error on failure
	 */
	public function parse( $file_path, $options = [] );

	/**
	 * Parse file in chunks for memory efficiency
	 *
	 * Used for large files that cannot fit into memory.
	 *
	 * @param string $file_path Absolute path to file
	 * @param int    $offset    Starting position (row number)
	 * @param int    $limit     Number of rows to read
	 * @param array  $options   Optional. Parser options
	 * @return array|WP_Error Array of parsed data or WP_Error on failure
	 */
	public function parse_chunk( $file_path, $offset, $limit, $options = [] );

	/**
	 * Generate file from data array
	 *
	 * @param array  $data      Data to write to file
	 * @param string $file_path Target file path
	 * @param array  $options   Optional. Generation options (headers, formatting, etc.)
	 * @return bool|WP_Error True on success or WP_Error on failure
	 */
	public function generate( $data, $file_path, $options = [] );

	/**
	 * Validate file format and structure
	 *
	 * Check if file can be parsed by this handler.
	 *
	 * @param string $file_path Absolute path to file
	 * @return bool|WP_Error True if valid or WP_Error with validation errors
	 */
	public function validate( $file_path );

	/**
	 * Get file headers/column names
	 *
	 * Extract column names or field structure from file.
	 *
	 * @param string $file_path Absolute path to file
	 * @param array  $options   Optional. Parser options
	 * @return array|WP_Error Array of column names or WP_Error on failure
	 */
	public function get_headers( $file_path, $options = [] );

	/**
	 * Count total rows in file
	 *
	 * Used for progress tracking.
	 *
	 * @param string $file_path Absolute path to file
	 * @return int|WP_Error Row count or WP_Error on failure
	 */
	public function count_rows( $file_path );

	/**
	 * Get supported file extensions
	 *
	 * @return array Array of supported extensions (e.g., ['csv', 'txt'])
	 */
	public function get_extensions();

	/**
	 * Get MIME types supported by this handler
	 *
	 * @return array Array of MIME types (e.g., ['text/csv', 'application/csv'])
	 */
	public function get_mime_types();
}

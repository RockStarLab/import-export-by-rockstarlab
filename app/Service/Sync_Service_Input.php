<?php
/**
 * Parsed input helpers for the extracted sync services.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

/** No HTTP globals, nonces or response termination inside business operations. */
trait Sync_Service_Input {
	/**
	 * Read a parsed value. Transport adapters sanitize their own inputs.
	 *
	 * @param array  $input Parsed input.
	 * @param string $key Key.
	 * @param mixed  $default_value Default value.
	 * @return mixed
	 */
	private function param( array $input, $key, $default_value = null ) {
		return $input[ $key ] ?? $default_value;
	}

	/**
	 * Read an array from parsed input.
	 *
	 * @param array  $input Parsed input.
	 * @param string $key Key.
	 * @param array  $default_value Default value.
	 * @return array
	 */
	private function array_param( array $input, $key, $default_value = [] ) {
		return isset( $input[ $key ] ) && is_array( $input[ $key ] ) ? $input[ $key ] : $default_value;
	}

	/**
	 * Return a service failure to the calling transport.
	 *
	 * @param string $message Explanation.
	 * @return \WP_Error
	 */
	private function error( $message ) {
		return new \WP_Error( 'rsl_ie_sync_error', $message, [ 'status' => 400 ] );
	}

	/**
	 * Preserve legacy response payloads without emitting JSON or exiting.
	 *
	 * @param array $result Business result.
	 * @return array
	 */
	private function success( array $result ) {
		return $result;
	}
}

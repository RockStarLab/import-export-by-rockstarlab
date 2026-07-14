<?php
/**
 * View Class
 *
 * Handles loading and rendering of view templates.
 *
 * @package RockStarLab\ImportExport\View
 */

namespace RockStarLab\ImportExport\View;

defined( 'ABSPATH' ) or exit;

class View {

	/**
	 * Load view. Used on back-end side
	 *
	 * @throws \Exception
	 **/
	function load( $path = '', $data = [], $return = false, $base = null ) {

		if ( is_null( $base ) ) {
			$base = RSL_IE_PATH . '/app/View/';
		} else {
			$base = wp_normalize_path( RSL_IE_PATH . '/' . $base );
		}

		$full_path = $base . $path . '.php';

		if ( $return ) {
			ob_start();
		}

		if ( file_exists( $full_path ) ) {
			require $full_path;
		} else {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- $full_path is a server-side path, not user output.
			throw new \Exception( 'The view path ' . esc_html( $full_path ) . ' can not be found.' );
		}

		if ( $return ) {
			return ob_get_clean();
		}
	}
}

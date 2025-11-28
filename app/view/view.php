<?php

namespace WP_AIE\view;
defined( 'ABSPATH') or exit;

/**
 * View
 **/
class view {

	/**
	 * Load view. Used on back-end side
	 *
	 * @throws \Exception
	 **/
	function load( $path = '', $data = [], $return = false, $base = null ) {

		if( is_null( $base ) ) {
			$base = WP_AIE_PATH . '/app/view/';
		} else {
			$base = wp_normalize_path( WP_AIE_PATH. '/' . $base );
		}

		$full_path = $base . $path . '.php';

		if( $return ) {
			ob_start();
		}

		if( file_exists( $full_path ) ) {
			require $full_path;
		} else {
			throw new \Exception( 'The view path ' . $full_path . ' can not be found.' );
		}

		if( $return ) {
			return ob_get_clean();
		}

	}

}
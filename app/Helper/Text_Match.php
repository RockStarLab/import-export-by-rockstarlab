<?php
/**
 * Case-insensitive text matching for export filters in any alphabet.
 *
 * stripos() and strtolower() ignore case only for Latin letters, so "мягк" did not find
 * "Мягкая" and "ЗАКАЗ" did not find "заказ". These helpers use mbstring when it is available.
 *
 * @package RockStarLab\ImportExport
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

class Text_Match {

	/**
	 * Case-insensitive strpos for UTF-8 text.
	 *
	 * @param mixed $haystack Text to search in.
	 * @param mixed $needle   Text to find.
	 * @return int|false
	 */
	public static function ipos( $haystack, $needle ) {
		$haystack = (string) $haystack;
		$needle   = (string) $needle;
		if ( function_exists( 'mb_stripos' ) ) {
			return mb_stripos( $haystack, $needle, 0, 'UTF-8' );
		}
		return stripos( $haystack, $needle );
	}

	/**
	 * Lowercase UTF-8 text.
	 *
	 * @param mixed $value Text.
	 * @return string
	 */
	public static function lower( $value ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $value, 'UTF-8' ) : strtolower( (string) $value );
	}
}

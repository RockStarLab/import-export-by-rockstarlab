<?php
/**
 * Unserialize values that come from import files or another site without creating objects.
 *
 * A meta value such as O:8:"Some_Cls":0:{} in a CSV cell must never become a PHP object
 * (PHP object injection). Arrays and scalars are restored as before; anything holding an
 * object is kept as the original text.
 *
 * @package RockStarLab\ImportExport
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

class Serialized {

	/**
	 * Drop-in for maybe_unserialize() on untrusted input.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	public static function maybe( $value ) {
		if ( ! is_string( $value ) || ! is_serialized( $value ) ) {
			return $value;
		}
		$result = self::parse( $value );
		return false === $result && 'b:0;' !== trim( $value ) ? $value : $result;
	}

	/**
	 * Drop-in for @unserialize() on untrusted input: false when the text is not a
	 * serialized array or scalar.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	public static function parse( $value ) {
		if ( ! is_string( $value ) ) {
			return false;
		}
		$result = @unserialize( trim( $value ), [ 'allowed_classes' => false ] ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.PHP.DiscouragedPHPFunctions.serialize_unserialize -- Objects are disabled.
		return self::has_object( $result ) ? false : $result;
	}

	/**
	 * Whether a value holds an object anywhere.
	 *
	 * @param mixed $value Value.
	 * @param int   $depth Depth.
	 * @return bool
	 */
	private static function has_object( $value, $depth = 0 ) {
		if ( is_object( $value ) ) {
			return true;
		}
		if ( is_array( $value ) ) {
			if ( $depth > 64 ) {
				return true;
			}
			foreach ( $value as $item ) {
				if ( self::has_object( $item, $depth + 1 ) ) {
					return true;
				}
			}
		}
		return false;
	}
}

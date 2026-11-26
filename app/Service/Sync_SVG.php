<?php
/** Sanitized SVG support (and Elementor Pro Lottie JSON files) scoped to media synchronization. */
namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;
class Sync_SVG {
	public static function available() {
		return class_exists( '\Elementor\Core\Utils\Svg\Svg_Sanitizer' ) && class_exists( 'DOMDocument' ) && class_exists( 'SimpleXMLElement' );
	}
	/** Elementor Pro keeps Lottie animations as .json files in the Media Library. */
	public static function json_available() {
		return defined( 'ELEMENTOR_PRO_VERSION' );
	}
	public static function mimes() {
		$mimes = get_allowed_mime_types();
		if ( self::available() ) {
			$mimes['svg'] = 'image/svg+xml'; }
		if ( self::json_available() && ! isset( $mimes['json'] ) ) {
			$mimes['json'] = 'application/json'; }
		return $mimes;
	}
	/**
	 * Check a received file whose type is only allowed for synchronization.
	 *
	 * @param string $path File.
	 * @param string $name File name.
	 * @return bool
	 */
	public static function sanitize( $path, $name ) {
		$ext = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( 'json' === $ext ) {
			// Only well-formed JSON (a Lottie animation is one JSON object) is kept.
			$text = is_readable( $path ) ? file_get_contents( $path, false, null, 0, 20 * MB_IN_BYTES ) : false;
			return is_string( $text ) && is_array( json_decode( $text, true ) );
		}
		if ( 'svg' !== $ext ) {
			return true; }
		// Without Elementor, retain the site's existing MIME and sideload policy.
		if ( ! self::available() ) {
			return true; }
		return ( new \Elementor\Core\Utils\Svg\Svg_Sanitizer() )->sanitize_file( $path );
	}
}

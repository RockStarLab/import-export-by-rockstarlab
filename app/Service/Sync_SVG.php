<?php
/** Sanitized SVG support scoped to media synchronization. */
namespace RockStarLab\ImportExport\Service;
defined( 'ABSPATH' ) || exit;
class Sync_SVG {
    public static function available() {
        return class_exists( '\Elementor\Core\Utils\Svg\Svg_Sanitizer' ) && class_exists( 'DOMDocument' ) && class_exists( 'SimpleXMLElement' );
    }
    public static function mimes() {
        $mimes = get_allowed_mime_types();
        if ( self::available() ) { $mimes['svg'] = 'image/svg+xml'; }
        return $mimes;
    }
    public static function sanitize( $path, $name ) {
        if ( 'svg' !== strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ) ) { return true; }
        // Without Elementor, retain the site's existing MIME and sideload policy.
        if ( ! self::available() ) { return true; }
        return ( new \Elementor\Core\Utils\Svg\Svg_Sanitizer() )->sanitize_file( $path );
    }
}

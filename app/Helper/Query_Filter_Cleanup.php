<?php
/**
 * Remove only the temporary query filters an exporter added.
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Exporters add closures to posts_where/posts_join around one WP_Query. They used to clean up
 * with remove_all_filters( 'posts_where', 10 ), which also removed every other plugin's filter at
 * priority 10 for the rest of the request (ACF's field-key lookup among them: after an export,
 * acf_get_field( 'field_x' ) returned another field and ACF values were written under the wrong
 * field). This removes only closures defined in the given file.
 */
class Query_Filter_Cleanup {

	/**
	 * Remove closures declared in $file from the hooks at $priority.
	 *
	 * @param string   $file     File that declared the closures (__FILE__ of the exporter).
	 * @param string[] $hooks    Hook names.
	 * @param int      $priority Priority used by add_filter().
	 * @return void
	 */
	public static function remove_own_closures( $file, array $hooks = array( 'posts_where', 'posts_join' ), $priority = 10 ) {
		global $wp_filter;
		foreach ( $hooks as $hook ) {
			if ( empty( $wp_filter[ $hook ] ) || ! isset( $wp_filter[ $hook ]->callbacks[ $priority ] ) ) {
				continue;
			}
			foreach ( $wp_filter[ $hook ]->callbacks[ $priority ] as $callback ) {
				$function = $callback['function'] ?? null;
				if ( ! $function instanceof \Closure ) {
					continue;
				}
				try {
					$declared = ( new \ReflectionFunction( $function ) )->getFileName();
				} catch ( \ReflectionException $e ) {
					continue;
				}
				if ( $declared === $file ) {
					remove_filter( $hook, $function, $priority );
				}
			}
		}
	}
}

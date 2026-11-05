<?php
/**
 * Closed bridge for premium product references.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

/** Keep WooCommerce decisions and writes in the premium provider. */
class Sync_Product_Links {
	/** Validate explicit deferred payloads before any receiver writes. */
	public static function validate_deferred( array $posts ) {
		foreach ( $posts as $post ) {
			if ( is_array( $post ) && array_key_exists( 'woo_variations_deferred', $post ) ) {
				$valid = apply_filters( 'rsl_ie_sync_deferred_product', Migration_Sync_Maps::error(), $post );
				if ( true !== $valid ) {
					return is_wp_error( $valid ) ? $valid : Migration_Sync_Maps::error(); }
			}
		}
		return true;
	}
	/**
	 * Current owned child fingerprints.
	 *
	 * @var array|null
	 */
	private static $active = null;

	/**
	 * Scope transfer evidence to one child invocation.
	 *
	 * @param array    $hashes Frozen fingerprints.
	 * @param callable $callback Child operation.
	 * @return mixed
	 */
	public static function during( array $hashes, callable $callback ) {
		$before       = self::$active;
		self::$active = $hashes;
		try {
			return $callback();
		} finally {
			self::$active = $before;
		}
	}

	/**
	 * Present deferred product phases from the stored parent checkpoint.
	 *
	 * @param array  $state Parent state.
	 * @param string $fallback Existing phase label.
	 * @return string
	 */
	public static function stage( array $state, $fallback ) {
		if ( 'syncing' !== ( $state['stage'] ?? '' ) || ! isset( $state['woo_graph'] ) || ! empty( $state['woo_graph']['complete'] ) ) {
			return $fallback;
		}
		$count = count(
			array_filter(
				$state['requests'] ?? [],
				static function ( $request ) {
					return 'pro' === $request['params']['edition'] && in_array( $request['params']['operation'], [ 'sync-push', 'sync-pull' ], true ) && 'product' === ( $request['params']['input']['post_type'] ?? '' );
				}
			)
		);
		if ( count( $state['woo_graph']['snapshots'] ?? [] ) < $count ) {
			return 'preparing_woo_references';
		}
		if ( ( $state['index'] ?? 0 ) >= count( $state['requests'] ?? [] ) && count( $state['acf_graph']['done'] ?? [] ) < count( $state['acf_graph']['snapshots'] ?? [] ) ) {
			return $fallback;
		}
		return ( $state['index'] ?? 0 ) >= count( $state['requests'] ?? [] ) ? 'finalizing_woo_references' : $fallback;
	}

	/** Return active transport evidence. @return array */
	public static function request() {
		return apply_filters( 'rsl_ie_sync_product_request', null === self::$active ? [] : [ 'woo_graph_hashes' => self::$active ] );
	}

	/**
	 * Dispatch a server-selected transfer phase.
	 *
	 * @param string $phase Export, prepare, strip or finalize.
	 * @param array  $data Records or prepared assignments.
	 * @param array  $context Maps and connection.
	 * @return mixed
	 */
	public static function run( $phase, array $data, array $context = [] ) {
		if ( null !== self::$active ) {
			$context['graph_hashes'] = self::$active;
		}
		return apply_filters( 'rsl_ie_sync_product_links', Migration_Sync_Maps::error(), $phase, $data, $context );
	}
}

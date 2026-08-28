<?php
/**
 * Export and sync button location settings.
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Stores configurable admin locations for export and sync quick actions.
 */
class Button_Location_Settings {

	/** Option name. */
	const OPTION_NAME = 'rsl_ie_button_location_settings';

	/**
	 * Return all supported admin locations grouped for the Settings UI.
	 *
	 * @return array
	 */
	public static function get_location_groups() {
		$groups = [
			'content_types' => [
				'label'       => __( 'Content types', 'import-export-by-rockstarlab' ),
				'description' => __( 'Free post list screens that support the WordPress admin UI.', 'import-export-by-rockstarlab' ),
				'items'       => self::get_post_type_locations(),
			],
			'taxonomies'    => [
				'label'       => __( 'Taxonomies', 'import-export-by-rockstarlab' ),
				'description' => __( 'Free taxonomy term screens.', 'import-export-by-rockstarlab' ),
				'items'       => self::get_taxonomy_locations(),
			],
			'other'         => [
				'label'       => __( 'Other admin screens', 'import-export-by-rockstarlab' ),
				'description' => __( 'Free object list screens that are not post types or taxonomy terms.', 'import-export-by-rockstarlab' ),
				'items'       => self::get_other_locations(),
			],
		];

		/**
		 * Filter export/sync button location groups.
		 *
		 * The free plugin intentionally exposes only free locations here. The
		 * separate PRO addon can append premium-only locations through this hook.
		 *
		 * @param array $groups Location groups.
		 */
		$groups = apply_filters( 'rsl_ie_button_location_groups', $groups );

		foreach ( $groups as $group_key => $group ) {
			if ( empty( $group['items'] ) ) {
				unset( $groups[ $group_key ] );
			}
		}

		return $groups;
	}

	/**
	 * Return saved settings merged with defaults.
	 *
	 * New locations default to enabled so existing installs keep current behavior.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$default_locations = self::get_default_location_map();
		$defaults          = [
			'export_button_locations' => $default_locations,
			'sync_button_locations'   => $default_locations,
		];
		$saved             = get_option( self::OPTION_NAME, [] );

		if ( ! is_array( $saved ) ) {
			return $defaults;
		}

		$settings = wp_parse_args( $saved, $defaults );

		foreach ( [ 'export_button_locations', 'sync_button_locations' ] as $setting_key ) {
			$settings[ $setting_key ] = self::normalize_location_map(
				is_array( $settings[ $setting_key ] ) ? $settings[ $setting_key ] : [],
				$default_locations
			);
		}

		return $settings;
	}

	/**
	 * Save selected admin locations.
	 *
	 * @param array $export_locations Selected export location IDs.
	 * @param array $sync_locations   Selected sync location IDs.
	 * @return bool
	 */
	public static function save( $export_locations, $sync_locations ) {
		$allowed = array_keys( self::get_default_location_map() );

		return update_option(
			self::OPTION_NAME,
			[
				'export_button_locations' => self::selected_ids_to_location_map( $export_locations, $allowed ),
				'sync_button_locations'   => self::selected_ids_to_location_map( $sync_locations, $allowed ),
			],
			false
		);
	}

	/**
	 * Check if an export button location is enabled.
	 *
	 * @param string $location_id Location ID.
	 * @return bool
	 */
	public static function is_export_enabled( $location_id ) {
		$settings = self::get_settings();
		return ! empty( $settings['export_button_locations'][ $location_id ] );
	}

	/**
	 * Check if a sync button location is enabled.
	 *
	 * @param string $location_id Location ID.
	 * @return bool
	 */
	public static function is_sync_enabled( $location_id ) {
		$settings = self::get_settings();
		return ! empty( $settings['sync_button_locations'][ $location_id ] );
	}

	/**
	 * Return a flat location map with every known location enabled.
	 *
	 * @return array
	 */
	private static function get_default_location_map() {
		$locations = [];

		foreach ( self::get_location_groups() as $group ) {
			foreach ( $group['items'] as $location_id => $location ) {
				$locations[ $location_id ] = true;
			}
		}

		return $locations;
	}

	/**
	 * Normalize a saved location map and enable newly discovered locations.
	 *
	 * @param array $saved    Saved map.
	 * @param array $defaults Default map.
	 * @return array
	 */
	private static function normalize_location_map( $saved, $defaults ) {
		$normalized = [];

		foreach ( $defaults as $location_id => $enabled ) {
			$normalized[ $location_id ] = array_key_exists( $location_id, $saved ) ? (bool) $saved[ $location_id ] : (bool) $enabled;
		}

		return $normalized;
	}

	/**
	 * Convert selected checkbox IDs into a full location map.
	 *
	 * @param array $selected_ids Selected IDs.
	 * @param array $allowed_ids  Allowed IDs.
	 * @return array
	 */
	private static function selected_ids_to_location_map( $selected_ids, $allowed_ids ) {
		$selected_ids = is_array( $selected_ids ) ? $selected_ids : [];
		$selected     = array_fill_keys( $allowed_ids, false );

		foreach ( $selected_ids as $location_id ) {
			if ( ! is_scalar( $location_id ) ) {
				continue;
			}

			$location_id = sanitize_text_field( wp_unslash( $location_id ) );
			if ( in_array( $location_id, $allowed_ids, true ) ) {
				$selected[ $location_id ] = true;
			}
		}

		return $selected;
	}

	/**
	 * Return admin-visible post type locations.
	 *
	 * @return array
	 */
	private static function get_post_type_locations() {
		$locations  = [];
		$post_types = [
			'post',
			'page',
		];

		foreach ( $post_types as $post_type ) {
			if ( ! post_type_exists( $post_type ) ) {
				continue;
			}

			$object = get_post_type_object( $post_type );
			if ( ! $object || empty( $object->show_ui ) ) {
				continue;
			}

			$locations[ 'post_type:' . $post_type ] = [
				'label'       => $object->labels->name,
				'description' => sprintf(
					/* translators: %s: post type slug. */
					__( 'Post type: %s', 'import-export-by-rockstarlab' ),
					$post_type
				),
			];
		}

		return $locations;
	}

	/**
	 * Return taxonomy locations.
	 *
	 * @return array
	 */
	private static function get_taxonomy_locations() {
		$locations  = [];
		$taxonomies = [
			'category',
			'post_tag',
		];

		foreach ( $taxonomies as $taxonomy ) {
			if ( ! taxonomy_exists( $taxonomy ) ) {
				continue;
			}

			$object = get_taxonomy( $taxonomy );
			if ( ! $object || empty( $object->show_ui ) ) {
				continue;
			}

			$locations[ 'taxonomy:' . $taxonomy ] = [
				'label'       => $object->labels->name,
				'description' => sprintf(
					/* translators: %s: taxonomy slug. */
					__( 'Taxonomy: %s', 'import-export-by-rockstarlab' ),
					$taxonomy
				),
			];
		}

		return $locations;
	}

	/**
	 * Return other free admin object locations.
	 *
	 * @return array
	 */
	private static function get_other_locations() {
		return [
			'admin:comments' => [
				'label'       => __( 'Comments', 'import-export-by-rockstarlab' ),
				'description' => __( 'WordPress comments list screen.', 'import-export-by-rockstarlab' ),
			],
		];
	}
}

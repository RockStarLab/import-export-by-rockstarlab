<?php
/**
 * Admin Menu Settings Helper
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Stores and applies configurable plugin admin-menu visibility.
 */
class Admin_Menu_Settings {

	/** Option name. */
	const OPTION_NAME = 'rsl_ie_admin_menu_settings';

	/** Parent admin-menu slug. */
	const PARENT_SLUG = 'import-export-by-rockstarlab';

	/**
	 * Return configurable menu items and their labels.
	 *
	 * @return array
	 */
	public static function get_menu_items() {
		$items = [
			'welcome'         => __( 'Welcome', 'import-export-by-rockstarlab' ),
			'import'          => __( 'Import', 'import-export-by-rockstarlab' ),
			'export'          => __( 'Export', 'import-export-by-rockstarlab' ),
			'content_sync'    => __( 'Content Sync', 'import-export-by-rockstarlab' ),
			'media_sync'      => __( 'Media Sync', 'import-export-by-rockstarlab' ),
			'ai_url_importer' => __( 'AI URL Importer', 'import-export-by-rockstarlab' ),
			'functions'       => __( 'Functions', 'import-export-by-rockstarlab' ),
			'tools'           => __( 'Tools', 'import-export-by-rockstarlab' ),
			'jobs_log'        => __( 'Jobs Log', 'import-export-by-rockstarlab' ),
			'schedules'       => __( 'Schedules', 'import-export-by-rockstarlab' ),
			'affiliation'     => __( 'Affiliation', 'import-export-by-rockstarlab' ),
			'account'         => __( 'Account', 'import-export-by-rockstarlab' ),
			'contact_us'      => __( 'Contact Us', 'import-export-by-rockstarlab' ),
			'support_forum'   => __( 'Support Forum', 'import-export-by-rockstarlab' ),
			'add_ons'         => __( 'Add-Ons', 'import-export-by-rockstarlab' ),
		];

		if ( Pro_Addon::is_pro_active() ) {
			$items = array_slice( $items, 0, 3, true )
				+ [ 'content_updater' => __( 'Content Updater', 'import-export-by-rockstarlab' ) ]
				+ array_slice( $items, 3, null, true );
		}

		return $items;
	}

	/**
	 * Return saved settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$items    = self::get_menu_items();
		$defaults = [
			'menu_title'       => __( 'Import Export by RockStarLab', 'import-export-by-rockstarlab' ),
			'visible_items'    => array_fill_keys( array_keys( $items ), true ),
			'show_tree_action' => false,
		];
		$saved    = get_option( self::OPTION_NAME, [] );

		if ( ! is_array( $saved ) ) {
			return $defaults;
		}

		$settings                  = wp_parse_args( $saved, $defaults );
		$settings['visible_items'] = wp_parse_args(
			is_array( $settings['visible_items'] ) ? $settings['visible_items'] : [],
			$defaults['visible_items']
		);

		foreach ( $settings['visible_items'] as $key => $visible ) {
			$settings['visible_items'][ $key ] = (bool) $visible;
		}

		return $settings;
	}

	/**
	 * Sanitize and save menu settings.
	 *
	 * @param string $menu_title    Custom parent-menu title.
	 * @param array  $visible_items Submitted visible item keys.
	 * @return bool
	 */
	public static function save( $menu_title, $visible_items ) {
		$menu_title = sanitize_text_field( $menu_title );
		if ( '' === $menu_title ) {
			$menu_title = __( 'Import Export by RockStarLab', 'import-export-by-rockstarlab' );
		}

		$allowed = array_keys( self::get_menu_items() );
		$visible = array_fill_keys( $allowed, false );

		foreach ( $visible_items as $item_key ) {
			$item_key = sanitize_key( $item_key );
			if ( in_array( $item_key, $allowed, true ) ) {
				$visible[ $item_key ] = true;
			}
		}

		return update_option(
			self::OPTION_NAME,
			[
				'menu_title'       => $menu_title,
				'visible_items'    => $visible,
				'show_tree_action' => ! empty( self::get_settings()['show_tree_action'] ),
			]
		);
	}

	/**
	 * Enable or disable the hierarchical post-list row action.
	 *
	 * @param bool $enabled Whether the action should be enabled.
	 * @return bool
	 */
	public static function save_show_tree_action( $enabled ) {
		$settings                     = self::get_settings();
		$settings['show_tree_action'] = (bool) $enabled;

		return update_option( self::OPTION_NAME, $settings );
	}

	/**
	 * Apply title and visibility after all plugin/Freemius menu items exist.
	 *
	 * @return void
	 */
	public static function apply() {
		global $menu, $submenu;

		$settings = self::get_settings();

		foreach ( $menu as &$menu_item ) {
			if ( isset( $menu_item[2] ) && self::PARENT_SLUG === $menu_item[2] ) {
				$menu_item[0] = esc_html( $settings['menu_title'] );
				break;
			}
		}
		unset( $menu_item );

		if ( empty( $submenu[ self::PARENT_SLUG ] ) || ! is_array( $submenu[ self::PARENT_SLUG ] ) ) {
			return;
		}

		foreach ( $submenu[ self::PARENT_SLUG ] as $index => $submenu_item ) {
			$slug    = isset( $submenu_item[2] ) ? (string) $submenu_item[2] : '';
			$item_id = self::identify_item( $slug );

			if ( $item_id && empty( $settings['visible_items'][ $item_id ] ) ) {
				unset( $submenu[ self::PARENT_SLUG ][ $index ] );
			}
		}
	}

	/**
	 * Map a submenu target to a configurable item identifier.
	 *
	 * @param string $slug Menu target or URL.
	 * @return string
	 */
	private static function identify_item( $slug ) {
		$slug_map = [
			self::PARENT_SLUG                       => 'welcome',
			'rsl-ie-import'                         => 'import',
			'rsl-ie-export'                         => 'export',
			'rsl-ie-content-updater'                => 'content_updater',
			'rsl-ie-content-sync'                   => 'content_sync',
			'rsl-ie-media-sync'                     => 'media_sync',
			'rsl-ie-ai-url-importer'                => 'ai_url_importer',
			'rsl-ie-tools'                          => 'tools',
			'rsl-ie-jobs-log'                       => 'jobs_log',
			'rsl-ie-schedules'                      => 'schedules',
			self::PARENT_SLUG . '-affiliation'      => 'affiliation',
			self::PARENT_SLUG . '-account'          => 'account',
			self::PARENT_SLUG . '-contact'          => 'contact_us',
			self::PARENT_SLUG . '-wp-support-forum' => 'support_forum',
			self::PARENT_SLUG . '-addons'           => 'add_ons',
		];

		if ( isset( $slug_map[ $slug ] ) ) {
			return $slug_map[ $slug ];
		}

		if ( false !== strpos( $slug, 'freemius.com/contact/' ) ) {
			return 'contact_us';
		}

		if ( false !== strpos( $slug, 'wordpress.org/support/plugin/import-export-by-rockstarlab' ) ) {
			return 'support_forum';
		}

		return '';
	}
}

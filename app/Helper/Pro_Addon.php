<?php
/**
 * Pro Addon Helper
 *
 * Utilities for detecting the optional PRO addon plugin and its license state.
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

class Pro_Addon {

	/**
	 * PRO addon plugin main file (plugin_basename format).
	 */
	public const PRO_PLUGIN_FILE = 'import-export-pro-by-rockstarlab/import-export-pro-by-rockstarlab.php';

	/**
	 * Check whether the PRO addon plugin is active.
	 *
	 * @return bool
	 */
	public static function is_pro_active() {
		$active_plugins = (array) get_option( 'active_plugins', array() );
		if ( in_array( self::PRO_PLUGIN_FILE, $active_plugins, true ) ) {
			return true;
		}

		if ( is_multisite() ) {
			$network_plugins = (array) get_site_option( 'active_sitewide_plugins', array() );
			return isset( $network_plugins[ self::PRO_PLUGIN_FILE ] );
		}

		return false;
	}

	/**
	 * PRO is enabled only when the addon plugin is active.
	 *
	 * @return bool
	 */
	public static function is_pro_enabled() {
		// The free plugin must only expose PRO functionality when the separate
		// PRO addon plugin is installed/active. License validation (if any) should
		// be handled by the PRO addon itself.
		return self::is_pro_active();
	}

	/**
	 * URL to trigger the license activation flow on the Plugins screen.
	 *
	 * @return string
	 */
	public static function get_license_activation_url() {
		if ( function_exists( 'rsl_ie_fs' ) ) {
			// Freemius account screen can open the license activation modal when
			// activate_license=true is present.
			return (string) rsl_ie_fs()->get_account_url( false, [ 'activate_license' => 'true' ], false );
		}

		return admin_url( 'admin.php?page=import-export-by-rockstarlab-account&activate_license=true' );
	}

	/**
	 * Upgrade URL (Freemius).
	 *
	 * @return string
	 */
	public static function get_upgrade_url() {
		if ( function_exists( 'rsl_ie_fs' ) ) {
			return (string) rsl_ie_fs()->get_upgrade_url();
		}

		return '';
	}

	/**
	 * Get CTA config for the PRO promo card.
	 *
	 * @return array{url:string,label:string}
	 */
	public static function get_promo_cta() {
		return [
			'url'   => admin_url( 'admin.php?page=import-export-by-rockstarlab-addons' ),
			'label' => __( 'Buy PRO Addon', 'import-export-by-rockstarlab' ),
		];
	}

	/**
	 * Get the list of PRO features to advertise in the free UI.
	 *
	 * Note: this list is informational only. Actual features are provided by the
	 * separate PRO addon plugin. Keep this list aligned with the content type
	 * cards the PRO addon registers for the given context.
	 *
	 * @param string $context Context string: import|export|updater.
	 * @return array<int,array{title:string,description:string}>
	 */
	public static function get_promo_features( $context ) {
		$context = strtolower( trim( (string) $context ) );

		$features = [];
		$cards    = self::get_pro_content_type_cards( $context );

		foreach ( $cards as $card ) {
			if ( empty( $card['title'] ) ) {
				continue;
			}

			$features[] = [
				'title'       => (string) $card['title'],
				'description' => (string) ( $card['description'] ?? '' ),
			];
		}

		$features[] = [
			'title'       => __( 'Data Transformation Tool', 'import-export-by-rockstarlab' ),
			'description' => __( 'Transform data during import or export process', 'import-export-by-rockstarlab' ),
		];

		return $features;
	}

	/**
	 * Get PRO content type cards (value/icon/title/description) for rendering in the free UI.
	 *
	 * These cards represent functionality provided by the separate PRO addon plugin.
	 *
	 * @param string $context Context string: import|export|updater.
	 * @return array<int,array{value:string,icon:string,title:string,description:string}>
	 */
	public static function get_pro_content_type_cards( $context ) {
		$context = strtolower( trim( (string) $context ) );

		$cards = [];

		if ( in_array( $context, [ 'import', 'export' ], true ) ) {
			$is_import = 'import' === $context;

			$cards[] = [
				'value'       => 'custom_post_types',
				'icon'        => 'dashicons-admin-generic',
				'title'       => __( 'Custom Post Types', 'import-export-by-rockstarlab' ),
				'description' => $is_import
					? __( 'Import any custom post types', 'import-export-by-rockstarlab' )
					: __( 'Export any custom post types', 'import-export-by-rockstarlab' ),
			];
			$cards[] = [
				'value'       => 'media',
				'icon'        => 'dashicons-admin-media',
				'title'       => __( 'Media', 'import-export-by-rockstarlab' ),
				'description' => $is_import
					? __( 'Import media files data', 'import-export-by-rockstarlab' )
					: __( 'Export media files data', 'import-export-by-rockstarlab' ),
			];
			$cards[] = [
				'value'       => 'menu',
				'icon'        => 'dashicons-menu',
				'title'       => __( 'Menus', 'import-export-by-rockstarlab' ),
				'description' => $is_import
					? __( 'Import navigation menus', 'import-export-by-rockstarlab' )
					: __( 'Export navigation menus', 'import-export-by-rockstarlab' ),
			];
			$cards[] = [
				'value'       => 'user',
				'icon'        => 'dashicons-admin-users',
				'title'       => __( 'Users', 'import-export-by-rockstarlab' ),
				'description' => $is_import
					? __( 'Import user accounts', 'import-export-by-rockstarlab' )
					: __( 'Export user accounts', 'import-export-by-rockstarlab' ),
			];
			$cards[] = [
				'value'       => 'comment',
				'icon'        => 'dashicons-admin-comments',
				'title'       => __( 'Comments', 'import-export-by-rockstarlab' ),
				'description' => $is_import
					? __( 'Import comments and reviews', 'import-export-by-rockstarlab' )
					: __( 'Export comments and reviews', 'import-export-by-rockstarlab' ),
			];
			$cards[] = [
				'value'       => 'taxonomy',
				'icon'        => 'dashicons-category',
				'title'       => __( 'Taxonomy Terms', 'import-export-by-rockstarlab' ),
				'description' => $is_import
					? __( 'Import categories, tags, and custom taxonomies', 'import-export-by-rockstarlab' )
					: __( 'Export categories, tags, and custom taxonomies', 'import-export-by-rockstarlab' ),
			];

			if ( class_exists( 'WooCommerce' ) ) {
				$cards[] = [
					'value'       => 'woo_product',
					'icon'        => 'dashicons-products',
					'title'       => __( 'WooCommerce Products', 'import-export-by-rockstarlab' ),
					'description' => $is_import
						? __( 'Import WooCommerce products', 'import-export-by-rockstarlab' )
						: __( 'Export WooCommerce products', 'import-export-by-rockstarlab' ),
				];
				$cards[] = [
					'value'       => 'woo_order',
					'icon'        => 'dashicons-cart',
					'title'       => __( 'WooCommerce Orders (8.0+)', 'import-export-by-rockstarlab' ),
					'description' => $is_import
						? __( 'Import WooCommerce orders', 'import-export-by-rockstarlab' )
						: __( 'Export WooCommerce orders', 'import-export-by-rockstarlab' ),
				];
				$cards[] = [
					'value'       => 'woo_coupon',
					'icon'        => 'dashicons-tickets-alt',
					'title'       => __( 'WooCommerce Coupons', 'import-export-by-rockstarlab' ),
					'description' => $is_import
						? __( 'Import WooCommerce coupons', 'import-export-by-rockstarlab' )
						: __( 'Export WooCommerce coupons', 'import-export-by-rockstarlab' ),
				];
				$cards[] = [
					'value'       => 'woo_attribute',
					'icon'        => 'dashicons-tag',
					'title'       => __( 'WooCommerce Attributes', 'import-export-by-rockstarlab' ),
					'description' => $is_import
						? __( 'Import WooCommerce attributes', 'import-export-by-rockstarlab' )
						: __( 'Export WooCommerce attributes', 'import-export-by-rockstarlab' ),
				];
			}

			$cards[] = [
				'value'       => 'database_table',
				'icon'        => 'dashicons-database-view',
				'title'       => __( 'MySQL Database Table', 'import-export-by-rockstarlab' ),
				'description' => $is_import
					? __( 'Import any MySQL table fields', 'import-export-by-rockstarlab' )
					: __( 'Export any MySQL table fields', 'import-export-by-rockstarlab' ),
			];
		}

		if ( 'updater' === $context ) {
			$cards[] = [
				'value'       => 'custom_post_types',
				'icon'        => 'dashicons-admin-generic',
				'title'       => __( 'Custom Post Types', 'import-export-by-rockstarlab' ),
				'description' => __( 'Update custom post types', 'import-export-by-rockstarlab' ),
			];
			$cards[] = [
				'value'       => 'user',
				'icon'        => 'dashicons-admin-users',
				'title'       => __( 'Users', 'import-export-by-rockstarlab' ),
				'description' => __( 'Update user accounts', 'import-export-by-rockstarlab' ),
			];
			$cards[] = [
				'value'       => 'taxonomy',
				'icon'        => 'dashicons-tag',
				'title'       => __( 'Taxonomy Terms', 'import-export-by-rockstarlab' ),
				'description' => __( 'Update taxonomy terms', 'import-export-by-rockstarlab' ),
			];

			if ( class_exists( 'WooCommerce' ) ) {
				$cards[] = [
					'value'       => 'woo_product',
					'icon'        => 'dashicons-products',
					'title'       => __( 'WooCommerce Products', 'import-export-by-rockstarlab' ),
					'description' => __( 'Update WooCommerce products', 'import-export-by-rockstarlab' ),
				];
			}

			$cards[] = [
				'value'       => 'database_table',
				'icon'        => 'dashicons-database-view',
				'title'       => __( 'MySQL Database Table', 'import-export-by-rockstarlab' ),
				'description' => __( 'Update database table records', 'import-export-by-rockstarlab' ),
			];
		}

		return $cards;
	}
}

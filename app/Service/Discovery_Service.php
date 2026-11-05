<?php
/**
 * Public plugin discovery data.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Ability_Registry;
use RockStarLab\ImportExport\Abilities\Providers\Free_Content_Provider;
use RockStarLab\ImportExport\Helper\Elementor_Fields;
use RockStarLab\ImportExport\Helper\Pro_Addon;
use RockStarLab\ImportExport\Helper\Seo_Fields;
use RockStarLab\ImportExport\Model\Format\Format_Factory;

defined( 'ABSPATH' ) || exit;

/**
 * Returns bounded metadata, never settings, credentials or content records.
 */
class Discovery_Service {

	/**
	 * Read the implemented ability names, not the planned feature catalogue.
	 *
	 * @return array
	 */
	public function get_capabilities() {
		$names = [];
		if ( Ability_Registry::is_available() ) {
			foreach ( wp_get_abilities() as $ability ) {
				$name = $ability->get_name();
				if ( 0 === strpos( $name, 'rsl-ie/' ) || 0 === strpos( $name, 'rsl-ie-pro/' ) ) {
					$names[] = $name;
				}
			}
		}
		sort( $names );

		return [
			'api_version'      => 1,
			'plugin_version'   => RSL_IE_VERSION,
			'abilities'        => $names,
			'pro_addon_active' => Pro_Addon::is_pro_active(),
		];
	}

	/**
	 * Read the free content catalogue; this does not promise workflow endpoints.
	 *
	 * @return array
	 */
	public function list_content_types() {
		return [
			'edition' => 'free',
			'types'   => array_keys( ( new Free_Content_Provider() )->get_definitions() ),
		];
	}

	/**
	 * Read file formats supported by the plugin.
	 *
	 * @return array
	 */
	public function list_formats() {
		return [ 'formats' => Format_Factory::get_supported_formats() ];
	}

	/**
	 * Read integration presence without loading credentials or calling AI.
	 *
	 * @return array
	 */
	public function get_integrations() {
		return [
			'acf'         => function_exists( 'acf_get_field_groups' ) && function_exists( 'acf_get_fields' ),
			'yoast'       => defined( 'WPSEO_VERSION' ),
			'rank_math'   => Seo_Fields::is_rank_math_active(),
			'elementor'   => Elementor_Fields::is_active(),
			'woocommerce' => class_exists( 'WooCommerce' ),
		];
	}
}

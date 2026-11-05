<?php
/**
 * Field transformation integration bridge.
 *
 * The free plugin exposes a narrow integration surface that the separate PRO
 * addon can opt into for field transformations.
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Integration bridge used by the optional PRO addon.
 */
class Field_Transformation_Bridge {

	/**
	 * Check whether the PRO addon has enabled field transformations.
	 *
	 * @return bool
	 */
	public static function is_enabled() {
		return (bool) apply_filters( 'rsl_ie_field_transformations_enabled', false );
	}

	/**
	 * Return the management URL supplied by the PRO addon, if any.
	 *
	 * @return string
	 */
	public static function get_management_url() {
		$url = (string) apply_filters( 'rsl_ie_field_transformations_url', '' );

		return '' !== $url ? esc_url_raw( $url ) : '';
	}

	/**
	 * Let the PRO addon transform one field value.
	 *
	 * @param mixed $value              Current field value.
	 * @param array $transformation_ids Transformation IDs selected by the UI.
	 * @param array $context            Context such as field name, item, and operation.
	 * @return mixed
	 */
	public static function apply( $value, $transformation_ids, $context = [] ) {
		if ( empty( $transformation_ids ) || ! is_array( $transformation_ids ) ) {
			return $value;
		}

		$context = is_array( $context ) ? $context : [];

		return apply_filters( 'rsl_ie_apply_field_transformations', $value, $transformation_ids, $context );
	}
}

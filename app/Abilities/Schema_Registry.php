<?php
/**
 * Shared JSON Schema builders for abilities.
 *
 * @package RockStarLab\ImportExport\Abilities
 */

namespace RockStarLab\ImportExport\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * Builds explicit, closed schemas for the public API.
 */
class Schema_Registry {

	/**
	 * Build an object whose declared fields are all required.
	 *
	 * @param array $properties Property schemas.
	 * @return array
	 */
	public static function object( array $properties ) {
		return array(
			'type'                 => 'object',
			'properties'           => $properties,
			'required'             => array_keys( $properties ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Build a list of strings.
	 *
	 * @return array
	 */
	public static function strings() {
		return array(
			'type'  => 'array',
			'items' => array( 'type' => 'string' ),
		);
	}

	/**
	 * Accept an empty input object, including an omitted input.
	 *
	 * @return array
	 */
	public static function empty_input() {
		return array(
			'type'                 => 'object',
			'default'              => new \stdClass(),
			'additionalProperties' => false,
			'maxProperties'        => 0,
		);
	}
}

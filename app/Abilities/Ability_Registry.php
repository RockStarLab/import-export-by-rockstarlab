<?php
/**
 * WordPress Abilities registration adapter.
 *
 * @package RockStarLab\ImportExport\Abilities
 */

namespace RockStarLab\ImportExport\Abilities;

defined( 'ABSPATH' ) || exit;

/**
 * Registers definitions without depending on AJAX or an MCP installation.
 */
class Ability_Registry {

	/** Site-level opt-in, shared by Free and Pro registration adapters. */
	public static function is_enabled() {
		return '1' === (string) get_option( 'rsl_ie_abilities_enabled', '0' );
	}

	/** Whether the UpdraftPlus runtime is active (not merely installed). */
	public static function is_updraftplus_available() {
		return defined( 'UPDRAFTPLUS_DIR' ) && isset( $GLOBALS['updraftplus'] ) && is_object( $GLOBALS['updraftplus'] );
	}

	/** Effective opt-in for the backup integration. */
	public static function is_updraftplus_enabled() {
		return self::is_enabled() && self::is_updraftplus_available()
			&& '1' === (string) get_option( 'rsl_ie_updraftplus_abilities_enabled', '0' );
	}

	/**
	 * Register a workflow with explicit transport annotations and schemas.
	 *
	 * @param string   $name Namespaced name.
	 * @param array    $definition Reviewed definition.
	 * @param callable $execute Service adapter.
	 * @return \WP_Ability|null
	 */
	public static function register_workflow( $name, array $definition, $execute ) {
		if ( ! self::is_enabled() || ! self::is_available() ) {
			return null;
		}
		return wp_register_ability(
			$name,
			[
				'label'               => $definition['label'],
				'description'         => $definition['description'],
				'category'            => 'rsl-ie',
				'input_schema'        => $definition['input'],
				'output_schema'       => $definition['output'],
				'execute_callback'    => static function ( $input = [] ) use ( $execute ) {
					// Core validates objects but preserves stdClass defaults used by MCP.
					// Service adapters consume an array at the top-level input boundary.
					return $execute( $input instanceof \stdClass ? (array) $input : $input );
				},
				'permission_callback' => static function () {
					return self::is_enabled() ? Access_Policy::check_management() : false;
				},
				'meta'                => [
					'show_in_rest' => true,
					'mcp'          => [
						'public' => true,
						'type'   => 'tool',
					],
					'annotations'  => [
						'readonly'    => $definition['readonly'],
						'destructive' => $definition['destructive'],
						'idempotent'  => $definition['idempotent'],
					],
				],
			]
		);
	}

	/**
	 * Check the complete API surface used by this integration.
	 *
	 * @return bool
	 */
	public static function is_available() {
		return function_exists( 'wp_register_ability' )
			&& function_exists( 'wp_register_ability_category' )
			&& function_exists( 'wp_get_abilities' );
	}

	/**
	 * Register one read-only discovery ability on wp_abilities_api_init.
	 *
	 * @param string   $name        Namespaced ability name.
	 * @param string   $label       Localized label.
	 * @param string   $description Localized description.
	 * @param array    $output      Output schema.
	 * @param callable $execute     Service callback.
	 * @param callable $permission  Permission callback.
	 * @return \WP_Ability|null
	 */
	public static function register_discovery( $name, $label, $description, array $output, $execute, $permission ) {
		if ( ! self::is_enabled() || ! self::is_available() ) {
			return null;
		}

		return wp_register_ability(
			$name,
			[
				'label'               => $label,
				'description'         => $description,
				'category'            => 'rsl-ie',
				'input_schema'        => Schema_Registry::empty_input(),
				'output_schema'       => $output,
				'execute_callback'    => $execute,
				'permission_callback' => static function ( ...$args ) use ( $permission ) {
					return self::is_enabled() ? call_user_func_array( $permission, $args ) : false;
				},
				'meta'                => [
					'show_in_rest' => true,
					'mcp'          => [
						'public' => true,
						'type'   => 'tool',
					],
					'annotations'  => [
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					],
				],
			]
		);
	}
}

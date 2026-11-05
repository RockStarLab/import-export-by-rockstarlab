<?php
/**
 * Shared migration discovery for the wizard and abilities.
 *
 * @package RockStarLab\ImportExport\Abilities
 */

namespace RockStarLab\ImportExport\Abilities;

use RockStarLab\ImportExport\Model\Export\Exporter_Factory;
use RockStarLab\ImportExport\Model\Import\Importer_Factory;

defined( 'ABSPATH' ) || exit;

/** Providers declare supported families; execution still rechecks policy. */
class Migration_Plan {
	/** Missing plugin-defined content is excluded without blocking other archive entries. */
	public static function unavailable_dependency( array $step ) {
		$type = $step['type'] ?? '';
		$taxonomy = $step['taxonomy'] ?? '';
		$post_type = $step['post_type'] ?? '';
		if ( 'taxonomy' === $type && is_string( $taxonomy ) && '' !== $taxonomy && ! taxonomy_exists( $taxonomy ) ) { return true; }
		if ( 'custom_post_types' === $type && is_string( $post_type ) && '' !== $post_type && ! post_type_exists( $post_type ) ) { return true; }
		return ! class_exists( 'WooCommerce' ) && self::requires_woocommerce( $step );
	}

	/** Whether a manifest step belongs to WooCommerce. */
	public static function requires_woocommerce( array $step ) {
		$type = $step['type'] ?? '';
		$taxonomy = $step['taxonomy'] ?? '';
		return ( 0 === strpos( $type, 'woo_' ) ||
			( 'taxonomy' === $type && ( 0 === strpos( $taxonomy, 'product_' ) || 0 === strpos( $taxonomy, 'pa_' ) ) ) );
	}

	/**
	 * Build the wizard's ordered step tree.
	 *
	 * @return array|\WP_Error
	 */
	public static function steps() {
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access; }
		$steps = [];
		foreach ( [
			'media'   => 4,
			'post'    => 5,
			'page'    => 6,
			'comment' => 14,
		] as $type => $order ) {
			$step = self::step( $type, $order );
			if ( $step ) {
				$steps[] = $step; }
		}
		$steps = apply_filters( 'rsl_ie_migration_steps', $steps );
		usort(
			$steps,
			static function ( $a, $b ) {
				return $a['order'] <=> $b['order'];
			}
		);
		return $steps;
	}

	/**
	 * Build one server-owned export definition after current provider checks.
	 *
	 * @param string $type Content family.
	 * @param int    $order Dependency position.
	 * @param array  $options Provider-owned subtype.
	 * @return array|null
	 */
	public static function step( $type, $order, array $options = [] ) {
		if ( 'database_table' === $type ) { return null; }
		if ( is_wp_error( Access_Policy::check_content_type( $type, 'export', [ 'options' => $options ] ) ) || ! Exporter_Factory::is_supported( $type ) ) {
			return null; }
		$exporter = Exporter_Factory::get_exporter( $type );
		if ( is_wp_error( $exporter ) ) {
			return null; }
		$fields = [];
		foreach ( $exporter->get_available_fields() as $field ) {
			if ( is_array( $field ) ) {
				$field = $field['name'] ?? $field['key'] ?? $field['id'] ?? $field['value'] ?? ''; }
			if ( is_string( $field ) && '' !== $field ) {
				$fields[] = sanitize_text_field( $field ); }
		}
		return [
			'key'        => $type,
			'type'       => $type,
			'label'      => ucwords( str_replace( '_', ' ', $type ) ),
			'filename'   => sprintf( '%02d-%s.json', $order, $type ),
			'fields'     => array_values( array_unique( $fields ) ),
			'importable' => Importer_Factory::is_supported( $type ),
			'children'   => [],
			'order'      => $order,
		];
	}

	/**
	 * Resolve a manifest target independently of existing content on this site.
	 *
	 * @param array $step Closed manifest step.
	 * @return array|\WP_Error
	 */
	public static function import_scope( array $step ) {
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access; }
		$free = [
			'media'   => 4,
			'post'    => 5,
			'page'    => 6,
			'comment' => 14,
		];
		$type = $step['type'];
		if ( 'database_table' === $type ) {
			return new \WP_Error( 'rsl_ie_migration_scope', 'Custom database tables are not supported by Content Migration.', [ 'status' => 400 ] );
		}
		if ( isset( $free[ $type ] ) ) {
			if ( ! empty( $step['table_suffix'] ) ) {
				return new \WP_Error( 'rsl_ie_migration_scope', 'Unexpected migration table target.', [ 'status' => 403 ] );
			}
			if ( ! empty( $step['taxonomy'] ) || ( ! empty( $step['post_type'] ) && ( 'media' === $type ? 'attachment' : $type ) !== $step['post_type'] ) ) {
				return new \WP_Error( 'rsl_ie_migration_scope', 'Invalid migration content scope.', [ 'status' => 403 ] );
			}
			$scope = [
				'key'     => $type,
				'type'    => $type,
				'order'   => $free[ $type ],
				'options' => [],
			];
		} else {
			$scope = apply_filters( 'rsl_ie_migration_import_scope', null, $step );
		}
		if ( is_wp_error( $scope ) ) {
			return $scope; }
		if ( ! is_array( $scope ) || ! Importer_Factory::is_supported( $type ) ) {
			return new \WP_Error( 'rsl_ie_migration_provider', 'The migration content provider is unavailable.', [ 'status' => 403 ] ); }
		$access = Access_Policy::check_content_type( $type, 'import', [ 'options' => $scope['options'] ] );
		return is_wp_error( $access ) ? $access : $scope;
	}

	/**
	 * Flatten selectable leaves while preserving migration dependency order.
	 *
	 * @return array|\WP_Error
	 */
	public static function leaves() {
		$steps = self::steps();
		if ( is_wp_error( $steps ) ) {
			return $steps; }
		$result = [];
		foreach ( $steps as $step ) {
			foreach ( ( $step['children'] ? $step['children'] : [ $step ] ) as $leaf ) {
				$leaf['order'] = $step['order'];
				unset( $leaf['children'] );
				$result[ $leaf['key'] ] = $leaf;
			}
		}
		return $result;
	}
}

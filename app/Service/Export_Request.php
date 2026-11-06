<?php
/**
 * One request contract for count, preview and queued exports.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Providers\Free_Content_Provider;
use RockStarLab\ImportExport\Model\Export\Exporter_Factory;
use RockStarLab\ImportExport\Model\Export\Urls_Exporter;
use RockStarLab\ImportExport\Model\Format\Format_Factory;

defined( 'ABSPATH' ) || exit;

/**
 * Normalizes legacy UI envelopes without allowing them to override identity.
 */
class Export_Request {

	/**
	 * Filter conditions understood by the Free query builders.
	 */
	const CONDITIONS = [ 'equals', 'not_equals', 'contains', 'not_contains', 'starts_with', 'ends_with', 'greater', 'less', 'equals_or_greater', 'equals_or_less', 'between', 'in', 'not_in', 'is_empty', 'is_not_empty' ];

	/**
	 * Common spellings agents and scripts use for the canonical conditions.
	 */
	const CONDITION_ALIASES = [
		'eq'                 => 'equals',
		'equal'              => 'equals',
		'neq'                => 'not_equals',
		'not_equal'          => 'not_equals',
		'does_not_contain'   => 'not_contains',
		'gt'                 => 'greater',
		'greater_than'       => 'greater',
		'lt'                 => 'less',
		'less_than'          => 'less',
		'gte'                => 'equals_or_greater',
		'greater_or_equal'   => 'equals_or_greater',
		'greater_or_equals'  => 'equals_or_greater',
		'greater_than_equal' => 'equals_or_greater',
		'lte'                => 'equals_or_less',
		'less_or_equal'      => 'equals_or_less',
		'less_or_equals'     => 'equals_or_less',
		'less_than_equal'    => 'equals_or_less',
		'empty'              => 'is_empty',
		'not_empty'          => 'is_not_empty',
	];

	/**
	 * Map a known alias to its canonical condition; other values pass through unchanged.
	 *
	 * @param string $condition Requested condition.
	 * @return string
	 */
	private static function condition( $condition ) {
		$key = strtolower( trim( $condition ) );
		return self::CONDITION_ALIASES[ $key ] ?? $condition;
	}

	/**
	 * Normalize an authenticated request or trusted stored parameters.
	 *
	 * @param array $input Input parameters.
	 * @return array|\WP_Error
	 */
	public static function normalize( array $input ) {
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		if ( empty( $input['export_type'] ) || ! is_string( $input['export_type'] ) ) {
			return self::error();
		}
		foreach ( [ 'options', 'filters', 'dynamic_filters', 'fields', 'custom_fields', 'field_functions', 'format_options' ] as $key ) {
			if ( isset( $input[ $key ] ) && ! is_array( $input[ $key ] ) ) {
				return self::error();
			}
		}
		$type    = strtolower( trim( $input['export_type'] ) );
		$options = $input['options'] ?? [];
		unset( $options['_rsl_ie_selected_id_field'], $options['_rsl_ie_free_comment_scope'] );
		$filters = [];
		foreach ( [ $options['filters'] ?? [], $input['filters'] ?? [], $options['dynamic_filters'] ?? [], $input['dynamic_filters'] ?? [] ] as $group ) {
			if ( ! is_array( $group ) ) {
				return self::error();
			}
			foreach ( $group as $filter ) {
				if ( ! is_array( $filter ) || ! isset( $filter['field'], $filter['condition'] ) || ! is_string( $filter['field'] ) || ! is_string( $filter['condition'] ) ) {
					return self::error();
				}
				$filter['condition']                  = self::condition( $filter['condition'] );
				$filters[ wp_json_encode( $filter ) ] = $filter;
			}
		}
		$options['filters'] = array_values( $filters );
		unset( $options['dynamic_filters'], $options['limit'], $options['offset'], $options['force_include_id'] );
		foreach ( [ 'fields', 'custom_fields', 'field_functions', 'taxonomy', 'table_name' ] as $key ) {
			if ( array_key_exists( $key, $input ) && null !== $input[ $key ] ) {
				$options[ $key ] = $input[ $key ];
			}
		}
		foreach ( [ 'fields', 'custom_fields', 'field_functions' ] as $key ) {
			if ( isset( $options[ $key ] ) && ! is_array( $options[ $key ] ) ) {
				return self::error();
			}
		}
		$transformations = Access_Policy::check_transformations( $options['field_functions'] ?? [] );
		if ( is_wp_error( $transformations ) ) {
			return $transformations; }
		foreach ( $options['fields'] ?? [] as $field ) {
			if ( ! is_string( $field ) || '' === $field ) {
				return self::error();
			}
		}
		$free = ( new Free_Content_Provider() )->normalize( $type );
		if ( null !== $free ) {
			$type = $free;
			$map  = [
				'post'  => 'post',
				'page'  => 'page',
				'media' => 'attachment',
			];
			if ( isset( $map[ $type ] ) && empty( $options['post_type'] ) ) {
				$options['post_type'] = $map[ $type ];
			}
			if ( isset( $map[ $type ] ) && empty( $options['orderby'] ) ) {
				$options['orderby'] = 'ID';
			}
		} else {
			/**
			 * Normalize an addon's own entity options before shared authorization.
			 *
			 * @param array $request Canonical export type and exporter options.
			 */
			$premium = apply_filters(
				'rsl_ie_normalize_export_request',
				[
					'export_type' => $type,
					'options'     => $options,
				]
			);
			if ( is_wp_error( $premium ) ) {
				return $premium;
			}
			$type    = $premium['export_type'];
			$options = $premium['options'];
		}
		$ids = self::selected_ids( $options );
		if ( is_wp_error( $ids ) ) {
			return $ids;
		}
		if ( $ids ) {
			$id_field             = $options['_rsl_ie_selected_id_field'] ?? ( 'comment' === $type ? 'comment_ID' : 'ID' );
			$options['filters']   = array_values(
				array_filter(
					$options['filters'],
					static function ( $filter ) use ( $id_field ) {
						return $id_field !== $filter['field'];
					}
				)
			);
			$options['filters'][] = [
				'field'     => $id_field,
				'condition' => 'in',
				'value'     => implode( ',', $ids ),
			];
		}
		unset( $options['_rsl_ie_selected_id_field'] );
		$permission = Access_Policy::check_content_type(
			$type,
			'export',
			[
				'options'      => $options,
				'selected_ids' => $ids,
			]
		);
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		$type = $permission['type'];
		if ( ! Exporter_Factory::is_supported( $type ) ) {
			return self::error();
		}
		// An unknown condition is ignored by the query builders, which would silently export everything.
		if ( null !== $free ) {
			foreach ( $options['filters'] as $filter ) {
				if ( ! in_array( $filter['condition'], self::CONDITIONS, true ) ) {
					return new \WP_Error(
						'rsl_ie_invalid_filter_condition',
						sprintf(
							/* translators: 1: rejected filter condition, 2: comma-separated list of supported conditions. */
							__( 'Unsupported filter condition "%1$s". Use one of: %2$s.', 'import-export-by-rockstarlab' ),
							$filter['condition'],
							implode( ', ', self::CONDITIONS )
						),
						[ 'status' => 400 ]
					);
				}
			}
		}
		// A query filter may narrow a Free entity set but may not widen its type.
		if ( null !== $free && 'urls' !== $type ) {
			foreach ( $options['filters'] as $filter ) {
				if ( in_array( $filter['field'], [ 'post_type', 'custom_post_type', 'comment_type' ], true ) && in_array( $filter['condition'], [ 'equals', 'in' ], true ) ) {
					$values = is_array( $filter['value'] ?? null ) ? $filter['value'] : explode( ',', (string) ( $filter['value'] ?? '' ) );
					foreach ( $values as $value ) {
						$permission = Access_Policy::check_content_type( $type, 'export', [ 'records' => [ [ $filter['field'] => trim( (string) $value ) ] ] ] );
						if ( is_wp_error( $permission ) ) {
							return $permission;
						}
					}
				}
			}
		}
		if ( 'comment' === $type ) {
			if ( ! empty( $options['type'] ) && ! in_array( $options['type'], [ 'comment', 'pingback', 'trackback' ], true ) ) {
				return new \WP_Error( 'rsl_ie_content_type_forbidden', __( 'This comment type requires its own content provider.', 'import-export-by-rockstarlab' ), [ 'status' => 403 ] );
			}
			$options['_rsl_ie_free_comment_scope'] = true;
		}
		if ( 'urls' === $type ) {
			$sources = self::url_sources( $options['content_types'] ?? [] );
			if ( is_wp_error( $sources ) ) {
				return $sources;
			}
			$options['content_types'] = $sources;
		}
		$options['items_per_iteration'] = max( 1, min( 500, (int) ( $options['items_per_iteration'] ?? 100 ) ) );
		$format                         = $input['format'] ?? 'csv';
		if ( ! is_string( $format ) || ! Format_Factory::is_supported( $format ) ) {
			return self::error();
		}
		$format_options = is_array( $input['format_options'] ?? null ) ? $input['format_options'] : [];
		// Form posts send checkbox states as the strings "true"/"false"; store real booleans.
		foreach ( [ 'csv_include_header', 'json_pretty_print', 'xml_pretty_print', 'spreadsheet_include_header' ] as $flag ) {
			if ( array_key_exists( $flag, $format_options ) ) {
				$format_options[ $flag ] = rest_sanitize_boolean( $format_options[ $flag ] );
			}
		}
		if ( 'csv' === $format ) {
			$delimiter = $format_options['csv_delimiter'] ?? ',';
			if ( in_array( $delimiter, [ 'tab', '\\t' ], true ) ) {
				$delimiter = "\t";
			}
			if ( ! is_string( $delimiter ) || 1 !== strlen( $delimiter ) ) {
				return self::error();
			}
			$format_options['csv_delimiter'] = $delimiter;
		}
		return [
			'export_type'    => $type,
			'format'         => $format,
			'options'        => $options,
			'format_options' => $format_options,
		];
	}

	/**
	 * Read selected IDs without silently turning an invalid selection into all.
	 *
	 * @param array $options Normalized exporter options.
	 * @return array|\WP_Error
	 */
	private static function selected_ids( array $options ) {
		$result = [];
		foreach ( [ 'selected_ids', 'media_library_selected_ids', 'post_list_selected_ids', 'taxonomy_term_selected_ids' ] as $key ) {
			if ( empty( $options[ $key ] ) ) {
				continue;
			}
			$values = is_array( $options[ $key ] ) ? $options[ $key ] : explode( ',', (string) $options[ $key ] );
			$ids    = [];
			foreach ( $values as $value ) {
				if ( ! is_scalar( $value ) || ! preg_match( '/^[1-9][0-9]*$/', trim( (string) $value ) ) ) {
					return self::error();
				}
				$ids[] = (int) $value;
			}
			$ids = array_values( array_unique( $ids ) );
			sort( $ids );
			if ( $result && $ids !== $result ) {
				return self::error();
			}
			$result = $ids;
		}
		return $result;
	}

	/**
	 * Authorize each URL source, including defaults, through its owning edition.
	 *
	 * @param array $sources Requested source tokens; empty means allowed defaults.
	 * @return array|\WP_Error
	 */
	public static function url_sources( $sources ) {
		if ( ! is_array( $sources ) ) {
			return self::error();
		}
		$defaults = empty( $sources );
		$sources  = $defaults ? Urls_Exporter::get_default_source_tokens() : $sources;
		$result   = [];
		foreach ( $sources as $source ) {
			if ( ! is_string( $source ) ) {
				return self::error();
			}
			$token           = false === strpos( $source, ':' ) ? 'post_type:' . $source : $source;
			[ $kind, $name ] = explode( ':', $token, 2 );
			$allowed         = ( in_array( $kind, [ 'post_type', 'post_type_archive', 'post_type_feed', 'rest_post_type' ], true ) && in_array( $name, [ 'post', 'page' ], true ) )
				|| ( 'standard' === $kind && in_array( $name, [ 'homepage', 'date_archives', 'search_results' ], true ) )
				|| ( 'feed' === $kind && in_array( $name, [ 'main', 'atom', 'comments' ], true ) ) || 'rest:root' === $token;
			if ( ! $allowed ) {
				/**
				 * Authorize URL sources belonging to an optional content provider.
				 *
				 * @param bool $allowed Whether the source is available.
				 * @param string $token Source kind and name.
				 */
				$allowed = true === apply_filters( 'rsl_ie_authorize_export_url_source', false, $token );
			}
			if ( ! $allowed && ! $defaults ) {
				return new \WP_Error( 'rsl_ie_url_source_forbidden', __( 'This URL source requires an available content provider.', 'import-export-by-rockstarlab' ), [ 'status' => 403 ] );
			}
			if ( $allowed ) {
				$result[] = $token;
			}
		}
		return array_values( array_unique( $result ) );
	}

	/**
	 * Return an input error without reflecting private request values.
	 *
	 * @return \WP_Error
	 */
	private static function error() {
		return new \WP_Error( 'rsl_ie_invalid_export_request', __( 'Invalid export parameters, filters or selected IDs.', 'import-export-by-rockstarlab' ), [ 'status' => 400 ] );
	}
}

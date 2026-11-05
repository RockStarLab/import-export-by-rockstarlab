<?php
/**
 * Reviewed file import/export contracts, separate from transport adapters.
 *
 * @package RockStarLab\ImportExport\Abilities
 */

namespace RockStarLab\ImportExport\Abilities;

defined( 'ABSPATH' ) || exit;

/** Closed configuration objects; arbitrary keys occur only in content and field maps. */
class Workflow_Schemas {
	/**
	 * Build a closed object with explicit required fields.
	 *
	 * @param array $properties Properties.
	 * @param array $required Required property names.
	 * @return array
	 */
	public static function object( array $properties, array $required = [] ) {
		return [
			'type'                 => 'object',
			'properties'           => $properties,
			'required'             => $required,
			'additionalProperties' => false,
		];
	}
	/**
	 * Bounded list.
	 *
	 * @param array $items Item schema.
	 * @param int   $max Maximum length.
	 * @return array
	 */
	public static function items( array $items, $max = 1000 ) {
		return [
			'type'     => 'array',
			'items'    => $items,
			'maxItems' => $max,
		];
	}
	/**
	 * String or enumerated string.
	 *
	 * @param array $values Optional values.
	 * @return array
	 */
	public static function string( array $values = [] ) {
		return $values ? [
			'type' => 'string',
			'enum' => $values,
		] : [ 'type' => 'string' ];
	}
	/**
	 * Bounded integer.
	 *
	 * @param int $min Minimum.
	 * @param int $max Maximum.
	 * @return array
	 */
	public static function integer( $min = 0, $max = PHP_INT_MAX ) {
		return [
			'type'    => 'integer',
			'minimum' => $min,
			'maximum' => $max,
		];
	}
	/**
	 * JSON content value; preserves nested ACF/meta data.
	 *
	 * @return array
	 */
	public static function value() {
		return [ 'type' => [ 'string', 'number', 'integer', 'boolean', 'object', 'array', 'null' ] ];
	}
	/**
	 * Opaque file identity.
	 *
	 * @return array
	 */
	public static function file_id() {
		return [
			'type'    => 'string',
			'pattern' => '^file_[a-f0-9]{32}$',
		];
	}
	/**
	 * Durable client request identity.
	 *
	 * @return array
	 */
	public static function request_id() {
		return [
			'type'      => 'string',
			'minLength' => 1,
			'maxLength' => 128,
			'pattern'   => '^[A-Za-z0-9][A-Za-z0-9._:-]*$',
		];
	}
	/**
	 * Parser choices, with no caller-supplied path.
	 *
	 * @return array
	 */
	public static function parser() {
		return self::object(
			[
				'delimiter'  => [
					'type'      => 'string',
					'minLength' => 1,
					'maxLength' => 1,
				],
				'has_header' => [ 'type' => 'boolean' ],
				'encoding'   => [
					'type' => 'string',
					'enum' => [ 'auto', 'UTF-8', 'UTF-16LE', 'UTF-16BE', 'Windows-1250', 'Windows-1251', 'Windows-1252', 'ISO-8859-1', 'ISO-8859-2', 'ISO-8859-15' ],
				],
			]
		);
	}
	/**
	 * Import mapping, including transformations owned by Pro.
	 *
	 * @return array
	 */
	public static function mapping() {
		$entry          = self::object(
			[
				'source_field'    => [
					'type'      => 'string',
					'minLength' => 1,
				],
				'source_index'    => self::integer(),
				'target_field'    => [
					'type'      => 'string',
					'minLength' => 1,
				],
				'function_ids'    => self::items(
					[
						'type'    => [ 'integer', 'string' ],
						'pattern' => '^[A-Za-z0-9_-]+$',
					],
					100
				),
				'taxonomy_format' => self::string( [ 'name', 'slug', 'id' ] ),
			],
			[ 'target_field' ]
		);
		$entry['oneOf'] = [ [ 'required' => [ 'source_field' ] ], [ 'required' => [ 'source_index' ] ] ];
		return self::items( $entry );
	}
	/**
	 * Free importer options and documented legacy aliases.
	 *
	 * @return array
	 */
	public static function import_options() {
		$s = self::string();
		$b = [ 'type' => 'boolean' ];
		$p = array_fill_keys( [ 'duplicate_check', 'unique_field', 'post_status', 'default_status' ], $s );
		foreach ( [ 'duplicate_mode', 'duplicate_handling', 'if_exists' ] as $key ) {
			$p[ $key ] = self::string( [ 'skip', 'update', 'create', 'ignore' ] ); }
		$p['if_not_exists']        = self::string( [ 'create', 'skip' ] );
		$p['post_type']            = self::string( [ 'post', 'page' ] );
		$p['post_author']          = self::integer( 1 );
		$p['comment_status']       = self::string( [ 'open', 'closed' ] );
		$p['ping_status']          = $p['comment_status'];
		$p['default_type']         = self::string( [ 'comment', 'pingback', 'trackback' ] );
		$p['media_duplicate_mode'] = self::string( [ 'skip', 'create', 'replace', 'update' ] );
		$p                        += array_fill_keys( [ 'auto_import_media', 'update_existing', 'import_acf', 'validate_post_exists', 'skip_missing_posts', 'create_missing_posts', 'generate_thumbnails', 'replace_links', 'has_header' ], $b );
		$p['batch_size']           = self::integer( 1, 500 );
		$p['download_timeout']     = self::integer( 1, 300 );
		$p['replace_links_rules']  = self::items(
			self::object(
				[
					'search'  => [
						'type'      => 'string',
						'minLength' => 1,
					],
					'replace' => $s,
				],
				[ 'search', 'replace' ]
			),
			100
		);
		return self::object( $p );
	}
	/**
	 * Import input; request ID is mandatory only for job creation.
	 *
	 * @param bool $start Whether this is a start operation.
	 * @return array
	 */
	public static function import_input( $start = false ) {
		$p        = [
			'file_id'     => self::file_id(),
			'import_type' => self::string( [ 'post', 'page', 'comment', 'media' ] ),
			'mapping'     => self::mapping(),
			'options'     => self::import_options(),
			'delimiter'   => self::parser()['properties']['delimiter'],
			'encoding'    => self::parser()['properties']['encoding'],
		];
		$required = [ 'file_id', 'import_type', 'mapping' ];
		if ( $start ) {
			$p['request_id'] = self::request_id();
			$required[]      = 'request_id'; }
		return self::object( $p, $required );
	}
	/**
	 * Canonical filters used by query builders.
	 *
	 * @return array
	 */
	public static function filters() {
		return self::items(
			self::object(
				[
					'field'     => self::string(),
					'condition' => self::string(),
					'value'     => self::value(),
					'taxonomy'  => self::string(),
					'type'      => self::string(),
				],
				[ 'field', 'condition' ]
			),
			100
		);
	}
	/**
	 * Bounded nested WordPress query clauses, with explicit keys.
	 *
	 * @param string $kind meta, tax or date.
	 * @return array
	 */
	public static function query( $kind ) {
		$s    = self::string();
		$n    = self::integer();
		$keys = 'meta' === $kind ? [
			'key'     => $s,
			'value'   => self::value(),
			'compare' => $s,
			'type'    => $s,
		] : ( 'tax' === $kind ? [
			'taxonomy'         => $s,
			'field'            => $s,
			'terms'            => self::value(),
			'operator'         => $s,
			'include_children' => [ 'type' => 'boolean' ],
		] : [
			'after'     => self::value(),
			'before'    => self::value(),
			'inclusive' => [ 'type' => 'boolean' ],
			'column'    => $s,
			'compare'   => $s,
			'year'      => $n,
			'month'     => $n,
			'day'       => $n,
			'hour'      => $n,
			'minute'    => $n,
			'second'    => $n,
			'week'      => $n,
			'dayofweek' => $n,
		] );
		$leaf = self::object( $keys );
		$node = $leaf;
		for ( $depth = 0; $depth < 3; ++$depth ) {
			$node = [
				'anyOf' => [
					$leaf,
					self::items( $node, 100 ),
					[
						'type'                 => 'object',
						'properties'           => [ 'relation' => self::string( [ 'AND', 'OR' ] ) ],
						'patternProperties'    => [ '^[0-9]+$' => $node ],
						'additionalProperties' => false,
					],
				],
			];
		}
		return $node;
	}
	/**
	 * Free export options; fields and filters have canonical top-level keys.
	 *
	 * @return array
	 */
	public static function export_options() {
		$s                        = self::string();
		$ids                      = self::items( self::integer( 1 ), 10000 );
		$p                        = array_fill_keys( [ 'custom_export_file_name', 'orderby', 's', 'mime_type', 'status' ], $s );
		$p['post_type']           = self::string( [ 'post', 'page', 'attachment' ] );
		$p['type']                = self::string( [ 'comment', 'pingback', 'trackback' ] );
		// WordPress also validates strings as arrays; scalar/list branches are not exclusive.
		$p['post_status']         = [ 'anyOf' => [ $s, self::items( $s ) ] ];
		$p['author']              = [ 'anyOf' => [ self::integer( 1 ), $ids ] ];
		$p['order']               = self::string( [ 'ASC', 'DESC' ] );
		$p['selected_ids']        = $ids;
		$p['post_parent']         = self::integer();
		$p['post_id']             = self::integer( 1 );
		$p['default_category']    = self::integer();
		$p['items_per_iteration'] = self::integer( 1, 500 );
		$p['include_file']        = [ 'type' => 'boolean' ];
		$p['taxonomy']            = self::items(
			self::object(
				[
					'taxonomy'  => $s,
					'terms'     => [ 'anyOf' => [ $s, self::items( self::value() ) ] ],
					'condition' => self::string( [ 'in', 'not_in', 'and', 'or' ] ),
				],
				[ 'taxonomy', 'terms' ]
			),
			100
		);
		$p['content_types']       = self::items( $s, 100 );
		foreach ( [ 'date', 'meta', 'tax' ] as $kind ) {
			$p[ $kind . '_query' ] = self::query( $kind ); }
		return self::object( $p );
	}
	/**
	 * Export request shared by count/preview/start.
	 *
	 * @param bool $start Whether a job will be created.
	 * @return array
	 */
	public static function export_input( $start = false ) {
		$b        = [ 'type' => 'boolean' ];
		$p        = [
			'export_type'     => self::string( [ 'post', 'page', 'comment', 'media', 'urls' ] ),
			'format'          => self::string( [ 'csv', 'json', 'xml', 'xlsx', 'ods' ] ),
			'fields'          => self::items( self::string() ),
			'options'         => self::export_options(),
			'filters'         => self::filters(),
			'taxonomy'        => self::export_options()['properties']['taxonomy'],
			'custom_fields'   => self::items(
				self::object(
					[
						'name'      => self::string(),
						'value'     => self::value(),
						'condition' => self::string(),
					],
					[ 'name', 'condition' ]
				),
				100
			),
			'field_functions' => [
				'type'                 => 'object',
				'additionalProperties' => self::items(
					[
						'type'    => [ 'integer', 'string' ],
						'pattern' => '^[A-Za-z0-9_-]+$',
					],
					100
				),
			],
			'format_options'  => self::object(
				[
					'csv_delimiter'              => self::parser()['properties']['delimiter'],
					'csv_include_header'         => $b,
					'spreadsheet_include_header' => $b,
					'json_pretty_print'          => $b,
					'xml_pretty_print'           => $b,
				]
			),
		];
		$required = [ 'export_type' ];
		if ( $start ) {
			$p['request_id']          = self::request_id();
			$required[]               = 'request_id';
			$required[]               = 'options';
			$p['options']['required'] = [ 'custom_export_file_name' ]; }
		return self::object( $p, $required );
	}
	/**
	 * Shared safe job snapshot.
	 *
	 * @return array
	 */
	public static function job() {
		$p                    = array_fill_keys( [ 'job_id', 'processed', 'total', 'success', 'failed', 'skipped', 'retries', 'poll_after_seconds' ], self::integer() );
		$p                   += array_fill_keys( [ 'operation', 'content_type', 'job_name' ], self::string() );
		$p['status']          = self::string( [ 'pending', 'processing', 'paused', 'completed', 'failed', 'cancelled' ] );
		$p['progress']        = [ 'type' => 'number' ];
		$p['allowed_actions'] = self::items( self::string() );
		$required             = array_keys( $p );
		$p['preparation']     = self::object(
			[
				'format'        => self::string( [ 'csv', 'json', 'xml', 'xlsx', 'ods' ] ),
				'phase'         => self::string( [ 'indexing', 'preparing', 'ready' ] ),
				'prepared_rows' => self::integer(),
				'indexed_rows'  => self::integer(),
			],
			[ 'format', 'phase', 'prepared_rows' ]
		);
		return self::object( $p, $required );
	}
	/**
	 * Queued job response.
	 *
	 * @return array
	 */
	public static function started() {
		return self::object(
			[
				'job_id'             => self::integer( 1 ),
				'status'             => self::string(),
				'deduplicated'       => [ 'type' => 'boolean' ],
				'poll_after_seconds' => self::integer(),
				'scheduling_warning' => self::string(),
			],
			[ 'job_id', 'status', 'deduplicated', 'poll_after_seconds' ]
		);
	}
	/**
	 * File metadata and optional parser preview.
	 *
	 * @return array
	 */
	public static function file() {
		$p                   = array_fill_keys( [ 'file_id', 'file_name', 'format', 'purpose', 'status', 'download_url', 'browser_download_url', 'chunk_url' ], self::string() );
		$p                  += array_fill_keys( [ 'file_size', 'expected_bytes', 'total_chunks', 'received_chunks', 'total_rows', 'columns', 'chunk_index', 'assembled_chunks', 'assembled_bytes' ], self::integer() );
		$p['headers']        = self::items( self::value() );
		$p['preview']        = self::items( self::value(), 5 );
		$p['parser_options'] = self::parser();
		return self::object( $p, [ 'file_id', 'status' ] );
	}
}

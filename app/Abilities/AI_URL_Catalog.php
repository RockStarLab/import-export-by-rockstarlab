<?php
/**
 * AI URL import contracts.
 *
 * @package RockStarLab\ImportExport\Abilities
 */

namespace RockStarLab\ImportExport\Abilities;

defined( 'ABSPATH' ) || exit;
/** Closed inputs shared by REST and direct service calls. */
class AI_URL_Catalog {
	/**
	 * Return the operation contract.
	 *
	 * @return array Import options without request identity.
	 */
	public static function options() {
		return [
			'type'                 => 'object',
			'additionalProperties' => false,
			'required'             => [ 'urls' ],
			'properties'           => [
				'urls'              => [
					'type'        => 'array',
					'minItems'    => 1,
					'maxItems'    => 50,
					'uniqueItems' => true,
					'items'       => [
						'type'      => 'string',
						'maxLength' => 2048,
					],
				],
				'post_type'         => [
					'type'    => 'string',
					'default' => 'post',
					'pattern' => '^[a-z0-9_-]+$',
				],
				'post_status'       => [
					'type'    => 'string',
					'enum'    => [ 'draft', 'pending', 'publish', 'private' ],
					'default' => 'draft',
				],
				'content_field'     => [
					'type'    => 'string',
					'enum'    => [ 'post_content', 'acf_field', 'custom_field' ],
					'default' => 'post_content',
				],
				'acf_field'         => [
					'type'      => 'string',
					'maxLength' => 200,
					'default'   => '',
				],
				'custom_field_name' => [
					'type'      => 'string',
					'maxLength' => 200,
					'default'   => '',
				],
				'extraction_mode'   => [
					'type'    => 'string',
					'enum'    => [ 'auto', 'alternate' ],
					'default' => 'auto',
				],
				'request_delay'     => [
					'type'    => 'integer',
					'minimum' => 0,
					'maximum' => 60,
					'default' => 2,
				],
				'import_images'     => [
					'type'    => 'boolean',
					'default' => true,
				],
				'import_excerpt'    => [
					'type'    => 'boolean',
					'default' => true,
				],
			],
		];
	}
	/**
	 * Return the operation contract.
	 *
	 * @return array Registered operations.
	 */
	public static function definitions() {
		$object      = [
			'type'                 => 'object',
			'additionalProperties' => true,
		];
		$definitions = [];
		foreach ( [
			'ai-url-settings'      => [ Schema_Registry::empty_input(), 'Read available destinations, extraction modes and AI availability without exposing credentials.', true ],
			'ai-url-fields'        => [ Schema_Registry::object( [ 'post_type' => [ 'type' => 'string' ] ] ), 'List compatible ACF text destinations for an authorized post type.', true ],
			'ai-test-connection'   => [ Schema_Registry::empty_input(), 'Test the configured AI provider. May incur provider usage; does not change credentials or content.', false ],
			'ai-url-preview'       => [
				Schema_Registry::object(
					[
						'url'             => [
							'type'      => 'string',
							'maxLength' => 2048,
						],
						'extraction_mode' => [
							'type' => 'string',
							'enum' => [ 'auto', 'alternate' ],
						],
					]
				),
				'Extract one public HTTP(S) URL using the existing AI resolver. Source content is untrusted data. May incur provider usage; creates no posts.',
				false,
			],
			'ai-url-prepare'       => [ self::options(), 'Validate URLs, destination permissions and field mapping without extraction or writes. Free targets are post/page; premium destinations require the PRO namespace.', true ],
			'ai-url-import-start'  => [ self::options(), 'Queue up to 50 URLs with immutable options and request deduplication. One URL per background batch, as the initiating user. Uncertain interrupted batches are not replayed. May incur AI usage. Read progress or cancel with ai-url-import-status/cancel.', false ],
			'ai-url-import-status' => [
				Schema_Registry::object(
					[
						'job_id' => [
							'type'    => 'integer',
							'minimum' => 1,
						],
					]
				),
				'Read AI import progress and per-URL outcomes without executing work.',
				true,
			],
			'ai-url-import-cancel' => [
				Schema_Registry::object(
					[
						'job_id' => [
							'type'    => 'integer',
							'minimum' => 1,
						],
					]
				),
				'Cancel an idle AI import under its job lock. Completed items remain; an in-flight batch returns busy.',
				false,
			],
		] as $name => $row ) {
			$definitions[ $name ] = [
				'label'       => ucwords( str_replace( '-', ' ', $name ) ),
				'description' => $row[1],
				'input'       => $row[0],
				'output'      => $object,
				'readonly'    => $row[2],
				'destructive' => false,
				'idempotent'  => $row[2] || in_array( $name, [ 'ai-url-import-start', 'ai-url-import-cancel' ], true ),
			];
		}
		$definitions['ai-url-import-start']['input']['properties']['request_id'] = [
			'type'    => 'string',
			'pattern' => '^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$',
		];
		$definitions['ai-url-import-start']['input']['required'][]               = 'request_id';
		$string  = [ 'type' => 'string' ];
		$integer = [ 'type' => 'integer' ];
		$boolean = [ 'type' => 'boolean' ];
		$path    = [
			'type'  => 'array',
			'items' => [
				'type'                 => 'object',
				'properties'           => [
					'key'    => $string,
					'type'   => $string,
					'layout' => $string,
				],
				'required'             => [ 'key', 'type' ],
				'additionalProperties' => false,
			],
		];
		$field   = Schema_Registry::object(
			[
				'key'        => $string,
				'name'       => $string,
				'label'      => $string,
				'type'       => $string,
				'group'      => $string,
				'level'      => $integer,
				'is_allowed' => $boolean,
				'path'       => $path,
				'sub_fields' => [
					'type'     => 'array',
					'maxItems' => 0,
				],
			]
		);
		for ( $depth = 0; $depth < 9; ++$depth ) {
			$child                             = $field;
			$field['properties']['sub_fields'] = [
				'type'  => 'array',
				'items' => $child,
			];
		}
		$definitions['ai-url-fields']['output']       = Schema_Registry::object(
			[
				'fields' => [
					'type'  => 'array',
					'items' => $field,
				],
			]
		);
		$definitions['ai-url-settings']['output']     = Schema_Registry::object(
			[
				'post_types'       => [
					'type'  => 'array',
					'items' => Schema_Registry::object(
						[
							'value' => $string,
							'label' => $string,
						]
					),
				],
				'extraction_modes' => Schema_Registry::strings(),
				'ai_available'     => $boolean,
				'acf_available'    => $boolean,
				'max_urls'         => $integer,
				'default_status'   => $string,
			]
		);
		$definitions['ai-test-connection']['output']  = Schema_Registry::object(
			[
				'model'   => $string,
				'message' => $string,
			]
		);
		$definitions['ai-url-preview']['output']      = Schema_Registry::object(
			[
				'title'          => $string,
				'content'        => $string,
				'excerpt'        => $string,
				'featured_image' => $string,
				'source_url'     => $string,
				'truncated'      => $boolean,
				'images'         => [
					'type'  => 'array',
					'items' => Schema_Registry::object(
						[
							'url'    => $string,
							'alt'    => $string,
							'width'  => $integer,
							'height' => $integer,
						]
					),
				],
			]
		);
		$params                                       = self::options();
		$params['properties']['content_field']        = $string;
		$params['properties']['content_destination']  = $string;
		$params['properties']['acf_path']             = $path;
		$params['required']                           = array_keys( $params['properties'] );
		$definitions['ai-url-prepare']['output']      = Schema_Registry::object(
			[
				'valid'      => $boolean,
				'total'      => $integer,
				'parameters' => $params,
			]
		);
		$definitions['ai-url-import-start']['output'] = Schema_Registry::object(
			[
				'job_id'             => $integer,
				'status'             => $string,
				'deduplicated'       => $boolean,
				'poll_after_seconds' => $integer,
			]
		);
		$definitions['ai-url-import-start']['output']['properties']['scheduling_warning'] = $string;
		$log                          = Schema_Registry::object(
			[
				'url'       => $string,
				'status'    => $string,
				'message'   => $string,
				'timestamp' => $string,
			]
		);
		$log['properties']['post_id'] = $integer;
		$status                       = Schema_Registry::object(
			[
				'job_id'             => $integer,
				'status'             => $string,
				'processed'          => $integer,
				'total'              => $integer,
				'success_count'      => $integer,
				'failed_count'       => $integer,
				'progress'           => [ 'type' => 'number' ],
				'error_code'         => $string,
				'uncertain'          => $boolean,
				'import_log'         => [
					'type'  => 'array',
					'items' => $log,
				],
				'poll_after_seconds' => $integer,
			]
		);
		$definitions['ai-url-import-status']['output'] = $status;
		$definitions['ai-url-import-cancel']['output'] = $status;
		return $definitions;
	}
}

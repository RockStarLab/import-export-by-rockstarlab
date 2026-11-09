<?php
/**
 * Saved import field mapping templates.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Mapping_Template_Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * Stores reusable mapping and import settings without file paths or runtime state.
 *
 * Templates live in one non-autoloaded option. Columns are matched by name when a
 * template is applied, so the same template works for every file with that layout.
 */
class Mapping_Template_Service {
	const OPTION        = 'rsl_ie_mapping_templates';
	const MAX_TEMPLATES = 200;
	const MAX_NAME      = 100;
	const MAX_ENTRIES   = 1000;

	/**
	 * Option keys a template may keep. Paths, file IDs and runtime counters never are.
	 *
	 * @var string[]
	 */
	const OPTION_KEYS = [
		'duplicate_check',
		'duplicate_handling',
		'duplicate_mode',
		'unique_field',
		'if_exists',
		'if_not_exists',
		'post_status',
		'default_status',
		'post_type',
		'custom_post_type',
		'post_author',
		'comment_status',
		'ping_status',
		'default_type',
		'download_images',
		'auto_import_media',
		'media_duplicate_mode',
		'update_existing',
		'import_acf',
		'validate_post_exists',
		'skip_missing_posts',
		'create_missing_posts',
		'generate_thumbnails',
		'replace_links',
		'replace_links_rules',
		'has_header',
		'batch_size',
		'download_timeout',
		'table_name',
		'create_table_if_missing',
	];

	/**
	 * Dispatch an ability after schema validation.
	 *
	 * @param string $name  Ability suffix.
	 * @param array  $input Input.
	 * @return array|\WP_Error
	 */
	public function execute( $name, array $input = [] ) {
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		$definition = Mapping_Template_Catalog::definitions()[ $name ] ?? null;
		if ( ! $definition ) {
			return $this->error( 'operation', __( 'Unknown mapping template operation.', 'import-export-by-rockstarlab' ), 400 );
		}
		$valid = rest_validate_value_from_schema( $input, $definition['input'], 'input' );
		if ( is_wp_error( $valid ) ) {
			$valid->add_data( [ 'status' => 400 ] );
			return $valid;
		}
		switch ( $name ) {
			case 'mapping-template-list':
				return [ 'templates' => $this->listing( $input['import_type'] ?? '' ) ];
			case 'mapping-template-get':
				return $this->get( $input['template_id'] );
			case 'mapping-template-save':
				return $this->save( $input );
			case 'mapping-template-from-job':
				return $this->from_job( $input['job_id'], $input['name'] ?? '', ! empty( $input['overwrite'] ) );
			case 'mapping-template-delete':
				return $this->delete( $input['template_id'] );
			case 'mapping-template-apply':
				return $this->apply_to_file( $input['template_id'], $input['file_id'] );
		}
		return $this->error( 'operation', __( 'Unknown mapping template operation.', 'import-export-by-rockstarlab' ), 400 );
	}

	/**
	 * Summaries, newest first, optionally for one import type.
	 *
	 * @param string $import_type Optional import type.
	 * @return array
	 */
	public function listing( $import_type = '' ) {
		$import_type = sanitize_key( (string) $import_type );
		$rows        = [];
		foreach ( $this->all() as $template ) {
			if ( '' !== $import_type && $template['import_type'] !== $import_type ) {
				continue;
			}
			$rows[] = [
				'template_id'   => $template['template_id'],
				'name'          => $template['name'],
				'import_type'   => $template['import_type'],
				'mapping_count' => count( $template['mapping'] ),
				'columns'       => array_values( array_unique( wp_list_pluck( $template['mapping'], 'source_field' ) ) ),
				'updated_at'    => $template['updated_at'],
			];
		}
		usort(
			$rows,
			static function ( $a, $b ) {
				return strcmp( $b['updated_at'], $a['updated_at'] );
			}
		);
		return $rows;
	}

	/**
	 * Read one template.
	 *
	 * @param string $id Template ID.
	 * @return array|\WP_Error
	 */
	public function get( $id ) {
		$all = $this->all();
		$id  = (string) $id;
		return isset( $all[ $id ] ) ? $all[ $id ] : $this->not_found();
	}

	/**
	 * Create or update a template.
	 *
	 * Without template_id a new template is created; an existing name of the same
	 * import type is replaced only with overwrite=true.
	 *
	 * @param array $input name, import_type, mapping, options, parser, template_id, overwrite.
	 * @return array|\WP_Error
	 */
	public function save( array $input ) {
		$name = $this->clean_name( $input['name'] ?? '' );
		if ( '' === $name ) {
			return $this->error( 'template_name', __( 'Enter a template name.', 'import-export-by-rockstarlab' ), 400 );
		}
		$type = sanitize_key( (string) ( $input['import_type'] ?? '' ) );
		if ( '' === $type ) {
			return $this->error( 'template_type', __( 'The template needs an import type.', 'import-export-by-rockstarlab' ), 400 );
		}
		$mapping = $this->clean_mapping( $input['mapping'] ?? [] );
		if ( ! $mapping ) {
			return $this->error( 'template_mapping', __( 'Map at least one column before saving a template.', 'import-export-by-rockstarlab' ), 400 );
		}
		$all = $this->all();
		$id  = isset( $input['template_id'] ) ? (string) $input['template_id'] : '';
		if ( '' !== $id && ! isset( $all[ $id ] ) ) {
			return $this->not_found();
		}
		foreach ( $all as $other ) {
			if ( $other['template_id'] !== $id && $other['import_type'] === $type && 0 === strcasecmp( $other['name'], $name ) ) {
				if ( empty( $input['overwrite'] ) ) {
					return $this->error( 'template_exists', __( 'A template with this name already exists for this content type.', 'import-export-by-rockstarlab' ), 409, [ 'template_id' => $other['template_id'] ] );
				}
				$id = $other['template_id'];
			}
		}
		if ( '' === $id && count( $all ) >= self::MAX_TEMPLATES ) {
			return $this->error( 'template_limit', __( 'You have reached the limit of saved mapping templates. Delete a template you no longer need.', 'import-export-by-rockstarlab' ), 409 );
		}
		$now      = gmdate( 'Y-m-d H:i:s' );
		$existing = '' !== $id ? $all[ $id ] : null;
		if ( '' === $id ) {
			$id = 'tpl_' . substr( md5( wp_generate_uuid4() ), 0, 12 );
		}
		$all[ $id ] = [
			'template_id'   => $id,
			'name'          => $name,
			'import_type'   => $type,
			'mapping'       => $mapping,
			'options'       => $this->clean_options( $input['options'] ?? [] ),
			'parser'        => $this->clean_parser( $input['parser'] ?? [] ),
			'source_job_id' => isset( $input['source_job_id'] ) ? absint( $input['source_job_id'] ) : (int) ( $existing['source_job_id'] ?? 0 ),
			'created_at'    => $existing['created_at'] ?? $now,
			'updated_at'    => $now,
		];
		$this->store( $all );
		return $all[ $id ];
	}

	/**
	 * Rename a template.
	 *
	 * @param string $id   Template ID.
	 * @param string $name New name.
	 * @return array|\WP_Error
	 */
	public function rename( $id, $name ) {
		$template = $this->get( $id );
		if ( is_wp_error( $template ) ) {
			return $template;
		}
		return $this->save(
			[
				'template_id' => $template['template_id'],
				'name'        => $name,
			] + $template
		);
	}

	/**
	 * Delete a template.
	 *
	 * @param string $id Template ID.
	 * @return array|\WP_Error
	 */
	public function delete( $id ) {
		$all = $this->all();
		$id  = (string) $id;
		if ( ! isset( $all[ $id ] ) ) {
			return $this->not_found();
		}
		unset( $all[ $id ] );
		$this->store( $all );
		return [
			'deleted'     => true,
			'template_id' => $id,
		];
	}

	/**
	 * Create a template from the settings of an import job.
	 *
	 * @param int    $job_id    Import job.
	 * @param string $name      Template name; defaults to the job name.
	 * @param bool   $overwrite Replace a template with the same name.
	 * @return array|\WP_Error
	 */
	public function from_job( $job_id, $name = '', $overwrite = false ) {
		$job = rsl_ie()->Model->job->find( absint( $job_id ) );
		if ( ! $job ) {
			return $this->error( 'job_not_found', __( 'Job not found.', 'import-export-by-rockstarlab' ), 404 );
		}
		$params = json_decode( (string) $job->parameters, true );
		if ( 'import' !== $job->type || ! is_array( $params ) || empty( $params['mapping'] ) || ! is_array( $params['mapping'] ) ) {
			return $this->error( 'job_not_mappable', __( 'Only file imports with a field mapping can be saved as a template.', 'import-export-by-rockstarlab' ), 400 );
		}
		$name = $this->clean_name( $name );
		if ( '' === $name ) {
			$name = $this->clean_name( $job->job_name ?? '' );
		}
		if ( '' === $name ) {
			/* translators: %d: job ID. */
			$name = sprintf( __( 'Import job #%d', 'import-export-by-rockstarlab' ), (int) $job->id );
		}
		$options = is_array( $params['options'] ?? null ) ? $params['options'] : [];
		$mapping = $this->name_columns( $params );
		if ( is_wp_error( $mapping ) ) {
			return $mapping;
		}
		return $this->save(
			[
				'name'          => $name,
				'import_type'   => $params['import_type'] ?? $job->data_type,
				'mapping'       => $mapping,
				'options'       => $options,
				'parser'        => [
					'delimiter' => $params['delimiter'] ?? '',
					'encoding'  => $params['encoding'] ?? '',
				],
				'source_job_id' => (int) $job->id,
				'overwrite'     => $overwrite,
			]
		);
	}

	/**
	 * Give every mapping entry of a job its column name.
	 *
	 * Imports started by AI agents may address columns only by position; the
	 * names are then read from the job's import file.
	 *
	 * @param array $params Job parameters.
	 * @return array|\WP_Error
	 */
	private function name_columns( array $params ) {
		$mapping = [];
		foreach ( $params['mapping'] as $key => $entry ) {
			if ( ! is_array( $entry ) ) {
				// Legacy associative mapping: source column => target field.
				$entry = [
					'source_field' => (string) $key,
					'target_field' => (string) $entry,
				];
			}
			$mapping[] = $entry;
		}
		$need = false;
		foreach ( $mapping as $entry ) {
			if ( ! isset( $entry['source_field'] ) || '' === (string) $entry['source_field'] ) {
				$need = true;
			}
		}
		if ( ! $need ) {
			return $mapping;
		}
		$headers = [];
		if ( ! empty( $params['file_id'] ) && File_Store::valid_id( $params['file_id'] ) ) {
			$file = ( new File_Service() )->preview( $params['file_id'], [] );
			if ( ! is_wp_error( $file ) ) {
				$headers = array_values( (array) ( $file['headers'] ?? [] ) );
			}
		}
		foreach ( $mapping as $i => $entry ) {
			if ( ( ! isset( $entry['source_field'] ) || '' === (string) $entry['source_field'] ) && isset( $entry['source_index'] ) ) {
				$index = (int) $entry['source_index'];
				if ( ! isset( $headers[ $index ] ) ) {
					return $this->error( 'job_columns_unavailable', __( 'The column names of this import are no longer available because its file was removed. Save the template from the import wizard instead.', 'import-export-by-rockstarlab' ), 409 );
				}
				$mapping[ $i ]['source_field'] = (string) $headers[ $index ];
			}
		}
		return $mapping;
	}

	/**
	 * Match a template to file columns by name.
	 *
	 * @param array $template Template.
	 * @param array $columns  File column names in file order.
	 * @return array mapping (with source_index), missing, unmapped_columns.
	 */
	public function match( array $template, array $columns ) {
		$index = [];
		foreach ( array_values( $columns ) as $i => $column ) {
			$key = self::column_key( $column );
			if ( '' !== $key && ! isset( $index[ $key ] ) ) {
				$index[ $key ] = $i;
			}
		}
		$mapping = [];
		$missing = [];
		$used    = [];
		foreach ( $template['mapping'] as $entry ) {
			$key = self::column_key( $entry['source_field'] );
			if ( ! isset( $index[ $key ] ) ) {
				$missing[] = [
					'source_field' => $entry['source_field'],
					'target_field' => $entry['target_field'],
				];
				continue;
			}
			$i                  = $index[ $key ];
			$used[ $i ]         = true;
			$row                = [
				'source_index' => $i,
				'source_field' => (string) array_values( $columns )[ $i ],
				'target_field' => $entry['target_field'],
			];
			if ( ! empty( $entry['function_ids'] ) ) {
				$row['function_ids'] = $entry['function_ids'];
			}
			if ( ! empty( $entry['taxonomy_format'] ) ) {
				$row['taxonomy_format'] = $entry['taxonomy_format'];
			}
			$mapping[] = $row;
		}
		$unmapped = [];
		foreach ( array_values( $columns ) as $i => $column ) {
			if ( empty( $used[ $i ] ) ) {
				$unmapped[] = (string) $column;
			}
		}
		return [
			'mapping'          => $mapping,
			'missing'          => $missing,
			'unmapped_columns' => $unmapped,
		];
	}

	/**
	 * Match a template to an uploaded import file.
	 *
	 * @param string $id      Template ID.
	 * @param string $file_id Ready import file.
	 * @return array|\WP_Error
	 */
	public function apply_to_file( $id, $file_id ) {
		$template = $this->get( $id );
		if ( is_wp_error( $template ) ) {
			return $template;
		}
		$file = ( new File_Service() )->preview( $file_id, [] );
		if ( is_wp_error( $file ) ) {
			return $file;
		}
		$match = $this->match( $template, (array) ( $file['headers'] ?? [] ) );
		// Return only the options import-validate/import-start accept, so the agent can pass them on unchanged.
		$allowed = \RockStarLab\ImportExport\Abilities\Workflow_Schemas::import_options()['properties'];
		$options = array_intersect_key( $template['options'], $allowed );
		return [
			'template_id' => $template['template_id'],
			'name'        => $template['name'],
			'import_type' => $template['import_type'],
			'file_id'     => (string) $file_id,
			'options'     => (object) $options,
			// Ready for import-validate/import-start: columns by position only (the schema allows one of source_field/source_index).
			'mapping'     => array_map(
				static function ( $row ) {
					unset( $row['source_field'] );
					return $row;
				},
				$match['mapping']
			),
			'matched'     => array_map(
				static function ( $row ) {
					return [
						'source_field' => $row['source_field'],
						'source_index' => $row['source_index'],
						'target_field' => $row['target_field'],
					];
				},
				$match['mapping']
			),
		] + $match;
	}

	/**
	 * Normalize a column name for matching: no BOM, trimmed, case-insensitive.
	 *
	 * @param mixed $name Column name.
	 * @return string
	 */
	public static function column_key( $name ) {
		$name = preg_replace( '/^\x{FEFF}/u', '', (string) $name );
		$name = preg_replace( '/\s+/u', ' ', trim( (string) $name ) );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $name, 'UTF-8' ) : strtolower( $name );
	}

	/**
	 * All stored templates keyed by ID, dropping malformed rows.
	 *
	 * @return array
	 */
	private function all() {
		$raw = get_option( self::OPTION, [] );
		$out = [];
		foreach ( is_array( $raw ) ? $raw : [] as $id => $row ) {
			if ( is_array( $row ) && isset( $row['template_id'], $row['name'], $row['import_type'], $row['mapping'] ) && $id === $row['template_id'] && is_array( $row['mapping'] ) ) {
				$row['options'] = is_array( $row['options'] ?? null ) ? $row['options'] : [];
				$row['parser']  = is_array( $row['parser'] ?? null ) ? $row['parser'] : [];
				$out[ $id ]     = $row;
			}
		}
		return $out;
	}

	/**
	 * Persist without autoload.
	 *
	 * @param array $all Templates.
	 */
	private function store( array $all ) {
		if ( false === get_option( self::OPTION, false ) ) {
			add_option( self::OPTION, $all, '', false );
			return;
		}
		update_option( self::OPTION, $all, false );
	}

	/**
	 * Clean a display name.
	 *
	 * @param mixed $name Name.
	 * @return string
	 */
	private function clean_name( $name ) {
		$name = trim( sanitize_text_field( is_scalar( $name ) ? (string) $name : '' ) );
		return function_exists( 'mb_substr' ) ? mb_substr( $name, 0, self::MAX_NAME ) : substr( $name, 0, self::MAX_NAME );
	}

	/**
	 * Keep column name, target field and transformation choices; drop file positions.
	 *
	 * @param mixed $mapping Raw mapping.
	 * @return array
	 */
	private function clean_mapping( $mapping ) {
		$out  = [];
		$seen = [];
		foreach ( is_array( $mapping ) ? $mapping : [] as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$source = isset( $entry['source_field'] ) && is_scalar( $entry['source_field'] ) ? preg_replace( '/^\x{FEFF}/u', '', trim( wp_strip_all_tags( (string) $entry['source_field'] ) ) ) : '';
			$target = isset( $entry['target_field'] ) && is_scalar( $entry['target_field'] ) ? trim( sanitize_text_field( (string) $entry['target_field'] ) ) : '';
			if ( '' === $source || '' === $target || strlen( $source ) > 255 || strlen( $target ) > 191 ) {
				continue;
			}
			$key = self::column_key( $source ) . "\0" . $target;
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$row          = [
				'source_field' => $source,
				'target_field' => $target,
			];
			$functions    = [];
			$ids          = [];
			foreach ( (array) ( $entry['functions'] ?? $entry['function_ids'] ?? [] ) as $function ) {
				$fid = is_array( $function ) ? ( $function['id'] ?? '' ) : $function;
				if ( ! is_scalar( $fid ) || ! preg_match( '/^[A-Za-z0-9_-]{1,64}$/', (string) $fid ) ) {
					continue;
				}
				$ids[]       = (string) $fid;
				$functions[] = [
					'id'   => (string) $fid,
					'name' => is_array( $function ) && isset( $function['name'] ) && is_scalar( $function['name'] ) ? sanitize_text_field( (string) $function['name'] ) : '',
				];
				if ( count( $ids ) >= 100 ) {
					break;
				}
			}
			if ( $ids ) {
				$row['function_ids'] = $ids;
				$row['functions']    = $functions;
			}
			if ( isset( $entry['taxonomy_format'] ) && in_array( $entry['taxonomy_format'], [ 'name', 'slug', 'id' ], true ) ) {
				$row['taxonomy_format'] = $entry['taxonomy_format'];
			}
			if ( isset( $entry['field_type'] ) && is_scalar( $entry['field_type'] ) && '' !== sanitize_key( (string) $entry['field_type'] ) ) {
				$row['field_type'] = substr( sanitize_key( (string) $entry['field_type'] ), 0, 40 );
			}
			$out[] = $row;
			if ( count( $out ) >= self::MAX_ENTRIES ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Keep only configuration options with scalar or rule values.
	 *
	 * @param mixed $options Raw options.
	 * @return array
	 */
	private function clean_options( $options ) {
		$out = [];
		foreach ( is_array( $options ) ? $options : [] as $key => $value ) {
			if ( ! in_array( $key, self::OPTION_KEYS, true ) ) {
				continue;
			}
			if ( 'replace_links_rules' === $key ) {
				$rules = [];
				foreach ( is_array( $value ) ? $value : [] as $rule ) {
					if ( is_array( $rule ) && isset( $rule['search'] ) && is_scalar( $rule['search'] ) && '' !== trim( (string) $rule['search'] ) ) {
						$rules[] = [
							'search'  => sanitize_text_field( (string) $rule['search'] ),
							'replace' => isset( $rule['replace'] ) && is_scalar( $rule['replace'] ) ? sanitize_text_field( (string) $rule['replace'] ) : '',
						];
					}
					if ( count( $rules ) >= 100 ) {
						break;
					}
				}
				$out[ $key ] = $rules;
				continue;
			}
			if ( is_bool( $value ) ) {
				$out[ $key ] = $value;
			} elseif ( is_int( $value ) || is_float( $value ) ) {
				$out[ $key ] = (int) $value;
			} elseif ( is_string( $value ) ) {
				if ( in_array( strtolower( $value ), [ 'true', 'false' ], true ) ) {
					$out[ $key ] = 'true' === strtolower( $value );
				} elseif ( in_array( $key, [ 'batch_size', 'post_author', 'download_timeout' ], true ) && is_numeric( $value ) ) {
					$out[ $key ] = (int) $value;
				} else {
					$out[ $key ] = substr( sanitize_text_field( $value ), 0, 191 );
				}
			}
		}
		return $out;
	}

	/**
	 * Keep the CSV separator and encoding the template was made with.
	 *
	 * @param mixed $parser Raw parser choices.
	 * @return array
	 */
	private function clean_parser( $parser ) {
		$out = [];
		if ( is_array( $parser ) ) {
			if ( isset( $parser['delimiter'] ) && is_string( $parser['delimiter'] ) && '' !== $parser['delimiter'] && strlen( $parser['delimiter'] ) <= 10 ) {
				$out['delimiter'] = "\t" === $parser['delimiter'] ? "\t" : sanitize_text_field( $parser['delimiter'] );
			}
			if ( isset( $parser['encoding'] ) && is_string( $parser['encoding'] ) && preg_match( '/^[A-Za-z0-9_-]{1,32}$/', $parser['encoding'] ) ) {
				$out['encoding'] = $parser['encoding'];
			}
		}
		return $out;
	}

	/**
	 * Not found error.
	 *
	 * @return \WP_Error
	 */
	private function not_found() {
		return $this->error( 'template_not_found', __( 'Mapping template not found.', 'import-export-by-rockstarlab' ), 404 );
	}

	/**
	 * Build an error with HTTP status.
	 *
	 * @param string $code    Code suffix.
	 * @param string $message Message.
	 * @param int    $status  Status.
	 * @param array  $data    Extra data.
	 * @return \WP_Error
	 */
	private function error( $code, $message, $status, array $data = [] ) {
		return new \WP_Error( 'rsl_ie_' . $code, $message, [ 'status' => $status ] + $data );
	}
}

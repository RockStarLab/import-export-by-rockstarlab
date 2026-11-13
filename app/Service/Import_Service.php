<?php
/**
 * Shared file import application service.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Execution_Context;
use RockStarLab\ImportExport\Model\Import\Importer_Factory;
use RockStarLab\ImportExport\Model\Format\Format_Factory;

defined( 'ABSPATH' ) || exit;

/**
 * Runs the same preparation, batches and finalization without HTTP globals.
 */
class Import_Service {

	/**
	 * Latest per-row entries kept in a job result (checkpoints stay constant-size).
	 */
	const RESULT_ITEMS_LIMIT = 200;

	/**
	 * Row errors kept verbatim in a job result; the failed counter stays exact.
	 */
	const RESULT_ERRORS_LIMIT = 500;

	/**
	 * Depth of running import batches in this request.
	 *
	 * @var int
	 */
	private static $processing = 0;

	/**
	 * Maximum rows stored per preparation slice (the 1 MB byte budget applies too).
	 */
	const PREPARATION_SLICE_ROWS = 1000;

	/**
	 * Seconds after which an import batch stops early; the next batch resumes from the
	 * per-row checkpoint, so a large Batch Size cannot run into request timeouts.
	 */
	const BATCH_TIME_BUDGET = 20;

	/**
	 * Server-normalized scope for relationship repairs in the current batch.
	 *
	 * @var array
	 */
	private $finalization_request = [];

	/**
	 * Validate a file with the same options, mapping and policy as execution.
	 *
	 * @param array $input Import request.
	 * @return array|\WP_Error
	 */
	public function validate( array $input ) {
		$request = $this->normalize_request( $input );
		if ( is_wp_error( $request ) ) {
			return $request;
		}
		$importer = Importer_Factory::get_importer( $request['import_type'] );
		if ( is_wp_error( $importer ) ) {
			return $importer;
		}
		// Only the unchanged base validator has the first-row-only contract.
		// Custom validators may inspect the whole dataset and retain their path.
		$method = new \ReflectionMethod( $importer, 'validate' );
		$sample = \RockStarLab\ImportExport\Model\Import\Abstract_Importer::class === $method->getDeclaringClass()->getName();
		$sample = true === apply_filters( 'rsl_ie_import_validation_first_row', $sample, $importer );
		$importer->set_options( (array) $request['options'] + [ 'source_format' => (string) ( $request['format'] ?? '' ) ] );
		$mode = apply_filters( 'rsl_ie_import_validation_row_mode', '', $importer );
		if ( in_array( $mode, [ 'all', 'any' ], true ) ) {
			$matched = false;
			$sample  = static function ( $item, $index ) use ( $importer, $mode, &$matched ) {
				if ( $matched ) {
					return false; }
				$valid   = ! is_wp_error( $importer->validate( [ $index => $item ] ) );
				$matched = 'all' === $mode ? ! $valid : $valid;
				return 0 === $index || $matched;
			};
		}
		$prepared = $this->prepare_file( $request, 0, $sample );
		if ( is_wp_error( $prepared ) ) {
			return $prepared; }
		$validation = $importer->validate( $prepared['data'] );
		return is_wp_error( $validation ) ? $validation : [
			'valid'       => true,
			'total_items' => $prepared['total_count'] ?? count( $prepared['data'] ),
		];
	}

	/**
	 * Queue an import with server-owned initiating identity.
	 *
	 * Preparation stays in the first batch to preserve the existing async UI.
	 * No caller-supplied prepared data, offset or execution context is stored.
	 *
	 * @param array $input Import request.
	 * @param int   $migration_parent_id Internal active parent.
	 * @return array|\WP_Error
	 */
	public function start( array $input, $migration_parent_id = 0 ) {
		if ( $migration_parent_id && ! Migration_Service::running( $migration_parent_id ) ) {
			return File_Store::error( 'migration_parent_required', 409 ); }
		$request = $this->normalize_request( $input );
		if ( is_wp_error( $request ) ) {
			return $request;
		}
		$context = Execution_Context::capture();
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		if ( $migration_parent_id ) {
			$request['migration_parent_id'] = (int) $migration_parent_id;
			$parent                         = rsl_ie()->Model->job->find( $migration_parent_id );
			$request['migration_seed_maps'] = json_decode( $parent->settings, true )['maps'] ?? [];
		}
		// Bind the queued job to its source, including edits before its first slice.
		$request['source_sha256'] = hash_file( 'sha256', $request['file_path'] );
		if ( ! is_string( $request['source_sha256'] ) ) {
			return File_Store::error( 'source_changed', 409 );
		}
		$file_path = $request['file_path'];
		unset( $request['file_path'] );
		$request['offset']            = 0;
		$request['execution_context'] = $context->to_storage();
		$result                       = Job_Service::enqueue(
			[
				'type'       => 'import',
				'data_type'  => $request['import_type'],
				'status'     => 'pending',
				'user_id'    => get_current_user_id(),
				'file_path'  => $file_path,
				'parameters' => wp_json_encode( $request ),
			],
			$input['request_id'] ?? ''
		);
		return $result;
	}

	/**
	 * Run one batch requested by an authenticated transport.
	 *
	 * @param int $job_id Job ID.
	 * @return array|\WP_Error
	 */
	public function process_batch( $job_id ) {
		$permission = Access_Policy::check_management();
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		return $this->process_background_batch( $job_id );
	}

	/**
	 * Worker entry point: identity comes exclusively from a stored job.
	 *
	 * Never expose this method directly as an ability/REST callback.
	 *
	 * @param int $job_id Job ID in the current site's jobs table.
	 * @return array|\WP_Error
	 */
	public function process_background_batch( $job_id ) {
		++self::$processing;
		try {
			return $this->run_background_batch( $job_id );
		} finally {
			--self::$processing;
		}
	}

	/**
	 * Whether an import batch is running in this request.
	 *
	 * Triggers use it to ignore posts published by an import, so an import that
	 * publishes posts cannot start itself again.
	 *
	 * @return bool
	 */
	public static function is_processing() {
		return self::$processing > 0;
	}

	/**
	 * Batch body of process_background_batch().
	 *
	 * @param int $job_id Job ID.
	 * @return array|\WP_Error
	 */
	private function run_background_batch( $job_id ) {
		$job_model = rsl_ie()->Model->job;
		$job       = $job_model->find( (int) $job_id );
		if ( ! $job || 'import' !== $job->type || 'media_sync' === $job->data_type ) {
			return new \WP_Error( 'rsl_ie_import_not_found', __( 'Import job not found.', 'import-export-by-rockstarlab' ), [ 'status' => 404 ] );
		}
		$parent = json_decode( $job->parameters, true )['migration_parent_id'] ?? 0;
		if ( $parent && ! Migration_Service::running( $parent ) ) {
			return File_Store::error( 'migration_parent_required', 409 ); }
		$lock = Import_Job_Lock::acquire( $job->id );
		if ( is_wp_error( $lock ) ) {
			return $this->fail( $job->id, $lock );
		}
		if ( false === $lock ) {
			return array_merge(
				$this->snapshot( $job ),
				[
					'completed' => false,
					'locked'    => true,
				]
			);
		}
		try {
			// Re-read after acquisition; another runner may have finished meanwhile.
			$job = $job_model->find( $job->id );
			if ( ! $job ) {
				return new \WP_Error( 'rsl_ie_import_not_found', __( 'Import job not found.', 'import-export-by-rockstarlab' ), [ 'status' => 404 ] );
			}
			if ( ! in_array( $job->status, [ 'pending', 'processing' ], true ) ) {
				return $this->snapshot( $job );
			}
			$parameters = json_decode( (string) $job->parameters, true );
			if ( ! is_array( $parameters ) ) {
				return $this->fail( $job->id, new \WP_Error( 'rsl_ie_invalid_job', __( 'Invalid import job parameters.', 'import-export-by-rockstarlab' ) ) );
			}
			// Legacy jobs have a trusted user_id and live in the current site table.
			$stored = $parameters['execution_context'] ?? [
				'user_id' => (int) $job->user_id,
				'blog_id' => get_current_blog_id(),
			];
			if ( ! is_array( $stored ) || (int) ( $stored['blog_id'] ?? 0 ) !== get_current_blog_id() || (int) ( $stored['user_id'] ?? 0 ) !== (int) $job->user_id ) {
				return $this->fail( $job->id, new \WP_Error( 'rsl_ie_invalid_context', __( 'The job execution context is invalid.', 'import-export-by-rockstarlab' ) ) );
			}
			$context = Execution_Context::from_storage( $stored['user_id'], $stored['blog_id'] );
			$result  = is_wp_error( $context ) ? $context : $context->run(
				function () use ( $job, $parameters ) {
					// Recount each touched term once per batch instead of once per row.
					$already_deferred = wp_defer_term_counting();
					wp_defer_term_counting( true );
					try {
						return $this->run_batch( $job, $parameters );
					} finally {
						if ( ! $already_deferred ) {
							wp_defer_term_counting( false );
						}
					}
				}
			);
			return is_wp_error( $result ) ? $this->fail( $job->id, $result ) : $result;
		} catch ( \Throwable $error ) {
			// Persist failure; never retry a partially executed row automatically.
			return $this->fail( $job->id, new \WP_Error( 'rsl_ie_import_exception', __( 'Import stopped unexpectedly. Review its progress before retrying.', 'import-export-by-rockstarlab' ) ) );
		} finally {
			Import_Job_Lock::release( $lock );
		}
	}

	/**
	 * Validate configuration without invoking transformations or creating jobs.
	 *
	 * @param array $input Import request.
	 * @return array|\WP_Error
	 */
	public function check_request( array $input ) {
		return $this->normalize_request( $input );
	}

	/**
	 * Validate and normalize transport-independent request parameters.
	 *
	 * @param array $input Request parameters.
	 * @return array|\WP_Error
	 */
	private function normalize_request( array $input ) {
		foreach ( [ 'file_id', 'file_path', 'import_type', 'format', 'delimiter', 'encoding' ] as $key ) {
			if ( isset( $input[ $key ] ) && ! is_string( $input[ $key ] ) ) {
				return new \WP_Error( 'rsl_ie_invalid_input', __( 'Invalid import parameters.', 'import-export-by-rockstarlab' ), [ 'status' => 400 ] );
			}
		}
		if ( empty( $input['mapping'] ) || ! is_array( $input['mapping'] ) || ( isset( $input['options'] ) && ! is_array( $input['options'] ) ) ) {
			return new \WP_Error( 'rsl_ie_invalid_mapping', __( 'An import mapping and valid options are required.', 'import-export-by-rockstarlab' ), [ 'status' => 400 ] );
		}
		$mapping = [];
		foreach ( $input['mapping'] as $source => $entry ) {
			if ( is_string( $source ) && is_string( $entry ) ) {
				$entry = [
					'source_field' => $source,
					'target_field' => $entry,
				];
			}
			if ( ! is_array( $entry ) || ! isset( $entry['target_field'] ) || ! is_string( $entry['target_field'] ) || '' === $entry['target_field'] || ( ! isset( $entry['source_index'] ) && ( ! isset( $entry['source_field'] ) || ! is_string( $entry['source_field'] ) || '' === $entry['source_field'] ) ) ) {
				return new \WP_Error( 'rsl_ie_invalid_mapping', __( 'Each mapping must select a source and a destination field.', 'import-export-by-rockstarlab' ), [ 'status' => 400 ] );
			}
			$mapping[] = $entry;
		}
		$groups = [];
		foreach ( $mapping as $entry ) {
			$groups[] = $entry['function_ids'] ?? $entry['functions'] ?? ( empty( $entry['function_id'] ) ? [] : [ $entry['function_id'] ] );
		}
		$transformations = Access_Policy::check_transformations( $groups );
		if ( is_wp_error( $transformations ) ) {
			return $transformations; }
		$type       = $input['import_type'] ?? '';
		$options    = $this->normalize_post_options( $type, $input['options'] ?? [] );
		// Resolve new-table names before checking the same target used by the importer.
		if ( 'database_table' === $type && ! empty( $options['create_table_if_missing'] ) && is_string( $options['table_name'] ?? null ) ) {
			global $wpdb;
			if ( 0 !== strpos( $options['table_name'], $wpdb->prefix ) && '' !== $options['table_name'] ) {
				$options['table_name'] = $wpdb->prefix . $options['table_name'];
			}
		}
		$permission = Access_Policy::check_content_type( $type, 'import', [ 'options' => $options ] );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		$type = $permission['type'];
		if ( ! Importer_Factory::is_supported( $type ) ) {
			return new \WP_Error( 'rsl_ie_unsupported_importer', __( 'The requested importer is unavailable.', 'import-export-by-rockstarlab' ), [ 'status' => 400 ] );
		}
		$format  = strtolower( $input['format'] ?? 'csv' );
		$file_id = $input['file_id'] ?? '';
		if ( ! $file_id && 0 === strpos( $input['file_path'] ?? '', 'rsl-ie-file:' ) ) {
			$file_id = substr( $input['file_path'], 12 );
		}
		if ( $file_id ) {
			$file = ( new File_Service() )->resolve( $file_id, 'import' );
			if ( is_wp_error( $file ) ) {
				return $file; }
			if ( ! empty( $file['migration_scope'] ) ) {
				$scope = \RockStarLab\ImportExport\Abilities\Migration_Plan::import_scope( $file['migration_scope'] );
				if ( is_wp_error( $scope ) ) {
					return $scope; }
				if ( $type !== $scope['type'] ) {
					return File_Store::error( 'migration_target_mismatch', 403 ); }
				foreach ( $scope['options'] as $key => $value ) {
					if ( isset( $options[ $key ] ) && $options[ $key ] !== $value ) {
						return File_Store::error( 'migration_target_mismatch', 403 ); }
					$options[ $key ] = $value;
				}
			}
			$format                = $file['format'];
			$file_path             = $file['path'];
			$source_url            = (string) ( $file['source_url'] ?? '' );
			$input['delimiter']    = $input['delimiter'] ?? $file['parser_options']['delimiter'];
			$input['encoding']     = $input['encoding'] ?? ( $file['parser_options']['encoding'] ?? 'auto' );
			$options['has_header'] = $options['has_header'] ?? $file['parser_options']['has_header'];
		} else {
			$file_path = $this->validate_import_file_path( $input['file_path'] ?? '', $format );
		}
		if ( is_wp_error( $file_path ) ) {
			return $file_path;
		}
		$delimiter = $input['delimiter'] ?? ',';
		if ( 1 !== strlen( $delimiter ) ) {
			return new \WP_Error( 'rsl_ie_invalid_delimiter', __( 'The delimiter must be one byte.', 'import-export-by-rockstarlab' ), [ 'status' => 400 ] );
		}
		$encoding = \RockStarLab\ImportExport\Model\Format\CSV_Format::normalize_encoding( $input['encoding'] ?? 'auto' );
		if ( null === $encoding ) {
			return new \WP_Error( 'rsl_ie_invalid_encoding', __( 'Unsupported file encoding.', 'import-export-by-rockstarlab' ), [ 'status' => 400 ] );
		}
		$options['batch_size'] = max( 1, min( 500, (int) ( $options['batch_size'] ?? 50 ) ) );
		$row_filters           = Import_Row_Filter::normalize( $options['row_filters'] ?? [] );
		if ( is_wp_error( $row_filters ) ) {
			return $row_filters;
		}
		$options['row_filters']       = $row_filters;
		$options['row_filters_match'] = 'any' === ( $options['row_filters_match'] ?? 'all' ) ? 'any' : 'all';
		return [
			// A file downloaded from a URL is downloaded again when a schedule repeats the import.
			'source_url'  => $source_url ?? '',
			'import_type' => $type,
			'file_path'   => $file_path,
			'file_id'     => $file_id,
			'format'      => $format,
			'delimiter'   => $delimiter,
			'encoding'    => $encoding,
			'mapping'     => $mapping,
			'options'     => $options,
		];
	}

	/**
	 * Parse, transform and authorize the actual prepared rows.
	 *
	 * @param array $request Normalized request.
	 * @param int   $job_id  Optional job ID for importer logging.
	 * @return array|\WP_Error
	 */
	private function prepare_file( array $request, $job_id, $sample = false ) {
		$parser = Format_Factory::create( $request['format'] );
		if ( is_wp_error( $parser ) ) {
			return $parser;
		}
		if ( in_array( $request['format'], [ 'json', 'csv', 'xml', 'xlsx', 'ods' ], true ) && true === apply_filters( 'rsl_ie_import_stream_preparation', in_array( $request['import_type'], [ 'post', 'page', 'comment', 'media' ], true ), $request['import_type'] ) ) {
			if ( in_array( $request['format'], [ 'csv', 'xml', 'xlsx', 'ods' ], true ) ) {
				return $this->prepare_streamed_rows( $request, $job_id, $sample ); }
			$stream = @fopen( $request['file_path'], 'rb' );
			if ( $stream ) {
				do {
					$first = fgetc( $stream );
				} while ( false !== $first && false !== strpos( " \t\r\n", $first ) );
				fclose( $stream );
				if ( '[' === $first || '{' === $first ) {
					return $this->prepare_streamed_rows( $request, $job_id, $sample ); }
			}
		}
		$data = $parser->parse(
			$request['file_path'],
			[
				'delimiter'  => $request['delimiter'],
				'encoding'   => $request['encoding'] ?? 'auto',
				'has_header' => $request['options']['has_header'] ?? true,
			]
		);
		if ( is_wp_error( $data ) ) {
			return $data;
		}
		if ( ! is_array( $data ) ) {
			return new \WP_Error( 'rsl_ie_invalid_data', __( 'The import file did not contain a list of rows.', 'import-export-by-rockstarlab' ) );
		}
		return $this->prepare_rows( $request, $job_id, $data );
	}

	/**
	 * Comments of one WordPress export (WXR) item, as plain field => value lists.
	 *
	 * @param array $raw Source row.
	 * @return array<int,array<string,mixed>>
	 */
	public static function wxr_comments( array $raw ) {
		$list = [];
		if ( isset( $raw['wp:comment'] ) && is_string( $raw['wp:comment'] ) && '' !== $raw['wp:comment'] && '[' === $raw['wp:comment'][0] ) {
			$decoded = json_decode( $raw['wp:comment'], true );
			$list    = is_array( $decoded ) ? $decoded : [];
		} elseif ( isset( $raw['wp:comment/wp:comment_id'] ) ) {
			$one = [];
			foreach ( $raw as $key => $value ) {
				if ( 0 === strpos( (string) $key, 'wp:comment/' ) ) {
					$one[ substr( (string) $key, 11 ) ] = $value;
				}
			}
			$list = [ $one ];
		}
		$out = [];
		foreach ( array_slice( $list, 0, 5000 ) as $comment ) {
			if ( ! is_array( $comment ) || ! isset( $comment['wp:comment_id'] ) ) {
				continue;
			}
			$clean = [];
			foreach ( $comment as $key => $value ) {
				$name = 0 === strpos( (string) $key, 'wp:' ) ? substr( (string) $key, 3 ) : (string) $key;
				if ( 'commentmeta' === $name ) {
					continue; // Spam-filter and plugin bookkeeping; not carried.
				}
				if ( is_scalar( $value ) ) {
					$clean[ $name ] = (string) $value;
				}
			}
			$out[] = $clean;
		}
		return $out;
	}

	/**
	 * Create the categories, tags and terms a WordPress export lists, with their parents.
	 *
	 * A post row only names its terms, so a Posts import created every category at the top
	 * level. Once per job, for the taxonomies the mapping imports, missing terms are created
	 * parents first; existing terms are not changed.
	 *
	 * @param array  $request       Import request.
	 * @param int    $job_id        Job ID.
	 * @param object $importer      Importer.
	 * @param array  $prepared_data Prepared rows of this slice.
	 * @return void
	 */
	private function ensure_wxr_terms( array $request, $job_id, $importer, array $prepared_data ) {
		if ( 'xml' !== ( $request['format'] ?? '' ) || empty( $request['file_path'] ) || ! $importer instanceof \RockStarLab\ImportExport\Model\Import\Post_Importer ) {
			return;
		}
		$wxr = false;
		foreach ( $prepared_data as $row ) {
			if ( is_array( $row ) && ! empty( $row['_rsl_ie_source_post_type'] ) ) {
				$wxr = true;
				break;
			}
		}
		$key = 'rsl_ie_wxr_terms_' . absint( $job_id );
		if ( ! $wxr || ( $job_id && get_transient( $key ) ) ) {
			return;
		}
		$taxonomies = [];
		foreach ( (array) ( $request['mapping'] ?? [] ) as $entry ) {
			$target = is_array( $entry ) ? (string) ( $entry['target_field'] ?? '' ) : '';
			if ( 0 === strpos( $target, 'taxonomy_' ) && taxonomy_exists( substr( $target, 9 ) ) ) {
				$taxonomies[ substr( $target, 9 ) ] = true;
			}
		}
		if ( $job_id ) {
			set_transient( $key, 1, DAY_IN_SECONDS );
		}
		if ( ! $taxonomies ) {
			return;
		}
		$terms = \RockStarLab\ImportExport\Model\Format\XML_Format::wxr_terms( (string) $request['file_path'] );
		$todo  = [];
		foreach ( $terms as $term ) {
			if ( isset( $taxonomies[ $term['taxonomy'] ] ) && current_user_can( get_taxonomy( $term['taxonomy'] )->cap->manage_terms ) ) {
				$todo[] = $term;
			}
		}
		// Parents first: repeat while a pass creates something.
		for ( $pass = 0; $todo && $pass < 10; ++$pass ) {
			$left = [];
			foreach ( $todo as $term ) {
				if ( get_term_by( 'slug', $term['slug'], $term['taxonomy'] ) ) {
					continue;
				}
				$parent = 0;
				if ( '' !== $term['parent'] && is_taxonomy_hierarchical( $term['taxonomy'] ) ) {
					$found = get_term_by( 'slug', $term['parent'], $term['taxonomy'] );
					if ( ! $found ) {
						$left[] = $term;
						continue;
					}
					$parent = (int) $found->term_id;
				}
				wp_insert_term(
					wp_slash( '' !== $term['name'] ? $term['name'] : $term['slug'] ),
					$term['taxonomy'],
					[
						'slug'        => $term['slug'],
						'parent'      => $parent,
						'description' => wp_slash( $term['description'] ),
					]
				);
			}
			if ( count( $left ) === count( $todo ) ) {
				break; // Parents missing from the file.
			}
			$todo = $left;
		}
	}

	/**
	 * The column list wizard positions (source_index) refer to.
	 *
	 * For XML that is the upload preview's list (columns of many records, see
	 * File_Service::xml_columns()); other formats use the first row.
	 *
	 * @param array $request Import request.
	 * @param array $row     First row.
	 * @return string[]|\WP_Error
	 */
	private function mapping_columns( array $request, array $row ) {
		if ( 'xml' !== ( $request['format'] ?? '' ) || empty( $request['file_path'] ) ) {
			return array_keys( $row );
		}
		$needs = false;
		foreach ( (array) ( $request['mapping'] ?? [] ) as $entry ) {
			if ( is_array( $entry ) && isset( $entry['source_index'] ) ) {
				$needs = true;
				break;
			}
		}
		if ( ! $needs ) {
			return array_keys( $row );
		}
		try {
			$columns = File_Service::xml_columns(
				Format_Factory::create( 'xml' ),
				$request['file_path'],
				[
					'delimiter'  => $request['delimiter'] ?? '',
					'encoding'   => $request['encoding'] ?? 'auto',
					'has_header' => $request['options']['has_header'] ?? true,
				]
			);
		} catch ( \Throwable $error ) {
			return new \WP_Error( 'xml_parse_error', __( 'Invalid XML file', 'import-export-by-rockstarlab' ) );
		}
		return is_wp_error( $columns ) ? $columns : $columns[0];
	}

	/** Prepare supported streamed rows without retaining a complete raw dataset. */
	private function prepare_streamed_rows( array $request, $job_id, $sample = false ) {
		$result = [
			'data'          => [],
			'replaced_urls' => 0,
		];
		if ( $job_id ) {
			$result['stored_count'] = 0;
			$reset                  = Import_Row_Store::reset( $job_id );
			if ( is_wp_error( $reset ) ) {
				return $reset; }
		}
		if ( $sample ) {
			$result['total_count'] = 0; }
		$batch       = [];
		$batch_bytes = 0;
		$first       = true;
		try {
			$rows = 'json' === $request['format']
				? Format_Factory::create( 'json' )->rows( $request['file_path'] )
				: Format_Factory::create( $request['format'] )->rows(
					$request['file_path'],
					[
						'delimiter'  => $request['delimiter'],
						'encoding'   => $request['encoding'] ?? 'auto',
						'has_header' => $request['options']['has_header'] ?? true,
					]
				);
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					return File_Store::error( 'invalid_rows' ); }
				if ( $first ) {
					// Column indices refer to the first row of the file, not each batch.
					$keys = $this->mapping_columns( $request, $row );
					if ( is_wp_error( $keys ) ) {
						return $keys; }
					foreach ( $request['mapping'] as &$entry ) {
						if ( isset( $entry['source_index'] ) ) {
							$index = filter_var( $entry['source_index'], FILTER_VALIDATE_INT );
							if ( false === $index || ! isset( $keys[ $index ] ) ) {
								return File_Store::error( 'invalid_mapping' ); }
							$entry['source_field'] = (string) $keys[ $index ];
							unset( $entry['source_index'] );
						}
					}
					unset( $entry );
					$first = false;
				}
				$batch[] = $row;
				$encoded = wp_json_encode( $row );
				if ( false === $encoded ) {
					return File_Store::error( 'invalid_rows' ); }
				$batch_bytes += strlen( $encoded );
				unset( $encoded );
				if ( count( $batch ) === 100 || $batch_bytes >= 1048576 ) {
					$prepared = $this->prepare_rows( $request, $job_id, $batch );
					if ( is_wp_error( $prepared ) ) {
						return $prepared; }
					if ( $job_id ) {
						$saved = Import_Row_Store::append( $job_id, $result['stored_count'], $prepared['data'] );
						if ( is_wp_error( $saved ) ) {
							return $saved; }
						$result['stored_count'] += count( $prepared['data'] );
					} else {
						$index = 0;
						foreach ( $prepared['data'] as $item ) {
							$this->retain_validation_row( $result, $sample, $item, $index++ ); }
					}
					if ( $sample ) {
						$result['total_count'] += count( $prepared['data'] ); }
					$result['replaced_urls'] += $prepared['replaced_urls'];
					$batch                    = [];
					$batch_bytes              = 0;
				}
			}
			$prepared = $this->prepare_rows( $request, $job_id, $batch );
			if ( is_wp_error( $prepared ) ) {
				return $prepared; }
			if ( $job_id ) {
				$saved = Import_Row_Store::append( $job_id, $result['stored_count'], $prepared['data'] );
				if ( is_wp_error( $saved ) ) {
					return $saved; }
				$result['stored_count'] += count( $prepared['data'] );
			} else {
				$index = 0;
				foreach ( $prepared['data'] as $item ) {
					$this->retain_validation_row( $result, $sample, $item, $index++ ); }
			}
			if ( $sample ) {
				$result['total_count'] += count( $prepared['data'] ); }
			$result['replaced_urls'] += $prepared['replaced_urls'];
			return $result;
		} catch ( \Throwable $error ) {
			return File_Store::error( 'invalid_rows' );
		}
	}

	/** Detect resumable JSON arrays without consuming a container or changing object semantics. */
	private function is_json_array( $path ) {
		$stream = fopen( $path, 'rb' );
		if ( ! $stream ) {
			return false; }
		try {
			do {
				$char = fgetc( $stream );
			} while ( false !== $char && false !== strpos( " \t\r\n", $char ) );
			return '[' === $char;
		} finally {
			fclose( $stream ); }
	}

	/** Prepare one CSV/JSON-array slice; migration posts/pages/comments use their frozen seed maps. */
	private function prepare_resumable_slice( $job, array $request, array &$parameters ) {
		$state_key = $request['format'] . '_preparation';
		$state     = $parameters[ $state_key ] ?? null;
		if ( null === $state ) {
			$reset = Import_Row_Store::reset( (int) $job->id );
			if ( is_wp_error( $reset ) ) {
				return $reset; }
			// Indexed snapshots already verify their source before exposing stored rows.
			$indexed       = isset( $parameters['json_object_index'] ) || isset( $parameters['spreadsheet_index'] );
			$source_sha256 = $indexed ? null : hash_file( 'sha256', $request['file_path'] );
			if ( ! $indexed && ! is_string( $source_sha256 ) ) {
				return File_Store::error( 'source_changed', 409 );
			}
			$state = [
				'source_sha256' => $source_sha256,
				'cursor'        => null,
				'count'         => 0,
				'replaced_urls' => 0,
				'mapping'       => $request['mapping'],
				'done'          => false,
			];
			// Bind the fast digest only when the same request proved the SHA-256 digest.
			$fast = $indexed ? null : self::fast_digest( $request['file_path'] );
			if ( is_string( $fast ) && is_string( $source_sha256 ) && hash_equals( (string) ( $parameters['source_sha256'] ?? $source_sha256 ), $source_sha256 ) ) {
				$state['source_fast_digest'] = $fast;
			}
		}
		if ( ! empty( $state['done'] ) ) {
			return [
				'stored_count'  => $state['count'],
				'replaced_urls' => $state['replaced_urls'],
				'data'          => [],
			]; }
		$request['mapping'] = $state['mapping'];
		$batch              = [];
		$bytes              = 0;
		// Each slice is a separate request that boots WordPress, so 100-row slices made a
		// 100k-row file cost ~1,000 requests. The 1 MB byte budget still bounds memory.
		$slice_rows = max( 1, (int) apply_filters( 'rsl_ie_import_preparation_slice_rows', self::PREPARATION_SLICE_ROWS, $request ) );
		$done               = true;
		$cursor             = $state['cursor'];
		$reader             = ( isset( $parameters['json_object_index'] ) || isset( $parameters['spreadsheet_index'] ) ) ? Import_Object_Store::rows( (int) $job->id, ( $parameters['json_object_index'] ?? $parameters['spreadsheet_index'] )['count'], $cursor ) : ( 'json' === $request['format'] ? \RockStarLab\ImportExport\Model\Format\JSON_Row_Stream::rows( $request['file_path'], [], $cursor ) : Format_Factory::create( $request['format'] )->rows(
			$request['file_path'],
			[
				'delimiter'  => $request['delimiter'],
				'encoding'   => $request['encoding'] ?? 'auto',
				'has_header' => $request['options']['has_header'] ?? true,
			],
			$cursor
		) );
		try {
			foreach ( $reader as $row ) {
				if ( ! is_array( $row ) ) {
					return File_Store::error( 'invalid_rows' ); }
				if ( 'json' === $request['format'] ) {
					// Same columns as the preview: values inside nested objects as "author.name".
					$row = \RockStarLab\ImportExport\Model\Format\JSON_Format::with_nested_columns( $row );
				}
				if ( null === $state['cursor'] && ! $batch ) {
					$keys = $this->mapping_columns( $request, $row );
					if ( is_wp_error( $keys ) ) {
						return $keys; }
					foreach ( $request['mapping'] as &$entry ) {
						if ( isset( $entry['source_index'] ) ) {
							$index = filter_var( $entry['source_index'], FILTER_VALIDATE_INT );
							if ( false === $index || ! isset( $keys[ $index ] ) ) {
								return File_Store::error( 'invalid_mapping' ); }
							$entry['source_field'] = (string) $keys[ $index ];
							unset( $entry['source_index'] );
						}
					}
					unset( $entry );
				}
				$encoded = wp_json_encode( $row );
				if ( false === $encoded ) {
					return File_Store::error( 'invalid_rows' ); }
				$batch[] = $row;
				$bytes  += strlen( $encoded );
				if ( count( $batch ) >= $slice_rows || $bytes >= 1048576 ) {
					$done = false;
					break; }
			}
		} finally {
			unset( $reader ); }
		$prepared = $this->prepare_rows( $request, (int) $job->id, $batch );
		if ( is_wp_error( $prepared ) ) {
			return $prepared; }
		// Stat timestamps may be too coarse to detect an equal-length rewrite.
		// Legacy cursors retain their original stat-based checks.
		if ( $done && isset( $state['source_sha256'] ) && ( ! is_string( $state['source_sha256'] ) || ! hash_equals( $state['source_sha256'], (string) hash_file( 'sha256', $request['file_path'] ) ) ) ) {
			return File_Store::error( 'source_changed', 409 );
		}
		$next               = $parameters;
		$next[ $state_key ] = [
			'cursor'        => $cursor,
			'count'         => $state['count'] + count( $prepared['data'] ),
			'replaced_urls' => $state['replaced_urls'] + $prepared['replaced_urls'],
			'mapping'       => $request['mapping'],
			'done'          => $done,
		];
		if ( isset( $state['source_sha256'] ) ) {
			$next[ $state_key ]['source_sha256'] = $state['source_sha256'];
		}
		if ( isset( $state['source_fast_digest'] ) ) {
			$next[ $state_key ]['source_fast_digest'] = $state['source_fast_digest'];
		}
		$saved = Import_Row_Store::append_checkpoint( (int) $job->id, $state['count'], $prepared['data'], $job->parameters, $next );
		if ( is_wp_error( $saved ) ) {
			return $saved; }
		$parameters = $next;
		return [
			'preparing'     => ! $done,
			'stored_count'  => $next[ $state_key ]['count'],
			'replaced_urls' => $next[ $state_key ]['replaced_urls'],
			'data'          => [],
		];
	}

	/** Retain a witness row for audited all/any validators, preserving error row numbers. */
	private function retain_validation_row( array &$result, $sample, $item, $index ) {
		if ( is_callable( $sample ) ) {
			$ordinal = $result['total_count'] + $index;
			if ( $sample( $item, $ordinal ) ) {
				$result['data'] = [ $ordinal => $item ]; }
		} elseif ( ! $sample || ! $result['data'] ) {
			$result['data'][] = $item; }
	}

	/** Apply the existing importer and relationship policy to one raw row set. */
	private function prepare_rows( array $request, $job_id, array $data ) {
		$import_type = $request['import_type'];
		$options     = $request['options'];
		$importer    = Importer_Factory::get_importer( $import_type, $job_id );
		if ( is_wp_error( $importer ) ) {
			return $importer;
		}
		$importer->set_options( (array) $options + [ 'source_format' => (string) ( $request['format'] ?? '' ) ] );
		// Row filter: keep only the source rows whose columns meet the conditions.
		$row_filters   = (array) ( $options['row_filters'] ?? [] );
		$filtered_rows = 0;
		if ( $row_filters && $data ) {
			$kept          = Import_Row_Filter::apply( $data, $row_filters, $options['row_filters_match'] ?? 'all' );
			$filtered_rows = count( $data ) - count( $kept );
			$data          = $kept;
			if ( $filtered_rows && $job_id ) {
				/* translators: %d: number of rows */
				Job_Log::write( $job_id, 'info', sprintf( _n( '%d row did not match the row filter and was not imported.', '%d rows did not match the row filter and were not imported.', $filtered_rows, 'import-export-by-rockstarlab' ), $filtered_rows ) );
			}
			if ( ! $data ) {
				return [
					'data'          => [],
					'replaced_urls' => 0,
					'filtered_rows' => $filtered_rows,
				];
			}
		}
		// Resolve wizard column indices against parsed rows, including headerless CSV.
		$mapping = $request['mapping'];
		foreach ( $mapping as &$entry ) {
			if ( isset( $entry['source_index'] ) && isset( $data[0] ) ) {
				$index = filter_var( $entry['source_index'], FILTER_VALIDATE_INT );
				$keys  = $this->mapping_columns( $request, $data[0] );
				if ( is_wp_error( $keys ) ) {
					return $keys;
				}
				if ( false === $index || ! isset( $keys[ $index ] ) ) {
					return new \WP_Error( 'rsl_ie_invalid_mapping', __( 'The source column index is outside the file columns.', 'import-export-by-rockstarlab' ), [ 'status' => 400 ] );
				}
				$entry['source_field'] = (string) $keys[ $index ];
			}
		}
		unset( $entry );
		$replacement_count = 0;
		// Long spreadsheet values come split over "<column>__partN" columns.
		if ( in_array( $request['format'] ?? '', [ 'xlsx', 'ods', 'xls' ], true ) ) {
			$data = array_map(
				static function ( $row ) {
					return is_array( $row ) ? \RockStarLab\ImportExport\Model\Format\Spreadsheet_Format::join_long_values( $row ) : $row;
				},
				$data
			);
		}
		$prepared_data     = $importer->prepare( $data, $mapping );
		$prepared_data     = $this->sanitize_user_preference_meta_for_import( $import_type, $prepared_data );
		$prepared_data     = $this->apply_replace_links_rules_to_data( $prepared_data, $options, $replacement_count, $import_type );
		$prepared_data     = Migration_Import_Service::prepare_rows( $job_id, $request, $prepared_data );
		if ( is_wp_error( $prepared_data ) ) {
			return $prepared_data; }
		$permission = Access_Policy::check_content_type(
			$import_type,
			'import',
			[
				'options' => $options,
				'records' => $prepared_data,
			]
		);
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
			// Preserve source IDs for cross-site relationship fixups (e.g. post_parent).
			// The columns are the ones mapped to ID / post_parent / post_name, or carry those
			// names (WordPress exports call them wp:post_id, wp:post_parent and wp:post_name).
			$source_columns = [
				'ID'          => [ 'ID', 'wp:post_id' ],
				'post_parent' => [ 'post_parent', 'wp:post_parent' ],
				'post_name'   => [ 'post_name', 'wp:post_name' ],
			];
			foreach ( (array) $mapping as $entry ) {
				$target = is_array( $entry ) ? (string) ( $entry['target_field'] ?? '' ) : '';
				$source = is_array( $entry ) ? (string) ( $entry['source_field'] ?? '' ) : '';
				if ( '' !== $source && isset( $source_columns[ $target ] ) && ! in_array( $source, $source_columns[ $target ], true ) ) {
					array_unshift( $source_columns[ $target ], $source );
				}
			}
			$source_value = static function ( array $row, array $columns ) {
				foreach ( $columns as $column ) {
					if ( isset( $row[ $column ] ) && is_scalar( $row[ $column ] ) && '' !== trim( (string) $row[ $column ] ) ) {
						return trim( (string) $row[ $column ] );
					}
				}
				return null;
			};
		foreach ( $prepared_data as $row_index => &$prepared_row ) {
			$raw = isset( $data[ $row_index ] ) && is_array( $data[ $row_index ] ) ? $data[ $row_index ] : [];
			$id  = $source_value( $raw, $source_columns['ID'] );
			if ( null !== $id && ctype_digit( $id ) ) {
				$prepared_row['_rsl_ie_source_id'] = absint( $id );
			}
			$parent = $source_value( $raw, $source_columns['post_parent'] );
			if ( null !== $parent && ctype_digit( $parent ) ) {
				$prepared_row['_rsl_ie_source_parent_id'] = absint( $parent );
			}
			$name = $source_value( $raw, $source_columns['post_name'] );
			if ( null !== $name ) {
				$prepared_row['_rsl_ie_source_post_name'] = $name;
			}
			// A WordPress export mixes posts and media items: the importers skip the other kind.
			if ( isset( $raw['wp:post_type'] ) && is_scalar( $raw['wp:post_type'] ) && '' !== trim( (string) $raw['wp:post_type'] ) ) {
				$prepared_row['_rsl_ie_source_post_type'] = sanitize_key( (string) $raw['wp:post_type'] );
				$prepared_row['_rsl_ie_wxr_item']         = true;
			}
			// The item's comments (WordPress export): one entry as wp:comment/... columns, several
			// as a JSON list in wp:comment. Post imports create them with the post.
			// The author's email from the export's author list: finds the same person under
			// another login here (no user is created from it).
			if ( 'xml' === ( $request['format'] ?? '' ) && ! empty( $raw['dc:creator'] ) && is_scalar( $raw['dc:creator'] ) && ! empty( $request['file_path'] ) ) {
				$authors = \RockStarLab\ImportExport\Model\Format\XML_Format::wxr_authors( (string) $request['file_path'] );
				if ( isset( $authors[ trim( (string) $raw['dc:creator'] ) ] ) ) {
					$prepared_row['_rsl_ie_wxr_author_email'] = $authors[ trim( (string) $raw['dc:creator'] ) ];
				}
			}
			$wxr_comments = self::wxr_comments( $raw );
			if ( $wxr_comments ) {
				$prepared_row['_rsl_ie_wxr_comments'] = $wxr_comments;
			}
		}
			unset( $prepared_row );
		$this->ensure_wxr_terms( $request, $job_id, $importer, $prepared_data );

		// Preserve source IDs + portable post hints for cross-site comment relationships.
		if ( class_exists( \RockStarLab\ImportExport\Model\Import\Comment_Importer::class ) && $importer instanceof \RockStarLab\ImportExport\Model\Import\Comment_Importer ) {
			foreach ( $prepared_data as $row_index => &$prepared_row ) {
				// Ensure core date fields are available even if the UI mapping omits them.
				if ( isset( $data[ $row_index ]['comment_date'] ) ) {
					$prepared_row['comment_date'] = (string) $data[ $row_index ]['comment_date'];
				}
				if ( isset( $data[ $row_index ]['comment_date_gmt'] ) ) {
					$prepared_row['comment_date_gmt'] = (string) $data[ $row_index ]['comment_date_gmt'];
				}
				if ( isset( $data[ $row_index ]['comment_ID'] ) ) {
					$prepared_row['_rsl_ie_source_comment_id'] = absint( $data[ $row_index ]['comment_ID'] );
				}
				if ( isset( $data[ $row_index ]['comment_parent'] ) ) {
					$prepared_row['_rsl_ie_source_comment_parent_id'] = absint( $data[ $row_index ]['comment_parent'] );
				}
				if ( isset( $data[ $row_index ]['post_permalink'] ) ) {
					$prepared_row['_rsl_ie_source_post_permalink'] = (string) $data[ $row_index ]['post_permalink'];
				}
				if ( isset( $data[ $row_index ]['post_slug'] ) ) {
					$prepared_row['_rsl_ie_source_post_slug'] = (string) $data[ $row_index ]['post_slug'];
				}
				if ( isset( $data[ $row_index ]['post_type'] ) ) {
					$prepared_row['_rsl_ie_source_post_type'] = (string) $data[ $row_index ]['post_type'];
				}
			}
			unset( $prepared_row );
		}

		// Preserve source IDs + portable parent hints for cross-site term hierarchy fixups.
		if ( class_exists( \RockStarLab\ImportExport\Model\Import\Taxonomy_Term_Importer::class ) && $importer instanceof \RockStarLab\ImportExport\Model\Import\Taxonomy_Term_Importer ) {
			foreach ( $prepared_data as $row_index => &$prepared_row ) {
				if ( isset( $data[ $row_index ]['term_id'] ) ) {
					$prepared_row['_rsl_ie_source_term_id'] = absint( $data[ $row_index ]['term_id'] );
				}
				if ( isset( $data[ $row_index ]['parent'] ) ) {
					$prepared_row['_rsl_ie_source_parent_term_id'] = absint( $data[ $row_index ]['parent'] );
				}
				if ( isset( $data[ $row_index ]['parent_slug'] ) ) {
					$prepared_row['_rsl_ie_source_parent_slug'] = (string) $data[ $row_index ]['parent_slug'];
				}
				// A spreadsheet names the parent ("Accessories" or "accessories") instead of
				// giving a source term ID: keep it as the portable parent hint.
				$parent_cell = isset( $prepared_row['parent'] ) && is_scalar( $prepared_row['parent'] ) ? trim( (string) $prepared_row['parent'] ) : '';
				if ( '' !== $parent_cell && ! ctype_digit( $parent_cell ) && empty( $prepared_row['_rsl_ie_source_parent_slug'] ) ) {
					$prepared_row['_rsl_ie_source_parent_slug'] = $parent_cell;
					$prepared_row['parent_slug']                = $prepared_row['parent_slug'] ?? $parent_cell;
				}
				// Rows without a taxonomy column use the taxonomy chosen in the import options.
				if ( empty( $prepared_row['taxonomy'] ) && ! empty( $options['default_taxonomy'] ) && is_string( $options['default_taxonomy'] ) && taxonomy_exists( $options['default_taxonomy'] ) ) {
					$prepared_row['taxonomy'] = $options['default_taxonomy'];
				}
			}
			unset( $prepared_row );
		}
		$result = [
			'data'          => $prepared_data,
			'replaced_urls' => $replacement_count,
		];
		if ( $filtered_rows ) {
			// Only present when a row filter skipped rows: chunked and full preparation stay identical otherwise.
			$result['filtered_rows'] = $filtered_rows; }
		return $result;
	}

	/**
	 * Execute under the initiating identity while holding the shared job lock.
	 *
	 * @param object $job        Fresh job record.
	 * @param array  $parameters Stored job parameters.
	 * @return array|\WP_Error
	 */
	private function run_batch( $job, array $parameters ) {
		if ( ! empty( $parameters['migration_parent_id'] ) ) {
			if ( ! empty( $parameters['migration_inflight'] ) ) {
				return File_Store::error( 'migration_uncertain_row', 409 ); }
			Migration_Import_Service::restore_maps( $job->id, $parameters['migration_maps'] ?? [] );
		}
		$job_id    = (int) $job->id;
		$job_model = rsl_ie()->Model->job;
		$request   = $this->normalize_request( array_merge( $parameters, [ 'file_path' => $job->file_path ] ) );
		if ( is_wp_error( $request ) ) {
			return $request;
		}
		// Check content rather than timestamps before consuming another source slice.
		// Once prepared, the independent row snapshot has its own integrity checks.
		if ( ! isset( $parameters['prepared_data'] ) && ! isset( $parameters['prepared_rows_count'] ) && isset( $parameters['source_sha256'] ) ) {
			// Slices of a started stream compare a fast full-file digest bound next to the
			// SHA-256 one: sha256 of a 100 MB file costs ~0.9 s on every 100-row slice.
			$stream_state = $parameters[ ( $parameters['format'] ?? '' ) . '_preparation' ] ?? null;
			$fast         = is_array( $stream_state ) && is_string( $stream_state['source_fast_digest'] ?? null ) ? self::fast_digest( $request['file_path'] ) : null;
			if ( is_string( $fast ) ) {
				if ( ! hash_equals( $stream_state['source_fast_digest'], $fast ) ) {
					return File_Store::error( 'source_changed', 409 );
				}
			} elseif ( ! is_string( $parameters['source_sha256'] ) || ! hash_equals( $parameters['source_sha256'], (string) hash_file( 'sha256', $request['file_path'] ) ) ) {
				return File_Store::error( 'source_changed', 409 );
			}
		}
		$importer = Importer_Factory::get_importer( $request['import_type'], $job_id );
		if ( is_wp_error( $importer ) ) {
			return $importer;
		}
		$importer->set_options( (array) $request['options'] + [ 'source_format' => (string) ( $request['format'] ?? '' ) ] );
		if ( ! isset( $parameters['prepared_data'] ) && ! isset( $parameters['prepared_rows_count'] ) ) {
			$stream_allowed = true === apply_filters( 'rsl_ie_import_stream_preparation', in_array( $request['import_type'], [ 'post', 'page', 'comment', 'media' ], true ), $request['import_type'] );
			$sheet          = in_array( $request['format'], [ 'xlsx', 'ods' ], true );
			if ( isset( $parameters['spreadsheet_index'] ) && ( ! $stream_allowed || ! $sheet || $parameters['spreadsheet_index']['format'] !== $request['format'] ) ) {
				return File_Store::error( 'preparation_mode_changed', 409 );
			}
			if ( $sheet && $stream_allowed && ( ! isset( $parameters['spreadsheet_index'] ) || ( isset( $parameters['spreadsheet_index']['done'] ) && ! $parameters['spreadsheet_index']['done'] ) ) && ( isset( $parameters['spreadsheet_index'] ) || Import_Object_Store::supported() ) ) {
				$indexed = Import_Object_Store::stage_spreadsheet( $job, $request, $parameters );
				if ( is_wp_error( $indexed ) ) {
					return $indexed;
				}
				return array_merge(
					$this->snapshot( $job_model->find( $job_id ) ),
					[
						'preparing'     => true,
						'indexed_items' => $parameters['spreadsheet_index']['count'],
					]
				);
			}
			$object = 'json' === $request['format'] && ! $this->is_json_array( $request['file_path'] );
			if ( isset( $parameters['json_object_index'] ) && ( ! $stream_allowed || ! $object ) ) {
				return File_Store::error( 'preparation_mode_changed', 409 ); }
			if ( $object && $stream_allowed && ( isset( $parameters['json_object_index'] ) || Import_Object_Store::supported() ) ) {
				if ( empty( $parameters['json_object_index']['done'] ) ) {
					$indexed = Import_Object_Store::advance( $job, $request, $parameters );
					if ( is_wp_error( $indexed ) ) {
						return $indexed; }
					return array_merge(
						$this->snapshot( $job_model->find( $job_id ) ),
						[
							'preparing'     => true,
							'indexed_items' => $parameters['json_object_index']['count'],
						]
					);
				}
			}
			$resumable = ( in_array( $request['format'], [ 'csv', 'xml' ], true ) || isset( $parameters['spreadsheet_index'] ) || ( 'json' === $request['format'] && ( isset( $parameters['json_object_index'] ) || $this->is_json_array( $request['file_path'] ) ) ) ) && true === $stream_allowed;
			if ( ( isset( $parameters['csv_preparation'] ) || isset( $parameters['json_preparation'] ) || isset( $parameters['xml_preparation'] ) || isset( $parameters['xlsx_preparation'] ) || isset( $parameters['ods_preparation'] ) ) && ! $resumable ) {
				return File_Store::error( 'preparation_mode_changed', 409 ); }
			if ( $resumable && ( isset( $parameters['csv_preparation'] ) || isset( $parameters['json_preparation'] ) || isset( $parameters['xml_preparation'] ) || isset( $parameters['xlsx_preparation'] ) || isset( $parameters['ods_preparation'] ) || Import_Row_Store::supports_checkpoints() ) ) {
				$prepared = $this->prepare_resumable_slice( $job, $request, $parameters );
				if ( ! is_wp_error( $prepared ) && ! empty( $prepared['preparing'] ) ) {
					return array_merge(
						$this->snapshot( $job_model->find( $job_id ) ),
						[
							'preparing'      => true,
							'prepared_items' => $prepared['stored_count'],
						]
					);
				}
			} else {
				$prepared = $this->prepare_file( $request, $job_id );
				if ( isset( $parameters['source_sha256'] ) && ! hash_equals( $parameters['source_sha256'], (string) hash_file( 'sha256', $request['file_path'] ) ) ) {
					return File_Store::error( 'source_changed', 409 );
				}
			}
			if ( is_wp_error( $prepared ) ) {
				return $prepared;
			}
			if ( isset( $prepared['stored_count'] ) ) {
				$parameters['prepared_rows_count'] = $prepared['stored_count'];
			} else {
				$parameters['prepared_data'] = $prepared['data'];
			}
			$parameters['total_items']       = $parameters['prepared_rows_count'] ?? count( $prepared['data'] );
			$parameters['offset']            = 0;
			$parameters['cumulative_result'] = [
				'total'         => $parameters['total_items'],
				'success'       => 0,
				'skipped'       => 0,
				'failed'        => 0,
				'updated'       => 0,
				'created'       => 0,
				'replaced_urls' => $prepared['replaced_urls'],
				'errors'        => [],
				'items'         => [],
			];
			$saved                           = $this->checkpoint( $job_id, $parameters );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}
		$stored = isset( $parameters['prepared_rows_count'] );
		$offset = (int) $parameters['offset'];
		$batch  = $stored ? Import_Row_Store::read_batch( $job_id, $parameters['prepared_rows_count'], $offset, $request['options']['batch_size'] ) : array_slice( $parameters['prepared_data'], $offset, $request['options']['batch_size'] );
		if ( is_wp_error( $batch ) ) {
			return $batch; }
		$permission = Access_Policy::check_content_type(
			$request['import_type'],
			'import',
			[
				'options' => $request['options'],
				'records' => $stored ? $batch : $parameters['prepared_data'],
			]
		);
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		$fresh = $job_model->find( $job_id );
		if ( ! $fresh || ! in_array( $fresh->status, [ 'pending', 'processing' ], true ) ) {
			return $fresh ? $this->snapshot( $fresh ) : new \WP_Error( 'rsl_ie_import_not_found', __( 'Import job not found.', 'import-export-by-rockstarlab' ) );
		}
		if ( 'pending' === $fresh->status ) {
			// Conditional transition cannot overwrite a concurrent pause/cancel.
			global $wpdb;
			$updated = $wpdb->query( $wpdb->prepare( "UPDATE %i SET status = 'processing', started_at = COALESCE(started_at, %s) WHERE id = %d AND status = 'pending'", $wpdb->prefix . 'rsl_ie_jobs', current_time( 'mysql' ), $job_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Compare-and-set on the job's lifecycle state.
			if ( false === $updated ) {
				return new \WP_Error( 'rsl_ie_job_write_failed', __( 'Could not save import progress.', 'import-export-by-rockstarlab' ) );
			}
		}
		if ( 'database_table' === $request['import_type'] && empty( $parameters['target_table_ready'] ) && method_exists( $importer, 'ensure_target_table' ) ) {
			$result = $stored
				? $importer->ensure_target_table_from_store( $job_id, $parameters['prepared_rows_count'] )
				: $importer->ensure_target_table( $parameters['prepared_data'] );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			$parameters['target_table_ready'] = true;
			$saved                            = $this->checkpoint( $job_id, $parameters );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}
		if ( method_exists( $importer, 'prime_duplicate_lookup' ) ) {
			$importer->prime_duplicate_lookup( $batch );
		}
		$batch_started = microtime( true );
		$time_budget   = (float) apply_filters( 'rsl_ie_import_batch_time_budget', self::BATCH_TIME_BUDGET, $request );
		$slowest_row   = 0.0;
		$first_row     = true;
		foreach ( $batch as $index => $item ) {
			// Rows are checkpointed one by one, so stopping between rows is always safe. Do not
			// start a row that would likely end past the budget (rows with media can take seconds).
			$elapsed = microtime( true ) - $batch_started;
			if ( ! $first_row && $time_budget > 0 && $elapsed + $slowest_row >= $time_budget ) {
				break;
			}
			$first_row = false;
			$row_started = microtime( true );
			if ( 'processing' !== $this->current_status( $job_id ) ) {
				$fresh = $job_model->find( $job_id );
				return $fresh ? $this->snapshot( $fresh ) : new \WP_Error( 'rsl_ie_import_not_found', __( 'Import job not found.', 'import-export-by-rockstarlab' ) );
			}
			// Recheck permissions/license for each row; no role snapshot is trusted.
			if ( ! empty( $parameters['migration_parent_id'] ) && 'comment' === $request['import_type'] ) {
				$access = Access_Policy::check_object( 'edit_post', $item['comment_post_ID'] ?? 0 );
				if ( is_wp_error( $access ) ) {
					return $access; }
			}
			$permission = Access_Policy::check_content_type(
				$request['import_type'],
				'import',
				[
					'options'              => $request['options'],
					'records'              => [ $item ],
					'record_ids_are_local' => ! empty( $parameters['migration_parent_id'] ) && 'comment' === $request['import_type'],
				]
			);
			if ( is_wp_error( $permission ) ) {
				return $permission;
			}
			if ( ! empty( $parameters['migration_parent_id'] ) ) {
				$parameters['migration_inflight'] = true;
				$saved                            = $this->checkpoint( $job_id, $parameters );
				if ( is_wp_error( $saved ) ) {
					return $saved; }
			}
			$result = $importer->import_item( $item, $offset + $index );
			$slowest_row = max( $slowest_row, microtime( true ) - $row_started );
			if ( ! empty( $parameters['migration_parent_id'] ) ) {
				$parameters['migration_maps']     = Migration_Import_Service::capture_maps( $job_id, $parameters['migration_maps'] ?? [], $request, $item, $result );
				$parameters['migration_inflight'] = false;
			}
			$cumulative = &$parameters['cumulative_result'];
			$action     = is_wp_error( $result ) ? 'failed' : ( in_array( $result, [ 'skipped', 'updated' ], true ) ? $result : 'created' );
			++$cumulative[ $action ];
			if ( in_array( $action, [ 'created', 'updated' ], true ) ) {
				++$cumulative['success'];
			}
			$entry = [
				'row'       => $offset + $index + 1,
				'action'    => $action,
				'source_id' => absint( $item['_rsl_ie_source_id'] ?? 0 ),
				'target_id' => is_numeric( $result ) ? absint( $result ) : 0,
				'title'     => (string) ( $item['post_title'] ?? '' ),
			];
			if ( ! is_wp_error( $result ) ) {
				if ( in_array( $request['import_type'], [ 'post', 'page', 'custom_post_types', 'woo_product' ], true ) ) {
					$source = absint( $item['_rsl_ie_source_id'] ?? $item['ID'] ?? 0 );
					$map    = get_transient( 'rsl_ie_import_post_id_map_' . $job_id );
					$target = is_numeric( $result ) ? absint( $result ) : absint( $map[ $source ] ?? 0 );
					if ( $source && $target ) {
						$parameters['relationship_maps']['posts'][ $source ] = $target;
					}
				}
				if ( isset( $item['_rsl_ie_source_comment_id'] ) ) {
					$source = absint( $item['_rsl_ie_source_comment_id'] );
					$map    = get_transient( 'rsl_ie_import_comment_id_map_' . $job_id );
					if ( ! empty( $map[ $source ] ) ) {
						$parameters['relationship_maps']['comments'][ $source ] = (int) $map[ $source ];
					}
				} elseif ( isset( $item['_rsl_ie_source_term_id'], $item['taxonomy'] ) ) {
					$source   = absint( $item['_rsl_ie_source_term_id'] );
					$taxonomy = sanitize_key( $item['taxonomy'] );
					$map      = get_transient( 'rsl_ie_import_term_id_map_' . $job_id );
					if ( ! empty( $map[ $taxonomy ][ $source ] ) ) {
						$parameters['relationship_maps']['terms'][ $taxonomy ][ $source ] = (int) $map[ $taxonomy ][ $source ];
					}
				}
			}
			if ( is_wp_error( $result ) ) {
				$entry['message']       = $result->get_error_message();
				if ( count( $cumulative['errors'] ) < self::RESULT_ERRORS_LIMIT ) {
					// Row, field, reason and how to fix; the first rows also keep their values for the error CSV.
					$cumulative['errors'][] = \RockStarLab\ImportExport\Helper\Import_Row_Error::describe(
						$result,
						is_array( $item ) ? $item : [],
						$entry['row'],
						count( $cumulative['errors'] ) < \RockStarLab\ImportExport\Helper\Import_Row_Error::VALUE_ROWS
					);
				}
			}
			if ( method_exists( $importer, 'take_media_issues' ) ) {
				foreach ( $importer->take_media_issues() as $issue ) {
					$cumulative['media_errors'] = (int) ( $cumulative['media_errors'] ?? 0 ) + 1;
					if ( count( $cumulative['media_warnings'] ?? [] ) < 50 ) {
						$cumulative['media_warnings'][] = [
							'row'     => $entry['row'],
							'url'     => $issue['url'],
							'message' => $issue['message'],
						];
					}
				}
			}
			// Keep only the latest rows: the full list made every checkpoint O(rows imported).
			$cumulative['items'][] = $entry;
			if ( count( $cumulative['items'] ) > self::RESULT_ITEMS_LIMIT ) {
				$cumulative['items'] = array_slice( $cumulative['items'], -self::RESULT_ITEMS_LIMIT );
			}
			unset( $cumulative );
			if ( ! is_wp_error( $result ) && 'skipped' !== $result && $importer instanceof \RockStarLab\ImportExport\Model\Import\Comment_Importer ) {
				$this->preserve_imported_comment_dates( $result, $item );
			}
			// Save each completed row even if its hooks paused/cancelled the job.
			$parameters['offset'] = $offset + $index + 1;
			$saved                = $this->checkpoint( $job_id, $parameters );
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
			// A migration stops on a failed row, except a media file the source site no longer has:
			// that row is counted as failed and the rest of the package still imports.
			if ( ! empty( $parameters['migration_parent_id'] ) && is_wp_error( $result ) && ! self::is_missing_source_media( $request, $result ) ) {
				return $result; }
		}
		$fresh = $job_model->find( $job_id );
		if ( $fresh && 'processing' === $fresh->status && (int) $parameters['offset'] >= (int) $parameters['total_items'] ) {
			$permission = Access_Policy::check_content_type(
				$request['import_type'],
				'import',
				[
					'options' => $request['options'],
					'records' => $stored ? $batch : $parameters['prepared_data'],
				]
			);
			if ( is_wp_error( $permission ) ) {
				return $permission;
			}
			if ( empty( $parameters['migration_parent_id'] ) ) {
				// Recover only identities confirmed by this job, including skipped rows.
				$post_key = 'rsl_ie_import_post_id_map_' . $job_id;
				$posts    = ( $parameters['relationship_maps']['posts'] ?? [] ) + (array) get_transient( $post_key );
				if ( $posts ) {
					set_transient( $post_key, $posts, DAY_IN_SECONDS ); }
				$comment_key = 'rsl_ie_import_comment_id_map_' . $job_id;
				$term_key    = 'rsl_ie_import_term_id_map_' . $job_id;
				$comments    = (array) get_transient( $comment_key );
				$terms       = (array) get_transient( $term_key );
				$comments    = ( $parameters['relationship_maps']['comments'] ?? [] ) + $comments;
				foreach ( $parameters['relationship_maps']['terms'] ?? [] as $taxonomy => $confirmed ) {
					$terms[ $taxonomy ] = $confirmed + ( $terms[ $taxonomy ] ?? [] );
				}
				if ( $comments ) {
					set_transient( $comment_key, $comments, DAY_IN_SECONDS ); }
				if ( $terms ) {
					set_transient( $term_key, $terms, DAY_IN_SECONDS ); }
				if ( $stored ) {
					$cursor    = $parameters['finalization_cursor'] ?? [];
					$finalized = $this->finalize_stored_rows( $job_id, $parameters['prepared_rows_count'], $request, $cursor );
					if ( is_wp_error( $finalized ) ) {
						return $finalized; }
					if ( false === $finalized ) {
						$parameters['finalization_cursor'] = $cursor;
						$saved                             = $this->checkpoint( $job_id, $parameters );
						return is_wp_error( $saved ) ? $saved : $this->snapshot( $job_model->find( $job_id ) );
					}
				} else {
					$this->finalization_request = $request;
					$this->fix_post_parent_relationships( $job_id, $parameters['prepared_data'] );
					$this->fix_acf_media_relationships( $parameters['prepared_data'] );
					$this->fix_acf_post_reference_relationships( $parameters['prepared_data'] );
					$this->fix_comment_parent_relationships( $job_id, $parameters['prepared_data'] );
					$this->fix_term_parent_relationships( $job_id, $parameters['prepared_data'] );
				}
			}
			// ACF post references to rows that came later in the file exist only now.
			if ( $importer instanceof \RockStarLab\ImportExport\Model\Import\Post_Importer ) {
				$importer->resolve_pending_acf_references();
			}
			global $wpdb;
			$updated = $wpdb->query( $wpdb->prepare( "UPDATE %i SET status = 'completed', progress = 100, completed_at = %s WHERE id = %d AND status = 'processing'", $wpdb->prefix . 'rsl_ie_jobs', current_time( 'mysql' ), $job_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Preserve concurrent lifecycle changes.
			if ( false === $updated ) {
				return new \WP_Error( 'rsl_ie_job_write_failed', __( 'Could not save import progress.', 'import-export-by-rockstarlab' ) );
			}
		}
		$fresh = $job_model->find( $job_id );
		return $fresh ? $this->snapshot( $fresh ) : new \WP_Error( 'rsl_ie_import_not_found', __( 'Import job not found.', 'import-export-by-rockstarlab' ) );
	}

	/**
	 * Finalize a completed child once all parent maps have been collected.
	 *
	 * @param int        $id Child ID.
	 * @param array      $maps Parent-owned maps.
	 * @param array|null $cursor Parent-owned continuation cursor, when supplied.
	 * @return bool|\WP_Error
	 */
	public function finalize_migration( $id, array $maps, &$cursor = null ) {
		$job    = rsl_ie()->Model->job->find( $id );
		$params = $job ? json_decode( $job->parameters, true ) : [];
		if ( ! $job || 'completed' !== $job->status || ! Migration_Service::running( $params['migration_parent_id'] ?? 0 ) ) {
			return File_Store::error( 'migration_parent_required', 409 ); }
		$request = $this->normalize_request( $params );
		if ( is_wp_error( $request ) ) {
			return $request; }
		$this->finalization_request = $request;
		Migration_Import_Service::restore_maps( $id, $maps );
		if ( isset( $params['prepared_rows_count'] ) ) {
			if ( is_array( $cursor ) ) {
				return $this->finalize_stored_rows( $id, $params['prepared_rows_count'], $request, $cursor );
			}
			return $this->finalize_stored_rows( $id, $params['prepared_rows_count'], $request );
		}
		$data = $params['prepared_data'] ?? [];
		$this->fix_post_parent_relationships( $id, $data );
		$this->fix_acf_media_relationships( $data );
		$this->fix_acf_post_reference_relationships( $data );
		$this->fix_comment_parent_relationships( $id, $data );
		$this->fix_term_parent_relationships( $id, $data );
		return true;
	}


	/**
	 * Validate stored rows before repairing relationships.
	 *
	 * An array cursor yields after each slice. Callers that omit it keep
	 * the synchronous finalization contract.
	 * An expired map restarts validation before any further repairs.
	 *
	 * @return bool|\WP_Error False means another finalization tick is needed.
	 */
	private function finalize_stored_rows( $job_id, $count, array $request, &$cursor = null ) {
		$this->finalization_request = $request;
		$key                        = 'rsl_ie_import_post_id_map_' . $job_id;
		$map                        = get_transient( $key );
		$bounded                    = is_array( $cursor );
		if ( $bounded && ( ! $cursor || ! is_array( $map ) ) ) {
			$cursor = [
				'phase'  => 'validate',
				'offset' => 0,
			];
		}
		$rebuild = ! is_array( $map ) || ! $map;
		if ( $rebuild ) {
			$map = []; }
		for ( $offset = $bounded ? ( 'validate' === $cursor['phase'] ? $cursor['offset'] : $count ) : 0; $offset < $count; ) {
			$slice_start = $offset;
			$rows        = Import_Row_Store::read_batch( $job_id, $count, $offset, 100 );
			if ( is_wp_error( $rows ) ) {
				return $rows; }
			$offset += count( $rows );
			$access  = Access_Policy::check_content_type(
				$request['import_type'],
				'import',
				[
					'options' => $request['options'],
					'records' => $rows,
				]
			);
			if ( is_wp_error( $access ) ) {
				return $access; }
			if ( in_array( $request['import_type'], [ 'post', 'page', 'custom_post_types', 'woo_product' ], true ) ) {
				$missing = array_filter(
					$rows,
					static function ( $row ) use ( $map ) {
						return ! isset( $map[ (string) ( $row['_rsl_ie_source_id'] ?? $row['ID'] ?? 0 ) ] );
					}
				);
				if ( $missing ) {
					$map += $this->build_post_source_id_map_from_meta( $missing ); }
			}
			if ( $bounded ) {
				set_transient( $key, $map, DAY_IN_SECONDS );
				$cursor = $offset < $count ? [
					'phase'  => 'validate',
					'offset' => $offset,
				] : [
					'phase'  => 'repair',
					'offset' => 0,
				];
				if ( $slice_start > 0 || $offset < $count ) {
					return false; }
			}
		}
		if ( $map || $bounded ) {
			set_transient( $key, $map, DAY_IN_SECONDS ); }
		for ( $offset = $bounded ? $cursor['offset'] : 0; $offset < $count; ) {
			$rows = Import_Row_Store::read_batch( $job_id, $count, $offset, 100 );
			if ( is_wp_error( $rows ) ) {
				return $rows; }
			$offset += count( $rows );
			$access  = Access_Policy::check_content_type(
				$request['import_type'],
				'import',
				[
					'options' => $request['options'],
					'records' => $rows,
				]
			);
			if ( is_wp_error( $access ) ) {
				return $access; }
			foreach ( $rows as $index => $row ) {
				foreach ( [ 'fix_post_parent_relationships', 'fix_acf_media_relationships', 'fix_acf_post_reference_relationships', 'fix_comment_parent_relationships', 'fix_term_parent_relationships' ] as $repair ) {
					$fresh  = rsl_ie()->Model->job->find( $job_id );
					$parent = $fresh ? ( json_decode( $fresh->parameters, true )['migration_parent_id'] ?? 0 ) : 0;
					if ( ! $fresh || ( $parent ? ! Migration_Service::running( $parent ) : 'processing' !== $fresh->status ) ) {
						if ( $bounded ) {
							$cursor = [
								'phase'  => 'repair',
								'offset' => $offset - count( $rows ) + $index,
							];
						}
						return false;
					}
					if ( in_array( $repair, [ 'fix_acf_media_relationships', 'fix_acf_post_reference_relationships' ], true ) ) {
						$this->$repair( [ $row ] );
					} else {
						$this->$repair( $job_id, [ $row ], false );
					}
				}
			}
			if ( $bounded && $offset < $count ) {
				$cursor = [
					'phase'  => 'repair',
					'offset' => $offset,
				];
				return false;
			}
		}
		delete_transient( $key );
		delete_transient( 'rsl_ie_import_comment_id_map_' . $job_id );
		delete_transient( 'rsl_ie_import_term_id_map_' . $job_id );
		return true;
	}

	/**
	 * Fast full-file digest (xxh128, PHP 8.1+) for per-slice drift checks.
	 *
	 * @param string $path File path.
	 * @return string|null Null when the algorithm is unavailable or the file is unreadable.
	 */
	private static function fast_digest( $path ) {
		if ( ! in_array( 'xxh128', hash_algos(), true ) ) {
			return null;
		}
		$digest = hash_file( 'xxh128', $path );
		return is_string( $digest ) ? 'xxh128:' . $digest : null;
	}

	/**
	 * Read only the lifecycle status of a job (cheap per-row pause/cancel check).
	 *
	 * @param int $job_id Job ID.
	 * @return string Status, or empty string when the job is gone.
	 */
	private function current_status( $job_id ) {
		global $wpdb;
		return (string) $wpdb->get_var( $wpdb->prepare( 'SELECT status FROM %i WHERE id = %d', $wpdb->prefix . 'rsl_ie_jobs', $job_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Lifecycle state must be read uncached.
	}

	/**
	 * Persist counters and resume position without overwriting lifecycle state.
	 *
	 * @param int   $job_id     Job ID.
	 * @param array $parameters Prepared data and cumulative results.
	 * @return int|\WP_Error
	 */
	private function checkpoint( $job_id, array $parameters ) {
		$result = $parameters['cumulative_result'];
		$total  = (int) $parameters['total_items'];
		$offset = (int) $parameters['offset'];
		return rsl_ie()->Model->job->update(
			$job_id,
			[
				'parameters'      => wp_json_encode( $parameters ),
				'result'          => wp_json_encode( $result ),
				'total_items'     => $total,
				'processed_items' => $offset,
				'success_items'   => (int) $result['success'],
				'failed_items'    => (int) $result['failed'],
				'skipped_items'   => (int) $result['skipped'],
				'progress'        => $total > 0 ? min( 99, round( $offset / $total * 100 ) ) : 0,
			]
		);
	}

	/**
	 * Build the existing UI batch response from persisted progress.
	 *
	 * @param object $job Job record.
	 * @return array
	 */
	private function snapshot( $job ) {
		$result = json_decode( (string) $job->result, true );
		return [
			'completed' => ! in_array( $job->status, [ 'pending', 'processing' ], true ),
			'status'    => $job->status,
			'offset'    => (int) $job->processed_items,
			'progress'  => (float) $job->progress,
			'result'    => is_array( $result ) ? $result : [],
		];
	}

	/**
	 * Whether a failed row is a media file the source site answered with an error page (404 or HTML).
	 * Network failures are not included: when the source site is down, the migration should stop.
	 *
	 * @param array     $request Import request.
	 * @param \WP_Error $error   Row error.
	 * @return bool
	 */
	public static function is_missing_source_media( array $request, \WP_Error $error ) {
		// download_failed: the source site no longer has the file. upload_error: WordPress here
		// refused this one file (e.g. SVG or another type this site does not allow, size limit).
		return 'media' === ( $request['import_type'] ?? '' ) && in_array( $error->get_error_code(), array( 'download_failed', 'upload_error' ), true );
	}

	/**
	 * Record errors without erasing completed rows or resurrecting stopped jobs.
	 *
	 * @param int       $job_id Job ID.
	 * @param \WP_Error $error  Service failure.
	 * @return \WP_Error
	 */
	private function fail( $job_id, \WP_Error $error ) {
		$job = rsl_ie()->Model->job->find( $job_id );
		if ( $job && in_array( $job->status, [ 'pending', 'processing' ], true ) ) {
			$result               = json_decode( (string) $job->result, true );
			$result               = is_array( $result ) ? $result : [];
			$result['error']      = $error->get_error_message();
			$result['error_code'] = $error->get_error_code();
			Job_Log::write( $job_id, 'error', $error->get_error_message() );
			global $wpdb;
			$wpdb->query( $wpdb->prepare( "UPDATE %i SET status = 'failed', result = %s, completed_at = %s WHERE id = %d AND status IN ('pending', 'processing')", $wpdb->prefix . 'rsl_ie_jobs', wp_json_encode( $result ), current_time( 'mysql' ), $job_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Preserve concurrent lifecycle changes.
		}
		return $error;
	}

	/**
	 * Normalize import options for post-based import types.
	 *
	 * Some UI flows (e.g. importing Pages) do not always send a `post_type`
	 * option. When importing posts/pages via Post_Importer we should default
	 * to the selected content type to prevent importing Pages as Posts.
	 *
	 * @param string $import_type Importer type from the request/job.
	 * @param array  $options     Options array.
	 * @return array Normalized options.
	 */
	private function normalize_post_options( $import_type, $options ) {
		if ( ! is_array( $options ) ) {
			$options = [];
		}

		$import_type = strtolower( trim( (string) $import_type ) );

		$map = [
			'post'  => 'post',
			'posts' => 'post',
			'page'  => 'page',
			'pages' => 'page',
		];

		if ( empty( $options['post_type'] ) && isset( $map[ $import_type ] ) ) {
			$options['post_type'] = $map[ $import_type ];
		}

		return $options;
	}

	/**
	 * Apply import search/replace rules to prepared import data.
	 *
	 * @param array $data    Prepared import rows.
	 * @param array $options Import options.
	 * @param int   $replacement_count Number of replacements applied.
	 * @return array Prepared data with replacements applied.
	 */
	private function apply_replace_links_rules_to_data( $data, $options, &$replacement_count = 0, $import_type = '' ) {
		if ( ! is_array( $data ) ) {
			return $data;
		}

		$rules = $this->get_replace_links_rules( $options );
		if ( empty( $rules ) ) {
			return $data;
		}

		// Download inputs must still point to the source until the importer has fetched them.
		$source_fields = [ 'featured_image', 'featured_image_url', 'product_gallery', '_thumbnail_url' ];
		if ( 'media' === $import_type ) {
			$source_fields = array_merge( $source_fields, [ 'file', 'file_url', 'file_path', 'url', 'path' ] );
		}
		foreach ( $data as $index => $row ) {
			$data[ $index ] = $this->apply_replace_links_rules_to_value( $row, $rules, $replacement_count, $source_fields );
		}

		return $data;
	}

	/**
	 * Keep per-user editor/admin preferences from overwriting the destination admin UI.
	 *
	 * @param string $import_type Import type.
	 * @param array  $data        Prepared import rows.
	 * @return array Prepared data.
	 */
	private function sanitize_user_preference_meta_for_import( $import_type, $data ) {
		if ( ! in_array( strtolower( (string) $import_type ), [ 'user', 'users' ], true ) || empty( $data ) || ! is_array( $data ) ) {
			return $data;
		}

		foreach ( $data as &$row ) {
			if ( is_array( $row ) ) {
				$row = $this->remove_user_preference_meta_keys( $row );
			}
		}
		unset( $row );

		return $data;
	}

	/**
	 * Remove volatile WordPress/admin user preference keys from nested user payloads.
	 *
	 * @param array $value User import row or nested user meta.
	 * @return array Filtered payload.
	 */
	private function remove_user_preference_meta_keys( array $value ) {
		foreach ( array_keys( $value ) as $key ) {
			$normalized_key = is_string( $key ) ? ltrim( $key, '_' ) : '';
			if ( $this->is_user_preference_meta_key( $normalized_key ) ) {
				unset( $value[ $key ] );
				continue;
			}

			if ( is_array( $value[ $key ] ) ) {
				$value[ $key ] = $this->remove_user_preference_meta_keys( $value[ $key ] );
			}
		}

		return $value;
	}

	/**
	 * Check whether a user meta key is a volatile editor/admin preference.
	 *
	 * @param string $key User meta key.
	 * @return bool
	 */
	private function is_user_preference_meta_key( $key ) {
		$key = (string) $key;
		if ( '' === $key ) {
			return false;
		}

		$exact = [
			'rich_editing',
			'syntax_highlighting',
			'infinite_scrolling',
			'comment_shortcuts',
			'admin_color',
			'use_ssl',
			'show_admin_bar_front',
			'locale',
			'session_tokens',
			'dismissed_wp_pointers',
			'show_welcome_panel',
			'wp_user-settings',
			'wp_user-settings-time',
			'wp_persisted_preferences',
			'wp_media_library_mode',
			'wp_dashboard_quick_press_last_post_id',
			'wp_product_import_error_log',
		];

		if ( in_array( $key, $exact, true ) ) {
			return true;
		}

		foreach ( [ 'metaboxhidden_', 'closedpostboxes_', 'meta-box-order_', 'manageedit-', 'edit_', 'screen_layout_' ] as $prefix ) {
			if ( 0 === strpos( $key, $prefix ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalize import search/replace rules.
	 *
	 * @param array $options Import options.
	 * @return array Normalized rules.
	 */
	private function get_replace_links_rules( $options ) {
		if ( empty( $options['replace_links_rules'] ) || ! is_array( $options['replace_links_rules'] ) ) {
			return [];
		}

		if ( isset( $options['replace_links'] ) && ! filter_var( $options['replace_links'], FILTER_VALIDATE_BOOLEAN ) ) {
			return [];
		}

		$rules = [];
		foreach ( $options['replace_links_rules'] as $rule ) {
			if ( ! is_array( $rule ) ) {
				continue;
			}

			$search  = isset( $rule['search'] ) && is_scalar( $rule['search'] ) ? sanitize_text_field( (string) $rule['search'] ) : '';
			$replace = isset( $rule['replace'] ) && is_scalar( $rule['replace'] ) ? sanitize_text_field( (string) $rule['replace'] ) : '';

			if ( '' === $search || $search === $replace ) {
				continue;
			}

			$rules[] = [
				'search'  => $search,
				'replace' => $replace,
			];
		}

		return $rules;
	}

	/**
	 * Recursively apply import search/replace rules to strings and nested arrays.
	 *
	 * JSON strings are intentionally handled as plain strings so URLs inside
	 * encoded Elementor/SEO/ACF payloads can be rewritten without changing the
	 * original structure.
	 *
	 * @param mixed $value Value to update.
	 * @param array $rules Normalized replacement rules.
	 * @param int   $replacement_count Number of replacements applied.
	 * @return mixed Updated value.
	 */
	/**
	 * Whether a replace-links rule points URLs at this site.
	 *
	 * @param string $replace Replacement text.
	 * @return bool
	 */
	private static function replacement_targets_this_site( $replace ) {
		$host = wp_parse_url( (string) $replace, PHP_URL_HOST );
		if ( ! $host && preg_match( '~^//?([^/]+)~', (string) $replace, $m ) ) {
			$host = $m[1];
		}
		return $host && strtolower( (string) $host ) === strtolower( (string) wp_parse_url( home_url(), PHP_URL_HOST ) );
	}

	private function apply_replace_links_rules_to_value( $value, $rules, &$replacement_count = 0, array $source_fields = [] ) {
		if ( is_object( $value ) && $value instanceof \stdClass ) {
			return (object) $this->apply_replace_links_rules_to_value( (array) $value, $rules, $replacement_count, $source_fields );
		}
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $child_value ) {
				if ( in_array( $key, $source_fields, true ) ) {
					continue;
				}
				$value[ $key ] = $this->apply_replace_links_rules_to_value( $child_value, $rules, $replacement_count, $source_fields );
			}

			return $value;
		}

		if ( is_string( $value ) && '' !== $value ) {
			if ( is_serialized( $value ) ) {
				$unserialized = \RockStarLab\ImportExport\Helper\Serialized::maybe( $value );
				if ( false !== $unserialized || 'b:0;' === $value ) {
					return maybe_serialize( $this->apply_replace_links_rules_to_value( $unserialized, $rules, $replacement_count, $source_fields ) );
				}
			}

			// Variations and other structured export fields may be JSON-encoded.
			$decoded = json_decode( $value );
			if ( ( is_array( $decoded ) || $decoded instanceof \stdClass ) && $source_fields ) {
				return wp_json_encode( $this->apply_replace_links_rules_to_value( $decoded, $rules, $replacement_count, $source_fields ) );
			}

			foreach ( $rules as $rule ) {
				$variants = array(
					$rule['search']                 => $rule['replace'],
					trim( wp_json_encode( $rule['search'] ), '"' ) => trim( wp_json_encode( $rule['replace'] ), '"' ),
					str_replace( '/', '\\/', $rule['search'] ) => str_replace( '/', '\\/', $rule['replace'] ),
					rawurlencode( $rule['search'] ) => rawurlencode( $rule['replace'] ),
				);

				$to_this_site = self::replacement_targets_this_site( $rule['replace'] );
				foreach ( $variants as $search => $replace ) {
					if ( ! $to_this_site ) {
						$count              = 0;
						$value              = str_replace( $search, $replace, $value, $count );
						$replacement_count += $count;
						continue;
					}
					// Source uploads must keep their source address: the media importer downloads
					// them from there. Re-pointed to this site before the download they became
					// URLs of files that do not exist here and ACF images/files/galleries came out empty.
					$value = (string) preg_replace_callback(
						'~' . preg_quote( $search, '~' ) . '([^\s"\'<>()\]\[]*)~',
						static function ( $m ) use ( $replace, &$replacement_count ) {
							$tail = str_replace( [ '\\/', '%2F', '%2f' ], '/', $m[1] );
							if ( false !== strpos( $tail, 'wp-content/uploads/' ) ) {
								return $m[0];
							}
							++$replacement_count;
							return $replace . $m[1];
						},
						$value
					);
				}
			}
		}

		return $value;
	}

	/**
	 * Best-effort fix for cross-site post_parent IDs after a Post_Importer job.
	 *
	 * When exporting posts/pages, `post_parent` is an ID from the source site.
	 * During import into another site those numeric IDs usually point to the wrong
	 * objects. Post_Importer records a source->target ID map (per job) and we
	 * use it here to rewrite `post_parent` to the correct target IDs once all
	 * items have been created/updated.
	 *
	 * @param int   $job_id        Job ID.
	 * @param array $prepared_data Prepared items (includes `_rsl_ie_source_*` keys when available).
	 * @return void
	 */
	private function fix_post_parent_relationships( $job_id, $prepared_data, $clear_map = true ) {
		$job_id = absint( $job_id );
		if ( $job_id <= 0 || ! is_array( $prepared_data ) || empty( $prepared_data ) ) {
			return;
		}

		$key = 'rsl_ie_import_post_id_map_' . $job_id;
		$map = get_transient( $key );
		if ( ! is_array( $map ) || empty( $map ) ) {
			$map = $this->build_post_source_id_map_from_meta( $prepared_data );
			if ( empty( $map ) ) {
				return;
			}
		}

		foreach ( $prepared_data as $row ) {
			// A file without a parent column says nothing about the parent: keep the current one.
			// Treating it as "top level" detached every page of a re-imported file from its parent.
			if ( ! is_array( $row ) || ! array_key_exists( '_rsl_ie_source_parent_id', $row ) ) {
				continue;
			}
			$source_id     = isset( $row['_rsl_ie_source_id'] ) ? absint( $row['_rsl_ie_source_id'] ) : 0;
			$source_parent = absint( $row['_rsl_ie_source_parent_id'] );
			$target_id     = $source_id ? absint( $map[ (string) $source_id ] ?? 0 ) : 0;
			$target_parent = $source_parent ? absint( $map[ (string) $source_parent ] ?? 0 ) : 0;
			// The parent may come from an earlier import (a child imported on its own later).
			if ( $source_parent > 0 && $target_parent <= 0 ) {
				$target_parent = $this->find_imported_post_by_source_id( $source_parent );
				if ( $target_parent > 0 ) {
					$map[ (string) $source_parent ] = $target_parent;
				}
			}

			if ( $target_id <= 0 || ( $source_parent > 0 && $target_parent <= 0 ) ) {
				continue;
			}

			$post = get_post( $target_id );
			if ( ! $post || ! $this->can_finalize_post( $target_id ) || ( $target_parent > 0 && ! $this->can_finalize_post( $target_parent ) ) ) {
				continue;
			}

			if ( (int) $post->post_parent === $target_parent ) {
				continue;
			}

			wp_update_post(
				[
					'ID'          => $target_id,
					'post_parent' => $target_parent,
				]
			);
		}

		// Prevent stale maps affecting future jobs.
		if ( $clear_map ) {
			delete_transient( $key ); }
	}

	/**
	 * Re-save ACF post-reference fields with local IDs after a file import.
	 *
	 * @param array $prepared_data Prepared imported rows.
	 * @return void
	 */
	private function fix_acf_post_reference_relationships( $prepared_data ) {
		if ( empty( $prepared_data ) || ! is_array( $prepared_data ) || ! class_exists( '\RockStarLab\ImportExport\Helper\Content_Sync_Replacer' ) ) {
			return;
		}

		foreach ( $prepared_data as $row ) {
			$source_id = isset( $row['_rsl_ie_source_id'] ) ? absint( $row['_rsl_ie_source_id'] ) : absint( $row['ID'] ?? 0 );
			if ( $source_id <= 0 ) {
				continue;
			}

			$post_id = $this->find_imported_post_by_source_id( $source_id );
			if ( $post_id <= 0 ) {
				continue;
			}

			$raw_meta = get_post_meta( $post_id );
			if ( empty( $raw_meta ) || ! is_array( $raw_meta ) ) {
				continue;
			}

			$meta = [];
			foreach ( $raw_meta as $key => $values ) {
				if ( ! is_array( $values ) || ! array_key_exists( 0, $values ) ) {
					continue;
				}
				$meta[ $key ] = maybe_unserialize( $values[0] );
			}

			\RockStarLab\ImportExport\Helper\Content_Sync_Replacer::translate_acf_post_reference_fields_in_meta(
				$meta,
				$post_id,
				$source_id
			);
		}
	}

	/**
	 * Re-save ACF media fields with local attachment IDs after a file import.
	 *
	 * @param array $prepared_data Prepared imported rows.
	 * @return void
	 */
	private function fix_acf_media_relationships( $prepared_data ) {
		if ( empty( $prepared_data ) || ! is_array( $prepared_data ) || ! class_exists( '\RockStarLab\ImportExport\Helper\Content_Sync_Replacer' ) ) {
			return;
		}

		$image_map = $this->build_attachment_source_id_map_from_meta();
		if ( empty( $image_map ) ) {
			return;
		}

		foreach ( $prepared_data as $row ) {
			$source_id = isset( $row['_rsl_ie_source_id'] ) ? absint( $row['_rsl_ie_source_id'] ) : absint( $row['ID'] ?? 0 );
			if ( $source_id <= 0 ) {
				continue;
			}

			$post_id = $this->find_imported_post_by_source_id( $source_id );
			if ( $post_id <= 0 ) {
				continue;
			}

			$raw_meta = get_post_meta( $post_id );
			if ( empty( $raw_meta ) || ! is_array( $raw_meta ) ) {
				continue;
			}

			// Fields imported from portable acf_<name> columns already hold this site's IDs. Remapping
			// them again through the global "source ID => local ID" map turned a correct local ID into
			// an unrelated attachment whenever it matched a source ID from an earlier import.
			$portable = [];
			foreach ( array_keys( $row ) as $column ) {
				if ( is_string( $column ) && 0 === strpos( $column, 'acf_' ) && strlen( $column ) > 4 ) {
					$portable[] = substr( $column, 4 );
				}
			}

			$meta = [];
			foreach ( $raw_meta as $key => $values ) {
				if ( ! is_array( $values ) || ! array_key_exists( 0, $values ) ) {
					continue;
				}
				$plain = ltrim( (string) $key, '_' );
				foreach ( $portable as $name ) {
					if ( $plain === $name || 0 === strpos( $plain, $name . '_' ) ) {
						continue 2;
					}
				}
				// Only ACF fields (their "_name" reference is "field_..."). Elementor, SEO and other
				// meta have their own importers; this pass re-wrote _elementor_data with IDs from
				// the global map (a wrong image) and without slashes (broken JSON).
				$reference = $raw_meta[ '_' . $key ][0] ?? '';
				if ( '_' === substr( (string) $key, 0, 1 ) || ! is_string( $reference ) || 0 !== strpos( $reference, 'field_' ) ) {
					continue;
				}
				$meta[ $key ] = maybe_unserialize( $values[0] );
			}

			$updated_meta = \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::replace_in_meta_public(
				$meta,
				'',
				'',
				$image_map,
				[]
			);

			foreach ( $updated_meta as $key => $value ) {
				if ( ! array_key_exists( $key, $meta ) || $value === $meta[ $key ] ) {
					continue;
				}
				update_post_meta( $post_id, $key, wp_slash( $value ) );
			}
		}
	}

	/**
	 * Build source attachment ID => local attachment ID map from stored media import meta.
	 *
	 * @return array
	 */
	private function build_attachment_source_id_map_from_meta() {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT post_id, meta_value
			FROM {$wpdb->postmeta}
			WHERE meta_key IN ('_rsl_ie_original_attachment_id', '_rsl_ie_source_attachment_id', '_rsl_ie_source_id')
			AND meta_value REGEXP '^[0-9]+$'",
			ARRAY_A
		); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Exact attachment source map is needed for post-import ACF media repair.

		if ( empty( $rows ) ) {
			return [];
		}

		$map = [];
		foreach ( $rows as $row ) {
			$attachment_id = absint( $row['post_id'] ?? 0 );
			$source_id     = absint( $row['meta_value'] ?? 0 );
			if ( $attachment_id <= 0 || $source_id <= 0 || 'attachment' !== get_post_type( $attachment_id ) ) {
				continue;
			}
			$map[ $source_id ] = $attachment_id;
		}

		return $map;
	}

	/**
	 * Rebuild source post ID => target post ID map from stored import meta.
	 *
	 * @param array $prepared_data Prepared rows.
	 * @return array
	 */
	private function build_post_source_id_map_from_meta( array $prepared_data ) {
		$map = [];
		foreach ( $prepared_data as $row ) {
			$source_id = isset( $row['_rsl_ie_source_id'] ) ? absint( $row['_rsl_ie_source_id'] ) : absint( $row['ID'] ?? 0 );
			if ( $source_id <= 0 ) {
				continue;
			}

			$target_id = $this->find_imported_post_by_source_id( $source_id );
			if ( $target_id > 0 ) {
				$map[ (string) $source_id ] = $target_id;
			}
		}

		return $map;
	}

	/**
	 * Find imported post by any source ID meta key used by free/PRO importers.
	 *
	 * @param int $source_id Source-site post ID.
	 * @return int
	 */
	private function find_imported_post_by_source_id( $source_id ) {
		$source_id = absint( $source_id );
		if ( $source_id <= 0 ) {
			return 0;
		}

		$posts = get_posts(
			[
				'post_type'              => 'any',
				'post_status'            => 'any',
				'posts_per_page'         => -1,
				'fields'                 => 'ids',
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
				'meta_query'             => [ // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Source ID repair lookup after import jobs.
					'relation' => 'OR',
					[
						'key'   => '_rsl_ie_source_post_id',
						'value' => (string) $source_id,
					],
					[
						'key'   => '_rsl_ie_source_id',
						'value' => (string) $source_id,
					],
					[
						'key'   => '_rsl_ie_original_post_id',
						'value' => (string) $source_id,
					],
				],
			]
		);

		foreach ( $posts as $post_id ) {
			if ( $this->can_finalize_post( $post_id ) ) {
				return absint( $post_id );
			}
		}
		return 0;
	}

	/**
	 * Source-ID matches must still refer to permitted destination objects.
	 *
	 * @param int $post_id Resolved destination post.
	 * @return bool
	 */
	private function can_finalize_post( $post_id ) {
		$request = $this->finalization_request;
		if ( empty( $request ) || is_wp_error( Access_Policy::check_object( 'edit_post', $post_id ) ) ) {
			return false;
		}
		return ! is_wp_error(
			Access_Policy::check_content_type(
				$request['import_type'],
				'import',
				[
					'options'      => $request['options'],
					'selected_ids' => [ $post_id ],
				]
			)
		);
	}


	/**
	 * Preserve source comment dates after WordPress insert/update filters run.
	 *
	 * @param int|string $item_result Import result.
	 * @param array      $item        Prepared import item.
	 * @return void
	 */
	private function preserve_imported_comment_dates( $item_result, $item ) {
		global $wpdb;

		$comment_id = is_numeric( $item_result ) ? absint( $item_result ) : 0;
		if ( $comment_id <= 0 && ! empty( $item['_rsl_ie_source_comment_id'] ) ) {
			$comment_id = $this->find_imported_comment_id( $item['_rsl_ie_source_comment_id'] );
		}

		if ( $comment_id <= 0 ) {
			return;
		}

		$update  = [];
		$formats = [];
		if ( isset( $item['comment_date'] ) && '' !== (string) $item['comment_date'] ) {
			$update['comment_date'] = (string) $item['comment_date'];
			$formats[]              = '%s';
		}
		if ( isset( $item['comment_date_gmt'] ) && '' !== (string) $item['comment_date_gmt'] ) {
			$update['comment_date_gmt'] = (string) $item['comment_date_gmt'];
			$formats[]                  = '%s';
		}
		if ( empty( $update ) ) {
			return;
		}

		$wpdb->update(
			$wpdb->comments,
			$update,
			[ 'comment_ID' => $comment_id ],
			$formats,
			[ '%d' ]
		);

		clean_comment_cache( $comment_id );
	}

	/**
	 * Find an imported comment by its source ID without a direct or meta query.
	 *
	 * This is only a fallback when an importer does not return the new comment
	 * ID. IDs are read in bounded pages and metadata uses WordPress caches.
	 *
	 * @param int|string $source_id Source-site comment ID.
	 * @return int Local comment ID, or zero when not found.
	 */
	private function find_imported_comment_id( $source_id ) {
		$comment_ids = get_comments(
			[
				'fields'     => 'ids',
				'number'     => 1,
				'orderby'    => 'comment_ID',
				'order'      => 'DESC',
				'status'     => 'any',
				'meta_key'   => '_aie_source_comment_id',
				'meta_value' => (string) absint( $source_id ),
			]
		);
		return $comment_ids ? absint( $comment_ids[0] ) : 0;
	}

	/**
	 * Best-effort fix for cross-site comment_parent relationships after a Comment_Importer job.
	 *
	 * The CSV stores source-site comment IDs in `comment_parent`. We import comments with
	 * parent=0 and store a source->target comment ID map in a transient keyed by job_id.
	 * Once all comments are created, we rewrite comment_parent to the correct target IDs.
	 *
	 * @param int   $job_id        Job ID.
	 * @param array $prepared_data Prepared items.
	 * @return void
	 */
	private function fix_comment_parent_relationships( $job_id, $prepared_data, $clear_map = true ) {
		$job_id = absint( $job_id );
		if ( $job_id <= 0 || ! is_array( $prepared_data ) || empty( $prepared_data ) ) {
			return;
		}

		$key = 'rsl_ie_import_comment_id_map_' . $job_id;
		$map = get_transient( $key );
		if ( ! is_array( $map ) || empty( $map ) ) {
			return;
		}

		foreach ( $prepared_data as $row ) {
			// Same precedence as Comment_Importer::import_item(): a file can carry the source IDs
			// in the Source Comment ID / Source Parent Comment ID fields or in comment_ID / comment_parent.
			$source_id     = 0;
			$source_parent = 0;
			foreach ( [ '_rsl_ie_source_comment_id', '_aie_source_comment_id', 'source_comment_id', 'comment_ID' ] as $field ) {
				if ( isset( $row[ $field ] ) && '' !== (string) $row[ $field ] ) {
					$source_id = absint( $row[ $field ] );
					break;
				}
			}
			foreach ( [ '_rsl_ie_source_comment_parent_id', '_aie_source_comment_parent_id', 'source_comment_parent_id', 'comment_parent' ] as $field ) {
				if ( isset( $row[ $field ] ) && '' !== (string) $row[ $field ] ) {
					$source_parent = absint( $row[ $field ] );
					break;
				}
			}

			$target_id     = $source_id ? absint( $map[ (string) $source_id ] ?? 0 ) : 0;
			$target_parent = $source_parent ? absint( $map[ (string) $source_parent ] ?? 0 ) : 0;
			// The parent may come from an earlier import: find it by its stored source ID.
			if ( $target_id > 0 && $source_parent > 0 && $target_parent <= 0 ) {
				$found         = get_comments( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Targeted lookup by importer meta.
					[
						'number'     => 1,
						'fields'     => 'ids',
						'status'     => 'any',
						'meta_key'   => '_aie_source_comment_id', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Targeted lookup.
						'meta_value' => (string) $source_parent, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Targeted lookup.
						'post_id'    => (int) ( get_comment( $target_id )->comment_post_ID ?? 0 ),
					]
				);
				$target_parent = ! empty( $found[0] ) ? absint( $found[0] ) : 0;
			}

			if ( $target_id <= 0 || $source_parent <= 0 || $target_parent <= 0 ) {
				continue;
			}

			$comment = get_comment( $target_id );
			if ( ! $comment ) {
				continue;
			}

			if ( (int) $comment->comment_parent === $target_parent ) {
				continue;
			}

			\RockStarLab\ImportExport\Helper\Comment_Update::update(
				[
					'comment_ID'     => $target_id,
					'comment_parent' => $target_parent,
				]
			);
		}

		if ( $clear_map ) {
			delete_transient( $key ); }
	}

	/**
	 * Best-effort fix for cross-site term parent relationships after a Taxonomy_Term_Importer job.
	 *
	 * Terms are exported with source-site `term_id`/`parent` IDs. On import we store a source->target
	 * map in a transient keyed by job_id and then rewrite parents once all terms exist.
	 *
	 * @param int   $job_id        Job ID.
	 * @param array $prepared_data Prepared items.
	 * @return void
	 */
	private function fix_term_parent_relationships( $job_id, $prepared_data, $clear_map = true ) {
		$job_id = absint( $job_id );
		if ( $job_id <= 0 || ! is_array( $prepared_data ) || empty( $prepared_data ) ) {
			return;
		}

		$key = 'rsl_ie_import_term_id_map_' . $job_id;
		$map = get_transient( $key );
		$map = is_array( $map ) ? $map : [];

		foreach ( $prepared_data as $row ) {
			$taxonomy = isset( $row['taxonomy'] ) ? sanitize_key( (string) $row['taxonomy'] ) : '';
			if ( '' === $taxonomy ) {
				continue;
			}

			$tax_map = isset( $map[ $taxonomy ] ) && is_array( $map[ $taxonomy ] ) ? $map[ $taxonomy ] : [];

			$source_id     = isset( $row['_rsl_ie_source_term_id'] ) ? absint( $row['_rsl_ie_source_term_id'] ) : absint( $row['term_id'] ?? 0 );
			$source_parent = isset( $row['_rsl_ie_source_parent_term_id'] ) ? absint( $row['_rsl_ie_source_parent_term_id'] ) : absint( $row['parent'] ?? 0 );

			$target_id     = $source_id ? absint( $tax_map[ (string) $source_id ] ?? 0 ) : 0;
			$target_parent = $source_parent ? absint( $tax_map[ (string) $source_parent ] ?? 0 ) : 0;

			// Fallback: resolve parent by exported parent_slug when ID mapping is missing.
			$row_parent_slug = (string) ( $row['_rsl_ie_source_parent_slug'] ?? ( $row['parent_slug'] ?? '' ) );
			if ( $target_parent <= 0 && '' !== $row_parent_slug ) {
				$parent_slug = sanitize_title( $row_parent_slug );
				if ( '' !== $parent_slug ) {
					$parent_term = get_term_by( 'slug', $parent_slug, $taxonomy );
					if ( ! $parent_term || is_wp_error( $parent_term ) ) {
						$parent_term = get_term_by( 'name', $row_parent_slug, $taxonomy );
					}
					if ( $parent_term && ! is_wp_error( $parent_term ) ) {
						$target_parent = (int) $parent_term->term_id;
					}
				}
			}

			// Rows without a term ID (a spreadsheet) are found by their slug or name.
			if ( $target_id <= 0 && $target_parent > 0 && ( ! empty( $row['slug'] ) || ! empty( $row['name'] ) ) ) {
				$own = ! empty( $row['slug'] ) ? get_term_by( 'slug', sanitize_title( (string) $row['slug'] ), $taxonomy ) : false;
				if ( ( ! $own || is_wp_error( $own ) ) && ! empty( $row['name'] ) ) {
					$own = get_term_by( 'name', (string) $row['name'], $taxonomy );
				}
				$target_id = ( $own && ! is_wp_error( $own ) ) ? (int) $own->term_id : 0;
			}

			if ( $target_id <= 0 || $target_parent <= 0 || ( $source_parent <= 0 && '' === $row_parent_slug ) || $target_id === $target_parent ) {
				continue;
			}

			$current = get_term( $target_id, $taxonomy );
			if ( ! $current || is_wp_error( $current ) ) {
				continue;
			}

			if ( (int) $current->parent === $target_parent ) {
				continue;
			}

			$result = wp_update_term( $target_id, $taxonomy, [ 'parent' => $target_parent ] );
			if ( is_wp_error( $result ) ) {
				continue;
			}
		}

		if ( $clear_map ) {
			delete_transient( $key ); }
	}



	/**
	 * Resolve an import file only from plugin-managed upload directories.
	 *
	 * @param string $file_path Candidate file path.
	 * @param string $format    Requested parser format.
	 * @return string|\WP_Error
	 */
	private function validate_import_file_path( $file_path, $format ) {
		$format          = strtolower( (string) $format );
		$allowed_formats = array( 'csv', 'json', 'xml', 'xlsx', 'ods' );

		if ( ! in_array( $format, $allowed_formats, true ) ) {
			return new \WP_Error( 'invalid_format', __( 'Only CSV, JSON, XML, XLSX, and ODS import files are supported.', 'import-export-by-rockstarlab' ) );
		}

		$real_path = realpath( (string) $file_path );
		if ( false === $real_path || ! is_file( $real_path ) || strtolower( pathinfo( $real_path, PATHINFO_EXTENSION ) ) !== $format ) {
			return new \WP_Error( 'invalid_file_path', __( 'Invalid import file path.', 'import-export-by-rockstarlab' ) );
		}

		$uploads = wp_upload_dir();
		$allowed = array(
			realpath( trailingslashit( $uploads['basedir'] ) . 'rsl-ie-uploads' ),
			realpath( trailingslashit( $uploads['basedir'] ) . 'rsl-ie-imports' ),
		);

		foreach ( array_filter( $allowed ) as $directory ) {
			if ( 0 === strpos( $real_path, trailingslashit( $directory ) ) ) {
				return $real_path;
			}
		}

		return new \WP_Error( 'invalid_file_path', __( 'Import file must be inside the plugin upload directory.', 'import-export-by-rockstarlab' ) );
	}
}

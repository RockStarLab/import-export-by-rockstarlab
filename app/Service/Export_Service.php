<?php
/**
 * Shared export application service for HTTP, abilities and cron.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Execution_Context;
use RockStarLab\ImportExport\Model\Export\Exporter_Factory;
use RockStarLab\ImportExport\Model\Format\Format_Factory;

defined( 'ABSPATH' ) || exit;

/**
 * Uses one query contract and persisted initiating identity for every batch.
 */
class Export_Service {

	/**
	 * Count using the same normalized selection as preview and execution.
	 *
	 * @param array $input Export request.
	 * @return array|\WP_Error
	 */
	public function count( array $input ) {
		$request = Export_Request::normalize( $input );
		if ( is_wp_error( $request ) ) {
			return $request;
		}
		$count = Exporter_Factory::get_count( $request['export_type'], $request['options'] );
		return is_wp_error( $count ) ? $count : [ 'count' => (int) $count ];
	}

	/**
	 * Preview processed fields/functions, never a separate unfiltered query.
	 *
	 * @param array $input Export request.
	 * @return array|\WP_Error
	 */
	public function preview( array $input ) {
		$request = Export_Request::normalize( $input );
		if ( is_wp_error( $request ) ) {
			return $request;
		}
		$exporter = Exporter_Factory::get_exporter( $request['export_type'] );
		if ( is_wp_error( $exporter ) ) {
			return $exporter;
		}
		$result = $exporter->export(
			array_merge(
				$request['options'],
				[
					'limit'  => 10,
					'offset' => 0,
				]
			)
		);
		return is_wp_error( $result ) ? $result : [
			'preview' => $result['data'],
			'fields'  => $exporter->get_available_fields(),
		];
	}

	/**
	 * Persist only normalized query parameters and server-owned identity.
	 *
	 * @param array $input Export request.
	 * @param int   $migration_parent_id Internal parent, never read from request data.
	 * @return array|\WP_Error
	 */
	public function start( array $input, $migration_parent_id = 0 ) {
		if ( $migration_parent_id && ! Migration_Service::running( $migration_parent_id ) ) {
			return new \WP_Error( 'rsl_ie_migration_parent_required', 'Run the parent migration.', [ 'status' => 409 ] ); }
		$request = Export_Request::normalize( $input );
		if ( is_wp_error( $request ) ) {
			return $request;
		}
		$name = $request['options']['custom_export_file_name'] ?? '';
		if ( ! is_string( $name ) || '' === trim( $name ) ) {
			return new \WP_Error( 'rsl_ie_export_filename_required', __( 'Custom export file name is required.', 'import-export-by-rockstarlab' ), [ 'status' => 400 ] );
		}
		$context = Execution_Context::capture();
		if ( is_wp_error( $context ) ) {
			return $context;
		}
		$request['execution_context'] = $context->to_storage();
		if ( $migration_parent_id ) {
			$request['migration_parent_id'] = (int) $migration_parent_id; }
		$result = Job_Service::enqueue(
			[
				'type'        => 'export',
				'status'      => 'pending',
				'user_id'     => get_current_user_id(),
				'data_type'   => $request['export_type'],
				'file_format' => $request['format'],
				'parameters'  => wp_json_encode( $request ),
			],
			$input['request_id'] ?? ''
		);
		return $result;
	}

	/**
	 * Authenticated transport entry point.
	 *
	 * @param int $job_id Job ID.
	 * @return array|\WP_Error
	 */
	public function process_batch( $job_id ) {
		$permission = Access_Policy::check_management();
		return is_wp_error( $permission ) ? $permission : $this->process_background_batch( $job_id );
	}

	/**
	 * Trusted worker entry point; never register directly as a public ability.
	 *
	 * @param int $job_id Job ID from the current site's jobs table.
	 * @return array|\WP_Error
	 */
	public function process_background_batch( $job_id ) {
		$model = rsl_ie()->Model->job;
		$job   = $model->find( (int) $job_id );
		if ( ! $job || 'export' !== $job->type ) {
			return new \WP_Error( 'rsl_ie_export_not_found', __( 'Export job not found.', 'import-export-by-rockstarlab' ), [ 'status' => 404 ] );
		}
		$parent = json_decode( $job->parameters, true )['migration_parent_id'] ?? 0;
		if ( $parent && ! Migration_Service::running( $parent ) ) {
			return new \WP_Error( 'rsl_ie_migration_parent_required', 'Run the parent migration.', [ 'status' => 409 ] ); }
		$lock = Job_Lock::acquire( $job->id );
		if ( is_wp_error( $lock ) ) {
			return $this->fail( $job->id, $lock );
		}
		if ( false === $lock ) {
			return array_merge( $this->snapshot( $job ), [ 'locked' => true ] );
		}
		try {
			$job = $model->find( $job->id );
			if ( ! $job ) {
				return new \WP_Error( 'rsl_ie_export_not_found', __( 'Export job not found.', 'import-export-by-rockstarlab' ) );
			}
			if ( ! in_array( $job->status, [ 'pending', 'processing' ], true ) ) {
				return $this->snapshot( $job );
			}
			$parameters = json_decode( (string) $job->parameters, true );
			if ( ! is_array( $parameters ) ) {
				return $this->fail( $job->id, new \WP_Error( 'rsl_ie_invalid_job', __( 'Invalid export job parameters.', 'import-export-by-rockstarlab' ) ) );
			}
			$stored = $parameters['execution_context'] ?? [
				'user_id' => (int) $job->user_id,
				'blog_id' => get_current_blog_id(),
			];
			if ( ! is_array( $stored ) || (int) ( $stored['user_id'] ?? 0 ) !== (int) $job->user_id || (int) ( $stored['blog_id'] ?? 0 ) !== get_current_blog_id() ) {
				return $this->fail( $job->id, new \WP_Error( 'rsl_ie_invalid_context', __( 'The job execution context is invalid.', 'import-export-by-rockstarlab' ) ) );
			}
			$context = Execution_Context::from_storage( $stored['user_id'], $stored['blog_id'] );
			$result  = is_wp_error( $context ) ? $context : $context->run(
				function () use ( $job, $parameters ) {
					return $this->run_batch( $job, $parameters );
				}
			);
			return is_wp_error( $result ) ? $this->fail( $job->id, $result ) : $result;
		} catch ( \Throwable $error ) {
			return $this->fail( $job->id, new \WP_Error( 'rsl_ie_export_exception', __( 'Export stopped unexpectedly. Review the job before retrying.', 'import-export-by-rockstarlab' ) ) );
		} finally {
			Job_Lock::release( $lock );
		}
	}

	/**
	 * Execute one batch and checkpoint the exact committed spool length.
	 *
	 * @param object $job Job record.
	 * @param array  $parameters Stored query and spool position.
	 * @return array|\WP_Error
	 */
	private function run_batch( $job, array $parameters ) {
		$request = Export_Request::normalize( $parameters );
		if ( is_wp_error( $request ) ) {
			return $request;
		}
		$model  = rsl_ie()->Model->job;
		$job_id = (int) $job->id;
		if ( 'pending' === $job->status ) {
			global $wpdb;
			$changed = $wpdb->query( $wpdb->prepare( "UPDATE %i SET status = 'processing', started_at = COALESCE(started_at, %s) WHERE id = %d AND status = 'pending'", $wpdb->prefix . 'rsl_ie_jobs', current_time( 'mysql' ), $job_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Atomic lifecycle transition.
			if ( false === $changed ) {
				return $this->io_error();
			}
		}
		$offset   = (int) $job->processed_items;
		$exporter = Exporter_Factory::get_exporter( $request['export_type'], $job_id );
		if ( is_wp_error( $exporter ) ) {
			return $exporter;
		}
		$options = array_merge(
			$request['options'],
			[
				'limit'  => $request['options']['items_per_iteration'],
				'offset' => $offset,
			]
		);
		$fresh   = $model->find( $job_id );
		if ( ! $fresh || 'processing' !== $fresh->status ) {
			return $fresh ? $this->snapshot( $fresh ) : $this->io_error();
		}
		$result = $exporter->export( $options );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$data      = $result['data'];
		$stats     = $result['stats'];
		$processed = (int) ( $stats['processed'] ?? count( $data ) );
		$bytes     = $this->append_batch( $job_id, $data, $parameters['spool_bytes'] ?? null, $offset );
		if ( is_wp_error( $bytes ) ) {
			return $bytes;
		}
		$parameters['spool_bytes'] = $bytes;
		$cumulative                = $parameters['export_stats'] ?? [
			'exported' => 0,
			'failed'   => 0,
			'skipped'  => 0,
			'errors'   => [],
		];
		foreach ( [ 'exported', 'failed', 'skipped' ] as $key ) {
			$cumulative[ $key ] += (int) ( $stats[ $key ] ?? 0 );
		}
		foreach ( $stats['errors'] ?? [] as $error ) {
			$error['row']          += $offset;
			$cumulative['errors'][] = $error;
		}
		$parameters['export_stats'] = $cumulative;
		$new_offset                 = $offset + $processed;
		$total                      = max( (int) ( $stats['total'] ?? $job->total_items ), $new_offset );
		$saved                      = $model->update(
			$job_id,
			[
				'parameters'      => wp_json_encode( $parameters ),
				'result'          => wp_json_encode( $cumulative ),
				'processed_items' => $new_offset,
				'total_items'     => $total,
				'success_items'   => $cumulative['exported'],
				'failed_items'    => $cumulative['failed'],
				'progress'        => $total > 0 ? min( 99, round( $new_offset / $total * 100 ) ) : 0,
			]
		);
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$fresh = $model->find( $job_id );
		if ( $fresh && 'processing' === $fresh->status && ( $new_offset >= $total || 0 === $processed ) ) {
			// Entitlement and current object selection may change during exporter hooks.
			$request = Export_Request::normalize( $parameters );
			if ( is_wp_error( $request ) ) {
				return $request;
			}
			$all_data = $this->read_spool( $job_id, $bytes );
			if ( is_wp_error( $all_data ) ) {
				return $all_data;
			}
			$finished = $this->finalize( $job_id, $request, $all_data );
			if ( is_wp_error( $finished ) ) {
				return $finished;
			}
		}
		$fresh = $model->find( $job_id );
		return $fresh ? $this->snapshot( $fresh ) : $this->io_error();
	}

	/**
	 * Append a batch after truncating any uncommitted tail from an interrupted run.
	 *
	 * @param int      $job_id Job ID.
	 * @param array    $rows Exported rows.
	 * @param int|null $committed Last persisted byte length, null for legacy jobs.
	 * @param int      $offset Persisted source offset.
	 * @return int|\WP_Error
	 */
	private function append_batch( $job_id, array $rows, $committed, $offset ) {
		$path = $this->spool_path( $job_id );
		if ( ! wp_mkdir_p( dirname( $path ) ) || ( $offset > 0 && ! is_file( $path ) ) ) {
			return $this->io_error();
		}
		$handle = fopen( $path, 'c+b' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Random-access spool needs truncation to a committed byte offset.
		if ( ! $handle ) {
			return $this->io_error();
		}
		try {
			$size      = fstat( $handle )['size'];
			$committed = null === $committed ? ( $offset > 0 ? $size : 0 ) : (int) $committed;
			if ( $size < $committed || ! ftruncate( $handle, $committed ) || 0 !== fseek( $handle, $committed ) ) {
				return $this->io_error();
			}
			foreach ( $rows as $row ) {
				$json = wp_json_encode( $row );
				if ( false === $json ) {
					return $this->io_error();
				}
				$line = $json . "\n";
				if ( strlen( $line ) !== fwrite( $handle, $line ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions -- Detect partial spool writes.
					return $this->io_error();
				}
			}
			return fflush( $handle ) ? ftell( $handle ) : $this->io_error();
		} finally {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Close the random-access spool.
		}
	}

	/**
	 * Read exactly the committed spool, failing on missing or malformed data.
	 *
	 * @param int $job_id Job ID.
	 * @param int $bytes Committed length.
	 * @return array|\WP_Error
	 */
	private function read_spool( $job_id, $bytes ) {
		$path = $this->spool_path( $job_id );
		$raw  = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Local generated spool, never a caller-supplied path.
		if ( false === $raw || strlen( $raw ) !== $bytes ) {
			return $this->io_error();
		}
		$data = [];
		foreach ( explode( "\n", rtrim( $raw, "\n" ) ) as $line ) {
			if ( '' === $line ) {
				continue;
			}
			$row = json_decode( $line, true );
			if ( ! is_array( $row ) || JSON_ERROR_NONE !== json_last_error() ) {
				return $this->io_error();
			}
			$data[] = $row;
		}
		return $data;
	}

	/**
	 * Keep existing spools readable; new spools use private storage.
	 *
	 * @param int $job_id Job ID.
	 * @return string
	 * @throws \RuntimeException When private storage is unavailable.
	 */
	private function spool_path( $job_id ) {
		$uploads = wp_upload_dir();
		$legacy  = $uploads['basedir'] . '/rsl-ie/temp/export-' . (int) $job_id . '.jsonl';
		if ( is_file( $legacy ) ) {
			return $legacy; }
		$info = File_Service::export_target( $job_id, 'spool.jsonl' );
		if ( is_wp_error( $info ) ) {
			throw new \RuntimeException( 'Private export storage is unavailable.' ); }
		return $info['path'];
	}

	/**
	 * Generate the final file and publish its path only after successful writes.
	 *
	 * @param int   $job_id Job ID.
	 * @param array $request Normalized request.
	 * @param array $data Exported rows.
	 * @return true|\WP_Error
	 */
	private function finalize( $job_id, array $request, array $data ) {
		$format  = $request['format'];
		$options = $request['format_options'];
		$name    = $request['options']['custom_export_file_name'] ?? 'export-{job_id}';
		$name    = sanitize_file_name( wp_basename( str_replace( '{job_id}', (string) $job_id, $name ) ) );
		$name    = preg_replace( '/\.[^.]+$/', '', $name );
		$name    = $name ? $name : 'export-' . $job_id;
		// Tab-separated files get .tsv, the extension Google Merchant Center and spreadsheet apps expect.
		$extension = ( 'csv' === $format && "\t" === ( $options['csv_delimiter'] ?? ',' ) ) ? 'tsv' : $format;
		$info      = File_Service::export_target( $job_id, $name . '.' . $extension );
		if ( is_wp_error( $info ) ) {
			return $info;
		}
		if ( ! is_dir( $info['dir'] ) ) {
			return $this->io_error();
		}
		$keep = false;
		try {
			$formatter = Format_Factory::create( $format );
			if ( is_wp_error( $formatter ) ) {
				return $formatter;
			}
			$formatter_options = [];
			if ( 'csv' === $format || in_array( $format, [ 'xlsx', 'ods' ], true ) ) {
				$include                      = ! empty( $options[ 'csv' === $format ? 'csv_include_header' : 'spreadsheet_include_header' ] );
				$formatter_options['headers'] = $include ? null : false;
				if ( $include && ! $data ) {
					$fields = $request['options']['fields'] ?? [];
					if ( ! $fields || in_array( '*', $fields, true ) ) {
						$exporter = Exporter_Factory::get_exporter( $request['export_type'] );
						$fields   = is_wp_error( $exporter ) ? [] : $exporter->get_default_fields();
					}
					$formatter_options['headers'] = $fields;
				}
				if ( 'csv' === $format ) {
					$formatter_options['delimiter'] = $options['csv_delimiter'] ?? ',';
				}
			} else {
				$formatter_options['pretty_print'] = ! empty( $options[ $format . '_pretty_print' ] );
			}
			$headers = self::column_headers( $request['options']['column_headers'] ?? [] );
			if ( $headers ) {
				$data = self::rename_columns( $data, $headers );
				if ( is_array( $formatter_options['headers'] ?? null ) ) {
					$formatter_options['headers'] = array_map(
						static function ( $field ) use ( $headers ) {
							return $headers[ $field ] ?? $field;
						},
						$formatter_options['headers']
					);
				}
			}
			$result = $formatter->generate( $data, $info['path'], $formatter_options );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
			if ( false === $result || ! is_file( $info['path'] ) ) {
				return $this->io_error();
			}
			global $wpdb;
			$saved = $wpdb->query( $wpdb->prepare( "UPDATE %i SET status = 'completed', progress = 100, file_path = %s, file_size = %d, completed_at = %s WHERE id = %d AND status = 'processing'", $wpdb->prefix . 'rsl_ie_jobs', $info['path'], filesize( $info['path'] ), current_time( 'mysql' ), $job_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Publish only if no concurrent pause/cancel occurred.
			if ( false === $saved ) {
				return $this->io_error();
			}
			$keep = 1 === $saved;
			if ( $keep ) {
				wp_delete_file( $this->spool_path( $job_id ) );
			}
			return true;
		} finally {
			if ( ! $keep && is_file( $info['path'] ) ) {
				wp_delete_file( $info['path'] );
			}
		}
	}

	/**
	 * Return persisted progress with a stable response shape for the UI.
	 *
	 * @param object $job Job record.
	 * @return array
	 */
	/**
	 * Clean the column names chosen on the Fields step: field => name in the file.
	 *
	 * @param mixed $headers Requested names.
	 * @return array
	 */
	public static function column_headers( $headers ) {
		$clean = [];
		if ( ! is_array( $headers ) ) {
			return $clean;
		}
		foreach ( $headers as $field => $name ) {
			if ( ! is_string( $field ) || ! is_scalar( $name ) ) {
				continue;
			}
			$name = trim( wp_strip_all_tags( (string) $name ) );
			if ( '' !== $name && $name !== $field ) {
				$clean[ $field ] = $name;
			}
		}
		return $clean;
	}

	/**
	 * Rename row keys, keeping the column order.
	 *
	 * @param array $rows Exported rows.
	 * @param array $headers Field => column name.
	 * @return array
	 */
	public static function rename_columns( array $rows, array $headers ) {
		foreach ( $rows as $i => $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$renamed = [];
			foreach ( $row as $key => $value ) {
				$name = $headers[ $key ] ?? $key;
				// Two columns renamed to the same name would overwrite each other; keep the field name for the second one.
				$renamed[ array_key_exists( $name, $renamed ) ? $key : $name ] = $value;
			}
			$rows[ $i ] = $renamed;
		}
		return $rows;
	}

	private function snapshot( $job ) {
		return [
			'completed' => ! in_array( $job->status, [ 'pending', 'processing' ], true ),
			'status'    => $job->status,
			'processed' => (int) $job->processed_items,
			'total'     => (int) $job->total_items,
			'progress'  => (float) $job->progress,
		];
	}

	/**
	 * Fail without overwriting a concurrent lifecycle change.
	 *
	 * @param int       $job_id Job ID.
	 * @param \WP_Error $error Failure.
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
			$wpdb->query( $wpdb->prepare( "UPDATE %i SET status = 'failed', result = %s, completed_at = %s WHERE id = %d AND status IN ('pending', 'processing')", $wpdb->prefix . 'rsl_ie_jobs', wp_json_encode( $result ), current_time( 'mysql' ), $job_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Conditional lifecycle update.
		}
		return $error;
	}

	/**
	 * Report I/O failure without leaking filesystem paths or database errors.
	 *
	 * @return \WP_Error
	 */
	private function io_error() {
		return new \WP_Error( 'rsl_ie_export_write_failed', __( 'Could not read or save export data. The job was stopped.', 'import-export-by-rockstarlab' ), [ 'status' => 500 ] );
	}
}

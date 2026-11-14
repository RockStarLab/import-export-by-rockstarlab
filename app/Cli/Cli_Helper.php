<?php
/**
 * Shared helpers for the `wp rsl-ie` commands: abilities, files, waiting for jobs, output.
 *
 * The commands are a thin layer over the Abilities API, so the same permission checks,
 * validation and limits apply as in the admin screens and for AI agents.
 *
 * @package RockStarLab\ImportExport\Cli
 */

namespace RockStarLab\ImportExport\Cli;

use WP_CLI;

defined( 'ABSPATH' ) || exit;

/** Helpers used by every command class. */
class Cli_Helper {
	/** Exit code when a job finished but some rows failed. */
	const EXIT_ROWS_FAILED = 2;

	/** Job statuses that are still running. */
	const ACTIVE = [ 'pending', 'processing' ];

	/** Register the commands. */
	public static function register() {
		// The commands call the plugin's abilities in this process. They work even when
		// "AI agent access" is off, because only this `wp rsl-ie` process sees them: other
		// WP-CLI commands, such as an MCP server, still follow the setting.
		$runner = WP_CLI::get_runner();
		if ( isset( $runner->arguments[0] ) && 'rsl-ie' === $runner->arguments[0] ) {
			add_filter(
				'pre_option_rsl_ie_abilities_enabled',
				static function () {
					return '1';
				}
			);
		}
		WP_CLI::add_command( 'rsl-ie', Main_Command::class );
		WP_CLI::add_command( 'rsl-ie job', Job_Command::class );
		WP_CLI::add_command( 'rsl-ie schedule', Schedule_Command::class );
		WP_CLI::add_command( 'rsl-ie trigger', Trigger_Command::class );
	}

	/** Stop unless the current user may manage the plugin. */
	public static function require_admin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			WP_CLI::error( 'Run this command as an administrator, for example: wp rsl-ie ... --user=admin' );
		}
	}

	/**
	 * Find an ability by its full name or its short name (rsl-ie/ is tried first, then rsl-ie-pro/).
	 *
	 * @param string $name Ability name.
	 * @return \WP_Ability|null
	 */
	public static function find_ability( $name ) {
		if ( ! function_exists( 'wp_get_ability' ) ) {
			WP_CLI::error( 'The WordPress Abilities API is not available on this site.' );
		}
		$name = trim( (string) $name );
		if ( false !== strpos( $name, '/' ) ) {
			return 0 === strpos( $name, 'rsl-ie/' ) || 0 === strpos( $name, 'rsl-ie-pro/' ) ? wp_get_ability( $name ) : null;
		}
		foreach ( [ 'rsl-ie/', 'rsl-ie-pro/' ] as $prefix ) {
			$ability = wp_get_ability( $prefix . $name );
			if ( $ability ) {
				return $ability;
			}
		}
		return null;
	}

	/**
	 * Run an ability and stop with its message on error.
	 *
	 * @param string $name  Ability name.
	 * @param array  $input Input.
	 * @return mixed
	 */
	public static function call( $name, array $input = [] ) {
		$result = self::try_call( $name, $input );
		if ( is_wp_error( $result ) ) {
			self::fail( $result );
		}
		return $result;
	}

	/**
	 * Run an ability and return its result or WP_Error.
	 *
	 * @param string $name  Ability name.
	 * @param array  $input Input.
	 * @return mixed|\WP_Error
	 */
	public static function try_call( $name, array $input = [] ) {
		$ability = self::find_ability( $name );
		if ( ! $ability ) {
			return new \WP_Error( 'rsl_ie_cli_unknown_ability', sprintf( 'Unknown ability "%s". See: wp rsl-ie abilities', $name ) );
		}
		return $ability->execute( empty( $ability->get_input_schema() ) ? null : $input );
	}

	/**
	 * Stop with an error message and code.
	 *
	 * @param \WP_Error $error Error.
	 */
	public static function fail( \WP_Error $error ) {
		$message = $error->get_error_message();
		$data    = $error->get_error_data();
		if ( is_array( $data ) && ! empty( $data['details'] ) && is_array( $data['details'] ) ) {
			$message .= ' ' . wp_json_encode( $data['details'] );
		}
		WP_CLI::error( $message . ' [' . $error->get_error_code() . ']' );
	}

	/**
	 * Read JSON from a string, from @file or from - (STDIN).
	 *
	 * @param string $value Raw value.
	 * @param string $what  Name for messages.
	 * @return array
	 */
	public static function json_arg( $value, $what ) {
		$value = (string) $value;
		if ( '-' === $value ) {
			$value = (string) stream_get_contents( STDIN );
		} elseif ( 0 === strpos( $value, '@' ) ) {
			$path = substr( $value, 1 );
			if ( ! is_file( $path ) || ! is_readable( $path ) ) {
				WP_CLI::error( sprintf( 'Cannot read the %s file: %s', $what, $path ) );
			}
			$value = (string) file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Local file named by the operator.
		}
		$decoded = json_decode( $value, true );
		if ( ! is_array( $decoded ) ) {
			WP_CLI::error( sprintf( 'The %s is not valid JSON (an object or a list).', $what ) );
		}
		return $decoded;
	}

	/**
	 * Upload a local file through the same private upload flow the browser and agents use.
	 *
	 * @param string $path   Local file path.
	 * @param array  $parser Parser options (delimiter, has_header, encoding).
	 * @return array upload-complete result.
	 */
	public static function upload( $path, array $parser = [] ) {
		if ( ! is_file( $path ) || ! is_readable( $path ) ) {
			WP_CLI::error( 'Cannot read the file: ' . $path );
		}
		$size = (int) filesize( $path );
		if ( $size < 1 ) {
			WP_CLI::error( 'The file is empty: ' . $path );
		}
		$chunk = 1024 * 1024;
		$total = (int) ceil( $size / $chunk );
		$file  = self::call(
			'rsl-ie/upload-create',
			[
				'file_name'    => basename( $path ),
				'file_size'    => $size,
				'total_chunks' => $total,
			]
		);
		$handle = fopen( $path, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Local file named by the operator.
		if ( ! $handle ) {
			WP_CLI::error( 'Cannot open the file: ' . $path );
		}
		try {
			for ( $index = 0; $index < $total; $index++ ) {
				$bytes = fread( $handle, $chunk ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Local file named by the operator.
				$sent  = self::try_call(
					'rsl-ie/upload-chunk',
					[
						'file_id'     => $file['file_id'],
						'index'       => $index,
						'data_base64' => base64_encode( (string) $bytes ), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- Chunk transport format.
					]
				);
				if ( is_wp_error( $sent ) ) {
					self::try_call( 'rsl-ie/upload-abort', [ 'file_id' => $file['file_id'] ] );
					self::fail( $sent );
				}
			}
		} finally {
			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Local file named by the operator.
		}
		$input = [ 'file_id' => $file['file_id'] ];
		if ( $parser ) {
			$input['parser_options'] = $parser;
		}
		return self::call( 'rsl-ie/upload-complete', $input );
	}

	/**
	 * Parser options from command flags.
	 *
	 * @param array $assoc Flags.
	 * @return array
	 */
	public static function parser_options( array $assoc ) {
		$parser = [];
		if ( isset( $assoc['delimiter'] ) ) {
			$parser['delimiter'] = 'tab' === $assoc['delimiter'] ? "\t" : (string) $assoc['delimiter'];
		}
		if ( isset( $assoc['encoding'] ) ) {
			$parser['encoding'] = (string) $assoc['encoding'];
		}
		if ( isset( $assoc['no-header'] ) ) {
			$parser['has_header'] = false;
		}
		return $parser;
	}

	/**
	 * Run a job's batches in this process until it stops (no WP-Cron needed).
	 *
	 * @param int  $job_id  Job ID.
	 * @param int  $timeout Seconds, 0 = no limit.
	 * @param bool $quiet   No progress output.
	 * @return object|null Final job row, or null on timeout.
	 */
	public static function wait( $job_id, $timeout = 0, $quiet = false ) {
		$job_id  = (int) $job_id;
		$started = time();
		$user    = get_current_user_id();
		$last    = '';
		$idle    = 0;
		while ( true ) {
			$job = rsl_ie()->Model->job->find( $job_id );
			if ( ! $job ) {
				WP_CLI::error( sprintf( 'Job %d not found.', $job_id ) );
			}
			if ( ! in_array( $job->status, self::ACTIVE, true ) ) {
				wp_clear_scheduled_hook( 'rsl_ie_run_job', [ $job_id ] );
				return $job;
			}
			if ( $timeout > 0 && time() - $started >= $timeout ) {
				return null;
			}
			// Jobs run as their stored owner, the same way WP-Cron runs them.
			wp_set_current_user( 0 );
			try {
				\RockStarLab\ImportExport\Service\Job_Runner::run( $job_id, true );
			} finally {
				wp_set_current_user( $user );
			}
			$now   = rsl_ie()->Model->job->find( $job_id );
			$state = $now ? $now->status . ':' . (int) $now->processed_items . ':' . (string) $now->updated_at : '';
			if ( $state === $last ) {
				// Another process holds the job, or this job type runs elsewhere: wait a moment.
				++$idle;
				sleep( min( 5, $idle ) );
			} else {
				$idle = 0;
				if ( ! $quiet && $now && in_array( $now->status, self::ACTIVE, true ) ) {
					WP_CLI::log( sprintf( 'Job %d: %s, %d of %d', $job_id, $now->status, (int) $now->processed_items, (int) $now->total_items ) );
				}
			}
			$last = $state;
		}
	}

	/**
	 * Wait for a job if asked, then print its result and exit.
	 *
	 * @param int   $job_id Job ID.
	 * @param array $assoc  Flags: wait, timeout, format, porcelain.
	 * @param array $extra  Callback to add fields once finished: fn( array $result ): array.
	 */
	public static function wait_and_finish( $job_id, array $assoc, $extra = null ) {
		$format = $assoc['format'] ?? 'table';
		if ( ! empty( $assoc['porcelain'] ) && ( isset( $assoc['wait'] ) && ! $assoc['wait'] ) ) {
			WP_CLI::line( (string) $job_id );
			WP_CLI::halt( 0 );
		}
		if ( isset( $assoc['wait'] ) && ! $assoc['wait'] ) {
			if ( 'table' === $format ) {
				WP_CLI::success( sprintf( 'Job %d queued. WP-Cron will run it; check it with: wp rsl-ie job get %d', $job_id, $job_id ) );
				WP_CLI::halt( 0 );
			}
			WP_CLI::print_value( self::result( $job_id ), [ 'format' => $format ] );
			WP_CLI::halt( 0 );
		}
		$job = self::wait( $job_id, (int) ( $assoc['timeout'] ?? 0 ), 'table' !== $format || ! empty( $assoc['porcelain'] ) );
		if ( ! $job ) {
			WP_CLI::warning( sprintf( 'Job %d is still running after %d seconds. It continues with WP-Cron; check it with: wp rsl-ie job get %d', $job_id, (int) $assoc['timeout'], $job_id ) );
			WP_CLI::halt( 3 );
		}
		$result = self::result( $job_id );
		$more   = is_callable( $extra ) ? (array) call_user_func( $extra, $result ) : [];
		if ( ! empty( $assoc['porcelain'] ) ) {
			WP_CLI::line( (string) ( $more['file'] ?? $job_id ) );
			self::exit_for( $result );
		}
		self::finish( $result, $format, $more );
	}

	/**
	 * Job summary through the job-get-result ability.
	 *
	 * @param int $job_id Job ID.
	 * @return array
	 */
	public static function result( $job_id ) {
		$job = rsl_ie()->Model->job->find( (int) $job_id );
		if ( $job && \RockStarLab\ImportExport\Service\Sync_Job_Service::supports( $job ) ) {
			return self::sync_result( (int) $job_id );
		}
		return self::call(
			'rsl-ie/job-get-result',
			[
				'job_id' => (int) $job_id,
				'limit'  => 20,
			]
		);
	}

	/**
	 * Content Sync job summary (push, pull, comments, media) in the same shape as job-get-result.
	 *
	 * @param int $job_id Sync job ID.
	 * @return array
	 */
	public static function sync_result( $job_id ) {
		$status = self::call( 'rsl-ie/sync-job-status', [ 'job_id' => (int) $job_id ] );
		$result = is_array( $status['result'] ?? null ) ? $status['result'] : [];
		$counts = is_array( $result['result'] ?? null ) ? $result['result'] : $result;
		$errors = [];
		foreach ( (array) ( $counts['items'] ?? [] ) as $item ) {
			if ( is_array( $item ) && 'failed' === ( $item['status'] ?? '' ) ) {
				$errors[] = [
					'row'     => $item['source_id'] ?? '?',
					'field'   => '',
					'message' => (string) ( $item['error_code'] ?? 'failed' ),
				];
			}
		}
		if ( ! empty( $status['error_code'] ) && ! $errors ) {
			$errors[] = [
				'row'     => '-',
				'field'   => '',
				'message' => (string) $status['error_code'],
			];
		}
		return [
			'job_id'       => (int) $status['job_id'],
			'operation'    => (string) ( $status['operation'] ?? '' ),
			'status'       => (string) ( $status['status'] ?? '' ),
			'total'        => (int) ( $status['total'] ?? 0 ),
			'processed'    => (int) ( $status['processed'] ?? 0 ),
			'success'      => (int) ( $counts['created'] ?? 0 ) + (int) ( $counts['updated'] ?? 0 ),
			'failed'       => (int) ( $counts['failed'] ?? 0 ),
			'skipped'      => (int) ( $counts['skipped'] ?? 0 ),
			'errors'       => $errors,
			'errors_total' => count( $errors ),
		];
	}

	/**
	 * Print a job result and exit with the CI-friendly code.
	 *
	 * @param array  $result Job result.
	 * @param string $format table|json|yaml.
	 * @param array  $extra  Extra fields (file path).
	 */
	public static function finish( array $result, $format, array $extra = [] ) {
		$row = array_intersect_key( $result, array_flip( [ 'job_id', 'operation', 'content_type', 'job_name', 'status', 'total', 'processed', 'success', 'failed', 'skipped' ] ) ) + $extra;
		if ( 'table' === $format ) {
			foreach ( $row as $key => $value ) {
				WP_CLI::log( str_pad( $key . ':', 14 ) . ( is_scalar( $value ) ? $value : wp_json_encode( $value ) ) );
			}
			foreach ( array_slice( (array) ( $result['errors'] ?? [] ), 0, 10 ) as $error ) {
				WP_CLI::warning( sprintf( 'Row %s%s: %s', $error['row'] ?? '?', ! empty( $error['field'] ) ? ' (' . $error['field'] . ')' : '', $error['message'] ?? '' ) );
			}
			if ( (int) ( $result['errors_total'] ?? 0 ) > 10 ) {
				WP_CLI::log( sprintf( '%d failed rows in total. See: wp rsl-ie job errors %d', (int) $result['errors_total'], (int) $result['job_id'] ) );
			}
		} else {
			$row['errors'] = $result['errors'] ?? [];
			WP_CLI::print_value( $row, [ 'format' => $format ] );
		}
		self::exit_for( $result );
	}

	/**
	 * Exit 1 when the job failed or was cancelled, 2 when rows failed, otherwise 0.
	 *
	 * @param array $result Job result.
	 */
	public static function exit_for( array $result ) {
		$status = $result['status'] ?? '';
		if ( in_array( $status, [ 'failed', 'cancelled' ], true ) ) {
			WP_CLI::halt( 1 );
		}
		if ( (int) ( $result['failed'] ?? 0 ) > 0 ) {
			WP_CLI::halt( self::EXIT_ROWS_FAILED );
		}
		WP_CLI::halt( 0 );
	}

	/**
	 * Request ID for one CLI call (abilities use it to ignore accidental repeats).
	 *
	 * @param string $what Operation.
	 * @return string
	 */
	public static function request_id( $what ) {
		return 'cli-' . $what . '-' . gmdate( 'YmdHis' ) . '-' . wp_generate_password( 8, false, false );
	}

	/**
	 * Resolve a content type to the ability namespace and the type name the abilities expect.
	 * Short WooCommerce names (product, order...) map to the PRO woo_* types.
	 *
	 * @param string $type   Type from the command line.
	 * @param bool   $export Export (true) or import (false).
	 * @return array [ namespace prefix, type ]
	 */
	public static function resolve_type( $type, $export ) {
		$type  = strtolower( trim( (string) $type ) );
		$free  = $export ? [ 'post', 'page', 'comment', 'media', 'urls', 'form_entries' ] : [ 'post', 'page', 'comment', 'media' ];
		$alias = [
			'product'   => 'woo_product',
			'order'     => 'woo_order',
			'coupon'    => 'woo_coupon',
			'customer'  => 'woo_customer',
			'shipping-zone' => 'woo_shipping_zone',
			'shipping_zone' => 'woo_shipping_zone',
			'review'    => 'woo_review',
			'refund'    => 'woo_refund',
			'attribute' => 'woo_attribute',
			'cpt'       => 'custom_post_types',
			'users'     => 'user',
			'menus'     => 'menu',
		];
		if ( in_array( $type, $free, true ) ) {
			return [ 'rsl-ie/', $type ];
		}
		$type = $alias[ $type ] ?? $type;
		if ( ! self::find_ability( 'rsl-ie-pro/' . ( $export ? 'export-start' : 'import-start' ) ) ) {
			WP_CLI::error( sprintf( '"%s" needs the PRO plugin. The free plugin handles: %s.', $type, implode( ', ', $free ) ) );
		}
		return [ 'rsl-ie-pro/', $type ];
	}

	/**
	 * Add --post-type, --taxonomy and --table to the options.
	 *
	 * @param array $options Options.
	 * @param array $assoc   Flags.
	 * @return array
	 */
	public static function target_options( array $options, array $assoc ) {
		$keys = [
			'post-type' => 'post_type',
			'taxonomy'  => 'taxonomy',
			'table'     => 'table_name',
		];
		foreach ( $keys as $flag => $key ) {
			if ( isset( $assoc[ $flag ] ) ) {
				$options[ $key ] = (string) $assoc[ $flag ];
			}
		}
		return $options;
	}

	/**
	 * Comma list argument to array.
	 *
	 * @param string $value Value.
	 * @return array
	 */
	public static function csv_list( $value ) {
		return array_values( array_filter( array_map( 'trim', explode( ',', (string) $value ) ), 'strlen' ) );
	}

	/**
	 * Rows for a table: keep the given columns, encode nested values.
	 *
	 * @param array $rows   Rows.
	 * @param array $fields Columns.
	 * @return array
	 */
	public static function rows( array $rows, array $fields ) {
		$out = [];
		foreach ( $rows as $row ) {
			$line = [];
			foreach ( $fields as $field ) {
				$value = $row[ $field ] ?? '';
				if ( is_bool( $value ) ) {
					$value = $value ? 'yes' : 'no';
				}
				$line[ $field ] = is_scalar( $value ) || null === $value ? $value : wp_json_encode( $value );
			}
			$out[] = $line;
		}
		return $out;
	}
}

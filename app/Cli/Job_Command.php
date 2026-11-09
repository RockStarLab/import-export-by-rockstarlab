<?php
/**
 * `wp rsl-ie job`: read, wait for, repeat and cancel jobs.
 *
 * @package RockStarLab\ImportExport\Cli
 */

namespace RockStarLab\ImportExport\Cli;

use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Read, wait for, repeat and cancel Import Export jobs.
 *
 * ## EXAMPLES
 *
 *     $ wp rsl-ie job get 42 --user=admin
 *     $ wp rsl-ie job wait 42 --user=admin
 *     $ wp rsl-ie job errors 42 --csv=failed-rows.csv --user=admin
 */
class Job_Command {

	/**
	 * Show the status and counts of a job.
	 *
	 * Exit code 0 when the job is done without failed rows, 1 when it failed, 2 when rows failed.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Job ID.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * @param array $args  Positional arguments.
	 * @param array $assoc Flags.
	 */
	public function get( $args, $assoc ) {
		Cli_Helper::require_admin();
		$result = Cli_Helper::result( (int) $args[0] );
		if ( in_array( $result['status'] ?? '', Cli_Helper::ACTIVE, true ) || 'paused' === ( $result['status'] ?? '' ) ) {
			$format = $assoc['format'] ?? 'table';
			'table' === $format ? WP_CLI::log( sprintf( 'Job %d: %s, %d of %d', (int) $result['job_id'], $result['status'], (int) $result['processed'], (int) $result['total'] ) ) : WP_CLI::print_value( $result, [ 'format' => $format ] );
			return;
		}
		Cli_Helper::finish( $result, $assoc['format'] ?? 'table' );
	}

	/**
	 * Run a pending job here until it finishes, without WP-Cron.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Job ID.
	 *
	 * [--timeout=<seconds>]
	 * : Stop waiting after this many seconds; the job continues with WP-Cron.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * @param array $args  Positional arguments.
	 * @param array $assoc Flags.
	 */
	public function wait( $args, $assoc ) {
		Cli_Helper::require_admin();
		$id = (int) $args[0];
		Cli_Helper::result( $id ); // Checks that the job exists and may be read.
		Cli_Helper::wait_and_finish( $id, $assoc );
	}

	/**
	 * Show the failed rows of an import: row, field, error and how to fix it.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Job ID.
	 *
	 * [--csv=<path>]
	 * : Save the failed rows with their values as a CSV file, ready to fix and import again.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * @param array $args  Positional arguments.
	 * @param array $assoc Flags.
	 */
	public function errors( $args, $assoc ) {
		Cli_Helper::require_admin();
		$result = Cli_Helper::call(
			'rsl-ie/job-errors',
			[
				'job_id' => (int) $args[0],
				'limit'  => 500,
				'csv'    => isset( $assoc['csv'] ),
			]
		);
		if ( isset( $assoc['csv'] ) ) {
			if ( empty( $result['csv'] ) ) {
				WP_CLI::warning( 'The job has no failed rows to save.' );
			} elseif ( false === file_put_contents( (string) $assoc['csv'], (string) $result['csv'] ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions -- Local file named by the operator.
				WP_CLI::error( 'Cannot write the file: ' . $assoc['csv'] );
			} else {
				WP_CLI::success( 'Failed rows saved to ' . $assoc['csv'] );
			}
		}
		$rows = (array) ( $result['errors'] ?? [] );
		if ( ! $rows && 'table' === ( $assoc['format'] ?? 'table' ) ) {
			WP_CLI::success( 'No failed rows.' );
			return;
		}
		WP_CLI\Utils\format_items( $assoc['format'] ?? 'table', Cli_Helper::rows( $rows, [ 'row', 'field', 'message', 'hint' ] ), [ 'row', 'field', 'message', 'hint' ] );
	}

	/**
	 * Start a new job with the same settings and file as a finished job.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : ID of the finished job to repeat.
	 *
	 * [--[no-]wait]
	 * : Run the new job here until it finishes (default), or leave it to WP-Cron.
	 * ---
	 * default: true
	 * ---
	 *
	 * [--timeout=<seconds>]
	 * : Stop waiting after this many seconds; the job continues with WP-Cron.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * [--porcelain]
	 * : Print only the new job ID.
	 *
	 * @param array $args  Positional arguments.
	 * @param array $assoc Flags.
	 */
	public function rerun( $args, $assoc ) {
		Cli_Helper::require_admin();
		$started = Cli_Helper::call(
			'rsl-ie/job-restart',
			[
				'job_id'     => (int) $args[0],
				'request_id' => Cli_Helper::request_id( 'rerun-' . (int) $args[0] ),
			]
		);
		$new     = (int) ( $started['job_id'] ?? 0 );
		if ( ! $new ) {
			WP_CLI::error( 'The job could not be started again.' );
		}
		if ( 'table' === ( $assoc['format'] ?? 'table' ) && empty( $assoc['porcelain'] ) ) {
			WP_CLI::log( sprintf( 'Job %d started from job %d.', $new, (int) $args[0] ) );
		}
		Cli_Helper::wait_and_finish( $new, $assoc );
	}

	/**
	 * Cancel a running or paused job.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Job ID.
	 *
	 * @param array $args  Positional arguments.
	 * @param array $assoc Flags.
	 */
	public function cancel( $args, $assoc ) {
		Cli_Helper::require_admin();
		Cli_Helper::call( 'rsl-ie/job-cancel', [ 'job_id' => (int) $args[0] ] );
		WP_CLI::success( sprintf( 'Job %d cancelled.', (int) $args[0] ) );
	}
}

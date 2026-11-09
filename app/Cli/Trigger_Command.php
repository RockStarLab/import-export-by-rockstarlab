<?php
/**
 * `wp rsl-ie trigger`: list triggers, run one now and read the run log.
 *
 * @package RockStarLab\ImportExport\Cli
 */

namespace RockStarLab\ImportExport\Cli;

use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * List triggers, run one now and read the trigger log.
 *
 * Create triggers in Jobs Log -> Triggers, or with: wp rsl-ie ability trigger-create --help-schema
 *
 * ## EXAMPLES
 *
 *     $ wp rsl-ie trigger list --user=admin
 *     $ wp rsl-ie trigger run trg_0123456789ab --user=admin
 */
class Trigger_Command {

	/**
	 * List the triggers. Tokens are never shown.
	 *
	 * ## OPTIONS
	 *
	 * [--fields=<fields>]
	 * : Columns to show.
	 * ---
	 * default: trigger_id,name,event,source,status,last_run_at,last_job_id,runs
	 * ---
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
	 *   - ids
	 * ---
	 *
	 * @subcommand list
	 *
	 * @param array $args  Positional arguments.
	 * @param array $assoc Flags.
	 */
	public function list_( $args, $assoc ) {
		Cli_Helper::require_admin();
		$rows   = (array) ( Cli_Helper::call( 'rsl-ie/trigger-list' )['triggers'] ?? [] );
		$format = $assoc['format'] ?? 'table';
		if ( 'ids' === $format ) {
			WP_CLI::line( implode( ' ', wp_list_pluck( $rows, 'trigger_id' ) ) );
			return;
		}
		$fields = Cli_Helper::csv_list( $assoc['fields'] ?? 'trigger_id,name,event,source,status,last_run_at,last_job_id,runs' );
		WP_CLI\Utils\format_items( $format, Cli_Helper::rows( $rows, $fields ), $fields );
	}

	/**
	 * Run a trigger now, even when it is paused.
	 *
	 * Refused while the previous job of the trigger still runs, and within a minute of the last run.
	 *
	 * ## OPTIONS
	 *
	 * <trigger_id>
	 * : Trigger ID (trg_...).
	 *
	 * [--[no-]wait]
	 * : Run the job here until it finishes (default), or leave it to WP-Cron.
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
	 * : Print only the job ID.
	 *
	 * @param array $args  Positional arguments.
	 * @param array $assoc Flags.
	 */
	public function run( $args, $assoc ) {
		Cli_Helper::require_admin();
		$started = Cli_Helper::call( 'rsl-ie/trigger-run', [ 'trigger_id' => (string) $args[0] ] );
		$job_id  = (int) ( $started['job_id'] ?? 0 );
		if ( ! $job_id ) {
			WP_CLI::error( 'The trigger did not start a job.' );
		}
		if ( 'table' === ( $assoc['format'] ?? 'table' ) && empty( $assoc['porcelain'] ) ) {
			WP_CLI::log( sprintf( 'Job %d started by trigger %s.', $job_id, $args[0] ) );
		}
		Cli_Helper::wait_and_finish( $job_id, $assoc );
	}

	/**
	 * Show the last trigger runs and refusals.
	 *
	 * ## OPTIONS
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
	public function log( $args, $assoc ) {
		Cli_Helper::require_admin();
		$rows   = (array) ( Cli_Helper::call( 'rsl-ie/trigger-log' )['entries'] ?? [] );
		$fields = [ 'time', 'trigger_id', 'name', 'reason', 'ok', 'job_id', 'detail' ];
		WP_CLI\Utils\format_items( $assoc['format'] ?? 'table', Cli_Helper::rows( $rows, $fields ), $fields );
	}
}

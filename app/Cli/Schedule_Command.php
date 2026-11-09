<?php
/**
 * `wp rsl-ie schedule`: list schedules and run one now.
 *
 * @package RockStarLab\ImportExport\Cli
 */

namespace RockStarLab\ImportExport\Cli;

use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * List job schedules and run one now.
 *
 * Create and change schedules in Jobs Log -> Schedules, or with the schedule-* abilities (wp rsl-ie ability schedule-create --help-schema).
 *
 * ## EXAMPLES
 *
 *     $ wp rsl-ie schedule list --user=admin
 *     $ wp rsl-ie schedule run 3 --user=admin
 */
class Schedule_Command {

	/**
	 * List the schedules.
	 *
	 * ## OPTIONS
	 *
	 * [--fields=<fields>]
	 * : Columns to show.
	 * ---
	 * default: id,name,source_job_id,schedule_type,recurrence,status,next_run_gmt,last_job_id,changed_only
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
		$rows = [];
		for ( $page = 1; $page <= 100; $page++ ) {
			$batch = Cli_Helper::call(
				'rsl-ie/schedule-list',
				[
					'page'     => $page,
					'per_page' => 100,
				]
			);
			$rows  = array_merge( $rows, (array) ( $batch['schedules'] ?? [] ) );
			if ( empty( $batch['more'] ) ) {
				break;
			}
		}
		$format = $assoc['format'] ?? 'table';
		if ( 'ids' === $format ) {
			WP_CLI::line( implode( ' ', wp_list_pluck( $rows, 'id' ) ) );
			return;
		}
		$fields = Cli_Helper::csv_list( $assoc['fields'] ?? 'id,name,source_job_id,schedule_type,recurrence,status,next_run_gmt,last_job_id,changed_only' );
		WP_CLI\Utils\format_items( $format, Cli_Helper::rows( $rows, $fields ), $fields );
	}

	/**
	 * Run a schedule's job now, with the same settings. The schedule keeps its next run time.
	 *
	 * With "changes only" on, the export includes what changed since the schedule's last completed job.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : Schedule ID.
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
	 * [--output=<path>]
	 * : For an export, copy the finished file here (a file path or a folder).
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
	 * : Print only the job ID (or the file path with --output).
	 *
	 * @param array $args  Positional arguments.
	 * @param array $assoc Flags.
	 */
	public function run( $args, $assoc ) {
		Cli_Helper::require_admin();
		if ( isset( $assoc['output'] ) ) {
			Main_Command::check_output( (string) $assoc['output'] );
		}
		$schedule = Cli_Helper::call( 'rsl-ie/schedule-get', [ 'schedule_id' => (int) $args[0] ] );
		$schedule = $schedule['schedule'] ?? $schedule;
		$last     = ! empty( $schedule['last_job_id'] ) ? rsl_ie()->Model->job->find( (int) $schedule['last_job_id'] ) : null;
		if ( $last && in_array( $last->status, [ 'pending', 'processing', 'paused' ], true ) ) {
			WP_CLI::error( sprintf( 'Job %d of this schedule is still running.', (int) $last->id ) );
		}
		$since   = ! empty( $schedule['changed_only'] ) && $last && 'completed' === $last->status ? (string) $last->created_at : '';
		$context = \RockStarLab\ImportExport\Abilities\Execution_Context::from_storage( get_current_user_id(), get_current_blog_id() );
		$result  = is_wp_error( $context ) ? $context : $context->run(
			static function () use ( $schedule, $since ) {
				return ( new \RockStarLab\ImportExport\Service\Schedule_Source_Service() )->execute( (int) $schedule['source_job_id'], Cli_Helper::request_id( 'schedule-' . (int) $schedule['id'] ), $since );
			}
		);
		if ( is_wp_error( $result ) ) {
			Cli_Helper::fail( $result );
		}
		$job_id = (int) ( $result['job_id'] ?? 0 );
		if ( ! $job_id ) {
			WP_CLI::error( 'The schedule did not start a job.' );
		}
		if ( 'table' === ( $assoc['format'] ?? 'table' ) && empty( $assoc['porcelain'] ) ) {
			WP_CLI::log( sprintf( 'Job %d started from schedule %d (%s).', $job_id, (int) $schedule['id'], (string) $schedule['name'] ) );
		}
		$output = $assoc['output'] ?? null;
		Cli_Helper::wait_and_finish(
			$job_id,
			$assoc,
			static function ( $res ) use ( $output ) {
				return null !== $output && 'export' === ( $res['operation'] ?? '' ) && 'completed' === ( $res['status'] ?? '' ) ? [ 'file' => Main_Command::copy_export( (int) $res['job_id'], (string) $output ) ] : [];
			}
		);
	}
}

<?php
/**
 * `wp rsl-ie`: import and export files, list jobs and call any ability from the command line.
 *
 * @package RockStarLab\ImportExport\Cli
 */

namespace RockStarLab\ImportExport\Cli;

use WP_CLI;

defined( 'ABSPATH' ) || exit;

/**
 * Import and export WordPress content, and run Import Export by RockStarLab jobs, from WP-CLI.
 *
 * Run the commands as an administrator (--user=<login>). Jobs started here run in the same process until they finish, so WP-Cron is not needed; add --no-wait to leave them to WP-Cron.
 *
 * Exit codes: 0 done, 1 error or failed job, 2 finished with failed rows, 3 still running after --timeout.
 *
 * ## EXAMPLES
 *
 *     # Import posts from a CSV file, matching columns by name
 *     $ wp rsl-ie import products.csv --type=post --user=admin
 *
 *     # Export published posts to Excel
 *     $ wp rsl-ie export --type=post --format=xlsx --status=publish --output=posts.xlsx --user=admin
 *
 *     # Call any ability with JSON input
 *     $ wp rsl-ie ability job-list '{"limit":5}' --user=admin
 */
class Main_Command {

	/**
	 * Import a local CSV, Excel, JSON or XML file.
	 *
	 * Columns are matched to fields by exact name unless you give a mapping template or a mapping.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : Path to the file (CSV, TSV, XLSX, ODS, JSON, XML or a ZIP with one of them).
	 *
	 * [--type=<type>]
	 * : What to import: post, page, comment or media. With the PRO plugin also product, order, coupon, customer, review, refund, attribute, user, taxonomy, menu, custom_post_types and database_table.
	 * ---
	 * default: post
	 * ---
	 *
	 * [--post-type=<post_type>]
	 * : PRO, with --type=custom_post_types: the post type, for example event.
	 *
	 * [--table=<table>]
	 * : PRO, with --type=database_table: the full table name.
	 *
	 * [--template=<template_id>]
	 * : Apply a saved mapping template (tpl_...). See: wp rsl-ie ability mapping-template-list
	 *
	 * [--mapping=<json>]
	 * : Mapping as JSON, @file.json or - for STDIN: [{"source_field":"Title","target_field":"post_title"}, ...]
	 *
	 * [--options=<json>]
	 * : Import options as JSON, @file.json or -, for example {"if_exists":"update","unique_field":"post_title"}.
	 *
	 * [--if-exists=<mode>]
	 * : What to do when the item already exists.
	 * ---
	 * options:
	 *   - skip
	 *   - update
	 *   - create
	 * ---
	 *
	 * [--unique-field=<field>]
	 * : Field used to find existing items, for example post_title, post_name or ID.
	 *
	 * [--status=<status>]
	 * : Post status for imported posts when the file has none (publish, draft...).
	 *
	 * [--delimiter=<char>]
	 * : CSV delimiter. Use "tab" for tab-separated files. Detected automatically when omitted.
	 *
	 * [--encoding=<encoding>]
	 * : File encoding, for example Windows-1252. Detected automatically when omitted.
	 *
	 * [--no-header]
	 * : The first row is data, not column names.
	 *
	 * [--dry-run]
	 * : Upload and check the file and the mapping, but import nothing.
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
	 * ## EXAMPLES
	 *
	 *     $ wp rsl-ie import coffee.csv --type=post --if-exists=update --unique-field=post_title --user=admin
	 *     $ wp rsl-ie import prices.csv --template=tpl_0123456789ab --user=admin
	 *
	 * @param array $args  Positional arguments.
	 * @param array $assoc Flags.
	 */
	public function import( $args, $assoc ) {
		Cli_Helper::require_admin();
		list( $ns, $type ) = Cli_Helper::resolve_type( $assoc['type'] ?? 'post', false );
		$file              = Cli_Helper::upload( $args[0], Cli_Helper::parser_options( $assoc ) );
		$options           = [];
		if ( isset( $assoc['template'] ) ) {
			$applied = Cli_Helper::call(
				'rsl-ie/mapping-template-apply',
				[
					'template_id' => (string) $assoc['template'],
					'file_id'     => $file['file_id'],
				]
			);
			$mapping = $applied['mapping'];
			$options = (array) ( $applied['options'] ?? [] );
			if ( ! empty( $applied['missing'] ) ) {
				WP_CLI::warning( 'Template columns missing from the file (their fields stay empty): ' . implode( ', ', array_map( [ __CLASS__, 'label' ], (array) $applied['missing'] ) ) );
			}
		} elseif ( isset( $assoc['mapping'] ) ) {
			$mapping = Cli_Helper::json_arg( $assoc['mapping'], 'mapping' );
		} else {
			$prepare = [
				'file_id'     => $file['file_id'],
				'import_type' => $type,
			];
			$target  = Cli_Helper::target_options( [], $assoc );
			if ( $target && 'rsl-ie-pro/' === $ns ) {
				$prepare['options'] = $target;
			}
			$prepared = Cli_Helper::call( $ns . 'import-prepare', $prepare );
			$mapping  = (array) ( $prepared['mapping'] ?? [] );
		}
		if ( ! $mapping ) {
			WP_CLI::error( 'No column matches a field by name. Give a mapping with --mapping or --template. File columns: ' . implode( ', ', array_map( 'strval', (array) ( $file['headers'] ?? [] ) ) ) );
		}
		if ( isset( $assoc['options'] ) ) {
			$options = array_merge( $options, Cli_Helper::json_arg( $assoc['options'], 'options' ) );
		}
		$options = Cli_Helper::target_options( $options, $assoc );
		if ( isset( $assoc['if-exists'] ) ) {
			$options['if_exists'] = (string) $assoc['if-exists'];
		}
		if ( isset( $assoc['unique-field'] ) ) {
			$options['unique_field'] = (string) $assoc['unique-field'];
		}
		if ( isset( $assoc['status'] ) ) {
			$options['post_status'] = (string) $assoc['status'];
		}
		$input = [
			'file_id'     => $file['file_id'],
			'import_type' => $type,
			'mapping'     => array_values( $mapping ),
		];
		if ( $options ) {
			$input['options'] = $options;
		}
		$format = $assoc['format'] ?? 'table';
		if ( ! empty( $assoc['dry-run'] ) ) {
			$checked = Cli_Helper::call( $ns . 'import-validate', $input );
			if ( 'table' === $format ) {
				WP_CLI::log( sprintf( 'File: %s, %d rows', $file['file_name'] ?? basename( $args[0] ), (int) ( $file['total_rows'] ?? 0 ) ) );
				$headers = array_values( (array) ( $file['headers'] ?? [] ) );
				foreach ( $mapping as $map ) {
					$column = $map['source_field'] ?? ( $headers[ (int) ( $map['source_index'] ?? -1 ) ] ?? ( '#' . ( $map['source_index'] ?? '?' ) ) );
					WP_CLI::log( '  ' . $column . ' -> ' . $map['target_field'] );
				}
				WP_CLI::success( 'The file and the mapping are valid. Nothing was imported.' );
			} else {
				WP_CLI::print_value(
					[
						'file'       => $file,
						'mapping'    => $mapping,
						'options'    => $options,
						'validation' => $checked,
					],
					[ 'format' => $format ]
				);
			}
			Cli_Helper::try_call( 'rsl-ie/upload-abort', [ 'file_id' => $file['file_id'] ] );
			return;
		}
		$input['request_id'] = Cli_Helper::request_id( 'import' );
		$started             = Cli_Helper::call( $ns . 'import-start', $input );
		if ( 'table' === $format && empty( $assoc['porcelain'] ) ) {
			WP_CLI::log( sprintf( 'Job %d started: %s, %d rows.', (int) $started['job_id'], $type, (int) ( $file['total_rows'] ?? 0 ) ) );
		}
		Cli_Helper::wait_and_finish( (int) $started['job_id'], $assoc );
	}

	/**
	 * Export content to a CSV, Excel, JSON or XML file.
	 *
	 * ## OPTIONS
	 *
	 * [--type=<type>]
	 * : What to export: post, page, comment, media, urls or form_entries. With the PRO plugin also product, order, coupon, customer, review, refund, attribute, user, taxonomy, menu, custom_post_types and database_table.
	 * ---
	 * default: post
	 * ---
	 *
	 * [--post-type=<post_type>]
	 * : PRO, with --type=custom_post_types: the post type, for example event.
	 *
	 * [--taxonomy=<taxonomy>]
	 * : PRO, with --type=taxonomy: the taxonomy, for example category.
	 *
	 * [--table=<table>]
	 * : PRO, with --type=database_table: the full table name.
	 *
	 * [--format=<format>]
	 * : File format.
	 * ---
	 * default: csv
	 * options:
	 *   - csv
	 *   - xlsx
	 *   - ods
	 *   - json
	 *   - xml
	 * ---
	 *
	 * [--fields=<fields>]
	 * : Comma-separated fields, for example ID,post_title,post_date. All default fields when omitted.
	 *
	 * [--status=<status>]
	 * : Post status, or several separated by commas (publish,draft).
	 *
	 * [--search=<text>]
	 * : Only items that contain this text.
	 *
	 * [--options=<json>]
	 * : Export options and filters as JSON, @file.json or -. See: wp rsl-ie ability export-start --help-schema
	 *
	 * [--name=<name>]
	 * : File name without extension. Defaults to <type>-export-<date>.
	 *
	 * [--output=<path>]
	 * : Copy the finished file here (a file path or a folder). Defaults to the current folder.
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
	 * [--format-output=<format>]
	 * : Output format of the summary.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * [--porcelain]
	 * : Print only the path of the exported file.
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp rsl-ie export --type=post --fields=ID,post_title,post_date --status=publish --user=admin
	 *     $ wp rsl-ie export --type=page --format=xlsx --output=/tmp/pages.xlsx --user=admin
	 *
	 * @param array $args  Positional arguments.
	 * @param array $assoc Flags.
	 */
	public function export( $args, $assoc ) {
		Cli_Helper::require_admin();
		list( $ns, $type ) = Cli_Helper::resolve_type( $assoc['type'] ?? 'post', true );
		self::check_output( $assoc['output'] ?? getcwd() );
		$options = Cli_Helper::target_options( isset( $assoc['options'] ) ? Cli_Helper::json_arg( $assoc['options'], 'options' ) : [], $assoc );
		if ( isset( $assoc['status'] ) ) {
			$list                   = Cli_Helper::csv_list( $assoc['status'] );
			$options['post_status'] = 1 === count( $list ) ? $list[0] : $list;
		}
		if ( isset( $assoc['search'] ) ) {
			$options['s'] = (string) $assoc['search'];
		}
		$name                               = (string) ( $assoc['name'] ?? ( $type . '-export-' . wp_date( 'Y-m-d-His' ) ) );
		$options['custom_export_file_name'] = $options['custom_export_file_name'] ?? $name;
		$input                              = [
			'export_type' => $type,
			'format'      => (string) ( $assoc['format'] ?? 'csv' ),
			'options'     => $options,
			'request_id'  => Cli_Helper::request_id( 'export' ),
		];
		if ( isset( $assoc['fields'] ) ) {
			$input['fields'] = Cli_Helper::csv_list( $assoc['fields'] );
		}
		$started = Cli_Helper::call( $ns . 'export-start', $input );
		$summary = $assoc['format-output'] ?? 'table';
		if ( 'table' === $summary && empty( $assoc['porcelain'] ) ) {
			WP_CLI::log( sprintf( 'Job %d started: %s export to %s.', (int) $started['job_id'], $type, $input['format'] ) );
		}
		$flags           = $assoc;
		$flags['format'] = $summary;
		$output          = $assoc['output'] ?? getcwd();
		Cli_Helper::wait_and_finish(
			(int) $started['job_id'],
			$flags,
			static function ( $result ) use ( $output ) {
				return 'completed' === ( $result['status'] ?? '' ) ? [ 'file' => self::copy_export( (int) $result['job_id'], (string) $output ) ] : [];
			}
		);
	}

	/**
	 * Stop before starting a job when the export cannot be saved where asked.
	 *
	 * @param string $output File path or folder.
	 */
	public static function check_output( $output ) {
		$folder = is_dir( $output ) ? $output : dirname( $output );
		if ( ! is_dir( $folder ) ) {
			WP_CLI::error( 'The folder does not exist: ' . $folder );
		}
		if ( ! wp_is_writable( $folder ) ) {
			WP_CLI::error( 'The folder is not writable: ' . $folder );
		}
	}

	/**
	 * Copy a finished export file out of the plugin's private storage.
	 *
	 * @param int    $job_id Job ID.
	 * @param string $output File path or folder.
	 * @return string Path written.
	 */
	public static function copy_export( $job_id, $output ) {
		$file = Cli_Helper::call( 'rsl-ie/file-get', [ 'job_id' => (int) $job_id ] );
		$rec  = \RockStarLab\ImportExport\Service\File_Store::get( (string) $file['file_id'] );
		if ( is_wp_error( $rec ) || empty( $rec['path'] ) || ! is_file( $rec['path'] ) ) {
			WP_CLI::error( sprintf( 'The file of job %d is no longer available.', $job_id ) );
		}
		$target = is_dir( $output ) ? rtrim( $output, '/\\' ) . DIRECTORY_SEPARATOR . basename( (string) $rec['file_name'] ) : $output;
		if ( ! is_dir( dirname( $target ) ) ) {
			WP_CLI::error( 'The folder does not exist: ' . dirname( $target ) );
		}
		if ( ! copy( $rec['path'], $target ) ) {
			WP_CLI::error( 'Cannot write the file: ' . $target );
		}
		return $target;
	}

	/**
	 * List the jobs in Jobs Log.
	 *
	 * ## OPTIONS
	 *
	 * [--status=<status>]
	 * : Only jobs with this status.
	 * ---
	 * options:
	 *   - pending
	 *   - processing
	 *   - paused
	 *   - completed
	 *   - failed
	 *   - cancelled
	 * ---
	 *
	 * [--type=<type>]
	 * : Only jobs of this kind, for example import or export.
	 *
	 * [--limit=<number>]
	 * : How many jobs, newest first.
	 * ---
	 * default: 20
	 * ---
	 *
	 * [--fields=<fields>]
	 * : Columns to show.
	 * ---
	 * default: job_id,operation,content_type,job_name,status,total,success,failed,skipped
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
	 * ## EXAMPLES
	 *
	 *     $ wp rsl-ie jobs --status=failed --user=admin
	 *
	 * @param array $args  Positional arguments.
	 * @param array $assoc Flags.
	 */
	public function jobs( $args, $assoc ) {
		Cli_Helper::require_admin();
		$input = [ 'limit' => max( 1, min( 100, (int) ( $assoc['limit'] ?? 20 ) ) ) ];
		foreach ( [ 'status', 'type' ] as $key ) {
			if ( isset( $assoc[ $key ] ) ) {
				$input[ $key ] = (string) $assoc[ $key ];
			}
		}
		$jobs   = (array) ( Cli_Helper::call( 'rsl-ie/job-list', $input )['jobs'] ?? [] );
		$format = $assoc['format'] ?? 'table';
		if ( 'ids' === $format ) {
			WP_CLI::line( implode( ' ', wp_list_pluck( $jobs, 'job_id' ) ) );
			return;
		}
		$fields = Cli_Helper::csv_list( $assoc['fields'] ?? 'job_id,operation,content_type,job_name,status,total,success,failed,skipped' );
		WP_CLI\Utils\format_items( $format, Cli_Helper::rows( $jobs, $fields ), $fields );
	}

	/**
	 * List the abilities of the plugin (the same ones AI agents use).
	 *
	 * ## OPTIONS
	 *
	 * [--search=<text>]
	 * : Only abilities whose name or description contains this text.
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
	 * ## EXAMPLES
	 *
	 *     $ wp rsl-ie abilities --search=trigger --user=admin
	 *
	 * @param array $args  Positional arguments.
	 * @param array $assoc Flags.
	 */
	public function abilities( $args, $assoc ) {
		Cli_Helper::require_admin();
		if ( ! function_exists( 'wp_get_abilities' ) ) {
			WP_CLI::error( 'The WordPress Abilities API is not available on this site.' );
		}
		$search = strtolower( (string) ( $assoc['search'] ?? '' ) );
		$rows   = [];
		foreach ( wp_get_abilities() as $ability ) {
			$name = $ability->get_name();
			if ( 0 !== strpos( $name, 'rsl-ie/' ) && 0 !== strpos( $name, 'rsl-ie-pro/' ) ) {
				continue;
			}
			$text = (string) $ability->get_description();
			if ( '' !== $search && false === strpos( strtolower( $name . ' ' . $text ), $search ) ) {
				continue;
			}
			$rows[] = [
				'name'        => $name,
				'description' => 'table' === ( $assoc['format'] ?? 'table' ) ? wp_html_excerpt( $text, 90, '...' ) : $text,
			];
		}
		usort(
			$rows,
			static function ( $a, $b ) {
				return strcmp( $a['name'], $b['name'] );
			}
		);
		WP_CLI\Utils\format_items( $assoc['format'] ?? 'table', $rows, [ 'name', 'description' ] );
	}

	/**
	 * Run one ability with JSON input and print its JSON result.
	 *
	 * The name can be short (job-list) or full (rsl-ie/job-list, rsl-ie-pro/...).
	 *
	 * ## OPTIONS
	 *
	 * <name>
	 * : Ability name.
	 *
	 * [<input>]
	 * : Input as JSON, @file.json or - for STDIN. Defaults to {}.
	 *
	 * [--help-schema]
	 * : Print the description and the input and output schema instead of running it.
	 *
	 * [--format=<format>]
	 * : Output format.
	 * ---
	 * default: json
	 * options:
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     $ wp rsl-ie ability job-list '{"limit":3}' --user=admin
	 *     $ wp rsl-ie ability trigger-create @trigger.json --user=admin
	 *     $ wp rsl-ie ability export-start --help-schema --user=admin
	 *
	 * @param array $args  Positional arguments.
	 * @param array $assoc Flags.
	 */
	public function ability( $args, $assoc ) {
		Cli_Helper::require_admin();
		$ability = Cli_Helper::find_ability( $args[0] );
		if ( ! $ability ) {
			WP_CLI::error( sprintf( 'Unknown ability "%s". See: wp rsl-ie abilities', $args[0] ) );
		}
		$format = $assoc['format'] ?? 'json';
		if ( ! empty( $assoc['help-schema'] ) ) {
			$result = [
				'name'          => $ability->get_name(),
				'description'   => $ability->get_description(),
				'input_schema'  => $ability->get_input_schema(),
				'output_schema' => $ability->get_output_schema(),
			];
		} else {
			$input  = isset( $args[1] ) ? Cli_Helper::json_arg( $args[1], 'input' ) : [];
			$result = Cli_Helper::call( $ability->get_name(), $input );
		}
		if ( 'json' === $format ) {
			WP_CLI::line( (string) wp_json_encode( $result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) );
			return;
		}
		WP_CLI::print_value( $result, [ 'format' => $format ] );
	}

	/**
	 * Readable label for a template column.
	 *
	 * @param mixed $column Column entry.
	 * @return string
	 */
	private static function label( $column ) {
		return is_array( $column ) ? (string) ( $column['source_field'] ?? $column['column'] ?? wp_json_encode( $column ) ) : (string) $column;
	}
}

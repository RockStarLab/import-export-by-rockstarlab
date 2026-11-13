<?php
/**
 * Implemented Free workflow definitions.
 *
 * @package RockStarLab\ImportExport\Abilities
 */

namespace RockStarLab\ImportExport\Abilities;

defined( 'ABSPATH' ) || exit;

/** One reviewed definition per actual operation, never future placeholders. */
class Workflow_Catalog {
	/**
	 * Ability suffixes and contracts.
	 *
	 * @return array
	 */
	public static function definitions() {
		$s           = Workflow_Schemas::string();
		$n           = Workflow_Schemas::integer();
		$b           = [ 'type' => 'boolean' ];
		$job         = Workflow_Schemas::job();
		$file        = Workflow_Schemas::file();
		$id          = [ 'job_id' => Workflow_Schemas::integer( 1 ) ];
		$fid         = [ 'file_id' => Workflow_Schemas::file_id() ];
		$page        = [
			'limit'  => Workflow_Schemas::integer( 1, 100 ),
			'offset' => $n,
		];
		$file_input  = Workflow_Schemas::object( $fid, [ 'file_id' ] );
		$job_input   = Workflow_Schemas::object( $id, [ 'job_id' ] );
		$definitions = [];
		$add         = static function ( $name, $description, $input, $output, $read_only = false, $destructive = false, $idempotent = true ) use ( &$definitions ) {
			$definitions[ $name ] = [
				'label'       => ucwords( str_replace( '-', ' ', $name ) ),
				'description' => $description,
				'input'       => $input,
				'output'      => $output,
				'readonly'    => $read_only,
				'destructive' => $destructive,
				'idempotent'  => $idempotent,
			];
		};
		$add(
			'get-content-schema',
			'Read Free import/export fields, required mappings, integration fields and typed options. Custom metadata uses meta_ keys; consult the returned field catalogue. Use meta_offset with custom_meta.next_offset to page through custom fields. URLs support export only. form_entries (Gravity Forms and Contact Form 7/Flamingo entries) is export only; list forms with form-entry-forms and read the columns of one form with form-entry-fields.',
			Workflow_Schemas::object(
				[
					'content_type' => Workflow_Schemas::string( [ 'post', 'page', 'comment', 'media', 'urls', 'form_entries' ] ),
					'meta_offset'  => $n,
				],
				[ 'content_type' ]
			),
			Workflow_Schemas::object(
				[
					'content_type' => $s,
					'import'       => Workflow_Schemas::value(),
					'export'       => Workflow_Schemas::value(),
					'integrations' => Workflow_Schemas::value(),
				],
				[ 'content_type', 'import', 'export', 'integrations' ]
			),
			true
		);
		$add(
			'get-diagnostics',
			'Read runtime versions, cron configuration flags and file limits. No credentials, license keys or filesystem paths are returned. Cron requires site traffic or a host cron job.',
			Schema_Registry::empty_input(),
			Workflow_Schemas::object(
				[
					'api_version'          => $n,
					'plugin_version'       => $s,
					'wordpress_version'    => $s,
					'php_version'          => $s,
					'cron_disabled'        => $b,
					'recovery_scheduled'   => $b,
					'max_upload_bytes'     => $n,
					'max_json_chunk_bytes' => $n,
				],
				[ 'api_version', 'plugin_version', 'wordpress_version', 'php_version', 'cron_disabled', 'recovery_scheduled', 'max_upload_bytes', 'max_json_chunk_bytes' ]
			),
			true
		);
		$add( 'list-url-sources', 'Read URL source tokens allowed by the currently available providers. Pass tokens as export-start options.content_types. Premium sources require Pro even inside URL export.', Schema_Registry::empty_input(), Workflow_Schemas::object( [ 'sources' => Workflow_Schemas::items( $s ) ], [ 'sources' ] ), true );
		$add(
			'upload-create',
			'Create a private upload session. Send binary chunks to the returned authenticated chunk_url (replace {index}), or use upload-chunk for at most 1 MiB per decoded chunk. Complete before importing.',
			Workflow_Schemas::object(
				[
					'file_name'    => $s,
					'file_size'    => Workflow_Schemas::integer( 1 ),
					'total_chunks' => Workflow_Schemas::integer( 1 ),
				],
				[ 'file_name', 'file_size' ]
			),
			$file,
			false,
			false,
			false
		);
		$add(
			'upload-chunk',
			'Write one base64 chunk, at most 1 MiB decoded. Index starts at zero. Repeating identical bytes at the same index is safe; changed bytes conflict. For large files prefer multipart binary REST upload.',
			Workflow_Schemas::object(
				$fid + [
					'index'       => Workflow_Schemas::integer( 0 ),
					'data_base64' => [
						'type'      => 'string',
						'minLength' => 4,
						'maxLength' => 1398104,
					],
				],
				[ 'file_id', 'index', 'data_base64' ]
			),
			$file
		);
		$parser_input = Workflow_Schemas::object( $fid + [ 'parser_options' => Workflow_Schemas::parser() ], [ 'file_id' ] );
		$add(
			'upload-complete',
			'Assemble and validate uploaded chunks; accept one import file, including a supported file inside ZIP. With resumable=true, repeat until status=ready; intermediate responses report assembled_chunks/assembled_bytes. Assembly yields between chunks; parsing remains synchronous. Persist parser choices and return up to five preview rows.',
			Workflow_Schemas::object(
				$fid + [
					'parser_options' => Workflow_Schemas::parser(),
					'resumable'      => [ 'type' => 'boolean' ],
				],
				[ 'file_id' ]
			),
			$file
		);
		$add(
			'upload-from-url',
			'Download an import file from an http(s) address (CSV, XML, JSON, XLSX, ODS or ZIP; for example a supplier feed or a Google Sheet published as CSV) and return it like upload-complete, ready for import-prepare and import-start. The address is kept with the file and the import job, so a schedule made from that job (schedule-create) downloads the current file at every run. Private network addresses are refused. A file on a saved FTP, FTPS or SFTP server is addressed as rsl-remote://<server id>/<path> (see remote-servers and remote-browse). Nothing is imported.',
			Workflow_Schemas::object(
				[
					'url'            => [
						'type'      => 'string',
						'minLength' => 8,
						'maxLength' => 2000,
						'pattern'   => '^(https?|rsl-remote)://',
					],
					'parser_options' => Workflow_Schemas::parser(),
				],
				[ 'url' ]
			),
			$file
		);
		$server = Workflow_Schemas::object(
			[
				'id'           => $s,
				'label'        => $s,
				'protocol'     => Workflow_Schemas::string( [ 'ftp', 'ftps', 'sftp' ] ),
				'host'         => $s,
				'port'         => Workflow_Schemas::integer(),
				'username'     => $s,
				'passive'      => $b,
				'has_password' => $b,
				'host_key'     => $s,
				'address'      => $s,
			],
			[ 'id', 'label', 'protocol', 'host', 'address' ]
		);
		$add(
			'remote-servers',
			'List the FTP, FTPS and SFTP servers saved in Content Sync, Import, Export > Plugin Options > FTP / SFTP Servers (no passwords). Use a server address (rsl-remote://<id>/<path>) with upload-from-url to import a file from it, or as export options.remote_destination to upload a finished export to it. Servers are added by an administrator in the settings screen.',
			Schema_Registry::empty_input(),
			Workflow_Schemas::object( [ 'servers' => Workflow_Schemas::items( $server ) ], [ 'servers' ] ),
			true
		);
		$add(
			'remote-browse',
			'List files and folders in one folder of a saved FTP, FTPS or SFTP server. Connects to the server; nothing is downloaded.',
			Workflow_Schemas::object(
				[
					'server_id' => Workflow_Schemas::string(),
					'path'      => Workflow_Schemas::string(),
				],
				[ 'server_id' ]
			),
			Workflow_Schemas::object(
				[
					'path'  => $s,
					'items' => Workflow_Schemas::items(
						Workflow_Schemas::object(
							[
								'name'    => $s,
								'type'    => Workflow_Schemas::string( [ 'file', 'dir' ] ),
								'size'    => Workflow_Schemas::integer(),
								'mtime'   => Workflow_Schemas::integer(),
								'address' => $s,
							],
							[ 'name', 'type' ]
						),
						500
					),
				],
				[ 'path', 'items' ]
			),
			true
		);
		$add( 'upload-abort', 'Abort an unfinished upload and remove its chunks. Ready files cannot be aborted.', $file_input, $file, false, true );
		$add( 'file-preview', 'Preview a ready import file and save delimiter/header choices for the next import. This updates file metadata, so use POST.', $parser_input, $file );
		$file_get          = Workflow_Schemas::object( $fid + $id );
		$file_get['oneOf'] = [ [ 'required' => [ 'file_id' ] ], [ 'required' => [ 'job_id' ] ] ];
		$add( 'file-get', 'Resolve file metadata by file ID, or adopt a completed export by job ID. Give users browser_download_url for clickable downloads; it requires WordPress administrator login and prompts login if needed. The binary download_url is for API clients sending authentication, not ordinary browser clicks. No disk paths or credentials are returned. Adoption may create private metadata.', $file_get, $file );
		$add(
			'import-prepare',
			'Preview source columns and return the destination content schema and a suggested exact-name mapping. Review mapping before import-validate/import-start; no content is imported.',
			Workflow_Schemas::object(
				$fid + [
					'import_type'    => Workflow_Schemas::string( [ 'post', 'page', 'comment', 'media' ] ),
					'parser_options' => Workflow_Schemas::parser(),
				],
				[ 'file_id', 'import_type' ]
			),
			Workflow_Schemas::object(
				[
					'file'    => $file,
					'schema'  => Workflow_Schemas::value(),
					'mapping' => Workflow_Schemas::mapping(),
				],
				[ 'file', 'schema', 'mapping' ]
			)
		);
		$add(
			'import-validate',
			'Parse the source and validate the chosen mapping and import options. Runs configured transformations; those can have side effects and require Pro. Does not create an import job.',
			Workflow_Schemas::import_input(),
			Workflow_Schemas::object(
				[
					'valid'       => $b,
					'total_items' => $n,
				],
				[ 'valid', 'total_items' ]
			),
			false,
			false,
			false
		);
		$add( 'import-start', 'Queue Free post/page/comment/media import. Keep request_id unchanged when retrying the same request. Import can create or update content and download media. Poll job-get; WP-Cron runs batches without browser processing.', Workflow_Schemas::import_input( true ), Workflow_Schemas::started(), false, true );
		$add( 'export-count', 'Count records using exactly the same selection and filters as export-start. Does not run field transformations or create files.', Workflow_Schemas::export_input(), Workflow_Schemas::object( [ 'count' => $n ], [ 'count' ] ), true );
		$add(
			'export-preview',
			'Preview up to ten exported rows with selected fields and transformations. Pro transformations may have side effects; use POST. Does not create a job or output file.',
			Workflow_Schemas::export_input(),
			Workflow_Schemas::object(
				[
					'preview' => Workflow_Schemas::items( Workflow_Schemas::value(), 10 ),
					'fields'  => Workflow_Schemas::items( Workflow_Schemas::value() ),
				],
				[ 'preview', 'fields' ]
			),
			false,
			false,
			false
		);
		$add( 'export-start', 'Queue a Free content or URL export. options.custom_export_file_name is required. Keep request_id for network retries; poll job-get, then call file-get with job_id and download via its authenticated URL. Transformations require Pro.', Workflow_Schemas::export_input( true ), Workflow_Schemas::started(), false, true );
		$add(
			'job-list',
			'List job summaries in descending ID order. Reads never execute batches. Domain-specific mutations are available only after their runner is implemented.',
			Workflow_Schemas::object(
				$page + [
					'status' => $job['properties']['status'],
					'type'   => $s,
				]
			),
			Workflow_Schemas::object(
				[
					'jobs'   => Workflow_Schemas::items( $job, 100 ),
					'total'  => $n,
					'limit'  => $n,
					'offset' => $n,
				],
				[ 'jobs', 'total', 'limit', 'offset' ]
			),
			true
		);
		$add( 'job-get', 'Read safe job status, counters and currently valid lifecycle actions without running work or exposing configuration and credentials. Import preparation optionally reports format, phase (indexing/preparing/ready), prepared_rows and indexed_rows. These are preparation counters, not imported records or an overall percentage; status remains authoritative for pause, failure and cancellation.', $job_input, $job, true );
		$add(
			'job-get-logs',
			'Read up to 100 persisted job log events, ordered by ID. Filter by severity. Events from before job logging was introduced cannot be reconstructed.',
			Workflow_Schemas::object( $id + $page + [ 'level' => Workflow_Schemas::string( [ 'all', 'debug', 'info', 'warning', 'error' ] ) ], [ 'job_id' ] ),
			Workflow_Schemas::object(
				[
					'logs'   => Workflow_Schemas::items(
						Workflow_Schemas::object(
							[
								'id'         => [ 'type' => [ 'integer', 'string' ] ],
								'level'      => $s,
								'message'    => $s,
								'created_at' => $s,
							],
							[ 'id', 'level', 'message', 'created_at' ]
						),
						100
					),
					'total'  => $n,
					'limit'  => $n,
					'offset' => $n,
				],
				[ 'logs', 'total', 'limit', 'offset' ]
			),
			true
		);
		$result                = $job;
		$result['properties'] += [
			'error'          => Workflow_Schemas::value(),
			'errors'         => Workflow_Schemas::items( Workflow_Schemas::value(), 100 ),
			'errors_total'   => $n,
			'limit'          => $n,
			'offset'         => $n,
			'file_available' => $b,
		];
		$add( 'job-get-result', 'Read job results and a page of errors. Content/provider permission is rechecked. For a completed export use file-get with job_id to obtain the download URL.', Workflow_Schemas::object( $id + $page, [ 'job_id' ] ), $result, true );
		foreach ( [ 'pause', 'cancel', 'resume', 'retry' ] as $action ) {
			$details = [
				'pause'  => 'Stop at the next processing boundary; retain checkpoint.',
				'cancel' => 'Cancel pending, processing or paused work; completed changes are retained.',
				'resume' => 'Continue a paused job at its checkpoint under the original initiating user.',
				'retry'  => 'Continue a failed job at its checkpoint, incrementing retries once. Effects of an unconfirmed row may repeat.',
			];
			$add( 'job-' . $action, $details[ $action ] . ' Only file import/export runners are currently supported.', $job_input, $job, false, in_array( $action, [ 'cancel', 'resume', 'retry' ], true ) );
		}
		$restart_id              = Workflow_Schemas::request_id();
		$restart_id['maxLength'] = 96;
		$add( 'job-restart', 'Create a new job from a terminal job using original input and the requesting administrator. Supports file/folder jobs and versioned AI URL/Content Updater jobs. Starts at zero; previously completed content effects may repeat. Domain active or uncertain writing checkpoints cannot restart. Requires a stable request_id for retries.', Workflow_Schemas::object( $id + [ 'request_id' => $restart_id ], [ 'job_id', 'request_id' ] ), Workflow_Schemas::started(), false, true );
		$add(
			'job-rename',
			'Change only the displayed job name.',
			Workflow_Schemas::object(
				$id + [
					'name' => [
						'type'      => 'string',
						'minLength' => 1,
						'maxLength' => 255,
					],
				],
				[ 'job_id', 'name' ]
			),
			Workflow_Schemas::object( $id + [ 'job_name' => $s ], [ 'job_id', 'job_name' ] )
		);
		$add( 'job-delete', 'Delete eligible terminal file/folder or versioned AI URL/Content Updater or standalone sync history and its logs. Terminal migration roots remove owned worker history atomically; reusable files and saved source selections are retained. Busy, active or uncertain descendants block deletion. Private export resources are removed only for file exports. Domain uncertain writing checkpoints block deletion. Source import files are retained. Active schedules block deletion. Used request IDs remain reserved.', $job_input, Workflow_Schemas::object( $id + [ 'deleted' => $b ], [ 'job_id', 'deleted' ] ), false, true );
		$add(
			'job-cleanup',
			'Inspect up to 100 terminal records older than days and delete eligible file, versioned AI URL/Content Updater, standalone sync or migration history, preserving active schedule references and request dedup history. May remove export files and logs; Skipped records include a reason; unresolved blocked records may remain in the next batch.',
			Workflow_Schemas::object( [ 'days' => Workflow_Schemas::integer( 1, 36500 ) ], [ 'days' ] ),
			Workflow_Schemas::object(
				[
					'deleted'     => $n,
					'skipped'     => Workflow_Schemas::items( Workflow_Schemas::object( $id + [ 'reason' => $s ], [ 'job_id', 'reason' ] ), 100 ),
					'batch_limit' => $n,
				],
				[ 'deleted', 'skipped', 'batch_limit' ]
			),
			false,
			true,
			false
		);
		// Starts are deduplicated, but restarting completed content work is still a POST.
		// Core selects DELETE for destructive+idempotent; use conservative false for content execution.
		foreach ( [ 'import-start', 'export-start', 'job-resume', 'job-retry', 'job-restart' ] as $name ) {
			$definitions[ $name ]['idempotent'] = false; }
		return $definitions;
	}
}

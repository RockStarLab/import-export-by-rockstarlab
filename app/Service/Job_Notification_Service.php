<?php
/**
 * Notifications when import and export jobs finish: email, Slack, webhook.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Notification_Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * Finds finished jobs after the request that finished them and sends one message per job.
 *
 * Detection is a cheap query run at the end of plugin requests; sending happens in
 * WP-Cron so a slow Slack or webhook endpoint never delays an import screen.
 * Secrets (Slack URL, webhook URL and signing secret) are never logged or returned.
 */
class Job_Notification_Service {
	const OPTION       = 'rsl_ie_notifications';
	const STATE        = 'rsl_ie_notifications_state';
	const LOG          = 'rsl_ie_notifications_log';
	const RETRIES      = 'rsl_ie_notifications_retries';
	const CRON_HOOK    = 'rsl_ie_send_notifications';
	const LOCK         = 'rsl_ie_notifications_lock';
	const HOURLY_LIMIT = 30;
	const LOG_SIZE     = 30;
	const TYPES        = [ 'import', 'export', 'sync', 'migration' ];

	/** Register hooks. */
	public static function init() {
		$service = new self();
		add_action( self::CRON_HOOK, [ $service, 'sweep' ] );
		add_action( 'shutdown', [ $service, 'on_shutdown' ], 20 );
	}

	/**
	 * Settings with defaults; secrets included (server use only).
	 *
	 * @return array
	 */
	public function settings() {
		$raw = get_option( self::OPTION, [] );
		$raw = is_array( $raw ) ? $raw : [];
		return array_replace_recursive(
			[
				'enabled'   => false,
				'when'      => 'errors',
				'job_types' => [ 'import', 'export' ],
				'email'     => [
					'enabled'    => false,
					'recipients' => [],
				],
				'slack'     => [
					'enabled' => false,
					'url'     => '',
				],
				'webhook'   => [
					'enabled' => false,
					'url'     => '',
					'secret'  => '',
				],
				'subject'   => $this->default_subject(),
				'message'   => $this->default_message(),
			],
			$raw
		);
	}

	/**
	 * Settings safe to show: URLs masked, secret reduced to a flag.
	 *
	 * @return array
	 */
	public function public_settings() {
		$s = $this->settings();
		return [
			'enabled'      => (bool) $s['enabled'],
			'when'         => $s['when'],
			'job_types'    => array_values( $s['job_types'] ),
			'email'        => [
				'enabled'    => (bool) $s['email']['enabled'],
				'recipients' => array_values( $s['email']['recipients'] ),
			],
			'slack'        => [
				'enabled' => (bool) $s['slack']['enabled'],
				'url_set' => '' !== $s['slack']['url'],
				'url'     => self::mask_url( $s['slack']['url'] ),
			],
			'webhook'      => [
				'enabled'    => (bool) $s['webhook']['enabled'],
				'url_set'    => '' !== $s['webhook']['url'],
				'url'        => self::mask_url( $s['webhook']['url'] ),
				'secret_set' => '' !== $s['webhook']['secret'],
			],
			'subject'      => $s['subject'],
			'message'      => $s['message'],
			'placeholders' => array_keys( $this->placeholders( null ) ),
		];
	}

	/**
	 * Validate and save a partial update.
	 *
	 * Empty slack.url / webhook.url keep the saved value; clear_slack_url and
	 * clear_webhook_url remove it. regenerate_secret returns the new signing secret once.
	 *
	 * @param array $input Changes.
	 * @return array|\WP_Error Public settings (+ webhook_secret when regenerated).
	 */
	public function update( array $input ) {
		$s = $this->settings();
		if ( isset( $input['enabled'] ) ) {
			$s['enabled'] = self::bool( $input['enabled'] );
		}
		if ( isset( $input['when'] ) ) {
			if ( ! in_array( $input['when'], [ 'always', 'errors' ], true ) ) {
				return $this->error( 'notification_when', __( 'Choose when to notify: always or only when something failed.', 'import-export-by-rockstarlab' ) );
			}
			$s['when'] = $input['when'];
		}
		if ( isset( $input['job_types'] ) ) {
			$types = array_values( array_intersect( self::TYPES, array_map( 'strval', (array) $input['job_types'] ) ) );
			if ( ! $types ) {
				return $this->error( 'notification_types', __( 'Choose at least one kind of job to be notified about.', 'import-export-by-rockstarlab' ) );
			}
			$s['job_types'] = $types;
		}
		if ( isset( $input['email'] ) && is_array( $input['email'] ) ) {
			if ( isset( $input['email']['enabled'] ) ) {
				$s['email']['enabled'] = self::bool( $input['email']['enabled'] );
			}
			if ( isset( $input['email']['recipients'] ) ) {
				$list = is_array( $input['email']['recipients'] ) ? $input['email']['recipients'] : preg_split( '/[\s,;]+/', (string) $input['email']['recipients'] );
				$good = [];
				foreach ( (array) $list as $address ) {
					$address = trim( (string) $address );
					if ( '' === $address ) {
						continue;
					}
					if ( ! is_email( $address ) ) {
						/* translators: %s: email address. */
						return $this->error( 'notification_email', sprintf( __( '"%s" is not a valid email address.', 'import-export-by-rockstarlab' ), $address ) );
					}
					if ( ! isset( $good[ strtolower( $address ) ] ) ) {
						$good[ strtolower( $address ) ] = sanitize_email( $address );
					}
				}
				if ( count( $good ) > 10 ) {
					return $this->error( 'notification_email', __( 'Enter at most 10 email addresses.', 'import-export-by-rockstarlab' ) );
				}
				$s['email']['recipients'] = array_values( $good );
			}
		}
		foreach ( [ 'slack', 'webhook' ] as $channel ) {
			if ( ! isset( $input[ $channel ] ) || ! is_array( $input[ $channel ] ) ) {
				continue;
			}
			if ( isset( $input[ $channel ]['enabled'] ) ) {
				$s[ $channel ]['enabled'] = self::bool( $input[ $channel ]['enabled'] );
			}
			if ( ! empty( $input[ $channel ]['clear_url'] ) ) {
				$s[ $channel ]['url'] = '';
			}
			$url = isset( $input[ $channel ]['url'] ) ? trim( (string) $input[ $channel ]['url'] ) : '';
			if ( '' !== $url ) {
				$valid = 'slack' === $channel ? self::validate_slack_url( $url ) : self::validate_webhook_url( $url );
				if ( is_wp_error( $valid ) ) {
					return $valid;
				}
				$s[ $channel ]['url'] = $valid;
			}
		}
		$new_secret = null;
		if ( ! empty( $input['regenerate_secret'] ) || ( $s['webhook']['enabled'] && '' === $s['webhook']['secret'] ) ) {
			$new_secret              = wp_generate_password( 40, false, false );
			$s['webhook']['secret'] = $new_secret;
		}
		foreach ( [ 'subject' => 200, 'message' => 2000 ] as $key => $max ) {
			if ( isset( $input[ $key ] ) ) {
				$text = 'subject' === $key ? sanitize_text_field( (string) $input[ $key ] ) : sanitize_textarea_field( (string) $input[ $key ] );
				$text = function_exists( 'mb_substr' ) ? mb_substr( $text, 0, $max ) : substr( $text, 0, $max );
				$s[ $key ] = '' !== trim( $text ) ? $text : ( 'subject' === $key ? $this->default_subject() : $this->default_message() );
			}
		}
		if ( $s['enabled'] ) {
			if ( $s['email']['enabled'] && ! $s['email']['recipients'] ) {
				return $this->error( 'notification_email', __( 'Enter at least one email address or turn email off.', 'import-export-by-rockstarlab' ) );
			}
			if ( $s['slack']['enabled'] && '' === $s['slack']['url'] ) {
				return $this->error( 'notification_slack', __( 'Paste the Slack incoming webhook URL or turn Slack off.', 'import-export-by-rockstarlab' ) );
			}
			if ( $s['webhook']['enabled'] && '' === $s['webhook']['url'] ) {
				return $this->error( 'notification_webhook', __( 'Enter the webhook URL or turn the webhook off.', 'import-export-by-rockstarlab' ) );
			}
		}
		$was = (bool) $this->settings()['enabled'];
		$this->store( self::OPTION, $s );
		if ( $s['enabled'] && ! $was ) {
			// Start from now: jobs that finished before notifications were turned on are not reported.
			$this->store( self::STATE, [ 'floor' => $this->max_job_id(), 'notified' => [] ] );
		}
		$out = $this->public_settings();
		if ( null !== $new_secret ) {
			$out['webhook_secret'] = $new_secret;
		}
		return $out;
	}

	/**
	 * Ready n8n workflow that receives the job webhook and checks its signature.
	 * Needs NODE_FUNCTION_ALLOW_BUILTIN=crypto in n8n. The secret is a placeholder.
	 *
	 * @return array
	 */
	public static function n8n_workflow() {
		$code = 'const crypto = require(\'crypto\');
const secret = \'PASTE-THE-SIGNING-SECRET-HERE\';
const headers = $input.first().json.headers;
const raw = (await this.helpers.getBinaryDataBuffer(0, \'data\')).toString(\'utf8\');
const expected = \'sha256=\' + crypto.createHmac(\'sha256\', secret).update(headers[\'x-rsl-timestamp\'] + \'.\' + raw).digest(\'hex\');
if (expected !== headers[\'x-rsl-signature\']) {
  throw new Error(\'Signature does not match: not sent by your WordPress site\');
}
const body = JSON.parse(raw);
return [{ json: { event: body.event, job_id: body.job ? body.job.id : null, status: body.job ? body.job.status : null, failed: body.job ? body.job.failed : 0, errors: body.errors, text: body.text } }];';
		return [
			'name'        => __( 'WordPress: job finished', 'import-export-by-rockstarlab' ),
			'nodes'       => [
				[
					'parameters'  => [
						'httpMethod' => 'POST',
						'path'       => 'rsl-job-finished',
						'options'    => [ 'rawBody' => true ],
					],
					'id'          => wp_generate_uuid4(),
					'name'        => 'Job finished',
					'type'        => 'n8n-nodes-base.webhook',
					'typeVersion' => 2,
					'position'    => [ 0, 0 ],
					'webhookId'   => wp_generate_uuid4(),
				],
				[
					'parameters'  => [ 'jsCode' => $code ],
					'id'          => wp_generate_uuid4(),
					'name'        => 'Check signature',
					'type'        => 'n8n-nodes-base.code',
					'typeVersion' => 2,
					'position'    => [ 240, 0 ],
				],
			],
			'connections' => [
				'Job finished' => [
					'main' => [
						[
							[
								'node'  => 'Check signature',
								'type'  => 'main',
								'index' => 0,
							],
						],
					],
				],
			],
			'settings'    => [ 'executionOrder' => 'v1' ],
			'pinData'     => new \stdClass(),
		];
	}

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
		$definition = Notification_Catalog::definitions()[ $name ] ?? null;
		if ( ! $definition ) {
			return $this->error( 'operation', __( 'Unknown notification operation.', 'import-export-by-rockstarlab' ), 400 );
		}
		$valid = rest_validate_value_from_schema( $input, $definition['input'], 'input' );
		if ( is_wp_error( $valid ) ) {
			$valid->add_data( [ 'status' => 400 ] );
			return $valid;
		}
		switch ( $name ) {
			case 'notification-settings-get':
				return $this->public_settings();
			case 'notification-settings-update':
				return $this->update( $input );
			case 'notification-test':
				return $this->send_test();
			case 'notification-log':
				return [ 'entries' => $this->log_entries() ];
			case 'notification-n8n-workflow':
				return [
					'workflow'      => json_decode( wp_json_encode( self::n8n_workflow() ), true ),
					'workflow_json' => (string) wp_json_encode( self::n8n_workflow(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
				];
			case 'job-errors':
				return $this->job_errors( (int) $input['job_id'], (int) ( $input['limit'] ?? 100 ), (int) ( $input['offset'] ?? 0 ), ! empty( $input['csv'] ) );
		}
		return $this->error( 'operation', __( 'Unknown notification operation.', 'import-export-by-rockstarlab' ), 400 );
	}

	/**
	 * Row errors of an import job, with fields and fixes, optionally as CSV.
	 *
	 * @param int  $job_id Job ID.
	 * @param int  $limit  Page size.
	 * @param int  $offset Offset.
	 * @param bool $csv    Include the error CSV.
	 * @return array|\WP_Error
	 */
	public function job_errors( $job_id, $limit = 100, $offset = 0, $csv = false ) {
		$job = rsl_ie()->Model->job->find( absint( $job_id ) );
		if ( ! $job ) {
			return $this->error( 'job_not_found', __( 'Job not found.', 'import-export-by-rockstarlab' ), 404 );
		}
		$errors = self::stored_errors( $job );
		$out    = [
			'job_id'       => (int) $job->id,
			'status'       => (string) $job->status,
			'errors_total' => count( $errors ),
			'errors'       => array_map(
				static function ( $e ) {
					unset( $e['values'] );
					return $e;
				},
				array_slice( $errors, max( 0, $offset ), max( 1, min( 500, $limit ) ) )
			),
		];
		if ( $csv ) {
			$out['file_name'] = sprintf( 'import-%d-errors.csv', (int) $job->id );
			$out['csv']       = \RockStarLab\ImportExport\Helper\Import_Row_Error::csv( $errors );
		}
		return $out;
	}

	/**
	 * Error entries of a job, upgraded with hints for jobs saved before hints existed.
	 *
	 * @param object $job Job row.
	 * @return array
	 */
	public static function stored_errors( $job ) {
		$result = json_decode( (string) $job->result, true );
		$errors = is_array( $result ) && is_array( $result['errors'] ?? null ) ? $result['errors'] : [];
		if ( ! $errors ) {
			$params = json_decode( (string) $job->parameters, true );
			$errors = is_array( $params['cumulative_result']['errors'] ?? null ) ? $params['cumulative_result']['errors'] : [];
		}
		$out = [];
		foreach ( $errors as $error ) {
			if ( ! is_array( $error ) ) {
				$error = [ 'message' => (string) $error ];
			}
			$error['row']     = isset( $error['row'] ) ? (int) $error['row'] : 0;
			$error['message'] = (string) ( $error['message'] ?? '' );
			$error['field']   = (string) ( $error['field'] ?? '' );
			$error['code']    = (string) ( $error['code'] ?? '' );
			$error['hint']    = (string) ( $error['hint'] ?? \RockStarLab\ImportExport\Helper\Import_Row_Error::hint( $error['code'], $error['field'], $error['message'] ) );
			$out[]            = $error;
		}
		return $out;
	}

	/**
	 * At the end of plugin requests, queue sending when a watched job has finished.
	 */
	public function on_shutdown() {
		$s = $this->settings();
		if ( empty( $s['enabled'] ) || ! $this->relevant_request() ) {
			return;
		}
		if ( wp_doing_cron() ) {
			$this->sweep();
			return;
		}
		if ( ( $this->pending_jobs( 1 ) || $this->due_retries() ) && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time(), self::CRON_HOOK );
		}
	}

	/**
	 * Send notifications for finished jobs and due retries. Safe to call often.
	 *
	 * @return array Jobs and channels handled.
	 */
	public function sweep() {
		$s = $this->settings();
		if ( empty( $s['enabled'] ) ) {
			return [];
		}
		if ( ! add_option( self::LOCK, time(), '', false ) ) {
			if ( time() - (int) get_option( self::LOCK, 0 ) < 120 ) {
				return [];
			}
			update_option( self::LOCK, time(), false );
		}
		$handled = [];
		try {
			foreach ( $this->pending_jobs( 20 ) as $job ) {
				$this->mark_notified( (int) $job->id );
				if ( ! $this->should_notify( $job, $s ) ) {
					continue;
				}
				$handled[ (int) $job->id ] = $this->deliver( $job, $s, $this->channels( $s ) );
			}
			foreach ( $this->take_due_retries() as $retry ) {
				$job = rsl_ie()->Model->job->find( (int) $retry['job_id'] );
				if ( $job && in_array( $retry['channel'], $this->channels( $s ), true ) ) {
					$handled[ (int) $job->id ] = $this->deliver( $job, $s, [ $retry['channel'] ], (int) $retry['attempt'] );
				}
			}
			$this->advance_floor();
		} finally {
			delete_option( self::LOCK );
		}
		return $handled;
	}

	/**
	 * Send a sample message to every enabled channel.
	 *
	 * @return array Per-channel result.
	 */
	public function send_test() {
		$s = $this->settings();
		$results = [];
		foreach ( $this->channels( $s ) as $channel ) {
			$results[ $channel ] = $this->send( $channel, $s, null );
			$this->log( 0, $channel, $results[ $channel ] );
		}
		if ( ! $results ) {
			return $this->error( 'notification_no_channel', __( 'Turn on email, Slack or a webhook first.', 'import-export-by-rockstarlab' ) );
		}
		return [ 'results' => $results ];
	}

	/**
	 * Recent deliveries, newest first. No URLs or secrets.
	 *
	 * @return array
	 */
	public function log_entries() {
		$log = get_option( self::LOG, [] );
		return array_reverse( is_array( $log ) ? $log : [] );
	}

	/**
	 * Deliver one job to the given channels, respecting the hourly limit.
	 *
	 * @param object $job      Job.
	 * @param array  $s        Settings.
	 * @param array  $channels Channels.
	 * @param int    $attempt  Attempt number (1 = first).
	 * @return array
	 */
	private function deliver( $job, array $s, array $channels, $attempt = 1 ) {
		$out = [];
		foreach ( $channels as $channel ) {
			if ( ! $this->within_rate_limit() ) {
				$out[ $channel ] = [
					'ok'     => false,
					'detail' => 'rate_limited',
				];
				$this->log( (int) $job->id, $channel, $out[ $channel ] );
				continue;
			}
			$out[ $channel ] = $this->send( $channel, $s, $job );
			$this->log( (int) $job->id, $channel, $out[ $channel ], $attempt );
			if ( ! $out[ $channel ]['ok'] && ! empty( $out[ $channel ]['retry'] ) && $attempt < 3 ) {
				$this->queue_retry( (int) $job->id, $channel, $attempt + 1 );
			}
		}
		return $out;
	}

	/**
	 * Send through one channel.
	 *
	 * @param string      $channel email|slack|webhook.
	 * @param array       $s       Settings.
	 * @param object|null $job     Job, or null for a test message.
	 * @return array ok, detail, retry.
	 */
	private function send( $channel, array $s, $job ) {
		$vars    = $this->placeholders( $job );
		// An empty placeholder (a job without a name) must not leave a double space.
		$subject = trim( preg_replace( '/ {2,}/', ' ', strtr( $s['subject'], $vars ) ) );
		if ( ! $job ) {
			/* translators: %s: notification subject. */
			$subject = sprintf( __( '[Test] %s', 'import-export-by-rockstarlab' ), $subject );
		}
		$message = preg_replace( '/ {2,}/', ' ', strtr( $s['message'], $vars ) );
		if ( 'email' === $channel ) {
			$sent = wp_mail( $s['email']['recipients'], $subject, $message );
			return [
				'ok'     => (bool) $sent,
				'detail' => $sent ? 'sent' : 'wp_mail_failed',
			];
		}
		if ( 'slack' === $channel ) {
			$response = $this->post( $s['slack']['url'], wp_json_encode( [ 'text' => '*' . $subject . "*\n" . $message ] ), [] );
			return $this->http_result( $response );
		}
		$body      = wp_json_encode( $this->payload( $job, $vars, $subject, $message ) );
		$timestamp = (string) time();
		$response  = $this->post(
			$s['webhook']['url'],
			$body,
			[
				'X-RSL-Event'     => $job ? 'job.finished' : 'test',
				'X-RSL-Timestamp' => $timestamp,
				'X-RSL-Signature' => 'sha256=' . hash_hmac( 'sha256', $timestamp . '.' . $body, (string) $s['webhook']['secret'] ),
			]
		);
		return $this->http_result( $response );
	}

	/**
	 * JSON body for webhooks.
	 *
	 * @param object|null $job     Job.
	 * @param array       $vars    Placeholder values.
	 * @param string      $subject Rendered subject.
	 * @param string      $message Rendered message.
	 * @return array
	 */
	private function payload( $job, array $vars, $subject, $message ) {
		$errors = [];
		if ( $job ) {
			foreach ( array_slice( self::stored_errors( $job ), 0, 20 ) as $error ) {
				$errors[] = [
					'row'     => $error['row'],
					'field'   => $error['field'],
					'message' => $error['message'],
					'hint'    => $error['hint'],
				];
			}
		}
		return [
			'event'   => $job ? 'job.finished' : 'test',
			'site'    => [
				'name' => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
				'url'  => home_url( '/' ),
			],
			'job'     => $job ? [
				'id'           => (int) $job->id,
				'name'         => (string) $job->job_name,
				'type'         => (string) $job->type,
				'content_type' => (string) $job->data_type,
				'status'       => (string) $job->status,
				'total'        => (int) $vars['{total}'],
				'success'      => (int) $vars['{success}'],
				'failed'       => (int) $vars['{failed}'],
				'skipped'      => (int) $vars['{skipped}'],
				'started_at'   => (string) $job->started_at,
				'completed_at' => (string) $job->completed_at,
				'url'          => $vars['{job_url}'],
			] : null,
			'errors'  => $errors,
			'subject' => $subject,
			'text'    => $message,
		];
	}

	/**
	 * Placeholder values for a job (or sample values for a test).
	 *
	 * @param object|null $job Job.
	 * @return array
	 */
	private function placeholders( $job ) {
		$counts = $job ? self::counts( $job ) : [
			'total'   => 120,
			'success' => 118,
			'failed'  => 2,
			'skipped' => 0,
		];
		$errors = '';
		if ( $job ) {
			foreach ( array_slice( self::stored_errors( $job ), 0, 5 ) as $error ) {
				/* translators: 1: row number, 2: field, 3: error message. */
				$errors .= sprintf( __( 'Row %1$d %2$s: %3$s', 'import-export-by-rockstarlab' ), $error['row'], '' !== $error['field'] ? '(' . $error['field'] . ')' : '', $error['message'] ) . "\n";
			}
		} else {
			$errors = __( 'Row 7 (post_date): Invalid date. (sample)', 'import-export-by-rockstarlab' ) . "\n";
		}
		$duration = $job && $job->started_at && $job->completed_at ? max( 0, strtotime( $job->completed_at ) - strtotime( $job->started_at ) ) : 0;
		$labels   = [
			'completed' => __( 'completed', 'import-export-by-rockstarlab' ),
			'failed'    => __( 'failed', 'import-export-by-rockstarlab' ),
			'cancelled' => __( 'cancelled', 'import-export-by-rockstarlab' ),
		];
		$status   = $job ? (string) $job->status : 'completed';
		if ( 'completed' === $status && $counts['failed'] > 0 ) {
			$status_label = __( 'completed with errors', 'import-export-by-rockstarlab' );
		} else {
			$status_label = $labels[ $status ] ?? $status;
		}
		return [
			'{site_name}'    => wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ),
			'{job_id}'       => $job ? (string) $job->id : '123',
			'{job_name}'     => $job ? (string) ( $job->job_name ?: '' ) : __( 'Test notification', 'import-export-by-rockstarlab' ),
			'{job_type}'     => $job ? ( 'export' === $job->type ? __( 'Export', 'import-export-by-rockstarlab' ) : ( 'import' === $job->type ? __( 'Import', 'import-export-by-rockstarlab' ) : ucwords( str_replace( '_', ' ', (string) $job->type ) ) ) ) : __( 'Import', 'import-export-by-rockstarlab' ),
			'{content_type}' => $job ? (string) $job->data_type : 'post',
			'{status}'       => $status_label,
			'{total}'        => (string) $counts['total'],
			'{success}'      => (string) $counts['success'],
			'{failed}'       => (string) $counts['failed'],
			'{skipped}'      => (string) $counts['skipped'],
			'{duration}'     => $duration >= 60 ? sprintf( '%dm %ds', floor( $duration / 60 ), $duration % 60 ) : $duration . 's',
			'{errors}'       => trim( $errors ),
			'{job_url}'      => admin_url( 'admin.php?page=rsl-ie-jobs-log' ),
		];
	}

	/**
	 * Row counters of a job.
	 *
	 * @param object $job Job.
	 * @return array
	 */
	public static function counts( $job ) {
		$result = json_decode( (string) $job->result, true );
		if ( ! is_array( $result ) || ! isset( $result['total'] ) ) {
			$params = json_decode( (string) $job->parameters, true );
			$result = is_array( $params['cumulative_result'] ?? null ) ? $params['cumulative_result'] : [];
		}
		$total = (int) ( $result['total'] ?? $job->total_items );
		return [
			'total'   => $total ? $total : (int) $job->processed_items,
			'success' => (int) ( $result['success'] ?? $job->success_items ),
			'failed'  => (int) ( $result['failed'] ?? $job->failed_items ),
			'skipped' => (int) ( $result['skipped'] ?? $job->skipped_items ),
		];
	}

	/**
	 * Whether a finished job matches the settings.
	 *
	 * @param object $job Job.
	 * @param array  $s   Settings.
	 * @return bool
	 */
	private function should_notify( $job, array $s ) {
		if ( 'cancelled' === $job->status ) {
			return false;
		}
		$params = json_decode( (string) $job->parameters, true );
		if ( ! empty( $params['migration_parent_id'] ) ) {
			return false;
		}
		$kind = self::job_kind( $job );
		if ( '' === $kind || ! in_array( $kind, (array) $s['job_types'], true ) ) {
			return false;
		}
		if ( 'errors' === $s['when'] ) {
			return 'failed' === $job->status || self::counts( $job )['failed'] > 0;
		}
		return true;
	}

	/**
	 * Settings group of a job.
	 *
	 * @param object $job Job.
	 * @return string
	 */
	public static function job_kind( $job ) {
		$type = (string) $job->type;
		if ( in_array( $type, [ 'import', 'export' ], true ) ) {
			return $type;
		}
		if ( 0 === strpos( $type, 'migration' ) ) {
			return 'migration';
		}
		if ( 0 === strpos( $type, 'content_sync' ) || 'sync_background' === $type ) {
			return 'sync';
		}
		return '';
	}

	/**
	 * Enabled channels.
	 *
	 * @param array $s Settings.
	 * @return string[]
	 */
	private function channels( array $s ) {
		$out = [];
		if ( ! empty( $s['email']['enabled'] ) && $s['email']['recipients'] ) {
			$out[] = 'email';
		}
		if ( ! empty( $s['slack']['enabled'] ) && '' !== $s['slack']['url'] ) {
			$out[] = 'slack';
		}
		if ( ! empty( $s['webhook']['enabled'] ) && '' !== $s['webhook']['url'] ) {
			$out[] = 'webhook';
		}
		return $out;
	}

	/**
	 * Finished jobs not handled yet.
	 *
	 * @param int $limit Max rows.
	 * @return array
	 */
	private function pending_jobs( $limit ) {
		global $wpdb;
		$state = $this->state();
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM %i WHERE id > %d AND status IN ('completed','failed','cancelled') ORDER BY id ASC LIMIT %d", $wpdb->prefix . 'rsl_ie_jobs', (int) $state['floor'], 200 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Live job state.
		$out   = [];
		foreach ( (array) $rows as $row ) {
			if ( ! isset( $state['notified'][ (int) $row->id ] ) ) {
				$out[] = $row;
				if ( count( $out ) >= $limit ) {
					break;
				}
			}
		}
		return $out;
	}

	/**
	 * Record a job as handled.
	 *
	 * @param int $id Job ID.
	 */
	private function mark_notified( $id ) {
		$state                     = $this->state();
		$state['notified'][ $id ] = 1;
		if ( count( $state['notified'] ) > 5000 ) {
			$state['notified'] = array_slice( $state['notified'], -5000, null, true );
		}
		$this->store( self::STATE, $state );
	}

	/** Move the floor past finished jobs that are handled, so the lookup stays small. */
	private function advance_floor() {
		global $wpdb;
		$state  = $this->state();
		$floor  = (int) $state['floor'];
		$active = (int) $wpdb->get_var( $wpdb->prepare( "SELECT MIN(id) FROM %i WHERE id > %d AND status IN ('pending','processing','paused')", $wpdb->prefix . 'rsl_ie_jobs', $floor ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Live job state.
		$top    = $active ? $active - 1 : $this->max_job_id();
		if ( $top <= $floor ) {
			return;
		}
		$ids = $wpdb->get_col( $wpdb->prepare( "SELECT id FROM %i WHERE id > %d AND id <= %d AND status IN ('completed','failed','cancelled')", $wpdb->prefix . 'rsl_ie_jobs', $floor, $top ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Live job state.
		foreach ( (array) $ids as $id ) {
			if ( ! isset( $state['notified'][ (int) $id ] ) ) {
				return; // Something below the active job is still unhandled.
			}
		}
		foreach ( array_keys( $state['notified'] ) as $id ) {
			if ( (int) $id <= $top ) {
				unset( $state['notified'][ $id ] );
			}
		}
		$state['floor'] = $top;
		$this->store( self::STATE, $state );
	}

	/**
	 * Tracking state.
	 *
	 * @return array
	 */
	private function state() {
		$state = get_option( self::STATE, null );
		if ( ! is_array( $state ) || ! isset( $state['floor'] ) ) {
			$state = [
				'floor'    => $this->max_job_id(),
				'notified' => [],
			];
			$this->store( self::STATE, $state );
		}
		$state['notified'] = is_array( $state['notified'] ?? null ) ? $state['notified'] : [];
		return $state;
	}

	/**
	 * Highest job ID.
	 *
	 * @return int
	 */
	private function max_job_id() {
		global $wpdb;
		return (int) $wpdb->get_var( $wpdb->prepare( 'SELECT MAX(id) FROM %i', $wpdb->prefix . 'rsl_ie_jobs' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Live job state.
	}

	/**
	 * Requests that can finish jobs: cron, plugin AJAX, plugin REST and abilities, WP-CLI.
	 *
	 * @return bool
	 */
	private function relevant_request() {
		if ( wp_doing_cron() || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
			return true;
		}
		if ( wp_doing_ajax() ) {
			$action = filter_input( INPUT_POST, 'action', FILTER_UNSAFE_RAW ) ?: filter_input( INPUT_GET, 'action', FILTER_UNSAFE_RAW );
			return is_string( $action ) && 0 === strpos( $action, 'rsl_ie_' );
		}
		if ( defined( 'REST_REQUEST' ) && REST_REQUEST ) {
			$uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';
			return false !== strpos( $uri, 'rsl-ie' ) || false !== strpos( $uri, 'wp-abilities' ) || false !== strpos( $uri, 'mcp' );
		}
		return false;
	}

	/**
	 * Count against the hourly limit.
	 *
	 * @return bool
	 */
	private function within_rate_limit() {
		$key   = 'rsl_ie_notifications_hour_' . gmdate( 'YmdH' );
		$count = (int) get_transient( $key );
		if ( $count >= self::HOURLY_LIMIT ) {
			return false;
		}
		set_transient( $key, $count + 1, HOUR_IN_SECONDS + 60 );
		return true;
	}

	/**
	 * Queue another attempt for a failed channel: after 5, then 30 minutes.
	 *
	 * @param int    $job_id  Job.
	 * @param string $channel Channel.
	 * @param int    $attempt Next attempt.
	 */
	private function queue_retry( $job_id, $channel, $attempt ) {
		$queue   = get_option( self::RETRIES, [] );
		$queue   = is_array( $queue ) ? array_slice( $queue, -50 ) : [];
		$queue[] = [
			'job_id'  => $job_id,
			'channel' => $channel,
			'attempt' => $attempt,
			'due'     => time() + ( 2 === $attempt ? 5 * MINUTE_IN_SECONDS : 30 * MINUTE_IN_SECONDS ),
		];
		$this->store( self::RETRIES, $queue );
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( time() + 5 * MINUTE_IN_SECONDS, self::CRON_HOOK );
		}
	}

	/**
	 * Whether a retry is due.
	 *
	 * @return bool
	 */
	private function due_retries() {
		foreach ( (array) get_option( self::RETRIES, [] ) as $retry ) {
			if ( is_array( $retry ) && (int) $retry['due'] <= time() ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Remove and return due retries; schedule the next pending one.
	 *
	 * @return array
	 */
	private function take_due_retries() {
		$queue = get_option( self::RETRIES, [] );
		$due   = [];
		$keep  = [];
		foreach ( is_array( $queue ) ? $queue : [] as $retry ) {
			if ( ! is_array( $retry ) ) {
				continue;
			}
			if ( (int) $retry['due'] <= time() ) {
				$due[] = $retry;
			} else {
				$keep[] = $retry;
			}
		}
		if ( $due ) {
			$this->store( self::RETRIES, $keep );
		}
		if ( $keep && ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_single_event( min( array_column( $keep, 'due' ) ), self::CRON_HOOK );
		}
		return $due;
	}

	/**
	 * POST JSON with a short timeout. Outside local development, private and loopback
	 * addresses are refused (SSRF protection) and redirects are not followed.
	 *
	 * @param string $url     Target.
	 * @param string $body    JSON.
	 * @param array  $headers Extra headers.
	 * @return array|\WP_Error
	 */
	private function post( $url, $body, array $headers ) {
		$args = [
			'timeout'     => 5,
			'redirection' => 0,
			'headers'     => [ 'Content-Type' => 'application/json; charset=utf-8' ] + $headers,
			'body'        => $body,
			'user-agent'  => 'Import Export by RockStarLab/' . ( defined( 'RSL_IE_VERSION' ) ? RSL_IE_VERSION : '' ) . '; ' . home_url( '/' ),
		];
		return self::local_dev() ? wp_remote_post( $url, $args ) : wp_safe_remote_post( $url, $args );
	}

	/**
	 * Normalize an HTTP result.
	 *
	 * @param array|\WP_Error $response Response.
	 * @return array
	 */
	private function http_result( $response ) {
		if ( is_wp_error( $response ) ) {
			return [
				'ok'     => false,
				'detail' => 'http_error',
				'retry'  => true,
			];
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		return [
			'ok'     => $code >= 200 && $code < 300,
			'detail' => 'HTTP ' . $code,
			'retry'  => 429 === $code || $code >= 500,
		];
	}

	/**
	 * Append to the delivery log (no URLs, addresses or secrets).
	 *
	 * @param int    $job_id  Job, 0 for a test.
	 * @param string $channel Channel.
	 * @param array  $result  Result.
	 * @param int    $attempt Attempt.
	 */
	private function log( $job_id, $channel, array $result, $attempt = 1 ) {
		$log   = get_option( self::LOG, [] );
		$log   = is_array( $log ) ? $log : [];
		$log[] = [
			'time'    => gmdate( 'Y-m-d H:i:s' ),
			'job_id'  => (int) $job_id,
			'channel' => $channel,
			'ok'      => ! empty( $result['ok'] ),
			'detail'  => (string) ( $result['detail'] ?? '' ),
			'attempt' => (int) $attempt,
		];
		$this->store( self::LOG, array_slice( $log, -self::LOG_SIZE ) );
	}

	/**
	 * Slack incoming webhook URLs only.
	 *
	 * @param string $url URL.
	 * @return string|\WP_Error
	 */
	public static function validate_slack_url( $url ) {
		$parts = wp_parse_url( $url );
		if ( ! $parts || 'https' !== ( $parts['scheme'] ?? '' ) || 'hooks.slack.com' !== strtolower( $parts['host'] ?? '' ) || ! preg_match( '#^/(services|triggers|workflows)/[A-Za-z0-9/_-]+$#', $parts['path'] ?? '' ) ) {
			return new \WP_Error( 'rsl_ie_notification_slack', __( 'Use a Slack incoming webhook URL that starts with https://hooks.slack.com/.', 'import-export-by-rockstarlab' ), [ 'status' => 400 ] );
		}
		return esc_url_raw( $url, [ 'https' ] );
	}

	/**
	 * Public https URLs only (http and local hosts are allowed on local development sites).
	 *
	 * @param string $url URL.
	 * @return string|\WP_Error
	 */
	public static function validate_webhook_url( $url ) {
		$local = self::local_dev();
		$clean = esc_url_raw( $url, $local ? [ 'http', 'https' ] : [ 'https' ] );
		$parts = wp_parse_url( $clean );
		if ( '' === $clean || ! $parts || empty( $parts['host'] ) || ! empty( $parts['user'] ) || ! empty( $parts['pass'] ) || strlen( $clean ) > 2000 ) {
			return new \WP_Error( 'rsl_ie_notification_webhook', __( 'Enter a webhook URL that starts with https://.', 'import-export-by-rockstarlab' ), [ 'status' => 400 ] );
		}
		if ( ! $local && ! wp_http_validate_url( $clean ) ) {
			return new \WP_Error( 'rsl_ie_notification_webhook', __( 'This address points to a private or local network and cannot receive webhooks from this site.', 'import-export-by-rockstarlab' ), [ 'status' => 400 ] );
		}
		return $clean;
	}

	/**
	 * Local or development environment (wp_get_environment_type).
	 *
	 * @return bool
	 */
	private static function local_dev() {
		return in_array( wp_get_environment_type(), [ 'local', 'development' ], true );
	}

	/**
	 * Show only the host and the last characters of a secret URL.
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function mask_url( $url ) {
		if ( '' === (string) $url ) {
			return '';
		}
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		return ( 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) ? 'https://' : 'http://' ) . $host . '/…' . substr( (string) $url, -4 );
	}

	/**
	 * Boolean from form or JSON input.
	 *
	 * @param mixed $value Value.
	 * @return bool
	 */
	private static function bool( $value ) {
		return true === $value || 1 === $value || in_array( strtolower( (string) $value ), [ '1', 'true', 'on', 'yes' ], true );
	}

	/**
	 * Default subject.
	 *
	 * @return string
	 */
	private function default_subject() {
		return __( '[{site_name}] {job_type} #{job_id} {status}: {success} done, {failed} failed', 'import-export-by-rockstarlab' );
	}

	/**
	 * Default message.
	 *
	 * @return string
	 */
	private function default_message() {
		return __( "{job_type} #{job_id} {job_name} ({content_type}) {status} in {duration}.\nRows: {total} total, {success} done, {failed} failed, {skipped} skipped.\n\n{errors}\n\nJobs Log: {job_url}", 'import-export-by-rockstarlab' );
	}

	/**
	 * Save a non-autoloaded option.
	 *
	 * @param string $name  Option.
	 * @param mixed  $value Value.
	 */
	private function store( $name, $value ) {
		if ( false === get_option( $name, false ) ) {
			add_option( $name, $value, '', false );
			return;
		}
		update_option( $name, $value, false );
	}

	/**
	 * Error with status.
	 *
	 * @param string $code    Code suffix.
	 * @param string $message Message.
	 * @param int    $status  Status.
	 * @return \WP_Error
	 */
	private function error( $code, $message, $status = 400 ) {
		return new \WP_Error( 'rsl_ie_' . $code, $message, [ 'status' => $status ] );
	}
}

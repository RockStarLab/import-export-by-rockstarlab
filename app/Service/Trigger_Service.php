<?php
/**
 * Event triggers: run a saved import or export when something happens.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Execution_Context;
use RockStarLab\ImportExport\Abilities\Trigger_Catalog;

defined( 'ABSPATH' ) || exit;

/**
 * A trigger points at a finished import or export job (its "source") and runs a
 * fresh copy of it, as the administrator who created the trigger, when:
 *
 * - webhook:          an external system calls the trigger URL with its secret token;
 * - post_published:   a post of the chosen types is published;
 * - woo_order_created: a WooCommerce order is created;
 * - file_changed:     the file an import downloads from a URL has changed (checked hourly).
 *
 * Post and order events are debounced: many events within the delay start one job.
 * A trigger never starts a second job while its previous job is still running.
 */
class Trigger_Service {
	const OPTION          = 'rsl_ie_triggers';
	const LOG             = 'rsl_ie_triggers_log';
	const RUN_HOOK        = 'rsl_ie_run_trigger';
	const FILE_HOOK       = 'rsl_ie_check_trigger_files';
	const MAX_TRIGGERS    = 50;
	const LOG_SIZE        = 50;
	const MIN_INTERVAL    = 60;
	const AUTH_FAIL_LIMIT = 20;
	const EVENTS          = [ 'webhook', 'post_published', 'woo_order_created', 'file_changed' ];

	/** Register hooks. */
	public static function init() {
		$service = new self();
		add_action( self::RUN_HOOK, [ $service, 'run_scheduled' ] );
		add_action( self::FILE_HOOK, [ $service, 'check_files' ] );
		add_action( 'transition_post_status', [ $service, 'on_post_status' ], 20, 3 );
		add_action( 'woocommerce_new_order', [ $service, 'on_new_order' ], 20, 1 );
		add_action( 'rest_api_init', [ $service, 'register_routes' ] );
		add_action( 'admin_init', [ $service, 'maybe_schedule_file_check' ] );
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
		$definition = Trigger_Catalog::definitions()[ $name ] ?? null;
		if ( ! $definition ) {
			return $this->error( 'operation', __( 'Unknown trigger operation.', 'import-export-by-rockstarlab' ) );
		}
		$valid = rest_validate_value_from_schema( $input, $definition['input'], 'input' );
		if ( is_wp_error( $valid ) ) {
			$valid->add_data( [ 'status' => 400 ] );
			return $valid;
		}
		switch ( $name ) {
			case 'trigger-list':
				return [ 'triggers' => array_values( array_map( [ $this, 'public_view' ], $this->all() ) ) ];
			case 'trigger-get':
				$trigger = $this->get( $input['trigger_id'] );
				return is_wp_error( $trigger ) ? $trigger : $this->public_view( $trigger );
			case 'trigger-create':
				return $this->create( $input );
			case 'trigger-update':
				return $this->update( $input['trigger_id'], $input );
			case 'trigger-delete':
				return $this->delete( $input['trigger_id'] );
			case 'trigger-run':
				$result = $this->run( $input['trigger_id'], 'manual' );
				return is_wp_error( $result ) ? $result : [
					'job_id'     => (int) $result['job_id'],
					'trigger_id' => $input['trigger_id'],
				];
			case 'trigger-rotate-token':
				return $this->rotate_token( $input['trigger_id'] );
			case 'trigger-log':
				return [ 'entries' => $this->log_entries() ];
			case 'trigger-sources':
				return [ 'sources' => $this->sources() ];
			case 'trigger-n8n-workflow':
				$workflow = $this->n8n_workflow( $input['trigger_id'] );
				return is_wp_error( $workflow ) ? $workflow : [
					'workflow'      => json_decode( wp_json_encode( $workflow ), true ),
					'workflow_json' => (string) wp_json_encode( $workflow, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ),
				];
		}
		return $this->error( 'operation', __( 'Unknown trigger operation.', 'import-export-by-rockstarlab' ) );
	}

	/**
	 * Create a trigger. A webhook trigger returns its token once, in "token".
	 *
	 * @param array $input name, source_job_id, event, post_types, delay.
	 * @return array|\WP_Error
	 */
	public function create( array $input ) {
		$all = $this->all();
		if ( count( $all ) >= self::MAX_TRIGGERS ) {
			return $this->error( 'trigger_limit', __( 'You have reached the limit of triggers. Delete one you no longer need.', 'import-export-by-rockstarlab' ), 409 );
		}
		$event = (string) ( $input['event'] ?? '' );
		if ( ! in_array( $event, self::EVENTS, true ) ) {
			return $this->error( 'trigger_event', __( 'Choose what starts the job.', 'import-export-by-rockstarlab' ) );
		}
		$source = $this->validate_source( (int) ( $input['source_job_id'] ?? 0 ), $event );
		if ( is_wp_error( $source ) ) {
			return $source;
		}
		$now     = gmdate( 'Y-m-d H:i:s' );
		$id      = 'trg_' . substr( md5( wp_generate_uuid4() ), 0, 12 );
		$trigger = [
			'trigger_id'    => $id,
			'name'          => $this->clean_name( $input['name'] ?? '', $source ),
			'source_job_id' => (int) $source->id,
			'event'         => $event,
			'post_types'    => 'post_published' === $event ? $this->clean_post_types( $input['post_types'] ?? [ 'post' ] ) : [],
			'delay'         => $this->clean_delay( $input['delay'] ?? null, $event ),
			'status'        => 'active',
			'created_by'    => get_current_user_id(),
			'token_hash'    => '',
			'file_state'    => [],
			'pending_since' => '',
			'last_run_at'   => '',
			'last_job_id'   => 0,
			'last_result'   => '',
			'runs'          => 0,
			'jobs'          => [],
			'allow_file_url' => false,
			'created_at'    => $now,
			'updated_at'    => $now,
		];
		if ( 'post_published' === $event && ! $trigger['post_types'] ) {
			return $this->error( 'trigger_post_types', __( 'Choose at least one post type.', 'import-export-by-rockstarlab' ) );
		}
		if ( 'woo_order_created' === $event && ! class_exists( 'WooCommerce' ) ) {
			return $this->error( 'trigger_woocommerce', __( 'WooCommerce is not active on this site.', 'import-export-by-rockstarlab' ) );
		}
		if ( ! empty( $input['allow_file_url'] ) ) {
			if ( 'webhook' !== $event || 'import' !== $source->type ) {
				return $this->error( 'trigger_file_url', __( 'Only webhook triggers of imports can accept a file URL.', 'import-export-by-rockstarlab' ) );
			}
			$trigger['allow_file_url'] = true;
		}
		$token = '';
		if ( 'webhook' === $event ) {
			$token                 = $this->new_token();
			$trigger['token_hash'] = $this->hash_token( $token );
		}
		$all[ $id ] = $trigger;
		$this->store( self::OPTION, $all );
		if ( 'file_changed' === $event ) {
			$this->ensure_file_check();
		}
		$out = $this->public_view( $trigger );
		if ( '' !== $token ) {
			$out['token'] = $token;
		}
		return $out;
	}

	/**
	 * Change name, status, post types or delay.
	 *
	 * @param string $id    Trigger ID.
	 * @param array  $input Changes.
	 * @return array|\WP_Error
	 */
	public function update( $id, array $input ) {
		$all = $this->all();
		if ( ! isset( $all[ $id ] ) ) {
			return $this->not_found();
		}
		$t = $all[ $id ];
		if ( isset( $input['name'] ) ) {
			$t['name'] = $this->clean_name( $input['name'], null, $t['name'] );
		}
		if ( isset( $input['status'] ) ) {
			if ( ! in_array( $input['status'], [ 'active', 'paused' ], true ) ) {
				return $this->error( 'trigger_status', __( 'A trigger is either active or paused.', 'import-export-by-rockstarlab' ) );
			}
			$t['status'] = $input['status'];
			if ( 'paused' === $t['status'] ) {
				wp_clear_scheduled_hook( self::RUN_HOOK, [ $id ] );
				$t['pending_since'] = '';
			}
		}
		if ( isset( $input['post_types'] ) && 'post_published' === $t['event'] ) {
			$types = $this->clean_post_types( $input['post_types'] );
			if ( ! $types ) {
				return $this->error( 'trigger_post_types', __( 'Choose at least one post type.', 'import-export-by-rockstarlab' ) );
			}
			$t['post_types'] = $types;
		}
		if ( isset( $input['allow_file_url'] ) ) {
			$source = rsl_ie()->Model->job->find( (int) $t['source_job_id'] );
			if ( $input['allow_file_url'] && ( 'webhook' !== $t['event'] || ! $source || 'import' !== $source->type ) ) {
				return $this->error( 'trigger_file_url', __( 'Only webhook triggers of imports can accept a file URL.', 'import-export-by-rockstarlab' ) );
			}
			$t['allow_file_url'] = (bool) $input['allow_file_url'];
		}
		if ( isset( $input['delay'] ) ) {
			$t['delay'] = $this->clean_delay( $input['delay'], $t['event'] );
		}
		$t['updated_at'] = gmdate( 'Y-m-d H:i:s' );
		$all[ $id ]      = $t;
		$this->store( self::OPTION, $all );
		return $this->public_view( $t );
	}

	/**
	 * Delete a trigger and its queued run.
	 *
	 * @param string $id Trigger ID.
	 * @return array|\WP_Error
	 */
	public function delete( $id ) {
		$all = $this->all();
		if ( ! isset( $all[ $id ] ) ) {
			return $this->not_found();
		}
		unset( $all[ $id ] );
		$this->store( self::OPTION, $all );
		wp_clear_scheduled_hook( self::RUN_HOOK, [ $id ] );
		return [
			'deleted'    => true,
			'trigger_id' => $id,
		];
	}

	/**
	 * Replace the token of a webhook trigger; the old one stops working at once.
	 *
	 * @param string $id Trigger ID.
	 * @return array|\WP_Error
	 */
	public function rotate_token( $id ) {
		$all = $this->all();
		if ( ! isset( $all[ $id ] ) ) {
			return $this->not_found();
		}
		if ( 'webhook' !== $all[ $id ]['event'] ) {
			return $this->error( 'trigger_not_webhook', __( 'Only webhook triggers have a token.', 'import-export-by-rockstarlab' ) );
		}
		$token                     = $this->new_token();
		$all[ $id ]['token_hash'] = $this->hash_token( $token );
		$all[ $id ]['updated_at'] = gmdate( 'Y-m-d H:i:s' );
		$this->store( self::OPTION, $all );
		return $this->public_view( $all[ $id ] ) + [ 'token' => $token ];
	}

	/**
	 * Read one trigger (internal view).
	 *
	 * @param string $id Trigger ID.
	 * @return array|\WP_Error
	 */
	public function get( $id ) {
		$all = $this->all();
		return $all[ (string) $id ] ?? $this->not_found();
	}

	/**
	 * Start the source job now, unless the trigger is paused, its last job is still
	 * running, or it ran less than a minute ago (webhook and manual runs).
	 *
	 * @param string $id     Trigger ID.
	 * @param string $reason manual|webhook|event|file.
	 * @param string $file_url Webhook only: import this file instead of the job's own.
	 * @return array|\WP_Error job_id on success.
	 */
	public function run( $id, $reason, $file_url = '' ) {
		$lock = Job_Lock::acquire_resource( 'trigger', $id );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return $this->error( 'trigger_busy', __( 'This trigger is starting a job right now. Try again in a moment.', 'import-export-by-rockstarlab' ), 409 );
		}
		try {
			$all = $this->all();
			if ( ! isset( $all[ $id ] ) ) {
				return $this->not_found();
			}
			$t = $all[ $id ];
			if ( 'active' !== $t['status'] && 'manual' !== $reason ) {
				return $this->finish( $t, $reason, $this->error( 'trigger_paused', __( 'This trigger is paused.', 'import-export-by-rockstarlab' ), 409 ) );
			}
			$last = $t['last_job_id'] ? rsl_ie()->Model->job->find( (int) $t['last_job_id'] ) : null;
			if ( $last && in_array( $last->status, [ 'pending', 'processing', 'paused' ], true ) ) {
				return $this->finish( $t, $reason, $this->error( 'trigger_overlap', __( 'The previous job of this trigger is still running.', 'import-export-by-rockstarlab' ), 409 ) );
			}
			if ( in_array( $reason, [ 'webhook', 'webhook_file', 'manual' ], true ) && $t['last_run_at'] && time() - strtotime( $t['last_run_at'] . ' UTC' ) < self::MIN_INTERVAL ) {
				return $this->finish( $t, $reason, $this->error( 'trigger_too_soon', __( 'This trigger ran less than a minute ago. Try again later.', 'import-export-by-rockstarlab' ), 429 ) );
			}
			$context = Execution_Context::from_storage( (int) $t['created_by'], get_current_blog_id() );
			$result  = is_wp_error( $context ) ? $context : $context->run(
				static function () use ( $t, $file_url ) {
					return ( new Schedule_Source_Service() )->execute( (int) $t['source_job_id'], 'trigger:' . $t['trigger_id'] . ':' . wp_generate_uuid4(), '', (string) $file_url );
				}
			);
			if ( ! is_wp_error( $result ) && empty( $result['job_id'] ) ) {
				$result = $this->error( 'trigger_no_job', __( 'The source job could not be started.', 'import-export-by-rockstarlab' ), 500 );
			}
			return $this->finish( $t, $reason, $result );
		} finally {
			Job_Lock::release( $lock );
		}
	}

	/**
	 * Cron entry for debounced post/order events.
	 *
	 * @param string $id Trigger ID.
	 */
	public function run_scheduled( $id ) {
		$all = $this->all();
		if ( isset( $all[ $id ] ) ) {
			$all[ $id ]['pending_since'] = '';
			$this->store( self::OPTION, $all );
			$result = $this->run( $id, 'event' );
			if ( is_wp_error( $result ) && 'rsl_ie_trigger_overlap' === $result->get_error_code() ) {
				// Run again after the current job, so the new posts or orders are not missed.
				wp_schedule_single_event( time() + max( 60, (int) $all[ $id ]['delay'] ), self::RUN_HOOK, [ $id ] );
			}
		}
	}

	/**
	 * A post was published: queue the matching triggers.
	 *
	 * @param string   $new_status New status.
	 * @param string   $old_status Old status.
	 * @param \WP_Post $post       Post.
	 */
	public function on_post_status( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status || ! $post instanceof \WP_Post || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}
		if ( self::inside_job() ) {
			return; // Published by an import, sync or migration: never let a job start itself again.
		}
		foreach ( $this->all() as $t ) {
			if ( 'active' === $t['status'] && 'post_published' === $t['event'] && in_array( $post->post_type, $t['post_types'], true ) ) {
				$this->queue( $t['trigger_id'] );
			}
		}
	}

	/**
	 * A WooCommerce order was created: queue the matching triggers.
	 *
	 * @param int $order_id Order ID.
	 */
	public function on_new_order( $order_id ) {
		if ( self::inside_job() ) {
			return;
		}
		foreach ( $this->all() as $t ) {
			if ( 'active' === $t['status'] && 'woo_order_created' === $t['event'] ) {
				$this->queue( $t['trigger_id'] );
			}
		}
	}

	/**
	 * Hourly: run file triggers whose remote file changed since the last check.
	 *
	 * @return array Trigger ID => changed|unchanged|baseline|error code.
	 */
	public function check_files() {
		$out = [];
		foreach ( $this->all() as $t ) {
			if ( 'active' !== $t['status'] || 'file_changed' !== $t['event'] ) {
				continue;
			}
			$url = $this->source_url( (int) $t['source_job_id'] );
			if ( '' === $url ) {
				$out[ $t['trigger_id'] ] = 'no_url';
				continue;
			}
			$state = $this->file_state( $url );
			if ( is_wp_error( $state ) ) {
				$out[ $t['trigger_id'] ] = $state->get_error_code();
				$this->log( $t, 'file', $state );
				continue;
			}
			$all = $this->all();
			if ( ! isset( $all[ $t['trigger_id'] ] ) ) {
				continue;
			}
			$before = $all[ $t['trigger_id'] ]['file_state'];
			if ( $before === $state ) {
				$out[ $t['trigger_id'] ] = 'unchanged';
				continue;
			}
			if ( ! $before ) {
				$this->save_file_state( $t['trigger_id'], $state );
				$out[ $t['trigger_id'] ] = 'baseline';
				continue;
			}
			$result = $this->run( $t['trigger_id'], 'file' );
			if ( is_wp_error( $result ) ) {
				// Keep the old fingerprint so the next check tries again (e.g. the previous job was still running).
				$out[ $t['trigger_id'] ] = $result->get_error_code();
				continue;
			}
			$this->save_file_state( $t['trigger_id'], $state );
			$out[ $t['trigger_id'] ] = 'changed';
		}
		return $out;
	}

	/**
	 * Whether the current request is running a plugin job (import batch, sync, migration).
	 *
	 * @return bool
	 */
	public static function inside_job() {
		return Import_Service::is_processing() || Job_Lock::holds_any_job();
	}

	/**
	 * Remember the fingerprint of a trigger's file.
	 *
	 * @param string $id    Trigger ID.
	 * @param array  $state Fingerprint.
	 */
	private function save_file_state( $id, array $state ) {
		$all = $this->all();
		if ( isset( $all[ $id ] ) ) {
			$all[ $id ]['file_state'] = $state;
			$this->store( self::OPTION, $all );
		}
	}

	/** REST route for webhook triggers. */
	public function register_routes() {
		register_rest_route(
			'rsl-ie/v1',
			'/triggers/(?P<trigger_id>trg_[a-f0-9]{12})/run',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'rest_run' ],
				'permission_callback' => '__return_true', // The secret token is checked in the callback.
				'args'                => [
					'trigger_id' => [
						'type'    => 'string',
						'pattern' => '^trg_[a-f0-9]{12}$',
					],
				],
			]
		);
		register_rest_route(
			'rsl-ie/v1',
			'/triggers/(?P<trigger_id>trg_[a-f0-9]{12})/jobs/(?P<job_id>[0-9]{1,12})',
			[
				'methods'             => 'GET',
				'callback'            => [ $this, 'rest_job' ],
				'permission_callback' => '__return_true', // The secret token is checked in the callback.
			]
		);
	}

	/**
	 * Run a webhook trigger. The token comes in the X-RSL-Token header or as a
	 * Bearer token, never in the URL, so it does not end up in server logs.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_run( \WP_REST_Request $request ) {
		$t = $this->authenticate( $request );
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		$id       = $t['trigger_id'];
		$file_url = $request->get_param( 'file_url' );
		if ( null !== $file_url && '' !== $file_url ) {
			if ( ! is_string( $file_url ) || strlen( $file_url ) > 2000 || ! preg_match( '#^https?://#i', $file_url ) ) {
				return $this->error( 'trigger_file_url', __( 'file_url must be a full http:// or https:// address.', 'import-export-by-rockstarlab' ), 400 );
			}
			if ( empty( $t['allow_file_url'] ) ) {
				return $this->error( 'trigger_file_url_off', __( 'This trigger does not accept a file URL. Turn on "Accept a file URL in the request" for it.', 'import-export-by-rockstarlab' ), 400 );
			}
		} else {
			$file_url = '';
		}
		$result = $this->run( $id, '' !== $file_url ? 'webhook_file' : 'webhook', $file_url );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return new \WP_REST_Response(
			[
				'accepted'   => true,
				'trigger_id' => $id,
				'job_id'     => (int) $result['job_id'],
			],
			202
		);
	}

	/**
	 * Status of a job this webhook trigger started, for Zapier, Make, n8n and scripts.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function rest_job( \WP_REST_Request $request ) {
		$t = $this->authenticate( $request );
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		$job_id = (int) $request['job_id'];
		$job    = in_array( $job_id, array_map( 'intval', (array) $t['jobs'] ), true ) ? rsl_ie()->Model->job->find( $job_id ) : null;
		if ( ! $job ) {
			return $this->error( 'trigger_job_not_found', __( 'This trigger did not start that job, or it was deleted.', 'import-export-by-rockstarlab' ), 404 );
		}
		return new \WP_REST_Response( self::job_summary( $job ), 200 );
	}

	/**
	 * Counts of a job, without file paths, settings or row values.
	 *
	 * @param object $job Job row.
	 * @return array
	 */
	public static function job_summary( $job ) {
		$result = json_decode( (string) $job->result, true );
		$result = is_array( $result ) ? $result : [];
		$errors = isset( $result['errors'] ) && is_array( $result['errors'] ) ? $result['errors'] : [];
		return [
			'job_id'    => (int) $job->id,
			'type'      => (string) $job->type,
			'status'    => (string) $job->status,
			'finished'  => in_array( $job->status, [ 'completed', 'failed', 'cancelled' ], true ),
			'total'     => (int) $job->total_items,
			'processed' => (int) $job->processed_items,
			'created'   => (int) ( $result['created'] ?? 0 ),
			'updated'   => (int) ( $result['updated'] ?? 0 ),
			'exported'  => (int) ( $result['exported'] ?? 0 ),
			'skipped'   => (int) ( $result['skipped'] ?? $job->skipped_items ),
			'failed'    => (int) ( $result['failed'] ?? $job->failed_items ),
			'errors'    => count( $errors ),
		];
	}

	/**
	 * Check the token of a webhook trigger request (header only) with a per-address
	 * limit on failures.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return array|\WP_Error Trigger.
	 */
	private function authenticate( \WP_REST_Request $request ) {
		$ip_key = 'rsl_ie_trigger_auth_' . md5( (string) ( $_SERVER['REMOTE_ADDR'] ?? '' ) . gmdate( 'YmdH' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Hashed for a counter only.
		if ( (int) get_transient( $ip_key ) >= self::AUTH_FAIL_LIMIT ) {
			return $this->error( 'trigger_auth_limit', __( 'Too many failed attempts. Try again later.', 'import-export-by-rockstarlab' ), 429 );
		}
		$token = (string) $request->get_header( 'x_rsl_token' );
		if ( '' === $token ) {
			$auth = (string) $request->get_header( 'authorization' );
			if ( 0 === stripos( $auth, 'Bearer ' ) ) {
				$token = trim( substr( $auth, 7 ) );
			}
		}
		$t = $this->all()[ (string) $request['trigger_id'] ] ?? null;
		if ( ! $t || 'webhook' !== $t['event'] || '' === $token || '' === $t['token_hash'] || ! hash_equals( $t['token_hash'], $this->hash_token( $token ) ) ) {
			set_transient( $ip_key, (int) get_transient( $ip_key ) + 1, HOUR_IN_SECONDS );
			return $this->error( 'trigger_unauthorized', __( 'Unknown trigger or wrong token.', 'import-export-by-rockstarlab' ), 401 );
		}
		return $t;
	}

	/**
	 * Finished import and export jobs a trigger can start.
	 *
	 * @return array
	 */
	public function sources() {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, job_name, type, data_type, status, parameters, file_path, created_at FROM %i WHERE type IN ('import','export') AND status IN ('completed','failed','cancelled') ORDER BY id DESC LIMIT %d", $wpdb->prefix . 'rsl_ie_jobs', 200 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Live job list.
		$out     = [];
		$checked = 0;
		foreach ( (array) $rows as $row ) {
			$params = json_decode( (string) $row->parameters, true );
			$params = is_array( $params ) ? $params : [];
			if ( ! empty( $params['migration_parent_id'] ) || in_array( $row->data_type, [ 'media_sync', 'ai_url', 'content_update' ], true ) ) {
				continue;
			}
			$from_url = '' !== (string) ( $params['source_url'] ?? '' );
			// An uploaded file that was cleaned up cannot be imported again.
			if ( 'import' === $row->type && ! $from_url && ( '' === (string) $row->file_path || ! file_exists( (string) $row->file_path ) ) ) {
				continue;
			}
			// Same check as schedules: only jobs that can really be started again.
			if ( ++$checked > 100 || is_wp_error( ( new Schedule_Source_Service() )->execute( (int) $row->id ) ) ) {
				continue;
			}
			$out[] = [
				'job_id'       => (int) $row->id,
				'label'        => self::source_label( $row ),
				'type'         => (string) $row->type,
				'content_type' => (string) $row->data_type,
				'from_url'     => $from_url,
				'created_at'   => (string) $row->created_at,
			];
			if ( count( $out ) >= 50 ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * "#12 Import · Products — Weekly prices" style label.
	 *
	 * @param object $job Job row.
	 * @return string
	 */
	public static function source_label( $job ) {
		$object = post_type_exists( (string) $job->data_type ) ? get_post_type_object( (string) $job->data_type ) : null;
		$what   = $object ? $object->labels->name : ucwords( str_replace( [ 'woo_', '_' ], [ 'WooCommerce ', ' ' ], (string) $job->data_type ) );
		$label  = sprintf( '#%d %s · %s', (int) $job->id, 'export' === $job->type ? __( 'Export', 'import-export-by-rockstarlab' ) : __( 'Import', 'import-export-by-rockstarlab' ), $what );
		return '' !== (string) $job->job_name ? $label . ' — ' . $job->job_name : $label;
	}

	/**
	 * Ready-to-import n8n workflow for a webhook trigger: start the job, wait, and
	 * check the result until it has finished. The token is a placeholder; it is
	 * never written into the file.
	 *
	 * @param string $id Trigger ID.
	 * @return array|\WP_Error
	 */
	public function n8n_workflow( $id ) {
		$t = $this->get( $id );
		if ( is_wp_error( $t ) ) {
			return $t;
		}
		if ( 'webhook' !== $t['event'] ) {
			return $this->error( 'trigger_not_webhook', __( 'Only webhook triggers have a URL to call.', 'import-export-by-rockstarlab' ) );
		}
		$run     = rest_url( 'rsl-ie/v1/triggers/' . $t['trigger_id'] . '/run' );
		$jobs    = rest_url( 'rsl-ie/v1/triggers/' . $t['trigger_id'] . '/jobs/' );
		$headers = [
			'parameters' => [
				[
					'name'  => 'X-RSL-Token',
					'value' => 'PASTE-THE-TRIGGER-TOKEN-HERE',
				],
			],
		];
		$start   = [
			'method'           => 'POST',
			'url'              => $run,
			'sendHeaders'      => true,
			'headerParameters' => $headers,
			'options'          => new \stdClass(),
		];
		if ( ! empty( $t['allow_file_url'] ) ) {
			$start['sendBody']    = true;
			$start['specifyBody'] = 'json';
			$start['jsonBody']    = '{ "file_url": "https://example.com/files/import.csv" }';
		}
		$node = static function ( $name, $type, $version, array $parameters, $x ) {
			return [
				'parameters'  => $parameters ? $parameters : new \stdClass(),
				'id'          => wp_generate_uuid4(),
				'name'        => $name,
				'type'        => $type,
				'typeVersion' => $version,
				'position'    => [ $x, 0 ],
			];
		};
		$wait            = $node( 'Wait 30 seconds', 'n8n-nodes-base.wait', 1.1, [ 'amount' => 30 ], 440 );
		$wait['webhookId'] = wp_generate_uuid4();
		$link            = static function ( $to ) {
			return [
				'node'  => $to,
				'type'  => 'main',
				'index' => 0,
			];
		};
		return [
			/* translators: %s: trigger name. */
			'name'        => sprintf( __( 'WordPress: %s', 'import-export-by-rockstarlab' ), $t['name'] ),
			'nodes'       => [
				$node( 'Start', 'n8n-nodes-base.manualTrigger', 1, [], 0 ),
				$node( 'Start the job', 'n8n-nodes-base.httpRequest', 4.2, $start, 220 ),
				$wait,
				$node(
					'Check the job',
					'n8n-nodes-base.httpRequest',
					4.2,
					[
						'url'              => '=' . $jobs . "{{ \$('Start the job').item.json.job_id }}",
						'sendHeaders'      => true,
						'headerParameters' => $headers,
						'options'          => new \stdClass(),
					],
					660
				),
				$node(
					'Finished?',
					'n8n-nodes-base.if',
					2,
					[
						'conditions' => [
							'options'    => [
								'caseSensitive'  => true,
								'leftValue'      => '',
								'typeValidation' => 'strict',
							],
							'conditions' => [
								[
									'id'         => wp_generate_uuid4(),
									'leftValue'  => '={{ $json.finished }}',
									'rightValue' => true,
									'operator'   => [
										'type'      => 'boolean',
										'operation' => 'true',
										'singleValue' => true,
									],
								],
							],
							'combinator' => 'and',
						],
						'options'    => new \stdClass(),
					],
					880
				),
			],
			'connections' => [
				'Start'         => [ 'main' => [ [ $link( 'Start the job' ) ] ] ],
				'Start the job' => [ 'main' => [ [ $link( 'Wait 30 seconds' ) ] ] ],
				'Wait 30 seconds' => [ 'main' => [ [ $link( 'Check the job' ) ] ] ],
				'Check the job' => [ 'main' => [ [ $link( 'Finished?' ) ] ] ],
				'Finished?'     => [ 'main' => [ [], [ $link( 'Wait 30 seconds' ) ] ] ],
			],
			'settings'    => [ 'executionOrder' => 'v1' ],
			'pinData'     => new \stdClass(),
		];
	}

	/**
	 * Recent trigger activity, newest first.
	 *
	 * @return array
	 */
	public function log_entries() {
		$log = get_option( self::LOG, [] );
		return array_reverse( is_array( $log ) ? $log : [] );
	}

	/**
	 * Trigger as shown to administrators and agents: no token or hash.
	 *
	 * @param array $t Trigger.
	 * @return array
	 */
	public function public_view( array $t ) {
		$job = rsl_ie()->Model->job->find( (int) $t['source_job_id'] );
		return [
			'trigger_id'    => $t['trigger_id'],
			'name'          => $t['name'],
			'event'         => $t['event'],
			'source_job_id' => (int) $t['source_job_id'],
			'source'        => $job ? self::source_label( $job ) : '',
			'source_type'   => $job ? (string) $job->type : '',
			'post_types'    => array_values( $t['post_types'] ),
			'delay'         => (int) $t['delay'],
			'status'        => $t['status'],
			'webhook_url'   => 'webhook' === $t['event'] ? rest_url( 'rsl-ie/v1/triggers/' . $t['trigger_id'] . '/run' ) : '',
			'pending'       => '' !== $t['pending_since'],
			'last_run_at'   => $t['last_run_at'],
			'last_job_id'   => (int) $t['last_job_id'],
			'last_result'   => $t['last_result'],
			'runs'          => (int) $t['runs'],
			'allow_file_url' => ! empty( $t['allow_file_url'] ),
			'created_at'    => $t['created_at'],
		];
	}

	/**
	 * Queue a debounced run: the first event schedules it, later ones join it.
	 *
	 * @param string $id Trigger ID.
	 */
	private function queue( $id ) {
		if ( wp_next_scheduled( self::RUN_HOOK, [ $id ] ) ) {
			return;
		}
		$all = $this->all();
		if ( ! isset( $all[ $id ] ) ) {
			return;
		}
		wp_schedule_single_event( time() + (int) $all[ $id ]['delay'], self::RUN_HOOK, [ $id ] );
		$all[ $id ]['pending_since'] = gmdate( 'Y-m-d H:i:s' );
		$this->store( self::OPTION, $all );
	}

	/**
	 * Record a run result on the trigger and in the log.
	 *
	 * @param array           $t      Trigger.
	 * @param string          $reason Reason.
	 * @param array|\WP_Error $result Result.
	 * @return array|\WP_Error The result.
	 */
	private function finish( array $t, $reason, $result ) {
		$all = $this->all();
		if ( isset( $all[ $t['trigger_id'] ] ) ) {
			$x                = &$all[ $t['trigger_id'] ];
			$x['last_result'] = is_wp_error( $result ) ? $result->get_error_code() : 'started';
			if ( ! is_wp_error( $result ) ) {
				$x['last_run_at'] = gmdate( 'Y-m-d H:i:s' );
				$x['last_job_id'] = (int) $result['job_id'];
				$x['runs']        = (int) $x['runs'] + 1;
				$x['jobs']        = array_slice( array_merge( (array) $x['jobs'], [ (int) $result['job_id'] ] ), -20 );
			}
			unset( $x );
			$this->store( self::OPTION, $all );
		}
		$this->log( $t, $reason, $result );
		return $result;
	}

	/**
	 * Append to the activity log.
	 *
	 * @param array           $t      Trigger.
	 * @param string          $reason Reason.
	 * @param array|\WP_Error $result Result.
	 */
	private function log( array $t, $reason, $result ) {
		$log   = get_option( self::LOG, [] );
		$log   = is_array( $log ) ? $log : [];
		$log[] = [
			'time'       => gmdate( 'Y-m-d H:i:s' ),
			'trigger_id' => $t['trigger_id'],
			'name'       => $t['name'],
			'reason'     => $reason,
			'ok'         => ! is_wp_error( $result ),
			'job_id'     => is_wp_error( $result ) ? 0 : (int) ( $result['job_id'] ?? 0 ),
			'detail'     => is_wp_error( $result ) ? $result->get_error_code() : 'started',
		];
		$this->store( self::LOG, array_slice( $log, -self::LOG_SIZE ) );
	}

	/**
	 * Source must be a finished, independent import or export the current user may run.
	 *
	 * @param int    $job_id Job ID.
	 * @param string $event  Event.
	 * @return object|\WP_Error Job.
	 */
	private function validate_source( $job_id, $event ) {
		$job = rsl_ie()->Model->job->find( $job_id );
		if ( ! $job ) {
			return $this->error( 'trigger_source', __( 'Choose a finished import or export to run.', 'import-export-by-rockstarlab' ), 404 );
		}
		$params = json_decode( (string) $job->parameters, true );
		if ( ! in_array( $job->type, [ 'import', 'export' ], true ) || in_array( $job->data_type, [ 'media_sync', 'ai_url', 'content_update' ], true ) || ! empty( $params['migration_parent_id'] ) ) {
			return $this->error( 'trigger_source', __( 'Triggers can run file imports and exports only.', 'import-export-by-rockstarlab' ) );
		}
		if ( ! in_array( $job->status, [ 'completed', 'failed', 'cancelled' ], true ) ) {
			return $this->error( 'trigger_source', __( 'Choose a job that has finished.', 'import-export-by-rockstarlab' ), 409 );
		}
		if ( 'file_changed' === $event && ( 'import' !== $job->type || '' === (string) ( $params['source_url'] ?? '' ) ) ) {
			return $this->error( 'trigger_source', __( '"When the file changes" needs an import that downloads its file from a URL.', 'import-export-by-rockstarlab' ) );
		}
		$check = ( new Schedule_Source_Service() )->execute( (int) $job->id );
		if ( is_wp_error( $check ) ) {
			return $check;
		}
		return $job;
	}

	/**
	 * URL an import downloads its file from.
	 *
	 * @param int $job_id Job.
	 * @return string
	 */
	private function source_url( $job_id ) {
		$job = rsl_ie()->Model->job->find( $job_id );
		if ( ! $job ) {
			return '';
		}
		$params = json_decode( (string) $job->parameters, true );
		return is_array( $params ) ? (string) ( $params['source_url'] ?? '' ) : '';
	}

	/**
	 * Cheap fingerprint of a remote file: ETag, Last-Modified and size from a HEAD
	 * request, or a hash of the body when the server sends none of them.
	 *
	 * @param string $url URL.
	 * @return array|\WP_Error
	 */
	private function file_state( $url ) {
		if ( Remote_File_Service::is_remote( $url ) ) {
			// Files on a saved FTP/SFTP server: size and modification time.
			$state = ( new Remote_File_Service() )->state( $url );
			return is_wp_error( $state ) ? $this->error( 'trigger_file_unreachable', $state->get_error_message(), 502 ) : $state;
		}
		$args = [
			'timeout'     => 15,
			'redirection' => 3,
		];
		// Same rule as the URL import itself: development hosts (*.local, *.test) are
		// allowed, everything else goes through the safe HTTP API (no private addresses).
		$local = (bool) preg_match( '/\.(local|test|localhost)$/i', (string) wp_parse_url( $url, PHP_URL_HOST ) );
		if ( $local ) {
			$args['reject_unsafe_urls'] = false;
		}
		$response = $local ? wp_remote_head( $url, $args ) : wp_safe_remote_head( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $this->error( 'trigger_file_unreachable', $response->get_error_message(), 502 );
		}
		$code = (int) wp_remote_retrieve_response_code( $response );
		if ( $code < 200 || $code >= 300 ) {
			return $this->error( 'trigger_file_http', 'HTTP ' . $code, 502 );
		}
		$state = array_filter(
			[
				'etag'          => (string) wp_remote_retrieve_header( $response, 'etag' ),
				'last_modified' => (string) wp_remote_retrieve_header( $response, 'last-modified' ),
				'length'        => (string) wp_remote_retrieve_header( $response, 'content-length' ),
			],
			'strlen'
		);
		if ( isset( $state['etag'] ) || isset( $state['last_modified'] ) ) {
			return $state;
		}
		$args['limit_response_size'] = 50 * MB_IN_BYTES;
		$response                    = $local ? wp_remote_get( $url, $args ) : wp_safe_remote_get( $url, $args );
		if ( is_wp_error( $response ) ) {
			return $this->error( 'trigger_file_unreachable', $response->get_error_message(), 502 );
		}
		return [ 'sha256' => hash( 'sha256', (string) wp_remote_retrieve_body( $response ) ) ];
	}

	/** Re-create the hourly file check if it went missing (e.g. after a cron reset). */
	public function maybe_schedule_file_check() {
		if ( wp_next_scheduled( self::FILE_HOOK ) ) {
			return;
		}
		foreach ( $this->all() as $t ) {
			if ( 'file_changed' === $t['event'] && 'active' === $t['status'] ) {
				$this->ensure_file_check();
				return;
			}
		}
	}

	/** Make sure the hourly file check is scheduled. */
	private function ensure_file_check() {
		if ( ! wp_next_scheduled( self::FILE_HOOK ) ) {
			wp_schedule_event( time() + 300, 'hourly', self::FILE_HOOK );
		}
	}

	/**
	 * All triggers keyed by ID with defaults filled in.
	 *
	 * @return array
	 */
	private function all() {
		$raw = get_option( self::OPTION, [] );
		$out = [];
		foreach ( is_array( $raw ) ? $raw : [] as $id => $t ) {
			if ( is_array( $t ) && isset( $t['trigger_id'], $t['event'], $t['source_job_id'] ) && $id === $t['trigger_id'] ) {
				$out[ $id ] = $t + [
					'post_types'    => [],
					'delay'         => 0,
					'status'        => 'active',
					'token_hash'    => '',
					'file_state'    => [],
					'pending_since' => '',
					'last_run_at'   => '',
					'last_job_id'   => 0,
					'last_result'   => '',
					'runs'          => 0,
					'jobs'          => [],
					'allow_file_url' => false,
					'name'          => $id,
					'created_by'    => 0,
					'created_at'    => '',
				];
			}
		}
		return $out;
	}

	/**
	 * Clean a name; default to the source job.
	 *
	 * @param mixed       $name     Name.
	 * @param object|null $source   Source job.
	 * @param string      $fallback Fallback.
	 * @return string
	 */
	private function clean_name( $name, $source = null, $fallback = '' ) {
		$name = trim( sanitize_text_field( is_scalar( $name ) ? (string) $name : '' ) );
		$name = function_exists( 'mb_substr' ) ? mb_substr( $name, 0, 100 ) : substr( $name, 0, 100 );
		if ( '' !== $name ) {
			return $name;
		}
		if ( $source ) {
			/* translators: %d: job ID. */
			return $source->job_name ? (string) $source->job_name : sprintf( __( 'Run job #%d', 'import-export-by-rockstarlab' ), (int) $source->id );
		}
		return $fallback;
	}

	/**
	 * Public post types only.
	 *
	 * @param mixed $types Types.
	 * @return string[]
	 */
	private function clean_post_types( $types ) {
		$public = get_post_types( [ 'public' => true ] );
		unset( $public['attachment'] );
		return array_values( array_intersect( array_map( 'sanitize_key', (array) $types ), array_keys( $public ) ) );
	}

	/**
	 * Delay in seconds: 0..3600, default 60 for post and order events.
	 *
	 * @param mixed  $delay Delay.
	 * @param string $event Event.
	 * @return int
	 */
	private function clean_delay( $delay, $event ) {
		if ( in_array( $event, [ 'webhook', 'file_changed' ], true ) ) {
			return 0;
		}
		return null === $delay || '' === $delay ? 60 : max( 0, min( 3600, (int) $delay ) );
	}

	/**
	 * Random token.
	 *
	 * @return string
	 */
	private function new_token() {
		return 'rslt_' . wp_generate_password( 40, false, false );
	}

	/**
	 * Keyed hash of a token; tokens themselves are never stored.
	 *
	 * @param string $token Token.
	 * @return string
	 */
	private function hash_token( $token ) {
		return hash_hmac( 'sha256', (string) $token, wp_salt( 'auth' ) );
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
	 * Not found.
	 *
	 * @return \WP_Error
	 */
	private function not_found() {
		return $this->error( 'trigger_not_found', __( 'Trigger not found.', 'import-export-by-rockstarlab' ), 404 );
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

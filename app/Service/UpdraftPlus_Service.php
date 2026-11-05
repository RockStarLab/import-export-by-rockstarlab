<?php
/**
 * Opt-in native UpdraftPlus adapter. See docs/third-party/acrossai-updraftplus.md.
 *
 * @package RockStarLab\ImportExport\Service
 * @license GPL-2.0-or-later
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Ability_Registry;
use RockStarLab\ImportExport\Abilities\UpdraftPlus_Catalog;

defined( 'ABSPATH' ) || exit;

/** Native operation adapter with independent durable recovery state. */
class UpdraftPlus_Service {
	const HOOK       = 'rsl_ie_updraft_worker';
	const COMPONENTS = [ 'db', 'plugins', 'themes', 'uploads', 'others' ];

	/**
	 * Install cron and native operation lifecycle hooks.
	 *
	 * @return void
	 */
	public static function hooks() {
		add_action( self::HOOK, [ self::class, 'worker' ] );
		add_filter( 'updraftplus_backup_complete', [ self::class, 'backup_complete' ] );
		add_filter( 'updraftplus_logline', [ self::class, 'backup_log' ], 10, 2 );
		add_action( 'updraftplus_restore_completed', [ self::class, 'restore_complete' ] );
		add_action( 'updraftplus_pre_entity_restoration', [ self::class, 'guard_native_restore' ], 1 );
		// Also prevent manually/scheduled-started native backups overlapping an agent restore.
		add_filter( 'updraftplus_boot_backup', [ self::class, 'allow_native_backup' ], 10, 1 );
	}

	/**
	 * Recheck both opt-ins and both administrator capability policies.
	 *
	 * @return bool
	 */
	public static function allowed() {
		return Ability_Registry::is_updraftplus_enabled() && current_user_can( 'manage_options' )
			&& class_exists( 'UpdraftPlus_Options' ) && \UpdraftPlus_Options::user_can_manage();
	}

	/**
	 * Stop with a stable public error code.
	 *
	 * @param string $code Stable error code.
	 * @return void
	 * @throws \RuntimeException When the operation cannot safely proceed.
	 */
	private static function fail( $code ) {
		throw new \RuntimeException( esc_html( $code ) );
	}
	/**
	 * Bind operations to the current administrator identity.
	 *
	 * @return string
	 */
	private static function owner() {
		$user = wp_get_current_user();
		return hash( 'sha256', $user->ID . '|' . $user->user_login . '|' . $user->user_registered );
	}
	/**
	 * Persist the operation with its latest confirmed update time.
	 *
	 * @param array $job Durable operation.
	 * @return array
	 */
	private static function save( array $job ) {
		$job['updated_at'] = time();
		UpdraftPlus_Store::write( 'job-' . $job['job_id'], $job );
		return $job;
	}
	/**
	 * Return only the public operation fields.
	 *
	 * @param array $job Durable operation.
	 * @return array
	 */
	private static function output( array $job ) {
		return array_intersect_key( $job, array_flip( [ 'job_id', 'type', 'status', 'stage', 'created_at', 'updated_at', 'backup_id', 'native_job_id', 'error_code', 'message', 'recovery_url' ] ) );
	}
	/**
	 * Load an operation belonging to this administrator and workflow.
	 *
	 * @param string $id Operation or backup identifier.
	 * @param string $type Expected workflow type.
	 * @return array
	 */
	private static function owned_job( $id, $type ) {
		$job = UpdraftPlus_Store::read( 'job-' . $id );
		if ( ! $job || self::owner() !== $job['owner'] || $type !== $job['type'] ) {
			self::fail( 'updraft_job_not_found' );
		}
		return $job;
	}
	/**
	 * Check whether a job no longer owns the site operation slot.
	 *
	 * @param array $job Durable operation.
	 * @return bool
	 */
	private static function terminal( $job ) {
		return in_array( $job['status'], [ 'completed', 'failed', 'cancelled' ], true ); }

	/**
	 * Validate and dispatch an authorized ability request.
	 *
	 * @param string $name Ability or lock name.
	 * @param array  $input Validated request fields.
	 * @return array|\WP_Error
	 * @throws \RuntimeException When the operation cannot safely proceed.
	 */
	public function execute( $name, $input = [] ) {
		if ( ! self::allowed() ) {
			return new \WP_Error( 'updraft_access_denied', 'Enable both abilities switches and use an authorized UpdraftPlus administrator.', [ 'status' => 403 ] );
		}
		$definitions = UpdraftPlus_Catalog::definitions();
		if ( ! isset( $definitions[ $name ] ) || ! is_array( $input ) ) {
			return new \WP_Error( 'updraft_invalid_input', 'Unknown operation or invalid input.' );
		}
		$valid = rest_validate_value_from_schema( $input, $definitions[ $name ]['input'], 'input' );
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		$lock = null;
		try {
			global $updraftplus;
			if ( 'updraftplus-status' === $name ) {
				return [
					'version'           => (string) $updraftplus->version,
					'native_busy'       => self::native_busy(),
					'cron_disabled'     => defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON,
					'restore_supported' => self::supported_restore(),
					'limitations'       => [ 'Restore: requires UpdraftPlus 1.26.8 or later, single site, direct filesystem, local standard archives from this site.', 'Encrypted, incremental, foreign, multisite and remote-only restores require the UpdraftPlus dashboard.', 'Database or plugin restoration may remove API access. Reconnect in WordPress; never treat a disconnected request as success.' ],
				];
			}
			if ( 'updraftplus-backup-list' === $name ) {
				$history = \UpdraftPlus_Backup_History::get_history();
				$result  = [];
				foreach ( array_slice( $history, $input['offset'] ?? 0, $input['limit'] ?? 20, true ) as $id => $set ) {
					$result[] = self::metadata( $id, $set );
				}
				return [
					'backups' => $result,
					'total'   => count( $history ),
				];
			}
			if ( 'updraftplus-backup-get' === $name ) {
				return self::metadata( $input['backup_id'], self::backup( $input['backup_id'] ) );
			}
			if ( in_array( $name, [ 'updraftplus-backup-status', 'updraftplus-restore-status' ], true ) ) {
				$job = self::owned_job( $input['job_id'], 'updraftplus-backup-status' === $name ? 'backup' : 'restore' );
				return self::output( self::refresh( $job ) );
			}
			$lock = UpdraftPlus_Store::lock();
			if ( 'updraftplus-restore-resume' === $name ) {
				$job = self::owned_job( $input['job_id'], 'restore' );
				if ( 'paused' !== $job['status'] || empty( $job['checkpoint'] ) ) {
					self::fail( 'updraft_no_safe_checkpoint' );
				}
				if ( self::native_busy( $job['native_job_id'] ) ) {
					self::fail( 'updraft_operation_busy' );
				}
				self::verify_archives( $job['plan'] );
				$job['status']     = 'queued';
				$job['error_code'] = '';
				self::save( $job );
				try {
					self::schedule( $job['job_id'] );
				} catch ( \RuntimeException $e ) {
					$job['status'] = 'paused';
					self::save( $job );
					throw $e;
				}
				return self::output( $job );
			}
			if ( isset( $input['request_id'] ) ) {
				ksort( $input );
				$key      = 'request-' . hash( 'sha256', self::owner() . $name . $input['request_id'] );
				$hash     = hash( 'sha256', wp_json_encode( $input ) );
				$existing = UpdraftPlus_Store::read( $key );
				if ( $existing ) {
					if ( $existing['hash'] !== $hash ) {
						self::fail( 'updraft_idempotency_conflict' );
					}
					return self::output( self::refresh( UpdraftPlus_Store::read( 'job-' . $existing['job_id'] ) ) );
				}
			}
			self::idle();
			if ( 'updraftplus-backup-start' === $name && ! empty( $input['send_to_remote'] ) ) {
				$services = (array) \UpdraftPlus_Options::get_updraft_option( 'updraft_service', [] );
				$services = array_values( array_diff( $services, [ 'none', '' ] ) );
				if ( ! $services || ! \UpdraftPlus_Storage_Methods_Interface::get_enabled_storage_objects_and_ids( $services ) ) {
					self::fail( 'updraft_remote_storage_not_configured' );
				}
			}
			if ( 'updraftplus-restore-plan' === $name ) {
				return self::plan( $input );
			}
			$plan = null;
			if ( 'updraftplus-restore-start' === $name ) {
				$plan = UpdraftPlus_Store::read( 'plan-' . $input['plan_id'] );
				if ( ! $plan || self::owner() !== $plan['owner'] || $plan['expires_at'] < time() || ! empty( $plan['used'] ) || ! hash_equals( $plan['token_hash'], hash( 'sha256', $input['confirmation_token'] ) ) ) {
					self::fail( 'updraft_invalid_confirmation' );
				}
				self::verify_archives( $plan );
			}
			$id  = bin2hex( random_bytes( 16 ) );
			$job = [
				'job_id'        => $id,
				'type'          => $plan ? 'restore' : 'backup',
				'status'        => 'queued',
				'stage'         => 'queued',
				'created_at'    => time(),
				'updated_at'    => time(),
				'backup_id'     => $plan ? $plan['backup_id'] : '',
				'native_job_id' => bin2hex( random_bytes( 6 ) ),
				'error_code'    => '',
				'message'       => 'Queued; poll status. A working WP-Cron or system cron runner is required.',
				'recovery_url'  => admin_url( 'options-general.php?page=updraftplus' ),
				'owner'         => self::owner(),
				'user_id'       => get_current_user_id(),
				'input'         => $plan ? [] : $input,
				'plan'          => $plan,
			];
			self::save( $job );
			UpdraftPlus_Store::write(
				$key,
				[
					'hash'   => $hash,
					'job_id' => $id,
				]
			);
			UpdraftPlus_Store::write( 'active', [ 'job_id' => $id ] );
			if ( $plan ) {
				$plan['used'] = $id;
				UpdraftPlus_Store::write( 'plan-' . $input['plan_id'], $plan ); }
			try {
				self::schedule( $id );
			} catch ( \RuntimeException $e ) {
				$job['status']     = 'failed';
				$job['error_code'] = $e->getMessage();
				self::save( $job );
				throw $e; }
			return self::output( $job );
		} catch ( \RuntimeException $e ) {
			$code = preg_match( '/^updraft_[a-z_]+$/D', $e->getMessage() ) ? $e->getMessage() : 'updraft_operation_failed';
			return new \WP_Error( $code, str_replace( '_', ' ', $code ) . '. Check the UpdraftPlus dashboard; no success is implied.', [ 'status' => 409 ] );
		} finally {
			if ( $lock ) {
				UpdraftPlus_Store::unlock( $lock );
			}
		}
	}

	/**
	 * Schedule one worker invocation without duplicate cron entries.
	 *
	 * @param string $id Operation or backup identifier.
	 * @return void
	 */
	private static function schedule( $id ) {
		if ( wp_next_scheduled( self::HOOK, [ $id ] ) ) {
			return;
		}
		if ( ! wp_schedule_single_event( time() + 1, self::HOOK, [ $id ] ) ) {
			self::fail( 'updraft_cron_schedule_failed' );
		}
		if ( ! ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) ) {
			spawn_cron();
		}
	}
	/**
	 * Load a native backup set by its history timestamp.
	 *
	 * @param string $id Operation or backup identifier.
	 * @return array
	 */
	private static function backup( $id ) {
		$set = \UpdraftPlus_Backup_History::get_history( (int) $id );
		if ( ! $set ) {
			self::fail( 'updraft_backup_not_found' );
		}
		return $set;
	}
	/**
	 * Resolve an existing local archive within the native backup directory.
	 *
	 * @param string $file Native archive basename.
	 * @return string
	 */
	private static function archive_path( $file ) {
		global $updraftplus;
		if ( ! is_string( $file ) || basename( $file ) !== $file || false !== strpos( $file, '\\' ) || false !== strpos( $file, "\0" ) ) {
			self::fail( 'updraft_invalid_archive' );
		}
		$dir  = realpath( $updraftplus->backups_dir_location() );
		$path = $dir . '/' . $file;
		if ( ! $dir || is_link( $path ) || ! is_file( $path ) || ! is_readable( $path ) ) {
			self::fail( 'updraft_local_archive_required' );
		}
		return $path;
	}
	/**
	 * Project native backup metadata without exposing paths or credentials.
	 *
	 * @param string $id Operation or backup identifier.
	 * @param array  $set Native backup metadata.
	 * @return array
	 */
	private static function metadata( $id, $set ) {
		$components = [];
		$count      = 0;
		$local      = 0;
		$size       = 0;
		foreach ( self::COMPONENTS as $component ) {
			if ( empty( $set[ $component ] ) ) {
				continue;
			}
			$components[] = $component;
			foreach ( (array) $set[ $component ] as $index => $file ) {
				++$count;
				$size += (int) ( $set[ $component . ( $index ? $index : '' ) . '-size' ] ?? 0 );
				try {
					self::archive_path( $file );
					++$local; } catch ( \RuntimeException $e ) {
					// Remote-only or unavailable.
					continue;
					}
			}
		}
		return [
			'backup_id'           => (string) $id,
			'created_at'          => (int) $id,
			'components'          => $components,
			'archive_count'       => $count,
			'local_archive_count' => $local,
			'size_bytes'          => max( 0, $size ),
			'result'              => self::historical_result( $set ),
			'remote_storage'      => array_values( array_intersect( (array) ( $set['service'] ?? [] ), [ 'dropbox', 's3', 's3generic', 'googledrive', 'ftp', 'sftp', 'webdav', 'azure', 'cloudfiles', 'openstack', 'onedrive', 'pcloud', 'backblaze', 'googlecloud', 'dreamobjects', 'email', 'updraftvault' ] ) ),
		];
	}
	/**
	 * Report success only when the completion callback was recorded.
	 *
	 * @param array $set Native backup metadata.
	 * @return string
	 */
	private static function historical_result( $set ) {
		if ( empty( $set['nonce'] ) || ! preg_match( '/^[a-f0-9]+$/D', $set['nonce'] ) ) {
			return 'unknown';
		}
		try {
			$origin = UpdraftPlus_Store::read( 'origin-' . $set['nonce'] ); } catch ( \RuntimeException $e ) {
			return 'unknown'; }
			return ! empty( $origin['completed'] ) ? 'completed' : 'unknown';
	}

	/**
	 * Detect native operations, excluding a known operation and completed cron entries.
	 *
	 * @param string $except Native job excluded from the busy check.
	 * @return bool
	 */
	private static function native_busy( $except = '' ) {
		$restore = get_site_option( 'updraft_restore_in_progress' );
		if ( $restore && $restore !== $except ) {
			return true;
		}
		foreach ( (array) _get_cron_array() as $events ) {
			foreach ( (array) ( $events['updraft_backup_resume'] ?? [] ) as $event ) {
				$nonce = $event['args'][1] ?? '';
				if ( $nonce === $except ) {
					continue;
				}
				// A DB restore can resurrect cron entries of already completed backups.
				$origin = preg_match( '/^[a-f0-9]+$/D', $nonce ) ? UpdraftPlus_Store::read( 'origin-' . $nonce ) : null;
				if ( ! empty( $origin['completed'] ) || $GLOBALS['updraftplus']->found_backup_complete_in_logfile( $nonce, false ) ) {
					continue;
				}
				return true;
			}
		}
		return false;
	}
	/**
	 * Require a free durable slot and no active native operation.
	 *
	 * @return void
	 */
	private static function idle() {
		$active = UpdraftPlus_Store::read( 'active' );
		if ( $active ) {
			$job = self::refresh( UpdraftPlus_Store::read( 'job-' . $active['job_id'] ) );
			if ( ! self::terminal( $job ) ) {
				self::fail( 'updraft_operation_busy' );
			}
		}
		if ( self::native_busy() ) {
			self::fail( 'updraft_operation_busy' );
		}
	}
	/**
	 * Prevent another native backup from overlapping an agent operation.
	 *
	 * @param bool $allowed Previous filter decision.
	 * @return bool
	 */
	public static function allow_native_backup( $allowed ) {
		try {
			$active = UpdraftPlus_Store::read( 'active' );
			if ( $active ) {
				$job = UpdraftPlus_Store::read( 'job-' . $active['job_id'] );
				if ( $job && ! self::terminal( $job ) && ( 'restore' === $job['type'] || ( $GLOBALS['updraftplus']->nonce ?? '' ) !== $job['native_job_id'] ) ) {
					return false;
				}
			}
		} catch ( \RuntimeException $e ) {
			return $allowed; }
		return $allowed;
	}
	/**
	 * Read native progress without promoting missing evidence to success.
	 *
	 * @param array $job Durable operation.
	 * @return array
	 */
	private static function refresh( array $job ) {
		global $updraftplus;
		if ( self::terminal( $job ) ) {
			return $job;
		}
		if ( 'backup' === $job['type'] ) {
			$data = $updraftplus->jobdata_getarray( $job['native_job_id'] );
			if ( ! empty( $data['jobstatus'] ) ) {
				$job['stage'] = sanitize_key( $data['jobstatus'] );
			}
		}
		if ( time() - $job['updated_at'] > 900 ) {
			$job['message']    = 'No recent worker confirmation. Check WP-Cron and the UpdraftPlus dashboard; do not restart a restore blindly.';
			$job['error_code'] = 'updraft_worker_stalled';
		}
		return $job;
	}
	/**
	 * Record authoritative native completion before UpdraftPlus removes job data.
	 *
	 * @param bool $delete Native cleanup filter value.
	 * @return bool
	 */
	public static function backup_complete( $delete ) {
		global $updraftplus;
		try {
			$active = UpdraftPlus_Store::read( 'active' );
			if ( ! $active ) {
				return $delete;
			}
			$job = UpdraftPlus_Store::read( 'job-' . $active['job_id'] );
			if ( $job && 'backup' === $job['type'] && $job['native_job_id'] === $updraftplus->nonce ) {
				$job['status']    = 'completed';
				$job['stage']     = 'completed';
				$job['backup_id'] = (string) $updraftplus->backup_time;
				$job['message']   = 'UpdraftPlus confirmed successful backup completion.';
				self::save( $job );
				UpdraftPlus_Store::write(
					'origin-' . $job['native_job_id'],
					[
						'site'      => site_url(),
						'completed' => true,
					]
				);
			}
		} catch ( \RuntimeException $e ) {
			// Never interfere with native cleanup.
			return $delete;
		}
		return $delete;
	}
	/**
	 * Capture native terminal failure messages without exposing the log.
	 *
	 * @param string|bool $line Native log entry.
	 * @param string      $nonce Native operation identifier.
	 * @return string|bool
	 */
	public static function backup_log( $line, $nonce ) {
		// phpcs:ignore WordPress.WP.I18n.TextDomainMismatch -- Must match the native plugin's translated terminal messages.
		$failures = [ __( 'The backup attempt has finished, apparently unsuccessfully', 'updraftplus' ), __( 'The backup was aborted by the user', 'updraftplus' ) ];
		if ( ! in_array( $line, $failures, true ) ) {
			return $line;
		}
		try {
			$active = UpdraftPlus_Store::read( 'active' );
			$job    = $active ? UpdraftPlus_Store::read( 'job-' . $active['job_id'] ) : null;
			if ( $job && 'backup' === $job['type'] && $nonce === $job['native_job_id'] && ! self::terminal( $job ) ) {
				$job['status']     = 'failed';
				$job['stage']      = 'failed';
				$job['error_code'] = 'updraft_native_backup_failed';
				$job['message']    = 'UpdraftPlus reported that the backup failed or was cancelled. See the native log.';
				self::save( $job );
			}
		} catch ( \RuntimeException $e ) {
			// Native logging must continue.
			return $line;
		}
		return $line;
	}

	/** Manual recovery in UpdraftPlus can finish an interrupted agent-owned job. */
	public static function restore_complete() {
		try {
			$active = UpdraftPlus_Store::read( 'active' );
			$job    = $active ? UpdraftPlus_Store::read( 'job-' . $active['job_id'] ) : null;
			if ( $job && 'restore' === $job['type'] && $job['native_job_id'] === $GLOBALS['updraftplus']->nonce ) {
				$job['status']     = 'completed';
				$job['stage']      = 'completed';
				$job['error_code'] = '';
				$job['message']    = 'UpdraftPlus confirmed successful restore completion.';
				self::save( $job );
			}
		} catch ( \RuntimeException $e ) {
			// Existing state remains non-success.
			return;
		}
	}

	/** Block another native restore from entering a destructive stage during our operation. */
	public static function guard_native_restore() {
		try {
			$active = UpdraftPlus_Store::read( 'active' );
		} catch ( \RuntimeException $e ) {
			return; }
		$job = $active ? UpdraftPlus_Store::read( 'job-' . $active['job_id'] ) : null;
		if ( $job && ! self::terminal( $job ) && $job['native_job_id'] !== $GLOBALS['updraftplus']->nonce ) {
			self::fail( 'updraft_operation_busy' );
		}
	}

	/**
	 * Require the minimum supported UpdraftPlus runtime for restoration.
	 *
	 * @return bool
	 */
	private static function supported_restore() {
		global $updraftplus;
		return version_compare( (string) $updraftplus->version, '1.26.8', '>=' ) && ! is_multisite();
	}
	/**
	 * Check local archives and issue an expiring administrator-bound confirmation.
	 *
	 * @param array $input Validated request fields.
	 * @return array
	 */
	private static function plan( $input ) {
		global $updraftplus;
		if ( ! self::supported_restore() ) {
			self::fail( 'updraft_restore_version_unsupported' );
		}
		$set = self::backup( $input['backup_id'] );
		if ( ! empty( $set['meta_foreign'] ) || ! empty( $set['is_multisite'] ) || count( $set['incremental_sets'] ?? [] ) > 1 ) {
			self::fail( 'updraft_restore_format_unsupported' );
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( 'direct' !== get_filesystem_method() || ! wp_is_writable( WP_CONTENT_DIR ) ) {
			self::fail( 'updraft_direct_filesystem_required' );
		}
		$archives = [];
		$bytes    = 0;
		$entities = $updraftplus->get_backupable_file_entities( true );
		foreach ( $input['components'] as $component ) {
			if ( 'db' === $component ) {
				continue;
			}
			foreach ( (array) ( $entities[ $component ]['path'] ?? [] ) as $path ) {
				if ( is_link( $path ) || ! is_dir( $path ) || ! wp_is_writable( $path ) ) {
					self::fail( 'updraft_component_not_writable' );
				}
				if ( file_exists( $updraftplus->backups_dir_location() . '/' . basename( $path ) . '-old' ) ) {
					self::fail( 'updraft_previous_restore_folders' );
				}
			}
		}
		foreach ( $input['components'] as $component ) {
			if ( empty( $set[ $component ] ) ) {
				self::fail( 'updraft_component_missing' );
			}
			foreach ( (array) $set[ $component ] as $index => $file ) {
				$path = self::archive_path( $file );
				if ( '.crypt' === substr( $file, -6 ) ) {
					self::fail( 'updraft_encrypted_restore_unsupported' );
				}
				$size     = filesize( $path );
				$expected = $set[ $component . ( $index ? $index : '' ) . '-size' ] ?? null;
				if ( ! $size || ( null !== $expected && $size !== (int) $expected ) ) {
					self::fail( 'updraft_archive_size_mismatch' );
				}
				foreach ( (array) ( $set['checksums'] ?? [] ) as $algorithm => $checksums ) {
					if ( in_array( $algorithm, [ 'sha1', 'sha256', 'md5' ], true ) && isset( $checksums[ $component . $index ] ) && ! hash_equals( $checksums[ $component . $index ], hash_file( $algorithm, $path ) ) ) {
						self::fail( 'updraft_archive_checksum_mismatch' );
					}
				}
				if ( 'db' !== $component ) {
					if ( ! class_exists( 'ZipArchive' ) ) {
						self::fail( 'updraft_zip_extension_required' );
					}
					$zip = new \ZipArchive();
					if ( true !== $zip->open( $path, \ZipArchive::CHECKCONS ) ) {
						self::fail( 'updraft_invalid_archive' );
					}
					try {
						// phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- PHP ZipArchive API.
						for ( $n = 0; $n < $zip->numFiles; ++$n ) {
							$entry = $zip->statIndex( $n );
							if ( preg_match( '#(^/|(^|/)\.\.(/|$)|\\\\|^[A-Za-z]:)#', $entry['name'] ) ) {
								self::fail( 'updraft_unsafe_archive' );
							}
							$zip->getExternalAttributesIndex( $n, $opsys, $attributes );
							if ( ( ( $attributes >> 16 ) & 0170000 ) === 0120000 ) {
								self::fail( 'updraft_unsafe_archive' );
							}
							$bytes += $entry['size'];
						}
					} finally {
						$zip->close(); }
				} else {
					$analysis = $updraftplus->analyse_db_file( (int) $input['backup_id'], [], $path, true );
					if ( ! empty( $analysis[2] ) || empty( $analysis[3]['same_url'] ) || ! empty( $analysis[3]['multisite'] ) ) {
						self::fail( 'updraft_database_preflight_failed' );
					}
					$bytes += self::database_size( $path );
				}
				$archives[ $file ] = hash_file( 'sha256', $path );
			}
		}
		// Files-only backups need a trustworthy same-site origin, recorded by this adapter.
		$origin = UpdraftPlus_Store::read( 'origin-' . sanitize_key( $set['nonce'] ?? '' ) );
		if ( ! in_array( 'db', $input['components'], true ) && ( ! $origin || site_url() !== $origin['site'] ) ) {
			self::fail( 'updraft_backup_origin_unverified' );
		}
		$required = (int) ( $bytes * 2 + 64 * 1024 * 1024 );
		$free     = disk_free_space( WP_CONTENT_DIR );
		if ( false === $free || $free < $required ) {
			self::fail( 'updraft_insufficient_disk_space' );
		}
		$token  = bin2hex( random_bytes( 32 ) );
		$id     = bin2hex( random_bytes( 16 ) );
		$output = [
			'plan_id'             => $id,
			'confirmation_token'  => $token,
			'expires_at'          => time() + 600,
			'backup_id'           => $input['backup_id'],
			'components'          => $input['components'],
			'required_free_bytes' => $required,
			'warning'             => 'Restoration overwrites selected components: ' . implode( ', ', $input['components'] ) . '. Newer data may be lost. Database/plugins may replace accounts, credentials, settings and this integration. Explicitly confirm before starting. Keep WordPress hosting and UpdraftPlus dashboard access available.',
		];
		$stored = $output;
		unset( $stored['confirmation_token'] );
		$stored += [
			'token_hash' => hash( 'sha256', $token ),
			'owner'      => self::owner(),
			'archives'   => $archives,
			'set'        => $set,
			'site'       => site_url(),
		];
		UpdraftPlus_Store::write( 'plan-' . $id, $stored );
		return $output;
	}

	/**
	 * Verify a standard gzip database stream and measure its expanded size.
	 *
	 * @param string $path Validated local database archive path.
	 * @return int
	 */
	private static function database_size( $path ) {
		if ( '.gz' !== substr( $path, -3 ) || filesize( $path ) < 18 ) {
			self::fail( 'updraft_database_format_unsupported' );
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Stream a validated local archive.
		$stream = fopen( $path, 'rb' );
		if ( ! $stream ) {
			self::fail( 'updraft_invalid_database_archive' );
		}
		$context = inflate_init( ZLIB_ENCODING_GZIP );
		$ended   = false;
		$bytes   = 0;
		try {
			while ( ! feof( $stream ) ) {
				// Small compressed chunks bound memory even for highly compressible SQL.
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread -- WP_Filesystem cannot stream gzip members.
				$pending = fread( $stream, 8192 );
				if ( false === $pending ) {
					self::fail( 'updraft_invalid_database_archive' );
				}
				while ( '' !== $pending ) {
					// Native UpdraftPlus appends multiple gzip members (for separate tables).
					if ( $ended ) {
						$context = inflate_init( ZLIB_ENCODING_GZIP );
						$ended   = false;
					}
					$before = inflate_get_read_len( $context );
					// phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Invalid CRCs/streams become a public error code.
					$expanded = @inflate_add( $context, $pending, ZLIB_SYNC_FLUSH );
					$consumed = inflate_get_read_len( $context ) - $before;
					if ( false === $expanded || $consumed <= 0 ) {
						self::fail( 'updraft_invalid_database_archive' );
					}
					$bytes  += strlen( $expanded );
					$pending = substr( $pending, $consumed );
					$ended   = ZLIB_STREAM_END === inflate_get_status( $context );
				}
			}
		} finally {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Close the local stream.
			fclose( $stream );
		}
		if ( ! $ended ) {
			self::fail( 'updraft_invalid_database_archive' );
		}
		return $bytes;
	}

	/**
	 * Recheck the exact confirmed bytes immediately before execution.
	 *
	 * @param array $plan Confirmed restore plan.
	 * @return void
	 */
	private static function verify_archives( $plan ) {
		if ( ! self::supported_restore() || site_url() !== $plan['site'] ) {
			self::fail( 'updraft_restore_environment_changed' );
		}
		foreach ( $plan['archives'] as $file => $hash ) {
			if ( ! hash_equals( $hash, hash_file( 'sha256', self::archive_path( $file ) ) ) ) {
				self::fail( 'updraft_archive_changed' );
			}
		}
		if ( disk_free_space( WP_CONTENT_DIR ) < $plan['required_free_bytes'] ) {
			self::fail( 'updraft_insufficient_disk_space' );
		}
	}

	/**
	 * Run an authorized native backup or one resumable restore step.
	 *
	 * @param string $id Operation or backup identifier.
	 * @return void
	 */
	public static function worker( $id ) {
		if ( ! is_string( $id ) || ! preg_match( '/^[a-f0-9]{32}$/D', $id ) ) {
			return;
		}
		$lock     = null;
		$old_user = get_current_user_id();
		try {
			$lock = UpdraftPlus_Store::lock( 'worker-' . $id );
			$job  = UpdraftPlus_Store::read( 'job-' . $id );
			if ( ! $job || 'queued' !== $job['status'] ) {
				return;
			}
			wp_set_current_user( $job['user_id'] );
			if ( ! self::allowed() || self::owner() !== $job['owner'] ) {
				self::fail( 'updraft_access_revoked' );
			}
			if ( self::native_busy( $job['native_job_id'] ) ) {
				self::fail( 'updraft_operation_busy' );
			}
			$job['status'] = 'running';
			$job['stage']  = 'starting';
			self::save( $job );
			global $updraftplus;
			if ( 'backup' === $job['type'] ) {
				UpdraftPlus_Store::write( 'origin-' . $job['native_job_id'], [ 'site' => site_url() ] );
				$files  = array_values( array_diff( $job['input']['components'], [ 'db' ] ) );
				$remote = ! empty( $job['input']['send_to_remote'] );
				$result = $updraftplus->boot_backup( $files ? 1 : 0, in_array( 'db', $job['input']['components'], true ) ? 1 : 0, $files, false, $remote ? false : 'none', [ 'use_nonce' => $job['native_job_id'] ] );
				if ( false === $result ) {
					self::fail( 'updraft_backup_start_failed' );
				}
			} else {
				self::restore( $job );
			}
		} catch ( \Throwable $e ) {
			if ( isset( $job ) && $job ) {
				// A nested restore may already have saved a newer checkpoint.
				$job = UpdraftPlus_Store::read( 'job-' . $id );
			}
			if ( isset( $job ) && $job && ! self::terminal( $job ) ) {
				$safe_checkpoint = ! empty( $job['checkpoint'] ) && in_array( $job['status'], [ 'queued', 'paused' ], true );
				$job['status']   = 'restore' === $job['type'] && ( 'running' === $job['status'] || ! empty( $job['checkpoint'] ) ) ? 'interrupted' : 'failed';
				if ( $safe_checkpoint ) {
					$job['status'] = 'paused';
				}
				$job['error_code'] = preg_match( '/^updraft_[a-z_]+$/D', $e->getMessage() ) ? $e->getMessage() : 'updraft_worker_failed';
				$job['message']    = 'Operation did not complete. Inspect the native UpdraftPlus log and dashboard.';
				self::save( $job );
			}
		} finally {
			wp_set_current_user( $old_user );
			if ( $lock ) {
				UpdraftPlus_Store::unlock( $lock );
			}
		}
	}

	/**
	 * Restore through the native engine and persist completed archive checkpoints.
	 *
	 * @param array $job Durable operation.
	 * @return void
	 */
	private static function restore( array $job ) {
		global $updraftplus, $updraftplus_restorer;
		// Load control flow before plugin files themselves can be replaced.
		class_exists( UpdraftPlus_Checkpoint::class );
		self::verify_archives( $job['plan'] );
		require_once ABSPATH . 'wp-admin/includes/file.php';
		if ( 'direct' !== get_filesystem_method() || ! WP_Filesystem() ) {
			self::fail( 'updraft_direct_filesystem_required' );
		}
		$updraftplus->initiate_restore_job( $job['native_job_id'] );
		$options      = [
			'updraft_encryptionphrase'          => '',
			'updraft_incremental_restore_point' => -1,
			'delete_during_restore'             => false,
		];
		$continuation = $job['checkpoint'] ?? null;
		if ( $continuation ) {
			$updraftplus->jobdata_set_from_array( $continuation );
		}
		$updraftplus->jobdata_set_multi(
			[
				'backup_timestamp' => (int) $job['backup_id'],
				'updraft_restore'  => $job['plan']['components'],
				'restore_options'  => $options,
			]
		);
		$set              = $job['plan']['set'];
		$set['timestamp'] = (int) $job['backup_id'];
		// Archives were verified locally. Do not download or delete them during restore.
		$set['service']   = [];
		$finished         = false;
		$handler_restored = false;
		register_shutdown_function(
			static function () use ( &$finished, $job ) {
				if ( $finished ) {
					return;
				}
				try {
					$state = UpdraftPlus_Store::read( 'job-' . $job['job_id'] );
					if ( 'running' === $state['status'] ) {
						$state['status']     = 'interrupted';
						$state['error_code'] = 'updraft_restore_interrupted';
						$state['message']    = 'Worker stopped inside a native stage. Use the UpdraftPlus dashboard for recovery; automatic replay is blocked.';
						self::save( $state );
					}
				} catch ( \Throwable $e ) {
					// Existing running state remains non-success.
					return;
				}
			}
		);
		$checkpoint = static function () use ( &$job ) {
			global $updraftplus;
			$job['checkpoint'] = $updraftplus->jobdata_getarray( $job['native_job_id'] );
			$remaining         = array_filter( $job['checkpoint']['second_loop_entities'] ?? [] );
			if ( ! $remaining ) {
				return; // Let native final cleanup confirm completion.
			}
			update_site_option( 'updraft_restore_in_progress', $job['native_job_id'] );
			$job['checkpoint']['updraftplus_ajax_restore'] = 'continue_ajax_restore';
			$job['status']                                 = 'paused';
			$job['stage']                                  = 'archive_checkpoint';
			$job['message']                                = 'Native archive checkpoint saved; resume continues remaining archives.';
			self::save( $job );
			throw new UpdraftPlus_Checkpoint();
		};
		add_action( 'updraft_restored_archive', $checkpoint, 999 );
		$level = ob_get_level();
		ob_start(
			static function () {
				return '';
			}
		);
		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Native restorer logging lifecycle.
		set_error_handler( [ $updraftplus, 'php_error' ] );
		try {
			$updraftplus_restorer = new \Updraft_Restorer( new \Updraft_Restorer_Skin(), $set, false, $options, $continuation );
			$result               = $updraftplus_restorer->perform_restore( array_combine( $job['plan']['components'], $job['plan']['components'] ), $options );
			if ( true !== $result ) {
				self::fail( 'updraft_restore_incomplete' );
			}
			$updraftplus_restorer->post_restore_clean_up( true, false );
			$handler_restored = true;
			if ( in_array( 'db', $job['plan']['components'], true ) ) {
				// The restored DB predates the creation of its own backup history entry.
				$history                            = \UpdraftPlus_Backup_History::get_history();
				$history[ (int) $job['backup_id'] ] = $job['plan']['set'];
				\UpdraftPlus_Backup_History::save_history( $history );
				\UpdraftPlus_Backup_History::rebuild( false );
			}
			$job['status']     = 'completed';
			$job['stage']      = 'completed';
			$job['message']    = 'UpdraftPlus confirmed restore and cleanup completed.';
			$job['error_code'] = '';
			self::save( $job );
			$finished = true;
		} catch ( UpdraftPlus_Checkpoint $e ) {
			$finished = true;
			// A restored database may revoke permission. Never silently re-enable it.
			if ( self::allowed() && self::owner() === $job['owner'] ) {
				$job['status'] = 'queued';
				self::save( $job );
				self::schedule( $job['job_id'] );
			}
		} finally {
			remove_action( 'updraft_restored_archive', $checkpoint, 999 );
			if ( ! $handler_restored ) {
				restore_error_handler();
			}
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
		}
	}
}

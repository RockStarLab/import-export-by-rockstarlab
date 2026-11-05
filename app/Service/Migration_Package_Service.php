<?php
/**
 * Bounded, all-or-nothing migration package preparation.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Abilities\Migration_Plan;
use RockStarLab\ImportExport\Abilities\Workflow_Schemas;
use RockStarLab\ImportExport\Model\Format\Format_Factory;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions -- Bounded private streams and atomic publication.

/** No content is imported by preparation; all entries validate before publication. */
class Migration_Package_Service {
	/** WooCommerce content excluded during the most recent full manifest inspection. */
	private $woocommerce_excluded = false;

	/** Legacy manifest bound retained for callers; no longer enforced. */
	const MANIFEST_LIMIT = 1048576;
	/** Maximum decoded bytes for entries requiring whole-file parsing. */
	const ENTRY_LIMIT = 16777216;

	/**
	 * Validate the manifest and every regular archive entry without extracting.
	 *
	 * @param string   $path Server-resolved ZIP path, never a public input.
	 * @param bool     $check_only Validate without collecting unselected output steps.
	 * @param array    $selected_keys Optional keys required to exist in check-only mode.
	 * @param int|null $only_index Return one dependency-ordered step and total count; null selects the first.
	 * @return array|\WP_Error Manifest steps with authorized scope.
	 */
	public function inspect( $path, $check_only = false, array $selected_keys = array(), $only_index = null ) {
		$this->woocommerce_excluded = false;
		$access = Access_Policy::check_management();
		if ( is_wp_error( $access ) ) {
			return $access; }
		if ( ! class_exists( 'ZipArchive' ) ) {
			return $this->error( 'zip_unavailable', 503 ); }
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return $this->error( 'invalid_zip' ); }
		$entries     = null;
		$scope_index = null;
		$sort        = null;
		$reader      = new Migration_Manifest_Reader();
		try {
		 // phpcs:ignore WordPress.NamingConventions.ValidVariableName -- Native ZipArchive property.
			if ( $zip->numFiles < 2 ) {
				return $this->error( 'entry_limit' ); }
			$entries     = new Migration_Entry_Index();
			$scope_index = new Migration_Entry_Index();
			if ( ! $check_only ) {
				$only_index = $only_index ?? 0;
				$sort       = new Migration_Step_Sort();
			}
		 // phpcs:ignore WordPress.NamingConventions.ValidVariableName -- Native ZipArchive property.
			for ( $i = 0; $i < $zip->numFiles; ++$i ) {
				$stat = $zip->statIndex( $i );
				if ( ! is_array( $stat ) ) {
					return $this->error( 'invalid_zip' ); }
				$name = $stat['name'];
				if ( ! preg_match( '/\A[A-Za-z0-9][A-Za-z0-9._-]{0,199}\z/', $name ) || str_contains( $name, '..' ) || ! $entries->add( $name ) ) {
					return $this->error( 'unsafe_entry' ); }
				$opsys = 0;
				$attr  = 0;
				$zip->getExternalAttributesIndex( $i, $opsys, $attr );
				$mode = ( $attr >> 16 ) & 0170000;
				if ( ( $mode && 0100000 !== $mode ) || ! empty( $stat['encryption_method'] ) ) {
					return $this->error( 'unsafe_entry' ); }
				$limit = self::ENTRY_LIMIT;
				if ( $stat['size'] > $limit && ! in_array( strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ), array( 'json', 'csv', 'xml', 'xlsx', 'ods' ), true ) ) {
					return $this->error( 'size_limit', 413 ); }
			}
			if ( ! $entries->consume( 'manifest.json' ) ) {
				return $this->error( 'manifest_missing' ); }
			$temporary = wp_tempnam( 'rsl-migration-manifest' );
			if ( ! $temporary ) {
				return $this->error( 'file_write', 500 );
			}
			try {
				$copied = $this->copy_entry( $zip, 'manifest.json', $temporary );
				if ( is_wp_error( $copied ) ) {
					return $copied;
				}
				$manifest = $reader->decode( $temporary, true );
			} catch ( \Throwable $error ) {
				return $this->error( 'manifest_invalid' );
			} finally {
				wp_delete_file( $temporary );
			}
			$s           = Workflow_Schemas::string();
			$step_schema = Workflow_Schemas::object(
				array(
					'type'         => $s,
					'file'         => $s,
					'taxonomy'     => $s,
					'post_type'    => $s,
				),
				array( 'type', 'file' )
			);
			$schema      = Workflow_Schemas::object(
				array(
					'version'    => Workflow_Schemas::string( array( '1.0' ) ),
					'created_at' => $s,
					'steps'      => array(
						'type'     => 'array',
						'items'    => $step_schema,
						'minItems' => 0,
					),
				),
				array( 'version', 'steps' )
			);
			if ( is_wp_error( rest_validate_value_from_schema( $manifest, $schema, 'manifest' ) ) ) {
				return $this->error( 'manifest_invalid' ); }
			if ( 0 === $reader->count_steps( $manifest ) ) {
				return $this->error( 'manifest_invalid' );
			}
			// Validate the whole schema before scope/entry checks, preserving error precedence.
			foreach ( $reader->steps( $manifest ) as $step ) {
				if ( is_wp_error( rest_validate_value_from_schema( $step, $step_schema, 'manifest.steps' ) ) ) {
					return $this->error( 'manifest_invalid' );
				}
			}
			$remaining_keys = array_fill_keys( $selected_keys, true );
			foreach ( $reader->steps( $manifest ) as $step ) {
				$name = $step['file'];
				if ( ! $entries->consume( $name ) || ! in_array( strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ), array( 'json', 'csv', 'xml', 'xlsx', 'ods' ), true ) ) {
					return $this->error( 'manifest_entry' ); }
				if ( Migration_Plan::unavailable_dependency( $step ) ) {
					if ( ! class_exists( 'WooCommerce' ) && Migration_Plan::requires_woocommerce( $step ) ) {
						$this->woocommerce_excluded = true;
					}
					$subtype = $step['taxonomy'] ?? $step['post_type'] ?? '';
					$key = $step['type'] . ( '' !== $subtype ? ':' . $subtype : '' );
					unset( $remaining_keys[ $key ] );
					continue;
				}
				$scope = Migration_Plan::import_scope( $step );
				if ( is_wp_error( $scope ) ) {
					return $scope; }
				if ( $scope_index ) {
					if ( ! $scope_index->add( hash( 'sha256', $scope['key'] ) ) ) {
						return $this->error( 'duplicate_step' );
					}
					if ( $sort ) {
						$sort->append(
							array(
								'manifest' => $step,
								'scope'    => $scope,
							)
						);
						continue;
					}
					unset( $remaining_keys[ $scope['key'] ] );
					continue;
				}
			}
			if ( $entries->remaining() ) {
				return $this->error( 'unlisted_entry' ); }
			if ( $sort ) {
				if ( 0 === $sort->count() ) {
					return new \WP_Error( 'rsl_ie_migration_no_available_content', 'This package has no available content to import. Content types unavailable on this site are skipped.', [ 'status' => 400 ] );
				}
				if ( ! is_int( $only_index ) || $only_index < 0 || $only_index >= $sort->count() ) {
					return $this->error( 'cursor', 409 );
				}
				return $sort->select( $only_index );
			}
			return $remaining_keys ? $this->error( 'selection' ) : [];
		} catch ( \Throwable $error ) {
			return $this->error( 'entry_index', 500 );
		} finally {
			$reader->close();
			if ( $sort ) {
				$sort->close();
			}
			if ( $scope_index ) {
				$scope_index->close();
			}
			if ( $entries ) {
				$entries->close();
			}
			$zip->close(); }
	}


	/** Copy decoded entry bytes with bounded memory and verify before parsing. */
	public function copy_entry( $zip, $name, $path, &$cursor = null ) {
		$stat = $zip->statName( $name );
		if ( ! is_array( $stat ) || ! isset( $stat['size'], $stat['crc'] ) || ! is_int( $stat['size'] ) || $stat['size'] < 0 ) {
			return $this->error( 'entry_integrity' );
		}
		$input  = $zip->getStream( $name );
		$offset = is_array( $cursor ) ? ( $cursor['bytes'] ?? 0 ) : 0;
		$output = fopen( $path, is_array( $cursor ) ? 'c+b' : 'wb' );
		if ( ! $input || ! $output ) {
			if ( $input ) {
				fclose( $input ); }
			if ( $output ) {
				fclose( $output ); }
			return $this->error( 'entry_read' );
		}
		if ( ! is_int( $offset ) || $offset < 0 || $offset > $stat['size'] || fstat( $output )['size'] < $offset || ! ftruncate( $output, $offset ) ) {
			fclose( $input );
			fclose( $output );
			return $this->error( 'cursor', 409 );
		}
		$bytes = 0;
		$hash  = hash_init( 'crc32b' );
		try {
			while ( ! feof( $input ) ) {
				$chunk = fread( $input, min( 65536, $stat['size'] - $bytes + 1 ) );
				if ( false === $chunk || ( '' === $chunk && ! feof( $input ) ) ) {
					return $this->error( 'entry_read' ); }
				if ( strlen( $chunk ) > $stat['size'] - $bytes ) {
					return $this->error( 'entry_integrity' );
				}
				$bytes += strlen( $chunk );
				if ( $bytes > self::ENTRY_LIMIT && ! in_array( strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ), array( 'json', 'csv', 'xml', 'xlsx', 'ods' ), true ) ) {
					return $this->error( 'size_limit', 413 ); }
				hash_update( $hash, $chunk );
				// ZipArchive streams cannot seek on supported PHP versions. Replay and
				// verify the committed prefix, then decode at most 1 MiB of new bytes.
				$prefix = min( strlen( $chunk ), max( 0, $offset - ( $bytes - strlen( $chunk ) ) ) );
				if ( $prefix && fread( $output, $prefix ) !== substr( $chunk, 0, $prefix ) ) {
					return $this->error( 'entry_integrity' );
				}
				$chunk = substr( $chunk, $prefix );
				if ( strlen( $chunk ) !== fwrite( $output, $chunk ) ) {
					return $this->error( 'file_write', 500 ); }
				if ( is_array( $cursor ) && $bytes - $offset >= 1048576 && $bytes < $stat['size'] ) {
					if ( ! fflush( $output ) ) {
						return $this->error( 'file_write', 500 ); }
					$cursor['bytes'] = $bytes;
					return array( 'pending' => true );
				}
			}
			if ( ! fflush( $output ) ) {
				return $this->error( 'file_write', 500 );
			}
		} finally {
			fclose( $input );
			fclose( $output );
		}
		if ( $bytes !== $stat['size'] || hash_final( $hash ) !== sprintf( '%08x', $stat['crc'] ) ) {
			return $this->error( 'entry_integrity' ); }
		if ( is_array( $cursor ) ) {
			$cursor = array(
				'bytes' => $bytes,
				'done'  => true,
			); }
		return $bytes;
	}


	/** Validate row iterators in slices, retaining the archive list-of-rows contract. */
	private function validate_rows( $path, $format, $parser, array $options, array $scope ) {
		$iterable = in_array( $format, array( 'csv', 'xml', 'xlsx', 'ods' ), true );
		$streamed = $iterable;
		if ( 'json' === $format ) {
			$handle = fopen( $path, 'rb' );
			if ( ! $handle ) {
				return $this->error( 'entry_read' ); }
			do {
				$first = fgetc( $handle );
			} while ( false !== $first && false !== strpos( " \t\r\n", $first ) );
			fclose( $handle );
			$streamed = '[' === $first || '{' === $first;
		}
		if ( ! $streamed && filesize( $path ) > self::ENTRY_LIMIT ) {
			return $this->error( 'size_limit', 413 ); }
		$headers = null;
		$cursor  = null;
		if ( 'csv' === $format ) {
			$rows = $parser->rows( $path, $options, $cursor, $headers );
		} elseif ( in_array( $format, array( 'xlsx', 'ods' ), true ) ) {
			$rows = $parser->rows( $path, $options, $headers );
		} else {
			$rows = $streamed ? $parser->rows( $path, $options ) : $parser->parse( $path, $options );
		}
		if ( ! $streamed && ( is_wp_error( $rows ) || ! is_array( $rows ) || ! array_is_list( $rows ) ) ) {
			return $this->error( 'invalid_rows' ); }
		$batch       = array();
		$batch_bytes = 0;
		$count       = 0;
		try {
			foreach ( $rows as $key => $row ) {
				if ( ! is_array( $row ) || ( 'json' === $format && $key !== $count ) ) {
					return $this->error( 'invalid_rows' ); }
				if ( null === $headers ) {
					$headers = array_keys( $row ); }
				$batch[] = $row;
				$encoded = wp_json_encode( $row );
				if ( false === $encoded ) {
					return $this->error( 'invalid_rows' ); }
				$batch_bytes += strlen( $encoded );
				unset( $encoded );
				++$count;
				if ( count( $batch ) === 100 || $batch_bytes >= 1048576 ) {
					$valid = Access_Policy::check_content_type(
						$scope['type'],
						'import',
						array(
							'options' => $scope['options'],
							'records' => $batch,
						)
					);
					if ( is_wp_error( $valid ) ) {
						return $valid; }
					$batch       = array();
					$batch_bytes = 0;
				}
			}
		} catch ( \Throwable $error ) {
			return $this->error( 'invalid_rows' ); }
		$valid = Access_Policy::check_content_type(
			$scope['type'],
			'import',
			array(
				'options' => $scope['options'],
				'records' => $batch,
			)
		);
		if ( is_wp_error( $valid ) ) {
			return $valid; }
		$headers = $headers ?? array();
		if ( is_wp_error( $headers ) || ! is_array( $headers ) || count( array_filter( $headers, 'is_string' ) ) !== count( $headers ) ) {
			return $this->error( 'invalid_rows' ); }
		return array(
			'count'   => $count,
			'headers' => $headers,
		);
	}


	/** Validate normalized workbook/object rows in slices; source-owned files inherit TTL cleanup. */
	private function validate_sheet_slice( $path, $parser, array $options, array $scope, array &$checkpoint, $json_object = false ) {
		$snapshot  = $path . '.rows-' . (int) ( $checkpoint['resource'] ?? 0 ) . '.json';
		$temporary = $snapshot . '.tmp';
		if ( is_link( $snapshot ) || is_link( $temporary ) ) {
			return $this->error( 'unsafe_path' ); }
		$pending = false;
		$stream  = null;
		try {
			if ( isset( $checkpoint['sheet_rows'] ) && (
				! is_array( $checkpoint['sheet_rows'] ) ||
				! is_array( $checkpoint['sheet_rows']['validation'] ?? null ) ||
				! is_array( $checkpoint['sheet_rows']['headers'] ?? null ) ||
				! is_int( $checkpoint['sheet_rows']['total'] ?? null ) || $checkpoint['sheet_rows']['total'] < 0 ||
				( $checkpoint['count'] ?? null ) !== ( $checkpoint['sheet_rows']['validation']['count'] ?? null )
			) ) {
				return $this->error( 'cursor', 409 ); }
			$digest = hash_file( 'sha256', $path );
			if ( ! is_string( $digest ) || ( isset( $checkpoint['sha256'] ) && $checkpoint['sha256'] !== $digest ) ) {
				return $this->error( 'source_changed', 409 );
			}
			if ( ! isset( $checkpoint['sheet_rows'] ) ) {
				$checkpoint['sha256'] = $digest;
				if ( $json_object ) {
					$checkpoint['building'] = $this->object_snapshot_slice( $path, $snapshot, $checkpoint['building'] ?? array() );
				} else {
					$request                = array(
						'file_path' => $path,
						'format'    => pathinfo( $path, PATHINFO_EXTENSION ),
						'options'   => $options,
					);
					$state                  = $checkpoint['building'] ?? Spreadsheet_Spool::create( 0, $request, $snapshot );
					$checkpoint['building'] = Spreadsheet_Spool::advance( 0, $request, $state, $snapshot );
				}
				$state = $checkpoint['building'];
				if ( isset( $state['build'] ) ) {
					$pending = true;
					return array(
						'pending' => true,
						'count'   => 0,
						'headers' => array(),
					);
				}
				$checkpoint['sheet_rows'] = array(
					'headers'    => $state['headers'],
					'total'      => $state['total'],
					'validation' => array(),
				);
				unset( $checkpoint['building'] );
			}
			if ( ! is_file( $snapshot ) ) {
				return $this->error( 'entry_read' ); }
			$state  = &$checkpoint['sheet_rows'];
			$result = $this->validate_slice( $snapshot, 'json', Format_Factory::create( 'json' ), array(), $scope, $state['validation'] );
			if ( is_wp_error( $result ) ) {
				return $result; }
			$checkpoint['count']   = $result['count'];
			$checkpoint['headers'] = $state['headers'];
			$pending               = ! empty( $result['pending'] );
			if ( ! $pending && $result['count'] !== $state['total'] ) {
				return $this->error( 'source_changed', 409 ); }
			$result['headers'] = $state['headers'];
			return $result;
		} catch ( \Throwable $error ) {
			return $this->error( 'invalid_rows' );
		} finally {
			if ( is_resource( $stream ) ) {
				fclose( $stream ); }
			if ( ! $pending ) {
				Spreadsheet_Spool::discard( 0, $snapshot ); }
		}
	}

	/** Archive objects must become a numeric list; duplicate keys retain the last value. */
	private function object_snapshot_slice( $path, $snapshot, array $state ) {
		$dir = $snapshot . '.work';
		if ( is_link( $dir ) || ( ! is_dir( $dir ) && ! mkdir( $dir, 0700 ) ) ) {
			throw new \RuntimeException( 'Cannot stage object.' ); }
		$state += array(
			'build'   => 'index',
			'cursor'  => null,
			'total'   => 0,
			'next'    => 0,
			'bytes'   => 1,
			'headers' => array(),
		);
		$count  = 0;
		$bytes  = 0;
		if ( 'index' === $state['build'] ) {
			$done = true;
			foreach ( \RockStarLab\ImportExport\Model\Format\JSON_Row_Stream::rows( $path, array( 'object_members' => true ), $state['cursor'] ) as $member ) {
				$key = array_key_first( $member );
				if ( ! is_int( $key ) || $key < 0 || $key > $state['total'] ) {
					throw new \RuntimeException( 'Invalid list key.' ); }
				if ( $key === $state['total'] ) {
					++$state['total']; }
				$json = wp_json_encode( $member[ $key ] );
				$file = $dir . '/' . $key;
				if ( false === $json || is_link( $file ) || strlen( $json ) !== file_put_contents( $file, $json ) ) {
					throw new \RuntimeException( 'Cannot store member.' ); }
				$bytes += strlen( $json );
				if ( ++$count >= 100 || $bytes >= 1048576 ) {
					$done = false;
					break; }
			}
			if ( $done ) {
				$state['build'] = 'snapshot'; }
			return $state;
		}
		if ( ! is_file( $snapshot . '.tmp' ) && is_file( $snapshot ) && ! rename( $snapshot, $snapshot . '.tmp' ) ) {
			throw new \RuntimeException( 'Cannot recover snapshot.' ); }
		if ( 1 === $state['bytes'] ) {
			file_put_contents( $snapshot . '.tmp', '[' ); }
		$out = fopen( $snapshot . '.tmp', 'c+b' );
		if ( ! $out ) {
			throw new \RuntimeException( 'Cannot open snapshot.' ); }
		try {
			if ( fstat( $out )['size'] < $state['bytes'] || ! ftruncate( $out, $state['bytes'] ) || fseek( $out, $state['bytes'] ) !== 0 ) {
				throw new \RuntimeException( 'Invalid snapshot offset.' ); }
			while ( $state['next'] < $state['total'] ) {
				$json = file_get_contents( $dir . '/' . $state['next'] );
				$row  = json_decode( $json, true );
				if ( ! is_array( $row ) || JSON_ERROR_NONE !== json_last_error() ) {
					throw new \RuntimeException( 'Invalid row.' ); }
				if ( 0 === $state['next'] ) {
					$state['headers'] = array_keys( $row ); }
				$data = ( $state['next'] ? ',' : '' ) . $json;
				if ( strlen( $data ) !== fwrite( $out, $data ) ) {
					throw new \RuntimeException( 'Cannot write row.' ); }
				++$state['next'];
				$state['bytes'] = ftell( $out );
				$bytes         += strlen( $data );
				if ( ++$count >= 100 || $bytes >= 1048576 ) {
					break; }
			}
			$done = $state['next'] === $state['total'];
			if ( $done && 1 !== fwrite( $out, ']' ) ) {
				throw new \RuntimeException( 'Cannot finish snapshot.' ); }
			if ( ! fflush( $out ) ) {
				throw new \RuntimeException( 'Cannot flush snapshot.' ); }
		} finally {
			fclose( $out ); }
		if ( $done ) {
			if ( ! rename( $snapshot . '.tmp', $snapshot ) ) {
				throw new \RuntimeException( 'Cannot publish snapshot.' ); }
			unset( $state['build'] );
		}
		return $state;
	}

	/**
	 * Validate an entry in bounded row batches without publishing incomplete data.
	 *
	 * @param string $path Private immutable entry.
	 * @param string $format Parser name.
	 * @param object $parser Format handler.
	 * @param array  $options Parser settings.
	 * @param array  $scope Authorized content scope.
	 * @param array  $checkpoint Durable parent-owned cursor.
	 * @return array|\WP_Error
	 */
	private function validate_slice( $path, $format, $parser, array $options, array $scope, array &$checkpoint ) {
		if ( in_array( $format, array( 'xlsx', 'ods' ), true ) ) {
			return $this->validate_sheet_slice( $path, $parser, $options, $scope, $checkpoint );
		}
		if ( ! in_array( $format, array( 'csv', 'json', 'xml' ), true ) ) {
			return $this->validate_rows( $path, $format, $parser, $options, $scope );
		}
		if ( 'json' === $format ) {
			$handle = fopen( $path, 'rb' );
			if ( ! $handle ) {
				return $this->error( 'entry_read' );
			}
			do {
				$first = fgetc( $handle );
			} while ( false !== $first && false !== strpos( " \t\r\n", $first ) );
			fclose( $handle );
			if ( '{' === $first ) {
				return $this->validate_sheet_slice( $path, $parser, $options, $scope, $checkpoint, true );
			}
			if ( '[' !== $first ) {
				return $this->validate_rows( $path, $format, $parser, $options, $scope );
			}
		}
		if ( isset( $checkpoint['count'] ) && ( ! is_int( $checkpoint['count'] ) || $checkpoint['count'] < 0 || ( $checkpoint['count'] > 0 && ( ! is_array( $checkpoint['cursor'] ?? null ) || ! is_array( $checkpoint['headers'] ?? null ) ) ) ) ) {
			return $this->error( 'cursor', 409 );
		}
		$digest = hash_file( 'sha256', $path );
		if ( false === $digest ) {
			return $this->error( 'entry_read' );
		}
		if ( isset( $checkpoint['sha256'] ) && $checkpoint['sha256'] !== $digest ) {
			return $this->error( 'source_changed', 409 );
		}
		$checkpoint += array(
			'sha256'  => $digest,
			'count'   => 0,
			'cursor'  => null,
			'headers' => null,
		);
		$batch       = array();
		$bytes       = 0;
		$pending     = false;
		try {
			$rows = 'json' === $format
				? \RockStarLab\ImportExport\Model\Format\JSON_Row_Stream::rows( $path, array(), $checkpoint['cursor'] )
				: $parser->rows( $path, $options, $checkpoint['cursor'] );
			foreach ( $rows as $row ) {
				$encoded = wp_json_encode( $row );
				if ( ! is_array( $row ) || false === $encoded ) {
					return $this->error( 'invalid_rows' );
				}
				if ( null === $checkpoint['headers'] ) {
					$checkpoint['headers'] = 'csv' === $format ? $parser->get_headers( $path, $options ) : array_keys( $row );
				}
				$batch[] = $row;
				$bytes  += strlen( $encoded );
				++$checkpoint['count'];
				if ( count( $batch ) >= 100 || $bytes >= 1048576 ) {
					$pending = true;
					break;
				}
			}
			unset( $rows );
		} catch ( \Throwable $error ) {
			return $this->error( 'invalid_rows' );
		}
		$valid = Access_Policy::check_content_type(
			$scope['type'],
			'import',
			array(
				'options' => $scope['options'],
				'records' => $batch,
			)
		);
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}
		if ( null === $checkpoint['headers'] ) {
			$checkpoint['headers'] = 'csv' === $format ? $parser->get_headers( $path, $options ) : array();
		}
		$headers = $checkpoint['headers'];
		if ( ! is_array( $headers ) || count( array_filter( $headers, 'is_string' ) ) !== count( $headers ) ) {
			return $this->error( 'invalid_rows' );
		}
		return array(
			'count'   => $checkpoint['count'],
			'headers' => $headers,
			'pending' => $pending,
		);
	}

	/** Public preparation advances one entry slice; partial file IDs never leave the server. */
	public function prepare_public( $id, $offset = 0, $limit = 100 ) {
		if ( ! is_int( $offset ) || $offset < 0 || ! is_int( $limit ) || $limit < 1 || $limit > 100 ) {
			return $this->error( 'cursor', 400 ); }
		$files = new File_Service();
		$meta  = $files->get( $id );
		if ( is_wp_error( $meta ) ) {
			return $meta; }
		$lock = Job_Lock::acquire_resource( 'migration-public', $id );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return $this->error( 'busy', 409 ); }
		try {
			$meta = $files->get( $id );
			if ( is_wp_error( $meta ) ) {
				return $meta; }
			$meta = File_Store::get( $id );
			if ( is_wp_error( $meta ) ) {
				return $meta; }
			$state  = $meta['package_preparation'] ?? array(
				'store' => [
					'file_id' => $id,
					'scope'   => 'public',
					'count'   => 0,
				],
				'entry' => array( 'resource' => 0 ),
			);
			$source = $files->resolve( $id, $meta['purpose'] );
			if ( is_wp_error( $source ) ) {
				return $source; }
			// Upgrade an in-flight legacy inline list once; new checkpoints contain only a count.
			if ( isset( $state['steps'] ) ) {
				$state['store'] = [
					'file_id' => $id,
					'scope'   => 'public',
					'count'   => 0,
				];
				foreach ( $state['steps'] as $step ) {
					Archive_Step_Store::put(
						$state['store'],
						$state['store']['count']++,
						[
							'step'    => $step,
							'created' => in_array( $step['file_id'], $state['created'] ?? [], true ),
						]
					); }
				unset( $state['steps'], $state['created'] );
			}
			if ( ! empty( $state['ready'] ) ) {
				if ( ! hash_equals( $state['sha256'], hash_file( 'sha256', $source['path'] ) ) ) {
					return $this->error( 'source_changed', 409 ); }
				$valid = $this->inspect( $source['path'], true );
				if ( is_wp_error( $valid ) ) {
					return $valid; }
				// Revalidate current row permissions without rebuilding a full step list.
				$page = $state['page'] ?? [];
				if ( ( $page['offset'] ?? -1 ) !== $offset || ( $page['limit'] ?? 0 ) !== $limit ) {
					$page = [
						'offset' => $offset,
						'limit'  => $limit,
						'index'  => $offset,
						'entry'  => [ 'resource' => 0 ],
					];
				}
				if ( $offset > $state['store']['count'] ) {
					return $this->error( 'cursor', 400 ); }
				$end = min( $state['store']['count'], $offset + $limit );
				if ( $page['index'] < $end ) {
					// Read first so damaged descriptors cannot be silently replaced.
					Archive_Step_Store::get( $state['store'], $page['index'] );
					$checked = $this->prepare( $id, $page['index'], $page['entry'] );
					if ( is_wp_error( $checked ) ) {
						return $checked; }
					if ( empty( $checked['pending'] ) ) {
						++$page['index'];
						$page['entry'] = [ 'resource' => 0 ]; }
				}
				$state['page'] = $page;
				if ( $page['index'] >= $end ) {
					unset( $state['page'] ); }
				$meta['package_preparation'] = $state;
				$saved                       = File_Store::save( $meta );
				if ( is_wp_error( $saved ) ) {
					return $saved; }
				return isset( $state['page'] ) ? [
					'pending'         => true,
					'package_file_id' => $id,
					'sha256'          => $state['sha256'],
					'steps'           => [],
					'offset'          => $offset,
					'next_offset'     => $offset,
					'total_steps'     => $state['store']['count'],
					'complete'        => false,
				] : $this->prepared_page( $id, $state, $offset, $limit );
			}
			$child     = 'file_' . md5( 'migration-entry:' . $id . ':' . hash_file( 'sha256', $source['path'] ) . ':' . $state['store']['count'] );
			$new_child = is_wp_error( File_Store::get( $child ) );
			$result    = $this->prepare( $id, $state['store']['count'], $state['entry'] );
			if ( is_wp_error( $result ) ) {
				foreach ( Archive_Step_Store::rows( $state['store'] ) as $record ) {
					if ( $record['created'] ) {
						File_Store::remove( $record['step']['file_id'] ); }
				}
				unset( $meta['package_preparation'] );
				File_Store::save( $meta );
				return $result;
			}
			if ( empty( $result['pending'] ) ) {
				Archive_Step_Store::put(
					$state['store'],
					$state['store']['count']++,
					[
						'step'    => $result['steps'][0],
						'created' => $new_child,
					]
				);
				$state['sha256'] = $result['sha256'];
				$state['entry']  = array(
					'resource'       => 0,
					'package_sha256' => $result['sha256'],
				);
			}
			$pending                     = ! empty( $result['pending'] ) || $state['store']['count'] < $result['total_steps'];
			$state['ready']              = ! $pending;
			$meta['package_preparation'] = $state;
			$meta['updated_at']          = time();
			$saved                       = File_Store::save( $meta );
			if ( is_wp_error( $saved ) ) {
				return $saved; }
			return $pending ? [
				'pending'         => true,
				'package_file_id' => $id,
				'sha256'          => $state['sha256'] ?? $state['entry']['package_sha256'],
				'steps'           => [],
				'offset'          => $offset,
				'next_offset'     => $offset,
				'total_steps'     => $state['store']['count'],
				'complete'        => false,
			] : $this->prepared_page( $id, $state, $offset, $limit );
		} catch ( \Throwable $error ) {
			return $this->error( 'step_unavailable', 409 );
		} finally {
			Job_Lock::release( $lock ); }
	}

	private function prepared_page( $id, array $state, $offset, $limit ) {
		$total = $state['store']['count'];
		if ( $offset > $total ) {
			return $this->error( 'cursor', 400 ); }
		$steps = [];
		$next  = $offset;
		$bytes = 0;
		while ( $next < $total && count( $steps ) < $limit ) {
			$record   = Archive_Step_Store::get( $state['store'], $next );
			$step     = $record['step'];
			$resolved = ( new File_Service() )->resolve( $step['file_id'], 'import' );
			if ( is_wp_error( $resolved ) ) {
				return $resolved; }
			$size = strlen( wp_json_encode( $step ) );
			if ( $steps && $bytes + $size > 1048576 ) {
				break; }
			$steps[] = $step;
			$bytes  += $size;
			++$next;
		}
		return [
			'pending'         => false,
			'package_file_id' => $id,
			'sha256'          => $state['sha256'],
			'steps'           => $steps,
			'offset'          => $offset,
			'next_offset'     => $next,
			'total_steps'     => $total,
			'complete'        => $next === $total,
			'woocommerce_excluded' => $this->woocommerce_excluded,
		];
	}

	/**
	 * Publish deterministic private import file IDs only after full validation.
	 *
	 * @param string     $id Existing uploaded or exported ZIP file ID.
	 * @param int|null   $only_index Internal dependency-ordered entry cursor; null advances public preparation.
	 * @param array|null $checkpoint Optional parent-owned row validation cursor.
	 * @return array|\WP_Error
	 */
	public function prepare( $id, $only_index = null, &$checkpoint = null ) {
		if ( null === $only_index ) {
			return $this->prepare_public( $id ); }
		$files = new File_Service();
		$meta  = $files->get( $id );
		if ( is_wp_error( $meta ) ) {
			return $meta; }
		$lock = Job_Lock::acquire_resource( 'migration-package', $id );
		if ( ! $lock || is_wp_error( $lock ) ) {
			return $this->error( 'busy', 409 ); }
		$staged    = array();
		$created   = array();
		$published = false;
		try {
			$source = $files->resolve( $id, $meta['purpose'] );
			if ( is_wp_error( $source ) ) {
				return $source; }
			if ( 'zip' !== $source['format'] ) {
				return $this->error( 'zip_required' ); }
			if ( is_array( $checkpoint ) ) {
				$digest = hash_file( 'sha256', $source['path'] );
				if ( isset( $checkpoint['package_sha256'] ) && $checkpoint['package_sha256'] !== $digest ) {
					return $this->error( 'source_changed', 409 ); }
				$checkpoint['package_sha256'] = $digest;
			}
			$steps = $this->inspect( $source['path'], false, array(), $only_index );
			if ( is_wp_error( $steps ) ) {
				return $steps; }
			$total_steps = null === $only_index ? count( $steps ) : $steps['total_steps'];
			if ( null !== $only_index ) {
				$steps = $steps['steps']; }
			$fingerprint = hash_file( 'sha256', $source['path'] );
			$zip         = new \ZipArchive();
			if ( true !== $zip->open( $source['path'] ) ) {
				return $this->error( 'invalid_zip' ); }
			try {
				foreach ( $steps as $index => &$step ) {
					$format = strtolower( pathinfo( $step['manifest']['file'], PATHINFO_EXTENSION ) );
					// Each resource stays private and is not published until all steps validate.
					$child_id = 'file_' . md5( 'migration-entry:' . $id . ':' . $fingerprint . ':' . $index );
					$existing = File_Store::get( $child_id );
					if ( is_array( $checkpoint ) && ! empty( $checkpoint['scratch'] ) && ! is_wp_error( $existing ) && isset( $existing['migration_preparation'] ) && ! is_file( dirname( $source['path'] ) . '/validate-' . $child_id . '-' . (int) ( $checkpoint['resource'] ?? 0 ) . '.' . $format ) ) {
						// Publication succeeded but the caller lost its final checkpoint.
						$checkpoint = array(
							'resource'       => $checkpoint['resource'] ?? 0,
							'package_sha256' => $fingerprint,
						);
					}

					if ( ! is_wp_error( $existing ) && isset( $existing['migration_preparation'] ) && empty( $checkpoint['scratch'] ) ) {
						$cached = $existing['migration_preparation'];
						if ( is_array( $cached ) && 1 === ( $cached['version'] ?? null ) &&
							( $cached['source_sha256'] ?? null ) === $fingerprint &&
							( $existing['migration_scope'] ?? null ) === $step['manifest'] &&
							is_array( $cached['headers'] ?? null ) &&
							is_int( $cached['total_rows'] ?? null ) && $cached['total_rows'] >= 0 ) {
							// Resolution rechecks ownership, scope, path and the entire content digest.
							$resolved = $files->resolve( $child_id, 'import' );
							if ( is_wp_error( $resolved ) ) {
								return $resolved;
							}
							if ( empty( $resolved['migration_sha256'] ) || $format !== $resolved['format'] ) {
								return $this->error( 'source_changed', 409 );
							}
							// Row authorization depends on current destination IDs and provider policy.
							$parser = Format_Factory::create( $format );
							if ( is_wp_error( $parser ) ) {
								return $parser;
							}
							$validated = is_array( $checkpoint ) ? $this->validate_slice( $resolved['path'], $format, $parser, $resolved['parser_options'], $step['scope'], $checkpoint ) : $this->validate_rows( $resolved['path'], $format, $parser, $resolved['parser_options'], $step['scope'] );
							if ( is_wp_error( $validated ) ) {
								return $validated;
							}
							if ( ! empty( $validated['pending'] ) ) {
								return array( 'pending' => true );
							}
							if ( $validated['count'] !== $cached['total_rows'] || array_values( $validated['headers'] ) !== $cached['headers'] ) {
								return $this->error( 'source_changed', 409 );
							}
							$step['record']     = $resolved;
							$step['headers']    = $cached['headers'];
							$step['total_rows'] = $cached['total_rows'];
							continue;
						}
					}
					$dir = File_Store::directory( $child_id );
					if ( is_wp_error( $dir ) ) {
						return $dir; }
					if ( is_wp_error( $existing ) && false === file_put_contents( $dir . '/.archive-owner', $id ) ) {
						return $this->error( 'file_write', 500 ); }
					$path = $dir . '/source.' . $format;
					$tmp  = $dir . '/prepare.' . $format;
					if ( is_link( $tmp ) || is_link( $path ) ) {
						return $this->error( 'unsafe_path' ); }
					if ( is_array( $checkpoint ) ) {
						// Keep resumable scratch files with the source so existing TTL cleanup owns them.
						$tmp = dirname( $source['path'] ) . '/validate-' . $child_id . '-' . (int) ( $checkpoint['resource'] ?? 0 ) . '.' . $format;
						if ( is_link( $tmp ) ) {
							return $this->error( 'unsafe_path' );
						}
					}
					$staged[] = $tmp;
					if ( is_wp_error( $existing ) ) {
						$created[] = $child_id; }
					if ( is_array( $checkpoint ) && ! isset( $checkpoint['sha256'] ) ) {
						$checkpoint['extraction'] = $checkpoint['extraction'] ?? array();
						$bytes                    = $this->copy_entry( $zip, $step['manifest']['file'], $tmp, $checkpoint['extraction'] );
					} else {
						$bytes = is_array( $checkpoint ) ? ( is_file( $tmp ) ? filesize( $tmp ) : $this->error( 'entry_read' ) ) : $this->copy_entry( $zip, $step['manifest']['file'], $tmp );
					}
					if ( is_wp_error( $bytes ) ) {
						return $bytes; }
					if ( is_array( $bytes ) ) {
						$staged = array_values( array_diff( $staged, array( $tmp ) ) );
						return $bytes;
					}
					// Reuse format container checks and parser headers, but never publish a preview.
					if ( in_array( $format, array( 'xlsx', 'ods' ), true ) ) {
						$valid = $files->check_spreadsheet_container( $tmp );
						if ( is_wp_error( $valid ) ) {
							return $valid; }
					}
					if ( 'xml' === $format && ! is_array( $checkpoint ) ) {
						$valid = $this->check_xml( $tmp );
						if ( is_wp_error( $valid ) ) {
							return $valid; }
					}
					$parser = Format_Factory::create( $format );
					if ( is_wp_error( $parser ) ) {
						return $parser; }
					$options = array(
						'delimiter'  => ',',
						'has_header' => true,
					);
					if ( is_array( $checkpoint ) ) {
						$checkpoint['scratch'] = true;
					}
					$validated = is_array( $checkpoint ) ? $this->validate_slice( $tmp, $format, $parser, $options, $step['scope'], $checkpoint ) : $this->validate_rows( $tmp, $format, $parser, $options, $step['scope'] );
					if ( is_wp_error( $validated ) ) {
						return $validated; }
					if ( ! empty( $validated['pending'] ) ) {
						$staged = array_values( array_diff( $staged, array( $tmp ) ) );
						return array( 'pending' => true );
					}
					$headers                                 = $validated['headers'];
					$step['record']                          = array(
						'file_id'          => $child_id,
						'owner_id'         => $source['owner_id'],
						'blog_id'          => $source['blog_id'],
						'purpose'          => 'import',
						'status'           => 'ready',
						'file_name'        => $step['manifest']['file'],
						'original_name'    => $step['manifest']['file'],
						'format'           => $format,
						'path'             => $path,
						'file_size'        => $bytes,
						'parser_options'   => $options,
						'migration_scope'  => $step['manifest'],
						'archive_source'   => $id,
						'migration_sha256' => hash_file( 'sha256', $tmp ),
						'created_at'       => is_wp_error( $existing ) ? time() : $existing['created_at'],
						'updated_at'       => time(),
					);
					$step['record']['migration_preparation'] = array(
						'version'       => 1,
						'source_sha256' => $fingerprint,
						'headers'       => array_values( $headers ),
						'total_rows'    => $validated['count'],
					);
					$step['temporary']                       = $tmp;
					$step['headers']                         = array_values( $headers );
					$step['total_rows']                      = $validated['count'];
				}
				unset( $step );
			} finally {
				$zip->close(); }
			if ( ! hash_equals( $fingerprint, hash_file( 'sha256', $source['path'] ) ) ) {
				return $this->error( 'source_changed', 409 ); }
			$output = array();
			foreach ( $steps as $step ) {
				$r        = $step['record'];
				$existing = File_Store::get( $r['file_id'] );
				if ( ! is_wp_error( $existing ) ) {
					$resolved = $files->resolve( $r['file_id'], 'import' );
					if ( is_wp_error( $resolved ) ) {
						return $resolved; }
					if ( ( $resolved['archive_source'] ?? '' ) !== $id ) {
						$resolved['archive_source'] = $id;
						$saved                      = File_Store::save( $resolved );
						if ( is_wp_error( $saved ) ) {
							return $saved; }
					}
					if ( isset( $step['temporary'] ) ) {
						// Upgrade legacy records only after validating their exact original bytes.
						if ( ! hash_equals( $r['migration_sha256'], hash_file( 'sha256', $resolved['path'] ) ) ) {
							return $this->error( 'source_changed', 409 );
						}
						$resolved['migration_preparation'] = $r['migration_preparation'];
						$resolved['migration_sha256']      = $r['migration_sha256'];
						$saved                             = File_Store::save( $resolved );
						if ( is_wp_error( $saved ) ) {
							return $saved;
						}
					}
				} else {
					if ( ! rename( $step['temporary'], $r['path'] ) ) {
						return $this->error( 'file_write', 500 ); }
					$saved = File_Store::save( $r );
					if ( is_wp_error( $saved ) ) {
						return $saved; }
				}
				$output[] = array(
					'key'          => $step['scope']['key'],
					'type'         => $step['scope']['type'],
					'file_id'      => $r['file_id'],
					'file_name'    => $r['file_name'],
					'format'       => $r['format'],
					'headers'      => $step['headers'],
					'total_rows'   => $step['total_rows'],
					'taxonomy'     => $step['manifest']['taxonomy'] ?? '',
					'post_type'    => $step['manifest']['post_type'] ?? '',
				);
			}
			$published = true;
			$result    = array(
				'package_file_id' => $id,
				'sha256'          => $fingerprint,
				'steps'           => $output,
			);
			if ( null !== $only_index ) {
				$result['total_steps'] = $total_steps;
			}
			return $result;
		} catch ( \Throwable $error ) {
			return $this->error( 'invalid_content' );
		} finally {
			foreach ( $staged as $path ) {
				if ( is_file( $path ) ) {
							wp_delete_file( $path ); }
			}
			if ( ! $published ) {
				foreach ( $created as $child_id ) {
						File_Store::remove( $child_id ); }
			}
			Job_Lock::release( $lock );
		}
	}

	/**
	 * Reject malformed XML and document type declarations before row parsing.
	 *
	 * @param string $path Private decoded entry.
	 * @return true|\WP_Error
	 */
	private function check_xml( $path ) {
		$previous = libxml_use_internal_errors( true );
		$reader   = new \XMLReader();
		try {
			if ( ! $reader->open( $path, null, LIBXML_NONET | LIBXML_COMPACT | LIBXML_PARSEHUGE ) ) {
				return $this->error( 'invalid_xml' ); }
			while ( $reader->read() ) {
				if ( \XMLReader::DOC_TYPE === $reader->nodeType ) { // phpcs:ignore WordPress.NamingConventions.ValidVariableName -- Native XMLReader property.
					return $this->error( 'invalid_xml' );
				}
			}
			return libxml_get_errors() ? $this->error( 'invalid_xml' ) : true;
		} finally {
			$reader->close();
			libxml_clear_errors();
			libxml_use_internal_errors( $previous );
		}
	}

	/**
	 * Return a stable error without archive payload or filesystem paths.
	 *
	 * @param string $code Suffix.
	 * @param int    $status HTTP status.
	 * @return \WP_Error
	 */
	private function error( $code, $status = 400 ) {
		return new \WP_Error( 'rsl_ie_migration_package_' . $code, __( 'The migration package is invalid or unavailable. Check its manifest, content scope and size limits.', 'import-export-by-rockstarlab' ), array( 'status' => $status ) ); }
}

<?php
/**
 * File lifecycle shared by the UI, REST transport and abilities.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Model\Format\Format_Factory;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions -- Bounded local stream I/O, atomic rename and checksums are required for resumable uploads.

/**
 * Opaque file IDs, immutable accepted chunks and authorization at every entry.
 */
class File_Service {

	/**
	 * Maximum assembled or extracted bytes, configurable by server-side code.
	 *
	 * @return int
	 */
	public static function max_bytes() {
		// Leave room for the one-byte overflow probe used by bounded copies.
		return max( 1, min( PHP_INT_MAX - 1, (int) apply_filters( 'rsl_ie_file_max_bytes', PHP_INT_MAX - 1 ) ) );
	}

	/**
	 * Map a legacy client upload token to this administrator's private session.
	 *
	 * @param string $token Legacy upload token.
	 * @return string|\WP_Error
	 */
	public static function legacy_id( $token ) {
		if ( ! is_string( $token ) || ! preg_match( '/\Aupload_[0-9]{10,16}_[a-z0-9]{5,20}\z/', $token ) ) {
			return File_Store::error( 'invalid_upload_id' );
		}
		return 'file_' . md5( get_current_blog_id() . ':' . get_current_user_id() . ':' . $token );
	}

	/**
	 * Begin an upload; repeated legacy requests must match the original manifest.
	 *
	 * @param array  $input File name, byte size and chunk count.
	 * @param string $legacy_token Optional UI token, scoped to current identity.
	 * @return array|\WP_Error
	 */
	public function begin( array $input, $legacy_token = '' ) {
		$permission = Access_Policy::check_management();
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		$name   = isset( $input['file_name'] ) && is_string( $input['file_name'] ) ? sanitize_file_name( wp_basename( $input['file_name'] ) ) : '';
		$size   = filter_var( $input['file_size'] ?? null, FILTER_VALIDATE_INT );
		$chunks = filter_var( $input['total_chunks'] ?? 1, FILTER_VALIDATE_INT );
		$format = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $format, [ 'csv', 'json', 'xml', 'xlsx', 'ods', 'zip' ], true ) || $size < 1 || $size > self::max_bytes() || $chunks < 1 || ( $chunks > 10000 && ! File_Chunk_Store::supported() ) || $chunks > $size ) {
			return File_Store::error( 'invalid_upload_manifest' );
		}
		$id = '' !== $legacy_token ? self::legacy_id( $legacy_token ) : 'file_' . str_replace( '-', '', wp_generate_uuid4() );
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		return $this->locked(
			$id,
			function () use ( $id, $name, $size, $chunks, $format ) {
				$existing = File_Store::get( $id );
				if ( ! is_wp_error( $existing ) ) {
					if ( $existing['original_name'] !== $name || $existing['expected_bytes'] !== $size || $existing['total_chunks'] !== $chunks || 'aborted' === $existing['status'] ) {
						return File_Store::error( 'upload_manifest_conflict', 409 );
					}
					return $this->describe( $existing );
				}
				$dir = File_Store::directory( $id );
				if ( is_wp_error( $dir ) ) {
					return $dir;
				}
				$record = [
					'file_id'        => $id,
					'owner_id'       => get_current_user_id(),
					'blog_id'        => get_current_blog_id(),
					'purpose'        => 'import',
					'status'         => 'uploading',
					'file_name'      => $name,
					'original_name'  => $name,
					'format'         => $format,
					'expected_bytes' => $size,
					'total_chunks'   => $chunks,
					'chunks'         => [],
					'received_bytes' => 0,
					'created_at'     => time(),
					'updated_at'     => time(),
					'parser_options' => [
						'delimiter'  => ',',
						'has_header' => true,
						'encoding'   => 'auto',
					],
				];
				if ( File_Chunk_Store::supported() ) {
					$record['chunk_storage']   = 'table';
					$record['received_chunks'] = 0;
				}
				$saved = File_Store::save( $record );
				return is_wp_error( $saved ) ? $saved : $this->describe( $record );
			}
		);
	}

	/**
	 * Download an import file from a URL, such as a supplier's price list or feed.
	 *
	 * The file goes through the same checks as an upload. The URL is kept on the
	 * file record, so a scheduled import downloads the current version each time.
	 *
	 * @param string $url     http(s) URL of a CSV, XML, JSON, XLSX, ODS or ZIP file.
	 * @param array  $options Parser options.
	 * @return array|\WP_Error
	 */
	public function from_url( $url, array $options = [] ) {
		$permission = Access_Policy::check_management();
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		$url    = esc_url_raw( trim( (string) $url ), [ 'http', 'https' ] );
		$host   = (string) wp_parse_url( $url, PHP_URL_HOST );
		$is_dev = (bool) preg_match( '/\.(local|test|localhost)$/i', $host );
		if ( '' === $url || '' === $host || ( ! $is_dev && ! wp_http_validate_url( $url ) ) ) {
			return new \WP_Error( 'rsl_ie_invalid_url', __( 'Enter a full http:// or https:// address of the file.', 'import-export-by-rockstarlab' ), [ 'status' => 400 ] );
		}
		$tmp = wp_tempnam( 'rsl-ie-url' );
		if ( ! $tmp ) {
			return File_Store::error( 'file_write', 500 );
		}
		$args = [
			'timeout'             => 120,
			'stream'              => true,
			'filename'            => $tmp,
			'limit_response_size' => self::max_bytes(),
		];
		// Development hosts (*.local, *.test) are refused by the safe HTTP API.
		$response = $is_dev ? wp_remote_get( $url, $args + [ 'reject_unsafe_urls' => false ] ) : wp_safe_remote_get( $url, $args );
		$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		if ( is_wp_error( $response ) || $code < 200 || $code >= 300 ) {
			wp_delete_file( $tmp );
			$reason = is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . $code;
			/* translators: %s: error message or HTTP status. */
			return new \WP_Error( 'rsl_ie_url_download_failed', sprintf( __( 'The file could not be downloaded (%s).', 'import-export-by-rockstarlab' ), $reason ), [ 'status' => 400 ] );
		}
		$name = '';
		if ( preg_match( '/filename\*?=(?:UTF-8\'\')?"?([^";]+)"?/i', (string) wp_remote_retrieve_header( $response, 'content-disposition' ), $match ) ) {
			$name = rawurldecode( $match[1] );
		}
		if ( '' === $name ) {
			$name = rawurldecode( wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) ) );
		}
		$name      = sanitize_file_name( '' === $name ? 'import' : $name );
		$extension = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
		if ( ! in_array( $extension, [ 'csv', 'json', 'xml', 'xlsx', 'ods', 'zip' ], true ) ) {
			$type = strtolower( (string) wp_remote_retrieve_header( $response, 'content-type' ) );
			foreach ( [ 'csv' => 'csv', 'comma-separated' => 'csv', 'xml' => 'xml', 'rss' => 'xml', 'json' => 'json', 'spreadsheetml' => 'xlsx', 'opendocument.spreadsheet' => 'ods', 'zip' => 'zip' ] as $needle => $guess ) {
				if ( false !== strpos( $type, $needle ) ) {
					$name     .= '.' . $guess;
					$extension = $guess;
					break;
				}
			}
		}
		$size = (int) filesize( $tmp );
		if ( ! in_array( $extension, [ 'csv', 'json', 'xml', 'xlsx', 'ods', 'zip' ], true ) || $size < 1 ) {
			wp_delete_file( $tmp );
			return new \WP_Error( 'rsl_ie_url_not_a_file', __( 'The address does not return a CSV, XML, JSON, XLSX, ODS or ZIP file.', 'import-export-by-rockstarlab' ), [ 'status' => 400 ] );
		}
		$record = $this->begin(
			[
				'file_name'    => $name,
				'file_size'    => $size,
				'total_chunks' => 1,
			]
		);
		if ( is_wp_error( $record ) ) {
			wp_delete_file( $tmp );
			return $record;
		}
		$stream = fopen( $tmp, 'rb' );
		$result = $stream ? $this->write_chunk( $record['file_id'], 0, $stream ) : File_Store::error( 'file_read', 500 );
		if ( $stream ) {
			fclose( $stream );
		}
		wp_delete_file( $tmp );
		if ( ! is_wp_error( $result ) ) {
			$result = $this->finalize( $record['file_id'], $options );
		}
		if ( is_wp_error( $result ) ) {
			$this->abort( $record['file_id'] );
			return $result;
		}
		$this->locked(
			$record['file_id'],
			function () use ( $record, $url ) {
				$stored = File_Store::get( $record['file_id'] );
				if ( ! is_wp_error( $stored ) ) {
					$stored['source_url'] = $url;
					File_Store::save( $stored );
				}
			}
		);
		$result['source_url'] = $url;
		return $result;
	}

	/**
	 * Receive a real PHP multipart upload; no caller-provided filesystem sources.
	 *
	 * @param array $file PHP uploaded-file entry.
	 * @param array $options Parser options.
	 * @return array|\WP_Error
	 */
	public function upload( array $file, array $options = [] ) {
		if ( ! $this->is_upload( $file ) ) {
			return File_Store::error( 'invalid_uploaded_file' );
		}
		$record = $this->begin(
			[
				'file_name' => $file['name'] ?? '',
				'file_size' => filesize( $file['tmp_name'] ),
			]
		);
		if ( is_wp_error( $record ) ) {
			return $record;
		}
		$result = $this->upload_chunk( $record['file_id'], 0, $file );
		if ( ! is_wp_error( $result ) ) {
			$result = $this->finalize( $record['file_id'], $options );
		}
		if ( is_wp_error( $result ) ) {
			$this->abort( $record['file_id'] );
		}
		return $result;
	}

	/**
	 * Accept a multipart chunk after PHP verifies its origin.
	 *
	 * @param string $id File ID.
	 * @param int    $index Zero-based chunk index.
	 * @param array  $file Uploaded chunk.
	 * @return array|\WP_Error
	 */
	public function upload_chunk( $id, $index, array $file ) {
		if ( ! $this->is_upload( $file ) ) {
			return File_Store::error( 'invalid_uploaded_file' );
		}
		$stream = fopen( $file['tmp_name'], 'rb' );
		if ( ! $stream ) {
			return File_Store::error( 'file_read', 500 );
		}
		try {
			return $this->write_chunk( $id, $index, $stream );
		} finally {
			fclose( $stream );
		}
	}

	/**
	 * Validate HTTP-upload origin without a testing bypass in production code.
	 *
	 * @param array $file Upload entry.
	 * @return bool
	 */
	private function is_upload( array $file ) {
		return isset( $file['error'], $file['tmp_name'] ) && UPLOAD_ERR_OK === $file['error'] && is_string( $file['tmp_name'] ) && is_uploaded_file( $file['tmp_name'] );
	}

	/**
	 * Store a bounded stream. Trusted PHP callers can use this without HTTP.
	 *
	 * @param string   $id File ID.
	 * @param int      $index Zero-based index.
	 * @param resource $stream Readable input stream, owned by caller.
	 * @return array|\WP_Error
	 */
	public function write_chunk( $id, $index, $stream ) {
		$index = filter_var( $index, FILTER_VALIDATE_INT );
		if ( false === $index || $index < 0 || ! is_resource( $stream ) || 'stream' !== get_resource_type( $stream ) ) {
			return File_Store::error( 'invalid_chunk' );
		}
		return $this->locked(
			$id,
			function () use ( $id, $index, $stream ) {
				$record = $this->authorized( $id );
				if ( is_wp_error( $record ) ) {
					return $record;
				}
				if ( 'uploading' !== $record['status'] || isset( $record['assembly'] ) || $index >= $record['total_chunks'] ) {
					return File_Store::error( 'upload_state_conflict', 409 );
				}
				$dir = File_Store::directory( $id );
				if ( is_wp_error( $dir ) ) {
					return $dir;
				}
				$before       = $record;
				$table_chunks = 'table' === ( $record['chunk_storage'] ?? '' );
				$previous     = $table_chunks ? File_Chunk_Store::get( $id, $index ) : ( $record['chunks'][ $index ] ?? null );
				if ( is_wp_error( $previous ) ) {
					return $previous; }
				$path   = $dir . '/chunk-' . $index;
				$staged = $path . '.tmp';
				$target = fopen( $staged, 'wb' );
				if ( ! $target ) {
					return File_Store::error( 'file_write', 500 );
				}
				$received  = $record['received_bytes'] ?? array_sum( array_column( $record['chunks'], 'bytes' ) );
				$total     = $received - ( $previous['bytes'] ?? 0 );
				$remaining = max( 0, min( $record['expected_bytes'], self::max_bytes() ) - $total );
				$bytes     = stream_copy_to_stream( $stream, $target, $remaining + 1 );
				$flush     = fflush( $target );
				fclose( $target );
				if ( ! $flush || ! $bytes || $bytes > $remaining ) {
					wp_delete_file( $staged );
					return File_Store::error( 'chunk_size_mismatch' );
				}
				$chunk = [
					'bytes'  => $bytes,
					'sha256' => hash_file( 'sha256', $staged ),
				];
				if ( null !== $previous && $previous !== $chunk ) {
					wp_delete_file( $staged );
					return File_Store::error( 'chunk_content_conflict', 409 );
				}
				if ( ! rename( $staged, $path ) ) {
					wp_delete_file( $staged );
					return File_Store::error( 'file_write', 500 );
				}
				if ( $table_chunks ) {
					$record['received_chunks'] += null === $previous ? 1 : 0;
				} else {
					$record['chunks'][ $index ] = $chunk;
				}
				$record['received_bytes'] = $total + $bytes;
				$record['updated_at']     = time();
				$saved                    = $table_chunks ? File_Chunk_Store::publish( $id, $index, $chunk, $before, $record ) : File_Store::save( $record );
				return is_wp_error( $saved ) ? $saved : array_merge( $this->describe( $record ), [ 'chunk_index' => $index ] );
			}
		);
	}

	/**
	 * Assemble, validate ZIP limits and parse before publishing the file.
	 *
	 * @param string $id File ID.
	 * @param array  $options Parser options.
	 * @param bool   $resumable Yield between assembly batches.
	 * @return array|\WP_Error
	 */
	public function finalize( $id, array $options = [], $resumable = false ) {
		return $this->locked(
			$id,
			function () use ( $id, $options, $resumable ) {
				$record = $this->authorized( $id );
				if ( is_wp_error( $record ) ) {
					return $record;
				}
				if ( 'import' !== $record['purpose'] ) {
					return File_Store::error( 'invalid_file_purpose', 409 );
				}
				if ( 'ready' === $record['status'] ) {
					$record = $this->resolve( $id, 'import' );
					return is_wp_error( $record ) ? $record : $this->preview_record( $record, $options );
				}
				if ( 'uploading' !== $record['status'] || ( $record['received_chunks'] ?? count( $record['chunks'] ) ) !== $record['total_chunks'] ) {
					return File_Store::error( 'upload_incomplete', 409 );
				}
				$dir = File_Store::directory( $id );
				if ( is_wp_error( $dir ) ) {
					return $dir;
				}
				if ( $record['expected_bytes'] > self::max_bytes() ) {
					return File_Store::error( 'upload_size_mismatch' );
				}
				$path = $dir . '/source.' . $record['format'];
				if ( $resumable || isset( $record['assembly'] ) ) {
					$assembled = $this->assemble_slice( $record, $path );
					if ( is_wp_error( $assembled ) ) {
						return $assembled; }
					if ( ! $assembled ) {
						return $this->describe( $record ) + [
							'assembled_chunks' => $record['assembly']['next'],
							'assembled_bytes'  => $record['assembly']['bytes'],
						];
					}
				} else {
					$target = fopen( $path, 'wb' );
					if ( ! $target ) {
						return File_Store::error( 'file_write', 500 );
					}
					$total     = 0;
					$assembled = false;
					try {
						for ( $i = 0; $i < $record['total_chunks']; ++$i ) {
							$metadata = 'table' === ( $record['chunk_storage'] ?? '' ) ? File_Chunk_Store::get( $id, $i ) : ( $record['chunks'][ $i ] ?? null );
							if ( is_wp_error( $metadata ) || null === $metadata ) {
								return File_Store::error( 'chunk_integrity', 409 ); }
							$chunk = $dir . '/chunk-' . $i;
							if ( ! is_file( $chunk ) ) {
								return File_Store::error( 'chunk_integrity', 409 );
							}
							$source = fopen( $chunk, 'rb' );
							if ( ! $source ) {
								return File_Store::error( 'file_read', 500 );
							}
							try {
								$copied = $this->copy_verified_chunk( $source, $target, $metadata, max( 0, $record['expected_bytes'] - $total ) );
							} finally {
								fclose( $source );
							}
							if ( is_wp_error( $copied ) ) {
								return $copied;
							}
							if ( $copied !== $metadata['bytes'] || $copied > $record['expected_bytes'] - $total ) {
								return File_Store::error( 'file_write', 500 );
							}
							$total += $copied;
						}
						if ( ! fflush( $target ) || $total !== $record['expected_bytes'] || $total > self::max_bytes() ) {
							return File_Store::error( 'upload_size_mismatch' );
						}
						$assembled = true;
					} finally {
						fclose( $target );
						if ( ! $assembled ) {
							wp_delete_file( $path );
						}
					}
				}
				if ( 'zip' === $record['format'] && class_exists( 'ZipArchive' ) ) {
					$zip = new \ZipArchive();
					if ( true === $zip->open( $path ) ) {
						$migration = false !== $zip->locateName( 'manifest.json' );
						$zip->close();
						if ( $migration ) {
							$valid = ( new Migration_Package_Service() )->inspect( $path, true );
							if ( is_wp_error( $valid ) ) {
								return $valid; }
							$record['path']              = $path;
							$record['file_size']         = filesize( $path );
							$record['status']            = 'ready';
							$record['migration_package'] = true;
							$record['updated_at']        = time();
							$saved                       = File_Store::save( $record );
							if ( is_wp_error( $saved ) ) {
								return $saved; }
							return $this->preview_record( $record, $options );
						}
					}
				}
				if ( 'zip' === $record['format'] ) {
					$extracted = $this->extract_zip( $path, $dir );
					if ( is_wp_error( $extracted ) ) {
						return $extracted;
					}
					$record['file_name'] = $extracted['file_name'];
					$record['format']    = $extracted['format'];
					$path                = $extracted['path'];
				}
				$prepared = $this->prepare_source( $path, $dir, $record );
				if ( is_wp_error( $prepared ) ) {
					return $prepared;
				}
				if ( $prepared ) {
					$path                     = $prepared['path'];
					$record['format']         = $prepared['format'];
					$record['converted_from'] = $prepared['source'];
					$record['conversion']     = $prepared['counts'];
				}
				$record['path']       = $path;
				$record['file_size']  = filesize( $path );
				$record['status']     = 'ready';
				$record['updated_at'] = time();
				return $this->preview_record( $record, $options );
			}
		);
	}

	/**
	 * Let extensions convert a known third-party export into the native layout.
	 *
	 * Filter `rsl_ie_import_file_prepared` receives null, the assembled source
	 * path, its format and the file record. A handler returns null to keep the
	 * file, a WP_Error to reject it, or an array with `path` (a regular file it
	 * wrote inside the same private directory), `format`, `source` and `counts`.
	 *
	 * @param string $path Assembled source file.
	 * @param string $dir Private file directory.
	 * @param array  $record File record.
	 * @return array|null|\WP_Error
	 */
	private function prepare_source( $path, $dir, array $record ) {
		$prepared = apply_filters( 'rsl_ie_import_file_prepared', null, $path, $record['format'], $record );
		if ( null === $prepared || false === $prepared ) {
			return null;
		}
		if ( is_wp_error( $prepared ) ) {
			return $prepared;
		}
		$target = is_array( $prepared ) && isset( $prepared['path'] ) && is_string( $prepared['path'] ) ? realpath( $prepared['path'] ) : false;
		$format = is_array( $prepared ) && isset( $prepared['format'] ) ? strtolower( (string) $prepared['format'] ) : $record['format'];
		if ( ! $target || dirname( $target ) !== realpath( $dir ) || is_link( $prepared['path'] ) || ! is_file( $target ) || ! in_array( $format, [ 'csv', 'json', 'xml', 'xlsx', 'ods' ], true ) ) {
			return File_Store::error( 'file_write', 500 );
		}
		$counts = [];
		foreach ( (array) ( $prepared['counts'] ?? [] ) as $key => $value ) {
			$counts[ sanitize_key( $key ) ] = (int) $value;
		}
		return [
			'path'   => $target,
			'format' => $format,
			'source' => sanitize_key( $prepared['source'] ?? 'custom' ),
			'counts' => $counts,
		];
	}

	/**
	 * Commit up to 100 complete chunks or 8 MiB; one larger chunk is indivisible.
	 *
	 * A failed/crashed append is truncated to the last durable byte checkpoint.
	 * Caller owns the file lock. The private output must not be externally edited.
	 *
	 * @param array  $record Session state, updated after publication.
	 * @param string $path Private assembly output.
	 * @return bool|\WP_Error Whether all chunks have been assembled.
	 */
	private function assemble_slice( array &$record, $path ) {
		$state  = $record['assembly'] ?? [
			'next'  => 0,
			'bytes' => 0,
		];
		$target = fopen( $path, 'c+b' );
		if ( ! $target ) {
			return File_Store::error( 'file_write', 500 ); }
		$published      = false;
		$append_started = false;
		try {
			if ( fstat( $target )['size'] < $state['bytes'] || ! ftruncate( $target, $state['bytes'] ) || 0 !== fseek( $target, $state['bytes'] ) ) {
				return File_Store::error( 'chunk_integrity', 409 );
			}
			$append_started = true;
			$next           = $state;
			while ( $next['next'] < $record['total_chunks'] && $next['next'] - $state['next'] < 100 && $next['bytes'] - $state['bytes'] < 8 * MB_IN_BYTES ) {
				$index    = $next['next'];
				$metadata = 'table' === ( $record['chunk_storage'] ?? '' ) ? File_Chunk_Store::get( $record['file_id'], $index ) : ( $record['chunks'][ $index ] ?? null );
				if ( is_wp_error( $metadata ) || null === $metadata ) {
					return File_Store::error( 'chunk_integrity', 409 ); }
				$source = fopen( dirname( $path ) . '/chunk-' . $index, 'rb' );
				if ( ! $source ) {
					return File_Store::error( 'file_read', 500 ); }
				try {
					$bytes = $this->copy_verified_chunk( $source, $target, $metadata, max( 0, $record['expected_bytes'] - $next['bytes'] ) );
				} finally {
					fclose( $source ); }
				if ( is_wp_error( $bytes ) ) {
					return $bytes; }
				$next['bytes'] += $bytes;
				++$next['next'];
			}
			$done = $next['next'] === $record['total_chunks'];
			if ( ! fflush( $target ) || ( $done && $next['bytes'] !== $record['expected_bytes'] ) ) {
				return File_Store::error( 'upload_size_mismatch' ); }
			$updated               = $record;
			$updated['assembly']   = $next;
			$updated['updated_at'] = time();
			$saved                 = File_Store::save( $updated );
			if ( is_wp_error( $saved ) ) {
				return $saved; }
			$record    = $updated;
			$published = true;
			return $done;
		} finally {
			if ( ! $published && $append_started ) {
				ftruncate( $target, $state['bytes'] ); }
			fclose( $target );
		}
	}

	/**
	 * Hash the bytes actually copied, using a bounded buffer and one source pass.
	 *
	 * @param resource $source Chunk stream.
	 * @param resource $target Assembled file stream.
	 * @param array    $metadata Committed byte count and digest.
	 * @param int      $remaining Remaining declared file capacity.
	 * @return int|\WP_Error
	 */
	private function copy_verified_chunk( $source, $target, array $metadata, $remaining ) {
		$limit = min( $remaining, $metadata['bytes'] );
		$total = 0;
		$hash  = hash_init( 'sha256' );
		while ( ! feof( $source ) ) {
			$buffer = fread( $source, min( 65536, $limit - $total + 1 ) );
			if ( false === $buffer || ( '' === $buffer && ! feof( $source ) ) ) {
				return File_Store::error( 'file_read', 500 );
			}
			$length = strlen( $buffer );
			if ( $length > $limit - $total ) {
				return File_Store::error( 'chunk_integrity', 409 );
			}
			if ( $length && fwrite( $target, $buffer ) !== $length ) {
				return File_Store::error( 'file_write', 500 );
			}
			hash_update( $hash, $buffer );
			$total += $length;
		}
		if ( $total !== $metadata['bytes'] || hash_final( $hash ) !== $metadata['sha256'] ) {
			return File_Store::error( 'chunk_integrity', 409 );
		}
		return $total;
	}

	/**
	 * Extract exactly one supported regular entry, bounded by actual output size.
	 *
	 * @param string $path ZIP source.
	 * @param string $dir Private resource directory.
	 * @return array|\WP_Error
	 */
	private function extract_zip( $path, $dir ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return File_Store::error( 'zip_unavailable', 503 );
		}
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return File_Store::error( 'zip_invalid' );
		}
		try {
			$entries = [];
			// phpcs:ignore WordPress.NamingConventions.ValidVariableName -- Native ZipArchive property.
			for ( $i = 0; $i < $zip->numFiles; ++$i ) {
				$stat = $zip->statIndex( $i );
				$name = str_replace( '\\', '/', $stat['name'] );
				if ( 0 === strpos( $name, '/' ) || preg_match( '~(^|/)\.\.(/|$)|^[a-zA-Z]:~', $name ) || false !== strpos( $name, "\0" ) ) {
					return File_Store::error( 'zip_unsafe_entry' );
				}
				$opsys = 0;
				$attr  = 0;
				$zip->getExternalAttributesIndex( $i, $opsys, $attr );
				if ( 0120000 === ( ( $attr >> 16 ) & 0170000 ) ) {
					return File_Store::error( 'zip_unsafe_entry' );
				}
				$format = strtolower( pathinfo( $name, PATHINFO_EXTENSION ) );
				if ( '/' !== substr( $name, -1 ) && false === strpos( $name, '__MACOSX/' ) && '.' !== substr( basename( $name ), 0, 1 ) && in_array( $format, [ 'csv', 'json', 'xml', 'xlsx', 'ods' ], true ) ) {
					if ( $entries ) {
						return File_Store::error( 'zip_content_limit' );
					}
					$entries[] = $stat + [ 'format' => $format ];
				}
			}
			if ( 1 !== count( $entries ) || $entries[0]['size'] > self::max_bytes() ) {
				return File_Store::error( 'zip_content_limit' );
			}
			$entry  = $entries[0];
			$output = $dir . '/extracted.' . $entry['format'];
			$source = $zip->getStream( $entry['name'] );
			$target = fopen( $output, 'wb' );
			if ( ! $source || ! $target ) {
				if ( $source ) {
					fclose( $source );
				}
				if ( $target ) {
					fclose( $target );
				}
				wp_delete_file( $output );
				return File_Store::error( 'zip_read' );
			}
			// A damaged archive makes the zip stream raise a PHP warning (CRC error). Report it as a
			// damaged file instead of writing the warning to the site's error log.
			$stream_error = false;
			set_error_handler( // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_set_error_handler -- Scoped to one stream copy.
				static function () use ( &$stream_error ) {
					$stream_error = true;
					return true;
				},
				E_WARNING
			);
			try {
				$copied = stream_copy_to_stream( $source, $target, min( $entry['size'], self::max_bytes() ) + 1 );
			} finally {
				restore_error_handler();
			}
			$flush = fflush( $target );
			fclose( $source );
			fclose( $target );
			if ( $stream_error ) {
				wp_delete_file( $output );
				return File_Store::error( 'zip_integrity' );
			}
			if ( ! $flush || $copied !== $entry['size'] || $copied > self::max_bytes() ) {
				wp_delete_file( $output );
				return File_Store::error( 'zip_size_mismatch' );
			}
			if ( hash_file( 'crc32b', $output ) !== sprintf( '%08x', $entry['crc'] ) ) {
				wp_delete_file( $output );
				return File_Store::error( 'zip_integrity' );
			}
			return [
				'path'      => $output,
				'file_name' => sanitize_file_name( basename( $entry['name'] ) ),
				'format'    => $entry['format'],
			];
		} finally {
			$zip->close();
		}
	}

	/**
	 * Re-preview any supported ready import, persisting parser choices for start.
	 *
	 * @param string $id File ID.
	 * @param array  $options Parser options.
	 * @return array|\WP_Error
	 */
	public function preview( $id, array $options = [] ) {
		return $this->locked(
			$id,
			function () use ( $id, $options ) {
				$record = $this->authorized( $id );
				if ( ! is_wp_error( $record ) && 'export' === $record['purpose'] ) {
					// Previewing parses import uploads; say so instead of a bare "not ready".
					return new \WP_Error( 'rsl_ie_export_file_preview', __( 'This is an export file, and only import uploads can be previewed. Use file-get for its download URL, or export-preview to read sample rows of the same export.', 'import-export-by-rockstarlab' ), [ 'status' => 409 ] );
				}
				$record = $this->resolve( $id, 'import' );
				return is_wp_error( $record ) ? $record : $this->preview_record( $record, $options );
			}
		);
	}

	/**
	 * Parse a trusted ready file using the same format handlers as import.
	 *
	 * @param array $record Server-owned file metadata.
	 * @param array $options Requested parser choices.
	 * @return array|\WP_Error
	 */
	private function preview_record( array $record, array $options ) {
		if ( ! empty( $record['migration_package'] ) ) {
			$valid = ( new Migration_Package_Service() )->inspect( $record['path'], true );
			if ( is_wp_error( $valid ) ) {
				return $valid; }
			$this->cleanup_ready_chunks( $record );
			return $this->describe( $record ) + [
				'headers'        => [],
				'preview'        => [],
				'total_rows'     => 0,
				'columns'        => 0,
				'parser_options' => $record['parser_options'],
			];
		}

		if ( in_array( $record['format'], [ 'xlsx', 'ods' ], true ) ) {
			$container = $this->check_spreadsheet_container( $record['path'] );
			if ( is_wp_error( $container ) ) {
				return $container;
			}
		}
		$settings = array_merge(
			$record['parser_options'],
			array_intersect_key(
				$options,
				[
					'delimiter'  => true,
					'has_header' => true,
					'encoding'   => true,
				]
			)
		);
		$encoding = \RockStarLab\ImportExport\Model\Format\CSV_Format::normalize_encoding( $settings['encoding'] ?? 'auto' );
		if ( null === $encoding ) {
			return File_Store::error( 'invalid_parser_options' );
		}
		$settings['encoding'] = $encoding;
		if ( ! is_string( $settings['delimiter'] ) ) {
			return File_Store::error( 'invalid_delimiter' );
		}
		if ( in_array( strtolower( $settings['delimiter'] ), [ 'tab', '\\t' ], true ) ) {
			$settings['delimiter'] = "\t";
		}
		if ( 1 !== strlen( $settings['delimiter'] ) || ! is_bool( $settings['has_header'] ) ) {
			return File_Store::error( 'invalid_parser_options' );
		}
		$parser = Format_Factory::create( $record['format'] );
		if ( is_wp_error( $parser ) ) {
			return $parser;
		}
		if ( in_array( $record['format'], [ 'csv', 'json', 'xlsx', 'ods' ], true ) ) {
			$summary = $parser->preview( $record['path'], $settings );
			if ( is_wp_error( $summary ) ) {
				return $summary;
			}
			$rows    = $summary['preview'];
			$headers = $summary['headers'];
			$count   = $summary['total_rows'];
		} elseif ( 'xml' === $record['format'] ) {
			// Derive all preview fields from the same fully validated document pass.
			$rows    = [];
			$headers = [];
			$count   = 0;
			try {
				foreach ( $parser->rows( $record['path'], $settings ) as $row ) {
					if ( ! is_array( $row ) ) {
						return File_Store::error( 'invalid_import_rows' );
					}
					if ( 0 === $count ) {
						$headers = array_keys( $row );
					}
					if ( $count < 5 ) {
						$rows[] = $row;
					}
					++$count;
				}
			} catch ( \Throwable $error ) {
				return new \WP_Error( 'xml_parse_error', __( 'Invalid XML file', 'import-export-by-rockstarlab' ) );
			}
		} else {
			$rows = $parser->parse_chunk( $record['path'], 0, 5, $settings );
			if ( is_wp_error( $rows ) ) {
				return $rows;
			}
			foreach ( $rows as $row ) {
				if ( ! is_array( $row ) ) {
					return File_Store::error( 'invalid_import_rows' );
				}
			}
			$headers = $parser->get_headers( $record['path'], $settings );
			$count   = $parser->count_rows( $record['path'], $settings );
			if ( is_wp_error( $headers ) || is_wp_error( $count ) ) {
				return is_wp_error( $headers ) ? $headers : $count;
			}
		}
		$record['parser_options'] = $settings;
		$record['updated_at']     = time();
		$saved                    = File_Store::save( $record );
		if ( is_wp_error( $saved ) ) {
			return $saved;
		}
		$this->cleanup_ready_chunks( $record );
		if ( 'zip' === strtolower( pathinfo( $record['original_name'], PATHINFO_EXTENSION ) ) ) {
			wp_delete_file( dirname( $record['path'] ) . '/source.zip' );
		}
		return $this->describe( $record ) + [
			'headers'        => $headers,
			'preview'        => $rows,
			'total_rows'     => (int) $count,
			'columns'        => count( $headers ),
			'parser_options' => $settings,
		];
	}

	/**
	 * Remove at most 100 obsolete upload chunks after publishing a ready file.
	 *
	 * @param array $record Persisted ready file metadata; caller holds its lock.
	 * @return void
	 */
	private function cleanup_ready_chunks( array $record ) {
		if ( 'ready' !== $record['status'] || empty( $record['path'] ) ) {
			return;
		}
		$dir = File_Store::directory( $record['file_id'] );
		if ( is_wp_error( $dir ) ) {
			return; }
		$attempted = 0;
		foreach ( File_Store::regular_files( $dir, 'chunk-' ) as $chunk ) {
			if ( ++$attempted > 100 ) {
				break; }
			wp_delete_file( $chunk );
		}
	}

	/**
	 * Bound compressed spreadsheet contents before invoking the workbook parser.
	 *
	 * @param string $path Private workbook path.
	 * @return true|\WP_Error
	 */
	public function check_spreadsheet_container( $path ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return File_Store::error( 'zip_unavailable', 503 );
		}
		$zip = new \ZipArchive();
		if ( true !== $zip->open( $path ) ) {
			return File_Store::error( 'spreadsheet_invalid' );
		}
		try {
			$count = $zip->numFiles; // phpcs:ignore WordPress.NamingConventions.ValidVariableName -- Native ZipArchive property.
			$bytes = 0;
			for ( $i = 0; $i < $count; ++$i ) {
				$stat = $zip->statIndex( $i );
				if ( ! is_array( $stat ) || ! isset( $stat['size'] ) || ! is_int( $stat['size'] ) || $stat['size'] < 0 ) {
					return File_Store::error( 'spreadsheet_invalid' ); }
				if ( $stat['size'] > self::max_bytes() - $bytes ) {
					return File_Store::error( 'zip_content_limit' );
				}
				$bytes += $stat['size'];
			}
			return true;
		} finally {
			$zip->close();
		}
	}

	/**
	 * Resolve a legacy wizard reference without accepting arbitrary disk paths.
	 *
	 * @param string $reference Opaque file reference.
	 * @return string|\WP_Error
	 */
	public function import_reference( $reference ) {
		$permission = Access_Policy::check_management();
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		if ( ! is_string( $reference ) ) {
			return File_Store::error( 'file_not_found', 404 );
		}
		$id = 0 === strpos( $reference, 'rsl-ie-file:' ) ? substr( $reference, 12 ) : $reference;
		return File_Store::valid_id( $id ) ? $id : File_Store::error( 'legacy_upload_requires_reupload', 409 );
	}

	/**
	 * Read authorized metadata, including ongoing upload progress.
	 *
	 * @param string $id File ID.
	 * @return array|\WP_Error
	 */
	public function get( $id ) {
		$record = $this->authorized( $id );
		return is_wp_error( $record ) ? $record : $this->describe( $record );
	}

	/**
	 * Resolve a ready file for trusted PHP services; paths stay out of transports.
	 *
	 * @param string $id File ID.
	 * @param string $purpose Server-selected purpose.
	 * @return array|\WP_Error
	 */
	public function resolve( $id, $purpose ) {
		$record = $this->authorized( $id );
		if ( is_wp_error( $record ) ) {
			return $record;
		}
		if ( 'ready' !== $record['status'] || $purpose !== $record['purpose'] ) {
			return File_Store::error( 'file_not_ready', 409 );
		}
		$dir  = File_Store::directory( $id );
		$path = isset( $record['path'] ) ? realpath( $record['path'] ) : false;
		if ( is_wp_error( $dir ) || ! $path || dirname( $path ) !== $dir || is_link( $record['path'] ) || ! is_file( $path ) || ! is_readable( $path ) ) {
			return File_Store::error( 'file_not_found', 404 );
		}
		if ( ! empty( $record['migration_sha256'] ) && ! hash_equals( $record['migration_sha256'], hash_file( 'sha256', $path ) ) ) {
			return File_Store::error( 'migration_file_changed', 409 ); }
		return $record;
	}

	/**
	 * Require the site administration boundary and recheck export entitlement.
	 *
	 * @param string $id File ID.
	 * @return array|\WP_Error
	 */
	private function authorized( $id ) {
		$permission = Access_Policy::check_management();
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		$record = File_Store::get( $id );
		if ( is_wp_error( $record ) ) {
			return $record;
		}
		$permission = Access_Policy::check_resource( $record['owner_id'], $record['blog_id'] );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		if ( ! empty( $record['migration_scope'] ) ) {
			$scope = \RockStarLab\ImportExport\Abilities\Migration_Plan::import_scope( $record['migration_scope'] );
			if ( is_wp_error( $scope ) ) {
				return $scope; }
		}
		if ( 'uploading' === $record['status'] && get_current_user_id() !== $record['owner_id'] ) {
			return File_Store::error( 'upload_owner', 403 );
		}
		if ( 'export' === $record['purpose'] ) {
			if ( ! rsl_ie()->Model->job->find( $record['job_id'] ) && ! empty( $record['retained_export_requests'] ) && is_array( $record['retained_export_requests'] ) ) {
				foreach ( $record['retained_export_requests'] as $request ) {
					$valid = is_array( $request ) ? Export_Request::normalize( $request ) : File_Store::error( 'export_not_ready', 409 );
					if ( is_wp_error( $valid ) ) {
						return $valid;
					}
				}
				return $record;
			}
			$job = $this->export_job( $record['job_id'] );
			if ( is_wp_error( $job ) ) {
				return $job;
			}
		}
		return $record;
	}

	/**
	 * Cancel an unfinished upload; completed files cannot be aborted.
	 *
	 * @param string $id File ID.
	 * @return array|\WP_Error
	 */
	public function abort( $id ) {
		return $this->locked(
			$id,
			function () use ( $id ) {
				$record = $this->authorized( $id );
				if ( is_wp_error( $record ) ) {
					return $record;
				}
				if ( ! in_array( $record['status'], [ 'uploading', 'aborted' ], true ) ) {
					return File_Store::error( 'upload_state_conflict', 409 );
				}
				$removed = File_Store::remove( $id );
				if ( is_wp_error( $removed ) ) {
					return $removed;
				}
				unset( $record['assembly'] );
				$record['status']          = 'aborted';
				$record['chunks']          = [];
				$record['received_bytes']  = 0;
				$record['received_chunks'] = 0;
				$record['updated_at']      = time();
				$saved                     = File_Store::save( $record );
				return is_wp_error( $saved ) ? $saved : $this->describe( $record );
			}
		);
	}

	/**
	 * Run under a connection-owned site/resource lock, including failure paths.
	 *
	 * @param string   $id File ID.
	 * @param callable $callback Critical section.
	 * @return mixed
	 */
	private function locked( $id, callable $callback ) {
		if ( ! File_Store::valid_id( $id ) ) {
			return File_Store::error( 'file_not_found', 404 );
		}
		$lock = Job_Lock::acquire_resource( 'file', $id );
		if ( is_wp_error( $lock ) ) {
			return $lock;
		}
		if ( false === $lock ) {
			return File_Store::error( 'file_busy', 409 );
		}
		try {
			return $callback();
		} finally {
			Job_Lock::release( $lock );
		}
	}

	/**
	 * Public metadata never includes local paths or identity supplied by callers.
	 *
	 * @param array $record Trusted metadata.
	 * @return array
	 */
	private function describe( array $record ) {
		$result = array_intersect_key( $record, array_flip( [ 'file_id', 'file_name', 'format', 'purpose', 'status', 'file_size', 'expected_bytes', 'total_chunks', 'converted_from', 'conversion' ] ) ) + [ 'received_chunks' => $record['received_chunks'] ?? count( $record['chunks'] ?? [] ) ];
		if ( 'export' === $record['purpose'] && 'ready' === $record['status'] ) {
			$result['download_url']         = rest_url( 'rsl-ie/v1/files/' . $record['file_id'] . '/download' );
			$result['browser_download_url'] = add_query_arg(
				[
					'action'  => 'rsl_ie_file_download',
					'file_id' => $record['file_id'],
				],
				admin_url( 'admin-post.php' )
			);
		}
		if ( isset( $record['assembly'] ) ) {
			$result['assembled_chunks'] = $record['assembly']['next'];
			$result['assembled_bytes']  = $record['assembly']['bytes'];
		}
		return $result;
	}

	/**
	 * Authorize a persisted completed export before adopting or downloading it.
	 *
	 * @param int $id Job ID.
	 * @return object|\WP_Error
	 */
	private function export_job( $id ) {
		$permission = Access_Policy::check_management();
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		$job = rsl_ie()->Model->job->find( (int) $id );
		if ( Migration_Service::supports( $job ) && 'migration_export' === $job->type && 'completed' === $job->status ) {
			$access = ( new Migration_Service() )->authorize( $job );
			return is_wp_error( $access ) ? $access : $job;
		}
		if ( ! $job || 'export' !== $job->type || 'completed' !== $job->status ) {
			return File_Store::error( 'export_not_ready', 409 );
		}
		$params     = json_decode( $job->parameters, true );
		$permission = Access_Policy::check_resource( $job->user_id, $params['execution_context']['blog_id'] ?? get_current_blog_id() );
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		$request = Export_Request::normalize( (array) $params );
		return is_wp_error( $request ) ? $request : $job;
	}

	/**
	 * Adopt a completed export into private storage, preserving old job history.
	 *
	 * @param int $job_id Export job ID.
	 * @return array|\WP_Error
	 */
	public function from_job( $job_id ) {
		$job = $this->export_job( $job_id );
		if ( is_wp_error( $job ) ) {
			return $job;
		}
		$id = 'file_' . md5( 'export:' . get_current_blog_id() . ':' . $job_id );
		return $this->locked(
			$id,
			function () use ( $id, $job ) {
				$record = File_Store::get( $id );
				if ( ! is_wp_error( $record ) && 'ready' === $record['status'] ) {
					$record = $this->resolve( $id, 'export' );
					return is_wp_error( $record ) ? $record : $this->describe( $record );
				}
				$source  = realpath( (string) $job->file_path );
				$uploads = wp_upload_dir();
				$legacy  = realpath( $uploads['basedir'] . '/import-export-by-rockstarlab-files' );
				$root    = File_Store::root();
				if ( is_wp_error( $root ) ) {
					return $root;
				}
				if ( ! $source || ! is_file( $source ) || ( 0 !== strpos( $source, $root . '/' ) && ( ! $legacy || 0 !== strpos( $source, $legacy . '/' ) ) ) ) {
					return File_Store::error( 'file_not_found', 404 );
				}
				$dir = File_Store::directory( $id );
				if ( is_wp_error( $dir ) ) {
					return $dir;
				}
				$name = sanitize_file_name( basename( $source ) );
				$path = $dir . '/' . $name;
				if ( $source !== $path && ( ! copy( $source, $path ) || hash_file( 'sha256', $source ) !== hash_file( 'sha256', $path ) ) ) {
					return File_Store::error( 'file_write', 500 );
				}
				$record = [
					'file_id'    => $id,
					'owner_id'   => (int) $job->user_id,
					'blog_id'    => get_current_blog_id(),
					'purpose'    => 'export',
					'status'     => 'ready',
					'file_name'  => $name,
					'format'     => strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ),
					'path'       => $path,
					'file_size'  => filesize( $path ),
					'job_id'     => (int) $job->id,
					'created_at' => time(),
					'updated_at' => time(),
				];
				$saved  = File_Store::save( $record );
				return is_wp_error( $saved ) ? $saved : $this->describe( $record );
			}
		);
	}

	/**
	 * Resolve a legacy migration selection through an actual completed export job.
	 *
	 * @param string $path Path returned by the existing job UI.
	 * @return array|\WP_Error Authorized export file record.
	 */
	public function export_reference( $path ) {
		$permission = Access_Policy::check_management();
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		if ( ! is_string( $path ) ) {
			return File_Store::error( 'file_not_found', 404 );
		}
		global $wpdb;
		$job_id = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE file_path = %s AND type = %s AND status = %s ORDER BY id DESC LIMIT 1', $wpdb->prefix . 'rsl_ie_jobs', $path, 'export', 'completed' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Translate a legacy selection using persisted job ownership, never directory membership alone.
		if ( ! $job_id ) {
			return File_Store::error( 'file_not_found', 404 );
		}
		$file = $this->from_job( $job_id );
		return is_wp_error( $file ) ? $file : $this->resolve( $file['file_id'], 'export' );
	}

	/**
	 * Allocate a private output directory for a trusted export worker.
	 *
	 * @param int    $job_id Job ID.
	 * @param string $name Sanitized filename.
	 * @return array|\WP_Error
	 */
	public static function export_target( $job_id, $name ) {
		$id  = 'file_' . md5( 'export:' . get_current_blog_id() . ':' . $job_id );
		$dir = File_Store::directory( $id );
		if ( is_wp_error( $dir ) ) {
			return $dir;
		}
		if ( is_wp_error( File_Store::get( $id ) ) ) {
			$job = rsl_ie()->Model->job->find( (int) $job_id );
			if ( ! $job || 'export' !== $job->type ) {
				return File_Store::error( 'export_not_ready', 409 );
			}
			$saved = File_Store::save(
				[
					'file_id'    => $id,
					'job_id'     => (int) $job_id,
					'owner_id'   => (int) $job->user_id,
					'blog_id'    => get_current_blog_id(),
					'purpose'    => 'export',
					'status'     => 'writing',
					'created_at' => time(),
					'updated_at' => time(),
				]
			);
			if ( is_wp_error( $saved ) ) {
				return $saved;
			}
		}
		return [
			'dir'     => $dir,
			'path'    => $dir . '/' . sanitize_file_name( $name ),
			'file_id' => $id,
		];
	}

	/**
	 * Open an authorized file or ZIP; the caller owns the returned stream.
	 *
	 * @param string $id File ID.
	 * @param bool   $zip_download Whether to wrap the export in ZIP.
	 * @return array|\WP_Error
	 */
	public function download( $id, $zip_download = false ) {
		return $this->locked(
			$id,
			function () use ( $id, $zip_download ) {
				$record = $this->resolve( $id, 'export' );
				if ( is_wp_error( $record ) ) {
					return $record;
				}
				$path = $record['path'];
				$name = $record['file_name'];
				if ( $zip_download ) {
					if ( ! class_exists( 'ZipArchive' ) ) {
						return File_Store::error( 'zip_unavailable', 503 );
					}
					$path = dirname( $path ) . '/download.zip';
					$zip  = new \ZipArchive();
					if ( true !== $zip->open( $path . '.tmp', \ZipArchive::CREATE | \ZipArchive::OVERWRITE ) ) {
						return File_Store::error( 'zip_write', 500 );
					}
					$added  = $zip->addFile( $record['path'], $name );
					$closed = $zip->close();
					if ( ! $added || ! $closed || ! rename( $path . '.tmp', $path ) ) {
						return File_Store::error( 'zip_write', 500 );
					}
					$name = pathinfo( $name, PATHINFO_FILENAME ) . '.zip';
				}
				$stream = fopen( $path, 'rb' );
				return $stream ? [
					'stream'       => $stream,
					'filename'     => $name,
					'file_size'    => fstat( $stream )['size'],
					'content_type' => $zip_download ? 'application/zip' : 'application/octet-stream',
				] : File_Store::error( 'file_read', 500 );
			}
		);
	}

	/**
	 * Cron cleanup, independent of a logged-in user; retain files referenced by jobs.
	 *
	 * @return int Number of removed records.
	 */
	public function cleanup() {
		global $wpdb;
		Migration_Entry_Index::cleanup();
		Archive_Step_Store::cleanup();
		XML_Row_Spool::cleanup();
		$removed = Media_Chunk_Store::cleanup();
		$cursor  = (int) get_option( 'rsl_ie_file_cleanup_cursor', 0 );
		$visited = 0;
		foreach ( File_Store::record_keys( $cursor ) as $option_id => $key ) {
			$before_cursor = $cursor;
			$cursor        = $option_id;
			++$visited;
			$id     = substr( $key, strlen( 'rsl_ie_file_' ) );
			$result = $this->locked(
				$id,
				function () use ( $id, $wpdb ) {
					$record = File_Store::get( $id );
					if ( is_wp_error( $record ) || get_current_blog_id() !== (int) $record['blog_id'] ) {
						return false;
					}
					$this->cleanup_ready_chunks( $record );
					if ( ! empty( $record['job_id'] ) ) {
						$linked_job = rsl_ie()->Model->job->find( $record['job_id'] );
						if ( $wpdb->last_error ) {
							return File_Store::error( 'file_read', 500 );
						}
						if ( $linked_job ) {
							return false;
						}
					}
					if ( ! empty( $record['archive_source'] ) && ! is_wp_error( File_Store::get( $record['archive_source'] ) ) ) {
						return false; }
					$ttl = 'ready' === $record['status'] ? 30 * DAY_IN_SECONDS : DAY_IN_SECONDS;
					if ( $record['updated_at'] + $ttl > time() ) {
						return false;
					}
					$referenced = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE file_path = %s OR parameters LIKE %s OR settings LIKE %s LIMIT 1', $wpdb->prefix . 'rsl_ie_jobs', $record['path'] ?? '__no_file_path__', '%' . $wpdb->esc_like( $id ) . '%', '%' . $wpdb->esc_like( $id ) . '%' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Retain referenced files, including paused jobs and historical downloads.
					if ( $wpdb->last_error ) {
						return File_Store::error( 'file_read', 500 );
					}
					if ( $referenced ) {
						return false;
					}
					$record['status'] = 'aborted';
					$saved            = File_Store::save( $record );
					return is_wp_error( $saved ) ? $saved : File_Store::remove( $id, 100 );
				}
			);
			if ( is_wp_error( $result ) && 'rsl_ie_file_read' === $result->get_error_code() ) {
				update_option( 'rsl_ie_file_cleanup_cursor', $before_cursor, false );
				return $removed;
			}
			if ( true === $result ) {
				++$removed;
			}
			if ( $visited >= 100 ) {
				update_option( 'rsl_ie_file_cleanup_cursor', $cursor, false );
				return $removed;
			}
		}
		update_option( 'rsl_ie_file_cleanup_cursor', 0, false );
		return $removed;
	}
}

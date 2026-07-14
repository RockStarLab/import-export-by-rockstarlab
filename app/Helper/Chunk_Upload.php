<?php
/**
 * Chunk Upload Handler
 * Handles chunked file uploads to bypass PHP upload limits
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

class Chunk_Upload {

	/**
	 * Temporary directory for chunked uploads.
	 *
	 * @var string
	 */
	private $chunks_dir;

	/**
	 * Final upload directory
	 *
	 * @var string
	 */
	private $upload_dir;

	/**
	 * Constructor
	 */
	public function __construct() {
		$upload_dir       = wp_upload_dir();
		$this->upload_dir = trailingslashit( $upload_dir['basedir'] ) . 'rsl-ie-imports/';
		$this->chunks_dir = trailingslashit( $upload_dir['basedir'] ) . 'rsl-ie-chunks/';

		// Create directories if they don't exist
		$this->ensure_directories();

		// Register AJAX handlers
		add_action( 'wp_ajax_rsl_ie_upload_chunk', array( $this, 'handle_chunk_upload' ) );
		add_action( 'wp_ajax_rsl_ie_finalize_upload', array( $this, 'handle_finalize_upload' ) );
		add_action( 'wp_ajax_rsl_ie_abort_upload', array( $this, 'handle_abort_upload' ) );
		add_action( 'wp_ajax_rsl_ie_reload_preview', array( $this, 'handle_reload_preview' ) );
		add_action( 'wp_ajax_rsl_ie_get_custom_post_types', array( $this, 'handle_get_custom_post_types' ) );

		foreach ( array( 'rsl_ie_upload_chunk', 'rsl_ie_finalize_upload', 'rsl_ie_abort_upload', 'rsl_ie_reload_preview', 'rsl_ie_get_custom_post_types' ) as $action ) {
			Ajax_Security::register_action( $action );
		}

		// Schedule cleanup
		if ( ! wp_next_scheduled( 'rsl_ie_cleanup_old_chunks' ) ) {
			wp_schedule_event( time(), 'daily', 'rsl_ie_cleanup_old_chunks' );
		}
		add_action( 'rsl_ie_cleanup_old_chunks', array( $this, 'cleanup_old_chunks' ) );
	}

	/**
	 * Ensure upload directories exist
	 */
	private function ensure_directories() {
		if ( ! file_exists( $this->upload_dir ) ) {
			wp_mkdir_p( $this->upload_dir );
			// Add index.php for security
			file_put_contents( $this->upload_dir . 'index.php', '<?php // Silence is golden' );
		}

		if ( ! file_exists( $this->chunks_dir ) ) {
			wp_mkdir_p( $this->chunks_dir );
			// Add index.php for security
			file_put_contents( $this->chunks_dir . 'index.php', '<?php // Silence is golden' );
		}
	}

	/**
	 * Handle chunk upload AJAX request
	 */
	public function handle_chunk_upload() {
		check_ajax_referer( Ajax_Security::nonce_action( 'rsl_ie_upload_chunk' ), 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'import-export-by-rockstarlab' ) );
		}

		// Validate required parameters
		$upload_id    = $this->validate_upload_id( sanitize_key( wp_unslash( $_POST['upload_id'] ?? '' ) ) );
		$chunk_index  = absint( $_POST['chunk_index'] ?? 0 );
		$total_chunks = absint( $_POST['total_chunks'] ?? 0 );
		$file_name    = sanitize_file_name( wp_unslash( $_POST['file_name'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Input is sanitized and validated in context.
		$file_size    = absint( $_POST['file_size'] ?? 0 );

		if ( empty( $upload_id ) || empty( $file_name ) || 0 === $total_chunks || $chunk_index >= $total_chunks ) {
			wp_send_json_error( __( 'Invalid parameters', 'import-export-by-rockstarlab' ) );
		}

		// Validate file extension.
		$allowed_extensions = $this->get_allowed_import_extensions();
		$file_extension     = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

		if ( ! in_array( $file_extension, $allowed_extensions, true ) ) {
			wp_send_json_error( __( 'Invalid file type. Supported formats: CSV, XML, XLSX, ODS, and ZIP archives containing one supported import file.', 'import-export-by-rockstarlab' ) );
		}

		// Check if chunk file was uploaded.
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Uploaded file arrays cannot be sanitized; validate before use.
		$chunk_upload = ( isset( $_FILES['chunk'] ) && is_array( $_FILES['chunk'] ) ) ? $_FILES['chunk'] : null;
		if ( empty( $chunk_upload ) || ! isset( $chunk_upload['error'] ) || (int) $chunk_upload['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( __( 'Failed to upload chunk', 'import-export-by-rockstarlab' ) );
		}

		// Create upload directory for this upload
		$upload_path = $this->chunks_dir . $upload_id . '/';
		if ( ! file_exists( $upload_path ) ) {
			wp_mkdir_p( $upload_path );

			// Store metadata
			$metadata = array(
				'upload_id'    => $upload_id,
				'file_name'    => $file_name,
				'file_size'    => $file_size,
				'total_chunks' => $total_chunks,
				'start_time'   => time(),
			);
			file_put_contents( $upload_path . 'metadata.json', wp_json_encode( $metadata ) );
		}

		// Save chunk
		$chunk_filename = 'chunk_' . str_pad( $chunk_index, 6, '0', STR_PAD_LEFT ) . '.part';
		$chunk_file     = $upload_path . $chunk_filename;

		if ( ! isset( $chunk_upload['tmp_name'] ) || ! is_string( $chunk_upload['tmp_name'] ) || ! is_uploaded_file( $chunk_upload['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Uploaded file path cannot be sanitized; validated by is_uploaded_file().
			wp_send_json_error( __( 'Invalid file upload', 'import-export-by-rockstarlab' ) );
		}

		// Use WordPress upload handler for core checks/filters.
		if ( file_exists( $chunk_file ) ) {
			wp_delete_file( $chunk_file );
		}

		$chunk_upload['name'] = $chunk_filename;

		$upload_dir = wp_upload_dir();
		$chunk_url  = str_replace( $upload_dir['basedir'], $upload_dir['baseurl'], untrailingslashit( $upload_path ) );

		$upload_dir_filter = static function ( $dirs ) use ( $upload_path, $chunk_url ) {
			$dirs['path']   = untrailingslashit( $upload_path );
			$dirs['url']    = untrailingslashit( $chunk_url );
			$dirs['subdir'] = '';
			return $dirs;
		};

		add_filter( 'upload_dir', $upload_dir_filter );
			$upload_result = wp_handle_upload(
				$chunk_upload,
				array(
					'test_form'                => false,
					// Chunk blobs may be detected as "application/octet-stream". We validate the
					// original filename extension above, so skip WordPress' MIME/type test here.
					'test_type'                => false,
					'unique_filename_callback' => static function ( $dir, $name, $ext ) use ( $chunk_filename ) {
						return $chunk_filename;
					},
				)
			);
			remove_filter( 'upload_dir', $upload_dir_filter );

		if ( isset( $upload_result['error'] ) ) {
			wp_send_json_error(
				sprintf(
					/* translators: %s: upload error message */
					__( 'Failed to save chunk: %s', 'import-export-by-rockstarlab' ),
					sanitize_text_field( (string) $upload_result['error'] )
				)
			);
		}

		wp_send_json_success(
			array(
				'chunk_index'  => $chunk_index,
				'total_chunks' => $total_chunks,
				// translators: %1$d is current chunk number, %2$d is total chunks count.
				'message'      => sprintf( __( 'Chunk %1$d of %2$d uploaded', 'import-export-by-rockstarlab' ), $chunk_index + 1, $total_chunks ),
			)
		);
	}

	/**
	 * Handle finalize upload AJAX request
	 */
	public function handle_finalize_upload() {
		check_ajax_referer( Ajax_Security::nonce_action( 'rsl_ie_finalize_upload' ), 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'import-export-by-rockstarlab' ) );
		}

		$upload_id    = $this->validate_upload_id( sanitize_key( wp_unslash( $_POST['upload_id'] ?? '' ) ) );
		$file_name    = sanitize_file_name( wp_unslash( $_POST['file_name'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Input is sanitized and validated in context.
		$total_chunks = absint( $_POST['total_chunks'] ?? 0 );

			// Get CSV options if provided
			$csv_options              = array(
				'delimiter'  => isset( $_POST['delimiter'] ) ? sanitize_text_field( wp_unslash( $_POST['delimiter'] ) ) : ',',
				'has_header' => isset( $_POST['has_header'] ) ? filter_var( wp_unslash( $_POST['has_header'] ), FILTER_VALIDATE_BOOLEAN ) : true,
			);
			$csv_options['delimiter'] = $this->normalize_csv_delimiter( $csv_options['delimiter'] );

			if ( empty( $upload_id ) || empty( $file_name ) || 0 === $total_chunks ) {
				wp_send_json_error( __( 'Invalid parameters', 'import-export-by-rockstarlab' ) );
			}

			if ( ! in_array( strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) ), $this->get_allowed_import_extensions(), true ) ) {
				wp_send_json_error( __( 'Invalid file type. Supported formats: CSV, XML, XLSX, ODS, and ZIP archives containing one supported import file.', 'import-export-by-rockstarlab' ) );
			}

			$upload_path = $this->chunks_dir . $upload_id . '/';

			if ( ! file_exists( $upload_path ) ) {
				wp_send_json_error( __( 'Upload not found', 'import-export-by-rockstarlab' ) );
			}

			$metadata_file = $upload_path . 'metadata.json';
			$metadata      = file_exists( $metadata_file ) ? json_decode( (string) file_get_contents( $metadata_file ), true ) : null;
			if (
				! is_array( $metadata ) ||
				$upload_id !== ( $metadata['upload_id'] ?? '' ) ||
				$file_name !== ( $metadata['file_name'] ?? '' ) ||
				$total_chunks !== absint( $metadata['total_chunks'] ?? 0 )
			) {
				wp_send_json_error( __( 'Upload metadata does not match the request.', 'import-export-by-rockstarlab' ) );
			}

			// Verify all chunks are present
			for ( $i = 0; $i < $total_chunks; $i++ ) {
				$chunk_file = $upload_path . 'chunk_' . str_pad( $i, 6, '0', STR_PAD_LEFT ) . '.part';
				if ( ! file_exists( $chunk_file ) ) {
					// translators: %d is the missing chunk number.
					wp_send_json_error( sprintf( __( 'Chunk %d is missing', 'import-export-by-rockstarlab' ), $i ) );
				}
			}

			// Merge chunks
			$final_file = $this->upload_dir . $file_name;

			// If file exists, add timestamp to make it unique
			if ( file_exists( $final_file ) ) {
				$file_info  = pathinfo( $file_name );
				$file_name  = $file_info['filename'] . '_' . time() . '.' . $file_info['extension'];
				$final_file = $this->upload_dir . $file_name;
			}

			$final_handle = fopen( $final_file, 'wb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Stream required for chunked merge.

			if ( ! $final_handle ) {
				wp_send_json_error( __( 'Failed to create final file', 'import-export-by-rockstarlab' ) );
			}

			// Merge all chunks
			for ( $i = 0; $i < $total_chunks; $i++ ) {
				$chunk_file = $upload_path . 'chunk_' . str_pad( $i, 6, '0', STR_PAD_LEFT ) . '.part';
				$chunk_data = file_get_contents( $chunk_file );

				if ( $chunk_data === false ) {
					fclose( $final_handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing stream opened above.
					wp_delete_file( $final_file );
					// translators: %d is the chunk number that failed to read.
					wp_send_json_error( sprintf( __( 'Failed to read chunk %d', 'import-export-by-rockstarlab' ), $i ) );
				}

				fwrite( $final_handle, $chunk_data ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Writing to stream opened above.
				unset( $chunk_data ); // Free memory
			}

			fclose( $final_handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing stream opened above.

			// Cleanup chunks
			$this->cleanup_upload( $upload_id );

			// Get file info
			$file_extension = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

			if ( 'zip' === $file_extension ) {
				$zip_result = Fs::extract_import_file_from_zip( $final_file, $this->upload_dir );
				wp_delete_file( $final_file );

				if ( is_wp_error( $zip_result ) ) {
					wp_send_json_error( $zip_result->get_error_message() );
				}

				$file_name      = $zip_result['file'];
				$final_file     = $zip_result['path'];
				$file_extension = $zip_result['format'];
			}

			$file_size = filesize( $final_file );
			$file_url  = str_replace( wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $final_file );

			// Generate preview with CSV options
			$preview_data = $this->generate_preview( $final_file, $file_extension, $csv_options );

			// Check if preview generation returned an error
			if ( isset( $preview_data['error'] ) ) {
				wp_send_json_success(
					array(
						'error'      => $preview_data['error'],
						'preview'    => isset( $preview_data['preview'] ) ? $preview_data['preview'] : array(),
						'total_rows' => isset( $preview_data['total_rows'] ) ? $preview_data['total_rows'] : 0,
						'columns'    => isset( $preview_data['columns'] ) ? $preview_data['columns'] : array(),
					)
				);
				return;
			}

			// Build success response
			$response = array(
				'file_name'  => $file_name,
				'file_path'  => $final_file,
				'file_url'   => $file_url,
				'file_size'  => $file_size,
				'format'     => $file_extension,
				'preview'    => $preview_data['preview'],
				'total_rows' => $preview_data['total_rows'],
				'columns'    => $preview_data['columns'],
				'message'    => __( 'File uploaded successfully', 'import-export-by-rockstarlab' ),
			);

			// Add warning if present
			if ( isset( $preview_data['warning'] ) && ! empty( $preview_data['warning'] ) ) {
				$response['warning'] = $preview_data['warning'];
			}

			wp_send_json_success( $response );
	}

	/**
	 * Handle abort upload AJAX request
	 */
	public function handle_abort_upload() {
		check_ajax_referer( Ajax_Security::nonce_action( 'rsl_ie_abort_upload' ), 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'import-export-by-rockstarlab' ) );
		}

		$upload_id = $this->validate_upload_id( sanitize_key( wp_unslash( $_POST['upload_id'] ?? '' ) ) );

		if ( empty( $upload_id ) ) {
			wp_send_json_error( __( 'Invalid parameters', 'import-export-by-rockstarlab' ) );
		}

		$this->cleanup_upload( $upload_id );

		wp_send_json_success( array( 'message' => __( 'Upload aborted', 'import-export-by-rockstarlab' ) ) );
	}

	/**
	 * Handle reload preview AJAX request
	 */
	public function handle_reload_preview() {
		check_ajax_referer( Ajax_Security::nonce_action( 'rsl_ie_reload_preview' ), 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'import-export-by-rockstarlab' ) );
		}

		$file_path      = sanitize_text_field( wp_unslash( $_POST['file_path'] ?? '' ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Input is sanitized and validated in context.
			$delimiter  = isset( $_POST['delimiter'] ) ? sanitize_text_field( wp_unslash( $_POST['delimiter'] ) ) : ','; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Input is sanitized and validated in context.
			$delimiter  = $this->normalize_csv_delimiter( $delimiter );
			$has_header = isset( $_POST['has_header'] ) ? filter_var( wp_unslash( $_POST['has_header'] ), FILTER_VALIDATE_BOOLEAN ) : true; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- Input is sanitized and validated in context.

		$real_file_path  = realpath( $file_path );
		$real_upload_dir = realpath( $this->upload_dir );
		if (
			false === $real_file_path ||
			false === $real_upload_dir ||
			0 !== strpos( $real_file_path, trailingslashit( $real_upload_dir ) ) ||
			'csv' !== strtolower( pathinfo( $real_file_path, PATHINFO_EXTENSION ) )
		) {
			wp_send_json_error( __( 'Invalid file path', 'import-export-by-rockstarlab' ) );
		}
		$file_path = $real_file_path;

		// Prepare CSV options
		$csv_options = array(
			'delimiter'  => $delimiter,
			'has_header' => $has_header,
		);

		// Regenerate preview with new options
		$preview_data = $this->generate_preview( $file_path, 'csv', $csv_options );

		if ( isset( $preview_data['error'] ) ) {
			wp_send_json_error( $preview_data['error'] );
		}

		wp_send_json_success(
			array(
				'preview'    => $preview_data['preview'],
				'columns'    => $preview_data['columns'],
				'total_rows' => $preview_data['total_rows'],
				'message'    => __( 'Preview reloaded successfully', 'import-export-by-rockstarlab' ),
			)
		);
	}

	/**
	 * Normalize delimiter values coming from UI / requests into a single character.
	 *
	 * @param mixed $delimiter Raw delimiter.
	 * @return string Normalized delimiter (never empty).
	 */
	private function normalize_csv_delimiter( $delimiter ) {
		if ( null === $delimiter ) {
			return ',';
		}

		if ( ! is_string( $delimiter ) ) {
			$delimiter = (string) $delimiter;
		}

		if ( '' === $delimiter ) {
			return ',';
		}

		$lower = strtolower( $delimiter );
		if ( 'tab' === $lower || '\\t' === $delimiter || "\t" === $delimiter ) {
			return "\t";
		}

		return $delimiter;
	}

	/**
	 * Get import file extensions accepted by the chunk uploader.
	 *
	 * @return array
	 */
	private function get_allowed_import_extensions() {
		return array( 'csv', 'xml', 'xlsx', 'ods', 'zip' );
	}

	/**
	 * Cleanup upload chunks
	 *
	 * @param string $upload_id Upload ID.
	 */
	private function cleanup_upload( $upload_id ) {
		$upload_id = $this->validate_upload_id( $upload_id );
		if ( '' === $upload_id ) {
			return;
		}

		$upload_path = $this->chunks_dir . $upload_id . '/';

		if ( file_exists( $upload_path ) ) {
			$files = glob( $upload_path . '*' );
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					wp_delete_file( $file );
				}
			}
			rmdir( $upload_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_rmdir -- Simple directory cleanup, no WP_Filesystem equivalent.
		}
	}

	/**
	 * Validate the client-generated upload identifier before using it in a path.
	 *
	 * @param mixed $upload_id Candidate upload ID.
	 * @return string Valid upload ID or an empty string.
	 */
	private function validate_upload_id( $upload_id ) {
		if ( ! is_string( $upload_id ) ) {
			return '';
		}

		$upload_id = strtolower( trim( $upload_id ) );
		return preg_match( '/\Aupload_[0-9]{10,16}_[a-z0-9]{5,20}\z/', $upload_id ) ? $upload_id : '';
	}

	/**
	 * Generate preview data from uploaded file
	 *
	 * @param string $file_path   File path.
	 * @param string $format      File format (csv, json, xml, xlsx, ods).
	 * @param array  $csv_options CSV options.
	 * @return array Preview data with headers and rows.
	 */
	private function generate_preview( $file_path, $format, $csv_options = array() ) {
		$preview_rows = 5; // Number of rows to preview
		$preview      = array(
			'headers' => array(),
			'data'    => array(),
		);
		$total_rows   = 0;
		$columns      = array();

		if ( in_array( $format, array( 'xlsx', 'ods', 'xml' ), true ) ) {
			$handler = \RockStarLab\ImportExport\Model\Format\Format_Factory::create( $format );
			if ( is_wp_error( $handler ) ) {
				return array(
					'error'      => $handler->get_error_message(),
					'preview'    => $preview,
					'total_rows' => 0,
					'columns'    => array(),
				);
			}

			$columns = $handler->get_headers( $file_path );
			if ( is_wp_error( $columns ) ) {
				return array(
					'error'      => $columns->get_error_message(),
					'preview'    => $preview,
					'total_rows' => 0,
					'columns'    => array(),
				);
			}

			$rows = $handler->parse_chunk( $file_path, 0, $preview_rows );
			if ( is_wp_error( $rows ) ) {
				return array(
					'error'      => $rows->get_error_message(),
					'preview'    => $preview,
					'total_rows' => 0,
					'columns'    => array(),
				);
			}

			$total_rows = $handler->count_rows( $file_path );
			if ( is_wp_error( $total_rows ) ) {
				$total_rows = count( $rows );
			}

			$preview['headers'] = $columns;
			foreach ( $rows as $row ) {
				$row_data = array();
				foreach ( $columns as $column ) {
					$row_data[] = isset( $row[ $column ] ) ? $row[ $column ] : '';
				}
				$preview['data'][] = $row_data;
			}
		} elseif ( 'csv' === $format ) {
			// Parse CSV with specified delimiter
			$delimiter  = isset( $csv_options['delimiter'] ) ? $csv_options['delimiter'] : ',';
			$has_header = isset( $csv_options['has_header'] ) ? $csv_options['has_header'] : true;

			$delimiter = $this->normalize_csv_delimiter( $delimiter );

			// Handle escape sequences in delimiter
			$delimiter = str_replace( array( '\t', '\n', '\r' ), array( "\t", "\n", "\r" ), $delimiter );
			$delimiter = $this->normalize_csv_delimiter( $delimiter );

			$handle = fopen( $file_path, 'r' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- Stream required for CSV parsing with fgetcsv.
			if ( ! $handle ) {
				return array(
					'preview'    => $preview,
					'total_rows' => 0,
					'columns'    => array(),
				);
			}

			// `fgetcsv()` requires a single-character delimiter.
			if ( '' === $delimiter || strlen( $delimiter ) !== 1 ) {
				$delimiter = ',';
			}

			// Read first row
			$first_row = fgetcsv( $handle, 0, $delimiter );

			if ( $first_row ) {
				if ( $has_header ) {
					// First row is header
					$preview['headers'] = $first_row;
					$columns            = $first_row;
				} else {
					// No header - generate column names
					$col_count = count( $first_row );
					for ( $i = 0; $i < $col_count; $i++ ) {
						$columns[]            = 'Column ' . ( $i + 1 );
						$preview['headers'][] = 'Column ' . ( $i + 1 );
					}
					// First row is data
					$preview['data'][] = $first_row;
				}
			}

			// Read preview rows
			$row_count = $has_header ? 0 : 1; // Already have first row if no header
			while ( ( $row = fgetcsv( $handle, 0, $delimiter ) ) !== false && $row_count < $preview_rows ) {
				$preview['data'][] = $row;
				++$row_count;
			}

			// Count total rows
			$total_rows = $row_count;
			if ( $has_header ) {
				++$total_rows; // +1 for header
			}

			while ( fgetcsv( $handle, 0, $delimiter ) !== false ) {
				++$total_rows;
			}

			fclose( $handle ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose -- Closing stream opened above.

		} elseif ( 'json' === $format ) {
			// Parse JSON
			$json_content = file_get_contents( $file_path );
			$data         = json_decode( $json_content, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return array(
					'error'      => __( 'Invalid JSON format', 'import-export-by-rockstarlab' ),
					'preview'    => $preview,
					'total_rows' => 0,
					'columns'    => array(),
				);
			}

			// Validate JSON structure
			$validation = $this->validate_json_structure( $data );
			if ( ! $validation['valid'] ) {
				return array(
					'error'      => $validation['error'],
					'preview'    => $preview,
					'total_rows' => 0,
					'columns'    => array(),
				);
			}

			if ( is_array( $data ) && ! empty( $data ) ) {
				$total_rows = count( $data );

				// Get first object for columns
				$first_row = reset( $data );
				if ( is_array( $first_row ) ) {
					$columns            = array_keys( $first_row );
					$preview['headers'] = $columns;

					// Convert first 5 objects to table rows (like CSV)
					$preview_rows = array_slice( $data, 0, 5 );
					$preview_data = array();

					foreach ( $preview_rows as $row ) {
						$row_data = array();
						foreach ( $columns as $col ) {
							$value = isset( $row[ $col ] ) ? $row[ $col ] : '';

							// Convert nested objects/arrays to JSON string for preview
							if ( is_array( $value ) || is_object( $value ) ) {
								$value = wp_json_encode( $value );
							}

							$row_data[] = $value;
						}
						$preview_data[] = $row_data;
					}

					$preview['data'] = $preview_data;
				}
			}

			// Add warning if present
			$warning = isset( $validation['warning'] ) ? $validation['warning'] : null;
		}

			return array(
				'preview'    => $preview,
				'total_rows' => $total_rows,
				'columns'    => $columns,
				'warning'    => isset( $warning ) ? $warning : null,
			);
	}

	/**
	 * Validate JSON structure for import
	 *
	 * Validates that JSON is suitable for import as tabular data.
	 *
	 * Allowed structure:
	 * - Array of objects: [{"field": "value"}, ...]
	 * - Object fields can be: strings, numbers, booleans, null
	 * - Object fields can also be: nested objects or arrays (will be serialized)
	 *
	 * Examples:
	 * Valid:   [{"id": 1, "title": "Post", "meta": {"views": 100}}]
	 * Valid:   [{"id": 1, "tags": ["tag1", "tag2"]}]
	 * Invalid: [{"id": 1, "data": {"meta": {"deep": {"too": "deep"}}}}]
	 *
	 * @param mixed $data Decoded JSON data.
	 * @return array Validation result with 'valid', 'error', and optional 'warning' keys.
	 */
	private function validate_json_structure( $data ) {
		// Must be an array
		if ( ! is_array( $data ) ) {
			return array(
				'valid' => false,
				'error' => __( 'JSON must be an array of objects. Example: [{"field1": "value1"}, {"field2": "value2"}]', 'import-export-by-rockstarlab' ),
			);
		}

		// Must not be empty
		if ( empty( $data ) ) {
			return array(
				'valid' => false,
				'error' => __( 'JSON file is empty', 'import-export-by-rockstarlab' ),
			);
		}

		// Check if it's an array of objects
		$first_item = reset( $data );
		if ( ! is_array( $first_item ) ) {
			return array(
				'valid' => false,
				'error' => __( 'JSON must contain an array of objects (associative arrays). Each item should have key-value pairs.', 'import-export-by-rockstarlab' ),
			);
		}

		// Check if it's associative array (has string keys)
		if ( array_values( $first_item ) === $first_item ) {
			return array(
				'valid' => false,
				'error' => __( 'JSON objects must have named fields (keys). Numeric arrays are not supported.', 'import-export-by-rockstarlab' ),
			);
		}

		// Check maximum nesting depth
		// Level 1: Array of objects [{}, {}]
		// Level 2: Object fields {"key": "value", "meta": {...}}
		// Level 3: Nested values (will be serialized) {"meta": {"views": 100}}
		// Maximum allowed: 2 levels (object fields can have nested objects/arrays as values)
		$max_depth = $this->get_array_depth( $first_item );
		if ( $max_depth > 2 ) {
			return array(
				'valid' => false,
				'error' => sprintf(
				/* translators: %d: current nesting depth */
					__( 'JSON structure is too deeply nested (depth: %d). Maximum allowed: array of flat objects with values. Nested values (objects/arrays) will be imported as serialized data. Example: [{"id": 1, "meta": {"key": "value"}}]', 'import-export-by-rockstarlab' ),
					$max_depth
				),
			);
		}

		// Validate all items have consistent structure
		$first_keys   = array_keys( $first_item );
		$inconsistent = false;

		foreach ( $data as $index => $item ) {
			if ( ! is_array( $item ) ) {
				return array(
					'valid' => false,
					'error' => sprintf(
					/* translators: %d: item index */
						__( 'Item at index %d is not an object. All items must be objects with the same structure.', 'import-export-by-rockstarlab' ),
						$index
					),
				);
			}

			// Check if keys match (warning only, not blocking)
			$item_keys = array_keys( $item );
			if ( count( array_diff( $first_keys, $item_keys ) ) > 0 ) {
				$inconsistent = true;
			}
		}

		// Return success with optional warning
		return array(
			'valid'   => true,
			'warning' => $inconsistent ? __( 'Note: Some objects have different fields. Missing fields will be treated as empty values.', 'import-export-by-rockstarlab' ) : null,
		);
	}

	/**
	 * Get maximum depth of nested array
	 *
	 * @param array $array Array to check.
	 * @param int   $depth Current depth.
	 * @return int Maximum depth.
	 */
	private function get_array_depth( $array, $depth = 1 ) {
		if ( ! is_array( $array ) ) {
			return $depth;
		}

		$max_depth = $depth;

		foreach ( $array as $value ) {
			if ( is_array( $value ) ) {
				$current_depth = $this->get_array_depth( $value, $depth + 1 );
				if ( $current_depth > $max_depth ) {
					$max_depth = $current_depth;
				}
			}
		}

		return $max_depth;
	}

	/**
	 * Cleanup old chunks (older than 24 hours)
	 */
	public function cleanup_old_chunks() {
		if ( ! file_exists( $this->chunks_dir ) ) {
			return;
		}

		$uploads = glob( $this->chunks_dir . '*', GLOB_ONLYDIR );
		$cutoff  = time() - ( 24 * HOUR_IN_SECONDS );

		foreach ( $uploads as $upload_path ) {
			$metadata_file = $upload_path . '/metadata.json';

			if ( file_exists( $metadata_file ) ) {
				$metadata = json_decode( file_get_contents( $metadata_file ), true );

				if ( isset( $metadata['start_time'] ) && $metadata['start_time'] < $cutoff ) {
					// Old upload, cleanup
					$upload_id = basename( $upload_path );
					$this->cleanup_upload( $upload_id );
				}
			} else {
				// No metadata, check directory modification time
				if ( filemtime( $upload_path ) < $cutoff ) {
					$upload_id = basename( $upload_path );
					$this->cleanup_upload( $upload_id );
				}
			}
		}
	}

	/**
	 * Get custom post types
	 */
	public function handle_get_custom_post_types() {
		// Verify nonce
		check_ajax_referer( Ajax_Security::nonce_action( 'rsl_ie_get_custom_post_types' ), 'nonce' );
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'import-export-by-rockstarlab' ), 403 );
		}

		// Get all public custom post types
		$args = array(
			'public'   => true,
			'_builtin' => false,
		);

		$post_types = get_post_types( $args, 'objects' );
		$result     = array();

		foreach ( $post_types as $post_type ) {
			$result[] = array(
				'name'  => $post_type->name,
				'label' => $post_type->label,
			);
		}

		wp_send_json_success( $result );
	}
}

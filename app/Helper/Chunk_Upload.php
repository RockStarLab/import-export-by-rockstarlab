<?php
/**
 * Chunk Upload Handler
 * Handles chunked file uploads to bypass PHP upload limits
 *
 * @package WP_AIE\Helper
 */

namespace WP_AIE\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Class Chunk_Upload
 */
class Chunk_Upload {

	/**
	 * Temporary d	public function handle_abort_upload() {
		check_ajax_referer( 'aie_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'wp-aie' ) );
		}

		$upload_id = sanitize_text_field( $_POST['upload_id'] ?? '' );

		if ( empty( $upload_id ) ) {
			wp_send_json_error( __( 'Invalid parameters', 'wp-aie' ) );
		}

		$this->cleanup_upload( $upload_id );

		wp_send_json_success( array( 'message' => __( 'Upload aborted', 'wp-aie' ) ) );
	}

	/**
	 * Handle reload preview AJAX request
	 */
	public function handle_reload_preview() {
		check_ajax_referer( 'aie_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'wp-aie' ) );
		}

		$file_path  = sanitize_text_field( $_POST['file_path'] ?? '' );
		$delimiter  = isset( $_POST['delimiter'] ) ? $_POST['delimiter'] : ',';
		$has_header = isset( $_POST['has_header'] ) ? filter_var( $_POST['has_header'], FILTER_VALIDATE_BOOLEAN ) : true;

		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			wp_send_json_error( __( 'Invalid file path', 'wp-aie' ) );
		}

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
				'message'    => __( 'Preview reloaded successfully', 'wp-aie' ),
			)
		);
	}

	/**
	 * Cleanup upload chunks	 *
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
		$this->upload_dir = trailingslashit( $upload_dir['basedir'] ) . 'wp-aie-imports/';
		$this->chunks_dir = trailingslashit( $upload_dir['basedir'] ) . 'wp-aie-chunks/';

		// Create directories if they don't exist
		$this->ensure_directories();

		// Register AJAX handlers
		add_action( 'wp_ajax_aie_upload_chunk', array( $this, 'handle_chunk_upload' ) );
		add_action( 'wp_ajax_aie_finalize_upload', array( $this, 'handle_finalize_upload' ) );
		add_action( 'wp_ajax_aie_abort_upload', array( $this, 'handle_abort_upload' ) );
		add_action( 'wp_ajax_aie_reload_preview', array( $this, 'handle_reload_preview' ) );

		// Schedule cleanup
		if ( ! wp_next_scheduled( 'aie_cleanup_old_chunks' ) ) {
			wp_schedule_event( time(), 'daily', 'aie_cleanup_old_chunks' );
		}
		add_action( 'aie_cleanup_old_chunks', array( $this, 'cleanup_old_chunks' ) );
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
		check_ajax_referer( 'aie_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'wp-aie' ) );
		}

		// Validate required parameters
		$upload_id    = sanitize_text_field( $_POST['upload_id'] ?? '' );
		$chunk_index  = absint( $_POST['chunk_index'] ?? 0 );
		$total_chunks = absint( $_POST['total_chunks'] ?? 0 );
		$file_name    = sanitize_file_name( $_POST['file_name'] ?? '' );
		$file_size    = absint( $_POST['file_size'] ?? 0 );

		if ( empty( $upload_id ) || empty( $file_name ) || $total_chunks === 0 ) {
			wp_send_json_error( __( 'Invalid parameters', 'wp-aie' ) );
		}

		// Validate file extension
		$allowed_extensions = array( 'csv', 'json' );
		$file_extension     = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

		if ( ! in_array( $file_extension, $allowed_extensions, true ) ) {
			wp_send_json_error( __( 'Invalid file type. Only CSV and JSON files are allowed.', 'wp-aie' ) );
		}

		// Check if chunk file was uploaded
		if ( ! isset( $_FILES['chunk'] ) || $_FILES['chunk']['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error( __( 'Failed to upload chunk', 'wp-aie' ) );
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
		$chunk_file = $upload_path . 'chunk_' . str_pad( $chunk_index, 6, '0', STR_PAD_LEFT );

		if ( ! move_uploaded_file( $_FILES['chunk']['tmp_name'], $chunk_file ) ) {
			wp_send_json_error( __( 'Failed to save chunk', 'wp-aie' ) );
		}

		wp_send_json_success(
			array(
				'chunk_index'  => $chunk_index,
				'total_chunks' => $total_chunks,
				'message'      => sprintf( __( 'Chunk %1$d of %2$d uploaded', 'wp-aie' ), $chunk_index + 1, $total_chunks ),
			)
		);
	}

	/**
	 * Handle finalize upload AJAX request
	 */
	public function handle_finalize_upload() {
		check_ajax_referer( 'aie_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'wp-aie' ) );
		}

		$upload_id    = sanitize_text_field( $_POST['upload_id'] ?? '' );
		$file_name    = sanitize_file_name( $_POST['file_name'] ?? '' );
		$total_chunks = absint( $_POST['total_chunks'] ?? 0 );
		
		// Get CSV options if provided
		$csv_options = array(
			'delimiter'  => isset( $_POST['delimiter'] ) ? $_POST['delimiter'] : ',',
			'has_header' => isset( $_POST['has_header'] ) ? filter_var( $_POST['has_header'], FILTER_VALIDATE_BOOLEAN ) : true,
		);

		if ( empty( $upload_id ) || empty( $file_name ) ) {
			wp_send_json_error( __( 'Invalid parameters', 'wp-aie' ) );
		}

		$upload_path = $this->chunks_dir . $upload_id . '/';

		if ( ! file_exists( $upload_path ) ) {
			wp_send_json_error( __( 'Upload not found', 'wp-aie' ) );
		}

		// Verify all chunks are present
		for ( $i = 0; $i < $total_chunks; $i++ ) {
			$chunk_file = $upload_path . 'chunk_' . str_pad( $i, 6, '0', STR_PAD_LEFT );
			if ( ! file_exists( $chunk_file ) ) {
				wp_send_json_error( sprintf( __( 'Chunk %d is missing', 'wp-aie' ), $i ) );
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

		$final_handle = fopen( $final_file, 'wb' );

		if ( ! $final_handle ) {
			wp_send_json_error( __( 'Failed to create final file', 'wp-aie' ) );
		}

		// Merge all chunks
		for ( $i = 0; $i < $total_chunks; $i++ ) {
			$chunk_file = $upload_path . 'chunk_' . str_pad( $i, 6, '0', STR_PAD_LEFT );
			$chunk_data = file_get_contents( $chunk_file );

			if ( $chunk_data === false ) {
				fclose( $final_handle );
				unlink( $final_file );
				wp_send_json_error( sprintf( __( 'Failed to read chunk %d', 'wp-aie' ), $i ) );
			}

			fwrite( $final_handle, $chunk_data );
			unset( $chunk_data ); // Free memory
		}

		fclose( $final_handle );

		// Cleanup chunks
		$this->cleanup_upload( $upload_id );

		// Get file info
		$file_size      = filesize( $final_file );
		$file_url       = str_replace( wp_upload_dir()['basedir'], wp_upload_dir()['baseurl'], $final_file );
		$file_extension = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );

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
			'message'    => __( 'File uploaded successfully', 'wp-aie' ),
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
		check_ajax_referer( 'aie_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Insufficient permissions', 'wp-aie' ) );
		}

		$upload_id = sanitize_text_field( $_POST['upload_id'] ?? '' );

		if ( empty( $upload_id ) ) {
			wp_send_json_error( __( 'Invalid parameters', 'wp-aie' ) );
		}

		$this->cleanup_upload( $upload_id );

		wp_send_json_success( array( 'message' => __( 'Upload aborted', 'wp-aie' ) ) );
	}

	/**
	 * Cleanup upload chunks
	 *
	 * @param string $upload_id Upload ID.
	 */
	private function cleanup_upload( $upload_id ) {
		$upload_path = $this->chunks_dir . $upload_id . '/';

		if ( file_exists( $upload_path ) ) {
			$files = glob( $upload_path . '*' );
			foreach ( $files as $file ) {
				if ( is_file( $file ) ) {
					unlink( $file );
				}
			}
			rmdir( $upload_path );
		}
	}

	/**
	 * Generate preview data from uploaded file
	 *
	 * @param string $file_path File path.
	 * @param string $format File format (csv, json).
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

		if ( 'csv' === $format ) {
			// Parse CSV with specified delimiter
			$delimiter  = isset( $csv_options['delimiter'] ) ? $csv_options['delimiter'] : ',';
			$has_header = isset( $csv_options['has_header'] ) ? $csv_options['has_header'] : true;
			
			// Handle escape sequences in delimiter
			$delimiter = str_replace( array( '\t', '\n', '\r' ), array( "\t", "\n", "\r" ), $delimiter );
			
			$handle = fopen( $file_path, 'r' );
			if ( ! $handle ) {
				return array(
					'preview'    => $preview,
					'total_rows' => 0,
					'columns'    => array(),
				);
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

			fclose( $handle );

		} elseif ( 'json' === $format ) {
			// Parse JSON
			$json_content = file_get_contents( $file_path );
			$data         = json_decode( $json_content, true );

			if ( json_last_error() !== JSON_ERROR_NONE ) {
				return array(
					'error'      => __( 'Invalid JSON format', 'wp-aie' ),
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
				'error' => __( 'JSON must be an array of objects. Example: [{"field1": "value1"}, {"field2": "value2"}]', 'wp-aie' ),
			);
		}

		// Must not be empty
		if ( empty( $data ) ) {
			return array(
				'valid' => false,
				'error' => __( 'JSON file is empty', 'wp-aie' ),
			);
		}

		// Check if it's an array of objects
		$first_item = reset( $data );
		if ( ! is_array( $first_item ) ) {
			return array(
				'valid' => false,
				'error' => __( 'JSON must contain an array of objects (associative arrays). Each item should have key-value pairs.', 'wp-aie' ),
			);
		}

		// Check if it's associative array (has string keys)
		if ( array_values( $first_item ) === $first_item ) {
			return array(
				'valid' => false,
				'error' => __( 'JSON objects must have named fields (keys). Numeric arrays are not supported.', 'wp-aie' ),
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
					__( 'JSON structure is too deeply nested (depth: %d). Maximum allowed: array of flat objects with values. Nested values (objects/arrays) will be imported as serialized data. Example: [{"id": 1, "meta": {"key": "value"}}]', 'wp-aie' ),
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
						__( 'Item at index %d is not an object. All items must be objects with the same structure.', 'wp-aie' ),
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
			'warning' => $inconsistent ? __( 'Note: Some objects have different fields. Missing fields will be treated as empty values.', 'wp-aie' ) : null,
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
}

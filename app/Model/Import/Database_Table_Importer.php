<?php
/**
 * Database Table Importer
 *
 * Handles importing data directly into WordPress database tables
 *
 * @package WP_AIE\Model\Import
 */

namespace WP_AIE\Model\Import;

defined( 'ABSPATH' ) || exit;

class Database_Table_Importer extends Abstract_Importer {

	/**
	 * Target table name
	 *
	 * @var string
	 */
	protected $table_name;

	/**
	 * Get importer name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'database_table';
	}

	/**
	 * Get importer description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Import data directly into WordPress database tables', 'wp-advanced-import-export' );
	}

	/**
	 * Get required fields
	 *
	 * @return array
	 */
	public function get_required_fields() {
		// No fixed required fields - depends on target table
		return [];
	}

	/**
	 * Get optional fields
	 *
	 * @return array
	 */
	public function get_optional_fields() {
		// Dynamic based on target table
		return [];
	}

	/**
	 * Get supported options
	 *
	 * @return array
	 */
	public function get_supported_options() {
		return [
			'table_name'      => 'Target database table name',
			'duplicate_mode'  => 'How to handle duplicates: skip, update, replace',
			'primary_key'     => 'Primary key field for duplicate detection',
			'batch_size'      => 'Number of rows to process per batch',
		];
	}

	/**
	 * Get default options
	 *
	 * @return array
	 */
	protected function get_default_options() {
		return array_merge(
			parent::get_default_options(),
			[
				'duplicate_mode' => 'update',
				'batch_size'     => 50,
			]
		);
	}

	/**
	 * Import data
	 *
	 * @param array $data    Data to import
	 * @param array $options Optional. Import options
	 * @return array|WP_Error Import results or WP_Error
	 */
	public function import( $data, $options = [] ) {
		$this->options = wp_parse_args( $options, $this->get_default_options() );
		
		// Set table name from options
		$this->table_name = $this->get_option( 'table_name', '' );
		
		if ( empty( $this->table_name ) ) {
			return new \WP_Error(
				'missing_table_name',
				__( 'Target table name is required', 'wp-advanced-import-export' )
			);
		}

		// Verify table exists
		if ( ! $this->table_exists( $this->table_name ) ) {
			return new \WP_Error(
				'table_not_found',
				sprintf(
					/* translators: %s: table name */
					__( 'Table does not exist: %s', 'wp-advanced-import-export' ),
					$this->table_name
				)
			);
		}

		return parent::import( $data, $options );
	}

	/**
	 * Import single item into database table
	 *
	 * @param array $item  Row data
	 * @param int   $index Item index
	 * @return int|string|WP_Error Row ID, 'skipped', 'updated', or WP_Error
	 */
	public function import_item( $item, $index ) {
		global $wpdb;

		// Get full table name (add prefix if not present)
		$table_name = $this->table_name;
		if ( strpos( $table_name, $wpdb->prefix ) !== 0 ) {
			$table_name = $wpdb->prefix . $table_name;
		}

		// Sanitize data
		$data = $this->sanitize_row_data( $item );

		if ( empty( $data ) ) {
			return new \WP_Error(
				'empty_data',
				__( 'No valid data to import', 'wp-advanced-import-export' )
			);
		}

		// Get user-specified unique field for checking existing items
		$unique_field = $this->get_option( 'unique_field', '' );
		
		$existing_id = null;
		$update_where = [];

		// If user specified a unique field, use it exclusively
		if ( ! empty( $unique_field ) && isset( $data[ $unique_field ] ) ) {
			$existing_id = $this->find_existing_row( $table_name, $unique_field, $data[ $unique_field ] );
			if ( $existing_id ) {
				$update_where = [ $unique_field => $data[ $unique_field ] ];
			}
		} else {
			// Otherwise, fall back to automatic detection
			
			// Get or detect primary key
			$primary_key = $this->get_option( 'primary_key', '' );
			
			// If no primary key specified, try to detect it
			if ( empty( $primary_key ) ) {
				$primary_key = $this->detect_primary_key( $table_name );
			}
			
			// Check for duplicate by primary key
			if ( ! empty( $primary_key ) && isset( $data[ $primary_key ] ) ) {
				$existing_id = $this->find_existing_row( $table_name, $primary_key, $data[ $primary_key ] );
				$update_where = [ $primary_key => $data[ $primary_key ] ];
			}
			
			// If no existing record found by primary key, check unique keys
			if ( ! $existing_id ) {
				$unique_keys = $this->detect_unique_keys( $table_name );
				foreach ( $unique_keys as $unique_key ) {
					if ( isset( $data[ $unique_key ] ) ) {
						$existing_id = $this->find_existing_row( $table_name, $unique_key, $data[ $unique_key ] );
						if ( $existing_id ) {
							$update_where = [ $unique_key => $data[ $unique_key ] ];
							break;
						}
					}
				}
			}
		}

		// Handle duplicate
		if ( $existing_id ) {
			$duplicate_mode = $this->get_option( 'duplicate_handling', 'update' );

			switch ( $duplicate_mode ) {
				case 'skip':
					return 'skipped';

				case 'update':
					// Update existing row
					$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
						$table_name,
						$data,
						$update_where
					);

					if ( false === $result ) {
						return new \WP_Error(
							'update_failed',
							sprintf(
								/* translators: %s: database error message */
								__( 'Failed to update row: %s', 'wp-advanced-import-export' ),
								$wpdb->last_error
							)
						);
					}

					return 'updated';

				case 'replace':
					// Delete and insert
					$wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
						$table_name,
						$update_where
					);
					break;
					
				case 'ignore':
				case 'create':
					// Create new record even if duplicate exists (don't check, just insert)
					// Fall through to insert below
					break;
			}
		} else {
			// No existing record found - check if_not_exists option
			$if_not_exists = $this->get_option( 'if_not_exists', 'create' );
			
			if ( 'skip' === $if_not_exists ) {
				return 'skipped';
			}
		}

		// Insert new row (if not skipped)
		$result = $wpdb->insert( $table_name, $data ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct DB query required here.

		if ( false === $result ) {
			return new \WP_Error(
				'insert_failed',
				sprintf(
					/* translators: %s: database error message */
					__( 'Failed to insert row: %s', 'wp-advanced-import-export' ),
					$wpdb->last_error
				)
			);
		}

		return $wpdb->insert_id;
	}

	/**
	 * Check if table exists
	 *
	 * @param string $table_name Table name (with or without prefix)
	 * @return bool True if table exists
	 */
	private function table_exists( $table_name ) {
		global $wpdb;

		// If table name doesn't start with prefix, add it
		$full_table_name = $table_name;
		if ( strpos( $table_name, $wpdb->prefix ) !== 0 ) {
			$full_table_name = $wpdb->prefix . $table_name;
		}
		
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var(
			$wpdb->prepare(
				'SHOW TABLES LIKE %s',
				$full_table_name
			)
		);

		return $result === $full_table_name;
	}

	/**
	 * Find existing row by primary key
	 *
	 * @param string $table_name Table name (with prefix)
	 * @param string $key_field  Primary key field name
	 * @param mixed  $key_value  Primary key value
	 * @return mixed Row ID or null
	 */
	private function find_existing_row( $table_name, $key_field, $key_value ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$result = $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here
			$wpdb->prepare(
				"SELECT {$key_field} FROM {$table_name} WHERE {$key_field} = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$key_value
			)
		);

		return $result;
	}

	/**
	 * Sanitize row data
	 *
	 * @param array $data Row data
	 * @return array Sanitized data
	 */
	private function sanitize_row_data( $data ) {
		$sanitized = [];

		foreach ( $data as $key => $value ) {
			// Skip empty keys
			if ( empty( $key ) ) {
				continue;
			}

			// Sanitize key
			$sanitized_key = sanitize_key( $key );

			// Skip invalid keys
			if ( empty( $sanitized_key ) ) {
				continue;
			}

			// Keep value as-is for database (wpdb will handle escaping)
			// But remove null bytes which can cause issues
			if ( is_string( $value ) ) {
				$value = str_replace( "\0", '', $value );
			}

			$sanitized[ $sanitized_key ] = $value;
		}

		return $sanitized;
	}

	/**
	 * Validate import data
	 *
	 * @param array $data Data to validate
	 * @return true|WP_Error True if valid or WP_Error
	 */
	public function validate( $data ) {
		if ( ! is_array( $data ) || empty( $data ) ) {
			return new \WP_Error(
				'invalid_data',
				__( 'Import data must be a non-empty array', 'wp-advanced-import-export' )
			);
		}

		// Check if all items are arrays
		foreach ( $data as $index => $item ) {
			if ( ! is_array( $item ) ) {
				return new \WP_Error(
					'invalid_item',
					sprintf(
						/* translators: %d: row number */
						__( 'Row %d must be an array', 'wp-advanced-import-export' ),
						$index + 1
					)
				);
			}
		}

		return true;
	}

	/**
	 * Prepare data for import
	 *
	 * @param array $raw_data Raw data from file
	 * @param array $mapping  Field mapping configuration
	 * @return array Prepared data
	 */
	public function prepare( $raw_data, $mapping = [] ) {
		$prepared = [];

		foreach ( $raw_data as $row ) {
			$prepared_row = $this->map_row( $row, $mapping );
			if ( ! empty( $prepared_row ) ) {
				$prepared[] = $prepared_row;
			}
		}

		return $prepared;
	}

	/**
	 * Map single row using field mapping
	 *
	 * @param array $row     Raw row data
	 * @param array $mapping Field mapping
	 * @return array Mapped row
	 */
	private function map_row( $row, $mapping ) {
		$mapped = [];

		if ( empty( $mapping ) ) {
			return $row;
		}

		foreach ( $mapping as $map ) {
			$source_field = $map['source_field'] ?? '';
			$target_field = $map['target_field'] ?? '';

			if ( empty( $target_field ) ) {
				continue;
			}

			// Get value from source
			$value = null;

			if ( isset( $map['source_index'] ) && isset( $row[ $map['source_index'] ] ) ) {
				$value = $row[ $map['source_index'] ];
			} elseif ( ! empty( $source_field ) && isset( $row[ $source_field ] ) ) {
				$value = $row[ $source_field ];
			}

			// Apply function if specified
			if ( ! empty( $map['function_id'] ) && function_exists( $map['function_id'] ) ) {
				$value = call_user_func( $map['function_id'], $value );
			}

			$mapped[ $target_field ] = $value;
		}

		return $mapped;
	}

	/**
	 * Detect primary key for a table
	 *
	 * @param string $table_name Full table name (with prefix)
	 * @return string Primary key field name or empty string
	 */
	private function detect_primary_key( $table_name ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$columns = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COLUMN_NAME 
				FROM INFORMATION_SCHEMA.COLUMNS 
				WHERE TABLE_SCHEMA = %s 
				AND TABLE_NAME = %s 
				AND COLUMN_KEY = 'PRI'
				LIMIT 1",
				DB_NAME,
				$table_name
			)
		);

		if ( ! empty( $columns ) && isset( $columns[0]->COLUMN_NAME ) ) {
			return $columns[0]->COLUMN_NAME;
		}

		return '';
	}

	/**
	 * Detect unique keys for a table
	 *
	 * @param string $table_name Full table name (with prefix)
	 * @return array Array of unique key field names
	 */
	private function detect_unique_keys( $table_name ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$columns = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT COLUMN_NAME 
				FROM INFORMATION_SCHEMA.COLUMNS 
				WHERE TABLE_SCHEMA = %s 
				AND TABLE_NAME = %s 
				AND COLUMN_KEY = 'UNI'",
				DB_NAME,
				$table_name
			)
		);

		$unique_keys = [];
		if ( ! empty( $columns ) ) {
			foreach ( $columns as $column ) {
				$unique_keys[] = $column->COLUMN_NAME;
			}
		}

		return $unique_keys;
	}
}

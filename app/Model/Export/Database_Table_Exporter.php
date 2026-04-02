<?php
/**
 * Database Table Exporter
 *
 * Handles exporting data from any MySQL table with dynamic field detection
 *
 * @package WP_AIE\Model\Export
 */

namespace WP_AIE\Model\Export;

defined( 'ABSPATH' ) || exit;

class Database_Table_Exporter extends Abstract_Exporter {
	/**
	 * Strictly normalize a table name for safe identifier interpolation.
	 *
	 * Database table identifiers cannot be safely passed through $wpdb->prepare()
	 * because placeholders are quoted. Instead we validate the table name and
	 * then interpolate it inside backticks.
	 *
	 * @param string $table_name Raw table name.
	 * @return string Normalized table name or empty string if invalid.
	 */
	protected function normalize_table_name( $table_name ) {
		$table_name = sanitize_text_field( (string) $table_name );
		$table_name = trim( $table_name );

		// Allow only typical MySQL identifier characters.
		if ( '' === $table_name || ! preg_match( '/^[A-Za-z0-9_]+$/', $table_name ) ) {
			return '';
		}

		return $table_name;
	}

	/**
	 * Get exporter name
	 *
	 * @return string
	 */
	public function get_name() {
		return 'database_table';
	}

	/**
	 * Get exporter description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Export data from any MySQL database table', 'advanced-import-export' );
	}

	/**
	 * Get supported export filters
	 *
	 * @return array
	 */
	public function get_supported_filters() {
		return [
			'table_name' => __( 'Table name to export', 'advanced-import-export' ),
			'limit'      => __( 'Number of records to export', 'advanced-import-export' ),
			'offset'     => __( 'Number of records to skip', 'advanced-import-export' ),
		];
	}

	/**
	 * Get available tables with row counts
	 *
	 * @return array Array of tables with format: ['table_name' => 'name', 'row_count' => count]
	 */
	public function get_available_tables() {
		global $wpdb;

		$tables = $wpdb->get_results( 'SHOW TABLES', ARRAY_N ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.

		if ( empty( $tables ) ) {
			$this->log_info( 'No tables found in database' );
			return [];
		}

		$result = [];
		foreach ( $tables as $table ) {
			$table_name = $table[0];

			// Get row count - use direct query since table names can't be prepared
			$count = $wpdb->get_var( "SELECT COUNT(*) FROM `{$table_name}`" ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct DB query required here.

			$result[] = [
				'table_name' => $table_name,
				'row_count'  => (int) $count,
				'label'      => $table_name . ' (' . number_format( (int) $count ) . ' rows)',
			];
		}

		$this->log_info( 'Found ' . count( $result ) . ' tables' );
		return $result;
	}

	/**
	 * Get columns for a specific table
	 *
	 * @param string $table_name Table name
	 * @return array Array of columns with name, type, and properties
	 */
	public function get_table_columns( $table_name ) {
		if ( empty( $table_name ) ) {
			return [];
		}

		global $wpdb;

		$table_name = $this->normalize_table_name( $table_name );
		if ( '' === $table_name ) {
			return [];
		}

		// Get column information
		$columns = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			$wpdb->prepare(
				'SELECT 
					COLUMN_NAME as name,
					DATA_TYPE as type,
					COLUMN_TYPE as full_type,
					IS_NULLABLE as nullable,
					COLUMN_KEY as `key`,
					COLUMN_DEFAULT as default_value,
					EXTRA as extra
				FROM INFORMATION_SCHEMA.COLUMNS
				WHERE TABLE_SCHEMA = %s
				AND TABLE_NAME = %s
				ORDER BY ORDINAL_POSITION',
				DB_NAME,
				$table_name
			)
		);

		if ( empty( $columns ) ) {
			return [];
		}

		$result = [];
		foreach ( $columns as $col ) {
			$result[] = [
				'name'          => $col->name,
				'type'          => $col->type,
				'full_type'     => $col->full_type,
				'nullable'      => $col->nullable === 'YES',
				'is_primary'    => $col->key === 'PRI',
				'is_numeric'    => $this->is_numeric_type( $col->type ),
				'is_string'     => $this->is_string_type( $col->type ),
				'is_date'       => $this->is_date_type( $col->type ),
				'default_value' => $col->default_value,
				'extra'         => $col->extra,
			];
		}

		return $result;
	}

	/**
	 * Get a stable column name for ordering results.
	 *
	 * @param string $table_name Table name.
	 * @return string
	 */
	protected function get_order_column( $table_name ) {
		$columns = $this->get_table_columns( $table_name );
		if ( empty( $columns ) ) {
			return '';
		}

		foreach ( $columns as $column ) {
			if ( ! empty( $column['is_primary'] ) ) {
				return $column['name'];
			}
		}

		return $columns[0]['name'] ?? '';
	}

	/**
	 * Check if column type is numeric
	 *
	 * @param string $type Column type
	 * @return bool
	 */
	protected function is_numeric_type( $type ) {
		$numeric_types = [ 'int', 'tinyint', 'smallint', 'mediumint', 'bigint', 'decimal', 'float', 'double' ];
		return in_array( strtolower( $type ), $numeric_types, true );
	}

	/**
	 * Check if column type is string
	 *
	 * @param string $type Column type
	 * @return bool
	 */
	protected function is_string_type( $type ) {
		$string_types = [ 'char', 'varchar', 'text', 'tinytext', 'mediumtext', 'longtext' ];
		return in_array( strtolower( $type ), $string_types, true );
	}

	/**
	 * Check if column type is date/time
	 *
	 * @param string $type Column type
	 * @return bool
	 */
	protected function is_date_type( $type ) {
		$date_types = [ 'date', 'datetime', 'timestamp', 'time', 'year' ];
		return in_array( strtolower( $type ), $date_types, true );
	}

	/**
	 * Get available fields for export (dynamic based on selected table)
	 *
	 * @return array
	 */
	public function get_available_fields() {
		// If table is specified in options, return its columns
		if ( ! empty( $this->options['table_name'] ) ) {
			$table_name = $this->normalize_table_name( $this->options['table_name'] );
			$columns    = $table_name ? $this->get_table_columns( $table_name ) : [];
			return array_column( $columns, 'name' );
		}

		return [];
	}

	/**
	 * Get default export fields (all columns from selected table)
	 *
	 * @return array
	 */
	public function get_default_fields() {
		return $this->get_available_fields();
	}

	/**
	 * Get total count of items
	 *
	 * @param array $options Optional. Export filters
	 * @return int
	 */
	public function get_count( $options = [] ) {

		if ( empty( $options['table_name'] ) ) {
			return 0;
		}

		global $wpdb;
		$table_name = $this->normalize_table_name( $options['table_name'] );
		if ( '' === $table_name ) {
			return 0;
		}

		// Validate table name exists
		$table_exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name )
		);
		if ( ! $table_exists ) {
			return 0;
		}

			// Fast path: no custom filters — use COUNT(*) directly.
			if ( empty( $options['filters'] ) || ! is_array( $options['filters'] ) ) {
				$count = $wpdb->get_var( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Identifier validated above.
					$wpdb->prepare( "SELECT COUNT(*) FROM `{$table_name}` WHERE 1 = %d", 1 ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifier validated above.
				);
				return (int) $count;
			}

			// Fetch all rows for filtering.
			$rows = $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Identifier validated above.
				$wpdb->prepare( "SELECT * FROM `{$table_name}` WHERE 1 = %d", 1 ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifier validated above.
				ARRAY_A
			);

		if ( empty( $rows ) ) {
			return 0;
		}

		$count = 0;
		foreach ( $rows as $row ) {
			if ( $this->passes_all_filters( $row, $options['filters'] ) ) {
				++$count;
			}
		}
		return $count;
	}

	/**
	 * Get data based on export options
	 *
	 * @param array $options Export options
	 * @return array|WP_Error
	 */
	public function get_data( $options = [] ) {
		if ( empty( $options['table_name'] ) ) {
			return new \WP_Error(
				'no_table_selected',
				__( 'No table selected for export', 'advanced-import-export' )
			);
		}

		global $wpdb;
		$table_name = $this->normalize_table_name( $options['table_name'] );
		if ( '' === $table_name ) {
			return new \WP_Error(
				'invalid_table_name',
				__( 'Invalid table name', 'advanced-import-export' )
			);
		}

		// Check if table exists
		$table_exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			$wpdb->prepare(
				'SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES 
				WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s',
				DB_NAME,
				$table_name
			)
		);

		if ( ! $table_exists ) {
			return new \WP_Error(
				'table_not_found',
				// translators: %s is a dynamic value.
				sprintf( __( 'Table %s not found', 'advanced-import-export' ), $table_name )
			);
		}

			$this->log_info( "Querying table: {$table_name}" );

			// Fetch all rows (stable ordering for deterministic exports).
			$order_column = $this->get_order_column( $table_name );
			if ( ! empty( $order_column ) && preg_match( '/^[A-Za-z0-9_]+$/', (string) $order_column ) ) {
				// $order_column is read from INFORMATION_SCHEMA and validated as a safe identifier.
				$rows = $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Identifiers validated above.
					$wpdb->prepare( "SELECT * FROM `{$table_name}` WHERE 1 = %d ORDER BY `{$order_column}`", 1 ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifiers validated above.
					ARRAY_A
				);
			} else {
				$rows = $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Identifier validated above.
					$wpdb->prepare( "SELECT * FROM `{$table_name}` WHERE 1 = %d", 1 ), // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Identifier validated above.
					ARRAY_A
				);
			}

		if ( empty( $rows ) ) {
			return [];
		}

		// Apply filters
		if ( ! empty( $options['filters'] ) && is_array( $options['filters'] ) ) {
			$rows = array_filter(
				$rows,
				function ( $row ) use ( $options ) {
					return $this->passes_all_filters( $row, $options['filters'] );
				}
			);
		}

		return array_values( $rows );
	}

	/**
	 * Get field value from row
	 *
	 * @param array  $row        Row data (associative array)
	 * @param string $field_name Field name
	 * @return mixed
	 */
	protected function get_field_value( $row, $field_name ) {
		return $row[ $field_name ] ?? '';
	}

	/**
	 * Check if row passes all filters
	 *
	 * @param array $row     Row data
	 * @param array $filters Filters array
	 * @return bool
	 */
	protected function passes_all_filters( $row, $filters ) {
		if ( empty( $filters ) ) {
			return true;
		}

		foreach ( $filters as $filter ) {
			$field_value = $this->get_field_value( $row, $filter['field'] );
			$condition   = $filter['condition'];
			$test_value  = $filter['value'] ?? '';

			if ( ! $this->check_condition( $field_value, $condition, $test_value ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Check if a condition matches
	 *
	 * @param mixed  $field_value The value to test
	 * @param string $condition   The condition type
	 * @param mixed  $test_value  The value to test against
	 * @return bool
	 */
	protected function check_condition( $field_value, $condition, $test_value ) {
		switch ( $condition ) {
			case 'equals':
				return $field_value == $test_value;

			case 'not_equals':
				return $field_value != $test_value;

			case 'contains':
				return stripos( (string) $field_value, (string) $test_value ) !== false;

			case 'not_contains':
				return stripos( (string) $field_value, (string) $test_value ) === false;

			case 'starts_with':
				return stripos( (string) $field_value, (string) $test_value ) === 0;

			case 'ends_with':
				$length = strlen( (string) $test_value );
				if ( $length === 0 ) {
					return true;
				}
				return substr( (string) $field_value, -$length ) === (string) $test_value;

			case 'greater':
				return (float) $field_value > (float) $test_value;

			case 'less':
				return (float) $field_value < (float) $test_value;

			case 'equals_or_greater':
				return (float) $field_value >= (float) $test_value;

			case 'equals_or_less':
				return (float) $field_value <= (float) $test_value;

			case 'between':
				$values = array_map( 'trim', explode( ',', (string) $test_value ) );
				if ( count( $values ) !== 2 ) {
					return false;
				}
				$min = (float) $values[0];
				$max = (float) $values[1];
				return (float) $field_value >= $min && (float) $field_value <= $max;

			case 'in':
				$values = array_map( 'trim', explode( ',', (string) $test_value ) );
				return in_array( (string) $field_value, $values, true );

			case 'not_in':
				$values = array_map( 'trim', explode( ',', (string) $test_value ) );
				return ! in_array( (string) $field_value, $values, true );

			case 'is_empty':
				return empty( $field_value );

			case 'is_not_empty':
				return ! empty( $field_value );

			default:
				return false;
		}
	}

	/**
	 * Process single item — overrides parent to keep the primary-key column
	 * in the result even when only a subset of fields is selected.
	 *
	 * The Content Updater passes force_include_id = true so that save helpers
	 * can identify which row to UPDATE.  For a DB table the "ID" is always the
	 * first column returned by the query.
	 *
	 * @param mixed $item  Row data (associative array)
	 * @param int   $index Row index
	 * @return mixed
	 */
	protected function process_item( $item, $index ) {
		$force_include_id = $this->get_option( 'force_include_id', false );

		// Remember the primary-key column (first column) before field filtering.
		$pk_column = null;
		$pk_value  = null;
		if ( $force_include_id && is_array( $item ) && ! empty( $item ) ) {
			$pk_column = array_key_first( $item );
			$pk_value  = $item[ $pk_column ];
		}

		$result = parent::process_item( $item, $index );

		// Re-inject the PK as the first key so get_item_id() and
		// save_database_item() can always find it.
		if ( $force_include_id && $pk_column !== null && is_array( $result ) ) {
			if ( ! array_key_exists( $pk_column, $result ) ) {
				$result = array_merge( [ $pk_column => $pk_value ], $result );
			} else {
				// Ensure it's first
				$result = array_merge( [ $pk_column => $result[ $pk_column ] ], $result );
			}
		}

		return $result;
	}

	/**
	 * Format data for export
	 *
	 * @param mixed $item   Data item
	 * @param array $options Export options
	 * @return array
	 */
	protected function format_data( $item, $options ) {
		// Data is already in array format from database
		return $item;
	}

	/**
	 * Validate export options
	 *
	 * @param array $options Export options
	 * @return true|WP_Error True if valid, WP_Error otherwise
	 */
	public function validate_options( $options ) {
		if ( empty( $options['table_name'] ) ) {
			return new \WP_Error(
				'missing_table_name',
				__( 'Table name is required', 'advanced-import-export' )
			);
		}

		return true;
	}
}

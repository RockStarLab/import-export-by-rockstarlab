<?php
/**
 * Base Model Class
 *
 * Provides common CRUD operations for all models.
 * Handles database interactions with wpdb.
 * All child models should extend this class and define $table_name.
 *
 * @package RockStarLab\ImportExport\Model
 */

namespace RockStarLab\ImportExport\Model;

defined( 'ABSPATH' ) || exit;

abstract class Model {

	/**
	 * Table name without WordPress prefix
	 * Must be defined in child classes
	 *
	 * @var string
	 */
	protected $table_name;

	/**
	 * Get full table name with WordPress prefix
	 *
	 * @return string Full table name with prefix
	 */
	protected function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . $this->table_name;
	}

	/**
	 * Get columns that may be used in dynamic SQL identifiers.
	 *
	 * Child models should extend this list for their own table schema.
	 *
	 * @return array
	 */
	protected function get_allowed_columns() {
		return [ 'id' ];
	}

	/**
	 * Resolve a requested column against the model allowlist.
	 *
	 * @param string $column   Requested column name.
	 * @param string $fallback Fallback column name.
	 * @return string Allowlisted column name, or an empty string when none is available.
	 */
	protected function get_allowed_column( $column, $fallback = 'id' ) {
		$allowed_columns = $this->get_allowed_columns();
		$column          = is_string( $column ) ? sanitize_key( $column ) : '';
		$fallback        = is_string( $fallback ) ? sanitize_key( $fallback ) : '';

		if ( in_array( $column, $allowed_columns, true ) ) {
			return $column;
		}

		if ( '' !== $fallback && in_array( $fallback, $allowed_columns, true ) ) {
			return $fallback;
		}

		return '';
	}

	/**
	 * Find a single record by ID
	 *
	 * @param int $id Record ID to find
	 * @return object|null Database row object or null if not found
	 */
	public function find( $id ) {
		global $wpdb;
		$table = esc_sql( $this->get_table_name() );

		return $wpdb->get_row( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			$wpdb->prepare( "SELECT * FROM `{$table}` WHERE id = %d", $id ) // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is controlled and SQL-escaped.
		);
	}

	/**
	 * Find records by column value
	 *
	 * @param string|array $column Column name to search, or column => value pairs.
	 * @param mixed        $value  Value to search for when $column is a string.
	 * @return array Array of matching records
	 */
	public function find_by( $column, $value = null ) {
		global $wpdb;
		$table = esc_sql( $this->get_table_name() );

		$where      = is_array( $column ) ? $column : [ $column => $value ];
		$conditions = [];
		$values     = [];

		foreach ( $where as $where_column => $where_value ) {
			$safe_column = esc_sql( $this->get_allowed_column( $where_column, '' ) );

			if ( '' === $safe_column ) {
				continue;
			}

			$conditions[] = "`{$safe_column}` = %s";
			$values[]     = $where_value;
		}

		if ( empty( $conditions ) ) {
			return [];
		}

		// Build the full SQL string first, then pass to prepare().
		// Column identifiers are validated against an allowlist and SQL-escaped above.
		// Values are passed as separate parameters to prepare() via the spread operator.
		$sql = 'SELECT * FROM `' . $table . '` WHERE ' . implode( ' AND ', $conditions );

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			$wpdb->prepare( $sql, ...$values ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is built from a static template and allowlisted, SQL-escaped identifiers only.
		);
	}

	/**
	 * Get all records with optional pagination and ordering
	 *
	 * @param array $args {
	 *     Optional. Array of query arguments.
	 *
	 *     @type int    $limit    Maximum number of records to return. Default 100.
	 *     @type int    $offset   Number of records to skip. Default 0.
	 *     @type string $order_by Column name to order by. Default 'id'.
	 *     @type string $order    Sort order 'ASC' or 'DESC'. Default 'DESC'.
	 * }
	 * @return array Array of database row objects
	 */
	public function all( $args = [] ) {
		global $wpdb;
		$table = esc_sql( $this->get_table_name() );

		$limit         = isset( $args['limit'] ) ? max( 0, intval( $args['limit'] ) ) : 100;
		$offset        = isset( $args['offset'] ) ? max( 0, intval( $args['offset'] ) ) : 0;
		$order_by      = isset( $args['order_by'] ) ? $this->get_allowed_column( $args['order_by'], 'id' ) : 'id';
		$safe_order_by = esc_sql( $order_by );
		$order         = isset( $args['order'] ) && in_array( strtoupper( $args['order'] ), [ 'ASC', 'DESC' ] )
			? strtoupper( $args['order'] )
			: 'DESC';
		$safe_order    = esc_sql( $order );

		return $wpdb->get_results( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			$wpdb->prepare(
				"SELECT * FROM `{$table}` ORDER BY `{$safe_order_by}` {$safe_order} LIMIT %d OFFSET %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table/order identifiers are allowlisted; limit values are prepared.
				$limit,
				$offset
			)
		);
	}

	/**
	 * Insert a new record into the database
	 *
	 * @param array $data Associative array of column => value pairs
	 * @return int|WP_Error Inserted record ID on success, WP_Error on failure
	 */
	public function insert( $data ) {
		global $wpdb;
		$table = $this->get_table_name();

		$formats = $this->get_formats( $data );

		$result = $wpdb->insert( $table, $data, $formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct DB query required here.

		if ( $result === false ) {
			return new \WP_Error( 'db_insert_error', $wpdb->last_error );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update an existing record by ID
	 *
	 * @param int   $id   Record ID to update
	 * @param array $data Associative array of column => value pairs to update
	 * @return int|WP_Error Number of rows affected on success, WP_Error on failure
	 */
	public function update( $id, $data ) {
		global $wpdb;
		$table = $this->get_table_name();

		$formats = $this->get_formats( $data );

		$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			$table,
			$data,
			[ 'id' => $id ],
			$formats,
			[ '%d' ]
		);

		if ( $result === false ) {
			return new \WP_Error( 'db_update_error', $wpdb->last_error );
		}

		return $result;
	}

	/**
	 * Delete a record by ID
	 *
	 * @param int $id Record ID to delete
	 * @return int|false Number of rows deleted on success, false on failure
	 */
	public function delete( $id ) {
		global $wpdb;
		$table = $this->get_table_name();

		return $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			$table,
			[ 'id' => $id ],
			[ '%d' ]
		);
	}

	/**
	 * Count records matching optional where conditions
	 *
	 * @param array $where Optional. Associative array of column => value pairs for WHERE clause
	 * @return int Number of matching records
	 */
	public function count( $where = [] ) {
		global $wpdb;
		$table = esc_sql( $this->get_table_name() );

		if ( empty( $where ) ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is SQL-escaped above; no user input involved.
		}

		$conditions = [];
		$values     = [];

		foreach ( $where as $column => $value ) {
			$safe_column = esc_sql( $this->get_allowed_column( $column, '' ) );

			if ( '' === $safe_column ) {
				continue;
			}

			$conditions[] = "`{$safe_column}` = %s";
			$values[]     = $value;
		}

		if ( empty( $conditions ) ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM `{$table}`" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name is SQL-escaped above; no user input involved.
		}

		// Build the full SQL string first, then pass to prepare().
		// Column identifiers are validated against an allowlist and SQL-escaped above.
		// Values are passed as separate parameters to prepare() via the spread operator.
		$sql = 'SELECT COUNT(*) FROM `' . $table . '` WHERE ' . implode( ' AND ', $conditions );

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			$wpdb->prepare( $sql, ...$values ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL is built from a static template and allowlisted, SQL-escaped identifiers only.
		);
	}

	/**
	 * Get wpdb format strings based on data types
	 * Automatically detects %d for integers, %f for floats, %s for strings
	 *
	 * @param array $data Associative array of data
	 * @return array Array of format strings (%d, %f, or %s)
	 */
	protected function get_formats( $data ) {
		$formats = [];

		foreach ( $data as $value ) {
			if ( is_int( $value ) ) {
				$formats[] = '%d';
			} elseif ( is_float( $value ) ) {
				$formats[] = '%f';
			} else {
				$formats[] = '%s';
			}
		}

		return $formats;
	}
}

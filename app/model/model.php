<?php
/**
 * Base Model Class
 *
 * Provides common CRUD operations for all models.
 * Handles database interactions with wpdb.
 * All child models should extend this class and define $table_name.
 *
 * @package WP_AIE\Model
 */

namespace WP_AIE\model;

/**
 * Base Model Class
 *
 * Abstract base class for all database models with common CRUD operations.
 *
 * @package WP_AIE\Model
 */
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
	 * Find a single record by ID
	 *
	 * @param int $id Record ID to find
	 * @return object|null Database row object or null if not found
	 */
	public function find( $id ) {
		global $wpdb;
		$table = $this->get_table_name();

		return $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id )
		);
	}

	/**
	 * Find records by column value
	 *
	 * @param string $column Column name to search
	 * @param mixed  $value  Value to search for
	 * @return array Array of matching records
	 */
	public function find_by( $column, $value ) {
		global $wpdb;
		$table = $this->get_table_name();

		return $wpdb->get_results(
			$wpdb->prepare( "SELECT * FROM {$table} WHERE {$column} = %s", $value )
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
		$table = $this->get_table_name();

		$limit    = isset( $args['limit'] ) ? intval( $args['limit'] ) : 100;
		$offset   = isset( $args['offset'] ) ? intval( $args['offset'] ) : 0;
		$order_by = isset( $args['order_by'] ) ? sanitize_key( $args['order_by'] ) : 'id';
		$order    = isset( $args['order'] ) && in_array( strtoupper( $args['order'] ), [ 'ASC', 'DESC' ] )
			? strtoupper( $args['order'] )
			: 'DESC';

		return $wpdb->get_results(
			"SELECT * FROM {$table} ORDER BY {$order_by} {$order} LIMIT {$limit} OFFSET {$offset}"
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

		$result = $wpdb->insert( $table, $data, $formats );

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

		$result = $wpdb->update(
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

		return $wpdb->delete(
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
		$table = $this->get_table_name();

		if ( empty( $where ) ) {
			return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
		}

		$conditions = [];
		$values     = [];

		foreach ( $where as $column => $value ) {
			$conditions[] = "{$column} = %s";
			$values[]     = $value;
		}

		$where_clause = implode( ' AND ', $conditions );
		$query        = $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}", $values );

		return (int) $wpdb->get_var( $query );
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

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

/**
 * Base model for custom database tables.
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
	 * Normalize dynamic where conditions to allowlisted table columns only.
	 *
	 * @param array $where Associative array of column => value pairs.
	 * @return array
	 */
	protected function normalize_where_conditions( $where ) {
		$normalized = [];

		foreach ( $where as $column => $value ) {
			$safe_column = $this->get_allowed_column( $column, '' );

			if ( '' === $safe_column ) {
				continue;
			}

			$normalized[ $safe_column ] = $value;
		}

		return $normalized;
	}

	/**
	 * Check a fetched row against additional normalized conditions.
	 *
	 * @param object $row        Database row object.
	 * @param array  $conditions Normalized column => value pairs.
	 * @return bool
	 */
	protected function row_matches_conditions( $row, $conditions ) {
		foreach ( $conditions as $column => $value ) {
			if ( ! isset( $row->{$column} ) || (string) $row->{$column} !== (string) $value ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Find a single record by ID
	 *
	 * @param int $id Record ID to find.
	 * @return object|null Database row object or null if not found
	 */
	public function find( $id ) {
		global $wpdb;
		$table = $this->get_table_name();

		return $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying the plugin's custom table by primary key.
			$wpdb->prepare( 'SELECT * FROM %i WHERE id = %d', $table, $id )
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
		$table = $this->get_table_name();

		$where      = is_array( $column ) ? $column : [ $column => $value ];
		$conditions = $this->normalize_where_conditions( $where );

		if ( empty( $conditions ) ) {
			return [];
		}

		$first_column = key( $conditions );
		$first_value  = reset( $conditions );
		$remaining    = $conditions;
		unset( $remaining[ $first_column ] );

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying the plugin's custom table with allowlisted columns.
			$wpdb->prepare(
				'SELECT * FROM %i WHERE %i = %s',
				$table,
				$first_column,
				$first_value
			)
		);

		if ( empty( $remaining ) ) {
			return $rows;
		}

		return array_values(
			array_filter(
				$rows,
				function ( $row ) use ( $remaining ) {
					return $this->row_matches_conditions( $row, $remaining );
				}
			)
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

		$limit    = isset( $args['limit'] ) ? max( 0, intval( $args['limit'] ) ) : 100;
		$offset   = isset( $args['offset'] ) ? max( 0, intval( $args['offset'] ) ) : 0;
		$order_by = isset( $args['order_by'] ) ? $this->get_allowed_column( $args['order_by'], 'id' ) : 'id';
		$order    = isset( $args['order'] ) && in_array( strtoupper( $args['order'] ), [ 'ASC', 'DESC' ], true )
			? strtoupper( $args['order'] )
			: 'DESC';
		if ( 'ASC' === $order ) {
			return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying the plugin's custom table with allowlisted ordering.
				$wpdb->prepare(
					'SELECT * FROM %i ORDER BY %i ASC LIMIT %d OFFSET %d',
					$table,
					$order_by,
					$limit,
					$offset
				)
			);
		}

		return $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Querying the plugin's custom table with allowlisted ordering.
			$wpdb->prepare(
				'SELECT * FROM %i ORDER BY %i DESC LIMIT %d OFFSET %d',
				$table,
				$order_by,
				$limit,
				$offset
			)
		);
	}

	/**
	 * Insert a new record into the database
	 *
	 * @param array $data Associative array of column => value pairs.
	 * @return int|WP_Error Inserted record ID on success, WP_Error on failure
	 */
	public function insert( $data ) {
		global $wpdb;
		$table = $this->get_table_name();

		$formats = $this->get_formats( $data );

		$result = $wpdb->insert( $table, $data, $formats ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Writing to the plugin's custom table.

		if ( false === $result ) {
			return new \WP_Error( 'db_insert_error', $wpdb->last_error );
		}

		return $wpdb->insert_id;
	}

	/**
	 * Update an existing record by ID
	 *
	 * @param int   $id   Record ID to update.
	 * @param array $data Associative array of column => value pairs to update.
	 * @return int|WP_Error Number of rows affected on success, WP_Error on failure
	 */
	public function update( $id, $data ) {
		global $wpdb;
		$table = $this->get_table_name();

		$formats = $this->get_formats( $data );

		$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Updating the plugin's custom table.
			$table,
			$data,
			[ 'id' => $id ],
			$formats,
			[ '%d' ]
		);

		if ( false === $result ) {
			return new \WP_Error( 'db_update_error', $wpdb->last_error );
		}

		return $result;
	}

	/**
	 * Delete a record by ID
	 *
	 * @param int $id Record ID to delete.
	 * @return int|false Number of rows deleted on success, false on failure
	 */
	public function delete( $id ) {
		global $wpdb;
		$table = $this->get_table_name();

		return $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Deleting from the plugin's custom table.
			$table,
			[ 'id' => $id ],
			[ '%d' ]
		);
	}

	/**
	 * Count records matching optional where conditions
	 *
	 * @param array $where Optional. Associative array of column => value pairs for WHERE clause.
	 * @return int Number of matching records
	 */
	public function count( $where = [] ) {
		global $wpdb;
		$table = $this->get_table_name();

		if ( empty( $where ) ) {
			return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Counting rows in the plugin's custom table.
				$wpdb->prepare( 'SELECT COUNT(*) FROM %i', $table )
			);
		}

		return count( $this->find_by( $where ) );
	}

	/**
	 * Get wpdb format strings based on data types
	 * Automatically detects %d for integers, %f for floats, %s for strings
	 *
	 * @param array $data Associative array of data.
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

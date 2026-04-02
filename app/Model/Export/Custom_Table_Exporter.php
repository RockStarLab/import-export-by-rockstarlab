<?php
/**
 * Custom Table Exporter
 *
 * Exports data from custom MySQL tables
 *
 * @package WP_AIE\Model\Export
 */

namespace WP_AIE\Model\Export;

defined( 'ABSPATH' ) || exit;

class Custom_Table_Exporter extends Abstract_Exporter {

	/**
	 * Get exporter name
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'Custom MySQL Table', 'amplified-import-export' );
	}

	/**
	 * Get exporter description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Export data from custom database tables', 'amplified-import-export' );
	}

	/**
	 * Get supported filters
	 *
	 * @return array
	 */
	public function get_supported_filters() {
		return [
			'table_name' => __( 'Table Name', 'amplified-import-export' ),
			'filters'    => __( 'Column Filters', 'amplified-import-export' ),
		];
	}

	/**
	 * Get available fields (dynamic based on selected table)
	 *
	 * @return array
	 */
	public function get_available_fields() {
		$table_name = $this->options['table_name'] ?? '';
		if ( empty( $table_name ) ) {
			return [];
		}

		return $this->get_table_columns( $table_name );
	}

	/**
	 * Get default fields (all columns)
	 *
	 * @return array
	 */
	public function get_default_fields() {
		return $this->get_available_fields();
	}

	/**
	 * Get default options
	 *
	 * @return array
	 */
	protected function get_default_options() {
		return [
			'table_name' => '',
			'filters'    => [],
			'limit'      => 0,
			'offset'     => 0,
		];
	}

	/**
	 * Validate export options
	 *
	 * @param array $options Export options
	 * @return true|\WP_Error True if valid or WP_Error
	 */
	public function validate_options( $options ) {
		if ( empty( $options['table_name'] ) ) {
			return new \WP_Error(
				'missing_table_name',
				__( 'Table name is required', 'amplified-import-export' )
			);
		}

		// Validate table exists and belongs to WordPress
		global $wpdb;
		if ( strpos( $options['table_name'], $wpdb->prefix ) !== 0 ) {
			return new \WP_Error(
				'invalid_table',
				__( 'Invalid table name', 'amplified-import-export' )
			);
		}

		return true;
	}

	/**
	 * Get count of items
	 *
	 * @param array $options Export options
	 * @return int
	 */
	public function get_count( $options = [] ) {
		global $wpdb;

		$options = wp_parse_args( $options, $this->get_default_options() );

		if ( empty( $options['table_name'] ) ) {
			return 0;
		}

		$table_name   = sanitize_text_field( $options['table_name'] );
		$where_clause = $this->build_where_clause( $options['filters'] ?? [], $table_name );

		// Build query with prepared statement
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare( 'SELECT COUNT(*) FROM `%1s`', $table_name ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder -- Direct DB query required here.
		if ( ! empty( $where_clause ) ) {
			$query .= ' WHERE ' . $where_clause;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$count = $wpdb->get_var( $query ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Direct DB query required here

		return absint( $count );
	}

	/**
	 * Get export data
	 *
	 * @param array $options Export options
	 * @return array
	 */
	public function get_data( $options = [] ) {
		global $wpdb;

		$options = wp_parse_args( $options, $this->get_default_options() );

		if ( empty( $options['table_name'] ) ) {
			return [];
		}

		$table_name   = sanitize_text_field( $options['table_name'] );
		$where_clause = $this->build_where_clause( $options['filters'] ?? [], $table_name );

		// Build query with prepared statement
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$query = $wpdb->prepare( 'SELECT * FROM `%1s`', $table_name ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnquotedComplexPlaceholder -- Direct DB query required here.
		if ( ! empty( $where_clause ) ) {
			$query .= ' WHERE ' . $where_clause;
		}

		// Add limit and offset
		if ( ! empty( $options['limit'] ) ) {
			$query .= $wpdb->prepare( ' LIMIT %d', absint( $options['limit'] ) );
			if ( ! empty( $options['offset'] ) ) {
				$query .= $wpdb->prepare( ' OFFSET %d', absint( $options['offset'] ) );
			}
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.PreparedSQL.NotPrepared -- Direct DB query required here

		return $results ?: [];
	}

	/**
	 * Process single item
	 *
	 * @param mixed $item  Item to process
	 * @param int   $index Item index
	 * @return array|null Processed item or null if skipped
	 */
	protected function process_item( $item, $index ) {
		// Custom table data is already in array format, no processing needed
		return $item;
	}

	/**
	 * Get table columns
	 *
	 * @param string $table_name Table name
	 * @return array
	 */
	private function get_table_columns( $table_name ) {
		global $wpdb;

		$columns = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			$wpdb->prepare(
				'SELECT COLUMN_NAME 
				FROM INFORMATION_SCHEMA.COLUMNS 
				WHERE TABLE_SCHEMA = %s AND TABLE_NAME = %s
				ORDER BY ORDINAL_POSITION',
				DB_NAME,
				$table_name
			)
		);

		$fields = [];
		foreach ( $columns as $column ) {
			$fields[ $column->COLUMN_NAME ] = $column->COLUMN_NAME;
		}

		return $fields;
	}

	/**
	 * Build WHERE clause from filters
	 *
	 * @param array  $filters    Filter array
	 * @param string $table_name Table name for field escaping
	 * @return string
	 */
	private function build_where_clause( $filters, $table_name = '' ) {
		if ( empty( $filters ) ) {
			return '';
		}

		global $wpdb;
		$conditions = [];

		foreach ( $filters as $filter ) {
			if ( empty( $filter['field'] ) || empty( $filter['condition'] ) ) {
				continue;
			}

			$field     = sanitize_key( $filter['field'] );
			$condition = $filter['condition'];
			$value     = $filter['value'] ?? '';

			// Check if field is likely a date field based on common naming patterns
			$is_date_field = preg_match( '/(date|time|created|updated|modified|published)$/i', $field );

			// Build condition based on type
			switch ( $condition ) {
				case 'equals':
					if ( $is_date_field ) {
						// For date fields, compare only the date part
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$conditions[] = $wpdb->prepare( 'DATE(`' . esc_sql( $field ) . '`) = %s', $value );
					} else {
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$conditions[] = $wpdb->prepare( '`' . esc_sql( $field ) . '` = %s', $value );
					}
					break;
				case 'not_equals':
					if ( $is_date_field ) {
						// For date fields, compare only the date part
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$conditions[] = $wpdb->prepare( 'DATE(`' . esc_sql( $field ) . '`) != %s', $value );
					} else {
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$conditions[] = $wpdb->prepare( '`' . esc_sql( $field ) . '` != %s', $value );
					}
					break;
				case 'in':
					// Split by comma and prepare IN clause
					$values = array_map(
						function ( $v ) {
							$v = trim( $v );
							// Remove surrounding quotes if present
							return trim( $v, '\'"' );
						},
						explode( ',', $value )
					);
					$values = array_filter( $values ); // Remove empty values
					if ( ! empty( $values ) ) {
						$placeholders = implode( ', ', array_fill( 0, count( $values ), '%s' ) );
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$conditions[] = $wpdb->prepare( '`' . esc_sql( $field ) . "` IN ($placeholders)", $values ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct DB query required here
					}
					break;
				case 'not_in':
					// Split by comma and prepare NOT IN clause
					$values = array_map(
						function ( $v ) {
							$v = trim( $v );
							// Remove surrounding quotes if present
							return trim( $v, '\'"' );
						},
						explode( ',', $value )
					);
					$values = array_filter( $values ); // Remove empty values
					if ( ! empty( $values ) ) {
						$placeholders = implode( ', ', array_fill( 0, count( $values ), '%s' ) );
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$conditions[] = $wpdb->prepare( '`' . esc_sql( $field ) . "` NOT IN ($placeholders)", $values ); // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare,WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct DB query required here
					}
					break;
				case 'contains':
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$conditions[] = $wpdb->prepare( '`' . esc_sql( $field ) . '` LIKE %s', '%' . $wpdb->esc_like( $value ) . '%' );
					break;
				case 'not_contains':
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$conditions[] = $wpdb->prepare( '`' . esc_sql( $field ) . '` NOT LIKE %s', '%' . $wpdb->esc_like( $value ) . '%' );
					break;
				case 'starts_with':
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$conditions[] = $wpdb->prepare( '`' . esc_sql( $field ) . '` LIKE %s', $wpdb->esc_like( $value ) . '%' );
					break;
				case 'ends_with':
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$conditions[] = $wpdb->prepare( '`' . esc_sql( $field ) . '` LIKE %s', '%' . $wpdb->esc_like( $value ) );
					break;
				case 'greater':
				case 'greater_than':
					if ( $is_date_field ) {
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$conditions[] = $wpdb->prepare( 'DATE(`' . esc_sql( $field ) . '`) > %s', $value );
					} else {
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$conditions[] = $wpdb->prepare( '`' . esc_sql( $field ) . '` > %s', $value );
					}
					break;
				case 'less':
				case 'less_than':
					if ( $is_date_field ) {
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$conditions[] = $wpdb->prepare( 'DATE(`' . esc_sql( $field ) . '`) < %s', $value );
					} else {
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$conditions[] = $wpdb->prepare( '`' . esc_sql( $field ) . '` < %s', $value );
					}
					break;
				case 'equals_or_greater':
				case 'greater_or_equal':
					if ( $is_date_field ) {
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$conditions[] = $wpdb->prepare( 'DATE(`' . esc_sql( $field ) . '`) >= %s', $value );
					} else {
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$conditions[] = $wpdb->prepare( '`' . esc_sql( $field ) . '` >= %s', $value );
					}
					break;
				case 'equals_or_less':
				case 'less_or_equal':
					if ( $is_date_field ) {
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$conditions[] = $wpdb->prepare( 'DATE(`' . esc_sql( $field ) . '`) <= %s', $value );
					} else {
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$conditions[] = $wpdb->prepare( '`' . esc_sql( $field ) . '` <= %s', $value );
					}
					break;
				case 'is_empty':
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$conditions[] = '(`' . esc_sql( $field ) . '` IS NULL OR `' . esc_sql( $field ) . "` = '')";
					break;
				case 'is_not_empty':
					// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
					$conditions[] = '(`' . esc_sql( $field ) . '` IS NOT NULL AND `' . esc_sql( $field ) . "` != '')";
					break;
				case 'between':
					if ( ! empty( $filter['value_from'] ) && ! empty( $filter['value_to'] ) ) {
						// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
						$conditions[] = $wpdb->prepare(
							'`' . esc_sql( $field ) . '` BETWEEN %s AND %s',
							$filter['value_from'],
							$filter['value_to']
						);
					}
					break;
			}
		}

		return implode( ' AND ', $conditions );
	}
}

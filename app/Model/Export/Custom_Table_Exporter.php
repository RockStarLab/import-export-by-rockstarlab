<?php
/**
 * Custom Table Exporter
 *
 * Exports data from custom MySQL tables
 *
 * @package WP_AIE\Model\Export
 */

namespace WP_AIE\Model\Export;

/**
 * Custom Table Exporter Class
 *
 * Handles export of custom database tables.
 *
 * @package WP_AIE\Model\Export
 */
class Custom_Table_Exporter extends Abstract_Exporter {

	/**
	 * Get exporter name
	 *
	 * @return string
	 */
	public function get_name() {
		return __( 'Custom MySQL Table', 'wp-advanced-import-export' );
	}

	/**
	 * Get exporter description
	 *
	 * @return string
	 */
	public function get_description() {
		return __( 'Export data from custom database tables', 'wp-advanced-import-export' );
	}

	/**
	 * Get supported filters
	 *
	 * @return array
	 */
	public function get_supported_filters() {
		return [
			'table_name' => __( 'Table Name', 'wp-advanced-import-export' ),
			'filters'    => __( 'Column Filters', 'wp-advanced-import-export' ),
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
				__( 'Table name is required', 'wp-advanced-import-export' )
			);
		}

		// Validate table exists and belongs to WordPress
		global $wpdb;
		if ( strpos( $options['table_name'], $wpdb->prefix ) !== 0 ) {
			return new \WP_Error(
				'invalid_table',
				__( 'Invalid table name', 'wp-advanced-import-export' )
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

		$where_clause = $this->build_where_clause( $options['filters'] ?? [] );

		$query = "SELECT COUNT(*) FROM `{$options['table_name']}`";
		if ( ! empty( $where_clause ) ) {
			$query .= ' WHERE ' . $where_clause;
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$count = $wpdb->get_var( $query );

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

		$where_clause = $this->build_where_clause( $options['filters'] ?? [] );

		$query = "SELECT * FROM `{$options['table_name']}`";
		if ( ! empty( $where_clause ) ) {
			$query .= ' WHERE ' . $where_clause;
		}

		// Add limit and offset
		if ( ! empty( $options['limit'] ) ) {
			$query .= ' LIMIT ' . absint( $options['limit'] );
			if ( ! empty( $options['offset'] ) ) {
				$query .= ' OFFSET ' . absint( $options['offset'] );
			}
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $query, ARRAY_A );

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

		$columns = $wpdb->get_results(
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
	 * @param array $filters Filter array
	 * @return string
	 */
	private function build_where_clause( $filters ) {
		if ( empty( $filters ) ) {
			return '';
		}

		global $wpdb;
		$conditions = [];

		foreach ( $filters as $filter ) {
			if ( empty( $filter['field'] ) || empty( $filter['condition'] ) ) {
				continue;
			}

			$field     = $filter['field'];
			$condition = $filter['condition'];
			$value     = $filter['value'] ?? '';

			// Build condition based on type
			switch ( $condition ) {
				case 'equals':
					$conditions[] = $wpdb->prepare( "`{$field}` = %s", $value );
					break;
				case 'not_equals':
					$conditions[] = $wpdb->prepare( "`{$field}` != %s", $value );
					break;
				case 'contains':
					$conditions[] = $wpdb->prepare( "`{$field}` LIKE %s", '%' . $wpdb->esc_like( $value ) . '%' );
					break;
				case 'not_contains':
					$conditions[] = $wpdb->prepare( "`{$field}` NOT LIKE %s", '%' . $wpdb->esc_like( $value ) . '%' );
					break;
				case 'starts_with':
					$conditions[] = $wpdb->prepare( "`{$field}` LIKE %s", $wpdb->esc_like( $value ) . '%' );
					break;
				case 'ends_with':
					$conditions[] = $wpdb->prepare( "`{$field}` LIKE %s", '%' . $wpdb->esc_like( $value ) );
					break;
				case 'greater':
				case 'greater_than':
					$conditions[] = $wpdb->prepare( "`{$field}` > %s", $value );
					break;
				case 'less':
				case 'less_than':
					$conditions[] = $wpdb->prepare( "`{$field}` < %s", $value );
					break;
				case 'equals_or_greater':
				case 'greater_or_equal':
					$conditions[] = $wpdb->prepare( "`{$field}` >= %s", $value );
					break;
				case 'equals_or_less':
				case 'less_or_equal':
					$conditions[] = $wpdb->prepare( "`{$field}` <= %s", $value );
					break;
				case 'is_empty':
					$conditions[] = "(`{$field}` IS NULL OR `{$field}` = '')";
					break;
				case 'is_not_empty':
					$conditions[] = "(`{$field}` IS NOT NULL AND `{$field}` != '')";
					break;
				case 'between':
					if ( ! empty( $filter['value_from'] ) && ! empty( $filter['value_to'] ) ) {
						$conditions[] = $wpdb->prepare(
							"`{$field}` BETWEEN %s AND %s",
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

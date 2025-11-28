<?php
/**
 * Custom Function Model
 *
 * Manages custom user functions in database
 *
 * @package WP_AIE\Model
 */

namespace WP_AIE\Model;

use WP_AIE\Helper\Function_Executor;
use WP_AIE\Helper\Logger;

/**
 * Custom Function Class
 *
 * CRUD operations for custom transformation functions
 *
 * @package WP_AIE\Model
 */
class Custom_Function extends Model {

	/**
	 * Table name (without prefix)
	 *
	 * @var string
	 */
	protected $table_name = 'aie_custom_functions';

	/**
	 * Function Executor instance
	 *
	 * @var Function_Executor
	 */
	private $executor;

	/**
	 * Logger instance
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->executor = new Function_Executor();
		$this->logger   = new Logger();
	}

	/**
	 * Create a new custom function
	 *
	 * @param array $data Function data
	 * @return int|\WP_Error Function ID or WP_Error on failure
	 */
	public function create( $data ) {
		global $wpdb;

		// Validate required fields
		if ( empty( $data['name'] ) || empty( $data['code'] ) ) {
			return new \WP_Error( 'missing_fields', __( 'Name and code are required', 'wp-aie' ) );
		}

		// Decode HTML entities from code
		$data['code'] = html_entity_decode( $data['code'], ENT_QUOTES, 'UTF-8' );

		// Validate code security
		$validation = $this->executor->validate_function_code( $data['code'] );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Prepare data
		$insert_data    = [
			'name'          => sanitize_text_field( $data['name'] ),
			'description'   => ! empty( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
			'function_code' => $data['code'], // Store as-is, already validated and decoded
			'source'        => ! empty( $data['source'] ) ? sanitize_text_field( $data['source'] ) : 'custom',
			'input_type'    => ! empty( $data['input_type'] ) ? sanitize_text_field( $data['input_type'] ) : 'string',
			'output_type'   => ! empty( $data['output_type'] ) ? sanitize_text_field( $data['output_type'] ) : 'string',
			'is_active'     => 1,
			'user_id'       => get_current_user_id(),
			'usage_count'   => 0,
			'created_at'    => current_time( 'mysql' ),
			'updated_at'    => current_time( 'mysql' ),
		];      $result = $wpdb->insert(
			$this->get_table_name(),
			$insert_data,
			[
				'%s', // name
				'%s', // description
				'%s', // function_code
				'%s', // source
				'%s', // input_type
				'%s', // output_type
				'%d', // is_active
				'%d', // user_id
				'%d', // usage_count
				'%s', // created_at
				'%s', // updated_at
			]
		);      if ( $result ) {
			$function_id = $wpdb->insert_id;

			$this->logger->log(
				0,
				'info',
				sprintf( 'Custom function created: %s (ID: %d)', $insert_data['name'], $function_id )
			);

			return $function_id;
		}

		return new \WP_Error(
			'db_error',
			__( 'Database error: Failed to save function', 'wp-aie' ),
			array( 'db_error' => $wpdb->last_error )
		);
	}

	/**
	 * Update existing function
	 *
	 * @param int   $id   Function ID
	 * @param array $data Function data
	 * @return bool|\WP_Error Success or WP_Error on failure
	 */
	public function update( $id, $data ) {
		global $wpdb;

		// Check if function exists
		$existing = $this->get( $id );
		if ( ! $existing ) {
			return new \WP_Error( 'not_found', __( 'Function not found', 'wp-aie' ) );
		}

		// Check permissions
		if ( ! $this->can_edit_function( $id ) ) {
			return new \WP_Error( 'permission_denied', __( 'You do not have permission to edit this function', 'wp-aie' ) );
		}

		// Decode HTML entities from code if provided
		if ( ! empty( $data['code'] ) ) {
			$data['code'] = html_entity_decode( $data['code'], ENT_QUOTES, 'UTF-8' );
		}

		// Validate code if provided
		if ( ! empty( $data['code'] ) ) {
			$validation = $this->executor->validate_function_code( $data['code'] );
			if ( is_wp_error( $validation ) ) {
				return $validation;
			}
		}

		// Prepare update data
		$update_data = [
			'updated_at' => current_time( 'mysql' ),
		];

		if ( isset( $data['name'] ) ) {
			$update_data['name'] = sanitize_text_field( $data['name'] );
		}

		if ( isset( $data['description'] ) ) {
			$update_data['description'] = sanitize_textarea_field( $data['description'] );
		}

		if ( isset( $data['code'] ) ) {
			$update_data['function_code'] = $data['code'];
		}

		if ( isset( $data['is_active'] ) ) {
			$update_data['is_active'] = (int) $data['is_active'];
		}

		if ( isset( $data['input_type'] ) ) {
			$update_data['input_type'] = sanitize_text_field( $data['input_type'] );
		}

		if ( isset( $data['output_type'] ) ) {
			$update_data['output_type'] = sanitize_text_field( $data['output_type'] );
		}

		$result = $wpdb->update(
			$this->get_table_name(),
			$update_data,
			array( 'id' => $id ),
			array_fill( 0, count( $update_data ), '%s' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			$this->logger->log(
				0,
				'info',
				sprintf( 'Custom function updated: ID %d', $id )
			);
			return true;
		}

		return new \WP_Error(
			'db_error',
			__( 'Database error: Failed to update function', 'wp-aie' ),
			array( 'db_error' => $wpdb->last_error )
		);
	}

	/**
	 * Delete function
	 *
	 * @param int $id Function ID
	 * @return bool Success
	 */
	public function delete( $id ) {
		global $wpdb;

		// Check permissions
		if ( ! $this->can_edit_function( $id ) ) {
			return false;
		}

		$result = $wpdb->delete(
			$this->get_table_name(),
			[ 'id' => $id ],
			[ '%d' ]
		);

		if ( $result ) {
			$this->logger->log(
				0,
				'info',
				sprintf( 'Custom function deleted: ID %d', $id )
			);
		}

		return (bool) $result;
	}

	/**
	 * Get function by ID
	 *
	 * @param int $id Function ID
	 * @return array|null Function data or null
	 */
	public function get( $id ) {
		global $wpdb;

		$function = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->get_table_name()} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$id
			),
			ARRAY_A
		);

		if ( $function ) {
			// Map database columns to expected format for backward compatibility
			$function = $this->map_db_to_output( $function );
		}

		return $function ?: null;
	}   /**
		 * Get all functions with optional filters
		 *
		 * @param array $args {
		 *     Optional. Arguments for filtering functions.
		 *
		 *     @type string $status   Filter by status (active, inactive)
		 *     @type string $category Filter by category
		 *     @type string $source   Filter by source (custom, library, snippet_key)
		 *     @type string $search   Search in name and description
		 *     @type string $orderby  Order by column (default: name)
		 *     @type string $order    Sort order (ASC, DESC) (default: ASC)
		 *     @type int    $limit    Limit results
		 *     @type int    $offset   Offset for pagination
		 * }
		 * @return array Functions array
		 */
	public function get_all( $args = [] ) {
		global $wpdb;

		$defaults = [
			'is_active' => '', // Filter by active status (1/0)
			'source'    => '', // Filter by source
			'category'  => '', // Filter by category (library/custom)
			'search'    => '', // Search in name/description
			'orderby'   => 'name',
			'order'     => 'ASC',
			'limit'     => 0,
			'offset'    => 0,
		];

		$args = wp_parse_args( $args, $defaults );

		// Build query
		$where_clauses = [];
		$where_values  = [];

		// Support old 'status' param for backward compatibility
		if ( ! empty( $args['status'] ) ) {
			$args['is_active'] = ( 'active' === $args['status'] ) ? 1 : 0;
		}

		// Support 'category' filter (library/custom)
		if ( ! empty( $args['category'] ) ) {
			if ( 'library' === $args['category'] ) {
				$where_clauses[] = 'source LIKE %s';
				$where_values[]  = 'library:%';
			} elseif ( 'custom' === $args['category'] ) {
				$where_clauses[] = 'source NOT LIKE %s';
				$where_values[]  = 'library:%';
			}
		}

		if ( '' !== $args['is_active'] && null !== $args['is_active'] ) {
			$where_clauses[] = 'is_active = %d';
			$where_values[]  = (int) $args['is_active'];
		}

		if ( ! empty( $args['source'] ) ) {
			$where_clauses[] = 'source = %s';
			$where_values[]  = $args['source'];
		}       if ( ! empty( $args['search'] ) ) {
			$where_clauses[] = '(name LIKE %s OR description LIKE %s)';
			$search_term     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where_values[]  = $search_term;
			$where_values[]  = $search_term;
		}       $where_sql = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';

		// Validate orderby
		$allowed_orderby = [ 'id', 'name', 'source', 'is_active', 'usage_count', 'created_at', 'updated_at' ];
		$orderby         = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'name';
		$order           = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';
		$query           = "SELECT * FROM {$this->get_table_name()} {$where_sql} ORDER BY {$orderby} {$order}";

		if ( $args['limit'] > 0 ) {
			$query .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $args['limit'], $args['offset'] );
		}

		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		$functions = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Map database columns to expected format
		if ( ! empty( $functions ) ) {
			$functions = array_map( [ $this, 'map_db_to_output' ], $functions );
		}
		return $functions ?: [];
	}

	/**
	 * Get functions count with filters
	 *
	 * @param array $args Same as get_all()
	 * @return int Count
	 */
	public function get_count( $args = [] ) {
		global $wpdb;

		$where_clauses = [];
		$where_values  = [];

		// Support old 'status' param for backward compatibility
		if ( ! empty( $args['status'] ) ) {
			$is_active       = ( 'active' === $args['status'] ) ? 1 : 0;
			$where_clauses[] = 'is_active = %d';
			$where_values[]  = $is_active;
		}

		// Support 'category' filter (library/custom)
		if ( ! empty( $args['category'] ) ) {
			if ( 'library' === $args['category'] ) {
				$where_clauses[] = 'source LIKE %s';
				$where_values[]  = 'library:%';
			} elseif ( 'custom' === $args['category'] ) {
				$where_clauses[] = 'source NOT LIKE %s';
				$where_values[]  = 'library:%';
			}
		}

		if ( ! empty( $args['source'] ) ) {
			$where_clauses[] = 'source = %s';
			$where_values[]  = $args['source'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where_clauses[] = '(name LIKE %s OR description LIKE %s)';
			$search_term     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$where_values[]  = $search_term;
			$where_values[]  = $search_term;
		}

		$where_sql = ! empty( $where_clauses ) ? 'WHERE ' . implode( ' AND ', $where_clauses ) : '';

		$query = "SELECT COUNT(*) FROM {$this->get_table_name()} {$where_sql}";

		if ( ! empty( $where_values ) ) {
			$query = $wpdb->prepare( $query, $where_values ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}   /**
		 * Test function with sample value
		 *
		 * @param string $code    PHP code to test
		 * @param mixed  $value   Test value
		 * @param array  $context Optional context
		 * @return array Test result
		 */
	public function test_function( $code, $value, $context = [] ) {
		return $this->executor->test_function( $code, $value, $context );
	}

	/**
	 * Create function from snippet library
	 *
	 * @param string $snippet_key Snippet key
	 * @param array  $data        Additional function data (name, description)
	 * @return int|false Function ID or false
	 */
	public function create_from_snippet( $snippet_key, $data = [] ) {
		// Get snippet from library
		$snippets = new \WP_AIE\Helper\Function_Snippets();
		$snippet  = $snippets->get_snippet( $snippet_key );

		if ( ! $snippet ) {
			return false;
		}

		// Prepare function data
		$function_data = [
			'name'        => ! empty( $data['name'] ) ? $data['name'] : $snippet['name'],
			'description' => ! empty( $data['description'] ) ? $data['description'] : $snippet['description'],
			'code'        => $snippet['code'],
			'source'      => 'library:' . $snippet_key,
			'category'    => $snippet['category'],
			'status'      => 'active',
		];

		return $this->create( $function_data );
	}

	/**
	 * Check if current user can edit function
	 *
	 * @param int $function_id Function ID
	 * @return bool
	 */
	public function can_edit_function( $function_id ) {
		// Admin can edit all
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		// Check if user created this function
		$function = $this->get( $function_id );
		if ( ! $function ) {
			return false;
		}

		return (int) $function['created_by'] === get_current_user_id();
	}

	/**
	 * Increment usage counter
	 *
	 * @param int $function_id Function ID
	 * @return bool
	 */
	public function increment_usage( $function_id ) {
		global $wpdb;

		return (bool) $wpdb->query(
			$wpdb->prepare(
				"UPDATE {$this->get_table_name()} SET usage_count = usage_count + 1 WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$function_id
			)
		);
	}

	/**
	 * Get functions by IDs
	 *
	 * @param array $ids Function IDs
	 * @return array Functions
	 */
	public function get_by_ids( $ids ) {
		global $wpdb;

		if ( empty( $ids ) ) {
			return [];
		}

		$ids          = array_map( 'intval', $ids );
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		$query = $wpdb->prepare(
			"SELECT * FROM {$this->get_table_name()} WHERE id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$ids
		);

		$functions = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		// Map database columns to expected format
		if ( ! empty( $functions ) ) {
			$functions = array_map( [ $this, 'map_db_to_output' ], $functions );
		}
		return $functions ?: [];
	}

	/**
	 * Get active functions for dropdown
	 *
	 * @return array Array of functions with id, name
	 */
	public function get_active_for_select() {
		$functions = $this->get_all(
			[
				'status'  => 'active',
				'orderby' => 'name',
				'order'   => 'ASC',
			]
		);

		$options = [];
		foreach ( $functions as $function ) {
			$options[] = [
				'id'     => $function['id'],
				'name'   => $function['name'],
				'source' => $function['source'],
			];
		}

		return $options;
	}

	/**
	 * Map database columns to output format for backward compatibility
	 *
	 * @param array $function Function data from database
	 * @return array Mapped function data
	 */
	private function map_db_to_output( $function ) {
		// Map function_code to code for JavaScript compatibility
		if ( isset( $function['function_code'] ) ) {
			$function['code'] = $function['function_code'];
			unset( $function['function_code'] );
		}

		// Map new fields to old fields for backward compatibility
		// is_active (1/0) -> status (active/inactive)
		if ( isset( $function['is_active'] ) ) {
			$function['status'] = $function['is_active'] ? 'active' : 'inactive';
		} else {
			$function['status'] = 'active'; // default
		}

		// Add category as source type for compatibility
		if ( isset( $function['source'] ) ) {
			$function['category'] = strpos( $function['source'], 'library:' ) === 0 ? 'library' : 'custom';
		} else {
			$function['category'] = 'custom';
		}

		return $function;
	}
}

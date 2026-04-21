<?php
/**
 * Custom Function Model
 *
 * Manages custom user functions in database
 *
 * @package RockStarLab\ImportExport\Model
 */

namespace RockStarLab\ImportExport\Model;

use RockStarLab\ImportExport\Helper\Function_Executor;

defined( 'ABSPATH' ) || exit;

class Custom_Function extends Model {

	/**
	 * Table name (without prefix)
	 *
	 * @var string
	 */
	protected $table_name = 'rsl_ie_custom_functions';

	/**
	 * Function Executor instance
	 *
	 * @var Function_Executor
	 */
	private $executor;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->executor = new Function_Executor();
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
			return new \WP_Error( 'missing_fields', __( 'Name and code are required', 'import-export-by-rockstarlab' ) );
		}

		// Check if function name already exists
		if ( $this->function_name_exists( $data['name'] ) ) {
			return new \WP_Error( 'duplicate_name', __( 'A function with this name already exists', 'import-export-by-rockstarlab' ) );
		}

		// Decode HTML entities from code
		$data['code'] = html_entity_decode( $data['code'], ENT_QUOTES, 'UTF-8' );

		// Validate code security
		$validation = $this->executor->validate_function_code( $data['code'] );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}

		// Ensure code starts with <?php tag for storage (for editor syntax highlighting)
		$code_to_store = trim( $data['code'] );
		if ( ! preg_match( '/^<\?php/i', $code_to_store ) ) {
			$code_to_store = "<?php\n\n" . $code_to_store;
		}

		// Prepare data
		$insert_data    = [
			'name'          => sanitize_text_field( $data['name'] ),
			'description'   => ! empty( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '',
			'function_code' => $code_to_store, // Store with <?php tag for editor
			'source'        => ! empty( $data['source'] ) ? sanitize_text_field( $data['source'] ) : 'custom',
			'input_type'    => ! empty( $data['input_type'] ) ? sanitize_text_field( $data['input_type'] ) : 'string',
			'output_type'   => ! empty( $data['output_type'] ) ? sanitize_text_field( $data['output_type'] ) : 'string',
			'is_active'     => 1,
			'user_id'       => get_current_user_id(),
			'usage_count'   => 0,
			'created_at'    => current_time( 'mysql' ),
			'updated_at'    => current_time( 'mysql' ),
		];      $result = $wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Direct DB query required here.
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

			return $function_id;
		}

		return new \WP_Error(
			'db_error',
			__( 'Database error: Failed to save function', 'import-export-by-rockstarlab' ),
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
			return new \WP_Error( 'not_found', __( 'Function not found', 'import-export-by-rockstarlab' ) );
		}

		// Check permissions
		if ( ! $this->can_edit_function( $id ) ) {
			return new \WP_Error( 'permission_denied', __( 'You do not have permission to edit this function', 'import-export-by-rockstarlab' ) );
		}

		// Check if function name is being changed and if new name already exists
		if ( isset( $data['name'] ) && $data['name'] !== $existing['name'] ) {
			if ( $this->function_name_exists( $data['name'], $id ) ) {
				return new \WP_Error( 'duplicate_name', __( 'A function with this name already exists', 'import-export-by-rockstarlab' ) );
			}
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
			// Ensure code starts with <?php tag for storage (for editor syntax highlighting)
			$code_to_store = trim( $data['code'] );
			if ( ! preg_match( '/^<\?php/i', $code_to_store ) ) {
				$code_to_store = "<?php\n\n" . $code_to_store;
			}
			$update_data['function_code'] = $code_to_store;
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

		$result = $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			$this->get_table_name(),
			$update_data,
			array( 'id' => $id ),
			array_fill( 0, count( $update_data ), '%s' ),
			array( '%d' )
		);

		if ( false !== $result ) {
			return true;
		}

		return new \WP_Error(
			'db_error',
			__( 'Database error: Failed to update function', 'import-export-by-rockstarlab' ),
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

		$result = $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			$this->get_table_name(),
			[ 'id' => $id ],
			[ '%d' ]
		);

		if ( $result ) {
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

		$function = $wpdb->get_row( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
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
	}

	/**
	 * Check if function name already exists
	 *
	 * Checks both custom functions in database and library snippets
	 *
	 * @param string $name       Function name to check
	 * @param int    $exclude_id Optional function ID to exclude from check
	 * @return bool True if name exists
	 */
	public function function_name_exists( $name, $exclude_id = 0 ) {
		global $wpdb;

		// Check in database (custom functions)
		$query = $wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->get_table_name()} WHERE name = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct DB query required here.
			$name
		);

		if ( $exclude_id > 0 ) {
			$query = $wpdb->prepare(
				"SELECT COUNT(*) FROM {$this->get_table_name()} WHERE name = %s AND id != %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Direct DB query required here.
				$name,
				$exclude_id
			);
		}

		$count = (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

		if ( $count > 0 ) {
			return true;
		}

		// Check in library snippets
		$snippets     = new \RockStarLab\ImportExport\Helper\Function_Snippets();
		$all_snippets = $snippets->get_all_snippets();

		foreach ( $all_snippets as $snippet ) {
			if ( isset( $snippet['name'] ) && $snippet['name'] === $name ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Generate a unique function name
	 *
	 * If the name already exists, append (2), (3), etc. until a unique name is found
	 *
	 * @param string $base_name  Base function name
	 * @param int    $exclude_id Optional function ID to exclude from check
	 * @return string Unique function name
	 */
	public function generate_unique_name( $base_name, $exclude_id = 0 ) {
		// If name doesn't exist, return as is
		if ( ! $this->function_name_exists( $base_name, $exclude_id ) ) {
			return $base_name;
		}

		// Extract existing number suffix if present
		$pattern = '/^(.+?)\s*\((\d+)\)$/';
		if ( preg_match( $pattern, $base_name, $matches ) ) {
			$base_name = trim( $matches[1] );
			$counter   = (int) $matches[2] + 1; // Start from next number
		} else {
			$counter = 2;
		}

		// Find next available number
		while ( $this->function_name_exists( $base_name . ' (' . $counter . ')', $exclude_id ) ) {
			$counter++;
		}

		return $base_name . ' (' . $counter . ')';
	}

	/**
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

		$functions = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

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

		return (int) $wpdb->get_var( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
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
		$snippets = new \RockStarLab\ImportExport\Helper\Function_Snippets();
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

		return (bool) $wpdb->query( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
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
			"SELECT * FROM {$this->get_table_name()} WHERE id IN ({$placeholders})", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared,WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
			$ids
		);

		$functions = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared,PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching

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
			$code = $function['function_code'];

			// Extract original code from wrapped function if it was auto-wrapped
			// Check if code contains the transform_value wrapper
			if ( strpos( $code, 'function transform_value' ) !== false ) {
				// Try to extract the body between the last { and first }
				// Find the position of the opening brace after transform_value
				$start_pos = strpos( $code, '{', strpos( $code, 'transform_value' ) );
				// Find the position of the last closing brace
				$end_pos = strrpos( $code, '}' );

				if ( $start_pos !== false && $end_pos !== false && $end_pos > $start_pos ) {
					// Extract the content between braces
					$body = substr( $code, $start_pos + 1, $end_pos - $start_pos - 1 );

					// Remove leading/trailing whitespace from the entire block
					$body = trim( $body );

					// Remove leading tab from each line (added during wrapping)
					$lines           = explode( "\n", $body );
					$extracted_lines = array_map(
						function ( $line ) {
							// Remove one leading tab if present
							if ( strpos( $line, "\t" ) === 0 ) {
								return substr( $line, 1 );
							}
							return $line;
						},
						$lines
					);
					$extracted_code  = implode( "\n", $extracted_lines );

					// Add <?php tag if it's not already there
					if ( ! empty( $extracted_code ) && ! preg_match( '/^<\?php/i', $extracted_code ) ) {
						$function['code'] = "<?php\n\n" . $extracted_code;
					} else {
						$function['code'] = $extracted_code;
					}
				} else {
					// Fallback: use code as-is if extraction failed
					$function['code'] = $code;
				}
			} else {
				// Code is not wrapped
				// Add <?php tag if it's not already there
				if ( ! empty( $code ) && ! preg_match( '/^<\?php/i', $code ) ) {
					$function['code'] = "<?php\n\n" . $code;
				} else {
					$function['code'] = $code;
				}
			}

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

	/**
	 * Seed built-in functions from snippets to database
	 *
	 * This loads all built-in functions from Function_Snippets into the database
	 * so they can be used by Content Updater and other features.
	 *
	 * @return array Array with 'created' and 'skipped' counts
	 */
	public function seed_builtin_functions() {
		$snippets     = new \RockStarLab\ImportExport\Helper\Function_Snippets();
		$all_snippets = $snippets->get_all_functions();

		$stats = [
			'created' => 0,
			'skipped' => 0,
			'errors'  => 0,
		];

		foreach ( $all_snippets as $key => $snippet ) {
			// Check if already exists by checking for source = 'library:key'
			$existing = $this->find_by(
				[
					'source' => 'library:' . $key,
				]
			);

			if ( ! empty( $existing ) ) {
				++$stats['skipped'];
				continue;
			}

			// Create the function
			$result = $this->create_from_snippet(
				$key,
				[
					'name' => $snippet['name'],
				]
			);

			if ( $result && ! is_wp_error( $result ) ) {
				++$stats['created'];
			} else {
				++$stats['errors'];
			}
		}

		return $stats;
	}
}

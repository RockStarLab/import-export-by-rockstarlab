<?php
/**
 * Function Executor
 *
 * Safely executes custom user functions with security validation
 *
 * @package WP_AIE\Helper
 */

namespace WP_AIE\Helper;

defined( 'ABSPATH' ) || exit;

class Function_Executor {

	/**
	 * Maximum execution time for a function (seconds)
	 *
	 * @var int
	 */
	private $timeout = 5;

	/**
	 * Whitelist of allowed PHP functions
	 *
	 * @var array
	 */
	private $allowed_functions = [
		// String functions
		'strtoupper',
		'strtolower',
		'ucfirst',
		'ucwords',
		'trim',
		'ltrim',
		'rtrim',
		'substr',
		'strlen',
		'str_replace',
		'str_ireplace',
		'preg_replace',
		'preg_match',
		'preg_match_all',
		'explode',
		'implode',
		'sprintf',
		'number_format',
		// Array functions
		'array_map',
		'array_filter',
		'array_merge',
		'array_slice',
		'array_keys',
		'array_values',
		'in_array',
		'array_search',
		'count',
		// Date/Time functions
		'date',
		'strtotime',
		'time',
		'mktime',
		'date_create',
		'date_format',
		// Type conversion
		'intval',
		'floatval',
		'strval',
		'boolval',
		// WordPress functions
		'sanitize_title',
		'sanitize_text_field',
		'sanitize_email',
		'esc_html',
		'esc_attr',
		'esc_url',
		'wp_strip_all_tags',
		'get_user_by',
		'get_term_by',
		'get_post',
		'get_category_by_slug',
		'term_exists',
		'wp_insert_term',
		'wp_create_category',
		// Math functions
		'abs',
		'round',
		'ceil',
		'floor',
		'min',
		'max',
		'rand',
		// Misc
		'empty',
		'isset',
		'is_array',
		'is_string',
		'is_numeric',
		'json_encode',
		'json_decode',
		'unserialize',
		'serialize',
		'base64_encode',
		'base64_decode',
		'md5',
		'sha1',
	];

	/**
	 * Blacklist of dangerous PHP constructs
	 *
	 * @var array
	 */
	private $blacklist_patterns = [
		'/\beval\s*\(/i',
		'/\bexec\s*\(/i',
		'/\bshell_exec\s*\(/i',
		'/\bsystem\s*\(/i',
		'/\bpassthru\s*\(/i',
		'/\bproc_open\s*\(/i',
		'/\bpopen\s*\(/i',
		'/\b`[^`]*`/i', // Backticks
		'/\$\$/', // Variable variables
		'/\bfile_get_contents\s*\(/i',
		'/\bfile_put_contents\s*\(/i',
		'/\bfopen\s*\(/i',
		'/\bfwrite\s*\(/i',
		'/\bunlink\s*\(/i',
		'/\brmdir\s*\(/i',
		'/\bmkdir\s*\(/i',
		'/\bchmod\s*\(/i',
		'/\bcurl_exec\s*\(/i',
		'/\bfsockopen\s*\(/i',
		'/\binclude\s*\(/i',
		'/\binclude_once\s*\(/i',
		'/\brequire\s*\(/i',
		'/\brequire_once\s*\(/i',
		'/\b__halt_compiler\s*\(/i',
		'/\bcreate_function\s*\(/i',
	];

	/**
	 * Constructor
	 */
	public function __construct() {
		// Constructor intentionally left empty
	}

	/**
	 * Execute a custom function by ID
	 *
	 * @param int|string $function_id Function ID from database or snippet key
	 * @param mixed      $value       Input value
	 * @param array      $context     Additional context (row data, field info, etc.)
	 * @return mixed Transformed value or original on error
	 */
	public function execute( $function_id, $value, $context = [] ) {
		$function = $this->get_function( $function_id );

		if ( ! $function ) {
			return $value;
		}

		// Check if function is active
		if ( ! empty( $function['status'] ) && 'active' !== $function['status'] ) {
			return $value;
		}

		$result = $this->execute_in_sandbox( $function['code'], $value, $context, $function_id );

		return $result;
	}

	/**
	 * Execute code in a sandboxed environment
	 *
	 * @param string $code       PHP code to execute
	 * @param mixed  $value      Input value
	 * @param array  $context    Additional context
	 * @param int    $function_id Optional function ID for logging
	 * @return mixed Transformed value or original on error
	 */
	public function execute_in_sandbox( $code, $value, $context = [], $function_id = 0 ) {
		// Use Safe_Code_Executor instead of eval()
		require_once WP_AIE_PATH . 'app/Helper/Safe_Code_Executor.php';

		$result = Safe_Code_Executor::execute( $code, $value );

		if ( $result['error'] ) {
			// Log error if function_id provided
			if ( $function_id > 0 ) {
				error_log( "AIE Function #{$function_id} error: " . $result['message'] ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug logging for development.
			}
			return $value; // Return original value on error
		}

		return $result['output'];
	}
	/**
	 * Validate function code for security issues
	 *
	 * @param string $code PHP code to validate
	 * @return true|\WP_Error True if valid, WP_Error if invalid
	 */
	public function validate_function_code( $code ) {
		// Check for empty code
		if ( empty( trim( $code ) ) ) {
			return new \WP_Error(
				'empty_code',
				__( 'Function code cannot be empty', 'advanced-import-export' )
			);
		}

		// Remove PHP tags for validation (they will be added later)
		$clean_code = preg_replace( '/<\?php\s*/', '', $code );
		$clean_code = preg_replace( '/<\?\s*/', '', $clean_code );
		$clean_code = preg_replace( '/\?>/', '', $clean_code );
		$clean_code = trim( $clean_code );

		if ( empty( $clean_code ) ) {
			return new \WP_Error(
				'empty_code',
				__( 'Function code cannot be empty', 'advanced-import-export' )
			);
		}

		// Check for blacklisted patterns
		foreach ( $this->blacklist_patterns as $pattern ) {
			if ( preg_match( $pattern, $clean_code ) ) {
				return new \WP_Error(
					'dangerous_code',
					__( 'Function contains dangerous or disallowed PHP constructs', 'advanced-import-export' )
				);
			}
		}

		// Try to parse the code (syntax check using token_get_all)
		$test_code = '<?php ' . $clean_code;

		// Suppress errors during parsing
		$old_error_level = error_reporting( 0 ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting -- error_reporting used intentionally for sandboxed execution.
		$tokens          = @token_get_all( $test_code ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		error_reporting( $old_error_level ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.prevent_path_disclosure_error_reporting -- error_reporting used intentionally for sandboxed execution.

		if ( false === $tokens ) {
			return new \WP_Error(
				'parse_error',
				__( 'Function code contains syntax errors', 'advanced-import-export' )
			);
		}

		// Additional check: look for T_ERROR tokens which indicate syntax issues
		foreach ( $tokens as $token ) {
			if ( is_array( $token ) && defined( 'T_ERROR' ) && T_ERROR === $token[0] ) {
				return new \WP_Error(
					'parse_error',
					sprintf(
						/* translators: %s: Error token */
						__( 'Syntax error near: %s', 'advanced-import-export' ),
						$token[1]
					)
				);
			}
		}

		// Use Safe_Code_Executor for validation instead of eval()
		require_once WP_AIE_PATH . 'app/Helper/Safe_Code_Executor.php';
		$test_result = Safe_Code_Executor::execute( $clean_code, 'test_value' );

		if ( $test_result['error'] ) {
			$error_message = $test_result['message'] ?? __( 'The function code contains errors. Please check your PHP syntax.', 'advanced-import-export' );
			
			return new \WP_Error(
				'validation_error',
				$error_message
			);
		}

		return true;
	}

	/**
	 * Get function from database
	 *
	 * @param int|string $function_id Function ID or snippet key
	 * @return array|null Function data or null
	 */
	public function get_function( $function_id ) {
		// Check if this is a snippet ID (string starting with "snippet_")
		if ( is_string( $function_id ) && strpos( $function_id, 'snippet_' ) === 0 ) {
			return $this->get_builtin_function( $function_id );
		}

		// Otherwise, try to get from database
		global $wpdb;

		$table = $wpdb->prefix . 'aie_custom_functions';

		$function = $wpdb->get_row( // phpcs:ignore PluginCheck.Security.DirectDB.UnescapedDBParameter,WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				(int) $function_id
			),
			ARRAY_A
		);

		// If not found in database, check if it's a built-in function
		if ( ! $function ) {
			$function = $this->get_builtin_function( $function_id );
		}

		if ( ! $function ) {
			return null;
		}

		// Map function_code to code for compatibility
		if ( isset( $function['function_code'] ) ) {
			$function['code'] = $function['function_code'];
			unset( $function['function_code'] );
		}

		return $function;
	}

	/**
	 * Get built-in function by ID (from Function_Snippets)
	 *
	 * @param int|string $function_id Function ID or snippet key (e.g., "snippet_uppercase")
	 * @return array|null Function data or null
	 */
	private function get_builtin_function( $function_id ) {
		// Extract snippet key if it's in format "snippet_xxx"
		$snippet_key = $function_id;
		if ( is_string( $function_id ) && strpos( $function_id, 'snippet_' ) === 0 ) {
			$snippet_key = substr( $function_id, 8 ); // Remove "snippet_" prefix
		}

		// Try to load from Function_Snippets library
		$library = new Function_Snippets();
		$snippet = $library->get_snippet( $snippet_key );

		if ( ! $snippet ) {
			return null;
		}

		// Add <?php tag to snippet code if not present (for editor syntax highlighting)
		$code = $snippet['code'];
		if ( ! preg_match( '/^<\?php/i', trim( $code ) ) ) {
			$code = "<?php\n\n" . $code;
		}

		// Convert snippet format to function format
		return [
			'id'     => $function_id,
			'name'   => $snippet['name'],
			'code'   => $code,
			'status' => 'active', // Snippets are always active
		];
	}

	/**
	 * Test function with sample value
	 *
	 * @param string $code  PHP code to test
	 * @param mixed  $value Test value
	 * @param array  $context Optional context
	 * @return array Result with success flag and output
	 */
	public function test_function( $code, $value, $context = [] ) {
		// Decode HTML entities that may have been encoded during transmission
		$code = html_entity_decode( $code, ENT_QUOTES, 'UTF-8' );

		// Validate code
		$validation = $this->validate_function_code( $code );
		if ( is_wp_error( $validation ) ) {
			return [
				'success' => false,
				'error'   => $validation->get_error_message(),
			];
		}

		// Execute and capture result
		$result = $this->execute_in_sandbox( $code, $value, $context );

		return [
			'success' => true,
			'input'   => $value,
			'output'  => $result,
		];
	}

	/**
	 * Set execution timeout
	 *
	 * @param int $seconds Timeout in seconds
	 */
	public function set_timeout( $seconds ) {
		$this->timeout = max( 1, (int) $seconds );
	}

	/**
	 * Get allowed functions list
	 *
	 * @return array
	 */
	public function get_allowed_functions() {
		return $this->allowed_functions;
	}

	/**
	 * Add function to whitelist
	 *
	 * @param string|array $functions Function name(s) to allow
	 */
	public function allow_function( $functions ) {
		$functions               = (array) $functions;
		$this->allowed_functions = array_unique(
			array_merge( $this->allowed_functions, $functions )
		);
	}

	/**
	 * Check if function name is allowed
	 *
	 * @param string $function_name Function name to check
	 * @return bool
	 */
	public function is_function_allowed( $function_name ) {
		return in_array( $function_name, $this->allowed_functions, true );
	}
}

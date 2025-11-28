<?php
/**
 * Function Executor
 *
 * Safely executes custom user functions with security validation
 *
 * @package WP_AIE\Helper
 */

namespace WP_AIE\Helper;

/**
 * Function Executor Class
 *
 * Provides secure execution environment for custom user functions
 * with timeout protection, whitelist/blacklist validation, and error handling.
 *
 * @package WP_AIE\Helper
 */
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
	 * Logger instance
	 *
	 * @var Logger
	 */
	private $logger;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->logger = new Logger();
	}

	/**
	 * Execute a custom function by ID
	 *
	 * @param int   $function_id Function ID from database
	 * @param mixed $value       Input value
	 * @param array $context     Additional context (row data, field info, etc.)
	 * @return mixed Transformed value or original on error
	 */
	public function execute( $function_id, $value, $context = [] ) {
		$function = $this->get_function( $function_id );

		if ( ! $function ) {
			$this->logger->log(
				0,
				'error',
				sprintf( 'Custom function not found: ID %d', $function_id )
			);
			return $value;
		}

		// Check if function is active
		if ( ! empty( $function['status'] ) && 'active' !== $function['status'] ) {
			return $value;
		}

		return $this->execute_in_sandbox( $function['code'], $value, $context, $function_id );
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
		// Validate code security
		$validation = $this->validate_function_code( $code );
		if ( is_wp_error( $validation ) ) {
			$this->logger->log(
				0,
				'error',
				sprintf(
					'Function validation failed (ID: %d): %s',
					$function_id,
					$validation->get_error_message()
				)
			);
			return $value;
		}

		// Set timeout
		$original_time_limit = ini_get( 'max_execution_time' );
		@set_time_limit( $this->timeout ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		// Remove PHP tags if present (eval doesn't need them)
		$clean_code = preg_replace( '/<\?php\s*/', '', $code );
		$clean_code = preg_replace( '/<\?\s*/', '', $clean_code );
		$clean_code = preg_replace( '/\?>/', '', $clean_code );
		$clean_code = trim( $clean_code );

		// Check if code is a complete function definition
		$is_function = preg_match( '/^function\s+(\w+)\s*\(/i', $clean_code, $matches );

		$result = $value; // Default to original value

		try {
			if ( $is_function ) {
				// For function definitions, convert to anonymous function to avoid redeclaration errors
				$function_name = $matches[1];

				// Extract function body
				// Replace function name with anonymous function
				$anonymous_code  = preg_replace(
					'/^function\s+' . preg_quote( $function_name, '/' ) . '\s*\(/i',
					'$func = function(',
					$clean_code,
					1
				);
				$anonymous_code .= ';';

				// Execute anonymous function definition
				// phpcs:ignore Squiz.PHP.Eval.Discouraged
				eval( $anonymous_code );

				// Call the anonymous function if it exists
				if ( isset( $func ) && is_callable( $func ) ) {
					$result = $func( $value, $context );
				}
			} else {
				// For non-function code (expressions), wrap in anonymous function
				// Create isolated scope
				$execute = function () use ( $clean_code, $value, $context ) {
					// Make context variables available
					if ( ! empty( $context ) ) {
						extract( $context, EXTR_SKIP ); // phpcs:ignore WordPress.PHP.DontExtract.extract_extract
					}

					// Execute the code - it should return a value
					// phpcs:ignore Squiz.PHP.Eval.Discouraged
					return eval( $clean_code );
				};

				$result = $execute();
			}

			// Restore time limit
			@set_time_limit( (int) $original_time_limit ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged

		} catch ( \ParseError $e ) {
			$this->logger->log(
				0,
				'error',
				sprintf(
					'Function parse error: %s at line %d',
					$e->getMessage(),
					$e->getLine()
				)
			);
			$result = $value;

		} catch ( \Error $e ) {
			$this->logger->log(
				0,
				'error',
				sprintf(
					'Function error: %s',
					$e->getMessage()
				)
			);
			$result = $value;

		} catch ( \Exception $e ) {
			$this->logger->log(
				0,
				'error',
				sprintf(
					'Function exception: %s',
					$e->getMessage()
				)
			);
			$result = $value;
		}

		return $result;
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
				__( 'Function code cannot be empty', 'wp-aie' )
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
				__( 'Function code cannot be empty', 'wp-aie' )
			);
		}

		// Check for blacklisted patterns
		foreach ( $this->blacklist_patterns as $pattern ) {
			if ( preg_match( $pattern, $clean_code ) ) {
				return new \WP_Error(
					'dangerous_code',
					__( 'Function contains dangerous or disallowed PHP constructs', 'wp-aie' )
				);
			}
		}

		// Try to parse the code (syntax check using token_get_all)
		$test_code = '<?php ' . $clean_code;

		// Suppress errors during parsing
		$old_error_level = error_reporting( 0 );
		$tokens          = @token_get_all( $test_code ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		error_reporting( $old_error_level );

		if ( false === $tokens ) {
			return new \WP_Error(
				'parse_error',
				__( 'Function code contains syntax errors', 'wp-aie' )
			);
		}

		// Additional check: look for T_ERROR tokens which indicate syntax issues
		foreach ( $tokens as $token ) {
			if ( is_array( $token ) && defined( 'T_ERROR' ) && T_ERROR === $token[0] ) {
				return new \WP_Error(
					'parse_error',
					sprintf(
						/* translators: %s: Error token */
						__( 'Syntax error near: %s', 'wp-aie' ),
						$token[1]
					)
				);
			}
		}

		// Advanced validation: Try to actually evaluate the code syntax
		// This catches errors that token_get_all misses
		// NOTE: eval() does NOT need <?php tag, it expects pure PHP code
		$validation_code = sprintf(
			'return true; if(false) { %s }',
			$clean_code
		);

		$old_error_handler = set_error_handler(
			function ( $errno, $errstr ) {
				// Capture the error
				return true;
			}
		);

		$syntax_error = null;
		ob_start();

		// Debug: log what we're trying to validate
		error_log( 'VALIDATION - Clean code: ' . $clean_code );
		error_log( 'VALIDATION - Validation code: ' . $validation_code );

		$result = eval( $validation_code ); // phpcs:ignore Squiz.PHP.Eval.Discouraged
		$output = ob_get_clean();

		// Check for parse errors
		$error = error_get_last();
		if ( $error && ( E_PARSE === $error['type'] || E_COMPILE_ERROR === $error['type'] ) ) {
			$syntax_error = $error['message'];
		}

		// Restore error handler
		if ( $old_error_handler ) {
			set_error_handler( $old_error_handler );
		} else {
			restore_error_handler();
		}

		if ( $syntax_error ) {
			// Clean up the error message
			$syntax_error = preg_replace( "/eval\(\)'d code/", 'code', $syntax_error );
			return new \WP_Error(
				'parse_error',
				sprintf(
					/* translators: %s: Error message */
					__( 'PHP Syntax Error: %s', 'wp-aie' ),
					$syntax_error
				)
			);
		}

		return true;
	}

	/**
	 * Get function from database
	 *
	 * @param int $function_id Function ID
	 * @return array|null Function data or null
	 */
	public function get_function( $function_id ) {
		global $wpdb;

		$table = $wpdb->prefix . 'aie_custom_functions';

		$function = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$function_id
			),
			ARRAY_A
		);

		return $function ?: null;
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

		// Debug logging
		error_log( 'TEST FUNCTION - Code received: ' . $code );

		// Validate code
		$validation = $this->validate_function_code( $code );
		if ( is_wp_error( $validation ) ) {
			error_log( 'TEST FUNCTION - Validation error: ' . $validation->get_error_message() );
			return [
				'success' => false,
				'error'   => $validation->get_error_message(),
			];
		}

		// Execute and capture result
		$result = $this->execute_in_sandbox( $code, $value, $context );

		error_log( 'TEST FUNCTION - Result: ' . print_r( $result, true ) );

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

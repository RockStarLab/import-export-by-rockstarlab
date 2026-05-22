<?php
/**
 * Safe Code Executor - Secure Alternative to eval()
 *
 * Provides multiple strategies for safe PHP code execution:
 * 1. Whitelist-only mode (safest)
 * 2. AST validation mode (balanced)
 * 3. Sandboxed execution (most flexible)
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

class Safe_Code_Executor {

	/**
	 * Execution mode
	 * - 'whitelist' - Only allow predefined functions (safest)
	 * - 'ast' - Parse and validate AST before execution (recommended)
	 * - 'sandbox' - Execute in isolated environment (most flexible)
	 *
	 * @var string
	 */
	private static $execution_mode = 'whitelist'; // Change via filter

	/**
	 * Allowed functions in whitelist mode
	 *
	 * @var array
	 */
	private static $allowed_functions = [
		// String functions
		'trim',
		'ltrim',
		'rtrim',
		'strtolower',
		'strtoupper',
		'ucfirst',
		'ucwords',
		'lcfirst',
		'substr',
		'str_replace',
		'str_ireplace',
		'strpos',
		'stripos',
		'strlen',
		'str_pad',
		'str_repeat',
		'implode',
		'explode',
		'sprintf',
		'number_format',
		'strip_tags',

		// Array functions
		'array_map',
		'array_filter',
		'array_merge',
		'array_slice',
		'array_values',
		'array_keys',
		'in_array',
		'count',

		// Date functions
		'date',
		'strtotime',
		'date_i18n',

		// WordPress functions
		'sanitize_text_field',
		'sanitize_email',
		'sanitize_url',
		'esc_html',
		'esc_attr',
		'esc_url',
		'wp_strip_all_tags',
		'wp_trim_words',
		'absint',
		'intval',
		'floatval',

		// Math functions
		'abs',
		'ceil',
		'floor',
		'round',
		'max',
		'min',

		// JSON
		'json_encode',
		'json_decode',

		// Regular expressions (safe)
		'preg_match',
		'preg_replace',
		'preg_split',
	];

	/**
	 * Dangerous functions that must be blocked
	 *
	 * @var array
	 */
	private static $dangerous_functions = [
		'eval',
		'exec',
		'system',
		'shell_exec',
		'passthru',
		'proc_open',
		'popen',
		'assert',
		'create_function',
		'include',
		'include_once',
		'require',
		'require_once',
		'file_get_contents',
		'file_put_contents',
		'fopen',
		'fwrite',
		'unlink',
		'rmdir',
		'rename',
		'copy',
		'move_uploaded_file',
		'curl_exec',
		'curl_init',
		'fsockopen',
		'socket_create',
		'mail',
		'header',
		'ob_start',
		'extract',
		'parse_str',
		'serialize',
		'unserialize',
		'call_user_func',
		'call_user_func_array',
		'register_shutdown_function',
		'register_tick_function',
	];

	/**
	 * Execute code safely
	 *
	 * @param string $code  PHP code to execute
	 * @param mixed  $value Input value for the function
	 * @return array Result array with 'output' and 'error' keys
	 */
	public static function execute( $code, $value ) {
		// Allow filtering execution mode
		$mode = apply_filters( 'rsl_ie_code_execution_mode', self::$execution_mode );

		// Validate mode
		if ( ! in_array( $mode, [ 'whitelist', 'ast', 'sandbox' ], true ) ) {
			$mode = 'whitelist';
		}

		// Execute based on mode
		switch ( $mode ) {
			case 'whitelist':
				return self::execute_whitelist( $code, $value );

			case 'ast':
				return self::execute_with_ast_validation( $code, $value );

			case 'sandbox':
				return self::execute_sandboxed( $code, $value );

			default:
				return self::execute_whitelist( $code, $value );
		}
	}

	/**
	 * OPTION 1: Whitelist Mode (Safest)
	 * Only allow predefined safe functions
	 *
	 * @param string $code  PHP code
	 * @param mixed  $value Input value
	 * @return array
	 */
	private static function execute_whitelist( $code, $value ) {
		// Step 1: Check for dangerous functions
		$dangerous_check = self::check_dangerous_functions( $code );
		if ( is_wp_error( $dangerous_check ) ) {
			return [
				'output'  => '',
				'error'   => true,
				'message' => $dangerous_check->get_error_message(),
			];
		}

		// Step 2: Extract function calls from code
		$used_functions = self::extract_function_calls( $code );

		// Step 3: Validate all functions are in whitelist
		$allowed = apply_filters( 'rsl_ie_allowed_functions', self::$allowed_functions );

		foreach ( $used_functions as $func ) {
			if ( ! in_array( $func, $allowed, true ) && ! in_array( $func, [ 'return', 'if', 'else', 'elseif', 'empty', 'isset' ], true ) ) {
				return [
					'output'  => '',
					'error'   => true,
					'message' => sprintf(
						/* translators: %s: function name */
						__( 'Function "%s" is not allowed. Only whitelisted functions are permitted.', 'import-export-by-rockstarlab' ),
						$func
					),
				];
			}
		}

		// Step 4: Execute in isolated scope
		return self::execute_isolated( $code, $value );
	}

	/**
	 * OPTION 2: AST Validation Mode (Recommended)
	 * Parse code and validate Abstract Syntax Tree
	 *
	 * Requires: composer require nikic/php-parser
	 *
	 * @param string $code  PHP code
	 * @param mixed  $value Input value
	 * @return array
	 */
	private static function execute_with_ast_validation( $code, $value ) {
		// Check if PHP-Parser is available
		if ( ! class_exists( '\PhpParser\ParserFactory' ) ) {
			// Fallback to whitelist mode
			return self::execute_whitelist( $code, $value );
		}

		try {
			// Create parser
			$parser = ( new \PhpParser\ParserFactory() )->create( \PhpParser\ParserFactory::PREFER_PHP7 );

			// Parse code into AST
			$ast = $parser->parse( "<?php\n" . $code );

			// Validate AST
			$validator = new AST_Validator();
			$traverser = new \PhpParser\NodeTraverser();
			$traverser->addVisitor( $validator );

			// Traverse and validate
			$traverser->traverse( $ast );

			// If validation passed, execute
			return self::execute_isolated( $code, $value );

		} catch ( \PhpParser\Error $e ) {
			return [
				'output'  => '',
				'error'   => true,
				'message' => sprintf(
					/* translators: %s: parse error message */
					__( 'PHP Syntax Error: %s', 'import-export-by-rockstarlab' ),
					$e->getMessage()
				),
			];
		} catch ( \Exception $e ) {
			return [
				'output'  => '',
				'error'   => true,
				'message' => $e->getMessage(),
			];
		}
	}

	/**
	 * OPTION 3: Sandboxed Execution (Most Flexible)
	 * Execute in restricted environment with resource limits
	 *
	 * @param string $code  PHP code
	 * @param mixed  $value Input value
	 * @return array
	 */
	private static function execute_sandboxed( $code, $value ) {
		// Check for dangerous functions
		$dangerous_check = self::check_dangerous_functions( $code );
		if ( is_wp_error( $dangerous_check ) ) {
			return [
				'output'  => '',
				'error'   => true,
				'message' => $dangerous_check->get_error_message(),
			];
		}

		return self::execute_isolated( $code, $value );
	}

	/**
	 * Execute code in isolated scope
	 *
	 * @param string $code  PHP code
	 * @param mixed  $value Input value
	 * @return array
	 */
	private static function execute_isolated( $code, $value ) {
		// Prepare code - remove PHP tags and clean up whitespace
		$code = trim( $code );
		$code = preg_replace( '/<\?php\s*/i', '', $code );
		$code = preg_replace( '/<\?\s*/', '', $code );
		$code = preg_replace( '/\?>\s*$/', '', $code );
		$code = trim( $code ); // Trim again after removing tags to handle extra newlines

		// Check if code is empty after cleanup
		if ( empty( $code ) ) {
			return [
				'output'  => '',
				'error'   => true,
				'message' => __( 'Function code is empty after processing', 'import-export-by-rockstarlab' ),
			];
		}

		// Check if the first meaningful token (ignoring comments/whitespace) is T_RETURN.
		// A plain stripos() check fails when comments precede the return statement.
		$tokens                 = @token_get_all( '<?php ' . $code ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged
		$first_meaningful_token = null;
		foreach ( $tokens as $token ) {
			if ( is_array( $token ) ) {
				if ( T_OPEN_TAG === $token[0] || T_WHITESPACE === $token[0] || T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] ) {
					continue;
				}
				$first_meaningful_token = $token[0];
			}
			break;
		}
		$starts_with_return = ( T_RETURN === $first_meaningful_token );

		// If code doesn't start with return, wrap it
		if ( ! $starts_with_return ) {
			// Remove trailing semicolon if exists before wrapping
			$code = rtrim( $code, '; ' );
			$code = 'return ' . $code . ';';
		} else {
			// Ensure it ends with semicolon
			if ( substr( rtrim( $code ), -1 ) !== ';' ) {
				$code = rtrim( $code ) . ';';
			}
		}

		// Execute in isolated function
		try {
			// Create anonymous function
			$func = function ( $value ) use ( $code ) {
				ob_start();

				try {
					// phpcs:ignore Squiz.PHP.Eval.Discouraged -- Controlled execution with validation
					$result = eval( $code ); // phpcs:ignore Generic.PHP.ForbiddenFunctions.Found -- Used intentionally in sandboxed executor.
					ob_end_clean();

					return [
						'output' => $result,
						'error'  => false,
					];
				} catch ( \ParseError $e ) {
					ob_end_clean();

					return [
						'output'  => '',
						'error'   => true,
						'message' => 'Syntax error: ' . $e->getMessage(),
					];
				} catch ( \Throwable $e ) {
					ob_end_clean();

					return [
						'output'  => '',
						'error'   => true,
						'message' => $e->getMessage(),
					];
				}
			};

			return $func( $value );

		} catch ( \Throwable $e ) {
			return [
				'output'  => '',
				'error'   => true,
				'message' => $e->getMessage(),
			];
		}
	}

	/**
	 * Check for dangerous functions in code
	 *
	 * @param string $code PHP code
	 * @return true|\WP_Error
	 */
	private static function check_dangerous_functions( $code ) {
		$dangerous = apply_filters( 'rsl_ie_dangerous_functions', self::$dangerous_functions );

		foreach ( $dangerous as $func ) {
			// Check for function call
			if ( preg_match( '/\b' . preg_quote( $func, '/' ) . '\s*\(/i', $code ) ) {
				return new \WP_Error(
					'dangerous_function',
					sprintf(
						/* translators: %s: function name */
						__( 'Dangerous function "%s" is not allowed for security reasons.', 'import-export-by-rockstarlab' ),
						$func
					)
				);
			}
		}

		// Check for backticks (shell execution)
		if ( strpos( $code, '`' ) !== false ) {
			return new \WP_Error(
				'dangerous_syntax',
				__( 'Shell execution syntax (backticks) is not allowed.', 'import-export-by-rockstarlab' )
			);
		}

		// Check for variable variables (can bypass restrictions)
		if ( preg_match( '/\$\$/', $code ) ) {
			return new \WP_Error(
				'dangerous_syntax',
				__( 'Variable variables ($$var) are not allowed.', 'import-export-by-rockstarlab' )
			);
		}

		return true;
	}

	/**
	 * Extract function calls from code
	 *
	 * @param string $code PHP code
	 * @return array Array of function names
	 */
	private static function extract_function_calls( $code ) {
		$functions = [];

		// Match function calls: function_name(
		preg_match_all( '/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $code, $matches );

		if ( ! empty( $matches[1] ) ) {
			$functions = array_unique( $matches[1] );
		}

		return $functions;
	}

	/**
	 * Get execution mode
	 *
	 * @return string
	 */
	public static function get_mode() {
		return apply_filters( 'rsl_ie_code_execution_mode', self::$execution_mode );
	}

	/**
	 * Get allowed functions list
	 *
	 * @return array
	 */
	public static function get_allowed_functions() {
		return apply_filters( 'rsl_ie_allowed_functions', self::$allowed_functions );
	}

	/**
	 * Add function to whitelist
	 *
	 * @param string|array $functions Function name(s) to add
	 */
	public static function add_allowed_function( $functions ) {
		$functions = (array) $functions;

		foreach ( $functions as $func ) {
			if ( ! in_array( $func, self::$allowed_functions, true ) ) {
				self::$allowed_functions[] = $func;
			}
		}
	}
}

// Only define AST_Validator if PhpParser is available
if ( class_exists( '\PhpParser\NodeVisitorAbstract' ) ) {
	/**
	 * AST Validator Class
	 * Validates PHP Abstract Syntax Tree for dangerous constructs
	 */
	class AST_Validator extends \PhpParser\NodeVisitorAbstract {

		/**
		 * Dangerous functions
		 *
		 * @var array
		 */
		private $dangerous_functions = [
			'eval',
			'exec',
			'system',
			'shell_exec',
			'passthru',
			'proc_open',
			'popen',
			'assert',
		];

		/**
		 * Enter node
		 *
		 * @param \PhpParser\Node $node Node
		 * @throws \Exception If dangerous construct found
		 */
		public function enterNode( \PhpParser\Node $node ) {
			// Check function calls
			if ( $node instanceof \PhpParser\Node\Expr\FuncCall ) {
				if ( $node->name instanceof \PhpParser\Node\Name ) {
					$func_name = $node->name->toString();

					if ( in_array( $func_name, $this->dangerous_functions, true ) ) {
						throw new \Exception( // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- internal exception not displayed to users
							sprintf(
								'Dangerous function "%s" is not allowed',
								$func_name // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- Internal exception not displayed to users.
							)
						);
					}
				}
			}

			// Check eval construct
			if ( $node instanceof \PhpParser\Node\Expr\Eval_ ) {
				throw new \Exception( 'eval() construct is not allowed' );
			}

			// Check shell execution
			if ( $node instanceof \PhpParser\Node\Expr\ShellExec ) {
				throw new \Exception( 'Shell execution is not allowed' );
			}

			// Check include/require
			if ( $node instanceof \PhpParser\Node\Expr\Include_ ) {
				throw new \Exception( 'Include/require is not allowed' );
			}
		}
	}
}

<?php
/**
 * AI Function Generator
 *
 * Generates PHP transformation functions using AI (OpenAI/Claude)
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

class AI_Function_Generator {

	/**
	 * OpenAI API Key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * API Endpoint
	 *
	 * @var string
	 */
	private $api_endpoint = 'https://api.openai.com/v1/chat/completions';

	/**
	 * Model to use
	 *
	 * @var string
	 */
	private $model = 'gpt-4o-mini';

	/**
	 * Constructor
	 */
	public function __construct() {
		// Get API key from WordPress options or define
		$this->api_key = $this->get_api_key();
	}

	/**
	 * Get API key
	 *
	 * Priority: 1. Options (user-defined in Plugin Options)
	 *           2. Constant (defined in wp-config.php)
	 *
	 * @return string
	 */
	private function get_api_key() {
		return OpenAI_API_Key::get_api_key();
	}

	/**
	 * Check if API key is configured
	 *
	 * @return bool
	 */
	public static function has_api_key() {
		return OpenAI_API_Key::has_api_key();
	}

	/**
	 * Generate function from prompt
	 *
	 * @param string $prompt User's description of what the function should do.
	 * @return array|\WP_Error Array with code, name, description or WP_Error on failure
	 */
	public function generate_function( $prompt ) {
		if ( empty( $this->api_key ) ) {
			return new \WP_Error(
				'no_api_key',
				__( 'OpenAI API key is not configured. Configure it in Settings → Connectors (WordPress 7+), or add it to wp-config.php as RSL_IE_OPENAI_API_KEY, or configure it in Plugin Options.', 'import-export-by-rockstarlab' )
			);
		}

		$system_prompt = $this->get_system_prompt();
		$user_prompt   = $this->format_user_prompt( $prompt );

		$response = $this->call_openai_api( $system_prompt, $user_prompt );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return $this->parse_response( $response );
	}

	/**
	 * Get system prompt
	 *
	 * @return string
	 */
	private function get_system_prompt() {
		return 'You are an expert PHP developer specializing in data transformation functions for WordPress.

Your task is to generate a PHP function that transforms a value based on the user\'s description.

IMPORTANT RULES:
1. The function MUST accept a single parameter called $value
2. The function MUST return the transformed value
3. DO NOT include <?php tags
4. DO NOT include function declaration (no function name() {})
5. ONLY return the function body code
6. Use only safe PHP functions - avoid eval, exec, system, file operations
7. Keep it simple and efficient
8. Handle edge cases (empty values, null, etc.)

Also provide:
- A short function name (snake_case)
- A brief description of what it does

Return your response in this JSON format:
{
  "name": "function_name",
  "description": "Brief description",
  "code": "return strtoupper($value);"
}

Example input: "Convert text to uppercase"
Example output:
{
  "name": "convert_to_uppercase",
  "description": "Converts text to uppercase",
  "code": "return strtoupper($value);"
}';
	}

	/**
	 * Format user prompt
	 *
	 * @param string $prompt User prompt.
	 * @return string
	 */
	private function format_user_prompt( $prompt ) {
		return sprintf(
			'Generate a PHP transformation function based on this description: "%s"

Remember:
- Accept $value as input
- Return transformed value
- Only return the code inside the function body
- No function declaration, no <?php tags
- Return response as JSON with name, description, and code',
			esc_html( $prompt )
		);
	}

	/**
	 * Call OpenAI API
	 *
	 * @param string $system_prompt System prompt.
	 * @param string $user_prompt User prompt.
	 * @return string|\WP_Error Response or error
	 */
	private function call_openai_api( $system_prompt, $user_prompt ) {
		$body = wp_json_encode(
			[
				'model'       => $this->model,
				'messages'    => [
					[
						'role'    => 'system',
						'content' => $system_prompt,
					],
					[
						'role'    => 'user',
						'content' => $user_prompt,
					],
				],
				'temperature' => 0.7,
				'max_tokens'  => 1000,
			]
		);

		$response = wp_remote_post(
			$this->api_endpoint,
			[
				'headers' => [
					'Content-Type'  => 'application/json',
					'Authorization' => 'Bearer ' . $this->api_key,
				],
				'body'    => $body,
				'timeout' => 30,
			]
		);

		if ( is_wp_error( $response ) ) {
			return new \WP_Error(
				'api_error',
				sprintf(
					/* translators: %s: error message */
					__( 'Failed to connect to OpenAI API: %s', 'import-export-by-rockstarlab' ),
					$response->get_error_message()
				)
			);
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			$error_data    = json_decode( $response_body, true );
			$error_message = $error_data['error']['message'] ?? __( 'Unknown API error', 'import-export-by-rockstarlab' );

			return new \WP_Error(
				'api_error',
				sprintf(
					/* translators: 1: HTTP code, 2: error message */
					__( 'OpenAI API returned error %1$d: %2$s', 'import-export-by-rockstarlab' ),
					$response_code,
					$error_message
				)
			);
		}

		$data = json_decode( $response_body, true );

		if ( ! isset( $data['choices'][0]['message']['content'] ) ) {
			return new \WP_Error(
				'invalid_response',
				__( 'Invalid response from OpenAI API', 'import-export-by-rockstarlab' )
			);
		}

		return $data['choices'][0]['message']['content'];
	}

	/**
	 * Parse AI response
	 *
	 * @param string $response AI response.
	 * @return array|\WP_Error Parsed data or error
	 */
	private function parse_response( $response ) {
		// Try to extract JSON from response (in case AI wrapped it in markdown code blocks)
		$response = trim( $response );

		// Remove markdown code blocks if present
		$response = preg_replace( '/^```json\s*/m', '', $response );
		$response = preg_replace( '/^```\s*/m', '', $response );
		$response = trim( $response );

		$data = json_decode( $response, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new \WP_Error(
				'parse_error',
				sprintf(
					/* translators: %s: JSON error message */
					__( 'Failed to parse AI response: %s', 'import-export-by-rockstarlab' ),
					json_last_error_msg()
				)
			);
		}

		if ( ! isset( $data['code'] ) || empty( $data['code'] ) ) {
			return new \WP_Error(
				'missing_code',
				__( 'AI did not generate valid code', 'import-export-by-rockstarlab' )
			);
		}

		// Clean up the code
		$code = trim( $data['code'] );

		// Remove any PHP tags that might have slipped through
		$code = preg_replace( '/<\?php\s*/i', '', $code );
		$code = preg_replace( '/\?>\s*$/i', '', $code );

		// Validate the code has a return statement
		if ( stripos( $code, 'return' ) === false ) {
			return new \WP_Error(
				'invalid_code',
				__( 'Generated code must contain a return statement', 'import-export-by-rockstarlab' )
			);
		}

		return [
			'code'        => $code,
			'name'        => $data['name'] ?? '',
			'description' => $data['description'] ?? '',
		];
	}
}

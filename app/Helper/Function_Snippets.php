<?php
/**
 * Function Snippets Library
 *
 * Ready-to-use code snippets for data transformation
 *
 * @package WP_AIE\Helper
 */

namespace WP_AIE\Helper;

/**
 * Function Snippets Class
 *
 * Provides library of 50+ ready-to-use transformation functions
 * organized by categories with search and filtering capabilities.
 *
 * @package WP_AIE\Helper
 */
class Function_Snippets {

	/**
	 * Get all available categories
	 *
	 * @return array Categories with icons and descriptions
	 */
	public function get_categories() {
		return [
			'string'     => [
				'name'        => __( 'String Operations', 'wp-aie' ),
				'description' => __( 'Text transformation, case conversion, trimming', 'wp-aie' ),
				'icon'        => 'dashicons-editor-alignleft',
			],
			'date'       => [
				'name'        => __( 'Date & Time', 'wp-aie' ),
				'description' => __( 'Date formatting, conversion, extraction', 'wp-aie' ),
				'icon'        => 'dashicons-calendar-alt',
			],
			'numeric'    => [
				'name'        => __( 'Numeric Operations', 'wp-aie' ),
				'description' => __( 'Numbers, prices, calculations, formatting', 'wp-aie' ),
				'icon'        => 'dashicons-calculator',
			],
			'html'       => [
				'name'        => __( 'HTML Operations', 'wp-aie' ),
				'description' => __( 'HTML cleaning, sanitization, entities', 'wp-aie' ),
				'icon'        => 'dashicons-editor-code',
			],
			'wordpress'  => [
				'name'        => __( 'WordPress Functions', 'wp-aie' ),
				'description' => __( 'Users, terms, categories, posts lookup', 'wp-aie' ),
				'icon'        => 'dashicons-wordpress',
			],
			'validation' => [
				'name'        => __( 'Validation', 'wp-aie' ),
				'description' => __( 'Email, URL, required fields validation', 'wp-aie' ),
				'icon'        => 'dashicons-yes',
			],
			'advanced'   => [
				'name'        => __( 'Advanced', 'wp-aie' ),
				'description' => __( 'Conditional logic, mapping, complex transformations', 'wp-aie' ),
				'icon'        => 'dashicons-admin-generic',
			],
		];
	}

	/**
	 * Get all snippets
	 *
	 * @return array All available snippets
	 */
	public function get_all_snippets() {
		return array_merge(
			$this->get_string_snippets(),
			$this->get_date_snippets(),
			$this->get_numeric_snippets(),
			$this->get_html_snippets(),
			$this->get_wordpress_snippets(),
			$this->get_validation_snippets(),
			$this->get_advanced_snippets()
		);
	}

	/**
	 * Get snippets by category
	 *
	 * @param string $category Category key
	 * @return array Snippets in category
	 */
	public function get_by_category( $category ) {
		$method = 'get_' . $category . '_snippets';

		if ( method_exists( $this, $method ) ) {
			return $this->$method();
		}

		return [];
	}

	/**
	 * Search snippets
	 *
	 * @param string $query Search query
	 * @return array Matching snippets
	 */
	public function search( $query ) {
		$all_snippets = $this->get_all_snippets();
		$query_lower  = strtolower( $query );
		$results      = [];

		foreach ( $all_snippets as $key => $snippet ) {
			$searchable = strtolower( $snippet['name'] . ' ' . $snippet['description'] . ' ' . implode( ' ', $snippet['tags'] ) );

			if ( false !== strpos( $searchable, $query_lower ) ) {
				$results[ $key ] = $snippet;
			}
		}

		return $results;
	}

	/**
	 * Get single snippet by key
	 *
	 * @param string $key Snippet key
	 * @return array|null Snippet or null
	 */
	public function get_snippet( $key ) {
		$all_snippets = $this->get_all_snippets();

		return $all_snippets[ $key ] ?? null;
	}

	/**
	 * String operation snippets
	 *
	 * @return array
	 */
	private function get_string_snippets() {
		return [
			'uppercase'              => [
				'name'        => __( 'Convert to Uppercase', 'wp-aie' ),
				'description' => __( 'Transform text to UPPERCASE', 'wp-aie' ),
				'category'    => 'string',
				'code'        => 'return strtoupper($value);',
				'example'     => [
					'input'  => 'hello world',
					'output' => 'HELLO WORLD',
				],
				'tags'        => [ 'text', 'case', 'upper' ],
			],
			'lowercase'              => [
				'name'        => __( 'Convert to Lowercase', 'wp-aie' ),
				'description' => __( 'Transform text to lowercase', 'wp-aie' ),
				'category'    => 'string',
				'code'        => 'return strtolower($value);',
				'example'     => [
					'input'  => 'HELLO WORLD',
					'output' => 'hello world',
				],
				'tags'        => [ 'text', 'case', 'lower' ],
			],
			'capitalize'             => [
				'name'        => __( 'Capitalize First Letter', 'wp-aie' ),
				'description' => __( 'First letter uppercase, rest lowercase', 'wp-aie' ),
				'category'    => 'string',
				'code'        => 'return ucfirst(strtolower($value));',
				'example'     => [
					'input'  => 'hELLO wORLD',
					'output' => 'Hello world',
				],
				'tags'        => [ 'text', 'case', 'capitalize' ],
			],
			'title_case'             => [
				'name'        => __( 'Title Case', 'wp-aie' ),
				'description' => __( 'Capitalize first letter of each word', 'wp-aie' ),
				'category'    => 'string',
				'code'        => 'return ucwords(strtolower($value));',
				'example'     => [
					'input'  => 'hELLO wORLD tEST',
					'output' => 'Hello World Test',
				],
				'tags'        => [ 'text', 'case', 'title' ],
			],
			'trim'                   => [
				'name'        => __( 'Trim Whitespace', 'wp-aie' ),
				'description' => __( 'Remove spaces from start and end', 'wp-aie' ),
				'category'    => 'string',
				'code'        => 'return trim($value);',
				'example'     => [
					'input'  => '  hello world  ',
					'output' => 'hello world',
				],
				'tags'        => [ 'text', 'trim', 'spaces' ],
			],
			'remove_multiple_spaces' => [
				'name'        => __( 'Remove Multiple Spaces', 'wp-aie' ),
				'description' => __( 'Replace multiple spaces with single space', 'wp-aie' ),
				'category'    => 'string',
				'code'        => 'return preg_replace(\'/\s+/\', \' \', trim($value));',
				'example'     => [
					'input'  => 'hello    world',
					'output' => 'hello world',
				],
				'tags'        => [ 'text', 'spaces', 'clean' ],
			],
			'replace_dashes'         => [
				'name'        => __( 'Replace Dashes with Spaces', 'wp-aie' ),
				'description' => __( 'Convert dashes to spaces', 'wp-aie' ),
				'category'    => 'string',
				'code'        => 'return str_replace([\'-\', \'_\'], \' \', $value);',
				'example'     => [
					'input'  => 'hello-world-test',
					'output' => 'hello world test',
				],
				'tags'        => [ 'text', 'replace', 'dashes' ],
			],
			'truncate'               => [
				'name'        => __( 'Truncate String', 'wp-aie' ),
				'description' => __( 'Limit string length to 100 characters', 'wp-aie' ),
				'category'    => 'string',
				'code'        => 'if (strlen($value) > 100) { return substr($value, 0, 97) . \'...\'; } return $value;',
				'example'     => [
					'input'  => 'Very long text...',
					'output' => 'Very long text (truncated)...',
				],
				'tags'        => [ 'text', 'truncate', 'limit' ],
			],
			'remove_special_chars'   => [
				'name'        => __( 'Remove Special Characters', 'wp-aie' ),
				'description' => __( 'Keep only letters, numbers, spaces', 'wp-aie' ),
				'category'    => 'string',
				'code'        => 'return preg_replace(\'/[^a-zA-Z0-9\s]/\', \'\', $value);',
				'example'     => [
					'input'  => 'Hello! @World# 2024',
					'output' => 'Hello World 2024',
				],
				'tags'        => [ 'text', 'clean', 'special' ],
			],
			'slugify'                => [
				'name'        => __( 'Generate Slug', 'wp-aie' ),
				'description' => __( 'Create URL-friendly slug', 'wp-aie' ),
				'category'    => 'string',
				'code'        => 'return sanitize_title($value);',
				'example'     => [
					'input'  => 'Hello World! 2024',
					'output' => 'hello-world-2024',
				],
				'tags'        => [ 'text', 'slug', 'url' ],
			],
		];
	}

	/**
	 * Date operation snippets
	 *
	 * @return array
	 */
	private function get_date_snippets() {
		return [
			'date_mysql'       => [
				'name'        => __( 'Format Date for MySQL', 'wp-aie' ),
				'description' => __( 'Convert any date format to MySQL datetime', 'wp-aie' ),
				'category'    => 'date',
				'code'        => '$timestamp = strtotime($value); if ($timestamp === false) { return $value; } return date(\'Y-m-d H:i:s\', $timestamp);',
				'example'     => [
					'input'  => '12/31/2024',
					'output' => '2024-12-31 00:00:00',
				],
				'tags'        => [ 'date', 'mysql', 'format' ],
			],
			'date_dmy_to_ymd'  => [
				'name'        => __( 'Convert DD/MM/YYYY to YYYY-MM-DD', 'wp-aie' ),
				'description' => __( 'European date format to ISO format', 'wp-aie' ),
				'category'    => 'date',
				'code'        => '$parts = explode(\'/\', $value); if (count($parts) === 3) { return sprintf(\'%s-%s-%s\', $parts[2], $parts[1], $parts[0]); } return $value;',
				'example'     => [
					'input'  => '31/12/2024',
					'output' => '2024-12-31',
				],
				'tags'        => [ 'date', 'convert', 'format' ],
			],
			'date_mdy_to_ymd'  => [
				'name'        => __( 'Convert MM/DD/YYYY to YYYY-MM-DD', 'wp-aie' ),
				'description' => __( 'US date format to ISO format', 'wp-aie' ),
				'category'    => 'date',
				'code'        => '$parts = explode(\'/\', $value); if (count($parts) === 3) { return sprintf(\'%s-%s-%s\', $parts[2], $parts[0], $parts[1]); } return $value;',
				'example'     => [
					'input'  => '12/31/2024',
					'output' => '2024-12-31',
				],
				'tags'        => [ 'date', 'convert', 'format', 'us' ],
			],
			'add_current_time' => [
				'name'        => __( 'Add Current Time to Date', 'wp-aie' ),
				'description' => __( 'Append current time to date', 'wp-aie' ),
				'category'    => 'date',
				'code'        => '$date = strtotime($value); if ($date === false) { return $value; } return date(\'Y-m-d H:i:s\', $date);',
				'example'     => [
					'input'  => '2024-12-31',
					'output' => '2024-12-31 14:30:45',
				],
				'tags'        => [ 'date', 'time', 'current' ],
			],
			'add_days'         => [
				'name'        => __( 'Add 7 Days to Date', 'wp-aie' ),
				'description' => __( 'Add week to date', 'wp-aie' ),
				'category'    => 'date',
				'code'        => '$date = strtotime($value); if ($date === false) { return $value; } return date(\'Y-m-d\', strtotime(\'+7 days\', $date));',
				'example'     => [
					'input'  => '2024-12-31',
					'output' => '2025-01-07',
				],
				'tags'        => [ 'date', 'add', 'days' ],
			],
			'extract_year'     => [
				'name'        => __( 'Extract Year from Date', 'wp-aie' ),
				'description' => __( 'Get only year from date', 'wp-aie' ),
				'category'    => 'date',
				'code'        => '$date = strtotime($value); if ($date === false) { return \'\'; } return date(\'Y\', $date);',
				'example'     => [
					'input'  => '2024-12-31',
					'output' => '2024',
				],
				'tags'        => [ 'date', 'extract', 'year' ],
			],
			'extract_month'    => [
				'name'        => __( 'Extract Month from Date', 'wp-aie' ),
				'description' => __( 'Get month number from date', 'wp-aie' ),
				'category'    => 'date',
				'code'        => '$date = strtotime($value); if ($date === false) { return \'\'; } return date(\'m\', $date);',
				'example'     => [
					'input'  => '2024-12-31',
					'output' => '12',
				],
				'tags'        => [ 'date', 'extract', 'month' ],
			],
		];
	}

	/**
	 * Numeric operation snippets
	 *
	 * @return array
	 */
	private function get_numeric_snippets() {
		return [
			'to_integer'     => [
				'name'        => __( 'Convert to Integer', 'wp-aie' ),
				'description' => __( 'Convert to whole number', 'wp-aie' ),
				'category'    => 'numeric',
				'code'        => 'return intval($value);',
				'example'     => [
					'input'  => '123.45',
					'output' => '123',
				],
				'tags'        => [ 'number', 'integer', 'convert' ],
			],
			'to_float'       => [
				'name'        => __( 'Convert to Float', 'wp-aie' ),
				'description' => __( 'Convert to decimal number', 'wp-aie' ),
				'category'    => 'numeric',
				'code'        => 'return floatval($value);',
				'example'     => [
					'input'  => '123.45',
					'output' => '123.45',
				],
				'tags'        => [ 'number', 'float', 'decimal' ],
			],
			'clean_price'    => [
				'name'        => __( 'Clean Price', 'wp-aie' ),
				'description' => __( 'Remove currency symbols and convert to decimal', 'wp-aie' ),
				'category'    => 'numeric',
				'code'        => '$cleaned = preg_replace(\'/[^0-9.,]/\', \'\', $value); $cleaned = str_replace(\',\', \'.\', $cleaned); return floatval($cleaned);',
				'example'     => [
					'input'  => '$1,234.56',
					'output' => '1234.56',
				],
				'tags'        => [ 'number', 'price', 'currency', 'clean' ],
			],
			'format_price'   => [
				'name'        => __( 'Format Price', 'wp-aie' ),
				'description' => __( 'Format number as price with 2 decimals', 'wp-aie' ),
				'category'    => 'numeric',
				'code'        => 'return number_format(floatval($value), 2, \'.\', \'\');',
				'example'     => [
					'input'  => '1234.5',
					'output' => '1234.50',
				],
				'tags'        => [ 'number', 'price', 'format' ],
			],
			'round_number'   => [
				'name'        => __( 'Round to 2 Decimals', 'wp-aie' ),
				'description' => __( 'Round number to 2 decimal places', 'wp-aie' ),
				'category'    => 'numeric',
				'code'        => 'return round(floatval($value), 2);',
				'example'     => [
					'input'  => '123.456',
					'output' => '123.46',
				],
				'tags'        => [ 'number', 'round', 'decimal' ],
			],
			'percentage'     => [
				'name'        => __( 'Convert to Percentage', 'wp-aie' ),
				'description' => __( 'Multiply by 100 and add % sign', 'wp-aie' ),
				'category'    => 'numeric',
				'code'        => 'return (floatval($value) * 100) . \'%\';',
				'example'     => [
					'input'  => '0.75',
					'output' => '75%',
				],
				'tags'        => [ 'number', 'percentage', 'percent' ],
			],
			'absolute_value' => [
				'name'        => __( 'Absolute Value', 'wp-aie' ),
				'description' => __( 'Remove negative sign', 'wp-aie' ),
				'category'    => 'numeric',
				'code'        => 'return abs(floatval($value));',
				'example'     => [
					'input'  => '-123.45',
					'output' => '123.45',
				],
				'tags'        => [ 'number', 'absolute', 'positive' ],
			],
		];
	}

	/**
	 * HTML operation snippets
	 *
	 * @return array
	 */
	private function get_html_snippets() {
		return [
			'strip_html'           => [
				'name'        => __( 'Strip HTML Tags', 'wp-aie' ),
				'description' => __( 'Remove all HTML tags', 'wp-aie' ),
				'category'    => 'html',
				'code'        => 'return wp_strip_all_tags($value);',
				'example'     => [
					'input'  => '<p>Hello <strong>World</strong></p>',
					'output' => 'Hello World',
				],
				'tags'        => [ 'html', 'strip', 'tags', 'clean' ],
			],
			'decode_html_entities' => [
				'name'        => __( 'Decode HTML Entities', 'wp-aie' ),
				'description' => __( 'Convert &amp; to & and similar', 'wp-aie' ),
				'category'    => 'html',
				'code'        => 'return html_entity_decode($value, ENT_QUOTES, \'UTF-8\');',
				'example'     => [
					'input'  => 'Hello &amp; World',
					'output' => 'Hello & World',
				],
				'tags'        => [ 'html', 'entities', 'decode' ],
			],
			'encode_html_entities' => [
				'name'        => __( 'Encode HTML Entities', 'wp-aie' ),
				'description' => __( 'Convert & to &amp; and similar', 'wp-aie' ),
				'category'    => 'html',
				'code'        => 'return htmlentities($value, ENT_QUOTES, \'UTF-8\');',
				'example'     => [
					'input'  => 'Hello & World',
					'output' => 'Hello &amp; World',
				],
				'tags'        => [ 'html', 'entities', 'encode' ],
			],
			'sanitize_text'        => [
				'name'        => __( 'Sanitize Text', 'wp-aie' ),
				'description' => __( 'Remove HTML and unsafe characters', 'wp-aie' ),
				'category'    => 'html',
				'code'        => 'return sanitize_text_field($value);',
				'example'     => [
					'input'  => '<script>alert("test")</script>Hello',
					'output' => 'Hello',
				],
				'tags'        => [ 'html', 'sanitize', 'clean', 'security' ],
			],
			'escape_html'          => [
				'name'        => __( 'Escape HTML', 'wp-aie' ),
				'description' => __( 'Make safe for HTML output', 'wp-aie' ),
				'category'    => 'html',
				'code'        => 'return esc_html($value);',
				'example'     => [
					'input'  => '<script>alert("test")</script>',
					'output' => '&lt;script&gt;alert("test")&lt;/script&gt;',
				],
				'tags'        => [ 'html', 'escape', 'security' ],
			],
		];
	}

	/**
	 * WordPress function snippets
	 *
	 * @return array
	 */
	private function get_wordpress_snippets() {
		return [
			'find_user_by_email'   => [
				'name'        => __( 'Find User ID by Email', 'wp-aie' ),
				'description' => __( 'Convert email to user ID', 'wp-aie' ),
				'category'    => 'wordpress',
				'code'        => '$user = get_user_by(\'email\', $value); return $user ? $user->ID : 0;',
				'example'     => [
					'input'  => 'user@example.com',
					'output' => '123',
				],
				'tags'        => [ 'wordpress', 'user', 'email', 'find' ],
			],
			'find_user_by_login'   => [
				'name'        => __( 'Find User ID by Login', 'wp-aie' ),
				'description' => __( 'Convert username to user ID', 'wp-aie' ),
				'category'    => 'wordpress',
				'code'        => '$user = get_user_by(\'login\', $value); return $user ? $user->ID : 0;',
				'example'     => [
					'input'  => 'admin',
					'output' => '1',
				],
				'tags'        => [ 'wordpress', 'user', 'login', 'find' ],
			],
			'find_term_by_name'    => [
				'name'        => __( 'Find Term ID by Name', 'wp-aie' ),
				'description' => __( 'Convert category/tag name to term ID', 'wp-aie' ),
				'category'    => 'wordpress',
				'code'        => '$term = get_term_by(\'name\', $value, \'category\'); return $term ? $term->term_id : 0;',
				'example'     => [
					'input'  => 'Technology',
					'output' => '5',
				],
				'tags'        => [ 'wordpress', 'term', 'category', 'find' ],
			],
			'find_term_by_slug'    => [
				'name'        => __( 'Find Term ID by Slug', 'wp-aie' ),
				'description' => __( 'Convert category/tag slug to term ID', 'wp-aie' ),
				'category'    => 'wordpress',
				'code'        => '$term = get_term_by(\'slug\', $value, \'category\'); return $term ? $term->term_id : 0;',
				'example'     => [
					'input'  => 'technology',
					'output' => '5',
				],
				'tags'        => [ 'wordpress', 'term', 'category', 'slug', 'find' ],
			],
			'create_category'      => [
				'name'        => __( 'Create Category if Not Exists', 'wp-aie' ),
				'description' => __( 'Get existing or create new category', 'wp-aie' ),
				'category'    => 'wordpress',
				'code'        => '$term = get_term_by(\'name\', $value, \'category\'); if ($term) { return $term->term_id; } $result = wp_insert_term($value, \'category\'); return is_wp_error($result) ? 0 : $result[\'term_id\'];',
				'example'     => [
					'input'  => 'New Category',
					'output' => '10',
				],
				'tags'        => [ 'wordpress', 'category', 'create' ],
			],
			'get_post_id_by_title' => [
				'name'        => __( 'Find Post ID by Title', 'wp-aie' ),
				'description' => __( 'Convert post title to post ID', 'wp-aie' ),
				'category'    => 'wordpress',
				'code'        => '$post = get_page_by_title($value, OBJECT, \'post\'); return $post ? $post->ID : 0;',
				'example'     => [
					'input'  => 'Hello World',
					'output' => '1',
				],
				'tags'        => [ 'wordpress', 'post', 'title', 'find' ],
			],
		];
	}

	/**
	 * Validation snippets
	 *
	 * @return array
	 */
	private function get_validation_snippets() {
		return [
			'validate_email' => [
				'name'        => __( 'Validate Email', 'wp-aie' ),
				'description' => __( 'Return email if valid, empty if not', 'wp-aie' ),
				'category'    => 'validation',
				'code'        => 'return is_email($value) ? $value : \'\';',
				'example'     => [
					'input'  => 'user@example.com',
					'output' => 'user@example.com',
				],
				'tags'        => [ 'validation', 'email', 'check' ],
			],
			'validate_url'   => [
				'name'        => __( 'Validate URL', 'wp-aie' ),
				'description' => __( 'Return URL if valid, empty if not', 'wp-aie' ),
				'category'    => 'validation',
				'code'        => 'return filter_var($value, FILTER_VALIDATE_URL) ? $value : \'\';',
				'example'     => [
					'input'  => 'https://example.com',
					'output' => 'https://example.com',
				],
				'tags'        => [ 'validation', 'url', 'check' ],
			],
			'require_value'  => [
				'name'        => __( 'Require Non-Empty', 'wp-aie' ),
				'description' => __( 'Return value or default if empty', 'wp-aie' ),
				'category'    => 'validation',
				'code'        => 'return !empty($value) ? $value : \'N/A\';',
				'example'     => [
					'input'  => '',
					'output' => 'N/A',
				],
				'tags'        => [ 'validation', 'required', 'default' ],
			],
			'sanitize_email' => [
				'name'        => __( 'Sanitize Email', 'wp-aie' ),
				'description' => __( 'Clean and validate email address', 'wp-aie' ),
				'category'    => 'validation',
				'code'        => 'return sanitize_email($value);',
				'example'     => [
					'input'  => 'USER@EXAMPLE.COM',
					'output' => 'user@example.com',
				],
				'tags'        => [ 'validation', 'email', 'sanitize' ],
			],
			'validate_phone' => [
				'name'        => __( 'Validate Phone Number', 'wp-aie' ),
				'description' => __( 'Check if value contains only digits and common phone chars', 'wp-aie' ),
				'category'    => 'validation',
				'code'        => 'return preg_match(\'/^[0-9+\-\(\)\s]+$/\', $value) ? $value : \'\';',
				'example'     => [
					'input'  => '+1-555-123-4567',
					'output' => '+1-555-123-4567',
				],
				'tags'        => [ 'validation', 'phone', 'check' ],
			],
		];
	}

	/**
	 * Advanced transformation snippets
	 *
	 * @return array
	 */
	private function get_advanced_snippets() {
		return [
			'concat_prefix'      => [
				'name'        => __( 'Add Prefix', 'wp-aie' ),
				'description' => __( 'Add text before value', 'wp-aie' ),
				'category'    => 'advanced',
				'code'        => 'return \'PREFIX-\' . $value;',
				'example'     => [
					'input'  => 'test',
					'output' => 'PREFIX-test',
				],
				'tags'        => [ 'advanced', 'concat', 'prefix' ],
			],
			'concat_suffix'      => [
				'name'        => __( 'Add Suffix', 'wp-aie' ),
				'description' => __( 'Add text after value', 'wp-aie' ),
				'category'    => 'advanced',
				'code'        => 'return $value . \'-SUFFIX\';',
				'example'     => [
					'input'  => 'test',
					'output' => 'test-SUFFIX',
				],
				'tags'        => [ 'advanced', 'concat', 'suffix' ],
			],
			'default_if_empty'   => [
				'name'        => __( 'Default if Empty', 'wp-aie' ),
				'description' => __( 'Use default value if empty', 'wp-aie' ),
				'category'    => 'advanced',
				'code'        => 'return !empty($value) ? $value : \'Default Value\';',
				'example'     => [
					'input'  => '',
					'output' => 'Default Value',
				],
				'tags'        => [ 'advanced', 'default', 'empty', 'conditional' ],
			],
			'map_values'         => [
				'name'        => __( 'Map Values', 'wp-aie' ),
				'description' => __( 'Convert one value to another (Yes/No to 1/0)', 'wp-aie' ),
				'category'    => 'advanced',
				'code'        => '$map = [\'yes\' => 1, \'no\' => 0]; $key = strtolower($value); return isset($map[$key]) ? $map[$key] : $value;',
				'example'     => [
					'input'  => 'Yes',
					'output' => '1',
				],
				'tags'        => [ 'advanced', 'map', 'convert', 'conditional' ],
			],
			'conditional_value'  => [
				'name'        => __( 'Conditional Value', 'wp-aie' ),
				'description' => __( 'Return different value based on condition', 'wp-aie' ),
				'category'    => 'advanced',
				'code'        => 'return intval($value) > 100 ? \'High\' : \'Low\';',
				'example'     => [
					'input'  => '150',
					'output' => 'High',
				],
				'tags'        => [ 'advanced', 'conditional', 'if' ],
			],
			'first_word'         => [
				'name'        => __( 'Extract First Word', 'wp-aie' ),
				'description' => __( 'Get first word from text', 'wp-aie' ),
				'category'    => 'advanced',
				'code'        => '$words = explode(\' \', trim($value)); return $words[0] ?? \'\';',
				'example'     => [
					'input'  => 'Hello World Test',
					'output' => 'Hello',
				],
				'tags'        => [ 'advanced', 'extract', 'word' ],
			],
			'json_decode_field'  => [
				'name'        => __( 'Extract from JSON', 'wp-aie' ),
				'description' => __( 'Parse JSON and extract field', 'wp-aie' ),
				'category'    => 'advanced',
				'code'        => '$data = json_decode($value, true); return $data[\'field_name\'] ?? \'\';',
				'example'     => [
					'input'  => '{"field_name":"value"}',
					'output' => 'value',
				],
				'tags'        => [ 'advanced', 'json', 'extract', 'parse' ],
			],
			'split_by_delimiter' => [
				'name'        => __( 'Split by Delimiter', 'wp-aie' ),
				'description' => __( 'Split by comma and take first part', 'wp-aie' ),
				'category'    => 'advanced',
				'code'        => '$parts = explode(\',\', $value); return trim($parts[0] ?? \'\');',
				'example'     => [
					'input'  => 'first,second,third',
					'output' => 'first',
				],
				'tags'        => [ 'advanced', 'split', 'delimiter', 'explode' ],
			],
		];
	}
}

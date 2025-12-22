<?php
/**
 * AI Content Extractor
 *
 * Extracts clean article content from URLs using OpenAI API
 * Removes sidebars, comments, banners, and other clutter
 *
 * @package WP_AIE\Helper
 */

namespace WP_AIE\Helper;

/**
 * AI Content Extractor Class
 *
 * Uses OpenAI GPT-4 to intelligently extract article content from web pages
 *
 * @package WP_AIE\Helper
 */
class AI_Content_Extractor {

	/**
	 * OpenAI API key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * OpenAI API endpoint
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
		$this->api_key = get_option( 'wp_aie_openai_api_key', '' );

		// Fallback to constant if not set in options
		if ( empty( $this->api_key ) && defined( 'WP_AIE_OPENAI_API_KEY' ) ) {
			$this->api_key = WP_AIE_OPENAI_API_KEY;
		}
	}

	/**
	 * Test OpenAI API connection
	 *
	 * @return string|\\WP_Error Model name or error
	 */
	public function test_connection() {
		if ( empty( $this->api_key ) ) {
			return new \WP_Error(
				'no_api_key',
				__( 'OpenAI API key is not set', 'wp-advanced-import-export' )
			);
		}

		$response = wp_remote_post(
			$this->api_endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'    => $this->model,
						'messages' => array(
							array(
								'role'    => 'user',
								'content' => 'Test connection',
							),
						),
						'max_tokens' => 10,
					)
				),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new \WP_Error(
				'api_error',
				$body['error']['message'] ?? __( 'Unknown API error', 'wp-advanced-import-export' )
			);
		}

		return $this->model;
	}

	/**
	 * Extract content from URL
	 *
	 * @param string $url URL to extract content from
	 * @return array|\\WP_Error Array with title, content, images, or error
	 */
	public function extract_from_url( $url ) {
		if ( empty( $this->api_key ) ) {
			return new \WP_Error(
				'no_api_key',
				__( 'OpenAI API key is not set', 'wp-advanced-import-export' )
			);
		}

		// Fetch the page content
		$html = $this->fetch_url_content( $url );

		if ( is_wp_error( $html ) ) {
			return $html;
		}

		// Extract content using AI
		$result = $this->extract_content_with_ai( $html, $url );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result;
	}

	/**
	 * Fetch URL content
	 *
	 * @param string $url URL to fetch
	 * @return string|\\WP_Error HTML content or error
	 */
	private function fetch_url_content( $url ) {
		$response = wp_remote_get(
			$url,
			array(
				'timeout'    => 30,
				'user-agent' => 'WordPress/' . get_bloginfo( 'version' ) . '; ' . home_url(),
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code( $response );

		if ( $status_code !== 200 ) {
			return new \WP_Error(
				'http_error',
				sprintf(
					/* translators: %d: HTTP status code */
					__( 'HTTP error %d when fetching URL', 'wp-advanced-import-export' ),
					$status_code
				)
			);
		}

		return wp_remote_retrieve_body( $response );
	}

	/**
	 * Extract content using AI
	 *
	 * @param string $html Raw HTML content
	 * @param string $url Original URL
	 * @return array|\\WP_Error Extracted content or error
	 */
	private function extract_content_with_ai( $html, $url ) {
		// Clean HTML to reduce tokens
		$cleaned_html = $this->clean_html( $html );

		// Prepare prompt for GPT
		$prompt = $this->build_extraction_prompt( $cleaned_html, $url );

		// Call OpenAI API
		$response = wp_remote_post(
			$this->api_endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model'       => $this->model,
						'messages'    => array(
							array(
								'role'    => 'system',
								'content' => 'You are a content extraction assistant. Extract article title and main content from HTML, removing sidebars, comments, ads, and navigation. Return valid JSON only.',
							),
							array(
								'role'    => 'user',
								'content' => $prompt,
							),
						),
						'temperature' => 0.3,
						'max_tokens'  => 4000,
						'response_format' => array( 'type' => 'json_object' ),
					)
				),
				'timeout' => 60,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['error'] ) ) {
			return new \WP_Error(
				'api_error',
				$body['error']['message'] ?? __( 'Unknown API error', 'wp-advanced-import-export' )
			);
		}

		if ( ! isset( $body['choices'][0]['message']['content'] ) ) {
			return new \WP_Error(
				'invalid_response',
				__( 'Invalid response from OpenAI API', 'wp-advanced-import-export' )
			);
		}

		// Parse JSON response
		$content_json = json_decode( $body['choices'][0]['message']['content'], true );

		if ( ! $content_json ) {
			return new \WP_Error(
				'invalid_json',
				__( 'Failed to parse AI response', 'wp-advanced-import-export' )
			);
		}

		// Extract images from content
		$images = $this->extract_images_from_content( $content_json['content'] ?? '', $url );

		return array(
			'title'          => $content_json['title'] ?? '',
			'content'        => $content_json['content'] ?? '',
			'excerpt'        => $content_json['excerpt'] ?? '',
			'images'         => $images,
			'featured_image' => $this->get_featured_image( $images ),
			'source_url'     => $url,
		);
	}

	/**
	 * Clean HTML to reduce tokens
	 *
	 * @param string $html Raw HTML
	 * @return string Cleaned HTML
	 */
	private function clean_html( $html ) {
		// Remove scripts
		$html = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $html );
		
		// Remove styles
		$html = preg_replace( '/<style\b[^>]*>.*?<\/style>/is', '', $html );
		
		// Remove comments
		$html = preg_replace( '/<!--.*?-->/s', '', $html );
		
		// Remove excessive whitespace
		$html = preg_replace( '/\s+/', ' ', $html );
		
		// Limit length to fit in context window (approximately 100k characters for GPT-4)
		if ( strlen( $html ) > 100000 ) {
			$html = substr( $html, 0, 100000 );
		}

		return $html;
	}

	/**
	 * Build extraction prompt
	 *
	 * @param string $html Cleaned HTML
	 * @param string $url Original URL
	 * @return string Prompt for GPT
	 */
	private function build_extraction_prompt( $html, $url ) {
		return sprintf(
			"Extract the main article content from this HTML page. Return ONLY a JSON object with these fields:\n\n" .
			"{\n" .
			'  "title": "Article title",\n' .
			'  "content": "Full article content in HTML format (preserve <p>, <h1-h6>, <strong>, <em>, <ul>, <ol>, <li>, <img>, <a> tags)",\n' .
			'  "excerpt": "Brief 1-2 sentence summary"' . "\n" .
			"}\n\n" .
			"Rules:\n" .
			"- Remove navigation, sidebars, comments, ads, footers, headers\n" .
			"- Keep only the main article text and related images\n" .
			"- Preserve paragraph structure and formatting\n" .
			"- Keep image tags with src attributes\n" .
			"- Make image URLs absolute (based on: %s)\n" .
			"- Return valid JSON only\n\n" .
			"HTML:\n%s",
			$url,
			$html
		);
	}

	/**
	 * Extract images from HTML content
	 *
	 * @param string $content HTML content
	 * @param string $base_url Base URL for making relative URLs absolute
	 * @return array Array of image URLs with metadata
	 */
	private function extract_images_from_content( $content, $base_url ) {
		$images = array();

		// Extract all img tags
		preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $matches );

		if ( empty( $matches[1] ) ) {
			return $images;
		}

		foreach ( $matches[1] as $index => $img_url ) {
			// Make URL absolute
			$absolute_url = $this->make_url_absolute( $img_url, $base_url );

			// Extract alt text
			$alt = '';
			if ( preg_match( '/alt=["\']([^"\']+)["\']/i', $matches[0][ $index ], $alt_match ) ) {
				$alt = $alt_match[1];
			}

			// Try to get image dimensions
			$width  = 0;
			$height = 0;
			if ( preg_match( '/width=["\']?(\d+)["\']?/i', $matches[0][ $index ], $w_match ) ) {
				$width = (int) $w_match[1];
			}
			if ( preg_match( '/height=["\']?(\d+)["\']?/i', $matches[0][ $index ], $h_match ) ) {
				$height = (int) $h_match[1];
			}

			$images[] = array(
				'url'    => $absolute_url,
				'alt'    => $alt,
				'width'  => $width,
				'height' => $height,
			);
		}

		return $images;
	}

	/**
	 * Get featured image (first non-small image)
	 *
	 * @param array $images Array of images
	 * @return string|null Featured image URL or null
	 */
	private function get_featured_image( $images ) {
		if ( empty( $images ) ) {
			return null;
		}

		foreach ( $images as $image ) {
			// Skip small images (likely icons or thumbnails)
			if ( ! empty( $image['width'] ) && ! empty( $image['height'] ) ) {
				if ( $image['width'] >= 300 && $image['height'] >= 200 ) {
					return $image['url'];
				}
			} else {
				// If no dimensions, assume it's large enough and use it
				return $image['url'];
			}
		}

		// If no large image found, return first image
		return $images[0]['url'];
	}

	/**
	 * Make URL absolute
	 *
	 * @param string $url URL to convert
	 * @param string $base_url Base URL
	 * @return string Absolute URL
	 */
	private function make_url_absolute( $url, $base_url ) {
		// Already absolute
		if ( preg_match( '/^https?:\/\//i', $url ) ) {
			return $url;
		}

		$base_parts = parse_url( $base_url );

		// Protocol-relative URL
		if ( substr( $url, 0, 2 ) === '//' ) {
			return $base_parts['scheme'] . ':' . $url;
		}

		// Absolute path
		if ( substr( $url, 0, 1 ) === '/' ) {
			return $base_parts['scheme'] . '://' . $base_parts['host'] . $url;
		}

		// Relative path
		$base_path = isset( $base_parts['path'] ) ? dirname( $base_parts['path'] ) : '';
		return $base_parts['scheme'] . '://' . $base_parts['host'] . $base_path . '/' . $url;
	}

	/**
	 * Import image to media library with duplicate checking
	 *
	 * @param string $image_url Image URL
	 * @param string $alt_text Alt text for image
	 * @return int|\\WP_Error Attachment ID or error
	 */
	public function import_image( $image_url, $alt_text = '' ) {
		// Check if image already exists by URL
		$existing_id = $this->find_image_by_url( $image_url );

		if ( $existing_id ) {
			return $existing_id;
		}

		// Download image
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = download_url( $image_url );

		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		// Get filename
		$file_name = basename( parse_url( $image_url, PHP_URL_PATH ) );

		// Check for duplicate by file hash
		$file_hash     = md5_file( $tmp );
		$existing_hash = $this->find_image_by_hash( $file_hash );

		if ( $existing_hash ) {
			@unlink( $tmp );
			return $existing_hash;
		}

		// Prepare file array
		$file_array = array(
			'name'     => $file_name,
			'tmp_name' => $tmp,
		);

		// Upload file
		$id = media_handle_sideload( $file_array, 0 );

		if ( is_wp_error( $id ) ) {
			@unlink( $tmp );
			return $id;
		}

		// Save file hash for future duplicate checking
		update_post_meta( $id, '_aie_image_hash', $file_hash );

		// Save original URL
		update_post_meta( $id, '_aie_source_url', $image_url );

		// Set alt text
		if ( ! empty( $alt_text ) ) {
			update_post_meta( $id, '_wp_attachment_image_alt', $alt_text );
		}

		return $id;
	}

	/**
	 * Find image by source URL
	 *
	 * @param string $url Source URL
	 * @return int|false Attachment ID or false
	 */
	private function find_image_by_url( $url ) {
		global $wpdb;

		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} 
				WHERE meta_key = '_aie_source_url' 
				AND meta_value = %s 
				LIMIT 1",
				$url
			)
		);

		return $attachment_id ? (int) $attachment_id : false;
	}

	/**
	 * Find image by file hash
	 *
	 * @param string $hash File hash
	 * @return int|false Attachment ID or false
	 */
	private function find_image_by_hash( $hash ) {
		global $wpdb;

		$attachment_id = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} 
				WHERE meta_key = '_aie_image_hash' 
				AND meta_value = %s 
				LIMIT 1",
				$hash
			)
		);

		return $attachment_id ? (int) $attachment_id : false;
	}
}

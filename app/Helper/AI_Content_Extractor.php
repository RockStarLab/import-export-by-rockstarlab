<?php
/**
 * AI Content Extractor
 *
 * Extracts clean article content from URLs using OpenAI API
 * Removes sidebars, comments, banners, and other clutter
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

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
		$this->api_key = OpenAI_API_Key::get_api_key();
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
				__( 'OpenAI API key is not set', 'import-export-by-rockstarlab' )
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
						'model'      => $this->model,
						'messages'   => array(
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
				$body['error']['message'] ?? __( 'Unknown API error', 'import-export-by-rockstarlab' )
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
				__( 'OpenAI API key is not set', 'import-export-by-rockstarlab' )
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
					__( 'HTTP error %d when fetching URL', 'import-export-by-rockstarlab' ),
					$status_code
				)
			);
		}

		$body = wp_remote_retrieve_body( $response );

		// Detect charset from headers or meta tags and convert to UTF-8 if needed.
		$charset = $this->detect_charset( $response, $body );
		if ( $charset && strtoupper( str_replace( '-', '', $charset ) ) !== 'UTF8' ) {
			$converted = mb_convert_encoding( $body, 'UTF-8', $charset );
			if ( false !== $converted ) {
				$body = $converted;
			}
		}

		return $body;
	}

	/**
	 * Detect charset from HTTP response headers or HTML meta tags
	 *
	 * @param array  $response HTTP response array.
	 * @param string $body     Response body.
	 * @return string|null Detected charset or null.
	 */
	private function detect_charset( $response, $body ) {
		// 1. Check Content-Type response header.
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		if ( $content_type && preg_match( '/charset=([^\s;"\']+)/i', $content_type, $matches ) ) {
			return trim( $matches[1], ' "\'' );
		}

		// 2. Check <meta charset="..."> (HTML5).
		if ( preg_match( '/<meta[^>]+charset=["\']?([^"\';\s>]+)/i', $body, $matches ) ) {
			return trim( $matches[1], ' "\'' );
		}

		// 3. Check <meta http-equiv="Content-Type" content="...; charset=...">.
		if ( preg_match( '/<meta[^>]+content=["\'][^"\']*charset=([^"\';\s]+)/i', $body, $matches ) ) {
			return trim( $matches[1], ' "\'' );
		}

		// 4. Check XML declaration encoding (<?xml ... encoding="...") for XHTML.
		if ( preg_match( '/<\?xml[^>]+encoding=["\']([^"\']+)["\']/i', $body, $matches ) ) {
			return trim( $matches[1], ' "\'' );
		}

		return null;
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
						'model'           => $this->model,
						'messages'        => array(
							array(
								'role'    => 'system',
								'content' => 'You are a content extraction assistant. Extract article title and COMPLETE main content from HTML, removing sidebars, comments, ads, and navigation. NEVER truncate or shorten the article text — reproduce every paragraph in full. Return valid JSON only.',
							),
							array(
								'role'    => 'user',
								'content' => $prompt,
							),
						),
						'temperature'     => 0.3,
						'max_tokens'      => 16000,
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
				$body['error']['message'] ?? __( 'Unknown API error', 'import-export-by-rockstarlab' )
			);
		}

		if ( ! isset( $body['choices'][0]['message']['content'] ) ) {
			return new \WP_Error(
				'invalid_response',
				__( 'Invalid response from OpenAI API', 'import-export-by-rockstarlab' )
			);
		}

		// Detect if OpenAI truncated the response due to token limit.
		$finish_reason = $body['choices'][0]['finish_reason'] ?? 'stop';
		$was_truncated = ( 'length' === $finish_reason );

		// Parse JSON response
		$content_json = json_decode( $body['choices'][0]['message']['content'], true );

		if ( ! $content_json ) {
			return new \WP_Error(
				'invalid_json',
				__( 'Failed to parse AI response', 'import-export-by-rockstarlab' )
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
			'truncated'      => $was_truncated,
		);
	}

	/**
	 * Clean HTML to reduce tokens
	 *
	 * Aggressively strips noise elements, removes all HTML attributes except
	 * essential ones (src/alt on img, href on a), and applies a character limit.
	 * This ensures that the actual article content fits within the context window.
	 *
	 * @param string $html Raw HTML
	 * @return string Cleaned HTML
	 */
	private function clean_html( $html ) {
		// Remove scripts
		$html = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $html );

		// Remove styles
		$html = preg_replace( '/<style\b[^>]*>.*?<\/style>/is', '', $html );

		// Remove HTML comments
		$html = preg_replace( '/<!--.*?-->/s', '', $html );

		// Remove common noise block elements (nav, header, footer, aside, etc.)
		$noise_tags = array( 'nav', 'header', 'footer', 'aside', 'form', 'iframe', 'noscript', 'svg', 'canvas', 'figure' );
		foreach ( $noise_tags as $tag ) {
			$html = preg_replace( '/<' . $tag . '\b[^>]*>.*?<\/' . $tag . '>/is', '', $html );
		}

		// Strip all HTML attributes except essential ones:
		// - src and alt are kept on <img>
		// - href is kept on <a>
		// Everything else (class, id, style, data-*, aria-*, etc.) is removed.
		$html = preg_replace_callback(
			'/<([a-zA-Z][a-zA-Z0-9]*)\b([^>]*)>/i',
			function ( $matches ) {
				$tag   = strtolower( $matches[1] );
				$attrs = $matches[2];
				$kept  = '';

				if ( 'img' === $tag ) {
					if ( preg_match( '/\bsrc=["\']((?:(?!["\']).)*)["\']/i', $attrs, $m ) ) {
						$kept .= ' src="' . $m[1] . '"';
					}
					if ( preg_match( '/\balt=["\']((?:(?!["\'])[^>])*)["\']/i', $attrs, $m ) ) {
						$kept .= ' alt="' . $m[1] . '"';
					}
				} elseif ( 'a' === $tag ) {
					if ( preg_match( '/\bhref=["\']((?:(?!["\']).)*)["\']/i', $attrs, $m ) ) {
						$kept .= ' href="' . $m[1] . '"';
					}
				}

				return '<' . $tag . $kept . '>';
			},
			$html
		);

		// Remove excessive whitespace
		$html = preg_replace( '/\s+/', ' ', $html );

		// Limit length to fit in context window.
		// After attribute stripping the HTML is typically 60-80% smaller,
		// so 100k characters now represents substantially more actual content.
		if ( mb_strlen( $html, 'UTF-8' ) > 100000 ) {
			$html = mb_substr( $html, 0, 100000, 'UTF-8' );
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
			"- Keep only the main article text and related images\n" . "- Include the COMPLETE article — every paragraph, do not cut off or truncate\n" . "- Preserve paragraph structure and formatting\n" .
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

		$base_parts = wp_parse_url( $base_url );

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
			Fs::load_media_core();

			$tmp = download_url( $image_url );

		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		// Get filename
		$file_name = basename( wp_parse_url( $image_url, PHP_URL_PATH ) );

		// Check for duplicate by file hash
		$file_hash     = md5_file( $tmp );
		$existing_hash = $this->find_image_by_hash( $file_hash );

		if ( $existing_hash ) {
			@wp_delete_file( $tmp );
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
			@wp_delete_file( $tmp );
			return $id;
		}

		// Save file hash for future duplicate checking
		Media_Hash::store_attachment_hash( $id, $file_hash );

		// Save original URL
		update_post_meta( $id, '_rsl_ie_source_url', $image_url );

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

		$attachment_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Direct DB query required here.
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} 
				WHERE meta_key = '_rsl_ie_source_url' 
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
		return Media_Hash::get_attachment_by_hash( $hash );
	}
}

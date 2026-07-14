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

/**
 * Extracts article content from public URLs.
 */
class AI_Content_Extractor {

	/**
	 * OpenAI API key
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * OpenAI chat completions endpoint.
	 *
	 * @var string
	 */
	private $api_endpoint = 'https://api.openai.com/v1/chat/completions';

	/**
	 * OpenAI Responses API endpoint.
	 *
	 * @var string
	 */
	private $responses_endpoint = 'https://api.openai.com/v1/responses';

	/**
	 * Model to use
	 *
	 * @var string
	 */
	private $model = 'gpt-4o-mini';

	/**
	 * Model to use for URL-first extraction.
	 *
	 * @var string
	 */
	private $responses_model = 'gpt-4.1-mini';

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
			return $this->test_wp_ai_client_connection();
		}

		return $this->test_direct_openai_connection();
	}

	/**
	 * Test the WordPress AI Client without reading provider credentials.
	 *
	 * @return string|\WP_Error Model/client label or error.
	 */
	private function test_wp_ai_client_connection() {
		if ( ! OpenAI_API_Key::has_wp_ai_client() ) {
			return new \WP_Error(
				'no_api_key',
				__( 'No AI provider is configured. Configure WordPress AI Client or add a plugin API key.', 'import-export-by-rockstarlab' )
			);
		}

		$result = $this->generate_text_with_wp_ai_client(
			'Reply with OK.',
			'You are testing an AI connection. Return only OK.',
			null,
			20,
			0
		);

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return __( 'WordPress AI Client', 'import-export-by-rockstarlab' );
	}

	/**
	 * Test the plugin-owned OpenAI API key.
	 *
	 * @return string|\WP_Error Model name or error.
	 */
	private function test_direct_openai_connection() {
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
	 * @param string $url  URL to extract content from.
	 * @param int    $delay Optional delay between import requests.
	 * @param string $mode Extraction mode.
	 * @return array|\\WP_Error Array with title, content, images, or error
	 */
	public function extract_from_url( $url, $delay = 0, $mode = 'auto' ) {
		if ( empty( $this->api_key ) && ! OpenAI_API_Key::has_wp_ai_client() ) {
			return new \WP_Error(
				'no_api_key',
				__( 'No AI provider is configured. Configure WordPress AI Client or add a plugin API key.', 'import-export-by-rockstarlab' )
			);
		}

		$delay = max( 0, min( 60, absint( $delay ) ) );
		if ( $delay > 0 ) {
			sleep( $delay );
		}

		$mode = sanitize_key( $mode );
		if ( 'alternate' !== $mode && ! empty( $this->api_key ) ) {
			$result = $this->extract_content_from_url_with_ai( $url );
			if ( ! is_wp_error( $result ) && ! empty( $result['content'] ) ) {
				return $result;
			}
		}

		// Fetch the page content.
		$html = $this->fetch_url_content( $url );

		if ( is_wp_error( $html ) ) {
			return $html;
		}

		$local_result = $this->extract_content_locally( $html, $url, $mode );
		if ( ! is_wp_error( $local_result ) && ! empty( $local_result['content'] ) ) {
			return $local_result;
		}

		// Extract content using AI.
		$result = $this->extract_content_with_ai( $html, $url );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return $result;
	}

	/**
	 * Extract content by giving the URL to OpenAI web search instead of sending full HTML.
	 *
	 * @param string $url URL to extract content from.
	 * @return array|\WP_Error Extracted content or error.
	 */
	private function extract_content_from_url_with_ai( $url ) {
		$response = wp_remote_post(
			$this->responses_endpoint,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'model' => $this->responses_model,
						'tools' => array(
							array(
								'type' => 'web_search',
							),
						),
						'input' => $this->build_url_extraction_prompt( $url ),
						'text'  => array(
							'format' => array(
								'type' => 'json_object',
							),
						),
					)
				),
				'timeout' => 25,
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

		$content = $this->get_responses_output_text( $body );
		if ( empty( $content ) ) {
			return new \WP_Error(
				'invalid_response',
				__( 'Invalid response from OpenAI API', 'import-export-by-rockstarlab' )
			);
		}

		$content_json = json_decode( $content, true );
		if ( ! is_array( $content_json ) ) {
			return new \WP_Error(
				'invalid_json',
				__( 'Failed to parse AI response', 'import-export-by-rockstarlab' )
			);
		}

		return $this->normalize_extraction_result( $content_json, $url, false );
	}

	/**
	 * Get text content from a Responses API response.
	 *
	 * @param array $body Decoded response body.
	 * @return string
	 */
	private function get_responses_output_text( $body ) {
		if ( ! empty( $body['output_text'] ) && is_string( $body['output_text'] ) ) {
			return $body['output_text'];
		}

		if ( empty( $body['output'] ) || ! is_array( $body['output'] ) ) {
			return '';
		}

		$text = '';
		foreach ( $body['output'] as $output_item ) {
			if ( empty( $output_item['content'] ) || ! is_array( $output_item['content'] ) ) {
				continue;
			}

			foreach ( $output_item['content'] as $content_item ) {
				if ( ! empty( $content_item['text'] ) && is_string( $content_item['text'] ) ) {
					$text .= $content_item['text'];
				}
			}
		}

		return $text;
	}

	/**
	 * Build the URL-first extraction prompt.
	 *
	 * @param string $url URL to extract.
	 * @return string Prompt.
	 */
	private function build_url_extraction_prompt( $url ) {
		return sprintf(
			"Open this exact URL using web search and extract the main article content:\n%s\n\n" .
			"Return ONLY a valid JSON object with these fields:\n" .
			"{\n" .
			'  "title": "Article title",' . "\n" .
			'  "content": "Main article content in HTML format using only <p>, <h1-h6>, <strong>, <em>, <ul>, <ol>, <li>, <img>, <a> tags",' . "\n" .
			'  "excerpt": "Brief 1-2 sentence summary",' . "\n" .
			'  "images": [{"url": "Absolute image URL", "alt": "Alt text", "width": 0, "height": 0}],' . "\n" .
			'  "featured_image": "Absolute featured image URL or empty string"' . "\n" .
			"}\n\n" .
			"Rules:\n" .
			"- Use the exact URL above as the source.\n" .
			"- Remove navigation, sidebars, comments, ads, footers, headers, cookie notices, and related-post blocks.\n" .
			"- Preserve paragraph structure and list formatting.\n" .
			"- Keep useful article images when available and make image URLs absolute.\n" .
				'- If you cannot access the page, return empty content.',
			$url
		);
	}

	/**
	 * Fetch URL content
	 *
	 * @param string $url URL to fetch.
	 * @return string|\\WP_Error HTML content or error
	 */
	private function fetch_url_content( $url ) {
		$response = wp_safe_remote_get(
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

		if ( 200 !== $status_code ) {
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
	 * Extract readable content locally as a fast fallback for pages that are too slow for AI.
	 *
	 * @param string $html Raw HTML content.
	 * @param string $url  Source URL.
	 * @param string $mode Extraction mode.
	 * @return array|\WP_Error Extracted content or error.
	 */
	private function extract_content_locally( $html, $url, $mode = 'auto' ) {
		$content_html = $this->find_main_content_html( $html, $mode );
		if ( '' === $content_html ) {
			return new \WP_Error(
				'content_not_found',
				__( 'Could not detect article content', 'import-export-by-rockstarlab' )
			);
		}

		$content = $this->sanitize_local_content_html( $content_html, $url );
		if ( mb_strlen( wp_strip_all_tags( $content ), 'UTF-8' ) < 300 ) {
			return new \WP_Error(
				'content_too_short',
				__( 'Detected article content is too short', 'import-export-by-rockstarlab' )
			);
		}

		$content_json = array(
			'title'          => $this->get_meta_content_from_html( $html, 'property', 'og:title' ),
			'content'        => $content,
			'excerpt'        => $this->get_meta_content_from_html( $html, 'name', 'description' ),
			'featured_image' => $this->get_meta_content_from_html( $html, 'property', 'og:image' ),
		);

		if ( '' === $content_json['title'] ) {
			$content_json['title'] = $this->get_title_from_html( $html );
		}

		return $this->normalize_extraction_result( $content_json, $url, false );
	}

	/**
	 * Find the most likely main content HTML.
	 *
	 * @param string $html Raw HTML.
	 * @param string $mode Extraction mode.
	 * @return string Main content HTML.
	 */
	private function find_main_content_html( $html, $mode = 'auto' ) {
		$html = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $html );
		$html = preg_replace( '/<style\b[^>]*>.*?<\/style>/is', '', $html );
		$html = preg_replace( '/<!--.*?-->/s', '', $html );
		$html = $this->remove_local_noise_blocks( $html );

		$candidates = array();
		foreach ( array( 'article', 'main' ) as $tag ) {
			if ( preg_match_all( '/<' . $tag . '\b([^>]*)>(.*?)<\/' . $tag . '>/is', $html, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $match ) {
					$candidates[] = array(
						'html'   => $match[2],
						'attrs'  => $match[1],
						'source' => $tag,
					);
				}
			}
		}

		$class_patterns = array(
			'entry-content',
			'post-content',
			'article-content',
			'article-body',
			'main-content',
			'content',
		);
		foreach ( $class_patterns as $class_pattern ) {
			if ( preg_match_all( '/<([a-z0-9]+)\b([^>]*class=["\'][^"\']*' . preg_quote( $class_pattern, '/' ) . '[^"\']*["\'][^>]*)>(.*?)<\/\1>/is', $html, $matches, PREG_SET_ORDER ) ) {
				foreach ( $matches as $match ) {
					$candidates[] = array(
						'html'   => $match[3],
						'attrs'  => $match[2],
						'source' => $class_pattern,
					);
				}
			}
		}

		if ( 'alternate' === $mode && preg_match( '/<body\b[^>]*>(.*?)<\/body>/is', $html, $body_match ) ) {
			$candidates[] = array(
				'html'   => $body_match[1],
				'attrs'  => 'body alternate',
				'source' => 'body',
			);
		}

		$scored_candidates = array();
		$seen_candidates   = array();
		foreach ( $candidates as $candidate ) {
			$fingerprint = md5( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $candidate['html'] ) ) );
			if ( isset( $seen_candidates[ $fingerprint ] ) ) {
				continue;
			}
			$seen_candidates[ $fingerprint ] = true;

			$score = $this->score_content_candidate( $candidate['html'], $candidate['attrs'], $candidate['source'] );
			if ( $score <= 0 ) {
				continue;
			}

			$candidate['score']  = $score;
			$scored_candidates[] = $candidate;
		}

		usort(
			$scored_candidates,
			function ( $left, $right ) {
				return $right['score'] <=> $left['score'];
			}
		);

		if ( empty( $scored_candidates ) ) {
			return '';
		}

		$index = ( 'alternate' === $mode && count( $scored_candidates ) > 1 ) ? 1 : 0;
		return $scored_candidates[ $index ]['html'];
	}

	/**
	 * Score a possible article container.
	 *
	 * @param string $html   Candidate HTML.
	 * @param string $attrs  Candidate attributes.
	 * @param string $source Candidate source.
	 * @return float Candidate score.
	 */
	private function score_content_candidate( $html, $attrs, $source ) {
		$text        = trim( preg_replace( '/\s+/', ' ', wp_strip_all_tags( $html ) ) );
		$text_length = mb_strlen( $text, 'UTF-8' );

		if ( $text_length < 150 ) {
			return 0;
		}

		$link_text_length = 0;
		if ( preg_match_all( '/<a\b[^>]*>(.*?)<\/a>/is', $html, $links ) ) {
			foreach ( $links[1] as $link_text ) {
				$link_text_length += mb_strlen( wp_strip_all_tags( $link_text ), 'UTF-8' );
			}
		}

		$link_density = $text_length > 0 ? $link_text_length / $text_length : 0;
		$paragraphs   = preg_match_all( '/<p\b/i', $html );
		$headings     = preg_match_all( '/<h[1-6]\b/i', $html );
		$images       = preg_match_all( '/<img\b/i', $html );

		$score  = min( $text_length, 50000 ) / 20;
		$score += min( $paragraphs, 80 ) * 35;
		$score += min( $headings, 20 ) * 20;
		$score += min( $images, 20 ) * 8;

		if ( 'article' === $source || 'main' === $source ) {
			$score += 120;
		}

		$attrs = strtolower( $attrs );
		if ( preg_match( '/article|post|entry|content|main|body|story/', $attrs ) ) {
			$score += 160;
		}
		if ( preg_match( '/comment|related|sidebar|nav|menu|footer|header|promo|ad-|advert|breadcrumb|share|widget|recommend/', $attrs ) ) {
			$score -= 450;
		}

		$score -= $link_density * 900;

		return $score;
	}

	/**
	 * Sanitize locally extracted HTML.
	 *
	 * @param string $html Content HTML.
	 * @param string $url  Source URL.
	 * @return string Sanitized HTML.
	 */
	private function sanitize_local_content_html( $html, $url ) {
		$html = preg_replace( '/<script\b[^>]*>.*?<\/script>/is', '', $html );
		$html = preg_replace( '/<style\b[^>]*>.*?<\/style>/is', '', $html );
		$html = preg_replace( '/<!--.*?-->/s', '', $html );

		$html = preg_replace_callback(
			'/<(img|a)\b([^>]*)>/i',
			function ( $matches ) use ( $url ) {
				$tag   = strtolower( $matches[1] );
				$attrs = $matches[2];

				if ( 'img' === $tag && preg_match( '/\bsrc=["\']([^"\']+)["\']/i', $attrs, $source ) ) {
					$alt = '';
					if ( preg_match( '/\balt=["\']([^"\']*)["\']/i', $attrs, $alt_match ) ) {
						$alt = $alt_match[1];
					}

					return '<img src="' . esc_url( $this->make_url_absolute( $source[1], $url ) ) . '" alt="' . esc_attr( $alt ) . '">';
				}

				if ( 'a' === $tag && preg_match( '/\bhref=["\']([^"\']+)["\']/i', $attrs, $href ) ) {
					return '<a href="' . esc_url( $this->make_url_absolute( $href[1], $url ) ) . '">';
				}

				return '<' . $tag . '>';
			},
			$html
		);

		$allowed_html = array(
			'p'      => array(),
			'h1'     => array(),
			'h2'     => array(),
			'h3'     => array(),
			'h4'     => array(),
			'h5'     => array(),
			'h6'     => array(),
			'strong' => array(),
			'em'     => array(),
			'ul'     => array(),
			'ol'     => array(),
			'li'     => array(),
			'br'     => array(),
			'a'      => array(
				'href' => true,
			),
			'img'    => array(
				'src' => true,
				'alt' => true,
			),
		);

		$html = wp_kses( $html, $allowed_html );
		$html = $this->trim_local_content_before_title( $html );
		$html = $this->remove_local_noise_fragments( $html );

		return trim( $html );
	}

	/**
	 * Remove obvious non-article blocks before stripping attributes.
	 *
	 * @param string $html Content HTML.
	 * @return string Cleaned HTML.
	 */
	private function remove_local_noise_blocks( $html ) {
		$noise_pattern = 'breadcrumb|breadcrumbs|byline|author|meta|post-meta|article-meta|share|sharing|social|summarize|summary-tools|related|recommend|newsletter|toc|table-of-contents|rating|comments';

		for ( $i = 0; $i < 3; $i++ ) {
			$updated = preg_replace(
				'/<([a-z0-9]+)\b(?=[^>]*(?:class|id|aria-label)=["\'][^"\']*(?:' . $noise_pattern . ')[^"\']*["\'])[^>]*>.*?<\/\1>/is',
				'',
				$html
			);

			if ( $updated === $html ) {
				break;
			}

			$html = $updated;
		}

		return $html;
	}

	/**
	 * Remove inline fragments that commonly leak from article chrome.
	 *
	 * @param string $html Sanitized HTML.
	 * @return string Cleaned HTML.
	 */
	private function remove_local_noise_fragments( $html ) {
		$patterns = array(
			'/<(p|div|section|ul|ol)\b[^>]*>[^<]*(?:Summarize with|Share:|Copy link Copied|ChatGPT|Claude\.ai|Google AI|Grok|Perplexity)[\s\S]*?<\/\1>/i',
			'/<p\b[^>]*>[^<]*(?:[A-Z][a-z]+\s+\d{1,2},\s+\d{4}|[A-Z][a-z]+\s+[A-Z]\.|[0-9]+\s+min\s+Read|Share:|Copy link Copied!)[^<]*<\/p>/i',
			'/<p\b[^>]*>\s*\/\s*<\/p>/i',
			'/<p\b[^>]*>\s*(?:Copied!?|Copy link)\s*<\/p>/i',
			'/<p\b[^>]*>\s*(?:<a\b[^>]*>[^<]*<\/a>\s*){2,}\s*<\/p>/i',
		);

		foreach ( $patterns as $pattern ) {
			$html = preg_replace( $pattern, '', $html );
		}

		$html = preg_replace( '/\s+\/\s+/', ' ', $html );
		$html = preg_replace( '/\s*(?:Copied!?|Copy link)\s*<\/a>/i', '', $html );
		$html = preg_replace( '/<\/a>\s*(?=<p>|<h[1-6]|<img|$)/i', '', $html );
		$html = preg_replace( '/<(p|div|section)\b[^>]*>\s*<\/\1>/i', '', $html );
		$html = preg_replace( '/>\s+</', ">\n<", $html );
		$html = preg_replace( '/[ \t]{2,}/', ' ', $html );

		return $html;
	}

	/**
	 * Drop breadcrumb/byline markup before the first meaningful article heading.
	 *
	 * @param string $html Sanitized HTML.
	 * @return string Trimmed HTML.
	 */
	private function trim_local_content_before_title( $html ) {
		if ( preg_match( '/<h1\b[^>]*>.*?<\/h1>/is', $html, $matches, PREG_OFFSET_CAPTURE ) ) {
			return substr( $html, $matches[0][1] );
		}

		if ( preg_match( '/<h2\b[^>]*>.*?<\/h2>/is', $html, $matches, PREG_OFFSET_CAPTURE ) ) {
			return substr( $html, $matches[0][1] );
		}

		return $html;
	}

	/**
	 * Get meta tag content from HTML.
	 *
	 * @param string $html      Raw HTML.
	 * @param string $attribute Attribute name.
	 * @param string $value     Attribute value.
	 * @return string
	 */
	private function get_meta_content_from_html( $html, $attribute, $value ) {
		$pattern = '/<meta\b(?=[^>]*\b' . preg_quote( $attribute, '/' ) . '=["\']' . preg_quote( $value, '/' ) . '["\'])(?=[^>]*\bcontent=["\']([^"\']*)["\'])[^>]*>/i';
		if ( preg_match( $pattern, $html, $matches ) ) {
			return sanitize_text_field( html_entity_decode( $matches[1], ENT_QUOTES, 'UTF-8' ) );
		}

		return '';
	}

	/**
	 * Get title from HTML.
	 *
	 * @param string $html Raw HTML.
	 * @return string
	 */
	private function get_title_from_html( $html ) {
		if ( preg_match( '/<title\b[^>]*>(.*?)<\/title>/is', $html, $matches ) ) {
			return sanitize_text_field( html_entity_decode( wp_strip_all_tags( $matches[1] ), ENT_QUOTES, 'UTF-8' ) );
		}

		return '';
	}

	/**
	 * Extract content using AI
	 *
	 * @param string $html Raw HTML content.
	 * @param string $url  Original URL.
	 * @return array|\\WP_Error Extracted content or error
	 */
	private function extract_content_with_ai( $html, $url ) {
		// Clean HTML to reduce tokens.
		$cleaned_html = $this->clean_html( $html );

		// Prepare prompt for GPT.
		$prompt = $this->build_extraction_prompt( $cleaned_html, $url );

		if ( empty( $this->api_key ) ) {
			return $this->extract_content_with_wp_ai_client( $prompt, $url );
		}

		// Call OpenAI API.
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

		// Parse JSON response.
		$content_json = json_decode( $body['choices'][0]['message']['content'], true );

		if ( ! $content_json ) {
			return new \WP_Error(
				'invalid_json',
				__( 'Failed to parse AI response', 'import-export-by-rockstarlab' )
			);
		}

		return $this->normalize_extraction_result( $content_json, $url, $was_truncated );
	}

	/**
	 * Extract content through the WordPress AI Client.
	 *
	 * @param string $prompt Prompt text.
	 * @param string $url    Original URL.
	 * @return array|\WP_Error Extracted content or error.
	 */
	private function extract_content_with_wp_ai_client( $prompt, $url ) {
		$content = $this->generate_text_with_wp_ai_client(
			$prompt,
			'You are a content extraction assistant. Extract article title and COMPLETE main content from HTML, removing sidebars, comments, ads, and navigation. NEVER truncate or shorten the article text. Return valid JSON only.',
			$this->get_extraction_json_schema(),
			16000,
			0.3
		);

		if ( is_wp_error( $content ) ) {
			return $content;
		}

		$content_json = json_decode( $content, true );
		if ( ! is_array( $content_json ) ) {
			return new \WP_Error(
				'invalid_json',
				__( 'Failed to parse AI response', 'import-export-by-rockstarlab' )
			);
		}

		return $this->normalize_extraction_result( $content_json, $url, false );
	}

	/**
	 * Generate text through WordPress AI Client without handling provider credentials.
	 *
	 * @param string     $prompt             Prompt text.
	 * @param string     $system_instruction System instruction.
	 * @param array|null $json_schema        Optional JSON schema.
	 * @param int|null   $max_tokens         Optional max token count.
	 * @param float|null $temperature        Optional temperature.
	 * @return string|\WP_Error Generated text or error.
	 */
	private function generate_text_with_wp_ai_client( $prompt, $system_instruction = '', $json_schema = null, $max_tokens = null, $temperature = null ) {
		if ( ! function_exists( 'wp_ai_client_prompt' ) ) {
			return new \WP_Error(
				'ai_client_unavailable',
				__( 'WordPress AI Client is not available.', 'import-export-by-rockstarlab' )
			);
		}

		$builder = call_user_func( 'wp_ai_client_prompt', $prompt );

		if ( '' !== $system_instruction && is_callable( [ $builder, 'using_system_instruction' ] ) ) {
			$builder = $builder->using_system_instruction( $system_instruction );
		}

		if ( null !== $temperature && is_callable( [ $builder, 'using_temperature' ] ) ) {
			$builder = $builder->using_temperature( $temperature );
		}

		if ( null !== $max_tokens && is_callable( [ $builder, 'using_max_tokens' ] ) ) {
			$builder = $builder->using_max_tokens( $max_tokens );
		}

		if ( is_array( $json_schema ) && is_callable( [ $builder, 'as_json_response' ] ) ) {
			$builder = $builder->as_json_response( $json_schema );
		}

		if ( is_callable( [ $builder, 'using_model_preference' ] ) ) {
			$builder = $builder->using_model_preference( 'gpt-4.1-mini', 'gpt-4o-mini', 'gpt-4o' );
		}

		if ( ! is_callable( [ $builder, 'is_supported_for_text_generation' ] ) || ! $builder->is_supported_for_text_generation() ) {
			return new \WP_Error(
				'ai_client_unsupported',
				__( 'No configured WordPress AI provider supports text generation.', 'import-export-by-rockstarlab' )
			);
		}

		return $builder->generate_text();
	}

	/**
	 * JSON schema for AI extraction responses.
	 *
	 * @return array
	 */
	private function get_extraction_json_schema() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'title'          => array( 'type' => 'string' ),
				'content'        => array( 'type' => 'string' ),
				'excerpt'        => array( 'type' => 'string' ),
				'images'         => array(
					'type'  => 'array',
					'items' => array(
						'type'       => 'object',
						'properties' => array(
							'url'    => array( 'type' => 'string' ),
							'alt'    => array( 'type' => 'string' ),
							'width'  => array( 'type' => 'integer' ),
							'height' => array( 'type' => 'integer' ),
						),
					),
				),
				'featured_image' => array( 'type' => 'string' ),
			),
			'required'   => array( 'title', 'content', 'excerpt' ),
		);
	}

	/**
	 * Normalize AI extraction output into the plugin's expected data shape.
	 *
	 * @param array  $content_json  Decoded AI response.
	 * @param string $url           Source URL.
	 * @param bool   $was_truncated Whether the AI response was truncated.
	 * @return array
	 */
	private function normalize_extraction_result( $content_json, $url, $was_truncated ) {
		$content = (string) ( $content_json['content'] ?? '' );
		$images  = array();

		if ( ! empty( $content_json['images'] ) && is_array( $content_json['images'] ) ) {
			foreach ( $content_json['images'] as $image ) {
				if ( is_string( $image ) ) {
					$image = array( 'url' => $image );
				}

				if ( ! is_array( $image ) || empty( $image['url'] ) ) {
					continue;
				}

				$images[] = array(
					'url'    => $this->make_url_absolute( esc_url_raw( $image['url'] ), $url ),
					'alt'    => sanitize_text_field( $image['alt'] ?? '' ),
					'width'  => absint( $image['width'] ?? 0 ),
					'height' => absint( $image['height'] ?? 0 ),
				);
			}
		}

		if ( empty( $images ) ) {
			$images = $this->extract_images_from_content( $content, $url );
		}

		$featured_image = '';
		if ( ! empty( $content_json['featured_image'] ) ) {
			$featured_image = $this->make_url_absolute( esc_url_raw( $content_json['featured_image'] ), $url );
		}

		if ( empty( $featured_image ) ) {
			$featured_image = $this->get_featured_image( $images );
		}

		return array(
			'title'          => sanitize_text_field( $content_json['title'] ?? '' ),
			'content'        => $content,
			'excerpt'        => sanitize_textarea_field( $content_json['excerpt'] ?? '' ),
			'images'         => $images,
			'featured_image' => $featured_image,
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
		if ( empty( $base_parts['scheme'] ) || empty( $base_parts['host'] ) ) {
			return $url;
		}

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

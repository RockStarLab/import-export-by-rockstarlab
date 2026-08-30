<?php
/**
 * Content Sync Media Helper
 *
 * Handles extraction and processing of media files during content sync
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

class Content_Sync_Media {

	/**
	 * Extract all images from a post
	 *
	 * @param int $post_id Post ID.
	 * @return array Array of image data with URLs and metadata
	 */
	public static function extract_post_images( $post_id ) {
		$images = array();
		$post   = get_post( $post_id );

		if ( ! $post ) {
			return $images;
		}

		// 1. Featured Image
		$featured_image = self::get_featured_image( $post_id );
		if ( $featured_image ) {
			$images[] = $featured_image;
		}

		// 2. Attached Images
		$attached_images = self::get_attached_images( $post_id );
		$images          = array_merge( $images, $attached_images );

		// 3. Images from Content (including Gutenberg blocks)
		$content_images = self::get_content_images( $post );
		$images         = array_merge( $images, $content_images );

		// 4. ACF Images
		$acf_images = self::get_acf_images( $post_id );
		$images     = array_merge( $images, $acf_images );

		// 5. Elementor Images
		$elementor_images = self::get_elementor_images( $post_id );
		$images           = array_merge( $images, $elementor_images );

		// 6. Rank Math SEO social/schema images.
		$rank_math_images = self::get_rank_math_images( $post_id );
		$images           = array_merge( $images, $rank_math_images );

		// 7. WooCommerce product gallery images.
		$woocommerce_gallery_images = self::get_woocommerce_product_gallery_images( $post_id );
		$images                     = array_merge( $images, $woocommerce_gallery_images );

		// Remove duplicates based on attachment ID
		$images = self::remove_duplicate_images( $images );

		return $images;
	}

	/**
	 * Get featured image data
	 *
	 * @param int $post_id Post ID.
	 * @return array|null Image data or null
	 */
	private static function get_featured_image( $post_id ) {
		$thumbnail_id = get_post_thumbnail_id( $post_id );

		if ( ! $thumbnail_id ) {
			return null;
		}

		return self::prepare_image_data( $thumbnail_id, 'featured' );
	}

	/**
	 * Get WooCommerce product gallery image data.
	 *
	 * WooCommerce stores product gallery attachment IDs in the
	 * `_product_image_gallery` meta key as a comma-separated list.
	 *
	 * @param int $post_id Post ID.
	 * @return array Array of image data.
	 */
	private static function get_woocommerce_product_gallery_images( $post_id ) {
		$images = array();
		$post   = get_post( $post_id );

		if ( ! $post || 'product' !== $post->post_type ) {
			return $images;
		}

		$gallery = get_post_meta( $post_id, '_product_image_gallery', true );
		if ( empty( $gallery ) || ! is_string( $gallery ) ) {
			return $images;
		}

		$attachment_ids = array_filter( array_map( 'absint', explode( ',', $gallery ) ) );
		foreach ( $attachment_ids as $attachment_id ) {
			$image_data = self::prepare_image_data( $attachment_id, 'woocommerce_product_gallery' );
			if ( $image_data ) {
				$images[] = $image_data;
			}
		}

		return $images;
	}

	/**
	 * Get attached images
	 *
	 * @param int $post_id Post ID.
	 * @return array Array of image data
	 */
	private static function get_attached_images( $post_id ) {
		$images      = array();
		$attachments = get_attached_media( 'image', $post_id );

		foreach ( $attachments as $attachment ) {
			$image_data = self::prepare_image_data( $attachment->ID, 'attached' );
			if ( $image_data ) {
				$images[] = $image_data;
			}
		}

		return $images;
	}

	/**
	 * Get images from post content (including Gutenberg blocks)
	 *
	 * @param \WP_Post $post Post object.
	 * @return array Array of image data
	 */
	private static function get_content_images( $post ) {
		$images = array();

		// Check if content has blocks (Gutenberg)
		if ( has_blocks( $post->post_content ) ) {
			$blocks = parse_blocks( $post->post_content );
			$images = self::extract_images_from_blocks( $blocks );
		}

		// Also extract from HTML img tags
		$html_images = self::extract_images_from_html( $post->post_content );
		$images      = array_merge( $images, $html_images );

		// Extract images from classic-editor shortcodes: [gallery ids="1,2,3"]
		// Covers any shortcode that uses an `ids` attribute with a comma-separated
		// list of attachment IDs (gallery, playlist, etc.).
		$shortcode_images = self::extract_images_from_shortcodes( $post->post_content );
		$images           = array_merge( $images, $shortcode_images );

		return $images;
	}

	/**
	 * Extract images referenced in shortcode `ids` attributes.
	 *
	 * Handles classic WP gallery shortcode `[gallery ids="90,89"]` and any
	 * other shortcode that stores attachment IDs in an `ids` attribute.
	 *
	 * @param string $content Post content.
	 * @return array Array of image data
	 */
	private static function extract_images_from_shortcodes( $content ) {
		$images = array();

		// Match shortcodes that have an `ids` attribute, e.g.
		// [gallery ids="90,89"] or [gallery ids='90,89' columns="2"]
		if ( ! preg_match_all( '/\[\w[^\]]*\bids=["\']([\d,\s]+)["\'][^\]]*\]/i', $content, $matches ) ) {
			return $images;
		}

		foreach ( $matches[1] as $ids_string ) {
			$ids = array_filter( array_map( 'intval', explode( ',', $ids_string ) ) );
			foreach ( $ids as $attachment_id ) {
				$image_data = self::prepare_image_data( $attachment_id, 'shortcode_gallery' );
				if ( $image_data ) {
					$images[] = $image_data;
				}
			}
		}

		return $images;
	}

	/**
	 * Extract images from Gutenberg blocks recursively
	 *
	 * @param array $blocks Parsed blocks.
	 * @return array Array of image data
	 */
	private static function extract_images_from_blocks( $blocks ) {
		$images = array();

		foreach ( $blocks as $block ) {
			// Handle image block
			if ( 'core/image' === $block['blockName'] ) {
				if ( isset( $block['attrs']['id'] ) ) {
					$image_data = self::prepare_image_data( $block['attrs']['id'], 'gutenberg_block' );
					if ( $image_data ) {
						$images[] = $image_data;
					}
				}
			}

			// Handle gallery block
			if ( 'core/gallery' === $block['blockName'] ) {
				if ( isset( $block['attrs']['ids'] ) && is_array( $block['attrs']['ids'] ) ) {
					foreach ( $block['attrs']['ids'] as $image_id ) {
						$image_data = self::prepare_image_data( $image_id, 'gutenberg_gallery' );
						if ( $image_data ) {
							$images[] = $image_data;
						}
					}
				}
			}

			// Handle cover block
			if ( 'core/cover' === $block['blockName'] ) {
				if ( isset( $block['attrs']['id'] ) ) {
					$image_data = self::prepare_image_data( $block['attrs']['id'], 'gutenberg_cover' );
					if ( $image_data ) {
						$images[] = $image_data;
					}
				}
			}

			// Handle media-text block
			if ( 'core/media-text' === $block['blockName'] ) {
				if ( isset( $block['attrs']['mediaId'] ) ) {
					$image_data = self::prepare_image_data( $block['attrs']['mediaId'], 'gutenberg_media_text' );
					if ( $image_data ) {
						$images[] = $image_data;
					}
				}
			}

			// Recursively check inner blocks
			if ( ! empty( $block['innerBlocks'] ) ) {
				$inner_images = self::extract_images_from_blocks( $block['innerBlocks'] );
				$images       = array_merge( $images, $inner_images );
			}
		}

		return $images;
	}

	/**
	 * Extract images from HTML content
	 *
	 * @param string $content HTML content.
	 * @return array Array of image data
	 */
	private static function extract_images_from_html( $content ) {
		$images = array();

		// Match all img tags with wp-image-ID class
		preg_match_all( '/wp-image-(\d+)/i', $content, $matches );

		if ( ! empty( $matches[1] ) ) {
			foreach ( $matches[1] as $image_id ) {
				$image_data = self::prepare_image_data( (int) $image_id, 'content_html' );
				if ( $image_data ) {
					$images[] = $image_data;
				}
			}
		}

		// Also try to match image URLs
		$images = array_merge( $images, self::extract_images_from_html_urls( $content, 'content_html' ) );

		return $images;
	}

	/**
	 * Extract source-site attachments referenced by img src and srcset URLs.
	 *
	 * @param string $content HTML content.
	 * @param string $context Context where the image was found.
	 * @return array Array of image data.
	 */
	private static function extract_images_from_html_urls( $content, $context ) {
		$images = array();

		if ( empty( $content ) || ! is_string( $content ) ) {
			return $images;
		}

		$urls = array();
		if ( preg_match_all( '/<img[^>]+src=["\']([^"\']+)["\'][^>]*>/i', $content, $url_matches ) ) {
			$urls = array_merge( $urls, $url_matches[1] );
		}

		if ( preg_match_all( '/\bsrcset=["\']([^"\']+)["\']/i', $content, $srcset_matches ) ) {
			foreach ( $srcset_matches[1] as $srcset ) {
				foreach ( array_map( 'trim', explode( ',', (string) $srcset ) ) as $candidate ) {
					if ( '' === $candidate ) {
						continue;
					}
					$parts = preg_split( '/\s+/', $candidate );
					if ( ! empty( $parts[0] ) ) {
						$urls[] = $parts[0];
					}
				}
			}
		}

		$urls = array_values( array_unique( array_filter( array_map( 'esc_url_raw', $urls ) ) ) );
		foreach ( $urls as $url ) {
			if ( '' === $url ) {
				continue;
			}

			$attachment_id = attachment_url_to_postid( $url );
			if ( $attachment_id ) {
				$image_data = self::prepare_image_data( $attachment_id, $context );
				if ( $image_data ) {
					$images[] = $image_data;
				}
			}
		}

		return $images;
	}

	/**
	 * Get images from ACF fields
	 *
	 * @param int $post_id Post ID.
	 * @return array Array of image data
	 */
	private static function get_acf_images( $post_id ) {
		$images = array();

		// Check if ACF is active
		if ( ! function_exists( 'get_field_objects' ) ) {
			return $images;
		}

		$fields = get_field_objects( $post_id, false, true, false );

		if ( ! $fields ) {
			return $images;
		}

		foreach ( $fields as $field ) {
			$images = array_merge( $images, self::extract_acf_field_images( $field, $post_id ) );
		}

		return $images;
	}

	/**
	 * Extract images from ACF field recursively
	 *
	 * @param array $field ACF field data.
	 * @param int   $post_id Post ID.
	 * @return array Array of image data
	 */
	private static function extract_acf_field_images( $field, $post_id ) {
		$images = array();

		if ( ! isset( $field['type'] ) ) {
			return $images;
		}

		// Handle image field
		if ( 'image' === $field['type'] && ! empty( $field['value'] ) ) {
			if ( is_numeric( $field['value'] ) ) {
				$image_data = self::prepare_image_data( $field['value'], 'acf_' . $field['name'] );
				if ( $image_data ) {
					$images[] = $image_data;
				}
			} elseif ( is_array( $field['value'] ) && isset( $field['value']['ID'] ) ) {
				$image_data = self::prepare_image_data( $field['value']['ID'], 'acf_' . $field['name'] );
				if ( $image_data ) {
					$images[] = $image_data;
				}
			}
		}

		// Handle file field (for images and other media)
		if ( 'file' === $field['type'] && ! empty( $field['value'] ) ) {
			$file_id = null;
			if ( is_numeric( $field['value'] ) ) {
				$file_id = $field['value'];
			} elseif ( is_array( $field['value'] ) && isset( $field['value']['ID'] ) ) {
				$file_id = $field['value']['ID'];
			}

			if ( $file_id ) {
				// Check if it's an image or other media type
				$mime_type = get_post_mime_type( $file_id );
				if ( $mime_type && strpos( $mime_type, 'image' ) === 0 ) {
					$image_data = self::prepare_image_data( $file_id, 'acf_file_' . $field['name'] );
					if ( $image_data ) {
						$images[] = $image_data;
					}
				} else {
					// For non-image files, still include them as media
					$image_data = self::prepare_image_data( $file_id, 'acf_file_' . $field['name'] );
					if ( $image_data ) {
						$images[] = $image_data;
					}
				}
			}
		}

		// Handle WYSIWYG field - extract media from both formatted ACF output and
		// the raw DB value. The raw value is where classic [gallery]/[playlist]
		// shortcode IDs still exist before ACF/the_content expands them to HTML.
		if ( 'wysiwyg' === $field['type'] && ! empty( $field['value'] ) && is_string( $field['value'] ) ) {
			$wysiwyg_values = array( $field['value'] );
			if ( ! empty( $field['name'] ) ) {
				$raw_value = get_post_meta( (int) $post_id, (string) $field['name'], true );
				if ( is_string( $raw_value ) && '' !== $raw_value && ! in_array( $raw_value, $wysiwyg_values, true ) ) {
					$wysiwyg_values[] = $raw_value;
				}
			}

			foreach ( $wysiwyg_values as $wysiwyg_value ) {
				// Collect by wp-image-ID class.
				if ( preg_match_all( '/wp-image-(\d+)/i', $wysiwyg_value, $matches ) ) {
					foreach ( $matches[1] as $image_id ) {
						$image_data = self::prepare_image_data( (int) $image_id, 'acf_wysiwyg_' . $field['name'] );
						if ( $image_data ) {
							$images[] = $image_data;
						}
					}
				}

				// Also collect images by src/srcset URLs and classic media shortcode IDs.
				$images = array_merge( $images, self::extract_images_from_html_urls( $wysiwyg_value, 'acf_wysiwyg_src_' . $field['name'] ) );
				$images = array_merge( $images, self::extract_images_from_shortcodes( $wysiwyg_value ) );
				if ( class_exists( ACF_Fields::class ) ) {
					foreach ( ACF_Fields::extract_media_shortcode_token_source_ids( $wysiwyg_value ) as $attachment_id ) {
						$image_data = self::prepare_image_data( (int) $attachment_id, 'acf_wysiwyg_shortcode_' . $field['name'] );
						if ( $image_data ) {
							$images[] = $image_data;
						}
					}
				}
			}
		}

		// Handle gallery field
		if ( 'gallery' === $field['type'] && ! empty( $field['value'] ) && is_array( $field['value'] ) ) {
			foreach ( $field['value'] as $image ) {
				$image_id = is_numeric( $image ) ? $image : ( isset( $image['ID'] ) ? $image['ID'] : 0 );
				if ( $image_id ) {
					$image_data = self::prepare_image_data( $image_id, 'acf_gallery_' . $field['name'] );
					if ( $image_data ) {
						$images[] = $image_data;
					}
				}
			}
		}

		// Handle repeater and flexible content
		if ( in_array( $field['type'], array( 'repeater', 'flexible_content' ), true ) && ! empty( $field['value'] ) && is_array( $field['value'] ) ) {
			foreach ( $field['value'] as $row ) {
				if ( is_array( $row ) ) {
					foreach ( $row as $sub_field_name => $sub_field_value ) {
						// Skip ACF internal field (acf_fc_layout)
						if ( $sub_field_name === 'acf_fc_layout' ) {
							continue;
						}

						// Get sub field object
						$sub_field = null;

						// For flexible content, check layouts first
						if ( $field['type'] === 'flexible_content' && isset( $field['layouts'] ) && isset( $row['acf_fc_layout'] ) ) {
							$layout_name = $row['acf_fc_layout'];                           // Find the layout
							foreach ( $field['layouts'] as $layout ) {
								if ( isset( $layout['name'] ) && $layout['name'] === $layout_name ) {
									// Find sub field in this layout
									if ( isset( $layout['sub_fields'] ) ) {
										foreach ( $layout['sub_fields'] as $sf ) {
											if ( isset( $sf['name'] ) && $sf['name'] === $sub_field_name ) {
												$sub_field          = $sf;
												$sub_field['value'] = $sub_field_value;
												break 2; // Break out of both loops
											}
										}
									}
									break;
								}
							}

							if ( ! $sub_field ) {
							}
						}

						// For repeater, check sub_fields
						if ( ! $sub_field && isset( $field['sub_fields'] ) ) {
							foreach ( $field['sub_fields'] as $sf ) {
								if ( isset( $sf['name'] ) && $sf['name'] === $sub_field_name ) {
									$sub_field          = $sf;
									$sub_field['value'] = $sub_field_value;
									break;
								}
							}
						}

						if ( $sub_field ) {
							$sub_images = self::extract_acf_field_images( $sub_field, $post_id );
							if ( ! empty( $sub_images ) ) {
							}
							$images = array_merge( $images, $sub_images );
						}
					}
				}
			}
		}

		// Handle group field
		if ( 'group' === $field['type'] && ! empty( $field['value'] ) && is_array( $field['value'] ) ) {
			foreach ( $field['sub_fields'] as $sub_field ) {
				if ( isset( $field['value'][ $sub_field['name'] ] ) ) {
					$sub_field['value'] = $field['value'][ $sub_field['name'] ];
					$sub_images         = self::extract_acf_field_images( $sub_field, $post_id );
					$images             = array_merge( $images, $sub_images );
				}
			}
		}

		return $images;
	}

	/**
	 * Get images from Elementor
	 *
	 * @param int $post_id Post ID.
	 * @return array Array of image data
	 */
	private static function get_elementor_images( $post_id ) {
		$images = array();

		// Check if Elementor is active
		if ( ! did_action( 'elementor/loaded' ) ) {
			return $images;
		}

		$elementor_data = get_post_meta( $post_id, '_elementor_data', true );

		if ( empty( $elementor_data ) ) {
			return $images;
		}

		$elements = json_decode( $elementor_data, true );

		if ( ! is_array( $elements ) ) {
			return $images;
		}

		$images = self::extract_elementor_images_recursive( $elements );

		return $images;
	}

	/**
	 * Get Rank Math SEO media referenced by social image fields and schema data.
	 *
	 * @param int $post_id Post ID.
	 * @return array Array of image data
	 */
	private static function get_rank_math_images( $post_id ) {
		$images = array();

		$social_id_keys = array(
			'rank_math_facebook_image_id',
			'rank_math_twitter_image_id',
		);

		foreach ( $social_id_keys as $meta_key ) {
			$attachment_id = absint( get_post_meta( $post_id, $meta_key, true ) );
			if ( $attachment_id <= 0 ) {
				continue;
			}

			$image_data = self::prepare_image_data( $attachment_id, $meta_key );
			if ( $image_data ) {
				$images[] = $image_data;
			}
		}

		$social_url_keys = array(
			'rank_math_facebook_image',
			'rank_math_twitter_image',
		);

		foreach ( $social_url_keys as $meta_key ) {
			$url = get_post_meta( $post_id, $meta_key, true );
			if ( ! is_string( $url ) || '' === $url ) {
				continue;
			}

			$attachment_id = attachment_url_to_postid( $url );
			if ( $attachment_id <= 0 ) {
				continue;
			}

			$image_data = self::prepare_image_data( $attachment_id, $meta_key );
			if ( $image_data ) {
				$images[] = $image_data;
			}
		}

		$all_meta = get_post_meta( $post_id );
		foreach ( $all_meta as $meta_key => $values ) {
			if ( ! is_string( $meta_key ) || 0 !== strpos( $meta_key, 'rank_math_schema_' ) ) {
				continue;
			}

			foreach ( (array) $values as $raw_value ) {
				$schema = maybe_unserialize( $raw_value );
				$ids    = self::extract_media_ids_from_rank_math_schema( $schema );
				foreach ( $ids as $attachment_id ) {
					$image_data = self::prepare_image_data( $attachment_id, $meta_key );
					if ( $image_data ) {
						$images[] = $image_data;
					}
				}
			}
		}

		return $images;
	}

	/**
	 * Extract attachment IDs from Rank Math schema arrays.
	 *
	 * @param mixed $value Schema value.
	 * @return array<int,int>
	 */
	private static function extract_media_ids_from_rank_math_schema( $value ) {
		$ids = array();

		if ( is_string( $value ) ) {
			$attachment_id = attachment_url_to_postid( $value );
			if ( $attachment_id > 0 ) {
				$ids[] = (int) $attachment_id;
			}
			return $ids;
		}

		if ( ! is_array( $value ) ) {
			return $ids;
		}

		if ( isset( $value['id'] ) && is_numeric( $value['id'] ) && (int) $value['id'] > 0 ) {
			$attachment = get_post( (int) $value['id'] );
			if ( $attachment && 'attachment' === $attachment->post_type ) {
				$ids[] = (int) $value['id'];
			}
		}

		if ( isset( $value['url'] ) && is_string( $value['url'] ) ) {
			$attachment_id = attachment_url_to_postid( $value['url'] );
			if ( $attachment_id > 0 ) {
				$ids[] = (int) $attachment_id;
			}
		}

		foreach ( $value as $child ) {
			$ids = array_merge( $ids, self::extract_media_ids_from_rank_math_schema( $child ) );
		}

		return array_values( array_unique( array_filter( array_map( 'absint', $ids ) ) ) );
	}

	/**
	 * Extract images from Elementor data recursively
	 *
	 * @param array $elements Elementor elements.
	 * @return array Array of image data
	 */
	private static function extract_elementor_images_recursive( $elements ) {
		$images = array();

		foreach ( $elements as $element ) {
			// Check settings for image fields
			if ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
				foreach ( $element['settings'] as $key => $value ) {
					// Look for image ID fields
					if ( is_numeric( $value ) && strpos( $key, 'image' ) !== false ) {
						$image_data = self::prepare_image_data( $value, 'elementor_' . $key );
						if ( $image_data ) {
							$images[] = $image_data;
						}
					}

					// Look for image array fields
					if ( is_array( $value ) && isset( $value['id'] ) && is_numeric( $value['id'] ) ) {
						$image_data = self::prepare_image_data( $value['id'], 'elementor_' . $key );
						if ( ! $image_data && ! empty( $value['url'] ) ) {
							$image_data = self::prepare_external_media_data( (string) $value['url'], (int) $value['id'], 'elementor_' . $key );
						}
						if ( $image_data ) {
							$images[] = $image_data;
						}
					}

					// Look for gallery fields
					if ( is_array( $value ) && strpos( $key, 'gallery' ) !== false ) {
						foreach ( $value as $gallery_item ) {
							if ( is_array( $gallery_item ) && isset( $gallery_item['id'] ) ) {
								$image_data = self::prepare_image_data( $gallery_item['id'], 'elementor_gallery' );
								if ( ! $image_data && ! empty( $gallery_item['url'] ) ) {
									$image_data = self::prepare_external_media_data( (string) $gallery_item['url'], (int) $gallery_item['id'], 'elementor_gallery' );
								}
								if ( $image_data ) {
									$images[] = $image_data;
								}
							}
						}
					}
				}
			}

			// Recursively check child elements
			if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$child_images = self::extract_elementor_images_recursive( $element['elements'] );
				$images       = array_merge( $images, $child_images );
			}
		}

		return $images;
	}

	/**
	 * Prepare image data for sync
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $context Context where image was found.
	 * @return array|null Image data or null
	 */
	public static function prepare_image_data( $attachment_id, $context = '' ) {
		$attachment = get_post( $attachment_id );

		if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
			return null;
		}

		$file_path = get_attached_file( $attachment_id );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return null;
		}

		$file_hash = Media_Hash::get_or_create_hash( $attachment_id );
		$file_url  = wp_get_attachment_url( $attachment_id );
		$metadata  = wp_get_attachment_metadata( $attachment_id );

		$description = (string) $attachment->post_content;
		if ( class_exists( ACF_Fields::class ) ) {
			$description = ACF_Fields::export_string_with_media_shortcode_tokens( $description );
		}

		$image_data = array(
			'attachment_id' => $attachment_id,
			'url'           => $file_url,
			'source_urls'   => self::get_attachment_source_urls( $attachment_id, $metadata ),
			'file_path'     => $file_path,
			'file_name'     => basename( $file_path ),
			'file_hash'     => $file_hash,
			'file_size'     => filesize( $file_path ),
			'mime_type'     => get_post_mime_type( $attachment_id ),
			'alt_text'      => get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ),
			'title'         => $attachment->post_title,
			'caption'       => $attachment->post_excerpt,
			'description'   => $description,
			'context'       => $context,
			'metadata'      => $metadata,
		);

		if ( class_exists( ACF_Fields::class ) ) {
			$acf = array();
			foreach ( ACF_Fields::get_fields_for_content_type( 'media' ) as $field ) {
				$field_name = isset( $field['name'] ) ? (string) $field['name'] : '';
				if ( '' === $field_name ) {
					continue;
				}
				$acf[ $field_name ] = ACF_Fields::export_value( 'media', (int) $attachment_id, $field_name );
			}
			if ( ! empty( $acf ) ) {
				$image_data['acf'] = $acf;
			}
		}

		return $image_data;
	}

	/**
	 * Prepare sync data for a media URL stored in builder data without a local source attachment.
	 *
	 * Elementor templates can keep external media URLs (including SVG icons) with an
	 * ID that does not exist in the source site's media library. Keeping the original
	 * ID as the mapping key lets Content Sync rewrite the builder JSON after the URL
	 * is downloaded on the receiving site.
	 *
	 * @param string $url           Media URL.
	 * @param int    $attachment_id Source-side ID from builder data, when present.
	 * @param string $context       Context where media was found.
	 * @return array|null Image data or null.
	 */
	private static function prepare_external_media_data( $url, $attachment_id = 0, $context = '' ) {
		$url = esc_url_raw( $url );
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return null;
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$ext  = strtolower( pathinfo( $path, PATHINFO_EXTENSION ) );
		if ( ! in_array( $ext, array( 'jpg', 'jpeg', 'png', 'gif', 'webp', 'avif', 'svg' ), true ) ) {
			return null;
		}

		$filename = sanitize_file_name( wp_basename( $path ) );
		if ( '' === $filename ) {
			$filename = 'content-sync-media.' . $ext;
		}

		$filetype = wp_check_filetype( $filename );
		$mime     = ! empty( $filetype['type'] ) ? $filetype['type'] : '';
		if ( '' === $mime && 'svg' === $ext ) {
			$mime = 'image/svg+xml';
		}

		return array(
			'attachment_id' => absint( $attachment_id ) > 0 ? absint( $attachment_id ) : absint( crc32( $url ) ),
			'url'           => $url,
			'source_urls'   => array(
				'full'    => $url,
				'by_size' => array(),
			),
			'file_path'     => '',
			'file_name'     => $filename,
			'file_hash'     => '',
			'file_size'     => 0,
			'mime_type'     => $mime,
			'alt_text'      => '',
			'title'         => preg_replace( '/\.[^.]+$/', '', $filename ),
			'caption'       => '',
			'description'   => '',
			'context'       => $context,
			'metadata'      => array(),
		);
	}

	/**
	 * Get all known URLs for an attachment, grouped by dimensions.
	 *
	 * @param int   $attachment_id Attachment ID.
	 * @param array $metadata Attachment metadata.
	 * @return array URL data.
	 */
	private static function get_attachment_source_urls( $attachment_id, $metadata ) {
		$urls = array(
			'full'        => wp_get_attachment_url( $attachment_id ),
			'by_size'     => array(),
			'by_basename' => array(),
		);

		if ( ! is_array( $metadata ) || empty( $metadata['file'] ) ) {
			return $urls;
		}

		$upload_dir = wp_upload_dir();
		$directory  = trailingslashit( dirname( $metadata['file'] ) );
		if ( './' === $directory ) {
			$directory = '';
		}

		$full_url = trailingslashit( $upload_dir['baseurl'] ) . ltrim( $metadata['file'], '/' );
		if ( ! empty( $metadata['width'] ) && ! empty( $metadata['height'] ) ) {
			$dimension                        = (int) $metadata['width'] . 'x' . (int) $metadata['height'];
			$basename                         = basename( $metadata['file'] );
			$urls['by_size'][ $dimension ]    = $full_url;
			$urls['by_basename'][ $basename ] = array(
				'url'       => $full_url,
				'dimension' => $dimension,
			);
		}

		if ( empty( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ) {
			return $urls;
		}

		foreach ( $metadata['sizes'] as $size ) {
			if ( empty( $size['file'] ) || empty( $size['width'] ) || empty( $size['height'] ) ) {
				continue;
			}

			$dimension = (int) $size['width'] . 'x' . (int) $size['height'];
			$url       = trailingslashit( $upload_dir['baseurl'] ) . $directory . $size['file'];

			$urls['by_size'][ $dimension ]        = $url;
			$urls['by_basename'][ $size['file'] ] = array(
				'url'       => $url,
				'dimension' => $dimension,
			);
		}

		return $urls;
	}

	/**
	 * Remove duplicate images from array
	 *
	 * @param array $images Array of image data.
	 * @return array Deduplicated array
	 */
	private static function remove_duplicate_images( $images ) {
		$seen   = array();
		$unique = array();

		foreach ( $images as $image ) {
			$key = $image['attachment_id'];
			if ( ! isset( $seen[ $key ] ) ) {
				$seen[ $key ] = true;
				$unique[]     = $image;
			}
		}

		return $unique;
	}

	/**
	 * Ensure all registered image sizes exist for an attachment.
	 *
	 * Checks each registered intermediate size against both the stored metadata
	 * and the physical file on disk. If any are missing, regenerates all sizes
	 * via wp_generate_attachment_metadata().
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public static function ensure_image_sizes( $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return;
		}

		// Only process rasterised images (skip SVG etc.)
		$mime_type = get_post_mime_type( $attachment_id );
		if ( ! $mime_type || 0 !== strpos( $mime_type, 'image/' ) ) {
			return;
		}
		if ( in_array( $mime_type, array( 'image/svg+xml', 'image/svg' ), true ) ) {
			return;
		}

		$meta             = wp_get_attachment_metadata( $attachment_id );
		$registered_sizes = get_intermediate_image_sizes();
		$needs_regen      = false;

		if ( ! is_array( $meta ) || empty( $meta['file'] ) ) {
			$needs_regen = true;
		} else {
			$upload_base = trailingslashit( dirname( $file_path ) );

			foreach ( $registered_sizes as $size_name ) {
				// Missing from metadata
				if ( ! isset( $meta['sizes'][ $size_name ] ) ) {
					$needs_regen = true;
					break;
				}
				// File missing on disk
				$size_file = $upload_base . $meta['sizes'][ $size_name ]['file'];
				if ( ! file_exists( $size_file ) ) {
					$needs_regen = true;
					break;
				}
			}
		}

		if ( $needs_regen ) {
			Fs::load_image_core();
			$new_meta = wp_generate_attachment_metadata( $attachment_id, $file_path );
			wp_update_attachment_metadata( $attachment_id, $new_meta );
		}
	}

	/**
	 * Check if image exists on remote site by hash
	 *
	 * @param string $file_hash File MD5 hash.
	 * @param string $remote_url Remote site URL.
	 * @param string $api_key API key.
	 * @return int|false Attachment ID if exists, false otherwise
	 */
	public static function check_remote_image_exists( $file_hash, $remote_url, $api_key, $source_attachment_id = 0 ) {
		$response = \RockStarLab\ImportExport\Helper\Remote_API::post(
			$remote_url,
			'check-media',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode(
					array(
						'file_hash'            => $file_hash,
						'source_attachment_id' => absint( $source_attachment_id ),
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( isset( $body['exists'] ) && $body['exists'] && isset( $body['attachment_id'] ) ) {
			return (int) $body['attachment_id'];
		}

		return false;
	}
}

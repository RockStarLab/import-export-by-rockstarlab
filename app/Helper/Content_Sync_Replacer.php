<?php
/**
 * Content Sync Replacer Helper
 *
 * Handles domain replacement in content and custom fields during content sync
 *
 * @package WP_AIE\Helper
 */

namespace WP_AIE\Helper;

/**
 * Content Sync Replacer Helper Class
 *
 * Replaces source domain with target domain in content, meta, and serialized data
 *
 * @package WP_AIE\Helper
 */
class Content_Sync_Replacer {

	/**
	 * Replace domain in post data
	 *
	 * @param array  $post_data Post data array.
	 * @param string $source_domain Source domain to replace.
	 * @param string $target_domain Target domain to replace with.
	 * @param array  $image_map Mapping of old attachment IDs to new ones.
	 * @return array Modified post data
	 */
	public static function replace_post_domains( $post_data, $source_domain, $target_domain, $image_map = array() ) {
		// Replace in content
		if ( isset( $post_data['post_content'] ) ) {
			$post_data['post_content'] = self::replace_in_content( $post_data['post_content'], $source_domain, $target_domain, $image_map );
		}

		// Replace in excerpt
		if ( isset( $post_data['post_excerpt'] ) ) {
			$post_data['post_excerpt'] = self::replace_in_text( $post_data['post_excerpt'], $source_domain, $target_domain );
		}

		// Replace in meta
		if ( isset( $post_data['meta'] ) && is_array( $post_data['meta'] ) ) {
			$post_data['meta'] = self::replace_in_meta( $post_data['meta'], $source_domain, $target_domain, $image_map );
		}

		return $post_data;
	}

	/**
	 * Replace domain in content (handles Gutenberg blocks)
	 *
	 * @param string $content Post content.
	 * @param string $source_domain Source domain.
	 * @param string $target_domain Target domain.
	 * @param array  $image_map Image ID mapping.
	 * @return string Modified content
	 */
	private static function replace_in_content( $content, $source_domain, $target_domain, $image_map = array() ) {
		// Replace URLs in content
		$content = self::replace_in_text( $content, $source_domain, $target_domain );

		// If content has blocks, update block attributes
		if ( has_blocks( $content ) ) {
			$content = self::replace_in_blocks( $content, $source_domain, $target_domain, $image_map );
		}

		// Replace attachment IDs in wp-image classes
		if ( ! empty( $image_map ) ) {
			foreach ( $image_map as $old_id => $new_id ) {
				$content = preg_replace(
					'/wp-image-' . $old_id . '\b/',
					'wp-image-' . $new_id,
					$content
				);
			}
		}

		return $content;
	}

	/**
	 * Replace domain in Gutenberg blocks
	 *
	 * @param string $content Content with blocks.
	 * @param string $source_domain Source domain.
	 * @param string $target_domain Target domain.
	 * @param array  $image_map Image ID mapping.
	 * @return string Modified content
	 */
	private static function replace_in_blocks( $content, $source_domain, $target_domain, $image_map = array() ) {
		$blocks = parse_blocks( $content );
		$blocks = self::replace_in_blocks_recursive( $blocks, $source_domain, $target_domain, $image_map );
		return serialize_blocks( $blocks );
	}

	/**
	 * Replace domain in blocks recursively
	 *
	 * @param array  $blocks Parsed blocks.
	 * @param string $source_domain Source domain.
	 * @param string $target_domain Target domain.
	 * @param array  $image_map Image ID mapping.
	 * @return array Modified blocks
	 */
	private static function replace_in_blocks_recursive( $blocks, $source_domain, $target_domain, $image_map = array() ) {
		foreach ( $blocks as &$block ) {
			// Replace in block attributes
			if ( ! empty( $block['attrs'] ) ) {
				// Replace image IDs
				if ( ! empty( $image_map ) ) {
					if ( isset( $block['attrs']['id'] ) && isset( $image_map[ $block['attrs']['id'] ] ) ) {
						$block['attrs']['id'] = $image_map[ $block['attrs']['id'] ];
					}

					if ( isset( $block['attrs']['mediaId'] ) && isset( $image_map[ $block['attrs']['mediaId'] ] ) ) {
						$block['attrs']['mediaId'] = $image_map[ $block['attrs']['mediaId'] ];
					}

					// Gallery IDs
					if ( isset( $block['attrs']['ids'] ) && is_array( $block['attrs']['ids'] ) ) {
						foreach ( $block['attrs']['ids'] as &$id ) {
							if ( isset( $image_map[ $id ] ) ) {
								$id = $image_map[ $id ];
							}
						}
					}
				}

				// Replace URLs in attributes
				foreach ( $block['attrs'] as $key => &$value ) {
					if ( is_string( $value ) ) {
						$value = self::replace_in_text( $value, $source_domain, $target_domain );
					} elseif ( is_array( $value ) ) {
						$value = self::replace_in_array( $value, $source_domain, $target_domain );
					}
				}
			}

			// Replace in block innerHTML
			if ( isset( $block['innerHTML'] ) ) {
				$block['innerHTML'] = self::replace_in_text( $block['innerHTML'], $source_domain, $target_domain );
			}

			// Replace in inner content
			if ( ! empty( $block['innerContent'] ) ) {
				foreach ( $block['innerContent'] as &$inner ) {
					if ( is_string( $inner ) ) {
						$inner = self::replace_in_text( $inner, $source_domain, $target_domain );
					}
				}
			}

			// Recursively process inner blocks
			if ( ! empty( $block['innerBlocks'] ) ) {
				$block['innerBlocks'] = self::replace_in_blocks_recursive( $block['innerBlocks'], $source_domain, $target_domain, $image_map );
			}
		}

		return $blocks;
	}

	/**
	 * Replace domain in post meta
	 *
	 * @param array  $meta Post meta array.
	 * @param string $source_domain Source domain.
	 * @param string $target_domain Target domain.
	 * @param array  $image_map Image ID mapping.
	 * @return array Modified meta
	 */
	private static function replace_in_meta( $meta, $source_domain, $target_domain, $image_map = array() ) {
		foreach ( $meta as $key => &$value ) {
			// Skip internal WordPress meta that shouldn't be replaced
			if ( in_array( $key, array( '_edit_lock', '_edit_last', '_wp_old_slug' ), true ) ) {
				continue;
			}

			// Replace thumbnail ID
			if ( '_thumbnail_id' === $key && ! empty( $image_map ) && isset( $image_map[ $value ] ) ) {
				$value = $image_map[ $value ];
				continue;
			}
			
			// Replace ACF image/file fields (numeric attachment IDs)
			if ( ! empty( $image_map ) && is_numeric( $value ) && $value > 0 && isset( $image_map[ $value ] ) ) {
				error_log( 'WP_AIE Replacer meta: Checking key "' . $key . '" with numeric value ' . $value );
				// Verify this is an attachment by checking if the new ID exists
				$attachment = get_post( $image_map[ $value ] );
				if ( $attachment && 'attachment' === $attachment->post_type ) {
					error_log( 'WP_AIE Replacer meta: Replacing ' . $key . ' => ' . $value . ' to ' . $image_map[ $value ] );
					$value = $image_map[ $value ];
					continue;
				}
			}

			// Handle different value types
			if ( is_string( $value ) ) {
				// Check if it's serialized data
				if ( self::is_serialized( $value ) ) {
					$unserialized = @unserialize( $value );
					if ( false !== $unserialized ) {
						$unserialized = self::replace_in_serialized( $unserialized, $source_domain, $target_domain, $image_map );
						$value        = serialize( $unserialized );
					}
				} else {
					$value = self::replace_in_text( $value, $source_domain, $target_domain );
				}
			} elseif ( is_array( $value ) ) {
				error_log( 'WP_AIE Replacer meta: Processing array for key "' . $key . '", structure: ' . substr( print_r( $value, true ), 0, 500 ) );
				$value = self::replace_in_array( $value, $source_domain, $target_domain, $image_map, 0 );
			}

			// Handle Elementor data
			if ( '_elementor_data' === $key && is_string( $value ) ) {
				$elementor_data = json_decode( $value, true );
				if ( is_array( $elementor_data ) ) {
					$elementor_data = self::replace_in_elementor( $elementor_data, $source_domain, $target_domain, $image_map );
					$value          = wp_json_encode( $elementor_data );
				}
			}
		}

		return $meta;
	}

	/**
	 * Replace domain in serialized data
	 *
	 * @param mixed  $data Unserialized data.
	 * @param string $source_domain Source domain.
	 * @param string $target_domain Target domain.
	 * @param array  $image_map Image ID mapping.
	 * @return mixed Modified data
	 */
	private static function replace_in_serialized( $data, $source_domain, $target_domain, $image_map = array() ) {
		if ( is_string( $data ) ) {
			return self::replace_in_text( $data, $source_domain, $target_domain );
		}

		if ( is_array( $data ) ) {
			return self::replace_in_array( $data, $source_domain, $target_domain, $image_map );
		}

		if ( is_object( $data ) ) {
			foreach ( $data as $key => &$value ) {
				$value = self::replace_in_serialized( $value, $source_domain, $target_domain, $image_map );
			}
		}

		return $data;
	}

	/**
	 * Replace domain in array
	 *
	 * @param array  $array Array to process.
	 * @param string $source_domain Source domain.
	 * @param string $target_domain Target domain.
	 * @param array  $image_map Image ID mapping.
	 * @return array Modified array
	 */
	public static function replace_in_array( $array, $source_domain, $target_domain, $image_map = array(), $depth = 0 ) {
		$indent = str_repeat( '  ', $depth );
		
		foreach ( $array as $key => &$value ) {
			// Replace attachment IDs in common field names
			if ( ! empty( $image_map ) && in_array( $key, array( 'id', 'ID', 'attachment_id', 'image_id', 'media_id', 'image', 'thumbnail_id', 'file' ), true ) ) {
				if ( is_numeric( $value ) && isset( $image_map[ $value ] ) ) {
					error_log( $indent . 'WP_AIE Replacer [depth ' . $depth . ']: Replacing ' . $key . ' ID ' . $value . ' => ' . $image_map[ $value ] );
					$value = $image_map[ $value ];
					continue;
				}
			}

			// ACF gallery and repeater fields - replace numeric IDs
			// This handles ACF image/file fields that store just the attachment ID
			if ( ! empty( $image_map ) && is_numeric( $value ) && $value > 0 ) {
				// Check if this looks like an attachment ID (positive integer)
				// Verify it's actually in the image map to avoid replacing other numeric values
				if ( isset( $image_map[ $value ] ) ) {
					// Double check this is an attachment by checking if the mapped value exists
					$attachment = get_post( $image_map[ $value ] );
					if ( $attachment && 'attachment' === $attachment->post_type ) {
						error_log( $indent . 'WP_AIE Replacer [depth ' . $depth . ']: Replacing numeric ID ' . $value . ' => ' . $image_map[ $value ] . ' (verified)' );
						$value = $image_map[ $value ];
						continue;
					}
				}
			}

			if ( is_string( $value ) ) {
				$value = self::replace_in_text( $value, $source_domain, $target_domain );
			} elseif ( is_array( $value ) ) {
				// Recursively process nested arrays
				$value = self::replace_in_array( $value, $source_domain, $target_domain, $image_map, $depth + 1 );
			}
		}

		return $array;
	}

	/**
	 * Replace domain in Elementor data
	 *
	 * @param array  $elements Elementor elements.
	 * @param string $source_domain Source domain.
	 * @param string $target_domain Target domain.
	 * @param array  $image_map Image ID mapping.
	 * @return array Modified elements
	 */
	private static function replace_in_elementor( $elements, $source_domain, $target_domain, $image_map = array() ) {
		foreach ( $elements as &$element ) {
			// Replace in settings
			if ( isset( $element['settings'] ) && is_array( $element['settings'] ) ) {
				foreach ( $element['settings'] as $key => &$value ) {
					// Replace image IDs
					if ( ! empty( $image_map ) ) {
						if ( is_numeric( $value ) && strpos( $key, 'image' ) !== false && isset( $image_map[ $value ] ) ) {
							$value = $image_map[ $value ];
							continue;
						}

						if ( is_array( $value ) && isset( $value['id'] ) && is_numeric( $value['id'] ) && isset( $image_map[ $value['id'] ] ) ) {
							$value['id'] = $image_map[ $value['id'] ];
						}
					}

					// Replace URLs
					if ( is_string( $value ) ) {
						$value = self::replace_in_text( $value, $source_domain, $target_domain );
					} elseif ( is_array( $value ) ) {
						$value = self::replace_in_array( $value, $source_domain, $target_domain, $image_map );
					}
				}
			}

			// Recursively process child elements
			if ( isset( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$element['elements'] = self::replace_in_elementor( $element['elements'], $source_domain, $target_domain, $image_map );
			}
		}

		return $elements;
	}

	/**
	 * Replace domain in text
	 *
	 * @param string $text Text to process.
	 * @param string $source_domain Source domain.
	 * @param string $target_domain Target domain.
	 * @return string Modified text
	 */
	private static function replace_in_text( $text, $source_domain, $target_domain ) {
		if ( empty( $text ) || ! is_string( $text ) ) {
			return $text;
		}

		// Normalize domains
		$source_domain = self::normalize_domain( $source_domain );
		$target_domain = self::normalize_domain( $target_domain );

		// Replace with different protocols
		$replacements = array(
			'https://' . $source_domain => 'https://' . $target_domain,
			'http://' . $source_domain  => 'http://' . $target_domain,
			'//' . $source_domain        => '//' . $target_domain,
		);

		$text = str_replace( array_keys( $replacements ), array_values( $replacements ), $text );

		return $text;
	}

	/**
	 * Normalize domain (remove protocol and trailing slash)
	 *
	 * @param string $domain Domain to normalize.
	 * @return string Normalized domain
	 */
	private static function normalize_domain( $domain ) {
		// Remove protocol
		$domain = preg_replace( '#^https?://#i', '', $domain );
		
		// Remove trailing slash
		$domain = rtrim( $domain, '/' );

		return $domain;
	}

	/**
	 * Check if string is serialized
	 *
	 * @param string $data Data to check.
	 * @return bool True if serialized
	 */
	private static function is_serialized( $data ) {
		if ( ! is_string( $data ) ) {
			return false;
		}

		$data = trim( $data );

		if ( 'N;' === $data ) {
			return true;
		}

		if ( ! preg_match( '/^([adObis]):/', $data, $matches ) ) {
			return false;
		}

		switch ( $matches[1] ) {
			case 'a':
			case 'O':
			case 's':
				if ( preg_match( "/^{$matches[1]}:[0-9]+:.*[;}]\$/s", $data ) ) {
					return true;
				}
				break;
			case 'b':
			case 'i':
			case 'd':
				if ( preg_match( "/^{$matches[1]}:[0-9.E-]+;\$/", $data ) ) {
					return true;
				}
				break;
		}

		return false;
	}
}

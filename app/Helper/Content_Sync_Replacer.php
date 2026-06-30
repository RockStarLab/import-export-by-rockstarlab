<?php
/**
 * Content Sync Replacer Helper
 *
 * Handles domain replacement in content and custom fields during content sync
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

class Content_Sync_Replacer {

	/**
	 * Collect a map of post IDs referenced by ACF post-reference fields in meta.
	 *
	 * This is used during Content Sync to resolve ACF post_object / relationship values
	 * across sites without requiring the referenced posts to have been synced already.
	 *
	 * Output format:
	 * - [ source_id => [ 'post_type' => 'post', 'post_name' => 'hello-world', 'post_title' => 'Hello world!' ] ]
	 *
	 * @param array $meta Post meta array (already deserialized).
	 * @return array Map of referenced post IDs to portable descriptors.
	 */
	public static function collect_acf_post_reference_map_from_meta( $meta ) {
		$map = array();

		if ( empty( $meta ) || ! is_array( $meta ) || ! function_exists( 'acf_get_field' ) ) {
			return $map;
		}

		foreach ( $meta as $key => $value ) {
			if ( ! is_string( $key ) || strpos( $key, '_' ) === 0 ) {
				continue;
			}

			$field_obj     = null;
			$field_ref_key = '_' . $key;
			if ( isset( $meta[ $field_ref_key ] ) && is_string( $meta[ $field_ref_key ] ) ) {
				$field_ref = $meta[ $field_ref_key ];
				if ( strpos( $field_ref, 'field_' ) === 0 ) {
					$field_obj = acf_get_field( $field_ref );
				}
			}
			if ( ! $field_obj ) {
				// Fallback: ACF can resolve fields by name on the current site.
				$field_obj = acf_get_field( $key );
			}

			if ( ! $field_obj || empty( $field_obj['type'] ) ) {
				continue;
			}

			if ( ! in_array( $field_obj['type'], array( 'post_object', 'relationship', 'page_link' ), true ) ) {
				continue;
			}

			$ids = is_array( $value ) ? $value : array( $value );
			foreach ( $ids as $id ) {
				if ( is_object( $id ) && isset( $id->ID ) ) {
					$id = $id->ID;
				} elseif ( is_array( $id ) && isset( $id['ID'] ) ) {
					$id = $id['ID'];
				}

				if ( ! is_numeric( $id ) || (int) $id <= 0 ) {
					continue;
				}

				$id = (int) $id;
				if ( isset( $map[ $id ] ) ) {
					continue;
				}

				$p = get_post( $id );
				if ( ! $p ) {
					continue;
				}

				$map[ $id ] = array(
					'post_type'  => (string) $p->post_type,
					'post_name'  => (string) $p->post_name,
					'post_title' => (string) $p->post_title,
				);
			}
		}

		return $map;
	}

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

		// Replace domain and image IDs in variation meta (variable products).
		if ( ! empty( $post_data['variations'] ) && is_array( $post_data['variations'] ) ) {
			foreach ( $post_data['variations'] as &$variation ) {
				if ( ! empty( $variation['meta'] ) && is_array( $variation['meta'] ) ) {
					$variation['meta'] = self::replace_in_meta( $variation['meta'], $source_domain, $target_domain, $image_map );
				}
			}
			unset( $variation );
		}

		// Replace domain and image IDs in grouped product children meta.
		if ( ! empty( $post_data['grouped_children'] ) && is_array( $post_data['grouped_children'] ) ) {
			foreach ( $post_data['grouped_children'] as &$grouped_child ) {
				if ( ! empty( $grouped_child['meta'] ) && is_array( $grouped_child['meta'] ) ) {
					$grouped_child['meta'] = self::replace_in_meta( $grouped_child['meta'], $source_domain, $target_domain, $image_map );
				}
				if ( ! empty( $grouped_child['post_content'] ) ) {
					$grouped_child['post_content'] = self::replace_in_content( $grouped_child['post_content'], $source_domain, $target_domain, $image_map );
				}
				if ( ! empty( $grouped_child['post_excerpt'] ) ) {
					$grouped_child['post_excerpt'] = self::replace_in_text( $grouped_child['post_excerpt'], $source_domain, $target_domain );
				}
			}
			unset( $grouped_child );
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

			// Replace attachment IDs inside shortcode `ids` attributes.
			// Handles classic [gallery ids="90,89"] and similar shortcodes.
			$content = preg_replace_callback(
				'/\b(ids=["\'])([\d,\s]+)(["\'])/i',
				function ( $m ) use ( $image_map ) {
					$ids = array_map( 'intval', explode( ',', $m[2] ) );
					$ids = array_map(
						function ( $id ) use ( $image_map ) {
							return isset( $image_map[ $id ] ) ? (int) $image_map[ $id ] : $id;
						},
						$ids
					);
					return $m[1] . implode( ',', $ids ) . $m[3];
				},
				$content
			);
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
	 * Public wrapper for replace_in_meta, used by receive_content on push path.
	 *
	 * @param array  $meta Post meta array.
	 * @param string $source_domain Source domain.
	 * @param string $target_domain Target domain.
	 * @param array  $image_map Image ID mapping.
	 * @return array Modified meta
	 */
	public static function replace_in_meta_public( $meta, $source_domain, $target_domain, $image_map = array() ) {
		return self::replace_in_meta( $meta, $source_domain, $target_domain, $image_map );
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

			if ( ! empty( $image_map ) && in_array( $key, array( 'rank_math_facebook_image_id', 'rank_math_twitter_image_id' ), true ) && isset( $image_map[ (int) $value ] ) ) {
				$value = $image_map[ (int) $value ];
				continue;
			}

			if ( ! empty( $image_map ) && in_array( $key, array( 'rank_math_facebook_image', 'rank_math_twitter_image' ), true ) ) {
				$companion_id_key = $key . '_id';
				$source_id        = isset( $meta[ $companion_id_key ] ) ? (int) $meta[ $companion_id_key ] : 0;
				if ( $source_id > 0 && isset( $image_map[ $source_id ] ) ) {
					$new_url = wp_get_attachment_url( (int) $image_map[ $source_id ] );
					if ( $new_url ) {
						$value = $new_url;
						continue;
					}
				}
			}

			// Replace ACF image/file fields (numeric attachment IDs).
			// We MUST check the ACF field type first to avoid corrupting ACF repeater row
			// counts: during Pull, $image_map keys are remote attachment IDs which can be
			// small numbers (1, 2, 3…) that accidentally match a repeater's row count value.
			if ( ! empty( $image_map ) && is_numeric( $value ) && $value > 0 && isset( $image_map[ $value ] ) ) {
				$confirmed_image = self::is_acf_image_or_file_field( $key, $meta );
				if ( ! $confirmed_image ) {
					// ACF couldn't confirm the field type (field group may not exist on this
					// site). Fall back to verifying the MAPPED value is really an attachment.
					// We still require the meta key to have an ACF field-key reference so we
					// do not accidentally remap unrelated numeric meta like post_views_count.
					$field_ref_key = '_' . $key;
					$has_acf_ref   = isset( $meta[ $field_ref_key ] )
						&& is_string( $meta[ $field_ref_key ] )
						&& strpos( $meta[ $field_ref_key ], 'field_' ) === 0;
					if ( $has_acf_ref ) {
						$attachment = get_post( $image_map[ $value ] );
						if ( $attachment && 'attachment' === $attachment->post_type ) {
							$confirmed_image = true;
						}
					}
				}
				if ( $confirmed_image ) {
					$attachment = get_post( $image_map[ $value ] );
					if ( $attachment && 'attachment' === $attachment->post_type ) {
						$value = $image_map[ $value ];
						continue;
					}
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
					// Replace wp-image-X attachment IDs and src URLs in HTML values (e.g. ACF WYSIWYG).
					//
					// Strategy: match <img> tags by their wp-image-{new_id} class (set in step 1
					// below), then look up the correct local URL by matching the WxH dimensions
					// extracted from the current src against the local attachment metadata sizes.
					//
					// We deliberately do NOT match by filename because WordPress may rename the
					// file when it re-scales an already-scaled image on import (e.g. remote has
					// "image-scaled.jpg" which locally becomes "image-scaled-1.jpg"), so the
					// basenames between remote and local can differ completely.
					if ( ! empty( $image_map ) && false !== strpos( $value, '<img' ) ) {
						foreach ( $image_map as $old_id => $new_id ) {
							// Step 1: Replace wp-image-{old_id} class reference.
							$value = preg_replace( '/\bwp-image-' . $old_id . '\b/', 'wp-image-' . $new_id, $value );

							$new_url = wp_get_attachment_url( $new_id );
							if ( ! $new_url ) {
								continue;
							}

							// Build a "WxH" → local URL map from the local attachment metadata.
							// NOTE: must NOT use $meta here — that variable is the outer function
							// parameter (post meta array) being iterated; shadowing it would
							// corrupt the loop and cause replace_in_meta to return garbage.
							$size_dim_map = array();
							$att_meta     = wp_get_attachment_metadata( $new_id );
							if ( is_array( $att_meta ) && ! empty( $att_meta['sizes'] ) && ! empty( $att_meta['file'] ) ) {
								$upload_dir = wp_upload_dir();
								$dir_prefix = trailingslashit( $upload_dir['baseurl'] )
									. ltrim( dirname( $att_meta['file'] ), '/' ) . '/';
								foreach ( $att_meta['sizes'] as $size_data ) {
									if ( ! empty( $size_data['file'] )
										&& isset( $size_data['width'], $size_data['height'] ) ) {
										$dim_key                  = $size_data['width'] . 'x' . $size_data['height'];
										$size_dim_map[ $dim_key ] = $dir_prefix . $size_data['file'];
									}
								}
							}

							// Step 2: For every <img> whose class now contains wp-image-{new_id},
							// replace its src with the correct local URL.
							// We match by class (not by filename) so the replacement works even
							// when local and remote filenames differ after WP re-scaling.
							$value = preg_replace_callback(
								'/<img\b[^>]*\bwp-image-' . $new_id . '\b[^>]*>/i',
								function ( $img_match ) use ( $new_id, $new_url, $size_dim_map ) {
									$img_tag = $img_match[0];

									// Extract current src attribute.
									if ( ! preg_match( '/\bsrc=(["\'])([^"\']+)\1/i', $img_tag, $src_m ) ) {
										return $img_tag;
									}
									$quote = $src_m[1];
									$src   = $src_m[2];

									// Determine which local URL to use.
									// Try to extract WxH dimensions from the src filename
									// (e.g. "image-200x300.jpg" → "200x300") and look up the
									// corresponding local size. Fall back to full-size URL.
									$local_url = $new_url;
									$basename  = basename( wp_parse_url( $src, PHP_URL_PATH ) );
									if ( preg_match( '/-(\d+x\d+)\.[^.]+$/i', $basename, $dim_m ) ) {
										$dim_key = $dim_m[1];
										if ( isset( $size_dim_map[ $dim_key ] ) ) {
											$local_url = $size_dim_map[ $dim_key ];
										}
									}

									// Replace src attribute value in the img tag.
									return preg_replace(
										'/\bsrc=(["\'])[^"\']+\1/i',
										'src=' . $quote . $local_url . $quote,
										$img_tag
									);
								},
								$value
							);
						}
					}
				}
			} elseif ( is_array( $value ) ) {
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
		// Keys whose numeric values are definitely attachment IDs.
		$attachment_id_keys = array( 'id', 'ID', 'attachment_id', 'image_id', 'media_id', 'image', 'thumbnail_id', 'file' );

		if ( ! empty( $image_map ) && isset( $array['id'], $array['url'] ) && is_numeric( $array['id'] ) && isset( $image_map[ (int) $array['id'] ] ) ) {
			$new_attachment_id = (int) $image_map[ (int) $array['id'] ];
			$new_url           = wp_get_attachment_url( $new_attachment_id );
			if ( $new_url ) {
				$array['id']  = $new_attachment_id;
				$array['url'] = $new_url;
			}
		}

		// Detect if this looks like a flat gallery-style array: a sequential list where
		// every element is a positive integer (e.g. ACF gallery stored as [123, 456, 789]).
		// In that case ALL values are treated as potential attachment IDs.
		$is_flat_id_list = ! empty( $image_map ) && ! empty( $array ) && array_keys( $array ) === range( 0, count( $array ) - 1 );
		if ( $is_flat_id_list ) {
			foreach ( $array as $v ) {
				if ( ! is_int( $v ) && ! ( is_string( $v ) && ctype_digit( $v ) ) ) {
					$is_flat_id_list = false;
					break;
				}
			}
		}

		foreach ( $array as $key => &$value ) {
			// Replace attachment IDs for well-known attachment-ID keys.
			if ( ! empty( $image_map ) && in_array( $key, $attachment_id_keys, true ) ) {
				if ( is_numeric( $value ) && isset( $image_map[ (int) $value ] ) ) {
					$attachment = get_post( $image_map[ (int) $value ] );
					if ( $attachment && 'attachment' === $attachment->post_type ) {
						$value = $image_map[ (int) $value ];
						continue;
					}
				}
			}

			// Replace IDs in flat sequential lists of integers (ACF gallery fields etc.)
			if ( $is_flat_id_list && is_numeric( $value ) && isset( $image_map[ (int) $value ] ) ) {
				$attachment = get_post( $image_map[ (int) $value ] );
				if ( $attachment && 'attachment' === $attachment->post_type ) {
					$value = $image_map[ (int) $value ];
					continue;
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
			'//' . $source_domain       => '//' . $target_domain,
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
	 * Check if a meta key corresponds to an ACF image or file field.
	 *
	 * ACF stores a field-key reference in the "_fieldname" meta entry.
	 * We use that reference to look up the field type and confirm it is
	 * an 'image' or 'file' type before replacing a numeric meta value
	 * with an attachment ID.  This prevents repeater row-count values
	 * (e.g. my_repeater = 3) from being wrongly replaced with an
	 * attachment ID that happens to share the same number.
	 *
	 * @param string $key  Meta key being evaluated.
	 * @param array  $meta Full meta array (used to look up the ACF field reference).
	 * @return bool True only when ACF confirms this is an image or file field.
	 */
	private static function is_acf_image_or_file_field( $key, $meta ) {
		if ( ! function_exists( 'acf_get_field' ) ) {
			return false;
		}

		$field_ref_key = '_' . $key;
		if ( ! isset( $meta[ $field_ref_key ] ) ) {
			return false;
		}

		$field_ref = $meta[ $field_ref_key ];
		if ( ! is_string( $field_ref ) || 0 !== strpos( $field_ref, 'field_' ) ) {
			return false;
		}

		$field_obj = acf_get_field( $field_ref );
		if ( ! $field_obj || ! isset( $field_obj['type'] ) ) {
			return false;
		}

		return in_array( $field_obj['type'], array( 'image', 'file' ), true );
	}

	/**
	 * After importing terms, re-save any ACF taxonomy fields in post meta so they
	 * reference the correct local term IDs instead of the source-site term IDs.
	 *
	 * Strategy: any ACF field (identified by a companion "_fieldname" = "field_xxx"
	 * meta entry) whose value is a term ID — or an array of term IDs — that exists
	 * in $term_id_map gets its value rewritten to the corresponding local term ID.
	 *
	 * acf_get_field() is used opportunistically: if it can resolve the field object
	 * we use the type to SKIP known non-taxonomy fields (images, repeater counts…).
	 * If it cannot resolve the field (field group not imported on target site) we
	 * still proceed with the heuristic translation, which is safe because
	 * $term_id_map only contains the specific source term IDs being synced.
	 *
	 * @param array $meta        Full post meta array (key → value, already deserialized).
	 * @param int   $post_id     Target post ID.
	 * @param array $term_id_map Map of source_term_id => local_term_id.
	 */
	public static function translate_acf_taxonomy_fields_in_meta( $meta, $post_id, $term_id_map ) {
		if ( empty( $term_id_map ) ) {
			return;
		}

		foreach ( $meta as $key => $value ) {
			// Only process non-underscore-prefixed keys (skip ACF reference entries).
			if ( strpos( $key, '_' ) === 0 ) {
				continue;
			}

			// Must have an ACF field-key reference to be an ACF field at all.
			$field_ref_key = '_' . $key;
			if ( ! isset( $meta[ $field_ref_key ] ) ) {
				continue;
			}
			$field_ref = $meta[ $field_ref_key ];
			if ( ! is_string( $field_ref ) || strpos( $field_ref, 'field_' ) !== 0 ) {
				continue;
			}

			// If ACF is available and the field definition exists on this site,
			// skip any field type that is definitely NOT a taxonomy field.
			if ( function_exists( 'acf_get_field' ) ) {
				$field_obj = acf_get_field( $field_ref );
				if ( $field_obj && isset( $field_obj['type'] ) && $field_obj['type'] !== 'taxonomy' ) {
					continue;
				}
				// $field_obj === false → field not registered here → fall through and translate.
			}

			// Translate single term ID.
			if ( is_numeric( $value ) && (int) $value > 0 && isset( $term_id_map[ (int) $value ] ) ) {
				update_post_meta( $post_id, $key, $term_id_map[ (int) $value ] );
				continue;
			}

			// Translate array of term IDs (multi-select taxonomy field).
			if ( is_array( $value ) ) {
				$changed    = false;
				$translated = array();
				foreach ( $value as $item ) {
					$int_item = (int) $item;
					if ( is_numeric( $item ) && $int_item > 0 && isset( $term_id_map[ $int_item ] ) ) {
						$translated[] = $term_id_map[ $int_item ];
						$changed      = true;
					} else {
						$translated[] = $item;
					}
				}
				if ( $changed ) {
					update_post_meta( $post_id, $key, $translated );
				}
			}
		}
	}

	/**
	 * After importing posts, re-save any ACF post-reference fields (post_object / relationship)
	 * so they reference the correct local post IDs instead of the source-site IDs.
	 *
	 * Strategy: identify ACF fields via the companion "_fieldname" = "field_xxx" meta entry,
	 * resolve the field definition with acf_get_field(), and only translate known reference
	 * field types. Each numeric ID is translated via:
	 * - self-reference: if it equals $source_post_id, rewrite to $post_id
	 * - otherwise: find a local post with meta "_rsl_ie_original_post_id" = source_id
	 *
	 * If a referenced post has not been synced (no mapping exists), the value is left intact.
	 *
	 * @param array $meta           Full post meta array (key → value, already deserialized).
	 * @param int   $post_id        Target post ID.
	 * @param int   $source_post_id Source-site post ID for this post.
	 */
	public static function translate_acf_post_reference_fields_in_meta( $meta, $post_id, $source_post_id, $post_ref_map = array() ) {
		if ( empty( $meta ) || ! function_exists( 'acf_get_field' ) ) {
			return;
		}

		$post_id        = (int) $post_id;
		$source_post_id = (int) $source_post_id;
		$post_ref_map   = is_array( $post_ref_map ) ? $post_ref_map : array();

		$map_id = static function ( $maybe_source_id ) use ( $post_id, $source_post_id ) {
			$maybe_source_id = (int) $maybe_source_id;
			if ( $maybe_source_id <= 0 ) {
				return 0;
			}

			// Self-reference: this post is guaranteed to exist locally now.
			if ( $source_post_id > 0 && $maybe_source_id === $source_post_id ) {
				return $post_id;
			}

			// phpcs:disable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- Indexed meta_key lookup, hard-limited to 1 ID.
			$found = get_posts(
				array(
					'post_type'              => 'any',
					'post_status'            => 'any',
					'posts_per_page'         => 1,
					'fields'                 => 'ids',
					'no_found_rows'          => true,
					'cache_results'          => false,
					'update_post_meta_cache' => false,
					'update_post_term_cache' => false,
					'meta_key'               => '_rsl_ie_original_post_id',
					'meta_value'             => $maybe_source_id,
				)
			);
			// phpcs:enable WordPress.DB.SlowDBQuery.slow_db_query_meta_key,WordPress.DB.SlowDBQuery.slow_db_query_meta_value

			return ! empty( $found ) ? (int) $found[0] : 0;
		};

		foreach ( $meta as $key => $value ) {
			// Only process non-underscore-prefixed keys (skip ACF reference entries).
			if ( strpos( $key, '_' ) === 0 ) {
				continue;
			}

			$field_obj     = null;
			$field_ref_key = '_' . $key;
			if ( isset( $meta[ $field_ref_key ] ) && is_string( $meta[ $field_ref_key ] ) ) {
				$field_ref = $meta[ $field_ref_key ];
				if ( strpos( $field_ref, 'field_' ) === 0 ) {
					$field_obj = acf_get_field( $field_ref );
				}
			}
			if ( ! $field_obj ) {
				// Fallback: resolve by field name on the receiving site (field keys differ across sites).
				$field_obj = acf_get_field( $key );
			}
			if ( ! $field_obj || empty( $field_obj['type'] ) ) {
				continue;
			}

			if ( ! in_array( $field_obj['type'], array( 'post_object', 'relationship', 'page_link' ), true ) ) {
				continue;
			}

			// Ensure the ACF field-key reference meta matches THIS site (field keys differ across sites).
			if ( ! empty( $field_obj['key'] ) && is_string( $field_obj['key'] ) ) {
				$existing_ref = get_post_meta( $post_id, '_' . $key, true );
				if ( $existing_ref !== $field_obj['key'] ) {
					update_post_meta( $post_id, '_' . $key, $field_obj['key'] );
				}
			}

			$map_id_with_fallbacks = static function ( $maybe_source_id ) use ( $map_id, $post_ref_map ) {
				$maybe_source_id = (int) $maybe_source_id;
				if ( $maybe_source_id <= 0 ) {
					return 0;
				}

				$mapped = $map_id( $maybe_source_id );
				if ( $mapped > 0 ) {
					return $mapped;
				}

				// Fallback: resolve by slug when sender provided a portable reference.
				if ( isset( $post_ref_map[ $maybe_source_id ] ) && is_array( $post_ref_map[ $maybe_source_id ] ) ) {
					$ref  = $post_ref_map[ $maybe_source_id ];
					$pt   = isset( $ref['post_type'] ) ? (string) $ref['post_type'] : '';
					$slug = isset( $ref['post_name'] ) ? (string) $ref['post_name'] : '';

					if ( $pt && $slug ) {
						$by_path = get_page_by_path( $slug, OBJECT, $pt );
						if ( $by_path && ! empty( $by_path->ID ) ) {
							return (int) $by_path->ID;
						}

						$found = get_posts(
							array(
								'post_type'      => $pt,
								'post_status'    => 'any',
								'posts_per_page' => 1,
								'fields'         => 'ids',
								'name'           => $slug,
							)
						);
						if ( ! empty( $found ) ) {
							return (int) $found[0];
						}
					}
				}

				return 0;
			};

				// Translate single post ID.
			if ( is_numeric( $value ) && (int) $value > 0 ) {
				$mapped = $map_id_with_fallbacks( $value );
				if ( $mapped > 0 && $mapped !== (int) $value ) {
					update_post_meta( $post_id, $key, $mapped );
				}
				continue;
			}

				// Translate array of post IDs.
			if ( is_array( $value ) ) {
				$changed    = false;
				$translated = array();
				foreach ( $value as $item ) {
					if ( is_numeric( $item ) && (int) $item > 0 ) {
						$mapped = $map_id_with_fallbacks( $item );
						if ( $mapped > 0 ) {
							$translated[] = $mapped;
							$changed      = $changed || ( $mapped !== (int) $item );
							continue;
						}
					}
					$translated[] = $item;
				}
				if ( $changed ) {
					update_post_meta( $post_id, $key, $translated );
				}
			}
		}
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

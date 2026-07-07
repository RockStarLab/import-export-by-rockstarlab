<?php
/**
 * SEO plugin field helpers.
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

class Seo_Fields {

	/**
	 * Get standard Yoast SEO post meta fields.
	 *
	 * @param string $post_type Optional post type used to add primary taxonomy fields.
	 * @return array<int,array{name:string,label:string,type:string}>
	 */
	public static function get_yoast_fields( $post_type = '' ) {
		$fields = [
			[
				'name'  => '_yoast_wpseo_title',
				'label' => __( 'SEO Title', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => '_yoast_wpseo_metadesc',
				'label' => __( 'Meta Description', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => '_yoast_wpseo_focuskw',
				'label' => __( 'Focus Keyword', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => '_yoast_wpseo_canonical',
				'label' => __( 'Canonical URL', 'import-export-by-rockstarlab' ),
				'type'  => 'url',
			],
			[
				'name'  => '_yoast_wpseo_meta-robots-noindex',
				'label' => __( 'Meta Robots (Index)', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => '_yoast_wpseo_meta-robots-nofollow',
				'label' => __( 'Meta Robots (Follow)', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => '_yoast_wpseo_opengraph-title',
				'label' => __( 'Social Title', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => '_yoast_wpseo_opengraph-description',
				'label' => __( 'Social Description', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => '_yoast_wpseo_opengraph-image',
				'label' => __( 'Social Image', 'import-export-by-rockstarlab' ),
				'type'  => 'url',
			],
			[
				'name'  => '_yoast_wpseo_twitter-title',
				'label' => __( 'X (Twitter) Title', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => '_yoast_wpseo_twitter-description',
				'label' => __( 'X (Twitter) Description', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => '_yoast_wpseo_twitter-image',
				'label' => __( 'X (Twitter) Image', 'import-export-by-rockstarlab' ),
				'type'  => 'url',
			],
		];

		return array_merge( $fields, self::get_yoast_primary_taxonomy_fields( $post_type ) );
	}

	/**
	 * Get Yoast primary term fields for taxonomies attached to a post type.
	 *
	 * Yoast stores primary terms as _yoast_wpseo_primary_{taxonomy}.
	 *
	 * @param string $post_type Post type.
	 * @return array<int,array{name:string,label:string,type:string}>
	 */
	protected static function get_yoast_primary_taxonomy_fields( $post_type ) {
		$post_type = sanitize_key( $post_type );
		if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
			return [];
		}

		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		if ( empty( $taxonomies ) || ! is_array( $taxonomies ) ) {
			return [];
		}

		$fields = [];
		foreach ( $taxonomies as $taxonomy ) {
			if ( empty( $taxonomy->name ) || empty( $taxonomy->hierarchical ) ) {
				continue;
			}

			$fields[] = [
				'name'  => '_yoast_wpseo_primary_' . sanitize_key( $taxonomy->name ),
				'label' => sprintf(
					/* translators: %s: taxonomy label. */
					__( 'Primary %s', 'import-export-by-rockstarlab' ),
					$taxonomy->labels->singular_name ?? $taxonomy->label
				),
				'type'  => 'number',
			];
		}

		return $fields;
	}

	/**
	 * Check whether Rank Math SEO is active.
	 *
	 * @return bool
	 */
	public static function is_rank_math_active() {
		return defined( 'RANK_MATH_VERSION' ) || defined( 'RANK_MATH_FILE' ) || class_exists( 'RankMath' );
	}

	/**
	 * Get standard Rank Math SEO post meta fields.
	 *
	 * @param string $post_type Optional post type used to add primary taxonomy fields.
	 * @return array<int,array{name:string,label:string,type:string}>
	 */
	public static function get_rank_math_fields( $post_type = '' ) {
		$fields = [
			[
				'name'  => 'rank_math_title',
				'label' => __( 'SEO Title', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => 'rank_math_description',
				'label' => __( 'Meta Description', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => 'rank_math_focus_keyword',
				'label' => __( 'Focus Keyword', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => 'rank_math_canonical_url',
				'label' => __( 'Canonical URL', 'import-export-by-rockstarlab' ),
				'type'  => 'url',
			],
			[
				'name'  => 'rank_math_robots',
				'label' => __( 'Robots Meta', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => 'rank_math_advanced_robots',
				'label' => __( 'Advanced Robots Meta', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => 'rank_math_breadcrumb_title',
				'label' => __( 'Breadcrumb Title', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => 'rank_math_pillar_content',
				'label' => __( 'Pillar Content', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => 'rank_math_schemas',
				'label' => __( 'Schema Markup', 'import-export-by-rockstarlab' ),
				'type'  => 'json',
			],
			[
				'name'  => 'rank_math_facebook_title',
				'label' => __( 'Facebook Title', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => 'rank_math_facebook_description',
				'label' => __( 'Facebook Description', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => 'rank_math_facebook_image',
				'label' => __( 'Facebook Image', 'import-export-by-rockstarlab' ),
				'type'  => 'url',
			],
			[
				'name'  => 'rank_math_facebook_image_id',
				'label' => __( 'Facebook Image ID', 'import-export-by-rockstarlab' ),
				'type'  => 'number',
			],
			[
				'name'  => 'rank_math_facebook_enable_image_overlay',
				'label' => __( 'Enable Facebook Image Overlay', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => 'rank_math_facebook_image_overlay',
				'label' => __( 'Facebook Image Overlay', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => 'rank_math_twitter_card_type',
				'label' => __( 'X (Twitter) Card Type', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => 'rank_math_twitter_title',
				'label' => __( 'X (Twitter) Title', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => 'rank_math_twitter_description',
				'label' => __( 'X (Twitter) Description', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => 'rank_math_twitter_image',
				'label' => __( 'X (Twitter) Image', 'import-export-by-rockstarlab' ),
				'type'  => 'url',
			],
			[
				'name'  => 'rank_math_twitter_image_id',
				'label' => __( 'X (Twitter) Image ID', 'import-export-by-rockstarlab' ),
				'type'  => 'number',
			],
			[
				'name'  => 'rank_math_twitter_use_facebook',
				'label' => __( 'Use Facebook Data for X (Twitter)', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => 'rank_math_twitter_enable_image_overlay',
				'label' => __( 'Enable X (Twitter) Image Overlay', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => 'rank_math_twitter_image_overlay',
				'label' => __( 'X (Twitter) Image Overlay', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
		];

		return array_merge( $fields, self::get_rank_math_primary_taxonomy_fields( $post_type ) );
	}

	/**
	 * Get Rank Math primary term fields for taxonomies attached to a post type.
	 *
	 * Rank Math stores primary terms as rank_math_primary_{taxonomy}.
	 *
	 * @param string $post_type Post type.
	 * @return array<int,array{name:string,label:string,type:string}>
	 */
	protected static function get_rank_math_primary_taxonomy_fields( $post_type ) {
		$post_type = sanitize_key( $post_type );
		if ( '' === $post_type || ! post_type_exists( $post_type ) ) {
			return [];
		}

		$taxonomies = get_object_taxonomies( $post_type, 'objects' );
		if ( empty( $taxonomies ) || ! is_array( $taxonomies ) ) {
			return [];
		}

		$fields = [];
		foreach ( $taxonomies as $taxonomy ) {
			if ( empty( $taxonomy->name ) || empty( $taxonomy->hierarchical ) ) {
				continue;
			}

			$fields[] = [
				'name'  => 'rank_math_primary_' . sanitize_key( $taxonomy->name ),
				'label' => sprintf(
					/* translators: %s: taxonomy label. */
					__( 'Primary %s', 'import-export-by-rockstarlab' ),
					$taxonomy->labels->singular_name ?? $taxonomy->label
				),
				'type'  => 'number',
			];
		}

		return $fields;
	}

	/**
	 * Export Rank Math Schema Builder data as a portable JSON string.
	 *
	 * Rank Math stores schemas as separate postmeta rows named
	 * rank_math_schema_{Type}; there can be more than one row for the same type,
	 * so this cannot be represented safely by get_post_meta( $key, true ).
	 *
	 * @param int $post_id Post ID.
	 * @return string JSON encoded schema list, or empty string when no schemas exist.
	 */
	public static function export_rank_math_schemas( $post_id ) {
		global $wpdb;

		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return '';
		}

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Rank Math stores one schema per postmeta row and duplicate keys must be preserved.
			$wpdb->prepare(
				"SELECT meta_key, meta_value FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s ORDER BY meta_id ASC",
				$post_id,
				$wpdb->esc_like( 'rank_math_schema_' ) . '%'
			),
			ARRAY_A
		);

		if ( empty( $rows ) || ! is_array( $rows ) ) {
			return '';
		}

		$schemas = [];
		foreach ( $rows as $row ) {
			$meta_key = isset( $row['meta_key'] ) ? (string) $row['meta_key'] : '';
			if ( '' === $meta_key || 0 !== strpos( $meta_key, 'rank_math_schema_' ) ) {
				continue;
			}

			$schema = maybe_unserialize( $row['meta_value'] ?? '' );
			if ( ! is_array( $schema ) ) {
				continue;
			}

			$type = self::get_schema_type( $schema, $meta_key );
			if ( '' === $type ) {
				continue;
			}

			$schemas[] = [
				'meta_key' => $meta_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Export payload key mirrors WordPress meta column naming.
				'type'     => $type,
				'data'     => $schema,
			];
		}

		return empty( $schemas ) ? '' : (string) wp_json_encode( $schemas );
	}

	/**
	 * Import Rank Math Schema Builder data from the portable JSON export.
	 *
	 * Existing Rank Math schema rows are replaced so updates do not leave stale
	 * schema duplicates behind. Multiple schemas with the same @type are preserved.
	 *
	 * @param int   $post_id Post ID.
	 * @param mixed $value   JSON string or decoded schema array.
	 * @return int Number of imported schema rows.
	 */
	public static function import_rank_math_schemas( $post_id, $value, $download_media = false ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || '' === $value || null === $value ) {
			return 0;
		}

		$schemas = self::decode_schema_payload( $value );
		if ( empty( $schemas ) ) {
			return 0;
		}

		self::delete_rank_math_schema_meta( $post_id );

		$imported = 0;
		foreach ( $schemas as $entry ) {
			$schema = isset( $entry['data'] ) && is_array( $entry['data'] ) ? $entry['data'] : $entry;
			if ( ! is_array( $schema ) ) {
				continue;
			}

			$type = '';
			if ( isset( $entry['type'] ) ) {
				$type = self::sanitize_schema_type( (string) $entry['type'] );
			}
			if ( '' === $type ) {
				$type = self::get_schema_type( $schema, isset( $entry['meta_key'] ) ? (string) $entry['meta_key'] : '' );
			}
			if ( '' === $type ) {
				continue;
			}

			if ( function_exists( 'wp_kses_post_deep' ) ) {
				$schema = wp_kses_post_deep( $schema );
			}

			if ( $download_media ) {
				$schema = self::prepare_nested_media_for_import( $post_id, $schema );
			}

			$meta_id = add_post_meta( $post_id, 'rank_math_schema_' . $type, $schema );
			if ( $meta_id ) {
				++$imported;
				self::maybe_add_schema_shortcode_meta( $post_id, (int) $meta_id, $schema );
			}
		}

		return $imported;
	}

	/**
	 * Decode schema payload into a list of schema entries.
	 *
	 * @param mixed $value Raw import value.
	 * @return array<int,array<string,mixed>>
	 */
	private static function decode_schema_payload( $value ) {
		if ( is_string( $value ) ) {
			$value = trim( $value );
			if ( '' === $value ) {
				return [];
			}

			if ( is_serialized( $value ) ) {
				$value = maybe_unserialize( $value );
			} else {
				$decoded = json_decode( $value, true );
				if ( JSON_ERROR_NONE === json_last_error() ) {
					$value = $decoded;
				}
			}
		}

		if ( ! is_array( $value ) ) {
			return [];
		}

		if ( isset( $value['data'] ) || isset( $value['@type'] ) ) {
			return [ $value ];
		}

		return array_values( array_filter( $value, 'is_array' ) );
	}

	/**
	 * Delete existing Rank Math schema and shortcode helper meta rows.
	 *
	 * @param int $post_id Post ID.
	 */
	private static function delete_rank_math_schema_meta( $post_id ) {
		global $wpdb;

		$meta_ids = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Need to delete duplicate Rank Math schema rows by meta_id.
			$wpdb->prepare(
				"SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND (meta_key LIKE %s OR meta_key LIKE %s)",
				$post_id,
				$wpdb->esc_like( 'rank_math_schema_' ) . '%',
				$wpdb->esc_like( 'rank_math_shortcode_schema_' ) . '%'
			)
		);

		if ( empty( $meta_ids ) || ! is_array( $meta_ids ) ) {
			return;
		}

		foreach ( $meta_ids as $meta_id ) {
			delete_metadata_by_mid( 'post', absint( $meta_id ) );
		}
	}

	/**
	 * Recreate Rank Math shortcode lookup meta when a schema has shortcode metadata.
	 *
	 * @param int   $post_id Post ID.
	 * @param int   $meta_id Newly added schema meta ID.
	 * @param array $schema  Schema data.
	 */
	private static function maybe_add_schema_shortcode_meta( $post_id, $meta_id, array $schema ) {
		$shortcode = $schema['metadata']['shortcode'] ?? '';
		if ( ! is_string( $shortcode ) || '' === $shortcode ) {
			return;
		}

		$shortcode = preg_replace( '/[^A-Za-z0-9_-]/', '', $shortcode );
		if ( ! is_string( $shortcode ) || '' === $shortcode ) {
			return;
		}

		add_post_meta( $post_id, 'rank_math_shortcode_schema_' . $shortcode, $meta_id );
	}

	/**
	 * Resolve and sanitize Rank Math schema type.
	 *
	 * @param array  $schema   Schema data.
	 * @param string $meta_key Fallback meta key.
	 * @return string
	 */
	private static function get_schema_type( array $schema, $meta_key = '' ) {
		$type = $schema['@type'] ?? '';
		if ( is_array( $type ) ) {
			$type = reset( $type );
		}
		if ( '' === $type && 0 === strpos( (string) $meta_key, 'rank_math_schema_' ) ) {
			$type = substr( (string) $meta_key, strlen( 'rank_math_schema_' ) );
		}

		return self::sanitize_schema_type( (string) $type );
	}

	/**
	 * Allow schema types in the same safe shape Rank Math uses for meta keys.
	 *
	 * @param string $type Raw schema type.
	 * @return string
	 */
	private static function sanitize_schema_type( $type ) {
		$type = preg_replace( '/[^A-Za-z0-9_-]/', '', $type );
		return is_string( $type ) ? $type : '';
	}

	/**
	 * Import media URLs inside nested Rank Math schema data.
	 *
	 * @param int   $post_id Post ID.
	 * @param mixed $value   Schema value.
	 * @return mixed
	 */
	private static function prepare_nested_media_for_import( $post_id, $value ) {
		if ( is_string( $value ) ) {
			$trimmed = trim( $value );
			if ( self::looks_like_media_url( $trimmed ) ) {
				$attachment_id = self::import_media_from_url( $trimmed, $post_id );
				if ( $attachment_id > 0 ) {
					$url = wp_get_attachment_url( $attachment_id );
					return $url ? $url : $value;
				}
			}
			return $value;
		}

		if ( ! is_array( $value ) ) {
			return $value;
		}

		if ( isset( $value['url'] ) && is_string( $value['url'] ) && self::looks_like_media_url( $value['url'] ) ) {
			$attachment_id = self::import_media_from_url( $value['url'], $post_id );
			if ( $attachment_id > 0 ) {
				$url = wp_get_attachment_url( $attachment_id );
				if ( $url ) {
					$value['url'] = $url;
				}
				if ( isset( $value['id'] ) ) {
					$value['id'] = $attachment_id;
				}
			}
		}

		foreach ( $value as $key => $child ) {
			$value[ $key ] = self::prepare_nested_media_for_import( $post_id, $child );
		}

		return $value;
	}

	/**
	 * Import a media URL, reusing an existing attachment when possible.
	 *
	 * @param string $url     Media URL.
	 * @param int    $post_id Parent post ID.
	 * @return int Attachment ID or 0.
	 */
	private static function import_media_from_url( $url, $post_id ) {
		$url = esc_url_raw( (string) $url );
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return 0;
		}

		$existing_id = attachment_url_to_postid( $url );
		if ( $existing_id > 0 ) {
			return (int) $existing_id;
		}

		$existing_by_source = self::find_attachment_by_source_url( $url );
		if ( $existing_by_source > 0 ) {
			return $existing_by_source;
		}

		$filename = wp_basename( (string) wp_parse_url( $url, PHP_URL_PATH ) );
		if ( '' !== $filename ) {
			$existing_by_name = self::find_attachment_by_filename( $filename );
			if ( $existing_by_name > 0 ) {
				return $existing_by_name;
			}
		}

		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$tmp = self::download_media_url_to_temp_file( $url );
		if ( is_wp_error( $tmp ) ) {
			return 0;
		}

		$file_array = [
			'name'     => '' !== $filename ? $filename : wp_basename( $tmp ),
			'tmp_name' => $tmp,
		];

		$attachment_id = media_handle_sideload( $file_array, $post_id );
		if ( is_wp_error( $attachment_id ) ) {
			wp_delete_file( $tmp );
			return 0;
		}

		update_post_meta( (int) $attachment_id, 'rsl_ie_source_url', $url );
		update_post_meta( (int) $attachment_id, 'rsl_ie_source_url_hash', md5( $url ) );

		if ( class_exists( '\RockStarLab\ImportExport\Helper\Media_Hash' ) ) {
			Media_Hash::get_or_create_hash( (int) $attachment_id );
		}

		return (int) $attachment_id;
	}

	/**
	 * Download a media URL to a temporary file.
	 *
	 * @param string $url Media URL.
	 * @return string|\WP_Error Temporary filename or error.
	 */
	private static function download_media_url_to_temp_file( $url ) {
		$tmp = download_url( $url );
		if ( ! is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$response = wp_remote_get(
			$url,
			[
				'timeout'            => 30,
				'redirection'        => 5,
				'reject_unsafe_urls' => false,
			]
		);
		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );
		if ( $code < 200 || $code >= 300 || '' === $body ) {
			return new \WP_Error( 'rsl_ie_media_download_failed', __( 'Could not download media URL.', 'import-export-by-rockstarlab' ) );
		}

		$tmp = wp_tempnam( $url );
		if ( ! $tmp ) {
			return new \WP_Error( 'rsl_ie_media_temp_failed', __( 'Could not create temporary media file.', 'import-export-by-rockstarlab' ) );
		}

		if ( false === file_put_contents( $tmp, $body ) ) {
			wp_delete_file( $tmp );
			return new \WP_Error( 'rsl_ie_media_temp_write_failed', __( 'Could not write temporary media file.', 'import-export-by-rockstarlab' ) );
		}

		return $tmp;
	}

	/**
	 * Find an existing attachment by filename.
	 *
	 * @param string $filename Filename.
	 * @return int
	 */
	private static function find_attachment_by_filename( $filename ) {
		global $wpdb;

		$filename = sanitize_file_name( $filename );
		if ( '' === $filename ) {
			return 0;
		}

		$attachment_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching,WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Attachment lookup by stored file path is needed for media reuse.
			$wpdb->prepare(
				"SELECT post_id
				 FROM {$wpdb->postmeta}
				 WHERE meta_key = '_wp_attached_file'
				   AND (meta_value = %s OR meta_value LIKE %s)
				 LIMIT 1",
				$filename,
				'%/' . $wpdb->esc_like( $filename )
			)
		);

		return $attachment_id ? absint( $attachment_id ) : 0;
	}

	/**
	 * Find an attachment previously imported from the same source URL.
	 *
	 * @param string $url Source URL.
	 * @return int
	 */
	private static function find_attachment_by_source_url( $url ) {
		global $wpdb;

		$hash          = md5( esc_url_raw( (string) $url ) );
		$attachment_id = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching -- Exact source URL hash lookup avoids duplicate sideloads.
			$wpdb->prepare(
				"SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = 'rsl_ie_source_url_hash' AND meta_value = %s LIMIT 1",
				$hash
			)
		);

		return $attachment_id ? absint( $attachment_id ) : 0;
	}

	/**
	 * Determine whether a string looks like an importable media URL.
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	private static function looks_like_media_url( $url ) {
		if ( '' === $url || ! wp_http_validate_url( $url ) ) {
			return false;
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$ext  = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );

		return in_array( $ext, [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif', 'pdf', 'mp4', 'webm' ], true );
	}
}

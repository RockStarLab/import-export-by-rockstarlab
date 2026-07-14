<?php
/**
 * Elementor field helpers.
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

class Elementor_Fields {

	/**
	 * Portable Elementor document aggregate field.
	 */
	const DOCUMENT_FIELD = 'elementor_document';

	/**
	 * Elementor post meta keys that should travel with a document.
	 *
	 * _elementor_css is intentionally excluded because it is generated cache.
	 *
	 * @var array<int,string>
	 */
	private static $document_meta_keys = [
		'_elementor_data',
		'_elementor_edit_mode',
		'_elementor_template_type',
		'_elementor_version',
		'_elementor_pro_version',
		'_elementor_page_settings',
		'_elementor_controls_usage',
		'_elementor_page_assets',
		'_wp_page_template',
	];

	/**
	 * Check whether Elementor is active.
	 *
	 * @return bool
	 */
	public static function is_active() {
		if ( did_action( 'elementor/loaded' ) && defined( 'ELEMENTOR_VERSION' ) ) {
			return true;
		}

		if ( ! function_exists( 'is_plugin_active' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		return is_plugin_active( 'elementor/elementor.php' ) || ( is_multisite() && is_plugin_active_for_network( 'elementor/elementor.php' ) );
	}

	/**
	 * Get Elementor fields exposed in the UI.
	 *
	 * @return array<int,array{name:string,label:string,type:string}>
	 */
	public static function get_fields() {
		return [
			[
				'name'  => self::DOCUMENT_FIELD,
				'label' => __( 'Elementor Document Data', 'import-export-by-rockstarlab' ),
				'type'  => 'json',
			],
			[
				'name'  => '_elementor_data',
				'label' => __( 'Elementor Builder Data', 'import-export-by-rockstarlab' ),
				'type'  => 'json',
			],
			[
				'name'  => '_elementor_page_settings',
				'label' => __( 'Elementor Page Settings', 'import-export-by-rockstarlab' ),
				'type'  => 'json',
			],
			[
				'name'  => '_elementor_template_type',
				'label' => __( 'Elementor Template Type', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => '_elementor_edit_mode',
				'label' => __( 'Elementor Edit Mode', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => '_elementor_version',
				'label' => __( 'Elementor Version', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => '_elementor_pro_version',
				'label' => __( 'Elementor Pro Version', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
			[
				'name'  => '_elementor_migrations_state_d2a1',
				'label' => __( 'Elementor Migrations State', 'import-export-by-rockstarlab' ),
				'type'  => 'json',
			],
			[
				'name'  => '_elementor_global_class_usage_indexed',
				'label' => __( 'Elementor Global Class Usage Index', 'import-export-by-rockstarlab' ),
				'type'  => 'json',
			],
			[
				'name'  => '_elementor_global_class_usage_indexed_preview',
				'label' => __( 'Elementor Global Class Usage Preview Index', 'import-export-by-rockstarlab' ),
				'type'  => 'json',
			],
			[
				'name'  => '_elementor_used_global_class',
				'label' => __( 'Elementor Used Global Classes', 'import-export-by-rockstarlab' ),
				'type'  => 'json',
			],
			[
				'name'  => '_elementor_used_global_class_preview',
				'label' => __( 'Elementor Used Global Classes Preview', 'import-export-by-rockstarlab' ),
				'type'  => 'json',
			],
			[
				'name'  => '_wp_page_template',
				'label' => __( 'Page Template', 'import-export-by-rockstarlab' ),
				'type'  => 'string',
			],
		];
	}

	/**
	 * Check whether a meta key belongs to a portable Elementor document.
	 *
	 * @param string $key Meta key.
	 * @return bool
	 */
	public static function is_elementor_meta_key( $key ) {
		return in_array( (string) $key, self::$document_meta_keys, true ) || 0 === strpos( (string) $key, '_elementor_' );
	}

	/**
	 * Check whether a meta key should be excluded from export/import.
	 *
	 * @param string $key Meta key.
	 * @return bool
	 */
	public static function is_generated_cache_key( $key ) {
		return in_array(
			(string) $key,
			[
				'_elementor_css',
				'_elementor_element_cache',
				'_elementor_page_assets',
				'_elementor_controls_usage',
			],
			true
		);
	}

	/**
	 * Export Elementor document meta as a portable JSON packet.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function export_document( $post_id ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 ) {
			return '';
		}

		$meta = [];
		foreach ( self::$document_meta_keys as $key ) {
			$value = get_post_meta( $post_id, $key, true );
			if ( '' === $value || null === $value || false === $value ) {
				continue;
			}

			$meta[ $key ] = $value;
		}

		if ( empty( $meta ) ) {
			return '';
		}

		return (string) wp_json_encode(
			[
				'version'           => 1,
				'elementor_version' => defined( 'ELEMENTOR_VERSION' ) ? ELEMENTOR_VERSION : '',
				'meta'              => $meta,
			]
		);
	}

	/**
	 * Import a portable Elementor document packet.
	 *
	 * @param int   $post_id Post ID.
	 * @param mixed $value   JSON string or decoded packet.
	 * @return bool
	 */
	public static function import_document( $post_id, $value, $download_media = true ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || '' === $value || null === $value ) {
			return false;
		}

		$packet = self::decode_packet( $value );
		if ( empty( $packet ) ) {
			return false;
		}

		$meta = [];
		if ( isset( $packet['meta'] ) && is_array( $packet['meta'] ) ) {
			$meta = $packet['meta'];
		} elseif ( isset( $packet['_elementor_data'] ) || isset( $packet['_elementor_page_settings'] ) ) {
			$meta = $packet;
		}

		if ( empty( $meta ) ) {
			return false;
		}

		foreach ( $meta as $key => $meta_value ) {
			$key = (string) $key;
			if ( ! self::is_elementor_meta_key( $key ) || self::is_generated_cache_key( $key ) ) {
				continue;
			}

			self::import_meta_value( $post_id, $key, $meta_value, false, $download_media );
		}

		self::after_import( $post_id );
		return true;
	}

	/**
	 * Import a single Elementor meta value.
	 *
	 * @param int    $post_id     Post ID.
	 * @param string $key         Meta key.
	 * @param mixed  $value       Meta value.
	 * @param bool   $clear_cache Whether to clear Elementor cache after import.
	 * @return bool
	 */
	public static function import_meta_value( $post_id, $key, $value, $clear_cache = true, $download_media = true ) {
		$post_id = absint( $post_id );
		$key     = (string) $key;
		if ( $post_id <= 0 || '' === $key || self::is_generated_cache_key( $key ) ) {
			return false;
		}

		if ( '_elementor_data' === $key ) {
			$value = self::prepare_elementor_data_for_import( $post_id, $value, $download_media );
		} elseif ( '_elementor_page_settings' === $key && $download_media ) {
			$value = self::prepare_nested_value_for_import( $post_id, $value );
		}

		if ( '_elementor_data' === $key && is_string( $value ) ) {
			$value = wp_slash( $value );
		}

		update_post_meta( $post_id, $key, $value );

		if ( $clear_cache ) {
			self::after_import( $post_id );
		}

		return true;
	}

	/**
	 * Decode a JSON/serialized packet.
	 *
	 * @param mixed $value Raw value.
	 * @return array<string,mixed>
	 */
	private static function decode_packet( $value ) {
		if ( is_string( $value ) ) {
			$value = trim( $value );
			if ( '' === $value ) {
				return [];
			}

			if ( is_serialized( $value ) ) {
				$value = maybe_unserialize( $value );
			} elseif ( '{' === $value[0] || '[' === $value[0] ) {
				$decoded = json_decode( $value, true );
				if ( JSON_ERROR_NONE === json_last_error() ) {
					$value = $decoded;
				}
			}
		}

		return is_array( $value ) ? $value : [];
	}

	/**
	 * Prepare _elementor_data for the target site.
	 *
	 * @param int   $post_id Post ID.
	 * @param mixed $value   Elementor data.
	 * @return string
	 */
	private static function prepare_elementor_data_for_import( $post_id, $value, $download_media = true ) {
		$data = self::decode_packet( $value );
		if ( empty( $data ) ) {
			return is_string( $value ) ? $value : (string) wp_json_encode( $value );
		}

		if ( $download_media ) {
			$data = self::prepare_nested_value_for_import( $post_id, $data );
		}

		return (string) wp_json_encode( $data );
	}

	/**
	 * Prepare nested Elementor arrays by importing media URLs and remapping IDs.
	 *
	 * @param int   $post_id Post ID.
	 * @param mixed $value   Value.
	 * @return mixed
	 */
	private static function prepare_nested_value_for_import( $post_id, $value ) {
		if ( is_string( $value ) ) {
			$trimmed = trim( $value );
			if ( self::is_local_wp_asset_url( $trimmed ) ) {
				return self::replace_local_url_host( $trimmed );
			}
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
			if ( self::is_local_wp_asset_url( $value['url'] ) ) {
				$value['url'] = self::replace_local_url_host( $value['url'] );
				return $value;
			}
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

		if (
			isset( $value['id'], $value['url'] ) &&
			is_array( $value['url'] ) &&
			isset( $value['url']['value'] ) &&
			is_string( $value['url']['value'] ) &&
			self::looks_like_media_url( $value['url']['value'] )
		) {
			if ( self::is_local_wp_asset_url( $value['url']['value'] ) ) {
				$value['url']['value'] = self::replace_local_url_host( $value['url']['value'] );
				return $value;
			}
			$attachment_id = self::import_media_from_url( $value['url']['value'], $post_id );
			if ( $attachment_id > 0 ) {
				$url = wp_get_attachment_url( $attachment_id );
				if ( $url ) {
					$value['url']['value'] = $url;
				}
				$value['id'] = $attachment_id;
			}
		}

		foreach ( $value as $key => $child ) {
			$value[ $key ] = self::prepare_nested_value_for_import( $post_id, $child );
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
		if ( '' === $url || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
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
	 * WordPress' download_url() uses safe HTTP validation and can reject local
	 * development hosts such as *.local even when wp_remote_get() can fetch them.
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
		if ( '' === $url || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return false;
		}

		$path = (string) wp_parse_url( $url, PHP_URL_PATH );
		$ext  = strtolower( (string) pathinfo( $path, PATHINFO_EXTENSION ) );

		return in_array( $ext, [ 'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'avif', 'pdf', 'mp4', 'webm' ], true );
	}

	private static function is_local_wp_asset_url( $url ) {
		$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
		return false !== strpos( $path, '/wp-content/plugins/' ) || false !== strpos( $path, '/wp-content/themes/' );
	}

	private static function replace_local_url_host( $url ) {
		$path = (string) wp_parse_url( (string) $url, PHP_URL_PATH );
		if ( '' === $path ) {
			return $url;
		}
		return home_url( $path );
	}

	/**
	 * Clear generated Elementor CSS/cache after import.
	 *
	 * @param int $post_id Post ID.
	 */
	private static function after_import( $post_id ) {
		delete_post_meta( $post_id, '_elementor_css' );
		clean_post_cache( $post_id );

		if ( class_exists( '\Elementor\Plugin' ) && isset( \Elementor\Plugin::$instance->files_manager ) ) {
			\Elementor\Plugin::$instance->files_manager->clear_cache();
		}
	}
}

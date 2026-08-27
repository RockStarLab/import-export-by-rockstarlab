<?php
/**
 * WPML compatibility helpers.
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Small wrapper around WPML's public hooks.
 */
class WPML_Compatibility {

	/**
	 * Whether WPML is active enough for language operations.
	 *
	 * @return bool
	 */
	public static function is_active() {
		return defined( 'ICL_SITEPRESS_VERSION' ) || function_exists( 'icl_object_id' ) || has_filter( 'wpml_element_language_details' ) || has_action( 'wpml_set_element_language_details' );
	}

	/**
	 * Whether WPML Media Translation is active.
	 *
	 * @return bool
	 */
	public static function is_media_active() {
		return defined( 'WPML_MEDIA_VERSION' ) || class_exists( '\WPML_Media' );
	}

	/**
	 * Get WPML element type for a post object.
	 *
	 * @param string $post_type Post type.
	 * @return string
	 */
	public static function get_post_element_type( $post_type ) {
		$post_type = sanitize_key( (string) $post_type );

		return apply_filters( 'wpml_element_type', 'post_' . $post_type );
	}

	/**
	 * Get WPML element type for a taxonomy term.
	 *
	 * @param string $taxonomy Taxonomy.
	 * @return string
	 */
	public static function get_term_element_type( $taxonomy ) {
		$taxonomy = sanitize_key( (string) $taxonomy );

		return apply_filters( 'wpml_element_type', 'tax_' . $taxonomy );
	}

	/**
	 * Export portable WPML post language/translation data.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type.
	 * @return array<string,mixed>
	 */
	public static function export_post_data( $post_id, $post_type ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || ! self::is_active() ) {
			return [];
		}

		$element_type = self::get_post_element_type( $post_type );
		$details      = apply_filters(
			'wpml_element_language_details',
			null,
			[
				'element_id'   => $post_id,
				'element_type' => $element_type,
			]
		);

		if ( empty( $details ) || empty( $details->language_code ) ) {
			return [];
		}

		$trid         = (int) ( $details->trid ?? 0 );
		$translations = [];
		if ( $trid > 0 ) {
			$wpml_translations = apply_filters( 'wpml_get_element_translations', [], $trid, $element_type );
			if ( is_array( $wpml_translations ) ) {
				foreach ( $wpml_translations as $language_code => $translation ) {
					if ( empty( $translation->element_id ) ) {
						continue;
					}

					$translation_post                        = get_post( (int) $translation->element_id );
					$translations[ (string) $language_code ] = [
						'source_id' => (int) $translation->element_id,
						'slug'      => $translation_post ? (string) $translation_post->post_name : '',
						'title'     => $translation_post ? (string) $translation_post->post_title : '',
					];
				}
			}
		}

		return [
			'language_code'        => (string) $details->language_code,
			'source_language_code' => isset( $details->source_language_code ) ? (string) $details->source_language_code : '',
			'translation_group'    => $trid,
			'translation_role'     => empty( $details->source_language_code ) ? 'source' : 'translation',
			'translations'         => $translations,
		];
	}

	/**
	 * Get a post/attachment language code for filtering.
	 *
	 * @param int    $post_id   Post ID.
	 * @param string $post_type Post type.
	 * @return string
	 */
	public static function get_post_language_code( $post_id, $post_type ) {
		$data = self::export_post_data( $post_id, $post_type );

		return isset( $data['language_code'] ) ? sanitize_key( (string) $data['language_code'] ) : '';
	}

	/**
	 * Export portable WPML term language/translation data.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy.
	 * @return array<string,mixed>
	 */
	public static function export_term_data( $term_id, $taxonomy ) {
		$term_id = absint( $term_id );
		if ( $term_id <= 0 || ! self::is_active() ) {
			return [];
		}

		$element_type = self::get_term_element_type( $taxonomy );
		$details      = apply_filters(
			'wpml_element_language_details',
			null,
			[
				'element_id'   => $term_id,
				'element_type' => $element_type,
			]
		);

		if ( empty( $details ) || empty( $details->language_code ) ) {
			return [];
		}

		$trid         = (int) ( $details->trid ?? 0 );
		$translations = [];
		if ( $trid > 0 ) {
			$wpml_translations = apply_filters( 'wpml_get_element_translations', [], $trid, $element_type );
			if ( is_array( $wpml_translations ) ) {
				foreach ( $wpml_translations as $language_code => $translation ) {
					if ( empty( $translation->element_id ) ) {
						continue;
					}

					$translation_term                        = get_term( (int) $translation->element_id, $taxonomy );
					$translations[ (string) $language_code ] = [
						'source_id' => (int) $translation->element_id,
						'slug'      => $translation_term && ! is_wp_error( $translation_term ) ? (string) $translation_term->slug : '',
						'name'      => $translation_term && ! is_wp_error( $translation_term ) ? (string) $translation_term->name : '',
					];
				}
			}
		}

		return [
			'language_code'        => (string) $details->language_code,
			'source_language_code' => isset( $details->source_language_code ) ? (string) $details->source_language_code : '',
			'translation_group'    => $trid,
			'translation_role'     => empty( $details->source_language_code ) ? 'source' : 'translation',
			'translations'         => $translations,
		];
	}

	/**
	 * Get a taxonomy term language code for filtering.
	 *
	 * @param int    $term_id  Term ID.
	 * @param string $taxonomy Taxonomy.
	 * @return string
	 */
	public static function get_term_language_code( $term_id, $taxonomy ) {
		$data = self::export_term_data( $term_id, $taxonomy );

		return isset( $data['language_code'] ) ? sanitize_key( (string) $data['language_code'] ) : '';
	}

	/**
	 * Apply WPML language details to an imported post.
	 *
	 * @param int   $post_id     Target post ID.
	 * @param array $wpml_data   Prepared WPML data.
	 * @param array $source_map  Source post ID => target post ID map.
	 * @return void
	 */
	public static function apply_post_language_details( $post_id, array $wpml_data, array $source_map = [] ) {
		$post_id = absint( $post_id );
		if ( $post_id <= 0 || ! self::is_active() || empty( $wpml_data['language_code'] ) ) {
			return;
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return;
		}

		$element_type         = self::get_post_element_type( $post->post_type );
		$translation_group_id = isset( $wpml_data['translation_group'] ) ? absint( $wpml_data['translation_group'] ) : 0;
		$trid                 = self::resolve_target_trid( $wpml_data, $source_map, $element_type );

		// If this is the source language item, let WPML create or reuse its own target-site trid.
		if ( $trid <= 0 && empty( $wpml_data['source_language_code'] ) ) {
			$existing_trid = apply_filters( 'wpml_element_trid', null, $post_id, $element_type );
			$trid          = $existing_trid ? absint( $existing_trid ) : 0;
		}

		do_action(
			'wpml_set_element_language_details',
			[
				'element_id'           => $post_id,
				'element_type'         => $element_type,
				'trid'                 => $trid > 0 ? $trid : false,
				'language_code'        => sanitize_key( (string) $wpml_data['language_code'] ),
				'source_language_code' => ! empty( $wpml_data['source_language_code'] ) ? sanitize_key( (string) $wpml_data['source_language_code'] ) : null,
			]
		);

		if ( $translation_group_id > 0 ) {
			update_post_meta( $post_id, '_rsl_ie_wpml_source_trid', (string) $translation_group_id );
		}
	}

	/**
	 * Apply the parent post language to an imported attachment.
	 *
	 * WPML filters media in the admin by language. If sideloaded attachments keep
	 * the current admin/default language instead of the imported post language,
	 * ACF image/gallery fields can store valid local IDs but still display
	 * "items not found" in translated posts.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $parent_post_id Parent/imported post ID.
	 * @return void
	 */
	public static function apply_attachment_language_from_parent( $attachment_id, $parent_post_id ) {
		$attachment_id  = absint( $attachment_id );
		$parent_post_id = absint( $parent_post_id );
		if ( $attachment_id <= 0 || $parent_post_id <= 0 || ! self::is_active() ) {
			return;
		}

		$parent = get_post( $parent_post_id );
		if ( ! $parent ) {
			return;
		}

		$parent_element_type = self::get_post_element_type( $parent->post_type );
		$parent_details      = apply_filters(
			'wpml_element_language_details',
			null,
			[
				'element_id'   => $parent_post_id,
				'element_type' => $parent_element_type,
			]
		);

		if ( empty( $parent_details ) || empty( $parent_details->language_code ) ) {
			return;
		}

		$attachment_element_type = self::get_post_element_type( 'attachment' );
		$language_code           = sanitize_key( (string) $parent_details->language_code );
		$attachment_details      = apply_filters(
			'wpml_element_language_details',
			null,
			[
				'element_id'   => $attachment_id,
				'element_type' => $attachment_element_type,
			]
		);

		if ( ! empty( $attachment_details ) && ! empty( $attachment_details->language_code ) && $language_code === sanitize_key( (string) $attachment_details->language_code ) ) {
			return;
		}

		self::remove_element_language_row( $attachment_id, $attachment_element_type );

		do_action(
			'wpml_set_element_language_details',
			[
				'element_id'           => $attachment_id,
				'element_type'         => $attachment_element_type,
				'trid'                 => false,
				'language_code'        => $language_code,
				'source_language_code' => null,
			]
		);
	}

	/**
	 * Apply a specific WPML language to an imported attachment.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $language_code WPML language code.
	 * @return void
	 */
	public static function apply_attachment_language( $attachment_id, $language_code ) {
		$attachment_id = absint( $attachment_id );
		$language_code = sanitize_key( (string) $language_code );
		if ( $attachment_id <= 0 || '' === $language_code || ! self::is_active() ) {
			return;
		}

		$attachment_element_type = self::get_post_element_type( 'attachment' );
		$attachment_details      = apply_filters(
			'wpml_element_language_details',
			null,
			[
				'element_id'   => $attachment_id,
				'element_type' => $attachment_element_type,
			]
		);

		if ( ! empty( $attachment_details ) && ! empty( $attachment_details->language_code ) && $language_code === sanitize_key( (string) $attachment_details->language_code ) ) {
			return;
		}

		self::remove_element_language_row( $attachment_id, $attachment_element_type );

		do_action(
			'wpml_set_element_language_details',
			[
				'element_id'           => $attachment_id,
				'element_type'         => $attachment_element_type,
				'trid'                 => false,
				'language_code'        => $language_code,
				'source_language_code' => null,
			]
		);
	}

	/**
	 * Remove an existing WPML language row for an element before reassignment.
	 *
	 * @param int    $element_id   Element ID.
	 * @param string $element_type WPML element type.
	 * @return void
	 */
	private static function remove_element_language_row( $element_id, $element_type ) {
		global $wpdb;

		$element_id   = absint( $element_id );
		$element_type = sanitize_text_field( (string) $element_type );
		if ( $element_id <= 0 || '' === $element_type ) {
			return;
		}

		$table = $wpdb->prefix . 'icl_translations';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table ) ) !== $table ) {
			return;
		}

		$wpdb->delete(
			$table,
			[
				'element_id'   => $element_id,
				'element_type' => $element_type,
			],
			[
				'%d',
				'%s',
			]
		);
	}

	/**
	 * Apply WPML language details to an imported term.
	 *
	 * @param int    $term_id    Target term ID.
	 * @param string $taxonomy   Taxonomy.
	 * @param array  $wpml_data  Prepared WPML data.
	 * @param array  $source_map Source term ID => target term ID map.
	 * @return void
	 */
	public static function apply_term_language_details( $term_id, $taxonomy, array $wpml_data, array $source_map = [] ) {
		$term_id  = absint( $term_id );
		$taxonomy = sanitize_key( (string) $taxonomy );
		if ( $term_id <= 0 || '' === $taxonomy || ! self::is_active() || empty( $wpml_data['language_code'] ) ) {
			return;
		}

		$term = get_term( $term_id, $taxonomy );
		if ( ! $term || is_wp_error( $term ) ) {
			return;
		}

		$element_type = self::get_term_element_type( $taxonomy );
		$trid         = self::resolve_target_trid( $wpml_data, $source_map, $element_type );

		if ( $trid <= 0 && empty( $wpml_data['source_language_code'] ) ) {
			$existing_trid = apply_filters( 'wpml_element_trid', null, $term_id, $element_type );
			$trid          = $existing_trid ? absint( $existing_trid ) : 0;
		}

		do_action(
			'wpml_set_element_language_details',
			[
				'element_id'           => $term_id,
				'element_type'         => $element_type,
				'trid'                 => $trid > 0 ? $trid : false,
				'language_code'        => sanitize_key( (string) $wpml_data['language_code'] ),
				'source_language_code' => ! empty( $wpml_data['source_language_code'] ) ? sanitize_key( (string) $wpml_data['source_language_code'] ) : null,
			]
		);

		if ( ! empty( $wpml_data['translation_group'] ) ) {
			update_term_meta( $term_id, '_rsl_ie_wpml_source_trid', (string) absint( $wpml_data['translation_group'] ) );
		}
	}

	/**
	 * Resolve target-site trid from an already imported sibling.
	 *
	 * @param array  $wpml_data    WPML data.
	 * @param array  $source_map   Source ID map.
	 * @param string $element_type WPML element type.
	 * @return int
	 */
	private static function resolve_target_trid( array $wpml_data, array $source_map, $element_type ) {
		$translations = isset( $wpml_data['translations'] ) && is_array( $wpml_data['translations'] ) ? $wpml_data['translations'] : [];
		foreach ( $translations as $translation ) {
			$source_id = is_array( $translation ) ? absint( $translation['source_id'] ?? 0 ) : 0;
			if ( $source_id <= 0 || empty( $source_map[ (string) $source_id ] ) ) {
				continue;
			}

			$target_id = absint( $source_map[ (string) $source_id ] );
			if ( $target_id <= 0 ) {
				continue;
			}

			$trid = apply_filters( 'wpml_element_trid', null, $target_id, $element_type );
			if ( $trid ) {
				return absint( $trid );
			}
		}

		return 0;
	}
}

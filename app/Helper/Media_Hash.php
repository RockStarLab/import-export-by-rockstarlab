<?php
/**
 * Media Hash Helper
 *
 * Automatically adds MD5 hash to all media uploads for duplicate detection
 *
 * @package RockStarLab\ImportExport\Helper
 */

namespace RockStarLab\ImportExport\Helper;

defined( 'ABSPATH' ) || exit;

/**
 * Maintains and queries the shared Media Library file-hash index.
 */
class Media_Hash {
	/** Canonical attachment meta key used by all import and sync flows. */
	public const META_KEY = 'rsl_ie_file_hash';

	/** Legacy key used by older Content Sync versions. */
	private const LEGACY_META_KEY = '_rsl_ie_file_hash';

	/** Legacy key used by the AI URL importer. */
	private const LEGACY_IMAGE_META_KEY = '_rsl_ie_image_hash';

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	public static function init() {
		// Hook into media upload process.
		add_filter( 'wp_generate_attachment_metadata', [ __CLASS__, 'add_hash_to_attachment' ], 10, 2 );

		// Fallback hook for non-image files that don't generate metadata.
		add_action( 'add_attachment', [ __CLASS__, 'add_hash_to_attachment_fallback' ] );

		// Hook for when file is replaced/updated.
		add_action( 'delete_attachment', [ __CLASS__, 'cleanup_hash_meta' ] );
	}

	/**
	 * Add MD5 hash to attachment metadata during upload
	 *
	 * This is called for images and other files that generate metadata
	 *
	 * @param array $metadata      Attachment metadata.
	 * @param int   $attachment_id Attachment ID.
	 * @return array Modified metadata.
	 */
	public static function add_hash_to_attachment( $metadata, $attachment_id ) {
		$file_path = get_attached_file( $attachment_id );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return $metadata;
		}

		// Calculate and store MD5 hash.
		self::calculate_and_store( $attachment_id, $file_path );

		return $metadata;
	}

	/**
	 * Fallback for non-image attachments
	 *
	 * Some file types don't trigger wp_generate_attachment_metadata,
	 * so we need this fallback
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public static function add_hash_to_attachment_fallback( $attachment_id ) {
		// Check if hash already exists (from main hook).
		$existing_hash = get_post_meta( $attachment_id, self::META_KEY, true );
		if ( ! empty( $existing_hash ) ) {
			return; // Already processed.
		}

		$file_path = get_attached_file( $attachment_id );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return;
		}

		// Calculate and store MD5 hash.
		self::calculate_and_store( $attachment_id, $file_path );
	}

	/**
	 * Clean up hash meta when attachment is deleted
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public static function cleanup_hash_meta( $attachment_id ) {
		delete_post_meta( $attachment_id, self::META_KEY );
		delete_post_meta( $attachment_id, self::LEGACY_META_KEY );
		delete_post_meta( $attachment_id, self::LEGACY_IMAGE_META_KEY );
		delete_post_meta( $attachment_id, 'rsl_ie_file_size' );
		delete_post_meta( $attachment_id, 'rsl_ie_hash_added' );
	}

	/**
	 * Get attachment by hash
	 *
	 * @param string $hash           MD5 hash to search for.
	 * @param bool   $scan_unindexed Whether to scan files missing from the index.
	 * @return int|false Attachment ID or false if not found.
	 */
	public static function get_attachment_by_hash( $hash, $scan_unindexed = false ) {
		$hash = strtolower( trim( (string) $hash ) );
		if ( ! preg_match( '/^[a-f0-9]{32}$/', $hash ) ) {
			return false;
		}

		$args = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.
				'relation' => 'OR',
				[
					'key'   => self::META_KEY,
					'value' => $hash,
				],
				[
					'key'   => self::LEGACY_META_KEY,
					'value' => $hash,
				],
				[
					'key'   => self::LEGACY_IMAGE_META_KEY,
					'value' => $hash,
				],
			],
			'fields'         => 'ids',
			'posts_per_page' => 1,
		];

		$query = new \WP_Query( $args );
		if ( $query->have_posts() ) {
			$attachment_id = (int) $query->posts[0];
			self::store_attachment_hash( $attachment_id, $hash );
			return $attachment_id;
		}

		if ( $scan_unindexed ) {
			$attachment_ids = get_posts(
				[
					'post_type'      => 'attachment',
					'post_status'    => 'inherit',
					'posts_per_page' => -1,
					'fields'         => 'ids',
				]
			);
			foreach ( $attachment_ids as $attachment_id ) {
				$file_path = get_attached_file( $attachment_id );
				if ( $file_path && file_exists( $file_path ) && md5_file( $file_path ) === $hash ) {
					self::store_attachment_hash( $attachment_id, $hash, $file_path );
					return (int) $attachment_id;
				}
			}
		}

		return false;
	}

	/**
	 * Store a known MD5 hash and related file metadata for an attachment.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $hash          MD5 hash.
	 * @param string $file_path     Optional absolute file path.
	 * @return bool Whether the hash was stored.
	 */
	public static function store_attachment_hash( $attachment_id, $hash, $file_path = '' ) {
		$attachment_id = absint( $attachment_id );
		$hash          = strtolower( trim( (string) $hash ) );
		if ( ! $attachment_id || ! preg_match( '/^[a-f0-9]{32}$/', $hash ) ) {
			return false;
		}

		update_post_meta( $attachment_id, self::META_KEY, $hash );
		if ( '' === $file_path ) {
			$file_path = get_attached_file( $attachment_id );
		}
		if ( $file_path && file_exists( $file_path ) ) {
			update_post_meta( $attachment_id, 'rsl_ie_file_size', filesize( $file_path ) );
		}
		update_post_meta( $attachment_id, 'rsl_ie_hash_added', current_time( 'mysql' ) );
		return true;
	}

	/**
	 * Return a stored hash or calculate it from the attached file.
	 *
	 * @param int  $attachment_id Attachment ID.
	 * @param bool $refresh       Recalculate even when a stored hash exists.
	 * @return string Empty string when the attachment file is unavailable.
	 */
	public static function get_or_create_hash( $attachment_id, $refresh = false ) {
		if ( ! $refresh ) {
			$hash = get_post_meta( $attachment_id, self::META_KEY, true );
			if ( is_string( $hash ) && preg_match( '/^[a-f0-9]{32}$/', $hash ) ) {
				return $hash;
			}

			$legacy_hash = get_post_meta( $attachment_id, self::LEGACY_META_KEY, true );
			if ( ! $legacy_hash ) {
				$legacy_hash = get_post_meta( $attachment_id, self::LEGACY_IMAGE_META_KEY, true );
			}
			if ( is_string( $legacy_hash ) && preg_match( '/^[a-f0-9]{32}$/', $legacy_hash ) ) {
				self::store_attachment_hash( $attachment_id, $legacy_hash );
				return $legacy_hash;
			}
		}

		$file_path = get_attached_file( $attachment_id );
		return self::calculate_and_store( $attachment_id, $file_path );
	}

	/**
	 * Calculate and store an attachment file hash.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $file_path     Absolute file path.
	 * @return string Empty string on failure.
	 */
	private static function calculate_and_store( $attachment_id, $file_path ) {
		if ( ! $file_path || ! is_file( $file_path ) || ! is_readable( $file_path ) ) {
			return '';
		}

		$hash = md5_file( $file_path );
		if ( ! $hash || ! self::store_attachment_hash( $attachment_id, $hash, $file_path ) ) {
			return '';
		}

		return $hash;
	}

	/**
	 * Check if file with same hash exists
	 *
	 * @param string $file_path Path to file to check.
	 * @return int|false Attachment ID if duplicate found, false otherwise.
	 */
	public static function find_duplicate( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return false;
		}

		$hash = md5_file( $file_path );
		if ( ! $hash ) {
			return false;
		}

		return self::get_attachment_by_hash( $hash );
	}

	/**
	 * Build or refresh hashes for a stable page of existing attachments.
	 *
	 * Useful for migrating existing media library
	 *
	 * @param int $batch_size Number of attachments to process per batch.
	 * @param int $offset     Offset for pagination.
	 * @return array Results with processed count and total.
	 */
	public static function bulk_add_hashes( $batch_size = 50, $offset = 0 ) {
		$args = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => $batch_size,
			'offset'         => $offset,
			'fields'         => 'ids',
			'orderby'        => 'ID',
			'order'          => 'ASC',
		];

		$query = new \WP_Query( $args );

		$processed = 0;
		$errors    = 0;

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $attachment_id ) {
				$file_path = get_attached_file( $attachment_id );

				if ( $file_path && file_exists( $file_path ) ) {
					if ( self::get_or_create_hash( $attachment_id, true ) ) {
						++$processed;
					} else {
						++$errors;
					}
				} else {
					++$errors;
				}
			}
		}

		$total       = (int) $query->found_posts;
		$attempted   = count( $query->posts );
		$next_offset = $offset + $attempted;
		$remaining   = max( 0, $total - $next_offset );

		return [
			'processed' => $processed,
			'errors'    => $errors,
			'remaining' => $remaining,
			'total'     => $total,
			'attempted' => $attempted,
			'offset'    => $next_offset,
			'complete'  => 0 === $remaining,
		];
	}

	/**
	 * Get statistics about hashed attachments
	 *
	 * @return array Statistics.
	 */
	public static function get_statistics() {
		// Total attachments.
		$total_args  = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => 1,
			'fields'         => 'ids',
		];
		$total_query = new \WP_Query( $total_args );
		$total       = $total_query->found_posts;

		// Attachments with hash.
		$hashed_args  = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.
				[
					'key'     => self::META_KEY,
					'compare' => 'EXISTS',
				],
			],
			'posts_per_page' => 1,
			'fields'         => 'ids',
		];
		$hashed_query = new \WP_Query( $hashed_args );
		$hashed       = $hashed_query->found_posts;

		$unhashed   = $total - $hashed;
		$percentage = $total > 0 ? round( ( $hashed / $total ) * 100, 2 ) : 0;

		return [
			'total'      => $total,
			'hashed'     => $hashed,
			'unhashed'   => $unhashed,
			'percentage' => $percentage,
		];
	}

	/**
	 * Check whether at least one Media Library attachment lacks a hash.
	 *
	 * @return bool
	 */
	public static function has_unindexed_attachments() {
		$query = new \WP_Query(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery -- Required to identify files missing the index meta.
					'relation' => 'OR',
					[
						'key'     => self::META_KEY,
						'compare' => 'NOT EXISTS',
					],
					[
						'key'   => self::META_KEY,
						'value' => '',
					],
				],
			]
		);

		return ! empty( $query->posts );
	}
}

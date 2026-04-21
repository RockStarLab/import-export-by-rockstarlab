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

class Media_Hash {

	/**
	 * Initialize hooks
	 *
	 * @return void
	 */
	public static function init() {
		// Hook into media upload process
		add_filter( 'wp_generate_attachment_metadata', [ __CLASS__, 'add_hash_to_attachment' ], 10, 2 );

		// Fallback hook for non-image files that don't generate metadata
		add_action( 'add_attachment', [ __CLASS__, 'add_hash_to_attachment_fallback' ] );

		// Hook for when file is replaced/updated
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

		// Calculate and store MD5 hash
		$hash = md5_file( $file_path );
		if ( $hash ) {
			update_post_meta( $attachment_id, 'rsl_ie_file_hash', $hash );
			update_post_meta( $attachment_id, 'rsl_ie_file_size', filesize( $file_path ) );
			update_post_meta( $attachment_id, 'rsl_ie_hash_added', current_time( 'mysql' ) );
		}

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
		// Check if hash already exists (from main hook)
		$existing_hash = get_post_meta( $attachment_id, 'rsl_ie_file_hash', true );
		if ( ! empty( $existing_hash ) ) {
			return; // Already processed
		}

		$file_path = get_attached_file( $attachment_id );

		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return;
		}

		// Calculate and store MD5 hash
		$hash = md5_file( $file_path );
		if ( $hash ) {
			update_post_meta( $attachment_id, 'rsl_ie_file_hash', $hash );
			update_post_meta( $attachment_id, 'rsl_ie_file_size', filesize( $file_path ) );
			update_post_meta( $attachment_id, 'rsl_ie_hash_added', current_time( 'mysql' ) );
		}
	}

	/**
	 * Clean up hash meta when attachment is deleted
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return void
	 */
	public static function cleanup_hash_meta( $attachment_id ) {
		delete_post_meta( $attachment_id, 'rsl_ie_file_hash' );
		delete_post_meta( $attachment_id, 'rsl_ie_file_size' );
		delete_post_meta( $attachment_id, 'rsl_ie_hash_added' );
	}

	/**
	 * Get attachment by hash
	 *
	 * @param string $hash MD5 hash to search for.
	 * @return int|false Attachment ID or false if not found.
	 */
	public static function get_attachment_by_hash( $hash ) {
		$args = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.
				[
					'key'   => 'rsl_ie_file_hash',
					'value' => $hash,
				],
			],
			'fields'         => 'ids',
			'posts_per_page' => 1,
		];

		$query = new \WP_Query( $args );
		if ( $query->have_posts() ) {
			return (int) $query->posts[0];
		}

		return false;
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
	 * Bulk add hashes to existing attachments without hash
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
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.
				[
					'key'     => 'rsl_ie_file_hash',
					'compare' => 'NOT EXISTS',
				],
			],
			'posts_per_page' => $batch_size,
			'offset'         => $offset,
			'fields'         => 'ids',
		];

		$query = new \WP_Query( $args );

		$processed = 0;
		$errors    = 0;

		if ( $query->have_posts() ) {
			foreach ( $query->posts as $attachment_id ) {
				$file_path = get_attached_file( $attachment_id );

				if ( $file_path && file_exists( $file_path ) ) {
					$hash = md5_file( $file_path );
					if ( $hash ) {
						update_post_meta( $attachment_id, 'rsl_ie_file_hash', $hash );
						update_post_meta( $attachment_id, 'rsl_ie_file_size', filesize( $file_path ) );
						update_post_meta( $attachment_id, 'rsl_ie_hash_added', current_time( 'mysql' ) );
						++$processed;
					} else {
						++$errors;
					}
				} else {
					++$errors;
				}
			}
		}

		// Get total count of attachments without hash
		$total_args  = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.
				[
					'key'     => 'rsl_ie_file_hash',
					'compare' => 'NOT EXISTS',
				],
			],
			'posts_per_page' => -1,
			'fields'         => 'ids',
		];
		$total_query = new \WP_Query( $total_args );
		$remaining   = $total_query->found_posts;

		return [
			'processed' => $processed,
			'errors'    => $errors,
			'remaining' => $remaining,
			'total'     => $remaining + $processed,
		];
	}

	/**
	 * Get statistics about hashed attachments
	 *
	 * @return array Statistics.
	 */
	public static function get_statistics() {
		// Total attachments
		$total_args  = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		];
		$total_query = new \WP_Query( $total_args );
		$total       = $total_query->found_posts;

		// Attachments with hash
		$hashed_args  = [
			'post_type'      => 'attachment',
			'post_status'    => 'inherit',
			'meta_query'     => [ // phpcs:ignore WordPress.DB.SlowDBQuery -- Direct DB query required here.
				[
					'key'     => 'rsl_ie_file_hash',
					'compare' => 'EXISTS',
				],
			],
			'posts_per_page' => -1,
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
}

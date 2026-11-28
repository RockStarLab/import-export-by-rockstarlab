<?php
/** Carry the files of Easy Digital Downloads products with Content Sync (push and pull). */
namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

/**
 * An EDD download keeps its files in the edd_download_files meta as a list of
 * { attachment_id, name, file (URL), condition }. The sender adds the attachments to the
 * media of the post, so the files travel with it; the receiver points each entry at the
 * local copy (new attachment ID and URL) and moves a new copy into EDD's protected uploads/edd folder.
 */
class Sync_Easy_Digital_Downloads {
	const POST_TYPE = 'download';
	const META      = 'edd_download_files';

	/**
	 * Attachment IDs of the files of a download.
	 *
	 * @param int $post_id Post.
	 * @return int[]
	 */
	public static function file_ids( $post_id ) {
		if ( self::POST_TYPE !== get_post_type( $post_id ) ) {
			return array();
		}
		$out = array();
		foreach ( self::files( $post_id ) as $file ) {
			$id = (int) ( $file['attachment_id'] ?? 0 );
			if ( ( $id <= 0 || 'attachment' !== get_post_type( $id ) ) && ! empty( $file['file'] ) && is_string( $file['file'] ) ) {
				$id = (int) \RockStarLab\ImportExport\Helper\Content_Sync_Media::attachment_url_to_original_postid( $file['file'] );
			}
			if ( $id > 0 && 'attachment' === get_post_type( $id ) ) {
				$out[] = $id;
			}
		}
		return array_values( array_unique( $out ) );
	}

	/** The file list of a download. */
	private static function files( $post_id ) {
		$files = get_post_meta( (int) $post_id, self::META, true );
		return is_array( $files ) ? $files : array();
	}

	/**
	 * Receiver: point the files of received downloads at the local copies.
	 *
	 * @param int[] $post_ids      Local posts of this sync.
	 * @param array $image_map     Source attachment ID => local attachment ID.
	 * @param array $image_sources Source media payload.
	 * @return void
	 */
	public static function fix_received( array $post_ids, array $image_map, array $image_sources ) {
		foreach ( array_unique( array_map( 'intval', $post_ids ) ) as $post_id ) {
			if ( $post_id > 0 && self::POST_TYPE === get_post_type( $post_id ) ) {
				self::fix_files( $post_id, $image_map, $image_sources );
			}
		}
	}

	/**
	 * Point the file entries of one download at local attachments.
	 *
	 * @param int   $post_id       Local download.
	 * @param array $image_map     Source attachment ID => local attachment ID.
	 * @param array $image_sources Source media payload.
	 * @return void
	 */
	public static function fix_files( $post_id, array $image_map, array $image_sources ) {
		$files = self::files( $post_id );
		if ( ! $files ) {
			return;
		}
		$changed = false;
		foreach ( $files as $key => $file ) {
			if ( ! is_array( $file ) ) {
				continue;
			}
			$local  = 0;
			$synced = false;
			$src    = (int) ( $file['attachment_id'] ?? 0 );
			if ( $src > 0 && ! empty( $image_map[ $src ] ) ) {
				$local  = (int) $image_map[ $src ];
				$synced = true;
			} elseif ( ! empty( $file['file'] ) && is_string( $file['file'] ) ) {
				// The meta was sent without IDs or by URL only: follow the URL to the uploaded copy.
				$url    = $image_map ? \RockStarLab\ImportExport\Helper\Content_Sync_Replacer::fix_local_image_urls_in_content( $file['file'], $image_map, $image_sources ) : $file['file'];
				$local  = is_string( $url ) ? (int) \RockStarLab\ImportExport\Helper\Content_Sync_Media::attachment_url_to_original_postid( $url ) : 0;
				$synced = $local > 0 && in_array( $local, array_map( 'intval', $image_map ), true );
			}
			if ( $local <= 0 || 'attachment' !== get_post_type( $local ) ) {
				continue;
			}
			if ( $synced ) {
				self::move_to_protected_folder( $local );
			}
			$url = wp_get_attachment_url( $local );
			if ( ! $url ) {
				continue;
			}
			if ( (int) ( $file['attachment_id'] ?? 0 ) !== $local || ( $file['file'] ?? '' ) !== $url ) {
				$files[ $key ]['attachment_id'] = $local;
				$files[ $key ]['file']          = $url;
				$changed                        = true;
			}
		}
		if ( $changed ) {
			update_post_meta( (int) $post_id, self::META, $files );
		}
	}

	/**
	 * EDD keeps product files in uploads/edd, which it protects from direct downloads.
	 * Move a copy that landed in the regular uploads folder there (same year/month).
	 *
	 * @param int $id Local attachment.
	 * @return void
	 */
	private static function move_to_protected_folder( $id ) {
		if ( ! function_exists( 'edd_get_upload_dir' ) || wp_attachment_is_image( $id ) ) {
			return;
		}
		// Only a copy this sync just created: a file that was already in the library may be linked elsewhere.
		$created = strtotime( (string) get_post_field( 'post_date_gmt', $id ) . ' UTC' );
		if ( ! $created || $created < time() - 15 * MINUTE_IN_SECONDS ) {
			return;
		}
		$relative = (string) get_post_meta( $id, '_wp_attached_file', true );
		if ( '' === $relative || 0 === strpos( $relative, 'edd/' ) ) {
			return;
		}
		$uploads = wp_upload_dir();
		$source  = trailingslashit( $uploads['basedir'] ) . $relative;
		$target  = trailingslashit( $uploads['basedir'] ) . 'edd/' . ltrim( $relative, '/' );
		if ( ! is_file( $source ) || file_exists( $target ) || ! wp_mkdir_p( dirname( $target ) ) ) {
			return;
		}
		if ( @rename( $source, $target ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors, WordPress.WP.AlternativeFunctions.rename_rename -- Same-filesystem move inside uploads.
			update_post_meta( $id, '_wp_attached_file', 'edd/' . ltrim( $relative, '/' ) );
		}
	}
}

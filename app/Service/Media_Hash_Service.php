<?php
/**
 * Shared, authorized Media Library hash operations.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;
use RockStarLab\ImportExport\Helper\Media_Hash;

defined( 'ABSPATH' ) || exit;

/** Hash file bytes only within this site's uploads and recheck attachment access. */
class Media_Hash_Service {

	/**
	 * Check management and media permissions for every entry point.
	 *
	 * @return true|\WP_Error
	 */
	private function authorize() {
		$access = Access_Policy::check_management();
		return is_wp_error( $access ) ? $access : ( current_user_can( 'upload_files' ) ? true : $this->error( 'media_forbidden', 403 ) );
	}

	/**
	 * Return counts of valid canonical hashes without writing metadata.
	 *
	 * @return array|\WP_Error
	 */
	public function statistics() {
		$access = $this->authorize();
		return is_wp_error( $access ) ? $access : Media_Hash::get_statistics();
	}

	/**
	 * Refresh an explicit selection or a bounded ID page.
	 *
	 * @param array $input Validated selection or cursor.
	 * @return array|\WP_Error
	 */
	public function build( array $input ) {
		$access = $this->authorize();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		global $wpdb;
		$after = (int) ( $input['after_id'] ?? 0 );
		$max   = (int) ( $input['snapshot_max_id'] ?? 0 );
		$limit = max( 1, min( 100, (int) ( $input['limit'] ?? 50 ) ) );
		$done  = true;
		if ( isset( $input['attachment_ids'] ) ) {
			if ( ! is_array( $input['attachment_ids'] ) || ! $input['attachment_ids'] || count( $input['attachment_ids'] ) > 100 || array_intersect( array_keys( $input ), [ 'after_id', 'snapshot_max_id', 'limit' ] ) ) {
				return $this->error( 'invalid_selection', 400 );
			}
			$ids = $input['attachment_ids'];
		} else {
			if ( $after < 0 || $max < 0 || ( $after && ! $max ) || ( $max && $after > $max ) ) {
				return $this->error( 'invalid_cursor', 400 );
			}
			if ( ! $max ) {
				$max = (int) $wpdb->get_var( "SELECT MAX(ID) FROM {$wpdb->posts} WHERE post_type = 'attachment' AND post_status = 'inherit'" ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Stable upper bound for cursor traversal.
			}
			$ids  = $wpdb->get_col( $wpdb->prepare( "SELECT ID FROM %i WHERE post_type = 'attachment' AND post_status = 'inherit' AND ID > %d AND ID <= %d ORDER BY ID ASC LIMIT %d", $wpdb->posts, $after, $max, $limit + 1 ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Keyset pagination avoids offset skips after deletion.
			$done = count( $ids ) <= $limit;
			$ids  = array_slice( $ids, 0, $limit );
		}
		$result = $this->index( $ids );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		return $result + [
			'complete'        => $done,
			'after_id'        => $ids ? max( array_map( 'intval', $ids ) ) : $after,
			'snapshot_max_id' => $max,
		];
	}

	/**
	 * Preserve the existing AJAX offset/counter contract over the same indexer.
	 *
	 * @param int $batch_size Page size.
	 * @param int $offset Legacy offset.
	 * @return array|\WP_Error
	 */
	public function build_legacy( $batch_size = 50, $offset = 0 ) {
		$access = $this->authorize();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		$offset = max( 0, (int) $offset );
		$query  = new \WP_Query(
			[
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'posts_per_page' => max( 1, min( 100, (int) $batch_size ) ),
				'offset'         => $offset,
				'fields'         => 'ids',
				'orderby'        => 'ID',
				'order'          => 'ASC',
			]
		);
		$result = $this->index( $query->posts );
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$total = (int) Media_Hash::get_statistics()['total'];
		$next  = $offset + $result['attempted'];
		return $result + [
			'total'     => $total,
			'offset'    => $next,
			'remaining' => max( 0, $total - $next ),
			'complete'  => $next >= $total,
		];
	}

	/**
	 * Preflight all capabilities, then report individual file failures.
	 *
	 * @param array $ids Selected attachment IDs.
	 * @return array|\WP_Error
	 */
	private function index( array $ids ) {
		$seen = [];
		foreach ( $ids as $id ) {
			if ( ! is_scalar( $id ) || ! ctype_digit( (string) $id ) || (int) $id < 1 || isset( $seen[ (int) $id ] ) || 'attachment' !== get_post_type( (int) $id ) || ! current_user_can( 'edit_post', (int) $id ) ) {
				return $this->error( 'attachment_forbidden', 403 );
			}
			$seen[ (int) $id ] = true;
		}
		$result = [
			'processed' => 0,
			'errors'    => 0,
			'attempted' => count( $ids ),
			'results'   => [],
		];
		foreach ( $ids as $id ) {
			$path = $this->uploads_file( get_attached_file( (int) $id ) );
			$hash = is_wp_error( $path ) ? false : md5_file( $path );
			$ok   = $hash && Media_Hash::store_attachment_hash( (int) $id, $hash, $path );
			++$result[ $ok ? 'processed' : 'errors' ];
			$result['results'][] = [
				'attachment_id' => (int) $id,
				'status'        => $ok ? 'indexed' : 'unavailable',
			];
		}
		return $result;
	}

	/**
	 * Find a duplicate by indexed MD5, uploads file, or source attachment.
	 *
	 * @param array $input One source selector.
	 * @return array|\WP_Error
	 */
	public function check_duplicate( array $input ) {
		$access = $this->authorize();
		if ( is_wp_error( $access ) ) {
			return $access;
		}
		if ( 1 !== count( array_intersect( array_keys( $input ), [ 'hash', 'file_path', 'attachment_id' ] ) ) ) {
			return $this->error( 'invalid_selection', 400 );
		}
		$exclude = 0;
		if ( isset( $input['attachment_id'] ) ) {
			$exclude = (int) $input['attachment_id'];
			if ( 'attachment' !== get_post_type( $exclude ) || ! current_user_can( 'read_post', $exclude ) ) {
				return $this->error( 'attachment_forbidden', 403 );
			}
			$input['file_path'] = get_attached_file( $exclude );
		}
		if ( array_key_exists( 'file_path', $input ) ) {
			$path = $this->uploads_file( $input['file_path'] );
			if ( is_wp_error( $path ) ) {
				return $path;
			}
			$hash = md5_file( $path );
		} else {
			$hash = $input['hash'];
		}
		if ( ! is_string( $hash ) || ! preg_match( '/^[a-f0-9]{32}$/iD', $hash ) ) {
			return $this->error( 'invalid_hash', 400 );
		}
		$id = Media_Hash::find_indexed_attachment( $hash, $exclude );
		if ( $id && ! current_user_can( 'read_post', $id ) ) {
			return $this->error( 'attachment_forbidden', 403 );
		}
		return [
			'is_duplicate'   => (bool) $id,
			'attachment_id'  => (int) $id,
			'attachment_url' => $id ? (string) wp_get_attachment_url( $id ) : '',
		];
	}

	/**
	 * Resolve readable regular files beneath this site's uploads, including symlinks.
	 *
	 * @param mixed $path Absolute path or uploads-relative path.
	 * @return string|\WP_Error
	 */
	private function uploads_file( $path ) {
		if ( ! is_string( $path ) || '' === $path || false !== strpos( $path, "\0" ) || preg_match( '#^[a-z][a-z0-9+.-]*://#i', $path ) ) {
			return $this->error( 'media_file_unavailable', 400 );
		}
		$uploads = wp_get_upload_dir();
		$base    = realpath( $uploads['basedir'] );
		$path    = path_is_absolute( $path ) ? $path : $uploads['basedir'] . '/' . $path;
		$real    = realpath( $path );
		if ( ! $base || ! $real || 0 !== strpos( $real, trailingslashit( $base ) ) || ! is_file( $real ) || ! is_readable( $real ) ) {
			return $this->error( 'media_file_unavailable', 400 );
		}
		return $real;
	}

	/**
	 * Do not echo paths or raw filesystem errors to callers.
	 *
	 * @param string $code Stable error suffix.
	 * @param int    $status HTTP status.
	 * @return \WP_Error
	 */
	private function error( $code, $status ) {
		return new \WP_Error( 'rsl_ie_' . $code, __( 'Check media selection, uploads file availability and permissions.', 'import-export-by-rockstarlab' ), [ 'status' => $status ] );
	}
}

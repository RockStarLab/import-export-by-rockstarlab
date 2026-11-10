<?php
/**
 * FTP and FTPS connection for saved servers.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

/** Thin wrapper over the PHP FTP extension. */
class Remote_FTP_Connection {
	/** @var resource|\FTP\Connection|null */
	private $conn = null;

	/**
	 * Connect and log in.
	 *
	 * @param array  $server Server.
	 * @param string $password Password.
	 * @return array|\WP_Error
	 */
	public function open( array $server, $password ) {
		$secure = 'ftps' === $server['protocol'];
		if ( ! function_exists( $secure ? 'ftp_ssl_connect' : 'ftp_connect' ) ) {
			return Remote_File_Service::error( 'remote_protocol_unavailable', $secure ? __( 'This server\'s PHP cannot use FTPS (the FTP extension with OpenSSL is missing). Ask your host to enable it, or use SFTP.', 'import-export-by-rockstarlab' ) : __( 'This server\'s PHP cannot use FTP (the FTP extension is missing). Ask your host to enable it, or use SFTP.', 'import-export-by-rockstarlab' ), 501 );
		}
		$conn = $secure ? @ftp_ssl_connect( $server['host'], (int) $server['port'], 20 ) : @ftp_connect( $server['host'], (int) $server['port'], 20 ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Reported below.
		if ( ! $conn ) {
			/* translators: 1: host, 2: port. */
			return Remote_File_Service::error( 'remote_connect_failed', sprintf( __( 'Could not connect to %1$s on port %2$d.', 'import-export-by-rockstarlab' ), $server['host'], (int) $server['port'] ), 502 );
		}
		if ( ! @ftp_login( $conn, $server['username'], $password ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Reported below.
			ftp_close( $conn );
			return Remote_File_Service::error( 'remote_login_failed', __( 'The server refused the user name or password.', 'import-export-by-rockstarlab' ), 401 );
		}
		if ( ! empty( $server['passive'] ) ) {
			ftp_pasv( $conn, true );
		}
		$this->conn = $conn;
		return [];
	}

	/**
	 * Files and folders in a folder.
	 *
	 * @param string $path Folder.
	 * @return array|\WP_Error
	 */
	public function listing( $path ) {
		$rows = function_exists( 'ftp_mlsd' ) ? @ftp_mlsd( $this->conn, $path ) : false; // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Falls back to NLST.
		$out  = [];
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				if ( in_array( $row['name'] ?? '', [ '.', '..', '' ], true ) || in_array( $row['type'] ?? '', [ 'cdir', 'pdir' ], true ) ) {
					continue;
				}
				$out[] = [
					'name'  => $row['name'],
					'type'  => 'dir' === ( $row['type'] ?? '' ) ? 'dir' : 'file',
					'size'  => (int) ( $row['size'] ?? 0 ),
					'mtime' => isset( $row['modify'] ) ? (int) strtotime( substr( $row['modify'], 0, 14 ) . ' UTC' ) : 0,
				];
			}
			return $out;
		}
		$names = @ftp_nlist( $this->conn, $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Reported below.
		if ( false === $names ) {
			return $this->missing( $path );
		}
		foreach ( $names as $name ) {
			$name = wp_basename( $name );
			if ( in_array( $name, [ '.', '..' ], true ) ) {
				continue;
			}
			$size  = ftp_size( $this->conn, rtrim( $path, '/' ) . '/' . $name );
			$out[] = [
				'name'  => $name,
				'type'  => $size < 0 ? 'dir' : 'file',
				'size'  => max( 0, $size ),
				'mtime' => 0,
			];
		}
		return $out;
	}

	/**
	 * Size and modification time of a file.
	 *
	 * @param string $path File.
	 * @return array|\WP_Error
	 */
	public function stat( $path ) {
		$size = ftp_size( $this->conn, $path );
		if ( $size < 0 ) {
			return $this->missing( $path );
		}
		$mtime = ftp_mdtm( $this->conn, $path );
		return [
			'size'  => (int) $size,
			'mtime' => $mtime > 0 ? (int) $mtime : 0,
		];
	}

	/**
	 * Download a file.
	 *
	 * @param string $path Remote file.
	 * @param string $local Local file.
	 * @return true|\WP_Error
	 */
	public function get( $path, $local ) {
		if ( ! @ftp_get( $this->conn, $local, $path, FTP_BINARY ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Reported below.
			/* translators: %s: path on the server. */
			return Remote_File_Service::error( 'remote_download_failed', sprintf( __( 'The file %s could not be downloaded from the server.', 'import-export-by-rockstarlab' ), $path ), 502 );
		}
		return true;
	}

	/**
	 * Upload a file through a temporary name, then rename it, so readers never see half a file.
	 *
	 * @param string $path Remote file.
	 * @param string $local Local file.
	 * @return true|\WP_Error
	 */
	public function put( $path, $local ) {
		$tmp = dirname( $path ) . '/.' . wp_basename( $path ) . '.part';
		if ( ! @ftp_put( $this->conn, $tmp, $local, FTP_BINARY ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Reported below.
			/* translators: %s: folder on the server. */
			return Remote_File_Service::error( 'remote_upload_failed', sprintf( __( 'The file could not be uploaded to %s. Check that the folder exists and the user may write to it.', 'import-export-by-rockstarlab' ), dirname( $path ) ), 502 );
		}
		if ( ftp_size( $this->conn, $path ) >= 0 ) {
			@ftp_delete( $this->conn, $path ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Replaced below.
		}
		if ( ! @ftp_rename( $this->conn, $tmp, $path ) ) { // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Reported below.
			@ftp_delete( $this->conn, $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Cleanup.
			/* translators: %s: path on the server. */
			return Remote_File_Service::error( 'remote_upload_failed', sprintf( __( 'The file could not be saved as %s on the server.', 'import-export-by-rockstarlab' ), $path ), 502 );
		}
		return true;
	}

	/** Close the connection. */
	public function close() {
		if ( $this->conn ) {
			@ftp_close( $this->conn ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged -- Already closed is fine.
			$this->conn = null;
		}
	}

	/**
	 * Not found error.
	 *
	 * @param string $path Path.
	 * @return \WP_Error
	 */
	private function missing( $path ) {
		/* translators: %s: path on the server. */
		return Remote_File_Service::error( 'remote_file_missing', sprintf( __( 'The server has no file or folder %s.', 'import-export-by-rockstarlab' ), $path ), 404 );
	}
}

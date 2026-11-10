<?php
/**
 * SFTP connection for saved servers (phpseclib, no PHP extension needed).
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

/** Thin wrapper over phpseclib's SFTP client. */
class Remote_SFTP_Connection {
	/** SSH_FILEXFER_TYPE_DIRECTORY. */
	const TYPE_DIRECTORY = 2;

	/** @var \phpseclib3\Net\SFTP|null */
	private $sftp = null;

	/**
	 * Connect and log in.
	 *
	 * @param array  $server Server.
	 * @param string $password Password.
	 * @return array|\WP_Error Includes the server's host key fingerprint.
	 */
	public function open( array $server, $password ) {
		if ( ! class_exists( '\phpseclib3\Net\SFTP' ) ) {
			return Remote_File_Service::error( 'remote_protocol_unavailable', __( 'SFTP support is missing from this copy of the plugin. Reinstall the plugin.', 'import-export-by-rockstarlab' ), 501 );
		}
		try {
			$sftp = new \phpseclib3\Net\SFTP( $server['host'], (int) $server['port'], 20 );
			$key  = $sftp->getServerPublicHostKey();
			if ( false === $key ) {
				/* translators: 1: host, 2: port. */
				return Remote_File_Service::error( 'remote_connect_failed', sprintf( __( 'Could not connect to %1$s on port %2$d.', 'import-export-by-rockstarlab' ), $server['host'], (int) $server['port'] ), 502 );
			}
			if ( ! $sftp->login( $server['username'], $password ) ) {
				return Remote_File_Service::error( 'remote_login_failed', __( 'The server refused the user name or password.', 'import-export-by-rockstarlab' ), 401 );
			}
		} catch ( \Throwable $e ) {
			/* translators: 1: host, 2: port, 3: reason. */
			return Remote_File_Service::error( 'remote_connect_failed', sprintf( __( 'Could not connect to %1$s on port %2$d (%3$s).', 'import-export-by-rockstarlab' ), $server['host'], (int) $server['port'], $e->getMessage() ), 502 );
		}
		$this->sftp = $sftp;
		return [ 'host_key' => 'SHA256:' . rtrim( base64_encode( hash( 'sha256', base64_decode( (string) preg_replace( '/^\S+\s+(\S+).*$/', '$1', $key ) ), true ) ), '=' ) ]; // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions -- OpenSSH-style fingerprint.
	}

	/**
	 * Files and folders in a folder.
	 *
	 * @param string $path Folder.
	 * @return array|\WP_Error
	 */
	public function listing( $path ) {
		$rows = $this->sftp->rawlist( $path );
		if ( ! is_array( $rows ) ) {
			return $this->missing( $path );
		}
		$out = [];
		foreach ( $rows as $name => $row ) {
			if ( in_array( (string) $name, [ '.', '..' ], true ) ) {
				continue;
			}
			$out[] = [
				'name'  => (string) $name,
				'type'  => self::TYPE_DIRECTORY === ( $row['type'] ?? 0 ) ? 'dir' : 'file',
				'size'  => (int) ( $row['size'] ?? 0 ),
				'mtime' => (int) ( $row['mtime'] ?? 0 ),
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
		$stat = $this->sftp->stat( $path );
		if ( ! is_array( $stat ) || self::TYPE_DIRECTORY === ( $stat['type'] ?? 0 ) ) {
			return $this->missing( $path );
		}
		return [
			'size'  => (int) ( $stat['size'] ?? 0 ),
			'mtime' => (int) ( $stat['mtime'] ?? 0 ),
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
		if ( ! $this->sftp->get( $path, $local ) ) {
			/* translators: %s: path on the server. */
			return Remote_File_Service::error( 'remote_download_failed', sprintf( __( 'The file %s could not be downloaded from the server.', 'import-export-by-rockstarlab' ), $path ), 502 );
		}
		return true;
	}

	/**
	 * Upload through a temporary name, then rename.
	 *
	 * @param string $path Remote file.
	 * @param string $local Local file.
	 * @return true|\WP_Error
	 */
	public function put( $path, $local ) {
		$tmp = dirname( $path ) . '/.' . wp_basename( $path ) . '.part';
		if ( ! $this->sftp->put( $tmp, $local, \phpseclib3\Net\SFTP::SOURCE_LOCAL_FILE ) ) {
			/* translators: %s: folder on the server. */
			return Remote_File_Service::error( 'remote_upload_failed', sprintf( __( 'The file could not be uploaded to %s. Check that the folder exists and the user may write to it.', 'import-export-by-rockstarlab' ), dirname( $path ) ), 502 );
		}
		if ( $this->sftp->file_exists( $path ) ) {
			$this->sftp->delete( $path, false );
		}
		if ( ! $this->sftp->rename( $tmp, $path ) ) {
			$this->sftp->delete( $tmp, false );
			/* translators: %s: path on the server. */
			return Remote_File_Service::error( 'remote_upload_failed', sprintf( __( 'The file could not be saved as %s on the server.', 'import-export-by-rockstarlab' ), $path ), 502 );
		}
		return true;
	}

	/** Close the connection. */
	public function close() {
		if ( $this->sftp ) {
			$this->sftp->disconnect();
			$this->sftp = null;
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

<?php
/**
 * Saved FTP, FTPS and SFTP servers: import files from them and upload exports to them.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

use RockStarLab\ImportExport\Abilities\Access_Policy;

defined( 'ABSPATH' ) || exit;

/**
 * Jobs refer to a file on a saved server as rsl-remote://<server id>/<path>, so no
 * password is ever stored in a job, a schedule or a trigger.
 */
class Remote_File_Service {
	/** Option that holds the saved servers. */
	const OPTION = 'rsl_ie_remote_servers';

	/** Address scheme for a file on a saved server. */
	const SCHEME = 'rsl-remote';

	/** Supported protocols. */
	const PROTOCOLS = [ 'ftp', 'ftps', 'sftp' ];

	/**
	 * Whether a source or destination points at a saved server.
	 *
	 * @param string $address Address.
	 * @return bool
	 */
	public static function is_remote( $address ) {
		return 0 === strpos( trim( (string) $address ), self::SCHEME . '://' );
	}

	/**
	 * Split rsl-remote://<id>/<path>.
	 *
	 * @param string $address Address.
	 * @return array|\WP_Error [ server, path ].
	 */
	public static function parse( $address ) {
		if ( ! preg_match( '#^' . self::SCHEME . '://([a-z0-9]{8,32})(/.*)?$#', trim( (string) $address ), $m ) ) {
			return self::error( 'remote_address_invalid', __( 'The server file address is not valid.', 'import-export-by-rockstarlab' ) );
		}
		$server = self::find( $m[1] );
		if ( ! $server ) {
			return self::error( 'remote_server_missing', __( 'The saved server was removed. Choose another server.', 'import-export-by-rockstarlab' ), 404 );
		}
		$path = self::clean_path( $m[2] ?? '/' );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		return [ $server, $path ];
	}

	/**
	 * Build an address for a server and path.
	 *
	 * @param string $id Server ID.
	 * @param string $path Path on the server.
	 * @return string
	 */
	public static function address( $id, $path ) {
		return self::SCHEME . '://' . $id . '/' . ltrim( (string) $path, '/' );
	}

	/**
	 * A readable label for an address, e.g. "Supplier FTP: /feeds/prices.csv".
	 *
	 * @param string $address Address.
	 * @return string
	 */
	public static function label( $address ) {
		$parsed = self::parse( $address );
		return is_wp_error( $parsed ) ? (string) $address : $parsed[0]['label'] . ': ' . $parsed[1];
	}

	/**
	 * Saved servers without their passwords.
	 *
	 * @return array
	 */
	public static function servers() {
		$out = [];
		foreach ( self::all() as $server ) {
			$out[] = self::public_view( $server );
		}
		return $out;
	}

	/**
	 * Add or change a server. An empty password keeps the saved one.
	 *
	 * @param array $input Fields.
	 * @return array|\WP_Error Saved server without password.
	 */
	public static function save( array $input ) {
		$permission = Access_Policy::check_management();
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		$all      = self::all();
		$id       = sanitize_key( (string) ( $input['id'] ?? '' ) );
		$existing = '' !== $id && isset( $all[ $id ] ) ? $all[ $id ] : null;
		if ( '' !== $id && ! $existing ) {
			return self::error( 'remote_server_missing', __( 'The saved server was removed. Choose another server.', 'import-export-by-rockstarlab' ), 404 );
		}
		$protocol = strtolower( (string) ( $input['protocol'] ?? ( $existing['protocol'] ?? 'sftp' ) ) );
		if ( ! in_array( $protocol, self::PROTOCOLS, true ) ) {
			return self::error( 'remote_protocol_invalid', __( 'Choose FTP, FTPS or SFTP.', 'import-export-by-rockstarlab' ) );
		}
		$host = strtolower( trim( (string) ( $input['host'] ?? '' ) ) );
		$host = preg_replace( '#^[a-z]+://#', '', $host );
		$host = rtrim( (string) $host, '/' );
		if ( '' === $host || ! preg_match( '/^[a-z0-9.\-]+$|^\[[0-9a-f:]+\]$/', $host ) ) {
			return self::error( 'remote_host_invalid', __( 'Enter the server name, e.g. ftp.example.com.', 'import-export-by-rockstarlab' ) );
		}
		$port = (int) ( $input['port'] ?? 0 );
		$port = $port > 0 && $port < 65536 ? $port : ( 'sftp' === $protocol ? 22 : 21 );
		$user = trim( sanitize_text_field( (string) ( $input['username'] ?? '' ) ) );
		if ( '' === $user ) {
			return self::error( 'remote_user_missing', __( 'Enter the user name.', 'import-export-by-rockstarlab' ) );
		}
		$password = (string) ( $input['password'] ?? '' );
		$secret   = '' !== $password ? self::encrypt( $password ) : ( $existing['password'] ?? '' );
		if ( '' === $secret ) {
			return self::error( 'remote_password_missing', __( 'Enter the password.', 'import-export-by-rockstarlab' ) );
		}
		$label  = trim( sanitize_text_field( (string) ( $input['label'] ?? '' ) ) );
		$server = [
			'id'          => $existing ? $existing['id'] : strtolower( wp_generate_password( 12, false, false ) ),
			'label'       => '' !== $label ? $label : $host,
			'protocol'    => $protocol,
			'host'        => $host,
			'port'        => $port,
			'username'    => $user,
			'password'    => $secret,
			'passive'     => ! isset( $input['passive'] ) || ! empty( $input['passive'] ),
			// A changed address or protocol has to be confirmed again.
			'host_key'    => $existing && $existing['host'] === $host && $existing['port'] === $port && $existing['protocol'] === $protocol ? ( $existing['host_key'] ?? '' ) : '',
			'created_at'  => $existing['created_at'] ?? current_time( 'mysql' ),
			'modified_at' => current_time( 'mysql' ),
		];
		$all[ $server['id'] ] = $server;
		update_option( self::OPTION, $all, false );
		return self::public_view( $server );
	}

	/**
	 * Remove a saved server.
	 *
	 * @param string $id Server ID.
	 * @return array|\WP_Error
	 */
	public static function delete( $id ) {
		$permission = Access_Policy::check_management();
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		$all = self::all();
		$id  = sanitize_key( (string) $id );
		if ( ! isset( $all[ $id ] ) ) {
			return self::error( 'remote_server_missing', __( 'The saved server was removed. Choose another server.', 'import-export-by-rockstarlab' ), 404 );
		}
		unset( $all[ $id ] );
		update_option( self::OPTION, $all, false );
		return [ 'deleted' => true ];
	}

	/**
	 * Connect, log in and list a folder.
	 *
	 * @param string $id Server ID.
	 * @param string $path Folder.
	 * @return array|\WP_Error
	 */
	public function browse( $id, $path = '/' ) {
		$permission = Access_Policy::check_management();
		if ( is_wp_error( $permission ) ) {
			return $permission;
		}
		$server = self::find( $id );
		if ( ! $server ) {
			return self::error( 'remote_server_missing', __( 'The saved server was removed. Choose another server.', 'import-export-by-rockstarlab' ), 404 );
		}
		$path = self::clean_path( $path );
		if ( is_wp_error( $path ) ) {
			return $path;
		}
		return $this->with_connection(
			$server,
			function ( $conn ) use ( $path ) {
				$items = $conn->listing( $path );
				if ( is_wp_error( $items ) ) {
					return $items;
				}
				usort(
					$items,
					static function ( $a, $b ) {
						return [ 'dir' === $a['type'] ? 0 : 1, strtolower( $a['name'] ) ] <=> [ 'dir' === $b['type'] ? 0 : 1, strtolower( $b['name'] ) ];
					}
				);
				return [
					'path'  => $path,
					'items' => array_slice( $items, 0, 500 ),
				];
			}
		);
	}

	/**
	 * Download a file to a local temporary file.
	 *
	 * @param string $address rsl-remote address.
	 * @param string $local Local file.
	 * @param int    $max_bytes Size limit.
	 * @return array|\WP_Error [ name, size ].
	 */
	public function download( $address, $local, $max_bytes ) {
		$parsed = self::parse( $address );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}
		list( $server, $path ) = $parsed;
		return $this->with_connection(
			$server,
			function ( $conn ) use ( $path, $local, $max_bytes ) {
				$stat = $conn->stat( $path );
				if ( is_wp_error( $stat ) ) {
					return $stat;
				}
				if ( $stat['size'] > $max_bytes ) {
					return self::error( 'remote_file_too_large', __( 'The file on the server is larger than the allowed import size.', 'import-export-by-rockstarlab' ), 413 );
				}
				$got = $conn->get( $path, $local );
				if ( is_wp_error( $got ) ) {
					return $got;
				}
				clearstatcache( true, $local );
				return [
					'name' => wp_basename( $path ),
					'size' => (int) filesize( $local ),
				];
			}
		);
	}

	/**
	 * Upload a local file. A destination ending with "/" is a folder: the file keeps its name.
	 *
	 * @param string $address rsl-remote address of a folder or file.
	 * @param string $local Local file.
	 * @param string $name File name for folder destinations.
	 * @return array|\WP_Error [ path, size ].
	 */
	public function upload( $address, $local, $name ) {
		$parsed = self::parse( $address );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}
		list( $server, $path ) = $parsed;
		if ( '/' === substr( trim( (string) $address ), -1 ) || '/' === $path ) {
			$path = rtrim( $path, '/' ) . '/' . sanitize_file_name( $name );
		}
		return $this->with_connection(
			$server,
			function ( $conn ) use ( $path, $local ) {
				$put = $conn->put( $path, $local );
				if ( is_wp_error( $put ) ) {
					return $put;
				}
				clearstatcache( true, $local );
				return [
					'path' => $path,
					'size' => (int) filesize( $local ),
				];
			}
		);
	}

	/**
	 * Size and modification time, for "file changed" triggers.
	 *
	 * @param string $address rsl-remote address.
	 * @return array|\WP_Error
	 */
	public function state( $address ) {
		$parsed = self::parse( $address );
		if ( is_wp_error( $parsed ) ) {
			return $parsed;
		}
		list( $server, $path ) = $parsed;
		return $this->with_connection(
			$server,
			function ( $conn ) use ( $path ) {
				$stat = $conn->stat( $path );
				return is_wp_error( $stat ) ? $stat : array_filter(
					[
						'length'   => (string) $stat['size'],
						'modified' => $stat['mtime'] ? (string) $stat['mtime'] : '',
					],
					'strlen'
				);
			}
		);
	}

	/**
	 * Which protocols this server can use.
	 *
	 * @return array
	 */
	public static function available() {
		return [
			'ftp'  => function_exists( 'ftp_connect' ),
			'ftps' => function_exists( 'ftp_ssl_connect' ),
			'sftp' => class_exists( '\phpseclib3\Net\SFTP' ),
		];
	}

	/**
	 * Open a connection, run a callback, close it.
	 *
	 * @param array    $server Server.
	 * @param callable $callback Work.
	 * @return mixed
	 */
	private function with_connection( array $server, callable $callback ) {
		$password = self::decrypt( $server['password'] );
		if ( '' === $password ) {
			return self::error( 'remote_password_unreadable', __( 'The saved password could not be read (the site keys may have changed). Enter the password again.', 'import-export-by-rockstarlab' ), 409 );
		}
		$conn = 'sftp' === $server['protocol'] ? new Remote_SFTP_Connection() : new Remote_FTP_Connection();
		$open = $conn->open( $server, $password );
		if ( is_wp_error( $open ) ) {
			return $open;
		}
		if ( 'sftp' === $server['protocol'] && isset( $open['host_key'] ) ) {
			if ( '' === (string) ( $server['host_key'] ?? '' ) ) {
				// Trust the key seen on the first connection, refuse a different one later.
				$all = self::all();
				if ( isset( $all[ $server['id'] ] ) ) {
					$all[ $server['id'] ]['host_key'] = $open['host_key'];
					update_option( self::OPTION, $all, false );
				}
			} elseif ( ! hash_equals( (string) $server['host_key'], (string) $open['host_key'] ) ) {
				$conn->close();
				return self::error( 'remote_host_key_changed', __( 'The SFTP server identifies itself with a different key than before. If the server was moved or reinstalled, save the server again to accept the new key.', 'import-export-by-rockstarlab' ), 409 );
			}
		}
		try {
			return $callback( $conn );
		} finally {
			$conn->close();
		}
	}

	/**
	 * Normalize a path on the server.
	 *
	 * @param string $path Path.
	 * @return string|\WP_Error
	 */
	public static function clean_path( $path ) {
		$path = str_replace( '\\', '/', rawurldecode( (string) $path ) );
		if ( preg_match( '/[\x00-\x1f]/', $path ) ) {
			return self::error( 'remote_path_invalid', __( 'The path on the server is not valid.', 'import-export-by-rockstarlab' ) );
		}
		$parts = [];
		foreach ( explode( '/', $path ) as $part ) {
			if ( '' === $part || '.' === $part ) {
				continue;
			}
			if ( '..' === $part ) {
				array_pop( $parts );
				continue;
			}
			$parts[] = $part;
		}
		return '/' . implode( '/', $parts );
	}

	/**
	 * Saved servers keyed by ID.
	 *
	 * @return array
	 */
	private static function all() {
		$all = get_option( self::OPTION, [] );
		return is_array( $all ) ? $all : [];
	}

	/**
	 * One saved server with its encrypted password.
	 *
	 * @param string $id Server ID.
	 * @return array|null
	 */
	private static function find( $id ) {
		$all = self::all();
		$id  = sanitize_key( (string) $id );
		return isset( $all[ $id ] ) && is_array( $all[ $id ] ) ? $all[ $id ] : null;
	}

	/**
	 * Server fields that may be shown.
	 *
	 * @param array $server Server.
	 * @return array
	 */
	private static function public_view( array $server ) {
		return [
			'id'           => $server['id'],
			'label'        => $server['label'],
			'protocol'     => $server['protocol'],
			'host'         => $server['host'],
			'port'         => (int) $server['port'],
			'username'     => $server['username'],
			'passive'      => ! empty( $server['passive'] ),
			'has_password' => '' !== (string) ( $server['password'] ?? '' ),
			'host_key'     => (string) ( $server['host_key'] ?? '' ),
			'address'      => self::address( $server['id'], '/' ),
		];
	}

	/**
	 * Encryption key derived from the site's secret keys.
	 *
	 * @return string
	 */
	private static function key() {
		return hash( 'sha256', wp_salt( 'auth' ) . '|rsl-ie-remote-servers', true );
	}

	/**
	 * Encrypt a password for the options table.
	 *
	 * @param string $plain Password.
	 * @return string
	 */
	private static function encrypt( $plain ) {
		if ( function_exists( 'sodium_crypto_secretbox' ) ) {
			$nonce = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
			return 's1:' . base64_encode( $nonce . sodium_crypto_secretbox( $plain, $nonce, self::key() ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary ciphertext.
		}
		if ( function_exists( 'openssl_encrypt' ) ) {
			$iv  = random_bytes( 16 );
			$enc = openssl_encrypt( $plain, 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, $iv );
			$mac = hash_hmac( 'sha256', $iv . $enc, self::key(), true );
			return 'o1:' . base64_encode( $iv . $mac . $enc ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary ciphertext.
		}
		return 'p0:' . base64_encode( $plain ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- No crypto extension available.
	}

	/**
	 * Decrypt a saved password; empty when it cannot be read.
	 *
	 * @param string $stored Saved value.
	 * @return string
	 */
	private static function decrypt( $stored ) {
		$stored = (string) $stored;
		$data   = base64_decode( substr( $stored, 3 ), true ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Binary ciphertext.
		if ( false === $data ) {
			return '';
		}
		$prefix = substr( $stored, 0, 3 );
		if ( 's1:' === $prefix && function_exists( 'sodium_crypto_secretbox_open' ) && strlen( $data ) > SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			$plain = sodium_crypto_secretbox_open( substr( $data, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), substr( $data, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ), self::key() );
			return false === $plain ? '' : $plain;
		}
		if ( 'o1:' === $prefix && function_exists( 'openssl_decrypt' ) && strlen( $data ) > 48 ) {
			$iv  = substr( $data, 0, 16 );
			$mac = substr( $data, 16, 32 );
			$enc = substr( $data, 48 );
			if ( ! hash_equals( $mac, hash_hmac( 'sha256', $iv . $enc, self::key(), true ) ) ) {
				return '';
			}
			$plain = openssl_decrypt( $enc, 'aes-256-cbc', self::key(), OPENSSL_RAW_DATA, $iv );
			return false === $plain ? '' : $plain;
		}
		return 'p0:' === $prefix ? $data : '';
	}

	/**
	 * Error helper.
	 *
	 * @param string $code Code without prefix.
	 * @param string $message Message.
	 * @param int    $status HTTP status.
	 * @return \WP_Error
	 */
	public static function error( $code, $message, $status = 400 ) {
		return new \WP_Error( 'rsl_ie_' . $code, $message, [ 'status' => $status ] );
	}
}

<?php
/** Incremental JSON container reader. */
namespace RockStarLab\ImportExport\Model\Format;

defined( 'ABSPATH' ) || exit;

/** Keeps only one encoded row in memory; json_decode validates each value. */
class JSON_Row_Stream {
	public static function rows( $path, array $options = [], &$cursor = null ) {
		$stream = fopen( $path, 'rb' );
		if ( ! $stream ) {
			throw new \RuntimeException( 'Cannot read JSON file.' );
		}
		$phase   = 'start';
		$value   = '';
		$stack   = [];
		$quoted  = false;
		$escaped = false;
		$depth   = $options['depth'] ?? 512;
		$object  = ! empty( $options['object_members'] );
		$opening = $object ? '{' : '[';
		$closing = $object ? '}' : ']';
		try {
			$identity = array_intersect_key( fstat( $stream ), array_flip( [ 'dev', 'ino', 'size', 'mtime', 'ctime' ] ) );
			$settings = hash( 'sha256', serialize( [ $depth, $object, $options['flags'] ?? 0 ] ) );
			if ( null !== $cursor ) {
				if ( ! is_array( $cursor ) || ( $cursor['identity'] ?? null ) !== $identity || ( $cursor['settings'] ?? null ) !== $settings || ! isset( $cursor['offset'], $cursor['phase'] ) || ! is_int( $cursor['offset'] ) || $cursor['offset'] < 0 || $cursor['offset'] > $identity['size'] || ! in_array( $cursor['phase'], [ 'next', 'end' ], true ) || 0 !== fseek( $stream, $cursor['offset'] ) ) {
					throw new \RuntimeException( 'Invalid JSON cursor.' ); }
				$phase = $cursor['phase'];
			}
			while ( ! feof( $stream ) ) {
				$buffer = fread( $stream, 65536 );
				if ( false === $buffer ) {
					throw new \RuntimeException( 'Cannot read JSON file.' );
				}
				for ( $i = 0, $length = strlen( $buffer ); $i < $length; ++$i ) {
					$char = $buffer[ $i ];
					if ( 'start' === $phase || 'end' === $phase ) {
						if ( false !== strpos( " \t\r\n", $char ) ) {
							continue; }
						if ( 'start' === $phase && $opening === $char ) {
							if ( $depth < 2 ) {
								throw new \RuntimeException( 'JSON depth exceeded.' ); }
							$phase = 'first';
							continue; }
						throw new \RuntimeException( 'Expected a JSON container without trailing content.' );
					}
					if ( ! $quoted && ! $stack && ( ',' === $char || $closing === $char ) ) {
						if ( '' === trim( $value ) ) {
							if ( 'first' !== $phase || $closing !== $char ) {
								throw new \RuntimeException( 'Missing JSON row.' ); }
						} else {
							$row = json_decode( $object ? '{' . $value . '}' : $value, true, $object ? $depth : $depth - 1, $options['flags'] ?? 0 );
							if ( JSON_ERROR_NONE !== json_last_error() ) {
								throw new \RuntimeException( 'Invalid JSON row.' ); }
							$cursor = [
								'identity' => $identity,
								'settings' => $settings,
								'offset'   => ftell( $stream ) - $length + $i + 1,
								'phase'    => $closing === $char ? 'end' : 'next',
							];
							yield $row;
						}
						$value = '';
						$phase = $closing === $char ? 'end' : 'next';
						continue;
					}
					// Preserve token separation without retaining arbitrary pretty-print padding.
					if ( ! $quoted && false !== strpos( " \t\r\n", $char ) && ( '' === $value || false !== strpos( " \t\r\n", substr( $value, -1 ) ) ) ) {
						continue;
					}
					$value .= $char;
					if ( $quoted ) {
						if ( $escaped ) {
							$escaped = false; } elseif ( '\\' === $char ) {
							$escaped = true; } elseif ( '"' === $char ) {
								$quoted = false; }
					} elseif ( '"' === $char ) {
						$quoted = true; } elseif ( '[' === $char || '{' === $char ) {
						$stack[] = $char;
						if ( count( $stack ) + 1 >= $depth ) {
							throw new \RuntimeException( 'JSON depth exceeded.' ); }
						} elseif ( ']' === $char || '}' === $char ) {
							if ( array_pop( $stack ) !== ( ']' === $char ? '[' : '{' ) ) {
								throw new \RuntimeException( 'Unbalanced JSON row.' ); }
						}
				}
			}
			if ( 'end' !== $phase ) {
				throw new \RuntimeException( 'Incomplete JSON container.' ); }
		} finally {
			fclose( $stream );
		}
	}
}

<?php
/**
 * Incremental migration manifest decoding.
 *
 * @package RockStarLab\ImportExport\Service
 */

namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.WP.AlternativeFunctions -- Bounded reads of private local streams.
// phpcs:disable Generic.CodeAnalysis.AssignmentInCondition -- Read until EOF without a second character fetch.

/** Decode one step at a time, retaining the existing last-member-wins semantics. */
class Migration_Manifest_Reader {
	/**
	 * Private input stream.
	 *
	 * @var resource
	 */
	private $stream;
	/**
	 * Current bounded input buffer.
	 *
	 * @var string
	 */
	private $buffer = '';
	/**
	 * Position within the input buffer.
	 *
	 * @var int
	 */
	private $offset = 0;
	/** @var resource|null Private normalized step spool. */
	private $steps_stream;
	/** @var int Number of spooled steps. */
	private $steps_count = 0;

	/**
	 * Decode a verified private file; schema validation belongs to the caller.
	 *
	 * @param string $path Verified private file.
	 * @param bool   $spool_steps Keep array steps on disk; consume through steps().
	 * @return array
	 * @throws \RuntimeException On unreadable or malformed input.
	 */
	public function decode( $path, $spool_steps = false ) {
		$this->close();
		$this->stream = fopen( $path, 'rb' );
		$this->buffer = '';
		$this->offset = 0;
		if ( ! $this->stream ) {
			throw new \RuntimeException( 'Cannot read manifest.' );
		}
		try {
			$this->expect( '{' );
			$result = array();
			if ( '}' !== $this->peek() ) {
				while ( true ) {
					$key = $this->value( 511 );
					if ( ! is_string( $key ) ) {
						throw new \RuntimeException( 'Invalid manifest key.' );
					}
					$this->expect( ':' );
					if ( 'steps' === $key && $spool_steps ) {
						$this->close();
					}
					if ( 'steps' === $key && '[' === $this->peek() ) {
						$this->expect( '[' );
						$steps = array();
						if ( $spool_steps ) {
							$this->steps_stream = tmpfile();
							if ( ! $this->steps_stream ) {
								throw new \RuntimeException( 'Cannot create manifest spool.' ); }
						}
						if ( ']' !== $this->peek() ) {
							while ( true ) {
								$step = $this->value( 510 );
								if ( $spool_steps ) {
									$encoded = base64_encode( serialize( $step ) ) . "\n";
									if ( strlen( $encoded ) !== fwrite( $this->steps_stream, $encoded ) ) {
										throw new \RuntimeException( 'Cannot write manifest spool.' ); }
									++$this->steps_count;
								} else {
									$steps[] = $step; }
								if ( ']' === $this->peek() ) {
									break;
								}
								$this->expect( ',' );
							}
						}
						$this->expect( ']' );
						$result[ $key ] = $steps;
						unset( $steps );
					} else {
						$result[ $key ] = $this->value( 511 );
					}
					if ( '}' === $this->peek() ) {
						break;
					}
					$this->expect( ',' );
				}
			}
			$this->expect( '}' );
			if ( null !== $this->peek() ) {
				throw new \RuntimeException( 'Trailing manifest content.' );
			}
			return $result;
		} catch ( \Throwable $error ) {
			$this->close();
			throw $error;
		} finally {
			fclose( $this->stream );
		}
	}

	/** Iterate the final steps member; overwritten members never escape. */
	public function steps( array $manifest ) {
		if ( ! is_resource( $this->steps_stream ) ) {
			yield from $manifest['steps'];
			return;
		}
		if ( ! rewind( $this->steps_stream ) ) {
			throw new \RuntimeException( 'Cannot rewind manifest spool.' ); }
		while ( false !== ( $line = fgets( $this->steps_stream ) ) ) {
			$decoded = base64_decode( rtrim( $line, "\n" ), true );
			if ( false === $decoded ) {
				throw new \RuntimeException( 'Invalid manifest spool.' ); }
			yield unserialize( $decoded, [ 'allowed_classes' => false ] );
		}
		if ( ! feof( $this->steps_stream ) ) {
			throw new \RuntimeException( 'Cannot read manifest spool.' ); }
	}

	/** Count without materializing the final steps array. */
	public function count_steps( array $manifest ) {
		return is_resource( $this->steps_stream ) ? $this->steps_count : count( $manifest['steps'] );
	}

	/** Release anonymous temporary storage, including overwritten steps. */
	public function close() {
		if ( is_resource( $this->steps_stream ) ) {
			fclose( $this->steps_stream ); }
		$this->steps_stream = null;
		$this->steps_count  = 0;
	}

	public function __destruct() {
		$this->close(); }

	/**
	 * Read a single JSON value, discarding redundant whitespace outside strings.
	 *
	 * @param int $depth Remaining JSON depth.
	 * @return mixed
	 * @throws \RuntimeException On malformed input.
	 */
	private function value( $depth ) {
		$value   = '';
		$stack   = array();
		$quoted  = false;
		$escaped = false;
		while ( null !== ( $char = $this->character() ) ) {
			if ( ! $quoted && ! $stack && false !== strpos( ',]}:', $char ) ) {
				break;
			}
			++$this->offset;
			if ( ! $quoted && false !== strpos( " \t\r\n", $char ) ) {
				$this->offset += strspn( $this->buffer, " \t\r\n", $this->offset );
				if ( '' !== $value && ' ' !== substr( $value, -1 ) ) {
					$value .= ' ';
				}
				continue;
			}
			$value .= $char;
			if ( $quoted ) {
				if ( $escaped ) {
					$escaped = false;
				} elseif ( '\\' === $char ) {
					$escaped = true;
				} elseif ( '"' === $char ) {
					$quoted = false;
				}
			} elseif ( '"' === $char ) {
				$quoted = true;
			} elseif ( '[' === $char || '{' === $char ) {
				$stack[] = $char;
				if ( count( $stack ) >= $depth ) {
					throw new \RuntimeException( 'Manifest depth exceeded.' );
				}
			} elseif ( ']' === $char || '}' === $char ) {
				if ( array_pop( $stack ) !== ( ']' === $char ? '[' : '{' ) ) {
					throw new \RuntimeException( 'Unbalanced manifest value.' );
				}
			}
		}
		return json_decode( $value, true, $depth, JSON_THROW_ON_ERROR );
	}

	/**
	 * Consume an expected delimiter.
	 *
	 * @param string $char Expected byte.
	 * @throws \RuntimeException On an unexpected delimiter.
	 */
	private function expect( $char ) {
		if ( $this->peek() !== $char ) {
			throw new \RuntimeException( 'Invalid manifest delimiter.' );
		}
		++$this->offset;
	}

	/** Return the next non-whitespace byte without consuming it. */
	private function peek() {
		while ( null !== ( $char = $this->character() ) && false !== strpos( " \t\r\n", $char ) ) {
			$this->offset += strspn( $this->buffer, " \t\r\n", $this->offset );
		}
		return $char;
	}

	/**
	 * Refill a bounded buffer and return its current byte.
	 *
	 * @return string|null
	 * @throws \RuntimeException On failed reads.
	 */
	private function character() {
		if ( $this->offset >= strlen( $this->buffer ) ) {
			$this->buffer = fread( $this->stream, 65536 );
			$this->offset = 0;
			if ( false === $this->buffer || ( '' === $this->buffer && ! feof( $this->stream ) ) ) {
				throw new \RuntimeException( 'Cannot read manifest.' );
			}
			if ( '' === $this->buffer ) {
				return null;
			}
		}
		return $this->buffer[ $this->offset ];
	}
}

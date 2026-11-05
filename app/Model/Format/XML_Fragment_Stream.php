<?php
/** Seekable XML record boundaries; libxml remains the syntax and namespace validator. */
namespace RockStarLab\ImportExport\Model\Format;

defined( 'ABSPATH' ) || exit;
class XML_Fragment_Stream {
	private $stream;
	private $buffer   = '';
	private $position = 0;
	private $base     = 0;
	private $encoding;
	private $width;
	private $little;
	public function __construct( $path, $offset, $encoding = array(
		'name'   => 'UTF-8',
		'width'  => 1,
		'little' => false,
	) ) {
		$this->encoding = $encoding['name'];
		$this->width    = $encoding['width'];
		$this->little   = $encoding['little'];
		$this->stream   = fopen( $path, 'rb' );
		if ( ! $this->stream || fseek( $this->stream, $offset ) !== 0 ) {
			throw new \RuntimeException( 'Cannot seek XML source.' );}
		$this->base = $offset;
	}
	private function fill() {
		if ( $this->position < strlen( $this->buffer ) ) {
			return true;}
		$this->base    += $this->position;
		$this->position = 0;
		$this->buffer   = fread( $this->stream, 65536 );
		if ( false === $this->buffer ) {
			throw new \RuntimeException( 'Cannot read XML source.' );}
		return '' !== $this->buffer;
	}
	private function byte() {
		return $this->fill() ? $this->buffer[ $this->position++ ] : null;}
	private function unit() {
		$raw = '';for ( $i = 0;$i < $this->width;++$i ) {
			$byte = $this->byte();
			if ( null === $byte ) {
				if ( $raw !== '' ) {
					throw new \RuntimeException( 'Incomplete XML encoding unit.' );
				}return null;
			}$raw .= $byte;}
		return $raw;
	}
	private function symbol( $raw ) {
		if ( 1 === $this->width ) {
			return $raw;}
		$char = $this->little ? $raw[0] : $raw[ $this->width - 1 ];
		return $raw === ( $this->little ? $char . str_repeat( "\0", $this->width - 1 ) : str_repeat( "\0", $this->width - 1 ) . $char ) ? $char : "\x80";
	}
	private function decoded( $raw ) {
		if ( 'UTF-8' === $this->encoding ) {
			return $raw;}
		$value = @iconv( $this->encoding, 'UTF-8', $raw );
		if ( false === $value ) {
			throw new \RuntimeException( 'Invalid XML encoding.' );}
		return $value;
	}
	private function token() {
		$first = $this->unit();
		if ( null === $first ) {
			return null;}
		$raw = $first;
		if ( '<' !== $this->symbol( $first ) ) {
			while ( null !== ( $unit = $this->unit() ) ) {
				if ( '<' === $this->symbol( $unit ) ) {
							$this->position -= $this->width;
							break;
				}$raw .= $unit;}
			return $this->decoded( $raw );
		}
		$quote      = null;
		$terminator = null;
		$head       = '<';
		$tail       = '<';
		while ( null !== ( $unit = $this->unit() ) ) {
			$raw .= $unit;
			$char = $this->symbol( $unit );
			if ( strlen( $head ) < 9 ) {
				$head .= $char;
			}$tail = substr( $tail . $char, -3 );
			if ( '<!--' === $head ) {
				$terminator = '-->';
			} elseif ( '<![CDATA[' === $head ) {
				$terminator = ']]>';
			} elseif ( '<?' === $head ) {
				$terminator = '?>';}
			if ( $terminator ) {
				if ( substr( $tail, -strlen( $terminator ) ) === $terminator ) {
							return $this->decoded( $raw );
				}continue;}
			if ( null !== $quote ) {
				if ( $char === $quote ) {
							$quote = null;
				}continue;}
			if ( '"' === $char || "'" === $char ) {
				$quote = $char;
				continue;}
			if ( '>' === $char ) {
				return $this->decoded( $raw );}
		}
		throw new \RuntimeException( 'Incomplete XML token.' );
	}
	/** Each checkpoint starts after a complete outermost matching record. */
	public function segments( $item, array $state, $max_bytes = 0 ) {
		$stack       = $state['stack'];
		$roots       = $state['roots'];
		$declaration = $state['declaration'];
		$prefix      = $declaration . implode( '', array_column( $stack, 'open' ) ) . ( ! $stack && $roots ? '<rsl-tail/>' : '' );
		$fragment    = '';
		$record      = null;
		while ( null !== ( $token = $this->token() ) ) {
			if ( preg_match( '/^<\?xml\s/', $token ) ) {
				$declaration = $token; }
			$fragment .= $token;
			if ( strncmp( $token, '<!DOCTYPE', 9 ) === 0 ) {
				throw new \RuntimeException( 'XML document types are unsupported.' );} elseif ( preg_match( '/^<\/([^\s>]+)\s*>$/D', $token, $match ) ) {
				$last = array_pop( $stack );
				if ( ! $last || $last['name'] !== $match[1] ) {
					throw new \RuntimeException( 'Unbalanced XML element.' );}
				} elseif ( preg_match( '/^<([^!?\/\s>]+)(?:\s|\/?>)/', $token, $match ) ) {
					if ( ! $stack && ++$roots > 1 ) {
						throw new \RuntimeException( 'Multiple XML roots.' );}
					$empty = substr( $token, -2 ) === '/>';
					if ( ( is_array( $item ) ? in_array( substr( strrchr( ':' . $match[1], ':' ), 1 ), $item, true ) : $match[1] === $item ) && null === $record ) {
						$record = count( $stack );}
					if ( ! $empty ) {
						$stack[] = array(
							'name' => $match[1],
							'open' => $token,
						);}
				}
				if ( ( null !== $record && count( $stack ) === $record ) || ( null === $record && $max_bytes > 0 && strlen( $fragment ) >= $max_bytes ) ) {
					$suffix = '';
					foreach ( array_reverse( $stack ) as $ancestor ) {
						$suffix .= '</' . $ancestor['name'] . '>';}
					$next = array(
						'offset'      => $this->base + $this->position,
						'stack'       => $stack,
						'roots'       => $roots,
						'declaration' => $declaration,
					);
					yield array(
						'xml'    => $prefix . $fragment . $suffix,
						'prefix' => $prefix,
						'body'   => $fragment,
						'suffix' => $suffix,
						'next'   => $next,
					);
					$prefix   = $declaration . implode( '', array_column( $stack, 'open' ) ) . ( ! $stack && $roots ? '<rsl-tail/>' : '' );
					$fragment = '';
					$record   = null;
				}
		}
		if ( $stack || 0 === $roots ) {
			throw new \RuntimeException( 'Incomplete XML document.' );}
		// Validate closing tags, trailing text and the empty-document case as well.
		yield array(
			'xml'    => $prefix . $fragment,
			'prefix' => $prefix,
			'body'   => $fragment,
			'suffix' => '',
			'next'   => array(
				'offset'      => $this->base + $this->position,
				'stack'       => array(),
				'roots'       => $roots,
				'declaration' => $declaration,
			),
			'eof'    => true,
		);
	}
	public function __destruct() {
		if ( is_resource( $this->stream ) ) {
			fclose( $this->stream );}}
}

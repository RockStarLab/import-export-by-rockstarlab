<?php
/** Bounded external sorting of validated archive steps. */
namespace RockStarLab\ImportExport\Service;

// phpcs:disable WordPress.WP.AlternativeFunctions.file_system_operations_fclose,WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- Streamed, chunked file handling for large imports, exports and media transfers: PHP stream handles, locks, private 0700 directories and atomic renames that WP_Filesystem does not provide (it also needs credentials, which cron and background requests lack).

defined( 'ABSPATH' ) || exit;

class Migration_Step_Sort {
	private $buffer = array();
	private $runs   = array();
	private $count  = 0;
	/** @var int Approximate serialized bytes retained by the current run. */
	private $buffer_bytes = 0;

	public static function compare( array $a, array $b ) {
		$order = $a['scope']['order'] <=> $b['scope']['order'];
		return $order ?: strcmp( $a['scope']['key'], $b['scope']['key'] );
	}

	public function append( array $step ) {
		$bytes = strlen( serialize( $step ) );
		if ( $this->buffer && $this->buffer_bytes + $bytes > 1048576 ) {
			$this->flush();
		}
		$this->buffer[]      = $step;
		$this->buffer_bytes += $bytes;
		++$this->count;
		if ( count( $this->buffer ) >= 100 || $this->buffer_bytes >= 1048576 ) {
			$this->flush(); }
	}

	private function write( $stream, array $step ) {
		$line = base64_encode( serialize( $step ) ) . "\n";
		if ( strlen( $line ) !== fwrite( $stream, $line ) ) {
			throw new \RuntimeException( esc_html__( 'Cannot write step sort.', 'import-export-by-rockstarlab' ) ); }
	}

	private function read( $stream ) {
		$line = fgets( $stream );
		if ( false === $line ) {
			if ( ! feof( $stream ) ) {
				throw new \RuntimeException( esc_html__( 'Cannot read step sort.', 'import-export-by-rockstarlab' ) ); }
			return null;
		}
		$value = unserialize( base64_decode( rtrim( $line, "\n" ), true ), array( 'allowed_classes' => false ) );
		if ( ! is_array( $value ) ) {
			throw new \RuntimeException( esc_html__( 'Invalid step sort.', 'import-export-by-rockstarlab' ) ); }
		return $value;
	}

	private function stream() {
		$stream = tmpfile();
		if ( ! $stream ) {
			throw new \RuntimeException( esc_html__( 'Cannot create step sort.', 'import-export-by-rockstarlab' ) ); }
		return $stream;
	}

	private function merge( $left, $right ) {
		$output = $this->stream();
		try {
			if ( ! rewind( $left ) || ! rewind( $right ) ) {
				throw new \RuntimeException( __( 'Cannot rewind step sort.', 'import-export-by-rockstarlab' ) ); }
			$a = $this->read( $left );
			$b = $this->read( $right );
			while ( null !== $a || null !== $b ) {
				if ( null === $b || ( null !== $a && self::compare( $a, $b ) <= 0 ) ) {
					$this->write( $output, $a );
					$a = $this->read( $left );
				} else {
					$this->write( $output, $b );
					$b = $this->read( $right ); }
			}
			return $output;
		} catch ( \Throwable $error ) {
			fclose( $output );
			throw $error; }
	}

	private function flush() {
		if ( ! $this->buffer ) {
			return; }
		usort( $this->buffer, array( self::class, 'compare' ) );
		$run = $this->stream();
		try {
			foreach ( $this->buffer as $step ) {
				$this->write( $run, $step ); }
			$this->buffer       = array();
			$this->buffer_bytes = 0;
			$level              = 0;
			while ( isset( $this->runs[ $level ] ) ) {
				$merged = $this->merge( $this->runs[ $level ], $run );
				fclose( $this->runs[ $level ] );
				unset( $this->runs[ $level ] );
				fclose( $run );
				$run = $merged;
				++$level;
			}
			$this->runs[ $level ] = $run;
		} catch ( \Throwable $error ) {
			fclose( $run );
			throw $error; }
	}

	/** Select one dependency-ordered step without retaining the full result. */
	public function select( $index ) {
		if ( ! is_int( $index ) || $index < 0 || $index >= $this->count ) {
			throw new \InvalidArgumentException( esc_html__( 'Invalid step ordinal.', 'import-export-by-rockstarlab' ) );
		}
		$this->flush();
		$run = null;
		try {
			foreach ( $this->runs as $part ) {
				if ( null === $run ) {
					$run = $this->stream();
					if ( ! rewind( $part ) || false === stream_copy_to_stream( $part, $run ) ) {
						throw new \RuntimeException( __( 'Cannot copy step sort.', 'import-export-by-rockstarlab' ) ); }
				} else {
					$merged = $this->merge( $run, $part );
					fclose( $run );
					$run = $merged; }
			}
			if ( null === $run || ! rewind( $run ) ) {
				throw new \RuntimeException( __( 'Missing step sort.', 'import-export-by-rockstarlab' ) ); }
			for ( $i = 0; $i <= $index; ++$i ) {
				$step = $this->read( $run );
				if ( null === $step ) {
					throw new \RuntimeException( __( 'Truncated step sort.', 'import-export-by-rockstarlab' ) ); }
			}
			return array(
				'total_steps' => $this->count,
				'steps'       => array( $index => $step ),
			);
		} finally {
			if ( is_resource( $run ) ) {
				fclose( $run ); }
		}
	}

	public function count() {
		return $this->count; }
	public function close() {
		foreach ( $this->runs as $run ) {
			fclose( $run );
		} $this->runs       = array();
		$this->buffer       = array();
		$this->buffer_bytes = 0; }
	public function __destruct() {
		$this->close(); }
}

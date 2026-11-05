<?php
/**
 * Private disk index for case-insensitive archive names.
 *
 * @package RockStarLab\ImportExport\Service
 */
namespace RockStarLab\ImportExport\Service;

defined( 'ABSPATH' ) || exit;

/** Retain archive membership on disk instead of in one unbounded PHP array. */
class Migration_Entry_Index {
	/** @var string Private temporary directory. */
	private $directory;
	/** @var int Number of names not yet consumed by the manifest. */
	private $remaining = 0;
	/** @var resource|null Exclusive lifetime lock, released by process termination. */
	private $owner;

	/** Create an index owned by one inspection invocation. */
	public function __construct() {
		$this->directory = rtrim( get_temp_dir(), '/\\' ) . '/rsl-archive-index-' . bin2hex( random_bytes( 16 ) );
		if ( ! mkdir( $this->directory, 0700 ) ) {
			throw new \RuntimeException( 'Cannot create archive index.' );
		}
		$this->owner = fopen( $this->directory . '/.owner', 'x+b' );
		if ( ! $this->owner || ! flock( $this->owner, LOCK_EX | LOCK_NB ) ) {
			$this->close();
			throw new \RuntimeException( 'Cannot lock archive index.' );
		}
	}

	/** Resolve an ASCII archive name into a fixed-size private key. */
	private function path( $name ) {
		$hash = hash( 'sha256', strtolower( $name ) );
		return $this->directory . '/' . substr( $hash, 0, 2 ) . '/' . $hash;
	}

	/** False means a duplicate name, including a case-only alias. */
	public function add( $name ) {
		$path = $this->path( $name );
		if ( file_exists( $path ) ) {
			return false;
		}
		$bucket = dirname( $path );
		if ( ! is_dir( $bucket ) && ! mkdir( $bucket, 0700 ) ) {
			throw new \RuntimeException( 'Cannot create archive bucket.' );
		}
		if ( strlen( $name ) !== file_put_contents( $path, $name ) ) {
			throw new \RuntimeException( 'Cannot write archive index.' );
		}
		++$this->remaining;
		return true;
	}

	/** Consume one exact spelling; a name cannot be used by two manifest steps. */
	public function consume( $name ) {
		$path = $this->path( $name );
		if ( ! is_file( $path ) ) {
			return false;
		}
		$value = file_get_contents( $path );
		if ( false === $value ) {
			throw new \RuntimeException( 'Cannot read archive index.' );
		}
		if ( $value !== $name ) {
			return false;
		}
		if ( ! unlink( $path ) ) {
			throw new \RuntimeException( 'Cannot consume archive index.' );
		}
		--$this->remaining;
		return true;
	}

	/** Return the number of unlisted archive entries. */
	public function remaining() {
		return $this->remaining;
	}

	/** Remove all buckets on both validation success and failure. */
	public function close() {
		if ( ! is_dir( $this->directory ) ) {
			$this->release();
			return;
		}
		$files = new \RecursiveIteratorIterator( new \RecursiveDirectoryIterator( $this->directory, \FilesystemIterator::SKIP_DOTS ), \RecursiveIteratorIterator::CHILD_FIRST );
		foreach ( $files as $file ) {
			if ( $file->isDir() ) {
				rmdir( $file->getPathname() );
			} else {
				unlink( $file->getPathname() );
			}
		}
		rmdir( $this->directory );
		$this->release();
	}

	/** Release only after the index has been removed. */
	private function release() {
		if ( is_resource( $this->owner ) ) {
			fclose( $this->owner );
		}
		$this->owner = null;
	}

	/** Reclaim stale interrupted inspections; live indexes remain exclusively locked.
	 *
	 * @return int Number of removed filesystem entries (at most 1000 per call).
	 */
	public static function cleanup() {
		$removed = 0;
		$root    = rtrim( get_temp_dir(), '/\\' );
		foreach ( new \DirectoryIterator( $root ) as $entry ) {
			if ( ! preg_match( '/^rsl-archive-index-[a-f0-9]{32}$/D', $entry->getFilename() ) || $entry->isLink() || ! $entry->isDir() ) {
				continue;
			}
			$path     = $entry->getPathname();
			$marker   = $path . '/.owner';
			$modified = @filemtime( $marker );
			if ( is_link( $marker ) || ! is_file( $marker ) || false === $modified || $modified > time() - DAY_IN_SECONDS ) {
				continue;
			}
			$owner = @fopen( $marker, 'r+b' );
			if ( ! $owner ) {
				continue; }
			try {
				if ( ! flock( $owner, LOCK_EX | LOCK_NB ) ) {
					continue; }
				foreach ( new \DirectoryIterator( $path ) as $bucket ) {
					if ( ! preg_match( '/^[a-f0-9]{2}$/D', $bucket->getFilename() ) || $bucket->isLink() || ! $bucket->isDir() ) {
						continue; }
					foreach ( new \DirectoryIterator( $bucket->getPathname() ) as $file ) {
						if ( $file->isLink() || ! $file->isFile() || ! preg_match( '/^' . $bucket->getFilename() . '[a-f0-9]{62}$/D', $file->getFilename() ) ) {
							continue; }
						if ( unlink( $file->getPathname() ) ) {
							++$removed; }
						if ( $removed >= 998 ) {
							return $removed; }
					}
					if ( 2 === iterator_count( new \DirectoryIterator( $bucket->getPathname() ) ) && rmdir( $bucket->getPathname() ) ) {
						++$removed; }
					if ( $removed >= 998 ) {
						return $removed; }
				}
				// Unknown entries are left untouched, including symlinks and foreign files.
				if ( 3 === iterator_count( new \DirectoryIterator( $path ) ) && unlink( $marker ) ) {
					++$removed;
					if ( rmdir( $path ) ) {
						++$removed; }
				}
				if ( $removed >= 998 ) {
					return $removed; }
			} catch ( \UnexpectedValueException $error ) {
				// A competing cleaner may have removed the directory before this lock was acquired.
				continue;
			} finally {
				fclose( $owner ); }
		}
		return $removed;
	}
}

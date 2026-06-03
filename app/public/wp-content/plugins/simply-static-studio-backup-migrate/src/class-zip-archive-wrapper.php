<?php

namespace Simply_Static\Backup;

use Simply_Static\Backup\Logs\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

/**
 * Wrapper class for ZIP archive operations.
 * Uses ZipArchive when available, falls back to PclZip (WordPress built-in).
 */
class Zip_Archive_Wrapper {

	/**
	 * The underlying archive handler (ZipArchive or PclZip).
	 *
	 * @var \ZipArchive|\PclZip|null
	 */
	private $archive = null;

	/**
	 * Whether we're using ZipArchive (true) or PclZip (false).
	 *
	 * @var bool
	 */
	private $use_zip_archive = false;

	/**
	 * The path to the archive file.
	 *
	 * @var string
	 */
	private $archive_path = '';

	/**
	 * Whether the archive is currently open.
	 *
	 * @var bool
	 */
	private $is_open = false;

	/**
	 * Number of files added since the last flush (ZipArchive only).
	 *
	 * @var int
	 */
	private $unflushed_count = 0;

	/**
	 * Archive file size threshold (in bytes) above which expensive safety
	 * copies during flush/close are skipped to avoid I/O bottlenecks.
	 *
	 * Default: 500 MB.
	 *
	 * @var int
	 */
	private $large_archive_threshold = 524288000;

	/**
	 * How many files to queue before forcing a flush (close + reopen).
	 * Only applies to ZipArchive which defers writes until close().
	 *
	 * @var int
	 */
	private $flush_interval = 50;

	/**
	 * Buffer of files waiting to be added to PclZip in a single batch.
	 *
	 * PclZip re-reads and re-writes the entire archive on every add() call,
	 * making per-file additions O(n²) on archive size. By buffering files and
	 * grouping them by directory, we call add() once per directory group per
	 * flush — dramatically reducing I/O on large archives.
	 *
	 * Each entry: [ 'file_path' => string, 'remove_path' => string, 'add_path' => string ]
	 *
	 * @var array
	 */
	private $pclzip_buffer = [];

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->use_zip_archive = class_exists( '\ZipArchive' );
	}

	/**
	 * Check if ZipArchive is available.
	 *
	 * @return bool
	 */
	public static function is_zip_archive_available() {
		return class_exists( '\ZipArchive' );
	}

	/**
	 * Get the library being used.
	 *
	 * @return string 'ZipArchive' or 'PclZip'
	 */
	public function get_library_name() {
		return $this->use_zip_archive ? 'ZipArchive' : 'PclZip';
	}

	/**
	 * Create a new empty archive file.
	 *
	 * @param string $path Path to the archive file.
	 * @return bool True on success, false on failure.
	 */
	public function create( $path ) {
		$this->archive_path = $path;

		if ( $this->use_zip_archive ) {
			$this->archive = new \ZipArchive();
			$result = $this->archive->open( $path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE );
			if ( $result === true ) {
				$this->archive->close();
				$this->is_open = false;
				return true;
			}
			Logger::log( 'ZipArchive create failed with error code: ' . $result );
			return false;
		}

		// PclZip fallback - ensure the library is loaded
		$this->load_pclzip();

		// Write a minimal valid empty ZIP file directly.
		// PclZip's create() + delete() approach corrupts the archive when the
		// last entry is removed, leaving an invalid file that causes
		// "Unable to find End of Central Dir Record signature" on subsequent adds.
		// A valid empty ZIP is just the 22-byte End of Central Directory record.
		$empty_zip = pack( 'VvvvvVVv',
			0x06054b50, // End of central directory signature
			0,          // Number of this disk
			0,          // Disk where central directory starts
			0,          // Number of central directory records on this disk
			0,          // Total number of central directory records
			0,          // Size of central directory (bytes)
			0,          // Offset of start of central directory
			0           // Comment length
		);

		if ( false === file_put_contents( $path, $empty_zip ) ) {
			Logger::log( 'PclZip create failed: could not write empty ZIP file to ' . $path );
			return false;
		}

		$this->is_open = false;
		return true;
	}

	/**
	 * Open an existing archive file for appending.
	 *
	 * @param string $path Path to the archive file.
	 * @param bool   $create_if_not_exists Whether to create the archive if it doesn't exist.
	 * @return bool True on success, false on failure.
	 */
	public function open( $path, $create_if_not_exists = true ) {
		$this->archive_path = $path;

		if ( $this->use_zip_archive ) {
			$this->archive = new \ZipArchive();

			if ( file_exists( $path ) ) {
				$file_size = filesize( $path );

				// Skip CHECKCONS for large archives to avoid scanning the
				// entire file on every open.  CHECKCONS verifies the central
				// directory against local headers which is O(n) on archive
				// size — for a multi-GB archive this alone can exceed the
				// PHP time limit and stall the migration.
				if ( $file_size < $this->large_archive_threshold ) {
					$result = $this->archive->open( $path, \ZipArchive::CHECKCONS );
					if ( $result !== true ) {
						Logger::log( 'ZipArchive CHECKCONS failed (error code: ' . $result . '), trying without CHECKCONS' );
						$result = $this->archive->open( $path );
					}
				} else {
					// Open directly without CHECKCONS for large files.
					$result = $this->archive->open( $path );
				}

				if ( $result === true ) {
					$this->is_open = true;
					return true;
				}

				Logger::log( 'ZipArchive open failed for existing archive (error code: ' . $result . '): ' . $path );
				return false;
			}

			// Archive does not exist yet.
			if ( ! $create_if_not_exists ) {
				Logger::log( 'ZipArchive open failed: file does not exist and create_if_not_exists is false' );
				return false;
			}
			$result = $this->archive->open( $path, \ZipArchive::CREATE );

			if ( $result === true ) {
				$this->is_open = true;
				return true;
			}

			Logger::log( 'ZipArchive open failed with error code: ' . $result );
			return false;
		}

		// PclZip fallback
		$this->load_pclzip();

		if ( ! file_exists( $path ) ) {
			if ( ! $create_if_not_exists ) {
				Logger::log( 'PclZip open failed: file does not exist and create_if_not_exists is false' );
				return false;
			}
			// Create the archive first
			if ( ! $this->create( $path ) ) {
				return false;
			}
		}

		// Validate that the archive is a readable ZIP before marking as open.
		// A corrupt file (e.g. from a previous failed write) would cause every
		// subsequent add() to fail with "End of Central Dir Record" errors.
		$this->archive = new \PclZip( $path );

		$properties = $this->archive->properties();
		if ( $properties === 0 ) {
			Logger::log( 'PclZip open: archive is corrupt or unreadable, recreating: ' . $path . ' — ' . $this->archive->errorInfo( true ) );

			// Attempt to recreate a valid empty archive so the export can continue.
			if ( ! $this->create( $path ) ) {
				Logger::log( 'PclZip open: failed to recreate archive' );
				return false;
			}

			$this->archive = new \PclZip( $path );
		}

		$this->is_open = true;
		return true;
	}

	/**
	 * Add a file to the archive.
	 *
	 * Uses ZipArchive::addFile() which streams from disk (deferred write)
	 * instead of reading the entire file into PHP memory. This prevents
	 * out-of-memory crashes on large media files (videos, PDFs, etc.).
	 *
	 * The archive is flushed (closed and reopened) every
	 * {@see $flush_interval} files to commit deferred writes to disk,
	 * so progress is not lost if the PHP process dies unexpectedly.
	 *
	 * @param string $file_path    Absolute path to the file to add.
	 * @param string $archive_path Path within the archive (relative path).
	 * @return bool True on success, false on failure.
	 */
	public function addFile( $file_path, $archive_path ) {
		if ( ! $this->is_open ) {
			Logger::log( 'Cannot add file: archive is not open' );
			return false;
		}

		if ( ! file_exists( $file_path ) ) {
			Logger::log( 'Cannot add file: file does not exist: ' . $file_path );
			return false;
		}

		if ( $this->use_zip_archive ) {
			// Use native addFile() which streams from disk — the file
			// contents are NOT loaded into PHP memory, avoiding OOM on
			// large files (videos, media, database dumps, etc.).
			$result = $this->archive->addFile( $file_path, $archive_path );

			if ( ! $result ) {
				Logger::log( 'ZipArchive addFile failed for: ' . $file_path );
				return false;
			}

			$this->unflushed_count++;

			// Periodically flush (close + reopen) to commit deferred
			// writes to disk and persist progress.
			if ( $this->unflushed_count >= $this->flush_interval ) {
				$this->flush();
			}

			return true;
		}

		// PclZip fallback — buffer the file for batch addition.
		// PclZip re-reads/rewrites the entire archive on every add() call,
		// so we collect files and add them in batches to avoid O(n²) I/O.
		$this->pclzip_buffer[] = [
			'file_path'   => $file_path,
			'remove_path' => dirname( $file_path ),
			'add_path'    => dirname( $archive_path ),
		];

		$this->unflushed_count++;

		if ( $this->unflushed_count >= $this->flush_interval ) {
			return $this->flush();
		}

		return true;
	}

	/**
	 * Add a large file to the archive without reading it entirely into memory.
	 *
	 * Uses ZipArchive::addFile() (deferred write) followed by an immediate
	 * flush (close + reopen) so the entry is persisted to disk without
	 * requiring the whole file contents in PHP memory.
	 *
	 * @param string $file_path    Absolute path to the file to add.
	 * @param string $archive_path Path within the archive (relative path).
	 * @return bool True on success, false on failure.
	 */
	public function addLargeFile( $file_path, $archive_path ) {
		if ( ! $this->is_open ) {
			Logger::log( 'Cannot add large file: archive is not open' );
			return false;
		}

		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			Logger::log( 'Cannot add large file: file does not exist or is not readable: ' . $file_path );
			return false;
		}

		if ( $this->use_zip_archive ) {
			// Flush any pending writes first so the archive on disk is up to date
			// before we attempt the large file addition.
			if ( $this->unflushed_count > 0 ) {
				$this->flush();
			}

			$result = $this->archive->addFile( $file_path, $archive_path );

			if ( ! $result ) {
				Logger::log( 'ZipArchive addFile (large) failed for: ' . $file_path );
				return false;
			}

			// Flush immediately so the deferred write is committed to disk.
			if ( ! $this->flush() ) {
				Logger::log( 'ZipArchive flush after addFile (large) failed for: ' . $file_path );
				// flush() already restored the archive backup, so existing
				// data is preserved even though this large file was lost.
				return false;
			}

			return true;
		}

		// PclZip fallback — PclZip reads from disk directly, no memory issue.
		return $this->addFile( $file_path, $archive_path );
	}

	/**
	 * Add a file to the archive from a string.
	 *
	 * @param string $archive_path Path within the archive (relative path).
	 * @param string $content      Content to add.
	 * @return bool True on success, false on failure.
	 */
	public function addFromString( $archive_path, $content ) {
		if ( ! $this->is_open ) {
			Logger::log( 'Cannot add from string: archive is not open' );
			return false;
		}

		if ( $this->use_zip_archive ) {
			return $this->archive->addFromString( $archive_path, $content );
		}

		// PclZip fallback - write to temp file with the correct basename
		// so the entry in the archive has the right filename.
		$temp_dir = wp_tempnam( 'pclzip_' );
		if ( ! $temp_dir ) {
			Logger::log( 'PclZip addFromString failed: could not create temp file' );
			return false;
		}

		// wp_tempnam creates a file; replace it with a directory + correctly named file.
		@unlink( $temp_dir );
		$temp_dir = $temp_dir . '_dir';
		@mkdir( $temp_dir, 0755, true );

		$temp_file = $temp_dir . DIRECTORY_SEPARATOR . basename( $archive_path );
		file_put_contents( $temp_file, $content );

		$archive_dir = dirname( $archive_path );
		$add_path    = ( $archive_dir === '.' || $archive_dir === '' ) ? '' : $archive_dir;

		$result = $this->archive->add(
			$temp_file,
			PCLZIP_OPT_REMOVE_PATH, $temp_dir,
			PCLZIP_OPT_ADD_PATH, $add_path
		);

		@unlink( $temp_file );
		@rmdir( $temp_dir );

		if ( $result === 0 ) {
			Logger::log( 'PclZip addFromString failed: ' . $this->archive->errorInfo( true ) );
			return false;
		}

		return true;
	}

	/**
	 * Flush pending writes to disk.
	 *
	 * For ZipArchive: closes and reopens the archive to commit deferred
	 * writes.
	 *
	 * For PclZip: writes all buffered files in a single add() call per
	 * directory group, dramatically reducing I/O compared to one add()
	 * per file.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function flush() {
		if ( ! $this->is_open ) {
			return true;
		}

		if ( $this->use_zip_archive ) {
			// For large archives, skip the expensive backup copy to avoid
			// I/O bottlenecks that can consume the entire request timeout
			// and stall the migration (e.g. a 2 GB archive on shared hosting).
			$is_large = file_exists( $this->archive_path ) && filesize( $this->archive_path ) >= $this->large_archive_threshold;
			$backup_path = $this->archive_path . '.bak';

			if ( ! $is_large ) {
				// Back up the archive before closing so we can recover if close fails.
				@copy( $this->archive_path, $backup_path );
			}

			if ( ! $this->archive->close() ) {
				Logger::log( 'ZipArchive flush (close) failed' );

				if ( ! $is_large && file_exists( $backup_path ) ) {
					Logger::log( 'Restoring backup after flush failure' );
					@copy( $backup_path, $this->archive_path );
					@unlink( $backup_path );
				}

				// Try to reopen the archive so subsequent
				// addFile() calls still work in the current session.
				$this->archive = new \ZipArchive();
				$result = $this->archive->open( $this->archive_path );
				if ( $result !== true ) {
					Logger::log( 'ZipArchive flush: reopen after restore failed (error code: ' . $result . ')' );
					$this->is_open = false;
				}

				// The unflushed entries were lost, but existing data is preserved.
				$this->unflushed_count = 0;
				return false;
			}

			// Close succeeded — remove the backup if one was made.
			if ( ! $is_large && file_exists( $backup_path ) ) {
				@unlink( $backup_path );
			}

			// Reopen without CHECKCONS — we just wrote this file so it is
			// known-good.  CHECKCONS reads the entire archive to verify the
			// central directory, which is extremely slow on large files.
			$this->archive = new \ZipArchive();
			$result = $this->archive->open( $this->archive_path );
			if ( $result !== true ) {
				Logger::log( 'ZipArchive flush (reopen) failed with error code: ' . $result );
				$this->is_open = false;
				return false;
			}

			$this->unflushed_count = 0;

			return true;
		}

		// PclZip: flush the buffered files in batches grouped by directory.
		return $this->flush_pclzip_buffer();
	}

	/**
	 * Flush buffered PclZip files to the archive.
	 *
	 * Groups files by their (REMOVE_PATH, ADD_PATH) pair so that all files
	 * sharing the same source/archive directory are added in one PclZip
	 * add() call. This avoids the O(n²) penalty of re-reading and
	 * re-writing the entire archive for every single file.
	 *
	 * @return bool True on success, false on failure.
	 */
	private function flush_pclzip_buffer() {
		if ( empty( $this->pclzip_buffer ) ) {
			$this->unflushed_count = 0;
			return true;
		}

		// Group buffered files by (remove_path, add_path).
		$groups = [];
		foreach ( $this->pclzip_buffer as $entry ) {
			$key = $entry['remove_path'] . '||' . $entry['add_path'];
			$groups[ $key ][] = $entry;
		}

		$success = true;

		foreach ( $groups as $entries ) {
			$remove_path = $entries[0]['remove_path'];
			$add_path    = $entries[0]['add_path'];

			// Collect file paths for this group.
			$file_list = [];
			foreach ( $entries as $entry ) {
				$file_list[] = $entry['file_path'];
			}

			// PclZip accepts an array of file paths (avoids issues with
			// commas inside file paths when using a comma-separated string).
			$result = $this->archive->add(
				$file_list,
				PCLZIP_OPT_REMOVE_PATH, $remove_path,
				PCLZIP_OPT_ADD_PATH, $add_path
			);

			if ( $result === 0 ) {
				Logger::log( 'PclZip batch addFile failed (' . count( $file_list ) . ' files): ' . $this->archive->errorInfo( true ) );
				$success = false;
			}
		}

		$this->pclzip_buffer   = [];
		$this->unflushed_count = 0;

		return $success;
	}

	/**
	 * Get the number of entries in the archive (ZipArchive only).
	 *
	 * @return int Number of entries, or -1 if not available.
	 */
	public function getEntryCount() {
		if ( $this->use_zip_archive && $this->archive instanceof \ZipArchive ) {
			return $this->archive->count();
		}

		return -1;
	}

	/**
	 * Close the archive.
	 *
	 * Flushes any remaining buffered PclZip files before closing.
	 *
	 * @return bool True on success, false on failure.
	 */
	public function close() {
		if ( ! $this->is_open ) {
			return true;
		}

		// Flush any remaining PclZip buffered files before closing.
		if ( ! $this->use_zip_archive && ! empty( $this->pclzip_buffer ) ) {
			$this->flush_pclzip_buffer();
		}

		$this->is_open         = false;
		$this->unflushed_count = 0;

		if ( $this->use_zip_archive ) {
			$is_large = file_exists( $this->archive_path ) && filesize( $this->archive_path ) >= $this->large_archive_threshold;
			$backup_path = $this->archive_path . '.bak';

			if ( ! $is_large ) {
				// Back up before final close to protect against corruption.
				@copy( $this->archive_path, $backup_path );
			}

			$result = $this->archive->close();

			if ( $result ) {
				if ( ! $is_large && file_exists( $backup_path ) ) {
					@unlink( $backup_path );
				}
			} else {
				Logger::log( 'ZipArchive close failed' );
				if ( ! $is_large && file_exists( $backup_path ) ) {
					Logger::log( 'Restoring backup after close failure' );
					@copy( $backup_path, $this->archive_path );
					@unlink( $backup_path );
				}
			}

			return $result;
		}

		// PclZip doesn't need explicit close
		return true;
	}

	/**
	 * Load the PclZip library from WordPress.
	 *
	 * Sets PCLZIP_TEMPORARY_DIR to a writable directory before loading the
	 * library so that PclZip temp files are not created in the (often
	 * unwritable) current working directory.
	 *
	 * @return void
	 */
	private function load_pclzip() {
		if ( ! defined( 'PCLZIP_TEMPORARY_DIR' ) ) {
			$upload_dir = wp_upload_dir();
			$temp_dir   = trailingslashit( $upload_dir['basedir'] );
			if ( is_writable( $temp_dir ) ) {
				define( 'PCLZIP_TEMPORARY_DIR', $temp_dir );
			}
		}

		if ( ! class_exists( '\PclZip' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-pclzip.php';
		}
	}

	/**
	 * Get the last error message.
	 *
	 * @return string
	 */
	public function getError() {
		if ( $this->use_zip_archive && $this->archive instanceof \ZipArchive ) {
			return $this->archive->getStatusString();
		}

		if ( ! $this->use_zip_archive && $this->archive instanceof \PclZip ) {
			return $this->archive->errorInfo( true );
		}

		return 'Unknown error';
	}
}

<?php

namespace Simply_Static\Backup\ThirdParty\servmask\backup;

use Simply_Static\Backup\Backup;
use Simply_Static\Backup\Helper;
use Simply_Static\Backup\Logs\Logger;
use Simply_Static\Backup\Status;
use Simply_Static\Backup\Zip_Archive_Wrapper;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

class Backup_Themes {

	public static function execute( $params = [], $task_id = 'themes' ) {

		// Set archive bytes offset
		if ( isset( $params['archive_bytes_offset'] ) ) {
			$archive_bytes_offset = (int) $params['archive_bytes_offset'];
		} else {
			$archive_bytes_offset = Helper::getArchiveBytes();
		}

		// Set file bytes offset
		if ( isset( $params['file_bytes_offset'] ) ) {
			$file_bytes_offset = (int) $params['file_bytes_offset'];
		} else {
			$file_bytes_offset = 0;
		}

		// Set themes bytes offset
		if ( isset( $params['themes_bytes_offset'] ) ) {
			$themes_bytes_offset = (int) $params['themes_bytes_offset'];
		} else {
			$themes_bytes_offset = 0;
		}

		// Get processed files size
		if ( isset( $params['processed_files_size'] ) ) {
			$processed_files_size = (int) $params['processed_files_size'];
		} else {
			$processed_files_size = 0;
		}

		// Get total themes files size
		if ( isset( $params['total_themes_files_size'] ) ) {
			$total_themes_files_size = (int) $params['total_themes_files_size'];
		} else {
			$total_themes_files_size = 1;
		}

		// Get total themes files count
		if ( isset( $params['total_themes_files_count'] ) ) {
			$total_themes_files_count = (int) $params['total_themes_files_count'];
		} else {
			$total_themes_files_count = 1;
		}

  // Set batch size for processing from settings (default 250 files per request)
		$settings   = get_option( 'sss_backup_migrate', [] );
  $batch_size = isset( $settings['batch_size'] ) ? absint( $settings['batch_size'] ) : 250;
		$batch_size = max( 1, $batch_size ); // Ensure minimum of 1
		$batch_size = apply_filters( 'sssbm_theme_batch_size', $batch_size );

		// Get memory limit and calculate thresholds
		$memory_limit     = self::get_memory_limit();
		$memory_threshold = $memory_limit * 0.9; // 90% threshold

		// Get processed files count in current session
		if ( isset( $params['processed_files_count'] ) ) {
			$processed_files_count = (int) $params['processed_files_count'];
		} else {
			$processed_files_count = 0;
		}

		// What percent of files have we processed?
		$progress = (int) min( ( $processed_files_size / $total_themes_files_size ) * 100, 100 );

		// Set progress
		/* translators: 1: Number of files, 2: Progress. */
		Status::info( sprintf( __( 'Backing up %1$d theme files...<br />%2$d%% complete', 'sss-backup-migrate' ), $total_themes_files_count, $progress ), 'ssse-themes' );

		// Flag to hold if all files have been processed (end of CSV reached)
		$completed = true;

		// Start time
		$start = microtime( true );

		// Counter for files processed in current batch
		$current_batch_count = 0;

		// Track files that could not be added to the archive
		$skipped_files = 0;

		// Track entries before this batch for verification
		$entries_before = 0;
		$entries_added  = 0;

		// Get themes list file
		$themes_list = @fopen( Backup::get_themes_list_path(), 'r' );

		// Set the file pointer at the current index
		if ( fseek( $themes_list, $themes_bytes_offset ) !== - 1 ) {

			// Acquire ZIP file lock to prevent concurrent access
			$zip_lock = Helper::waitForZipLock( 30 );
			if ( ! $zip_lock ) {
				Logger::log( 'Failed to acquire ZIP lock for themes export' );
				@fclose( $themes_list );
				$params['completed']  = false;
				$params['_skip_save'] = true;

				return $params;
			}

			// Open the archive file for writing using wrapper (supports ZipArchive and PclZip fallback)
			$archive     = new Zip_Archive_Wrapper();
			$backup_path = Backup::get_backup_path();

			// Open the archive (creates if not exists)
			if ( ! $archive->open( $backup_path ) ) {
				Logger::log( 'Failed to open ZIP archive for themes export: ' . $archive->getError() );
				Helper::releaseZipLock( $zip_lock );
				@fclose( $themes_list );
				$params['completed'] = false;

				return $params;
			}

			$entries_before = $archive->getEntryCount();

			// Loop over files
			while ( list( $file_abspath, $file_relpath, $file_size, $file_mtime ) = Helper::getcsv( $themes_list ) ) {

				// Check if we've reached the batch limit
				if ( $current_batch_count >= $batch_size ) {
					Logger::log( 'Batch limit reached. Processed ' . $current_batch_count . ' files in this batch.' );
					$completed = false;
					break;
				}

				// Check memory usage before processing each file (safety check)
				$current_memory = memory_get_usage( true );
				if ( $current_memory >= $memory_threshold ) {
					Logger::log( 'Memory threshold reached. Current: ' . self::format_bytes( $current_memory ) . ', Threshold: ' . self::format_bytes( $memory_threshold ) );
					$completed = false;
					break;
				}

				// Check if file exists before adding to archive
				if ( file_exists( $file_abspath ) ) {
					if ( $archive->addFile( $file_abspath, self::get_formatted_path( 'themes' . DIRECTORY_SEPARATOR . $file_relpath ) ) ) {
						$entries_added++;
					} else {
						Logger::log( 'Warning: Failed to add theme file to archive: ' . $file_abspath );
						$skipped_files++;
					}
				} else {
					Logger::log( 'Warning: Theme file does not exist, skipping: ' . $file_abspath );
					$skipped_files++;
				}

				// Always advance the CSV offset so we never get stuck
				// retrying a permanently failed file.
				$themes_bytes_offset = ftell( $themes_list );

				// Increment processed files size and count
				$processed_files_size += $file_size;
				$processed_files_count ++;
				$current_batch_count ++;

				// What percent of files have we processed?
				$progress = (int) min( ( $processed_files_size / $total_themes_files_size ) * 100, 100 );

				// Set progress
				/* translators: 1: Number of files, 2: Progress. */
				Status::info( sprintf( __( 'Backing up %1$d theme files...<br />%2$d%% complete', 'sss-backup-migrate' ), $total_themes_files_count, $progress ), 'ssse-themes' );

				// More than 10 seconds have passed, break and do another request
				if ( ( $timeout = apply_filters( 'sssbm_completed_timeout', 10 ) ) ) {
					if ( ( microtime( true ) - $start ) > $timeout ) {
						$completed = false;
						break;
					}
				}
			}

 		// Flush deferred writes to disk before saving progress.
			if ( ! $archive->flush() ) {
				Logger::log( 'Warning: flush failed for themes batch — entries from this batch may not have been persisted.' );
			}

			// Persist progress to DB before closing the archive.
			if ( $current_batch_count > 0 ) {
				$progress_params = [
					'archive_bytes_offset'          => $archive_bytes_offset,
					'file_bytes_offset'             => $file_bytes_offset,
					'themes_bytes_offset'           => $themes_bytes_offset,
					'processed_files_size'          => $processed_files_size,
					'processed_files_count'         => $processed_files_count,
					'total_themes_files_size'       => $total_themes_files_size,
					'total_themes_files_count'      => $total_themes_files_count,
					'completed'                     => $completed,
				];
				Backup::save_export_param( $task_id, $progress_params );
			}

			// Verify archive integrity after closing
			$entries_after = $archive->getEntryCount();

			// Close the archive file
			$archive->close();

			if ( $entries_before >= 0 && $entries_after >= 0 ) {
				$expected_new = $entries_added;
				$actual_new   = $entries_after - $entries_before;
				if ( $actual_new < $expected_new ) {
					Logger::log( sprintf(
						'Warning: Archive integrity check — expected %d new entries but only %d were written (before: %d, after: %d)',
						$expected_new, $actual_new, $entries_before, $entries_after
					) );
				}
			}

			if ( $skipped_files > 0 ) {
				Logger::log( sprintf( 'Warning: %d theme file(s) were skipped in this batch.', $skipped_files ) );
			}

			// Release ZIP file lock
			Helper::releaseZipLock( $zip_lock );
		}

		// Set archive bytes offset
		$params['archive_bytes_offset'] = $archive_bytes_offset;

		// Set file bytes offset
		$params['file_bytes_offset'] = $file_bytes_offset;

		// Set themes bytes offset
		$params['themes_bytes_offset'] = $themes_bytes_offset;

		// Set processed files size
		$params['processed_files_size'] = $processed_files_size;

		// Set total themes files size
		$params['total_themes_files_size'] = $total_themes_files_size;

		// Set total themes files count
		$params['total_themes_files_count'] = $total_themes_files_count;

		// Set completed flag
		$params['completed'] = $completed;

		// Set processed files count
		$params['processed_files_count'] = $processed_files_count;

		if ( $completed ) {
			Status::success( __( 'Backed up all theme files...<br />100% complete', 'sss-backup-migrate' ), 'ssse-themes' );
		}

		// Close the themes list file
		@fclose( $themes_list );

		return $params;
	}

	/**
	 * Get the memory limit in bytes
	 *
	 * @return int Memory limit in bytes
	 */
	private static function get_memory_limit() {
		$memory_limit = ini_get( 'memory_limit' );

		if ( $memory_limit == - 1 ) {
			// No memory limit
			return PHP_INT_MAX;
		}

		// Convert memory limit to bytes
		$memory_limit = trim( $memory_limit );
		$last_char    = strtolower( $memory_limit[ strlen( $memory_limit ) - 1 ] );
		$memory_limit = (int) $memory_limit;

		switch ( $last_char ) {
			case 'g':
				$memory_limit *= 1024;
			case 'm':
				$memory_limit *= 1024;
			case 'k':
				$memory_limit *= 1024;
		}

		return $memory_limit;
	}

	public static function get_formatted_path( $path ) {

		$path_array = explode( DIRECTORY_SEPARATOR, $path );
		if ( ! in_array( 'themes', $path_array ) ) {
			$path_array = array_merge( [ 'themes' ], $path_array );
		}

		if ( ! in_array( 'wp-content', $path_array ) ) {
			$path_array = array_merge( [ 'wp-content' ], $path_array );
		}

		return implode( '/', $path_array );
	}

	/**
	 * Format bytes into a human-readable string.
	 *
	 * @param int $bytes Number of bytes.
	 *
	 * @return string Formatted string (e.g. "128.00 MB").
	 */
	private static function format_bytes( $bytes ) {
		if ( $bytes >= 1073741824 ) {
			return number_format( $bytes / 1073741824, 2 ) . ' GB';
		} elseif ( $bytes >= 1048576 ) {
			return number_format( $bytes / 1048576, 2 ) . ' MB';
		} elseif ( $bytes >= 1024 ) {
			return number_format( $bytes / 1024, 2 ) . ' KB';
		}

		return $bytes . ' bytes';
	}
}

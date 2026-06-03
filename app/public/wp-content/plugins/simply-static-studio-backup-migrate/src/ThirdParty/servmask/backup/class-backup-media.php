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

class Backup_Media {

	public static function execute( $params = [], $task_id = 'media' ) {

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

		// Set media bytes offset (prefer persisted option to avoid getting stuck)
		$persisted_media_offset = (int) get_option( 'sssbm_media_bytes_offset', 0 );
		if ( isset( $params['media_bytes_offset'] ) ) {
			$media_bytes_offset = max( (int) $params['media_bytes_offset'], $persisted_media_offset );
		} else {
			$media_bytes_offset = $persisted_media_offset;
		}

		// Get processed files size
		if ( isset( $params['processed_files_size'] ) ) {
			$processed_files_size = (int) $params['processed_files_size'];
		} else {
			$processed_files_size = 0;
		}

		// Get total media files size
		if ( isset( $params['total_media_files_size'] ) ) {
			$total_media_files_size = (int) $params['total_media_files_size'];
		} else {
			$total_media_files_size = 1;
		}

		// Get total media files count
		if ( isset( $params['total_media_files_count'] ) ) {
			$total_media_files_count = (int) $params['total_media_files_count'];
		} else {
			$total_media_files_count = 1;
		}

  // Set batch size for processing from settings (default 250 files per request)
		$settings   = get_option( 'sss_backup_migrate', [] );
  $batch_size = isset( $settings['batch_size'] ) ? absint( $settings['batch_size'] ) : 250;
		$batch_size = max( 1, $batch_size ); // Ensure minimum of 1
		$batch_size = apply_filters( 'sssbm_media_batch_size', $batch_size );

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
		$progress = (int) min( ( $processed_files_size / $total_media_files_size ) * 100, 100 );

		// Set progress
		/* translators: 1: Number of files, 2: Progress. */
		Status::info( sprintf( __( 'Backing up %1$d media files...<br />%2$d%% complete', 'sss-backup-migrate' ), $total_media_files_count, $progress ), 'ssse-media' );

		// Flag to hold if all files have been processed (end of CSV reached)
		$completed = true;

		// Counter for files processed in current batch
		$current_batch_count = 0;

		// Track files that could not be added to the archive
		$skipped_files = 0;

		// Track entries before this batch for verification
		$entries_before = 0;
		$entries_added  = 0;

		// Start time
		$start = microtime( true );

		// Get media list file
		$media_list_path = Backup::get_media_list_path();
		Logger::log( 'Media list path: ' . $media_list_path . ' (exists: ' . ( file_exists( $media_list_path ) ? 'yes' : 'no' ) . ')' );
		Logger::log( 'Media bytes offset: ' . $media_bytes_offset . ', Processed files: ' . $processed_files_count . ', Batch size: ' . $batch_size );
		$media_list = @fopen( $media_list_path, 'r' );

		if ( ! $media_list ) {
			Logger::log( 'Failed to open media list file: ' . $media_list_path );
			$params['completed'] = true;
			@fclose( $media_list );
			return $params;
		}

		// Set the file pointer at the current index
		if ( fseek( $media_list, $media_bytes_offset ) !== - 1 ) {

			Logger::log( 'Media list fseek successful. Starting ZIP operations...' );

			// Acquire ZIP file lock to prevent concurrent access
			$zip_lock = Helper::waitForZipLock( 30 );
			if ( ! $zip_lock ) {
				Logger::log( 'Failed to acquire ZIP lock for media export' );
				@fclose( $media_list );
				$params['completed']  = false;
				$params['_skip_save'] = true;

				return $params;
			}

			// Open the archive file for writing using wrapper (supports ZipArchive and PclZip fallback)
			$archive     = new Zip_Archive_Wrapper();
			$backup_path = Backup::get_backup_path();

			// Open the archive (creates if not exists)
			if ( ! $archive->open( $backup_path ) ) {
				Logger::log( 'Failed to open ZIP archive for media export: ' . $archive->getError() );
				Helper::releaseZipLock( $zip_lock );
				@fclose( $media_list );
				$params['completed'] = false;

				return $params;
			}

			$entries_before = $archive->getEntryCount();
			Logger::log( 'Archive opened. Entries before: ' . $entries_before . '. Time since start: ' . round( microtime( true ) - $start, 2 ) . 's' );

			// Loop over files
			while ( list( $file_abspath, $file_relpath, $file_size, $file_mtime ) = Helper::getcsv( $media_list ) ) {
				// Advance and persist offset immediately so we never re-read the same line on the next run
				$media_bytes_offset = ftell( $media_list );
				update_option( 'sssbm_media_bytes_offset', (int) $media_bytes_offset, false );

				// Optionally skip very large files based on a filter-configured size limit (in bytes)
				$max_file_size = apply_filters( 'sssbm_media_max_file_size', 0 ); // 0 disables size-based skipping
				if ( $max_file_size > 0 && $file_size > $max_file_size ) {
					Logger::log( 'Skipping large media file (' . self::format_bytes( $file_size ) . '): ' . $file_abspath );
					// Adjust totals to reflect exclusion so progress is accurate
					$total_media_files_size = max( 1, $total_media_files_size - $file_size );
					if ( $total_media_files_count > 1 ) {
						$total_media_files_count--;
					}
					$current_batch_count++;
					// Continue with next file without attempting to add to the archive
					continue;
				}

				// Check memory usage before processing each file (safety check)
				$current_memory = memory_get_usage( true );
				if ( $current_memory >= $memory_threshold ) {
					Logger::log( 'Memory threshold reached. Current: ' . self::format_bytes( $current_memory ) . ', Threshold: ' . self::format_bytes( $memory_threshold ) );
					$completed = false;
					break;
				}

				// Ensure the file exists before adding
				if ( ! file_exists( $file_abspath ) ) {
					Logger::log( 'Warning: Media file does not exist, skipping: ' . $file_abspath );
					$skipped_files++;
				} else {
					$zip_path = self::get_formatted_path( 'uploads' . DIRECTORY_SEPARATOR . $file_relpath );
					$added    = $archive->addFile( $file_abspath, $zip_path );
					if ( $added ) {
						$file_bytes_offset = 0;
						$entries_added++;
						$processed_files_size += $file_size;
						$processed_files_count++;
					} else {
						Logger::log( 'Warning: Failed to add media file to archive: ' . $file_abspath . ' -> ' . $zip_path );
						$skipped_files++;
					}
				}

				$current_batch_count++;

				// What percent of files have we processed?
				$progress = (int) min( ( $processed_files_size / $total_media_files_size ) * 100, 100 );

				// Set progress
				/* translators: 1: Number of files, 2: Progress. */
				Status::info( sprintf( __( 'Backing up %1$d media files...<br />%2$d%% complete', 'sss-backup-migrate' ), $total_media_files_count, $progress ), 'ssse-media' );

 				// Check if we've reached the batch limit AFTER processing this file
				if ( $current_batch_count >= $batch_size ) {
					Logger::log( 'Batch limit reached. Processed ' . $current_batch_count . ' files in this batch.' );
					$completed = false;
					break;
				}

				// More than 10 seconds have passed, break and do another request
				if ( ( $timeout = apply_filters( 'sssbm_completed_timeout', 10 ) ) ) {
					if ( ( microtime( true ) - $start ) > $timeout ) {
						Logger::log( 'Timeout reached after ' . round( microtime( true ) - $start, 2 ) . 's. Processed ' . $current_batch_count . ' files in this batch.' );
						$completed = false;
						break;
					}
				}
			}

			Logger::log( 'Batch finished. Files processed: ' . $current_batch_count . '. Flushing archive...' );

 			// Flush deferred writes to disk before saving progress.
			if ( ! $archive->flush() ) {
				Logger::log( 'Warning: flush failed for media batch — entries from this batch may not have been persisted.' );
			}

			// Persist progress to DB before closing the archive.
			if ( $current_batch_count > 0 ) {
				$progress_params = [
					'archive_bytes_offset'      => $archive_bytes_offset,
					'file_bytes_offset'         => $file_bytes_offset,
					'media_bytes_offset'        => $media_bytes_offset,
					'processed_files_size'      => $processed_files_size,
					'processed_files_count'     => $processed_files_count,
					'total_media_files_size'    => $total_media_files_size,
					'total_media_files_count'   => $total_media_files_count,
					'completed'                 => $completed,
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
				Logger::log( sprintf( 'Warning: %d media file(s) were skipped in this batch.', $skipped_files ) );
			}

			// Release ZIP file lock
			Helper::releaseZipLock( $zip_lock );
		}

		// Set archive bytes offset
		$params['archive_bytes_offset'] = $archive_bytes_offset;

		// Set file bytes offset
		$params['file_bytes_offset'] = $file_bytes_offset;

		// Set media bytes offset
		$params['media_bytes_offset'] = $media_bytes_offset;

		// Set processed files size
		$params['processed_files_size'] = $processed_files_size;

		// Set total media files size
		$params['total_media_files_size'] = $total_media_files_size;

		// Set total media files count
		$params['total_media_files_count'] = $total_media_files_count;

		// Set processed files count
		$params['processed_files_count'] = $processed_files_count;

		// Set completed flag
		$params['completed'] = $completed;

		if ( $completed ) {
			Status::success( __( 'Backed up all media files...<br />100% complete', 'sss-backup-migrate' ), 'ssse-media' );
		}
		// Close the media list file
		@fclose( $media_list );

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
		if ( ! in_array( 'uploads', $path_array ) ) {
			$path_array = array_merge( [ 'uploads' ], $path_array );
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

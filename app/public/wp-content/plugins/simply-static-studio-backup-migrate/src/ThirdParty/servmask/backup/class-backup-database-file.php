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

class Backup_Database_File {

	/**
	 * Maximum number of attempts before giving up on adding the database file.
	 */
	const MAX_RETRIES = 3;

	public static function execute( $params = [] ) {

		// Set exclude database
		if ( isset( $params['options']['no_database'] ) ) {
			return $params;
		}

		// Track retry attempts to prevent infinite loops
		$retry_count = isset( $params['database_file_retry_count'] ) ? (int) $params['database_file_retry_count'] : 0;

		// Get total database size
		$database_path = Backup::get_database_path();

		if ( ! file_exists( $database_path ) || ! is_readable( $database_path ) ) {
			Logger::log( 'Database file does not exist or is not readable: ' . $database_path );
			Status::error( __( 'Database backup failed — database dump file is missing.', 'sss-backup-migrate' ), 'ssse-database-file' );
			$params['completed'] = true;
			return $params;
		}

		$total_database_size = filesize( $database_path );

		// Set progress
		/* translators: Progress. */
		Status::info( __( 'Adding database to backup archive...', 'sss-backup-migrate' ), 'ssse-database-file' );

		// Open the archive file for writing using wrapper (supports ZipArchive and PclZip fallback)
		$archive = new Zip_Archive_Wrapper();
		if ( ! $archive->open( Backup::get_backup_path() ) ) {
			Logger::log( 'Failed to open ZIP archive for database file: ' . $archive->getError() );

			$retry_count++;
			$params['database_file_retry_count'] = $retry_count;

			if ( $retry_count >= self::MAX_RETRIES ) {
				Logger::log( 'Database file task exceeded maximum retries (' . self::MAX_RETRIES . '). Skipping.' );
				Status::error( __( 'Database backup failed — could not open backup archive after multiple attempts.', 'sss-backup-migrate' ), 'ssse-database-file' );
				$params['completed'] = true;
				return $params;
			}

			$params['completed'] = false;
			return $params;
		}

		// Use addLargeFile() to avoid reading the entire database dump into
		// PHP memory — the file can easily exceed the memory_limit on sites
		// with large databases (e.g. WPML, WooCommerce).
		Logger::log( 'Adding database file to archive (' . size_format( $total_database_size ) . '): ' . $database_path );

		if ( $archive->addLargeFile( $database_path, 'db.sql' ) ) {

			// Set progress
			Status::success( __( 'Database backed up.', 'sss-backup-migrate' ), 'ssse-database-file' );

			$params['completed'] = true;

		} else {

			$retry_count++;
			$params['database_file_retry_count'] = $retry_count;

			Logger::log( 'Failed to add database file to archive (attempt ' . $retry_count . ' of ' . self::MAX_RETRIES . ').' );

			if ( $retry_count >= self::MAX_RETRIES ) {
				Logger::log( 'Database file task exceeded maximum retries (' . self::MAX_RETRIES . '). Skipping.' );
				Status::error( __( 'Database backup failed — could not add database to archive after multiple attempts.', 'sss-backup-migrate' ), 'ssse-database-file' );
				$params['completed'] = true;
			} else {
				// Set progress
				Status::info( __( 'Retrying database archive step...', 'sss-backup-migrate' ), 'ssse-database-file' );
				$params['completed'] = false;
			}
		}

		// Close the archive file
		$archive->close();

		return $params;
	}
}

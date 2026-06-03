<?php

namespace Simply_Static\Backup\ThirdParty\servmask\backup;

use Simply_Static\Backup\Backup;
use Simply_Static\Backup\Logs\Logger;
use Simply_Static\Backup\Status;
use Simply_Static\Backup\Zip_Archive_Wrapper;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

class Backup_Archive {

	public static function execute( $params = [] ) {

		do_action( 'sssbm_status_export_start', $params );

		// Set progress
		Status::info( __( 'Creating backup file...', 'sss-backup-migrate' ), 'ssse-export-init' );

		if ( file_exists( Backup::get_backup_path() ) ) {
			@unlink( Backup::get_backup_path() );
		}

		// Create empty archive file using wrapper (supports ZipArchive and PclZip fallback)
		$archive = new Zip_Archive_Wrapper();
		Logger::log( 'Using ZIP library: ' . $archive->get_library_name() );

		if ( ! $archive->create( Backup::get_backup_path() ) ) {
			Logger::log( 'Failed to create backup archive: ' . $archive->getError() );
			throw new \Exception( 'Failed to create backup archive: ' . $archive->getError() );
		}

		// Set progress
		Status::success( __( 'Backup file created.', 'sss-backup-migrate' ), 'ssse-export-init' );

		return $params;
	}
}

<?php

namespace Simply_Static\Backup\ThirdParty\servmask\backup;

use Simply_Static\Backup\Backup;
use Simply_Static\Backup\Logs\Logger;
use Simply_Static\Backup\Status;
use Simply_Static\Backup\Zip_Archive_Wrapper;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

class Backup_Config_File {

	public static function execute( $params = [] ) {
		// Set package bytes offset 
		if ( get_option( 'sssbm_package_bytes_offset' ) ) {
			$package_bytes_offset = (int) get_option( 'sssbm_package_bytes_offset' );
		} else {
			$package_bytes_offset = 0;
		}

		// Get total package size
		if ( get_option( 'ssm_total_package_size' ) ) {
			$total_package_size = (int) get_option( 'ssm_total_package_size' );
		} else {
			$config_path = Backup::get_config_file_path();
			$total_package_size = file_exists( $config_path ) ? (int) filesize( $config_path ) : 0;
		}

		// What percent of package have we processed?
		$progress = $total_package_size > 0 ? (int) min( ( $package_bytes_offset / $total_package_size ) * 100, 100 ) : 0;

		// Set progress
		/* translators: Progress. */
		Status::info( sprintf( __( 'Backing up configuration...<br />%d%% complete', 'sss-backup-migrate' ), $progress ), 'ssm-config-file' );

		// Open the archive using wrapper (supports ZipArchive and PclZip fallback)
		$archive = new Zip_Archive_Wrapper();
		if ( ! $archive->open( Backup::get_backup_path() ) ) {
			Logger::log( 'Failed to open ZIP archive for config file: ' . $archive->getError() );
			$params['completed'] = false;
			return $params;
		}

		// Add config.json to archive
		if ( $archive->addFile( Backup::get_config_file_path(), 'config.json' ) ) {

			// Set progress
			Status::success( __( 'Configuration backed up.', 'sss-backup-migrate' ), 'ssm-config-file' );
			$params['completed'] = true;
		} else {

			// What percent of package have we processed?
			$progress = $total_package_size > 0 ? (int) min( ( $package_bytes_offset / $total_package_size ) * 100, 100 ) : 0;

			// Set progress
			/* translators: Progress. */
			Status::info( sprintf( __( 'Backing up configuration...<br />%d%% complete', 'sss-backup-migrate' ), $progress ), 'ssm-config-file' );

			$params['package_bytes_offset'] = $package_bytes_offset;

			// Set total package size
			$params['total_package_size'] = $total_package_size;

			// Set completed flag
			$params['completed'] = false;
		}

		// Close the archive file
		$archive->close();

		return $params;
	}
}

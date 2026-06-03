<?php

namespace Simply_Static\Backup\ThirdParty\servmask\backup;

use Simply_Static\Backup\Backup;
use Simply_Static\Backup\Status;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

class Backup_Init {

	public static function execute( $params = [] ) {
		$blog_id = null;

		do_action( 'sssbm_status_export_init', $params );

		Status::clear_messages();

		Backup::clear_all_export_files();

		// Set progress
		Status::info( __( 'Preparing backup...', 'sss-backup-migrate' ), 'ssse-export-init' );

		return $params;
	}
}

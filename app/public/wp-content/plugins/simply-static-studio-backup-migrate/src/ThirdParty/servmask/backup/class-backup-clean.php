<?php

namespace Simply_Static\Backup\ThirdParty\servmask\backup;

use Simply_Static\Backup\Backup;
use Simply_Static\Backup\Status;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

class Backup_Clean {

	public static function execute( $params = [] ) {

		// Delete storage files
		if ( isset( $params['sssbm_export_cancel'] ) ) {
			do_action( 'sssbm_status_export_canceled', $params );

			Status::info( __( 'Backup canceled.', 'sss-backup-migrate' ), 'ssse-clean' );
			Backup::clear_all_export_files();
		} else {
			Backup::set_as_completed();
			Status::success( __( 'Backup done.', 'sss-backup-migrate' ), 'ssse-clean' );

		}

		Backup::set_as_not_running();

		// Exit in console
		if ( defined( 'WP_CLI' ) ) {
			return $params;
		}

		return true;
	}
}

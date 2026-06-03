<?php

namespace Simply_Static\Backup\Queue\Tasks;

use Simply_Static\Backup\Backup;
use Simply_Static\Backup\ThirdParty\servmask\backup\Backup_Clean;

class Clean extends Backup_Task {

	public function run() {
		$params = Backup::get_export_params();

		$init = new Backup_Clean();

		$params = $init->execute( $params );

		return true;
	}
}
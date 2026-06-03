<?php

namespace Simply_Static\Backup\Queue\Tasks;

use Simply_Static\Backup\ThirdParty\servmask\backup\Backup_Config;

class Config extends Backup_Task {

	public function run() {
		// Include the functions file that defines the ai1wm_* functions
		require_once dirname(__FILE__, 3) . '/ThirdParty/servmask/backup/functions.php';

		// Call the static execute method
		Backup_Config::execute();

		return true;
	}
}

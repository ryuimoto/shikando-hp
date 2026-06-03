<?php

namespace Simply_Static\Backup\Queue\Tasks;

use Simply_Static\Backup\ThirdParty\servmask\backup\Backup_Config_File;

class Config_File extends Backup_Task {

	public function run() {
		$init = new Backup_Config_File();
		$init->execute();

		return true;
	}
}
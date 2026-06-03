<?php

namespace Simply_Static\Backup\Queue\Tasks;

use Simply_Static\Backup\ThirdParty\servmask\backup\Backup_Init;

class Init extends Backup_Task {

	public function run() {
		Backup_Init::execute();

		return true;
	}
}

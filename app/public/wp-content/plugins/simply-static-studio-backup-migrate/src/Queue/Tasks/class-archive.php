<?php

namespace Simply_Static\Backup\Queue\Tasks;

use Simply_Static\Backup\ThirdParty\servmask\backup\Backup_Archive;

class Archive extends Backup_Task {

	public function run() {
		$init = new Backup_Archive();
		$init->execute();

		return true;
	}
}
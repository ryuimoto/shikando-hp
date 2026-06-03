<?php

namespace Simply_Static\Backup\Queue\Tasks;

use Simply_Static\Backup\ThirdParty\servmask\backup\Backup_Database_File;

class Database_File extends Backup_Task {

	protected $longRunning = true;
	
	public function run() {
		$params = $this->get_task_params();

		$init = new Backup_Database_File();

		$params = $init->execute( $params );

		$this->save_task_params( $params );

		return isset( $params['completed'] ) && $params['completed'];
	}
}
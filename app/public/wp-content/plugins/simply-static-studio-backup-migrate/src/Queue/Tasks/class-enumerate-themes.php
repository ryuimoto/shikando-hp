<?php

namespace Simply_Static\Backup\Queue\Tasks;

use Simply_Static\Backup\ThirdParty\servmask\backup\Backup_Enumerate_Themes;

class Enumerate_Themes extends Backup_Task {

	public function run() {
		$params = $this->get_task_params();

		$init = new Backup_Enumerate_Themes();

		$params = $init->execute( $params );

		$this->save_task_params( $params );

		return isset( $params['completed'] ) && $params['completed'];
	}
}
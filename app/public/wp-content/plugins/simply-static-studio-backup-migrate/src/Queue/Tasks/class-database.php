<?php

namespace Simply_Static\Backup\Queue\Tasks;

use Simply_Static\Backup\Backup;
use Simply_Static\Backup\ThirdParty\servmask\backup\Backup_Database;

class Database extends Backup_Task {

	public function run() {
		$file_params = Backup::get_export_params();
		$file_params = $file_params['enumerate_tables'];
		$params      = $this->get_task_params();
		$params      = array_merge( $params, [
			'total_tables_count' => $file_params['total_tables_count']
		] );

		$init = new Backup_Database();

		$params = $init->execute( $params );

		$this->save_task_params( $params );

		return isset( $params['completed'] ) && $params['completed'];
	}
}

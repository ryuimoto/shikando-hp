<?php

namespace Simply_Static\Backup\Queue\Tasks;

use Simply_Static\Backup\Backup;
use Simply_Static\Backup\ThirdParty\servmask\backup\Backup_Content;

class Content extends Backup_Task {

	public function run() {
		$file_params = Backup::get_export_params();
		$file_params = $file_params['enumerate_content'];
		$params      = $this->get_task_params();
		$params      = array_merge( $params, [
			'total_content_files_size'  => $file_params['total_content_files_size'],
			'total_content_files_count' => $file_params['total_content_files_count'],
		] );

		$init = new Backup_Content();

		$params = $init->execute( $params, $this->get_id() );

		$this->save_task_params( $params );

		return isset( $params['completed'] ) && $params['completed'];
	}
}
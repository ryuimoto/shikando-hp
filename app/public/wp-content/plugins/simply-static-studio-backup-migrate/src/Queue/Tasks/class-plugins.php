<?php

namespace Simply_Static\Backup\Queue\Tasks;

use Simply_Static\Backup\Backup;
use Simply_Static\Backup\Logs\Logger;
use Simply_Static\Backup\ThirdParty\servmask\backup\Backup_Plugins;

class Plugins extends Backup_Task {

	protected $longRunning = true;
	
	public function run() {
		$file_params = Backup::get_export_params();
		$file_params = $file_params['enumerate_plugins'];
		$params      = $this->get_task_params();
		$params      = array_merge( $params, [
			'total_plugins_files_size'  => $file_params['total_plugins_files_size'],
			'total_plugins_files_count' => $file_params['total_plugins_files_count'],
		] );

		Logger::log( 'Starting plugins export' );
		Logger::log( print_r( $params, true ) );

		$init = new Backup_Plugins();

		$params = $init->execute( $params, $this->get_id() );

		Logger::log( 'Finished plugins export iteration' );
		Logger::log( print_r( $params, true ) );

		$this->save_task_params( $params );

		return isset( $params['completed'] ) && $params['completed'];
	}
}
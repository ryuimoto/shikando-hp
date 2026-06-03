<?php

namespace Simply_Static\Backup\CLI;

use Simply_Static\Backup\Backup;
use Simply_Static\Backup\Logs\Logger;
use Simply_Static\Backup\Queue\Exceptions\Retry_Exception;
use Simply_Static\Backup\Queue\Exceptions\Skippable_Exception;
use Simply_Static\Backup\Status;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP-CLI commands for Simply Static Studio Backup and Migrate
 */
class Backup_Command {
	/**
	 * Registers the WP-CLI command namespace.
	 */
	public static function register() {
		if ( defined( 'WP_CLI' ) && WP_CLI ) {
			\WP_CLI::add_command( 'sssbm backup', new self() );
		}
	}

	/**
	 * Run the full backup synchronously via CLI.
	 *
	 * ## EXAMPLES
	 *
	 *     wp sssbm backup run
	 */
	public function run( $args, $assoc_args ) {
		$this->run_full_backup();
	}

	/**
	 * Start the full backup and iterate through all tasks.
	 */
	protected function run_full_backup() {
		// Prepare environment similar to Backup::start_export() but synchronous
		Logger::clearLog();
		Status::clear_messages();
		Backup::set_as_not_completed();
		Backup::delete_export_params();
		Backup::set_as_running();

		\WP_CLI::log( 'Starting Simply Static Studio Backup and Migrate (CLI)…' );

		$tasks = Backup::get_tasks();
		foreach ( $tasks as $task ) {
			$this->run_single_task_internal( $task );
		}

		Backup::set_as_not_running();
		Backup::set_as_completed();
		\WP_CLI::success( 'Backup finished successfully.' );
	}

	/**
	 * Init task
	 */
	public function init( $args, $assoc_args ) { $this->run_single_task( 'init' ); }
	public function archive( $args, $assoc_args ) { $this->run_single_task( 'archive' ); }
	public function config( $args, $assoc_args ) { $this->run_single_task( 'config' ); }
	public function config_file( $args, $assoc_args ) { $this->run_single_task( 'config_file' ); }
	public function enumerate_content( $args, $assoc_args ) { $this->run_single_task( 'enumerate_content' ); }
	public function enumerate_media( $args, $assoc_args ) { $this->run_single_task( 'enumerate_media' ); }
	public function enumerate_plugins( $args, $assoc_args ) { $this->run_single_task( 'enumerate_plugins' ); }
	public function enumerate_themes( $args, $assoc_args ) { $this->run_single_task( 'enumerate_themes' ); }
	public function enumerate_tables( $args, $assoc_args ) { $this->run_single_task( 'enumerate_tables' ); }
	public function content( $args, $assoc_args ) { $this->run_single_task( 'content' ); }
	public function media( $args, $assoc_args ) { $this->run_single_task( 'media' ); }
	public function plugins( $args, $assoc_args ) { $this->run_single_task( 'plugins' ); }
	public function themes( $args, $assoc_args ) { $this->run_single_task( 'themes' ); }
	public function database( $args, $assoc_args ) { $this->run_single_task( 'database' ); }
	public function database_file( $args, $assoc_args ) { $this->run_single_task( 'database_file' ); }
	public function clean( $args, $assoc_args ) { $this->run_single_task( 'clean' ); }

	/**
	 * Wrapper to run a single task with WP-CLI messages
	 *
	 * @param string $task
	 */
	protected function run_single_task( $task ) {
		// When running a single task, ensure running flag set for consistency
		Backup::set_as_running();
		$this->run_single_task_internal( $task );
		Backup::set_as_not_running();
		\WP_CLI::success( sprintf( 'Task "%s" finished successfully.', $task ) );
	}

	/**
	 * Run a single task and handle exceptions similar to the background process
	 *
	 * @param string $task
	 */
	protected function run_single_task_internal( $task ) {
		$taskClass = '\\Simply_Static\\Backup\\Queue\\Tasks\\' . str_replace( ' ', '_', ucwords( str_replace( '_', ' ', $task ) ) );
		if ( ! class_exists( $taskClass ) ) {
			\WP_CLI::error( sprintf( 'Task class not found for "%s" (%s).', $task, $taskClass ) );
			return;
		}

		/** @var object $taskObj */
		$taskObj = new $taskClass();

		\WP_CLI::log( sprintf( 'Running task: %s', $task ) );

		$attempts = 0;
		$max_loops = 5; // Safety to avoid infinite loops for tasks returning false

		while ( $attempts < $max_loops ) {
			try {
				$done = $taskObj->perform();
				if ( $done ) {
					return; // Task finished
				}
				$attempts++;
				\WP_CLI::log( sprintf( 'Task "%s" not done yet, retrying (loop %d/%d)…', $task, $attempts, $max_loops ) );
				sleep( 1 );
			} catch ( Skippable_Exception $e ) {
				\WP_CLI::warning( sprintf( 'Task "%s" skipped: %s', $task, $e->getMessage() ) );
				return;
			} catch ( Retry_Exception $e ) {
				// Imitate retry handling
				if ( method_exists( $taskObj, 'incrementRetryCount' ) && method_exists( $taskObj, 'hasExceededMaxRetries' ) ) {
					$taskObj->incrementRetryCount();
					if ( $taskObj->hasExceededMaxRetries() ) {
						\WP_CLI::error( sprintf( 'Task "%s" exceeded maximum retries. Last error: %s', $task, $e->getMessage() ) );
						return;
					}
				}
				$attempts++;
				\WP_CLI::warning( sprintf( 'Retrying task "%s" due to error: %s (attempt %d)', $task, $e->getMessage(), $attempts ) );
				sleep( 1 );
			} catch ( \Exception $e ) {
				\WP_CLI::error( sprintf( 'Task "%s" failed: %s', $task, $e->getMessage() ) );
				return;
			}
		}

		// If we exit the loop without finishing
		\WP_CLI::error( sprintf( 'Task "%s" did not complete after multiple attempts.', $task ) );
	}
}

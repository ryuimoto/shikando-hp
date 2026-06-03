<?php

namespace Simply_Static\Backup\Queue;

use Simply_Static\Backup\Backup;
use Simply_Static\Backup\Logs\Logger;
use Simply_Static\Backup\Migration\Migration;
use Simply_Static\Backup\Queue\Exceptions\Retry_Exception;
use Simply_Static\Backup\Queue\Exceptions\Skippable_Exception;
use Simply_Static\Backup\Queue\Tasks\MigrationTask;
use Simply_Static\Backup\Status;

class Backup_Process extends Background_Process {

	/**
	 * @var string
	 */
	protected $prefix = 'simply_static';

	/**
	 * @var string
	 */
	protected $action = 'backup_process';

	/**
	 * Constructor - set up iteration delay filter.
	 */
	public function __construct() {
		parent::__construct();

		// Add filter for iteration delay (seconds between batches/iterations)
		add_filter( $this->identifier . '_seconds_between_batches', array( $this, 'get_iteration_delay' ) );
	}

	/**
	 * Get the iteration delay from settings.
	 * Minimum 1 second to prevent crashing slow-performing shared hosting environments.
	 *
	 * @param int $seconds Default seconds.
	 * @return int
	 */
	public function get_iteration_delay( $seconds ) {
		$settings = get_option( 'sss_backup_migrate', [] );
		$delay    = isset( $settings['iteration_delay'] ) ? absint( $settings['iteration_delay'] ) : 1;

		// Enforce minimum of 1 second
		return max( 1, $delay );
	}

	/**
	 * Get the task delay from settings.
	 * Minimum 5 seconds to prevent crashing slow-performing shared hosting environments.
	 *
	 * @return int
	 */
	protected function get_task_delay() {
		$settings = get_option( 'sss_backup_migrate', [] );
		$delay    = isset( $settings['task_delay'] ) ? absint( $settings['task_delay'] ) : 5;

		// Enforce minimum of 5 seconds
		return max( 5, $delay );
	}

	/**
	 * Perform task with queued item.
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param mixed $item Queue item to iterate over.
	 *
	 * @return mixed
	 */
	protected function task( $item ) {
		// Actions to perform.

		$task = $this->getItem( $item );

		if ( ! $task ) {
			Logger::log( 'Task not found: ' . $item );
			return false;
		}

		// Track current task for progress calculation.
		update_option( 'sssbm_current_task', $item );

		try {
			Logger::log( 'Performing: ' . $item );
			$done = $task->perform();
			Logger::log( 'Task perform() returned: ' . ( $done ? 'true' : 'false' ) );

			if ( ! $done ) {
				Logger::log( 'Not done with: ' . $item );

				return $item;
			}

			// Task completed successfully, reset retry count
			$task->resetRetryCount();

			$nextTask = Backup::get_next_task( $item );

			if ( null === $nextTask ) {
				// No more tasks.
				Logger::log( "No more tasks" );

				return false;
			}

			Logger::log( 'New task: ' . $nextTask );

			// Apply task delay to prevent crashing slow-performing shared hosting environments
			$task_delay = $this->get_task_delay();
			Logger::log( 'Waiting ' . $task_delay . ' seconds before next task...' );
			sleep( $task_delay );

			return $nextTask;

		} catch ( Skippable_Exception $e ) {
			Logger::log( 'Skippable: ' . $e->getMessage() );
			Logger::log( $e->getTraceAsString() );
			$nextTask = Backup::get_next_task( $item );
			if ( null === $nextTask ) {
				// No more tasks.
				Logger::log( "No more tasks" );

				return false;
			}

			Logger::log( 'New task: ' . $nextTask );

			// Apply task delay to prevent crashing slow-performing shared hosting environments
			$task_delay = $this->get_task_delay();
			Logger::log( 'Waiting ' . $task_delay . ' seconds before next task...' );
			sleep( $task_delay );

			return $nextTask;
		} catch ( Retry_Exception $e ) {
			$task->incrementRetryCount();

			if ( $task->hasExceededMaxRetries() ) {
				Logger::log( 'Task has exceeded maximum retries: ' . $item );
				$this->pauseExecution( $e, $item, $task );

				return $item;
			}

			Logger::log( 'Retry: ' . $e->getMessage() );
			Logger::log( $e->getTraceAsString() );

			Logger::log( 'Retrying task: ' . $item . ' (Attempt ' . $task->getRetryCount() . ')' );

			return $item;
		} catch ( \Throwable $e ) {
			// Catch all errors and exceptions (including PHP Error objects in PHP 7+)
			Logger::log( 'Error (' . get_class( $e ) . '): ' . $e->getMessage() );
			Logger::log( 'File: ' . $e->getFile() . ':' . $e->getLine() );
			Logger::log( $e->getTraceAsString() );
			$this->pauseExecution( $e, $item, $task );

			// Something to do here when exception is met: pause migration.
			return $item;
		}
	}

	protected function pauseExecution( $exception, $taskName, $taskObject ) {
		$this->pause();
		Status::error( 'Migration paused. Reason: ' . $exception->getMessage() );
		Logger::log( $exception->getMessage() );
		Logger::log( $exception->getTraceAsString() );
		do_action( 'simply_static_studio_migration_process_failed', [
			'message'     => $exception->getMessage(),
			'code'        => $exception->getCode(),
			'task'        => $taskName,
			'task_object' => $taskObject
		],
			$this
		);
	}

	/**
	 * @param $item
	 *
	 * @return false|MigrationTask
	 */
	protected function getItem( $item ) {

		$className = '\Simply_Static\Backup\Queue\Tasks\\' . ucfirst( $item );

		if ( class_exists( $className ) ) {
			return new $className();
		}

		return false;
	}
}

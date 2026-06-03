<?php

namespace Simply_Static\Backup\Queue\Tasks;

use Simply_Static\Backup\Backup;
use Simply_Static\Backup\Helper;
use Simply_Static\Backup\Queue\Exceptions\Retry_Exception;
use Simply_Static\Backup\Status;

class Backup_Task {

	protected $id = '';

	protected $done = false;

	protected $test = false;
	protected $tables = null;

	/**
	 * Maximum number of retries for this task.
	 *
	 * @var int
	 */
	protected $maxRetries = 1;

	/**
	 * Current retry count.
	 *
	 * @var int|null
	 */
	protected $retryCount = null;

	protected $longRunning = false;

	/**
	 * @param boolean $test If true, it will output queries but not perform them.
	 *
	 * @return void
	 */
	public function setTest( $test ) {
		$this->test = $test;
	}

	public function maybeTestStart() {
		if ( ! $this->test ) {
			return;
		}

		add_filter( 'query', [ $this, 'output_query' ] );
	}

	public function maybeTestEnd() {
		if ( ! $this->test ) {
			return;
		}

		remove_filter( 'query', [ $this, 'output_query' ] );
	}

	public function output_query( $query ) {
		print_r( $query . "\n" );

		if ( 'SHOW TABLES' === $query ) {
			return $query;
		}

		return '';
	}

	/**
	 * @return true
	 */
	protected function run() {
		return true;
	}

	public function isLongRunning() {
		return $this->longRunning;
	}

	public function perform() {
		if ( $this->isLongRunning() ) {
			set_time_limit( 0 );
		}

		return $this->run();
	}

	public function getFilesystem() {
		return Helper::getFileSystem();
	}

	public function move( $from, $to ) {
		$filesystem = $this->getFilesystem();

		if ( ! $filesystem ) {
			throw new Retry_Exception( 'Filesystem is not available' );
		}

		$filesystem->copy( $from, $to );
	}

	/**
	 * Get the task name based on the class name.
	 *
	 * @return string
	 */
	public function getTaskName() {
		$className = get_class( $this );
		$parts     = explode( '\\', $className );

		return lcfirst( end( $parts ) );
	}

	/**
	 * Get the option name for storing the retry count.
	 *
	 * @return string
	 */
	protected function getRetryCountOptionName() {
		return 'simply_static_migration_retry_count_' . $this->getTaskName();
	}

	/**
	 * Get the current retry count from the database.
	 *
	 * @return int
	 */
	public function getRetryCount() {
		if ( null === $this->retryCount ) {
			$retryCount       = get_option( $this->getRetryCountOptionName(), 0 );
			$this->retryCount = (int) $retryCount;
		}

		return $this->retryCount;
	}

	/**
	 * Increment the retry count and save it to the database.
	 *
	 * @return void
	 */
	public function incrementRetryCount() {
		$this->retryCount = $this->getRetryCount() + 1;
		update_option( $this->getRetryCountOptionName(), $this->retryCount );
	}

	/**
	 * Check if the task has exceeded its maximum retries.
	 *
	 * @return bool
	 */
	public function hasExceededMaxRetries() {
		return $this->getRetryCount() >= $this->maxRetries;
	}

	/**
	 * Reset the retry count in the database.
	 *
	 * @return void
	 */
	public function resetRetryCount() {
		delete_option( $this->getRetryCountOptionName() );
		$this->retryCount = 0;
	}

	public function save_task_params( $task_params ) {

		// When no work was done (e.g. ZIP lock failure), skip saving to
		// avoid overwriting progress made by a concurrent process.
		if ( ! empty( $task_params['_skip_save'] ) ) {
			return;
		}

		$params                    = Backup::get_export_params();
		$params[ $this->get_id() ] = $task_params;

		Backup::save_export_params( $params );
	}

	/**
	 * @return mixed|string
	 */
	public function get_task_params() {
		$params = Backup::get_export_params();

		return $params[ $this->get_id() ] ?? [];
	}

	public function get_id() {
		if ( $this->id ) {
			return $this->id;
		}

		$class_name = get_class( $this );
		$parts      = explode( '\\', $class_name );
		$this->id   = strtolower( end( $parts ) );

		return $this->id;
	}

	protected function status( $message, $id = null ) {
		if ( null === $id ) {
			$id = $this->get_id();
		}

		Status::info( $message, $id );
	}

}

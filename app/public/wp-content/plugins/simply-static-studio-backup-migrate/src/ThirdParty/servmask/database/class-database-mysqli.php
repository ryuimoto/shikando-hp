<?php

namespace Simply_Static\Backup\ThirdParty\servmask\database;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

class Database_Mysqli extends Database {

	/**
	 * Run MySQL query
	 *
	 * @param  string $input SQL query
	 * @return mixed
	 */
	public function query( $input ) {
		if ( ! mysqli_real_query( $this->wpdb->dbh, $input ) ) {
			$mysqli_errno = 0;

			// Get MySQL error code
			if ( ! empty( $this->wpdb->dbh ) ) {
				if ( $this->wpdb->dbh instanceof \mysqli ) {
					$mysqli_errno = mysqli_errno( $this->wpdb->dbh );
				} else {
					$mysqli_errno = 2006;
				}
			}

			// MySQL server has gone away, try to reconnect
			if ( empty( $this->wpdb->dbh ) || 2006 === $mysqli_errno ) {
				if ( ! $this->wpdb->check_connection( false ) ) {
					throw new \Exception( __( 'Error reconnecting to the database. <a href="https://help.servmask.com/knowledgebase/mysql-error-reconnecting/" target="_blank">Technical details</a>', 'all-in-one-wp-migration' ), 503 );
				}

				mysqli_real_query( $this->wpdb->dbh, $input );
			}
		}

		// The parameter $mode has had no effect as of PHP 8.1.0.
		if ( ( PHP_MAJOR_VERSION >= 8 && PHP_MINOR_VERSION >= 1 ) || ! defined( 'MYSQLI_STORE_RESULT_COPY_DATA' ) ) {
			return mysqli_store_result( $this->wpdb->dbh );
		}

		// Copy results from the internal mysqlnd buffer into the PHP variables fetched
		return mysqli_store_result( $this->wpdb->dbh, MYSQLI_STORE_RESULT_COPY_DATA );
	}

	/**
	 * Escape string input for MySQL query
	 *
	 * @param  string $input String to escape
	 * @return string
	 */
	public function escape( $input ) {
		return mysqli_real_escape_string( $this->wpdb->dbh, $input );
	}

	/**
	 * Return the error code for the most recent function call
	 *
	 * @return integer
	 */
	public function errno() {
		return mysqli_errno( $this->wpdb->dbh );
	}

	/**
	 * Return a string description of the last error
	 *
	 * @return string
	 */
	public function error() {
		return mysqli_error( $this->wpdb->dbh );
	}

	/**
	 * Return server info
	 *
	 * @return string
	 */
	public function server_info() {
		return mysqli_get_server_info( $this->wpdb->dbh );
	}

	/**
	 * Return the result from MySQL query as associative array
	 *
	 * @param  mixed $result MySQL resource
	 * @return array
	 */
	public function fetch_assoc( &$result ) {
		return mysqli_fetch_assoc( $result );
	}

	/**
	 * Return the result from MySQL query as row
	 *
	 * @param  mixed $result MySQL resource
	 * @return array
	 */
	public function fetch_row( &$result ) {
		return mysqli_fetch_row( $result );
	}

	/**
	 * Return the number for rows from MySQL results
	 *
	 * @param  mixed $result MySQL resource
	 * @return integer
	 */
	public function num_rows( &$result ) {
		return mysqli_num_rows( $result );
	}

	/**
	 * Free MySQL result memory
	 *
	 * @param  mixed $result MySQL resource
	 * @return boolean
	 */
	public function free_result( &$result ) {
		return mysqli_free_result( $result );
	}
}

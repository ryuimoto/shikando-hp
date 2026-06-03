<?php

namespace Simply_Static\Backup\ThirdParty\servmask\database;


if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

class Database_Mysql extends Database {

	/**
	 * Run MySQL query
	 *
	 * @param  string $input SQL query
	 * @return mixed
	 */
	public function query( $input ) {
		if ( ! ( $result = mysql_query( $input, $this->wpdb->dbh ) ) ) {
			$mysql_errno = 0;

			// Get MySQL error code
			if ( ! empty( $this->wpdb->dbh ) ) {
				if ( is_resource( $this->wpdb->dbh ) ) {
					$mysql_errno = mysql_errno( $this->wpdb->dbh );
				} else {
					$mysql_errno = 2006;
				}
			}

			// MySQL server has gone away, try to reconnect
			if ( empty( $this->wpdb->dbh ) || 2006 === $mysql_errno ) {
				if ( ! $this->wpdb->check_connection( false ) ) {
					throw new \Exception( __( 'Error reconnecting to the database. <a href="https://help.servmask.com/knowledgebase/mysql-error-reconnecting/" target="_blank">Technical details</a>', 'all-in-one-wp-migration' ), 503 );
				}

				$result = mysql_query( $input, $this->wpdb->dbh );
			}
		}

		return $result;
	}

	/**
	 * Escape string input for MySQL query
	 *
	 * @param  string $input String to escape
	 * @return string
	 */
	public function escape( $input ) {
		return mysql_real_escape_string( $input, $this->wpdb->dbh );
	}

	/**
	 * Return the error code for the most recent function call
	 *
	 * @return integer
	 */
	public function errno() {
		return mysql_errno( $this->wpdb->dbh );
	}

	/**
	 * Return a string description of the last error
	 *
	 * @return string
	 */
	public function error() {
		return mysql_error( $this->wpdb->dbh );
	}

	/**
	 * Return server info
	 *
	 * @return string
	 */
	public function server_info() {
		return mysql_get_server_info( $this->wpdb->dbh );
	}

	/**
	 * Return the result from MySQL query as associative array
	 *
	 * @param  mixed $result MySQL resource
	 * @return array
	 */
	public function fetch_assoc( &$result ) {
		return mysql_fetch_assoc( $result );
	}

	/**
	 * Return the result from MySQL query as row
	 *
	 * @param  mixed $result MySQL resource
	 * @return array
	 */
	public function fetch_row( &$result ) {
		return mysql_fetch_row( $result );
	}

	/**
	 * Return the number for rows from MySQL results
	 *
	 * @param  mixed $result MySQL resource
	 * @return integer
	 */
	public function num_rows( &$result ) {
		return mysql_num_rows( $result );
	}

	/**
	 * Free MySQL result memory
	 *
	 * @param  mixed $result MySQL resource
	 * @return boolean
	 */
	public function free_result( &$result ) {
		return mysql_free_result( $result );
	}
}

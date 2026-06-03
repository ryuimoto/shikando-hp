<?php

namespace Simply_Static\Backup\ThirdParty\servmask\backup;

use Simply_Static\Backup\Backup;
use Simply_Static\Backup\Helper;
use Simply_Static\Backup\Status;
use Simply_Static\Backup\ThirdParty\servmask\database\Database_Utility;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

class Backup_Enumerate_Tables {

	public static function execute( $params = [] ) {
		// Set exclude database
		if ( isset( $params['options']['no_database'] ) ) {
			return $params;
		}

		// Get total tables count
		if ( isset( $params['total_tables_count'] ) ) {
			$total_tables_count = (int) $params['total_tables_count'];
		} else {
			$total_tables_count = 0;
		}

		// Set progress
		Status::info( __( 'Gathering database tables...', 'sss-backup-migrate' ), 'enumerate_tables' );

		// Get database client
		$db_client = Database_Utility::create_client();

		// Include table prefixes
		global $wpdb;
		$db_client->add_table_prefix_filter( $wpdb->prefix );

		if ( is_multisite() ) {
			// Always include users, usermeta and sitemeta for subsite exports
			$db_client->add_table_prefix_filter( $wpdb->users );
			$db_client->add_table_prefix_filter( $wpdb->usermeta );
			$db_client->add_table_prefix_filter( $wpdb->sitemeta );
		}
		/*if ( ai1wm_table_prefix() ) {
			$db_client->add_table_prefix_filter( ai1wm_table_prefix() );

			// Include table prefixes (Webba Booking and CiviCRM)
			foreach ( array( 'wbk_', 'civicrm_' ) as $table_name ) {
				$db_client->add_table_prefix_filter( $table_name );
			}
		}*/

		// Create tables list file
		$tables_list = @fopen( Backup::get_tables_list_path(), 'w' );

		// Exclude selected db tables
		$excluded_db_tables = array();
		if ( isset( $params['options']['exclude_db_tables'], $params['excluded_db_tables'] ) ) {
			$excluded_db_tables = explode( ',', $params['excluded_db_tables'] );
		}

		// Write table line
		foreach ( $db_client->get_tables() as $table_name ) {
			if ( ! in_array( $table_name, $excluded_db_tables ) && Helper::putcsv( $tables_list, array( $table_name ) ) ) {
				$total_tables_count ++;
			}
		}

		// Include selected db tables
		if ( isset( $params['options']['include_db_tables'] ) && ! empty( $params['included_db_tables'] ) ) {
			foreach ( explode( ',', $params['included_db_tables'] ) as $table_name ) {
				if ( Helper::putcsv( $tables_list, array( $table_name ) ) ) {
					$total_tables_count ++;
				}
			}
		}

		// Set progress
		Status::success( __( 'Database tables gathered.', 'sss-backup-migrate' ), 'enumerate_tables' );

		// Set total tables count
		$params['total_tables_count'] = $total_tables_count;
		$params['completed']          = true;
		// Close the tables list file
		@fclose( $tables_list );

		return $params;
	}
}

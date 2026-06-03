<?php

namespace Simply_Static\Backup\ThirdParty\servmask\backup;

use Simply_Static\Backup\Backup;
use Simply_Static\Backup\Helper;
use Simply_Static\Backup\Status;
use Simply_Static\Backup\ThirdParty\servmask\filter\Recursive_Exclude_Filter;
use Simply_Static\Backup\ThirdParty\servmask\iterator\Recursive_Directory_Iterator;
use Simply_Static\Backup\ThirdParty\servmask\iterator\Recursive_Iterator_Iterator;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

class Backup_Enumerate_Content {

	/**
	 * Get default content filters
	 *
	 * @param array $filters List of files and directories
	 *
	 * @return array
	 */
	public static function content_filters( $filters = array() ) {
		return array_merge(
			$filters,
			array(
				Backup::get_backup_path(),
				'config.json',
				'database.sql',
				'db.sql',

				// Exclude third-party backup plugin directories to avoid
				// backing up potentially multi-GB archive files from other
				// plugins, which bloat the ZIP and slow the entire migration.
				'ai1wm-backups',       // All-in-One WP Migration
				'updraft',             // UpdraftPlus
				'backups-dup-pro',     // Duplicator Pro
				'backups-dup-lite',    // Duplicator Lite
				'backupbuddy_backups', // BackupBuddy
				'backup-guard',        // BackupGuard
			)
		);
	}

	public static function execute( $params = [] ) {

		$uploads_dir = '';

		if ( ( $upload_dir = wp_upload_dir() ) ) {
			if ( isset( $upload_dir['basedir'] ) ) {
				$uploads_dir = untrailingslashit( $upload_dir['basedir'] );
			}
		}

		$plugins_dir    = untrailingslashit( WP_PLUGIN_DIR );
		$mu_plugins_dir = defined( 'WPMU_PLUGIN_DIR' ) ? untrailingslashit( WPMU_PLUGIN_DIR ) : WP_CONTENT_DIR . DIRECTORY_SEPARATOR . 'mu-plugins';

		$theme_dirs = array();
		foreach ( search_theme_directories() as $theme_name => $theme_info ) {
			if ( isset( $theme_info['theme_root'] ) ) {
				if ( ! in_array( $theme_info['theme_root'], $theme_dirs ) ) {
					$theme_dirs[] = untrailingslashit( $theme_info['theme_root'] );
				}
			}
		}

		$exclude_filters = array_merge( array( $uploads_dir, $plugins_dir, $mu_plugins_dir ), $theme_dirs );

		// Get total content files count
		if ( isset( $params['total_content_files_count'] ) ) {
			$total_content_files_count = (int) $params['total_content_files_count'];
		} else {
			$total_content_files_count = 0;
		}

		// Get total content files size
		if ( isset( $params['total_content_files_size'] ) ) {
			$total_content_files_size = (int) $params['total_content_files_size'];
		} else {
			$total_content_files_size = 0;
		}

		// Set progress
		Status::info( __( 'Gathering content files...', 'sss-backup-migrate' ), 'enumerate_content' );

		// Exclude cache
		$exclude_filters[] = 'cache';

		// Exclude selected files
		if ( isset( $params['options']['exclude_files'], $params['excluded_files'] ) ) {
			if ( ( $excluded_files = explode( ',', $params['excluded_files'] ) ) ) {
				foreach ( $excluded_files as $excluded_path ) {
					$exclude_filters[] = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . untrailingslashit( $excluded_path );
				}
			}
		}

		// Create content list file
		$content_list = @fopen( Backup::get_content_list_path(), "w" );

		// Enumerate over content directory
		if ( isset( $params['options']['no_themes'], $params['options']['no_muplugins'], $params['options']['no_plugins'] ) === false ) {

			// Iterate over content directory
			$iterator = new Recursive_Directory_Iterator( WP_CONTENT_DIR );

			// Exclude content files
			$iterator = new Recursive_Exclude_Filter( $iterator, apply_filters( 'sssbm_exclude_content_from_export', self::content_filters( $exclude_filters ) ) );

			// Recursively iterate over content directory
			$iterator = new Recursive_Iterator_Iterator( $iterator, \RecursiveIteratorIterator::LEAVES_ONLY, \RecursiveIteratorIterator::CATCH_GET_CHILD );

			// Write path line
			foreach ( $iterator as $item ) {
				if ( $item->isFile() ) {
					if ( Helper::putcsv( $content_list, array(
						$iterator->getPathname(),
						$iterator->getSubPathname(),
						$iterator->getSize(),
						$iterator->getMTime()
					) ) ) {
						$total_content_files_count ++;

						// Add current file size
						$total_content_files_size += $iterator->getSize();
					}
				}
			}
		}

		// Set progress
		Status::success( __( 'Content files gathered.', 'sss-backup-migrate' ), 'enumerate_content' );

		// Set total content files count
		$params['total_content_files_count'] = $total_content_files_count;

		// Set total content files size
		$params['total_content_files_size'] = $total_content_files_size;

		$params['completed'] = true;

		// Close the content list file
		@fclose( $content_list );

		return $params;
	}
}

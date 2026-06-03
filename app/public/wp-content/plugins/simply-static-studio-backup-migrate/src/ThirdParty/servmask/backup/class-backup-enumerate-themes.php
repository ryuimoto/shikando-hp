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

class Backup_Enumerate_Themes {

	public static function execute( $params = [] ) {

		$exclude_filters = array();

		// Get total themes files count
		if ( isset( $params['total_themes_files_count'] ) ) {
			$total_themes_files_count = (int) $params['total_themes_files_count'];
		} else {
			$total_themes_files_count = 0;
		}

		// Get total themes files size
		if ( isset( $params['total_themes_files_size'] ) ) {
			$total_themes_files_size = (int) $params['total_themes_files_size'];
		} else {
			$total_themes_files_size = 0;
		}

		// Set progress
		Status::info( __( 'Gathering theme files...', 'sss-backup-migrate' ), 'enumerate_themes' );

		// Exclude inactive themes
		foreach ( search_theme_directories() as $theme_name => $theme_info ) {
			if ( ! in_array( $theme_name, array( get_template(), get_stylesheet() ) ) ) {
				if ( isset( $theme_info['theme_root'] ) ) {
					$exclude_filters[] = $theme_info['theme_root'] . DIRECTORY_SEPARATOR . $theme_name;
				}
			}
		}

		// Exclude selected files
		if ( isset( $params['options']['exclude_files'], $params['excluded_files'] ) ) {
			if ( ( $excluded_files = explode( ',', $params['excluded_files'] ) ) ) {
				foreach ( $excluded_files as $excluded_path ) {
					$exclude_filters[] = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . untrailingslashit( $excluded_path );
				}
			}
		}

		// Create themes list file
		$themes_list = @fopen( Backup::get_themes_list_path(), 'w' );

		// Enumerate over themes directory
		if ( isset( $params['options']['no_themes'] ) === false ) {
			foreach ( Helper::get_themes_dirs() as $theme_dir ) {
				if ( is_dir( $theme_dir ) ) {

					// Iterate over themes directory
					$iterator = new Recursive_Directory_Iterator( $theme_dir );

					// Exclude themes files
					$iterator = new Recursive_Exclude_Filter( $iterator, apply_filters( 'sssbm_exclude_themes_from_export', $exclude_filters ) );

					// Recursively iterate over themes directory
					$iterator = new Recursive_Iterator_Iterator( $iterator, \RecursiveIteratorIterator::LEAVES_ONLY, \RecursiveIteratorIterator::CATCH_GET_CHILD );

					// Write path line
					foreach ( $iterator as $item ) {
						if ( $item->isFile() ) {

							if ( Helper::putcsv( $themes_list, array(
								$iterator->getPathname(),
								$iterator->getSubPathname(),
								$iterator->getSize(),
								$iterator->getMTime()
							) ) ) {
								$total_themes_files_count ++;

								// Add current file size
								$total_themes_files_size += $iterator->getSize();
							}
						}
					}
				}
			}
		}

		// Set progress
		Status::success( __( 'Theme files gathered.', 'sss-backup-migrate' ), 'enumerate_themes' );

		// Set total themes files count
		$params['total_themes_files_count'] = $total_themes_files_count;

		// Set total themes files size
		$params['total_themes_files_size'] = $total_themes_files_size;
		$params['completed']               = true;
		// Close the themes list file
		@fclose( $themes_list );

		return $params;
	}
}

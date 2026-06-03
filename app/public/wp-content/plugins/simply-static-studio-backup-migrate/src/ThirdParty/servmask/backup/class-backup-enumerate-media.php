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

class Backup_Enumerate_Media {

	public static function execute( $params = [] ) {

		$exclude_filters = array();

		// Get total media files count
		if ( isset( $params['total_media_files_count'] ) ) {
			$total_media_files_count = (int) $params['total_media_files_count'];
		} else {
			$total_media_files_count = 0;
		}

		// Get total media files size
		if ( isset( $params['total_media_files_size'] ) ) {
			$total_media_files_size = (int) $params['total_media_files_size'];
		} else {
			$total_media_files_size = 0;
		}

		// Set progress
		Status::info( __( 'Gathering media files...', 'sss-backup-migrate' ), 'enumerate_media' );

		// Exclude selected files
		if ( isset( $params['options']['exclude_files'], $params['excluded_files'] ) ) {
			if ( ( $excluded_files = explode( ',', $params['excluded_files'] ) ) ) {
				foreach ( $excluded_files as $excluded_path ) {
					$exclude_filters[] = WP_CONTENT_DIR . DIRECTORY_SEPARATOR . untrailingslashit( $excluded_path );
				}
			}
		}

		// Create media list file
		$media_list = @fopen( Backup::get_media_list_path(), 'w' );

		$exclude_filters[] = untrailingslashit( Backup::get_storage_path() );

		// Enumerate over media directory
		if ( isset( $params['options']['no_media'] ) === false ) {
			$upload_dir = wp_upload_dir();
			if ( is_dir( untrailingslashit( $upload_dir['basedir'] ) ) ) {

				// Iterate over media directory
				$iterator = new Recursive_Directory_Iterator( untrailingslashit( $upload_dir['basedir'] ) );

				// Exclude media files
				$iterator = new Recursive_Exclude_Filter( $iterator, apply_filters( 'sssbm_exclude_media_from_export', $exclude_filters ) );

				// Recursively iterate over content directory
				$iterator = new Recursive_Iterator_Iterator( $iterator, \RecursiveIteratorIterator::LEAVES_ONLY, \RecursiveIteratorIterator::CATCH_GET_CHILD );


				// Write path line
				foreach ( $iterator as $item ) {
					if ( $item->isFile() ) {
						if ( Helper::putcsv( $media_list, array(
							$iterator->getPathname(),
							$iterator->getSubPathname(),
							$iterator->getSize(),
							$iterator->getMTime()
						) ) ) {
							$total_media_files_count ++;

							// Add current file size
							$total_media_files_size += $iterator->getSize();
						}
					}
				}
			}
		}

		// Set progress
		Status::success( __( 'Media files gathered.', 'sss-backup-migrate' ), 'enumerate_media' );

		// Set total media files count
		$params['total_media_files_count'] = $total_media_files_count;

		// Set total media files size
		$params['total_media_files_size'] = $total_media_files_size;

		$params['completed'] = true;

		// Close the media list file
		@fclose( $media_list );

		return $params;
	}
}

<?php

namespace Simply_Static\Backup\ThirdParty\servmask\backup;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

class Backup_Download {

	public static function execute( $params = [] ) {

		// Set progress
		Status::info( __( 'Renaming backup file...', 'sss-backup-migrate' ) );

		// Open the archive file for writing
		$archive = new Compressor( ai1wm_archive_path( $params ) );

		// Append EOF block
		$archive->close( true );

		// Rename archive file
		if ( rename( ai1wm_archive_path( $params ), ai1wm_backup_path( $params ) ) ) {

			$blog_id = null;

			// Get subsite Blog ID
			if ( isset( $params['options']['sites'] ) && ( $sites = $params['options']['sites'] ) ) {
				if ( count( $sites ) === 1 ) {
					$blog_id = array_shift( $sites );
				}
			}

			// Set archive details
			$file = ai1wm_archive_name( $params );
			$link = ai1wm_backup_url( $params );
			$size = ai1wm_backup_size( $params );
			$name = ai1wm_site_name( $blog_id );

			// Set progress
			if ( ai1wm_direct_download_supported() ) {
				Status::download(
					sprintf(
					/* translators: 1: Link to archive, 2: Archive title, 3: File name, 4: Archive title, 5: File size. */
						__(
							'<a href="%1$s" class="ai1wm-button-green ai1wm-emphasize ai1wm-button-download" title="%2$s" download="%3$s">
							<span>Download %2$s</span>
							<em>Size: %4$s</em>
							</a>',
							'sss-backup-migrate'
						),
						$link,
						$name,
						$file,
						$size
					)
				);
			} else {
				Status::download(
					sprintf(
					/* translators: 1: Archive title, 2: File name, 3: Archive title, 4: File size. */
						__(
							'<a href="#" class="ai1wm-button-green ai1wm-emphasize ai1wm-direct-download" title="%1$s" download="%2$s">
							<span>Download %3$s</span>
							<em>Size: %4$s</em>
							</a>',
							'sss-backup-migrate'
						),
						$name,
						$file,
						$name,
						$size
					)
				);
			}
		}

		do_action( 'sssbm_status_export_done', $params );

		if ( isset( $params['sssbm_manual_backup'] ) ) {
			do_action( 'sssbm_status_backup_created', $params );
		}

		return $params;
	}
}

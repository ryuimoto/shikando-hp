<?php

namespace Simply_Static\Backup\ThirdParty\servmask\backup;

use Simply_Static\Backup\Backup;
use Simply_Static\Backup\Helper;
use Simply_Static\Backup\Status;
use Simply_Static\Backup\ThirdParty\servmask\database\Database_Utility;

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

class Backup_Config {

	public static function execute( $params = [] ) {
		global $table_prefix, $wp_version;

		// Set progress
		Status::info( __( 'Preparing configuration...', 'sss-backup-migrate' ), 'ssm-config' );

		// Get options
		$options = wp_load_alloptions();

		// Get database client
		$db_client = Database_Utility::create_client();

		$config = array();

		// Set site URL
		$config['SiteURL'] = site_url();

		// Set home URL
		$config['HomeURL'] = home_url();

		// Set internal site URL
		if ( isset( $options['siteurl'] ) ) {
			$config['InternalSiteURL'] = $options['siteurl'];
		}

		// Set internal home URL
		if ( isset( $options['home'] ) ) {
			$config['InternalHomeURL'] = $options['home'];
		}

		// Set replace old and new values
		if ( isset( $params['options']['replace'] ) && ( $replace = $params['options']['replace'] ) ) {
			for ( $i = 0; $i < count( $replace['old_value'] ); $i ++ ) {
				if ( ! empty( $replace['old_value'][ $i ] ) && ! empty( $replace['new_value'][ $i ] ) ) {
					$config['Replace']['OldValues'][] = $replace['old_value'][ $i ];
					$config['Replace']['NewValues'][] = $replace['new_value'][ $i ];
				}
			}
		}

		// Set no spam comments
		if ( isset( $params['options']['no_spam_comments'] ) ) {
			$config['NoSpamComments'] = true;
		}

		// Set no post revisions
		if ( isset( $params['options']['no_post_revisions'] ) ) {
			$config['NoPostRevisions'] = true;
		}

		// Set no media
		if ( isset( $params['options']['no_media'] ) ) {
			$config['NoMedia'] = true;
		}

		// Set no themes
		if ( isset( $params['options']['no_themes'] ) ) {
			$config['NoThemes'] = true;
		}

		// Set no inactive themes
		$config['NoInactiveThemes'] = true;

		// Set no must-use plugins
		if ( isset( $params['options']['no_muplugins'] ) ) {
			$config['NoMustUsePlugins'] = true;
		}

		// Set no plugins
		if ( isset( $params['options']['no_plugins'] ) ) {
			$config['NoPlugins'] = true;
		}

		// Set no inactive plugins
		if ( isset( $params['options']['no_inactive_plugins'] ) ) {
			$config['NoInactivePlugins'] = true;
		}

		// Set no cache
		if ( isset( $params['options']['no_cache'] ) ) {
			$config['NoCache'] = true;
		}

		// Set no database
		if ( isset( $params['options']['no_database'] ) ) {
			$config['NoDatabase'] = true;
		}

		// Set no email replace
		if ( isset( $params['options']['no_email_replace'] ) ) {
			$config['NoEmailReplace'] = true;
		}

		// Set plugin version
		$config['Plugin'] = array( 'Version' => SSSBM_VERSION );

		// Set WordPress version and content
		$config['WordPress'] = array( 'Version'    => $wp_version,
		                              'Absolute'   => ABSPATH,
		                              'Content'    => WP_CONTENT_DIR,
		                              'Plugins'    => ai1wm_get_plugins_dir(),
		                              'Themes'     => ai1wm_get_themes_dirs(),
		                              'Uploads'    => ai1wm_get_uploads_dir(),
		                              'UploadsURL' => ai1wm_get_uploads_url()
		);

		// Set database version
		$config['Database'] = array(
			'Version' => $db_client->server_info(),
			'Charset' => defined( 'DB_CHARSET' ) ? DB_CHARSET : 'undefined',
			'Collate' => defined( 'DB_COLLATE' ) ? DB_COLLATE : 'undefined',
			'Prefix'  => $table_prefix,
		);

		// Exclude selected db tables
		if ( isset( $params['options']['exclude_db_tables'], $params['excluded_db_tables'] ) ) {
			if ( ( $excluded_db_tables = explode( ',', $params['excluded_db_tables'] ) ) ) {
				$config['Database']['ExcludedTables'] = $excluded_db_tables;
			}
		}

		// Include selected db tables
		if ( isset( $params['options']['include_db_tables'], $params['included_db_tables'] ) ) {
			if ( ( $included_db_tables = explode( ',', $params['included_db_tables'] ) ) ) {
				$config['Database']['IncludedTables'] = $included_db_tables;
			}
		}

		// Set PHP version
		$config['PHP'] = array( 'Version' => PHP_VERSION, 'System' => PHP_OS, 'Integer' => PHP_INT_SIZE );

		// Set active plugins
		$config['Plugins'] = array_values( get_option( 'active_plugins' ) );

		// Set active template
		$config['Template'] = get_option( 'template' );

		// Set active stylesheet
		$config['Stylesheet'] = get_option( 'stylesheet' );

		// Set upload path
		$config['Uploads'] = get_option( 'upload_path' );

		// Set upload URL path
		$config['UploadsURL'] = get_option( 'upload_url_path' );

		// Save file
		$handle = @fopen( Backup::get_config_file_path(), "w" );
		@fwrite( $handle, json_encode( $config ) );
		@fclose( $handle );

		// Set progress
		Status::success( __( 'Configuration prepared.', 'sss-backup-migrate' ), 'ssm-config' );

		return $params;
	}
}

<?php

namespace Simply_Static\Backup;

use Simply_Static\Backup\Logs\Logger;

class Backup {

	/**
	 * Return the list of tasks.
	 *
	 * @return string[]
	 */
	public static function get_tasks() {
		$settings = get_option( 'sss_backup_migrate', [] );

		// Check advanced settings - default to true (include) if not set
		// Settings may be stored as strings ("1", "", "true", "false") or booleans
		// We need to check for falsy values: false, "false", "0", "", 0
		$include_plugins  = ! isset( $settings['include_plugins'] ) || ! in_array( $settings['include_plugins'], [ false, 'false', '0', '', 0 ], true );
		$include_themes   = ! isset( $settings['include_themes'] ) || ! in_array( $settings['include_themes'], [ false, 'false', '0', '', 0 ], true );
		$include_media    = ! isset( $settings['include_media'] ) || ! in_array( $settings['include_media'], [ false, 'false', '0', '', 0 ], true );
		$include_database = ! isset( $settings['include_database'] ) || ! in_array( $settings['include_database'], [ false, 'false', '0', '', 0 ], true );

		$tasks = [
			'init',
			'archive',
			'config',
			'config_file',
			'enumerate_content',
		];

		// Add media tasks if included
		if ( $include_media ) {
			$tasks[] = 'enumerate_media';
		}

		// Add plugins tasks if included
		if ( $include_plugins ) {
			$tasks[] = 'enumerate_plugins';
		}

		// Add themes tasks if included
		if ( $include_themes ) {
			$tasks[] = 'enumerate_themes';
		}

		// Add database tasks if included
		if ( $include_database ) {
			$tasks[] = 'enumerate_tables';
		}

		// Add content task (always included)
		$tasks[] = 'content';

		// Add media task if included
		if ( $include_media ) {
			$tasks[] = 'media';
		}

		// Add plugins task if included
		if ( $include_plugins ) {
			$tasks[] = 'plugins';
		}

		// Add themes task if included
		if ( $include_themes ) {
			$tasks[] = 'themes';
		}

		// Add database tasks if included
		if ( $include_database ) {
			$tasks[] = 'database';
			$tasks[] = 'database_file';
		}

		// Always add clean task at the end
		$tasks[] = 'clean';

		return $tasks;
	}

	public static function get_next_task( $task = null ) {
		$tasks = self::get_tasks();

		if ( null === $task ) {
			return $tasks[0];
		}

		$currentIndex = array_search( $task, $tasks, true );

		if ( false === $currentIndex || $currentIndex < 0 ) {
			return null;
		}

		$nextIndex = $currentIndex + 1;

		if ( ! isset( $tasks[ $nextIndex ] ) ) {
			return null;
		}

		return $tasks[ $nextIndex ];
	}

	public static function is_running() {
		return get_option( 'sss_export_process_running', false );
	}

	public static function is_completed() {
		return get_option( 'sss_export_complete', false );
	}

	public static function set_as_running() {
		update_option( 'sss_export_process_running', true );
	}

	public static function set_as_not_running() {
		update_option( 'sss_export_process_running', false );
	}

	public static function set_as_completed() {
		update_option( 'sss_export_complete', true );
	}

	public static function set_as_not_completed() {
		delete_option( 'sss_export_complete' );
	}

	private static $process = null;

	public static function get_process() {
		if ( null === self::$process ) {
			self::$process = new \Simply_Static\Backup\Queue\Backup_Process();
		}

		return self::$process;
	}

	public static function get_storage_url() {
		$uploads_dir       = wp_upload_dir();
		$simply_static_dir = $uploads_dir['baseurl'] . '/simply-static/';

		return $simply_static_dir;
	}

	public static function get_storage_path() {
		$uploads_dir       = wp_upload_dir();
		$simply_static_dir = $uploads_dir['basedir'] . DIRECTORY_SEPARATOR . 'simply-static' . DIRECTORY_SEPARATOR;

		if ( ! is_dir( $simply_static_dir ) ) {
			wp_mkdir_p( $simply_static_dir );
		}

		return $simply_static_dir;
	}

	public static function get_database_path() {
		return self::get_storage_path() . 'db.sql';
	}

	public static function get_tables_list_path() {
		return self::get_storage_path() . 'tables.list';
	}

	public static function get_themes_list_path() {
		return self::get_storage_path() . 'themes.list';
	}

	public static function get_plugins_list_path() {
		return self::get_storage_path() . 'plugins.list';
	}

	public static function get_content_list_path() {
		return self::get_storage_path() . 'content.list';
	}

	public static function get_media_list_path() {
		return self::get_storage_path() . 'media.list';
	}

	public static function get_config_file_path() {
		return self::get_storage_path() . 'config.json';
	}

	public static function get_backup_url() {
		$storage_url = self::get_storage_url();
		$existing   = get_option( 'sssbm_archive_file_path', '' );
		if ( is_string( $existing ) && $existing !== '' ) {
			return $storage_url . basename( $existing );
		}
		$today       = date( 'Y-m-d' );

		return apply_filters( 'sssbm_archive_url', $storage_url . 'studio-backup-' . $today . '.zip', '' );
	}

	public static function get_backup_path() {
		// Use a persistent archive file path for the entire backup run to avoid creating multiple ZIPs
		$existing = get_option( 'sssbm_archive_file_path', '' );
		if ( is_string( $existing ) && $existing !== '' ) {
			return $existing;
		}

		$simply_static_dir = self::get_storage_path();
		$today             = date( 'Y-m-d' );
		$path              = apply_filters( 'sssbm_archive_file', $simply_static_dir . 'studio-backup-' . $today . '.zip', '' );

		// Persist for subsequent tasks/requests of this run
		update_option( 'sssbm_archive_file_path', $path, false );

		return $path;
	}

	public static function start_export() {
		// No need to start another process.
		if ( self::is_running() ) {
			Logger::log( 'Backup already running.' );

			return;
		}

		Logger::clearLog();
		Status::clear_messages();
		self::set_as_not_completed();
		self::delete_export_params();
		// Ensure we start with a fresh, single archive path for this run
		delete_option( 'sssbm_archive_file_path' );
		// Reset persisted media offset for a fresh run
		delete_option( 'sssbm_media_bytes_offset' );
		// Reset current task tracker for progress
		delete_option( 'sssbm_current_task' );
		self::set_as_running();
		$process   = self::get_process();
		$firstTask = self::get_next_task();

		$process->push_to_queue( $firstTask );

		$process->save()->dispatch();
	}

	public static function delete_export_params() {
		delete_option( 'sssbm_export_params' );
	}

	public static function get_export_params() {
		return get_option( 'sssbm_export_params', [] );
	}

	public static function save_export_params( $params ) {
		update_option( 'sssbm_export_params', $params );
	}

	public static function get_export_param( $key, $default = null ) {
		$params = self::get_export_params();

		return isset( $params[ $key ] ) ? $params[ $key ] : $default;
	}

	public static function save_export_param( $key, $value ) {
		$params         = self::get_export_params();
		$params[ $key ] = $value;
		self::save_export_params( $params );
	}

	public static function clear_all_export_files() {
		$dir = self::get_storage_path();

		// Collect known single-file paths
		$files = [
			self::get_media_list_path(),
			self::get_config_file_path(),
			self::get_content_list_path(),
			self::get_plugins_list_path(),
			self::get_themes_list_path(),
			self::get_database_path(),
			self::get_tables_list_path(),
			Logger::getFilename()
		];

		// Add all matching ZIP archives generated by this plugin
		$zip_files = glob( $dir . 'studio-backup-*.zip' );
		if ( is_array( $zip_files ) ) {
			$files = array_merge( $files, $zip_files );
		}

		// Add any list files just in case (future-proofing)
		$list_files = glob( $dir . '*.list' );
		if ( is_array( $list_files ) ) {
			$files = array_merge( $files, $list_files );
		}

		// Deduplicate
		$files = array_unique( array_filter( $files ) );

		foreach ( $files as $file ) {
			if ( is_string( $file ) && file_exists( $file ) ) {
				@unlink( $file );
			}
		}

		// Also clear the persisted archive path marker
		delete_option( 'sssbm_archive_file_path' );
		// Clear persisted media offset
		delete_option( 'sssbm_media_bytes_offset' );
	}
}

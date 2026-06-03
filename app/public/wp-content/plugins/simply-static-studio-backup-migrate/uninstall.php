<?php
/**
 * Uninstall routine for Simply Static Studio Backup and Migrate
 *
 * This file is executed by WordPress during plugin uninstallation.
 * Having this file ensures WordPress does NOT load the main plugin file,
 * avoiding any side effects that could interfere with deletion.
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove options used by the plugin
if ( function_exists( 'delete_option' ) ) {
	delete_option( 'sssbm_export_params' );
	delete_option( 'sssbm_messages' );
	delete_option( 'sss_export_process_running' );
	delete_option( 'sss_export_complete' );

	// Remove retry counters per task (keep list in sync with Backup::get_tasks())
	$tasks = array(
		'init',
		'archive',
		'config',
		'config_file',
		'enumerate_content',
		'enumerate_media',
		'enumerate_plugins',
		'enumerate_themes',
		'enumerate_tables',
		'content',
		'media',
		'plugins',
		'themes',
		'database',
		'database_file',
		'clean',
	);

	foreach ( $tasks as $task ) {
		delete_option( 'simply_static_migration_retry_count_' . $task );
	}
}

// Remove export files created in uploads/simply-static directory
if ( function_exists( 'wp_upload_dir' ) ) {
	$uploads = wp_upload_dir();
	$dir     = trailingslashit( $uploads['basedir'] ) . 'simply-static' . DIRECTORY_SEPARATOR;

	// Known single-file paths
	$known_files = array(
		$dir . 'media.list',
		$dir . 'config.json',
		$dir . 'content.list',
		$dir . 'plugins.list',
		$dir . 'themes.list',
		$dir . 'db.sql',
		$dir . 'tables.list',
		$dir . 'sssbm-debug.txt',
	);

	$files = array();
	foreach ( $known_files as $file ) {
		if ( is_string( $file ) && file_exists( $file ) ) {
			$files[] = $file;
		}
	}

	// Also delete any ZIP archives and .list files we may have created
	$patterns = array(
		$dir . 'studio-backup-*.zip',
		$dir . '*.list',
	);

	foreach ( $patterns as $pattern ) {
		$matches = glob( $pattern );
		if ( is_array( $matches ) ) {
			$files = array_merge( $files, $matches );
		}
	}

	$files = array_unique( array_filter( $files ) );

	foreach ( $files as $file ) {
		@unlink( $file );
	}
}

// Best-effort: remove any leftover site transients/options used by our background process
if ( function_exists( 'delete_site_option' ) ) {
	// These keys are derived from Queue\Background_Process identifier "simply_static_backup_process"
	// but since we don't load classes here, we perform broad cleanup.
	$maybe_keys = array(
		'simply_static_backup_process_status',
	);
	foreach ( $maybe_keys as $key ) {
		delete_site_option( $key );
	}
}

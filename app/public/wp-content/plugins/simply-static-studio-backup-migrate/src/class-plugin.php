<?php

namespace Simply_Static\Backup;

use Simply_Static\Backup\Queue\Backup_Process;
use Simply_Static\Backup\Rest\Settings;

class Plugin {

	public function __construct() {
		new Backup_Process();

		Settings::get_instance();

		add_action( 'admin_menu', [ $this, 'admin_menu' ] );

		// Inject Basic Auth headers into background process HTTP requests.
		add_filter( 'simply_static_backup_process_post_args', [ $this, 'add_basic_auth_headers' ] );
	}

	/**
	 * Register Admin menu page
	 *
	 * @return void
	 */
	public function admin_menu() {

		$menu_hook = add_submenu_page(
			'tools.php',
			__( 'Backup and Migrate', 'sss-backup-migrate' ),
			__( 'Backup and Migrate', 'sss-backup-migrate' ),
			'manage_options',
			'studio-backup',
			array( $this, 'render_menu_page' ),
			0 // menu position
		);

		add_action( "admin_print_scripts-{$menu_hook}", array( $this, 'add_settings_scripts' ) );
	}

	public function add_settings_scripts() {
		wp_enqueue_script( 'simply-static-backup-migrate-settings', SSSBM_URL . '/src/Admin/build/index.js', array(
			'wp-api',
			'wp-components',
			'wp-element',
			'wp-api-fetch',
			'wp-data',
			'wp-i18n',
			'wp-block-editor'
		), SSSBM_VERSION, true );

		$localize_data = [
			'blog_id'      => get_current_blog_id(),
			'is_multisite' => is_multisite(),
			'is_main_site' => is_main_site(),
		];

		// Pass Basic Auth credentials to frontend so apiFetch can include them.
		$settings = get_option( 'sss_backup_migrate', [] );
		if ( ! empty( $settings['http_basic_auth_username'] ) && ! empty( $settings['http_basic_auth_password'] ) ) {
			$localize_data['basic_auth'] = base64_encode( $settings['http_basic_auth_username'] . ':' . $settings['http_basic_auth_password'] );
		}

		wp_localize_script( 'simply-static-backup-migrate-settings', 'sss_backup_migrate_options', $localize_data );
		wp_enqueue_style( 'simply-static-backup-migrate-settings-style', SSSBM_URL . '/src/Admin/build/index.css', array( 'wp-components' ) );
	}


	/**
	 * Add Basic Auth Authorization header to background process HTTP requests.
	 *
	 * @param array $args Post arguments for wp_remote_post.
	 * @return array
	 */
	public function add_basic_auth_headers( $args ) {
		$settings = get_option( 'sss_backup_migrate', [] );

		if ( ! empty( $settings['http_basic_auth_username'] ) && ! empty( $settings['http_basic_auth_password'] ) ) {
			$credentials = base64_encode( $settings['http_basic_auth_username'] . ':' . $settings['http_basic_auth_password'] );

			if ( ! isset( $args['headers'] ) ) {
				$args['headers'] = [];
			}

			$args['headers']['Authorization'] = 'Basic ' . $credentials;
		}

		return $args;
	}

	/**
	 * Render Menu page.
	 * @return void
	 */
	public function render_menu_page() {
		?>
        <div id="static-studio-backup"></div>
        <script type="text/javascript">
            jQuery(document).ready(function ($) {
                $('#wpcontent').height($('#wpwrap').height());
            });
        </script>
		<?php
	}


}
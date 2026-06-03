<?php

namespace Simply_Static\Backup\Rest;

use Simply_Static\Backup\Backup;
use Simply_Static\Backup\Logs\Logger;
use Simply_Static\Backup\Status;

class Settings {
	/**
	 * Contains the number of failed tests.
	 *
	 * @var int
	 */
	public int $failed_tests = 0;

	/**
	 * Contains instance or null
	 *
	 * @var object|null
	 */
	private static $instance = null;

	/**
	 * Returns instance of SS_Admin_Settings.
	 *
	 * @return object
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Setting up admin fields
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'rest_api_init' ) );
	}

	/**
	 * Setup Rest API endpoints.
	 *
	 * @return void
	 */
	public function rest_api_init() {
		register_rest_route( 'sss-backup-migrate/v1', '/settings', array(
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_settings' ],
			'permission_callback' => function () {
				return current_user_can( apply_filters( 'ss_user_capability', 'manage_options', 'settings' ) );
			},
		) );

		register_rest_route( 'sss-backup-migrate/v1', '/settings', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'save_settings' ],
			'permission_callback' => function () {
				return current_user_can( apply_filters( 'ss_user_capability', 'manage_options', 'settings' ) );
			},
		) );

		register_rest_route( 'sss-backup-migrate/v1', '/activity-log', array(
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_activity_log' ],
			'permission_callback' => function () {
				return current_user_can( apply_filters( 'ss_user_capability', 'manage_options', 'activity-log' ) );
			},
		) );

		register_rest_route( 'sss-backup-migrate/v1', '/export-log', array(
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_export_log' ],
			'permission_callback' => function () {
				return current_user_can( apply_filters( 'ss_user_capability', 'manage_options', 'activity-log' ) );
			},
		) );

		register_rest_route( 'sss-backup-migrate/v1', '/start-export', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'start_export' ],
			'permission_callback' => function () {
				return current_user_can( apply_filters( 'ss_user_capability', 'publish_pages', 'generate' ) );
			},
		) );

		register_rest_route( 'sss-backup-migrate/v1', '/cancel-export', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'cancel_export' ],
			'permission_callback' => function () {
				return current_user_can( apply_filters( 'ss_user_capability', 'publish_pages', 'generate' ) );
			},
		) );

		register_rest_route( 'sss-backup-migrate/v1', '/pause-export', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'pause_export' ],
			'permission_callback' => function () {
				return current_user_can( apply_filters( 'ss_user_capability', 'publish_pages', 'generate' ) );
			},
		) );

		register_rest_route( 'sss-backup-migrate/v1', '/resume-export', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'resume_export' ],
			'permission_callback' => function () {
				return current_user_can( apply_filters( 'ss_user_capability', 'publish_pages', 'generate' ) );
			},
		) );

		register_rest_route( 'sss-backup-migrate/v1', '/is-running', array(
			'methods'             => 'GET',
			'callback'            => [ $this, 'is_running' ],
			'permission_callback' => function () {
				return current_user_can( apply_filters( 'ss_user_capability', 'publish_pages', 'generate' ) );
			},
		) );

		register_rest_route( 'sss-backup-migrate/v1', '/debug-log', array(
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_debug_log' ],
			'permission_callback' => function () {
				return current_user_can( apply_filters( 'ss_user_capability', 'manage_options', 'settings' ) );
			},
		) );

		register_rest_route( 'sss-backup-migrate/v1', '/delete-backups', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'delete_all_backups' ],
			'permission_callback' => function () {
				return current_user_can( apply_filters( 'ss_user_capability', 'manage_options', 'settings' ) );
			},
		) );

		register_rest_route( 'sss-backup-migrate/v1', '/download-backup', array(
			'methods'             => 'GET',
			'callback'            => [ $this, 'download_backup' ],
			'permission_callback' => function () {
				return current_user_can( apply_filters( 'ss_user_capability', 'manage_options', 'settings' ) );
			},
		) );

		register_rest_route( 'sss-backup-migrate/v1', '/download-backup-shared', array(
			'methods'             => 'GET',
			'callback'            => [ $this, 'download_backup_shared' ],
			'permission_callback' => '__return_true',
			'args'                => array(
				'token' => array(
					'required'          => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
			),
		) );

		register_rest_route( 'sss-backup-migrate/v1', '/send-otp', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'send_otp' ],
			'permission_callback' => function () {
				return current_user_can( apply_filters( 'ss_user_capability', 'manage_options', 'settings' ) );
			},
		) );

		register_rest_route( 'sss-backup-migrate/v1', '/verify-otp', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'verify_otp' ],
			'permission_callback' => function () {
				return current_user_can( apply_filters( 'ss_user_capability', 'manage_options', 'settings' ) );
			},
		) );

		register_rest_route( 'sss-backup-migrate/v1', '/disconnect', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'disconnect_from_studio' ],
			'permission_callback' => function () {
				return current_user_can( apply_filters( 'ss_user_capability', 'manage_options', 'settings' ) );
			},
		) );

		register_rest_route( 'sss-backup-migrate/v1', '/connection-status', array(
			'methods'             => 'GET',
			'callback'            => [ $this, 'get_connection_status' ],
			'permission_callback' => function () {
				return current_user_can( apply_filters( 'ss_user_capability', 'manage_options', 'settings' ) );
			},
		) );

		register_rest_route( 'sss-backup-migrate/v1', '/push-to-studio', array(
			'methods'             => 'POST',
			'callback'            => [ $this, 'push_to_studio' ],
			'permission_callback' => function () {
				return current_user_can( apply_filters( 'ss_user_capability', 'manage_options', 'settings' ) );
			},
		) );
	}

	/**
	 * Get settings via Rest API.
	 *
	 * @return false|mixed|null
	 */
	public function get_settings() {
		$settings = get_option( 'sss_backup_migrate' );

		return $settings;
	}

	/**
	 * Save settings via rest API.
	 *
	 * @param object $request given request.
	 *
	 * @return false|string
	 */
	public function save_settings( $request ) {
		if ( $request->get_params() ) {
			$options = sanitize_option( 'sss_backup_migrate', $request->get_params() );

			$multiline_fields = [];

			$array_fields = [];

			// Sanitize each key/value pair in options.
			foreach ( $options as $key => $value ) {
				if ( in_array( $key, $multiline_fields ) ) {
					$options[ $key ] = sanitize_textarea_field( $value );
				} elseif ( in_array( $key, $array_fields ) ) {
					$options[ $key ] = array_map( 'sanitize_text_field', $value );
				} else {
					// Sanitize Basic Auth fields but preserve them (don't skip).
					if ( $key === 'http_basic_auth_username' || $key === 'http_basic_auth_password' ) {
						$options[ $key ] = sanitize_text_field( $value );
					} else {
						$options[ $key ] = sanitize_text_field( $value );
					}
				}
			}
			// Update settings.
			update_option( 'sss_backup_migrate', $options );

			return json_encode( [ 'status' => 200, 'message' => "Ok" ] );
		}

		return json_encode( [ 'status' => 400, 'message' => "No options updated." ] );
	}

	/**
	 * Get Activity Log.
	 *
	 * @return false|string
	 */
	public function get_activity_log( $request ) {
		$params       = $request->get_params();
		$activity_log = Status::get_messages();

		// Calculate progress percentage based on current task position.
		$progress       = 0;
		$current_action = '';
		$is_running     = Backup::is_running() || Backup::get_process()->is_active();

		if ( $is_running || Backup::is_completed() ) {
			$tasks        = Backup::get_tasks();
			$total_tasks  = count( $tasks );
			$current_task = get_option( 'sssbm_current_task', '' );

			if ( Backup::is_completed() ) {
				$progress = 100;
			} elseif ( $current_task && $total_tasks > 0 ) {
				$current_index = array_search( $current_task, $tasks, true );
				if ( false !== $current_index ) {
					// Use (index + 1) so that the first task already shows some progress
					// instead of sitting at 0% while it's actively running.
					$progress = min( 99, round( ( ( $current_index + 1 ) / $total_tasks ) * 100 ) );
				}
			}
		}

		// Get the latest message as current action.
		if ( ! empty( $activity_log ) ) {
			$last_message   = end( $activity_log );
			$current_action = wp_strip_all_tags( $last_message['message'] );
		}

		// Include debug log availability so the frontend can show the button
		// without a separate request.
		$debug_file    = Logger::getFilename();
		$debug_log_url = '';
		if ( file_exists( $debug_file ) ) {
			$uploads_dir   = wp_upload_dir();
			$debug_log_url = $uploads_dir['baseurl'] . '/simply-static/sssbm-debug.txt';
		}

		return json_encode( [
			'status'         => 200,
			'data'           => $activity_log,
			'running'        => Backup::get_process()->is_active(),
			'progress'       => $progress,
			'current_action' => $current_action,
			'debug_log_url'  => $debug_log_url,
		] );
	}

	/**
	 * Get Backup Log
	 *
	 * @return false|string
	 */
	public function get_export_log( $request ) {
		$params     = $request->get_params();
		$export_log = Plugin::instance()->get_export_log( $params['per_page'], $params['page'], $params['blog_id'] );

		return json_encode( [
			'status' => 200,
			'data'   => $export_log,
		] );
	}

	/**
	 * Start Backup
	 *
	 * @return false|string
	 */
	public function start_export( $request ) {
		if ( is_multisite() && is_main_site() ) {
			return json_encode( [
				'status'  => 400,
				'message' => __( 'Multisite backups on network level are not supported.', 'sss-backup-migrate' ),
			] );
		}

		$params  = $request->get_params();
		$blog_id = ! empty( $params['blog_id'] ) ? $params['blog_id'] : 0;
		do_action( 'sssbm_before_perform_action', $blog_id, 'start' );

		// Clear previous activity log before starting new backup.
		Status::clear_messages();

		// Invalidate any previous shared download token.
		delete_transient( 'sssbm_download_token' );

		Backup::start_export();

		do_action( 'sssbm_after_perform_action', $blog_id, 'start' );

		return json_encode( [
			'status' => 200,
		] );
	}

	/**
	 * Cancel Backup
	 *
	 * @param object $request given request.
	 *
	 * @return false|string
	 */
	public function cancel_export( $request ) {
		Logger::log( "Received request to cancel static archive generation" );
		$params = $request->get_params();
		$blog_id = ! empty( $params['blog_id'] ) ? $params['blog_id'] : 0;

		do_action( 'sssbm_before_perform_action', $blog_id, 'cancel' );

		Status::error( __( 'Backup cancelled.', 'sss-backup-migrate' ) );
		delete_transient( 'sssbm_download_token' );
		Backup::clear_all_export_files();
		Backup::get_process()->cancel();
		Backup::set_as_not_running();

		do_action( 'sssbm_after_perform_action', $blog_id, 'cancel' );

		return json_encode( [ 'status' => 200 ] );
	}

	/**
	 * Is running
	 *
	 * @return false|string
	 */
	public function is_running( $request ) {
		// Detect stale/stuck backup processes.
		// If the process is marked as running but the background queue is empty
		// and it's not actually processing, the loopback request likely failed.
		if ( Backup::is_running() && ! Backup::is_completed() ) {
			$process  = Backup::get_process();
			$is_queue_active = $process->is_active();

			if ( ! $is_queue_active ) {
				// The process flag says running, but the queue is empty/inactive.
				// This means the background loopback request failed silently.
				Logger::log( 'Stale backup process detected — process marked as running but queue is inactive. Cleaning up.' );
				Status::error( __( 'Backup failed — the background process could not continue. This usually happens when your hosting blocks loopback HTTP requests. Please try again or contact your hosting provider.', 'sss-backup-migrate' ) );
				Backup::set_as_not_running();
			}
		}

		$backup_url = '';

		$backup_size = 0;

		if ( Backup::is_completed() && file_exists( Backup::get_backup_path() ) ) {
			// Token-based URL — works for both browser downloads and server-side fetching
			// (no cookie/nonce auth required).
			$token = get_transient( 'sssbm_download_token' );

			if ( ! $token ) {
				$token = wp_generate_password( 32, false );
				set_transient( 'sssbm_download_token', $token, 48 * HOUR_IN_SECONDS );
			}

			$backup_url = rest_url( 'sss-backup-migrate/v1/download-backup-shared' );
			$backup_url = add_query_arg( 'token', $token, $backup_url );

			$backup_size = filesize( Backup::get_backup_path() );
		}

		return json_encode( [
			'status'      => 200,
			'running'     => Backup::is_running() || Backup::get_process()->is_active(),
			'paused'      => Backup::get_process()->is_paused(),
			'backup'      => $backup_url,
			'backup_link' => $backup_url,
			'backup_size' => $backup_size,
		] );
	}

	/**
	 * Pause Backup
	 *
	 * @param object $request given request.
	 *
	 * @return false|string
	 */
	public function pause_export( $request ) {
		Logger::log( "Received request to pause static archive generation" );
		$params = $request->get_params();
		$blog_id = ! empty( $params['blog_id'] ) ? $params['blog_id'] : 0;

		do_action( 'sssbm_before_perform_action', $blog_id, 'pause' );

		Backup::get_process()->pause();

		do_action( 'sssbm_after_perform_action', $blog_id, 'pause' );

		return json_encode( [ 'status' => 200 ] );
	}

	/**
	 * Resume Backup
	 *
	 * @param object $request given request.
	 *
	 * @return false|string
	 */
	public function resume_export( $request ) {
		Logger::log( "Received request to resume static archive generation" );
		$params = $request->get_params();
		$blog_id = ! empty( $params['blog_id'] ) ? $params['blog_id'] : 0;

		do_action( 'sssbm_before_perform_action', $blog_id, 'resume' );

		Backup::get_process()->resume();

		do_action( 'sssbm_after_perform_action', $blog_id, 'resume' );

		return json_encode( [ 'status' => 200 ] );
	}

	/**
	 * Get Debug Log URL if it exists.
	 *
	 * @return false|string
	 */
	public function get_debug_log() {
		$debug_file = Logger::getFilename();
		$exists     = file_exists( $debug_file );
		$url        = '';

		if ( $exists ) {
			$uploads_dir = wp_upload_dir();
			$url         = $uploads_dir['baseurl'] . '/simply-static/sssbm-debug.txt';
		}

		return json_encode( [
			'status' => 200,
			'exists' => $exists,
			'url'    => $url,
		] );
	}

	/**
	 * Download backup file via REST API.
	 *
	 * Streams the backup ZIP file through PHP so that it works
	 * on hosting environments where direct file access is blocked
	 * (e.g. nginx restrictions, Basic Auth).
	 *
	 * @return \WP_Error|void
	 */
	public function download_backup() {
		$archive_path = Backup::get_backup_path();

		if ( ! $archive_path || ! file_exists( $archive_path ) ) {
			return new \WP_Error(
				'backup_not_found',
				__( 'Could not download the file.', 'sss-backup-migrate' ),
				[ 'status' => 404 ]
			);
		}

		$filename = basename( $archive_path );
		$filesize = filesize( $archive_path );

		header( 'Content-Type: application/zip' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Content-Length: ' . $filesize );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Flush any existing output buffers.
		while ( ob_get_level() ) {
			ob_end_clean();
		}

		readfile( $archive_path );
		exit;
	}

	/**
	 * Download backup file via a shareable token link.
	 *
	 * Validates a temporary token so the link can be shared
	 * without requiring WordPress authentication.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_Error|void
	 */
	public function download_backup_shared( $request ) {
		$token       = $request->get_param( 'token' );
		$valid_token = get_transient( 'sssbm_download_token' );

		if ( ! $token || ! $valid_token || ! hash_equals( $valid_token, $token ) ) {
			return new \WP_Error(
				'invalid_token',
				__( 'This download link is invalid or has expired.', 'sss-backup-migrate' ),
				[ 'status' => 403 ]
			);
		}

		// Reuse the authenticated download handler.
		return $this->download_backup();
	}

	/**
	 * Supabase API URL.
	 */
	private $supabase_url = 'https://api.static.studio';

	/**
	 * Supabase anon key.
	 */
	private $supabase_anon_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6InlpbmdxZGltdmtlcW9udGFlc3hrIiwicm9sZSI6ImFub24iLCJpYXQiOjE3Mjc3OTA4MzQsImV4cCI6MjA0MzM2NjgzNH0.wgw5P41QmXkdPkek9sUFBWEKTh_8ZVFLTmBwVGohVGM';

	/**
	 * Send OTP to user's email via Supabase auth.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return false|string
	 */
	public function send_otp( $request ) {
		$params = $request->get_params();
		$email  = sanitize_email( $params['email'] ?? '' );

		if ( empty( $email ) ) {
			return json_encode( [ 'status' => 400, 'error' => 'Email is required.' ] );
		}

		$response = wp_remote_post( $this->supabase_url . '/auth/v1/otp', [
			'headers' => [
				'Content-Type' => 'application/json',
				'apikey'       => $this->supabase_anon_key,
			],
			'body'    => json_encode( [
				'email' => $email,
			] ),
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return json_encode( [ 'status' => 500, 'error' => $response->get_error_message() ] );
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code !== 200 ) {
			$body      = json_decode( wp_remote_retrieve_body( $response ), true );
			$error_msg = $body['error_description'] ?? $body['msg'] ?? 'Failed to send verification code.';
			return json_encode( [ 'status' => $code, 'error' => $error_msg ] );
		}

		return json_encode( [
			'status'  => 200,
			'message' => 'Verification code sent to your email.',
		] );
	}

	/**
	 * Verify OTP and connect to Simply Static Studio.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return false|string
	 */
	public function verify_otp( $request ) {
		$params = $request->get_params();
		$email  = sanitize_email( $params['email'] ?? '' );
		$token  = sanitize_text_field( $params['token'] ?? '' );

		if ( empty( $email ) || empty( $token ) ) {
			return json_encode( [ 'status' => 400, 'error' => 'Email and verification code are required.' ] );
		}

		$response = wp_remote_post( $this->supabase_url . '/auth/v1/verify', [
			'headers' => [
				'Content-Type' => 'application/json',
				'apikey'       => $this->supabase_anon_key,
			],
			'body'    => json_encode( [
				'email' => $email,
				'token' => $token,
				'type'  => 'email',
			] ),
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return json_encode( [ 'status' => 500, 'error' => $response->get_error_message() ] );
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 || empty( $body['access_token'] ) ) {
			$error_msg = $body['error_description'] ?? $body['msg'] ?? 'Invalid verification code.';
			return json_encode( [ 'status' => 401, 'error' => $error_msg ] );
		}

		// Store credentials securely.
		update_option( 'sssbm_studio_access_token', $body['access_token'] );
		update_option( 'sssbm_studio_refresh_token', $body['refresh_token'] ?? '' );
		update_option( 'sssbm_studio_user_email', $email );
		update_option( 'sssbm_studio_user_id', $body['user']['id'] ?? '' );
		update_option( 'sssbm_studio_token_expires', time() + ( $body['expires_in'] ?? 3600 ) );

		return json_encode( [
			'status'    => 200,
			'connected' => true,
			'email'     => $email,
		] );
	}

	/**
	 * Disconnect from Simply Static Studio.
	 *
	 * @return false|string
	 */
	public function disconnect_from_studio() {
		delete_option( 'sssbm_studio_access_token' );
		delete_option( 'sssbm_studio_refresh_token' );
		delete_option( 'sssbm_studio_user_email' );
		delete_option( 'sssbm_studio_user_id' );
		delete_option( 'sssbm_studio_token_expires' );

		return json_encode( [
			'status'    => 200,
			'connected' => false,
		] );
	}

	/**
	 * Get current connection status.
	 *
	 * @return false|string
	 */
	public function get_connection_status() {
		$token = get_option( 'sssbm_studio_access_token', '' );
		$email = get_option( 'sssbm_studio_user_email', '' );

		return json_encode( [
			'status'    => 200,
			'connected' => ! empty( $token ),
			'email'     => $email,
		] );
	}

	/**
	 * Refresh the Supabase access token if expired.
	 *
	 * @return string|false The access token or false on failure.
	 */
	private function refresh_studio_token() {
		$access_token  = get_option( 'sssbm_studio_access_token', '' );
		$refresh_token = get_option( 'sssbm_studio_refresh_token', '' );
		$expires       = (int) get_option( 'sssbm_studio_token_expires', 0 );

		if ( empty( $access_token ) ) {
			return false;
		}

		// If token is still valid (with 60s buffer), return it.
		if ( $expires > time() + 60 ) {
			return $access_token;
		}

		// Try to refresh.
		if ( empty( $refresh_token ) ) {
			return false;
		}

		$response = wp_remote_post( $this->supabase_url . '/auth/v1/token?grant_type=refresh_token', [
			'headers' => [
				'Content-Type' => 'application/json',
				'apikey'       => $this->supabase_anon_key,
			],
			'body'    => json_encode( [
				'refresh_token' => $refresh_token,
			] ),
			'timeout' => 30,
		] );

		if ( is_wp_error( $response ) ) {
			return false;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code !== 200 || empty( $body['access_token'] ) ) {
			// Refresh failed — clear stored credentials.
			delete_option( 'sssbm_studio_access_token' );
			delete_option( 'sssbm_studio_refresh_token' );
			delete_option( 'sssbm_studio_user_email' );
			delete_option( 'sssbm_studio_user_id' );
			delete_option( 'sssbm_studio_token_expires' );
			return false;
		}

		update_option( 'sssbm_studio_access_token', $body['access_token'] );
		update_option( 'sssbm_studio_refresh_token', $body['refresh_token'] ?? $refresh_token );
		update_option( 'sssbm_studio_token_expires', time() + ( $body['expires_in'] ?? 3600 ) );

		return $body['access_token'];
	}

	/**
	 * Push backup to Simply Static Studio.
	 *
	 * Flow: init (create site + get signed URL) → upload file → complete (trigger deployment).
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return false|string
	 */
	public function push_to_studio( $request ) {
		if ( is_multisite() && is_main_site() ) {
			return json_encode( [
				'status' => 400,
				'error'  => __( 'Pushing entire multisite networks is not supported.', 'sss-backup-migrate' ),
			] );
		}

		$access_token = $this->refresh_studio_token();

		if ( ! $access_token ) {
			return json_encode( [ 'status' => 401, 'error' => 'Not connected to Simply Static Studio. Please connect first.' ] );
		}

		$archive_path = Backup::get_backup_path();

		if ( ! $archive_path || ! file_exists( $archive_path ) ) {
			return json_encode( [ 'status' => 404, 'error' => 'No backup file found. Please create a backup first.' ] );
		}

		$params      = $request->get_params();
		$site_name   = sanitize_text_field( $params['site_name'] ?? get_bloginfo( 'name' ) );
		$php_version = sanitize_text_field( $params['php_version'] ?? '8.3' );

		// Step 1: Initialize the push migration (creates site, gets signed upload URL).
		$init_response = wp_remote_post( $this->supabase_url . '/functions/v1/push-migration', [
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $access_token,
				'apikey'        => $this->supabase_anon_key,
			],
			'body'    => json_encode( [
				'action'     => 'init',
				'phpVersion' => $php_version,
				'siteName'   => $site_name,
			] ),
			'timeout' => 60,
		] );

		if ( is_wp_error( $init_response ) ) {
			return json_encode( [ 'status' => 500, 'error' => 'Failed to initialize migration: ' . $init_response->get_error_message() ] );
		}

		$init_code = wp_remote_retrieve_response_code( $init_response );
		$init_body = json_decode( wp_remote_retrieve_body( $init_response ), true );

		if ( $init_code !== 201 || empty( $init_body['uploadUrl'] ) ) {
			$error_msg = $init_body['error'] ?? 'Failed to initialize migration.';
			return json_encode( [ 'status' => $init_code, 'error' => $error_msg ] );
		}

		$upload_url        = $init_body['uploadUrl'];
		$upload_token      = $init_body['uploadToken'] ?? '';
		$site_id           = $init_body['siteId'];
		$deployment_params = $init_body['_deploymentParams'];
		$site_url          = $init_body['url'] ?? '';

		// Step 2: Upload the backup file to the signed URL.
		$file_size = filesize( $archive_path );

		// Use cURL for streaming large file uploads.
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $upload_url );
		curl_setopt( $ch, CURLOPT_PUT, true );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 600 );
		curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 30 );

		$fh = fopen( $archive_path, 'rb' );
		curl_setopt( $ch, CURLOPT_INFILE, $fh );
		curl_setopt( $ch, CURLOPT_INFILESIZE, $file_size );

		$upload_headers = [
			'Content-Type: application/zip',
		];

		if ( $upload_token ) {
			$upload_headers[] = 'x-upsert: true';
		}

		curl_setopt( $ch, CURLOPT_HTTPHEADER, $upload_headers );

		$upload_result = curl_exec( $ch );
		$upload_code   = curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$curl_error    = curl_error( $ch );

		curl_close( $ch );
		fclose( $fh );

		if ( $upload_code < 200 || $upload_code >= 300 ) {
			$error_detail = $curl_error ?: $upload_result;
			return json_encode( [ 'status' => 500, 'error' => 'Failed to upload backup file (HTTP ' . $upload_code . '): ' . $error_detail ] );
		}

		// Step 3: Complete the migration (trigger deployment).
		$complete_response = wp_remote_post( $this->supabase_url . '/functions/v1/push-migration', [
			'headers' => [
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $access_token,
				'apikey'        => $this->supabase_anon_key,
			],
			'body'    => json_encode( [
				'action'           => 'complete',
				'siteId'           => $site_id,
				'deploymentParams' => $deployment_params,
			] ),
			'timeout' => 60,
		] );

		if ( is_wp_error( $complete_response ) ) {
			return json_encode( [ 'status' => 500, 'error' => 'Backup uploaded but failed to start deployment: ' . $complete_response->get_error_message() ] );
		}

		$complete_code = wp_remote_retrieve_response_code( $complete_response );
		$complete_body = json_decode( wp_remote_retrieve_body( $complete_response ), true );

		if ( $complete_code !== 200 ) {
			$error_msg = $complete_body['error'] ?? 'Failed to start deployment.';
			return json_encode( [ 'status' => $complete_code, 'error' => 'Backup uploaded but: ' . $error_msg ] );
		}

		return json_encode( [
			'status'  => 200,
			'message' => 'Backup pushed to Simply Static Studio successfully. Your site is being deployed.',
			'siteUrl' => $site_url,
			'siteId'  => $site_id,
		] );
	}

	/**
	 * Delete all backups from the filesystem.
	 *
	 * @return false|string
	 */
	public function delete_all_backups() {
		// Invalidate the shared download token when backups are deleted.
		delete_transient( 'sssbm_download_token' );

		Backup::clear_all_export_files();

		return json_encode( [
			'status'  => 200,
			'message' => __( 'All backups deleted successfully.', 'sss-backup-migrate' ),
		] );
	}
}

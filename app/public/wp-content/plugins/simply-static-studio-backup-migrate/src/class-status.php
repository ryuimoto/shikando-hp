<?php

namespace Simply_Static\Backup;

class Status {

	public static function success( $message, ?string $id = null ) {
		self::message( $message, 'success', $id );
	}

	public static function info( $message, ?string $id = null ) {
		self::message( $message, 'info', $id );
	}

	public static function error( $message, ?string $id = null ) {
		self::message( $message, 'error', $id );
	}

	/**
	 * @param string $message
	 * @param string $type
	 * @param string|null $id
	 *
	 * @return array
	 */
	public static function message( string $message, string $type = 'info', ?string $id = null ) {
		if ( ! $id ) {
			$id = uniqid( 'sssbm_' );
		}

		$message = [
			'id'      => $id,
			'type'    => $type,
			'message' => $message,
			'time'    => current_time( 'timestamp' ),
		];

		return self::add_message( $message );
	}

	public static function clear_messages() {
		delete_option( 'sssbm_messages' );
	}

	public static function add_message( $message ) {
		$messages = self::get_messages();
		$ids      = wp_list_pluck( $messages, 'id' );
		if ( in_array( $message['id'], $ids, true ) ) {
			$messages[ array_search( $message['id'], $ids, true ) ] = $message;
		} else {
			$messages[] = $message;
		}

		self::save_messages( $messages );

		return $messages;
	}

	/**
	 * Get Messages.
	 *
	 * @return false|mixed|null
	 */
	public static function get_messages() {
		return get_option( 'sssbm_messages', [] );
	}

	public static function save_messages( $messages ) {
		return update_option( 'sssbm_messages', $messages );
	}
}
<?php

namespace Simply_Static\Backup;

class Helper {

	/**
	 * Returns the global $wp_filesystem with credentials set.
	 * Returns null in case of any errors.
	 *
	 * @return \WP_Filesystem_Base|null
	 */
	public static function getFileSystem() {
		global $wp_filesystem;

		$success = true;

		// Initialize the file system if it has not been done yet.
		if ( ! $wp_filesystem ) {
			require_once ABSPATH . '/wp-admin/includes/file.php';

			$constants = array(
				'hostname'    => 'FTP_HOST',
				'username'    => 'FTP_USER',
				'password'    => 'FTP_PASS',
				'public_key'  => 'FTP_PUBKEY',
				'private_key' => 'FTP_PRIKEY',
			);

			$credentials = array();

			// We provide credentials based on wp-config.php constants.
			// Reference https://developer.wordpress.org/apis/wp-config-php/#wordpress-upgrade-constants
			foreach ( $constants as $key => $constant ) {
				if ( defined( $constant ) ) {
					$credentials[ $key ] = constant( $constant );
				}
			}

			$success = WP_Filesystem( $credentials );
		}

		if ( ! $success || $wp_filesystem->errors->has_errors() ) {
			return null;
		}

		return $wp_filesystem;
	}

	/**
	 * Cleans a WordPress options SQL INSERT query by removing rows that contain a specific keyword
	 * in the option_name field.
	 *
	 * @todo Test thoroughly before using. Used to remove rows of insert SQL that is not needed such as wc_session from option_value.
	 *
	 * @param string $sql_query The SQL INSERT query to clean
	 * @param string|array $keywords Single keyword or array of keywords to filter out
	 * @return string The cleaned SQL query
	 */
	public static function cleanInsertQuery($sql_query, $keywords) {
		// Convert single keyword to array for consistent processing
		if (!is_array($keywords)) {
			$keywords = [$keywords];
		}

		// Check if this is an INSERT query for wp_options table
		if (strpos($sql_query, 'INSERT INTO') === false || strpos($sql_query, 'wp_options') === false) {
			return $sql_query; // Return original if not a relevant query
		}

		// Extract the header part (everything before VALUES)
		$parts = explode('VALUES', $sql_query, 2);
		if (count($parts) !== 2) {
			return $sql_query; // Return original if format doesn't match expected
		}

		$header = $parts[0];
		$values_section = $parts[1];

		// Find the start and end of the values section
		$values_start = strpos($values_section, '(');
		$values_end = strrpos($values_section, ';');

		if ($values_start === false) {
			return $sql_query; // Return original if no opening parenthesis found
		}

		// Extract just the values part
		$values_content = $values_end !== false
			? substr($values_section, $values_start, $values_end - $values_start)
			: substr($values_section, $values_start);

		// Split into individual rows
		$rows = [];
		$current_row = '';
		$in_quotes = false;
		$escaped = false;
		$paren_level = 0;

		for ($i = 0; $i < strlen($values_content); $i++) {
			$char = $values_content[$i];

			if ( $char === ',' && ! $current_row ) {
				continue;
			}

			if ($escaped) {
				$current_row .= $char;
				$escaped = false;
				continue;
			}

			if ($char === '\\') {
				$current_row .= $char;
				$escaped = true;
				continue;
			}

			if ($char === "'" && !$in_quotes) {
				$in_quotes = true;
				$current_row .= $char;
			} elseif ($char === "'" && $in_quotes) {
				$in_quotes = false;
				$current_row .= $char;
			} elseif ($char === '(' && !$in_quotes) {
				$paren_level++;
				$current_row .= $char;
			} elseif ($char === ')' && !$in_quotes) {
				$paren_level--;
				$current_row .= $char;

				if ($paren_level === 0) {
					// End of a row
					$rows[] = trim($current_row);
					$current_row = '';
				}
			} else {
				$current_row .= $char;
			}
		}

		// Filter out rows containing any of the keywords in the option_name field
		$filtered_rows = [];
		foreach ($rows as $row) {
			$row_values = str_getcsv(trim($row, '()'), ',', "'");

			// Assuming option_name is the second field (index 1)
			if (isset($row_values[1])) {
				$option_name = trim($row_values[1], "'");
				$contains_keyword = false;

				foreach ($keywords as $keyword) {
					if (strpos($option_name, $keyword) !== false) {
						$contains_keyword = true;
						break;
					}
				}

				if (!$contains_keyword) {
					$filtered_rows[] = $row;
				}
			} else {
				// If we can't parse the row properly, include it
				$filtered_rows[] = $row;
			}
		}

		// Reassemble the SQL query
		$cleaned_sql = $header . 'VALUES' . PHP_EOL;
		if (!empty($filtered_rows)) {
			$cleaned_sql .= ' ' . implode(',' . PHP_EOL . ' ', $filtered_rows) . ';';
		} else {
			// If all rows were filtered out, return an empty query or placeholder
			$cleaned_sql = '-- All rows were filtered out from the query';
		}

		return $cleaned_sql;
	}

	/**
	 * Get archive size in bytes
	 *
	 * @return integer
	 */
	public static function getArchiveBytes() {
		return filesize( Backup::get_backup_path() );
	}

	/**
	 * Acquire a lock for ZIP file operations
	 *
	 * @return resource|false Lock file handle or false on failure
	 */
	public static function acquireZipLock() {
		$lock_file = Backup::get_storage_path() . 'backup.lock';
		$lock_handle = fopen( $lock_file, 'w' );

		if ( $lock_handle && flock( $lock_handle, LOCK_EX | LOCK_NB ) ) {
			return $lock_handle;
		}

		if ( $lock_handle ) {
			fclose( $lock_handle );
		}

		return false;
	}

	/**
	 * Release ZIP file lock
	 *
	 * @param resource $lock_handle Lock file handle
	 */
	public static function releaseZipLock( $lock_handle ) {
		if ( $lock_handle ) {
			flock( $lock_handle, LOCK_UN );
			fclose( $lock_handle );

			$lock_file = Backup::get_storage_path() . 'backup.lock';
			if ( file_exists( $lock_file ) ) {
				@unlink( $lock_file );
			}
		}
	}

	/**
	 * Wait for ZIP lock with timeout
	 *
	 * @param int $timeout_seconds Maximum time to wait for lock
	 * @return resource|false Lock file handle or false on timeout
	 */
	public static function waitForZipLock( $timeout_seconds = 30 ) {
		$start_time = time();

		while ( ( time() - $start_time ) < $timeout_seconds ) {
			$lock_handle = self::acquireZipLock();
			if ( $lock_handle ) {
				return $lock_handle;
			}

			// Wait 100ms before trying again
			usleep( 100000 );
		}

		return false;
	}

	/**
	 * Write fields to a file
	 *
	 * @param resource  $handle File handle to write to
	 * @param array     $fields Fields to write to the file
	 * @param string    $separator
	 * @param string    $enclosure
	 * @param string    $escape
	 *
	 * @return integer
	 * @throws \Exception
	 */
	public static function putcsv( $handle, $fields, $separator = ',', $enclosure = '"', $escape = '\\' ) {
		if ( PHP_MAJOR_VERSION >= 7 ) {
			$write_result = @fputcsv( $handle, $fields, $separator, $enclosure, $escape );
		} else {
			$write_result = @fputcsv( $handle, $fields, $separator, $enclosure );
		}

		if ( false === $write_result ) {
			if ( ( $meta = stream_get_meta_data( $handle ) ) ) {
				throw new \Exception(
					sprintf( __( 'Could not write to: %s. The process cannot continue.', 'all-in-one-wp-migration' ), $meta['uri'] )
				);
			}
		}

		return $write_result;
	}

	/**
	 * Read fields from a file
	 *
	 * @param resource  $handle File handle to read from
	 * @param int       $length
	 * @param string    $separator
	 * @param string    $enclosure
	 * @param string    $escape
	 *
	 * @return array|false|null
	 */
	public static function getcsv( $handle, $length = null, $separator = ',', $enclosure = '"', $escape = '\\' ) {
		return fgetcsv( $handle, $length, $separator, $enclosure, $escape );
	}

	/**
	 * Get WordPress theme directories
	 *
	 * @return array
	 */
	public static function get_themes_dirs() {
		$theme_dirs = array();
		foreach ( search_theme_directories() as $theme_name => $theme_info ) {
			if ( isset( $theme_info['theme_root'] ) ) {
				if ( ! in_array( $theme_info['theme_root'], $theme_dirs ) ) {
					$theme_dirs[] = untrailingslashit( $theme_info['theme_root'] );
				}
			}
		}

		return $theme_dirs;
	}

	/**
	 * Get WordPress table prefix by blog ID
	 *
	 * @param  integer $blog_id Blog ID
	 * @return string
	 */
	public static function get_table_prefix( $blog_id = null ) {
		global $wpdb;

		// Set base table prefix
		if ( self::is_mainsite( $blog_id ) ) {
			return $wpdb->base_prefix;
		}

		return $wpdb->base_prefix . $blog_id . '_';
	}

	/**
	 * Check whether blog ID is main site
	 *
	 * @param  integer $blog_id Blog ID
	 * @return boolean
	 */
	public static function is_mainsite( $blog_id = null ) {
		return $blog_id === null || $blog_id === 0 || $blog_id === 1;
	}

	/**
	 * Write contents to a file
	 *
	 * @param  resource $handle  File handle to write to
	 * @param  string   $content Contents to write to the file
	 * @return integer
	 * @throws \Exception
	 */
	public static function write( $handle, $content ) {
		$write_result = @fwrite( $handle, $content );
		if ( false === $write_result ) {
			if ( ( $meta = stream_get_meta_data( $handle ) ) ) {
				throw new \Exception(
					sprintf( __( 'Could not write to: %s. The process cannot continue.', 'all-in-one-wp-migration' ), $meta['uri'] )
				);
			}
		} elseif ( null === $write_result ) {
			return strlen( $content );
		} elseif ( strlen( $content ) !== $write_result ) {
			if ( ( $meta = stream_get_meta_data( $handle ) ) ) {
				throw new \Exception(
					sprintf( __( 'Out of disk space. Could not write to: %s. The process cannot continue.', 'all-in-one-wp-migration' ), $meta['uri'] )
				);
			}
		}

		return $write_result;
	}
}

<?php
namespace Hotel_Management\Includes;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * Lightweight Environment (.env) Loader
 * Automatically detects and parses .env files into environment variables and constants.
 */
class Hotel_Env {

	/**
	 * Load .env file from common candidate paths.
	 */
	public static function load() {
		$candidate_paths = [
			// Plugin directory root
			HOTEL_MGMT_PATH . '.env',
			// WordPress content directory parent (site root)
			dirname( dirname( dirname( __DIR__ ) ) ) . '/.env',
			// If inside standard wp-content/plugins/hotel-management
			dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/.env',
		];

		if ( defined( 'ABSPATH' ) ) {
			$candidate_paths[] = ABSPATH . '.env';
			$candidate_paths[] = dirname( ABSPATH ) . '/.env';
		}

		foreach ( $candidate_paths as $path ) {
			if ( file_exists( $path ) && is_readable( $path ) ) {
				self::parse_file( $path );
				break;
			}
		}
	}

	/**
	 * Parse .env file line by line.
	 *
	 * @param string $file_path
	 */
	private static function parse_file( $file_path ) {
		$lines = file( $file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );

		if ( ! is_array( $lines ) ) {
			return;
		}

		foreach ( $lines as $line ) {
			$line = trim( $line );

			// Skip comments or empty lines
			if ( empty( $line ) || strpos( $line, '#' ) === 0 ) {
				continue;
			}

			// Key=Value split
			if ( strpos( $line, '=' ) !== false ) {
				list( $key, $value ) = explode( '=', $line, 2 );
				$key   = trim( $key );
				$value = trim( $value );

				// Strip surrounding quotes
				if ( ( str_starts_with( $value, '"' ) && str_ends_with( $value, '"' ) ) ||
				     ( str_starts_with( $value, "'" ) && str_ends_with( $value, "'" ) ) ) {
					$value = substr( $value, 1, -1 );
				}

				// Set environment variable
				putenv( "{$key}={$value}" );
				$_ENV[ $key ]    = $value;
				$_SERVER[ $key ] = $value;

				// Define PHP constant if not already defined
				if ( ! defined( $key ) ) {
					define( $key, $value );
				}
			}
		}
	}
}


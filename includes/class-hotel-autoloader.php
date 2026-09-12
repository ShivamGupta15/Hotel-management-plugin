<?php
namespace Hotel_Management\Includes;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * PSR-4 Compatible Autoloader for Hotel Management Plugin
 */
class Hotel_Autoloader {

	/**
	 * Register the autoloader with SPL.
	 */
	public static function register() {
		spl_autoload_register( [ __CLASS__, 'autoload' ] );
	}

	/**
	 * Load class files dynamically based on namespace.
	 *
	 * @param string $class Fully qualified class name.
	 */
	public static function autoload( $class ) {
		$prefix = 'Hotel_Management\\';
		$len    = strlen( $prefix );

		if ( strncmp( $prefix, $class, $len ) !== 0 ) {
			return;
		}

		$relative_class = substr( $class, $len );
		$parts          = explode( '\\', $relative_class );

		if ( empty( $parts ) ) {
			return;
		}

		$class_name = array_pop( $parts );
		$sub_dirs   = strtolower( implode( DIRECTORY_SEPARATOR, $parts ) );

		// Convert Class_Name to class-hotel-name.php format or class-name.php
		$formatted_file = 'class-' . strtolower( str_replace( '_', '-', $class_name ) ) . '.php';

		$file = HOTEL_MGMT_PATH . ( ! empty( $sub_dirs ) ? $sub_dirs . DIRECTORY_SEPARATOR : '' ) . $formatted_file;

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}


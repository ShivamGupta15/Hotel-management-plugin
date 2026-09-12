<?php
namespace Hotel_Management\Includes;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * Fired during plugin deactivation.
 */
class Hotel_Deactivator {

	/**
	 * Deactivation cleanup: unschedule crons.
	 */
	public static function deactivate() {
		$timestamp = wp_next_scheduled( 'hotel_cleanup_expired_holds' );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, 'hotel_cleanup_expired_holds' );
		}
		flush_rewrite_rules();
	}
}


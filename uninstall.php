<?php
/**
 * Fired when the Hotel Management plugin is uninstalled.
 *
 * @package Hotel_Management
 */

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// 1. Clear scheduled cron tasks
wp_clear_scheduled_hook( 'hotel_cleanup_expired_holds' );

// 2. Clear plugin transients and temporary caches
delete_transient( 'hotel_active_holds_cache' );
delete_transient( 'hotel_daily_rates_cache' );
delete_transient( 'hotel_availability_cache' );

// Note: Core transactional tables (wp_hotel_bookings, wp_hotel_payments,
// wp_hotel_inventory_calendar, etc.) are intentionally preserved to prevent
// catastrophic data loss during routine plugin upgrades or reinstallation.
// If complete database removal is desired, database administrators should
// manually drop the `wp_hotel_*` tables or run a dedicated purge script.


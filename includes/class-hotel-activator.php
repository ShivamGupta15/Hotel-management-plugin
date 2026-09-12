<?php
namespace Hotel_Management\Includes;

use Hotel_Management\Database\Hotel_Schema;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * Fired during plugin activation.
 */
class Hotel_Activator {

	/**
	 * Activate plugin, setup database, roles, capabilities, and cron jobs.
	 */
	public static function activate() {
		// 1. Create/migrate database tables and seed initial data
		Hotel_Schema::create_tables();

		// 2. Register custom hotel roles & capabilities
		self::register_roles_and_caps();

		// 3. Schedule cron for expired hold cleanup (every minute)
		if ( ! wp_next_scheduled( 'hotel_cleanup_expired_holds' ) ) {
			wp_schedule_event( time(), 'hotel_every_minute', 'hotel_cleanup_expired_holds' );
		}

		// Flush rewrite rules for custom REST endpoints
		flush_rewrite_rules();
	}

	/**
	 * Register hotel management roles and capabilities.
	 */
	private static function register_roles_and_caps() {
		// Hotel Administrator
		add_role(
			'hotel_administrator',
			__( 'Hotel Administrator', 'hotel-management' ),
			[
				'read'                  => true,
				'manage_hotel_all'      => true,
				'manage_hotel_settings' => true,
				'manage_hotel_rates'    => true,
				'manage_hotel_rooms'    => true,
				'manage_hotel_bookings' => true,
				'checkin_hotel_guests'  => true,
				'view_hotel_reports'    => true,
			]
		);

		// Hotel Manager
		add_role(
			'hotel_manager',
			__( 'Hotel Manager', 'hotel-management' ),
			[
				'read'                  => true,
				'manage_hotel_rates'    => true,
				'manage_hotel_rooms'    => true,
				'manage_hotel_bookings' => true,
				'checkin_hotel_guests'  => true,
				'view_hotel_reports'    => true,
			]
		);

		// Front Desk Staff
		add_role(
			'hotel_frontdesk',
			__( 'Hotel Front Desk', 'hotel-management' ),
			[
				'read'                  => true,
				'manage_hotel_bookings' => true,
				'checkin_hotel_guests'  => true,
			]
		);

		// Hotel Guest
		add_role(
			'hotel_guest',
			__( 'Hotel Guest', 'hotel-management' ),
			[
				'read'                   => true,
				'view_own_hotel_bookings'=> true,
			]
		);

		// Grant all hotel capabilities to WP site Administrator
		$admin_role = get_role( 'administrator' );
		if ( $admin_role ) {
			$admin_role->add_cap( 'manage_hotel_all' );
			$admin_role->add_cap( 'manage_hotel_settings' );
			$admin_role->add_cap( 'manage_hotel_rates' );
			$admin_role->add_cap( 'manage_hotel_rooms' );
			$admin_role->add_cap( 'manage_hotel_bookings' );
			$admin_role->add_cap( 'checkin_hotel_guests' );
			$admin_role->add_cap( 'view_hotel_reports' );
		}
	}
}


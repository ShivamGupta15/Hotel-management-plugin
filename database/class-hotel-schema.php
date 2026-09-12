<?php
namespace Hotel_Management\Database;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * Database Schema Manager
 * Handles table creation, migrations, indexes, and initial data seeding.
 */
class Hotel_Schema {

	/**
	 * Run migrations to create or update all custom InnoDB hotel tables.
	 */
	public static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		// 1. Hotel Properties
		$table_properties = $wpdb->prefix . 'hotel_properties';
		$sql_properties   = "CREATE TABLE {$table_properties} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			name varchar(191) NOT NULL,
			slug varchar(191) NOT NULL,
			description text NULL,
			address text NULL,
			city varchar(100) NULL,
			country varchar(100) DEFAULT 'India',
			phone varchar(50) NULL,
			email varchar(100) NULL,
			currency varchar(10) DEFAULT 'INR',
			check_in_time varchar(20) DEFAULT '14:00',
			check_out_time varchar(20) DEFAULT '11:00',
			tax_percentage decimal(5,2) DEFAULT '12.00',
			status varchar(50) DEFAULT 'active',
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY slug (slug)
		) ENGINE=InnoDB {$charset_collate};";
		dbDelta( $sql_properties );

		// 2. Room Types (Categories)
		$table_room_types = $wpdb->prefix . 'hotel_room_types';
		$sql_room_types   = "CREATE TABLE {$table_room_types} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			property_id bigint(20) unsigned NOT NULL DEFAULT 1,
			title varchar(191) NOT NULL,
			slug varchar(191) NOT NULL,
			description longtext NULL,
			short_description text NULL,
			base_price decimal(10,2) NOT NULL DEFAULT '0.00',
			weekend_price decimal(10,2) NOT NULL DEFAULT '0.00',
			max_occupancy int(11) NOT NULL DEFAULT 2,
			base_occupancy int(11) NOT NULL DEFAULT 2,
			extra_person_fee decimal(10,2) NOT NULL DEFAULT '0.00',
			total_inventory int(11) NOT NULL DEFAULT 1,
			size_sqft int(11) NULL DEFAULT 350,
			bed_type varchar(100) DEFAULT 'King Bed',
			amenities longtext NULL,
			gallery_images longtext NULL,
			featured_image text NULL,
			status varchar(50) DEFAULT 'active',
			sort_order int(11) DEFAULT 0,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY property_id (property_id),
			UNIQUE KEY slug (slug)
		) ENGINE=InnoDB {$charset_collate};";
		dbDelta( $sql_room_types );

		// 3. Physical Rooms
		$table_physical_rooms = $wpdb->prefix . 'hotel_physical_rooms';
		$sql_physical_rooms   = "CREATE TABLE {$table_physical_rooms} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			property_id bigint(20) unsigned NOT NULL DEFAULT 1,
			room_type_id bigint(20) unsigned NOT NULL,
			room_number varchar(50) NOT NULL,
			floor varchar(50) DEFAULT '1st Floor',
			housekeeping_status enum('clean','dirty','inspecting','maintenance') DEFAULT 'clean',
			operational_status enum('available','out_of_order') DEFAULT 'available',
			notes text NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY property_room (property_id, room_number),
			KEY room_type_id (room_type_id)
		) ENGINE=InnoDB {$charset_collate};";
		dbDelta( $sql_physical_rooms );

		// 4. Inventory Calendar (Nightly Concurrency Core)
		$table_inventory = $wpdb->prefix . 'hotel_inventory_calendar';
		$sql_inventory   = "CREATE TABLE {$table_inventory} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			property_id bigint(20) unsigned NOT NULL DEFAULT 1,
			room_type_id bigint(20) unsigned NOT NULL,
			stay_date date NOT NULL,
			total_rooms int(11) NOT NULL DEFAULT 0,
			booked_rooms int(11) NOT NULL DEFAULT 0,
			held_rooms int(11) NOT NULL DEFAULT 0,
			available_rooms int(11) NOT NULL DEFAULT 0,
			price_override decimal(10,2) NULL,
			is_closed tinyint(1) NOT NULL DEFAULT 0,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY room_date (room_type_id, stay_date),
			KEY date_lookup (property_id, stay_date),
			KEY availability_idx (room_type_id, stay_date, available_rooms)
		) ENGINE=InnoDB {$charset_collate};";
		dbDelta( $sql_inventory );

		// 5. Daily Rates & Seasonal Pricing
		$table_rates = $wpdb->prefix . 'hotel_daily_rates';
		$sql_rates   = "CREATE TABLE {$table_rates} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			property_id bigint(20) unsigned NOT NULL DEFAULT 1,
			room_type_id bigint(20) unsigned NOT NULL,
			start_date date NOT NULL,
			end_date date NOT NULL,
			price_per_night decimal(10,2) NOT NULL,
			weekend_price decimal(10,2) NULL,
			min_nights int(11) DEFAULT 1,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY room_dates (room_type_id, start_date, end_date)
		) ENGINE=InnoDB {$charset_collate};";
		dbDelta( $sql_rates );

		// 6. Bookings
		$table_bookings = $wpdb->prefix . 'hotel_bookings';
		$sql_bookings   = "CREATE TABLE {$table_bookings} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			property_id bigint(20) unsigned NOT NULL DEFAULT 1,
			booking_number varchar(64) NOT NULL,
			user_id bigint(20) unsigned NULL DEFAULT 0,
			guest_name varchar(191) NOT NULL,
			guest_email varchar(191) NOT NULL,
			guest_phone varchar(50) NOT NULL,
			guest_address text NULL,
			check_in_date date NOT NULL,
			check_out_date date NOT NULL,
			total_nights int(11) NOT NULL DEFAULT 1,
			adults int(11) NOT NULL DEFAULT 1,
			children int(11) NOT NULL DEFAULT 0,
			room_count int(11) NOT NULL DEFAULT 1,
			subtotal decimal(10,2) NOT NULL DEFAULT '0.00',
			tax_amount decimal(10,2) NOT NULL DEFAULT '0.00',
			discount_amount decimal(10,2) NOT NULL DEFAULT '0.00',
			total_amount decimal(10,2) NOT NULL DEFAULT '0.00',
			paid_amount decimal(10,2) NOT NULL DEFAULT '0.00',
			balance_amount decimal(10,2) NOT NULL DEFAULT '0.00',
			booking_status enum('draft_held','confirmed','checked_in','checked_out','cancelled','refunded') NOT NULL DEFAULT 'draft_held',
			payment_status enum('unpaid','partially_paid','paid','refunded') NOT NULL DEFAULT 'unpaid',
			payment_method varchar(50) DEFAULT 'razorpay',
			hold_expires_at datetime NULL,
			special_requests text NULL,
			check_in_token varchar(64) NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY booking_number (booking_number),
			KEY guest_email (guest_email),
			KEY check_in_date (check_in_date),
			KEY check_out_date (check_out_date),
			KEY status_idx (booking_status, hold_expires_at)
		) ENGINE=InnoDB {$charset_collate};";
		dbDelta( $sql_bookings );

		// 7. Booking Items
		$table_booking_items = $wpdb->prefix . 'hotel_booking_items';
		$sql_booking_items   = "CREATE TABLE {$table_booking_items} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			room_type_id bigint(20) unsigned NOT NULL,
			physical_room_id bigint(20) unsigned NULL DEFAULT NULL,
			room_name varchar(191) NOT NULL,
			stay_date date NOT NULL,
			price_per_night decimal(10,2) NOT NULL,
			tax_amount decimal(10,2) NOT NULL DEFAULT '0.00',
			status varchar(50) DEFAULT 'active',
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY room_stay (room_type_id, stay_date),
			KEY physical_room (physical_room_id, stay_date)
		) ENGINE=InnoDB {$charset_collate};";
		dbDelta( $sql_booking_items );

		// 8. Payment Records
		$table_payments = $wpdb->prefix . 'hotel_payments';
		$sql_payments   = "CREATE TABLE {$table_payments} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NOT NULL,
			transaction_id varchar(191) NULL,
			gateway varchar(50) DEFAULT 'razorpay',
			order_id varchar(191) NULL,
			signature varchar(255) NULL,
			amount decimal(10,2) NOT NULL,
			currency varchar(10) DEFAULT 'INR',
			status enum('pending','authorized','captured','failed','refunded') NOT NULL DEFAULT 'pending',
			gateway_payload longtext NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY transaction_id (transaction_id),
			KEY order_id (order_id)
		) ENGINE=InnoDB {$charset_collate};";
		dbDelta( $sql_payments );

		// 9. Audit Logs
		$table_audit = $wpdb->prefix . 'hotel_audit_logs';
		$sql_audit   = "CREATE TABLE {$table_audit} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			booking_id bigint(20) unsigned NULL,
			user_id bigint(20) unsigned NULL DEFAULT 0,
			user_role varchar(50) NULL,
			action varchar(100) NOT NULL,
			message text NOT NULL,
			old_value longtext NULL,
			new_value longtext NULL,
			ip_address varchar(45) NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY booking_id (booking_id),
			KEY action_idx (action),
			KEY created_at (created_at)
		) ENGINE=InnoDB {$charset_collate};";
		dbDelta( $sql_audit );

		// Initialize default property and sample room data if first run
		self::seed_initial_data();
	}

	/**
	 * Seed initial sample hotel property, room types, physical rooms, and 90-day inventory calendar.
	 */
	public static function seed_initial_data() {
		global $wpdb;

		$table_properties = $wpdb->prefix . 'hotel_properties';
		$property_exists  = $wpdb->get_var( "SELECT COUNT(*) FROM {$table_properties}" );

		if ( $property_exists > 0 ) {
			return; // Data already exists
		}

		// Insert Default Property
		$wpdb->insert(
			$table_properties,
			[
				'name'           => 'The Grand Azure Luxury Resort & Spa',
				'slug'           => 'grand-azure-resort',
				'description'    => 'An ultra-modern luxury sanctuary featuring panoramic ocean views, world-class spa facilities, and bespoke hospitality.',
				'address'        => 'Beachfront Boulevard, Coastal Haven',
				'city'           => 'Goa',
				'country'        => 'India',
				'phone'          => '+91 98765 43210',
				'email'          => 'reservations@grandazureresort.com',
				'currency'       => 'INR',
				'check_in_time'  => '14:00',
				'check_out_time' => '11:00',
				'tax_percentage' => 12.00,
				'status'         => 'active',
			]
		);
		$property_id = $wpdb->insert_id;

		// Seed Room Types
		$table_room_types     = $wpdb->prefix . 'hotel_room_types';
		$table_physical_rooms = $wpdb->prefix . 'hotel_physical_rooms';

		$sample_types = [
			[
				'title'             => 'Deluxe Ocean Suite',
				'slug'              => 'deluxe-ocean-suite',
				'description'       => 'Spacious contemporary suite with private balcony offering sweeping sea views, plush king-size bed, rain shower, and curated minibar.',
				'short_description' => 'Ocean view, King Bed, Private Balcony, 450 sqft',
				'base_price'        => 6500.00,
				'weekend_price'     => 7800.00,
				'max_occupancy'     => 3,
				'base_occupancy'    => 2,
				'extra_person_fee'  => 1200.00,
				'total_inventory'   => 5,
				'size_sqft'         => 450,
				'bed_type'          => 'King Bed',
				'amenities'         => json_encode( [ 'Free High-Speed Wi-Fi', 'Complimentary Breakfast', 'Private Ocean Balcony', 'Smart 4K TV', 'Espresso Machine', 'Luxury Bathtub' ] ),
				'featured_image'    => 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=1200&q=80',
				'rooms'             => [ '101', '102', '103', '104', '105' ],
				'floor'             => '1st Floor',
			],
			[
				'title'             => 'Executive Garden Villa',
				'slug'              => 'executive-garden-villa',
				'description'       => 'Private standalone villa surrounded by lush tropical landscaping. Includes personal plunge pool, open-air outdoor bath, and butler service.',
				'short_description' => 'Private Plunge Pool, King Bed, Garden View, 750 sqft',
				'base_price'        => 12500.00,
				'weekend_price'     => 14900.00,
				'max_occupancy'     => 4,
				'base_occupancy'    => 2,
				'extra_person_fee'  => 2000.00,
				'total_inventory'   => 3,
				'size_sqft'         => 750,
				'bed_type'          => 'California King',
				'amenities'         => json_encode( [ 'Private Plunge Pool', 'Personal Butler Service', 'Free Airport Transfer', 'Outdoor Rain Shower', 'Complimentary Wine & Cheese', 'High-Speed Wi-Fi' ] ),
				'featured_image'    => 'https://images.unsplash.com/photo-1591088398332-8a7791972843?auto=format&fit=crop&w=1200&q=80',
				'rooms'             => [ 'V01', 'V02', 'V03' ],
				'floor'             => 'Ground Floor',
			],
			[
				'title'             => 'Presidential Penthouse',
				'slug'              => 'presidential-penthouse',
				'description'       => 'The pinnacle of luxury hospitality. Top-floor penthouse featuring 360-degree panoramic terrace, private infinity jacuzzi, grand dining room, and 24/7 dedicated concierge.',
				'short_description' => 'Top-Floor Penthouse, Infinity Jacuzzi, 1200 sqft',
				'base_price'        => 24000.00,
				'weekend_price'     => 29000.00,
				'max_occupancy'     => 6,
				'base_occupancy'    => 4,
				'extra_person_fee'  => 3000.00,
				'total_inventory'   => 2,
				'size_sqft'         => 1200,
				'bed_type'          => '2x King Beds',
				'amenities'         => json_encode( [ 'Private Rooftop Jacuzzi', 'Dedicated Concierge', 'Chef-prepared Gourmet Dining', 'Private Bar & Cellar', 'Walk-in Dressing Suite', 'VIP Airport Chauffeur' ] ),
				'featured_image'    => 'https://images.unsplash.com/photo-1618773928121-c32242e63f39?auto=format&fit=crop&w=1200&q=80',
				'rooms'             => [ 'PH-01', 'PH-02' ],
				'floor'             => '5th Floor (Penthouse)',
			],
		];

		foreach ( $sample_types as $type_data ) {
			$rooms = $type_data['rooms'];
			$floor = $type_data['floor'];
			unset( $type_data['rooms'], $type_data['floor'] );

			$type_data['property_id'] = $property_id;
			$wpdb->insert( $table_room_types, $type_data );
			$room_type_id = $wpdb->insert_id;

			// Insert Physical Rooms
			foreach ( $rooms as $r_num ) {
				$wpdb->insert(
					$table_physical_rooms,
					[
						'property_id'         => $property_id,
						'room_type_id'        => $room_type_id,
						'room_number'         => $r_num,
						'floor'               => $floor,
						'housekeeping_status' => 'clean',
						'operational_status'  => 'available',
					]
				);
			}

			// Generate 90-Day Calendar Inventory
			self::generate_inventory_calendar( $room_type_id, $type_data['total_inventory'], 90, $property_id );
		}
	}

	/**
	 * Generate or refresh daily inventory calendar rows.
	 *
	 * @param int $room_type_id
	 * @param int $total_rooms
	 * @param int $days_ahead
	 * @param int $property_id
	 */
	public static function generate_inventory_calendar( $room_type_id, $total_rooms, $days_ahead = 90, $property_id = 1 ) {
		global $wpdb;
		$table_inventory = $wpdb->prefix . 'hotel_inventory_calendar';

		$today = new \DateTime( 'today' );

		for ( $i = 0; $i < $days_ahead; $i ++ ) {
			$current_date = clone $today;
			$current_date->modify( "+{$i} days" );
			$date_str = $current_date->format( 'Y-m-d' );

			$sql = $wpdb->prepare(
				"INSERT INTO {$table_inventory} (property_id, room_type_id, stay_date, total_rooms, booked_rooms, held_rooms, available_rooms)
				VALUES (%d, %d, %s, %d, 0, 0, %d)
				ON DUPLICATE KEY UPDATE total_rooms = VALUES(total_rooms), available_rooms = VALUES(total_rooms) - booked_rooms - held_rooms",
				$property_id,
				$room_type_id,
				$date_str,
				$total_rooms,
				$total_rooms
			);

			$wpdb->query( $sql );
		}
	}

	/**
	 * Generate or refresh daily inventory calendar rows for a specific date range.
	 *
	 * @param int    $room_type_id
	 * @param int    $total_rooms
	 * @param string $start_date (Y-m-d)
	 * @param string $end_date (Y-m-d)
	 * @param int    $property_id
	 */
	public static function generate_inventory_for_range( $room_type_id, $total_rooms, $start_date, $end_date, $property_id = 1 ) {
		global $wpdb;
		$table_inventory = $wpdb->prefix . 'hotel_inventory_calendar';

		$cur = new \DateTime( $start_date );
		$end = new \DateTime( $end_date );

		while ( $cur <= $end ) {
			$date_str = $cur->format( 'Y-m-d' );

			$sql = $wpdb->prepare(
				"INSERT INTO {$table_inventory} (property_id, room_type_id, stay_date, total_rooms, booked_rooms, held_rooms, available_rooms)
				VALUES (%d, %d, %s, %d, 0, 0, %d)
				ON DUPLICATE KEY UPDATE total_rooms = VALUES(total_rooms), available_rooms = VALUES(total_rooms) - booked_rooms - held_rooms",
				$property_id,
				$room_type_id,
				$date_str,
				$total_rooms,
				$total_rooms
			);

			$wpdb->query( $sql );
			$cur->modify( '+1 day' );
		}
	}
}


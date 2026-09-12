<?php
namespace Hotel_Management\Admin;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * Admin & Staff Operations Controller
 */
class Hotel_Admin {

	public function init() {
		add_action( 'admin_menu', [ $this, 'register_admin_menus' ] );
		add_action( 'admin_init', [ $this, 'register_plugin_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_admin_assets' ] );
	}

	public function register_admin_menus() {
		$main_cap = 'manage_hotel_bookings';

		// Top Level Menu
		add_menu_page(
			__( 'Hotel Management', 'hotel-management' ),
			__( 'Grand Hotel', 'hotel-management' ),
			$main_cap,
			'hotel-management',
			[ $this, 'render_tape_chart_page' ],
			'dashicons-building',
			26
		);

		// Submenu: Tape Chart / Front Desk
		add_submenu_page(
			'hotel-management',
			__( 'Front Desk Tape Chart', 'hotel-management' ),
			__( 'Front Desk (Tape Chart)', 'hotel-management' ),
			$main_cap,
			'hotel-management',
			[ $this, 'render_tape_chart_page' ]
		);

		// Submenu: Rooms & Suites
		add_submenu_page(
			'hotel-management',
			__( 'Rooms & Suites', 'hotel-management' ),
			__( 'Rooms & Suites', 'hotel-management' ),
			'manage_hotel_rooms',
			'hotel-rooms',
			[ $this, 'render_rooms_page' ]
		);

		// Submenu: Inventory & Rates
		add_submenu_page(
			'hotel-management',
			__( 'Inventory & Rates Calendar', 'hotel-management' ),
			__( 'Inventory & Rates', 'hotel-management' ),
			'manage_hotel_rates',
			'hotel-inventory',
			[ $this, 'render_inventory_page' ]
		);

		// Submenu: Bookings
		add_submenu_page(
			'hotel-management',
			__( 'All Bookings', 'hotel-management' ),
			__( 'All Bookings', 'hotel-management' ),
			$main_cap,
			'hotel-bookings',
			[ $this, 'render_bookings_page' ]
		);

		// Submenu: Settings & Integrations (Strictly Administrator Only)
		add_submenu_page(
			'hotel-management',
			__( 'Hotel Settings & APIs', 'hotel-management' ),
			__( 'Settings & APIs', 'hotel-management' ),
			'manage_options',
			'hotel-settings',
			[ $this, 'render_settings_page' ]
		);
	}

	public function register_plugin_settings() {
		$args = [ 'capability' => 'manage_options' ];
		register_setting( 'hotel_settings_group', 'hotel_razorpay_key_id', $args );
		register_setting( 'hotel_settings_group', 'hotel_razorpay_key_secret', $args );
		register_setting( 'hotel_settings_group', 'hotel_razorpay_webhook_secret', $args );
		register_setting( 'hotel_settings_group', 'hotel_razorpay_test_mode', $args );
		register_setting( 'hotel_settings_group', 'hotel_whatsapp_token', $args );
		register_setting( 'hotel_settings_group', 'hotel_whatsapp_phone_id', $args );
		register_setting( 'hotel_settings_group', 'hotel_ai_api_key', $args );
	}

	public function enqueue_admin_assets( $hook ) {
		if ( strpos( $hook, 'hotel' ) === false ) {
			return;
		}

		wp_enqueue_style(
			'hotel-admin-css',
			HOTEL_MGMT_URL . 'admin/assets/css/hotel-admin.css',
			[],
			HOTEL_MGMT_VERSION
		);
	}

	public function render_tape_chart_page() {
		require_once HOTEL_MGMT_PATH . 'admin/views/view-tape-chart.php';
	}

	public function render_rooms_page() {
		require_once HOTEL_MGMT_PATH . 'admin/views/view-rooms.php';
	}

	public function render_inventory_page() {
		require_once HOTEL_MGMT_PATH . 'admin/views/view-inventory.php';
	}

	public function render_bookings_page() {
		require_once HOTEL_MGMT_PATH . 'admin/views/view-bookings.php';
	}

	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( __( 'Access denied. Only site administrators can access Hotel Settings & APIs.', 'hotel-management' ), 403 );
		}
		require_once HOTEL_MGMT_PATH . 'admin/views/view-settings.php';
	}
}


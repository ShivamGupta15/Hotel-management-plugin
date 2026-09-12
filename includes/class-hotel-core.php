<?php
namespace Hotel_Management\Includes;

use Hotel_Management\API\Hotel_REST_Controller;
use Hotel_Management\Admin\Hotel_Admin;
use Hotel_Management\Frontend\Hotel_Frontend;
use Hotel_Management\Services\Hotel_Inventory_Service;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * The core plugin runtime orchestrator.
 */
class Hotel_Core {

	/**
	 * Run the plugin: attach all action and filter hooks.
	 */
	public function run() {
		$this->ensure_database_setup();
		$this->setup_cron_schedules();
		$this->register_rest_routes();
		$this->register_admin();
		$this->register_frontend();
		$this->register_integrations();
	}

	/**
	 * Ensure database tables and sample data exist even if activation was skipped.
	 */
	private function ensure_database_setup() {
		add_action( 'init', function() {
			global $wpdb;
			$table_types = $wpdb->prefix . 'hotel_room_types';
			$exists      = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_types ) );

			if ( ! $exists ) {
				\Hotel_Management\Database\Hotel_Schema::create_tables();
			}
		} );
	}

	/**
	 * Setup custom cron schedules and expired hold listener.
	 */
	private function setup_cron_schedules() {
		add_filter(
			'cron_schedules',
			function( $schedules ) {
				$schedules['hotel_every_minute'] = [
					'interval' => 60,
					'display'  => __( 'Every Minute (Hotel Hold TTL)', 'hotel-management' ),
				];
				return $schedules;
			}
		);

		add_action(
			'hotel_cleanup_expired_holds',
			function() {
				if ( class_exists( 'Hotel_Management\Services\Hotel_Inventory_Service' ) ) {
					Hotel_Inventory_Service::cleanup_expired_holds();
				}
			}
		);
	}

	/**
	 * Register Hotel REST API endpoints.
	 */
	private function register_rest_routes() {
		add_action(
			'rest_api_init',
			function() {
				if ( class_exists( 'Hotel_Management\API\Hotel_REST_Controller' ) ) {
					$controller = new Hotel_REST_Controller();
					$controller->register_routes();
				}
			}
		);
	}

	/**
	 * Register Admin Dashboard, Tape Chart, and Settings.
	 */
	private function register_admin() {
		if ( is_admin() && class_exists( 'Hotel_Management\Admin\Hotel_Admin' ) ) {
			$admin = new Hotel_Admin();
			$admin->init();
		}
	}

	/**
	 * Register Frontend Templates, Shortcodes, and Elementor Integration.
	 */
	private function register_frontend() {
		if ( class_exists( 'Hotel_Management\Frontend\Hotel_Frontend' ) ) {
			$frontend = new Hotel_Frontend();
			$frontend->init();
		}
	}

	/**
	 * Register External Integrations (WhatsApp, AI).
	 */
	private function register_integrations() {
		if ( class_exists( 'Hotel_Management\Services\Hotel_WhatsApp_Service' ) ) {
			$whatsapp = new \Hotel_Management\Services\Hotel_WhatsApp_Service();
			$whatsapp->init();
		}

		if ( class_exists( 'Hotel_Management\Services\Hotel_AI_Service' ) ) {
			$ai = new \Hotel_Management\Services\Hotel_AI_Service();
			$ai->init();
		}
	}
}

<?php
namespace Hotel_Management\Frontend;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * Elementor Custom Widgets Integration
 */
class Hotel_Elementor {

	/**
	 * Register widgets with Elementor Widgets Manager.
	 *
	 * @param \Elementor\Widgets_Manager $widgets_manager
	 */
	public static function register( $widgets_manager ) {
		if ( ! class_exists( '\Elementor\Widget_Base' ) ) {
			return;
		}

		$widgets_manager->register( new Hotel_Elementor_Search_Widget() );
		$widgets_manager->register( new Hotel_Elementor_Rooms_Widget() );
		$widgets_manager->register( new Hotel_Elementor_Booking_Widget() );
	}
}

// Declare widget classes at file/namespace level if Elementor is loaded
if ( class_exists( '\Elementor\Widget_Base' ) ) {

	/**
	 * Booking Search Bar Widget
	 */
	class Hotel_Elementor_Search_Widget extends \Elementor\Widget_Base {
		public function get_name() {
			return 'hotel_search_bar';
		}

		public function get_title() {
			return __( 'Hotel Booking Bar', 'hotel-management' );
		}

		public function get_icon() {
			return 'eicon-calendar';
		}

		public function get_categories() {
			return [ 'general' ];
		}

		protected function render() {
			echo do_shortcode( '[hotel_booking_search]' );
		}
	}

	/**
	 * Rooms Showcase Grid Widget
	 */
	class Hotel_Elementor_Rooms_Widget extends \Elementor\Widget_Base {
		public function get_name() {
			return 'hotel_rooms_showcase';
		}

		public function get_title() {
			return __( 'Hotel Rooms Showcase', 'hotel-management' );
		}

		public function get_icon() {
			return 'eicon-gallery-grid';
		}

		public function get_categories() {
			return [ 'general' ];
		}

		protected function render() {
			echo do_shortcode( '[hotel_room_showcase]' );
		}
	}

	/**
	 * Complete Booking Engine Widget
	 */
	class Hotel_Elementor_Booking_Widget extends \Elementor\Widget_Base {
		public function get_name() {
			return 'hotel_booking_engine';
		}

		public function get_title() {
			return __( 'Hotel Complete Booking Engine', 'hotel-management' );
		}

		public function get_icon() {
			return 'eicon-form-horizontal';
		}

		public function get_categories() {
			return [ 'general' ];
		}

		protected function render() {
			echo do_shortcode( '[hotel_booking_engine]' );
		}
	}
}

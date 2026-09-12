<?php
namespace Hotel_Management\API;

use DateTime;
use Exception;
use Hotel_Management\Services\Hotel_Inventory_Service;
use Hotel_Management\Services\Hotel_Pricing_Service;
use Hotel_Management\Services\Hotel_Payment_Service;
use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * REST API Controller for Hotel Management System
 * Exposes endpoints at /wp-json/hotel/v1/...
 */
class Hotel_REST_Controller extends WP_REST_Controller {

	protected $namespace = 'hotel/v1';

	/**
	 * Register all Hotel REST routes.
	 */
	public function register_routes() {
		// 1. Search Availability
		register_rest_route(
			$this->namespace,
			'/availability',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_availability' ],
				'permission_callback' => '__return_true',
				'args'                => [
					'check_in'   => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
					'check_out'  => [ 'required' => true, 'sanitize_callback' => 'sanitize_text_field' ],
					'adults'     => [ 'default' => 2, 'sanitize_callback' => 'absint' ],
					'children'   => [ 'default' => 0, 'sanitize_callback' => 'absint' ],
					'room_count' => [ 'default' => 1, 'sanitize_callback' => 'absint' ],
				],
			]
		);

		// 2. Room Catalog
		register_rest_route(
			$this->namespace,
			'/rooms',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_rooms' ],
				'permission_callback' => '__return_true',
			]
		);

		// 3. Create Reservation Hold & Initiate Razorpay Order
		register_rest_route(
			$this->namespace,
			'/booking/hold',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'create_booking_hold' ],
				'permission_callback' => '__return_true',
			]
		);

		// 4. Verify Payment & Confirm Booking
		register_rest_route(
			$this->namespace,
			'/booking/verify',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'verify_payment_and_confirm' ],
				'permission_callback' => '__return_true',
			]
		);

		// 5. Guest Booking & Invoice Retrieval
		register_rest_route(
			$this->namespace,
			'/guest/booking/(?P<token>[a-zA-Z0-9_-]+)',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_guest_booking' ],
				'permission_callback' => '__return_true',
			]
		);

		// 6. Razorpay Webhook Handler
		register_rest_route(
			$this->namespace,
			'/webhook/razorpay',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'handle_razorpay_webhook' ],
				'permission_callback' => '__return_true',
			]
		);

		// 7. Manager Tape Chart (Occupancy Matrix)
		register_rest_route(
			$this->namespace,
			'/manager/tape-chart',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'get_manager_tape_chart' ],
				'permission_callback' => [ $this, 'check_manager_permission' ],
			]
		);

		// 8. Manager Check-in Action
		register_rest_route(
			$this->namespace,
			'/manager/check-in',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'process_check_in' ],
				'permission_callback' => [ $this, 'check_frontdesk_permission' ],
			]
		);

		// 9. Manager Check-out Action
		register_rest_route(
			$this->namespace,
			'/manager/check-out',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'process_check_out' ],
				'permission_callback' => [ $this, 'check_frontdesk_permission' ],
			]
		);

		// 10. Manager Room Assignment
		register_rest_route(
			$this->namespace,
			'/manager/assign-room',
			[
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => [ $this, 'assign_physical_room' ],
				'permission_callback' => [ $this, 'check_frontdesk_permission' ],
			]
		);
	}

	/**
	 * Search room availability for dates.
	 */
	public function get_availability( WP_REST_Request $request ) {
		global $wpdb;

		$check_in   = sanitize_text_field( $request->get_param( 'check_in' ) );
		$check_out  = sanitize_text_field( $request->get_param( 'check_out' ) );
		$adults     = (int) $request->get_param( 'adults' );
		$children   = (int) $request->get_param( 'children' );
		$room_count = max( 1, (int) $request->get_param( 'room_count' ) );

		try {
			$d_in  = new DateTime( $check_in );
			$d_out = new DateTime( $check_out );
			if ( $d_in >= $d_out ) {
				return new WP_Error( 'invalid_dates', __( 'Check-out date must be after check-in date.', 'hotel-management' ), [ 'status' => 400 ] );
			}
			$nights = (int) $d_in->diff( $d_out )->days;
		} catch ( Exception $e ) {
			return new WP_Error( 'invalid_dates', __( 'Invalid date format provided.', 'hotel-management' ), [ 'status' => 400 ] );
		}

		$table_types = $wpdb->prefix . 'hotel_room_types';
		$table_inv   = $wpdb->prefix . 'hotel_inventory_calendar';

		$room_types = $wpdb->get_results( "SELECT * FROM {$table_types} WHERE status = 'active' ORDER BY sort_order ASC", ARRAY_A );

		$available_list = [];

		foreach ( $room_types as $room ) {
			// Check capacity for adults
			$total_guests = $adults + $children;
			if ( $total_guests > ( (int) $room['max_occupancy'] * $room_count ) ) {
				continue; // Exceeds room category maximum occupancy
			}

			// Check minimum available rooms across all stay dates
			$sql_min_avail = $wpdb->prepare(
				"SELECT MIN(total_rooms - booked_rooms - held_rooms) as min_avail, COUNT(id) as days_recorded
				 FROM {$table_inv}
				 WHERE room_type_id = %d AND stay_date >= %s AND stay_date < %s AND is_closed = 0",
				$room['id'],
				$check_in,
				$check_out
			);

			$inv_stats = $wpdb->get_row( $sql_min_avail, ARRAY_A );
			$days_rec  = isset( $inv_stats['days_recorded'] ) ? (int) $inv_stats['days_recorded'] : 0;
			$has_min   = isset( $inv_stats['min_avail'] ) && $inv_stats['min_avail'] !== null;

			// If calendar rows for this date range are missing or incomplete, auto-generate them immediately
			if ( $days_rec < $nights || ! $has_min ) {
				\Hotel_Management\Database\Hotel_Schema::generate_inventory_for_range( $room['id'], (int) $room['total_inventory'], $check_in, $check_out );
				$inv_stats = $wpdb->get_row( $sql_min_avail, ARRAY_A );
			}

			$min_avail = ( isset( $inv_stats['min_avail'] ) && $inv_stats['min_avail'] !== null )
				? (int) $inv_stats['min_avail']
				: (int) $room['total_inventory'];

			if ( $min_avail >= $room_count ) {
				// Calculate dynamic price quote
				$quote = Hotel_Pricing_Service::calculate_quote(
					(object) $room,
					$check_in,
					$check_out,
					$room_count,
					$adults,
					$children
				);

				$room['amenities_list']      = json_decode( $room['amenities'], true );
				$room['min_available_rooms'] = $min_avail;
				$room['price_quote']         = $quote;

				$available_list[] = $room;
			}
		}

		return new WP_REST_Response(
			[
				'success'     => true,
				'check_in'    => $check_in,
				'check_out'   => $check_out,
				'nights'      => $nights,
				'rooms_found' => count( $available_list ),
				'results'     => $available_list,
			],
			200
		);
	}

	/**
	 * Get Room Catalog.
	 */
	public function get_rooms() {
		global $wpdb;
		$table_types = $wpdb->prefix . 'hotel_room_types';
		$rooms       = $wpdb->get_results( "SELECT * FROM {$table_types} WHERE status = 'active' ORDER BY sort_order ASC", ARRAY_A );

		foreach ( $rooms as &$r ) {
			$r['amenities_list'] = json_decode( $r['amenities'], true );
		}

		return new WP_REST_Response( [ 'success' => true, 'rooms' => $rooms ], 200 );
	}

	/**
	 * Create 15-minute reservation hold and Razorpay order.
	 */
	public function create_booking_hold( WP_REST_Request $request ) {
		$params = $request->get_json_params();

		if ( empty( $params ) ) {
			$params = $request->get_body_params();
		}

		// Validation
		$required = [ 'room_type_id', 'check_in', 'check_out', 'guest_name', 'guest_email', 'guest_phone' ];
		foreach ( $required as $f ) {
			if ( empty( $params[ $f ] ) ) {
				return new WP_Error( 'missing_param', sprintf( __( 'Field %s is required.', 'hotel-management' ), $f ), [ 'status' => 400 ] );
			}
		}

		try {
			// 1. Concurrency-Locked Hold in Database
			$hold = Hotel_Inventory_Service::create_reservation_hold( $params );

			// 2. Create Razorpay Order
			$payment_order = Hotel_Payment_Service::create_order(
				$hold['booking_id'],
				$hold['pricing']['total_amount'],
				'INR'
			);

			return new WP_REST_Response(
				[
					'success'         => true,
					'booking_id'      => $hold['booking_id'],
					'booking_number'  => $hold['booking_number'],
					'check_in_token'  => $hold['check_in_token'],
					'hold_expires_at' => $hold['hold_expires_at'],
					'ttl_seconds'     => $hold['ttl_seconds'],
					'pricing'         => $hold['pricing'],
					'razorpay'        => $payment_order,
				],
				201
			);

		} catch ( Exception $e ) {
			return new WP_Error( 'hold_failed', $e->getMessage(), [ 'status' => 409 ] );
		}
	}

	/**
	 * Verify Razorpay payment and finalize reservation.
	 */
	public function verify_payment_and_confirm( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( empty( $params ) ) {
			$params = $request->get_body_params();
		}

		$booking_id = isset( $params['booking_id'] ) ? (int) $params['booking_id'] : 0;
		$order_id   = isset( $params['razorpay_order_id'] ) ? sanitize_text_field( $params['razorpay_order_id'] ) : '';
		$payment_id = isset( $params['razorpay_payment_id'] ) ? sanitize_text_field( $params['razorpay_payment_id'] ) : '';
		$signature  = isset( $params['razorpay_signature'] ) ? sanitize_text_field( $params['razorpay_signature'] ) : '';

		if ( ! $booking_id || ! $payment_id || ! $order_id ) {
			return new WP_Error( 'invalid_payment_payload', __( 'Incomplete payment verification payload.', 'hotel-management' ), [ 'status' => 400 ] );
		}

		// Verify cryptographic signature
		$is_valid = Hotel_Payment_Service::verify_signature( $order_id, $payment_id, $signature );

		if ( ! $is_valid ) {
			return new WP_Error( 'signature_mismatch', __( 'Cryptographic signature verification failed. Payment cannot be verified.', 'hotel-management' ), [ 'status' => 403 ] );
		}

		// Confirm booking in database
		$confirmed = Hotel_Inventory_Service::confirm_booking(
			$booking_id,
			$payment_id,
			'razorpay',
			$params
		);

		if ( ! $confirmed ) {
			return new WP_Error( 'confirmation_error', __( 'Failed confirming booking after payment.', 'hotel-management' ), [ 'status' => 500 ] );
		}

		global $wpdb;
		$table_bookings = $wpdb->prefix . 'hotel_bookings';
		$booking        = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_bookings} WHERE id = %d", $booking_id ), ARRAY_A );

		return new WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Payment successfully verified. Booking confirmed!', 'hotel-management' ),
				'booking' => $booking,
			],
			200
		);
	}

	/**
	 * Guest booking lookup via secure token.
	 */
	public function get_guest_booking( WP_REST_Request $request ) {
		global $wpdb;

		$token = sanitize_text_field( $request->get_param( 'token' ) );

		$table_bookings = $wpdb->prefix . 'hotel_bookings';
		$table_items    = $wpdb->prefix . 'hotel_booking_items';
		$table_props    = $wpdb->prefix . 'hotel_properties';

		$booking = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table_bookings} WHERE check_in_token = %s", $token ),
			ARRAY_A
		);

		if ( ! $booking ) {
			return new WP_Error( 'not_found', __( 'Booking not found.', 'hotel-management' ), [ 'status' => 404 ] );
		}

		$items    = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table_items} WHERE booking_id = %d", $booking['id'] ), ARRAY_A );
		$property = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_props} WHERE id = %d", $booking['property_id'] ), ARRAY_A );

		return new WP_REST_Response(
			[
				'success'  => true,
				'booking'  => $booking,
				'items'    => $items,
				'property' => $property,
			],
			200
		);
	}

	/**
	 * Handle Razorpay Webhooks.
	 */
	public function handle_razorpay_webhook( WP_REST_Request $request ) {
		$raw_body  = $request->get_body();
		$signature = $request->get_header( 'X-Razorpay-Signature' );

		$webhook_secret = get_option( 'hotel_razorpay_webhook_secret', '' );

		if ( ! empty( $webhook_secret ) ) {
			$expected = hash_hmac( 'sha256', $raw_body, $webhook_secret );
			if ( ! hash_equals( $expected, (string) $signature ) ) {
				return new WP_REST_Response( [ 'status' => 'invalid_signature' ], 400 );
			}
		}

		$data = json_decode( $raw_body, true );

		if ( isset( $data['event'] ) && $data['event'] === 'payment.captured' ) {
			$payment = $data['payload']['payment']['entity'];
			$notes   = isset( $payment['notes'] ) ? $payment['notes'] : [];

			if ( ! empty( $notes['booking_id'] ) ) {
				Hotel_Inventory_Service::confirm_booking(
					(int) $notes['booking_id'],
					$payment['id'],
					'razorpay_webhook',
					$payment
				);
			}
		}

		return new WP_REST_Response( [ 'status' => 'ok' ], 200 );
	}

	/**
	 * Manager Tape Chart data.
	 */
	public function get_manager_tape_chart( WP_REST_Request $request ) {
		global $wpdb;

		$start_date = sanitize_text_field( $request->get_param( 'start_date' ) );
		$days       = max( 7, min( 31, (int) $request->get_param( 'days' ) ) );

		if ( empty( $start_date ) ) {
			$start_date = gmdate( 'Y-m-d' );
		}

		$d_start = new DateTime( $start_date );
		$d_end   = clone $d_start;
		$d_end->modify( "+{$days} days" );

		$table_types = $wpdb->prefix . 'hotel_room_types';
		$table_rooms = $wpdb->prefix . 'hotel_physical_rooms';
		$table_items = $wpdb->prefix . 'hotel_booking_items';
		$table_bks   = $wpdb->prefix . 'hotel_bookings';

		// Physical rooms with their type
		$rooms = $wpdb->get_results(
			"SELECT r.*, t.title as room_type_name, t.base_price
			 FROM {$table_rooms} r
			 LEFT JOIN {$table_types} t ON r.room_type_id = t.id
			 ORDER BY r.floor ASC, r.room_number ASC",
			ARRAY_A
		);

		// Bookings active in this date range
		$sql_bookings = $wpdb->prepare(
			"SELECT b.id, b.booking_number, b.guest_name, b.guest_phone, b.check_in_date, b.check_out_date,
			        b.booking_status, b.payment_status, i.physical_room_id, i.room_type_id, i.stay_date
			 FROM {$table_bks} b
			 JOIN {$table_items} i ON b.id = i.booking_id
			 WHERE b.booking_status IN ('confirmed', 'checked_in', 'draft_held')
			   AND i.stay_date >= %s AND i.stay_date < %s",
			$d_start->format( 'Y-m-d' ),
			$d_end->format( 'Y-m-d' )
		);

		$allocations = $wpdb->get_results( $sql_bookings, ARRAY_A );

		return new WP_REST_Response(
			[
				'success'     => true,
				'start_date'  => $d_start->format( 'Y-m-d' ),
				'end_date'    => $d_end->format( 'Y-m-d' ),
				'days'        => $days,
				'rooms'       => $rooms,
				'allocations' => $allocations,
			],
			200
		);
	}

	/**
	 * Process Guest Check-in.
	 */
	public function process_check_in( WP_REST_Request $request ) {
		global $wpdb;
		$booking_id       = (int) $request->get_param( 'booking_id' );
		$physical_room_id = (int) $request->get_param( 'physical_room_id' );

		$table_bks   = $wpdb->prefix . 'hotel_bookings';
		$table_items = $wpdb->prefix . 'hotel_booking_items';
		$table_rooms = $wpdb->prefix . 'hotel_physical_rooms';

		$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_bks} WHERE id = %d", $booking_id ) );
		if ( ! $booking ) {
			return new WP_Error( 'not_found', __( 'Booking not found.', 'hotel-management' ), [ 'status' => 404 ] );
		}

		$wpdb->update(
			$table_bks,
			[ 'booking_status' => 'checked_in', 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ],
			[ 'id' => $booking_id ]
		);

		if ( $physical_room_id > 0 ) {
			$wpdb->update(
				$table_items,
				[ 'physical_room_id' => $physical_room_id ],
				[ 'booking_id' => $booking_id ]
			);

			$wpdb->update(
				$table_rooms,
				[ 'operational_status' => 'available' ],
				[ 'id' => $physical_room_id ]
			);
		}

		return new WP_REST_Response( [ 'success' => true, 'message' => __( 'Guest successfully checked-in.', 'hotel-management' ) ], 200 );
	}

	/**
	 * Process Guest Check-out.
	 */
	public function process_check_out( WP_REST_Request $request ) {
		global $wpdb;
		$booking_id = (int) $request->get_param( 'booking_id' );

		$table_bks   = $wpdb->prefix . 'hotel_bookings';
		$table_items = $wpdb->prefix . 'hotel_booking_items';
		$table_rooms = $wpdb->prefix . 'hotel_physical_rooms';

		$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_bks} WHERE id = %d", $booking_id ) );
		if ( ! $booking ) {
			return new WP_Error( 'not_found', __( 'Booking not found.', 'hotel-management' ), [ 'status' => 404 ] );
		}

		$wpdb->update(
			$table_bks,
			[ 'booking_status' => 'checked_out', 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ],
			[ 'id' => $booking_id ]
		);

		// Mark assigned physical room as 'dirty' for housekeeping
		$assigned_rooms = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT physical_room_id FROM {$table_items} WHERE booking_id = %d AND physical_room_id IS NOT NULL", $booking_id ) );

		foreach ( $assigned_rooms as $r_id ) {
			$wpdb->update(
				$table_rooms,
				[ 'housekeeping_status' => 'dirty' ],
				[ 'id' => $r_id ]
			);
		}

		return new WP_REST_Response( [ 'success' => true, 'message' => __( 'Guest checked out. Housekeeping notified.', 'hotel-management' ) ], 200 );
	}

	/**
	 * Assign physical room to booking items.
	 */
	public function assign_physical_room( WP_REST_Request $request ) {
		global $wpdb;
		$booking_id       = (int) $request->get_param( 'booking_id' );
		$physical_room_id = (int) $request->get_param( 'physical_room_id' );

		$table_items = $wpdb->prefix . 'hotel_booking_items';
		$wpdb->update(
			$table_items,
			[ 'physical_room_id' => $physical_room_id ],
			[ 'booking_id' => $booking_id ]
		);

		return new WP_REST_Response( [ 'success' => true, 'message' => __( 'Room successfully assigned.', 'hotel-management' ) ], 200 );
	}

	/**
	 * Permission Checks
	 */
	public function check_manager_permission() {
		return current_user_can( 'manage_hotel_bookings' ) || current_user_can( 'manage_options' );
	}

	public function check_frontdesk_permission() {
		return current_user_can( 'checkin_hotel_guests' ) || current_user_can( 'manage_hotel_bookings' ) || current_user_can( 'manage_options' );
	}
}


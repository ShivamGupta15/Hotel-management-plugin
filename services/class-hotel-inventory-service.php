<?php
namespace Hotel_Management\Services;

use DateTime;
use Exception;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * Concurrency-Safe Inventory & Booking Lifecycle Service
 * Utilizes InnoDB row-level locking (SELECT ... FOR UPDATE) inside transactions to prevent race conditions.
 */
class Hotel_Inventory_Service {

	const HOLD_DURATION_MINUTES = 15;

	/**
	 * Create a temporary concurrency-locked reservation hold.
	 *
	 * @param array $params Booking parameters:
	 *                      - property_id (int)
	 *                      - room_type_id (int)
	 *                      - check_in (string Y-m-d)
	 *                      - check_out (string Y-m-d)
	 *                      - room_count (int)
	 *                      - adults (int)
	 *                      - children (int)
	 *                      - guest_name (string)
	 *                      - guest_email (string)
	 *                      - guest_phone (string)
	 *                      - special_requests (string)
	 *
	 * @return array Result array with status, booking_id, booking_number, hold_expires_at, total_amount
	 * @throws Exception On concurrency conflict or validation failure.
	 */
	public static function create_reservation_hold( array $params ) {
		global $wpdb;

		$property_id  = isset( $params['property_id'] ) ? (int) $params['property_id'] : 1;
		$room_type_id = (int) $params['room_type_id'];
		$check_in     = sanitize_text_field( $params['check_in'] );
		$check_out    = sanitize_text_field( $params['check_out'] );
		$room_count   = isset( $params['room_count'] ) && (int) $params['room_count'] > 0 ? (int) $params['room_count'] : 1;

		$d_in  = new DateTime( $check_in );
		$d_out = new DateTime( $check_out );

		if ( $d_in >= $d_out ) {
			throw new Exception( __( 'Check-out date must be strictly after check-in date.', 'hotel-management' ) );
		}

		$interval     = $d_in->diff( $d_out );
		$total_nights = (int) $interval->days;

		// Fetch room type details
		$table_room_types = $wpdb->prefix . 'hotel_room_types';
		$room_type        = $wpdb->get_row(
			$wpdb->prepare( "SELECT * FROM {$table_room_types} WHERE id = %d AND status = 'active'", $room_type_id )
		);

		if ( ! $room_type ) {
			throw new Exception( __( 'Selected room category is invalid or unavailable.', 'hotel-management' ) );
		}

		// Calculate pricing
		$pricing = Hotel_Pricing_Service::calculate_quote(
			$room_type,
			$check_in,
			$check_out,
			$room_count,
			isset( $params['adults'] ) ? (int) $params['adults'] : 2,
			isset( $params['children'] ) ? (int) $params['children'] : 0
		);

		// BEGIN TRANSACTION
		$wpdb->query( 'START TRANSACTION' );

		try {
			$table_inv = $wpdb->prefix . 'hotel_inventory_calendar';

			// PESSIMISTIC ROW-LEVEL LOCK: Lock all inventory rows for the stay dates
			$sql_lock = $wpdb->prepare(
				"SELECT id, stay_date, total_rooms, booked_rooms, held_rooms, available_rooms, is_closed
				 FROM {$table_inv}
				 WHERE room_type_id = %d AND stay_date >= %s AND stay_date < %s
				 FOR UPDATE",
				$room_type_id,
				$check_in,
				$check_out
			);

			$locked_dates = $wpdb->get_results( $sql_lock, ARRAY_A );

			// Verify that every single night has sufficient capacity
			if ( count( $locked_dates ) < $total_nights ) {
				// Calendar inventory row might be missing for future dates; auto-ensure
				Hotel_Schema::generate_inventory_calendar( $room_type_id, $room_type->total_inventory, 120, $property_id );

				$locked_dates = $wpdb->get_results( $sql_lock, ARRAY_A );
				if ( count( $locked_dates ) < $total_nights ) {
					throw new Exception( __( 'Inventory is not available for all selected dates.', 'hotel-management' ) );
				}
			}

			foreach ( $locked_dates as $night ) {
				if ( ! empty( $night['is_closed'] ) ) {
					throw new Exception( sprintf( __( 'Date %s is closed for reservations.', 'hotel-management' ), $night['stay_date'] ) );
				}

				$current_available = (int) $night['total_rooms'] - (int) $night['booked_rooms'] - (int) $night['held_rooms'];
				if ( $current_available < $room_count ) {
					throw new Exception( sprintf( __( 'No rooms available for date %s. Only %d remaining.', 'hotel-management' ), $night['stay_date'], max( 0, $current_available ) ) );
				}
			}

			// All nights confirmed available! Atomically update held_rooms and available_rooms
			foreach ( $locked_dates as $night ) {
				$new_held      = (int) $night['held_rooms'] + $room_count;
				$new_available = (int) $night['total_rooms'] - (int) $night['booked_rooms'] - $new_held;

				$wpdb->update(
					$table_inv,
					[
						'held_rooms'      => $new_held,
						'available_rooms' => $new_available,
					],
					[ 'id' => $night['id'] ],
					[ '%d', '%d' ],
					[ '%d' ]
				);
			}

			// Generate unique booking number and expiration
			$booking_number  = 'BK-' . gmdate( 'Ymd' ) . '-' . strtoupper( wp_generate_password( 6, false ) );
			$check_in_token  = wp_generate_password( 32, false );
			$hold_expires_at = gmdate( 'Y-m-d H:i:s', time() + ( self::HOLD_DURATION_MINUTES * 60 ) );

			// Insert booking record
			$table_bookings = $wpdb->prefix . 'hotel_bookings';
			$booking_data   = [
				'property_id'      => $property_id,
				'booking_number'   => $booking_number,
				'user_id'          => get_current_user_id() ? get_current_user_id() : 0,
				'guest_name'       => sanitize_text_field( $params['guest_name'] ),
				'guest_email'      => sanitize_email( $params['guest_email'] ),
				'guest_phone'      => sanitize_text_field( $params['guest_phone'] ),
				'check_in_date'    => $check_in,
				'check_out_date'   => $check_out,
				'total_nights'     => $total_nights,
				'adults'           => isset( $params['adults'] ) ? (int) $params['adults'] : 2,
				'children'         => isset( $params['children'] ) ? (int) $params['children'] : 0,
				'room_count'       => $room_count,
				'subtotal'         => $pricing['subtotal'],
				'tax_amount'       => $pricing['tax_amount'],
				'discount_amount'  => 0.00,
				'total_amount'     => $pricing['total_amount'],
				'paid_amount'      => 0.00,
				'balance_amount'   => $pricing['total_amount'],
				'booking_status'   => 'draft_held',
				'payment_status'   => 'unpaid',
				'payment_method'   => isset( $params['payment_method'] ) ? sanitize_text_field( $params['payment_method'] ) : 'razorpay',
				'hold_expires_at'  => $hold_expires_at,
				'special_requests' => isset( $params['special_requests'] ) ? sanitize_textarea_field( $params['special_requests'] ) : '',
				'check_in_token'   => $check_in_token,
				'created_at'       => gmdate( 'Y-m-d H:i:s' ),
			];

			$wpdb->insert( $table_bookings, $booking_data );
			$booking_id = $wpdb->insert_id;

			// Insert booking items (nightly records)
			$table_items = $wpdb->prefix . 'hotel_booking_items';
			foreach ( $pricing['daily_breakdown'] as $nightly ) {
				for ( $r = 0; $r < $room_count; $r++ ) {
					$wpdb->insert(
						$table_items,
						[
							'booking_id'      => $booking_id,
							'room_type_id'    => $room_type_id,
							'room_name'       => $room_type->title,
							'stay_date'       => $nightly['date'],
							'price_per_night' => $nightly['base_rate'],
							'tax_amount'      => $nightly['tax_rate'],
							'status'          => 'held',
						]
					);
				}
			}

			// COMMIT TRANSACTION
			$wpdb->query( 'COMMIT' );

			return [
				'success'         => true,
				'booking_id'      => $booking_id,
				'booking_number'  => $booking_number,
				'hold_expires_at' => $hold_expires_at,
				'ttl_seconds'     => self::HOLD_DURATION_MINUTES * 60,
				'pricing'         => $pricing,
				'check_in_token'  => $check_in_token,
			];

		} catch ( Exception $e ) {
			// ROLLBACK ON ANY CONCURRENCY OR VALIDATION ERROR
			$wpdb->query( 'ROLLBACK' );
			throw $e;
		}
	}

	/**
	 * Confirm booking upon verified payment.
	 *
	 * @param int    $booking_id
	 * @param string $transaction_id
	 * @param string $gateway
	 * @param array  $payload
	 *
	 * @return bool
	 */
	public static function confirm_booking( $booking_id, $transaction_id, $gateway = 'razorpay', $payload = [] ) {
		global $wpdb;

		$table_bookings = $wpdb->prefix . 'hotel_bookings';
		$table_inv      = $wpdb->prefix . 'hotel_inventory_calendar';
		$table_items    = $wpdb->prefix . 'hotel_booking_items';

		$wpdb->query( 'START TRANSACTION' );

		try {
			$booking = $wpdb->get_row(
				$wpdb->prepare( "SELECT * FROM {$table_bookings} WHERE id = %d FOR UPDATE", $booking_id )
			);

			if ( ! $booking ) {
				throw new Exception( __( 'Booking record not found.', 'hotel-management' ) );
			}

			if ( in_array( $booking->booking_status, [ 'confirmed', 'checked_in' ], true ) ) {
				// Already confirmed (idempotent protection)
				$wpdb->query( 'COMMIT' );
				return true;
			}

			// Get items to find room type and dates
			$items = $wpdb->get_results(
				$wpdb->prepare( "SELECT * FROM {$table_items} WHERE booking_id = %d", $booking_id )
			);

			if ( empty( $items ) ) {
				throw new Exception( __( 'Booking items missing.', 'hotel-management' ) );
			}

			// Deduct held_rooms and increment booked_rooms on each night
			$dates_counted = [];
			foreach ( $items as $item ) {
				$key = $item->room_type_id . '_' . $item->stay_date;
				if ( ! isset( $dates_counted[ $key ] ) ) {
					$dates_counted[ $key ] = [
						'room_type_id' => $item->room_type_id,
						'stay_date'    => $item->stay_date,
						'count'        => 0,
					];
				}
				$dates_counted[ $key ]['count']++;
			}

			foreach ( $dates_counted as $entry ) {
				$sql_convert = $wpdb->prepare(
					"UPDATE {$table_inv}
					 SET held_rooms = GREATEST(0, held_rooms - %d),
					     booked_rooms = booked_rooms + %d,
					     available_rooms = total_rooms - booked_rooms - held_rooms
					 WHERE room_type_id = %d AND stay_date = %s",
					$entry['count'],
					$entry['count'],
					$entry['room_type_id'],
					$entry['stay_date']
				);
				$wpdb->query( $sql_convert );
			}

			// Update booking status
			$wpdb->update(
				$table_bookings,
				[
					'booking_status'  => 'confirmed',
					'payment_status'  => 'paid',
					'paid_amount'     => $booking->total_amount,
					'balance_amount'  => 0.00,
					'hold_expires_at' => null,
					'updated_at'      => gmdate( 'Y-m-d H:i:s' ),
				],
				[ 'id' => $booking_id ]
			);

			// Update booking items to active
			$wpdb->update(
				$table_items,
				[ 'status' => 'confirmed' ],
				[ 'booking_id' => $booking_id ]
			);

			// Record Payment
			$table_payments = $wpdb->prefix . 'hotel_payments';
			$wpdb->insert(
				$table_payments,
				[
					'booking_id'      => $booking_id,
					'transaction_id'  => $transaction_id,
					'gateway'         => $gateway,
					'amount'          => $booking->total_amount,
					'currency'        => 'INR',
					'status'          => 'captured',
					'gateway_payload' => ! empty( $payload ) ? json_encode( $payload ) : null,
					'created_at'      => gmdate( 'Y-m-d H:i:s' ),
				]
			);

			// Audit Log
			$table_audit = $wpdb->prefix . 'hotel_audit_logs';
			$wpdb->insert(
				$table_audit,
				[
					'booking_id' => $booking_id,
					'action'     => 'booking_confirmed',
					'message'    => "Payment captured via {$gateway} (Txn: {$transaction_id}). Reservation confirmed.",
					'created_at' => gmdate( 'Y-m-d H:i:s' ),
				]
			);

			$wpdb->query( 'COMMIT' );

			// Trigger notification hook
			do_action( 'hotel_booking_confirmed', $booking_id );

			return true;
		} catch ( Exception $e ) {
			$wpdb->query( 'ROLLBACK' );
			error_log( 'Hotel Management Error [confirm_booking]: ' . $e->getMessage() );
			return false;
		}
	}

	/**
	 * Scheduled background worker: clean up expired draft holds and release inventory back to the pool.
	 */
	public static function cleanup_expired_holds() {
		global $wpdb;

		$table_bookings = $wpdb->prefix . 'hotel_bookings';
		$table_inv      = $wpdb->prefix . 'hotel_inventory_calendar';
		$table_items    = $wpdb->prefix . 'hotel_booking_items';

		$now = gmdate( 'Y-m-d H:i:s' );

		// Find expired holds
		$sql_expired = $wpdb->prepare(
			"SELECT id FROM {$table_bookings}
			 WHERE booking_status = 'draft_held' AND hold_expires_at IS NOT NULL AND hold_expires_at < %s
			 LIMIT 50",
			$now
		);

		$expired_ids = $wpdb->get_col( $sql_expired );

		if ( empty( $expired_ids ) ) {
			return 0;
		}

		$released_count = 0;

		foreach ( $expired_ids as $booking_id ) {
			$wpdb->query( 'START TRANSACTION' );

			try {
				// Lock booking record
				$booking = $wpdb->get_row(
					$wpdb->prepare( "SELECT * FROM {$table_bookings} WHERE id = %d AND booking_status = 'draft_held' FOR UPDATE", $booking_id )
				);

				if ( ! $booking ) {
					$wpdb->query( 'ROLLBACK' );
					continue;
				}

				// Find items to release held rooms
				$items = $wpdb->get_results(
					$wpdb->prepare( "SELECT * FROM {$table_items} WHERE booking_id = %d", $booking_id )
				);

				$dates_to_release = [];
				foreach ( $items as $item ) {
					$key = $item->room_type_id . '_' . $item->stay_date;
					if ( ! isset( $dates_to_release[ $key ] ) ) {
						$dates_to_release[ $key ] = [
							'room_type_id' => $item->room_type_id,
							'stay_date'    => $item->stay_date,
							'count'        => 0,
						];
					}
					$dates_to_release[ $key ]['count']++;
				}

				foreach ( $dates_to_release as $entry ) {
					$sql_release = $wpdb->prepare(
						"UPDATE {$table_inv}
						 SET held_rooms = GREATEST(0, held_rooms - %d),
						     available_rooms = total_rooms - booked_rooms - held_rooms
						 WHERE room_type_id = %d AND stay_date = %s",
						$entry['count'],
						$entry['room_type_id'],
						$entry['stay_date']
					);
					$wpdb->query( $sql_release );
				}

				// Mark booking as cancelled due to TTL expiry
				$wpdb->update(
					$table_bookings,
					[
						'booking_status' => 'cancelled',
						'updated_at'     => $now,
					],
					[ 'id' => $booking_id ]
				);

				$wpdb->update(
					$table_items,
					[ 'status' => 'cancelled' ],
					[ 'booking_id' => $booking_id ]
				);

				$wpdb->query( 'COMMIT' );
				$released_count++;

			} catch ( Exception $e ) {
				$wpdb->query( 'ROLLBACK' );
				error_log( "Failed releasing hold for booking #{$booking_id}: " . $e->getMessage() );
			}
		}

		return $released_count;
	}
}


<?php
namespace Hotel_Management\Services;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * WhatsApp Cloud API Notification Service
 */
class Hotel_WhatsApp_Service {

	public function init() {
		add_action( 'hotel_booking_confirmed', [ $this, 'send_booking_confirmation' ] );
	}

	/**
	 * Send automated WhatsApp booking confirmation to the guest.
	 *
	 * @param int $booking_id
	 */
	public function send_booking_confirmation( $booking_id ) {
		global $wpdb;

		$table_bks   = $wpdb->prefix . 'hotel_bookings';
		$table_items = $wpdb->prefix . 'hotel_booking_items';
		$table_props = $wpdb->prefix . 'hotel_properties';

		$booking = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_bks} WHERE id = %d", $booking_id ) );
		if ( ! $booking || empty( $booking->guest_phone ) ) {
			return;
		}

		$room_name = $wpdb->get_var( $wpdb->prepare( "SELECT room_name FROM {$table_items} WHERE booking_id = %d LIMIT 1", $booking_id ) );
		$property  = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_props} WHERE id = %d", $booking->property_id ) );

		$phone_id = get_option( 'hotel_whatsapp_phone_id' );
		$token    = get_option( 'hotel_whatsapp_token' );

		if ( empty( $phone_id ) && defined( 'HOTEL_WHATSAPP_PHONE_ID' ) ) {
			$phone_id = HOTEL_WHATSAPP_PHONE_ID;
		}
		if ( empty( $token ) && defined( 'HOTEL_WHATSAPP_TOKEN' ) ) {
			$token = HOTEL_WHATSAPP_TOKEN;
		}

		if ( empty( $phone_id ) || empty( $token ) ) {
			// Log simulation when API credentials are not yet configured
			error_log( sprintf( '[WhatsApp Simulated] To: %s | Booking: %s | Confirmed!', $booking->guest_phone, $booking->booking_number ) );
			return;
		}

		$recipient_phone = preg_replace( '/[^0-9]/', '', $booking->guest_phone );
		$pass_url        = home_url( '/?booking_token=' . $booking->check_in_token );

		$message = sprintf(
			"🏨 *%s - Reservation Confirmed!*\n\nDear %s,\nYour booking *#%s* is confirmed.\n\n🛏 *Room:* %s\n📅 *Check-in:* %s (from 14:00)\n📅 *Check-out:* %s (by 11:00)\n💰 *Total Paid:* ₹%s\n\n📱 *View Digital Pass & Invoice:*\n%s\n\nWe look forward to welcoming you!",
			$property ? $property->name : 'The Grand Azure',
			$booking->guest_name,
			$booking->booking_number,
			$room_name ? $room_name : 'Luxury Suite',
			$booking->check_in_date,
			$booking->check_out_date,
			number_format( (float) $booking->paid_amount ),
			$pass_url
		);

		wp_remote_post(
			"https://graph.facebook.com/v19.0/{$phone_id}/messages",
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $token,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode( [
					'messaging_product' => 'whatsapp',
					'recipient_type'    => 'individual',
					'to'                => $recipient_phone,
					'type'              => 'text',
					'text'              => [
						'preview_url' => true,
						'body'        => $message,
					],
				] ),
				'timeout' => 10,
			]
		);
	}
}


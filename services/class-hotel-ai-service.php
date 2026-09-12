<?php
namespace Hotel_Management\Services;

use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * AI Guest Concierge Service
 */
class Hotel_AI_Service {

	public function init() {
		add_action( 'rest_api_init', [ $this, 'register_ai_routes' ] );
	}

	public function register_ai_routes() {
		register_rest_route(
			'hotel/v1',
			'/ai-concierge',
			[
				'methods'             => 'POST',
				'callback'            => [ $this, 'handle_chat' ],
				'permission_callback' => '__return_true',
			]
		);
	}

	public function handle_chat( WP_REST_Request $request ) {
		$params  = $request->get_json_params();
		$message = isset( $params['message'] ) ? sanitize_text_field( $params['message'] ) : '';

		if ( empty( $message ) ) {
			return new WP_REST_Response( [ 'reply' => 'How may I assist your stay with us today?' ], 200 );
		}

		$api_key = get_option( 'hotel_ai_api_key' );
		if ( empty( $api_key ) && defined( 'HOTEL_AI_API_KEY' ) ) {
			$api_key = HOTEL_AI_API_KEY;
		}

		global $wpdb;
		$table_types = $wpdb->prefix . 'hotel_room_types';
		$rooms       = $wpdb->get_results( "SELECT title, base_price, bed_type, amenities, max_occupancy FROM {$table_types} WHERE status = 'active'", ARRAY_A );

		// If OpenAI key is configured, call GPT-4o-mini
		if ( ! empty( $api_key ) ) {
			$system_prompt = "You are the AI Luxury Concierge for The Grand Azure Luxury Resort. Be polite, concise, and helpful. Check-in is 14:00, check-out is 11:00. Available suites:\n" . json_encode( $rooms );

			$response = wp_remote_post(
				'https://api.openai.com/v1/chat/completions',
				[
					'headers' => [
						'Authorization' => 'Bearer ' . $api_key,
						'Content-Type'  => 'application/json',
					],
					'body'    => wp_json_encode( [
						'model'       => 'gpt-4o-mini',
						'messages'    => [
							[ 'role' => 'system', 'content' => $system_prompt ],
							[ 'role' => 'user', 'content' => $message ],
						],
						'max_tokens'  => 180,
						'temperature' => 0.7,
					] ),
					'timeout' => 12,
				]
			);

			if ( ! is_wp_error( $response ) ) {
				$body = json_decode( wp_remote_retrieve_body( $response ), true );
				if ( ! empty( $body['choices'][0]['message']['content'] ) ) {
					return new WP_REST_Response( [ 'reply' => $body['choices'][0]['message']['content'] ], 200 );
				}
			}
		}

		// Built-in intelligent conversational fallback
		$lower = strtolower( $message );
		if ( strpos( $lower, 'check-in' ) !== false || strpos( $lower, 'checkin' ) !== false || strpos( $lower, 'time' ) !== false ) {
			$reply = "Our standard check-in time begins at 14:00 (2:00 PM) and check-out is at 11:00 AM. Early check-in can be requested in your reservation notes!";
		} elseif ( strpos( $lower, 'room' ) !== false || strpos( $lower, 'suite' ) !== false || strpos( $lower, 'villa' ) !== false || strpos( $lower, 'price' ) !== false ) {
			$reply = "We offer three luxurious categories: Deluxe Ocean Suite (from ₹6,500/night), Executive Garden Villa with Private Pool (from ₹12,500/night), and the Presidential Penthouse (from ₹24,000/night). Use our search bar above to lock in current rates!";
		} elseif ( strpos( $lower, 'breakfast' ) !== false || strpos( $lower, 'food' ) !== false || strpos( $lower, 'dine' ) !== false ) {
			$reply = "Complimentary gourmet breakfast is served daily from 07:00 to 10:30 AM at our Azure Sea Breeze restaurant.";
		} elseif ( strpos( $lower, 'cancel' ) !== false || strpos( $lower, 'refund' ) !== false ) {
			$reply = "We offer 100% free cancellation up to 48 hours prior to your check-in date.";
		} else {
			$reply = "Welcome to The Grand Azure! I can assist with suite availability, check-in policies, dining, and amenities. How can I help you plan your stay?";
		}

		return new WP_REST_Response( [ 'reply' => $reply ], 200 );
	}
}


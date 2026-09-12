<?php
namespace Hotel_Management\Services;

use Exception;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * Razorpay Payment Gateway Integration Service
 * Manages order creation, cryptographic signature verification, and sandbox fallbacks.
 */
class Hotel_Payment_Service {

	/**
	 * Get configured Razorpay credentials.
	 *
	 * @return array
	 */
	public static function get_credentials() {
		$key_id     = get_option( 'hotel_razorpay_key_id' );
		$key_secret = get_option( 'hotel_razorpay_key_secret' );
		$test_mode  = get_option( 'hotel_razorpay_test_mode' );

		if ( empty( $key_id ) && defined( 'HOTEL_RAZORPAY_KEY_ID' ) ) {
			$key_id = HOTEL_RAZORPAY_KEY_ID;
		}
		if ( empty( $key_secret ) && defined( 'HOTEL_RAZORPAY_KEY_SECRET' ) ) {
			$key_secret = HOTEL_RAZORPAY_KEY_SECRET;
		}

		$is_test = ( $test_mode !== false && $test_mode !== '' )
			? ( $test_mode === '1' )
			: ( defined( 'HOTEL_RAZORPAY_TEST_MODE' ) ? (bool) HOTEL_RAZORPAY_TEST_MODE : true );

		return [
			'key_id'     => trim( (string) $key_id ),
			'key_secret' => trim( (string) $key_secret ),
			'is_test'    => $is_test,
		];
	}

	/**
	 * Create an order on Razorpay for the given booking.
	 *
	 * @param int    $booking_id
	 * @param float  $amount
	 * @param string $currency
	 *
	 * @return array
	 * @throws Exception
	 */
	public static function create_order( $booking_id, $amount, $currency = 'INR' ) {
		$creds = self::get_credentials();

		$amount_paise = (int) round( $amount * 100 ); // Razorpay amounts are in the smallest currency sub-unit

		$has_keys = ! empty( $creds['key_id'] ) && ! empty( $creds['key_secret'] );
		$is_placeholder = $has_keys && (
			stripos( $creds['key_id'], 'Sample' ) !== false ||
			stripos( $creds['key_secret'], 'Sample' ) !== false
		);

		// If real credentials are provided and not placeholders, call official Razorpay Orders API
		if ( $has_keys && ! $is_placeholder ) {
			$auth = base64_encode( $creds['key_id'] . ':' . $creds['key_secret'] );

			$response = wp_remote_post(
				'https://api.razorpay.com/v1/orders',
				[
					'headers' => [
						'Authorization' => 'Basic ' . $auth,
						'Content-Type'  => 'application/json',
					],
					'body'    => wp_json_encode( [
						'amount'   => $amount_paise,
						'currency' => $currency,
						'receipt'  => 'BK-' . $booking_id,
						'notes'    => [
							'booking_id' => (string) $booking_id,
						],
					] ),
					'timeout' => 8,
				]
			);

			if ( ! is_wp_error( $response ) ) {
				$code = wp_remote_retrieve_response_code( $response );
				$body = json_decode( wp_remote_retrieve_body( $response ), true );

				if ( ( $code === 200 || $code === 201 ) && ! empty( $body['id'] ) ) {
					return [
						'order_id' => $body['id'],
						'amount'   => $amount_paise,
						'currency' => $currency,
						'key_id'   => $creds['key_id'],
						'is_mock'  => false,
					];
				}
			}

			// If API call failed (e.g. offline, unreachable host, or invalid test credentials)
			if ( $creds['is_test'] ) {
				$err_msg = is_wp_error( $response ) ? $response->get_error_message() : ( $body['error']['description'] ?? 'API response code ' . ( $code ?? 'none' ) );
				error_log( '[Hotel Management] Razorpay API unavailable in test mode (' . $err_msg . '). Falling back to sandbox simulation.' );
				return self::create_mock_order( $booking_id, $amount_paise, $currency, $creds['key_id'] );
			}

			// In production live mode, throw explicit exception
			$err_msg = is_wp_error( $response )
				? $response->get_error_message()
				: ( isset( $body['error']['description'] ) ? $body['error']['description'] : 'Failed creating Razorpay order.' );

			throw new Exception( 'Razorpay Gateway Error: ' . $err_msg );
		}

		// Sandbox Mock Order (when keys are not configured or are sample placeholders)
		return self::create_mock_order( $booking_id, $amount_paise, $currency, ! empty( $creds['key_id'] ) ? $creds['key_id'] : 'rzp_test_mockkey123' );
	}

	/**
	 * Generate a sandbox mock order for testing and local development.
	 *
	 * @param int    $booking_id
	 * @param int    $amount_paise
	 * @param string $currency
	 * @param string $key_id
	 * @return array
	 */
	public static function create_mock_order( $booking_id, $amount_paise, $currency = 'INR', $key_id = 'rzp_test_mockkey123' ) {
		return [
			'order_id' => 'order_mock_' . bin2hex( random_bytes( 8 ) ),
			'amount'   => $amount_paise,
			'currency' => $currency,
			'key_id'   => ! empty( $key_id ) ? $key_id : 'rzp_test_mockkey123',
			'is_mock'  => true,
		];
	}

	/**
	 * Verify cryptographic signature of Razorpay payment.
	 *
	 * @param string $order_id
	 * @param string $payment_id
	 * @param string $signature
	 *
	 * @return bool
	 */
	public static function verify_signature( $order_id, $payment_id, $signature ) {
		$creds = self::get_credentials();

		// Check for sandbox mock orders or mock payments
		if ( strpos( $order_id, 'order_mock_' ) === 0 || strpos( $payment_id, 'pay_mock_' ) === 0 || strpos( $signature, 'sig_mock_' ) === 0 ) {
			return ! empty( $payment_id );
		}

		if ( empty( $creds['key_secret'] ) ) {
			return false;
		}

		$generated_signature = hash_hmac( 'sha256', $order_id . '|' . $payment_id, $creds['key_secret'] );

		return hash_equals( $generated_signature, $signature );
	}
}


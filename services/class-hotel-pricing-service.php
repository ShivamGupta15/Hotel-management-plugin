<?php
namespace Hotel_Management\Services;

use DateTime;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * Dynamic Pricing, Weekend Differentials & Tax Calculation Engine
 */
class Hotel_Pricing_Service {

	/**
	 * Calculate itemized price quote for given room type and date range.
	 *
	 * @param object $room_type
	 * @param string $check_in (Y-m-d)
	 * @param string $check_out (Y-m-d)
	 * @param int    $room_count
	 * @param int    $adults
	 * @param int    $children
	 *
	 * @return array
	 */
	public static function calculate_quote( $room_type, $check_in, $check_out, $room_count = 1, $adults = 2, $children = 0 ) {
		global $wpdb;

		$d_in  = new DateTime( $check_in );
		$d_out = new DateTime( $check_out );
		$days  = (int) $d_in->diff( $d_out )->days;

		if ( $days <= 0 ) {
			$days = 1;
		}

		$table_rates = $wpdb->prefix . 'hotel_daily_rates';
		$table_props = $wpdb->prefix . 'hotel_properties';

		// Fetch property tax rate
		$tax_pct = (float) $wpdb->get_var(
			$wpdb->prepare( "SELECT tax_percentage FROM {$table_props} WHERE id = %d", $room_type->property_id )
		);
		if ( ! $tax_pct ) {
			$tax_pct = 12.00; // Default 12%
		}

		$daily_breakdown = [];
		$subtotal        = 0.00;
		$tax_amount      = 0.00;

		$extra_guests = max( 0, $adults - ( (int) $room_type->base_occupancy * $room_count ) );
		$extra_fee    = (float) $room_type->extra_person_fee * $extra_guests;

		for ( $i = 0; $i < $days; $i++ ) {
			$cur = clone $d_in;
			$cur->modify( "+{$i} days" );
			$date_str = $cur->format( 'Y-m-d' );
			$day_of_week = (int) $cur->format( 'N' ); // 1 (Mon) to 7 (Sun)

			// Check for explicit seasonal rate override in database
			$rate_override = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT price_per_night, weekend_price FROM {$table_rates}
					 WHERE room_type_id = %d AND %s BETWEEN start_date AND end_date
					 LIMIT 1",
					$room_type->id,
					$date_str
				)
			);

			$is_weekend = ( $day_of_week === 5 || $day_of_week === 6 ); // Fri & Sat

			if ( $rate_override ) {
				$base_night = ( $is_weekend && ! empty( $rate_override->weekend_price ) )
					? (float) $rate_override->weekend_price
					: (float) $rate_override->price_per_night;
			} else {
				$base_night = ( $is_weekend && (float) $room_type->weekend_price > 0 )
					? (float) $room_type->weekend_price
					: (float) $room_type->base_price;
			}

			$night_total_base = ( $base_night * $room_count ) + $extra_fee;
			$night_tax        = round( ( $night_total_base * $tax_pct ) / 100, 2 );

			$subtotal   += $night_total_base;
			$tax_amount += $night_tax;

			$daily_breakdown[] = [
				'date'       => $date_str,
				'is_weekend' => $is_weekend,
				'base_rate'  => $base_night,
				'extra_fee'  => $extra_fee,
				'tax_rate'   => $night_tax,
				'total'      => $night_total_base + $night_tax,
			];
		}

		$total_amount = round( $subtotal + $tax_amount, 2 );

		return [
			'total_nights'    => $days,
			'room_count'      => $room_count,
			'subtotal'        => round( $subtotal, 2 ),
			'tax_percentage'  => $tax_pct,
			'tax_amount'      => round( $tax_amount, 2 ),
			'total_amount'    => $total_amount,
			'currency'        => 'INR',
			'daily_breakdown' => $daily_breakdown,
		];
	}
}


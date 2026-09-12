<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$table_types = $wpdb->prefix . 'hotel_room_types';
$table_inv   = $wpdb->prefix . 'hotel_inventory_calendar';

// Handle Bulk Inventory Extension
if ( isset( $_POST['hm_bulk_inventory'] ) && check_admin_referer( 'hm_save_inventory', 'hm_inv_nonce' ) ) {
	$type_id    = intval( $_POST['room_type_id'] );
	$start_date = sanitize_text_field( $_POST['start_date'] );
	$end_date   = sanitize_text_field( $_POST['end_date'] );
	$stock      = intval( $_POST['total_rooms'] );

	\Hotel_Management\Database\Hotel_Schema::generate_inventory_for_range( $type_id, $stock, $start_date, $end_date );

	echo '<div class="notice notice-success is-dismissible"><p><strong>Inventory generated and updated successfully for ' . esc_html( $start_date ) . ' to ' . esc_html( $end_date ) . '!</strong></p></div>';
}

// Handle Date Closure / Blackout
if ( isset( $_POST['hm_close_dates'] ) && check_admin_referer( 'hm_save_inventory', 'hm_inv_nonce' ) ) {
	$type_id    = intval( $_POST['close_room_type_id'] );
	$start_date = sanitize_text_field( $_POST['close_start_date'] );
	$end_date   = sanitize_text_field( $_POST['close_end_date'] );
	$is_closed  = isset( $_POST['is_closed'] ) ? 1 : 0;

	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$table_inv} SET is_closed = %d WHERE room_type_id = %d AND stay_date >= %s AND stay_date <= %s",
			$is_closed,
			$type_id,
			$start_date,
			$end_date
		)
	);

	echo '<div class="notice notice-success is-dismissible"><p><strong>Room status updated for the selected date range.</strong></p></div>';
}

$room_types = $wpdb->get_results( "SELECT id, title, total_inventory FROM {$table_types} ORDER BY id ASC", ARRAY_A );

// Fetch 30-day view for first room type
$selected_type = isset( $_GET['filter_type'] ) ? intval( $_GET['filter_type'] ) : ( ! empty( $room_types[0]['id'] ) ? $room_types[0]['id'] : 1 );
$today         = new DateTime( 'today' );
$future_30     = clone $today;
$future_30->modify( '+30 days' );

$calendar_rows = $wpdb->get_results(
	$wpdb->prepare(
		"SELECT * FROM {$table_inv} WHERE room_type_id = %d AND stay_date >= %s AND stay_date <= %s ORDER BY stay_date ASC",
		$selected_type,
		$today->format( 'Y-m-d' ),
		$future_30->format( 'Y-m-d' )
	),
	ARRAY_A
);
?>

<div class="hm-admin-wrap">
	<div class="hm-admin-header">
		<div>
			<h1 class="hm-admin-title">Inventory Calendar &amp; Rates Manager</h1>
			<p class="hm-admin-subtitle">Generate future inventory capacity, set date blackouts, and monitor nightly room counts</p>
		</div>
	</div>

	<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 30px;">
		<!-- Bulk Inventory Generator -->
		<div style="background: #fff; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 2px 6px rgba(0,0,0,0.04);">
			<h2 style="font-size: 1.2rem; color: #0f172a; margin-top: 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
				📦 Bulk Generate / Extend Room Inventory
			</h2>
			<form method="post">
				<?php wp_nonce_field( 'hm_save_inventory', 'hm_inv_nonce' ); ?>
				<table class="form-table" style="margin-top: 0;">
					<tr>
						<th><label>Room Category *</label></th>
						<td>
							<select name="room_type_id" required>
								<?php foreach ( $room_types as $t ) : ?>
									<option value="<?php echo esc_attr( $t['id'] ); ?>">
										<?php echo esc_html( $t['title'] ); ?> (Default: <?php echo esc_html( $t['total_inventory'] ); ?> rooms)
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label>Date Range *</label></th>
						<td>
							<input type="date" name="start_date" value="<?php echo esc_attr( $today->format( 'Y-m-d' ) ); ?>" required /> to 
							<input type="date" name="end_date" value="<?php echo esc_attr( $future_30->format( 'Y-m-d' ) ); ?>" required />
						</td>
					</tr>
					<tr>
						<th><label>Available Stock Count *</label></th>
						<td>
							<input type="number" name="total_rooms" value="5" min="1" max="500" required /> rooms per night
						</td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" name="hm_bulk_inventory" class="button button-primary" value="Generate / Update Daily Inventory" />
				</p>
			</form>
		</div>

		<!-- Date Blackout / Closure -->
		<div style="background: #fff; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 2px 6px rgba(0,0,0,0.04);">
			<h2 style="font-size: 1.2rem; color: #0f172a; margin-top: 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
				🚫 Room Blackout / Close Dates
			</h2>
			<p style="color: #64748b; font-size: 0.85rem;">Block reservations during maintenance, renovations, or private VIP buyouts.</p>
			<form method="post">
				<?php wp_nonce_field( 'hm_save_inventory', 'hm_inv_nonce' ); ?>
				<table class="form-table" style="margin-top: 0;">
					<tr>
						<th><label>Room Category *</label></th>
						<td>
							<select name="close_room_type_id" required>
								<?php foreach ( $room_types as $t ) : ?>
									<option value="<?php echo esc_attr( $t['id'] ); ?>"><?php echo esc_html( $t['title'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label>Blackout Dates *</label></th>
						<td>
							<input type="date" name="close_start_date" value="<?php echo esc_attr( $today->format( 'Y-m-d' ) ); ?>" required /> to 
							<input type="date" name="close_end_date" value="<?php echo esc_attr( $today->format( 'Y-m-d' ) ); ?>" required />
						</td>
					</tr>
					<tr>
						<th><label>Status</label></th>
						<td>
							<label>
								<input type="checkbox" name="is_closed" value="1" checked />
								Close dates for new bookings (Block inventory)
							</label>
						</td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" name="hm_close_dates" class="button" value="Apply Date Closure" />
				</p>
			</form>
		</div>
	</div>

	<!-- 30-Day Daily Inventory Inspector -->
	<div style="background: #fff; padding: 20px; border-radius: 12px; border: 1px solid #e2e8f0;">
		<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
			<h2 style="font-size: 1.15rem; color: #0f172a; margin: 0;">
				Next 30 Days Inventory &amp; Occupancy Breakdown
			</h2>
			<form method="get" style="display: flex; gap: 8px;">
				<input type="hidden" name="page" value="hotel-inventory" />
				<select name="filter_type" onchange="this.form.submit()">
					<?php foreach ( $room_types as $t ) : ?>
						<option value="<?php echo esc_attr( $t['id'] ); ?>" <?php selected( $selected_type, $t['id'] ); ?>>
							<?php echo esc_html( $t['title'] ); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</form>
		</div>

		<table class="hm-data-table">
			<thead>
				<tr>
					<th>Date</th>
					<th>Total Rooms</th>
					<th>Booked</th>
					<th>Held (In Checkout)</th>
					<th>Available to Book</th>
					<th>Status</th>
				</tr>
			</thead>
			<tbody>
				<?php if ( ! empty( $calendar_rows ) ) : ?>
					<?php foreach ( $calendar_rows as $row ) :
						$avail = (int) $row['total_rooms'] - (int) $row['booked_rooms'] - (int) $row['held_rooms'];
					?>
						<tr>
							<td><strong><?php echo esc_html( $row['stay_date'] ); ?></strong></td>
							<td><?php echo esc_html( $row['total_rooms'] ); ?></td>
							<td style="color: #2563eb; font-weight: 600;"><?php echo esc_html( $row['booked_rooms'] ); ?></td>
							<td style="color: #d97706; font-weight: 600;"><?php echo esc_html( $row['held_rooms'] ); ?></td>
							<td>
								<span style="font-weight: 700; color: <?php echo $avail > 0 ? '#16a34a' : '#dc2626'; ?>;">
									<?php echo $avail; ?> rooms available
								</span>
							</td>
							<td>
								<?php if ( ! empty( $row['is_closed'] ) ) : ?>
									<span style="color: #dc2626; font-weight: 600;">🚫 Closed</span>
								<?php else : ?>
									<span style="color: #16a34a;">✔ Open</span>
								<?php endif; ?>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php else : ?>
					<tr>
						<td colspan="6" style="text-align: center; padding: 24px; color: #64748b;">
							No calendar rows generated yet for this date window. Use the Bulk Generator above to create inventory!
						</td>
					</tr>
				<?php endif; ?>
			</tbody>
		</table>
	</div>
</div>


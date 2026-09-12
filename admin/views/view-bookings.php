<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

$table_bks   = $wpdb->prefix . 'hotel_bookings';
$table_items = $wpdb->prefix . 'hotel_booking_items';
$table_rooms = $wpdb->prefix . 'hotel_physical_rooms';

// Quick Actions Handling
if ( isset( $_GET['action'], $_GET['booking_id'] ) && check_admin_referer( 'hm_manage_booking' ) ) {
	$act  = sanitize_text_field( $_GET['action'] );
	$b_id = (int) $_GET['booking_id'];

	if ( $act === 'checkin' ) {
		$wpdb->update( $table_bks, [ 'booking_status' => 'checked_in', 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ], [ 'id' => $b_id ] );
		echo '<div class="notice notice-success is-dismissible"><p><strong>Booking #' . $b_id . ' marked Checked-In.</strong></p></div>';
	} elseif ( $act === 'checkout' ) {
		$wpdb->update( $table_bks, [ 'booking_status' => 'checked_out', 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ], [ 'id' => $b_id ] );
		$assigned = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT physical_room_id FROM {$table_items} WHERE booking_id = %d AND physical_room_id IS NOT NULL", $b_id ) );
		foreach ( $assigned as $r_id ) {
			$wpdb->update( $table_rooms, [ 'housekeeping_status' => 'dirty' ], [ 'id' => $r_id ] );
		}
		echo '<div class="notice notice-success is-dismissible"><p><strong>Booking #' . $b_id . ' checked out. Housekeeping updated.</strong></p></div>';
	} elseif ( $act === 'unassign_room' ) {
		$wpdb->update( $table_items, [ 'physical_room_id' => null ], [ 'booking_id' => $b_id ] );
		echo '<div class="notice notice-success is-dismissible"><p><strong>Room number unassigned from Booking #' . $b_id . '. You can now re-assign the correct room.</strong></p></div>';
	}
}

// Handle inline room assignment / re-assignment
if ( isset( $_POST['hm_assign_room'] ) && check_admin_referer( 'hm_assign_room_nonce', 'hm_ar_nonce' ) ) {
	$b_id    = intval( $_POST['booking_id'] );
	$room_id = intval( $_POST['physical_room_id'] );

	$wpdb->update(
		$table_items,
		[ 'physical_room_id' => $room_id > 0 ? $room_id : null ],
		[ 'booking_id' => $b_id ]
	);

	echo '<div class="notice notice-success is-dismissible"><p><strong>Assigned room updated successfully for Booking #' . $b_id . '!</strong></p></div>';
}

// Fetch all bookings with assigned physical room details
$bookings = $wpdb->get_results(
	"SELECT b.*,
	        (SELECT room_name FROM {$table_items} WHERE booking_id = b.id LIMIT 1) as room_name,
	        (SELECT room_type_id FROM {$table_items} WHERE booking_id = b.id LIMIT 1) as room_type_id,
	        (SELECT r.room_number FROM {$table_items} i JOIN {$table_rooms} r ON i.physical_room_id = r.id WHERE i.booking_id = b.id LIMIT 1) as assigned_room_number,
	        (SELECT physical_room_id FROM {$table_items} WHERE booking_id = b.id LIMIT 1) as assigned_room_id
	 FROM {$table_bks} b
	 ORDER BY b.id DESC LIMIT 100",
	ARRAY_A
);

$all_physical_rooms = $wpdb->get_results( "SELECT id, room_type_id, room_number FROM {$table_rooms} ORDER BY room_number ASC", ARRAY_A );
?>

<div class="hm-admin-wrap">
	<div class="hm-admin-header">
		<div>
			<h1 class="hm-admin-title">Guest Reservations &amp; Bookings</h1>
			<p class="hm-admin-subtitle">Manage guest arrivals, payments, check-ins, and invoices</p>
		</div>
	</div>

	<table class="hm-data-table">
		<thead>
			<tr>
				<th>Ref #</th>
				<th>Guest Details</th>
				<th>Room Category</th>
				<th>Assigned Room #</th>
				<th>Stay Dates</th>
				<th>Amount</th>
				<th>Payment</th>
				<th>Status</th>
				<th>Front Desk Actions</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! empty( $bookings ) ) : ?>
				<?php foreach ( $bookings as $b ) : ?>
					<tr>
						<td>
							<strong><?php echo esc_html( $b['booking_number'] ); ?></strong>
							<div style="font-size:0.75rem; color:#64748b;"><?php echo esc_html( $b['created_at'] ); ?></div>
						</td>
						<td>
							<strong><?php echo esc_html( $b['guest_name'] ); ?></strong>
							<div style="font-size:0.8rem; color:#64748b;"><?php echo esc_html( $b['guest_phone'] ); ?></div>
							<div style="font-size:0.8rem; color:#64748b;"><?php echo esc_html( $b['guest_email'] ); ?></div>
						</td>
						<td><?php echo esc_html( $b['room_name'] ? $b['room_name'] : 'Suite' ); ?></td>
						<td>
							<?php if ( ! empty( $b['assigned_room_number'] ) ) : ?>
								<div style="font-weight: 700; color: #15803d;">
									Room <?php echo esc_html( $b['assigned_room_number'] ); ?>
								</div>
								<a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=hotel-bookings&action=unassign_room&booking_id=' . $b['id'] ), 'hm_manage_booking' ); ?>"
									onclick="return confirm('Remove Room <?php echo esc_js( $b['assigned_room_number'] ); ?> from this booking?');"
									style="color: #dc2626; font-size: 0.75rem; text-decoration: none; font-weight: 600;">
									✕ Remove / Unassign
								</a>
							<?php else : ?>
								<span style="color: #d97706; font-size: 0.8rem; font-weight: 600;">Not Assigned</span>
							<?php endif; ?>

							<form method="post" style="margin-top: 6px;">
								<?php wp_nonce_field( 'hm_assign_room_nonce', 'hm_ar_nonce' ); ?>
								<input type="hidden" name="booking_id" value="<?php echo esc_attr( $b['id'] ); ?>" />
								<select name="physical_room_id" style="font-size: 0.75rem; padding: 2px 4px; max-width: 130px;" onchange="this.form.submit()">
									<option value="">-- <?php echo ! empty( $b['assigned_room_number'] ) ? 'Change...' : 'Assign Room'; ?> --</option>
									<?php foreach ( $all_physical_rooms as $pr ) :
										if ( (int) $pr['room_type_id'] === (int) $b['room_type_id'] ) : ?>
											<option value="<?php echo esc_attr( $pr['id'] ); ?>" <?php selected( $b['assigned_room_id'], $pr['id'] ); ?>>
												Room <?php echo esc_html( $pr['room_number'] ); ?>
											</option>
										<?php endif;
									endforeach; ?>
								</select>
								<input type="hidden" name="hm_assign_room" value="1" />
							</form>
						</td>
						<td>
							<div><strong>In:</strong> <?php echo esc_html( $b['check_in_date'] ); ?></div>
							<div><strong>Out:</strong> <?php echo esc_html( $b['check_out_date'] ); ?> (<?php echo esc_html( $b['total_nights'] ); ?>N)</div>
						</td>
						<td>
							<strong>₹<?php echo number_format( (float) $b['total_amount'] ); ?></strong>
						</td>
						<td>
							<span class="hm-badge hm-badge-<?php echo esc_attr( $b['payment_status'] ); ?>">
								<?php echo esc_html( ucfirst( $b['payment_status'] ) ); ?>
							</span>
						</td>
						<td>
							<span class="hm-badge hm-badge-<?php echo esc_attr( $b['booking_status'] ); ?>">
								<?php echo esc_html( ucfirst( str_replace( '_', ' ', $b['booking_status'] ) ) ); ?>
							</span>
						</td>
						<td>
							<?php if ( $b['booking_status'] === 'confirmed' ) : ?>
								<a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=hotel-bookings&action=checkin&booking_id=' . $b['id'] ), 'hm_manage_booking' ); ?>" class="hm-btn-action success">
									Check-In
								</a>
							<?php elseif ( $b['booking_status'] === 'checked_in' ) : ?>
								<a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=hotel-bookings&action=checkout&booking_id=' . $b['id'] ), 'hm_manage_booking' ); ?>" class="hm-btn-action" style="background: #d97706;">
									Check-Out
								</a>
							<?php endif; ?>
							<a href="<?php echo esc_url( home_url( '/?booking_token=' . $b['check_in_token'] ) ); ?>" target="_blank" class="hm-btn-action" style="background:#475569;">
								Pass
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="8" style="text-align: center; padding: 40px; color: #64748b;">
						No bookings found yet. Test the booking widget on the frontend to create a reservation.
					</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>
</div>


<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;

// Handle manual quick actions from Front Desk
if ( isset( $_POST['hm_admin_action'] ) && check_admin_referer( 'hm_frontdesk_action', 'hm_fd_nonce' ) ) {
	$action     = sanitize_text_field( $_POST['hm_admin_action'] );
	$booking_id = (int) $_POST['booking_id'];

	$table_bks   = $wpdb->prefix . 'hotel_bookings';
	$table_items = $wpdb->prefix . 'hotel_booking_items';
	$table_rooms = $wpdb->prefix . 'hotel_physical_rooms';

	if ( $action === 'check_in' ) {
		$physical_room_id = (int) $_POST['physical_room_id'];
		$wpdb->update( $table_bks, [ 'booking_status' => 'checked_in', 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ], [ 'id' => $booking_id ] );
		if ( $physical_room_id > 0 ) {
			$wpdb->update( $table_items, [ 'physical_room_id' => $physical_room_id ], [ 'booking_id' => $booking_id ] );
		}
		echo '<div class="notice notice-success is-dismissible"><p><strong>Guest checked in successfully!</strong></p></div>';
	} elseif ( $action === 'check_out' ) {
		$wpdb->update( $table_bks, [ 'booking_status' => 'checked_out', 'updated_at' => gmdate( 'Y-m-d H:i:s' ) ], [ 'id' => $booking_id ] );
		$assigned = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT physical_room_id FROM {$table_items} WHERE booking_id = %d AND physical_room_id IS NOT NULL", $booking_id ) );
		foreach ( $assigned as $r_id ) {
			$wpdb->update( $table_rooms, [ 'housekeeping_status' => 'dirty' ], [ 'id' => $r_id ] );
		}
		echo '<div class="notice notice-success is-dismissible"><p><strong>Guest checked out. Room marked for housekeeping.</strong></p></div>';
	}
}

// Fetch Tape Chart Data
$table_types = $wpdb->prefix . 'hotel_room_types';
$table_rooms = $wpdb->prefix . 'hotel_physical_rooms';
$table_bks   = $wpdb->prefix . 'hotel_bookings';
$table_items = $wpdb->prefix . 'hotel_booking_items';

$today = new DateTime( 'today' );
$days_to_show = 14;

$dates = [];
for ( $i = 0; $i < $days_to_show; $i++ ) {
	$cur = clone $today;
	$cur->modify( "+{$i} days" );
	$dates[] = $cur->format( 'Y-m-d' );
}

$rooms = $wpdb->get_results(
	"SELECT r.*, t.title as type_title
	 FROM {$table_rooms} r
	 LEFT JOIN {$table_types} t ON r.room_type_id = t.id
	 ORDER BY r.floor ASC, r.room_number ASC",
	ARRAY_A
);

// Fetch active allocations
$sql_alloc = $wpdb->prepare(
	"SELECT b.id, b.booking_number, b.guest_name, b.booking_status, i.physical_room_id, i.stay_date
	 FROM {$table_bks} b
	 JOIN {$table_items} i ON b.id = i.booking_id
	 WHERE b.booking_status IN ('confirmed', 'checked_in')
	   AND i.stay_date >= %s AND i.stay_date <= %s",
	$dates[0],
	end( $dates )
);
$allocations = $wpdb->get_results( $sql_alloc, ARRAY_A );

// Map allocations by [physical_room_id][stay_date]
$occupancy_map = [];
foreach ( $allocations as $al ) {
	if ( ! empty( $al['physical_room_id'] ) ) {
		$occupancy_map[ $al['physical_room_id'] ][ $al['stay_date'] ] = $al;
	}
}

// KPIs
$arrivals_today   = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_bks} WHERE check_in_date = %s AND booking_status = 'confirmed'", $today->format( 'Y-m-d' ) ) );
$departures_today = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_bks} WHERE check_out_date = %s AND booking_status = 'checked_in'", $today->format( 'Y-m-d' ) ) );
$in_house_guests  = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_bks} WHERE booking_status = 'checked_in'" );
$clean_rooms      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_rooms} WHERE housekeeping_status = 'clean'" );
$dirty_rooms      = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_rooms} WHERE housekeeping_status = 'dirty'" );
?>

<div class="hm-admin-wrap">
	<div class="hm-admin-header">
		<div>
			<h1 class="hm-admin-title">Front Desk &amp; Room Tape Chart</h1>
			<p class="hm-admin-subtitle">Live room allocation, daily arrivals, departures, and housekeeping management</p>
		</div>
		<div>
			<span style="font-size: 0.9rem; font-weight: 600; color: #475569;">Date: <?php echo esc_html( $today->format( 'D, d M Y' ) ); ?></span>
		</div>
	</div>

	<!-- KPIs -->
	<div class="hm-kpi-grid">
		<div class="hm-kpi-card">
			<div class="hm-kpi-label">Today's Arrivals</div>
			<div class="hm-kpi-val" style="color: #2563eb;"><?php echo $arrivals_today; ?></div>
		</div>
		<div class="hm-kpi-card">
			<div class="hm-kpi-label">Today's Departures</div>
			<div class="hm-kpi-val" style="color: #d97706;"><?php echo $departures_today; ?></div>
		</div>
		<div class="hm-kpi-card">
			<div class="hm-kpi-label">Current In-House</div>
			<div class="hm-kpi-val" style="color: #16a34a;"><?php echo $in_house_guests; ?></div>
		</div>
		<div class="hm-kpi-card">
			<div class="hm-kpi-label">Housekeeping Status</div>
			<div class="hm-kpi-val" style="font-size: 1.2rem; margin-top: 10px;">
				<span style="color: #16a34a;"><?php echo $clean_rooms; ?> Clean</span> &bull; 
				<span style="color: #dc2626;"><?php echo $dirty_rooms; ?> Dirty</span>
			</div>
		</div>
	</div>

	<!-- Tape Chart -->
	<div class="hm-tapechart-container">
		<table class="hm-tapechart-table">
			<thead>
				<tr>
					<th class="hm-tapechart-room-col">Room</th>
					<?php foreach ( $dates as $d ) :
						$dt = new DateTime( $d );
					?>
						<th>
							<div><?php echo $dt->format( 'D' ); ?></div>
							<div style="font-size: 1.1em;"><?php echo $dt->format( 'd' ); ?></div>
						</th>
					<?php endforeach; ?>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rooms as $r ) : ?>
					<tr>
						<td class="hm-tapechart-room-col">
							<strong>Room <?php echo esc_html( $r['room_number'] ); ?></strong>
							<div style="font-size: 0.75rem; color: #64748b; font-weight: normal;"><?php echo esc_html( $r['type_title'] ); ?></div>
							<div style="font-size: 0.7rem; margin-top: 2px;">
								<span style="color: <?php echo $r['housekeeping_status'] === 'clean' ? '#16a34a' : '#dc2626'; ?>;">
									● <?php echo ucfirst( $r['housekeeping_status'] ); ?>
								</span>
							</div>
						</td>
						<?php foreach ( $dates as $d ) :
							$occupant = isset( $occupancy_map[ $r['id'] ][ $d ] ) ? $occupancy_map[ $r['id'] ][ $d ] : null;
						?>
							<?php if ( $occupant ) : ?>
								<td class="<?php echo $occupant['booking_status'] === 'checked_in' ? 'hm-cell-checkedin' : 'hm-cell-booked'; ?>"
									title="<?php echo esc_attr( $occupant['guest_name'] . ' (' . $occupant['booking_number'] . ')' ); ?>">
									<div style="font-size: 0.75rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 60px;">
										<?php echo esc_html( $occupant['guest_name'] ); ?>
									</div>
								</td>
							<?php else : ?>
								<td class="hm-cell-empty">&bull;</td>
							<?php endif; ?>
						<?php endforeach; ?>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	</div>
</div>


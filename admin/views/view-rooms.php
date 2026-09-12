<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

global $wpdb;
$table_types = $wpdb->prefix . 'hotel_room_types';
$table_rooms = $wpdb->prefix . 'hotel_physical_rooms';

// Edit Mode Detection
$edit_id       = isset( $_GET['action'], $_GET['type_id'] ) && $_GET['action'] === 'edit_suite' ? intval( $_GET['type_id'] ) : 0;
$editing_suite = null;
if ( $edit_id > 0 ) {
	$editing_suite = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_types} WHERE id = %d", $edit_id ), ARRAY_A );
}

// Handle Add New Room Type
if ( isset( $_POST['hm_add_room_type'] ) && check_admin_referer( 'hm_save_room_type', 'hm_rt_nonce' ) ) {
	$title         = sanitize_text_field( $_POST['title'] );
	$slug          = sanitize_title( $title );
	$base_price    = floatval( $_POST['base_price'] );
	$weekend_price = ! empty( $_POST['weekend_price'] ) ? floatval( $_POST['weekend_price'] ) : $base_price;
	$max_occ       = intval( $_POST['max_occupancy'] );
	$base_occ      = intval( $_POST['base_occupancy'] );
	$inventory     = intval( $_POST['total_inventory'] );
	$size_sqft     = intval( $_POST['size_sqft'] );
	$bed_type      = sanitize_text_field( $_POST['bed_type'] );
	$image         = esc_url_raw( $_POST['featured_image'] );
	$short_desc    = sanitize_text_field( $_POST['short_description'] );
	$desc          = sanitize_textarea_field( $_POST['description'] );

	$amenities_raw = isset( $_POST['amenities'] ) ? array_map( 'sanitize_text_field', explode( ',', $_POST['amenities'] ) ) : [];
	$amenities     = json_encode( array_values( array_filter( array_map( 'trim', $amenities_raw ) ) ) );

	$wpdb->insert(
		$table_types,
		[
			'property_id'       => 1,
			'title'             => $title,
			'slug'              => $slug,
			'description'       => $desc,
			'short_description' => $short_desc,
			'base_price'        => $base_price,
			'weekend_price'     => $weekend_price,
			'max_occupancy'     => $max_occ,
			'base_occupancy'    => $base_occ,
			'total_inventory'   => $inventory,
			'size_sqft'         => $size_sqft,
			'bed_type'          => $bed_type,
			'amenities'         => $amenities,
			'featured_image'    => $image,
			'status'            => 'active',
		]
	);
	$new_type_id = $wpdb->insert_id;

	// Automatically seed calendar inventory for the next 180 days
	\Hotel_Management\Database\Hotel_Schema::generate_inventory_calendar( $new_type_id, $inventory, 180 );

	echo '<div class="notice notice-success is-dismissible"><p><strong>Suite Category "' . esc_html( $title ) . '" added and 180-day inventory calendar generated!</strong></p></div>';
	echo '<div class="notice notice-success is-dismissible"><p><strong>Suite Category "' . esc_html( $title ) . '" created and 180-day inventory generated!</strong></p></div>';
}

// Handle Update Existing Room Type
if ( isset( $_POST['hm_update_room_type'] ) && check_admin_referer( 'hm_save_room_type', 'hm_rt_nonce' ) ) {
	$type_id       = intval( $_POST['type_id'] );
	$title         = sanitize_text_field( $_POST['title'] );
	$slug          = sanitize_title( $title );
	$base_price    = floatval( $_POST['base_price'] );
	$weekend_price = ! empty( $_POST['weekend_price'] ) ? floatval( $_POST['weekend_price'] ) : $base_price;
	$max_occ       = intval( $_POST['max_occupancy'] );
	$base_occ      = intval( $_POST['base_occupancy'] );
	$inventory     = intval( $_POST['total_inventory'] );
	$size_sqft     = intval( $_POST['size_sqft'] );
	$bed_type      = sanitize_text_field( $_POST['bed_type'] );
	$image         = esc_url_raw( $_POST['featured_image'] );
	$short_desc    = sanitize_text_field( $_POST['short_description'] );
	$desc          = sanitize_textarea_field( $_POST['description'] );

	$amenities_raw = isset( $_POST['amenities'] ) ? array_map( 'sanitize_text_field', explode( ',', $_POST['amenities'] ) ) : [];
	$amenities     = json_encode( array_values( array_filter( array_map( 'trim', $amenities_raw ) ) ) );

	$wpdb->update(
		$table_types,
		[
			'title'             => $title,
			'slug'              => $slug,
			'description'       => $desc,
			'short_description' => $short_desc,
			'base_price'        => $base_price,
			'weekend_price'     => $weekend_price,
			'max_occupancy'     => $max_occ,
			'base_occupancy'    => $base_occ,
			'total_inventory'   => $inventory,
			'size_sqft'         => $size_sqft,
			'bed_type'          => $bed_type,
			'amenities'         => $amenities,
			'featured_image'    => $image,
		],
		[ 'id' => $type_id ]
	);

	// Synchronize calendar inventory total_rooms where not fully booked
	$wpdb->query(
		$wpdb->prepare(
			"UPDATE {$wpdb->prefix}hotel_inventory_calendar
			 SET total_rooms = %d, available_rooms = GREATEST(0, %d - booked_rooms - held_rooms)
			 WHERE room_type_id = %d",
			$inventory,
			$inventory,
			$type_id
		)
	);

	echo '<div class="notice notice-success is-dismissible"><p><strong>Suite Category "' . esc_html( $title ) . '" updated successfully!</strong></p></div>';
	$editing_suite = null;
}

// Handle Delete Room Type (Suite Category)
if ( isset( $_GET['action'], $_GET['type_id'] ) && $_GET['action'] === 'delete_suite' && check_admin_referer( 'hm_delete_suite' ) ) {
	$type_id = intval( $_GET['type_id'] );
	$suite   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_types} WHERE id = %d", $type_id ) );

	if ( $suite ) {
		// Delete associated physical rooms
		$wpdb->delete( $table_rooms, [ 'room_type_id' => $type_id ] );
		// Delete inventory calendar rows
		$wpdb->delete( $wpdb->prefix . 'hotel_inventory_calendar', [ 'room_type_id' => $type_id ] );
		// Delete room type
		$wpdb->delete( $table_types, [ 'id' => $type_id ] );

		echo '<div class="notice notice-success is-dismissible"><p><strong>Suite Category "' . esc_html( $suite->title ) . '" and its inventory were deleted successfully.</strong></p></div>';
	}
}

// Handle Add Physical Room Number
if ( isset( $_POST['hm_add_physical_room'] ) && check_admin_referer( 'hm_save_physical_room', 'hm_pr_nonce' ) ) {
	$type_id = intval( $_POST['room_type_id'] );
	$number  = sanitize_text_field( $_POST['room_number'] );
	$floor   = sanitize_text_field( $_POST['floor'] );

	$wpdb->insert(
		$table_rooms,
		[
			'property_id'         => 1,
			'room_type_id'        => $type_id,
			'room_number'         => $number,
			'floor'               => $floor,
			'housekeeping_status' => 'clean',
			'operational_status'  => 'available',
		]
	);

	// Update total_inventory count on room type
	$current_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_rooms} WHERE room_type_id = %d", $type_id ) );
	$wpdb->update( $table_types, [ 'total_inventory' => $current_count ], [ 'id' => $type_id ] );

	echo '<div class="notice notice-success is-dismissible"><p><strong>Physical Room ' . esc_html( $number ) . ' assigned successfully!</strong></p></div>';
}

// Handle Delete Physical Room Number
if ( isset( $_GET['action'], $_GET['room_id'] ) && $_GET['action'] === 'delete_physical_room' && check_admin_referer( 'hm_delete_physical_room' ) ) {
	$room_id = intval( $_GET['room_id'] );
	$room    = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table_rooms} WHERE id = %d", $room_id ) );

	if ( $room ) {
		// Unassign from any booking items first to prevent orphan references
		$wpdb->update( $wpdb->prefix . 'hotel_booking_items', [ 'physical_room_id' => null ], [ 'physical_room_id' => $room_id ] );

		// Delete the room
		$wpdb->delete( $table_rooms, [ 'id' => $room_id ] );

		// Update total_inventory count on room type
		$current_count = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table_rooms} WHERE room_type_id = %d", $room->room_type_id ) );
		$wpdb->update( $table_types, [ 'total_inventory' => $current_count ], [ 'id' => $room->room_type_id ] );

		echo '<div class="notice notice-success is-dismissible"><p><strong>Physical Room ' . esc_html( $room->room_number ) . ' deleted successfully.</strong></p></div>';
	}
}

// Fetch existing data
$room_types     = $wpdb->get_results( "SELECT * FROM {$table_types} ORDER BY id ASC", ARRAY_A );
$physical_rooms = $wpdb->get_results(
	"SELECT r.*, t.title as type_title FROM {$table_rooms} r LEFT JOIN {$table_types} t ON r.room_type_id = t.id ORDER BY r.room_number ASC",
	ARRAY_A
);
?>

<div class="hm-admin-wrap">
	<div class="hm-admin-header">
		<div>
			<h1 class="hm-admin-title">Rooms &amp; Suite Inventory Management</h1>
			<p class="hm-admin-subtitle">Create luxury suite categories, configure pricing tiers, and assign physical room numbers</p>
			<p class="hm-admin-subtitle">Create, update, or delete suite categories and assign physical room numbers</p>
		</div>
	</div>

	<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 30px;">
		<!-- Form: Add Suite Category -->
		<!-- Form: Add or Edit Suite Category -->
		<div style="background: #fff; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 2px 6px rgba(0,0,0,0.04);">
			<h2 style="font-size: 1.2rem; color: #0f172a; margin-top: 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
				➕ Add New Room Category / Suite
			</h2>
			<form method="post">
			<div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px; margin-bottom: 16px;">
				<h2 style="font-size: 1.2rem; color: #0f172a; margin: 0;">
					<?php echo $editing_suite ? '✏️ Edit Suite: ' . esc_html( $editing_suite['title'] ) : '➕ Add New Room Category / Suite'; ?>
				</h2>
				<?php if ( $editing_suite ) : ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=hotel-rooms' ) ); ?>" class="button button-secondary" style="font-size: 0.8rem;">
						✕ Cancel Edit
					</a>
				<?php endif; ?>
			</div>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=hotel-rooms' ) ); ?>">
				<?php wp_nonce_field( 'hm_save_room_type', 'hm_rt_nonce' ); ?>
				<?php if ( $editing_suite ) : ?>
					<input type="hidden" name="type_id" value="<?php echo esc_attr( $editing_suite['id'] ); ?>" />
				<?php endif; ?>

				<table class="form-table" style="margin-top: 0;">
					<tr>
						<th><label>Suite Title *</label></th>
						<td><input type="text" name="title" class="regular-text" placeholder="e.g. Royal Panoramic Suite" required /></td>
						<td>
							<input type="text" name="title" class="regular-text" placeholder="e.g. Royal Panoramic Suite"
								value="<?php echo esc_attr( $editing_suite ? $editing_suite['title'] : '' ); ?>" required />
						</td>
					</tr>
					<tr>
						<th><label>Base Price / Night (₹) *</label></th>
						<td><input type="number" step="0.01" name="base_price" class="regular-text" placeholder="8500.00" required /></td>
						<td>
							<input type="number" step="0.01" name="base_price" class="regular-text" placeholder="8500.00"
								value="<?php echo esc_attr( $editing_suite ? $editing_suite['base_price'] : '' ); ?>" required />
						</td>
					</tr>
					<tr>
						<th><label>Weekend Price (₹)</label></th>
						<td><input type="number" step="0.01" name="weekend_price" class="regular-text" placeholder="9800.00" /></td>
						<td>
							<input type="number" step="0.01" name="weekend_price" class="regular-text" placeholder="9800.00"
								value="<?php echo esc_attr( $editing_suite ? $editing_suite['weekend_price'] : '' ); ?>" />
						</td>
					</tr>
					<tr>
						<th><label>Base / Max Occupancy</label></th>
						<td>
							<input type="number" name="base_occupancy" value="2" style="width: 70px;" min="1" /> Base &bull;
							<input type="number" name="max_occupancy" value="3" style="width: 70px;" min="1" /> Max
							<input type="number" name="base_occupancy" value="<?php echo esc_attr( $editing_suite ? $editing_suite['base_occupancy'] : 2 ); ?>" style="width: 70px;" min="1" /> Base &bull;
							<input type="number" name="max_occupancy" value="<?php echo esc_attr( $editing_suite ? $editing_suite['max_occupancy'] : 3 ); ?>" style="width: 70px;" min="1" /> Max
						</td>
					</tr>
					<tr>
						<th><label>Total Available Inventory *</label></th>
						<td><input type="number" name="total_inventory" value="5" class="small-text" min="1" required /> rooms</td>
						<td>
							<input type="number" name="total_inventory" value="<?php echo esc_attr( $editing_suite ? $editing_suite['total_inventory'] : 5 ); ?>" class="small-text" min="1" required /> rooms
						</td>
					</tr>
					<tr>
						<th><label>Size &amp; Bed Type</label></th>
						<td>
							<input type="number" name="size_sqft" value="500" style="width: 80px;" /> sqft &bull;
							<input type="text" name="bed_type" value="King Bed" style="width: 140px;" />
							<input type="number" name="size_sqft" value="<?php echo esc_attr( $editing_suite ? $editing_suite['size_sqft'] : 500 ); ?>" style="width: 80px;" /> sqft &bull;
							<input type="text" name="bed_type" value="<?php echo esc_attr( $editing_suite ? $editing_suite['bed_type'] : 'King Bed' ); ?>" style="width: 140px;" />
						</td>
					</tr>
					<tr>
						<th><label>Featured Image URL</label></th>
						<td><input type="url" name="featured_image" class="regular-text" placeholder="https://images.unsplash.com/photo-..." /></td>
						<td>
							<input type="url" name="featured_image" class="regular-text" placeholder="https://images.unsplash.com/photo-..."
								value="<?php echo esc_attr( $editing_suite ? $editing_suite['featured_image'] : '' ); ?>" />
						</td>
					</tr>
					<tr>
						<th><label>Amenities (Comma-separated)</label></th>
						<td><input type="text" name="amenities" class="large-text" value="Free Wi-Fi, Breakfast Included, Ocean Balcony, Rain Shower" /></td>
						<td>
							<?php
							$current_amenities = '';
							if ( $editing_suite && ! empty( $editing_suite['amenities'] ) ) {
								$decoded = json_decode( $editing_suite['amenities'], true );
								$current_amenities = is_array( $decoded ) ? implode( ', ', $decoded ) : $editing_suite['amenities'];
							} else {
								$current_amenities = 'Free Wi-Fi, Breakfast Included, Ocean Balcony, Rain Shower';
							}
							?>
							<input type="text" name="amenities" class="large-text" value="<?php echo esc_attr( $current_amenities ); ?>" />
						</td>
					</tr>
					<tr>
						<th><label>Short Description</label></th>
						<td><input type="text" name="short_description" class="large-text" placeholder="Ocean View, King Bed, Private Balcony" /></td>
						<td>
							<input type="text" name="short_description" class="large-text" placeholder="Ocean View, King Bed, Private Balcony"
								value="<?php echo esc_attr( $editing_suite ? $editing_suite['short_description'] : '' ); ?>" />
						</td>
					</tr>
					<tr>
						<th><label>Full Description</label></th>
						<td>
							<textarea name="description" rows="3" class="large-text" placeholder="Detailed luxury description of suite amenities and features..."><?php echo esc_textarea( $editing_suite ? $editing_suite['description'] : '' ); ?></textarea>
						</td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" name="hm_add_room_type" class="button button-primary" value="Create Suite &amp; Seed Inventory" />
				<p class="submit" style="display: flex; gap: 10px; align-items: center;">
					<?php if ( $editing_suite ) : ?>
						<input type="submit" name="hm_update_room_type" class="button button-primary" value="Update Suite Category" />
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=hotel-rooms' ) ); ?>" class="button">Cancel</a>
					<?php else : ?>
						<input type="submit" name="hm_add_room_type" class="button button-primary" value="Create Suite &amp; Seed Inventory" />
					<?php endif; ?>
				</p>
			</form>
		</div>

		<!-- Form: Add Physical Room Number -->
		<div style="background: #fff; padding: 24px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 2px 6px rgba(0,0,0,0.04);">
			<h2 style="font-size: 1.2rem; color: #0f172a; margin-top: 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
				🔑 Add Physical Room Number
			</h2>
			<form method="post">
				<?php wp_nonce_field( 'hm_save_physical_room', 'hm_pr_nonce' ); ?>
				<table class="form-table" style="margin-top: 0;">
					<tr>
						<th><label>Suite Category *</label></th>
						<td>
							<select name="room_type_id" required style="max-width: 250px;">
								<?php foreach ( $room_types as $t ) : ?>
									<option value="<?php echo esc_attr( $t['id'] ); ?>"><?php echo esc_html( $t['title'] ); ?></option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
					<tr>
						<th><label>Room Number / ID *</label></th>
						<td><input type="text" name="room_number" class="regular-text" placeholder="e.g. 106, 204, V04, PH-03" required /></td>
					</tr>
					<tr>
						<th><label>Floor / Wing</label></th>
						<td><input type="text" name="floor" class="regular-text" placeholder="e.g. 2nd Floor, Ocean Wing" /></td>
					</tr>
				</table>
				<p class="submit">
					<input type="submit" name="hm_add_physical_room" class="button button-primary" value="Assign Room Number" />
				</p>
			</form>

			<h3 style="font-size: 1rem; color: #0f172a; margin-top: 30px; border-bottom: 1px solid #f1f5f9; padding-bottom: 6px;">
				Existing Physical Rooms (<?php echo count( $physical_rooms ); ?> total)
			</h3>
			<div style="max-height: 220px; overflow-y: auto; border: 1px solid #e2e8f0; border-radius: 8px;">
				<table style="width: 100%; border-collapse: collapse; font-size: 0.85rem;">
					<thead>
						<tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0;">
							<th style="padding: 8px 12px; text-align: left;">Room #</th>
							<th style="padding: 8px 12px; text-align: left;">Category</th>
							<th style="padding: 8px 12px; text-align: left;">Housekeeping</th>
							<th style="padding: 8px 12px; text-align: right;">Action</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $physical_rooms as $pr ) : ?>
							<tr style="border-bottom: 1px solid #f1f5f9;">
								<td style="padding: 8px 12px; font-weight: 600;">Room <?php echo esc_html( $pr['room_number'] ); ?></td>
								<td style="padding: 8px 12px; color: #64748b;"><?php echo esc_html( $pr['type_title'] ); ?></td>
								<td style="padding: 8px 12px;">
									<span style="color: <?php echo $pr['housekeeping_status'] === 'clean' ? '#16a34a' : '#dc2626'; ?>;">
										● <?php echo ucfirst( $pr['housekeeping_status'] ); ?>
									</span>
								</td>
								<td style="padding: 8px 12px; text-align: right;">
									<a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=hotel-rooms&action=delete_physical_room&room_id=' . $pr['id'] ), 'hm_delete_physical_room' ); ?>"
										onclick="return confirm('Are you sure you want to delete Room <?php echo esc_js( $pr['room_number'] ); ?>?');"
										style="color: #dc2626; text-decoration: none; font-weight: 600;">
										Delete
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</div>
	</div>

	<!-- Room Categories Table -->
	<!-- Room Categories Table with Update & Delete Actions -->
	<h2 style="font-size: 1.3rem; color: #0f172a; margin-bottom: 14px;">Current Suite Categories</h2>
	<table class="hm-data-table">
		<thead>
			<tr>
				<th>Image</th>
				<th>Suite Category</th>
				<th>Pricing</th>
				<th>Occupancy</th>
				<th>Stock (Inventory)</th>
				<th>Amenities</th>
				<th style="text-align: right;">Actions</th>
			</tr>
		</thead>
		<tbody>
			<?php if ( ! empty( $room_types ) ) : ?>
				<?php foreach ( $room_types as $rt ) :
					$am_list = ! empty( $rt['amenities'] ) ? json_decode( $rt['amenities'], true ) : [];
				?>
					<tr>
						<td style="width: 80px;">
							<img src="<?php echo esc_url( $rt['featured_image'] ? $rt['featured_image'] : 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=200&q=80' ); ?>" style="width: 70px; height: 50px; object-fit: cover; border-radius: 6px;" />
						</td>
						<td>
							<strong><?php echo esc_html( $rt['title'] ); ?></strong>
							<div style="font-size: 0.8rem; color: #64748b;"><?php echo esc_html( $rt['bed_type'] ); ?> &bull; <?php echo esc_html( $rt['size_sqft'] ); ?> sqft</div>
						</td>
						<td>
							<strong>₹<?php echo number_format( (float) $rt['base_price'] ); ?></strong>/night
							<?php if ( (float) $rt['weekend_price'] > 0 ) : ?>
								<div style="font-size: 0.75rem; color: #b45309;">Weekend: ₹<?php echo number_format( (float) $rt['weekend_price'] ); ?></div>
							<?php endif; ?>
						</td>
						<td>Up to <?php echo esc_html( $rt['max_occupancy'] ); ?> guests</td>
						<td>
							<span style="font-size: 1.1rem; font-weight: 700; color: #16a34a;"><?php echo esc_html( $rt['total_inventory'] ); ?></span> rooms
						</td>
						<td>
							<?php foreach ( array_slice( (array) $am_list, 0, 3 ) as $am ) : ?>
								<span class="hm-tag" style="margin-right: 4px;"><?php echo esc_html( $am ); ?></span>
							<?php endforeach; ?>
						</td>
						<td style="text-align: right; white-space: nowrap;">
							<a href="<?php echo esc_url( admin_url( 'admin.php?page=hotel-rooms&action=edit_suite&type_id=' . $rt['id'] ) ); ?>"
								class="hm-btn-action" style="background: #2563eb;">
								✏️ Edit
							</a>
							<a href="<?php echo wp_nonce_url( admin_url( 'admin.php?page=hotel-rooms&action=delete_suite&type_id=' . $rt['id'] ), 'hm_delete_suite' ); ?>"
								onclick="return confirm('Are you sure you want to delete suite category &quot;<?php echo esc_js( $rt['title'] ); ?>&quot;? All associated physical rooms and calendar inventory will be deleted.');"
								class="hm-btn-action" style="background: #dc2626;">
								🗑️ Delete
							</a>
						</td>
					</tr>
				<?php endforeach; ?>
			<?php else : ?>
				<tr>
					<td colspan="7" style="text-align: center; padding: 40px; color: #64748b;">
						No suite categories found. Use the form above to create your first suite category!
					</td>
				</tr>
			<?php endif; ?>
		</tbody>
	</table>
</div>


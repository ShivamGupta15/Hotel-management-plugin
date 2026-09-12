<?php
namespace Hotel_Management\Frontend;

if ( ! defined( 'ABSPATH' ) && ! defined( 'WPINC' ) ) {
	exit;
}

/**
 * Frontend Controller for Hotel Management System
 * Handles shortcodes, template rendering, and asset enqueues.
 */
class Hotel_Frontend {

	/**
	 * Track if checkout modal drawer has already been rendered.
	 *
	 * @var bool
	 */
	private static $modal_rendered = false;

	/**
	 * Initialize frontend hooks.
	 */
	public function init() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_action( 'wp_footer', [ $this, 'render_modal_drawer' ] );
		add_shortcode( 'hotel_booking_search', [ $this, 'render_search_bar' ] );
		add_shortcode( 'hotel_room_showcase', [ $this, 'render_room_showcase' ] );
		add_shortcode( 'hotel_booking_engine', [ $this, 'render_complete_booking_engine' ] );
		add_shortcode( 'hotel_guest_portal', [ $this, 'render_guest_portal' ] );

		// Elementor Integration
		add_action( 'elementor/widgets/register', [ $this, 'register_elementor_widgets' ] );
	}

	/**
	 * Enqueue Modern CSS & Vanilla JS
	 */
	public function enqueue_assets() {
		wp_enqueue_style(
			'hotel-modern-css',
			HOTEL_MGMT_URL . 'frontend/assets/css/hotel-modern.css',
			[],
			HOTEL_MGMT_VERSION
		);

		wp_enqueue_script(
			'hotel-booking-app',
			HOTEL_MGMT_URL . 'frontend/assets/js/hotel-booking-app.js',
			[],
			HOTEL_MGMT_VERSION,
			true
		);

		wp_localize_script(
			'hotel-booking-app',
			'HotelMgmtConfig',
			[
				'apiUrl'         => esc_url_raw( rest_url( 'hotel/v1' ) ),
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'currencySymbol' => '₹',
			]
		);
	}

	/**
	 * Render Sticky Search Bar Shortcode
	 */
	public function render_search_bar() {
		ob_start();
		?>
		<div class="hm-booking-wrapper">
			<form id="hm-search-form" class="hm-search-bar">
				<div class="hm-search-group">
					<label class="hm-search-label">Check-In</label>
					<input type="date" id="hm-search-checkin" class="hm-search-input" required />
				</div>
				<div class="hm-search-group">
					<label class="hm-search-label">Check-Out</label>
					<input type="date" id="hm-search-checkout" class="hm-search-input" required />
				</div>
				<div class="hm-search-group">
					<label class="hm-search-label">Guests (Adults)</label>
					<select id="hm-search-adults" class="hm-search-select">
						<option value="1">1 Adult</option>
						<option value="2" selected>2 Adults</option>
						<option value="3">3 Adults</option>
						<option value="4">4 Adults</option>
					</select>
				</div>
				<div class="hm-search-group">
					<label class="hm-search-label">Children</label>
					<select id="hm-search-children" class="hm-search-select">
						<option value="0" selected>0 Children</option>
						<option value="1">1 Child</option>
						<option value="2">2 Children</option>
					</select>
				</div>
				<div class="hm-search-group">
					<label class="hm-search-label">Rooms</label>
					<select id="hm-search-rooms" class="hm-search-select">
						<option value="1" selected>1 Room</option>
						<option value="2">2 Rooms</option>
						<option value="3">3 Rooms</option>
					</select>
				</div>
				<button type="submit" class="hm-btn-search">
					Check Availability
				</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render Room Showcase Grid Shortcode
	 */
	public function render_room_showcase() {
		global $wpdb;
		$table_types = $wpdb->prefix . 'hotel_room_types';
		$rooms       = $wpdb->get_results( "SELECT * FROM {$table_types} WHERE status = 'active' ORDER BY sort_order ASC", ARRAY_A );

		if ( empty( $rooms ) && ! get_option( 'hotel_data_initialized' ) ) {
			\Hotel_Management\Database\Hotel_Schema::create_tables();
			update_option( 'hotel_data_initialized', 1 );
			$rooms = $wpdb->get_results( "SELECT * FROM {$table_types} WHERE status = 'active' ORDER BY sort_order ASC", ARRAY_A );
		}

		ob_start();
		?>
		<div class="hm-booking-wrapper">
			<div id="hm-rooms-results-container" class="hm-rooms-grid">
				<?php if ( ! empty( $rooms ) ) : ?>
					<?php foreach ( $rooms as $room ) :
						$amenities = ! empty( $room['amenities'] ) ? json_decode( $room['amenities'], true ) : [];
						$display_amenities = array_slice( $amenities, 0, 4 );
					?>
						<article class="hm-room-card">
							<div class="hm-room-image-wrap">
								<img src="<?php echo esc_url( ! empty( $room['featured_image'] ) ? $room['featured_image'] : 'https://images.unsplash.com/photo-1582719478250-c89cae4dc85b?auto=format&fit=crop&w=800&q=80' ); ?>" alt="<?php echo esc_attr( $room['title'] ); ?>" class="hm-room-image" loading="lazy" />
								<div class="hm-room-badge"><?php echo esc_html( $room['bed_type'] ); ?></div>
							</div>
							<div class="hm-room-body">
								<h3 class="hm-room-title"><?php echo esc_html( $room['title'] ); ?></h3>
								<div class="hm-room-specs">
									<span>👤 Up to <?php echo esc_html( $room['max_occupancy'] ); ?> guests</span>
									<span>📐 <?php echo esc_html( $room['size_sqft'] ); ?> sqft</span>
								</div>
								<p class="hm-room-desc"><?php echo esc_html( ! empty( $room['short_description'] ) ? $room['short_description'] : $room['description'] ); ?></p>
								<div class="hm-amenities-tags">
									<?php foreach ( $display_amenities as $am ) : ?>
										<span class="hm-tag"><?php echo esc_html( $am ); ?></span>
									<?php endforeach; ?>
								</div>
								<div class="hm-room-footer">
									<div class="hm-price-box">
										<span class="hm-price-amount">₹<?php echo number_format( (float) $room['base_price'] ); ?></span>
										<span class="hm-price-period">per night (excl. taxes)</span>
									</div>
									<button type="button" class="hm-btn-book"
										data-room-id="<?php echo esc_attr( $room['id'] ); ?>"
										data-room-title="<?php echo esc_attr( $room['title'] ); ?>"
										data-room-price="<?php echo esc_attr( $room['base_price'] ); ?>">
										Book Suite
									</button>
								</div>
							</div>
						</article>
					<?php endforeach; ?>
				<?php endif; ?>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render Stepped Checkout Modal Drawer (Rendered once per request)
	 */
	public function render_modal_drawer() {
		if ( self::$modal_rendered ) {
			return '';
		}
		self::$modal_rendered = true;
		?>
		<!-- Stepped Checkout Modal Drawer -->
		<div id="hm-booking-modal" class="hm-modal-overlay">
			<div class="hm-modal-container">
				<div class="hm-modal-header">
					<h3 class="hm-modal-title">Complete Your Reservation</h3>
					<button type="button" class="hm-modal-close" aria-label="Close">&times;</button>
				</div>
				<div id="hm-modal-content-slot" class="hm-modal-body">
					<!-- Injected dynamically by JS -->
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Render Complete Booking Engine (Search + Showcase + Modal Drawer)
	 */
	public function render_complete_booking_engine() {
		$search_html   = $this->render_search_bar();
		$showcase_html = $this->render_room_showcase();

		ob_start();
		echo $search_html;
		echo $showcase_html;
		$this->render_modal_drawer();
		?>
		<!-- Floating AI Concierge Widget -->
		<div id="hm-ai-concierge" style="position: fixed; bottom: 28px; right: 28px; z-index: 9999;">
			<button type="button" id="hm-ai-toggle-btn" style="background: var(--hm-accent-gradient); color: #fff; border: none; border-radius: 50px; padding: 14px 22px; font-weight: 600; box-shadow: 0 10px 25px rgba(180,83,9,0.35); cursor: pointer; display: flex; align-items: center; gap: 8px; font-size: 0.95rem;">
				<span>✨</span> AI Concierge
			</button>
			<div id="hm-ai-box" style="display: none; position: absolute; bottom: 65px; right: 0; width: 340px; background: #fff; border-radius: 18px; box-shadow: 0 20px 40px rgba(0,0,0,0.18); border: 1px solid #e2e8f0; overflow: hidden;">
				<div style="background: var(--hm-primary); color: #fff; padding: 16px; display: flex; justify-content: space-between; align-items: center;">
					<strong style="font-size: 0.95rem;">Grand Azure AI Concierge</strong>
					<button type="button" onclick="document.getElementById('hm-ai-box').style.display='none'" style="background:none; border:none; color:#fff; font-size:1.2rem; cursor:pointer;">&times;</button>
				</div>
				<div id="hm-ai-messages" style="padding: 14px; height: 260px; overflow-y: auto; font-size: 0.85rem; display: flex; flex-direction: column; gap: 10px; background: #f8fafc;">
					<div style="background: #fff; padding: 10px 12px; border-radius: 10px; border: 1px solid #e2e8f0; max-width: 85%;">
						Hello! I am your personal luxury concierge. Ask me anything about room amenities, check-in policies, or dining!
					</div>
				</div>
				<form id="hm-ai-form" style="display: flex; border-top: 1px solid #e2e8f0;" onsubmit="event.preventDefault(); window.sendHotelAiMsg();">
					<input type="text" id="hm-ai-input" placeholder="Ask a question..." style="flex: 1; border: none; padding: 12px 14px; font-size: 0.85rem; outline: none;" />
					<button type="submit" style="background: var(--hm-accent); color: #fff; border: none; padding: 0 16px; font-weight: 600; cursor: pointer;">Send</button>
				</form>
			</div>
		</div>
		<script>
		document.getElementById('hm-ai-toggle-btn').addEventListener('click', function() {
			var b = document.getElementById('hm-ai-box');
			b.style.display = b.style.display === 'none' ? 'block' : 'none';
		});
		window.sendHotelAiMsg = async function() {
			var inp = document.getElementById('hm-ai-input');
			var msg = inp.value.trim();
			if (!msg) return;
			var box = document.getElementById('hm-ai-messages');
			box.innerHTML += '<div style="background: #0f172a; color: #fff; padding: 10px 12px; border-radius: 10px; align-self: flex-end; max-width: 85%;">' + msg + '</div>';
			inp.value = '';
			box.scrollTop = box.scrollHeight;
			try {
				var res = await fetch('/wp-json/hotel/v1/ai-concierge', {
					method: 'POST',
					headers: {'Content-Type': 'application/json'},
					body: JSON.stringify({message: msg})
				});
				var data = await res.json();
				box.innerHTML += '<div style="background: #fff; padding: 10px 12px; border-radius: 10px; border: 1px solid #e2e8f0; max-width: 85%;">' + data.reply + '</div>';
				box.scrollTop = box.scrollHeight;
			} catch(e) {
				box.innerHTML += '<div style="color:red; font-size:0.75rem;">Error connecting to concierge.</div>';
			}
		};
		</script>
		<?php
		return ob_get_clean();
	}

	/**
	 * Render Guest Lookup Portal
	 */
	public function render_guest_portal() {
		ob_start();
		?>
		<div class="hm-booking-wrapper" style="max-width: 600px; margin: 40px auto; padding: 24px; background: #fff; border-radius: 16px; box-shadow: var(--hm-card-shadow);">
			<h3 style="font-family: var(--hm-font-heading); margin-top: 0; font-size: 1.5rem;">Access Your Booking</h3>
			<p style="color: #64748b; font-size: 0.9rem;">Enter your 32-character booking token or booking reference to view digital pass and invoice.</p>
			<form id="hm-guest-lookup-form" onsubmit="event.preventDefault(); const t = document.getElementById('hm-token-input').value; if(t) window.location.href='?booking_token=' + encodeURIComponent(t);">
				<input type="text" id="hm-token-input" class="hm-form-input" style="width: 100%; margin-bottom: 16px;" placeholder="e.g. 8f92ab14de..." required />
				<button type="submit" class="hm-btn-search" style="width: 100%; justify-content: center;">View My Reservation</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Register Custom Elementor Widgets
	 */
	public function register_elementor_widgets( $widgets_manager ) {
		require_once HOTEL_MGMT_PATH . 'frontend/class-hotel-elementor.php';
		Hotel_Elementor::register( $widgets_manager );
	}
}

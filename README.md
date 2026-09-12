# Luxury Hotel Booking & Property Management System for WordPress

A high-performance, concurrency-safe, enterprise-grade Hotel Booking and Property Management System (PMS) built specifically for WordPress. Fully modular, decoupled from themes, and compatible with Elementor, block themes, and traditional WordPress themes.

---

## 📋 System Requirements

Ensure the target server meets these baseline requirements before installing:

| Component | Minimum Required | Recommended |
| :--- | :--- | :--- |
| **WordPress** | 6.0 or higher | Latest stable release |
| **PHP Version** | 8.0 or higher | PHP 8.1, 8.2, or 8.3 |
| **Database** | MySQL 5.7+ or MariaDB 10.3+ | MySQL 8.0+ (InnoDB required) |
| **PHP Extensions** | `curl`, `json`, `mbstring`, `openssl`, `mysqli` | Enabled by default on most hosts |
| **PHP Memory Limit** | `128M` | `256M` or higher |
| **Web Server** | Apache (with `mod_rewrite`), Nginx, or LiteSpeed | HTTPS / SSL active |

---

## 🚀 Installation

### Method 1: WordPress Admin Upload (Recommended)
1. Download or locate the deployable package: `hotel-management.zip`.
2. In your WordPress admin dashboard, navigate to **Plugins $\rightarrow$ Add New $\rightarrow$ Upload Plugin**.
3. Choose `hotel-management.zip` and click **Install Now**.
4. Once uploaded, click **Activate Plugin**.

### Method 2: Manual Upload via SFTP / SSH
1. Extract `hotel-management.zip` on your computer.
2. Upload the unzipped `hotel-management` folder into your site's `wp-content/plugins/` directory:
   ```bash
   # Target location:
   /path/to/wordpress/wp-content/plugins/hotel-management/
   ```
3. In your WordPress admin dashboard, go to **Plugins $\rightarrow$ Installed Plugins**.
4. Locate **Hotel Booking & Management System** and click **Activate**.

### Method 3: WP-CLI
```bash
wp plugin install /path/to/hotel-management.zip --activate
```

---

## ⚙️ Mandatory Post-Installation Steps

Follow these steps immediately after activation to ensure all booking, payment, and inventory services operate smoothly:

### Step 1: Configure Permalinks (CRITICAL)
The booking engine and checkout system rely on the WordPress REST API (`/wp-json/hotel/v1/...`). Standard plain permalinks (`?p=123`) will cause API routing failures.

1. In WordPress Admin, navigate to **Settings $\rightarrow$ Permalinks**.
2. Under **Common Settings**, select **Post name** (`/%postname%/`).
3. Click **Save Changes** at the bottom of the page.

---

### Step 2: Configure Razorpay Payment Gateway
1. Navigate to **Grand Hotel $\rightarrow$ Settings & APIs** (accessible only by Site Administrators).
2. Enter your credentials:
   * **Razorpay Key ID**: e.g., `rzp_test_...` or `rzp_live_...`
   * **Razorpay Key Secret**: Your secret key from the [Razorpay Dashboard](https://dashboard.razorpay.com/#/app/keys).
   * **Webhook Secret**: Your chosen secret for verifying webhook requests.
   * **Test / Sandbox Mode**: Check this box while developing or testing. When enabled, local environments or unconfigured test accounts automatically fall back to sandbox mock orders without breaking the checkout flow.
3. Click **Save Hotel Settings**.
4. **Webhook Setup (Production)**:
   * In your [Razorpay Dashboard](https://dashboard.razorpay.com/#/app/webhooks), add a new webhook.
   * **Webhook URL**: `https://yourdomain.com/wp-json/hotel/v1/webhook/razorpay`
   * **Secret**: Enter the exact webhook secret saved in your Hotel Settings.
   * **Active Events**: Select `payment.captured` and `payment.failed`.

---

### Step 3: Embed Frontend Booking Engine
You can embed the booking engine on any page using either shortcodes or Elementor widgets:

#### Option A: Using Shortcodes
Create a new page in WordPress (e.g., titled **"Book Your Stay"** with slug `/book/`), add a Shortcode block, and insert:
```text
[hotel_booking_engine]
```
This renders the complete stepped flow: sticky date/guest search bar, luxury room showcase cards, live availability query, and the stepped checkout drawer with Razorpay payment.

*Optional Guest Lookup Page:* Create a page (e.g. `/my-booking/`) and insert `[hotel_guest_portal]` so guests can view their digital voucher pass using their 32-character booking token.

#### Option B: Using Elementor
If Elementor is active on your site, open any page with the Elementor editor:
1. In the Elementor widget search, type **Hotel**.
2. Three custom widgets will appear:
   * **Hotel Complete Booking Engine**: Complete search + showcase + checkout drawer.
   * **Hotel Booking Bar**: Standalone sticky availability search bar.
   * **Hotel Rooms Showcase**: Standalone luxury suite showcase grid.
3. Drag and drop into your desired section and hit **Publish**.

---

### Step 4: Configure Room Categories & Physical Rooms
Upon activation, the plugin automatically creates default starter suites (Deluxe Ocean Suite, Executive Garden Villa, Presidential Penthouse) with a 90-day calendar inventory. Customize them for your property:

1. Go to **Grand Hotel $\rightarrow$ Rooms & Suites**.
2. **Edit Existing Suites**: Update pricing, maximum occupancy, bed types, descriptions, and amenities.
3. **Assign Physical Room Numbers**: Under each category, assign specific room numbers (e.g. `101`, `102`, `V01`) and floor designations. Mistakes can easily be unassigned with one click.
4. **Delete or Add Categories**: Add new suite types or remove categories no longer offered.
5. Go to **Grand Hotel $\rightarrow$ Inventory & Rates**:
   * Review daily room counts and pricing.
   * Use the **Bulk Rate & Inventory Override** tool to set weekend rate premiums or seasonal price increases for specific date ranges.

---

### Step 5: Configure Production Cron Worker (Pessimistic Hold Cleanup)
When a customer selects a suite and enters checkout, the system uses MySQL pessimistic row locking (`SELECT ... FOR UPDATE`) to place a **15-minute temporary hold** on the inventory. If the customer closes their browser or abandons payment, the hold expires and must be released.

WordPress default cron runs only when visitors browse the site. In production, configure a real server cron job to release holds with 1-minute precision:

1. Open `wp-config.php` and add:
   ```php
   define( 'DISABLE_WP_CRON', true );
   ```
2. On your server (cPanel Cron Jobs, Plesk, or Linux `crontab -e`), add a 1-minute cron:
   ```bash
   * * * * * curl -s https://yourdomain.com/wp-cron.php?doing_wp_cron > /dev/null 2>&1
   ```

---

### Step 6: Assign Staff Roles & Permissions
The plugin registers custom WordPress user roles with strict server-side capability checks:

* **Hotel Administrator** (`manage_hotel_all`, `manage_options`): Full access to PMS, room deletion, rates, and API secrets.
* **Hotel Manager** (`manage_hotel_rates`, `manage_hotel_rooms`, `manage_hotel_bookings`): Access to Tape Chart, room status, reservations, and pricing calendars.
* **Hotel Front Desk** (`manage_hotel_bookings`, `checkin_hotel_guests`): Front desk staff access for real-time guest check-in, room assignment, and check-out.
* **Hotel Guest** (`view_own_hotel_bookings`): Access to personal booking passes and invoices.

To create staff accounts:
1. Navigate to **Users $\rightarrow$ Add New**.
2. Under **Role**, select **Hotel Front Desk** or **Hotel Manager**.

---

### Step 7: WhatsApp & AI Concierge (Optional)
Navigate to **Grand Hotel $\rightarrow$ Settings & APIs**:
* **WhatsApp Cloud API**: Enter your Meta Phone Number ID and Permanent Access Token to enable automated booking confirmation messages with digital pass links.
* **AI Concierge**: Enter your OpenAI API key (`sk-proj-...`) to power the 24/7 floating AI guest concierge on the booking page.

---

## 🧩 Shortcodes Reference

| Shortcode | Description |
| :--- | :--- |
| `[hotel_booking_engine]` | Complete booking engine: search bar, suite cards, and stepped checkout drawer. |
| `[hotel_booking_search]` | Standalone search bar (Check-in, Check-out, Adults, Children, Room Count). |
| `[hotel_room_showcase]` | Standalone showcase grid displaying active room categories and amenities. |
| `[hotel_guest_portal]` | Guest self-service portal to lookup reservation passes via booking token. |

---

## 🔒 Security Architecture

* **Decoupled Business Logic**: Zero business logic inside WordPress themes. All database transactions and concurrency checks reside in isolated plugin services.
* **ACID Concurrency Safety**: Guaranteed zero overselling. Database operations execute inside InnoDB transactions with `SELECT ... FOR UPDATE` row locks.
* **No Sensitive Payment Data**: Credit card numbers and CVVs are processed entirely by Razorpay. Only gateway transaction IDs and cryptographic signatures are recorded.
* **Server-Side Authorization**: Every administrative and manager endpoint validates capabilities via `current_user_can()`. Frontend role tampering is impossible.
* **Cryptographic Signatures**: Razorpay checkouts and webhook events are validated using HMAC-SHA256 with timing-attack resistant `hash_equals()`.

---

## 🛠️ Automated Testing Suite

The plugin includes automated test suites to verify integrity:

1. **Payment Gateway & Cryptographic Verification**:
   ```bash
   php wp-content/plugins/hotel-management/tests/test-payment-service.php
   ```
2. **Concurrency & Race Condition Simulation**:
   ```bash
   node wp-content/plugins/hotel-management/tests/verify-concurrency-simulation.js
   ```

---

## ❓ Frequently Asked Questions (FAQ)

**Q: Search returns "No Suites Available" for selected dates?**
* Check **Grand Hotel $\rightarrow$ Inventory & Rates Calendar**. Ensure your date range has inventory generated. If inventory is missing, use the **Bulk Update** tool to generate inventory for that date range.

**Q: Clicks on "Book Suite" do not open the checkout modal?**
* Verify that you have completed **Step 1** (Permalinks set to "Post name").
* Clear any page caching plugins (WP Rocket, LiteSpeed Cache, W3 Total Cache) to ensure the latest JavaScript assets are loaded.

**Q: Can I test bookings without real money or real credit cards?**
* Yes! In **Grand Hotel $\rightarrow$ Settings & APIs**, ensure **Test / Sandbox Mode** is enabled. When on the checkout payment step, click **⚡ Instant Test Payment (Sandbox Bypass)** to immediately verify the reservation and generate a digital voucher pass without contacting live banking networks.

---

## 📄 License
This plugin is licensed under the **GPL-2.0-or-later** license.

# Hotel-management-plugin

<?php
/**
 * Plugin Name:       Hotel Booking & Management System
 * Plugin URI:        https://github.com/wp-hotel-management
 * Description:       High-performance, concurrency-safe hotel booking, inventory management, dynamic pricing, staff operations, and customer booking engine.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Antigravity Senior Architecture Team
 * License:           GPL-2.0-or-later
 * Text Domain:       hotel-management
 * Domain Path:       /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Current plugin version and environment constants.
 */
define( 'HOTEL_MGMT_VERSION', '1.0.0' );
define( 'HOTEL_MGMT_PATH', plugin_dir_path( __FILE__ ) );
define( 'HOTEL_MGMT_URL', plugin_dir_url( __FILE__ ) );
define( 'HOTEL_MGMT_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Pre-flight Environment & Compatibility Verification
 *
 * @return bool True if all environment requirements are satisfied, false otherwise.
 */
function hotel_management_check_requirements() {
	$errors = [];

	if ( version_compare( PHP_VERSION, '8.0', '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Current PHP version, 2: Required PHP version */
			__( 'Hotel Booking & Management System requires PHP version %2$s or higher. Your server is running PHP %1$s.', 'hotel-management' ),
			PHP_VERSION,
			'8.0'
		);
	}

	global $wp_version;
	if ( version_compare( $wp_version, '6.0', '<' ) ) {
		$errors[] = sprintf(
			/* translators: 1: Current WP version, 2: Required WP version */
			__( 'Hotel Booking & Management System requires WordPress version %2$s or higher. Your site is running WordPress %1$s.', 'hotel-management' ),
			$wp_version,
			'6.0'
		);
	}

	$required_extensions = [ 'curl', 'json', 'mbstring', 'openssl' ];
	$missing_extensions  = [];
	foreach ( $required_extensions as $ext ) {
		if ( ! extension_loaded( $ext ) ) {
			$missing_extensions[] = $ext;
		}
	}

	if ( ! empty( $missing_extensions ) ) {
		$errors[] = sprintf(
			/* translators: %s: Comma-separated list of missing PHP extensions */
			__( 'The following required PHP extensions are missing from your server: %s.', 'hotel-management' ),
			implode( ', ', $missing_extensions )
		);
	}

	if ( ! empty( $errors ) ) {
		add_action( 'admin_notices', function() use ( $errors ) {
			?>
			<div class="notice notice-error is-dismissible">
				<p><strong><?php esc_html_e( 'Hotel Booking & Management System initialization blocked:', 'hotel-management' ); ?></strong></p>
				<ul>
					<?php foreach ( $errors as $err ) : ?>
						<li><?php echo esc_html( $err ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
			<?php
		} );
		return false;
	}

	return true;
}

// Abort loading if server does not satisfy baseline requirements
if ( ! hotel_management_check_requirements() ) {
	return;
}

/**
 * Register PSR-4 Autoloader
 */
require_once HOTEL_MGMT_PATH . 'includes/class-hotel-autoloader.php';
Hotel_Management\Includes\Hotel_Autoloader::register();

/**
 * Load environment variables from .env if present
 */
require_once HOTEL_MGMT_PATH . 'includes/class-hotel-env.php';
Hotel_Management\Includes\Hotel_Env::load();

/**
 * Plugin Activation Handler
 */
function activate_hotel_management() {
	require_once HOTEL_MGMT_PATH . 'includes/class-hotel-activator.php';
	Hotel_Management\Includes\Hotel_Activator::activate();
}

/**
 * Plugin Deactivation Handler
 */
function deactivate_hotel_management() {
	require_once HOTEL_MGMT_PATH . 'includes/class-hotel-deactivator.php';
	Hotel_Management\Includes\Hotel_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_hotel_management' );
register_deactivation_hook( __FILE__, 'deactivate_hotel_management' );

/**
 * Add Settings quick action link on the Plugins page
 */
add_filter( 'plugin_action_links_' . HOTEL_MGMT_BASENAME, function( $links ) {
	$settings_url  = admin_url( 'admin.php?page=hotel-settings' );
	$settings_link = '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'hotel-management' ) . '</a>';
	array_unshift( $links, $settings_link );
	return $links;
} );

/**
 * Initialize and Run the Plugin Core
 */
function run_hotel_management() {
	load_plugin_textdomain( 'hotel-management', false, dirname( HOTEL_MGMT_BASENAME ) . '/languages' );

	$plugin = new Hotel_Management\Includes\Hotel_Core();
	$plugin->run();
}
add_action( 'plugins_loaded', 'run_hotel_management' );


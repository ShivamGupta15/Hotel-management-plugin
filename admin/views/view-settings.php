<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	wp_die( __( 'Access denied. Only site administrators can access Hotel Settings & APIs.', 'hotel-management' ), 403 );
}
?>

<div class="hm-admin-wrap" style="max-width: 800px;">
	<div class="hm-admin-header">
		<div>
			<h1 class="hm-admin-title">Hotel Settings &amp; API Integrations</h1>
			<p class="hm-admin-subtitle">Configure Razorpay payment gateway, WhatsApp Cloud API, and AI concierge</p>
		</div>
	</div>

	<form method="post" action="options.php" style="background:#ffffff; padding: 28px; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 2px 8px rgba(0,0,0,0.04);">
		<?php
		settings_fields( 'hotel_settings_group' );
		do_settings_sections( 'hotel_settings_group' );
		?>

		<h2 style="font-size: 1.15rem; color: #0f172a; margin-top: 0; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
			💳 Razorpay Payment Gateway
		</h2>
		<p style="color: #64748b; font-size: 0.85rem; margin-bottom: 20px;">
			Configure your Razorpay Live or Test API keys. Leave blank to operate in automatic Sandbox simulation mode.
		</p>

		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="hotel_razorpay_key_id">Razorpay Key ID</label></th>
				<td>
					<input type="text" id="hotel_razorpay_key_id" name="hotel_razorpay_key_id" value="<?php echo esc_attr( get_option( 'hotel_razorpay_key_id' ) ); ?>" class="regular-text" placeholder="rzp_test_..." />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="hotel_razorpay_key_secret">Razorpay Key Secret</label></th>
				<td>
					<input type="password" id="hotel_razorpay_key_secret" name="hotel_razorpay_key_secret" value="<?php echo esc_attr( get_option( 'hotel_razorpay_key_secret' ) ); ?>" class="regular-text" />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="hotel_razorpay_webhook_secret">Webhook Secret</label></th>
				<td>
					<input type="password" id="hotel_razorpay_webhook_secret" name="hotel_razorpay_webhook_secret" value="<?php echo esc_attr( get_option( 'hotel_razorpay_webhook_secret' ) ); ?>" class="regular-text" />
					<p class="description">Set your webhook URL in Razorpay dashboard to: <code><?php echo esc_url( rest_url( 'hotel/v1/webhook/razorpay' ) ); ?></code></p>
				</td>
			</tr>
			<tr>
				<th scope="row">Test / Sandbox Mode</th>
				<td>
					<label>
						<input type="checkbox" name="hotel_razorpay_test_mode" value="1" <?php checked( '1', get_option( 'hotel_razorpay_test_mode', '1' ) ); ?> />
						Enable test mode / simulated payments
					</label>
				</td>
			</tr>
		</table>

		<h2 style="font-size: 1.15rem; color: #0f172a; margin-top: 30px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
			💬 WhatsApp Cloud API Integration
		</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="hotel_whatsapp_phone_id">Phone Number ID</label></th>
				<td>
					<input type="text" id="hotel_whatsapp_phone_id" name="hotel_whatsapp_phone_id" value="<?php echo esc_attr( get_option( 'hotel_whatsapp_phone_id' ) ); ?>" class="regular-text" placeholder="10492839281..." />
				</td>
			</tr>
			<tr>
				<th scope="row"><label for="hotel_whatsapp_token">Permanent Access Token</label></th>
				<td>
					<input type="password" id="hotel_whatsapp_token" name="hotel_whatsapp_token" value="<?php echo esc_attr( get_option( 'hotel_whatsapp_token' ) ); ?>" class="regular-text" />
				</td>
			</tr>
		</table>

		<h2 style="font-size: 1.15rem; color: #0f172a; margin-top: 30px; border-bottom: 1px solid #f1f5f9; padding-bottom: 10px;">
			🤖 AI Guest Concierge
		</h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="hotel_ai_api_key">OpenAI API Key</label></th>
				<td>
					<input type="password" id="hotel_ai_api_key" name="hotel_ai_api_key" value="<?php echo esc_attr( get_option( 'hotel_ai_api_key' ) ); ?>" class="regular-text" placeholder="sk-proj-..." />
					<p class="description">Powers the floating AI Concierge assistant on the guest booking page.</p>
				</td>
			</tr>
		</table>

		<?php submit_button( __( 'Save Hotel Settings', 'hotel-management' ) ); ?>
	</form>
</div>


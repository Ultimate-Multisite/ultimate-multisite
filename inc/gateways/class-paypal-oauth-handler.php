<?php
/**
 * PayPal OAuth Handler.
 *
 * Handles the OAuth "Connect with PayPal" flow via a proxy server.
 * The proxy holds the partner credentials securely; this handler
 * communicates with the proxy to initiate onboarding and verify merchants.
 *
 * @package WP_Ultimo
 * @subpackage Gateways
 * @since 2.0.0
 */

namespace WP_Ultimo\Gateways;

use Psr\Log\LogLevel;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * PayPal OAuth Handler class.
 *
 * @since 2.0.0
 */
class PayPal_OAuth_Handler {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Holds if we are in test mode.
	 *
	 * @since 2.0.0
	 * @var bool
	 */
	protected $test_mode = true;

	/**
	 * Initialize the OAuth handler.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		$this->test_mode = (bool) (int) wu_get_setting('paypal_rest_sandbox_mode', true);

		// Register AJAX handlers
		add_action('wp_ajax_wu_paypal_connect', [$this, 'ajax_initiate_oauth']);
		add_action('wp_ajax_wu_paypal_disconnect', [$this, 'ajax_disconnect']);

		// Handle OAuth return callback
		add_action('admin_init', [$this, 'handle_oauth_return']);
	}

	/**
	 * Get the PayPal Connect proxy URL.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	protected function get_proxy_url(): string {

		/**
		 * Filters the PayPal Connect proxy URL.
		 *
		 * @since 2.0.0
		 *
		 * @param string $url Proxy server URL.
		 */
		return apply_filters(
			'wu_paypal_connect_proxy_url',
			'https://ultimatemultisite.com/wp-json/paypal-connect/v1'
		);
	}

	/**
	 * Returns the PayPal API base URL based on test mode.
	 *
	 * Used only for the access token call needed by the REST gateway
	 * (merchant's own credentials, not partner credentials).
	 *
	 * @since 2.0.0
	 * @return string
	 */
	protected function get_api_base_url(): string {

		return $this->test_mode ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
	}

	/**
	 * AJAX handler to initiate OAuth flow via the proxy.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function ajax_initiate_oauth(): void {

		check_ajax_referer('wu_paypal_oauth', 'nonce');

		if (! current_user_can('manage_network_options')) {
			wp_send_json_error(
				[
					'message' => __('You do not have permission to do this.', 'ultimate-multisite'),
				]
			);
		}

		// Update test mode from request if provided
		if (isset($_POST['sandbox_mode'])) {
			$this->test_mode = (bool) (int) $_POST['sandbox_mode'];
		}

		// Build the return URL
		$return_url = add_query_arg(
			[
				'page'                 => 'wp-ultimo-settings',
				'tab'                  => 'payment-gateways',
				'wu_paypal_onboarding' => 'complete',
			],
			network_admin_url('admin.php')
		);

		$proxy_url = $this->get_proxy_url();

		// Call the proxy to initiate the OAuth flow
		$response = wp_remote_post(
			$proxy_url . '/oauth/init',
			[
				'body'    => wp_json_encode(
					[
						'returnUrl' => $return_url,
						'testMode'  => $this->test_mode,
					]
				),
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'timeout' => 30,
			]
		);

		if (is_wp_error($response)) {
			wu_log_add('paypal', 'Proxy init failed: ' . $response->get_error_message(), LogLevel::ERROR);

			wp_send_json_error(
				[
					'message' => __('Could not reach the PayPal Connect service. Please check that your server can make outbound HTTPS requests and try again.', 'ultimate-multisite'),
				]
			);
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$data        = json_decode(wp_remote_retrieve_body($response), true);

		if (200 !== $status_code || empty($data['actionUrl'])) {
			$error_msg = $data['error'] ?? __('Failed to initiate PayPal onboarding', 'ultimate-multisite');
			wu_log_add('paypal', 'Proxy init error: ' . $error_msg, LogLevel::ERROR);

			wp_send_json_error(
				[
					'message' => $error_msg,
				]
			);
		}

		// Store the tracking ID locally for verification on return
		$tracking_id = $data['trackingId'] ?? '';

		if ($tracking_id) {
			set_site_transient(
				'wu_paypal_onboarding_' . $tracking_id,
				[
					'started'   => time(),
					'test_mode' => $this->test_mode,
				],
				DAY_IN_SECONDS
			);
		}

		wu_log_add('paypal', sprintf('OAuth initiated via proxy. Tracking ID: %s', $tracking_id));

		wp_send_json_success(
			[
				'redirect_url' => $data['actionUrl'],
				'tracking_id'  => $tracking_id,
			]
		);
	}

	/**
	 * Handle the OAuth return callback.
	 *
	 * When the merchant completes the PayPal onboarding flow, they are redirected
	 * back to WordPress with parameters indicating success/failure.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function handle_oauth_return(): void {

		// Check if this is an OAuth return - nonce verification not possible for external OAuth callback
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback from PayPal
		if (! isset($_GET['wu_paypal_onboarding']) || 'complete' !== $_GET['wu_paypal_onboarding']) {
			return;
		}

		// Verify we're on the settings page
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- OAuth callback from PayPal
		if (! isset($_GET['page']) || 'wp-ultimo-settings' !== $_GET['page']) {
			return;
		}

		// Get parameters from PayPal
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- OAuth callback from PayPal
		$merchant_id         = isset($_GET['merchantIdInPayPal']) ? sanitize_text_field(wp_unslash($_GET['merchantIdInPayPal'])) : '';
		$merchant_email      = isset($_GET['merchantId']) ? sanitize_email(wp_unslash($_GET['merchantId'])) : '';
		$permissions_granted = isset($_GET['permissionsGranted']) && 'true' === $_GET['permissionsGranted'];
		$tracking_id         = isset($_GET['tracking_id']) ? sanitize_text_field(wp_unslash($_GET['tracking_id'])) : '';
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Verify tracking ID was created by us
		$onboarding_data = get_site_transient('wu_paypal_onboarding_' . $tracking_id);

		if (! $onboarding_data) {
			wu_log_add('paypal', 'OAuth return with invalid tracking ID: ' . $tracking_id, LogLevel::WARNING);
			$this->add_oauth_notice('error', __('Invalid onboarding session. Please try again.', 'ultimate-multisite'));

			return;
		}

		// Update test mode to match the onboarding session
		$this->test_mode = $onboarding_data['test_mode'];

		// Check if permissions were granted
		if (! $permissions_granted) {
			wu_log_add('paypal', 'OAuth: Merchant did not grant permissions', LogLevel::WARNING);
			$this->add_oauth_notice('warning', __('PayPal permissions were not granted. Please try again and approve the required permissions.', 'ultimate-multisite'));

			return;
		}

		// Verify the merchant status via the proxy
		$merchant_status = $this->verify_merchant_via_proxy($merchant_id, $tracking_id);

		if (is_wp_error($merchant_status)) {
			wu_log_add('paypal', 'Failed to verify merchant status: ' . $merchant_status->get_error_message(), LogLevel::ERROR);
			$this->add_oauth_notice('error', __('Failed to verify your PayPal account status. Please try again.', 'ultimate-multisite'));

			return;
		}

		// Store the merchant credentials
		$mode_prefix = $this->test_mode ? 'sandbox' : 'live';

		wu_save_setting("paypal_rest_{$mode_prefix}_merchant_id", $merchant_id);
		wu_save_setting("paypal_rest_{$mode_prefix}_merchant_email", $merchant_email);
		wu_save_setting('paypal_rest_connected', true);
		wu_save_setting('paypal_rest_connection_date', current_time('mysql'));
		wu_save_setting('paypal_rest_connection_mode', $mode_prefix);

		// Store additional status info if available
		$payments_receivable = isset($merchant_status['paymentsReceivable']) ? (bool) $merchant_status['paymentsReceivable'] : true;
		$email_confirmed     = isset($merchant_status['emailConfirmed']) ? (bool) $merchant_status['emailConfirmed'] : true;

		wu_save_setting("paypal_rest_{$mode_prefix}_payments_receivable", $payments_receivable);
		wu_save_setting("paypal_rest_{$mode_prefix}_email_confirmed", $email_confirmed);

		// Clean up the tracking transient
		delete_site_transient('wu_paypal_onboarding_' . $tracking_id);

		wu_log_add(
			'paypal',
			sprintf(
				'PayPal OAuth completed. Merchant ID: %s, Mode: %s, payments_receivable: %s, email_confirmed: %s',
				$merchant_id,
				$mode_prefix,
				$payments_receivable ? 'true' : 'false',
				$email_confirmed ? 'true' : 'false'
			)
		);

		// Automatically install webhooks for the connected account
		$this->install_webhook_after_oauth($mode_prefix);

		// Show required PayPal error messages when merchant status is incomplete
		if (! $payments_receivable) {
			$this->add_oauth_notice(
				'error',
				__('Your PayPal account is not yet able to receive payments. Please complete your PayPal account setup and try connecting again.', 'ultimate-multisite')
			);
		} elseif (! $email_confirmed) {
			$this->add_oauth_notice(
				'error',
				__('Your PayPal account email address is not confirmed. Please confirm your email with PayPal and try connecting again.', 'ultimate-multisite')
			);
		} else {
			$this->add_oauth_notice('success', __('PayPal account connected successfully!', 'ultimate-multisite'));
		}

		// Redirect to remove query parameters
		wp_safe_redirect(
			add_query_arg(
				[
					'page'             => 'wp-ultimo-settings',
					'tab'              => 'payment-gateways',
					'paypal_connected' => '1',
				],
				network_admin_url('admin.php')
			)
		);
		exit;
	}

	/**
	 * Verify merchant status via the proxy.
	 *
	 * The proxy holds the partner credentials needed to check the
	 * merchant's integration status with PayPal.
	 *
	 * @since 2.0.0
	 *
	 * @param string $merchant_id The merchant's PayPal ID.
	 * @param string $tracking_id The tracking ID from onboarding.
	 * @return array|\WP_Error Merchant status data or error.
	 */
	protected function verify_merchant_via_proxy(string $merchant_id, string $tracking_id) {

		$proxy_url = $this->get_proxy_url();

		$response = wp_remote_post(
			$proxy_url . '/oauth/verify',
			[
				'body'    => wp_json_encode(
					[
						'merchantId' => $merchant_id,
						'trackingId' => $tracking_id,
						'testMode'   => $this->test_mode,
					]
				),
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'timeout' => 30,
			]
		);

		if (is_wp_error($response)) {
			return $response;
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$data        = json_decode(wp_remote_retrieve_body($response), true);

		if (200 !== $status_code) {
			$error_msg = $data['error'] ?? __('Failed to verify merchant status', 'ultimate-multisite');

			return new \WP_Error('wu_paypal_verify_error', $error_msg);
		}

		return $data;
	}

	/**
	 * AJAX handler to disconnect PayPal.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function ajax_disconnect(): void {

		check_ajax_referer('wu_paypal_oauth', 'nonce');

		if (! current_user_can('manage_network_options')) {
			wp_send_json_error(
				[
					'message' => __('You do not have permission to do this.', 'ultimate-multisite'),
				]
			);
		}

		// Delete webhooks before clearing credentials
		$this->delete_webhooks_on_disconnect();

		// Notify proxy of disconnect (non-blocking)
		$proxy_url = $this->get_proxy_url();

		wp_remote_post(
			$proxy_url . '/deauthorize',
			[
				'body'     => wp_json_encode(
					[
						'siteUrl'  => get_site_url(),
						'testMode' => $this->test_mode,
					]
				),
				'headers'  => ['Content-Type' => 'application/json'],
				'timeout'  => 10,
				'blocking' => false,
			]
		);

		// Clear all connection data
		$settings_to_clear = [
			'paypal_rest_connected',
			'paypal_rest_connection_date',
			'paypal_rest_connection_mode',
			'paypal_rest_sandbox_merchant_id',
			'paypal_rest_sandbox_merchant_email',
			'paypal_rest_sandbox_payments_receivable',
			'paypal_rest_sandbox_email_confirmed',
			'paypal_rest_live_merchant_id',
			'paypal_rest_live_merchant_email',
			'paypal_rest_live_payments_receivable',
			'paypal_rest_live_email_confirmed',
			'paypal_rest_sandbox_webhook_id',
			'paypal_rest_live_webhook_id',
		];

		foreach ($settings_to_clear as $setting) {
			wu_save_setting($setting, '');
		}

		// Clear cached access tokens
		delete_site_transient('wu_paypal_rest_access_token_sandbox');
		delete_site_transient('wu_paypal_rest_access_token_live');

		wu_log_add('paypal', 'PayPal account disconnected');

		wp_send_json_success(
			[
				'message' => __('PayPal account disconnected successfully.', 'ultimate-multisite'),
			]
		);
	}

	/**
	 * Add an admin notice for OAuth status.
	 *
	 * @since 2.0.0
	 *
	 * @param string $type    Notice type: 'success', 'error', 'warning', 'info'.
	 * @param string $message The notice message.
	 * @return void
	 */
	protected function add_oauth_notice(string $type, string $message): void {

		set_site_transient(
			'wu_paypal_oauth_notice',
			[
				'type'    => $type,
				'message' => $message,
			],
			60
		);
	}

	/**
	 * Display OAuth notices.
	 *
	 * Should be called on admin_notices hook.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function display_oauth_notices(): void {

		$notice = get_site_transient('wu_paypal_oauth_notice');

		if ($notice) {
			delete_site_transient('wu_paypal_oauth_notice');

			$class = 'notice notice-' . esc_attr($notice['type']) . ' is-dismissible';
			printf(
				'<div class="%1$s"><p>%2$s</p></div>',
				esc_attr($class),
				esc_html($notice['message'])
			);
		}
	}

	/**
	 * Check if the proxy is reachable and configured.
	 *
	 * This replaces the old is_configured() which checked for local partner credentials.
	 * Now we just check if the proxy URL is set (it always is by default).
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function is_configured(): bool {

		return ! empty($this->get_proxy_url());
	}

	/**
	 * Check if the PayPal OAuth Connect feature is enabled.
	 *
	 * The feature flag is controlled by the PayPal proxy plugin on
	 * ultimatemultisite.com. OAuth Connect is only available when the
	 * proxy has partner credentials configured (i.e. the PayPal
	 * partnership is active). The result is cached for 12 hours.
	 *
	 * Local override: define WU_PAYPAL_OAUTH_ENABLED as true in
	 * wp-config.php to force-enable without the proxy check.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function is_oauth_feature_enabled(): bool {

		// Local constant override (useful for dev/testing)
		if (defined('WU_PAYPAL_OAUTH_ENABLED')) {
			return (bool) WU_PAYPAL_OAUTH_ENABLED;
		}

		/**
		 * Filters whether the PayPal OAuth Connect feature is enabled.
		 *
		 * Return a non-null value to override the remote check.
		 *
		 * @since 2.0.0
		 *
		 * @param bool|null $enabled Null to use remote check, bool to override.
		 */
		$override = apply_filters('wu_paypal_oauth_enabled', null);

		if (null !== $override) {
			return (bool) $override;
		}

		// Check cached flag from proxy
		$cached = get_site_transient('wu_paypal_oauth_enabled');

		if (false !== $cached) {
			return 'yes' === $cached;
		}

		// Fetch from proxy /status endpoint
		$proxy_url = $this->get_proxy_url();

		if (empty($proxy_url)) {
			return false;
		}

		$response = wp_remote_get(
			$proxy_url . '/status',
			['timeout' => 5]
		);

		if (is_wp_error($response)) {
			// Cache failure as disabled for 1 hour (retry sooner)
			set_site_transient('wu_paypal_oauth_enabled', 'no', HOUR_IN_SECONDS);

			return false;
		}

		$body    = json_decode(wp_remote_retrieve_body($response), true);
		$enabled = ! empty($body['oauth_enabled']);

		set_site_transient(
			'wu_paypal_oauth_enabled',
			$enabled ? 'yes' : 'no',
			12 * HOUR_IN_SECONDS
		);

		return $enabled;
	}

	/**
	 * Check if a merchant is connected via OAuth.
	 *
	 * @since 2.0.0
	 *
	 * @param bool $sandbox Whether to check sandbox mode.
	 * @return bool
	 */
	public function is_merchant_connected(bool $sandbox = true): bool {

		$mode_prefix = $sandbox ? 'sandbox' : 'live';
		$merchant_id = wu_get_setting("paypal_rest_{$mode_prefix}_merchant_id", '');

		return ! empty($merchant_id);
	}

	/**
	 * Get connected merchant details.
	 *
	 * @since 2.0.0
	 *
	 * @param bool $sandbox Whether to get sandbox mode details.
	 * @return array
	 */
	public function get_merchant_details(bool $sandbox = true): array {

		$mode_prefix = $sandbox ? 'sandbox' : 'live';

		return [
			'merchant_id'         => wu_get_setting("paypal_rest_{$mode_prefix}_merchant_id", ''),
			'merchant_email'      => wu_get_setting("paypal_rest_{$mode_prefix}_merchant_email", ''),
			'payments_receivable' => wu_get_setting("paypal_rest_{$mode_prefix}_payments_receivable", false),
			'email_confirmed'     => wu_get_setting("paypal_rest_{$mode_prefix}_email_confirmed", false),
			'connection_date'     => wu_get_setting('paypal_rest_connection_date', ''),
		];
	}

	/**
	 * Install webhooks after successful OAuth connection.
	 *
	 * Creates the webhook endpoint in PayPal to receive subscription and payment events.
	 *
	 * @since 2.0.0
	 *
	 * @param string $mode_prefix The mode prefix ('sandbox' or 'live').
	 * @return void
	 */
	protected function install_webhook_after_oauth(string $mode_prefix): void {

		try {
			// Get the PayPal REST gateway instance
			$gateway = wu_get_gateway('paypal-rest');

			if (! $gateway instanceof PayPal_REST_Gateway) {
				wu_log_add('paypal', 'Could not get PayPal REST gateway instance for webhook installation', LogLevel::WARNING);

				return;
			}

			// Ensure the gateway is in the correct mode
			$gateway->set_test_mode('sandbox' === $mode_prefix);

			// Install the webhook
			$result = $gateway->install_webhook();

			if (true === $result) {
				wu_log_add('paypal', sprintf('Webhook installed successfully for %s mode after OAuth', $mode_prefix));
			} elseif (is_wp_error($result)) {
				wu_log_add('paypal', sprintf('Failed to install webhook after OAuth: %s', $result->get_error_message()), LogLevel::ERROR);
			}
		} catch (\Exception $e) {
			wu_log_add('paypal', sprintf('Exception installing webhook after OAuth: %s', $e->getMessage()), LogLevel::ERROR);
		}
	}

	/**
	 * Delete webhooks when disconnecting from PayPal.
	 *
	 * Attempts to delete webhooks from both sandbox and live modes.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	protected function delete_webhooks_on_disconnect(): void {

		try {
			$gateway = wu_get_gateway('paypal-rest');

			if (! $gateway instanceof PayPal_REST_Gateway) {
				return;
			}

			// Try to delete sandbox webhook
			$gateway->set_test_mode(true);
			$result = $gateway->delete_webhook();

			if (is_wp_error($result)) {
				wu_log_add('paypal', sprintf('Failed to delete sandbox webhook: %s', $result->get_error_message()), LogLevel::WARNING);
			} else {
				wu_log_add('paypal', 'Sandbox webhook deleted during disconnect');
			}

			// Try to delete live webhook
			$gateway->set_test_mode(false);
			$result = $gateway->delete_webhook();

			if (is_wp_error($result)) {
				wu_log_add('paypal', sprintf('Failed to delete live webhook: %s', $result->get_error_message()), LogLevel::WARNING);
			} else {
				wu_log_add('paypal', 'Live webhook deleted during disconnect');
			}
		} catch (\Exception $e) {
			wu_log_add('paypal', sprintf('Exception deleting webhooks during disconnect: %s', $e->getMessage()), LogLevel::WARNING);
		}
	}
}

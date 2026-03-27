<?php
/**
 * PayPal REST Webhook Handler.
 *
 * Handles webhook notifications from PayPal REST API for subscriptions and payments.
 * Uses PayPal's webhook signature verification for security.
 *
 * @package WP_Ultimo
 * @subpackage Gateways
 * @since 2.0.0
 */

namespace WP_Ultimo\Gateways;

use Psr\Log\LogLevel;
use WP_Ultimo\Database\Payments\Payment_Status;
use WP_Ultimo\Database\Memberships\Membership_Status;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * PayPal REST Webhook Handler class.
 *
 * @since 2.0.0
 */
class PayPal_Webhook_Handler {

	use \WP_Ultimo\Traits\Singleton;

	/**
	 * Holds if we are in test mode.
	 *
	 * @since 2.0.0
	 * @var bool
	 */
	protected $test_mode = true;

	/**
	 * Initialize the webhook handler.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		$this->test_mode = (bool) (int) wu_get_setting('paypal_rest_sandbox_mode', true);

		// Register webhook listener
		add_action('wu_paypal-rest_process_webhooks', [$this, 'process_webhook']);
	}

	/**
	 * Returns the PayPal API base URL based on test mode.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	protected function get_api_base_url(): string {

		return $this->test_mode ? 'https://api-m.sandbox.paypal.com' : 'https://api-m.paypal.com';
	}

	/**
	 * Process incoming webhook.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function process_webhook(): void {

		$raw_body = file_get_contents('php://input');

		if (empty($raw_body)) {
			$this->log('Webhook received with empty body', LogLevel::WARNING);
			status_header(400);
			exit;
		}

		$event = json_decode($raw_body, true);

		if (json_last_error() !== JSON_ERROR_NONE || empty($event['event_type'])) {
			$this->log('Webhook received with invalid JSON', LogLevel::WARNING);
			status_header(400);
			exit;
		}

		$this->log(sprintf('Webhook received: %s, ID: %s', $event['event_type'], $event['id'] ?? 'unknown'));

		// Verify webhook signature
		if (! $this->verify_webhook_signature($raw_body)) {
			$this->log('Webhook signature verification failed', LogLevel::ERROR);
			status_header(401);
			exit;
		}

		// Process based on event type
		$event_type = $event['event_type'];
		$resource   = $event['resource'] ?? [];

		switch ($event_type) {
			// Subscription events
			case 'BILLING.SUBSCRIPTION.CREATED':
				$this->handle_subscription_created($resource);
				break;

			case 'BILLING.SUBSCRIPTION.ACTIVATED':
				$this->handle_subscription_activated($resource);
				break;

			case 'BILLING.SUBSCRIPTION.UPDATED':
				$this->handle_subscription_updated($resource);
				break;

			case 'BILLING.SUBSCRIPTION.CANCELLED':
				$this->handle_subscription_cancelled($resource);
				break;

			case 'BILLING.SUBSCRIPTION.SUSPENDED':
				$this->handle_subscription_suspended($resource);
				break;

			case 'BILLING.SUBSCRIPTION.PAYMENT.FAILED':
				$this->handle_subscription_payment_failed($resource);
				break;

			// Payment events
			case 'PAYMENT.SALE.COMPLETED':
				$this->handle_payment_completed($resource);
				break;

			case 'PAYMENT.CAPTURE.COMPLETED':
				$this->handle_capture_completed($resource);
				break;

			case 'PAYMENT.CAPTURE.REFUNDED':
				$this->handle_capture_refunded($resource);
				break;

			default:
				$this->log(sprintf('Unhandled webhook event: %s', $event_type));
				break;
		}

		status_header(200);
		exit;
	}

	/**
	 * Verify the webhook signature.
	 *
	 * PayPal REST webhooks use RSA-SHA256 signatures for verification.
	 *
	 * @since 2.0.0
	 *
	 * @param string $raw_body The raw request body.
	 * @return bool
	 */
	protected function verify_webhook_signature(string $raw_body): bool {

		// Get webhook headers - these come from PayPal's webhook signature
		$auth_algo         = isset($_SERVER['HTTP_PAYPAL_AUTH_ALGO']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_PAYPAL_AUTH_ALGO'])) : '';
		$cert_url          = isset($_SERVER['HTTP_PAYPAL_CERT_URL']) ? sanitize_url(wp_unslash($_SERVER['HTTP_PAYPAL_CERT_URL'])) : '';
		$transmission_id   = isset($_SERVER['HTTP_PAYPAL_TRANSMISSION_ID']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_PAYPAL_TRANSMISSION_ID'])) : '';
		$transmission_sig  = isset($_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_PAYPAL_TRANSMISSION_SIG'])) : '';
		$transmission_time = isset($_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_PAYPAL_TRANSMISSION_TIME'])) : '';

		// If headers are missing, we can't verify
		if (empty($auth_algo) || empty($cert_url) || empty($transmission_id) || empty($transmission_sig) || empty($transmission_time)) {
			$this->log('Missing webhook signature headers', LogLevel::WARNING);

			/**
			 * Filters whether to skip webhook signature verification.
			 *
			 * Only use this for local development or testing environments.
			 *
			 * @since 2.0.0
			 *
			 * @param bool $skip Whether to skip verification. Default false.
			 */
			if (apply_filters('wu_paypal_skip_webhook_verification', false)) {
				$this->log('Skipping signature verification via filter');
				return true;
			}

			return false;
		}

		// Get webhook ID from settings
		$mode_prefix = $this->test_mode ? 'sandbox' : 'live';
		$webhook_id  = wu_get_setting("paypal_rest_{$mode_prefix}_webhook_id", '');

		if (empty($webhook_id)) {
			$this->log('Webhook ID not configured, cannot verify signature', LogLevel::WARNING);
			return false;
		}

		// Get access token for verification API call
		$gateway = wu_get_gateway('paypal-rest');
		if (! $gateway) {
			$this->log('PayPal REST gateway not available', LogLevel::ERROR);
			return false;
		}

		// Build verification request
		$verify_data = [
			'auth_algo'         => $auth_algo,
			'cert_url'          => $cert_url,
			'transmission_id'   => $transmission_id,
			'transmission_sig'  => $transmission_sig,
			'transmission_time' => $transmission_time,
			'webhook_id'        => $webhook_id,
			'webhook_event'     => json_decode($raw_body, true),
		];

		// Get client credentials
		$client_id     = wu_get_setting("paypal_rest_{$mode_prefix}_client_id", '');
		$client_secret = wu_get_setting("paypal_rest_{$mode_prefix}_client_secret", '');

		if (empty($client_id) || empty($client_secret)) {
			$this->log('Client credentials not configured for webhook verification', LogLevel::WARNING);
			return false;
		}

		// Get access token
		$token_response = wp_remote_post(
			$this->get_api_base_url() . '/v1/oauth2/token',
			[
				'headers' => [
					'Authorization' => 'Basic ' . base64_encode($client_id . ':' . $client_secret), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for PayPal API Basic auth
					'Content-Type'  => 'application/x-www-form-urlencoded',
				],
				'body'    => 'grant_type=client_credentials',
				'timeout' => 30,
			]
		);

		if (is_wp_error($token_response)) {
			$this->log('Failed to get token for webhook verification: ' . $token_response->get_error_message(), LogLevel::ERROR);
			return false;
		}

		$token_body   = json_decode(wp_remote_retrieve_body($token_response), true);
		$access_token = $token_body['access_token'] ?? '';

		if (empty($access_token)) {
			$this->log('Failed to get access token for webhook verification', LogLevel::ERROR);
			return false;
		}

		// Call verification endpoint
		$verify_response = wp_remote_post(
			$this->get_api_base_url() . '/v1/notifications/verify-webhook-signature',
			[
				'headers' => [
					'Authorization' => 'Bearer ' . $access_token,
					'Content-Type'  => 'application/json',
				],
				'body'    => wp_json_encode($verify_data),
				'timeout' => 30,
			]
		);

		if (is_wp_error($verify_response)) {
			$this->log('Webhook verification request failed: ' . $verify_response->get_error_message(), LogLevel::ERROR);
			return false;
		}

		$verify_body = json_decode(wp_remote_retrieve_body($verify_response), true);

		if (($verify_body['verification_status'] ?? '') === 'SUCCESS') {
			$this->log('Webhook signature verified successfully');
			return true;
		}

		$this->log(sprintf('Webhook signature verification returned: %s', $verify_body['verification_status'] ?? 'unknown'), LogLevel::WARNING);

		return false;
	}

	/**
	 * Handle subscription created event.
	 *
	 * @since 2.0.0
	 *
	 * @param array $event_data The subscription event data.
	 * @return void
	 */
	protected function handle_subscription_created(array $event_data): void {

		$subscription_id = $event_data['id'] ?? '';
		$this->log(sprintf('Subscription created: %s', $subscription_id));

		// Subscription created is usually handled during checkout flow
		// This webhook is mainly for logging/verification
	}

	/**
	 * Handle subscription activated event.
	 *
	 * @since 2.0.0
	 *
	 * @param array $event_data The subscription event data.
	 * @return void
	 */
	protected function handle_subscription_activated(array $event_data): void {

		$subscription_id = $event_data['id'] ?? '';

		$membership = $this->get_membership_by_subscription($subscription_id);

		if (! $membership) {
			$this->log(sprintf('Subscription activated but no membership found: %s', $subscription_id), LogLevel::WARNING);
			return;
		}

		// Update membership status if needed — use renew() so wu_membership_post_renew
		// fires and triggers site creation / all post-activation hooks.
		if ($membership->get_status() !== Membership_Status::ACTIVE) {
			$membership->renew(true);

			$this->log(sprintf('Membership %d activated via webhook', $membership->get_id()));
		}
	}

	/**
	 * Handle subscription updated event.
	 *
	 * @since 2.0.0
	 *
	 * @param array $event_data The subscription event data.
	 * @return void
	 */
	protected function handle_subscription_updated(array $event_data): void {

		$subscription_id = $event_data['id'] ?? '';
		$this->log(sprintf('Subscription updated: %s', $subscription_id));

		// Handle any subscription updates as needed
	}

	/**
	 * Handle subscription cancelled event.
	 *
	 * @since 2.0.0
	 *
	 * @param array $event_data The subscription event data.
	 * @return void
	 */
	protected function handle_subscription_cancelled(array $event_data): void {

		$subscription_id = $event_data['id'] ?? '';

		$membership = $this->get_membership_by_subscription($subscription_id);

		if (! $membership) {
			$this->log(sprintf('Subscription cancelled but no membership found: %s', $subscription_id), LogLevel::WARNING);
			return;
		}

		// Cancel at end of period
		$membership->set_auto_renew(false);
		$membership->save();

		$this->log(sprintf('Membership %d set to not auto-renew after cancellation', $membership->get_id()));
	}

	/**
	 * Handle subscription suspended event.
	 *
	 * @since 2.0.0
	 *
	 * @param array $event_data The subscription event data.
	 * @return void
	 */
	protected function handle_subscription_suspended(array $event_data): void {

		$subscription_id = $event_data['id'] ?? '';

		$membership = $this->get_membership_by_subscription($subscription_id);

		if (! $membership) {
			$this->log(sprintf('Subscription suspended but no membership found: %s', $subscription_id), LogLevel::WARNING);
			return;
		}

		$membership->set_status(Membership_Status::ON_HOLD);
		$membership->save();

		$this->log(sprintf('Membership %d suspended via webhook', $membership->get_id()));
	}

	/**
	 * Handle subscription payment failed event.
	 *
	 * @since 2.0.0
	 *
	 * @param array $event_data The subscription event data.
	 * @return void
	 */
	protected function handle_subscription_payment_failed(array $event_data): void {

		$subscription_id = $event_data['id'] ?? '';

		$membership = $this->get_membership_by_subscription($subscription_id);

		if (! $membership) {
			$this->log(sprintf('Subscription payment failed but no membership found: %s', $subscription_id), LogLevel::WARNING);
			return;
		}

		$this->log(sprintf('Payment failed for membership %d, subscription %s', $membership->get_id(), $subscription_id));

		// Optionally record a failed payment
		// The membership status might be updated by PayPal's retry logic
	}

	/**
	 * Handle payment sale completed event.
	 *
	 * This is triggered for subscription payments.
	 *
	 * @since 2.0.0
	 *
	 * @param array $event_data The sale event data.
	 * @return void
	 */
	protected function handle_payment_completed(array $event_data): void {

		$sale_id    = $event_data['id'] ?? '';
		$billing_id = $event_data['billing_agreement_id'] ?? '';
		$custom_id  = $event_data['custom'] ?? ($event_data['custom_id'] ?? '');
		$amount     = $event_data['amount']['total'] ?? ($event_data['amount']['value'] ?? 0);
		$currency   = $event_data['amount']['currency'] ?? ($event_data['amount']['currency_code'] ?? 'USD');

		$this->log(sprintf('Payment completed: %s, Amount: %s %s', $sale_id, $amount, $currency));

		// Try to find membership by billing agreement (subscription ID)
		$membership = null;
		if (! empty($billing_id)) {
			$membership = $this->get_membership_by_subscription($billing_id);
		}

		// Fallback to custom_id parsing
		if (! $membership && ! empty($custom_id)) {
			$custom_parts = explode('|', $custom_id);
			if (count($custom_parts) >= 2) {
				$membership = wu_get_membership((int) $custom_parts[1]);
			}
		}

		if (! $membership) {
			$this->log(sprintf('Payment completed but no membership found for sale: %s', $sale_id), LogLevel::WARNING);
			return;
		}

		// Check if we already recorded this exact payment
		$existing_payment = wu_get_payment_by('gateway_payment_id', $sale_id);

		if ($existing_payment) {
			$this->log(sprintf('Payment %s already recorded', $sale_id));
			return;
		}

		// Check for the original pending payment created during checkout (first payment only).
		// When a subscription returns APPROVED, we leave the initial payment as PENDING and
		// expect this webhook to confirm it rather than creating a duplicate.
		$pending_payments = wu_get_payments([
			'membership_id' => $membership->get_id(),
			'status'        => Payment_Status::PENDING,
			'number'        => 1,
		]);

		$payment = ! empty($pending_payments) ? $pending_payments[0] : null;

		if ($payment) {
			// Confirm the existing pending payment
			$payment->set_gateway_payment_id($sale_id);
			$payment->set_status(Payment_Status::COMPLETED);
			$payment->save();

			$this->log(sprintf('Confirmed pending payment %d for membership %d', $payment->get_id(), $membership->get_id()));
		} else {
			// No pending payment — create a renewal payment record
			$payment_data = [
				'customer_id'        => $membership->get_customer_id(),
				'membership_id'      => $membership->get_id(),
				'gateway'            => 'paypal-rest',
				'gateway_payment_id' => $sale_id,
				'currency'           => $currency,
				'subtotal'           => (float) $amount,
				'total'              => (float) $amount,
				'status'             => Payment_Status::COMPLETED,
				'product_id'         => $membership->get_plan_id(),
			];

			$payment = wu_create_payment($payment_data);

			if (is_wp_error($payment)) {
				$this->log(sprintf('Failed to create renewal payment: %s', $payment->get_error_message()), LogLevel::ERROR);
				return;
			}

			$this->log(sprintf('Renewal payment created: %d for membership %d', $payment->get_id(), $membership->get_id()));
		}

		// Update membership
		$membership->add_to_times_billed(1);
		$membership->renew(false);
	}

	/**
	 * Handle capture completed event.
	 *
	 * This is triggered for one-time payments.
	 *
	 * @since 2.0.0
	 *
	 * @param array $event_data The capture event data.
	 * @return void
	 */
	protected function handle_capture_completed(array $event_data): void {

		$capture_id = $event_data['id'] ?? '';
		$this->log(sprintf('Capture completed: %s', $capture_id));

		// Capture completed is usually handled during the confirmation flow
		// This webhook is for verification/edge cases
	}

	/**
	 * Handle capture refunded event.
	 *
	 * @since 2.0.0
	 *
	 * @param array $event_data The refund event data.
	 * @return void
	 */
	protected function handle_capture_refunded(array $event_data): void {

		$refund_id  = $event_data['id'] ?? '';
		$capture_id = '';
		$amount     = $event_data['amount']['value'] ?? 0;

		// Find the original capture ID from links
		foreach ($event_data['links'] ?? [] as $link) {
			if ('up' === $link['rel']) {
				// Extract capture ID from the link
				preg_match('/captures\/([A-Z0-9]+)/', $link['href'], $matches);
				$capture_id = $matches[1] ?? '';
				break;
			}
		}

		$this->log(sprintf('Refund processed: %s for capture %s, Amount: %s', $refund_id, $capture_id, $amount));

		if (empty($capture_id)) {
			return;
		}

		// Find the payment
		$payment = wu_get_payment_by('gateway_payment_id', $capture_id);

		if (! $payment) {
			$this->log(sprintf('Refund webhook: payment not found for capture %s', $capture_id), LogLevel::WARNING);
			return;
		}

		// Update payment status if fully refunded
		if ($amount >= $payment->get_total()) {
			$payment->set_status(Payment_Status::REFUND);
			$payment->save();
			$this->log(sprintf('Payment %d marked as refunded', $payment->get_id()));
		}
	}

	/**
	 * Get membership by PayPal subscription ID.
	 *
	 * @since 2.0.0
	 *
	 * @param string $subscription_id The PayPal subscription ID.
	 * @return \WP_Ultimo\Models\Membership|null
	 */
	protected function get_membership_by_subscription(string $subscription_id) {

		if (empty($subscription_id)) {
			return null;
		}

		return wu_get_membership_by('gateway_subscription_id', $subscription_id);
	}

	/**
	 * Log a message.
	 *
	 * @since 2.0.0
	 *
	 * @param string $message The message to log.
	 * @param string $level   Log level.
	 * @return void
	 */
	protected function log(string $message, string $level = 'info'): void {

		wu_log_add('paypal', '[Webhook] ' . $message, $level);
	}
}

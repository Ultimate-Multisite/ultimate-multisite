<?php
/**
 * PayPal REST Gateway.
 *
 * Modern PayPal integration using the REST API with OAuth authentication.
 * Supports PayPal Commerce Platform (PPCP) with "Connect with PayPal" onboarding.
 *
 * @package WP_Ultimo
 * @subpackage Gateways
 * @since 2.0.0
 */

namespace WP_Ultimo\Gateways;

use Psr\Log\LogLevel;
use WP_Ultimo\Gateways\Base_PayPal_Gateway;
use WP_Ultimo\Gateways\PayPal_OAuth_Handler;
use WP_Ultimo\Database\Payments\Payment_Status;
use WP_Ultimo\Database\Memberships\Membership_Status;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * PayPal REST API Gateway
 *
 * @since 2.0.0
 */
class PayPal_REST_Gateway extends Base_PayPal_Gateway {

	/**
	 * Holds the ID of this gateway.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $id = 'paypal-rest';

	/**
	 * Backwards compatibility for the old notify ajax url.
	 *
	 * @since 2.0.0
	 * @var bool|string
	 */
	protected $backwards_compatibility_v1_id = false;

	/**
	 * Client ID for REST API authentication.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $client_id = '';

	/**
	 * Client secret for REST API authentication.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $client_secret = '';

	/**
	 * Merchant ID from OAuth onboarding.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $merchant_id = '';

	/**
	 * Cached access token.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $access_token = '';

	/**
	 * Partner client ID (set when using OAuth/proxy mode).
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $partner_client_id = '';

	/**
	 * Currencies supported by the PayPal REST API.
	 *
	 * @since 2.0.0
	 * @link https://developer.paypal.com/docs/reports/reference/paypal-supported-currencies/
	 */
	protected const SUPPORTED_CURRENCIES = [
		'AUD',
		'BRL',
		'CAD',
		'CNY',
		'CZK',
		'DKK',
		'EUR',
		'HKD',
		'HUF',
		'ILS',
		'JPY',
		'MYR',
		'MXN',
		'TWD',
		'NZD',
		'NOK',
		'PHP',
		'PLN',
		'GBP',
		'SGD',
		'SEK',
		'CHF',
		'THB',
		'USD',
	];

	/**
	 * Initialization code.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {

		$this->test_mode = (bool) (int) wu_get_setting('paypal_rest_sandbox_mode', true);

		$this->load_credentials();
	}

	/**
	 * Load credentials from settings.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	protected function load_credentials(): void {

		$mode_prefix = $this->test_mode ? 'sandbox' : 'live';

		// First check for OAuth-connected merchant
		$this->merchant_id = wu_get_setting("paypal_rest_{$mode_prefix}_merchant_id", '');

		// Load client credentials (either from OAuth or manual entry)
		$this->client_id     = wu_get_setting("paypal_rest_{$mode_prefix}_client_id", '');
		$this->client_secret = wu_get_setting("paypal_rest_{$mode_prefix}_client_secret", '');
	}

	/**
	 * Set the test mode and reload credentials.
	 *
	 * @since 2.0.0
	 *
	 * @param bool $test_mode Whether to use sandbox mode.
	 * @return void
	 */
	public function set_test_mode(bool $test_mode): void {

		$this->test_mode         = $test_mode;
		$this->access_token      = ''; // Clear cached token
		$this->partner_client_id = '';

		$this->load_credentials();
	}

	/**
	 * Adds the necessary hooks for the REST PayPal gateway.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function hooks(): void {

		parent::hooks();

		// Initialize OAuth handler
		PayPal_OAuth_Handler::get_instance()->init();

		// Preserve hidden OAuth connection settings during general settings saves
		add_filter('wu_pre_save_settings', [$this, 'preserve_oauth_settings'], 10, 3);

		// Handle webhook installation after settings save
		add_action('wu_after_save_settings', [$this, 'maybe_install_webhook'], 10, 3);

		// AJAX handler for manual webhook installation
		add_action('wp_ajax_wu_paypal_install_webhook', [$this, 'ajax_install_webhook']);

		// Display OAuth notices
		add_action('admin_notices', [PayPal_OAuth_Handler::get_instance(), 'display_oauth_notices']);

		// Hide PayPal from checkout when currency is not supported
		add_filter('wu_get_active_gateways', [$this, 'maybe_remove_for_unsupported_currency']);

		// Hide PayPal from checkout when merchant cannot receive payments
		add_filter('wu_get_active_gateways', [$this, 'maybe_remove_for_invalid_merchant_status']);

		// Register PayPal checkout scripts (button branding)
		add_action('wu_checkout_scripts', [$this, 'register_scripts']);

		// Display PayPal logo with equal prominence on checkout
		add_filter('wu_gateway_paypal-rest_as_option_title', [$this, 'get_checkout_label_html']);
	}

	/**
	 * Checks if PayPal REST is properly configured.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function is_configured(): bool {

		// Either OAuth connected OR manual credentials
		$has_oauth  = ! empty($this->merchant_id);
		$has_manual = ! empty($this->client_id) && ! empty($this->client_secret);

		return $has_oauth || $has_manual;
	}

	/**
	 * Checks if the current store currency is supported by PayPal.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public static function is_currency_supported(): bool {

		$currency = strtoupper((string) wu_get_setting('currency', 'USD'));

		return in_array($currency, self::SUPPORTED_CURRENCIES, true);
	}

	/**
	 * Removes PayPal from the active gateways list when the store currency is not supported.
	 *
	 * Hooked to 'wu_get_active_gateways'.
	 *
	 * @since 2.0.0
	 * @param array $gateways The registered active gateways.
	 * @return array
	 */
	public function maybe_remove_for_unsupported_currency(array $gateways): array {

		if (! self::is_currency_supported()) {
			unset($gateways['paypal-rest']);
		}

		return $gateways;
	}

	/**
	 * Removes PayPal from the active gateways list when the merchant cannot receive payments.
	 *
	 * PayPal requires that merchants with `payments_receivable=false` or
	 * `email_confirmed=false` are blocked from processing payments until
	 * their account setup is complete.
	 *
	 * Hooked to 'wu_get_active_gateways'.
	 *
	 * @since 2.0.0
	 * @param array $gateways The registered active gateways.
	 * @return array
	 */
	public function maybe_remove_for_invalid_merchant_status(array $gateways): array {

		// Only applies when connected via OAuth
		if (empty($this->merchant_id)) {
			return $gateways;
		}

		$mode_prefix         = $this->test_mode ? 'sandbox' : 'live';
		$payments_receivable = wu_get_setting("paypal_rest_{$mode_prefix}_payments_receivable", true);
		$email_confirmed     = wu_get_setting("paypal_rest_{$mode_prefix}_email_confirmed", true);

		if (! $payments_receivable || ! $email_confirmed) {
			unset($gateways['paypal-rest']);
		}

		return $gateways;
	}

	/**
	 * Returns a branded HTML label for the PayPal option in the checkout gateway selector.
	 *
	 * Hooked to 'wu_gateway_paypal-rest_as_option_title'.
	 *
	 * @since 2.0.0
	 * @param string $title The default title string.
	 * @return string HTML label with the PayPal logo.
	 */
	public function get_checkout_label_html(string $title): string {

		return sprintf(
			'<span style="display:flex;align-items:center;flex:1;min-width:0"><span>%s</span><img src="%s" alt="PayPal" height="20" style="margin-left:auto;max-height:20px;display:block" loading="lazy"></span>',
			esc_html($title),
			esc_url('https://www.paypalobjects.com/webstatic/mktg/Logo/pp-logo-100px.png')
		);
	}

	/**
	 * Returns the connection status for display in settings.
	 *
	 * @since 2.0.0
	 * @return array{connected: bool, message: string, details: array}
	 */
	public function get_connection_status(): array {

		$oauth_handler = PayPal_OAuth_Handler::get_instance();
		$is_sandbox    = $this->test_mode;

		if ($oauth_handler->is_merchant_connected($is_sandbox)) {
			$merchant_details = $oauth_handler->get_merchant_details($is_sandbox);

			return [
				'connected' => true,
				'message'   => __('Connected via PayPal', 'ultimate-multisite'),
				'details'   => [
					'mode'         => $is_sandbox ? 'sandbox' : 'live',
					'merchant_id'  => $merchant_details['merchant_id'],
					'email'        => $merchant_details['merchant_email'],
					'connected_at' => $merchant_details['connection_date'],
					'method'       => 'oauth',
				],
			];
		}

		if (! empty($this->client_id) && ! empty($this->client_secret)) {
			return [
				'connected' => true,
				'message'   => __('Connected via API credentials', 'ultimate-multisite'),
				'details'   => [
					'mode'      => $is_sandbox ? 'sandbox' : 'live',
					'client_id' => substr($this->client_id, 0, 20) . '...',
					'method'    => 'manual',
				],
			];
		}

		return [
			'connected' => false,
			'message'   => __('Not connected', 'ultimate-multisite'),
			'details'   => [
				'mode' => $is_sandbox ? 'sandbox' : 'live',
			],
		];
	}

	/**
	 * Whether to apply platform fees to payments.
	 *
	 * Platform fees only apply when the merchant connected via OAuth
	 * (Partner Referral flow) and has not purchased any addon.
	 * Manual credential users are not charged platform fees.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function should_apply_platform_fee(): bool {

		if (empty($this->merchant_id)) {
			return false;
		}

		$addon_repo = \WP_Ultimo::get_instance()->get_addon_repository();

		return ! $addon_repo->has_addon_purchase();
	}

	/**
	 * Gets the platform fee percentage.
	 *
	 * @since 2.0.0
	 * @return float
	 */
	public function get_platform_fee_percent(): float {

		return 3.0;
	}

	/**
	 * Get partner data (access token and client ID) from the proxy.
	 *
	 * Cached in a transient to avoid calling the proxy on every payment.
	 *
	 * @since 2.0.0
	 * @return array{access_token: string, partner_client_id: string}|\WP_Error
	 */
	protected function get_partner_data() {

		$cache_key = 'wu_paypal_partner_data_' . ($this->test_mode ? 'sandbox' : 'live');
		$cached    = get_site_transient($cache_key);

		if ($cached && ! empty($cached['access_token'])) {
			return $cached;
		}

		$proxy_url = apply_filters('wu_paypal_connect_proxy_url', 'https://ultimatemultisite.com/wp-json/paypal-connect/v1');

		$response = wp_remote_post(
			$proxy_url . '/partner-token',
			[
				'body'    => wp_json_encode(['testMode' => $this->test_mode]),
				'headers' => ['Content-Type' => 'application/json'],
				'timeout' => 15,
			]
		);

		if (is_wp_error($response)) {
			return $response;
		}

		$body = json_decode(wp_remote_retrieve_body($response), true);
		$code = wp_remote_retrieve_response_code($response);

		if (200 !== $code || empty($body['access_token'])) {
			return new \WP_Error(
				'wu_paypal_partner_token_error',
				$body['error'] ?? __('Failed to get partner token from proxy.', 'ultimate-multisite')
			);
		}

		$data = [
			'access_token'      => $body['access_token'],
			'partner_client_id' => $body['partner_client_id'] ?? '',
		];

		$expires_in = (int) ($body['expires_in'] ?? 3300);
		set_site_transient($cache_key, $data, $expires_in);

		return $data;
	}

	/**
	 * Build PayPal-Auth-Assertion JWT header.
	 *
	 * Used to make API calls on behalf of a merchant using the partner's token.
	 *
	 * @see https://developer.paypal.com/docs/api/reference/api-requests/#paypal-auth-assertion
	 *
	 * @since 2.0.0
	 *
	 * @param string $partner_client_id The partner's PayPal client ID.
	 * @param string $merchant_payer_id The merchant's PayPal payer/merchant ID.
	 * @return string The JWT assertion string.
	 */
	protected function build_auth_assertion(string $partner_client_id, string $merchant_payer_id): string {

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for PayPal Auth Assertion JWT
		$header = base64_encode(wp_json_encode(['alg' => 'none']));

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for PayPal Auth Assertion JWT
		$payload = base64_encode(
			wp_json_encode(
				[
					'iss'      => $partner_client_id,
					'payer_id' => $merchant_payer_id,
				]
			)
		);

		return $header . '.' . $payload . '.';
	}

	/**
	 * Create a PayPal order with platform fees via partner credentials.
	 *
	 * Uses the partner's access token and PayPal-Auth-Assertion header
	 * to create an order on behalf of the merchant with a platform fee.
	 *
	 * @since 2.0.0
	 *
	 * @param array  $order_data The order data (without platform fee).
	 * @param string $currency The currency code.
	 * @param float  $total The total amount.
	 * @return array|\WP_Error The PayPal API response or error.
	 */
	protected function create_order_with_platform_fee(array $order_data, string $currency, float $total) {

		$partner_data = $this->get_partner_data();

		if (is_wp_error($partner_data)) {
			$this->log('Platform fee skipped: ' . $partner_data->get_error_message());

			return $partner_data;
		}

		if (empty($partner_data['partner_client_id'])) {
			return new \WP_Error('wu_paypal_no_partner_id', 'Partner client ID not available.');
		}

		// Calculate the platform fee
		$fee_amount = round($total * $this->get_platform_fee_percent() / 100, 2);

		if ($fee_amount < 0.01) {
			return new \WP_Error('wu_paypal_fee_too_small', 'Platform fee amount too small.');
		}

		// Add platform fee to the first purchase unit
		$order_data['purchase_units'][0]['payment_instruction'] = [
			'platform_fees' => [
				[
					'amount' => [
						'currency_code' => $currency,
						'value'         => $this->format_amount($fee_amount, $currency),
					],
				],
			],
		];

		// Build the auth assertion
		$auth_assertion = $this->build_auth_assertion(
			$partner_data['partner_client_id'],
			$this->merchant_id
		);

		// Make the API call with partner credentials
		$response = wp_remote_post(
			$this->get_api_base_url() . '/v2/checkout/orders',
			[
				'headers' => [
					'Authorization'                 => 'Bearer ' . $partner_data['access_token'],
					'Content-Type'                  => 'application/json',
					'PayPal-Auth-Assertion'         => $auth_assertion,
					'PayPal-Partner-Attribution-Id' => $this->bn_code,
				],
				'body'    => wp_json_encode($order_data),
				'timeout' => 30,
			]
		);

		if (is_wp_error($response)) {
			return $response;
		}

		$body = json_decode(wp_remote_retrieve_body($response), true);
		$code = wp_remote_retrieve_response_code($response);

		if ($code < 200 || $code >= 300) {
			$error_msg = $body['message'] ?? __('PayPal API error', 'ultimate-multisite');

			return new \WP_Error('wu_paypal_order_error', $error_msg);
		}

		$this->log(sprintf('Order created with %.2f%% platform fee ($%s)', $this->get_platform_fee_percent(), number_format($fee_amount, 2)));

		return $body;
	}

	/**
	 * Format a monetary amount as a string for the PayPal API.
	 *
	 * PayPal requires zero-decimal currencies (JPY, KRW, etc.) to be sent as
	 * whole integers. Sending "4767.00" for JPY causes INVALID_PARAMETER_VALUE.
	 *
	 * @since 2.0.0
	 *
	 * @param float  $amount   The amount to format.
	 * @param string $currency ISO 4217 currency code.
	 * @return string
	 */
	protected function format_amount(float $amount, string $currency): string {

		// Delegate to the shared check so the list stays in one place.
		$decimals = wu_is_zero_decimal_currency($currency) ? 0 : 2;

		return number_format($amount, $decimals, '.', '');
	}

	/**
	 * Get an access token for API requests.
	 *
	 * @since 2.0.0
	 * @return string|\WP_Error Access token or error.
	 */
	protected function get_access_token() {

		if (! empty($this->access_token)) {
			return $this->access_token;
		}

		$mode_suffix = $this->test_mode ? 'sandbox' : 'live';

		// OAuth mode: use partner token from proxy (cached as array to preserve partner_client_id)
		if (! empty($this->merchant_id) && (empty($this->client_id) || empty($this->client_secret))) {
			$oauth_cache_key = 'wu_paypal_rest_partner_data_' . $mode_suffix;
			$cached_partner  = get_site_transient($oauth_cache_key);

			if ($cached_partner && ! empty($cached_partner['access_token'])) {
				$this->partner_client_id = $cached_partner['partner_client_id'];
				$this->access_token      = $cached_partner['access_token'];

				return $this->access_token;
			}

			$partner_data = $this->get_partner_data();

			if (is_wp_error($partner_data)) {
				return $partner_data;
			}

			$this->partner_client_id = $partner_data['partner_client_id'];
			$this->access_token      = $partner_data['access_token'];

			set_site_transient($oauth_cache_key, $partner_data, 3300);

			return $this->access_token;
		}

		// Manual credentials mode
		$cache_key    = 'wu_paypal_rest_access_token_' . $mode_suffix;
		$cached_token = get_site_transient($cache_key);

		if ($cached_token) {
			$this->access_token = $cached_token;
			return $this->access_token;
		}

		if (empty($this->client_id) || empty($this->client_secret)) {
			return new \WP_Error(
				'wu_paypal_missing_credentials',
				__('PayPal API credentials not configured.', 'ultimate-multisite')
			);
		}

		$response = wp_remote_post(
			$this->get_api_base_url() . '/v1/oauth2/token',
			[
				'headers' => [
					'Authorization' => 'Basic ' . base64_encode($this->client_id . ':' . $this->client_secret), // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required for PayPal API Basic auth
					'Content-Type'  => 'application/x-www-form-urlencoded',
				],
				'body'    => 'grant_type=client_credentials',
				'timeout' => 30,
			]
		);

		if (is_wp_error($response)) {
			$this->log('Failed to get access token: ' . $response->get_error_message(), LogLevel::ERROR);
			return $response;
		}

		$body = json_decode(wp_remote_retrieve_body($response), true);
		$code = wp_remote_retrieve_response_code($response);

		if (200 !== $code || empty($body['access_token'])) {
			$error_msg = $body['error_description'] ?? __('Failed to obtain access token', 'ultimate-multisite');
			$this->log('Failed to get access token: ' . $error_msg, LogLevel::ERROR);
			return new \WP_Error('wu_paypal_token_error', $error_msg);
		}

		// Cache the token (expires_in is in seconds, subtract 5 minutes for safety)
		$expires_in = isset($body['expires_in']) ? (int) $body['expires_in'] - 300 : 3300;
		set_site_transient($cache_key, $body['access_token'], $expires_in);

		$this->access_token = $body['access_token'];

		return $this->access_token;
	}

	/**
	 * Make an API request to PayPal REST API.
	 *
	 * @since 2.0.0
	 *
	 * @param string $endpoint      API endpoint (relative to base URL).
	 * @param array  $data          Request data.
	 * @param string $method        HTTP method.
	 * @param array  $extra_headers Additional HTTP headers to include.
	 * @return array|\WP_Error Response data or error.
	 */
	protected function api_request(string $endpoint, array $data = [], string $method = 'POST', array $extra_headers = []) {

		$access_token = $this->get_access_token();

		if (is_wp_error($access_token)) {
			return $access_token;
		}

		$headers = [
			'Authorization' => 'Bearer ' . $access_token,
			'Content-Type'  => 'application/json',
		];

		$headers = $this->add_partner_attribution_header($headers);

		// In OAuth mode, add PayPal-Auth-Assertion so calls act on behalf of the connected merchant
		if (! empty($this->merchant_id) && ! empty($this->partner_client_id)) {
			$headers['PayPal-Auth-Assertion'] = $this->build_auth_assertion($this->partner_client_id, $this->merchant_id);
		}

		$headers = array_merge($headers, $extra_headers);

		$args = [
			'headers' => $headers,
			'method'  => $method,
			'timeout' => 45,
		];

		if (! empty($data) && in_array($method, ['POST', 'PATCH', 'PUT'], true)) {
			$args['body'] = wp_json_encode($data);
		}

		$url = $this->get_api_base_url() . $endpoint;

		$this->log(sprintf('API Request: %s %s', $method, $endpoint));

		$response = wp_remote_request($url, $args);

		if (is_wp_error($response)) {
			$this->log('API Request failed: ' . $response->get_error_message(), LogLevel::ERROR);
			return $response;
		}

		// Log PayPal-Debug-Id for every response to aid support and review submissions.
		$debug_id = wp_remote_retrieve_header($response, 'paypal-debug-id');
		if ($debug_id) {
			$this->log(sprintf('PayPal-Debug-Id: %s [%s %s]', $debug_id, $method, $endpoint));
		}

		$body = json_decode(wp_remote_retrieve_body($response), true);
		$code = wp_remote_retrieve_response_code($response);

		if ($code >= 400) {
			$error_msg = $body['message'] ?? ($body['error_description'] ?? __('API request failed', 'ultimate-multisite'));
			$this->log(sprintf('API Error (%d): %s', $code, wp_json_encode($body)), LogLevel::ERROR);
			return new \WP_Error(
				'wu_paypal_api_error',
				$error_msg,
				[
					'status'   => $code,
					'response' => $body,
				]
			);
		}

		return $body ?? [];
	}

	/**
	 * Process a checkout.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Payment    $payment    The payment associated with the checkout.
	 * @param \WP_Ultimo\Models\Membership $membership The membership.
	 * @param \WP_Ultimo\Models\Customer   $customer   The customer checking out.
	 * @param \WP_Ultimo\Checkout\Cart     $cart       The cart object.
	 * @param string                       $type       The checkout type.
	 * @return void
	 */
	public function process_checkout($payment, $membership, $customer, $cart, $type): void {

		$should_auto_renew = $cart->should_auto_renew();
		$is_recurring      = $cart->has_recurring();

		if ($should_auto_renew && $is_recurring) {
			$this->create_subscription($payment, $membership, $customer, $cart, $type);
		} else {
			$this->create_order($payment, $membership, $customer, $cart, $type);
		}
	}

	/**
	 * Create a PayPal subscription for recurring payments.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Payment    $payment    The payment.
	 * @param \WP_Ultimo\Models\Membership $membership The membership.
	 * @param \WP_Ultimo\Models\Customer   $customer   The customer.
	 * @param \WP_Ultimo\Checkout\Cart     $cart       The cart object.
	 * @param string                       $type       The checkout type.
	 * @return void
	 */
	protected function create_subscription($payment, $membership, $customer, $cart, $type): void {

		$currency    = $this->get_payment_currency_code($payment);
		$description = $this->get_subscription_description($cart);

		// First, create or get the billing plan
		$plan_id = $this->get_or_create_plan($cart, $currency);

		if (is_wp_error($plan_id)) {
			wp_die(
				esc_html($plan_id->get_error_message()),
				esc_html__('PayPal Error', 'ultimate-multisite'),
				[
					'back_link' => true,
					'response'  => '200',
				]
			);
		}

		// Create the subscription
		$subscription_data = [
			'plan_id'             => $plan_id,
			'subscriber'          => [
				'name'          => [
					'given_name' => $customer->get_display_name(),
				],
				'email_address' => $customer->get_email_address(),
			],
			'application_context' => [
				'brand_name'          => wu_get_setting('company_name', get_network_option(null, 'site_name')),
				'locale'              => str_replace('_', '-', get_locale()),
				'shipping_preference' => 'NO_SHIPPING',
				'user_action'         => 'SUBSCRIBE_NOW',
				'return_url'          => $this->get_confirm_url(),
				'cancel_url'          => $this->get_cancel_url(),
			],
			'custom_id'           => sprintf('%s|%s|%s', $payment->get_id(), $membership->get_id(), $customer->get_id()),
		];

		// Handle initial payment if different from recurring
		$initial_amount   = $payment->get_total();
		$recurring_amount = $cart->get_recurring_total();

		if ($initial_amount > 0 && abs($initial_amount - $recurring_amount) > 0.01) {
			// Add setup fee for the difference
			$setup_fee = $initial_amount - $recurring_amount;
			if ($setup_fee > 0) {
				$subscription_data['plan'] = [
					'payment_preferences' => [
						'setup_fee' => [
							'value'         => $this->format_amount($setup_fee, $currency),
							'currency_code' => $currency,
						],
					],
				];
			}
		}

		// Handle trial periods
		if ($membership->is_trialing()) {
			$trial_end = $membership->get_date_trial_end();
			if ($trial_end) {
				$subscription_data['start_time'] = gmdate('Y-m-d\TH:i:s\Z', strtotime($trial_end));
			}
		}

		/**
		 * Filter subscription data before creating.
		 *
		 * @since 2.0.0
		 *
		 * @param array $subscription_data The subscription data.
		 * @param \WP_Ultimo\Models\Membership $membership The membership.
		 * @param \WP_Ultimo\Checkout\Cart $cart The cart.
		 */
		$subscription_data = apply_filters('wu_paypal_rest_subscription_data', $subscription_data, $membership, $cart);

		$result = $this->api_request('/v1/billing/subscriptions', $subscription_data);

		if (is_wp_error($result)) {
			wp_die(
				esc_html($result->get_error_message()),
				esc_html__('PayPal Error', 'ultimate-multisite'),
				[
					'back_link' => true,
					'response'  => '200',
				]
			);
		}

		// Find approval URL (PayPal returns 'approve' or 'payer-action' depending on context)
		$approval_url = '';
		foreach ($result['links'] ?? [] as $link) {
			if (in_array($link['rel'], ['approve', 'payer-action'], true)) {
				$approval_url = $link['href'];
				break;
			}
		}

		if (empty($approval_url)) {
			wp_die(
				esc_html__('Failed to get PayPal approval URL', 'ultimate-multisite'),
				esc_html__('PayPal Error', 'ultimate-multisite'),
				[
					'back_link' => true,
					'response'  => '200',
				]
			);
		}

		// Store subscription ID for confirmation
		$membership->set_gateway_subscription_id($result['id']);
		$membership->save();

		$this->log(sprintf('Subscription created: %s. Redirecting to approval.', $result['id']));

		// Redirect to PayPal for approval
		wp_redirect($approval_url); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- PayPal external URL
		exit;
	}

	/**
	 * Get the ISO 4217 currency code from a payment object.
	 *
	 * Some legacy payments store the currency symbol (e.g. "$") instead of
	 * the ISO code. Normalise it by falling back to the store setting.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Payment $payment The payment object.
	 * @return string 3-letter uppercase ISO currency code.
	 */
	protected function get_payment_currency_code($payment): string {

		$currency = strtoupper($payment->get_currency());

		// Validate it looks like an ISO 4217 code (3 uppercase ASCII letters).
		if (preg_match('/^[A-Z]{3}$/', $currency)) {
			return $currency;
		}

		// The stored value might be a symbol (e.g. "$") rather than a code.
		// Look up the store currency setting — wu_get_currencies() is keyed by ISO code.
		$store_currency = strtoupper(wu_get_setting('currency_symbol', 'USD'));

		if (preg_match('/^[A-Z]{3}$/', $store_currency) && array_key_exists($store_currency, wu_get_currencies())) {
			return $store_currency;
		}

		// Absolute fallback: find the first supported currency in the store currencies list.
		foreach (array_keys(wu_get_currencies()) as $iso) {
			if (in_array($iso, self::SUPPORTED_CURRENCIES, true)) {
				return $iso;
			}
		}

		return 'USD';
	}

	/**
	 * Get or create a billing plan for the subscription.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Checkout\Cart $cart     The cart object.
	 * @param string                   $currency The currency code.
	 * @return string|\WP_Error Plan ID or error.
	 */
	protected function get_or_create_plan($cart, string $currency) {

		// Generate a unique plan key based on cart contents
		$plan_key = 'wu_paypal_plan_' . md5(
			wp_json_encode(
				[
					'amount'        => $cart->get_recurring_total(),
					'currency'      => $currency,
					'duration'      => $cart->get_duration(),
					'duration_unit' => $cart->get_duration_unit(),
				]
			)
		);

		// Check if we already have this plan
		$existing_plan_id = get_site_option($plan_key);
		if ($existing_plan_id) {
			// Verify the plan still exists
			$plan = $this->api_request('/v1/billing/plans/' . $existing_plan_id, [], 'GET');
			if (! is_wp_error($plan) && isset($plan['id'])) {
				return $plan['id'];
			}
		}

		// First create a product
		$product_name = wu_get_setting('company_name', get_network_option(null, 'site_name')) . ' - ' . $cart->get_cart_descriptor();

		$product_data = [
			'name'        => substr($product_name, 0, 127),
			'description' => substr($this->get_subscription_description($cart), 0, 256),
			'type'        => 'SERVICE',
			'category'    => 'SOFTWARE',
		];

		$product = $this->api_request('/v1/catalogs/products', $product_data);

		if (is_wp_error($product)) {
			return $product;
		}

		// Convert duration unit to PayPal format
		$interval_unit   = strtoupper($cart->get_duration_unit());
		$interval_map    = [
			'DAY'   => 'DAY',
			'WEEK'  => 'WEEK',
			'MONTH' => 'MONTH',
			'YEAR'  => 'YEAR',
		];
		$paypal_interval = $interval_map[ $interval_unit ] ?? 'MONTH';

		// Create the billing plan
		$plan_data = [
			'product_id'          => $product['id'],
			'name'                => substr($product_name, 0, 127),
			'description'         => substr($this->get_subscription_description($cart), 0, 127),
			'billing_cycles'      => [
				[
					'frequency'      => [
						'interval_unit'  => $paypal_interval,
						'interval_count' => $cart->get_duration(),
					],
					'tenure_type'    => 'REGULAR',
					'sequence'       => 1,
					'total_cycles'   => 0, // 0 = unlimited
					'pricing_scheme' => [
						'fixed_price' => [
							'value'         => $this->format_amount($cart->get_recurring_total(), $currency),
							'currency_code' => $currency,
						],
					],
				],
			],
			'payment_preferences' => [
				'auto_bill_outstanding'     => true,
				'payment_failure_threshold' => 3,
			],
		];

		$plan = $this->api_request('/v1/billing/plans', $plan_data);

		if (is_wp_error($plan)) {
			return $plan;
		}

		// Cache the plan ID
		update_site_option($plan_key, $plan['id']);

		$this->log(sprintf('Billing plan created: %s', $plan['id']));

		return $plan['id'];
	}

	/**
	 * Create a PayPal order for one-time payments.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Payment    $payment    The payment.
	 * @param \WP_Ultimo\Models\Membership $membership The membership.
	 * @param \WP_Ultimo\Models\Customer   $customer   The customer.
	 * @param \WP_Ultimo\Checkout\Cart     $cart       The cart object.
	 * @param string                       $type       The checkout type.
	 * @return void
	 */
	protected function create_order($payment, $membership, $customer, $cart, $type): void {

		$currency    = $this->get_payment_currency_code($payment);
		$description = $this->get_subscription_description($cart);

		$purchase_unit = [
			'reference_id' => $payment->get_hash(),
			'description'  => substr($description, 0, 127),
			'custom_id'    => sprintf('%s|%s|%s', $payment->get_id(), $membership->get_id(), $customer->get_id()),
			'amount'       => [
				'currency_code' => $currency,
				'value'         => $this->format_amount($payment->get_total(), $currency),
				'breakdown'     => [
					'item_total' => [
						'currency_code' => $currency,
						'value'         => $this->format_amount($payment->get_subtotal(), $currency),
					],
					'tax_total'  => [
						'currency_code' => $currency,
						'value'         => $this->format_amount($payment->get_tax_total(), $currency),
					],
				],
			],
			'items'        => $this->build_order_items($cart, $currency),
		];

		// Include payee.merchant_id when connected via OAuth so PayPal routes
		// the payment to the correct merchant account (required for PPCP compliance).
		if (! empty($this->merchant_id)) {
			$purchase_unit['payee'] = [
				'merchant_id' => $this->merchant_id,
			];
		}

		$order_data = [
			'intent'         => 'CAPTURE',
			'payment_source' => [
				'paypal' => [
					'experience_context' => [
						'brand_name'          => wu_get_setting('company_name', get_network_option(null, 'site_name')),
						'locale'              => str_replace('_', '-', get_locale()),
						'shipping_preference' => 'NO_SHIPPING',
						'user_action'         => 'PAY_NOW',
						'return_url'          => $this->get_confirm_url(),
						'cancel_url'          => $this->get_cancel_url(),
					],
					'email_address'      => $customer->get_email_address(),
				],
			],
			'purchase_units' => [ $purchase_unit ],
		];

		/**
		 * Filter order data before creating.
		 *
		 * @since 2.0.0
		 *
		 * @param array $order_data The order data.
		 * @param \WP_Ultimo\Models\Payment $payment The payment.
		 * @param \WP_Ultimo\Checkout\Cart $cart The cart.
		 */
		$order_data = apply_filters('wu_paypal_rest_order_data', $order_data, $payment, $cart);

		$result = null;

		// Try creating with platform fee if applicable
		if ($this->should_apply_platform_fee()) {
			$result = $this->create_order_with_platform_fee($order_data, $currency, (float) $payment->get_total());

			if (is_wp_error($result)) {
				$this->log('Platform fee order failed, falling back to standard: ' . $result->get_error_message());
				$result = null;
			}
		}

		// Standard order creation (no platform fee or fallback)
		if (null === $result) {
			$result = $this->api_request('/v2/checkout/orders', $order_data);
		}

		if (is_wp_error($result)) {
			wp_die(
				esc_html($result->get_error_message()),
				esc_html__('PayPal Error', 'ultimate-multisite'),
				[
					'back_link' => true,
					'response'  => '200',
				]
			);
		}

		// Find approval URL (PayPal returns 'approve' or 'payer-action' depending on context)
		$approval_url = '';
		foreach ($result['links'] ?? [] as $link) {
			if (in_array($link['rel'], ['approve', 'payer-action'], true)) {
				$approval_url = $link['href'];
				break;
			}
		}

		if (empty($approval_url)) {
			wp_die(
				esc_html__('Failed to get PayPal approval URL', 'ultimate-multisite'),
				esc_html__('PayPal Error', 'ultimate-multisite'),
				[
					'back_link' => true,
					'response'  => '200',
				]
			);
		}

		// Store order ID for confirmation
		$payment->set_gateway_payment_id($result['id']);
		$payment->save();

		$this->log(sprintf('Order created: %s. Redirecting to approval.', $result['id']));

		// Redirect to PayPal for approval
		wp_redirect($approval_url); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- PayPal external URL
		exit;
	}

	/**
	 * Build order items array for PayPal.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Checkout\Cart $cart     The cart object.
	 * @param string                   $currency The currency code.
	 * @return array
	 */
	protected function build_order_items($cart, string $currency): array {

		$items = [];

		foreach ($cart->get_line_items() as $line_item) {
			$items[] = [
				'name'        => substr($line_item->get_title(), 0, 127),
				'description' => substr($line_item->get_description(), 0, 127) ?: null,
				'unit_amount' => [
					'currency_code' => $currency,
					'value'         => $this->format_amount($line_item->get_unit_price(), $currency),
				],
				'quantity'    => (string) $line_item->get_quantity(),
				'category'    => 'DIGITAL_GOODS',
			];
		}

		return $items;
	}

	/**
	 * Process confirmation after PayPal approval.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function process_confirmation(): void {

		$token           = sanitize_text_field(wu_request('token', ''));
		$subscription_id = sanitize_text_field(wu_request('subscription_id', ''));

		if (! empty($subscription_id)) {
			$this->confirm_subscription($subscription_id);
		} elseif (! empty($token)) {
			$this->confirm_order($token);
		} else {
			wp_die(
				esc_html__('Invalid PayPal confirmation', 'ultimate-multisite'),
				esc_html__('PayPal Error', 'ultimate-multisite'),
				['back_link' => true]
			);
		}
	}

	/**
	 * Confirm a subscription after PayPal approval.
	 *
	 * @since 2.0.0
	 *
	 * @param string $subscription_id The PayPal subscription ID.
	 * @return void
	 */
	protected function confirm_subscription(string $subscription_id): void {

		// Get subscription details
		$subscription = $this->api_request('/v1/billing/subscriptions/' . $subscription_id, [], 'GET');

		if (is_wp_error($subscription)) {
			$this->redirect_with_error($subscription->get_error_message());
		}

		// Parse custom_id to get our IDs
		$custom_parts = explode('|', $subscription['custom_id'] ?? '');
		if (count($custom_parts) !== 3) {
			$this->redirect_with_error(__('Invalid subscription data', 'ultimate-multisite'));
		}

		[$payment_id, $membership_id, $customer_id] = $custom_parts;

		$payment    = wu_get_payment($payment_id);
		$membership = wu_get_membership($membership_id);

		if (! $payment || ! $membership) {
			$this->redirect_with_error(__('Payment or membership not found', 'ultimate-multisite'));
		}

		// Check subscription status
		if ('ACTIVE' === $subscription['status'] || 'APPROVED' === $subscription['status']) {
			// Update membership
			$membership->set_gateway('paypal-rest');
			$membership->set_gateway_subscription_id($subscription_id);
			$membership->set_gateway_customer_id($subscription['subscriber']['payer_id'] ?? '');
			$membership->set_auto_renew(true);

			// Handle based on status
			if ('ACTIVE' === $subscription['status']) {
				// Payment already processed
				$payment->set_status(Payment_Status::COMPLETED);
			} else {
				// First payment being processed — activate membership immediately so the
				// site is created now. Payment will be confirmed via PAYMENT.SALE.COMPLETED webhook.
				$payment->set_status(Payment_Status::PENDING);
			}

			// Always renew regardless of ACTIVE vs APPROVED — this activates the membership
			// and fires wu_membership_post_renew which triggers site creation.
			$membership->renew(false);

			$payment->set_gateway('paypal-rest');
			$payment->save();
			$membership->save();

			$this->log(sprintf('Subscription confirmed: %s, Status: %s', $subscription_id, $subscription['status']));

			$this->payment = $payment;
			wp_safe_redirect($this->get_return_url());
			exit;
		}

		$this->redirect_with_error(
			// translators: %s is the subscription status
			sprintf(__('Subscription not approved. Status: %s', 'ultimate-multisite'), $subscription['status'])
		);
	}

	/**
	 * Confirm an order after PayPal approval.
	 *
	 * @since 2.0.0
	 *
	 * @param string $token The PayPal order token.
	 * @return void
	 */
	protected function confirm_order(string $token): void {

		// Capture the order (Prefer header ensures full response with capture details)
		// Capture the order (Prefer header ensures full response with capture details)
		$capture = $this->api_request('/v2/checkout/orders/' . $token . '/capture', [], 'POST', ['Prefer' => 'return=representation']);

		if (is_wp_error($capture)) {
			$this->redirect_with_error($capture->get_error_message());
		}

		if ('COMPLETED' !== $capture['status']) {
			$this->redirect_with_error(
				// translators: %s is the order status
				sprintf(__('Order not completed. Status: %s', 'ultimate-multisite'), $capture['status'])
			);
		}

		// Parse custom_id
		$purchase_unit = $capture['purchase_units'][0] ?? [];
		$custom_parts  = explode('|', $purchase_unit['payments']['captures'][0]['custom_id'] ?? '');

		if (count($custom_parts) !== 3) {
			$this->redirect_with_error(__('Invalid order data', 'ultimate-multisite'));
		}

		[$payment_id, $membership_id, $customer_id] = $custom_parts;

		$payment    = wu_get_payment($payment_id);
		$membership = wu_get_membership($membership_id);

		if (! $payment || ! $membership) {
			$this->redirect_with_error(__('Payment or membership not found', 'ultimate-multisite'));
		}

		// Get transaction ID from capture
		$transaction_id = $purchase_unit['payments']['captures'][0]['id'] ?? $token;

		// Update payment
		$payment->set_gateway('paypal-rest');
		$payment->set_gateway_payment_id($transaction_id);
		$payment->set_status(Payment_Status::COMPLETED);
		$payment->save();

		// Update membership
		$membership->set_gateway('paypal-rest');
		$membership->set_gateway_customer_id($capture['payer']['payer_id'] ?? '');
		$membership->add_to_times_billed(1);
		$membership->renew(false);

		$this->log(sprintf('Order captured: %s, Transaction: %s', $token, $transaction_id));

		$this->payment = $payment;
		wp_safe_redirect($this->get_return_url());
		exit;
	}

	/**
	 * Process cancellation of a subscription.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Membership $membership The membership.
	 * @param \WP_Ultimo\Models\Customer   $customer   The customer.
	 * @return void
	 */
	public function process_cancellation($membership, $customer): void {

		$subscription_id = $membership->get_gateway_subscription_id();

		if (empty($subscription_id)) {
			return;
		}

		$result = $this->api_request(
			'/v1/billing/subscriptions/' . $subscription_id . '/cancel',
			['reason' => __('Cancelled by user', 'ultimate-multisite')]
		);

		if (is_wp_error($result)) {
			$this->log('Failed to cancel subscription: ' . $result->get_error_message(), LogLevel::ERROR);
			return;
		}

		$this->log(sprintf('Subscription cancelled: %s', $subscription_id));
	}

	/**
	 * Process refund.
	 *
	 * @since 2.0.0
	 *
	 * @param float                        $amount     The amount to refund.
	 * @param \WP_Ultimo\Models\Payment    $payment    The payment.
	 * @param \WP_Ultimo\Models\Membership $membership The membership.
	 * @param \WP_Ultimo\Models\Customer   $customer   The customer.
	 * @return void
	 * @throws \Exception When refund fails.
	 */
	public function process_refund($amount, $payment, $membership, $customer): void {

		$capture_id = $payment->get_gateway_payment_id();

		/*
		 * For subscription payments, the capture ID is not stored on the
		 * payment because PayPal handles the charge internally. Look it up
		 * from the subscription's transaction history.
		 */
		if (empty($capture_id) && $membership) {
			$capture_id = $this->find_capture_id_for_payment($payment, $membership);

			if ($capture_id) {
				$payment->set_gateway_payment_id($capture_id);
				$payment->save();
			}
		}

		if (empty($capture_id)) {
			throw new \Exception(esc_html__('No capture ID found for this payment. PayPal subscription payments require an active subscription to look up the transaction.', 'ultimate-multisite'));
		}

		$refund_data = [];

		// Only include amount for partial refunds
		if ($amount < $payment->get_total()) {
			$refund_data['amount'] = [
				'value'         => $this->format_amount($amount, strtoupper($payment->get_currency())),
				'currency_code' => strtoupper($payment->get_currency()),
			];
		}

		/*
		 * Try the captures endpoint first (for one-time orders), then fall
		 * back to the sale endpoint (for subscription payments). PayPal uses
		 * different transaction types depending on the payment flow.
		 */
		$result = $this->api_request('/v2/payments/captures/' . $capture_id . '/refund', $refund_data);

		if (is_wp_error($result)) {
			// Try the v1 sale refund endpoint as fallback for subscription payments.
			$result = $this->api_request('/v1/payments/sale/' . $capture_id . '/refund', $refund_data);
		}

		if (is_wp_error($result)) {
			throw new \Exception(esc_html($result->get_error_message()));
		}

		$this->log(sprintf('Refund processed: %s for transaction %s', $result['id'] ?? 'unknown', $capture_id));
	}

	/**
	 * Find the PayPal capture ID for a payment by querying the subscription transactions.
	 *
	 * @param \WP_Ultimo\Models\Payment    $payment    The payment.
	 * @param \WP_Ultimo\Models\Membership $membership The membership.
	 * @return string|false The capture ID or false if not found.
	 */
	protected function find_capture_id_for_payment($payment, $membership) {

		$subscription_id = $membership->get_gateway_subscription_id();

		if (empty($subscription_id)) {
			return false;
		}

		$start_time = gmdate('Y-m-d\TH:i:s.000\Z', strtotime($payment->get_date_created()) - 86400);
		$end_time   = gmdate('Y-m-d\TH:i:s.000\Z', strtotime($payment->get_date_created()) + 86400);

		$transactions = $this->api_request(
			'/v1/billing/subscriptions/' . $subscription_id . '/transactions?start_time=' . $start_time . '&end_time=' . $end_time,
			[],
			'GET'
		);

		if (is_wp_error($transactions) || empty($transactions['transactions'])) {
			$this->log('Failed to fetch subscription transactions for refund lookup.', LogLevel::WARNING);

			return false;
		}

		/*
		 * PayPal subscription transactions may have statuses like COMPLETED
		 * or UNCLAIMED (sandbox). The amounts may also be split into a setup
		 * fee and a recurring charge. Try to match by amount first, then fall
		 * back to the largest transaction (typically the setup fee).
		 */
		$payment_total    = (float) $payment->get_total();
		$payment_currency = strtoupper($payment->get_currency());
		$best_match       = null;
		$best_amount      = 0;

		foreach ($transactions['transactions'] as $transaction) {
			$txn_status = $transaction['status'] ?? '';

			// Skip failed or refunded transactions.
			if (in_array($txn_status, ['DECLINED', 'REFUNDED', 'PARTIALLY_REFUNDED'], true)) {
				continue;
			}

			$txn_amount   = (float) ($transaction['amount_with_breakdown']['gross_amount']['value'] ?? 0);
			$txn_currency = strtoupper($transaction['amount_with_breakdown']['gross_amount']['currency_code'] ?? '');

			if ($txn_currency !== $payment_currency) {
				continue;
			}

			// Exact match on amount — best case.
			if (abs($txn_amount - $payment_total) < 0.01) {
				$this->log(sprintf('Found exact capture ID %s for payment %d', $transaction['id'], $payment->get_id()));

				return $transaction['id'];
			}

			// Track the largest transaction as fallback (likely the setup fee).
			if ($txn_amount > $best_amount) {
				$best_amount = $txn_amount;
				$best_match  = $transaction['id'];
			}
		}

		// Fall back to the largest transaction if no exact match.
		if ($best_match) {
			$this->log(sprintf('Found capture ID %s (amount fallback) for payment %d', $best_match, $payment->get_id()));

			return $best_match;
		}

		return false;
	}

	/**
	 * Reflects membership changes on the gateway.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Membership $membership The membership.
	 * @param \WP_Ultimo\Models\Customer   $customer   The customer.
	 * @return bool|\WP_Error
	 */
	public function process_membership_update(&$membership, $customer) {

		$subscription_id = $membership->get_gateway_subscription_id();

		if (empty($subscription_id)) {
			return new \WP_Error(
				'wu_paypal_no_subscription',
				__('No subscription ID found for this membership.', 'ultimate-multisite')
			);
		}

		// Note: PayPal subscription updates are limited
		// For significant changes, may need to cancel and recreate
		$this->log(sprintf('Membership update requested for subscription: %s', $subscription_id));

		return true;
	}

	/**
	 * Registers and enqueues the PayPal checkout scripts for button branding.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_scripts(): void {

		wp_register_script(
			'wu-paypal-rest',
			wu_get_asset('gateways/paypal-rest.js', 'js'),
			['wu-checkout'],
			wu_get_version(),
			true
		);

		wp_enqueue_script('wu-paypal-rest');
	}

	/**
	 * Adds the PayPal REST Gateway settings to the settings screen.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function settings(): void {

		wu_register_settings_field(
			'payment-gateways',
			'paypal_rest_header',
			[
				'title'           => __('PayPal', 'ultimate-multisite'),
				'desc'            => __('Use the settings section below to configure PayPal as a payment method.', 'ultimate-multisite'),
				'type'            => 'header',
				'show_as_submenu' => true,
				'require'         => [
					'active_gateways' => 'paypal-rest',
				],
			]
		);

		// Sandbox mode toggle
		wu_register_settings_field(
			'payment-gateways',
			'paypal_rest_sandbox_mode',
			[
				'title'     => __('PayPal Sandbox Mode', 'ultimate-multisite'),
				'desc'      => __('Toggle this to put PayPal on sandbox mode. This is useful for testing and making sure PayPal is correctly setup to handle your payments.', 'ultimate-multisite'),
				'type'      => 'toggle',
				'default'   => 1,
				'html_attr' => [
					'v-model' => 'paypal_rest_sandbox_mode',
				],
				'require'   => [
					'active_gateways' => 'paypal-rest',
				],
			]
		);

		// Currency support warning — shown when the store currency is not supported by PayPal
		if (! self::is_currency_supported()) {
			wu_register_settings_field(
				'payment-gateways',
				'paypal_rest_currency_notice',
				[
					'title'   => '',
					'type'    => 'html',
					'content' => [$this, 'render_currency_warning'],
					'require' => [
						'active_gateways' => 'paypal-rest',
					],
				]
			);
		}

		$oauth_enabled = PayPal_OAuth_Handler::get_instance()->is_oauth_feature_enabled();

		// PayPal Connect section — only shown when OAuth feature is enabled via proxy
		if ($oauth_enabled) {
			wu_register_settings_field(
				'payment-gateways',
				'paypal_rest_oauth_connection',
				[
					'title'   => __('PayPal Connect (Recommended)', 'ultimate-multisite'),
					'desc'    => __('Connect your PayPal account securely with one click. This provides easier setup and automatic webhook configuration.', 'ultimate-multisite'),
					'type'    => 'html',
					'content' => [$this, 'render_oauth_connection'],
					'require' => [
						'active_gateways' => 'paypal-rest',
					],
				]
			);

			// Advanced: Show Direct API Keys Toggle (only when OAuth is available)
			wu_register_settings_field(
				'payment-gateways',
				'paypal_rest_show_manual_keys',
				[
					'title'     => __('Use Direct API Keys (Advanced)', 'ultimate-multisite'),
					'desc'      => __('Toggle to manually enter API keys instead of using PayPal Connect. Use this for backwards compatibility or advanced configurations.', 'ultimate-multisite'),
					'type'      => 'toggle',
					'default'   => 0,
					'html_attr' => [
						'v-model' => 'paypal_rest_show_manual_keys',
					],
					'require'   => [
						'active_gateways' => 'paypal-rest',
					],
				]
			);
		}

		// Build the require array for manual key fields.
		// When OAuth is enabled, keys are behind the advanced toggle.
		// When OAuth is disabled, keys are shown directly.
		$sandbox_key_require = [
			'active_gateways'          => 'paypal-rest',
			'paypal_rest_sandbox_mode' => 1,
		];

		$live_key_require = [
			'active_gateways'          => 'paypal-rest',
			'paypal_rest_sandbox_mode' => 0,
		];

		if ($oauth_enabled) {
			$sandbox_key_require['paypal_rest_show_manual_keys'] = 1;
			$live_key_require['paypal_rest_show_manual_keys']    = 1;
		}

		// Sandbox Client ID
		wu_register_settings_field(
			'payment-gateways',
			'paypal_rest_sandbox_client_id',
			[
				'title'       => __('Sandbox Client ID', 'ultimate-multisite'),
				'placeholder' => __('e.g. AX7MV...', 'ultimate-multisite'),
				'type'        => 'text',
				'default'     => '',
				'capability'  => 'manage_api_keys',
				'require'     => $sandbox_key_require,
			]
		);

		// Sandbox Client Secret
		wu_register_settings_field(
			'payment-gateways',
			'paypal_rest_sandbox_client_secret',
			[
				'title'       => __('Sandbox Client Secret', 'ultimate-multisite'),
				'placeholder' => __('e.g. EK4jT...', 'ultimate-multisite'),
				'type'        => 'text',
				'default'     => '',
				'capability'  => 'manage_api_keys',
				'require'     => $sandbox_key_require,
			]
		);

		// Live Client ID
		wu_register_settings_field(
			'payment-gateways',
			'paypal_rest_live_client_id',
			[
				'title'       => __('Live Client ID', 'ultimate-multisite'),
				'placeholder' => __('e.g. AX7MV...', 'ultimate-multisite'),
				'type'        => 'text',
				'default'     => '',
				'capability'  => 'manage_api_keys',
				'require'     => $live_key_require,
			]
		);

		// Live Client Secret
		wu_register_settings_field(
			'payment-gateways',
			'paypal_rest_live_client_secret',
			[
				'title'       => __('Live Client Secret', 'ultimate-multisite'),
				'placeholder' => __('e.g. EK4jT...', 'ultimate-multisite'),
				'type'        => 'text',
				'default'     => '',
				'capability'  => 'manage_api_keys',
				'require'     => $live_key_require,
			]
		);

		$webhook_message = sprintf(
			'<span class="wu-p-2 wu-bg-blue-100 wu-text-blue-600 wu-rounded wu-mt-3 wu-mb-0 wu-block wu-text-xs">%s</span>',
			__('Webhooks are automatically configured when you connect your PayPal account or save settings with valid API credentials.', 'ultimate-multisite')
		);

		wu_register_settings_field(
			'payment-gateways',
			'paypal_rest_webhook_url',
			[
				'title'           => __('Webhook Listener URL', 'ultimate-multisite'),
				'desc'            => $webhook_message,
				'tooltip'         => __('This is the URL PayPal should send webhook calls to.', 'ultimate-multisite'),
				'type'            => 'text-display',
				'copy'            => true,
				'display_value'   => $this->get_webhook_listener_url(),
				'wrapper_classes' => '',
				'require'         => [
					'active_gateways'              => 'paypal-rest',
					'paypal_rest_show_manual_keys' => 1,
				],
			]
		);
	}

	/**
	 * Render the PayPal OAuth connection status and button.
	 *
	 * Mirrors the Stripe Connect pattern: shows connected status with disconnect,
	 * or disconnected status with connect button, plus fee notice.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_oauth_connection(): void {

		$oauth        = PayPal_OAuth_Handler::get_instance();
		$is_connected = $oauth->is_merchant_connected($this->test_mode);

		if ($is_connected) {
			$status     = $this->get_connection_status();
			$mode_label = 'sandbox' === ($status['details']['mode'] ?? '')
				? __('Sandbox', 'ultimate-multisite')
				: __('Live', 'ultimate-multisite');
			$identifier = $status['details']['merchant_id']
				?? ($status['details']['email'] ?? ($status['details']['client_id'] ?? ''));

			$mode_prefix         = $this->test_mode ? 'sandbox' : 'live';
			$payments_receivable = (bool) wu_get_setting("paypal_rest_{$mode_prefix}_payments_receivable", true);
			$email_confirmed     = (bool) wu_get_setting("paypal_rest_{$mode_prefix}_email_confirmed", true);

			// Show required PayPal error banners when merchant status is incomplete
			if (! $payments_receivable) {
				printf(
					'<div class="wu-oauth-status-warning wu-p-4 wu-bg-red-50 wu-border wu-border-red-300 wu-rounded wu-mb-3">
						<p class="wu-text-sm wu-text-red-800 wu-m-0">%s</p>
					</div>',
					esc_html__('Attention: You currently cannot receive payments due to restriction on your PayPal account. Please reach out to PayPal Customer Support or connect to https://www.paypal.com for more information.', 'ultimate-multisite')
				);
			} elseif (! $email_confirmed) {
				printf(
					'<div class="wu-oauth-status-warning wu-p-4 wu-bg-red-50 wu-border wu-border-red-300 wu-rounded wu-mb-3">
						<p class="wu-text-sm wu-text-red-800 wu-m-0">%s</p>
					</div>',
					esc_html__('Attention: Please confirm your email address on https://www.paypal.com/businessprofile/settings in order to receive payments! You currently cannot receive payments.', 'ultimate-multisite')
				);
			}

			// Connected state
			printf(
				'<div class="wu-oauth-status wu-connected wu-p-4 wu-bg-green-50 wu-border wu-border-green-200 wu-rounded">
					<div class="wu-flex wu-items-center wu-mb-2">
						<span class="dashicons dashicons-yes-alt wu-text-green-600 wu-mr-2"></span>
						<strong class="wu-text-green-800">%s</strong>
					</div>
					<p class="wu-text-sm wu-text-gray-600 wu-mb-2">%s <code class="wu-bg-white wu-px-2 wu-py-1 wu-rounded">%s</code> (%s)</p>
					<button type="button" class="button wu-mt-2 wu-paypal-disconnect">%s</button>
				</div>',
				esc_html__('Connected via PayPal', 'ultimate-multisite'),
				esc_html__('Merchant ID:', 'ultimate-multisite'),
				esc_html($identifier),
				esc_html($mode_label),
				esc_html__('Disconnect', 'ultimate-multisite')
			);
		} else {

			// Disconnected state - show connect button
			$can_connect = $oauth->is_configured();

			if ($can_connect) {
				printf(
					'<div class="wu-oauth-status wu-disconnected wu-p-4 wu-bg-blue-50 wu-border wu-border-blue-200 wu-rounded">
						<p class="wu-text-sm wu-text-gray-700 wu-mb-3">%s</p>
						<button type="button" class="button button-primary wu-paypal-connect">
							<span class="dashicons dashicons-admin-links wu-mr-1 wu-mt-1"></span>
							%s
						</button>
						<p class="wu-text-xs wu-text-gray-500 wu-mt-2">%s</p>
					</div>',
					esc_html__('Connect your PayPal account with one click. Webhooks will be configured automatically.', 'ultimate-multisite'),
					esc_html__('Connect with PayPal', 'ultimate-multisite'),
					esc_html__('You will be redirected to PayPal to securely authorize the connection.', 'ultimate-multisite')
				);
			} else {
				printf(
					'<div class="wu-oauth-status wu-disconnected wu-p-4 wu-bg-gray-50 wu-border wu-border-gray-200 wu-rounded">
						<p class="wu-text-sm wu-text-gray-600">%s</p>
					</div>',
					esc_html__('Use the Direct API Keys option below to enter your PayPal credentials manually.', 'ultimate-multisite')
				);
			}
		}

		// Enqueue the connect/disconnect scripts
		$this->enqueue_connect_scripts();

		// Fee notice (mirrors Stripe Connect fee notice)
//		if (! \WP_Ultimo::get_instance()->get_addon_repository()->has_addon_purchase()) {
//			printf(
//				'<div class="wu-py-3">%s <br><a href="%s" target="_blank" rel="noopener">%s</a></div>',
//				esc_html(
//					sprintf(
//						/* translators: %s: the fee percentage */
//						__('There is a %s%% fee per-transaction to use the PayPal integration included in the free Ultimate Multisite plugin.', 'ultimate-multisite'),
//						number_format_i18n($this->get_platform_fee_percent(), 0)
//					)
//				),
//				esc_url(network_admin_url('admin.php?page=wp-ultimo-addons')),
//				esc_html__('Remove this fee by purchasing any addon and connecting your store.', 'ultimate-multisite')
//			);
//		} else {
//			printf(
//				'<p class="wu-text-xs wu-text-green-700 wu-mt-2">%s</p>',
//				esc_html__('No application fee — thank you for your support!', 'ultimate-multisite')
//			);
//		}
	}

	/**
	 * Renders an admin notice when the store currency is not supported by PayPal.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function render_currency_warning(): void {

		$currency = strtoupper((string) wu_get_setting('currency', 'USD'));
		$docs_url = 'https://developer.paypal.com/docs/reports/reference/paypal-supported-currencies/';

		printf(
			'<div class="wu-p-3 wu-bg-yellow-50 wu-border wu-border-yellow-300 wu-rounded wu-text-sm wu-mb-4">
				<p class="wu-font-semibold wu-text-yellow-800 wu-mb-1">&#9888; %s</p>
				<p class="wu-text-yellow-700 wu-m-0">%s <a href="%s" target="_blank" rel="noopener noreferrer">%s</a></p>
			</div>',
			esc_html__('Unsupported Currency', 'ultimate-multisite'),
			sprintf(
				/* translators: %s: currency code such as "NGN" */
				esc_html__('Your store currency (%s) is not supported by PayPal. PayPal will be hidden from the checkout until the currency is changed to a supported one.', 'ultimate-multisite'),
				esc_html($currency)
			),
			esc_url($docs_url),
			esc_html__('View supported currencies', 'ultimate-multisite')
		);
	}

	/**
	 * Enqueue the connect/disconnect button scripts.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	protected function enqueue_connect_scripts(): void {

		static $enqueued = false;

		if ($enqueued) {
			return;
		}

		$enqueued = true;

		// Capture values now; wp_kses strips data-* attributes from the button HTML,
		// so we embed nonce and sandbox values directly in the footer script.
		$nonce   = wp_create_nonce('wu_paypal_oauth');
		$sandbox = $this->test_mode ? 1 : 0;

		add_action(
			'admin_footer',
			function () use ($nonce, $sandbox) {
				?>
			<script>
			jQuery(function($) {
				var wuPayPalNonce = <?php echo wp_json_encode($nonce); ?>;
				var wuPayPalSandbox = <?php echo (int) $sandbox; ?>;

				$(".wu-paypal-connect").on("click", function(e) {
					e.preventDefault();
					var $btn = $(this);
					$btn.prop("disabled", true).text(<?php echo wp_json_encode(__('Connecting...', 'ultimate-multisite')); ?>);

					$.post(ajaxurl, {
						action: "wu_paypal_connect",
						nonce: wuPayPalNonce,
						sandbox_mode: wuPayPalSandbox
					}, function(response) {
						if (response.success) {
							window.location.href = response.data.redirect_url;
						} else {
							alert(response.data.message || <?php echo wp_json_encode(__('Connection failed. Please try again.', 'ultimate-multisite')); ?>);
							$btn.prop("disabled", false).html('<span class="dashicons dashicons-admin-links wu-mr-1 wu-mt-1"></span> ' + <?php echo wp_json_encode(__('Connect with PayPal', 'ultimate-multisite')); ?>);
						}
					}).fail(function() {
						alert(<?php echo wp_json_encode(__('Connection failed. Please try again.', 'ultimate-multisite')); ?>);
						$btn.prop("disabled", false).html('<span class="dashicons dashicons-admin-links wu-mr-1 wu-mt-1"></span> ' + <?php echo wp_json_encode(__('Connect with PayPal', 'ultimate-multisite')); ?>);
					});
				});

			$(".wu-paypal-disconnect").on("click", function(e) {
				e.preventDefault();
				if (!confirm(<?php echo wp_json_encode(__('Disconnecting your PayPal account will prevent you from offering PayPal services and products on your website. Do you wish to continue?', 'ultimate-multisite')); ?>)) return;

				var $btn = $(this);
				$btn.prop("disabled", true);

				$.post(ajaxurl, {
					action: "wu_paypal_disconnect",
					nonce: wuPayPalNonce
				}, function(response) {
					window.location.reload();
				});
			});
		});
		</script>
				<?php
			}
		);
	}

	/**
	 * Preserves PayPal OAuth connection settings during a general settings save.
	 *
	 * The settings save mechanism only persists registered fields. OAuth tokens and
	 * merchant connection data are stored separately via wu_save_setting() and must
	 * be carried forward so they are not wiped when unrelated settings are saved.
	 *
	 * @since 2.0.0
	 *
	 * @param array $settings         The settings being saved (built from registered fields only).
	 * @param array $settings_to_save Raw POST data.
	 * @param array $saved_settings   The full settings array before this save.
	 * @return array
	 */
	public function preserve_oauth_settings(array $settings, array $settings_to_save, array $saved_settings): array {

		$oauth_keys = [
			'paypal_rest_sandbox_merchant_id',
			'paypal_rest_sandbox_merchant_email',
			'paypal_rest_sandbox_payments_receivable',
			'paypal_rest_sandbox_email_confirmed',
			'paypal_rest_live_merchant_id',
			'paypal_rest_live_merchant_email',
			'paypal_rest_live_payments_receivable',
			'paypal_rest_live_email_confirmed',
			'paypal_rest_connected',
			'paypal_rest_connection_date',
			'paypal_rest_connection_mode',
		];

		foreach ($oauth_keys as $key) {
			if (array_key_exists($key, $saved_settings)) {
				$settings[ $key ] = $saved_settings[ $key ];
			}
		}

		return $settings;
	}

	/**
	 * @inheritdoc
	 * @since 2.0.0
	 */
	public function supports_payment_polling(): bool {

		return true;
	}

	/**
	 * Verify and complete a pending payment by polling PayPal directly.
	 *
	 * Fallback for environments where webhooks cannot reach the server (e.g. local dev).
	 * Checks the subscription status on PayPal; if ACTIVE, marks the local payment COMPLETED
	 * and stamps the gateway_payment_id from the latest transaction.
	 *
	 * @since 2.0.0
	 *
	 * @param int $payment_id The local payment ID to verify.
	 * @return array{success: bool, message: string, status?: string}
	 */
	public function verify_and_complete_payment(int $payment_id): array {

		$payment = wu_get_payment($payment_id);

		if (! $payment) {
			return [
				'success' => false,
				'message' => __('Payment not found.', 'ultimate-multisite'),
			];
		}

		if ($payment->get_status() === \WP_Ultimo\Database\Payments\Payment_Status::COMPLETED) {
			return [
				'success' => true,
				'message' => __('Payment already completed.', 'ultimate-multisite'),
				'status'  => 'completed',
			];
		}

		$membership = $payment->get_membership();

		if (! $membership) {
			return [
				'success' => false,
				'message' => __('Membership not found.', 'ultimate-multisite'),
				'status'  => $payment->get_status(),
			];
		}

		$subscription_id = $membership->get_gateway_subscription_id();

		if (empty($subscription_id)) {
			return [
				'success' => false,
				'message' => __('No PayPal subscription ID found.', 'ultimate-multisite'),
				'status'  => $payment->get_status(),
			];
		}

		// Ask PayPal for the current subscription status.
		$subscription = $this->api_request('/v1/billing/subscriptions/' . $subscription_id, [], 'GET');

		if (is_wp_error($subscription)) {
			return [
				'success' => false,
				'message' => $subscription->get_error_message(),
				'status'  => $payment->get_status(),
			];
		}

		$status = $subscription['status'] ?? '';

		if ('ACTIVE' !== $status && 'APPROVED' !== $status) {
			return [
				'success' => false,
				'message' => sprintf(
					// translators: %s is the PayPal subscription status.
					__('PayPal subscription status: %s', 'ultimate-multisite'),
					$status
				),
				'status'  => $payment->get_status(),
			];
		}

		if ('APPROVED' === $status) {
			// First payment not yet captured by PayPal — still waiting.
			return [
				'success' => false,
				'message' => __('PayPal subscription approved, waiting for first payment to process.', 'ultimate-multisite'),
				'status'  => 'pending',
			];
		}

		// Subscription is ACTIVE — try to find the transaction ID for the first payment.
		$gateway_payment_id = '';

		$start_time     = gmdate('Y-m-d\TH:i:s\Z', strtotime('-1 day'));
		$end_time       = gmdate('Y-m-d\TH:i:s\Z');
		$transactions   = $this->api_request(
			sprintf('/v1/billing/subscriptions/%s/transactions?start_time=%s&end_time=%s', $subscription_id, $start_time, $end_time),
			[],
			'GET'
		);

		if (! is_wp_error($transactions) && ! empty($transactions['transactions'])) {
			$gateway_payment_id = $transactions['transactions'][0]['id'] ?? '';
		}

		// Mark the pending payment as completed.
		if (! empty($gateway_payment_id)) {
			$payment->set_gateway_payment_id($gateway_payment_id);
		}

		$payment->set_status(\WP_Ultimo\Database\Payments\Payment_Status::COMPLETED);
		$payment->save();

		// Ensure membership customer ID is populated from the subscription if missing.
		if (empty($membership->get_gateway_customer_id())) {
			$payer_id = $subscription['subscriber']['payer_id'] ?? '';
			if (! empty($payer_id)) {
				$membership->set_gateway_customer_id($payer_id);
				$membership->save();
			}
		}

		$this->log(sprintf('Payment %d verified and completed via polling. Subscription: %s', $payment_id, $subscription_id));

		return [
			'success' => true,
			'message' => __('Payment confirmed.', 'ultimate-multisite'),
			'status'  => 'completed',
		];
	}

	/**
	 * Maybe install webhook after settings save.
	 *
	 * Automatically creates a webhook in PayPal when credentials are configured.
	 *
	 * @since 2.0.0
	 *
	 * @param array $settings         The final settings array.
	 * @param array $settings_to_save Settings being updated.
	 * @param array $saved_settings   Original settings.
	 * @return void
	 */
	public function maybe_install_webhook($settings, $settings_to_save, $saved_settings): void {

		$active_gateways = (array) wu_get_isset($settings_to_save, 'active_gateways', []);

		if (! in_array('paypal-rest', $active_gateways, true)) {
			return;
		}

		// Check if settings changed
		$changed_settings = [
			$settings['paypal_rest_sandbox_mode'] ?? '',
			$settings['paypal_rest_sandbox_client_id'] ?? '',
			$settings['paypal_rest_sandbox_client_secret'] ?? '',
			$settings['paypal_rest_live_client_id'] ?? '',
			$settings['paypal_rest_live_client_secret'] ?? '',
		];

		$original_settings = [
			$saved_settings['paypal_rest_sandbox_mode'] ?? '',
			$saved_settings['paypal_rest_sandbox_client_id'] ?? '',
			$saved_settings['paypal_rest_sandbox_client_secret'] ?? '',
			$saved_settings['paypal_rest_live_client_id'] ?? '',
			$saved_settings['paypal_rest_live_client_secret'] ?? '',
		];

		// Only install if settings changed
		if ($changed_settings === $original_settings) {
			return;
		}

		// Reload credentials with new settings
		$this->test_mode = (bool) (int) ($settings['paypal_rest_sandbox_mode'] ?? true);
		$this->load_credentials();

		// Check if we have credentials
		if (! $this->is_configured()) {
			return;
		}

		$this->install_webhook();
	}

	/**
	 * Install webhook in PayPal.
	 *
	 * @since 2.0.0
	 * @return bool|\WP_Error True on success, WP_Error on failure.
	 */
	public function install_webhook() {

		$mode_prefix = $this->test_mode ? 'sandbox' : 'live';

		// Check if we already have a webhook installed
		$existing_webhook_id = wu_get_setting("paypal_rest_{$mode_prefix}_webhook_id", '');

		if (! empty($existing_webhook_id)) {
			// Verify it still exists
			$existing = $this->api_request('/v1/notifications/webhooks/' . $existing_webhook_id, [], 'GET');

			if (! is_wp_error($existing) && ! empty($existing['id'])) {
				$this->log(sprintf('Webhook already exists: %s', $existing_webhook_id));
				return true;
			}

			// Webhook was deleted, clear the setting
			wu_save_setting("paypal_rest_{$mode_prefix}_webhook_id", '');
		}

		$webhook_url = $this->get_webhook_listener_url();

		// Define the events we want to receive
		$event_types = [
			['name' => 'BILLING.SUBSCRIPTION.CREATED'],
			['name' => 'BILLING.SUBSCRIPTION.ACTIVATED'],
			['name' => 'BILLING.SUBSCRIPTION.UPDATED'],
			['name' => 'BILLING.SUBSCRIPTION.CANCELLED'],
			['name' => 'BILLING.SUBSCRIPTION.SUSPENDED'],
			['name' => 'BILLING.SUBSCRIPTION.PAYMENT.FAILED'],
			['name' => 'PAYMENT.SALE.COMPLETED'],
			['name' => 'PAYMENT.CAPTURE.COMPLETED'],
			['name' => 'PAYMENT.CAPTURE.REFUNDED'],
		];

		$webhook_data = [
			'url'         => $webhook_url,
			'event_types' => $event_types,
		];

		$this->log(sprintf('Creating webhook for URL: %s', $webhook_url));

		$result = $this->api_request('/v1/notifications/webhooks', $webhook_data);

		if (is_wp_error($result)) {
			$this->log(sprintf('Failed to create webhook: %s', $result->get_error_message()), LogLevel::ERROR);
			return $result;
		}

		if (empty($result['id'])) {
			$this->log('Webhook created but no ID returned', LogLevel::ERROR);
			return new \WP_Error('wu_paypal_webhook_no_id', __('Webhook created but no ID returned', 'ultimate-multisite'));
		}

		// Save the webhook ID
		wu_save_setting("paypal_rest_{$mode_prefix}_webhook_id", $result['id']);

		$this->log(sprintf('Webhook created successfully: %s', $result['id']));

		return true;
	}

	/**
	 * Check if webhook is installed.
	 *
	 * @since 2.0.0
	 * @return bool|array False if not installed, webhook data if installed.
	 */
	public function has_webhook_installed() {

		$mode_prefix = $this->test_mode ? 'sandbox' : 'live';
		$webhook_id  = wu_get_setting("paypal_rest_{$mode_prefix}_webhook_id", '');

		if (empty($webhook_id)) {
			return false;
		}

		$webhook = $this->api_request('/v1/notifications/webhooks/' . $webhook_id, [], 'GET');

		if (is_wp_error($webhook)) {
			return false;
		}

		return $webhook;
	}

	/**
	 * Delete webhook from PayPal.
	 *
	 * @since 2.0.0
	 * @return bool True on success.
	 */
	public function delete_webhook(): bool {

		$mode_prefix = $this->test_mode ? 'sandbox' : 'live';
		$webhook_id  = wu_get_setting("paypal_rest_{$mode_prefix}_webhook_id", '');

		if (empty($webhook_id)) {
			return true;
		}

		$result = $this->api_request('/v1/notifications/webhooks/' . $webhook_id, [], 'DELETE');

		// Clear the stored webhook ID regardless of result
		wu_save_setting("paypal_rest_{$mode_prefix}_webhook_id", '');

		if (is_wp_error($result)) {
			$this->log(sprintf('Failed to delete webhook: %s', $result->get_error_message()), LogLevel::WARNING);
			return false;
		}

		$this->log(sprintf('Webhook deleted: %s', $webhook_id));

		return true;
	}

	/**
	 * AJAX handler to manually install webhook.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function ajax_install_webhook(): void {

		check_ajax_referer('wu_paypal_webhook', 'nonce');

		if (! current_user_can('manage_network_options')) {
			wp_send_json_error(
				[
					'message' => __('You do not have permission to do this.', 'ultimate-multisite'),
				]
			);
		}

		// Reload credentials to ensure we have the latest
		$this->load_credentials();

		if (! $this->is_configured()) {
			wp_send_json_error(
				[
					'message' => __('PayPal credentials are not configured.', 'ultimate-multisite'),
				]
			);
		}

		$result = $this->install_webhook();

		if (true === $result) {
			wp_send_json_success(
				[
					'message' => __('Webhook configured successfully.', 'ultimate-multisite'),
				]
			);
		} elseif (is_wp_error($result)) {
			wp_send_json_error(
				[
					'message' => $result->get_error_message(),
				]
			);
		} else {
			wp_send_json_error(
				[
					'message' => __('Failed to configure webhook. Please try again.', 'ultimate-multisite'),
				]
			);
		}
	}
}

<?php
/**
 * Base Gateway.
 *
 * Base Gateway class. Should be extended to add new payment gateways.
 *
 * @package WP_Ultimo
 * @subpackage Managers/Site_Manager
 * @since 2.0.0
 */

namespace WP_Ultimo\Gateways;

use Psr\Log\LogLevel;
use Stripe;
use Stripe\Customer;
use Stripe\PaymentMethod;
use Stripe\StripeClient;
use Stripe\Subscription;
use Stripe\WebhookEndpoint;
use WP_Ultimo\Checkout\Cart;
use WP_Ultimo\Exception\Runtime_Exception;
use WP_Ultimo\Models\Membership;
use WP_Ultimo\Database\Memberships\Membership_Status;
use WP_Ultimo\Database\Payments\Payment_Status;
use WP_Ultimo\Checkout\Line_Item;
use WP_Ultimo\Models\Payment;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Base Gateway class. Should be extended to add new payment gateways.
 *
 * @since 2.0.0
 */
class Base_Stripe_Gateway extends Base_Gateway {

	/**
	 * Allow gateways to declare multiple additional ids.
	 *
	 * These ids can be retrieved alongside the main id,
	 * via the method get_all_ids().
	 *
	 * @since 2.0.7
	 * @var array
	 */
	protected $other_ids = ['stripe', 'stripe-checkout'];

	/**
	 * Backwards compatibility for the old notify ajax url.
	 *
	 * @since 2.0.4
	 * @var bool|string
	 */
	protected $backwards_compatibility_v1_id = 'stripe';

	/**
	 * Holds the publishable API key provided by Stripe.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $publishable_key;

	/**
	 * Holds the secret API key provided by Stripe.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $secret_key;

	/**
	 * If we should request the billing address.
	 *
	 * @since 2.2.0
	 * @var bool
	 */
	protected $request_billing_address;

	/**
	 * If we are in test mode.
	 *
	 * @since 2.2.0
	 * @var bool
	 */
	protected $test_mode;

	/**
	 * If Stripe Connect is enabled (OAuth mode).
	 *
	 * @since 2.x.x
	 * @var bool
	 */
	protected $is_connect_enabled = false;

	/**
	 * OAuth access token from Stripe Connect.
	 *
	 * @since 2.x.x
	 * @var string
	 */
	protected $oauth_access_token = '';

	/**
	 * Stripe Connect account ID.
	 *
	 * @since 2.x.x
	 * @var string
	 */
	protected $oauth_account_id = '';

	/**
	 * OAuth error message to display inline on settings page.
	 *
	 * @since 2.x.x
	 * @var string
	 */
	protected string $oauth_error = '';

	/**
	 * Authentication mode: 'direct' or 'oauth'.
	 *
	 * @since 2.x.x
	 * @var string
	 */
	protected $authentication_mode = 'direct';

	/**
	 * Holds the Stripe client instance.
	 *
	 * @since 2.0.0
	 * @var StripeClient
	 */
	protected StripeClient $stripe_client;

	/**
	 * Gets or creates the Stripe client instance.
	 *
	 * For OAuth/Connect mode, sets the Stripe-Account header to direct API calls
	 * to the connected account.
	 *
	 * @return StripeClient
	 * @throws Runtime_Exception Other Error.
	 */
	protected function get_stripe_client(): StripeClient {
		if (! isset($this->stripe_client)) {
			if (empty($this->secret_key)) {
				throw new Runtime_Exception(
					esc_html__('Stripe API key is not configured. Please add your Stripe API keys in the Ultimate Multisite settings.', 'ultimate-multisite')
				);
			}

			$client_config = [
				'api_key' => $this->secret_key,
			];

			// Set Stripe-Account header for Connect mode
			if ($this->is_using_oauth() && ! empty($this->oauth_account_id)) {
				$client_config['stripe_account'] = $this->oauth_account_id;
			}

			$this->stripe_client = new StripeClient($client_config);
		}

		return $this->stripe_client;
	}


	/**
	 * Sets a mock Stripe client for testing purposes.
	 *
	 * @param StripeClient $mock_client Mock Stripe client.
	 * @return void
	 */
	public function set_stripe_client(StripeClient $mock_client): void {
		$this->stripe_client = $mock_client;
	}

	/**
	 * Declares support to subscription amount updates.
	 *
	 * @since 2.1.2
	 * @return true
	 */
	public function supports_amount_update(): bool {

		return true;
	}

	/**
	 * Get things going
	 *
	 * @access public
	 * @since  2.1
	 * @return void
	 */
	public function init(): void {

		$id = wu_replace_dashes($this->get_id());

		$this->request_billing_address = true;

		/**
		 * As the toggle return a string with a int value,
		 * we need to convert this first to int then to bool.
		 */
		$this->test_mode = (bool) (int) wu_get_setting("{$id}_sandbox_mode", true);

		$this->setup_api_keys($id);

		if (method_exists(Stripe\Stripe::class, 'setAppInfo')) {
			Stripe\Stripe::setAppInfo('WordPress Ultimate Multisite', wu_get_version(), esc_url(site_url()));
		}
	}

	/**
	 * Setup api keys for stripe.
	 *
	 * Supports dual authentication: OAuth (preferred) and direct API keys (fallback).
	 *
	 * @since 2.0.7
	 *
	 * @param string $id The gateway stripe id.
	 * @return void
	 */
	public function setup_api_keys($id = false): void {

		$id = $id ?: wu_replace_dashes($this->get_id());

		// Check OAuth tokens first (preferred method)
		if ($this->test_mode) {
			$oauth_token = wu_get_setting("{$id}_test_access_token", '');

			if (! empty($oauth_token)) {
				// Use OAuth mode
				$this->authentication_mode = 'oauth';
				$this->oauth_access_token  = $oauth_token;
				$this->publishable_key     = wu_get_setting("{$id}_test_publishable_key", '');
				$this->secret_key          = $oauth_token;
				$this->oauth_account_id    = wu_get_setting("{$id}_test_account_id", '');
				$this->is_connect_enabled  = true;
			} else {
				// Fallback to direct API keys
				$this->authentication_mode = 'direct';
				$this->publishable_key     = wu_get_setting("{$id}_test_pk_key", '');
				$this->secret_key          = wu_get_setting("{$id}_test_sk_key", '');
			}
		} else {
			$oauth_token = wu_get_setting("{$id}_live_access_token", '');

			if (! empty($oauth_token)) {
				// Use OAuth mode
				$this->authentication_mode = 'oauth';
				$this->oauth_access_token  = $oauth_token;
				$this->publishable_key     = wu_get_setting("{$id}_live_publishable_key", '');
				$this->secret_key          = $oauth_token;
				$this->oauth_account_id    = wu_get_setting("{$id}_live_account_id", '');
				$this->is_connect_enabled  = true;
			} else {
				// Fallback to direct API keys
				$this->authentication_mode = 'direct';
				$this->publishable_key     = wu_get_setting("{$id}_live_pk_key", '');
				$this->secret_key          = wu_get_setting("{$id}_live_sk_key", '');
			}
		}

		if ($this->secret_key && Stripe\Stripe::getApiKey() !== $this->secret_key) {
			Stripe\Stripe::setApiKey($this->secret_key);
		}
	}

	/**
	 * Returns the current authentication mode.
	 *
	 * @since 2.x.x
	 * @return string 'oauth' or 'direct'
	 */
	public function get_authentication_mode(): string {
		return $this->authentication_mode;
	}

	/**
	 * Adds application fee to Stripe payment intent arguments.
	 *
	 * @since 2.4.11
	 *
	 * @param array               $intent_args The payment intent arguments.
	 * @param Base_Stripe_Gateway $gateway The gateway instance.
	 * @return array
	 */
	public function add_application_fee_to_intent(array $intent_args, $gateway): array {

		if (! $gateway->should_apply_application_fee()) {
			return $intent_args;
		}

		if (empty($intent_args['amount']) || $intent_args['amount'] <= 0) {
			return $intent_args;
		}

		$intent_args['application_fee_amount'] = (int) round(
			$intent_args['amount'] * $gateway->get_application_fee_percent() / 100
		);

		return $intent_args;
	}

	/**
	 * Adds application fee to Stripe subscription arguments.
	 *
	 * @since 2.4.11
	 *
	 * @param array               $sub_args The subscription arguments.
	 * @param Base_Stripe_Gateway $gateway The gateway instance.
	 * @return array
	 */
	public function add_application_fee_to_subscription(array $sub_args, $gateway): array {

		if (! $gateway->should_apply_application_fee()) {
			return $sub_args;
		}

		$sub_args['application_fee_percent'] = $gateway->get_application_fee_percent();

		return $sub_args;
	}

	/**
	 * Adds application fee to Stripe Checkout session data.
	 *
	 * Handles both subscription and one-time payment modes.
	 *
	 * @since 2.4.11
	 *
	 * @param array               $data The checkout session arguments.
	 * @param Base_Stripe_Gateway $gateway The gateway instance.
	 * @return array
	 */
	public function add_application_fee_to_checkout(array $data, $gateway): array {

		if (! $gateway->should_apply_application_fee()) {
			return $data;
		}

		if (isset($data['subscription_data'])) {
			// Subscription mode — use percentage.
			$data['subscription_data']['application_fee_percent'] = $gateway->get_application_fee_percent();
		} elseif (! empty($data['line_items'])) {
			// One-time payment mode — calculate from line items total.
			$total = 0;

			foreach ($data['line_items'] as $item) {
				$quantity   = $item['quantity'] ?? 1;
				$unit_price = $item['price_data']['unit_amount'] ?? 0;

				$total += $unit_price * $quantity;
			}

			if ($total > 0) {
				$data['payment_intent_data']['application_fee_amount'] = (int) round(
					$total * $gateway->get_application_fee_percent() / 100
				);
			}
		}

		return $data;
	}

	/**
	 * Get Stripe Connect proxy server URL.
	 *
	 * The proxy server handles OAuth flow and keeps platform credentials secure.
	 * Platform credentials are never exposed in the distributed plugin code.
	 *
	 * @since 2.x.x
	 * @return string
	 */
	protected function get_proxy_url(): string {
		/**
		 * Filter the Stripe Connect proxy URL.
		 *
		 * @param string $url Proxy server URL.
		 */
		return apply_filters(
			'wu_stripe_connect_proxy_url',
			'https://ultimatemultisite.com/wp-json/stripe-connect/v1'
		);
	}

	/**
	 * Get business data for prefilling Stripe Connect form.
	 *
	 * @since 2.x.x
	 * @return array
	 */
	protected function get_business_data(): array {
		return [
			'url'           => get_site_url(),
			'business_name' => get_bloginfo('name'),
			'country'       => 'US', // Could be made dynamic based on site settings
		];
	}

	/**
	 * Generate Stripe Connect OAuth authorization URL via proxy server.
	 *
	 * @since 2.x.x
	 * @param string $state CSRF protection state parameter (unused, kept for compatibility).
	 * @return string
	 */
	public function get_connect_authorization_url(string $state = ''): string {
		$proxy_url  = $this->get_proxy_url();
		$return_url = network_admin_url('admin.php?page=wp-ultimo-settings&tab=payment-gateways');

		// Call proxy to initialize OAuth
		$response = wp_remote_post(
			$proxy_url . '/oauth/init',
			[
				'body'    => wp_json_encode(
					[
						'returnUrl'    => $return_url,
						'businessData' => $this->get_business_data(),
						'testMode'     => $this->test_mode,
					]
				),
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'timeout' => 30,
			]
		);

		if (is_wp_error($response)) {
			$this->oauth_error = __('Could not reach the Stripe Connect service. Please check that your server can make outbound HTTPS requests and try again.', 'ultimate-multisite');

			return '';
		}

		$data = json_decode(wp_remote_retrieve_body($response), true);

		if (empty($data['oauthUrl'])) {
			$this->oauth_error = __('Unable to start the Stripe Connect authorization. Please try again or use direct API keys instead.', 'ultimate-multisite');

			return '';
		}

		// Store state for verification
		update_option('wu_stripe_oauth_state', $data['state'], false);

		return $data['oauthUrl'];
	}

	/**
	 * Get OAuth init URL (triggers OAuth flow when clicked).
	 *
	 * This returns a local URL that will initiate the OAuth flow only when clicked,
	 * avoiding unnecessary HTTP requests to the proxy on every page load.
	 *
	 * @since 2.x.x
	 * @return string
	 */
	protected function get_oauth_init_url(): string {
		return add_query_arg(
			[
				'page'              => 'wp-ultimo-settings',
				'tab'               => 'payment-gateways',
				'stripe_oauth_init' => '1',
				'_wpnonce'          => wp_create_nonce('stripe_oauth_init'),
			],
			network_admin_url('admin.php')
		);
	}

	/**
	 * Get disconnect URL.
	 *
	 * @since 2.x.x
	 * @return string
	 */
	protected function get_disconnect_url(): string {
		return add_query_arg(
			[
				'page'              => 'wp-ultimo-settings',
				'tab'               => 'payment-gateways',
				'stripe_disconnect' => '1',
				'_wpnonce'          => wp_create_nonce('stripe_disconnect'),
			],
			network_admin_url('admin.php')
		);
	}

	/**
	 * Handle OAuth callbacks and disconnects.
	 *
	 * @since 2.x.x
	 * @return void
	 */
	public function handle_oauth_callbacks(): void {
		// Handle OAuth init (user clicked Connect button)
		if (isset($_GET['stripe_oauth_init'], $_GET['_wpnonce']) && isset($_GET['page']) && 'wp-ultimo-settings' === $_GET['page']) {
			if (wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'stripe_oauth_init')) {
				// Now make the proxy call and redirect to OAuth URL
				$oauth_url = $this->get_connect_authorization_url();

				if (! empty($oauth_url)) {
					wp_safe_redirect($oauth_url);
					exit;
				}

				// oauth_error was set by get_connect_authorization_url();
				// fall through so the settings page renders the error inline.
			}
		}

		// Handle OAuth callback from proxy (encrypted code)
		if (isset($_GET['wcs_stripe_code'], $_GET['wcs_stripe_state']) && isset($_GET['page']) && 'wp-ultimo-settings' === $_GET['page']) {
			if (! current_user_can('manage_network')) {
				return;
			}

			$encrypted_code = sanitize_text_field(wp_unslash($_GET['wcs_stripe_code']));
			$state          = sanitize_text_field(wp_unslash($_GET['wcs_stripe_state']));

			// Verify CSRF state
			$expected_state = get_option('wu_stripe_oauth_state');

			if (! $expected_state || ! hash_equals($expected_state, $state)) {
				return;
			}

			// Delete state immediately to prevent replay attacks
			delete_option('wu_stripe_oauth_state');

			$this->exchange_code_for_keys($encrypted_code);
		}

		// Handle disconnect
		if (isset($_GET['stripe_disconnect'], $_GET['_wpnonce']) && isset($_GET['page']) && 'wp-ultimo-settings' === $_GET['page']) {
			if (wp_verify_nonce(sanitize_text_field(wp_unslash($_GET['_wpnonce'])), 'stripe_disconnect')) {
				$this->handle_disconnect();
			}
		}
	}

	/**
	 * Exchange encrypted code for API keys via proxy.
	 *
	 * @since 2.x.x
	 * @param string $encrypted_code Encrypted authorization code from proxy.
	 * @return void
	 */
	protected function exchange_code_for_keys(string $encrypted_code): void {
		$proxy_url = $this->get_proxy_url();

		// Call proxy to exchange code for keys
		$response = wp_remote_post(
			$proxy_url . '/oauth/keys',
			[
				'body'    => wp_json_encode(
					[
						'code'     => $encrypted_code,
						'testMode' => $this->test_mode,
					]
				),
				'headers' => [
					'Content-Type' => 'application/json',
				],
				'timeout' => 30,
			]
		);

		if (is_wp_error($response)) {
			$this->oauth_error = __('Could not reach the Stripe Connect service to complete authorization. Please check your server\'s outbound connectivity and try again.', 'ultimate-multisite');

			return;
		}

		$status_code = wp_remote_retrieve_response_code($response);
		$body        = wp_remote_retrieve_body($response);

		if (200 !== $status_code) {
			$this->oauth_error = __('Stripe Connect authorization was not accepted. The link may have expired — please try connecting again.', 'ultimate-multisite');

			return;
		}

		$data = json_decode($body, true);

		if (empty($data['accountId'])) {
			$this->oauth_error = __('Received an unexpected response while completing Stripe Connect. Please try again or use direct API keys instead.', 'ultimate-multisite');

			return;
		}

		$id = wu_replace_dashes($this->get_id());

		// Save tokens
		if ($this->test_mode) {
			wu_save_setting("{$id}_test_access_token", $data['secretKey']);
			wu_save_setting("{$id}_test_refresh_token", $data['refreshToken'] ?? '');
			wu_save_setting("{$id}_test_account_id", $data['accountId']);
			wu_save_setting("{$id}_test_publishable_key", $data['publishableKey']);
		} else {
			wu_save_setting("{$id}_live_access_token", $data['secretKey']);
			wu_save_setting("{$id}_live_refresh_token", $data['refreshToken'] ?? '');
			wu_save_setting("{$id}_live_account_id", $data['accountId']);
			wu_save_setting("{$id}_live_publishable_key", $data['publishableKey']);
		}

		// Re-initialise API keys with the newly saved OAuth token so that the
		// webhook installation below uses the correct credentials immediately.
		$this->setup_api_keys($id);

		// Install the webhook endpoint on the connected Stripe account right away.
		// The install_webhook() method is normally triggered by wu_after_save_settings,
		// but OAuth connect bypasses that flow, so we call it directly here.
		$this->install_webhook_for_oauth();

		// Redirect back to settings
		$redirect_url = add_query_arg(
			[
				'page'             => 'wp-ultimo-settings',
				'tab'              => 'payment-gateways',
				'stripe_connected' => '1',
			],
			network_admin_url('admin.php')
		);

		wp_safe_redirect($redirect_url);
		exit;
	}

	/**
	 * Install the Stripe webhook endpoint immediately after an OAuth connect.
	 *
	 * The standard install_webhook() method is hooked to wu_after_save_settings and
	 * only fires when direct API keys change. OAuth connect saves tokens directly via
	 * wu_save_setting() and never triggers that hook, so this method provides a
	 * dedicated path to ensure the webhook is registered right after connect.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	protected function install_webhook_for_oauth(): void {

		$webhook_url = $this->get_webhook_listener_url();

		try {
			$existing_webhook = $this->has_webhook_installed();

			if (is_wp_error($existing_webhook)) {
				wu_log_add('stripe', sprintf('install_webhook_for_oauth: could not check existing webhooks — %s', $existing_webhook->get_error_message()));

				return;
			}

			if ($existing_webhook) {
				if ('disabled' === $existing_webhook->status) {
					$this->get_stripe_client()->webhookEndpoints->update(
						$existing_webhook->id,
						['status' => 'enabled']
					);

					wu_log_add('stripe', 'install_webhook_for_oauth: re-enabled existing webhook endpoint.');
				} else {
					wu_log_add('stripe', 'install_webhook_for_oauth: webhook endpoint already exists and is enabled.');
				}

				return;
			}

			$this->get_stripe_client()->webhookEndpoints->create(
				[
					'enabled_events' => ['*'],
					'url'            => $webhook_url,
					'description'    => 'Added by Ultimate Multisite. Required to correctly handle changes in subscription status.',
				]
			);

			wu_log_add('stripe', 'install_webhook_for_oauth: webhook endpoint created successfully.');
		} catch (\Throwable $e) {
			$error_code = $e->getCode();

			if (empty($error_code)) {
				$error_code = method_exists($e, 'getHttpStatus') ? $e->getHttpStatus() : 500;
			}

			// translators: %1$s: HTTP error code, %2$s: error message.
			wu_log_add('stripe', sprintf(__('install_webhook_for_oauth: failed to create webhook endpoint: %1$s, %2$s', 'ultimate-multisite'), $error_code, $e->getMessage()));
		}
	}

	/**
	 * Handle disconnect request.
	 *
	 * @since 2.x.x
	 * @return void
	 */
	protected function handle_disconnect(): void {
		$id = wu_replace_dashes($this->get_id());

		// Optionally notify proxy of disconnect
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
				'blocking' => false, // Don't wait for response
			]
		);

		// Clear OAuth tokens for both test and live
		wu_save_setting("{$id}_test_access_token", '');
		wu_save_setting("{$id}_test_refresh_token", '');
		wu_save_setting("{$id}_test_account_id", '');
		wu_save_setting("{$id}_test_publishable_key", '');

		wu_save_setting("{$id}_live_access_token", '');
		wu_save_setting("{$id}_live_refresh_token", '');
		wu_save_setting("{$id}_live_account_id", '');
		wu_save_setting("{$id}_live_publishable_key", '');

		// Redirect back to settings
		$redirect_url = add_query_arg(
			[
				'page'                => 'wp-ultimo-settings',
				'tab'                 => 'payment-gateways',
				'stripe_disconnected' => '1',
			],
			network_admin_url('admin.php')
		);

		wp_safe_redirect($redirect_url);
		exit;
	}

	/**
	 * Adds additional hooks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function hooks(): void {

		add_action('wu_after_save_settings', [$this, 'install_webhook'], 10, 3);

		add_action('wu_after_save_settings', [$this, 'check_keys_status'], 10, 3);

		add_filter('wu_pre_save_settings', [$this, 'fix_saving_settings'], 10, 3);

		/**
		 * We need to check if we should redirect after instantiate the Currents
		 */
		add_action('init', [$this, 'maybe_redirect_to_portal'], 11);
		add_action('wp', [$this, 'maybe_redirect_to_portal'], 11);

		add_filter('allowed_redirect_hosts', [$this, 'allow_stripe_redirect_host']);

		// Application fee filters for Stripe Connect.
		add_filter('wu_stripe_create_payment_intent_args', [$this, 'add_application_fee_to_intent'], 10, 2);
		add_filter('wu_stripe_create_subscription_args', [$this, 'add_application_fee_to_subscription'], 10, 2);
		add_filter('wu_stripe_checkout_subscription_data', [$this, 'add_application_fee_to_checkout'], 10, 2);
	}

	/**
	 * Allow redirects to Stripe's Connect OAuth domain.
	 *
	 * @since 2.x.x
	 *
	 * @param string[] $hosts An array of allowed host names.
	 * @return string[]
	 */
	public function allow_stripe_redirect_host(array $hosts): array {

		$hosts[] = 'connect.stripe.com';
		$hosts[] = 'dashboard.stripe.com';
		$hosts[] = 'checkout.stripe.com';
		$hosts[] = 'billing.stripe.com';

		return $hosts;
	}

	/**
	 * Returns the Stripe Billing Portal URL for changing payment method.
	 *
	 * @since 2.5.0
	 *
	 * @param \WP_Ultimo\Models\Membership $membership The membership to change payment for.
	 * @return string|null The portal URL, or null if not supported.
	 */
	public function get_change_payment_method_url($membership) {

		$gateway_id = wu_replace_dashes($this->id);

		if ( ! wu_get_setting("{$gateway_id}_enable_portal")) {
			return null;
		}

		$s_subscription_id = $membership->get_gateway_subscription_id();

		if (empty($s_subscription_id)) {
			return null;
		}

		return add_query_arg(
			[
				'wu-stripe-portal' => true,
				'membership'       => $membership->get_hash(),
			],
			home_url()
		);
	}

	/**
	 * Returns payment method display info from the Stripe subscription.
	 *
	 * @since 2.5.0
	 *
	 * @param \WP_Ultimo\Models\Membership $membership The membership.
	 * @return array{brand: string, last4: string}|null Payment method info, or null.
	 */
	public function get_payment_method_display($membership): ?array {

		try {
			$sub_id = $membership->get_gateway_subscription_id();

			if (empty($sub_id)) {
				return null;
			}

			$subscription = $this->get_stripe_client()->subscriptions->retrieve(
				$sub_id,
				['expand' => ['default_payment_method']]
			);

			$pm = $subscription->default_payment_method;

			if ( ! $pm || ! $pm->card) {
				return null;
			}

			return [
				'brand' => ucfirst($pm->card->brand ?? ''),
				'last4' => $pm->card->last4 ?? '',
			];
		} catch (\Throwable $e) {
			return null;
		}
	}

	/**
	 * Maybe redirect to the Stripe Billing Portal.
	 *
	 * @since 2.1.2
	 * @return void
	 */
	public function maybe_redirect_to_portal(): void {

		if ( ! wu_request('wu-stripe-portal')) {
			return;
		}

		$membership = WP_Ultimo()->currents->get_membership();

		if ( ! $membership) {
			return;
		}

		$customer = wu_get_current_customer();

		if ( ! is_super_admin() && (! $customer || $customer->get_id() !== $membership->get_customer_id())) {
			wp_die(esc_html__('You are not allowed to modify this membership.', 'ultimate-multisite'));
		}

		$gateway_id = $membership->get_gateway();

		$gateway = wu_get_gateway($gateway_id);

		$allowed_payment_method_types = apply_filters(
			'wu_stripe_checkout_allowed_payment_method_types',
			[
				'card',
			],
			$gateway
		);

		$customer_id   = $membership->get_customer_id();
		$s_customer_id = $membership->get_gateway_customer_id();

		$stored_redirect = get_user_meta(get_current_user_id(), '_wu_change_payment_redirect', true);

		if ($stored_redirect) {
			delete_user_meta(get_current_user_id(), '_wu_change_payment_redirect');
			$return_url = add_query_arg('updated', 'payment_method', $stored_redirect);
		} else {
			$return_url = remove_query_arg('wu-stripe-portal', wu_get_current_url());
		}

		try {
			// If customer is not set, get from checkout session
			if (empty($s_customer_id)) {
				/**
				 * Filter Stripe Subscription data. Can override success_url or cancel_url.
				 *
				 * @since 2.4.2
				 *
				 * @param array $subscription_data An array of parameters to pass to Stripe.
				 * @param Base_Gateway $gateway The current Stripe Gateway object.
				 */
				$subscription_data = apply_filters(
					'wu_stripe_checkout_subscription_data',
					[
						'payment_method_types'       => $allowed_payment_method_types,
						'mode'                       => 'setup',
						'success_url'                => $return_url,
						'cancel_url'                 => wu_get_current_url(),
						'billing_address_collection' => 'required',
						'client_reference_id'        => $customer_id,
						'customer'                   => $s_customer_id,
					],
					$gateway
				);

				$session       = $this->get_stripe_client()->checkout->sessions->create($subscription_data);
				$s_customer_id = $session->customer;
			}

			$portal_config_id = get_site_option('wu_stripe_portal_config_id');

			if ( ! $portal_config_id) {
				$portal_config = $this->get_stripe_client()->billingPortal->configurations->create(
					[
						'features'         => [
							'invoice_history'       => [
								'enabled' => true,
							],
							'payment_method_update' => [
								'enabled' => true,
							],
							'subscription_cancel'   => [
								'enabled'             => true,
								'mode'                => 'at_period_end',
								'cancellation_reason' => [
									'enabled' => true,
									'options' => [
										'too_expensive',
										'missing_features',
										'switched_service',
										'unused',
										'customer_service',
										'too_complex',
										'other',
									],
								],
							],
						],
						'business_profile' => [
							'headline' => __('Manage your membership payment methods.', 'ultimate-multisite'),
						],
					]
				);

				$portal_config_id = $portal_config->id;

				update_site_option('wu_stripe_portal_config_id', $portal_config_id);
			}

			$subscription_data = [
				'return_url'    => $return_url,
				'customer'      => $s_customer_id,
				'configuration' => $portal_config_id,
			];

			$session = $this->get_stripe_client()->billingPortal->sessions->create($subscription_data);

			wp_safe_redirect($session->url);
			exit;
		} catch (\Throwable $e) {
			wp_die(
				esc_html($e->getMessage()),
				esc_html__('Stripe Error', 'ultimate-multisite'),
				['back_link' => true]
			);
		}
	}

	/**
	 * Allows Gateways to override the gateway title.
	 *
	 * @since 2.0.0
	 *
	 * @return string
	 */
	public function get_public_title() {

		$gateway_id = wu_replace_dashes($this->id);

		return wu_get_setting("{$gateway_id}_public_title", __('Credit Card', 'ultimate-multisite'));
	}

	/**
	 * Adds the Stripe Gateway settings to the settings screen.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function settings(): void {

		$gateway_id = wu_replace_dashes($this->id);

		wu_register_settings_field(
			'payment-gateways',
			"{$gateway_id}_enable_portal",
			[
				'title'      => __('Use Stripe Billing Portal', 'ultimate-multisite'),
				'desc'       => 'Add a link to the Billing Portal in the site actions widget so your customer can change the payment method used in Stripe (additional charges from Stripe could be applied).',
				'type'       => 'toggle',
				'default'    => 0,
				'capability' => 'manage_api_keys',
				'require'    => [
					'active_gateways' => $this->get_id(),
				],
			]
		);
	}

	/**
	 * Checks if we already have a webhook listener installed.
	 *
	 * @since 2.0.0
	 * @return WebhookEndpoint|\WP_Error|false
	 */
	public function has_webhook_installed() {

		try {
			$webhook_url = $this->get_webhook_listener_url();

			$this->setup_api_keys();

			$search_webhook = $this->get_stripe_client()->webhookEndpoints->all(
				[
					'limit' => 100,
				]
			);

			foreach ($search_webhook as $webhook_endpoint) {
				if ($webhook_endpoint->url === $webhook_url) {
					return $webhook_endpoint;
				}
			}
		} catch (\Throwable $e) {
			$error_code = $e->getCode();

			// WP Error did not handle empty error code
			if (empty($error_code)) {
				if (method_exists($e, 'getHttpStatus')) {
					$error_code = $e->getHttpStatus();
				} else {
					$error_code = 500;
				}
			}

			return new \WP_Error($error_code, $e->getMessage());
		}

		return false;
	}

	/**
	 * Fix stripe settings
	 *
	 * @since 2.0.18
	 *
	 * @param array $settings The final settings array being saved, containing ALL options.
	 * @param array $settings_to_save Array containing just the options being updated.
	 * @param array $saved_settings Array containing the original settings.
	 * @return array
	 */
	public function fix_saving_settings($settings, $settings_to_save, $saved_settings) {

		$id = wu_replace_dashes($this->get_id());

		// Always preserve OAuth tokens so they are not wiped when unrelated settings are saved.
		$oauth_keys = [
			"{$id}_test_access_token",
			"{$id}_test_refresh_token",
			"{$id}_test_account_id",
			"{$id}_test_publishable_key",
			"{$id}_live_access_token",
			"{$id}_live_refresh_token",
			"{$id}_live_account_id",
			"{$id}_live_publishable_key",
		];

		foreach ($oauth_keys as $key) {
			if (array_key_exists($key, $saved_settings)) {
				$settings[ $key ] = $saved_settings[ $key ];
			}
		}

		$active_gateways = (array) wu_get_isset($settings_to_save, 'active_gateways', []);

		if ( ! in_array($this->get_id(), $active_gateways, true)) {
			return $settings;
		}

		if ( ! isset($settings_to_save[ "{$id}_sandbox_mode" ])) {
			$settings[ "{$id}_sandbox_mode" ] = false;
		}

		// Unset webhook url to show the get_webhook_listener_url value to customer
		unset($settings[ "{$id}_webhook_listener_explanation" ]);

		return $settings;
	}

	/**
	 * Check stripe API keys
	 *
	 * @since 2.0.18
	 *
	 * @param array $settings The final settings array being saved, containing ALL options.
	 * @param array $settings_to_save Array containing just the options being updated.
	 * @param array $saved_settings Array containing the original settings.
	 * @return void
	 */
	public function check_keys_status($settings, $settings_to_save, $saved_settings): void {

		$id = wu_replace_dashes($this->get_id());

		$active_gateways = (array) wu_get_isset($settings_to_save, 'active_gateways', []);

		if ( ! in_array($this->get_id(), $active_gateways, true)) {
			return;
		}

		$stripe_mode = (bool) (int) $settings[ "{$id}_sandbox_mode" ] ? 'test' : 'live';

		/*
		 * Checked if the Stripe Settings changed, so we can install webhooks.
		 */
		$changed_settings = [
			$settings[ "{$id}_sandbox_mode" ],
			$settings[ "{$id}_{$stripe_mode}_pk_key" ],
			$settings[ "{$id}_{$stripe_mode}_sk_key" ],
		];

		$original_settings = [
			$saved_settings[ "{$id}_sandbox_mode" ],
			$saved_settings[ "{$id}_{$stripe_mode}_pk_key" ],
			$saved_settings[ "{$id}_{$stripe_mode}_sk_key" ],
		];

		if ($changed_settings == $original_settings) { // phpcs:ignore

			return;
		}

		try {
			Stripe\Stripe::setApiKey($settings[ "{$id}_{$stripe_mode}_sk_key" ]);

			Stripe\Token::create(
				[
					'card' => [
						'number'    => '4242424242424242',
						'exp_month' => 7,
						'exp_year'  => 2028,
						'cvc'       => '314',
					],
				]
			);

			wu_save_setting("{$id}_{$stripe_mode}_sk_key_status", '');
		} catch (\Throwable $e) {
			if (str_starts_with($e->getMessage(), 'Invalid API Key provided')) {
				/**
				 *  The secret key is invalid;
				 */
				wu_save_setting("{$id}_{$stripe_mode}_sk_key_status", __('Invalid API Key provided', 'ultimate-multisite'));
			}
		}

		try {
			Stripe\Stripe::setApiKey($settings[ "{$id}_{$stripe_mode}_pk_key" ]);

			Stripe\Token::create(
				[
					'card' => [
						'number'    => '4242424242424242',
						'exp_month' => 7,
						'exp_year'  => 2028,
						'cvc'       => '314',
					],
				]
			);

			wu_save_setting("{$id}_{$stripe_mode}_pk_key_status", '');
		} catch (\Throwable $e) {
			if (str_starts_with($e->getMessage(), 'Invalid API Key provided')) {
				/**
				 *  The public key is invalid;
				 */
				wu_save_setting("{$id}_{$stripe_mode}_pk_key_status", __('Invalid API Key provided', 'ultimate-multisite'));
			}
		}
	}

	/**
	 * Installs webhook urls onto Stripe.
	 *
	 * Ultimate Multisite will call this whenever settings for this api changes.
	 * That being said, it might be a good idea to check if the webhook already exists
	 * before trying to re-create it.
	 *
	 * Return true for success, or a \WP_Error instance in case of failure.
	 *
	 * @since 2.0.0
	 *
	 * @param array $settings The final settings array being saved, containing ALL options.
	 * @param array $settings_to_save Array containing just the options being updated.
	 * @param array $saved_settings Array containing the original settings.
	 * @return void
	 */
	public function install_webhook($settings, $settings_to_save, $saved_settings) {

		$id = wu_replace_dashes($this->get_id());

		$active_gateways = (array) wu_get_isset($settings_to_save, 'active_gateways', []);

		if ( ! in_array($this->get_id(), $active_gateways, true)) {
			return;
		}

		/*
		 * Checked if the Stripe Settings changed, so we can install webhooks.
		 * Include OAuth access tokens so that connecting via Stripe Connect also
		 * triggers webhook installation — previously only direct API key changes
		 * were detected, leaving OAuth-connected sites without a webhook endpoint.
		 */
		$changed_settings = [
			$settings[ "{$id}_sandbox_mode" ],
			$settings[ "{$id}_test_pk_key" ],
			$settings[ "{$id}_test_sk_key" ],
			$settings[ "{$id}_live_pk_key" ],
			$settings[ "{$id}_live_sk_key" ],
			wu_get_isset($settings, "{$id}_test_access_token", ''),
			wu_get_isset($settings, "{$id}_live_access_token", ''),
		];

		$original_settings = [
			$saved_settings[ "{$id}_sandbox_mode" ],
			$saved_settings[ "{$id}_test_pk_key" ],
			$saved_settings[ "{$id}_test_sk_key" ],
			$saved_settings[ "{$id}_live_pk_key" ],
			$saved_settings[ "{$id}_live_sk_key" ],
			wu_get_isset($saved_settings, "{$id}_test_access_token", ''),
			wu_get_isset($saved_settings, "{$id}_live_access_token", ''),
		];

		if ($changed_settings == $original_settings) { // phpcs:ignore

			return;
		}

		$webhook_url = $this->get_webhook_listener_url();

		$existing_webhook = $this->has_webhook_installed();

		if (is_wp_error($existing_webhook)) {
			return;
		}

		$this->setup_api_keys($id);

		try {
			/*
			 * If already exists, checks for status
			 */
			if ($existing_webhook) {
				if ('disabled' === $existing_webhook->status) {
					$status = $this->get_stripe_client()->webhookEndpoints->update(
						$existing_webhook->id,
						[
							'status' => 'enabled',
						]
					);
				}

				return;
			}

			/*
			 * Otherwise, create it.
			 */
			$this->get_stripe_client()->webhookEndpoints->create(
				[
					'enabled_events' => ['*'],
					'url'            => $webhook_url,
					'description'    => 'Added by Ultimate Multisite. Required to correctly handle changes in subscription status.',
				]
			);
		} catch (\Throwable $e) {
			$error_code = $e->getCode();

			// WP Error did not handle empty error code
			if (empty($error_code)) {
				if (method_exists($e, 'getHttpStatus')) {
					$error_code = $e->getHttpStatus();
				} else {
					$error_code = 500;
				}
			}

			// translators: %1$s: HTTP error code, %2$s: error message.
			wu_log_add('stripe', sprintf(__('Failed to add stripe webhook: %1$s, %2$s', 'ultimate-multisite'), $error_code, $e->getMessage()));
		}
	}

	/**
	 * Reflects membership changes on the gateway.
	 *
	 * By default, this method will process tha cancellation of current gateway subscription
	 *
	 * @since 2.1.3
	 *
	 * @param \WP_Ultimo\Models\Membership $membership The membership object.
	 * @param \WP_Ultimo\Models\Customer   $customer   The customer object.
	 * @return bool|\WP_Error true if it's all done or error object if something went wrong.
	 */
	public function process_membership_update(&$membership, $customer) {

		$gateway_subscription_id = $membership->get_gateway_subscription_id();

		if (empty($gateway_subscription_id)) {
			return new \WP_Error('wu_stripe_no_subscription_id', __('Error: No gateway subscription ID found for this membership.', 'ultimate-multisite'));
		}

		$this->setup_api_keys();

		try {
			$subscription = $this->get_stripe_client()->subscriptions->retrieve($gateway_subscription_id);

			/**
			 * Generate a temporary wu payment so we can get the correct line items and amounts.
			 * It's important to note that we should only get recurring payments so we can correctly update the subscription.
			 */
			$temp_payment = wu_membership_create_new_payment($membership, false, true, false);

			$line_items = $temp_payment->get_line_items();

			$recurring_items = [];

			$credits = [];

			$s_coupon = '';

			foreach ($line_items as $line_item) {
				if ($line_item->get_total() < 0) {
					$credits[] = [
						'amount'      => $line_item->get_total(),
						'description' => $line_item->get_title(),
					];

					continue;
				}

				$sub_total = $line_item->get_quantity() * $line_item->get_unit_price();
				$discounts = $line_item->calculate_discounts($sub_total);

				$discounted_subtotal = $sub_total - $discounts;

				// We will probably never enter here but just in case.
				if ($discounted_subtotal < 0) {
					continue;
				}

				$tax_behavior = '';
				$s_tax_rate   = false;

				if ($line_item->is_taxable() && ! empty($line_item->get_tax_rate())) {
					$tax_behavior = $line_item->get_tax_inclusive() ? 'inclusive' : 'exclusive';

					$tax_args = [
						'country'   => $membership->get_billing_address()->billing_country,
						'tax_rate'  => $line_item->get_tax_rate(),
						'type'      => $line_item->get_tax_type(),
						'title'     => $line_item->get_tax_label(),
						'inclusive' => $line_item->get_tax_inclusive(),
					];

					$s_tax_rate = $this->maybe_create_tax_rate($tax_args);
				}

				$s_price = $this->maybe_create_price(
					$line_item->get_title(),
					$discounted_subtotal,
					$membership->get_currency(),
					$line_item->get_quantity(),
					$membership->get_duration(),
					$membership->get_duration_unit(),
					$tax_behavior
				);

				$recurring_item = [
					'price' => $s_price,
				];

				if ($s_tax_rate) {
					$recurring_item['tax_rates'] = [$s_tax_rate];
				}

				$recurring_items[] = $recurring_item;
			}

			if ( ! empty($credits)) {
				if (count($credits) > 1) {
					$credit = [
						'amount'      => array_sum(wp_list_pluck($credits, 'amount')),
						'description' => __('Amount adjustment based on custom deal.', 'ultimate-multisite'),
					];
				} else {
					$credit = $credits[0];
				}

				$s_amount = - round($credit['amount'] * wu_stripe_get_currency_multiplier(strtoupper($membership->get_currency())));

				if ($s_amount >= 1) {
					$currency = strtolower($membership->get_currency());

					$coupon_data = [
						'id'         => sprintf('%s-%s-%s', $s_amount, $currency, 'forever'),
						'name'       => $credit['description'],
						'amount_off' => $s_amount,
						'duration'   => 'forever',
						'currency'   => $currency,
					];

					$s_coupon = $this->get_stripe_coupon($coupon_data);
				}
			}

			$existing_items = array_map(
				fn($item) => [
					'id'      => $item->id,
					'deleted' => true,
				],
				$subscription->items->data
			);

			$update_data = [
				'items'              => array_merge($recurring_items, $existing_items),
				'proration_behavior' => 'none',
			];

			/*
			 * Use 'discounts' array instead of deprecated 'coupon' parameter.
			 * The 'coupon' parameter was removed in newer Stripe API versions.
			 *
			 * @since 2.0.12
			 */
			if ( ! empty($s_coupon)) {
				$update_data['discounts'] = [['coupon' => $s_coupon]];
			}

			$subscription = $this->get_stripe_client()->subscriptions->update($gateway_subscription_id, $update_data);

			if (empty($s_coupon) && ! empty($subscription->discount)) {
				$this->get_stripe_client()->subscriptions->deleteDiscount($gateway_subscription_id);
			}
		} catch (\Throwable $e) {
			return new \WP_Error('wu_stripe_update_error', $e->getMessage());
		}

		return true;
	}

	/**
	 * Run preparations before checkout processing.
	 *
	 * This runs during the checkout form validation
	 * and it is a great chance to do preflight stuff
	 * if the gateway requires it.
	 *
	 * If you return an array here, Ultimo
	 * will append the key => value of that array
	 * as hidden fields to the checkout field,
	 * and those get submitted with the rest of the form.
	 *
	 * As an example, this is how we create payment
	 * intents for Stripe to make the experience more
	 * streamlined.
	 *
	 * @since 2.0.0
	 * @return void|array
	 */
	public function run_preflight() {}
	/**
	 * Get or create Stripe Customer.
	 *
	 * @since 2.0.0
	 *
	 * @param integer $customer_id Ultimate Multisite customer ID.
	 * @param integer $user_id The WordPress user ID.
	 * @param integer $stripe_customer_id The Stripe Customer ID.
	 * @return \Stripe\Customer|\WP_Error
	 */
	public function get_or_create_customer($customer_id = 0, $user_id = 0, $stripe_customer_id = 0) {
		/*
		 * Sets flag to control if we need
		 * to create a new customer or not.
		 */
		$customer_exists = false;

		/*
		 * Use the Ultimate Multisite customer ID to search on the
		 * database for an existing Stripe customer id.
		 */
		if (empty($stripe_customer_id)) {
			$stripe_customer_id = wu_get_customer_gateway_id($customer_id, ['stripe', 'stripe-checkout']);
		}

		/**
		 * Ensure the correct api keys are set
		 */
		$this->setup_api_keys();

		/*
		 * We found a Stripe Customer ID!
		 *
		 * Now we have to use it to try and retrieve a
		 * stripe customer object.
		 */
		if ($stripe_customer_id) {
			try {
				$stripe_customer = $this->get_stripe_client()->customers->retrieve($stripe_customer_id);

				/*
				 * If the customer was deleted, we
				 * cannot use it again...
				 */
				if ( $stripe_customer && (! isset($stripe_customer->deleted) || ! $stripe_customer->deleted)) {
					$customer_exists = true;
				}
			} catch (\Exception $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch

				/**
				 * Silence is golden.
				 */
			}
		}

		/*
		 * No customer found.
		 *
		 * In this scenario, we'll need to create a new one.
		 */
		if (empty($customer_exists)) {
			try {
				/*
				 * Pass the name and email to stripe.
				 */
				$customer_args = [
					'email'   => $this->customer->get_email_address(),
					'name'    => $this->customer->get_display_name(),
					'address' => $this->convert_to_stripe_address($this->customer->get_billing_address()),
				];

				/*
				 * Filters the customer creation arguments.
				 */
				$customer_args = apply_filters('wu_stripe_customer_create_args', $customer_args, $this);

				/*
				 * Finally, try to create it.
				 */
				$stripe_customer = $this->get_stripe_client()->customers->create($customer_args);
			} catch (\Exception $e) {
				$error_code = $e->getCode();

				// WP Error did not handle empty error code
				if (empty($error_code)) {
					if (method_exists($e, 'getHttpStatus')) {
						$error_code = $e->getHttpStatus();
					} else {
						$error_code = 500;
					}
				}

				return new \WP_Error($error_code, $e->getMessage());
			}
		}

		return $stripe_customer;
	}

	/**
	 * Convert our billing address to the format Stripe is expecting.
	 *
	 * @since 2.0.11
	 *
	 * @param \WP_Ultimo\Objects\Billing_Address $billing_address The Ultimate Multisite billing address.
	 * @return array
	 */
	public function convert_to_stripe_address($billing_address) {

		return [
			'city'        => $billing_address->billing_city,
			'country'     => $billing_address->billing_country,
			'line1'       => $billing_address->billing_address_line_1,
			'line2'       => $billing_address->billing_address_line_2,
			'postal_code' => $billing_address->billing_zip_code,
			'state'       => $billing_address->billing_state,
		];
	}

	/**
	 * Returns an array with customer meta data.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	protected function get_customer_metadata() {

		$meta_data = [
			'key'           => $this->membership->get_id(),
			'email'         => $this->customer->get_email_address(),
			'membership_id' => $this->membership->get_id(),
			'customer_id'   => $this->customer->get_id(),
			'payment_id'    => $this->payment->get_id(),
		];

		return $meta_data;
	}

	/**
	 * Process a checkout.
	 *
	 * It takes the data concerning
	 * a new checkout and process it.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Payment    $payment The payment associated with the checkout.
	 * @param \WP_Ultimo\Models\Membership $membership The membership.
	 * @param \WP_Ultimo\Models\Customer   $customer The customer checking out.
	 * @param \WP_Ultimo\Checkout\Cart     $cart The cart object.
	 * @param string                       $type The checkout type. Can be 'new', 'retry', 'upgrade', 'downgrade', 'addon'.
	 *
	 * @throws \Exception When a stripe API error is caught.
	 *
	 * @return void
	 */
	public function process_checkout($payment, $membership, $customer, $cart, $type) {}

	/**
	 * Create a recurring subscription in Stripe.
	 *
	 * @param Membership    $membership The membership.
	 * @param Cart          $cart The cart object.
	 * @param PaymentMethod $payment_method The save payment method on Stripe.
	 * @param Customer      $s_customer The Stripe customer.
	 *
	 * @return Subscription|bool The Stripe subscription object or false if the creation is running in another process.
	 * @throws \Exception Other Error.
	 * @since 2.0.0
	 */
	protected function create_recurring_payment($membership, $cart, $payment_method, $s_customer) {
		/**
		 * First we need to ensure that this process is not running in another place.
		 */
		$internal_key = "wu_stripe_recurring_creation_{$membership->get_id()}";

		$has_transient = get_site_transient($internal_key);

		if ($has_transient) {
			/**
			 * Process already start at another point (webhook or sync call).
			 */
			return false;
		}

		/**
		 * Set transient to avoid multiple calls.
		 */
		set_site_transient($internal_key, true, 120);

		/*
		 * We need to create a cart description that Stripe understands.
		 */
		$stripe_cart = $this->build_stripe_cart($cart);

		/*
		 * The cart creation process might run into
		 * errors, and in that case, it will
		 * return a WP_Error object.
		 */
		if (is_object($stripe_cart) && is_wp_error($stripe_cart)) {
			throw new \Exception(esc_html($stripe_cart->get_error_message()));
		}

		// Otherwise, use the calculated expiration date of the membership, modified to current time instead of 23:59.
		$billing_date = $cart->get_billing_start_date();
		$base_date    = $billing_date ?: $cart->get_billing_next_charge_date();
		$datetime     = \DateTime::createFromFormat('U', $base_date);
		$current_time = getdate();

		$datetime->setTime($current_time['hours'], $current_time['minutes'], $current_time['seconds']);

		$start_date = $datetime->getTimestamp() - HOUR_IN_SECONDS; // Reduce by 60 seconds to account for inaccurate server times.

		if (empty($payment_method)) {
			throw new \Exception(esc_html__('Invalid payment method', 'ultimate-multisite'));
		}

		/*
		 * Subscription arguments for Stripe
		 */
		$sub_args = [
			'customer'               => $s_customer->id,
			'items'                  => array_values($stripe_cart),
			'default_payment_method' => $payment_method->id,
			'proration_behavior'     => 'none',
			'metadata'               => $this->get_customer_metadata(),
		];

		/*
		 * Now determine if we use `trial_end` or `billing_cycle_anchor` to schedule the start of the
		 * subscription.
		 *
		 * If this is an actual trial, then we use `trial_end`.
		 *
		 * Otherwise, billing cycle anchor is preferable because that works with Stripe MRR.
		 * However, the anchor date cannot be further in the future than a normal billing cycle duration.
		 * If that's the case, then we have to use trial end instead.
		 */
		$stripe_max_anchor = $this->get_stripe_max_billing_cycle_anchor($cart->get_duration(), $cart->get_duration_unit(), 'now');

		if ($cart->has_trial() || $start_date > $stripe_max_anchor->getTimestamp()) {
			$sub_args['trial_end'] = $start_date;
		} else {
			$sub_args['billing_cycle_anchor'] = $start_date;
		}

		/*
		 * Sets the billing anchor.
		 */
		$set_anchor = isset($sub_args['billing_cycle_anchor']);

		/**
		 *  If we have a nun recurring discount code we need to add here to use in first payment.
		 */
		if ($cart->has_trial()) {
			/**
			 * If we have pro-rata credit (in case of an upgrade, for example)
			 * try to create a custom coupon.
			 */
			$s_coupon = $this->get_credit_coupon($cart);

			if ($s_coupon) {
				$sub_args['discounts'] = [['coupon' => $s_coupon]];
			}
		}

		/*
		 * Filters the Stripe subscription arguments.
		 */
		$sub_args = apply_filters('wu_stripe_create_subscription_args', $sub_args, $this);

		/*
		 * If we have a `billing_cycle_anchor` AND a `trial_end`, then we need to unset whichever one
		 * we set, and leave the customer's custom one in tact.
		 *
		 * This is done to account for people who filter the arguments to customize the next bill
		 * date. If `trial_end` is used in conjunction with `billing_cycle_anchor` then it will create
		 * unexpected results and the next bill date will not be what they want.
		 *
		 * This may not be completely perfect but it's the best way to try to account for any errors.
		 */
		if ( ! empty($sub_args['trial_end']) && ! empty($sub_args['billing_cycle_anchor'])) {
			/*
			 * If we set an anchor, remove that, because
			 * this means the customer has set their own `trial_end`.
			 */
			if ($set_anchor) {
				unset($sub_args['billing_cycle_anchor']);
			} else {
				/*
				 * We set a trial, which means the customer
				 * has set their own `billing_cycle_anchor`.
				 */
				unset($sub_args['trial_end']);
			}
		}

		$sub_options = apply_filters(
			'wu_stripe_create_subscription_options',
			[
				'idempotency_key' => wu_stripe_generate_idempotency_key($sub_args),
			]
		);

		try {
			/*
			 * Tries to create the subscription
			 * on Stripe!
			 */
			$subscription = $this->get_stripe_client()->subscriptions->create($sub_args, $sub_options);
		} catch (Stripe\Exception\IdempotencyException $exception) {
			/**
			 * In this case, the subscription is being created by another call.
			 */
			return false;
		}

		// If we have a trial we need to add fees to next invoice.
		if ($cart->has_trial()) {
			$currency = strtolower($cart->get_currency());

			$fees = array_filter($cart->get_line_items_by_type('fee'), fn($fee) => ! $fee->is_recurring());

			$s_fees = [];

			foreach ($fees as $fee) {
				$amount = $fee->get_quantity() * $fee->get_unit_price();

				$tax_behavior = '';
				$s_tax_rate   = false;

				if ($fee->is_taxable() && ! empty($fee->get_tax_rate())) {
					$tax_behavior = $fee->get_tax_inclusive() ? 'inclusive' : 'exclusive';

					$tax_args = [
						'country'   => $membership->get_billing_address()->billing_country,
						'tax_rate'  => $fee->get_tax_rate(),
						'type'      => $fee->get_tax_type(),
						'title'     => $fee->get_tax_label(),
						'inclusive' => $fee->get_tax_inclusive(),
					];

					$s_tax_rate = $this->maybe_create_tax_rate($tax_args);
				}

				$s_price = $this->maybe_create_price(
					$fee->get_title(),
					$amount,
					$currency,
					1,
					false,
					false,
					$tax_behavior,
				);

				$s_fee = [
					'price' => $s_price,
				];

				if ($s_tax_rate) {
					$s_fee['tax_rates'] = [$s_tax_rate];
				}

				$s_fees[] = $s_fee;
			}

			if ( ! empty($s_fees)) {
				$options = [
					'add_invoice_items' => $s_fees,
				];

				$sub_options = [
					'idempotency_key' => wu_stripe_generate_idempotency_key(array_merge(['s_subscription' => $subscription->id], $options)),
				];

				try {
					$subscription = $this->get_stripe_client()->subscriptions->update($subscription->id, $options, $sub_options);
				} catch (Stripe\Exception\IdempotencyException $exception) {
					/**
					 * In this case, the subscription is being updated by another call.
					 */
					return false;
				}
			}
		}

		return $subscription;
	}

	/**
	 * Checks if we need to create a pro-rate/credit coupon based on the cart data.
	 *
	 * Will return an array with coupon arguments for stripe if
	 * there is credit to be added and false if not.
	 *
	 * @param \WP_Ultimo\Checkout\Cart $cart The current cart.
	 *
	 * @return string|false
	 * @throws \Exception Exception.
	 * @since 2.0.0
	 */
	protected function get_credit_coupon($cart) {

		$amount = 0;

		foreach ($cart->get_line_items() as $line_item) {
			if ($line_item->get_total() < 0) {
				$amount += $line_item->get_total();
			} elseif ( ! $line_item->should_apply_discount_to_renewals()) {
				$amount += - $line_item->get_discount_total();
			}
		}

		if (empty($amount)) {
			return false;
		}

		$s_amount = - round($amount * wu_stripe_get_currency_multiplier(strtoupper($cart->get_currency())));
		$currency = strtolower($cart->get_currency());

		$coupon_data = [
			'id'         => sprintf('%s-%s-%s', $s_amount, $currency, 'once'),
			'name'       => __('Account credit and other discounts', 'ultimate-multisite'),
			'amount_off' => $s_amount,
			'duration'   => 'once',
			'currency'   => $currency,
		];

		return $this->get_stripe_coupon($coupon_data);
	}

	/**
	 * Checks to see if the coupon exists, and if so, returns the ID of
	 * that coupon. If not, a new coupon is created.
	 *
	 * @param array $coupon_data The cart/order object.
	 *
	 * @return string
	 * @throws \Exception Other Error.
	 * @since 2.0.18
	 */
	protected function get_stripe_coupon($coupon_data) {

		// First check to see if a coupon exists with this ID. If so, return that.
		try {
			$coupon = $this->get_stripe_client()->coupons->retrieve($coupon_data['id']);

			$this->get_stripe_client()->coupons->update(
				$coupon->id,
				[
					'name' => $coupon_data['name'],
				]
			);

			return $coupon->id;
		} catch (\Exception $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch

			// silence is golden
		}

		// Otherwise, create a new plan.
		try {
			$coupon = $this->get_stripe_client()->coupons->create($coupon_data);

			return $coupon->id;
		} catch (\Exception $e) {
			$error_code = $e->getCode();

			// WP Error did not handle empty error code
			if (empty($error_code)) {
				if (method_exists($e, 'getHttpStatus')) {
					$error_code = $e->getHttpStatus();
				} else {
					$error_code = 500;
				}
			}

			wu_log_add('stripe', sprintf('Error creating Stripe coupon. Code: %s; Message: %s', $error_code, $e->getMessage()), LogLevel::ERROR);

			throw $e;
		}
	}
	/**
	 * Builds the non-recurring list of items to be paid on Stripe.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Checkout\Cart $cart The cart/order object.
	 * @param bool                     $include_recurring_products If we should include recurring items as non-recurring.
	 */
	protected function build_non_recurring_cart($cart, $include_recurring_products = false): array {

		$cart_items = [];

		foreach ($cart->get_line_items() as $line_item) {
			/*
			 * Skip recurring items
			 */
			if ($line_item->is_recurring() && false === $include_recurring_products) {
				continue;
			}

			/*
			 * Skip negative items.
			 * In cases like this, we need to generate a coupon code.
			 */
			if ($line_item->get_unit_price() < 0) {
				continue;
			}

			$unit_amount = round($line_item->get_unit_price() * wu_stripe_get_currency_multiplier(strtoupper($cart->get_currency())));

			/*
			 * Skip zero-amount items.
			 * These would cause errors in Stripe Checkout Sessions.
			 */
			if ($unit_amount <= 0) {
				continue;
			}

			$product_data = [
				'name' => $line_item->get_title(),
			];

			$description = $line_item->get_description();

			if ( ! empty($description)) {
				$product_data['description'] = $description;
			}

			$cart_items[ $line_item->get_id() ] = [
				'price_data' => [
					'currency'     => strtolower($cart->get_currency()),
					'unit_amount'  => (int) $unit_amount,
					'product_data' => $product_data,
				],
				'quantity'   => $line_item->get_quantity(),
			];

			/*
			 * Now, we handle the taxable status
			 * of the payment.
			 *
			 * We might need to create tax rates on
			 * Stripe and apply it on the subscription cart.
			 */
			if ($line_item->is_taxable() && ! empty($line_item->get_tax_rate())) {
				$tax_args = [
					'country'   => $this->membership->get_billing_address()->billing_country,
					'tax_rate'  => $line_item->get_tax_rate(),
					'type'      => $line_item->get_tax_type(),
					'title'     => $line_item->get_tax_label(),
					'inclusive' => $line_item->get_tax_inclusive(),
				];

				$cart_items[ $line_item->get_id() ]['tax_rates'] = [$this->maybe_create_tax_rate($tax_args)];
			}
		}

		return array_values($cart_items);
	}

	/**
	 * Converts the Ultimate Multisite cart into Stripe Sub arguments.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Checkout\Cart $cart The cart object.
	 * @return array|\WP_Error
	 */
	protected function build_stripe_cart($cart) {
		/*
		 * Set up a recurring subscription in Stripe with
		 * a delayed start date.
		 *
		 * All start dates are delayed one cycle because we use a
		 * one-time payment for the first charge.
		 */
		$plans = [];

		$all_products = $cart->get_all_products();

		foreach ($cart->get_line_items() as $line_item) {
			$product = $line_item->get_product();

			if ( ! $product) {
				continue;
			}

			/*
			 * Exclude products that are not recurring.
			 */
			if ( ! $product->is_recurring()) {
				continue;
			}

			$amount = $product->get_amount();

			/**
			 * Filters the recurring product amount used to create a Stripe plan.
			 *
			 * Allows addons (e.g. multi-currency) to convert the product
			 * price before the Stripe plan/price is created.
			 *
			 * @since 2.5.0
			 *
			 * @param float                       $amount  The product amount.
			 * @param \WP_Ultimo\Models\Product   $product The product.
			 * @param \WP_Ultimo\Checkout\Cart    $cart    The cart instance.
			 * @return float
			 */
			$amount = apply_filters('wu_stripe_cart_product_amount', $amount, $product, $cart);

			$discount_code = $cart->get_discount_code();

			if ($discount_code) {
				if ($discount_code->should_apply_to_renewals() && $cart->get_cart_type() !== 'renewal') {
					$amount = wu_get_discounted_price($amount, $discount_code->get_value(), $discount_code->get_type(), false);
				}
			}

			try {
				/*
				 * We might need to create the plan on Stripe.
				 * Otherwise, we'll get the stripe plan id in here.
				 */
				$plan_id = $this->maybe_create_plan(
					[
						'name'           => $product->get_name(),
						'price'          => $amount,
						'interval'       => $product->get_duration_unit(),
						'interval_count' => $product->get_duration(),
						'currency'       => strtolower($cart->get_currency()),
					]
				);

				if (is_wp_error($plan_id)) {
					return $plan_id;
				}

				/*
				 * Adds the new plan ID to the subscription cart.
				 */
				$plans[ $plan_id ] = [
					'plan' => $plan_id,
				];
			} catch (\Exception $e) {
				$error_message = sprintf('Failed to create subscription for membership #%d. Message: %s', $this->membership->get_id(), $e->getMessage());

				return new \WP_Error('plan-creation-failed', esc_html($error_message));
			}

			/*
			 * Now, we handle the taxable status
			 * of the payment.
			 *
			 * We might need to create tax rates on
			 * Stripe and apply it on the subscription cart.
			 */
			if ($line_item->is_taxable() && ! empty($line_item->get_tax_rate())) {
				$tax_args = [
					'country'   => $this->membership->get_billing_address()->billing_country,
					'tax_rate'  => $line_item->get_tax_rate(),
					'type'      => $line_item->get_tax_type(),
					'title'     => $line_item->get_tax_label(),
					'inclusive' => $line_item->get_tax_inclusive(),
				];

				$plans[ $plan_id ]['tax_rates'] = [$this->maybe_create_tax_rate($tax_args)];
			}
		}

		return $plans;
	}

	/**
	 * Converts the Stripe invoice line items into Ultimate Multisite line items.
	 *
	 * @since 2.0.19
	 *
	 * @param Stripe\InvoiceLineItem[] $invoice_line_items The line items array.
	 * @return array
	 */
	protected function get_ultimo_line_items_from_invoice($invoice_line_items) {

		$line_items = [];

		$membership_products = [];

		if ($this->membership) {
			$m_products = $this->membership->get_all_products();

			foreach ($m_products as $array) {
				$membership_products[ $array['product']->get_name() ] = $array['product'];
			}
		}

		foreach ($invoice_line_items as $s_line_item) {
			$currency = strtoupper((string) $s_line_item->currency);

			$currency_multiplier = wu_stripe_get_currency_multiplier($currency);

			$quantity = $s_line_item->quantity;

			$description_pattern = "/{$quantity} × (.*) - .*/";

			$title = preg_replace($description_pattern, '$1', (string) $s_line_item->description);

			$has_taxes = ! empty($s_line_item->taxes);

			$line_item_data = [
				'title'         => $title,
				'description'   => $s_line_item->description,
				'tax_inclusive' => $has_taxes && 'inclusive' === $s_line_item->taxes[0]->tax_behavior,
				'unit_price'    => (float) $s_line_item->pricing->unit_amount_decimal / $currency_multiplier,
				'quantity'      => $quantity,
			];

			if (wu_get_isset($membership_products, $title)) {
				$line_item_data['product'] = wu_get_isset($membership_products, $title);
			}

			$line_item = new Line_Item($line_item_data);

			$subtotal  = $s_line_item->amount / $currency_multiplier;
			$tax_total = $has_taxes ? $s_line_item->taxes[0]->amount / $currency_multiplier : 0;
			$total     = $s_line_item->amount / $currency_multiplier;

			// Set this values after generate the line item to bypass the recalculate_totals
			$line_item->attributes(
				[
					'discount_total' => 0,
					'subtotal'       => $subtotal,
					'tax_total'      => $tax_total,
					'total'          => $total,
				]
			);

			$line_items[] = $line_item;
		}

		return $line_items;
	}

	/**
	 * Saves a payment method to a customer on Stripe.
	 *
	 * @since 2.0.0
	 *
	 * @param  \Stripe\PaymentIntent $payment_intent The payment intent.
	 * @param  \Stripe\Customer      $s_customer The stripe customer.
	 * @return \Stripe\PaymentMethod
	 */
	protected function save_payment_method($payment_intent, $s_customer) {

		$payment_method = false;

		try {
			$payment_method = $this->get_stripe_client()->paymentMethods->retrieve($payment_intent->payment_method);

			if (empty($payment_method->customer)) {
				$payment_method->attach(
					[
						'customer' => $s_customer->id,
					]
				);
			}

			/*
			 * Update remote payment methods.
			 */
			$this->get_stripe_client()->customers->update(
				$s_customer->id,
				[
					'invoice_settings' => [
						'default_payment_method' => $payment_intent->payment_method,
					],
				]
			);

			/*
			 * De-dupe payment methods.
			 *
			 * If someone re-registers with the same card details they've used in the past, Stripe
			 * will actually create a whole new payment method object with the same fingerprint.
			 * This could result in the same card being added to the customer's payment methods in
			 * Stripe, which is kind of annoying. So we de-dupe them to make sure one customer only
			 * has each payment method listed once. Hopefully Stripe will handle this automatically
			 * in the future.
			 */
			$customer_payment_methods = $this->get_stripe_client()->paymentMethods->all(
				[
					'customer' => $s_customer->id,
					'type'     => 'card',
				]
			);

			if ( ! empty($customer_payment_methods->data)) {
				foreach ($customer_payment_methods->data as $existing_method) {
					/*
					 * Detach if the fingerprint matches but payment method ID is different.
					 */
					if ($existing_method->card->fingerprint === $payment_method->card->fingerprint && $existing_method->id !== $payment_method->id) {
						$existing_method->detach();
					}
				}
			}
		} catch (\Exception $e) {
			$error = sprintf('Stripe Gateway: Failed to attach payment method to customer while activating membership #%d. Message: %s', 0, $e->getMessage());

			wu_log_add('stripe', $error, LogLevel::ERROR);
		}

		return $payment_method;
	}

	/**
	 * Maybe cancel old subscriptions.
	 *
	 * @since 2.0.0
	 *
	 * @param Stripe\Customer $s_customer The stripe customer.
	 * @return void
	 */
	public function maybe_cancel_old_subscriptions($s_customer): void {

		$allow_multiple_membership = wu_multiple_memberships_enabled();

		try {

			// Set up array of subscriptions we cancel below so we don't try to cancel the same one twice.
			$cancelled_subscriptions = [];

			// Clean up any past due or unpaid subscriptions. We do this to ensure we don't end up with duplicates.
			$subscriptions = $s_customer->subscriptions->all();

			foreach ( $subscriptions->data as $subscription ) {

				// Cancel subscriptions with the RCP metadata present and matching member ID.
				if ( ! empty($subscription->metadata) ) {
					$customer_id = (int) $subscription->metadata['customer_id'];

					// Legacy Ultimate Multisite uses user_id
					$user_id = (int) $subscription->metadata['user_id'];

					if (0 === $customer_id && 0 === $user_id) {
						continue;
					}

					if ($this->customer->get_id() !== $customer_id && $this->customer->get_user_id() !== $user_id) {
						continue;
					}

					$membership_id = (int) $subscription->metadata['membership_id'];

					if ($allow_multiple_membership && $this->membership->get_id() !== $membership_id) {
						continue;
					}

					if (0 === $membership_id && 0 === $customer_id) {
						/**
						 * If we do not have a $membership_id it can be a legacy subscription.
						 * The best way to check this is checking if the plan in Stripe haves
						 * a plan_id on metadata (value used on legacy)
						 */

						$stop_here = true;

						// Check if it is not a ultimo subscription
						foreach ($subscription->items->data as $item) {
							if ( ! empty($item->plan) && ! empty($item->plan->metadata) && isset($item->plan->metadata['plan_id'])) {
								if ( wu_get_product_by('migrated_from_id', $item->plan->metadata['plan_id']) ) {
									$stop_here = false;

									break;
								}
							}
						}

						if ($stop_here) {
							continue;
						}
					}

					// Check if membership exist and is from this customer before delete subscription
					if (0 !== $membership_id && $this->membership->get_id() !== $membership_id) {
						$membership_from_s = wu_get_membership($membership_id);

						if ( ! $membership_from_s || $membership_from_s->get_customer_id() !== $customer_id) {
							continue;
						}
					}

					$subscription->cancel();

					$cancelled_subscriptions[] = $subscription->id;

					wu_log_add('stripe', sprintf('Stripe Gateway: Cancelled Stripe subscription %s.', $subscription->id));

					continue;
				}
			}
		} catch ( \Exception $e ) {
			wu_log_add('stripe', sprintf('Stripe Gateway: Subscription cleanup failed for customer #%d. Message: %s', $this->customer->get_id(), $e->getMessage()), LogLevel::ERROR);
		}
	}


	/**
	 * Checks if using OAuth authentication.
	 *
	 * @since 2.x.x
	 * @return bool
	 */
	public function is_using_oauth(): bool {
		return 'oauth' === $this->authentication_mode && $this->is_connect_enabled;
	}

	/**
	 * It's nice to be paid for hard work.
	 *
	 * The fee only applies when using Stripe Connect (OAuth mode) and
	 * the site owner has not purchased any addon from ultimatemultisite.com.
	 *
	 * @since 2.4.11
	 * @return bool
	 */
	public function should_apply_application_fee(): bool {

		if (! $this->is_using_oauth()) {
			return false;
		}

		$addon_repo = \WP_Ultimo::get_instance()->get_addon_repository();

		return ! $addon_repo->has_addon_purchase();
	}


	/**
	 * Process a refund.
	 *
	 * It takes the data concerning
	 * a refund and process it.
	 *
	 * @param float                      $amount The amount to refund.
	 * @param Payment                    $payment The payment associated with the checkout.
	 * @param Membership                 $membership The membership.
	 * @param \WP_Ultimo\Models\Customer $customer The customer checking out.
	 *
	 * @return bool
	 * @throws \Exception Other Error.
	 * @since 2.0.0
	 */
	public function process_refund($amount, $payment, $membership, $customer): bool {

		$gateway_payment_id = $payment->get_gateway_payment_id();

		if (empty($gateway_payment_id)) {
			throw new \Exception(esc_html__('Gateway payment ID not found. Cannot process refund automatically.', 'ultimate-multisite'));
		}

		/**
		 * Ensure the correct api keys are set
		 */
		$this->setup_api_keys();

		/*
		 * Check if we have an invoice,
		 * or a charge at hand.
		 */
		if (str_starts_with((string) $gateway_payment_id, 'ch_')) {
			$charge_id = $gateway_payment_id;
		} elseif (str_starts_with((string) $gateway_payment_id, 'in_')) {
			$invoice = $this->get_stripe_client()->invoices->retrieve($gateway_payment_id);

			$gateway_payment_id = $invoice->charge;
		} else {
			throw new \Exception(esc_html__('Gateway payment ID not valid.', 'ultimate-multisite'));
		}

		/*
		 * We need to normalize the value
		 * for Stripe, which usually works
		 * in cents.
		 */
		$normalize_amount = $amount * wu_stripe_get_currency_multiplier(strtoupper($payment->get_currency()));

		$this->get_stripe_client()->refunds->create(
			[
				'charge' => $charge_id,
				'amount' => $normalize_amount,
			]
		);

		/*
		 * You might be asking why we are not
		 * calling $payment->refund($amount) to
		 * update the payment status.
		 *
		 * We will do that once Stripe tells us
		 * that the refund was successful.
		 */
		return true;
	}

	/**
	 * Gets the application fee percentage for Stripe Connect payments.
	 *
	 * @since 2.4.11
	 * @return float
	 */
	public function get_application_fee_percent(): float {

		return 3.0;
	}

	/**
	 * Process a cancellation.
	 *
	 * It takes the data concerning
	 * a membership cancellation and process it.
	 *
	 * @since 2.0.0
	 *
	 * @param \WP_Ultimo\Models\Membership $membership The membership.
	 * @param \WP_Ultimo\Models\Customer   $customer The customer checking out.
	 * @return void|bool
	 */
	public function process_cancellation($membership, $customer) {

		$subscription_id = $membership->get_gateway_subscription_id();

		if ( ! empty($subscription_id)) {
			/**
			 * Ensure the correct api keys are set
			 */
			$this->setup_api_keys();

			try {
				$subscription = $this->get_stripe_client()->subscriptions->retrieve($subscription_id);

				if ('canceled' !== $subscription->status) {
					$subscription->cancel();
				}
			} catch (\Exception $e) {
				wu_log_add('stripe', sprintf('Stripe Gateway: Failed to cancel subscription %s. Message: %s', $subscription_id, $e->getMessage()), LogLevel::ERROR);

				return false;
			}
		}
	}

	/**
	 * Attempt to guess the maximum `billing_cycle_anchor` Stripe will allow us to set, given a signup date
	 * and billing cycle interval.
	 *
	 * @param int    $interval      Billing cycle interval.
	 * @param string $interval_unit Billing cycle interval unit.
	 * @param string $signup_date   Signup date that can be parsed by `strtotime()`. Will almost always be
	 *                              `now`, but can be overridden for help in unit tests.
	 *
	 * @since 2.0.0
	 * @return \DateTime
	 */
	public function get_stripe_max_billing_cycle_anchor($interval, $interval_unit, $signup_date = 'now') {

		try {
			$signup_date = new \DateTimeImmutable($signup_date);
		} catch (\Exception $exception) {
			$signup_date = new \DateTimeImmutable();
		}

		$stripe_max_anchor = $signup_date->modify(sprintf('+%d %s', $interval, $interval_unit));

		$proposed_next_bill_date = new \DateTime();

		$proposed_next_bill_date->setTimestamp($signup_date->getTimestamp());

		// Set to first day of the month so we're not dealing with mismatching days of the month.
		$proposed_next_bill_date->setDate($proposed_next_bill_date->format('Y'), $proposed_next_bill_date->format('m'), 1);

		// Now we can safely add 1 interval and still be in the expected month.
		$proposed_next_bill_date->modify(sprintf('+ %d %s', $interval, $interval_unit));

		/*
		 * If the day of the month in the signup date exceeds the total number of days in the proposed month,
		 * set the anchor to the last day of the proposed month - whatever that is.
		 */
		if (date('j', $signup_date->getTimestamp()) > date('t', $proposed_next_bill_date->getTimestamp())) { // phpcs:ignore

			try {
				$stripe_max_anchor = new \DateTime(date('Y-m-t H:i:s', $proposed_next_bill_date->getTimestamp())); // phpcs:ignore
			} catch (\Exception $exception) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				// Silence is golden
			}
		}

		return $stripe_max_anchor;
	}

	/**
	 * Get Stripe error from exception
	 *
	 * This converts the exception into a WP_Error object with a localized error message.
	 *
	 * @param \Stripe\Exception\ExceptionInterface $e The stripe error object.
	 *
	 * @since 2.0.0
	 * @return \WP_Error
	 */
	protected function get_stripe_error($e) {

		$wp_error = new \WP_Error();

		if (method_exists($e, 'getJsonBody')) {
			$body  = $e->getJsonBody();
			$error = $body['error'];

			$wp_error->add($error['code'], $this->get_localized_error_message($error['code'], $e->getMessage()));
		} else {
			$wp_error->add('unknown_error', __('An unknown error has occurred.', 'ultimate-multisite'));
		}

		return $wp_error;
	}

	/**
	 * Localize common Stripe error messages so they're available for translation.
	 *
	 * @link https://stripe.com/docs/error-codes
	 *
	 * @param string $error_code    Stripe error code.
	 * @param string $error_message Original Stripe error message. This will be returned if we don't have a localized version of
	 *                              the error code.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	protected function get_localized_error_message($error_code, $error_message = '') {

		// TODO: Fetch a translated message from an error_code => error_message map.

		// translators: 1 is the error code and 2 the message.
		return sprintf(__('An error has occurred (code: %1$s; message: %2$s).', 'ultimate-multisite'), $error_code, $error_message);
	}

	/**
	 * Gives gateways a chance to run things before backwards compatible webhooks are run.
	 *
	 * @since 2.0.8
	 * @return void
	 */
	public function before_backwards_compatible_webhook(): void {

		if (empty($this->secret_key)) {
			$other_id = $this->get_id() === 'stripe' ? 'stripe-checkout' : 'stripe';

			/*
			 * If we don't have stripe anymore, and only stripe checkout,
			 * We might want to use the keys from stripe checkout here
			 * or vice-versa.
			 */
			$this->setup_api_keys($other_id);
		}
	}

	/**
	 * Process webhooks
	 *
	 * @return void
	 * @throws \Exception Other Error.
	 * @throws Ignorable_Exception Something that can be ignored.
	 * @since 2.0.0
	 */
	public function process_webhooks() {

		wu_log_add('stripe', 'Receiving Stripe webhook...');

		/*
		 * PHP Input as object
		 */
		$received_event = wu_get_input();

		// for extra security, retrieve from the Stripe API
		if ( ! isset($received_event->id)) {
			throw new \Exception(esc_html__('Event ID not found.', 'ultimate-multisite'));
		}

		// Set the right mode for this request.
		// Fix: operator precedence bug — `! $received_event->livemode !== $this->test_mode`
		// was evaluated as `(!livemode) !== test_mode`, which is always true for live events
		// in test mode, causing setup_api_keys() to load the wrong key set and the subsequent
		// events->retrieve() call to fail with an authentication error.
		// Correct logic: if the event's livemode flag disagrees with our current test_mode
		// setting, update test_mode to match the incoming event so the right keys are loaded.
		if (isset($received_event->livemode) && (bool) $received_event->livemode === $this->test_mode) {
			$this->test_mode = ! $received_event->livemode;
		}

		/**
		 * Ensure the correct api keys are set
		 */
		$this->setup_api_keys();

		$event_id = $received_event->id;

		$event         = $this->get_stripe_client()->events->retrieve($event_id);
		$payment_event = $event->data->object;

		$membership   = false;
		$payment      = false;
		$customer     = false;
		$invoice      = false;
		$subscription = false;

		/*
		 * Check if we have a customer present.
		 */
		if (empty($payment_event->customer)) {
			return;
		}

		/*
		 * Try to get an invoice object from the payment event.
		 */
		if ( ! empty($payment_event->object) && 'invoice' === $payment_event->object) {
			$invoice = $payment_event;
		} elseif ( ! empty($payment_event->invoice)) {
			$invoice = $this->get_stripe_client()->invoices->retrieve($payment_event->invoice);
		}

		/*
		 * Now try to get a subscription from the invoice object.
		 */
		if ( ! empty($invoice->subscription)) {
			$subscription = $this->get_stripe_client()->subscriptions->retrieve($invoice->subscription);
		}

		/*
		 * We can also get the subscription by the
		 * object ID in some circumstances.
		 */
		if (empty($subscription) && str_contains((string) $payment_event->id, 'sub_')) {
			$subscription = $this->get_stripe_client()->subscriptions->retrieve($payment_event->id);
		}

		/*
		 * Retrieve the membership by subscription ID.
		 */
		if ( ! empty($subscription)) {
			$membership = wu_get_membership_by('gateway_subscription_id', $subscription->id);
		}

		// Retrieve the membership by payment meta (one-time charges only).
		if ( ! empty($payment_event->metadata->membership_id)) {
			$membership = wu_get_membership($payment_event->metadata->membership_id);
		}

		/**
		 * Last ditch effort to retrieve a valid membership.
		 */
		if (empty($membership) && ! empty($invoice)) {
			$amount = $invoice->amount_paid / wu_stripe_get_currency_multiplier(strtoupper($invoice->currency ?? 'USD'));

			$membership = wu_get_membership_by_customer_gateway_id($payment_event->customer, ['stripe', 'stripe-checkout'], $amount);
		}

		/**
		 * Filters the membership record associated with this webhook.
		 *
		 * This filter was introduced due to conflicts that may arise when the same Stripe customer may
		 * be used on different sites.
		 *
		 * @param \WP_Ultimo\Models\Membership|false $membership The membership object.
		 * @param Stripe\Event                       $event The event received.
		 */
		$membership = apply_filters('wu_stripe_webhook_membership', $membership, $event);

		$this->membership = $membership;

		if ( ! is_a($membership, '\WP_Ultimo\Models\Membership')) {
			/**
			 * If we don't have a membership, we can't do anything
			 * and this is not an error.
			 */
			return;
		}

		/**
		 *  Ensure the membership is using the current gateway
		 */
		if ($this->get_id() !== $membership->get_gateway()) {

			// translators: %s is the customer ID.
			throw new Ignorable_Exception(esc_html(sprintf(__('Exiting Stripe webhook - This call must be handled by %s webhook', 'ultimate-multisite'), $membership->get_gateway())));
		}

		/*
		 * Set the Ultimate Multisite customer.
		 */
		$customer = $membership->get_customer();

		$this->customer = $customer;

		/*
		 * Now, we actually get to handle
		 * webhook messages.
		 *
		 * We'll handle 4 cases:
		 *
		 * 1. Customer subscription created - For Stripe Gateway;
		 * 2. Charge Succeeded & Invoice Payment Succeed;
		 * 3. Payment failed;
		 * 4. Subscription deleted.
		 *
		 * First, we'll start customer subscription created.
		 */
		if ('customer.subscription.created' === $event->type) {
			do_action('wu_webhook_recurring_payment_profile_created', $membership, $this);
		}

		/*
		 * Deal with Stripe Checkouts case.
		 *
		 * On Stripe Checkout, we rely entirely on
		 * the webhook call to change the status of things.
		 */
		if ('checkout.session.completed' === $event->type) {
			$membership->set_gateway_customer_id($payment_event->customer);

			$membership->set_gateway_subscription_id($payment_event->subscription);

			$membership->set_gateway($this->get_id());

			$membership->save();

			return;
		}

		/*
		 * Next, let's deal with charges that went through!
		 */
		if ('charge.succeeded' === $event->type || 'invoice.payment_succeeded' === $event->type) {
			/**
			 * Here we need to handle invoice.payment_succeeded
			 * events due subscriptions with trials and we need
			 * to handle charge.succeeded for payments without
			 * stripe invoices.
			 */

			$payment_data = [
				'status'  => Payment_Status::COMPLETED,
				'gateway' => $this->get_id(),
			];

			if ('charge.succeeded' === $event->type) {
				/*
				 * Successful one-time payment
				 */
				if (empty($payment_event->invoice)) {
					$payment_data['total']              = $payment_event->amount / wu_stripe_get_currency_multiplier(strtoupper($payment_event->currency ?? 'USD'));
					$payment_data['gateway_payment_id'] = $payment_event->id;

					/*
					* Subscription payment received.
					*/
				} else {
					$invoice_currency = strtoupper($invoice->currency ?? 'USD');
					$payment_data['total']              = $invoice->total / wu_stripe_get_currency_multiplier($invoice_currency);
					$payment_data['subtotal']           = ($invoice->total_excluding_tax / wu_stripe_get_currency_multiplier($invoice_currency)) - $payment_data['discount_total'];
					$payment_data['tax_total']          = $invoice->tax / wu_stripe_get_currency_multiplier($invoice_currency);
					$payment_data['gateway_payment_id'] = $payment_event->id;

					if ( ! empty($payment_event->discount)) {
						$payment_data['discount_code'] = $payment_event->discount->coupon_id;
					}
				}
			}

			/*
			 * Let's check if we have the payment
			 * created already. We only want to create a new
			 * one if we don't have one already.
			 */
			$gateway_payment_id = $payment_event->id;

			$payment = wu_get_payment_by('gateway_payment_id', $gateway_payment_id);

			$expiration = false;

			/*
			 * Payment does not exist.
			 */
			if ( ! empty($gateway_payment_id) && ! $payment) {
				/*
				 * Checks if we have the data about a subscription.
				 */
				if ( ! empty($subscription)) {
					$membership->set_recurring(true);

					$membership->set_gateway_subscription_id($subscription->id);

					/*
					 * Set the new expiration date.
					 * We use the `current_period_end` as our base and force the time to be 23:59:59 that day.
					 * However, this must be at least two hours after `current_period_end` to ensure there's
					 * plenty of time between the next invoice being generated and actually being paid/finalized.
					 * Stripe usually does this within 1 hour, but we're using 2 to be on the safe side and
					 * account for delays.
					*/
					$renewal_date = new \DateTime();
					foreach ($subscription->items as $item) {
						$end_timestamp = $item->current_period_end;
						break;
					}
					$renewal_date->setTimestamp($end_timestamp);
					$renewal_date->setTime(23, 59, 59);

					/*
					 * Estimated charge date is 2 hours
					 * after `current_period_end`.
					 */
					$stripe_estimated_charge_timestamp = $end_timestamp + (2 * HOUR_IN_SECONDS);

					if ($stripe_estimated_charge_timestamp > $renewal_date->getTimestamp()) {
						$renewal_date->setTimestamp($stripe_estimated_charge_timestamp);
					}

					/*
					 * Set the expiration.
					 */
					$expiration = $renewal_date->format('Y-m-d H:i:s');
				}

				/*
				 * Checks for a pending payment on the membership.
				 */
				$pending_payment = $membership->get_last_pending_payment();

				if ( ! empty($pending_payment)) {
					/*
					 * Completing a pending payment.
					 */
					$pending_payment->attributes($payment_data);

					$payment = $pending_payment;
				} elseif ('charge.succeeded' === $event->type) {
					/**
					 * These must be retrieved after the status
					 * is set to active in order for upgrades to work properly
					 */

					// We need to get the line items from the invoice.
					$line_items = $this->get_ultimo_line_items_from_invoice($invoice->lines->data);

					// If we have a tax_total let's add it to line items.
					if ( ! empty($payment_data['tax_total'])) {
						foreach ($line_items as &$line_item) {
							$current_total       = $line_item->get_total();
							$percent_of_subtotal = $current_total / $payment_data['subtotal'];

							$item_tax_total = $payment_data['tax_total'] * $percent_of_subtotal;
							$item_total     = $current_total + $item_tax_total;
							$item_tax_rate  = round(($item_tax_total / $current_total) * 100, 2);

							$line_item->set_tax_total($item_tax_total);
							$line_item->set_tax_rate($item_tax_rate);
							$line_item->set_total($item_total);
						}
					}

					/*
					 * As we add the discount code value into plan price we need
					 * to add the discount code from membership if it exist.
					 */
					$discount_code = $membership->get_discount_code();

					if ($discount_code && $discount_code->should_apply_to_renewals()) {
						$type = $discount_code->get_type();

						$old_subtotal = $payment_data['subtotal'];

						if ('percentage' === $type) {
							$payment_data['subtotal'] = $old_subtotal / (1 - ($discount_code->get_value() / 100));

							$discount_total = $payment_data['subtotal'] - $old_subtotal;
						} elseif ('absolute' === $type) {
							$discount_total = $discount_code->get_value();

							$payment_data['subtotal'] -= $discount_total;
						}

						// Now we apply this discount to the line items.
						foreach ($line_items as $line_item) {
							$current_item_subtotal = $line_item->get_subtotal();
							$percent_of_subtotal   = $current_item_subtotal / $old_subtotal;

							$line_item->set_discount_total($discount_total * $percent_of_subtotal);
							$line_item->set_subtotal($line_item->get_discount_total() + $current_item_subtotal);
						}
					}

					$payment_data['transaction_type'] = 'renewal';
					$payment_data['customer_id']      = $customer->get_id();
					$payment_data['membership_id']    = $membership->get_id();
					$payment_data['line_items']       = $line_items;
					$payment                          = wu_create_payment($payment_data);
				} else {
					/**
					 *  We do not have a payment to change
					 */
					return;
				}

				$this->payment = $payment;

				if ('charge' === $payment_event->object && ! $subscription && $this->get_id() === 'stripe') {
					$cart = $payment->get_meta('wu_original_cart');

					$payment_intent_id = (string) $payment->get_meta('stripe_payment_intent_id');

					// We handle setup intents from process_checkout.
					$is_setup_intent = str_starts_with($payment_intent_id, 'seti_');

					if ($cart && $cart->should_auto_renew() && $cart->has_recurring() && ! $is_setup_intent) {
						$s_customer     = $this->get_stripe_client()->customers->retrieve($payment_event->customer);
						$payment_method = $this->get_stripe_client()->paymentMethods->retrieve($payment_event->payment_method);

						$subscription = $this->create_recurring_payment($membership, $cart, $payment_method, $s_customer);

						// If we receive a subscription, we need to update the membership.
						if ($subscription) {
							$membership->set_gateway_subscription_id($subscription->id);

							$renewal_date = new \DateTime();

							foreach ($subscription->items as $item) {
								$end_timestamp = $item->current_period_end;
								break;
							}
							$renewal_date->setTimestamp($end_timestamp);
							$renewal_date->setTime(23, 59, 59);

							$stripe_estimated_charge_timestamp = $end_timestamp + (2 * HOUR_IN_SECONDS);

							if ($stripe_estimated_charge_timestamp > $renewal_date->getTimestamp()) {
								$renewal_date->setTimestamp($stripe_estimated_charge_timestamp);
							}

							$expiration = $renewal_date->format('Y-m-d H:i:s');
						} else {
							return;
						}
					}
				}

				/**
				 * Renewals the membership
				 */
				$membership->add_to_times_billed(1);
				$membership->renew($membership->is_recurring(), 'active', $expiration);

				/**
				 * We need to save here to ensure that we are not saving more than once.
				 *
				 * @see process_checkout method
				 */
				$payment->save();

				/*
				 * Tell the gateway to do their stuff.
				 */
				$this->trigger_payment_processed($payment, $membership);

				return;
			} elseif ( ! empty($gateway_payment_id) && $payment) {
				/*
				 * The payment already exists.
				 *
				 * Throws to inform that
				 * we have a duplicate payment.
				 */
				throw new Ignorable_Exception(esc_html__('Duplicate payment.', 'ultimate-multisite'));
			}
		}

		/*
		 * Next, let's deal with charges that went through!
		 */
		if ('charge.refunded' === $event->type) {
			$payment_id = $payment_event->metadata->payment_id;

			$payment = wu_get_payment($payment_id);

			if (empty($payment)) {
				throw new Ignorable_Exception(esc_html__('Payment not found on refund webhook call.', 'ultimate-multisite'));
			}

			$is_refundable = in_array($payment->get_status(), wu_get_refundable_payment_types(), true);

			if ( ! $is_refundable) {
				throw new Ignorable_Exception(esc_html__('Payment is not refundable.', 'ultimate-multisite'));
			}

			/*
			 * Let's address the type.
			 */
			$amount = $payment_event->amount_refunded / wu_stripe_get_currency_multiplier(strtoupper($payment_event->currency ?? 'USD'));

			/*
			 * Actually process the refund
			 * using the helper method.
			 */
			$payment->refund($amount);

			return;
		}

		/*
		 * Failed payments.
		 */
		if ('invoice.payment_failed' === $event->type) {
			wu_log_add('stripe', 'Processing Stripe invoice.payment_failed webhook.');

			// Make sure this invoice is tied to a subscription and is the user's current subscription.
			if ( ! empty($event->data->object->subscription) && $membership->get_gateway_subscription_id() === $event->data->object->subscription) {
				do_action('wu_recurring_payment_failed', $membership, $this);

				$customer = $membership->get_customer();

				if ($customer) {
					$payload = array_merge(
						wu_generate_event_payload('membership', $membership),
						wu_generate_event_payload('customer', $customer),
						[
							'payment_gateway' => $this->get_id(),
						]
					);

					wu_do_event('payment_failed', $payload);
				}
			}

			do_action('wu_stripe_charge_failed', $payment_event, $event, $membership);

			return;
		}

		/*
		 * Subscription status changed (e.g. active → past_due, unpaid, paused, or canceled).
		 *
		 * Stripe fires customer.subscription.updated whenever any subscription attribute changes,
		 * including the status field. We only act when the status has actually changed to a
		 * non-active state so that routine billing-cycle updates (current_period_end changes)
		 * do not trigger unnecessary membership status transitions.
		 *
		 * Mapping:
		 *  - past_due  → membership expires (grace period; Stripe will retry and may recover)
		 *  - unpaid    → membership expires (Stripe gave up retrying)
		 *  - canceled  → membership cancelled (handled by subscription.deleted too, belt-and-suspenders)
		 *  - paused    → membership expires
		 */
		if ('customer.subscription.updated' === $event->type) {
			wu_log_add('stripe', 'Processing Stripe customer.subscription.updated webhook.');

			if ($membership->get_gateway_subscription_id() === $payment_event->id) {
				$stripe_status = $payment_event->status ?? '';

				$previous_attributes = $event->data->previous_attributes ?? null;
				$previous_status     = $previous_attributes->status ?? null;

				// Only act when the status field itself changed.
				if (null !== $previous_status && $previous_status !== $stripe_status) {
					wu_log_add('stripe', sprintf('Subscription %s status changed: %s → %s', $payment_event->id, $previous_status, $stripe_status));

					if (in_array($stripe_status, ['past_due', 'unpaid', 'paused'], true)) {
						/*
						 * Mark the membership as expired so access is revoked.
						 * Stripe may still recover the payment (retries), in which case
						 * the invoice.payment_succeeded webhook will renew the membership.
						 */
						if ($membership->is_active()) {
							$membership->set_status(Membership_Status::EXPIRED);
							$membership->save();

							$membership->add_note(
								[
									// translators: %s is the Stripe subscription status (e.g. "past_due").
									'text' => sprintf(__('Membership expired via Stripe webhook — subscription status: %s.', 'ultimate-multisite'), $stripe_status),
								]
							);

							do_action('wu_stripe_subscription_status_changed', $membership, $stripe_status, $previous_status, $this);
						}
					} elseif ('canceled' === $stripe_status) {
						/*
						 * Belt-and-suspenders: customer.subscription.deleted fires for hard
						 * cancellations, but some cancellation paths only fire .updated with
						 * status=canceled. Handle both to avoid missed cancellations.
						 */
						if ($membership->is_active()) {
							$membership->cancel();

							$membership->add_note(['text' => __('Membership cancelled via Stripe webhook (subscription.updated).', 'ultimate-multisite')]);
						}
					} elseif ('active' === $stripe_status || 'trialing' === $stripe_status) {
						/*
						 * Subscription recovered (e.g. past_due → active after successful retry).
						 * Renew the membership if it is currently expired.
						 */
						if (Membership_Status::EXPIRED === $membership->get_status()) {
							$renewal_date = new \DateTime();

							foreach ($payment_event->items->data as $item) {
								$end_timestamp = $item->current_period_end;
								break;
							}

							if ( ! empty($end_timestamp)) {
								$renewal_date->setTimestamp($end_timestamp);
								$renewal_date->setTime(23, 59, 59);

								$stripe_estimated_charge_timestamp = $end_timestamp + (2 * HOUR_IN_SECONDS);

								if ($stripe_estimated_charge_timestamp > $renewal_date->getTimestamp()) {
									$renewal_date->setTimestamp($stripe_estimated_charge_timestamp);
								}

								$expiration = $renewal_date->format('Y-m-d H:i:s');
							} else {
								$expiration = '';
							}

							$new_status = 'trialing' === $stripe_status ? Membership_Status::TRIALING : Membership_Status::ACTIVE;

							$membership->renew($membership->is_recurring(), $new_status, $expiration);

							$membership->add_note(
								[
									// translators: %s is the Stripe subscription status (e.g. "active").
									'text' => sprintf(__('Membership renewed via Stripe webhook — subscription recovered to status: %s.', 'ultimate-multisite'), $stripe_status),
								]
							);

							do_action('wu_stripe_subscription_status_changed', $membership, $stripe_status, $previous_status, $this);
						}
					}
				}
			} else {
				wu_log_add('stripe', sprintf('subscription.updated: event sub ID (%s) does not match membership sub ID (%s) — skipping.', $payment_event->id, $membership->get_gateway_subscription_id()));
			}

			return;
		}

		/*
		 * Cancelled / failed subscription.
		 */
		if ('customer.subscription.deleted' === $event->type) {
			wu_log_add('stripe', 'Processing Stripe customer.subscription.deleted webhook.');

			if ($membership->get_gateway_subscription_id() === $payment_event->id) {
				/*
				 * If this is a completed payment plan,
				 * we can skip any cancellation actions.
				 */
				if ( ! $membership->is_forever_recurring() && $membership->at_maximum_renewals()) {
					return;
				}

				if ($membership->is_active()) {
					$membership->cancel();

					$membership->add_note(['text' => __('Membership cancelled via Stripe webhook.', 'ultimate-multisite')]);
				} else {
					wu_log_add('stripe', sprintf('Membership #%d is not active - not cancelling account.', $membership->get_id()));
				}
			} else {
				wu_log_add('stripe', sprintf('Payment event ID (%s) doesn\'t match membership\'s merchant subscription ID (%s).', $payment_event->id, $membership->get_gateway_subscription_id()), true);
			}
		}
	}

	/**
	 * Get saved card options for this customer.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function get_saved_card_options() {

		if ( ! is_user_logged_in()) {
			return [];
		}

		$options = [];

		$saved_payment_methods = $this->get_user_saved_payment_methods();

		foreach ($saved_payment_methods as $saved_payment_method) {
			$options[ $saved_payment_method->id ] = sprintf(
				// translators: 1 is the card brand (e.g. VISA), and 2 is the last 4 digits.
				__('%1$s ending in %2$s', 'ultimate-multisite'),
				strtoupper((string) $saved_payment_method->card->brand),
				$saved_payment_method->card->last4
			);
		}

		return $options;
	}
	/**
	 * Add credit card fields.
	 *
	 * @since 2.0.0
	 */
	public function fields(): string {

		return '';
	}

	/**
	 * Load fields for the Update Billing Card form.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function update_card_fields(): void { // phpcs:disable ?>

		<div class="wu-gateway-new-card-fields">

			<fieldset id="wu-card-name-wrapper" class="wu_card_fieldset">
				<p id="wu_card_name_wrap">
					<label for="wu-update-card-name"><?php esc_html_e('Name on Card', 'ultimate-multisite'); ?></label>
					<input type="text" size="20" id="wu-update-card-name" name="wu_card_name" class="wu_card_name card-name" />
				</p>
			</fieldset>

			<fieldset id="wu-card-wrapper" class="wu_card_fieldset">
				<div id="wu_card_wrap">
					<div id="wu-card-element"></div>
				</div>
			</fieldset>

		</div>

		<div id="wu-card-element-errors"></div>

		<?php // phpcs:enable
	}

	/**
	 * Register stripe scripts.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function register_scripts(): void {

		$active_gateways = (array) wu_get_setting('active_gateways', []);

		if (empty($this->publishable_key) || ! in_array($this->get_id(), $active_gateways, true)) {
			return;
		}

		wp_register_script('wu-stripe-sdk', 'https://js.stripe.com/v3/', false, 'v3', true);

		wp_register_script("wu-{$this->get_id()}", wu_get_asset("gateways/{$this->get_id()}.js", 'js'), ['wu-checkout', 'wu-stripe-sdk'], wu_get_version(), true);

		$saved_cards = $this->get_saved_card_options();

		$obj_name = 'wu_' . str_replace('-', '_', (string) $this->get_id());

		wp_localize_script(
			"wu-{$this->get_id()}",
			$obj_name,
			[
				'pk_key'                  => $this->publishable_key,
				'request_billing_address' => $this->request_billing_address,
				'add_new_card'            => empty($saved_cards),
				'payment_method'          => empty($saved_cards) ? 'add-new' : current(array_keys($saved_cards)),
				'currency'                => strtolower((string) wu_get_setting('currency_symbol', 'USD')),
			]
		);

		wp_enqueue_script("wu-{$this->get_id()}");
	}

	/**
	 * Maybe create a new tax rate on Stripe
	 *
	 * @since 2.0.0
	 *
	 * @param array $args The tax rate arguments.
	 * @return string
	 */
	public function maybe_create_tax_rate($args) {

		$slug = strtolower(sprintf('%s-%s-%s', $args['country'], $args['tax_rate'], $args['type']));

		if (wu_get_isset($args, 'inclusive')) {
			$slug .= '-inclusive';
		}

		static $cache = [];

		if (wu_get_isset($cache, $slug)) {
			return wu_get_isset($cache, $slug);
		}

		$stripe_tax_rates = $this->get_stripe_client()->taxRates->all();

		foreach ($stripe_tax_rates as $stripe_tax_rate) {
			if (isset($stripe_tax_rate->metadata->tax_rate_id) && $stripe_tax_rate->metadata->tax_rate_id === $slug) {
				$cache[ $slug ] = $stripe_tax_rate->id;

				return $stripe_tax_rate->id;
			}
		}

		$args = [
			'display_name' => $args['title'],
			'description'  => $args['title'],
			'jurisdiction' => $args['country'],
			'percentage'   => absint($args['tax_rate']),
			'inclusive'    => wu_get_isset($args, 'inclusive'),
			'metadata'     => [
				'tax_rate_id' => $slug,
			],
		];

		try {
			$tax_rate = $this->get_stripe_client()->taxRates->create($args);

			return $tax_rate->id;
		} catch (\Exception $exception) {

			// Silence is golden.
			return '';
		}
	}

	/**
	 * Checks to see if a plan exists with the provided arguments, and if so, returns the ID of
	 * that plan. If not, a new plan is created.
	 *
	 * This method differs from create_plan() and plan_exists() because it doesn't expect
	 * a membership level ID number. This allows for the creation of plans that may not be
	 * exactly based on a membership level's parameters.
	 *
	 * @param array $args           {
	 *                              Array of arguments.
	 *
	 * @type string $name           Required. Name of the plan.
	 * @type float  $price          Required. Price each interval.
	 * @type string $interval       Optional. Billing interval (i.e ."day", "month", "year"). Default is "month".
	 * @type int    $interval_count Optional. Interval count. Default is "1".
	 * @type string $currency       Optional. Currency. Defaults to site currency.
	 * @type string $id             Optional. Plan ID. Automatically generated based on arguments.
	 *                    }
	 *
	 * @since 2.0.0
	 * @return string|\WP_Error Plan ID on success or WP_Error on failure.
	 */
	public function maybe_create_plan($args) {

		$args = wp_parse_args(
			$args,
			[
				'name'           => '',
				'price'          => 0.00,
				'interval'       => 'month',
				'interval_count' => 1,
				'currency'       => strtolower((string) wu_get_setting('currency_symbol', 'USD')),
				'id'             => '',
			]
		);

		// Name and price are required.
		if (empty($args['name']) || empty($args['price'])) {
			return new \WP_Error('missing_name_price', __('Missing plan name or price.', 'ultimate-multisite'));
		}

		/*
		 * Create a new object that looks like a membership level object.
		 * We do this because generate_plan_id() expects a membership level object but we
		 * don't actually have one.
		 */
		if (empty($args['id'])) {
			$plan_level                = new \stdClass();
			$plan_level->name          = $args['name'];
			$plan_level->price         = $args['price'];
			$plan_level->duration      = $args['interval_count'];
			$plan_level->duration_unit = $args['interval'];
			$plan_level->currency      = $args['currency'];
			$plan_id                   = $this->generate_plan_id($plan_level);
		} else {
			$plan_id = $args['id'];
		}

		if (empty($plan_id)) {
			return new \WP_Error('empty_plan_id', __('Empty plan ID.', 'ultimate-multisite'));
		}

		// Convert price to Stripe format.
		$price = round($args['price'] * wu_stripe_get_currency_multiplier(strtoupper($args['currency'] ?? 'USD')), 0);

		// First check to see if a plan exists with this ID. If so, return that.
		try {
			$membership_level = $plan_level ?? new \stdClass();

			/**
			 * Filters the ID of the plan to check for. If this exists, the new subscription will
			 * use this plan.
			 *
			 * @param string $plan_id          ID of the Stripe plan to check for.
			 * @param object $membership_level Membership level object.
			 */
			$existing_plan_id = apply_filters('wu_stripe_existing_plan_id', $plan_id, $membership_level);

			$plan = $this->get_stripe_client()->plans->retrieve($existing_plan_id);

			return $plan->id;
		} catch (\Exception $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch

			// silence is golden
		}

		// Otherwise, create a new plan.
		try {
			$product = $this->get_stripe_client()->products->create(
				[
					'name' => $args['name'] . ' - ' . $args['currency'],
				]
			);

			$plan = $this->get_stripe_client()->plans->create(
				[
					'amount'         => $price,
					'interval'       => $args['interval'],
					'interval_count' => $args['interval_count'],
					'currency'       => $args['currency'],
					'id'             => $plan_id,
					'product'        => $product->id,
				]
			);

			// plan successfully created
			return $plan->id;
		} catch (\Exception $e) {
			$error_code = $e->getCode();

			// WP Error did not handle empty error code
			if (empty($error_code)) {
				if (method_exists($e, 'getHttpStatus')) {
					$error_code = $e->getHttpStatus();
				} else {
					$error_code = 500;
				}
			}

			wu_log_add('stripe', sprintf('Error creating Stripe plan. Code: %s; Message: %s', $error_code, $e->getMessage()), LogLevel::ERROR);

			return new \WP_Error('stripe_exception', sprintf('Error creating Stripe plan. Code: %s; Message: %s', $error_code, $e->getMessage()));
		}
	}

	/**
	 * Checks to see if a product exists with the provided arguments, and if so, returns the ID of
	 * that product. If not, a new product is created.
	 *
	 * @since 2.1.1
	 *
	 * @param string $name Product name.
	 * @param string $id   Optional. Product ID. Automatically generated based on arguments.
	 * @return string|\WP_Error Product ID on success or WP_Error on failure.
	 */
	private function maybe_create_product($name, $id = '') {

		// Name are required.
		if (empty($name)) {
			return new \WP_Error('missing_name', __('Missing product name.', 'ultimate-multisite'));
		}

		if (empty($id)) {
			$product_id = strtolower(str_replace(' ', '', sanitize_title_with_dashes($name)));
			$product_id = sprintf('wu-%s', $product_id);
			$product_id = preg_replace('/[^a-z0-9_\-]/', '-', $product_id);
		} else {
			$product_id = $id;
		}

		if (empty($product_id)) {
			return new \WP_Error('empty_product_id', __('Empty product ID.', 'ultimate-multisite'));
		}

		// First check to see if a product exists with this ID. If so, return that.
		try {
			/**
			 * Filters the ID of the product to check for. If this exists, the new subscription will
			 * use this product.
			 *
			 * @param string $product_id ID of the Stripe product to check for.
			 * @param string $name       Product name.
			 */
			$existing_product_id = apply_filters('wu_stripe_existing_product_id', $product_id, $name);

			$product = $this->get_stripe_client()->products->retrieve($existing_product_id);

			return $product->id;
		} catch (\Exception $e) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch

			// silence is golden
		}

		// Otherwise, create a new product.
		try {
			$product = $this->get_stripe_client()->products->create(
				[
					'id'   => $product_id,
					'name' => $name,
				]
			);

			// product successfully created
			return $product->id;
		} catch (\Exception $e) {
			$error_code = $e->getCode();

			// WP Error did not handle empty error code
			if (empty($error_code)) {
				if (method_exists($e, 'getHttpStatus')) {
					$error_code = $e->getHttpStatus();
				} else {
					$error_code = 500;
				}
			}

			wu_log_add('stripe', sprintf('Error creating Stripe product. Code: %s; Message: %s', $error_code, $e->getMessage()), LogLevel::ERROR);

			return new \WP_Error('stripe_exception', sprintf('Error creating Stripe product. Code: %s; Message: %s', $error_code, $e->getMessage()));
		}
	}

	/**
	 * Checks to see if a price exists with the provided arguments,
	 * and if so, returns the ID of that price. If not, a new price is created.
	 *
	 * @since 2.1.3
	 * @param string $title         Price title.
	 * @param float  $amount        Amount.
	 * @param string $currency      Currency.
	 * @param int    $quantity      Quantity.
	 * @param int    $duration      Duration.
	 * @param string $duration_unit Duration unit.
	 * @param string $tax_behavior  Tax behavior.
	 * @return string|\WP_Error Price ID on success or WP_Error on failure.
	 */
	public function maybe_create_price($title, $amount, $currency, $quantity = 1, $duration = false, $duration_unit = false, $tax_behavior = '') {

		$name = 1 === $quantity ? $title : "x$quantity $title";

		$currency = strtolower($currency);
		$s_amount = round($amount * wu_stripe_get_currency_multiplier(strtoupper($currency)));

		$s_product = $this->maybe_create_product($name);

		$s_price_data = [
			'lookup_key'  => "$s_product-$s_amount-$currency",
			'unit_amount' => $s_amount,
			'currency'    => $currency,
			'product'     => $s_product,
		];

		if ($duration && $duration_unit) {
			$s_price_data['recurring'] = [
				'interval'       => $duration_unit,
				'interval_count' => $duration,
			];

			$s_price_data['lookup_key'] .= "-$duration-$duration_unit";
		}

		if ( ! empty($tax_behavior)) {
			$s_price_data['tax_behavior'] = $tax_behavior;
			$s_price_data['lookup_key']  .= "-$tax_behavior";
		}

		// check if price already exists
		$existing = $this->get_stripe_client()->prices->all(
			[
				'lookup_keys' => [$s_price_data['lookup_key']],
				'limit'       => 1,
			]
		);

		if ( ! empty($existing->data)) {
			return $existing->data[0]->id;
		}

		$s_price = $this->get_stripe_client()->prices->create($s_price_data);

		return $s_price->id;
	}

	/**
	 * Generate a Stripe plan ID string based on a membership level
	 *
	 * The plan name is set to {levelname}-{price}-{duration}{duration unit}
	 * Strip out invalid characters such as '@', '.', and '()'.
	 * Similar to WP core's sanitize_html_class() & sanitize_key() functions.
	 *
	 * @param object $product_info The product info object.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	private function generate_plan_id($product_info) {

		$product_name = strtolower(str_replace(' ', '', sanitize_title_with_dashes($product_info->name)));

		$plan_id = sprintf('%s-%s-%s-%s', $product_name, $product_info->price, $product_info->currency, $product_info->duration . $product_info->duration_unit);

		$plan_id = preg_replace('/[^a-z0-9_\-]/', '-', $plan_id);

		return $plan_id;
	}

	/**
	 * Get the saved Stripe payment methods for a given user ID.
	 *
	 * @since 2.0.0
	 *
	 * @throws \Exception, When info is wrong.
	 * @throws \Exception When info is wrong 2.
	 * @return \Stripe\PaymentMethod[]|array
	 */
	public function get_user_saved_payment_methods() {

		$customer = wu_get_current_customer();

		if ( ! $customer) {
			return [];
		}

		$customer_id = $customer->get_id();

		/**
		 * Ensure the correct api keys are set
		 */
		$this->setup_api_keys();

		try {
			/*
			 * Declare static to prevent multiple calls.
			 */
			static $existing_payment_methods;

			if ( ! is_null($existing_payment_methods) && array_key_exists($customer_id, $existing_payment_methods)) {
				return $existing_payment_methods[ $customer_id ];
			}

			$customer_payment_methods = [];

			$stripe_customer_id = \WP_Ultimo\Models\Membership::query(
				[
					'customer_id' => $customer_id,
					'search'      => 'cus_*',
					'fields'      => ['gateway_customer_id'],
				]
			);

			$stripe_customer_id = current(array_column($stripe_customer_id, 'gateway_customer_id'));

			$payment_methods = $this->get_stripe_client()->paymentMethods->all(
				[
					'customer' => $stripe_customer_id,
					'type'     => 'card',
				]
			);

			foreach ($payment_methods->data as $payment_method) {
				$customer_payment_methods[ $payment_method->id ] = $payment_method;
			}

			$existing_payment_methods[ $customer_id ] = $customer_payment_methods;

			return $existing_payment_methods[ $customer_id ];
		} catch (\Throwable $exception) {
			return [];
		}
	}

	/**
	 * Returns the external link to view the payment on the payment gateway.
	 *
	 * Return an empty string to hide the link element.
	 *
	 * @since 2.0.0
	 *
	 * @param string $gateway_payment_id The gateway payment id.
	 * @return string.
	 */
	public function get_payment_url_on_gateway($gateway_payment_id): string {

		$route = $this->test_mode ? '/test' : '/';

		$path = 'payments';

		if (str_starts_with($gateway_payment_id, 'in_')) {
			$path = 'invoices';
		}

		return sprintf('https://dashboard.stripe.com%s/%s/%s', $route, $path, $gateway_payment_id);
	}

	/**
	 * Returns the external link to view the membership on the membership gateway.
	 *
	 * Return an empty string to hide the link element.
	 *
	 * @since 2.0.0
	 *
	 * @param string $gateway_subscription_id The gateway subscription id.
	 * @return string.
	 */
	public function get_subscription_url_on_gateway($gateway_subscription_id): string {

		$route = $this->test_mode ? '/test' : '/';

		return sprintf('https://dashboard.stripe.com%s/subscriptions/%s', $route, $gateway_subscription_id);
	}

	/**
	 * Returns the external link to view the customer on the gateway.
	 *
	 * Return an empty string to hide the link element.
	 *
	 * @since 2.0.7
	 *
	 * @param string $gateway_customer_id The gateway customer id.
	 * @return string.
	 */
	public function get_customer_url_on_gateway($gateway_customer_id): string {

		$route = $this->test_mode ? '/test' : '/';

		return sprintf('https://dashboard.stripe.com%s/customers/%s', $route, $gateway_customer_id);
	}

	/**
	 * @inheritdoc
	 * @since 2.0.0
	 */
	public function supports_payment_polling(): bool {

		return true;
	}

	/**
	 * Verify and complete a pending payment by checking Stripe directly.
	 *
	 * This is a fallback mechanism when webhooks are not working correctly.
	 * It checks the payment intent status on Stripe and completes the payment locally if successful.
	 *
	 * @since 2.x.x
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

		// Already completed - nothing to do
		if ($payment->get_status() === Payment_Status::COMPLETED) {
			return [
				'success' => true,
				'message' => __('Payment already completed.', 'ultimate-multisite'),
				'status'  => 'completed',
			];
		}

		// Only process pending payments
		if ($payment->get_status() !== Payment_Status::PENDING) {
			return [
				'success' => false,
				'message' => __('Payment is not in pending status.', 'ultimate-multisite'),
				'status'  => $payment->get_status(),
			];
		}

		// Get the payment intent ID from payment meta
		$payment_intent_id = $payment->get_meta('stripe_payment_intent_id');

		if (empty($payment_intent_id)) {
			return [
				'success' => false,
				'message' => __('No Stripe payment intent found for this payment.', 'ultimate-multisite'),
			];
		}

		try {
			$this->setup_api_keys();

			// Determine intent type and retrieve it
			if (str_starts_with((string) $payment_intent_id, 'seti_')) {
				$intent          = $this->get_stripe_client()->setupIntents->retrieve($payment_intent_id);
				$is_setup_intent = true;
				$is_succeeded    = 'succeeded' === $intent->status;
			} else {
				$intent          = $this->get_stripe_client()->paymentIntents->retrieve($payment_intent_id);
				$is_setup_intent = false;
				$is_succeeded    = 'succeeded' === $intent->status;
			}

			if (! $is_succeeded) {
				return [
					'success' => false,
					'message' => sprintf(
						// translators: %s is the intent status from Stripe.
						__('Payment intent status is: %s', 'ultimate-multisite'),
						$intent->status
					),
					'status'  => 'pending',
				];
			}

			// Payment succeeded on Stripe - complete it locally
			$gateway_payment_id = $is_setup_intent
				? $intent->id
				: ($intent->latest_charge ?? $intent->id);

			$payment->set_status(Payment_Status::COMPLETED);
			$payment->set_gateway($this->get_id());
			$payment->set_gateway_payment_id($gateway_payment_id);
			$payment->save();

			// Trigger payment processed
			$membership = $payment->get_membership();

			if ($membership) {
				$this->trigger_payment_processed($payment, $membership);
			}

			wu_log_add('stripe', sprintf('Payment %d completed via fallback verification (intent: %s)', $payment_id, $payment_intent_id));

			return [
				'success' => true,
				'message' => __('Payment verified and completed successfully.', 'ultimate-multisite'),
				'status'  => 'completed',
			];
		} catch (\Throwable $e) {
			wu_log_add('stripe', sprintf('Payment verification failed for payment %d: %s', $payment_id, $e->getMessage()), LogLevel::ERROR);

			return [
				'success' => false,
				'message' => $e->getMessage(),
			];
		}
	}

	/**
	 * Schedule a fallback payment verification job.
	 *
	 * This schedules an Action Scheduler job to verify the payment status
	 * if webhooks don't complete the payment in time.
	 *
	 * @since 2.x.x
	 *
	 * @param int $payment_id The payment ID to verify.
	 * @param int $delay_seconds How many seconds to wait before checking (default: 30).
	 * @return int|false The scheduled action ID or false on failure.
	 */
	public function schedule_payment_verification(int $payment_id, int $delay_seconds = 30) {

		$hook = 'wu_verify_stripe_payment';
		$args = [
			'payment_id' => $payment_id,
			'gateway_id' => $this->get_id(),
		];

		// Check if already scheduled
		if (wu_next_scheduled_action($hook, $args)) {
			return false;
		}

		$timestamp = time() + $delay_seconds;

		return wu_schedule_single_action($timestamp, $hook, $args, 'wu-stripe-verification');
	}
}

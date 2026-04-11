<?php
/**
 * PayPal Gateway (Legacy NVP API).
 *
 * This gateway uses the legacy PayPal NVP (Name-Value Pair) API with
 * username/password/signature authentication. This is maintained for
 * backwards compatibility with existing integrations.
 *
 * For new integrations, use PayPal_REST_Gateway which uses the modern
 * REST API with OAuth authentication.
 *
 * @package WP_Ultimo
 * @subpackage Gateways
 * @since 2.0.0
 */

namespace WP_Ultimo\Gateways;

use Psr\Log\LogLevel;
use WP_Ultimo\Gateways\Base_PayPal_Gateway;
use WP_Ultimo\Database\Payments\Payment_Status;
use WP_Ultimo\Database\Memberships\Membership_Status;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * PayPal Payments Gateway (Legacy NVP API)
 *
 * @since 2.0.0
 */
class PayPal_Gateway extends Base_PayPal_Gateway {

	/**
	 * @var string
	 */
	public $error_message;

	/**
	 * @var string
	 */
	public $webhook_event_id;

	/**
	 * @var \WP_Ultimo\Models\Payment
	 */
	public $payment;

	/**
	 * Holds the ID of a given gateway.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $id = 'paypal';

	/**
	 * Holds if we are in test mode.
	 *
	 * @since 2.0.0
	 * @var boolean
	 */
	protected $test_mode = true;

	/**
	 * The API endpoint. Depends on the test mode.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $api_endpoint;

	/**
	 * Checkout URL.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $checkout_url;

	/**
	 * PayPal username.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $username;

	/**
	 * PayPal password.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $password;

	/**
	 * PayPal signature.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $signature;

	/**
	 * Backwards compatibility for the old notify ajax url.
	 *
	 * @since 2.0.4
	 * @var bool|string
	 */
	protected $backwards_compatibility_v1_id = 'paypal';

	/**
	 * Checks if PayPal is properly configured with NVP credentials.
	 *
	 * @since 2.0.0
	 * @return bool
	 */
	public function is_configured(): bool {

		return ! empty($this->username) && ! empty($this->password) && ! empty($this->signature);
	}

	/**
	 * Returns the connection status for display in settings.
	 *
	 * @since 2.0.0
	 * @return array{connected: bool, message: string, details: array}
	 */
	public function get_connection_status(): array {

		$configured = $this->is_configured();

		return [
			'connected' => $configured,
			'message'   => $configured
				? __('PayPal credentials configured', 'ultimate-multisite')
				: __('PayPal credentials not configured', 'ultimate-multisite'),
			'details'   => [
				'mode'     => $this->test_mode ? 'sandbox' : 'live',
				'username' => ! empty($this->username) ? substr($this->username, 0, 10) . '...' : '',
			],
		];
	}

	/**
	 * Initialization code.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function init(): void {
		/*
		 * Checks if we are in test mode or not,
		 * based on the PayPal Setting.
		 * As the toggle return a string with a int value,
		 * we need to convert this first to int then to bool.
		 */
		$this->test_mode = (bool) (int) wu_get_setting('paypal_sandbox_mode', true);

		/*
		 * If we are in test mode
		 * use test mode keys.
		 */
		if ($this->test_mode) {
			$this->api_endpoint = 'https://api-3t.sandbox.paypal.com/nvp';
			$this->checkout_url = 'https://www.sandbox.paypal.com/webscr&cmd=_express-checkout&token=';

			$this->username  = wu_get_setting('paypal_test_username', '');
			$this->password  = wu_get_setting('paypal_test_password', '');
			$this->signature = wu_get_setting('paypal_test_signature', '');

			return;
		}

		/*
		 * Otherwise, set
		 * PayPal live keys.
		 */
		$this->api_endpoint = 'https://api-3t.paypal.com/nvp';
		$this->checkout_url = 'https://www.paypal.com/webscr&cmd=_express-checkout&token=';

		$this->username  = wu_get_setting('paypal_live_username', '');
		$this->password  = wu_get_setting('paypal_live_password', '');
		$this->signature = wu_get_setting('paypal_live_signature', '');
	}

	/**
	 * Adds the PayPal Gateway settings to the settings screen.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function settings(): void {

		wu_register_settings_field(
			'payment-gateways',
			'paypal_header',
			[
				'title'           => __('PayPal', 'ultimate-multisite'),
				'desc'            => __('Use the settings section below to configure PayPal Express as a payment method.', 'ultimate-multisite'),
				'type'            => 'header',
				'show_as_submenu' => true,
				'require'         => [
					'active_gateways' => 'paypal',
				],
			]
		);

		wu_register_settings_field(
			'payment-gateways',
			'paypal_sandbox_mode',
			[
				'title'     => __('PayPal Sandbox Mode', 'ultimate-multisite'),
				'desc'      => __('Toggle this to put PayPal on sandbox mode. This is useful for testing and making sure PayPal is correctly setup to handle your payments.', 'ultimate-multisite'),
				'type'      => 'toggle',
				'default'   => 0,
				'html_attr' => [
					'v-model' => 'paypal_sandbox_mode',
				],
				'require'   => [
					'active_gateways' => 'paypal',
				],
			]
		);

		wu_register_settings_field(
			'payment-gateways',
			'paypal_test_username',
			[
				'title'       => __('PayPal Test Username', 'ultimate-multisite'),
				'desc'        => '',
				'tooltip'     => __('Make sure you are placing the TEST username, not the live one.', 'ultimate-multisite'),
				'placeholder' => __('e.g. username_api1.username.co', 'ultimate-multisite'),
				'type'        => 'text',
				'default'     => '',
				'capability'  => 'manage_api_keys',
				'require'     => [
					'active_gateways'     => 'paypal',
					'paypal_sandbox_mode' => 1,
				],
			]
		);

		wu_register_settings_field(
			'payment-gateways',
			'paypal_test_password',
			[
				'title'       => __('PayPal Test Password', 'ultimate-multisite'),
				'desc'        => '',
				'tooltip'     => __('Make sure you are placing the TEST password, not the live one.', 'ultimate-multisite'),
				'placeholder' => __('e.g. IUOSABK987HJG88N', 'ultimate-multisite'),
				'type'        => 'text',
				'default'     => '',
				'capability'  => 'manage_api_keys',
				'require'     => [
					'active_gateways'     => 'paypal',
					'paypal_sandbox_mode' => 1,
				],
			]
		);

		wu_register_settings_field(
			'payment-gateways',
			'paypal_test_signature',
			[
				'title'       => __('PayPal Test Signature', 'ultimate-multisite'),
				'desc'        => '',
				'tooltip'     => __('Make sure you are placing the TEST signature, not the live one.', 'ultimate-multisite'),
				'placeholder' => __('e.g. AFcpSSRl31ADOdqnHNv4KZdVHEQzdMEEsWxV21C7fd0v3bYYYRCwYxqo', 'ultimate-multisite'),
				'type'        => 'text',
				'default'     => '',
				'capability'  => 'manage_api_keys',
				'require'     => [
					'active_gateways'     => 'paypal',
					'paypal_sandbox_mode' => 1,
				],
			]
		);

		wu_register_settings_field(
			'payment-gateways',
			'paypal_live_username',
			[
				'title'       => __('PayPal Live Username', 'ultimate-multisite'),
				'desc'        => '',
				'tooltip'     => __('Make sure you are placing the LIVE username, not the test one.', 'ultimate-multisite'),
				'placeholder' => __('e.g. username_api1.username.co', 'ultimate-multisite'),
				'type'        => 'text',
				'default'     => '',
				'capability'  => 'manage_api_keys',
				'require'     => [
					'active_gateways'     => 'paypal',
					'paypal_sandbox_mode' => 0,
				],
			]
		);

		wu_register_settings_field(
			'payment-gateways',
			'paypal_live_password',
			[
				'title'       => __('PayPal Live Password', 'ultimate-multisite'),
				'desc'        => '',
				'tooltip'     => __('Make sure you are placing the LIVE password, not the test one.', 'ultimate-multisite'),
				'placeholder' => __('e.g. IUOSABK987HJG88N', 'ultimate-multisite'),
				'type'        => 'text',
				'default'     => '',
				'capability'  => 'manage_api_keys',
				'require'     => [
					'active_gateways'     => 'paypal',
					'paypal_sandbox_mode' => 0,
				],
			]
		);

		wu_register_settings_field(
			'payment-gateways',
			'paypal_live_signature',
			[
				'title'       => __('PayPal Live Signature', 'ultimate-multisite'),
				'desc'        => '',
				'tooltip'     => __('Make sure you are placing the LIVE signature, not the test one.', 'ultimate-multisite'),
				'placeholder' => __('e.g. AFcpSSRl31ADOdqnHNv4KZdVHEQzdMEEsWxV21C7fd0v3bYYYRCwYxqo', 'ultimate-multisite'),
				'type'        => 'text',
				'default'     => '',
				'capability'  => 'manage_api_keys',
				'require'     => [
					'active_gateways'     => 'paypal',
					'paypal_sandbox_mode' => 0,
				],
			]
		);
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
			return new \WP_Error('wu_paypal_no_subscription_id', __('Error: No gateway subscription ID found for this membership.', 'ultimate-multisite'));
		}

		$original = $membership->_get_original();

		$has_duration_change = $membership->get_duration() !== absint(wu_get_isset($original, 'duration')) || $membership->get_duration_unit() !== wu_get_isset($original, 'duration_unit');

		if ($has_duration_change) {
			return new \WP_Error('wu_paypal_no_duration_change', __('Error: PayPal does not support changing the duration of a subscription.', 'ultimate-multisite'));
		}

		/**
		 * Generate a temporary wu payment so we can get the correct line items and amounts.
		 * It's important to note that we should only get recurring payments so we can correctly update the subscription.
		 */
		$temp_payment = wu_membership_create_new_payment($membership, false, true, false);

		$description = wu_get_setting('company_name', get_network_option(null, 'site_name')) . ': ' . implode(', ', array_map(fn($item) => 'x' . $item->get_quantity() . ' ' . $item->get_title(), $temp_payment->get_line_items()));

		$args = [
			'USER'      => $this->username,
			'PWD'       => $this->password,
			'SIGNATURE' => $this->signature,
			'VERSION'   => '124',
			'METHOD'    => 'UpdateRecurringPaymentsProfile',
			'PROFILEID' => $gateway_subscription_id,
			'NOTE'      => __('Membership update', 'ultimate-multisite'),
			'DESC'      => $description,
			'AMT'       => $temp_payment->get_total() - $temp_payment->get_tax_total(),
			'TAXAMT'    => $temp_payment->get_tax_total(),
		];

		$request = wp_remote_post(
			$this->api_endpoint,
			[
				'timeout'     => 45,
				'httpversion' => '1.1',
				'body'        => $args,
			]
		);

		/*
		 * Check for wp-error on the request call
		 *
		 * This will catch timeouts and similar errors.
		 * Maybe PayPal is out? We can't be sure.
		 */
		if (is_wp_error($request)) {
			return $request;
		}

		$body = wp_remote_retrieve_body($request);

		if (is_string($body)) {
			wp_parse_str($body, $body);
		}

		if ('failure' === strtolower((string) $body['ACK'])) {
			return new \WP_Error($body['L_ERRORCODE0'], __('PayPal Error:', 'ultimate-multisite') . ' ' . $body['L_LONGMESSAGE0']);
		}

		return true;
	}

	/**
	 * Process a checkout.
	 *
	 * It takes the data concerning
	 * a new checkout and process it.
	 *
	 * @param \WP_Ultimo\Models\Payment    $payment The payment associated with the checkout.
	 * @param \WP_Ultimo\Models\Membership $membership The membership.
	 * @param \WP_Ultimo\Models\Customer   $customer The customer checking out.
	 * @param \WP_Ultimo\Checkout\Cart     $cart The cart object.
	 * @param string                       $type The checkout type. Can be 'new', 'retry', 'upgrade', 'downgrade', 'addon'.
	 * @throws \Exception When PayPal API call fails or returns an error.
	 * @return void
	 * @throws \Exception If something goes really wrong.
	 * @since 2.0.0
	 */
	public function process_checkout($payment, $membership, $customer, $cart, $type): void {
		/*
		 * To make our lives easier, let's
		 * set a couple of variables based on the order.
		 */
		$initial_amount    = $payment->get_total();
		$should_auto_renew = $cart->should_auto_renew();
		$is_recurring      = $cart->has_recurring();
		$is_trial_setup    = $membership->is_trialing() && empty($payment->get_total());

		/*
		 * Get the amount depending on
		 * the auto-renew status.
		 */
		$amount = $should_auto_renew ? $payment->get_total() : $cart->get_recurring_total();

		/*
		 * Sets the cancel URL.
		 */
		$cancel_url = $this->get_cancel_url();

		/*
		 * Calculates the return URL
		 * for the intermediary return URL.
		 */
		$return_url = $this->get_confirm_url();

		/*
		 * Setup variables
		 *
		 * PayPal takes a ***load of variables.
		 * Some of them need to be prepped beforehand.
		 */
		$currency    = strtoupper((string) $payment->get_currency());
		$description = $this->get_subscription_description($cart);
		$notify_url  = $this->get_webhook_listener_url();

		/*
		 * This is a special key paypal lets us set.
		 * It contains the payment_id, membership_id and customer_id
		 * in the following format: payment_id|membership_id|customer_id
		 */
		$custom_key = sprintf('%s|%s|%s', $payment->get_id(), $membership->get_id(), $customer->get_id());

		/*
		 * Now we can build the PayPal
		 * request object, and append the products
		 * later.
		 */
		$args = [
			'USER'                           => $this->username,
			'PWD'                            => $this->password,
			'SIGNATURE'                      => $this->signature,
			'VERSION'                        => '124',
			'METHOD'                         => 'SetExpressCheckout',
			'PAYMENTREQUEST_0_SHIPPINGAMT'   => 0,
			'PAYMENTREQUEST_0_TAXAMT'        => 0,
			'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
			'PAYMENTREQUEST_0_AMT'           => 0,
			'PAYMENTREQUEST_0_ITEMAMT'       => 0,
			'PAYMENTREQUEST_0_CURRENCYCODE'  => $currency,
			'PAYMENTREQUEST_0_DESC'          => $description,
			'PAYMENTREQUEST_0_CUSTOM'        => $custom_key,
			'PAYMENTREQUEST_0_NOTIFYURL'     => $notify_url,
			'EMAIL'                          => $customer->get_email_address(),
			'CANCELURL'                      => $cancel_url,
			'NOSHIPPING'                     => 1,
			'REQCONFIRMSHIPPING'             => 0,
			'ALLOWNOTE'                      => 0,
			'ADDROVERRIDE'                   => 0,
			'PAGESTYLE'                      => '',
			'SOLUTIONTYPE'                   => 'Sole',
			'LANDINGPAGE'                    => 'Billing',
			'RETURNURL'                      => $return_url,
			'LOGOIMG'                        => wu_get_network_logo(),
		];

		$notes = [];

		if ($is_trial_setup) {
			$desc = $membership->get_recurring_description();

			$date = wp_date(get_option('date_format'), strtotime($membership->get_date_trial_end(), wu_get_current_time('timestamp', true)));
			// translators: %1$s is the date it will end
			$notes[] = sprintf(__('Your trial period will end on %1$s.', 'ultimate-multisite'), $date);
		}

		if ($is_recurring && $should_auto_renew) {
			$recurring_total = $cart->get_recurring_total();
			$cart_total      = $cart->get_total();

			$args['L_BILLINGAGREEMENTDESCRIPTION0'] = $description;
			$args['L_BILLINGTYPE0']                 = 'RecurringPayments';
			$args['MAXAMT']                         = $recurring_total > $cart_total ? $recurring_total : $cart_total;

			$desc = $membership->get_recurring_description();

			$recurring_total_format = wu_format_currency($recurring_total, $cart->get_currency());

			if ($recurring_total !== $cart_total) {
				if ('downgrade' === $type) {
					if ($is_trial_setup) {
						// translators: $1$s the date membership will start, $2$s amount to be billed.
						$notes[] = sprintf(__('Your updated membership will start on $1$s, from that date you will be billed %2$s every month.', 'ultimate-multisite'), $date, $recurring_total_format);
					} else {
						$date_renew = wp_date(get_option('date_format'), strtotime($membership->get_date_expiration(), wu_get_current_time('timestamp', true)));
						// translators: $1$s the date membership will start, $2$s amount to be billed, %3$s the description of how often.
						$notes[] = sprintf(__('Your updated membership will start on %1$s, from that date you will be billed %2$s %3$s.', 'ultimate-multisite'), $date_renew, $recurring_total_format, $desc);
					}
				} elseif ($is_trial_setup) {
					// translators: $1$s amount to be billed, $2$s how often
					$notes[] = sprintf(__('After the first payment you will be billed %1$s %2$s.', 'ultimate-multisite'), $recurring_total_format, $desc);
				} else {
					// translators: $1$s amount to be billed, $2$s how often
					$notes[] = sprintf(__('After this payment you will be billed %1$s %2$s.', 'ultimate-multisite'), $recurring_total_format, $desc);
				}
			} elseif ($is_trial_setup) {
					// translators: $1$s amount to be billed, $2$s how often
					$notes[] = sprintf(__('From that date, you will be billed %1$s %2$s.', 'ultimate-multisite'), $recurring_total_format, $desc);
			} else {
				// translators: $1$s how often
				$notes[] = sprintf(__('After this payment you will be billed %1$s.', 'ultimate-multisite'), $desc);
			}
		}

		$args['NOTETOBUYER'] = implode(' ', $notes);

		/*
		 * After that, we need to add the additional
		 * products.
		 */
		$product_index = 0;

		/*
		 * Loop products and add them to the paypal
		 */
		foreach ($cart->get_line_items() as $line_item) {
			$total      = $line_item->get_total();
			$sub_total  = $line_item->get_subtotal();
			$tax_amount = $line_item->get_tax_total();

			$product_args = [
				"L_PAYMENTREQUEST_0_NAME{$product_index}" => $line_item->get_title(),
				"L_PAYMENTREQUEST_0_DESC{$product_index}" => $line_item->get_description(),
				"L_PAYMENTREQUEST_0_AMT{$product_index}"  => $sub_total,
				"L_PAYMENTREQUEST_0_QTY{$product_index}"  => $line_item->get_quantity(),
				"L_PAYMENTREQUEST_0_TAXAMT{$product_index}" => $tax_amount,
			];

			$args['PAYMENTREQUEST_0_ITEMAMT'] += $sub_total;
			$args['PAYMENTREQUEST_0_TAXAMT']  += $tax_amount;
			$args['PAYMENTREQUEST_0_AMT']      = $args['PAYMENTREQUEST_0_AMT'] + $sub_total + $tax_amount;

			$args = array_merge($args, $product_args);

			++$product_index;
		}

		$discounts_total = $cart->get_total_discounts();

		if ( ! empty($discounts_total)) {
			__('Account credit and other discounts', 'ultimate-multisite');

			$args = array_merge(
				$args,
				[
					"L_PAYMENTREQUEST_0_NAME{$product_index}" => __('Account credit and other discounts', 'ultimate-multisite'),
					"L_PAYMENTREQUEST_0_AMT{$product_index}"  => $discounts_total,
					"L_PAYMENTREQUEST_0_QTY{$product_index}"  => 1,
				]
			);

			$args['PAYMENTREQUEST_0_ITEMAMT'] += $discounts_total;
			$args['PAYMENTREQUEST_0_AMT']     += $discounts_total;

			++$product_index;
		}

		$request = wp_remote_post(
			$this->api_endpoint,
			[
				'timeout'     => 45,
				'httpversion' => '1.1',
				'body'        => $args,
			]
		);

		$body    = wp_remote_retrieve_body($request);
		$code    = wp_remote_retrieve_response_code($request);
		$message = wp_remote_retrieve_response_message($request);

		// Add multiple items: https://stackoverflow.com/questions/31957791/paypal-subscription-for-multiple-product-using-paypal-api

		/*
		 * Check for wp-error on the request call
		 *
		 * This will catch timeouts and similar errors.
		 * Maybe PayPal is out? We can't be sure.
		 */
		if (is_wp_error($request)) {
			throw new \Exception(esc_html($request->get_error_message()), esc_html($request->get_error_code()));
		}

		/*
		 * If we get here, we got a 200.
		 * This means we got a valid response from
		 * PayPal.
		 *
		 * Now we need to check for a valid token to
		 * redirect the customer to the checkout page.
		 */
		if (200 === absint($code) && 'OK' === $message) {
			/*
			 * PayPal gives us a URL-formatted string
			 * Urrrrgh! Let's parse it.
			 */
			if (is_string($body)) {
				wp_parse_str($body, $body);
			}

			if ('failure' === strtolower((string) $body['ACK']) || 'failurewithwarning' === strtolower((string) $body['ACK'])) {
				wp_die(esc_html($body['L_LONGMESSAGE0']), esc_html($body['L_ERRORCODE0']));
			} else {
				/*
				 * We do have a valid token.
				 *
				 * Redirect to the PayPal checkout URL.
				 */
				wp_redirect($this->checkout_url . $body['TOKEN']); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect -- PayPal external URL

				exit;
			}
		}

		/*
		 * If we get here, something went wrong.
		 */
		throw new \Exception(esc_html__('Something has gone wrong, please try again', 'ultimate-multisite'));
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
	public function process_cancellation($membership, $customer): void {

		$profile_id = $membership->get_gateway_subscription_id();

		$args = [
			'USER'      => $this->username,
			'PWD'       => $this->password,
			'SIGNATURE' => $this->signature,
			'VERSION'   => '124',
			'METHOD'    => 'ManageRecurringPaymentsProfileStatus',
			'PROFILEID' => $profile_id,
			'ACTION'    => 'Cancel',
		];

		$request = wp_remote_post(
			$this->api_endpoint,
			[
				'timeout'     => 45,
				'httpversion' => '1.1',
				'body'        => $args,
			]
		);
	}

	/**
	 * Process a checkout.
	 *
	 * It takes the data concerning
	 * a refund and process it.
	 *
	 * @since 2.0.0
	 *
	 * @throws \Exception                  When something goes wrong.
	 *
	 * @param float                        $amount The amount to refund.
	 * @param \WP_Ultimo\Models\Payment    $payment The payment associated with the checkout.
	 * @param \WP_Ultimo\Models\Membership $membership The membership.
	 * @param \WP_Ultimo\Models\Customer   $customer The customer checking out.
	 * @return void|bool
	 */
	public function process_refund($amount, $payment, $membership, $customer) {

		$gateway_payment_id = $payment->get_gateway_payment_id();

		if (empty($gateway_payment_id)) {
			throw new \Exception(esc_html__('Gateway payment ID not found. Cannot process refund automatically.', 'ultimate-multisite'));
		}

		$refund_type = 'Partial';

		if ($amount >= $payment->get_total()) {
			$refund_type = 'Full';
		}

		$amount_formatted = number_format($amount, 2);

		$args = [
			'USER'          => $this->username,
			'PWD'           => $this->password,
			'SIGNATURE'     => $this->signature,
			'VERSION'       => '124',
			'METHOD'        => 'RefundTransaction',
			'REFUND_TYPE'   => $refund_type,
			'TRANSACTIONID' => $gateway_payment_id,
			'INVOICEID'     => $payment->get_hash(),
		];

		if ('Partial' === $refund_type) {
			$args['AMT'] = $amount_formatted;
		}

		$request = wp_remote_post(
			$this->api_endpoint,
			[
				'timeout'     => 45,
				'httpversion' => '1.1',
				'body'        => $args,
			]
		);

		$body    = wp_remote_retrieve_body($request);
		$code    = wp_remote_retrieve_response_code($request);
		$message = wp_remote_retrieve_response_message($request);

		if (is_wp_error($request)) {
			throw new \Exception(esc_html($request->get_error_message()));
		}

		if (200 === absint($code) && 'OK' === $message) {
			/*
			 * PayPal gives us a URL-formatted string
			 * Urrrrgh! Let's parse it.
			 */
			if (is_string($body)) {
				wp_parse_str($body, $body);
			}

			if ('failure' === strtolower((string) $body['ACK'])) {
				throw new \Exception(esc_html($body['L_LONGMESSAGE0']));
			}

			/*
			 * All good.
			 */
			return true;
		}

		throw new \Exception(esc_html__('Something went wrong.', 'ultimate-multisite'));
	}

	/**
	 * Adds additional fields to the checkout form for a particular gateway.
	 *
	 * In this method, you can either return an array of fields (that we will display
	 * using our form display methods) or you can return plain HTML in a string,
	 * which will get outputted to the gateway section of the checkout.
	 *
	 * @since 2.0.0
	 * @return mixed[]|string
	 */
	public function fields() {

		$message = __('You will be redirected to PayPal to complete the purchase.', 'ultimate-multisite');

		return sprintf('<p class="wu-p-4 wu-bg-yellow-200">%s</p>', $message);
	}

	/**
	 * Process confirmation.
	 *
	 * Some gateways require user confirmation at some point.
	 * It's the case for PayPal Express, for example.
	 * This method implements the necessary things.
	 *
	 * After a successful payment, redirect to $this->return_url.
	 *
	 * @access public
	 * @return void
	 */
	public function process_confirmation(): void {
		/*
		 * Tries to retrieve the nonce, this part is necessary due EU SCA Compliancy.
		 */
		$nonce = wu_request('wu_ppe_confirm_nonce', 'no-nonce');

		/*
		 * If the nonce is present and is valid,
		 * we can be sure we have the data we need to process a confirmation
		 * screen. Here we actually finish the payment
		 * and/or create the subscription.
		 */
		if (wp_verify_nonce($nonce, 'wu-ppe-confirm-nonce')) {
			/*
			* Retrieve the payment details, base on the token.
			*/
			$details = $this->get_checkout_details(wu_request('token'));

			if (empty($details)) {
				wp_die(esc_html__('PayPal token no longer valid.', 'ultimate-multisite'));
			}

			/*
			* Tries to get the payment based on the request
			*/
			$payment_id = absint(wu_request('payment-id'));
			$payment    = $payment_id ? wu_get_payment($payment_id) : wu_get_payment_by_hash(wu_request('payment'));

			/*
			* The pending payment does not exist...
			* Bail.
			*/
			if (empty($payment)) {
				wp_die(esc_html__('Pending payment does not exist.', 'ultimate-multisite'));
			}

			/*
			* Now we need to original cart.
			*
			* The original cart gets saved with the original
			* payment. Otherwise, we bail.
			*/
			$original_cart = $payment->get_meta('wu_original_cart');

			if (empty($original_cart)) {
				wp_die(esc_html__('Original cart does not exist.', 'ultimate-multisite'));
			}

			/*
			* Set the variables
			*/
			$membership        = $payment->get_membership();
			$customer          = $payment->get_customer();
			$should_auto_renew = $original_cart->should_auto_renew();
			$is_recurring      = $original_cart->has_recurring();

			if (empty($membership) || empty($customer)) {
				$error = new \WP_Error('no-membership', esc_html__('Missing membership or customer data.', 'ultimate-multisite'));

				wp_die($error); // phpcs:ignore WordPress.Security.EscapeOutput
			}

			if ($should_auto_renew && $is_recurring) {
				/*
				* We need to create the payment profile.
				* As this is a recurring payment and the
				* auto-renew option is active.
				*/
				$this->create_recurring_profile($details, $original_cart, $payment, $membership, $customer);
			} else {
				/*
				* Otherwise, process
				* single payment.
				*/
				$this->complete_single_payment($details, $original_cart, $payment, $membership, $customer);
			}
		} elseif ( ! empty(wu_request('token'))) {

			// Prints the form
			$this->confirmation_form();
		}
	}

	/**
	 * Process webhooks
	 *
	 * @since 2.0.0
	 * @throws \Exception When transaciton id is missing.
	 */
	public function process_webhooks(): bool {

		wu_log_add('paypal', 'Receiving PayPal IPN webhook...');

		$allowed_ipn_fields = [
			'custom',
			'recurring_payment_id',
			'mc_gross',
			'payment_date',
			'txn_id',
			'txn_type',
			'initial_payment_txn_id',
			'initial_payment_status',
			'ipn_track_id',
			'time_created',
			'amount',
			'next_payment_date',
			'payment_status',
			'pending_reason',
		];

		$posted = [];

		foreach ($allowed_ipn_fields as $field) {
			// Not possible to use a nonce with this webhook but verify_ipn() is used below to confirm the request came from PayPal.
			if (isset($_POST[ $field ])) { // phpcs:ignore WordPress.Security.NonceVerification
				$posted[ $field ] = sanitize_text_field(wp_unslash($_POST[ $field ])); // phpcs:ignore WordPress.Security.NonceVerification
			}
		}

		$posted = apply_filters('wu_ipn_post', $posted);

		// Verify the IPN actually came from PayPal to prevent spoofing attacks
		if (! $this->verify_ipn($posted)) {
			wu_log_add('paypal', 'PayPal IPN verification failed - possible fraud attempt. Rejecting IPN.', LogLevel::ERROR);
			http_response_code(400);
			wp_die('IPN verification failed');
		}

		$payment    = false;
		$customer   = false;
		$membership = false;

		$custom = ! empty($posted['custom']) ? explode('|', (string) $posted['custom']) : [];

		if (is_array($custom) && ! empty($custom)) {
			$payment    = wu_get_payment(absint($custom[0]));
			$membership = wu_get_payment(absint($custom[1]));
			$customer   = wu_get_payment(absint($custom[2]));
		}

		if ( ! empty($posted['recurring_payment_id'])) {
			$membership = wu_get_membership_by('gateway_subscription_id', $posted['recurring_payment_id']);
		}

		if (empty($membership)) {
			throw new \Exception(esc_html__('Exiting PayPal Express IPN - membership ID not found.', 'ultimate-multisite'));
		}

		wu_log_add('paypal', sprintf('Processing IPN for membership #%d.', $membership->get_id()));

		/*
		 * Base payment data for update
		 * or insertion.
		 */
		$payment_data = [
			'status'        => Payment_Status::COMPLETED,
			'customer_id'   => $membership->get_customer_id(),
			'membership_id' => $membership->get_id(),
			'gateway'       => $this->id,
		];

		$amount = isset($posted['mc_gross']) ? wu_to_float($posted['mc_gross']) : false;

		if (false !== $amount) {
			$payment_data['amount'] = $amount;
		}

		if ( ! empty($posted['payment_date'])) {
			$payment_data['date'] = date('Y-m-d H:i:s', strtotime((string) $posted['payment_date'])); // phpcs:ignore
		}

		if ( ! empty($posted['txn_id'])) {
			$payment_data['gateway_payment_id'] = sanitize_text_field($posted['txn_id']);
		}

		/*
		 * Deal with each transaction type
		 * accordingly
		 */
		switch ($posted['txn_type']) :
			/*
			 * New recurring profile, aka paypal subscription created.
			 */
			case 'recurring_payment_profile_created':
				/*
				 * Log
				 */
				wu_log_add('paypal', 'Processing PayPal Express recurring_payment_profile_created IPN.');

				/*
				 * Get the gateway payment ID.
				 *
				 * We'll use this to try to localize the pending
				 * payment and make sure we have a match.
				 */
				if (isset($posted['initial_payment_txn_id'])) {
					$transaction_id = ('Completed' === $posted['initial_payment_status']) ? $posted['initial_payment_txn_id'] : '';
				} else {
					$transaction_id = $posted['ipn_track_id'];
				}

				if (empty($transaction_id)) {
					throw new \Exception('Breaking out of PayPal Express IPN recurring_payment_profile_created. Transaction ID not given.');
				}

				// setup the payment info in an array for storage
				$payment_data['date']               = date('Y-m-d H:i:s', strtotime($posted['time_created'])); // phpcs:ignore
				$payment_data['amount']             = wu_to_float($posted['amount']);
				$payment_data['gateway_payment_id'] = sanitize_text_field($transaction_id);

				$payment = wu_get_payment_by('gateway_payment_id', $payment_data['gateway_payment_id']);

				$should_use_subscription = ! $payment && isset($posted['recurring_payment_id']);

				$payment = $should_use_subscription ? wu_get_payment_by('gateway_payment_id', sanitize_text_field($posted['recurring_payment_id'])) : $payment;

				/*
				 * In the case that the payment
				 * already exists, update it.
				 */
				if ($payment) {
					$payment->attributes($payment_data);

					$payment->save();
				} else {
					/*
					 * Payment does not exist. Create it and renew the membership.
					 */
					$temp_membership = clone $membership;

					$temp_membership->set_amount($payment_data['amount']);

					$payment = wu_membership_create_new_payment($temp_membership, false, true, false);

					$payment->attributes($payment_data);

					$payment->save();
				}

				$is_trial_setup = $membership->is_trialing() && empty($payment->get_total());
				$status         = $is_trial_setup ? Membership_Status::TRIALING : Membership_Status::ACTIVE;

				$expiration = date('Y-m-d 23:59:59', strtotime((string) $posted['next_payment_date'])); // phpcs:ignore

				/*
				 * Tell the gateway to do their stuff.
				 */
				$this->trigger_payment_processed($payment, $membership);

				/**
				 * Renewals the membership
				 */
				$membership->add_to_times_billed(1);
				$membership->renew(true, $status, $expiration);

				break;

			case 'recurring_payment':
				wu_log_add('paypal', 'Processing PayPal Express recurring_payment IPN.');

				if ('failed' === strtolower((string) $posted['payment_status'])) {

					// Recurring payment failed.
					// translators: %s: Transaction ID
					$membership->add_note(['text' => sprintf(__('Transaction ID %s failed in PayPal.', 'ultimate-multisite'), $posted['txn_id'])]);

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

					die('Subscription payment failed');
				} elseif ('pending' === strtolower((string) $posted['payment_status'])) {

					// Recurring payment pending (such as echeck).
					$pending_reason = ! empty($posted['pending_reason']) ? $posted['pending_reason'] : __('unknown', 'ultimate-multisite');
					// translators: %1$s: Transaction ID, %2$s: Pending reason
					$membership->add_note(['text' => sprintf(__('Transaction ID %1$s is pending in PayPal for reason: %2$s', 'ultimate-multisite'), $posted['txn_id'], $pending_reason)]);

					die('Subscription payment pending');
				}

				$payment_data['transaction_type'] = 'renewal';

				$payment = wu_get_payment_by('gateway_payment_id', $payment_data['gateway_payment_id']);

				/*
				 * In the case that the payment
				 * already exists, update it.
				 */
				if ($payment) {
					$payment->attributes($payment_data);

					$payment->save();
				} else {
					/*
					 * Payment does not exist. Create it and renew the membership.
					 */
					$payment = wu_create_payment($payment_data);

					$membership->add_to_times_billed(1);
				}

				$is_trial_setup = $membership->is_trialing() && empty($payment->get_total());

				if ( ! $is_trial_setup) {
					$membership->renew(true);
				} else {
					$membership->save();
				}

				break;

			case 'recurring_payment_profile_cancel':
				wu_log_add('paypal', 'Processing PayPal Express recurring_payment_profile_cancel IPN.');

				if (isset($posted['initial_payment_status']) && 'Failed' === $posted['initial_payment_status']) {

					// Initial payment failed, so set the user back to pending.
					$membership->set_status('pending');

					$membership->add_note(['text' => __('Initial payment failed in PayPal Express.', 'ultimate-multisite')]);

					$this->error_message = __('Initial payment failed.', 'ultimate-multisite');
				} else {

					// If this is a completed payment plan, we can skip any cancellation actions. This is handled in renewals.
					if ($membership->has_payment_plan() && $membership->at_maximum_renewals()) {
						wu_log_add('paypal', sprintf('Membership #%d has completed its payment plan - not cancelling.', $membership->get_id()));

						die('membership payment plan completed');
					}

					// user is marked as cancelled but retains access until end of term
					$membership->cancel();

					$membership->add_note(['text' => __('Membership cancelled via PayPal Express IPN.', 'ultimate-multisite')]);
				}

				break;

			case 'recurring_payment_failed':
			case 'recurring_payment_suspended_due_to_max_failed_payment': // Same case as before
				wu_log_add('paypal', 'Processing PayPal Express recurring_payment_failed or recurring_payment_suspended_due_to_max_failed_payment IPN.');

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

				if ( ! in_array($membership->get_status(), ['cancelled', 'expired'], true)) {
					$membership->set_status('expired');
				}

				if ( ! empty($posted['txn_id'])) {
					$this->webhook_event_id = sanitize_text_field($posted['txn_id']);
				} elseif ( ! empty($posted['ipn_track_id'])) {
					$this->webhook_event_id = sanitize_text_field($posted['ipn_track_id']);
				}

				break;

			case 'web_accept':
				wu_log_add('paypal', sprintf('Processing PayPal Express web_accept IPN. Payment status: %s', $posted['payment_status']));

				switch (strtolower((string) $posted['payment_status'])) :
					case 'completed':
						if (empty($payment_data['gateway_payment_id'])) {
							throw new \Exception('Breaking out of PayPal Express IPN recurring_payment_profile_created. Transaction ID not given.');
						}

						$payment = wu_get_payment_by('gateway_payment_id', $payment_data['gateway_payment_id']);

						/*
						* In the case that the payment
						* already exists, update it.
						*/
						if ($payment) {
							$payment->attributes($payment_data);

							$payment->save();

							/*
							* Payment does not exist. Create it and renew the membership.
							*/
						} else {
							wu_create_payment($payment_data);

							$membership->add_to_times_billed(1);
						}

						// Membership was already activated.

						break;

					case 'denied':  // all the same case
					case 'expired': // all the same case
					case 'failed':  // all the same case
					case 'voided':  // all the same case
						wu_log_add('paypal', sprintf('Membership #%d is not active - not cancelling account.', $membership->get_id()));

						/*
						 * Cancel active memberships.
						 */
						if ($membership->is_active()) {
							$membership->cancel();
						} else {
							wu_log_add('paypal', sprintf('Membership #%d is not active - not cancelling account.', $membership->get_id()));
						}

						break;
				endswitch;

				break;
		endswitch;

		return true;
	}

	/**
	 * Create a recurring profile.
	 *
	 * @since 2.0.0
	 *
	 * @param array                        $details The PayPal transaction details.
	 * @param \WP_Ultimo\Checkout\Cart     $cart The cart object.
	 * @param \WP_Ultimo\Models\Payment    $payment The payment associated with the checkout.
	 * @param \WP_Ultimo\Models\Membership $membership The membership.
	 * @param \WP_Ultimo\Models\Customer   $customer The customer checking out.
	 * @return void
	 */
	protected function create_recurring_profile($details, $cart, $payment, $membership, $customer) {

		$args = [
			'USER'                => $this->username,
			'PWD'                 => $this->password,
			'SIGNATURE'           => $this->signature,
			'VERSION'             => '124',
			'TOKEN'               => wu_request('token'),
			'METHOD'              => 'CreateRecurringPaymentsProfile',
			'PROFILESTARTDATE'    => date('Y-m-d\TH:i:s', strtotime('+' . $cart->get_duration() . ' ' . $cart->get_duration_unit(), wu_get_current_time('timestamp', true))), // phpcs:ignore
			'BILLINGPERIOD'       => ucwords($cart->get_duration_unit()),
			'BILLINGFREQUENCY'    => $cart->get_duration(),
			'AMT'                 => $cart->get_recurring_total(),
			'INITAMT'             => $payment->get_total(),
			'CURRENCYCODE'        => strtoupper($cart->get_currency()),
			'FAILEDINITAMTACTION' => 'CancelOnFailure',
			'L_BILLINGTYPE0'      => 'RecurringPayments',
			'DESC'                => $this->get_subscription_description($cart),
			'BUTTONSOURCE'        => 'WP_Ultimo',
		];

		if ($args['INITAMT'] < 0) {
			unset($args['INITAMT']);
		}

		$is_trial_setup = $membership->is_trialing() && empty($payment->get_total());

		if ($is_trial_setup) {
			$trial_end = strtotime($membership->get_date_trial_end(), wu_get_current_time('timestamp', true));

			$args['PROFILESTARTDATE']        = date('Y-m-d\TH:i:s', $trial_end); // phpcs:ignore
			$args['TRIALBILLINGPERIOD']      = 'Day';
			$args['TRIALBILLINGFREQUENCY']   = floor(($trial_end - time()) / 86400);
			$args['TRIALAMT']                = $membership->get_initial_amount();
			$args['TRIALTOTALBILLINGCYCLES'] = 1;
		}

		if ( ! $membership->is_forever_recurring()) {
			$args['TOTALBILLINGCYCLES'] = $membership->get_billing_cycles() - $membership->get_times_billed();
		}

		$request = wp_remote_post(
			$this->api_endpoint,
			[
				'timeout'     => 45,
				'httpversion' => '1.1',
				'body'        => $args,
			]
		);

		if (is_wp_error($request)) {
			wp_die(esc_html($request->get_error_message()));
		}

		$body    = wp_remote_retrieve_body($request);
		$code    = wp_remote_retrieve_response_code($request);
		$message = wp_remote_retrieve_response_message($request);

		if (200 === absint($code) && 'OK' === $message) {
			/*
			 * PayPal gives us a URL-formatted string
			 * Urrrrgh! Let's parse it.
			 */
			if (is_string($body)) {
				wp_parse_str($body, $body);
			}

			if ('failure' === strtolower((string) $body['ACK'])) {
				wp_die(esc_html($body['L_LONGMESSAGE0']), esc_html($body['L_ERRORCODE0']));
			} else {
				/*
				 * We were successful, let's update
				 * the payment.
				 *
				 * First, set the value
				 * and the transaction ID.
				 */
				$transaction_id = $body['TRANSACTIONID'] ?? '';
				$profile_status = $body['PROFILESTATUS'] ?? '';

				// If TRANSACTIONID is not passed we need to wait for webhook
				$payment_status = Payment_Status::PENDING;

				if ( ! empty($transaction_id) || 'ActiveProfile' === $profile_status || $is_trial_setup) {
					$payment_status = Payment_Status::COMPLETED;
				}

				/**
				 * If we don't have a transaction ID, let's use the profile ID.
				 */
				$transaction_id = empty($transaction_id) && ! empty($body['PROFILEID']) ? $body['PROFILEID'] : $transaction_id;

				$payment_data = [
					'gateway_payment_id' => $transaction_id,
					'status'             => $payment_status,
				];

				/*
				 * Update local payment.
				 *
				 * This will add the transaction id,
				 * if we have it already, and mark it as
				 * complete.
				 *
				 * If we have a pending membership,
				 * and a pending site, for example,
				 * those will be marked as active.
				 */
				$payment->attributes($payment_data);
				$payment->save();

				$membership = $payment->get_membership();
				$membership->set_gateway_subscription_id($body['PROFILEID']);
				$membership->set_gateway_customer_id($details['PAYERID']);
				$membership->set_gateway('paypal');

			if (Payment_Status::COMPLETED === $payment_status) {
				$membership->add_to_times_billed(1);

				/*
				 * Lets deal with upgrades, downgrades and addons
				 *
				 * Here, we just need to make sure we process
				 * a membership swap.
				 */
				if ('upgrade' === $cart->get_cart_type() || 'addon' === $cart->get_cart_type()) {
					$membership->swap($cart);

					$membership->renew(true);
				} elseif ('downgrade' === $cart->get_cart_type()) {
					$membership->set_auto_renew(true);

					$membership->schedule_swap($cart);

					$membership->save();
				} elseif ('reactivation' === $cart->get_cart_type()) {
					/*
					 * Use reactivate() for reactivation carts so that the
					 * wu_membership_pre_reactivate / wu_membership_post_reactivate
					 * hooks fire and cancellation metadata is cleared correctly.
					 *
					 * Gate the success redirect on a successful transition to
					 * prevent charged-but-inactive memberships.
					 *
					 * @since 2.5.0
					 */
					$result = $membership->reactivate(true);

					if (true !== $result) {
						$error_msg = is_wp_error($result) ? $result->get_error_message() : __('Membership reactivation failed.', 'ultimate-multisite');

						wu_log_add('paypal', sprintf('Reactivation failed for membership %d: %s', $membership->get_id(), $error_msg));

						$this->redirect_with_error($error_msg);

						return;
					}
				} elseif ( ! $is_trial_setup) {
					$membership->renew(true);
				} else {
					$membership->save();
				}
			} else {
				$membership->save();
			}

			$this->payment = $payment;
			$redirect_url  = $this->get_return_url();

			wp_safe_redirect($redirect_url);

			exit;
		}
		} else {
			wp_die(
				esc_html__('Something has gone wrong, please try again', 'ultimate-multisite'),
				esc_html__('Error', 'ultimate-multisite'),
				[
					'back_link' => true,
					'response'  => '401',
				]
			);
		}
	}

	/**
	 * Create a single payment on PayPal.
	 *
	 * @since 2.0.0
	 *
	 * @param array                        $details The PayPal transaction details.
	 * @param \WP_Ultimo\Checkout\Cart     $cart The cart object.
	 * @param \WP_Ultimo\Models\Payment    $payment The payment associated with the checkout.
	 * @param \WP_Ultimo\Models\Membership $membership The membership.
	 * @param \WP_Ultimo\Models\Customer   $customer The customer checking out.
	 * @return void
	 */
	protected function complete_single_payment($details, $cart, $payment, $membership, $customer) {

		// One time payment
		$args = [
			'USER'                           => $this->username,
			'PWD'                            => $this->password,
			'SIGNATURE'                      => $this->signature,
			'VERSION'                        => '124',
			'METHOD'                         => 'DoExpressCheckoutPayment',
			'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
			'TOKEN'                          => wu_request('token'),
			'PAYERID'                        => wu_request('payer_id'),
			'PAYMENTREQUEST_0_AMT'           => $details['AMT'],
			'PAYMENTREQUEST_0_ITEMAMT'       => $details['AMT'],
			'PAYMENTREQUEST_0_SHIPPINGAMT'   => 0,
			'PAYMENTREQUEST_0_TAXAMT'        => 0,
			'PAYMENTREQUEST_0_CURRENCYCODE'  => $details['CURRENCYCODE'],
			'BUTTONSOURCE'                   => 'WP_Ultimo',
		];

		$request = wp_remote_post(
			$this->api_endpoint,
			[
				'timeout'     => 45,
				'httpversion' => '1.1',
				'body'        => $args,
			]
		);

		/*
			* Retrieve the results of
			* the API call to PayPal
			*/
		$body    = wp_remote_retrieve_body($request);
		$code    = wp_remote_retrieve_response_code($request);
		$message = wp_remote_retrieve_response_message($request);

		if (is_wp_error($request)) {
			wp_die(esc_html($request->get_error_message()));
		}

		if (200 === absint($code) && 'OK' === $message) {
			if (is_string($body)) {
				wp_parse_str($body, $body);
			}

			if ('failure' === strtolower((string) $body['ACK'])) {
				wp_die(esc_html($body['L_LONGMESSAGE0']), esc_html($body['L_ERRORCODE0']));
			} else {
				/*
					* We were successful, let's update
					* the payment.
					*
					* First, set the value
					* and the transaction ID.
					*/
				$transaction_id = $body['PAYMENTINFO_0_TRANSACTIONID'];

				$payment_data = [
					'gateway_payment_id' => $transaction_id,
					'status'             => Payment_Status::COMPLETED,
				];

				/*
					* Update local payment.
					*
					* This will add the transaction id,
					* if we have it already, and mark it as
					* complete.
					*
					* If we have a pending membership,
					* and a pending site, for example,
					* those will be marked as active.
					*/
				$payment->attributes($payment_data);
				$payment->save();

				$membership = $payment->get_membership();

				if ($cart->get_total() > 0) {
					$membership->add_to_times_billed(1);
				}

				$is_trial_setup = $membership->is_trialing() && empty($payment->get_total());

			/*
			 * Lets deal with upgrades, downgrades and addons
			 *
			 * Here, we just need to make sure we process
			 * a membership swap.
			 */
			if ('upgrade' === $cart->get_cart_type() || 'addon' === $cart->get_cart_type()) {
				$membership->swap($cart);

				$membership->renew(false);
			} elseif ('downgrade' === $cart->get_cart_type()) {
				$membership->schedule_swap($cart);

				$membership->save();
			} elseif ('reactivation' === $cart->get_cart_type()) {
				/*
				 * Use reactivate() for reactivation carts so that the
				 * wu_membership_pre_reactivate / wu_membership_post_reactivate
				 * hooks fire and cancellation metadata is cleared correctly.
				 *
				 * Gate the success redirect on a successful transition to
				 * prevent charged-but-inactive memberships.
				 *
				 * @since 2.5.0
				 */
				$result = $membership->reactivate(false);

				if (true !== $result) {
					$error_msg = is_wp_error($result) ? $result->get_error_message() : __('Membership reactivation failed.', 'ultimate-multisite');

					wu_log_add('paypal', sprintf('Reactivation failed for membership %d (IPN): %s', $membership->get_id(), $error_msg));

					$this->redirect_with_error($error_msg);

					return;
				}
			} elseif ( ! $is_trial_setup) {
				$membership->renew(false);
			} else {
				$membership->save();
			}

			$this->payment = $payment;
				$redirect_url  = $this->get_return_url();

				wp_safe_redirect($redirect_url);

				exit;
			}
		} else {
			wp_die(
				esc_html__('Something has gone wrong, please try again', 'ultimate-multisite'),
				esc_html__('Error', 'ultimate-multisite'),
				[
					'back_link' => true,
					'response'  => '401',
				]
			);
		}
	}

	/**
	 * Display the confirmation form.
	 *
	 * @since 2.1
	 * @return void
	 */
	public function confirmation_form() {

		$token = sanitize_text_field(wu_request('token'));

		$checkout_details = $this->get_checkout_details($token);

		if ( ! is_array($checkout_details)) {
			$error = is_wp_error($checkout_details) ? $checkout_details->get_error_message() : __('Invalid response code from PayPal', 'ultimate-multisite');

			// translators: %s is the paypal error message.
			echo '<p>' . sprintf(esc_html__('An unexpected PayPal error occurred. Error message: %s.', 'ultimate-multisite'), esc_html($error)) . '</p>';
		}

		/*
		 * Compiles the necessary elements.
		 */
		$customer = $checkout_details['pending_payment']->get_customer(); // current customer

		wu_get_template(
			'checkout/paypal/confirm',
			[
				'checkout_details' => $checkout_details,
				'customer'         => $customer,
				'payment'          => $checkout_details['pending_payment'],
				'membership'       => $checkout_details['pending_payment']->get_membership(),
			]
		);
	}

	/**
	 * Get checkout details.
	 *
	 * @param string $token PayPal token.
	 * @return mixed[]|bool|string|\WP_Error
	 */
	public function get_checkout_details($token = '') {

		$args = [
			'TOKEN'     => $token,
			'USER'      => $this->username,
			'PWD'       => $this->password,
			'SIGNATURE' => $this->signature,
			'VERSION'   => '124',
			'METHOD'    => 'GetExpressCheckoutDetails',
		];

		$request = wp_remote_post(
			$this->api_endpoint,
			[
				'timeout'     => 45,
				'httpversion' => '1.1',
				'body'        => $args,
			]
		);

		$body    = wp_remote_retrieve_body($request);
		$code    = wp_remote_retrieve_response_code($request);
		$message = wp_remote_retrieve_response_message($request);

		if (is_wp_error($request)) {
			return $request;
		} elseif (200 === absint($code) && 'OK' === $message) {
			if (is_string($body)) {
				wp_parse_str($body, $body);
			}

			$payment_id = absint(wu_request('payment-id'));

			$pending_payment = $payment_id ? wu_get_payment($payment_id) : wu_get_payment_by_hash(wu_request('payment'));

			if ( ! empty($pending_payment)) {
				$pending_amount = $pending_payment->get_total();
			}

			$body['pending_payment'] = $pending_payment;

			$custom = explode('|', (string) $body['PAYMENTREQUEST_0_CUSTOM']);

			return $body;
		}

		return false;
	}

	/**
	 * Returns the external link to view the payment on the payment gateway.
	 *
	 * For the legacy NVP API, there's no reliable payment link, so we return empty.
	 * The base class provides a URL that may work for some transactions.
	 *
	 * @since 2.0.0
	 *
	 * @param string $gateway_payment_id The gateway payment id.
	 * @return string
	 */
	public function get_payment_url_on_gateway($gateway_payment_id) {

		unset($gateway_payment_id);

		return '';
	}

	/**
	 * Verifies that the IPN notification actually came from PayPal.
	 *
	 * This prevents spoofing attacks by validating the IPN with PayPal's servers.
	 *
	 * @since 2.0.0
	 *
	 * @param array $posted The IPN data to verify.
	 * @return bool True if verified, false otherwise.
	 */
	protected function verify_ipn($posted): bool {

		wu_log_add('paypal', 'Verifying PayPal IPN with PayPal servers...');

		$req = 'cmd=_notify-validate';

		foreach ($posted as $key => $value) {
			$req .= '&' . rawurlencode($key) . '=' . rawurlencode($value);
		}

		// Use sandbox or live verification endpoint
		$verify_url = $this->test_mode
			? 'https://ipnpb.sandbox.paypal.com/cgi-bin/webscr'
			: 'https://ipnpb.paypal.com/cgi-bin/webscr';

		$response = wp_remote_post(
			$verify_url,
			[
				'body'        => $req,
				'timeout'     => 30,
				'httpversion' => '1.1',
				'headers'     => [
					'Host'         => $this->test_mode ? 'ipnpb.sandbox.paypal.com' : 'ipnpb.paypal.com',
					'Connection'   => 'close',
					'Content-Type' => 'application/x-www-form-urlencoded',
				],
			]
		);

		if (is_wp_error($response)) {
			wu_log_add('paypal', 'PayPal IPN verification failed: ' . $response->get_error_message());
			return false;
		}

		$body     = wp_remote_retrieve_body($response);
		$verified = 'VERIFIED' === $body;

		wu_log_add('paypal', $verified ? 'PayPal IPN verification successful' : 'PayPal IPN verification failed - received: ' . $body);

		return $verified;
	}
}

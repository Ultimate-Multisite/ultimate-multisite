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

use Stripe\Exception\ApiErrorException;
use Stripe\PaymentMethod;
use WP_Ultimo\Exception\Runtime_Exception;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Base Gateway class. Should be extended to add new payment gateways.
 *
 * @since 2.0.0
 */
class Stripe_Checkout_Gateway extends Base_Stripe_Gateway {

	/**
	 * Holds the ID of a given gateway.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $id = 'stripe-checkout';

	/**
	 * Adds the Stripe Gateway settings to the settings screen.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function settings(): void {

		$error_message_wrap = '<span class="wu-p-2 wu-bg-red-100 wu-text-red-600 wu-rounded wu-mt-3 wu-mb-0 wu-block wu-text-xs">%s</span>';

		wu_register_settings_field(
			'payment-gateways',
			'stripe_checkout_header',
			[
				'title'           => __('Stripe Checkout', 'ultimate-multisite'),
				'desc'            => __('Use the settings section below to configure Stripe Checkout as a payment method.', 'ultimate-multisite'),
				'type'            => 'header',
				'show_as_submenu' => true,
				'require'         => [
					'active_gateways' => 'stripe-checkout',
				],
			]
		);

		wu_register_settings_field(
			'payment-gateways',
			'stripe_checkout_public_title',
			[
				'title'   => __('Stripe Public Name', 'ultimate-multisite'),
				'tooltip' => __('The name to display on the payment method selection field. By default, "Credit Card" is used.', 'ultimate-multisite'),
				'type'    => 'text',
				'default' => __('Credit Card', 'ultimate-multisite'),
				'require' => [
					'active_gateways' => 'stripe-checkout',
				],
			]
		);

		wu_register_settings_field(
			'payment-gateways',
			'stripe_checkout_sandbox_mode',
			[
				'title'     => __('Stripe Checkout Sandbox Mode', 'ultimate-multisite'),
				'desc'      => __('Toggle this to put Stripe on sandbox mode. This is useful for testing and making sure Stripe is correctly setup to handle your payments.', 'ultimate-multisite'),
				'type'      => 'toggle',
				'default'   => 1,
				'html_attr' => [
					'v-model' => 'stripe_checkout_sandbox_mode',
				],
				'require'   => [
					'active_gateways' => 'stripe-checkout',
				],
			]
		);

		$pk_test_status = wu_get_setting('stripe_checkout_test_pk_key_status', '');

		wu_register_settings_field(
			'payment-gateways',
			'stripe_checkout_test_pk_key',
			[
				'title'       => __('Stripe Test Publishable Key', 'ultimate-multisite'),
				'desc'        => ! empty($pk_test_status) ? sprintf($error_message_wrap, $pk_test_status) : '',
				'tooltip'     => __('Make sure you are placing the TEST keys, not the live ones.', 'ultimate-multisite'),
				'placeholder' => __('pk_test_***********', 'ultimate-multisite'),
				'type'        => 'text',
				'default'     => '',
				'capability'  => 'manage_api_keys',
				'require'     => [
					'active_gateways'              => 'stripe-checkout',
					'stripe_checkout_sandbox_mode' => 1,
				],
			]
		);

		$sk_test_status = wu_get_setting('stripe_checkout_test_sk_key_status', '');

		wu_register_settings_field(
			'payment-gateways',
			'stripe_checkout_test_sk_key',
			[
				'title'       => __('Stripe Test Secret Key', 'ultimate-multisite'),
				'desc'        => ! empty($sk_test_status) ? sprintf($error_message_wrap, $sk_test_status) : '',
				'tooltip'     => __('Make sure you are placing the TEST keys, not the live ones.', 'ultimate-multisite'),
				'placeholder' => __('sk_test_***********', 'ultimate-multisite'),
				'type'        => 'text',
				'default'     => '',
				'capability'  => 'manage_api_keys',
				'require'     => [
					'active_gateways'              => 'stripe-checkout',
					'stripe_checkout_sandbox_mode' => 1,
				],
			]
		);

		$pk_status = wu_get_setting('stripe_checkout_live_pk_key_status', '');

		wu_register_settings_field(
			'payment-gateways',
			'stripe_checkout_live_pk_key',
			[
				'title'       => __('Stripe Live Publishable Key', 'ultimate-multisite'),
				'desc'        => ! empty($pk_status) ? sprintf($error_message_wrap, $pk_status) : '',
				'tooltip'     => __('Make sure you are placing the LIVE keys, not the test ones.', 'ultimate-multisite'),
				'placeholder' => __('pk_live_***********', 'ultimate-multisite'),
				'type'        => 'text',
				'default'     => '',
				'capability'  => 'manage_api_keys',
				'require'     => [
					'active_gateways'              => 'stripe-checkout',
					'stripe_checkout_sandbox_mode' => 0,
				],
			]
		);

		$sk_status = wu_get_setting('stripe_checkout_live_sk_key_status', '');

		wu_register_settings_field(
			'payment-gateways',
			'stripe_checkout_live_sk_key',
			[
				'title'       => __('Stripe Live Secret Key', 'ultimate-multisite'),
				'desc'        => ! empty($sk_status) ? sprintf($error_message_wrap, $sk_status) : '',
				'tooltip'     => __('Make sure you are placing the LIVE keys, not the test ones.', 'ultimate-multisite'),
				'placeholder' => __('sk_live_***********', 'ultimate-multisite'),
				'type'        => 'text',
				'default'     => '',
				'capability'  => 'manage_api_keys',
				'require'     => [
					'active_gateways'              => 'stripe-checkout',
					'stripe_checkout_sandbox_mode' => 0,
				],
			]
		);

		$webhook_message = sprintf('<span class="wu-p-2 wu-bg-blue-100 wu-text-blue-600 wu-rounded wu-mt-3 wu-mb-0 wu-block wu-text-xs">%s</span>', __('Whenever you change your Stripe settings, Ultimate Multisite will automatically check the webhook URLs on your Stripe account to make sure we get notified about changes in subscriptions and payments.', 'ultimate-multisite'));

		wu_register_settings_field(
			'payment-gateways',
			'stripe_checkout_webhook_listener_explanation',
			[
				'title'           => __('Webhook Listener URL', 'ultimate-multisite'),
				'desc'            => $webhook_message,
				'tooltip'         => __('This is the URL Stripe should send webhook calls to.', 'ultimate-multisite'),
				'type'            => 'text-display',
				'copy'            => true,
				'display_value'   => $this->get_webhook_listener_url(),
				'wrapper_classes' => '',
				'require'         => [
					'active_gateways' => 'stripe-checkout',
				],
			]
		);

		parent::settings();
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
	 * @return \WP_Error|array
	 * @throws Runtime_Exception Something happened creating the client.
	 * @throws ApiErrorException Something bag happened sending an API request.
	 * @since 2.0.0
	 */
	public function run_preflight() {

		/**
		 * Ensure the correct api keys are set
		 */
		$this->setup_api_keys();

		/*
		 * Creates or retrieves the Stripe Customer.
		 */
		$s_customer = $this->get_or_create_customer($this->customer->get_id());

		/*
		 * Bail early if customer creation/retrieval failed.
		 *
		 * get_or_create_customer() is documented to return \Stripe\Customer|\WP_Error,
		 * but historically the caller dereferenced ->id without checking — which
		 * fatals on the typed parameter of sync_billing_address_to_stripe() when
		 * the Stripe API call throws (mismatched test/live keys, deleted customer,
		 * network failure, etc.). Surface the error to the checkout flow instead.
		 */
		if (is_wp_error($s_customer)) {
			return $s_customer;
		}

		if (empty($s_customer) || empty($s_customer->id)) {
			return new \WP_Error(
				'wu_stripe_checkout_no_customer',
				__('We could not create or retrieve your Stripe customer record. Please try again, or contact support if the problem persists.', 'ultimate-multisite')
			);
		}

		/*
		 * Update the Stripe customer with the current billing address.
		 * This ensures the address is pre-filled in Stripe Checkout.
		 */
		$this->sync_billing_address_to_stripe($s_customer->id);

		/*
		 * Stripe Checkout allows for tons of different payment methods.
		 * These include:
		 *
		 * 'card'
		 * 'alipay'
		 * 'ideal'
		 * 'fpx'
		 * 'bacs_debit'
		 * 'bancontact'
		 * 'giropay'
		 * 'p24'
		 * 'eps'
		 * 'sofort'
		 * 'sepa_debit'
		 * 'grabpay'
		 * 'afterpay_clearpay'
		 *
		 * For those to work, you'll need to activate them on your
		 * Stripe account, and you should also be in live mode.
		 */
		$allowed_payment_method_types = apply_filters(
			'wu_stripe_checkout_allowed_payment_method_types',
			[
				'card',
			],
			$this
		);

		$metadata = [
			'payment_id'    => $this->payment->get_id(),
			'membership_id' => $this->membership->get_id(),
			'customer_id'   => $this->customer->get_id(),
		];

		$this->membership->set_gateway_customer_id($s_customer->id);
		$this->membership->set_gateway($this->get_id());

		$this->membership->save();

		/**
		 * Verify the card type
		 */
		if ($this->order->get_cart_type() === 'new') {
			$redirect_url = $this->get_return_url();
		} else {
			/*
			 * Saves cart for later swap.
			 */
			$swap_id = $this->save_swap($this->order);

			$redirect_url = add_query_arg('swap', $swap_id, $this->get_confirm_url());

			$metadata['swap_id'] = $swap_id;
		}

		$subscription_data = [
			'payment_method_types'          => $allowed_payment_method_types,
			'excluded_payment_method_types' => $this->get_excluded_payment_method_types(),
			'success_url'                   => $redirect_url,
			'cancel_url'                    => $this->get_cancel_url(),
			'billing_address_collection'    => 'required',
			'client_reference_id'           => $this->customer->get_id(),
			'customer'                      => $s_customer->id,
			'metadata'                      => $metadata,
		];

		if ($this->order->should_auto_renew() && $this->order->has_recurring()) {
			$subscription_data['mode'] = 'subscription';

			$stripe_cart = $this->build_stripe_cart($this->order);

			if (is_wp_error($stripe_cart)) {
				return $stripe_cart;
			}

			/*
			 * In subscription mode, recurring items go in line_items
			 * with their plan/price IDs. The deprecated subscription_data[items]
			 * parameter has been replaced by line_items.
			 */
			$line_items = [];

			foreach (array_values($stripe_cart) as $plan_data) {
				$item = [
					'price'    => $plan_data['plan'],
					'quantity' => 1,
				];

				if ( ! empty($plan_data['tax_rates'])) {
					$item['tax_rates'] = $plan_data['tax_rates'];
				}

				$line_items[] = $item;
			}

			/*
			 * Add non-recurring items (setup fees, etc.)
			 */
			$stripe_non_recurring_cart = $this->build_non_recurring_cart($this->order);

			$line_items = array_merge($line_items, $stripe_non_recurring_cart);

			$subscription_data['line_items']        = $line_items;
			$subscription_data['subscription_data'] = [];

			/**
			 * If its a downgrade, we need to set as a trial,
			 * billing_cycle_anchor isn't supported by Checkout.
			 * (https://stripe.com/docs/api/checkout/sessions/create)
			 */
			if ($this->order->get_cart_type() === 'downgrade') {
				$next_charge      = $this->order->get_billing_next_charge_date();
				$next_charge_date = \DateTime::createFromFormat('U', $next_charge);
				$current_time     = new \DateTime();

				if ($current_time < $next_charge_date) {

					// The `trial_end` date has to be at least 2 days in the future.
					$next_charge = $next_charge_date->diff($current_time)->days > 2 ? $next_charge : strtotime('+2 days');

					$subscription_data['subscription_data']['trial_end'] = $next_charge;
				}
			} elseif ($this->order->has_trial()) {
				/*
				 * Handle trial periods.
				 */
				$subscription_data['subscription_data']['trial_end'] = $this->order->get_billing_start_date();
			}
		} else {
			$subscription_data['mode'] = 'payment';

			/*
			 * Create non-recurring only cart.
			 */
			$stripe_non_recurring_cart = $this->build_non_recurring_cart($this->order, true);

			/*
			 * If there are no payable line items (e.g. $0 checkout with 100% coupon),
			 * Stripe Checkout cannot process this. Return an error so the
			 * checkout falls back gracefully.
			 */
			if (empty($stripe_non_recurring_cart)) {
				return new \WP_Error(
					'stripe-checkout-no-items',
					__('No payable items for Stripe Checkout. The order total may be zero.', 'ultimate-multisite')
				);
			}

			$subscription_data['line_items'] = $stripe_non_recurring_cart;
		}

		/**
		 * If we have pro-rata credit (in case of an upgrade, for example)
		 * try to create a custom coupon.
		 */
		$s_coupon = $this->get_credit_coupon($this->order);

		if ($s_coupon) {
			$subscription_data['discounts'] = [
				['coupon' => $s_coupon],
			];
		}

		$session = $this->get_stripe_client()->checkout->sessions->create(apply_filters('wu_stripe_checkout_subscription_data', $subscription_data, $this));

		// Return the session URL for direct redirect (preferred) and the session ID as fallback.
		return [
			'stripe_session_id'   => sanitize_text_field($session->id),
			'stripe_checkout_url' => esc_url_raw($session->url),
		];
	}

	/**
	 * Handles confirmation windows and extra processing.
	 *
	 * This endpoint gets called when we get to the
	 * /confirm/ URL on the registration page.
	 *
	 * For example, PayPal needs a confirmation screen.
	 * And it uses this method to handle that.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function process_confirmation(): void {

		$saved_swap = $this->get_saved_swap(wu_request('swap'));

		$membership = $this->payment ? $this->payment->get_membership() : wu_get_membership_by_hash(wu_request('membership'));

		if ($saved_swap && $membership) {
			if ($saved_swap->get_cart_type() === 'downgrade') {
				$membership->schedule_swap($saved_swap);
			} else {
				$membership->swap($saved_swap);
			}

			$membership->save();

			$redirect_url = $this->get_return_url();

			wp_safe_redirect($redirect_url);

			exit;
		}
	}

	/**
	 * Add credit card fields.
	 *
	 * @since 2.0.0
	 */
	public function fields(): string {

		$message = __('You will be redirected to a checkout to complete the purchase.', 'ultimate-multisite');

		return sprintf('<p class="wu-p-4 wu-bg-yellow-200">%s</p>', $message);
	}

	/**
	 * Returns the payment methods.
	 *
	 * @since 2.0.0
	 * @return array
	 */
	public function payment_methods() {

		$fields = [];

		$card_options = $this->get_saved_card_options();

		if ($card_options) {
			foreach ($card_options as $payment_method => $card) {
				$fields = [
					"payment_method_{$payment_method}" => [
						'type'          => 'text-display',
						'title'         => __('Saved Cards', 'ultimate-multisite'),
						'display_value' => $card,
					],
				];
			}
		}

		return $fields;
	}

	/**
	 * Get the saved Stripe payment methods for a given user ID.
	 *
	 * @since 2.0.0
	 *
	 * @throws \Exception, When info is wrong.
	 * @throws \Exception When info is wrong 2.
	 * @return PaymentMethod[]|array
	 */
	public function get_user_saved_payment_methods() {

		$customer = wu_get_current_customer();

		if ( ! $customer) {
			return [];
		}

		$customer_id = $customer->get_id();

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

			/**
			 * Ensure the correct api keys are set
			 */
			$this->setup_api_keys();

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
	 * Syncs the customer's billing address to the Stripe customer object.
	 *
	 * This ensures that Stripe Checkout has the latest billing address
	 * pre-filled when the checkout modal opens.
	 *
	 * @since 2.3.0
	 *
	 * @param string $stripe_customer_id The Stripe customer ID.
	 * @return void
	 */
	protected function sync_billing_address_to_stripe(string $stripe_customer_id): void {
		/*
		 * Defensive guard: an empty customer ID would 400 from Stripe and is
		 * never a valid call. The primary caller (run_preflight) already
		 * blocks this case, but keep this here for any future caller.
		 */
		if (empty($stripe_customer_id)) {
			return;
		}

		$billing_address = $this->customer->get_billing_address();

		/*
		 * Only update if we have billing address data.
		 */
		if (empty($billing_address->billing_country) && empty($billing_address->billing_zip_code)) {
			return;
		}

		try {
			$stripe_address = $this->convert_to_stripe_address($billing_address);

			$update_data = [
				'address' => $stripe_address,
			];

			/*
			 * Also update name and email if available.
			 */
			if ($this->customer->get_display_name()) {
				$update_data['name'] = $this->customer->get_display_name();
			}

			if ($this->customer->get_email_address()) {
				$update_data['email'] = $this->customer->get_email_address();
			}

			$this->get_stripe_client()->customers->update($stripe_customer_id, $update_data);
		} catch (\Throwable $exception) {
			/*
			 * Log the error but don't fail the checkout.
			 * Stripe Checkout will still collect the address.
			 */
			wu_log_add(
				'stripe-checkout',
				sprintf(
					'Failed to sync billing address to Stripe customer %s: %s',
					$stripe_customer_id,
					$exception->getMessage()
				)
			);
		}
	}
}

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

use Stripe\PaymentMethod;
use WP_Ultimo\Database\Payments\Payment_Status;
use WP_Ultimo\Database\Memberships\Membership_Status;
use WP_Ultimo\Gateways\Base_Stripe_Gateway;
use Stripe;

// Exit if accessed directly
defined('ABSPATH') || exit;

/**
 * Base Gateway class. Should be extended to add new payment gateways.
 *
 * @since 2.0.0
 */
class Stripe_Gateway extends Base_Stripe_Gateway {

	/**
	 * Holds the ID of a given gateway.
	 *
	 * @since 2.0.0
	 * @var string
	 */
	protected $id = 'stripe';

	/**
	 * Adds additional hooks.
	 *
	 * @since 2.0.0
	 * @return void
	 */
	public function hooks(): void {

		parent::hooks();

		// Handle OAuth callbacks and disconnects
		add_action('admin_init', [$this, 'handle_oauth_callbacks']);

		// Redirect to OAuth init after settings save when connect button was clicked
		add_filter('wu_settings_save_redirect', [$this, 'maybe_redirect_to_stripe_oauth'], 10, 2);

		add_filter(
			'wu_customer_payment_methods',
			function ($fields, $customer): array {

				$this->customer = $customer;

				$extra_fields = $this->payment_methods();

				return array_merge($fields, $extra_fields);
			},
			10,
			2
		);
	}

	/**
	 * Redirect to Stripe OAuth init URL after settings are saved.
	 *
	 * When the user clicks the "Connect with Stripe" button, the settings form
	 * is submitted first (saving sandbox mode, active gateways, etc.), then
	 * this filter redirects to the OAuth init URL instead of the normal
	 * settings page.
	 *
	 * @since 2.x.x
	 *
	 * @param string $redirect_url The default redirect URL.
	 * @param array  $saved_data   The saved settings data.
	 * @return string
	 */
	public function maybe_redirect_to_stripe_oauth(string $redirect_url, array $saved_data): string {

		if (empty($_POST['wu_connect_stripe'])) { // phpcs:ignore WordPress.Security.NonceVerification
			return $redirect_url;
		}

		return $this->get_oauth_init_url();
	}

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
			'stripe_header',
			[
				'title'           => __('Stripe', 'ultimate-multisite'),
				'desc'            => __('Use the settings section below to configure Stripe as a payment method.', 'ultimate-multisite'),
				'type'            => 'header',
				'show_as_submenu' => true,
				'require'         => [
					'active_gateways' => 'stripe',
				],
			]
		);

		wu_register_settings_field(
			'payment-gateways',
			'stripe_public_title',
			[
				'title'   => __('Stripe Public Name', 'ultimate-multisite'),
				'tooltip' => __('The name to display on the payment method selection field. By default, "Credit Card" is used.', 'ultimate-multisite'),
				'type'    => 'text',
				'default' => __('Credit Card', 'ultimate-multisite'),
				'require' => [
					'active_gateways' => 'stripe',
				],
			]
		);

		wu_register_settings_field(
			'payment-gateways',
			'stripe_sandbox_mode',
			[
				'title'     => __('Stripe Sandbox Mode', 'ultimate-multisite'),
				'desc'      => __('Toggle this to put Stripe on sandbox mode. This is useful for testing and making sure Stripe is correctly setup to handle your payments.', 'ultimate-multisite'),
				'type'      => 'toggle',
				'default'   => 1,
				'html_attr' => [
					'v-model' => 'stripe_sandbox_mode',
				],
				'require'   => [
					'active_gateways' => 'stripe',
				],
			]
		);

		// OAuth Connect Section
		wu_register_settings_field(
			'payment-gateways',
			'stripe_auth_header',
			[
				'title'           => __('Stripe Authentication', 'ultimate-multisite'),
				'desc'            => __('Choose how to authenticate with Stripe. OAuth is recommended for easier setup and platform fees.', 'ultimate-multisite'),
				'type'            => 'header',
				'show_as_submenu' => false,
				'require'         => [
					'active_gateways' => 'stripe',
				],
			]
		);

		// OAuth Connection Status/Button
		wu_register_settings_field(
			'payment-gateways',
			'stripe_oauth_connection',
			[
				'title'   => __('Stripe Connect (Recommended)', 'ultimate-multisite'),
				'desc'    => __('Connect your Stripe account securely with one click. This provides easier setup and automatic configuration.', 'ultimate-multisite'),
				'type'    => 'html',
				'content' => [$this, 'render_oauth_connection'],
				'require' => [
					'active_gateways' => 'stripe',
				],
			]
		);

		// Advanced: Show Direct API Keys Toggle
		wu_register_settings_field(
			'payment-gateways',
			'stripe_show_direct_keys',
			[
				'title'     => __('Use Direct API Keys (Advanced)', 'ultimate-multisite'),
				'desc'      => __('Toggle to manually enter API keys instead of using OAuth. Use this for backwards compatibility or advanced configurations.', 'ultimate-multisite'),
				'type'      => 'toggle',
				'default'   => 0,
				'html_attr' => [
					'v-model' => 'stripe_show_direct_keys',
				],
				'require'   => [
					'active_gateways' => 'stripe',
				],
			]
		);

		$pk_test_status = wu_get_setting('stripe_test_pk_key_status', '');

		wu_register_settings_field(
			'payment-gateways',
			'stripe_test_pk_key',
			[
				'title'       => __('Stripe Test Publishable Key', 'ultimate-multisite'),
				'desc'        => ! empty($pk_test_status) ? sprintf($error_message_wrap, $pk_test_status) : '',
				'tooltip'     => __('Make sure you are placing the TEST keys, not the live ones.', 'ultimate-multisite'),
				'placeholder' => __('pk_test_***********', 'ultimate-multisite'),
				'type'        => 'text',
				'default'     => '',
				'capability'  => 'manage_api_keys',
				'require'     => [
					'active_gateways'         => 'stripe',
					'stripe_sandbox_mode'     => 1,
					'stripe_show_direct_keys' => 1,
				],
			]
		);

		$sk_test_status = wu_get_setting('stripe_test_sk_key_status', '');

		wu_register_settings_field(
			'payment-gateways',
			'stripe_test_sk_key',
			[
				'title'       => __('Stripe Test Secret Key', 'ultimate-multisite'),
				'desc'        => ! empty($sk_test_status) ? sprintf($error_message_wrap, $sk_test_status) : '',
				'tooltip'     => __('Make sure you are placing the TEST keys, not the live ones.', 'ultimate-multisite'),
				'placeholder' => __('sk_test_***********', 'ultimate-multisite'),
				'type'        => 'text',
				'default'     => '',
				'capability'  => 'manage_api_keys',
				'require'     => [
					'active_gateways'         => 'stripe',
					'stripe_sandbox_mode'     => 1,
					'stripe_show_direct_keys' => 1,
				],
			]
		);

		$pk_status = wu_get_setting('stripe_live_pk_key_status', '');

		wu_register_settings_field(
			'payment-gateways',
			'stripe_live_pk_key',
			[
				'title'       => __('Stripe Live Publishable Key', 'ultimate-multisite'),
				'desc'        => ! empty($pk_status) ? sprintf($error_message_wrap, $pk_status) : '',
				'tooltip'     => __('Make sure you are placing the LIVE keys, not the test ones.', 'ultimate-multisite'),
				'placeholder' => __('pk_live_***********', 'ultimate-multisite'),
				'type'        => 'text',
				'default'     => '',
				'capability'  => 'manage_api_keys',
				'require'     => [
					'active_gateways'         => 'stripe',
					'stripe_sandbox_mode'     => 0,
					'stripe_show_direct_keys' => 1,
				],
			]
		);

		$sk_status = wu_get_setting('stripe_live_sk_key_status', '');

		wu_register_settings_field(
			'payment-gateways',
			'stripe_live_sk_key',
			[
				'title'       => __('Stripe Live Secret Key', 'ultimate-multisite'),
				'desc'        => ! empty($sk_status) ? sprintf($error_message_wrap, $sk_status) : '',
				'tooltip'     => __('Make sure you are placing the LIVE keys, not the test ones.', 'ultimate-multisite'),
				'placeholder' => __('sk_live_***********', 'ultimate-multisite'),
				'type'        => 'text',
				'default'     => '',
				'capability'  => 'manage_api_keys',
				'require'     => [
					'active_gateways'         => 'stripe',
					'stripe_sandbox_mode'     => 0,
					'stripe_show_direct_keys' => 1,
				],
			]
		);

		$webhook_message = sprintf('<span class="wu-p-2 wu-bg-blue-100 wu-text-blue-600 wu-rounded wu-mt-3 wu-mb-0 wu-block wu-text-xs">%s</span>', __('Whenever you change your Stripe settings, Ultimate Multisite will automatically check the webhook URLs on your Stripe account to make sure we get notified about changes in subscriptions and payments.', 'ultimate-multisite'));

		wu_register_settings_field(
			'payment-gateways',
			'stripe_webhook_listener_explanation',
			[
				'title'           => __('Webhook Listener URL', 'ultimate-multisite'),
				'desc'            => $webhook_message,
				'tooltip'         => __('This is the URL Stripe should send webhook calls to.', 'ultimate-multisite'),
				'type'            => 'text-display',
				'copy'            => true,
				'display_value'   => $this->get_webhook_listener_url(),
				'wrapper_classes' => '',
				'require'         => [
					'active_gateways'         => 'stripe',
					'stripe_show_direct_keys' => 1,
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
	 * @since 2.0.0
	 * @return array|\WP_Error
	 */
	public function run_preflight() {
		/*
		 * This is the stripe preflight code.
		 *
		 * Stripe requires us to create a payment intent
		 * or payment setup to be able to charge customers.
		 *
		 * This is done in order to comply with EU SCA
		 * and other such regulations.
		 *
		 * Before we get started, we need to get our stripe
		 * customer.
		 */
		$s_customer = $this->get_or_create_customer($this->customer->get_id(), $this->customer->get_user_id());

		/*
		 * Things can go wrong,
		 * check for WP_Error.
		 */
		if (is_wp_error($s_customer)) {

			// translators: %s is the error message.
			return new \WP_Error($s_customer->get_error_code(), sprintf(__('Error creating Stripe customer: %s', 'ultimate-multisite'), $s_customer->get_error_message()));
		}

		$this->membership->set_gateway_customer_id($s_customer->id);
		$this->membership->set_gateway($this->get_id());

		$type = $this->order->get_cart_type();

		/*
		 * Let's deal with upgrades, downgrades and addons.
		 *
		 * Here we just need to make sure we process
		 * a membership swap.
		 */
		if ('upgrade' === $type || 'addon' === $type) {
			$this->membership->swap($this->order);
		} elseif ('downgrade' === $type) {
			$this->membership->schedule_swap($this->order);
		}

		$this->membership->save();

		$intent_args = [
			'customer'    => $s_customer->id,
			'metadata'    => $this->get_customer_metadata(),
			'description' => $this->order->get_cart_descriptor(),
		];

		/*
		 * Maybe use an existing payment method.
		 */
		if (wu_request('payment_method', 'add-new') !== 'add-new') {
			$intent_args['payment_method'] = sanitize_text_field(wu_request('payment_method'));
		}

		/*
		 * Let's start with the intent options.
		 *
		 * We'll append the extra options as we go.
		 * This should also be filterable to allow support
		 * for Stripe Connect in the future.
		 */
		$intent_options = [];

		/*
		 * Tries to retrieve an existing intent id,
		 * from the current payment.
		 */
		$payment_intent_id = $this->payment->get_meta('stripe_payment_intent_id');
		$existing_intent   = false;

		/**
		 * Ensure the correct api keys are set
		 */
		$this->setup_api_keys();

		/*
		 * Tries to retrieve an intent on Stripe.
		 *
		 * If we succeed, we update it, if we fail,
		 * we try to create a new one.
		 */
		try {
			/*
			 * Payment intents are used when we have an initial
			 * payment attached to the membership. These start with a pi_
			 * id.
			 */
			if ( ! empty($payment_intent_id) && str_starts_with((string) $payment_intent_id, 'pi_')) {
				$existing_intent = $this->get_stripe_client()->paymentIntents->retrieve($payment_intent_id);

				/*
				* Setup intents are created with the intent
				* of future charging. This is what we use
				* when we set up a subscription without a
				* initial amount.
				*/
			} elseif ( ! empty($payment_intent_id) && str_starts_with((string) $payment_intent_id, 'seti_')) {
				$existing_intent = $this->get_stripe_client()->setupIntents->retrieve($payment_intent_id);
			}

			/*
			 * We can't use canceled intents
			 * for obvious reasons...
			 *
			 * We also can't reuse intents that are already
			 * in a terminal state (succeeded), or that have
			 * been captured already - Stripe rejects updates
			 * to those PaymentIntents (e.g. application_fee_amount
			 * cannot be updated after capture).
			 *
			 * Treating them as missing forces a fresh intent
			 * to be created for the retry, which is the
			 * correct behaviour for a "finish payment" flow
			 * landing on an already-captured PI.
			 */
			$terminal_intent_statuses = ['canceled', 'succeeded'];

			if ( ! empty($existing_intent) && in_array($existing_intent->status, $terminal_intent_statuses, true)) {
				$existing_intent = false;
			}

			/*
			 * If we have a initial payment,
			 * we need to take care of that logic.
			 *
			 * If we have a trial, we need to deal with that via a setup intent.
			 */
			if ($this->order->get_total() && $this->order->has_trial() === false) {
				$intent_args = wp_parse_args(
					$intent_args,
					[
						'amount'              => $this->order->get_total() * wu_stripe_get_currency_multiplier(),
						'confirmation_method' => 'automatic',
						'setup_future_usage'  => 'off_session',
						'currency'            => strtolower((string) wu_get_setting('currency_symbol', 'USD')),
						'confirm'             => false,
					]
				);

				/**
				 * Filters the payment intent arguments.
				 *
				 * @since 2.0
				 *
				 * @param array $intent_args The list of intent args.
				 * @param Stripe_Gateway $stripe_gateway.
				 * @return array
				 */
				$intent_args = apply_filters('wu_stripe_create_payment_intent_args', $intent_args, $this);

				if ( ! empty($existing_intent) && 'payment_intent' === $existing_intent->object) {
					$idempotency_args           = $intent_args;
					$idempotency_args['update'] = true;

					/*
					 * Stripe allows us to send a key
					 * together with the arguments to prevent
					 * duplication in payment intents.
					 *
					 * Same parameters = same key,
					 * so Stripe knows what to ignore.
					 */
					$intent_options['idempotency_key'] = wu_stripe_generate_idempotency_key($idempotency_args);

					/*
					 * Unset some options we can't update.
					 *
					 * application_fee_amount is set at PaymentIntent
					 * creation time and cannot be modified once the
					 * intent has progressed to capture. Stripe also
					 * rejects updates that include confirmation_method
					 * or confirm.
					 */
					$unset_args = ['confirmation_method', 'confirm', 'application_fee_amount'];

					foreach ($unset_args as $unset_arg) {
						if (isset($intent_args[ $unset_arg ])) {
							unset($intent_args[ $unset_arg ]);
						}
					}

					/*
					 * Tries to update the payment intent.
					 */
					$intent = $this->get_stripe_client()->paymentIntents->update($existing_intent->id, $intent_args, $intent_options);
				} else {
					$intent_options['idempotency_key'] = wu_stripe_generate_idempotency_key($intent_args);

					$intent = $this->get_stripe_client()->paymentIntents->create($intent_args, $intent_options);
				}
			} else {
				/*
				 * Create a setup intent instead.
				 */
				$intent_args = wp_parse_args(
					$intent_args,
					[
						'usage' => 'off_session',
					]
				);

				if (empty($existing_intent) || 'setup_intent' !== $existing_intent->object) {
					$intent_options['idempotency_key'] = wu_stripe_generate_idempotency_key($intent_args);

					/*
					 * Tries to create in Stripe.
					 */
					$intent = $this->get_stripe_client()->setupIntents->create($intent_args, $intent_options);
				} else {
					$intent = $existing_intent;
				}
			}
		} catch (\Stripe\Exception\ExceptionInterface $e) {
			return $this->get_stripe_error($e);
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

		/*
		 * To prevent re-doing all this
		 * work again, we save the intent on
		 * the payment, so we can use it
		 * in cases a retry is needed.
		 */
		$this->payment->update_meta('stripe_payment_intent_id', sanitize_text_field($intent->id));

		/*
		 * Anything returned in this array
		 * gets added to the checkout form as hidden
		 * fields just before the form submission.
		 *
		 * Here we pass the data we need from the
		 * recently create intents.
		 *
		 * Using this info, we'll be able to process
		 * the Stripe payment on the next step: process_checkout
		 */
		return [
			'stripe_client_secret' => sanitize_text_field($intent->client_secret),
			'stripe_intent_type'   => sanitize_text_field($intent->object),
		];
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
	public function process_checkout($payment, $membership, $customer, $cart, $type): void {
		/*
		 * Here's the general idea
		 * of how the Stripe integration works.
		 *
		 * Despite of the type, we'll need to
		 * cancel an existing subscription to create
		 * a new one.
		 *
		 * Then, after that's all said and done
		 * we can move our attention back to handling
		 * the membership, payment, and customer locally.
		 *
		 * For sanity reasons, stripe variants of data type
		 * such as a Stripe\Customer instance, will be
		 * held by variables stating with s_ (e.g. s_customer)
		 *
		 * First, we need to check for a valid payment intent.
		 */
		$payment_intent_id = $payment->get_meta('stripe_payment_intent_id');

		if (empty($payment_intent_id)) {
			throw new \Exception(esc_html__('Missing Stripe payment intent, please try again or contact support if the issue persists.', 'ultimate-multisite'));
		}

		/**
		 * Ensure the correct api keys are set
		 */
		$this->setup_api_keys();

		/*
		 * To make our lives easier, let's
		 * set a couple of variables based on the order.
		 */
		$should_auto_renew = $cart->should_auto_renew();
		$is_recurring      = $cart->has_recurring();
		$is_setup_intent   = false;

		/*
		 * Get the correct intent
		 * type depending on the intent ID
		 */
		if (str_starts_with((string) $payment_intent_id, 'seti_')) {
			$is_setup_intent = true;

			$payment_intent = $this->get_stripe_client()->setupIntents->retrieve($payment_intent_id);
		} else {
			$payment_intent = $this->get_stripe_client()->paymentIntents->retrieve($payment_intent_id, ['expand' => ['latest_charge']]);
		}

		/*
		 * Retrieves the Stripe Customer
		 * or create a new one!
		 */
		$s_customer = $this->get_or_create_customer($customer->get_id(), $customer->get_user_id(), $payment_intent->customer);

		// translators: first is the customer id, then the customer email.
		$description = sprintf(__('Customer ID: %1$d - User Email: %2$s', 'ultimate-multisite'), $customer->get_id(), $customer->get_email_address());

		if (strlen($description) > 350) {
			$description = substr($description, 0, 350);
		}

		/*
		 * Updates the customer on Stripe
		 * to make sure it always has the most
		 * up-to-date info.
		 */
		$this->get_stripe_client()->customers->update(
			$s_customer->id,
			[
				'address'     => $this->convert_to_stripe_address($customer->get_billing_address()),
				'description' => sanitize_text_field($description),
				'metadata'    => [
					'email'       => $customer->get_email_address(),
					'user_id'     => $customer->get_user_id(),
					'customer_id' => $customer->get_id(),
				],
			]
		);

		/*
		 * Persist payment methods.
		 *
		 * This is not really THAT mission
		 * critical, but it is a nice-to-have
		 * that being said, we'll have it happen
		 * on the sidelines.
		 */
		$payment_method = $this->save_payment_method($payment_intent, $s_customer);

		$payment_completed = $is_setup_intent || ('succeeded' === $payment_intent->status && ! empty($payment_intent->latest_charge));

		$subscription = false;

		if ($payment_completed && $should_auto_renew && $is_recurring) {
			$subscription = $this->create_recurring_payment($membership, $cart, $payment_method, $s_customer);

			if ( ! $subscription) {
				/**
				 * Another process is already taking care of this (webhook).
				 */
				return;
			}
		}

		if ($payment_completed) {
			$charge_id  = is_object($payment_intent->latest_charge) ? $payment_intent->latest_charge->id : $payment_intent->latest_charge;
			$payment_id = $is_setup_intent ? $payment_intent->id : sanitize_text_field($charge_id);

			$payment->set_status(Payment_Status::COMPLETED);
			$payment->set_gateway($this->get_id());
			$payment->set_gateway_payment_id($payment_id);
			$payment->save();

			if ( ! $subscription && 'downgrade' !== $type ) {
				$membership_status = $cart->has_trial() ? Membership_Status::TRIALING : Membership_Status::ACTIVE;

				$membership->set_gateway($this->get_id());
				$membership->set_gateway_customer_id($s_customer->id);
				$membership->set_gateway_subscription_id('');
				$membership->add_to_times_billed(1);

				if ( 'reactivation' === $type ) {
					$membership->reactivate(false);
				} else {
					$membership->renew(false, $membership_status);
				}
			}

			$this->trigger_payment_processed($payment, $membership);
		}

		if ($subscription) {
			$membership->set_gateway($this->get_id());
			$membership->set_gateway_customer_id($s_customer->id);
			$membership->set_gateway_subscription_id($subscription->id);
			$membership->add_to_times_billed(1);

			if ('downgrade' !== $type) {
				$membership_status = $cart->has_trial() ? Membership_Status::TRIALING : Membership_Status::ACTIVE;

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

				/*
				 * Use reactivate() for reactivation carts so that the
				 * wu_membership_pre_reactivate / wu_membership_post_reactivate
				 * hooks fire and cancellation metadata is cleared correctly.
				 *
				 * @since 2.5.0
				 */
				if ('reactivation' === $type) {
					$membership->reactivate(true, $expiration);
				} else {
					$membership->renew(true, $membership_status, $expiration);
				}
			} else {
				$membership->save();
			}
		}
	}

	/**
	 * Add credit card fields.
	 *
	 * @since 2.0.0
	 * @return string
	 */
	public function fields(): string {

		$fields = [];

		$card_options = $this->get_saved_card_options();

		if ($card_options) {
			$card_options['add-new'] = __('Add new card', 'ultimate-multisite');

			$fields = [
				'payment_method' => [
					'type'      => 'radio',
					'title'     => __('Saved Payment Methods', 'ultimate-multisite'),
					'value'     => wu_request('payment_method'),
					'options'   => $card_options,
					'html_attr' => [
						'v-model' => 'payment_method',
					],
				],
			];
		}

		$stripe_form = new \WP_Ultimo\UI\Form(
			'billing-address-fields',
			$fields,
			[
				'views'     => 'checkout/fields',
				'variables' => [
					'step' => (object) [
						'classes' => '',
					],
				],
			]
		);

		ob_start();

		$stripe_form->render();
		?>

		<div v-if="payment_method == 'add-new'">

			<!-- Payment Element container -->
			<div id="payment-element" class="wu-mb-4">
				<!-- Stripe Payment Element will be inserted here -->
			</div>

			<!-- Used to display Element errors. -->
			<div id="payment-errors" role="alert"></div>

		</div>

		<?php

		return ob_get_clean();
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
	 * Render OAuth connection status HTML.
	 *
	 * Displays either the connected status with account ID and disconnect button,
	 * or a "Connect with Stripe" button for new connections.
	 * When an OAuth error occurred during admin_init, it is shown inline.
	 *
	 * @since 2.x.x
	 * @return void
	 */
	public function render_oauth_connection(): void {
		$is_oauth   = $this->is_using_oauth();
		$account_id = $this->oauth_account_id;

		if ($is_oauth && ! empty($account_id)) {
			// Connected state
			printf(
				'<div class="wu-oauth-status wu-connected wu-p-4 wu-bg-green-50 wu-border wu-border-green-200 wu-rounded">
					<div class="wu-flex wu-items-center wu-mb-2">
						<span class="dashicons dashicons-yes-alt wu-text-green-600 wu-mr-2"></span>
						<strong class="wu-text-green-800">%s</strong>
					</div>
					<p class="wu-text-sm wu-text-gray-600 wu-mb-2">%s <code class="wu-bg-white wu-px-2 wu-py-1 wu-rounded">%s</code></p>
					<a href="%s" class="button wu-mt-2">%s</a>
				</div>',
				esc_html__('Connected via Stripe Connect', 'ultimate-multisite'),
				esc_html__('Account ID:', 'ultimate-multisite'),
				esc_html($account_id),
				esc_url($this->get_disconnect_url()),
				esc_html__('Disconnect', 'ultimate-multisite')
			);
		} else {

			// Error display
			if ( ! empty($this->oauth_error)) {
				printf(
					'<div class="wu-p-3 wu-bg-red-100 wu-text-red-600 wu-rounded wu-mb-3 wu-text-sm">%s</div>',
					esc_html($this->oauth_error)
				);
			}

			// Disconnected state - show connect button (submits form to save settings first)
			printf(
				'<div class="wu-oauth-status wu-disconnected wu-p-4 wu-bg-blue-50 wu-border wu-border-blue-200 wu-rounded">
				<p class="wu-text-sm wu-text-gray-700 wu-mb-3">%s</p>
				<button type="submit" name="wu_connect_stripe" value="1" class="button button-primary">
					<span class="dashicons dashicons-admin-links wu-mr-1 wu-mt-1"></span>
					%s
				</button>
				<p class="wu-text-xs wu-text-gray-500 wu-mt-2">%s</p>
			</div>',
				esc_html__('Connect your Stripe account with one click.', 'ultimate-multisite'),
				esc_html__('Connect with Stripe', 'ultimate-multisite'),
				esc_html__('You will be redirected to Stripe to securely authorize the connection.', 'ultimate-multisite'),
			);
		}
		// Fee notice for connected state.
		if ( ! \WP_Ultimo::get_instance()->get_addon_repository()->has_addon_purchase()) {
			printf(
				'<div class="wu-py-3">
						%s <br>
						<a href="%s" target="_blank" rel="noopener">%s</a>
					</div>',
				esc_html(
					sprintf(
					/* translators: %s: the fee percentage */
						__('There is a %s%% fee per-transaction to use the Stripe integration included in the free Ultimate Multisite plugin.', 'ultimate-multisite'),
						number_format_i18n($this->get_application_fee_percent(), 0)
					)
				),
				esc_url(network_admin_url('admin.php?page=wp-ultimo-addons')),
				esc_html__('Remove this fee by purchasing any addon and connecting your store.', 'ultimate-multisite')
			);
		} else {
			printf(
				'<p class="wu-text-xs wu-text-green-700 wu-mt-2">%s</p>',
				esc_html__('No application fee — thank you for your support!', 'ultimate-multisite')
			);
		}
	}
}

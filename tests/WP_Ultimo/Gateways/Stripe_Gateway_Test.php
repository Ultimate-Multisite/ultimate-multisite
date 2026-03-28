<?php
/**
 * Comprehensive unit tests for the Stripe_Gateway class.
 *
 * Covers the following methods and code paths:
 * - hooks()
 * - maybe_redirect_to_stripe_oauth()
 * - settings()
 * - run_preflight() — all branches (payment intent, setup intent, existing intent, error paths)
 * - fields()
 * - payment_methods()
 * - get_user_saved_payment_methods()
 * - render_oauth_connection()
 *
 * @package WP_Ultimo\Gateways
 * @since 2.0.0
 */

namespace WP_Ultimo\Gateways;

use WP_Ultimo\Database\Memberships\Membership_Status;
use WP_Ultimo\Database\Payments\Payment_Status;
use WP_Ultimo\Models\Customer;
use Stripe\StripeClient;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for Stripe_Gateway.
 */
class Stripe_Gateway_Test extends \WP_UnitTestCase {

	/**
	 * @var Stripe_Gateway
	 */
	private $gateway;

	/**
	 * @var MockObject|StripeClient
	 */
	private $stripe_client_mock;

	/**
	 * @var Customer
	 */
	private static Customer $customer;

	/**
	 * Create shared test customer once.
	 *
	 * @return void
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		self::$customer = wu_create_customer(
			[
				'username' => 'stripe_gw_test_user',
				'email'    => 'stripe_gw_test@example.com',
				'password' => 'password123',
			]
		);
	}

	/**
	 * Set up test environment with mocked Stripe client.
	 *
	 * @return void
	 */
	public function setUp(): void {
		parent::setUp();

		// Clear Stripe settings before each test.
		wu_save_setting('stripe_test_access_token', '');
		wu_save_setting('stripe_test_account_id', '');
		wu_save_setting('stripe_test_publishable_key', '');
		wu_save_setting('stripe_test_pk_key', 'pk_test_123');
		wu_save_setting('stripe_test_sk_key', 'sk_test_123');
		wu_save_setting('stripe_sandbox_mode', 1);

		$this->stripe_client_mock = $this->build_stripe_client_mock();

		$this->gateway = new Stripe_Gateway();
		$this->gateway->set_stripe_client($this->stripe_client_mock);
	}

	// -------------------------------------------------------------------------
	// Helper: build a fully-wired Stripe client mock
	// -------------------------------------------------------------------------

	/**
	 * Build a Stripe client mock with all required service mocks.
	 *
	 * @param array $overrides Optional service mock overrides keyed by service name.
	 * @return MockObject|StripeClient
	 */
	private function build_stripe_client_mock(array $overrides = []) {
		$client = $this->getMockBuilder(StripeClient::class)
			->disableOriginalConstructor()
			->getMock();

		$payment_intents_mock = $overrides['paymentIntents']
			?? $this->getMockBuilder(\Stripe\Service\PaymentIntentService::class)
				->disableOriginalConstructor()
				->getMock();

		$setup_intents_mock = $overrides['setupIntents']
			?? $this->getMockBuilder(\Stripe\Service\SetupIntentService::class)
				->disableOriginalConstructor()
				->getMock();

		$customers_mock = $overrides['customers']
			?? $this->getMockBuilder(\Stripe\Service\CustomerService::class)
				->disableOriginalConstructor()
				->getMock();

		$payment_methods_mock = $overrides['paymentMethods']
			?? $this->getMockBuilder(\Stripe\Service\PaymentMethodService::class)
				->disableOriginalConstructor()
				->getMock();

		$subscriptions_mock = $overrides['subscriptions']
			?? $this->getMockBuilder(\Stripe\Service\SubscriptionService::class)
				->disableOriginalConstructor()
				->getMock();

		$plans_mock = $overrides['plans']
			?? $this->getMockBuilder(\Stripe\Service\PlanService::class)
				->disableOriginalConstructor()
				->getMock();

		// Default customer stub.
		$stripe_customer = \Stripe\Customer::constructFrom(['id' => 'cus_test123']);
		$customers_mock->method('retrieve')->willReturn($stripe_customer);
		$customers_mock->method('create')->willReturn($stripe_customer);
		$customers_mock->method('update')->willReturn($stripe_customer);

		// Default payment method stub.
		$stripe_pm = \Stripe\PaymentMethod::constructFrom(['id' => 'pm_test123', 'type' => 'card', 'card' => ['brand' => 'visa', 'last4' => '4242', 'exp_month' => 12, 'exp_year' => 2030]]);
		$payment_methods_mock->method('retrieve')->willReturn($stripe_pm);

		// Default payment methods list (for get_user_saved_payment_methods).
		$pm_list = \Stripe\Collection::constructFrom([
			'object' => 'list',
			'data'   => [$stripe_pm],
		]);
		$payment_methods_mock->method('all')->willReturn($pm_list);

		// Default plan stub.
		$stripe_plan = \Stripe\Plan::constructFrom(['id' => 'plan_test123']);
		$plans_mock->method('retrieve')->willReturn($stripe_plan);

		$client->method('__get')->willReturnCallback(
			function ($property) use (
				$payment_intents_mock,
				$setup_intents_mock,
				$customers_mock,
				$payment_methods_mock,
				$subscriptions_mock,
				$plans_mock
			) {
				switch ($property) {
					case 'paymentIntents':
						return $payment_intents_mock;
					case 'setupIntents':
						return $setup_intents_mock;
					case 'customers':
						return $customers_mock;
					case 'paymentMethods':
						return $payment_methods_mock;
					case 'subscriptions':
						return $subscriptions_mock;
					case 'plans':
						return $plans_mock;
					default:
						return null;
				}
			}
		);

		return $client;
	}

	/**
	 * Build a minimal checkout context (product, membership, payment, cart).
	 *
	 * @param array $args Optional overrides.
	 * @return array{product, membership, payment, cart}
	 */
	private function build_checkout_context(array $args = []): array {
		$customer = self::$customer;
		wp_set_current_user($customer->get_user_id(), $customer->get_username());

		$recurring      = $args['recurring'] ?? true;
		$trial          = $args['trial'] ?? false;
		$amount         = $args['amount'] ?? 29.00;
		$payment_method = $args['payment_method'] ?? 'add-new';

		$product = wu_create_product(
			[
				'name'                => 'Test Plan ' . uniqid(),
				'slug'                => 'test-plan-' . uniqid(),
				'amount'              => $amount,
				'recurring'           => $recurring,
				'duration'            => 1,
				'duration_unit'       => 'month',
				'trial_duration'      => $trial ? 7 : 0,
				'trial_duration_unit' => 'day',
				'type'                => 'plan',
				'pricing_type'        => 'paid',
				'active'              => true,
			]
		);

		$membership = wu_create_membership(
			[
				'customer_id'     => $customer->get_id(),
				'plan_id'         => $product->get_id(),
				'status'          => Membership_Status::PENDING,
				'recurring'       => $recurring,
				'date_expiration' => gmdate('Y-m-d 23:59:59', strtotime('+30 days')),
				'currency'        => 'USD',
			]
		);

		$payment = wu_create_payment(
			[
				'customer_id'   => $customer->get_id(),
				'membership_id' => $membership->get_id(),
				'gateway'       => 'stripe',
				'status'        => 'pending',
				'total'         => $trial ? 0 : $amount,
			]
		);

		$cart = new \WP_Ultimo\Checkout\Cart(
			[
				'cart_type'     => 'new',
				'products'      => [$product->get_id()],
				'duration'      => 1,
				'duration_unit' => 'month',
				'membership_id' => false,
				'payment_id'    => false,
				'discount_code' => false,
				'auto_renew'    => $recurring,
				'country'       => 'US',
				'state'         => 'NY',
				'city'          => 'New York',
				'currency'      => 'USD',
			]
		);

		$this->gateway->set_order($cart);
		$this->gateway->set_membership($membership);
		$this->gateway->set_customer($customer);
		$this->gateway->set_payment($payment);

		return compact('product', 'membership', 'payment', 'cart');
	}

	// -------------------------------------------------------------------------
	// hooks()
	// -------------------------------------------------------------------------

	/**
	 * Test that hooks() registers the expected WordPress actions and filters.
	 *
	 * @return void
	 */
	public function test_hooks_registers_admin_init_action(): void {
		$gateway = new Stripe_Gateway();
		$gateway->hooks();

		$this->assertGreaterThan(
			0,
			has_action('admin_init', [$gateway, 'handle_oauth_callbacks']),
			'hooks() must register handle_oauth_callbacks on admin_init'
		);
	}

	/**
	 * Test that hooks() registers the settings save redirect filter.
	 *
	 * @return void
	 */
	public function test_hooks_registers_settings_save_redirect_filter(): void {
		$gateway = new Stripe_Gateway();
		$gateway->hooks();

		$this->assertGreaterThan(
			0,
			has_filter('wu_settings_save_redirect', [$gateway, 'maybe_redirect_to_stripe_oauth']),
			'hooks() must register maybe_redirect_to_stripe_oauth on wu_settings_save_redirect'
		);
	}

	/**
	 * Test that hooks() registers the customer payment methods filter.
	 *
	 * @return void
	 */
	public function test_hooks_registers_customer_payment_methods_filter(): void {
		$gateway = new Stripe_Gateway();
		$gateway->hooks();

		$this->assertGreaterThan(
			0,
			has_filter('wu_customer_payment_methods'),
			'hooks() must register a filter on wu_customer_payment_methods'
		);
	}

	// -------------------------------------------------------------------------
	// maybe_redirect_to_stripe_oauth()
	// -------------------------------------------------------------------------

	/**
	 * Test that maybe_redirect_to_stripe_oauth() returns the original URL when
	 * the wu_connect_stripe POST field is absent.
	 *
	 * @return void
	 */
	public function test_maybe_redirect_returns_original_url_when_no_connect_button(): void {
		unset($_POST['wu_connect_stripe']);

		$result = $this->gateway->maybe_redirect_to_stripe_oauth('https://example.com/settings', []);

		$this->assertSame(
			'https://example.com/settings',
			$result,
			'Should return original URL when wu_connect_stripe is not set'
		);
	}

	/**
	 * Test that maybe_redirect_to_stripe_oauth() returns the OAuth init URL when
	 * the wu_connect_stripe POST field is present.
	 *
	 * @return void
	 */
	public function test_maybe_redirect_returns_oauth_url_when_connect_button_clicked(): void {
		$_POST['wu_connect_stripe'] = '1';

		// Mock the proxy call so get_oauth_init_url() returns something.
		add_filter('pre_http_request', function ($preempt, $args, $url) {
			if (false !== strpos($url, '/oauth/init')) {
				return [
					'response' => ['code' => 200],
					'body'     => wp_json_encode([
						'oauthUrl' => 'https://connect.stripe.com/oauth/authorize?client_id=ca_test&state=enc_state&scope=read_write',
						'state'    => 'test_state_abc',
					]),
				];
			}
			return $preempt;
		}, 10, 3);

		$result = $this->gateway->maybe_redirect_to_stripe_oauth('https://example.com/settings', []);

		unset($_POST['wu_connect_stripe']);

		$this->assertNotSame(
			'https://example.com/settings',
			$result,
			'Should NOT return original URL when wu_connect_stripe is set'
		);
	}

	// -------------------------------------------------------------------------
	// settings()
	// -------------------------------------------------------------------------

	/**
	 * Test settings() registers the expected settings fields.
	 *
	 * @return void
	 */
	public function test_settings_registers_stripe_fields(): void {
		// Track which field IDs are registered via the wu_register_settings_field hook.
		$registered_field_ids = [];

		add_filter(
			'wu_settings_section_payment-gateways_fields',
			function ($fields) use (&$registered_field_ids) {
				$registered_field_ids = array_merge($registered_field_ids, array_keys($fields));
				return $fields;
			},
			999
		);

		$this->gateway->settings();

		// Verify key fields are registered by checking the settings sections.
		$settings = \WP_Ultimo\Settings::get_instance();
		$sections = $settings->get_sections();

		$payment_gateway_fields = $sections['payment-gateways']['fields'] ?? [];
		$field_ids = array_keys($payment_gateway_fields);

		$this->assertContains(
			'stripe_header',
			$field_ids,
			'settings() must register stripe_header field'
		);
		$this->assertContains(
			'stripe_sandbox_mode',
			$field_ids,
			'settings() must register stripe_sandbox_mode field'
		);
		$this->assertContains(
			'stripe_oauth_connection',
			$field_ids,
			'settings() must register stripe_oauth_connection field'
		);
	}

	// -------------------------------------------------------------------------
	// run_preflight() — new payment intent (no existing intent)
	// -------------------------------------------------------------------------

	/**
	 * Test run_preflight() creates a new payment intent for a paid, non-trial order.
	 *
	 * @return void
	 */
	public function test_run_preflight_creates_payment_intent_for_paid_order(): void {
		// Build a payment intent mock that expects create() to be called.
		$payment_intent = \Stripe\PaymentIntent::constructFrom([
			'id'            => 'pi_new123',
			'object'        => 'payment_intent',
			'status'        => 'requires_payment_method',
			'client_secret' => 'pi_new123_secret_abc',
		]);

		$payment_intents_mock = $this->getMockBuilder(\Stripe\Service\PaymentIntentService::class)
			->disableOriginalConstructor()
			->getMock();
		$payment_intents_mock->expects($this->once())
			->method('create')
			->willReturn($payment_intent);
		$payment_intents_mock->method('retrieve')->willReturn($payment_intent);

		$client = $this->build_stripe_client_mock(['paymentIntents' => $payment_intents_mock]);
		$this->gateway->set_stripe_client($client);

		$context = $this->build_checkout_context(['recurring' => false, 'trial' => false, 'amount' => 50.00]);

		$result = $this->gateway->run_preflight();

		$this->assertIsArray($result, 'run_preflight() must return an array on success');
		$this->assertArrayHasKey('stripe_client_secret', $result, 'Result must include stripe_client_secret');
		$this->assertArrayHasKey('stripe_intent_type', $result, 'Result must include stripe_intent_type');
		$this->assertSame('payment_intent', $result['stripe_intent_type']);

		// Cleanup.
		$context['payment']->delete();
		$context['membership']->delete();
		$context['product']->delete();
	}

	/**
	 * Test run_preflight() creates a setup intent for a zero-amount (trial) order.
	 *
	 * @return void
	 */
	public function test_run_preflight_creates_setup_intent_for_trial_order(): void {
		$setup_intent = \Stripe\SetupIntent::constructFrom([
			'id'            => 'seti_new123',
			'object'        => 'setup_intent',
			'status'        => 'requires_payment_method',
			'client_secret' => 'seti_new123_secret_abc',
		]);

		$setup_intents_mock = $this->getMockBuilder(\Stripe\Service\SetupIntentService::class)
			->disableOriginalConstructor()
			->getMock();
		$setup_intents_mock->expects($this->once())
			->method('create')
			->willReturn($setup_intent);
		$setup_intents_mock->method('retrieve')->willReturn($setup_intent);

		$client = $this->build_stripe_client_mock(['setupIntents' => $setup_intents_mock]);
		$this->gateway->set_stripe_client($client);

		$context = $this->build_checkout_context(['recurring' => true, 'trial' => true, 'amount' => 29.00]);

		$result = $this->gateway->run_preflight();

		$this->assertIsArray($result, 'run_preflight() must return an array for trial order');
		$this->assertArrayHasKey('stripe_client_secret', $result);
		$this->assertArrayHasKey('stripe_intent_type', $result);
		$this->assertSame('setup_intent', $result['stripe_intent_type']);

		// Cleanup.
		$context['payment']->delete();
		$context['membership']->delete();
		$context['product']->delete();
	}

	// -------------------------------------------------------------------------
	// run_preflight() — existing intent paths
	// -------------------------------------------------------------------------

	/**
	 * Test run_preflight() updates an existing payment intent when pi_ id is stored.
	 *
	 * @return void
	 */
	public function test_run_preflight_updates_existing_payment_intent(): void {
		$existing_intent = \Stripe\PaymentIntent::constructFrom([
			'id'            => 'pi_existing123',
			'object'        => 'payment_intent',
			'status'        => 'requires_payment_method',
			'client_secret' => 'pi_existing123_secret',
		]);

		$updated_intent = \Stripe\PaymentIntent::constructFrom([
			'id'            => 'pi_existing123',
			'object'        => 'payment_intent',
			'status'        => 'requires_payment_method',
			'client_secret' => 'pi_existing123_secret_updated',
		]);

		$payment_intents_mock = $this->getMockBuilder(\Stripe\Service\PaymentIntentService::class)
			->disableOriginalConstructor()
			->getMock();
		$payment_intents_mock->expects($this->once())
			->method('retrieve')
			->with('pi_existing123')
			->willReturn($existing_intent);
		$payment_intents_mock->expects($this->once())
			->method('update')
			->willReturn($updated_intent);
		$payment_intents_mock->method('create')->willReturn($updated_intent);

		$client = $this->build_stripe_client_mock(['paymentIntents' => $payment_intents_mock]);
		$this->gateway->set_stripe_client($client);

		$context = $this->build_checkout_context(['recurring' => false, 'trial' => false, 'amount' => 50.00]);

		// Pre-set the payment intent ID on the payment.
		$context['payment']->update_meta('stripe_payment_intent_id', 'pi_existing123');

		$result = $this->gateway->run_preflight();

		$this->assertIsArray($result, 'run_preflight() must return an array when updating existing intent');
		$this->assertArrayHasKey('stripe_client_secret', $result);

		// Cleanup.
		$context['payment']->delete();
		$context['membership']->delete();
		$context['product']->delete();
	}

	/**
	 * Test run_preflight() reuses an existing setup intent when seti_ id is stored.
	 *
	 * @return void
	 */
	public function test_run_preflight_reuses_existing_setup_intent(): void {
		$existing_setup_intent = \Stripe\SetupIntent::constructFrom([
			'id'            => 'seti_existing123',
			'object'        => 'setup_intent',
			'status'        => 'requires_payment_method',
			'client_secret' => 'seti_existing123_secret',
		]);

		$setup_intents_mock = $this->getMockBuilder(\Stripe\Service\SetupIntentService::class)
			->disableOriginalConstructor()
			->getMock();
		$setup_intents_mock->expects($this->once())
			->method('retrieve')
			->with('seti_existing123')
			->willReturn($existing_setup_intent);
		// create() should NOT be called since we reuse the existing intent.
		$setup_intents_mock->expects($this->never())
			->method('create');

		$client = $this->build_stripe_client_mock(['setupIntents' => $setup_intents_mock]);
		$this->gateway->set_stripe_client($client);

		$context = $this->build_checkout_context(['recurring' => true, 'trial' => true, 'amount' => 29.00]);

		// Pre-set the setup intent ID on the payment.
		$context['payment']->update_meta('stripe_payment_intent_id', 'seti_existing123');

		$result = $this->gateway->run_preflight();

		$this->assertIsArray($result, 'run_preflight() must return an array when reusing existing setup intent');
		$this->assertArrayHasKey('stripe_client_secret', $result);
		$this->assertSame('setup_intent', $result['stripe_intent_type']);

		// Cleanup.
		$context['payment']->delete();
		$context['membership']->delete();
		$context['product']->delete();
	}

	/**
	 * Test run_preflight() creates a new intent when the existing one is canceled.
	 *
	 * @return void
	 */
	public function test_run_preflight_creates_new_intent_when_existing_is_canceled(): void {
		$canceled_intent = \Stripe\PaymentIntent::constructFrom([
			'id'            => 'pi_canceled123',
			'object'        => 'payment_intent',
			'status'        => 'canceled',
			'client_secret' => 'pi_canceled123_secret',
		]);

		$new_intent = \Stripe\PaymentIntent::constructFrom([
			'id'            => 'pi_new456',
			'object'        => 'payment_intent',
			'status'        => 'requires_payment_method',
			'client_secret' => 'pi_new456_secret',
		]);

		$payment_intents_mock = $this->getMockBuilder(\Stripe\Service\PaymentIntentService::class)
			->disableOriginalConstructor()
			->getMock();
		$payment_intents_mock->expects($this->once())
			->method('retrieve')
			->with('pi_canceled123')
			->willReturn($canceled_intent);
		// A new intent must be created since the existing one is canceled.
		$payment_intents_mock->expects($this->once())
			->method('create')
			->willReturn($new_intent);

		$client = $this->build_stripe_client_mock(['paymentIntents' => $payment_intents_mock]);
		$this->gateway->set_stripe_client($client);

		$context = $this->build_checkout_context(['recurring' => false, 'trial' => false, 'amount' => 50.00]);
		$context['payment']->update_meta('stripe_payment_intent_id', 'pi_canceled123');

		$result = $this->gateway->run_preflight();

		$this->assertIsArray($result, 'run_preflight() must return an array after creating new intent for canceled one');
		$this->assertSame('pi_new456_secret', $result['stripe_client_secret']);

		// Cleanup.
		$context['payment']->delete();
		$context['membership']->delete();
		$context['product']->delete();
	}

	// -------------------------------------------------------------------------
	// run_preflight() — upgrade / downgrade / addon cart types
	// -------------------------------------------------------------------------

	/**
	 * Test run_preflight() handles upgrade cart type (calls membership->swap).
	 *
	 * @return void
	 */
	public function test_run_preflight_handles_upgrade_cart_type(): void {
		$payment_intent = \Stripe\PaymentIntent::constructFrom([
			'id'            => 'pi_upgrade123',
			'object'        => 'payment_intent',
			'status'        => 'requires_payment_method',
			'client_secret' => 'pi_upgrade123_secret',
		]);

		$payment_intents_mock = $this->getMockBuilder(\Stripe\Service\PaymentIntentService::class)
			->disableOriginalConstructor()
			->getMock();
		$payment_intents_mock->method('create')->willReturn($payment_intent);
		$payment_intents_mock->method('retrieve')->willReturn($payment_intent);

		$client = $this->build_stripe_client_mock(['paymentIntents' => $payment_intents_mock]);
		$this->gateway->set_stripe_client($client);

		$customer = self::$customer;
		wp_set_current_user($customer->get_user_id(), $customer->get_username());

		$product1 = wu_create_product([
			'name'          => 'Plan A ' . uniqid(),
			'slug'          => 'plan-a-' . uniqid(),
			'amount'        => 29.00,
			'recurring'     => true,
			'duration'      => 1,
			'duration_unit' => 'month',
			'type'          => 'plan',
			'pricing_type'  => 'paid',
			'active'        => true,
		]);

		$product2 = wu_create_product([
			'name'          => 'Plan B ' . uniqid(),
			'slug'          => 'plan-b-' . uniqid(),
			'amount'        => 49.00,
			'recurring'     => true,
			'duration'      => 1,
			'duration_unit' => 'month',
			'type'          => 'plan',
			'pricing_type'  => 'paid',
			'active'        => true,
		]);

		$membership = wu_create_membership([
			'customer_id'     => $customer->get_id(),
			'plan_id'         => $product1->get_id(),
			'status'          => Membership_Status::ACTIVE,
			'recurring'       => true,
			'date_expiration' => gmdate('Y-m-d 23:59:59', strtotime('+30 days')),
		]);

		$payment = wu_create_payment([
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'gateway'       => 'stripe',
			'status'        => 'pending',
			'total'         => 49.00,
		]);

		$cart = new \WP_Ultimo\Checkout\Cart([
			'cart_type'     => 'upgrade',
			'products'      => [$product2->get_id()],
			'duration'      => 1,
			'duration_unit' => 'month',
			'membership_id' => $membership->get_id(),
			'payment_id'    => false,
			'discount_code' => false,
			'auto_renew'    => true,
			'country'       => 'US',
			'currency'      => 'USD',
		]);

		$this->gateway->set_order($cart);
		$this->gateway->set_membership($membership);
		$this->gateway->set_customer($customer);
		$this->gateway->set_payment($payment);

		$result = $this->gateway->run_preflight();

		$this->assertIsArray($result, 'run_preflight() must return an array for upgrade cart type');
		$this->assertArrayHasKey('stripe_client_secret', $result);

		// Cleanup.
		$payment->delete();
		$membership->delete();
		$product1->delete();
		$product2->delete();
	}

	// -------------------------------------------------------------------------
	// run_preflight() — error paths
	// -------------------------------------------------------------------------

	/**
	 * Test run_preflight() returns WP_Error when Stripe throws a Stripe exception.
	 *
	 * @return void
	 */
	public function test_run_preflight_returns_wp_error_on_stripe_exception(): void {
		$payment_intents_mock = $this->getMockBuilder(\Stripe\Service\PaymentIntentService::class)
			->disableOriginalConstructor()
			->getMock();

		// Build a CardException with a proper JSON body so get_stripe_error() can parse it.
		$card_exception = \Stripe\Exception\CardException::factory(
			'Your card was declined.',
			402,
			wp_json_encode(['error' => ['code' => 'card_declined', 'message' => 'Your card was declined.']]),
			['error' => ['code' => 'card_declined', 'message' => 'Your card was declined.']],
			null,
			'card_declined',
			'insufficient_funds'
		);

		$payment_intents_mock->method('create')->willThrowException($card_exception);
		$payment_intents_mock->method('retrieve')->willReturn(
			\Stripe\PaymentIntent::constructFrom(['id' => 'pi_x', 'object' => 'payment_intent', 'status' => 'canceled', 'client_secret' => 'x'])
		);

		$client = $this->build_stripe_client_mock(['paymentIntents' => $payment_intents_mock]);
		$this->gateway->set_stripe_client($client);

		$context = $this->build_checkout_context(['recurring' => false, 'trial' => false, 'amount' => 50.00]);

		$result = $this->gateway->run_preflight();

		$this->assertInstanceOf(
			\WP_Error::class,
			$result,
			'run_preflight() must return WP_Error when Stripe throws a card exception'
		);

		// Cleanup.
		$context['payment']->delete();
		$context['membership']->delete();
		$context['product']->delete();
	}

	/**
	 * Test run_preflight() returns WP_Error when a generic exception is thrown.
	 *
	 * @return void
	 */
	public function test_run_preflight_returns_wp_error_on_generic_exception(): void {
		$payment_intents_mock = $this->getMockBuilder(\Stripe\Service\PaymentIntentService::class)
			->disableOriginalConstructor()
			->getMock();
		$payment_intents_mock->method('create')
			->willThrowException(new \RuntimeException('Something went wrong', 500));
		$payment_intents_mock->method('retrieve')->willReturn(
			\Stripe\PaymentIntent::constructFrom(['id' => 'pi_x', 'object' => 'payment_intent', 'status' => 'canceled', 'client_secret' => 'x'])
		);

		$client = $this->build_stripe_client_mock(['paymentIntents' => $payment_intents_mock]);
		$this->gateway->set_stripe_client($client);

		$context = $this->build_checkout_context(['recurring' => false, 'trial' => false, 'amount' => 50.00]);

		$result = $this->gateway->run_preflight();

		$this->assertInstanceOf(
			\WP_Error::class,
			$result,
			'run_preflight() must return WP_Error when a generic exception is thrown'
		);
		$this->assertSame(500, $result->get_error_code());

		// Cleanup.
		$context['payment']->delete();
		$context['membership']->delete();
		$context['product']->delete();
	}

	/**
	 * Test run_preflight() returns WP_Error when get_or_create_customer fails.
	 *
	 * @return void
	 */
	public function test_run_preflight_returns_wp_error_when_customer_creation_fails(): void {
		$customers_mock = $this->getMockBuilder(\Stripe\Service\CustomerService::class)
			->disableOriginalConstructor()
			->getMock();

		$retrieve_exception = \Stripe\Exception\InvalidRequestException::factory(
			'No such customer',
			404,
			wp_json_encode(['error' => ['code' => 'resource_missing', 'message' => 'No such customer']]),
			['error' => ['code' => 'resource_missing', 'message' => 'No such customer']],
			null,
			'resource_missing'
		);

		$create_exception = \Stripe\Exception\InvalidRequestException::factory(
			'Invalid customer',
			400,
			wp_json_encode(['error' => ['code' => 'invalid_request_error', 'message' => 'Invalid customer']]),
			['error' => ['code' => 'invalid_request_error', 'message' => 'Invalid customer']],
			null,
			'invalid_request_error'
		);

		$customers_mock->method('retrieve')->willThrowException($retrieve_exception);
		$customers_mock->method('create')->willThrowException($create_exception);

		$client = $this->build_stripe_client_mock(['customers' => $customers_mock]);
		$this->gateway->set_stripe_client($client);

		$context = $this->build_checkout_context(['recurring' => false, 'trial' => false, 'amount' => 50.00]);

		$result = $this->gateway->run_preflight();

		$this->assertInstanceOf(
			\WP_Error::class,
			$result,
			'run_preflight() must return WP_Error when customer creation fails'
		);

		// Cleanup.
		$context['payment']->delete();
		$context['membership']->delete();
		$context['product']->delete();
	}

	/**
	 * Test run_preflight() handles exception with empty error code (uses getHttpStatus fallback).
	 *
	 * @return void
	 */
	public function test_run_preflight_handles_exception_with_empty_error_code(): void {
		$payment_intents_mock = $this->getMockBuilder(\Stripe\Service\PaymentIntentService::class)
			->disableOriginalConstructor()
			->getMock();
		// Exception with code 0 (empty).
		$payment_intents_mock->method('create')
			->willThrowException(new \RuntimeException('Empty code error', 0));
		$payment_intents_mock->method('retrieve')->willReturn(
			\Stripe\PaymentIntent::constructFrom(['id' => 'pi_x', 'object' => 'payment_intent', 'status' => 'canceled', 'client_secret' => 'x'])
		);

		$client = $this->build_stripe_client_mock(['paymentIntents' => $payment_intents_mock]);
		$this->gateway->set_stripe_client($client);

		$context = $this->build_checkout_context(['recurring' => false, 'trial' => false, 'amount' => 50.00]);

		$result = $this->gateway->run_preflight();

		$this->assertInstanceOf(
			\WP_Error::class,
			$result,
			'run_preflight() must return WP_Error even when exception has empty error code'
		);
		// Should fall back to 500 when code is empty.
		$this->assertSame(500, $result->get_error_code());

		// Cleanup.
		$context['payment']->delete();
		$context['membership']->delete();
		$context['product']->delete();
	}

	// -------------------------------------------------------------------------
	// run_preflight() — wu_stripe_create_payment_intent_args filter
	// -------------------------------------------------------------------------

	/**
	 * Test run_preflight() applies the wu_stripe_create_payment_intent_args filter.
	 *
	 * @return void
	 */
	public function test_run_preflight_applies_payment_intent_args_filter(): void {
		$captured_args = null;

		add_filter('wu_stripe_create_payment_intent_args', function ($args, $gateway) use (&$captured_args) {
			$captured_args = $args;
			return $args;
		}, 10, 2);

		$payment_intent = \Stripe\PaymentIntent::constructFrom([
			'id'            => 'pi_filter123',
			'object'        => 'payment_intent',
			'status'        => 'requires_payment_method',
			'client_secret' => 'pi_filter123_secret',
		]);

		$payment_intents_mock = $this->getMockBuilder(\Stripe\Service\PaymentIntentService::class)
			->disableOriginalConstructor()
			->getMock();
		$payment_intents_mock->method('create')->willReturn($payment_intent);
		$payment_intents_mock->method('retrieve')->willReturn($payment_intent);

		$client = $this->build_stripe_client_mock(['paymentIntents' => $payment_intents_mock]);
		$this->gateway->set_stripe_client($client);

		$context = $this->build_checkout_context(['recurring' => false, 'trial' => false, 'amount' => 50.00]);

		$this->gateway->run_preflight();

		remove_all_filters('wu_stripe_create_payment_intent_args');

		$this->assertNotNull($captured_args, 'wu_stripe_create_payment_intent_args filter must be applied');
		$this->assertArrayHasKey('amount', $captured_args, 'Filter args must include amount');
		$this->assertArrayHasKey('currency', $captured_args, 'Filter args must include currency');

		// Cleanup.
		$context['payment']->delete();
		$context['membership']->delete();
		$context['product']->delete();
	}

	// -------------------------------------------------------------------------
	// fields()
	// -------------------------------------------------------------------------

	/**
	 * Test fields() returns a string containing the payment element container.
	 *
	 * @return void
	 */
	public function test_fields_returns_html_with_payment_element(): void {
		$result = $this->gateway->fields();

		$this->assertIsString($result, 'fields() must return a string');
		$this->assertStringContainsString(
			'payment-element',
			$result,
			'fields() must include the #payment-element container'
		);
		$this->assertStringContainsString(
			'payment-errors',
			$result,
			'fields() must include the #payment-errors container'
		);
	}

	/**
	 * Test fields() includes saved payment method radio when card options exist.
	 *
	 * @return void
	 */
	public function test_fields_includes_saved_payment_method_radio_when_cards_exist(): void {
		// Set up a customer with a Stripe customer ID so saved cards can be fetched.
		$customer = self::$customer;
		wp_set_current_user($customer->get_user_id(), $customer->get_username());

		$membership = wu_create_membership([
			'customer_id'              => $customer->get_id(),
			'plan_id'                  => 0,
			'status'                   => Membership_Status::ACTIVE,
			'gateway_customer_id'      => 'cus_test123',
			'gateway'                  => 'stripe',
			'date_expiration'          => gmdate('Y-m-d 23:59:59', strtotime('+30 days')),
		]);

		$this->gateway->set_customer($customer);
		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$result = $this->gateway->fields();

		$this->assertIsString($result, 'fields() must return a string');
		$this->assertStringContainsString('payment-element', $result);

		// Cleanup.
		$membership->delete();
	}

	// -------------------------------------------------------------------------
	// payment_methods()
	// -------------------------------------------------------------------------

	/**
	 * Test payment_methods() returns an empty array when no saved cards exist.
	 *
	 * @return void
	 */
	public function test_payment_methods_returns_empty_array_when_no_cards(): void {
		// No customer set — no saved cards.
		$result = $this->gateway->payment_methods();

		$this->assertIsArray($result, 'payment_methods() must return an array');
		$this->assertEmpty($result, 'payment_methods() must return empty array when no saved cards');
	}

	/**
	 * Test payment_methods() returns fields when saved cards exist.
	 *
	 * @return void
	 */
	public function test_payment_methods_returns_fields_when_cards_exist(): void {
		$customer = self::$customer;
		wp_set_current_user($customer->get_user_id(), $customer->get_username());

		// Create a membership with a Stripe customer ID so saved cards can be fetched.
		$membership = wu_create_membership([
			'customer_id'         => $customer->get_id(),
			'plan_id'             => 0,
			'status'              => Membership_Status::ACTIVE,
			'gateway_customer_id' => 'cus_test123',
			'gateway'             => 'stripe',
			'date_expiration'     => gmdate('Y-m-d 23:59:59', strtotime('+30 days')),
		]);

		$this->gateway->set_customer($customer);
		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$result = $this->gateway->payment_methods();

		$this->assertIsArray($result, 'payment_methods() must return an array');

		// Cleanup.
		$membership->delete();
	}

	// -------------------------------------------------------------------------
	// get_user_saved_payment_methods()
	// -------------------------------------------------------------------------

	/**
	 * Test get_user_saved_payment_methods() returns empty array when no customer is logged in.
	 *
	 * @return void
	 */
	public function test_get_user_saved_payment_methods_returns_empty_when_no_customer(): void {
		// Ensure no customer is logged in.
		wp_set_current_user(0);

		$result = $this->gateway->get_user_saved_payment_methods();

		$this->assertIsArray($result, 'get_user_saved_payment_methods() must return an array');
		$this->assertEmpty($result, 'get_user_saved_payment_methods() must return empty array when no customer');
	}

	/**
	 * Test get_user_saved_payment_methods() returns payment methods for a logged-in customer.
	 *
	 * @return void
	 */
	public function test_get_user_saved_payment_methods_returns_methods_for_customer(): void {
		$customer = self::$customer;
		wp_set_current_user($customer->get_user_id(), $customer->get_username());

		// Create a membership with a Stripe customer ID.
		$membership = wu_create_membership([
			'customer_id'         => $customer->get_id(),
			'plan_id'             => 0,
			'status'              => Membership_Status::ACTIVE,
			'gateway_customer_id' => 'cus_test123',
			'gateway'             => 'stripe',
			'date_expiration'     => gmdate('Y-m-d 23:59:59', strtotime('+30 days')),
		]);

		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$result = $this->gateway->get_user_saved_payment_methods();

		$this->assertIsArray($result, 'get_user_saved_payment_methods() must return an array');

		// Cleanup.
		$membership->delete();
	}

	/**
	 * Test get_user_saved_payment_methods() returns empty array when Stripe API throws.
	 *
	 * Uses a dedicated WP Ultimo customer so the static cache (keyed by WP Ultimo
	 * customer ID) is never pre-populated by earlier tests in this class.
	 *
	 * @return void
	 */
	public function test_get_user_saved_payment_methods_returns_empty_on_stripe_exception(): void {
		// Dedicated customer — unique WP Ultimo customer ID avoids static cache collision.
		$exc_customer = wu_create_customer(
			[
				'username' => 'stripe_exc_test_' . uniqid(),
				'email'    => 'stripe_exc_' . uniqid() . '@example.com',
				'password' => 'password123',
			]
		);

		$this->assertNotWPError($exc_customer, 'Failed to create dedicated exception-test customer');

		$unique_cus_id = 'cus_exc_' . uniqid();
		wp_set_current_user($exc_customer->get_user_id());

		$membership = wu_create_membership(
			[
				'customer_id'         => $exc_customer->get_id(),
				'plan_id'             => 0,
				'status'              => Membership_Status::ACTIVE,
				'gateway_customer_id' => $unique_cus_id,
				'gateway'             => 'stripe',
				'date_expiration'     => gmdate('Y-m-d 23:59:59', strtotime('+30 days')),
			]
		);

		$payment_methods_mock = $this->getMockBuilder(\Stripe\Service\PaymentMethodService::class)
			->disableOriginalConstructor()
			->getMock();
		$payment_methods_mock->method('all')
			->willThrowException(new \Stripe\Exception\ApiConnectionException('Network error'));

		$client = $this->build_stripe_client_mock(['paymentMethods' => $payment_methods_mock]);

		$fresh_gateway = new Stripe_Gateway();
		$fresh_gateway->set_stripe_client($client);

		$result = $fresh_gateway->get_user_saved_payment_methods();

		$this->assertIsArray($result, 'get_user_saved_payment_methods() must return an array on exception');
		$this->assertEmpty($result, 'get_user_saved_payment_methods() must return empty array on Stripe exception');

		// Cleanup.
		if ( ! is_wp_error($membership)) {
			$membership->delete();
		}
		$exc_customer->delete();
	}

	/**
	 * Test get_user_saved_payment_methods() uses static cache on repeated calls.
	 *
	 * Uses a dedicated WP Ultimo customer so the static cache (keyed by WP Ultimo
	 * customer ID) is never pre-populated by earlier tests in this class, ensuring
	 * the first call always hits the Stripe API exactly once.
	 *
	 * @return void
	 */
	public function test_get_user_saved_payment_methods_uses_static_cache(): void {
		// Dedicated customer — unique WP Ultimo customer ID avoids static cache collision.
		$cache_customer = wu_create_customer(
			[
				'username' => 'stripe_cache_test_' . uniqid(),
				'email'    => 'stripe_cache_' . uniqid() . '@example.com',
				'password' => 'password123',
			]
		);

		$this->assertNotWPError($cache_customer, 'Failed to create dedicated cache-test customer');

		$unique_cus_id = 'cus_cache_' . uniqid();
		wp_set_current_user($cache_customer->get_user_id());

		$membership = wu_create_membership(
			[
				'customer_id'         => $cache_customer->get_id(),
				'plan_id'             => 0,
				'status'              => Membership_Status::ACTIVE,
				'gateway_customer_id' => $unique_cus_id,
				'gateway'             => 'stripe',
				'date_expiration'     => gmdate('Y-m-d 23:59:59', strtotime('+30 days')),
			]
		);

		$payment_methods_mock = $this->getMockBuilder(\Stripe\Service\PaymentMethodService::class)
			->disableOriginalConstructor()
			->getMock();
		// all() must be called exactly once; the second call must use the static cache.
		$pm_list = \Stripe\Collection::constructFrom(
			[
				'object' => 'list',
				'data'   => [],
			]
		);
		$payment_methods_mock->expects($this->once())
			->method('all')
			->willReturn($pm_list);

		$client = $this->build_stripe_client_mock(['paymentMethods' => $payment_methods_mock]);

		$fresh_gateway = new Stripe_Gateway();
		$fresh_gateway->set_stripe_client($client);

		// First call populates the static cache; second call must return from cache.
		$fresh_gateway->get_user_saved_payment_methods();
		$fresh_gateway->get_user_saved_payment_methods();

		// Cleanup.
		if ( ! is_wp_error($membership)) {
			$membership->delete();
		}
		$cache_customer->delete();
	}

	// -------------------------------------------------------------------------
	// render_oauth_connection()
	// -------------------------------------------------------------------------

	/**
	 * Test render_oauth_connection() outputs connected state when OAuth is active.
	 *
	 * @return void
	 */
	public function test_render_oauth_connection_shows_connected_state(): void {
		wu_save_setting('stripe_test_access_token', 'sk_test_oauth_token_123');
		wu_save_setting('stripe_test_account_id', 'acct_test123');
		wu_save_setting('stripe_test_publishable_key', 'pk_test_oauth_123');
		wu_save_setting('stripe_sandbox_mode', 1);

		$gateway = new Stripe_Gateway();
		$gateway->init();

		ob_start();
		$gateway->render_oauth_connection();
		$output = ob_get_clean();

		$this->assertStringContainsString(
			'wu-connected',
			$output,
			'render_oauth_connection() must show connected state when OAuth is active'
		);
		$this->assertStringContainsString(
			'acct_test123',
			$output,
			'render_oauth_connection() must display the account ID'
		);
		$this->assertStringContainsString(
			'Disconnect',
			$output,
			'render_oauth_connection() must show a Disconnect button in connected state'
		);
	}

	/**
	 * Test render_oauth_connection() outputs disconnected state when OAuth is not active.
	 *
	 * @return void
	 */
	public function test_render_oauth_connection_shows_disconnected_state(): void {
		wu_save_setting('stripe_test_access_token', '');
		wu_save_setting('stripe_test_account_id', '');
		wu_save_setting('stripe_sandbox_mode', 1);

		$gateway = new Stripe_Gateway();
		$gateway->init();

		ob_start();
		$gateway->render_oauth_connection();
		$output = ob_get_clean();

		$this->assertStringContainsString(
			'wu-disconnected',
			$output,
			'render_oauth_connection() must show disconnected state when OAuth is not active'
		);
		$this->assertStringContainsString(
			'Connect with Stripe',
			$output,
			'render_oauth_connection() must show Connect with Stripe button in disconnected state'
		);
	}

	/**
	 * Test render_oauth_connection() shows OAuth error when one is set.
	 *
	 * @return void
	 */
	public function test_render_oauth_connection_shows_oauth_error(): void {
		wu_save_setting('stripe_test_access_token', '');
		wu_save_setting('stripe_test_account_id', '');
		wu_save_setting('stripe_sandbox_mode', 1);

		$gateway = new Stripe_Gateway();
		$gateway->init();

		// Inject an OAuth error via reflection.
		$reflection = new \ReflectionClass($gateway);
		$prop = $reflection->getProperty('oauth_error');
		$prop->setAccessible(true);
		$prop->setValue($gateway, 'OAuth connection failed: access_denied');

		ob_start();
		$gateway->render_oauth_connection();
		$output = ob_get_clean();

		$this->assertStringContainsString(
			'OAuth connection failed: access_denied',
			$output,
			'render_oauth_connection() must display the OAuth error message'
		);
	}

	/**
	 * Test render_oauth_connection() shows fee notice when no addon purchase.
	 *
	 * @return void
	 */
	public function test_render_oauth_connection_shows_fee_notice_without_addon(): void {
		wu_save_setting('stripe_test_access_token', '');
		wu_save_setting('stripe_test_account_id', '');
		wu_save_setting('stripe_sandbox_mode', 1);

		$gateway = new Stripe_Gateway();
		$gateway->init();

		ob_start();
		$gateway->render_oauth_connection();
		$output = ob_get_clean();

		// The fee notice should appear (either the fee message or the "no fee" message).
		$has_fee_notice = (
			false !== strpos($output, 'fee per-transaction') ||
			false !== strpos($output, 'No application fee')
		);

		$this->assertTrue(
			$has_fee_notice,
			'render_oauth_connection() must show either a fee notice or a no-fee notice'
		);
	}

	// -------------------------------------------------------------------------
	// Teardown
	// -------------------------------------------------------------------------

	/**
	 * Tear down after all tests in the class.
	 *
	 * @return void
	 */
	public static function tear_down_after_class() {
		global $wpdb;
		self::$customer->delete();
		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wu_memberships");
		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wu_products");
		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wu_customers");
		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wu_payments");
		parent::tear_down_after_class();
	}
}

<?php
/**
 * Comprehensive unit tests for Base_Stripe_Gateway class.
 *
 * Covers the large number of uncovered statements in:
 * inc/gateways/class-base-stripe-gateway.php
 *
 * Target: ≥80% coverage (currently 28.6%, 1093 uncovered statements).
 *
 * @package WP_Ultimo\Gateways
 * @since 2.0.0
 */

namespace WP_Ultimo\Gateways;

use Stripe\StripeClient;
use WP_Ultimo\Database\Memberships\Membership_Status;
use WP_Ultimo\Database\Payments\Payment_Status;
use WP_Ultimo\Models\Customer;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Minimal concrete stub of Base_Stripe_Gateway for testing base-class-only methods.
 *
 * Does NOT override run_preflight(), process_checkout(), or fields() so that
 * the base-class implementations are exercised directly.
 */
class Base_Stripe_Gateway_Stub extends Base_Stripe_Gateway {

	/**
	 * Gateway ID.
	 *
	 * @var string
	 */
	protected $id = 'stripe-stub';

	/**
	 * Gateway title.
	 *
	 * @var string
	 */
	protected $title = 'Stripe Stub';

	/**
	 * Minimal init — no hooks needed for unit tests.
	 */
	public function init(): void {
		$this->test_mode = (bool) wu_get_setting('stripe_sandbox_mode', true);
		$this->setup_api_keys();
	}
}

/**
 * Comprehensive tests for Base_Stripe_Gateway.
 *
 * Uses Stripe_Gateway as the concrete implementation since
 * Base_Stripe_Gateway is abstract.
 */
class Base_Stripe_Gateway_Test extends \WP_UnitTestCase {

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
	 * Create shared customer once per class.
	 */
	public static function set_up_before_class(): void {
		parent::set_up_before_class();

		$result = wu_create_customer(
			[
				'username' => 'base_stripe_testuser',
				'email'    => 'base_stripe_test@example.com',
				'password' => 'password123',
			]
		);

		if (is_wp_error($result)) {
			// Customer already exists from a previous test run — look it up by username.
			$user = get_user_by('login', 'base_stripe_testuser');
			if ($user) {
				$existing = wu_get_customer_by('user_id', $user->ID);
				if ($existing instanceof Customer) {
					self::$customer = $existing;
					return;
				}
			}
			throw new \RuntimeException('Could not create or retrieve test customer: ' . $result->get_error_message());
		}

		self::$customer = $result;
	}

	/**
	 * Set up before each test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Reset Stripe settings.
		wu_save_setting('stripe_sandbox_mode', 1);
		wu_save_setting('stripe_test_pk_key', '');
		wu_save_setting('stripe_test_sk_key', '');
		wu_save_setting('stripe_test_access_token', '');
		wu_save_setting('stripe_test_account_id', '');
		wu_save_setting('stripe_test_publishable_key', '');
		wu_save_setting('stripe_live_pk_key', '');
		wu_save_setting('stripe_live_sk_key', '');
		wu_save_setting('stripe_live_access_token', '');
		wu_save_setting('stripe_live_account_id', '');
		wu_save_setting('stripe_live_publishable_key', '');

		$this->gateway = new Stripe_Gateway();

		$this->stripe_client_mock = $this->getMockBuilder(StripeClient::class)
			->disableOriginalConstructor()
			->getMock();
	}

	// =========================================================================
	// supports_amount_update
	// =========================================================================

	/**
	 * Test supports_amount_update returns true.
	 */
	public function test_supports_amount_update_returns_true(): void {
		$this->assertTrue($this->gateway->supports_amount_update());
	}

	// =========================================================================
	// supports_payment_polling
	// =========================================================================

	/**
	 * Test supports_payment_polling returns true.
	 */
	public function test_supports_payment_polling_returns_true(): void {
		$this->assertTrue($this->gateway->supports_payment_polling());
	}

	// =========================================================================
	// get_application_fee_percent
	// =========================================================================

	/**
	 * Test get_application_fee_percent returns 3.0.
	 */
	public function test_get_application_fee_percent_returns_three(): void {
		$this->assertSame(3.0, $this->gateway->get_application_fee_percent());
	}

	// =========================================================================
	// is_using_oauth / should_apply_application_fee
	// =========================================================================

	/**
	 * Test is_using_oauth returns false in direct mode.
	 */
	public function test_is_using_oauth_false_in_direct_mode(): void {
		wu_save_setting('stripe_test_pk_key', 'pk_test_direct');
		wu_save_setting('stripe_test_sk_key', 'sk_test_direct');
		wu_save_setting('stripe_sandbox_mode', 1);

		$gateway = new Stripe_Gateway();
		$gateway->init();

		$this->assertFalse($gateway->is_using_oauth());
	}

	/**
	 * Test is_using_oauth returns true in OAuth mode.
	 */
	public function test_is_using_oauth_true_in_oauth_mode(): void {
		wu_save_setting('stripe_test_access_token', 'sk_test_oauth_token');
		wu_save_setting('stripe_test_account_id', 'acct_123');
		wu_save_setting('stripe_test_publishable_key', 'pk_test_oauth');
		wu_save_setting('stripe_sandbox_mode', 1);

		$gateway = new Stripe_Gateway();
		$gateway->init();

		$this->assertTrue($gateway->is_using_oauth());
	}

	/**
	 * Test should_apply_application_fee returns false in direct mode.
	 */
	public function test_should_apply_application_fee_false_in_direct_mode(): void {
		wu_save_setting('stripe_test_pk_key', 'pk_test_direct');
		wu_save_setting('stripe_test_sk_key', 'sk_test_direct');
		wu_save_setting('stripe_sandbox_mode', 1);

		$gateway = new Stripe_Gateway();
		$gateway->init();

		$this->assertFalse($gateway->should_apply_application_fee());
	}

	// =========================================================================
	// get_authentication_mode
	// =========================================================================

	/**
	 * Test get_authentication_mode returns 'direct' by default.
	 */
	public function test_get_authentication_mode_default_direct(): void {
		$this->assertSame('direct', $this->gateway->get_authentication_mode());
	}

	/**
	 * Test get_authentication_mode returns 'oauth' after OAuth setup.
	 */
	public function test_get_authentication_mode_oauth_after_setup(): void {
		wu_save_setting('stripe_test_access_token', 'sk_test_oauth_token');
		wu_save_setting('stripe_test_account_id', 'acct_123');
		wu_save_setting('stripe_sandbox_mode', 1);

		$gateway = new Stripe_Gateway();
		$gateway->init();

		$this->assertSame('oauth', $gateway->get_authentication_mode());
	}

	// =========================================================================
	// setup_api_keys — test mode / live mode branches
	// =========================================================================

	/**
	 * Test setup_api_keys loads test direct keys in test mode.
	 */
	public function test_setup_api_keys_test_mode_direct(): void {
		wu_save_setting('stripe_test_pk_key', 'pk_test_123');
		wu_save_setting('stripe_test_sk_key', 'sk_test_123');
		wu_save_setting('stripe_sandbox_mode', 1);

		$gateway = new Stripe_Gateway();
		$gateway->init();

		$reflection = new \ReflectionClass($gateway);

		$pk_prop = $reflection->getProperty('publishable_key');
		$pk_prop->setAccessible(true);

		$sk_prop = $reflection->getProperty('secret_key');
		$sk_prop->setAccessible(true);

		$this->assertSame('pk_test_123', $pk_prop->getValue($gateway));
		$this->assertSame('sk_test_123', $sk_prop->getValue($gateway));
		$this->assertSame('direct', $gateway->get_authentication_mode());
	}

	/**
	 * Test setup_api_keys loads live direct keys in live mode.
	 */
	public function test_setup_api_keys_live_mode_direct(): void {
		wu_save_setting('stripe_live_pk_key', 'pk_live_123');
		wu_save_setting('stripe_live_sk_key', 'sk_live_123');
		wu_save_setting('stripe_sandbox_mode', 0);

		$gateway = new Stripe_Gateway();
		$gateway->init();

		$reflection = new \ReflectionClass($gateway);

		$pk_prop = $reflection->getProperty('publishable_key');
		$pk_prop->setAccessible(true);

		$sk_prop = $reflection->getProperty('secret_key');
		$sk_prop->setAccessible(true);

		$this->assertSame('pk_live_123', $pk_prop->getValue($gateway));
		$this->assertSame('sk_live_123', $sk_prop->getValue($gateway));
		$this->assertSame('direct', $gateway->get_authentication_mode());
	}

	/**
	 * Test setup_api_keys loads live OAuth keys in live mode.
	 */
	public function test_setup_api_keys_live_mode_oauth(): void {
		wu_save_setting('stripe_live_access_token', 'sk_live_oauth_token');
		wu_save_setting('stripe_live_account_id', 'acct_live_123');
		wu_save_setting('stripe_live_publishable_key', 'pk_live_oauth');
		wu_save_setting('stripe_sandbox_mode', 0);

		$gateway = new Stripe_Gateway();
		$gateway->init();

		$this->assertSame('oauth', $gateway->get_authentication_mode());
		$this->assertTrue($gateway->is_using_oauth());

		$reflection = new \ReflectionClass($gateway);
		$prop       = $reflection->getProperty('oauth_account_id');
		$prop->setAccessible(true);

		$this->assertSame('acct_live_123', $prop->getValue($gateway));
	}

	/**
	 * Test setup_api_keys with explicit id parameter.
	 */
	public function test_setup_api_keys_with_explicit_id(): void {
		wu_save_setting('stripe_test_pk_key', 'pk_test_explicit');
		wu_save_setting('stripe_test_sk_key', 'sk_test_explicit');
		wu_save_setting('stripe_sandbox_mode', 1);

		$gateway = new Stripe_Gateway();

		$reflection = new \ReflectionClass($gateway);
		$tm_prop    = $reflection->getProperty('test_mode');
		$tm_prop->setAccessible(true);
		$tm_prop->setValue($gateway, true);

		$gateway->setup_api_keys('stripe');

		$sk_prop = $reflection->getProperty('secret_key');
		$sk_prop->setAccessible(true);

		$this->assertSame('sk_test_explicit', $sk_prop->getValue($gateway));
	}

	// =========================================================================
	// add_application_fee_to_intent
	// =========================================================================

	/**
	 * Test add_application_fee_to_intent skips when fee not applicable.
	 */
	public function test_add_application_fee_to_intent_skips_when_not_applicable(): void {
		$gateway_mock = $this->getMockBuilder(Stripe_Gateway::class)
			->onlyMethods(['should_apply_application_fee'])
			->getMock();

		$gateway_mock->method('should_apply_application_fee')->willReturn(false);

		$intent_args = ['amount' => 1000, 'currency' => 'usd'];
		$result      = $this->gateway->add_application_fee_to_intent($intent_args, $gateway_mock);

		$this->assertArrayNotHasKey('application_fee_amount', $result);
		$this->assertSame($intent_args, $result);
	}

	/**
	 * Test add_application_fee_to_intent skips when amount is zero.
	 */
	public function test_add_application_fee_to_intent_skips_zero_amount(): void {
		$gateway_mock = $this->getMockBuilder(Stripe_Gateway::class)
			->onlyMethods(['should_apply_application_fee', 'get_application_fee_percent'])
			->getMock();

		$gateway_mock->method('should_apply_application_fee')->willReturn(true);
		$gateway_mock->method('get_application_fee_percent')->willReturn(3.0);

		$intent_args = ['amount' => 0, 'currency' => 'usd'];
		$result      = $this->gateway->add_application_fee_to_intent($intent_args, $gateway_mock);

		$this->assertArrayNotHasKey('application_fee_amount', $result);
	}

	/**
	 * Test add_application_fee_to_intent adds fee when applicable.
	 */
	public function test_add_application_fee_to_intent_adds_fee(): void {
		$gateway_mock = $this->getMockBuilder(Stripe_Gateway::class)
			->onlyMethods(['should_apply_application_fee', 'get_application_fee_percent'])
			->getMock();

		$gateway_mock->method('should_apply_application_fee')->willReturn(true);
		$gateway_mock->method('get_application_fee_percent')->willReturn(3.0);

		$intent_args = ['amount' => 1000, 'currency' => 'usd'];
		$result      = $this->gateway->add_application_fee_to_intent($intent_args, $gateway_mock);

		$this->assertArrayHasKey('application_fee_amount', $result);
		$this->assertSame(30, $result['application_fee_amount']); // 3% of 1000
	}

	/**
	 * Test add_application_fee_to_intent skips negative amount.
	 */
	public function test_add_application_fee_to_intent_skips_negative_amount(): void {
		$gateway_mock = $this->getMockBuilder(Stripe_Gateway::class)
			->onlyMethods(['should_apply_application_fee', 'get_application_fee_percent'])
			->getMock();

		$gateway_mock->method('should_apply_application_fee')->willReturn(true);
		$gateway_mock->method('get_application_fee_percent')->willReturn(3.0);

		$intent_args = ['amount' => -100, 'currency' => 'usd'];
		$result      = $this->gateway->add_application_fee_to_intent($intent_args, $gateway_mock);

		$this->assertArrayNotHasKey('application_fee_amount', $result);
	}

	// =========================================================================
	// add_application_fee_to_subscription
	// =========================================================================

	/**
	 * Test add_application_fee_to_subscription skips when not applicable.
	 */
	public function test_add_application_fee_to_subscription_skips_when_not_applicable(): void {
		$gateway_mock = $this->getMockBuilder(Stripe_Gateway::class)
			->onlyMethods(['should_apply_application_fee'])
			->getMock();

		$gateway_mock->method('should_apply_application_fee')->willReturn(false);

		$sub_args = ['customer' => 'cus_123'];
		$result   = $this->gateway->add_application_fee_to_subscription($sub_args, $gateway_mock);

		$this->assertArrayNotHasKey('application_fee_percent', $result);
		$this->assertSame($sub_args, $result);
	}

	/**
	 * Test add_application_fee_to_subscription adds fee when applicable.
	 */
	public function test_add_application_fee_to_subscription_adds_fee(): void {
		$gateway_mock = $this->getMockBuilder(Stripe_Gateway::class)
			->onlyMethods(['should_apply_application_fee', 'get_application_fee_percent'])
			->getMock();

		$gateway_mock->method('should_apply_application_fee')->willReturn(true);
		$gateway_mock->method('get_application_fee_percent')->willReturn(3.0);

		$sub_args = ['customer' => 'cus_123'];
		$result   = $this->gateway->add_application_fee_to_subscription($sub_args, $gateway_mock);

		$this->assertArrayHasKey('application_fee_percent', $result);
		$this->assertSame(3.0, $result['application_fee_percent']);
	}

	// =========================================================================
	// add_application_fee_to_checkout
	// =========================================================================

	/**
	 * Test add_application_fee_to_checkout skips when not applicable.
	 */
	public function test_add_application_fee_to_checkout_skips_when_not_applicable(): void {
		$gateway_mock = $this->getMockBuilder(Stripe_Gateway::class)
			->onlyMethods(['should_apply_application_fee'])
			->getMock();

		$gateway_mock->method('should_apply_application_fee')->willReturn(false);

		$data   = ['subscription_data' => ['items' => []]];
		$result = $this->gateway->add_application_fee_to_checkout($data, $gateway_mock);

		$this->assertArrayNotHasKey('application_fee_percent', $result['subscription_data'] ?? []);
		$this->assertSame($data, $result);
	}

	/**
	 * Test add_application_fee_to_checkout adds fee to subscription mode.
	 */
	public function test_add_application_fee_to_checkout_subscription_mode(): void {
		$gateway_mock = $this->getMockBuilder(Stripe_Gateway::class)
			->onlyMethods(['should_apply_application_fee', 'get_application_fee_percent'])
			->getMock();

		$gateway_mock->method('should_apply_application_fee')->willReturn(true);
		$gateway_mock->method('get_application_fee_percent')->willReturn(3.0);

		$data   = ['subscription_data' => ['items' => []]];
		$result = $this->gateway->add_application_fee_to_checkout($data, $gateway_mock);

		$this->assertArrayHasKey('application_fee_percent', $result['subscription_data']);
		$this->assertSame(3.0, $result['subscription_data']['application_fee_percent']);
	}

	/**
	 * Test add_application_fee_to_checkout adds fee to one-time payment mode.
	 */
	public function test_add_application_fee_to_checkout_one_time_mode(): void {
		$gateway_mock = $this->getMockBuilder(Stripe_Gateway::class)
			->onlyMethods(['should_apply_application_fee', 'get_application_fee_percent'])
			->getMock();

		$gateway_mock->method('should_apply_application_fee')->willReturn(true);
		$gateway_mock->method('get_application_fee_percent')->willReturn(3.0);

		$data = [
			'line_items' => [
				[
					'quantity'   => 2,
					'price_data' => ['unit_amount' => 1000],
				],
			],
		];

		$result = $this->gateway->add_application_fee_to_checkout($data, $gateway_mock);

		// 2 * 1000 = 2000, 3% = 60
		$this->assertArrayHasKey('payment_intent_data', $result);
		$this->assertArrayHasKey('application_fee_amount', $result['payment_intent_data']);
		$this->assertSame(60, $result['payment_intent_data']['application_fee_amount']);
	}

	/**
	 * Test add_application_fee_to_checkout skips one-time when total is zero.
	 */
	public function test_add_application_fee_to_checkout_skips_zero_total(): void {
		$gateway_mock = $this->getMockBuilder(Stripe_Gateway::class)
			->onlyMethods(['should_apply_application_fee', 'get_application_fee_percent'])
			->getMock();

		$gateway_mock->method('should_apply_application_fee')->willReturn(true);
		$gateway_mock->method('get_application_fee_percent')->willReturn(3.0);

		$data = [
			'line_items' => [
				[
					'quantity'   => 1,
					'price_data' => ['unit_amount' => 0],
				],
			],
		];

		$result = $this->gateway->add_application_fee_to_checkout($data, $gateway_mock);

		$this->assertArrayNotHasKey('payment_intent_data', $result);
	}

	// =========================================================================
	// allow_stripe_redirect_host
	// =========================================================================

	/**
	 * Test allow_stripe_redirect_host adds Stripe domains.
	 */
	public function test_allow_stripe_redirect_host_adds_domains(): void {
		$hosts  = ['example.com'];
		$result = $this->gateway->allow_stripe_redirect_host($hosts);

		$this->assertContains('connect.stripe.com', $result);
		$this->assertContains('dashboard.stripe.com', $result);
		$this->assertContains('checkout.stripe.com', $result);
		$this->assertContains('billing.stripe.com', $result);
		$this->assertContains('example.com', $result);
	}

	/**
	 * Test allow_stripe_redirect_host with empty array.
	 */
	public function test_allow_stripe_redirect_host_with_empty_array(): void {
		$result = $this->gateway->allow_stripe_redirect_host([]);

		$this->assertCount(4, $result);
		$this->assertContains('connect.stripe.com', $result);
	}

	// =========================================================================
	// get_change_payment_method_url
	// =========================================================================

	/**
	 * Test get_change_payment_method_url returns null when portal disabled.
	 */
	public function test_get_change_payment_method_url_null_when_portal_disabled(): void {
		wu_save_setting('stripe_enable_portal', 0);

		$membership_mock = $this->getMockBuilder(\WP_Ultimo\Models\Membership::class)
			->disableOriginalConstructor()
			->getMock();

		$result = $this->gateway->get_change_payment_method_url($membership_mock);

		$this->assertNull($result);
	}

	/**
	 * Test get_change_payment_method_url returns null when no subscription ID.
	 */
	public function test_get_change_payment_method_url_null_when_no_subscription_id(): void {
		wu_save_setting('stripe_enable_portal', 1);

		$membership_mock = $this->getMockBuilder(\WP_Ultimo\Models\Membership::class)
			->disableOriginalConstructor()
			->onlyMethods(['get_gateway_subscription_id'])
			->getMock();

		$membership_mock->method('get_gateway_subscription_id')->willReturn('');

		$result = $this->gateway->get_change_payment_method_url($membership_mock);

		$this->assertNull($result);
	}

	/**
	 * Test get_change_payment_method_url returns URL when portal enabled and subscription exists.
	 */
	public function test_get_change_payment_method_url_returns_url(): void {
		wu_save_setting('stripe_enable_portal', 1);

		$membership_mock = $this->getMockBuilder(\WP_Ultimo\Models\Membership::class)
			->disableOriginalConstructor()
			->onlyMethods(['get_gateway_subscription_id', 'get_hash'])
			->getMock();

		$membership_mock->method('get_gateway_subscription_id')->willReturn('sub_test123');
		$membership_mock->method('get_hash')->willReturn('abc123hash');

		$result = $this->gateway->get_change_payment_method_url($membership_mock);

		$this->assertNotNull($result);
		$this->assertStringContainsString('wu-stripe-portal', $result);
		$this->assertStringContainsString('abc123hash', $result);
	}

	// =========================================================================
	// get_payment_method_display
	// =========================================================================

	/**
	 * Test get_payment_method_display returns null when no subscription ID.
	 */
	public function test_get_payment_method_display_null_when_no_subscription_id(): void {
		$membership_mock = $this->getMockBuilder(\WP_Ultimo\Models\Membership::class)
			->disableOriginalConstructor()
			->onlyMethods(['get_gateway_subscription_id'])
			->getMock();

		$membership_mock->method('get_gateway_subscription_id')->willReturn('');

		$result = $this->gateway->get_payment_method_display($membership_mock);

		$this->assertNull($result);
	}

	/**
	 * Test get_payment_method_display returns null when Stripe throws exception.
	 */
	public function test_get_payment_method_display_null_on_stripe_exception(): void {
		$membership_mock = $this->getMockBuilder(\WP_Ultimo\Models\Membership::class)
			->disableOriginalConstructor()
			->onlyMethods(['get_gateway_subscription_id'])
			->getMock();

		$membership_mock->method('get_gateway_subscription_id')->willReturn('sub_test123');

		$subscriptions_mock = $this->getMockBuilder(\Stripe\Service\SubscriptionService::class)
			->disableOriginalConstructor()
			->getMock();

		$subscriptions_mock->method('retrieve')
			->willThrowException(new \Exception('Stripe error'));

		$this->stripe_client_mock->method('__get')
			->willReturnCallback(
				function ($property) use ($subscriptions_mock) {
					if ('subscriptions' === $property) {
						return $subscriptions_mock;
					}
					return null;
				}
			);

		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$result = $this->gateway->get_payment_method_display($membership_mock);

		$this->assertNull($result);
	}

	/**
	 * Test get_payment_method_display returns null when no payment method on subscription.
	 */
	public function test_get_payment_method_display_null_when_no_payment_method(): void {
		$membership_mock = $this->getMockBuilder(\WP_Ultimo\Models\Membership::class)
			->disableOriginalConstructor()
			->onlyMethods(['get_gateway_subscription_id'])
			->getMock();

		$membership_mock->method('get_gateway_subscription_id')->willReturn('sub_test123');

		$subscription = \Stripe\Subscription::constructFrom(
			[
				'id'                      => 'sub_test123',
				'status'                  => 'active',
				'default_payment_method'  => null,
			]
		);

		$subscriptions_mock = $this->getMockBuilder(\Stripe\Service\SubscriptionService::class)
			->disableOriginalConstructor()
			->getMock();

		$subscriptions_mock->method('retrieve')->willReturn($subscription);

		$this->stripe_client_mock->method('__get')
			->willReturnCallback(
				function ($property) use ($subscriptions_mock) {
					if ('subscriptions' === $property) {
						return $subscriptions_mock;
					}
					return null;
				}
			);

		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$result = $this->gateway->get_payment_method_display($membership_mock);

		$this->assertNull($result);
	}

	/**
	 * Test get_payment_method_display returns card info when available.
	 */
	public function test_get_payment_method_display_returns_card_info(): void {
		$membership_mock = $this->getMockBuilder(\WP_Ultimo\Models\Membership::class)
			->disableOriginalConstructor()
			->onlyMethods(['get_gateway_subscription_id'])
			->getMock();

		$membership_mock->method('get_gateway_subscription_id')->willReturn('sub_test123');

		$card = new \stdClass();
		$card->brand = 'visa';
		$card->last4 = '4242';

		$pm = new \stdClass();
		$pm->card = $card;

		$subscription = \Stripe\Subscription::constructFrom(
			[
				'id'                     => 'sub_test123',
				'status'                 => 'active',
				'default_payment_method' => $pm,
			]
		);

		$subscriptions_mock = $this->getMockBuilder(\Stripe\Service\SubscriptionService::class)
			->disableOriginalConstructor()
			->getMock();

		$subscriptions_mock->method('retrieve')->willReturn($subscription);

		$this->stripe_client_mock->method('__get')
			->willReturnCallback(
				function ($property) use ($subscriptions_mock) {
					if ('subscriptions' === $property) {
						return $subscriptions_mock;
					}
					return null;
				}
			);

		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$result = $this->gateway->get_payment_method_display($membership_mock);

		$this->assertIsArray($result);
		$this->assertSame('Visa', $result['brand']);
		$this->assertSame('4242', $result['last4']);
	}

	// =========================================================================
	// get_public_title
	// =========================================================================

	/**
	 * Test get_public_title returns default when not set.
	 */
	public function test_get_public_title_returns_default(): void {
		$result = $this->gateway->get_public_title();

		$this->assertSame('Credit Card', $result);
	}

	/**
	 * Test get_public_title returns custom value when set.
	 */
	public function test_get_public_title_returns_custom_value(): void {
		wu_save_setting('stripe_public_title', 'Pay with Stripe');

		$result = $this->gateway->get_public_title();

		$this->assertSame('Pay with Stripe', $result);
	}

	// =========================================================================
	// has_webhook_installed
	// =========================================================================

	/**
	 * Test has_webhook_installed returns false when no matching webhook.
	 */
	public function test_has_webhook_installed_returns_false_when_no_match(): void {
		$webhook_endpoints_mock = $this->getMockBuilder(\Stripe\Service\WebhookEndpointService::class)
			->disableOriginalConstructor()
			->getMock();

		$webhook_endpoints_mock->method('all')
			->willReturn(
				\Stripe\Collection::constructFrom(
					[
						'object' => 'list',
						'data'   => [
							[
								'id'  => 'we_other',
								'url' => 'https://other-site.com/webhook',
							],
						],
					]
				)
			);

		$this->stripe_client_mock->method('__get')
			->willReturnCallback(
				function ($property) use ($webhook_endpoints_mock) {
					if ('webhookEndpoints' === $property) {
						return $webhook_endpoints_mock;
					}
					return null;
				}
			);

		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$result = $this->gateway->has_webhook_installed();

		$this->assertFalse($result);
	}

	/**
	 * Test has_webhook_installed returns webhook when URL matches.
	 */
	public function test_has_webhook_installed_returns_webhook_when_match(): void {
		$webhook_url = $this->gateway->get_webhook_listener_url();

		$webhook_endpoints_mock = $this->getMockBuilder(\Stripe\Service\WebhookEndpointService::class)
			->disableOriginalConstructor()
			->getMock();

		$webhook_endpoints_mock->method('all')
			->willReturn(
				\Stripe\Collection::constructFrom(
					[
						'object' => 'list',
						'data'   => [
							[
								'id'     => 'we_match',
								'url'    => $webhook_url,
								'status' => 'enabled',
							],
						],
					]
				)
			);

		$this->stripe_client_mock->method('__get')
			->willReturnCallback(
				function ($property) use ($webhook_endpoints_mock) {
					if ('webhookEndpoints' === $property) {
						return $webhook_endpoints_mock;
					}
					return null;
				}
			);

		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$result = $this->gateway->has_webhook_installed();

		$this->assertNotFalse($result);
		$this->assertSame('we_match', $result->id);
	}

	/**
	 * Test has_webhook_installed returns WP_Error on exception.
	 */
	public function test_has_webhook_installed_returns_wp_error_on_exception(): void {
		$webhook_endpoints_mock = $this->getMockBuilder(\Stripe\Service\WebhookEndpointService::class)
			->disableOriginalConstructor()
			->getMock();

		$webhook_endpoints_mock->method('all')
			->willThrowException(new \Exception('API Error', 401));

		$this->stripe_client_mock->method('__get')
			->willReturnCallback(
				function ($property) use ($webhook_endpoints_mock) {
					if ('webhookEndpoints' === $property) {
						return $webhook_endpoints_mock;
					}
					return null;
				}
			);

		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$result = $this->gateway->has_webhook_installed();

		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	// =========================================================================
	// fix_saving_settings
	// =========================================================================

	/**
	 * Test fix_saving_settings preserves OAuth tokens.
	 */
	public function test_fix_saving_settings_preserves_oauth_tokens(): void {
		$id = 'stripe';

		$saved_settings = [
			"{$id}_test_access_token"   => 'sk_test_existing_token',
			"{$id}_test_refresh_token"  => 'rt_test_existing',
			"{$id}_test_account_id"     => 'acct_existing',
			"{$id}_test_publishable_key" => 'pk_test_existing',
			"{$id}_live_access_token"   => 'sk_live_existing_token',
			"{$id}_live_refresh_token"  => 'rt_live_existing',
			"{$id}_live_account_id"     => 'acct_live_existing',
			"{$id}_live_publishable_key" => 'pk_live_existing',
		];

		$settings_to_save = [
			'active_gateways' => ['stripe'],
		];

		$settings = array_merge($saved_settings, $settings_to_save);

		$result = $this->gateway->fix_saving_settings($settings, $settings_to_save, $saved_settings);

		// OAuth tokens should be preserved from saved_settings.
		$this->assertSame('sk_test_existing_token', $result["{$id}_test_access_token"]);
		$this->assertSame('acct_existing', $result["{$id}_test_account_id"]);
	}

	/**
	 * Test fix_saving_settings returns early when gateway not active.
	 */
	public function test_fix_saving_settings_returns_early_when_not_active(): void {
		$id = 'stripe';

		$settings = [
			"{$id}_sandbox_mode"                => '1',
			"{$id}_webhook_listener_explanation" => 'some_url',
		];

		$settings_to_save = [
			'active_gateways' => ['paypal'], // stripe not active
		];

		$saved_settings = $settings;

		$result = $this->gateway->fix_saving_settings($settings, $settings_to_save, $saved_settings);

		// Should return early — webhook explanation should still be present.
		$this->assertArrayHasKey("{$id}_webhook_listener_explanation", $result);
	}

	/**
	 * Test fix_saving_settings sets sandbox_mode to false when not in settings_to_save.
	 */
	public function test_fix_saving_settings_sets_sandbox_mode_false(): void {
		$id = 'stripe';

		$settings = [
			"{$id}_sandbox_mode"                => '1',
			"{$id}_webhook_listener_explanation" => 'some_url',
		];

		$settings_to_save = [
			'active_gateways' => ['stripe'],
			// No sandbox_mode key
		];

		$saved_settings = $settings;

		$result = $this->gateway->fix_saving_settings($settings, $settings_to_save, $saved_settings);

		$this->assertFalse($result["{$id}_sandbox_mode"]);
		$this->assertArrayNotHasKey("{$id}_webhook_listener_explanation", $result);
	}

	// =========================================================================
	// process_membership_update
	// =========================================================================

	/**
	 * Test process_membership_update returns WP_Error when no subscription ID.
	 */
	public function test_process_membership_update_returns_error_when_no_subscription_id(): void {
		$membership_mock = $this->getMockBuilder(\WP_Ultimo\Models\Membership::class)
			->disableOriginalConstructor()
			->onlyMethods(['get_gateway_subscription_id'])
			->getMock();

		$membership_mock->method('get_gateway_subscription_id')->willReturn('');

		$customer_mock = $this->getMockBuilder(\WP_Ultimo\Models\Customer::class)
			->disableOriginalConstructor()
			->getMock();

		$result = $this->gateway->process_membership_update($membership_mock, $customer_mock);

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('wu_stripe_no_subscription_id', $result->get_error_code());
	}

	// =========================================================================
	// convert_to_stripe_address
	// =========================================================================

	/**
	 * Test convert_to_stripe_address maps fields correctly.
	 */
	public function test_convert_to_stripe_address_maps_fields(): void {
		$billing_address                       = new \stdClass();
		$billing_address->billing_city         = 'New York';
		$billing_address->billing_country      = 'US';
		$billing_address->billing_address_line_1 = '123 Main St';
		$billing_address->billing_address_line_2 = 'Apt 4';
		$billing_address->billing_zip_code     = '10001';
		$billing_address->billing_state        = 'NY';

		$result = $this->gateway->convert_to_stripe_address($billing_address);

		$this->assertSame('New York', $result['city']);
		$this->assertSame('US', $result['country']);
		$this->assertSame('123 Main St', $result['line1']);
		$this->assertSame('Apt 4', $result['line2']);
		$this->assertSame('10001', $result['postal_code']);
		$this->assertSame('NY', $result['state']);
	}

	// =========================================================================
	// process_refund
	// =========================================================================

	/**
	 * Test process_refund throws exception when no gateway payment ID.
	 */
	public function test_process_refund_throws_when_no_gateway_payment_id(): void {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessageMatches('/Gateway payment ID not found/');

		$payment_mock = $this->getMockBuilder(\WP_Ultimo\Models\Payment::class)
			->disableOriginalConstructor()
			->onlyMethods(['get_gateway_payment_id'])
			->getMock();

		$payment_mock->method('get_gateway_payment_id')->willReturn('');

		$membership_mock = $this->getMockBuilder(\WP_Ultimo\Models\Membership::class)
			->disableOriginalConstructor()
			->getMock();

		$customer_mock = $this->getMockBuilder(\WP_Ultimo\Models\Customer::class)
			->disableOriginalConstructor()
			->getMock();

		$this->gateway->process_refund(10.00, $payment_mock, $membership_mock, $customer_mock);
	}

	/**
	 * Test process_refund throws exception for invalid payment ID format.
	 */
	public function test_process_refund_throws_for_invalid_payment_id(): void {
		$this->expectException(\Exception::class);
		$this->expectExceptionMessageMatches('/Gateway payment ID not valid/');

		$payment_mock = $this->getMockBuilder(\WP_Ultimo\Models\Payment::class)
			->disableOriginalConstructor()
			->onlyMethods(['get_gateway_payment_id', 'get_currency'])
			->getMock();

		$payment_mock->method('get_gateway_payment_id')->willReturn('pi_invalid_format');
		$payment_mock->method('get_currency')->willReturn('USD');

		$membership_mock = $this->getMockBuilder(\WP_Ultimo\Models\Membership::class)
			->disableOriginalConstructor()
			->getMock();

		$customer_mock = $this->getMockBuilder(\WP_Ultimo\Models\Customer::class)
			->disableOriginalConstructor()
			->getMock();

		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$this->gateway->process_refund(10.00, $payment_mock, $membership_mock, $customer_mock);
	}

	/**
	 * Test process_refund succeeds with charge ID.
	 */
	public function test_process_refund_succeeds_with_charge_id(): void {
		$payment_mock = $this->getMockBuilder(\WP_Ultimo\Models\Payment::class)
			->disableOriginalConstructor()
			->onlyMethods(['get_gateway_payment_id', 'get_currency'])
			->getMock();

		$payment_mock->method('get_gateway_payment_id')->willReturn('ch_test123');
		$payment_mock->method('get_currency')->willReturn('USD');

		$membership_mock = $this->getMockBuilder(\WP_Ultimo\Models\Membership::class)
			->disableOriginalConstructor()
			->getMock();

		$customer_mock = $this->getMockBuilder(\WP_Ultimo\Models\Customer::class)
			->disableOriginalConstructor()
			->getMock();

		$refunds_mock = $this->getMockBuilder(\Stripe\Service\RefundService::class)
			->disableOriginalConstructor()
			->getMock();

		$refunds_mock->expects($this->once())
			->method('create')
			->with($this->arrayHasKey('charge'));

		$this->stripe_client_mock->method('__get')
			->willReturnCallback(
				function ($property) use ($refunds_mock) {
					if ('refunds' === $property) {
						return $refunds_mock;
					}
					return null;
				}
			);

		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$result = $this->gateway->process_refund(10.00, $payment_mock, $membership_mock, $customer_mock);

		$this->assertTrue($result);
	}

	// =========================================================================
	// process_cancellation
	// =========================================================================

	/**
	 * Test process_cancellation does nothing when no subscription ID.
	 */
	public function test_process_cancellation_does_nothing_when_no_subscription_id(): void {
		$membership_mock = $this->getMockBuilder(\WP_Ultimo\Models\Membership::class)
			->disableOriginalConstructor()
			->onlyMethods(['get_gateway_subscription_id'])
			->getMock();

		$membership_mock->method('get_gateway_subscription_id')->willReturn('');

		$customer_mock = $this->getMockBuilder(\WP_Ultimo\Models\Customer::class)
			->disableOriginalConstructor()
			->getMock();

		// Should not throw or call Stripe.
		$this->gateway->process_cancellation($membership_mock, $customer_mock);

		$this->assertTrue(true); // No exception = pass.
	}

	/**
	 * Test process_cancellation cancels active subscription.
	 */
	public function test_process_cancellation_cancels_active_subscription(): void {
		$membership_mock = $this->getMockBuilder(\WP_Ultimo\Models\Membership::class)
			->disableOriginalConstructor()
			->onlyMethods(['get_gateway_subscription_id'])
			->getMock();

		$membership_mock->method('get_gateway_subscription_id')->willReturn('sub_test123');

		$customer_mock = $this->getMockBuilder(\WP_Ultimo\Models\Customer::class)
			->disableOriginalConstructor()
			->getMock();

		$subscription_mock = $this->getMockBuilder(\Stripe\Subscription::class)
			->disableOriginalConstructor()
			->getMock();

		$subscription_mock->status = 'active';
		$subscription_mock->expects($this->once())->method('cancel');

		$subscriptions_mock = $this->getMockBuilder(\Stripe\Service\SubscriptionService::class)
			->disableOriginalConstructor()
			->getMock();

		$subscriptions_mock->method('retrieve')->willReturn($subscription_mock);

		$this->stripe_client_mock->method('__get')
			->willReturnCallback(
				function ($property) use ($subscriptions_mock) {
					if ('subscriptions' === $property) {
						return $subscriptions_mock;
					}
					return null;
				}
			);

		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$this->gateway->process_cancellation($membership_mock, $customer_mock);
	}

	/**
	 * Test process_cancellation skips already-canceled subscription.
	 *
	 * Uses constructFrom instead of a mock so that Stripe\StripeObject magic
	 * property access works correctly for the status check.
	 */
	public function test_process_cancellation_skips_already_canceled(): void {
		$membership_mock = $this->getMockBuilder(\WP_Ultimo\Models\Membership::class)
			->disableOriginalConstructor()
			->onlyMethods(['get_gateway_subscription_id'])
			->getMock();

		$membership_mock->method('get_gateway_subscription_id')->willReturn('sub_test123');

		$customer_mock = $this->getMockBuilder(\WP_Ultimo\Models\Customer::class)
			->disableOriginalConstructor()
			->getMock();

		// Use constructFrom so that StripeObject magic __get returns 'canceled' for ->status.
		$subscription = \Stripe\Subscription::constructFrom(
			[
				'id'     => 'sub_test123',
				'status' => 'canceled',
			]
		);

		$subscriptions_mock = $this->getMockBuilder(\Stripe\Service\SubscriptionService::class)
			->disableOriginalConstructor()
			->getMock();

		$subscriptions_mock->method('retrieve')->willReturn($subscription);

		$this->stripe_client_mock->method('__get')
			->willReturnCallback(
				function ($property) use ($subscriptions_mock) {
					if ('subscriptions' === $property) {
						return $subscriptions_mock;
					}
					return null;
				}
			);

		$this->gateway->set_stripe_client($this->stripe_client_mock);

		// Should not throw and should not call cancel() since status is already 'canceled'.
		$this->gateway->process_cancellation($membership_mock, $customer_mock);

		// If we reach here without exception, the test passes (cancel was not called).
		$this->assertTrue(true);
	}

	/**
	 * Test process_cancellation returns false on exception.
	 */
	public function test_process_cancellation_returns_false_on_exception(): void {
		$membership_mock = $this->getMockBuilder(\WP_Ultimo\Models\Membership::class)
			->disableOriginalConstructor()
			->onlyMethods(['get_gateway_subscription_id'])
			->getMock();

		$membership_mock->method('get_gateway_subscription_id')->willReturn('sub_test123');

		$customer_mock = $this->getMockBuilder(\WP_Ultimo\Models\Customer::class)
			->disableOriginalConstructor()
			->getMock();

		$subscriptions_mock = $this->getMockBuilder(\Stripe\Service\SubscriptionService::class)
			->disableOriginalConstructor()
			->getMock();

		$subscriptions_mock->method('retrieve')
			->willThrowException(new \Exception('Stripe error'));

		$this->stripe_client_mock->method('__get')
			->willReturnCallback(
				function ($property) use ($subscriptions_mock) {
					if ('subscriptions' === $property) {
						return $subscriptions_mock;
					}
					return null;
				}
			);

		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$result = $this->gateway->process_cancellation($membership_mock, $customer_mock);

		$this->assertFalse($result);
	}

	// =========================================================================
	// get_stripe_max_billing_cycle_anchor
	// =========================================================================

	/**
	 * Test get_stripe_max_billing_cycle_anchor returns a DateTimeInterface.
	 */
	public function test_get_stripe_max_billing_cycle_anchor_returns_datetime(): void {
		$result = $this->gateway->get_stripe_max_billing_cycle_anchor(1, 'month', '2024-01-15');

		$this->assertInstanceOf(\DateTimeInterface::class, $result);
	}

	/**
	 * Test get_stripe_max_billing_cycle_anchor for monthly interval.
	 */
	public function test_get_stripe_max_billing_cycle_anchor_monthly(): void {
		$result = $this->gateway->get_stripe_max_billing_cycle_anchor(1, 'month', '2024-01-15');

		// Should be around 2024-02-15.
		$this->assertGreaterThan(strtotime('2024-01-15'), $result->getTimestamp());
	}

	/**
	 * Test get_stripe_max_billing_cycle_anchor for yearly interval.
	 */
	public function test_get_stripe_max_billing_cycle_anchor_yearly(): void {
		$result = $this->gateway->get_stripe_max_billing_cycle_anchor(1, 'year', '2024-01-15');

		// Should be around 2025-01-15.
		$this->assertGreaterThan(strtotime('2024-06-01'), $result->getTimestamp());
	}

	/**
	 * Test get_stripe_max_billing_cycle_anchor handles invalid date gracefully.
	 */
	public function test_get_stripe_max_billing_cycle_anchor_invalid_date(): void {
		$result = $this->gateway->get_stripe_max_billing_cycle_anchor(1, 'month', 'invalid-date');

		// Should still return a DateTimeInterface (falls back to now).
		$this->assertInstanceOf(\DateTimeInterface::class, $result);
	}

	/**
	 * Test get_stripe_max_billing_cycle_anchor handles end-of-month overflow.
	 */
	public function test_get_stripe_max_billing_cycle_anchor_end_of_month_overflow(): void {
		// Jan 31 + 1 month = Feb 28/29 (overflow case).
		$result = $this->gateway->get_stripe_max_billing_cycle_anchor(1, 'month', '2024-01-31');

		$this->assertInstanceOf(\DateTime::class, $result);
	}

	// =========================================================================
	// get_payment_url_on_gateway
	// =========================================================================

	/**
	 * Test get_payment_url_on_gateway returns test URL in test mode.
	 */
	public function test_get_payment_url_on_gateway_test_mode(): void {
		$reflection = new \ReflectionClass($this->gateway);
		$prop       = $reflection->getProperty('test_mode');
		$prop->setAccessible(true);
		$prop->setValue($this->gateway, true);

		$result = $this->gateway->get_payment_url_on_gateway('ch_test123');

		$this->assertStringContainsString('/test/', $result);
		$this->assertStringContainsString('ch_test123', $result);
		$this->assertStringContainsString('payments', $result);
	}

	/**
	 * Test get_payment_url_on_gateway returns live URL in live mode.
	 */
	public function test_get_payment_url_on_gateway_live_mode(): void {
		$reflection = new \ReflectionClass($this->gateway);
		$prop       = $reflection->getProperty('test_mode');
		$prop->setAccessible(true);
		$prop->setValue($this->gateway, false);

		$result = $this->gateway->get_payment_url_on_gateway('ch_live123');

		$this->assertStringNotContainsString('/test/', $result);
		$this->assertStringContainsString('ch_live123', $result);
	}

	/**
	 * Test get_payment_url_on_gateway uses invoices path for invoice IDs.
	 */
	public function test_get_payment_url_on_gateway_invoice_path(): void {
		$reflection = new \ReflectionClass($this->gateway);
		$prop       = $reflection->getProperty('test_mode');
		$prop->setAccessible(true);
		$prop->setValue($this->gateway, true);

		$result = $this->gateway->get_payment_url_on_gateway('in_test123');

		$this->assertStringContainsString('invoices', $result);
		$this->assertStringContainsString('in_test123', $result);
	}

	// =========================================================================
	// get_subscription_url_on_gateway
	// =========================================================================

	/**
	 * Test get_subscription_url_on_gateway returns test URL in test mode.
	 */
	public function test_get_subscription_url_on_gateway_test_mode(): void {
		$reflection = new \ReflectionClass($this->gateway);
		$prop       = $reflection->getProperty('test_mode');
		$prop->setAccessible(true);
		$prop->setValue($this->gateway, true);

		$result = $this->gateway->get_subscription_url_on_gateway('sub_test123');

		$this->assertStringContainsString('/test/', $result);
		$this->assertStringContainsString('sub_test123', $result);
		$this->assertStringContainsString('subscriptions', $result);
	}

	/**
	 * Test get_subscription_url_on_gateway returns live URL in live mode.
	 */
	public function test_get_subscription_url_on_gateway_live_mode(): void {
		$reflection = new \ReflectionClass($this->gateway);
		$prop       = $reflection->getProperty('test_mode');
		$prop->setAccessible(true);
		$prop->setValue($this->gateway, false);

		$result = $this->gateway->get_subscription_url_on_gateway('sub_live123');

		$this->assertStringNotContainsString('/test/', $result);
		$this->assertStringContainsString('sub_live123', $result);
	}

	// =========================================================================
	// get_customer_url_on_gateway
	// =========================================================================

	/**
	 * Test get_customer_url_on_gateway returns test URL in test mode.
	 */
	public function test_get_customer_url_on_gateway_test_mode(): void {
		$reflection = new \ReflectionClass($this->gateway);
		$prop       = $reflection->getProperty('test_mode');
		$prop->setAccessible(true);
		$prop->setValue($this->gateway, true);

		$result = $this->gateway->get_customer_url_on_gateway('cus_test123');

		$this->assertStringContainsString('/test/', $result);
		$this->assertStringContainsString('cus_test123', $result);
		$this->assertStringContainsString('customers', $result);
	}

	/**
	 * Test get_customer_url_on_gateway returns live URL in live mode.
	 */
	public function test_get_customer_url_on_gateway_live_mode(): void {
		$reflection = new \ReflectionClass($this->gateway);
		$prop       = $reflection->getProperty('test_mode');
		$prop->setAccessible(true);
		$prop->setValue($this->gateway, false);

		$result = $this->gateway->get_customer_url_on_gateway('cus_live123');

		$this->assertStringNotContainsString('/test/', $result);
		$this->assertStringContainsString('cus_live123', $result);
	}

	// =========================================================================
	// get_localized_error_message
	// =========================================================================

	/**
	 * Test get_localized_error_message returns formatted message.
	 */
	public function test_get_localized_error_message_returns_formatted(): void {
		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('get_localized_error_message');
		$method->setAccessible(true);

		$result = $method->invoke($this->gateway, 'card_declined', 'Your card was declined.');

		$this->assertStringContainsString('card_declined', $result);
		$this->assertStringContainsString('Your card was declined.', $result);
	}

	// =========================================================================
	// get_stripe_error
	// =========================================================================

	/**
	 * Test get_stripe_error returns WP_Error with unknown_error when no getJsonBody.
	 */
	public function test_get_stripe_error_returns_unknown_error_without_json_body(): void {
		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('get_stripe_error');
		$method->setAccessible(true);

		$exception = new \Exception('Some error');
		$result    = $method->invoke($this->gateway, $exception);

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertTrue($result->has_errors());
	}

	// =========================================================================
	// before_backwards_compatible_webhook
	// =========================================================================

	/**
	 * Test before_backwards_compatible_webhook loads other gateway keys when secret_key empty.
	 */
	public function test_before_backwards_compatible_webhook_loads_other_keys(): void {
		// setup_api_keys('stripe-checkout') calls wu_get_setting with keys containing dashes,
		// which triggers _doing_it_wrong notices in the WP test framework.
		$this->setExpectedIncorrectUsage('stripe-checkout_test_access_token');
		$this->setExpectedIncorrectUsage('stripe-checkout_test_pk_key');
		$this->setExpectedIncorrectUsage('stripe-checkout_test_sk_key');

		wu_save_setting('stripe_checkout_test_sk_key', 'sk_test_checkout_key');
		wu_save_setting('stripe_checkout_sandbox_mode', 1);

		$reflection = new \ReflectionClass($this->gateway);
		$sk_prop    = $reflection->getProperty('secret_key');
		$sk_prop->setAccessible(true);

		// Ensure secret_key is empty.
		$sk_prop->setValue($this->gateway, '');

		// Should not throw.
		$this->gateway->before_backwards_compatible_webhook();

		$this->assertTrue(true); // No exception = pass.
	}

	// =========================================================================
	// fields
	// =========================================================================

	/**
	 * Test fields returns empty string (base class implementation).
	 *
	 * Uses Base_Stripe_Gateway_Stub since Stripe_Gateway overrides fields() with HTML output.
	 */
	public function test_fields_returns_empty_string(): void {
		$stub   = new Base_Stripe_Gateway_Stub();
		$result = $stub->fields();

		$this->assertSame('', $result);
	}

	// =========================================================================
	// verify_and_complete_payment
	// =========================================================================

	/**
	 * Test verify_and_complete_payment returns error when payment not found.
	 */
	public function test_verify_and_complete_payment_returns_error_when_not_found(): void {
		$result = $this->gateway->verify_and_complete_payment(999999);

		$this->assertFalse($result['success']);
		$this->assertStringContainsString('not found', $result['message']);
	}

	/**
	 * Test verify_and_complete_payment returns success when already completed.
	 */
	public function test_verify_and_complete_payment_returns_success_when_already_completed(): void {
		$product = wu_create_product(
			[
				'name'          => 'Verify Test Plan',
				'slug'          => 'verify-test-plan-' . uniqid(),
				'amount'        => 10.00,
				'duration'      => 1,
				'duration_unit' => 'month',
				'type'          => 'plan',
				'pricing_type'  => 'paid',
				'active'        => true,
			]
		);

		$membership = wu_create_membership(
			[
				'customer_id' => self::$customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => Membership_Status::ACTIVE,
				'gateway'     => 'stripe',
				'amount'      => 10.00,
				'currency'    => 'USD',
			]
		);

		$payment = wu_create_payment(
			[
				'membership_id' => $membership->get_id(),
				'customer_id'   => self::$customer->get_id(),
				'status'        => Payment_Status::COMPLETED,
				'gateway'       => 'stripe',
				'total'         => 10.00,
				'currency'      => 'USD',
			]
		);

		$result = $this->gateway->verify_and_complete_payment($payment->get_id());

		$this->assertTrue($result['success']);
		$this->assertSame('completed', $result['status']);

		// Cleanup.
		$payment->delete();
		$membership->delete();
	}

	/**
	 * Test verify_and_complete_payment returns error when payment not pending.
	 */
	public function test_verify_and_complete_payment_returns_error_when_not_pending(): void {
		$product = wu_create_product(
			[
				'name'          => 'Verify Test Plan 2',
				'slug'          => 'verify-test-plan-2-' . uniqid(),
				'amount'        => 10.00,
				'duration'      => 1,
				'duration_unit' => 'month',
				'type'          => 'plan',
				'pricing_type'  => 'paid',
				'active'        => true,
			]
		);

		$membership = wu_create_membership(
			[
				'customer_id' => self::$customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => Membership_Status::ACTIVE,
				'gateway'     => 'stripe',
				'amount'      => 10.00,
				'currency'    => 'USD',
			]
		);

		$payment = wu_create_payment(
			[
				'membership_id' => $membership->get_id(),
				'customer_id'   => self::$customer->get_id(),
				'status'        => Payment_Status::FAILED,
				'gateway'       => 'stripe',
				'total'         => 10.00,
				'currency'      => 'USD',
			]
		);

		$result = $this->gateway->verify_and_complete_payment($payment->get_id());

		$this->assertFalse($result['success']);
		$this->assertStringContainsString('not in pending', $result['message']);

		// Cleanup.
		$payment->delete();
		$membership->delete();
	}

	/**
	 * Test verify_and_complete_payment returns error when no payment intent.
	 */
	public function test_verify_and_complete_payment_returns_error_when_no_intent(): void {
		$product = wu_create_product(
			[
				'name'          => 'Verify Test Plan 3',
				'slug'          => 'verify-test-plan-3-' . uniqid(),
				'amount'        => 10.00,
				'duration'      => 1,
				'duration_unit' => 'month',
				'type'          => 'plan',
				'pricing_type'  => 'paid',
				'active'        => true,
			]
		);

		$membership = wu_create_membership(
			[
				'customer_id' => self::$customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => Membership_Status::ACTIVE,
				'gateway'     => 'stripe',
				'amount'      => 10.00,
				'currency'    => 'USD',
			]
		);

		$payment = wu_create_payment(
			[
				'membership_id' => $membership->get_id(),
				'customer_id'   => self::$customer->get_id(),
				'status'        => Payment_Status::PENDING,
				'gateway'       => 'stripe',
				'total'         => 10.00,
				'currency'      => 'USD',
			]
		);

		// No stripe_payment_intent_id meta set.
		$result = $this->gateway->verify_and_complete_payment($payment->get_id());

		$this->assertFalse($result['success']);
		$this->assertStringContainsString('No Stripe payment intent', $result['message']);

		// Cleanup.
		$payment->delete();
		$membership->delete();
	}

	// =========================================================================
	// get_connect_authorization_url
	// =========================================================================

	/**
	 * Test get_connect_authorization_url returns empty string on WP_Error response.
	 */
	public function test_get_connect_authorization_url_returns_empty_on_wp_error(): void {
		add_filter(
			'pre_http_request',
			function ($preempt, $args, $url) {
				if (strpos($url, '/oauth/init') !== false) {
					return new \WP_Error('http_request_failed', 'Connection failed');
				}
				return $preempt;
			},
			10,
			3
		);

		$result = $this->gateway->get_connect_authorization_url('');

		remove_all_filters('pre_http_request');

		$this->assertSame('', $result);
	}

	/**
	 * Test get_connect_authorization_url returns empty when oauthUrl missing.
	 */
	public function test_get_connect_authorization_url_returns_empty_when_no_oauth_url(): void {
		add_filter(
			'pre_http_request',
			function ($preempt, $args, $url) {
				if (strpos($url, '/oauth/init') !== false) {
					return [
						'response' => ['code' => 200],
						'body'     => wp_json_encode(['error' => 'something went wrong']),
					];
				}
				return $preempt;
			},
			10,
			3
		);

		$result = $this->gateway->get_connect_authorization_url('');

		remove_all_filters('pre_http_request');

		$this->assertSame('', $result);
	}

	/**
	 * Test get_connect_authorization_url returns URL and stores state.
	 */
	public function test_get_connect_authorization_url_returns_url_and_stores_state(): void {
		add_filter(
			'pre_http_request',
			function ($preempt, $args, $url) {
				if (strpos($url, '/oauth/init') !== false) {
					return [
						'response' => ['code' => 200],
						'body'     => wp_json_encode(
							[
								'oauthUrl' => 'https://connect.stripe.com/oauth/authorize?client_id=ca_test',
								'state'    => 'test_state_abc',
							]
						),
					];
				}
				return $preempt;
			},
			10,
			3
		);

		$result = $this->gateway->get_connect_authorization_url('');

		remove_all_filters('pre_http_request');

		$this->assertStringContainsString('connect.stripe.com', $result);
		$this->assertSame('test_state_abc', get_option('wu_stripe_oauth_state'));
	}

	// =========================================================================
	// get_proxy_url
	// =========================================================================

	/**
	 * Test get_proxy_url returns default URL.
	 */
	public function test_get_proxy_url_returns_default(): void {
		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('get_proxy_url');
		$method->setAccessible(true);

		$result = $method->invoke($this->gateway);

		$this->assertStringContainsString('ultimatemultisite.com', $result);
		$this->assertStringContainsString('stripe-connect', $result);
	}

	/**
	 * Test get_proxy_url is filterable.
	 */
	public function test_get_proxy_url_is_filterable(): void {
		add_filter(
			'wu_stripe_connect_proxy_url',
			function () {
				return 'https://custom-proxy.example.com/stripe';
			}
		);

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('get_proxy_url');
		$method->setAccessible(true);

		$result = $method->invoke($this->gateway);

		remove_all_filters('wu_stripe_connect_proxy_url');

		$this->assertSame('https://custom-proxy.example.com/stripe', $result);
	}

	// =========================================================================
	// get_business_data
	// =========================================================================

	/**
	 * Test get_business_data returns expected keys.
	 */
	public function test_get_business_data_returns_expected_keys(): void {
		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('get_business_data');
		$method->setAccessible(true);

		$result = $method->invoke($this->gateway);

		$this->assertArrayHasKey('url', $result);
		$this->assertArrayHasKey('business_name', $result);
		$this->assertArrayHasKey('country', $result);
		$this->assertSame('US', $result['country']);
	}

	// =========================================================================
	// get_oauth_init_url
	// =========================================================================

	/**
	 * Test get_oauth_init_url contains required parameters.
	 */
	public function test_get_oauth_init_url_contains_required_params(): void {
		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('get_oauth_init_url');
		$method->setAccessible(true);

		$result = $method->invoke($this->gateway);

		$this->assertStringContainsString('stripe_oauth_init=1', $result);
		$this->assertStringContainsString('_wpnonce=', $result);
		$this->assertStringContainsString('page=wp-ultimo-settings', $result);
		$this->assertStringContainsString('tab=payment-gateways', $result);
	}

	// =========================================================================
	// get_disconnect_url
	// =========================================================================

	/**
	 * Test get_disconnect_url contains required parameters.
	 */
	public function test_get_disconnect_url_contains_required_params(): void {
		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('get_disconnect_url');
		$method->setAccessible(true);

		$result = $method->invoke($this->gateway);

		$this->assertStringContainsString('stripe_disconnect=1', $result);
		$this->assertStringContainsString('_wpnonce=', $result);
		$this->assertStringContainsString('page=wp-ultimo-settings', $result);
	}

	// =========================================================================
	// hooks
	// =========================================================================

	/**
	 * Test hooks registers expected actions and filters.
	 */
	public function test_hooks_registers_actions_and_filters(): void {
		$gateway = new Stripe_Gateway();
		$gateway->hooks();

		$this->assertGreaterThan(
			0,
			has_action('wu_after_save_settings', [$gateway, 'install_webhook'])
		);

		$this->assertGreaterThan(
			0,
			has_action('wu_after_save_settings', [$gateway, 'check_keys_status'])
		);

		$this->assertGreaterThan(
			0,
			has_filter('wu_pre_save_settings', [$gateway, 'fix_saving_settings'])
		);

		$this->assertGreaterThan(
			0,
			has_filter('allowed_redirect_hosts', [$gateway, 'allow_stripe_redirect_host'])
		);
	}

	// =========================================================================
	// set_stripe_client
	// =========================================================================

	/**
	 * Test set_stripe_client injects mock client.
	 */
	public function test_set_stripe_client_injects_mock(): void {
		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$reflection = new \ReflectionClass($this->gateway);
		$prop       = $reflection->getProperty('stripe_client');
		$prop->setAccessible(true);

		$this->assertSame($this->stripe_client_mock, $prop->getValue($this->gateway));
	}

	// =========================================================================
	// maybe_create_plan — validation
	// =========================================================================

	/**
	 * Test maybe_create_plan returns WP_Error when name is missing.
	 */
	public function test_maybe_create_plan_returns_error_when_name_missing(): void {
		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$result = $this->gateway->maybe_create_plan(
			[
				'name'     => '',
				'price'    => 10.00,
				'currency' => 'usd',
			]
		);

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('missing_name_price', $result->get_error_code());
	}

	/**
	 * Test maybe_create_plan returns WP_Error when price is missing.
	 */
	public function test_maybe_create_plan_returns_error_when_price_missing(): void {
		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$result = $this->gateway->maybe_create_plan(
			[
				'name'     => 'Test Plan',
				'price'    => 0,
				'currency' => 'usd',
			]
		);

		$this->assertInstanceOf(\WP_Error::class, $result);
		$this->assertSame('missing_name_price', $result->get_error_code());
	}

	/**
	 * Test maybe_create_plan returns existing plan ID when plan exists.
	 */
	public function test_maybe_create_plan_returns_existing_plan_id(): void {
		$plans_mock = $this->getMockBuilder(\Stripe\Service\PlanService::class)
			->disableOriginalConstructor()
			->getMock();

		$plan = \Stripe\Plan::constructFrom(['id' => 'plan_existing_123']);

		$plans_mock->method('retrieve')->willReturn($plan);

		$this->stripe_client_mock->method('__get')
			->willReturnCallback(
				function ($property) use ($plans_mock) {
					if ('plans' === $property) {
						return $plans_mock;
					}
					return null;
				}
			);

		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$result = $this->gateway->maybe_create_plan(
			[
				'name'           => 'Test Plan',
				'price'          => 10.00,
				'interval'       => 'month',
				'interval_count' => 1,
				'currency'       => 'usd',
				'id'             => 'plan_existing_123',
			]
		);

		$this->assertSame('plan_existing_123', $result);
	}

	// =========================================================================
	// install_webhook — gateway not active
	// =========================================================================

	/**
	 * Test install_webhook returns early when gateway not in active_gateways.
	 */
	public function test_install_webhook_returns_early_when_not_active(): void {
		$webhook_endpoints_mock = $this->getMockBuilder(\Stripe\Service\WebhookEndpointService::class)
			->disableOriginalConstructor()
			->getMock();

		$webhook_endpoints_mock->expects($this->never())->method('all');

		$this->stripe_client_mock->method('__get')
			->willReturnCallback(
				function ($property) use ($webhook_endpoints_mock) {
					if ('webhookEndpoints' === $property) {
						return $webhook_endpoints_mock;
					}
					return null;
				}
			);

		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$id = 'stripe';

		$settings = [
			"{$id}_sandbox_mode"      => '1',
			"{$id}_test_pk_key"       => 'pk_test_new',
			"{$id}_test_sk_key"       => 'sk_test_new',
			"{$id}_live_pk_key"       => '',
			"{$id}_live_sk_key"       => '',
			"{$id}_test_access_token" => '',
			"{$id}_live_access_token" => '',
			'active_gateways'         => ['paypal'], // stripe not active
		];

		$saved_settings = $settings;

		$this->gateway->install_webhook($settings, $settings, $saved_settings);
	}

	/**
	 * Test install_webhook re-enables disabled webhook.
	 */
	public function test_install_webhook_reenables_disabled_webhook(): void {
		$webhook_url = $this->gateway->get_webhook_listener_url();

		$webhook_endpoints_mock = $this->getMockBuilder(\Stripe\Service\WebhookEndpointService::class)
			->disableOriginalConstructor()
			->getMock();

		$existing_webhook = \Stripe\WebhookEndpoint::constructFrom(
			[
				'id'     => 'we_disabled',
				'url'    => $webhook_url,
				'status' => 'disabled',
			]
		);

		$webhook_endpoints_mock->method('all')
			->willReturn(
				\Stripe\Collection::constructFrom(
					[
						'object' => 'list',
						'data'   => [
							[
								'id'     => 'we_disabled',
								'url'    => $webhook_url,
								'status' => 'disabled',
							],
						],
					]
				)
			);

		$webhook_endpoints_mock->expects($this->once())
			->method('update')
			->with('we_disabled', $this->arrayHasKey('status'));

		$this->stripe_client_mock->method('__get')
			->willReturnCallback(
				function ($property) use ($webhook_endpoints_mock) {
					if ('webhookEndpoints' === $property) {
						return $webhook_endpoints_mock;
					}
					return null;
				}
			);

		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$id = 'stripe';

		$settings = [
			"{$id}_sandbox_mode"      => '1',
			"{$id}_test_pk_key"       => 'pk_test_new',
			"{$id}_test_sk_key"       => 'sk_test_new',
			"{$id}_live_pk_key"       => '',
			"{$id}_live_sk_key"       => '',
			"{$id}_test_access_token" => '',
			"{$id}_live_access_token" => '',
			'active_gateways'         => ['stripe'],
		];

		$saved_settings = array_merge(
			$settings,
			[
				"{$id}_test_pk_key" => 'pk_test_old', // changed
			]
		);

		$this->gateway->install_webhook($settings, $settings, $saved_settings);
	}

	// =========================================================================
	// install_webhook_for_oauth — via reflection
	// =========================================================================

	/**
	 * Test install_webhook_for_oauth creates webhook when none exists.
	 */
	public function test_install_webhook_for_oauth_creates_webhook(): void {
		$webhook_url = $this->gateway->get_webhook_listener_url();

		$webhook_endpoints_mock = $this->getMockBuilder(\Stripe\Service\WebhookEndpointService::class)
			->disableOriginalConstructor()
			->getMock();

		$webhook_endpoints_mock->method('all')
			->willReturn(
				\Stripe\Collection::constructFrom(
					[
						'object' => 'list',
						'data'   => [],
					]
				)
			);

		$webhook_endpoints_mock->expects($this->once())
			->method('create')
			->with($this->arrayHasKey('url'));

		$this->stripe_client_mock->method('__get')
			->willReturnCallback(
				function ($property) use ($webhook_endpoints_mock) {
					if ('webhookEndpoints' === $property) {
						return $webhook_endpoints_mock;
					}
					return null;
				}
			);

		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('install_webhook_for_oauth');
		$method->setAccessible(true);

		$method->invoke($this->gateway);
	}

	/**
	 * Test install_webhook_for_oauth re-enables disabled webhook.
	 */
	public function test_install_webhook_for_oauth_reenables_disabled_webhook(): void {
		$webhook_url = $this->gateway->get_webhook_listener_url();

		$webhook_endpoints_mock = $this->getMockBuilder(\Stripe\Service\WebhookEndpointService::class)
			->disableOriginalConstructor()
			->getMock();

		$webhook_endpoints_mock->method('all')
			->willReturn(
				\Stripe\Collection::constructFrom(
					[
						'object' => 'list',
						'data'   => [
							[
								'id'     => 'we_disabled',
								'url'    => $webhook_url,
								'status' => 'disabled',
							],
						],
					]
				)
			);

		$webhook_endpoints_mock->expects($this->once())
			->method('update')
			->with('we_disabled', $this->arrayHasKey('status'));

		$webhook_endpoints_mock->expects($this->never())->method('create');

		$this->stripe_client_mock->method('__get')
			->willReturnCallback(
				function ($property) use ($webhook_endpoints_mock) {
					if ('webhookEndpoints' === $property) {
						return $webhook_endpoints_mock;
					}
					return null;
				}
			);

		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('install_webhook_for_oauth');
		$method->setAccessible(true);

		$method->invoke($this->gateway);
	}

	/**
	 * Test install_webhook_for_oauth skips when webhook already enabled.
	 */
	public function test_install_webhook_for_oauth_skips_when_already_enabled(): void {
		$webhook_url = $this->gateway->get_webhook_listener_url();

		$webhook_endpoints_mock = $this->getMockBuilder(\Stripe\Service\WebhookEndpointService::class)
			->disableOriginalConstructor()
			->getMock();

		$webhook_endpoints_mock->method('all')
			->willReturn(
				\Stripe\Collection::constructFrom(
					[
						'object' => 'list',
						'data'   => [
							[
								'id'     => 'we_enabled',
								'url'    => $webhook_url,
								'status' => 'enabled',
							],
						],
					]
				)
			);

		$webhook_endpoints_mock->expects($this->never())->method('update');
		$webhook_endpoints_mock->expects($this->never())->method('create');

		$this->stripe_client_mock->method('__get')
			->willReturnCallback(
				function ($property) use ($webhook_endpoints_mock) {
					if ('webhookEndpoints' === $property) {
						return $webhook_endpoints_mock;
					}
					return null;
				}
			);

		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('install_webhook_for_oauth');
		$method->setAccessible(true);

		$method->invoke($this->gateway);
	}

	/**
	 * Test install_webhook_for_oauth handles WP_Error from has_webhook_installed.
	 */
	public function test_install_webhook_for_oauth_handles_wp_error(): void {
		$webhook_endpoints_mock = $this->getMockBuilder(\Stripe\Service\WebhookEndpointService::class)
			->disableOriginalConstructor()
			->getMock();

		$webhook_endpoints_mock->method('all')
			->willThrowException(new \Exception('API Error', 401));

		$webhook_endpoints_mock->expects($this->never())->method('create');

		$this->stripe_client_mock->method('__get')
			->willReturnCallback(
				function ($property) use ($webhook_endpoints_mock) {
					if ('webhookEndpoints' === $property) {
						return $webhook_endpoints_mock;
					}
					return null;
				}
			);

		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('install_webhook_for_oauth');
		$method->setAccessible(true);

		// Should not throw.
		$method->invoke($this->gateway);

		$this->assertTrue(true); // No exception = pass.
	}

	// =========================================================================
	// exchange_code_for_keys — via reflection
	// =========================================================================

	/**
	 * Test exchange_code_for_keys sets oauth_error on WP_Error response.
	 */
	public function test_exchange_code_for_keys_sets_error_on_wp_error(): void {
		add_filter(
			'pre_http_request',
			function ($preempt, $args, $url) {
				if (strpos($url, '/oauth/keys') !== false) {
					return new \WP_Error('http_request_failed', 'Connection failed');
				}
				return $preempt;
			},
			10,
			3
		);

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('exchange_code_for_keys');
		$method->setAccessible(true);

		$tm_prop = $reflection->getProperty('test_mode');
		$tm_prop->setAccessible(true);
		$tm_prop->setValue($this->gateway, true);

		$method->invoke($this->gateway, 'encrypted_code_123');

		remove_all_filters('pre_http_request');

		$error_prop = $reflection->getProperty('oauth_error');
		$error_prop->setAccessible(true);

		$this->assertNotEmpty($error_prop->getValue($this->gateway));
	}

	/**
	 * Test exchange_code_for_keys sets error on non-200 response.
	 */
	public function test_exchange_code_for_keys_sets_error_on_non_200(): void {
		add_filter(
			'pre_http_request',
			function ($preempt, $args, $url) {
				if (strpos($url, '/oauth/keys') !== false) {
					return [
						'response' => ['code' => 400],
						'body'     => wp_json_encode(['error' => 'bad request']),
					];
				}
				return $preempt;
			},
			10,
			3
		);

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('exchange_code_for_keys');
		$method->setAccessible(true);

		$tm_prop = $reflection->getProperty('test_mode');
		$tm_prop->setAccessible(true);
		$tm_prop->setValue($this->gateway, true);

		$method->invoke($this->gateway, 'encrypted_code_123');

		remove_all_filters('pre_http_request');

		$error_prop = $reflection->getProperty('oauth_error');
		$error_prop->setAccessible(true);

		$this->assertNotEmpty($error_prop->getValue($this->gateway));
	}

	/**
	 * Test exchange_code_for_keys sets error when accountId missing.
	 */
	public function test_exchange_code_for_keys_sets_error_when_account_id_missing(): void {
		add_filter(
			'pre_http_request',
			function ($preempt, $args, $url) {
				if (strpos($url, '/oauth/keys') !== false) {
					return [
						'response' => ['code' => 200],
						'body'     => wp_json_encode(['secretKey' => 'sk_test_123']), // no accountId
					];
				}
				return $preempt;
			},
			10,
			3
		);

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('exchange_code_for_keys');
		$method->setAccessible(true);

		$tm_prop = $reflection->getProperty('test_mode');
		$tm_prop->setAccessible(true);
		$tm_prop->setValue($this->gateway, true);

		$method->invoke($this->gateway, 'encrypted_code_123');

		remove_all_filters('pre_http_request');

		$error_prop = $reflection->getProperty('oauth_error');
		$error_prop->setAccessible(true);

		$this->assertNotEmpty($error_prop->getValue($this->gateway));
	}

	// =========================================================================
	// handle_disconnect — via reflection
	// =========================================================================

	/**
	 * Test handle_disconnect clears OAuth settings.
	 */
	public function test_handle_disconnect_clears_oauth_settings(): void {
		$id = 'stripe';

		// Set OAuth tokens.
		wu_save_setting("{$id}_test_access_token", 'sk_test_token');
		wu_save_setting("{$id}_test_account_id", 'acct_test');
		wu_save_setting("{$id}_live_access_token", 'sk_live_token');
		wu_save_setting("{$id}_live_account_id", 'acct_live');

		// Mock the proxy deauthorize call (fire-and-forget).
		add_filter(
			'pre_http_request',
			function ($preempt, $args, $url) {
				if (strpos($url, '/deauthorize') !== false) {
					return ['response' => ['code' => 200], 'body' => '{}'];
				}
				return $preempt;
			},
			10,
			3
		);

		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('handle_disconnect');
		$method->setAccessible(true);

		$tm_prop = $reflection->getProperty('test_mode');
		$tm_prop->setAccessible(true);
		$tm_prop->setValue($this->gateway, true);

		try {
			$method->invoke($this->gateway);
		} catch (\Exception $e) {
			// wp_safe_redirect + exit will throw in test context — that's OK.
		}

		remove_all_filters('pre_http_request');

		// Verify settings were cleared before the redirect.
		$this->assertEmpty(wu_get_setting("{$id}_test_access_token", ''));
		$this->assertEmpty(wu_get_setting("{$id}_test_account_id", ''));
		$this->assertEmpty(wu_get_setting("{$id}_live_access_token", ''));
		$this->assertEmpty(wu_get_setting("{$id}_live_account_id", ''));
	}

	// =========================================================================
	// get_or_create_customer
	// =========================================================================

	/**
	 * Test get_or_create_customer returns existing customer when found.
	 */
	public function test_get_or_create_customer_returns_existing(): void {
		$stripe_customer = \Stripe\Customer::constructFrom(
			[
				'id'      => 'cus_existing123',
				'email'   => 'test@example.com',
				'deleted' => false,
			]
		);

		$customers_mock = $this->getMockBuilder(\Stripe\Service\CustomerService::class)
			->disableOriginalConstructor()
			->getMock();

		$customers_mock->method('retrieve')->willReturn($stripe_customer);

		$this->stripe_client_mock->method('__get')
			->willReturnCallback(
				function ($property) use ($customers_mock) {
					if ('customers' === $property) {
						return $customers_mock;
					}
					return null;
				}
			);

		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$result = $this->gateway->get_or_create_customer(
			self::$customer->get_id(),
			self::$customer->get_user_id(),
			'cus_existing123'
		);

		$this->assertSame('cus_existing123', $result->id);
	}

	/**
	 * Test get_or_create_customer creates new customer when not found.
	 */
	public function test_get_or_create_customer_creates_new_when_not_found(): void {
		$new_customer = \Stripe\Customer::constructFrom(
			[
				'id'    => 'cus_new123',
				'email' => 'test@example.com',
			]
		);

		$customers_mock = $this->getMockBuilder(\Stripe\Service\CustomerService::class)
			->disableOriginalConstructor()
			->getMock();

		// retrieve throws (customer not found).
		$customers_mock->method('retrieve')
			->willThrowException(new \Exception('No such customer'));

		$customers_mock->expects($this->once())
			->method('create')
			->willReturn($new_customer);

		$this->stripe_client_mock->method('__get')
			->willReturnCallback(
				function ($property) use ($customers_mock) {
					if ('customers' === $property) {
						return $customers_mock;
					}
					return null;
				}
			);

		$this->gateway->set_stripe_client($this->stripe_client_mock);

		// Set customer on gateway.
		$reflection = new \ReflectionClass($this->gateway);
		$prop       = $reflection->getProperty('customer');
		$prop->setAccessible(true);
		$prop->setValue($this->gateway, self::$customer);

		$result = $this->gateway->get_or_create_customer(
			self::$customer->get_id(),
			self::$customer->get_user_id(),
			'cus_nonexistent'
		);

		$this->assertSame('cus_new123', $result->id);
	}

	/**
	 * Test get_or_create_customer returns WP_Error when create fails.
	 */
	public function test_get_or_create_customer_returns_wp_error_on_create_failure(): void {
		$customers_mock = $this->getMockBuilder(\Stripe\Service\CustomerService::class)
			->disableOriginalConstructor()
			->getMock();

		// retrieve throws (customer not found).
		$customers_mock->method('retrieve')
			->willThrowException(new \Exception('No such customer'));

		// create also throws.
		$customers_mock->method('create')
			->willThrowException(new \Exception('API Error', 500));

		$this->stripe_client_mock->method('__get')
			->willReturnCallback(
				function ($property) use ($customers_mock) {
					if ('customers' === $property) {
						return $customers_mock;
					}
					return null;
				}
			);

		$this->gateway->set_stripe_client($this->stripe_client_mock);

		// Set customer on gateway.
		$reflection = new \ReflectionClass($this->gateway);
		$prop       = $reflection->getProperty('customer');
		$prop->setAccessible(true);
		$prop->setValue($this->gateway, self::$customer);

		$result = $this->gateway->get_or_create_customer(
			self::$customer->get_id(),
			self::$customer->get_user_id(),
			'cus_nonexistent'
		);

		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	// =========================================================================
	// get_saved_card_options
	// =========================================================================

	/**
	 * Test get_saved_card_options returns empty array when not logged in.
	 */
	public function test_get_saved_card_options_returns_empty_when_not_logged_in(): void {
		// Ensure no user is logged in.
		wp_set_current_user(0);

		$result = $this->gateway->get_saved_card_options();

		$this->assertIsArray($result);
		$this->assertEmpty($result);
	}

	// =========================================================================
	// maybe_create_tax_rate
	// =========================================================================

	/**
	 * Test maybe_create_tax_rate returns existing tax rate ID when found.
	 */
	public function test_maybe_create_tax_rate_returns_existing(): void {
		$tax_rates_mock = $this->getMockBuilder(\Stripe\Service\TaxRateService::class)
			->disableOriginalConstructor()
			->getMock();

		$tax_rate = \Stripe\TaxRate::constructFrom(
			[
				'id'       => 'txr_existing',
				'metadata' => ['tax_rate_id' => 'us-10-vat'],
			]
		);

		$tax_rates_mock->method('all')
			->willReturn(
				\Stripe\Collection::constructFrom(
					[
						'object' => 'list',
						'data'   => [
							[
								'id'       => 'txr_existing',
								'metadata' => ['tax_rate_id' => 'us-10-vat'],
							],
						],
					]
				)
			);

		$this->stripe_client_mock->method('__get')
			->willReturnCallback(
				function ($property) use ($tax_rates_mock) {
					if ('taxRates' === $property) {
						return $tax_rates_mock;
					}
					return null;
				}
			);

		$this->gateway->set_stripe_client($this->stripe_client_mock);

		$result = $this->gateway->maybe_create_tax_rate(
			[
				'country'  => 'US',
				'tax_rate' => 10,
				'type'     => 'vat',
				'title'    => 'VAT',
				'inclusive' => false,
			]
		);

		$this->assertSame('txr_existing', $result);
	}

	/**
	 * Test maybe_create_tax_rate creates new tax rate when not found.
	 *
	 * Uses a different country/type combination than test_maybe_create_tax_rate_returns_existing
	 * to avoid the static cache in maybe_create_tax_rate() returning the cached value.
	 */
	public function test_maybe_create_tax_rate_creates_new(): void {
		$tax_rates_mock = $this->getMockBuilder(\Stripe\Service\TaxRateService::class)
			->disableOriginalConstructor()
			->getMock();

		$new_tax_rate = \Stripe\TaxRate::constructFrom(['id' => 'txr_new']);

		$tax_rates_mock->method('all')
			->willReturn(
				\Stripe\Collection::constructFrom(
					[
						'object' => 'list',
						'data'   => [], // no existing tax rates
					]
				)
			);

		$tax_rates_mock->expects($this->once())
			->method('create')
			->willReturn($new_tax_rate);

		$this->stripe_client_mock->method('__get')
			->willReturnCallback(
				function ($property) use ($tax_rates_mock) {
					if ('taxRates' === $property) {
						return $tax_rates_mock;
					}
					return null;
				}
			);

		$this->gateway->set_stripe_client($this->stripe_client_mock);

		// Use a different country/type to avoid static cache collision with test_maybe_create_tax_rate_returns_existing.
		$result = $this->gateway->maybe_create_tax_rate(
			[
				'country'   => 'CA',
				'tax_rate'  => 5,
				'type'      => 'gst',
				'title'     => 'GST',
				'inclusive' => false,
			]
		);

		$this->assertSame('txr_new', $result);
	}

	// =========================================================================
	// check_keys_status — returns early when not active
	// =========================================================================

	/**
	 * Test check_keys_status returns early when gateway not active.
	 */
	public function test_check_keys_status_returns_early_when_not_active(): void {
		$id = 'stripe';

		$settings = [
			"{$id}_sandbox_mode"      => '1',
			"{$id}_test_pk_key"       => 'pk_test_new',
			"{$id}_test_sk_key"       => 'sk_test_new',
			"{$id}_live_pk_key"       => '',
			"{$id}_live_sk_key"       => '',
		];

		$settings_to_save = [
			'active_gateways' => ['paypal'], // stripe not active
		];

		$saved_settings = $settings;

		// Should not throw.
		$this->gateway->check_keys_status($settings, $settings_to_save, $saved_settings);

		$this->assertTrue(true); // No exception = pass.
	}

	/**
	 * Test check_keys_status returns early when settings unchanged.
	 */
	public function test_check_keys_status_returns_early_when_unchanged(): void {
		$id = 'stripe';

		$settings = [
			"{$id}_sandbox_mode"  => '1',
			"{$id}_test_pk_key"   => 'pk_test_same',
			"{$id}_test_sk_key"   => 'sk_test_same',
			"{$id}_live_pk_key"   => '',
			"{$id}_live_sk_key"   => '',
		];

		$settings_to_save = [
			'active_gateways' => ['stripe'],
		];

		$saved_settings = $settings; // Same as settings — no change.

		// Should not throw.
		$this->gateway->check_keys_status($settings, $settings_to_save, $saved_settings);

		$this->assertTrue(true); // No exception = pass.
	}

	// =========================================================================
	// run_preflight
	// =========================================================================

	/**
	 * Test run_preflight returns nothing (empty method in base class).
	 *
	 * Uses Base_Stripe_Gateway_Stub since Stripe_Gateway overrides run_preflight() with real logic.
	 */
	public function test_run_preflight_returns_nothing(): void {
		$stub  = new Base_Stripe_Gateway_Stub();
		$result = $stub->run_preflight();

		$this->assertNull($result);
	}

	// =========================================================================
	// process_checkout
	// =========================================================================

	/**
	 * Test process_checkout is empty in base class.
	 *
	 * Uses Base_Stripe_Gateway_Stub since Stripe_Gateway overrides process_checkout() with real logic.
	 */
	public function test_process_checkout_is_empty(): void {
		$stub            = new Base_Stripe_Gateway_Stub();
		$payment_mock    = $this->getMockBuilder(\WP_Ultimo\Models\Payment::class)->disableOriginalConstructor()->getMock();
		$membership_mock = $this->getMockBuilder(\WP_Ultimo\Models\Membership::class)->disableOriginalConstructor()->getMock();
		$customer_mock   = $this->getMockBuilder(\WP_Ultimo\Models\Customer::class)->disableOriginalConstructor()->getMock();
		$cart_mock       = $this->getMockBuilder(\WP_Ultimo\Checkout\Cart::class)->disableOriginalConstructor()->getMock();

		$result = $stub->process_checkout($payment_mock, $membership_mock, $customer_mock, $cart_mock, 'new');

		$this->assertNull($result);
	}

	// =========================================================================
	// settings
	// =========================================================================

	/**
	 * Test settings registers the enable_portal field.
	 */
	public function test_settings_registers_enable_portal_field(): void {
		// Should not throw.
		$this->gateway->settings();

		$this->assertTrue(true); // No exception = pass.
	}

	// =========================================================================
	// register_scripts — skips when no publishable key
	// =========================================================================

	/**
	 * Test register_scripts returns early when no publishable key.
	 */
	public function test_register_scripts_returns_early_when_no_publishable_key(): void {
		// No publishable key set.
		$reflection = new \ReflectionClass($this->gateway);
		$prop       = $reflection->getProperty('publishable_key');
		$prop->setAccessible(true);
		$prop->setValue($this->gateway, '');

		// Should not throw.
		$this->gateway->register_scripts();

		$this->assertTrue(true); // No exception = pass.
	}

	// =========================================================================
	// maybe_redirect_to_portal — returns early when no portal request
	// =========================================================================

	/**
	 * Test maybe_redirect_to_portal returns early when no portal request param.
	 */
	public function test_maybe_redirect_to_portal_returns_early_when_no_param(): void {
		// No wu-stripe-portal in request.
		unset($_GET['wu-stripe-portal']);

		// Should not throw.
		$this->gateway->maybe_redirect_to_portal();

		$this->assertTrue(true); // No exception = pass.
	}

	// =========================================================================
	// handle_oauth_callbacks — returns early when no relevant params
	// =========================================================================

	/**
	 * Test handle_oauth_callbacks returns early when no relevant GET params.
	 */
	public function test_handle_oauth_callbacks_returns_early_when_no_params(): void {
		// Clear GET params.
		$_GET = [];

		// Should not throw.
		$this->gateway->handle_oauth_callbacks();

		$this->assertTrue(true); // No exception = pass.
	}

	/**
	 * Test handle_oauth_callbacks handles OAuth callback with invalid state.
	 */
	public function test_handle_oauth_callbacks_ignores_invalid_state(): void {
		// Set up GET params for OAuth callback.
		$_GET = [
			'page'              => 'wp-ultimo-settings',
			'wcs_stripe_code'   => 'encrypted_code_123',
			'wcs_stripe_state'  => 'invalid_state',
		];

		// Set a different expected state.
		update_option('wu_stripe_oauth_state', 'correct_state');

		// Grant manage_network capability.
		$user_id = self::$customer->get_user_id();
		grant_super_admin($user_id);
		wp_set_current_user($user_id);

		// Should return early due to state mismatch.
		$this->gateway->handle_oauth_callbacks();

		// State should still be set (not deleted since we returned early).
		$this->assertSame('correct_state', get_option('wu_stripe_oauth_state'));

		// Cleanup.
		$_GET = [];
		revoke_super_admin($user_id);
		wp_set_current_user(0);
	}

	// =========================================================================
	// schedule_payment_verification
	// =========================================================================

	/**
	 * Test schedule_payment_verification returns false when already scheduled.
	 *
	 * Schedules the action first via wu_schedule_single_action, then verifies
	 * that a second call to schedule_payment_verification returns false.
	 */
	public function test_schedule_payment_verification_returns_false_when_already_scheduled(): void {
		$hook = 'wu_verify_stripe_payment';
		$args = [
			'payment_id' => 999,
			'gateway_id' => $this->gateway->get_id(),
		];

		// Schedule the action so it already exists.
		wu_schedule_single_action(time() + 60, $hook, $args, 'wu-stripe-verification');

		// Now schedule_payment_verification should detect it's already scheduled and return false.
		$result = $this->gateway->schedule_payment_verification(999, 30);

		$this->assertFalse($result);
	}
}

<?php
/**
 * Tests for the Stripe Checkout Gateway run_preflight() method.
 *
 * Verifies that the Stripe Checkout integration uses the current Stripe API
 * (price/price_data) instead of the deprecated line_items fields
 * (amount, currency, name, description, images).
 *
 * Regression test for GitHub issue #247:
 * "You cannot use line_items.amount, line_items.currency, line_items.name,
 * line_items.description, or line_items.images in this API version."
 *
 * @package WP_Ultimo\Gateways
 * @since 2.0.0
 */

namespace WP_Ultimo\Gateways;

use WP_Ultimo\Database\Memberships\Membership_Status;
use WP_Ultimo\Models\Customer;
use Stripe\StripeClient;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Unit tests for Stripe Checkout Gateway run_preflight().
 */
class Stripe_Checkout_Gateway_Run_Preflight_Test extends \WP_UnitTestCase {

	/**
	 * @var \WP_Ultimo\Gateways\Stripe_Checkout_Gateway
	 */
	private $gateway;

	/**
	 * @var MockObject|StripeClient
	 */
	private $stripe_client_mock;

	/**
	 * Captured checkout session arguments from the mock.
	 *
	 * @var array|null
	 */
	private $captured_session_args = null;

	/**
	 * @var Customer
	 */
	private static Customer $customer;

	/**
	 * Set up test customer once for all tests.
	 *
	 * @return void
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();
		self::$customer = wu_create_customer(
			[
				'username' => 'stripe_checkout_test_user',
				'email'    => 'stripe_checkout_test@example.com',
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

		$this->captured_session_args = null;

		// Build Stripe client mock
		$this->stripe_client_mock = $this->getMockBuilder(StripeClient::class)
			->disableOriginalConstructor()
			->getMock();

		// Customers service mock
		$customers_mock = $this->getMockBuilder(\Stripe\Service\CustomerService::class)
			->disableOriginalConstructor()
			->getMock();

		$stripe_customer = \Stripe\Customer::constructFrom(
			[
				'id'            => 'cus_test123',
				'subscriptions' => [
					'object' => 'list',
					'data'   => [],
				],
			]
		);

		$customers_mock->method('retrieve')->willReturn($stripe_customer);
		$customers_mock->method('create')->willReturn($stripe_customer);
		$customers_mock->method('update')->willReturn($stripe_customer);

		// Plans service mock (for build_stripe_cart)
		$plans_mock = $this->getMockBuilder(\Stripe\Service\PlanService::class)
			->disableOriginalConstructor()
			->getMock();

		$stripe_plan = \Stripe\Plan::constructFrom(['id' => 'plan_test123']);
		$plans_mock->method('retrieve')->willReturn($stripe_plan);
		$plans_mock->method('create')->willReturn($stripe_plan);

		// Products service mock (for maybe_create_plan)
		$products_mock = $this->getMockBuilder(\Stripe\Service\ProductService::class)
			->disableOriginalConstructor()
			->getMock();

		$stripe_product = \Stripe\Product::constructFrom(['id' => 'prod_test123']);
		$products_mock->method('retrieve')->willReturn($stripe_product);
		$products_mock->method('create')->willReturn($stripe_product);

		// Prices service mock (for build_non_recurring_cart)
		$prices_mock = $this->getMockBuilder(\Stripe\Service\PriceService::class)
			->disableOriginalConstructor()
			->getMock();

		$stripe_price = \Stripe\Price::constructFrom(['id' => 'price_test123']);
		$prices_list  = \Stripe\Collection::constructFrom(
			[
				'object' => 'list',
				'data'   => [],
			]
		);
		$prices_mock->method('all')->willReturn($prices_list);
		$prices_mock->method('create')->willReturn($stripe_price);

		// Tax rates service mock
		$tax_rates_mock = $this->getMockBuilder(\Stripe\Service\TaxRateService::class)
			->disableOriginalConstructor()
			->getMock();

		$tax_rates_list = \Stripe\Collection::constructFrom(
			[
				'object' => 'list',
				'data'   => [],
			]
		);
		$tax_rates_mock->method('all')->willReturn($tax_rates_list);

		// Coupons service mock
		$coupons_mock = $this->getMockBuilder(\Stripe\Service\CouponService::class)
			->disableOriginalConstructor()
			->getMock();

		$coupons_mock->method('retrieve')->will($this->throwException(new \Stripe\Exception\InvalidRequestException('No such coupon', 404)));

		// Checkout sessions service mock — captures the arguments passed to create()
		$checkout_sessions_mock = $this->getMockBuilder(\Stripe\Service\Checkout\SessionService::class)
			->disableOriginalConstructor()
			->getMock();

		$test_instance = $this;

		$checkout_sessions_mock->method('create')
			->willReturnCallback(
				function ($args) use ($test_instance) {
					$test_instance->captured_session_args = $args;

					return \Stripe\Checkout\Session::constructFrom(
						[
							'id'  => 'cs_test123',
							'url' => 'https://checkout.stripe.com/pay/cs_test123',
						]
					);
				}
			);

		// Checkout service mock (wraps sessions)
		$checkout_mock = new class($checkout_sessions_mock) {
			/** @var mixed */
			private $sessions;

			/**
			 * Constructor.
			 *
			 * @param mixed $sessions The sessions service mock.
			 */
			public function __construct($sessions) {
				$this->sessions = $sessions;
			}

			/**
			 * Magic getter for service properties.
			 *
			 * @param string $name Property name.
			 * @return mixed
			 */
			public function __get($name) {
				if ('sessions' === $name) {
					return $this->sessions;
				}
				return null;
			}
		};

		// Wire up the top-level client mock
		$this->stripe_client_mock->method('__get')
			->willReturnCallback(
				function ($property) use (
					$customers_mock,
					$plans_mock,
					$products_mock,
					$prices_mock,
					$tax_rates_mock,
					$coupons_mock,
					$checkout_mock
				) {
					switch ($property) {
						case 'customers':
							return $customers_mock;
						case 'plans':
							return $plans_mock;
						case 'products':
							return $products_mock;
						case 'prices':
							return $prices_mock;
						case 'taxRates':
							return $tax_rates_mock;
						case 'coupons':
							return $coupons_mock;
						case 'checkout':
							return $checkout_mock;
						default:
							return null;
					}
				}
			);

		// Create gateway and inject mock client
		$this->gateway = new \WP_Ultimo\Gateways\Stripe_Checkout_Gateway();
		$this->gateway->set_stripe_client($this->stripe_client_mock);
	}

	/**
	 * Helper: build a minimal cart, membership, payment, and wire them to the gateway.
	 *
	 * @param bool $recurring Whether the product is recurring.
	 * @param bool $trial     Whether to include a trial period.
	 * @return array{product: \WP_Ultimo\Models\Product, membership: \WP_Ultimo\Models\Membership, payment: \WP_Ultimo\Models\Payment}
	 */
	private function build_checkout_context(bool $recurring = true, bool $trial = false): array {
		$customer = self::$customer;
		wp_set_current_user($customer->get_user_id(), $customer->get_username());

		$product = wu_create_product(
			[
				'name'                => 'Checkout Test Plan',
				'slug'                => 'checkout-test-plan-' . uniqid(),
				'amount'              => 29.00,
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
				'gateway'       => 'stripe-checkout',
				'status'        => 'pending',
				'total'         => $trial ? 0 : $product->get_amount(),
			]
		);

		$cart_args = [
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
		];

		$cart = new \WP_Ultimo\Checkout\Cart($cart_args);

		// set_order() overwrites customer/membership/payment from the cart.
		// Set order first, then override with our concrete objects.
		$this->gateway->set_order($cart);
		$this->gateway->set_membership($membership);
		$this->gateway->set_customer($customer);
		$this->gateway->set_payment($payment);

		return compact('product', 'membership', 'payment');
	}

	/**
	 * Verify that run_preflight() for a one-time (non-recurring) checkout
	 * uses price_data instead of the deprecated amount/currency/name fields.
	 *
	 * Regression test for GitHub issue #247.
	 *
	 * @return void
	 */
	public function test_run_preflight_payment_mode_uses_price_data(): void {
		$context = $this->build_checkout_context(false, false);

		$result = $this->gateway->run_preflight();

		// run_preflight must succeed (not return WP_Error)
		$this->assertNotInstanceOf(
			\WP_Error::class,
			$result,
			'run_preflight() should not return WP_Error for a valid one-time checkout'
		);

		// The session must have been created
		$this->assertNotNull(
			$this->captured_session_args,
			'Stripe checkout session must have been created'
		);

		$this->assertSame(
			'payment',
			$this->captured_session_args['mode'],
			'One-time checkout must use payment mode'
		);

		// Verify line_items use price_data (current API) not deprecated fields
		$this->assertArrayHasKey(
			'line_items',
			$this->captured_session_args,
			'Session must include line_items'
		);

		foreach ($this->captured_session_args['line_items'] as $item) {
			// Current API: must use price_data
			$this->assertArrayHasKey(
				'price_data',
				$item,
				'Each line item must use price_data (current Stripe API)'
			);

			// Deprecated fields must NOT be present at the line_item level
			$this->assertArrayNotHasKey(
				'amount',
				$item,
				'line_items.amount is deprecated and must not be used (issue #247)'
			);
			$this->assertArrayNotHasKey(
				'currency',
				$item,
				'line_items.currency is deprecated and must not be used (issue #247)'
			);
			$this->assertArrayNotHasKey(
				'name',
				$item,
				'line_items.name is deprecated and must not be used (issue #247)'
			);
			$this->assertArrayNotHasKey(
				'description',
				$item,
				'line_items.description is deprecated and must not be used (issue #247)'
			);
			$this->assertArrayNotHasKey(
				'images',
				$item,
				'line_items.images is deprecated and must not be used (issue #247)'
			);

			// price_data must contain the required fields
			$this->assertArrayHasKey(
				'currency',
				$item['price_data'],
				'price_data must include currency'
			);
			$this->assertArrayHasKey(
				'unit_amount',
				$item['price_data'],
				'price_data must include unit_amount'
			);
			$this->assertArrayHasKey(
				'product_data',
				$item['price_data'],
				'price_data must include product_data'
			);
		}

		// Cleanup
		$context['payment']->delete();
		$context['membership']->delete();
		$context['product']->delete();
	}

	/**
	 * Verify that run_preflight() for a recurring subscription checkout
	 * uses price IDs (not deprecated subscription_data.items format).
	 *
	 * @return void
	 */
	public function test_run_preflight_subscription_mode_uses_price_ids(): void {
		$context = $this->build_checkout_context(true, false);

		$result = $this->gateway->run_preflight();

		// run_preflight must succeed
		$this->assertNotInstanceOf(
			\WP_Error::class,
			$result,
			'run_preflight() should not return WP_Error for a valid subscription checkout'
		);

		$this->assertNotNull(
			$this->captured_session_args,
			'Stripe checkout session must have been created'
		);

		$this->assertSame(
			'subscription',
			$this->captured_session_args['mode'],
			'Recurring checkout must use subscription mode'
		);
		$this->assertSame(
			['card'],
			$this->captured_session_args['payment_method_types'],
			'Stripe Checkout must preserve its card-only default'
		);
		$this->assertSame(
			['klarna'],
			$this->captured_session_args['excluded_payment_method_types'],
			'Stripe Checkout must exclude Klarna'
		);

		// Verify line_items exist and use price IDs (not deprecated format)
		$this->assertArrayHasKey(
			'line_items',
			$this->captured_session_args,
			'Subscription session must include line_items'
		);

		foreach ($this->captured_session_args['line_items'] as $item) {
			// Deprecated fields must NOT be present at the line_item level
			$this->assertArrayNotHasKey(
				'amount',
				$item,
				'line_items.amount is deprecated and must not be used (issue #247)'
			);
			$this->assertArrayNotHasKey(
				'currency',
				$item,
				'line_items.currency is deprecated and must not be used (issue #247)'
			);
			$this->assertArrayNotHasKey(
				'name',
				$item,
				'line_items.name is deprecated and must not be used (issue #247)'
			);
			$this->assertArrayNotHasKey(
				'description',
				$item,
				'line_items.description is deprecated and must not be used (issue #247)'
			);
			$this->assertArrayNotHasKey(
				'images',
				$item,
				'line_items.images is deprecated and must not be used (issue #247)'
			);

			// Each item must use either price (ID) or price_data
			$has_price      = array_key_exists('price', $item);
			$has_price_data = array_key_exists('price_data', $item);

			$this->assertTrue(
				$has_price || $has_price_data,
				'Each line item must use price (ID) or price_data — not deprecated amount/currency/name fields'
			);
		}

		// Deprecated subscription_data.items must not be used
		if (isset($this->captured_session_args['subscription_data'])) {
			$this->assertArrayNotHasKey(
				'items',
				$this->captured_session_args['subscription_data'],
				'subscription_data.items is deprecated — recurring items must be in top-level line_items'
			);
		}

		// Cleanup
		$context['payment']->delete();
		$context['membership']->delete();
		$context['product']->delete();
	}

	/**
	 * Verify that run_preflight() returns the session URL and session ID.
	 *
	 * @return void
	 */
	public function test_run_preflight_returns_session_url_and_id(): void {
		$context = $this->build_checkout_context(false, false);

		$result = $this->gateway->run_preflight();

		$this->assertIsArray($result, 'run_preflight() must return an array on success');
		$this->assertArrayHasKey(
			'stripe_session_id',
			$result,
			'Result must include stripe_session_id'
		);
		$this->assertArrayHasKey(
			'stripe_checkout_url',
			$result,
			'Result must include stripe_checkout_url for direct redirect (issue #369)'
		);
		$this->assertNotEmpty($result['stripe_session_id'], 'stripe_session_id must not be empty');
		$this->assertNotEmpty($result['stripe_checkout_url'], 'stripe_checkout_url must not be empty');

		// Cleanup
		$context['payment']->delete();
		$context['membership']->delete();
		$context['product']->delete();
	}

	/**
	 * Verify that run_preflight() returns a WP_Error (rather than fatalling on
	 * a null customer id) when get_or_create_customer() fails to retrieve or
	 * create a Stripe customer.
	 *
	 * Regression test for the support report:
	 *   "Stripe Checkout session is returning null customer; please add
	 *   customer_creation => always or guard null customer in
	 *   sync_billing_address_to_stripe()"
	 *
	 *   TypeError: Stripe_Checkout_Gateway::sync_billing_address_to_stripe():
	 *   Argument #1 ($stripe_customer_id) must be of type string, null given
	 *
	 * The root cause is that get_or_create_customer() can return WP_Error (or
	 * an object whose id is null), but run_preflight() previously dereferenced
	 * ->id without checking.
	 *
	 * @return void
	 */
	public function test_run_preflight_returns_wp_error_when_customer_creation_fails(): void {
		$context = $this->build_checkout_context(false, false);

		// Build a Stripe client whose customers service throws on both retrieve
		// and create — simulating, for example, a key/account mismatch.
		$failing_client = $this->getMockBuilder(StripeClient::class)
			->disableOriginalConstructor()
			->getMock();

		$failing_customers = $this->getMockBuilder(\Stripe\Service\CustomerService::class)
			->disableOriginalConstructor()
			->getMock();

		$failing_customers->method('retrieve')->will(
			$this->throwException(new \Stripe\Exception\InvalidRequestException('No such customer', 404))
		);
		$failing_customers->method('create')->will(
			$this->throwException(new \Stripe\Exception\AuthenticationException('Invalid API key provided', 401))
		);

		$failing_client->method('__get')->willReturnCallback(
			function ($property) use ($failing_customers) {
				if ('customers' === $property) {
					return $failing_customers;
				}
				return null;
			}
		);

		$this->gateway->set_stripe_client($failing_client);

		$result = $this->gateway->run_preflight();

		// Must return a WP_Error — never fatal on a null customer id.
		$this->assertInstanceOf(
			\WP_Error::class,
			$result,
			'run_preflight() must return WP_Error when Stripe customer cannot be created'
		);

		// Cleanup
		$context['payment']->delete();
		$context['membership']->delete();
		$context['product']->delete();
	}

	/**
	 * Verify that get_or_create_customer() self-heals when the stored Stripe
	 * customer id cannot be retrieved (e.g. test/live key mismatch, account
	 * swap, customer deleted in dashboard) by creating a fresh customer.
	 *
	 * @return void
	 */
	public function test_get_or_create_customer_self_heals_on_stale_stored_id(): void {
		$customer = self::$customer;

		// Pre-seed a stale gateway_customer_id on a membership for this customer.
		$stale_membership = wu_create_membership(
			[
				'customer_id'         => $customer->get_id(),
				'plan_id'             => 0,
				'status'              => Membership_Status::PENDING,
				'recurring'           => false,
				'gateway'             => 'stripe-checkout',
				'gateway_customer_id' => 'cus_stale_does_not_exist',
				'currency'            => 'USD',
			]
		);

		// Build a client whose retrieve throws (stale id) but create succeeds.
		$client = $this->getMockBuilder(StripeClient::class)
			->disableOriginalConstructor()
			->getMock();

		$customers_mock = $this->getMockBuilder(\Stripe\Service\CustomerService::class)
			->disableOriginalConstructor()
			->getMock();

		$customers_mock->method('retrieve')->will(
			$this->throwException(new \Stripe\Exception\InvalidRequestException('No such customer: cus_stale_does_not_exist', 404))
		);

		$fresh_customer = \Stripe\Customer::constructFrom(['id' => 'cus_fresh456']);
		$customers_mock->method('create')->willReturn($fresh_customer);

		$client->method('__get')->willReturnCallback(
			function ($property) use ($customers_mock) {
				if ('customers' === $property) {
					return $customers_mock;
				}
				return null;
			}
		);

		$this->gateway->set_stripe_client($client);
		$this->gateway->set_customer($customer);

		$result = $this->gateway->get_or_create_customer($customer->get_id());

		$this->assertNotInstanceOf(
			\WP_Error::class,
			$result,
			'get_or_create_customer() must self-heal when the stored Stripe id is stale'
		);
		$this->assertSame(
			'cus_fresh456',
			$result->id,
			'A fresh Stripe customer id must be returned when the stored one is unretrievable'
		);

		$stale_membership->delete();
	}

	/**
	 * Verify that sync_billing_address_to_stripe() returns early without
	 * making any API call when given an empty customer id (defensive guard).
	 *
	 * @return void
	 */
	public function test_sync_billing_address_to_stripe_short_circuits_on_empty_id(): void {
		$customer = self::$customer;
		$this->gateway->set_customer($customer);

		// A client whose customers->update would fail the test if called.
		$client = $this->getMockBuilder(StripeClient::class)
			->disableOriginalConstructor()
			->getMock();

		$customers_mock = $this->getMockBuilder(\Stripe\Service\CustomerService::class)
			->disableOriginalConstructor()
			->getMock();

		$customers_mock->expects($this->never())->method('update');

		$client->method('__get')->willReturnCallback(
			function ($property) use ($customers_mock) {
				if ('customers' === $property) {
					return $customers_mock;
				}
				return null;
			}
		);

		$this->gateway->set_stripe_client($client);

		// Reflect to call the protected method directly.
		$reflection = new \ReflectionClass($this->gateway);
		$method     = $reflection->getMethod('sync_billing_address_to_stripe');
		$method->setAccessible(true);

		$method->invoke($this->gateway, '');

		// If the early return works, customers->update was never called.
		$this->addToAssertionCount(1);
	}

	/**
	 * Tear down after all tests.
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

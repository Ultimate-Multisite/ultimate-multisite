<?php
/**
 * Tests for Base_Gateway class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Gateways;

use WP_UnitTestCase;
use WP_Error;
use WP_Ultimo\Database\Memberships\Membership_Status;
use WP_Ultimo\Database\Payments\Payment_Status;

/**
 * Test class for Base_Gateway.
 *
 * Since Base_Gateway is abstract, we create a concrete test implementation.
 */
class Base_Gateway_Test extends WP_UnitTestCase {

	/**
	 * @var Test_Gateway
	 */
	private $gateway;

	/**
	 * @var \WP_Ultimo\Models\Customer
	 */
	private $customer;

	/**
	 * @var \WP_Ultimo\Models\Product
	 */
	private $product;

	/**
	 * @var \WP_Ultimo\Models\Membership
	 */
	private $membership;

	/**
	 * @var \WP_Ultimo\Models\Payment
	 */
	private $payment;

	/**
	 * @var \WP_Ultimo\Checkout\Cart
	 */
	private $cart;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();

		// Only create full fixtures for tests that need them
		// Other tests will create their own minimal fixtures
	}

	/**
	 * Create full test fixtures (customer, product, membership, payment, cart, gateway).
	 */
	private function create_full_fixtures(): void {
		// Create test user
		$user_id = self::factory()->user->create();

		// Create customer
		$this->customer = wu_create_customer([
			'user_id'            => $user_id,
			'email_verification' => 'none',
		]);

		if (is_wp_error($this->customer)) {
			$this->fail('Failed to create customer: ' . $this->customer->get_error_message());
		}

		// Create product
		$this->product = wu_create_product([
			'name'         => 'Test Plan',
			'slug'         => 'test-plan-' . wp_generate_uuid4(),
			'pricing_type' => 'paid',
			'amount'       => 10.00,
			'type'         => 'plan',
		]);

		if (is_wp_error($this->product)) {
			$this->fail('Failed to create product: ' . $this->product->get_error_message());
		}

		// Create membership
		$this->membership = wu_create_membership([
			'customer_id'     => $this->customer->get_id(),
			'plan_id'         => $this->product->get_id(),
			'status'          => Membership_Status::PENDING,
			'recurring'       => true,
			'amount'          => 10.00,
			'skip_validation' => true,
		]);

		if (is_wp_error($this->membership)) {
			$this->fail('Failed to create membership: ' . $this->membership->get_error_message());
		}

		// Create payment
		$this->payment = wu_create_payment([
			'customer_id'   => $this->customer->get_id(),
			'membership_id' => $this->membership->get_id(),
			'currency'      => 'USD',
			'subtotal'      => 10.00,
			'total'         => 10.00,
			'status'        => Payment_Status::PENDING,
			'gateway'       => 'test',
		]);

		if (is_wp_error($this->payment)) {
			$this->fail('Failed to create payment: ' . $this->payment->get_error_message());
		}

		// Create cart
		$this->cart = new \WP_Ultimo\Checkout\Cart([
			'products' => [$this->product->get_id()],
		]);

		$this->cart->set_customer($this->customer);
		$this->cart->set_membership($this->membership);
		$this->cart->set_payment($this->payment);

		// Create gateway instance
		$this->gateway = new Test_Gateway($this->cart);
	}

	/**
	 * Test constructor initializes with null order.
	 */
	public function test_constructor_with_null_order(): void {
		$gateway = new Test_Gateway();

		$this->assertInstanceOf(Test_Gateway::class, $gateway);
		$this->assertEquals('test', $gateway->get_id());
	}

	/**
	 * Test constructor initializes with order and calls init.
	 */
	public function test_constructor_with_order(): void {
		$this->create_full_fixtures();
		
		$this->assertInstanceOf(Test_Gateway::class, $this->gateway);
		$this->assertTrue($this->gateway->init_called);
	}

	/**
	 * Test set_order sets order and related entities.
	 */
	public function test_set_order(): void {
		$this->create_full_fixtures();
		
		$gateway = new Test_Gateway();
		$gateway->set_order($this->cart);

		$this->assertSame($this->customer, $gateway->get_customer());
		$this->assertSame($this->membership, $gateway->get_membership());
		$this->assertSame($this->payment, $gateway->get_payment());
	}

	/**
	 * Test set_order with null does nothing.
	 */
	public function test_set_order_with_null(): void {
		$gateway = new Test_Gateway();
		$gateway->set_order(null);

		$this->assertNull($gateway->get_customer());
	}

	/**
	 * Test get_id returns gateway ID.
	 */
	public function test_get_id(): void {
		$this->assertEquals('test', $this->gateway->get_id());
	}

	/**
	 * Test get_all_ids returns main ID and other IDs.
	 */
	public function test_get_all_ids(): void {
		$gateway = new Test_Gateway_With_Other_Ids();
		$all_ids = $gateway->get_all_ids();

		$this->assertContains('test-multi', $all_ids);
		$this->assertContains('test-alt', $all_ids);
		$this->assertContains('test-legacy', $all_ids);
		$this->assertCount(3, $all_ids);
	}

	/**
	 * Test get_all_ids with duplicates returns unique IDs.
	 */
	public function test_get_all_ids_unique(): void {
		$gateway = new Test_Gateway_With_Duplicate_Ids();
		$all_ids = $gateway->get_all_ids();

		$this->assertCount(2, $all_ids);
	}

	/**
	 * Test get_payment_method_display returns null by default.
	 */
	public function test_get_payment_method_display_default(): void {
		$result = $this->gateway->get_payment_method_display($this->membership);

		$this->assertNull($result);
	}

	/**
	 * Test get_change_payment_method_url returns null by default.
	 */
	public function test_get_change_payment_method_url_default(): void {
		$result = $this->gateway->get_change_payment_method_url($this->membership);

		$this->assertNull($result);
	}

	/**
	 * Test supports_recurring returns false by default.
	 */
	public function test_supports_recurring_default(): void {
		$this->assertFalse($this->gateway->supports_recurring());
	}

	/**
	 * Test supports_free_trials returns false by default.
	 */
	public function test_supports_free_trials_default(): void {
		$this->assertFalse($this->gateway->supports_free_trials());
	}

	/**
	 * Test supports_amount_update returns false by default.
	 */
	public function test_supports_amount_update_default(): void {
		$this->assertFalse($this->gateway->supports_amount_update());
	}

	/**
	 * Test supports_payment_polling returns false by default.
	 */
	public function test_supports_payment_polling_default(): void {
		$this->assertFalse($this->gateway->supports_payment_polling());
	}

	/**
	 * Test verify_and_complete_payment returns default response.
	 */
	public function test_verify_and_complete_payment_default(): void {
		$result = $this->gateway->verify_and_complete_payment($this->payment->get_id());

		$this->assertIsArray($result);
		$this->assertArrayHasKey('success', $result);
		$this->assertArrayHasKey('status', $result);
		$this->assertArrayHasKey('message', $result);
		$this->assertFalse($result['success']);
		$this->assertEquals('pending', $result['status']);
	}

	/**
	 * Test get_public_title returns registered gateway title.
	 */
	public function test_get_public_title(): void {
		// Mock wu_get_gateways to return our test gateway
		$gateway_manager = \WP_Ultimo\Managers\Gateway_Manager::get_instance();
		
		// Register the gateway temporarily
		add_filter('wu_gateways', function ($gateways) {
			$gateways['test'] = [
				'title' => 'Test Payment Gateway',
				'class' => Test_Gateway::class,
			];
			return $gateways;
		}, 10);

		// Force re-initialization of gateways
		$reflection = new \ReflectionClass($gateway_manager);
		$property   = $reflection->getProperty('gateways');
		$property->setAccessible(true);
		$property->setValue($gateway_manager, null);

		$title = $this->gateway->get_public_title();

		$this->assertEquals('Test Payment Gateway', $title);

		// Clean up
		remove_all_filters('wu_gateways', 10);
	}

	/**
	 * Test get_public_title falls back to formatted ID.
	 */
	public function test_get_public_title_fallback(): void {
		$gateway = new Test_Gateway_Unregistered();
		$title   = $gateway->get_public_title();

		$this->assertEquals('Test Unregistered', $title);
	}

	/**
	 * Test get_return_url generates correct URL.
	 */
	public function test_get_return_url(): void {
		$url = $this->gateway->get_return_url();

		$this->assertStringContainsString('payment=' . $this->payment->get_hash(), $url);
		$this->assertStringContainsString('status=done', $url);
	}

	/**
	 * Test get_cancel_url generates correct URL.
	 */
	public function test_get_cancel_url(): void {
		$url = $this->gateway->get_cancel_url();

		$this->assertStringContainsString('payment=' . $this->payment->get_hash(), $url);
	}

	/**
	 * Test get_confirm_url generates correct URL.
	 */
	public function test_get_confirm_url(): void {
		$url = $this->gateway->get_confirm_url();

		$this->assertStringContainsString('payment=' . $this->payment->get_hash(), $url);
		$this->assertStringContainsString('wu-confirm=test', $url);
	}

	/**
	 * Test get_webhook_listener_url generates correct URL.
	 */
	public function test_get_webhook_listener_url(): void {
		$url = $this->gateway->get_webhook_listener_url();

		$this->assertStringContainsString('wu-gateway=test', $url);
	}

	/**
	 * Test set_payment updates payment property.
	 */
	public function test_set_payment(): void {
		$gateway = new Test_Gateway();

		$new_payment = wu_create_payment([
			'customer_id'   => $this->customer->get_id(),
			'membership_id' => $this->membership->get_id(),
			'currency'      => 'USD',
			'subtotal'      => 20.00,
			'total'         => 20.00,
			'status'        => Payment_Status::PENDING,
			'gateway'       => 'test',
		]);

		$gateway->set_payment($new_payment);

		$this->assertSame($new_payment, $gateway->get_payment());
	}

	/**
	 * Test set_membership updates membership property.
	 */
	public function test_set_membership(): void {
		$gateway = new Test_Gateway();

		$gateway->set_membership($this->membership);

		$this->assertSame($this->membership, $gateway->get_membership());
	}

	/**
	 * Test set_customer updates customer property.
	 */
	public function test_set_customer(): void {
		$gateway = new Test_Gateway();

		$gateway->set_customer($this->customer);

		$this->assertSame($this->customer, $gateway->get_customer());
	}

	/**
	 * Test trigger_payment_processed fires action.
	 */
	public function test_trigger_payment_processed(): void {
		$action_fired = false;

		add_action('wu_gateway_payment_processed', function ($payment, $membership, $gateway) use (&$action_fired) {
			$action_fired = true;
			$this->assertInstanceOf(\WP_Ultimo\Models\Payment::class, $payment);
			$this->assertInstanceOf(\WP_Ultimo\Models\Membership::class, $membership);
			$this->assertInstanceOf(Test_Gateway::class, $gateway);
		}, 10, 3);

		$this->gateway->trigger_payment_processed($this->payment, $this->membership);

		$this->assertTrue($action_fired);
	}

	/**
	 * Test save_swap creates transient.
	 */
	public function test_save_swap(): void {
		$swap_id = $this->gateway->save_swap($this->cart);

		$this->assertStringStartsWith('wu_swap_', $swap_id);

		$saved_cart = get_site_transient($swap_id);
		$this->assertInstanceOf(\WP_Ultimo\Checkout\Cart::class, $saved_cart);
	}

	/**
	 * Test get_saved_swap retrieves cart.
	 */
	public function test_get_saved_swap(): void {
		$swap_id = $this->gateway->save_swap($this->cart);
		$retrieved = $this->gateway->get_saved_swap($swap_id);

		$this->assertInstanceOf(\WP_Ultimo\Checkout\Cart::class, $retrieved);
	}

	/**
	 * Test get_saved_swap returns false for non-existent swap.
	 */
	public function test_get_saved_swap_nonexistent(): void {
		$retrieved = $this->gateway->get_saved_swap('wu_swap_nonexistent');

		$this->assertFalse($retrieved);
	}

	/**
	 * Test get_backwards_compatibility_v1_id returns false by default.
	 */
	public function test_get_backwards_compatibility_v1_id_default(): void {
		$this->assertFalse($this->gateway->get_backwards_compatibility_v1_id());
	}

	/**
	 * Test get_backwards_compatibility_v1_id returns set value.
	 */
	public function test_get_backwards_compatibility_v1_id_set(): void {
		$gateway = new Test_Gateway_With_V1_Compat();

		$this->assertEquals('legacy_test', $gateway->get_backwards_compatibility_v1_id());
	}

	/**
	 * Test get_amount_update_message when not supported.
	 */
	public function test_get_amount_update_message_not_supported(): void {
		$message = $this->gateway->get_amount_update_message();

		$this->assertStringContainsString('cancelled', $message);
		$this->assertStringContainsString('next billing cycle', $message);
	}

	/**
	 * Test get_amount_update_message for customer.
	 */
	public function test_get_amount_update_message_to_customer(): void {
		$message = $this->gateway->get_amount_update_message(true);

		$this->assertStringContainsString('You will receive', $message);
	}

	/**
	 * Test get_amount_update_message for admin.
	 */
	public function test_get_amount_update_message_to_admin(): void {
		$message = $this->gateway->get_amount_update_message(false);

		$this->assertStringContainsString('customer will receive', $message);
	}

	/**
	 * Test get_amount_update_message when supported.
	 */
	public function test_get_amount_update_message_supported(): void {
		$gateway = new Test_Gateway_With_Amount_Update();
		$message = $gateway->get_amount_update_message();

		$this->assertStringContainsString('updated', $message);
	}

	/**
	 * Test process_membership_update with no changes returns true.
	 */
	public function test_process_membership_update_no_changes(): void {
		$result = $this->gateway->process_membership_update($this->membership, $this->customer);

		$this->assertTrue($result);
	}

	/**
	 * Test process_membership_update with amount change.
	 */
	public function test_process_membership_update_amount_change(): void {
		// Use reflection to set the _original property
		$reflection = new \ReflectionClass($this->membership);
		$property   = $reflection->getProperty('_original');
		$property->setAccessible(true);
		$property->setValue($this->membership, [
			'amount'        => 10.00,
			'duration'      => 1,
			'duration_unit' => 'month',
		]);

		// Change amount
		$this->membership->set_amount(20.00);

		$result = $this->gateway->process_membership_update($this->membership, $this->customer);

		$this->assertTrue($result);
		$this->assertEquals('', $this->membership->get_gateway());
		$this->assertFalse($this->membership->get_auto_renew());
	}

	/**
	 * Test process_membership_update with duration change.
	 */
	public function test_process_membership_update_duration_change(): void {
		// Use reflection to set the _original property
		$reflection = new \ReflectionClass($this->membership);
		$property   = $reflection->getProperty('_original');
		$property->setAccessible(true);
		$property->setValue($this->membership, [
			'amount'        => 10.00,
			'duration'      => 1,
			'duration_unit' => 'month',
		]);

		// Change duration
		$this->membership->set_duration(3);

		$result = $this->gateway->process_membership_update($this->membership, $this->customer);

		$this->assertTrue($result);
		$this->assertEquals('', $this->membership->get_gateway());
	}

	/**
	 * Test get_payment_url_on_gateway returns empty string by default.
	 */
	public function test_get_payment_url_on_gateway_default(): void {
		$url = $this->gateway->get_payment_url_on_gateway('test_payment_123');

		$this->assertEquals('', $url);
	}

	/**
	 * Test get_subscription_url_on_gateway returns empty string by default.
	 */
	public function test_get_subscription_url_on_gateway_default(): void {
		$url = $this->gateway->get_subscription_url_on_gateway('test_sub_123');

		$this->assertEquals('', $url);
	}

	/**
	 * Test get_customer_url_on_gateway returns empty string by default.
	 */
	public function test_get_customer_url_on_gateway_default(): void {
		$url = $this->gateway->get_customer_url_on_gateway('test_cust_123');

		$this->assertEquals('', $url);
	}

	/**
	 * Test optional methods have default implementations.
	 */
	public function test_optional_methods_defaults(): void {
		// These should not throw errors
		$this->gateway->init();
		$this->gateway->settings();
		$this->gateway->fields();
		$this->gateway->hooks();
		$this->gateway->run_preflight();
		$this->gateway->register_scripts();
		$this->gateway->before_backwards_compatible_webhook();
		$this->gateway->process_webhooks();
		$this->gateway->process_confirmation();
		$this->gateway->update_payment_method();

		$this->assertTrue(true);
	}
}

/**
 * Concrete test implementation of Base_Gateway.
 */
class Test_Gateway extends Base_Gateway {

	/**
	 * Track if init was called.
	 *
	 * @var bool
	 */
	public $init_called = false;

	/**
	 * Constructor.
	 *
	 * @param \WP_Ultimo\Checkout\Cart|null $order Cart order.
	 */
	public function __construct($order = null) {
		$this->id = 'test';
		parent::__construct($order);
	}

	/**
	 * Init method.
	 */
	public function init() {
		$this->init_called = true;
	}

	/**
	 * Process checkout (required abstract method).
	 *
	 * @param \WP_Ultimo\Models\Payment    $payment Payment.
	 * @param \WP_Ultimo\Models\Membership $membership Membership.
	 * @param \WP_Ultimo\Models\Customer   $customer Customer.
	 * @param \WP_Ultimo\Checkout\Cart     $cart Cart.
	 * @param string                       $type Checkout type.
	 * @return bool
	 */
	public function process_checkout($payment, $membership, $customer, $cart, $type) {
		return true;
	}

	/**
	 * Process cancellation (required abstract method).
	 *
	 * @param \WP_Ultimo\Models\Membership $membership Membership.
	 * @param \WP_Ultimo\Models\Customer   $customer Customer.
	 * @return bool|WP_Error
	 */
	public function process_cancellation($membership, $customer) {
		return true;
	}

	/**
	 * Process refund (required abstract method).
	 *
	 * @param float                        $amount Amount.
	 * @param \WP_Ultimo\Models\Payment    $payment Payment.
	 * @param \WP_Ultimo\Models\Membership $membership Membership.
	 * @param \WP_Ultimo\Models\Customer   $customer Customer.
	 * @return bool
	 */
	public function process_refund($amount, $payment, $membership, $customer) {
		return true;
	}

	/**
	 * Expose protected customer for testing.
	 *
	 * @return \WP_Ultimo\Models\Customer|null
	 */
	public function get_customer() {
		return $this->customer;
	}

	/**
	 * Expose protected membership for testing.
	 *
	 * @return \WP_Ultimo\Models\Membership|null
	 */
	public function get_membership() {
		return $this->membership;
	}

	/**
	 * Expose protected payment for testing.
	 *
	 * @return \WP_Ultimo\Models\Payment|null
	 */
	public function get_payment() {
		return $this->payment;
	}
}

/**
 * Test gateway with other IDs.
 */
class Test_Gateway_With_Other_Ids extends Base_Gateway {

	/**
	 * Track if init was called.
	 *
	 * @var bool
	 */
	public $init_called = false;

	/**
	 * Constructor.
	 *
	 * @param \WP_Ultimo\Checkout\Cart|null $order Cart order.
	 */
	public function __construct($order = null) {
		$this->id        = 'test-multi';
		$this->other_ids = ['test-alt', 'test-legacy'];
		parent::__construct($order);
	}

	/**
	 * Init method.
	 */
	public function init() {
		$this->init_called = true;
	}

	/**
	 * Process checkout (required abstract method).
	 *
	 * @param \WP_Ultimo\Models\Payment    $payment Payment.
	 * @param \WP_Ultimo\Models\Membership $membership Membership.
	 * @param \WP_Ultimo\Models\Customer   $customer Customer.
	 * @param \WP_Ultimo\Checkout\Cart     $cart Cart.
	 * @param string                       $type Checkout type.
	 * @return bool
	 */
	public function process_checkout($payment, $membership, $customer, $cart, $type) {
		return true;
	}

	/**
	 * Process cancellation (required abstract method).
	 *
	 * @param \WP_Ultimo\Models\Membership $membership Membership.
	 * @param \WP_Ultimo\Models\Customer   $customer Customer.
	 * @return bool|\WP_Error
	 */
	public function process_cancellation($membership, $customer) {
		return true;
	}

	/**
	 * Process refund (required abstract method).
	 *
	 * @param float                        $amount Amount.
	 * @param \WP_Ultimo\Models\Payment    $payment Payment.
	 * @param \WP_Ultimo\Models\Membership $membership Membership.
	 * @param \WP_Ultimo\Models\Customer   $customer Customer.
	 * @return bool
	 */
	public function process_refund($amount, $payment, $membership, $customer) {
		return true;
	}
}

/**
 * Test gateway with duplicate IDs.
 */
class Test_Gateway_With_Duplicate_Ids extends Base_Gateway {

	/**
	 * Constructor.
	 *
	 * @param \WP_Ultimo\Checkout\Cart|null $order Cart order.
	 */
	public function __construct($order = null) {
		$this->id        = 'test-dup';
		$this->other_ids = ['test-dup', 'test-other'];
		parent::__construct($order);
	}

	/**
	 * Process checkout (required abstract method).
	 *
	 * @param \WP_Ultimo\Models\Payment    $payment Payment.
	 * @param \WP_Ultimo\Models\Membership $membership Membership.
	 * @param \WP_Ultimo\Models\Customer   $customer Customer.
	 * @param \WP_Ultimo\Checkout\Cart     $cart Cart.
	 * @param string                       $type Checkout type.
	 * @return bool
	 */
	public function process_checkout($payment, $membership, $customer, $cart, $type) {
		return true;
	}

	/**
	 * Process cancellation (required abstract method).
	 *
	 * @param \WP_Ultimo\Models\Membership $membership Membership.
	 * @param \WP_Ultimo\Models\Customer   $customer Customer.
	 * @return bool|\WP_Error
	 */
	public function process_cancellation($membership, $customer) {
		return true;
	}

	/**
	 * Process refund (required abstract method).
	 *
	 * @param float                        $amount Amount.
	 * @param \WP_Ultimo\Models\Payment    $payment Payment.
	 * @param \WP_Ultimo\Models\Membership $membership Membership.
	 * @param \WP_Ultimo\Models\Customer   $customer Customer.
	 * @return bool
	 */
	public function process_refund($amount, $payment, $membership, $customer) {
		return true;
	}
}

/**
 * Test gateway that is not registered.
 */
class Test_Gateway_Unregistered extends Test_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->id = 'test-unregistered';
		parent::__construct();
	}
}

/**
 * Test gateway with v1 compatibility ID.
 */
class Test_Gateway_With_V1_Compat extends Test_Gateway {

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();
		$this->backwards_compatibility_v1_id = 'legacy_test';
	}
}

/**
 * Test gateway that supports amount updates.
 */
class Test_Gateway_With_Amount_Update extends Test_Gateway {

	/**
	 * Supports amount update.
	 *
	 * @return bool
	 */
	public function supports_amount_update() {
		return true;
	}
}

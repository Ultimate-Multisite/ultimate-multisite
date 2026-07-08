<?php
/**
 * Tests for the Faker class.
 */

namespace WP_Ultimo;

use WP_UnitTestCase;

/**
 * @group faker
 */
class Faker_Test extends WP_UnitTestCase {

	/**
	 * @var Faker
	 */
	private $faker;

	public function set_up() {
		parent::set_up();
		$this->faker = new Faker();
	}

	// ------------------------------------------------------------------
	// Constructor / generate
	// ------------------------------------------------------------------

	public function test_constructor_creates_faker_instance() {
		$this->assertInstanceOf(Faker::class, $this->faker);
	}

	public function test_generate_returns_faker_generator() {
		$generator = $this->faker->generate();
		$this->assertInstanceOf(\Faker\Generator::class, $generator);
	}

	// ------------------------------------------------------------------
	// get_fake_data_generated / set_fake_data_generated
	// ------------------------------------------------------------------

	public function test_get_fake_data_generated_returns_default_structure() {
		$data = $this->faker->get_fake_data_generated();

		$this->assertIsArray($data);
		$this->assertArrayHasKey('customers', $data);
		$this->assertArrayHasKey('products', $data);
		$this->assertArrayHasKey('memberships', $data);
		$this->assertArrayHasKey('domains', $data);
		$this->assertArrayHasKey('events', $data);
		$this->assertArrayHasKey('discount_codes', $data);
		$this->assertArrayHasKey('checkout_forms', $data);
		$this->assertArrayHasKey('emails', $data);
		$this->assertArrayHasKey('broadcasts', $data);
		$this->assertArrayHasKey('webhooks', $data);
		$this->assertArrayHasKey('payments', $data);
		$this->assertArrayHasKey('sites', $data);
	}

	public function test_get_fake_data_generated_returns_empty_array_for_model() {
		$data = $this->faker->get_fake_data_generated('customers');
		$this->assertIsArray($data);
		$this->assertEmpty($data);
	}

	public function test_get_fake_data_generated_returns_empty_array_for_unknown_model() {
		$data = $this->faker->get_fake_data_generated('nonexistent');
		$this->assertIsArray($data);
		$this->assertEmpty($data);
	}

	public function test_set_fake_data_generated_adds_value() {
		$this->faker->set_fake_data_generated('customers', 42);

		$data = $this->faker->get_fake_data_generated('customers');
		$this->assertCount(1, $data);
		$this->assertEquals(42, $data[0]);
	}

	public function test_set_fake_data_generated_appends_multiple_values() {
		$this->faker->set_fake_data_generated('products', 1);
		$this->faker->set_fake_data_generated('products', 2);
		$this->faker->set_fake_data_generated('products', 3);

		$data = $this->faker->get_fake_data_generated('products');
		$this->assertCount(3, $data);
		$this->assertEquals([1, 2, 3], $data);
	}

	public function test_set_fake_data_generated_works_for_different_models() {
		$this->faker->set_fake_data_generated('customers', 'c1');
		$this->faker->set_fake_data_generated('products', 'p1');

		$this->assertCount(1, $this->faker->get_fake_data_generated('customers'));
		$this->assertCount(1, $this->faker->get_fake_data_generated('products'));
		$this->assertEmpty($this->faker->get_fake_data_generated('memberships'));
	}

	// ------------------------------------------------------------------
	// get_option_debug_faker
	// ------------------------------------------------------------------

	public function test_get_option_debug_faker_returns_default_structure() {
		$data = $this->faker->get_option_debug_faker();

		$this->assertIsArray($data);
		$this->assertArrayHasKey('customers', $data);
		$this->assertArrayHasKey('products', $data);
		$this->assertArrayHasKey('memberships', $data);
		$this->assertArrayHasKey('domains', $data);
		$this->assertArrayHasKey('events', $data);
		$this->assertArrayHasKey('discount_codes', $data);
		$this->assertArrayHasKey('checkout_forms', $data);
		$this->assertArrayHasKey('emails', $data);
		$this->assertArrayHasKey('broadcasts', $data);
		$this->assertArrayHasKey('webhooks', $data);
		$this->assertArrayHasKey('payments', $data);
		$this->assertArrayHasKey('sites', $data);
	}

	// ------------------------------------------------------------------
	// generate_fake_customers
	// ------------------------------------------------------------------

	public function test_generate_fake_customers_creates_one_by_default() {
		$this->faker->generate_fake_customers();

		$data = $this->faker->get_fake_data_generated('customers');
		$this->assertCount(1, $data);
	}

	public function test_generate_fake_customers_creates_multiple() {
		$this->faker->generate_fake_customers(3);

		$data = $this->faker->get_fake_data_generated('customers');
		$this->assertCount(3, $data);
	}

	public function test_generate_fake_customers_creates_valid_customer_objects() {
		$this->faker->generate_fake_customers();

		$data     = $this->faker->get_fake_data_generated('customers');
		$customer = $data[0];

		$this->assertInstanceOf(\WP_Ultimo\Models\Customer::class, $customer);
		$this->assertGreaterThan(0, $customer->get_id());
		$this->assertGreaterThan(0, $customer->get_user_id());
	}

	// ------------------------------------------------------------------
	// generate_fake_products
	// ------------------------------------------------------------------

	public function test_generate_fake_products_creates_one_by_default() {
		$this->faker->generate_fake_products();

		$data = $this->faker->get_fake_data_generated('products');
		$this->assertCount(1, $data);
	}

	public function test_generate_fake_products_creates_multiple() {
		$this->faker->generate_fake_products(2);

		$data = $this->faker->get_fake_data_generated('products');
		$this->assertCount(2, $data);
	}

	public function test_generate_fake_products_creates_valid_product_objects() {
		$this->faker->generate_fake_products();

		$data    = $this->faker->get_fake_data_generated('products');
		$product = $data[0];

		$this->assertInstanceOf(\WP_Ultimo\Models\Product::class, $product);
		$this->assertGreaterThan(0, $product->get_id());
		$this->assertNotEmpty($product->get_name());
	}

	public function test_generate_fake_products_has_valid_type() {
		$this->faker->generate_fake_products(5);

		$valid_types = ['plan', 'package', 'service'];
		$data        = $this->faker->get_fake_data_generated('products');

		foreach ($data as $product) {
			$this->assertContains($product->get_type(), $valid_types);
		}
	}

	public function test_generate_fake_products_has_valid_pricing_type() {
		$this->faker->generate_fake_products(5);

		$valid_pricing = ['paid', 'free', 'contact_us'];
		$data          = $this->faker->get_fake_data_generated('products');

		foreach ($data as $product) {
			$this->assertContains($product->get_pricing_type(), $valid_pricing);
		}
	}

	// ------------------------------------------------------------------
	// generate_fake_memberships
	// ------------------------------------------------------------------

	public function test_generate_fake_memberships_creates_one_by_default() {
		$this->faker->generate_fake_memberships();

		$data = $this->faker->get_fake_data_generated('memberships');
		$this->assertCount(1, $data);
	}

	public function test_generate_fake_memberships_creates_valid_membership_objects() {
		$this->faker->generate_fake_memberships();

		$data       = $this->faker->get_fake_data_generated('memberships');
		$membership = $data[0];

		$this->assertInstanceOf(\WP_Ultimo\Models\Membership::class, $membership);
		$this->assertGreaterThan(0, $membership->get_id());
	}

	public function test_generate_fake_memberships_auto_creates_customer_and_product() {
		$this->faker->generate_fake_memberships();

		// Should have auto-created a customer and product
		$customers = $this->faker->get_fake_data_generated('customers');
		$products  = $this->faker->get_fake_data_generated('products');

		$this->assertNotEmpty($customers);
		$this->assertNotEmpty($products);
	}

	public function test_generate_fake_memberships_has_valid_status() {
		$this->faker->generate_fake_memberships(3);

		$valid_statuses = ['pending', 'active', 'on-hold', 'expired', 'cancelled'];
		$data           = $this->faker->get_fake_data_generated('memberships');

		foreach ($data as $membership) {
			$this->assertContains($membership->get_status(), $valid_statuses);
		}
	}

	// ------------------------------------------------------------------
	// generate_fake_domain
	// ------------------------------------------------------------------

	public function test_generate_fake_domain_creates_one_by_default() {
		$this->faker->generate_fake_domain();

		$data = $this->faker->get_fake_data_generated('domains');
		$this->assertCount(1, $data);
	}

	public function test_generate_fake_domain_creates_valid_domain_objects() {
		$this->faker->generate_fake_domain();

		$data   = $this->faker->get_fake_data_generated('domains');
		$domain = $data[0];

		$this->assertInstanceOf(\WP_Ultimo\Models\Domain::class, $domain);
		$this->assertGreaterThan(0, $domain->get_id());
		$this->assertNotEmpty($domain->get_domain());
	}

	// ------------------------------------------------------------------
	// generate_fake_checkout_form
	// ------------------------------------------------------------------

	public function test_generate_fake_checkout_form_creates_one_by_default() {
		$this->faker->generate_fake_checkout_form();

		$data = $this->faker->get_fake_data_generated('checkout_forms');
		$this->assertCount(1, $data);
	}

	public function test_generate_fake_checkout_form_creates_valid_objects() {
		$this->faker->generate_fake_checkout_form();

		$data = $this->faker->get_fake_data_generated('checkout_forms');
		$form = $data[0];

		$this->assertInstanceOf(\WP_Ultimo\Models\Checkout_Form::class, $form);
		$this->assertGreaterThan(0, $form->get_id());
		$this->assertNotEmpty($form->get_name());
	}

	// ------------------------------------------------------------------
	// generate_fake_email
	// ------------------------------------------------------------------

	public function test_generate_fake_email_creates_one_by_default() {
		$this->faker->generate_fake_email();

		$data = $this->faker->get_fake_data_generated('emails');
		$this->assertCount(1, $data);
	}

	public function test_generate_fake_email_creates_valid_objects() {
		$this->faker->generate_fake_email();

		$data  = $this->faker->get_fake_data_generated('emails');
		$email = $data[0];

		$this->assertInstanceOf(\WP_Ultimo\Models\Email::class, $email);
		$this->assertGreaterThan(0, $email->get_id());
	}

	// ------------------------------------------------------------------
	// generate_fake_broadcast
	// ------------------------------------------------------------------

	public function test_generate_fake_broadcast_creates_one_by_default() {
		$this->faker->generate_fake_broadcast();

		$data = $this->faker->get_fake_data_generated('broadcasts');
		$this->assertCount(1, $data);
	}

	public function test_generate_fake_broadcast_creates_valid_objects() {
		$this->faker->generate_fake_broadcast();

		$data      = $this->faker->get_fake_data_generated('broadcasts');
		$broadcast = $data[0];

		$this->assertInstanceOf(\WP_Ultimo\Models\Broadcast::class, $broadcast);
		$this->assertGreaterThan(0, $broadcast->get_id());
	}

	public function test_generate_fake_broadcast_has_valid_notice_type() {
		$this->faker->generate_fake_broadcast(5);

		$valid_types = ['info', 'success', 'warning', 'error'];
		$data        = $this->faker->get_fake_data_generated('broadcasts');

		foreach ($data as $broadcast) {
			$this->assertContains($broadcast->get_notice_type(), $valid_types);
		}
	}

	// ------------------------------------------------------------------
	// generate_fake_webhook
	// ------------------------------------------------------------------

	public function test_generate_fake_webhook_creates_one_by_default() {
		$this->faker->generate_fake_webhook();

		$data = $this->faker->get_fake_data_generated('webhooks');
		$this->assertCount(1, $data);
	}

	public function test_generate_fake_webhook_creates_valid_objects() {
		$this->faker->generate_fake_webhook();

		$data    = $this->faker->get_fake_data_generated('webhooks');
		$webhook = $data[0];

		$this->assertInstanceOf(\WP_Ultimo\Models\Webhook::class, $webhook);
		$this->assertGreaterThan(0, $webhook->get_id());
		$this->assertNotEmpty($webhook->get_name());
	}

	public function test_generate_fake_webhook_has_valid_event() {
		$this->faker->generate_fake_webhook(3);

		$valid_events = [
			'account_created',
			'account_deleted',
			'new_domain_mapping',
			'payment_received',
			'payment_successful',
			'payment_failed',
			'refund_issued',
			'plan_change',
		];

		$data = $this->faker->get_fake_data_generated('webhooks');

		foreach ($data as $webhook) {
			$this->assertContains($webhook->get_event(), $valid_events);
		}
	}

	// ------------------------------------------------------------------
	// generate_fake_payment (requires memberships)
	// ------------------------------------------------------------------

	public function test_generate_fake_payment_creates_one_by_default() {
		// Need memberships first
		$this->faker->generate_fake_memberships();
		$this->faker->generate_fake_payment();

		$data = $this->faker->get_fake_data_generated('payments');
		$this->assertCount(1, $data);
	}

	public function test_generate_fake_payment_creates_valid_objects() {
		$this->faker->generate_fake_memberships();
		$this->faker->generate_fake_payment();

		$data    = $this->faker->get_fake_data_generated('payments');
		$payment = $data[0];

		$this->assertInstanceOf(\WP_Ultimo\Models\Payment::class, $payment);
		$this->assertGreaterThan(0, $payment->get_id());
	}

	// ------------------------------------------------------------------
	// generate_fake_events (requires memberships)
	// ------------------------------------------------------------------

	public function test_generate_fake_events_creates_one_by_default() {
		$this->faker->generate_fake_memberships();
		$this->faker->generate_fake_events();

		$data = $this->faker->get_fake_data_generated('events');
		$this->assertCount(1, $data);
	}

	// ------------------------------------------------------------------
	// generate_fake_site
	// ------------------------------------------------------------------

	public function test_generate_fake_site_creates_one_by_default() {
		// Create memberships first for customer_owned type
		$this->faker->generate_fake_memberships();
		$this->faker->generate_fake_site(1, 'default');

		$data = $this->faker->get_fake_data_generated('sites');
		$this->assertCount(1, $data);
	}

	public function test_generate_fake_site_creates_valid_objects() {
		$this->faker->generate_fake_site(1, 'site_template');

		$data = $this->faker->get_fake_data_generated('sites');
		$site = $data[0];

		$this->assertInstanceOf(\WP_Ultimo\Models\Site::class, $site);
		$this->assertGreaterThan(0, $site->get_id());
	}

	// ------------------------------------------------------------------
	// Full pipeline: multiple generators together
	// ------------------------------------------------------------------

	public function test_full_pipeline_generates_all_data_types() {
		$this->faker->generate_fake_customers(2);
		$this->faker->generate_fake_products(2);
		$this->faker->generate_fake_memberships(2);
		$this->faker->generate_fake_domain();
		$this->faker->generate_fake_checkout_form();
		$this->faker->generate_fake_email();
		$this->faker->generate_fake_broadcast();
		$this->faker->generate_fake_webhook();

		$all = $this->faker->get_fake_data_generated();

		// Customers: 2 explicit + at least 2 from memberships auto-create
		$this->assertGreaterThanOrEqual(2, count($all['customers']));
		$this->assertGreaterThanOrEqual(2, count($all['products']));
		$this->assertCount(2, $all['memberships']);
		$this->assertCount(1, $all['domains']);
		$this->assertCount(1, $all['checkout_forms']);
		$this->assertCount(1, $all['emails']);
		$this->assertCount(1, $all['broadcasts']);
		$this->assertCount(1, $all['webhooks']);
	}

	// ------------------------------------------------------------------
	// get_random_data (private, tested via reflection)
	// ------------------------------------------------------------------

	public function test_get_random_data_returns_false_for_empty_model() {
		$method = new \ReflectionMethod(Faker::class, 'get_random_data');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke($this->faker, 'customers');
		$this->assertFalse($result);
	}

	public function test_get_random_data_returns_false_for_falsy_model() {
		$method = new \ReflectionMethod(Faker::class, 'get_random_data');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke($this->faker, '');
		$this->assertFalse($result);
	}

	public function test_get_random_data_returns_value_from_in_memory() {
		$method = new \ReflectionMethod(Faker::class, 'get_random_data');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$this->faker->set_fake_data_generated('customers', 'test_value');

		$result = $method->invoke($this->faker, 'customers');
		$this->assertEquals('test_value', $result);
	}

	// ------------------------------------------------------------------
	// get_random_customer (private, tested via reflection)
	// ------------------------------------------------------------------

	public function test_get_random_customer_returns_false_when_empty() {
		$method = new \ReflectionMethod(Faker::class, 'get_random_customer');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke($this->faker, false);
		$this->assertFalse($result);
	}

	public function test_get_random_customer_creates_if_not_exist() {
		$method = new \ReflectionMethod(Faker::class, 'get_random_customer');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke($this->faker, true);
		$this->assertInstanceOf(\WP_Ultimo\Models\Customer::class, $result);
	}

	// ------------------------------------------------------------------
	// get_random_product (private, tested via reflection)
	// ------------------------------------------------------------------

	public function test_get_random_product_returns_false_when_empty() {
		$method = new \ReflectionMethod(Faker::class, 'get_random_product');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke($this->faker, false);
		$this->assertFalse($result);
	}

	public function test_get_random_product_creates_if_not_exist() {
		$method = new \ReflectionMethod(Faker::class, 'get_random_product');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke($this->faker, true);
		$this->assertInstanceOf(\WP_Ultimo\Models\Product::class, $result);
	}

	// ------------------------------------------------------------------
	// get_random_membership (private, tested via reflection)
	// ------------------------------------------------------------------

	public function test_get_random_membership_returns_false_when_empty() {
		$method = new \ReflectionMethod(Faker::class, 'get_random_membership');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke($this->faker);
		$this->assertFalse($result);
	}

	// ------------------------------------------------------------------
	// get_random_site (private, tested via reflection)
	// ------------------------------------------------------------------

	public function test_get_random_site_returns_false_when_empty() {
		$method = new \ReflectionMethod(Faker::class, 'get_random_site');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke($this->faker);
		$this->assertFalse($result);
	}

	// ------------------------------------------------------------------
	// get_random_payment (private, tested via reflection)
	// ------------------------------------------------------------------

	public function test_get_random_payment_returns_false_when_empty() {
		$method = new \ReflectionMethod(Faker::class, 'get_random_payment');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke($this->faker);
		$this->assertFalse($result);
	}

	// ------------------------------------------------------------------
	// get_faker (private, tested via reflection)
	// ------------------------------------------------------------------

	public function test_get_faker_returns_generator() {
		$method = new \ReflectionMethod(Faker::class, 'get_faker');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$result = $method->invoke($this->faker);
		$this->assertInstanceOf(\Faker\Generator::class, $result);
	}
}

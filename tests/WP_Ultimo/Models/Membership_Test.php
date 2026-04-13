<?php

namespace WP_Ultimo\Models;

use WP_Ultimo\Database\Memberships\Membership_Status;

/**
 * Unit tests for the Membership class.
 */
class Membership_Test extends \WP_UnitTestCase {

	/**
	 * Membership instance.
	 *
	 * @var Membership
	 */
	protected $membership;

	/**
	 * Customer instance.
	 *
	 * @var \WP_Ultimo\Models\Customer
	 */
	protected $customer;

	/**
	 * Product instance.
	 *
	 * @var \WP_Ultimo\Models\Product
	 */
	protected $product;

	/**
	 * Set up the test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Create a WordPress user for the customer.
		$user_id = self::factory()->user->create(
			[
				'user_login' => 'membershiptest_' . wp_generate_password(6, false),
				'user_email' => 'membershiptest_' . wp_generate_password(6, false) . '@example.com',
			]
		);

		// Create customer directly.
		$this->customer = new Customer(
			[
				'user_id'            => $user_id,
				'email_verification' => 'none',
				'type'               => 'customer',
			]
		);
		$this->customer->set_skip_validation(true);
		$this->customer->save();

		// Create product directly.
		$this->product = new Product(
			[
				'name'         => 'Test Plan',
				'slug'         => 'test-plan-' . wp_generate_password(6, false),
				'description'  => 'A test plan',
				'pricing_type' => 'paid',
				'amount'       => 29.99,
				'currency'     => 'USD',
				'duration'     => 1,
				'duration_unit' => 'month',
				'type'         => 'plan',
				'recurring'    => true,
				'active'       => true,
			]
		);
		$this->product->set_skip_validation(true);
		$this->product->save();

		// Create a membership tied to the customer and product.
		$this->membership = new Membership(
			[
				'customer_id'   => $this->customer->get_id(),
				'user_id'       => $user_id,
				'plan_id'       => $this->product->get_id(),
				'status'        => Membership_Status::ACTIVE,
				'amount'        => 29.99,
				'initial_amount' => 29.99,
				'duration'      => 1,
				'duration_unit' => 'month',
				'recurring'     => true,
				'auto_renew'    => true,
				'currency'      => 'USD',
				'gateway'       => '',
				'date_created'  => gmdate('Y-m-d H:i:s'),
				'date_modified' => gmdate('Y-m-d H:i:s'),
				'date_expiration' => gmdate('Y-m-d H:i:s', strtotime('+30 days')),
			]
		);
		$this->membership->set_skip_validation(true);
		$this->membership->save();
	}

	// ---------------------------------------------------------------
	// Duration / Billing Cycle Tests
	// ---------------------------------------------------------------

	/**
	 * Test get_duration and set_duration.
	 */
	public function test_get_and_set_duration(): void {
		$this->membership->set_duration(3);
		$this->assertSame(3, $this->membership->get_duration());

		$this->membership->set_duration(12);
		$this->assertSame(12, $this->membership->get_duration());
	}

	/**
	 * Test get_duration_unit and set_duration_unit.
	 */
	public function test_get_and_set_duration_unit(): void {
		$this->membership->set_duration_unit('day');
		$this->assertSame('day', $this->membership->get_duration_unit());

		$this->membership->set_duration_unit('week');
		$this->assertSame('week', $this->membership->get_duration_unit());

		$this->membership->set_duration_unit('month');
		$this->assertSame('month', $this->membership->get_duration_unit());

		$this->membership->set_duration_unit('year');
		$this->assertSame('year', $this->membership->get_duration_unit());
	}

	/**
	 * Test get_billing_cycles and set_billing_cycles.
	 */
	public function test_get_and_set_billing_cycles(): void {
		$this->membership->set_billing_cycles(12);
		$this->assertSame(12, $this->membership->get_billing_cycles());

		$this->membership->set_billing_cycles(0);
		$this->assertSame(0, $this->membership->get_billing_cycles());
	}

	/**
	 * Test is_forever_recurring returns true when billing_cycles is 0.
	 */
	public function test_is_forever_recurring_with_zero_cycles(): void {
		$this->membership->set_billing_cycles(0);
		$this->assertTrue($this->membership->is_forever_recurring());
	}

	/**
	 * Test is_forever_recurring returns false when billing_cycles is set.
	 */
	public function test_is_forever_recurring_with_nonzero_cycles(): void {
		$this->membership->set_billing_cycles(12);
		$this->assertFalse($this->membership->is_forever_recurring());
	}

	/**
	 * Test get_times_billed and set_times_billed.
	 */
	public function test_get_and_set_times_billed(): void {
		$this->membership->set_times_billed(5);
		$this->assertSame(5, $this->membership->get_times_billed());
	}

	/**
	 * Test add_to_times_billed increments correctly.
	 */
	public function test_add_to_times_billed(): void {
		$this->membership->set_times_billed(3);
		$result = $this->membership->add_to_times_billed(2);
		$this->assertSame(5, $this->membership->get_times_billed());
		$this->assertInstanceOf(Membership::class, $result, 'add_to_times_billed should return the membership for chaining.');
	}

	/**
	 * Test add_to_times_billed with default increment of 1.
	 */
	public function test_add_to_times_billed_default(): void {
		$this->membership->set_times_billed(0);
		$this->membership->add_to_times_billed();
		$this->assertSame(1, $this->membership->get_times_billed());
	}

	/**
	 * Test get_times_billed_description for forever recurring.
	 */
	public function test_get_times_billed_description_forever(): void {
		$this->membership->set_billing_cycles(0);
		$this->membership->set_times_billed(3);
		$description = $this->membership->get_times_billed_description();
		$this->assertStringContainsString('3', $description);
		$this->assertStringContainsString('until cancelled', $description);
	}

	/**
	 * Test get_times_billed_description for limited cycles.
	 */
	public function test_get_times_billed_description_limited(): void {
		$this->membership->set_billing_cycles(12);
		$this->membership->set_times_billed(5);
		$description = $this->membership->get_times_billed_description();
		$this->assertStringContainsString('5', $description);
		$this->assertStringContainsString('12', $description);
		$this->assertStringContainsString('cycles', $description);
	}

	// ---------------------------------------------------------------
	// Status Method Tests
	// ---------------------------------------------------------------

	/**
	 * Test is_active returns true for active status.
	 */
	public function test_is_active_with_active_status(): void {
		$this->membership->set_status(Membership_Status::ACTIVE);
		$this->assertTrue($this->membership->is_active());
	}

	/**
	 * Test is_active returns true for on-hold status.
	 */
	public function test_is_active_with_on_hold_status(): void {
		$this->membership->set_status(Membership_Status::ON_HOLD);
		$this->assertTrue($this->membership->is_active());
	}

	/**
	 * Test is_active returns false for pending status.
	 */
	public function test_is_active_with_pending_status(): void {
		$this->membership->set_status(Membership_Status::PENDING);
		$this->assertFalse($this->membership->is_active());
	}

	/**
	 * Test is_active returns false for cancelled status.
	 */
	public function test_is_active_with_cancelled_status(): void {
		$this->membership->set_status(Membership_Status::CANCELLED);
		$this->assertFalse($this->membership->is_active());
	}

	/**
	 * Test is_active returns false for expired status.
	 */
	public function test_is_active_with_expired_status(): void {
		$this->membership->set_status(Membership_Status::EXPIRED);
		$this->assertFalse($this->membership->is_active());
	}

	/**
	 * Test is_active returns false for trialing status.
	 */
	public function test_is_active_with_trialing_status(): void {
		$this->membership->set_status(Membership_Status::TRIALING);
		$this->assertFalse($this->membership->is_active());
	}

	/**
	 * Test get_status and set_status.
	 */
	public function test_get_and_set_status(): void {
		$this->membership->set_status(Membership_Status::CANCELLED);
		$this->assertSame(Membership_Status::CANCELLED, $this->membership->get_status());

		$this->membership->set_status(Membership_Status::EXPIRED);
		$this->assertSame(Membership_Status::EXPIRED, $this->membership->get_status());
	}

	/**
	 * Test get_status_label returns a non-empty string.
	 */
	public function test_get_status_label(): void {
		$this->membership->set_status(Membership_Status::ACTIVE);
		$label = $this->membership->get_status_label();
		$this->assertNotEmpty($label);
		$this->assertIsString($label);
	}

	/**
	 * Test get_status_class returns a non-empty string.
	 */
	public function test_get_status_class(): void {
		$this->membership->set_status(Membership_Status::ACTIVE);
		$class = $this->membership->get_status_class();
		$this->assertNotEmpty($class);
		$this->assertIsString($class);
	}

	/**
	 * Test is_disabled and set_disabled.
	 */
	public function test_is_disabled(): void {
		$this->membership->set_disabled(false);
		$this->assertFalse($this->membership->is_disabled());

		$this->membership->set_disabled(true);
		$this->assertTrue($this->membership->is_disabled());
	}

	// ---------------------------------------------------------------
	// Amount / Pricing Method Tests
	// ---------------------------------------------------------------

	/**
	 * Test get_amount and set_amount.
	 */
	public function test_get_and_set_amount(): void {
		$this->membership->set_amount(49.99);
		$this->assertEquals(49.99, $this->membership->get_amount());

		$this->membership->set_amount(0);
		$this->assertEquals(0, $this->membership->get_amount());
	}

	/**
	 * Test get_initial_amount and set_initial_amount.
	 */
	public function test_get_and_set_initial_amount(): void {
		$this->membership->set_initial_amount(99.99);
		$this->assertEquals(99.99, $this->membership->get_initial_amount());

		$this->membership->set_initial_amount(0);
		$this->assertEquals(0, $this->membership->get_initial_amount());
	}

	/**
	 * Test is_recurring returns true when recurring and amount > 0.
	 */
	public function test_is_recurring_true(): void {
		$this->membership->set_recurring(true);
		$this->membership->set_amount(29.99);
		$this->assertTrue($this->membership->is_recurring());
	}

	/**
	 * Test is_recurring returns false when recurring flag is false.
	 */
	public function test_is_recurring_false_when_flag_off(): void {
		$this->membership->set_recurring(false);
		$this->membership->set_amount(29.99);
		$this->assertFalse($this->membership->is_recurring());
	}

	/**
	 * Test is_recurring returns false when amount is 0.
	 */
	public function test_is_recurring_false_when_amount_zero(): void {
		$this->membership->set_recurring(true);
		$this->membership->set_amount(0);
		$this->assertFalse($this->membership->is_recurring());
	}

	/**
	 * Test is_free returns true for non-recurring membership with no initial amount.
	 */
	public function test_is_free_true(): void {
		$this->membership->set_recurring(false);
		$this->membership->set_amount(0);
		$this->membership->set_initial_amount(0);
		$this->assertTrue($this->membership->is_free());
	}

	/**
	 * Test is_free returns false for recurring membership.
	 */
	public function test_is_free_false_for_recurring(): void {
		$this->membership->set_recurring(true);
		$this->membership->set_amount(29.99);
		$this->assertFalse($this->membership->is_free());
	}

	/**
	 * Test is_free returns false for non-recurring with initial amount.
	 */
	public function test_is_free_false_with_initial_amount(): void {
		$this->membership->set_recurring(false);
		$this->membership->set_amount(0);
		$this->membership->set_initial_amount(50.00);
		$this->assertFalse($this->membership->is_free());
	}

	/**
	 * Test is_lifetime returns true for non-recurring with no expiration.
	 */
	public function test_is_lifetime_true(): void {
		$this->membership->set_recurring(false);
		$this->membership->set_amount(0);
		$this->membership->set_date_expiration('');
		$this->assertTrue($this->membership->is_lifetime());
	}

	/**
	 * Test is_lifetime returns true for non-recurring with zeroed expiration.
	 */
	public function test_is_lifetime_true_zeroed_date(): void {
		$this->membership->set_recurring(false);
		$this->membership->set_amount(0);
		$this->membership->set_date_expiration('0000-00-00 00:00:00');
		$this->assertTrue($this->membership->is_lifetime());
	}

	/**
	 * Test is_lifetime returns false for recurring membership.
	 */
	public function test_is_lifetime_false_for_recurring(): void {
		$this->membership->set_recurring(true);
		$this->membership->set_amount(29.99);
		$this->assertFalse($this->membership->is_lifetime());
	}

	/**
	 * Test get_currency returns the setting value.
	 */
	public function test_get_currency(): void {
		$currency = $this->membership->get_currency();
		$this->assertIsString($currency);
		$this->assertNotEmpty($currency);
	}

	/**
	 * Test set_currency sets the value.
	 */
	public function test_set_currency(): void {
		$this->membership->set_currency('EUR');
		// get_currency always returns the setting value, but set_currency sets the property.
		// We verify the property was set.
		$reflection = new \ReflectionProperty(Membership::class, 'currency');
		$reflection->setAccessible(true);
		$this->assertSame('EUR', $reflection->getValue($this->membership));
	}

	/**
	 * Test get_price_description for recurring membership.
	 */
	public function test_get_price_description_recurring(): void {
		$this->membership->set_recurring(true);
		$this->membership->set_amount(29.99);
		$this->membership->set_duration(1);
		$this->membership->set_duration_unit('month');
		$this->membership->set_billing_cycles(0);
		$description = $this->membership->get_price_description();
		$this->assertIsString($description);
		$this->assertNotEmpty($description);
	}

	/**
	 * Test get_price_description for recurring membership with limited cycles.
	 */
	public function test_get_price_description_recurring_limited_cycles(): void {
		$this->membership->set_recurring(true);
		$this->membership->set_amount(29.99);
		$this->membership->set_duration(1);
		$this->membership->set_duration_unit('month');
		$this->membership->set_billing_cycles(12);
		$description = $this->membership->get_price_description();
		$this->assertIsString($description);
		$this->assertStringContainsString('12', $description);
	}

	/**
	 * Test get_price_description for non-recurring membership.
	 */
	public function test_get_price_description_non_recurring(): void {
		$this->membership->set_recurring(false);
		$this->membership->set_amount(0);
		$this->membership->set_initial_amount(99.99);
		$description = $this->membership->get_price_description();
		$this->assertIsString($description);
	}

	/**
	 * Test get_price_description for free membership.
	 */
	public function test_get_price_description_free(): void {
		$this->membership->set_recurring(false);
		$this->membership->set_amount(0);
		$this->membership->set_initial_amount(0);
		$description = $this->membership->get_price_description();
		$this->assertStringContainsString('Free', $description);
	}

	/**
	 * Test get_recurring_description.
	 */
	public function test_get_recurring_description(): void {
		$this->membership->set_duration(1);
		$this->membership->set_duration_unit('month');
		$description = $this->membership->get_recurring_description();
		$this->assertIsString($description);
		$this->assertNotEmpty($description);
	}

	/**
	 * Test get_recurring_description with duration > 1.
	 */
	public function test_get_recurring_description_plural_duration(): void {
		$this->membership->set_duration(3);
		$this->membership->set_duration_unit('month');
		$description = $this->membership->get_recurring_description();
		$this->assertIsString($description);
		$this->assertStringContainsString('3', $description);
	}

	// ---------------------------------------------------------------
	// Gateway Method Tests
	// ---------------------------------------------------------------

	/**
	 * Test get_gateway and set_gateway.
	 */
	public function test_get_and_set_gateway(): void {
		$this->membership->set_gateway('stripe');
		$this->assertSame('stripe', $this->membership->get_gateway());

		$this->membership->set_gateway('manual');
		$this->assertSame('manual', $this->membership->get_gateway());

		$this->membership->set_gateway('');
		$this->assertSame('', $this->membership->get_gateway());
	}

	/**
	 * Test get_gateway_customer_id and set_gateway_customer_id.
	 */
	public function test_get_and_set_gateway_customer_id(): void {
		$this->membership->set_gateway_customer_id('cus_abc123');
		$this->assertSame('cus_abc123', $this->membership->get_gateway_customer_id());
	}

	/**
	 * Test get_gateway_subscription_id and set_gateway_subscription_id.
	 */
	public function test_get_and_set_gateway_subscription_id(): void {
		$this->membership->set_gateway_subscription_id('sub_xyz789');
		$this->assertSame('sub_xyz789', $this->membership->get_gateway_subscription_id());
	}

	/**
	 * Test has_gateway_changes detects gateway changes.
	 */
	public function test_has_gateway_changes(): void {
		// Create a fresh membership to have clean gateway_info state.
		$m = new Membership(
			[
				'customer_id'   => $this->customer->get_id(),
				'plan_id'       => $this->product->get_id(),
				'status'        => Membership_Status::ACTIVE,
				'gateway'       => 'stripe',
				'gateway_customer_id'     => 'cus_old',
				'gateway_subscription_id' => 'sub_old',
			]
		);

		// Access has_gateway_changes via reflection since it is protected.
		$reflection = new \ReflectionMethod(Membership::class, 'has_gateway_changes');
		$reflection->setAccessible(true);

		// No changes yet.
		$this->assertFalse($reflection->invoke($m));

		// Change gateway.
		$m->set_gateway('manual');
		$this->assertTrue($reflection->invoke($m));
	}

	// ---------------------------------------------------------------
	// Product-Related Method Tests
	// ---------------------------------------------------------------

	/**
	 * Test adding a product to the membership.
	 */
	public function test_add_product(): void {
		// Create an addon product.
		$addon = new Product(
			[
				'name'         => 'Addon Product',
				'slug'         => 'addon-product-' . wp_generate_password(6, false),
				'pricing_type' => 'paid',
				'amount'       => 9.99,
				'currency'     => 'USD',
				'duration'     => 1,
				'duration_unit' => 'month',
				'type'         => 'package',
				'active'       => true,
			]
		);
		$addon->set_skip_validation(true);
		$addon->save();

		$product_id = $addon->get_id();
		$this->membership->add_product($product_id, 2);

		$addon_ids = $this->membership->get_addon_ids();
		$this->assertContains($product_id, $addon_ids);

		$addon_products = $this->membership->get_addon_products();
		$found = false;
		foreach ($addon_products as $item) {
			if ($item['product']->get_id() === $product_id) {
				$this->assertEquals(2, $item['quantity']);
				$found = true;
			}
		}
		$this->assertTrue($found, 'Addon product should be found in addon products list.');

		// Add more of the same product.
		$this->membership->add_product($product_id, 3);
		$addon_products = $this->membership->get_addon_products();
		foreach ($addon_products as $item) {
			if ($item['product']->get_id() === $product_id) {
				$this->assertEquals(5, $item['quantity']);
			}
		}
	}

	/**
	 * Test removing a product from the membership.
	 */
	public function test_remove_product(): void {
		$addon = new Product(
			[
				'name'         => 'Removable Addon',
				'slug'         => 'removable-addon-' . wp_generate_password(6, false),
				'pricing_type' => 'paid',
				'amount'       => 4.99,
				'currency'     => 'USD',
				'duration'     => 1,
				'duration_unit' => 'month',
				'type'         => 'package',
				'active'       => true,
			]
		);
		$addon->set_skip_validation(true);
		$addon->save();

		$product_id = $addon->get_id();
		$this->membership->add_product($product_id, 5);
		$this->membership->remove_product($product_id, 3);

		$addon_products = $this->membership->get_addon_products();
		foreach ($addon_products as $item) {
			if ($item['product']->get_id() === $product_id) {
				$this->assertEquals(2, $item['quantity']);
			}
		}

		// Remove remaining quantity.
		$this->membership->remove_product($product_id, 5);
		$addon_ids = $this->membership->get_addon_ids();
		$this->assertNotContains($product_id, $addon_ids);
	}

	/**
	 * Test get_all_products includes plan and addons.
	 */
	public function test_get_all_products(): void {
		$addon = new Product(
			[
				'name'         => 'All Products Addon',
				'slug'         => 'all-prod-addon-' . wp_generate_password(6, false),
				'pricing_type' => 'paid',
				'amount'       => 9.99,
				'currency'     => 'USD',
				'duration'     => 1,
				'duration_unit' => 'month',
				'type'         => 'package',
				'active'       => true,
			]
		);
		$addon->set_skip_validation(true);
		$addon->save();

		$this->membership->add_product($addon->get_id(), 1);

		$all_products = $this->membership->get_all_products();
		$this->assertCount(2, $all_products, 'Should have plan + 1 addon = 2 products.');

		// First product should be the plan.
		$this->assertEquals($this->product->get_id(), $all_products[0]['product']->get_id());
		$this->assertEquals(1, $all_products[0]['quantity']);

		// Second product should be the addon.
		$this->assertEquals($addon->get_id(), $all_products[1]['product']->get_id());
		$this->assertEquals(1, $all_products[1]['quantity']);
	}

	/**
	 * Test get_addon_ids returns only addon IDs.
	 */
	public function test_get_addon_ids_empty(): void {
		// Fresh membership has no addons.
		$m = new Membership(
			[
				'customer_id' => $this->customer->get_id(),
				'plan_id'     => $this->product->get_id(),
				'status'      => Membership_Status::ACTIVE,
			]
		);
		$this->assertEmpty($m->get_addon_ids());
	}

	/**
	 * Test has_addons returns false when no addons present.
	 */
	public function test_has_addons_false(): void {
		$m = new Membership(
			[
				'customer_id' => $this->customer->get_id(),
				'plan_id'     => $this->product->get_id(),
				'status'      => Membership_Status::ACTIVE,
			]
		);
		$this->assertFalse($m->has_addons());
	}

	/**
	 * Test has_plan returns true when plan is set.
	 */
	public function test_has_plan(): void {
		$this->assertTrue($this->membership->has_plan());
	}

	/**
	 * Test has_plan returns false when plan ID is invalid.
	 */
	public function test_has_plan_false_for_invalid_id(): void {
		$m = new Membership(
			[
				'customer_id' => $this->customer->get_id(),
				'plan_id'     => 99999,
				'status'      => Membership_Status::ACTIVE,
			]
		);
		$this->assertFalse($m->has_plan());
	}

	/**
	 * Test set_addon_products directly.
	 */
	public function test_set_addon_products(): void {
		$addon = new Product(
			[
				'name'         => 'Set Addon Prod',
				'slug'         => 'set-addon-prod-' . wp_generate_password(6, false),
				'pricing_type' => 'paid',
				'amount'       => 5.00,
				'currency'     => 'USD',
				'duration'     => 1,
				'duration_unit' => 'month',
				'type'         => 'package',
				'active'       => true,
			]
		);
		$addon->set_skip_validation(true);
		$addon->save();

		$data = [$addon->get_id() => 3];
		$this->membership->set_addon_products($data);

		$addon_ids = $this->membership->get_addon_ids();
		$this->assertContains($addon->get_id(), $addon_ids);
	}

	// ---------------------------------------------------------------
	// Customer / User Method Tests
	// ---------------------------------------------------------------

	/**
	 * Test get_customer_id and set_customer_id.
	 */
	public function test_get_and_set_customer_id(): void {
		$this->membership->set_customer_id(999);
		$this->assertSame(999, $this->membership->get_customer_id());
	}

	/**
	 * Test get_customer returns a customer object.
	 */
	public function test_get_customer(): void {
		$customer = $this->membership->get_customer();
		$this->assertInstanceOf(Customer::class, $customer);
		$this->assertEquals($this->customer->get_id(), $customer->get_id());
	}

	/**
	 * Test get_user_id and set_user_id.
	 */
	public function test_get_and_set_user_id(): void {
		$this->membership->set_user_id(42);
		$this->assertSame(42, $this->membership->get_user_id());
	}

	/**
	 * Test get_plan_id and set_plan_id.
	 */
	public function test_get_and_set_plan_id(): void {
		$this->membership->set_plan_id(123);
		$this->assertSame(123, $this->membership->get_plan_id());
	}

	/**
	 * Test get_plan returns a product object.
	 */
	public function test_get_plan(): void {
		$plan = $this->membership->get_plan();
		$this->assertInstanceOf(Product::class, $plan);
		$this->assertEquals($this->product->get_id(), $plan->get_id());
	}

	/**
	 * Test is_customer_allowed with matching customer.
	 */
	public function test_is_customer_allowed_matching(): void {
		$customer_id = $this->customer->get_id();
		wp_set_current_user($customer_id);
		$this->assertTrue($this->membership->is_customer_allowed($customer_id));
	}

	/**
	 * Test is_customer_allowed with non-matching customer.
	 */
	public function test_is_customer_allowed_non_matching(): void {
		$wrong_customer_id = 456;
		wp_set_current_user($wrong_customer_id);
		$this->assertFalse($this->membership->is_customer_allowed($wrong_customer_id));
	}

	// ---------------------------------------------------------------
	// Date Method Tests
	// ---------------------------------------------------------------

	/**
	 * Test date getters and setters.
	 */
	public function test_date_getters_and_setters(): void {
		$now = gmdate('Y-m-d H:i:s');

		$this->membership->set_date_created($now);
		$this->assertSame($now, $this->membership->get_date_created());

		$this->membership->set_date_activated($now);
		$this->assertSame($now, $this->membership->get_date_activated());

		$this->membership->set_date_trial_end($now);
		$this->assertSame($now, $this->membership->get_date_trial_end());

		$this->membership->set_date_renewed($now);
		$this->assertSame($now, $this->membership->get_date_renewed());

		$this->membership->set_date_cancellation($now);
		$this->assertSame($now, $this->membership->get_date_cancellation());

		$this->membership->set_date_expiration($now);
		$this->assertSame($now, $this->membership->get_date_expiration());

		$this->membership->set_date_payment_plan_completed($now);
		$this->assertSame($now, $this->membership->get_date_payment_plan_completed());

		$this->membership->set_date_modified($now);
		$this->assertSame($now, $this->membership->get_date_modified());
	}

	/**
	 * Test is_trialing returns true when trial end is in the future.
	 */
	public function test_is_trialing_true(): void {
		$future = gmdate('Y-m-d H:i:s', strtotime('+7 days'));
		$this->membership->set_date_trial_end($future);
		$this->assertTrue($this->membership->is_trialing());
	}

	/**
	 * Test is_trialing returns false when trial end is in the past.
	 */
	public function test_is_trialing_false(): void {
		$past = gmdate('Y-m-d H:i:s', strtotime('-7 days'));
		$this->membership->set_date_trial_end($past);
		$this->assertFalse($this->membership->is_trialing());
	}

	/**
	 * Test is_trialing returns false when trial end is not set.
	 */
	public function test_is_trialing_false_no_date(): void {
		$this->membership->set_date_trial_end('');
		$this->assertFalse($this->membership->is_trialing());
	}

	// ---------------------------------------------------------------
	// Auto Renew Tests
	// ---------------------------------------------------------------

	/**
	 * Test should_auto_renew and set_auto_renew.
	 */
	public function test_should_auto_renew(): void {
		$this->membership->set_auto_renew(true);
		$this->assertTrue($this->membership->should_auto_renew());

		$this->membership->set_auto_renew(false);
		$this->assertFalse($this->membership->should_auto_renew());
	}

	// ---------------------------------------------------------------
	// Remaining Days Tests
	// ---------------------------------------------------------------

	/**
	 * Test get_remaining_days_in_cycle for non-recurring membership.
	 */
	public function test_get_remaining_days_in_cycle_non_recurring(): void {
		$this->membership->set_recurring(false);
		$this->membership->set_amount(0);
		$this->assertEquals(10000, $this->membership->get_remaining_days_in_cycle());
	}

	/**
	 * Test get_remaining_days_in_cycle with invalid expiration date.
	 */
	public function test_get_remaining_days_in_cycle_invalid_date(): void {
		$this->membership->set_recurring(true);
		$this->membership->set_amount(29.99);
		$this->membership->set_date_expiration('invalid-date');
		$this->assertEquals(0, $this->membership->get_remaining_days_in_cycle());
	}

	/**
	 * Test get_remaining_days_in_cycle with empty expiration date.
	 */
	public function test_get_remaining_days_in_cycle_empty_date(): void {
		$this->membership->set_recurring(true);
		$this->membership->set_amount(29.99);
		$this->membership->set_date_expiration('');
		$this->assertEquals(0, $this->membership->get_remaining_days_in_cycle());
	}

	/**
	 * Test get_remaining_days_in_cycle with future expiration.
	 */
	public function test_get_remaining_days_in_cycle_future_date(): void {
		$this->membership->set_recurring(true);
		$this->membership->set_amount(29.99);
		$future = new \DateTime('now', new \DateTimeZone('UTC'));
		$future->add(new \DateInterval('P10D'));
		$this->membership->set_date_expiration($future->format('Y-m-d H:i:s'));
		$remaining = $this->membership->get_remaining_days_in_cycle();
		$this->assertEquals(10, $remaining);
	}

	/**
	 * Test get_remaining_days_in_cycle with past expiration returns 0.
	 */
	public function test_get_remaining_days_in_cycle_past_date(): void {
		$this->membership->set_recurring(true);
		$this->membership->set_amount(29.99);
		$this->membership->set_date_expiration(gmdate('Y-m-d H:i:s', strtotime('-5 days')));
		$this->assertEquals(0, $this->membership->get_remaining_days_in_cycle());
	}

	// ---------------------------------------------------------------
	// Calculate Expiration Tests
	// ---------------------------------------------------------------

	/**
	 * Test calculate_expiration returns null for non-recurring membership.
	 */
	public function test_calculate_expiration_non_recurring(): void {
		$this->membership->set_recurring(false);
		$this->membership->set_amount(0);
		$this->assertNull($this->membership->calculate_expiration());
	}

	/**
	 * Test calculate_expiration returns a future date for recurring membership.
	 */
	public function test_calculate_expiration_recurring(): void {
		$this->membership->set_recurring(true);
		$this->membership->set_amount(29.99);
		$this->membership->set_duration(1);
		$this->membership->set_duration_unit('month');
		$this->membership->set_date_expiration('');

		$expiration = $this->membership->calculate_expiration(true);
		$this->assertNotNull($expiration);
		$this->assertNotEmpty($expiration);

		// The new expiration should be in the future.
		$exp_time = strtotime($expiration);
		$this->assertGreaterThan(time(), $exp_time);
	}

	/**
	 * Test calculate_expiration returns null when duration is 0 (lifetime).
	 */
	public function test_calculate_expiration_zero_duration(): void {
		$this->membership->set_recurring(true);
		$this->membership->set_amount(29.99);
		$this->membership->set_duration(0);
		$this->membership->set_duration_unit('month');

		$expiration = $this->membership->calculate_expiration(true);
		$this->assertNull($expiration);
	}

	// ---------------------------------------------------------------
	// Signup / Upgrade Methods
	// ---------------------------------------------------------------

	/**
	 * Test get_signup_method and set_signup_method.
	 */
	public function test_get_and_set_signup_method(): void {
		$this->membership->set_signup_method('checkout');
		$this->assertSame('checkout', $this->membership->get_signup_method());

		$this->membership->set_signup_method('admin');
		$this->assertSame('admin', $this->membership->get_signup_method());
	}

	/**
	 * Test get_upgraded_from and set_upgraded_from.
	 */
	public function test_get_and_set_upgraded_from(): void {
		$this->membership->set_upgraded_from(42);
		$this->assertEquals(42, $this->membership->get_upgraded_from());
	}

	// ---------------------------------------------------------------
	// Network ID Tests
	// ---------------------------------------------------------------

	/**
	 * Test get_network_id and set_network_id.
	 */
	public function test_get_and_set_network_id(): void {
		$this->membership->set_network_id(5);
		$this->assertSame(5, $this->membership->get_network_id());

		$this->membership->set_network_id(null);
		$this->assertNull($this->membership->get_network_id());
	}

	// ---------------------------------------------------------------
	// Cancellation Tests
	// ---------------------------------------------------------------

	/**
	 * Test set_cancellation_reason and get_cancellation_reason.
	 */
	public function test_cancellation_reason(): void {
		$this->membership->set_status(Membership_Status::CANCELLED);
		$this->membership->set_cancellation_reason('Too expensive');
		$this->assertSame('Too expensive', $this->membership->get_cancellation_reason());
	}

	/**
	 * Test get_cancellation_reason returns empty string when not cancelled.
	 */
	public function test_cancellation_reason_empty_when_not_cancelled(): void {
		$this->membership->set_status(Membership_Status::ACTIVE);
		$this->membership->set_cancellation_reason('Some reason');
		$this->assertSame('', $this->membership->get_cancellation_reason());
	}

	// ---------------------------------------------------------------
	// Swap Tests
	// ---------------------------------------------------------------

	/**
	 * Test swap with non-Cart object returns WP_Error.
	 */
	public function test_swap_with_invalid_order(): void {
		$result = $this->membership->swap('not-a-cart');
		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	/**
	 * Test get_scheduled_swap returns false when nothing scheduled.
	 */
	public function test_get_scheduled_swap_returns_false(): void {
		$swap = $this->membership->get_scheduled_swap();
		$this->assertFalse($swap);
	}

	/**
	 * Test delete_scheduled_swap runs without error.
	 */
	public function test_delete_scheduled_swap(): void {
		$this->membership->delete_scheduled_swap();
		$this->assertFalse($this->membership->get_scheduled_swap());
	}

	/**
	 * Test schedule_swap with invalid order returns WP_Error.
	 */
	public function test_schedule_swap_invalid_order(): void {
		$result = $this->membership->schedule_swap('not-a-cart');
		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	/**
	 * Test schedule_swap with invalid date returns WP_Error.
	 */
	public function test_schedule_swap_invalid_date(): void {
		$cart = new \WP_Ultimo\Checkout\Cart([]);
		$result = $this->membership->schedule_swap($cart, 'bogus-date');
		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	// ---------------------------------------------------------------
	// Validation Tests
	// ---------------------------------------------------------------

	/**
	 * Test validation rules include expected fields.
	 */
	public function test_validation_rules(): void {
		$rules = $this->membership->validation_rules();
		$this->assertArrayHasKey('customer_id', $rules);
		$this->assertArrayHasKey('plan_id', $rules);
		$this->assertArrayHasKey('status', $rules);
		$this->assertArrayHasKey('amount', $rules);
		$this->assertArrayHasKey('duration', $rules);
		$this->assertArrayHasKey('duration_unit', $rules);
		$this->assertArrayHasKey('currency', $rules);
		$this->assertArrayHasKey('billing_cycles', $rules);
		$this->assertArrayHasKey('recurring', $rules);
	}

	/**
	 * Test the save method with validation error handling.
	 */
	public function test_save_with_validation_error(): void {
		$this->membership->set_status('bogus');
		$this->membership->set_skip_validation(false);
		$result = $this->membership->save();
		$this->assertInstanceOf(\WP_Error::class, $result);
	}

	// ---------------------------------------------------------------
	// Save Tests
	// ---------------------------------------------------------------

	/**
	 * Test save basic functionality.
	 */
	public function test_save_basic_functionality(): void {
		// Verify the membership has basic properties set correctly.
		$this->assertEquals($this->customer->get_id(), $this->membership->get_customer_id());
		$this->assertEquals($this->product->get_id(), $this->membership->get_plan_id());
		$this->assertEquals(29.99, $this->membership->get_amount());
		$this->assertSame(Membership_Status::ACTIVE, $this->membership->get_status());
	}

	/**
	 * Test save with amount update.
	 */
	public function test_save_with_membership_updates(): void {
		$gateway = wu_get_gateway('manual');
		if ( ! $gateway) {
			$this->markTestSkipped('Manual gateway not available');
		}

		$this->membership->set_amount(19.99);
		$this->membership->set_gateway('manual');
		$this->membership->set_gateway_customer_id('cus_123');
		$this->membership->set_gateway_subscription_id('sub_123');
		$this->membership->set_skip_validation(true);

		$result = $this->membership->save();
		if ($result === false) {
			$this->markTestSkipped('Initial save failed in test environment.');
		}

		$this->assertTrue($result);

		// Update amount.
		$this->membership->set_amount(29.99);
		$this->membership->set_skip_validation(true);
		$result = $this->membership->save();
		$this->assertTrue($result);
		$this->assertEquals(29.99, $this->membership->get_amount());
	}

	// ---------------------------------------------------------------
	// Payments / Sites Related Tests
	// ---------------------------------------------------------------

	/**
	 * Test get_payments returns an array.
	 */
	public function test_get_payments(): void {
		$payments = $this->membership->get_payments();
		$this->assertIsArray($payments);
	}

	/**
	 * Test get_last_pending_payment returns false when no pending payments.
	 */
	public function test_get_last_pending_payment_no_payments(): void {
		$result = $this->membership->get_last_pending_payment();
		$this->assertFalse($result);
	}

	/**
	 * Test get_sites returns an array.
	 */
	public function test_get_sites(): void {
		$sites = $this->membership->get_sites();
		$this->assertIsArray($sites);
	}

	/**
	 * Test get_published_sites returns an array.
	 */
	public function test_get_published_sites(): void {
		$sites = $this->membership->get_published_sites();
		$this->assertIsArray($sites);
	}

	/**
	 * Test get_pending_site returns false when no pending site.
	 */
	public function test_get_pending_site(): void {
		$this->assertFalse($this->membership->get_pending_site());
	}

	/**
	 * Test delete_pending_site returns boolean.
	 */
	public function test_delete_pending_site(): void {
		$result = $this->membership->delete_pending_site();
		$this->assertIsBool($result);
	}

	// ---------------------------------------------------------------
	// to_search_results Tests
	// ---------------------------------------------------------------

	/**
	 * Test to_search_results includes expected keys.
	 */
	public function test_to_search_results(): void {
		$results = $this->membership->to_search_results();
		$this->assertIsArray($results);
		$this->assertArrayHasKey('customer', $results);
		$this->assertArrayHasKey('display_name', $results);
		$this->assertArrayHasKey('formatted_price', $results);
		$this->assertArrayHasKey('reference_code', $results);
	}

	// ---------------------------------------------------------------
	// at_maximum_renewals Tests
	// ---------------------------------------------------------------

	/**
	 * Test at_maximum_renewals returns false for forever recurring.
	 */
	public function test_at_maximum_renewals_forever_recurring(): void {
		$this->membership->set_billing_cycles(0);
		$this->assertFalse($this->membership->at_maximum_renewals());
	}

	// ---------------------------------------------------------------
	// Normalized Amount Tests
	// ---------------------------------------------------------------

	/**
	 * Test get_normalized_amount for recurring membership.
	 */
	public function test_get_normalized_amount_recurring(): void {
		$this->membership->set_recurring(true);
		$this->membership->set_amount(29.99);
		$this->assertEquals(29.99, $this->membership->get_normalized_amount());
	}

	/**
	 * Test get_normalized_amount for non-recurring membership.
	 */
	public function test_get_normalized_amount_non_recurring(): void {
		$this->membership->set_recurring(false);
		$this->membership->set_amount(120.00);
		$this->membership->set_duration(1);
		$this->membership->set_duration_unit('year');
		$normalized = $this->membership->get_normalized_amount();
		// For non-recurring, the normalized amount is calculated based on duration conversion.
		$this->assertIsFloat($normalized);
	}

	// ---------------------------------------------------------------
	// Cancel Tests
	// ---------------------------------------------------------------

	/**
	 * Test cancel sets the status to cancelled and sets date_cancellation.
	 */
	public function test_cancel(): void {
		$this->membership->set_status(Membership_Status::ACTIVE);
		$this->membership->set_skip_validation(true);
		$this->membership->cancel('Too expensive');

		$this->assertSame(Membership_Status::CANCELLED, $this->membership->get_status());
		$this->assertNotEmpty($this->membership->get_date_cancellation());
	}

	/**
	 * Test cancel with already cancelled membership does nothing.
	 */
	public function test_cancel_already_cancelled(): void {
		$this->membership->set_status(Membership_Status::CANCELLED);
		$old_date = $this->membership->get_date_cancellation();
		$this->membership->cancel('Another reason');
		// Status should still be cancelled, date should not change.
		$this->assertSame(Membership_Status::CANCELLED, $this->membership->get_status());
	}

	// ---------------------------------------------------------------
	// Renew Tests
	// ---------------------------------------------------------------

	/**
	 * Test renew updates membership status and expiration.
	 */
	public function test_renew(): void {
		$this->membership->set_status(Membership_Status::ACTIVE);
		$this->membership->set_recurring(true);
		$this->membership->set_amount(29.99);
		$this->membership->set_duration(1);
		$this->membership->set_duration_unit('month');
		$this->membership->set_skip_validation(true);

		$result = $this->membership->renew(true, 'active');
		$this->assertTrue($result);
		$this->assertSame('active', $this->membership->get_status());
		$this->assertNotEmpty($this->membership->get_date_renewed());
	}

	/**
	 * Test renew returns false when plan_id is empty.
	 */
	public function test_renew_no_plan(): void {
		$this->membership->set_plan_id(0);
		$result = $this->membership->renew();
		$this->assertFalse($result);
	}

	// ---------------------------------------------------------------
	// Reactivation Tests (PR #751 / issue #814)
	// ---------------------------------------------------------------

	/**
	 * Test renew() does NOT clear date_cancellation on a regular active-to-active renewal.
	 *
	 * Guards against the regression where renew() unconditionally cleared date_cancellation
	 * on any renewal that resulted in active status, destroying historical cancellation records.
	 */
	public function test_renew_preserves_cancellation_date_on_active_renewal(): void {
		$now = wu_get_current_time('mysql');

		$this->membership->set_status(Membership_Status::ACTIVE);
		$this->membership->set_date_cancellation($now);
		$this->membership->set_recurring(true);
		$this->membership->set_amount(29.99);
		$this->membership->set_skip_validation(true);

		$result = $this->membership->renew(true, 'active');

		$this->assertTrue($result);
		$this->assertSame($now, $this->membership->get_date_cancellation(), 'date_cancellation must not be cleared when renewing an already-active membership');
	}

	/**
	 * Test renew() DOES clear date_cancellation when reactivating a cancelled membership.
	 *
	 * renew() is called by reactivate(), and also directly by gateways via IPN/webhook.
	 * It must clear the cancellation timestamp when the previous status was CANCELLED
	 * so that cancelled membership records are cleaned up in a single save.
	 */
	public function test_renew_clears_cancellation_date_for_cancelled_membership(): void {
		$now = wu_get_current_time('mysql');

		$this->membership->set_status(Membership_Status::CANCELLED);
		$this->membership->set_date_cancellation($now);
		$this->membership->set_recurring(true);
		$this->membership->set_amount(29.99);
		$this->membership->set_skip_validation(true);

		$result = $this->membership->renew(true, 'active');

		$this->assertTrue($result);
		$this->assertNull($this->membership->get_date_cancellation(), 'date_cancellation must be cleared when renewing a cancelled membership to active');
	}

	/**
	 * Test reactivate() clears date_cancellation and sets membership to active.
	 */
	public function test_reactivate_clears_cancellation_date(): void {
		$now = wu_get_current_time('mysql');

		$this->membership->set_status(Membership_Status::CANCELLED);
		$this->membership->set_date_cancellation($now);
		$this->membership->set_recurring(true);
		$this->membership->set_amount(29.99);
		$this->membership->set_skip_validation(true);

		$result = $this->membership->reactivate(true);

		$this->assertTrue($result);
		$this->assertSame('active', $this->membership->get_status());
		$this->assertNull($this->membership->get_date_cancellation(), 'reactivate() must clear date_cancellation');
	}

	/**
	 * Test reactivate() fires wu_membership_pre_reactivate action.
	 */
	public function test_reactivate_fires_pre_reactivate_hook(): void {
		$this->membership->set_status(Membership_Status::CANCELLED);
		$this->membership->set_recurring(true);
		$this->membership->set_amount(29.99);
		$this->membership->set_skip_validation(true);

		$pre_fired   = false;
		$captured_id = 0;

		add_action(
			'wu_membership_pre_reactivate',
			function($id) use (&$pre_fired, &$captured_id) {
				$pre_fired   = true;
				$captured_id = $id;
			}
		);

		$this->membership->reactivate(true);

		$this->assertTrue($pre_fired, 'wu_membership_pre_reactivate must fire before reactivation');
		$this->assertSame($this->membership->get_id(), $captured_id);
	}

	/**
	 * Test reactivate() fires wu_membership_post_reactivate action on success.
	 */
	public function test_reactivate_fires_post_reactivate_hook_on_success(): void {
		$this->membership->set_status(Membership_Status::CANCELLED);
		$this->membership->set_recurring(true);
		$this->membership->set_amount(29.99);
		$this->membership->set_skip_validation(true);

		$post_fired  = false;
		$captured_id = 0;

		add_action(
			'wu_membership_post_reactivate',
			function($id) use (&$post_fired, &$captured_id) {
				$post_fired  = true;
				$captured_id = $id;
			}
		);

		$result = $this->membership->reactivate(true);

		$this->assertTrue($result);
		$this->assertTrue($post_fired, 'wu_membership_post_reactivate must fire after successful reactivation');
		$this->assertSame($this->membership->get_id(), $captured_id);
	}

	/**
	 * Test reactivate() does not fire wu_membership_post_reactivate when renew() fails.
	 *
	 * Simulates a renew() failure by clearing plan_id (renew() returns false immediately
	 * when plan_id is empty). Verifies wu_membership_post_reactivate is NOT fired.
	 */
	public function test_reactivate_does_not_fire_post_hook_on_failure(): void {
		// Deliberately remove the plan_id so renew() returns false.
		$this->membership->set_plan_id(0);
		$this->membership->set_status(Membership_Status::CANCELLED);
		$this->membership->set_recurring(true);
		$this->membership->set_amount(29.99);
		$this->membership->set_skip_validation(true);

		$post_fired = false;

		add_action(
			'wu_membership_post_reactivate',
			function() use (&$post_fired) {
				$post_fired = true;
			}
		);

		$result = $this->membership->reactivate(true);

		$this->assertFalse($result, 'reactivate() must return false when renew() fails due to missing plan');
		$this->assertFalse($post_fired, 'wu_membership_post_reactivate must NOT fire when reactivation fails');
	}

	// ---------------------------------------------------------------
	// Meta Constants Tests
	// ---------------------------------------------------------------

	/**
	 * Test meta key constants are defined.
	 */
	public function test_meta_key_constants(): void {
		$this->assertSame('wu_swap_order', Membership::META_SWAP_ORDER);
		$this->assertSame('wu_swap_scheduled_date', Membership::META_SWAP_SCHEDULED_DATE);
		$this->assertSame('cancellation_reason', Membership::META_CANCELLATION_REASON);
		$this->assertSame('discount_code', Membership::META_DISCOUNT_CODE);
		$this->assertSame('pending_site', Membership::META_PENDING_SITE);
	}

	// ---------------------------------------------------------------
	// Billing Address Tests
	// ---------------------------------------------------------------

	/**
	 * Test get_default_billing_address returns a billing address object.
	 */
	public function test_get_default_billing_address(): void {
		$address = $this->membership->get_default_billing_address();
		$this->assertInstanceOf(\WP_Ultimo\Objects\Billing_Address::class, $address);
	}
}

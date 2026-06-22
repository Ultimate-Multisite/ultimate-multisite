<?php
/**
 * Tests for Cart addon pricing functionality.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 * @since 2.0.12
 */

namespace WP_Ultimo\Checkout;

use WP_Ultimo\Models\Customer;
use WP_Ultimo\Models\Membership;
use WP_Ultimo\Models\Product;
use WP_Ultimo\Database\Memberships\Membership_Status;
use WP_UnitTestCase;

/**
 * Test class for Cart addon pricing functionality.
 *
 * Tests the fix for the bug where adding addon services to an existing membership
 * was incorrectly charging for the next billing period in advance.
 *
 * @group cart
 * @group checkout
 * @group addon-pricing
 */
class Cart_Addon_Pricing_Test extends WP_UnitTestCase {

	/**
	 * Test customer.
	 *
	 * @var Customer
	 */
	private static Customer $customer;

	/**
	 * Test plan product.
	 *
	 * @var Product
	 */
	private static Product $plan;

	/**
	 * Test addon product.
	 *
	 * @var Product
	 */
	private static Product $addon;

	/**
	 * Test membership.
	 *
	 * @var Membership
	 */
	private static Membership $membership;

	/**
	 * Set up test fixtures before running tests.
	 *
	 * @since 2.0.12
	 * @return void
	 */
	public static function set_up_before_class() {
		parent::set_up_before_class();

		$suffix = wp_rand(100000, 999999);

		// Create a test customer.
		$customer = wu_create_customer(
			array(
				'username' => 'testuser_addon_pricing_' . $suffix,
				'email'    => 'addon_pricing_' . $suffix . '@example.com',
				'password' => 'password123',
			)
		);

		if ( is_wp_error($customer) ) {
			self::fail('Failed to create test customer: ' . $customer->get_error_message());
		}

		self::$customer = $customer;

		// Create a plan product (€90/month).
		$plan = wu_create_product(
			array(
				'name'          => 'Test Plan',
				'slug'          => 'test-plan-addon-pricing-' . $suffix,
				'amount'        => 90.00,
				'duration'      => 1,
				'duration_unit' => 'month',
				'type'          => 'plan',
				'pricing_type'  => 'paid',
				'currency'      => 'EUR',
				'recurring'     => true,
				'setup_fee'     => 0,
				'active'        => true,
			)
		);

		if ( is_wp_error($plan) ) {
			self::fail('Failed to create test plan: ' . $plan->get_error_message());
		}

		self::$plan = $plan;

		// Create an addon product (€5).
		$addon = wu_create_product(
			array(
				'name'          => 'Test Addon',
				'slug'          => 'test-addon-service-' . $suffix,
				'amount'        => 5.00,
				'duration'      => 1,
				'duration_unit' => 'month',
				'type'          => 'service',
				'pricing_type'  => 'paid',
				'currency'      => 'EUR',
				'recurring'     => true,
				'setup_fee'     => 0,
				'active'        => true,
			)
		);

		if ( is_wp_error($addon) ) {
			self::fail('Failed to create test addon: ' . $addon->get_error_message());
		}

		self::$addon = $addon;

		// Create an active membership for the customer.
		$membership = wu_create_membership(
			array(
				'customer_id'     => self::$customer->get_id(),
				'plan_id'         => self::$plan->get_id(),
				'amount'          => 90.00,
				'currency'        => 'EUR',
				'duration'        => 1,
				'duration_unit'   => 'month',
				'recurring'       => true,
				'status'          => Membership_Status::ACTIVE,
				'times_billed'    => 1,
				'date_created'    => wu_date()->modify('-15 days')->format('Y-m-d H:i:s'),
				'date_renewed'    => wu_date()->modify('-15 days')->format('Y-m-d H:i:s'),
				'date_expiration' => wu_date()->modify('+15 days')->format('Y-m-d H:i:s'),
			)
		);

		if ( is_wp_error($membership) ) {
			self::fail('Failed to create test membership: ' . $membership->get_error_message());
		}

		self::$membership = $membership;
	}

	/**
	 * Set up before each test: authenticate as the test customer.
	 *
	 * The Cart class uses wu_get_current_customer() which calls get_current_user_id().
	 * Without setting the current user, the permission check in build_from_membership()
	 * fails and the cart type stays 'upgrade' instead of being set to 'addon'.
	 *
	 * @since 2.0.12
	 * @return void
	 */
	public function set_up() {
		parent::set_up();
		wp_set_current_user(self::$customer->get_user_id());
	}

	/**
	 * Tear down after each test: reset the current user.
	 *
	 * @since 2.0.12
	 * @return void
	 */
	public function tear_down() {
		wp_set_current_user(0);
		parent::tear_down();
	}

	/**
	 * Test that addon purchases only charge for the addon, not the existing plan.
	 *
	 * Bug: Previously, adding a €5 addon to a €90/month membership would charge ~€89.59
	 * (€90 plan + €5 addon - small pro-rata credit).
	 *
	 * Expected: Should only charge €5 for the addon.
	 */
	public function test_addon_only_charges_for_addon_product() {
		$cart = new Cart(
			array(
				'customer_id'   => self::$customer->get_id(),
				'membership_id' => self::$membership->get_id(),
				'products'      => array(self::$addon->get_id()),
			)
		);

		// The cart should be type 'addon'.
		$this->assertEquals('addon', $cart->get_cart_type(), 'Cart type should be "addon"');

		// The cart should NOT include the existing plan.
		$line_items         = $cart->get_line_items();
		$product_line_items = array_filter(
			$line_items,
			function ($item) {
				return $item->get_type() === 'product';
			}
		);

		// Should only have 1 product line item (the addon).
		$this->assertCount(1, $product_line_items, 'Should only have 1 product line item (the addon)');

		// Verify it's the addon, not the plan.
		$addon_line_item = reset($product_line_items);
		$this->assertEquals(self::$addon->get_id(), $addon_line_item->get_product_id(), 'Product should be the addon');

		// The subtotal should be €5.00 (addon price only).
		$this->assertEquals(5.00, $cart->get_subtotal(), 'Subtotal should be €5.00 (addon price only)');

		// There should be NO pro-rata credit line items.
		$credit_line_items = array_filter(
			$line_items,
			function ($item) {
				return $item->get_type() === 'credit';
			}
		);
		$this->assertCount(0, $credit_line_items, 'Should have NO pro-rata credit for addon-only purchases');

		// Total should be €5.00 (no taxes in this test).
		$this->assertEquals(5.00, $cart->get_total(), 'Total should be €5.00');
	}

	/**
	 * Test that existing discount codes are applied to addon purchases.
	 *
	 * Bug: Previously, discount codes from the membership were not being applied
	 * to addon purchases.
	 *
	 * Expected: The membership's discount code (10% off) should be applied to the addon.
	 */
	public function test_addon_applies_existing_discount_code() {
		$discount_code_value = 'TEST10' . wp_rand(100000, 999999);
		$discount_code       = wu_create_discount_code(
			array(
				'name'              => 'Test Discount',
				'code'              => $discount_code_value,
				'value'             => 10,
				'type'              => 'percentage',
				'uses'              => 0,
				'max_uses'          => 100,
				'apply_to_renewals' => true,
			)
		);

		$this->assertNotWPError($discount_code);

		self::$membership->set_discount_code($discount_code);
		$saved = self::$membership->save();

		$this->assertNotWPError($saved);

		$cart = new Cart(
			array(
				'customer_id'   => self::$customer->get_id(),
				'membership_id' => self::$membership->get_id(),
				'products'      => array(self::$addon->get_id()),
			)
		);

		// The cart should have the discount code from the membership.
		$discount_code = $cart->get_discount_code();
		$this->assertNotNull($discount_code, 'Discount code should be applied');
		$this->assertEquals($discount_code_value, $discount_code->get_code(), 'Should be the membership discount code');

		// The addon should have a discount applied (10% off €5 = €0.50).
		$line_items      = $cart->get_line_items();
		$addon_line_item = null;
		foreach ( $line_items as $item ) {
			if ( $item->get_type() === 'product' && $item->get_product_id() === self::$addon->get_id() ) {
				$addon_line_item = $item;
				break;
			}
		}

		$this->assertNotNull($addon_line_item, 'Addon line item should exist');
		$this->assertEquals(0.50, $addon_line_item->get_discount_total(), 'Discount should be €0.50 (10% of €5)');
		$this->assertEquals(4.50, $addon_line_item->get_total(), 'Addon total should be €4.50 after discount');

		self::$membership->delete_meta(Membership::META_DISCOUNT_CODE);
		self::$membership->delete_meta(Membership::META_VERIFIED_PAYMENT_DISCOUNT);
	}

	/**
	 * Test that the filter 'wu_cart_addon_include_existing_plan' can override the default behavior.
	 *
	 * The filter defaults to false (don't include plan), but sites can set it to true
	 * if they need the old behavior for specific use cases.
	 */
	public function test_addon_filter_can_include_existing_plan() {
		// Add filter to force inclusion of existing plan.
		add_filter('wu_cart_addon_include_existing_plan', '__return_true');

		try {
			$cart = new Cart(
				array(
					'customer_id'   => self::$customer->get_id(),
					'membership_id' => self::$membership->get_id(),
					'products'      => array(self::$addon->get_id()),
				)
			);

			// Should have 2 product line items (plan + addon).
			$line_items         = $cart->get_line_items();
			$product_line_items = array_filter(
				$line_items,
				function ($item) {
					return $item->get_type() === 'product';
				}
			);

			$this->assertCount(2, $product_line_items, 'Should have 2 product line items when filter returns true');

			// Should have a pro-rata credit line item.
			$credit_line_items = array_filter(
				$line_items,
				function ($item) {
					return $item->get_type() === 'credit';
				}
			);
			$this->assertGreaterThan(0, count($credit_line_items), 'Should have pro-rata credit when plan is included');
		} finally {
			// Remove filter - always cleanup even if assertions fail.
			remove_filter('wu_cart_addon_include_existing_plan', '__return_true');
		}
	}

	/**
	 * Test that plan upgrades still use pro-rata correctly.
	 *
	 * When changing plans (upgrade/downgrade), pro-rata SHOULD still be applied.
	 * The fix should only affect addon-only purchases.
	 */
	public function test_plan_upgrade_still_uses_prorate() {
		// Create a higher-tier plan.
		$upgraded_plan = wu_create_product(
			array(
				'name'          => 'Premium Plan',
				'slug'          => 'premium-plan-addon-test-' . wp_rand(100000, 999999),
				'amount'        => 150.00,
				'duration'      => 1,
				'duration_unit' => 'month',
				'type'          => 'plan',
				'pricing_type'  => 'paid',
				'currency'      => 'EUR',
				'recurring'     => true,
				'setup_fee'     => 0,
				'active'        => true,
			)
		);

		$this->assertNotWPError($upgraded_plan);

		$cart = new Cart(
			array(
				'customer_id'   => self::$customer->get_id(),
				'membership_id' => self::$membership->get_id(),
				'products'      => array($upgraded_plan->get_id()),
			)
		);

		// Cart type should be 'upgrade' (or 'downgrade').
		$this->assertContains($cart->get_cart_type(), array('upgrade', 'downgrade'), 'Cart type should be upgrade or downgrade');

		// Should have a pro-rata credit for plan changes.
		$line_items        = $cart->get_line_items();
		$credit_line_items = array_filter(
			$line_items,
			function ($item) {
				return $item->get_type() === 'credit';
			}
		);

		$this->assertGreaterThan(0, count($credit_line_items), 'Plan upgrades should have pro-rata credit');

		// Clean up.
		$upgraded_plan->delete();
	}

	/**
	 * Test that setup fees are not re-applied for addon purchases on existing memberships.
	 */
	public function test_addon_does_not_reapply_setup_fees() {
		// Create an addon with a setup fee.
		$addon_with_fee = wu_create_product(
			array(
				'name'          => 'Addon with Fee',
				'slug'          => 'addon-with-setup-fee-' . wp_rand(100000, 999999),
				'amount'        => 10.00,
				'duration'      => 1,
				'duration_unit' => 'month',
				'type'          => 'service',
				'pricing_type'  => 'paid',
				'currency'      => 'EUR',
				'recurring'     => true,
				'setup_fee'     => 20.00,
				'active'        => true,
			)
		);

		$this->assertNotWPError($addon_with_fee);

		$cart = new Cart(
			array(
				'customer_id'   => self::$customer->get_id(),
				'membership_id' => self::$membership->get_id(),
				'products'      => array($addon_with_fee->get_id()),
			)
		);

		// Get all line items.
		$line_items = $cart->get_line_items();

		// Should have setup fee line item for the NEW addon (first time adding it).
		$fee_line_items = array_filter(
			$line_items,
			function ($item) use ($addon_with_fee) {
				return $item->get_type() === 'fee' && $item->get_product_id() === $addon_with_fee->get_id();
			}
		);

		$this->assertCount(1, $fee_line_items, 'Should have 1 setup fee for the new addon');

		$fee_line_item = reset($fee_line_items);
		$this->assertEquals(20.00, $fee_line_item->get_unit_price(), 'Setup fee should be €20');

		// Clean up.
		$addon_with_fee->delete();
	}

	/**
	 * Tear down test fixtures after all tests are complete.
	 *
	 * @since 2.0.12
	 * @return void
	 */
	public static function tear_down_after_class() {
		if ( isset(self::$membership) ) {
			self::$membership->delete();
		}

		if ( isset(self::$addon) ) {
			self::$addon->delete();
		}

		if ( isset(self::$plan) ) {
			self::$plan->delete();
		}

		if ( isset(self::$customer) ) {
			self::$customer->delete();
		}

		parent::tear_down_after_class();
	}
}

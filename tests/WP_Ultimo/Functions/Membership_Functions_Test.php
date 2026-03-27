<?php
/**
 * Tests for membership functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for membership functions.
 */
class Membership_Functions_Test extends WP_UnitTestCase {

	/**
	 * Test wu_get_membership returns false for nonexistent.
	 */
	public function test_get_membership_nonexistent(): void {

		$result = wu_get_membership(999999);

		$this->assertFalse($result);
	}

	/**
	 * Test wu_get_membership_by returns false for nonexistent.
	 */
	public function test_get_membership_by_nonexistent(): void {

		$result = wu_get_membership_by('id', 999999);

		$this->assertFalse($result);
	}

	/**
	 * Test wu_get_membership_by_hash returns false for nonexistent.
	 */
	public function test_get_membership_by_hash_nonexistent(): void {

		$result = wu_get_membership_by_hash('nonexistent_hash');

		$this->assertFalse($result);
	}

	/**
	 * Test wu_get_memberships returns array.
	 */
	public function test_get_memberships_returns_array(): void {

		$result = wu_get_memberships();

		$this->assertIsArray($result);
	}

	/**
	 * Test wu_get_memberships with search query that finds no customers.
	 */
	public function test_get_memberships_with_search_no_customers(): void {

		$result = wu_get_memberships(['search' => 'nonexistent_xyz_abc_123']);

		$this->assertIsArray($result);
	}

	/**
	 * Test wu_get_memberships with search query that finds customers.
	 */
	public function test_get_memberships_with_search_finds_customers(): void {

		$user_id = self::factory()->user->create(['user_email' => 'searchable-member@example.com']);

		wu_create_customer([
			'user_id'         => $user_id,
			'skip_validation' => true,
		]);

		$result = wu_get_memberships(['search' => 'searchable-member@example.com']);

		$this->assertIsArray($result);
	}

	/**
	 * Test wu_create_membership creates a membership.
	 */
	public function test_create_membership(): void {

		$customer = wu_create_customer([
			'user_id'         => self::factory()->user->create(),
			'skip_validation' => true,
		]);

		$product = wu_create_product([
			'name'            => 'Test Plan',
			'slug'            => 'test-plan-membership-' . wp_rand(),
			'type'            => 'plan',
			'amount'          => 29.99,
			'skip_validation' => true,
		]);

		$membership = wu_create_membership([
			'customer_id'     => $customer->get_id(),
			'plan_id'         => $product->get_id(),
			'status'          => 'active',
			'amount'          => 29.99,
			'currency'        => 'USD',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($membership);
		$this->assertInstanceOf(\WP_Ultimo\Models\Membership::class, $membership);
		$this->assertEquals('active', $membership->get_status());
	}

	/**
	 * Test wu_create_membership with non-numeric migrated_from_id defaults to 0.
	 */
	public function test_create_membership_non_numeric_migrated_from_id(): void {

		$customer = wu_create_customer([
			'user_id'         => self::factory()->user->create(),
			'skip_validation' => true,
		]);

		$membership = wu_create_membership([
			'customer_id'      => $customer->get_id(),
			'status'           => 'pending',
			'amount'           => 0,
			'currency'         => 'USD',
			'migrated_from_id' => 'not-a-number',
			'skip_validation'  => true,
		]);

		$this->assertNotWPError($membership);
		$this->assertInstanceOf(\WP_Ultimo\Models\Membership::class, $membership);
	}

	/**
	 * Test wu_get_membership retrieves created membership.
	 */
	public function test_get_membership_retrieves_created(): void {

		$customer = wu_create_customer([
			'user_id'         => self::factory()->user->create(),
			'skip_validation' => true,
		]);

		$membership = wu_create_membership([
			'customer_id'     => $customer->get_id(),
			'status'          => 'active',
			'amount'          => 10.00,
			'currency'        => 'USD',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($membership);

		$retrieved = wu_get_membership($membership->get_id());

		$this->assertNotFalse($retrieved);
		$this->assertEquals($membership->get_id(), $retrieved->get_id());
	}

	/**
	 * Test wu_get_membership_by_hash retrieves created membership.
	 */
	public function test_get_membership_by_hash_retrieves_created(): void {

		$customer = wu_create_customer([
			'user_id'         => self::factory()->user->create(),
			'skip_validation' => true,
		]);

		$membership = wu_create_membership([
			'customer_id'     => $customer->get_id(),
			'status'          => 'active',
			'amount'          => 10.00,
			'currency'        => 'USD',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($membership);

		$hash = $membership->get_hash();

		$retrieved = wu_get_membership_by_hash($hash);

		$this->assertNotFalse($retrieved);
		$this->assertEquals($membership->get_id(), $retrieved->get_id());
	}

	/**
	 * Test wu_get_membership_by_customer_gateway_id returns false when not found.
	 */
	public function test_get_membership_by_customer_gateway_id_not_found(): void {

		$result = wu_get_membership_by_customer_gateway_id('cus_nonexistent', ['stripe']);

		$this->assertFalse($result);
	}

	/**
	 * Test wu_get_membership_by_customer_gateway_id with amount filter returns false.
	 */
	public function test_get_membership_by_customer_gateway_id_with_amount(): void {

		$result = wu_get_membership_by_customer_gateway_id('cus_nonexistent', ['stripe'], 99.99);

		$this->assertFalse($result);
	}

	/**
	 * Test wu_get_membership_by_customer_gateway_id finds matching membership.
	 */
	public function test_get_membership_by_customer_gateway_id_found(): void {

		$customer = wu_create_customer([
			'user_id'         => self::factory()->user->create(),
			'skip_validation' => true,
		]);

		$gateway_customer_id = 'cus_test_' . wp_rand();

		$membership = wu_create_membership([
			'customer_id'         => $customer->get_id(),
			'status'              => 'pending',
			'amount'              => 29.99,
			'initial_amount'      => 29.99,
			'currency'            => 'USD',
			'gateway'             => 'stripe',
			'gateway_customer_id' => $gateway_customer_id,
			'skip_validation'     => true,
		]);

		$this->assertNotWPError($membership);

		$result = wu_get_membership_by_customer_gateway_id($gateway_customer_id, ['stripe']);

		$this->assertNotFalse($result);
		$this->assertInstanceOf(\WP_Ultimo\Models\Membership::class, $result);
		$this->assertEquals($membership->get_id(), $result->get_id());
	}

	/**
	 * Test wu_get_membership_customers returns array for nonexistent product.
	 */
	public function test_get_membership_customers_returns_array(): void {

		$result = wu_get_membership_customers(999999);

		$this->assertIsArray($result);
	}

	/**
	 * Test wu_get_membership_customers with a real product returns customer IDs.
	 */
	public function test_get_membership_customers_with_product(): void {

		$customer = wu_create_customer([
			'user_id'         => self::factory()->user->create(),
			'skip_validation' => true,
		]);

		$product = wu_create_product([
			'name'            => 'Customers Test Plan',
			'slug'            => 'customers-test-plan-' . wp_rand(),
			'type'            => 'plan',
			'amount'          => 19.99,
			'skip_validation' => true,
		]);

		$this->assertNotWPError($product);

		wu_create_membership([
			'customer_id'     => $customer->get_id(),
			'plan_id'         => $product->get_id(),
			'status'          => 'active',
			'amount'          => 19.99,
			'currency'        => 'USD',
			'skip_validation' => true,
		]);

		$result = wu_get_membership_customers($product->get_id());

		$this->assertIsArray($result);
		$this->assertContains($customer->get_id(), $result);
	}

	/**
	 * Test wu_get_membership_update_url returns a string.
	 */
	public function test_get_membership_update_url_returns_string(): void {

		$customer = wu_create_customer([
			'user_id'         => self::factory()->user->create(),
			'skip_validation' => true,
		]);

		$membership = wu_create_membership([
			'customer_id'     => $customer->get_id(),
			'status'          => 'active',
			'amount'          => 29.99,
			'currency'        => 'USD',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($membership);

		$url = wu_get_membership_update_url($membership);

		$this->assertIsString($url);
		$this->assertNotEmpty($url);
	}

	/**
	 * Test wu_get_membership_update_url contains membership hash.
	 */
	public function test_get_membership_update_url_contains_hash(): void {

		$customer = wu_create_customer([
			'user_id'         => self::factory()->user->create(),
			'skip_validation' => true,
		]);

		$membership = wu_create_membership([
			'customer_id'     => $customer->get_id(),
			'status'          => 'active',
			'amount'          => 29.99,
			'currency'        => 'USD',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($membership);

		$url = wu_get_membership_update_url($membership);
		$hash = $membership->get_hash();

		$this->assertStringContainsString($hash, $url);
	}

	/**
	 * Test wu_get_membership_new_cart returns a Cart instance.
	 */
	public function test_get_membership_new_cart_returns_cart(): void {

		$customer = wu_create_customer([
			'user_id'         => self::factory()->user->create(),
			'skip_validation' => true,
		]);

		$product = wu_create_product([
			'name'            => 'Cart Test Plan',
			'slug'            => 'cart-test-plan-' . wp_rand(),
			'type'            => 'plan',
			'amount'          => 29.99,
			'recurring'       => true,
			'duration'        => 1,
			'duration_unit'   => 'month',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($product);

		$membership = wu_create_membership([
			'customer_id'     => $customer->get_id(),
			'plan_id'         => $product->get_id(),
			'status'          => 'active',
			'amount'          => 29.99,
			'initial_amount'  => 29.99,
			'currency'        => 'USD',
			'recurring'       => true,
			'duration'        => 1,
			'duration_unit'   => 'month',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($membership);

		$cart = wu_get_membership_new_cart($membership);

		$this->assertInstanceOf(\WP_Ultimo\Checkout\Cart::class, $cart);
	}

	/**
	 * Test wu_get_membership_new_cart with amount difference creates adjustment line item.
	 */
	public function test_get_membership_new_cart_with_amount_difference(): void {

		$customer = wu_create_customer([
			'user_id'         => self::factory()->user->create(),
			'skip_validation' => true,
		]);

		$product = wu_create_product([
			'name'            => 'Adjustment Plan',
			'slug'            => 'adjustment-plan-' . wp_rand(),
			'type'            => 'plan',
			'amount'          => 10.00,
			'recurring'       => true,
			'duration'        => 1,
			'duration_unit'   => 'month',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($product);

		// Set amount different from product price to trigger adjustment line item
		$membership = wu_create_membership([
			'customer_id'     => $customer->get_id(),
			'plan_id'         => $product->get_id(),
			'status'          => 'active',
			'amount'          => 15.00,
			'initial_amount'  => 15.00,
			'currency'        => 'USD',
			'recurring'       => true,
			'duration'        => 1,
			'duration_unit'   => 'month',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($membership);

		$cart = wu_get_membership_new_cart($membership);

		$this->assertInstanceOf(\WP_Ultimo\Checkout\Cart::class, $cart);
	}

	/**
	 * Test wu_membership_create_new_payment returns a Payment.
	 */
	public function test_membership_create_new_payment_returns_payment(): void {

		$customer = wu_create_customer([
			'user_id'         => self::factory()->user->create(),
			'skip_validation' => true,
		]);

		$product = wu_create_product([
			'name'            => 'Payment Plan',
			'slug'            => 'payment-plan-' . wp_rand(),
			'type'            => 'plan',
			'amount'          => 29.99,
			'recurring'       => true,
			'duration'        => 1,
			'duration_unit'   => 'month',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($product);

		$membership = wu_create_membership([
			'customer_id'     => $customer->get_id(),
			'plan_id'         => $product->get_id(),
			'status'          => 'active',
			'amount'          => 29.99,
			'initial_amount'  => 29.99,
			'currency'        => 'USD',
			'recurring'       => true,
			'duration'        => 1,
			'duration_unit'   => 'month',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($membership);

		$payment = wu_membership_create_new_payment($membership);

		$this->assertNotWPError($payment);
		$this->assertInstanceOf(\WP_Ultimo\Models\Payment::class, $payment);
	}

	/**
	 * Test wu_membership_create_new_payment with save=false returns unsaved payment.
	 */
	public function test_membership_create_new_payment_no_save(): void {

		$customer = wu_create_customer([
			'user_id'         => self::factory()->user->create(),
			'skip_validation' => true,
		]);

		$product = wu_create_product([
			'name'            => 'No Save Plan',
			'slug'            => 'no-save-plan-' . wp_rand(),
			'type'            => 'plan',
			'amount'          => 19.99,
			'recurring'       => true,
			'duration'        => 1,
			'duration_unit'   => 'month',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($product);

		$membership = wu_create_membership([
			'customer_id'     => $customer->get_id(),
			'plan_id'         => $product->get_id(),
			'status'          => 'active',
			'amount'          => 19.99,
			'initial_amount'  => 19.99,
			'currency'        => 'USD',
			'recurring'       => true,
			'duration'        => 1,
			'duration_unit'   => 'month',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($membership);

		$payment = wu_membership_create_new_payment($membership, true, true, false);

		$this->assertNotWPError($payment);
		$this->assertInstanceOf(\WP_Ultimo\Models\Payment::class, $payment);
		$this->assertEmpty($payment->get_id());
	}

	/**
	 * Test wu_membership_create_new_payment cancels existing pending payment.
	 */
	public function test_membership_create_new_payment_cancels_pending(): void {

		$customer = wu_create_customer([
			'user_id'         => self::factory()->user->create(),
			'skip_validation' => true,
		]);

		$product = wu_create_product([
			'name'            => 'Cancel Pending Plan',
			'slug'            => 'cancel-pending-plan-' . wp_rand(),
			'type'            => 'plan',
			'amount'          => 29.99,
			'recurring'       => true,
			'duration'        => 1,
			'duration_unit'   => 'month',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($product);

		$membership = wu_create_membership([
			'customer_id'     => $customer->get_id(),
			'plan_id'         => $product->get_id(),
			'status'          => 'active',
			'amount'          => 29.99,
			'initial_amount'  => 29.99,
			'currency'        => 'USD',
			'recurring'       => true,
			'duration'        => 1,
			'duration_unit'   => 'month',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($membership);

		// Create a pending payment first
		wu_create_payment([
			'customer_id'     => $customer->get_id(),
			'membership_id'   => $membership->get_id(),
			'currency'        => 'USD',
			'subtotal'        => 29.99,
			'total'           => 29.99,
			'status'          => 'pending',
			'skip_validation' => true,
		]);

		// Now create a new payment — should cancel the pending one
		$new_payment = wu_membership_create_new_payment($membership, true);

		$this->assertNotWPError($new_payment);
		$this->assertInstanceOf(\WP_Ultimo\Models\Payment::class, $new_payment);
	}

	/**
	 * Test wu_membership_create_new_payment without cancelling pending.
	 */
	public function test_membership_create_new_payment_no_cancel_pending(): void {

		$customer = wu_create_customer([
			'user_id'         => self::factory()->user->create(),
			'skip_validation' => true,
		]);

		$product = wu_create_product([
			'name'            => 'No Cancel Plan',
			'slug'            => 'no-cancel-plan-' . wp_rand(),
			'type'            => 'plan',
			'amount'          => 29.99,
			'recurring'       => true,
			'duration'        => 1,
			'duration_unit'   => 'month',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($product);

		$membership = wu_create_membership([
			'customer_id'     => $customer->get_id(),
			'plan_id'         => $product->get_id(),
			'status'          => 'active',
			'amount'          => 29.99,
			'initial_amount'  => 29.99,
			'currency'        => 'USD',
			'recurring'       => true,
			'duration'        => 1,
			'duration_unit'   => 'month',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($membership);

		$payment = wu_membership_create_new_payment($membership, false);

		$this->assertNotWPError($payment);
		$this->assertInstanceOf(\WP_Ultimo\Models\Payment::class, $payment);
	}

	/**
	 * Test wu_membership_create_new_payment keeping non-recurring items.
	 */
	public function test_membership_create_new_payment_keep_non_recurring(): void {

		$customer = wu_create_customer([
			'user_id'         => self::factory()->user->create(),
			'skip_validation' => true,
		]);

		$product = wu_create_product([
			'name'            => 'Keep Non Recurring Plan',
			'slug'            => 'keep-non-recurring-plan-' . wp_rand(),
			'type'            => 'plan',
			'amount'          => 29.99,
			'recurring'       => true,
			'duration'        => 1,
			'duration_unit'   => 'month',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($product);

		$membership = wu_create_membership([
			'customer_id'     => $customer->get_id(),
			'plan_id'         => $product->get_id(),
			'status'          => 'active',
			'amount'          => 29.99,
			'initial_amount'  => 29.99,
			'currency'        => 'USD',
			'recurring'       => true,
			'duration'        => 1,
			'duration_unit'   => 'month',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($membership);

		$payment = wu_membership_create_new_payment($membership, false, false);

		$this->assertNotWPError($payment);
		$this->assertInstanceOf(\WP_Ultimo\Models\Payment::class, $payment);
	}

	/**
	 * Test wu_get_memberships returns created memberships filtered by customer.
	 */
	public function test_get_memberships_returns_created(): void {

		$customer = wu_create_customer([
			'user_id'         => self::factory()->user->create(),
			'skip_validation' => true,
		]);

		$membership = wu_create_membership([
			'customer_id'     => $customer->get_id(),
			'status'          => 'active',
			'amount'          => 10.00,
			'currency'        => 'USD',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($membership);

		$memberships = wu_get_memberships(['customer_id' => $customer->get_id()]);

		$this->assertIsArray($memberships);
		$this->assertNotEmpty($memberships);
	}

	/**
	 * Test wu_get_membership_product_price returns a float for a valid product.
	 */
	public function test_get_membership_product_price_returns_float(): void {

		$customer = wu_create_customer([
			'user_id'         => self::factory()->user->create(),
			'skip_validation' => true,
		]);

		$product = wu_create_product([
			'name'            => 'Price Test Plan',
			'slug'            => 'price-test-plan-' . wp_rand(),
			'type'            => 'plan',
			'amount'          => 29.99,
			'recurring'       => true,
			'duration'        => 1,
			'duration_unit'   => 'month',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($product);

		$membership = wu_create_membership([
			'customer_id'     => $customer->get_id(),
			'plan_id'         => $product->get_id(),
			'status'          => 'active',
			'amount'          => 29.99,
			'initial_amount'  => 29.99,
			'currency'        => 'USD',
			'recurring'       => true,
			'duration'        => 1,
			'duration_unit'   => 'month',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($membership);

		$price = wu_get_membership_product_price($membership, $product->get_id(), 1);

		$this->assertIsFloat($price);
	}

	/**
	 * Test wu_get_membership_product_price with only_recurring=false returns total.
	 */
	public function test_get_membership_product_price_not_only_recurring(): void {

		$customer = wu_create_customer([
			'user_id'         => self::factory()->user->create(),
			'skip_validation' => true,
		]);

		$product = wu_create_product([
			'name'            => 'Non Recurring Price Plan',
			'slug'            => 'non-recurring-price-plan-' . wp_rand(),
			'type'            => 'plan',
			'amount'          => 19.99,
			'recurring'       => true,
			'duration'        => 1,
			'duration_unit'   => 'month',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($product);

		$membership = wu_create_membership([
			'customer_id'     => $customer->get_id(),
			'plan_id'         => $product->get_id(),
			'status'          => 'active',
			'amount'          => 19.99,
			'initial_amount'  => 19.99,
			'currency'        => 'USD',
			'recurring'       => true,
			'duration'        => 1,
			'duration_unit'   => 'month',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($membership);

		$price = wu_get_membership_product_price($membership, $product->get_id(), 1, false);

		$this->assertIsFloat($price);
	}

	/**
	 * Test wu_get_membership_product_price with invalid product returns errors object.
	 */
	public function test_get_membership_product_price_invalid_product(): void {

		$customer = wu_create_customer([
			'user_id'         => self::factory()->user->create(),
			'skip_validation' => true,
		]);

		$membership = wu_create_membership([
			'customer_id'     => $customer->get_id(),
			'status'          => 'active',
			'amount'          => 10.00,
			'initial_amount'  => 10.00,
			'currency'        => 'USD',
			'recurring'       => true,
			'duration'        => 1,
			'duration_unit'   => 'month',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($membership);

		// Pass a non-existent product ID — add_product returns false, function returns cart errors
		$result = wu_get_membership_product_price($membership, 999999, 1);

		// Should return the cart errors object (not a numeric price)
		$this->assertNotNull($result);
		$this->assertIsNotFloat($result);
	}

	/**
	 * Test wu_get_membership_new_cart with initial amount different from cart total.
	 */
	public function test_get_membership_new_cart_with_initial_amount_difference(): void {

		$customer = wu_create_customer([
			'user_id'         => self::factory()->user->create(),
			'skip_validation' => true,
		]);

		$product = wu_create_product([
			'name'            => 'Init Adjust Plan',
			'slug'            => 'init-adjust-plan-' . wp_rand(),
			'type'            => 'plan',
			'amount'          => 10.00,
			'recurring'       => true,
			'duration'        => 1,
			'duration_unit'   => 'month',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($product);

		// Set initial_amount different from amount to trigger INITADJUSTMENT line item
		$membership = wu_create_membership([
			'customer_id'     => $customer->get_id(),
			'plan_id'         => $product->get_id(),
			'status'          => 'active',
			'amount'          => 10.00,
			'initial_amount'  => 25.00,
			'currency'        => 'USD',
			'recurring'       => true,
			'duration'        => 1,
			'duration_unit'   => 'month',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($membership);

		$cart = wu_get_membership_new_cart($membership);

		$this->assertInstanceOf(\WP_Ultimo\Checkout\Cart::class, $cart);

		// The INITADJUSTMENT line item should bring the cart total to match initial_amount (25.00)
		$this->assertEquals(25.00, $cart->get_total());
	}

	/**
	 * Test wu_get_membership_update_url fallback to register page when no site and no checkout page.
	 */
	public function test_get_membership_update_url_fallback_register(): void {

		$customer = wu_create_customer([
			'user_id'         => self::factory()->user->create(),
			'skip_validation' => true,
		]);

		$membership = wu_create_membership([
			'customer_id'     => $customer->get_id(),
			'status'          => 'active',
			'amount'          => 10.00,
			'currency'        => 'USD',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($membership);

		// No site attached, no checkout page configured — falls through to register page fallback
		$url = wu_get_membership_update_url($membership);

		$this->assertIsString($url);
		$this->assertStringContainsString($membership->get_hash(), $url);
		// The register fallback branch adds wu_form=wu-checkout to the URL
		$this->assertStringContainsString('wu_form=wu-checkout', $url);
	}
}

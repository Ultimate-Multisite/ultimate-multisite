<?php
/**
 * Tests for Free_Gateway class.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Gateways;

use WP_UnitTestCase;
use WP_Ultimo\Database\Memberships\Membership_Status;
use WP_Ultimo\Database\Payments\Payment_Status;

/**
 * Test class for Free_Gateway.
 */
class Free_Gateway_Test extends WP_UnitTestCase {

	/**
	 * @var Free_Gateway
	 */
	private $gateway;

	/**
	 * Set up test fixtures.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->gateway = new Free_Gateway();
	}

	/**
	 * Test gateway ID is free.
	 */
	public function test_gateway_id(): void {
		$this->assertEquals('free', $this->gateway->get_id());
	}

	/**
	 * Test process_checkout with new subscription.
	 */
	public function test_process_checkout_new(): void {
		// Create test user
		$user_id = self::factory()->user->create([
			'user_login' => 'freegatewayuser',
			'user_email' => 'freegateway@example.com',
		]);

		// Create customer
		$customer = wu_create_customer([
			'user_id'            => $user_id,
			'email_verification' => 'none',
		]);

		$this->assertNotWPError($customer);

		// Create product
		$product = wu_create_product([
			'name'         => 'Free Plan',
			'slug'         => 'free-plan-' . wp_generate_uuid4(),
			'pricing_type' => 'free',
			'amount'       => 0,
			'type'         => 'plan',
		]);

		$this->assertNotWPError($product);

		// Create membership
		$membership = wu_create_membership([
			'customer_id'     => $customer->get_id(),
			'plan_id'         => $product->get_id(),
			'status'          => Membership_Status::PENDING,
			'recurring'       => false,
			'amount'          => 0,
			'skip_validation' => true,
		]);

		$this->assertNotWPError($membership);

		// Create payment
		$payment = wu_create_payment([
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'currency'      => 'USD',
			'subtotal'      => 0,
			'total'         => 0,
			'status'        => Payment_Status::PENDING,
			'gateway'       => 'free',
		]);

		$this->assertNotWPError($payment);

		// Create cart mock
		$cart = new \WP_Ultimo\Checkout\Cart([
			'products' => [$product->get_id()],
		]);

		// Process checkout
		$this->gateway->process_checkout($payment, $membership, $customer, $cart, 'new');

		// Reload from database
		$updated_payment    = wu_get_payment($payment->get_id());
		$updated_membership = wu_get_membership($membership->get_id());

		// Assert payment is completed
		$this->assertEquals(Payment_Status::COMPLETED, $updated_payment->get_status());

		// Assert membership gateway is set
		$this->assertEquals('free', $updated_membership->get_gateway());
	}

	/**
	 * Test process_cancellation does nothing.
	 */
	public function test_process_cancellation(): void {
		// Create test user
		$user_id = self::factory()->user->create([
			'user_login' => 'canceluser',
			'user_email' => 'cancel@example.com',
		]);

		$customer = wu_create_customer([
			'user_id'            => $user_id,
			'email_verification' => 'none',
		]);

		$this->assertNotWPError($customer);

		$product = wu_create_product([
			'name'         => 'Cancel Plan',
			'slug'         => 'cancel-plan-' . wp_generate_uuid4(),
			'pricing_type' => 'free',
			'amount'       => 0,
			'type'         => 'plan',
		]);

		$this->assertNotWPError($product);

		$membership = wu_create_membership([
			'customer_id'     => $customer->get_id(),
			'plan_id'         => $product->get_id(),
			'status'          => Membership_Status::ACTIVE,
			'recurring'       => false,
			'amount'          => 0,
			'skip_validation' => true,
		]);

		$this->assertNotWPError($membership);

		// Process cancellation - should not throw
		$result = $this->gateway->process_cancellation($membership, $customer);

		$this->assertNull($result);
	}

	/**
	 * Test process_refund does nothing.
	 */
	public function test_process_refund(): void {
		// Create test user
		$user_id = self::factory()->user->create([
			'user_login' => 'refunduser',
			'user_email' => 'refund@example.com',
		]);

		$customer = wu_create_customer([
			'user_id'            => $user_id,
			'email_verification' => 'none',
		]);

		$this->assertNotWPError($customer);

		$product = wu_create_product([
			'name'         => 'Refund Plan',
			'slug'         => 'refund-plan-' . wp_generate_uuid4(),
			'pricing_type' => 'free',
			'amount'       => 0,
			'type'         => 'plan',
		]);

		$this->assertNotWPError($product);

		$membership = wu_create_membership([
			'customer_id'     => $customer->get_id(),
			'plan_id'         => $product->get_id(),
			'status'          => Membership_Status::ACTIVE,
			'recurring'       => false,
			'amount'          => 0,
			'skip_validation' => true,
		]);

		$this->assertNotWPError($membership);

		$payment = wu_create_payment([
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'currency'      => 'USD',
			'subtotal'      => 0,
			'total'         => 0,
			'status'        => Payment_Status::COMPLETED,
			'gateway'       => 'free',
		]);

		$this->assertNotWPError($payment);

		// Process refund - should not throw
		$result = $this->gateway->process_refund(0, $payment, $membership, $customer);

		$this->assertNull($result);
	}
}

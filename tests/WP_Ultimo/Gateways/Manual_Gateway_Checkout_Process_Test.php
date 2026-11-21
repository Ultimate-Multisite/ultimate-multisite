<?php

namespace WP_Ultimo\Gateways;

use WP_Ultimo\Database\Memberships\Membership_Status;
use WP_Ultimo\Database\Payments\Payment_Status;
use WP_Ultimo\Models\Customer;

class Manual_Gateway_Checkout_Process_Test extends \WP_UnitTestCase {
	/**
	 * @var \WP_Ultimo\Gateways\Manual_Gateway
	 */
	private $gateway;

	private static Customer $customer;


	public static function set_up_before_class() {
		parent::set_up_before_class();
		self::$customer = wu_create_customer(
			[
				'username' => 'testuser',
				'email'    => 'test@example.com',
				'password' => 'password123',
			]
		);
	}
	public function setUp(): void {
		parent::setUp();
		$this->gateway = new \WP_Ultimo\Gateways\Manual_Gateway();
		// Create customer
	}

	/**
	 * Data provider for test cases
	 */
	public function checkoutDataProvider(): array {
		return [
			'new_subscription'     => [
				'type'            => 'new',
				'recurring'       => true,
				'trial'           => false,
				'expected_status' => Membership_Status::ON_HOLD,
			],
			'new_with_trial'       => [
				'type'            => 'new',
				'recurring'       => true,
				'trial'           => true,
				'expected_status' => Membership_Status::TRIALING,
			],
			'retry_payment'        => [
				'type'            => 'retry',
				'recurring'       => true,
				'trial'           => false,
				'expected_status' => Membership_Status::ON_HOLD,
			],
			'upgrade_membership'   => [
				'type'            => 'upgrade',
				'recurring'       => true,
				'trial'           => false,
				'expected_status' => Membership_Status::ON_HOLD,
			],
			'downgrade_membership' => [
				'type'            => 'downgrade',
				'recurring'       => true,
				'trial'           => false,
				'expected_status' => Membership_Status::ON_HOLD,
			],
			'addon_purchase'       => [
				'type'            => 'addon',
				'recurring'       => true,
				'trial'           => false,
				'expected_status' => Membership_Status::ON_HOLD,
			],
		];
	}

	/**
	 * @dataProvider checkoutDataProvider
	 */
	public function test_process_checkout(string $type, bool $recurring, bool $trial, string $expected_status): void {
		$customer = self::$customer;
		wp_set_current_user($customer->get_user_id(), $customer->get_username());
		// Create first product
		$product = wu_create_product(
			[
				'name'                => 'Test Product',
				'slug'                => 'test-product',
				'amount'              => 50.00,
				'recurring'           => $recurring,
				'duration'            => 1,
				'duration_unit'       => 'month',
				'trial_duration'      => $trial ? 14 : 0,
				'trial_duration_unit' => 'day',
				'type'                => 'plan',
				'pricing_type'        => 'paid',
				'active'              => true,
			]
		);

		// Create a second product for upgrade/downgrade scenarios
		$second_product = wu_create_product(
			[
				'name'          => 'Second Product',
				'slug'          => 'second-product',
				'amount'        => 75.00,
				'recurring'     => $recurring,
				'duration'      => 1,
				'duration_unit' => 'month',
				'type'          => 'plan',
				'pricing_type'  => 'paid',
				'active'        => true,
			]
		);

		// Create membership
		$membership = wu_create_membership(
			[
				'customer_id' => $customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => 'active',
				'recurring'   => $recurring,
			]
		);

		// Create cart with appropriate settings
		$cart_args = [
			'cart_type'     => $type,
			'products'      => [$product->get_id()],
			'duration'      => 1,
			'duration_unit' => 'month',
			'membership_id' => ($type !== 'new') ? $membership->get_id() : false,
			'payment_id'    => false,
			'discount_code' => false,
			'auto_renew'    => $recurring,
			'country'       => 'US',
			'state'         => 'NY',
			'city'          => 'New York',
			'currency'      => 'USD',
		];

		$cart = new \WP_Ultimo\Checkout\Cart($cart_args);

		// Create a payment
		$payment = wu_create_payment(
			[
				'customer_id'   => $customer->get_id(),
				'membership_id' => $membership->get_id(),
				'gateway'       => 'manual',
				'status'        => 'pending',
				'total'         => $trial ? 0 : $product->get_amount(),
			]
		);

		// Process the checkout
		$this->gateway->process_checkout($payment, $membership, $customer, $cart, $type);

		// Check payment status
		if ($trial) {
			$this->assertEquals(
				Payment_Status::COMPLETED,
				$payment->get_status(),
				'Payment should be completed for trials'
			);
			$this->assertEquals(
				Membership_Status::TRIALING,
				$membership->get_status(),
				'Membership should be in trial status'
			);
		} elseif ($payment->get_total() === 0.00) {
			$this->assertEquals(
				Payment_Status::COMPLETED,
				$payment->get_status(),
				'Payment should be completed for zero amount'
			);
		} elseif ($type === 'upgrade' || $type === 'addon') {
			$this->assertEquals(
				Payment_Status::PENDING,
				$payment->get_status(),
				'Payment should be pending for upgrades and addons'
			);
		} else {
			$this->assertEquals(
				Payment_Status::PENDING,
				$payment->get_status(),
				'Payment should be pending for regular transactions'
			);
		}

		// Assert membership status
		if ($type === 'downgrade') {
			// For downgrades, verify scheduled swap
			$this->assertTrue((bool) $membership->get_scheduled_swap());
			$scheduled_swap = $membership->get_scheduled_swap();
			$this->assertEquals($product->get_id(), $scheduled_swap->order->get_plan_id());
		} elseif ($type === 'upgrade' || $type === 'addon') {
			// For upgrades and addons, verify immediate swap
			$this->assertEquals($product->get_id(), $membership->get_plan_id());
			$this->assertEquals($expected_status, $membership->get_status());
		}

		// Cleanup
		$payment->delete();
		$membership->delete();
		$product->delete();
		$second_product->delete();
	}

	/**
	 * Builds a membership, a pending manual payment and a cart for one scenario.
	 *
	 * @param string $slug            Unique plan slug.
	 * @param string $cart_type       Cart type to build.
	 * @param array  $membership_args Membership attributes.
	 * @return array{0: \WP_Ultimo\Models\Membership, 1: \WP_Ultimo\Models\Payment, 2: \WP_Ultimo\Checkout\Cart}
	 */
	private function build_checkout(string $slug, string $cart_type, array $membership_args): array {

		$customer = self::$customer;

		wp_set_current_user($customer->get_user_id(), $customer->get_username());

		$product = wu_create_product(
			[
				'name'          => $slug,
				'slug'          => $slug,
				'amount'        => 50.00,
				'recurring'     => true,
				'duration'      => 1,
				'duration_unit' => 'month',
				'type'          => 'plan',
				'pricing_type'  => 'paid',
				'active'        => true,
			]
		);

		$membership = wu_create_membership(
			array_merge(
				[
					'customer_id' => $customer->get_id(),
					'plan_id'     => $product->get_id(),
					'recurring'   => true,
				],
				$membership_args
			)
		);

		$this->assertNotWPError($membership);

		$cart = new \WP_Ultimo\Checkout\Cart(
			[
				'cart_type'     => $cart_type,
				'products'      => [$product->get_id()],
				'duration'      => 1,
				'duration_unit' => 'month',
				'membership_id' => $membership->get_id(),
				'currency'      => 'USD',
				'country'       => 'US',
			]
		);

		$payment = wu_create_payment(
			[
				'customer_id'   => $customer->get_id(),
				'membership_id' => $membership->get_id(),
				'gateway'       => 'manual',
				'status'        => Payment_Status::PENDING,
				'total'         => 50.00,
			]
		);

		return [$membership, $payment, $cart];
	}

	/**
	 * On-hold counts as active, so a reactivation must not restore access
	 * before an administrator confirms the payment.
	 */
	public function test_reactivation_keeps_the_membership_inactive_until_payment_is_confirmed(): void {

		[$membership, $payment, $cart] = $this->build_checkout(
			'manual-reactivation-plan',
			'upgrade',
			['status' => Membership_Status::CANCELLED]
		);

		$this->gateway->process_checkout($payment, $membership, self::$customer, $cart, 'reactivation');

		$reloaded = wu_get_membership($membership->get_id());

		$this->assertSame(Membership_Status::CANCELLED, $reloaded->get_status());
		$this->assertFalse($reloaded->is_active(), 'Access must wait for the payment confirmation.');
		$this->assertSame(Payment_Status::PENDING, $payment->get_status());
	}

	/**
	 * An add-on is an extra item on the existing subscription: paying for it
	 * manually must not move the membership off its recurring gateway.
	 */
	public function test_addon_keeps_the_existing_recurring_gateway(): void {

		[$membership, $payment, $cart] = $this->build_checkout(
			'manual-addon-plan',
			'addon',
			[
				'status'  => Membership_Status::ACTIVE,
				'gateway' => 'free',
			]
		);

		$this->gateway->process_checkout($payment, $membership, self::$customer, $cart, 'addon');

		$this->assertSame('free', wu_get_membership($membership->get_id())->get_gateway());
	}

	/**
	 * A manually paid plan change is invoiced manually from now on.
	 */
	public function test_upgrade_moves_the_membership_to_the_manual_gateway(): void {

		[$membership, $payment, $cart] = $this->build_checkout(
			'manual-upgrade-plan',
			'upgrade',
			[
				'status'  => Membership_Status::ACTIVE,
				'gateway' => 'free',
			]
		);

		$this->gateway->process_checkout($payment, $membership, self::$customer, $cart, 'upgrade');

		$reloaded = wu_get_membership($membership->get_id());

		$this->assertSame('manual', $reloaded->get_gateway());
		$this->assertFalse($reloaded->should_auto_renew());
	}

	public function tearDown(): void {

		parent::tearDown();
	}

	public static function tear_down_after_class() {
		global $wpdb;
		self::$customer->delete();
		// Clean up the test data
		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wu_memberships");
		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wu_products");
		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wu_customers");
		$wpdb->query("TRUNCATE TABLE {$wpdb->prefix}wu_payments");
		parent::tear_down_after_class();
	}
}

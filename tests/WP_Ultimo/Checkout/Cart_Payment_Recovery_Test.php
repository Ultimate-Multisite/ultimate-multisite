<?php
/**
 * Outstanding invoices must retain their existing membership during checkout.
 *
 * @package WP_Ultimo
 * @subpackage Tests\Checkout
 */

namespace WP_Ultimo\Checkout;

use WP_UnitTestCase;

/** Regression coverage for payment retries on already-active memberships. */
class Cart_Payment_Recovery_Test extends WP_UnitTestCase {

	/**
	 * Payable invoices remain retries regardless of membership activation.
	 *
	 * @dataProvider recovery_cases
	 * @param string $membership_status Existing membership state.
	 * @param string $payment_status Outstanding invoice state.
	 */
	public function test_outstanding_invoice_preserves_membership($membership_status, $payment_status) {
		$customer = wu_create_customer([
			'user_id' => self::factory()->user->create(),
		]);
		$this->assertNotWPError($customer);
		wp_set_current_user($customer->get_user_id());

		$plan = wu_create_product([
			'name'          => 'Payment recovery plan',
			'type'          => 'plan',
			'pricing_type'  => 'paid',
			'amount'        => 35,
			'recurring'     => true,
			'duration'      => 1,
			'duration_unit' => 'month',
		]);
		$this->assertNotWPError($plan);
		$membership = wu_create_membership([
			'customer_id'   => $customer->get_id(),
			'plan_id'       => $plan->get_id(),
			'status'        => $membership_status,
			'amount'        => 19.64,
			'currency'      => 'EUR',
			'duration'      => 1,
			'duration_unit' => 'month',
			'auto_renew'    => false,
		]);
		$this->assertNotWPError($membership);
		$item    = new Line_Item([
			'product'      => $plan,
			'unit_price'   => 19.64,
			'quantity'     => 1,
			'taxable'      => false,
			'discountable' => false,
		]);
		$payment = wu_create_payment([
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'status'        => $payment_status,
			'currency'      => 'EUR',
			'total'         => 19.64,
			'subtotal'      => 19.64,
			'line_items'    => [$item],
		]);
		$this->assertNotWPError($payment);

		$cart = new Cart([
			'payment_id' => $payment->get_id(),
			'auto_renew' => true,
		]);

		$this->assertFalse($cart->errors->has_errors());
		$this->assertSame('retry', $cart->get_cart_type());
		$this->assertSame($membership->get_id(), $cart->get_membership()->get_id());
		$this->assertSame($payment->get_id(), $cart->get_payment()->get_id());
		$this->assertSame('EUR', $cart->get_currency());
		$this->assertEquals(19.64, $cart->get_total());
		$this->assertEquals(19.64, $cart->get_recurring_total());
		$this->assertTrue($cart->should_collect_payment());
		$this->assertTrue($cart->should_auto_renew());
		$this->assertSame($membership_status, wu_get_membership($membership->get_id())->get_status());
		$this->assertSame($payment_status, wu_get_payment($payment->get_id())->get_status());
	}

	/** @return array Membership and payment states supported by payment recovery. */
	public static function recovery_cases() {
		return [
			'active pending'   => ['active', 'pending'],
			'active failed'    => ['active', 'failed'],
			'pending pending'  => ['pending', 'pending'],
			'pending failed'   => ['pending', 'failed'],
			'trialing pending' => ['trialing', 'pending'],
		];
	}
}

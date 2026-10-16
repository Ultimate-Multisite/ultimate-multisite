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

	/** @var array Models requiring explicit cleanup outside WordPress core tables. */
	private $fixtures = [];

	/** @return void Remove addon-table fixtures even if an assertion fails. */
	public function tear_down() {
		foreach (array_reverse($this->fixtures) as $fixture) {
			$fixture->delete();
		}
		$this->fixtures = [];
		wp_set_current_user(0);
		parent::tear_down();
	}

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
		$this->fixtures[] = $customer;
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
		$this->fixtures[] = $plan;

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
		$this->fixtures[] = $membership;

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
		$this->fixtures[] = $payment;

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

	/**
	 * A discounted invoice is paid as issued, not re-priced with the coupon's current value.
	 *
	 * The fixed coupon is stored in the base currency (13.00); the invoice was
	 * issued in EUR with the converted discount (11.60) already applied.
	 */
	public function test_discounted_invoice_keeps_issued_amount() {
		$customer = wu_create_customer([
			'user_id' => self::factory()->user->create(),
		]);
		$this->assertNotWPError($customer);
		$this->fixtures[] = $customer;
		wp_set_current_user($customer->get_user_id());

		$plan = wu_create_product([
			'name'          => 'Discounted recovery plan',
			'type'          => 'plan',
			'pricing_type'  => 'paid',
			'amount'        => 35,
			'recurring'     => true,
			'duration'      => 1,
			'duration_unit' => 'month',
		]);
		$this->assertNotWPError($plan);
		$this->fixtures[] = $plan;

		$coupon = wu_create_discount_code([
			'name'              => 'Recovery coupon',
			'code'              => 'RECOVERY13',
			'value'             => 13,
			'type'              => 'absolute',
			'apply_to_renewals' => true,
			'active'            => true,
		]);
		$this->assertNotWPError($coupon);
		$this->fixtures[] = $coupon;

		$membership = wu_create_membership([
			'customer_id'   => $customer->get_id(),
			'plan_id'       => $plan->get_id(),
			'status'        => 'active',
			'amount'        => 19.64,
			'currency'      => 'EUR',
			'duration'      => 1,
			'duration_unit' => 'month',
			'auto_renew'    => false,
		]);
		$this->assertNotWPError($membership);
		$this->fixtures[] = $membership;

		$item = new Line_Item([
			'product'                    => $plan,
			'unit_price'                 => 31.24,
			'quantity'                   => 1,
			'taxable'                    => false,
			'discountable'               => true,
			'discount_rate'              => 11.60,
			'discount_type'              => 'absolute',
			'discount_label'             => 'RECOVERY13',
			'apply_discount_to_renewals' => true,
		]);
		$this->assertEquals(19.64, $item->get_total());

		$payment = wu_create_payment([
			'customer_id'   => $customer->get_id(),
			'membership_id' => $membership->get_id(),
			'status'        => 'pending',
			'currency'      => 'EUR',
			'subtotal'      => 31.24,
			'total'         => 19.64,
			'discount_code' => 'RECOVERY13',
			'line_items'    => [$item],
		]);
		$this->assertNotWPError($payment);
		$this->fixtures[] = $payment;

		$cart = new Cart([
			'payment_id' => $payment->get_id(),
		]);

		$this->assertFalse($cart->errors->has_errors());
		$this->assertSame('retry', $cart->get_cart_type());
		$this->assertSame('EUR', $cart->get_currency());
		$this->assertEquals(19.64, $cart->get_total());
		$this->assertEquals(19.64, $cart->get_recurring_total());
		$this->assertEquals(-11.60, $cart->get_total_discounts());
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

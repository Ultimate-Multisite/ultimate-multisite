<?php
/**
 * Regression guard for WP_Ultimo\Checkout\Cart::should_collect_payment().
 *
 * THE INCIDENT (2026-05-27 — self-inflicted production outage):
 * When the network setting `allow_trial_without_payment_method` was ON, a $0
 * trial cart made should_collect_payment() return FALSE. Checkout then routed
 * through the `free` gateway, which SKIPS WooCommerce entirely. Customers went
 * through every step and a site was created "dry": NO real WooCommerce order,
 * NO Stripe charge, NO "new order" email, NO AutomateWoo, and the subscription
 * (gateway=free) never billed. Real cases: Eva and Liz (orders 547/354,
 * 548/355). The fix in production was to keep the setting OFF so a paid plan —
 * even a trial that requires a payment method — returns TRUE and routes through
 * the real `woocommerce` gateway.
 *
 * These tests exercise the REAL method on a REAL Cart built from a REAL
 * wu_create_product(), toggling the REAL setting via wu_save_setting().
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Checkout;

use WP_UnitTestCase;

/**
 * Test class for Cart::should_collect_payment().
 */
class Cart_Should_Collect_Payment_Test extends WP_UnitTestCase {

	/**
	 * The setting key that caused the 2026-05-27 outage when set to true.
	 *
	 * @var string
	 */
	private const TRIAL_SETTING = 'allow_trial_without_payment_method';

	/**
	 * Restore the safe production default after every test so a leaked ON
	 * value cannot poison later tests.
	 *
	 * @return void
	 */
	protected function tearDown(): void {
		wu_save_setting(self::TRIAL_SETTING, false);
		wp_set_current_user(0);
		parent::tearDown();
	}

	/**
	 * Build a Cart for a single product, as the checkout does for a new signup.
	 *
	 * @param int $product_id The product to put in the cart.
	 * @return Cart
	 */
	private function build_cart_for_product(int $product_id): Cart {
		return new Cart(
			[
				'cart_type'     => 'new',
				'products'      => [ $product_id ],
				'duration'      => 1,
				'duration_unit' => 'month',
			]
		);
	}

	/**
	 * A normal paid recurring plan must collect payment.
	 *
	 * This is the baseline: a non-trial, non-free plan always routes through
	 * the real `woocommerce` gateway. If this ever returns false, paid signups
	 * would silently bypass WooCommerce just like the 2026-05-27 trial outage.
	 *
	 * @return void
	 */
	public function test_paid_plan_collects_payment(): void {
		$product = wu_create_product(
			[
				'name'          => 'Paid Plan',
				'slug'          => 'paid-plan-' . uniqid(),
				'amount'        => 49,
				'type'          => 'plan',
				'active'        => true,
				'recurring'     => true,
				'duration'      => 1,
				'duration_unit' => 'month',
			]
		);

		if ( is_wp_error( $product ) ) {
			$this->markTestSkipped( 'Could not create product: ' . $product->get_error_message() );
			return;
		}

		$cart = $this->build_cart_for_product( $product->get_id() );

		$this->assertGreaterThan( 0.0, (float) $cart->get_total(), 'A paid plan must have a non-zero total for this test to be meaningful.' );
		$this->assertTrue(
			$cart->should_collect_payment(),
			'A paid recurring plan must collect payment so checkout routes through the real woocommerce gateway, never the free gateway that skips WooCommerce.'
		);
	}

	/**
	 * THE REGRESSION GUARD.
	 *
	 * A trial plan with `allow_trial_without_payment_method` = false (the safe
	 * production setting) MUST still collect payment. This is the exact
	 * condition from the 2026-05-27 outage: with the setting OFF, a trial has
	 * to route through the real `woocommerce` gateway. If this assertion ever
	 * flips to false, trial signups would again be created "dry" with no
	 * WooCommerce order, no Stripe charge, no new-order email, and no
	 * AutomateWoo (the Eva / Liz incident, orders 547/354, 548/355).
	 *
	 * @return void
	 */
	public function test_trial_with_payment_method_required_still_collects(): void {
		// The safe production setting: trials DO require a payment method.
		wu_save_setting( self::TRIAL_SETTING, false );

		// Brand-new visitor (not logged in) => no customer => trial is eligible
		// (has_trial() returns true). wu_get_current_customer() keys off the
		// current user id.
		wp_set_current_user( 0 );

		$product = wu_create_product(
			[
				'name'                => 'Trial Plan (payment required)',
				'slug'                => 'trial-plan-required-' . uniqid(),
				'amount'              => 19.99,
				'type'                => 'plan',
				'active'              => true,
				'recurring'           => true,
				'duration'            => 1,
				'duration_unit'       => 'month',
				'trial_duration'      => 14,
				'trial_duration_unit' => 'day',
			]
		);

		if ( is_wp_error( $product ) ) {
			$this->markTestSkipped( 'Could not create product: ' . $product->get_error_message() );
			return;
		}

		$cart = $this->build_cart_for_product( $product->get_id() );

		$this->assertTrue(
			$cart->has_trial(),
			'Sanity check: the cart must actually be a trial cart, otherwise this guard is not exercising the trial branch of should_collect_payment().'
		);

		$this->assertTrue(
			$cart->should_collect_payment(),
			'REGRESSION GUARD (2026-05-27): with allow_trial_without_payment_method OFF, a trial plan MUST collect payment so checkout uses the real woocommerce gateway. Returning false here is exactly the bug that created dry sites with no WC order / Stripe charge / new-order email / AutomateWoo (Eva, Liz — orders 547/354, 548/355).'
		);
	}

	/**
	 * Pin the dangerous behavior so any change to it is VISIBLE in the diff.
	 *
	 * With `allow_trial_without_payment_method` = true, the method returns
	 * false for a trial cart and checkout routes through the `free` gateway
	 * that skips WooCommerce. This is the configuration that caused the
	 * 2026-05-27 outage. We do NOT endorse it — we document it so that if the
	 * core logic changes, this test breaks and forces a deliberate review
	 * instead of a silent regression.
	 *
	 * @return void
	 */
	public function test_documents_free_route_when_setting_on(): void {
		// The dangerous setting that caused the outage.
		wu_save_setting( self::TRIAL_SETTING, true );

		// Brand-new visitor (not logged in) => trial eligible.
		wp_set_current_user( 0 );

		$product = wu_create_product(
			[
				'name'                => 'Trial Plan (no payment)',
				'slug'                => 'trial-plan-nopay-' . uniqid(),
				'amount'              => 19.99,
				'type'                => 'plan',
				'active'              => true,
				'recurring'           => true,
				'duration'            => 1,
				'duration_unit'       => 'month',
				'trial_duration'      => 14,
				'trial_duration_unit' => 'day',
			]
		);

		if ( is_wp_error( $product ) ) {
			$this->markTestSkipped( 'Could not create product: ' . $product->get_error_message() );
			return;
		}

		$cart = $this->build_cart_for_product( $product->get_id() );

		$this->assertTrue( $cart->has_trial(), 'Sanity check: must be a trial cart for this branch.' );

		$this->assertFalse(
			$cart->should_collect_payment(),
			'Documents the 2026-05-27 outage configuration: with allow_trial_without_payment_method ON, a trial cart skips payment collection and routes through the free gateway (which bypasses WooCommerce). If this ever changes, the change must be reviewed deliberately — this setting must stay OFF in production.'
		);
	}
}

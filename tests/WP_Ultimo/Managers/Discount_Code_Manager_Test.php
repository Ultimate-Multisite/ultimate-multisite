<?php
/**
 * Unit tests for Discount_Code_Manager.
 *
 * @package WP_Ultimo\Tests\Managers
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Discount_Code_Manager;

class Discount_Code_Manager_Test extends \WP_UnitTestCase {

	use Manager_Test_Trait;

	protected function get_manager_class(): string {
		return Discount_Code_Manager::class;
	}

	protected function get_expected_slug(): ?string {
		return 'discount_code';
	}

	protected function get_expected_model_class(): ?string {
		return \WP_Ultimo\Models\Discount_Code::class;
	}

	public function test_discount_use_is_counted_once_when_receipt_dispatch_is_retried(): void {

		$code     = 'RETRY' . wp_rand(1000, 9999);
		$discount = wu_create_discount_code(
			[
				'name'            => 'Retry Discount',
				'code'            => $code,
				'description'     => '',
				'value'           => 10,
				'type'            => 'percentage',
				'active'          => true,
				'skip_validation' => true,
			]
		);

		$this->assertNotWPError($discount);
		$this->assertGreaterThan(0, $discount->get_id());

		$customer = wu_create_customer(
			[
				'username' => 'discount-retry-' . wp_rand(1000, 9999),
				'email'    => 'discount-retry-' . wp_rand(1000, 9999) . '@example.com',
				'password' => 'password123',
			]
		);

		$this->assertNotWPError($customer);

		$membership = wu_create_membership(
			[
				'customer_id'     => $customer->get_id(),
				'status'          => \WP_Ultimo\Database\Memberships\Membership_Status::ACTIVE,
				'skip_validation' => true,
			]
		);

		$this->assertNotWPError($membership);

		$payment = wu_create_payment(
			[
				'customer_id'   => $customer->get_id(),
				'membership_id' => $membership->get_id(),
				'currency'      => 'USD',
				'subtotal'      => 90,
				'total'         => 90,
				'status'        => \WP_Ultimo\Database\Payments\Payment_Status::COMPLETED,
				'gateway'       => 'manual',
			]
		);

		$this->assertNotWPError($payment);

		$payment->update_meta('wu_original_cart', new \WP_Ultimo\Checkout\Cart(['discount_code' => $code]));

		$resolve_gateway = static function ($gateway, $gateway_id) {
			return ! $gateway && 'manual' === $gateway_id ? new \WP_Ultimo\Gateways\Manual_Gateway() : $gateway;
		};
		$fail_once       = static function (): void {
			static $failed = false;

			if ( ! $failed) {
				$failed = true;

				throw new \RuntimeException('receipt dispatch failed');
			}
		};

		// Fail inside the receipt event, after the discount listener but before the sent flag is stored.
		add_filter('wu_get_gateway', $resolve_gateway, 10, 2);
		add_action('wu_event_payment_received', $fail_once, 1, 0);

		$payment_manager = \WP_Ultimo\Managers\Payment_Manager::get_instance();

		try {
			try {
				$payment_manager->maybe_trigger_payment_received($payment);
				$this->fail('The first dispatch should fail.');
			} catch (\RuntimeException $exception) {
				$this->assertSame('receipt dispatch failed', $exception->getMessage());
			}

			$this->assertSame(1, wu_get_discount_code($discount->get_id())->get_uses());

			$this->assertTrue($payment_manager->maybe_trigger_payment_received($payment));
		} finally {
			remove_filter('wu_get_gateway', $resolve_gateway, 10);
			remove_action('wu_event_payment_received', $fail_once, 1);
		}

		$this->assertSame(1, wu_get_discount_code($discount->get_id())->get_uses());
		$this->assertSame(1, (int) $payment->get_meta('wu_payment_received_sent'));
	}
}

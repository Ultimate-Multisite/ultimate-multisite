<?php

namespace WP_Ultimo\Managers;

use WP_Ultimo\Models\Payment;
use WP_Ultimo\Models\Customer;
use WP_Ultimo\Database\Memberships\Membership_Status;
use WP_Ultimo\Database\Payments\Payment_Status;
use WP_Ultimo\Invoices\Invoice;
use WP_UnitTestCase;

class Payment_Manager_Test extends WP_UnitTestCase {

	private static Customer $customer;
	private static Payment $payment;
	private Payment_Manager $payment_manager;

	public static function set_up_before_class() {
		parent::set_up_before_class();

		self::$customer = wu_create_customer(
			[
				'username' => 'invoicetest',
				'email'    => 'invoicetest@example.com',
				'password' => 'password123',
			]
		);

		$product = wu_create_product(
			[
				'name'         => 'Test Plan',
				'slug'         => 'test-plan-' . wp_generate_uuid4(),
				'pricing_type' => 'paid',
				'amount'       => 100,
				'currency'     => 'USD',
				'recurring'    => false,
				'type'         => 'plan',
			]
		);

		$membership = wu_create_membership(
			[
				'customer_id' => self::$customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => Membership_Status::ACTIVE,
			]
		);

		self::$payment = wu_create_payment(
			[
				'customer_id'   => self::$customer->get_id(),
				'membership_id' => $membership->get_id(),
				'product_id'    => $product->get_id(),
				'currency'      => 'USD',
				'subtotal'      => 100.00,
				'total'         => 100.00,
				'status'        => Payment_Status::COMPLETED,
				'gateway'       => 'manual',
			]
		);
	}

	public function set_up() {
		parent::set_up();
		$this->payment_manager = Payment_Manager::get_instance();
		self::$payment->delete_meta('wu_payment_received_sent');
		self::$payment->delete_meta('wu_payment_received_pending');
	}

	/**
	 * Test invoice_viewer method with non-existent payment reference.
	 */
	public function test_invoice_viewer_with_nonexistent_payment(): void {
		$_REQUEST['action']    = 'invoice';
		$_REQUEST['reference'] = 'nonexistent_hash';

		$reflection = new \ReflectionClass($this->payment_manager);
		$method     = $reflection->getMethod('invoice_viewer');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$this->expectException(\WPDieException::class);
		$this->expectExceptionMessage('This invoice does not exist.');

		$method->invoke($this->payment_manager);

		unset($_REQUEST['action'], $_REQUEST['reference']);
	}

	/**
	 * Test invoice_viewer denies access to unauthorized users.
	 */
	public function test_invoice_viewer_with_unauthorized_user(): void {
		$_REQUEST['action']    = 'invoice';
		$_REQUEST['reference'] = self::$payment->get_hash();

		// Switch to a non-admin user with no customer record.
		$user_id = self::factory()->user->create(['role' => 'subscriber']);
		wp_set_current_user($user_id);

		$reflection = new \ReflectionClass($this->payment_manager);
		$method     = $reflection->getMethod('invoice_viewer');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		$this->expectException(\WPDieException::class);
		$this->expectExceptionMessage('You do not have permissions to access this file.');

		$method->invoke($this->payment_manager);

		unset($_REQUEST['action'], $_REQUEST['reference']);
	}

	/**
	 * Test invoice_viewer method with missing action parameter.
	 */
	public function test_invoice_viewer_with_missing_action(): void {
		$_REQUEST['reference'] = self::$payment->get_hash();

		$reflection = new \ReflectionClass($this->payment_manager);
		$method     = $reflection->getMethod('invoice_viewer');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		ob_start();
		$method->invoke($this->payment_manager);
		$output = ob_get_clean();

		$this->assertEmpty($output, 'Method should return early when action parameter is missing');

		unset($_REQUEST['reference']);
	}

	/**
	 * Test invoice_viewer method with missing reference parameter.
	 */
	public function test_invoice_viewer_with_missing_reference(): void {
		$_REQUEST['action'] = 'invoice';

		$reflection = new \ReflectionClass($this->payment_manager);
		$method     = $reflection->getMethod('invoice_viewer');

		if (PHP_VERSION_ID < 80100) {
			$method->setAccessible(true);
		}

		ob_start();
		$method->invoke($this->payment_manager);
		$output = ob_get_clean();

		$this->assertEmpty($output, 'Method should return early when reference parameter is missing');

		unset($_REQUEST['action']);
	}

	public function test_maybe_trigger_payment_received_only_dispatches_once(): void {

		self::$payment->update_meta('wu_payment_received_pending', 1);

		$dispatch_count = 0;
		$count_dispatch = static function () use (&$dispatch_count): void {
			++$dispatch_count;
		};

		add_action('wu_gateway_payment_processed', $count_dispatch, PHP_INT_MAX, 0);

		try {
			$this->assertTrue($this->payment_manager->maybe_trigger_payment_received(self::$payment));
			$this->assertFalse($this->payment_manager->maybe_trigger_payment_received(self::$payment));
		} finally {
			remove_action('wu_gateway_payment_processed', $count_dispatch, PHP_INT_MAX);
		}

		$this->assertSame(1, $dispatch_count);
		$this->assertSame(1, (int) self::$payment->get_meta('wu_payment_received_sent'));
		$this->assertFalse(self::$payment->get_meta('wu_payment_received_pending'));
	}

	public function test_maybe_trigger_payment_received_remains_retryable_after_failure(): void {

		self::$payment->update_meta('wu_payment_received_pending', 1);

		$fail_dispatch = static function (): void {
			throw new \RuntimeException('payment received dispatch failed');
		};

		add_action('wu_gateway_payment_processed', $fail_dispatch, 1, 0);

		try {
			$this->payment_manager->maybe_trigger_payment_received(self::$payment);
			$this->fail('The dispatch failure should be propagated.');
		} catch (\RuntimeException $exception) {
			$this->assertSame('payment received dispatch failed', $exception->getMessage());
		} finally {
			remove_action('wu_gateway_payment_processed', $fail_dispatch, 1);
		}

		$this->assertFalse(self::$payment->get_meta('wu_payment_received_sent'));
		$this->assertSame(1, (int) self::$payment->get_meta('wu_payment_received_pending'));
		$this->assertTrue($this->payment_manager->maybe_trigger_payment_received(self::$payment));
		$this->assertFalse(self::$payment->get_meta('wu_payment_received_pending'));
	}

	public function test_maybe_trigger_payment_received_rejects_reentrant_dispatch(): void {

		$reentrant_result = null;
		$reenter_dispatch = function () use (&$reentrant_result): void {
			$reentrant_result = $this->payment_manager->maybe_trigger_payment_received(self::$payment);
		};

		add_action('wu_gateway_payment_processed', $reenter_dispatch, 1, 0);

		try {
			$this->assertTrue($this->payment_manager->maybe_trigger_payment_received(self::$payment));
		} finally {
			remove_action('wu_gateway_payment_processed', $reenter_dispatch, 1);
		}

		$this->assertFalse($reentrant_result);
	}

	public static function tear_down_after_class() {

		self::$payment->delete();
		self::$customer->delete();

		parent::tear_down_after_class();
	}
}

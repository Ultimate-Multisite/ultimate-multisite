<?php

namespace WP_Ultimo\Gateways;

/**
 * Tests for the Manual_Gateway class.
 */
class Manual_Gateway_Test extends \WP_UnitTestCase {

	/**
	 * Get a fresh Manual_Gateway instance.
	 *
	 * @return Manual_Gateway
	 */
	private function get_gateway() {

		return new Manual_Gateway();
	}

	/**
	 * Test gateway ID.
	 */
	public function test_get_id() {

		$gateway = $this->get_gateway();

		$this->assertSame('manual', $gateway->get_id());
	}

	/**
	 * Test does not support recurring.
	 */
	public function test_supports_recurring() {

		$gateway = $this->get_gateway();

		$this->assertFalse($gateway->supports_recurring());
	}

	/**
	 * Test does not support free trials.
	 */
	public function test_supports_free_trials() {

		$gateway = $this->get_gateway();

		$this->assertFalse($gateway->supports_free_trials());
	}

	/**
	 * Test does not support amount update.
	 */
	public function test_supports_amount_update() {

		$gateway = $this->get_gateway();

		$this->assertFalse($gateway->supports_amount_update());
	}

	/**
	 * Test hooks registers action.
	 */
	public function test_hooks() {

		$gateway = $this->get_gateway();

		$gateway->hooks();

		$this->assertNotFalse(has_action('wu_thank_you_before_info_blocks', [$gateway, 'add_payment_instructions_block']));
	}

	/**
	 * Test settings registers fields.
	 */
	public function test_settings() {

		$gateway = $this->get_gateway();

		// Should not throw
		$gateway->settings();

		$this->assertTrue(true);
	}

	/**
	 * Test fields returns HTML string.
	 */
	public function test_fields() {

		$gateway = $this->get_gateway();

		$result = $gateway->fields();

		$this->assertIsString($result);
		$this->assertStringContainsString('v-if="!order.has_trial"', $result);
		$this->assertStringContainsString('wu-bg-yellow-200', $result);
	}

	/**
	 * Test process_membership_update returns true.
	 */
	public function test_process_membership_update() {

		$gateway = $this->get_gateway();

		$membership = wu_create_membership([
			'customer_id' => 0,
			'status'      => 'active',
		]);

		$customer = wu_create_customer([
			'user_id'            => self::factory()->user->create(),
			'email_verification' => 'none',
		]);

		$result = $gateway->process_membership_update($membership, $customer);

		$this->assertTrue($result);
	}

	/**
	 * Test get_amount_update_message for customer.
	 */
	public function test_get_amount_update_message_customer() {

		$gateway = $this->get_gateway();

		$message = $gateway->get_amount_update_message(true);

		$this->assertIsString($message);
		$this->assertStringContainsString('updated invoice', $message);
	}

	/**
	 * Test get_amount_update_message for admin.
	 */
	public function test_get_amount_update_message_admin() {

		$gateway = $this->get_gateway();

		$message = $gateway->get_amount_update_message(false);

		$this->assertIsString($message);
		$this->assertStringContainsString('customer', $message);
	}

	/**
	 * Test process_cancellation returns null.
	 */
	public function test_process_cancellation() {

		$gateway = $this->get_gateway();

		$result = $gateway->process_cancellation(null, null);

		$this->assertNull($result);
	}

	/**
	 * Test get_all_ids includes manual.
	 */
	public function test_get_all_ids() {

		$gateway = $this->get_gateway();

		$ids = $gateway->get_all_ids();

		$this->assertContains('manual', $ids);
	}

	/**
	 * Test get_backwards_compatibility_v1_id.
	 */
	public function test_get_backwards_compatibility_v1_id() {

		$gateway = $this->get_gateway();

		$this->assertFalse($gateway->get_backwards_compatibility_v1_id());
	}

	/**
	 * Test get_payment_method_display returns null.
	 */
	public function test_get_payment_method_display() {

		$gateway = $this->get_gateway();

		$this->assertNull($gateway->get_payment_method_display(null));
	}

	/**
	 * Test get_change_payment_method_url returns null.
	 */
	public function test_get_change_payment_method_url() {

		$gateway = $this->get_gateway();

		$this->assertNull($gateway->get_change_payment_method_url(null));
	}

	/**
	 * Test get_public_title returns string.
	 */
	public function test_get_public_title() {

		$gateway = $this->get_gateway();

		$title = $gateway->get_public_title();

		$this->assertIsString($title);
	}

	/**
	 * Test get_webhook_listener_url.
	 */
	public function test_get_webhook_listener_url() {

		$gateway = $this->get_gateway();

		$url = $gateway->get_webhook_listener_url();

		$this->assertIsString($url);
		$this->assertStringContainsString('wu-gateway=manual', $url);
	}

	/**
	 * Test set_payment.
	 */
	public function test_set_payment() {

		$gateway = $this->get_gateway();

		$payment = wu_create_payment([
			'membership_id' => 0,
			'customer_id'   => 0,
			'status'        => 'pending',
		]);

		$gateway->set_payment($payment);

		// Access via reflection
		$ref = new \ReflectionProperty($gateway, 'payment');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$this->assertSame($payment, $ref->getValue($gateway));
	}

	/**
	 * Test set_membership.
	 */
	public function test_set_membership() {

		$gateway = $this->get_gateway();

		$membership = wu_create_membership([
			'customer_id' => 0,
			'status'      => 'active',
		]);

		$gateway->set_membership($membership);

		$ref = new \ReflectionProperty($gateway, 'membership');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$this->assertSame($membership, $ref->getValue($gateway));
	}

	/**
	 * Test set_customer.
	 */
	public function test_set_customer() {

		$gateway = $this->get_gateway();

		$customer = wu_create_customer([
			'user_id'            => self::factory()->user->create(),
			'email_verification' => 'none',
		]);

		$gateway->set_customer($customer);

		$ref = new \ReflectionProperty($gateway, 'customer');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$this->assertSame($customer, $ref->getValue($gateway));
	}

	/**
	 * Test save_swap and get_saved_swap.
	 */
	public function test_save_and_get_swap() {

		$gateway = $this->get_gateway();

		// Use a simple object as a stand-in for a cart
		$cart       = new \stdClass();
		$cart->test = 'value';

		$swap_id = $gateway->save_swap($cart);

		$this->assertIsString($swap_id);
		$this->assertStringStartsWith('wu_swap_', $swap_id);

		$retrieved = $gateway->get_saved_swap($swap_id);

		$this->assertEquals($cart, $retrieved);
	}

	/**
	 * Test get_saved_swap returns false for non-existent.
	 */
	public function test_get_saved_swap_nonexistent() {

		$gateway = $this->get_gateway();

		$result = $gateway->get_saved_swap('wu_swap_nonexistent');

		$this->assertFalse($result);
	}

	/**
	 * Test set_order with null.
	 */
	public function test_set_order_null() {

		$gateway = $this->get_gateway();

		$gateway->set_order(null);

		$ref = new \ReflectionProperty($gateway, 'order');

		if (PHP_VERSION_ID < 80100) {
			$ref->setAccessible(true);
		}

		$this->assertNull($ref->getValue($gateway));
	}
}

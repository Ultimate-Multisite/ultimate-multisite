<?php
/**
 * Tests for the Billable interface.
 *
 * @package WP_Ultimo\Tests
 * @subpackage Models\Interfaces
 * @since 2.0.0
 */

namespace WP_Ultimo\Models\Interfaces;

use WP_UnitTestCase;
use WP_Ultimo\Models\Customer;
use WP_Ultimo\Objects\Billing_Address;

/**
 * Test class for the Billable interface (interface-billable.php).
 *
 * The interface is verified through Customer, which implements it via Trait_Billable.
 */
class Interface_Billable_Test extends WP_UnitTestCase {

	/**
	 * Test that Customer implements the Billable interface.
	 */
	public function test_customer_implements_billable_interface(): void {

		$customer = new Customer();

		$this->assertInstanceOf(Billable::class, $customer);
	}

	/**
	 * Test that Billable interface declares get_default_billing_address.
	 */
	public function test_interface_declares_get_default_billing_address(): void {

		$this->assertTrue(method_exists(Billable::class, 'get_default_billing_address'));
	}

	/**
	 * Test that Billable interface declares get_billing_address.
	 */
	public function test_interface_declares_get_billing_address(): void {

		$this->assertTrue(method_exists(Billable::class, 'get_billing_address'));
	}

	/**
	 * Test that Billable interface declares set_billing_address.
	 */
	public function test_interface_declares_set_billing_address(): void {

		$this->assertTrue(method_exists(Billable::class, 'set_billing_address'));
	}

	/**
	 * Test get_billing_address returns a Billing_Address instance.
	 */
	public function test_get_billing_address_returns_billing_address_instance(): void {

		$user_id  = self::factory()->user->create(['user_email' => 'billable@example.com']);
		$customer = new Customer();
		$customer->set_user_id($user_id);

		$billing_address = $customer->get_billing_address();

		$this->assertInstanceOf(Billing_Address::class, $billing_address);
	}

	/**
	 * Test set_billing_address accepts array and converts to Billing_Address.
	 */
	public function test_set_billing_address_accepts_array(): void {

		$customer = new Customer();

		$customer->set_billing_address([
			'billing_email'   => 'test@example.com',
			'billing_country' => 'US',
		]);

		$billing_address = $customer->get_billing_address();

		$this->assertInstanceOf(Billing_Address::class, $billing_address);
	}

	/**
	 * Test set_billing_address accepts Billing_Address instance.
	 */
	public function test_set_billing_address_accepts_billing_address_instance(): void {

		$customer = new Customer();

		$billing_address = new Billing_Address([
			'billing_email'   => 'direct@example.com',
			'billing_country' => 'CA',
		]);

		$customer->set_billing_address($billing_address);

		$result = $customer->get_billing_address();

		$this->assertInstanceOf(Billing_Address::class, $result);
		$this->assertSame($billing_address, $result);
	}

	/**
	 * Test get_default_billing_address returns Billing_Address.
	 */
	public function test_get_default_billing_address_returns_billing_address(): void {

		$user_id  = self::factory()->user->create([
			'user_email'   => 'default@example.com',
			'display_name' => 'Default User',
		]);
		$customer = new Customer();
		$customer->set_user_id($user_id);

		$result = $customer->get_default_billing_address();

		$this->assertInstanceOf(Billing_Address::class, $result);
	}

	/**
	 * Test billing address is stored in meta array.
	 */
	public function test_set_billing_address_stores_in_meta(): void {

		$customer = new Customer();

		$customer->set_billing_address(['billing_email' => 'meta@example.com']);

		$this->assertArrayHasKey('wu_billing_address', $customer->meta);
		$this->assertInstanceOf(Billing_Address::class, $customer->meta['wu_billing_address']);
	}
}

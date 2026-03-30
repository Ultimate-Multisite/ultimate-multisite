<?php
/**
 * Tests for the Billable trait via the Customer model.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Models\Traits;

use WP_UnitTestCase;
use WP_Ultimo\Models\Customer;
use WP_Ultimo\Objects\Billing_Address;

/**
 * Test class for the Billable trait.
 */
class Billable_Trait_Test extends WP_UnitTestCase {

	/**
	 * Create a saved customer for tests that need persistence.
	 */
	protected function create_saved_customer(): Customer {

		return wu_create_customer([
			'user_id' => self::factory()->user->create(),
		]);
	}

	public function test_get_billing_address_returns_billing_address_instance(): void {

		$customer = new Customer();

		$address = $customer->get_billing_address();

		$this->assertInstanceOf(Billing_Address::class, $address);
	}

	public function test_set_billing_address_with_array(): void {

		$customer = new Customer();
		$customer->set_billing_address([
			'billing_country'  => 'US',
			'billing_state'    => 'CA',
			'billing_city'     => 'San Francisco',
			'billing_zip_code' => '94102',
		]);

		$address = $customer->get_billing_address();

		$this->assertInstanceOf(Billing_Address::class, $address);
		$this->assertSame('US', $address->billing_country);
	}

	public function test_set_billing_address_with_object(): void {

		$customer = new Customer();
		$addr     = new Billing_Address([
			'billing_country' => 'GB',
		]);

		$customer->set_billing_address($addr);

		$this->assertSame('GB', $customer->get_billing_address()->billing_country);
	}

	public function test_billing_address_stored_in_meta(): void {

		$customer = new Customer();
		$customer->set_billing_address(['billing_country' => 'DE']);

		$this->assertArrayHasKey('wu_billing_address', $customer->meta);
		$this->assertInstanceOf(Billing_Address::class, $customer->meta['wu_billing_address']);
	}

	public function test_get_default_billing_address_returns_billing_address(): void {

		$customer = new Customer();

		$default = $customer->get_default_billing_address();

		$this->assertInstanceOf(Billing_Address::class, $default);
	}

	public function tearDown(): void {

		$customers = Customer::get_all();

		if ($customers) {
			foreach ($customers as $customer) {
				if ($customer->get_id()) {
					$customer->delete();
				}
			}
		}

		parent::tearDown();
	}
}

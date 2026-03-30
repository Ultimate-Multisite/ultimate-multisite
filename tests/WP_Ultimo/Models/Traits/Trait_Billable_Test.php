<?php
/**
 * Tests for the Billable trait.
 *
 * @package WP_Ultimo\Tests
 * @subpackage Models\Traits
 * @since 2.0.0
 */

namespace WP_Ultimo\Models\Traits;

use WP_UnitTestCase;
use WP_Ultimo\Models\Customer;
use WP_Ultimo\Objects\Billing_Address;

/**
 * Test class for the Billable trait (trait-billable.php).
 *
 * Uses Customer as the concrete class that uses this trait.
 */
class Trait_Billable_Test extends WP_UnitTestCase {

	/**
	 * Customer instance.
	 *
	 * @var Customer
	 */
	protected $customer;

	/**
	 * Set up test environment.
	 */
	public function setUp(): void {

		parent::setUp();

		$this->customer = new Customer();
	}

	/**
	 * Test Customer uses the Billable trait.
	 */
	public function test_customer_uses_billable_trait(): void {

		$traits = class_uses_recursive(Customer::class);

		$this->assertContains(Billable::class, $traits);
	}

	/**
	 * Test get_billing_address returns Billing_Address when not set (falls back to default).
	 */
	public function test_get_billing_address_falls_back_to_default(): void {

		$user_id = self::factory()->user->create(['user_email' => 'trait@example.com']);
		$this->customer->set_user_id($user_id);

		$result = $this->customer->get_billing_address();

		$this->assertInstanceOf(Billing_Address::class, $result);
	}

	/**
	 * Test set_billing_address with array creates Billing_Address.
	 */
	public function test_set_billing_address_with_array_creates_billing_address(): void {

		$this->customer->set_billing_address([
			'billing_email'   => 'array@example.com',
			'billing_country' => 'US',
		]);

		$result = $this->customer->get_billing_address();

		$this->assertInstanceOf(Billing_Address::class, $result);
	}

	/**
	 * Test set_billing_address with Billing_Address instance stores it directly.
	 */
	public function test_set_billing_address_with_instance_stores_directly(): void {

		$address = new Billing_Address([
			'billing_email'   => 'instance@example.com',
			'billing_country' => 'GB',
		]);

		$this->customer->set_billing_address($address);

		$result = $this->customer->get_billing_address();

		$this->assertSame($address, $result);
	}

	/**
	 * Test set_billing_address stores in meta array.
	 */
	public function test_set_billing_address_stores_in_meta_array(): void {

		$this->customer->set_billing_address(['billing_email' => 'meta@example.com']);

		$this->assertArrayHasKey('wu_billing_address', $this->customer->meta);
	}

	/**
	 * Test get_billing_address caches result after first call.
	 */
	public function test_get_billing_address_caches_result(): void {

		$user_id = self::factory()->user->create(['user_email' => 'cache@example.com']);
		$this->customer->set_user_id($user_id);

		$first  = $this->customer->get_billing_address();
		$second = $this->customer->get_billing_address();

		$this->assertSame($first, $second);
	}

	/**
	 * Test billing address data is preserved after set.
	 */
	public function test_billing_address_data_preserved_after_set(): void {

		$this->customer->set_billing_address([
			'billing_email'   => 'preserved@example.com',
			'billing_country' => 'DE',
			'company_name'    => 'Test GmbH',
		]);

		$address = $this->customer->get_billing_address();
		$array   = $address->to_array();

		$this->assertSame('preserved@example.com', $array['billing_email']);
		$this->assertSame('DE', $array['billing_country']);
		$this->assertSame('Test GmbH', $array['company_name']);
	}
}

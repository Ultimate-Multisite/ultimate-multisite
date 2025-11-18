<?php

namespace WP_Ultimo\Models;

use WP_Ultimo\Helpers\Hash;
use WP_UnitTestCase;
use WP_User;

/**
 * Test class for Customer model functionality.
 *
 * Tests customer creation, validation, key generation, and
 * other customer-related operations.
 */
class Customer_Test extends WP_UnitTestCase {

	/**
	 * Test customer creation with valid data.
	 */
	public function test_customer_creation_with_valid_data(): void {
		$user_id = self::factory()->user->create(
			[
				'user_login'   => 'testuser',
				'user_email'   => 'test@example.com',
				'display_name' => 'Test User',
			]
		);

		$customer = new Customer();
		$customer->set_user_id($user_id);
		$customer->set_type('customer');
		$customer->set_email_verification('none');
		$customer->set_date_registered('2023-01-01 00:00:00');

		$this->assertEquals($user_id, $customer->get_user_id());
		$this->assertEquals('customer', $customer->get_type());
		$this->assertEquals('none', $customer->get_email_verification());
		$this->assertEquals('2023-01-01 00:00:00', $customer->get_date_registered());
	}

	/**
	 * Test get_user returns correct WP_User object.
	 */
	public function test_get_user_returns_correct_user(): void {
		$user_id = self::factory()->user->create(
			[
				'user_login'   => 'testuser',
				'user_email'   => 'test@example.com',
				'display_name' => 'Test User',
			]
		);

		$customer = new Customer();
		$customer->set_user_id($user_id);

		$user = $customer->get_user();
		$this->assertInstanceOf(WP_User::class, $user);
		$this->assertEquals($user_id, $user->ID);
		$this->assertEquals('testuser', $user->user_login);
	}

	/**
	 * Test get_display_name returns correct display name.
	 */
	public function test_get_display_name_returns_correct_name(): void {
		$user_id = self::factory()->user->create(
			[
				'user_login'   => 'testuser',
				'user_email'   => 'test@example.com',
				'display_name' => 'Test User Display',
			]
		);

		$customer = new Customer();
		$customer->set_user_id($user_id);

		$this->assertEquals('Test User Display', $customer->get_display_name());
	}

	/**
	 * Test get_display_name returns 'User Deleted' for non-existent user.
	 */
	public function test_get_display_name_returns_user_deleted_for_invalid_user(): void {
		$customer = new Customer();
		$customer->set_user_id(99999); // Non-existent user ID

		$this->assertEquals('User Deleted', $customer->get_display_name());
	}

	/**
	 * Test get_username returns correct username.
	 */
	public function test_get_username_returns_correct_username(): void {
		$user_id = self::factory()->user->create(
			[
				'user_login' => 'testusername',
				'user_email' => 'test@example.com',
			]
		);

		$customer = new Customer();
		$customer->set_user_id($user_id);

		$this->assertEquals('testusername', $customer->get_username());
	}

	/**
	 * Test get_username returns 'none' for non-existent user.
	 */
	public function test_get_username_returns_none_for_invalid_user(): void {
		$customer = new Customer();
		$customer->set_user_id(99999);

		$this->assertEquals('none', $customer->get_username());
	}

	/**
	 * Test get_email_address returns correct email.
	 */
	public function test_get_email_address_returns_correct_email(): void {
		$user_id = self::factory()->user->create(
			[
				'user_login' => 'testuser',
				'user_email' => 'test@example.com',
			]
		);

		$customer = new Customer();
		$customer->set_user_id($user_id);

		$this->assertEquals('test@example.com', $customer->get_email_address());
	}

	/**
	 * Test get_email_address returns 'none' for non-existent user.
	 */
	public function test_get_email_address_returns_none_for_invalid_user(): void {
		$customer = new Customer();
		$customer->set_user_id(99999);

		$this->assertEquals('none', $customer->get_email_address());
	}

	/**
	 * Test email verification status setters and getters.
	 */
	public function test_email_verification_status(): void {
		$customer = new Customer();

		$customer->set_email_verification('pending');
		$this->assertEquals('pending', $customer->get_email_verification());

		$customer->set_email_verification('verified');
		$this->assertEquals('verified', $customer->get_email_verification());

		$customer->set_email_verification('none');
		$this->assertEquals('none', $customer->get_email_verification());
	}

	/**
	 * Test last login functionality.
	 */
	public function test_last_login_functionality(): void {
		$customer  = new Customer();
		$timestamp = '2023-01-15 10:30:00';

		$customer->set_last_login($timestamp);
		$this->assertEquals($timestamp, $customer->get_last_login());
	}

	/**
	 * Test VIP status functionality.
	 */
	public function test_vip_status_functionality(): void {
		$customer = new Customer();

		// Default should be false
		$this->assertFalse($customer->is_vip());

		$customer->set_vip(true);
		$this->assertTrue($customer->is_vip());

		$customer->set_vip(false);
		$this->assertFalse($customer->is_vip());
	}

	/**
	 * Test IP address functionality.
	 */
	public function test_ip_address_functionality(): void {
		$customer = new Customer();

		// Test empty IPs
		$this->assertEquals([], $customer->get_ips());
		$this->assertNull($customer->get_last_ip());

		// Test setting IPs as array
		$ips = ['192.168.1.1', '10.0.0.1'];
		$customer->set_ips($ips);
		$this->assertEquals($ips, $customer->get_ips());
		$this->assertEquals('10.0.0.1', $customer->get_last_ip());

		// Test adding new IP
		$customer->add_ip('172.16.0.1');
		$updated_ips = $customer->get_ips();
		$this->assertContains('172.16.0.1', $updated_ips);
		$this->assertEquals('172.16.0.1', $customer->get_last_ip());

		// Test adding duplicate IP (should not be added)
		$customer->add_ip('192.168.1.1');
		$this->assertEquals($updated_ips, $customer->get_ips()); // Should remain the same
	}

	/**
	 * Test extra information functionality.
	 */
	public function test_extra_information_functionality(): void {
		$customer = new Customer();

		$extra_info = [
			'company' => 'Test Company',
			'phone'   => '+1234567890',
			'notes'   => 'Test customer notes',
		];

		$customer->set_extra_information($extra_info);
		$this->assertEquals($extra_info, $customer->get_extra_information());
	}

	/**
	 * Test signup form functionality.
	 */
	public function test_signup_form_functionality(): void {
		$customer = new Customer();
		$form_id  = 'checkout-form-123';

		$customer->set_signup_form($form_id);
		$this->assertEquals($form_id, $customer->get_signup_form());
	}

	/**
	 * Test customer type functionality.
	 */
	public function test_customer_type_functionality(): void {
		$customer = new Customer();

		$customer->set_type('customer');
		$this->assertEquals('customer', $customer->get_type());
	}

	/**
	 * Test is_online functionality with mock data.
	 */
	public function test_is_online_functionality(): void {
		$customer = new Customer();

		// Test with no login (default value)
		$customer->set_last_login('0000-00-00 00:00:00');
		$this->assertFalse($customer->is_online());

		// Test with recent login (within 3 minutes)
		$recent_time = gmdate('Y-m-d H:i:s', strtotime('-2 minutes'));
		$customer->set_last_login($recent_time);
		$this->assertTrue($customer->is_online());

		// Test with old login (more than 3 minutes ago)
		$old_time = gmdate('Y-m-d H:i:s', strtotime('-5 minutes'));
		$customer->set_last_login($old_time);
		$this->assertFalse($customer->is_online());
	}

	/**
	 * Test validation rules.
	 */
	public function test_validation_rules(): void {
		$customer = new Customer();
		$rules    = $customer->validation_rules();

		// Check that required validation rules exist
		$this->assertArrayHasKey('user_id', $rules);
		$this->assertArrayHasKey('email_verification', $rules);
		$this->assertArrayHasKey('type', $rules);

		// Check specific rule patterns
		$this->assertStringContainsString('required', $rules['user_id']);
		$this->assertStringContainsString('integer', $rules['user_id']);
		$this->assertStringContainsString('unique', $rules['user_id']);
		$this->assertStringContainsString('in:none,pending,verified', $rules['email_verification']);
		$this->assertStringContainsString('in:customer', $rules['type']);
	}

	/**
	 * Test has_trialed functionality.
	 */
	public function test_has_trialed_functionality(): void {
		$customer = new Customer();

		// Test setting trialed status
		$customer->set_has_trialed(true);
		$this->assertTrue($customer->has_trialed());

		$customer->set_has_trialed(false);
		$this->assertFalse($customer->has_trialed());
	}

	/**
	 * Test verification key functionality.
	 */
	public function test_verification_key_functionality(): void {
		$user_id = self::factory()->user->create(
			[
				'user_login' => 'testuser',
				'user_email' => 'test@example.com',
			]
		);

		$customer = new Customer();
		$customer->set_user_id($user_id);
		$customer->set_type('customer');
		$customer->set_email_verification('none');

		// Initially should have no verification key
		$this->assertFalse($customer->get_verification_key());

		$customer->save();

		// After generation, should have a key
		$customer->generate_verification_key();

		$this->assertGreaterThanOrEqual(Hash::LENGTH, strlen($customer->get_verification_key()));

		// Test that verification key was generated by c
		// Test disabling verification key
		$disable_result = $customer->disable_verification_key();
		$this->assertTrue((bool) $disable_result);
		// Just check that the key was disabled
		$customer->disable_verification_key();
		$this->assertEmpty($customer->get_verification_key());
	}

	/**
	 * Test default billing address creation.
	 */
	public function test_default_billing_address_creation(): void {
		$user_id = self::factory()->user->create(
			[
				'user_login'   => 'testuser',
				'user_email'   => 'billing@example.com',
				'display_name' => 'Billing User',
			]
		);

		$customer = new Customer();
		$customer->set_user_id($user_id);

		$billing_address = $customer->get_default_billing_address();
		$this->assertInstanceOf(\WP_Ultimo\Objects\Billing_Address::class, $billing_address);

		$address_array = $billing_address->to_array();
		$this->assertEquals('Billing User', $address_array['company_name']);
		$this->assertEquals('billing@example.com', $address_array['billing_email']);
	}

	/**
	 * Test to_search_results method.
	 */
	public function test_to_search_results(): void {
		$user_id = self::factory()->user->create(
			[
				'user_login'   => 'searchuser',
				'user_email'   => 'search@example.com',
				'display_name' => 'Search User',
			]
		);

		$customer = new Customer();
		$customer->set_user_id($user_id);

		$search_results = $customer->to_search_results();

		$this->assertIsArray($search_results);
		$this->assertArrayHasKey('billing_address_data', $search_results);
		$this->assertArrayHasKey('billing_address', $search_results);
		$this->assertArrayHasKey('user_login', $search_results);
		$this->assertArrayHasKey('user_email', $search_results);
		$this->assertEquals('searchuser', $search_results['user_login']);
		$this->assertEquals('search@example.com', $search_results['user_email']);
	}

	/**
	 * Tear down test environment.
	 */
	public function tearDown(): void {
		// Clean up created customers
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

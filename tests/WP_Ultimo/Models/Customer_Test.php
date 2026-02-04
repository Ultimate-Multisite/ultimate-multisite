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

	// ---------------------------------------------------------------
	// CRUD via helper functions
	// ---------------------------------------------------------------

	/**
	 * Helper: create a WP user and return its ID.
	 *
	 * @param string $login User login.
	 * @param string $email User email.
	 * @return int
	 */
	private function make_user(string $login = '', string $email = ''): int {

		$login = $login ?: 'user_' . wp_generate_uuid4();
		$email = $email ?: $login . '@example.com';

		return self::factory()->user->create(
			[
				'user_login' => $login,
				'user_email' => $email,
			]
		);
	}

	/**
	 * Helper: create a saved customer via wu_create_customer.
	 *
	 * @param array $overrides Overrides to pass.
	 * @return Customer
	 */
	private function make_customer(array $overrides = []): Customer {

		$user_id = $this->make_user();

		$defaults = [
			'user_id'            => $user_id,
			'email_verification' => 'none',
		];

		$customer = wu_create_customer(array_merge($defaults, $overrides));

		$this->assertNotWPError($customer);
		$this->assertInstanceOf(Customer::class, $customer);

		return $customer;
	}

	/**
	 * Test wu_create_customer creates and persists a customer.
	 */
	public function test_wu_create_customer_persists(): void {

		$customer = $this->make_customer();

		$this->assertGreaterThan(0, $customer->get_id());
		$this->assertEquals('customer', $customer->get_type());
	}

	/**
	 * Test wu_get_customer retrieves a persisted customer by ID.
	 */
	public function test_wu_get_customer_by_id(): void {

		$customer = $this->make_customer();

		$fetched = wu_get_customer($customer->get_id());

		$this->assertInstanceOf(Customer::class, $fetched);
		$this->assertEquals($customer->get_id(), $fetched->get_id());
		$this->assertEquals($customer->get_user_id(), $fetched->get_user_id());
	}

	/**
	 * Test wu_get_customer returns false for non-existent ID.
	 */
	public function test_wu_get_customer_returns_false_for_missing(): void {

		$this->assertFalse(wu_get_customer(999999));
	}

	/**
	 * Test wu_get_customer_by_user_id retrieves customer by WP user ID.
	 */
	public function test_wu_get_customer_by_user_id(): void {

		$customer = $this->make_customer();

		$fetched = wu_get_customer_by_user_id($customer->get_user_id());

		$this->assertInstanceOf(Customer::class, $fetched);
		$this->assertEquals($customer->get_id(), $fetched->get_id());
	}

	/**
	 * Test wu_get_customer_by_user_id returns false for unknown user.
	 */
	public function test_wu_get_customer_by_user_id_returns_false_for_unknown(): void {

		$this->assertFalse(wu_get_customer_by_user_id(999999));
	}

	/**
	 * Test customer deletion.
	 */
	public function test_customer_delete(): void {

		$customer = $this->make_customer();
		$id       = $customer->get_id();

		$this->assertInstanceOf(Customer::class, wu_get_customer($id));

		$result = $customer->delete();

		$this->assertNotEmpty($result);
		$this->assertFalse(wu_get_customer($id));
	}

	/**
	 * Test customer update after save.
	 */
	public function test_customer_update(): void {

		$customer = $this->make_customer(['email_verification' => 'none']);

		$customer->set_email_verification('verified');
		$customer->save();

		$fetched = wu_get_customer($customer->get_id());

		$this->assertEquals('verified', $fetched->get_email_verification());
	}

	/**
	 * Test creating customer with existing email reuses WP user.
	 */
	public function test_wu_create_customer_with_existing_user(): void {

		$user_id = $this->make_user('existinguser', 'existing@example.com');

		$customer = wu_create_customer(
			[
				'user_id'            => $user_id,
				'email_verification' => 'none',
			]
		);

		$this->assertNotWPError($customer);
		$this->assertEquals($user_id, $customer->get_user_id());
	}

	// ---------------------------------------------------------------
	// Getter / Setter pairs
	// ---------------------------------------------------------------

	/**
	 * Test user_id getter returns absint.
	 */
	public function test_get_user_id_returns_absint(): void {

		$customer = new Customer();
		$customer->set_user_id(-5);

		$this->assertEquals(5, $customer->get_user_id());
	}

	/**
	 * Test user_id getter for zero when unset.
	 */
	public function test_get_user_id_defaults_to_zero(): void {

		$customer = new Customer();

		$this->assertEquals(0, $customer->get_user_id());
	}

	/**
	 * Test date_registered getter and setter.
	 */
	public function test_date_registered_getter_setter(): void {

		$customer = new Customer();
		$date     = '2024-06-15 12:00:00';

		$customer->set_date_registered($date);

		$this->assertEquals($date, $customer->get_date_registered());
	}

	/**
	 * Test last_login getter and setter with various dates.
	 */
	public function test_last_login_various_dates(): void {

		$customer = new Customer();

		$customer->set_last_login('2024-12-31 23:59:59');
		$this->assertEquals('2024-12-31 23:59:59', $customer->get_last_login());

		$customer->set_last_login('0000-00-00 00:00:00');
		$this->assertEquals('0000-00-00 00:00:00', $customer->get_last_login());
	}

	/**
	 * Test type getter and setter.
	 */
	public function test_type_getter_setter(): void {

		$customer = new Customer();

		$customer->set_type('customer');
		$this->assertEquals('customer', $customer->get_type());
	}

	/**
	 * Test VIP getter returns boolean.
	 */
	public function test_vip_returns_boolean_true(): void {

		$customer = new Customer();
		$customer->set_vip(1);

		$this->assertTrue($customer->is_vip());
		$this->assertIsBool($customer->is_vip());
	}

	/**
	 * Test VIP setter with falsy value.
	 */
	public function test_vip_with_falsy_value(): void {

		$customer = new Customer();
		$customer->set_vip(0);

		$this->assertFalse($customer->is_vip());
	}

	/**
	 * Test signup form default value.
	 */
	public function test_signup_form_default(): void {

		$customer = new Customer();

		$this->assertEquals('by-admin', $customer->get_signup_form());
	}

	/**
	 * Test signup form setter and getter.
	 */
	public function test_signup_form_setter_getter(): void {

		$customer = new Customer();
		$customer->set_signup_form('my-custom-form');

		$this->assertEquals('my-custom-form', $customer->get_signup_form());
	}

	/**
	 * Test network_id getter and setter.
	 */
	public function test_network_id_getter_setter(): void {

		$customer = new Customer();

		$this->assertNull($customer->get_network_id());

		$customer->set_network_id(5);
		$this->assertEquals(5, $customer->get_network_id());

		$customer->set_network_id(null);
		$this->assertNull($customer->get_network_id());
	}

	/**
	 * Test network_id getter returns absint for positive values.
	 */
	public function test_network_id_returns_absint(): void {

		$customer = new Customer();
		$customer->set_network_id(42);

		$this->assertSame(42, $customer->get_network_id());
	}

	/**
	 * Test network_id zero is treated as null.
	 */
	public function test_network_id_zero_is_null(): void {

		$customer = new Customer();
		$customer->set_network_id(0);

		$this->assertNull($customer->get_network_id());
	}

	// ---------------------------------------------------------------
	// IP address edge cases
	// ---------------------------------------------------------------

	/**
	 * Test set_ips with serialized string.
	 */
	public function test_set_ips_with_serialized_string(): void {

		$customer = new Customer();
		$ips      = ['1.1.1.1', '2.2.2.2'];

		$customer->set_ips(maybe_serialize($ips));

		$this->assertEquals($ips, $customer->get_ips());
	}

	/**
	 * Test add_ip sanitizes input.
	 */
	public function test_add_ip_sanitizes_input(): void {

		$customer = new Customer();
		$customer->set_ips([]);
		$customer->add_ip('<script>alert("xss")</script>');

		$ips = $customer->get_ips();

		$this->assertCount(1, $ips);
		$this->assertStringNotContainsString('<script>', $ips[0]);
	}

	/**
	 * Test get_last_ip returns last element.
	 */
	public function test_get_last_ip_returns_last(): void {

		$customer = new Customer();
		$customer->set_ips(['10.0.0.1', '10.0.0.2', '10.0.0.3']);

		$this->assertEquals('10.0.0.3', $customer->get_last_ip());
	}

	/**
	 * Test add_ip to empty list.
	 */
	public function test_add_ip_to_empty_list(): void {

		$customer = new Customer();
		$customer->set_ips([]);
		$customer->add_ip('8.8.8.8');

		$this->assertEquals(['8.8.8.8'], $customer->get_ips());
	}

	/**
	 * Test multiple add_ip calls accumulate.
	 */
	public function test_multiple_add_ip_calls(): void {

		$customer = new Customer();
		$customer->set_ips([]);

		$customer->add_ip('1.1.1.1');
		$customer->add_ip('2.2.2.2');
		$customer->add_ip('3.3.3.3');

		$this->assertCount(3, $customer->get_ips());
		$this->assertEquals(['1.1.1.1', '2.2.2.2', '3.3.3.3'], $customer->get_ips());
	}

	// ---------------------------------------------------------------
	// Email verification
	// ---------------------------------------------------------------

	/**
	 * Test email verification transitions.
	 */
	public function test_email_verification_transitions(): void {

		$customer = $this->make_customer(['email_verification' => 'none']);

		$this->assertEquals('none', $customer->get_email_verification());

		$customer->set_email_verification('pending');
		$customer->save();

		$fetched = wu_get_customer($customer->get_id());
		$this->assertEquals('pending', $fetched->get_email_verification());

		$customer->set_email_verification('verified');
		$customer->save();

		$fetched = wu_get_customer($customer->get_id());
		$this->assertEquals('verified', $fetched->get_email_verification());
	}

	/**
	 * Test email verification persisted via wu_create_customer.
	 */
	public function test_email_verification_persisted_on_create(): void {

		$customer = $this->make_customer(['email_verification' => 'pending']);

		$fetched = wu_get_customer($customer->get_id());

		$this->assertEquals('pending', $fetched->get_email_verification());
	}

	// ---------------------------------------------------------------
	// VIP status persistence
	// ---------------------------------------------------------------

	/**
	 * Test VIP status persists through save/load cycle.
	 */
	public function test_vip_status_persists(): void {

		$customer = $this->make_customer();

		$customer->set_vip(true);
		$customer->save();

		$fetched = wu_get_customer($customer->get_id());

		$this->assertTrue($fetched->is_vip());
	}

	/**
	 * Test VIP status can be toggled off.
	 */
	public function test_vip_status_toggle_off(): void {

		$customer = $this->make_customer();
		$customer->set_vip(true);
		$customer->save();

		$customer->set_vip(false);
		$customer->save();

		$fetched = wu_get_customer($customer->get_id());

		$this->assertFalse($fetched->is_vip());
	}

	// ---------------------------------------------------------------
	// is_online edge cases
	// ---------------------------------------------------------------

	/**
	 * Test is_online returns true when last login is exactly now.
	 */
	public function test_is_online_at_exact_now(): void {

		$customer = new Customer();
		$customer->set_last_login(gmdate('Y-m-d H:i:s'));

		$this->assertTrue($customer->is_online());
	}

	/**
	 * Test is_online returns false when last login is an hour ago.
	 */
	public function test_is_online_one_hour_ago(): void {

		$customer = new Customer();
		$customer->set_last_login(gmdate('Y-m-d H:i:s', strtotime('-1 hour')));

		$this->assertFalse($customer->is_online());
	}

	/**
	 * Test is_online returns true at exactly 3 minutes.
	 */
	public function test_is_online_at_boundary(): void {

		$customer = new Customer();
		$customer->set_last_login(gmdate('Y-m-d H:i:s', strtotime('-3 minutes')));

		$this->assertTrue($customer->is_online());
	}

	/**
	 * Test is_online returns false at 4 minutes.
	 */
	public function test_is_online_past_boundary(): void {

		$customer = new Customer();
		$customer->set_last_login(gmdate('Y-m-d H:i:s', strtotime('-4 minutes')));

		$this->assertFalse($customer->is_online());
	}

	// ---------------------------------------------------------------
	// Billing address
	// ---------------------------------------------------------------

	/**
	 * Test set_billing_address with array.
	 */
	public function test_set_billing_address_with_array(): void {

		$customer = new Customer();
		$user_id  = $this->make_user();
		$customer->set_user_id($user_id);

		$address_data = [
			'company_name'          => 'Acme Corp',
			'billing_email'         => 'billing@acme.com',
			'billing_address_line_1' => '123 Main St',
			'billing_country'       => 'US',
			'billing_state'         => 'NY',
			'billing_city'          => 'New York',
			'billing_zip_code'      => '10001',
		];

		$customer->set_billing_address($address_data);

		$billing = $customer->get_billing_address();

		$this->assertInstanceOf(\WP_Ultimo\Objects\Billing_Address::class, $billing);
		$this->assertEquals('Acme Corp', $billing->company_name);
		$this->assertEquals('US', $billing->billing_country);
		$this->assertEquals('10001', $billing->billing_zip_code);
	}

	/**
	 * Test set_billing_address with Billing_Address object.
	 */
	public function test_set_billing_address_with_object(): void {

		$customer = new Customer();
		$user_id  = $this->make_user();
		$customer->set_user_id($user_id);

		$address = new \WP_Ultimo\Objects\Billing_Address(
			[
				'company_name'  => 'Widget Inc',
				'billing_email' => 'info@widget.com',
			]
		);

		$customer->set_billing_address($address);

		$billing = $customer->get_billing_address();

		$this->assertEquals('Widget Inc', $billing->company_name);
		$this->assertEquals('info@widget.com', $billing->billing_email);
	}

	/**
	 * Test billing address persists through save/load cycle.
	 */
	public function test_billing_address_persistence(): void {

		$customer = $this->make_customer();

		$customer->set_billing_address(
			[
				'company_name'    => 'Persisted Corp',
				'billing_email'   => 'persist@test.com',
				'billing_country' => 'DE',
			]
		);
		$customer->save();

		$fetched = wu_get_customer($customer->get_id());

		$billing = $fetched->get_billing_address();

		$this->assertEquals('Persisted Corp', $billing->company_name);
		$this->assertEquals('DE', $billing->billing_country);
	}

	/**
	 * Test get_billing_address returns default when none set on a saved customer.
	 */
	public function test_get_billing_address_returns_default(): void {

		$customer = $this->make_customer();

		$fetched = wu_get_customer($customer->get_id());

		$billing = $fetched->get_billing_address();

		$this->assertInstanceOf(\WP_Ultimo\Objects\Billing_Address::class, $billing);
	}

	// ---------------------------------------------------------------
	// to_array
	// ---------------------------------------------------------------

	/**
	 * Test to_array contains expected keys.
	 */
	public function test_to_array_contains_expected_keys(): void {

		$customer = new Customer();
		$customer->set_user_id(1);
		$customer->set_type('customer');
		$customer->set_email_verification('none');

		$array = $customer->to_array();

		$this->assertIsArray($array);
		$this->assertArrayHasKey('user_id', $array);
		$this->assertArrayHasKey('type', $array);
		$this->assertArrayHasKey('email_verification', $array);
		$this->assertArrayHasKey('vip', $array);
		$this->assertArrayHasKey('last_login', $array);
		$this->assertArrayHasKey('date_registered', $array);
		$this->assertArrayHasKey('signup_form', $array);
	}

	/**
	 * Test to_array excludes internal properties.
	 */
	public function test_to_array_excludes_internal_properties(): void {

		$customer = new Customer();
		$array    = $customer->to_array();

		$this->assertArrayNotHasKey('query_class', $array);
		$this->assertArrayNotHasKey('skip_validation', $array);
		$this->assertArrayNotHasKey('meta', $array);
		$this->assertArrayNotHasKey('_original', $array);
		$this->assertArrayNotHasKey('_mappings', $array);
		$this->assertArrayNotHasKey('_mocked', $array);
	}

	// ---------------------------------------------------------------
	// to_search_results with injected user
	// ---------------------------------------------------------------

	/**
	 * Test to_search_results with injected _user property.
	 */
	public function test_to_search_results_with_injected_user(): void {

		$user_id = $this->make_user('injected_user', 'injected@example.com');

		$customer = new Customer();
		$customer->set_user_id($user_id);

		$user           = get_userdata($user_id);
		$customer->_user = $user;

		$results = $customer->to_search_results();

		$this->assertArrayHasKey('user_login', $results);
		$this->assertEquals('injected_user', $results['user_login']);
		$this->assertArrayHasKey('avatar', $results);
	}

	/**
	 * Test to_search_results for customer with no valid user.
	 */
	public function test_to_search_results_without_valid_user(): void {

		$customer = new Customer();
		$customer->set_user_id(999999);

		$results = $customer->to_search_results();

		$this->assertIsArray($results);
		$this->assertArrayHasKey('billing_address_data', $results);
		$this->assertArrayHasKey('billing_address', $results);
	}

	/**
	 * Test to_search_results includes billing address data.
	 */
	public function test_to_search_results_has_billing_address_data(): void {

		$customer = $this->make_customer();

		$customer->set_billing_address(
			[
				'company_name'  => 'Search Corp',
				'billing_email' => 'searchcorp@example.com',
			]
		);
		$customer->save();

		$fetched = wu_get_customer($customer->get_id());
		$results = $fetched->to_search_results();

		$this->assertArrayHasKey('billing_address_data', $results);
		$this->assertIsArray($results['billing_address_data']);
	}

	// ---------------------------------------------------------------
	// Validation rules details
	// ---------------------------------------------------------------

	/**
	 * Test validation rules include all expected keys.
	 */
	public function test_validation_rules_all_keys(): void {

		$customer = new Customer();
		$rules    = $customer->validation_rules();

		$expected_keys = [
			'user_id',
			'email_verification',
			'type',
			'last_login',
			'has_trialed',
			'vip',
			'ips',
			'extra_information',
			'signup_form',
			'network_id',
		];

		foreach ($expected_keys as $key) {
			$this->assertArrayHasKey($key, $rules, "Missing validation rule for: $key");
		}
	}

	/**
	 * Test validation rules for specific field constraints.
	 */
	public function test_validation_rule_details(): void {

		$customer = new Customer();
		$rules    = $customer->validation_rules();

		$this->assertStringContainsString('boolean', $rules['has_trialed']);
		$this->assertStringContainsString('boolean', $rules['vip']);
		$this->assertStringContainsString('array', $rules['ips']);
		$this->assertStringContainsString('integer', $rules['network_id']);
		$this->assertStringContainsString('nullable', $rules['network_id']);
	}

	/**
	 * Test validation fails without user_id.
	 */
	public function test_validation_fails_without_user_id(): void {

		$customer = new Customer();
		$customer->set_type('customer');
		$customer->set_email_verification('none');

		$result = $customer->validate();

		$this->assertWPError($result);
	}

	/**
	 * Test validation passes with skip_validation.
	 */
	public function test_skip_validation(): void {

		$customer = new Customer();
		$customer->set_skip_validation(true);

		$result = $customer->validate();

		$this->assertTrue($result);
	}

	/**
	 * Test validation unique constraint on user_id includes current ID.
	 */
	public function test_validation_unique_constraint_includes_id(): void {

		$customer = $this->make_customer();
		$rules    = $customer->validation_rules();

		$this->assertStringContainsString((string) $customer->get_id(), $rules['user_id']);
	}

	// ---------------------------------------------------------------
	// Extra information edge cases
	// ---------------------------------------------------------------

	/**
	 * Test extra information filters out empty values.
	 */
	public function test_extra_information_filters_empty(): void {

		$customer = new Customer();
		$customer->set_extra_information(
			[
				'key1' => 'value1',
				'key2' => '',
				'key3' => null,
				'key4' => 'value4',
			]
		);

		$extra = $customer->get_extra_information();

		$this->assertArrayHasKey('key1', $extra);
		$this->assertArrayHasKey('key4', $extra);
		$this->assertArrayNotHasKey('key2', $extra);
		$this->assertArrayNotHasKey('key3', $extra);
	}

	/**
	 * Test extra information persists through save/load via meta.
	 */
	public function test_extra_information_persistence(): void {

		$customer = $this->make_customer();

		$customer->set_extra_information(
			[
				'company' => 'Meta Corp',
				'phone'   => '555-1234',
			]
		);
		$customer->save();

		$fetched = wu_get_customer($customer->get_id());

		$extra = $fetched->get_extra_information();

		$this->assertEquals('Meta Corp', $extra['company']);
		$this->assertEquals('555-1234', $extra['phone']);
	}

	/**
	 * Test setting extra information with empty array.
	 */
	public function test_extra_information_empty_array(): void {

		$customer = new Customer();
		$customer->set_extra_information([]);

		$this->assertEmpty($customer->get_extra_information());
	}

	// ---------------------------------------------------------------
	// Meta data operations
	// ---------------------------------------------------------------

	/**
	 * Test update_meta and get_meta on a saved customer.
	 */
	public function test_meta_operations(): void {

		$customer = $this->make_customer();

		$customer->update_meta('test_key', 'test_value');

		$this->assertEquals('test_value', $customer->get_meta('test_key'));
	}

	/**
	 * Test delete_meta removes the meta.
	 */
	public function test_delete_meta(): void {

		$customer = $this->make_customer();

		$customer->update_meta('deletable_key', 'deletable_value');
		$this->assertEquals('deletable_value', $customer->get_meta('deletable_key'));

		$customer->delete_meta('deletable_key');

		$this->assertFalse($customer->get_meta('deletable_key'));
	}

	/**
	 * Test get_meta returns default when key does not exist.
	 */
	public function test_get_meta_default(): void {

		$customer = $this->make_customer();

		$this->assertEquals('default_val', $customer->get_meta('nonexistent_key', 'default_val'));
	}

	/**
	 * Test update_meta_batch stores multiple keys.
	 */
	public function test_update_meta_batch(): void {

		$customer = $this->make_customer();

		$customer->update_meta_batch(
			[
				'batch_key_1' => 'batch_val_1',
				'batch_key_2' => 'batch_val_2',
			]
		);

		$this->assertEquals('batch_val_1', $customer->get_meta('batch_key_1'));
		$this->assertEquals('batch_val_2', $customer->get_meta('batch_key_2'));
	}

	/**
	 * Test meta is not available on unsaved customer.
	 */
	public function test_meta_not_available_on_unsaved(): void {

		$customer = new Customer();
		$customer->set_user_id(1);

		$this->assertFalse($customer->get_meta('some_key'));
	}

	/**
	 * Test wu_get_customer_meta helper.
	 */
	public function test_wu_get_customer_meta_helper(): void {

		$customer = $this->make_customer();
		$customer->update_meta('helper_test_key', 'helper_test_val');

		$val = wu_get_customer_meta($customer->get_id(), 'helper_test_key');

		$this->assertEquals('helper_test_val', $val);
	}

	/**
	 * Test wu_update_customer_meta helper.
	 */
	public function test_wu_update_customer_meta_helper(): void {

		$customer = $this->make_customer();

		wu_update_customer_meta($customer->get_id(), 'update_helper_key', 'update_helper_val');

		$this->assertEquals('update_helper_val', $customer->get_meta('update_helper_key'));
	}

	/**
	 * Test wu_delete_customer_meta helper.
	 */
	public function test_wu_delete_customer_meta_helper(): void {

		$customer = $this->make_customer();
		$customer->update_meta('del_helper_key', 'del_helper_val');

		wu_delete_customer_meta($customer->get_id(), 'del_helper_key');

		$this->assertFalse($customer->get_meta('del_helper_key'));
	}

	/**
	 * Test wu_get_customer_meta returns default for non-existent customer.
	 */
	public function test_wu_get_customer_meta_nonexistent_customer(): void {

		$result = wu_get_customer_meta(999999, 'some_key', 'default');

		$this->assertEquals('default', $result);
	}

	/**
	 * Test wu_update_customer_meta returns false for non-existent customer.
	 */
	public function test_wu_update_customer_meta_nonexistent_customer(): void {

		$result = wu_update_customer_meta(999999, 'some_key', 'some_val');

		$this->assertFalse($result);
	}

	/**
	 * Test wu_delete_customer_meta returns false for non-existent customer.
	 */
	public function test_wu_delete_customer_meta_nonexistent_customer(): void {

		$result = wu_delete_customer_meta(999999, 'some_key');

		$this->assertFalse($result);
	}

	// ---------------------------------------------------------------
	// Constants
	// ---------------------------------------------------------------

	/**
	 * Test meta key constants are defined.
	 */
	public function test_meta_key_constants(): void {

		$this->assertEquals('ip_country', Customer::META_IP_COUNTRY);
		$this->assertEquals('wu_has_trialed', Customer::META_HAS_TRIALED);
		$this->assertEquals('wu_customer_extra_information', Customer::META_EXTRA_INFORMATION);
		$this->assertEquals('wu_verification_key', Customer::META_VERIFICATION_KEY);
	}

	// ---------------------------------------------------------------
	// Verification URL
	// ---------------------------------------------------------------

	/**
	 * Test get_verification_url without key returns site URL.
	 */
	public function test_get_verification_url_without_key(): void {

		$customer = $this->make_customer();

		$url = $customer->get_verification_url();

		$this->assertStringContainsString(get_site_url(wu_get_main_site_id()), $url);
	}

	/**
	 * Test get_verification_url with key includes query params.
	 */
	public function test_get_verification_url_with_key(): void {

		$customer = $this->make_customer();

		$customer->generate_verification_key();

		$url = $customer->get_verification_url();

		$this->assertStringContainsString('email-verification-key=', $url);
		$this->assertStringContainsString('customer=', $url);
	}

	// ---------------------------------------------------------------
	// get_country
	// ---------------------------------------------------------------

	/**
	 * Test get_country returns billing address country when set.
	 */
	public function test_get_country_from_billing_address(): void {

		$customer = $this->make_customer();

		$customer->set_billing_address(
			[
				'billing_country' => 'BR',
			]
		);
		$customer->save();

		$fetched = wu_get_customer($customer->get_id());

		$this->assertEquals('BR', $fetched->get_country());
	}

	/**
	 * Test get_country falls back to ip_country meta.
	 */
	public function test_get_country_fallback_to_ip_meta(): void {

		$customer = $this->make_customer();

		$customer->update_meta(Customer::META_IP_COUNTRY, 'JP');

		// No billing address set, so country should come from meta
		$fetched = wu_get_customer($customer->get_id());

		$this->assertEquals('JP', $fetched->get_country());
	}

	// ---------------------------------------------------------------
	// Membership methods
	// ---------------------------------------------------------------

	/**
	 * Test get_memberships returns empty array for customer without memberships.
	 */
	public function test_get_memberships_empty(): void {

		$customer = $this->make_customer();

		$memberships = $customer->get_memberships();

		$this->assertIsArray($memberships);
		$this->assertEmpty($memberships);
	}

	/**
	 * Test get_memberships returns memberships belonging to customer.
	 */
	public function test_get_memberships_with_data(): void {

		$customer = $this->make_customer();

		$product = wu_create_product(
			[
				'name'         => 'Test Plan',
				'slug'         => 'test-plan-' . wp_generate_uuid4(),
				'pricing_type' => 'paid',
				'amount'       => 10,
				'type'         => 'plan',
			]
		);
		$this->assertNotWPError($product);

		$membership = wu_create_membership(
			[
				'customer_id'     => $customer->get_id(),
				'plan_id'         => $product->get_id(),
				'status'          => 'active',
				'skip_validation' => true,
			]
		);
		$this->assertNotWPError($membership);

		$memberships = $customer->get_memberships();

		$this->assertCount(1, $memberships);
		$this->assertEquals($membership->get_id(), $memberships[0]->get_id());
	}

	/**
	 * Test get_pending_sites returns empty when no pending sites.
	 */
	public function test_get_pending_sites_empty(): void {

		$customer = $this->make_customer();

		$pending = $customer->get_pending_sites();

		$this->assertIsArray($pending);
		$this->assertEmpty($pending);
	}

	// ---------------------------------------------------------------
	// Payment methods
	// ---------------------------------------------------------------

	/**
	 * Test get_payments returns empty for customer without payments.
	 */
	public function test_get_payments_empty(): void {

		$customer = $this->make_customer();

		$payments = $customer->get_payments();

		$this->assertIsArray($payments);
		$this->assertEmpty($payments);
	}

	/**
	 * Test get_payments returns payments belonging to customer.
	 */
	public function test_get_payments_with_data(): void {

		$customer = $this->make_customer();

		$product = wu_create_product(
			[
				'name'         => 'Payment Plan',
				'slug'         => 'pay-plan-' . wp_generate_uuid4(),
				'pricing_type' => 'paid',
				'amount'       => 50,
				'type'         => 'plan',
			]
		);
		$this->assertNotWPError($product);

		$membership = wu_create_membership(
			[
				'customer_id'     => $customer->get_id(),
				'plan_id'         => $product->get_id(),
				'status'          => 'active',
				'skip_validation' => true,
			]
		);
		$this->assertNotWPError($membership);

		$payment = wu_create_payment(
			[
				'customer_id'   => $customer->get_id(),
				'membership_id' => $membership->get_id(),
				'currency'      => 'USD',
				'subtotal'      => 50.00,
				'total'         => 50.00,
				'status'        => 'completed',
				'gateway'       => 'manual',
			]
		);
		$this->assertNotWPError($payment);

		$payments = $customer->get_payments();

		$this->assertCount(1, $payments);
		$this->assertEquals($payment->get_id(), $payments[0]->get_id());
	}

	// ---------------------------------------------------------------
	// get_total_grossed
	// ---------------------------------------------------------------

	/**
	 * Test get_total_grossed returns sum of payment totals.
	 */
	public function test_get_total_grossed(): void {

		$customer = $this->make_customer();

		$product = wu_create_product(
			[
				'name'         => 'Gross Plan',
				'slug'         => 'gross-plan-' . wp_generate_uuid4(),
				'pricing_type' => 'paid',
				'amount'       => 100,
				'type'         => 'plan',
			]
		);
		$this->assertNotWPError($product);

		$membership = wu_create_membership(
			[
				'customer_id'     => $customer->get_id(),
				'plan_id'         => $product->get_id(),
				'status'          => 'active',
				'skip_validation' => true,
			]
		);
		$this->assertNotWPError($membership);

		wu_create_payment(
			[
				'customer_id'   => $customer->get_id(),
				'membership_id' => $membership->get_id(),
				'currency'      => 'USD',
				'subtotal'      => 75.00,
				'total'         => 75.00,
				'status'        => 'completed',
				'gateway'       => 'manual',
			]
		);

		wu_create_payment(
			[
				'customer_id'   => $customer->get_id(),
				'membership_id' => $membership->get_id(),
				'currency'      => 'USD',
				'subtotal'      => 25.00,
				'total'         => 25.00,
				'status'        => 'completed',
				'gateway'       => 'manual',
			]
		);

		$total = $customer->get_total_grossed();

		$this->assertEquals(100.00, (float) $total);
	}

	// ---------------------------------------------------------------
	// has_trialed persistence
	// ---------------------------------------------------------------

	/**
	 * Test has_trialed set_has_trialed stores in meta array.
	 */
	public function test_set_has_trialed_stores_in_meta(): void {

		$customer = new Customer();
		$customer->set_has_trialed(true);

		$this->assertTrue($customer->has_trialed());
	}

	/**
	 * Test has_trialed returns falsy when not trialed.
	 */
	public function test_has_trialed_returns_falsy_when_not_set(): void {

		$customer = $this->make_customer();

		$result = $customer->has_trialed();

		$this->assertEmpty($result);
	}

	// ---------------------------------------------------------------
	// Constructor with data array
	// ---------------------------------------------------------------

	/**
	 * Test constructor accepts an array of attributes.
	 */
	public function test_constructor_with_array(): void {

		$user_id = $this->make_user();

		$customer = new Customer(
			[
				'user_id'            => $user_id,
				'type'               => 'customer',
				'email_verification' => 'verified',
				'vip'                => true,
				'signup_form'        => 'test-form',
			]
		);

		$this->assertEquals($user_id, $customer->get_user_id());
		$this->assertEquals('customer', $customer->get_type());
		$this->assertEquals('verified', $customer->get_email_verification());
		$this->assertTrue($customer->is_vip());
		$this->assertEquals('test-form', $customer->get_signup_form());
	}

	/**
	 * Test constructor with stdClass object.
	 */
	public function test_constructor_with_stdclass(): void {

		$user_id = $this->make_user();

		$obj                     = new \stdClass();
		$obj->user_id            = $user_id;
		$obj->type               = 'customer';
		$obj->email_verification = 'pending';

		$customer = new Customer($obj);

		$this->assertEquals($user_id, $customer->get_user_id());
		$this->assertEquals('customer', $customer->get_type());
		$this->assertEquals('pending', $customer->get_email_verification());
	}

	/**
	 * Test constructor with no arguments.
	 */
	public function test_constructor_no_args(): void {

		$customer = new Customer();

		$this->assertEquals(0, $customer->get_id());
		$this->assertEquals(0, $customer->get_user_id());
		$this->assertNull($customer->get_type());
	}

	// ---------------------------------------------------------------
	// exists()
	// ---------------------------------------------------------------

	/**
	 * Test exists returns false for unsaved customer.
	 */
	public function test_exists_false_for_unsaved(): void {

		$customer = new Customer();

		$this->assertFalse($customer->exists());
	}

	/**
	 * Test exists returns true for saved customer.
	 */
	public function test_exists_true_for_saved(): void {

		$customer = $this->make_customer();

		$this->assertTrue($customer->exists());
	}

	// ---------------------------------------------------------------
	// get_hash
	// ---------------------------------------------------------------

	/**
	 * Test get_hash returns a string for saved customer.
	 */
	public function test_get_hash_returns_string(): void {

		$customer = $this->make_customer();

		$hash = $customer->get_hash();

		$this->assertIsString($hash);
		$this->assertNotEmpty($hash);
	}

	/**
	 * Test get_hash can be decoded back.
	 */
	public function test_get_hash_is_decodable(): void {

		$customer = $this->make_customer();

		$hash    = $customer->get_hash();
		$decoded = Hash::decode($hash, 'customer');

		$this->assertEquals($customer->get_id(), $decoded);
	}

	// ---------------------------------------------------------------
	// get_sites
	// ---------------------------------------------------------------

	/**
	 * Test get_sites returns empty array for customer without sites.
	 */
	public function test_get_sites_empty(): void {

		$customer = $this->make_customer();

		$sites = $customer->get_sites();

		$this->assertIsArray($sites);
		$this->assertEmpty($sites);
	}

	// ---------------------------------------------------------------
	// Lock / Unlock
	// ---------------------------------------------------------------

	/**
	 * Test lock and is_locked on a saved customer.
	 */
	public function test_lock_and_is_locked(): void {

		$customer = $this->make_customer();

		$this->assertFalse($customer->is_locked());

		$customer->lock();

		$this->assertNotEmpty($customer->is_locked());
	}

	/**
	 * Test unlock after lock.
	 */
	public function test_unlock(): void {

		$customer = $this->make_customer();

		$customer->lock();
		$this->assertNotEmpty($customer->is_locked());

		$customer->unlock();
		$this->assertFalse($customer->is_locked());
	}

	// ---------------------------------------------------------------
	// Date created / modified (from Base_Model)
	// ---------------------------------------------------------------

	/**
	 * Test date_created getter and setter.
	 */
	public function test_date_created_getter_setter(): void {

		$customer = new Customer();
		$date     = '2025-01-01 10:00:00';

		$customer->set_date_created($date);

		$this->assertEquals($date, $customer->get_date_created());
	}

	/**
	 * Test date_modified getter and setter.
	 */
	public function test_date_modified_getter_setter(): void {

		$customer = new Customer();
		$date     = '2025-06-15 14:30:00';

		$customer->set_date_modified($date);

		$this->assertEquals($date, $customer->get_date_modified());
	}

	// ---------------------------------------------------------------
	// migrated_from_id
	// ---------------------------------------------------------------

	/**
	 * Test migrated_from_id getter and setter.
	 */
	public function test_migrated_from_id(): void {

		$customer = new Customer();

		$customer->set_migrated_from_id(42);

		$this->assertEquals(42, $customer->get_migrated_from_id());
		$this->assertTrue($customer->is_migrated());
	}

	/**
	 * Test is_migrated returns false when not migrated.
	 */
	public function test_is_migrated_false(): void {

		$customer = new Customer();

		$this->assertFalse($customer->is_migrated());
	}

	// ---------------------------------------------------------------
	// JsonSerializable
	// ---------------------------------------------------------------

	/**
	 * Test customer implements JsonSerializable.
	 */
	public function test_json_serializable(): void {

		$customer = new Customer();
		$customer->set_user_id(1);
		$customer->set_type('customer');

		$json = json_encode($customer);

		$this->assertIsString($json);

		$decoded = json_decode($json, true);
		$this->assertIsArray($decoded);
		$this->assertArrayHasKey('user_id', $decoded);
		$this->assertArrayHasKey('type', $decoded);
	}

	// ---------------------------------------------------------------
	// Multiple customers
	// ---------------------------------------------------------------

	/**
	 * Test wu_get_customers returns list of customers.
	 */
	public function test_wu_get_customers(): void {

		$c1 = $this->make_customer();
		$c2 = $this->make_customer();

		$customers = wu_get_customers();

		$ids = array_map(fn($c) => $c->get_id(), $customers);

		$this->assertContains($c1->get_id(), $ids);
		$this->assertContains($c2->get_id(), $ids);
	}

	// ---------------------------------------------------------------
	// attributes method
	// ---------------------------------------------------------------

	/**
	 * Test attributes method sets multiple properties at once.
	 */
	public function test_attributes_method(): void {

		$customer = new Customer();
		$user_id  = $this->make_user();

		$customer->attributes(
			[
				'user_id'            => $user_id,
				'type'               => 'customer',
				'email_verification' => 'verified',
				'vip'                => true,
			]
		);

		$this->assertEquals($user_id, $customer->get_user_id());
		$this->assertEquals('customer', $customer->get_type());
		$this->assertEquals('verified', $customer->get_email_verification());
		$this->assertTrue($customer->is_vip());
	}

	// ---------------------------------------------------------------
	// get_primary_site_id
	// ---------------------------------------------------------------

	/**
	 * Test get_primary_site_id returns main site when no sites exist.
	 */
	public function test_get_primary_site_id_fallback(): void {

		$customer = $this->make_customer();

		$primary_site_id = $customer->get_primary_site_id();

		$this->assertIsInt($primary_site_id);
		$this->assertGreaterThan(0, $primary_site_id);
	}

	// ---------------------------------------------------------------
	// IP persistence through save/load
	// ---------------------------------------------------------------

	/**
	 * Test IPs persist through save/load.
	 */
	public function test_ips_persist_through_save_load(): void {

		$customer = $this->make_customer();

		$customer->set_ips(['192.168.1.1', '10.0.0.1']);
		$customer->save();

		$fetched = wu_get_customer($customer->get_id());

		$this->assertEquals(['192.168.1.1', '10.0.0.1'], $fetched->get_ips());
	}

	// ---------------------------------------------------------------
	// Signup form persistence
	// ---------------------------------------------------------------

	/**
	 * Test signup form persists through save/load.
	 */
	public function test_signup_form_persistence(): void {

		$customer = $this->make_customer(['signup_form' => 'custom-checkout']);

		$fetched = wu_get_customer($customer->get_id());

		$this->assertEquals('custom-checkout', $fetched->get_signup_form());
	}

	// ---------------------------------------------------------------
	// Last login persistence
	// ---------------------------------------------------------------

	/**
	 * Test last login persists through save/load.
	 */
	public function test_last_login_persistence(): void {

		$customer = $this->make_customer();

		$login_time = '2025-03-15 09:30:00';
		$customer->set_last_login($login_time);
		$customer->save();

		$fetched = wu_get_customer($customer->get_id());

		$this->assertEquals($login_time, $fetched->get_last_login());
	}

	// ---------------------------------------------------------------
	// wu_create_customer with email and password
	// ---------------------------------------------------------------

	/**
	 * Test wu_create_customer creates a WP user when email/username/password given.
	 */
	public function test_wu_create_customer_creates_wp_user(): void {

		$unique   = wp_generate_uuid4();
		$email    = "newcustomer_$unique@example.com";
		$username = "newcustomer_$unique";

		$customer = wu_create_customer(
			[
				'email'              => $email,
				'username'           => $username,
				'password'           => 'securepassword123',
				'email_verification' => 'none',
			]
		);

		$this->assertNotWPError($customer);
		$this->assertInstanceOf(Customer::class, $customer);

		$user = get_user_by('email', $email);
		$this->assertInstanceOf(WP_User::class, $user);
		$this->assertEquals($user->ID, $customer->get_user_id());
	}

	/**
	 * Test wu_create_customer returns WP_Error for invalid email.
	 */
	public function test_wu_create_customer_invalid_email(): void {

		$result = wu_create_customer(
			[
				'email'              => 'not-an-email',
				'username'           => 'baduser',
				'email_verification' => 'none',
			]
		);

		$this->assertWPError($result);
	}

	// ---------------------------------------------------------------
	// Multiple memberships for a single customer
	// ---------------------------------------------------------------

	/**
	 * Test customer can have multiple memberships.
	 */
	public function test_multiple_memberships(): void {

		$customer = $this->make_customer();

		$product1 = wu_create_product(
			[
				'name'         => 'Plan A',
				'slug'         => 'plan-a-' . wp_generate_uuid4(),
				'pricing_type' => 'paid',
				'amount'       => 10,
				'type'         => 'plan',
			]
		);

		$product2 = wu_create_product(
			[
				'name'         => 'Plan B',
				'slug'         => 'plan-b-' . wp_generate_uuid4(),
				'pricing_type' => 'paid',
				'amount'       => 20,
				'type'         => 'plan',
			]
		);

		$this->assertNotWPError($product1);
		$this->assertNotWPError($product2);

		wu_create_membership(
			[
				'customer_id'     => $customer->get_id(),
				'plan_id'         => $product1->get_id(),
				'status'          => 'active',
				'skip_validation' => true,
			]
		);

		wu_create_membership(
			[
				'customer_id'     => $customer->get_id(),
				'plan_id'         => $product2->get_id(),
				'status'          => 'active',
				'skip_validation' => true,
			]
		);

		$memberships = $customer->get_memberships();

		$this->assertCount(2, $memberships);
	}

	// ---------------------------------------------------------------
	// get_user edge cases
	// ---------------------------------------------------------------

	/**
	 * Test get_user returns false for user_id 0.
	 */
	public function test_get_user_for_zero_user_id(): void {

		$customer = new Customer();

		$user = $customer->get_user();

		$this->assertFalse($user);
	}

	// ---------------------------------------------------------------
	// Model name
	// ---------------------------------------------------------------

	/**
	 * Test model property is set correctly.
	 */
	public function test_model_name(): void {

		$customer = new Customer();

		$this->assertEquals('customer', $customer->model);
	}

	// ---------------------------------------------------------------
	// duplicate
	// ---------------------------------------------------------------

	/**
	 * Test duplicate creates a copy with ID reset to 0.
	 */
	public function test_duplicate(): void {

		$customer = $this->make_customer();

		$this->assertGreaterThan(0, $customer->get_id());

		$clone = $customer->duplicate();

		$this->assertEquals(0, $clone->get_id());
		$this->assertEquals($customer->get_user_id(), $clone->get_user_id());
		$this->assertEquals($customer->get_type(), $clone->get_type());
	}

	// ---------------------------------------------------------------
	// get_by_hash
	// ---------------------------------------------------------------

	/**
	 * Test customer can be retrieved by hash.
	 */
	public function test_get_by_hash(): void {

		$customer = $this->make_customer();
		$hash     = $customer->get_hash();

		$fetched = Customer::get_by_hash($hash);

		$this->assertInstanceOf(Customer::class, $fetched);
		$this->assertEquals($customer->get_id(), $fetched->get_id());
	}

	// ---------------------------------------------------------------
	// wu_get_customer_by_hash
	// ---------------------------------------------------------------

	/**
	 * Test wu_get_customer_by_hash helper function.
	 */
	public function test_wu_get_customer_by_hash(): void {

		$customer = $this->make_customer();
		$hash     = $customer->get_hash();

		$fetched = wu_get_customer_by_hash($hash);

		$this->assertInstanceOf(Customer::class, $fetched);
		$this->assertEquals($customer->get_id(), $fetched->get_id());
	}

	// ---------------------------------------------------------------
	// Verification key lifecycle
	// ---------------------------------------------------------------

	/**
	 * Test full verification key lifecycle: generate, get, disable.
	 */
	public function test_verification_key_lifecycle(): void {

		$customer = $this->make_customer();

		// No key initially
		$this->assertFalse($customer->get_verification_key());

		// Generate
		$customer->generate_verification_key();
		$key = $customer->get_verification_key();

		$this->assertNotEmpty($key);
		$this->assertIsString($key);

		// Disable
		$customer->disable_verification_key();
		$this->assertEmpty($customer->get_verification_key());
	}

	// ---------------------------------------------------------------
	// wu_update_customer_meta with type and title
	// ---------------------------------------------------------------

	/**
	 * Test wu_update_customer_meta with type parameter stores custom keys.
	 */
	public function test_wu_update_customer_meta_with_type(): void {

		$customer = $this->make_customer();

		wu_update_customer_meta($customer->get_id(), 'custom_field', 'custom_value', 'text', 'Custom Field');

		$this->assertEquals('custom_value', $customer->get_meta('custom_field'));

		$custom_keys = $customer->get_meta('wu_custom_meta_keys', []);

		$this->assertArrayHasKey('custom_field', $custom_keys);
		$this->assertEquals('text', $custom_keys['custom_field']['type']);
		$this->assertEquals('Custom Field', $custom_keys['custom_field']['title']);
	}

	// ---------------------------------------------------------------
	// _get_original
	// ---------------------------------------------------------------

	/**
	 * Test _get_original returns original state.
	 */
	public function test_get_original(): void {

		$user_id = $this->make_user();

		$customer = new Customer(
			[
				'user_id'            => $user_id,
				'type'               => 'customer',
				'email_verification' => 'none',
			]
		);

		$original = $customer->_get_original();

		$this->assertIsArray($original);
		$this->assertArrayHasKey('user_id', $original);
	}
}

<?php
/**
 * Tests for the Unique_Customer_Email validation rule.
 *
 * @package WP_Ultimo\Tests\Helpers
 * @since 2.3.0
 */

namespace WP_Ultimo\Tests\Helpers;

use WP_Ultimo\Helpers\Validation_Rules\Unique_Customer_Email;
use WP_UnitTestCase;

/**
 * Test case for the Unique_Customer_Email validation rule.
 *
 * @since 2.3.0
 */
class Unique_Customer_Email_Test extends WP_UnitTestCase {

	/**
	 * Test that an empty email passes validation.
	 *
	 * @since 2.3.0
	 */
	public function test_empty_email_passes() {

		$rule = new Unique_Customer_Email();

		$this->assertTrue($rule->check(''));
		$this->assertTrue($rule->check(null));
	}

	/**
	 * Test that a new email (no user or customer exists) passes validation.
	 *
	 * @since 2.3.0
	 */
	public function test_new_email_passes() {

		$rule = new Unique_Customer_Email();

		// Use a random email that doesn't exist
		$this->assertTrue($rule->check('nonexistent-' . wp_generate_uuid4() . '@example.com'));
	}

	/**
	 * Test that an email belonging to a WordPress user but not a customer passes validation.
	 *
	 * @since 2.3.0
	 */
	public function test_email_with_user_but_no_customer_passes() {

		// Create a WordPress user without a customer using a unique email
		$email   = 'user-only-' . wp_generate_uuid4() . '@example.com';
		$user_id = $this->factory()->user->create([
			'user_email' => $email,
		]);

		$user = get_user_by('id', $user_id);
		$this->assertEquals($email, $user->user_email, 'User email should match created email');

		// Delete any existing customer linked to this user (cleanup from previous test runs)
		$existing_customer = wu_get_customer_by_user_id($user_id);
		if ($existing_customer) {
			$existing_customer->delete();
		}

		// Verify no customer is linked to this user now
		$customer = wu_get_customer_by_user_id($user_id);
		$this->assertFalse($customer, 'No customer should be linked to this user');

		$rule = new Unique_Customer_Email();

		// Should pass because no customer is linked to this user
		$this->assertTrue($rule->check($user->user_email));
	}

	/**
	 * Test that an email belonging to an existing customer fails validation.
	 *
	 * @since 2.3.0
	 */
	public function test_email_with_existing_customer_fails() {

		$email = 'customer-' . wp_generate_uuid4() . '@example.com';

		// Create a customer using wu_create_customer
		$customer = wu_create_customer([
			'email'    => $email,
			'username' => 'testcustomer' . wp_generate_password(8, false),
			'password' => 'password123',
		]);

		$this->assertInstanceOf(\WP_Ultimo\Models\Customer::class, $customer);

		$user = $customer->get_user();
		$this->assertInstanceOf(\WP_User::class, $user);

		$rule = new Unique_Customer_Email();

		// Should fail because a customer exists with this email
		$this->assertFalse($rule->check($user->user_email));
	}

	/**
	 * Test that the validation rule works with the Validator class.
	 *
	 * @since 2.3.0
	 */
	public function test_validation_rule_works_with_validator() {

		$validator = new \WP_Ultimo\Helpers\Validator();

		// Test with non-existent email (should pass)
		$data = [
			'email' => 'new-email-' . wp_generate_uuid4() . '@example.com',
		];

		$rules = [
			'email' => 'unique_customer_email',
		];

		$result = $validator->validate($data, $rules);
		$this->assertFalse($result->fails(), 'Validation should pass for new email');

		$email = 'existing-customer-' . wp_generate_uuid4() . '@example.com';

		// Create a customer
		$customer = wu_create_customer([
			'email'    => $email,
			'username' => 'validatorcust' . wp_generate_password(8, false),
			'password' => 'password123',
		]);

		$user = $customer->get_user();

		// Test with existing customer email (should fail)
		$data = [
			'email' => $user->user_email,
		];

		$result = $validator->validate($data, $rules);
		$this->assertTrue($result->fails(), 'Validation should fail for existing customer email');
	}
}

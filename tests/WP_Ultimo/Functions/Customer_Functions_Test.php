<?php
/**
 * Tests for customer functions.
 *
 * @package WP_Ultimo\Tests
 */

namespace WP_Ultimo\Functions;

use WP_UnitTestCase;

/**
 * Test class for customer functions.
 */
class Customer_Functions_Test extends WP_UnitTestCase {

	/**
	 * Test wu_get_customer returns false for nonexistent.
	 */
	public function test_get_customer_nonexistent(): void {

		$result = wu_get_customer(999999);

		$this->assertFalse($result);
	}

	/**
	 * Test wu_get_customer_by returns false for nonexistent.
	 */
	public function test_get_customer_by_nonexistent(): void {

		$result = wu_get_customer_by('id', 999999);

		$this->assertFalse($result);
	}

	/**
	 * Test wu_get_customer_by_hash returns false for nonexistent.
	 */
	public function test_get_customer_by_hash_nonexistent(): void {

		$result = wu_get_customer_by_hash('nonexistent_hash');

		$this->assertFalse($result);
	}

	/**
	 * Test wu_get_customers returns array.
	 */
	public function test_get_customers_returns_array(): void {

		$result = wu_get_customers();

		$this->assertIsArray($result);
	}

	/**
	 * Test wu_get_customers with search query.
	 */
	public function test_get_customers_with_search(): void {

		$result = wu_get_customers(['search' => 'nonexistent_user_xyz']);

		$this->assertIsArray($result);
	}

	/**
	 * Test wu_get_customer_by_user_id returns false for nonexistent.
	 */
	public function test_get_customer_by_user_id_nonexistent(): void {

		$result = wu_get_customer_by_user_id(999999);

		$this->assertFalse($result);
	}

	/**
	 * Test wu_get_current_customer returns false when no user logged in.
	 */
	public function test_get_current_customer_no_user(): void {

		wp_set_current_user(0);

		$result = wu_get_current_customer();

		$this->assertFalse($result);
	}

	/**
	 * Test wu_create_customer creates a customer.
	 */
	public function test_create_customer(): void {

		$user_id = self::factory()->user->create();

		$customer = wu_create_customer([
			'user_id'         => $user_id,
			'skip_validation' => true,
		]);

		$this->assertNotWPError($customer);
		$this->assertInstanceOf(\WP_Ultimo\Models\Customer::class, $customer);
	}

	/**
	 * Test wu_create_customer with invalid email returns WP_Error.
	 */
	public function test_create_customer_invalid_email(): void {

		$result = wu_create_customer([
			'email'           => 'not-an-email',
			'skip_validation' => true,
		]);

		$this->assertWPError($result);
	}

	/**
	 * Test wu_get_customer retrieves created customer.
	 */
	public function test_get_customer_retrieves_created(): void {

		$user_id = self::factory()->user->create();

		$customer = wu_create_customer([
			'user_id'         => $user_id,
			'skip_validation' => true,
		]);

		$this->assertNotWPError($customer);

		$retrieved = wu_get_customer($customer->get_id());

		$this->assertNotFalse($retrieved);
		$this->assertEquals($customer->get_id(), $retrieved->get_id());
	}

	/**
	 * Test wu_get_customer_by_user_id retrieves created customer.
	 */
	public function test_get_customer_by_user_id_retrieves_created(): void {

		$user_id = self::factory()->user->create();

		$customer = wu_create_customer([
			'user_id'         => $user_id,
			'skip_validation' => true,
		]);

		$this->assertNotWPError($customer);

		$retrieved = wu_get_customer_by_user_id($user_id);

		$this->assertNotFalse($retrieved);
		$this->assertEquals($customer->get_id(), $retrieved->get_id());
	}

	/**
	 * Test wu_username_from_email generates username from email.
	 */
	public function test_username_from_email_basic(): void {

		$username = wu_username_from_email('john.doe@example.com');

		$this->assertNotEmpty($username);
		$this->assertIsString($username);
	}

	/**
	 * Test wu_username_from_email with first and last name.
	 */
	public function test_username_from_email_with_name(): void {

		$username = wu_username_from_email('john@example.com', [
			'first_name' => 'John',
			'last_name'  => 'Doe',
		]);

		$this->assertStringContainsString('john', strtolower($username));
	}

	/**
	 * Test wu_username_from_email with common email prefix falls back to domain.
	 */
	public function test_username_from_email_common_prefix(): void {

		$username = wu_username_from_email('info@example.com');

		// Should use domain part since 'info' is a common prefix
		$this->assertNotEquals('info', $username);
	}

	/**
	 * Test wu_username_from_email with suffix.
	 */
	public function test_username_from_email_with_suffix(): void {

		$username = wu_username_from_email('unique_test_user@example.com', [], '-99');

		$this->assertStringContainsString('-99', $username);
	}

	/**
	 * Test wu_get_customer_meta returns default for nonexistent customer.
	 */
	public function test_get_customer_meta_nonexistent(): void {

		$result = wu_get_customer_meta(999999, 'some_key', 'default_val');

		$this->assertEquals('default_val', $result);
	}

	/**
	 * Test wu_update_customer_meta returns false for nonexistent customer.
	 */
	public function test_update_customer_meta_nonexistent(): void {

		$result = wu_update_customer_meta(999999, 'some_key', 'some_value');

		$this->assertFalse($result);
	}

	/**
	 * Test wu_delete_customer_meta returns false for nonexistent customer.
	 */
	public function test_delete_customer_meta_nonexistent(): void {

		$result = wu_delete_customer_meta(999999, 'some_key');

		$this->assertFalse($result);
	}

	/**
	 * Test wu_get_customer_gateway_id returns empty string when no memberships.
	 */
	public function test_get_customer_gateway_id_no_memberships(): void {

		$user_id = self::factory()->user->create();

		$customer = wu_create_customer([
			'user_id'         => $user_id,
			'skip_validation' => true,
		]);

		$this->assertNotWPError($customer);

		$result = wu_get_customer_gateway_id($customer->get_id(), ['stripe']);

		$this->assertEquals('', $result);
	}

	/**
	 * Test wu_create_customer reuses existing WP user instead of creating a duplicate.
	 *
	 * Scenario: a previous checkout attempt created a WP user but failed before
	 * saving the customer record (orphaned user). On retry, wu_create_customer()
	 * must reuse the existing user rather than calling wpmu_create_user() again.
	 */
	public function test_create_customer_reuses_existing_wp_user_on_retry(): void {

		$email   = 'retry-test-' . uniqid() . '@example.com';
		$user_id = self::factory()->user->create(['user_email' => $email]);

		// Confirm no customer exists for this user yet (simulates orphaned state).
		$this->assertFalse(wu_get_customer_by_user_id($user_id));

		// Now call wu_create_customer() with the same email.
		$customer = wu_create_customer([
			'email'           => $email,
			'username'        => 'retryuser',
			'password'        => 'Str0ngP@ss!',
			'skip_validation' => true,
		]);

		$this->assertNotWPError($customer);
		$this->assertInstanceOf(\WP_Ultimo\Models\Customer::class, $customer);

		// The customer must be linked to the pre-existing WP user, not a new one.
		$this->assertSame($user_id, $customer->get_user_id());

		// Confirm only one WP user exists with this email.
		$users_with_email = get_users(['search' => $email, 'search_columns' => ['user_email']]);
		$this->assertCount(1, $users_with_email);
	}

	/**
	 * Test wu_create_customer returns WP_Error when email belongs to a user
	 * who already has a customer record (genuine email-in-use case).
	 *
	 * This is tested at the checkout layer (maybe_create_customer), but we also
	 * verify wu_create_customer itself does not create a duplicate customer row.
	 */
	public function test_create_customer_prevents_duplicate_customer_for_same_user(): void {

		$user_id = self::factory()->user->create();

		// First customer creation for this user must succeed.
		$first = wu_create_customer([
			'user_id'         => $user_id,
			'skip_validation' => true,
		]);

		$this->assertNotWPError($first);
		$this->assertInstanceOf(\WP_Ultimo\Models\Customer::class, $first);

		// A second call for the same user_id must not create a second customer row.
		wu_create_customer([
			'user_id'         => $user_id,
			'skip_validation' => true,
		]);

		// wu_create_customer reuses the existing user; save() may return an error
		// for the duplicate or the existing customer object — either is acceptable.
		// What must NOT happen is a second customer row being saved.

		// There should still be exactly one customer linked to this user.
		$customers_for_user = array_filter(
			wu_get_customers(),
			function ($c) use ($user_id) {
				return (int) $c->get_user_id() === (int) $user_id;
			}
		);

		$this->assertCount(1, $customers_for_user);
	}
}

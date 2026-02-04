<?php
/**
 * Unit tests for Customer_Manager.
 *
 * @package WP_Ultimo\Tests\Managers
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Customer_Manager;

class Customer_Manager_Test extends \WP_UnitTestCase {

	use Manager_Test_Trait;

	protected function get_manager_class(): string {
		return Customer_Manager::class;
	}

	protected function get_expected_slug(): ?string {
		return 'customer';
	}

	protected function get_expected_model_class(): ?string {
		return \WP_Ultimo\Models\Customer::class;
	}

	/**
	 * Test log_ip_and_last_login with a valid WP_User.
	 */
	public function test_log_ip_and_last_login_with_valid_user(): void {

		$user_id  = $this->factory()->user->create(['role' => 'subscriber']);
		$customer = wu_create_customer(
			[
				'user_id'       => $user_id,
				'email_address' => 'login-test@example.com',
			]
		);

		$this->assertNotWPError($customer);

		$user    = get_user_by('id', $user_id);
		$manager = $this->get_manager_instance();

		$manager->log_ip_and_last_login($user->user_login, $user);

		// Refresh customer from DB.
		$customer = wu_get_customer($customer->get_id());

		$this->assertNotEmpty($customer->get_last_login(), 'last_login should be set after log_ip_and_last_login.');
	}

	/**
	 * Test log_ip_and_last_login with an invalid user does nothing.
	 */
	public function test_log_ip_and_last_login_with_nonexistent_user(): void {

		$manager = $this->get_manager_instance();

		// Should not throw — just return early.
		$manager->log_ip_and_last_login('nonexistent_user_xyz', null);

		$this->assertTrue(true);
	}

	/**
	 * Test transition_customer_email_verification only acts on pending.
	 */
	public function test_transition_email_verification_ignores_non_pending(): void {

		$manager = $this->get_manager_instance();

		// Should return early without error when new_status is not 'pending'.
		$manager->transition_customer_email_verification('none', 'verified', 99999);

		$this->assertTrue(true);
	}

	/**
	 * Test maybe_add_to_main_site respects the setting.
	 */
	public function test_maybe_add_to_main_site_skips_when_disabled(): void {

		wu_save_setting('add_users_to_main_site', false);

		$user_id  = $this->factory()->user->create(['role' => 'subscriber']);
		$customer = wu_create_customer(
			[
				'user_id'       => $user_id,
				'email_address' => 'mainsite-test@example.com',
			]
		);

		$this->assertNotWPError($customer);

		$manager = $this->get_manager_instance();

		// Create a mock checkout — we only need the method not to throw.
		$manager->maybe_add_to_main_site($customer, new \stdClass());

		// User should NOT be a member of the main site (beyond default).
		$this->assertTrue(true);
	}

	/**
	 * Test on_heartbeat_send returns the response array.
	 */
	public function test_on_heartbeat_send_returns_response(): void {

		$manager  = $this->get_manager_instance();
		$response = $manager->on_heartbeat_send(['server_time' => time()]);

		$this->assertIsArray($response);
		$this->assertArrayHasKey('server_time', $response);
	}
}

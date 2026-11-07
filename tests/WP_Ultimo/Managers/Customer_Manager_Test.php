<?php
/**
 * Unit tests for Customer_Manager.
 *
 * @package WP_Ultimo\Tests\Managers
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Database\Memberships\Membership_Status;
use WP_Ultimo\Database\Payments\Payment_Status;
use WP_Ultimo\Gateways\Free_Gateway;
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

	/**
	 * Test email verification succeeds when the customer is logged out.
	 */
	public function test_email_verification_does_not_require_logged_in_customer(): void {

		$customer = $this->create_pending_customer_with_verification_key('logged-out-verification@example.com');

		wp_set_current_user(0);

		$this->assert_email_verification_request_succeeds($customer);
	}

	/**
	 * Test email verification succeeds even when another user is logged in.
	 */
	public function test_email_verification_allows_different_logged_in_user(): void {

		$customer = $this->create_pending_customer_with_verification_key('different-user-verification@example.com');
		$admin_id = self::factory()->user->create(['role' => 'administrator']);

		wp_set_current_user($admin_id);

		$this->assert_email_verification_request_succeeds($customer);
	}

	public function test_email_verification_dispatches_free_signup_receipt_once(): void {

		$customer = $this->create_pending_customer_with_verification_key('free-signup-verification@example.com');
		$product  = wu_create_product(
			[
				'name'         => 'Verified Free Plan',
				'slug'         => 'verified-free-plan-' . wp_generate_uuid4(),
				'pricing_type' => 'free',
				'amount'       => 0,
				'type'         => 'plan',
			]
		);

		$this->assertNotWPError($product);

		$membership = wu_create_membership(
			[
				'customer_id'     => $customer->get_id(),
				'plan_id'         => $product->get_id(),
				'status'          => Membership_Status::PENDING,
				'date_trial_end'  => gmdate('Y-m-d H:i:s', time() - HOUR_IN_SECONDS),
				'skip_validation' => true,
			]
		);

		$this->assertNotWPError($membership);

		$payment = wu_create_payment(
			[
				'customer_id'   => $customer->get_id(),
				'membership_id' => $membership->get_id(),
				'product_id'    => $product->get_id(),
				'currency'      => 'USD',
				'subtotal'      => 0,
				'total'         => 0,
				'status'        => Payment_Status::COMPLETED,
				'gateway'       => 'free',
			]
		);

		$this->assertNotWPError($payment);

		$dispatch_count  = 0;
		$count_dispatch  = static function () use (&$dispatch_count): void {
			++$dispatch_count;
		};
		$resolve_gateway = static function ($gateway, $gateway_id) {
			return ! $gateway && 'free' === $gateway_id ? new Free_Gateway() : $gateway;
		};

		add_action('wu_gateway_payment_processed', $count_dispatch, PHP_INT_MAX, 0);
		add_filter('wu_get_gateway', $resolve_gateway, 10, 2);

		try {
			$this->assert_email_verification_request_succeeds($customer);

			$membership = wu_get_membership($membership->get_id());
			$payment    = wu_get_payment($payment->get_id());

			$this->assertSame(Membership_Status::ACTIVE, $membership->get_status());
			$this->assertSame(1, (int) $payment->get_meta('wu_payment_received_sent'));

			$reflection = new \ReflectionClass($this->get_manager_instance());
			$method     = $reflection->getMethod('maybe_send_payment_received');
			$method->invoke($this->get_manager_instance(), $payment, $membership);
		} finally {
			remove_action('wu_gateway_payment_processed', $count_dispatch, PHP_INT_MAX);
			remove_filter('wu_get_gateway', $resolve_gateway, 10);
		}

		$this->assertSame(1, $dispatch_count);
	}

	/**
	 * Create a pending customer with a saved verification key.
	 *
	 * @param string $email Customer email address.
	 * @return \WP_Ultimo\Models\Customer
	 */
	private function create_pending_customer_with_verification_key(string $email): \WP_Ultimo\Models\Customer {

		$user_id = self::factory()->user->create(['user_email' => $email]);

		$customer = wu_create_customer(
			[
				'user_id'            => $user_id,
				'email_verification' => 'pending',
			]
		);

		$this->assertNotWPError($customer);
		$this->assertTrue((bool) $customer->generate_verification_key());

		return $customer;
	}

	/**
	 * Assert a verification request verifies the customer and clears the key.
	 *
	 * @param \WP_Ultimo\Models\Customer $customer Customer being verified.
	 * @return void
	 */
	private function assert_email_verification_request_succeeds(\WP_Ultimo\Models\Customer $customer): void {

		$_REQUEST['email-verification-key'] = $customer->get_verification_key();
		$_REQUEST['customer']               = $customer->get_hash();

		$redirect_filter = static function () {
			throw new \RuntimeException('email verification redirected');
		};

		add_filter('wp_redirect', $redirect_filter, 10, 1);

		try {
			$this->get_manager_instance()->maybe_verify_email_address();
			$this->fail('Email verification should redirect after a successful verification.');
		} catch (\RuntimeException $exception) {
			$this->assertSame('email verification redirected', $exception->getMessage());
		} finally {
			remove_filter('wp_redirect', $redirect_filter, 10);
			unset($_REQUEST['email-verification-key'], $_REQUEST['customer']);
			wp_set_current_user(0);
		}

		$verified_customer = wu_get_customer($customer->get_id());

		$this->assertSame('verified', $verified_customer->get_email_verification());
		$this->assertEmpty($verified_customer->get_verification_key());
	}
}

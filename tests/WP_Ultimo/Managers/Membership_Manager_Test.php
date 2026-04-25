<?php
/**
 * Test case for Membership Manager.
 *
 * @package WP_Ultimo
 * @subpackage Tests
 */

namespace WP_Ultimo\Tests\Managers;

use WP_Ultimo\Managers\Membership_Manager;
use WP_Ultimo\Models\Membership;
use WP_Ultimo\Models\Customer;
use WP_Ultimo\Models\Product;
use WP_Ultimo\Database\Memberships\Membership_Status;

/**
 * Test Membership Manager functionality.
 */
class Membership_Manager_Test extends \WP_UnitTestCase {

	use Manager_Test_Trait;

	/**
	 * Test customer.
	 *
	 * @var Customer
	 */
	private $customer;

	/**
	 * Test product.
	 *
	 * @var Product
	 */
	private $product;

	protected function get_manager_class(): string {
		return Membership_Manager::class;
	}

	protected function get_expected_slug(): ?string {
		return 'membership';
	}

	protected function get_expected_model_class(): ?string {
		return \WP_Ultimo\Models\Membership::class;
	}

	/**
	 * Set up test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->customer = wu_create_customer(
			[
				'username' => 'testmember' . wp_rand(),
				'email'    => 'testmember' . wp_rand() . '@example.com',
				'password' => 'password123',
			]
		);

		if (is_wp_error($this->customer)) {
			$this->fail('Could not create test customer: ' . $this->customer->get_error_message());
		}

		$this->product = wu_create_product(
			[
				'name'          => 'Test Product',
				'slug'          => 'test-product-' . wp_rand(),
				'description'   => 'A test product',
				'type'          => 'plan',
				'amount'        => 10,
				'duration'      => 1,
				'duration_unit' => 'month',
				'pricing_type'  => 'paid',
			]
		);

		if (is_wp_error($this->product)) {
			$this->fail('Could not create test product: ' . $this->product->get_error_message());
		}
	}

	/**
	 * Clean up after tests.
	 */
	public function tearDown(): void {

		if ($this->customer && ! is_wp_error($this->customer)) {
			$this->customer->delete();
		}
		if ($this->product && ! is_wp_error($this->product)) {
			$this->product->delete();
		}

		// Clean up the WC order stub global between tests.
		unset($GLOBALS['_wu_test_wc_order_email']);

		parent::tearDown();
	}

	/**
	 * Helper to create a membership.
	 *
	 * @param array $overrides Overrides for the membership data.
	 * @return Membership
	 */
	protected function create_membership(array $overrides = []): Membership {

		$defaults = [
			'customer_id'     => $this->customer->get_id(),
			'plan_id'         => $this->product->get_id(),
			'status'          => Membership_Status::ACTIVE,
			'amount'          => 10,
			'currency'        => 'USD',
			'skip_validation' => true,
		];

		$membership = wu_create_membership(array_merge($defaults, $overrides));

		if (is_wp_error($membership)) {
			$this->fail('Could not create test membership: ' . $membership->get_error_message());
		}

		return $membership;
	}

	// ========================================================================
	// init() -- verify hooks are registered
	// ========================================================================

	/**
	 * Test init registers the wu_async_transfer_membership hook.
	 */
	public function test_init_registers_async_transfer_membership_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('wu_async_transfer_membership', [$manager, 'async_transfer_membership'])
		);
	}

	/**
	 * Test init registers the wu_async_delete_membership hook.
	 */
	public function test_init_registers_async_delete_membership_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('wu_async_delete_membership', [$manager, 'async_delete_membership'])
		);
	}

	/**
	 * Test init registers the mark_cancelled_date hook.
	 */
	public function test_init_registers_mark_cancelled_date_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('wu_transition_membership_status', [$manager, 'mark_cancelled_date'])
		);
	}

	/**
	 * Test init registers the transition_membership_status hook.
	 */
	public function test_init_registers_transition_membership_status_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('wu_transition_membership_status', [$manager, 'transition_membership_status'])
		);
	}

	/**
	 * Test init registers the wu_async_membership_swap hook.
	 */
	public function test_init_registers_async_membership_swap_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('wu_async_membership_swap', [$manager, 'async_membership_swap'])
		);
	}

	/**
	 * Test init registers the wp_ajax_wu_publish_pending_site hook.
	 */
	public function test_init_registers_publish_pending_site_ajax_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('wp_ajax_wu_publish_pending_site', [$manager, 'publish_pending_site'])
		);
	}

	/**
	 * Test init registers the wp_ajax_wu_check_pending_site_created hook.
	 */
	public function test_init_registers_check_pending_site_created_ajax_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('wp_ajax_wu_check_pending_site_created', [$manager, 'check_pending_site_created'])
		);
	}

	/**
	 * Test init registers the wu_async_publish_pending_site hook.
	 */
	public function test_init_registers_async_publish_pending_site_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('wu_async_publish_pending_site', [$manager, 'async_publish_pending_site'])
		);
	}

	// ========================================================================
	// mark_cancelled_date()
	// ========================================================================

	/**
	 * Test mark_cancelled_date sets date_cancellation when transitioning to cancelled.
	 */
	public function test_mark_cancelled_date_sets_cancellation_date(): void {

		$membership = $this->create_membership(['status' => Membership_Status::ACTIVE]);
		$manager    = $this->get_manager_instance();

		$this->assertEmpty($membership->get_date_cancellation());

		$manager->mark_cancelled_date(
			Membership_Status::ACTIVE,
			Membership_Status::CANCELLED,
			$membership->get_id()
		);

		$refreshed = wu_get_membership($membership->get_id());

		$this->assertNotEmpty($refreshed->get_date_cancellation());
	}

	/**
	 * Test mark_cancelled_date does NOT set cancellation date when status is not cancelled.
	 */
	public function test_mark_cancelled_date_does_not_set_for_non_cancelled(): void {

		$membership = $this->create_membership(['status' => Membership_Status::PENDING]);
		$manager    = $this->get_manager_instance();

		$manager->mark_cancelled_date(
			Membership_Status::PENDING,
			Membership_Status::ACTIVE,
			$membership->get_id()
		);

		$refreshed = wu_get_membership($membership->get_id());

		$this->assertEmpty($refreshed->get_date_cancellation());
	}

	/**
	 * Test mark_cancelled_date from pending to cancelled.
	 */
	public function test_mark_cancelled_date_from_pending_to_cancelled(): void {

		$membership = $this->create_membership(['status' => Membership_Status::PENDING]);
		$manager    = $this->get_manager_instance();

		$manager->mark_cancelled_date(
			Membership_Status::PENDING,
			Membership_Status::CANCELLED,
			$membership->get_id()
		);

		$refreshed = wu_get_membership($membership->get_id());

		$this->assertNotEmpty($refreshed->get_date_cancellation());
	}

	/**
	 * Test mark_cancelled_date from on-hold to cancelled.
	 */
	public function test_mark_cancelled_date_from_on_hold_to_cancelled(): void {

		$membership = $this->create_membership(['status' => Membership_Status::ON_HOLD]);
		$manager    = $this->get_manager_instance();

		$manager->mark_cancelled_date(
			Membership_Status::ON_HOLD,
			Membership_Status::CANCELLED,
			$membership->get_id()
		);

		$refreshed = wu_get_membership($membership->get_id());

		$this->assertNotEmpty($refreshed->get_date_cancellation());
	}

	// ========================================================================
	// transition_membership_status()
	// ========================================================================

	/**
	 * Test transition from pending to active does not throw errors.
	 */
	public function test_transition_membership_status_pending_to_active(): void {

		$membership = $this->create_membership(['status' => Membership_Status::PENDING]);
		$manager    = $this->get_manager_instance();

		$manager->transition_membership_status(
			Membership_Status::PENDING,
			Membership_Status::ACTIVE,
			$membership->get_id()
		);

		// If we get here without errors, the method executed correctly.
		$this->assertTrue(true);
	}

	/**
	 * Test transition from pending to trialing does not throw errors.
	 */
	public function test_transition_membership_status_pending_to_trialing(): void {

		$membership = $this->create_membership(['status' => Membership_Status::PENDING]);
		$manager    = $this->get_manager_instance();

		$manager->transition_membership_status(
			Membership_Status::PENDING,
			Membership_Status::TRIALING,
			$membership->get_id()
		);

		$this->assertTrue(true);
	}

	/**
	 * Test transition from on_hold to active does not throw errors.
	 */
	public function test_transition_membership_status_on_hold_to_active(): void {

		$membership = $this->create_membership(['status' => Membership_Status::ON_HOLD]);
		$manager    = $this->get_manager_instance();

		$manager->transition_membership_status(
			Membership_Status::ON_HOLD,
			Membership_Status::ACTIVE,
			$membership->get_id()
		);

		$this->assertTrue(true);
	}

	/**
	 * Test transition from active to cancelled is a no-op (old_status not in allowed list).
	 */
	public function test_transition_membership_status_active_to_cancelled_returns_early(): void {

		$membership = $this->create_membership(['status' => Membership_Status::ACTIVE]);
		$manager    = $this->get_manager_instance();

		// This should return early because 'active' is not in allowed_previous_status.
		$manager->transition_membership_status(
			Membership_Status::ACTIVE,
			Membership_Status::CANCELLED,
			$membership->get_id()
		);

		$this->assertTrue(true);
	}

	/**
	 * Test transition from pending to expired is a no-op (new_status not in allowed list).
	 */
	public function test_transition_membership_status_pending_to_expired_returns_early(): void {

		$membership = $this->create_membership(['status' => Membership_Status::PENDING]);
		$manager    = $this->get_manager_instance();

		// This should return early because 'expired' is not in allowed_status.
		$manager->transition_membership_status(
			Membership_Status::PENDING,
			Membership_Status::EXPIRED,
			$membership->get_id()
		);

		$this->assertTrue(true);
	}

	/**
	 * Test transition from pending to on_hold is a no-op (new_status not in allowed list).
	 */
	public function test_transition_membership_status_pending_to_on_hold_returns_early(): void {

		$membership = $this->create_membership(['status' => Membership_Status::PENDING]);
		$manager    = $this->get_manager_instance();

		$manager->transition_membership_status(
			Membership_Status::PENDING,
			Membership_Status::ON_HOLD,
			$membership->get_id()
		);

		$this->assertTrue(true);
	}

	/**
	 * Test transition from expired to active is a no-op (old_status not in allowed list).
	 */
	public function test_transition_membership_status_expired_to_active_returns_early(): void {

		$membership = $this->create_membership(['status' => Membership_Status::EXPIRED]);
		$manager    = $this->get_manager_instance();

		$manager->transition_membership_status(
			Membership_Status::EXPIRED,
			Membership_Status::ACTIVE,
			$membership->get_id()
		);

		$this->assertTrue(true);
	}

	// ========================================================================
	// async_publish_pending_site()
	// ========================================================================

	/**
	 * Test async publish pending site with valid membership ID.
	 */
	public function test_async_publish_pending_site_valid_membership(): void {

		$membership = $this->create_membership();
		$manager    = $this->get_manager_instance();

		$result = $manager->async_publish_pending_site($membership->get_id());

		$this->assertNull($result);
	}

	/**
	 * Test async publish pending site with invalid membership ID.
	 */
	public function test_async_publish_pending_site_invalid_id(): void {

		$manager = $this->get_manager_instance();

		$result = $manager->async_publish_pending_site(99999);

		$this->assertNull($result);
	}

	/**
	 * Test async publish pending site with zero membership ID.
	 */
	public function test_async_publish_pending_site_zero_id(): void {

		$manager = $this->get_manager_instance();

		$result = $manager->async_publish_pending_site(0);

		$this->assertNull($result);
	}

	// ========================================================================
	// async_membership_swap()
	// ========================================================================

	/**
	 * Test async membership swap with a membership that has no scheduled swap.
	 */
	public function test_async_membership_swap_no_scheduled_swap(): void {

		$membership = $this->create_membership();
		$manager    = $this->get_manager_instance();

		// Should return early because there is no scheduled swap.
		$manager->async_membership_swap($membership->get_id());

		$this->assertTrue(true);
	}

	/**
	 * Test async membership swap with invalid membership ID.
	 */
	public function test_async_membership_swap_invalid_id(): void {

		$manager = $this->get_manager_instance();

		$manager->async_membership_swap(99999);

		$this->assertTrue(true);
	}

	// ========================================================================
	// async_transfer_membership()
	// ========================================================================

	/**
	 * Test async transfer membership to a new customer.
	 */
	public function test_async_transfer_membership_success(): void {

		$membership = $this->create_membership();
		$manager    = $this->get_manager_instance();

		$new_customer = wu_create_customer(
			[
				'username' => 'transfer_target_' . wp_rand(),
				'email'    => 'transfer_target_' . wp_rand() . '@example.com',
				'password' => 'password123',
			]
		);

		if (is_wp_error($new_customer)) {
			$this->fail('Could not create target customer: ' . $new_customer->get_error_message());
		}

		$manager->async_transfer_membership($membership->get_id(), $new_customer->get_id());

		$refreshed = wu_get_membership($membership->get_id());

		$this->assertEquals($new_customer->get_id(), $refreshed->get_customer_id());

		$new_customer->delete();
	}

	/**
	 * Test async transfer membership with invalid membership ID.
	 */
	public function test_async_transfer_membership_invalid_membership_id(): void {

		$manager = $this->get_manager_instance();

		$new_customer = wu_create_customer(
			[
				'username' => 'transfer_invalid_' . wp_rand(),
				'email'    => 'transfer_invalid_' . wp_rand() . '@example.com',
				'password' => 'password123',
			]
		);

		if (is_wp_error($new_customer)) {
			$this->fail('Could not create target customer: ' . $new_customer->get_error_message());
		}

		// Should return early without error.
		$manager->async_transfer_membership(99999, $new_customer->get_id());

		$this->assertTrue(true);

		$new_customer->delete();
	}

	/**
	 * Test async transfer membership with invalid target customer ID.
	 */
	public function test_async_transfer_membership_invalid_customer_id(): void {

		$membership = $this->create_membership();
		$manager    = $this->get_manager_instance();

		// Should return early without error because target customer does not exist.
		$manager->async_transfer_membership($membership->get_id(), 99999);

		// Membership should remain unchanged.
		$refreshed = wu_get_membership($membership->get_id());
		$this->assertEquals($this->customer->get_id(), $refreshed->get_customer_id());
	}

	/**
	 * Test async transfer membership to same customer is a no-op.
	 */
	public function test_async_transfer_membership_same_customer_is_noop(): void {

		$membership = $this->create_membership();
		$manager    = $this->get_manager_instance();

		// Transferring to the same customer should be a no-op.
		$manager->async_transfer_membership($membership->get_id(), $this->customer->get_id());

		$refreshed = wu_get_membership($membership->get_id());
		$this->assertEquals($this->customer->get_id(), $refreshed->get_customer_id());
	}

	// ========================================================================
	// async_delete_membership()
	// ========================================================================

	/**
	 * Test async delete membership.
	 */
	public function test_async_delete_membership_success(): void {

		$membership = $this->create_membership();
		$manager    = $this->get_manager_instance();

		$membership_id = $membership->get_id();

		$manager->async_delete_membership($membership_id);

		$deleted = wu_get_membership($membership_id);

		$this->assertEmpty($deleted);
	}

	/**
	 * Test async delete membership with invalid ID.
	 */
	public function test_async_delete_membership_invalid_id(): void {

		$manager = $this->get_manager_instance();

		// Should return early without error.
		$manager->async_delete_membership(99999);

		$this->assertTrue(true);
	}

	/**
	 * Test async delete membership with zero ID.
	 */
	public function test_async_delete_membership_zero_id(): void {

		$manager = $this->get_manager_instance();

		$manager->async_delete_membership(0);

		$this->assertTrue(true);
	}

	// ========================================================================
	// Integration: status transition fires action
	// ========================================================================

	/**
	 * Test that wu_transition_membership_status action fires when membership status changes.
	 */
	public function test_wu_transition_membership_status_action_fires(): void {

		$membership = $this->create_membership(['status' => Membership_Status::PENDING]);

		$fired = false;

		add_action(
			'wu_transition_membership_status',
			function ($old_status, $new_status, $id) use (&$fired, $membership) {
				if ($id === $membership->get_id()) {
					$fired = true;
				}
			},
			1,
			3
		);

		$membership->set_status(Membership_Status::ACTIVE);
		$membership->save();

		$this->assertTrue($fired);
	}

	// ========================================================================
	// Edge cases
	// ========================================================================

	/**
	 * Test LOG_FILE_NAME constant.
	 */
	public function test_log_file_name_constant(): void {

		$this->assertEquals('memberships', Membership_Manager::LOG_FILE_NAME);
	}

	// ========================================================================
	// email verification gate — GH#935
	// ========================================================================

	/**
	 * When enable_email_verification=always and the customer has not yet verified
	 * their email, transitioning from pending → active must NOT publish the pending
	 * site. The site is held until the customer completes email verification.
	 */
	public function test_transition_status_skips_publish_when_customer_email_pending(): void {

		// Make publishing synchronous so we can detect it via a hook.
		wu_save_setting('force_publish_sites_sync', true);

		// Create a customer whose email is still pending verification.
		$this->customer->set_email_verification('pending');
		$this->customer->save();

		$membership = $this->create_membership(['status' => Membership_Status::PENDING]);

		$membership->create_pending_site(
			[
				'title' => 'Email Pending Site',
				'path'  => '/emailpending/',
			]
		);

		// Track whether wu_before_pending_site_published fires.
		$published = false;
		add_action(
			'wu_before_pending_site_published',
			function () use (&$published) {
				$published = true;
			}
		);

		$manager = $this->get_manager_instance();

		$manager->transition_membership_status(
			Membership_Status::PENDING,
			Membership_Status::ACTIVE,
			$membership->get_id()
		);

		$this->assertFalse(
			$published,
			'Site must not be published while customer email is pending verification (GH#935).'
		);

		// Restore setting.
		wu_save_setting('force_publish_sites_sync', false);
	}

	/**
	 * When the customer has already verified their email (email_verification=none),
	 * transitioning from pending → active must publish the pending site normally.
	 */
	public function test_transition_status_publishes_when_customer_email_not_pending(): void {

		// Make publishing synchronous so we can detect it via a hook.
		wu_save_setting('force_publish_sites_sync', true);

		// Customer email is verified (default: none).
		$this->customer->set_email_verification('none');
		$this->customer->save();

		$membership = $this->create_membership(['status' => Membership_Status::PENDING]);

		$membership->create_pending_site(
			[
				'title' => 'Email Verified Site',
				'path'  => '/emailverified/',
			]
		);

		$published = false;
		add_action(
			'wu_before_pending_site_published',
			function () use (&$published) {
				$published = true;
			}
		);

		$manager = $this->get_manager_instance();

		$manager->transition_membership_status(
			Membership_Status::PENDING,
			Membership_Status::ACTIVE,
			$membership->get_id()
		);

		$this->assertTrue(
			$published,
			'Site must be published when customer email is not pending verification.'
		);

		// Restore setting.
		wu_save_setting('force_publish_sites_sync', false);
	}

	// ========================================================================
	// handle_pending_site_on_cancellation()
	// ========================================================================

	/**
	 * Test init registers the handle_pending_site_on_cancellation hook.
	 */
	public function test_init_registers_handle_pending_site_on_cancellation_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('wu_transition_membership_status', [$manager, 'handle_pending_site_on_cancellation'])
		);
	}

	/**
	 * Test pending_site is deleted from membership meta on cancellation.
	 */
	public function test_handle_pending_site_on_cancellation_cleans_up_pending_site(): void {

		$membership = $this->create_membership(['status' => Membership_Status::ACTIVE]);
		$manager    = $this->get_manager_instance();

		$membership->create_pending_site(
			[
				'title' => 'Test Pending Site',
				'path'  => '/testpending/',
			]
		);

		$this->assertNotFalse($membership->get_pending_site(), 'pending_site should exist before cancellation');

		$manager->handle_pending_site_on_cancellation(
			Membership_Status::ACTIVE,
			Membership_Status::CANCELLED,
			$membership->get_id()
		);

		$refreshed = wu_get_membership($membership->get_id());

		$this->assertFalse($refreshed->get_pending_site(), 'pending_site should be removed after cancellation');
	}

	/**
	 * Test transient is set with the correct key and 24-hour TTL on cancellation.
	 */
	public function test_handle_pending_site_on_cancellation_sets_transient_with_correct_key(): void {

		$membership = $this->create_membership(['status' => Membership_Status::ACTIVE]);
		$manager    = $this->get_manager_instance();

		$membership->create_pending_site(
			[
				'title' => 'Transient Test Site',
				'path'  => '/transientpending/',
			]
		);

		$email         = $this->customer->get_email_address();
		$transient_key = 'wu_transferable_pending_' . md5($email);

		// Ensure transient does not exist before the call.
		delete_transient($transient_key);
		$this->assertFalse(get_transient($transient_key), 'transient should not exist before cancellation');

		$manager->handle_pending_site_on_cancellation(
			Membership_Status::ACTIVE,
			Membership_Status::CANCELLED,
			$membership->get_id()
		);

		$stored = get_transient($transient_key);

		$this->assertNotFalse($stored, 'transient should be set after cancellation');
	}

	/**
	 * Test no transient is set and no error occurs when there is no pending_site.
	 */
	public function test_handle_pending_site_on_cancellation_skips_when_no_pending_site(): void {

		$membership = $this->create_membership(['status' => Membership_Status::ACTIVE]);
		$manager    = $this->get_manager_instance();

		// Confirm no pending site exists.
		$this->assertFalse($membership->get_pending_site());

		$email         = $this->customer->get_email_address();
		$transient_key = 'wu_transferable_pending_' . md5($email);
		delete_transient($transient_key);

		$manager->handle_pending_site_on_cancellation(
			Membership_Status::ACTIVE,
			Membership_Status::CANCELLED,
			$membership->get_id()
		);

		$this->assertFalse(get_transient($transient_key), 'transient should NOT be set when there is no pending_site');
	}

	/**
	 * Test non-cancellation transitions do not affect pending_site.
	 */
	public function test_handle_pending_site_on_cancellation_ignores_non_cancelled_status(): void {

		$membership = $this->create_membership(['status' => Membership_Status::ACTIVE]);
		$manager    = $this->get_manager_instance();

		$membership->create_pending_site(
			[
				'title' => 'Should Stay',
				'path'  => '/shouldstay/',
			]
		);

		// Trigger with a non-cancelled new status.
		$manager->handle_pending_site_on_cancellation(
			Membership_Status::ACTIVE,
			Membership_Status::ON_HOLD,
			$membership->get_id()
		);

		$refreshed = wu_get_membership($membership->get_id());

		$this->assertNotFalse($refreshed->get_pending_site(), 'pending_site should remain intact for non-cancelled transitions');
	}

	// ========================================================================
	// reclaim_pending_site_on_wc_order_completion()
	// ========================================================================

	/**
	 * Test init registers the woocommerce_order_status_completed hook.
	 */
	public function test_init_registers_wc_order_completion_hook(): void {

		$manager = $this->get_manager_instance();

		$this->assertIsInt(
			has_action('woocommerce_order_status_completed', [$manager, 'reclaim_pending_site_on_wc_order_completion'])
		);
	}

	/**
	 * Test that the method bails gracefully when wc_get_order() returns false
	 * (i.e. the order does not exist).
	 *
	 * The global _wu_test_wc_order_email is intentionally NOT set so the stub
	 * returns false, simulating a missing order.
	 */
	public function test_reclaim_bails_when_order_not_found(): void {

		$manager = $this->get_manager_instance();

		// _wu_test_wc_order_email is NOT set, so the stub returns false.
		unset($GLOBALS['_wu_test_wc_order_email']);

		$membership = $this->create_membership(['status' => Membership_Status::CANCELLED]);

		$manager->reclaim_pending_site_on_wc_order_completion(999);

		// Membership should remain unchanged.
		$refreshed = wu_get_membership($membership->get_id());

		$this->assertSame(
			Membership_Status::CANCELLED,
			$refreshed->get_status(),
			'Membership should remain cancelled when order is not found.'
		);
	}

	/**
	 * Test the full reclaim flow: cancelled membership with transient is
	 * reactivated, pending_site attached, transient deleted, and publish triggered.
	 */
	public function test_reclaim_pending_site_reactivates_membership_and_clears_transient(): void {

		$manager = $this->get_manager_instance();

		// Create a cancelled membership for our test customer.
		$membership = $this->create_membership(['status' => Membership_Status::CANCELLED]);

		// Create and store a pending_site in the transient, mirroring the cancellation flow.
		$membership->create_pending_site(
			[
				'title' => 'Reclaim Test Site',
				'path'  => '/reclaimtest/',
			]
		);

		$email         = $this->customer->get_email_address();
		$transient_key = 'wu_transferable_pending_' . md5($email);
		$pending_site  = $membership->get_pending_site();

		set_transient($transient_key, $pending_site, DAY_IN_SECONDS);

		// Remove pending_site from membership to simulate post-cancellation state.
		$membership->delete_pending_site();

		$this->assertFalse($membership->get_pending_site(), 'pending_site should be absent before reclaim');
		$this->assertNotFalse(get_transient($transient_key), 'transient should exist before reclaim');

		// Fake WC order ID — wc_get_order stub is expected to return an object with this email.
		$fake_order_id              = 42;
		$GLOBALS['_wu_test_wc_order_email'] = $email;

		$manager->reclaim_pending_site_on_wc_order_completion($fake_order_id);

		// Transient must be deleted after successful reclaim.
		$this->assertFalse(get_transient($transient_key), 'transient should be deleted after reclaim');

		// Membership must be reactivated.
		$refreshed = wu_get_membership($membership->get_id());

		$this->assertSame(
			Membership_Status::ACTIVE,
			$refreshed->get_status(),
			'membership status should be active after reclaim'
		);

		// pending_site should now be attached to the membership.
		$this->assertNotFalse($refreshed->get_pending_site(), 'pending_site should be re-attached to the membership');
	}

	/**
	 * Test that reclaim does nothing when no transient exists for the billing email.
	 */
	public function test_reclaim_skips_when_no_transient(): void {

		$manager = $this->get_manager_instance();

		$membership = $this->create_membership(['status' => Membership_Status::CANCELLED]);

		$email                              = $this->customer->get_email_address();
		$transient_key                      = 'wu_transferable_pending_' . md5($email);
		$GLOBALS['_wu_test_wc_order_email'] = $email;

		// Ensure no transient is set.
		delete_transient($transient_key);

		$manager->reclaim_pending_site_on_wc_order_completion(42);

		// Membership must stay cancelled — nothing was reclaimed.
		$refreshed = wu_get_membership($membership->get_id());

		$this->assertSame(
			Membership_Status::CANCELLED,
			$refreshed->get_status(),
			'membership should remain cancelled when no transient exists'
		);
	}
}

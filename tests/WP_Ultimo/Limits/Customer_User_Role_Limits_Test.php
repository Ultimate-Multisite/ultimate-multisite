<?php
namespace WP_Ultimo\Limits;

use WP_Ultimo\Database\Sites\Site_Type;
use WP_Ultimo\Models\Site;
use WP_Ultimo\Objects\Limitations;

class Customer_User_Role_Limits_Test extends \WP_UnitTestCase {

	/**
	 * Test site for tests.
	 *
	 * @var Site
	 */
	private static $test_site;

	protected function setUp(): void {
		parent::setUp();

		// Ensure no user is logged in by default
		wp_set_current_user(0);

		// Reset Limitations early cache between tests
		$ref  = new \ReflectionClass(Limitations::class);
		$prop = $ref->getProperty('limitations_cache');

		// Only call setAccessible() on PHP < 8.1 where it's needed
		if (PHP_VERSION_ID < 80100) {
			$prop->setAccessible(true);
		}

		$prop->setValue(null, []);

		// Create a test site
		self::$test_site = wu_create_site(
			[
				'title'       => 'Test Site',
				'domain'      => 'test-site5.example.com',
				'template_id' => 1,
				'type'        => Site_Type::CUSTOMER_OWNED,
			]
		);
		// Remove any pre-existing site limitations
		$blog_id = get_current_blog_id();
		delete_metadata('blog', $blog_id, 'wu_limitations');
	}


	/**
	 * Clean up after tests.
	 */
	public static function tear_down_after_class() {
		parent::tear_down_after_class();

		if (self::$test_site) {
			self::$test_site->delete();
		}
	}

	public function test_filter_editable_roles_returns_original_on_frontend_for_visitors(): void {
		$instance = Customer_User_Role_Limits::get_instance();

		// Simulate visitor on frontend (no current screen and no user)
		wp_set_current_user(0);
		if (function_exists('set_current_screen')) {
			// Clear current screen to ensure is_admin() is false
			set_current_screen('front');
		}

		$roles = [
			'subscriber'  => ['name' => 'Subscriber'],
			'contributor' => ['name' => 'Contributor'],
		];

		$result = $instance->filter_editable_roles($roles);

		$this->assertSame($roles, $result, 'Roles should remain unchanged for visitors on the frontend.');
	}

	public function test_filter_editable_roles_returns_original_in_admin_when_not_logged_in(): void {
		$instance = Customer_User_Role_Limits::get_instance();

		// Admin screen but still not logged in
		if (function_exists('set_current_screen')) {
			set_current_screen('dashboard');
		}
		wp_set_current_user(0);

		$roles = [
			'subscriber'  => ['name' => 'Subscriber'],
			'contributor' => ['name' => 'Contributor'],
		];

		$result = $instance->filter_editable_roles($roles);

		$this->assertSame($roles, $result, 'Roles should remain unchanged in admin when user is not logged in.');
	}

	public function test_filter_editable_roles_removes_role_when_over_limit_in_admin(): void {
		$instance = Customer_User_Role_Limits::get_instance();

		switch_to_blog(static::$test_site->get_id());
		// Set admin user and admin screen
		$admin_id = self::factory()->user->create(['role' => 'administrator']);
		wp_set_current_user($admin_id);
		if (function_exists('revoke_super_admin')) {
			// Ensure wp_users.can is an array before calling revoke_super_admin
			// (required on some WP versions to avoid a fatal error)
			wp_cache_flush();
			if (!is_array(get_option('wp_users.can'))) {
				update_option('wp_users.can', ['list_users' => true, 'promote_users' => true, 'remove_users' => true, 'edit_users' => true]);
			}
			revoke_super_admin($admin_id);
		}
		if (function_exists('set_current_screen')) {
			set_current_screen('users');
		}

		// Create two subscriber users assigned to this blog
		$blog_id = get_current_blog_id();
		$u1      = self::factory()->user->create(['role' => 'subscriber']);
		$u2      = self::factory()->user->create(['role' => 'subscriber']);
		add_user_to_blog($blog_id, $u1, 'subscriber');
		add_user_to_blog($blog_id, $u2, 'subscriber');

		// Enable users limitation with subscriber limit = 1 (so we are over the limit)
		$limitations = [
			'users' => [
				'enabled' => true,
				'limit'   => [
					'subscriber'  => [
						'enabled' => true,
						'number'  => 1,
					],
					'contributor' => [
						'enabled' => true,
						'number'  => 0,
					], // unlimited
				],
			],
		];

		// Persist blog limitations and reset cache
		delete_metadata('blog', $blog_id, 'wu_limitations');
		add_metadata('blog', $blog_id, 'wu_limitations', $limitations, true);
		$ref  = new \ReflectionClass(Limitations::class);
		$prop = $ref->getProperty('limitations_cache');

		// Only call setAccessible() on PHP < 8.1 where it's needed
		if (PHP_VERSION_ID < 80100) {
			$prop->setAccessible(true);
		}

		$prop->setValue(null, []);

		$roles = [
			'subscriber'    => ['name' => 'Subscriber'],
			'contributor'   => ['name' => 'Contributor'],
			'administrator' => ['name' => 'Administrator'],
		];

		$filtered = $instance->filter_editable_roles($roles);

		// Subscriber should be removed due to over the limit
		$this->assertArrayNotHasKey('subscriber', $filtered);
		// Other roles should remain available
		$this->assertArrayHasKey('contributor', $filtered);
		$this->assertArrayHasKey('administrator', $filtered);
		restore_current_blog();
	}

	/**
	 * Test handle_downgrade method exists.
	 */
	public function test_handle_downgrade_exists(): void {

		$instance = Customer_User_Role_Limits::get_instance();

		$this->assertTrue(method_exists($instance, 'handle_downgrade'));
	}

	/**
	 * Test handle_downgrade returns early for invalid membership ID.
	 */
	public function test_handle_downgrade_invalid_membership(): void {

		$instance = Customer_User_Role_Limits::get_instance();

		// Should not throw with an invalid membership ID.
		$instance->handle_downgrade(0);

		$this->assertTrue(true);
	}

	/**
	 * Test handle_downgrade removes excess users per role on downgrade.
	 */
	public function test_handle_downgrade_removes_excess_users(): void {

		$instance = Customer_User_Role_Limits::get_instance();

		// Create a product with a user limit of 1 subscriber.
		$product = wu_create_product(
			[
				'name'  => 'User Limit Plan',
				'slug'  => 'user-limit-plan-' . wp_rand(),
				'type'  => 'plan',
				'price' => 10,
			]
		);

		$this->assertNotWPError($product);

		$product->update_meta(
			'wu_limitations',
			[
				'users' => [
					'enabled' => true,
					'limit'   => [
						'subscriber' => [
							'enabled' => true,
							'number'  => 1,
						],
					],
				],
			]
		);

		$owner_user_id = self::factory()->user->create();

		$customer = wu_create_customer(
			[
				'user_id' => $owner_user_id,
			]
		);

		$this->assertNotWPError($customer);

		$membership = wu_create_membership(
			[
				'customer_id' => $customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => 'active',
			]
		);

		$this->assertNotWPError($membership);

		// Create a site and attach it to the membership.
		$site = wu_create_site(
			[
				'title'       => 'User Limit Test Site',
				'domain'      => 'user-limit-' . wp_rand() . '.example.com',
				'template_id' => 1,
				'type'        => 'customer_owned',
			]
		);

		$this->assertNotWPError($site);

		// Attach site to membership via set_membership_id.
		$site->set_membership_id($membership->get_id());
		$site->save();

		$blog_id = $site->get_id();

		switch_to_blog($blog_id);

		// Add 3 subscriber users — 2 over the limit of 1.
		$sub1 = self::factory()->user->create();
		$sub2 = self::factory()->user->create();
		$sub3 = self::factory()->user->create();
		add_user_to_blog($blog_id, $sub1, 'subscriber');
		add_user_to_blog($blog_id, $sub2, 'subscriber');
		add_user_to_blog($blog_id, $sub3, 'subscriber');

		$count_before = count(get_users(['blog_id' => $blog_id, 'role' => 'subscriber', 'fields' => 'ID']));

		restore_current_blog();

		// Run the downgrade handler.
		$instance->handle_downgrade($membership->get_id());

		switch_to_blog($blog_id);

		$count_after = count(get_users(['blog_id' => $blog_id, 'role' => 'subscriber', 'fields' => 'ID']));

		// After downgrade, subscriber count should be at or below the limit.
		$this->assertLessThanOrEqual(1, $count_after, 'Excess subscribers should be removed after downgrade.');
		$this->assertLessThan($count_before, $count_after, 'Some subscribers should have been removed.');

		restore_current_blog();

		// Clean up.
		$site->delete();
	}

	/**
	 * Test handle_downgrade never removes the membership owner.
	 */
	public function test_handle_downgrade_preserves_owner(): void {

		$instance = Customer_User_Role_Limits::get_instance();

		// Create a product with a user limit of 0 subscribers (unlimited).
		$product = wu_create_product(
			[
				'name'  => 'Owner Preserve Plan',
				'slug'  => 'owner-preserve-plan-' . wp_rand(),
				'type'  => 'plan',
				'price' => 10,
			]
		);

		$this->assertNotWPError($product);

		$product->update_meta(
			'wu_limitations',
			[
				'users' => [
					'enabled' => true,
					'limit'   => [
						'subscriber' => [
							'enabled' => true,
							'number'  => 1,
						],
					],
				],
			]
		);

		$owner_user_id = self::factory()->user->create();

		$customer = wu_create_customer(
			[
				'user_id' => $owner_user_id,
			]
		);

		$this->assertNotWPError($customer);

		$membership = wu_create_membership(
			[
				'customer_id' => $customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => 'active',
			]
		);

		$this->assertNotWPError($membership);

		$site = wu_create_site(
			[
				'title'       => 'Owner Preserve Site',
				'domain'      => 'owner-preserve-' . wp_rand() . '.example.com',
				'template_id' => 1,
				'type'        => 'customer_owned',
			]
		);

		$this->assertNotWPError($site);

		// Attach site to membership via set_membership_id.
		$site->set_membership_id($membership->get_id());
		$site->save();

		$blog_id = $site->get_id();

		switch_to_blog($blog_id);

		// Add the owner as a subscriber and two other subscribers.
		add_user_to_blog($blog_id, $owner_user_id, 'subscriber');
		$other1 = self::factory()->user->create();
		$other2 = self::factory()->user->create();
		add_user_to_blog($blog_id, $other1, 'subscriber');
		add_user_to_blog($blog_id, $other2, 'subscriber');

		restore_current_blog();

		$instance->handle_downgrade($membership->get_id());

		switch_to_blog($blog_id);

		$remaining_ids = get_users(['blog_id' => $blog_id, 'role' => 'subscriber', 'fields' => 'ID']);

		// The owner should never be removed.
		$this->assertContains($owner_user_id, $remaining_ids, 'Membership owner should never be removed during downgrade.');

		restore_current_blog();

		// Clean up.
		$site->delete();
	}

	/**
	 * Test handle_downgrade respects wu_membership_downgrade_user_roles filter.
	 */
	public function test_handle_downgrade_filter_can_prevent_removal(): void {

		$instance = Customer_User_Role_Limits::get_instance();

		// Prevent removal via filter.
		add_filter(
			'wu_membership_downgrade_user_roles',
			function() {
				return []; // Return empty to prevent removal.
			}
		);

		$product = wu_create_product(
			[
				'name'  => 'Filter User Plan',
				'slug'  => 'filter-user-plan-' . wp_rand(),
				'type'  => 'plan',
				'price' => 10,
			]
		);

		$this->assertNotWPError($product);

		$product->update_meta(
			'wu_limitations',
			[
				'users' => [
					'enabled' => true,
					'limit'   => [
						'subscriber' => [
							'enabled' => true,
							'number'  => 1,
						],
					],
				],
			]
		);

		$customer = wu_create_customer(
			[
				'user_id' => self::factory()->user->create(),
			]
		);

		$this->assertNotWPError($customer);

		$membership = wu_create_membership(
			[
				'customer_id' => $customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => 'active',
			]
		);

		$this->assertNotWPError($membership);

		$site = wu_create_site(
			[
				'title'       => 'Filter User Site',
				'domain'      => 'filter-user-' . wp_rand() . '.example.com',
				'template_id' => 1,
				'type'        => 'customer_owned',
			]
		);

		$this->assertNotWPError($site);

		// Attach site to membership via set_membership_id.
		$site->set_membership_id($membership->get_id());
		$site->save();

		$blog_id = $site->get_id();

		switch_to_blog($blog_id);

		$sub1 = self::factory()->user->create();
		$sub2 = self::factory()->user->create();
		$sub3 = self::factory()->user->create();
		add_user_to_blog($blog_id, $sub1, 'subscriber');
		add_user_to_blog($blog_id, $sub2, 'subscriber');
		add_user_to_blog($blog_id, $sub3, 'subscriber');

		$count_before = count(get_users(['blog_id' => $blog_id, 'role' => 'subscriber', 'fields' => 'ID']));

		restore_current_blog();

		$instance->handle_downgrade($membership->get_id());

		switch_to_blog($blog_id);

		$count_after = count(get_users(['blog_id' => $blog_id, 'role' => 'subscriber', 'fields' => 'ID']));

		// Filter prevented removal — count should be unchanged.
		$this->assertSame($count_before, $count_after, 'Filter should prevent users from being removed.');

		restore_current_blog();

		remove_all_filters('wu_membership_downgrade_user_roles');

		// Clean up.
		$site->delete();
	}
}

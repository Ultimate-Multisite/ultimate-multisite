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
	public function test_handle_downgrade_method_exists(): void {

		$instance = Customer_User_Role_Limits::get_instance();

		$this->assertTrue(method_exists($instance, 'handle_downgrade'));
	}

	/**
	 * Test handle_downgrade returns early for invalid membership ID.
	 */
	public function test_handle_downgrade_invalid_membership(): void {

		$instance = Customer_User_Role_Limits::get_instance();

		// Should not throw — wu_get_membership(0) returns false.
		$instance->handle_downgrade(0);

		$this->assertTrue(true);
	}

	/**
	 * Test handle_downgrade demotes excess users to subscriber when over role quota.
	 */
	public function test_handle_downgrade_demotes_excess_users(): void {

		$instance = Customer_User_Role_Limits::get_instance();

		$product = wu_create_product(
			[
				'name'  => 'Role Limit Plan',
				'slug'  => 'role-limit-plan-' . wp_rand(),
				'type'  => 'plan',
				'price' => 10,
			]
		);

		$this->assertNotWPError($product);

		// Set a users limitation: max 1 editor.
		$product->update_meta(
			'wu_limitations',
			[
				'users' => [
					'enabled' => true,
					'limit'   => [
						'editor' => [
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

		$site = wu_create_site(
			[
				'title'       => 'Role Downgrade Site',
				'domain'      => 'role-downgrade-' . wp_rand() . '.example.com',
				'template_id' => 1,
				'type'        => Site_Type::CUSTOMER_OWNED,
			]
		);

		$this->assertNotWPError($site);

		$membership = wu_create_membership(
			[
				'customer_id' => $customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => 'active',
			]
		);

		$this->assertNotWPError($membership);

		$site->update_meta('wu_membership_id', $membership->get_id());

		$blog_id = $site->get_id();

		switch_to_blog($blog_id);

		// Create 3 editor users on the site (limit is 1, so 2 should be demoted).
		$editor_ids = [];

		for ($i = 0; $i < 3; $i++) {
			$uid          = self::factory()->user->create();
			$editor_ids[] = $uid;
			add_user_to_blog($blog_id, $uid, 'editor');
		}

		restore_current_blog();

		$instance->handle_downgrade($membership->get_id());

		switch_to_blog($blog_id);

		// Sort descending by ID — highest IDs are demoted first.
		rsort($editor_ids);

		// The 2 most recently added editors (highest IDs) should be demoted to subscriber.
		$user_0 = new \WP_User($editor_ids[0], '', $blog_id);
		$user_1 = new \WP_User($editor_ids[1], '', $blog_id);

		$this->assertTrue(in_array('subscriber', $user_0->roles, true), 'Most recently added editor should be demoted to subscriber.');
		$this->assertTrue(in_array('subscriber', $user_1->roles, true), 'Second most recently added editor should be demoted to subscriber.');

		// The oldest editor (lowest ID) should remain as editor.
		$user_2 = new \WP_User($editor_ids[2], '', $blog_id);

		$this->assertTrue(in_array('editor', $user_2->roles, true), 'Oldest editor should remain as editor.');

		restore_current_blog();
	}

	/**
	 * Test handle_downgrade fires wu_customer_user_role_downgrade_demoted action.
	 */
	public function test_handle_downgrade_fires_demoted_action(): void {

		$demoted_user_ids = [];

		add_action(
			'wu_customer_user_role_downgrade_demoted',
			function($user_id) use (&$demoted_user_ids) {
				$demoted_user_ids[] = $user_id;
			}
		);

		$product = wu_create_product(
			[
				'name'  => 'Demote Action Plan',
				'slug'  => 'demote-action-plan-' . wp_rand(),
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
						'editor' => [
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

		$site = wu_create_site(
			[
				'title'       => 'Demote Action Site',
				'domain'      => 'demote-action-' . wp_rand() . '.example.com',
				'template_id' => 1,
				'type'        => Site_Type::CUSTOMER_OWNED,
			]
		);

		$this->assertNotWPError($site);

		$membership = wu_create_membership(
			[
				'customer_id' => $customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => 'active',
			]
		);

		$this->assertNotWPError($membership);

		$site->update_meta('wu_membership_id', $membership->get_id());

		$blog_id = $site->get_id();

		switch_to_blog($blog_id);

		// Create 2 editors (limit is 1, so 1 should be demoted).
		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		add_user_to_blog($blog_id, $u1, 'editor');
		add_user_to_blog($blog_id, $u2, 'editor');

		restore_current_blog();

		$instance = Customer_User_Role_Limits::get_instance();

		$instance->handle_downgrade($membership->get_id());

		$this->assertCount(1, $demoted_user_ids, 'Exactly one user should have been demoted.');
	}

	/**
	 * Test handle_downgrade does not demote users when within quota.
	 */
	public function test_handle_downgrade_no_demotion_within_quota(): void {

		$demoted_user_ids = [];

		add_action(
			'wu_customer_user_role_downgrade_demoted',
			function($user_id) use (&$demoted_user_ids) {
				$demoted_user_ids[] = $user_id;
			}
		);

		$product = wu_create_product(
			[
				'name'  => 'Within Role Quota Plan',
				'slug'  => 'within-role-quota-plan-' . wp_rand(),
				'type'  => 'plan',
				'price' => 10,
			]
		);

		$this->assertNotWPError($product);

		// Set a limit of 5 editors — we'll only create 2.
		$product->update_meta(
			'wu_limitations',
			[
				'users' => [
					'enabled' => true,
					'limit'   => [
						'editor' => [
							'enabled' => true,
							'number'  => 5,
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

		$site = wu_create_site(
			[
				'title'       => 'Within Role Quota Site',
				'domain'      => 'within-role-quota-' . wp_rand() . '.example.com',
				'template_id' => 1,
				'type'        => Site_Type::CUSTOMER_OWNED,
			]
		);

		$this->assertNotWPError($site);

		$membership = wu_create_membership(
			[
				'customer_id' => $customer->get_id(),
				'plan_id'     => $product->get_id(),
				'status'      => 'active',
			]
		);

		$this->assertNotWPError($membership);

		$site->update_meta('wu_membership_id', $membership->get_id());

		$blog_id = $site->get_id();

		switch_to_blog($blog_id);

		$u1 = self::factory()->user->create();
		$u2 = self::factory()->user->create();
		add_user_to_blog($blog_id, $u1, 'editor');
		add_user_to_blog($blog_id, $u2, 'editor');

		restore_current_blog();

		$instance = Customer_User_Role_Limits::get_instance();

		$instance->handle_downgrade($membership->get_id());

		$this->assertEmpty($demoted_user_ids, 'No users should be demoted when within quota.');
	}
}

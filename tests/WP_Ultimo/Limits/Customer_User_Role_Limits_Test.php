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
			// Ensure wp_users.can is properly set as an array before calling revoke_super_admin
			// Clear all WordPress caches to ensure fresh option load
			wp_cache_flush();
			
			$wp_users_can = get_option('wp_users.can');
			echo 'wp_users.can type: ' . gettype($wp_users_can) . PHP_EOL;
			echo 'wp_users.can value: ';
			var_export($wp_users_can);
			echo PHP_EOL;
			
			if (!is_array($wp_users_can)) {
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
}
